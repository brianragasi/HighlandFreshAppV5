<?php
/**
 * Admin Products API
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
                getProduct($conn, $id);
            } elseif ($action === 'statistics') {
                getProductStatistics($conn);
            } elseif ($action === 'export') {
                exportProducts($conn);
            } elseif ($action === 'categories') {
                getCategories($conn);
            } elseif ($action === 'milk-types') {
                getMilkTypes($conn);
            } else {
                getProducts($conn);
            }
            break;
        case 'POST':
            createProduct($conn);
            break;
        case 'PUT':
            if ($id) {
                updateProduct($conn, $id);
            } else {
                sendError('Product ID required', 400);
            }
            break;
        case 'DELETE':
            if ($id) {
                deleteProduct($conn, $id);
            } else {
                sendError('Product ID required', 400);
            }
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

/**
 * Get all products with pagination and filters
 */
function getProducts($conn) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $milkTypeId = isset($_GET['milk_type_id']) ? intval($_GET['milk_type_id']) : null;
    $isActive = isset($_GET['is_active']) ? $_GET['is_active'] : '';
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(p.product_name LIKE ? OR p.product_code LIKE ? OR p.variant LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($category) {
        $where[] = "p.category = ?";
        $params[] = $category;
    }
    
    if ($milkTypeId) {
        $where[] = "p.milk_type_id = ?";
        $params[] = $milkTypeId;
    }
    
    if ($isActive !== '') {
        $where[] = "p.is_active = ?";
        $params[] = intval($isActive);
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM products p $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get products with milk type info
    $sql = "SELECT 
                p.id,
                p.product_code,
                p.product_name,
                p.category,
                p.variant,
                p.milk_type_id,
                mt.type_code as milk_type_code,
                mt.type_name as milk_type_name,
                p.description,
                p.unit_size,
                p.unit_measure,
                p.shelf_life_days,
                p.storage_temp_min,
                p.storage_temp_max,
                p.base_unit,
                p.box_unit,
                p.pieces_per_box,
                p.is_active,
                p.created_at,
                p.updated_at,
                (SELECT COUNT(*) FROM master_recipes mr WHERE mr.product_id = p.id) as recipe_count,
                (SELECT COALESCE(SUM(fgi.remaining_quantity), 0) 
                 FROM finished_goods_inventory fgi WHERE fgi.product_id = p.id AND fgi.status = 'available') as current_stock
            FROM products p
            LEFT JOIN milk_types mt ON p.milk_type_id = mt.id
            $whereClause
            ORDER BY p.product_name ASC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess([
        'products' => $products,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => intval($total),
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get single product by ID
 */
function getProduct($conn, $id) {
    $sql = "SELECT 
                p.id,
                p.product_code,
                p.product_name,
                p.category,
                p.variant,
                p.milk_type_id,
                mt.type_code as milk_type_code,
                mt.type_name as milk_type_name,
                p.description,
                p.unit_size,
                p.unit_measure,
                p.shelf_life_days,
                p.storage_temp_min,
                p.storage_temp_max,
                p.base_unit,
                p.box_unit,
                p.pieces_per_box,
                p.is_active,
                p.created_at,
                p.updated_at
            FROM products p
            LEFT JOIN milk_types mt ON p.milk_type_id = mt.id
            WHERE p.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        sendError('Product not found', 404);
        return;
    }
    
    // Get associated recipes (master_recipes)
    $recipesSql = "SELECT 
                    mr.id,
                    mr.recipe_code,
                    mr.product_name,
                    mr.product_type,
                    mr.variant,
                    mr.base_milk_liters,
                    mr.expected_yield,
                    mr.yield_unit,
                    mr.shelf_life_days,
                    mr.is_active
                   FROM master_recipes mr
                   WHERE mr.product_id = ?
                   ORDER BY mr.recipe_code";
    
    $recipesStmt = $conn->prepare($recipesSql);
    $recipesStmt->execute([$id]);
    $product['recipes'] = $recipesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get current inventory (finished_goods_inventory)
    $inventorySql = "SELECT 
                        fgi.id,
                        fgi.batch_id,
                        pb.batch_code,
                        fgi.quantity,
                        fgi.remaining_quantity,
                        fgi.manufacturing_date,
                        fgi.expiry_date,
                        fgi.chiller_location,
                        fgi.status
                     FROM finished_goods_inventory fgi
                     LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                     WHERE fgi.product_id = ? AND fgi.status = 'available'
                     ORDER BY fgi.expiry_date ASC
                     LIMIT 10";
    
    $inventoryStmt = $conn->prepare($inventorySql);
    $inventoryStmt->execute([$id]);
    $product['inventory'] = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Inventory summary
    $summaryStmt = $conn->prepare("SELECT 
        COALESCE(SUM(remaining_quantity), 0) as total_stock,
        COUNT(*) as batch_count,
        MIN(expiry_date) as nearest_expiry
    FROM finished_goods_inventory 
    WHERE product_id = ? AND status = 'available'");
    $summaryStmt->execute([$id]);
    $product['inventory_summary'] = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    
    sendSuccess(['product' => $product]);
}

/**
 * Create new product
 */
function createProduct($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['product_name', 'category'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendError("Field '$field' is required", 400);
            return;
        }
    }
    
    // Validate category
    $validCategories = ['pasteurized_milk', 'flavored_milk', 'yogurt', 'cheese', 'butter', 'cream'];
    if (!in_array($data['category'], $validCategories)) {
        sendError('Invalid category', 400);
        return;
    }
    
    // Generate product code if not provided
    if (empty($data['product_code'])) {
        $categoryPrefixes = [
            'pasteurized_milk' => 'PM',
            'flavored_milk' => 'FM',
            'yogurt' => 'YG',
            'cheese' => 'CH',
            'butter' => 'BT',
            'cream' => 'CR'
        ];
        $prefix = $categoryPrefixes[$data['category']] ?? 'PRD';
        $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(product_code, 3) AS UNSIGNED)) as max_num FROM products WHERE product_code LIKE '{$prefix}%'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNum = ($row['max_num'] ?? 0) + 1;
        $data['product_code'] = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }
    
    // Check for duplicate product code
    $checkStmt = $conn->prepare("SELECT id FROM products WHERE product_code = ?");
    $checkStmt->execute([$data['product_code']]);
    if ($checkStmt->fetch()) {
        sendError('Product code already exists', 409);
        return;
    }
    
    $sql = "INSERT INTO products (
                product_code, product_name, category, variant, milk_type_id,
                description, unit_size, unit_measure, shelf_life_days,
                storage_temp_min, storage_temp_max, base_unit, box_unit,
                pieces_per_box, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $data['product_code'],
        $data['product_name'],
        $data['category'],
        $data['variant'] ?? null,
        $data['milk_type_id'] ?? null,
        $data['description'] ?? null,
        $data['unit_size'] ?? null,
        $data['unit_measure'] ?? 'ml',
        $data['shelf_life_days'] ?? 7,
        $data['storage_temp_min'] ?? 2.00,
        $data['storage_temp_max'] ?? 6.00,
        $data['base_unit'] ?? 'piece',
        $data['box_unit'] ?? 'box',
        $data['pieces_per_box'] ?? 1,
        $data['is_active'] ?? 1
    ]);
    
    $productId = $conn->lastInsertId();
    
    sendSuccess([
        'message' => 'Product created successfully',
        'product_id' => $productId,
        'product_code' => $data['product_code']
    ], 201);
}

