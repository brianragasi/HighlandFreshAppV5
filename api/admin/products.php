<?php
/**
 * Admin Products API
 * Follows highland_fresh_revised.sql schema
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/sku_packaging_bom.php';

// Require GM/Admin role
Auth::requireRole(['general_manager', 'admin']);

// Get database connection
$conn = Database::getInstance()->getConnection();
ensureIngredientPackagingRoleSupport($conn);
ensureSkuPackagingBomTable($conn);
ensureProductPrimaryContainerSupport($conn);

// Get request method and handle routing
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$action = isset($_GET['action']) ? $_GET['action'] : null;

try {
    switch ($method) {
        case 'GET':
            if ($action === 'packaging_bom' && $id) {
                getProductPackagingBom($conn, $id);
            } elseif ($action === 'packaging_options') {
                getProductPackagingOptions($conn);
            } elseif ($id) {
                getProduct($conn, $id);
            } elseif ($action === 'statistics') {
                getProductStatistics($conn);
            } elseif ($action === 'export') {
                exportProducts($conn);
            } elseif ($action === 'categories') {
                getCategories($conn);
            } elseif ($action === 'milk-types') {
                getMilkTypes($conn);
            } elseif ($action === 'base_products') {
                getBaseProducts($conn);
            } else {
                getProducts($conn);
            }
            break;
        case 'POST':
            if ($action === 'disable_skus') {
                disableSkus($conn);
            } else {
                createProduct($conn);
            }
            break;
        case 'PUT':
            if ($action === 'packaging_bom' && $id) {
                saveProductPackagingBom($conn, $id);
            } elseif ($action === 'update_base' && $id) {
                updateBaseProduct($conn, $id);
            } elseif ($id) {
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
} catch (Throwable $e) {
    if (isDuplicateProductException($e)) {
        sendError(
            'A product with the same code or packaging size already exists. Open the existing SKU instead of creating another one.',
            409
        );
    }
    sendError($e->getMessage(), 500);
}

function isDuplicateProductException(Throwable $e) {
    $message = $e->getMessage();
    $driverCode = $e instanceof PDOException && isset($e->errorInfo[1])
        ? (int) $e->errorInfo[1]
        : 0;

    return $driverCode === 1062
        || stripos($message, 'duplicate entry') !== false;
}

function isLegacyProductSchemaException(Throwable $e) {
    $message = $e->getMessage();
    $driverCode = $e instanceof PDOException && isset($e->errorInfo[1])
        ? (int) $e->errorInfo[1]
        : 0;
    $sqlState = (string) $e->getCode();

    return in_array($driverCode, [1054, 1146], true)
        || in_array($sqlState, ['42S02', '42S22'], true)
        || stripos($message, 'base_products') !== false
        || stripos($message, 'base_product_id') !== false;
}

/**
 * List base liquid products with nested packaging SKUs.
 * GET /api/admin/products.php?action=base_products
 */
