<?php
/**
 * Highland Fresh System - Suppliers API
 * 
 * GET - List suppliers, get details, search
 * POST - Create supplier
 * PUT - Update supplier, toggle status
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Purchaser or GM role
$currentUser = Auth::requireRole(['purchaser', 'general_manager']);

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
    error_log("Suppliers API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            $status = getParam('status');
            $search = getParam('search');
            
            $sql = "SELECT * FROM suppliers WHERE 1=1";
            $params = [];
            
            if ($status === 'active') {
                $sql .= " AND is_active = 1";
            } elseif ($status === 'inactive') {
                $sql .= " AND is_active = 0";
            }
            
            if ($search) {
                $sql .= " AND (supplier_name LIKE ? OR supplier_code LIKE ? OR contact_person LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY supplier_name ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $suppliers = $stmt->fetchAll();
            
            Response::success($suppliers, 'Suppliers retrieved');
            break;
            
        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('Supplier ID required', 400);
            }
            
            $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            $supplier = $stmt->fetch();
            
            if (!$supplier) {
                Response::error('Supplier not found', 404);
            }
            
            // Get recent POs for this supplier
            $posStmt = $db->prepare("
                SELECT 
                    po_number, order_date, status, total_amount, payment_status
                FROM purchase_orders
                WHERE supplier_id = ?
                ORDER BY order_date DESC
                LIMIT 10
            ");
            $posStmt->execute([$id]);
            $supplier['recent_orders'] = $posStmt->fetchAll();
            
            // Get total business stats
            $statsStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(total_amount), 0) as total_business,
                    COALESCE(AVG(total_amount), 0) as avg_order_value,
                    MAX(order_date) as last_order_date
                FROM purchase_orders
                WHERE supplier_id = ?
                AND status != 'cancelled'
            ");
            $statsStmt->execute([$id]);
            $supplier['business_stats'] = $statsStmt->fetch();
            
            Response::success($supplier, 'Supplier details retrieved');
            break;
            
        case 'search':
            $query = getParam('q');
            if (!$query || strlen($query) < 2) {
                Response::success([], 'Search query too short');
                break;
            }
            
            $stmt = $db->prepare("
                SELECT id, supplier_code, supplier_name, contact_person, phone, payment_terms
                FROM suppliers
                WHERE is_active = 1
                AND (supplier_name LIKE ? OR supplier_code LIKE ? OR contact_person LIKE ?)
                ORDER BY supplier_name ASC
                LIMIT 20
            ");
            $searchTerm = "%$query%";
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
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
        $required = ['supplier_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                Response::error("$field is required", 400);
            }
        }
        
        // Generate supplier code
        $stmt = $db->query("SELECT COUNT(*) as count FROM suppliers");
        $count = (int) $stmt->fetch()['count'];
        $supplierCode = 'SUP-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        
        // Check for duplicate name
        $check = $db->prepare("SELECT id FROM suppliers WHERE supplier_name = ?");
        $check->execute([$data['supplier_name']]);
        if ($check->fetch()) {
            Response::error('A supplier with this name already exists', 400);
        }
        
        $stmt = $db->prepare("
            INSERT INTO suppliers 
            (supplier_code, supplier_name, contact_person, phone, email, address, payment_terms, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $supplierCode,
            $data['supplier_name'],
            $data['contact_person'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            $data['payment_terms'] ?? '30 days',
            $data['notes'] ?? null
        ]);
        
        $supplierId = $db->lastInsertId();
        
        logAudit($currentUser['user_id'], 'CREATE', 'suppliers', $supplierId, null, $data);
        
        Response::success(['id' => $supplierId, 'supplier_code' => $supplierCode], 'Supplier created', 201);
    }
    
    Response::error('Invalid action', 400);
}

function handlePut($db, $action, $currentUser) {
    $data = getRequestBody();
    $id = getParam('id') ?? ($data['id'] ?? null);
    
    if (!$id) {
        Response::error('Supplier ID required', 400);
    }
    
    // Get current supplier
    $check = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
    $check->execute([$id]);
    $current = $check->fetch();
    
    if (!$current) {
        Response::error('Supplier not found', 404);
    }
    
    switch ($action) {
        case 'update':
            $stmt = $db->prepare("
                UPDATE suppliers SET
                    supplier_name = COALESCE(?, supplier_name),
                    contact_person = COALESCE(?, contact_person),
                    phone = COALESCE(?, phone),
                    email = COALESCE(?, email),
                    address = COALESCE(?, address),
                    payment_terms = COALESCE(?, payment_terms),
                    notes = COALESCE(?, notes),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['supplier_name'] ?? null,
                $data['contact_person'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $data['address'] ?? null,
                $data['payment_terms'] ?? null,
                $data['notes'] ?? null,
                $id
            ]);
            
            logAudit($currentUser['user_id'], 'UPDATE', 'suppliers', $id, $current, $data);
            
            Response::success(null, 'Supplier updated');
            break;
            
        case 'toggle_status':
            $newStatus = $current['is_active'] ? 0 : 1;
            $stmt = $db->prepare("UPDATE suppliers SET is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $id]);
            
            logAudit($currentUser['user_id'], 'UPDATE', 'suppliers', $id, 
                ['is_active' => $current['is_active']], 
                ['is_active' => $newStatus]
            );
            
            Response::success(
                ['is_active' => $newStatus], 
                $newStatus ? 'Supplier activated' : 'Supplier deactivated'
            );
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
