<?php

/**
 * Delivery timing belongs to the supplier record. A Purchase Order always
 * uses today's date and calculates delivery from the supplier's saved working
 * day lead time, so Purchasers cannot enter arbitrary planning dates.
 */
function ensureSupplierDeliveryTerms(PDO $db): void {
    if (!auditColumnExists($db, 'suppliers', 'lead_time_days')) {
        $db->exec("ALTER TABLE suppliers ADD COLUMN lead_time_days INT NOT NULL DEFAULT 3 AFTER address");
    }

    $db->exec("UPDATE suppliers SET lead_time_days = 3 WHERE lead_time_days IS NULL OR lead_time_days < 1 OR lead_time_days > 60");
}

function hfNormalizeSupplierLeadTimeDays($value): int {
    $raw = trim((string) $value);
    if (!preg_match('/^(?:[1-9]|[1-5][0-9]|60)$/', $raw)) {
        throw new InvalidArgumentException('Delivery lead time must be a whole number from 1 to 60 working days');
    }
    return (int) $raw;
}

/**
 * Return optional company/supplier closure dates configured in .env.
 *
 * Example: SUPPLIER_NON_WORKING_DATES=2026-09-01,2026-12-25
 * Invalid values are ignored so a typo cannot break Purchase Order creation.
 */
function hfSupplierNonWorkingDates(): array {
    $raw = function_exists('envOrDefault')
        ? (string) envOrDefault('SUPPLIER_NON_WORKING_DATES', '')
        : '';
    $dates = [];

    foreach (preg_split('/[,;\s]+/', trim($raw)) ?: [] as $value) {
        if ($value === '') continue;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date && $date->format('Y-m-d') === $value) {
            $dates[$value] = $value;
        }
    }

    ksort($dates);
    return array_values($dates);
}

function hfAddSupplierWorkingDays(DateTimeImmutable $startDate, int $days, array $nonWorkingDates = []): DateTimeImmutable {
    $blockedDates = array_fill_keys($nonWorkingDates, true);
    $date = $startDate;
    $remaining = $days;

    while ($remaining > 0) {
        $date = $date->modify('+1 day');
        $isWeekend = (int) $date->format('N') >= 6;
        $isConfiguredClosure = isset($blockedDates[$date->format('Y-m-d')]);
        if ($isWeekend || $isConfiguredClosure) continue;
        $remaining--;
    }

    return $date;
}

function hfSupplierPurchaseOrderDates($leadTimeDays): array {
    $days = hfNormalizeSupplierLeadTimeDays($leadTimeDays);
    $orderDate = new DateTimeImmutable('today');
    $nonWorkingDates = hfSupplierNonWorkingDates();

    return [
        'order_date' => $orderDate->format('Y-m-d'),
        'expected_delivery' => hfAddSupplierWorkingDays($orderDate, $days, $nonWorkingDates)->format('Y-m-d'),
        'lead_time_days' => $days,
        'lead_time_basis' => 'working_days',
    ];
}
