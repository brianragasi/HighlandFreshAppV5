<?php

require_once __DIR__ . '/../api/helpers/customer_order_import.php';

function salesWholesaleAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Sales wholesale pricing test failed: {$message}\n");
        exit(1);
    }
}

$product = [
    'selling_price' => 10,
    'wholesale_box_price' => 90,
    'pieces_per_box' => 10,
];

$boxOnly = hf_sales_pack_pricing($product, 1, 0);
salesWholesaleAssert($boxOnly['base_quantity'] === 10, 'one box must reserve ten bottles');
salesWholesaleAssert($boxOnly['line_total'] === 90.0, 'one box must use the ₱90 wholesale price');

$pieceOnly = hf_sales_pack_pricing($product, 0, 3);
salesWholesaleAssert($pieceOnly['base_quantity'] === 3, 'three loose bottles must reserve three bottles');
salesWholesaleAssert($pieceOnly['line_total'] === 30.0, 'three loose bottles must use the ₱10 retail price');

$mixed = hf_sales_pack_pricing($product, 1, 2);
salesWholesaleAssert($mixed['base_quantity'] === 12, 'one box plus two bottles must reserve twelve bottles');
salesWholesaleAssert($mixed['pack_total'] === 90.0, 'the full box part must remain ₱90');
salesWholesaleAssert($mixed['loose_total'] === 20.0, 'the loose bottle part must total ₱20');
salesWholesaleAssert($mixed['line_total'] === 110.0, 'one ₱90 box plus two ₱10 bottles must total ₱110');

foreach ([0, 100, 110] as $badWholesalePrice) {
    try {
        hf_sales_pack_pricing(array_merge($product, ['wholesale_box_price' => $badWholesalePrice]), 1, 0);
        salesWholesaleAssert(false, "a ₱{$badWholesalePrice} box price must not be accepted");
    } catch (InvalidArgumentException $expected) {
        // Expected: a full pack needs a real discount.
    }
}

$emailBox = hfCustomerOrderLinePricing([
    'quantity_base' => 20,
    'quantity_boxes' => 2,
    'quantity_pieces' => 0,
    'pieces_per_box' => 10,
    'base_unit' => 'bottle',
    'box_unit' => 'box',
    'system_unit_price' => 9,
    'raw_data' => json_encode([
        'price_unit' => 'box',
        'system_retail_unit_price' => 10,
        'system_wholesale_box_price' => 90,
    ]),
]);
salesWholesaleAssert($emailBox['line_total'] === 180.0, 'an emailed order with no quoted price must use two ₱90 boxes');

$quotedEmailBox = hfCustomerOrderLinePricing([
    'quantity_base' => 20,
    'quantity_boxes' => 2,
    'quantity_pieces' => 0,
    'pieces_per_box' => 10,
    'base_unit' => 'bottle',
    'box_unit' => 'box',
    'raw_data' => json_encode([
        'entered_unit_price' => 85,
        'price_unit' => 'box',
    ]),
]);
salesWholesaleAssert($quotedEmailBox['line_total'] === 170.0, 'a customer-quoted PO price must remain visible for review');

$root = dirname(__DIR__);
$ordersApi = file_get_contents($root . '/api/sales/orders.php');
$ordersPage = file_get_contents($root . '/html/sales/orders.html');
$inboxPage = file_get_contents($root . '/html/sales/order_inbox.html');
$warehouseProducts = file_get_contents($root . '/api/warehouse/fg/products.php');

salesWholesaleAssert(str_contains($ordersApi, 'hf_sales_pack_pricing($product, $boxes, $pieces)'), 'the Sales API must calculate the saved price itself');
salesWholesaleAssert(str_contains($warehouseProducts, 'wholesale_box_price'), 'the Sales product list must receive the approved wholesale price');
salesWholesaleAssert(str_contains($ordersPage, '(boxes * wholesalePrice) + (pieces * retailPrice)'), 'phone and message order totals must preview the mixed price');
salesWholesaleAssert(str_contains($inboxPage, 'productHasWholesalePrice'), 'email PO review must recognize configured wholesale products');

echo "Sales retail and wholesale order pricing checks passed.\n";
