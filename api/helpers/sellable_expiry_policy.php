<?php

/**
 * Client shelf-life rule shared by Cashier, Sales, and dispatch.
 *
 * A batch with seven days or less remaining is reserved for QC handling and
 * must not be offered, promised as ready stock, or dispatched to a customer.
 */
const HF_NEAR_EXPIRY_DAYS = 7;

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
