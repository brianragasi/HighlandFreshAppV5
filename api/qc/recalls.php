<?php
/**
 * Batch Recall API
 * Highland Fresh Quality Control System
 * 
 * Handles product recall management for contaminated/defective batches
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once __DIR__ . '/../bootstrap.php';

// Allowed roles for this API
$allowedRoles = ['qc_officer', 'general_manager', 'admin', 'warehouse_fg', 'sales_custodian'];

// Authenticate
$currentUser = Auth::requireRole($allowedRoles);

// Get database connection
$db = Database::getInstance()->getConnection();

// Use the request method from bootstrap
$method = $requestMethod;

// Route request
switch ($method) {
    case 'GET':
        handleGetRequest($db, $currentUser);
        break;
    case 'POST':
        handlePostRequest($db, $currentUser);
        break;
    case 'PUT':
        handlePutRequest($db, $currentUser);
        break;
    case 'DELETE':
        handleDeleteRequest($db, $currentUser);
        break;
    default:
        Response::error('Method not allowed', 405);
}

/**
 * Handle GET requests
 */
function handleGetRequest($db, $currentUser) {
    $action = $_GET['action'] ?? null;
    $id = $_GET['id'] ?? null;
    
    if ($action === 'stats') {
        getRecallStats($db);
        return;
    }
    
    if ($action === 'active') {
        getActiveRecalls($db);
        return;
    }

    if ($action === 'batches') {
        getRecallBatches($db);
        return;
    }
    
    if ($id) {
        getRecallDetails($db, $id);
        return;
    }
    
    getRecallList($db);
}

function currentUserId($currentUser) {
    return (int)($currentUser['user_id'] ?? $currentUser['id'] ?? 0);
}

function recallBatchQuery($whereClause) {
    return "
        SELECT
            pb.id,
            pb.batch_code,
            COALESCE(pb.product_id, mr.product_id, inv.product_id, 0) as product_id,
            COALESCE(p.product_name, mr.product_name, inv.product_name, NULLIF(pb.product_type, ''), 'Production Batch') as product_name,
            COALESCE(pb.actual_yield, pb.expected_yield, inv.total_quantity, 0) as quantity_produced,
            pb.qc_status,
            pb.manufacturing_date,
            pb.expiry_date,
            COALESCE(dispatch.total_dispatched, 0) as total_dispatched,
            COALESCE(inv.available_quantity, 0) as total_in_warehouse
        FROM production_batches pb
        LEFT JOIN master_recipes mr ON pb.recipe_id = mr.id
        LEFT JOIN (
            SELECT
                batch_id,
                MIN(product_id) as product_id,
                MIN(product_name) as product_name,
                SUM(COALESCE(quantity, quantity_available, pieces_available, remaining_quantity, 0)) as total_quantity,
                SUM(COALESCE(quantity_available, pieces_available, remaining_quantity, quantity, 0)) as available_quantity
            FROM finished_goods_inventory
            WHERE batch_id IS NOT NULL
            GROUP BY batch_id
        ) inv ON inv.batch_id = pb.id
        LEFT JOIN products p ON p.id = COALESCE(pb.product_id, mr.product_id, inv.product_id)
        LEFT JOIN (
            SELECT
                fgi.batch_id,
                SUM(dl.quantity_released) as total_dispatched
            FROM fg_dispatch_log dl
            JOIN finished_goods_inventory fgi ON dl.inventory_id = fgi.id
            WHERE fgi.batch_id IS NOT NULL
            GROUP BY fgi.batch_id
        ) dispatch ON dispatch.batch_id = pb.id
        {$whereClause}
    ";
}

/**
 * Get released batches that can be recalled.
 */
