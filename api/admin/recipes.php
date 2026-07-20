<?php
/**
 * Admin Recipes API
 * CRUD for master_recipes — product master is source of truth for
 * name, category, and milk type. Recipes own process params + BOM.
 */

require_once __DIR__ . '/../bootstrap.php';

Auth::requireAuth();

$conn = Database::getInstance()->getConnection();

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
 * Map product.category → master_recipes.product_type (aligned enums).
 * Legacy bottled_milk is treated as pasteurized_milk.
 */
function mapCategoryToRecipeType($category) {
    $category = strtolower(trim((string) $category));
    $map = [
        'pasteurized_milk' => 'pasteurized_milk',
        'bottled_milk' => 'pasteurized_milk',
        'flavored_milk' => 'flavored_milk',
        'yogurt' => 'yogurt',
        'cheese' => 'cheese',
        'butter' => 'butter',
        'cream' => 'cream',
        'milk_bar' => 'milk_bar',
    ];
    return $map[$category] ?? 'pasteurized_milk';
}

/**
 * Map ingredient category name → recipe_ingredients.ingredient_category enum.
 */
function mapIngredientCategory($name) {
    $n = strtolower(trim((string) $name));
    $allowed = ['milk', 'sugar', 'flavoring', 'powder', 'culture', 'rennet', 'salt', 'packaging', 'other'];
    if (in_array($n, $allowed, true)) {
        return $n;
    }
    foreach ($allowed as $key) {
        if ($key !== 'other' && $n !== '' && strpos($n, $key) !== false) {
            return $key;
        }
    }
    return 'other';
}

/**
 * Resolve product master fields from base_product_id and/or product_id.
 * Returns array: base_product_id, product_id, product_name, product_type, milk_type_id,
 *                category, milk_type_name, shelf_life_days, skus[]
 */
