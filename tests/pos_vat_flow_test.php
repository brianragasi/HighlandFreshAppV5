<?php

require_once dirname(__DIR__) . '/api/helpers/pos_vat.php';

function posVatAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "POS VAT flow test failed: {$message}\n");
        exit(1);
    }
}

$hundred = hfPosVatBreakdown(100);
posVatAssert($hundred['vatable_sales'] === 89.29, '₱100 VAT-inclusive price must contain ₱89.29 VATable sales');
posVatAssert($hundred['tax_amount'] === 10.71, '₱100 VAT-inclusive price must contain ₱10.71 VAT');

$fiveFifty = hfPosVatBreakdown(550);
posVatAssert($fiveFifty['vatable_sales'] === 491.07, '₱550 VAT-inclusive total must contain ₱491.07 VATable sales');
posVatAssert($fiveFifty['tax_amount'] === 58.93, '₱550 VAT-inclusive total must contain ₱58.93 VAT');
posVatAssert(round($fiveFifty['vatable_sales'] + $fiveFifty['tax_amount'], 2) === 550.00, 'VAT breakdown must reconcile to the amount paid');

$api = file_get_contents(dirname(__DIR__) . '/api/pos/transactions.php');
$salePage = file_get_contents(dirname(__DIR__) . '/html/pos/sale.html');
posVatAssert(str_contains($api, 'tax_amount, total_amount'), 'checkout must persist tax_amount');
posVatAssert(str_contains($salePage, 'VAT (12%, included)'), 'cashier must disclose that VAT is included, not added');

echo "POS VAT-inclusive calculation and disclosure checks passed.\n";
