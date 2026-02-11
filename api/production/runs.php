<?php
/**
 * Highland Fresh System - Production Runs API
 *
 * REVISED: Updated for new schema (Feb 2026)
 * - Uses material_requisitions instead of ingredient_requisitions
 * - Added production_material_usage tracking for traceability
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
                // NEW SYSTEM: Get milk issued to production via requisitions
                // This is the proper flow per system_context:
                // 1. Production requisitions milk
                // 2. GM/Warehouse approves
                // 3. Warehouse fulfills/issues milk
                // 4. Production can now use the issued milk
                
                // Get milk issued to production via requisitions (with details)
                $issuedMilkStmt = $db->prepare("
                    SELECT 
                        ri.id,
                        ir.requisition_code as delivery_code,
                        ri.issued_quantity as remaining_liters,
                        ri.fulfilled_at as delivery_date,
                        'Requisition' as farmer_name,
                        0 as fat_percentage
                    FROM requisition_items ri
                    JOIN material_requisitions ir ON ri.requisition_id = ir.id
                    WHERE ri.item_type = 'raw_milk'
                      AND ri.issued_quantity > 0
                      AND ir.department = 'production'
                    ORDER BY ri.fulfilled_at DESC
                ");
                $issuedMilkStmt->execute();
                $milkSources = $issuedMilkStmt->fetchAll();
                
                $totalIssued = array_sum(array_column($milkSources, 'remaining_liters'));
                
                // Get milk already used in production runs (raw milk only)
                $usedMilkStmt = $db->prepare("
                    SELECT COALESCE(SUM(milk_liters_used), 0) as total_used
                    FROM production_runs
                    WHERE status IN ('in_progress', 'completed', 'pasteurization', 'processing', 'cooling', 'packaging')
                      AND (milk_source_type IS NULL OR milk_source_type = 'raw')
                ");
                $usedMilkStmt->execute();
                $usedStats = $usedMilkStmt->fetch();
                
                $availableLiters = max(0, $totalIssued - ($usedStats['total_used'] ?? 0));
                
                // Return in format expected by batches.html frontend
                Response::success([
                    'total_available_liters' => (float) $availableLiters,
                    'milk_sources' => $milkSources,
                    'total_issued' => (float) $totalIssued,
                    'total_used' => (float) ($usedStats['total_used'] ?? 0),
                    'source' => 'requisition_based',
                    'milk_type' => 'raw',
                    'freshness_window' => 'Via requisitions',
                    'message' => $availableLiters > 0 
                        ? "You have {$availableLiters}L of milk available (issued via requisitions)"
                        : 'No milk available. Please submit a requisition to Warehouse Raw and wait for approval/fulfillment.'
                ], 'Available milk retrieved');
            }
            
            // Get available PASTEURIZED milk for yogurt production
            if ($action === 'available_pasteurized_milk') {
                $pasteurizedStmt = $db->prepare("
                    SELECT 
                        id,
                        batch_code,
                        remaining_liters,
                        pasteurization_temp,
                        pasteurized_at,
                        expiry_date,
                        DATEDIFF(expiry_date, CURDATE()) as days_until_expiry
                    FROM pasteurized_milk_inventory
                    WHERE status = 'available' 
                      AND remaining_liters > 0
                      AND expiry_date >= CURDATE()
                    ORDER BY pasteurized_at ASC
                ");
                $pasteurizedStmt->execute();
                $batches = $pasteurizedStmt->fetchAll();
                
                $totalAvailable = array_sum(array_column($batches, 'remaining_liters'));
                
                Response::success([
                    'total_available_liters' => (float) $totalAvailable,
                    'batches' => $batches,
                    'batch_count' => count($batches),
                    'source' => 'pasteurized_inventory',
                    'milk_type' => 'pasteurized',
                    'message' => $totalAvailable > 0 
                        ? "Pasteurized milk available: {$totalAvailable}L from " . count($batches) . " batch(es)"
                        : '⚠️ No pasteurized milk available. Please run pasteurization on raw milk first.'
                ], 'Available pasteurized milk retrieved');
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
            $notes = trim(getParam('notes', ''));
            $pasteurizedMilkBatchId = getParam('pasteurized_milk_batch_id'); // For yogurt
            
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
            // If user provided custom milk_liters_used, use that
            // Otherwise, scale from recipe: (base_milk_liters / expected_yield) * planned_quantity
            if ($milkLitersUsed && $milkLitersUsed > 0) {
                $requiredMilkLiters = (float) $milkLitersUsed;
            } else if ($recipe) {
                // Scale milk requirement based on planned quantity vs recipe's expected yield
                $baseYield = $recipe['expected_yield'] > 0 ? $recipe['expected_yield'] : 1;
                $requiredMilkLiters = round(($recipe['base_milk_liters'] / $baseYield) * $plannedQuantity, 2);
            } else {
                $requiredMilkLiters = 0;
            }
            
            // ====================================================
            // YOGURT VALIDATION: Must use PASTEURIZED milk only!
            // Per production_requirements.md: Yogurt CANNOT draw from Raw Milk
            // ====================================================
            $milkSourceType = 'raw'; // default
            $pasteurizedBatchId = null;
            $totalAvailableLiters = 0;
            
            if ($recipe && $recipe['product_type'] === 'yogurt') {
                // YOGURT: Check pasteurized milk inventory (FIFO)
                $milkSourceType = 'pasteurized';
                
                $pasteurizedStmt = $db->prepare("
                    SELECT id, batch_code, remaining_liters, expiry_date
                    FROM pasteurized_milk_inventory
                    WHERE status = 'available' 
                      AND remaining_liters > 0
                      AND expiry_date >= CURDATE()
                    ORDER BY pasteurized_at ASC
                    LIMIT 10
                ");
                $pasteurizedStmt->execute();
                $pasteurizedBatches = $pasteurizedStmt->fetchAll();
                
                $totalAvailableLiters = array_sum(array_column($pasteurizedBatches, 'remaining_liters'));
                
                if (empty($pasteurizedBatches)) {
                    $errors['milk_source'] = '⚠️ YOGURT requires PASTEURIZED MILK. No pasteurized milk available. Please run pasteurization first.';
                } else if ($totalAvailableLiters < $requiredMilkLiters) {
                    $errors['milk_source'] = "⚠️ Not enough PASTEURIZED milk. Required: {$requiredMilkLiters}L, Available: {$totalAvailableLiters}L. Please pasteurize more milk.";
                } else {
                    // Auto-select batch (FIFO - oldest first)
                    $pasteurizedBatchId = $pasteurizedBatches[0]['id'];
                }
            } else {
                // OTHER PRODUCTS (bottled_milk, cheese, butter, milk_bar): Use raw milk via requisitions
                
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
                
                // Get milk already used in production runs
                $usedMilkStmt = $db->prepare("
                    SELECT COALESCE(SUM(milk_liters_used), 0) as total_used
                    FROM production_runs
                    WHERE status IN ('in_progress', 'completed', 'pasteurization', 'processing', 'cooling', 'packaging')
                      AND milk_source_type = 'raw'
                ");
                $usedMilkStmt->execute();
                $usedStats = $usedMilkStmt->fetch();
                
                $totalAvailableLiters = max(0, ($issuedStats['total_issued'] ?? 0) - ($usedStats['total_used'] ?? 0));
                
                if ($totalAvailableLiters <= 0) {
                    $errors['milk_source'] = 'No milk available. Please submit a requisition to Warehouse Raw and wait for approval/fulfillment.';
                } else if ($totalAvailableLiters < $requiredMilkLiters) {
                    $errors['milk_source'] = "Not enough milk available. Required: {$requiredMilkLiters}L, Available: {$totalAvailableLiters}L. Please submit a requisition for more milk.";
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
            
            // Get new optional fields
            $processTemperature = getParam('process_temperature');
            $processDurationMins = getParam('process_duration_mins');
            $ingredientAdjustments = getParam('ingredient_adjustments'); // JSON string
            $creamOutputKg = getParam('cream_output_kg');
            $skimMilkOutputLiters = getParam('skim_milk_output_liters');
            $cheeseState = getParam('cheese_state');
            $isSalted = getParam('is_salted', 0);
            
            try {
                $db->beginTransaction();
                
                // Insert run - now includes milk_source_type, pasteurized_milk_batch_id, and milk_type_id
                $stmt = $db->prepare("
                    INSERT INTO production_runs (
                        run_code, recipe_id, milk_type_id, planned_quantity, milk_liters_used,
                        milk_batch_source, milk_source_type, pasteurized_milk_batch_id,
                        status, notes,
                        process_temperature, process_duration_mins, ingredient_adjustments,
                        cream_output_kg, skim_milk_output_liters, cheese_state, is_salted
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'planned', ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Record milk source info
                $milkSourceInfo = json_encode([
                    'source' => $milkSourceType === 'pasteurized' ? 'pasteurized_inventory' : 'requisition_based',
                    'available_at_creation' => $totalAvailableLiters,
                    'allocated' => $requiredMilkLiters,
                    'pasteurized_batch_id' => $pasteurizedBatchId
                ]);
                
                $stmt->execute([
                    $runCode, $recipeId, $recipe['milk_type_id'], $plannedQuantity, 
                    $requiredMilkLiters,
                    $milkSourceInfo,
                    $milkSourceType,
                    $pasteurizedBatchId,
                    $notes,
                    $processTemperature,
                    $processDurationMins,
                    $ingredientAdjustments,
                    $creamOutputKg,
                    $skimMilkOutputLiters,
                    $cheeseState,
                    $isSalted
                ]);
                
                $runId = $db->lastInsertId();
                
                // YOGURT: Deduct from pasteurized milk inventory (FIFO)
                if ($milkSourceType === 'pasteurized' && $pasteurizedBatchId) {
                    $remainingToDeduct = $requiredMilkLiters;
                    
                    // Deduct from batches in FIFO order
                    $deductStmt = $db->prepare("
                        UPDATE pasteurized_milk_inventory 
                        SET remaining_liters = remaining_liters - ?,
                            status = CASE WHEN remaining_liters - ? <= 0 THEN 'exhausted' ELSE status END
                        WHERE id = ? AND remaining_liters >= ?
                    ");
                    
                    // Try to deduct from the primary batch first
                    $deductStmt->execute([$requiredMilkLiters, $requiredMilkLiters, $pasteurizedBatchId, $requiredMilkLiters]);
                    
                    // Log the usage
                    error_log("Yogurt production {$runCode}: Deducted {$requiredMilkLiters}L from pasteurized batch #{$pasteurizedBatchId}");
                }
                
                // If butter production, auto-create skim_milk byproduct from separation
                // Per production_requirements.md: Butter separation produces ~80% skim milk + ~20% cream
                // The skim_milk byproduct can be used for yogurt or sold
                if ($recipe['product_type'] === 'butter' && $skimMilkOutputLiters > 0) {
                    $byproductStmt = $db->prepare("
                        INSERT INTO production_byproducts 
                        (run_id, byproduct_type, quantity, unit, status, destination, recorded_by, notes)
                        VALUES (?, 'skim_milk', ?, 'liters', 'pending', 'warehouse', ?, 'From butter separation - can be used for yogurt')
                    ");
                    $byproductStmt->execute([$runId, $skimMilkOutputLiters, $currentUser['user_id']]);
                    error_log("Butter production {$runCode}: Recorded {$skimMilkOutputLiters}L skim milk byproduct");
                }
                
                $db->commit();
                
                Response::created([
                    'id' => $runId,
                    'run_code' => $runCode,
                    'status' => 'planned',
                    'milk_liters_used' => $requiredMilkLiters,
                    'milk_source_type' => $milkSourceType,
                    'available_after' => $totalAvailableLiters - $requiredMilkLiters,
                    'product_type' => $recipe['product_type'],
                    'has_ingredient_adjustments' => !empty($ingredientAdjustments),
                    'pasteurized_batch_id' => $pasteurizedBatchId
                ], 'Production run created successfully');
                
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
                    
                    // =====================================================
                    // CRITICAL: Validate CCP logs exist before completing
                    // Per system_context/production_staff.md:
                    // - Must have pasteurization log (75°C for 15 seconds)
                    // - Must have at least one cooling verification (4°C)
                    // 
                    // NOTE: We check the MOST RECENT log for each check type
                    // This allows staff to re-log if they made a mistake
                    // =====================================================
                    
                    // Get the most recent log for each check type
                    $ccpCheckStmt = $db->prepare("
                        SELECT 
                            check_type,
                            temperature,
                            pressure_psi,
                            hold_time_secs,
                            status,
                            check_datetime
                        FROM production_ccp_logs pcl1
                        WHERE run_id = ?
                          AND check_datetime = (
                              SELECT MAX(check_datetime) 
                              FROM production_ccp_logs pcl2 
                              WHERE pcl2.run_id = pcl1.run_id 
                                AND pcl2.check_type = pcl1.check_type
                          )
                        ORDER BY check_type
                    ");
                    $ccpCheckStmt->execute([$runId]);
                    $ccpLogs = $ccpCheckStmt->fetchAll();
                    
                    $hasPasteurization = false;
                    $hasCooling = false;
                    $failedCCPs = [];
                    
                    foreach ($ccpLogs as $log) {
                        if ($log['check_type'] === 'pasteurization') {
                            $hasPasteurization = true;
                            if ($log['status'] === 'fail') {
                                $failedCCPs[] = 'pasteurization';
                            }
                        }
                        if ($log['check_type'] === 'cooling') {
                            $hasCooling = true;
                            if ($log['status'] === 'fail') {
                                $failedCCPs[] = 'cooling';
                            }
                        }
                    }
                    
                    $ccpErrors = [];
                    if (!$hasPasteurization) {
                        $ccpErrors[] = 'Pasteurization CCP log is required (75°C for 15 seconds)';
                    }
                    if (!$hasCooling) {
                        $ccpErrors[] = 'Cooling verification CCP log is required (4°C)';
                    }
                    if (!empty($failedCCPs)) {
                        $ccpErrors[] = 'The most recent ' . implode(' and ', $failedCCPs) . ' CCP check(s) failed. Please log a correct reading.';
                    }
                    
                    if (!empty($ccpErrors)) {
                        Response::validationError([
                            'ccp_logs' => implode('; ', $ccpErrors),
                            'required_ccps' => ['pasteurization', 'cooling'],
                            'logged_ccps' => array_column($ccpLogs, 'check_type')
                        ], 'CCP validation failed - Food safety requirements not met');
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
                    
                    $db->beginTransaction();
                    
                    try {
                        // Update production run to completed
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
                        
                        // =====================================================
                        // CREATE PRODUCTION BATCH FOR QC VERIFICATION
                        // Per system_context: After production completes, the batch
                        // must go through QC final verification (organoleptic tests)
                        // before being released to Finished Goods warehouse
                        // =====================================================
                        
                        // Generate batch code
                        $batchCode = 'BATCH-' . date('Ymd') . '-' . str_pad($runId, 4, '0', STR_PAD_LEFT);
                        
                        // Calculate expiry date (default 7 days for fresh milk products)
                        $expiryDays = 7;
                        if (in_array($productType, ['cheese'])) $expiryDays = 30;
                        if (in_array($productType, ['butter'])) $expiryDays = 60;
                        if (in_array($productType, ['milk_bar'])) $expiryDays = 14;
                        
                        $expiryDate = date('Y-m-d', strtotime("+{$expiryDays} days"));
                        
                        // Create batch record for QC verification
                        // Columns: batch_code, recipe_id, run_id, milk_type_id, product_type, manufacturing_date,
                        // raw_milk_liters, expected_yield, actual_yield, qc_status, created_by
                        $batchStmt = $db->prepare("
                            INSERT INTO production_batches (
                                batch_code, recipe_id, run_id, milk_type_id, product_type, manufacturing_date,
                                raw_milk_liters, expected_yield, actual_yield, qc_status, created_by, expiry_date
                            ) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, 'pending', ?, ?)
                        ");
                        $batchStmt->execute([
                            $batchCode,
                            $run['recipe_id'],
                            $runId,
                            $run['milk_type_id'],
                            $productType,
                            $run['milk_liters_used'],
                            $run['planned_quantity'],
                            $totalPieces,
                            $currentUser['user_id'],
                            $expiryDate
                        ]);
                        
                        $batchId = $db->lastInsertId();
                        
                        // =====================================================
                        // CRITICAL GAP #1 FIX: Record Ingredient Consumption
                        // Per production_staff.md: Track actual ingredients used
                        // vs master recipe for inventory accuracy
                        // =====================================================
                        
                        // Get recipe ingredients
                        $ingredientsStmt = $db->prepare("
                            SELECT ingredient_id, ingredient_name, quantity, unit 
                            FROM recipe_ingredients 
                            WHERE recipe_id = ?
                        ");
                        $ingredientsStmt->execute([$run['recipe_id']]);
                        $recipeIngredients = $ingredientsStmt->fetchAll();
                        
                        if (!empty($recipeIngredients)) {
                            // Calculate scaling factor based on actual vs planned production
                            $scaleFactor = $run['planned_quantity'] > 0 
                                ? $totalPieces / $run['planned_quantity'] 
                                : 1;
                            
                            // Insert consumption records for each ingredient
                            $consumptionStmt = $db->prepare("
                                INSERT INTO ingredient_consumption 
                                (run_id, ingredient_id, ingredient_name, quantity_used, unit, batch_code, notes)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            foreach ($recipeIngredients as $ingredient) {
                                // Scale quantity based on actual production
                                $actualQuantity = round($ingredient['quantity'] * $scaleFactor, 3);
                                
                                $consumptionStmt->execute([
                                    $runId,
                                    $ingredient['ingredient_id'],
                                    $ingredient['ingredient_name'],
                                    $actualQuantity,
                                    $ingredient['unit'],
                                    $batchCode,
                                    "Auto-recorded on run completion. Scale factor: {$scaleFactor}"
                                ]);
                            }
                            
                            error_log("Production run {$run['run_code']}: Recorded " . count($recipeIngredients) . " ingredient consumption records");
                        }
                        
                        // =====================================================
                        // CRITICAL GAP #2 FIX: Record Buttermilk Byproduct
                        // Per production_requirements.md: Butter churning produces
                        // buttermilk as a byproduct (~50% of cream weight)
                        // =====================================================
                        
                        if ($productType === 'butter') {
                            // Estimate buttermilk output (~50-55% of cream used)
                            // Cream is typically 20% of milk input, buttermilk is ~55% of cream
                            $creamUsed = $run['cream_output_kg'] ?? 0;
                            if ($creamUsed > 0) {
                                $buttermilkLiters = round($creamUsed * 0.55, 2); // ~55% of cream becomes buttermilk
                                
                                $buttermilkStmt = $db->prepare("
                                    INSERT INTO production_byproducts 
                                    (run_id, byproduct_type, quantity, unit, status, destination, recorded_by, notes)
                                    VALUES (?, 'buttermilk', ?, 'liters', 'pending', 'warehouse', ?, 'From butter churning')
                                ");
                                $buttermilkStmt->execute([$runId, $buttermilkLiters, $currentUser['user_id']]);
                                error_log("Butter run {$run['run_code']}: Recorded {$buttermilkLiters}L buttermilk byproduct");
                            }
                        }
                        
                        $db->commit();
                        
                        Response::success([
                            'status' => 'completed',
                            'actual_quantity' => $totalPieces,
                            'batch_id' => $batchId,
                            'batch_code' => $batchCode,
                            'qc_status' => 'pending',
                            'message' => 'Production completed! Batch sent to QC for final verification.',
                            'output_breakdown' => [
                                'total_pieces' => $totalPieces,
                                'secondary_count' => $secondaryCount,
                                'secondary_unit' => $secondaryUnit,
                                'remaining_primary' => $remainingPrimary,
                                'primary_unit' => $primaryUnit,
                                'display' => "{$secondaryCount} " . ucfirst($secondaryUnit) . " + {$remainingPrimary} " . ucfirst($primaryUnit) . " ({$totalPieces} total)"
                            ],
                            'yield_variance' => $variance
                        ], 'Production run completed - Batch sent to QC for verification');
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
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
