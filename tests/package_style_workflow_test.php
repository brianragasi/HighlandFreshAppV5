<?php

$root = dirname(__DIR__);
require_once $root . '/api/helpers/ingredient_packaging_roles.php';
require_once $root . '/api/helpers/sku_packaging_bom.php';

$sources = [
    'ingredients' => file_get_contents($root . '/html/admin/ingredients.html'),
    'products' => file_get_contents($root . '/html/admin/products.html'),
    'ingredient_api' => file_get_contents($root . '/api/admin/ingredients.php'),
    'product_api' => file_get_contents($root . '/api/admin/products.php'),
];

$checks = [
    'Packaging page begins with one component chooser' =>
        str_contains($sources['ingredients'], 'id="packagingChooserModal"')
        && str_contains($sources['ingredients'], "choosePackagingForm('printed_pouch')")
        && str_contains($sources['ingredients'], 'Show planning &amp; storage settings'),
    'Packaging form is persisted separately from BOM role' =>
        str_contains($sources['ingredient_api'], "'packaging_form' => [30, false]")
        && str_contains($sources['ingredient_api'], 'ingredientPackagingFormRole($packagingForm)'),
    'Product form exposes explicit real-world package styles' =>
        str_contains($sources['products'], '<option value="printed_pouch">Printed pouch / sachet</option>')
        && str_contains($sources['products'], '<option value="plain_pouch">Plain pouch + label</option>')
        && str_contains($sources['products'], '<option value="wrapped_block">Wrapped bar / block</option>')
        && !str_contains($sources['products'], '<option value="piece">Loose piece (legacy)</option>'),
    'Legacy SKUs must choose a real package before opening the BOM' =>
        str_contains($sources['products'], 'skuHasConfiguredPackage')
        && str_contains($sources['products'], 'Set package first')
        && str_contains($sources['product_api'], 'Choose the SKU package style and actual primary package'),
    'BOM choices are narrowed to the next compatible component' =>
        str_contains($sources['products'], 'filterPackagingBomMaterials')
        && str_contains($sources['products'], 'packagingBomNextMissingRole')
        && str_contains($sources['products'], 'No unused ${component} material is available'),
    'Blank BOM row cannot be mistaken for the primary package' =>
        str_contains($sources['products'], 'packagingBomPrimaryContainerId > 0')
        && str_contains($sources['products'], 'Number(item.ingredient_id || 0) === packagingBomPrimaryContainerId'),
    'Milk Bar is accepted by Product master data' =>
        str_contains($sources['product_api'], "'milk_bar' => 'MB'")
        && str_contains($sources['products'], 'Milk Bar / Frozen Dairy Bar'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Failed: {$label}.\n");
        exit(1);
    }
}

$printedPouch = assessSkuPackagingBomReadiness('printed_pouch', [[
    'ingredient_name' => 'WakoKabalo Printed Pouch 320 mL',
    'packaging_role' => 'container',
    'packaging_capacity_value' => 320,
    'packaging_capacity_unit' => 'ml',
]], 320, 'ml');
$plainPouchIncomplete = assessSkuPackagingBomReadiness('plain_pouch', [[
    'ingredient_name' => 'Plain Pouch 320 mL',
    'packaging_role' => 'container',
    'packaging_capacity_value' => 320,
    'packaging_capacity_unit' => 'ml',
]], 320, 'ml');
$plainPouchComplete = assessSkuPackagingBomReadiness('plain_pouch', [[
    'ingredient_name' => 'Plain Pouch 320 mL',
    'packaging_role' => 'container',
    'packaging_capacity_value' => 320,
    'packaging_capacity_unit' => 'ml',
], [
    'ingredient_name' => 'WakoKabalo Label 320 mL',
    'packaging_role' => 'label',
    'packaging_capacity_value' => 320,
    'packaging_capacity_unit' => 'ml',
]], 320, 'ml');

if (!$printedPouch['ready'] || $plainPouchIncomplete['ready'] || !$plainPouchComplete['ready']) {
    fwrite(STDERR, "Failed: pouch package-style readiness rules are incorrect.\n");
    exit(1);
}

if (ingredientPackagingFormRole('printed_pouch') !== 'container'
    || ingredientPackagingFormRole('cap_lid') !== 'closure'
    || ingredientPackagingFormRole('carton_case') !== 'secondary') {
    fwrite(STDERR, "Failed: physical packaging forms do not map to stable BOM roles.\n");
    exit(1);
}

echo "Package style workflow tests passed.\n";
