<?php

require_once dirname(__DIR__) . '/api/helpers/pos_wholesale.php';

function wholesaleAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "POS wholesale test failed: {$message}\n");
        exit(1);
    }
}

$product = [
    'selling_price' => 25,
    'unit_price' => 25,
    'pieces_per_box' => 24,
    'wholesale_box_price' => 550,
];

$retail = hfPosPriceLine($product, 'retail', 3);
wholesaleAssert($retail['sale_unit'] === 'piece', 'retail must be recorded as pieces');
wholesaleAssert($retail['base_quantity'] === 3, 'three retail pieces must deduct three inventory units');
wholesaleAssert($retail['line_total'] === 75.0, 'retail must use the saved unit price');

$wholesale = hfPosPriceLine($product, 'wholesale', 2);
wholesaleAssert($wholesale['sale_unit'] === 'box', 'wholesale must be recorded as boxes');
wholesaleAssert($wholesale['sale_quantity'] === 2, 'the receipt must preserve two selected boxes');
wholesaleAssert($wholesale['base_quantity'] === 48, 'two boxes of 24 must deduct 48 inventory units');
wholesaleAssert($wholesale['unit_price'] === 550.0, 'wholesale must use the saved box price');
wholesaleAssert($wholesale['line_total'] === 1100.0, 'two boxes must total two times the box price');

$discounted = hfWholesaleDiscountSummary(10, 10, 90);
wholesaleAssert($discounted['valid'] === true, '₱90 must be accepted for ten ₱10 units');
wholesaleAssert($discounted['retail_pack_value'] === 100.0, 'regular pack value must be ₱100');
wholesaleAssert($discounted['savings'] === 10.0, 'the displayed savings must be ₱10');
wholesaleAssert($discounted['discount_percent'] === 10.0, 'the displayed discount must be 10%');

foreach ([100, 110] as $notDiscountedPrice) {
    $notDiscounted = hfWholesaleDiscountSummary(10, 10, $notDiscountedPrice);
    wholesaleAssert($notDiscounted['valid'] === false, "₱{$notDiscountedPrice} must be rejected because it is not below the ₱100 retail value");
}

foreach ([
    ['product' => array_merge($product, ['pieces_per_box' => 1]), 'message' => 'no box configuration'],
    ['product' => array_merge($product, ['wholesale_box_price' => null]), 'message' => 'no box price'],
    ['product' => array_merge($product, ['wholesale_box_price' => 600]), 'message' => 'no wholesale discount'],
] as $invalid) {
    try {
        hfPosPriceLine($invalid['product'], 'wholesale', 1);
        wholesaleAssert(false, "wholesale should reject a product with {$invalid['message']}");
    } catch (InvalidArgumentException $expected) {
        // Expected.
    }
}

$root = dirname(__DIR__);
$salePage = file_get_contents($root . '/html/pos/sale.html');
$transactionApi = file_get_contents($root . '/api/pos/transactions.php');
$productApi = file_get_contents($root . '/api/pos/products.php');
$adminPage = file_get_contents($root . '/html/admin/products.html');
$ordersPage = file_get_contents($root . '/html/sales/orders.html');

wholesaleAssert(str_contains($salePage, 'sale.html?mode=wholesale'), 'Cashier must expose Wholesale Sale navigation');
wholesaleAssert(str_contains($salePage, "sale_mode: saleMode"), 'Cashier must send the selected mode');
wholesaleAssert(!str_contains($salePage, 'id="customerName"'), 'Direct cashier sales must not ask for a free-typed customer name');
wholesaleAssert(str_contains($salePage, "customer_name: 'Walk-in Customer'"), 'Direct cashier sales must keep a safe internal customer label');
wholesaleAssert(str_contains($salePage, 'fullBoxesAvailable'), 'Wholesale UI must use full-box availability');
wholesaleAssert(str_contains($transactionApi, 'hfPosPriceLine($product, $saleMode, $saleQuantity)'), 'server must price and convert each line from product master data');
wholesaleAssert(str_contains($productApi, 'wholesale_box_price'), 'POS product response must expose the approved box price');
wholesaleAssert(str_contains($adminPage, 'sku_edit_wholesale_box_price'), 'Admin must be able to set the wholesale box price');
wholesaleAssert(substr_count($adminPage, 'wholesale_box_price: row.wholesale_box_price') >= 2, 'new and edited product saves must send the entered wholesale price');
wholesaleAssert(str_contains($adminPage, 'Object.values(err.errors).filter(Boolean)'), 'product setup must show the exact validation reason');
wholesaleAssert(str_contains($adminPage, 'Customer saves'), 'Admin must immediately explain the wholesale savings');
wholesaleAssert(str_contains($adminPage, 'Must be below'), 'Admin must warn when the pack has no discount');
wholesaleAssert(!str_contains($ordersPage, 'value="manual_walk_in"'), 'Sales must not offer immediate walk-in entry');

echo "POS retail and wholesale flow tests passed.\n";
