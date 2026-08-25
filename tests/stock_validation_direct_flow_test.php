<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$support = file_get_contents($root . '/api/helpers/stock_validation_support.php');
$validationApi = file_get_contents($root . '/api/warehouse/raw/stock_validations.php');
$oldPrApi = file_get_contents($root . '/api/purchasing/purchase_requests.php');
$poApi = file_get_contents($root . '/api/purchasing/purchase_orders.php');
$warehousePage = file_get_contents($root . '/html/warehouse/raw/reorder_alerts.html');
$purchasingPage = file_get_contents($root . '/html/purchasing/purchase_orders.html');

$checks = [
    'new shelf checks use dedicated confirmation tables' =>
        str_contains($support, 'CREATE TABLE IF NOT EXISTS stock_validations')
        && str_contains($support, 'CREATE TABLE IF NOT EXISTS stock_validation_items')
        && str_contains($support, 'CREATE TABLE IF NOT EXISTS stock_validation_item_po'),
    'Warehouse confirms a physical count without creating a PRS' =>
        str_contains($validationApi, 'function createStockValidation')
        && str_contains($validationApi, 'physical_stock')
        && str_contains($validationApi, 'quantity_needed')
        && !str_contains($validationApi, 'INSERT INTO purchase_requests'),
    'a second open confirmation for the same item is blocked' =>
        str_contains($validationApi, "sv.status IN ('open','partially_ordered')")
        && str_contains($validationApi, 'already has an active stock confirmation'),
    'old open PRSs are carried forward without deleting history' =>
        str_contains($support, 'migrateOpenLegacyPRSToStockValidations')
        && str_contains($support, "CONCAT('LEGACY-', pr.pr_number)")
        && str_contains($support, 'legacy_purchase_request_item_id'),
    'old duplicate products are hidden from the new queue but remain in history' =>
        str_contains($support, 'deduplicateLegacyStockValidationQueue')
        && str_contains($support, 'SET is_queue_active = 0')
        && str_contains($validationApi, 'svi.is_queue_active = 1'),
    'old PRS creation is retired' =>
        str_contains($oldPrApi, 'New Purchase Request Slips are retired')
        && str_contains($oldPrApi, '410'),
    'Purchasing reads and orders the confirmed shortage' =>
        str_contains($purchasingPage, 'getConfirmedLowStock()')
        && str_contains($purchasingPage, 'stock_validation_item_id: prItemId')
        && str_contains($purchasingPage, 'purchasing.service.js?v=procurement-flow-3')
        && str_contains($poApi, 'FROM stock_validation_items svi')
        && str_contains($poApi, 'insertStockValidationItemPOAllocation'),
    'Warehouse screen uses plain validation wording' =>
        str_contains($warehousePage, 'Low Stock Validation')
        && str_contains($warehousePage, 'Confirm Stock Check')
        && !str_contains($warehousePage, 'window.location.replace'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}
if ($failed) {
    fwrite(STDERR, "Direct stock-validation flow checks failed.\n");
    exit(1);
}
echo "Direct stock-validation flow checks passed.\n";
