<?php
/**
 * Highland Fresh - Pasteurization API
 * 
 * Handles pasteurization runs: converting raw milk to pasteurized milk
 * 
 * Endpoints:
 *   GET    ?action=available_raw_milk  - Get available raw milk for pasteurization
 *   GET    ?action=runs               - List pasteurization runs
 *   GET    ?id=X                      - Get single run details
 *   POST   action=create              - Create new pasteurization run
 *   PUT    action=complete            - Complete pasteurization run
 * 
 * @version 1.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Production role
$currentUser = Auth::requireRole(['production_staff', 'general_manager']);

try {
    $db = Database::getInstance()->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            $action = getParam('action');
            $runId = getParam('id');
            
            // Get available raw milk for pasteurization
            if ($action === 'available_raw_milk') {
                // Get milk issued to production via requisitions
                $issuedMilkStmt = $db->prepare("
                    SELECT COALESCE(SUM(ri.issued_quantity), 0) as total_issued
                    FROM requisition_items ri
                    JOIN material_requisitions ir ON ri.requisition_id = ir.id
                    WHERE ri.item_type = 'raw_milk'
                      AND ri.issued_quantity > 0
                      AND ir.department = 'production'
                ");
                $issuedMilkStmt->execute();
                $issuedStats = $issuedMilkStmt->fetch();
                
                // Get milk already used in production runs (raw milk only)
                $usedMilkStmt = $db->prepare("
                    SELECT COALESCE(SUM(milk_liters_used), 0) as total_used
                    FROM production_runs
                    WHERE status IN ('in_progress', 'completed', 'pasteurization', 'processing', 'cooling', 'packaging')
                      AND (milk_source_type IS NULL OR milk_source_type = 'raw')
                ");
                $usedMilkStmt->execute();
                $usedStats = $usedMilkStmt->fetch();
                
                // Get milk used in pasteurization runs
                $pastUsedStmt = $db->prepare("
                    SELECT COALESCE(SUM(input_milk_liters), 0) as pasteurization_used
                    FROM pasteurization_runs
                    WHERE status IN ('in_progress', 'completed')
                ");
                $pastUsedStmt->execute();
                $pastStats = $pastUsedStmt->fetch();
                
                $totalIssued = (float) ($issuedStats['total_issued'] ?? 0);
                $totalUsed = (float) ($usedStats['total_used'] ?? 0);
                $pasteurizationUsed = (float) ($pastStats['pasteurization_used'] ?? 0);
                $availableLiters = max(0, $totalIssued - $totalUsed - $pasteurizationUsed);
                
                Response::success([
                    'available_liters' => $availableLiters,
                    'total_issued' => $totalIssued,
                    'used_in_production' => $totalUsed,
                    'used_in_pasteurization' => $pasteurizationUsed
                ], 'Available raw milk retrieved');
            }
            
            // List pasteurization runs
            if ($action === 'runs' || !$runId) {
                $status = getParam('status');
                $limit = (int) getParam('limit', 20);
                $page = (int) getParam('page', 1);
                $offset = ($page - 1) * $limit;
                
                $where = "1=1";
                $params = [];
                
                if ($status) {
                    $where .= " AND pr.status = ?";
                    $params[] = $status;
                }
                
                $stmt = $db->prepare("
                    SELECT 
                        pr.*,
                        CONCAT(u.first_name, ' ', u.last_name) as performed_by_name
                    FROM pasteurization_runs pr
                    LEFT JOIN users u ON pr.performed_by = u.id
                    WHERE {$where}
                    ORDER BY pr.created_at DESC
                    LIMIT {$limit} OFFSET {$offset}
                ");
                $stmt->execute($params);
                $runs = $stmt->fetchAll();
                
                // Get total count
                $countStmt = $db->prepare("SELECT COUNT(*) FROM pasteurization_runs pr WHERE {$where}");
                $countStmt->execute($params);
                $total = $countStmt->fetchColumn();
                
                Response::success([
                    'runs' => $runs,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => (int) $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]);
            }
            
            // Get single run
            if ($runId) {
                $stmt = $db->prepare("
                    SELECT 
                        pr.*,
                        CONCAT(u.first_name, ' ', u.last_name) as performed_by_name
                    FROM pasteurization_runs pr
                    LEFT JOIN users u ON pr.performed_by = u.id
                    WHERE pr.id = ?
                ");
                $stmt->execute([$runId]);
                $run = $stmt->fetch();
                
                if (!$run) {
                    Response::notFound('Pasteurization run not found');
                }
                
                Response::success($run);
            }
            break;
            
        case 'POST':
            // Create new pasteurization run
            $inputLiters = (float) getParam('input_liters');
            $temperature = (float) getParam('temperature', 75.0);
            $durationMins = (int) getParam('duration_mins', 15);
            $notes = trim(getParam('notes', ''));
            
            // Validation
            $errors = [];
            if ($inputLiters <= 0) $errors['input_liters'] = 'Input liters must be greater than 0';
            if ($temperature < 60 || $temperature > 100) $errors['temperature'] = 'Temperature must be between 60-100°C';
            if ($durationMins < 1 || $durationMins > 120) $errors['duration_mins'] = 'Duration must be between 1-120 minutes';
            
            // Check available raw milk
            $issuedMilkStmt = $db->prepare("
                SELECT COALESCE(SUM(ri.issued_quantity), 0) as total_issued
                FROM requisition_items ri
                JOIN material_requisitions ir ON ri.requisition_id = ir.id
                WHERE ri.item_type = 'raw_milk'
                  AND ri.issued_quantity > 0
                  AND ir.department = 'production'
            ");
            $issuedMilkStmt->execute();
            $issuedStats = $issuedMilkStmt->fetch();
            
            $usedMilkStmt = $db->prepare("
                SELECT COALESCE(SUM(milk_liters_used), 0) as total_used
                FROM production_runs
                WHERE status IN ('in_progress', 'completed', 'pasteurization', 'processing', 'cooling', 'packaging')
                  AND (milk_source_type IS NULL OR milk_source_type = 'raw')
            ");
            $usedMilkStmt->execute();
            $usedStats = $usedMilkStmt->fetch();
            
            $pastUsedStmt = $db->prepare("
                SELECT COALESCE(SUM(input_milk_liters), 0) as pasteurization_used
                FROM pasteurization_runs
                WHERE status IN ('in_progress', 'completed')
            ");
            $pastUsedStmt->execute();
            $pastStats = $pastUsedStmt->fetch();
            
            $availableLiters = max(0, ($issuedStats['total_issued'] ?? 0) - ($usedStats['total_used'] ?? 0) - ($pastStats['pasteurization_used'] ?? 0));
            
            if ($inputLiters > $availableLiters) {
                $errors['input_liters'] = "Not enough raw milk available. Required: {$inputLiters}L, Available: {$availableLiters}L";
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Generate run code
            $today = date('Ymd');
            $codeStmt = $db->prepare("SELECT COUNT(*) as count FROM pasteurization_runs WHERE run_code LIKE ?");
            $codeStmt->execute(["PAST-{$today}-%"]);
            $count = $codeStmt->fetch()['count'] + 1;
            $runCode = "PAST-{$today}-" . str_pad($count, 3, '0', STR_PAD_LEFT);
            
            // Create pasteurization run
            $stmt = $db->prepare("
                INSERT INTO pasteurization_runs (
                    run_code, input_milk_liters, output_milk_liters, 
                    temperature, duration_mins, 
                    started_at, performed_by, status, notes
                ) VALUES (?, ?, 0, ?, ?, NOW(), ?, 'in_progress', ?)
            ");
            $stmt->execute([
                $runCode, $inputLiters, $temperature, $durationMins, $currentUser['user_id'], $notes
            ]);
            
            $runId = $db->lastInsertId();
            
            Response::created([
                'id' => $runId,
                'run_code' => $runCode,
                'status' => 'in_progress',
                'input_liters' => $inputLiters,
                'temperature' => $temperature,
                'duration_mins' => $durationMins
            ], 'Pasteurization run started');
            break;
            
        case 'PUT':
            $runId = getParam('id');
            $action = getParam('action');
            
            if (!$runId) {
                Response::validationError(['id' => 'Run ID is required']);
            }
            
            // Get current run
            $stmt = $db->prepare("SELECT * FROM pasteurization_runs WHERE id = ?");
            $stmt->execute([$runId]);
            $run = $stmt->fetch();
            
            if (!$run) {
                Response::notFound('Pasteurization run not found');
            }
            
            if ($action === 'complete') {
                if ($run['status'] !== 'in_progress') {
                    Response::error('Can only complete runs that are in progress');
                }
                
                $outputLiters = (float) getParam('output_liters');
                $expiryDays = (int) getParam('expiry_days', 2); // Pasteurized milk typically expires in 2-3 days
                
                if ($outputLiters <= 0) {
                    Response::validationError(['output_liters' => 'Output liters must be greater than 0']);
                }
                
                // Calculate shrinkage
                $shrinkagePercent = round((1 - ($outputLiters / $run['input_milk_liters'])) * 100, 2);
                
                $db->beginTransaction();
                
                try {
                    // Update pasteurization run
                    $updateStmt = $db->prepare("
                        UPDATE pasteurization_runs 
                        SET status = 'completed',
                            output_milk_liters = ?,
                            shrinkage_percent = ?,
                            completed_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$outputLiters, $shrinkagePercent, $runId]);
                    
                    // Generate batch code for pasteurized milk
                    $batchCode = "PAST-" . date('Ymd') . "-" . str_pad($runId, 3, '0', STR_PAD_LEFT);
                    $expiryDate = date('Y-m-d', strtotime("+{$expiryDays} days"));
                    
                    // Create pasteurized milk inventory record
                    $insertStmt = $db->prepare("
                        INSERT INTO pasteurized_milk_inventory (
                            batch_code, source_type, source_run_id,
                            quantity_liters, remaining_liters,
                            pasteurization_temp, pasteurization_duration_mins,
                            pasteurized_at, pasteurized_by, expiry_date,
                            status, notes
                        ) VALUES (?, 'pasteurization_run', ?, ?, ?, ?, ?, NOW(), ?, ?, 'available', ?)
                    ");
                    $insertStmt->execute([
                        $batchCode,
                        $runId,
                        $outputLiters,
                        $outputLiters,
                        $run['temperature'],
                        $run['duration_mins'],
                        $run['performed_by'],
                        $expiryDate,
                        "From pasteurization run {$run['run_code']}"
                    ]);
                    
                    $db->commit();
                    
                    Response::success([
                        'id' => $runId,
                        'status' => 'completed',
                        'output_liters' => $outputLiters,
                        'shrinkage_percent' => $shrinkagePercent,
                        'batch_code' => $batchCode,
                        'expiry_date' => $expiryDate
                    ], 'Pasteurization completed. Pasteurized milk added to inventory.');
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }
            }
            
            if ($action === 'cancel') {
                if ($run['status'] !== 'in_progress') {
                    Response::error('Can only cancel runs that are in progress');
                }
                
                $stmt = $db->prepare("UPDATE pasteurization_runs SET status = 'failed' WHERE id = ?");
                $stmt->execute([$runId]);
                
                Response::success(['id' => $runId, 'status' => 'failed'], 'Pasteurization run cancelled');
            }
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Pasteurization API Error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage());
}
