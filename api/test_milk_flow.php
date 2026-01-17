<?php
/**
 * Test script to verify milk QC flow
 */

define('HIGHLAND_FRESH', true);

// Database config
define('DB_HOST', 'localhost');
define('DB_NAME', 'highland_fresh');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

require_once __DIR__ . '/config/database.php';

echo "<h2>🥛 Highland Fresh - Milk QC Flow Test</h2>";

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Check milk_deliveries table
    echo "<h3>1. Milk Deliveries (Last 7 Days)</h3>";
    $stmt = $db->query("
        SELECT md.*, f.first_name, f.last_name 
        FROM milk_deliveries md 
        LEFT JOIN farmers f ON md.farmer_id = f.id 
        WHERE md.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY md.delivery_date DESC
        LIMIT 10
    ");
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($deliveries)) {
        echo "<p style='color: orange;'>⚠️ No deliveries in the last 7 days</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Code</th><th>Farmer</th><th>Liters</th><th>Status</th><th>Date</th></tr>";
        foreach ($deliveries as $d) {
            $color = $d['status'] === 'accepted' ? 'green' : ($d['status'] === 'rejected' ? 'red' : 'gray');
            echo "<tr style='color: {$color}'>";
            echo "<td>{$d['delivery_code']}</td>";
            echo "<td>{$d['first_name']} {$d['last_name']}</td>";
            echo "<td>{$d['volume_liters']}L</td>";
            echo "<td>{$d['status']}</td>";
            echo "<td>{$d['delivery_date']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 2. Check QC tests
    echo "<h3>2. QC Milk Tests (Last 7 Days)</h3>";
    $stmt = $db->query("
        SELECT qmt.*, md.delivery_code 
        FROM qc_milk_tests qmt
        LEFT JOIN milk_deliveries md ON qmt.delivery_id = md.id
        WHERE qmt.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY qmt.created_at DESC
        LIMIT 10
    ");
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tests)) {
        echo "<p style='color: orange;'>⚠️ No QC tests in the last 7 days</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Test Code</th><th>Delivery</th><th>Fat%</th><th>Acidity%</th><th>Accepted</th><th>Price/L</th></tr>";
        foreach ($tests as $t) {
            $color = $t['is_accepted'] ? 'green' : 'red';
            echo "<tr style='color: {$color}'>";
            echo "<td>{$t['test_code']}</td>";
            echo "<td>{$t['delivery_code']}</td>";
            echo "<td>{$t['fat_percentage']}%</td>";
            echo "<td>{$t['titratable_acidity']}%</td>";
            echo "<td>" . ($t['is_accepted'] ? '✅ Yes' : '❌ No') . "</td>";
            echo "<td>₱{$t['final_price_per_liter']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 3. Check available milk for production
    echo "<h3>3. Available QC-Approved Milk for Production</h3>";
    
    // Check if production_run_milk_usage table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'production_run_milk_usage'");
    if ($tableCheck->rowCount() === 0) {
        echo "<p style='color: gray;'>📝 Creating production_run_milk_usage table...</p>";
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
        echo "<p style='color: green;'>✅ Table created!</p>";
    }
    
    $stmt = $db->query("
        SELECT 
            md.id,
            md.delivery_code,
            md.accepted_liters,
            md.delivery_date,
            qmt.test_code,
            qmt.fat_percentage,
            COALESCE(
                md.accepted_liters - (
                    SELECT COALESCE(SUM(pru.milk_liters_allocated), 0)
                    FROM production_run_milk_usage pru
                    WHERE pru.delivery_id = md.id
                ), md.accepted_liters
            ) as remaining_liters
        FROM milk_deliveries md
        JOIN qc_milk_tests qmt ON qmt.delivery_id = md.id
        WHERE md.status = 'accepted'
          AND DATE(md.delivery_date) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
        HAVING remaining_liters > 0
        ORDER BY md.delivery_date ASC
    ");
    $availableMilk = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalAvailable = array_sum(array_column($availableMilk, 'remaining_liters'));
    
    if ($totalAvailable > 0) {
        echo "<p style='color: green; font-size: 1.2em;'><strong>✅ Total Available: {$totalAvailable}L</strong></p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Delivery Code</th><th>QC Test</th><th>Fat%</th><th>Available</th><th>Date</th></tr>";
        foreach ($availableMilk as $m) {
            echo "<tr>";
            echo "<td>{$m['delivery_code']}</td>";
            echo "<td>{$m['test_code']}</td>";
            echo "<td>{$m['fat_percentage']}%</td>";
            echo "<td><strong>{$m['remaining_liters']}L</strong></td>";
            echo "<td>{$m['delivery_date']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red; font-size: 1.2em;'><strong>❌ No QC-approved milk available!</strong></p>";
        echo "<p>Production cannot proceed until milk deliveries are tested and approved by QC.</p>";
    }
    
    // 4. Production runs summary
    echo "<h3>4. Recent Production Runs</h3>";
    $stmt = $db->query("
        SELECT pr.*, mr.product_name 
        FROM production_runs pr
        JOIN master_recipes mr ON pr.recipe_id = mr.id
        ORDER BY pr.created_at DESC
        LIMIT 5
    ");
    $runs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($runs)) {
        echo "<p style='color: gray;'>No production runs yet.</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Run Code</th><th>Product</th><th>Milk Used</th><th>Status</th><th>Created</th></tr>";
        foreach ($runs as $r) {
            echo "<tr>";
            echo "<td>{$r['run_code']}</td>";
            echo "<td>{$r['product_name']}</td>";
            echo "<td>{$r['milk_liters_used']}L</td>";
            echo "<td>{$r['status']}</td>";
            echo "<td>{$r['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Summary
    echo "<hr><h3>📊 Summary</h3>";
    echo "<ul>";
    echo "<li><strong>Correct Flow:</strong> Farmer Delivery → QC Testing → If Accepted → Available for Production</li>";
    echo "<li><strong>Current Status:</strong> " . ($totalAvailable > 0 ? "✅ Ready for production ({$totalAvailable}L available)" : "❌ No milk available - need QC approval") . "</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
