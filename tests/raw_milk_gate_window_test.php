<?php

define('APP_TIMEZONE', 'Asia/Manila');
require_once __DIR__ . '/../api/helpers/raw_milk_gate.php';

function gateAssert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$timezone = new DateTimeZone(APP_TIMEZONE);
$now = new DateTimeImmutable('2026-08-26 06:41:00', $timezone);

$fresh = rawMilkGateStatus('2026-08-26', '06:40', null, $now);
gateAssert($fresh['valid'] && !$fresh['is_expired'], 'a newly arrived delivery must remain usable');
gateAssert($fresh['remaining_seconds'] === 10740, 'a fresh arrival must retain the correct portion of its three-hour window');

$old = rawMilkGateStatus('2026-08-25', '06:41', null, $now);
gateAssert($old['valid'] && $old['is_expired'], 'yesterday date plus today time must not be treated as a new arrival');
gateAssert($old['remaining_seconds'] === -75600, 'the old example must be reported as expired by 21 hours');

$future = rawMilkGateStatus('2026-08-26', '07:00', null, $now);
gateAssert($future['is_future'], 'a materially future arrival must be rejected');

$invalid = rawMilkGateStatus('2026-02-31', '06:41', null, $now);
gateAssert(!$invalid['valid'], 'an impossible calendar date must fail validation');

$gradingSource = file_get_contents(__DIR__ . '/../api/qc/milk_grading.php');
$deliverySource = file_get_contents(__DIR__ . '/../api/qc/deliveries.php');
$tankSource = file_get_contents(__DIR__ . '/../api/warehouse/raw/tanks.php');
$warehouseIssueSource = file_get_contents(__DIR__ . '/../api/warehouse/raw/requisitions.php');
$productionRequestSource = file_get_contents(__DIR__ . '/../api/production/requisitions.php');
$receivingPage = file_get_contents(__DIR__ . '/../html/qc/milk_receiving.html');

gateAssert(str_contains($gradingSource, 'Milk exceeded the 3-hour receiving-to-storage window'), 'QC must automatically reject stale milk');
gateAssert(str_contains($deliverySource, "'rejected' : 'pending_qc'"), 'Receiving must retain stale arrivals as rejected audit records instead of creating a dead end');
gateAssert(str_contains($tankSource, 'past the 3-hour receiving window'), 'Warehouse must block stale tank assignment');
gateAssert(str_contains($warehouseIssueSource, "sqlRawMilkExpiresAtExpr('rmi', 'mr')"), 'Warehouse issuing must exclude milk after the gate deadline');
gateAssert(str_contains($productionRequestSource, "sqlRawMilkExpiresAtExpr('rmi', 'mr')"), 'Production shortage checks must exclude milk after the gate deadline');
gateAssert(str_contains($receivingPage, 'function localDateValue'), 'Receiving must use the local calendar date');
gateAssert(!str_contains($receivingPage, "new Date().toISOString().split('T')[0]"), 'Receiving must not combine a UTC date with a local time');

echo "Raw milk gate window tests passed.\n";
