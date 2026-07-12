<?php
/**
 * Highland Fresh — Production Loss Recording API
 *
 * Records material losses at each production stage. After every loss entry,
 * automatically recalculates net yield and triggers a revised packaging estimate.
 *
 * GET  ?run_id=X             - All losses for a run
 * GET  ?run_id=X&stage=X     - Losses filtered by stage
 * POST                       - Record a new loss
 * DELETE ?id=X               - Remove erroneous loss (run must not be completed)
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
            $stage = getParam('stage');

            if (!$runId) {
                Response::error('run_id is required', 400);
            }

            $sql = "
                SELECT pl.*, u.name AS recorded_by_name
                FROM production_losses pl
                LEFT JOIN users u ON u.id = pl.recorded_by
                WHERE pl.production_run_id = ?
            ";
            $params = [$runId];

            if ($stage) {
                $sql .= " AND pl.stage = ?";
                $params[] = $stage;
            }

            $sql .= " ORDER BY pl.recorded_at DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $losses = $stmt->fetchAll();

            // Summary by stage + type
            $summaryStmt = $db->prepare("
                SELECT stage, loss_type, SUM(loss_volume_ml) AS total_ml, COUNT(*) AS entries
                FROM production_losses
                WHERE production_run_id = ?
                GROUP BY stage, loss_type
                ORDER BY stage, loss_type
            ");
            $summaryStmt->execute([$runId]);
            $summary = $summaryStmt->fetchAll();

            Response::success([
                'losses' => $losses,
                'summary' => $summary,
                'total_count' => count($losses)
            ], 'Production losses retrieved');
            break;

        case 'POST':
            $runId = $requestBody['production_run_id'] ?? null;
            $stage = $requestBody['stage'] ?? null;
            $lossType = $requestBody['loss_type'] ?? null;
            $lossVolumeMl = $requestBody['loss_volume_ml'] ?? null;
            $notes = $requestBody['notes'] ?? null;

            $errors = [];
            if (!$runId) $errors[] = 'production_run_id is required';
            if (!$stage) $errors[] = 'stage is required';
            if (!$lossType) $errors[] = 'loss_type is required';
            if (!$lossVolumeMl || $lossVolumeMl <= 0) $errors[] = 'loss_volume_ml must be positive';

            $validStages = ['pasteurization', 'processing', 'cooling', 'packaging'];
            if ($stage && !in_array($stage, $validStages)) {
                $errors[] = 'stage must be one of: ' . implode(', ', $validStages);
            }

            $validTypes = ['evaporation', 'spillage', 'sampling', 'equipment_retention', 'other'];
            if ($lossType && !in_array($lossType, $validTypes)) {
                $errors[] = 'loss_type must be one of: ' . implode(', ', $validTypes);
            }

            if (!empty($errors)) {
                Response::validationError($errors);
            }

            // Verify run exists and is active
            $runStmt = $db->prepare("
                SELECT id, status, initial_volume_ml, total_loss_ml
                FROM production_runs WHERE id = ?
            ");
            $runStmt->execute([$runId]);
            $run = $runStmt->fetch();

            if (!$run) {
                Response::error('Production run not found', 404);
            }

            if (in_array($run['status'], ['completed', 'cancelled'])) {
                Response::error('Cannot record losses on a completed/cancelled run', 400);
            }

            if (!$run['initial_volume_ml']) {
                Response::error('Initial volume must be set before recording losses', 400);
            }

            $remainingVolume = (float)$run['initial_volume_ml'] - (float)$run['total_loss_ml'];
            if ((float)$lossVolumeMl > $remainingVolume) {
                Response::error(
                    "Loss volume ({$lossVolumeMl} mL) exceeds remaining volume ({$remainingVolume} mL)",
                    400
                );
            }

            $lossPercentage = round(((float)$lossVolumeMl / (float)$run['initial_volume_ml']) * 100, 2);

            $db->beginTransaction();

            $insertStmt = $db->prepare("
                INSERT INTO production_losses
                    (production_run_id, stage, loss_type, loss_volume_ml, loss_percentage, recorded_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
                $runId, $stage, $lossType, $lossVolumeMl, $lossPercentage, $currentUser['user_id'], $notes
            ]);
            $lossId = $db->lastInsertId();

            // Recalculate yield + auto-trigger revised packaging estimate
            $updatedYield = recalculateRunYield($db, $runId);

            $db->commit();

            Response::success([
                'loss_id' => (int)$lossId,
                'loss_percentage' => $lossPercentage,
                'updated_yield' => $updatedYield
            ], 'Loss recorded successfully', 201);
            break;

        case 'DELETE':
            $id = getParam('id');
            if (!$id) {
                Response::error('Loss id is required', 400);
            }

            $lossStmt = $db->prepare("
                SELECT pl.production_run_id, pr.status AS run_status
                FROM production_losses pl
                JOIN production_runs pr ON pr.id = pl.production_run_id
                WHERE pl.id = ?
            ");
            $lossStmt->execute([$id]);
            $loss = $lossStmt->fetch();

            if (!$loss) {
                Response::error('Loss record not found', 404);
            }

            if ($loss['run_status'] === 'completed') {
                Response::error('Cannot delete losses from a completed run', 400);
            }

            $db->beginTransaction();

            $delStmt = $db->prepare("DELETE FROM production_losses WHERE id = ?");
            $delStmt->execute([$id]);

            $updatedYield = recalculateRunYield($db, $loss['production_run_id']);

            $db->commit();

            Response::success([
                'deleted_id' => (int)$id,
                'updated_yield' => $updatedYield
            ], 'Loss record deleted');
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
