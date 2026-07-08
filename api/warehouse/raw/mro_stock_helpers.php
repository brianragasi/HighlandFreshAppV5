<?php
/**
 * Shared helpers for MRO stock and FIFO batch reconciliation.
 */

if (!defined('HIGHLAND_FRESH')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

function getUsableMROBatchStock($db, $mroItemId) {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(remaining_quantity), 0) AS available_quantity
        FROM mro_inventory
        WHERE mro_item_id = ?
          AND status IN ('available', 'partially_used')
          AND remaining_quantity > 0
    ");
    $stmt->execute([$mroItemId]);
    return (float) ($stmt->fetch()['available_quantity'] ?? 0);
}

function generateMROAdjustmentBatchCode($db) {
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $code = 'MRO-ADJ-' . date('Ymd') . '-' . str_pad((string) mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT COUNT(*) AS existing_count FROM mro_inventory WHERE batch_code = ?");
        $stmt->execute([$code]);
        if ((int) ($stmt->fetch()['existing_count'] ?? 0) === 0) {
            return $code;
        }
    }

    return generateCode('MRO-ADJ');
}

function repairMROSummaryToInventory($db, $itemData, $currentUser, $reason = 'Created to repair missing FIFO batch from existing stock on file') {
    $mroItemId = (int) $itemData['id'];
    $summaryStock = (float) ($itemData['current_stock'] ?? 0);
    $batchStock = getUsableMROBatchStock($db, $mroItemId);
    $missingQuantity = round($summaryStock - $batchStock, 3);

    if ($missingQuantity <= 0.0005) {
        return null;
    }

    $batchCode = generateMROAdjustmentBatchCode($db);
    $stmt = $db->prepare("
        INSERT INTO mro_inventory
        (batch_code, mro_item_id, quantity, remaining_quantity, unit_cost,
         supplier_name, received_date, status, received_by, notes)
        VALUES (?, ?, ?, ?, ?, 'Stock repair', CURDATE(), 'available', ?, ?)
    ");
    $stmt->execute([
        $batchCode,
        $mroItemId,
        $missingQuantity,
        $missingQuantity,
        $itemData['unit_cost'] ?? null,
        $currentUser['user_id'],
        $reason
    ]);

    $batchId = (int) $db->lastInsertId();

    logAudit($currentUser['user_id'], 'repair_mro_batches', 'mro_inventory', $batchId, null, [
        'mro_item_id' => $mroItemId,
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

function ensureMROBatchesForIssue($db, $itemData, $neededQuantity, $currentUser) {
    $mroItemId = (int) $itemData['id'];
    $batchStock = getUsableMROBatchStock($db, $mroItemId);
    $summaryStock = (float) ($itemData['current_stock'] ?? 0);

    if ($batchStock + 0.0005 >= (float) $neededQuantity) {
        return null;
    }

    if ($summaryStock + 0.0005 >= (float) $neededQuantity && $summaryStock > $batchStock + 0.0005) {
        return repairMROSummaryToInventory(
            $db,
            $itemData,
            $currentUser,
            'Auto-created from existing summary stock before MRO issue'
        );
    }

    return null;
}
