<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$api = file_get_contents($root . '/api/purchasing/suppliers.php');
$service = file_get_contents($root . '/js/purchasing/purchasing.service.js');
$page = file_get_contents($root . '/html/purchasing/purchase_orders.html');
$gmApi = file_get_contents($root . '/api/admin/gm_approvals.php');

$checks = [
    'Purchasing has a controlled price-list action' =>
        str_contains($api, "action !== 'update_item_price'")
        && str_contains($api, "['purchaser', 'general_manager']")
        && str_contains($service, 'updateSupplierItemPrice(data)'),
    'price input rejects scientific and signed notation' =>
        str_contains($api, 'supplierCatalogIsPlainDecimal($rawPrice, $maxDecimals)')
        && str_contains($page, 'Do not use e, E, +, or -.')
        && str_contains($page, 'const pattern = new RegExp'),
    'package prices allow no more than two decimal places' =>
        str_contains($api, '$maxDecimals = $packaged ? 2 : 6')
        && str_contains($page, 'const maximumDecimals = packaged ? 2 : 6'),
    'every change needs a written reason and price history' =>
        str_contains($api, 'mb_strlen($reason) < 10')
        && str_contains($api, 'INSERT INTO ingredient_price_history')
        && str_contains($api, 'INSERT INTO mro_price_history')
        && str_contains($api, 'INSERT INTO supplier_price_list_history')
        && str_contains($gmApi, 'FROM supplier_price_list_history sph')
        && str_contains($api, 'UPDATE_SUPPLIER_PRICE'),
    'Purchaser can update the selected line and refresh the PO' =>
        str_contains($page, 'Update price')
        && str_contains($page, 'function openSupplierPriceDialog')
        && str_contains($page, 'function saveSupplierPrice')
        && str_contains($page, 'await onMainSupplierChange()'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}
if ($failed) {
    fwrite(STDERR, "Purchaser price-list checks failed.\n");
    exit(1);
}
echo "Purchaser price-list checks passed.\n";
