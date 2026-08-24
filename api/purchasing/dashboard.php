<?php
/**
 * Highland Fresh System - Purchasing Dashboard API
 * 
 * GET - Dashboard stats, PRS inbox, recent POs, pending requisitions
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/helpers/procurement_notifications.php';
require_once dirname(__DIR__) . '/warehouse/raw/ingredient_stock_helpers.php';

// Require Purchaser or GM role
$currentUser = Auth::requireRole(['purchaser', 'general_manager']);

$action = getParam('action', 'stats');

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
    error_log("Purchasing Dashboard API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'stats':
            getDashboardStats($db);
            break;
        case 'low_stock':
            getLowStockAlerts($db);
            break;
        case 'recent_pos':
            getRecentPOs($db);
            break;
        case 'pending_requisitions':
            getPendingRequisitions($db);
            break;
        case 'monthly_spending':
            getMonthlySpending($db);
            break;
        case 'notifications':
            getPurchaserNotifications($db);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function getDashboardStats($db) {
    $stats = [];
    $usableIngredientStockSql = usableIngredientBatchStockSql('i.id', 'stats_ib');
    
    // Total Active Suppliers
    $stmt = $db->query("SELECT COUNT(*) as count FROM suppliers WHERE is_active = 1");
    $stats['total_suppliers'] = (int) $stmt->fetch()['count'];
    
    // Total POs this month
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM purchase_orders 
        WHERE YEAR(order_date) = YEAR(CURDATE()) 
        AND MONTH(order_date) = MONTH(CURDATE())
    ");
    $stats['pos_this_month'] = (int) $stmt->fetch()['count'];
    
    // Pending POs (pending or approved, not received)
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM purchase_orders 
        WHERE status IN ('pending', 'approved', 'ordered', 'partial_received')
    ");
    $stats['pending_pos'] = (int) $stmt->fetch()['count'];
    
    // Total spending this month
    $stmt = $db->query("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM purchase_orders 
        WHERE YEAR(order_date) = YEAR(CURDATE()) 
        AND MONTH(order_date) = MONTH(CURDATE())
        AND status != 'cancelled'
    ");
    $stats['monthly_spending'] = (float) $stmt->fetch()['total'];
    
    // Low stock ingredients count
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM ingredients i
        WHERE i.is_active = 1
        AND {$usableIngredientStockSql} <= " . StockRule::lowThresholdSql('i.reorder_point', 'i.minimum_stock') . "
    ");
    $stats['low_stock_ingredients'] = (int) $stmt->fetch()['count'];

    // Low stock MRO items count
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM mro_items
        WHERE is_active = 1
        AND current_stock <= " . StockRule::lowThresholdSql('reorder_point', 'minimum_stock') . "
    ");
    $stats['low_stock_mro'] = (int) $stmt->fetch()['count'];

    // Items that have reached the warehouse's physical minimum. This is more
    // urgent than the broader reorder list shown elsewhere on the dashboard.
    $stmt = $db->query("
        SELECT (
            (SELECT COUNT(*) FROM ingredients i
             WHERE i.is_active = 1 AND {$usableIngredientStockSql} <= i.minimum_stock) +
            (SELECT COUNT(*) FROM mro_items
             WHERE is_active = 1 AND current_stock <= minimum_stock)
        ) AS count
    ");
    $stats['critical_stock_items'] = (int) $stmt->fetch()['count'];
    
    // Pending material requisitions
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM material_requisitions 
        WHERE status IN ('pending', 'approved')
    ");
    $stats['pending_requisitions'] = (int) $stmt->fetch()['count'];

    // Submitted PRS records routed from Warehouse Raw to Purchasing
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM purchase_requests
        WHERE status IN ('pending', 'approved')
          AND id NOT IN (
              SELECT COALESCE(purchase_request_id, 0)
              FROM purchase_orders
              WHERE status NOT IN ('cancelled', 'rejected')
                AND purchase_request_id IS NOT NULL
          )
    ");
    $stats['prs_inbox'] = (int) $stmt->fetch()['count'];
    
    // Unpaid POs amount
    $stmt = $db->query("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM purchase_orders 
        WHERE payment_status IN ('unpaid', 'partial')
        AND status != 'cancelled'
    ");
    $stats['unpaid_amount'] = (float) $stmt->fetch()['total'];
    
    Response::success($stats, 'Dashboard stats retrieved');
}

function getLowStockAlerts($db) {
    // Get ingredients at/below reorder point (LOW or OUT_OF_STOCK). Uses the
    // shared StockRule so the threshold matches every other surface instead
    // of the old per-tier critical/low/reorder breakdown.
    $ingThreshold = StockRule::lowThresholdSql('i.reorder_point', 'i.minimum_stock');
    $usableIngredientStockSql = usableIngredientBatchStockSql('i.id', 'alert_ib');
    $stmt = $db->query("
        SELECT
            i.id,
            i.ingredient_code,
            i.ingredient_name,
            i.unit_of_measure,
            {$usableIngredientStockSql} as current_stock,
            i.current_stock as current_stock_on_file,
            i.reorder_point,
            i.minimum_stock,
            i.lead_time_days,
            i.unit_cost,
            ic.category_name,
            'ingredient' as item_type,
            " . StockRule::statusCaseSql($usableIngredientStockSql, 'i.reorder_point', 'i.minimum_stock') . " as stock_status
        FROM ingredients i
        LEFT JOIN ingredient_categories ic ON i.category_id = ic.id
        WHERE i.is_active = 1
        AND {$usableIngredientStockSql} <= {$ingThreshold}
        ORDER BY
            CASE
                WHEN {$usableIngredientStockSql} <= 0 THEN 1
                ELSE 2
            END,
            i.ingredient_name ASC
    ");
    $ingredients = $stmt->fetchAll();

    // Get MRO items LOW or OUT_OF_STOCK. MRO's reorder_point is usually NULL,
    // so the rule falls back to minimum_stock — consistent with ingredients.
    $mroThreshold = StockRule::lowThresholdSql('m.reorder_point', 'm.minimum_stock');
    $stmtMro = $db->query("
        SELECT
            m.id,
            m.item_code,
            m.item_name,
            m.unit_of_measure,
            m.current_stock,
            m.reorder_point,
            m.minimum_stock,
            m.lead_time_days,
            m.unit_cost,
            mc.category_name,
            'mro' as item_type,
            " . StockRule::statusCaseSql('m.current_stock', 'm.reorder_point', 'm.minimum_stock') . " as stock_status
        FROM mro_items m
        LEFT JOIN mro_categories mc ON m.category_id = mc.id
        WHERE m.is_active = 1
        AND m.current_stock <= {$mroThreshold}
        ORDER BY m.current_stock ASC
    ");
    $mroItems = $stmtMro->fetchAll();

    $allAlerts = array_merge($ingredients, $mroItems);
    
    Response::success($allAlerts, 'Low stock alerts retrieved');
}

function getRecentPOs($db) {
    $limit = getParam('limit', 10);
    
    $stmt = $db->prepare("
        SELECT 
            po.id,
            po.po_number,
            po.order_date,
            po.expected_delivery,
            po.status,
            po.total_amount,
            po.payment_status,
            po.notes,
            s.supplier_name,
            s.supplier_code,
            u.full_name as created_by_name,
            (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = po.id) as item_count
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.status IN ('draft', 'pending', 'approved', 'ordered', 'partial_received')
        ORDER BY po.created_at DESC, po.id DESC
        LIMIT ?
    ");
    $stmt->execute([(int) $limit]);
    $orders = $stmt->fetchAll();
    
    Response::success($orders, 'Recent purchase orders retrieved');
}

function getPendingRequisitions($db) {
    $stmt = $db->query("
        SELECT 
            mr.id,
            mr.requisition_code,
            mr.department,
            mr.priority,
            mr.needed_by_date,
            mr.purpose,
            mr.total_items,
            mr.status,
            mr.created_at,
            u.full_name as requested_by_name,
            (SELECT GROUP_CONCAT(ri.item_name SEPARATOR ', ') 
             FROM requisition_items ri 
             WHERE ri.requisition_id = mr.id 
             LIMIT 3) as item_names
        FROM material_requisitions mr
        LEFT JOIN users u ON mr.requested_by = u.id
        WHERE mr.status IN ('pending', 'approved', 'partial')
        ORDER BY 
            FIELD(mr.priority, 'urgent', 'high', 'normal', 'low'),
            mr.created_at ASC
    ");
    $requisitions = $stmt->fetchAll();
    
    Response::success($requisitions, 'Pending requisitions retrieved');
}

function getMonthlySpending($db) {
    $months = getParam('months', 6);
    
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(order_date, '%Y-%m') as month,
            DATE_FORMAT(order_date, '%b %Y') as month_label,
            COUNT(*) as po_count,
            COALESCE(SUM(total_amount), 0) as total_spending
        FROM purchase_orders
        WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
        AND status != 'cancelled'
        GROUP BY DATE_FORMAT(order_date, '%Y-%m'), DATE_FORMAT(order_date, '%b %Y')
        ORDER BY month ASC
    ");
    $stmt->execute([(int) $months]);
    $spending = $stmt->fetchAll();
    
    Response::success($spending, 'Monthly spending retrieved');
}

/**
 * Fetch unread procurement notifications for the Purchaser role.
 * Includes manually submitted PRS records and other procurement events.
 */
function getPurchaserNotifications($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `procurement_notifications` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `target_role` VARCHAR(50) NOT NULL,
            `notification_type` VARCHAR(50) NOT NULL,
            `title` VARCHAR(150) NOT NULL,
            `message` TEXT NOT NULL,
            `reference_type` VARCHAR(50) DEFAULT NULL,
            `reference_id` INT(11) DEFAULT NULL,
            `is_read` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_procurement_notifications_role` (`target_role`, `is_read`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    closeMisroutedProcurementNotifications($db);

    $stmt = $db->prepare("
        SELECT id, notification_type, title, message, reference_type, reference_id, created_at
        FROM procurement_notifications
        WHERE target_role = 'purchaser'
          AND is_read = 0
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $notifications = $stmt->fetchAll();

    Response::success($notifications, 'Notifications retrieved');
}
