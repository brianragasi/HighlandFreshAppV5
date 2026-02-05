<?php
/**
 * Highland Fresh System - QC Dashboard API
 * 
 * GET - Get QC dashboard statistics
 * 
 * UPDATED: Uses milk_receiving table (revised schema)
 * 
 * @package HighlandFresh
 * @version 4.0
 * @deployed 2026-02-06
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require QC role
$currentUser = Auth::requireRole(['qc_officer', 'general_manager']);

if ($requestMethod !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Today's date
    $today = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $monthStart = date('Y-m-01');
    
    // Today's receiving stats (using milk_receiving - revised schema)
    $todayReceiving = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending_qc' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'accepted' THEN accepted_liters ELSE 0 END) as accepted_liters,
            SUM(CASE WHEN status = 'rejected' THEN volume_liters ELSE 0 END) as rejected_liters
        FROM milk_receiving
        WHERE receiving_date = ?
    ");
    $todayReceiving->execute([$today]);
    $receivingStats = $todayReceiving->fetch();
    
    // Week's grading summary (using titratable_acidity - revised schema)
    $weekGrading = $db->prepare("
        SELECT 
            grade,
            COUNT(*) as count,
            AVG(fat_percentage) as avg_fat,
            AVG(titratable_acidity) as avg_ta,
            SUM(total_amount) as total_value
        FROM qc_milk_tests
        WHERE DATE(test_datetime) >= ?
        GROUP BY grade
    ");
    $weekGrading->execute([$weekStart]);
    $gradeStats = $weekGrading->fetchAll();
    
    // Pending batch releases (use qc_status column - actual column in DB)
    $pendingBatches = $db->prepare("
        SELECT COUNT(*) as count
        FROM production_batches
        WHERE qc_status = 'pending'
    ");
    $pendingBatches->execute();
    $batchStats = $pendingBatches->fetch();
    
    // Expiry alerts (products expiring in next 3 days)
    $expiryAlerts = $db->prepare("
        SELECT COUNT(*) as count,
               COALESCE(SUM(quantity_available), SUM(remaining_quantity), 0) as total_quantity
        FROM finished_goods_inventory
        WHERE status = 'available'
          AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ");
    $expiryAlerts->execute();
    $expiryStats = $expiryAlerts->fetch();
    
    // Top farmers this week (using milk_receiving - revised schema)
    $topFarmers = $db->prepare("
        SELECT 
            f.farmer_code,
            COALESCE(f.first_name, '') as farmer_name,
            COUNT(mr.id) as deliveries,
            SUM(CASE WHEN mr.status = 'accepted' THEN mr.accepted_liters ELSE 0 END) as total_liters,
            AVG(qmt.fat_percentage) as avg_fat,
            AVG(qmt.final_price_per_liter) as avg_price
        FROM farmers f
        LEFT JOIN milk_receiving mr ON f.id = mr.farmer_id AND mr.receiving_date >= ?
        LEFT JOIN qc_milk_tests qmt ON mr.id = qmt.receiving_id
        GROUP BY f.id
        HAVING total_liters > 0
        ORDER BY total_liters DESC
        LIMIT 5
    ");
    $topFarmers->execute([$weekStart]);
    $farmerRankings = $topFarmers->fetchAll();
    
    // Recent tests (using milk_receiving and receiving_id - revised schema)
    $recentTests = $db->prepare("
        SELECT 
            qmt.test_code,
            qmt.grade,
            qmt.fat_percentage,
            qmt.total_amount,
            qmt.test_datetime,
            f.farmer_code,
            COALESCE(f.first_name, '') as farmer_name
        FROM qc_milk_tests qmt
        LEFT JOIN milk_receiving mr ON qmt.receiving_id = mr.id
        LEFT JOIN farmers f ON mr.farmer_id = f.id
        ORDER BY qmt.test_datetime DESC
        LIMIT 10
    ");
    $recentTests->execute();
    $recentTestsList = $recentTests->fetchAll();
    
    Response::success([
        'today' => [
            'date' => $today,
            'total_deliveries' => (int) ($receivingStats['total'] ?? 0),
            'pending_tests' => (int) ($receivingStats['pending'] ?? 0),
            'accepted' => (int) ($receivingStats['accepted'] ?? 0),
            'rejected' => (int) ($receivingStats['rejected'] ?? 0),
            'accepted_liters' => (float) ($receivingStats['accepted_liters'] ?? 0),
            'rejected_liters' => (float) ($receivingStats['rejected_liters'] ?? 0)
        ],
        'week_grades' => $gradeStats,
        'pending_batch_releases' => (int) ($batchStats['count'] ?? 0),
        'expiry_alerts' => [
            'count' => (int) ($expiryStats['count'] ?? 0),
            'quantity' => (int) ($expiryStats['total_quantity'] ?? 0)
        ],
        'top_farmers' => $farmerRankings,
        'recent_tests' => $recentTestsList
    ], 'Dashboard data retrieved successfully');
    
} catch (Exception $e) {
    error_log("Dashboard API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
