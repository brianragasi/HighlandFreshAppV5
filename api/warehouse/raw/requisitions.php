<?php
/**
 * Highland Fresh System - Warehouse Raw Requisitions API
 * 
 * Manages requisition fulfillment from production/maintenance
 * 
 * GET    - List requisitions, get details
 * PUT    - Fulfill, partial fulfill, reject requisitions
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

// Require Warehouse Raw role
$currentUser = Auth::requireRole(['warehouse_raw', 'general_manager', 'production_staff', 'maintenance_head']);

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $currentUser);
            break;
        case 'PUT':
            handlePut($db, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Warehouse Raw Requisitions API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}

/**
 * Handle GET requests
 */
function handleGet($db, $currentUser) {
    $action = getParam('action', 'list');
    $id = getParam('id');
    
    switch ($action) {
        case 'list':
            // Get all requisitions for warehouse to fulfill
            $status = getParam('status');
            $department = getParam('department');
            $priority = getParam('priority');
            
            $sql = "
                SELECT 
                    ir.*,
                    u.first_name as requested_by_first,
                    u.last_name as requested_by_last,
                    ua.first_name as approved_by_first,
                    ua.last_name as approved_by_last,
                    uf.first_name as fulfilled_by_first,
                    uf.last_name as fulfilled_by_last,
                    (SELECT COUNT(*) FROM requisition_items ri WHERE ri.requisition_id = ir.id) as item_count,
                    (SELECT COUNT(*) FROM requisition_items ri 
                     WHERE ri.requisition_id = ir.id AND ri.status IN ('fulfilled', 'partial')) as processed_count,
                    (SELECT COUNT(*) FROM requisition_items ri 
                     WHERE ri.requisition_id = ir.id AND ri.status = 'fulfilled') as fulfilled_count
                FROM material_requisitions ir
                JOIN users u ON ir.requested_by = u.id
                LEFT JOIN users ua ON ir.approved_by = ua.id
                LEFT JOIN users uf ON ir.fulfilled_by = uf.id
                WHERE 1=1
            ";
            $params = [];
            
            // Warehouse sees pending, approved and fulfilled requisitions
            // Pending shown so warehouse can prepare, but only approved can be fulfilled
            if (!$status) {
                if (in_array($currentUser['role'], ['warehouse_raw', 'general_manager'])) {
                    $sql .= " AND ir.status IN ('pending', 'approved', 'partial', 'fulfilled')";
                } else {
                    // Requesters see their own requisitions
                    $sql .= " AND ir.requested_by = ?";
                    $params[] = $currentUser['user_id'];
                }
            } else {
                $sql .= " AND ir.status = ?";
                $params[] = $status;
            }
            
            if ($department) {
                $sql .= " AND ir.department = ?";
                $params[] = $department;
            }
            
            if ($priority) {
                $sql .= " AND ir.priority = ?";
                $params[] = $priority;
            }
            
            $sql .= " ORDER BY 
                CASE ir.status WHEN 'approved' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END,
                CASE ir.priority 
                    WHEN 'urgent' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'normal' THEN 3 
                    ELSE 4 
                END,
                ir.needed_by_date ASC,
                ir.created_at ASC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $requisitions = $stmt->fetchAll();
            
            Response::success(['requisitions' => $requisitions], 'Requisitions retrieved successfully');
            break;
            
        case 'detail':
            if (!$id) {
                Response::error('Requisition ID is required', 400);
            }
            
            // Get requisition details
            $requisition = $db->prepare("
                SELECT 
                    ir.*,
                    u.first_name as requested_by_first,
                    u.last_name as requested_by_last,
                    ua.first_name as approved_by_first,
                    ua.last_name as approved_by_last,
                    uf.first_name as fulfilled_by_first,
                    uf.last_name as fulfilled_by_last
                FROM material_requisitions ir
                JOIN users u ON ir.requested_by = u.id
                LEFT JOIN users ua ON ir.approved_by = ua.id
                LEFT JOIN users uf ON ir.fulfilled_by = uf.id
                WHERE ir.id = ?
            ");
            $requisition->execute([$id]);
            $requisitionData = $requisition->fetch();
            
            if (!$requisitionData) {
                Response::error('Requisition not found', 404);
            }
            
            // Get requisition items
            $items = $db->prepare("
                SELECT 
                    ri.*,
                    uf.first_name as fulfilled_by_first,
                    uf.last_name as fulfilled_by_last,
                    CASE 
                        -- Explicit raw_milk type OR item_name matches raw milk patterns
                        WHEN ri.item_type = 'raw_milk' OR LOWER(ri.item_name) IN ('raw', 'raw milk', 'fresh milk', 'carabao', 'cow milk', 'goat milk', 'whole milk')
                            OR (LOWER(ri.item_name) LIKE '%milk%' AND LOWER(ri.item_name) NOT LIKE '%powder%' AND LOWER(ri.item_name) NOT LIKE '%chocolate%')
                        THEN (SELECT COALESCE(SUM(remaining_liters), 0) FROM raw_milk_inventory WHERE status IN ('available', 'reserved') AND remaining_liters > 0)
                        WHEN ri.item_type = 'ingredient' THEN (SELECT COALESCE(current_stock, 0) FROM ingredients WHERE id = ri.item_id)
                        WHEN ri.item_type = 'mro' THEN (SELECT COALESCE(current_stock, 0) FROM mro_items WHERE id = ri.item_id)
                        ELSE 0
                    END as available_stock,
                    CASE 
                        WHEN ri.item_type = 'raw_milk' OR LOWER(ri.item_name) IN ('raw', 'raw milk', 'fresh milk', 'carabao', 'cow milk', 'goat milk', 'whole milk')
                            OR (LOWER(ri.item_name) LIKE '%milk%' AND LOWER(ri.item_name) NOT LIKE '%powder%' AND LOWER(ri.item_name) NOT LIKE '%chocolate%')
                        THEN 'raw_milk'
                        ELSE ri.item_type
                    END as effective_item_type
                FROM requisition_items ri
                LEFT JOIN users uf ON ri.fulfilled_by = uf.id
                WHERE ri.requisition_id = ?
                ORDER BY ri.id ASC
            ");
            $items->execute([$id]);
            $itemList = $items->fetchAll();
            
            Response::success([
                'requisition' => $requisitionData,
                'items' => $itemList
            ], 'Requisition details retrieved successfully');
            break;
            
        case 'pending_count':
            // Get count of pending requisitions to fulfill
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
                    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high,
                    SUM(CASE WHEN needed_by_date <= CURDATE() THEN 1 ELSE 0 END) as overdue
                FROM material_requisitions
                WHERE status = 'approved'
            ");
            $stmt->execute();
            $counts = $stmt->fetch();
            
            Response::success($counts, 'Pending count retrieved successfully');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle PUT requests - Fulfill requisitions
 */
function handlePut($db, $currentUser) {
    // Only warehouse_raw and GM can fulfill
    if (!in_array($currentUser['role'], ['warehouse_raw', 'general_manager'])) {
        Response::error('Only Warehouse Raw staff can fulfill requisitions', 403);
    }
    
    $action = getParam('action');
    $id = getParam('id');
    
    if (!$action || !$id) {
        Response::error('Action and requisition ID are required', 400);
    }
    
    switch ($action) {
        case 'approve':
            // Approve a pending requisition
            // Only GM or warehouse_raw (supervisor) can approve
            if (!in_array($currentUser['role'], ['warehouse_raw', 'general_manager', 'admin'])) {
                Response::error('Not authorized to approve requisitions', 403);
            }
            
            try {
                // Get requisition
                $requisition = $db->prepare("
                    SELECT * FROM material_requisitions WHERE id = ? AND status = 'pending'
                ");
                $requisition->execute([$id]);
                $reqData = $requisition->fetch();
                
                if (!$reqData) {
                    Response::error('Requisition not found or not pending', 404);
                }
                
                // Update requisition status to approved
                $stmt = $db->prepare("
                    UPDATE material_requisitions 
                    SET status = 'approved',
                        approved_by = ?,
                        approved_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$currentUser['user_id'], $id]);
                
                logAudit($currentUser['user_id'], 'approve_requisition', 'material_requisitions', $id, 
                    null, ['status' => 'approved']);
                
                Response::success([
                    'id' => $id,
                    'status' => 'approved'
                ], 'Requisition approved successfully');
                
            } catch (Exception $e) {
                Response::error('Failed to approve requisition: ' . $e->getMessage(), 500);
            }
            break;
            
        case 'reject':
            // Reject a pending requisition
            if (!in_array($currentUser['role'], ['warehouse_raw', 'general_manager', 'admin'])) {
                Response::error('Not authorized to reject requisitions', 403);
            }
            
            $reason = getParam('reason', '');
            
            try {
                // Get requisition
                $requisition = $db->prepare("
                    SELECT * FROM material_requisitions WHERE id = ? AND status = 'pending'
                ");
                $requisition->execute([$id]);
                $reqData = $requisition->fetch();
                
                if (!$reqData) {
                    Response::error('Requisition not found or not pending', 404);
                }
                
                // Update requisition status to rejected
                $stmt = $db->prepare("
                    UPDATE material_requisitions 
                    SET status = 'rejected',
                        notes = CONCAT(COALESCE(notes, ''), '\nRejected: ', ?),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$reason, $id]);
                
                logAudit($currentUser['user_id'], 'reject_requisition', 'material_requisitions', $id, 
                    null, ['status' => 'rejected', 'reason' => $reason]);
                
                Response::success([
                    'id' => $id,
                    'status' => 'rejected'
                ], 'Requisition rejected');
                
            } catch (Exception $e) {
                Response::error('Failed to reject requisition: ' . $e->getMessage(), 500);
            }
            break;
            
        case 'fulfill':
            // Fulfill entire requisition
            $db->beginTransaction();
            
            try {
                // Get requisition - allow approved or partially fulfilled
                $requisition = $db->prepare("
                    SELECT * FROM material_requisitions WHERE id = ? AND status IN ('approved', 'partial', 'in_progress')
                ");
                $requisition->execute([$id]);
                $reqData = $requisition->fetch();
                
                if (!$reqData) {
                    throw new Exception('Requisition not found or not in a fulfillable status');
                }
                
                // Get all pending items
                $items = $db->prepare("
                    SELECT ri.*, 
                        CASE ri.item_type
                            WHEN 'ingredient' THEN i.unit_of_measure
                            WHEN 'mro' THEN m.unit_of_measure
                        END as unit_of_measure
                    FROM requisition_items ri
                    LEFT JOIN ingredients i ON ri.item_type = 'ingredient' AND ri.item_id = i.id
                    LEFT JOIN mro_items m ON ri.item_type = 'mro' AND ri.item_id = m.id
                    WHERE ri.requisition_id = ? AND ri.status IN ('pending', 'partial')
                ");
                $items->execute([$id]);
                $itemList = $items->fetchAll();
                
                if (empty($itemList)) {
                    throw new Exception('No items to fulfill');
                }
                
                $fulfilledItems = [];
                $issuedQuantities = getParam('issued_quantities', []); // Optional: specify exact quantities
                
                foreach ($itemList as $item) {
                    $requestedQty = $item['requested_quantity'];
                    $issuedQty = isset($issuedQuantities[$item['id']]) 
                        ? (float)$issuedQuantities[$item['id']] 
                        : $requestedQty;
                    
                    // Determine effective item type - detect raw milk from item_name if not explicitly set
                    $effectiveItemType = $item['item_type'];
                    $itemNameLower = strtolower(trim($item['item_name'] ?? ''));
                    $rawMilkPatterns = ['raw', 'raw milk', 'fresh milk', 'carabao', 'cow milk', 'goat milk', 'whole milk'];
                    
                    foreach ($rawMilkPatterns as $pattern) {
                        if ($itemNameLower === $pattern || strpos($itemNameLower, $pattern) !== false) {
                            $effectiveItemType = 'raw_milk';
                            break;
                        }
                    }
                    // Also check for 'milk' but exclude processed products
                    if ($effectiveItemType !== 'raw_milk' && strpos($itemNameLower, 'milk') !== false) {
                        if (strpos($itemNameLower, 'powder') === false && strpos($itemNameLower, 'chocolate') === false) {
                            $effectiveItemType = 'raw_milk';
                        }
                    }
                    
                    if ($effectiveItemType === 'raw_milk') {
                        // Issue from tanks
                        $result = issueMilk($db, $issuedQty, $id, $currentUser);
                        $fulfilledItems[] = array_merge($item, ['issued' => $result]);
                    } elseif ($effectiveItemType === 'ingredient') {
                        // Issue ingredient
                        $result = issueIngredient($db, $item['item_id'], $issuedQty, $id, $currentUser);
                        $fulfilledItems[] = array_merge($item, ['issued' => $result]);
                    } elseif ($effectiveItemType === 'mro') {
                        // Issue MRO item
                        $result = issueMRO($db, $item['item_id'], $issuedQty, $id, $currentUser);
                        $fulfilledItems[] = array_merge($item, ['issued' => $result]);
                    }
                    
                    // Update requisition item - accumulate issued quantity
                    $stmt = $db->prepare("
                        UPDATE requisition_items 
                        SET issued_quantity = COALESCE(issued_quantity, 0) + ?, 
                            status = CASE WHEN (COALESCE(issued_quantity, 0) + ?) >= requested_quantity THEN 'fulfilled' ELSE 'partial' END,
                            fulfilled_by = ?,
                            fulfilled_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$issuedQty, $issuedQty, $currentUser['user_id'], $item['id']]);
                }
                
                // Check if all items are fulfilled to determine requisition status
                $checkItems = $db->prepare("
                    SELECT 
                        COUNT(*) as total_items,
                        SUM(CASE WHEN status = 'fulfilled' THEN 1 ELSE 0 END) as fulfilled_items,
                        SUM(CASE WHEN status IN ('partial', 'fulfilled') THEN 1 ELSE 0 END) as processed_items
                    FROM requisition_items WHERE requisition_id = ?
                ");
                $checkItems->execute([$id]);
                $itemCounts = $checkItems->fetch();
                
                // Determine requisition status based on item statuses
                $newReqStatus = 'approved'; // Default
                if ($itemCounts['fulfilled_items'] == $itemCounts['total_items']) {
                    $newReqStatus = 'fulfilled'; // All items fully fulfilled
                } elseif ($itemCounts['processed_items'] > 0) {
                    $newReqStatus = 'partial'; // Some items processed but not all complete
                }
                
                // Update requisition status
                $stmt = $db->prepare("
                    UPDATE material_requisitions 
                    SET status = ?,
                        fulfilled_by = ?,
                        fulfilled_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$newReqStatus, $currentUser['user_id'], $id]);
                
                // Log audit
                logAudit($currentUser['user_id'], 'fulfill_requisition', 'material_requisitions', $id, 
                    ['status' => 'approved'], 
                    ['status' => 'fulfilled']
                );
                
                $db->commit();
                
                Response::success([
                    'requisition_id' => $id,
                    'fulfilled_items' => $fulfilledItems
                ], 'Requisition fulfilled successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'fulfill_item':
            // Fulfill a single item from requisition
            $itemId = getParam('item_id');
            $issuedQuantity = getParam('issued_quantity');
            
            if (!$itemId || $issuedQuantity === null) {
                Response::error('Item ID and issued quantity are required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Get item
                $item = $db->prepare("
                    SELECT ri.*, ir.status as req_status
                    FROM requisition_items ri
                    JOIN material_requisitions ir ON ri.requisition_id = ir.id
                    WHERE ri.id = ? AND ri.requisition_id = ?
                ");
                $item->execute([$itemId, $id]);
                $itemData = $item->fetch();
                
                if (!$itemData) {
                    throw new Exception('Item not found');
                }
                
                // Allow fulfillment for approved, partial, or in_progress requisitions
                if (!in_array($itemData['req_status'], ['approved', 'partial', 'in_progress'])) {
                    throw new Exception('Requisition is not in a fulfillable status');
                }
                
                // Determine effective item type - detect raw milk from item_name if not explicitly set
                $effectiveItemType = $itemData['item_type'];
                $itemNameLower = strtolower(trim($itemData['item_name'] ?? ''));
                $rawMilkPatterns = ['raw', 'raw milk', 'fresh milk', 'carabao', 'cow milk', 'goat milk', 'whole milk'];
                
                foreach ($rawMilkPatterns as $pattern) {
                    if ($itemNameLower === $pattern || strpos($itemNameLower, $pattern) !== false) {
                        $effectiveItemType = 'raw_milk';
                        break;
                    }
                }
                // Also check for 'milk' but exclude processed products
                if ($effectiveItemType !== 'raw_milk' && strpos($itemNameLower, 'milk') !== false) {
                    if (strpos($itemNameLower, 'powder') === false && strpos($itemNameLower, 'chocolate') === false) {
                        $effectiveItemType = 'raw_milk';
                    }
                }
                
                // Issue based on type
                if ($effectiveItemType === 'raw_milk') {
                    issueMilk($db, $issuedQuantity, $id, $currentUser);
                } elseif ($effectiveItemType === 'ingredient') {
                    issueIngredient($db, $itemData['item_id'], $issuedQuantity, $id, $currentUser);
                } elseif ($effectiveItemType === 'mro') {
                    issueMRO($db, $itemData['item_id'], $issuedQuantity, $id, $currentUser);
                }
                
                // Update item - calculate new total and check against requested
                $currentIssued = floatval($itemData['issued_quantity'] ?? 0);
                $newTotalIssued = $currentIssued + floatval($issuedQuantity);
                $newStatus = $newTotalIssued >= floatval($itemData['requested_quantity']) ? 'fulfilled' : 'partial';
                
                $stmt = $db->prepare("
                    UPDATE requisition_items 
                    SET issued_quantity = ?, 
                        status = ?,
                        fulfilled_by = ?,
                        fulfilled_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$newTotalIssued, $newStatus, $currentUser['user_id'], $itemId]);
                
                // Check if all items are fulfilled
                $checkAll = $db->prepare("
                    SELECT 
                        COUNT(*) as total_items,
                        SUM(CASE WHEN status = 'fulfilled' THEN 1 ELSE 0 END) as fulfilled_items,
                        SUM(CASE WHEN status IN ('partial', 'fulfilled') THEN 1 ELSE 0 END) as processed_items
                    FROM requisition_items 
                    WHERE requisition_id = ?
                ");
                $checkAll->execute([$id]);
                $itemCounts = $checkAll->fetch();
                
                // Determine requisition status based on item statuses
                $newReqStatus = 'approved'; // Default - still being processed
                if ($itemCounts['fulfilled_items'] == $itemCounts['total_items']) {
                    $newReqStatus = 'fulfilled'; // All items fully fulfilled
                } elseif ($itemCounts['processed_items'] > 0) {
                    $newReqStatus = 'partial'; // Some items processed but not all complete
                }
                
                // Update requisition status
                $stmt = $db->prepare("
                    UPDATE material_requisitions 
                    SET status = ?, fulfilled_by = ?, fulfilled_at = NOW(), updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$newReqStatus, $currentUser['user_id'], $id]);
                
                $db->commit();
                
                Response::success([
                    'item_id' => $itemId,
                    'issued_quantity' => $issuedQuantity,
                    'status' => $newStatus
                ], 'Item fulfilled successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'reject_item':
            // Reject/cancel an item (out of stock, etc.)
            $itemId = getParam('item_id');
            $reason = getParam('reason');
            
            if (!$itemId || !$reason) {
                Response::error('Item ID and reason are required', 400);
            }
            
            $stmt = $db->prepare("
                UPDATE requisition_items 
                SET status = 'cancelled', notes = ?, updated_at = NOW()
                WHERE id = ? AND requisition_id = ?
            ");
            $stmt->execute([$reason, $itemId, $id]);
            
            Response::success(null, 'Item cancelled successfully');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Issue milk from tanks (FIFO)
 * REVISED: Uses raw_milk_inventory instead of tank_milk_batches
 */
function issueMilk($db, $liters, $requisitionId, $currentUser) {
    // Get available batches from raw_milk_inventory (FIFO)
    $batches = $db->prepare("
        SELECT rmi.*, st.tank_code
        FROM raw_milk_inventory rmi
        LEFT JOIN storage_tanks st ON rmi.tank_id = st.id
        WHERE rmi.status IN ('available', 'reserved')
        AND rmi.remaining_liters > 0
        ORDER BY rmi.expiry_date ASC, rmi.received_date ASC, rmi.id ASC
    ");
    $batches->execute();
    $batchList = $batches->fetchAll();
    
    $totalAvailable = array_sum(array_column($batchList, 'remaining_liters'));
    
    if ($totalAvailable < $liters) {
        throw new Exception("Insufficient milk. Available: {$totalAvailable}L, Needed: {$liters}L");
    }
    
    $remainingToIssue = $liters;
    $issued = [];
    
    foreach ($batchList as $batch) {
        if ($remainingToIssue <= 0) break;
        
        $issueFromBatch = min($batch['remaining_liters'], $remainingToIssue);
        $newRemaining = $batch['remaining_liters'] - $issueFromBatch;
        $newStatus = $newRemaining > 0 ? 'available' : 'depleted';
        
        // Update raw_milk_inventory batch
        $stmt = $db->prepare("
            UPDATE raw_milk_inventory 
            SET remaining_liters = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newRemaining, $newStatus, $batch['id']]);
        
        // Update tank volume if assigned to a tank
        if ($batch['tank_id']) {
            $stmt = $db->prepare("
                UPDATE storage_tanks 
                SET current_volume = current_volume - ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$issueFromBatch, $batch['tank_id']]);
        }
        
        // Create transaction
        $txCode = generateCode('TX');
        $stmt = $db->prepare("
            INSERT INTO inventory_transactions 
            (transaction_code, transaction_type, item_type, item_id, batch_id,
             quantity, unit_of_measure, reference_type, reference_id,
             from_location, performed_by, reason)
            VALUES (?, 'issue', 'raw_milk', ?, ?, ?, 'L', 'requisition', ?, ?, ?, 'Requisition fulfillment')
        ");
        $stmt->execute([
            $txCode,
            $batch['id'],
            $batch['id'],
            $issueFromBatch,
            $requisitionId,
            $batch['tank_code'] ?? 'Unassigned',
            $currentUser['user_id']
        ]);
        
        $issued[] = [
            'tank_code' => $batch['tank_code'] ?? 'Unassigned',
            'liters' => $issueFromBatch
        ];
        
        $remainingToIssue -= $issueFromBatch;
    }
    
    return ['total_liters' => $liters, 'from_tanks' => $issued];
}

/**
 * Issue ingredient (FIFO)
 */
function issueIngredient($db, $ingredientId, $quantity, $requisitionId, $currentUser) {
    // Validate ingredient ID
    if (!$ingredientId || $ingredientId <= 0) {
        throw new Exception("Invalid ingredient ID");
    }
    
    $ingredient = $db->prepare("SELECT * FROM ingredients WHERE id = ?");
    $ingredient->execute([$ingredientId]);
    $ingredientData = $ingredient->fetch();
    
    if (!$ingredientData) {
        throw new Exception("Ingredient not found (ID: {$ingredientId})");
    }
    
    // Get available batches
    $batches = $db->prepare("
        SELECT * FROM ingredient_batches
        WHERE ingredient_id = ?
        AND status IN ('available', 'partially_used')
        AND remaining_quantity > 0
        ORDER BY expiry_date ASC, received_date ASC, id ASC
    ");
    $batches->execute([$ingredientId]);
    $batchList = $batches->fetchAll();
    
    $totalAvailable = array_sum(array_column($batchList, 'remaining_quantity'));
    
    if ($totalAvailable < $quantity) {
        throw new Exception("Insufficient {$ingredientData['ingredient_name']}. Available: {$totalAvailable}, Needed: {$quantity}");
    }
    
    $remainingToIssue = $quantity;
    $issued = [];
    
    foreach ($batchList as $batch) {
        if ($remainingToIssue <= 0) break;
        
        $issueFromBatch = min($batch['remaining_quantity'], $remainingToIssue);
        $newRemaining = $batch['remaining_quantity'] - $issueFromBatch;
        $newStatus = $newRemaining > 0 ? 'partially_used' : 'consumed';
        
        // Update batch
        $stmt = $db->prepare("
            UPDATE ingredient_batches 
            SET remaining_quantity = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newRemaining, $newStatus, $batch['id']]);
        
        // Create transaction
        $txCode = generateCode('TX');
        $stmt = $db->prepare("
            INSERT INTO inventory_transactions 
            (transaction_code, transaction_type, item_type, item_id, batch_id,
             quantity, unit_of_measure, reference_type, reference_id,
             from_location, performed_by, reason)
            VALUES (?, 'issue', 'ingredient', ?, ?, ?, ?, 'requisition', ?, ?, ?, 'Requisition fulfillment')
        ");
        $stmt->execute([
            $txCode,
            $ingredientId,
            $batch['id'],
            $issueFromBatch,
            $ingredientData['unit_of_measure'],
            $requisitionId,
            $ingredientData['storage_location'],
            $currentUser['user_id']
        ]);
        
        $issued[] = [
            'batch_code' => $batch['batch_code'],
            'quantity' => $issueFromBatch
        ];
        
        $remainingToIssue -= $issueFromBatch;
    }
    
    // Update ingredient current stock
    $stmt = $db->prepare("
        UPDATE ingredients SET current_stock = current_stock - ?, updated_at = NOW() WHERE id = ?
    ");
    $stmt->execute([$quantity, $ingredientId]);
    
    return ['total_quantity' => $quantity, 'from_batches' => $issued];
}

/**
 * Issue MRO item (FIFO)
 */
function issueMRO($db, $mroItemId, $quantity, $requisitionId, $currentUser) {
    $item = $db->prepare("SELECT * FROM mro_items WHERE id = ?");
    $item->execute([$mroItemId]);
    $itemData = $item->fetch();
    
    // Get available inventory
    $inventory = $db->prepare("
        SELECT * FROM mro_inventory
        WHERE mro_item_id = ?
        AND status IN ('available', 'partially_used')
        AND remaining_quantity > 0
        ORDER BY received_date ASC, id ASC
    ");
    $inventory->execute([$mroItemId]);
    $inventoryList = $inventory->fetchAll();
    
    $totalAvailable = array_sum(array_column($inventoryList, 'remaining_quantity'));
    
    if ($totalAvailable < $quantity) {
        throw new Exception("Insufficient {$itemData['item_name']}. Available: {$totalAvailable}, Needed: {$quantity}");
    }
    
    $remainingToIssue = $quantity;
    $issued = [];
    
    foreach ($inventoryList as $inv) {
        if ($remainingToIssue <= 0) break;
        
        $issueFromBatch = min($inv['remaining_quantity'], $remainingToIssue);
        $newRemaining = $inv['remaining_quantity'] - $issueFromBatch;
        $newStatus = $newRemaining > 0 ? 'partially_used' : 'consumed';
        
        // Update inventory
        $stmt = $db->prepare("
            UPDATE mro_inventory 
            SET remaining_quantity = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newRemaining, $newStatus, $inv['id']]);
        
        // Create transaction
        $txCode = generateCode('TX');
        $stmt = $db->prepare("
            INSERT INTO inventory_transactions 
            (transaction_code, transaction_type, item_type, item_id, batch_id,
             quantity, unit_of_measure, reference_type, reference_id,
             from_location, performed_by, reason)
            VALUES (?, 'issue', 'mro', ?, ?, ?, ?, 'requisition', ?, ?, ?, 'Requisition fulfillment')
        ");
        $stmt->execute([
            $txCode,
            $mroItemId,
            $inv['id'],
            $issueFromBatch,
            $itemData['unit_of_measure'],
            $requisitionId,
            $itemData['storage_location'],
            $currentUser['user_id']
        ]);
        
        $issued[] = [
            'batch_code' => $inv['batch_code'],
            'quantity' => $issueFromBatch
        ];
        
        $remainingToIssue -= $issueFromBatch;
    }
    
    // Update item current stock
    $stmt = $db->prepare("
        UPDATE mro_items SET current_stock = current_stock - ?, updated_at = NOW() WHERE id = ?
    ");
    $stmt->execute([$quantity, $mroItemId]);
    
    return ['total_quantity' => $quantity, 'from_batches' => $issued];
}

