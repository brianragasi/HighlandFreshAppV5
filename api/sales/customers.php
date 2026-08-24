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
require_once dirname(__DIR__) . '/helpers/plain_text.php';
require_once dirname(__DIR__) . '/helpers/customer_accounts.php';

// Require Sales Custodian or GM role
$currentUser = Auth::requireRole(['sales_custodian', 'general_manager']);

$action = getParam('action', 'list');

// Values must match the customer table. The UI calls distributors "Wholesalers".
$validCustomerTypes = ['walk_in', 'institutional', 'supermarket', 'feeding_program', 'distributor', 'restaurant'];
$validPaymentTypes = ['cash', 'credit'];

function hfNormalizeSalesCustomerType(?string $type): string {
    $value = strtolower(trim((string) $type));
    return [
        'wholesaler' => 'distributor',
        'retailer' => 'walk_in',
        'individual' => 'walk_in',
    ][$value] ?? $value;
}

try {
    $db = Database::getInstance()->getConnection();
    hfEnsureCustomerAccountSchema($db);
    
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
            $type = hfNormalizeSalesCustomerType(getParam('type'));
            $status = getParam('status');
            $search = getParam('search');
            $payment_type = getParam('payment_type');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $outstandingSql = hfCustomerOutstandingSql('c.id');
            $sql = "SELECT c.*, {$outstandingSql} as outstanding_balance
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

            foreach ($customers as &$c) {
                $c['phone'] = $c['contact_number'] ?? $c['phone'] ?? null;
                $c['payment_terms'] = (int)($c['payment_terms_days'] ?? $c['payment_terms'] ?? 0);
                $c['outstanding_balance'] = round((float)($c['outstanding_balance'] ?? 0), 2);
                $c['current_balance'] = $c['outstanding_balance'];
                $c['default_payment_mode'] = $c['default_payment_type'] ?? 'cash';
            }
            unset($c);
            
            Response::paginated($customers, $total, $page, $limit, 'Customers retrieved');
            break;
            
        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('Customer ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT c.*,
                    c.contact_number as phone,
                    c.payment_terms_days as payment_terms,
                    (SELECT COALESCE(SUM(dr.total_amount - COALESCE(dr.amount_paid, 0)), 0)
                     FROM delivery_receipts dr
                     WHERE dr.customer_id = c.id
                     AND dr.payment_status != 'paid'
                     AND dr.status NOT IN ('cancelled', 'draft')
                     AND (dr.total_amount - COALESCE(dr.amount_paid, 0)) > 0) as outstanding_balance,
                    (SELECT COUNT(*)
                     FROM delivery_receipts dr
                     WHERE dr.customer_id = c.id
                     AND dr.status NOT IN ('cancelled', 'draft')) as total_orders,
                    (SELECT COALESCE(SUM(dr.total_amount), 0)
                     FROM delivery_receipts dr
                     WHERE dr.customer_id = c.id
                     AND dr.status NOT IN ('cancelled', 'draft')) as lifetime_value,
                    (SELECT COALESCE(AVG(dr.total_amount), 0)
                     FROM delivery_receipts dr
                     WHERE dr.customer_id = c.id
                     AND dr.status NOT IN ('cancelled', 'draft')) as avg_order_value
                FROM customers c
                WHERE c.id = ?
            ");
            $stmt->execute([$id]);
            $customer = $stmt->fetch();
            
            if (!$customer) {
                Response::notFound('Customer not found');
            }

            $orders = (int)($customer['total_orders'] ?? 0);
            $sales = (float)($customer['lifetime_value'] ?? 0);
            $customer['order_count'] = $orders;
            $customer['total_sales'] = round($sales, 2);
            $customer['avg_order_value'] = $orders > 0
                ? round($sales / $orders, 2)
                : round((float)($customer['avg_order_value'] ?? 0), 2);
            $customer['outstanding_balance'] = round((float)($customer['outstanding_balance'] ?? 0), 2);
            $customer['current_balance'] = $customer['outstanding_balance'];
            $customer['default_payment_mode'] = $customer['default_payment_type'] ?? 'cash';
            $limit = (float)($customer['credit_limit'] ?? 0);
            $outstanding = (float)$customer['outstanding_balance'];
            $customer['available_credit'] = $limit > 0 ? max(0, round($limit - $outstanding, 2)) : null;
            
            // Recent completed Delivery Receipts only (never PICK- / draft picking tickets)
            $ordersStmt = $db->prepare("
                SELECT id, dr_number as order_number, dr_number,
                       DATE(COALESCE(delivered_at, created_at)) as order_date,
                       COALESCE(delivered_at, created_at) as created_at,
                       status, total_amount,
                       COALESCE(amount_paid, 0) as amount_paid,
                       payment_status,
                       (total_amount - COALESCE(amount_paid, 0)) as balance_due
                FROM delivery_receipts
                WHERE customer_id = ?
                  AND status NOT IN ('cancelled', 'draft', 'picking', 'pending', 'preparing')
                  AND dr_number NOT LIKE 'PICK-%'
                  AND dr_number LIKE 'DR-%'
                ORDER BY COALESCE(delivered_at, created_at) DESC
                LIMIT 12
            ");
            $ordersStmt->execute([$id]);
            $customer['recent_orders'] = $ordersStmt->fetchAll();

            // Get recent payments
            $paymentsStmt = $db->prepare("
                SELECT pc.id, pc.or_number, pc.amount_collected, pc.payment_method, 
                       pc.collected_at, pc.dr_number
                FROM payment_collections pc
                WHERE pc.customer_id = ? AND pc.status IN ('confirmed', 'cleared')
                ORDER BY pc.collected_at DESC
                LIMIT 10
            ");
            $paymentsStmt->execute([$id]);
            $customer['recent_payments'] = $paymentsStmt->fetchAll();

            // Running AR ledger (debits = invoices, credits = payments) ending at outstanding
            $customer['balance_history'] = buildCustomerBalanceHistory($db, (int)$id, (float)$outstanding);
            $customer['locations'] = hfCustomerLocations($db, (int) $id, true);
            
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
            // Group each unpaid balance by the number of days past its payment due date.
            $type = getParam('type');
            $minBalance = (float)getParam('min_balance', 0);
            
            $invoiceDateExpr = "DATE(COALESCE(dr.delivered_at, dr.created_at))";
            $dueDateExpr = "COALESCE(dr.due_date, {$invoiceDateExpr})";
            $daysLateExpr = "DATEDIFF(CURDATE(), {$dueDateExpr})";
            $balExpr = "(dr.total_amount - COALESCE(dr.amount_paid, 0))";

            $sql = "
                SELECT c.id, c.customer_code, c.name as customer_name, c.customer_type, 
                       c.contact_number as phone, c.contact_person, c.email, c.credit_limit,
                    COALESCE(SUM(CASE WHEN {$daysLateExpr} <= 0 THEN {$balExpr} ELSE 0 END), 0) as balance_current,
                    COALESCE(SUM(CASE WHEN {$daysLateExpr} BETWEEN 1 AND 30 THEN {$balExpr} ELSE 0 END), 0) as balance_1_30,
                    COALESCE(SUM(CASE WHEN {$daysLateExpr} BETWEEN 31 AND 60 THEN {$balExpr} ELSE 0 END), 0) as balance_31_60,
                    COALESCE(SUM(CASE WHEN {$daysLateExpr} BETWEEN 61 AND 90 THEN {$balExpr} ELSE 0 END), 0) as balance_61_90,
                    COALESCE(SUM(CASE WHEN {$daysLateExpr} > 90 THEN {$balExpr} ELSE 0 END), 0) as balance_91_plus,
                    COALESCE(SUM({$balExpr}), 0) as total_outstanding
                FROM customers c
                LEFT JOIN delivery_receipts dr ON c.id = dr.customer_id 
                    AND dr.payment_status != 'paid' 
                    AND dr.status NOT IN ('cancelled', 'draft')
                    AND {$balExpr} > 0
                WHERE c.status = 'active'
            ";
            $params = [];
            
            if ($type && in_array($type, $validCustomerTypes)) {
                $sql .= " AND c.customer_type = ?";
                $params[] = $type;
            }
            
            $sql .= " GROUP BY c.id, c.customer_code, c.name, c.customer_type, c.contact_number, c.contact_person, c.email, c.credit_limit HAVING total_outstanding > ? ORDER BY total_outstanding DESC";
            $params[] = $minBalance;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $customers = $stmt->fetchAll();

            // Enrich: Total MUST equal sum of buckets; credit flags use that total
            foreach ($customers as &$row) {
                $current = round((float)$row['balance_current'], 2);
                $b1 = round((float)$row['balance_1_30'], 2);
                $b2 = round((float)$row['balance_31_60'], 2);
                $b3 = round((float)$row['balance_61_90'], 2);
                $b4 = round((float)$row['balance_91_plus'], 2);
                $total = round($current + $b1 + $b2 + $b3 + $b4, 2);
                $row['balance_current'] = $current;
                $row['balance_1_30'] = $b1;
                $row['balance_31_60'] = $b2;
                $row['balance_61_90'] = $b3;
                $row['balance_91_plus'] = $b4;
                $row['balance_0_30'] = round($current + $b1, 2);
                $row['total_outstanding'] = $total;
                $limit = (float)$row['credit_limit'];
                $row['over_limit'] = ($limit > 0 && $total > $limit) ? 1 : 0;
                $row['available_credit'] = $limit > 0 ? max(0, round($limit - $total, 2)) : null;
                $row['over_by'] = ($limit > 0 && $total > $limit) ? round($total - $limit, 2) : 0;
                $row['past_due'] = round($b1 + $b2 + $b3 + $b4, 2);
            }
            unset($row);
            
            Response::success($customers, 'Aging report retrieved');
            break;

        case 'open_items':
            // Return the real unpaid Delivery Receipts behind a selected report amount.
            $customerId = (int) getParam('customer_id');
            $bucket = trim((string) getParam('bucket', '')); // current|1-30|31-60|61-90|over90|''
            if (!$customerId) {
                Response::error('customer_id is required', 400);
            }

            $cust = $db->prepare("SELECT id, customer_code, name, customer_type, credit_limit, contact_person, contact_number, email FROM customers WHERE id = ?");
            $cust->execute([$customerId]);
            $customer = $cust->fetch();
            if (!$customer) {
                Response::error('Customer not found', 404);
            }

            $invoiceDateExpr = "DATE(COALESCE(dr.delivered_at, dr.created_at))";
            $dueDateExpr = "COALESCE(dr.due_date, {$invoiceDateExpr})";
            $daysLateExpr = "DATEDIFF(CURDATE(), {$dueDateExpr})";
            $balExpr = "(dr.total_amount - COALESCE(dr.amount_paid, 0))";

            $sql = "
                SELECT
                    dr.id,
                    dr.dr_number,
                    dr.status,
                    dr.payment_status,
                    dr.total_amount,
                    COALESCE(dr.amount_paid, 0) as amount_paid,
                    {$balExpr} as balance_due,
                    {$invoiceDateExpr} as document_date,
                    {$dueDateExpr} as due_date,
                    {$daysLateExpr} as days_late,
                    CASE
                        WHEN {$daysLateExpr} <= 0 THEN 'current'
                        WHEN {$daysLateExpr} BETWEEN 1 AND 30 THEN '1-30'
                        WHEN {$daysLateExpr} BETWEEN 31 AND 60 THEN '31-60'
                        WHEN {$daysLateExpr} BETWEEN 61 AND 90 THEN '61-90'
                        ELSE 'over90'
                    END as age_bucket
                FROM delivery_receipts dr
                LEFT JOIN customers c ON c.id = dr.customer_id
                WHERE dr.customer_id = ?
                  AND dr.payment_status != 'paid'
                  AND dr.status NOT IN ('cancelled', 'draft')
                  AND {$balExpr} > 0
            ";
            $params = [$customerId];

            if ($bucket === 'current') {
                $sql .= " AND {$daysLateExpr} <= 0";
            } elseif ($bucket === '1-30') {
                $sql .= " AND {$daysLateExpr} BETWEEN 1 AND 30";
            } elseif ($bucket === '31-60') {
                $sql .= " AND {$daysLateExpr} BETWEEN 31 AND 60";
            } elseif ($bucket === '61-90') {
                $sql .= " AND {$daysLateExpr} BETWEEN 61 AND 90";
            } elseif ($bucket === 'over90') {
                $sql .= " AND {$daysLateExpr} > 90";
            }

            $sql .= " ORDER BY {$dueDateExpr} ASC, dr.id ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll();

            $bucketLabels = [
                'current' => 'Not yet due',
                '1-30' => '1-30 days late',
                '31-60' => '31-60 days late',
                '61-90' => '61-90 days late',
                'over90' => 'Over 90 days late'
            ];
            foreach ($items as &$item) {
                $item['balance_due'] = round((float)$item['balance_due'], 2);
                $item['total_amount'] = round((float)$item['total_amount'], 2);
                $item['amount_paid'] = round((float)$item['amount_paid'], 2);
                $item['days_late'] = (int)$item['days_late'];
                $item['bucket_label'] = $bucketLabels[$item['age_bucket']] ?? $item['age_bucket'];
            }
            unset($item);

            $totalOpen = 0.0;
            foreach ($items as $it) {
                $totalOpen += (float)$it['balance_due'];
            }

            Response::success([
                'customer' => $customer,
                'bucket' => $bucket !== '' ? $bucket : 'all',
                'items' => $items,
                'summary' => [
                    'item_count' => count($items),
                    'total_open' => round($totalOpen, 2)
                ]
            ], 'Open items retrieved');
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
        $data = hfPlainTextFields($data, [
            'name' => [160, false], 'customer_name' => [160, false],
            'contact_person' => [160, false], 'address' => [500, true],
            'blocked_reason' => [500, true], 'notes' => [1000, true],
        ]);
        $data['name'] = $data['name'] ?? $data['customer_name'] ?? null;
        $data['customer_type'] = hfNormalizeSalesCustomerType($data['customer_type'] ?? '');
        $contactCheck = hfValidateContactPayload($data, ['phone', 'contact_number'], 'email');
        if (!empty($contactCheck['errors'])) {
            Response::validationError($contactCheck['errors']);
        }
        $data = hfCustomerNormalizePayload($contactCheck['data']);
        $errors = hfValidateCustomerAccountPayload($data, [], true);
        if (!hfPersonNameHasLetter($data['contact_person'] ?? '', true)) {
            $errors['contact_person'] = 'Contact person must contain at least one letter.';
        }
        if ($errors) {
            Response::validationError($errors);
        }
        $paymentType = $data['default_payment_type'];
        
        // Check for duplicate
        $check = $db->prepare("SELECT id FROM customers WHERE name = ? AND customer_type = ?");
        $check->execute([$data['name'], $data['customer_type']]);
        if ($check->fetch()) {
            Response::error('A customer with this name and type already exists', 400);
        }
        if (!empty($data['email'])) {
            $emailCheck = $db->prepare("SELECT id FROM customers WHERE status = 'active' AND LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1");
            $emailCheck->execute([$data['email']]);
            if ($emailCheck->fetch()) {
                Response::error('This email is already assigned to another active customer', 409);
            }
        }
        
        // Generate customer code based on type
        $typePrefix = strtoupper(substr($data['customer_type'], 0, 3));
        $codeStmt = $db->prepare("SELECT MAX(CAST(SUBSTRING(customer_code, 5) AS UNSIGNED)) as max_num FROM customers WHERE customer_code LIKE ?");
        $codeStmt->execute([$typePrefix . '-%']);
        $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
        $customerCode = $typePrefix . '-' . str_pad($maxNum + 1, 4, '0', STR_PAD_LEFT);
        
        $stmt = $db->prepare("
            INSERT INTO customers 
            (customer_code, name, customer_type, sub_location, contact_person, contact_number, email, address,
             default_payment_type, credit_limit, current_balance, payment_terms_days, status, blocked_reason, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $customerCode,
            $data['name'],
            $data['customer_type'],
            $data['sub_location'] ?? null,
            $data['contact_person'] ?? null,
            $data['phone'] ?? $data['contact_number'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            $paymentType,
            $data['credit_limit'] ?? 0,
            $data['payment_terms_days'] ?? 0,
            $data['status'] ?? 'active',
            $data['blocked_reason'] ?? null,
            $data['notes'] ?? null
        ]);
        
        $customerId = $db->lastInsertId();
        if (isset($data['locations']) && is_array($data['locations'])) {
            hfSaveCustomerLocations($db, (int) $customerId, $data['locations']);
        }
        
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
    $data = hfPlainTextFields($data, [
        'name' => [160, false], 'customer_name' => [160, false],
        'contact_person' => [160, false], 'address' => [500, true],
        'blocked_reason' => [500, true], 'notes' => [1000, true],
    ]);
    if (array_key_exists('customer_name', $data) && !array_key_exists('name', $data)) {
        $data['name'] = $data['customer_name'];
    }
    if (array_key_exists('customer_type', $data)) {
        $data['customer_type'] = hfNormalizeSalesCustomerType($data['customer_type']);
    }
    if (array_key_exists('default_payment_mode', $data) && !array_key_exists('default_payment_type', $data)) {
        $data['default_payment_type'] = $data['default_payment_mode'];
    }
    $contactCheck = hfValidateContactPayload($data, ['phone', 'contact_number'], 'email');
    if (!empty($contactCheck['errors'])) {
        Response::validationError($contactCheck['errors']);
    }
    $data = $contactCheck['data'];
    if (array_key_exists('contact_person', $data) && !hfPersonNameHasLetter($data['contact_person'], true)) {
        Response::validationError(['contact_person' => 'Contact person must contain at least one letter when provided']);
    }
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
            $data = hfCustomerNormalizePayload($data, $current);
            $errors = hfValidateCustomerAccountPayload($data, $current);
            if ($errors) {
                Response::validationError($errors);
            }
            $effectiveEmail = array_key_exists('email', $data) ? trim((string) $data['email']) : trim((string) ($current['email'] ?? ''));
            $effectiveStatus = array_key_exists('status', $data) ? (string) $data['status'] : (string) $current['status'];
            if ($effectiveStatus === 'active' && $effectiveEmail !== '') {
                $emailCheck = $db->prepare("SELECT id FROM customers WHERE status = 'active' AND LOWER(TRIM(email)) = LOWER(TRIM(?)) AND id <> ? LIMIT 1");
                $emailCheck->execute([$effectiveEmail, $id]);
                if ($emailCheck->fetch()) {
                    Response::error('This email is already assigned to another active customer', 409);
                }
            }
            
            $stmt = $db->prepare("
                UPDATE customers SET
                    name = COALESCE(?, name),
                    customer_type = COALESCE(?, customer_type),
                    sub_location = COALESCE(?, sub_location),
                    contact_person = COALESCE(?, contact_person),
                    contact_number = COALESCE(?, contact_number),
                    email = COALESCE(?, email),
                    address = COALESCE(?, address),
                    default_payment_type = COALESCE(?, default_payment_type),
                    payment_terms_days = COALESCE(?, payment_terms_days),
                    credit_limit = COALESCE(?, credit_limit),
                    notes = COALESCE(?, notes),
                    status = COALESCE(?, status),
                    blocked_reason = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['name'] ?? null,
                $data['customer_type'] ?? null,
                $data['sub_location'] ?? null,
                $data['contact_person'] ?? null,
                $data['phone'] ?? $data['contact_number'] ?? null,
                $data['email'] ?? null,
                $data['address'] ?? null,
                $data['default_payment_type'] ?? null,
                $data['payment_terms_days'] ?? null,
                $data['credit_limit'] ?? null,
                $data['notes'] ?? null,
                $data['status'] ?? null,
                $data['blocked_reason'] ?? null,
                $id
            ]);
            if (isset($data['locations']) && is_array($data['locations'])) {
                hfSaveCustomerLocations($db, (int) $id, $data['locations']);
            }
            $outstanding = hfSyncCustomerBalance($db, (int) $id);
            
            logAudit($currentUser['user_id'], 'UPDATE', 'customers', $id, $current, $data);
            
            // Get updated customer
            $getStmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
            $getStmt->execute([$id]);
            $customer = $getStmt->fetch();
            $customer['outstanding_balance'] = $outstanding;
            $customer['current_balance'] = $outstanding;
            $customer['locations'] = hfCustomerLocations($db, (int) $id, true);
            
            Response::success($customer, 'Customer updated successfully');
            break;
            
        case 'update_credit_limit':
            if (!isset($data['credit_limit'])) {
                Response::validationError(['credit_limit' => 'Credit limit is required']);
            }
            
            $rawLimit = trim((string) $data['credit_limit']);
            if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $rawLimit)) {
                Response::validationError(['credit_limit' => 'Enter a normal peso amount with up to two decimal places.']);
            }
            $newLimit = (float) $rawLimit;
            if (!is_finite($newLimit) || $newLimit > 9999999999.99) {
                Response::validationError(['credit_limit' => 'Credit limit must not exceed PHP 9,999,999,999.99.']);
            }
            if (($current['default_payment_type'] ?? 'cash') !== 'credit' || $newLimit <= 0) {
                Response::validationError(['credit_limit' => 'Only credit customers can have a positive credit limit.']);
            }
            $outstanding = hfCustomerOutstandingBalance($db, (int) $id);
            
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

/**
 * Build a running AR ledger for a customer from delivery receipts (debits)
 * and confirmed payment collections (credits). Final balance equals outstanding.
 *
 * @return array<int, array<string, mixed>>
 */
function buildCustomerBalanceHistory(PDO $db, int $customerId, float $targetOutstanding): array {
    // Most recent documents first from DB, then reverse to chronological for running balance
    $drStmt = $db->prepare("
        SELECT
            DATE(COALESCE(delivered_at, created_at)) as entry_date,
            COALESCE(delivered_at, created_at) as sort_ts,
            dr_number,
            total_amount,
            COALESCE(amount_paid, 0) as amount_paid,
            payment_status
        FROM delivery_receipts
        WHERE customer_id = ?
          AND status NOT IN ('cancelled', 'draft')
          AND dr_number LIKE 'DR-%'
          AND dr_number NOT LIKE 'PICK-%'
        ORDER BY COALESCE(delivered_at, created_at) DESC
        LIMIT 24
    ");
    $drStmt->execute([$customerId]);
    $drs = array_reverse($drStmt->fetchAll(PDO::FETCH_ASSOC));

    $payStmt = $db->prepare("
        SELECT
            DATE(collected_at) as entry_date,
            collected_at as sort_ts,
            or_number,
            amount_collected,
            payment_method,
            dr_number
        FROM payment_collections
        WHERE customer_id = ?
          AND status IN ('confirmed', 'cleared')
        ORDER BY collected_at DESC
        LIMIT 24
    ");
    $payStmt->execute([$customerId]);
    $payments = array_reverse($payStmt->fetchAll(PDO::FETCH_ASSOC));

    $events = [];
    foreach ($drs as $dr) {
        $events[] = [
            'sort_ts' => $dr['sort_ts'],
            'entry_date' => $dr['entry_date'],
            'type' => 'debit',
            'description' => 'Invoice ' . $dr['dr_number'],
            'debit' => round((float)$dr['total_amount'], 2),
            'credit' => 0.0,
            'ref' => $dr['dr_number'],
        ];
    }
    foreach ($payments as $pay) {
        $method = $pay['payment_method'] ? str_replace('_', ' ', $pay['payment_method']) : 'payment';
        $events[] = [
            'sort_ts' => $pay['sort_ts'],
            'entry_date' => $pay['entry_date'],
            'type' => 'credit',
            'description' => 'Payment ' . $pay['or_number'] . ' (' . $method . ')',
            'debit' => 0.0,
            'credit' => round((float)$pay['amount_collected'], 2),
            'ref' => $pay['or_number'],
        ];
    }

    usort($events, function ($a, $b) {
        $cmp = strcmp((string)$a['sort_ts'], (string)$b['sort_ts']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp($a['type'], $b['type']);
    });

    if (count($events) > 18) {
        $events = array_slice($events, -18);
    }

    $net = 0.0;
    foreach ($events as $e) {
        $net += $e['debit'] - $e['credit'];
    }
    $opening = round($targetOutstanding - $net, 2);

    $history = [];
    $balance = $opening;

    if (abs($opening) > 0.009) {
        $firstDate = !empty($events[0]['entry_date'])
            ? $events[0]['entry_date']
            : date('Y-m-d');
        $history[] = [
            'entry_date' => $firstDate,
            'description' => 'Opening balance (prior period)',
            'debit' => $opening > 0 ? abs($opening) : 0.0,
            'credit' => $opening < 0 ? abs($opening) : 0.0,
            'balance' => round($opening, 2),
            'type' => 'opening',
        ];
    }

    foreach ($events as $e) {
        $balance = round($balance + $e['debit'] - $e['credit'], 2);
        $history[] = [
            'entry_date' => $e['entry_date'],
            'description' => $e['description'],
            'debit' => $e['debit'],
            'credit' => $e['credit'],
            'balance' => $balance,
            'type' => $e['type'],
            'ref' => $e['ref'],
        ];
    }

    if (!empty($history)) {
        $last = count($history) - 1;
        $history[$last]['balance'] = round($targetOutstanding, 2);
    } else {
        $history[] = [
            'entry_date' => date('Y-m-d'),
            'description' => 'Current outstanding balance',
            'debit' => round($targetOutstanding, 2),
            'credit' => 0.0,
            'balance' => round($targetOutstanding, 2),
            'type' => 'opening',
        ];
    }

    return array_reverse($history);
}
