<?php
/**
 * Test Dashboard Queries
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'highland_fresh';

$results = [];

try {
    $db = new PDO("mysql:host=$host;dbname=$dbName", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $today = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    
    // Test 1: Today's deliveries
    try {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending_test' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'accepted' THEN volume_liters ELSE 0 END) as accepted_liters,
                SUM(CASE WHEN status = 'rejected' THEN volume_liters ELSE 0 END) as rejected_liters
            FROM milk_deliveries
            WHERE delivery_date = ?
        ");
        $stmt->execute([$today]);
        $results['deliveries'] = ['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)];
    } catch (Exception $e) {
        $results['deliveries'] = ['success' => false, 'error' => $e->getMessage()];
    }
    
    // Test 2: Week grading
    try {
        $stmt = $db->prepare("
            SELECT 
                grade,
                COUNT(*) as count,
                AVG(fat_percentage) as avg_fat,
                AVG(acidity_ph) as avg_ph,
                SUM(total_amount) as total_value
            FROM qc_milk_tests
            WHERE DATE(test_datetime) >= ?
            GROUP BY grade
        ");
        $stmt->execute([$weekStart]);
        $results['grading'] = ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (Exception $e) {
        $results['grading'] = ['success' => false, 'error' => $e->getMessage()];
    }
    
    // Test 3: Pending batches
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM production_batches
            WHERE status = 'pending_qc'
        ");
        $stmt->execute();
        $results['batches'] = ['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)];
    } catch (Exception $e) {
        $results['batches'] = ['success' => false, 'error' => $e->getMessage()];
    }
    
    // Test 4: Expiry alerts
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count,
                   SUM(quantity_available) as total_quantity
            FROM finished_goods_inventory
            WHERE status = 'available'
              AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
        ");
        $stmt->execute();
        $results['expiry'] = ['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)];
    } catch (Exception $e) {
        $results['expiry'] = ['success' => false, 'error' => $e->getMessage()];
    }
    
    // Test 5: Top farmers
    try {
        $stmt = $db->prepare("
            SELECT 
                f.farmer_code,
                CONCAT(f.first_name, ' ', f.last_name) as farmer_name,
                COUNT(md.id) as deliveries,
                SUM(CASE WHEN md.status = 'accepted' THEN md.volume_liters ELSE 0 END) as total_liters,
                AVG(qmt.fat_percentage) as avg_fat,
                AVG(qmt.final_price_per_liter) as avg_price
            FROM farmers f
            LEFT JOIN milk_deliveries md ON f.id = md.farmer_id AND md.delivery_date >= ?
            LEFT JOIN qc_milk_tests qmt ON md.id = qmt.delivery_id
            GROUP BY f.id
            HAVING total_liters > 0
            ORDER BY total_liters DESC
            LIMIT 5
        ");
        $stmt->execute([$weekStart]);
        $results['top_farmers'] = ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (Exception $e) {
        $results['top_farmers'] = ['success' => false, 'error' => $e->getMessage()];
    }
    
    // Test 6: Recent tests - check users table structure
    try {
        $stmt = $db->query("DESCRIBE users");
        $results['users_structure'] = ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (Exception $e) {
        $results['users_structure'] = ['success' => false, 'error' => $e->getMessage()];
    }
    
    // Test 7: Recent tests query
    try {
        $stmt = $db->prepare("
            SELECT 
                qmt.test_code,
                qmt.grade,
                qmt.fat_percentage,
                qmt.total_amount,
                qmt.test_datetime,
                f.farmer_code,
                CONCAT(f.first_name, ' ', f.last_name) as farmer_name
            FROM qc_milk_tests qmt
            LEFT JOIN milk_deliveries md ON qmt.delivery_id = md.id
            LEFT JOIN farmers f ON md.farmer_id = f.id
            ORDER BY qmt.test_datetime DESC
            LIMIT 10
        ");
        $stmt->execute();
        $results['recent_tests'] = ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (Exception $e) {
        $results['recent_tests'] = ['success' => false, 'error' => $e->getMessage()];
    }
    
    echo json_encode(['success' => true, 'results' => $results], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
