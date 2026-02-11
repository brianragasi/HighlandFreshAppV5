<?php
/**
 * Highland Fresh System - Expiry Management API
 * 
 * Implements "The Yogurt Rule" from PRD:
 * "Near-expiry milk must be transformed into Yogurt to prevent financial loss"
 * 
 * Endpoints:
 * GET    - List expiring/expired products
 * POST   - Initiate yogurt transformation (creates transformation + production run)
 * PUT    - Update transformation status / link to production run
 * 
 * Flow per system_context/production_staff.md:
 * 1. QC identifies bottled milk nearing expiry
 * 2. Production Staff take that milk
 * 3. "Less" it from finished goods inventory
 * 4. Use it as raw ingredient for new batch of Yogurt
 * 5. Document as "Transformation" (NOT "Waste")
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require QC or Production role
$currentUser = Auth::requireRole(['qc_officer', 'general_manager', 'production_staff']);

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            $action = getParam('action');
            
            // Handle different GET actions
            switch ($action) {
                case 'expiring':
                    // Get expiring FG products within X days
                    $days = (int) getParam('days', 7);
                    
                    $stmt = $db->prepare("
                        SELECT fgi.*,
                               p.product_name, p.category, p.variant,
                               p.unit_size, p.unit_measure,
                               pb.batch_code,
                               pb.batch_code as batch_number,
                               DATEDIFF(fgi.expiry_date, CURDATE()) as days_until_expiry,
                               'finished_goods' as type,
                               fgi.quantity_available as remaining_liters,
                               fgi.quantity_available,
                               COALESCE(fgi.chiller_location, 'FG Warehouse') as location,
                               CASE 
                                   WHEN p.category IN ('milk', 'pasteurized_milk', 'flavored_milk') THEN true 
                                   ELSE false 
                               END as can_transform
                        FROM finished_goods_inventory fgi
                        LEFT JOIN products p ON fgi.product_id = p.id
                        LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                        WHERE fgi.status = 'available' 
                          AND fgi.quantity_available > 0
                          AND fgi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                        ORDER BY fgi.expiry_date ASC
                    ");
                    $stmt->execute([$days]);
                    Response::success($stmt->fetchAll(), 'Expiring products retrieved');
                    break;
                    
                case 'raw_milk':
                    // Get raw milk inventory with expiry status (using milk_receiving - revised schema)
                    $id = getParam('id');
                    
                    if ($id) {
                        $stmt = $db->prepare("
                            SELECT rmi.*,
                                   mr.receiving_code, mr.volume_liters as original_liters,
                                   f.farmer_code, COALESCE(f.first_name, '') as farmer_name,
                                   qmt.grade, qmt.test_code,
                                   DATEDIFF(rmi.expiry_date, CURDATE()) as days_until_expiry
                            FROM raw_milk_inventory rmi
                            LEFT JOIN milk_receiving mr ON rmi.receiving_id = mr.id
                            LEFT JOIN farmers f ON mr.farmer_id = f.id
                            LEFT JOIN qc_milk_tests qmt ON rmi.qc_test_id = qmt.id
                            WHERE rmi.id = ?
                        ");
                        $stmt->execute([$id]);
                        $item = $stmt->fetch();
                        if (!$item) Response::notFound('Raw milk not found');
                        Response::success($item, 'Raw milk retrieved');
                    } else {
                        $stmt = $db->query("
                            SELECT rmi.*,
                                   mr.receiving_code, mr.volume_liters as original_liters, mr.receiving_date as received_date,
                                   f.farmer_code, COALESCE(f.first_name, '') as farmer_name,
                                   qmt.grade, qmt.test_code,
                                   DATEDIFF(rmi.expiry_date, CURDATE()) as days_until_expiry,
                                   'raw_milk' as type
                            FROM raw_milk_inventory rmi
                            LEFT JOIN milk_receiving mr ON rmi.receiving_id = mr.id
                            LEFT JOIN farmers f ON mr.farmer_id = f.id
                            LEFT JOIN qc_milk_tests qmt ON rmi.qc_test_id = qmt.id
                            WHERE (rmi.status = 'available' OR rmi.status = '' OR rmi.status IS NULL) 
                              AND rmi.remaining_liters > 0
                            ORDER BY rmi.expiry_date ASC
                        ");
                        Response::success($stmt->fetchAll(), 'Raw milk inventory retrieved');
                    }
                    break;
                    
                case 'finished_goods':
                    // Get FG by ID
                    $id = getParam('id');
                    if (!$id) Response::validationError(['id' => 'ID required']);
                    
                    $stmt = $db->prepare("
                        SELECT fgi.*,
                               p.product_name, p.category, p.variant, p.unit_size, p.unit_measure,
                               pb.batch_code as batch_number,
                               DATEDIFF(fgi.expiry_date, CURDATE()) as days_until_expiry,
                               fgi.quantity_available as remaining_liters
                        FROM finished_goods_inventory fgi
                        LEFT JOIN products p ON fgi.product_id = p.id
                        LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                        WHERE fgi.id = ?
                    ");
                    $stmt->execute([$id]);
                    $item = $stmt->fetch();
                    if (!$item) Response::notFound('Finished goods not found');
                    Response::success($item, 'Finished goods retrieved');
                    break;
                    
                case 'transformations':
                    // Get transformation history
                    $stmt = $db->query("
                        SELECT yt.*,
                               p.product_name as source_product_name,
                               mr.product_name as output_product_name,
                               CONCAT(u.first_name, ' ', u.last_name) as processed_by_name,
                               yt.source_quantity as input_quantity,
                               'units' as input_unit,
                               yt.target_quantity as output_quantity,
                               'L' as output_unit,
                               yt.notes as reason,
                               yt.created_at as transformed_at
                        FROM yogurt_transformations yt
                        LEFT JOIN finished_goods_inventory fgi ON yt.source_inventory_id = fgi.id
                        LEFT JOIN products p ON fgi.product_id = p.id
                        LEFT JOIN master_recipes mr ON yt.target_recipe_id = mr.id
                        LEFT JOIN users u ON yt.initiated_by = u.id
                        ORDER BY yt.created_at DESC
                        LIMIT 50
                    ");
                    Response::success($stmt->fetchAll(), 'Transformations retrieved');
                    break;
                    
                case 'yogurt_products':
                    // Get yogurt recipes for transformation target
                    $stmt = $db->query("
                        SELECT id, recipe_code, product_name as name, expected_yield, yield_unit
                        FROM master_recipes
                        WHERE product_type = 'yogurt' AND is_active = 1
                    ");
                    Response::success($stmt->fetchAll(), 'Yogurt products retrieved');
                    break;
                    
                default:
                    // Check if requesting transformation list (legacy)
                    $listTransformations = getParam('list_transformations');
            
                    if ($listTransformations) {
                        // List all transformations
                        $status = getParam('status');
                        $page = (int) getParam('page', 1);
                        $limit = (int) getParam('limit', 20);
                        $offset = ($page - 1) * $limit;
                        
                        $where = "WHERE 1=1";
                        $params = [];
                        
                        if ($status) {
                            $where .= " AND yt.status = ?";
                            $params[] = $status;
                        }
                        
                        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM yogurt_transformations yt {$where}");
                        $countStmt->execute($params);
                        $total = $countStmt->fetch()['total'];
                        
                        $stmt = $db->prepare("
                            SELECT yt.*,
                                   fgi.expiry_date as source_expiry_date,
                                   p.product_name as source_product_name,
                                   mr.product_name as target_recipe_name,
                                   pr.run_code as production_run_code,
                                   pr.status as production_run_status,
                                   CONCAT(u1.first_name, ' ', u1.last_name) as initiated_by_name,
                                   CONCAT(u2.first_name, ' ', u2.last_name) as completed_by_name
                            FROM yogurt_transformations yt
                            LEFT JOIN finished_goods_inventory fgi ON yt.source_inventory_id = fgi.id
                            LEFT JOIN products p ON fgi.product_id = p.id
                            LEFT JOIN master_recipes mr ON yt.target_recipe_id = mr.id
                            LEFT JOIN production_runs pr ON yt.production_run_id = pr.id
                            LEFT JOIN users u1 ON yt.initiated_by = u1.id
                            LEFT JOIN users u2 ON yt.completed_by = u2.id
                            {$where}
                            ORDER BY yt.created_at DESC
                            LIMIT ? OFFSET ?
                        ");
                        $params[] = $limit;
                        $params[] = $offset;
                        $stmt->execute($params);
                        $transformations = $stmt->fetchAll();
                        
                        Response::paginated($transformations, $total, $page, $limit, 'Transformations retrieved');
                        break;
                    }
                    
                    // Default: List expiring products
                    $filter = getParam('filter', 'all'); // all, warning, critical, expired
                    $productId = getParam('product_id');
                    $page = (int) getParam('page', 1);
                    $limit = (int) getParam('limit', 20);
                    $offset = ($page - 1) * $limit;
                    
                    $where = "WHERE fgi.status = 'available' AND fgi.quantity_available > 0";
                    $params = [];
                    
                    switch ($filter) {
                        case 'warning':
                            $where .= " AND fgi.expiry_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 4 DAY) AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                            break;
                        case 'critical':
                            $where .= " AND fgi.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)";
                            break;
                        case 'expired':
                            $where .= " AND fgi.expiry_date < CURDATE()";
                            break;
                    }
                    
                    if ($productId) {
                        $where .= " AND fgi.product_id = ?";
                        $params[] = $productId;
                    }
                    
                    // Get total count
                    $countStmt = $db->prepare("
                        SELECT COUNT(*) as total 
                        FROM finished_goods_inventory fgi
                        {$where}
                    ");
                    $countStmt->execute($params);
                    $total = $countStmt->fetch()['total'];
                    
                    // Get inventory
                    $stmt = $db->prepare("
                        SELECT fgi.*,
                               pb.batch_code,
                               p.product_code, p.product_name, p.category, p.variant,
                               DATEDIFF(fgi.expiry_date, CURDATE()) as days_until_expiry,
                               CASE 
                                   WHEN fgi.expiry_date < CURDATE() THEN 'expired'
                                   WHEN DATEDIFF(fgi.expiry_date, CURDATE()) <= 3 THEN 'critical'
                                   WHEN DATEDIFF(fgi.expiry_date, CURDATE()) <= 7 THEN 'warning'
                                   ELSE 'ok'
                               END as alert_status
                        FROM finished_goods_inventory fgi
                        LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                        LEFT JOIN products p ON fgi.product_id = p.id
                        {$where}
                        ORDER BY fgi.expiry_date ASC
                        LIMIT ? OFFSET ?
                    ");
                    $params[] = $limit;
                    $params[] = $offset;
                    $stmt->execute($params);
                    $inventory = $stmt->fetchAll();
                    
                    Response::paginated($inventory, $total, $page, $limit, 'Expiry data retrieved successfully');
                    break;
            } // End of inner action switch
            break; // End of GET case
            
        case 'POST':
            // Initiate yogurt transformation - The "Yogurt Rule"
            $inventoryId = getParam('inventory_id');
            $quantity = getParam('quantity');
            $notes = trim(getParam('notes', ''));
            $createProductionRun = getParam('create_production_run', false);
            
            // Validation
            $errors = [];
            if (!$inventoryId) $errors['inventory_id'] = 'Inventory ID is required';
            if (!$quantity || $quantity <= 0) $errors['quantity'] = 'Valid quantity is required';
            
            // Get inventory item
            $inventory = null;
            if ($inventoryId) {
                $invStmt = $db->prepare("
                    SELECT fgi.*, p.category, p.unit_size, p.unit_measure, p.product_name
                    FROM finished_goods_inventory fgi
                    LEFT JOIN products p ON fgi.product_id = p.id
                    WHERE fgi.id = ? AND fgi.status = 'available'
                ");
                $invStmt->execute([$inventoryId]);
                $inventory = $invStmt->fetch();
                
                if (!$inventory) {
                    $errors['inventory_id'] = 'Inventory not found or not available';
                } elseif (!in_array($inventory['category'], ['milk', 'pasteurized_milk', 'flavored_milk'])) {
                    $errors['inventory_id'] = 'Only milk products can be transformed to yogurt';
                } elseif ($quantity > $inventory['quantity_available']) {
                    $errors['quantity'] = 'Quantity exceeds available stock';
                }
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Calculate volume in liters
            $volumeLiters = $quantity * ($inventory['unit_size'] ?? 1);
            if (($inventory['unit_measure'] ?? 'ml') === 'ml') {
                $volumeLiters = $volumeLiters / 1000;
            }
            
            // Find a yogurt recipe to use
            $recipeStmt = $db->query("
                SELECT id, recipe_code, product_name, base_milk_liters, expected_yield 
                FROM master_recipes 
                WHERE product_type = 'yogurt' AND is_active = 1 
                LIMIT 1
            ");
            $yogurtRecipe = $recipeStmt->fetch();
            
            // Generate transformation code
            $codeStmt = $db->query("SELECT MAX(CAST(SUBSTRING(transformation_code, 5) AS UNSIGNED)) as max_num FROM yogurt_transformations WHERE transformation_code LIKE 'YTF-%'");
            $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
            $transformCode = 'YTF-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);
            
            // Begin transaction
            $db->beginTransaction();
            
            try {
                // 1. Create transformation record
                $stmt = $db->prepare("
                    INSERT INTO yogurt_transformations (
                        transformation_code, source_inventory_id, source_quantity,
                        source_volume_liters, target_product, target_recipe_id,
                        transformation_date, initiated_by, approved_by, approval_datetime,
                        safety_verified, status, notes
                    ) VALUES (?, ?, ?, ?, 'Yogurt', ?, CURDATE(), ?, ?, NOW(), 1, 'pending', ?)
                ");
                $stmt->execute([
                    $transformCode, 
                    $inventoryId, 
                    $quantity,
                    $volumeLiters, 
                    $yogurtRecipe ? $yogurtRecipe['id'] : null,
                    $currentUser['user_id'],
                    $currentUser['user_id'], 
                    $notes ?: "Transformation from near-expiry: {$inventory['product_name']}"
                ]);
                
                $transformId = $db->lastInsertId();
                
                // 2. Deduct from FG inventory - "Less" it per spec
                $updateStmt = $db->prepare("
                    UPDATE finished_goods_inventory 
                    SET quantity_available = quantity_available - ?,
                        status = CASE WHEN quantity_available - ? <= 0 THEN 'transformed' ELSE status END
                    WHERE id = ?
                ");
                $updateStmt->execute([$quantity, $quantity, $inventoryId]);
                
                $productionRunId = null;
                $runCode = null;
                
                // 3. Optionally create production run for yogurt
                if ($createProductionRun && $yogurtRecipe) {
                    // Generate run code
                    $today = date('Ymd');
                    $runCodeStmt = $db->prepare("SELECT COUNT(*) as count FROM production_runs WHERE run_code LIKE ?");
                    $runCodeStmt->execute(["PRD-{$today}-%"]);
                    $runCount = $runCodeStmt->fetch()['count'] + 1;
                    $runCode = "PRD-{$today}-" . str_pad($runCount, 3, '0', STR_PAD_LEFT);
                    
                    // Calculate expected yield based on volume
                    $expectedYield = round($volumeLiters * ($yogurtRecipe['expected_yield'] / $yogurtRecipe['base_milk_liters']));
                    
                    $runStmt = $db->prepare("
                        INSERT INTO production_runs (
                            run_code, recipe_id, planned_quantity, milk_liters_used,
                            status, notes, created_by
                        ) VALUES (?, ?, ?, ?, 'planned', ?, ?)
                    ");
                    $runStmt->execute([
                        $runCode,
                        $yogurtRecipe['id'],
                        $expectedYield,
                        $volumeLiters,
                        "Yogurt transformation from {$transformCode}",
                        $currentUser['user_id']
                    ]);
                    
                    $productionRunId = $db->lastInsertId();
                    
                    // 4. Link transformation to production run
                    $linkStmt = $db->prepare("
                        UPDATE yogurt_transformations 
                        SET production_run_id = ?, status = 'in_progress'
                        WHERE id = ?
                    ");
                    $linkStmt->execute([$productionRunId, $transformId]);
                }
                
                $db->commit();
                
                // Log audit
                logAudit($currentUser['user_id'], 'CREATE', 'yogurt_transformations', $transformId, null, [
                    'transformation_code' => $transformCode,
                    'source_inventory_id' => $inventoryId,
                    'quantity' => $quantity,
                    'production_run_id' => $productionRunId
                ]);
                
                $response = [
                    'transformation_id' => $transformId,
                    'transformation_code' => $transformCode,
                    'source_quantity' => $quantity,
                    'volume_liters' => $volumeLiters,
                    'status' => $productionRunId ? 'in_progress' : 'pending'
                ];
                
                if ($productionRunId) {
                    $response['production_run'] = [
                        'id' => $productionRunId,
                        'run_code' => $runCode,
                        'recipe' => $yogurtRecipe['product_name']
                    ];
                }
                
                Response::success($response, 'Yogurt transformation initiated successfully', 201);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'PUT':
            // Update transformation status or link to production run
            $transformId = getParam('id');
            $action = getParam('action');
            
            if (!$transformId) {
                Response::validationError(['id' => 'Transformation ID is required']);
            }
            
            // Get current transformation
            $stmt = $db->prepare("
                SELECT yt.*, mr.product_name as recipe_name
                FROM yogurt_transformations yt
                LEFT JOIN master_recipes mr ON yt.target_recipe_id = mr.id
                WHERE yt.id = ?
            ");
            $stmt->execute([$transformId]);
            $transformation = $stmt->fetch();
            
            if (!$transformation) {
                Response::notFound('Transformation not found');
            }
            
            switch ($action) {
                case 'link_production_run':
                    // Link an existing production run to this transformation
                    $runId = getParam('production_run_id');
                    if (!$runId) {
                        Response::validationError(['production_run_id' => 'Production run ID is required']);
                    }
                    
                    // Verify run exists and is for yogurt
                    $runStmt = $db->prepare("
                        SELECT pr.*, mr.product_type 
                        FROM production_runs pr
                        JOIN master_recipes mr ON pr.recipe_id = mr.id
                        WHERE pr.id = ?
                    ");
                    $runStmt->execute([$runId]);
                    $run = $runStmt->fetch();
                    
                    if (!$run) {
                        Response::notFound('Production run not found');
                    }
                    
                    if ($run['product_type'] !== 'yogurt') {
                        Response::validationError(['production_run_id' => 'Production run must be for yogurt product']);
                    }
                    
                    $updateStmt = $db->prepare("
                        UPDATE yogurt_transformations 
                        SET production_run_id = ?, status = 'in_progress'
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$runId, $transformId]);
                    
                    Response::success(['status' => 'in_progress', 'production_run_id' => $runId], 'Production run linked');
                    break;
                    
                case 'complete':
                    // Mark transformation as completed (when yogurt batch is done)
                    if (!$transformation['production_run_id']) {
                        Response::validationError(['error' => 'Cannot complete transformation without a linked production run']);
                    }
                    
                    $targetQuantity = getParam('target_quantity', 0);
                    
                    $updateStmt = $db->prepare("
                        UPDATE yogurt_transformations 
                        SET status = 'completed', 
                            target_quantity = ?,
                            completed_by = ?,
                            completed_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$targetQuantity, $currentUser['user_id'], $transformId]);
                    
                    Response::success(['status' => 'completed'], 'Transformation completed - Yogurt batch ready');
                    break;
                    
                case 'cancel':
                    // Cancel transformation (only if not yet in production)
                    if ($transformation['status'] === 'completed') {
                        Response::validationError(['error' => 'Cannot cancel completed transformation']);
                    }
                    
                    $db->beginTransaction();
                    try {
                        // Restore inventory if possible
                        if ($transformation['status'] === 'pending') {
                            $restoreStmt = $db->prepare("
                                UPDATE finished_goods_inventory 
                                SET quantity_available = quantity_available + ?,
                                    status = 'available'
                                WHERE id = ?
                            ");
                            $restoreStmt->execute([$transformation['source_quantity'], $transformation['source_inventory_id']]);
                        }
                        
                        $updateStmt = $db->prepare("
                            UPDATE yogurt_transformations 
                            SET status = 'cancelled'
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$transformId]);
                        
                        $db->commit();
                        Response::success(['status' => 'cancelled'], 'Transformation cancelled');
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;
                    
                default:
                    Response::validationError(['action' => 'Invalid action. Use: link_production_run, complete, or cancel']);
            }
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Expiry Management API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