/**
 * Update product
 */
function updateProduct($conn, $id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if product exists
    $checkStmt = $conn->prepare("SELECT id, product_code FROM products WHERE id = ?");
    $checkStmt->execute([$id]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        sendError('Product not found', 404);
        return;
    }
    
    // Build dynamic update
    $updates = [];
    $params = [];
    
    $allowedFields = [
        'product_code', 'product_name', 'category', 'variant', 'milk_type_id',
        'description', 'unit_size', 'unit_measure', 'shelf_life_days',
        'storage_temp_min', 'storage_temp_max', 'base_unit', 'box_unit',
        'pieces_per_box', 'is_active'
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
    
    // Check for duplicate product code if changing
    if (isset($data['product_code']) && $data['product_code'] !== $existing['product_code']) {
        $dupStmt = $conn->prepare("SELECT id FROM products WHERE product_code = ? AND id != ?");
        $dupStmt->execute([$data['product_code'], $id]);
        if ($dupStmt->fetch()) {
            sendError('Product code already exists', 409);
            return;
        }
    }
    
    $params[] = $id;
    $sql = "UPDATE products SET " . implode(', ', $updates) . " WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    sendSuccess(['message' => 'Product updated successfully']);
}

/**
 * Delete (deactivate) product
 */
function deleteProduct($conn, $id) {
    // Check if product exists
    $checkStmt = $conn->prepare("SELECT id FROM products WHERE id = ?");
    $checkStmt->execute([$id]);
    
    if (!$checkStmt->fetch()) {
        sendError('Product not found', 404);
        return;
    }
    
    // Check for related inventory
    $relatedStmt = $conn->prepare("SELECT COUNT(*) as count FROM finished_goods_inventory WHERE product_id = ?");
    $relatedStmt->execute([$id]);
    $related = $relatedStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($related['count'] > 0) {
        // Soft delete - deactivate
        $stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        sendSuccess(['message' => 'Product deactivated (has inventory records)']);
    } else {
        // Also check for recipes
        $recipeStmt = $conn->prepare("SELECT COUNT(*) as count FROM master_recipes WHERE product_id = ?");
        $recipeStmt->execute([$id]);
        $recipeCount = $recipeStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($recipeCount['count'] > 0) {
            $stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            sendSuccess(['message' => 'Product deactivated (has associated recipes)']);
        } else {
            // Hard delete if no related records
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);
            sendSuccess(['message' => 'Product deleted successfully']);
        }
    }
}

