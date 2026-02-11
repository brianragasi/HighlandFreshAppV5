<?php
/**
 * Highland Fresh System - Maintenance Requisitions API
 * 
 * Handles MRO part requests from Maintenance to Warehouse
 * Requires GM approval before warehouse fulfills
 * 
 * GET    - List requisitions / Get single requisition
 * POST   - Create new requisition
 * PUT    - Update requisition / Approve / Reject / Fulfill
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Maintenance Head, Warehouse, or GM role
$currentUser = Auth::requireRole(['maintenance_head', 'warehouse_raw', 'general_manager']);

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            $id = getParam('id');
            
            if ($id) {
                // Get single requisition with items
                $stmt = $db->prepare("
                    SELECT mr.*, 
                           m.machine_name, m.machine_code,
                           rep.repair_code,
                           u1.first_name as requested_by_first, u1.last_name as requested_by_last,
                           u2.first_name as approved_by_first, u2.last_name as approved_by_last,
                           u3.first_name as fulfilled_by_first, u3.last_name as fulfilled_by_last
                    FROM maintenance_requisitions mr
                    LEFT JOIN machines m ON mr.machine_id = m.id
                    LEFT JOIN machine_repairs rep ON mr.repair_id = rep.id
                    LEFT JOIN users u1 ON mr.requested_by = u1.id
                    LEFT JOIN users u2 ON mr.approved_by = u2.id
                    LEFT JOIN users u3 ON mr.fulfilled_by = u3.id
                    WHERE mr.id = ?
                ");
                $stmt->execute([$id]);
                $requisition = $stmt->fetch();
                
                if (!$requisition) {
                    Response::notFound('Requisition not found');
                }
                
                // Get items
                $itemsStmt = $db->prepare("
                    SELECT mri.*, mi.item_name, mi.item_code, mi.current_stock
                    FROM maintenance_requisition_items mri
                    JOIN mro_items mi ON mri.mro_item_id = mi.id
                    WHERE mri.requisition_id = ?
                ");
                $itemsStmt->execute([$id]);
                $requisition['items'] = $itemsStmt->fetchAll();
                
                Response::success($requisition, 'Requisition retrieved successfully');
            }
            
            // List requisitions
            $status = getParam('status');
            $repairId = getParam('repair_id');
            $machineId = getParam('machine_id');
            $dateFrom = getParam('date_from');
            $dateTo = getParam('date_to');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $where = "WHERE 1=1";
            $params = [];
            
            if ($status) {
                $where .= " AND mr.status = ?";
                $params[] = $status;
            }
            
            if ($repairId) {
                $where .= " AND mr.repair_id = ?";
                $params[] = $repairId;
            }
            
            if ($machineId) {
                $where .= " AND mr.machine_id = ?";
                $params[] = $machineId;
            }
            
            if ($dateFrom) {
                $where .= " AND DATE(mr.created_at) >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $where .= " AND DATE(mr.created_at) <= ?";
                $params[] = $dateTo;
            }
            
            // Count total
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM maintenance_requisitions mr {$where}");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get requisitions
            $stmt = $db->prepare("
                SELECT mr.id, mr.requisition_code, mr.status, mr.priority, mr.total_items,
                       mr.purpose, mr.created_at,
                       m.machine_name, m.machine_code,
                       rep.repair_code,
                       u.first_name as requested_by_first, u.last_name as requested_by_last
                FROM maintenance_requisitions mr
                LEFT JOIN machines m ON mr.machine_id = m.id
                LEFT JOIN machine_repairs rep ON mr.repair_id = rep.id
                LEFT JOIN users u ON mr.requested_by = u.id
                {$where}
                ORDER BY 
                    CASE mr.priority 
                        WHEN 'urgent' THEN 1 
                        WHEN 'high' THEN 2 
                        WHEN 'normal' THEN 3 
                        ELSE 4 
                    END,
                    mr.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            
            Response::paginated($stmt->fetchAll(), $total, $page, $limit, 'Requisitions retrieved successfully');
            break;
            
        case 'POST':
            // Create new requisition - Maintenance Head only
            if ($currentUser['role'] !== 'maintenance_head') {
                Response::forbidden('Only Maintenance Head can create requisitions');
            }
            
            $repairId = getParam('repair_id');
            $machineId = getParam('machine_id');
            $priority = getParam('priority', 'normal');
            $neededBy = getParam('needed_by');
            $purpose = trim(getParam('purpose', ''));
            $items = getParam('items', []);
            
            // Validation
            $errors = [];
            if (empty($items) || !is_array($items)) {
                $errors['items'] = 'At least one item is required';
            }
            if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) {
                $errors['priority'] = 'Invalid priority level';
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            $db->beginTransaction();
            
            try {
                // Generate requisition code
                $today = date('Ymd');
                $codeStmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requisitions WHERE requisition_code LIKE ?");
                $codeStmt->execute(["MRO-{$today}-%"]);
                $count = $codeStmt->fetch()['count'] + 1;
                $requisitionCode = "MRO-{$today}-" . str_pad($count, 3, '0', STR_PAD_LEFT);
                
                // Insert requisition
                $stmt = $db->prepare("
                    INSERT INTO maintenance_requisitions (
                        requisition_code, repair_id, machine_id, requested_by, 
                        priority, needed_by_date, purpose, total_items, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                
                $stmt->execute([
                    $requisitionCode, 
                    $repairId ?: null,
                    $machineId ?: null,
                    $currentUser['user_id'],
                    $priority,
                    $neededBy,
                    $purpose,
                    count($items)
                ]);
                
                $requisitionId = $db->lastInsertId();
                
                // Insert items
                $itemStmt = $db->prepare("
                    INSERT INTO maintenance_requisition_items (
                        requisition_id, mro_item_id, requested_quantity, unit_of_measure, notes
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($items as $item) {
                    $itemStmt->execute([
                        $requisitionId,
                        $item['mro_item_id'],
                        $item['quantity'],
                        $item['unit'] ?? 'pcs',
                        $item['notes'] ?? ''
                    ]);
                }
                
                $db->commit();
                
                Response::created([
                    'id' => $requisitionId,
                    'requisition_code' => $requisitionCode,
                    'status' => 'pending',
                    'total_items' => count($items)
                ], 'Requisition created successfully - Awaiting GM approval');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'PUT':
            $id = getParam('id');
            $action = getParam('action');
            
            if (!$id) {
                Response::validationError(['id' => 'Requisition ID is required']);
            }
            
            // Get current requisition
            $stmt = $db->prepare("SELECT * FROM maintenance_requisitions WHERE id = ?");
            $stmt->execute([$id]);
            $requisition = $stmt->fetch();
            
            if (!$requisition) {
                Response::notFound('Requisition not found');
            }
            
            switch ($action) {
                case 'approve':
                    // GM only
                    if ($currentUser['role'] !== 'general_manager') {
                        Response::forbidden('Only General Manager can approve requisitions');
                    }
                    
                    if ($requisition['status'] !== 'pending') {
                        Response::error('Can only approve pending requisitions', 400);
                    }
                    
                    $stmt = $db->prepare("
                        UPDATE maintenance_requisitions 
                        SET status = 'approved', approved_by = ?, approved_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$currentUser['user_id'], $id]);
                    
                    Response::success(null, 'Requisition approved');
                    break;
                    
                case 'reject':
                    // GM only
                    if ($currentUser['role'] !== 'general_manager') {
                        Response::forbidden('Only General Manager can reject requisitions');
                    }
                    
                    if ($requisition['status'] !== 'pending') {
                        Response::error('Can only reject pending requisitions', 400);
                    }
                    
                    $reason = getParam('reason', '');
                    
                    $stmt = $db->prepare("
                        UPDATE maintenance_requisitions 
                        SET status = 'rejected', 
                            rejected_by = ?, 
                            rejected_at = NOW(),
                            rejection_reason = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$currentUser['user_id'], $reason, $id]);
                    
                    Response::success(null, 'Requisition rejected');
                    break;
                    
                case 'fulfill':
                    // Warehouse or GM
                    if (!in_array($currentUser['role'], ['warehouse_raw', 'general_manager'])) {
                        Response::forbidden('Only Warehouse can fulfill requisitions');
                    }
                    
                    if ($requisition['status'] !== 'approved') {
                        Response::error('Can only fulfill approved requisitions', 400);
                    }
                    
                    $issuedItems = getParam('items', []);
                    
                    $db->beginTransaction();
                    try {
                        $allFulfilled = true;
                        $anyFulfilled = false;
                        
                        foreach ($issuedItems as $item) {
                            $itemId = $item['item_id'];
                            $issuedQty = (float) $item['issued_quantity'];
                            
                            // Get the requisition item
                            $itemStmt = $db->prepare("
                                SELECT mri.*, mi.current_stock 
                                FROM maintenance_requisition_items mri
                                JOIN mro_items mi ON mri.mro_item_id = mi.id
                                WHERE mri.id = ?
                            ");
                            $itemStmt->execute([$itemId]);
                            $reqItem = $itemStmt->fetch();
                            
                            if ($reqItem && $issuedQty > 0) {
                                // Check stock
                                if ($reqItem['current_stock'] < $issuedQty) {
                                    $issuedQty = $reqItem['current_stock'];
                                }
                                
                                // Update issued quantity
                                $updateStmt = $db->prepare("
                                    UPDATE maintenance_requisition_items 
                                    SET issued_quantity = ? 
                                    WHERE id = ?
                                ");
                                $updateStmt->execute([$issuedQty, $itemId]);
                                
                                // Deduct from MRO stock
                                $stockStmt = $db->prepare("
                                    UPDATE mro_items 
                                    SET current_stock = current_stock - ? 
                                    WHERE id = ?
                                ");
                                $stockStmt->execute([$issuedQty, $reqItem['mro_item_id']]);
                                
                                if ($issuedQty < $reqItem['requested_quantity']) {
                                    $allFulfilled = false;
                                }
                                $anyFulfilled = true;
                            } else {
                                $allFulfilled = false;
                            }
                        }
                        
                        // Update requisition status
                        $newStatus = $allFulfilled ? 'fulfilled' : ($anyFulfilled ? 'partially_fulfilled' : 'approved');
                        
                        $stmt = $db->prepare("
                            UPDATE maintenance_requisitions 
                            SET status = ?, fulfilled_by = ?, fulfilled_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$newStatus, $currentUser['user_id'], $id]);
                        
                        $db->commit();
                        Response::success(['status' => $newStatus], 'Requisition fulfilled');
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;
                    
                case 'cancel':
                    if (!in_array($requisition['status'], ['pending'])) {
                        Response::error('Can only cancel pending requisitions', 400);
                    }
                    
                    $stmt = $db->prepare("UPDATE maintenance_requisitions SET status = 'cancelled' WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    Response::success(null, 'Requisition cancelled');
                    break;
                    
                default:
                    Response::error('Invalid action', 400);
            }
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Maintenance Requisitions API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
