<?php

require_once dirname(__DIR__) . '/api/helpers/sellable_expiry_policy.php';

function salesExpiryAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Sales expiry policy test failed: {$message}\n");
        exit(1);
    }
}

$today = new DateTimeImmutable('2026-09-10');
salesExpiryAssert(!hfExpiryIsSellable('2026-09-17', $today), 'exactly seven days must be QC stock');
salesExpiryAssert(hfExpiryIsSellable('2026-09-18', $today), 'eight days must remain sellable');

$sources = [
    'Sales product availability' => dirname(__DIR__) . '/api/warehouse/fg/products.php',
    'Sales order validation' => dirname(__DIR__) . '/api/sales/orders.php',
    'Customer PO stock check' => dirname(__DIR__) . '/api/helpers/customer_order_import.php',
    'Delivery Receipt validation' => dirname(__DIR__) . '/api/warehouse/fg/delivery_receipts.php',
    'Warehouse dispatch' => dirname(__DIR__) . '/api/warehouse/fg/dispatch.php',
];

foreach ($sources as $label => $path) {
    $source = file_get_contents($path);
    salesExpiryAssert(
        str_contains($source, 'sellable_expiry_policy.php')
            && str_contains($source, 'hfSellableExpirySql('),
        "{$label} must use the shared seven-day rule"
    );
}

$dispatch = file_get_contents($sources['Warehouse dispatch']);
salesExpiryAssert(
    str_contains($dispatch, 'Batches with 7 days or less before expiry are handled by QC.'),
    'a direct dispatch attempt must explain why the batch is blocked'
);

echo "Sales and dispatch seven-day expiry policy checks passed.\n";
