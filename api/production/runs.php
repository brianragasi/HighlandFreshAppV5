<?php
/**
 * Highland Fresh System - Production Runs API
 * 
 * GET  - List production runs / Get single run / Get available milk
 * POST - Create new production run
 * PUT  - Update run status / Complete run
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Production role
$currentUser = Auth::requireRole(['production_staff', 'general_manager', 'qc_officer']);

try {
    $db = Database::getInstance()->getConnection();
    
    // Ensure output_breakdown column exists for multi-unit output tracking
    try {
        $db->exec("
            ALTER TABLE production_runs 
            ADD COLUMN IF NOT EXISTS output_breakdown JSON DEFAULT NULL 
            COMMENT 'Stores unit breakdown: total_pieces, secondary_count, remaining_primary, etc.'
        ");
    } catch (PDOException $e) {
        // Column might already exist or MySQL version doesn't support IF NOT EXISTS
        // Try alternative approach
        $checkCol = $db->query("SHOW COLUMNS FROM production_runs LIKE 'output_breakdown'");
        if ($checkCol->rowCount() === 0) {
            try {
                $db->exec("ALTER TABLE production_runs ADD COLUMN output_breakdown JSON DEFAULT NULL");
            } catch (PDOException $e2) {
                // Ignore if already exists
            }
        }
    }
    
    switch ($requestMethod) {
        case 'GET':
            $runId = getParam('id');
            $action = getParam('action');
            
            // Get available QC-approved milk for production
            if ($action === 'available_milk') {
                // Ensure the milk usage table exists
                $db->exec("
                    CREATE TABLE IF NOT EXISTS production_run_milk_usage (
                        id INT(11) NOT NULL AUTO_INCREMENT,
                        run_id INT(11) NOT NULL,
                        delivery_id INT(11) NOT NULL,
                        milk_liters_allocated DECIMAL(10,2) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        INDEX idx_run (run_id),
                        INDEX idx_delivery (delivery_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                
                $availableMilkStmt = $db->prepare("
                    SELECT 
                        md.id,
                        md.delivery_code,
                        md.volume_liters,
                        md.accepted_liters,
                        md.delivery_date,
                        md.delivery_time,
                        qmt.test_code,
                        qmt.fat_percentage,
                        qmt.titratable_acidity,
                        qmt.specific_gravity,
                        f.farmer_code,
                        CONCAT(f.first_name, ' ', f.last_name) as farmer_name,
                        -- Use volume_liters if accepted_liters is 0 or NULL
                        COALESCE(
                            CASE 
                                WHEN md.accepted_liters > 0 THEN md.accepted_liters 
                                ELSE md.volume_liters 
                            END - (
                                SELECT COALESCE(SUM(pru.milk_liters_allocated), 0)
                                FROM production_run_milk_usage pru
                                WHERE pru.delivery_id = md.id
                            ), 
                            CASE 
                                WHEN md.accepted_liters > 0 THEN md.accepted_liters 
                                ELSE md.volume_liters 
                            END
                        ) as remaining_liters
                    FROM milk_deliveries md
                    JOIN qc_milk_tests qmt ON qmt.delivery_id = md.id
                    LEFT JOIN farmers f ON md.farmer_id = f.id
                    WHERE md.status = 'accepted'
                      AND DATE(md.delivery_date) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
                    HAVING remaining_liters > 0
                    ORDER BY md.delivery_date ASC, md.delivery_time ASC
                ");
                $availableMilkStmt->execute();
                $availableMilk = $availableMilkStmt->fetchAll();
                
                $totalAvailable = array_sum(array_column($availableMilk, 'remaining_liters'));
                
                Response::success([
                    'milk_sources' => $availableMilk,
                    'total_available_liters' => $totalAvailable,
                    'freshness_window' => '2 days',
                    'message' => $totalAvailable > 0 
                        ? "You have {$totalAvailable}L of QC-approved milk available for production"
                        : "No QC-approved milk available. Please wait for deliveries to be graded."
                ], 'Available milk retrieved successfully');
            }
            
            if ($runId) {
                // Get single run with details
                $stmt = $db->prepare("
                    SELECT pr.*, 
                           mr.recipe_code, mr.product_name, mr.product_type, mr.variant,
                           mr.base_milk_liters, mr.expected_yield, mr.yield_unit,
                           mr.pasteurization_temp, mr.pasteurization_time_mins, mr.cooling_temp,
                           u1.first_name as started_by_first, u1.last_name as started_by_last,
                           u2.first_name as completed_by_first, u2.last_name as completed_by_last
                    FROM production_runs pr
                    JOIN master_recipes mr ON pr.recipe_id = mr.id
                    LEFT JOIN users u1 ON pr.started_by = u1.id
                    LEFT JOIN users u2 ON pr.completed_by = u2.id
                    WHERE pr.id = ?
                ");
                $stmt->execute([$runId]);
                $run = $stmt->fetch();
                
                if (!$run) {
                    Response::notFound('Production run not found');
                }
                
                // Get CCP logs for this run
                $ccpStmt = $db->prepare("
                    SELECT pcl.*, u.first_name, u.last_name
                    FROM production_ccp_logs pcl
                    LEFT JOIN users u ON pcl.verified_by = u.id
                    WHERE pcl.run_id = ?
                    ORDER BY pcl.check_datetime
                ");
                $ccpStmt->execute([$runId]);
                $run['ccp_logs'] = $ccpStmt->fetchAll();
                
                // Get ingredient consumption
                $consStmt = $db->prepare("
                    SELECT * FROM ingredient_consumption WHERE run_id = ?
                ");
                $consStmt->execute([$runId]);
                $run['consumption'] = $consStmt->fetchAll();
                
                // Get byproducts
                $byStmt = $db->prepare("
                    SELECT * FROM production_byproducts WHERE run_id = ?
                ");
                $byStmt->execute([$runId]);
                $run['byproducts'] = $byStmt->fetchAll();
                
                Response::success($run, 'Production run retrieved successfully');
            }
            
            // List runs
            $status = getParam('status');
            $recipeId = getParam('recipe_id');
            $productType = getParam('product_type');
            $dateFrom = getParam('date_from');
            $dateTo = getParam('date_to');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $where = "WHERE 1=1";
            $params = [];
            
            if ($status) {
                $where .= " AND pr.status = ?";
                $params[] = $status;
            }
            
            if ($recipeId) {
                $where .= " AND pr.recipe_id = ?";
                $params[] = $recipeId;
            }
            
            if ($productType) {
                $where .= " AND mr.product_type = ?";
                $params[] = $productType;
            }
            
            if ($dateFrom) {
                $where .= " AND DATE(pr.created_at) >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $where .= " AND DATE(pr.created_at) <= ?";
                $params[] = $dateTo;
            }
            
            // Get total count
            $countStmt = $db->prepare("
                SELECT COUNT(*) as total 
                FROM production_runs pr 
                JOIN master_recipes mr ON pr.recipe_id = mr.id
                {$where}
            ");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get runs
            $stmt = $db->prepare("
                SELECT pr.id, pr.run_code, pr.recipe_id, pr.status, pr.planned_quantity,
                       pr.actual_quantity, pr.output_breakdown, pr.milk_liters_used, pr.start_datetime, pr.end_datetime,
                       pr.yield_variance, pr.created_at,
                       mr.recipe_code, mr.product_name, mr.product_type, mr.variant, mr.yield_unit
                FROM production_runs pr
                JOIN master_recipes mr ON pr.recipe_id = mr.id
                {$where}
                ORDER BY pr.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $runs = $stmt->fetchAll();
            
            Response::paginated($runs, $total, $page, $limit, 'Production runs retrieved successfully');
            break;
            
        case 'POST':
            // Create new production run
            $recipeId = getParam('recipe_id');
            $plannedQuantity = (int) getParam('planned_quantity', 0);
            $milkLitersUsed = getParam('milk_liters_used');
            $milkSourceIds = getParam('milk_source_ids'); // Array of milk delivery IDs to use
            $notes = trim(getParam('notes', ''));
            
            // Validation
            $errors = [];
            if (!$recipeId) $errors['recipe_id'] = 'Recipe is required';
            if ($plannedQuantity <= 0) $errors['planned_quantity'] = 'Planned quantity must be greater than 0';
            
            // Verify recipe exists
            $recipeStmt = $db->prepare("SELECT * FROM master_recipes WHERE id = ? AND is_active = 1");
            $recipeStmt->execute([$recipeId]);
            $recipe = $recipeStmt->fetch();
            
            if (!$recipe) {
                $errors['recipe_id'] = 'Recipe not found or inactive';
            }
            
            // Calculate required milk liters
            $requiredMilkLiters = $milkLitersUsed ?? $recipe['base_milk_liters'];
            
            // ====================================================
            // CRITICAL: Validate QC-approved milk is available
            // ====================================================
            
            // Check available QC-approved milk from recent deliveries (within 2 days for freshness)
            $availableMilkStmt = $db->prepare("
                SELECT 
                    md.id,
                    md.delivery_code,
                    md.volume_liters,
                    md.accepted_liters,
                    md.delivery_date,
                    qmt.test_code,
                    qmt.fat_percentage,
                    qmt.titratable_acidity,
                    f.first_name as farmer_first,
                    f.last_name as farmer_last,
                    COALESCE(
                        md.accepted_liters - (
                            SELECT COALESCE(SUM(pru.milk_liters_allocated), 0)
                            FROM production_run_milk_usage pru
                            WHERE pru.delivery_id = md.id
                        ), md.accepted_liters
                    ) as remaining_liters
                FROM milk_deliveries md
                JOIN qc_milk_tests qmt ON qmt.delivery_id = md.id
                LEFT JOIN farmers f ON md.farmer_id = f.id
                WHERE md.status = 'accepted'
                  AND DATE(md.delivery_date) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
                HAVING remaining_liters > 0
                ORDER BY md.delivery_date ASC
            ");
            $availableMilkStmt->execute();
            $availableMilk = $availableMilkStmt->fetchAll();
            
            // Calculate total available milk
            $totalAvailableLiters = array_sum(array_column($availableMilk, 'remaining_liters'));
            
            if ($totalAvailableLiters <= 0) {
                $errors['milk_source'] = 'No QC-approved milk available. Please wait for milk deliveries to be graded and approved by QC.';
            } else if ($totalAvailableLiters < $requiredMilkLiters) {
                $errors['milk_source'] = "Not enough milk available. Required: {$requiredMilkLiters}L, Available: {$totalAvailableLiters}L";
            }
            
            // If specific milk sources provided, validate them
            $selectedMilkSources = [];
            if (!empty($milkSourceIds) && is_array($milkSourceIds)) {
                $allocatedTotal = 0;
                foreach ($milkSourceIds as $sourceId) {
                    $source = array_filter($availableMilk, fn($m) => $m['id'] == $sourceId);
                    if (empty($source)) {
                        $errors['milk_source'] = "Invalid milk source ID: {$sourceId}";
                        break;
                    }
                    $source = array_values($source)[0];
                    $selectedMilkSources[] = $source;
                    $allocatedTotal += $source['remaining_liters'];
                }
                
                if (empty($errors['milk_source']) && $allocatedTotal < $requiredMilkLiters) {
                    $errors['milk_source'] = "Selected milk sources have only {$allocatedTotal}L. Required: {$requiredMilkLiters}L";
                }
            } else {
                // Auto-allocate from available milk (FIFO)
                $remainingToAllocate = $requiredMilkLiters;
                foreach ($availableMilk as $milk) {
                    if ($remainingToAllocate <= 0) break;
                    $selectedMilkSources[] = $milk;
                    $remainingToAllocate -= $milk['remaining_liters'];
                }
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Generate run code
            $today = date('Ymd');
            $codeStmt = $db->prepare("
                SELECT COUNT(*) as count FROM production_runs 
                WHERE run_code LIKE ?
            ");
            $codeStmt->execute(["PRD-{$today}-%"]);
            $count = $codeStmt->fetch()['count'] + 1;
            $runCode = "PRD-{$today}-" . str_pad($count, 3, '0', STR_PAD_LEFT);
            
            $db->beginTransaction();
            
            try {
                // Insert run
                $stmt = $db->prepare("
                    INSERT INTO production_runs (
                        run_code, recipe_id, planned_quantity, milk_liters_used,
                        milk_batch_source, status, notes
                    ) VALUES (?, ?, ?, ?, ?, 'planned', ?)
                ");
                
                $milkSourceInfo = array_map(function($s) {
                    return [
                        'delivery_id' => $s['id'],
                        'delivery_code' => $s['delivery_code'],
                        'test_code' => $s['test_code'],
                        'liters_available' => $s['remaining_liters']
                    ];
                }, $selectedMilkSources);
                
                $stmt->execute([
                    $runCode, $recipeId, $plannedQuantity, 
                    $requiredMilkLiters,
                    json_encode($milkSourceInfo),
                    $notes
                ]);
                
                $runId = $db->lastInsertId();
                
                // Record milk allocation (reserve the milk for this run)
                // Create tracking table if it doesn't exist
                $db->exec("
                    CREATE TABLE IF NOT EXISTS production_run_milk_usage (
                        id INT(11) NOT NULL AUTO_INCREMENT,
                        run_id INT(11) NOT NULL,
                        delivery_id INT(11) NOT NULL,
                        milk_liters_allocated DECIMAL(10,2) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        INDEX idx_run (run_id),
                        INDEX idx_delivery (delivery_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                
                // Allocate milk from each source
                $remainingToAllocate = $requiredMilkLiters;
                foreach ($selectedMilkSources as $source) {
                    if ($remainingToAllocate <= 0) break;
                    
                    $allocateAmount = min($source['remaining_liters'], $remainingToAllocate);
                    
                    $allocStmt = $db->prepare("
                        INSERT INTO production_run_milk_usage (run_id, delivery_id, milk_liters_allocated)
                        VALUES (?, ?, ?)
                    ");
                    $allocStmt->execute([$runId, $source['id'], $allocateAmount]);
                    
                    $remainingToAllocate -= $allocateAmount;
                }
                
                $db->commit();
                
                Response::created([
                    'id' => $runId,
                    'run_code' => $runCode,
                    'status' => 'planned',
                    'milk_sources' => $milkSourceInfo,
                    'milk_liters_allocated' => $requiredMilkLiters
                ], 'Production run created successfully with QC-approved milk allocated');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'PUT':
            $runId = getParam('id');
            
            if (!$runId) {
                Response::validationError(['id' => 'Run ID is required']);
            }
            
            // Get current run
            $stmt = $db->prepare("SELECT * FROM production_runs WHERE id = ?");
            $stmt->execute([$runId]);
            $run = $stmt->fetch();
            
            if (!$run) {
                Response::notFound('Production run not found');
            }
            
            $action = getParam('action', 'update');
            
            switch ($action) {
                case 'start':
                    // Start the production run
                    if ($run['status'] !== 'planned') {
                        Response::error('Can only start a planned run', 400);
                    }
                    
                    $stmt = $db->prepare("
                        UPDATE production_runs 
                        SET status = 'in_progress', start_datetime = NOW(), started_by = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$currentUser['user_id'], $runId]);
                    
                    Response::success(['status' => 'in_progress'], 'Production run started');
                    break;
                    
                case 'update_status':
                    // Update status during production
                    $newStatus = getParam('status');
                    $validStatuses = ['pasteurization', 'processing', 'cooling', 'packaging'];
                    
                    if (!in_array($newStatus, $validStatuses)) {
                        Response::error('Invalid status', 400);
                    }
                    
                    $stmt = $db->prepare("UPDATE production_runs SET status = ? WHERE id = ?");
                    $stmt->execute([$newStatus, $runId]);
                    
                    Response::success(['status' => $newStatus], 'Status updated');
                    break;
                    
                case 'complete':
                    // Complete the production run
                    if (!in_array($run['status'], ['in_progress', 'pasteurization', 'processing', 'cooling', 'packaging'])) {
                        Response::error('Run is not in progress', 400);
                    }
                    
                    $actualQuantity = (int) getParam('actual_quantity', 0);
                    $outputUnit = getParam('output_unit', 'pieces'); // pieces, boxes, crates, cases
                    $varianceReason = trim(getParam('variance_reason', ''));
                    
                    if ($actualQuantity <= 0) {
                        Response::validationError(['actual_quantity' => 'Actual quantity is required']);
                    }
                    
                    // =====================================================
                    // MULTI-UNIT OUTPUT CONVERSION
                    // Product Conversions:
                    // - Bottled Milk: 1 Crate = 24 Bottles
                    // - Milk Bars: 1 Box = 50 Pieces
                    // - Cheese: 1 Case = 12 Blocks
                    // - Butter: 1 Case = 20 Packs
                    // =====================================================
                    
                    // Get recipe info for product type
                    $recipeStmt = $db->prepare("SELECT product_type FROM master_recipes WHERE id = ?");
                    $recipeStmt->execute([$run['recipe_id']]);
                    $recipeInfo = $recipeStmt->fetch();
                    $productType = $recipeInfo['product_type'] ?? 'bottled_milk';
                    
                    // Define unit conversions per product type
                    $unitConversions = [
                        'bottled_milk' => ['primary' => 'bottles', 'secondary' => 'crates', 'conversion' => 24],
                        'milk_bar' => ['primary' => 'pieces', 'secondary' => 'boxes', 'conversion' => 50],
                        'cheese' => ['primary' => 'blocks', 'secondary' => 'cases', 'conversion' => 12],
                        'butter' => ['primary' => 'packs', 'secondary' => 'cases', 'conversion' => 20],
                        'yogurt' => ['primary' => 'cups', 'secondary' => 'trays', 'conversion' => 12],
                    ];
                    
                    $conversionConfig = $unitConversions[$productType] ?? $unitConversions['bottled_milk'];
                    $conversionFactor = $conversionConfig['conversion'];
                    $primaryUnit = $conversionConfig['primary'];
                    $secondaryUnit = $conversionConfig['secondary'];
                    
                    // Calculate total pieces and breakdown
                    $totalPieces = $actualQuantity;
                    
                    // If input was in secondary unit (boxes/crates/cases), convert to pieces
                    if (in_array($outputUnit, ['boxes', 'crates', 'cases', $secondaryUnit])) {
                        $totalPieces = $actualQuantity * $conversionFactor;
                    }
                    
                    // Calculate breakdown: X boxes + Y pieces
                    $secondaryCount = floor($totalPieces / $conversionFactor);
                    $remainingPrimary = $totalPieces % $conversionFactor;
                    
                    $variance = $totalPieces - $run['planned_quantity'];
                    
                    // Store output breakdown as JSON
                    $outputBreakdown = json_encode([
                        'total_pieces' => $totalPieces,
                        'secondary_count' => $secondaryCount,
                        'secondary_unit' => $secondaryUnit,
                        'remaining_primary' => $remainingPrimary,
                        'primary_unit' => $primaryUnit,
                        'input_quantity' => $actualQuantity,
                        'input_unit' => $outputUnit,
                        'conversion_factor' => $conversionFactor
                    ]);
                    
                    $stmt = $db->prepare("
                        UPDATE production_runs 
                        SET status = 'completed', 
                            end_datetime = NOW(), 
                            completed_by = ?,
                            actual_quantity = ?,
                            output_breakdown = ?,
                            yield_variance = ?,
                            variance_reason = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $currentUser['user_id'],
                        $totalPieces,
                        $outputBreakdown,
                        $variance,
                        $varianceReason,
                        $runId
                    ]);
                    
                    Response::success([
                        'status' => 'completed',
                        'actual_quantity' => $totalPieces,
                        'output_breakdown' => [
                            'total_pieces' => $totalPieces,
                            'secondary_count' => $secondaryCount,
                            'secondary_unit' => $secondaryUnit,
                            'remaining_primary' => $remainingPrimary,
                            'primary_unit' => $primaryUnit,
                            'display' => "{$secondaryCount} " . ucfirst($secondaryUnit) . " + {$remainingPrimary} " . ucfirst($primaryUnit) . " ({$totalPieces} total)"
                        ],
                        'yield_variance' => $variance
                    ], 'Production run completed');
                    break;
                    
                case 'cancel':
                    if ($run['status'] === 'completed') {
                        Response::error('Cannot cancel a completed run', 400);
                    }
                    
                    $stmt = $db->prepare("UPDATE production_runs SET status = 'cancelled' WHERE id = ?");
                    $stmt->execute([$runId]);
                    
                    Response::success(['status' => 'cancelled'], 'Production run cancelled');
                    break;
                    
                default:
                    // General update
                    $notes = getParam('notes');
                    if ($notes !== null) {
                        $stmt = $db->prepare("UPDATE production_runs SET notes = ? WHERE id = ?");
                        $stmt->execute([$notes, $runId]);
                    }
                    
                    Response::success(null, 'Production run updated');
            }
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Production Runs API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