function resolveProductMaster(PDO $conn, $data) {
    $baseProductId = isset($data['base_product_id']) && $data['base_product_id'] !== ''
        ? (int) $data['base_product_id'] : null;
    $productId = isset($data['product_id']) && $data['product_id'] !== ''
        ? (int) $data['product_id'] : null;

    // Prefer explicit base product (liquid identity)
    if ($baseProductId) {
        try {
            $stmt = $conn->prepare("
                SELECT bp.id, bp.code, bp.name, bp.category, bp.milk_type_id,
                       bp.default_shelf_life_days, bp.description,
                       mt.type_name AS milk_type_name
                FROM base_products bp
                LEFT JOIN milk_types mt ON mt.id = bp.milk_type_id
                WHERE bp.id = ?
            ");
            $stmt->execute([$baseProductId]);
            $base = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $base = null;
        }
        if (!$base) {
            return [null, ['base_product_id' => 'Base product not found']];
        }

        // Optional SKU under this base; pick first active if product_id not given
        $sku = null;
        if ($productId) {
            $s = $conn->prepare("
                SELECT id, product_code, product_name, category, milk_type_id, unit_size, unit_measure, is_active
                FROM products WHERE id = ? AND base_product_id = ?
            ");
            $s->execute([$productId, $baseProductId]);
            $sku = $s->fetch(PDO::FETCH_ASSOC);
            if (!$sku) {
                return [null, ['product_id' => 'SKU does not belong to the selected base product']];
            }
        } else {
            $s = $conn->prepare("
                SELECT id, product_code, product_name, category, milk_type_id, unit_size, unit_measure, is_active
                FROM products WHERE base_product_id = ? AND is_active = 1
                ORDER BY unit_size DESC, id ASC LIMIT 1
            ");
            $s->execute([$baseProductId]);
            $sku = $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $skus = [];
        $skuList = $conn->prepare("
            SELECT id, product_code, product_name, variant, unit_size, unit_measure, is_active
            FROM products WHERE base_product_id = ? ORDER BY unit_size DESC, id ASC
        ");
        $skuList->execute([$baseProductId]);
        $skus = $skuList->fetchAll(PDO::FETCH_ASSOC);

        // Milk type: base first, then SKU fallback
        $milkTypeId = $base['milk_type_id'] !== null && $base['milk_type_id'] !== ''
            ? (int) $base['milk_type_id']
            : ($sku['milk_type_id'] ?? null);
        $milkTypeName = $base['milk_type_name'] ?? null;
        if ($milkTypeId && !$milkTypeName) {
            $mt = $conn->prepare("SELECT type_name FROM milk_types WHERE id = ?");
            $mt->execute([$milkTypeId]);
            $milkTypeName = $mt->fetchColumn() ?: null;
        }

        $category = $base['category'] ?: ($sku['category'] ?? 'pasteurized_milk');

        return [[
            'base_product_id' => (int) $base['id'],
            'product_id' => $sku ? (int) $sku['id'] : null,
            'product_name' => $base['name'],
            'product_type' => mapCategoryToRecipeType($category),
            'category' => $category,
            'milk_type_id' => $milkTypeId ? (int) $milkTypeId : null,
            'milk_type_name' => $milkTypeName,
            'shelf_life_days' => (int) ($base['default_shelf_life_days'] ?? 7),
            'description_product' => $base['description'] ?? null,
            'skus' => $skus,
        ], null];
    }

    // Fallback: product_id only (SKU) → resolve base via products.base_product_id
    if ($productId) {
        $stmt = $conn->prepare("
            SELECT p.id, p.product_code, p.product_name, p.category, p.milk_type_id,
                   p.base_product_id, p.shelf_life_days, p.unit_size, p.unit_measure,
                   mt.type_name AS milk_type_name,
                   bp.name AS base_name, bp.category AS base_category,
                   bp.milk_type_id AS base_milk_type_id, bp.default_shelf_life_days
            FROM products p
            LEFT JOIN milk_types mt ON mt.id = p.milk_type_id
            LEFT JOIN base_products bp ON bp.id = p.base_product_id
            WHERE p.id = ?
        ");
        $stmt->execute([$productId]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            return [null, ['product_id' => 'Product not found']];
        }

        $category = $p['base_category'] ?: $p['category'];
        $milkTypeId = $p['base_milk_type_id'] ?: $p['milk_type_id'];
        $name = $p['base_name'] ?: preg_replace('/\s+\d+(\.\d+)?\s*(ml|l|g|kg)\b/i', '', $p['product_name']);
        $name = trim($name) ?: $p['product_name'];

        return [[
            'base_product_id' => $p['base_product_id'] ? (int) $p['base_product_id'] : null,
            'product_id' => (int) $p['id'],
            'product_name' => $name,
            'product_type' => mapCategoryToRecipeType($category),
            'category' => $category,
            'milk_type_id' => $milkTypeId ? (int) $milkTypeId : null,
            'milk_type_name' => $p['milk_type_name'] ?? null,
            'shelf_life_days' => (int) ($p['default_shelf_life_days'] ?? $p['shelf_life_days'] ?? 7),
            'description_product' => null,
            'skus' => [],
        ], null];
    }

    return [null, ['base_product_id' => 'Select a base product (liquid) for this recipe']];
}

/**
 * Normalize BOM lines: lock name/unit/category from ingredients master.
 */
function normalizeRecipeIngredients(PDO $conn, array $lines) {
    $normalized = [];
    $errors = [];

    foreach ($lines as $idx => $line) {
        $ingId = isset($line['ingredient_id']) ? (int) $line['ingredient_id'] : 0;
        $qty = isset($line['quantity']) ? (float) $line['quantity']
            : (isset($line['quantity_required']) ? (float) $line['quantity_required'] : 0);

        if ($ingId <= 0) {
            $errors["ingredients.$idx.ingredient_id"] = 'Ingredient is required';
            continue;
        }
        if ($qty <= 0) {
            $errors["ingredients.$idx.quantity"] = 'Quantity must be greater than 0';
            continue;
        }

        $stmt = $conn->prepare("
            SELECT i.id, i.ingredient_name, i.unit_of_measure, i.category_id,
                   ic.category_name
            FROM ingredients i
            LEFT JOIN ingredient_categories ic ON ic.id = i.category_id
            WHERE i.id = ?
        ");
        $stmt->execute([$ingId]);
        $ing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ing) {
            $errors["ingredients.$idx.ingredient_id"] = 'Ingredient not found';
            continue;
        }

        $unit = $ing['unit_of_measure'] ?: 'kg';
        $normalized[] = [
            'ingredient_id' => (int) $ing['id'],
            'ingredient_name' => $ing['ingredient_name'],
            'ingredient_category' => mapIngredientCategory($ing['category_name'] ?? 'other'),
            'quantity' => round($qty, 3),
            'unit' => $unit,
            'notes' => isset($line['notes']) ? substr((string) $line['notes'], 0, 255) : '',
        ];
    }

    return [$normalized, $errors];
}

function insertRecipeIngredients(PDO $conn, $recipeId, array $ingredients) {
    if (empty($ingredients)) {
        return;
    }
    $sql = "INSERT INTO recipe_ingredients
            (recipe_id, ingredient_id, ingredient_name, ingredient_category, quantity, unit, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    foreach ($ingredients as $ingredient) {
        $stmt->execute([
            $recipeId,
            $ingredient['ingredient_id'],
            $ingredient['ingredient_name'],
            $ingredient['ingredient_category'],
            $ingredient['quantity'],
            $ingredient['unit'],
            $ingredient['notes'] ?? '',
        ]);
    }
}

/**
 * Mass-balance guardrail: process input (base milk + BOM) must be ≥ 90% of expected yield.
 * Also enforces milk ≥ 90% of yield when yield is liquid liters (catches 10 L milk / 95 L yield).
 *
 * @return array field => message errors
 */
function validateRecipeMassBalance($baseMilkLiters, $expectedYield, $yieldUnit, array $ingredients) {
    $errors = [];
    $milk = (float) $baseMilkLiters;
    $yield = (float) $expectedYield;
    if ($milk <= 0 || $yield <= 0) {
        return $errors; // other validators already catch these
    }

    $bomTotal = 0.0;
    foreach ($ingredients as $line) {
        $q = (float) ($line['quantity'] ?? 0);
        if ($q > 0) {
            $bomTotal += $q;
        }
    }
    $processInput = $milk + $bomTotal;
    $minRatio = 0.90;

    if ($processInput + 1e-9 < $yield * $minRatio) {
        $errors['mass_balance'] = 'Configuration Error: Total ingredient volume does not match the expected yield. '
            . "Input {$processInput} (milk {$milk} + BOM {$bomTotal}) is below 90% of yield {$yield}.";
    }

    $yu = strtolower((string) $yieldUnit);
    if (in_array($yu, ['liters', 'liter', 'l', 'lt'], true) && $milk + 1e-9 < $yield * $minRatio) {
        $errors['base_milk_liters'] = 'Configuration Error: Total ingredient volume does not match the expected yield. '
            . "Base milk ({$milk} L) is far below expected yield ({$yield} L).";
    }

    return $errors;
}

function loadRecipeIngredients(PDO $conn, $recipeId) {
    $stmt = $conn->prepare("
        SELECT ri.id, ri.recipe_id, ri.ingredient_id, ri.ingredient_name,
               ri.ingredient_category, ri.quantity, ri.unit, ri.notes,
               i.unit_of_measure AS master_unit,
               i.ingredient_name AS master_name
        FROM recipe_ingredients ri
        LEFT JOIN ingredients i ON i.id = ri.ingredient_id
        WHERE ri.recipe_id = ?
        ORDER BY ri.id ASC
    ");
    $stmt->execute([$recipeId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function enrichRecipeRow(PDO $conn, array $recipe) {
    // Prefer live product master for display of inherited fields
    if (!empty($recipe['base_product_id'])) {
        try {
            $stmt = $conn->prepare("
                SELECT bp.name, bp.category, bp.milk_type_id, bp.default_shelf_life_days,
                       mt.type_name AS milk_type_name
                FROM base_products bp
                LEFT JOIN milk_types mt ON mt.id = bp.milk_type_id
                WHERE bp.id = ?
            ");
            $stmt->execute([(int) $recipe['base_product_id']]);
            $bp = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $bp = null;
        }
        if ($bp) {
            $recipe['product_name'] = $bp['name'];
            $recipe['category'] = $bp['category'];
            $recipe['product_type_display'] = $bp['category'];
            $recipe['milk_type_id'] = $bp['milk_type_id'] !== null ? (int) $bp['milk_type_id'] : $recipe['milk_type_id'];
            $recipe['milk_type_name'] = $bp['milk_type_name'] ?: ($recipe['milk_type_name'] ?? null);
            $recipe['shelf_life_days_product'] = (int) ($bp['default_shelf_life_days'] ?? 7);

            $skuStmt = $conn->prepare("
                SELECT id, product_code, product_name, variant, unit_size, unit_measure, is_active
                FROM products WHERE base_product_id = ? ORDER BY unit_size DESC, id ASC
            ");
            $skuStmt->execute([(int) $recipe['base_product_id']]);
            $recipe['skus'] = $skuStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Normalize legacy bottled_milk for UI filters
    if (($recipe['product_type'] ?? '') === 'bottled_milk') {
        $recipe['product_type'] = 'pasteurized_milk';
    }

    return $recipe;
}

function getRecipes($conn) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;

    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $productType = isset($_GET['product_type']) ? $_GET['product_type'] : '';
    $isActive = isset($_GET['is_active']) ? $_GET['is_active'] : '';

    // Check if base_products table exists before joining
    $hasBpTable = false;
    try {
        $conn->query('SELECT id FROM base_products LIMIT 0');
        $hasBpTable = true;
    } catch (Throwable $e) { /* table missing */ }

    $bpJoin = $hasBpTable ? 'LEFT JOIN base_products bp ON bp.id = r.base_product_id' : '';
    $bpSelect = $hasBpTable ? 'bp.name AS base_product_name, bp.category AS base_category' : 'NULL AS base_product_name, NULL AS base_category';

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
        // Accept both pasteurized_milk and legacy bottled_milk filter
        if ($productType === 'pasteurized_milk') {
            $where[] = "r.product_type IN ('pasteurized_milk', 'bottled_milk')";
        } else {
            $where[] = "r.product_type = ?";
            $params[] = $productType;
        }
    }

    if ($isActive !== '') {
        $where[] = "r.is_active = ?";
        $params[] = intval($isActive);
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    $countSql = "SELECT COUNT(*) as total FROM master_recipes r $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    $sql = "SELECT r.*, mt.type_name as milk_type_name,
                   u.full_name as created_by_name,
                   {$bpSelect}
            FROM master_recipes r
            LEFT JOIN milk_types mt ON r.milk_type_id = mt.id
            LEFT JOIN users u ON r.created_by = u.id
            {$bpJoin}
            $whereClause
            ORDER BY r.product_name ASC
            LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($recipes as &$recipe) {
        $recipe = enrichRecipeRow($conn, $recipe);
        $recipe['ingredients'] = loadRecipeIngredients($conn, $recipe['id']);
        $recipe['ingredient_count'] = count($recipe['ingredients']);
    }
    unset($recipe);

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

    $recipe = enrichRecipeRow($conn, $recipe);
    $recipe['ingredients'] = loadRecipeIngredients($conn, $id);

    sendSuccess(['recipe' => $recipe]);
}

function getRecipeStatistics($conn) {
    $stats = [];

    $stmt = $conn->query("SELECT COUNT(*) as count FROM master_recipes");
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $conn->query("SELECT COUNT(*) as count FROM master_recipes WHERE is_active = 1");
    $stats['active'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $conn->query("SELECT product_type, COUNT(*) as count FROM master_recipes GROUP BY product_type");
    $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendSuccess($stats);
}

function createRecipe($conn) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $user = Auth::getCurrentUser();

    list($master, $masterErrors) = resolveProductMaster($conn, $data);
    $errors = $masterErrors ?: [];

    if (empty($data['base_milk_liters']) || (float) $data['base_milk_liters'] < 1) {
        $errors['base_milk_liters'] = 'Base milk liters must be at least 1';
    }
    if (!isset($data['expected_yield']) || $data['expected_yield'] === '' || (float) $data['expected_yield'] < 1) {
        $errors['expected_yield'] = 'Expected yield must be at least 1';
    }

    $yieldUnit = $data['yield_unit'] ?? 'liters';
    $allowedYieldUnits = ['liters', 'kg', 'units'];
    if (!in_array($yieldUnit, $allowedYieldUnits, true)) {
        $errors['yield_unit'] = 'Yield unit must be liters, kg, or units';
    }

    $ingredientsIn = isset($data['ingredients']) && is_array($data['ingredients']) ? $data['ingredients'] : [];
    list($ingredients, $ingErrors) = normalizeRecipeIngredients($conn, $ingredientsIn);
    $errors = array_merge($errors, $ingErrors);

    // Mass-balance guardrail (same rules as admin recipes UI)
    $massErrors = validateRecipeMassBalance(
        (float) ($data['base_milk_liters'] ?? 0),
        (float) ($data['expected_yield'] ?? 0),
        $yieldUnit,
        $ingredients
    );
    $errors = array_merge($errors, $massErrors);

    if (!empty($errors)) {
        sendValidationError($errors);
    }

    if (empty($data['recipe_code'])) {
        $stmt = $conn->query("SELECT MAX(id) as max_id FROM master_recipes");
        $maxId = $stmt->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
        $data['recipe_code'] = 'RCP-' . str_pad($maxId + 1, 4, '0', STR_PAD_LEFT);
    }

    $conn->beginTransaction();

    try {
        $sql = "INSERT INTO master_recipes (
                    recipe_code, product_id, base_product_id, product_name, product_type,
                    milk_type_id, description, base_milk_liters, expected_yield, yield_unit,
                    pasteurization_temp, pasteurization_time_mins, cooling_temp, special_instructions,
                    is_active, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $data['recipe_code'],
            $master['product_id'],
            $master['base_product_id'],
            $master['product_name'],
            $master['product_type'],
            $master['milk_type_id'],
            $data['description'] ?? null,
            (float) $data['base_milk_liters'],
            (float) $data['expected_yield'],
            $yieldUnit,
            isset($data['pasteurization_temp']) ? (float) $data['pasteurization_temp'] : 81.00,
            isset($data['pasteurization_time_mins']) ? (int) $data['pasteurization_time_mins'] : 15,
            isset($data['cooling_temp']) ? (float) $data['cooling_temp'] : 4.00,
            $data['special_instructions'] ?? null,
            isset($data['is_active']) ? intval($data['is_active']) : 1,
            $user['user_id'] ?? $user['id'] ?? null
        ]);

        $recipeId = $conn->lastInsertId();
        insertRecipeIngredients($conn, $recipeId, $ingredients);

        $conn->commit();

        $stmt = $conn->prepare("SELECT * FROM master_recipes WHERE id = ?");
        $stmt->execute([$recipeId]);
        $recipe = enrichRecipeRow($conn, $stmt->fetch(PDO::FETCH_ASSOC));
        $recipe['ingredients'] = loadRecipeIngredients($conn, $recipeId);

        sendSuccess(['recipe' => $recipe], 'Recipe created successfully');
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function updateRecipe($conn, $id) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    $stmt = $conn->prepare("SELECT * FROM master_recipes WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        sendError('Recipe not found', 404);
    }

    // Re-resolve product master if a product link is provided; otherwise keep existing FKs
    $hasProductLink = !empty($data['base_product_id']) || !empty($data['product_id']);
    $master = null;
    $errors = [];

    if ($hasProductLink) {
        list($master, $masterErrors) = resolveProductMaster($conn, $data);
        if ($masterErrors) {
            $errors = array_merge($errors, $masterErrors);
        }
    } else {
        // Refresh derived fields from existing FKs when possible
        list($master, $masterErrors) = resolveProductMaster($conn, [
            'base_product_id' => $existing['base_product_id'],
            'product_id' => $existing['product_id'],
        ]);
        if (!$master) {
            // Fallback to existing denormalized values (legacy rows)
            $master = [
                'base_product_id' => $existing['base_product_id'],
                'product_id' => $existing['product_id'],
                'product_name' => $existing['product_name'],
                'product_type' => mapCategoryToRecipeType($existing['product_type']),
                'milk_type_id' => $existing['milk_type_id'],
            ];
        }
    }

    if (isset($data['base_milk_liters']) && (float) $data['base_milk_liters'] < 1) {
        $errors['base_milk_liters'] = 'Base milk liters must be at least 1';
    }
    if (isset($data['expected_yield']) && (float) $data['expected_yield'] < 1) {
        $errors['expected_yield'] = 'Expected yield must be at least 1';
    }

    $ingredients = null;
    if (isset($data['ingredients']) && is_array($data['ingredients'])) {
        list($ingredients, $ingErrors) = normalizeRecipeIngredients($conn, $data['ingredients']);
        $errors = array_merge($errors, $ingErrors);
    }

    // Mass balance uses submitted values when present, else existing recipe values
    $milkForBalance = isset($data['base_milk_liters'])
        ? (float) $data['base_milk_liters']
        : (float) $existing['base_milk_liters'];
    $yieldForBalance = isset($data['expected_yield'])
        ? (float) $data['expected_yield']
        : (float) $existing['expected_yield'];
    $yieldUnitForBalance = $data['yield_unit'] ?? $existing['yield_unit'] ?? 'liters';
    $ingsForBalance = $ingredients;
    if ($ingsForBalance === null) {
        $ingsForBalance = loadRecipeIngredients($conn, $id);
    }
    $massErrors = validateRecipeMassBalance($milkForBalance, $yieldForBalance, $yieldUnitForBalance, $ingsForBalance);
    $errors = array_merge($errors, $massErrors);

    if (!empty($errors)) {
        sendValidationError($errors);
    }

    $conn->beginTransaction();

    try {
        // Recipe-owned fields only; product_name/type/milk always from master when resolved
        $yieldUnit = $data['yield_unit'] ?? $existing['yield_unit'] ?? 'liters';
        if (!in_array($yieldUnit, ['liters', 'kg', 'units'], true)) {
            $yieldUnit = 'liters';
        }

        $sql = "UPDATE master_recipes SET
                    product_id = ?,
                    base_product_id = ?,
                    product_name = ?,
                    product_type = ?,
                    milk_type_id = ?,
                    description = ?,
                    base_milk_liters = ?,
                    expected_yield = ?,
                    yield_unit = ?,
                    pasteurization_temp = ?,
                    pasteurization_time_mins = ?,
                    cooling_temp = ?,
                    special_instructions = ?,
                    is_active = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $master['product_id'],
            $master['base_product_id'],
            $master['product_name'],
            $master['product_type'],
            $master['milk_type_id'],
            array_key_exists('description', $data) ? $data['description'] : $existing['description'],
            isset($data['base_milk_liters']) ? (float) $data['base_milk_liters'] : (float) $existing['base_milk_liters'],
            isset($data['expected_yield']) ? (float) $data['expected_yield'] : (float) $existing['expected_yield'],
            $yieldUnit,
            isset($data['pasteurization_temp']) ? (float) $data['pasteurization_temp'] : $existing['pasteurization_temp'],
            isset($data['pasteurization_time_mins']) ? (int) $data['pasteurization_time_mins'] : $existing['pasteurization_time_mins'],
            isset($data['cooling_temp']) ? (float) $data['cooling_temp'] : $existing['cooling_temp'],
            array_key_exists('special_instructions', $data) ? $data['special_instructions'] : $existing['special_instructions'],
            isset($data['is_active']) ? intval($data['is_active']) : (int) $existing['is_active'],
            $id
        ]);

        if ($ingredients !== null) {
            $del = $conn->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?");
            $del->execute([$id]);
            insertRecipeIngredients($conn, $id, $ingredients);
        }

        $conn->commit();

        $stmt = $conn->prepare("SELECT * FROM master_recipes WHERE id = ?");
        $stmt->execute([$id]);
        $recipe = enrichRecipeRow($conn, $stmt->fetch(PDO::FETCH_ASSOC));
        $recipe['ingredients'] = loadRecipeIngredients($conn, $id);

        sendSuccess(['recipe' => $recipe], 'Recipe updated successfully');
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function deleteRecipe($conn, $id) {
    $stmt = $conn->prepare("SELECT id FROM master_recipes WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendError('Recipe not found', 404);
    }

    $stmt = $conn->prepare("UPDATE master_recipes SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);

    sendSuccess(null, 'Recipe retired successfully');
}
