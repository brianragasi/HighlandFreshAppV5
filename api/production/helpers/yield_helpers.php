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
 * Convert a product unit_size + unit_measure to milliliters (liquid).
 * Solids (g/kg) map 1:1 as "ml-equivalent" for packing math only when no better unit exists.
 */
function packagingSizeToMl($unitSize, $unitMeasure) {
    $size = (float) $unitSize;
    if ($size <= 0) {
        return 0;
    }
    $u = strtolower(trim((string) $unitMeasure));
    if (in_array($u, ['l', 'lt', 'liter', 'liters'], true)) {
        return (int) round($size * 1000);
    }
    if (in_array($u, ['ml', 'milliliter', 'milliliters'], true)) {
        return (int) round($size);
    }
    // g / kg: treat as ml-equivalent for dairy tubs (approx density 1) — better than failing
    if (in_array($u, ['kg'], true)) {
        return (int) round($size * 1000);
    }
    if (in_array($u, ['g', 'gram', 'grams'], true)) {
        return (int) round($size);
    }
    return (int) round($size);
}

/**
 * Resolve packaging SKU sizes for a run from base_product_id (preferred) or legacy product_id.
 * Returns list of: product_id, packaging_size_ml, packaging_label, priority_order
 */
function resolvePackagingSkusForRun(PDO $db, $runId) {
    $stmt = $db->prepare("
        SELECT mr.product_id, mr.base_product_id, mr.product_name, bp.name AS base_name
        FROM production_runs pr
        JOIN master_recipes mr ON mr.id = pr.recipe_id
        LEFT JOIN base_products bp ON bp.id = mr.base_product_id
        WHERE pr.id = ?
    ");
    $stmt->execute([(int) $runId]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipe) {
        return ['success' => false, 'message' => 'Run/recipe not found', 'skus' => [], 'base_product_id' => null];
    }

    $baseProductId = !empty($recipe['base_product_id']) ? (int) $recipe['base_product_id'] : null;
    $legacyProductId = !empty($recipe['product_id']) ? (int) $recipe['product_id'] : null;
    $skus = [];

    // 1) Preferred: all active packaging SKUs under the base liquid product
    if ($baseProductId) {
        try {
            $skuStmt = $db->prepare("
                SELECT id, product_code, product_name, variant, unit_size, unit_measure, is_active
                FROM products
                WHERE base_product_id = ? AND is_active = 1
                ORDER BY unit_size DESC, id ASC
            ");
            $skuStmt->execute([$baseProductId]);
            $rows = $skuStmt->fetchAll(PDO::FETCH_ASSOC);
            $order = 1;
            foreach ($rows as $p) {
                $ml = packagingSizeToMl($p['unit_size'] ?? 0, $p['unit_measure'] ?? 'ml');
                if ($ml <= 0) {
                    continue;
                }
                $sizeNum = (float) ($p['unit_size'] ?? 0);
                $sizeDisp = (abs($sizeNum - round($sizeNum)) < 0.001)
                    ? (string) (int) round($sizeNum)
                    : rtrim(rtrim(number_format($sizeNum, 2, '.', ''), '0'), '.');
                $meas = $p['unit_measure'] ?? 'ml';
                $label = trim(($p['variant'] ? $p['variant'] . ' · ' : '') . $sizeDisp . $meas);
                if ($label === '' || $label === $meas) {
                    $label = $p['product_name'] ?: ($ml . ' ml');
                }
                $skus[] = [
                    'product_id' => (int) $p['id'],
                    'product_code' => $p['product_code'],
                    'product_name' => $p['product_name'],
                    'packaging_size_ml' => $ml,
                    'packaging_label' => $label,
                    'priority_order' => $order++,
                ];
            }
        } catch (Throwable $e) {
            // base_product_id column missing — fall through
        }
    }

    // 2) packaging_rules on legacy product_id (or each SKU we already found)
    if (empty($skus) && $legacyProductId) {
        $rulesStmt = $db->prepare("
            SELECT packaging_size_ml, packaging_label, priority_order
            FROM packaging_rules
            WHERE product_id = ? AND is_active = 1
            ORDER BY priority_order ASC, packaging_size_ml DESC
        ");
        $rulesStmt->execute([$legacyProductId]);
        foreach ($rulesStmt->fetchAll(PDO::FETCH_ASSOC) as $rule) {
            $skus[] = [
                'product_id' => $legacyProductId,
                'product_code' => null,
                'product_name' => $recipe['product_name'],
                'packaging_size_ml' => (int) $rule['packaging_size_ml'],
                'packaging_label' => $rule['packaging_label'],
                'priority_order' => (int) ($rule['priority_order'] ?? 1),
            ];
        }
        // Single SKU product itself as a size
        if (empty($skus)) {
            $pStmt = $db->prepare("SELECT id, product_code, product_name, unit_size, unit_measure, variant FROM products WHERE id = ?");
            $pStmt->execute([$legacyProductId]);
            $p = $pStmt->fetch(PDO::FETCH_ASSOC);
            if ($p) {
                $ml = packagingSizeToMl($p['unit_size'] ?? 0, $p['unit_measure'] ?? 'ml');
                if ($ml > 0) {
                    $skus[] = [
                        'product_id' => (int) $p['id'],
                        'product_code' => $p['product_code'],
                        'product_name' => $p['product_name'],
                        'packaging_size_ml' => $ml,
                        'packaging_label' => $p['variant'] ?: ($ml . ' ml'),
                        'priority_order' => 1,
                    ];
                }
            }
        }
    }

    // 3) Aggregate packaging_rules for any SKU under base (if SKU list empty but rules exist)
    if (empty($skus) && $baseProductId) {
        try {
            $rulesStmt = $db->prepare("
                SELECT pr.product_id, pr.packaging_size_ml, pr.packaging_label, pr.priority_order,
                       p.product_code, p.product_name
                FROM packaging_rules pr
                JOIN products p ON p.id = pr.product_id
                WHERE p.base_product_id = ? AND pr.is_active = 1 AND p.is_active = 1
                ORDER BY pr.priority_order ASC, pr.packaging_size_ml DESC
            ");
            $rulesStmt->execute([$baseProductId]);
            foreach ($rulesStmt->fetchAll(PDO::FETCH_ASSOC) as $rule) {
                $skus[] = [
                    'product_id' => (int) $rule['product_id'],
                    'product_code' => $rule['product_code'],
                    'product_name' => $rule['product_name'],
                    'packaging_size_ml' => (int) $rule['packaging_size_ml'],
                    'packaging_label' => $rule['packaging_label'],
                    'priority_order' => (int) ($rule['priority_order'] ?? 1),
                ];
            }
        } catch (Throwable $e) { /* ignore */ }
    }

    // De-dupe by size_ml (prefer first / larger priority)
    $bySize = [];
    foreach ($skus as $s) {
        $key = (int) $s['packaging_size_ml'];
        if ($key <= 0) {
            continue;
        }
        if (!isset($bySize[$key])) {
            $bySize[$key] = $s;
        }
    }
    $skus = array_values($bySize);
    usort($skus, function ($a, $b) {
        return $b['packaging_size_ml'] <=> $a['packaging_size_ml'];
    });
    $order = 1;
    foreach ($skus as &$s) {
        $s['priority_order'] = $order++;
    }
    unset($s);

    if (empty($skus)) {
        return [
            'success' => false,
            'message' => 'No packaging SKUs found for this product. Add bottle sizes under the base product in Admin → Products.',
            'skus' => [],
            'base_product_id' => $baseProductId,
            'product_id' => $legacyProductId,
            'product_name' => $recipe['base_name'] ?: $recipe['product_name'],
        ];
    }

    return [
        'success' => true,
        'skus' => $skus,
        'base_product_id' => $baseProductId,
        'product_id' => $legacyProductId,
        'product_name' => $recipe['base_name'] ?: $recipe['product_name'],
    ];
}

/**
 * Generate packaging estimates for a production run.
 * Bulk-batch aware: resolves all SKU sizes under base_product_id.
 * Greedy default: fill largest bottles first, remainder to smaller sizes.
 * Floor staff should still confirm/adjust counts.
 */
function generatePackagingEstimate($db, $runId, $estimateType, $basisVolumeMl) {
    $resolved = resolvePackagingSkusForRun($db, $runId);
    if (!$resolved['success'] || empty($resolved['skus'])) {
        return [
            'success' => false,
            'message' => $resolved['message'] ?? 'No packaging sizes available for this run',
        ];
    }

    $rules = $resolved['skus'];
    $basisVolumeMl = (float) $basisVolumeMl;
    if ($basisVolumeMl <= 0) {
        return ['success' => false, 'message' => 'Basis volume must be greater than zero (ml)'];
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

    // Greedy allocation largest-first so total bottle volume ≤ net yield (no double-count)
    $remainingMl = $basisVolumeMl;
    $ruleCount = count($rules);
    foreach ($rules as $idx => $rule) {
        $sizeMl = (int) $rule['packaging_size_ml'];
        if ($sizeMl <= 0) {
            continue;
        }
        $units = (int) floor($remainingMl / $sizeMl);
        $used = $units * $sizeMl;
        $remainingMl = max(0, $remainingMl - $used);
        $isLast = ($idx === $ruleCount - 1);
        $rowRemainder = $isLast ? round($remainingMl, 2) : 0;

        $insertStmt->execute([
            $runId,
            $estimateType,
            $basisVolumeMl,
            $sizeMl,
            $rule['packaging_label'],
            $units,
            $rowRemainder,
        ]);

        $estimates[] = [
            'id' => (int) $db->lastInsertId(),
            'product_id' => (int) ($rule['product_id'] ?? 0) ?: null,
            'product_code' => $rule['product_code'] ?? null,
            'packaging_size_ml' => $sizeMl,
            'packaging_label' => $rule['packaging_label'],
            'label' => $rule['packaging_label'],
            'estimated_units' => $units,
            'volume_used_ml' => $used,
            'remainder_ml' => $rowRemainder,
        ];
    }

    return [
        'success' => true,
        'estimate_type' => $estimateType,
        'basis_volume_ml' => $basisVolumeMl,
        'base_product_id' => $resolved['base_product_id'],
        'product_id' => $resolved['product_id'],
        'product_name' => $resolved['product_name'],
        'remainder_ml' => round($remainingMl, 2),
        'skus' => $resolved['skus'],
        'estimates' => $estimates,
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
