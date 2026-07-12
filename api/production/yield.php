<?php
/**
 * Highland Fresh — Yield Calculation & Reconciliation API
 *
 * Provides real-time net yield, stage-by-stage breakdown, and
 * full material reconciliation for production runs.
 *
 * GET  ?run_id=X                - Current yield for a run
 * GET  ?run_id=X&action=summary - Full reconciliation breakdown
 * POST ?action=calculate        - Force recalculation + save snapshot
 *
 * @version 1.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/helpers/yield_helpers.php';

$currentUser = Auth::requireRole(['production_staff', 'general_manager', 'qc_officer']);

try {
    $db = Database::getInstance()->getConnection();

    switch ($requestMethod) {

        case 'GET':
            $runId = getParam('run_id');
            $action = getParam('action');

            if (!$runId) {
                Response::error('run_id is required', 400);
            }

            // Verify run exists
            $runStmt = $db->prepare("
                SELECT pr.id, pr.run_code, pr.initial_volume_ml, pr.total_loss_ml,
                       pr.total_byproduct_ml, pr.net_yield_ml, pr.material_reconciled,
                       pr.reconciliation_notes, pr.status, pr.actual_quantity,
                       mr.expected_yield, mr.product_name, mr.base_milk_liters
                FROM production_runs pr
                JOIN master_recipes mr ON mr.id = pr.recipe_id
                WHERE pr.id = ?
            ");
            $runStmt->execute([$runId]);
            $run = $runStmt->fetch();

            if (!$run) {
                Response::error('Production run not found', 404);
            }

            // Full reconciliation summary
            if ($action === 'summary') {
                // Loss breakdown by type
                $lossBreakdown = $db->prepare("
                    SELECT loss_type, SUM(loss_volume_ml) AS total_ml
                    FROM production_losses
                    WHERE production_run_id = ?
                    GROUP BY loss_type
                ");
                $lossBreakdown->execute([$runId]);
                $losses = [];
                foreach ($lossBreakdown->fetchAll() as $row) {
                    $losses[$row['loss_type']] = (float)$row['total_ml'];
                }

                // Byproduct breakdown
                $byproductBreakdown = $db->prepare("
                    SELECT byproduct_type,
                        SUM(CASE WHEN unit = 'liters' THEN quantity * 1000
                                 WHEN unit = 'ml' THEN quantity
                                 WHEN unit = 'kg' THEN quantity * 1000
                                 ELSE quantity END) AS total_ml
                    FROM production_byproducts
                    WHERE run_id = ?
                    GROUP BY byproduct_type
                ");
                $byproductBreakdown->execute([$runId]);
                $byproducts = [];
                foreach ($byproductBreakdown->fetchAll() as $row) {
                    $byproducts[$row['byproduct_type']] = (float)$row['total_ml'];
                }

                // Finished product total (from packaging if exists)
                $finishedStmt = $db->prepare("
                    SELECT COALESCE(SUM(pe.actual_units * pe.packaging_size_ml), 0)
                    FROM packaging_estimates pe
                    WHERE pe.production_run_id = ? AND pe.actual_units IS NOT NULL
                ");
                $finishedStmt->execute([$runId]);
                $finishedProductMl = (float)$finishedStmt->fetchColumn();

                $initialVolume = (float)$run['initial_volume_ml'];
                $totalLoss = (float)$run['total_loss_ml'];
                $totalByproduct = (float)$run['total_byproduct_ml'];
                $totalAccounted = $finishedProductMl + $totalLoss + $totalByproduct;
                $unaccountedMl = $initialVolume - $totalAccounted;
                $tolerance = max(50, $initialVolume * 0.01); // 50ml or 1%, whichever is larger

                Response::success([
                    'production_run_id' => (int)$runId,
                    'run_code' => $run['run_code'],
                    'initial_volume_ml' => $initialVolume,
                    'breakdown' => [
                        'finished_product_ml' => $finishedProductMl,
                        'losses' => $losses,
                        'losses_total_ml' => $totalLoss,
                        'byproducts' => $byproducts,
                        'byproducts_total_ml' => $totalByproduct,
                        'total_accounted_ml' => $totalAccounted,
                        'unaccounted_ml' => round($unaccountedMl, 2)
                    ],
                    'reconciliation' => [
                        'tolerance_ml' => $tolerance,
                        'within_tolerance' => abs($unaccountedMl) <= $tolerance,
                        'reconciled' => (bool)$run['material_reconciled'],
                        'notes' => $run['reconciliation_notes']
                    ]
                ], 'Reconciliation summary');
                break;
            }

            // Standard yield response
            $initialVolume = (float)$run['initial_volume_ml'];
            $expectedYieldRatio = (int)$run['expected_yield'] > 0 && (float)$run['base_milk_liters'] > 0
                ? ((int)$run['expected_yield'] / (float)$run['base_milk_liters'])
                : null;

            Response::success([
                'production_run_id' => (int)$runId,
                'run_code' => $run['run_code'],
                'product_name' => $run['product_name'],
                'status' => $run['status'],
                'initial_volume_ml' => $initialVolume,
                'total_loss_ml' => (float)$run['total_loss_ml'],
                'total_byproduct_ml' => (float)$run['total_byproduct_ml'],
                'net_yield_ml' => (float)$run['net_yield_ml'],
                'yield_efficiency_percent' => $initialVolume > 0
                    ? round(((float)$run['net_yield_ml'] / $initialVolume) * 100, 2) : 0,
                'expected_yield_ratio' => $expectedYieldRatio,
                'material_reconciled' => (bool)$run['material_reconciled']
            ], 'Yield calculation retrieved');
            break;

        case 'POST':
            $action = $requestBody['action'] ?? getParam('action');
            $runId = $requestBody['production_run_id'] ?? $requestBody['run_id'] ?? null;

            if ($action !== 'calculate') {
                Response::error('Use action=calculate', 400);
            }

            if (!$runId) {
                Response::error('production_run_id is required', 400);
            }

            $runStmt = $db->prepare("
                SELECT id, status, initial_volume_ml FROM production_runs WHERE id = ?
            ");
            $runStmt->execute([$runId]);
            $run = $runStmt->fetch();

            if (!$run) {
                Response::error('Production run not found', 404);
            }

            if (!$run['initial_volume_ml']) {
                Response::error('Initial volume not set for this run', 400);
            }

            $db->beginTransaction();

            $updatedYield = recalculateRunYield($db, $runId);

            // Save a yield_calculations snapshot
            $snapshotStmt = $db->prepare("
                INSERT INTO yield_calculations
                    (production_run_id, stage, input_volume_ml, total_loss_ml, byproduct_volume_ml, net_yield_ml, yield_efficiency_percent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stage = $run['status'];
            if (!in_array($stage, ['pasteurization', 'processing', 'cooling', 'packaging'])) {
                $stage = 'processing';
            }

            $snapshotStmt->execute([
                $runId,
                $stage,
                $updatedYield['initial_volume_ml'],
                $updatedYield['total_loss_ml'],
                $updatedYield['total_byproduct_ml'],
                $updatedYield['net_yield_ml'],
                $updatedYield['yield_efficiency_percent']
            ]);

            $db->commit();

            Response::success($updatedYield, 'Yield recalculated');
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    Response::error('Database error: ' . $e->getMessage(), 500);
}
