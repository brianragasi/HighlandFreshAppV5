<?php
/**
 * Fix Farmers Table Structure
 * Recreates the farmers table with the correct structure and imports data
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=UTF-8');

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'highland_fresh';

echo "<h1>Highland Fresh - Fix Farmers Table</h1>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "<p style='color:green'>✓ Disabled foreign key checks</p>";
    
    // First, check current table structure
    echo "<h3>Current farmers table structure:</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE farmers");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Type</th></tr>";
        foreach ($columns as $col) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td></tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "<p>No farmers table exists yet</p>";
    }
    
    // Drop milk_deliveries first (has foreign key to farmers)
    echo "<p>Dropping milk_deliveries table...</p>";
    $pdo->exec("DROP TABLE IF EXISTS milk_deliveries");
    echo "<p style='color:green'>✓ Dropped milk_deliveries</p>";
    
    // Drop farmers table
    echo "<p>Dropping farmers table...</p>";
    $pdo->exec("DROP TABLE IF EXISTS farmers");
    echo "<p style='color:green'>✓ Dropped farmers</p>";
    
    // Recreate farmers table with correct structure
    $pdo->exec("
        CREATE TABLE `farmers` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `farmer_code` VARCHAR(20) NOT NULL,
            `first_name` VARCHAR(100) NOT NULL,
            `last_name` VARCHAR(100) NULL,
            `contact_number` VARCHAR(20) NULL,
            `address` TEXT NULL,
            `membership_type` ENUM('member', 'non_member') NOT NULL DEFAULT 'member',
            `base_price_per_liter` DECIMAL(10,2) NOT NULL DEFAULT 40.00,
            `bank_name` VARCHAR(100) NULL,
            `bank_account_number` VARCHAR(50) NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `farmer_code` (`farmer_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created farmers table with first_name, last_name columns</p>";
    
    // Create milk_deliveries table
    $pdo->exec("
        CREATE TABLE `milk_deliveries` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `delivery_code` VARCHAR(30) NOT NULL,
            `rmr_number` VARCHAR(20) NULL COMMENT 'Raw Milk Receipt Number',
            `farmer_id` INT(11) NOT NULL,
            `delivery_date` DATE NOT NULL,
            `delivery_time` TIME NULL,
            `volume_liters` DECIMAL(10,2) NOT NULL,
            `rejected_liters` DECIMAL(10,2) DEFAULT 0,
            `accepted_liters` DECIMAL(10,2) NOT NULL,
            `sediment_grade` VARCHAR(10) DEFAULT 'G-1',
            `acidity_ta` DECIMAL(5,2) NULL COMMENT 'Titratable Acidity %',
            `fat_percentage` DECIMAL(5,2) NULL,
            `temperature_celsius` DECIMAL(4,1) NULL,
            `transport_cost` DECIMAL(10,2) DEFAULT 0,
            `price_per_liter` DECIMAL(10,2) NOT NULL,
            `total_amount` DECIMAL(12,2) NOT NULL,
            `received_by` INT(11) NULL,
            `status` ENUM('pending', 'accepted', 'rejected', 'partial') NOT NULL DEFAULT 'accepted',
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `delivery_code` (`delivery_code`),
            KEY `idx_farmer` (`farmer_id`),
            KEY `idx_date` (`delivery_date`),
            KEY `idx_rmr` (`rmr_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created milk_deliveries table</p>";
    
    // Now import the supplier data
    $suppliers = [
        ['code' => 'FRM-001', 'name' => 'Lacandula', 'price' => 41.25, 'membership' => 'member'],
        ['code' => 'FRM-002', 'name' => 'Galla', 'price' => 42.25, 'membership' => 'member'],
        ['code' => 'FRM-003', 'name' => 'DMDC', 'price' => 40.00, 'membership' => 'member'],
        ['code' => 'FRM-004', 'name' => 'Dumindin', 'price' => 41.50, 'membership' => 'member'],
        ['code' => 'FRM-005', 'name' => 'Paraguya', 'price' => 42.50, 'membership' => 'member'],
        ['code' => 'FRM-006', 'name' => 'MMDC', 'price' => 40.00, 'membership' => 'member'],
        ['code' => 'FRM-007', 'name' => 'Bernales', 'price' => 40.00, 'membership' => 'member'],
        ['code' => 'FRM-008', 'name' => 'Tagadan', 'price' => 39.25, 'membership' => 'member'],
        ['code' => 'FRM-009', 'name' => 'Abonitalla', 'price' => 41.25, 'membership' => 'member'],
        ['code' => 'FRM-010', 'name' => 'C1/Dumaluan', 'price' => 40.00, 'membership' => 'member'],
        ['code' => 'FRM-011', 'name' => 'C1/Dumaluan Goat', 'price' => 70.00, 'membership' => 'member'],
        ['code' => 'FRM-012', 'name' => 'C3/Valledor', 'price' => 41.25, 'membership' => 'member'],
        ['code' => 'FRM-013', 'name' => 'Jardin', 'price' => 40.75, 'membership' => 'member'],
        ['code' => 'FRM-014', 'name' => 'Malig', 'price' => 40.50, 'membership' => 'member'],
        ['code' => 'FRM-015', 'name' => 'Abriol', 'price' => 40.25, 'membership' => 'member'],
        ['code' => 'FRM-016', 'name' => 'Gargar', 'price' => 69.25, 'membership' => 'member'],
        ['code' => 'FRM-017', 'name' => 'Navarro', 'price' => 40.75, 'membership' => 'member'],
    ];
    
    echo "<h3>Importing Suppliers:</h3>";
    
    $stmt = $pdo->prepare("
        INSERT INTO farmers (farmer_code, first_name, last_name, membership_type, base_price_per_liter, is_active)
        VALUES (?, ?, '', ?, ?, 1)
    ");
    
    foreach ($suppliers as $supplier) {
        $stmt->execute([
            $supplier['code'],
            $supplier['name'],
            $supplier['membership'],
            $supplier['price']
        ]);
        echo "<p style='color:green'>✓ Added {$supplier['code']} - {$supplier['name']} (₱{$supplier['price']}/L)</p>";
    }
    
    // Now import the delivery data for October 21, 2025
    $deliveries = [
        ['rmr' => '66173', 'farmer_code' => 'FRM-001', 'liters' => 104, 'rejected' => 0, 'acidity' => 0.17, 'fat' => 3.5, 'transport' => 0, 'price' => 41.25],
        ['rmr' => '66174', 'farmer_code' => 'FRM-002', 'liters' => 52, 'rejected' => 0, 'acidity' => 0.16, 'fat' => 3.5, 'transport' => 0, 'price' => 42.25],
        ['rmr' => '66175', 'farmer_code' => 'FRM-003', 'liters' => 115, 'rejected' => 0, 'acidity' => 0.16, 'fat' => 2.5, 'transport' => 0, 'price' => 40.00],
        ['rmr' => '66176', 'farmer_code' => 'FRM-004', 'liters' => 105, 'rejected' => 0, 'acidity' => 0.17, 'fat' => 3.0, 'transport' => 0, 'price' => 41.50],
        ['rmr' => '66177', 'farmer_code' => 'FRM-005', 'liters' => 50, 'rejected' => 0, 'acidity' => 0.16, 'fat' => 3.5, 'transport' => 500, 'price' => 42.50],
        ['rmr' => '66178', 'farmer_code' => 'FRM-006', 'liters' => 118, 'rejected' => 0, 'acidity' => 0.17, 'fat' => 3.0, 'transport' => 0, 'price' => 40.00],
        ['rmr' => '66179', 'farmer_code' => 'FRM-007', 'liters' => 598, 'rejected' => 0, 'acidity' => 0.17, 'fat' => 2.5, 'transport' => 0, 'price' => 40.00],
        ['rmr' => '66180', 'farmer_code' => 'FRM-008', 'liters' => 181, 'rejected' => 171, 'acidity' => 0.17, 'fat' => 3.0, 'transport' => 0, 'price' => 39.25],
        ['rmr' => '66181', 'farmer_code' => 'FRM-009', 'liters' => 171, 'rejected' => 0, 'acidity' => 0.17, 'fat' => 3.0, 'transport' => 0, 'price' => 41.25],
        ['rmr' => '66182', 'farmer_code' => 'FRM-010', 'liters' => 88, 'rejected' => 0, 'acidity' => 0.17, 'fat' => 2.5, 'transport' => 0, 'price' => 40.00],
        ['rmr' => '66183', 'farmer_code' => 'FRM-011', 'liters' => 22, 'rejected' => 0, 'acidity' => 0.17, 'fat' => 5.0, 'transport' => 0, 'price' => 70.00],
        ['rmr' => '66184', 'farmer_code' => 'FRM-012', 'liters' => 117, 'rejected' => 0, 'acidity' => 0.17, 'fat' => 3.0, 'transport' => 500, 'price' => 41.25],
        ['rmr' => '66185', 'farmer_code' => 'FRM-013', 'liters' => 147, 'rejected' => 0, 'acidity' => 0.17, 'fat' => 3.0, 'transport' => 500, 'price' => 40.75],
        ['rmr' => '66186', 'farmer_code' => 'FRM-014', 'liters' => 154, 'rejected' => 0, 'acidity' => 0.17, 'fat' => 2.5, 'transport' => 500, 'price' => 40.50],
        ['rmr' => '66187', 'farmer_code' => 'FRM-015', 'liters' => 68, 'rejected' => 0, 'acidity' => 0.18, 'fat' => 3.0, 'transport' => 500, 'price' => 40.25],
        ['rmr' => '66188', 'farmer_code' => 'FRM-016', 'liters' => 10, 'rejected' => 0, 'acidity' => 0.18, 'fat' => 5.0, 'transport' => 0, 'price' => 69.25],
        ['rmr' => '66189', 'farmer_code' => 'FRM-017', 'liters' => 194, 'rejected' => 0, 'acidity' => 0.17, 'fat' => 3.0, 'transport' => 1000, 'price' => 40.75],
    ];
    
    echo "<h3>Importing Deliveries (October 21, 2025):</h3>";
    
    $insertDelivery = $pdo->prepare("
        INSERT INTO milk_deliveries 
        (delivery_code, rmr_number, farmer_id, delivery_date, volume_liters, rejected_liters, 
         accepted_liters, sediment_grade, acidity_ta, fat_percentage, transport_cost, 
         price_per_liter, total_amount, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'G-1', ?, ?, ?, ?, ?, ?)
    ");
    
    $counter = 1;
    foreach ($deliveries as $d) {
        // Get farmer ID
        $fstmt = $pdo->prepare("SELECT id FROM farmers WHERE farmer_code = ?");
        $fstmt->execute([$d['farmer_code']]);
        $farmer = $fstmt->fetch();
        
        $accepted = $d['liters'] - $d['rejected'];
        $total = ($accepted * $d['price']) - $d['transport'];
        $status = $d['rejected'] > 0 ? 'partial' : 'accepted';
        $deliveryCode = 'DLV-2025-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
        
        $insertDelivery->execute([
            $deliveryCode,
            $d['rmr'],
            $farmer['id'],
            '2025-10-21',
            $d['liters'],
            $d['rejected'],
            $accepted,
            $d['acidity'],
            $d['fat'],
            $d['transport'],
            $d['price'],
            $total,
            $status
        ]);
        
        echo "<p style='color:green'>✓ Delivery {$deliveryCode} (RMR#{$d['rmr']}): {$accepted}L @ ₱{$d['price']}/L = ₱" . number_format($total, 2) . "</p>";
        $counter++;
    }
    
    // Calculate totals
    $totals = $pdo->query("
        SELECT 
            SUM(volume_liters) as total_liters,
            SUM(rejected_liters) as total_rejected,
            SUM(accepted_liters) as total_accepted,
            SUM(transport_cost) as total_transport,
            SUM(total_amount) as grand_total
        FROM milk_deliveries
    ")->fetch();
    
    echo "<h3>Summary:</h3>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><td>Total Farmers</td><td><strong>17</strong></td></tr>";
    echo "<tr><td>Total Deliveries</td><td><strong>17</strong></td></tr>";
    echo "<tr><td>Total Liters</td><td><strong>" . number_format($totals['total_liters'], 0) . " L</strong></td></tr>";
    echo "<tr><td>Rejected Liters</td><td><strong>" . number_format($totals['total_rejected'], 0) . " L</strong></td></tr>";
    echo "<tr><td>Accepted Liters</td><td><strong>" . number_format($totals['total_accepted'], 0) . " L</strong></td></tr>";
    echo "<tr><td>Transport Costs</td><td><strong>₱" . number_format($totals['total_transport'], 2) . "</strong></td></tr>";
    echo "<tr><td style='background:#dfd'>Grand Total</td><td style='background:#dfd'><strong>₱" . number_format($totals['grand_total'], 2) . "</strong></td></tr>";
    echo "</table>";
    
    echo "<p style='color:green; font-size:1.2em; margin-top:20px'>✅ All data imported successfully!</p>";
    echo "<p><a href='/HighlandFreshAppV4/html/qc/milk_receiving.html'>Go to Milk Receiving →</a></p>";
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}
