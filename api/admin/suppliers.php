<?php
/**
 * Admin Suppliers API
 * CRUD operations for suppliers table
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
                getSupplier($conn, $id);
            } elseif ($action === 'statistics') {
                getSupplierStatistics($conn);
            } else {
                getSuppliers($conn);
            }
            break;
        case 'POST':
            createSupplier($conn);
            break;
        case 'PUT':
            if ($id) {
                updateSupplier($conn, $id);
            } else {
                sendError('Supplier ID required', 400);
            }
            break;
        case 'DELETE':
            if ($id) {
                deleteSupplier($conn, $id);
            } else {
                sendError('Supplier ID required', 400);
            }
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

/**
 * Get all suppliers with pagination and filters
 */
function getSuppliers($conn) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $isActive = isset($_GET['is_active']) ? $_GET['is_active'] : '';
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(supplier_name LIKE ? OR supplier_code LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($isActive !== '') {
        $where[] = "is_active = ?";
        $params[] = intval($isActive);
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM suppliers $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get suppliers
    $sql = "SELECT * FROM suppliers $whereClause ORDER BY supplier_name ASC LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess([
        'suppliers' => $suppliers,
        'pagination' => [
            'total' => intval($total),
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get single supplier
 */
function getSupplier($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$supplier) {
        sendError('Supplier not found', 404);
    }
    
    sendSuccess(['supplier' => $supplier]);
}

/**
 * Get supplier statistics
 */
function getSupplierStatistics($conn) {
    $stats = [];
    
    // Total suppliers
    $stmt = $conn->query("SELECT COUNT(*) as count FROM suppliers");
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Active suppliers
    $stmt = $conn->query("SELECT COUNT(*) as count FROM suppliers WHERE is_active = 1");
    $stats['active'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Inactive suppliers
    $stats['inactive'] = $stats['total'] - $stats['active'];
    
    sendSuccess($stats);
}

/**
 * Create new supplier
 */
function createSupplier($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validation
    $errors = [];
    if (empty($data['supplier_name'])) {
        $errors['supplier_name'] = 'Supplier name is required';
    }
    
    if (!empty($errors)) {
        sendValidationError($errors);
    }
    
    // Generate supplier code if not provided
    if (empty($data['supplier_code'])) {
        $stmt = $conn->query("SELECT MAX(id) as max_id FROM suppliers");
        $maxId = $stmt->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
        $data['supplier_code'] = 'SUP-' . str_pad($maxId + 1, 4, '0', STR_PAD_LEFT);
    }
    
    // Check if supplier code already exists
    $stmt = $conn->prepare("SELECT id FROM suppliers WHERE supplier_code = ?");
    $stmt->execute([$data['supplier_code']]);
    if ($stmt->fetch()) {
        sendValidationError(['supplier_code' => 'Supplier code already exists']);
    }
    
    $sql = "INSERT INTO suppliers (supplier_code, supplier_name, contact_person, phone, email, address, payment_terms, is_active, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $data['supplier_code'],
        $data['supplier_name'],
        $data['contact_person'] ?? null,
        $data['phone'] ?? null,
        $data['email'] ?? null,
        $data['address'] ?? null,
        $data['payment_terms'] ?? '30 days',
        isset($data['is_active']) ? intval($data['is_active']) : 1,
        $data['notes'] ?? null
    ]);
    
    $newId = $conn->lastInsertId();
    
    // Get the created supplier
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$newId]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    sendSuccess(['supplier' => $supplier], 'Supplier created successfully');
}

/**
 * Update supplier
 */
function updateSupplier($conn, $id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if supplier exists
    $stmt = $conn->prepare("SELECT id FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendError('Supplier not found', 404);
    }
    
    // Build update query
    $fields = [];
    $params = [];
    
    $allowedFields = ['supplier_name', 'contact_person', 'phone', 'email', 'address', 'payment_terms', 'is_active', 'notes'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $params[] = $field === 'is_active' ? intval($data[$field]) : $data[$field];
        }
    }
    
    if (empty($fields)) {
        sendError('No fields to update', 400);
    }
    
    $params[] = $id;
    $sql = "UPDATE suppliers SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    // Get updated supplier
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    sendSuccess(['supplier' => $supplier], 'Supplier updated successfully');
}

/**
 * Delete supplier
 */
function deleteSupplier($conn, $id) {
    // Check if supplier exists
    $stmt = $conn->prepare("SELECT id FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendError('Supplier not found', 404);
    }
    
    // Soft delete (set is_active to 0)
    $stmt = $conn->prepare("UPDATE suppliers SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    
    sendSuccess(null, 'Supplier deactivated successfully');
}
