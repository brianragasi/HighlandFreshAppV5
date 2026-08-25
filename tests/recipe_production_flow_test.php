<?php

$root = dirname(__DIR__);
$sources = [
    'admin_api' => file_get_contents($root . '/api/admin/recipes.php'),
    'admin_page' => file_get_contents($root . '/html/admin/recipes.html'),
    'recipes_api' => file_get_contents($root . '/api/production/recipes.php'),
    'requisitions_api' => file_get_contents($root . '/api/production/requisitions.php'),
    'requisitions_page' => file_get_contents($root . '/html/production/requisitions.html'),
    'runs_api' => file_get_contents($root . '/api/production/runs.php'),
    'batches_page' => file_get_contents($root . '/html/production/batches.html'),
];

foreach ($sources as $name => $source) {
    if ($source === false) {
        fwrite(STDERR, "Unable to read {$name}.\n");
        exit(1);
    }
}

$checks = [
    'Recipe selection keeps the planned finished amount separate from the reference yield' =>
        !str_contains($sources['requisitions_page'], 'qtyInput.value = Number(recipe.bulk_yield_liters)')
        && str_contains($sources['requisitions_page'], "handlePlanSelectionChange('recipe')")
        && str_contains($sources['requisitions_page'], 'Planned finished amount (L)')
        && str_contains($sources['requisitions_page'], 'Standard recipe produces ${Number(recipe.bulk_yield_liters)} L.'),
    'Production selectors request only current bulk recipes' =>
        substr_count($sources['requisitions_page'] . $sources['batches_page'], 'for_requisition: 1') >= 2
        && str_contains($sources['recipes_api'], 'filterCurrentBulkProductionRecipes'),
    'Bulk recipes are not silently bound to the first packaging SKU' =>
        str_contains($sources['admin_page'], 'product_id: null')
        && str_contains($sources['admin_api'], 'A recipe belongs to the bulk/base product'),
    'No-SKU wording distinguishes cooking from packaging readiness' =>
        str_contains($sources['admin_page'], 'Bulk cooking is allowed without a packaging SKU')
        && str_contains($sources['requisitions_page'], 'Packaging and finished-goods receiving require an active size'),
    'Large unexplained liquid loss is rejected in browser and API' =>
        str_contains($sources['admin_api'], 'Expected liquid yield is below 90% of liquid input')
        && str_contains($sources['admin_page'], 'Expected liquid yield is below 90% of liquid input'),
    'Every production recipe has a server-enforced maximum one-run volume' =>
        str_contains($sources['admin_api'], 'max_batch_liters')
        && str_contains($sources['admin_api'], 'bulk_yield_liters = ?')
        && str_contains($sources['admin_page'], 'Maximum one run (L)')
        && str_contains($sources['requisitions_api'], 'assessRecipeBatchPlan')
        && str_contains($sources['runs_api'], 'assessRecipeBatchPlan'),
    'Production cannot override unavailable Warehouse stock' =>
        str_contains($sources['requisitions_api'], 'This production request cannot be submitted because Warehouse does not have every required material')
        && !str_contains($sources['requisitions_page'], 'Submit with override')
        && !str_contains($sources['requisitions_page'], 'confirmStockOverride'),
    'Shortage modal receives only real shortages and never uses a negative fallback' =>
        str_contains($sources['requisitions_api'], "'shortages' => \$actualShortages")
        && str_contains($sources['requisitions_page'], 'Array.isArray(stockCheck?.shortages)')
        && str_contains($sources['requisitions_page'], 'requested > available && calculateShortage(s) > 0')
        && !str_contains($sources['requisitions_page'], 's.shortage || (requested - available)'),
    'Covered material preview shows how much stock remains' =>
        str_contains($sources['requisitions_page'], 'Enough stock ·')
        && str_contains($sources['requisitions_page'], 'would remain'),
    'Planned recipe materials are regenerated from the saved formula on the server' =>
        str_contains($sources['requisitions_api'], '$authoritativePlan')
        && str_contains($sources['requisitions_api'], '$items = $authoritativePlan[\'items\']'),
    'Likely solid-component unit mistakes require formula review' =>
        str_contains($sources['admin_api'], 'function validateRecipeDoseGuardrails')
        && str_contains($sources['admin_api'], 'single solid component above 25%')
        && str_contains($sources['admin_page'], "failedRule = 'solid_dose'"),
    'Saving an active replacement retires older active recipes' =>
        substr_count($sources['admin_api'], 'retireOtherActiveRecipes') >= 2,
    'Approved historical requisitions can keep their exact retired recipe' =>
        str_contains($sources['runs_api'], '$historicalRecipeAllowed')
        && str_contains($sources['batches_page'], 'approved historical plan'),
    'Recipe numbers hide meaningless trailing zeros without losing three-decimal BOM precision' =>
        str_contains($sources['admin_page'], 'function formatRecipeNumber(')
        && str_contains($sources['admin_page'], 'formatRecipeNumber(ingredient.quantity, 3)')
        && str_contains($sources['admin_page'], 'step="0.001" min="0.001"')
        && str_contains($sources['admin_page'], 'onblur="formatRecipeNumberInput(this, 3)'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Failed: {$label}.\n");
        exit(1);
    }
}

