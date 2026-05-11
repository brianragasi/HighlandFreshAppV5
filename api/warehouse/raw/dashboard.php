<?php
/**
 * Highland Fresh System - Warehouse Raw Dashboard API
 *
 * REVISED: Updated for new schema (Feb 2026)
 * - Uses milk_receiving instead of milk_deliveries
 * - Uses raw_milk_inventory instead of tank_milk_batches
 * - Uses material_requisitions instead of ingredient_requisitions
 *
 * GET - Get warehouse raw dashboard statistics
 *
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

/**
 * Backfill raw milk inventory rows for accepted QC records that are missing inventory.
 * This keeps warehouse dashboard and storage queue consistent with QC acceptance.
 */
function syncAcceptedMilkInventory($db) {
    $stmt = $db->prepare(" 
        INSERT INTO raw_milk_inventory (
            batch_code, receiving_id, qc_test_id, milk_type_id, tank_id,
            volume_liters, remaining_liters, received_date, expiry_date,
            fat_percentage, grade, unit_cost, status, qc_status, received_by, notes
        )
        SELECT
            CONCAT('RAW-RCV-', LPAD(mr.id, 6, '0')) as batch_code,
            mr.id as receiving_id,
            qmt.id as qc_test_id,
            mr.milk_type_id,
            NULL as tank_id,
            COALESCE(NULLIF(mr.accepted_liters, 0), mr.volume_liters) as volume_liters,
            COALESCE(NULLIF(mr.accepted_liters, 0), mr.volume_liters) as remaining_liters,
            mr.receiving_date as received_date,
            DATE_ADD(mr.receiving_date, INTERVAL 2 DAY) as expiry_date,
            qmt.fat_percentage,
            qmt.grade,
            qmt.final_price_per_liter as unit_cost,
            'available' as status,
            'approved' as qc_status,
            qmt.tested_by as received_by,
            CONCAT('Backfilled from accepted QC receiving ', mr.receiving_code) as notes
        FROM milk_receiving mr
        JOIN (
            SELECT receiving_id, MAX(id) as latest_test_id
            FROM qc_milk_tests
            WHERE is_accepted = 1
            GROUP BY receiving_id
        ) latest ON latest.receiving_id = mr.id
        JOIN qc_milk_tests qmt ON qmt.id = latest.latest_test_id
        LEFT JOIN raw_milk_inventory rmi ON rmi.receiving_id = mr.id
        WHERE mr.status = 'accepted'
          AND COALESCE(NULLIF(mr.accepted_liters, 0), mr.volume_liters) > 0
          AND rmi.id IS NULL
    ");
    $stmt->execute();
}

// Require Warehouse Raw role
$currentUser = Auth::requireRole(['warehouse_raw', 'general_manager']);

if ($requestMethod !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    $db = Database::getInstance()->getConnection();
    ensureProcurementNotificationSupport($db);
    syncAcceptedMilkInventory($db);

    $today = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));

    // === RAW MILK STATISTICS ===

    // Total milk in storage (from raw_milk_inventory - revised schema)
    $milkStats = $db->prepare("
        SELECT
            COALESCE(SUM(remaining_liters), 0) as total_liters,
            COUNT(DISTINCT tank_id) as tanks_with_milk,
            COUNT(*) as batch_count
        FROM raw_milk_inventory
        WHERE status IN ('available', 'reserved')
        AND qc_status = 'approved'
    ");
    $milkStats->execute();
    $milkData = $milkStats->fetch();

    // Storage tanks overview
    $tankStats = $db->prepare("
        SELECT
            COUNT(*) as total_tanks,
            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN status = 'in_use' THEN 1 ELSE 0 END) as in_use,
            SUM(CASE WHEN status IN ('cleaning', 'maintenance') THEN 1 ELSE 0 END) as offline,
            COALESCE(SUM(capacity_liters), 0) as total_capacity,
            COALESCE(SUM(current_volume), 0) as current_volume
        FROM storage_tanks
        WHERE is_active = 1
    ");
    $tankStats->execute();
    $tankData = $tankStats->fetch();

    // Milk expiring soon (within 2 days) - from raw_milk_inventory
    $expiringMilk = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(remaining_liters), 0) as liters
        FROM raw_milk_inventory
        WHERE status IN ('available', 'reserved')
        AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
    ");
    $expiringMilk->execute();
    $expiringMilkData = $expiringMilk->fetch();

    // === INGREDIENTS STATISTICS ===

    // Low stock ingredients
    $lowStockIngredients = $db->prepare("
        SELECT COUNT(*) as count
        FROM ingredients
        WHERE is_active = 1 AND current_stock <= minimum_stock
    ");
    $lowStockIngredients->execute();
    $lowStockData = $lowStockIngredients->fetch();

    // Ingredients expiring soon (within 7 days)
    $expiringIngredients = $db->prepare("
        SELECT COUNT(DISTINCT ingredient_id) as count
        FROM ingredient_batches
        WHERE status IN ('available', 'partially_used')
        AND qc_status = 'approved'
        AND expiry_date IS NOT NULL
        AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ");
    $expiringIngredients->execute();
    $expiringIngData = $expiringIngredients->fetch();

    // Total ingredient items
    $totalIngredients = $db->prepare("
        SELECT COUNT(*) as count FROM ingredients WHERE is_active = 1
    ");
    $totalIngredients->execute();
    $totalIngData = $totalIngredients->fetch();

    // === MRO STATISTICS ===

    // Low stock MRO items
    $lowStockMRO = $db->prepare("
        SELECT COUNT(*) as count
        FROM mro_items
        WHERE is_active = 1 AND current_stock <= minimum_stock
    ");
    $lowStockMRO->execute();
    $lowStockMROData = $lowStockMRO->fetch();

    // Critical MRO items low stock
    $criticalMRO = $db->prepare("
        SELECT COUNT(*) as count
        FROM mro_items
        WHERE is_active = 1 AND is_critical = 1 AND current_stock <= minimum_stock
    ");
    $criticalMRO->execute();
    $criticalMROData = $criticalMRO->fetch();

    // === REQUISITIONS STATISTICS (using material_requisitions - revised schema) ===

    // Pending requisitions to fulfill
    $pendingRequisitions = $db->prepare("
        SELECT
            COUNT(*) as count,
            SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
            SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as `high_priority`
        FROM material_requisitions
        WHERE status = 'approved'
    ");
    $pendingRequisitions->execute();
    $pendingReqData = $pendingRequisitions->fetch();

    // Today's fulfilled requisitions
    $todayFulfilled = $db->prepare("
        SELECT COUNT(*) as count
        FROM material_requisitions
        WHERE status = 'fulfilled' AND DATE(fulfilled_at) = ?
    ");
    $todayFulfilled->execute([$today]);
    $todayFulfilledData = $todayFulfilled->fetch();

    // === RECENT ACTIVITY ===

    // Recent transactions (using revised transaction types)
    $recentTransactions = $db->prepare("
        SELECT
            it.transaction_code,
            it.transaction_type,
            it.item_type,
            it.quantity,
            it.unit_of_measure,
            it.created_at,
            u.first_name,
            u.last_name,
            CASE
                WHEN it.item_type = 'ingredient' THEN i.ingredient_name
                WHEN it.item_type = 'mro' THEN m.item_name
                WHEN it.item_type = 'raw_milk' THEN 'Raw Milk'
                ELSE it.item_type
            END as item_name
        FROM inventory_transactions it
        JOIN users u ON it.performed_by = u.id
        LEFT JOIN ingredients i ON it.item_type = 'ingredient' AND it.item_id = i.id
        LEFT JOIN mro_items m ON it.item_type = 'mro' AND it.item_id = m.id
        ORDER BY it.created_at DESC
        LIMIT 10
    ");
    $recentTransactions->execute();
    $recentTxList = $recentTransactions->fetchAll();

    // Pending requisitions list (using material_requisitions - revised schema)
    $pendingReqsList = $db->prepare("
        SELECT
            mr.id,
            mr.requisition_code,
            mr.department,
            mr.priority,
            mr.needed_by_date,
            mr.total_items,
            mr.created_at,
            u.first_name,
            u.last_name
        FROM material_requisitions mr
        JOIN users u ON mr.requested_by = u.id
        WHERE mr.status = 'approved'
        ORDER BY
            CASE mr.priority
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'normal' THEN 3
                ELSE 4
            END,
            mr.needed_by_date ASC,
            mr.created_at ASC
        LIMIT 5
    ");
    $pendingReqsList->execute();
    $pendingReqListData = $pendingReqsList->fetchAll();

    // GM-approved POs awaiting warehouse receiving
    $pendingDeliveries = $db->prepare("
        SELECT
            COUNT(*) as count,
            SUM(CASE WHEN po.expected_delivery IS NOT NULL AND po.expected_delivery < CURDATE() THEN 1 ELSE 0 END) as overdue_count
        FROM purchase_orders po
        WHERE po.status IN ('approved', 'ordered', 'partial_received')
    ");
    $pendingDeliveries->execute();
    $pendingDeliveryData = $pendingDeliveries->fetch();

    $pendingDeliveryList = $db->prepare("
        SELECT
            po.id,
            po.po_number,
            po.order_date,
            po.expected_delivery,
            po.status,
            po.total_amount,
            s.supplier_name,
            s.supplier_code,
            (SELECT COUNT(*) FROM purchase_order_items poi WHERE poi.po_id = po.id) as item_count,
            (SELECT COALESCE(SUM(poi.quantity), 0) FROM purchase_order_items poi WHERE poi.po_id = po.id) as total_ordered,
            (SELECT COALESCE(SUM(poi.quantity_received), 0) FROM purchase_order_items poi WHERE poi.po_id = po.id) as total_accepted
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        WHERE po.status IN ('approved', 'ordered', 'partial_received')
        ORDER BY
            CASE WHEN po.expected_delivery IS NOT NULL AND po.expected_delivery < CURDATE() THEN 0 ELSE 1 END,
            po.expected_delivery ASC,
            po.order_date ASC
        LIMIT 5
    ");
    $pendingDeliveryList->execute();
    $pendingDeliveryListData = $pendingDeliveryList->fetchAll();

    // Low stock alerts (combined)
    $lowStockAlerts = $db->prepare("
        SELECT
            'ingredient' as type,
            ingredient_code as code,
            ingredient_name as name,
            current_stock,
            minimum_stock,
            unit_of_measure
        FROM ingredients
        WHERE is_active = 1 AND current_stock <= minimum_stock
        UNION ALL
        SELECT
            'mro' as type,
            item_code as code,
            item_name as name,
            current_stock,
            minimum_stock,
            unit_of_measure
        FROM mro_items
        WHERE is_active = 1 AND current_stock <= minimum_stock
        ORDER BY (current_stock / NULLIF(minimum_stock, 0)) ASC
        LIMIT 10
    ");
    $lowStockAlerts->execute();
    $lowStockAlertList = $lowStockAlerts->fetchAll();

    // Pending milk receiving (from milk_receiving - revised schema)
    // Milk that passed QC but not yet assigned to tank
    $pendingMilk = $db->prepare("
        SELECT
            rmi.id,
            rmi.batch_code,
            rmi.tank_id,
            rmi.volume_liters,
            rmi.remaining_liters,
            rmi.received_date,
            rmi.expiry_date,
            rmi.grade,
            mr.receiving_code,
            f.farmer_code,
            CONCAT(COALESCE(f.first_name, ''), ' ', COALESCE(f.last_name, '')) as farmer_name,
            mt.type_name as milk_type
        FROM raw_milk_inventory rmi
        JOIN milk_receiving mr ON rmi.receiving_id = mr.id
        JOIN farmers f ON mr.farmer_id = f.id
        LEFT JOIN milk_types mt ON rmi.milk_type_id = mt.id
        WHERE rmi.status = 'available'
        AND rmi.tank_id IS NULL
        ORDER BY rmi.received_date ASC
        LIMIT 10
    ");
    $pendingMilk->execute();
    $pendingMilkList = $pendingMilk->fetchAll();

    $notificationsStmt = $db->prepare("
        SELECT *
        FROM procurement_notifications
        WHERE target_role = 'warehouse_raw'
          AND is_read = 0
        ORDER BY created_at DESC
        LIMIT 8
    ");
    $notificationsStmt->execute();
    $notifications = $notificationsStmt->fetchAll();

    // Milk by type summary
    $milkByType = $db->prepare("
        SELECT
            mt.type_code,
            mt.type_name,
            COALESCE(SUM(rmi.remaining_liters), 0) as total_liters,
            COUNT(DISTINCT rmi.id) as batch_count
        FROM milk_types mt
        LEFT JOIN raw_milk_inventory rmi ON mt.id = rmi.milk_type_id
            AND rmi.status IN ('available', 'reserved')
        GROUP BY mt.id, mt.type_code, mt.type_name
    ");
    $milkByType->execute();
    $milkByTypeData = $milkByType->fetchAll();

    Response::success([
        'raw_milk' => [
            'total_liters' => (float) ($milkData['total_liters'] ?? 0),
            'tanks_with_milk' => (int) ($milkData['tanks_with_milk'] ?? 0),
            'batch_count' => (int) ($milkData['batch_count'] ?? 0),
            'expiring_soon_count' => (int) ($expiringMilkData['count'] ?? 0),
            'expiring_soon_liters' => (float) ($expiringMilkData['liters'] ?? 0),
            'by_type' => $milkByTypeData
        ],
        'storage_tanks' => [
            'total' => (int) ($tankData['total_tanks'] ?? 0),
            'available' => (int) ($tankData['available'] ?? 0),
            'in_use' => (int) ($tankData['in_use'] ?? 0),
            'offline' => (int) ($tankData['offline'] ?? 0),
            'total_capacity' => (float) ($tankData['total_capacity'] ?? 0),
            'current_volume' => (float) ($tankData['current_volume'] ?? 0),
            'utilization_percent' => $tankData['total_capacity'] > 0
                ? round(($tankData['current_volume'] / $tankData['total_capacity']) * 100, 1)
                : 0
        ],
        'ingredients' => [
            'total_items' => (int) ($totalIngData['count'] ?? 0),
            'low_stock_count' => (int) ($lowStockData['count'] ?? 0),
            'expiring_soon_count' => (int) ($expiringIngData['count'] ?? 0)
        ],
        'mro' => [
            'low_stock_count' => (int) ($lowStockMROData['count'] ?? 0),
            'critical_low_stock' => (int) ($criticalMROData['count'] ?? 0)
        ],
        'requisitions' => [
            'pending_count' => (int) ($pendingReqData['count'] ?? 0),
            'urgent_count' => (int) ($pendingReqData['urgent'] ?? 0),
            'high_priority_count' => (int) ($pendingReqData['high_priority'] ?? 0),
            'fulfilled_today' => (int) ($todayFulfilledData['count'] ?? 0)
        ],
        'pending_deliveries' => [
            'count' => (int) ($pendingDeliveryData['count'] ?? 0),
            'overdue_count' => (int) ($pendingDeliveryData['overdue_count'] ?? 0)
        ],
        'pending_deliveries_list' => $pendingDeliveryListData,
        'pending_requisitions_list' => $pendingReqListData,
        'low_stock_alerts' => $lowStockAlertList,
        'pending_milk_storage' => $pendingMilkList,
        'notifications' => $notifications,
        'recent_transactions' => $recentTxList
    ], 'Dashboard data retrieved successfully');

} catch (Exception $e) {
    error_log("Warehouse Raw Dashboard API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}

function ensureProcurementNotificationSupport($db) {
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
            KEY `idx_procurement_notifications_role` (`target_role`, `is_read`),
            KEY `idx_procurement_notifications_reference` (`reference_type`, `reference_id`),
            KEY `idx_procurement_notifications_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}
