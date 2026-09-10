<?php

/**
 * Highland Fresh POS prices are retail shelf prices and therefore VAT-inclusive.
 * Extract the VAT disclosure without adding another charge at checkout.
 */
if (!function_exists('hfPosVatBreakdown')) {
    function hfPosVatBreakdown($vatInclusiveAmount, float $rate = 0.12): array
    {
        $gross = round(max(0, (float) $vatInclusiveAmount), 2);
        if ($gross <= 0 || $rate <= 0) {
            return [
                'vatable_sales' => $gross,
                'tax_amount' => 0.00,
                'vat_rate' => 0.00,
            ];
        }

        $vatableSales = round($gross / (1 + $rate), 2);
        $taxAmount = round($gross - $vatableSales, 2);

        return [
            'vatable_sales' => $vatableSales,
            'tax_amount' => $taxAmount,
            'vat_rate' => round($rate * 100, 2),
        ];
    }
}
