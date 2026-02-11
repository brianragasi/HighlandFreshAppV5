<?php
/**
 * Highland Fresh System - Machine Repairs API
 * 
 * Manages repair logging and tracking
 * 
 * GET    - List repairs / Get single repair
 * POST   - Report new repair
 * PUT    - Update repair status / Add parts used
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Maintenance Head or GM role
$currentUser = Auth::requireRole(['maintenance_head', 'general_manager']);

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            $id = getParam('id');
            
            if ($id) {
                // Get single repair with parts used
                $stmt = $db->prepare("
                    SELECT mr.*, 
                           m.machine_name, m.machine_code, m.machine_type,
                           u1.first_name as reported_by_first, u1.last_name as reported_by_last,
                           u2.first_name as assigned_to_first, u2.last_name as assigned_to_last,
                           u3.first_name as completed_by_first, u3.last_name as completed_by_last
                    FROM machine_repairs mr
                    JOIN machines m ON mr.machine_id = m.id
                    LEFT JOIN users u1 ON mr.reported_by = u1.id
                    LEFT JOIN users u2 ON mr.assigned_to = u2.id
                    LEFT JOIN users u3 ON mr.completed_by = u3.id
                    WHERE mr.id = ?
                ");
                $stmt->execute([$id]);
                $repair = $stmt->fetch();
                
                if (!$repair) {
                    Response::notFound('Repair not found');
                }
                
                // Get parts used
                $partsStmt = $db->prepare("
                    SELECT rpu.*, mi.item_name, mi.item_code
                    FROM repair_parts_used rpu
                    JOIN mro_items mi ON rpu.mro_item_id = mi.id
                    WHERE rpu.repair_id = ?
                ");
                $partsStmt->execute([$id]);
                $repair['parts_used'] = $partsStmt->fetchAll();
                
                Response::success($repair, 'Repair retrieved successfully');
            }
            
            // List repairs
            $status = getParam('status');
            $machineId = getParam('machine_id');
            $repairType = getParam('repair_type');
            $priority = getParam('priority');
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
            
            if ($machineId) {
                $where .= " AND mr.machine_id = ?";
                $params[] = $machineId;
            }
            
            if ($repairType) {
                $where .= " AND mr.repair_type = ?";
                $params[] = $repairType;
            }
            
            if ($priority) {
                $where .= " AND mr.priority = ?";
                $params[] = $priority;
            }
            
            if ($dateFrom) {
                $where .= " AND DATE(mr.reported_at) >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $where .= " AND DATE(mr.reported_at) <= ?";
                $params[] = $dateTo;
            }
            
            // Count total
            $countStmt = $db->prepare("
                SELECT COUNT(*) as total 
                FROM machine_repairs mr {$where}
            ");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get repairs
            $stmt = $db->prepare("
                SELECT mr.id, mr.repair_code, mr.repair_type, mr.priority, mr.status,
                       mr.issue_description, mr.reported_at, mr.completed_at, mr.total_cost,
                       m.machine_name, m.machine_code,
                       u.first_name as reported_by_first, u.last_name as reported_by_last
                FROM machine_repairs mr
                JOIN machines m ON mr.machine_id = m.id
                LEFT JOIN users u ON mr.reported_by = u.id
                {$where}
                ORDER BY 
                    CASE mr.status 
                        WHEN 'awaiting_parts' THEN 1
                        WHEN 'in_progress' THEN 2
                        WHEN 'diagnosed' THEN 3
                        WHEN 'reported' THEN 4
                        ELSE 5
                    END,
                    CASE mr.priority
                        WHEN 'critical' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'normal' THEN 3
                        ELSE 4
                    END,
                    mr.reported_at DESC
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            
            Response::paginated($stmt->fetchAll(), $total, $page, $limit, 'Repairs retrieved successfully');
            break;
            
        case 'POST':
            // Report new repair
            $machineId = getParam('machine_id');
            $repairType = getParam('repair_type', 'corrective');
            $priority = getParam('priority', 'normal');
            $issueDescription = trim(getParam('issue_description', ''));
            $diagnosis = getParam('diagnosis');
            
            // Validation
            $errors = [];
            if (!$machineId) {
                $errors['machine_id'] = 'Machine is required';
            }
            if (empty($issueDescription)) {
                $errors['issue_description'] = 'Issue description is required';
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Verify machine exists
            $machineStmt = $db->prepare("SELECT id, machine_code FROM machines WHERE id = ?");
            $machineStmt->execute([$machineId]);
            $machine = $machineStmt->fetch();
            
            if (!$machine) {
                Response::notFound('Machine not found');
            }
            
            $db->beginTransaction();
            
            try {
                // Generate repair code
                $today = date('Ymd');
                $codeStmt = $db->prepare("SELECT COUNT(*) as count FROM machine_repairs WHERE repair_code LIKE ?");
                $codeStmt->execute(["RPR-{$today}-%"]);
                $count = $codeStmt->fetch()['count'] + 1;
                $repairCode = "RPR-{$today}-" . str_pad($count, 3, '0', STR_PAD_LEFT);
                
                // Insert repair
                $stmt = $db->prepare("
                    INSERT INTO machine_repairs (
                        repair_code, machine_id, repair_type, priority, 
                        issue_description, diagnosis, reported_by, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'reported')
                ");
                
                $stmt->execute([
                    $repairCode, $machineId, $repairType, $priority,
                    $issueDescription, $diagnosis, $currentUser['user_id']
                ]);
                
                $repairId = $db->lastInsertId();
                
                // Update machine status if needed
                if ($priority === 'critical' || $priority === 'high') {
                    $updateStmt = $db->prepare("
                        UPDATE machines SET status = 'needs_maintenance' WHERE id = ? AND status = 'operational'
                    ");
                    $updateStmt->execute([$machineId]);
                }
                
                $db->commit();
                
                Response::created([
                    'id' => $repairId,
                    'repair_code' => $repairCode
                ], 'Repair reported successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'PUT':
            $id = getParam('id');
            $action = getParam('action');
            
            if (!$id) {
                Response::validationError(['id' => 'Repair ID is required']);
            }
            
            // Get current repair
            $checkStmt = $db->prepare("SELECT * FROM machine_repairs WHERE id = ?");
            $checkStmt->execute([$id]);
            $repair = $checkStmt->fetch();
            
            if (!$repair) {
                Response::notFound('Repair not found');
            }
            
            switch ($action) {
                case 'start':
                    if ($repair['status'] !== 'reported' && $repair['status'] !== 'diagnosed') {
                        Response::error('Can only start reported or diagnosed repairs', 400);
                    }
                    
                    $stmt = $db->prepare("
                        UPDATE machine_repairs 
                        SET status = 'in_progress', 
                            started_at = NOW(),
                            assigned_to = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$currentUser['user_id'], $id]);
                    
                    // Update machine status
                    $db->prepare("UPDATE machines SET status = 'under_repair' WHERE id = ?")->execute([$repair['machine_id']]);
                    
                    Response::success(null, 'Repair started');
                    break;
                    
                case 'diagnose':
                    $diagnosis = getParam('diagnosis');
                    if (empty($diagnosis)) {
                        Response::validationError(['diagnosis' => 'Diagnosis is required']);
                    }
                    
                    $stmt = $db->prepare("
                        UPDATE machine_repairs 
                        SET status = 'diagnosed', diagnosis = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$diagnosis, $id]);
                    
                    Response::success(null, 'Diagnosis recorded');
                    break;
                    
                case 'awaiting_parts':
                    $stmt = $db->prepare("UPDATE machine_repairs SET status = 'awaiting_parts' WHERE id = ?");
                    $stmt->execute([$id]);
                    Response::success(null, 'Status updated to awaiting parts');
                    break;
                    
                case 'complete':
                    $repairActions = getParam('repair_actions');
                    $laborCost = (float) getParam('labor_cost', 0);
                    $downtimeHours = (float) getParam('downtime_hours', 0);
                    
                    // Calculate parts cost
                    $partsCostStmt = $db->prepare("
                        SELECT COALESCE(SUM(quantity_used * unit_cost), 0) as total
                        FROM repair_parts_used WHERE repair_id = ?
                    ");
                    $partsCostStmt->execute([$id]);
                    $partsCost = $partsCostStmt->fetch()['total'];
                    
                    $stmt = $db->prepare("
                        UPDATE machine_repairs 
                        SET status = 'completed',
                            repair_actions = ?,
                            labor_cost = ?,
                            parts_cost = ?,
                            downtime_hours = ?,
                            completed_at = NOW(),
                            completed_by = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$repairActions, $laborCost, $partsCost, $downtimeHours, $currentUser['user_id'], $id]);
                    
                    // Update machine status back to operational
                    $db->prepare("
                        UPDATE machines 
                        SET status = 'operational',
                            last_maintenance_date = CURDATE()
                        WHERE id = ?
                    ")->execute([$repair['machine_id']]);
                    
                    Response::success(null, 'Repair completed');
                    break;
                    
                case 'add_part':
                    $mroItemId = getParam('mro_item_id');
                    $quantityUsed = (float) getParam('quantity_used');
                    
                    if (!$mroItemId || $quantityUsed <= 0) {
                        Response::validationError(['error' => 'MRO item and quantity are required']);
                    }
                    
                    // Get MRO item details and current stock
                    $mroStmt = $db->prepare("SELECT * FROM mro_items WHERE id = ?");
                    $mroStmt->execute([$mroItemId]);
                    $mroItem = $mroStmt->fetch();
                    
                    if (!$mroItem) {
                        Response::notFound('MRO item not found');
                    }
                    
                    if ($mroItem['current_stock'] < $quantityUsed) {
                        Response::error('Insufficient stock. Available: ' . $mroItem['current_stock'], 400);
                    }
                    
                    $db->beginTransaction();
                    try {
                        // Record part usage
                        $insertStmt = $db->prepare("
                            INSERT INTO repair_parts_used (repair_id, mro_item_id, quantity_used, unit_cost)
                            VALUES (?, ?, ?, ?)
                        ");
                        $insertStmt->execute([$id, $mroItemId, $quantityUsed, $mroItem['unit_cost']]);
                        
                        // Deduct from stock
                        $updateStmt = $db->prepare("
                            UPDATE mro_items SET current_stock = current_stock - ? WHERE id = ?
                        ");
                        $updateStmt->execute([$quantityUsed, $mroItemId]);
                        
                        // Update parts cost in repair
                        $costStmt = $db->prepare("
                            UPDATE machine_repairs 
                            SET parts_cost = (SELECT COALESCE(SUM(quantity_used * unit_cost), 0) FROM repair_parts_used WHERE repair_id = ?)
                            WHERE id = ?
                        ");
                        $costStmt->execute([$id, $id]);
                        
                        $db->commit();
                        Response::success(null, 'Part added to repair');
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;
                    
                case 'cancel':
                    if ($repair['status'] === 'completed') {
                        Response::error('Cannot cancel completed repair', 400);
                    }
                    
                    $stmt = $db->prepare("UPDATE machine_repairs SET status = 'cancelled' WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    Response::success(null, 'Repair cancelled');
                    break;
                    
                default:
                    // General update
                    $updates = [];
                    $params = [];
                    
                    $allowedFields = ['priority', 'issue_description', 'diagnosis', 'repair_actions', 'notes'];
                    
                    foreach ($allowedFields as $field) {
                        $value = getParam($field);
                        if ($value !== null) {
                            $updates[] = "{$field} = ?";
                            $params[] = $value;
                        }
                    }
                    
                    if (empty($updates)) {
                        Response::error('No fields to update', 400);
                    }
                    
                    $params[] = $id;
                    $stmt = $db->prepare("UPDATE machine_repairs SET " . implode(', ', $updates) . " WHERE id = ?");
                    $stmt->execute($params);
                    
                    Response::success(null, 'Repair updated successfully');
            }
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Repairs API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
