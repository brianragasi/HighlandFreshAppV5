<?php
/**
 * Admin Ingredients API
 * CRUD operations for ingredients table
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/supplier_ingredient_catalog.php';
require_once __DIR__ . '/../helpers/plain_text.php';
require_once __DIR__ . '/../warehouse/raw/ingredient_stock_helpers.php';

// Require GM/Admin role
$currentUser = Auth::requireRole(['general_manager', 'admin']);

// Get database connection
$conn = Database::getInstance()->getConnection();
ensureIngredientMasterSettings($conn);
ensureSupplierIngredientCatalog($conn);

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
            createIngredient($conn, $currentUser);
            break;
        case 'PUT':
            if ($id) {
                updateIngredient($conn, $id, $currentUser);
            } else {
                sendError('Ingredient ID required', 400);
            }
            break;
        case 'DELETE':
            if ($id) {
                deleteIngredient($conn, $id, $currentUser);
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
    $sql = "SELECT i.*, c.category_name,
                   COUNT(DISTINCT CASE WHEN si.is_active = 1 AND s.is_active = 1 THEN si.supplier_id END) AS supplier_count,
                   GROUP_CONCAT(DISTINCT CASE WHEN si.is_active = 1 AND s.is_active = 1 THEN s.supplier_name END ORDER BY s.supplier_name SEPARATOR ', ') AS supplier_names
            FROM ingredients i 
            LEFT JOIN ingredient_categories c ON i.category_id = c.id
            LEFT JOIN supplier_ingredients si ON si.ingredient_id = i.id
            LEFT JOIN suppliers s ON s.id = si.supplier_id
            $whereClause 
            GROUP BY i.id
            ORDER BY i.id DESC
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

function ensureIngredientMasterSettings($conn) {
    if (!auditColumnExists($conn, 'ingredients', 'physical_state')) {
        $conn->exec("ALTER TABLE `ingredients` ADD COLUMN `physical_state` VARCHAR(20) NULL AFTER `unit_of_measure`");
        $conn->exec("
            UPDATE `ingredients`
            SET `physical_state` = CASE
                WHEN LOWER(TRIM(COALESCE(unit_of_measure, ''))) IN ('kg', 'kilogram', 'kilograms', 'g', 'gram', 'grams') THEN 'solid'
                WHEN LOWER(TRIM(COALESCE(unit_of_measure, ''))) IN ('l', 'lt', 'liter', 'liters', 'litre', 'litres', 'ml', 'milliliter', 'milliliters') THEN 'liquid'
                ELSE 'count'
            END
        ");
    }

    if (!auditColumnExists($conn, 'ingredients', 'is_perishable')) {
        $conn->exec("ALTER TABLE `ingredients` ADD COLUMN `is_perishable` TINYINT(1) NOT NULL DEFAULT 1 AFTER `shelf_life_days`");
        $conn->exec("
            UPDATE `ingredients`
            SET `is_perishable` = CASE
                WHEN LOWER(CONCAT(COALESCE(ingredient_name, ''), ' ', COALESCE(storage_requirements, ''))) REGEXP 'bottle|cap|label|ribbon|cellophane|plastic|packaging' THEN 0
                ELSE 1
            END
        ");
    }

    // Packaging materials are counted supplies, not food batches. Keep this
    // rule active on every run so an old or imported packaging record cannot
    // accidentally be held for a food-style supplier lot and expiry date.
    $conn->exec("
        UPDATE ingredients i
        JOIN ingredient_categories c ON c.id = i.category_id
        SET i.is_perishable = 0,
            i.shelf_life_days = NULL
        WHERE LOWER(TRIM(COALESCE(c.category_name, ''))) LIKE '%packaging%'
          AND (COALESCE(i.is_perishable, 1) <> 0 OR i.shelf_life_days IS NOT NULL)
    ");

    if (!auditColumnExists($conn, 'ingredients', 'maximum_stock')) {
        $conn->exec("ALTER TABLE `ingredients` ADD COLUMN `maximum_stock` DECIMAL(10,2) DEFAULT NULL COMMENT 'Par level / order-up-to stock' AFTER `reorder_point`");
    }

    $packagingColumns = [
        'purchase_format' => "ALTER TABLE `ingredients` ADD COLUMN `purchase_format` VARCHAR(20) NOT NULL DEFAULT 'direct_unit' AFTER `physical_state`",
        'container_type' => "ALTER TABLE `ingredients` ADD COLUMN `container_type` VARCHAR(30) NULL AFTER `purchase_format`",
        'container_size_value' => "ALTER TABLE `ingredients` ADD COLUMN `container_size_value` DECIMAL(12,3) NULL AFTER `container_type`",
        'container_size_unit' => "ALTER TABLE `ingredients` ADD COLUMN `container_size_unit` VARCHAR(20) NULL AFTER `container_size_value`",
        'purchase_package_type' => "ALTER TABLE `ingredients` ADD COLUMN `purchase_package_type` VARCHAR(30) NULL AFTER `container_size_unit`",
        'containers_per_purchase_package' => "ALTER TABLE `ingredients` ADD COLUMN `containers_per_purchase_package` INT NULL AFTER `purchase_package_type`",
        'purchase_price_basis' => "ALTER TABLE `ingredients` ADD COLUMN `purchase_price_basis` VARCHAR(30) NOT NULL DEFAULT 'stock_unit' AFTER `containers_per_purchase_package`",
        'purchase_price' => "ALTER TABLE `ingredients` ADD COLUMN `purchase_price` DECIMAL(12,2) NULL AFTER `purchase_price_basis`",
    ];
    foreach ($packagingColumns as $column => $sql) {
        if (!auditColumnExists($conn, 'ingredients', $column)) {
            $conn->exec($sql);
        }
    }

    // Preserve existing package records as one-container packages. New records
    // can additionally describe an outer box/case without changing stock math.
    $conn->exec("
        UPDATE `ingredients`
        SET container_type = CASE
                WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'sack' THEN 'sack'
                WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'bag' THEN 'bag'
                WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'bottle' THEN 'bottle'
                WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'sachet' THEN 'sachet'
                WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'packet' THEN 'packet'
                WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'roll' THEN 'roll'
                WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'drum' THEN 'drum'
                WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'pail' THEN 'pail'
                -- Legacy rows stored only one package level. A box/case/crate
                -- therefore cannot safely be treated as a known inner container.
                WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'crate|case|box' THEN 'container'
                WHEN pack_size_value IS NOT NULL THEN 'container'
                ELSE NULL
            END,
            container_size_value = COALESCE(container_size_value, pack_size_value),
            container_size_unit = COALESCE(container_size_unit, pack_size_unit, unit_of_measure),
            purchase_price_basis = COALESCE(NULLIF(purchase_price_basis, ''), 'stock_unit'),
            purchase_price = COALESCE(purchase_price, unit_cost)
        WHERE pack_size_value IS NOT NULL
          AND (container_type IS NULL OR container_size_value IS NULL OR purchase_price IS NULL)
    ");
    $conn->exec("
        UPDATE `ingredients`
        SET purchase_format = CASE
            WHEN container_type IS NULL THEN 'direct_unit'
            ELSE 'packaged'
        END
        WHERE purchase_format NOT IN ('direct_unit', 'packaged')
           OR (container_type IS NOT NULL AND purchase_format = 'direct_unit')
    ");
}

function ingredientCategoryIsPackaging($conn, $categoryId) {
    $stmt = $conn->prepare("SELECT category_name FROM ingredient_categories WHERE id = ?");
    $stmt->execute([(int) $categoryId]);
    $name = strtolower(trim((string) $stmt->fetchColumn()));
    return $name !== '' && strpos($name, 'packaging') !== false;
}

function ingredientUnitKey($unit) {
    $key = strtolower(trim((string) $unit));
    $aliases = [
        'l' => 'liter',
        'liters' => 'liter',
        'litre' => 'liter',
        'litres' => 'liter',
        'milliliter' => 'ml',
        'milliliters' => 'ml',
        'kilogram' => 'kg',
        'kilograms' => 'kg',
        'gram' => 'g',
        'grams' => 'g',
        'pc' => 'pcs',
        'piece' => 'pcs',
        'pieces' => 'pcs',
        'packs' => 'pack',
        'packets' => 'packet',
        'rolls' => 'roll',
        'bottles' => 'bottle',
    ];
    return $aliases[$key] ?? $key;
}

function normalizeIngredientStockUnit($unit) {
    $normalized = ingredientUnitKey($unit);
    $allowed = ['kg', 'g', 'liter', 'ml', 'pcs', 'pack', 'packet', 'roll', 'bottle'];
    if (!in_array($normalized, $allowed, true)) {
        sendValidationError(['unit_of_measure' => 'Choose a supported stock unit']);
    }
    return $normalized;
}

function inferIngredientPhysicalState($unit) {
    $normalized = ingredientUnitKey($unit);
    if (in_array($normalized, ['kg', 'g'], true)) {
        return 'solid';
    }
    if (in_array($normalized, ['liter', 'ml'], true)) {
        return 'liquid';
    }
    return 'count';
}

function normalizeIngredientPhysicalState($state, $unit = '') {
    $normalized = strtolower(trim((string) $state));
    $aliases = [
        'solid' => 'solid',
        'powder' => 'solid',
        'dry' => 'solid',
        'liquid' => 'liquid',
        'fluid' => 'liquid',
        'count' => 'count',
        'counted' => 'count',
        'piece' => 'count',
    ];
    if ($normalized === '' && $unit !== '') {
        return inferIngredientPhysicalState($unit);
    }
    if (!isset($aliases[$normalized])) {
        sendValidationError(['physical_state' => 'Choose whether the ingredient is solid, liquid, or counted']);
    }
    return $aliases[$normalized];
}

function validateIngredientPhysicalStateUnit($physicalState, $unit) {
    $allowedByState = [
        'solid' => ['kg', 'g'],
        'liquid' => ['liter', 'ml'],
        'count' => ['pcs', 'pack', 'packet', 'roll', 'bottle'],
    ];
    if (!isset($allowedByState[$physicalState]) || !in_array($unit, $allowedByState[$physicalState], true)) {
        $message = $physicalState === 'liquid'
            ? 'Liquid ingredients must use liters or milliliters. Grams require a separately approved density conversion.'
            : ($physicalState === 'solid'
                ? 'Solid ingredients must use kilograms or grams.'
                : 'Counted items must use pieces, packs, packets, rolls, or bottles.');
        sendValidationError(['unit_of_measure' => $message]);
    }
}

/**
 * Keep the category and stock unit meaningful. Packaging is counted, while
 * process ingredients are measured by mass or volume.
 */
