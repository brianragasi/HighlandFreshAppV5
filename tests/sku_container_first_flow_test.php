<?php

$root = dirname(__DIR__);
require_once $root . '/api/helpers/ingredient_packaging_roles.php';
require_once $root . '/api/helpers/sku_packaging_bom.php';

$sources = [
    'ingredient_api' => file_get_contents($root . '/api/admin/ingredients.php'),
    'product_api' => file_get_contents($root . '/api/admin/products.php'),
    'ingredient_page' => file_get_contents($root . '/html/admin/ingredients.html'),
    'product_page' => file_get_contents($root . '/html/admin/products.html'),
];

$checks = [
    'Packaging master records explicit capacity' =>
        str_contains($sources['ingredient_api'], 'applyIngredientPackagingCapacity')
        && str_contains($sources['ingredient_page'], 'id="packaging_capacity_value"'),
    'Bottle SKU payload trusts the selected container' =>
        str_contains($sources['product_api'], 'function applyPrimaryContainerToSkuPayload')
        && str_contains($sources['product_api'], '$data[\'unit_size\'] = (float) $container[\'packaging_capacity_value\']'),
    'Bottle SKU creation automatically records its container BOM item' =>
        str_contains($sources['product_api'], 'function syncSkuPrimaryContainer')
        && str_contains($sources['product_api'], 'syncSkuPrimaryContainer($conn, $productId, $primaryContainer)'),
    'SKU form selects a container and locks derived size' =>
        str_contains($sources['product_page'], 'sku-primary-container')
        && str_contains($sources['product_page'], 'sizeInput.readOnly = isBottle'),
    'BOM save protects the SKU primary container' =>
        str_contains($sources['product_api'], "'items.container'")
        && str_contains($sources['product_api'], 'submittedContainers !== [$primaryContainerId]'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Failed: {$label}.\n");
        exit(1);
    }
}

$matching = validateSkuPackagingBomSizes('bottle', 500, 'ml', [[
    'ingredient_name' => 'Opaque client container name',
    'packaging_role' => 'container',
    'packaging_capacity_value' => 500,
    'packaging_capacity_unit' => 'ml',
]]);
$mismatching = validateSkuPackagingBomSizes('bottle', 250, 'ml', [[
    'ingredient_name' => 'Opaque client container name',
    'packaging_role' => 'container',
    'packaging_capacity_value' => 500,
    'packaging_capacity_unit' => 'ml',
]]);

if ($matching !== [] || count($mismatching) !== 1) {
    fwrite(STDERR, "Failed: explicit container capacity must drive size validation.\n");
    exit(1);
}

echo "SKU container-first flow tests passed.\n";
