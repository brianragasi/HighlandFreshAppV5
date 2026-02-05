<?php
/**
 * Admin Ingredients API
 * CRUD operations for ingredients table
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
                getIngredient($conn, $id);
            } elseif ($action === 'statistics') {
                getIngredientStatistics($conn);
            } elseif ($action === 'categories') {
                getCategories($conn);
            } elseif ($action === 'low-stock') {
                getLowStockIngredients($conn);
            } else {
                getIngredients($conn);
            }
            break;
        case 'POST':
            createIngredient($conn);
            break;
        case 'PUT':
            if ($id) {
                updateIngredient($conn, $id);
            } else {
                sendError('Ingredient ID required', 400);
            }
            break;
        case 'DELETE':
            if ($id) {
                deleteIngredient($conn, $id);
            } else {
                sendError('Ingredient ID required', 400);
            }
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

/**
 * Get all ingredients with pagination and filters
 */
function getIngredients($conn) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;
    $isActive = isset($_GET['is_active']) ? $_GET['is_active'] : '';
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(i.ingredient_name LIKE ? OR i.ingredient_code LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($categoryId) {
        $where[] = "i.category_id = ?";
        $params[] = $categoryId;
    }
    
    if ($isActive !== '') {
        $where[] = "i.is_active = ?";
        $params[] = intval($isActive);
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM ingredients i $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get ingredients with category
    $sql = "SELECT i.*, c.category_name
            FROM ingredients i 
            LEFT JOIN ingredient_categories c ON i.category_id = c.id
            $whereClause 
            ORDER BY i.ingredient_name ASC 
            LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess([
        'ingredients' => $ingredients,
        'pagination' => [
            'total' => intval($total),
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get single ingredient
 */
function getIngredient($conn, $id) {
    $stmt = $conn->prepare("
        SELECT i.*, c.category_name
        FROM ingredients i 
        LEFT JOIN ingredient_categories c ON i.category_id = c.id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ingredient) {
        sendError('Ingredient not found', 404);
    }
    
    sendSuccess(['ingredient' => $ingredient]);
}

/**
 * Get ingredient categories
 */
function getCategories($conn) {
    $stmt = $conn->query("SELECT * FROM ingredient_categories ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendSuccess(['categories' => $categories]);
}

/**
 * Get low stock ingredients
 */
function getLowStockIngredients($conn) {
    $stmt = $conn->query("
        SELECT i.*, c.category_name
        FROM ingredients i 
        LEFT JOIN ingredient_categories c ON i.category_id = c.id
        WHERE i.current_stock <= i.reorder_point AND i.is_active = 1
        ORDER BY (i.current_stock / NULLIF(i.reorder_point, 0)) ASC
    ");
    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendSuccess(['ingredients' => $ingredients]);
}

/**
 * Get ingredient statistics
 */
function getIngredientStatistics($conn) {
    $stats = [];
    
    // Total ingredients
    $stmt = $conn->query("SELECT COUNT(*) as count FROM ingredients");
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Active ingredients
    $stmt = $conn->query("SELECT COUNT(*) as count FROM ingredients WHERE is_active = 1");
    $stats['active'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Low stock count
    $stmt = $conn->query("SELECT COUNT(*) as count FROM ingredients WHERE current_stock <= reorder_point AND is_active = 1");
    $stats['low_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // By category
    $stmt = $conn->query("
        SELECT c.category_name, COUNT(*) as count 
        FROM ingredients i 
        LEFT JOIN ingredient_categories c ON i.category_id = c.id 
        GROUP BY c.category_name
    ");
    $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total inventory value
    $stmt = $conn->query("SELECT SUM(current_stock * unit_cost) as total_value FROM ingredients WHERE is_active = 1");
    $stats['total_value'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;
    
    sendSuccess($stats);
}

/**
 * Create new ingredient
 */
function createIngredient($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validation
    $errors = [];
    if (empty($data['ingredient_name'])) {
        $errors['ingredient_name'] = 'Ingredient name is required';
    }
    if (empty($data['unit_of_measure'])) {
        $errors['unit_of_measure'] = 'Unit of measure is required';
    }
    
    if (!empty($errors)) {
        sendValidationError($errors);
    }
    
    // Generate ingredient code if not provided
    if (empty($data['ingredient_code'])) {
        $stmt = $conn->query("SELECT MAX(id) as max_id FROM ingredients");
        $maxId = $stmt->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
        $data['ingredient_code'] = 'ING-' . str_pad($maxId + 1, 4, '0', STR_PAD_LEFT);
    }
    
    // Check if ingredient code already exists
    $stmt = $conn->prepare("SELECT id FROM ingredients WHERE ingredient_code = ?");
    $stmt->execute([$data['ingredient_code']]);
    if ($stmt->fetch()) {
        sendValidationError(['ingredient_code' => 'Ingredient code already exists']);
    }
    
    $sql = "INSERT INTO ingredients (ingredient_code, ingredient_name, category_id, unit_of_measure,
            minimum_stock, reorder_point, lead_time_days, current_stock, unit_cost, 
            storage_location, storage_requirements, shelf_life_days, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $data['ingredient_code'],
        $data['ingredient_name'],
        $data['category_id'] ?? null,
        $data['unit_of_measure'],
        $data['minimum_stock'] ?? 0,
        $data['reorder_point'] ?? 0,
        $data['lead_time_days'] ?? 7,
        $data['current_stock'] ?? 0,
        $data['unit_cost'] ?? null,
        $data['storage_location'] ?? null,
        $data['storage_requirements'] ?? null,
        $data['shelf_life_days'] ?? null,
        isset($data['is_active']) ? intval($data['is_active']) : 1
    ]);
    
    $newId = $conn->lastInsertId();
    
    // Get the created ingredient
    $stmt = $conn->prepare("SELECT i.*, c.category_name FROM ingredients i LEFT JOIN ingredient_categories c ON i.category_id = c.id WHERE i.id = ?");
    $stmt->execute([$newId]);
    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    sendSuccess(['ingredient' => $ingredient], 'Ingredient created successfully');
}

/**
 * Update ingredient
 */
function updateIngredient($conn, $id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if ingredient exists
    $stmt = $conn->prepare("SELECT id FROM ingredients WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendError('Ingredient not found', 404);
    }
    
    // Build update query
    $fields = [];
    $params = [];
    
    $allowedFields = ['ingredient_name', 'category_id', 'unit_of_measure', 'minimum_stock',
                      'reorder_point', 'lead_time_days', 'current_stock', 'unit_cost',
                      'storage_location', 'storage_requirements', 'shelf_life_days', 'is_active'];
    
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
    $sql = "UPDATE ingredients SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    // Get updated ingredient
    $stmt = $conn->prepare("SELECT i.*, c.category_name FROM ingredients i LEFT JOIN ingredient_categories c ON i.category_id = c.id WHERE i.id = ?");
    $stmt->execute([$id]);
    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    sendSuccess(['ingredient' => $ingredient], 'Ingredient updated successfully');
}

/**
 * Delete ingredient
 */
function deleteIngredient($conn, $id) {
    // Check if ingredient exists
    $stmt = $conn->prepare("SELECT id FROM ingredients WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendError('Ingredient not found', 404);
    }
    
    // Soft delete (set is_active to 0)
    $stmt = $conn->prepare("UPDATE ingredients SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    
    sendSuccess(null, 'Ingredient deactivated successfully');
}
