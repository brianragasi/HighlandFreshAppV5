<?php
/**
 * Shared helpers for ingredient stock and batch reconciliation.
 */

if (!defined('HIGHLAND_FRESH')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

function getUsableIngredientBatches($db, $ingredientId, $forUpdate = false) {
    // Perishable stock must have at least one full day left. A batch whose
    // printed expiry is today is held so it cannot be issued mid-process.
    $lockSql = $forUpdate ? ' FOR UPDATE' : '';
    $stmt = $db->prepare("
        SELECT ib.*
        FROM ingredient_batches ib
        JOIN ingredients trace_i ON trace_i.id = ib.ingredient_id
        WHERE ib.ingredient_id = ?
          AND ib.status IN ('available', 'partially_used')
          AND ib.remaining_quantity > 0
          AND (COALESCE(trace_i.is_perishable, 1) = 0 OR ib.expiry_date > CURDATE())
          AND (
              COALESCE(trace_i.is_perishable, 1) = 0
              OR (
                  NULLIF(TRIM(ib.supplier_batch_no), '') IS NOT NULL
                  AND ib.expiry_date IS NOT NULL
              )
          )
        ORDER BY ib.expiry_date ASC, ib.received_date ASC, ib.id ASC
        {$lockSql}
    ");
    $stmt->execute([$ingredientId]);
    return $stmt->fetchAll();
}

function getUsableIngredientBatchStock($db, $ingredientId) {
    // Same expiry filter as getUsableIngredientBatches(). Without
    // this, `current_stock` calculations on the ingredient summary would
    // count expired batches, leading the page to show phantom stock.
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(remaining_quantity), 0) AS available_quantity
        FROM ingredient_batches ib
        JOIN ingredients trace_i ON trace_i.id = ib.ingredient_id
        WHERE ib.ingredient_id = ?
          AND ib.status IN ('available', 'partially_used')
          AND ib.remaining_quantity > 0
          AND (COALESCE(trace_i.is_perishable, 1) = 0 OR ib.expiry_date > CURDATE())
          AND (
              COALESCE(trace_i.is_perishable, 1) = 0
              OR (
                  NULLIF(TRIM(ib.supplier_batch_no), '') IS NOT NULL
                  AND ib.expiry_date IS NOT NULL
              )
          )
    ");
    $stmt->execute([$ingredientId]);
    return (float) ($stmt->fetch()['available_quantity'] ?? 0);
}

/**
 * Canonical correlated SQL expression for usable ingredient batch stock.
 * Callers provide trusted SQL identifiers, never request values.
 */
function usableIngredientBatchStockSql($ingredientIdSql = 'i.id', $batchAlias = 'usable_ib') {
    return "COALESCE((
        SELECT SUM({$batchAlias}.remaining_quantity)
        FROM ingredient_batches {$batchAlias}
        WHERE {$batchAlias}.ingredient_id = {$ingredientIdSql}
          AND {$batchAlias}.status IN ('available', 'partially_used')
          AND {$batchAlias}.remaining_quantity > 0
          AND (
              COALESCE((SELECT expiry_i.is_perishable FROM ingredients expiry_i WHERE expiry_i.id = {$ingredientIdSql}), 1) = 0
              OR {$batchAlias}.expiry_date > CURDATE()
          )
          AND (
              COALESCE((SELECT trace_i.is_perishable FROM ingredients trace_i WHERE trace_i.id = {$ingredientIdSql}), 1) = 0
              OR (
                  NULLIF(TRIM({$batchAlias}.supplier_batch_no), '') IS NOT NULL
                  AND {$batchAlias}.expiry_date IS NOT NULL
              )
          )
    ), 0)";
}

/**
 * Stock that is still physically accounted for in the warehouse.
 *
 * Expired and quarantined batches are deliberately included here. They are
 * not usable, but they still exist on the shelf until Warehouse Raw records
 * their disposal or return. Comparing the item summary only with usable stock
 * would incorrectly treat these batches as missing and could recreate expired
 * material as a fresh adjustment batch.
 */
function getAccountedIngredientBatchStock($db, $ingredientId) {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(remaining_quantity), 0) AS accounted_quantity
        FROM ingredient_batches
        WHERE ingredient_id = ?
          AND status IN ('available', 'partially_used', 'quarantine', 'expired')
          AND remaining_quantity > 0
    ");
    $stmt->execute([$ingredientId]);
    return (float) ($stmt->fetch()['accounted_quantity'] ?? 0);
}

