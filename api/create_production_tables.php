<?php
/**
 * Highland Fresh - Create Production Module Tables
 * Run this to create all required tables for the Production Staff module
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=UTF-8');

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'highland_fresh';

echo "<h1>Highland Fresh - Create Production Tables</h1>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Master Recipes Table (GM-defined)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `master_recipes` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `recipe_code` VARCHAR(30) NOT NULL,
            `product_name` VARCHAR(100) NOT NULL,
            `product_type` ENUM('bottled_milk', 'cheese', 'butter', 'yogurt', 'milk_bar') NOT NULL,
            `variant` VARCHAR(50) NULL COMMENT 'e.g., Choco, Melon, Plain, Strawberry',
            `size_ml` INT(11) NULL COMMENT 'For bottled products',
            `size_grams` INT(11) NULL COMMENT 'For cheese/butter',
            `description` TEXT NULL,
            `base_milk_liters` DECIMAL(10,2) NOT NULL COMMENT 'Liters of milk needed per batch',
            `expected_yield` INT(11) NOT NULL COMMENT 'Expected units/kg output',
            `yield_unit` VARCHAR(20) DEFAULT 'units' COMMENT 'units, kg, liters',
            `shelf_life_days` INT(11) NOT NULL DEFAULT 7,
            `pasteurization_temp` DECIMAL(5,2) DEFAULT 81.00 COMMENT 'Required temp in Celsius',
            `pasteurization_time_mins` INT(11) DEFAULT 15,
            `cooling_temp` DECIMAL(5,2) DEFAULT 4.00 COMMENT 'Target cooling temp',
            `special_instructions` TEXT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_by` INT(11) NOT NULL COMMENT 'GM who created',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `recipe_code` (`recipe_code`),
            KEY `idx_product_type` (`product_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created master_recipes table</p>";
    
    // 2. Recipe Ingredients (BOM - Bill of Materials)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `recipe_ingredients` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `recipe_id` INT(11) NOT NULL,
            `ingredient_name` VARCHAR(100) NOT NULL,
            `ingredient_category` ENUM('milk', 'sugar', 'flavoring', 'powder', 'culture', 'rennet', 'salt', 'packaging', 'other') NOT NULL,
            `quantity` DECIMAL(10,3) NOT NULL,
            `unit` VARCHAR(20) NOT NULL COMMENT 'liters, kg, grams, pcs, ml',
            `is_optional` TINYINT(1) DEFAULT 0,
            `notes` VARCHAR(255) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_recipe` (`recipe_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created recipe_ingredients table</p>";
    
    // 3. Production Runs (Active Batches being produced)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `production_runs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `run_code` VARCHAR(30) NOT NULL,
            `recipe_id` INT(11) NOT NULL,
            `batch_id` INT(11) NULL COMMENT 'Links to production_batches after completion',
            `planned_quantity` INT(11) NOT NULL,
            `actual_quantity` INT(11) NULL,
            `milk_batch_source` TEXT NULL COMMENT 'JSON of milk delivery IDs used',
            `milk_liters_used` DECIMAL(10,2) NULL,
            `status` ENUM('planned', 'in_progress', 'pasteurization', 'processing', 'cooling', 'packaging', 'completed', 'cancelled') DEFAULT 'planned',
            `start_datetime` DATETIME NULL,
            `end_datetime` DATETIME NULL,
            `started_by` INT(11) NULL,
            `completed_by` INT(11) NULL,
            `yield_variance` DECIMAL(10,2) NULL COMMENT 'Actual - Expected',
            `variance_reason` TEXT NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `run_code` (`run_code`),
            KEY `idx_recipe` (`recipe_id`),
            KEY `idx_status` (`status`),
            KEY `idx_start_date` (`start_datetime`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created production_runs table</p>";
    
    // 4. Production CCP Logs (Critical Control Points)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `production_ccp_logs` (
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
            `deviation_action` TEXT NULL COMMENT 'Corrective action if out of limits',
            `equipment_used` VARCHAR(100) NULL COMMENT 'e.g., Retort, Homogenizer, Chiller',
            `verified_by` INT(11) NOT NULL,
            `supervisor_verified` TINYINT(1) DEFAULT 0,
            `supervisor_id` INT(11) NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `log_code` (`log_code`),
            KEY `idx_run` (`run_id`),
            KEY `idx_ccp_type` (`ccp_type`),
            KEY `idx_check_date` (`check_datetime`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created production_ccp_logs table</p>";
    
    // 5. Ingredient Requisitions (Request from Warehouse Raw)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `ingredient_requisitions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `requisition_code` VARCHAR(30) NOT NULL,
            `run_id` INT(11) NULL COMMENT 'Linked production run',
            `requested_by` INT(11) NOT NULL,
            `request_datetime` DATETIME NOT NULL,
            `purpose` VARCHAR(255) NOT NULL,
            `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
            `status` ENUM('draft', 'pending_approval', 'approved', 'rejected', 'partially_released', 'released', 'cancelled') DEFAULT 'draft',
            `approved_by` INT(11) NULL COMMENT 'GM approval',
            `approved_datetime` DATETIME NULL,
            `rejection_reason` TEXT NULL,
            `released_by` INT(11) NULL COMMENT 'Warehouse staff',
            `released_datetime` DATETIME NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `requisition_code` (`requisition_code`),
            KEY `idx_run` (`run_id`),
            KEY `idx_status` (`status`),
            KEY `idx_request_date` (`request_datetime`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created ingredient_requisitions table</p>";
    
    // 6. Requisition Items
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `requisition_items` (
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
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_requisition` (`requisition_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created requisition_items table</p>";
    
    // 7. Production Byproducts (e.g., Buttermilk from Butter)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `production_byproducts` (
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
            UNIQUE KEY `byproduct_code` (`byproduct_code`),
            KEY `idx_run` (`run_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created production_byproducts table</p>";
    
    // 8. Ingredient Consumption Log (Actual usage tracking)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `ingredient_consumption` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `run_id` INT(11) NOT NULL,
            `requisition_item_id` INT(11) NULL,
            `ingredient_name` VARCHAR(100) NOT NULL,
            `recipe_quantity` DECIMAL(10,3) NOT NULL COMMENT 'Expected from recipe',
            `actual_quantity` DECIMAL(10,3) NOT NULL COMMENT 'Actually used',
            `variance` DECIMAL(10,3) NULL,
            `unit` VARCHAR(20) NOT NULL,
            `variance_reason` VARCHAR(255) NULL,
            `recorded_by` INT(11) NOT NULL,
            `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_run` (`run_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created ingredient_consumption table</p>";
    
    // ========== INSERT SAMPLE MASTER RECIPES ==========
    echo "<h3>Inserting Sample Recipes:</h3>";
    
    $recipes = [
        [
            'code' => 'RCP-001',
            'name' => 'Highland Fresh Choco Milk 330ml',
            'type' => 'bottled_milk',
            'variant' => 'Chocolate',
            'size_ml' => 330,
            'milk_liters' => 100,
            'yield' => 300,
            'shelf_days' => 7,
            'instructions' => 'Standard chocolate milk production with cocoa powder'
        ],
        [
            'code' => 'RCP-002',
            'name' => 'Highland Fresh Plain Milk 500ml',
            'type' => 'bottled_milk',
            'variant' => 'Plain',
            'size_ml' => 500,
            'milk_liters' => 100,
            'yield' => 200,
            'shelf_days' => 7,
            'instructions' => 'Plain pasteurized milk'
        ],
        [
            'code' => 'RCP-003',
            'name' => 'Highland Fresh Melon Milk 330ml',
            'type' => 'bottled_milk',
            'variant' => 'Melon',
            'size_ml' => 330,
            'milk_liters' => 100,
            'yield' => 300,
            'shelf_days' => 7,
            'instructions' => 'Melon flavored milk with melon extract'
        ],
        [
            'code' => 'RCP-004',
            'name' => 'Highland Fresh Cheese 250g',
            'type' => 'cheese',
            'variant' => 'White Cheese',
            'size_grams' => 250,
            'milk_liters' => 50,
            'yield' => 20,
            'yield_unit' => 'kg',
            'shelf_days' => 30,
            'instructions' => 'Traditional cheese with 2-hour pressing time'
        ],
        [
            'code' => 'RCP-005',
            'name' => 'Highland Fresh Butter 250g',
            'type' => 'butter',
            'variant' => 'Salted',
            'size_grams' => 250,
            'milk_liters' => 100,
            'yield' => 10,
            'yield_unit' => 'kg',
            'shelf_days' => 60,
            'instructions' => '24-hour cream storage, 45-60 min churning'
        ],
        [
            'code' => 'RCP-006',
            'name' => 'Highland Fresh Yogurt 200ml',
            'type' => 'yogurt',
            'variant' => 'Strawberry',
            'size_ml' => 200,
            'milk_liters' => 50,
            'yield' => 250,
            'shelf_days' => 14,
            'instructions' => 'Fermentation with live cultures, strawberry flavoring'
        ],
        [
            'code' => 'RCP-007',
            'name' => 'Highland Fresh Milk Bar',
            'type' => 'milk_bar',
            'variant' => 'Chocolate',
            'size_ml' => 75,
            'milk_liters' => 50,
            'yield' => 500,
            'shelf_days' => 30,
            'instructions' => 'Ice candy style milk bars in plastic film'
        ]
    ];
    
    $insertRecipe = $pdo->prepare("
        INSERT INTO master_recipes (recipe_code, product_name, product_type, variant, size_ml, size_grams, 
            base_milk_liters, expected_yield, yield_unit, shelf_life_days, special_instructions, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE product_name = VALUES(product_name)
    ");
    
    foreach ($recipes as $r) {
        $insertRecipe->execute([
            $r['code'],
            $r['name'],
            $r['type'],
            $r['variant'],
            $r['size_ml'] ?? null,
            $r['size_grams'] ?? null,
            $r['milk_liters'],
            $r['yield'],
            $r['yield_unit'] ?? 'units',
            $r['shelf_days'],
            $r['instructions']
        ]);
        echo "<p style='color:green'>✓ Added recipe: {$r['name']}</p>";
    }
    
    // Insert recipe ingredients for Choco Milk
    echo "<h3>Adding Choco Milk Ingredients (RCP-001):</h3>";
    $recipeId = $pdo->query("SELECT id FROM master_recipes WHERE recipe_code = 'RCP-001'")->fetch()['id'];
    
    $ingredients = [
        ['recipe_id' => $recipeId, 'name' => 'Raw Milk', 'category' => 'milk', 'qty' => 100, 'unit' => 'liters'],
        ['recipe_id' => $recipeId, 'name' => 'Cocoa Powder', 'category' => 'powder', 'qty' => 2.5, 'unit' => 'kg'],
        ['recipe_id' => $recipeId, 'name' => 'Sugar', 'category' => 'sugar', 'qty' => 8, 'unit' => 'kg'],
        ['recipe_id' => $recipeId, 'name' => 'Milk Powder', 'category' => 'powder', 'qty' => 1.5, 'unit' => 'kg'],
        ['recipe_id' => $recipeId, 'name' => 'Bottles 330ml', 'category' => 'packaging', 'qty' => 310, 'unit' => 'pcs'],
        ['recipe_id' => $recipeId, 'name' => 'Caps', 'category' => 'packaging', 'qty' => 310, 'unit' => 'pcs'],
    ];
    
    $insertIngredient = $pdo->prepare("
        INSERT INTO recipe_ingredients (recipe_id, ingredient_name, ingredient_category, quantity, unit)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    // Clear existing ingredients first
    $pdo->exec("DELETE FROM recipe_ingredients WHERE recipe_id = $recipeId");
    
    foreach ($ingredients as $ing) {
        $insertIngredient->execute([$ing['recipe_id'], $ing['name'], $ing['category'], $ing['qty'], $ing['unit']]);
        echo "<p style='color:green'>✓ Added: {$ing['qty']} {$ing['unit']} {$ing['name']}</p>";
    }
    
    // Add production_staff user if not exists
    echo "<h3>Adding Production Staff User:</h3>";
    $checkUser = $pdo->query("SELECT id FROM users WHERE username = 'production_staff'")->fetch();
    if (!$checkUser) {
        $hashedPassword = password_hash('password', PASSWORD_DEFAULT);
        $pdo->exec("
            INSERT INTO users (username, password, full_name, first_name, last_name, role, email, is_active)
            VALUES ('production_staff', '$hashedPassword', 'Juan Dela Cruz', 'Juan', 'Dela Cruz', 'production_staff', 'production@highlandfresh.com', 1)
        ");
        echo "<p style='color:green'>✓ Added production_staff user (password: password)</p>";
    } else {
        echo "<p style='color:blue'>ℹ production_staff user already exists</p>";
    }
    
    // Verify all tables
    echo "<h3>Verifying Tables:</h3>";
    $tables = ['master_recipes', 'recipe_ingredients', 'production_runs', 'production_ccp_logs', 
               'ingredient_requisitions', 'requisition_items', 'production_byproducts', 'ingredient_consumption'];
    
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) as c FROM $table")->fetch()['c'];
            echo "<p style='color:green'>✓ $table: $count records</p>";
        } catch (Exception $e) {
            echo "<p style='color:red'>✗ $table: Error - " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<p style='color:green; font-size:1.2em; margin-top:20px'>✅ All Production tables created successfully!</p>";
    echo "<p><a href='/HighlandFreshAppV4/html/production/dashboard.html'>Go to Production Dashboard →</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}
