<?php
$root = dirname(__DIR__);
$sources = [
    'products_page' => file_get_contents($root . '/html/admin/products.html'),
    'ingredients_page' => file_get_contents($root . '/html/admin/ingredients.html'),
    'dashboard_page' => file_get_contents($root . '/html/admin/dashboard.html'),
    'products_api' => file_get_contents($root . '/api/admin/products.php'),
    'ingredients_api' => file_get_contents($root . '/api/admin/ingredients.php'),
    'sets_api' => file_get_contents($root . '/api/admin/packaging_sets.php'),
    'sets_helper' => file_get_contents($root . '/api/helpers/packaging_sets.php'),
];

$checks = [
    'Admin separates raw ingredients from packaging materials' =>
        str_contains($sources['ingredients_page'], 'Raw Ingredients')
        && str_contains($sources['ingredients_page'], 'Packaging Materials')
        && str_contains($sources['ingredients_api'], "materialScope === 'packaging'")
        && str_contains($sources['ingredients_api'], "materialScope === 'raw'"),
    'Packaging workspace provides role-specific creation actions' =>
        str_contains($sources['ingredients_page'], 'Add Bottle / Container')
        && str_contains($sources['ingredients_page'], 'Add Cap / Closure')
        && str_contains($sources['ingredients_page'], 'Add Label')
        && str_contains($sources['ingredients_page'], 'Add Secondary Packaging'),
    'Guided setup exposes all requested steps and draft save' =>
        str_contains($sources['products_page'], 'Product Setup Wizard')
        && str_contains($sources['products_page'], 'Product details')
        && str_contains($sources['products_page'], 'Package sizes')
        && str_contains($sources['products_page'], 'Review &amp; Activate')
        && str_contains($sources['products_page'], 'Save as Draft'),
    'Readiness count and exact continuation actions are present' =>
        str_contains($sources['products_page'], 'of 4 setup steps complete')
        && str_contains($sources['products_page'], 'Add package size')
        && str_contains($sources['products_page'], 'Create recipe')
        && str_contains($sources['products_page'], 'Add packaging'),
    'Reusable packaging sets persist and copy into SKU BOMs' =>
        str_contains($sources['sets_helper'], 'CREATE TABLE IF NOT EXISTS packaging_sets')
        && str_contains($sources['sets_helper'], 'CREATE TABLE IF NOT EXISTS packaging_set_items')
        && str_contains($sources['sets_api'], "action === 'apply'")
        && str_contains($sources['sets_api'], 'replaceSkuPackagingBom'),
    'Set application validates equivalent SKU capacity' =>
        str_contains($sources['sets_api'], 'packagingCanonicalSize')
        && str_contains($sources['sets_api'], 'this SKU is'),
    'Suspicious liter capacity requires an explicit human choice' =>
        str_contains($sources['ingredients_page'], 'Did you mean')
        && str_contains($sources['ingredients_page'], 'data-capacity-choice="milliliters"')
        && str_contains($sources['ingredients_page'], 'data-capacity-choice="keep"')
        && str_contains($sources['ingredients_page'], 'data-capacity-choice="back"')
        && str_contains($sources['ingredients_api'], 'confirm_unusual_capacity'),
    'Dashboard surfaces unfinished product setup' =>
        str_contains($sources['dashboard_page'], 'Finish Product Setup')
        && str_contains($sources['dashboard_page'], 'loadProductSetupReadiness'),
    'Inactive product drafts remain visible to Admin setup' =>
        !str_contains($sources['products_api'], "WHERE bp.is_active = 1\n            ORDER BY bp.id DESC")
        && str_contains($sources['products_page'], "g.is_active === false")
        && str_contains($sources['products_page'], "document.getElementById('base_is_active').checked = false"),
    'Optional milk type cannot send the old zero sentinel into a foreign key' =>
        str_contains($sources['products_api'], 'function normalizeProductMilkType')
        && str_contains($sources['products_api'], '$raw === 0')
        && !str_contains($sources['products_page'], '<option value="0">N/A (Not Applicable)</option>')
        && str_contains($sources['products_page'], 'function loadMilkTypeOptions()'),
    'Recipe continuation opens the requested draft product without guessing its milk type' =>
        str_contains(file_get_contents($root . '/html/admin/recipes.html'), "params.get('base_product_id')")
        && str_contains(file_get_contents($root . '/html/admin/recipes.html'), 'openEditor(null, parseInt(requestedBaseProductId, 10))')
        && !str_contains($sources['products_page'], 'inferMilkType(g.name, g.category)')
        && str_contains($sources['products_page'], "|| 'Not set'")
        && str_contains($sources['products_page'], 'Product details & milk type'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) {
        $failed++;
    }
}
exit($failed === 0 ? 0 : 1);
