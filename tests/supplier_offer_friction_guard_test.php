<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/helpers/supplier_ingredient_catalog.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(supplierCatalogIsPlainDecimal('25', 3), 'Whole package quantities should be accepted.');
$assert(supplierCatalogIsPlainDecimal('25.125', 3), 'Package quantities should allow three decimal places.');
$assert(!supplierCatalogIsPlainDecimal('25.1259', 3), 'Package quantities should reject excessive decimal places.');
$assert(!supplierCatalogIsPlainDecimal('2e10', 3), 'Scientific notation must be rejected.');
$assert(!supplierCatalogIsPlainDecimal('-1', 3), 'Negative quantities must be rejected.');
$assert(!supplierCatalogIsPlainDecimal('+1', 3), 'Explicit positive signs must be rejected.');
$assert(supplierCatalogMaximumPackageSize('kg') === 100000.0, 'Mass package ceiling is missing.');
$assert(supplierCatalogMaximumPackageSize('ml') === 100000000.0, 'Small-volume package ceiling is missing.');
$assert(supplierCatalogIsCountUnit('pieces'), 'Piece aliases must be treated as count units.');
$assert(!supplierCatalogIsCountUnit('kg'), 'Mass units must allow legitimate decimal quantities.');
$assert(supplierCatalogConvertToStockUnit(500.0, 'g', 'kg') === 0.5, 'Gram-to-kilogram conversion changed unexpectedly.');

$page = file_get_contents(__DIR__ . '/../html/admin/suppliers.html');
$helper = file_get_contents(__DIR__ . '/../api/helpers/supplier_ingredient_catalog.php');
$supplierApi = file_get_contents(__DIR__ . '/../api/admin/suppliers.php');
$assert($page !== false && str_contains($page, 'Quantity inside one package'), 'Clear package quantity wording is missing.');
$assert($page !== false && str_contains($page, 'Effective Stock Unit Cost'), 'The live normalized stock-unit cost preview is missing.');
$assert($page !== false && str_contains($page, 'offer-package-size-unit-'), 'The package quantity does not show its unit beside the input.');
$assert($page !== false && str_contains($page, 'formatSupplierOfferFieldOnBlur'), 'Supplier package quantities and prices are not formatted on blur.');
$assert($page !== false && str_contains($page, "format === 'packaged' ? 2 : 6"), 'Package prices are not limited to normal currency precision.');
$assert($page !== false && str_contains($page, 'min="${format === \'packaged\' ? \'0.01\' : \'0.000001\'}"'), 'Package price minimum is not aligned to the cent step grid.');
$assert($page !== false && str_contains($page, "priceInput.min = packaged ? '0.01' : '0.000001';"), 'Changing the quote format does not realign the price minimum.');
$assert($helper !== false && str_contains($helper, '$maximumPriceDecimals = $format === \'packaged\' ? 2 : 6;'), 'The server does not enforce two-decimal whole-package prices.');
$assert($page !== false && str_contains($page, 'selectedIngredientsOnly'), 'Selected-item filtering is missing.');
$assert($page !== false && str_contains($page, "'Liquid containers': ['bottle', 'jug', 'drum', 'pail', 'tank', 'container']"), 'Grouped package choices or Container compatibility is missing.');
$assert($helper !== false && str_contains($helper, "'container'"), 'The server does not accept the Container package type.');
$assert($supplierApi !== false && str_contains($supplierApi, "intval(\$data['is_active']) : 0"), 'New suppliers still silently default to active.');

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "Failed: {$failure}\n");
    }
    exit(1);
}

echo "Supplier offer friction and validation tests passed.\n";
