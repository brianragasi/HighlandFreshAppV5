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
            // Get products available for sale (with inventory > 0)
            // Uses multi-unit system: boxes_available + pieces_available
            // Subtracts quantities reserved in pending/approved/preparing orders
            // INCLUDES items without product_id (manually packaged custom sizes)
            $category = getParam('category');
            $search = getParam('search');
            
            $sql = "
                SELECT 
                    COALESCE(p.id, CONCAT('fg-', fi.id)) as id,
                    COALESCE(p.product_code, CONCAT('FG-', fi.id)) as product_code,
                    COALESCE(p.product_name, fi.product_name) as product_name,
                    COALESCE(p.category, fi.product_type) as category,
                    COALESCE(p.variant, fi.variant, CONCAT(fi.size_ml, fi.unit)) as variant,
                    COALESCE(p.unit_size, fi.size_ml) as unit_size,
                    COALESCE(p.unit_measure, fi.unit) as unit_measure,
                    COALESCE(p.base_unit, 'piece') as base_unit,
                    COALESCE(p.selling_price, 0) as selling_price,
                    COALESCE(p.pieces_per_box, 1) as pieces_per_box,
                    p.id as real_product_id,
                    fi.id as fg_inventory_id,
                    GREATEST(0, 
                        SUM(CASE 
                            WHEN fi.status = 'available' AND fi.expiry_date > CURDATE() 
                            THEN COALESCE(NULLIF(fi.pieces_available, 0), NULLIF(fi.quantity_pieces, 0), fi.quantity_available, fi.remaining_quantity, 0)
                            ELSE 0 
                        END)
                        - COALESCE((
                            SELECT SUM(soi.quantity_ordered)
                            FROM sales_order_items soi
                            JOIN sales_orders so ON soi.order_id = so.id
                            WHERE (soi.product_id = p.id OR (soi.product_id IS NULL AND soi.product_name = fi.product_name))
                            AND so.status IN ('pending', 'approved', 'preparing')
                        ), 0)
                    ) as available_qty,
                    COALESCE((
                        SELECT SUM(soi.quantity_ordered)
                        FROM sales_order_items soi
                        JOIN sales_orders so ON soi.order_id = so.id
                        WHERE (soi.product_id = p.id OR (soi.product_id IS NULL AND soi.product_name = fi.product_name))
                        AND so.status IN ('pending', 'approved', 'preparing')
                    ), 0) as reserved_qty
                FROM finished_goods_inventory fi
                LEFT JOIN products p ON fi.product_id = p.id
                WHERE (p.id IS NULL OR p.is_active = 1)
                AND fi.status = 'available' 
                AND fi.expiry_date > CURDATE()
            ";
            $params = [];
            
            if ($category) {
                $sql .= " AND (p.category = ? OR fi.product_type = ?)";
                $params[] = $category;
                $params[] = $category;
            }
            
            if ($search) {
                $sql .= " AND ((p.product_name LIKE ? OR p.product_code LIKE ?) OR (fi.product_name LIKE ?))";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " GROUP BY COALESCE(p.id, fi.id), fi.id, p.product_code, p.product_name, fi.product_name, p.category, fi.product_type, p.variant, fi.variant, fi.size_ml, fi.unit, p.unit_size, p.unit_measure, p.base_unit, p.selling_price, p.pieces_per_box 
                      HAVING available_qty > 0 OR reserved_qty > 0 
                      ORDER BY product_name ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll();
            
            Response::success($products, 'Available products retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
