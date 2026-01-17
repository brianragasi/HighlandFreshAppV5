<?php
/**
 * Highland Fresh System - CCP Logs API
 * 
 * Critical Control Point logging for food safety
 * Based on Production Staff Process Flow:
 * - Chilling (4°C)
 * - Pre-heating (65°C)
 * - Homogenization (1000-1500 psi)
 * - Pasteurization HTST (75°C for 15 seconds)
 * - Cooling (4°C)
 * - Storage (4°C)
 * 
 * GET  - List CCP logs / Get single log
 * POST - Create new CCP log entry
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Production role
$currentUser = Auth::requireRole(['production_staff', 'general_manager', 'qc_officer']);

// CCP Check Type Configurations based on Production Staff documentation
define('CCP_CONFIGS', [
    'chilling' => ['target' => 4, 'tolerance' => 1, 'is_max' => true, 'unit' => '°C'],
    'preheating' => ['target' => 65, 'tolerance' => 2, 'is_max' => false, 'unit' => '°C'],
    'homogenization' => ['target_min' => 1000, 'target_max' => 1500, 'unit' => 'psi'],
    'pasteurization' => ['target' => 75, 'tolerance' => 2, 'is_max' => false, 'hold_time' => 15, 'unit' => '°C'],
    'cooling' => ['target' => 4, 'tolerance' => 1, 'is_max' => true, 'unit' => '°C'],
    'storage' => ['target' => 4, 'tolerance' => 1, 'is_max' => true, 'unit' => '°C'],
    'intermediate' => ['target' => 4, 'tolerance' => 2, 'is_max' => true, 'unit' => '°C']
]);

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            $logId = getParam('id');
            $runId = getParam('run_id');
            
            if ($logId) {
                // Get single log
                $stmt = $db->prepare("
                    SELECT pcl.*, 
                           pr.run_code,
                           mr.product_name,
                           u.first_name, u.last_name
                    FROM production_ccp_logs pcl
                    JOIN production_runs pr ON pcl.run_id = pr.id
                    JOIN master_recipes mr ON pr.recipe_id = mr.id
                    LEFT JOIN users u ON pcl.verified_by = u.id
                    WHERE pcl.id = ?
                ");
                $stmt->execute([$logId]);
                $log = $stmt->fetch();
                
                if (!$log) {
                    Response::notFound('CCP log not found');
                }
                
                Response::success($log, 'CCP log retrieved successfully');
            }
            
            // List logs
            $checkType = getParam('check_type');
            $status = getParam('status');
            $dateFrom = getParam('date_from');
            $dateTo = getParam('date_to');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 50);
            $offset = ($page - 1) * $limit;
            
            $where = "WHERE 1=1";
            $params = [];
            
            if ($runId) {
                $where .= " AND pcl.run_id = ?";
                $params[] = $runId;
            }
            
            if ($checkType) {
                $where .= " AND pcl.check_type = ?";
                $params[] = $checkType;
            }
            
            if ($status) {
                $where .= " AND pcl.status = ?";
                $params[] = $status;
            }
            
            if ($dateFrom) {
                $where .= " AND DATE(pcl.check_datetime) >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $where .= " AND DATE(pcl.check_datetime) <= ?";
                $params[] = $dateTo;
            }
            
            // Get total count
            $countStmt = $db->prepare("
                SELECT COUNT(*) as total 
                FROM production_ccp_logs pcl 
                {$where}
            ");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get logs
            $stmt = $db->prepare("
                SELECT pcl.id, pcl.run_id, pcl.check_type, pcl.temperature, pcl.pressure_psi,
                       pcl.hold_time_mins, pcl.hold_time_secs, pcl.target_temp, pcl.temp_tolerance,
                       pcl.status, pcl.check_datetime, pcl.notes,
                       pr.run_code,
                       mr.product_name,
                       u.first_name, u.last_name
                FROM production_ccp_logs pcl
                JOIN production_runs pr ON pcl.run_id = pr.id
                JOIN master_recipes mr ON pr.recipe_id = mr.id
                LEFT JOIN users u ON pcl.verified_by = u.id
                {$where}
                ORDER BY pcl.check_datetime DESC
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $logs = $stmt->fetchAll();
            
            Response::paginated($logs, $total, $page, $limit, 'CCP logs retrieved successfully');
            break;
            
        case 'POST':
            // Create new CCP log entry
            $runId = getParam('run_id');
            $checkType = getParam('check_type');
            $temperature = getParam('temperature');
            $pressurePsi = getParam('pressure_psi');
            $holdTimeSecs = getParam('hold_time_secs', 0);
            $notes = trim(getParam('notes', ''));
            
            // Valid check types based on process flow
            $validCheckTypes = ['chilling', 'preheating', 'homogenization', 'pasteurization', 'cooling', 'storage', 'intermediate'];
            
            // Validation
            $errors = [];
            if (!$runId) $errors['run_id'] = 'Run ID is required';
            if (!$checkType || !in_array($checkType, $validCheckTypes)) {
                $errors['check_type'] = 'Valid check type is required';
            }
            
            // Homogenization requires pressure, others require temperature
            if ($checkType === 'homogenization') {
                if (!is_numeric($pressurePsi)) $errors['pressure_psi'] = 'Pressure reading is required for homogenization';
            } else {
                if (!is_numeric($temperature)) $errors['temperature'] = 'Temperature reading is required';
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Get production run
            $runStmt = $db->prepare("
                SELECT pr.*, mr.product_name
                FROM production_runs pr
                JOIN master_recipes mr ON pr.recipe_id = mr.id
                WHERE pr.id = ?
            ");
            $runStmt->execute([$runId]);
            $run = $runStmt->fetch();
            
            if (!$run) {
                Response::notFound('Production run not found');
            }
            
            // Get CCP configuration for this check type
            $config = CCP_CONFIGS[$checkType];
            $status = 'pass';
            $targetTemp = null;
            $tolerance = null;
            
            // Determine pass/fail based on check type
            if ($checkType === 'homogenization') {
                // Homogenization: check pressure range (1000-1500 psi)
                $pressure = floatval($pressurePsi);
                if ($pressure < $config['target_min'] || $pressure > $config['target_max']) {
                    $status = 'fail';
                }
                $targetTemp = null; // No temperature for homogenization
            } else {
                // Temperature-based checks
                $targetTemp = $config['target'];
                $tolerance = $config['tolerance'];
                $temp = floatval($temperature);
                
                if ($config['is_max']) {
                    // For cooling/chilling/storage: must be at or below target
                    if ($temp > ($targetTemp + $tolerance)) {
                        $status = 'fail';
                    } elseif ($temp > $targetTemp) {
                        $status = 'warning';
                    }
                } else {
                    // For pasteurization/preheating: must be at or above target
                    if ($temp < ($targetTemp - $tolerance)) {
                        $status = 'fail';
                    } elseif ($temp < $targetTemp) {
                        $status = 'warning';
                    }
                }
                
                // For pasteurization, also check hold time (minimum 15 seconds for HTST)
                if ($checkType === 'pasteurization' && isset($config['hold_time'])) {
                    if ($holdTimeSecs < $config['hold_time']) {
                        $status = 'warning'; // Hold time not met
                    }
                }
            }
            
            // Insert CCP log
            $stmt = $db->prepare("
                INSERT INTO production_ccp_logs (
                    run_id, check_type, temperature, pressure_psi, hold_time_secs,
                    target_temp, temp_tolerance, status, verified_by, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $runId, 
                $checkType, 
                $checkType === 'homogenization' ? null : $temperature,
                $checkType === 'homogenization' ? $pressurePsi : null,
                $holdTimeSecs,
                $targetTemp, 
                $tolerance, 
                $status, 
                $currentUser['user_id'], 
                $notes
            ]);
            
            $logId = $db->lastInsertId();
            
            // Build response message
            $reading = $checkType === 'homogenization' 
                ? "{$pressurePsi} psi" 
                : "{$temperature}°C";
            
            $messages = [
                'pass' => "CCP check PASSED - {$checkType} reading ({$reading}) within acceptable range",
                'warning' => "CCP check WARNING - {$checkType} reading ({$reading}) marginal",
                'fail' => "CCP check FAILED - {$checkType} reading ({$reading}) out of range!"
            ];
            
            // Return response with pass/fail status
            Response::created([
                'id' => $logId,
                'status' => $status,
                'check_type' => $checkType,
                'temperature' => $temperature,
                'pressure_psi' => $pressurePsi,
                'target_temp' => $targetTemp,
                'hold_time_secs' => $holdTimeSecs
            ], $messages[$status]);
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("CCP Logs API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
