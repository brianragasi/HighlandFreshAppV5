<?php
/**
 * Shared payment-reference rules for supplier and farmer disbursements.
 */

if (defined('FINANCE_PAYMENT_REFERENCE_HELPERS_LOADED')) {
    return;
}
define('FINANCE_PAYMENT_REFERENCE_HELPERS_LOADED', true);

/**
 * Cash has no bank-generated transaction number. Generate an internal voucher
 * that is safe under concurrent requests and remains readable in audit trails.
 */
function financeGenerateCashVoucherNumber($paymentDate)
{
    $timestamp = strtotime((string) $paymentDate);
    $datePart = $timestamp ? date('Ymd', $timestamp) : date('Ymd');
    return 'CPV-' . $datePart . '-' . strtoupper(bin2hex(random_bytes(6)));
}

function financeReferenceIsValid($value, $required = true)
{
    $value = trim((string) $value);
    if ($value === '') {
        return !$required;
    }
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._\/-]{2,99}$/D', $value) === 1;
}
