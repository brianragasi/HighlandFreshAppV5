<?php
/**
 * Highland Fresh System - Sales Customers API
 * 
 * Customer management for Sales Custodian
 * Uses existing tables: customers, delivery_receipts, payment_collections
 * 
 * GET actions: list, detail, search, aging
 * POST actions: create
 * PUT actions: update, update_credit_limit
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Sales Custodian or GM role
$currentUser = Auth::requireRole(['sales_custodian', 'general_manager']);

$action = getParam('action', 'list');

// Valid customer types
$validCustomerTypes = ['walk_in', 'institutional', 'supermarket', 'feeding_program', 'wholesaler', 'retailer'];
$validPaymentTypes = ['cash', 'credit', 'check', 'bank_transfer'];

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action, $validCustomerTypes);
            break;
        case 'POST':
            handlePost($db, $action, $currentUser, $validCustomerTypes, $validPaymentTypes);
            break;
        case 'PUT':
            handlePut($db, $action, $currentUser, $validCustomerTypes, $validPaymentTypes);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Sales Customers API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

/**
 * Handle GET requests
 */
function handleGet($db, $action, $validCustomerTypes) {
    switch ($action) {
        case 'list':
            $type = getParam('type');
            $status = getParam('status');
            $search = getParam('search');
            $payment_type = getParam('payment_type');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT c.*, 
                    (SELECT COALESCE(SUM(dr.total_amount - dr.amount_paid), 0) 
                     FROM delivery_receipts dr 
                     WHERE dr.customer_id = c.id 
                     AND dr.payment_status != 'paid' 
                     AND dr.status NOT IN ('cancelled', 'draft')) as outstanding_balance
                    FROM customers c WHERE 1=1";
            $params = [];
            
            if ($type && in_array($type, $validCustomerTypes)) {
                $sql .= " AND c.customer_type = ?";
                $params[] = $type;
            }
            
            if ($status) {
                $sql .= " AND c.status = ?";
                $params[] = $status;
            }
            
            if ($payment_type) {
                $sql .= " AND c.default_payment_type = ?";
                $params[] = $payment_type;
            }
            
            if ($search) {
                $sql .= " AND (c.name LIKE ? OR c.customer_code LIKE ? OR c.contact_person LIKE ? OR c.contact_number LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Get total count
            $countSql = preg_replace('/SELECT c\.\*.*?FROM customers c/s', 'SELECT COUNT(*) as total FROM customers c', $sql);
            $countStmt = $db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            $sql .= " ORDER BY c.name ASC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $customers = $stmt->fetchAll();
            
            Response::paginated($customers, $total, $page, $limit, 'Customers retrieved');
            break;
            
        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('Customer ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT c.*,
                    (SELECT COALESCE(SUM(dr.total_amount - dr.amount_paid), 0) 
                     FROM delivery_receipts dr 
                     WHERE dr.customer_id = c.id 
                     AND dr.payment_status != 'paid' 
                     AND dr.status NOT IN ('cancelled', 'draft')) as outstanding_balance,
                    (SELECT COUNT(*) FROM delivery_receipts dr WHERE dr.customer_id = c.id) as total_orders,
                    (SELECT COALESCE(SUM(dr.total_amount), 0) 
                     FROM delivery_receipts dr 
                     WHERE dr.customer_id = c.id 
                     AND dr.status NOT IN ('cancelled', 'draft')) as lifetime_value
                FROM customers c 
                WHERE c.id = ?
            ");
            $stmt->execute([$id]);
            $customer = $stmt->fetch();
            
            if (!$customer) {
                Response::notFound('Customer not found');
            }
            
            // Get recent delivery receipts (orders/invoices)
            $ordersStmt = $db->prepare("
                SELECT id, dr_number as order_number, created_at as order_date, status, 
                       total_amount, amount_paid, payment_status
                FROM delivery_receipts
                WHERE customer_id = ?
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $ordersStmt->execute([$id]);
            $customer['recent_orders'] = $ordersStmt->fetchAll();
            
            // Get recent payments
            $paymentsStmt = $db->prepare("
                SELECT pc.id, pc.or_number, pc.amount_collected, pc.payment_method, 
                       pc.collected_at, pc.dr_number
                FROM payment_collections pc
                WHERE pc.customer_id = ? AND pc.status = 'confirmed'
                ORDER BY pc.collected_at DESC
                LIMIT 10
            ");
            $paymentsStmt->execute([$id]);
            $customer['recent_payments'] = $paymentsStmt->fetchAll();
            
            Response::success($customer, 'Customer details retrieved');
            break;
            
        case 'search':
            $query = getParam('q');
            if (!$query || strlen($query) < 2) {
                Response::success([], 'Search query too short');
                break;
            }
            
            $stmt = $db->prepare("
                SELECT c.id, c.customer_code, c.name as customer_name, c.customer_type, 
                       c.address, c.contact_number as phone, c.default_payment_type, c.credit_limit,
                    (SELECT COALESCE(SUM(dr.total_amount - dr.amount_paid), 0) 
                     FROM delivery_receipts dr 
                     WHERE dr.customer_id = c.id 
                     AND dr.payment_status != 'paid' 
                     AND dr.status NOT IN ('cancelled', 'draft')) as outstanding_balance
                FROM customers c
                WHERE c.status = 'active'
                AND (c.name LIKE ? OR c.customer_code LIKE ? OR c.contact_person LIKE ?)
                ORDER BY c.name ASC
                LIMIT 20
            ");
            $searchTerm = "%$query%";
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
            $results = $stmt->fetchAll();
            
            Response::success($results, 'Search results');
            break;
            
        case 'aging':
            // Get customers with outstanding balances
            $type = getParam('type');
            $minBalance = (float)getParam('min_balance', 0);
            
            $sql = "
                SELECT c.id, c.customer_code, c.name as customer_name, c.customer_type, 
                       c.contact_number as phone, c.credit_limit,
                    COALESCE(SUM(CASE WHEN dr.delivered_at IS NULL OR DATEDIFF(CURDATE(), dr.delivered_at) <= 30 
                        THEN (dr.total_amount - dr.amount_paid) ELSE 0 END), 0) as balance_0_30,
                    COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), dr.delivered_at) > 30 AND DATEDIFF(CURDATE(), dr.delivered_at) <= 60 
                        THEN (dr.total_amount - dr.amount_paid) ELSE 0 END), 0) as balance_31_60,
                    COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), dr.delivered_at) > 60 AND DATEDIFF(CURDATE(), dr.delivered_at) <= 90 
                        THEN (dr.total_amount - dr.amount_paid) ELSE 0 END), 0) as balance_61_90,
                    COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), dr.delivered_at) > 90 
                        THEN (dr.total_amount - dr.amount_paid) ELSE 0 END), 0) as balance_91_plus,
                    COALESCE(SUM(dr.total_amount - dr.amount_paid), 0) as total_outstanding
                FROM customers c
                LEFT JOIN delivery_receipts dr ON c.id = dr.customer_id 
                    AND dr.payment_status != 'paid' 
                    AND dr.status NOT IN ('cancelled', 'draft')
                WHERE c.status = 'active'
            ";
            $params = [];
            
            if ($type && in_array($type, $validCustomerTypes)) {
                $sql .= " AND c.customer_type = ?";
                $params[] = $type;
            }
            
            $sql .= " GROUP BY c.id HAVING total_outstanding > ? ORDER BY total_outstanding DESC";
            $params[] = $minBalance;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $customers = $stmt->fetchAll();
            
            Response::success($customers, 'Aging report retrieved');
            break;
            
        case 'sub_accounts':
            // Get sub-accounts for a customer (e.g., schools under DepEd feeding program)
            $customerId = getParam('customer_id');
            if (!$customerId) {
                Response::error('Customer ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT id, sub_name as name, address as location, contact_person, contact_number, status, created_at
                FROM sales_customer_sub_accounts
                WHERE customer_id = ? AND status = 'active'
                ORDER BY sub_name ASC
            ");
            $stmt->execute([$customerId]);
            $subAccounts = $stmt->fetchAll();
            
            Response::success($subAccounts, 'Sub-accounts retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle POST requests
 */
function handlePost($db, $action, $currentUser, $validCustomerTypes, $validPaymentTypes) {
    $data = getRequestBody();
    
    if ($action === 'create') {
        // Validation
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Customer name is required';
        }
        
        if (empty($data['customer_type']) || !in_array($data['customer_type'], $validCustomerTypes)) {
            $errors['customer_type'] = 'Valid customer type is required: ' . implode(', ', $validCustomerTypes);
        }
        
        $paymentType = $data['default_payment_type'] ?? 'cash';
        if (!in_array($paymentType, $validPaymentTypes)) {
            $errors['default_payment_type'] = 'Valid payment type is required: ' . implode(', ', $validPaymentTypes);
        }
        
        if (!empty($errors)) {
            Response::validationError($errors);
        }
        
        // Check for duplicate
        $check = $db->prepare("SELECT id FROM customers WHERE name = ? AND customer_type = ?");
        $check->execute([$data['name'], $data['customer_type']]);
        if ($check->fetch()) {
            Response::error('A customer with this name and type already exists', 400);
        }
        
        // Generate customer code based on type
        $typePrefix = strtoupper(substr($data['customer_type'], 0, 3));
        $codeStmt = $db->prepare("SELECT MAX(CAST(SUBSTRING(customer_code, 5) AS UNSIGNED)) as max_num FROM customers WHERE customer_code LIKE ?");
        $codeStmt->execute([$typePrefix . '-%']);
        $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
        $customerCode = $typePrefix . '-' . str_pad($maxNum + 1, 4, '0', STR_PAD_LEFT);
        
        // Set default credit limit based on type
        $defaultCreditLimits = [
            'walk_in' => 0,
            'institutional' => 100000,
            'supermarket' => 500000,
            'feeding_program' => 200000,
            'wholesaler' => 50000,
            'retailer' => 20000
        ];
        $creditLimit = $data['credit_limit'] ?? $defaultCreditLimits[$data['customer_type']] ?? 0;
        
        $stmt = $db->prepare("
            INSERT INTO customers 
            (customer_code, name, customer_type, contact_person, contact_number, email, address,
             default_payment_type, credit_limit, payment_terms_days, status, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
        ");
        
        $stmt->execute([
            $customerCode,
            $data['name'],
            $data['customer_type'],
            $data['contact_person'] ?? null,
            $data['contact_number'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            $paymentType,
            $creditLimit,
            $data['payment_terms_days'] ?? 30,
            $data['notes'] ?? null
        ]);
        
        $customerId = $db->lastInsertId();
        
        logAudit($currentUser['user_id'], 'CREATE', 'customers', $customerId, null, $data);
        
        // Get created customer
        $getStmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
        $getStmt->execute([$customerId]);
        $customer = $getStmt->fetch();
        
        Response::created($customer, 'Customer created successfully');
    }
    
    Response::error('Invalid action', 400);
}

/**
 * Handle PUT requests
 */
function handlePut($db, $action, $currentUser, $validCustomerTypes, $validPaymentTypes) {
    $data = getRequestBody();
    $id = getParam('id') ?? ($data['id'] ?? null);
    
    if (!$id) {
        Response::error('Customer ID required', 400);
    }
    
    // Get current customer
    $check = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $check->execute([$id]);
    $current = $check->fetch();
    
    if (!$current) {
        Response::notFound('Customer not found');
    }
    
    switch ($action) {
        case 'update':
            // Validate customer type if provided
            if (!empty($data['customer_type']) && !in_array($data['customer_type'], $validCustomerTypes)) {
                Response::validationError(['customer_type' => 'Invalid customer type']);
            }
            
            // Validate payment type if provided
            if (!empty($data['default_payment_type']) && !in_array($data['default_payment_type'], $validPaymentTypes)) {
                Response::validationError(['default_payment_type' => 'Invalid payment type']);
            }
            
            $stmt = $db->prepare("
                UPDATE customers SET
                    name = COALESCE(?, name),
                    customer_type = COALESCE(?, customer_type),
                    contact_person = COALESCE(?, contact_person),
                    contact_number = COALESCE(?, contact_number),
                    email = COALESCE(?, email),
                    address = COALESCE(?, address),
                    default_payment_type = COALESCE(?, default_payment_type),
                    payment_terms_days = COALESCE(?, payment_terms_days),
                    notes = COALESCE(?, notes),
                    status = COALESCE(?, status),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['name'] ?? null,
                $data['customer_type'] ?? null,
                $data['contact_person'] ?? null,
                $data['contact_number'] ?? null,
                $data['email'] ?? null,
                $data['address'] ?? null,
                $data['default_payment_type'] ?? null,
                $data['payment_terms_days'] ?? null,
                $data['notes'] ?? null,
                $data['status'] ?? null,
                $id
            ]);
            
            logAudit($currentUser['user_id'], 'UPDATE', 'customers', $id, $current, $data);
            
            // Get updated customer
            $getStmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
            $getStmt->execute([$id]);
            $customer = $getStmt->fetch();
            
            Response::success($customer, 'Customer updated successfully');
            break;
            
        case 'update_credit_limit':
            if (!isset($data['credit_limit'])) {
                Response::validationError(['credit_limit' => 'Credit limit is required']);
            }
            
            $newLimit = (float) $data['credit_limit'];
            if ($newLimit < 0) {
                Response::validationError(['credit_limit' => 'Credit limit cannot be negative']);
            }
            
            // Check outstanding balance
            $balanceStmt = $db->prepare("
                SELECT COALESCE(SUM(total_amount - amount_paid), 0) as outstanding 
                FROM delivery_receipts 
                WHERE customer_id = ? 
                AND payment_status != 'paid' 
                AND status NOT IN ('cancelled', 'draft')
            ");
            $balanceStmt->execute([$id]);
            $outstanding = $balanceStmt->fetch()['outstanding'];
            
            if ($newLimit < $outstanding) {
                Response::error("Cannot set credit limit below outstanding balance of " . number_format($outstanding, 2), 400);
            }
            
            $stmt = $db->prepare("
                UPDATE customers SET
                    credit_limit = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$newLimit, $id]);
            
            logAudit($currentUser['user_id'], 'UPDATE_CREDIT_LIMIT', 'customers', $id, 
                ['credit_limit' => $current['credit_limit']], 
                ['credit_limit' => $newLimit]
            );
            
            Response::success([
                'id' => $id,
                'credit_limit' => $newLimit,
                'outstanding_balance' => $outstanding,
                'available_credit' => $newLimit - $outstanding
            ], 'Credit limit updated successfully');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