function getBaseProducts($conn) {
    // Product stock changes throughout dispatch and receiving. Never let a browser
    // reuse an old catalog response after Warehouse FG has moved inventory.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    try {
        $conn->query('SELECT id FROM base_products LIMIT 0');
    } catch (Throwable $e) {
        sendError('base_products table missing — run scripts/sql/bulk_batch_product_architecture.sql', 500);
        return;
    }

    try {
        $stmt = $conn->query("
            SELECT bp.*,
                   mt.type_name AS milk_type_name,
                   (SELECT COUNT(*) FROM products p WHERE p.base_product_id = bp.id) AS sku_count,
                   (SELECT COUNT(*) FROM master_recipes mr WHERE mr.base_product_id = bp.id AND mr.is_active = 1) AS recipe_count
            FROM base_products bp
            LEFT JOIN milk_types mt ON mt.id = bp.milk_type_id
            ORDER BY bp.id DESC
        ");
        $bases = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Stock subquery is optional — never fail the whole catalog if FG inventory is unavailable
        $skuSqlWithStock = "
            SELECT p.id, p.product_code, p.product_name, p.variant, p.unit_size, p.unit_measure,
                   p.base_unit, p.box_unit, p.pieces_per_box, p.selling_price, p.unit_price, p.is_active,
                   p.category, p.milk_type_id, p.shelf_life_days, p.storage_temp_min, p.storage_temp_max,
                   p.description, p.base_product_id, p.primary_container_id,
                   mt.type_name AS milk_type_name,
                   (SELECT COALESCE(SUM(
                            GREATEST(
                                COALESCE(fgi.quantity_available, 0),
                                COALESCE(fgi.remaining_quantity, 0)
                            )
                        ), 0)
                      FROM finished_goods_inventory fgi
                     WHERE fgi.product_id = p.id
                       AND fgi.status IN ('available', 'low_stock', 'reserved')
                       AND (fgi.expiry_date IS NULL OR fgi.expiry_date >= CURDATE())
                   ) AS current_stock
            FROM products p
            LEFT JOIN milk_types mt ON mt.id = p.milk_type_id
            WHERE p.base_product_id = ?
            ORDER BY p.unit_size ASC, p.product_code ASC
        ";
        $skuSqlSimple = "
            SELECT p.id, p.product_code, p.product_name, p.variant, p.unit_size, p.unit_measure,
                   p.base_unit, p.box_unit, p.pieces_per_box, p.selling_price, p.unit_price, p.is_active,
                   p.category, p.milk_type_id, p.shelf_life_days, p.storage_temp_min, p.storage_temp_max,
                   p.description, p.base_product_id, p.primary_container_id,
                   mt.type_name AS milk_type_name,
                   NULL AS current_stock
            FROM products p
            LEFT JOIN milk_types mt ON mt.id = p.milk_type_id
            WHERE p.base_product_id = ?
            ORDER BY p.unit_size ASC, p.product_code ASC
        ";

        try {
            $skuStmt = $conn->prepare($skuSqlWithStock);
            // Probe one execute path with invalid id to validate SQL compiles
            $skuStmt->execute([0]);
        } catch (Throwable $e) {
            error_log('getBaseProducts stock subquery unavailable: ' . $e->getMessage());
            $skuStmt = $conn->prepare($skuSqlSimple);
        }

        $recipeStmt = null;
        try {
            $recipeStmt = $conn->prepare("
                SELECT id, recipe_code, product_name, bulk_yield_liters, expected_yield, yield_unit,
                       base_milk_liters, is_active
                FROM master_recipes
                WHERE base_product_id = ?
                ORDER BY recipe_code ASC
            ");
        } catch (Throwable $e) {
            $recipeStmt = null;
        }

        $orphans = [];
        foreach ($bases as &$bp) {
            try {
                $skuStmt->execute([(int) $bp['id']]);
                $bp['skus'] = $skuStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                error_log('getBaseProducts SKU fetch id=' . $bp['id'] . ': ' . $e->getMessage());
                $bp['skus'] = [];
            }
            try {
                $bomMap = getSkuPackagingBomMap($conn, array_column($bp['skus'], 'id'));
            } catch (Throwable $e) {
                error_log('getBaseProducts packaging BOM fetch id=' . $bp['id'] . ': ' . $e->getMessage());
                $bomMap = [];
            }
            foreach ($bp['skus'] as &$sku) {
                $skuId = (int) $sku['id'];
                $sku['packaging_bom'] = $bomMap[$skuId] ?? [];
                $sku['packaging_bom_count'] = count($sku['packaging_bom']);
                $readiness = assessSkuPackagingBomReadiness(
                    $sku['base_unit'] ?? '',
                    $sku['packaging_bom'],
                    $sku['unit_size'] ?? null,
                    $sku['unit_measure'] ?? ''
                );
                $sku['packaging_bom_ready'] = $readiness['ready'];
                $sku['packaging_bom_missing'] = $readiness['missing'];
            }
            unset($sku);
            $bp['recipes'] = [];
            if ($recipeStmt) {
                try {
                    $recipeStmt->execute([(int) $bp['id']]);
                    $bp['recipes'] = $recipeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (Throwable $e) {
                    $bp['recipes'] = [];
                }
            }
            $recipeCount = (int) ($bp['recipe_count'] ?? count(array_filter(
                $bp['recipes'],
                static function ($r) {
                    return (int) ($r['is_active'] ?? 0) === 1;
                }
            )));
            $bp['recipe_count'] = $recipeCount;
            $bp['has_active_recipe'] = $recipeCount > 0;
            $bp['is_orphan'] = !$bp['has_active_recipe'];
            if ($bp['is_orphan']) {
                $orphans[] = [
                    'id' => (int) $bp['id'],
                    'code' => $bp['code'] ?? null,
                    'name' => $bp['name'] ?? '',
                    'category' => $bp['category'] ?? null,
                    'sku_count' => (int) ($bp['sku_count'] ?? count($bp['skus'])),
                    'recipe_count' => 0,
                ];
            }
        }
        unset($bp);

        // Ensure JSON-safe UTF-8 (prevents silent json_encode failures on odd names)
        array_walk_recursive($bases, function (&$v) {
            if (is_string($v) && !mb_check_encoding($v, 'UTF-8')) {
                $v = mb_convert_encoding($v, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            }
        });

        sendSuccess([
            'base_products' => $bases,
            'orphan_summary' => [
                'count' => count($orphans),
                'products' => $orphans,
                'message' => count($orphans) > 0
                    ? sprintf(
                        'Warning: %d product%s do not have an active recipe and cannot be manufactured.',
                        count($orphans),
                        count($orphans) === 1 ? '' : 's'
                    )
                    : null,
            ],
        ], 'Base products retrieved');
    } catch (Throwable $e) {
        error_log('getBaseProducts failed: ' . $e->getMessage());
        sendError('Could not load base products: ' . $e->getMessage(), 500);
    }
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
    
    // base_product_id optional (SKU → base liquid link)
    $hasBase = false;
    try {
        $conn->query('SELECT base_product_id FROM products LIMIT 0');
        $conn->query('SELECT id FROM base_products LIMIT 0');
        $hasBase = true;
    } catch (Throwable $e) {
        $hasBase = false;
    }

    if ($hasBase) {
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
                    p.selling_price,
                    p.is_active,
                    p.created_at,
                    p.updated_at,
                    p.base_product_id,
                    bp.code AS base_product_code,
                    bp.name AS base_product_name,
                    (SELECT COUNT(*) FROM master_recipes mr
                      WHERE mr.product_id = p.id OR mr.base_product_id = p.base_product_id) as recipe_count,
                    (SELECT COALESCE(SUM(fgi.remaining_quantity), 0) 
                     FROM finished_goods_inventory fgi WHERE fgi.product_id = p.id AND fgi.status = 'available') as current_stock
                FROM products p
                LEFT JOIN milk_types mt ON p.milk_type_id = mt.id
                LEFT JOIN base_products bp ON bp.id = p.base_product_id
                $whereClause
                ORDER BY COALESCE(bp.id, p.id) DESC, p.unit_size ASC
                LIMIT ? OFFSET ?";
    } else {
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
                    p.selling_price,
                    p.is_active,
                    p.created_at,
                    p.updated_at,
                    NULL AS base_product_id,
                    NULL AS base_product_code,
                    NULL AS base_product_name,
                    (SELECT COUNT(*) FROM master_recipes mr WHERE mr.product_id = p.id) as recipe_count,
                    (SELECT COALESCE(SUM(fgi.remaining_quantity), 0) 
                     FROM finished_goods_inventory fgi WHERE fgi.product_id = p.id AND fgi.status = 'available') as current_stock
                FROM products p
                LEFT JOIN milk_types mt ON p.milk_type_id = mt.id
                $whereClause
                ORDER BY p.id DESC
                LIMIT ? OFFSET ?";
    }
    
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
                p.selling_price,
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
    $product['packaging_bom'] = getSkuPackagingBom($conn, $id);
    $product['packaging_bom_count'] = count($product['packaging_bom']);
    $readiness = assessSkuPackagingBomReadiness(
        $product['base_unit'] ?? '',
        $product['packaging_bom'],
        $product['unit_size'] ?? null,
        $product['unit_measure'] ?? ''
    );
    $product['packaging_bom_ready'] = $readiness['ready'];
    $product['packaging_bom_missing'] = $readiness['missing'];
    
    sendSuccess(['product' => $product]);
}

/**
 * Get one sellable SKU's packaging BOM and eligible packaging materials.
 */
function getProductPackagingBom(PDO $conn, $id) {
    $stmt = $conn->prepare("
        SELECT p.id, p.product_code, p.product_name, p.variant,
               p.unit_size, p.unit_measure, p.base_unit, p.base_product_id,
               p.primary_container_id, p.is_active,
               bp.name AS base_product_name
        FROM products p
        LEFT JOIN base_products bp ON bp.id = p.base_product_id
        WHERE p.id = ?
    ");
    $stmt->execute([(int) $id]);
    $sku = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sku) {
        sendError('SKU not found', 404);
        return;
    }
    if (empty($sku['base_product_id'])) {
        sendValidationError(['product_id' => 'Packaging BOMs can only be configured on a sellable SKU under a base product']);
        return;
    }

    $items = getSkuPackagingBom($conn, $id);
    $readiness = assessSkuPackagingBomReadiness(
        $sku['base_unit'] ?? '',
        $items,
        $sku['unit_size'] ?? null,
        $sku['unit_measure'] ?? ''
    );
    sendSuccess([
        'sku' => $sku,
        'items' => $items,
        'available_materials' => getAvailablePackagingMaterials($conn),
        'ready' => $readiness['ready'],
        'missing_components' => $readiness['missing'],
    ]);
}

/** Save one sellable SKU's packaging BOM. */
function saveProductPackagingBom(PDO $conn, $id) {
    $data = getRequestBody();
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

    $stmt = $conn->prepare('SELECT id, base_product_id, base_unit, primary_container_id, is_active FROM products WHERE id = ?');
    $stmt->execute([(int) $id]);
    $sku = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sku) {
        sendError('SKU not found', 404);
        return;
    }
    if (empty($sku['base_product_id'])) {
        sendValidationError(['product_id' => 'Select a sellable packaging SKU, not a bulk/base product']);
        return;
    }

    if (in_array(strtolower((string) ($sku['base_unit'] ?? '')), ['bottle', 'bottles'], true)) {
        $primaryContainerId = (int) ($sku['primary_container_id'] ?? 0);
        $submittedIds = array_values(array_unique(array_filter(array_map(
            static fn($item) => (int) ($item['ingredient_id'] ?? 0),
            $items
        ))));
        $submittedContainers = [];
        if ($submittedIds) {
            $placeholders = implode(',', array_fill(0, count($submittedIds), '?'));
            $roleStmt = $conn->prepare("SELECT id FROM ingredients WHERE packaging_role = 'container' AND id IN ($placeholders)");
            $roleStmt->execute($submittedIds);
            $submittedContainers = array_map('intval', $roleStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        }
        sort($submittedContainers);
        if ($primaryContainerId <= 0 || $submittedContainers !== [$primaryContainerId]) {
            sendValidationError([
                'items.container' => 'Keep the SKU\'s selected primary container in the BOM. Change container from Edit SKU if the package size must change.'
            ]);
        }
    }

    $result = replaceSkuPackagingBom($conn, $id, $items);
    if (!$result['success']) {
        sendValidationError($result['errors']);
        return;
    }

    sendSuccess([
        'product_id' => (int) $id,
        'items' => $result['items'],
        'ready' => count($result['items']) > 0,
    ], count($result['items']) > 0
        ? 'Packaging BOM saved'
        : 'Packaging BOM cleared; this SKU cannot be used to complete packaging');
}

/** Active container master records available to the SKU form. */
function getProductPackagingOptions(PDO $conn): void {
    $stmt = $conn->query("
        SELECT i.id, i.ingredient_code, i.ingredient_name,
               i.packaging_capacity_value, i.packaging_capacity_unit,
               i.packaging_capacity_confirmed,
               i.unit_of_measure, i.current_stock, i.available_stock
        FROM ingredients i
        JOIN ingredient_categories c ON c.id = i.category_id
        WHERE i.is_active = 1
          AND i.packaging_role = 'container'
          AND i.packaging_capacity_value > 0
          AND i.packaging_capacity_unit IN ('ml', 'L', 'g', 'kg')
          AND (LOWER(c.category_name) LIKE '%packag%'
               OR LOWER(c.category_code) LIKE '%pack%'
               OR LOWER(c.category_name) LIKE '%container%')
        ORDER BY
          CASE WHEN i.packaging_capacity_unit IN ('ml', 'L') THEN 0 ELSE 1 END,
          CASE i.packaging_capacity_unit
            WHEN 'L' THEN i.packaging_capacity_value * 1000
            WHEN 'kg' THEN i.packaging_capacity_value * 1000
            ELSE i.packaging_capacity_value
          END,
          i.ingredient_name
    ");
    sendSuccess(['containers' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
}

/**
 * For bottled SKUs the selected inventory container is the source of truth.
 * Any client-supplied size is overwritten before duplicate checks and writes.
 */
function applyPrimaryContainerToSkuPayload(PDO $conn, array &$data, array $existing = []): ?array {
    $baseUnit = strtolower(trim((string) (
        $data['base_unit'] ?? ($existing['base_unit'] ?? 'piece')
    )));
    if (!in_array($baseUnit, ['bottle', 'bottles'], true)) {
        $data['primary_container_id'] = null;
        return null;
    }

    $containerId = (int) (
        $data['primary_container_id'] ?? ($existing['primary_container_id'] ?? 0)
    );
    if ($containerId <= 0) {
        sendValidationError([
            'primary_container_id' => 'Select the actual bottle/container. The SKU size is taken from its configured capacity.'
        ]);
    }

    $stmt = $conn->prepare("
        SELECT i.id, i.ingredient_code, i.ingredient_name, i.unit_of_measure,
               i.packaging_capacity_value, i.packaging_capacity_unit,
               i.packaging_capacity_confirmed
        FROM ingredients i
        JOIN ingredient_categories c ON c.id = i.category_id
        WHERE i.id = ? AND i.is_active = 1 AND i.packaging_role = 'container'
          AND (LOWER(c.category_name) LIKE '%packag%'
               OR LOWER(c.category_code) LIKE '%pack%'
               OR LOWER(c.category_name) LIKE '%container%')
        LIMIT 1
    ");
    $stmt->execute([$containerId]);
    $container = $stmt->fetch(PDO::FETCH_ASSOC);
    $capacity = $container
        ? packagingCanonicalSize(
            $container['packaging_capacity_value'] ?? null,
            $container['packaging_capacity_unit'] ?? ''
        )
        : null;
    if (!$container || !$capacity) {
        sendValidationError([
            'primary_container_id' => 'The selected container is inactive or has no valid capacity in Admin → Ingredients.'
        ]);
    }

    $data['base_unit'] = 'bottle';
    $data['primary_container_id'] = (int) $container['id'];
    $data['unit_size'] = (float) $container['packaging_capacity_value'];
    $data['unit_measure'] = $container['packaging_capacity_unit'];
    $data['confirm_unusual_capacity'] = (int) ($container['packaging_capacity_confirmed'] ?? 0) === 1;
    return $container;
}

/** Keep exactly one trusted container component in the SKU BOM. */
function syncSkuPrimaryContainer(PDO $conn, int $productId, ?array $container): void {
    $containerId = $container ? (int) $container['id'] : 0;
    $deleteSql = "
        DELETE spbi
        FROM sku_packaging_bom_items spbi
        JOIN ingredients i ON i.id = spbi.ingredient_id
        WHERE spbi.product_id = ? AND i.packaging_role = 'container'
    ";
    $deleteParams = [$productId];
    if ($containerId > 0) {
        $deleteSql .= ' AND spbi.ingredient_id <> ?';
        $deleteParams[] = $containerId;
    }
    $conn->prepare($deleteSql)->execute($deleteParams);
    if (!$container) {
        return;
    }

    $insert = $conn->prepare("
        INSERT INTO sku_packaging_bom_items
            (product_id, ingredient_id, quantity_per_unit, waste_percent, unit, is_active)
        VALUES (?, ?, 1, 0, ?, 1)
        ON DUPLICATE KEY UPDATE
            quantity_per_unit = 1, unit = VALUES(unit), is_active = 1
    ");
    $insert->execute([
        $productId,
        (int) $container['id'],
        $container['unit_of_measure'] ?: 'piece',
    ]);
}

/**
 * Create new product
 */
function validateProductNumericPayload(array &$data): void {
    $errors = [];
    foreach ([
        'unit_size' => ['Package size', 1.00, 99999999.99],
        'selling_price' => ['Selling price', 0.01, 9999999999.99],
        'unit_price' => ['Unit price', 0.01, 9999999999.99],
    ] as $field => [$label, $minimum, $maximum]) {
        if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
            continue;
        }
        try {
            $data[$field] = hfParseBusinessDecimal($data[$field], $label, $minimum, $maximum, 2);
        } catch (InvalidArgumentException $error) {
            $errors[$field] = $error->getMessage();
        }
    }
    foreach ([
        'pieces_per_box' => ['Pieces per box', 1, 1000000],
        'shelf_life_days' => ['Shelf life', 1, 3650],
    ] as $field => [$label, $minimum, $maximum]) {
        if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
            continue;
        }
        try {
            $data[$field] = hfParseBusinessInteger($data[$field], $label, $minimum, $maximum);
        } catch (InvalidArgumentException $error) {
            $errors[$field] = $error->getMessage();
        }
    }
    if ($errors) {
        sendValidationError($errors);
    }
}

function validateUnusualSkuCapacity(array $data): void {
    $isBottle = in_array(strtolower(trim((string) ($data['base_unit'] ?? ''))), ['bottle', 'bottles'], true);
    $isLargeLiterValue = strtolower(trim((string) ($data['unit_measure'] ?? ''))) === 'l'
        && (float) ($data['unit_size'] ?? 0) >= 20;
    if ($isBottle && $isLargeLiterValue && empty($data['confirm_unusual_capacity'])) {
        sendValidationError([
            'unit_size' => 'This bottle size is unusually large. Review the container in Admin > Packaging Materials and confirm whether liters or milliliters is correct.'
        ]);
    }
}

/**
 * Normalize the optional milk-type selector before it reaches a foreign key.
 * The UI historically used 0 for "N/A", but milk_types IDs start at 1, so
 * passing that sentinel through caused an opaque SQL 1452/HTTP 500 response.
 */
function normalizeProductMilkType($conn, array &$data): void {
    if (!array_key_exists('milk_type_id', $data)) {
        return;
    }

    $raw = $data['milk_type_id'];
    if ($raw === null || $raw === '' || $raw === 0 || $raw === '0') {
        $data['milk_type_id'] = null;
        return;
    }

    if (filter_var($raw, FILTER_VALIDATE_INT) === false || (int) $raw <= 0) {
        sendValidationError([
            'milk_type_id' => 'Select a valid milk type or choose Not specified / N/A.'
        ]);
    }

    $milkTypeId = (int) $raw;
    $stmt = $conn->prepare('SELECT id FROM milk_types WHERE id = ? LIMIT 1');
    $stmt->execute([$milkTypeId]);
    if (!$stmt->fetchColumn()) {
        sendValidationError([
            'milk_type_id' => 'The selected milk type is no longer available. Refresh the page and choose again.'
        ]);
    }

    $data['milk_type_id'] = $milkTypeId;
}

function createProduct($conn) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    normalizeProductMilkType($conn, $data);
    $primaryContainer = applyPrimaryContainerToSkuPayload($conn, $data);
    validateProductNumericPayload($data);
    validateUnusualSkuCapacity($data);
    
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

    // Product codes are generated from the latest number in a category. Hold
    // a short category lock through the insert so simultaneous browser tabs or
    // older clients cannot receive the same generated code.
    $productCodeLock = 'hf_product_code_' . $data['category'];
    $lockStmt = $conn->prepare('SELECT GET_LOCK(?, 10)');
    $lockStmt->execute([$productCodeLock]);
    if ((int) $lockStmt->fetchColumn() !== 1) {
        sendError('Another product is being saved. Please try again.', 409);
        return;
    }

    try {

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

    // ── Duplicate volume/weight detection ─────────────────────────────────────
    // Prevents creating '1L' when '1000ml' already exists (or vice versa).
    $baseProductId = !empty($data['base_product_id']) ? (int) $data['base_product_id'] : null;
    try {
        $conn->query('SELECT base_product_id FROM products LIMIT 0');
        $conn->query('SELECT id FROM base_products LIMIT 0');
        if (!$baseProductId) {
            $find = $conn->prepare("SELECT id FROM base_products WHERE name = ? AND category = ? LIMIT 1");
            $find->execute([trim($data['product_name']), $data['category']]);
            $baseProductId = $find->fetchColumn() ?: null;
        }
    } catch (Throwable $e) {
        $baseProductId = null;
    }

    // A base product is one manufacturable formula/flavor. Its child SKUs vary
    // only by packaging, so a SKU-level flavor must never create a second formula.
    if ($baseProductId) {
        $data['variant'] = null;
    }

    if (!empty($data['unit_size']) && !empty($data['unit_measure'])) {
        $canonicalSize = normalizeToBaseUnit((float) $data['unit_size'], $data['unit_measure']);
        if ($canonicalSize !== null) {
            $existingDup = findEquivalentSku(
                $conn,
                $data['category'],
                $canonicalSize,
                $baseProductId,
                $data['product_name'] ?? null,
                $data['variant'] ?? null,
                $data['base_unit'] ?? 'piece'
            );
            if ($existingDup) {
                sendError(
                    "A SKU with the same packaging type and equivalent size ({$existingDup['unit_size']} {$existingDup['unit_measure']}) already exists for this formula: \"{$existingDup['product_name']}\" (SKU: {$existingDup['product_code']})",
                    409
                );
                return;
            }
        }
    }

    // Resolve / create base product when architecture is present
    try {
        $conn->query('SELECT base_product_id FROM products LIMIT 0');
        $conn->query('SELECT id FROM base_products LIMIT 0');
        if (!$baseProductId) {
            $find = $conn->prepare("SELECT id FROM base_products WHERE name = ? AND category = ? LIMIT 1");
            $find->execute([trim($data['product_name']), $data['category']]);
            $baseProductId = $find->fetchColumn() ?: null;
            if (!$baseProductId) {
                $prefixMap = [
                    'pasteurized_milk' => 'BASE-PM', 'flavored_milk' => 'BASE-FM',
                    'yogurt' => 'BASE-YG', 'cheese' => 'BASE-CH', 'butter' => 'BASE-BT', 'cream' => 'BASE-CR'
                ];
                $prefix = $prefixMap[$data['category']] ?? 'BASE-XX';
                $code = $prefix . '-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $data['product_name']), 0, 6)) . rand(10, 99);
                $insBp = $conn->prepare("
                    INSERT INTO base_products (code, name, category, milk_type_id, description,
                        default_shelf_life_days, storage_temp_min, storage_temp_max, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insBp->execute([
                    $code,
                    trim($data['product_name']),
                    $data['category'],
                    $data['milk_type_id'] ?? null,
                    $data['description'] ?? null,
                    $data['shelf_life_days'] ?? 7,
                    $data['storage_temp_min'] ?? 2.00,
                    $data['storage_temp_max'] ?? 6.00,
                    $data['is_active'] ?? 1,
                ]);
                $baseProductId = (int) $conn->lastInsertId();
            }
        }

        if ($baseProductId) {
            $data['variant'] = null;
        }

        $sql = "INSERT INTO products (
                    base_product_id, product_code, product_name, category, variant, milk_type_id,
                    description, unit_size, unit_measure, primary_container_id, shelf_life_days,
                    storage_temp_min, storage_temp_max, base_unit, box_unit,
                    pieces_per_box, selling_price, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $baseProductId,
            $data['product_code'],
            $data['product_name'],
            $data['category'],
            $data['variant'] ?? null,
            $data['milk_type_id'] ?? null,
            $data['description'] ?? null,
            $data['unit_size'] ?? null,
            $data['unit_measure'] ?? 'ml',
            $data['primary_container_id'],
            $data['shelf_life_days'] ?? 7,
            $data['storage_temp_min'] ?? 2.00,
            $data['storage_temp_max'] ?? 6.00,
            $data['base_unit'] ?? 'piece',
            $data['box_unit'] ?? 'box',
            $data['pieces_per_box'] ?? 1,
            $data['selling_price'] ?? $data['unit_price'] ?? 0.00,
            $data['is_active'] ?? 1
        ]);
    } catch (Throwable $e) {
        if (!isLegacyProductSchemaException($e)) {
            throw $e;
        }

        $sql = "INSERT INTO products (
                    product_code, product_name, category, variant, milk_type_id,
                    description, unit_size, unit_measure, primary_container_id, shelf_life_days,
                    storage_temp_min, storage_temp_max, base_unit, box_unit,
                    pieces_per_box, selling_price, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

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
            $data['primary_container_id'],
            $data['shelf_life_days'] ?? 7,
            $data['storage_temp_min'] ?? 2.00,
            $data['storage_temp_max'] ?? 6.00,
            $data['base_unit'] ?? 'piece',
            $data['box_unit'] ?? 'box',
            $data['pieces_per_box'] ?? 1,
            $data['selling_price'] ?? $data['unit_price'] ?? 0.00,
            $data['is_active'] ?? 1
        ]);
    }
    
        $productId = (int) $conn->lastInsertId();
        syncSkuPrimaryContainer($conn, $productId, $primaryContainer);
    } finally {
        $releaseStmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
        $releaseStmt->execute([$productCodeLock]);
    }
    
    sendSuccess([
        'message' => 'Product created successfully',
        'product_id' => $productId,
        'product_code' => $data['product_code']
    ], 'Product created successfully', 201);
}

/**
 * Update base liquid product (shared master props).
 * PUT /api/admin/products.php?action=update_base&id={base_product_id}
 */
function updateBaseProduct($conn, $id) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    normalizeProductMilkType($conn, $data);
    $id = (int) $id;
    if ($id <= 0) {
        sendError('Base product ID required', 400);
        return;
    }

    try {
        $conn->query('SELECT id FROM base_products LIMIT 0');
    } catch (Throwable $e) {
        sendError('base_products table missing', 500);
        return;
    }

    $check = $conn->prepare('SELECT id FROM base_products WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendError('Base product not found', 404);
        return;
    }

    $updates = [];
    $params = [];
    $map = [
        'name' => 'name',
        'category' => 'category',
        'milk_type_id' => 'milk_type_id',
        'description' => 'description',
        'default_shelf_life_days' => 'default_shelf_life_days',
        'shelf_life_days' => 'default_shelf_life_days', // alias from modal
        'storage_temp_min' => 'storage_temp_min',
        'storage_temp_max' => 'storage_temp_max',
        'is_active' => 'is_active',
    ];
    $seen = [];
    foreach ($map as $inKey => $col) {
        if (!array_key_exists($inKey, $data) || isset($seen[$col])) {
            continue;
        }
        $seen[$col] = true;
        $updates[] = "{$col} = ?";
        $params[] = $data[$inKey];
    }

    if (empty($updates)) {
        sendError('No fields to update', 400);
        return;
    }

    $params[] = $id;
    $sql = 'UPDATE base_products SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    // Keep child SKU shared fields in sync with base name/category when provided
    $skuUpdates = [];
    $skuParams = [];
    if (array_key_exists('name', $data) && trim((string) $data['name']) !== '') {
        $skuUpdates[] = 'product_name = ?';
        $skuParams[] = trim((string) $data['name']);
    }
    if (array_key_exists('category', $data) && $data['category'] !== null && $data['category'] !== '') {
        $skuUpdates[] = 'category = ?';
        $skuParams[] = $data['category'];
    }
    if (array_key_exists('milk_type_id', $data)) {
        $skuUpdates[] = 'milk_type_id = ?';
        $skuParams[] = $data['milk_type_id'] !== '' ? $data['milk_type_id'] : null;
    }
    $shelf = $data['default_shelf_life_days'] ?? $data['shelf_life_days'] ?? null;
    if ($shelf !== null && $shelf !== '') {
        $skuUpdates[] = 'shelf_life_days = ?';
        $skuParams[] = (int) $shelf;
    }
    if (array_key_exists('storage_temp_min', $data)) {
        $skuUpdates[] = 'storage_temp_min = ?';
        $skuParams[] = $data['storage_temp_min'];
    }
    if (array_key_exists('storage_temp_max', $data)) {
        $skuUpdates[] = 'storage_temp_max = ?';
        $skuParams[] = $data['storage_temp_max'];
    }
    if (array_key_exists('description', $data)) {
        $skuUpdates[] = 'description = ?';
        $skuParams[] = $data['description'];
    }
    if (array_key_exists('is_active', $data)) {
        $skuUpdates[] = 'is_active = ?';
        $skuParams[] = (int) $data['is_active'];
    }
    if (!empty($skuUpdates)) {
        $skuParams[] = $id;
        $skuSql = 'UPDATE products SET ' . implode(', ', $skuUpdates) . ' WHERE base_product_id = ?';
        try {
            $conn->prepare($skuSql)->execute($skuParams);
        } catch (Throwable $e) {
            // Non-fatal: individual SKU updates from the modal still apply
            error_log('updateBaseProduct SKU cascade: ' . $e->getMessage());
        }
    }

    sendSuccess(['message' => 'Base product updated successfully', 'base_product_id' => $id]);
}

/**
 * Update product
 */
function updateProduct($conn, $id) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    normalizeProductMilkType($conn, $data);
    
    // Check if product exists
    $checkStmt = $conn->prepare("
        SELECT id, product_code, product_name, category, variant, unit_size, unit_measure,
               base_unit, base_product_id, primary_container_id
        FROM products
        WHERE id = ?
    ");
    $checkStmt->execute([$id]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        sendError('Product not found', 404);
        return;
    }

    $primaryContainer = applyPrimaryContainerToSkuPayload($conn, $data, $existing);
    validateProductNumericPayload($data);
    validateUnusualSkuCapacity(array_merge($existing, $data));

    // Keep legacy variant text intact for audit/migration, but do not allow it
    // to be edited on a base-linked SKU. Flavor belongs to base_products.
    if (!empty($existing['base_product_id'])) {
        unset($data['variant']);
    }

    // Build dynamic update
    $updates = [];
    $params = [];
    
    $allowedFields = [
        'product_code', 'product_name', 'category', 'variant', 'milk_type_id',
        'description', 'unit_size', 'unit_measure', 'primary_container_id', 'shelf_life_days',
        'storage_temp_min', 'storage_temp_max', 'base_unit', 'box_unit',
        'pieces_per_box', 'selling_price', 'unit_price', 'is_active'
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

    $nextUnitSize = array_key_exists('unit_size', $data) ? (float) $data['unit_size'] : (float) $existing['unit_size'];
    $nextUnitMeasure = array_key_exists('unit_measure', $data) ? $data['unit_measure'] : $existing['unit_measure'];
    $nextCategory = array_key_exists('category', $data) ? $data['category'] : $existing['category'];
    $nextProductName = array_key_exists('product_name', $data) ? $data['product_name'] : $existing['product_name'];
    $nextVariant = array_key_exists('variant', $data) ? $data['variant'] : $existing['variant'];
    $nextBaseUnit = array_key_exists('base_unit', $data) ? $data['base_unit'] : ($existing['base_unit'] ?? 'piece');
    $nextBaseProductId = !empty($existing['base_product_id']) ? (int) $existing['base_product_id'] : null;

    if ($nextUnitSize > 0 && !empty($nextUnitMeasure)) {
        $canonicalSize = normalizeToBaseUnit($nextUnitSize, $nextUnitMeasure);
        if ($canonicalSize !== null) {
            $existingDup = findEquivalentSku(
                $conn,
                $nextCategory,
                $canonicalSize,
                $nextBaseProductId,
                $nextProductName,
                $nextVariant,
                $nextBaseUnit,
                (int) $id
            );
            if ($existingDup) {
                sendError(
                    "A SKU with the same packaging type and equivalent size ({$existingDup['unit_size']} {$existingDup['unit_measure']}) already exists for this formula: \"{$existingDup['product_name']}\" (SKU: {$existingDup['product_code']})",
                    409
                );
                return;
            }
        }
    }
    
    $params[] = $id;
    $sql = "UPDATE products SET " . implode(', ', $updates) . " WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    syncSkuPrimaryContainer($conn, (int) $id, $primaryContainer);
    
    sendSuccess(['message' => 'Product updated successfully']);
}

/**
 * Soft-delete one or more SKUs (products.is_active = 0).
 * POST /api/admin/products.php?action=disable_skus
 * Body: { "sku_ids": [1,2,3] } or { "skus_to_disable": [1,2,3] }
 *
 * Never runs DELETE FROM — preserves referential integrity for FG inventory,
 * packaging, recipes, and historical sales lines.
 */
function disableSkus($conn) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $ids = $data['sku_ids'] ?? $data['skus_to_disable'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
        return $id > 0;
    })));

    if (empty($ids)) {
        sendError('No SKU IDs provided to disable', 400);
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $affected = $stmt->rowCount();

    sendSuccess([
        'disabled_count' => $affected,
        'sku_ids' => $ids,
        'message' => "Deactivated {$affected} SKU(s) (soft delete — rows retained)"
    ], "Deactivated {$affected} SKU(s)");
}

