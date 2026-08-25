<?php

require_once __DIR__ . '/../api/helpers/receiving_resolution.php';

function assertResolution($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$exact = buildReceivingResolutionPlan([
    ['id' => 1, 'item_description' => 'Milk', 'quantity' => 100, 'quantity_received' => 100, 'quantity_rejected' => 0, 'unit' => 'L'],
]);
assertResolution($exact['is_complete'], 'Exact delivery should be complete');
assertResolution($exact['verification_outcome'] === 'exact_match', 'Exact delivery should use exact_match outcome');
assertResolution(abs($exact['total_short']) < 0.0001, 'Exact delivery should have no short balance');

$replacement = buildReceivingResolutionPlan([
    ['id' => 2, 'item_description' => 'Sugar', 'quantity' => 100, 'quantity_received' => 100, 'quantity_rejected' => 10, 'unit' => 'kg'],
]);
assertResolution($replacement['is_complete'], 'Replacement stock should complete accepted quantity');
assertResolution($replacement['has_historical_rejections'], 'Rejected stock must remain visible in history');
assertResolution($replacement['verification_outcome'] === 'replacement_completed', 'Completed replacement should not remain blocked');

$short = buildReceivingResolutionPlan([
    ['id' => 3, 'purchase_request_item_id' => 9, 'item_description' => 'Cups', 'quantity' => 100, 'quantity_received' => 70, 'quantity_rejected' => 5, 'unit' => 'pcs'],
]);
assertResolution(!$short['is_complete'], 'Permanent short delivery must not appear exact');
assertResolution(abs($short['total_short'] - 30) < 0.0001, 'Short close must use accepted, not physically rejected, quantity');
assertResolution($short['verification_outcome'] === 'short_closed', 'Partial delivery should require short-close outcome');

$backend = file_get_contents(__DIR__ . '/../api/purchasing/purchase_orders.php');
$frontend = file_get_contents(__DIR__ . '/../html/purchasing/purchase_orders.html');
$service = file_get_contents(__DIR__ . '/../js/purchasing/purchasing.service.js');
$finance = file_get_contents(__DIR__ . '/../api/finance/payables.php');
$legacyReceiving = file_get_contents(__DIR__ . '/../api/warehouse/raw/receiving.php');
$receivingPage = file_get_contents(__DIR__ . '/../html/warehouse/raw/receive_deliveries.html');

foreach ([
    "'close_short'",
    'recordShortClosureAndReleasePRSBalance',
    'po_short_closure_items',
    'quantity_short_closed',
    'At least one accepted delivery is required',
    'A PO with received stock cannot be cancelled',
    'Undelivered PO balance reopened',
] as $fragment) {
    assertResolution(strpos($backend, $fragment) !== false, "Backend is missing controlled-flow fragment: {$fragment}");
}
assertResolution(strpos($backend, "UPDATE purchase_request_item_po SET quantity = ?") !== false, 'Short close must release only its allocation balance');
assertResolution(strpos($backend, 'has rejected quantity. Resolve the discrepancy before closing') === false, 'Historical rejection must not be a permanent exact-verification blocker');
assertResolution(strpos($backend, 'appears more than once in the confirmed-needs queue') !== false, 'PO creation must stop duplicate material requirements from becoming repeated receiving lines');
assertResolution(strpos($frontend, 'Close Short &amp; Verify') === false, 'Button text should be normal HTML, not double encoded');
assertResolution(strpos($frontend, 'Close Short & Verify') !== false, 'Purchaser needs a distinct short-close action');
assertResolution(strpos($frontend, 'remaining PR balance will reopen') !== false, 'UI must explain the allocation effect');
assertResolution(strpos($frontend, "!['partial_received', 'received', 'closed', 'rejected', 'cancelled'].includes(po.status)") !== false, 'UI must not offer ordinary cancellation after partial receiving');
assertResolution(strpos($service, "resolution = 'exact_match'") !== false, 'Client must send an explicit resolution mode');
assertResolution(strpos($finance, 'quantity_received') !== false, 'Finance must continue deriving payment from accepted quantity');
assertResolution(strpos($legacyReceiving, 'verification has moved to Purchasing > Purchase Orders') !== false, 'Legacy endpoint must not bypass the canonical audited verification flow');
assertResolution(strpos($receivingPage, 'Repeated PO material') !== false, 'Receiving must identify repeated material lines instead of rendering indistinguishable cards');
assertResolution(strpos($receivingPage, 'copyReceivingTraceabilityToMatchingLines') !== false, 'Receiving must provide a safe lot-copy action for legacy repeated PO lines');
assertResolution(strpos($receivingPage, 'receivingLineLabel(item, idx)') !== false, 'Receiving validation must identify the exact repeated PO line with missing traceability');
assertResolution(strpos($receivingPage, 'receiveItemCard-${idx}') !== false, 'Receiving validation must be able to reveal the exact invalid line');
assertResolution(strpos($receivingPage, 'function receivingUsesPackages(item)') !== false, 'Receiving must distinguish true packages from direct measured supplier units');
assertResolution(strpos($receivingPage, 'Measured Quantity Received *') !== false, 'Direct-unit deliveries need measured-quantity wording instead of package wording');
assertResolution(strpos($receivingPage, 'receiveFullMeasuredBalance') !== false, 'Warehouse needs a one-click way to enter the exact remaining measured delivery');
assertResolution(strpos($receivingPage, 'rewriteAdjusted = true') !== false, 'Receiving decimal inputs need a non-destructive typing mode');
assertResolution(strpos($receivingPage, 'preserveReceivedTyping') !== false, 'The live receiving calculation must not rewrite an unfinished decimal');
assertResolution(strpos($receivingPage, 'limitReceivingLine(${idx}, \'received\', this, true)') !== false, 'Measured quantities must be finalized only after the user leaves the field');

echo "Receiving resolution tests passed.\n";
