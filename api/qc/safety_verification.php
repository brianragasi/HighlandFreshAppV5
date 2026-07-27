<?php
/**
 * Highland Fresh System - Safety Verification API
 *
 * Required before any yogurt transformation of near-expiry inventory.
 * QC Officer must confirm milk is safe for reprocessing.
 *
 * Endpoints:
 * GET  ?action=check&inventory_id=X&type=finished_goods  - Check if verified
 * GET  ?action=history&inventory_id=X                     - Get verification history
 * POST - Create safety verification record
 *
 * @package HighlandFresh
 * @version 4.0
 */

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Fatal PHP error',
            'error_detail' => $error['message'],
            'error_file' => basename($error['file']),
            'error_line' => $error['line']
        ]);
    }
});

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/bootstrap.php';

$currentUser = Auth::requireRole(['qc_officer', 'general_manager']);

try {
    $db = Database::getInstance()->getConnection();
    global $requestMethod;

    switch ($requestMethod) {
        case 'GET':
            $action = getParam('action');

            if ($action === 'check') {
                $inventoryId = (int) getParam('inventory_id', 0);
                $sourceType = getParam('source_type', 'finished_goods');

                if (!$inventoryId) {
                    Response::validationError(['inventory_id' => 'Inventory ID is required']);
                }

                $stmt = $db->prepare("
                    SELECT id, verification_code, status, temperature_reading,
                           organoleptic_passed, temperature_passed,
                           packaging_integrity_passed, visual_inspection_passed,
                           verified_by, verification_datetime, notes
                    FROM safety_verifications
                    WHERE inventory_id = ? AND source_type = ? AND status = 'approved'
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$inventoryId, $sourceType]);
                $verification = $stmt->fetch();

                Response::success([
                    'verified' => !!$verification,
                    'verification' => $verification
                ], $verification ? 'Safety verification found' : 'No safety verification found');
                return;

            }

            if ($action === 'history') {
                $inventoryId = (int) getParam('inventory_id', 0);
                if (!$inventoryId) {
                    Response::validationError(['inventory_id' => 'Inventory ID is required']);
                }

                $stmt = $db->prepare("
                    SELECT sv.*, u.first_name, u.last_name
                    FROM safety_verifications sv
                    LEFT JOIN users u ON sv.verified_by = u.id
                    WHERE sv.inventory_id = ?
                    ORDER BY sv.id DESC
                ");
                $stmt->execute([$inventoryId]);
                $history = $stmt->fetchAll();

                Response::success(['history' => $history], 'Verification history retrieved');
                return;
            }

            Response::error('Invalid action', 400);
            break;

        case 'POST':
            $inventoryId = (int) getParam('inventory_id', 0);
            $sourceType = getParam('source_type', 'finished_goods');
            $organoleptic = (int) getParam('organoleptic_passed', 0);
            $temperature = (int) getParam('temperature_passed', 0);
            $packaging = (int) getParam('packaging_integrity_passed', 0);
            $visual = (int) getParam('visual_inspection_passed', 0);
            $tempReading = getParam('temperature_reading');
            $notes = getParam('notes', '');

            if (!$inventoryId) {
                Response::validationError(['inventory_id' => 'Inventory ID is required']);
            }

            // All four checks must pass
            if (!$organoleptic || !$temperature || !$packaging || !$visual) {
                $missing = [];
                if (!$organoleptic) $missing[] = 'Organoleptic (taste/smell/appearance)';
                if (!$temperature) $missing[] = 'Temperature';
                if (!$packaging) $missing[] = 'Packaging Integrity';
                if (!$visual) $missing[] = 'Visual Inspection';
                Response::validationError([
                    'checks' => 'All safety checks must pass before approval. Missing: ' . implode(', ', $missing)
                ]);
            }

            // Resolve product name and batch code
            $productName = '';
            $batchCode = '';
            if ($sourceType === 'finished_goods') {
                $fgStmt = $db->prepare("
                    SELECT fgi.product_name, pb.batch_code
                    FROM finished_goods_inventory fgi
                    LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                    WHERE fgi.id = ?
                ");
                $fgStmt->execute([$inventoryId]);
                $fgRow = $fgStmt->fetch();
                if ($fgRow) {
                    $productName = $fgRow['product_name'];
                    $batchCode = $fgRow['batch_code'];
                }
            } else {
                $rmStmt = $db->prepare("
                    SELECT 'Raw Milk' as product_name, receiving_code as batch_code
                    FROM raw_milk_inventory WHERE id = ?
                ");
                $rmStmt->execute([$inventoryId]);
                $rmRow = $rmStmt->fetch();
                if ($rmRow) {
                    $productName = $rmRow['product_name'];
                    $batchCode = $rmRow['batch_code'];
                }
            }

            // Generate verification code
            $codeStmt = $db->query("SELECT MAX(CAST(SUBSTRING(verification_code, 4) AS UNSIGNED)) as max_num FROM safety_verifications WHERE verification_code LIKE 'SV-%'");
            $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
            $verificationCode = 'SV-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);

            // Insert verification record
            $stmt = $db->prepare("
                INSERT INTO safety_verifications (
                    verification_code, inventory_id, source_type, batch_code,
                    product_name, organoleptic_passed, temperature_passed,
                    packaging_integrity_passed, visual_inspection_passed,
                    temperature_reading, notes, verified_by, verification_datetime, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'approved')
            ");
            $stmt->execute([
                $verificationCode,
                $inventoryId,
                $sourceType,
                $batchCode,
                $productName,
                $organoleptic,
                $temperature,
                $packaging,
                $visual,
                $tempReading !== '' && $tempReading !== null ? (float) $tempReading : null,
                $notes,
                $currentUser['user_id']
            ]);

            $verificationId = $db->lastInsertId();

            Response::success([
                'id' => $verificationId,
                'verification_code' => $verificationCode,
                'status' => 'approved',
                'verified_by' => $currentUser['user_id'],
                'verification_datetime' => date('Y-m-d H:i:s')
            ], 'Safety verification approved. Transformation is now authorized.');
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    error_log("Safety Verification API Error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
