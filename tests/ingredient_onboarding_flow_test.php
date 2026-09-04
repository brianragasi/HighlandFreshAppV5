<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$adminApi = file_get_contents($root . '/api/admin/ingredients.php');
$adminPage = file_get_contents($root . '/html/admin/ingredients.html');
$onboarding = file_get_contents($root . '/api/helpers/ingredient_onboarding.php');
$packagingRoles = file_get_contents($root . '/api/helpers/ingredient_packaging_roles.php');
$validationSupport = file_get_contents($root . '/api/helpers/stock_validation_support.php');
$validationApi = file_get_contents($root . '/api/warehouse/raw/stock_validations.php');
$warehouseApi = file_get_contents($root . '/api/warehouse/raw/ingredients.php');
$warehousePage = file_get_contents($root . '/html/warehouse/raw/ingredients.html');
$purchasingPage = file_get_contents($root . '/html/purchasing/purchase_orders.html');

$checks = [
    'Admin must choose the real starting-stock situation' =>
        str_contains($adminPage, 'Do we currently have physical stock of this material?')
        && str_contains($adminPage, 'value="purchase_required"')
        && str_contains($adminPage, 'value="opening_stock"')
        && str_contains($adminApi, "normalizeIngredientInitialStockRoute"),
    'Packaging materials require an explicit BOM component type' =>
        str_contains($adminPage, 'Packaging Type *')
        && str_contains($adminPage, 'id="packaging_role"')
        && str_contains($adminApi, "'packaging_role'")
        && str_contains($packagingRoles, "['container', 'closure', 'label', 'secondary', 'other']"),
    'Legacy Warehouse/Purchasing ingredient creation cannot bypass Admin routing' =>
        str_contains($warehouseApi, 'New ingredients must be configured in Admin → Ingredients')
        && !str_contains($warehouseApi, 'Only GM or Purchaser can create ingredients'),
    'Admin never types an opening physical quantity' =>
        !str_contains($adminPage, 'id="current_stock"')
        && str_contains($adminApi, "'pending_count'"),
    'Known empty stock bypasses Warehouse and creates one Purchasing demand' =>
        str_contains($adminApi, "'admin_new_material'")
        && str_contains($adminApi, "'new_material_purchase'")
        && str_contains($adminApi, 'INSERT INTO stock_validation_items')
        && str_contains($adminApi, "'purchaser'")
        && !str_contains($adminApi, 'createStockValidation('),
    'The demand source is distinguished from a Warehouse shelf count' =>
        str_contains($validationSupport, "DEFAULT 'warehouse_count'")
        && str_contains($validationApi, "'New material — no opening stock'")
        && str_contains($purchasingPage, "pr.source_type === 'admin_new_material'")
        && str_contains($purchasingPage, 'New material registered by Admin — no opening stock'),
    'Unlinked new materials remain visible while supplier linking is explained' =>
        str_contains($purchasingPage, 'New materials without a supplier stay visible')
        && str_contains($purchasingPage, 'Ask the GM or Admin to connect the supplier and material first.')
        && str_contains($adminPage, 'Admin → Suppliers'),
    'Existing physical stock creates a Warehouse count action' =>
        str_contains($adminApi, "'warehouse_raw'")
        && str_contains($adminApi, "'ingredient_opening_count'")
        && str_contains($warehousePage, 'Opening stock count required')
        && str_contains($warehousePage, 'Record Opening Stock'),
    'Opening-stock onboarding cannot also enter ordinary low-stock validation' =>
        str_contains($warehouseApi, "i.initial_stock_route = 'opening_stock'")
        && str_contains($warehouseApi, "i.onboarding_status IN ('pending_count', 'under_review')"),
    'Warehouse submission moves the task under review without making stock usable' =>
        str_contains($warehouseApi, "markIngredientOnboardingStatus(\$db, \$ingredientId, 'under_review')")
        && str_contains($warehousePage, "sourceTypeSelect.value = isOnboardingCount ? 'opening_balance'")
        && str_contains($warehousePage, 'It remains unusable until the required reviews finish.'),
    'GM decision completes or returns the one-time onboarding task' =>
        str_contains(file_get_contents($root . '/api/helpers/ingredient_opening_stock.php'), "'pending_count'")
        && str_contains(file_get_contents($root . '/api/helpers/ingredient_opening_stock.php'), "'completed'")
        && str_contains($onboarding, "initial_stock_route = 'opening_stock'"),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}
if ($failed) {
    fwrite(STDERR, "Ingredient onboarding flow checks failed.\n");
    exit(1);
}

require_once $root . '/api/helpers/ingredient_onboarding.php';
if (normalizeIngredientInitialStockRoute(' purchase_required ') !== 'purchase_required'
    || normalizeIngredientInitialStockRoute('opening_stock') !== 'opening_stock'
    || normalizeIngredientInitialStockRoute('guess') !== '') {
    throw new RuntimeException('Ingredient starting-stock route normalization is unsafe');
}

echo "Ingredient onboarding flow checks passed.\n";
