<?php
/**
 * Highland Fresh System - MRO Inventory API (Read-only for Maintenance)
 * 
 * Allows Maintenance Head to view MRO inventory levels
 * 
 * GET    - List MRO items / Get single item / Categories
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Maintenance Head, Warehouse, or GM role
$currentUser = Auth::requireRole(['maintenance_head', 'warehouse_raw', 'general_manager', 'purchaser']);

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            $action = getParam('action', 'list');
            $id = getParam('id');
            
            switch ($action) {
                case 'categories':
                    $stmt = $db->query("
                        SELECT mc.*, 
                               (SELECT COUNT(*) FROM mro_items WHERE category_id = mc.id AND is_active = 1) as item_count
                        FROM mro_categories mc
                        WHERE mc.is_active = 1
                        ORDER BY mc.category_name
                    ");
                    Response::success($stmt->fetchAll(), 'Categories retrieved');
                    break;
                    
                case 'detail':
                    if (!$id) {
                        Response::validationError(['id' => 'Item ID is required']);
                    }
                    
                    $stmt = $db->prepare("
                        SELECT mi.*, mc.category_name
                        FROM mro_items mi
                        LEFT JOIN mro_categories mc ON mi.category_id = mc.id
                        WHERE mi.id = ?
                    ");
                    $stmt->execute([$id]);
                    $item = $stmt->fetch();
                    
                    if (!$item) {
                        Response::notFound('Item not found');
                    }
                    
                    // Get inventory batches
                    $batchStmt = $db->prepare("
                        SELECT * FROM mro_inventory
                        WHERE mro_item_id = ? AND status IN ('available', 'partially_used')
                        ORDER BY received_date ASC
                    ");
                    $batchStmt->execute([$id]);
                    $item['batches'] = $batchStmt->fetchAll();
                    
                    // Get recent usage in repairs
                    $usageStmt = $db->prepare("
                        SELECT rpu.*, mr.repair_code, m.machine_name
                        FROM repair_parts_used rpu
                        JOIN machine_repairs mr ON rpu.repair_id = mr.id
                        JOIN machines m ON mr.machine_id = m.id
                        WHERE rpu.mro_item_id = ?
                        ORDER BY rpu.created_at DESC
                        LIMIT 10
                    ");
                    $usageStmt->execute([$id]);
                    $item['recent_usage'] = $usageStmt->fetchAll();
                    
                    Response::success($item, 'Item details retrieved');
                    break;
                    
                case 'low_stock':
                    $stmt = $db->query("
                        SELECT mi.*, mc.category_name,
                               (mi.minimum_stock - mi.current_stock) as deficit
                        FROM mro_items mi
                        LEFT JOIN mro_categories mc ON mi.category_id = mc.id
                        WHERE mi.is_active = 1 
                          AND mi.current_stock <= mi.minimum_stock
                        ORDER BY mi.is_critical DESC, (mi.minimum_stock - mi.current_stock) DESC
                    ");
                    Response::success($stmt->fetchAll(), 'Low stock items retrieved');
                    break;
                    
                default:
                    // List all MRO items
                    $categoryId = getParam('category_id');
                    $search = getParam('search');
                    $lowStockOnly = getParam('low_stock_only');
                    $page = (int) getParam('page', 1);
                    $limit = (int) getParam('limit', 50);
                    $offset = ($page - 1) * $limit;
                    
                    $where = "WHERE mi.is_active = 1";
                    $params = [];
                    
                    if ($categoryId) {
                        $where .= " AND mi.category_id = ?";
                        $params[] = $categoryId;
                    }
                    
                    if ($search) {
                        $where .= " AND (mi.item_name LIKE ? OR mi.item_code LIKE ?)";
                        $params[] = "%{$search}%";
                        $params[] = "%{$search}%";
                    }
                    
                    if ($lowStockOnly) {
                        $where .= " AND mi.current_stock <= mi.minimum_stock";
                    }
                    
                    // Count
                    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM mro_items mi {$where}");
                    $countStmt->execute($params);
                    $total = $countStmt->fetch()['total'];
                    
                    // Get items
                    $stmt = $db->prepare("
                        SELECT mi.*, mc.category_name,
                               CASE WHEN mi.current_stock <= mi.minimum_stock THEN 1 ELSE 0 END as is_low_stock
                        FROM mro_items mi
                        LEFT JOIN mro_categories mc ON mi.category_id = mc.id
                        {$where}
                        ORDER BY mi.is_critical DESC, is_low_stock DESC, mi.item_name ASC
                        LIMIT ? OFFSET ?
                    ");
                    $params[] = $limit;
                    $params[] = $offset;
                    $stmt->execute($params);
                    
                    Response::paginated($stmt->fetchAll(), $total, $page, $limit, 'MRO items retrieved');
            }
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("MRO Inventory API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
