<?php
$_SERVER["REQUEST_METHOD"] = "GET";
$_SERVER["HTTP_HOST"] = "localhost";
require "api/bootstrap.php";

$db = Database::getInstance()->getConnection();

// Mirror the available_raw_milk query from api/production/pasteurization.php
$issuedMilkStmt = $db->prepare("
    SELECT COALESCE(SUM(it.quantity), 0) as total_issued,
           MIN(it.created_at) as earliest_issued_at
    FROM inventory_transactions it
    JOIN material_requisitions ir ON ir.id = it.reference_id
    JOIN raw_milk_inventory rmi ON rmi.id = it.batch_id
    WHERE it.item_type = 'raw_milk'
      AND it.reference_type = 'requisition'
      AND it.quantity > 0
      AND ir.department = 'production'
      AND rmi.expiry_date >= CURDATE()
");
$issuedMilkStmt->execute();
$issuedStats = $issuedMilkStmt->fetch();

$usedMilkSql = "
    SELECT COALESCE(SUM(milk_liters_used), 0) as total_used
    FROM production_runs
    WHERE status IN ('planned', 'in_progress', 'completed', 'pasteurization', 'processing', 'cooling', 'packaging')
      AND (milk_source_type IS NULL OR milk_source_type = 'raw')
";
$usedMilkParams = [];
if (!empty($issuedStats['earliest_issued_at'])) {
    $usedMilkSql .= " AND created_at >= ?";
    $usedMilkParams[] = $issuedStats['earliest_issued_at'];
}
$usedMilkStmt = $db->prepare($usedMilkSql);
$usedMilkStmt->execute($usedMilkParams);
$usedStats = $usedMilkStmt->fetch();

$pastUsedStmt = $db->prepare("
    SELECT COALESCE(SUM(pr.input_milk_liters), 0) as pasteurization_used
    FROM pasteurization_runs pr
    WHERE pr.status IN ('in_progress', 'completed')
      AND EXISTS (
          SELECT 1 FROM inventory_transactions it
          WHERE it.item_type = 'raw_milk'
            AND it.reference_type = 'pasteurization_run'
            AND it.reference_id = pr.id
      )
");
$pastUsedStmt->execute();
$pastStats = $pastUsedStmt->fetch();

$totalIssued = (float) ($issuedStats['total_issued'] ?? 0);
$totalUsed = (float) ($usedStats['total_used'] ?? 0);
$pasteurizationUsed = (float) ($pastStats['pasteurization_used'] ?? 0);
$availableLiters = max(0, $totalIssued - $totalUsed - $pasteurizationUsed);

echo "============================================================\n";
echo "  V4.0.1 LIVE CHECK — available raw milk right now\n";
echo "============================================================\n\n";
printf("  total_issued:           %.4f L\n", $totalIssued);
printf("  total_used (prod runs): %.4f L\n", $totalUsed);
printf("  pasteurization_used:    %.4f L\n", $pasteurizationUsed);
printf("  availableLiters:        %.4f L\n", $availableLiters);
echo "\n";
echo "  Old UI display: \"" . number_format($availableLiters, 2) . "\"  -> user types this, server: " . (0.11 > $availableLiters ? "422 blocked" : "OK") . "\n";
echo "  New UI display: \"" . number_format($availableLiters, 3) . "\"  -> user types this, server: " . (number_format($availableLiters, 3) > $availableLiters ? "422 blocked" : "OK") . "\n";
echo "\n";

if ($availableLiters > 0) {
    echo "  You can now retry: type " . number_format($availableLiters, 3) . " in the input field and submit.\n";
} else {
    echo "  No raw milk available — request more from Warehouse Raw first.\n";
}
