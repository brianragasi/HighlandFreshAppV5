<?php
/**
 * Highland Fresh System - AR Collection API
 * 
 * Collection of payments for credit accounts (Accounts Receivable)
 * Generates Official Receipt (OR) documents
 * Supports partial payments (staggered collection)
 * 
 * GET - Search by DR number, outstanding balances, customer balance
 * POST - Record collection payment, generate OR
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Cashier or GM role
$currentUser = Auth::requireRole(['cashier', 'general_manager']);

$action = getParam('action', 'outstanding');

try {
    $db = Database::getInstance()->getConnection();
    
    // Ensure payment_collections table exists
    ensureCollectionsTable($db);
    
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
    error_log("Collections API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

/**
 * Ensure the payment_collections table exists
 */
function ensureCollectionsTable($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS payment_collections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            or_number VARCHAR(30) NOT NULL UNIQUE COMMENT 'Official Receipt Number: OR-YYYY-XXXXX',
            dr_id INT NULL COMMENT 'Link to delivery_receipts',
            dr_number VARCHAR(30) NULL,
            transaction_id INT NULL COMMENT 'Link to sales_transactions for credit sales',
            customer_id INT NULL,
            customer_name VARCHAR(200) NOT NULL,
            
            amount_collected DECIMAL(12,2) NOT NULL,
            balance_before DECIMAL(12,2) NOT NULL,
            balance_after DECIMAL(12,2) NOT NULL,
            
            payment_method ENUM('cash', 'gcash', 'bank_transfer', 'check') NOT NULL DEFAULT 'cash',
            payment_metadata JSON COMMENT 'check_number, check_bank, check_date, gcash_ref, bank_ref',
            
            collected_by INT NOT NULL,
            collected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            notes TEXT,
            status ENUM('confirmed', 'bounced', 'cancelled') DEFAULT 'confirmed',
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_or_number (or_number),
            INDEX idx_dr (dr_id, dr_number),
            INDEX idx_customer (customer_id),
            INDEX idx_collected_at (collected_at),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

/**
 * Generate Official Receipt number
 * Format: OR-YYYY-XXXXX (sequential)
 */
function generateORNumber($db) {
    $year = date('Y');
    $prefix = "OR-{$year}-";
    
    // Get the last OR number for this year
    $stmt = $db->prepare("
        SELECT or_number 
        FROM payment_collections 
        WHERE or_number LIKE ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetch();
    
    if ($last) {
        $lastNum = intval(substr($last['or_number'], -5));
        $newNum = $lastNum + 1;
    } else {
        $newNum = 1;
    }
    
    return $prefix . str_pad($newNum, 5, '0', STR_PAD_LEFT);
}

// ========================================
// GET HANDLERS
// ========================================

function handleGet($db, $action, $currentUser) {
    switch ($action) {
        case 'search_by_dr':
            $drNumber = getParam('dr_number');
            $drId = getParam('dr_id');
            
            if (!$drNumber && !$drId) {
                Response::error('DR number or DR ID required', 400);
            }
            
            // Find delivery receipt
            $sql = "
                SELECT 
                    dr.*,
                    c.id as customer_id_record,
                    c.contact_person,
                    c.credit_limit,
                    c.current_balance as customer_total_balance,
                    COALESCE(c.payment_terms_days, 30) as payment_terms
                FROM delivery_receipts dr
                LEFT JOIN customers c ON dr.customer_id = c.id
                WHERE " . ($drId ? "dr.id = ?" : "dr.dr_number = ?");
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$drId ?: $drNumber]);
            $dr = $stmt->fetch();
            
            if (!$dr) {
                Response::error('Delivery Receipt not found', 404);
            }
            
            // Get items on this DR
            $itemsStmt = $db->prepare("
                SELECT 
                    dri.*,
                    p.product_name,
                    p.category
                FROM delivery_receipt_items dri
                LEFT JOIN products p ON dri.product_id = p.id
                WHERE dri.dr_id = ?
            ");
            $itemsStmt->execute([$dr['id']]);
            $dr['items'] = $itemsStmt->fetchAll();
            
            // Get payment history for this DR
            $paymentsStmt = $db->prepare("
                SELECT 
                    pc.*,
                    u.first_name as collected_by_first_name,
                    u.last_name as collected_by_last_name
                FROM payment_collections pc
                LEFT JOIN users u ON pc.collected_by = u.id
                WHERE pc.dr_id = ?
                ORDER BY pc.collected_at DESC
            ");
            $paymentsStmt->execute([$dr['id']]);
            $dr['payment_history'] = $paymentsStmt->fetchAll();
            
            // Calculate totals
            $totalPaid = array_sum(array_map(function($p) {
                return $p['status'] === 'confirmed' ? floatval($p['amount_collected']) : 0;
            }, $dr['payment_history']));
            
            $dr['calculated_balance'] = floatval($dr['total_amount']) - $totalPaid;
            $dr['total_collected'] = $totalPaid;
            
            Response::success($dr, 'Delivery Receipt found');
            break;
            
        case 'outstanding':
            // Get all outstanding (unpaid/partial) delivery receipts
            $customerId = getParam('customer_id');
            $limit = min(100, max(10, intval(getParam('limit', 50))));
            
            $sql = "
                SELECT 
                    dr.id,
                    dr.dr_number,
                    COALESCE(c.customer_type, 'institutional') as customer_type,
                    dr.customer_name,
                    COALESCE(c.sub_location, '') as sub_location,
                    dr.total_amount,
                    COALESCE(dr.amount_paid, 0) as amount_paid,
                    (dr.total_amount - COALESCE(dr.amount_paid, 0)) as amount_due,
                    COALESCE(dr.payment_status, 'unpaid') as payment_status,
                    dr.status as delivery_status,
                    dr.created_at,
                    dr.delivered_at,
                    DATEDIFF(CURDATE(), COALESCE(dr.delivered_at, dr.created_at)) as days_outstanding,
                    c.id as customer_id,
                    COALESCE(c.payment_terms_days, 30) as payment_terms
                FROM delivery_receipts dr
                LEFT JOIN customers c ON dr.customer_id = c.id OR dr.customer_name = c.name
                WHERE COALESCE(dr.payment_status, 'unpaid') IN ('unpaid', 'partial')
                AND dr.status NOT IN ('cancelled')
            ";
            $params = [];
            
            if ($customerId) {
                $sql .= " AND c.id = ?";
                $params[] = $customerId;
            }
            
            $sql .= " ORDER BY dr.delivered_at ASC, dr.created_at ASC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $outstanding = $stmt->fetchAll();
            
            // Calculate totals
            $totalOutstanding = array_sum(array_column($outstanding, 'amount_due'));
            
            Response::success([
                'receivables' => $outstanding,
                'count' => count($outstanding),
                'total_outstanding' => $totalOutstanding
            ], 'Outstanding receivables retrieved');
            break;
            
        case 'customer_balance':
            $customerId = getParam('customer_id');
            $customerName = getParam('customer_name');
            
            if (!$customerId && !$customerName) {
                Response::error('Customer ID or name required', 400);
            }
            
            // Get customer record
            $sql = "SELECT * FROM customers WHERE " . ($customerId ? "id = ?" : "name LIKE ?");
            $stmt = $db->prepare($sql);
            $stmt->execute([$customerId ?: "%{$customerName}%"]);
            $customer = $stmt->fetch();
            
            if (!$customer) {
                Response::error('Customer not found', 404);
            }
            
            // Get outstanding DRs
            $drsStmt = $db->prepare("
                SELECT 
                    dr.id,
                    dr.dr_number,
                    dr.total_amount,
                    dr.amount_paid,
                    (dr.total_amount - dr.amount_paid) as amount_due,
                    dr.payment_status,
                    dr.delivered_at,
                    DATEDIFF(CURDATE(), dr.delivered_at) as days_outstanding
                FROM delivery_receipts dr
                WHERE dr.customer_name = ?
                AND dr.payment_status IN ('unpaid', 'partial')
                AND dr.status NOT IN ('cancelled')
                ORDER BY dr.delivered_at ASC
            ");
            $drsStmt->execute([$customer['name']]);
            $outstandingDRs = $drsStmt->fetchAll();
            
            // Get recent payments
            $paymentsStmt = $db->prepare("
                SELECT 
                    pc.or_number,
                    pc.dr_number,
                    pc.amount_collected,
                    pc.payment_method,
                    pc.collected_at,
                    pc.status
                FROM payment_collections pc
                WHERE pc.customer_id = ? OR pc.customer_name = ?
                ORDER BY pc.collected_at DESC
                LIMIT 20
            ");
            $paymentsStmt->execute([$customer['id'], $customer['name']]);
            $recentPayments = $paymentsStmt->fetchAll();
            
            $totalOutstanding = array_sum(array_column($outstandingDRs, 'amount_due'));
            
            // AR Aging breakdown
            $aging = [
                'current' => 0,      // 0-30 days
                'days_31_60' => 0,
                'days_61_90' => 0,
                'over_90' => 0
            ];
            
            foreach ($outstandingDRs as $dr) {
                $days = intval($dr['days_outstanding']);
                $amount = floatval($dr['amount_due']);
                
                if ($days <= 30) {
                    $aging['current'] += $amount;
                } elseif ($days <= 60) {
                    $aging['days_31_60'] += $amount;
                } elseif ($days <= 90) {
                    $aging['days_61_90'] += $amount;
                } else {
                    $aging['over_90'] += $amount;
                }
            }
            
            Response::success([
                'customer' => $customer,
                'outstanding_deliveries' => $outstandingDRs,
                'recent_payments' => $recentPayments,
                'total_outstanding' => $totalOutstanding,
                'credit_limit' => floatval($customer['credit_limit']),
                'available_credit' => floatval($customer['credit_limit']) - $totalOutstanding,
                'aging' => $aging
            ], 'Customer balance retrieved');
            break;
            
        case 'collection_history':
            // Get collection history with filters
            $fromDate = getParam('from_date');
            $toDate = getParam('to_date');
            $customerId = getParam('customer_id');
            $status = getParam('status'); // Default to null (show all)
            $limit = min(200, max(10, intval(getParam('limit', 50))));
            
            $sql = "
                SELECT 
                    pc.*,
                    u.first_name as collected_by_first_name,
                    u.last_name as collected_by_last_name
                FROM payment_collections pc
                LEFT JOIN users u ON pc.collected_by = u.id
                WHERE 1=1
            ";
            $params = [];
            
            if ($fromDate) {
                $sql .= " AND DATE(pc.collected_at) >= ?";
                $params[] = $fromDate;
            }
            
            if ($toDate) {
                $sql .= " AND DATE(pc.collected_at) <= ?";
                $params[] = $toDate;
            }
            
            if ($customerId) {
                $sql .= " AND pc.customer_id = ?";
                $params[] = $customerId;
            }
            
            if ($status && $status !== 'all') {
                $sql .= " AND pc.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY pc.collected_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $collections = $stmt->fetchAll();
            
            // Decode payment_metadata
            foreach ($collections as &$c) {
                $c['payment_metadata'] = $c['payment_metadata'] ? json_decode($c['payment_metadata'], true) : null;
            }
            
            $totalCollected = array_sum(array_map(function($c) {
                return $c['status'] === 'confirmed' ? floatval($c['amount_collected']) : 0;
            }, $collections));
            
            Response::success([
                'collections' => $collections,
                'count' => count($collections),
                'total_collected' => $totalCollected
            ], 'Collection history retrieved');
            break;
        
        case 'receipt': // Alias for or_detail
        case 'or_detail':
            $orNumber = getParam('or_number');
            $id = getParam('id');
            
            if (!$orNumber && !$id) {
                Response::error('OR number or ID required', 400);
            }
            
            $sql = "
                SELECT 
                    pc.*,
                    u.first_name as collected_by_first_name,
                    u.last_name as collected_by_last_name,
                    dr.total_amount as dr_total_amount,
                    c.customer_type
                FROM payment_collections pc
                LEFT JOIN users u ON pc.collected_by = u.id
                LEFT JOIN delivery_receipts dr ON pc.dr_id = dr.id
                LEFT JOIN customers c ON dr.customer_id = c.id
                WHERE " . ($id ? "pc.id = ?" : "pc.or_number = ?");
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$id ?: $orNumber]);
            $collection = $stmt->fetch();
            
            if (!$collection) {
                Response::error('Collection record not found', 404);
            }
            
            $collection['payment_metadata'] = json_decode($collection['payment_metadata'], true);
            
            Response::success($collection, 'Collection detail retrieved');
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
    
    // For POST, action should come from the body
    $action = $data['action'] ?? $action;
    
    switch ($action) {
        case 'record_collection':
            // Validate required fields
            $drId = $data['dr_id'] ?? null;
            $drNumber = $data['dr_number'] ?? null;
            $amountCollected = floatval($data['amount_collected'] ?? 0);
            $paymentMethod = $data['payment_method'] ?? 'cash';
            
            if (!$drId && !$drNumber) {
                Response::error('DR ID or DR number is required', 400);
            }
            
            if ($amountCollected <= 0) {
                Response::error('Amount collected must be greater than 0', 400);
            }
            
            $validMethods = ['cash', 'gcash', 'bank_transfer', 'check'];
            if (!in_array($paymentMethod, $validMethods)) {
                Response::error('Invalid payment method', 400);
            }
            
            // Build payment metadata
            $paymentMetadata = [];
            
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
                $paymentMetadata = [
                    'check_bank' => $data['check_bank'],
                    'check_number' => $data['check_number'],
                    'check_date' => $data['check_date'],
                    'check_account_owner' => $data['check_account_owner'] ?? null
                ];
            } elseif ($paymentMethod === 'gcash') {
                $paymentMetadata = [
                    'gcash_ref' => $data['gcash_ref'] ?? null,
                    'gcash_number' => $data['gcash_number'] ?? null
                ];
            } elseif ($paymentMethod === 'bank_transfer') {
                $paymentMetadata = [
                    'bank_name' => $data['bank_name'] ?? null,
                    'bank_ref' => $data['bank_ref'] ?? null,
                    'account_number' => $data['account_number'] ?? null
                ];
            }
            
            $db->beginTransaction();
            
            try {
                // Get delivery receipt
                $sql = "SELECT * FROM delivery_receipts WHERE " . ($drId ? "id = ?" : "dr_number = ?") . " FOR UPDATE";
                $stmt = $db->prepare($sql);
                $stmt->execute([$drId ?: $drNumber]);
                $dr = $stmt->fetch();
                
                if (!$dr) {
                    throw new Exception('Delivery Receipt not found');
                }
                
                $drId = $dr['id'];
                $currentBalance = floatval($dr['total_amount']) - floatval($dr['amount_paid']);
                
                if ($currentBalance <= 0) {
                    throw new Exception('This delivery receipt is already fully paid');
                }
                
                // Allow overpayment warning but proceed
                $isOverpayment = $amountCollected > $currentBalance;
                $effectiveAmount = $isOverpayment ? $currentBalance : $amountCollected;
                $newBalance = $currentBalance - $effectiveAmount;
                
                // Generate OR number
                $orNumber = generateORNumber($db);
                
                // Get or find customer ID
                $customerId = null;
                $custStmt = $db->prepare("SELECT id FROM customers WHERE name = ? LIMIT 1");
                $custStmt->execute([$dr['customer_name']]);
                $cust = $custStmt->fetch();
                if ($cust) {
                    $customerId = $cust['id'];
                }
                
                // Create collection record
                $insertStmt = $db->prepare("
                    INSERT INTO payment_collections 
                    (or_number, dr_id, dr_number, customer_id, customer_name,
                     amount_collected, balance_before, balance_after,
                     payment_method, payment_metadata, collected_by, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $insertStmt->execute([
                    $orNumber,
                    $drId,
                    $dr['dr_number'],
                    $customerId,
                    $dr['customer_name'],
                    $effectiveAmount,
                    $currentBalance,
                    $newBalance,
                    $paymentMethod,
                    json_encode($paymentMetadata),
                    $currentUser['user_id'],
                    $data['notes'] ?? null
                ]);
                
                $collectionId = $db->lastInsertId();
                
                // Update delivery receipt
                $newAmountPaid = floatval($dr['amount_paid']) + $effectiveAmount;
                $newPaymentStatus = $newBalance <= 0 ? 'paid' : 'partial';
                
                $updateDR = $db->prepare("
                    UPDATE delivery_receipts 
                    SET amount_paid = ?,
                        payment_status = ?
                    WHERE id = ?
                ");
                $updateDR->execute([$newAmountPaid, $newPaymentStatus, $drId]);
                
                // Update customer current_balance if we have customer ID
                if ($customerId) {
                    $db->prepare("
                        UPDATE customers 
                        SET current_balance = current_balance - ?
                        WHERE id = ?
                    ")->execute([$effectiveAmount, $customerId]);
                }
                
                // If there's a linked sales_transaction, update it too (optional - column may not exist)
                try {
                    $txnStmt = $db->prepare("SELECT id FROM sales_transactions WHERE dr_id = ?");
                    $txnStmt->execute([$drId]);
                    $txn = $txnStmt->fetch();
                    
                    if ($txn) {
                        $db->prepare("
                            UPDATE sales_transactions 
                            SET amount_paid = amount_paid + ?,
                                amount_due = amount_due - ?,
                                payment_status = CASE WHEN amount_due - ? <= 0 THEN 'paid' ELSE 'partial' END,
                                paid_at = CASE WHEN amount_due - ? <= 0 THEN NOW() ELSE paid_at END
                            WHERE id = ?
                        ")->execute([$effectiveAmount, $effectiveAmount, $effectiveAmount, $effectiveAmount, $txn['id']]);
                    }
                } catch (Exception $e) {
                    // dr_id column may not exist - skip sales_transaction update
                    error_log("Skipping sales_transaction update: " . $e->getMessage());
                }
                
                // Log audit
                logAudit(
                    $currentUser['user_id'],
                    'create',
                    'payment_collections',
                    $collectionId,
                    null,
                    [
                        'or_number' => $orNumber,
                        'dr_number' => $dr['dr_number'],
                        'amount' => $effectiveAmount,
                        'method' => $paymentMethod
                    ]
                );
                
                $db->commit();
                
                Response::created([
                    'collection_id' => $collectionId,
                    'or_number' => $orNumber,
                    'dr_number' => $dr['dr_number'],
                    'customer_name' => $dr['customer_name'],
                    'amount_collected' => $effectiveAmount,
                    'balance_before' => $currentBalance,
                    'balance_after' => $newBalance,
                    'payment_status' => $newPaymentStatus,
                    'payment_method' => $paymentMethod,
                    'is_fully_paid' => $newBalance <= 0,
                    'overpayment_warning' => $isOverpayment ? "Payment exceeds balance. Only {$effectiveAmount} was applied." : null
                ], 'Collection recorded successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'mark_bounced':
            // Mark a check as bounced
            $id = $data['id'] ?? null;
            $orNumber = $data['or_number'] ?? null;
            $reason = $data['reason'] ?? 'Check bounced';
            
            if (!$id && !$orNumber) {
                Response::error('Collection ID or OR number required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Get collection
                $sql = "SELECT * FROM payment_collections WHERE " . ($id ? "id = ?" : "or_number = ?") . " FOR UPDATE";
                $stmt = $db->prepare($sql);
                $stmt->execute([$id ?: $orNumber]);
                $collection = $stmt->fetch();
                
                if (!$collection) {
                    throw new Exception('Collection record not found');
                }
                
                if ($collection['payment_method'] !== 'check') {
                    throw new Exception('Only check payments can be marked as bounced');
                }
                
                if ($collection['status'] !== 'confirmed') {
                    throw new Exception('Collection is already ' . $collection['status']);
                }
                
                // Mark as bounced
                $db->prepare("
                    UPDATE payment_collections 
                    SET status = 'bounced',
                        notes = CONCAT(COALESCE(notes, ''), '\n[BOUNCED: ', ?, ']')
                    WHERE id = ?
                ")->execute([$reason, $collection['id']]);
                
                // Reverse the DR payment
                $db->prepare("
                    UPDATE delivery_receipts 
                    SET amount_paid = amount_paid - ?,
                        payment_status = CASE 
                            WHEN amount_paid - ? <= 0 THEN 'unpaid'
                            ELSE 'partial'
                        END
                    WHERE id = ?
                ")->execute([
                    $collection['amount_collected'],
                    $collection['amount_collected'],
                    $collection['dr_id']
                ]);
                
                // Reverse customer balance
                if ($collection['customer_id']) {
                    $db->prepare("
                        UPDATE customers 
                        SET current_balance = current_balance + ?
                        WHERE id = ?
                    ")->execute([$collection['amount_collected'], $collection['customer_id']]);
                }
                
                logAudit(
                    $currentUser['user_id'],
                    'bounce',
                    'payment_collections',
                    $collection['id'],
                    ['status' => 'confirmed'],
                    ['status' => 'bounced', 'reason' => $reason]
                );
                
                $db->commit();
                
                Response::success([
                    'collection_id' => $collection['id'],
                    'or_number' => $collection['or_number'],
                    'status' => 'bounced',
                    'amount_reversed' => $collection['amount_collected']
                ], 'Collection marked as bounced');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'cancel':
            // Cancel a collection (admin action)
            $id = $data['id'] ?? null;
            $reason = $data['reason'] ?? null;
            
            if (!$id) {
                Response::error('Collection ID required', 400);
            }
            if (!$reason) {
                Response::error('Cancellation reason required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                $stmt = $db->prepare("SELECT * FROM payment_collections WHERE id = ? FOR UPDATE");
                $stmt->execute([$id]);
                $collection = $stmt->fetch();
                
                if (!$collection) {
                    throw new Exception('Collection record not found');
                }
                
                if ($collection['status'] !== 'confirmed') {
                    throw new Exception('Only confirmed collections can be cancelled');
                }
                
                // Mark as cancelled
                $db->prepare("
                    UPDATE payment_collections 
                    SET status = 'cancelled',
                        notes = CONCAT(COALESCE(notes, ''), '\n[CANCELLED: ', ?, ']')
                    WHERE id = ?
                ")->execute([$reason, $id]);
                
                // Reverse the DR payment
                $db->prepare("
                    UPDATE delivery_receipts 
                    SET amount_paid = amount_paid - ?,
                        payment_status = CASE 
                            WHEN amount_paid - ? <= 0 THEN 'unpaid'
                            ELSE 'partial'
                        END
                    WHERE id = ?
                ")->execute([
                    $collection['amount_collected'],
                    $collection['amount_collected'],
                    $collection['dr_id']
                ]);
                
                // Reverse customer balance
                if ($collection['customer_id']) {
                    $db->prepare("
                        UPDATE customers 
                        SET current_balance = current_balance + ?
                        WHERE id = ?
                    ")->execute([$collection['amount_collected'], $collection['customer_id']]);
                }
                
                $db->commit();
                
                Response::success([
                    'collection_id' => $id,
                    'status' => 'cancelled'
                ], 'Collection cancelled successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

// Cache buster: 20260203192649
