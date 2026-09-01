<?php

require_once dirname(__DIR__) . '/warehouse/raw/ingredient_stock_helpers.php';

/**
 * A transparent, non-AI reorder forecast.
 *
 * projected stock = usable stock + active PO balance - (30-day daily use × lead time)
 * An early reorder is recommended only when stock is currently above the normal
 * threshold but is projected to reach that threshold before the fastest linked
 * supplier can deliver.
 */
function calculateIngredientEarlyReorder(array $row, ?int $supplierLeadDays = null): array {
    $usable = max(0, (float) ($row['usable_stock'] ?? $row['current_stock'] ?? 0));
    $minimum = max(0, (float) ($row['minimum_stock'] ?? 0));
    $reorder = max($minimum, (float) ($row['reorder_point'] ?? 0));
    $target = max($reorder, (float) ($row['maximum_stock'] ?? 0));
    if ($target <= $reorder && $reorder > 0) $target = $reorder * 2;
    $issued30 = max(0, (float) ($row['issued_quantity_30d'] ?? 0));
    $dailyUse = $issued30 / 30;
    $leadDays = max(0, $supplierLeadDays ?? (int) ($row['best_supplier_lead_days'] ?? $row['lead_time_days'] ?? 0));
    $onOrder = max(0, (float) ($row['active_po_balance'] ?? $row['on_order_quantity'] ?? 0));
    $leadTimeUse = $dailyUse * $leadDays;
    $projected = max(0, $usable + $onOrder - $leadTimeUse);
    $recommended = $usable > $reorder + 0.0005
        && $dailyUse > 0.000001
        && $leadDays > 0
        && $projected <= $reorder + 0.0005;
    $suggested = $recommended ? max(0, $target - $projected) : 0;

    return [
        'issued_quantity_30d' => round($issued30, 3),
        'average_daily_issue_30d' => round($dailyUse, 6),
        'forecast_window_days' => 30,
        'supplier_lead_days' => $leadDays,
        'on_order_quantity' => round($onOrder, 3),
        'lead_time_demand' => round($leadTimeUse, 3),
        'projected_stock_at_delivery' => round($projected, 3),
        'forecast_reorder_threshold' => round($reorder, 3),
        'forecast_target_stock' => round($target, 3),
        'early_reorder_recommended' => $recommended,
        'suggested_early_order_quantity' => round($suggested, 3),
    ];
}

function ingredientEarlyReorderEvidence(PDO $db, array $ingredientIds = []): array {
    if (!auditTableExists($db, 'ingredients') || !auditTableExists($db, 'inventory_transactions')) return [];
    $ingredientIds = array_values(array_unique(array_filter(array_map('intval', $ingredientIds))));
    $usableSql = usableIngredientBatchStockSql('i.id', 'forecast_usable_ib');
    $where = 'WHERE i.is_active = 1';
    $params = [];
    if ($ingredientIds) {
        $where .= ' AND i.id IN (' . implode(',', array_fill(0, count($ingredientIds), '?')) . ')';
        $params = $ingredientIds;
    }
    $stmt = $db->prepare("
        SELECT i.id AS ingredient_id, {$usableSql} AS usable_stock,
               i.minimum_stock, i.reorder_point, i.maximum_stock, i.lead_time_days,
               COALESCE((SELECT SUM(ABS(it.quantity))
                         FROM inventory_transactions it
                         WHERE it.item_type = 'ingredient' AND it.item_id = i.id
                           AND it.transaction_type = 'production_issue'
                           AND COALESCE(it.reference_type, '') <> 'manual_issue'
                           AND it.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS issued_quantity_30d,
               COALESCE((SELECT COUNT(*) FROM inventory_transactions it
                         WHERE it.item_type = 'ingredient' AND it.item_id = i.id
                           AND it.transaction_type = 'production_issue'
                           AND COALESCE(it.reference_type, '') <> 'manual_issue'
                           AND it.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS issue_transaction_count_30d,
               COALESCE((SELECT SUM(GREATEST(poi.quantity - COALESCE(poi.quantity_received,0)
                                  - COALESCE(poi.quantity_rejected,0) - COALESCE(poi.quantity_short_closed,0), 0))
                         FROM purchase_order_items poi JOIN purchase_orders po ON po.id = poi.po_id
                         WHERE poi.ingredient_id = i.id
                           AND po.status IN ('pending','approved','ordered','partial_received')), 0) AS active_po_balance,
               COALESCE((SELECT MIN(GREATEST(1, COALESCE(s.lead_time_days, i.lead_time_days, 7)))
                         FROM supplier_ingredients si JOIN suppliers s ON s.id = si.supplier_id
                         WHERE si.ingredient_id = i.id AND si.is_active = 1 AND s.is_active = 1),
                        NULLIF(i.lead_time_days,0), 7) AS best_supplier_lead_days
        FROM ingredients i
        {$where}
    ");
    $stmt->execute($params);
    $evidence = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $calculated = calculateIngredientEarlyReorder($row);
        $calculated['usable_stock'] = round((float) $row['usable_stock'], 3);
        $calculated['minimum_stock'] = (float) $row['minimum_stock'];
        $calculated['reorder_point'] = (float) $row['reorder_point'];
        $calculated['maximum_stock'] = (float) $row['maximum_stock'];
        $calculated['issue_transaction_count_30d'] = (int) ($row['issue_transaction_count_30d'] ?? 0);
        $evidence[(int) $row['ingredient_id']] = $calculated;
    }
    return $evidence;
}