function getExpiredIngredientBatchStock($db, $ingredientId) {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(remaining_quantity), 0) AS expired_quantity
        FROM ingredient_batches
        WHERE ingredient_id = ?
          AND status IN ('available', 'partially_used', 'quarantine', 'expired')
          AND remaining_quantity > 0
          AND expiry_date IS NOT NULL
          AND expiry_date <= CURDATE()
    ");
    $stmt->execute([$ingredientId]);
    return (float) ($stmt->fetch()['expired_quantity'] ?? 0);
}

function generateIngredientBatchCode($db, $prefix = 'IB-ADJ') {
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $code = $prefix . '-' . date('Ymd') . '-' . str_pad((string) mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT COUNT(*) AS existing_count FROM ingredient_batches WHERE batch_code = ?");
        $stmt->execute([$code]);
        if ((int) ($stmt->fetch()['existing_count'] ?? 0) === 0) {
            return $code;
        }
    }

    return generateCode($prefix);
}

function getIngredientAdjustmentExpiryDate($ingredientData) {
    if (isset($ingredientData['is_perishable']) && (int) $ingredientData['is_perishable'] === 0) {
        return null;
    }

    $shelfLifeDays = (int) ($ingredientData['shelf_life_days'] ?? 0);
    if ($shelfLifeDays <= 0) {
        return null;
    }

    return date('Y-m-d', strtotime("+{$shelfLifeDays} days"));
}

