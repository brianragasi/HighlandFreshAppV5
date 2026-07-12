<?php
/**
 * Highland Fresh System - Finished Goods Products API
 * 
 * Product listing for Finished Goods Warehouse
 * Used by Sales module for order creation
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

// Allow access for warehouse, sales, and GM roles
$currentUser = Auth::requireRole(['warehouse_fg', 'sales_custodian', 'general_manager', 'cashier']);

$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Warehouse FG Products API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            $category = getParam('category');
            $search = getParam('search');
            $active = getParam('active', '1');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 50);
            $offset = ($page - 1) * $limit;
            
            $sql = "
                SELECT 
                    p.id,
                    p.product_code,
                    p.product_name,
                    p.category,
                    p.variant,
                    p.description,
                    p.unit_size,
                    p.unit_measure,
                    p.base_unit,
                    p.box_unit,
                    p.pieces_per_box,
                    p.unit_price,
                    p.selling_price,
                    p.cost_price,
                    p.shelf_life_days,
                    p.is_active,
                    COALESCE(inv.available_qty, 0) as available_qty,
                    COALESCE(inv.total_qty, 0) as total_qty
                FROM products p
                LEFT JOIN (
                    SELECT 
                        fi.product_id, 
                        SUM(fi.quantity) as total_qty,
                        SUM(CASE WHEN fi.status = 'available' 
                            THEN (COALESCE(NULLIF(fi.boxes_available, 0), NULLIF(fi.quantity_boxes, 0), 0) * COALESCE(p2.pieces_per_box, 1)) 
                                + COALESCE(NULLIF(fi.pieces_available, 0), NULLIF(fi.quantity_pieces, 0), fi.quantity_available, fi.remaining_quantity, 0)
                            ELSE 0 END) as available_qty
                    FROM finished_goods_inventory fi
                    JOIN products p2 ON fi.product_id = p2.id
                    WHERE fi.expiry_date > CURDATE()
                    GROUP BY fi.product_id
                ) inv ON p.id = inv.product_id
                WHERE 1=1
            ";
            $params = [];
            
            if ($active !== 'all') {
                $sql .= " AND p.is_active = ?";
                $params[] = $active;
            }
            
            if ($category) {
                $sql .= " AND p.category = ?";
                $params[] = $category;
            }
            
            if ($search) {
                $sql .= " AND (p.product_name LIKE ? OR p.product_code LIKE ? OR p.variant LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Get total count
            $countSql = preg_replace('/SELECT.*?FROM/s', 'SELECT COUNT(DISTINCT p.id) as total FROM', $sql);
            $countStmt = $db->prepare($countSql);
            $countStmt->execute($params);
            $result = $countStmt->fetch();
            $total = $result ? $result['total'] : 0;
            
            $sql .= " ORDER BY p.category ASC, p.product_name ASC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll();
            
            Response::paginated($products, $total, $page, $limit, 'Products retrieved');
            break;
            
        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('Product ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT p.*
                FROM products p
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                Response::notFound('Product not found');
            }
            
            // Get inventory by batch (multi-unit)
            $invStmt = $db->prepare("
                SELECT 
                    fi.id, fi.batch_id, fi.quantity, 
                    COALESCE(NULLIF(fi.boxes_available, 0), NULLIF(fi.quantity_boxes, 0), 0) as boxes_available,
                    COALESCE(NULLIF(fi.pieces_available, 0), NULLIF(fi.quantity_pieces, 0), fi.quantity_available, fi.remaining_quantity, 0) as pieces_available,
                    (COALESCE(NULLIF(fi.boxes_available, 0), NULLIF(fi.quantity_boxes, 0), 0) * COALESCE(p.pieces_per_box, 1)) 
                        + COALESCE(NULLIF(fi.pieces_available, 0), NULLIF(fi.quantity_pieces, 0), fi.quantity_available, fi.remaining_quantity, 0) as quantity_available,
                    fi.status, fi.manufacturing_date as production_date, fi.expiry_date,
                    DATEDIFF(fi.expiry_date, CURDATE()) as days_to_expiry
                FROM finished_goods_inventory fi
                JOIN products p ON fi.product_id = p.id
                WHERE fi.product_id = ? 
                AND (COALESCE(NULLIF(fi.boxes_available, 0), NULLIF(fi.quantity_boxes, 0), 0) > 0 
                     OR COALESCE(NULLIF(fi.pieces_available, 0), NULLIF(fi.quantity_pieces, 0), fi.quantity_available, fi.remaining_quantity, 0) > 0)
                ORDER BY fi.expiry_date ASC
            ");
            $invStmt->execute([$id]);
            $product['inventory'] = $invStmt->fetchAll();
            
            // Calculate totals
            $product['total_qty'] = array_sum(array_column($product['inventory'], 'quantity'));
            $product['available_qty'] = array_sum(array_column($product['inventory'], 'quantity_available'));
            
            Response::success($product, 'Product details retrieved');
            break;
            
        case 'categories':
            $stmt = $db->prepare("
                SELECT DISTINCT category, COUNT(*) as product_count
                FROM products
                WHERE is_active = 1
                GROUP BY category
                ORDER BY category ASC
            ");
            $stmt->execute();
            $categories = $stmt->fetchAll();
            
            Response::success($categories, 'Categories retrieved');
            break;
            
        case 'for_sale':
            // Products available for sale, aggregated by product SKU.
            // Authoritative sellable unit = BASE units (bottles/packs/pieces):
            //   on_hand = SUM(quantity_available) for available, non-expired lots
            //   reserved = SUM(quantity_ordered) on open sales orders
            //   available_qty = max(0, on_hand - reserved)
            // Pack config (pieces_per_box, base_unit, box_unit) is for UI conversion only.
            $category = getParam('category');
            $search = getParam('search');

            // Linked products (normal path)
            $sql = "
                SELECT
                    p.id,
                    p.product_code,
                    p.product_name,
                    p.category,
                    p.variant,
                    p.unit_size,
                    p.unit_measure,
                    COALESCE(p.base_unit, 'piece') AS base_unit,
                    COALESCE(p.box_unit, 'box') AS box_unit,
                    COALESCE(p.selling_price, 0) AS selling_price,
                    COALESCE(p.pieces_per_box, 1) AS pieces_per_box,
                    p.id AS real_product_id,
                    COALESCE(stock.on_hand, 0) AS on_hand_qty,
                    COALESCE(res.reserved_qty, 0) AS reserved_qty,
                    GREATEST(0, COALESCE(stock.on_hand, 0) - COALESCE(res.reserved_qty, 0)) AS available_qty
                FROM products p
                INNER JOIN (
                    SELECT fi.product_id,
                           SUM(GREATEST(0, COALESCE(fi.quantity_available, 0))) AS on_hand
                    FROM finished_goods_inventory fi
                    WHERE fi.product_id IS NOT NULL
                      AND fi.status = 'available'
                      AND (fi.expiry_date IS NULL OR fi.expiry_date > CURDATE())
                      AND COALESCE(fi.quantity_available, 0) > 0
                    GROUP BY fi.product_id
                ) stock ON stock.product_id = p.id
                LEFT JOIN (
                    SELECT soi.product_id,
                           SUM(COALESCE(soi.quantity_ordered, 0)) AS reserved_qty
                    FROM sales_order_items soi
                    JOIN sales_orders so ON soi.order_id = so.id
                    WHERE soi.product_id IS NOT NULL
                      AND so.status IN ('pending', 'approved', 'preparing')
                    GROUP BY soi.product_id
                ) res ON res.product_id = p.id
                WHERE p.is_active = 1
            ";
            $params = [];

            if ($category) {
                $sql .= " AND p.category = ?";
                $params[] = $category;
            }
            if ($search) {
                $sql .= " AND (p.product_name LIKE ? OR p.product_code LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $sql .= " HAVING available_qty > 0 OR reserved_qty > 0
                      ORDER BY p.product_name ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Enrich with pack breakdown for UI (purely presentational)
            foreach ($products as &$prod) {
                $ppb = max(1, (int) ($prod['pieces_per_box'] ?? 1));
                $avail = (int) ($prod['available_qty'] ?? 0);
                $onHand = (int) ($prod['on_hand_qty'] ?? 0);
                $reserved = (int) ($prod['reserved_qty'] ?? 0);
                $base = $prod['base_unit'] ?: 'piece';
                $box = $prod['box_unit'] ?: 'box';

                $prod['available_qty'] = $avail;
                $prod['on_hand_qty'] = $onHand;
                $prod['reserved_qty'] = $reserved;
                $prod['available_boxes'] = $ppb > 1 ? intdiv($avail, $ppb) : 0;
                $prod['available_loose'] = $ppb > 1 ? ($avail % $ppb) : $avail;
                $basePlural = $base . (substr($base, -1) === 's' ? 'es' : 's');
                if (in_array(strtolower($base), ['piece', 'bottle', 'pack', 'block', 'cup', 'tub', 'jar'], true)) {
                    $basePlural = $base . 's';
                }
                $boxOne = rtrim($box, 's');
                $prod['pack_config_label'] = $ppb > 1
                    ? ("1 {$boxOne} = {$ppb} {$basePlural}")
                    : ("Sold as individual {$basePlural} (no multi-pack)");
                $prod['quantity_unit'] = $base; // authoritative unit for available_qty / orders
                $prod['quantity_unit_label'] = $basePlural;
            }
            unset($prod);

            Response::success($products, 'Available products retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
