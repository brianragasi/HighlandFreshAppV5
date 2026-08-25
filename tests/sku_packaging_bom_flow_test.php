<?php

$root = dirname(__DIR__);
$sources = [
    'products_api' => file_get_contents($root . '/api/admin/products.php'),
    'products_page' => file_get_contents($root . '/html/admin/products.html'),
    'packaging_helper' => file_get_contents($root . '/api/helpers/sku_packaging_bom.php'),
    'recipes_api' => file_get_contents($root . '/api/admin/recipes.php'),
    'recipes_page' => file_get_contents($root . '/html/admin/recipes.html'),
    'production_recipes_api' => file_get_contents($root . '/api/production/recipes.php'),
    'yield_helpers' => file_get_contents($root . '/api/production/helpers/yield_helpers.php'),
    'runs_api' => file_get_contents($root . '/api/production/runs.php'),
    'workbench' => file_get_contents($root . '/html/production/run-workbench.html'),
    'warehouse_requisitions_api' => file_get_contents($root . '/api/warehouse/raw/requisitions.php'),
    'warehouse_requisitions_page' => file_get_contents($root . '/html/warehouse/raw/requisitions.html'),
];

$checks = [
    'Admin Products exposes a SKU packaging BOM editor' =>
        str_contains($sources['products_api'], "action === 'packaging_bom'")
        && str_contains($sources['products_page'], 'SKU Packaging BOM')
        && str_contains($sources['products_page'], 'quantity_per_unit')
        && str_contains($sources['products_page'], 'function escapeAttr'),
    'Roll materials use coverage instead of asking users for a confusing fraction' =>
        str_contains($sources['products_page'], '1 roll covers')
        && str_contains($sources['products_page'], 'units_per_stock_unit')
        && str_contains($sources['products_page'], 'updatePackagingBomRollPreview')
        && str_contains($sources['packaging_helper'], 'roundPackagingRequirementForStock'),
    'Packaging BOM hides meaningless trailing zeros without losing small quantities' =>
        str_contains($sources['products_page'], 'function formatProductNumber(')
        && str_contains($sources['products_page'], 'formatProductNumber(item.quantity_per_unit, 6)')
        && str_contains($sources['products_page'], 'formatProductNumber(item.waste_percent ?? 0, 2)')
        && str_contains($sources['products_page'], 'onblur="formatProductNumberInput(this, 6);'),
    'Flavor is modeled on the base formula rather than the packaging SKU' =>
        str_contains($sources['products_page'], 'Base Product / Formula Name')
        && str_contains($sources['products_page'], 'A different flavor must be a new base product with its own recipe')
        && !str_contains($sources['products_page'], 'Variant/Flavor')
        && !str_contains($sources['products_page'], 'sku_edit_variant')
        && !str_contains($sources['products_page'], 'sku_variant_'),
    'Base-linked SKUs cannot use variant text to bypass duplicate-size checks' =>
        str_contains($sources['products_api'], "\$data['variant'] = null")
        && str_contains($sources['products_api'], "unset(\$data['variant'])")
        && str_contains($sources['products_api'], 'Legacy variant text does')
        && str_contains($sources['products_page'], '`${packagingType}::${normalized.family}:${normalized.value.toFixed(4)}`'),
    'Bulk recipe API rejects packaging-category components' =>
        str_contains($sources['recipes_api'], 'Packaging materials belong to the SKU Packaging BOM')
        && str_contains($sources['recipes_page'], "!category.includes('packag')"),
    'Recipe selectors identify the standard yield as a reference' =>
        str_contains($sources['production_recipes_api'], 'Standard yield: ')
        && str_contains($sources['recipes_page'], 'Standard yield (reference)'),
    'Production resolves packaging BOMs on catalog SKUs' =>
        str_contains($sources['yield_helpers'], "['packaging_bom']")
        && str_contains($sources['yield_helpers'], "['packaging_bom_ready']"),
    'Production creates a separate Warehouse packaging request' =>
        str_contains($sources['runs_api'], 'calculateSkuPackagingRequirements')
        && str_contains($sources['runs_api'], "case 'request_packaging'")
        && str_contains($sources['runs_api'], "request_type = 'packaging'")
        && str_contains($sources['runs_api'], "'status' => 'approved'"),
    'Warehouse physically issues packaging and owns the stock deduction' =>
        str_contains($sources['warehouse_requisitions_api'], "\$effectiveItemType === 'packaging'")
        && str_contains($sources['warehouse_requisitions_api'], "issueIngredient(\$db, \$item['item_id'], \$issuedQty, \$id, \$currentUser, 'packaging')")
        && str_contains($sources['warehouse_requisitions_page'], 'Packaging handover'),
    'Production completion requires fulfillment and does not deduct twice' =>
        str_contains($sources['runs_api'], "\$packReq['status'] !== 'fulfilled'")
        && str_contains($sources['runs_api'], 'stock issued earlier by Warehouse')
        && !str_contains($sources['runs_api'], 'consumeSkuPackagingRequirements('),
    'Workbench shows requirements, requests Warehouse issue, and has no manual-SKU fallback' =>
        str_contains($sources['workbench'], 'Packaging materials from SKU BOM')
        && str_contains($sources['workbench'], 'calculatePackagingMaterialRequirements')
        && str_contains($sources['workbench'], 'Send packaging request to Warehouse')
        && str_contains($sources['workbench'], "packaging_request?.status !== 'fulfilled'")
        && str_contains($sources['workbench'], 'packagingFormInitInFlight')
        && str_contains($sources['workbench'], 'lossSubmissionInFlight')
        && str_contains($sources['workbench'], 'completionSubmissionInFlight')
        && !str_contains($sources['workbench'], 'Manual entry (no catalog sizes)'),
    'Production chooses the intended SKU before a multi-size recommendation is applied' =>
        str_contains($sources['workbench'], 'Choose the intended product size')
        && str_contains($sources['workbench'], 'getPackagingBasisVolumeMl')
        && str_contains($sources['workbench'], 'Math.floor(availableMl / sizeMl)')
        && str_contains($sources['runs_api'], 'validateSkuPackagingPlanVolume'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Failed: {$label}.\n");
        exit(1);
    }
}

