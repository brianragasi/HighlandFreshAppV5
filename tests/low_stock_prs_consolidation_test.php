<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sidebar = file_get_contents($root . '/js/warehouse/raw-sidebar.js');
$dashboard = file_get_contents($root . '/html/warehouse/raw/dashboard.html');
$prs = file_get_contents($root . '/html/warehouse/raw/purchase_requests.html');
$validation = file_get_contents($root . '/html/warehouse/raw/reorder_alerts.html');
$validationApi = file_get_contents($root . '/api/warehouse/raw/stock_validations.php');
$legacyApi = file_get_contents($root . '/api/purchasing/purchase_requests.php');

$checks = [
    'Warehouse navigation exposes Stock Validation instead of PRS creation' =>
        str_contains($sidebar, "label: 'Stock Validation'")
        && str_contains($sidebar, "href: 'reorder_alerts.html'")
        && !str_contains($sidebar, "label: 'Low Stock & PRS'"),
    'Dashboard low-stock actions open Stock Validation' =>
        substr_count($dashboard, 'href="reorder_alerts.html"') >= 3
        && str_contains($dashboard, 'Validate Stock'),
    'Warehouse sends confirmed shelf counts directly to the new endpoint' =>
        str_contains($validation, "'/warehouse/raw/stock_validations.php?action=validate'")
        && str_contains($validation, 'Confirm Stock Check')
        && !str_contains($validation, "window.location.replace('purchase_requests.html?panel=reorder')"),
    'New confirmations are stored separately from old PRSs' =>
        str_contains($validationApi, 'INSERT INTO stock_validations')
        && str_contains($validationApi, 'INSERT INTO stock_validation_items')
        && !str_contains($validationApi, 'INSERT INTO purchase_requests'),
    'Old PRS page is history-only and new PRS creation is retired' =>
        str_contains($prs, 'Old PRS History')
        && str_contains($prs, 'Validate Low Stock')
        && str_contains($legacyApi, 'New Purchase Request Slips are retired'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}
if ($failed) {
    fwrite(STDERR, "Low-stock validation workflow checks failed.\n");
    exit(1);
}
echo "Low-stock validation workflow checks passed.\n";
