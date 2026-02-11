<?php
/**
 * Highland Fresh System - Disposals API
 * 
 * Manages disposal/write-off records for QC rejected items
 * Implements GM approval workflow
 * 
 * Endpoints:
 * GET    - List disposals, get single disposal, get stats
 * POST   - Create new disposal request
 * PUT    - Approve/reject/complete disposal
 * DELETE - Cancel disposal (only pending)
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require QC, GM, or Finance role
$currentUser = Auth::requireRole(['qc_officer', 'general_manager', 'finance_officer', 'warehouse_raw', 'warehouse_fg']);

// Disposal categories
define('DISPOSAL_CATEGORIES', [
    'qc_failed' => 'QC Test Failed',
    'expired' => 'Expired',
    'near_expiry' => 'Near Expiry',
    'spoiled' => 'Spoiled',
    'contaminated' => 'Contaminated',
    'damaged' => 'Damaged',
    'rejected_receipt' => 'Rejected at Receiving',
    'production_waste' => 'Production Waste',
    'other' => 'Other'
]);

define('DISPOSAL_METHODS', [
    'drain' => 'Drain (Liquid)',
    'incinerate' => 'Incinerate',
    'animal_feed' => 'Convert to Animal Feed',
    'compost' => 'Compost',
    'special_waste' => 'Special Waste Contractor',
    'other' => 'Other Method'
]);

define('SOURCE_TYPES', [
    'raw_milk' => 'Raw Milk Inventory',
    'finished_goods' => 'Finished Goods',
    'ingredients' => 'Ingredients',
    'production_batch' => 'Production Batch',
    'milk_receiving' => 'Milk Receiving'
]);

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
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
    
} catch (Exception $e) {
    error_log("Disposals API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}

/**
 * Handle GET requests
 */
