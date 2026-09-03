<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$productionApi = file_get_contents($root . '/api/production/requisitions.php');
$productionRunsApi = file_get_contents($root . '/api/production/runs.php');
$productionPage = file_get_contents($root . '/html/production/requisitions.html');
$warehouseApi = file_get_contents($root . '/api/warehouse/raw/requisitions.php');
$warehousePage = file_get_contents($root . '/html/warehouse/raw/requisitions.html');
$gmApi = file_get_contents($root . '/api/admin/gm_approvals.php');
$gmPage = file_get_contents($root . '/html/admin/gm_approvals.html');
$recipeAdminApi = file_get_contents($root . '/api/admin/recipes.php');
$pasteurizationApi = file_get_contents($root . '/api/production/pasteurization.php');
$pasteurizationPage = file_get_contents($root . '/html/production/pasteurization.html');

$checks = [
    'server regenerates recipe items for planned and existing-run requests' =>
        str_contains($productionApi, 'getRequisitionRecipeItemsForPlan(')
        && str_contains($productionApi, 'getRequisitionRecipeItemsForRun($db, (int) $productionRunId)'),
    'recipe requests enter the Warehouse queue directly' =>
        str_contains($productionApi, "\$initialStatus = \$requiresGmExceptionReview ? 'pending' : 'approved'")
        && str_contains($productionApi, ": 'approved_recipe';")
        && str_contains($productionApi, "'status' => \$initialStatus"),
    'authorization basis is persisted and audited' =>
        str_contains($productionApi, 'ADD COLUMN authorization_basis')
        && str_contains($productionApi, 'CREATE_RECIPE_REQUISITION')
        && str_contains($productionApi, "'authorization_basis' => \$authorizationBasis"),
    'master recipe changes remain restricted to management' =>
        str_contains($recipeAdminApi, "Auth::requireRole(['general_manager', 'admin'])"),
    'Warehouse can immediately fulfill recipe-authorized requests' =>
        str_contains($warehouseApi, "status IN ('approved', 'partial', 'in_progress')")
        && str_contains($warehousePage, 'Recipe-authorized'),
    'management-controlled packaging BOM requests use the same direct handoff' =>
        str_contains($productionRunsApi, "'approved_packaging_bom'")
        && str_contains($warehousePage, 'BOM-authorized'),
    'Production explains the direct handoff without a normal GM button' =>
        str_contains($productionPage, 'Send to Warehouse')
        && str_contains($productionPage, 'Recipe-authorized request sent directly to Warehouse Raw.')
        && !str_contains($productionPage, 'Submit for GM Approval'),
    'unreleased recipe requests can still be cancelled safely' =>
        str_contains($productionApi, "in_array(\$requisition['status'], ['pending', 'approved'], true)")
        && str_contains($productionApi, 'SUM(issued_quantity)')
        && str_contains($productionApi, 'Warehouse has already issued material'),
    'GM workflow is retained only for exceptions' =>
        str_contains($productionApi, "'recipe_adjustment_exception'")
        && str_contains($gmApi, "authorization_basis = 'gm_exception'")
        && str_contains($pasteurizationApi, "'process_shortfall_exception'")
        && str_contains($pasteurizationPage, 'Sent for GM exception review.')
        && str_contains($gmPage, 'Material Reviews'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed) {
    fwrite(STDERR, "Recipe-authorized requisition checks failed.\n");
    exit(1);
}

echo "Recipe-authorized requisition checks passed.\n";
