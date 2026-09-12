<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$warehouseApi = file_get_contents($root . '/api/warehouse/raw/requisitions.php');
$warehousePage = file_get_contents($root . '/html/warehouse/raw/requisitions.html');
$productionApi = file_get_contents($root . '/api/production/runs.php');
$productionWorkbench = file_get_contents($root . '/html/production/run-workbench.html');

$checks = [
    'Packaging requests are server-enforced as all-or-nothing handovers' =>
        str_contains($warehouseApi, "=== 'packaging'")
        && str_contains($warehouseApi, 'Every packaging line must be released together.')
        && str_contains($warehouseApi, 'Cannot partially issue packaging request'),
    'Single-line API cannot bypass the complete packaging handover' =>
        str_contains($warehouseApi, 'Packaging requests cannot be issued partially.'),
    'Usable batch stock is checked before an ingredient or package is issued' =>
        str_contains($warehouseApi, 'getUsableIngredientBatches($db, $ingredientId, true)')
        && str_contains($warehouseApi, 'if ($totalAvailable < $quantity)')
        && str_contains($warehouseApi, 'Insufficient {$ingredientData'),
    'Any failed stock check rolls the complete database transaction back' =>
        str_contains($warehouseApi, '$db->rollBack();')
        && str_contains($warehouseApi, 'Response::error($e->getMessage(), 400)'),
    'Warehouse clearly blocks a short packaging request in the modal' =>
        str_contains($warehousePage, 'const packagingBlocked = packagingShortages.length > 0;')
        && str_contains($warehousePage, 'Packaging request cannot be fulfilled yet')
        && str_contains($warehousePage, "'disabled' : ''")
        && str_contains($warehousePage, "readonly aria-readonly=\"true\"")
        && str_contains($warehousePage, 'Cannot Fulfill — Stock Short'),
    'Cooking requests retain their existing partial-release workflow' =>
        str_contains($warehousePage, "const isPackaging = (currentRequisition.request_type || 'cooking') === 'packaging';")
        && str_contains($warehouseApi, "'Selected quantities released; requisition remains partial'"),
    'Production completion still requires the packaging request to be fulfilled' =>
        str_contains($productionApi, "\$packReq['status'] !== 'fulfilled'")
        && str_contains($productionApi, 'Warehouse must issue every packaging material first.'),
    'Production completion cannot silently accept unexplained milk volume' =>
        str_contains($productionApi, '$unaccountedMl = $initialVolumeMl - ($totalPackagedVolumeMl + $updatedTotalLossMl + $totalByproductMl)')
        && str_contains($productionApi, "'reconciliation_notes' => sprintf(")
        && str_contains($productionApi, 'material_reconciled = 1'),
    'Workbench explains an unbalanced run instead of inventing a note' =>
        str_contains($productionWorkbench, 'const remainingMl = initialVolumeMl - packagedVolumeMl - accountedLossMl - byproductMl;')
        && str_contains($productionWorkbench, 'Record the real loss or add a short note before sending to QC.')
        && !str_contains($productionWorkbench, "notes || 'Completed from workbench'"),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) {
        $failed[] = $label;
    }
}

if ($failed) {
    fwrite(STDERR, "Packaging fulfillment stock-guard checks failed.\n");
    exit(1);
}

echo "Packaging fulfillment stock-guard checks passed.\n";
