<?php
/**
 * Highland Fresh System - Ingredient Requisitions API
 *
 * Handles ingredient requests from Production to Warehouse Raw.
 *
 * Workflow (V4.0 — no GM approval gate):
 *   1. Production staff creates a requisition     -> status 'pending'
 *   2. Production staff explicitly acknowledges
 *      any stock shortage on submit (audit row in
 *      requisition_stock_warnings)                -> status still 'pending'
 *   3. Warehouse Raw sees the 'pending' request
 *      immediately and fulfills / partially
 *      fulfills it                                -> status 'fulfilled' / 'partial'
 *
 * GET    - List requisitions / Get single requisition
 * POST   - Create new requisition (with stock validation gate)
 * PUT    - Cancel pending requisition (own only)
 *
 * Approve / Reject are no longer exposed here. The 'approved' status is kept
 * in the enum for legacy rows but is not set by new requisitions.
 *
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Production, GM, or Warehouse Raw role.
//   - production_staff: create + cancel own pending
//   - warehouse_raw: read (so they see pending requests immediately)
//   - general_manager: read-only oversight
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

/**
 * Idempotent runtime migration for the stock-validation feature added in V4.0.
 * Mirrors sql/add_requisition_stock_validation.sql so fresh installs work
 * without a manual SQL step. Safe to call on every request — each ALTER /
 * CREATE is guarded by auditColumnExists / auditTableExists.
 */
