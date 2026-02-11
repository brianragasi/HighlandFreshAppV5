<?php
/**
 * Highland Fresh System - Ingredient Requisitions API
 * 
 * Handles ingredient requests from Production to Warehouse Raw
 * Requires GM approval before warehouse can fulfill
 * 
 * GET    - List requisitions / Get single requisition
 * POST   - Create new requisition
 * PUT    - Update requisition / Approve / Reject
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Production or GM role
$currentUser = Auth::requireRole(['production_staff', 'general_manager', 'warehouse_raw']);

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            $reqId = getParam('id');
            
            if ($reqId) {
                // Get single requisition with items
                $stmt = $db->prepare("
                    SELECT ir.*, 
                           u1.first_name as requested_by_first, u1.last_name as requested_by_last,
                           u2.first_name as approved_by_first, u2.last_name as approved_by_last,
                           u3.first_name as fulfilled_by_first, u3.last_name as fulfilled_by_last
                    FROM material_requisitions ir
                    LEFT JOIN users u1 ON ir.requested_by = u1.id
                    LEFT JOIN users u2 ON ir.approved_by = u2.id
                    LEFT JOIN users u3 ON ir.fulfilled_by = u3.id
                    WHERE ir.id = ?
                ");
                $stmt->execute([$reqId]);
                $requisition = $stmt->fetch();
                
                if (!$requisition) {
                    Response::notFound('Requisition not found');
                }
                
                // Get items
                $itemsStmt = $db->prepare("SELECT * FROM requisition_items WHERE requisition_id = ?");
                $itemsStmt->execute([$reqId]);
                $requisition['items'] = $itemsStmt->fetchAll();
                
                Response::success($requisition, 'Requisition retrieved successfully');
            }
            
            // List requisitions
            $status = getParam('status');
            $productionRunId = getParam('run_id');
            $dateFrom = getParam('date_from');
            $dateTo = getParam('date_to');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $where = "WHERE 1=1";
            $params = [];
            
            if ($status) {
                $where .= " AND ir.status = ?";
                $params[] = $status;
            }
            
            if ($productionRunId) {
                $where .= " AND ir.production_run_id = ?";
                $params[] = $productionRunId;
            }
            
            if ($dateFrom) {
                $where .= " AND DATE(ir.created_at) >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $where .= " AND DATE(ir.created_at) <= ?";
                $params[] = $dateTo;
            }
            
            // Get total count
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM material_requisitions ir {$where}");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get requisitions
            $stmt = $db->prepare("
                SELECT ir.id, ir.requisition_code, ir.production_run_id, ir.status,
                       ir.priority, ir.needed_by_date, ir.purpose, ir.total_items, ir.created_at,
                       u1.first_name as requested_by_first, u1.last_name as requested_by_last,
                       pr.run_code
                FROM material_requisitions ir
                LEFT JOIN users u1 ON ir.requested_by = u1.id
                LEFT JOIN production_runs pr ON ir.production_run_id = pr.id
                {$where}
                ORDER BY 
                    CASE ir.priority 
                        WHEN 'urgent' THEN 1 
                        WHEN 'high' THEN 2 
                        WHEN 'normal' THEN 3 
                        WHEN 'low' THEN 4 
                    END,
                    ir.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $requisitions = $stmt->fetchAll();
            
            Response::paginated($requisitions, $total, $page, $limit, 'Requisitions retrieved successfully');
            break;
            
        case 'POST':
            // Create new requisition - Production staff only
            if ($currentUser['role'] !== 'production_staff') {
                Response::forbidden('Only production staff can create requisitions');
            }
            
            $productionRunId = getParam('production_run_id');
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
                $codeStmt = $db->prepare("SELECT COUNT(*) as count FROM material_requisitions WHERE requisition_code LIKE ?");
                $codeStmt->execute(["REQ-{$today}-%"]);
                $count = $codeStmt->fetch()['count'] + 1;
                $requisitionCode = "REQ-{$today}-" . str_pad($count, 3, '0', STR_PAD_LEFT);
                
                // Insert requisition
                $stmt = $db->prepare("
                    INSERT INTO material_requisitions (
                        requisition_code, production_run_id, requested_by, 
                        priority, needed_by_date, purpose, total_items, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                
                $stmt->execute([
                    $requisitionCode, 
                    $productionRunId ?: null,
                    $currentUser['user_id'],
                    $priority,
                    $neededBy,
                    $purpose,
                    count($items)
                ]);
                
                $requisitionId = $db->lastInsertId();
                
                // Insert items
                $itemStmt = $db->prepare("
                    INSERT INTO requisition_items (
                        requisition_id, item_type, item_id, item_name, requested_quantity, unit_of_measure, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($items as $item) {
                    // Auto-detect item_type based on item name (free-text input)
                    $itemName = strtolower(trim($item['item_name'] ?? ''));
                    $itemType = 'ingredient'; // Default
                    $itemId = 0;
                    
                    // Check if this is raw milk based on name patterns
                    $rawMilkPatterns = ['raw milk', 'fresh milk', 'carabao milk', 'cow milk', 'goat milk', 'whole milk'];
                    foreach ($rawMilkPatterns as $pattern) {
                        if ($itemName === $pattern || strpos($itemName, $pattern) !== false) {
                            $itemType = 'raw_milk';
                            break;
                        }
                    }
                    
                    // Also check for just 'milk' but exclude processed products
                    if ($itemType !== 'raw_milk' && strpos($itemName, 'milk') !== false) {
                        $excludePatterns = ['powder', 'chocolate', 'flavored', 'pasteurized', 'skim', 'condensed', 'evaporated'];
                        $isExcluded = false;
                        foreach ($excludePatterns as $exclude) {
                            if (strpos($itemName, $exclude) !== false) {
                                $isExcluded = true;
                                break;
                            }
                        }
                        if (!$isExcluded) {
                            $itemType = 'raw_milk';
                        }
                    }
                    
                    $itemStmt->execute([
                        $requisitionId,
                        $itemType,
                        $itemId,
                        $item['item_name'],
                        $item['quantity'],
                        $item['unit'] ?? 'units',
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
            $reqId = getParam('id');
            
            if (!$reqId) {
                Response::validationError(['id' => 'Requisition ID is required']);
            }
            
            // Get current requisition
            $stmt = $db->prepare("SELECT * FROM material_requisitions WHERE id = ?");
            $stmt->execute([$reqId]);
            $requisition = $stmt->fetch();
            
            if (!$requisition) {
                Response::notFound('Requisition not found');
            }
            
            $action = getParam('action');
            
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
                        UPDATE material_requisitions 
                        SET status = 'approved', approved_by = ?, approved_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$currentUser['user_id'], $reqId]);
                    
                    Response::success(['status' => 'approved'], 'Requisition approved');
                    break;
                    
                case 'reject':
                    // GM only
                    if ($currentUser['role'] !== 'general_manager') {
                        Response::forbidden('Only General Manager can reject requisitions');
                    }
                    
                    if ($requisition['status'] !== 'pending') {
                        Response::error('Can only reject pending requisitions', 400);
                    }
                    
                    $rejectionReason = trim(getParam('rejection_reason', ''));
                    
                    $stmt = $db->prepare("
                        UPDATE material_requisitions 
                        SET status = 'rejected', approved_by = ?, approved_at = NOW(),
                            rejection_reason = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$currentUser['user_id'], $rejectionReason, $reqId]);
                    
                    Response::success(['status' => 'rejected'], 'Requisition rejected');
                    break;
                    
                case 'fulfill':
                    // Warehouse only
                    if ($currentUser['role'] !== 'warehouse_raw') {
                        Response::forbidden('Only warehouse staff can fulfill requisitions');
                    }
                    
                    if ($requisition['status'] !== 'approved') {
                        Response::error('Can only fulfill approved requisitions', 400);
                    }
                    
                    $stmt = $db->prepare("
                        UPDATE material_requisitions 
                        SET status = 'fulfilled', fulfilled_by = ?, fulfilled_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$currentUser['user_id'], $reqId]);
                    
                    Response::success(['status' => 'fulfilled'], 'Requisition fulfilled');
                    break;
                    
                case 'partially_fulfill':
                    // Warehouse only
                    if ($currentUser['role'] !== 'warehouse_raw') {
                        Response::forbidden('Only warehouse staff can fulfill requisitions');
                    }
                    
                    if ($requisition['status'] !== 'approved') {
                        Response::error('Can only fulfill approved requisitions', 400);
                    }
                    
                    $fulfillmentNotes = trim(getParam('fulfillment_notes', ''));
                    
                    $stmt = $db->prepare("
                        UPDATE material_requisitions 
                        SET status = 'partial', fulfilled_by = ?, 
                            fulfilled_at = NOW(), fulfillment_notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$currentUser['user_id'], $fulfillmentNotes, $reqId]);
                    
                    Response::success(['status' => 'partial'], 'Requisition partially fulfilled');
                    break;
                    
                case 'cancel':
                    // Only requester can cancel pending requisitions
                    if ($requisition['status'] !== 'pending') {
                        Response::error('Can only cancel pending requisitions', 400);
                    }
                    
                    if ($requisition['requested_by'] != $currentUser['user_id'] && $currentUser['role'] !== 'general_manager') {
                        Response::forbidden('You can only cancel your own requisitions');
                    }
                    
                    $stmt = $db->prepare("UPDATE material_requisitions SET status = 'cancelled' WHERE id = ?");
                    $stmt->execute([$reqId]);
                    
                    Response::success(['status' => 'cancelled'], 'Requisition cancelled');
                    break;
                    
                default:
                    Response::error('Invalid action', 400);
            }
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Requisitions API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
