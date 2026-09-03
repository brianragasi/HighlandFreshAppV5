<?php

$root = dirname(__DIR__);
$page = file_get_contents($root . '/html/sales/order_inbox.html');
$api = file_get_contents($root . '/api/sales/order_inbox.php');

if ($page === false || $api === false) {
    fwrite(STDERR, "Unable to load Customer PO Inbox sources.\n");
    exit(1);
}

$checks = [
    'The normal review exposes one primary workflow action' =>
        str_contains($page, 'id="primaryReviewButton"')
        && str_contains($page, 'Submit for GM Approval')
        && !str_contains($page, 'id="saveDetailsButton"')
        && !str_contains($page, 'id="createOrderButton"'),
    'Customer confirmation is contextual rather than a permanent action' =>
        str_contains($page, "activeImport.status === 'needs_customer_confirmation'")
        && str_contains($page, '<span>Resolve issue</span>')
        && !str_contains($page, 'id="recordCallPrimaryButton"'),
    'Inbox navigation is reduced to three user-facing queues' =>
        substr_count($page, 'data-inbox-view=') === 3
        && str_contains($page, 'Waiting for customer')
        && str_contains($page, '>History <')
        && !str_contains($page, 'id="statusFilter"'),
    'Rows use one consistent review label' =>
        str_contains($page, "'Review order'")
        && !str_contains($page, "'Compare Details'"),
    'Optional customer price is hidden behind progressive disclosure' =>
        str_contains($page, 'Customer price from PO (optional)')
        && str_contains($page, 'Leave this blank to use the official system price.'),
    'A physically missing source blocks frontend submission' =>
        str_contains($page, 'sourceDocumentReady')
        && str_contains($page, '<span>Source unavailable</span>'),
    'A physically missing source blocks API save and conversion' =>
        str_contains($api, 'function hfAssertCustomerOrderSourceAvailable')
        && substr_count($api, 'hfAssertCustomerOrderSourceAvailable($db,') >= 2
        && str_contains($api, "'source_available' => \$available"),
    'The API supports the simplified waiting queue and history count' =>
        str_contains($api, "\$view === 'waiting'")
        && str_contains($api, "'history' => (int)(\$summary['history'] ?? 0)"),
    'History reflects the linked Sales Order lifecycle' =>
        str_contains($page, 'function visibleOrderStatus(row)')
        && str_contains($page, "approved: 'sales_approved'")
        && str_contains($page, "rejected: 'rejected'")
        && str_contains($page, "fulfilled: 'sales_completed'"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Failed: {$label}.\n");
        exit(1);
    }
}

echo "Simplified Customer PO workflow tests passed.\n";