function validateIngredientCategoryUnit($conn, $categoryId, $unit) {
    if (!is_numeric($categoryId) || (int) $categoryId <= 0) {
        sendValidationError(['category_id' => 'Ingredient category is required']);
    }

    $stmt = $conn->prepare("SELECT category_name FROM ingredient_categories WHERE id = ?");
    $stmt->execute([(int) $categoryId]);
    $categoryName = $stmt->fetchColumn();
    if (!$categoryName) {
        sendValidationError(['category_id' => 'Choose a valid ingredient category']);
    }

    $name = strtolower(trim((string) $categoryName));
    if (strpos($name, 'packaging') !== false) {
        $allowed = ['pcs', 'pack', 'packet', 'roll', 'bottle'];
    } elseif (strpos($name, 'culture') !== false || strpos($name, 'enzyme') !== false) {
        $allowed = ['kg', 'g', 'liter', 'ml', 'packet'];
    } else {
        $allowed = ['kg', 'g', 'liter', 'ml'];
    }

    if (!in_array($unit, $allowed, true)) {
        sendValidationError([
            'unit_of_measure' => "The stock unit '{$unit}' does not match the '{$categoryName}' category",
        ]);
    }
}

/**
 * A package quantity is always stored in the ingredient's stock unit.
 * This prevents invalid conversions such as a liter-based item using kg per pack.
 */
