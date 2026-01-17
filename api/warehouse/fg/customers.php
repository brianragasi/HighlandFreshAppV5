<?php
/**
 * Highland Fresh System - Warehouse FG Customers API
 * 
 * GET - List customers, get details, search
 * POST - Create customer
 * PUT - Update customer
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

// Require Warehouse FG role
$currentUser = Auth::requireRole(['warehouse_fg', 'general_manager', 'sales_custodian']);

$action = getParam('action', 'list');

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
    error_log("Customers API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            $type = getParam('type');
            $status = getParam('status');
            $search = getParam('search');
            
            $sql = "SELECT * FROM customers WHERE 1=1";
            $params = [];
            
            if ($type) {
                $sql .= " AND customer_type = ?";
                $params[] = $type;
            }
            
            if ($status) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }
            
            if ($search) {
                $sql .= " AND (name LIKE ? OR contact_person LIKE ? OR phone LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY name ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $customers = $stmt->fetchAll();
            
            Response::success($customers, 'Customers retrieved');
            break;
            
        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('Customer ID required', 400);
            }
            
            $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$id]);
            $customer = $stmt->fetch();
            
            if (!$customer) {
                Response::error('Customer not found', 404);
            }
            
            // Get recent deliveries
            $deliveriesStmt = $db->prepare("
                SELECT dr_number, status, created_at, total_amount
                FROM delivery_receipts
                WHERE customer_id = ?
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $deliveriesStmt->execute([$id]);
            $customer['recent_deliveries'] = $deliveriesStmt->fetchAll();
            
            Response::success($customer, 'Customer details retrieved');
            break;
            
        case 'search':
            $query = getParam('q');
            if (!$query || strlen($query) < 2) {
                Response::success([], 'Search query too short');
                break;
            }
            
            $stmt = $db->prepare("
                SELECT id, name, customer_type, address, phone
                FROM customers
                WHERE status = 'active'
                AND (name LIKE ? OR contact_person LIKE ?)
                ORDER BY name ASC
                LIMIT 20
            ");
            $searchTerm = "%$query%";
            $stmt->execute([$searchTerm, $searchTerm]);
            $results = $stmt->fetchAll();
            
            Response::success($results, 'Search results');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

function handlePost($db, $action, $currentUser) {
    $data = getRequestBody();
    
    if ($action === 'create') {
        $required = ['name', 'customer_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                Response::error("$field is required", 400);
            }
        }
        
        // Check for duplicate
        $check = $db->prepare("SELECT id FROM customers WHERE name = ? AND customer_type = ?");
        $check->execute([$data['name'], $data['customer_type']]);
        if ($check->fetch()) {
            Response::error('A customer with this name and type already exists', 400);
        }
        
        $stmt = $db->prepare("
            INSERT INTO customers 
            (name, customer_type, contact_person, phone, email, address, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['customer_type'],
            $data['contact_person'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            $data['status'] ?? 'active',
            $currentUser['id']
        ]);
        
        $customerId = $db->lastInsertId();
        
        logAudit($currentUser['id'], 'CREATE', 'customers', $customerId, null, $data);
        
        Response::success(['id' => $customerId], 'Customer created', 201);
    }
    
    Response::error('Invalid action', 400);
}

function handlePut($db, $action, $currentUser) {
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
        Response::error('Customer not found', 404);
    }
    
    if ($action === 'update') {
        $stmt = $db->prepare("
            UPDATE customers SET
                name = COALESCE(?, name),
                customer_type = COALESCE(?, customer_type),
                contact_person = COALESCE(?, contact_person),
                phone = COALESCE(?, phone),
                email = COALESCE(?, email),
                address = COALESCE(?, address),
                status = COALESCE(?, status),
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['name'] ?? null,
            $data['customer_type'] ?? null,
            $data['contact_person'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            $data['status'] ?? null,
            $id
        ]);
        
        logAudit($currentUser['id'], 'UPDATE', 'customers', $id, $current, $data);
        
        Response::success(null, 'Customer updated');
    }
    
    Response::error('Invalid action', 400);
}
