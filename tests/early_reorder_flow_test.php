<?php

declare(strict_types=1);

define('HIGHLAND_FRESH', true);
$root = dirname(__DIR__);
require_once $root . '/api/helpers/early_reorder.php';

function assertEarlyReorder(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

try {
    $triggered = calculateIngredientEarlyReorder([
        'usable_stock' => 10000,
        'minimum_stock' => 2500,
        'reorder_point' => 3000,
        'maximum_stock' => 10000,
        'issued_quantity_30d' => 72000,
        'active_po_balance' => 0,
    ], 3);
    assertEarlyReorder($triggered['early_reorder_recommended'] === true, 'A material projected below its reorder point was not flagged');
    assertEarlyReorder(abs($triggered['average_daily_issue_30d'] - 2400) < 0.0001, 'The 30-day daily-use average is wrong');
    assertEarlyReorder(abs($triggered['projected_stock_at_delivery'] - 2800) < 0.0001, 'Projected delivery-date stock is wrong');
    assertEarlyReorder(abs($triggered['suggested_early_order_quantity'] - 7200) < 0.0001, 'Suggested replenishment to target is wrong');

    $coveredByPo = calculateIngredientEarlyReorder([
        'usable_stock' => 10000,
        'minimum_stock' => 2500,
        'reorder_point' => 3000,
        'maximum_stock' => 10000,
        'issued_quantity_30d' => 72000,
        'active_po_balance' => 5000,
    ], 3);
    assertEarlyReorder($coveredByPo['early_reorder_recommended'] === false, 'An active PO balance did not suppress a duplicate early-order warning');
    assertEarlyReorder(abs($coveredByPo['projected_stock_at_delivery'] - 7800) < 0.0001, 'Active PO stock was not included in the projection');

    $alreadyLow = calculateIngredientEarlyReorder([
        'usable_stock' => 2500,
        'minimum_stock' => 2500,
        'reorder_point' => 3000,
        'maximum_stock' => 10000,
        'issued_quantity_30d' => 72000,
        'active_po_balance' => 0,
    ], 3);
    assertEarlyReorder($alreadyLow['early_reorder_recommended'] === false, 'An already-low item was incorrectly duplicated into the early-order flow');

    $warehouseApi = file_get_contents($root . '/api/warehouse/raw/stock_validations.php');
    $warehousePage = file_get_contents($root . '/html/warehouse/raw/reorder_alerts.html');
    $purchasingPage = file_get_contents($root . '/html/purchasing/purchase_orders.html');
    $gmPage = file_get_contents($root . '/html/admin/gm_approvals.html');
    assertEarlyReorder(str_contains($warehouseApi, "recommendationType === 'early_reorder'"), 'Warehouse does not revalidate the forecast after the physical count');
    assertEarlyReorder(str_contains($warehousePage, 'ORDER SOON'), 'Warehouse cannot see the automatic early-order warning');
    assertEarlyReorder(str_contains($purchasingPage, 'Early reorder confirmed by Warehouse'), 'Purchasing cannot distinguish a forecast confirmation from ordinary low stock');
    assertEarlyReorder(str_contains($gmPage, 'System early reorder · Warehouse confirmed'), 'GM cannot see the early-order evidence source');
} catch (Throwable $error) {
    fwrite(STDERR, 'Early reorder flow test failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

echo "Early reorder flow tests passed.\n";
