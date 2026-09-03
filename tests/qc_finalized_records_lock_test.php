<?php

$root = dirname(__DIR__);
$batchApi = file_get_contents($root . '/api/qc/batch_release.php');
$batchPage = file_get_contents($root . '/html/qc/batch_release.html');
$gradingApi = file_get_contents($root . '/api/qc/milk_grading.php');
$gradingPage = file_get_contents($root . '/html/qc/milk_grading.html');
$receivingApi = file_get_contents($root . '/api/qc/deliveries.php');
$receivingPage = file_get_contents($root . '/html/qc/milk_receiving.html');

foreach (compact('batchApi', 'batchPage', 'gradingApi', 'gradingPage', 'receivingApi', 'receivingPage') as $name => $source) {
    if ($source === false) {
        fwrite(STDERR, "Unable to load {$name} source.\n");
        exit(1);
    }
}

$checks = [
    'Released and rejected batch decisions are rejected by the API' =>
        str_contains($batchApi, "\$finalizedStatuses = ['released', 'rejected']")
        && str_contains($batchApi, 'finalized and read-only'),
    'Batch finalization uses an optimistic status lock' =>
        substr_count($batchApi, 'WHERE id = ? AND qc_status = ?') >= 2
        && substr_count($batchApi, 'changed while you were reviewing it') >= 2,
    'Rejected batches expose only a read-only detail action' =>
        str_contains($batchPage, 'View read-only details')
        && str_contains($batchPage, 'Finalized QC decision — read-only'),
    'Rejected batch history identifies the actual inspector and decision time' =>
        str_contains($batchApi, 'u3.first_name as inspected_by_first')
        && str_contains($batchApi, 'qbr.inspection_datetime as qc_inspected_at')
        && str_contains($batchPage, 'b.qc_inspected_at'),
    'Milk grading API has no update or delete route' =>
        !str_contains($gradingApi, "case 'PUT':")
        && !str_contains($gradingApi, "case 'DELETE':"),
    'Milk grading results open a view-only modal' =>
        str_contains($gradingPage, 'onclick="viewTest(')
        && str_contains($gradingPage, 'Finalized lab result — read-only')
        && !preg_match('/onclick="[^\"]*(?:edit|update|delete)Test/i', $gradingPage),
    'Milk receiving API has no update or delete route' =>
        !str_contains($receivingApi, "case 'PUT':")
        && !str_contains($receivingApi, "case 'DELETE':"),
    'Finalized milk receiving rows do not expose Grade Milk' =>
        str_contains($receivingPage, "['pending_qc', 'pending_test', 'in_testing'].includes(d.status)"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Failed: {$label}.\n");
        exit(1);
    }
}

echo "QC finalized-record lock tests passed.\n";
