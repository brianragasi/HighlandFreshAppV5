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
require_once dirname(__DIR__) . '/config/ccp_standards.php';
require_once dirname(__DIR__) . '/helpers/pack_uom.php';
require_once dirname(__DIR__) . '/helpers/recipe_production_readiness.php';
require_once dirname(__DIR__) . '/helpers/qc_count_discrepancy.php';
require_once __DIR__ . '/helpers/yield_helpers.php';

// Require Production role
$currentUser = Auth::requireRole(['production_staff', 'general_manager', 'qc_officer']);

function productionRunMaterialStatusesSql() {
    return "'planned', 'in_progress', 'pasteurization', 'processing', 'cooling', 'packaging', 'completed'";
}

/**
 * Packaging is issued through the normal Warehouse requisition queue, but it
 * is a separate request from the ingredients used to cook the bulk liquid.
 * Runtime guards keep upgraded local databases usable without a manual import.
 */
function ensureProductionPackagingRequestSchema(PDO $db) {
    if (!auditColumnExists($db, 'material_requisitions', 'request_type')) {
        $db->exec("ALTER TABLE material_requisitions
            ADD COLUMN request_type VARCHAR(30) NOT NULL DEFAULT 'cooking' AFTER production_run_id");
    }
    if (!auditColumnExists($db, 'material_requisitions', 'packaging_plan_json')) {
        $db->exec("ALTER TABLE material_requisitions
            ADD COLUMN packaging_plan_json LONGTEXT NULL AFTER request_type");
    }
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

/**
 * Bulk-batch plan denominator (liters of liquid plan), matching requisitions.php.
 * Priority: bulk_yield_liters → expected_yield if unit is liters → base_milk_liters → expected_yield.
 */
function getRecipeBulkPlanDenominator(array $recipe) {
    return getStrictRecipeBulkYieldLiters($recipe) ?? 1.0;
}

/** Scale factor: planned bulk liters / recipe bulk denominator. */
function getRecipePlanScaleFactor(array $recipe, $plannedQuantity) {
    $denom = getRecipeBulkPlanDenominator($recipe);
    return $denom > 0 ? max(0, (float) $plannedQuantity) / $denom : 1.0;
}

/** Milk liters required for a planned bulk quantity. */
function calculateRequiredMilkLitersForPlan(array $recipe, $plannedQuantity, $overrideLiters = null) {
    if ($overrideLiters !== null && (float) $overrideLiters > 0) {
        return round((float) $overrideLiters, 2);
    }
    $scale = getRecipePlanScaleFactor($recipe, $plannedQuantity);
    return round(((float) ($recipe['base_milk_liters'] ?? 0)) * $scale, 2);
}

function calculateRecipeIngredientRequirements($db, $recipeId, $plannedQuantity, $ingredientAdjustmentsJson = null) {
    // Prefer bulk-aware columns when present (same scale as material requisitions).
    $select = 'expected_yield, base_milk_liters, yield_unit';
    try {
        $db->query('SELECT bulk_yield_liters FROM master_recipes LIMIT 0');
        $select .= ', bulk_yield_liters';
    } catch (Throwable $e) {
        // legacy schema
    }
    $recipeStmt = $db->prepare("SELECT {$select} FROM master_recipes WHERE id = ?");
    $recipeStmt->execute([$recipeId]);
    $recipe = $recipeStmt->fetch() ?: [];
    $scaleFactor = getRecipePlanScaleFactor($recipe, $plannedQuantity);

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
    ensureProductionPackagingRequestSchema($db);
    ensureQcCountDiscrepancyTables($db);
    
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

    // Ensure yield/volume tracking columns exist
    $existingCols = array_map('strtolower', $db->query("SHOW COLUMNS FROM production_runs")->fetchAll(PDO::FETCH_COLUMN));
    $yieldCols = [
        'initial_volume_ml'      => 'decimal(12,2) DEFAULT 0',
        'total_loss_ml'          => 'decimal(12,2) DEFAULT 0',
        'total_byproduct_ml'     => 'decimal(12,2) DEFAULT 0',
        'net_yield_ml'           => 'decimal(12,2) DEFAULT 0',
        'material_reconciled'    => 'tinyint(1) DEFAULT 0',
        'reconciliation_notes'   => 'text NULL',
    ];
    foreach ($yieldCols as $col => $type) {
        if (!in_array($col, $existingCols)) {
            try {
                $db->exec("ALTER TABLE production_runs ADD COLUMN `{$col}` {$type}");
            } catch (Throwable $e) { /* ignore */ }
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
                try {
                    $issuedMilkStats = getUsableIssuedRawMilkStats($db);
                    $totalIssued = $issuedMilkStats['total_issued'];
                    $totalReserved = getReservedRawMilkLiters($db, $issuedMilkStats['earliest_issued_at']);
                    $availableLiters = max(0, $totalIssued - $totalReserved);
                    $milkSources = array_map(function ($source) {
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
                } catch (Throwable $e) {
                    error_log('available_milk failed: ' . $e->getMessage());
                    // Always return proper JSON so the UI can leave the spinner.
                    Response::error('Could not load milk availability. Please retry.', 500);
                }
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
                           mr.bulk_yield_liters,
                           mr.pasteurization_temp, mr.pasteurization_time_mins, mr.cooling_temp,
                           u1.first_name as started_by_first, u1.last_name as started_by_last,
                           u2.first_name as completed_by_first, u2.last_name as completed_by_last,
                           mrq.id as linked_requisition_id,
                           mrq.requisition_code as linked_requisition_code,
                           mrq.status as linked_requisition_status,
                           mrq.planned_quantity as linked_requisition_planned_quantity,
                           mrq.planned_yield_unit as linked_requisition_yield_unit,
                           (SELECT pb.qc_status FROM production_batches pb WHERE pb.run_id = pr.id ORDER BY pb.id DESC LIMIT 1) AS qc_batch_status,
                           (SELECT d.variance
                              FROM qc_batch_count_discrepancies d
                              JOIN production_batches pb2 ON pb2.id = d.batch_id
                             WHERE pb2.run_id = pr.id AND d.status = 'open'
                             ORDER BY d.id DESC LIMIT 1) AS qc_count_hold_variance,
                           (SELECT d.reason_notes
                              FROM qc_batch_count_discrepancies d
                              JOIN production_batches pb3 ON pb3.id = d.batch_id
                             WHERE pb3.run_id = pr.id AND d.status = 'open'
                             ORDER BY d.id DESC LIMIT 1) AS qc_count_hold_reason
                    FROM production_runs pr
                    JOIN master_recipes mr ON pr.recipe_id = mr.id
                    LEFT JOIN users u1 ON pr.started_by = u1.id
                    LEFT JOIN users u2 ON pr.completed_by = u2.id
                    LEFT JOIN material_requisitions mrq
                      ON mrq.production_run_id = pr.id
                     AND COALESCE(mrq.request_type, 'cooking') = 'cooking'
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

                // Separate Warehouse handover for bottles, caps, labels, etc.
                $packReqStmt = $db->prepare("
                    SELECT id, requisition_code, status, packaging_plan_json,
                           requested_by, approved_by, approved_at,
                           fulfilled_by, fulfilled_at, created_at
                    FROM material_requisitions
                    WHERE production_run_id = ? AND request_type = 'packaging'
                      AND status NOT IN ('cancelled', 'rejected')
                    ORDER BY id DESC LIMIT 1
                ");
                $packReqStmt->execute([$runId]);
                $packagingRequest = $packReqStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($packagingRequest) {
                    $packItemStmt = $db->prepare("
                        SELECT id, item_id, item_name, requested_quantity,
                               issued_quantity, unit_of_measure, status
                        FROM requisition_items
                        WHERE requisition_id = ? ORDER BY id
                    ");
                    $packItemStmt->execute([(int) $packagingRequest['id']]);
                    $packagingRequest['items'] = $packItemStmt->fetchAll(PDO::FETCH_ASSOC);
                    $packagingRequest['packaging_plan'] = json_decode(
                        (string) ($packagingRequest['packaging_plan_json'] ?? ''),
                        true
                    ) ?: [];
                    unset($packagingRequest['packaging_plan_json']);
                }
                $run['packaging_request'] = $packagingRequest;

                // Suggested starting volume for yield tracking (mL).
                // Prefer milk already planned on the run / issued via requisition —
                // production should not re-type what the system already knows.
                $suggestedLiters = null;
                $suggestedSource = null;

                // 1) Milk issued on linked fulfilled requisition (raw milk lines)
                if (!empty($run['linked_requisition_id'])) {
                    $issStmt = $db->prepare("
                        SELECT SUM(COALESCE(ri.issued_quantity, 0)) AS issued_qty,
                               MAX(ri.unit_of_measure) AS unit
                        FROM requisition_items ri
                        WHERE ri.requisition_id = ?
                          AND COALESCE(ri.issued_quantity, 0) > 0
                          AND (
                              ri.item_type IN ('raw_milk', 'pasteurized_milk', 'milk')
                              OR LOWER(COALESCE(ri.item_name, '')) LIKE '%milk%'
                          )
                    ");
                    try {
                        $issStmt->execute([(int) $run['linked_requisition_id']]);
                        $iss = $issStmt->fetch(PDO::FETCH_ASSOC);
                        if ($iss && (float) ($iss['issued_qty'] ?? 0) > 0) {
                            $qty = (float) $iss['issued_qty'];
                            $unit = strtolower((string) ($iss['unit'] ?? 'liters'));
                            // Convert to liters if unit is mL
                            if (in_array($unit, ['ml', 'milliliter', 'milliliters'], true)) {
                                $suggestedLiters = $qty / 1000;
                            } else {
                                $suggestedLiters = $qty; // assume liters
                            }
                            $suggestedSource = 'requisition_issued';
                        }
                    } catch (Throwable $e) {
                        // column names may vary; fall through to recipe plan
                    }
                }

                // 2) Planned milk on the run (set when run is created from recipe)
                if ($suggestedLiters === null && (float) ($run['milk_liters_used'] ?? 0) > 0) {
                    $suggestedLiters = (float) $run['milk_liters_used'];
                    $suggestedSource = 'recipe_plan';
                }

                // 3) Recipe base scaled by planned bulk volume (bulk_yield aware)
                if ($suggestedLiters === null
                    && (float) ($run['base_milk_liters'] ?? 0) > 0
                    && (float) ($run['planned_quantity'] ?? 0) > 0
                ) {
                    $suggestedLiters = calculateRequiredMilkLitersForPlan($run, $run['planned_quantity']);
                    $suggestedSource = 'recipe_scaled';
                }

                $run['suggested_initial_volume_liters'] = $suggestedLiters;
                $run['suggested_initial_volume_ml'] = $suggestedLiters !== null
                    ? (int) round($suggestedLiters * 1000)
                    : null;
                $run['suggested_volume_source'] = $suggestedSource;
                $run['initial_volume_liters'] = isset($run['initial_volume_ml']) && $run['initial_volume_ml'] !== null
                    ? round(((float) $run['initial_volume_ml']) / 1000, 3)
                    : null;

                // Best-effort pack config from product master (for complete-run unit display)
                $run['pieces_per_box'] = 1;
                $run['base_unit'] = 'piece';
                $run['box_unit'] = 'box';
                try {
                    $prodStmt = $db->prepare("
                        SELECT pieces_per_box, base_unit, box_unit, product_name
                        FROM products
                        WHERE is_active = 1
                          AND (
                            product_name = ?
                            OR product_name LIKE CONCAT(?, '%')
                            OR LOWER(REPLACE(product_name, ' ', '_')) = LOWER(?)
                          )
                        ORDER BY
                          CASE WHEN product_name = ? THEN 0 ELSE 1 END,
                          pieces_per_box DESC
                        LIMIT 1
                    ");
                    $pname = $run['product_name'] ?? '';
                    $prodStmt->execute([$pname, $pname, $pname, $pname]);
                    $prodPack = $prodStmt->fetch(PDO::FETCH_ASSOC);
                    if ($prodPack) {
                        $run['pieces_per_box'] = max(1, (int)($prodPack['pieces_per_box'] ?? 1));
                        $run['base_unit'] = $prodPack['base_unit'] ?: 'piece';
                        $run['box_unit'] = $prodPack['box_unit'] ?: 'box';
                        $run['product_pack_matched'] = $prodPack['product_name'];
                    }
                } catch (Throwable $e) {
                    // products table shape may vary
                }
                
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
            
            // Get runs (includes yield/volume fields for dashboard + yield tracking)
            $stmt = $db->prepare("
                SELECT pr.id, pr.run_code, pr.recipe_id, pr.status, pr.planned_quantity,
                       pr.actual_quantity, pr.output_breakdown, pr.milk_liters_used, pr.start_datetime, pr.end_datetime,
                       pr.yield_variance, pr.created_at,
                       pr.initial_volume_ml, pr.total_loss_ml, pr.total_byproduct_ml, pr.net_yield_ml,
                       pr.material_reconciled, pr.reconciliation_notes,
                       mr.recipe_code, mr.product_name, mr.product_type, mr.variant, mr.yield_unit,
                       (SELECT pb.qc_status FROM production_batches pb WHERE pb.run_id = pr.id ORDER BY pb.id DESC LIMIT 1) AS qc_batch_status,
                       (SELECT d.variance
                          FROM qc_batch_count_discrepancies d
                          JOIN production_batches pb2 ON pb2.id = d.batch_id
                         WHERE pb2.run_id = pr.id AND d.status = 'open'
                         ORDER BY d.id DESC LIMIT 1) AS qc_count_hold_variance
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
            if ($plannedQuantity <= 0) $errors['planned_quantity'] = 'Planned finished amount must be greater than 0 L';

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
                            'Planned finished amount must match the approved requisition (%s %s). ' .
                            'Edit the requisition to change the finished liters.',
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

            // A fulfilled/partial requisition is an approved historical snapshot.
            // Its exact recipe may have been retired after approval, so keep that
            // one path usable while new/direct runs require the current recipe.
            $historicalRecipeAllowed = $sourceRequisition
                && in_array($sourceRequisition['status'], ['fulfilled', 'partial'], true)
                && empty($sourceRequisition['production_run_id'])
                && (int) ($sourceRequisition['planned_recipe_id'] ?? 0) === (int) $recipeId;

            $recipeStmt = $db->prepare("SELECT * FROM master_recipes WHERE id = ?");
            $recipeStmt->execute([$recipeId]);
            $recipe = $recipeStmt->fetch();

            if (!$recipe) {
                $errors['recipe_id'] = 'Recipe not found';
            } elseif ((int) ($recipe['is_active'] ?? 0) !== 1 && !$historicalRecipeAllowed) {
                $errors['recipe_id'] = 'Recipe is inactive; choose the current recipe';
            } elseif (!isBulkProductionRecipe($recipe)) {
                $errors['recipe_id'] = 'Choose a bulk recipe with a base product and a liquid yield in liters';
            } elseif (!$historicalRecipeAllowed) {
                $currentRecipe = getCurrentActiveBulkRecipeForBase($db, $recipe['base_product_id']);
                if (!$currentRecipe || (int) $currentRecipe['id'] !== (int) $recipe['id']) {
                    $errors['recipe_id'] = $currentRecipe
                        ? 'Recipe was superseded; choose ' . $currentRecipe['recipe_code']
                        : 'This base product has no current bulk recipe';
                }
            }
            
            // Calculate required milk liters (bulk-batch aware).
            // planned_quantity is liquid volume (L); scale vs bulk_yield_liters
            // (fallback base_milk_liters / expected_yield) — same as requisitions.
            if ($recipe) {
                $requiredMilkLiters = calculateRequiredMilkLitersForPlan(
                    $recipe,
                    $plannedQuantity,
                    ($milkLitersUsed && (float) $milkLitersUsed > 0) ? $milkLitersUsed : null
                );
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
                
                $shrinkageTolerance = 0.95;
                if (empty($pasteurizedBatches)) {
                    $errors['milk_source'] = '⚠️ YOGURT requires PASTEURIZED MILK. No pasteurized milk available. Please run pasteurization first.';
                } else if ($totalAvailableLiters < $requiredMilkLiters * $shrinkageTolerance) {
                    $errors['milk_source'] = "⚠️ Not enough PASTEURIZED milk. Required: {$requiredMilkLiters}L (5% shrinkage allowed), Available: {$totalAvailableLiters}L. Please pasteurize more milk.";
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
                    // Starting is idempotent: a browser retry after a lost/error
                    // response should learn that the desired state was already
                    // reached instead of showing a misleading 400 error.
                    if ($run['status'] === 'in_progress') {
                        Response::success([
                            'status' => 'in_progress',
                            'initial_volume_ml' => (float) ($run['initial_volume_ml'] ?? 0),
                            'already_started' => true
                        ], 'Production run is already started');
                    }
                    if ($run['status'] !== 'planned') {
                        Response::error('Can only start a planned run', 400);
                    }

                    $initialVolumeMl = getParam('initial_volume_ml');

                    // Auto-fill from planned/issued milk if staff did not re-type it
                    if (!$initialVolumeMl || (float) $initialVolumeMl <= 0) {
                        if (!empty($run['initial_volume_ml']) && (float) $run['initial_volume_ml'] > 0) {
                            $initialVolumeMl = (float) $run['initial_volume_ml'];
                        } elseif (!empty($run['milk_liters_used']) && (float) $run['milk_liters_used'] > 0) {
                            $initialVolumeMl = (float) $run['milk_liters_used'] * 1000;
                        }
                    }

                    // Initialize optional packaging storage before opening the
                    // run transaction. Its schema check must never perform DDL
                    // from inside this transaction.
                    ensureSkuPackagingBomTable($db);

                    $db->beginTransaction();
                    try {
                        if ($initialVolumeMl && (float)$initialVolumeMl > 0) {
                            // Set initial volume and net yield (net = initial at start, no losses yet)
                            $stmt = $db->prepare("
                                UPDATE production_runs
                                SET status = 'in_progress', start_datetime = NOW(), started_by = ?,
                                    initial_volume_ml = ?, net_yield_ml = ?
                                WHERE id = ? AND status = 'planned'
                            ");
                            $stmt->execute([$currentUser['user_id'], (float)$initialVolumeMl, (float)$initialVolumeMl, $runId]);

                            // Auto-trigger Stage 1 packaging estimate
                            $estimateResult = generatePackagingEstimate($db, $runId, 'initial', (float)$initialVolumeMl);
                        } else {
                            $stmt = $db->prepare("
                                UPDATE production_runs
                                SET status = 'in_progress', start_datetime = NOW(), started_by = ?
                                WHERE id = ? AND status = 'planned'
                            ");
                            $stmt->execute([$currentUser['user_id'], $runId]);
                            $estimateResult = null;
                        }

                        $db->commit();
                    } catch (Throwable $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        throw $e;
                    }

                    $responseData = ['status' => 'in_progress'];
                    if ($initialVolumeMl) {
                        $responseData['initial_volume_ml'] = (float)$initialVolumeMl;
                        $responseData['auto_volume'] = true;
                    }
                    if ($estimateResult && $estimateResult['success']) {
                        $responseData['packaging_estimate'] = $estimateResult;
                    }

                    Response::success($responseData, 'Production run started');
                    break;
                    
                case 'set_volume':
                    // Set or update initial volume (and trigger/refresh packaging estimate)
                    $initialVolumeMl = getParam('initial_volume_ml');
                    if (!$initialVolumeMl || (float)$initialVolumeMl <= 0) {
                        Response::error('initial_volume_ml must be positive', 400);
                    }

                    if ($run['status'] === 'completed' || $run['status'] === 'cancelled') {
                        Response::error('Cannot set volume on a completed/cancelled run', 400);
                    }

                    $db->beginTransaction();

                    $netYield = (float)$initialVolumeMl - (float)($run['total_loss_ml'] ?? 0) - (float)($run['total_byproduct_ml'] ?? 0);
                    $stmt = $db->prepare("
                        UPDATE production_runs
                        SET initial_volume_ml = ?, net_yield_ml = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([(float)$initialVolumeMl, max(0, $netYield), $runId]);

                    // Generate initial packaging estimate
                    $estimateResult = generatePackagingEstimate($db, $runId, 'initial', (float)$initialVolumeMl);

                    $db->commit();

                    $responseData = [
                        'initial_volume_ml' => (float)$initialVolumeMl,
                        'net_yield_ml' => max(0, $netYield)
                    ];
                    if ($estimateResult && $estimateResult['success']) {
                        $responseData['packaging_estimate'] = $estimateResult;
                    }

                    Response::success($responseData, 'Initial volume set and packaging estimate generated');
                    break;

                case 'reconcile':
                    // Mark material reconciliation complete (optionally with override notes)
                    if ($run['status'] === 'cancelled') {
                        Response::error('Cannot reconcile a cancelled run', 400);
                    }

                    $reconNotes = trim(getParam('reconciliation_notes', ''));
                    $force = filter_var(getParam('force', false), FILTER_VALIDATE_BOOLEAN);

                    // Soft check: if initial volume set, verify unaccounted within tolerance unless force
                    if (!$force && !empty($run['initial_volume_ml'])) {
                        $finishedStmt = $db->prepare("
                            SELECT COALESCE(SUM(pe.actual_units * pe.packaging_size_ml), 0)
                            FROM packaging_estimates pe
                            WHERE pe.production_run_id = ? AND pe.actual_units IS NOT NULL
                        ");
                        $finishedStmt->execute([$runId]);
                        $finishedProductMl = (float) $finishedStmt->fetchColumn();
                        $initialVolume = (float) $run['initial_volume_ml'];
                        $totalLoss = (float) ($run['total_loss_ml'] ?? 0);
                        $totalByproduct = (float) ($run['total_byproduct_ml'] ?? 0);
                        $unaccounted = $initialVolume - ($finishedProductMl + $totalLoss + $totalByproduct);
                        $tolerance = max(50, $initialVolume * 0.01);

                        if (abs($unaccounted) > $tolerance && $reconNotes === '') {
                            Response::validationError([
                                'reconciliation_notes' => 'Unaccounted volume exceeds tolerance. Provide override notes or set force=true with notes.',
                                'unaccounted_ml' => round($unaccounted, 2),
                                'tolerance_ml' => $tolerance,
                            ]);
                        }
                    }

                    $stmt = $db->prepare("
                        UPDATE production_runs
                        SET material_reconciled = 1,
                            reconciliation_notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$reconNotes !== '' ? $reconNotes : null, $runId]);

                    Response::success([
                        'id' => (int) $runId,
                        'material_reconciled' => true,
                        'reconciliation_notes' => $reconNotes !== '' ? $reconNotes : null,
                    ], 'Material reconciliation recorded');
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

                case 'request_packaging':
                    if ($currentUser['role'] !== 'production_staff') {
                        Response::forbidden('Only Production staff can request packaging materials');
                    }
                    if ($run['status'] !== 'packaging') {
                        Response::validationError([
                            'run_status' => 'Move the run to the Packaging stage before requesting bottles, caps, labels, or wraps.'
                        ]);
                    }

                    $requestedPackagingItems = getParam('packaging_items', []);
                    if (!is_array($requestedPackagingItems) || !$requestedPackagingItems) {
                        Response::validationError([
                            'packaging_items' => 'Choose at least one packaging SKU and enter the quantity planned.'
                        ]);
                    }

                    // Server replaces all client product names/sizes and calculates
                    // the material quantities from the saved SKU Packaging BOM.
                    $packagingPlan = calculateSkuPackagingRequirements(
                        $db,
                        (int) $run['recipe_id'],
                        $requestedPackagingItems
                    );
                    if (!$packagingPlan['success']) {
                        Response::validationError($packagingPlan['errors']);
                    }
                    $plannedSkuItems = $packagingPlan['items'];
                    $packagingRequirements = $packagingPlan['requirements'];

                    $existingStmt = $db->prepare("
                        SELECT id, requisition_code, status
                        FROM material_requisitions
                        WHERE production_run_id = ? AND request_type = 'packaging'
                          AND status NOT IN ('cancelled', 'rejected')
                        ORDER BY id DESC LIMIT 1
                    ");
                    $existingStmt->execute([$runId]);
                    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
                    if ($existing) {
                        Response::success([
                            'requisition_id' => (int) $existing['id'],
                            'requisition_code' => $existing['requisition_code'],
                            'status' => $existing['status'],
                            'already_requested' => true,
                        ], $existing['requisition_code'] . ' already covers this run. Open the existing Warehouse request.');
                    }

                    // The original cooking request proves the run already passed GM
                    // approval. Packaging therefore goes directly to Warehouse.
                    $sourceStmt = $db->prepare("
                        SELECT id, approved_by
                        FROM material_requisitions
                        WHERE production_run_id = ?
                          AND COALESCE(request_type, 'cooking') = 'cooking'
                          AND status = 'fulfilled'
                        ORDER BY id DESC LIMIT 1
                    ");
                    $sourceStmt->execute([$runId]);
                    $sourceRequest = $sourceStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$sourceRequest) {
                        Response::validationError([
                            'source_requisition' => 'This run has no fulfilled cooking-material request. Warehouse must issue the approved cooking materials first.'
                        ]);
                    }

                    $db->beginTransaction();
                    try {
                        $requisitionCode = 'PKG-REQ-' . str_pad((string) $runId, 6, '0', STR_PAD_LEFT);
                        $insertReq = $db->prepare("
                            INSERT INTO material_requisitions (
                                requisition_code, production_run_id, request_type, packaging_plan_json,
                                planned_recipe_id, planned_quantity, planned_yield_unit,
                                requested_by, department, priority, purpose, total_items,
                                status, approved_by, approved_at
                            ) VALUES (?, ?, 'packaging', ?, ?, ?, 'liters', ?, 'production',
                                      'normal', ?, ?, 'approved', ?, NOW())
                        ");
                        $insertReq->execute([
                            $requisitionCode,
                            $runId,
                            json_encode($plannedSkuItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            (int) $run['recipe_id'],
                            (float) ($run['planned_quantity'] ?? 0),
                            $currentUser['user_id'],
                            'Packaging materials for ' . ($run['run_code'] ?? ('run #' . $runId)),
                            count($packagingRequirements),
                            $sourceRequest['approved_by'] ?: null,
                        ]);
                        $packagingRequisitionId = (int) $db->lastInsertId();

                        $insertItem = $db->prepare("
                            INSERT INTO requisition_items (
                                requisition_id, item_type, item_id, item_code, item_name,
                                requested_quantity, unit_of_measure, status, notes
                            ) VALUES (?, 'packaging', ?, ?, ?, ?, ?, 'pending', ?)
                        ");
                        foreach ($packagingRequirements as $requirement) {
                            $insertItem->execute([
                                $packagingRequisitionId,
                                (int) $requirement['ingredient_id'],
                                $requirement['ingredient_code'] ?? null,
                                $requirement['ingredient_name'],
                                (float) $requirement['quantity_required'],
                                $requirement['unit'],
                                'Calculated from the saved SKU Packaging BOM',
                            ]);
                        }

                        logAudit(
                            $currentUser['user_id'],
                            'request_packaging_materials',
                            'material_requisitions',
                            $packagingRequisitionId,
                            null,
                            [
                                'run_id' => (int) $runId,
                                'status' => 'approved',
                                'source_requisition_id' => (int) $sourceRequest['id'],
                            ]
                        );
                        $db->commit();

                        Response::success([
                            'requisition_id' => $packagingRequisitionId,
                            'requisition_code' => $requisitionCode,
                            'status' => 'approved',
                            'packaging_plan' => $plannedSkuItems,
                            'requirements' => $packagingRequirements,
                        ], 'Packaging request sent to Warehouse. Complete the run after Warehouse issues every material.');
                    } catch (Throwable $e) {
                        if ($db->inTransaction()) $db->rollBack();
                        throw $e;
                    }
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
                    $reconNotes = trim(getParam('reconciliation_notes', ''));

                    // =====================================================
                    // V4.1 — PACKAGING ITEMS (merged into run completion)
                    // Production reports what they physically packed:
                    // e.g., [{product_id:2, size_ml:500, quantity:298, ...}]
                    // This replaces the old post-QC packaging step.
                    // =====================================================
                    $packagingItems = getParam('packaging_items', null);
                    $processLossMl = (float) getParam('process_loss_ml', 0);

                    // Decode JSON string if sent as form-encoded
                    if (is_string($packagingItems)) {
                        $decoded = json_decode($packagingItems, true);
                        $packagingItems = is_array($decoded) ? $decoded : null;
                    }

                    if (!is_array($packagingItems) || count($packagingItems) === 0) {
                        Response::validationError([
                            'packaging_items' => 'Allocate the actual yield to at least one configured packaging SKU'
                        ]);
                    }

                    // Product identity, size, and packaging BOM all come from the
                    // SKU master. Never trust client-supplied names or bottle sizes.
                    $packagingPlan = calculateSkuPackagingRequirements(
                        $db,
                        $run['recipe_id'],
                        $packagingItems
                    );
                    if (!$packagingPlan['success']) {
                        Response::validationError($packagingPlan['errors']);
                    }
                    $packagingItems = $packagingPlan['items'];
                    $packagingMaterialRequirements = $packagingPlan['requirements'];

                    // Packaging stock must already have been physically issued by
                    // Warehouse. Completion records usage but never deducts it a
                    // second time.
                    $packReqStmt = $db->prepare("
                        SELECT id, requisition_code, status, packaging_plan_json
                        FROM material_requisitions
                        WHERE production_run_id = ? AND request_type = 'packaging'
                          AND status NOT IN ('cancelled', 'rejected')
                        ORDER BY id DESC LIMIT 1
                    ");
                    $packReqStmt->execute([$runId]);
                    $packReq = $packReqStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$packReq) {
                        Response::validationError([
                            'packaging_request' => 'Send the packaging-material request to Warehouse before completing this run.'
                        ]);
                    }
                    if ($packReq['status'] !== 'fulfilled') {
                        Response::validationError([
                            'packaging_request' => $packReq['requisition_code'] . ' is still ' . $packReq['status'] . '. Warehouse must issue every packaging material first.'
                        ]);
                    }

                    $requestedSkuItems = json_decode((string) ($packReq['packaging_plan_json'] ?? ''), true) ?: [];
                    $requestedBySku = [];
                    foreach ($requestedSkuItems as $item) {
                        $requestedBySku[(int) ($item['product_id'] ?? 0)] = (int) ($item['quantity'] ?? 0);
                    }
                    $planErrors = [];
                    foreach ($packagingItems as $idx => $item) {
                        $productId = (int) ($item['product_id'] ?? 0);
                        $actualQty = (int) ($item['quantity'] ?? 0);
                        $requestedQty = (int) ($requestedBySku[$productId] ?? 0);
                        if ($requestedQty <= 0) {
                            $planErrors["packaging_items.$idx.product_id"] = 'This SKU was not included in the Warehouse packaging request';
                        } elseif ($actualQty > $requestedQty) {
                            $planErrors["packaging_items.$idx.quantity"] = "Warehouse issued packaging for {$requestedQty} unit(s), but {$actualQty} were entered";
                        }
                    }
                    $requestedFinishedUnits = array_sum(array_map(
                        static fn($item) => (int) ($item['quantity'] ?? 0),
                        $requestedSkuItems
                    ));
                    $actualFinishedUnits = array_sum(array_map(
                        static fn($item) => (int) ($item['quantity'] ?? 0),
                        $packagingItems
                    ));
                    if ($actualFinishedUnits < $requestedFinishedUnits && trim((string) $reconNotes) === '') {
                        $planErrors['reconciliation_notes'] = sprintf(
                            'Warehouse issued packaging for %d finished unit(s), but Production recorded %d. Explain damaged or unused packaging in Notes.',
                            $requestedFinishedUnits,
                            $actualFinishedUnits
                        );
                    }
                    if ($planErrors) {
                        Response::validationError($planErrors);
                    }

                    $issuedStmt = $db->prepare("
                        SELECT item_id, SUM(issued_quantity) AS issued_quantity
                        FROM requisition_items
                        WHERE requisition_id = ? AND item_type = 'packaging'
                        GROUP BY item_id
                    ");
                    $issuedStmt->execute([(int) $packReq['id']]);
                    $issuedByMaterial = [];
                    foreach ($issuedStmt->fetchAll(PDO::FETCH_ASSOC) as $issued) {
                        $issuedByMaterial[(int) $issued['item_id']] = (float) $issued['issued_quantity'];
                    }
                    $issueErrors = [];
                    foreach ($packagingMaterialRequirements as $idx => $requirement) {
                        $ingredientId = (int) $requirement['ingredient_id'];
                        $needed = (float) $requirement['quantity_required'];
                        $issued = (float) ($issuedByMaterial[$ingredientId] ?? 0);
                        if ($issued + 0.0001 < $needed) {
                            $issueErrors["packaging_materials.$idx"] = sprintf(
                                '%s needs %.3f %s but Warehouse issued %.3f',
                                $requirement['ingredient_name'],
                                $needed,
                                $requirement['unit'],
                                $issued
                            );
                        }
                    }
                    if ($issueErrors) {
                        Response::validationError($issueErrors);
                    }

                    // Validate packaging items when provided
                    $packagingErrors = [];
                    $totalPackagedPieces = 0;
                    $totalPackagedVolumeMl = 0;
                    if (is_array($packagingItems) && count($packagingItems) > 0) {
                        foreach ($packagingItems as $idx => $pItem) {
                            $pQty = (int) ($pItem['quantity'] ?? 0);
                            if ($pQty <= 0) {
                                $packagingErrors["packaging_items.{$idx}.quantity"] = 'Quantity must be greater than 0';
                                continue;
                            }
                            $totalPackagedPieces += $pQty;
                            $pSizeMl = (float) ($pItem['size_ml'] ?? 0);
                            if ($pSizeMl > 0) {
                                $totalPackagedVolumeMl += $pSizeMl * $pQty;
                            }
                        }
                    }
                    if (!empty($packagingErrors)) {
                        Response::validationError($packagingErrors);
                    }

                    // When packaging items are provided, they define the actual output
                    if ($totalPackagedPieces > 0) {
                        $actualQuantity = $totalPackagedPieces;
                    }
                    
                    // Fallback: use packaging estimate units when actual not provided
                    if ($actualQuantity <= 0) {
                        $estQtyStmt = $db->prepare("
                            SELECT COALESCE(SUM(COALESCE(actual_units, estimated_units)), 0)
                            FROM packaging_estimates
                            WHERE production_run_id = ?
                              AND estimate_type = (
                                  SELECT IF(
                                      EXISTS(SELECT 1 FROM packaging_estimates pe2
                                             WHERE pe2.production_run_id = ? AND pe2.estimate_type = 'revised'),
                                      'revised', 'initial'
                                  )
                              )
                        ");
                        $estQtyStmt->execute([$runId, $runId]);
                        $actualQuantity = (int) $estQtyStmt->fetchColumn();
                    }

                    if ($actualQuantity <= 0 && (int) ($run['planned_quantity'] ?? 0) > 0) {
                        $actualQuantity = (int) $run['planned_quantity'];
                    }

                    if ($actualQuantity <= 0) {
                        Response::validationError(['actual_quantity' => 'Actual quantity is required']);
                    }
                    
                    // Recipe product type (byproduct rules only — NOT pack conversion)
                    $recipeStmt = $db->prepare("SELECT product_type FROM master_recipes WHERE id = ?");
                    $recipeStmt->execute([$run['recipe_id']]);
                    $recipeInfo = $recipeStmt->fetch();
                    $productType = $recipeInfo['product_type'] ?? 'bottled_milk';

                    // =====================================================
                    // MULTI-UNIT OUTPUT — ALWAYS from product master UOM
                    // products.pieces_per_box / box_unit / base_unit
                    // NEVER hardcode 24 crates / 50 boxes by product_type.
                    // =====================================================

                    $packProductId = (int)(
                        $run['product_id']
                        ?? $run['recipe_product_id']
                        ?? $run['base_product_id']
                        ?? 0
                    );
                    // Prefer an active SKU under this recipe product when base liquid has no pack size
                    if ($packProductId > 0) {
                        $packCfg = hf_get_product_pack_config($db, $packProductId);
                        if ($packCfg['units_per_pack'] <= 1) {
                            try {
                                $skuStmt = $db->prepare("
                                    SELECT id FROM products
                                    WHERE (base_product_id = ? OR id = ?)
                                      AND COALESCE(pieces_per_box, 1) > 1
                                      AND COALESCE(is_active, 1) = 1
                                    ORDER BY pieces_per_box DESC, id ASC
                                    LIMIT 1
                                ");
                                $skuStmt->execute([$packProductId, $packProductId]);
                                $skuId = (int)$skuStmt->fetchColumn();
                                if ($skuId > 0) {
                                    $packProductId = $skuId;
                                    $packCfg = hf_get_product_pack_config($db, $packProductId);
                                }
                            } catch (Exception $e) { /* keep packCfg */ }
                        }
                    } else {
                        $packCfg = hf_pack_config_from_row(null);
                    }

                    $conversionFactor = max(1, (int)$packCfg['units_per_pack']);
                    $primaryUnit = $packCfg['base_unit'] ?: 'piece';
                    $secondaryUnit = $packCfg['pack_name'] ?: 'box';

                    // Calculate total base units and pack breakdown
                    $totalPieces = $actualQuantity;

                    // If operator entered pack count (boxes/crates/cases), convert to base units
                    $packUnitAliases = array_unique(array_filter([
                        'boxes', 'box', 'crates', 'crate', 'cases', 'case', 'trays', 'tray',
                        $secondaryUnit,
                        rtrim($secondaryUnit, 's'),
                        $secondaryUnit . 's',
                    ]));
                    if (in_array(strtolower((string)$outputUnit), array_map('strtolower', $packUnitAliases), true)) {
                        $totalPieces = $actualQuantity * $conversionFactor;
                    }

                    $split = hf_split_base_to_pack($totalPieces, $conversionFactor);
                    $secondaryCount = $split['packs'];
                    $remainingPrimary = $split['loose'];

                    $variance = $totalPieces - $run['planned_quantity'];

                    // Store output breakdown as JSON (product-master driven)
                    $outputBreakdown = json_encode([
                        'total_pieces' => $totalPieces,
                        'secondary_count' => $secondaryCount,
                        'secondary_unit' => $secondaryUnit,
                        'remaining_primary' => $remainingPrimary,
                        'primary_unit' => $primaryUnit,
                        'input_quantity' => $actualQuantity,
                        'input_unit' => $outputUnit,
                        'conversion_factor' => $conversionFactor,
                        'units_per_pack' => $conversionFactor,
                        'pack_name' => $secondaryUnit,
                        'base_unit' => $primaryUnit,
                        'product_id' => $packProductId,
                        'pack_formula' => format_pack_config_line($packCfg),
                    ]);
                    
                    $packagingMaterialsConsumed = [];
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
                                variance_reason = ?,
                                material_reconciled = CASE WHEN ? = 1 OR material_reconciled = 1 THEN 1 ELSE material_reconciled END,
                                reconciliation_notes = COALESCE(?, reconciliation_notes)
                            WHERE id = ?
                        ");
                        $markReconciled = $reconNotes !== '' ? 1 : 0;
                        $stmt->execute([
                            $currentUser['user_id'],
                            $totalPieces,
                            $outputBreakdown,
                            $variance,
                            $varianceReason,
                            $markReconciled,
                            $reconNotes !== '' ? $reconNotes : null,
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
                        
                        // Expiry from recipe/product shelf_life_days (not hard-coded product-type map).
                        // Butter recipe stores 30; products.shelf_life_days and base_products also 30.
                        $expiryDays = 0;
                        if (!empty($run['shelf_life_days']) && (int) $run['shelf_life_days'] > 0) {
                            $expiryDays = (int) $run['shelf_life_days'];
                        } else {
                            try {
                                $slStmt = $db->prepare("
                                    SELECT COALESCE(
                                        NULLIF(p.shelf_life_days, 0),
                                        NULLIF(mr.shelf_life_days, 0),
                                        NULLIF(bp.default_shelf_life_days, 0),
                                        7
                                    ) AS days
                                    FROM master_recipes mr
                                    LEFT JOIN products p ON p.id = mr.product_id
                                    LEFT JOIN base_products bp ON bp.id = mr.base_product_id
                                    WHERE mr.id = ?
                                    LIMIT 1
                                ");
                                $slStmt->execute([(int) $run['recipe_id']]);
                                $expiryDays = (int) ($slStmt->fetchColumn() ?: 7);
                            } catch (Throwable $e) {
                                $expiryDays = 7;
                            }
                        }
                        if ($expiryDays <= 0) {
                            $expiryDays = 7;
                        }

                        $expiryDate = date('Y-m-d', strtotime("+{$expiryDays} days"));
                        
                        // Pull verified CCP temperatures from production logs so QC
                        // sees actual production inputs without re-entry.
                        $ccpTemps = ccp_extract_temps_from_logs($ccpLogs);
                        $pasteurizationTemp = $ccpTemps['pasteurization_temp'];
                        $coolingTemp = $ccpTemps['cooling_temp'];

                        // Create batch record for QC verification (includes denormalized CCP temps)
                        $batchStmt = $db->prepare("
                            INSERT INTO production_batches (
                                batch_code, recipe_id, run_id, milk_type_id, product_type, manufacturing_date,
                                raw_milk_liters, expected_yield, actual_yield, qc_status, created_by, expiry_date,
                                pasteurization_temp, cooling_temp
                            ) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, 'pending', ?, ?, ?, ?)
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
                            $expiryDate,
                            $pasteurizationTemp,
                            $coolingTemp
                        ]);
                        
                        $batchId = $db->lastInsertId();

                        // =====================================================
                        // V4.1 — CREATE PACKAGING RECORDS AT COMPLETION
                        // Production reports what they physically packed.
                        // This happens BEFORE QC so QC can verify counts.
                        // FG inventory is NOT created here — only at warehouse receive.
                        // =====================================================
                        $packagingRunId = null;
                        if (is_array($packagingItems) && count($packagingItems) > 0) {
                            // Get recipe product name for fallback
                            $recipeProductName = $productType;
                            try {
                                $rpnStmt = $db->prepare("SELECT product_name FROM master_recipes WHERE id = ? LIMIT 1");
                                $rpnStmt->execute([(int) $run['recipe_id']]);
                                $rpn = $rpnStmt->fetchColumn();
                                if ($rpn) $recipeProductName = $rpn;
                            } catch (Throwable $e) { /* use productType fallback */ }

                            // Generate packaging code
                            $pkgToday = date('Ymd');
                            $maxPkgStmt = $db->prepare("
                                SELECT COALESCE(MAX(CAST(SUBSTRING(packaging_code, -3) AS UNSIGNED)), 0)
                                FROM packaging_runs
                                WHERE packaging_code LIKE ?
                            ");
                            $maxPkgStmt->execute(["PKG-{$pkgToday}-%"]);
                            $pkgSeq = (int) $maxPkgStmt->fetchColumn() + 1;
                            $packagingCode = "PKG-{$pkgToday}-" . str_pad($pkgSeq, 3, '0', STR_PAD_LEFT);

                            $pkgInsStmt = $db->prepare("
                                INSERT INTO packaging_runs
                                    (packaging_code, production_run_id, batch_id, batch_code,
                                     product_type, total_pieces_packaged, packaging_date,
                                     packaged_by, notes, process_loss_ml, status)
                                VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, 'completed')
                            ");
                            $pkgInsStmt->execute([
                                $packagingCode,
                                $runId,
                                $batchId,
                                $batchCode,
                                $productType,
                                $totalPackagedPieces,
                                $currentUser['user_id'],
                                'Recorded at run completion (V4.1 flow)',
                                $processLossMl
                            ]);
                            $packagingRunId = (int) $db->lastInsertId();

                            // Insert individual packaging line items
                            $pkgItemStmt = $db->prepare("
                                INSERT INTO packaging_run_items
                                    (packaging_run_id, product_id, product_name, product_variant,
                                     size_ml, unit_measure, quantity, fg_inventory_id)
                                VALUES (?, ?, ?, ?, ?, ?, ?, NULL)
                            ");

                            foreach ($packagingItems as $pItem) {
                                $pProductId = !empty($pItem['product_id']) ? (int) $pItem['product_id'] : null;
                                $pName = $pItem['product_name'] ?? $recipeProductName;
                                $pVariant = $pItem['product_variant'] ?? null;
                                $pSizeMl = isset($pItem['size_ml']) && $pItem['size_ml'] !== '' ? (float) $pItem['size_ml'] : null;
                                $pUnitMeasure = $pItem['unit_measure'] ?? 'ml';
                                $pQty = (int) $pItem['quantity'];

                                // Auto-generate variant from size when empty
                                if (($pVariant === null || $pVariant === '') && $pSizeMl > 0) {
                                    $pVariant = rtrim(rtrim(number_format($pSizeMl, 0, '.', ''), '0'), '.') . ($pUnitMeasure ?: 'ml');
                                }

                                $pkgItemStmt->execute([
                                    $packagingRunId,
                                    $pProductId,
                                    $pName,
                                    $pVariant,
                                    $pSizeMl,
                                    $pUnitMeasure,
                                    $pQty
                                ]);
                            }

                            // Link packaging run to the batch
                            $db->prepare("
                                UPDATE production_batches SET packaging_run_id = ? WHERE id = ?
                            ")->execute([$packagingRunId, $batchId]);

                            error_log("Production run {$run['run_code']}: Created packaging run {$packagingCode} with " . count($packagingItems) . " items ({$totalPackagedPieces} pieces)");
                        }

                        // Warehouse already reduced stock when it physically issued
                        // PKG-REQ. Record actual usage for traceability only.
                        $packagingMaterialsConsumed = [];
                        $packagingUsageStmt = $db->prepare("
                            INSERT INTO ingredient_consumption
                                (run_id, ingredient_id, ingredient_name, quantity_used, unit, batch_code, notes)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        foreach ($packagingMaterialRequirements as $requirement) {
                            $packagingUsageStmt->execute([
                                $runId,
                                (int) $requirement['ingredient_id'],
                                $requirement['ingredient_name'],
                                (float) $requirement['quantity_required'],
                                $requirement['unit'],
                                $batchCode,
                                'Packaging usage; stock issued earlier by Warehouse through ' . $packReq['requisition_code'],
                            ]);
                            $packagingMaterialsConsumed[] = $requirement;
                        }
                        
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
                            'packaging_run_id' => $packagingRunId,
                            'packaging_items_count' => is_array($packagingItems) ? count($packagingItems) : 0,
                            'packaging_material_requirements' => $packagingMaterialRequirements,
                            'packaging_materials_consumed' => $packagingMaterialsConsumed,
                            'packaging_requisition_id' => (int) $packReq['id'],
                            'packaging_requisition_code' => $packReq['requisition_code'],
                            'packaging_stock_deducted_by_warehouse' => true,
                            'process_loss_ml' => $processLossMl,
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
                        
                    } catch (SkuPackagingStockException $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        Response::validationError($e->getValidationErrors());
                    } catch (Exception $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
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
