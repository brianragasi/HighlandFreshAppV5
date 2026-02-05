<?php
/**
 * Admin Customers API
 * Follows highland_fresh_revised.sql schema
 */

require_once __DIR__ . '/../bootstrap.php';

// Require authentication
Auth::requireAuth();

// Get database connection
$conn = Database::getInstance()->getConnection();

// Get request method and handle routing
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$action = isset($_GET['action']) ? $_GET['action'] : null;

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                getCustomer($conn, $id);
            } elseif ($action === 'statistics') {
                getCustomerStatistics($conn);
            } elseif ($action === 'export') {
                exportCustomers($conn);
            } elseif ($action === 'receivables') {
                getReceivables($conn);
            } else {
                getCustomers($conn);
            }
            break;
        case 'POST':
            createCustomer($conn);
            break;
        case 'PUT':
            if ($id) {
                updateCustomer($conn, $id);
            } else {
                sendError('Customer ID required', 400);
            }
            break;
        case 'DELETE':
            if ($id) {
                deleteCustomer($conn, $id);
            } else {
                sendError('Customer ID required', 400);
            }
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

/**
 * Get all customers with pagination and filters
 */
function getCustomers($conn) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $customerType = isset($_GET['customer_type']) ? $_GET['customer_type'] : '';
    $paymentType = isset($_GET['payment_type']) ? $_GET['payment_type'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(c.name LIKE ? OR c.customer_code LIKE ? OR c.contact_person LIKE ? OR c.contact_number LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($customerType) {
        $where[] = "c.customer_type = ?";
        $params[] = $customerType;
    }
    
    if ($paymentType && in_array($paymentType, ['cash', 'credit'])) {
        $where[] = "c.default_payment_type = ?";
        $params[] = $paymentType;
    }
    
    if ($status && in_array($status, ['active', 'inactive', 'blocked'])) {
        $where[] = "c.status = ?";
        $params[] = $status;
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM customers c $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get customers
    $sql = "SELECT 
                c.id,
                c.customer_code,
                c.customer_type,
                c.name,
                c.sub_location,
                c.contact_person,
                c.contact_number,
                c.email,
                c.address,
                c.credit_limit,
                c.current_balance,
                c.payment_terms_days,
                c.default_payment_type,
                c.status,
                c.notes,
                c.created_at,
                c.updated_at,
                (SELECT COUNT(*) FROM sales_orders so WHERE so.customer_id = c.id) as total_orders,
                (SELECT COALESCE(SUM(so.total_amount), 0) FROM sales_orders so WHERE so.customer_id = c.id AND so.status NOT IN ('cancelled', 'rejected')) as total_purchases
            FROM customers c
            $whereClause
            ORDER BY c.name ASC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess([
        'customers' => $customers,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => intval($total),
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get single customer by ID
 */
function getCustomer($conn, $id) {
    $sql = "SELECT 
                c.id,
                c.customer_code,
                c.customer_type,
                c.name,
                c.sub_location,
                c.contact_person,
                c.contact_number,
                c.email,
                c.address,
                c.credit_limit,
                c.current_balance,
                c.payment_terms_days,
                c.default_payment_type,
                c.status,
                c.notes,
                c.created_at,
                c.updated_at
            FROM customers c
            WHERE c.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        sendError('Customer not found', 404);
        return;
    }
    
    // Get recent orders
    $ordersSql = "SELECT 
                    so.id,
                    so.order_number,
                    so.payment_type,
                    so.total_amount,
                    so.amount_paid,
                    so.balance_due,
                    so.payment_status,
                    so.status,
                    so.delivery_date,
                    so.created_at
                  FROM sales_orders so
                  WHERE so.customer_id = ?
                  ORDER BY so.created_at DESC
                  LIMIT 10";
    
    $ordersStmt = $conn->prepare($ordersSql);
    $ordersStmt->execute([$id]);
    $customer['recent_orders'] = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get order statistics
    $statsSql = "SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(total_amount), 0) as total_purchases,
                    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as total_paid,
                    COALESCE(SUM(balance_due), 0) as total_outstanding,
                    COALESCE(AVG(total_amount), 0) as avg_order_value
                 FROM sales_orders 
                 WHERE customer_id = ? AND status NOT IN ('cancelled', 'rejected')";
    
    $statsStmt = $conn->prepare($statsSql);
    $statsStmt->execute([$id]);
    $customer['statistics'] = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    sendSuccess(['customer' => $customer]);
}

/**
 * Create new customer
 */
function createCustomer($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['name', 'customer_type'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendError("Field '$field' is required", 400);
            return;
        }
    }
    
    // Validate customer_type
    $validTypes = ['walk_in', 'institutional', 'supermarket', 'feeding_program', 'distributor', 'restaurant'];
    if (!in_array($data['customer_type'], $validTypes)) {
        sendError('Invalid customer type', 400);
        return;
    }
    
    // Generate customer code if not provided
    if (empty($data['customer_code'])) {
        $prefix = 'CUS';
        $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(customer_code, 4) AS UNSIGNED)) as max_num FROM customers WHERE customer_code LIKE 'CUS%'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNum = ($row['max_num'] ?? 0) + 1;
        $data['customer_code'] = $prefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    }
    
    // Check for duplicate customer code
    if (!empty($data['customer_code'])) {
        $checkStmt = $conn->prepare("SELECT id FROM customers WHERE customer_code = ?");
        $checkStmt->execute([$data['customer_code']]);
        if ($checkStmt->fetch()) {
            sendError('Customer code already exists', 409);
            return;
        }
    }
    
    $sql = "INSERT INTO customers (
                customer_code, customer_type, name, sub_location,
                contact_person, contact_number, email, address,
                credit_limit, current_balance, payment_terms_days,
                default_payment_type, status, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $data['customer_code'],
        $data['customer_type'],
        $data['name'],
        $data['sub_location'] ?? null,
        $data['contact_person'] ?? null,
        $data['contact_number'] ?? null,
        $data['email'] ?? null,
        $data['address'] ?? null,
        $data['credit_limit'] ?? 0.00,
        $data['current_balance'] ?? 0.00,
        $data['payment_terms_days'] ?? 0,
        $data['default_payment_type'] ?? 'cash',
        $data['status'] ?? 'active',
        $data['notes'] ?? null
    ]);
    
    $customerId = $conn->lastInsertId();
    
    sendSuccess([
        'message' => 'Customer created successfully',
        'customer_id' => $customerId,
        'customer_code' => $data['customer_code']
    ], 201);
}

