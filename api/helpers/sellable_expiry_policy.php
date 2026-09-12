<?php

/**
 * Client shelf-life rule shared by Cashier, Sales, and dispatch.
 *
 * A batch with seven days or less remaining is reserved for QC handling and
 * must not be offered, promised as ready stock, or dispatched to a customer.
 */
const HF_NEAR_EXPIRY_DAYS = 7;
const HF_MIN_FINISHED_PRODUCT_SHELF_LIFE_DAYS = HF_NEAR_EXPIRY_DAYS + 1;

if (!function_exists('hfResolveFinishedProductShelfLifeDays')) {
    /**
     * Shelf life is maintained on the current product master. The recipe value
     * is only a legacy fallback for older records that are not linked to a
     * product/base product yet.
     */
    function hfResolveFinishedProductShelfLifeDays($skuDays, $baseProductDays, $legacyRecipeDays): int
    {
        foreach ([$skuDays, $baseProductDays, $legacyRecipeDays] as $candidate) {
            $days = filter_var($candidate, FILTER_VALIDATE_INT);
            if ($days !== false && $days > 0) {
                return $days;
            }
        }

        return HF_MIN_FINISHED_PRODUCT_SHELF_LIFE_DAYS;
    }
}

if (!function_exists('hfFinishedProductShelfLifeError')) {
    function hfFinishedProductShelfLifeError($value): ?string
    {
        $days = filter_var($value, FILTER_VALIDATE_INT);
        if ($days === false || $days < HF_MIN_FINISHED_PRODUCT_SHELF_LIFE_DAYS) {
            return 'Finished-product shelf life must be at least '
                . HF_MIN_FINISHED_PRODUCT_SHELF_LIFE_DAYS
                . ' days so it starts outside the '
                . HF_NEAR_EXPIRY_DAYS
                . '-day QC handling window.';
        }

        return null;
    }
}

if (!function_exists('hfSellableExpirySql')) {
    function hfSellableExpirySql(string $column): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $column)) {
            throw new InvalidArgumentException('Invalid expiry-date column');
        }

        return $column . ' > DATE_ADD(CURDATE(), INTERVAL ' . HF_NEAR_EXPIRY_DAYS . ' DAY)';
    }
}
if (!function_exists('hfExpiryIsSellable')) {
    function hfExpiryIsSellable(string $expiryDate, ?DateTimeImmutable $today = null): bool
    {
        try {
            $expiry = (new DateTimeImmutable($expiryDate))->setTime(0, 0);
        } catch (Exception $error) {
            return false;
        }

        $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
        return $expiry > $today->modify('+' . HF_NEAR_EXPIRY_DAYS . ' days');
    }
}
