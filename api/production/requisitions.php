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

function requisitionParseIngredientAdjustments($ingredientAdjustmentsJson) {
    if (!$ingredientAdjustmentsJson) {
        return [];
    }

    $decoded = json_decode($ingredientAdjustmentsJson, true);
    if (!is_array($decoded)) {
        return [];
    }

    $adjustments = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }

        $ingredientId = (int) ($item['ingredient_id'] ?? 0);
        $ingredientName = strtolower(trim($item['ingredient_name'] ?? ''));
        $key = $ingredientId > 0 ? "id:{$ingredientId}" : "name:{$ingredientName}";
        $adjustments[$key] = $item;
    }

    return $adjustments;
}

function ensureProductionRequisitionPlanColumns($db) {
    if (!auditColumnExists($db, 'material_requisitions', 'planned_recipe_id')) {
        $db->exec("ALTER TABLE material_requisitions ADD COLUMN planned_recipe_id INT(11) DEFAULT NULL AFTER production_run_id");
    }
    if (!auditColumnExists($db, 'material_requisitions', 'planned_quantity')) {
        $db->exec("ALTER TABLE material_requisitions ADD COLUMN planned_quantity DECIMAL(10,2) DEFAULT NULL AFTER planned_recipe_id");
    }
    if (!auditColumnExists($db, 'material_requisitions', 'planned_yield_unit')) {
        $db->exec("ALTER TABLE material_requisitions ADD COLUMN planned_yield_unit VARCHAR(20) DEFAULT NULL AFTER planned_quantity");
    }

    $precisionStmt = $db->prepare("
        SELECT COLUMN_NAME, COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'requisition_items'
          AND COLUMN_NAME IN ('requested_quantity', 'issued_quantity')
    ");
    $precisionStmt->execute();
    $columnTypes = [];
    foreach ($precisionStmt->fetchAll() as $column) {
        $columnTypes[$column['COLUMN_NAME']] = strtolower($column['COLUMN_TYPE']);
    }

    if (($columnTypes['requested_quantity'] ?? '') !== 'decimal(10,3)') {
        $db->exec("ALTER TABLE requisition_items MODIFY requested_quantity DECIMAL(10,3) NOT NULL");
    }
    if (($columnTypes['issued_quantity'] ?? '') !== 'decimal(10,3)') {
        $db->exec("ALTER TABLE requisition_items MODIFY issued_quantity DECIMAL(10,3) DEFAULT 0.000");
    }
}

function getRequisitionRecipeItemsForPlan($db, $recipeId, $plannedQuantity) {
    $stmt = $db->prepare("
        SELECT id, recipe_code, product_name, variant, product_type, base_milk_liters, expected_yield, yield_unit
        FROM master_recipes
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$recipeId]);
    $recipe = $stmt->fetch();

    if (!$recipe) {
        Response::notFound('Recipe not found or inactive');
    }

    $expectedYield = (float) ($recipe['expected_yield'] ?? 0);
    $scaleFactor = $expectedYield > 0 ? max(0, (float) $plannedQuantity) / $expectedYield : 1;
    $requiredMilk = round(((float) $recipe['base_milk_liters']) * $scaleFactor, 3);
    $items = [];

    if ($requiredMilk > 0 && ($recipe['product_type'] ?? '') !== 'yogurt') {
        $items[] = [
            'item_type' => 'raw_milk',
            'item_id' => null,
            'item_name' => 'Raw Milk',
            'quantity' => $requiredMilk,
            'unit' => 'liters',
            'notes' => "Milk needed for planned {$recipe['product_name']} production"
        ];
    }

    $ingredientsStmt = $db->prepare("
        SELECT ingredient_id, ingredient_name, quantity, unit, is_optional, notes
        FROM recipe_ingredients
        WHERE recipe_id = ?
        ORDER BY is_optional ASC, ingredient_name ASC
    ");
    $ingredientsStmt->execute([$recipeId]);

    foreach ($ingredientsStmt->fetchAll() as $ingredient) {
        $quantity = round(((float) $ingredient['quantity']) * $scaleFactor, 3);
        if ($quantity <= 0) {
            continue;
        }

        $notes = trim((string) ($ingredient['notes'] ?? ''));
        if ((int) ($ingredient['is_optional'] ?? 0) === 1) {
            $notes = trim($notes . ($notes ? ' - ' : '') . 'Optional recipe item');
        }

        $items[] = [
            'item_type' => 'ingredient',
            'item_id' => (int) ($ingredient['ingredient_id'] ?? 0) > 0 ? (int) $ingredient['ingredient_id'] : null,
            'item_name' => trim($ingredient['ingredient_name'] ?? ''),
            'quantity' => $quantity,
            'unit' => $ingredient['unit'] ?: 'units',
            'notes' => $notes
        ];
    }

    return [
        'plan' => [
            'recipe_id' => (int) $recipe['id'],
            'recipe_code' => $recipe['recipe_code'],
            'product_name' => $recipe['product_name'],
            'variant' => $recipe['variant'],
            'planned_quantity' => (float) $plannedQuantity,
            'yield_unit' => $recipe['yield_unit']
        ],
        'items' => $items
    ];
}

function getRequisitionRecipeItemsForRun($db, $runId) {
    $stmt = $db->prepare("
        SELECT pr.id, pr.run_code, pr.recipe_id, pr.planned_quantity, pr.milk_liters_used,
               pr.milk_source_type, pr.ingredient_adjustments,
               mr.recipe_code, mr.product_name, mr.variant, mr.expected_yield
        FROM production_runs pr
        JOIN master_recipes mr ON pr.recipe_id = mr.id
        WHERE pr.id = ?
    ");
    $stmt->execute([$runId]);
    $run = $stmt->fetch();

    if (!$run) {
        Response::notFound('Production run not found');
    }

    $items = [];

    if (($run['milk_source_type'] ?? 'raw') === 'raw' && (float) ($run['milk_liters_used'] ?? 0) > 0) {
        $items[] = [
            'item_type' => 'raw_milk',
            'item_id' => null,
            'item_name' => 'Raw Milk',
            'quantity' => round((float) $run['milk_liters_used'], 3),
            'unit' => 'liters',
            'notes' => "Milk needed for {$run['run_code']}"
        ];
    }

    $expectedYield = (float) ($run['expected_yield'] ?? 0);
    $scaleFactor = $expectedYield > 0 ? max(0, (float) $run['planned_quantity']) / $expectedYield : 1;
    $adjustments = requisitionParseIngredientAdjustments($run['ingredient_adjustments'] ?? null);

    $ingredientsStmt = $db->prepare("
        SELECT ingredient_id, ingredient_name, quantity, unit, is_optional, notes
        FROM recipe_ingredients
        WHERE recipe_id = ?
        ORDER BY is_optional ASC, ingredient_name ASC
    ");
    $ingredientsStmt->execute([$run['recipe_id']]);

    foreach ($ingredientsStmt->fetchAll() as $ingredient) {
        $ingredientId = (int) ($ingredient['ingredient_id'] ?? 0);
        $ingredientName = trim($ingredient['ingredient_name'] ?? '');
        $nameKey = strtolower($ingredientName);
        $adjustment = $adjustments[$ingredientId > 0 ? "id:{$ingredientId}" : "name:{$nameKey}"] ?? null;
        $quantity = $adjustment && isset($adjustment['actual_quantity'])
            ? (float) $adjustment['actual_quantity']
            : round(((float) $ingredient['quantity']) * $scaleFactor, 3);

        if ($quantity <= 0) {
            continue;
        }

        $notes = trim((string) ($ingredient['notes'] ?? ''));
        if ((int) ($ingredient['is_optional'] ?? 0) === 1) {
            $notes = trim($notes . ($notes ? ' - ' : '') . 'Optional recipe item');
        }

        $items[] = [
            'item_type' => 'ingredient',
            'item_id' => $ingredientId > 0 ? $ingredientId : null,
            'item_name' => $ingredientName,
            'quantity' => $quantity,
            'unit' => $ingredient['unit'] ?: 'units',
            'notes' => $notes
        ];
    }

    return [
        'run' => [
            'id' => (int) $run['id'],
            'run_code' => $run['run_code'],
            'recipe_id' => (int) $run['recipe_id'],
            'recipe_code' => $run['recipe_code'],
            'product_name' => $run['product_name'],
            'variant' => $run['variant'],
            'planned_quantity' => (float) $run['planned_quantity']
        ],
        'items' => $items
    ];
}

try {
    $db = Database::getInstance()->getConnection();
    ensureProductionRequisitionPlanColumns($db);
    
    switch ($requestMethod) {
        case 'GET':
            $action = getParam('action', 'list');
            $reqId = getParam('id');

            if ($action === 'run_recipe_items') {
                $runId = getParam('run_id');
                if (!$runId) {
                    Response::validationError(['run_id' => 'Production run is required']);
                }

                Response::success(
                    getRequisitionRecipeItemsForRun($db, $runId),
                    'Recipe items retrieved successfully'
                );
            }

            if ($action === 'planned_recipe_items') {
                $recipeId = getParam('recipe_id');
                $plannedQuantity = (float) getParam('planned_quantity', 0);
                if (!$recipeId) {
                    Response::validationError(['recipe_id' => 'Recipe is required']);
                }
                if ($plannedQuantity <= 0) {
                    Response::validationError(['planned_quantity' => 'Planned quantity must be greater than 0']);
                }

                Response::success(
                    getRequisitionRecipeItemsForPlan($db, $recipeId, $plannedQuantity),
                    'Planned recipe items retrieved successfully'
                );
            }
            
            if ($reqId) {
                // Get single requisition with items
                $stmt = $db->prepare("
                    SELECT ir.*, 
                           pmr.recipe_code as planned_recipe_code,
                           pmr.product_name as planned_product_name,
                           pmr.variant as planned_variant,
                           u1.first_name as requested_by_first, u1.last_name as requested_by_last,
                           u2.first_name as approved_by_first, u2.last_name as approved_by_last,
                           u3.first_name as fulfilled_by_first, u3.last_name as fulfilled_by_last
                    FROM material_requisitions ir
                    LEFT JOIN master_recipes pmr ON ir.planned_recipe_id = pmr.id
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
                
                // Get items with fulfillment details
                $itemsStmt = $db->prepare("
                    SELECT ri.*,
                           uf.first_name as item_fulfilled_by_first,
                           uf.last_name as item_fulfilled_by_last
                    FROM requisition_items ri
                    LEFT JOIN users uf ON ri.fulfilled_by = uf.id
                    WHERE ri.requisition_id = ?
                    ORDER BY ri.id ASC
                ");
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
                SELECT ir.id, ir.requisition_code, ir.production_run_id, ir.planned_recipe_id,
                       ir.planned_quantity, ir.planned_yield_unit, ir.status,
                       ir.priority, ir.needed_by_date, ir.purpose, ir.total_items, ir.created_at,
                       u1.first_name as requested_by_first, u1.last_name as requested_by_last,
                       pr.run_code,
                       pmr.recipe_code as planned_recipe_code,
                       pmr.product_name as planned_product_name,
                       pmr.variant as planned_variant
                FROM material_requisitions ir
                LEFT JOIN users u1 ON ir.requested_by = u1.id
                LEFT JOIN production_runs pr ON ir.production_run_id = pr.id
                LEFT JOIN master_recipes pmr ON ir.planned_recipe_id = pmr.id
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
            $plannedRecipeId = getParam('planned_recipe_id');
            $plannedQuantity = getParam('planned_quantity');
            $plannedYieldUnit = getParam('planned_yield_unit');
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
            if (!$productionRunId && !$plannedRecipeId) {
                $errors['planned_recipe_id'] = 'Choose the planned recipe before submitting a pre-run requisition';
            }
            if (!$productionRunId && (!$plannedQuantity || (float) $plannedQuantity <= 0)) {
                $errors['planned_quantity'] = 'Planned quantity must be greater than 0';
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
                        requisition_code, production_run_id, planned_recipe_id, planned_quantity, planned_yield_unit, requested_by, 
                        priority, needed_by_date, purpose, total_items, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                
                $stmt->execute([
                    $requisitionCode, 
                    $productionRunId ?: null,
                    $productionRunId ? null : ($plannedRecipeId ?: null),
                    $productionRunId ? null : ($plannedQuantity ?: null),
                    $productionRunId ? null : ($plannedYieldUnit ?: null),
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
                    $itemNameRaw = trim($item['item_name'] ?? '');
                    $itemName = strtolower($itemNameRaw);
                    $itemType = $item['item_type'] ?? null;
                    $itemId = $item['item_id'] ?? 0;
                    $allowedTypes = ['ingredient', 'raw_milk'];

                    if ($itemType && !in_array($itemType, $allowedTypes, true)) {
                        $itemType = null;
                    }

                    if ($itemType === 'ingredient') {
                        $itemId = (int) $itemId;
                    } elseif ($itemType === 'raw_milk') {
                        $itemId = 0;
                    }

                    if (!$itemType) {
                        // Auto-detect item_type based on item name (free-text input)
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
                    }

                    $itemStmt->execute([
                        $requisitionId,
                        $itemType,
                        $itemId,
                        $itemNameRaw,
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
