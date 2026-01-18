<?php
/**
 * Highland Fresh System - Production Dashboard API
 * 
 * GET - Get production dashboard statistics
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Production role
$currentUser = Auth::requireRole(['production_staff', 'general_manager', 'qc_officer']);

if ($requestMethod !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    $db = Database::getInstance()->getConnection();
    
    $today = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    
    // Today's production runs
    $todayRuns = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'planned' THEN 1 ELSE 0 END) as planned,
            SUM(CASE WHEN status IN ('in_progress', 'pasteurization', 'processing', 'cooling', 'packaging') THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'completed' THEN actual_quantity ELSE 0 END) as total_yield
        FROM production_runs
        WHERE DATE(created_at) = ?
    ");
    $todayRuns->execute([$today]);
    $runStats = $todayRuns->fetch();
    
    // Week's production by product type
    $weekProduction = $db->prepare("
        SELECT 
            mr.product_type,
            COUNT(pr.id) as batches,
            SUM(pr.actual_quantity) as total_produced,
            AVG(pr.yield_variance) as avg_variance
        FROM production_runs pr
        JOIN master_recipes mr ON pr.recipe_id = mr.id
        WHERE pr.status = 'completed' AND DATE(pr.end_datetime) >= ?
        GROUP BY mr.product_type
    ");
    $weekProduction->execute([$weekStart]);
    $productionByType = $weekProduction->fetchAll();
    
    // Pending requisitions
    $pendingReqs = $db->prepare("
        SELECT COUNT(*) as count
        FROM ingredient_requisitions
        WHERE status IN ('draft', 'pending')
    ");
    $pendingReqs->execute();
    $reqStats = $pendingReqs->fetch();
    
    // Recent CCP logs needing attention (failures)
    $ccpAlerts = $db->prepare("
        SELECT COUNT(*) as count
        FROM production_ccp_logs
        WHERE status = 'fail'
    ");
    $ccpAlerts->execute();
    $ccpStats = $ccpAlerts->fetch();
    
    // Active recipes
    $activeRecipes = $db->prepare("
        SELECT COUNT(*) as count FROM master_recipes WHERE is_active = 1
    ");
    $activeRecipes->execute();
    $recipeStats = $activeRecipes->fetch();
    
    // Recent production runs
    $recentRuns = $db->prepare("
        SELECT 
            pr.run_code,
            pr.status,
            pr.planned_quantity,
            pr.actual_quantity,
            pr.start_datetime,
            pr.end_datetime,
            mr.product_name,
            mr.product_type,
            mr.variant
        FROM production_runs pr
        JOIN master_recipes mr ON pr.recipe_id = mr.id
        ORDER BY pr.created_at DESC
        LIMIT 10
    ");
    $recentRuns->execute();
    $recentRunsList = $recentRuns->fetchAll();
    
    // Ensure milk usage tracking table exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS production_run_milk_usage (
            id INT(11) NOT NULL AUTO_INCREMENT,
            run_id INT(11) NOT NULL,
            delivery_id INT(11) NOT NULL,
            milk_liters_allocated DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_run (run_id),
            INDEX idx_delivery (delivery_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Available milk for production (ACTUAL QC-approved milk from recent deliveries minus allocated)
    // This matches what production/runs.php uses for validation!
    $availableMilk = $db->prepare("
        SELECT 
            COALESCE(SUM(remaining_liters), 0) as total_liters,
            COUNT(*) as source_count
        FROM (
            SELECT 
                md.id,
                COALESCE(
                    CASE 
                        WHEN md.accepted_liters > 0 THEN md.accepted_liters 
                        ELSE md.volume_liters 
                    END - (
                        SELECT COALESCE(SUM(pru.milk_liters_allocated), 0)
                        FROM production_run_milk_usage pru
                        WHERE pru.delivery_id = md.id
                    ), 
                    CASE 
                        WHEN md.accepted_liters > 0 THEN md.accepted_liters 
                        ELSE md.volume_liters 
                    END
                ) as remaining_liters
            FROM milk_deliveries md
            JOIN qc_milk_tests qmt ON qmt.delivery_id = md.id
            WHERE md.status = 'accepted'
              AND DATE(md.delivery_date) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
            HAVING remaining_liters > 0
        ) as available
    ");
    $availableMilk->execute();
    $milkStats = $availableMilk->fetch();
    
    // Also get raw_milk_inventory for total stored milk (for informational purposes)
    $storedMilk = $db->prepare("
        SELECT COALESCE(SUM(volume_liters), 0) as total_liters
        FROM raw_milk_inventory
        WHERE status = 'available'
    ");
    $storedMilk->execute();
    $storedStats = $storedMilk->fetch();
    
    // Get tank-based milk inventory (what warehouse actually has - for reference only)
    $tankMilk = $db->prepare("
        SELECT COALESCE(SUM(current_volume), 0) as total_liters, COUNT(*) as tank_count
        FROM storage_tanks
        WHERE current_volume > 0
    ");
    $tankMilk->execute();
    $tankStats = $tankMilk->fetch();
    
    // Get milk ISSUED TO PRODUCTION through fulfilled requisitions
    // This is what production actually has available to use
    // Milk issued = requisition items fulfilled for raw_milk type
    // Minus milk already used in production runs
    $productionMilk = $db->prepare("
        SELECT 
            COALESCE(SUM(ri.issued_quantity), 0) as total_issued,
            COUNT(DISTINCT ri.id) as issued_batches
        FROM requisition_items ri
        JOIN ingredient_requisitions ir ON ri.requisition_id = ir.id
        WHERE ri.item_type = 'raw_milk'
          AND ri.issued_quantity > 0
          AND ir.department = 'production'
    ");
    $productionMilk->execute();
    $prodMilkStats = $productionMilk->fetch();
    
    // Get milk already used in production runs
    $usedMilk = $db->prepare("
        SELECT COALESCE(SUM(milk_liters_allocated), 0) as total_used
        FROM production_run_milk_usage
    ");
    $usedMilk->execute();
    $usedStats = $usedMilk->fetch();
    
    // Production's available milk = issued - used
    $productionAvailableMilk = max(0, ($prodMilkStats['total_issued'] ?? 0) - ($usedStats['total_used'] ?? 0));
    
    Response::success([
        'today' => [
            'date' => $today,
            'total_runs' => (int) $runStats['total'],
            'planned' => (int) $runStats['planned'],
            'in_progress' => (int) $runStats['in_progress'],
            'completed' => (int) $runStats['completed'],
            'total_yield' => (int) ($runStats['total_yield'] ?? 0)
        ],
        'week_production' => $productionByType,
        'pending_requisitions' => (int) $reqStats['count'],
        'ccp_alerts' => (int) $ccpStats['count'],
        'active_recipes' => (int) $recipeStats['count'],
        // Production's available milk (issued via requisitions minus used in runs)
        'available_milk' => (float) $productionAvailableMilk,
        'available_milk_sources' => (int) ($prodMilkStats['issued_batches'] ?? 0),
        // Warehouse tank inventory (for reference - production must requisition to get this)
        'warehouse_tank_milk' => (float) ($tankStats['total_liters'] ?? 0),
        // Legacy: delivery-based allocation tracking
        'delivery_based_milk' => (float) ($milkStats['total_liters'] ?? 0),
        // Total stored raw milk (for informational purposes - may be older than 2 days)
        'stored_milk' => (float) ($storedStats['total_liters'] ?? 0),
        'recent_runs' => $recentRunsList
    ], 'Dashboard data retrieved successfully');
    
} catch (Exception $e) {
    error_log("Production Dashboard API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