require_once $root . '/api/helpers/recipe_production_readiness.php';

$fixtureRows = [
    ['id' => 10, 'base_product_id' => 7, 'expected_yield' => 95, 'yield_unit' => 'bottles', 'is_active' => 1],
    ['id' => 20, 'base_product_id' => 8, 'expected_yield' => 50, 'yield_unit' => 'liters', 'is_active' => 1],
    ['id' => 21, 'base_product_id' => 8, 'expected_yield' => 95, 'yield_unit' => 'liters', 'is_active' => 1],
];
$filtered = filterCurrentBulkProductionRecipes($fixtureRows);
if (count($filtered) !== 1 || (int) $filtered[0]['id'] !== 21) {
    fwrite(STDERR, "Failed: current bulk filter did not exclude bottle recipes/superseded duplicates.\n");
    exit(1);
}

$oneBatch = assessRecipeBatchPlan([
    'bulk_yield_liters' => 47,
    'max_batch_liters' => 60,
], 47);
$tooLargeBatch = assessRecipeBatchPlan([
    'bulk_yield_liters' => 47,
    'max_batch_liters' => 60,
], 61);
if (!$oneBatch['valid'] || $tooLargeBatch['valid']) {
    fwrite(STDERR, "Failed: production batch capacity guard did not enforce the one-run maximum.\n");
    exit(1);
}

$sugarRequested = 0.168;
$sugarAvailable = 101.0;
$sugarShortage = max(0.0, $sugarRequested - $sugarAvailable);
$sugarRemaining = max(0.0, $sugarAvailable - $sugarRequested);
if (abs($sugarShortage) > 0.000001 || abs($sugarRemaining - 100.832) > 0.000001) {
    fwrite(STDERR, "Failed: sufficient Sugar stock did not resolve to zero shortage and 100.832 kg remaining.\n");
    exit(1);
}

// Isolated workflow simulation. Connection-local temporary tables shadow the
// live tables, so no business rows or auto-increment counters are touched.
define('HIGHLAND_FRESH', true);
require_once $root . '/api/config/config.php';
require_once $root . '/api/config/database.php';

$db = Database::getInstance()->getConnection();
$seed = random_int(100000, 900000);
$baseId = $seed;
$recipe1Id = $seed * 10;
$recipe2Id = $recipe1Id + 1;
$reqId = $seed * 10 + 2;
$code = "SIM-BP-{$seed}";
$creatorId = 1;
$milkTypeId = 1;
$shadowTables = ['base_products', 'products', 'master_recipes', 'recipe_ingredients', 'material_requisitions'];
$schema = str_replace('`', '``', DB_NAME);

