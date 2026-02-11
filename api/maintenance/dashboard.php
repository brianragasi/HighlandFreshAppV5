<?php
/**
 * Highland Fresh System - Maintenance Dashboard API
 * 
 * Provides dashboard statistics for Maintenance Head
 * - Machine health overview
 * - Pending repairs
 * - MRO requisition status
 * - Upcoming maintenance
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Maintenance Head or GM role
$currentUser = Auth::requireRole(['maintenance_head', 'general_manager']);

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            $action = getParam('action', 'stats');
            
            switch ($action) {
                case 'stats':
                    // Machine Status Summary
                    $machineStmt = $db->query("
                        SELECT 
                            status,
                            COUNT(*) as count
                        FROM machines
                        WHERE is_active = 1
                        GROUP BY status
                    ");
                    $machineStats = [];
                    while ($row = $machineStmt->fetch()) {
                        $machineStats[$row['status']] = (int) $row['count'];
                    }
                    
                    // Repair Summary
                    $repairStmt = $db->query("
                        SELECT 
                            status,
                            COUNT(*) as count
                        FROM machine_repairs
                        WHERE status NOT IN ('completed', 'cancelled')
                        GROUP BY status
                    ");
                    $repairStats = [];
                    while ($row = $repairStmt->fetch()) {
                        $repairStats[$row['status']] = (int) $row['count'];
                    }
                    
                    // Requisition Summary
                    $reqStmt = $db->query("
                        SELECT 
                            status,
                            COUNT(*) as count
                        FROM maintenance_requisitions
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        GROUP BY status
                    ");
                    $reqStats = [];
                    while ($row = $reqStmt->fetch()) {
                        $reqStats[$row['status']] = (int) $row['count'];
                    }
                    
                    // Upcoming Maintenance (next 7 days)
                    $upcomingStmt = $db->query("
                        SELECT COUNT(*) as count
                        FROM machines
                        WHERE is_active = 1 
                          AND next_maintenance_due <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                          AND next_maintenance_due >= CURDATE()
                    ");
                    $upcomingMaintenance = $upcomingStmt->fetch()['count'];
                    
                    // Overdue Maintenance
                    $overdueStmt = $db->query("
                        SELECT COUNT(*) as count
                        FROM machines
                        WHERE is_active = 1 
                          AND next_maintenance_due < CURDATE()
                    ");
                    $overdueMaintenance = $overdueStmt->fetch()['count'];
                    
                    // Critical MRO Items (below minimum stock)
                    $criticalStmt = $db->query("
                        SELECT COUNT(*) as count
                        FROM mro_items
                        WHERE is_active = 1 
                          AND current_stock <= minimum_stock
                    ");
                    $criticalItems = $criticalStmt->fetch()['count'];
                    
                    // Total repair cost this month
                    $costStmt = $db->query("
                        SELECT COALESCE(SUM(total_cost), 0) as total
                        FROM machine_repairs
                        WHERE MONTH(completed_at) = MONTH(CURDATE())
                          AND YEAR(completed_at) = YEAR(CURDATE())
                          AND status = 'completed'
                    ");
                    $monthlyRepairCost = $costStmt->fetch()['total'];
                    
                    Response::success([
                        'machines' => [
                            'total' => array_sum($machineStats),
                            'by_status' => $machineStats
                        ],
                        'repairs' => [
                            'active' => array_sum($repairStats),
                            'by_status' => $repairStats
                        ],
                        'requisitions' => [
                            'pending' => $reqStats['pending'] ?? 0,
                            'by_status' => $reqStats
                        ],
                        'maintenance' => [
                            'upcoming_7days' => (int) $upcomingMaintenance,
                            'overdue' => (int) $overdueMaintenance
                        ],
                        'alerts' => [
                            'critical_mro_items' => (int) $criticalItems,
                            'monthly_repair_cost' => (float) $monthlyRepairCost
                        ]
                    ], 'Dashboard stats retrieved successfully');
                    break;
                    
                case 'recent_repairs':
                    $limit = (int) getParam('limit', 5);
                    $stmt = $db->prepare("
                        SELECT mr.*, 
                               m.machine_name, m.machine_code,
                               u.first_name as reported_by_first, u.last_name as reported_by_last
                        FROM machine_repairs mr
                        JOIN machines m ON mr.machine_id = m.id
                        LEFT JOIN users u ON mr.reported_by = u.id
                        ORDER BY mr.created_at DESC
                        LIMIT ?
                    ");
                    $stmt->execute([$limit]);
                    Response::success($stmt->fetchAll(), 'Recent repairs retrieved');
                    break;
                    
                case 'upcoming_maintenance':
                    $days = (int) getParam('days', 7);
                    $stmt = $db->prepare("
                        SELECT m.*, 
                               DATEDIFF(m.next_maintenance_due, CURDATE()) as days_until_due
                        FROM machines m
                        WHERE m.is_active = 1 
                          AND m.next_maintenance_due <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                        ORDER BY m.next_maintenance_due ASC
                    ");
                    $stmt->execute([$days]);
                    Response::success($stmt->fetchAll(), 'Upcoming maintenance retrieved');
                    break;
                    
                case 'low_stock_mro':
                    $stmt = $db->query("
                        SELECT mi.*, mc.category_name,
                               (mi.minimum_stock - mi.current_stock) as deficit
                        FROM mro_items mi
                        LEFT JOIN mro_categories mc ON mi.category_id = mc.id
                        WHERE mi.is_active = 1 
                          AND mi.current_stock <= mi.minimum_stock
                        ORDER BY (mi.minimum_stock - mi.current_stock) DESC
                    ");
                    Response::success($stmt->fetchAll(), 'Low stock MRO items retrieved');
                    break;
                    
                default:
                    Response::error('Invalid action', 400);
            }
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Maintenance Dashboard API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