function getRecallBatches($db) {
    $search = trim($_GET['search'] ?? '');
    $where = "
        WHERE pb.qc_status = 'released'
          AND NOT EXISTS (
              SELECT 1
              FROM batch_recalls br
              WHERE br.batch_id = pb.id
                AND br.status NOT IN ('completed', 'cancelled')
          )
    ";
    $params = [];

    if ($search !== '') {
        $where .= " AND (pb.batch_code LIKE ? OR p.product_name LIKE ? OR mr.product_name LIKE ? OR inv.product_name LIKE ?)";
        $term = "%{$search}%";
        $params = [$term, $term, $term, $term];
    }

    $sql = recallBatchQuery($where) . "
        ORDER BY pb.manufacturing_date DESC, pb.created_at DESC
        LIMIT 100
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Get list of recalls with filters
 */
function getRecallList($db) {
    $status = $_GET['status'] ?? null;
    $recallClass = $_GET['recall_class'] ?? null;
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $where = ["1=1"];
    $params = [];
    
    if ($status) {
        $where[] = "br.status = ?";
        $params[] = $status;
    }
    
    if ($recallClass) {
        $where[] = "br.recall_class = ?";
        $params[] = $recallClass;
    }
    
    if ($dateFrom) {
        $where[] = "DATE(br.initiated_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $where[] = "DATE(br.initiated_at) <= ?";
        $params[] = $dateTo;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT 
                br.*,
                CASE 
                    WHEN br.total_dispatched > 0 
                    THEN ROUND((br.total_recovered / br.total_dispatched) * 100, 2)
                    ELSE 0 
                END as recovery_rate,
                CONCAT(ui.first_name, ' ', ui.last_name) as initiated_by_name,
                CONCAT(ua.first_name, ' ', ua.last_name) as approved_by_name,
                (SELECT COUNT(*) FROM recall_affected_locations WHERE recall_id = br.id) as affected_locations_count
            FROM batch_recalls br
            LEFT JOIN users ui ON br.initiated_by = ui.id
            LEFT JOIN users ua ON br.approved_by = ua.id
            WHERE {$whereClause}
            ORDER BY 
                CASE br.recall_class 
                    WHEN 'class_i' THEN 1 
                    WHEN 'class_ii' THEN 2 
                    ELSE 3 
                END,
                br.initiated_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $recalls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    Response::success($recalls);
}

/**
 * Get single recall with full details
 */
function getRecallDetails($db, $id) {
    // Get recall
    $stmt = $db->prepare("
        SELECT 
            br.*,
            CASE 
                WHEN br.total_dispatched > 0 
                THEN ROUND((br.total_recovered / br.total_dispatched) * 100, 2)
                ELSE 0 
            END as recovery_rate,
            CONCAT(ui.first_name, ' ', ui.last_name) as initiated_by_name,
            CONCAT(ua.first_name, ' ', ua.last_name) as approved_by_name,
            CONCAT(uc.first_name, ' ', uc.last_name) as completed_by_name
        FROM batch_recalls br
        LEFT JOIN users ui ON br.initiated_by = ui.id
        LEFT JOIN users ua ON br.approved_by = ua.id
        LEFT JOIN users uc ON br.completed_by = uc.id
        WHERE br.id = ?
    ");
    $stmt->execute([$id]);
    $recall = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recall) {
        Response::error('Recall not found', 404);
    }
    
    // Get affected locations
    $stmt = $db->prepare("
        SELECT * FROM recall_affected_locations 
        WHERE recall_id = ?
        ORDER BY units_dispatched DESC
    ");
    $stmt->execute([$id]);
    $recall['affected_locations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get returns
    $stmt = $db->prepare("
        SELECT 
            rr.*,
            ral.location_name,
            CONCAT(u.first_name, ' ', u.last_name) as received_by_name
        FROM recall_returns rr
        JOIN recall_affected_locations ral ON rr.affected_location_id = ral.id
        JOIN users u ON rr.received_by = u.id
        WHERE rr.recall_id = ?
        ORDER BY rr.return_date DESC
    ");
    $stmt->execute([$id]);
    $recall['returns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get activity log
    $stmt = $db->prepare("
        SELECT 
            ral.*,
            CONCAT(u.first_name, ' ', u.last_name) as action_by_name
        FROM recall_activity_log ral
        JOIN users u ON ral.action_by = u.id
        WHERE ral.recall_id = ?
        ORDER BY ral.action_at DESC
        LIMIT 50
    ");
    $stmt->execute([$id]);
    $recall['activity_log'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    Response::success($recall);
}

/**
 * Get recall statistics
 */
function getRecallStats($db) {
    // Summary by status
    $stmt = $db->query("
        SELECT 
            status,
            COUNT(*) as count,
            SUM(total_dispatched) as total_units,
            SUM(total_recovered) as total_recovered
        FROM batch_recalls
        GROUP BY status
    ");
    $byStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Summary by class
    $stmt = $db->query("
        SELECT 
            recall_class,
            COUNT(*) as count,
            SUM(total_dispatched) as total_units
        FROM batch_recalls
        WHERE status NOT IN ('cancelled', 'completed')
        GROUP BY recall_class
    ");
    $byClass = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Active recalls count
    $stmt = $db->query("
        SELECT COUNT(*) as count FROM batch_recalls 
        WHERE status IN ('approved', 'in_progress')
    ");
    $activeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Pending approval
    $stmt = $db->query("
        SELECT COUNT(*) as count FROM batch_recalls 
        WHERE status = 'pending_approval'
    ");
    $pendingApproval = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Average recovery rate for completed recalls
    $stmt = $db->query("
        SELECT 
            AVG(CASE 
                WHEN total_dispatched > 0 
                THEN (total_recovered / total_dispatched) * 100 
                ELSE 0 
            END) as avg_recovery_rate
        FROM batch_recalls
        WHERE status = 'completed'
    ");
    $avgRecovery = $stmt->fetch(PDO::FETCH_ASSOC)['avg_recovery_rate'];
    
    Response::success([
        'summary' => [
            'active' => $activeCount,
            'pending_approval' => $pendingApproval,
            'avg_recovery_rate' => round($avgRecovery ?? 0, 2)
        ],
        'by_status' => $byStatus,
        'by_class' => $byClass
    ]);
}

/**
 * Get active recalls (for dashboard alerts)
 */
function getActiveRecalls($db) {
    $stmt = $db->query("
        SELECT 
            br.id,
            br.recall_code,
            br.batch_code,
            br.product_name,
            br.recall_class,
            br.status,
            br.total_dispatched,
            br.total_recovered,
            CASE 
                WHEN br.total_dispatched > 0 
                THEN ROUND((br.total_recovered / br.total_dispatched) * 100, 2)
                ELSE 0 
            END as recovery_rate,
            br.initiated_at
        FROM batch_recalls br
        WHERE br.status IN ('pending_approval', 'approved', 'in_progress')
        ORDER BY 
            CASE br.recall_class WHEN 'class_i' THEN 1 WHEN 'class_ii' THEN 2 ELSE 3 END,
            br.initiated_at DESC
    ");
    
    Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Handle POST requests - Create new recall
 */
function handlePostRequest($db, $currentUser) {
    $data = getRequestBody();
    
    // Validate required fields - accept either batch_id or batch_code
    if (empty($data['batch_id']) && empty($data['batch_code'])) {
        Response::error("Missing required field: batch_id or batch_code", 400);
    }
    
    $required = ['recall_class', 'reason'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            Response::error("Missing required field: {$field}", 400);
        }
    }
    
    // Validate recall class
    $validClasses = ['class_i', 'class_ii', 'class_iii'];
    if (!in_array($data['recall_class'], $validClasses)) {
        Response::error('Invalid recall class', 400);
    }
    
    // Get batch details from the real production batch structure.
    $batchInput = trim((string)($data['batch_id'] ?? $data['batch_code']));

    if (is_numeric($batchInput) && strlen($batchInput) < 10) {
        $stmt = $db->prepare(recallBatchQuery("WHERE pb.id = ?"));
        $stmt->execute([$batchInput]);
    } else {
        $stmt = $db->prepare(recallBatchQuery("WHERE pb.batch_code = ?"));
        $stmt->execute([$batchInput]);
    }
    
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$batch) {
        Response::error('Batch not found. Please enter a valid batch code (e.g., BATCH-20260205-0001)', 404);
    }
    
    // Use the actual batch ID from here
    $batchId = $batch['id'];
    
    // Check for existing active recall on this batch
    $stmt = $db->prepare("
        SELECT id FROM batch_recalls 
        WHERE batch_id = ? AND status NOT IN ('completed', 'cancelled')
    ");
    $stmt->execute([$batchId]);
    if ($stmt->fetch()) {
        Response::error('An active recall already exists for this batch', 400);
    }
    
    // Generate recall code
    $recallCode = 'RCL-' . date('Ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    $totalProduced = (int)$batch['quantity_produced'];
    $totalDispatched = (int)$batch['total_dispatched'];
    $totalInWarehouse = (int)$batch['total_in_warehouse'];

    if ($totalDispatched === 0 && $totalInWarehouse === 0 && $totalProduced > 0) {
        $totalInWarehouse = $totalProduced;
    }

    $userId = currentUserId($currentUser);
    if ($userId <= 0) {
        Response::error('Unable to identify current user for recall initiation', 401);
    }
    
    $db->beginTransaction();
    
    try {
        // Create recall
        $stmt = $db->prepare("
            INSERT INTO batch_recalls (
                recall_code, batch_id, batch_code, product_id, product_name,
                recall_class, reason, evidence_notes,
                total_produced, total_dispatched, total_in_warehouse,
                status, initiated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval', ?)
        ");
        
        $stmt->execute([
            $recallCode,
            $batchId,
            $batch['batch_code'],
            $batch['product_id'],
            $batch['product_name'],
            $data['recall_class'],
            $data['reason'],
            $data['evidence_notes'] ?? null,
            $totalProduced,
            $totalDispatched,
            $totalInWarehouse,
            $userId
        ]);
        
        $recallId = $db->lastInsertId();
        
        // Try to populate affected locations from dispatch records
        // This depends on your sales/dispatch table structure
        populateAffectedLocations($db, $recallId, $batchId);
        
        // Log activity
        $stmt = $db->prepare("
            INSERT INTO recall_activity_log (recall_id, action, action_by, details)
            VALUES (?, 'created', ?, ?)
        ");
        $stmt->execute([
            $recallId,
            $userId,
            json_encode(['recall_class' => $data['recall_class'], 'reason' => $data['reason']])
        ]);
        
        $db->commit();
        
        // Get affected locations count
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM recall_affected_locations WHERE recall_id = ?");
        $stmt->execute([$recallId]);
        $locCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        Response::success([
            'id' => $recallId,
            'recall_code' => $recallCode,
            'batch_code' => $batch['batch_code'],
            'product_name' => $batch['product_name'],
            'total_dispatched' => $totalDispatched,
            'affected_locations' => $locCount,
            'status' => 'pending_approval'
        ], 'Recall initiated successfully');
        
    } catch (Exception $e) {
        $db->rollBack();
        Response::error('Failed to create recall: ' . $e->getMessage(), 500);
    }
}

/**
 * Populate affected locations from dispatch records
 */
function populateAffectedLocations($db, $recallId, $batchId) {
    // First try to get from actual delivery records
    $stmt = $db->prepare("
        SELECT 
            CASE d.customer_type 
                WHEN 'supermarket' THEN 'store'
                WHEN 'school' THEN 'store'
                WHEN 'feeding_program' THEN 'distributor'
                ELSE 'direct_customer'
            END as location_type,
            d.customer_id as location_id,
            d.customer_name as location_name,
            d.delivery_address as location_address,
            fc.contact_person,
            d.contact_number as contact_phone,
            fc.email as contact_email,
            DATE(d.dispatched_at) as dispatch_date,
            d.dr_number as dispatch_reference,
            SUM(di.quantity_dispatched) as units_dispatched
        FROM delivery_items di
        JOIN deliveries d ON di.delivery_id = d.id
        JOIN finished_goods_inventory fgi ON di.inventory_id = fgi.id
        LEFT JOIN fg_customers fc ON d.customer_id = fc.id
        WHERE fgi.batch_id = ?
          AND d.status IN ('dispatched', 'in_transit', 'delivered')
          AND di.quantity_dispatched > 0
        GROUP BY d.customer_id, d.customer_name, d.customer_type, 
                 d.delivery_address, d.contact_number,
                 fc.contact_person, fc.email,
                 d.dispatched_at, d.dr_number
    ");
    $stmt->execute([$batchId]);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($locations)) {
        // Insert each affected location
        $insertStmt = $db->prepare("
            INSERT INTO recall_affected_locations 
            (recall_id, location_type, location_id, location_name, location_address,
             contact_person, contact_phone, contact_email, dispatch_date, 
             dispatch_reference, units_dispatched)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($locations as $loc) {
            $insertStmt->execute([
                $recallId,
                $loc['location_type'],
                $loc['location_id'],
                $loc['location_name'],
                $loc['location_address'],
                $loc['contact_person'],
                $loc['contact_phone'],
                $loc['contact_email'],
                $loc['dispatch_date'],
                $loc['dispatch_reference'],
                $loc['units_dispatched']
            ]);
        }
        
        // Update recall with accurate total from delivery records
        $stmt = $db->prepare("
            UPDATE batch_recalls 
            SET total_dispatched = (
                SELECT COALESCE(SUM(units_dispatched), 0) 
                FROM recall_affected_locations 
                WHERE recall_id = ?
            )
            WHERE id = ?
        ");
        $stmt->execute([$recallId, $recallId]);
        
    } else {
        // No delivery records found - create placeholder
        $stmt = $db->prepare("
            INSERT INTO recall_affected_locations 
            (recall_id, location_type, location_name, units_dispatched, notes)
            VALUES (?, 'internal', 'Distribution Points (Manual Tracking Required)', 
                    (SELECT total_dispatched FROM batch_recalls WHERE id = ?),
                    'Delivery records not linked to this batch. Please add affected locations manually.')
        ");
        $stmt->execute([$recallId, $recallId]);
    }
}

/**
 * Handle PUT requests - Update recall status
 */
function handlePutRequest($db, $currentUser) {
    $data = getRequestBody();
    
    if (empty($data['id'])) {
        Response::error('Recall ID required', 400);
    }
    
    // Get current recall
    $stmt = $db->prepare("SELECT * FROM batch_recalls WHERE id = ?");
    $stmt->execute([$data['id']]);
    $recall = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recall) {
        Response::error('Recall not found', 404);
    }
    
    $action = $data['action'] ?? 'update';
    
    switch ($action) {
        case 'approve':
            approveRecall($db, $recall, $currentUser, $data);
            break;
            
        case 'reject':
            rejectRecall($db, $recall, $currentUser, $data);
            break;
            
        case 'log_return':
            logReturn($db, $recall, $currentUser, $data);
            break;
            
        case 'send_notification':
            sendNotification($db, $recall, $currentUser, $data);
            break;
            
        case 'complete':
            completeRecall($db, $recall, $currentUser, $data);
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Approve recall
 */
function approveRecall($db, $recall, $currentUser, $data) {
    // Only GM can approve
    if (!in_array($currentUser['role'], ['general_manager', 'admin'])) {
        Response::error('Only General Manager can approve recalls', 403);
    }
    
    if ($recall['status'] !== 'pending_approval') {
        Response::error('Recall is not pending approval', 400);
    }
    
    $stmt = $db->prepare("
        UPDATE batch_recalls 
        SET status = 'approved',
            approved_by = ?,
            approved_at = NOW(),
            approval_notes = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        currentUserId($currentUser),
        $data['approval_notes'] ?? null,
        $recall['id']
    ]);

    // Log status change (replaces tr_recall_status_change trigger)
    $stmt = $db->prepare("
        INSERT INTO recall_activity_log (recall_id, action, action_by, details)
        VALUES (?, 'approved', ?, ?)
    ");
    $stmt->execute([
        $recall['id'],
        currentUserId($currentUser),
        json_encode(['old_status' => $recall['status'], 'new_status' => 'approved'])
    ]);

    Response::success(['status' => 'approved'], 'Recall approved - notifications can now be sent');
}

/**
 * Reject recall
 */
function rejectRecall($db, $recall, $currentUser, $data) {
    // Only GM can reject
    if (!in_array($currentUser['role'], ['general_manager', 'admin'])) {
        Response::error('Only General Manager can reject recalls', 403);
    }
    
    if ($recall['status'] !== 'pending_approval') {
        Response::error('Recall is not pending approval', 400);
    }
    
    if (empty($data['rejection_reason'])) {
        Response::error('Rejection reason required', 400);
    }
    
    $stmt = $db->prepare("
        UPDATE batch_recalls 
        SET status = 'cancelled',
            approved_by = ?,
            approved_at = NOW(),
            rejection_reason = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        currentUserId($currentUser),
        $data['rejection_reason'],
        $recall['id']
    ]);
    
    Response::success(['status' => 'cancelled'], 'Recall rejected');
}

/**
 * Log product return
 */
function logReturn($db, $recall, $currentUser, $data) {
    // Validate
    if (empty($data['affected_location_id']) || empty($data['units_returned'])) {
        Response::error('Location ID and units returned required', 400);
    }

    $unitsReturned = (int)$data['units_returned'];
    if ($unitsReturned <= 0) {
        Response::error('Units returned must be greater than zero', 400);
    }
    
    if (!in_array($recall['status'], ['approved', 'in_progress'])) {
        Response::error('Cannot log returns for this recall status', 400);
    }
    
    // Verify location belongs to this recall
    $stmt = $db->prepare("
        SELECT * FROM recall_affected_locations 
        WHERE id = ? AND recall_id = ?
    ");
    $stmt->execute([$data['affected_location_id'], $recall['id']]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$location) {
        Response::error('Invalid location for this recall', 400);
    }

    $unitsDispatched = (int)($location['units_dispatched'] ?? 0);
    $alreadyReturned = (int)($location['units_returned'] ?? 0);
    $destroyedOnsite = (int)($location['units_destroyed_onsite'] ?? 0);
    $consumed = (int)($location['units_consumed'] ?? 0);
    $remainingRecoverable = max(0, $unitsDispatched - $alreadyReturned - $destroyedOnsite - $consumed);

    if ($unitsReturned > $remainingRecoverable) {
        Response::error(
            "Cannot log {$unitsReturned} returned units. Only {$remainingRecoverable} units remain recoverable for this location.",
            400
        );
    }
    
    $db->beginTransaction();
    
    try {
        // Insert return record
        $stmt = $db->prepare("
            INSERT INTO recall_returns 
            (recall_id, affected_location_id, return_date, units_returned, 
             condition_status, condition_notes, received_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $recall['id'],
            $data['affected_location_id'],
            $data['return_date'] ?? date('Y-m-d'),
            $unitsReturned,
            $data['condition_status'] ?? 'unknown',
            $data['condition_notes'] ?? null,
            currentUserId($currentUser)
        ]);

        // Update affected location return counts (replaces tr_recall_return_update trigger)
        $stmt = $db->prepare("
            UPDATE recall_affected_locations
            SET units_returned = units_returned + ?,
                return_status = CASE
                    WHEN (units_returned + ?) >= units_dispatched THEN 'complete'
                    WHEN (units_returned + ?) > 0 THEN 'partial'
                    ELSE 'pending'
                END
            WHERE id = ?
        ");
        $stmt->execute([
            $unitsReturned,
            $unitsReturned,
            $unitsReturned,
            $data['affected_location_id']
        ]);

        // Update total_recovered on the recall
        $stmt = $db->prepare("
            UPDATE batch_recalls
            SET total_recovered = (
                SELECT COALESCE(SUM(units_returned), 0)
                FROM recall_affected_locations
                WHERE recall_id = ?
            )
            WHERE id = ?
        ");
        $stmt->execute([$recall['id'], $recall['id']]);

        // Update recall status to in_progress if not already
        if ($recall['status'] === 'approved') {
            $stmt = $db->prepare("UPDATE batch_recalls SET status = 'in_progress' WHERE id = ?");
            $stmt->execute([$recall['id']]);
        }
        
        // Log activity
        $stmt = $db->prepare("
            INSERT INTO recall_activity_log (recall_id, action, action_by, details)
            VALUES (?, 'return_logged', ?, ?)
        ");
        $stmt->execute([
            $recall['id'],
            currentUserId($currentUser),
            json_encode([
                'location' => $location['location_name'],
                'units' => $unitsReturned
            ])
        ]);
        
        $db->commit();
        
        Response::success(['units_logged' => $unitsReturned], 'Return logged successfully');
        
    } catch (Exception $e) {
        $db->rollBack();
        Response::error('Failed to log return: ' . $e->getMessage(), 500);
    }
}

/**
 * Send notification to a location
 */
function sendNotification($db, $recall, $currentUser, $data) {
    if (empty($data['affected_location_id'])) {
        Response::error('Location ID required', 400);
    }
    
    $stmt = $db->prepare("
        UPDATE recall_affected_locations 
        SET notification_sent = TRUE,
            notification_sent_at = NOW(),
            notification_method = ?,
            notification_sent_by = ?
        WHERE id = ? AND recall_id = ?
    ");
    
    $stmt->execute([
        $data['notification_method'] ?? 'phone',
        currentUserId($currentUser),
        $data['affected_location_id'],
        $recall['id']
    ]);
    
    // Log activity
    $stmt = $db->prepare("
        INSERT INTO recall_activity_log (recall_id, action, action_by, details)
        VALUES (?, 'notification_sent', ?, ?)
    ");
    $stmt->execute([
        $recall['id'],
        currentUserId($currentUser),
        json_encode(['location_id' => $data['affected_location_id'], 'method' => $data['notification_method'] ?? 'phone'])
    ]);
    
    Response::success(null, 'Notification marked as sent');
}

/**
 * Complete recall
 */
function completeRecall($db, $recall, $currentUser, $data) {
    if (!in_array($recall['status'], ['approved', 'in_progress'])) {
        Response::error('Cannot complete recall with current status', 400);
    }
    
    $stmt = $db->prepare("
        UPDATE batch_recalls 
        SET status = 'completed',
            completed_by = ?,
            completed_at = NOW(),
            completion_notes = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        currentUserId($currentUser),
        $data['completion_notes'] ?? null,
        $recall['id']
    ]);

    // Log status change (replaces tr_recall_status_change trigger)
    $stmt = $db->prepare("
        INSERT INTO recall_activity_log (recall_id, action, action_by, details)
        VALUES (?, 'completed', ?, ?)
    ");
    $stmt->execute([
        $recall['id'],
        currentUserId($currentUser),
        json_encode(['old_status' => $recall['status'], 'new_status' => 'completed'])
    ]);

    Response::success(['status' => 'completed'], 'Recall marked as completed');
}

/**
 * Handle DELETE requests - Cancel recall
 */
function handleDeleteRequest($db, $currentUser) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        Response::error('Recall ID required', 400);
    }
    
    $stmt = $db->prepare("SELECT * FROM batch_recalls WHERE id = ?");
    $stmt->execute([$id]);
    $recall = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recall) {
        Response::error('Recall not found', 404);
    }
    
    // Only allow cancellation of initiated/pending recalls
    if (!in_array($recall['status'], ['initiated', 'pending_approval'])) {
        Response::error('Cannot cancel recall with current status', 400);
    }
    
    $stmt = $db->prepare("UPDATE batch_recalls SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$id]);

    // Log status change (replaces tr_recall_status_change trigger)
    $stmt = $db->prepare("
        INSERT INTO recall_activity_log (recall_id, action, action_by, details)
        VALUES (?, 'cancelled', ?, ?)
    ");
    $stmt->execute([
        $id,
        currentUserId($currentUser),
        json_encode(['old_status' => $recall['status'], 'new_status' => 'cancelled'])
    ]);

    Response::success(null, 'Recall cancelled');
}
