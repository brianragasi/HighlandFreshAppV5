<?php
/**
 * Highland Fresh - Setup Production Tables (Drop & Recreate)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=UTF-8');

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'highland_fresh';

echo "<h1>Highland Fresh - Setup Production Tables</h1>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Drop existing tables
    $tables = ['recipe_ingredients', 'master_recipes', 'production_ccp_logs', 'production_runs', 
               'requisition_items', 'ingredient_requisitions', 'production_byproducts', 'ingredient_consumption'];
    
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
        echo "<p>Dropped $table if existed</p>";
    }
    
    // 1. Master Recipes Table
    $pdo->exec("
        CREATE TABLE `master_recipes` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `recipe_code` VARCHAR(30) NOT NULL,
            `product_name` VARCHAR(100) NOT NULL,
            `product_type` ENUM('bottled_milk', 'cheese', 'butter', 'yogurt', 'milk_bar') NOT NULL,
            `variant` VARCHAR(50) NULL,
            `size_ml` INT(11) NULL,
            `size_grams` INT(11) NULL,
            `description` TEXT NULL,
            `base_milk_liters` DECIMAL(10,2) NOT NULL,
            `expected_yield` INT(11) NOT NULL,
            `yield_unit` VARCHAR(20) DEFAULT 'units',
            `shelf_life_days` INT(11) NOT NULL DEFAULT 7,
            `pasteurization_temp` DECIMAL(5,2) DEFAULT 81.00,
            `pasteurization_time_mins` INT(11) DEFAULT 15,
            `cooling_temp` DECIMAL(5,2) DEFAULT 4.00,
            `special_instructions` TEXT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_by` INT(11) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `recipe_code` (`recipe_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created master_recipes</p>";
    
    // 2. Recipe Ingredients
    $pdo->exec("
        CREATE TABLE `recipe_ingredients` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `recipe_id` INT(11) NOT NULL,
            `ingredient_name` VARCHAR(100) NOT NULL,
            `ingredient_category` ENUM('milk', 'sugar', 'flavoring', 'powder', 'culture', 'rennet', 'salt', 'packaging', 'other') NOT NULL,
            `quantity` DECIMAL(10,3) NOT NULL,
            `unit` VARCHAR(20) NOT NULL,
            `is_optional` TINYINT(1) DEFAULT 0,
            `notes` VARCHAR(255) NULL,
            PRIMARY KEY (`id`),
            KEY `idx_recipe` (`recipe_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created recipe_ingredients</p>";
    
    // 3. Production Runs
    $pdo->exec("
        CREATE TABLE `production_runs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `run_code` VARCHAR(30) NOT NULL,
            `recipe_id` INT(11) NOT NULL,
            `batch_id` INT(11) NULL,
            `planned_quantity` INT(11) NOT NULL,
            `actual_quantity` INT(11) NULL,
            `milk_batch_source` TEXT NULL,
            `milk_liters_used` DECIMAL(10,2) NULL,
            `status` ENUM('planned', 'in_progress', 'pasteurization', 'processing', 'cooling', 'packaging', 'completed', 'cancelled') DEFAULT 'planned',
            `start_datetime` DATETIME NULL,
            `end_datetime` DATETIME NULL,
            `started_by` INT(11) NULL,
            `completed_by` INT(11) NULL,
            `yield_variance` DECIMAL(10,2) NULL,
            `variance_reason` TEXT NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `run_code` (`run_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created production_runs</p>";
    
    // 4. Production CCP Logs
    $pdo->exec("
        CREATE TABLE `production_ccp_logs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `log_code` VARCHAR(30) NOT NULL,
            `run_id` INT(11) NOT NULL,
            `ccp_type` ENUM('pasteurization', 'homogenization', 'cooling', 'fermentation', 'pressing', 'churning', 'storage') NOT NULL,
            `check_datetime` DATETIME NOT NULL,
            `temperature_actual` DECIMAL(5,2) NULL,
            `temperature_target` DECIMAL(5,2) NULL,
            `duration_minutes` INT(11) NULL,
            `duration_target` INT(11) NULL,
            `is_within_limits` TINYINT(1) DEFAULT 1,
            `deviation_action` TEXT NULL,
            `equipment_used` VARCHAR(100) NULL,
            `verified_by` INT(11) NOT NULL,
            `supervisor_verified` TINYINT(1) DEFAULT 0,
            `supervisor_id` INT(11) NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `log_code` (`log_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created production_ccp_logs</p>";
    
    // 5. Ingredient Requisitions
    $pdo->exec("
        CREATE TABLE `ingredient_requisitions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `requisition_code` VARCHAR(30) NOT NULL,
            `run_id` INT(11) NULL,
            `requested_by` INT(11) NOT NULL,
            `request_datetime` DATETIME NOT NULL,
            `purpose` VARCHAR(255) NOT NULL,
            `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
            `status` ENUM('draft', 'pending_approval', 'approved', 'rejected', 'partially_released', 'released', 'cancelled') DEFAULT 'draft',
            `approved_by` INT(11) NULL,
            `approved_datetime` DATETIME NULL,
            `rejection_reason` TEXT NULL,
            `released_by` INT(11) NULL,
            `released_datetime` DATETIME NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `requisition_code` (`requisition_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created ingredient_requisitions</p>";
    
    // 6. Requisition Items
    $pdo->exec("
        CREATE TABLE `requisition_items` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `requisition_id` INT(11) NOT NULL,
            `item_name` VARCHAR(100) NOT NULL,
            `item_category` ENUM('ingredient', 'packaging', 'mro', 'other') NOT NULL,
            `quantity_requested` DECIMAL(10,3) NOT NULL,
            `quantity_approved` DECIMAL(10,3) NULL,
            `quantity_released` DECIMAL(10,3) NULL,
            `unit` VARCHAR(20) NOT NULL,
            `unit_cost` DECIMAL(10,2) NULL,
            `notes` VARCHAR(255) NULL,
            PRIMARY KEY (`id`),
            KEY `idx_requisition` (`requisition_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created requisition_items</p>";
    
    // 7. Production Byproducts
    $pdo->exec("
        CREATE TABLE `production_byproducts` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `byproduct_code` VARCHAR(30) NOT NULL,
            `run_id` INT(11) NOT NULL,
            `byproduct_name` VARCHAR(100) NOT NULL,
            `quantity` DECIMAL(10,2) NOT NULL,
            `unit` VARCHAR(20) NOT NULL,
            `disposition` ENUM('inventory', 'waste', 'reprocess', 'sold', 'donated') DEFAULT 'inventory',
            `destination_notes` VARCHAR(255) NULL,
            `recorded_by` INT(11) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `byproduct_code` (`byproduct_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created production_byproducts</p>";
    
    // 8. Ingredient Consumption
    $pdo->exec("
        CREATE TABLE `ingredient_consumption` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `run_id` INT(11) NOT NULL,
            `requisition_item_id` INT(11) NULL,
            `ingredient_name` VARCHAR(100) NOT NULL,
            `recipe_quantity` DECIMAL(10,3) NOT NULL,
            `actual_quantity` DECIMAL(10,3) NOT NULL,
            `variance` DECIMAL(10,3) NULL,
            `unit` VARCHAR(20) NOT NULL,
            `variance_reason` VARCHAR(255) NULL,
            `recorded_by` INT(11) NOT NULL,
            `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_run` (`run_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created ingredient_consumption</p>";
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Insert sample recipes
    echo "<h3>Adding Sample Recipes:</h3>";
    
    $recipes = [
        ['RCP-001', 'Highland Fresh Choco Milk 330ml', 'bottled_milk', 'Chocolate', 330, null, 100, 300, 'units', 7],
        ['RCP-002', 'Highland Fresh Plain Milk 500ml', 'bottled_milk', 'Plain', 500, null, 100, 200, 'units', 7],
        ['RCP-003', 'Highland Fresh Melon Milk 330ml', 'bottled_milk', 'Melon', 330, null, 100, 300, 'units', 7],
        ['RCP-004', 'Highland Fresh Strawberry Milk 330ml', 'bottled_milk', 'Strawberry', 330, null, 100, 300, 'units', 7],
        ['RCP-005', 'Highland Fresh Cheese 250g', 'cheese', 'White Cheese', null, 250, 50, 20, 'kg', 30],
        ['RCP-006', 'Highland Fresh Butter 250g', 'butter', 'Salted', null, 250, 100, 10, 'kg', 60],
        ['RCP-007', 'Highland Fresh Yogurt 200ml', 'yogurt', 'Strawberry', 200, null, 50, 250, 'units', 14],
        ['RCP-008', 'Highland Fresh Milk Bar Choco', 'milk_bar', 'Chocolate', 75, null, 50, 500, 'units', 30],
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO master_recipes (recipe_code, product_name, product_type, variant, size_ml, size_grams, 
            base_milk_liters, expected_yield, yield_unit, shelf_life_days, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    
    foreach ($recipes as $r) {
        $stmt->execute($r);
        echo "<p style='color:green'>✓ {$r[1]}</p>";
    }
    
    // Add ingredients for Choco Milk
    echo "<h3>Adding Choco Milk Recipe Ingredients:</h3>";
    $ingredients = [
        [1, 'Raw Milk', 'milk', 100, 'liters'],
        [1, 'Cocoa Powder', 'powder', 2.5, 'kg'],
        [1, 'White Sugar', 'sugar', 8, 'kg'],
        [1, 'Milk Powder', 'powder', 1.5, 'kg'],
        [1, 'Bottles 330ml', 'packaging', 310, 'pcs'],
        [1, 'Bottle Caps', 'packaging', 310, 'pcs'],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_name, ingredient_category, quantity, unit) VALUES (?, ?, ?, ?, ?)");
    foreach ($ingredients as $i) {
        $stmt->execute($i);
        echo "<p style='color:green'>✓ {$i[3]} {$i[4]} {$i[1]}</p>";
    }
    
    // Add production staff user
    echo "<h3>Adding Production User:</h3>";
    $check = $pdo->query("SELECT id FROM users WHERE username = 'production_staff'")->fetch();
    if (!$check) {
        $hash = password_hash('password', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password, full_name, first_name, last_name, role, is_active) 
                    VALUES ('production_staff', '$hash', 'Juan Dela Cruz', 'Juan', 'Dela Cruz', 'production_staff', 1)");
        echo "<p style='color:green'>✓ Added production_staff (password: password)</p>";
    } else {
        echo "<p style='color:blue'>ℹ production_staff exists</p>";
    }
    
    echo "<p style='color:green; font-size:1.2em; margin-top:20px'>✅ Production tables ready!</p>";
    echo "<p><a href='/HighlandFreshAppV4/html/production/dashboard.html'>Go to Production Dashboard →</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}
