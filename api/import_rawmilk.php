<?php
/**
 * Import Daily Raw Milk Record Data
 * Access: http://localhost/HighlandFreshAppV4/api/import_rawmilk.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=UTF-8');

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'highland_fresh';

echo "<h1>Highland Fresh - Import Raw Milk Records</h1>";
echo "<p>Date: October 21, 2025</p>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // First, ensure farmers table exists with correct structure
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `farmers` (
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
    echo "<p style='color:green'>✓ Farmers table ready</p>";
    
    // Create milk_deliveries table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `milk_deliveries` (
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
    echo "<p style='color:green'>✓ Milk deliveries table ready</p>";
    
    // Supplier data from the Daily Raw Milk Record (October 21, 2025)
    $suppliers = [
        ['name' => 'Lacandula', 'type' => 'member'],
        ['name' => 'Galla', 'type' => 'member'],
        ['name' => 'DMDC', 'type' => 'member'],
        ['name' => 'Dumindin', 'type' => 'member'],
        ['name' => 'Paraguya', 'type' => 'member'],
        ['name' => 'MMDC', 'type' => 'member'],
        ['name' => 'Bernales', 'type' => 'member'],
        ['name' => 'Tagadan', 'type' => 'non_member'], // Higher price suggests special/non-member
        ['name' => 'Abonitalla', 'type' => 'member'],
        ['name' => 'C1/Dumaluan', 'type' => 'member'],
        ['name' => 'C1/Dumaluan Goat', 'type' => 'non_member'], // Goat milk - special pricing
        ['name' => 'C3/Valledor', 'type' => 'member'],
        ['name' => 'Jardin', 'type' => 'member'],
        ['name' => 'Malig', 'type' => 'member'],
        ['name' => 'Abriol', 'type' => 'member'],
        ['name' => 'Gargar', 'type' => 'member'],
        ['name' => 'Navarro', 'type' => 'member'],
    ];
    
    // Insert suppliers
    echo "<h3>Importing Suppliers (Farmers):</h3>";
    $farmerIds = [];
    $codeNum = 1;
    
    foreach ($suppliers as $supplier) {
        $code = 'FRM-' . str_pad($codeNum, 3, '0', STR_PAD_LEFT);
        
        // Check if farmer exists
        $stmt = $pdo->prepare("SELECT id FROM farmers WHERE first_name = ?");
        $stmt->execute([$supplier['name']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $farmerIds[$supplier['name']] = $existing['id'];
            echo "<p style='color:blue'>ℹ {$supplier['name']} already exists (ID: {$existing['id']})</p>";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO farmers (farmer_code, first_name, last_name, membership_type, base_price_per_liter, address)
                VALUES (?, ?, '', ?, 40.00, 'Misamis Oriental')
            ");
            $stmt->execute([$code, $supplier['name'], $supplier['type']]);
            $farmerIds[$supplier['name']] = $pdo->lastInsertId();
            echo "<p style='color:green'>✓ Added: {$supplier['name']} ({$code})</p>";
        }
        $codeNum++;
    }
    
    // Raw Milk Delivery Records from October 21, 2025
    $deliveries = [
        ['supplier' => 'Lacandula', 'rmr' => '66173', 'liters' => 55, 'rejected' => 0, 'sediment' => 'G-1', 'ta' => 0.19, 'fat' => 2.5, 'transpo' => 0, 'price' => 39.25, 'amount' => 2158.75],
        ['supplier' => 'Galla', 'rmr' => '66174', 'liters' => 112, 'rejected' => 0, 'sediment' => 'G-1', 'ta' => 0.20, 'fat' => 5.0, 'transpo' => 500.00, 'price' => 40.00, 'amount' => 3980.00],
        ['supplier' => 'DMDC', 'rmr' => '66175', 'liters' => 20, 'rejected' => 87, 'sediment' => 'G-1', 'ta' => 0.19, 'fat' => 5.0, 'transpo' => 141.51, 'price' => 40.25, 'amount' => 663.49],
        ['supplier' => 'Dumindin', 'rmr' => '66176', 'liters' => 93, 'rejected' => 0, 'sediment' => 'G-1', 'ta' => 0.19, 'fat' => 2.9, 'transpo' => 658.02, 'price' => 39.25, 'amount' => 2992.23],
        ['supplier' => 'Paraguya', 'rmr' => '66177', 'liters' => 59, 'rejected' => 0, 'sediment' => 'G-1', 'ta' => 0.19, 'fat' => 4.4, 'transpo' => 417.45, 'price' => 40.00, 'amount' => 1942.55],
        ['supplier' => 'MMDC', 'rmr' => '66178', 'liters' => 40, 'rejected' => 0, 'sediment' => 'G-1', 'ta' => 0.19, 'fat' => 3.7, 'transpo' => 283.02, 'price' => 39.75, 'amount' => 1306.98],
        ['supplier' => 'Bernales', 'rmr' => '66179', 'liters' => 598, 'rejected' => 0, 'sediment' => 'G-1', 'ta' => 0.18, 'fat' => 3.6, 'transpo' => 0, 'price' => 40.00, 'amount' => 23920.00],
        ['supplier' => 'Tagadan', 'rmr' => '66180', 'liters' => 26, 'rejected' => 0, 'sediment' => 'G-1', 'ta' => 0.18, 'fat' => 4.0, 'transpo' => 0, 'price' => 70.00, 'amount' => 1820.00],
        ['supplier' => 'Abonitalla', 'rmr' => '66181', 'liters' => 124, 'rejected' => 0, 'sediment' => 'G-1', 'ta' => 0.19, 'fat' => 2.8, 'transpo' => 0, 'price' => 39.25, 'amount' => 4867.00],
        ['supplier' => 'C1/Dumaluan', 'rmr' => '66182', 'liters' => 201, 'rejected' => 0, 'sediment' => 'G-1', 'ta' => 0.20, 'fat' => 3.6, 'transpo' => 258.35, 'price' => 39.50, 'amount' => 7681.15],
        ['supplier' => 'C1/Dumaluan Goat', 'rmr' => '66183', 'liters' => 8, 'rejected' => 0, 'sediment' => 'G-1', 'ta' => 0.19, 'fat' => 2.5, 'transpo' => 10.28, 'price' => 69.25, 'amount' => 543.72],
        ['supplier' => 'C3/Valledor', 'rmr' => '66184', 'liters' => 149, 'rejected' => 57, 'sediment' => 'G-1', 'ta' => 0.19, 'fat' => 3.9, 'transpo' => 191.52, 'price' => 39.75, 'amount' => 5731.23],
        ['supplier' => 'Jardin', 'rmr' => '66185', 'liters' => 42, 'rejected' => 0, 'sediment' => 'G-1', 'ta' => 0.19, 'fat' => 5.0, 'transpo' => 53.98, 'price' => 40.25, 'amount' => 1636.52],
        ['supplier' => 'Malig', 'rmr' => '66186', 'liters' => 91, 'rejected' => 0, 'sediment' => 'G-1', 'ta' => 0.18, 'fat' => 4.8, 'transpo' => 116.97, 'price' => 40.25, 'amount' => 3545.78],
        ['supplier' => 'Abriol', 'rmr' => '66187', 'liters' => 173, 'rejected' => 27, 'sediment' => 'G-1', 'ta' => 0.19, 'fat' => 4.4, 'transpo' => 222.37, 'price' => 40.00, 'amount' => 6697.63],
        ['supplier' => 'Gargar', 'rmr' => '66188', 'liters' => 401, 'rejected' => 0, 'sediment' => 'G-1', 'ta' => 0.19, 'fat' => 3.7, 'transpo' => 515.42, 'price' => 39.75, 'amount' => 15424.33],
        ['supplier' => 'Navarro', 'rmr' => '66189', 'liters' => 102, 'rejected' => 0, 'sediment' => 'G-1', 'ta' => 0.19, 'fat' => 5.0, 'transpo' => 131.11, 'price' => 40.25, 'amount' => 3974.39],
    ];
    
    // Insert deliveries
    echo "<h3>Importing Milk Deliveries:</h3>";
    $deliveryDate = '2025-10-21';
    $totalLiters = 0;
    $totalAmount = 0;
    
    foreach ($deliveries as $delivery) {
        $farmerId = $farmerIds[$delivery['supplier']] ?? null;
        
        if (!$farmerId) {
            echo "<p style='color:red'>✗ Farmer not found: {$delivery['supplier']}</p>";
            continue;
        }
        
        $deliveryCode = 'DLV-' . $delivery['rmr'];
        $acceptedLiters = $delivery['liters'] - $delivery['rejected'];
        $status = $delivery['rejected'] > 0 ? 'partial' : 'accepted';
        
        // Check if delivery exists
        $stmt = $pdo->prepare("SELECT id FROM milk_deliveries WHERE rmr_number = ?");
        $stmt->execute([$delivery['rmr']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            echo "<p style='color:blue'>ℹ RMR #{$delivery['rmr']} already exists</p>";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO milk_deliveries 
                (delivery_code, rmr_number, farmer_id, delivery_date, volume_liters, rejected_liters, 
                 accepted_liters, sediment_grade, acidity_ta, fat_percentage, transport_cost, 
                 price_per_liter, total_amount, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $deliveryCode,
                $delivery['rmr'],
                $farmerId,
                $deliveryDate,
                $delivery['liters'],
                $delivery['rejected'],
                $acceptedLiters,
                $delivery['sediment'],
                $delivery['ta'],
                $delivery['fat'],
                $delivery['transpo'],
                $delivery['price'],
                $delivery['amount'],
                $status
            ]);
            
            $statusBadge = $status === 'partial' ? '<span style="color:orange">[PARTIAL]</span>' : '<span style="color:green">[ACCEPTED]</span>';
            echo "<p>✓ RMR #{$delivery['rmr']} - {$delivery['supplier']}: {$delivery['liters']}L @ ₱{$delivery['price']}/L = ₱" . number_format($delivery['amount'], 2) . " {$statusBadge}</p>";
        }
        
        $totalLiters += $delivery['liters'];
        $totalAmount += $delivery['amount'];
    }
    
    echo "<hr>";
    echo "<h2 style='color:green'>✓ Import Complete!</h2>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background:#16a34a; color:white;'><th>Summary</th><th>Value</th></tr>";
    echo "<tr><td>Total Suppliers</td><td><strong>" . count($suppliers) . "</strong></td></tr>";
    echo "<tr><td>Total Deliveries</td><td><strong>" . count($deliveries) . "</strong></td></tr>";
    echo "<tr><td>Total Liters</td><td><strong>" . number_format($totalLiters, 2) . " L</strong></td></tr>";
    echo "<tr><td>Total Rejected</td><td><strong>171.00 L</strong></td></tr>";
    echo "<tr><td>Total Transport</td><td><strong>₱3,500.00</strong></td></tr>";
    echo "<tr><td>Total Amount</td><td><strong>₱" . number_format($totalAmount, 2) . "</strong></td></tr>";
    echo "</table>";
    
    echo "<br>";
    echo "<a href='../html/login.html' style='display:inline-block; padding:10px 20px; background:#16a34a; color:white; text-decoration:none; border-radius:5px; margin-right:10px;'>Go to Login →</a>";
    echo "<a href='../html/qc/milk_receiving.html' style='display:inline-block; padding:10px 20px; background:#0ea5e9; color:white; text-decoration:none; border-radius:5px;'>View Milk Receiving →</a>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
