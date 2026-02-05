<?php
/**
 * Admin Recipes API
 * CRUD operations for master_recipes table
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
                getRecipe($conn, $id);
            } elseif ($action === 'statistics') {
                getRecipeStatistics($conn);
            } else {
                getRecipes($conn);
            }
            break;
        case 'POST':
            createRecipe($conn);
            break;
        case 'PUT':
            if ($id) {
                updateRecipe($conn, $id);
            } else {
                sendError('Recipe ID required', 400);
            }
            break;
        case 'DELETE':
            if ($id) {
                deleteRecipe($conn, $id);
            } else {
                sendError('Recipe ID required', 400);
            }
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

/**
 * Get all recipes with pagination and filters
 */
function getRecipes($conn) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $productType = isset($_GET['product_type']) ? $_GET['product_type'] : '';
    $isActive = isset($_GET['is_active']) ? $_GET['is_active'] : '';
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(r.product_name LIKE ? OR r.recipe_code LIKE ? OR r.variant LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($productType) {
        $where[] = "r.product_type = ?";
        $params[] = $productType;
    }
    
    if ($isActive !== '') {
        $where[] = "r.is_active = ?";
        $params[] = intval($isActive);
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM master_recipes r $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get recipes with milk type
    $sql = "SELECT r.*, mt.type_name as milk_type_name,
                   u.full_name as created_by_name
            FROM master_recipes r 
            LEFT JOIN milk_types mt ON r.milk_type_id = mt.id
            LEFT JOIN users u ON r.created_by = u.id
            $whereClause 
            ORDER BY r.product_name ASC 
            LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get ingredients for each recipe
    foreach ($recipes as &$recipe) {
        $stmt = $conn->prepare("
            SELECT ri.id, ri.recipe_id, ri.ingredient_id, ri.ingredient_name,
                   ri.ingredient_category, ri.quantity, ri.unit, ri.notes
            FROM recipe_ingredients ri
            WHERE ri.recipe_id = ?
        ");
        $stmt->execute([$recipe['id']]);
        $recipe['ingredients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    sendSuccess([
        'recipes' => $recipes,
        'pagination' => [
            'total' => intval($total),
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get single recipe with ingredients
 */
function getRecipe($conn, $id) {
    $stmt = $conn->prepare("
        SELECT r.*, mt.type_name as milk_type_name,
               u.full_name as created_by_name
        FROM master_recipes r 
        LEFT JOIN milk_types mt ON r.milk_type_id = mt.id
        LEFT JOIN users u ON r.created_by = u.id
        WHERE r.id = ?
    ");
    $stmt->execute([$id]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recipe) {
        sendError('Recipe not found', 404);
    }
    
    // Get ingredients
    $stmt = $conn->prepare("
        SELECT ri.id, ri.recipe_id, ri.ingredient_id, ri.ingredient_name,
               ri.ingredient_category, ri.quantity, ri.unit, ri.notes
        FROM recipe_ingredients ri
        WHERE ri.recipe_id = ?
    ");
    $stmt->execute([$id]);
    $recipe['ingredients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess(['recipe' => $recipe]);
}

/**
 * Get recipe statistics
 */
function getRecipeStatistics($conn) {
    $stats = [];
    
    // Total recipes
    $stmt = $conn->query("SELECT COUNT(*) as count FROM master_recipes");
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Active recipes
    $stmt = $conn->query("SELECT COUNT(*) as count FROM master_recipes WHERE is_active = 1");
    $stats['active'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // By product type
    $stmt = $conn->query("SELECT product_type, COUNT(*) as count FROM master_recipes GROUP BY product_type");
    $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess($stats);
}

/**
 * Create new recipe
 */
function createRecipe($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $user = getAuthUser();
    
    // Validation
    $errors = [];
    if (empty($data['product_name'])) {
        $errors['product_name'] = 'Product name is required';
    }
    if (empty($data['product_type'])) {
        $errors['product_type'] = 'Product type is required';
    }
    if (empty($data['milk_type_id'])) {
        $errors['milk_type_id'] = 'Milk type is required';
    }
    if (empty($data['base_milk_liters'])) {
        $errors['base_milk_liters'] = 'Base milk liters is required';
    }
    if (empty($data['expected_yield'])) {
        $errors['expected_yield'] = 'Expected yield is required';
    }
    
    if (!empty($errors)) {
        sendValidationError($errors);
    }
    
    // Generate recipe code if not provided
    if (empty($data['recipe_code'])) {
        $stmt = $conn->query("SELECT MAX(id) as max_id FROM master_recipes");
        $maxId = $stmt->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
        $data['recipe_code'] = 'RCP-' . str_pad($maxId + 1, 4, '0', STR_PAD_LEFT);
    }
    
    $conn->beginTransaction();
    
    try {
        $sql = "INSERT INTO master_recipes (recipe_code, product_id, product_name, product_type, variant, 
                milk_type_id, description, base_milk_liters, expected_yield, yield_unit, shelf_life_days,
                pasteurization_temp, pasteurization_time_mins, cooling_temp, special_instructions, 
                is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $data['recipe_code'],
            $data['product_id'] ?? null,
            $data['product_name'],
            $data['product_type'],
            $data['variant'] ?? null,
            $data['milk_type_id'],
            $data['description'] ?? null,
            $data['base_milk_liters'],
            $data['expected_yield'],
            $data['yield_unit'] ?? 'units',
            $data['shelf_life_days'] ?? 7,
            $data['pasteurization_temp'] ?? 81.00,
            $data['pasteurization_time_mins'] ?? 15,
            $data['cooling_temp'] ?? 4.00,
            $data['special_instructions'] ?? null,
            isset($data['is_active']) ? intval($data['is_active']) : 1,
            $user['id']
        ]);
        
        $recipeId = $conn->lastInsertId();
        
        // Insert ingredients if provided
        if (!empty($data['ingredients']) && is_array($data['ingredients'])) {
            $ingredientSql = "INSERT INTO recipe_ingredients (recipe_id, ingredient_id, ingredient_name, ingredient_category, quantity, unit, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $ingredientStmt = $conn->prepare($ingredientSql);
            
            foreach ($data['ingredients'] as $ingredient) {
                $ingredientStmt->execute([
                    $recipeId,
                    $ingredient['ingredient_id'],
                    $ingredient['ingredient_name'] ?? '',
                    $ingredient['ingredient_category'] ?? '',
                    $ingredient['quantity'] ?? $ingredient['quantity_required'] ?? 0,
                    $ingredient['unit'] ?? $ingredient['unit_of_measure'] ?? 'units',
                    $ingredient['notes'] ?? ''
                ]);
            }
        }
        
        $conn->commit();
        
        // Get the created recipe
        $stmt = $conn->prepare("SELECT * FROM master_recipes WHERE id = ?");
        $stmt->execute([$recipeId]);
        $recipe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        sendSuccess(['recipe' => $recipe], 'Recipe created successfully');
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

/**
 * Update recipe
 */
function updateRecipe($conn, $id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if recipe exists
    $stmt = $conn->prepare("SELECT id FROM master_recipes WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendError('Recipe not found', 404);
    }
    
    // Build update query
    $fields = [];
    $params = [];
    
    $allowedFields = ['product_name', 'product_type', 'variant', 'milk_type_id', 'description',
                      'base_milk_liters', 'expected_yield', 'yield_unit', 'shelf_life_days',
                      'pasteurization_temp', 'pasteurization_time_mins', 'cooling_temp',
                      'special_instructions', 'is_active'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $params[] = $field === 'is_active' ? intval($data[$field]) : $data[$field];
        }
    }
    
    if (empty($fields)) {
        sendError('No fields to update', 400);
    }
    
    $conn->beginTransaction();
    
    try {
        $params[] = $id;
        $sql = "UPDATE master_recipes SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        // Update ingredients if provided
        if (isset($data['ingredients']) && is_array($data['ingredients'])) {
            // Delete existing ingredients
            $stmt = $conn->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?");
            $stmt->execute([$id]);
            
            // Insert new ingredients
            if (!empty($data['ingredients'])) {
                $ingredientSql = "INSERT INTO recipe_ingredients (recipe_id, ingredient_id, ingredient_name, ingredient_category, quantity, unit, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $ingredientStmt = $conn->prepare($ingredientSql);
                
                foreach ($data['ingredients'] as $ingredient) {
                    $ingredientStmt->execute([
                        $id,
                        $ingredient['ingredient_id'],
                        $ingredient['ingredient_name'] ?? '',
                        $ingredient['ingredient_category'] ?? '',
                        $ingredient['quantity'] ?? $ingredient['quantity_required'] ?? 0,
                        $ingredient['unit'] ?? $ingredient['unit_of_measure'] ?? 'units',
                        $ingredient['notes'] ?? ''
                    ]);
                }
            }
        }
        
        $conn->commit();
        
        // Get updated recipe
        $stmt = $conn->prepare("SELECT * FROM master_recipes WHERE id = ?");
        $stmt->execute([$id]);
        $recipe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        sendSuccess(['recipe' => $recipe], 'Recipe updated successfully');
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

/**
 * Delete recipe
 */
function deleteRecipe($conn, $id) {
    // Check if recipe exists
    $stmt = $conn->prepare("SELECT id FROM master_recipes WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendError('Recipe not found', 404);
    }
    
    // Soft delete (set is_active to 0)
    $stmt = $conn->prepare("UPDATE master_recipes SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    
    sendSuccess(null, 'Recipe deactivated successfully');
}
