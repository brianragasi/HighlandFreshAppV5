<?php
/**
 * Highland Fresh System - Machines/Equipment API
 * 
 * Manages plant equipment registry
 * 
 * GET    - List machines / Get single machine
 * POST   - Add new machine (GM only)
 * PUT    - Update machine status / details
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
                // Get single machine with repair history
                $stmt = $db->prepare("
                    SELECT m.*
                    FROM machines m
                    WHERE m.id = ?
                ");
                $stmt->execute([$id]);
                $machine = $stmt->fetch();
                
                if (!$machine) {
                    Response::notFound('Machine not found');
                }
                
                // Get recent repairs
                $repairStmt = $db->prepare("
                    SELECT mr.id, mr.repair_code, mr.repair_type, mr.status, mr.issue_description,
                           mr.completed_at, mr.total_cost
                    FROM machine_repairs mr
                    WHERE mr.machine_id = ?
                    ORDER BY mr.created_at DESC
                    LIMIT 10
                ");
                $repairStmt->execute([$id]);
                $machine['recent_repairs'] = $repairStmt->fetchAll();
                
                // Get maintenance schedule
                $scheduleStmt = $db->prepare("
                    SELECT * FROM maintenance_schedules
                    WHERE machine_id = ? AND is_active = 1
                ");
                $scheduleStmt->execute([$id]);
                $machine['schedules'] = $scheduleStmt->fetchAll();
                
                Response::success($machine, 'Machine retrieved successfully');
            }
            
            // List machines
            $status = getParam('status');
            $type = getParam('type');
            $search = getParam('search');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $where = "WHERE m.is_active = 1";
            $params = [];
            
            if ($status) {
                $where .= " AND m.status = ?";
                $params[] = $status;
            }
            
            if ($type) {
                $where .= " AND m.machine_type = ?";
                $params[] = $type;
            }
            
            if ($search) {
                $where .= " AND (m.machine_name LIKE ? OR m.machine_code LIKE ?)";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
            }
            
            // Count total
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM machines m {$where}");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get machines
            $stmt = $db->prepare("
                SELECT m.*,
                       (SELECT COUNT(*) FROM machine_repairs WHERE machine_id = m.id AND status NOT IN ('completed', 'cancelled')) as active_repairs,
                       DATEDIFF(m.next_maintenance_due, CURDATE()) as days_until_maintenance
                FROM machines m
                {$where}
                ORDER BY 
                    CASE m.status 
                        WHEN 'under_repair' THEN 1
                        WHEN 'needs_maintenance' THEN 2
                        WHEN 'offline' THEN 3
                        WHEN 'operational' THEN 4
                        ELSE 5
                    END,
                    m.next_maintenance_due ASC
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            
            Response::paginated($stmt->fetchAll(), $total, $page, $limit, 'Machines retrieved successfully');
            break;
            
        case 'POST':
            // Add new machine - GM only
            if ($currentUser['role'] !== 'general_manager') {
                Response::forbidden('Only General Manager can add machines');
            }
            
            $machineName = trim(getParam('machine_name', ''));
            $machineType = getParam('machine_type', 'other');
            $location = getParam('location');
            $manufacturer = getParam('manufacturer');
            $modelNumber = getParam('model_number');
            $serialNumber = getParam('serial_number');
            $purchaseDate = getParam('purchase_date');
            $warrantyExpiry = getParam('warranty_expiry');
            $maintenanceInterval = (int) getParam('maintenance_interval_days', 30);
            
            // Validation
            if (empty($machineName)) {
                Response::validationError(['machine_name' => 'Machine name is required']);
            }
            
            // Generate machine code
            $typePrefix = strtoupper(substr($machineType, 0, 4));
            $codeStmt = $db->prepare("SELECT COUNT(*) as count FROM machines WHERE machine_type = ?");
            $codeStmt->execute([$machineType]);
            $count = $codeStmt->fetch()['count'] + 1;
            $machineCode = "MCH-{$typePrefix}-" . str_pad($count, 3, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("
                INSERT INTO machines (
                    machine_code, machine_name, machine_type, location, manufacturer,
                    model_number, serial_number, purchase_date, warranty_expiry,
                    maintenance_interval_days, next_maintenance_due
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL ? DAY))
            ");
            
            $stmt->execute([
                $machineCode, $machineName, $machineType, $location, $manufacturer,
                $modelNumber, $serialNumber, $purchaseDate, $warrantyExpiry,
                $maintenanceInterval, $maintenanceInterval
            ]);
            
            Response::created([
                'id' => $db->lastInsertId(),
                'machine_code' => $machineCode
            ], 'Machine added successfully');
            break;
            
        case 'PUT':
            $id = getParam('id');
            $action = getParam('action');
            
            if (!$id) {
                Response::validationError(['id' => 'Machine ID is required']);
            }
            
            // Verify machine exists
            $checkStmt = $db->prepare("SELECT * FROM machines WHERE id = ?");
            $checkStmt->execute([$id]);
            $machine = $checkStmt->fetch();
            
            if (!$machine) {
                Response::notFound('Machine not found');
            }
            
            switch ($action) {
                case 'update_status':
                    $newStatus = getParam('status');
                    $validStatuses = ['operational', 'needs_maintenance', 'under_repair', 'offline', 'decommissioned'];
                    
                    if (!in_array($newStatus, $validStatuses)) {
                        Response::validationError(['status' => 'Invalid status']);
                    }
                    
                    $stmt = $db->prepare("UPDATE machines SET status = ? WHERE id = ?");
                    $stmt->execute([$newStatus, $id]);
                    
                    Response::success(['status' => $newStatus], 'Machine status updated');
                    break;
                    
                case 'record_maintenance':
                    // Record that maintenance was performed
                    $stmt = $db->prepare("
                        UPDATE machines 
                        SET last_maintenance_date = CURDATE(),
                            next_maintenance_due = DATE_ADD(CURDATE(), INTERVAL maintenance_interval_days DAY),
                            status = 'operational'
                        WHERE id = ?
                    ");
                    $stmt->execute([$id]);
                    
                    Response::success(null, 'Maintenance recorded successfully');
                    break;
                    
                default:
                    // General update
                    $updates = [];
                    $params = [];
                    
                    $allowedFields = [
                        'machine_name', 'location', 'manufacturer', 'model_number',
                        'serial_number', 'warranty_expiry', 'maintenance_interval_days', 'notes'
                    ];
                    
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
                    $stmt = $db->prepare("UPDATE machines SET " . implode(', ', $updates) . " WHERE id = ?");
                    $stmt->execute($params);
                    
                    Response::success(null, 'Machine updated successfully');
            }
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Machines API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
