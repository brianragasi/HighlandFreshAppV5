<?php

require_once dirname(__DIR__) . '/api/finance/payment_reference_helpers.php';

function cashVoucherAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$first = financeGenerateCashVoucherNumber('2026-09-04');
$second = financeGenerateCashVoucherNumber('2026-09-04');

cashVoucherAssert(
    preg_match('/^CPV-20260904-[A-F0-9]{12}$/D', $first) === 1,
    'cash voucher must contain the payment date and a random suffix'
);
cashVoucherAssert($first !== $second, 'separate cash payments must receive separate voucher numbers');
cashVoucherAssert(financeReferenceIsValid('', false), 'official receipt is optional for cash');
cashVoucherAssert(!financeReferenceIsValid('', true), 'external reference is required for non-cash payments');
cashVoucherAssert(financeReferenceIsValid('OR-20491', false), 'valid official receipt should be accepted');
cashVoucherAssert(!financeReferenceIsValid('<script>', false), 'unsafe reference characters should be rejected');

$supplierApi = file_get_contents(dirname(__DIR__) . '/api/finance/payables.php');
$farmerApi = file_get_contents(dirname(__DIR__) . '/api/finance/farmer_payments.php');
$supplierUi = file_get_contents(dirname(__DIR__) . '/html/finance/payables.html');
$farmerUi = file_get_contents(dirname(__DIR__) . '/html/finance/farmer_payments.html');

foreach ([$supplierApi, $farmerApi] as $source) {
    cashVoucherAssert(
        strpos($source, "if (\$paymentMethod === 'cash')") !== false
            && strpos($source, 'financeGenerateCashVoucherNumber($paymentDate)') !== false,
        'each Finance API must generate cash voucher numbers on the server'
    );
    cashVoucherAssert(
        strpos($source, 'external_receipt_number') !== false,
        'each Finance API must store an optional official receipt separately'
    );
}

foreach ([$supplierUi, $farmerUi] as $source) {
    cashVoucherAssert(
        strpos($source, 'Official Receipt Number (optional)') !== false,
        'each Finance form must identify the cash receipt as optional'
    );
    cashVoucherAssert(
        strpos($source, 'The system generates the Cash Payment Voucher number after saving.') !== false,
        'each Finance form must explain automatic voucher generation'
    );
    cashVoucherAssert(
        strpos($source, "paymentMethod !== 'cash'") !== false,
        'each Finance form must require external references only for non-cash methods'
    );
}

echo "Finance cash voucher flow checks passed.\n";
