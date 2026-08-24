<?php
declare(strict_types=1);

function assertNear(float $actual, float $expected, string $message): void {
    if (abs($actual - $expected) > 0.000001) {
        throw new RuntimeException("{$message}: expected {$expected}, got {$actual}");
    }
}

function convertOfferAmount(float $amount, string $from, string $to): float {
    if ($from === $to) return $amount;
    $factors = [
        'kg:g' => 1000.0,
        'g:kg' => 0.001,
        'liter:ml' => 1000.0,
        'ml:liter' => 0.001,
    ];
    $key = "{$from}:{$to}";
    if (!isset($factors[$key])) {
        throw new InvalidArgumentException('Incompatible measurement families');
    }
    return $amount * $factors[$key];
}

function comparisonPrice(float $quotedPrice, float $packageAmount, string $packageUnit, string $stockUnit): float {
    return $quotedPrice / convertOfferAmount($packageAmount, $packageUnit, $stockUnit);
}

// Same Sugar master (stocked in kg), three genuinely different supplier offers.
assertNear(60.0, 60.0, 'Direct bulk supplier price per kg');
assertNear(comparisonPrice(1400.0, 25.0, 'kg', 'kg'), 56.0, '25 kg sack normalized price');
assertNear(comparisonPrice(2900.0, 50.0, 'kg', 'kg'), 58.0, '50 kg sack normalized price');
assertNear(comparisonPrice(240.0, 500.0, 'g', 'kg'), 480.0, '500 g packet normalized to kg');

$helper = file_get_contents(__DIR__ . '/../api/helpers/supplier_ingredient_catalog.php');
$ingredientApi = file_get_contents(__DIR__ . '/../api/admin/ingredients.php');
$ingredientPage = file_get_contents(__DIR__ . '/../html/admin/ingredients.html');
$supplierPage = file_get_contents(__DIR__ . '/../html/admin/suppliers.html');
$canvassApi = file_get_contents(__DIR__ . '/../api/purchasing/canvassing.php');
$migration = file_get_contents(__DIR__ . '/../sql/move_buying_format_to_supplier_ingredients.sql');

foreach ([
    'purchase_format', 'package_type', 'package_size_value', 'package_size_unit',
    'package_quantity_in_stock_unit', 'quoted_price', 'reference_unit_price',
    'offer_label', 'enforce_whole_packages',
] as $field) {
    if (!str_contains($helper, $field) || !str_contains($migration, $field)) {
        throw new RuntimeException("Supplier-offer field is missing: {$field}");
    }
}

foreach ([
    'Supplier price is quoted',
    'Per warehouse unit (for example, per kg)',
    'Per whole package (for example, per sack)',
    'Quantity inside one package',
    'Unit of measure',
    'Effective Stock Unit Cost',
    'selectedIngredientCount',
    'supplierOfferToStock',
] as $text) {
    if (!str_contains($supplierPage, $text)) {
        throw new RuntimeException("Supplier-specific buying UI is missing: {$text}");
    }
}

foreach ([
    "'container'",
    'supplierCatalogIsPlainDecimal',
    'supplierCatalogMaximumPackageSize',
    'supplierCatalogIsCountUnit',
] as $text) {
    if (!str_contains($helper, $text)) {
        throw new RuntimeException("Supplier package safeguard is missing: {$text}");
    }
}

if (str_contains($ingredientPage, 'id="packaged_purchase_toggle"')
    || str_contains($ingredientPage, 'Sold in a container or package')) {
    throw new RuntimeException('The Ingredient form still owns the supplier buying-format switch');
}

if (str_contains($ingredientPage, 'How suppliers sell this ingredient')
    || str_contains($ingredientPage, 'Buying format is set separately for every accredited supplier.')) {
    throw new RuntimeException('Ingredient page still shows the removed supplier buying-format information panel');
}

if (str_contains($ingredientApi, "\$errors['purchase_format']")) {
    throw new RuntimeException('Ingredient API still requires a global purchase format');
}

if (!str_contains($canvassApi, 'normalized to PHP %s per %s for comparison')) {
    throw new RuntimeException('Canvassing does not explain normalized supplier comparison prices');
}

echo "Supplier-specific ingredient offer tests passed.\n";
