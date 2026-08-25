<?php

/**
 * Delivery timing belongs to the supplier record. A Purchase Order always
 * uses today's date and calculates delivery from the supplier's saved lead
 * time, so Purchasers cannot enter arbitrary planning dates.
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
        throw new InvalidArgumentException('Delivery lead time must be a whole number from 1 to 60 days');
    }
    return (int) $raw;
}

function hfSupplierPurchaseOrderDates($leadTimeDays): array {
    $days = hfNormalizeSupplierLeadTimeDays($leadTimeDays);
    $orderDate = new DateTimeImmutable('today');

    return [
        'order_date' => $orderDate->format('Y-m-d'),
        'expected_delivery' => $orderDate->modify('+' . $days . ' days')->format('Y-m-d'),
        'lead_time_days' => $days,
    ];
}

