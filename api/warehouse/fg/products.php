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
                        product_id, 
                        SUM(quantity) as total_qty,
                        SUM(CASE WHEN status = 'available' THEN quantity_available ELSE 0 END) as available_qty
                    FROM finished_goods_inventory
                    WHERE expiry_date > CURDATE()
                    GROUP BY product_id
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
            
            // Get inventory by batch
            $invStmt = $db->prepare("
                SELECT 
                    fi.id, fi.batch_id, fi.quantity, fi.quantity_available, fi.status,
                    fi.manufacturing_date as production_date, fi.expiry_date,
                    DATEDIFF(fi.expiry_date, CURDATE()) as days_to_expiry
                FROM finished_goods_inventory fi
                WHERE fi.product_id = ? AND fi.quantity_available > 0
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
            $category = getParam('category');
            $search = getParam('search');
            
            $sql = "
                SELECT 
                    p.id,
                    p.product_code,
                    p.product_name,
                    p.category,
                    p.variant,
                    p.unit_size,
                    p.unit_measure,
                    p.base_unit,
                    p.selling_price,
                    p.pieces_per_box,
                    COALESCE(SUM(CASE WHEN fi.status = 'available' AND fi.expiry_date > CURDATE() THEN fi.quantity_available ELSE 0 END), 0) as available_qty
                FROM products p
                LEFT JOIN finished_goods_inventory fi ON p.id = fi.product_id
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
            
            $sql .= " GROUP BY p.id HAVING available_qty > 0 ORDER BY p.product_name ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll();
            
            Response::success($products, 'Available products retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
