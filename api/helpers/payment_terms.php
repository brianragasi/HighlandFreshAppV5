<?php

/**
 * Convert the supplier-registration wording into the value stored on a PO.
 */
function hfNormalizeSupplierPaymentTerms($terms): ?string {
    $value = strtolower(trim((string) $terms));
    if (in_array($value, ['cod', 'cash'], true)) {
        return 'cash';
    }

    if (preg_match('/^(7|15|30|45|60)\s*days?$/', $value, $matches)) {
        return 'credit_' . $matches[1];
    }

    if (preg_match('/^credit_(7|15|30|45|60)$/', $value, $matches)) {
        return 'credit_' . $matches[1];
    }

    return null;
}

function hfResolvePurchaseOrderPaymentTerms($supplierTerms, $requestedTerms, $overrideReason): array {
    $supplierDefault = hfNormalizeSupplierPaymentTerms($supplierTerms);
    if ($supplierDefault === null) {
        throw new InvalidArgumentException('The supplier does not have a valid payment term. Update the supplier record before creating the Purchase Order.');
    }

    $requestedValue = trim((string) $requestedTerms);
    $selectedTerms = $requestedValue === ''
        ? $supplierDefault
        : hfNormalizeSupplierPaymentTerms($requestedValue);
    if ($selectedTerms === null) {
        throw new InvalidArgumentException('Choose valid payment terms.');
    }

    $reason = trim((string) $overrideReason);
    $isOverride = $selectedTerms !== $supplierDefault;
    if ($isOverride && mb_strlen($reason) < 10) {
        throw new InvalidArgumentException('Explain why this order uses different payment terms from the supplier agreement (at least 10 characters).');
    }
    if (mb_strlen($reason) > 500) {
        throw new InvalidArgumentException('The payment-term change reason must be 500 characters or fewer.');
    }

    return [
        'payment_terms' => $selectedTerms,
        'supplier_payment_terms' => $supplierDefault,
        'is_override' => $isOverride,
        'override_reason' => $isOverride ? $reason : null,
    ];
}

function hfCalculatePurchaseOrderDueDate(string $paymentTerms, string $orderDate): ?string {
    if ($paymentTerms === 'cash') {
        // COD becomes due when Purchasing verifies the received delivery.
        return null;
    }

    $days = (int) str_replace('credit_', '', $paymentTerms);
    return date('Y-m-d', strtotime($orderDate . " + {$days} days"));
}
