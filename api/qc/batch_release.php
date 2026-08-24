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
require_once dirname(__DIR__) . '/helpers/plain_text.php';
require_once dirname(__DIR__) . '/helpers/qc_count_discrepancy.php';

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

function qcUpsertReleaseDecision(PDO $db, array $batch, array $user, $decision, $notes = '')
{
    $code = 'QCR-' . date('Ymd') . '-' . str_pad((int) $batch['id'], 5, '0', STR_PAD_LEFT);
    $approved = $decision === 'approved';
    $stmt = $db->prepare("
        INSERT INTO qc_batch_release
            (release_code, batch_id, inspection_datetime, release_decision,
             rejection_reason, corrective_action, inspected_by, approved_by,
             approval_datetime, notes)
        VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            inspection_datetime = VALUES(inspection_datetime),
            release_decision = VALUES(release_decision),
            rejection_reason = VALUES(rejection_reason),
            corrective_action = VALUES(corrective_action),
            inspected_by = VALUES(inspected_by),
            approved_by = VALUES(approved_by),
            approval_datetime = VALUES(approval_datetime),
            notes = VALUES(notes)
    ");
    $stmt->execute([
        $code,
        (int) $batch['id'],
        $decision,
        $decision === 'rejected' ? $notes : null,
        $decision === 'hold' ? $notes : null,
        (int) $user['user_id'],
        $approved ? (int) $user['user_id'] : null,
        $approved ? date('Y-m-d H:i:s') : null,
        $notes ?: null,
    ]);
}

function qcCreateCountDisposal(PDO $db, array $batch, array $snapshot, array $user, $resolutionType, $notes)
{
    $quantity = 0;
    foreach ($snapshot['lines'] as $line) {
        $quantity += max(0, (int) $line['expected_quantity'] - (int) $line['counted_quantity']);
    }
    if ($quantity <= 0) return null;

    $prefix = 'DSP-' . date('Ymd') . '-';
    $stmt = $db->prepare('SELECT disposal_code FROM disposals WHERE disposal_code LIKE ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$prefix . '%']);
    $last = (string) ($stmt->fetchColumn() ?: '');
    $next = $last ? ((int) substr($last, -4) + 1) : 1;
    $code = $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    $category = $resolutionType === 'damaged_disposal' ? 'damaged' : 'production_waste';
    $reason = ($resolutionType === 'damaged_disposal'
        ? 'Damaged or leaking finished units found during QC physical count. '
        : 'Finished units used for approved sampling or recorded production loss. ')
        . $notes;

    $insert = $db->prepare("
        INSERT INTO disposals
            (disposal_code, source_type, source_id, source_reference,
             product_id, product_name, quantity, unit, disposal_category,
             disposal_reason, disposal_method, status, initiated_by,
             initiated_at, notes)
        VALUES (?, 'production_batch', ?, ?, ?, ?, ?, 'pcs', ?, ?, 'other',
                'pending', ?, NOW(), ?)
    ");
    $insert->execute([
        $code,
        (int) $batch['id'],
        $batch['batch_code'],
        $batch['product_id'] ?? null,
        $batch['product_type'] ?? 'Finished product',
        $quantity,
        $category,
        $reason,
        (int) $user['user_id'],
        'Automatically opened from QC count discrepancy resolution; requires GM approval.',
    ]);
    return (int) $db->lastInsertId();
}

try {
    $db = Database::getInstance()->getConnection();
    ensureQcCountDiscrepancyTables($db);

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

                // Production's immutable packaging record is shown beside QC's
                // physical count. Any later released-quantity correction lives
                // in the discrepancy record; the original is never overwritten.
                $batch['packaging_lines'] = qcGetBatchPackagingLines(
                    $db,
                    (int) $batch['id'],
                    (int) ($batch['run_id'] ?? 0)
                );
                $batch['production_packed_total'] = array_sum(array_map(
                    fn($line) => (int) ($line['quantity'] ?? 0),
                    $batch['packaging_lines']
                ));
                $batch['production_packed_lines'] = count($batch['packaging_lines']);
                $batch['count_discrepancy'] = qcGetLatestCountDiscrepancy($db, (int) $batch['id']);
                $effectiveLines = qcGetEffectiveReleasedPackagingLines(
                    $db,
                    (int) $batch['id'],
                    (int) ($batch['run_id'] ?? 0)
                );
                $batch['qc_released_total'] = array_sum(array_map(
                    fn($line) => (int) ($line['quantity'] ?? 0),
                    $effectiveLines
                ));

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
            // Release, hold for count investigation, resolve a hold, or reject.
            $batchId = getParam('batch_id');
            $action = getParam('action');
            $qcNotes = hfPlainText(getParam('qc_notes', ''), 2000, true);
            $countLines = getParam('count_lines', []);
            if (is_string($countLines)) {
                $decoded = json_decode($countLines, true);
                $countLines = is_array($decoded) ? $decoded : [];
            }
            $holdReasonCategory = hfPlainText(getParam('hold_reason_category', ''), 40, false);
            $holdReasonNotes = hfPlainText(getParam('hold_reason_notes', ''), 2000, true);
            $resolutionType = hfPlainText(getParam('resolution_type', ''), 40, false);
            $resolutionNotes = hfPlainText(getParam('resolution_notes', ''), 2000, true);
            $releaseRequested = in_array($action, ['release', 'resolve_release'], true);

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
            if (!$action || !in_array($action, ['release', 'hold', 'resolve_release', 'reject'], true)) {
                $errors['action'] = 'Choose Release, Place on Hold, Resolve and Release, or Reject';
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

            $packagingLines = qcGetBatchPackagingLines($db, (int) $batchId, (int) ($batch['run_id'] ?? 0));
            $countSnapshot = qcBuildCountSnapshot($packagingLines, is_array($countLines) ? $countLines : []);

            if (in_array($action, ['release', 'hold', 'resolve_release'], true) && !$countSnapshot['success']) {
                Response::validationError($countSnapshot['errors']);
            }
            $hasSkuCountMismatch = $countSnapshot['success'] && count(array_filter(
                $countSnapshot['lines'],
                fn($line) => (int) $line['variance'] !== 0
            )) > 0;

            if ($action === 'hold') {
                $allowedReasons = ['production_entry_error', 'damaged', 'sampling', 'missing', 'wrong_sku', 'other'];
                $holdErrors = [];
                if (!$hasSkuCountMismatch) {
                    $holdErrors['count_lines'] = 'The count matches Production. Use Release for Sale instead of a count hold.';
                }
                if (!in_array($holdReasonCategory, $allowedReasons, true)) {
                    $holdErrors['hold_reason_category'] = 'Select why the physical count differs';
                }
                if ($holdReasonNotes === '') {
                    $holdErrors['hold_reason_notes'] = 'Describe what was recounted and what must be investigated';
                }
                if (!empty($holdErrors)) {
                    Response::validationError($holdErrors);
                }

                $db->beginTransaction();
                try {
                    $db->prepare("UPDATE qc_batch_count_discrepancies SET status = 'superseded' WHERE batch_id = ? AND status = 'open'")
                        ->execute([(int) $batchId]);
                    $insert = $db->prepare("
                        INSERT INTO qc_batch_count_discrepancies
                            (batch_id, status, reason_category, reason_notes,
                             expected_total, counted_total, variance, opened_by, opened_at)
                        VALUES (?, 'open', ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $insert->execute([
                        (int) $batchId,
                        $holdReasonCategory,
                        $holdReasonNotes,
                        $countSnapshot['expected_total'],
                        $countSnapshot['counted_total'],
                        $countSnapshot['variance'],
                        (int) $currentUser['user_id'],
                    ]);
                    $discrepancyId = (int) $db->lastInsertId();
                    $lineInsert = $db->prepare("
                        INSERT INTO qc_batch_count_discrepancy_lines
                            (discrepancy_id, packaging_run_item_id, product_id,
                             product_name, size_ml, expected_quantity,
                             counted_quantity, variance)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    foreach ($countSnapshot['lines'] as $line) {
                        $lineInsert->execute([
                            $discrepancyId,
                            $line['packaging_run_item_id'],
                            $line['product_id'],
                            $line['product_name'],
                            $line['size_ml'],
                            $line['expected_quantity'],
                            $line['counted_quantity'],
                            $line['variance'],
                        ]);
                    }
                    $db->prepare("
                        UPDATE production_batches
                        SET qc_status = 'on_hold', qc_notes = ?,
                            qc_verified_boxes = NULL, qc_verified_pieces = ?,
                            qc_count_variance = ?, fg_received = 0
                        WHERE id = ?
                    ")->execute([
                        $holdReasonNotes,
                        $countSnapshot['counted_total'],
                        $countSnapshot['variance'],
                        (int) $batchId,
                    ]);
                    qcUpsertReleaseDecision($db, $batch, $currentUser, 'hold', $holdReasonNotes);
                    $db->commit();
                } catch (Throwable $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    throw $e;
                }

                logAudit($currentUser['user_id'], 'QC_COUNT_HOLD', 'production_batches', $batchId, null, [
                    'expected_total' => $countSnapshot['expected_total'],
                    'counted_total' => $countSnapshot['counted_total'],
                    'variance' => $countSnapshot['variance'],
                    'reason_category' => $holdReasonCategory,
                    'reason_notes' => $holdReasonNotes,
                ]);
                Response::success([
                    'batch_id' => (int) $batchId,
                    'status' => 'on_hold',
                    'discrepancy_id' => $discrepancyId,
                    'expected_total' => $countSnapshot['expected_total'],
                    'counted_total' => $countSnapshot['counted_total'],
                    'variance' => $countSnapshot['variance'],
                ], 'Batch placed on hold. The count difference was saved for investigation.');
            }

            $latestDiscrepancy = qcGetLatestCountDiscrepancy($db, (int) $batchId);
            if ($action === 'release' && $batch['qc_status'] === 'on_hold') {
                Response::validationError([
                    'resolution_type' => 'Resolve the saved physical-count hold before releasing this batch',
                ]);
            }
            if ($action === 'resolve_release') {
                $resolutionErrors = [];
                $allowedResolutions = ['recount_matched', 'items_found', 'production_record_corrected', 'damaged_disposal', 'sample_usage'];
                if ($batch['qc_status'] !== 'on_hold' || !$latestDiscrepancy || $latestDiscrepancy['status'] !== 'open') {
                    $resolutionErrors['resolution_type'] = 'This batch has no open physical-count hold to resolve';
                }
                if (!in_array($resolutionType, $allowedResolutions, true)) {
                    $resolutionErrors['resolution_type'] = 'Select how the count difference was resolved';
                }
                if ($resolutionNotes === '') {
                    $resolutionErrors['resolution_notes'] = 'Record who investigated and what was confirmed';
                }
                if (in_array($resolutionType, ['recount_matched', 'items_found'], true) && $hasSkuCountMismatch) {
                    $resolutionErrors['count_lines'] = 'This resolution requires every packaging SKU count to match Production';
                }
                if ($countSnapshot['variance'] > 0 && in_array($resolutionType, ['damaged_disposal', 'sample_usage'], true)) {
                    $resolutionErrors['count_lines'] = 'Damaged or sampled resolution cannot explain an overage';
                }
                if (!empty($resolutionErrors)) {
                    Response::validationError($resolutionErrors);
                }
            }

            // Server-side CCP gate: only for release (reject always allowed)
            $ccpValidation = null;
            if ($releaseRequested) {
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

                $qcTotalPieces = $countSnapshot['counted_total'];
                $productionTotalPieces = $countSnapshot['expected_total'];
                $countVariance = $countSnapshot['variance'];

                if ($action === 'release' && $hasSkuCountMismatch) {
                    Response::validationError([
                        'qc_count_mismatch' => "Count mismatch: you counted {$qcTotalPieces} pieces but production recorded {$productionTotalPieces}. "
                            . "Variance: " . ($countVariance > 0 ? "+" : "") . "{$countVariance}. "
                            . "Choose Place on Hold and record the reason before releasing.",
                    ]);
                }
            }

            // Begin transaction
            $db->beginTransaction();

            try {
                $newStatus = $releaseRequested ? 'released' : 'rejected';
                $releasedAt = $releaseRequested ? date('Y-m-d H:i:s') : null;
                $releasedBy = $releaseRequested ? $currentUser['user_id'] : null;
                $disposalId = null;

                if ($action === 'resolve_release') {
                    if (in_array($resolutionType, ['damaged_disposal', 'sample_usage'], true)) {
                        $disposalId = qcCreateCountDisposal(
                            $db,
                            $batch,
                            $countSnapshot,
                            $currentUser,
                            $resolutionType,
                            $resolutionNotes
                        );
                    }
                    $lineUpdate = $db->prepare("
                        UPDATE qc_batch_count_discrepancy_lines
                        SET counted_quantity = ?, variance = ?, released_quantity = ?
                        WHERE discrepancy_id = ? AND packaging_run_item_id = ?
                    ");
                    foreach ($countSnapshot['lines'] as $line) {
                        $lineUpdate->execute([
                            $line['counted_quantity'],
                            $line['variance'],
                            $line['counted_quantity'],
                            (int) $latestDiscrepancy['id'],
                            $line['packaging_run_item_id'],
                        ]);
                    }
                    $db->prepare("
                        UPDATE qc_batch_count_discrepancies
                        SET status = 'resolved', counted_total = ?, variance = ?,
                            resolution_type = ?, resolution_notes = ?, disposal_id = ?,
                            resolved_by = ?, resolved_at = NOW()
                        WHERE id = ? AND status = 'open'
                    ")->execute([
                        $countSnapshot['counted_total'],
                        $countSnapshot['variance'],
                        $resolutionType,
                        $resolutionNotes,
                        $disposalId,
                        (int) $currentUser['user_id'],
                        (int) $latestDiscrepancy['id'],
                    ]);
                    $qcNotes = trim($qcNotes . ($qcNotes ? ' · ' : '') . 'Count hold resolved: ' . $resolutionNotes);
                } elseif ($action === 'reject' && $latestDiscrepancy && $latestDiscrepancy['status'] === 'open') {
                    $db->prepare("
                        UPDATE qc_batch_count_discrepancies
                        SET status = 'resolved', resolution_type = 'rejected_batch',
                            resolution_notes = ?, resolved_by = ?, resolved_at = NOW()
                        WHERE id = ?
                    ")->execute([
                        $qcNotes,
                        (int) $currentUser['user_id'],
                        (int) $latestDiscrepancy['id'],
                    ]);
                }

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
                if ($releaseRequested && empty($batch['barcode'])) {
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
                    $releaseRequested ? ($packagingIntegrityPassed ? 1 : 0) : null,
                    $releaseRequested ? ($labelingPassed ? 1 : 0) : null,
                    null,
                    $releaseRequested ? ($countSnapshot['counted_total'] ?? 0) : null,
                    $releaseRequested ? ($countSnapshot['variance'] ?? 0) : null,
                    $batchId,
                ]);

                qcUpsertReleaseDecision(
                    $db,
                    $batch,
                    $currentUser,
                    $releaseRequested ? 'approved' : 'rejected',
                    $action === 'resolve_release' ? $resolutionNotes : $qcNotes
                );

                // Note: fg_received is explicitly set to 0 when QC releases a batch.
                // The FG Warehouse team will set fg_received = 1 when they physically
                // receive the batch into a specific chiller via the FG receiving page.

                $db->commit();

                // Log audit
                logAudit($currentUser['user_id'], $releaseRequested ? 'RELEASE' : 'REJECT', 'production_batches', $batchId, null, [
                    'action' => $action,
                    'qc_notes' => $qcNotes,
                    'ccp_summary' => $summary,
                    'count_snapshot' => $releaseRequested ? $countSnapshot : null,
                    'resolution_type' => $resolutionType ?: null,
                    'disposal_id' => $disposalId,
                ]);

                $message = $releaseRequested
                    ? ($action === 'resolve_release' ? 'Count hold resolved and verified quantity released.' : 'Batch released successfully!')
                    : 'Batch rejected';

                Response::success([
                    'batch_id' => $batchId,
                    'batch_code' => $batch['batch_code'],
                    'status' => $newStatus,
                    'barcode' => $barcode ?: $batch['barcode'],
                    'ccp_summary' => $summary,
                    'packaging_integrity_passed' => $releaseRequested ? $packagingIntegrityPassed : null,
                    'labeling_passed' => $releaseRequested ? $labelingPassed : null,
                    'qc_verified_boxes' => null,
                    'qc_verified_pieces' => $releaseRequested ? ($countSnapshot['counted_total'] ?? 0) : null,
                    'qc_count_variance' => $releaseRequested ? ($countSnapshot['variance'] ?? 0) : null,
                    'disposal_id' => $disposalId,
                ], $message);

            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
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
