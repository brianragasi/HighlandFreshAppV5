<?php
/**
 * Highland Fresh System - Warehouse Raw Dashboard API
 * 
 * GET - Get warehouse raw dashboard statistics
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

// Require Warehouse Raw role
$currentUser = Auth::requireRole(['warehouse_raw', 'general_manager']);

if ($requestMethod !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    $db = Database::getInstance()->getConnection();
    
    $today = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    
    // === RAW MILK STATISTICS ===
    
    // Total milk in storage (from tank_milk_batches)
    $milkStats = $db->prepare("
        SELECT 
            COALESCE(SUM(remaining_liters), 0) as total_liters,
            COUNT(DISTINCT tank_id) as tanks_with_milk
        FROM tank_milk_batches
        WHERE status IN ('available', 'partially_used')
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
    
    // Milk expiring soon (within 2 days)
    $expiringMilk = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(remaining_liters), 0) as liters
        FROM tank_milk_batches
        WHERE status IN ('available', 'partially_used')
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
    
    // === REQUISITIONS STATISTICS ===
    
    // Pending requisitions to fulfill
    $pendingRequisitions = $db->prepare("
        SELECT 
            COUNT(*) as count,
            SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
            SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority
        FROM ingredient_requisitions
        WHERE status = 'approved'
    ");
    $pendingRequisitions->execute();
    $pendingReqData = $pendingRequisitions->fetch();
    
    // Today's fulfilled requisitions
    $todayFulfilled = $db->prepare("
        SELECT COUNT(*) as count
        FROM ingredient_requisitions
        WHERE status = 'fulfilled' AND DATE(fulfilled_at) = ?
    ");
    $todayFulfilled->execute([$today]);
    $todayFulfilledData = $todayFulfilled->fetch();
    
    // === RECENT ACTIVITY ===
    
    // Recent transactions
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
                ELSE 'Raw Milk'
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
    
    // Pending requisitions list
    $pendingReqsList = $db->prepare("
        SELECT 
            ir.id,
            ir.requisition_code,
            ir.department,
            ir.priority,
            ir.needed_by_date,
            ir.total_items,
            ir.created_at,
            u.first_name,
            u.last_name
        FROM ingredient_requisitions ir
        JOIN users u ON ir.requested_by = u.id
        WHERE ir.status = 'approved'
        ORDER BY 
            CASE ir.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'normal' THEN 3 
                ELSE 4 
            END,
            ir.needed_by_date ASC,
            ir.created_at ASC
        LIMIT 5
    ");
    $pendingReqsList->execute();
    $pendingReqListData = $pendingReqsList->fetchAll();
    
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
    
    // QC-Approved milk waiting for storage (from raw_milk_inventory not yet in tanks)
    $pendingMilk = $db->prepare("
        SELECT 
            rmi.id,
            rmi.tank_id as qc_tank_id,
            rmi.volume_liters,
            rmi.received_date,
            rmi.expiry_date,
            f.farmer_code,
            CONCAT(f.first_name, ' ', f.last_name) as farmer_name
        FROM raw_milk_inventory rmi
        JOIN qc_milk_tests qmt ON rmi.qc_test_id = qmt.id
        JOIN milk_deliveries md ON qmt.delivery_id = md.id
        JOIN farmers f ON md.farmer_id = f.id
        WHERE rmi.status = 'available'
        AND NOT EXISTS (
            SELECT 1 FROM tank_milk_batches tmb 
            WHERE tmb.raw_milk_inventory_id = rmi.id
        )
        ORDER BY rmi.received_date ASC
        LIMIT 10
    ");
    $pendingMilk->execute();
    $pendingMilkList = $pendingMilk->fetchAll();
    
    Response::success([
        'raw_milk' => [
            'total_liters' => (float) ($milkData['total_liters'] ?? 0),
            'tanks_with_milk' => (int) ($milkData['tanks_with_milk'] ?? 0),
            'expiring_soon_count' => (int) ($expiringMilkData['count'] ?? 0),
            'expiring_soon_liters' => (float) ($expiringMilkData['liters'] ?? 0)
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
        'pending_requisitions_list' => $pendingReqListData,
        'low_stock_alerts' => $lowStockAlertList,
        'pending_milk_storage' => $pendingMilkList,
        'recent_transactions' => $recentTxList
    ], 'Dashboard data retrieved successfully');
    
} catch (Exception $e) {
    error_log("Warehouse Raw Dashboard API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