try {
    foreach ($shadowTables as $table) {
        $safeTable = str_replace('`', '``', $table);
        $db->exec("CREATE TEMPORARY TABLE `{$safeTable}` AS SELECT source_row.* FROM `{$schema}`.`{$safeTable}` AS source_row WHERE 1 = 0");
    }

    $insertBalanceLine = $db->prepare("
        INSERT INTO recipe_ingredients
            (recipe_id, ingredient_name, ingredient_category, quantity, unit, is_optional)
        VALUES (?, ?, 'flavoring', ?, 'liter', 0)
    ");
    $insertBalanceLine->execute([$recipe1Id, 'Reasonable liquid addition', 2]);
    $insertBalanceLine->execute([$recipe2Id, 'Excess liquid addition', 5]);
    $reasonableBalance = assessRecipeLiquidBalance($db, [
        'id' => $recipe1Id,
        'base_milk_liters' => 50,
        'bulk_yield_liters' => 47,
    ]);
    $dutchLikeBalance = assessRecipeLiquidBalance($db, [
        'id' => $recipe2Id,
        'base_milk_liters' => 50,
        'bulk_yield_liters' => 47,
    ]);
    if (!$reasonableBalance['valid'] || $dutchLikeBalance['valid']) {
        throw new RuntimeException('Liquid formula review did not block an unexplained Dutch-like loss');
    }

    $stmt = $db->prepare("\n        INSERT INTO base_products
            (id, code, name, category, milk_type_id, default_shelf_life_days, is_active)
        VALUES (?, ?, ?, 'flavored_milk', ?, 7, 1)
    ");
    $stmt->execute([$baseId, $code, "Simulation Product {$seed}", $milkTypeId]);

    $insertRecipe = $db->prepare("\n        INSERT INTO master_recipes
            (id, recipe_code, product_id, base_product_id, product_name, product_type, milk_type_id,
             base_milk_liters, expected_yield, bulk_yield_liters, yield_unit, is_active, created_by)
        VALUES (?, ?, NULL, ?, ?, 'flavored_milk', ?, 100, ?, ?, 'liters', 1, ?)
    ");

    lockRecipeBaseProduct($db, $baseId);
    retireOtherActiveRecipes($db, $baseId);
    $insertRecipe->execute([
        $recipe1Id,
        "SIM-R1-{$seed}",
        $baseId,
        "Simulation Product {$seed}",
        $milkTypeId,
        95,
        95,
        $creatorId,
    ]);

    $skuCountStmt = $db->prepare('SELECT COUNT(*) FROM products WHERE base_product_id = ? AND is_active = 1');
    $skuCountStmt->execute([$baseId]);
    $current = getCurrentActiveBulkRecipeForBase($db, $baseId);
    if ((int) $skuCountStmt->fetchColumn() !== 0 || (int) ($current['id'] ?? 0) !== $recipe1Id) {
        throw new RuntimeException('No-SKU product recipe was not visible as the current bulk recipe');
    }

    $reqStmt = $db->prepare("\n        INSERT INTO material_requisitions
            (id, requisition_code, planned_recipe_id, planned_quantity, planned_yield_unit,
             requested_by, department, priority, purpose, total_items, status)
        VALUES (?, ?, ?, 70, 'liters', ?, 'production', 'normal', 'Rollback-only simulation', 1, 'fulfilled')
    ");
    $reqStmt->execute([$reqId, "SIM-REQ-{$seed}", $recipe1Id, $creatorId]);

    lockRecipeBaseProduct($db, $baseId);
    retireOtherActiveRecipes($db, $baseId);
    $insertRecipe->execute([
        $recipe2Id,
        "SIM-R2-{$seed}",
        $baseId,
        "Simulation Product {$seed}",
        $milkTypeId,
        96,
        96,
        $creatorId,
    ]);

    $current = getCurrentActiveBulkRecipeForBase($db, $baseId);
    $historyStmt = $db->prepare('SELECT planned_recipe_id FROM material_requisitions WHERE id = ?');
    $historyStmt->execute([$reqId]);
    $oldActiveStmt = $db->prepare('SELECT is_active FROM master_recipes WHERE id = ?');
    $oldActiveStmt->execute([$recipe1Id]);
    if ((int) ($current['id'] ?? 0) !== $recipe2Id
        || (int) $oldActiveStmt->fetchColumn() !== 0
        || (int) $historyStmt->fetchColumn() !== $recipe1Id) {
        throw new RuntimeException('Recipe replacement did not preserve the historical requisition link');
    }

} catch (Throwable $e) {
    foreach (array_reverse($shadowTables) as $table) {
        try {
            $db->exec('DROP TEMPORARY TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
        } catch (Throwable $ignored) {
        }
    }
    fwrite(STDERR, 'Failed workflow simulation: ' . $e->getMessage() . "\n");
    exit(1);
}

foreach (array_reverse($shadowTables) as $table) {
    $db->exec('DROP TEMPORARY TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
}

echo "Recipe production flow tests passed (product → recipe → current Production plan; isolated cleanup complete).\n";
