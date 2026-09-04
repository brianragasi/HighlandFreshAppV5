<?php

require_once dirname(__DIR__) . '/api/helpers/procurement_notifications.php';

$checks = [
    ['purchaser', 'prs_submitted_for_supplier_review', true],
    ['purchaser', 'stock_validated', true],
    ['general_manager', 'po_pending_approval', true],
    ['warehouse_raw', 'po_approved_pending_delivery', true],
    ['finance_officer', 'po_approved_prepare_funds', true],
    ['purchaser', 'rr_ready_for_verification', true],
    ['qc_officer', 'fg_disposal_review', true],
    ['purchaser', 'found_stock_price_check', true],
    ['qc_officer', 'found_stock_qc_check', true],
    ['general_manager', 'found_stock_ready_for_gm', true],
    ['warehouse_raw', 'found_stock_rejected', true],
    ['purchaser', 'new_material_purchase', true],
    ['warehouse_raw', 'ingredient_opening_count', true],
    ['warehouse_raw', 'new_material_purchase', false],
    ['purchaser', 'ingredient_opening_count', false],
    ['security', 'prs_submitted_for_supplier_review', false],
    ['qc_officer', 'po_pending_approval', false],
    ['warehouse_raw', 'stock_validated', false],
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
