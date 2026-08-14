<?php
/**
 * Admin Suppliers API
 * CRUD operations for suppliers table
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/supplier_ingredient_catalog.php';

// Require GM/Admin role
$currentUser = Auth::requireRole(['general_manager', 'admin']);

// Get database connection
$conn = Database::getInstance()->getConnection();
ensureSupplierIngredientCatalog($conn);

// Get request method and handle routing
$method = $requestMethod;
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
            createSupplier($conn, $currentUser);
            break;
        case 'PUT':
            if ($id) {
                updateSupplier($conn, $id, $currentUser);
            } else {
                sendError('Supplier ID required', 400);
            }
            break;
        case 'DELETE':
            if ($id) {
                deleteSupplier($conn, $id, $currentUser);
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
        $where[] = "(s.supplier_name LIKE ? OR s.supplier_code LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($isActive !== '') {
        $where[] = "s.is_active = ?";
        $params[] = intval($isActive);
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM suppliers s $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get suppliers
    $sql = "SELECT s.*,
                   COUNT(DISTINCT CASE WHEN si.is_active = 1 AND i.is_active = 1 THEN si.ingredient_id END) AS ingredient_count,
                   GROUP_CONCAT(DISTINCT CASE WHEN si.is_active = 1 AND i.is_active = 1 THEN i.ingredient_name END ORDER BY i.ingredient_name SEPARATOR ', ') AS supplied_ingredients
            FROM suppliers s
            LEFT JOIN supplier_ingredients si ON si.supplier_id = s.id
            LEFT JOIN ingredients i ON i.id = si.ingredient_id
            $whereClause
            GROUP BY s.id
            ORDER BY s.supplier_name ASC
            LIMIT $limit OFFSET $offset";
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

    $supplier['ingredients'] = supplierCatalogGetSupplierIngredients($conn, (int) $id);
    
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
function createSupplier($conn, $currentUser) {
    $data = json_decode(file_get_contents('php://input'), true);
    $isActive = isset($data['is_active']) ? intval($data['is_active']) : 1;
    $ingredientLinks = supplierCatalogNormalizeIngredientLinks($data['ingredients'] ?? []);
    
    // Validation
    $errors = [];
    if (empty($data['supplier_name'])) {
        $errors['supplier_name'] = 'Supplier name is required';
    }
    $contactCheck = hfValidateContactPayload($data, ['phone'], 'email');
    $data = $contactCheck['data'];
    $errors = array_merge($errors, $contactCheck['errors']);
    
    if (!empty($errors)) {
        sendValidationError($errors);
    }
    // A supplier can be accredited before its first ingredient is registered.
    // Purchasing only sees this supplier after an ingredient link is added.
    supplierCatalogValidateIngredientLinks($conn, $ingredientLinks, false);
    
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

    $stmt = $conn->prepare("SELECT id FROM suppliers WHERE LOWER(TRIM(supplier_name)) = LOWER(TRIM(?))");
    $stmt->execute([$data['supplier_name']]);
    if ($stmt->fetch()) {
        sendValidationError(['supplier_name' => 'A supplier with this name already exists']);
    }
    
    $sql = "INSERT INTO suppliers (supplier_code, supplier_name, contact_person, phone, email, address, payment_terms, is_active, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $data['supplier_code'],
            $data['supplier_name'],
            $data['contact_person'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            $data['payment_terms'] ?? '30 days',
            $isActive,
            $data['notes'] ?? null
        ]);

        $newId = (int) $conn->lastInsertId();
        supplierCatalogSyncSupplier($conn, $newId, $ingredientLinks, (int) $currentUser['user_id']);
        $conn->commit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
    
    // Get the created supplier
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$newId]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    $supplier['ingredients'] = supplierCatalogGetSupplierIngredients($conn, $newId);

    logAudit($currentUser['user_id'], 'CREATE', 'suppliers', $newId, null, $supplier);
    
    sendSuccess(['supplier' => $supplier], 'Supplier created successfully');
}

/**
 * Update supplier
 */
function updateSupplier($conn, $id, $currentUser) {
    $data = json_decode(file_get_contents('php://input'), true);
    $hasIngredientLinks = array_key_exists('ingredients', $data);
    $contactCheck = hfValidateContactPayload($data, ['phone'], 'email');
    if (!empty($contactCheck['errors'])) {
        sendValidationError($contactCheck['errors']);
    }
    $data = $contactCheck['data'];
    
    // Check if supplier exists
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);
    $currentSupplier = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$currentSupplier) {
        sendError('Supplier not found', 404);
    }

    $ingredientLinks = $hasIngredientLinks
        ? supplierCatalogNormalizeIngredientLinks($data['ingredients'])
        : supplierCatalogNormalizeIngredientLinks(supplierCatalogGetSupplierIngredients($conn, (int) $id));
    $nextIsActive = isset($data['is_active']) ? intval($data['is_active']) : intval($currentSupplier['is_active']);
    supplierCatalogValidateIngredientLinks($conn, $ingredientLinks, false);
    supplierCatalogValidateSupplierCoverageAfterChange(
        $conn,
        (int) $id,
        $ingredientLinks,
        $nextIsActive === 1
    );

    if (isset($data['supplier_name'])) {
        $stmt = $conn->prepare("SELECT id FROM suppliers WHERE LOWER(TRIM(supplier_name)) = LOWER(TRIM(?)) AND id != ?");
        $stmt->execute([$data['supplier_name'], $id]);
        if ($stmt->fetch()) {
            sendValidationError(['supplier_name' => 'A supplier with this name already exists']);
        }
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
    
    if (empty($fields) && !$hasIngredientLinks) {
        sendError('No fields to update', 400);
    }

    $conn->beginTransaction();
    try {
        if ($fields) {
            $params[] = $id;
            $sql = "UPDATE suppliers SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }
        if ($hasIngredientLinks) {
            supplierCatalogSyncSupplier($conn, (int) $id, $ingredientLinks, (int) $currentUser['user_id']);
        }
        $conn->commit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
    
    // Get updated supplier
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    $supplier['ingredients'] = supplierCatalogGetSupplierIngredients($conn, (int) $id);

    logAudit($currentUser['user_id'], 'UPDATE', 'suppliers', $id, $currentSupplier, $supplier);
    
    sendSuccess(['supplier' => $supplier], 'Supplier updated successfully');
}

/**
 * Archive supplier by deactivating the row.
 * Supplier rows are kept so PRS, canvass, PO, and receiving history remain traceable.
 */
function deleteSupplier($conn, $id, $currentUser) {
    // Check if supplier exists
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);
    $currentSupplier = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$currentSupplier) {
        sendError('Supplier not found', 404);
    }

    $currentLinks = supplierCatalogNormalizeIngredientLinks(
        supplierCatalogGetSupplierIngredients($conn, (int) $id)
    );
    supplierCatalogValidateSupplierCoverageAfterChange($conn, (int) $id, $currentLinks, false);
    
    $stmt = $conn->prepare("UPDATE suppliers SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);

    logAudit(
        $currentUser['user_id'],
        'UPDATE',
        'suppliers',
        $id,
        $currentSupplier,
        array_merge($currentSupplier, ['is_active' => 0])
    );
    
    sendSuccess([
        'supplier_id' => (int) $id,
        'is_active' => 0,
        'archived' => true
    ], 'Supplier archived successfully');
}
