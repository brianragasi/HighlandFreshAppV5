<?php
/**
 * Debug QC endpoints - runs ALL dashboard queries exactly as dashboard.php does
 * DELETE THIS FILE AFTER DEBUGGING
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$results = ['timestamp' => date('Y-m-d H:i:s')];

try {
    define('HIGHLAND_FRESH', true);
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    $db = Database::getInstance()->getConnection();
    $results['db'] = 'OK';
} catch (Exception $e) {
    $results['db'] = 'FAILED: ' . $e->getMessage();
    echo json_encode($results, JSON_PRETTY_PRINT);
    exit;
}

// ======= CHECK TABLE STRUCTURES =======
$tablesToCheck = [
    'finished_goods_inventory' => ['boxes_available', 'pieces_available', 'chiller_location', 'quantity_available', 'expiry_date', 'status', 'product_id', 'batch_id'],
    'products' => ['pieces_per_box', 'product_name', 'category', 'variant', 'unit_size', 'unit_measure', 'product_code'],
    'production_batches' => ['qc_status', 'batch_code'],
    'milk_receiving' => ['receiving_date', 'status', 'accepted_liters', 'volume_liters', 'farmer_id'],
    'qc_milk_tests' => ['test_code', 'grade', 'fat_percentage', 'titratable_acidity', 'total_amount', 'test_datetime', 'receiving_id', 'final_price_per_liter'],
    'farmers' => ['farmer_code', 'first_name'],
    'disposals' => ['id', 'initiated_by', 'approved_by', 'disposed_by', 'product_id', 'status'],
    'yogurt_transformations' => ['id']
];

$results['table_columns'] = [];
foreach ($tablesToCheck as $table => $expectedColumns) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `$table`");
        $actualColumns = array_column($stmt->fetchAll(), 'Field');
        $missing = array_diff($expectedColumns, $actualColumns);
        if (empty($missing)) {
            $results['table_columns'][$table] = 'OK - all columns present';
        } else {
            $results['table_columns'][$table] = 'MISSING COLUMNS: ' . implode(', ', $missing);
        }
    } catch (Exception $e) {
        $results['table_columns'][$table] = 'TABLE MISSING: ' . $e->getMessage();
    }
}

// ======= RUN EXACT DASHBOARD QUERIES =======
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));

// Query 1: Today's receiving stats
try {
    $stmt = $db->prepare("
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
    $stmt->execute([$today]);
    $results['query1_receiving'] = 'OK';
} catch (Exception $e) {
    $results['query1_receiving'] = 'FAILED: ' . $e->getMessage();
}

// Query 2: Week grading
try {
    $stmt = $db->prepare("
        SELECT grade, COUNT(*) as count, AVG(fat_percentage) as avg_fat,
               AVG(titratable_acidity) as avg_ta, SUM(total_amount) as total_value
        FROM qc_milk_tests WHERE DATE(test_datetime) >= ? GROUP BY grade
    ");
    $stmt->execute([$weekStart]);
    $results['query2_grading'] = 'OK';
} catch (Exception $e) {
    $results['query2_grading'] = 'FAILED: ' . $e->getMessage();
}

// Query 3: Pending batches
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM production_batches WHERE qc_status = 'pending'");
    $results['query3_batches'] = 'OK';
} catch (Exception $e) {
    $results['query3_batches'] = 'FAILED: ' . $e->getMessage();
}

// Query 4: Expiry alerts (THIS ONE uses boxes_available / pieces_available)
try {
    $stmt = $db->query("
        SELECT COUNT(*) as count,
               COALESCE(SUM((COALESCE(fgi.boxes_available, 0) * COALESCE(p.pieces_per_box, 1)) + COALESCE(fgi.pieces_available, 0)), 0) as total_quantity
        FROM finished_goods_inventory fgi
        LEFT JOIN products p ON fgi.product_id = p.id
        WHERE fgi.status = 'available'
          AND fgi.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ");
    $results['query4_expiry'] = 'OK';
} catch (Exception $e) {
    $results['query4_expiry'] = 'FAILED: ' . $e->getMessage();
}

// Query 5: Top farmers
try {
    $stmt = $db->prepare("
        SELECT f.farmer_code, COALESCE(f.first_name, '') as farmer_name,
               COUNT(mr.id) as deliveries,
               SUM(CASE WHEN mr.status = 'accepted' THEN mr.accepted_liters ELSE 0 END) as total_liters,
               AVG(qmt.fat_percentage) as avg_fat, AVG(qmt.final_price_per_liter) as avg_price
        FROM farmers f
        LEFT JOIN milk_receiving mr ON f.id = mr.farmer_id AND mr.receiving_date >= ?
        LEFT JOIN qc_milk_tests qmt ON mr.id = qmt.receiving_id
        GROUP BY f.id HAVING total_liters > 0 ORDER BY total_liters DESC LIMIT 5
    ");
    $stmt->execute([$weekStart]);
    $results['query5_farmers'] = 'OK';
} catch (Exception $e) {
    $results['query5_farmers'] = 'FAILED: ' . $e->getMessage();
}

// Query 6: Recent tests
try {
    $stmt = $db->query("
        SELECT qmt.test_code, qmt.grade, qmt.fat_percentage, qmt.total_amount, qmt.test_datetime,
               f.farmer_code, COALESCE(f.first_name, '') as farmer_name
        FROM qc_milk_tests qmt
        LEFT JOIN milk_receiving mr ON qmt.receiving_id = mr.id
        LEFT JOIN farmers f ON mr.farmer_id = f.id
        ORDER BY qmt.test_datetime DESC LIMIT 10
    ");
    $results['query6_recent'] = 'OK';
} catch (Exception $e) {
    $results['query6_recent'] = 'FAILED: ' . $e->getMessage();
}

// ======= CHECK DISPOSALS TABLE =======
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM disposals");
    $results['disposals_table'] = 'OK - ' . $stmt->fetch()['count'] . ' records';
} catch (Exception $e) {
    $results['disposals_table'] = 'FAILED: ' . $e->getMessage();
}

// ======= CHECK YOGURT TRANSFORMATIONS TABLE =======
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM yogurt_transformations");
    $results['yogurt_transformations_table'] = 'OK - ' . $stmt->fetch()['count'] . ' records';
} catch (Exception $e) {
    $results['yogurt_transformations_table'] = 'FAILED: ' . $e->getMessage();
}

// ======= PHP INFO =======
$results['php'] = [
    'version' => phpversion(),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'display_errors' => ini_get('display_errors'),
    'error_reporting' => error_reporting()
];

echo json_encode($results, JSON_PRETTY_PRINT);