define('HIGHLAND_FRESH', true);
require_once $root . '/api/config/config.php';
require_once $root . '/api/config/database.php';
require_once $root . '/api/helpers/sku_packaging_bom.php';

$db = Database::getInstance()->getConnection();
$schema = str_replace('`', '``', DB_NAME);
$temporaryTables = [
    'ingredients',
    'sku_packaging_bom_items',
    'ingredient_consumption',
    'inventory_transactions',
];

try {
    $fixtureStmt = $db->query("
        SELECT mr.id AS recipe_id, p.id AS product_id
        FROM master_recipes mr
        JOIN products p ON p.base_product_id = mr.base_product_id AND p.is_active = 1
        WHERE mr.is_active = 1 AND mr.base_product_id IS NOT NULL
          AND LOWER(COALESCE(p.base_unit, '')) NOT IN ('bottle', 'bottles')
        ORDER BY mr.id DESC, p.id ASC LIMIT 1
    ");
    $fixture = $fixtureStmt->fetch(PDO::FETCH_ASSOC);
    $packaging = $db->query("
        SELECT i.* FROM ingredients i
        JOIN ingredient_categories ic ON ic.id = i.category_id
        WHERE i.is_active = 1 AND LOWER(ic.category_name) LIKE '%packag%'
        ORDER BY i.id LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    $nonPackaging = $db->query("
        SELECT i.* FROM ingredients i
        JOIN ingredient_categories ic ON ic.id = i.category_id
        WHERE i.is_active = 1 AND LOWER(ic.category_name) NOT LIKE '%packag%'
        ORDER BY i.id LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    $rollPackaging = $db->query("
        SELECT i.* FROM ingredients i
        JOIN ingredient_categories ic ON ic.id = i.category_id
        WHERE i.is_active = 1
          AND LOWER(ic.category_name) LIKE '%packag%'
          AND LOWER(i.unit_of_measure) IN ('roll', 'rolls')
        ORDER BY i.id LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    if (!$fixture || !$packaging || !$nonPackaging || !$rollPackaging) {
        throw new RuntimeException('Required recipe/SKU/ingredient fixtures were not found');
    }

    // Connection-local temporary tables shadow live stock and audit tables.
    foreach ($temporaryTables as $table) {
        $safe = str_replace('`', '``', $table);
        $db->exec("CREATE TEMPORARY TABLE `{$safe}` AS SELECT source_row.* FROM `{$schema}`.`{$safe}` AS source_row WHERE 1 = 0");
    }

    $columns = array_keys($packaging);
    $quotedColumns = implode(', ', array_map(static fn($column) => '`' . str_replace('`', '``', $column) . '`', $columns));
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $ingredientInsert = $db->prepare("INSERT INTO ingredients ({$quotedColumns}) VALUES ({$placeholders})");
    $ingredientFixtures = [];
    foreach ([$packaging, $nonPackaging, $rollPackaging] as $ingredientFixture) {
        $ingredientFixture['current_stock'] = 200;
        $ingredientFixture['reserved_stock'] = 0;
        $ingredientFixture['available_stock'] = 200;
        $ingredientFixtures[(int) $ingredientFixture['id']] = $ingredientFixture;
    }
    foreach ($ingredientFixtures as $ingredientFixture) {
        $ingredientInsert->execute(array_values($ingredientFixture));
    }

    [$normalizedRoll, $rollErrors] = normalizeSkuPackagingBomInput($db, [[
        'ingredient_id' => (int) $rollPackaging['id'],
        'quantity_per_unit' => 999,
        'units_per_stock_unit' => 10,
        'waste_percent' => 0,
    ]]);
    if (!empty($rollErrors) || abs((float) ($normalizedRoll[0]['quantity_per_unit'] ?? 0) - 0.1) > 0.000001) {
        throw new RuntimeException('Roll coverage was not converted to per-finished-unit usage');
    }
    [, $missingCoverageErrors] = normalizeSkuPackagingBomInput($db, [[
        'ingredient_id' => (int) $rollPackaging['id'],
        'quantity_per_unit' => 0.1,
        'waste_percent' => 0,
    ]]);
    if (empty($missingCoverageErrors)) {
        throw new RuntimeException('Roll usage was accepted without an explicit coverage value');
    }
    if (abs(roundPackagingRequirementForStock(0.0105, 'roll') - 0.02) > 0.000001) {
        throw new RuntimeException('Small roll consumption was allowed to disappear below stock precision');
    }

    $fiveLiterTenBottlePlan = validateSkuPackagingPlanVolume([
        ['size_ml' => 500, 'quantity' => 10],
    ], 5000);
    $fiveLiterElevenBottlePlan = validateSkuPackagingPlanVolume([
        ['size_ml' => 500, 'quantity' => 11],
    ], 5000);
    if (!$fiveLiterTenBottlePlan['valid'] || $fiveLiterElevenBottlePlan['valid']) {
        throw new RuntimeException('Five liters was not limited to ten 500 mL finished units');
    }

    [, $multiBottleErrors] = normalizeSkuPackagingBomInput($db, [[
        'ingredient_id' => (int) $packaging['id'],
        'quantity_per_unit' => 5,
        'waste_percent' => 0,
    ]]);
    if (empty($multiBottleErrors)) {
        throw new RuntimeException('A bottle/cap/label quantity above one was accepted per finished product');
    }
    $incompleteBottleProfile = assessSkuPackagingBomReadiness('bottle', [
        ['ingredient_name' => '500 mL Bottle'],
        ['ingredient_name' => '28 mm Cap'],
    ]);
    $completeBottleProfile = assessSkuPackagingBomReadiness('bottle', [
        ['ingredient_name' => '500 mL Bottle'],
        ['ingredient_name' => '28 mm Cap'],
        ['ingredient_name' => '500 mL Label'],
    ]);
    if ($incompleteBottleProfile['ready'] || !$completeBottleProfile['ready']) {
        throw new RuntimeException('Bottled SKU readiness did not require bottle, closure, and label');
    }

    $db->prepare("
        INSERT INTO sku_packaging_bom_items
            (id, product_id, ingredient_id, quantity_per_unit, waste_percent, unit, is_active)
        VALUES (1, ?, ?, 1.000000, 5.00, ?, 1)
    ")->execute([
        (int) $fixture['product_id'],
        (int) $packaging['id'],
        $packaging['unit_of_measure'],
    ]);

    $calculated = calculateSkuPackagingRequirements($db, (int) $fixture['recipe_id'], [[
        'product_id' => (int) $fixture['product_id'],
        'product_name' => 'Untrusted client name',
        'size_ml' => 999999,
        'quantity' => 100,
    ]]);
    $required = (float) ($calculated['requirements'][0]['quantity_required'] ?? 0);
    if (!$calculated['success'] || abs($required - 105.0) > 0.0001) {
        throw new RuntimeException('SKU allocation did not calculate quantity × BOM × waste allowance');
    }
    if (($calculated['items'][0]['product_name'] ?? '') === 'Untrusted client name'
        || (float) ($calculated['items'][0]['size_ml'] ?? 0) === 999999.0) {
        throw new RuntimeException('Client product identity was not replaced by product-master data');
    }

    $db->exec('UPDATE sku_packaging_bom_items SET waste_percent = 0 WHERE id = 1');
    $tenUnitPlan = calculateSkuPackagingRequirements($db, (int) $fixture['recipe_id'], [[
        'product_id' => (int) $fixture['product_id'],
        'quantity' => 10,
    ]]);
    if (!$tenUnitPlan['success']
        || abs((float) ($tenUnitPlan['requirements'][0]['quantity_required'] ?? 0) - 10.0) > 0.0001) {
        throw new RuntimeException('Ten finished units did not request ten one-per-unit packaging pieces');
    }
    $db->exec('UPDATE sku_packaging_bom_items SET waste_percent = 5 WHERE id = 1');

    $duplicateSku = calculateSkuPackagingRequirements($db, (int) $fixture['recipe_id'], [
        ['product_id' => (int) $fixture['product_id'], 'quantity' => 10],
        ['product_id' => (int) $fixture['product_id'], 'quantity' => 10],
    ]);
    if ($duplicateSku['success'] || !str_contains(implode(' ', $duplicateSku['errors']), 'more than once')) {
        throw new RuntimeException('Duplicate packaging SKU lines were not rejected');
    }

    [, $badCategoryErrors] = normalizeSkuPackagingBomInput($db, [[
        'ingredient_id' => (int) $nonPackaging['id'],
        'quantity_per_unit' => 1,
        'waste_percent' => 0,
    ]]);
    if (empty($badCategoryErrors)) {
        throw new RuntimeException('A non-packaging ingredient was accepted into the SKU packaging BOM');
    }

    $db->beginTransaction();
    $consumed = consumeSkuPackagingRequirements(
        $db,
        987654,
        'TEST-BATCH',
        $calculated['requirements'],
        1
    );
    $stockAfter = (float) $db->query('SELECT current_stock FROM ingredients WHERE id = ' . (int) $packaging['id'])->fetchColumn();
    $auditCount = (int) $db->query("SELECT COUNT(*) FROM inventory_transactions WHERE reference_type = 'production_run' AND reference_id = 987654")->fetchColumn();
    if (count($consumed) !== 1 || abs($stockAfter - 95.0) > 0.0001 || $auditCount !== 1) {
        throw new RuntimeException('Packaging stock consumption/audit transaction was not recorded correctly');
    }
    $db->rollBack();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    foreach (array_reverse($temporaryTables) as $table) {
        try {
            $db->exec('DROP TEMPORARY TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
        } catch (Throwable $ignored) {
        }
    }
    fwrite(STDERR, 'Failed packaging BOM flow: ' . $e->getMessage() . "\n");
    exit(1);
}

foreach (array_reverse($temporaryTables) as $table) {
    $db->exec('DROP TEMPORARY TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
}

echo "SKU packaging BOM flow tests passed (allocation → Warehouse request/issue → completion without double deduction; isolated cleanup complete).\n";
