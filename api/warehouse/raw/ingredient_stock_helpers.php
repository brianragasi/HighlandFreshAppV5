<?php
/**
 * Shared helpers for ingredient stock and batch reconciliation.
 */

if (!defined('HIGHLAND_FRESH')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

function getUsableIngredientBatches($db, $ingredientId, $forUpdate = false) {
    $lockSql = $forUpdate ? ' FOR UPDATE' : '';
    $stmt = $db->prepare("
        SELECT *
        FROM ingredient_batches
        WHERE ingredient_id = ?
          AND status IN ('available', 'partially_used')
          AND remaining_quantity > 0
        ORDER BY expiry_date ASC, received_date ASC, id ASC
        {$lockSql}
    ");
    $stmt->execute([$ingredientId]);
    return $stmt->fetchAll();
}

function getUsableIngredientBatchStock($db, $ingredientId) {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(remaining_quantity), 0) AS available_quantity
        FROM ingredient_batches
        WHERE ingredient_id = ?
          AND status IN ('available', 'partially_used')
          AND remaining_quantity > 0
    ");
    $stmt->execute([$ingredientId]);
    return (float) ($stmt->fetch()['available_quantity'] ?? 0);
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

function reconcileIngredientSummaryToBatches($db, $ingredientData, $currentUser, $reason = 'Reconciled summary stock to usable batches') {
    $ingredientId = (int) $ingredientData['id'];
    $summaryStock = (float) ($ingredientData['current_stock'] ?? 0);
    $batchStock = getUsableIngredientBatchStock($db, $ingredientId);
    $missingQuantity = round($summaryStock - $batchStock, 3);

    if ($missingQuantity <= 0.0005) {
        return null;
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

function ensureIngredientBatchesForIssue($db, $ingredientData, $neededQuantity, $currentUser) {
    $ingredientId = (int) $ingredientData['id'];
    $batchStock = getUsableIngredientBatchStock($db, $ingredientId);
    $summaryStock = (float) ($ingredientData['current_stock'] ?? 0);

    if ($batchStock + 0.0005 >= (float) $neededQuantity) {
        return null;
    }

    if ($summaryStock + 0.0005 >= (float) $neededQuantity && $summaryStock > $batchStock + 0.0005) {
        return reconcileIngredientSummaryToBatches(
            $db,
            $ingredientData,
            $currentUser,
            'Auto-created from existing summary stock before ingredient issue'
        );
    }

    return null;
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
