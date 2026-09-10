<?php

require_once dirname(__DIR__) . '/api/helpers/sellable_expiry_policy.php';

function posExpiryPolicyAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "POS expiry policy test failed: {$message}\n");
        exit(1);
    }
}

$today = new DateTimeImmutable('2026-09-10');

posExpiryPolicyAssert(
    !hfExpiryIsSellable('2026-09-17', $today),
    'a batch expiring exactly seven days from today must be reserved for QC'
);
posExpiryPolicyAssert(
    hfExpiryIsSellable('2026-09-18', $today),
    'a batch with eight days remaining must stay sellable'
);
posExpiryPolicyAssert(
    !hfExpiryIsSellable('2026-09-10', $today),
    'a batch expiring today must not be sellable'
);

$productsApi = file_get_contents(dirname(__DIR__) . '/api/pos/products.php');
$transactionsApi = file_get_contents(dirname(__DIR__) . '/api/pos/transactions.php');
$qcDashboardApi = file_get_contents(dirname(__DIR__) . '/api/qc/dashboard.php');
$cashierPage = file_get_contents(dirname(__DIR__) . '/html/pos/sale.html');

posExpiryPolicyAssert(
    str_contains($productsApi, "hfSellableExpirySql('fgi.expiry_date')")
        && str_contains($productsApi, "hfSellableExpirySql('fg.expiry_date')")
        && str_contains($productsApi, 'AND COALESCE(inv.total_available, 0) > 0'),
    'POS listing, barcode lookup, and product details must use the shared cutoff'
);
posExpiryPolicyAssert(
    str_contains($transactionsApi, "hfSellableExpirySql('fg.expiry_date')")
        && str_contains($transactionsApi, 'near-expiry stock is reserved for QC'),
    'checkout must enforce and explain the same cutoff'
);
posExpiryPolicyAssert(
    str_contains($qcDashboardApi, 'INTERVAL 7 DAY'),
    'QC dashboard alerts must begin when Cashier stops selling the batch'
);
posExpiryPolicyAssert(
    str_contains($cashierPage, 'Batches with 7 days or less before expiry are handled by QC.'),
    'Cashier must explain why near-expiry products are absent'
);

echo "POS seven-day near-expiry policy checks passed.\n";
