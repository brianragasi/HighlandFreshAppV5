<?php
/**
 * Highland Fresh — Packaging Estimate API (Two-Stage Auto-Estimation)
 *
 * Stage 1 (initial): Auto-generated when initial volume is set on run start.
 * Stage 2 (revised): Auto-recalculated every time a loss is recorded.
 * Workers NEVER manually calculate packaging outputs.
 *
 * GET  ?run_id=X              - All estimates for a run
 * GET  ?run_id=X&type=initial - Initial estimate only
 * GET  ?run_id=X&type=revised - Latest revised estimate
 * POST                        - Generate/recalculate estimate
 * PUT  ?id=X                  - Update with actual packaged units
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
            $type = getParam('type');

            if (!$runId) {
                Response::error('run_id is required', 400);
            }

            $sql = "
                SELECT id, estimate_type, basis_volume_ml, packaging_size_ml,
                       packaging_label, estimated_units, actual_units, remainder_ml, created_at
                FROM packaging_estimates
                WHERE production_run_id = ?
            ";
            $params = [$runId];

            if ($type && in_array($type, ['initial', 'revised'])) {
                $sql .= " AND estimate_type = ?";
                $params[] = $type;
            }

            $sql .= " ORDER BY estimate_type ASC, packaging_size_ml DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $estimates = $stmt->fetchAll();

            // Group by estimate_type
            $grouped = ['initial' => [], 'revised' => []];
            foreach ($estimates as $est) {
                $grouped[$est['estimate_type']][] = $est;
            }

            $initialBasis = !empty($grouped['initial']) ? $grouped['initial'][0]['basis_volume_ml'] : null;
            $revisedBasis = !empty($grouped['revised']) ? $grouped['revised'][0]['basis_volume_ml'] : null;

            Response::success([
                'production_run_id' => (int)$runId,
                'initial_estimate' => [
                    'basis_volume_ml' => $initialBasis ? (float)$initialBasis : null,
                    'items' => $grouped['initial']
                ],
                'revised_estimate' => [
                    'basis_volume_ml' => $revisedBasis ? (float)$revisedBasis : null,
                    'items' => $grouped['revised']
                ],
                'has_revision' => !empty($grouped['revised'])
            ], 'Packaging estimates retrieved');
            break;

        case 'POST':
            $runId = $requestBody['production_run_id'] ?? null;
            $estimateType = $requestBody['estimate_type'] ?? null;
            $basisVolumeMl = $requestBody['basis_volume_ml'] ?? null;

            if (!$runId) {
                Response::error('production_run_id is required', 400);
            }

            $runStmt = $db->prepare("
                SELECT id, initial_volume_ml, net_yield_ml, status
                FROM production_runs WHERE id = ?
            ");
            $runStmt->execute([$runId]);
            $run = $runStmt->fetch();

            if (!$run) {
                Response::error('Production run not found', 404);
            }

            if (!$estimateType) {
                $estimateType = $run['net_yield_ml'] && $run['net_yield_ml'] != $run['initial_volume_ml']
                    ? 'revised' : 'initial';
            }

            if (!$basisVolumeMl) {
                $basisVolumeMl = $estimateType === 'initial'
                    ? $run['initial_volume_ml']
                    : ($run['net_yield_ml'] ?? $run['initial_volume_ml']);
            }

            if (!$basisVolumeMl || $basisVolumeMl <= 0) {
                Response::error('No volume available for estimation. Set initial_volume_ml first.', 400);
            }

            $db->beginTransaction();
            $result = generatePackagingEstimate($db, $runId, $estimateType, $basisVolumeMl);
            if (!$result['success']) {
                $db->rollBack();
                Response::error($result['message'], 400);
            }
            $db->commit();

            Response::success($result, 'Packaging estimate generated');
            break;

        case 'PUT':
            $id = getParam('id');
            if (!$id) {
                Response::error('Estimate id is required', 400);
            }

            $actualUnits = $requestBody['actual_units'] ?? null;
            if ($actualUnits === null) {
                Response::error('actual_units is required', 400);
            }

            $stmt = $db->prepare("UPDATE packaging_estimates SET actual_units = ? WHERE id = ?");
            $stmt->execute([(int)$actualUnits, $id]);

            if ($stmt->rowCount() === 0) {
                Response::error('Estimate not found', 404);
            }

            Response::success(['id' => (int)$id, 'actual_units' => (int)$actualUnits], 'Actual units recorded');
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