function ensureStockValidationTables($db) {
    if (!auditColumnExists($db, 'material_requisitions', 'stock_override_acknowledged')) {
        $db->exec("ALTER TABLE material_requisitions
            ADD COLUMN stock_override_acknowledged TINYINT(1) NOT NULL DEFAULT 0
                COMMENT '1 = requester explicitly acknowledged a stock shortage on submit',
            ADD COLUMN stock_override_by INT NULL
                COMMENT 'FK to users — who acknowledged the shortage',
            ADD COLUMN stock_override_reason VARCHAR(255) NULL
                COMMENT 'Free-text reason for the override',
            ADD COLUMN stock_override_at DATETIME NULL
                COMMENT 'When the override was acknowledged'");
    }

    // V4.0 — pack-traceability columns on requisition_items. When the
    // requester uses the "Request as Packs" mode in the form, we record
    // both the pack count they asked for AND a snapshot of the ingredient's
    // pack_size_value at submit time. The snapshot is what makes the audit
    // row meaningful even if the catalog pack size changes later.
    if (!auditColumnExists($db, 'requisition_items', 'requested_quantity_in_packs')) {
        $db->exec("ALTER TABLE requisition_items
            ADD COLUMN requested_quantity_in_packs DECIMAL(10,3) NULL
                COMMENT 'Pack count the requester asked for (null = requested in base units)',
            ADD COLUMN pack_size_at_submit DECIMAL(10,3) NULL
                COMMENT 'Snapshot of ingredients.pack_size_value at submit time'");
    }

    // V4.0 — pack-integrity enforcement (B). Per-item "yes I know I'm
    // opening a pack" flag, written only when enforce_whole_packs=true on
    // the ingredient and the requester chose to submit a fractional pack
    // count instead of rounding up.
    if (!auditColumnExists($db, 'requisition_items', 'break_pack_acknowledged')) {
        $db->exec("ALTER TABLE requisition_items
            ADD COLUMN break_pack_acknowledged TINYINT(1) NOT NULL DEFAULT 0
                COMMENT '1 = requester explicitly chose to break a pack instead of rounding up',
            ADD COLUMN break_pack_acknowledged_reason VARCHAR(255) NULL
                COMMENT 'Free-text reason when break_pack_acknowledged=1'");
    }

    // V4.0 — per-ingredient opt-in for whole-pack enforcement. Defaults
    // to 0 so legacy ingredients keep their existing behavior. The prof
    // / warehouse_raw can flip this on for ingredients that physically
    // ship in sealed packs (cellophane, sugar sachets, etc.) once the
    // pack_size_value is configured.
    if (!auditColumnExists($db, 'ingredients', 'enforce_whole_packs')) {
        $db->exec("ALTER TABLE ingredients
            ADD COLUMN enforce_whole_packs TINYINT(1) NOT NULL DEFAULT 0
                COMMENT '1 = requests for this ingredient must be in whole packs (or break_pack_acknowledged)'");
    }

    if (!auditTableExists($db, 'requisition_stock_warnings')) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS requisition_stock_warnings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                requisition_id INT NOT NULL,
                requisition_item_id INT NULL,
                ingredient_id INT NULL,
                item_name VARCHAR(150) NOT NULL,
                requested_qty DECIMAL(10,3) NOT NULL,
                available_qty DECIMAL(10,3) NOT NULL,
                shortage DECIMAL(10,3) NOT NULL,
                decision ENUM('blocked','overridden') NOT NULL,
                decided_by INT NULL,
                decided_role VARCHAR(40) NULL,
                override_reason VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_rsw_requisition (requisition_id),
                INDEX idx_rsw_ingredient (ingredient_id),
                INDEX idx_rsw_decision (decision),
                INDEX idx_rsw_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

/**
 * Compute available stock for a list of requisition items.
 *
 * Returns an array of shortages with the shape:
 *   [
 *     { item_index, item_name, item_id, item_type, requested, available, shortage, sufficient },
 *     ...
 *   ]
 *
 * - For ingredient items we use the same query the warehouse check_stock
 *   endpoint uses: max(SUM(ingredient_batches.remaining_quantity),
 *   ingredients.current_stock). This is the most accurate available figure
 *   given FIFO issuance + reconciliation.
 * - For raw_milk items we use the same SUM(remaining_liters) from
 *   raw_milk_inventory that the warehouse uses for issuance.
 * - Items without a resolvable item_id (free-text from the legacy form) are
 *   reported as 'unknown' with shortage = 0 and insufficient metadata —
 *   they never trigger a stock block, but they are listed in the response so
 *   the UI can show "could not verify stock for this item".
 */
function checkRequisitionStock($db, $items) {
    $shortages = [];
    $allSufficient = true;

    foreach ($items as $index => $item) {
        $itemType = $item['item_type'] ?? null;
        $itemId = (int) ($item['item_id'] ?? 0);
        $itemName = trim($item['item_name'] ?? '');
        $requestedRaw = (float) ($item['quantity'] ?? 0);
        $requestedPacks = isset($item['quantity_in_packs']) && $item['quantity_in_packs'] !== null && $item['quantity_in_packs'] !== ''
            ? (float) $item['quantity_in_packs']
            : null;

        // Normalize item_type using the same patterns the POST handler uses,
        // so a free-text "Raw Milk" name gets the right stock lookup.
        if (!$itemType) {
            $lower = strtolower($itemName);
            $rawMilkPatterns = ['raw milk', 'fresh milk', 'carabao milk', 'cow milk', 'goat milk', 'whole milk'];
            $isRawMilk = false;
            foreach ($rawMilkPatterns as $p) {
                if ($lower === $p || strpos($lower, $p) !== false) { $isRawMilk = true; break; }
            }
            if (!$isRawMilk && strpos($lower, 'milk') !== false) {
                $exclude = ['powder', 'chocolate', 'flavored', 'pasteurized', 'skim', 'condensed', 'evaporated'];
                $blocked = false;
                foreach ($exclude as $e) { if (strpos($lower, $e) !== false) { $blocked = true; break; } }
                if (!$blocked) $isRawMilk = true;
            }
            $itemType = $isRawMilk ? 'raw_milk' : 'ingredient';
        }

        $available = 0.0;
        $verified = true;
        $packSize = null;

        if ($itemType === 'ingredient' && $itemId > 0) {
            // Pull current_stock + batch_stock + pack_size_value in one
            // round-trip so we can both compare against stock AND convert
            // any quantity_in_packs into base units.
            $stmt = $db->prepare("
                SELECT i.current_stock, i.pack_size_value,
                       (SELECT COALESCE(SUM(remaining_quantity), 0)
                          FROM ingredient_batches ib
                         WHERE ib.ingredient_id = i.id
                           AND ib.status IN ('available', 'partially_used')) AS batch_stock
                FROM ingredients i
                WHERE i.id = ?
            ");
            $stmt->execute([$itemId]);
            $row = $stmt->fetch();
            if (!$row) {
                $available = 0.0;
                $verified = false;
            } else {
                $available = max((float) $row['current_stock'], (float) $row['batch_stock']);
                $packSize = $row['pack_size_value'] !== null ? (float) $row['pack_size_value'] : null;
            }
        } elseif ($itemType === 'raw_milk') {
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(remaining_liters), 0) AS available_liters
                FROM raw_milk_inventory
                WHERE status IN ('available', 'reserved')
                  AND remaining_liters > 0
            ");
            $stmt->execute();
            $available = (float) ($stmt->fetch()['available_liters'] ?? 0);
        } else {
            // Free-text item with no resolvable id — cannot verify.
            $verified = false;
        }

        // V4.0 — pack conversion. If the requester asked for N packs and
        // the ingredient has a pack_size, the base quantity to compare
        // against stock is N * pack_size. If pack_size is missing or
        // the requester didn't ask in packs, fall back to the raw
        // quantity (today's behavior).
        $packConversionOk = true;
        $requestedBase = $requestedRaw;
        if ($requestedPacks !== null && $packSize !== null && $packSize > 0) {
            $requestedBase = round($requestedPacks * $packSize, 3);
        } elseif ($requestedPacks !== null) {
            // Requester asked for packs but the ingredient has no pack
            // config — we can't convert. Mark unverified so the gate
            // doesn't false-positive, and surface the mismatch in the
            // response so the UI can warn the user.
            $packConversionOk = false;
            $verified = false;
        }

        $sufficient = $verified && $requestedBase <= $available;
        $shortage = $sufficient ? 0.0 : max(0.0, $requestedBase - $available);

        if (!$sufficient) $allSufficient = false;

        $shortages[] = [
            'item_index' => $index,
            'item_id' => $itemId > 0 ? $itemId : null,
            'item_type' => $itemType,
            'item_name' => $itemName,
            'requested' => $requestedBase,
            'requested_packs' => $requestedPacks,
            'requested_raw' => $requestedRaw,
            'pack_size' => $packSize,
            'pack_conversion_ok' => $packConversionOk,
            'available' => $available,
            'shortage' => $shortage,
            'sufficient' => $sufficient,
            'verified' => $verified,
        ];
    }

    return [
        'all_sufficient' => $allSufficient,
        'items' => $shortages,
    ];
}

/**
 * Persist stock-validation decisions to the audit table. Called both when a
 * shortage is blocked (decision = 'blocked') and when the requester
 * acknowledges the override (decision = 'overridden').
 */
function logStockValidationDecisions($db, $requisitionId, $shortages, $decision, $userId, $role, $reason = null) {
    if (empty($shortages)) return;

    $insert = $db->prepare("
        INSERT INTO requisition_stock_warnings
            (requisition_id, requisition_item_id, ingredient_id, item_name,
             requested_qty, available_qty, shortage, decision,
             decided_by, decided_role, override_reason, created_at)
        VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($shortages as $s) {
        if ($s['sufficient'] && $s['verified']) {
            // No need to log a "no shortage, sufficient, verified" row —
            // it would just bloat the audit table. Skip.
            continue;
        }
        $insert->execute([
            $requisitionId,
            $s['item_id'] ?? null,
            $s['item_name'] ?: 'Unknown item',
            $s['requested'],
            $s['available'],
            $s['shortage'],
            $decision,
            $userId,
            $role,
            $reason,
        ]);
    }
}

/**
 * V4.0 (Option B) — Check whether any submitted item is a fractional pack
 * count for an ingredient that has enforce_whole_packs = 1. Returns the
 * list of offending items (so the UI can offer "round up" or "acknowledge
 * pack break") or an empty list if everything is integral.
 *
 * Math note (for the prof): an item is "fractional" if its effective
 * pack count is not an integer. The effective pack count is either:
 *   - quantity_in_packs if the requester used pack mode, OR
 *   - quantity / pack_size_value if they used base mode (the implicit
 *     pack count of their base submission)
 *
 * In both cases the check is `(effective != floor(effective))` with an
 * epsilon of 0.0005 to absorb floating-point noise.
 *
 * Only ingredient items with a configured pack_size and the
 * enforce_whole_packs flag set are checked. Raw milk, free-text items,
 * and ingredients without pack config are skipped.
 */
function checkRequisitionPackIntegrity($db, $items) {
    $lookup = [];
    $ids = [];
    foreach ($items as $item) {
        $id = (int) ($item['item_id'] ?? 0);
        $type = $item['item_type'] ?? null;
        if ($id > 0 && $type === 'ingredient') {
            $ids[$id] = true;
        }
    }
    if (!empty($ids)) {
        $idList = implode(',', array_map('intval', array_keys($ids)));
        $stmt = $db->query("SELECT id, ingredient_name, pack_size_value, pack_size_unit, pack_label, enforce_whole_packs FROM ingredients WHERE id IN ({$idList})");
        foreach ($stmt->fetchAll() as $row) {
            $lookup[(int) $row['id']] = $row;
        }
    }

    $offenders = [];
    foreach ($items as $index => $item) {
        $id = (int) ($item['item_id'] ?? 0);
        $type = $item['item_type'] ?? null;
        if ($id <= 0 || $type !== 'ingredient') continue;
        $row = $lookup[$id] ?? null;
        if (!$row) continue;
        // Skip ingredients that haven't opted into pack enforcement.
        if ((int) ($row['enforce_whole_packs'] ?? 0) !== 1) continue;
        $packSize = $row['pack_size_value'] !== null ? (float) $row['pack_size_value'] : null;
        if ($packSize === null || $packSize <= 0) {
            // enforce_whole_packs is on but no pack size configured — treat
            // as a config error rather than a hard block. Surface in the
            // response so the prof/Warehouse Raw can notice the misconfig.
            $offenders[] = [
                'item_index' => $index,
                'item_id' => $id,
                'item_name' => $row['ingredient_name'] ?? '',
                'kind' => 'misconfigured',
                'enforce_whole_packs' => true,
                'message' => 'enforce_whole_packs is on but pack_size_value is not set. Either configure the pack size in the catalog or turn off enforcement.',
            ];
            continue;
        }

        // Effective pack count: explicit if the requester used pack mode,
        // otherwise derived from the base quantity.
        $explicitPacks = isset($item['quantity_in_packs']) && $item['quantity_in_packs'] !== null && $item['quantity_in_packs'] !== ''
            ? (float) $item['quantity_in_packs']
            : null;
        $baseQty = (float) ($item['quantity'] ?? 0);
        $effectivePacks = $explicitPacks !== null ? $explicitPacks : ($baseQty / $packSize);

        // Float-fractional check with epsilon for fp noise.
        $floor = floor($effectivePacks);
        $isFractional = abs($effectivePacks - $floor) > 0.0005;
        if (!$isFractional) continue;

        $ceilPacks = (int) ceil($effectivePacks);
        $ceilBase = round($ceilPacks * $packSize, 3);
        $diff = round($ceilPacks - $effectivePacks, 3);
        $packUnit = $row['pack_size_unit'] ?? '';

        $offenders[] = [
            'item_index' => $index,
            'item_id' => $id,
            'item_name' => $row['ingredient_name'] ?? '',
            'kind' => 'fractional',
            'pack_size' => $packSize,
            'pack_size_unit' => $packUnit,
            'pack_label' => $row['pack_label'] ?? null,
            'enforce_whole_packs' => true,
            'effective_packs' => $effectivePacks,
            'ceil_packs' => $ceilPacks,
            'ceil_base' => $ceilBase,
            'extra_packs_to_round_up' => $diff,
            'unit' => $item['unit'] ?? $packUnit ?: 'units',
            'message' => sprintf(
                '%s requests %s packs but each pack is %s %s. Round up to %d packs (%s %s) or acknowledge the pack break.',
                $row['ingredient_name'] ?? 'Item',
                rtrim(rtrim(number_format($effectivePacks, 3, '.', ''), '0'), '.'),
                rtrim(rtrim(number_format($packSize, 3, '.', ''), '0'), '.'),
                $packUnit,
                $ceilPacks,
                rtrim(rtrim(number_format($ceilBase, 3, '.', ''), '0'), '.'),
                $packUnit
            ),
        ];
    }

    return ['items' => $offenders];
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

    // V4.0 — always add raw_milk when the recipe calls for it, regardless
    // of product_type. Previously this branch excluded yogurt recipes
    // (`product_type !== 'yogurt'`) on the (wrong) assumption that
    // yogurt would get raw milk through some other path. There is no
    // other path — yogurt production needs raw_milk to feed the
    // pasteurization step (raw → pasteurized → run). Skipping it meant
    // yogurt requisitions arrived in Warehouse Raw with no milk to
    // issue, and the user had to manually add it (and didn't know why
    // the system left it out).
    if ($requiredMilk > 0) {
        $items[] = [
            'item_type' => 'raw_milk',
            'item_id' => null,
            'item_name' => 'Raw Milk',
            'quantity' => $requiredMilk,
            'unit' => 'liters',
            // V4.0 — note mentions the destination so warehouse staff
            // know this milk is going through pasteurization. For
            // yogurt recipes specifically, the warehouse still issues
            // raw milk — production pasteurizes it themselves.
            'notes' => ($recipe['product_type'] ?? '') === 'yogurt'
                ? "Raw milk for planned {$recipe['product_name']} production (will be pasteurized by production before the run)"
                : "Milk needed for planned {$recipe['product_name']} production"
        ];
    }

    $ingredientsStmt = $db->prepare("
        SELECT ri.ingredient_id, ri.ingredient_name, ri.quantity, ri.unit, ri.is_optional, ri.notes,
               i.pack_size_value, i.pack_size_unit, i.pack_label,
               i.enforce_whole_packs, i.current_stock
        FROM recipe_ingredients ri
        LEFT JOIN ingredients i ON ri.ingredient_id = i.id
        WHERE ri.recipe_id = ?
        ORDER BY ri.is_optional ASC, ri.ingredient_name ASC
    ");
    $ingredientsStmt->execute([$recipeId]);

    foreach ($ingredientsStmt->fetchAll() as $ingredient) {
        $quantity = round(((float) $ingredient['quantity']) * $scaleFactor, 3);
        if ($quantity <= 0) {
            continue;
        }

        // Pack conversion: ceil(qty / packSize) so warehouse always gets enough. The
        // base-unit quantity is preserved for fulfillment; the pack hint is advisory.
        $packSizeValue = $ingredient['pack_size_value'] !== null ? (float) $ingredient['pack_size_value'] : null;
        $packSizeUnit  = $ingredient['pack_size_unit']  ?: null;
        $packLabel     = $ingredient['pack_label']      ?: null;
        $packCount = null;
        $packHint  = null;
        if ($packSizeValue !== null && $packSizeValue > 0) {
            // The actual math the prof will ask about.
            // ceil() guarantees the recipe never runs short; floor/round would risk shortage.
            $packCount = (int) ceil($quantity / $packSizeValue);
            $displayUnit    = $packSizeUnit ?: $ingredient['unit'] ?: 'units';
            $packHint       = sprintf(
                '1 %s = %s %s',
                rtrim(rtrim(number_format($packSizeValue, 3, '.', ''), '0'), '.'),
                rtrim(rtrim(number_format($packSizeValue, 3, '.', ''), '0'), '.'),
                $displayUnit
            );
            // Build a clean hint like "1 packet = 100 ml" when label is set, else fallback to the unit math.
            if ($packLabel) {
                $packHint = $packLabel;
            } else {
                $packHint = sprintf(
                    '1 pack = %s %s',
                    rtrim(rtrim(number_format($packSizeValue, 3, '.', ''), '0'), '.'),
                    $displayUnit
                );
            }
        }

        $enforceWholePacks = (int) ($ingredient['enforce_whole_packs'] ?? 0) === 1;
        $currentStock = $ingredient['current_stock'] !== null ? (float) $ingredient['current_stock'] : null;

        $notes = trim((string) ($ingredient['notes'] ?? ''));
        if ((int) ($ingredient['is_optional'] ?? 0) === 1) {
            $notes = trim($notes . ($notes ? ' - ' : '') . 'Optional recipe item');
        }
        if ($packCount !== null) {
            $packNote = sprintf('Rounded up from %s %s -> %d pack%s',
                rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.'),
                $ingredient['unit'] ?: 'units',
                $packCount,
                $packCount === 1 ? '' : 's'
            );
            $notes = trim($notes . ($notes ? ' - ' : '') . $packNote);
        }

        $items[] = [
            'item_type' => 'ingredient',
            'item_id' => (int) ($ingredient['ingredient_id'] ?? 0) > 0 ? (int) $ingredient['ingredient_id'] : null,
            'item_name' => trim($ingredient['ingredient_name'] ?? ''),
            'quantity' => $quantity,
            'unit' => $ingredient['unit'] ?: 'units',
            'pack_size_value' => $packSizeValue,
            'pack_size_unit' => $packSizeUnit,
            'pack_label' => $packLabel,
            'pack_count' => $packCount,
            'enforce_whole_packs' => $enforceWholePacks,
            'current_stock' => $currentStock,
            // Kept for back-compat with any older caller; identical to pack_count.
            'suggested_packs' => $packCount,
            'pack_hint' => $packHint,
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
        SELECT ri.ingredient_id, ri.ingredient_name, ri.quantity, ri.unit, ri.is_optional, ri.notes,
               i.pack_size_value, i.pack_size_unit, i.pack_label,
               i.enforce_whole_packs, i.current_stock
        FROM recipe_ingredients ri
        LEFT JOIN ingredients i ON ri.ingredient_id = i.id
        WHERE ri.recipe_id = ?
        ORDER BY ri.is_optional ASC, ri.ingredient_name ASC
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

        // V4.0 — same pack conversion as getRequisitionRecipeItemsForPlan.
        // Run-scoped requisitions get the same pack_count / enforce_whole_packs
        // data so the form locks the qty field identically whether the
        // requester picks a recipe plan or an existing production run.
        $packSizeValue = $ingredient['pack_size_value'] !== null ? (float) $ingredient['pack_size_value'] : null;
        $packSizeUnit  = $ingredient['pack_size_unit']  ?: null;
        $packLabel     = $ingredient['pack_label']      ?: null;
        $packCount = null;
        $packHint  = null;
        if ($packSizeValue !== null && $packSizeValue > 0) {
            $packCount = (int) ceil($quantity / $packSizeValue);
            $displayUnit = $packSizeUnit ?: ($ingredient['unit'] ?: 'units');
            $packHint = $packLabel ?: sprintf(
                '1 pack = %s %s',
                rtrim(rtrim(number_format($packSizeValue, 3, '.', ''), '0'), '.'),
                $displayUnit
            );
        }
        $enforceWholePacks = (int) ($ingredient['enforce_whole_packs'] ?? 0) === 1;
        $currentStock = $ingredient['current_stock'] !== null ? (float) $ingredient['current_stock'] : null;

        $notes = trim((string) ($ingredient['notes'] ?? ''));
        if ((int) ($ingredient['is_optional'] ?? 0) === 1) {
            $notes = trim($notes . ($notes ? ' - ' : '') . 'Optional recipe item');
        }
        if ($packCount !== null) {
            $packNote = sprintf('Rounded up from %s %s -> %d pack%s',
                rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.'),
                $ingredient['unit'] ?: 'units',
                $packCount,
                $packCount === 1 ? '' : 's'
            );
            $notes = trim($notes . ($notes ? ' - ' : '') . $packNote);
        }

        $items[] = [
            'item_type' => 'ingredient',
            'item_id' => $ingredientId > 0 ? $ingredientId : null,
            'item_name' => $ingredientName,
            'quantity' => $quantity,
            'unit' => $ingredient['unit'] ?: 'units',
            'pack_size_value' => $packSizeValue,
            'pack_size_unit' => $packSizeUnit,
            'pack_label' => $packLabel,
            'pack_count' => $packCount,
            'enforce_whole_packs' => $enforceWholePacks,
            'current_stock' => $currentStock,
            'suggested_packs' => $packCount,
            'pack_hint' => $packHint,
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
    ensureStockValidationTables($db);
    
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
                           pr.run_code,
                           pr.status as run_status,
                           pmr.id as planned_recipe_exists,
                           pmr.recipe_code as planned_recipe_code,
                           pmr.product_name as planned_product_name,
                           pmr.variant as planned_variant,
                           pmr.product_type as planned_product_type,
                           u1.first_name as requested_by_first, u1.last_name as requested_by_last,
                           u2.first_name as approved_by_first, u2.last_name as approved_by_last,
                           u3.first_name as fulfilled_by_first, u3.last_name as fulfilled_by_last
                    FROM material_requisitions ir
                    LEFT JOIN production_runs pr ON ir.production_run_id = pr.id
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
                // V4.0.1 — also pull the ingredient's base unit_of_measure and
                // pack_label so the requisition detail modal can show
                // "25 kg (= 1 sack)" instead of "25 sack". The value in
                // requisition_items is ALWAYS in the base unit when
                // pack_size_at_submit is set (see the conversion at line
                // 1164 of this file), but the unit_of_measure column on
                // the row is the user-typed pack container word, not the
                // base unit. The JOIN is what gives the modal the info
                // it needs to show the right unit.
                $itemsStmt = $db->prepare("
                    SELECT ri.*,
                           uf.first_name as item_fulfilled_by_first,
                           uf.last_name as item_fulfilled_by_last,
                           i.unit_of_measure as ingredient_unit_of_measure,
                           i.pack_size_value as ingredient_pack_size_value,
                           i.pack_size_unit as ingredient_pack_size_unit,
                           i.pack_label as ingredient_pack_label
                    FROM requisition_items ri
                    LEFT JOIN users uf ON ri.fulfilled_by = uf.id
                    LEFT JOIN ingredients i ON ri.item_id = i.id AND ri.item_type = 'ingredient'
                    WHERE ri.requisition_id = ?
                    ORDER BY ri.id ASC
                ");
                $itemsStmt->execute([$reqId]);
                $requisition['items'] = $itemsStmt->fetchAll();

                // Surface stock-override info + per-item audit trail so the
                // warehouse can see exactly which lines were self-acknowledged
                // by production as short. The prof can run equivalent queries
                // against requisition_stock_warnings for the review.
                $overrideUserStmt = $db->prepare("
                    SELECT u.first_name, u.last_name, u.role
                    FROM users u WHERE u.id = ?
                ");
                $overrideUserStmt->execute([$requisition['stock_override_by'] ?? 0]);
                $overrideUser = $overrideUserStmt->fetch();

                $warningsStmt = $db->prepare("
                    SELECT id, ingredient_id, item_name, requested_qty, available_qty,
                           shortage, decision, decided_role, override_reason, created_at
                    FROM requisition_stock_warnings
                    WHERE requisition_id = ?
                    ORDER BY id ASC
                ");
                $warningsStmt->execute([$reqId]);
                $warnings = $warningsStmt->fetchAll();

                $requisition['stock_override'] = [
                    'acknowledged' => (bool) ($requisition['stock_override_acknowledged'] ?? 0),
                    'reason' => $requisition['stock_override_reason'] ?? null,
                    'at' => $requisition['stock_override_at'] ?? null,
                    'by' => $overrideUser ? [
                        'first_name' => $overrideUser['first_name'],
                        'last_name' => $overrideUser['last_name'],
                        'role' => $overrideUser['role'],
                    ] : null,
                    'warnings' => $warnings,
                ];
                // Strip the raw columns from the top level so the response
                // shape is consistent with what the rest of the app expects.
                unset(
                    $requisition['stock_override_acknowledged'],
                    $requisition['stock_override_by'],
                    $requisition['stock_override_reason'],
                    $requisition['stock_override_at']
                );

                Response::success($requisition, 'Requisition retrieved successfully');
            }
            
            // List requisitions
            $status = getParam('status');
            $workflow = getParam('workflow', 'active');
            $productionRunId = getParam('run_id');
            $dateFrom = getParam('date_from');
            $dateTo = getParam('date_to');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $joins = "
                LEFT JOIN production_runs pr ON ir.production_run_id = pr.id
                LEFT JOIN master_recipes pmr ON ir.planned_recipe_id = pmr.id
            ";
            $where = "WHERE 1=1";
            $params = [];

            switch ($workflow) {
                case 'active':
                    $where .= "
                        AND ir.status IN ('pending', 'partial', 'fulfilled')
                        AND NOT (ir.status = 'fulfilled' AND ir.production_run_id IS NOT NULL)
                        AND NOT (ir.planned_recipe_id IS NOT NULL AND pmr.id IS NULL)
                        AND NOT (
                            ir.status = 'fulfilled'
                            AND ir.production_run_id IS NULL
                            AND (ir.planned_recipe_id IS NULL OR ir.planned_quantity IS NULL OR ir.planned_quantity <= 0)
                        )
                    ";
                    break;
                case 'issues':
                    $where .= "
                        AND (
                            (ir.planned_recipe_id IS NOT NULL AND pmr.id IS NULL)
                            OR (
                                ir.status IN ('fulfilled', 'partial')
                                AND ir.production_run_id IS NULL
                                AND (ir.planned_recipe_id IS NULL OR ir.planned_quantity IS NULL OR ir.planned_quantity <= 0)
                            )
                            OR EXISTS (
                                SELECT 1
                                FROM requisition_items ri_issue
                                WHERE ri_issue.requisition_id = ir.id
                                  AND (
                                      (ri_issue.status = 'fulfilled' AND ri_issue.issued_quantity + 0.0005 < ri_issue.requested_quantity)
                                      OR (ri_issue.status = 'partial' AND ri_issue.issued_quantity + 0.0005 >= ri_issue.requested_quantity)
                                      OR (ir.status = 'fulfilled' AND ri_issue.status <> 'fulfilled')
                                  )
                            )
                            OR (
                                ir.status = 'partial'
                                AND EXISTS (
                                    SELECT 1 FROM requisition_items ri_any
                                    WHERE ri_any.requisition_id = ir.id
                                )
                                AND NOT EXISTS (
                                    SELECT 1 FROM requisition_items ri_open
                                    WHERE ri_open.requisition_id = ir.id
                                      AND ri_open.status <> 'fulfilled'
                                )
                            )
                        )
                    ";
                    break;
                case 'legacy':
                    $where .= " AND ir.production_run_id IS NULL AND ir.planned_recipe_id IS NULL";
                    break;
                case 'started':
                    $where .= " AND ir.production_run_id IS NOT NULL";
                    break;
                case 'all':
                default:
                    break;
            }
            
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
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM material_requisitions ir {$joins} {$where}");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get requisitions
            $stmt = $db->prepare("
                SELECT ir.id, ir.requisition_code, ir.production_run_id, ir.planned_recipe_id,
                       ir.planned_quantity, ir.planned_yield_unit, ir.status,
                       ir.priority, ir.needed_by_date, ir.purpose, ir.total_items, ir.created_at,
                       ir.stock_override_acknowledged, ir.stock_override_at,
                       (SELECT COALESCE(SUM(ri.requested_quantity), 0)
                          FROM requisition_items ri WHERE ri.requisition_id = ir.id) AS total_requested_quantity,
                       (SELECT COALESCE(SUM(ri.issued_quantity), 0)
                          FROM requisition_items ri WHERE ri.requisition_id = ir.id) AS total_issued_quantity,
                       (SELECT COUNT(*) FROM requisition_items ri
                          WHERE ri.requisition_id = ir.id AND ri.status = 'pending') AS pending_item_count,
                       (SELECT COUNT(*) FROM requisition_items ri
                          WHERE ri.requisition_id = ir.id AND ri.status = 'partial') AS partial_item_count,
                       (SELECT COUNT(*) FROM requisition_items ri
                          WHERE ri.requisition_id = ir.id AND ri.status = 'fulfilled') AS fulfilled_item_count,
                       (SELECT COUNT(*) FROM requisition_stock_warnings w
                         WHERE w.requisition_id = ir.id AND w.decision = 'overridden') AS stock_warning_count,
                       u1.first_name as requested_by_first, u1.last_name as requested_by_last,
                       pr.run_code,
                       pr.status as run_status,
                       pmr.id as planned_recipe_exists,
                       pmr.recipe_code as planned_recipe_code,
                       pmr.product_name as planned_product_name,
                       pmr.variant as planned_variant,
                       pmr.product_type as planned_product_type
                FROM material_requisitions ir
                LEFT JOIN users u1 ON ir.requested_by = u1.id
                {$joins}
                {$where}
                ORDER BY
                    ir.created_at DESC,
                    CASE ir.priority
                        WHEN 'urgent' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'normal' THEN 3
                        WHEN 'low' THEN 4
                    END,
                    ir.id DESC
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

            if (!$productionRunId && $plannedRecipeId) {
                $recipeCheck = $db->prepare("SELECT id FROM master_recipes WHERE id = ? AND is_active = 1");
                $recipeCheck->execute([$plannedRecipeId]);
                if (!$recipeCheck->fetch()) {
                    $errors['planned_recipe_id'] = 'Choose an active planned recipe';
                }
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }

            // ----------------------------------------------------------------
            // Stock validation gate (V4.0)
            //
            // The prof's review flagged that production could request more
            // materials than the warehouse had on hand. We now compute the
            // available stock for every item, and:
            //   - If every item has enough stock: proceed silently.
            //   - If any item has a shortage and the client did not send
            //     stock_override_acknowledged=true: return 422 with the
            //     shortages[] payload so the UI can show a confirmation modal.
            //   - If the client acknowledges the override: proceed, mark the
            //     requisition as overridden, and write audit rows to
            //     requisition_stock_warnings for accountability.
            // ----------------------------------------------------------------
            $stockCheck = checkRequisitionStock($db, $items);
            $hasShortage = false;
            foreach ($stockCheck['items'] as $s) {
                if (!$s['sufficient']) { $hasShortage = true; break; }
            }

            $overrideAcknowledged = filter_var(
                getParam('stock_override_acknowledged', false),
                FILTER_VALIDATE_BOOLEAN
            );
            $overrideReason = trim((string) getParam('stock_override_reason', ''));

            if ($hasShortage && !$overrideAcknowledged) {
                // Custom error response so we can include the full stock_check
                // payload (per-item requested/available/shortage) in addition
                // to the human message. Response::validationError() only
                // accepts a flat errors array, so we go through error() with
                // status 422 and pack the structure into the 'errors' field.
                Response::error(
                    'Insufficient stock for one or more requested items. Acknowledge the shortage to proceed.',
                    422,
                    [
                        'error_code' => 'insufficient_stock',
                        'stock_check' => $stockCheck,
                    ]
                );
            }

            // V4.0 (Option B) — pack-integrity gate. If any submitted
            // ingredient has enforce_whole_packs=true AND the requester
            // typed a fractional pack count, return 422 with the offender
            // list. The client can either round up (re-submit with the
            // ceil_packs value) or send break_pack_acknowledged=true per
            // item. Misconfigured offenders (enforce_whole_packs on but no
            // pack_size) are surfaced in the same payload as info — they
            // don't block the submit, they just call attention.
            $packCheck = checkRequisitionPackIntegrity($db, $items);
            $fractionalOffenders = array_values(array_filter(
                $packCheck['items'],
                fn($o) => ($o['kind'] ?? '') === 'fractional'
            ));
            $unackedFractionals = [];
            foreach ($fractionalOffenders as $off) {
                $clientItem = $items[$off['item_index']] ?? null;
                $acked = $clientItem && filter_var(
                    $clientItem['break_pack_acknowledged'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );
                if (!$acked) {
                    $unackedFractionals[] = $off;
                }
            }
            if (!empty($unackedFractionals)) {
                Response::error(
                    'One or more ingredients require whole packs. Round up or acknowledge the pack break per item.',
                    422,
                    [
                        'error_code' => 'pack_fractional',
                        'pack_check' => [
                            'fractional_count' => count($fractionalOffenders),
                            'unacked_count' => count($unackedFractionals),
                            'items' => $unackedFractionals,
                            'all_offenders' => $packCheck['items'],
                        ],
                    ]
                );
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
                        priority, needed_by_date, purpose, total_items, status,
                        stock_override_acknowledged, stock_override_by, stock_override_reason, stock_override_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)
                ");

                $overrideCols = $hasShortage ? [1, $currentUser['user_id'], $overrideReason ?: null, date('Y-m-d H:i:s')] : [0, null, null, null];

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
                    count($items),
                    $overrideCols[0],
                    $overrideCols[1],
                    $overrideCols[2],
                    $overrideCols[3],
                ]);

                $requisitionId = $db->lastInsertId();

                // V4.0 — pre-fetch pack_size_value for any ingredient item
                // the requester asked for in packs. We use this BOTH to
                // compute the base quantity for the requisition_items row
                // AND to write the audit-faithful pack_size_at_submit
                // snapshot. Free-text and raw_milk rows get NULL.
                $packSizeLookup = [];
                $ingredientIdsForPackLookup = [];
                foreach ($items as $item) {
                    $pItemId = (int) ($item['item_id'] ?? 0);
                    $pItemType = $item['item_type'] ?? null;
                    $pPacks = $item['quantity_in_packs'] ?? null;
                    if ($pItemId > 0 && $pPacks !== null && $pPacks !== '' && $pItemType === 'ingredient') {
                        $ingredientIdsForPackLookup[$pItemId] = true;
                    }
                }
                $ingredientUnitLookup = [];
                if (!empty($ingredientIdsForPackLookup)) {
                    $idList = implode(',', array_map('intval', array_keys($ingredientIdsForPackLookup)));
                    $packStmt = $db->query("SELECT id, pack_size_value, unit_of_measure FROM ingredients WHERE id IN ({$idList})");
                    foreach ($packStmt->fetchAll() as $row) {
                        $packSizeLookup[(int) $row['id']] = $row['pack_size_value'] !== null ? (float) $row['pack_size_value'] : null;
                        $ingredientUnitLookup[(int) $row['id']] = $row['unit_of_measure'] ?: null;
                    }
                }

                // Insert items
                $itemStmt = $db->prepare("
                    INSERT INTO requisition_items (
                        requisition_id, item_type, item_id, item_name,
                        requested_quantity, requested_quantity_in_packs, pack_size_at_submit,
                        break_pack_acknowledged, break_pack_acknowledged_reason,
                        unit_of_measure, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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

                    // V4.0 — pack-traceability fields. When the requester
                    // asked in packs, store the pack count and the snapshot
                    // of pack_size_at_submit. Convert the stored base
                    // quantity to the converted value so warehouse sees the
                    // same number the stock check used.
                    $requestedRawQty = (float) ($item['quantity'] ?? 0);
                    $requestedPacks = isset($item['quantity_in_packs']) && $item['quantity_in_packs'] !== null && $item['quantity_in_packs'] !== ''
                        ? (float) $item['quantity_in_packs']
                        : null;
                    $packSizeAtSubmit = null;
                    $storedBaseQty = $requestedRawQty;
                    if ($requestedPacks !== null && $itemType === 'ingredient' && $itemId > 0
                        && isset($packSizeLookup[$itemId]) && $packSizeLookup[$itemId] > 0) {
                        $packSizeAtSubmit = $packSizeLookup[$itemId];
                        $storedBaseQty = round($requestedPacks * $packSizeAtSubmit, 3);
                    }

                    // V4.0 (Option B) — pack-break acknowledgement. The
                    // client can ack a fractional pack count per-item by
                    // sending break_pack_acknowledged=true. We persist both
                    // the flag and the reason to the row so the warehouse
                    // and the prof can see when a requester knowingly
                    // opened a pack instead of rounding up.
                    $breakPackAck = filter_var(
                        $item['break_pack_acknowledged'] ?? false,
                        FILTER_VALIDATE_BOOLEAN
                    ) ? 1 : 0;
                    $breakPackReason = $breakPackAck
                        ? trim((string) ($item['break_pack_acknowledged_reason'] ?? '')) ?: null
                        : null;

                    // When pack-converted, use the ingredient's base unit (kg, liter)
                    // instead of the display pack word (sack, bottle) so warehouse
                    // stock comparisons use consistent units.
                    $unitOfMeasure = $item['unit'] ?? 'units';
                    if ($requestedPacks !== null && $itemType === 'ingredient' && $itemId > 0
                        && isset($ingredientUnitLookup[$itemId]) && $ingredientUnitLookup[$itemId]) {
                        $unitOfMeasure = $ingredientUnitLookup[$itemId];
                    }

                    $itemStmt->execute([
                        $requisitionId,
                        $itemType,
                        $itemId,
                        $itemNameRaw,
                        $storedBaseQty,
                        $requestedPacks,
                        $packSizeAtSubmit,
                        $breakPackAck,
                        $breakPackReason,
                        $unitOfMeasure,
                        $item['notes'] ?? ''
                    ]);
                }
                
                $db->commit();

                // Write the per-item stock-validation audit rows now that the
                // requisition id is real and committed. Only rows for items
                // that actually had a shortage (or could not be verified) are
                // logged; clean rows are skipped to keep the audit table lean.
                if ($hasShortage) {
                    logStockValidationDecisions(
                        $db,
                        $requisitionId,
                        $stockCheck['items'],
                        'overridden',
                        $currentUser['user_id'],
                        $currentUser['role'],
                        $overrideReason ?: null
                    );
                }

                $message = $hasShortage
                    ? "Requisition {$requisitionCode} submitted with acknowledged stock shortage. Warehouse Raw has been notified."
                    : "Requisition {$requisitionCode} submitted. Warehouse Raw has been notified.";

                Response::created([
                    'id' => $requisitionId,
                    'requisition_code' => $requisitionCode,
                    'status' => 'pending',
                    'total_items' => count($items),
                    'stock_override_acknowledged' => $hasShortage ? 1 : 0,
                    'pack_traceability' => [
                        // Per-item echo so the UI can confirm the conversion
                        // the server did. The same data is also in the
                        // requisition_items table for warehouse to read.
                        'items' => array_map(function ($s) {
                            return [
                                'item_index' => $s['item_index'],
                                'item_id' => $s['item_id'],
                                'item_name' => $s['item_name'],
                                'requested_packs' => $s['requested_packs'],
                                'pack_size' => $s['pack_size'],
                                'requested_base' => $s['requested'],
                            ];
                        }, array_values(array_filter($stockCheck['items'], fn($s) => $s['requested_packs'] !== null))),
                    ],
                    'stock_summary' => [
                        'all_sufficient' => $stockCheck['all_sufficient'],
                        'shortage_count' => count(array_filter($stockCheck['items'], fn($s) => !$s['sufficient'])),
                        'shortages' => array_values(array_filter($stockCheck['items'], fn($s) => !$s['sufficient'])),
                    ],
                ], $message);

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

            // V4.0 — Approve / Reject / Fulfill / Partially-fulfill were
            // removed from this endpoint. There is no GM gate anymore (pending
            // requisitions are visible to warehouse immediately), and the
            // warehouse fulfillment flow lives entirely in
            // api/warehouse/raw/requisitions.php. The only mutating action
            // exposed to production staff here is `cancel` for their own
            // pending requisitions. Any other action returns 400.
            switch ($action) {
                case 'cancel':
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

                case 'approve':
                case 'reject':
                case 'fulfill':
                case 'partially_fulfill':
                    Response::error(
                        "The '{$action}' action is no longer supported. " .
                        "Production requisitions are visible to Warehouse Raw immediately on submit. " .
                        "Use api/warehouse/raw/requisitions.php for fulfillment actions.",
                        400
                    );
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