/**
 * Delete (deactivate) product — ALWAYS soft-delete.
 * Hard DELETE is disabled to protect inventory/recipe foreign keys.
 */
function deleteProduct($conn, $id) {
    // Check if product exists
    $checkStmt = $conn->prepare("SELECT id, product_code, product_name, is_active FROM products WHERE id = ?");
    $checkStmt->execute([$id]);
    $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        sendError('Product not found', 404);
        return;
    }

    // Soft delete only — never hard DELETE FROM products
    $stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
    $stmt->execute([(int) $id]);

    $relatedStmt = $conn->prepare("SELECT COUNT(*) as count FROM finished_goods_inventory WHERE product_id = ?");
    $relatedStmt->execute([(int) $id]);
    $invCount = (int) ($relatedStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    $msg = $invCount > 0
        ? 'SKU deactivated (inventory history retained)'
        : 'SKU deactivated (soft delete)';

    sendSuccess([
        'message' => $msg,
        'product_id' => (int) $id,
        'is_active' => 0,
        'soft_deleted' => true
    ], $msg);
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

/**
 * Convert a unit_size + unit_measure pair to a canonical base measurement (ml or g).
 * Returns null if the unit_measure is unrecognized.
 */
function normalizeToBaseUnit(float $size, string $measure): ?float {
    $measure = strtolower(trim($measure));

    $mlMap = [
        'ml'     => 1,
        'l'      => 1000,
        'ltr'    => 1000,
        'litre'  => 1000,
        'liter'  => 1000,
        'litres' => 1000,
        'liters' => 1000,
        'cl'     => 10,
    ];

    $gMap = [
        'g'      => 1,
        'gm'     => 1,
        'gram'   => 1,
        'grams'  => 1,
        'kg'     => 1000,
        'kgs'    => 1000,
        'kilogram'  => 1000,
        'kilograms' => 1000,
    ];

    if (isset($mlMap[$measure])) {
        return round($size * $mlMap[$measure], 4);
    }
    if (isset($gMap[$measure])) {
        return round($size * $gMap[$measure], 4);
    }

    return null;
}

/**
 * Find an active SKU with the same formula/base, packaging type, and normalized
 * volume/weight (within 0.01 tolerance). Variant is considered only for legacy
 * products that are not linked to a base product.
 */
function findEquivalentSku(
    PDO $conn,
    string $category,
    float $canonicalValue,
    ?int $baseProductId = null,
    ?string $productName = null,
    ?string $variant = null,
    ?string $baseUnit = null,
    ?int $excludeId = null
): ?array {
    $sql = "
        SELECT id, product_code, product_name, variant, unit_size, unit_measure, base_unit, base_product_id
          FROM products
         WHERE is_active = 1
           AND category = :category
    ";
    $params = [':category' => $category];

    if ($excludeId) {
        $sql .= " AND id != :exclude_id";
        $params[':exclude_id'] = $excludeId;
    }

    if ($baseProductId) {
        $sql .= " AND base_product_id = :base_product_id";
        $params[':base_product_id'] = $baseProductId;
    } elseif ($productName !== null && trim($productName) !== '') {
        $sql .= " AND LOWER(TRIM(product_name)) = LOWER(TRIM(:product_name))";
        $params[':product_name'] = $productName;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $targetVariant = strtolower(trim((string) ($variant ?? '')));

    foreach ($rows as $row) {
        $existing = normalizeToBaseUnit((float) $row['unit_size'], $row['unit_measure']);
        if ($existing === null) continue;

        $sameSize = abs($existing - $canonicalValue) < 0.01;
        if (!$sameSize) {
            continue;
        }

        // Under the current model, base_product_id identifies the formula/flavor
        // and base_unit identifies the packaging type. Legacy variant text does
        // not create a second formula or bypass a true packaging duplicate.
        if ($baseProductId) {
            $existingBaseUnit = strtolower(trim((string) ($row['base_unit'] ?? 'piece')));
            $targetBaseUnit = strtolower(trim((string) ($baseUnit ?? 'piece')));
            if ($existingBaseUnit === $targetBaseUnit) {
                return $row;
            }
            continue;
        }

        $existingVariant = strtolower(trim((string) ($row['variant'] ?? '')));
        if ($existingVariant === $targetVariant) {
            return $row;
        }
    }

    return null;
}
