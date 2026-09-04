<?php

$root = dirname(__DIR__);
$sources = [
    'page' => file_get_contents($root . '/html/admin/ingredients.html'),
    'api' => file_get_contents($root . '/api/admin/ingredients.php'),
    'supplier_page' => file_get_contents($root . '/html/admin/suppliers.html'),
    'supplier_helper' => file_get_contents($root . '/api/helpers/supplier_ingredient_catalog.php'),
    'migration' => file_get_contents($root . '/sql/normalize_ingredient_uom_rules.sql'),
];

foreach ($sources as $name => $source) {
    if ($source === false) {
        fwrite(STDERR, "Unable to read {$name}.\n");
        exit(1);
    }
}

$checks = [
    'Ingredient form requires a material form' =>
        str_contains($sources['page'], 'id="physical_state"')
        && str_contains($sources['page'], 'Liquid - measured by volume'),
    'Browser limits solids to mass units' =>
        str_contains($sources['page'], "solid: ['kg', 'g']"),
    'Browser limits liquids to volume units' =>
        str_contains($sources['page'], "liquid: ['liter', 'ml']"),
    'Browser keeps counted containers separate from measurement' =>
        str_contains($sources['page'], "count: ['pcs', 'pack', 'packet', 'roll', 'bottle']")
        && !str_contains($sources['page'], 'id="packaged_purchase_toggle"'),
    'Supplier page owns package measurement and conversion' =>
        str_contains($sources['supplier_page'], 'Quantity inside one package')
        && str_contains($sources['supplier_page'], 'Unit of measure')
        && str_contains($sources['supplier_page'], 'supplierOfferCompatibleUnits')
        && str_contains($sources['supplier_page'], 'supplierOfferToStock'),
    'Server validates material form against stock unit' =>
        str_contains($sources['api'], 'function validateIngredientPhysicalStateUnit')
        && str_contains($sources['api'], "'liquid' => ['liter', 'ml']")
        && str_contains($sources['api'], 'Grams require a separately approved density conversion.'),
    'Server rejects a supplier package outside the stock-unit family' =>
        str_contains($sources['supplier_helper'], 'function supplierCatalogConvertToStockUnit')
        && str_contains($sources['supplier_helper'], 'must match its stock unit'),
    'Create and update both save physical state' =>
        str_contains($sources['api'], 'unit_of_measure, physical_state, packaging_role')
        && str_contains($sources['api'], "'unit_of_measure', 'physical_state', 'packaging_role', 'minimum_stock'"),
    'Packaging is always non-perishable in both the form and server' =>
        str_contains($sources['page'], 'function syncExpiryHandlingForCategory()')
        && str_contains($sources['api'], 'function ingredientCategoryIsPackaging')
        && str_contains($sources['api'], 'SET i.is_perishable = 0'),
    'Database update classifies existing measurement units' =>
        str_contains($sources['migration'], 'ADD COLUMN IF NOT EXISTS physical_state')
        && str_contains($sources['migration'], "THEN 'liquid'")
        && str_contains($sources['migration'], "THEN 'solid'"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Failed: {$label}.\n");
        exit(1);
    }
}

echo "Ingredient measurement rule tests passed.\n";
