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
require_once dirname(__DIR__) . '/helpers/stock_validation_support.php';
require_once dirname(__DIR__) . '/helpers/ingredient_opening_stock.php';
require_once dirname(__DIR__) . '/helpers/plain_text.php';
require_once dirname(__DIR__) . '/warehouse/raw/ingredient_stock_helpers.php';

// Require Purchaser or GM role
$currentUser = Auth::requireRole(['purchaser', 'general_manager']);

$action = getParam('action', 'stats');

try {
    $db = Database::getInstance()->getConnection();
    ensureStockValidationSupport($db);
    ensureIngredientOpeningStockSupport($db);
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action);
            break;
        case 'POST':
            handlePost($db, $action, $currentUser);
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
        case 'found_stock_price_checks':
            getFoundStockPriceChecks($db);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function getFoundStockPriceChecks(PDO $db): void {
    $stmt = $db->query("
        SELECT osr.id, osr.request_code, osr.quantity_to_add, osr.unit,
               osr.source_type, osr.source_reference, osr.received_date,
               osr.supplier_batch_no, osr.expiry_date, osr.reason, osr.created_at,
               i.ingredient_code, i.ingredient_name, i.is_perishable,
               s.supplier_name, s.supplier_code,
               si.reference_unit_price AS supplier_reference_price,
               i.unit_cost AS last_inventory_cost
        FROM ingredient_opening_stock_requests osr
        JOIN ingredients i ON i.id = osr.ingredient_id
        LEFT JOIN suppliers s ON s.id = osr.supplier_id
        LEFT JOIN supplier_ingredients si
          ON si.ingredient_id = osr.ingredient_id
         AND si.supplier_id = osr.supplier_id
         AND si.is_active = 1
        WHERE osr.status = 'pending' AND osr.price_status = 'pending'
        ORDER BY osr.created_at ASC
    ");
    Response::success($stmt->fetchAll(PDO::FETCH_ASSOC), 'Found-stock price checks retrieved');
}

function handlePost(PDO $db, string $action, array $currentUser): void {
    if (!in_array($action, ['verify_found_stock_price', 'reject_found_stock_price'], true)) {
        Response::error('Invalid action', 400);
    }

    $requestId = (int) getParam('request_id', 0);
    if ($requestId <= 0) Response::error('Found-stock request is required', 400);
    $notes = hfPlainText(getParam('notes'), 500, false);

    try {
        $db->beginTransaction();
        $stmt = $db->prepare("
            SELECT osr.*, i.ingredient_name
            FROM ingredient_opening_stock_requests osr
            JOIN ingredients i ON i.id = osr.ingredient_id
            WHERE osr.id = ? FOR UPDATE
        ");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$request || $request['status'] !== 'pending' || $request['price_status'] !== 'pending') {
            throw new RuntimeException('This price check was already completed or is no longer available');
        }

        if ($action === 'reject_found_stock_price') {
            if (mb_strlen($notes) < 5) throw new RuntimeException('Explain why the source or price cannot be verified');
            $update = $db->prepare("UPDATE ingredient_opening_stock_requests
                SET status = 'rejected', decision_notes = ?, decided_by = ?, decided_at = NOW()
                WHERE id = ? AND status = 'pending'");
            $update->execute(['Purchasing rejected the source/price: ' . $notes, (int) $currentUser['user_id'], $requestId]);
            writeProcurementNotification($db, 'warehouse_raw', 'found_stock_rejected',
                'Found-stock request returned',
                "{$request['request_code']} was rejected by Purchasing: {$notes}",
                'ingredient_opening_stock', $requestId);
            $message = 'Request rejected and returned to Warehouse';
        } else {
            $priceReference = hfPlainText(getParam('price_reference'), 100, false);
            if (mb_strlen($priceReference) < 3) throw new RuntimeException('Enter the PO, invoice, or approved valuation reference');
            try {
                $unitCost = hfParseBusinessDecimal(getParam('unit_cost'), 'Verified unit cost', 0.01, 99999999.99, 2);
            } catch (InvalidArgumentException $error) {
                throw new RuntimeException($error->getMessage());
            }
            $update = $db->prepare("UPDATE ingredient_opening_stock_requests
                SET unit_cost = ?, price_status = 'verified', price_verified_by = ?,
                    price_verified_at = NOW(), price_reference = ?
                WHERE id = ? AND status = 'pending' AND price_status = 'pending'");
            $update->execute([$unitCost, (int) $currentUser['user_id'], $priceReference, $requestId]);

            if (in_array((string) $request['qc_status'], ['approved', 'not_required'], true)) {
                writeProcurementNotification($db, 'general_manager', 'found_stock_ready_for_gm',
                    'Found stock ready for final review',
                    "{$request['request_code']}: price and required safety checks are complete.",
                    'ingredient_opening_stock', $requestId);
            }
            $message = 'Price verified. The request will move to GM after all required checks are complete.';
        }

        $db->prepare("UPDATE procurement_notifications SET is_read = 1
            WHERE target_role = 'purchaser' AND notification_type = 'found_stock_price_check'
              AND reference_type = 'ingredient_opening_stock' AND reference_id = ?")
            ->execute([$requestId]);
        logAudit($currentUser['user_id'], 'VERIFY_PRICE', 'ingredient_opening_stock_requests', $requestId, null, [
            'action' => $action,
            'notes' => $notes,
        ]);
        $db->commit();
        Response::success(['id' => $requestId], $message);
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        Response::error($error->getMessage(), 400);
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

    // Physically confirmed items that need a Purchasing decision now.
    $stmt = $db->query("
        SELECT COUNT(*) AS count
        FROM stock_validation_items svi
        JOIN stock_validations sv ON sv.id = svi.stock_validation_id
        WHERE sv.status IN ('open','partially_ordered')
          AND svi.is_queue_active = 1
          AND (
              svi.purchasing_decision = 'pending'
              OR (svi.purchasing_decision = 'deferred' AND svi.deferred_until <= CURDATE())
          )
          AND svi.quantity_needed > COALESCE((
              SELECT SUM(svip.quantity)
              FROM stock_validation_item_po svip
              JOIN purchase_orders po ON po.id = svip.po_id
              WHERE svip.stock_validation_item_id = svi.id
                AND po.status NOT IN ('cancelled','rejected')
          ), 0) + 0.0001
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
