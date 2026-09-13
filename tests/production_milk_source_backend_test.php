<?php

$root = dirname(__DIR__);
$runs = file_get_contents($root . '/api/production/runs.php');
$ccp = file_get_contents($root . '/api/production/ccp_logs.php');
$products = file_get_contents($root . '/api/admin/products.php');
$page = file_get_contents($root . '/html/admin/products.html');

$checks = [
    'Production decides pasteurization from the recorded milk source' =>
        str_contains($runs, 'function productionRunNeedsPasteurization')
        && str_contains($runs, "milk_source_type'] ?? 'raw'"),
    'Already-pasteurized milk carries its source heat record' =>
        str_contains($runs, 'Carried from pasteurized milk batch')
        && str_contains($runs, 'source_pasteurization_temp'),
    'One production run uses one traceable pasteurized milk batch' =>
        str_contains($runs, 'Make this production run smaller so it uses one traceable milk batch')
        && str_contains($runs, '$deductStmt->execute([$requiredMilkLiters, $pasteurizedBatchId])'),
    'Yogurt consumes the actual pasteurized output from its own requisition' =>
        str_contains($runs, 'pr.output_milk_liters')
        && str_contains($runs, 'pr.requisition_id = ?')
        && str_contains($runs, '$requiredMilkLiters = $actualPasteurizedOutput;')
        && str_contains($runs, 'Pasteurize the raw milk issued for this requisition'),
    'New runs use the current product category instead of a stale recipe copy' =>
        str_contains($runs, "COALESCE(NULLIF(bp.category, ''), mr.product_type) AS effective_product_type"),
    'The server enforces the physical production order' =>
        str_contains($runs, 'productionRunNextStage($run)')
        && str_contains($runs, 'Follow the production steps in order'),
    'Duplicate floor pasteurization is rejected' =>
        str_contains($ccp, 'This milk was already pasteurized before this run'),
    'Category changes keep the active recipe aligned' =>
        str_contains($products, "UPDATE master_recipes SET product_type = ? WHERE base_product_id = ? AND is_active = 1"),
    'New products require a deliberate category choice' =>
        str_contains($page, '<option value="" disabled selected>Choose category</option>'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Failed: {$label}.\n");
        exit(1);
    }
}

echo "Production milk source backend tests passed.\n";
