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
    
    // Milk available for production (from raw_milk_inventory with status='available')
    $availableMilk = $db->prepare("
        SELECT 
            COALESCE(SUM(volume_liters), 0) as total_liters
        FROM raw_milk_inventory
        WHERE status = 'available'
    ");
    $availableMilk->execute();
    $milkStats = $availableMilk->fetch();
    
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
        'available_milk' => (float) ($milkStats['total_liters'] ?? 0),
        'recent_runs' => $recentRunsList
    ], 'Dashboard data retrieved successfully');
    
} catch (Exception $e) {
    error_log("Production Dashboard API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