function ingredientPackageType($value, array $allowed, $field, $label) {
    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
        return null;
    }
    if (!in_array($normalized, $allowed, true)) {
        sendValidationError([$field => "Choose a supported {$label}"]);
    }
    return $normalized;
}

function ingredientContainersForPhysicalState($physicalState) {
    $byState = [
        'solid' => ['bag', 'sack', 'sachet', 'packet', 'drum', 'pail'],
        'liquid' => ['bottle', 'jug', 'drum', 'pail', 'tank'],
        'count' => ['pack', 'packet', 'bundle', 'roll'],
    ];
    return $byState[$physicalState] ?? [];
}

function validateIngredientContainerForPhysicalState($containerType, $physicalState) {
    if ($containerType === null) {
        return;
    }
    $allowed = ingredientContainersForPhysicalState($physicalState);
    if (!in_array($containerType, $allowed, true)) {
        sendValidationError([
            'container_type' => ucfirst($containerType) . " does not match a {$physicalState} material. Choose " . implode(', ', $allowed),
        ]);
    }
}

function convertIngredientPackageAmount($amount, $fromUnit, $toUnit) {
    $from = ingredientUnitKey($fromUnit);
    $to = ingredientUnitKey($toUnit);
    if ($from === $to) {
        return (float) $amount;
    }
    $factors = [
        'kg' => ['g' => 1000],
        'g' => ['kg' => 0.001],
        'liter' => ['ml' => 1000],
        'ml' => ['liter' => 0.001],
    ];
    if (!isset($factors[$from][$to])) {
        sendValidationError([
            'container_size_unit' => "The container amount cannot convert from {$fromUnit} to stock unit {$toUnit}",
        ]);
    }
    return (float) $amount * $factors[$from][$to];
}

