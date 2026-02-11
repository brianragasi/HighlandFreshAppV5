<?php
/**
 * Highland Fresh System - Sales Invoices API
 * 
 * CSI (Charge Sales Invoice) management for Sales Custodian
 * 
 * GET actions: list, detail, unpaid, aging_report
 * POST actions: create_csi, record_payment
 * PUT actions: void
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Sales Custodian or GM role
$currentUser = Auth::requireRole(['sales_custodian', 'general_manager']);

$action = getParam('action', 'list');

// Valid payment statuses
$validPaymentStatuses = ['unpaid', 'partial', 'paid'];

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action);
            break;
        case 'POST':
            handlePost($db, $action, $currentUser);
            break;
        case 'PUT':
            handlePut($db, $action, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Sales Invoices API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

/**
 * Generate CSI number: CSI-YYYY-XXXXX (sequential, no gaps)
 */
function generateCSINumber($db) {
    $yearPrefix = 'CSI-' . date('Y') . '-';
    
    // Find the next sequence number for this year
    $stmt = $db->prepare("
        SELECT MAX(CAST(SUBSTRING(csi_number, 10) AS UNSIGNED)) as max_seq 
        FROM sales_invoices 
        WHERE csi_number LIKE ?
    ");
    $stmt->execute([$yearPrefix . '%']);
    $maxSeq = $stmt->fetch()['max_seq'] ?? 0;
    
    return $yearPrefix . str_pad($maxSeq + 1, 5, '0', STR_PAD_LEFT);
}

/**
 * Handle GET requests
 */
function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            $customerId = getParam('customer_id');
            $paymentStatus = getParam('payment_status');
            $dateFrom = getParam('date_from');
            $dateTo = getParam('date_to');
            $search = getParam('search');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $sql = "
                SELECT i.*, c.name as customer_name, c.customer_type, c.customer_code
                FROM sales_invoices i
                LEFT JOIN customers c ON i.customer_id = c.id
                WHERE i.status = 'active'
            ";
            $params = [];
            
            if ($customerId) {
                $sql .= " AND i.customer_id = ?";
                $params[] = $customerId;
            }
            
            if ($paymentStatus && in_array($paymentStatus, ['unpaid', 'partial', 'paid'])) {
                $sql .= " AND i.payment_status = ?";
                $params[] = $paymentStatus;
            }
            
            if ($dateFrom) {
                $sql .= " AND i.invoice_date >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $sql .= " AND i.invoice_date <= ?";
                $params[] = $dateTo;
            }
            
            if ($search) {
                $sql .= " AND (i.csi_number LIKE ? OR i.dr_number LIKE ? OR c.name LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Get total count
            $countSql = str_replace("SELECT i.*, c.name as customer_name, c.customer_type, c.customer_code", "SELECT COUNT(*) as total", $sql);
            $countStmt = $db->prepare($countSql);
            $countStmt->execute($params);
            $result = $countStmt->fetch();
            $total = $result ? $result['total'] : 0;
            
            $sql .= " ORDER BY i.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $invoices = $stmt->fetchAll();
            
            Response::paginated($invoices, $total, $page, $limit, 'Invoices retrieved');
            break;
            
        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('Invoice ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT i.*, c.name as customer_name, c.customer_type, c.customer_code, c.address as customer_address, 
                       c.contact_number as customer_phone,
                       o.order_number, o.customer_po_number
                FROM sales_invoices i
                LEFT JOIN customers c ON i.customer_id = c.id
                LEFT JOIN sales_orders o ON i.order_id = o.id
                WHERE i.id = ?
            ");
            $stmt->execute([$id]);
            $invoice = $stmt->fetch();
            
            if (!$invoice) {
                Response::notFound('Invoice not found');
            }
            
            // Get invoice line items
            $itemsStmt = $db->prepare("
                SELECT ii.*, p.product_name, p.product_code, p.unit
                FROM sales_invoice_items ii
                LEFT JOIN products p ON ii.product_id = p.id
                WHERE ii.invoice_id = ?
                ORDER BY ii.id ASC
            ");
            $itemsStmt->execute([$id]);
            $invoice['items'] = $itemsStmt->fetchAll();
            
            // Get payment history
            $paymentsStmt = $db->prepare("
                SELECT p.*, u.username as recorded_by_name
                FROM sales_invoice_payments p
                LEFT JOIN users u ON p.recorded_by = u.id
                WHERE p.invoice_id = ? AND p.status = 'active'
                ORDER BY p.payment_date DESC
            ");
            $paymentsStmt->execute([$id]);
            $invoice['payments'] = $paymentsStmt->fetchAll();
            
            // Calculate days overdue
            if ($invoice['payment_status'] !== 'paid' && $invoice['due_date']) {
                $dueDate = new DateTime($invoice['due_date']);
                $today = new DateTime();
                $diff = $today->diff($dueDate);
                $invoice['days_overdue'] = $dueDate < $today ? $diff->days : 0;
            } else {
                $invoice['days_overdue'] = 0;
            }
            
            Response::success($invoice, 'Invoice details retrieved');
            break;
            
        case 'unpaid':
            // Get all unpaid or partially paid invoices
            $customerId = getParam('customer_id');
            $customerType = getParam('customer_type');
            
            $sql = "
                SELECT i.*, c.name as customer_name, c.customer_type, c.customer_code, c.contact_number as phone,
                       DATEDIFF(CURDATE(), i.due_date) as days_overdue
                FROM sales_invoices i
                LEFT JOIN customers c ON i.customer_id = c.id
                WHERE i.status = 'active' AND i.payment_status != 'paid'
            ";
            $params = [];
            
            if ($customerId) {
                $sql .= " AND i.customer_id = ?";
                $params[] = $customerId;
            }
            
            if ($customerType) {
                $sql .= " AND c.customer_type = ?";
                $params[] = $customerType;
            }
            
            $sql .= " ORDER BY i.due_date ASC, i.balance_due DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $invoices = $stmt->fetchAll();
            
            // Calculate totals
            $totalUnpaid = array_sum(array_column($invoices, 'balance_due'));
            
            Response::success([
                'invoices' => $invoices,
                'summary' => [
                    'total_invoices' => count($invoices),
                    'total_unpaid' => $totalUnpaid
                ]
            ], 'Unpaid invoices retrieved');
            break;
            
        case 'aging_report':
            // Aging report: 0-30, 31-60, 61-90, 91+ days
            $customerType = getParam('customer_type');
            $asOfDate = getParam('as_of_date', date('Y-m-d'));
            
            $sql = "
                SELECT 
                    c.id as customer_id,
                    c.customer_code,
                    c.name as customer_name,
                    c.customer_type,
                    c.credit_limit,
                    COALESCE(SUM(CASE WHEN DATEDIFF(?, i.due_date) <= 0 THEN i.balance_due ELSE 0 END), 0) as current_balance,
                    COALESCE(SUM(CASE WHEN DATEDIFF(?, i.due_date) BETWEEN 1 AND 30 THEN i.balance_due ELSE 0 END), 0) as days_1_30,
                    COALESCE(SUM(CASE WHEN DATEDIFF(?, i.due_date) BETWEEN 31 AND 60 THEN i.balance_due ELSE 0 END), 0) as days_31_60,
                    COALESCE(SUM(CASE WHEN DATEDIFF(?, i.due_date) BETWEEN 61 AND 90 THEN i.balance_due ELSE 0 END), 0) as days_61_90,
                    COALESCE(SUM(CASE WHEN DATEDIFF(?, i.due_date) > 90 THEN i.balance_due ELSE 0 END), 0) as days_91_plus,
                    COALESCE(SUM(i.balance_due), 0) as total_outstanding
                FROM customers c
                LEFT JOIN sales_invoices i ON c.id = i.customer_id 
                    AND i.status = 'active' 
                    AND i.payment_status != 'paid'
                WHERE c.status = 'active'
            ";
            $params = [$asOfDate, $asOfDate, $asOfDate, $asOfDate, $asOfDate];
            
            if ($customerType) {
                $sql .= " AND c.customer_type = ?";
                $params[] = $customerType;
            }
            
            $sql .= " GROUP BY c.id, c.customer_code, c.name, c.customer_type, c.credit_limit HAVING total_outstanding > 0 ORDER BY total_outstanding DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $agingData = $stmt->fetchAll();
            
            // Calculate summary totals
            $summary = [
                'current_balance' => 0,
                'days_1_30' => 0,
                'days_31_60' => 0,
                'days_61_90' => 0,
                'days_91_plus' => 0,
                'total_outstanding' => 0
            ];
            
            foreach ($agingData as $row) {
                $summary['current_balance'] += $row['current_balance'];
                $summary['days_1_30'] += $row['days_1_30'];
                $summary['days_31_60'] += $row['days_31_60'];
                $summary['days_61_90'] += $row['days_61_90'];
                $summary['days_91_plus'] += $row['days_91_plus'];
                $summary['total_outstanding'] += $row['total_outstanding'];
            }
            
            Response::success([
                'as_of_date' => $asOfDate,
                'customers' => $agingData,
                'summary' => $summary
            ], 'Aging report generated');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle POST requests
 */
function handlePost($db, $action, $currentUser) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'create_csi':
            // Validation
            $errors = [];
            
            if (empty($data['customer_id'])) {
                $errors['customer_id'] = 'Customer ID is required';
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Verify customer exists
            $customerStmt = $db->prepare("SELECT * FROM customers WHERE id = ? AND status = 'active'");
            $customerStmt->execute([$data['customer_id']]);
            $customer = $customerStmt->fetch();
            
            if (!$customer) {
                Response::error('Customer not found or inactive', 400);
            }
            
            // Verify DR if provided
            $drId = null;
            if (!empty($data['dr_id'])) {
                $drStmt = $db->prepare("SELECT * FROM delivery_receipts WHERE id = ?");
                $drStmt->execute([$data['dr_id']]);
                $dr = $drStmt->fetch();
                if (!$dr) {
                    Response::error('Delivery receipt not found', 400);
                }
                $drId = $dr['id'];
            }
            
            // Verify order if provided
            $orderId = null;
            if (!empty($data['order_id'])) {
                $orderStmt = $db->prepare("SELECT * FROM sales_orders WHERE id = ?");
                $orderStmt->execute([$data['order_id']]);
                $order = $orderStmt->fetch();
                if (!$order) {
                    Response::error('Order not found', 400);
                }
                $orderId = $order['id'];
            }
            
            // Generate CSI number
            $csiNumber = generateCSINumber($db);
            
            // Calculate due date based on payment terms
            $invoiceDate = $data['invoice_date'] ?? date('Y-m-d');
            $paymentTermsDays = $customer['payment_terms_days'] ?? 30;
            $dueDate = date('Y-m-d', strtotime($invoiceDate . " + {$paymentTermsDays} days"));
            
            $db->beginTransaction();
            
            try {
                // Create invoice
                $stmt = $db->prepare("
                    INSERT INTO sales_invoices 
                    (csi_number, customer_id, order_id, dr_id, dr_number, invoice_date, due_date,
                     subtotal, discount_amount, tax_amount, total_amount, amount_paid, balance_due,
                     payment_status, notes, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'unpaid', ?, 'active', ?)
                ");
                
                $subtotal = $data['subtotal'] ?? 0;
                $discountAmount = $data['discount_amount'] ?? 0;
                $taxAmount = $data['tax_amount'] ?? 0;
                $totalAmount = $data['total_amount'] ?? ($subtotal - $discountAmount + $taxAmount);
                
                $stmt->execute([
                    $csiNumber,
                    $data['customer_id'],
                    $orderId,
                    $drId,
                    $data['dr_number'] ?? null,
                    $invoiceDate,
                    $dueDate,
                    $subtotal,
                    $discountAmount,
                    $taxAmount,
                    $totalAmount,
                    $totalAmount, // balance_due = total_amount initially
                    $data['notes'] ?? null,
                    $currentUser['user_id']
                ]);
                
                $invoiceId = $db->lastInsertId();
                
                // Add line items if provided
                if (!empty($data['items']) && is_array($data['items'])) {
                    $itemStmt = $db->prepare("
                        INSERT INTO sales_invoice_items 
                        (invoice_id, product_id, description, quantity, unit_price, discount_amount, line_total)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $calculatedSubtotal = 0;
                    
                    foreach ($data['items'] as $item) {
                        $qty = $item['quantity'] ?? 1;
                        $unitPrice = $item['unit_price'] ?? 0;
                        $itemDiscount = $item['discount_amount'] ?? 0;
                        $lineTotal = ($qty * $unitPrice) - $itemDiscount;
                        $calculatedSubtotal += $lineTotal;
                        
                        $itemStmt->execute([
                            $invoiceId,
                            $item['product_id'] ?? null,
                            $item['description'] ?? null,
                            $qty,
                            $unitPrice,
                            $itemDiscount,
                            $lineTotal
                        ]);
                    }
                    
                    // Update invoice totals if items were provided
                    if ($calculatedSubtotal > 0 && empty($data['subtotal'])) {
                        $newTotal = $calculatedSubtotal - $discountAmount + $taxAmount;
                        $updateStmt = $db->prepare("
                            UPDATE sales_invoices SET 
                                subtotal = ?, 
                                total_amount = ?,
                                balance_due = ?
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$calculatedSubtotal, $newTotal, $newTotal, $invoiceId]);
                    }
                }
                
                $db->commit();
                
                logAudit($currentUser['user_id'], 'CREATE', 'sales_invoices', $invoiceId, null, $data);
                
                // Get created invoice
                $getStmt = $db->prepare("
                    SELECT i.*, c.name as customer_name 
                    FROM sales_invoices i 
                    LEFT JOIN customers c ON i.customer_id = c.id 
                    WHERE i.id = ?
                ");
                $getStmt->execute([$invoiceId]);
                $invoice = $getStmt->fetch();
                
                Response::created($invoice, 'Invoice created successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'record_payment':
            // Record payment (supports partial/staggered payments)
            $invoiceId = $data['invoice_id'] ?? getParam('id');
            
            if (!$invoiceId) {
                Response::error('Invoice ID required', 400);
            }
            
            // Get invoice
            $invoiceStmt = $db->prepare("SELECT * FROM sales_invoices WHERE id = ? AND status = 'active'");
            $invoiceStmt->execute([$invoiceId]);
            $invoice = $invoiceStmt->fetch();
            
            if (!$invoice) {
                Response::notFound('Invoice not found');
            }
            
            if ($invoice['payment_status'] === 'paid') {
                Response::error('Invoice is already fully paid', 400);
            }
            
            // Validation
            $errors = [];
            
            $paymentAmount = $data['amount'] ?? null;
            if (!$paymentAmount || $paymentAmount <= 0) {
                $errors['amount'] = 'Valid payment amount is required';
            }
            
            if ($paymentAmount > $invoice['balance_due']) {
                $errors['amount'] = 'Payment amount exceeds balance due of ' . number_format($invoice['balance_due'], 2);
            }
            
            $paymentMethod = $data['payment_method'] ?? 'cash';
            if (!in_array($paymentMethod, ['cash', 'check', 'bank_transfer', 'online'])) {
                $errors['payment_method'] = 'Invalid payment method';
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            $db->beginTransaction();
            
            try {
                // Record payment
                $stmt = $db->prepare("
                    INSERT INTO sales_invoice_payments 
                    (invoice_id, payment_date, amount, payment_method, reference_number, 
                     check_number, check_date, bank_name, notes, status, recorded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)
                ");
                
                $stmt->execute([
                    $invoiceId,
                    $data['payment_date'] ?? date('Y-m-d'),
                    $paymentAmount,
                    $paymentMethod,
                    $data['reference_number'] ?? null,
                    $data['check_number'] ?? null,
                    $data['check_date'] ?? null,
                    $data['bank_name'] ?? null,
                    $data['notes'] ?? null,
                    $currentUser['user_id']
                ]);
                
                $paymentId = $db->lastInsertId();
                
                // Update invoice balance
                $newAmountPaid = $invoice['amount_paid'] + $paymentAmount;
                $newBalance = $invoice['total_amount'] - $newAmountPaid;
                $newStatus = $newBalance <= 0 ? 'paid' : ($newAmountPaid > 0 ? 'partial' : 'unpaid');
                
                $updateStmt = $db->prepare("
                    UPDATE sales_invoices SET
                        amount_paid = ?,
                        balance_due = ?,
                        payment_status = ?,
                        last_payment_date = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $newAmountPaid,
                    max(0, $newBalance),
                    $newStatus,
                    $data['payment_date'] ?? date('Y-m-d'),
                    $invoiceId
                ]);
                
                $db->commit();
                
                logAudit($currentUser['user_id'], 'RECORD_PAYMENT', 'sales_invoices', $invoiceId, 
                    ['amount_paid' => $invoice['amount_paid'], 'balance_due' => $invoice['balance_due']],
                    ['amount_paid' => $newAmountPaid, 'balance_due' => max(0, $newBalance), 'payment_amount' => $paymentAmount]
                );
                
                Response::success([
                    'payment_id' => $paymentId,
                    'invoice_id' => $invoiceId,
                    'payment_amount' => $paymentAmount,
                    'new_balance' => max(0, $newBalance),
                    'payment_status' => $newStatus
                ], 'Payment recorded successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle PUT requests
 */
function handlePut($db, $action, $currentUser) {
    $data = getRequestBody();
    $id = getParam('id') ?? ($data['id'] ?? null);
    
    if (!$id) {
        Response::error('Invoice ID required', 400);
    }
    
    // Get current invoice
    $check = $db->prepare("SELECT * FROM sales_invoices WHERE id = ?");
    $check->execute([$id]);
    $current = $check->fetch();
    
    if (!$current) {
        Response::notFound('Invoice not found');
    }
    
    switch ($action) {
        case 'void':
            if ($current['status'] === 'voided') {
                Response::error('Invoice is already voided', 400);
            }
            
            if ($current['amount_paid'] > 0) {
                Response::error('Cannot void invoice with recorded payments. Reverse payments first.', 400);
            }
            
            if (empty($data['void_reason'])) {
                Response::validationError(['void_reason' => 'Void reason is required']);
            }
            
            $db->beginTransaction();
            
            try {
                $stmt = $db->prepare("
                    UPDATE sales_invoices SET
                        status = 'voided',
                        void_reason = ?,
                        voided_by = ?,
                        voided_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$data['void_reason'], $currentUser['user_id'], $id]);
                
                $db->commit();
                
                logAudit($currentUser['user_id'], 'VOID', 'sales_invoices', $id, 
                    ['status' => $current['status']], 
                    ['status' => 'voided', 'reason' => $data['void_reason']]
                );
                
                Response::success(null, 'Invoice voided successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
