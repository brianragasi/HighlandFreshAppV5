<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$helper = file_get_contents($root . '/api/helpers/supplier_mro_catalog.php');
$adminApi = file_get_contents($root . '/api/admin/suppliers.php');
$purchasingSupplierApi = file_get_contents($root . '/api/purchasing/suppliers.php');
$poApi = file_get_contents($root . '/api/purchasing/purchase_orders.php');
$supplierPage = file_get_contents($root . '/html/admin/suppliers.html');
$poPage = file_get_contents($root . '/html/purchasing/purchase_orders.html');

$checks = [
    'dedicated supplier-to-MRO relation has one active row per pair' =>
        str_contains($helper, 'CREATE TABLE IF NOT EXISTS supplier_mro_items')
        && str_contains($helper, 'UNIQUE KEY uq_supplier_mro_item (supplier_id, mro_item_id)'),
    'Admin supplier form saves MRO links and direct prices' =>
        str_contains($adminApi, 'supplierMroSyncSupplier')
        && str_contains($supplierPage, 'id="mroCatalog"')
        && str_contains($supplierPage, 'mro_items: mroLinks'),
    'Purchasing supplier directory returns accredited MRO items' =>
        str_contains($purchasingSupplierApi, "\$supplier['mro_items'] = supplierMroGetSupplierItems")
        && str_contains($purchasingSupplierApi, 'FROM supplier_mro_items smi'),
    'Supplier-first workbench filters MRO demand by the selected supplier' =>
        str_contains($poPage, 'directSupplierMroItems = response?.data?.mro_items || []')
        && str_contains($poPage, 'suppliedByMroId.has(Number(item.mro_item_id))'),
    'Server verifies the MRO catalog and applies its saved price' =>
        str_contains($poApi, 'FROM supplier_mro_items')
        && str_contains($poApi, "(int) \$prItem['mro_item_id']")
        && str_contains($poApi, "\$supplierUnit = \$prItem['unit'] ?: 'units'"),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed) {
    fwrite(STDERR, "Supplier MRO Purchase Order regression checks failed.\n");
    exit(1);
}

echo "Supplier MRO Purchase Order regression checks passed.\n";
