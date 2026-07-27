<?php
/**
 * Highland Fresh System - Batch Release API
 *
 * QC verifies production batches before FG warehouse transfer.
 * Production CCP temperatures are pulled from production_ccp_logs
 * (no re-entry by QC). Release is blocked when required CCPs are
 * missing or failed.
 *
 * Endpoints:
 * GET  - List pending batches / Get batch release details / Get stats
 * PUT  - Approve/Reject batch release
 *
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/config/ccp_standards.php';

// Require QC role
$currentUser = Auth::requireRole(['qc_officer', 'general_manager']);

// Shared thresholds (single source of truth)
$ccpThresholds = ccp_public_thresholds();
if (!defined('PASTEURIZATION_TEMP')) {
    define('PASTEURIZATION_TEMP', $ccpThresholds['pasteurization_min']);
}
if (!defined('MAX_COOLING_TEMP')) {
    define('MAX_COOLING_TEMP', $ccpThresholds['cooling_max']);
}

try {
    $db = Database::getInstance()->getConnection();

    switch ($requestMethod) {
        case 'GET':
            $batchId = getParam('batch_id');
            $status = getParam('status');
            $action = getParam('action');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;

            // Get stats
            if ($action === 'stats') {
                $statsStmt = $db->query("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN qc_status = 'pending' OR qc_status = 'on_hold' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN qc_status = 'released' THEN 1 ELSE 0 END) as released,
                        SUM(CASE WHEN qc_status = 'rejected' THEN 1 ELSE 0 END) as rejected
                    FROM production_batches
                ");
                $stats = $statsStmt->fetch();

                Response::success([
                    'total' => (int) $stats['total'],
                    'pending' => (int) $stats['pending'],
                    'released' => (int) $stats['released'],
                    'rejected' => (int) $stats['rejected'],
                    'ccp_standards' => ccp_public_thresholds(),
                ], 'Stats retrieved successfully');
            }

            if ($batchId) {
                // Get single batch details with full CCP integration
                $stmt = $db->prepare("
                    SELECT pb.*,
                           mr.product_name, mr.product_type as recipe_type, mr.variant as recipe_variant,
                           u.first_name as created_by_first, u.last_name as created_by_last,
                           CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as produced_by_name,
                           u2.first_name as released_by_first, u2.last_name as released_by_last,
                           CONCAT(COALESCE(u2.first_name, ''), ' ', COALESCE(u2.last_name, '')) as verified_by_name
                    FROM production_batches pb
                    LEFT JOIN master_recipes mr ON pb.recipe_id = mr.id
                    LEFT JOIN users u ON pb.created_by = u.id
                    LEFT JOIN users u2 ON pb.released_by = u2.id
                    WHERE pb.id = ?
                ");
                $stmt->execute([$batchId]);
                $batch = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$batch) {
                    Response::notFound('Batch not found');
                }

                $batch = ccp_enrich_batch($db, $batch, true, true);

                // V4.1: Include production's packaging summary so QC knows what to verify
                $pkgSummaryStmt = $db->prepare("
                    SELECT COALESCE(SUM(pri.quantity), 0) AS total_pieces,
                           COUNT(DISTINCT pri.id) AS line_count
                    FROM packaging_run_items pri
                    JOIN packaging_runs pr ON pri.packaging_run_id = pr.id
                    WHERE pr.batch_id = ? OR pr.production_run_id = ?
                ");
                $pkgSummaryStmt->execute([(int) $batch['id'], (int) ($batch['run_id'] ?? 0)]);
                $pkgRow = $pkgSummaryStmt->fetch(PDO::FETCH_ASSOC);
                $batch['production_packed_total'] = (int) ($pkgRow['total_pieces'] ?? 0);
                $batch['production_packed_lines'] = (int) ($pkgRow['line_count'] ?? 0);

                // Also fetch the packaging line details for display
                $pkgDetailStmt = $db->prepare("
                    SELECT pri.product_name, pri.product_variant, pri.size_ml,
                           pri.unit_measure, pri.quantity
                    FROM packaging_run_items pri
                    JOIN packaging_runs pr ON pri.packaging_run_id = pr.id
                    WHERE pr.batch_id = ? OR pr.production_run_id = ?
                    ORDER BY pri.id
                ");
                $pkgDetailStmt->execute([(int) $batch['id'], (int) ($batch['run_id'] ?? 0)]);
                $batch['packaging_lines'] = $pkgDetailStmt->fetchAll(PDO::FETCH_ASSOC);

                // Give the QC modal the pack size so physical counts can be entered
                // as boxes + loose pieces when the product master supports it.
                $packConfigStmt = $db->prepare("
                    SELECT
                        COALESCE(NULLIF(p.pieces_per_box, 0), 1) AS pieces_per_box,
                        COALESCE(NULLIF(p.base_unit, ''), 'piece') AS base_unit,
                        COALESCE(NULLIF(p.box_unit, ''), 'box') AS box_unit
                    FROM packaging_run_items pri
                    JOIN packaging_runs pr ON pri.packaging_run_id = pr.id
                    JOIN products p ON p.id = pri.product_id
                    WHERE pr.batch_id = ? OR pr.production_run_id = ?
                    ORDER BY pri.id ASC
                    LIMIT 1
                ");
                $packConfigStmt->execute([(int) $batch['id'], (int) ($batch['run_id'] ?? 0)]);
                $packConfig = $packConfigStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $batch['pieces_per_box'] = (int) ($packConfig['pieces_per_box'] ?? 1);
                $batch['base_unit'] = $packConfig['base_unit'] ?? 'piece';
                $batch['box_unit'] = $packConfig['box_unit'] ?? 'box';

                Response::success($batch, 'Batch retrieved successfully');
            }

            // List batches based on status filter
            $where = "WHERE 1=1";
            $params = [];

            if ($status) {
                if ($status === 'pending') {
                    $where .= " AND (pb.qc_status = 'pending' OR pb.qc_status = 'on_hold')";
                } elseif ($status === 'released') {
                    // Only return released batches that are still in their valid date range.
                    // Expired batches should not be available for new label printing.
                    $where .= " AND pb.qc_status = 'released' AND pb.expiry_date >= CURDATE()";
                } elseif ($status === 'rejected') {
                    $where .= " AND pb.qc_status = 'rejected'";
                }
            }

            // Get total count
            $countStmt = $db->prepare("
                SELECT COUNT(*) as total 
                FROM production_batches pb 
                {$where}
            ");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];

            // Get batches
            $stmt = $db->prepare("
                SELECT pb.*,
                       mr.product_name, mr.product_type as recipe_type, mr.variant as recipe_variant,
                       u.first_name as created_by_first, u.last_name as created_by_last,
                       u2.first_name as released_by_first, u2.last_name as released_by_last
                FROM production_batches pb
                LEFT JOIN master_recipes mr ON pb.recipe_id = mr.id
                LEFT JOIN users u ON pb.created_by = u.id
                LEFT JOIN users u2 ON pb.released_by = u2.id
                {$where}
                ORDER BY pb.manufacturing_date DESC, pb.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Pull production CCP temps + pass/fail summary for each row
            $batches = ccp_enrich_batches_list($db, $batches);

            Response::paginated($batches, $total, $page, $limit, 'Batches retrieved successfully');
            break;

        case 'PUT':
            // Release or Reject a batch
            $batchId = getParam('batch_id');
            $action = getParam('action'); // 'release' or 'reject'
            $qcNotes = trim(getParam('qc_notes', ''));

            // Organoleptic checks
            $organolepticTaste = filter_var(getParam('organoleptic_taste', false), FILTER_VALIDATE_BOOLEAN);
            $organolepticAppearance = filter_var(getParam('organoleptic_appearance', false), FILTER_VALIDATE_BOOLEAN);
            $organolepticSmell = filter_var(getParam('organoleptic_smell', false), FILTER_VALIDATE_BOOLEAN);
            $ccpAcknowledged = filter_var(getParam('ccp_acknowledged', false), FILTER_VALIDATE_BOOLEAN);

            // V4.1: Packaging integrity + physical count verification
            $packagingIntegrityPassed = filter_var(getParam('packaging_integrity_passed', false), FILTER_VALIDATE_BOOLEAN);
            $labelingPassed = filter_var(getParam('labeling_passed', false), FILTER_VALIDATE_BOOLEAN);
            $qcVerifiedBoxes = getParam('qc_verified_boxes', null);
            $qcVerifiedPieces = getParam('qc_verified_pieces', null);
            if ($qcVerifiedBoxes !== null) $qcVerifiedBoxes = (int) $qcVerifiedBoxes;
            if ($qcVerifiedPieces !== null) $qcVerifiedPieces = (int) $qcVerifiedPieces;

            // Validation
            $errors = [];
            if (!$batchId) {
                $errors['batch_id'] = 'Batch ID is required';
            }
            if (!$action || !in_array($action, ['release', 'reject'], true)) {
                $errors['action'] = 'Action must be release or reject';
            }

            if ($action === 'reject' && empty($qcNotes)) {
                $errors['qc_notes'] = 'Please provide a reason for rejection';
            }

            if (!empty($errors)) {
                Response::validationError($errors);
            }

            // Get batch
            $batchStmt = $db->prepare("SELECT * FROM production_batches WHERE id = ?");
            $batchStmt->execute([$batchId]);
            $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);

            if (!$batch) {
                Response::notFound('Batch not found');
            }

            if ($batch['qc_status'] === 'released') {
                Response::error('Batch has already been released', 400);
            }

            // Server-side CCP gate: only for release (reject always allowed)
            $ccpValidation = null;
            if ($action === 'release') {
                $ccpValidation = ccp_validate_for_release($db, $batch);
                if (!$ccpValidation['ok']) {
                    Response::validationError($ccpValidation['errors']);
                }
                if (!$ccpAcknowledged) {
                    Response::validationError([
                        'ccp_acknowledged' => 'QC officer must confirm CCP parameters were reviewed against standards',
                    ]);
                }

                // V4.1: Packaging integrity is mandatory for release
                if (!$packagingIntegrityPassed) {
                    Response::validationError([
                        'packaging_integrity_passed' => 'You must confirm packaging integrity (seals, caps, no leaks) before releasing',
                    ]);
                }
                if (!$labelingPassed) {
                    Response::validationError([
                        'labeling_passed' => 'You must confirm labeling is correct before releasing',
                    ]);
                }

                // V4.1: Physical count verification — QC must enter what they counted
                if ($qcVerifiedBoxes === null && $qcVerifiedPieces === null) {
                    Response::validationError([
                        'qc_verified_pieces' => 'Enter the number of pieces you physically counted (boxes and/or loose pieces)',
                    ]);
                }

                // Compare QC count against what production recorded.
                // If QC counted sealed boxes, convert boxes into pieces using the
                // product's pack size. Loose pieces remain as-is.
                $piecesPerBoxStmt = $db->prepare("
                    SELECT COALESCE(NULLIF(p.pieces_per_box, 0), 1) AS pieces_per_box
                    FROM packaging_run_items pri
                    JOIN packaging_runs pr ON pri.packaging_run_id = pr.id
                    JOIN products p ON p.id = pri.product_id
                    WHERE pr.batch_id = ? OR pr.production_run_id = ?
                    ORDER BY pri.id ASC
                    LIMIT 1
                ");
                $piecesPerBoxStmt->execute([(int) $batchId, (int) ($batch['run_id'] ?? 0)]);
                $piecesPerBox = max(1, (int) ($piecesPerBoxStmt->fetchColumn() ?: 1));
                $qcTotalPieces = (($qcVerifiedBoxes ?? 0) * $piecesPerBox) + ($qcVerifiedPieces ?? 0);
                $productionTotalPieces = 0;

                // Get production's total from packaging_run_items linked to this batch
                $pkgStmt = $db->prepare("
                    SELECT COALESCE(SUM(pri.quantity), 0) AS total_packed
                    FROM packaging_run_items pri
                    JOIN packaging_runs pr ON pri.packaging_run_id = pr.id
                    WHERE pr.batch_id = ? OR pr.production_run_id = ?
                ");
                $pkgStmt->execute([(int) $batchId, (int) ($batch['run_id'] ?? 0)]);
                $productionTotalPieces = (int) $pkgStmt->fetchColumn();

                // If no packaging records exist, fall back to batch actual_yield
                if ($productionTotalPieces <= 0) {
                    $productionTotalPieces = (int) ($batch['actual_yield'] ?? 0);
                }

                $countVariance = $qcTotalPieces - $productionTotalPieces;

                if ($countVariance !== 0) {
                    Response::validationError([
                        'qc_count_mismatch' => "Count mismatch: you counted {$qcTotalPieces} pieces but production recorded {$productionTotalPieces}. "
                            . "Variance: " . ($countVariance > 0 ? "+" : "") . "{$countVariance}. "
                            . "Re-count or investigate before releasing.",
                    ]);
                }
            }

            // Begin transaction
            $db->beginTransaction();

            try {
                $newStatus = $action === 'release' ? 'released' : 'rejected';
                $releasedAt = $action === 'release' ? date('Y-m-d H:i:s') : null;
                $releasedBy = $action === 'release' ? $currentUser['user_id'] : null;

                // Keep denormalized temps in sync from production logs on release
                $summary = $ccpValidation['summary'] ?? ccp_build_summary(
                    ccp_get_latest_logs_for_run($db, $batch['run_id'] ?? null),
                    $batch['pasteurization_temp'] ?? null,
                    $batch['cooling_temp'] ?? null
                );
                $pasteTemp = $summary['pasteurization_temp'] ?? $batch['pasteurization_temp'];
                $coolTemp = $summary['cooling_temp'] ?? $batch['cooling_temp'];

                // Generate barcode if releasing
                $barcode = null;
                if ($action === 'release' && empty($batch['barcode'])) {
                    $barcode = $batch['batch_code'] . '-' . date('ymd', strtotime($batch['manufacturing_date']));
                }

                // Update batch
                $updateStmt = $db->prepare("
                    UPDATE production_batches SET
                        qc_status = ?,
                        qc_released_at = ?,
                        released_by = ?,
                        released_at = ?,
                        organoleptic_taste = ?,
                        organoleptic_appearance = ?,
                        organoleptic_smell = ?,
                        qc_notes = ?,
                        barcode = COALESCE(?, barcode),
                        pasteurization_temp = COALESCE(?, pasteurization_temp),
                        cooling_temp = COALESCE(?, cooling_temp),
                        packaging_integrity_passed = ?,
                        labeling_passed = ?,
                        qc_verified_boxes = ?,
                        qc_verified_pieces = ?,
                        qc_count_variance = ?,
                        fg_received = 0
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $newStatus,
                    $releasedAt,
                    $releasedBy,
                    $releasedAt,
                    $organolepticTaste ? 1 : 0,
                    $organolepticAppearance ? 1 : 0,
                    $organolepticSmell ? 1 : 0,
                    $qcNotes,
                    $barcode,
                    $pasteTemp,
                    $coolTemp,
                    $action === 'release' ? ($packagingIntegrityPassed ? 1 : 0) : null,
                    $action === 'release' ? ($labelingPassed ? 1 : 0) : null,
                    $action === 'release' ? $qcVerifiedBoxes : null,
                    $action === 'release' ? $qcVerifiedPieces : null,
                    $action === 'release' ? ($countVariance ?? 0) : null,
                    $batchId,
                ]);

                // Note: fg_received is explicitly set to 0 when QC releases a batch.
                // The FG Warehouse team will set fg_received = 1 when they physically
                // receive the batch into a specific chiller via the FG receiving page.

                $db->commit();

                // Log audit
                logAudit($currentUser['user_id'], $action === 'release' ? 'RELEASE' : 'REJECT', 'production_batches', $batchId, null, [
                    'action' => $action,
                    'qc_notes' => $qcNotes,
                    'ccp_summary' => $summary,
                ]);

                $message = $action === 'release' ? '✅ Batch released successfully!' : '❌ Batch rejected';

                Response::success([
                    'batch_id' => $batchId,
                    'batch_code' => $batch['batch_code'],
                    'status' => $newStatus,
                    'barcode' => $barcode ?: $batch['barcode'],
                    'ccp_summary' => $summary,
                    'packaging_integrity_passed' => $action === 'release' ? $packagingIntegrityPassed : null,
                    'labeling_passed' => $action === 'release' ? $labelingPassed : null,
                    'qc_verified_boxes' => $action === 'release' ? $qcVerifiedBoxes : null,
                    'qc_verified_pieces' => $action === 'release' ? $qcVerifiedPieces : null,
                    'qc_count_variance' => $action === 'release' ? ($countVariance ?? 0) : null,
                ], $message);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    error_log("Batch Release API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
