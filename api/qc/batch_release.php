<?php
/**
 * Highland Fresh System - Batch Release API
 * 
 * Updated to match actual production_batches schema
 * 
 * Endpoints:
 * GET  - List pending batches / Get batch release details / Get stats
 * POST - Create batch release verification
 * PUT  - Approve/Reject batch release
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require QC role
$currentUser = Auth::requireRole(['qc_officer', 'general_manager']);

// Constants for CCP verification (only define if not already defined)
if (!defined('PASTEURIZATION_TEMP')) {
    define('PASTEURIZATION_TEMP', 72);
}
if (!defined('MAX_COOLING_TEMP')) {
    define('MAX_COOLING_TEMP', 6);
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
                    'total' => (int)$stats['total'],
                    'pending' => (int)$stats['pending'],
                    'released' => (int)$stats['released'],
                    'rejected' => (int)$stats['rejected']
                ], 'Stats retrieved successfully');
            }
            
            if ($batchId) {
                // Get single batch details
                $stmt = $db->prepare("
                    SELECT pb.*,
                           mr.product_name, mr.product_type as recipe_type, mr.variant as recipe_variant,
                           u.first_name as created_by_first, u.last_name as created_by_last,
                           u2.first_name as released_by_first, u2.last_name as released_by_last
                    FROM production_batches pb
                    LEFT JOIN master_recipes mr ON pb.recipe_id = mr.id
                    LEFT JOIN users u ON pb.created_by = u.id
                    LEFT JOIN users u2 ON pb.released_by = u2.id
                    WHERE pb.id = ?
                ");
                $stmt->execute([$batchId]);
                $batch = $stmt->fetch();
                
                if (!$batch) {
                    Response::notFound('Batch not found');
                }
                
                Response::success($batch, 'Batch retrieved successfully');
            }
            
            // List batches based on status filter
            $where = "WHERE 1=1";
            $params = [];
            
            if ($status) {
                if ($status === 'pending') {
                    $where .= " AND (pb.qc_status = 'pending' OR pb.qc_status = 'on_hold')";
                } elseif ($status === 'released') {
                    $where .= " AND pb.qc_status = 'released'";
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
            $batches = $stmt->fetchAll();
            
            Response::paginated($batches, $total, $page, $limit, 'Batches retrieved successfully');
            break;
            
        case 'PUT':
            // Release or Reject a batch
            $batchId = getParam('batch_id');
            $action = getParam('action'); // 'release' or 'reject'
            $qcNotes = trim(getParam('qc_notes', ''));
            
            // Organoleptic checks
            $organolepticTaste = getParam('organoleptic_taste', false);
            $organolepticAppearance = getParam('organoleptic_appearance', false);
            $organolepticSmell = getParam('organoleptic_smell', false);
            
            // Validation
            $errors = [];
            if (!$batchId) {
                $errors['batch_id'] = 'Batch ID is required';
            }
            if (!$action || !in_array($action, ['release', 'reject'])) {
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
            $batch = $batchStmt->fetch();
            
            if (!$batch) {
                Response::notFound('Batch not found');
            }
            
            if ($batch['qc_status'] === 'released') {
                Response::error('Batch has already been released', 400);
            }
            
            // Begin transaction
            $db->beginTransaction();
            
            try {
                $newStatus = $action === 'release' ? 'released' : 'rejected';
                $releasedAt = $action === 'release' ? date('Y-m-d H:i:s') : null;
                $releasedBy = $action === 'release' ? $currentUser['user_id'] : null;
                
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
                    $batchId
                ]);
                
                // Note: fg_received is explicitly set to 0 when QC releases a batch.
                // The FG Warehouse team will set fg_received = 1 when they physically 
                // receive the batch into a specific chiller via the FG receiving page.
                
                $db->commit();
                
                // Log audit
                logAudit($currentUser['user_id'], $action === 'release' ? 'RELEASE' : 'REJECT', 'production_batches', $batchId, null, [
                    'action' => $action,
                    'qc_notes' => $qcNotes
                ]);
                
                $message = $action === 'release' ? '✅ Batch released successfully!' : '❌ Batch rejected';
                
                Response::success([
                    'batch_id' => $batchId,
                    'batch_code' => $batch['batch_code'],
                    'status' => $newStatus,
                    'barcode' => $barcode ?: $batch['barcode']
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
