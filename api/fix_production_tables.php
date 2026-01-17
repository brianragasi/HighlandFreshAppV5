<?php
/**
 * Highland Fresh - Fix Production Tables
 * Updates table structures to match API requirements
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=UTF-8');

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'highland_fresh';

echo "<h1>Highland Fresh - Fix Production Tables</h1>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // =============================================
    // 1. Fix production_ccp_logs table
    // =============================================
    echo "<h2>Fixing production_ccp_logs</h2>";
    
    // Drop and recreate with correct columns
    $pdo->exec("DROP TABLE IF EXISTS `production_ccp_logs`");
    
    $pdo->exec("
        CREATE TABLE `production_ccp_logs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `run_id` INT(11) NOT NULL,
            `check_type` ENUM('pasteurization', 'cooling', 'intermediate') NOT NULL,
            `temperature` DECIMAL(5,2) NOT NULL,
            `hold_time_mins` INT(11) DEFAULT 0,
            `target_temp` DECIMAL(5,2) NULL,
            `temp_tolerance` DECIMAL(5,2) DEFAULT 2.00,
            `status` ENUM('pass', 'fail', 'warning') DEFAULT 'pass',
            `check_datetime` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `verified_by` INT(11) NOT NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_run` (`run_id`),
            KEY `idx_check_type` (`check_type`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Recreated production_ccp_logs with correct columns</p>";
    
    // =============================================
    // 2. Fix ingredient_requisitions table
    // =============================================
    echo "<h2>Fixing ingredient_requisitions</h2>";
    
    $pdo->exec("DROP TABLE IF EXISTS `requisition_items`");
    $pdo->exec("DROP TABLE IF EXISTS `ingredient_requisitions`");
    
    $pdo->exec("
        CREATE TABLE `ingredient_requisitions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `requisition_code` VARCHAR(30) NOT NULL,
            `production_run_id` INT(11) NULL,
            `requested_by` INT(11) NOT NULL,
            `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
            `needed_by` DATETIME NULL,
            `purpose` VARCHAR(255) NULL,
            `total_items` INT(11) DEFAULT 0,
            `status` ENUM('draft', 'pending', 'approved', 'rejected', 'fulfilled', 'cancelled') DEFAULT 'draft',
            `approved_by` INT(11) NULL,
            `approved_at` DATETIME NULL,
            `rejection_reason` TEXT NULL,
            `fulfilled_by` INT(11) NULL,
            `fulfilled_at` DATETIME NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `requisition_code` (`requisition_code`),
            KEY `idx_status` (`status`),
            KEY `idx_production_run` (`production_run_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Recreated ingredient_requisitions with correct columns</p>";
    
    $pdo->exec("
        CREATE TABLE `requisition_items` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `requisition_id` INT(11) NOT NULL,
            `item_name` VARCHAR(100) NOT NULL,
            `quantity` DECIMAL(10,3) NOT NULL,
            `unit` VARCHAR(20) NOT NULL DEFAULT 'units',
            `notes` VARCHAR(255) NULL,
            PRIMARY KEY (`id`),
            KEY `idx_requisition` (`requisition_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Recreated requisition_items</p>";
    
    // =============================================
    // 3. Fix production_byproducts table
    // =============================================
    echo "<h2>Fixing production_byproducts</h2>";
    
    $pdo->exec("DROP TABLE IF EXISTS `production_byproducts`");
    
    $pdo->exec("
        CREATE TABLE `production_byproducts` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `run_id` INT(11) NOT NULL,
            `byproduct_type` ENUM('buttermilk', 'whey', 'cream', 'skim_milk', 'other') NOT NULL,
            `quantity` DECIMAL(10,2) NOT NULL,
            `unit` VARCHAR(20) NOT NULL DEFAULT 'liters',
            `destination` ENUM('warehouse', 'reprocess', 'dispose', 'sale') NULL,
            `status` ENUM('pending', 'transferred', 'disposed', 'sold') DEFAULT 'pending',
            `recorded_by` INT(11) NOT NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_run` (`run_id`),
            KEY `idx_type` (`byproduct_type`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Recreated production_byproducts with correct columns</p>";
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // =============================================
    // Insert Sample Data
    // =============================================
    echo "<h2>Inserting Sample Data</h2>";
    
    // Get a production run ID
    $runStmt = $pdo->query("SELECT id FROM production_runs LIMIT 1");
    $run = $runStmt->fetch();
    
    // Get a user ID (production staff)
    $userStmt = $pdo->query("SELECT id FROM users WHERE role = 'production_staff' LIMIT 1");
    $user = $userStmt->fetch();
    $userId = $user ? $user['id'] : 1;
    
    if ($run) {
        $runId = $run['id'];
        
        // Sample CCP logs
        $pdo->exec("
            INSERT INTO production_ccp_logs (run_id, check_type, temperature, hold_time_mins, target_temp, temp_tolerance, status, verified_by, notes)
            VALUES 
            ({$runId}, 'pasteurization', 82.5, 15, 81.00, 2.00, 'pass', {$userId}, 'Initial pasteurization check'),
            ({$runId}, 'cooling', 3.5, 30, 4.00, 1.00, 'pass', {$userId}, 'Post-processing cooling verified')
        ");
        echo "<p style='color:blue'>✓ Added sample CCP logs</p>";
        
        // Sample byproducts
        $pdo->exec("
            INSERT INTO production_byproducts (run_id, byproduct_type, quantity, unit, destination, status, recorded_by, notes)
            VALUES 
            ({$runId}, 'buttermilk', 25.5, 'liters', 'warehouse', 'pending', {$userId}, 'From butter production'),
            ({$runId}, 'whey', 15.0, 'liters', 'reprocess', 'pending', {$userId}, 'From cheese production')
        ");
        echo "<p style='color:blue'>✓ Added sample byproducts</p>";
    }
    
    // Sample requisition
    $today = date('Ymd');
    $pdo->exec("
        INSERT INTO ingredient_requisitions (requisition_code, production_run_id, requested_by, priority, needed_by, purpose, total_items, status)
        VALUES 
        ('REQ-{$today}-001', " . ($run ? $runId : 'NULL') . ", {$userId}, 'normal', NOW() + INTERVAL 1 DAY, 'Production batch materials', 3, 'pending')
    ");
    $reqId = $pdo->lastInsertId();
    
    $pdo->exec("
        INSERT INTO requisition_items (requisition_id, item_name, quantity, unit, notes)
        VALUES 
        ({$reqId}, 'Sugar', 50, 'kg', 'For sweetened milk production'),
        ({$reqId}, 'Cocoa Powder', 10, 'kg', 'For chocolate milk'),
        ({$reqId}, 'PET Bottles 1L', 500, 'units', 'Packaging for batch')
    ");
    echo "<p style='color:blue'>✓ Added sample requisition with items</p>";
    
    echo "<h2 style='color:green'>✓ All tables fixed successfully!</h2>";
    echo "<p>You can now use the production module:</p>";
    echo "<ul>";
    echo "<li><a href='../html/production/dashboard.html'>Production Dashboard</a></li>";
    echo "<li><a href='../html/production/ccp_logging.html'>CCP Logging</a></li>";
    echo "<li><a href='../html/production/requisitions.html'>Requisitions</a></li>";
    echo "<li><a href='../html/production/byproducts.html'>Byproducts</a></li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>