/**
 * Update customer
 */
function updateCustomer($conn, $id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if customer exists
    $checkStmt = $conn->prepare("SELECT id, customer_code FROM customers WHERE id = ?");
    $checkStmt->execute([$id]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        sendError('Customer not found', 404);
        return;
    }
    
    // Build dynamic update
    $updates = [];
    $params = [];
    
    $allowedFields = [
        'customer_code', 'customer_type', 'name', 'sub_location',
        'contact_person', 'contact_number', 'email', 'address',
        'credit_limit', 'current_balance', 'payment_terms_days',
        'default_payment_type', 'status', 'notes'
    ];
    
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($updates)) {
        sendError('No fields to update', 400);
        return;
    }
    
    // Check for duplicate customer code if changing
    if (isset($data['customer_code']) && $data['customer_code'] !== $existing['customer_code']) {
        $dupStmt = $conn->prepare("SELECT id FROM customers WHERE customer_code = ? AND id != ?");
        $dupStmt->execute([$data['customer_code'], $id]);
        if ($dupStmt->fetch()) {
            sendError('Customer code already exists', 409);
            return;
        }
    }
    
    $params[] = $id;
    $sql = "UPDATE customers SET " . implode(', ', $updates) . " WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    sendSuccess(['message' => 'Customer updated successfully']);
}