/**
 * Get product statistics
 */
function getProductStatistics($conn) {
    $stats = [];
    
    // Total products by status
    $stmt = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM products");
    $stats['totals'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // By category
    $stmt = $conn->query("SELECT 
        category, 
        COUNT(*) as count
    FROM products
    WHERE is_active = 1
    GROUP BY category");
    $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // By milk type
    $stmt = $conn->query("SELECT 
        mt.type_name, 
        COUNT(p.id) as count
    FROM products p
    LEFT JOIN milk_types mt ON p.milk_type_id = mt.id
    WHERE p.is_active = 1 AND p.milk_type_id IS NOT NULL
    GROUP BY p.milk_type_id, mt.type_name");
    $stats['by_milk_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Current inventory summary
    $stmt = $conn->query("SELECT 
        p.id,
        p.product_code,
        p.product_name,
        p.category,
        COALESCE(SUM(fgi.remaining_quantity), 0) as current_stock,
        COUNT(fgi.id) as batch_count,
        MIN(fgi.expiry_date) as nearest_expiry
    FROM products p
    LEFT JOIN finished_goods_inventory fgi ON p.id = fgi.product_id AND fgi.status = 'available'
    WHERE p.is_active = 1
    GROUP BY p.id, p.product_code, p.product_name, p.category
    ORDER BY current_stock ASC
    LIMIT 20");
    $stats['inventory_summary'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Low stock products (less than 50 units)
    $stmt = $conn->query("SELECT 
        p.id,
        p.product_code,
        p.product_name,
        COALESCE(SUM(fgi.remaining_quantity), 0) as current_stock
    FROM products p
    LEFT JOIN finished_goods_inventory fgi ON p.id = fgi.product_id AND fgi.status = 'available'
    WHERE p.is_active = 1
    GROUP BY p.id, p.product_code, p.product_name
    HAVING current_stock < 50
    ORDER BY current_stock ASC");
    $stats['low_stock'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess(['statistics' => $stats]);
}

/**
 * Get product categories
 */
function getCategories($conn) {
    $categories = [
        ['value' => 'pasteurized_milk', 'label' => 'Pasteurized Milk'],
        ['value' => 'flavored_milk', 'label' => 'Flavored Milk'],
        ['value' => 'yogurt', 'label' => 'Yogurt'],
        ['value' => 'cheese', 'label' => 'Cheese'],
        ['value' => 'butter', 'label' => 'Butter'],
        ['value' => 'cream', 'label' => 'Cream']
    ];
    
    sendSuccess(['categories' => $categories]);
}

/**
 * Get milk types
 */
function getMilkTypes($conn) {
    $stmt = $conn->query("SELECT id, type_code, type_name, base_price_per_liter, is_active FROM milk_types WHERE is_active = 1");
    $milkTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess(['milk_types' => $milkTypes]);
}

/**
 * Export products to CSV
 */
function exportProducts($conn) {
    $sql = "SELECT 
                p.product_code,
                p.product_name,
                p.category,
                p.variant,
                mt.type_name as milk_type,
                p.unit_size,
                p.unit_measure,
                p.shelf_life_days,
                p.storage_temp_min,
                p.storage_temp_max,
                p.base_unit,
                p.pieces_per_box,
                CASE WHEN p.is_active = 1 THEN 'Active' ELSE 'Inactive' END as status,
                p.created_at
            FROM products p
            LEFT JOIN milk_types mt ON p.milk_type_id = mt.id
            ORDER BY p.product_code";
    
    $stmt = $conn->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess(['export_data' => $products]);
}