function handleGetRequest($db, $currentUser) {
    $action = getParam('action', 'list');
    $id = getParam('id');
    
    // Get single disposal
    if ($id) {
        $stmt = $db->prepare("
            SELECT d.*,
                   ui.first_name as initiated_by_name, ui.last_name as initiated_by_last,
                   ua.first_name as approved_by_name, ua.last_name as approved_by_last,
                   ud.first_name as disposed_by_name, ud.last_name as disposed_by_last,
                   p.product_code, p.product_name as product_full_name
            FROM disposals d
            LEFT JOIN users ui ON d.initiated_by = ui.id
            LEFT JOIN users ua ON d.approved_by = ua.id
            LEFT JOIN users ud ON d.disposed_by = ud.id
            LEFT JOIN products p ON d.product_id = p.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $disposal = $stmt->fetch();
        
        if (!$disposal) {
            Response::notFound('Disposal not found');
        }
        
        // Get disposal items if any
        $itemsStmt = $db->prepare("
            SELECT di.*, p.product_code
            FROM disposal_items di
            LEFT JOIN products p ON di.product_id = p.id
            WHERE di.disposal_id = ?
            ORDER BY di.id
        ");
        $itemsStmt->execute([$id]);
        $disposal['items'] = $itemsStmt->fetchAll();
        
        Response::success($disposal, 'Disposal retrieved successfully');
    }
    
    // Get statistics
    if ($action === 'stats') {
        $period = getParam('period', 'month'); // today, week, month, year
        
        $dateFilter = match($period) {
            'today' => "DATE(d.created_at) = CURDATE()",
            'week' => "d.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            'month' => "d.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            'year' => "YEAR(d.created_at) = YEAR(CURDATE())",
            default => "d.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
        };
        
        // Overall stats
        $stats = $db->query("
            SELECT 
                COUNT(*) as total_disposals,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'completed' THEN total_value ELSE 0 END) as total_loss_value
            FROM disposals d
            WHERE $dateFilter
        ")->fetch();
        
        // By category
        $byCategory = $db->query("
            SELECT 
                disposal_category,
                COUNT(*) as count,
                SUM(quantity) as total_quantity,
                SUM(CASE WHEN status = 'completed' THEN total_value ELSE 0 END) as total_value
            FROM disposals d
            WHERE $dateFilter
            GROUP BY disposal_category
            ORDER BY count DESC
        ")->fetchAll();
        
        // By source type
        $bySourceType = $db->query("
            SELECT 
                source_type,
                COUNT(*) as count,
                SUM(CASE WHEN status = 'completed' THEN total_value ELSE 0 END) as total_value
            FROM disposals d
            WHERE $dateFilter
            GROUP BY source_type
        ")->fetchAll();
        
        // Recent pending approvals (for GM)
        $pendingApprovals = $db->query("
            SELECT d.id, d.disposal_code, d.product_name, d.quantity, d.unit,
                   d.disposal_category, d.total_value, d.initiated_at,
                   u.first_name as initiated_by_name
            FROM disposals d
            LEFT JOIN users u ON d.initiated_by = u.id
            WHERE d.status = 'pending'
            ORDER BY d.initiated_at ASC
            LIMIT 10
        ")->fetchAll();
        
        Response::success([
            'summary' => $stats,
            'by_category' => $byCategory,
            'by_source_type' => $bySourceType,
            'pending_approvals' => $pendingApprovals,
            'categories' => DISPOSAL_CATEGORIES,
            'methods' => DISPOSAL_METHODS,
            'source_types' => SOURCE_TYPES
        ], 'Disposal statistics retrieved');
    }
    
    // Get pending approvals for GM
    if ($action === 'pending') {
        $stmt = $db->query("
            SELECT d.*,
                   u.first_name as initiated_by_name,
                   p.product_code
            FROM disposals d
            LEFT JOIN users u ON d.initiated_by = u.id
            LEFT JOIN products p ON d.product_id = p.id
            WHERE d.status = 'pending'
            ORDER BY d.initiated_at ASC
        ");
        
        Response::success([
            'pending' => $stmt->fetchAll(),
            'count' => $stmt->rowCount()
        ], 'Pending approvals retrieved');
    }
    
    // Get lookup data
    if ($action === 'lookup') {
        Response::success([
            'categories' => DISPOSAL_CATEGORIES,
            'methods' => DISPOSAL_METHODS,
            'source_types' => SOURCE_TYPES
        ], 'Lookup data retrieved');
    }
    
    // Get available sources for disposal
    if ($action === 'sources') {
        $type = getParam('type', 'finished_goods');
        
        $sources = [];
        
        if ($type === 'finished_goods' || $type === 'all') {
            // Get finished goods with available quantity, prioritizing items near expiry
            $stmt = $db->query("
                SELECT 
                    'finished_goods' as source_type,
                    fgi.id as source_id,
                    CONCAT(fgi.product_name, ' (', pb.batch_code, ')') as display_name,
                    fgi.product_id,
                    fgi.product_name,
                    pb.batch_code as reference,
                    (COALESCE(fgi.boxes_available, 0) * COALESCE(p.pieces_per_box, 1)) + COALESCE(fgi.pieces_available, 0) as available_quantity,
                    'pcs' as unit,
                    COALESCE(fgi.unit_price, 0) as unit_cost,
                    fgi.expiry_date,
                    fgi.manufacturing_date,
                    DATEDIFF(fgi.expiry_date, CURDATE()) as days_until_expiry,
                    CASE 
                        WHEN fgi.expiry_date < CURDATE() THEN 'Expired'
                        WHEN fgi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'Expiring Soon'
                        ELSE 'Active'
                    END as status
                FROM finished_goods_inventory fgi
                LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                LEFT JOIN products p ON fgi.product_id = p.id
                WHERE (fgi.boxes_available > 0 OR fgi.pieces_available > 0)
                ORDER BY fgi.expiry_date ASC
                LIMIT 100
            ");
            $sources = array_merge($sources, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        
        if ($type === 'raw_milk' || $type === 'all') {
            $stmt = $db->query("
                SELECT 
                    'raw_milk' as source_type,
                    rmi.id as source_id,
                    CONCAT('Raw Milk - Tank ', COALESCE(st.tank_name, 'Unknown'), ' (Batch: ', COALESCE(rmi.batch_code, 'N/A'), ')') as display_name,
                    NULL as product_id,
                    'Raw Milk' as product_name,
                    COALESCE(rmi.batch_code, CONCAT('RMI-', rmi.id)) as reference,
                    COALESCE(rmi.remaining_liters, rmi.volume_liters, 0) as available_quantity,
                    'liters' as unit,
                    COALESCE(rmi.unit_cost, 30.00) as unit_cost,
                    rmi.expiry_date,
                    DATE(rmi.received_date) as manufacturing_date,
                    CASE 
                        WHEN rmi.expiry_date IS NOT NULL THEN DATEDIFF(rmi.expiry_date, CURDATE())
                        ELSE NULL
                    END as days_until_expiry,
                    CASE 
                        WHEN rmi.expiry_date IS NOT NULL AND rmi.expiry_date < CURDATE() THEN 'Expired'
                        WHEN rmi.expiry_date IS NOT NULL AND rmi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'Expiring Soon'
                        ELSE rmi.status
                    END as status
                FROM raw_milk_inventory rmi
                LEFT JOIN storage_tanks st ON rmi.tank_id = st.id
                WHERE COALESCE(rmi.remaining_liters, rmi.volume_liters, 0) > 0 
                  AND (rmi.status IN ('stored', 'available', 'active', '') OR rmi.status IS NULL)
                ORDER BY rmi.expiry_date ASC, rmi.created_at ASC
                LIMIT 50
            ");
            $sources = array_merge($sources, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        
        Response::success($sources, 'Disposal sources retrieved');
    }
    
    // List disposals with filters
    $status = getParam('status');
    $category = getParam('category');
    $sourceType = getParam('source_type');
    $dateFrom = getParam('date_from');
    $dateTo = getParam('date_to');
    $search = getParam('search');
    $page = max(1, intval(getParam('page', 1)));
    $limit = min(100, max(1, intval(getParam('limit', 20))));
    $offset = ($page - 1) * $limit;
    
    $where = "1=1";
    $params = [];
    
    if ($status && in_array($status, ['pending', 'approved', 'rejected', 'completed', 'cancelled'])) {
        $where .= " AND d.status = ?";
        $params[] = $status;
    }
    
    if ($category && array_key_exists($category, DISPOSAL_CATEGORIES)) {
        $where .= " AND d.disposal_category = ?";
        $params[] = $category;
    }
    
    if ($sourceType && array_key_exists($sourceType, SOURCE_TYPES)) {
        $where .= " AND d.source_type = ?";
        $params[] = $sourceType;
    }
    
    if ($dateFrom) {
        $where .= " AND DATE(d.created_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $where .= " AND DATE(d.created_at) <= ?";
        $params[] = $dateTo;
    }
    
    if ($search) {
        $where .= " AND (d.disposal_code LIKE ? OR d.product_name LIKE ? OR d.disposal_reason LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Count total
    $countStmt = $db->prepare("SELECT COUNT(*) FROM disposals d WHERE $where");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    
    // Get data
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare("
        SELECT d.*,
               ui.first_name as initiated_by_name,
               ua.first_name as approved_by_name,
               p.product_code
        FROM disposals d
        LEFT JOIN users ui ON d.initiated_by = ui.id
        LEFT JOIN users ua ON d.approved_by = ua.id
        LEFT JOIN products p ON d.product_id = p.id
        WHERE $where
        ORDER BY d.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    $disposals = $stmt->fetchAll();
    
    Response::paginated($disposals, $total, $page, $limit, 'Disposals retrieved');
}

/**
 * Handle POST requests - Create new disposal
 */
function handlePostRequest($db, $currentUser) {
    // Only QC Officer and General Manager can create disposal requests
    // Warehouse staff must report to QC (segregation of duties)
    $allowedToCreate = ['qc_officer', 'general_manager'];
    if (!in_array($currentUser['role'], $allowedToCreate)) {
        Response::error('Only QC Officers can create disposal requests. Please report the issue to QC for validation.', 403);
    }
    
    // Validate required fields
    $sourceType = getParam('source_type');
    $sourceId = getParam('source_id');
    $quantity = floatval(getParam('quantity'));
    $unit = getParam('unit', 'pcs');
    $category = getParam('disposal_category');
    $reason = trim(getParam('disposal_reason', ''));
    $method = getParam('disposal_method', 'drain');
    
    $errors = [];
    
    if (!$sourceType || !array_key_exists($sourceType, SOURCE_TYPES)) {
        $errors['source_type'] = 'Valid source type is required';
    }
    
    if (!$sourceId || !is_numeric($sourceId)) {
        $errors['source_id'] = 'Source ID is required';
    }
    
    if ($quantity <= 0) {
        $errors['quantity'] = 'Quantity must be greater than 0';
    }
    
    if (!$category || !array_key_exists($category, DISPOSAL_CATEGORIES)) {
        $errors['disposal_category'] = 'Valid disposal category is required';
    }
    
    if (empty($reason)) {
        $errors['disposal_reason'] = 'Disposal reason is required';
    }
    
    if (!empty($errors)) {
        Response::validationError($errors);
    }
    
    // Validate source exists and get details
    $sourceDetails = validateSource($db, $sourceType, $sourceId, $quantity);
    
    if (!$sourceDetails['valid']) {
        Response::error($sourceDetails['error'], 400);
    }
    
    // Generate disposal code
    $disposalCode = generateDisposalCode($db);
    
    // Calculate total value
    $unitCost = floatval(getParam('unit_cost', $sourceDetails['unit_cost'] ?? 0));
    $totalValue = $quantity * $unitCost;
    
    // Create disposal record
    $db->beginTransaction();
    
    try {
        // Optional recall_id for linking disposal to a recall
        $recallId = getParam('recall_id') ? intval(getParam('recall_id')) : null;
        
        $stmt = $db->prepare("
            INSERT INTO disposals (
                disposal_code, source_type, source_id, source_reference,
                product_id, product_name, quantity, unit,
                unit_cost, total_value, disposal_category, disposal_reason,
                disposal_method, status, initiated_by, initiated_at, notes, recall_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), ?, ?)
        ");
        
        $stmt->execute([
            $disposalCode,
            $sourceType,
            $sourceId,
            $sourceDetails['reference'] ?? null,
            $sourceDetails['product_id'] ?? null,
            $sourceDetails['product_name'] ?? getParam('product_name'),
            $quantity,
            $unit,
            $unitCost,
            $totalValue,
            $category,
            $reason,
            $method,
            $currentUser['user_id'],
            getParam('notes'),
            $recallId
        ]);
        
        $disposalId = $db->lastInsertId();
        
        // Log audit
        logAudit($currentUser['user_id'], 'CREATE', 'disposals', $disposalId, null, [
            'disposal_code' => $disposalCode,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'quantity' => $quantity,
            'category' => $category
        ]);
        
        $db->commit();
        
        // Fetch created disposal
        $stmt = $db->prepare("SELECT * FROM disposals WHERE id = ?");
        $stmt->execute([$disposalId]);
        $disposal = $stmt->fetch();
        
        Response::success($disposal, 'Disposal request created successfully. Awaiting GM approval.', 201);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Handle PUT requests - Approve/Reject/Complete disposal
 */
function handlePutRequest($db, $currentUser) {
    $id = getParam('id');
    $action = getParam('action'); // approve, reject, complete, execute
    
    if (!$id || !is_numeric($id)) {
        Response::error('Disposal ID is required', 400);
    }
    
    // Get existing disposal
    $stmt = $db->prepare("SELECT * FROM disposals WHERE id = ?");
    $stmt->execute([$id]);
    $disposal = $stmt->fetch();
    
    if (!$disposal) {
        Response::notFound('Disposal not found');
    }
    
    $db->beginTransaction();
    
    try {
        switch ($action) {
            case 'approve':
                // Only GM can approve
                if (!in_array($currentUser['role'], ['general_manager', 'admin'])) {
                    Response::error('Only General Manager can approve disposals', 403);
                }
                
                if ($disposal['status'] !== 'pending') {
                    Response::error('Only pending disposals can be approved', 400);
                }
                
                $approvalNotes = trim(getParam('approval_notes', ''));
                
                $stmt = $db->prepare("
                    UPDATE disposals SET
                        status = 'approved',
                        approved_by = ?,
                        approved_at = NOW(),
                        approval_notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$currentUser['user_id'], $approvalNotes, $id]);
                
                logAudit($currentUser['user_id'], 'APPROVE', 'disposals', $id, 
                    ['status' => 'pending'], 
                    ['status' => 'approved', 'approved_by' => $currentUser['user_id']]
                );
                
                $message = 'Disposal approved successfully';
                break;
                
            case 'reject':
                // Only GM can reject
                if (!in_array($currentUser['role'], ['general_manager', 'admin'])) {
                    Response::error('Only General Manager can reject disposals', 403);
                }
                
                if ($disposal['status'] !== 'pending') {
                    Response::error('Only pending disposals can be rejected', 400);
                }
                
                $rejectionReason = trim(getParam('rejection_reason', getParam('approval_notes', '')));
                if (empty($rejectionReason)) {
                    Response::validationError(['rejection_reason' => 'Rejection reason is required']);
                }
                
                $stmt = $db->prepare("
                    UPDATE disposals SET
                        status = 'rejected',
                        approved_by = ?,
                        approved_at = NOW(),
                        approval_notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$currentUser['user_id'], $rejectionReason, $id]);
                
                logAudit($currentUser['user_id'], 'REJECT', 'disposals', $id,
                    ['status' => 'pending'],
                    ['status' => 'rejected', 'reason' => $rejectionReason]
                );
                
                $message = 'Disposal rejected';
                break;
                
            case 'complete':
            case 'execute':
                if ($disposal['status'] !== 'approved') {
                    Response::error('Only approved disposals can be completed', 400);
                }
                
                $witnessName = trim(getParam('witness_name', ''));
                $disposalLocation = trim(getParam('disposal_location', ''));
                $executionNotes = trim(getParam('notes', ''));
                
                // Execute the disposal - deduct from inventory
                executeDisposal($db, $disposal, $currentUser);
                
                $stmt = $db->prepare("
                    UPDATE disposals SET
                        status = 'completed',
                        disposed_by = ?,
                        disposed_at = NOW(),
                        witness_name = ?,
                        disposal_location = ?,
                        notes = CONCAT(COALESCE(notes, ''), '\n[Execution] ', ?)
                    WHERE id = ?
                ");
                $stmt->execute([
                    $currentUser['user_id'],
                    $witnessName,
                    $disposalLocation,
                    $executionNotes,
                    $id
                ]);
                
                logAudit($currentUser['user_id'], 'COMPLETE', 'disposals', $id,
                    ['status' => 'approved'],
                    ['status' => 'completed', 'disposed_by' => $currentUser['user_id']]
                );
                
                $message = 'Disposal completed successfully. Inventory updated.';
                break;
                
            default:
                Response::error('Invalid action. Use: approve, reject, complete', 400);
        }
        
        $db->commit();
        
        // Fetch updated disposal
        $stmt = $db->prepare("SELECT * FROM disposals WHERE id = ?");
        $stmt->execute([$id]);
        $updatedDisposal = $stmt->fetch();
        
        Response::success($updatedDisposal, $message);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Handle DELETE requests - Cancel disposal
 */
function handleDeleteRequest($db, $currentUser) {
    $id = getParam('id');
    
    if (!$id || !is_numeric($id)) {
        Response::error('Disposal ID is required', 400);
    }
    
    $stmt = $db->prepare("SELECT * FROM disposals WHERE id = ?");
    $stmt->execute([$id]);
    $disposal = $stmt->fetch();
    
    if (!$disposal) {
        Response::notFound('Disposal not found');
    }
    
    // Only pending can be cancelled, and only by initiator or GM
    if ($disposal['status'] !== 'pending') {
        Response::error('Only pending disposals can be cancelled', 400);
    }
    
    if ($disposal['initiated_by'] != $currentUser['user_id'] && 
        !in_array($currentUser['role'], ['general_manager', 'admin'])) {
        Response::error('You can only cancel your own disposal requests', 403);
    }
    
    $stmt = $db->prepare("UPDATE disposals SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$id]);
    
    logAudit($currentUser['user_id'], 'CANCEL', 'disposals', $id,
        ['status' => 'pending'],
        ['status' => 'cancelled']
    );
    
    Response::success(null, 'Disposal cancelled successfully');
}

/**
 * Validate source item exists and has sufficient quantity
 */
function validateSource($db, $sourceType, $sourceId, $quantity) {
    switch ($sourceType) {
        case 'raw_milk':
            $stmt = $db->prepare("
                SELECT rmi.*, 
                       COALESCE(rmi.batch_code, CONCAT('RMI-', rmi.id)) as reference,
                       COALESCE(rmi.remaining_liters, rmi.volume_liters, 0) as available_liters
                FROM raw_milk_inventory rmi
                WHERE rmi.id = ?
            ");
            $stmt->execute([$sourceId]);
            $item = $stmt->fetch();
            
            if (!$item) {
                return ['valid' => false, 'error' => 'Raw milk inventory not found'];
            }
            
            $available = floatval($item['available_liters']);
            if ($quantity > $available) {
                return ['valid' => false, 'error' => "Insufficient quantity. Available: $available liters"];
            }
            
            return [
                'valid' => true,
                'reference' => $item['reference'],
                'product_name' => 'Raw Milk',
                'unit_cost' => floatval($item['unit_cost'] ?? 30.00)
            ];
            
        case 'finished_goods':
            $stmt = $db->prepare("
                SELECT fgi.*, p.product_name, p.product_code, p.cost_price,
                       pb.batch_code as reference
                FROM finished_goods_inventory fgi
                LEFT JOIN products p ON fgi.product_id = p.id
                LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                WHERE fgi.id = ?
            ");
            $stmt->execute([$sourceId]);
            $item = $stmt->fetch();
            
            if (!$item) {
                return ['valid' => false, 'error' => 'Finished goods inventory not found'];
            }
            
            $available = intval($item['quantity_available'] ?? $item['remaining_quantity'] ?? 0);
            if ($quantity > $available) {
                return ['valid' => false, 'error' => "Insufficient quantity. Available: $available pieces"];
            }
            
            return [
                'valid' => true,
                'reference' => $item['reference'],
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'unit_cost' => floatval($item['cost_price'] ?? 0)
            ];
            
        case 'milk_receiving':
            $stmt = $db->prepare("
                SELECT mr.*, f.farmer_code, 
                       CONCAT(f.first_name, ' ', COALESCE(f.last_name, '')) as farmer_name
                FROM milk_receiving mr
                LEFT JOIN farmers f ON mr.farmer_id = f.id
                WHERE mr.id = ?
            ");
            $stmt->execute([$sourceId]);
            $item = $stmt->fetch();
            
            if (!$item) {
                return ['valid' => false, 'error' => 'Milk receiving record not found'];
            }
            
            return [
                'valid' => true,
                'reference' => $item['receiving_code'],
                'product_name' => 'Raw Milk from ' . ($item['farmer_name'] ?? 'Unknown Farmer'),
                'unit_cost' => 30.00
            ];
            
        case 'production_batch':
            $stmt = $db->prepare("
                SELECT pb.*, p.product_name, p.cost_price
                FROM production_batches pb
                LEFT JOIN products p ON pb.product_id = p.id
                WHERE pb.id = ?
            ");
            $stmt->execute([$sourceId]);
            $item = $stmt->fetch();
            
            if (!$item) {
                return ['valid' => false, 'error' => 'Production batch not found'];
            }
            
            return [
                'valid' => true,
                'reference' => $item['batch_code'],
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'] ?? 'Production Batch',
                'unit_cost' => floatval($item['cost_price'] ?? 0)
            ];
            
        case 'ingredients':
            $stmt = $db->prepare("
                SELECT ib.*, i.ingredient_name, i.unit_cost
                FROM ingredient_batches ib
                LEFT JOIN ingredients i ON ib.ingredient_id = i.id
                WHERE ib.id = ?
            ");
            $stmt->execute([$sourceId]);
            $item = $stmt->fetch();
            
            if (!$item) {
                return ['valid' => false, 'error' => 'Ingredient batch not found'];
            }
            
            $available = floatval($item['current_quantity'] ?? $item['quantity'] ?? 0);
            if ($quantity > $available) {
                return ['valid' => false, 'error' => "Insufficient quantity. Available: $available"];
            }
            
            return [
                'valid' => true,
                'reference' => $item['batch_number'] ?? $item['id'],
                'product_name' => $item['ingredient_name'] ?? 'Ingredient',
                'unit_cost' => floatval($item['unit_cost'] ?? 0)
            ];
            
        default:
            return ['valid' => false, 'error' => 'Invalid source type'];
    }
}

/**
 * Execute disposal - deduct from inventory
 */
function executeDisposal($db, $disposal, $currentUser) {
    $sourceType = $disposal['source_type'];
    $sourceId = $disposal['source_id'];
    $quantity = floatval($disposal['quantity']);
    
    switch ($sourceType) {
        case 'raw_milk':
            // Deduct from raw_milk_inventory
            $stmt = $db->prepare("
                UPDATE raw_milk_inventory SET
                    remaining_liters = GREATEST(0, COALESCE(remaining_liters, volume_liters, 0) - ?),
                    disposed_liters = COALESCE(disposed_liters, 0) + ?,
                    disposal_id = ?,
                    disposed_at = NOW(),
                    disposal_reason = ?,
                    status = CASE 
                        WHEN COALESCE(remaining_liters, volume_liters, 0) - ? <= 0 THEN 'consumed'
                        ELSE status 
                    END
                WHERE id = ?
            ");
            $stmt->execute([
                $quantity, $quantity,
                $disposal['id'],
                $disposal['disposal_reason'],
                $quantity,
                $sourceId
            ]);
            break;
            
        case 'finished_goods':
            // Deduct from finished_goods_inventory
            $stmt = $db->prepare("
                UPDATE finished_goods_inventory SET
                    quantity_available = GREATEST(0, COALESCE(quantity_available, remaining_quantity) - ?),
                    remaining_quantity = GREATEST(0, COALESCE(remaining_quantity, 0) - ?),
                    disposed_quantity = COALESCE(disposed_quantity, 0) + ?,
                    disposal_id = ?,
                    disposed_at = NOW(),
                    disposal_reason = ?,
                    status = CASE 
                        WHEN COALESCE(quantity_available, remaining_quantity) - ? <= 0 THEN 'consumed'
                        ELSE status 
                    END
                WHERE id = ?
            ");
            $stmt->execute([
                $quantity, $quantity, $quantity,
                $disposal['id'],
                $disposal['disposal_reason'],
                $quantity,
                $sourceId
            ]);
            
            // Create inventory transaction record
            $txCode = 'DSP-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $txStmt = $db->prepare("
                INSERT INTO fg_inventory_transactions (
                    transaction_code, transaction_type, inventory_id, product_id,
                    quantity, performed_by, reason, reference_type, reference_id
                ) VALUES (?, 'disposal', ?, ?, ?, ?, ?, 'disposal', ?)
            ");
            $txStmt->execute([
                $txCode, $sourceId, $disposal['product_id'],
                $quantity, $currentUser['user_id'],
                'Disposal: ' . $disposal['disposal_code'] . ' - ' . $disposal['disposal_reason'],
                $disposal['id']
            ]);
            break;
            
        case 'ingredients':
            // Deduct from ingredient_batches
            $stmt = $db->prepare("
                UPDATE ingredient_batches SET
                    current_quantity = GREATEST(0, COALESCE(current_quantity, quantity) - ?),
                    status = CASE 
                        WHEN COALESCE(current_quantity, quantity) - ? <= 0 THEN 'consumed'
                        ELSE status 
                    END
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $quantity, $sourceId]);
            break;
            
        case 'milk_receiving':
            // Update milk_receiving status
            $stmt = $db->prepare("
                UPDATE milk_receiving SET
                    status = 'rejected',
                    rejected_liters = COALESCE(rejected_liters, 0) + ?
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $sourceId]);
            break;
            
        case 'production_batch':
            // Update production batch status
            $stmt = $db->prepare("
                UPDATE production_batches SET
                    qc_status = 'rejected',
                    qc_notes = CONCAT(COALESCE(qc_notes, ''), '\n[Disposed] ', ?)
                WHERE id = ?
            ");
            $stmt->execute([$disposal['disposal_reason'], $sourceId]);
            break;
    }
}

/**
 * Generate unique disposal code
 */
function generateDisposalCode($db) {
    $date = date('Ymd');
    
    // Get today's count
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM disposals 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $count = $stmt->fetchColumn() + 1;
    
    return 'DSP-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}
