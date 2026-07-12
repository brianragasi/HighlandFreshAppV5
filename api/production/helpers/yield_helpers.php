<?php
/**
 * Highland Fresh — Yield/Loss Helper Functions
 *
 * Shared logic used by losses.php, yield.php, packaging-estimate.php, and runs.php.
 * This file does NOT send HTTP responses — it only returns data arrays.
 */

if (defined('YIELD_HELPERS_LOADED')) return;
define('YIELD_HELPERS_LOADED', true);

/**
 * Generate packaging estimates for a production run.
 * Fetches packaging_rules for the run's product and calculates units per size.
 */
function generatePackagingEstimate($db, $runId, $estimateType, $basisVolumeMl) {
    $stmt = $db->prepare("
        SELECT mr.product_id
        FROM production_runs pr
        JOIN master_recipes mr ON mr.id = pr.recipe_id
        WHERE pr.id = ?
    ");
    $stmt->execute([$runId]);
    $recipe = $stmt->fetch();

    if (!$recipe || !$recipe['product_id']) {
        return ['success' => false, 'message' => 'No product linked to this run\'s recipe'];
    }

    $productId = $recipe['product_id'];

    $rulesStmt = $db->prepare("
        SELECT packaging_size_ml, packaging_label, priority_order
        FROM packaging_rules
        WHERE product_id = ? AND is_active = 1
        ORDER BY priority_order ASC
    ");
    $rulesStmt->execute([$productId]);
    $rules = $rulesStmt->fetchAll();

    if (empty($rules)) {
        return ['success' => false, 'message' => 'No packaging rules configured for this product'];
    }

    // Delete previous estimates of this type
    $delStmt = $db->prepare("
        DELETE FROM packaging_estimates
        WHERE production_run_id = ? AND estimate_type = ?
    ");
    $delStmt->execute([$runId, $estimateType]);

    $estimates = [];
    $insertStmt = $db->prepare("
        INSERT INTO packaging_estimates
            (production_run_id, estimate_type, basis_volume_ml, packaging_size_ml, packaging_label, estimated_units, remainder_ml)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($rules as $rule) {
        $sizeMl = (int)$rule['packaging_size_ml'];
        $units = floor($basisVolumeMl / $sizeMl);
        $remainder = $basisVolumeMl - ($units * $sizeMl);

        $insertStmt->execute([
            $runId, $estimateType, $basisVolumeMl, $sizeMl,
            $rule['packaging_label'], $units, $remainder
        ]);

        $estimates[] = [
            'packaging_size_ml' => $sizeMl,
            'label' => $rule['packaging_label'],
            'estimated_units' => (int)$units,
            'volume_used_ml' => $units * $sizeMl,
            'remainder_ml' => round($remainder, 2)
        ];
    }

    return [
        'success' => true,
        'estimate_type' => $estimateType,
        'basis_volume_ml' => (float)$basisVolumeMl,
        'product_id' => (int)$productId,
        'estimates' => $estimates
    ];
}

/**
 * Recalculate total losses and net yield for a production run.
 * Also triggers a revised packaging estimate.
 */
function recalculateRunYield($db, $runId) {
    $lossStmt = $db->prepare("
        SELECT COALESCE(SUM(loss_volume_ml), 0) AS total_loss
        FROM production_losses
        WHERE production_run_id = ?
    ");
    $lossStmt->execute([$runId]);
    $totalLoss = (float)$lossStmt->fetchColumn();

    $byproductStmt = $db->prepare("
        SELECT COALESCE(SUM(
            CASE WHEN unit = 'liters' THEN quantity * 1000
                 WHEN unit = 'ml' THEN quantity
                 WHEN unit = 'kg' THEN quantity * 1000
                 ELSE quantity
            END
        ), 0) AS total_byproduct
        FROM production_byproducts
        WHERE run_id = ?
    ");
    $byproductStmt->execute([$runId]);
    $totalByproduct = (float)$byproductStmt->fetchColumn();

    $runStmt = $db->prepare("SELECT initial_volume_ml FROM production_runs WHERE id = ?");
    $runStmt->execute([$runId]);
    $initialVolume = (float)$runStmt->fetchColumn();

    $netYield = max(0, $initialVolume - $totalLoss - $totalByproduct);

    $updateStmt = $db->prepare("
        UPDATE production_runs
        SET total_loss_ml = ?, total_byproduct_ml = ?, net_yield_ml = ?
        WHERE id = ?
    ");
    $updateStmt->execute([$totalLoss, $totalByproduct, $netYield, $runId]);

    // Auto-trigger revised packaging estimate
    if ($initialVolume > 0 && $netYield > 0) {
        generatePackagingEstimate($db, $runId, 'revised', $netYield);
    }

    return [
        'initial_volume_ml' => $initialVolume,
        'total_loss_ml' => $totalLoss,
        'total_byproduct_ml' => $totalByproduct,
        'net_yield_ml' => $netYield,
        'yield_efficiency_percent' => $initialVolume > 0 ? round(($netYield / $initialVolume) * 100, 2) : 0
    ];
}