function pluralIngredientPackage($type, $count) {
    if ((int) $count === 1) {
        return $type;
    }
    if ($type === 'box') {
        return 'boxes';
    }
    if ($type === 'case') {
        return 'cases';
    }
    return $type . 's';
}

function normalizeIngredientPackage(array $data, array $current = []) {
    $merged = array_merge($current, $data);
    $unit = normalizeIngredientStockUnit($merged['unit_of_measure'] ?? '');
    $physicalState = normalizeIngredientPhysicalState($merged['physical_state'] ?? '', $unit);
    $purchaseFormat = strtolower(trim((string) ($merged['purchase_format'] ?? '')));
    if (!in_array($purchaseFormat, ['direct_unit', 'packaged'], true)) {
        sendValidationError(['purchase_format' => 'Choose Direct or bulk, or Packaged']);
    }
    $containerType = ingredientPackageType(
        $merged['container_type'] ?? null,
        ['sack', 'bag', 'bottle', 'sachet', 'packet', 'roll', 'drum', 'pail', 'jug', 'tank', 'pack', 'bundle'],
        'container_type',
        'container type'
    );
    validateIngredientContainerForPhysicalState($containerType, $physicalState);
    $rawContainerSize = $merged['container_size_value'] ?? null;
    $containerSizeProvided = $rawContainerSize !== null && $rawContainerSize !== '';
    $containerSizeUnit = ingredientUnitKey($merged['container_size_unit'] ?? $unit);
    $purchasePackageType = ingredientPackageType(
        $merged['purchase_package_type'] ?? null,
        ['box', 'case', 'crate'],
        'purchase_package_type',
        'outer purchase package'
    );
    $rawContainersPerPackage = $merged['containers_per_purchase_package'] ?? null;
    $containersPerPackageProvided = $rawContainersPerPackage !== null && $rawContainersPerPackage !== '';
    $priceBasis = strtolower(trim((string) ($merged['purchase_price_basis'] ?? 'stock_unit')));
    $purchasePrice = array_key_exists('purchase_price', $data)
        ? $data['purchase_price']
        : ($merged['purchase_price'] ?? ($merged['unit_cost'] ?? null));
    $enforceWholePacks = !empty($merged['enforce_whole_packs']) ? 1 : 0;

    if ($purchaseFormat === 'direct_unit') {
        if ($purchasePackageType !== null || $containersPerPackageProvided || $enforceWholePacks === 1) {
            sendValidationError(['purchase_format' => 'Direct or bulk purchasing cannot include an outer package']);
        }
        if ($containerType !== null || $containerSizeProvided) {
            sendValidationError(['purchase_format' => 'Choose Packaged when an inner container is configured']);
        }
        if ($priceBasis !== 'stock_unit') {
            sendValidationError(['purchase_price_basis' => 'Direct or bulk supplier price must be per stock unit']);
        }
        if ($purchasePrice !== null && $purchasePrice !== '') {
            try {
                $purchasePrice = hfParseBusinessDecimal(
                    $purchasePrice,
                    'Supplier price',
                    0.01,
                    999999.99,
                    2
                );
            } catch (InvalidArgumentException $error) {
                sendValidationError(['purchase_price' => $error->getMessage()]);
            }
        }
        return [
            'purchase_format' => 'direct_unit',
            'container_type' => null,
            'container_size_value' => null,
            'container_size_unit' => null,
            'purchase_package_type' => null,
            'containers_per_purchase_package' => null,
            'purchase_price_basis' => 'stock_unit',
            'purchase_price' => $purchasePrice === null || $purchasePrice === '' ? null : round((float) $purchasePrice, 2),
            'unit_cost' => $purchasePrice === null || $purchasePrice === '' ? null : round((float) $purchasePrice, 2),
            'pack_size_value' => null,
            'pack_size_unit' => null,
            'pack_label' => null,
            'enforce_whole_packs' => 0,
        ];
    }

    if ($containerType === null && !$containerSizeProvided) {
        sendValidationError(['container_type' => 'Packaged purchasing requires an inner container and its amount']);
    }
    if ($containerType === null || !$containerSizeProvided) {
        sendValidationError(['container_size_value' => 'Choose the container and enter how much it contains']);
    }
    try {
        $rawContainerSize = hfParseBusinessDecimal(
            $rawContainerSize,
            'Quantity inside one container',
            0.001,
            1000000.000,
            3
        );
    } catch (InvalidArgumentException $error) {
        sendValidationError(['container_size_value' => $error->getMessage()]);
    }
    $containerSizeInStockUnit = convertIngredientPackageAmount($rawContainerSize, $containerSizeUnit, $unit);
    if ($containerSizeInStockUnit <= 0) {
        sendValidationError(['container_size_value' => 'Container amount must be greater than zero']);
    }

    if (($purchasePackageType !== null && !$containersPerPackageProvided)
        || ($purchasePackageType === null && $containersPerPackageProvided)) {
        sendValidationError(['purchase_package_type' => 'Complete both outer package fields, or leave both blank']);
    }
    $containersPerPackage = null;
    if ($purchasePackageType !== null) {
        try {
            $rawContainersPerPackage = hfParseBusinessInteger(
                $rawContainersPerPackage,
                'Containers in one package',
                1,
                1000000
            );
        } catch (InvalidArgumentException $error) {
            sendValidationError(['containers_per_purchase_package' => $error->getMessage()]);
        }
        $containersPerPackage = (int) $rawContainersPerPackage;
    }

    if (!in_array($priceBasis, ['stock_unit', 'container', 'purchase_package'], true)) {
        sendValidationError(['purchase_price_basis' => 'Choose what the supplier price covers']);
    }
    if ($priceBasis === 'purchase_package' && $purchasePackageType === null) {
        sendValidationError(['purchase_price_basis' => 'Add an outer package before pricing per package']);
    }
    if ($purchasePrice !== null && $purchasePrice !== '') {
        try {
            $purchasePrice = hfParseBusinessDecimal(
                $purchasePrice,
                'Supplier price',
                0.01,
                999999.99,
                2
            );
        } catch (InvalidArgumentException $error) {
            sendValidationError(['purchase_price' => $error->getMessage()]);
        }
    }

    $totalStockUnits = $containerSizeInStockUnit * ($containersPerPackage ?? 1);
    $normalizedUnitCost = null;
    if ($purchasePrice !== null && $purchasePrice !== '') {
        $denominator = $priceBasis === 'stock_unit'
            ? 1
            : ($priceBasis === 'container' ? $containerSizeInStockUnit : $totalStockUnits);
        $normalizedUnitCost = round((float) $purchasePrice / $denominator, 6);
    }

    $containerText = rtrim(rtrim(number_format((float) $rawContainerSize, 3, '.', ''), '0'), '.');
    $packLabel = "{$containerText} {$containerSizeUnit} {$containerType}";
    if ($purchasePackageType !== null) {
        $containerWord = pluralIngredientPackage($containerType, $containersPerPackage);
        $packLabel = "{$containersPerPackage} {$containerWord} x {$containerText} {$containerSizeUnit} {$purchasePackageType}";
    }

    return [
        'purchase_format' => 'packaged',
        'container_type' => $containerType,
        'container_size_value' => round((float) $rawContainerSize, 3),
        'container_size_unit' => $containerSizeUnit,
        'purchase_package_type' => $purchasePackageType,
        'containers_per_purchase_package' => $containersPerPackage,
        'purchase_price_basis' => $priceBasis,
        'purchase_price' => $purchasePrice === null || $purchasePrice === '' ? null : round((float) $purchasePrice, 2),
        'unit_cost' => $normalizedUnitCost,
        'pack_size_value' => round($totalStockUnits, 3),
        'pack_size_unit' => $unit,
        'pack_label' => $packLabel,
        'enforce_whole_packs' => $enforceWholePacks,
    ];
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

    $ingredient['suppliers'] = supplierCatalogGetIngredientSuppliers($conn, (int) $id);
    
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
    $usableStockSql = usableIngredientBatchStockSql('i.id', 'admin_low_ib');
    $stmt = $conn->query("
        SELECT i.*, {$usableStockSql} AS current_stock, i.current_stock AS current_stock_on_file, c.category_name
        FROM ingredients i
        LEFT JOIN ingredient_categories c ON i.category_id = c.id
        WHERE {$usableStockSql} <= " . StockRule::lowThresholdSql('i.reorder_point', 'i.minimum_stock') . " AND i.is_active = 1
        ORDER BY ({$usableStockSql} / NULLIF(" . StockRule::lowThresholdSql('i.reorder_point', 'i.minimum_stock') . ", 0)) ASC
    ");
    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendSuccess(['ingredients' => $ingredients]);
}

/**
 * Get ingredient statistics
 */
function getIngredientStatistics($conn) {
    $stats = [];
    $usableStockSql = usableIngredientBatchStockSql('i.id', 'admin_stats_ib');
    
    // Total ingredients
    $stmt = $conn->query("SELECT COUNT(*) as count FROM ingredients");
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Active ingredients
    $stmt = $conn->query("SELECT COUNT(*) as count FROM ingredients WHERE is_active = 1");
    $stats['active'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Low stock count
    $stmt = $conn->query("SELECT COUNT(*) as count FROM ingredients i WHERE {$usableStockSql} <= " . StockRule::lowThresholdSql('i.reorder_point', 'i.minimum_stock') . " AND i.is_active = 1");
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
    $stmt = $conn->query("SELECT SUM(({$usableStockSql}) * i.unit_cost) as total_value FROM ingredients i WHERE i.is_active = 1");
    $stats['total_value'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;
    
    sendSuccess($stats);
}

/**
 * Create new ingredient
 */
function validateIngredientPlanningNumbers(array &$data): void {
    $errors = [];
    foreach ([
        'minimum_stock' => 'Minimum stock',
        'reorder_point' => 'Reorder point',
        'maximum_stock' => 'Restocking target',
    ] as $field => $label) {
        if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
            continue;
        }
        try {
            $data[$field] = hfParseBusinessDecimal($data[$field], $label, 0.00, 99999999.99, 2);
        } catch (InvalidArgumentException $error) {
            $errors[$field] = $error->getMessage();
        }
    }

    foreach ([
        'lead_time_days' => ['Lead time', 0, 3650],
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

function createIngredient($conn, $currentUser) {
    $data = json_decode(file_get_contents('php://input'), true);
    $data = hfPlainTextFields(is_array($data) ? $data : [], [
        'ingredient_code' => [40, false],
        'ingredient_name' => [160, false],
        'unit_of_measure' => [40, false],
        'physical_state' => [20, false],
        'storage_location' => [160, false],
        'storage_requirements' => [1000, true],
    ]);
    $isActive = isset($data['is_active']) ? intval($data['is_active']) : 1;
    $supplierIds = supplierCatalogNormalizeSupplierIds($data['supplier_ids'] ?? []);
    validateIngredientPlanningNumbers($data);
    
    // Validation
    $errors = [];
    if (empty($data['ingredient_name'])) {
        $errors['ingredient_name'] = 'Ingredient name is required';
    }
    if (empty($data['unit_of_measure'])) {
        $errors['unit_of_measure'] = 'Unit of measure is required';
    }
    if (empty($data['category_id'])) {
        $errors['category_id'] = 'Ingredient category is required';
    }
    if (empty($data['physical_state'])) {
        $errors['physical_state'] = 'Choose whether the ingredient is solid, liquid, or counted';
    }
    $createReorderPoint = array_key_exists('reorder_point', $data)
        && $data['reorder_point'] !== null && $data['reorder_point'] !== ''
        ? $data['reorder_point']
        : null;
    $createMaximumStock = array_key_exists('maximum_stock', $data)
        && $data['maximum_stock'] !== null && $data['maximum_stock'] !== ''
        ? $data['maximum_stock']
        : null;
    $thresholdError = StockRule::thresholdValidationError(
        $data['minimum_stock'] ?? 0,
        $createReorderPoint,
        $createMaximumStock
    );
    if ($thresholdError !== null) {
        $errors['stock_levels'] = $thresholdError;
    }
    if (!empty($errors)) {
        sendValidationError($errors);
    }
    $data['unit_of_measure'] = normalizeIngredientStockUnit($data['unit_of_measure']);
    $data['physical_state'] = normalizeIngredientPhysicalState($data['physical_state'], $data['unit_of_measure']);
    validateIngredientPhysicalStateUnit($data['physical_state'], $data['unit_of_measure']);
    validateIngredientCategoryUnit($conn, $data['category_id'], $data['unit_of_measure']);
    if (ingredientCategoryIsPackaging($conn, $data['category_id'])) {
        $data['is_perishable'] = 0;
        $data['shelf_life_days'] = null;
    }
    // Supplier accreditation is managed after the ingredient exists, from the Supplier page.
    supplierCatalogValidateSupplierIds($conn, $supplierIds, false);
    
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
    
        $sql = "INSERT INTO ingredients (ingredient_code, ingredient_name, category_id, unit_of_measure, physical_state,
            minimum_stock, reorder_point, maximum_stock, lead_time_days, current_stock,
            storage_location, storage_requirements, shelf_life_days, is_perishable, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $data['ingredient_code'],
            $data['ingredient_name'],
            $data['category_id'] ?? null,
            $data['unit_of_measure'],
            $data['physical_state'],
            $data['minimum_stock'] ?? 0,
            $data['reorder_point'] ?? 0,
            $data['maximum_stock'] ?? null,
            $data['lead_time_days'] ?? 7,
            0,
            $data['storage_location'] ?? null,
            $data['storage_requirements'] ?? null,
            $data['shelf_life_days'] ?? null,
            isset($data['is_perishable']) ? intval($data['is_perishable']) : 1,
            $isActive
        ]);

        $newId = (int) $conn->lastInsertId();
        supplierCatalogSyncIngredient($conn, $newId, $supplierIds, (int) $currentUser['user_id']);
        $conn->commit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
    
    // Get the created ingredient
    $stmt = $conn->prepare("SELECT i.*, c.category_name FROM ingredients i LEFT JOIN ingredient_categories c ON i.category_id = c.id WHERE i.id = ?");
    $stmt->execute([$newId]);
    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
    $ingredient['suppliers'] = supplierCatalogGetIngredientSuppliers($conn, $newId);

    logAudit($currentUser['user_id'], 'CREATE', 'ingredients', $newId, null, $ingredient);
    
    sendSuccess(['ingredient' => $ingredient], 'Ingredient created successfully');
}

/**
 * Update ingredient
 */
function updateIngredient($conn, $id, $currentUser) {
    $data = json_decode(file_get_contents('php://input'), true);
    $data = hfPlainTextFields(is_array($data) ? $data : [], [
        'ingredient_name' => [160, false],
        'unit_of_measure' => [40, false],
        'physical_state' => [20, false],
        'storage_location' => [160, false],
        'storage_requirements' => [1000, true],
    ]);
    $hasSupplierIds = array_key_exists('supplier_ids', $data);
    validateIngredientPlanningNumbers($data);
    
    // Check if ingredient exists
    $stmt = $conn->prepare("SELECT * FROM ingredients WHERE id = ?");
    $stmt->execute([$id]);
    $currentIngredient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$currentIngredient) {
        sendError('Ingredient not found', 404);
    }

    $measurementTouched = array_key_exists('unit_of_measure', $data)
        || array_key_exists('category_id', $data)
        || array_key_exists('physical_state', $data);
    if ($measurementTouched) {
        $nextUnit = normalizeIngredientStockUnit($data['unit_of_measure'] ?? $currentIngredient['unit_of_measure']);
        $nextCategoryId = $data['category_id'] ?? $currentIngredient['category_id'];
        $nextPhysicalState = normalizeIngredientPhysicalState(
            $data['physical_state'] ?? ($currentIngredient['physical_state'] ?? ''),
            $nextUnit
        );
        validateIngredientPhysicalStateUnit($nextPhysicalState, $nextUnit);
        validateIngredientCategoryUnit($conn, $nextCategoryId, $nextUnit);
        if ($nextUnit !== normalizeIngredientStockUnit($currentIngredient['unit_of_measure'])) {
            $linkedOfferStmt = $conn->prepare("SELECT COUNT(*) FROM supplier_ingredients WHERE ingredient_id = ? AND is_active = 1");
            $linkedOfferStmt->execute([$id]);
            if ((int) $linkedOfferStmt->fetchColumn() > 0) {
                sendValidationError([
                    'unit_of_measure' => 'This ingredient already has supplier offers. Remove or update those offers before changing the warehouse stock unit.'
                ]);
            }
        }
        $data['unit_of_measure'] = $nextUnit;
        $data['physical_state'] = $nextPhysicalState;
    }

    $effectiveCategoryId = $data['category_id'] ?? $currentIngredient['category_id'];
    if (ingredientCategoryIsPackaging($conn, $effectiveCategoryId)) {
        $data['is_perishable'] = 0;
        $data['shelf_life_days'] = null;
    }

    $supplierIds = $hasSupplierIds
        ? supplierCatalogNormalizeSupplierIds($data['supplier_ids'])
        : supplierCatalogNormalizeSupplierIds(supplierCatalogGetIngredientSuppliers($conn, (int) $id));
    $nextIsActive = isset($data['is_active']) ? intval($data['is_active']) : intval($currentIngredient['is_active']);
    supplierCatalogValidateSupplierIds($conn, $supplierIds, false);

    if (array_key_exists('minimum_stock', $data)
        || array_key_exists('reorder_point', $data)
        || array_key_exists('maximum_stock', $data)) {
        $nextMinimumStock = array_key_exists('minimum_stock', $data)
            ? $data['minimum_stock']
            : $currentIngredient['minimum_stock'];
        $nextReorderPoint = array_key_exists('reorder_point', $data)
            ? (($data['reorder_point'] === null || $data['reorder_point'] === '') ? null : $data['reorder_point'])
            : ((float) ($currentIngredient['reorder_point'] ?? 0) > 0 ? $currentIngredient['reorder_point'] : null);
        $nextMaximumStock = array_key_exists('maximum_stock', $data)
            ? (($data['maximum_stock'] === null || $data['maximum_stock'] === '') ? null : $data['maximum_stock'])
            : ((float) ($currentIngredient['maximum_stock'] ?? 0) > 0 ? $currentIngredient['maximum_stock'] : null);
        $thresholdError = StockRule::thresholdValidationError(
            $nextMinimumStock,
            $nextReorderPoint,
            $nextMaximumStock
        );
        if ($thresholdError !== null) {
            sendValidationError(['stock_levels' => $thresholdError]);
        }
    }
    
    // Build update query
    $fields = [];
    $params = [];
    
    $allowedFields = ['ingredient_name', 'category_id', 'unit_of_measure', 'physical_state', 'minimum_stock',
                      'reorder_point', 'maximum_stock', 'lead_time_days',
                      'storage_location', 'storage_requirements', 'shelf_life_days', 'is_perishable', 'is_active',
                     ];
    
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $fields[] = "$field = ?";
            $params[] = in_array($field, ['is_active', 'is_perishable'], true)
                ? intval($data[$field])
                : $data[$field];
        }
    }
    
    if (empty($fields) && !$hasSupplierIds) {
        sendError('No fields to update', 400);
    }

    $conn->beginTransaction();
    try {
        if ($fields) {
            $params[] = $id;
            $sql = "UPDATE ingredients SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }
        if ($hasSupplierIds) {
            supplierCatalogSyncIngredient($conn, (int) $id, $supplierIds, (int) $currentUser['user_id']);
        }
        $conn->commit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
    
    // Get updated ingredient
    $stmt = $conn->prepare("SELECT i.*, c.category_name FROM ingredients i LEFT JOIN ingredient_categories c ON i.category_id = c.id WHERE i.id = ?");
    $stmt->execute([$id]);
    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
    $ingredient['suppliers'] = supplierCatalogGetIngredientSuppliers($conn, (int) $id);

    logAudit($currentUser['user_id'], 'UPDATE', 'ingredients', $id, $currentIngredient, $ingredient);
    
    sendSuccess(['ingredient' => $ingredient], 'Ingredient updated successfully');
}

/**
 * Archive ingredient by deactivating the row.
 * Ingredient rows are kept so inventory, production, and purchasing history remain traceable.
 */
function deleteIngredient($conn, $id, $currentUser) {
    // Check if ingredient exists
    $stmt = $conn->prepare("SELECT * FROM ingredients WHERE id = ?");
    $stmt->execute([$id]);
    $currentIngredient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$currentIngredient) {
        sendError('Ingredient not found', 404);
    }
    
    $stmt = $conn->prepare("UPDATE ingredients SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);

    logAudit(
        $currentUser['user_id'],
        'UPDATE',
        'ingredients',
        $id,
        $currentIngredient,
        array_merge($currentIngredient, ['is_active' => 0])
    );
    
    sendSuccess([
        'ingredient_id' => (int) $id,
        'is_active' => 0,
        'archived' => true
    ], 'Ingredient archived successfully');
}