function reconcileIngredientSummaryToBatches($db, $ingredientData, $currentUser, $reason = 'Reconciled summary stock to physically accounted batches') {
    $ingredientId = (int) $ingredientData['id'];
    $summaryStock = (float) ($ingredientData['current_stock'] ?? 0);
    $batchStock = getAccountedIngredientBatchStock($db, $ingredientId);
    $missingQuantity = round($summaryStock - $batchStock, 3);

    if ($missingQuantity <= 0.0005) {
        return null;
    }

    if ((int) ($ingredientData['is_perishable'] ?? 1) === 1) {
        throw new RuntimeException(
            'Perishable stock cannot be repaired automatically. Record the physical source, supplier lot, and printed expiry for review.'
        );
    }

    $batchCode = generateIngredientBatchCode($db);
    $expiryDate = getIngredientAdjustmentExpiryDate($ingredientData);

    $stmt = $db->prepare("
        INSERT INTO ingredient_batches
        (batch_code, ingredient_id, quantity, remaining_quantity, unit_cost,
         received_date, expiry_date, qc_status, status, received_by, notes)
        VALUES (?, ?, ?, ?, ?, CURDATE(), ?, 'approved', 'available', ?, ?)
    ");
    $stmt->execute([
        $batchCode,
        $ingredientId,
        $missingQuantity,
        $missingQuantity,
        $ingredientData['unit_cost'] ?? null,
        $expiryDate,
        $currentUser['user_id'],
        $reason
    ]);

    $batchId = (int) $db->lastInsertId();

    logAudit($currentUser['user_id'], 'reconcile_ingredient_batches', 'ingredient_batches', $batchId, null, [
        'ingredient_id' => $ingredientId,
        'batch_code' => $batchCode,
        'quantity' => $missingQuantity,
        'reason' => $reason
    ]);

    return [
        'batch_id' => $batchId,
        'batch_code' => $batchCode,
        'quantity' => $missingQuantity
    ];
}

/**
 * Record stock discovered during a Warehouse physical count.
 *
 * The correction goes Warehouse -> GM only. Approval creates a clearly
 * labelled internal count batch for exactly the positive difference so the
 * ingredient summary and batch ledger stay aligned without pretending that
 * the correction was a new supplier delivery.
 */
function increaseIngredientBatchesToQuantity($db, $ingredientData, $targetQuantity, $currentUser, $reason) {
    $targetQuantity = max(0, (float) $targetQuantity);
    $currentQuantity = (float) ($ingredientData['current_stock'] ?? 0);
    $difference = round($targetQuantity - $currentQuantity, 3);

    if ($difference <= 0.0005) {
        return [];
    }

    $isPerishable = (int) ($ingredientData['is_perishable'] ?? 1) === 1;
    $batchCode = generateIngredientBatchCode($db, 'IB-COUNT');
    $expiryDate = getIngredientAdjustmentExpiryDate($ingredientData);
    if ($isPerishable && $expiryDate === null) {
        // A conservative one-day window keeps the correction visible and
        // usable for the demonstrated workflow without granting anonymous
        // perishable stock an unlimited shelf life.
        $expiryDate = date('Y-m-d', strtotime('+1 day'));
    }
    $internalLot = $isPerishable ? 'WAREHOUSE-' . $batchCode : null;

    $stmt = $db->prepare("
        INSERT INTO ingredient_batches
        (batch_code, ingredient_id, quantity, remaining_quantity, unit_cost,
         supplier_batch_no, received_date, expiry_date, qc_status, status,
         received_by, notes)
        VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, 'approved', 'available', ?, ?)
    ");
    $stmt->execute([
        $batchCode,
        (int) $ingredientData['id'],
        $difference,
        $difference,
        $ingredientData['unit_cost'] ?? null,
        $internalLot,
        $expiryDate,
        (int) $currentUser['user_id'],
        'GM-approved Warehouse physical count: ' . $reason,
    ]);
    $batchId = (int) $db->lastInsertId();

    logAudit($currentUser['user_id'], 'warehouse_count_increase', 'ingredient_batches', $batchId, null, [
        'ingredient_id' => (int) $ingredientData['id'],
        'batch_code' => $batchCode,
        'quantity' => $difference,
        'reason' => $reason,
    ]);

    return [[
        'batch_id' => $batchId,
        'batch_code' => $batchCode,
        'quantity_before' => 0.0,
        'quantity_after' => $difference,
        'quantity_changed' => $difference,
        'reason' => $reason,
    ]];
}

function reduceIngredientBatchesToQuantity($db, $ingredientData, $targetQuantity, $currentUser, $reason) {
    $targetQuantity = max(0, (float) $targetQuantity);
    $ingredientId = (int) $ingredientData['id'];

    reconcileIngredientSummaryToBatches(
        $db,
        $ingredientData,
        $currentUser,
        'Auto-created from existing summary stock before stock adjustment'
    );

    $batchStock = getUsableIngredientBatchStock($db, $ingredientId);
    $quantityToRemove = round($batchStock - $targetQuantity, 3);
    if ($quantityToRemove <= 0.0005) {
        return [];
    }

    $adjustedBatches = [];
    foreach (getUsableIngredientBatches($db, $ingredientId, true) as $batch) {
        if ($quantityToRemove <= 0.0005) {
            break;
        }

        $removeFromBatch = min((float) $batch['remaining_quantity'], $quantityToRemove);
        $newRemaining = round((float) $batch['remaining_quantity'] - $removeFromBatch, 3);
        $newStatus = $newRemaining > 0.0005 ? 'partially_used' : 'consumed';

        $stmt = $db->prepare("
            UPDATE ingredient_batches
            SET remaining_quantity = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newRemaining, $newStatus, $batch['id']]);

        $adjustedBatches[] = [
            'batch_id' => (int) $batch['id'],
            'batch_code' => $batch['batch_code'],
            'quantity_removed' => $removeFromBatch,
            'reason' => $reason
        ];

        $quantityToRemove = round($quantityToRemove - $removeFromBatch, 3);
    }

    return $adjustedBatches;
}

/**
 * Preview the FEFO/FIFO allocation used by an ingredient-wide lower count.
 * This performs no writes and lets Warehouse and the GM see the exact lots
 * the system proposes to reduce before an approval changes inventory.
 */
function previewIngredientBatchReduction($db, $ingredientId, $quantityToRemove) {
    $quantityToRemove = max(0, round((float) $quantityToRemove, 3));
    if ($quantityToRemove <= 0.0005) {
        return [];
    }

    $allocation = [];
    foreach (getUsableIngredientBatches($db, (int) $ingredientId, false) as $batch) {
        if ($quantityToRemove <= 0.0005) {
            break;
        }
        $before = (float) ($batch['remaining_quantity'] ?? 0);
        $removed = min($before, $quantityToRemove);
        $allocation[] = [
            'batch_id' => (int) $batch['id'],
            'batch_code' => (string) ($batch['batch_code'] ?? ('Batch #' . $batch['id'])),
            'quantity_before' => $before,
            'quantity_after' => round($before - $removed, 3),
            'quantity_removed' => $removed,
        ];
        $quantityToRemove = round($quantityToRemove - $removed, 3);
    }

    return $allocation;
}
