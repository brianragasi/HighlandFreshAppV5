<?php

/** Explain why a material must stay active before any archive write occurs. */
function hfIngredientArchiveBlockers(PDO $db, int $ingredientId): array
{
    $blockers = [];

    $sku = $db->prepare("SELECT p.product_code, COALESCE(bp.name, p.product_name) AS product_name
        FROM sku_packaging_bom_items bom
        JOIN products p ON p.id = bom.product_id AND p.is_active = 1
        LEFT JOIN base_products bp ON bp.id = p.base_product_id
        WHERE bom.ingredient_id = ? AND bom.is_active = 1
          AND (bp.id IS NULL OR bp.is_active = 1)
        ORDER BY p.product_code LIMIT 1");
    $sku->execute([$ingredientId]);
    if ($linked = $sku->fetch(PDO::FETCH_ASSOC)) {
        $blockers[] = sprintf('Used by active SKU %s (%s). Change its packaging BOM first.',
            $linked['product_code'], $linked['product_name']);
    } else {
        $primary = $db->prepare("SELECT p.product_code FROM products p
            LEFT JOIN base_products bp ON bp.id = p.base_product_id
            WHERE p.primary_container_id = ? AND p.is_active = 1
              AND (bp.id IS NULL OR bp.is_active = 1)
            ORDER BY p.product_code LIMIT 1");
        $primary->execute([$ingredientId]);
        if ($code = $primary->fetchColumn()) {
            $blockers[] = sprintf('Selected as the primary package for active SKU %s. Change its package first.', $code);
        }
    }

    $recipe = $db->prepare("SELECT r.recipe_code, COALESCE(bp.name, r.product_name) AS product_name
        FROM recipe_ingredients ri
        JOIN master_recipes r ON r.id = ri.recipe_id AND r.is_active = 1
        LEFT JOIN base_products bp ON bp.id = r.base_product_id
        WHERE ri.ingredient_id = ? AND (bp.id IS NULL OR bp.is_active = 1)
        ORDER BY r.recipe_code LIMIT 1");
    $recipe->execute([$ingredientId]);
    if ($linked = $recipe->fetch(PDO::FETCH_ASSOC)) {
        $blockers[] = sprintf('Used by active recipe %s (%s). Change its BOM first.',
            $linked['recipe_code'], $linked['product_name']);
    }

    $order = $db->prepare("SELECT po.po_number FROM purchase_order_items poi
        JOIN purchase_orders po ON po.id = poi.po_id
        WHERE poi.ingredient_id = ?
          AND po.status IN ('draft', 'approved', 'ordered', 'partial_received')
        ORDER BY po.id DESC LIMIT 1");
    $order->execute([$ingredientId]);
    if ($poNumber = $order->fetchColumn()) {
        $blockers[] = sprintf('Included in open purchase order %s. Close or revise it first.', $poNumber);
    }

    return $blockers;
}

function hfSupplierOpenPurchaseOrderCount(PDO $db, int $supplierId): int
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM purchase_orders
        WHERE supplier_id = ?
          AND status IN ('draft', 'approved', 'ordered', 'partial_received')");
    $stmt->execute([$supplierId]);
    return (int) $stmt->fetchColumn();
}
