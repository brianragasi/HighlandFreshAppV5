<?php
/**
 * Highland Fresh System - POS/Cash Sales Transactions API
 * 
 * Point of Sale for walk-in customers and cash sales
 * Generates Sales Invoice (SI) documents
 * Immediate inventory deduction on sale
 * 
 * GET - List transactions, get details, today's sales, by date range
 * POST - Create cash sale with immediate inventory deduction
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Cashier or GM role
$currentUser = Auth::requireRole(['cashier', 'general_manager']);

$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action, $currentUser);
            break;
        case 'POST':
            handlePost($db, $action, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("POS Transactions API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

/**
 * Generate Sales Invoice transaction code
 * Format: SI-YYYY-XXXXX (sequential)
 */
function generateSICode($db) {
    $year = date('Y');
    $prefix = "SI-{$year}-";
    
    // Get the last SI number for this year
    $stmt = $db->prepare("
        SELECT transaction_code 
        FROM sales_transactions 
        WHERE transaction_code LIKE ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetch();
    
    if ($last) {
        // Extract the sequence number and increment
        $lastNum = intval(substr($last['transaction_code'], -5));
        $newNum = $lastNum + 1;
    } else {
        $newNum = 1;
    }
    
    return $prefix . str_pad($newNum, 5, '0', STR_PAD_LEFT);
}

/**
 * Deduct from finished goods inventory using FIFO
 * Returns array of inventory deductions made
 */
function deductInventory($db, $productId, $quantityNeeded, $userId, $transactionId, $transactionCode) {
    // Get available inventory for this product, ordered by expiry (FIFO)
    $stmt = $db->prepare("
        SELECT 
            fg.id,
            fg.product_id,
            fg.quantity_available,
            fg.boxes_available,
            fg.pieces_available,
            fg.expiry_date,
            fg.chiller_id,
            p.pieces_per_box,
            p.base_unit,
            p.box_unit
        FROM finished_goods_inventory fg
        JOIN products p ON fg.product_id = p.id
        WHERE fg.product_id = ?
        AND fg.status = 'available'
        AND fg.expiry_date > CURDATE()
        AND (fg.quantity_available > 0 OR fg.pieces_available > 0 OR fg.boxes_available > 0)
        ORDER BY fg.expiry_date ASC
        FOR UPDATE
    ");
    $stmt->execute([$productId]);
    $inventoryItems = $stmt->fetchAll();
    
    if (empty($inventoryItems)) {
        throw new Exception("No available inventory for product ID: {$productId}");
    }
    
    $deductions = [];
    $remaining = $quantityNeeded;
    
    foreach ($inventoryItems as $inv) {
        if ($remaining <= 0) break;
        
        $piecesPerBox = $inv['pieces_per_box'] ?: 1;
        $availablePieces = $inv['pieces_available'] ?: 0;
        $availableBoxes = $inv['boxes_available'] ?: 0;
        $totalAvailable = ($availableBoxes * $piecesPerBox) + $availablePieces;
        
        if ($totalAvailable <= 0) continue;
        
        $deductAmount = min($remaining, $totalAvailable);
        $remaining -= $deductAmount;
        
        // Calculate new boxes and pieces
        $newTotal = $totalAvailable - $deductAmount;
        $newBoxes = intdiv($newTotal, $piecesPerBox);
        $newPieces = $newTotal % $piecesPerBox;
        
        // Update inventory
        $updateStmt = $db->prepare("
            UPDATE finished_goods_inventory 
            SET quantity_available = quantity_available - ?,
                boxes_available = ?,
                pieces_available = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$deductAmount, $newBoxes, $newPieces, $inv['id']]);
        
        // Update chiller count if applicable
        if ($inv['chiller_id']) {
            $db->prepare("UPDATE chiller_locations SET current_count = current_count - ? WHERE id = ?")
               ->execute([$deductAmount, $inv['chiller_id']]);
        }
        
        // Log the transaction
        $logStmt = $db->prepare("
            INSERT INTO fg_inventory_transactions
            (transaction_code, transaction_type, inventory_id, product_id, quantity,
             boxes_quantity, pieces_quantity, quantity_before, quantity_after,
             boxes_before, pieces_before, boxes_after, pieces_after,
             from_chiller_id, performed_by, reason, reference_type, reference_id)
            VALUES (?, 'sale', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $logStmt->execute([
            'FGT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            $inv['id'],
            $productId,
            $deductAmount,
            $availableBoxes - $newBoxes,
            $availablePieces - $newPieces,
            $totalAvailable,
            $newTotal,
            $availableBoxes,
            $availablePieces,
            $newBoxes,
            $newPieces,
            $inv['chiller_id'],
            $userId,
            "POS Sale - {$transactionCode}",
            'sales_transaction',
            $transactionId
        ]);
        
        $deductions[] = [
            'inventory_id' => $inv['id'],
            'quantity_deducted' => $deductAmount,
            'expiry_date' => $inv['expiry_date']
        ];
    }
    
    if ($remaining > 0) {
        throw new Exception("Insufficient inventory. Short by {$remaining} units for product ID: {$productId}");
    }
    
    return $deductions;
}

// ========================================
// GET HANDLERS
// ========================================

function handleGet($db, $action, $currentUser) {
    switch ($action) {
        case 'list':
            $page = max(1, intval(getParam('page', 1)));
            $limit = min(100, max(10, intval(getParam('limit', 20))));
            $offset = ($page - 1) * $limit;
            $status = getParam('status');
            $type = getParam('type'); // cash, credit, csi
            $fromDate = getParam('from_date');
            $toDate = getParam('to_date');
            $paymentMethod = getParam('payment_method');
            $search = getParam('search');
            
            $sql = "
                SELECT 
                    st.*,
                    u.first_name as cashier_first_name,
                    u.last_name as cashier_last_name,
                    c.name as customer_record_name,
                    (SELECT COUNT(*) FROM sales_transaction_items WHERE transaction_id = st.id) as items_count
                FROM sales_transactions st
                LEFT JOIN users u ON st.cashier_id = u.id
                LEFT JOIN customers c ON st.customer_id = c.id
                WHERE 1=1
            ";
            $countSql = "SELECT COUNT(*) FROM sales_transactions st WHERE 1=1";
            $params = [];
            
            if ($status) {
                $sql .= " AND st.payment_status = ?";
                $countSql .= " AND st.payment_status = ?";
                $params[] = $status;
            }
            
            if ($type) {
                $sql .= " AND st.transaction_type = ?";
                $countSql .= " AND st.transaction_type = ?";
                $params[] = $type;
            }
            
            if ($fromDate) {
                $sql .= " AND DATE(st.created_at) >= ?";
                $countSql .= " AND DATE(st.created_at) >= ?";
                $params[] = $fromDate;
            }
            
            if ($toDate) {
                $sql .= " AND DATE(st.created_at) <= ?";
                $countSql .= " AND DATE(st.created_at) <= ?";
                $params[] = $toDate;
            }
            
            if ($paymentMethod) {
                $sql .= " AND st.payment_method = ?";
                $countSql .= " AND st.payment_method = ?";
                $params[] = $paymentMethod;
            }
            
            if ($search) {
                $sql .= " AND (st.transaction_code LIKE ? OR st.customer_name LIKE ?)";
                $countSql .= " AND (st.transaction_code LIKE ? OR st.customer_name LIKE ?)";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
            }
            
            // Get total count
            $countStmt = $db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            $sql .= " ORDER BY st.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $transactions = $stmt->fetchAll();
            
            Response::paginated($transactions, $total, $page, $limit, 'Transactions retrieved');
            break;
            
        case 'detail':
            $id = getParam('id');
            $code = getParam('code');
            
            if (!$id && !$code) {
                Response::error('Transaction ID or code required', 400);
            }
            
            $sql = "
                SELECT 
                    st.*,
                    u.first_name as cashier_first_name,
                    u.last_name as cashier_last_name,
                    c.name as customer_record_name,
                    c.contact_person,
                    c.contact_number,
                    c.address
                FROM sales_transactions st
                LEFT JOIN users u ON st.cashier_id = u.id
                LEFT JOIN customers c ON st.customer_id = c.id
                WHERE " . ($id ? "st.id = ?" : "st.transaction_code = ?");
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$id ?: $code]);
            $transaction = $stmt->fetch();
            
            if (!$transaction) {
                Response::error('Transaction not found', 404);
            }
            
            // Get transaction items
            $itemsStmt = $db->prepare("
                SELECT 
                    sti.*,
                    p.product_name,
                    p.category,
                    p.base_unit,
                    p.box_unit
                FROM sales_transaction_items sti
                LEFT JOIN products p ON sti.product_id = p.id
                WHERE sti.transaction_id = ?
                ORDER BY sti.id ASC
            ");
            $itemsStmt->execute([$transaction['id']]);
            $transaction['items'] = $itemsStmt->fetchAll();
            
            Response::success($transaction, 'Transaction details retrieved');
            break;
            
        case 'today':
            $sql = "
                SELECT 
                    st.*,
                    u.first_name as cashier_first_name,
                    u.last_name as cashier_last_name
                FROM sales_transactions st
                LEFT JOIN users u ON st.cashier_id = u.id
                WHERE DATE(st.created_at) = CURDATE()
                ORDER BY st.created_at DESC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $transactions = $stmt->fetchAll();
            
            // Calculate totals
            $totals = [
                'count' => count($transactions),
                'total_sales' => 0,
                'total_paid' => 0,
                'cash_sales' => 0,
                'gcash_sales' => 0,
                'check_sales' => 0,
                'bank_transfer_sales' => 0
            ];
            
            foreach ($transactions as $t) {
                $totals['total_sales'] += floatval($t['total_amount']);
                $totals['total_paid'] += floatval($t['amount_paid']);
                
                if ($t['payment_status'] === 'paid') {
                    switch ($t['payment_method']) {
                        case 'cash':
                            $totals['cash_sales'] += floatval($t['amount_paid']);
                            break;
                        case 'gcash':
                            $totals['gcash_sales'] += floatval($t['amount_paid']);
                            break;
                        case 'check':
                            $totals['check_sales'] += floatval($t['amount_paid']);
                            break;
                        case 'bank_transfer':
                            $totals['bank_transfer_sales'] += floatval($t['amount_paid']);
                            break;
                    }
                }
            }
            
            Response::success([
                'transactions' => $transactions,
                'totals' => $totals
            ], "Today's transactions retrieved");
            break;
            
        case 'by_date_range':
            $fromDate = getParam('from_date');
            $toDate = getParam('to_date');
            
            if (!$fromDate || !$toDate) {
                Response::error('from_date and to_date are required', 400);
            }
            
            $sql = "
                SELECT 
                    st.*,
                    u.first_name as cashier_first_name,
                    u.last_name as cashier_last_name
                FROM sales_transactions st
                LEFT JOIN users u ON st.cashier_id = u.id
                WHERE DATE(st.created_at) BETWEEN ? AND ?
                ORDER BY st.created_at DESC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$fromDate, $toDate]);
            $transactions = $stmt->fetchAll();
            
            // Calculate totals by day
            $dailyTotals = [];
            foreach ($transactions as $t) {
                $date = date('Y-m-d', strtotime($t['created_at']));
                if (!isset($dailyTotals[$date])) {
                    $dailyTotals[$date] = [
                        'date' => $date,
                        'count' => 0,
                        'total_sales' => 0,
                        'total_paid' => 0
                    ];
                }
                $dailyTotals[$date]['count']++;
                $dailyTotals[$date]['total_sales'] += floatval($t['total_amount']);
                $dailyTotals[$date]['total_paid'] += floatval($t['amount_paid']);
            }
            
            Response::success([
                'transactions' => $transactions,
                'daily_totals' => array_values($dailyTotals),
                'total_count' => count($transactions)
            ], 'Transactions by date range retrieved');
            break;
            
        case 'receipt':
            // Get receipt-ready data for printing
            $id = getParam('id');
            if (!$id) {
                Response::error('Transaction ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT 
                    st.*,
                    u.first_name as cashier_first_name,
                    u.last_name as cashier_last_name
                FROM sales_transactions st
                LEFT JOIN users u ON st.cashier_id = u.id
                WHERE st.id = ?
            ");
            $stmt->execute([$id]);
            $transaction = $stmt->fetch();
            
            if (!$transaction) {
                Response::error('Transaction not found', 404);
            }
            
            // Get items
            $itemsStmt = $db->prepare("
                SELECT 
                    sti.*,
                    p.product_name,
                    p.variant,
                    p.unit_size,
                    p.unit_measure
                FROM sales_transaction_items sti
                LEFT JOIN products p ON sti.product_id = p.id
                WHERE sti.transaction_id = ?
            ");
            $itemsStmt->execute([$id]);
            $items = $itemsStmt->fetchAll();
            
            $receipt = [
                'header' => [
                    'company_name' => 'Highland Fresh',
                    'address' => 'Cagayan de Oro City',
                    'document_type' => 'SALES INVOICE',
                    'si_number' => $transaction['transaction_code']
                ],
                'transaction' => $transaction,
                'items' => $items,
                'payment' => [
                    'subtotal' => $transaction['subtotal_amount'] ?? $transaction['total_amount'],
                    'discount' => $transaction['discount_amount'],
                    'total' => $transaction['total_amount'],
                    'amount_paid' => $transaction['amount_paid'],
                    'change' => max(0, floatval($transaction['amount_paid']) - floatval($transaction['total_amount'])),
                    'method' => $transaction['payment_method']
                ],
                'footer' => [
                    'cashier' => trim($transaction['cashier_first_name'] . ' ' . $transaction['cashier_last_name']),
                    'date' => $transaction['created_at'],
                    'message' => 'Thank you for your purchase!'
                ]
            ];
            
            Response::success($receipt, 'Receipt data retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

// ========================================
// POST HANDLERS
// ========================================

function handlePost($db, $action, $currentUser) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'create_cash_sale':
            // Validate required fields
            if (empty($data['items']) || !is_array($data['items'])) {
                Response::error('Items are required', 400);
            }
            
            $paymentMethod = $data['payment_method'] ?? 'cash';
            $validMethods = ['cash', 'gcash', 'bank_transfer', 'check'];
            if (!in_array($paymentMethod, $validMethods)) {
                Response::error('Invalid payment method. Must be: ' . implode(', ', $validMethods), 400);
            }
            
            // Validate check payment details
            if ($paymentMethod === 'check') {
                if (empty($data['check_bank'])) {
                    Response::error('Check bank is required for check payments', 400);
                }
                if (empty($data['check_number'])) {
                    Response::error('Check number is required for check payments', 400);
                }
                if (empty($data['check_date'])) {
                    Response::error('Check date is required for check payments', 400);
                }
            }
            
            $amountPaid = floatval($data['amount_paid'] ?? 0);
            $customerName = $data['customer_name'] ?? 'Walk-in Customer';
            $customerId = $data['customer_id'] ?? null;
            $discountPercent = floatval($data['discount_percent'] ?? 0);
            $notes = $data['notes'] ?? null;
            
            try {
                $db->beginTransaction();
                
                // Calculate totals and validate items
                $subtotal = 0;
                $itemsData = [];
                
                foreach ($data['items'] as $item) {
                    if (empty($item['product_id']) || empty($item['quantity']) || !isset($item['unit_price'])) {
                        throw new Exception('Each item must have product_id, quantity, and unit_price');
                    }
                    
                    // Verify product exists and get details
                    $prodStmt = $db->prepare("SELECT id, product_code, product_name, variant FROM products WHERE id = ?");
                    $prodStmt->execute([$item['product_id']]);
                    $product = $prodStmt->fetch();
                    
                    if (!$product) {
                        throw new Exception("Product not found: {$item['product_id']}");
                    }
                    
                    $quantity = intval($item['quantity']);
                    $unitPrice = floatval($item['unit_price']);
                    $lineTotal = $quantity * $unitPrice;
                    $subtotal += $lineTotal;
                    
                    $itemsData[] = [
                        'product_id' => $item['product_id'],
                        'product_code' => $product['product_code'],
                        'product_name' => $product['product_name'],
                        'variant' => $product['variant'],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal
                    ];
                }
                
                // Calculate discount and total
                $discountAmount = $subtotal * ($discountPercent / 100);
                $totalAmount = $subtotal - $discountAmount;
                
                // Validate payment
                if ($amountPaid < $totalAmount) {
                    throw new Exception("Insufficient payment. Required: {$totalAmount}, Received: {$amountPaid}");
                }
                
                // Generate SI code
                $transactionCode = generateSICode($db);
                
                // Create transaction record
                $changeAmount = $amountPaid - $totalAmount;
                
                $stmt = $db->prepare("
                    INSERT INTO sales_transactions 
                    (transaction_code, transaction_type, customer_id, customer_name,
                     subtotal_amount, discount_value, discount_amount, total_amount,
                     amount_paid, change_amount, payment_status, payment_method,
                     payment_reference, cashier_id, notes)
                    VALUES (?, 'cash', ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?, ?, ?)
                ");
                
                // Build payment reference for check/bank/gcash
                $paymentRef = null;
                if ($paymentMethod === 'check') {
                    $paymentRef = 'Check: ' . ($data['check_bank'] ?? '') . ' #' . ($data['check_number'] ?? '') . ' dated ' . ($data['check_date'] ?? '');
                } elseif ($paymentMethod === 'gcash') {
                    $paymentRef = 'GCash: ' . ($data['gcash_ref'] ?? '');
                } elseif ($paymentMethod === 'bank_transfer') {
                    $paymentRef = 'Bank: ' . ($data['bank_name'] ?? '') . ' Ref#' . ($data['bank_ref'] ?? '');
                }
                
                $stmt->execute([
                    $transactionCode,
                    $customerId,
                    $customerName,
                    $subtotal,
                    $discountPercent,
                    $discountAmount,
                    $totalAmount,
                    $amountPaid,
                    $changeAmount,
                    $paymentMethod,
                    $paymentRef,
                    $currentUser['user_id'],
                    $notes
                ]);
                
                $transactionId = $db->lastInsertId();
                
                // Insert items and deduct inventory
                $itemStmt = $db->prepare("
                    INSERT INTO sales_transaction_items 
                    (transaction_id, product_id, product_code, product_name, variant, quantity, unit_price, line_total, inventory_deductions)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $allDeductions = [];
                
                foreach ($itemsData as $item) {
                    // Deduct from inventory (FIFO)
                    $deductions = deductInventory(
                        $db,
                        $item['product_id'],
                        $item['quantity'],
                        $currentUser['user_id'],
                        $transactionId,
                        $transactionCode
                    );
                    
                    $itemStmt->execute([
                        $transactionId,
                        $item['product_id'],
                        $item['product_code'],
                        $item['product_name'],
                        $item['variant'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['line_total'],
                        json_encode($deductions)
                    ]);
                    
                    $allDeductions[] = [
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'],
                        'deductions' => $deductions
                    ];
                }
                
                // Log the audit
                logAudit(
                    $currentUser['user_id'],
                    'create',
                    'sales_transactions',
                    $transactionId,
                    null,
                    ['transaction_code' => $transactionCode, 'total' => $totalAmount, 'items' => count($itemsData)]
                );
                
                $db->commit();
                
                Response::created([
                    'transaction_id' => $transactionId,
                    'transaction_code' => $transactionCode,
                    'si_number' => $transactionCode,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'total_amount' => $totalAmount,
                    'amount_paid' => $amountPaid,
                    'change' => $amountPaid - $totalAmount,
                    'payment_method' => $paymentMethod,
                    'items_count' => count($itemsData),
                    'inventory_deductions' => $allDeductions
                ], 'Cash sale created successfully');
                
            } catch (Exception $e) {
                error_log("POS Cash Sale Error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'void':
            // Void a transaction (admin only - could add permission check)
            $id = $data['id'] ?? null;
            $reason = $data['reason'] ?? null;
            
            if (!$id) {
                Response::error('Transaction ID required', 400);
            }
            if (!$reason) {
                Response::error('Void reason is required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Get transaction
                $stmt = $db->prepare("SELECT * FROM sales_transactions WHERE id = ?");
                $stmt->execute([$id]);
                $transaction = $stmt->fetch();
                
                if (!$transaction) {
                    throw new Exception('Transaction not found');
                }
                
                if ($transaction['payment_status'] === 'voided') {
                    throw new Exception('Transaction is already voided');
                }
                
                // Restore inventory (get items and reverse deductions)
                $itemsStmt = $db->prepare("SELECT * FROM sales_transaction_items WHERE transaction_id = ?");
                $itemsStmt->execute([$id]);
                $items = $itemsStmt->fetchAll();
                
                foreach ($items as $item) {
                    $deductions = json_decode($item['inventory_deductions'], true);
                    if ($deductions) {
                        foreach ($deductions as $ded) {
                            // Restore inventory
                            $db->prepare("
                                UPDATE finished_goods_inventory 
                                SET quantity_available = quantity_available + ?
                                WHERE id = ?
                            ")->execute([$ded['quantity_deducted'], $ded['inventory_id']]);
                        }
                    }
                }
                
                // Mark transaction as voided
                $updateStmt = $db->prepare("
                    UPDATE sales_transactions 
                    SET payment_status = 'voided', 
                        notes = CONCAT(COALESCE(notes, ''), '\n[VOIDED: ', ?, ']')
                    WHERE id = ?
                ");
                $updateStmt->execute([$reason, $id]);
                
                logAudit(
                    $currentUser['user_id'],
                    'void',
                    'sales_transactions',
                    $id,
                    $transaction,
                    ['reason' => $reason]
                );
                
                $db->commit();
                
                Response::success([
                    'transaction_id' => $id,
                    'status' => 'voided'
                ], 'Transaction voided successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
