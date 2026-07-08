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

function productionRunMaterialStatusesSql() {
    return "'planned', 'in_progress', 'pasteurization', 'processing', 'cooling', 'packaging', 'completed'";
}

function normalizeProductionUnit($unit) {
    $unit = strtolower(trim((string) $unit));
    $unit = rtrim($unit, '.');

    $aliases = [
        'kgs' => 'kg',
        'kilo' => 'kg',
        'kilos' => 'kg',
        'kilogram' => 'kg',
        'kilograms' => 'kg',
        'l' => 'liter',
        'litre' => 'liter',
        'litres' => 'liter',
        'liters' => 'liter',
        'packet' => 'packet',
        'packets' => 'packet',
        'pcs' => 'piece',
        'pc' => 'piece',
        'pieces' => 'piece',
        'unit' => 'unit',
        'units' => 'unit'
    ];

    return $aliases[$unit] ?? $unit;
}

function parseIngredientAdjustments($ingredientAdjustmentsJson) {
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

function calculateRecipeIngredientRequirements($db, $recipeId, $plannedQuantity, $ingredientAdjustmentsJson = null) {
    $recipeStmt = $db->prepare("SELECT expected_yield FROM master_recipes WHERE id = ?");
    $recipeStmt->execute([$recipeId]);
    $recipe = $recipeStmt->fetch();
    $expectedYield = $recipe && (float) $recipe['expected_yield'] > 0 ? (float) $recipe['expected_yield'] : 1;
    $scaleFactor = max(0, (float) $plannedQuantity) / $expectedYield;

    $ingredientsStmt = $db->prepare("
        SELECT ingredient_id, ingredient_name, quantity, unit, is_optional
        FROM recipe_ingredients
        WHERE recipe_id = ?
    ");
    $ingredientsStmt->execute([$recipeId]);
    $recipeIngredients = $ingredientsStmt->fetchAll();

    $adjustments = parseIngredientAdjustments($ingredientAdjustmentsJson);
    $requirements = [];

    foreach ($recipeIngredients as $ingredient) {
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

        $requirements[] = [
            'ingredient_id' => $ingredientId,
            'ingredient_name' => $ingredientName,
            'quantity' => $quantity,
            'unit' => $ingredient['unit'],
            'normalized_unit' => normalizeProductionUnit($ingredient['unit']),
            'is_optional' => (int) ($ingredient['is_optional'] ?? 0)
        ];
    }

    $combined = [];
    foreach ($requirements as $requirement) {
        $idPart = $requirement['ingredient_id'] > 0
            ? "id:{$requirement['ingredient_id']}"
            : "name:" . strtolower(trim($requirement['ingredient_name']));
        $key = $idPart . "|unit:" . $requirement['normalized_unit'];

        if (!isset($combined[$key])) {
            $combined[$key] = $requirement;
            continue;
        }

        $combined[$key]['quantity'] += $requirement['quantity'];
        $combined[$key]['quantity'] = round($combined[$key]['quantity'], 3);
    }

    return array_values($combined);
}

function getIssuedIngredientStats($db, $ingredient) {
    $ingredientId = (int) ($ingredient['ingredient_id'] ?? 0);
    $normalizedUnit = $ingredient['normalized_unit'];
    $params = [];

    $where = "
        ri.item_type = 'ingredient'
        AND ri.issued_quantity > 0
        AND ir.department = 'production'
    ";

    if ($ingredientId > 0) {
        $where .= " AND ri.item_id = ?";
        $params[] = $ingredientId;
    } else {
        $where .= " AND LOWER(TRIM(ri.item_name)) = ?";
        $params[] = strtolower(trim($ingredient['ingredient_name']));
    }

    // V4.0.1 — also pull the pack_size_at_submit snapshot from the row.
    // The requisition system stores the value already converted to the
    // base unit (see $storedBaseQty = $requestedPacks * $packSizeAtSubmit
    // in api/production/requisitions.php line 1164) but the unit_of_measure
    // column can still be the pack container word (e.g., "sack" for Sugar
    // with pack_size 25 kg, or "packet" for Cultures with pack_size 1 kg).
    // So a row like (issued=1, unit="packet", pack_size_at_submit=1)
    // is actually "1 kg", not "1 packet" — the value is in the base unit.
    // Without this snapshot check, the exact-unit filter below would
    // silently drop these rows and the production run page would say
    // "0 kg available" even though the requisition was fulfilled.
    $stmt = $db->prepare("
        SELECT ri.issued_quantity, ri.unit_of_measure,
               ri.pack_size_at_submit,
               COALESCE(ri.fulfilled_at, ri.updated_at, ri.created_at) AS issued_at
        FROM requisition_items ri
        JOIN material_requisitions ir ON ri.requisition_id = ir.id
        WHERE {$where}
    ");
    $stmt->execute($params);

    $total = 0.0;
    $earliestIssuedAt = null;
    foreach ($stmt->fetchAll() as $row) {
        $rowUnit = normalizeProductionUnit($row['unit_of_measure']);
        // Accept the row when EITHER:
        //   (a) the unit label matches the recipe's base unit (the
        //       requester typed the qty in kg/L directly, no pack
        //       conversion at submit time), OR
        //   (b) the row has a pack_size_at_submit snapshot, which
        //       means the value was already converted to the base
        //       unit at requisition-create time and is in kg/L
        //       regardless of what unit_of_measure is labeled.
        $isBaseUnit = ($rowUnit === $normalizedUnit);
        $hasPackSizeSnapshot = $row['pack_size_at_submit'] !== null;
        if ($isBaseUnit || $hasPackSizeSnapshot) {
            $total += (float) $row['issued_quantity'];
            if ($row['issued_at'] && (!$earliestIssuedAt || $row['issued_at'] < $earliestIssuedAt)) {
                $earliestIssuedAt = $row['issued_at'];
            }
        }
    }

    return [
        'total_issued' => $total,
        'earliest_issued_at' => $earliestIssuedAt
    ];
}

function getReservedIngredientQuantity($db, $ingredient, $excludeRunId = null, $earliestIssuedAt = null) {
    $statuses = productionRunMaterialStatusesSql();
    $sql = "
        SELECT pr.id, pr.recipe_id, pr.planned_quantity, pr.ingredient_adjustments
        FROM production_runs pr
        WHERE pr.status IN ({$statuses})
    ";
    $params = [];

    if ($excludeRunId) {
        $sql .= " AND pr.id <> ?";
        $params[] = $excludeRunId;
    }

    if ($earliestIssuedAt) {
        $sql .= " AND pr.created_at >= ?";
        $params[] = $earliestIssuedAt;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $total = 0.0;
    $targetId = (int) ($ingredient['ingredient_id'] ?? 0);
    $targetName = strtolower(trim($ingredient['ingredient_name']));
    $targetUnit = $ingredient['normalized_unit'];

    foreach ($stmt->fetchAll() as $run) {
        $requirements = calculateRecipeIngredientRequirements(
            $db,
            $run['recipe_id'],
            $run['planned_quantity'],
            $run['ingredient_adjustments']
        );

        foreach ($requirements as $requirement) {
            $sameId = $targetId > 0 && (int) $requirement['ingredient_id'] === $targetId;
            $sameName = $targetId <= 0 && strtolower(trim($requirement['ingredient_name'])) === $targetName;
            if (($sameId || $sameName) && $requirement['normalized_unit'] === $targetUnit) {
                $total += (float) $requirement['quantity'];
            }
        }
    }

    return $total;
}

function validateIssuedIngredientsForRun($db, $recipeId, $plannedQuantity, $ingredientAdjustmentsJson) {
    $requirements = calculateRecipeIngredientRequirements($db, $recipeId, $plannedQuantity, $ingredientAdjustmentsJson);
    $errors = [];
    $allocation = [];

    foreach ($requirements as $ingredient) {
        $issuedStats = getIssuedIngredientStats($db, $ingredient);
        $issued = $issuedStats['total_issued'];
        $reserved = getReservedIngredientQuantity($db, $ingredient, null, $issuedStats['earliest_issued_at']);
        $available = max(0, $issued - $reserved);
        $needed = (float) $ingredient['quantity'];

        $allocation[] = [
            'ingredient_id' => $ingredient['ingredient_id'],
            'ingredient_name' => $ingredient['ingredient_name'],
            'quantity_reserved' => $needed,
            'unit' => $ingredient['unit'],
            'issued_to_production' => $issued,
            'already_reserved' => $reserved,
            'available_before_run' => $available
        ];

        if ($needed > $available + 0.01) {
            $errors[] = "{$ingredient['ingredient_name']}: need {$needed} {$ingredient['unit']}, available " . round($available, 3) . " {$ingredient['unit']}";
        }
    }

    return [
        'requirements' => $requirements,
        'allocation' => $allocation,
        'errors' => $errors
    ];
}

function getUsableIssuedRawMilkStats($db) {
    // V4.0 (Issue A fix) — only count raw milk that is still usable. The
    // previous version summed ALL issued_quantity from requisition_items
    // regardless of batch expiry, which made the batches page report
    // "1,213 L available" when in fact every batch had expired (the
    // pasteurization page correctly excluded them, so the two pages
    // disagreed about the same number). Now we INNER JOIN to the
    // inventory_transactions → raw_milk_inventory trace and require
    // earliest_expiry >= CURDATE() before counting the requisition.
    $stmt = $db->prepare("
        SELECT
            issued.requisition_id,
            ir.requisition_code,
            issued.issued_liters,
            issued.issued_at,
            trace.earliest_expiry,
            trace.source_batches
        FROM (
            SELECT
                ri.requisition_id,
                SUM(COALESCE(ri.issued_quantity, 0)) as issued_liters,
                MAX(COALESCE(ri.fulfilled_at, ri.updated_at, ri.created_at)) as issued_at
            FROM requisition_items ri
            WHERE COALESCE(ri.issued_quantity, 0) > 0
              AND (
                  ri.item_type = 'raw_milk'
                  OR LOWER(ri.item_name) IN ('raw', 'raw milk', 'fresh milk', 'carabao', 'cow milk', 'goat milk', 'whole milk')
                  OR (
                      LOWER(ri.item_name) LIKE '%milk%'
                      AND LOWER(ri.item_name) NOT LIKE '%powder%'
                      AND LOWER(ri.item_name) NOT LIKE '%chocolate%'
                  )
              )
            GROUP BY ri.requisition_id
        ) issued
        JOIN material_requisitions ir ON ir.id = issued.requisition_id
        -- INNER JOIN (was LEFT) so a requisition with no inventory
        -- transactions, or only expired batches, is excluded entirely.
        JOIN (
            SELECT
                it.reference_id,
                MIN(rmi.expiry_date) as earliest_expiry,
                GROUP_CONCAT(DISTINCT rmi.batch_code ORDER BY rmi.expiry_date ASC SEPARATOR ', ') as source_batches
            FROM inventory_transactions it
            JOIN raw_milk_inventory rmi ON rmi.id = it.batch_id
            WHERE it.item_type = 'raw_milk'
              AND it.reference_type = 'requisition'
              AND it.quantity > 0
            GROUP BY it.reference_id
            -- Only count requisitions whose milk is still in date.
            HAVING MIN(rmi.expiry_date) >= CURDATE()
        ) trace ON trace.reference_id = issued.requisition_id
        WHERE issued.issued_liters > 0
          AND ir.department = 'production'
        ORDER BY COALESCE(trace.earliest_expiry, '9999-12-31') ASC, issued.issued_at ASC
    ");
    $stmt->execute();
    $sources = $stmt->fetchAll();

    $totalIssued = 0.0;
    $earliestIssuedAt = null;
    foreach ($sources as $source) {
        $totalIssued += (float) $source['issued_liters'];
        if ($source['issued_at'] && (!$earliestIssuedAt || $source['issued_at'] < $earliestIssuedAt)) {
            $earliestIssuedAt = $source['issued_at'];
        }
    }

    return [
        'sources' => $sources,
        'total_issued' => $totalIssued,
        'earliest_issued_at' => $earliestIssuedAt
    ];
}

function getReservedRawMilkLiters($db, $earliestIssuedAt = null) {
    $statuses = productionRunMaterialStatusesSql();
    $sql = "
        SELECT COALESCE(SUM(milk_liters_used), 0) as total_used
        FROM production_runs
        WHERE status IN ({$statuses})
          AND (milk_source_type IS NULL OR milk_source_type = 'raw')
    ";
    $params = [];

    if ($earliestIssuedAt) {
        $sql .= " AND created_at >= ?";
        $params[] = $earliestIssuedAt;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return (float) ($row['total_used'] ?? 0);
}

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
                
                $issuedMilkStats = getUsableIssuedRawMilkStats($db);
                $totalIssued = $issuedMilkStats['total_issued'];
                $totalReserved = getReservedRawMilkLiters($db, $issuedMilkStats['earliest_issued_at']);
                $availableLiters = max(0, $totalIssued - $totalReserved);
                $milkSources = array_map(function($source) {
                    return [
                        'id' => $source['requisition_id'],
                        'delivery_code' => $source['requisition_code'],
                        'remaining_liters' => (float) $source['issued_liters'],
                        'delivery_date' => $source['issued_at'],
                        'expiry_date' => $source['earliest_expiry'],
                        'source_batches' => $source['source_batches'],
                        'farmer_name' => 'Warehouse Raw',
                        'fat_percentage' => 0
                    ];
                }, $issuedMilkStats['sources']);
                
                // Return in format expected by batches.html frontend
                Response::success([
                    'total_available_liters' => (float) $availableLiters,
                    'milk_sources' => $milkSources,
                    'total_issued' => (float) $totalIssued,
                    'total_used' => (float) $totalReserved,
                    'source' => 'requisition_based',
                    'milk_type' => 'raw',
                    'freshness_window' => 'Issued through fulfilled requisitions',
                    'message' => $availableLiters > 0 
                        ? "You have {$availableLiters}L of usable issued milk available"
                        : 'No issued milk available. Submit a requisition or wait for Warehouse Raw to fulfill it.'
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
                // Get single run with details. LEFT JOIN material_requisitions so the
                // detail view can render a "From REQ-XXX" badge for runs started from
                // a pre-run requisition.
                $stmt = $db->prepare("
                    SELECT pr.*,
                           mr.recipe_code, mr.product_name, mr.product_type, mr.variant,
                           mr.base_milk_liters, mr.expected_yield, mr.yield_unit,
                           mr.pasteurization_temp, mr.pasteurization_time_mins, mr.cooling_temp,
                           u1.first_name as started_by_first, u1.last_name as started_by_last,
                           u2.first_name as completed_by_first, u2.last_name as completed_by_last,
                           mrq.id as linked_requisition_id,
                           mrq.requisition_code as linked_requisition_code,
                           mrq.status as linked_requisition_status,
                           mrq.planned_quantity as linked_requisition_planned_quantity,
                           mrq.planned_yield_unit as linked_requisition_yield_unit
                    FROM production_runs pr
                    JOIN master_recipes mr ON pr.recipe_id = mr.id
                    LEFT JOIN users u1 ON pr.started_by = u1.id
                    LEFT JOIN users u2 ON pr.completed_by = u2.id
                    LEFT JOIN material_requisitions mrq ON mrq.production_run_id = pr.id
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
            $statusGroup = getParam('status_group');
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
            } elseif ($statusGroup === 'active') {
                $where .= " AND pr.status IN ('planned', 'in_progress', 'pasteurization', 'processing', 'cooling', 'packaging')";
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
            $plannedQuantity = (float) getParam('planned_quantity', 0);
            $milkLitersUsed = getParam('milk_liters_used');
            $notes = trim(getParam('notes', ''));
            $pasteurizedMilkBatchId = getParam('pasteurized_milk_batch_id'); // For yogurt
            $processTemperature = getParam('process_temperature');
            $processDurationMins = getParam('process_duration_mins');
            $ingredientAdjustments = getParam('ingredient_adjustments'); // JSON string
            $creamOutputKg = getParam('cream_output_kg');
            $skimMilkOutputLiters = getParam('skim_milk_output_liters');
            $cheeseState = getParam('cheese_state');
            $isSalted = getParam('is_salted', 0);
            // Optional: when this run is being started from a fulfilled pre-run requisition,
            // the frontend passes the requisition ID. We validate the requisition and link
            // the new run back to it so the lineage (REQ -> RUN) is preserved.
            $materialRequisitionId = getParam('material_requisition_id');
            $linkedRequisitionCode = null;
            $ingredientValidation = [
                'requirements' => [],
                'allocation' => [],
                'errors' => []
            ];

            // Validation
            $errors = [];
            if (!$recipeId) $errors['recipe_id'] = 'Recipe is required';
            if ($plannedQuantity <= 0) $errors['planned_quantity'] = 'Planned quantity must be greater than 0';

            // If a source requisition is provided, validate it up front so the rest of
            // the flow can assume the link is legitimate.
            $sourceRequisition = null;
            if ($materialRequisitionId) {
                // Fetch the planned fields too so we can enforce a 1:1 match
                // between the run's planned_quantity and the requisition's plan.
                // See the "planned_quantity must match" check below.
                $reqStmt = $db->prepare("
                    SELECT id, requisition_code, status, planned_recipe_id, planned_quantity,
                           planned_yield_unit, production_run_id
                    FROM material_requisitions
                    WHERE id = ?
                ");
                $reqStmt->execute([$materialRequisitionId]);
                $sourceRequisition = $reqStmt->fetch();

                if (!$sourceRequisition) {
                    $errors['material_requisition_id'] = 'Source requisition not found';
                } elseif (!in_array($sourceRequisition['status'], ['fulfilled', 'partial'], true)) {
                    $errors['material_requisition_id'] = 'Source requisition is not ready for production (status: ' . $sourceRequisition['status'] . ')';
                } elseif (!empty($sourceRequisition['production_run_id'])) {
                    $errors['material_requisition_id'] = 'Source requisition is already linked to a production run';
                } elseif (!empty($sourceRequisition['planned_recipe_id']) && (int)$sourceRequisition['planned_recipe_id'] !== (int)$recipeId) {
                    $errors['material_requisition_id'] = 'Recipe does not match the source requisition';
                } else {
                    // Requisition-driven runs must respect the requisition's plan
                    // exactly. The requisition is the GM-approved production plan,
                    // and the warehouse issued materials specifically for that qty.
                    // If production staff want a different batch size, the right
                    // path is to edit the requisition (which forces re-approval +
                    // re-fulfillment with the right amounts), not to silently
                    // scale the run. This protects the REQ -> RUN traceability
                    // chain and prevents silent over-issues from the shared pool.
                    //
                    // Legacy requisitions (pre plan-guard) may have NULL or 0
                    // planned_quantity; those skip the equality check and are
                    // allowed through with whatever the run supplies.
                    $reqPlanned = (float)($sourceRequisition['planned_quantity'] ?? 0);
                    if ($reqPlanned > 0 && abs((float)$plannedQuantity - $reqPlanned) > 0.001) {
                        $errors['planned_quantity'] = sprintf(
                            'Planned volume must match the source requisition (%s %s). ' .
                            'Edit the requisition to change the batch size.',
                            number_format($reqPlanned, 3),
                            $sourceRequisition['planned_yield_unit'] ?? ''
                        );
                    } elseif ($reqPlanned > 0) {
                        // Snap the run's planned_quantity to the requisition's
                        // exact value to avoid float drift (e.g. user sent 50.0001
                        // and the requisition is 50.00).
                        $plannedQuantity = $reqPlanned;
                    }
                }

                // Set the lineage code on the success path. The error branches
                // above leave $errors populated and this is never reached, but
                // we guard with a no-errors check so the response payload is
                // clean.
                if (empty($errors['material_requisition_id']) && empty($errors['planned_quantity'])) {
                    $linkedRequisitionCode = $sourceRequisition['requisition_code'];
                }
            }

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
                } else if ($totalAvailableLiters < $requiredMilkLiters - 0.01) {
                    $errors['milk_source'] = "⚠️ Not enough PASTEURIZED milk. Required: {$requiredMilkLiters}L, Available: {$totalAvailableLiters}L. Please pasteurize more milk.";
                } else {
                    // Auto-select batch (FIFO - oldest first)
                    $pasteurizedBatchId = $pasteurizedBatches[0]['id'];
                }
            } else {
                // OTHER PRODUCTS (bottled_milk, cheese, butter, milk_bar): Use raw milk via requisitions
                
                $issuedMilkStats = getUsableIssuedRawMilkStats($db);
                $totalAvailableLiters = max(
                    0,
                    $issuedMilkStats['total_issued'] - getReservedRawMilkLiters($db, $issuedMilkStats['earliest_issued_at'])
                );
                
                if ($totalAvailableLiters <= 0) {
                    $errors['milk_source'] = 'No usable issued milk available. Submit a requisition to Warehouse Raw or wait for fresh issued milk.';
                } else if ($totalAvailableLiters < $requiredMilkLiters - 0.01) {
                    $errors['milk_source'] = "Not enough usable issued milk. Required: {$requiredMilkLiters}L, Available: {$totalAvailableLiters}L. Please submit a requisition for more fresh milk.";
                }
            }

            if ($recipe) {
                $ingredientValidation = validateIssuedIngredientsForRun($db, $recipeId, $plannedQuantity, $ingredientAdjustments);
                if (!empty($ingredientValidation['errors'])) {
                    $errors['ingredients'] = 'Not enough issued ingredients for this run. Submit/fulfill a Warehouse Raw requisition first: ' . implode('; ', $ingredientValidation['errors']);
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
                    'pasteurized_batch_id' => $pasteurizedBatchId,
                    'ingredient_reservations' => $ingredientValidation['allocation']
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
                    // V4.0 — fixed two bugs in this query:
                    //   1. The original `status = CASE WHEN remaining_liters - ? <= 0
                    //      THEN 'exhausted' ELSE status END` re-subtracted the
                    //      quantity a second time, so a batch that ended up
                    //      with any positive remaining (e.g. 1.97 - 1.11 = 0.86)
                    //      was being marked as exhausted because (0.86 - 1.11 = -0.25)
                    //      is <= 0. The check should look at the NEW value of
                    //      remaining_liters (after the first SET clause), not
                    //      subtract again.
                    //   2. 'exhausted' is not a valid value in the status ENUM
                    //      ('available','reserved','used','expired','disposed').
                    //      MySQL silently stored it as the empty string '',
                    //      which then failed the `WHERE status = 'available'`
                    //      filter in available_pasteurized_milk, hiding
                    //      partially-used batches from the stock check.
                    //      Now uses 'used' (a valid ENUM value) for fully
                    //      consumed batches and leaves 'available' alone for
                    //      partially-used ones.
                    $deductStmt = $db->prepare("
                        UPDATE pasteurized_milk_inventory
                        SET remaining_liters = remaining_liters - ?,
                            status = CASE WHEN remaining_liters <= 0 THEN 'used' ELSE status END
                        WHERE id = ?
                    ");

                    foreach ($pasteurizedBatches as $batch) {
                        if ($remainingToDeduct <= 0) break;

                        $deductQty = min((float) $batch['remaining_liters'], $remainingToDeduct);
                        // V4.0 — only 2 params now (qty, id) since the
                        // status CASE no longer needs the qty twice.
                        $deductStmt->execute([$deductQty, $batch['id']]);
                        $remainingToDeduct -= $deductQty;
                    }
                    
                    // Log the usage
                    error_log("Yogurt production {$runCode}: Deducted {$requiredMilkLiters}L from pasteurized milk inventory");
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

                // If this run was started from a pre-run requisition, link the new
                // run back to it so the lineage REQ -> RUN is preserved. We do this
                // AFTER commit so a failure here doesn't roll back the run creation.
                if ($sourceRequisition && empty($sourceRequisition['production_run_id'])) {
                    $linkStmt = $db->prepare("UPDATE material_requisitions SET production_run_id = ? WHERE id = ?");
                    $linkStmt->execute([$runId, $materialRequisitionId]);
                }

                Response::created([
                    'id' => $runId,
                    'run_code' => $runCode,
                    'status' => 'planned',
                    'milk_liters_used' => $requiredMilkLiters,
                    'milk_source_type' => $milkSourceType,
                    'available_after' => $totalAvailableLiters - $requiredMilkLiters,
                    'product_type' => $recipe['product_type'],
                    'has_ingredient_adjustments' => !empty($ingredientAdjustments),
                    'ingredient_reservations' => $ingredientValidation['allocation'],
                    'pasteurized_batch_id' => $pasteurizedBatchId,
                    'material_requisition_id' => $materialRequisitionId ?: null,
                    'linked_requisition_code' => $linkedRequisitionCode
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
                        
                        $recipeIngredients = calculateRecipeIngredientRequirements(
                            $db,
                            $run['recipe_id'],
                            $run['planned_quantity'],
                            $run['ingredient_adjustments'] ?? null
                        );
                        
                        if (!empty($recipeIngredients)) {
                            // Insert consumption records for each ingredient
                            $consumptionStmt = $db->prepare("
                                INSERT INTO ingredient_consumption 
                                (run_id, ingredient_id, ingredient_name, quantity_used, unit, batch_code, notes)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            foreach ($recipeIngredients as $ingredient) {
                                $consumptionStmt->execute([
                                    $runId,
                                    $ingredient['ingredient_id'],
                                    $ingredient['ingredient_name'],
                                    $ingredient['quantity'],
                                    $ingredient['unit'],
                                    $batchCode,
                                    "Auto-recorded from materials reserved when the run was created."
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
