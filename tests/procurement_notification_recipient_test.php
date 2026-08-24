<?php

require_once dirname(__DIR__) . '/api/helpers/procurement_notifications.php';

$checks = [
    ['purchaser', 'prs_submitted_for_supplier_review', true],
    ['general_manager', 'po_pending_approval', true],
    ['warehouse_raw', 'po_approved_pending_delivery', true],
    ['finance_officer', 'po_approved_prepare_funds', true],
    ['purchaser', 'rr_ready_for_verification', true],
    ['qc_officer', 'fg_disposal_review', true],
    ['security', 'prs_submitted_for_supplier_review', false],
    ['qc_officer', 'po_pending_approval', false],
    ['purchaser', 'unknown_notification_type', false],
];

foreach ($checks as [$role, $type, $expected]) {
    $actual = isProcurementNotificationRecipientAllowed($role, $type);
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected notification rule for {$type} -> {$role}.\n");
        exit(1);
    }
}

echo "Procurement notification recipient tests passed.\n";