/**
 * Delete (deactivate) customer
 */
function deleteCustomer($conn, $id) {
    // Check if customer exists
    $checkStmt = $conn->prepare("SELECT id FROM customers WHERE id = ?");
    $checkStmt->execute([$id]);
    
    if (!$checkStmt->fetch()) {
        sendError('Customer not found', 404);
        return;
    }
    
    // Check for related orders
    $relatedStmt = $conn->prepare("SELECT COUNT(*) as count FROM sales_orders WHERE customer_id = ?");
    $relatedStmt->execute([$id]);
    $related = $relatedStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($related['count'] > 0) {
        // Soft delete - set status to inactive
        $stmt = $conn->prepare("UPDATE customers SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);
        sendSuccess(['message' => 'Customer deactivated (has related orders)']);
    } else {
        // Hard delete if no related records
        $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        sendSuccess(['message' => 'Customer deleted successfully']);
    }
}

/**
 * Get customer statistics
 */
function getCustomerStatistics($conn) {
    $stats = [];
    
    // Total customers by status
    $stmt = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked
    FROM customers");
    $stats['totals'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // By customer type
    $stmt = $conn->query("SELECT 
        customer_type, 
        COUNT(*) as count
    FROM customers
    WHERE status = 'active'
    GROUP BY customer_type");
    $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // By payment type
    $stmt = $conn->query("SELECT 
        default_payment_type, 
        COUNT(*) as count
    FROM customers
    WHERE status = 'active'
    GROUP BY default_payment_type");
    $stats['by_payment_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total receivables
    $stmt = $conn->query("SELECT 
        SUM(current_balance) as total_receivables,
        SUM(credit_limit) as total_credit_limit,
        COUNT(CASE WHEN current_balance > 0 THEN 1 END) as customers_with_balance
    FROM customers
    WHERE status = 'active'");
    $stats['receivables'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Top 10 customers by purchases
    $stmt = $conn->query("SELECT 
        c.id,
        c.customer_code,
        c.name,
        c.customer_type,
        COUNT(so.id) as order_count,
        COALESCE(SUM(so.total_amount), 0) as total_purchases
    FROM customers c
    LEFT JOIN sales_orders so ON c.id = so.customer_id AND so.status NOT IN ('cancelled', 'rejected')
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY total_purchases DESC
    LIMIT 10");
    $stats['top_customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess(['statistics' => $stats]);
}

/**
 * Get accounts receivable summary
 */
function getReceivables($conn) {
    // Get customers with outstanding balance
    $sql = "SELECT 
                c.id,
                c.customer_code,
                c.name,
                c.customer_type,
                c.payment_terms_days,
                c.credit_limit,
                c.current_balance,
                (SELECT COUNT(*) FROM sales_orders so 
                 WHERE so.customer_id = c.id AND so.payment_status != 'paid' 
                 AND so.status NOT IN ('cancelled', 'rejected')) as unpaid_orders
            FROM customers c
            WHERE c.current_balance > 0
            ORDER BY c.current_balance DESC";
    
    $stmt = $conn->query($sql);
    $receivables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Summary
    $summaryStmt = $conn->query("SELECT 
        SUM(current_balance) as total_receivables,
        COUNT(*) as customer_count
    FROM customers WHERE current_balance > 0");
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    
    sendSuccess([
        'receivables' => $receivables,
        'summary' => $summary
    ]);
}

/**
 * Export customers to CSV
 */
function exportCustomers($conn) {
    $sql = "SELECT 
                c.customer_code,
                c.customer_type,
                c.name,
                c.sub_location,
                c.contact_person,
                c.contact_number,
                c.email,
                c.address,
                c.credit_limit,
                c.current_balance,
                c.payment_terms_days,
                c.default_payment_type,
                c.status,
                c.created_at
            FROM customers c
            ORDER BY c.customer_code";
    
    $stmt = $conn->query($sql);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess(['export_data' => $customers]);
}
