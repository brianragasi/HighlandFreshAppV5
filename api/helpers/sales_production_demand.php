<?php

/**
 * Return approved Sales Order quantities that cannot yet be covered by
 * saleable Finished Goods stock. Stock is assigned to older approved orders
 * first so the same units are not promised to multiple orders.
 */
function hfApprovedSalesProductionDemand(PDO $db): array
{
    $rows = $db->query("
        SELECT so.id AS order_id,
               so.order_number,
               COALESCE(NULLIF(so.customer_name, ''), c.name, 'Customer') AS customer_name,
               so.delivery_date,
               so.approved_at,
               soi.product_id,
               p.product_code,
               p.product_name,
               p.base_product_id,
               p.base_unit,
               p.pieces_per_box,
               SUM(GREATEST(0, COALESCE(soi.quantity_ordered, 0) - COALESCE(soi.quantity_fulfilled, 0))) AS requested
        FROM sales_orders so
        JOIN sales_order_items soi ON soi.order_id = so.id
        JOIN products p ON p.id = soi.product_id
        LEFT JOIN customers c ON c.id = so.customer_id
        WHERE so.status = 'approved'
          AND soi.status NOT IN ('fulfilled', 'cancelled')
          AND p.is_active = 1
        GROUP BY so.id, soi.product_id
        HAVING requested > 0
        ORDER BY COALESCE(so.approved_at, so.created_at), so.id, soi.product_id
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        return [];
    }

    $productIds = array_values(array_unique(array_map(
        static fn(array $row): int => (int) $row['product_id'],
        $rows
    )));
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stockStmt = $db->prepare("
        SELECT fgi.product_id,
               COALESCE(SUM(
                   CASE
                       WHEN COALESCE(fgi.boxes_available, 0) > 0
                            OR COALESCE(fgi.pieces_available, 0) > 0
                            OR COALESCE(fgi.quantity_boxes, 0) > 0
                            OR COALESCE(fgi.quantity_pieces, 0) > 0
                       THEN (COALESCE(fgi.boxes_available, fgi.quantity_boxes, 0)
                             * COALESCE(p.pieces_per_box, 1))
                            + COALESCE(fgi.pieces_available, fgi.quantity_pieces, 0)
                       ELSE GREATEST(0, COALESCE(fgi.quantity_available, fgi.remaining_quantity, 0))
                   END
               ), 0) AS available
        FROM finished_goods_inventory fgi
        JOIN products p ON p.id = fgi.product_id
        WHERE fgi.product_id IN ({$placeholders})
          AND fgi.status = 'available'
          AND (fgi.expiry_date IS NULL OR fgi.expiry_date >= CURDATE())
        GROUP BY fgi.product_id
    ");
    $stockStmt->execute($productIds);
    $stockPool = array_fill_keys($productIds, 0);
    foreach ($stockStmt->fetchAll(PDO::FETCH_ASSOC) as $stock) {
        $stockPool[(int) $stock['product_id']] = max(0, (int) $stock['available']);
    }

    $recipeStmt = $db->prepare("
        SELECT id, recipe_code, product_name, bulk_yield_liters
        FROM master_recipes
        WHERE is_active = 1
          AND (product_id = ? OR (? > 0 AND base_product_id = ?))
        ORDER BY CASE WHEN product_id = ? THEN 0 ELSE 1 END, id
        LIMIT 1
    ");
    $recipeCache = [];
    $demands = [];

    foreach ($rows as $row) {
        $productId = (int) $row['product_id'];
        $requested = max(0, (int) $row['requested']);
        $allocated = min($requested, (int) ($stockPool[$productId] ?? 0));
        $stockPool[$productId] = max(0, (int) ($stockPool[$productId] ?? 0) - $allocated);
        $shortage = $requested - $allocated;
        if ($shortage <= 0) {
            continue;
        }

        if (!array_key_exists($productId, $recipeCache)) {
            $baseProductId = (int) ($row['base_product_id'] ?? 0);
            $recipeStmt->execute([$productId, $baseProductId, $baseProductId, $productId]);
            $recipeCache[$productId] = $recipeStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $recipe = $recipeCache[$productId];
        $piecesPerBox = max(1, (int) ($row['pieces_per_box'] ?? 1));
        $deliveryDate = (string) ($row['delivery_date'] ?? '');
        $daysUntilDue = $deliveryDate !== ''
            ? (int) floor((strtotime($deliveryDate . ' 23:59:59') - time()) / 86400)
            : null;

        $demands[] = [
            'demand_key' => ((int) $row['order_id']) . ':' . $productId,
            'order_id' => (int) $row['order_id'],
            'order_number' => $row['order_number'],
            'customer_name' => $row['customer_name'],
            'delivery_date' => $deliveryDate ?: null,
            'days_until_due' => $daysUntilDue,
            'product_id' => $productId,
            'product_code' => $row['product_code'],
            'product_name' => $row['product_name'],
            'base_unit' => $row['base_unit'] ?: 'piece',
            'pieces_per_box' => $piecesPerBox,
            'requested_quantity' => $requested,
            'stock_allocated' => $allocated,
            'shortage_quantity' => $shortage,
            'shortage_boxes' => intdiv($shortage, $piecesPerBox),
            'shortage_loose' => $shortage % $piecesPerBox,
            'recipe_id' => $recipe ? (int) $recipe['id'] : null,
            'recipe_code' => $recipe['recipe_code'] ?? null,
            'recipe_name' => $recipe['product_name'] ?? null,
            'recipe_bulk_yield_liters' => $recipe ? (float) ($recipe['bulk_yield_liters'] ?? 0) : null,
        ];
    }

    return $demands;
}
