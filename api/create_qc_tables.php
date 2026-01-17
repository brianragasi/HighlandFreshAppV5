<?php
/**
 * Create Missing QC Tables
 * Run this to create all required tables for the QC module
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=UTF-8');

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'highland_fresh';

echo "<h1>Highland Fresh - Create QC Tables</h1>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Create qc_milk_tests table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `qc_milk_tests` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `test_code` VARCHAR(30) NOT NULL,
            `delivery_id` INT(11) NOT NULL,
            `test_datetime` DATETIME NOT NULL,
            `fat_percentage` DECIMAL(5,2) NOT NULL,
            `acidity_ph` DECIMAL(4,2) NULL,
            `temperature_celsius` DECIMAL(4,1) NULL,
            `sediment_level` ENUM('clean', 'slight', 'moderate', 'heavy') DEFAULT 'clean',
            `density` DECIMAL(6,4) NULL,
            `protein_percentage` DECIMAL(5,2) NULL,
            `snf_percentage` DECIMAL(5,2) NULL COMMENT 'Solids-Not-Fat',
            `grade` ENUM('A', 'B', 'C', 'D', 'Rejected') NOT NULL,
            `is_accepted` TINYINT(1) NOT NULL DEFAULT 1,
            `rejection_reason` TEXT NULL,
            `base_price_per_liter` DECIMAL(10,2) NOT NULL,
            `fat_adjustment` DECIMAL(10,2) DEFAULT 0,
            `quality_adjustment` DECIMAL(10,2) DEFAULT 0,
            `final_price_per_liter` DECIMAL(10,2) NOT NULL,
            `total_amount` DECIMAL(12,2) NOT NULL,
            `tested_by` INT(11) NOT NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `test_code` (`test_code`),
            KEY `idx_delivery` (`delivery_id`),
            KEY `idx_grade` (`grade`),
            KEY `idx_test_date` (`test_datetime`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created qc_milk_tests table</p>";
    
    // 2. Create production_batches table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `production_batches` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `batch_code` VARCHAR(30) NOT NULL,
            `product_type` VARCHAR(50) NOT NULL,
            `product_name` VARCHAR(100) NOT NULL,
            `production_date` DATE NOT NULL,
            `expiry_date` DATE NOT NULL,
            `quantity_produced` INT(11) NOT NULL,
            `unit` VARCHAR(20) DEFAULT 'units',
            `milk_batch_ids` TEXT NULL COMMENT 'JSON array of milk delivery IDs used',
            `status` ENUM('in_production', 'pending_qc', 'qc_passed', 'qc_failed', 'released', 'on_hold') DEFAULT 'in_production',
            `qc_release_id` INT(11) NULL,
            `produced_by` INT(11) NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `batch_code` (`batch_code`),
            KEY `idx_status` (`status`),
            KEY `idx_production_date` (`production_date`),
            KEY `idx_expiry_date` (`expiry_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created production_batches table</p>";
    
    // 3. Create batch_release table (QC verification for batches)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `batch_releases` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `release_code` VARCHAR(30) NOT NULL,
            `batch_id` INT(11) NOT NULL,
            `inspection_date` DATETIME NOT NULL,
            `appearance_check` TINYINT(1) DEFAULT 0,
            `odor_check` TINYINT(1) DEFAULT 0,
            `taste_check` TINYINT(1) DEFAULT 0,
            `packaging_check` TINYINT(1) DEFAULT 0,
            `label_check` TINYINT(1) DEFAULT 0,
            `temperature_check` TINYINT(1) DEFAULT 0,
            `sample_retained` TINYINT(1) DEFAULT 0,
            `overall_status` ENUM('pending', 'approved', 'rejected', 'on_hold') DEFAULT 'pending',
            `rejection_reason` TEXT NULL,
            `inspected_by` INT(11) NOT NULL,
            `approved_by` INT(11) NULL,
            `approval_datetime` DATETIME NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `release_code` (`release_code`),
            KEY `idx_batch` (`batch_id`),
            KEY `idx_status` (`overall_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created batch_releases table</p>";
    
    // 4. Create finished_goods_inventory table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `finished_goods_inventory` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `sku` VARCHAR(30) NOT NULL,
            `batch_id` INT(11) NOT NULL,
            `product_name` VARCHAR(100) NOT NULL,
            `product_type` VARCHAR(50) NOT NULL,
            `quantity_initial` INT(11) NOT NULL,
            `quantity_available` INT(11) NOT NULL,
            `quantity_sold` INT(11) DEFAULT 0,
            `quantity_damaged` INT(11) DEFAULT 0,
            `quantity_expired` INT(11) DEFAULT 0,
            `unit_price` DECIMAL(10,2) NOT NULL,
            `production_date` DATE NOT NULL,
            `expiry_date` DATE NOT NULL,
            `location` VARCHAR(50) DEFAULT 'Main Warehouse',
            `status` ENUM('available', 'low_stock', 'out_of_stock', 'expired', 'recalled') DEFAULT 'available',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `sku` (`sku`),
            KEY `idx_batch` (`batch_id`),
            KEY `idx_expiry` (`expiry_date`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created finished_goods_inventory table</p>";
    
    // 5. Create ccp_logs table (Critical Control Points)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `ccp_logs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `log_code` VARCHAR(30) NOT NULL,
            `ccp_point` VARCHAR(50) NOT NULL COMMENT 'e.g., pasteurization, cooling, storage',
            `batch_id` INT(11) NULL,
            `check_datetime` DATETIME NOT NULL,
            `temperature_reading` DECIMAL(5,1) NULL,
            `temperature_min` DECIMAL(5,1) NULL,
            `temperature_max` DECIMAL(5,1) NULL,
            `duration_minutes` INT(11) NULL,
            `is_within_limits` TINYINT(1) DEFAULT 1,
            `corrective_action` TEXT NULL,
            `verified_by` INT(11) NOT NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `log_code` (`log_code`),
            KEY `idx_ccp_point` (`ccp_point`),
            KEY `idx_batch` (`batch_id`),
            KEY `idx_check_date` (`check_datetime`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created ccp_logs table</p>";
    
    // 6. Create yogurt_transformations table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `yogurt_transformations` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `transformation_code` VARCHAR(30) NOT NULL,
            `source_inventory_id` INT(11) NOT NULL,
            `source_quantity` INT(11) NOT NULL,
            `target_product` VARCHAR(100) NOT NULL DEFAULT 'Yogurt',
            `target_quantity` INT(11) NOT NULL,
            `transformation_date` DATE NOT NULL,
            `reason` TEXT NULL,
            `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
            `initiated_by` INT(11) NOT NULL,
            `completed_by` INT(11) NULL,
            `completed_at` DATETIME NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `transformation_code` (`transformation_code`),
            KEY `idx_source` (`source_inventory_id`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created yogurt_transformations table</p>";
    
    // Verify all tables
    echo "<h3>Verifying Tables:</h3>";
    $tables = ['farmers', 'milk_deliveries', 'qc_milk_tests', 'production_batches', 
               'batch_releases', 'finished_goods_inventory', 'ccp_logs', 'yogurt_transformations', 'users'];
    
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) as c FROM $table")->fetch()['c'];
            echo "<p style='color:green'>✓ $table: $count records</p>";
        } catch (Exception $e) {
            echo "<p style='color:red'>✗ $table: Not found or error</p>";
        }
    }
    
    echo "<p style='color:green; font-size:1.2em; margin-top:20px'>✅ All QC tables created successfully!</p>";
    echo "<p><a href='/HighlandFreshAppV4/html/qc/dashboard.html'>Go to QC Dashboard →</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}
