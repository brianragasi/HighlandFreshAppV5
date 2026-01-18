<?php
/**
 * Highland Fresh System - Warehouse FG Data Integrity Fix
 * 
 * Fixes issues found by the integration test:
 * 1. Creates chillers if none exist
 * 2. Configures product multi-unit settings
 * 3. Assigns inventory to chillers
 * 4. Creates missing tables (temperature_logs, product_returns, sales_orders)
 * 
 * @version 4.0
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== Warehouse FG Data Integrity Fix ===\n\n";

try {
    $db = Database::getInstance()->getConnection();
    echo "✓ Database connected\n\n";
} catch (Exception $e) {
    die("✗ Database connection failed: " . $e->getMessage() . "\n");
}

// ================================================
// FIX 1: Create Chillers
// ================================================
echo "--- Fix 1: Creating Chillers ---\n";

$chillerCheck = $db->query("SELECT COUNT(*) as count FROM chiller_locations WHERE is_active = 1");
$chillerCount = $chillerCheck->fetch()['count'];

if ($chillerCount == 0) {
    $chillers = [
        ['CHILLER-01', 'Main Chiller A', 500, 4.0, 2.0, 8.0, 'Cold Storage Room A'],
        ['CHILLER-02', 'Main Chiller B', 500, 4.5, 2.0, 8.0, 'Cold Storage Room A'],
        ['CHILLER-03', 'Dispatch Chiller', 200, 3.5, 2.0, 8.0, 'Loading Area'],
        ['CHILLER-04', 'Reserve Chiller', 300, 4.0, 2.0, 8.0, 'Cold Storage Room B']
    ];
    
    $stmt = $db->prepare("
        INSERT INTO chiller_locations 
        (chiller_code, chiller_name, capacity, temperature_celsius, min_temperature, max_temperature, location, status, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'available', 1)
    ");
    
    foreach ($chillers as $chiller) {
        $stmt->execute($chiller);
        echo "  ✓ Created chiller: {$chiller[0]} - {$chiller[1]}\n";
    }
    echo "  Total chillers created: " . count($chillers) . "\n";
} else {
    echo "  Chillers already exist: $chillerCount\n";
}

// ================================================
// FIX 2: Configure Product Multi-Unit Settings
// ================================================
echo "\n--- Fix 2: Configuring Product Multi-Unit Settings ---\n";

// Define conversion ratios per product category
$productConfigs = [
    'pasteurized_milk' => ['base_unit' => 'bottle', 'box_unit' => 'crate', 'pieces_per_box' => 24],
    'flavored_milk' => ['base_unit' => 'bottle', 'box_unit' => 'case', 'pieces_per_box' => 24],
    'yogurt' => ['base_unit' => 'cup', 'box_unit' => 'tray', 'pieces_per_box' => 12],
    'cheese' => ['base_unit' => 'pack', 'box_unit' => 'case', 'pieces_per_box' => 20],
    'butter' => ['base_unit' => 'pack', 'box_unit' => 'case', 'pieces_per_box' => 20],
    'cream' => ['base_unit' => 'bottle', 'box_unit' => 'case', 'pieces_per_box' => 12],
];

foreach ($productConfigs as $category => $config) {
    $updateStmt = $db->prepare("
        UPDATE products 
        SET base_unit = ?, box_unit = ?, pieces_per_box = ?
        WHERE category = ? AND (pieces_per_box IS NULL OR pieces_per_box <= 1)
    ");
    $updateStmt->execute([
        $config['base_unit'],
        $config['box_unit'],
        $config['pieces_per_box'],
        $category
    ]);
    $affected = $updateStmt->rowCount();
    if ($affected > 0) {
        echo "  ✓ Updated $affected products in '$category': {$config['pieces_per_box']} {$config['base_unit']}s per {$config['box_unit']}\n";
    }
}

// ================================================
// FIX 3: Assign Inventory to Chillers
// ================================================
echo "\n--- Fix 3: Assigning Inventory to Chillers ---\n";

// Get first available chiller
$availableChiller = $db->query("
    SELECT id, chiller_code FROM chiller_locations 
    WHERE is_active = 1 AND status IN ('available', 'full')
    ORDER BY current_count ASC LIMIT 1
")->fetch();

if ($availableChiller) {
    $updateInvStmt = $db->prepare("
        UPDATE finished_goods_inventory 
        SET chiller_id = ?, chiller_location = ?
        WHERE chiller_id IS NULL AND status = 'available'
    ");
    $updateInvStmt->execute([$availableChiller['id'], $availableChiller['chiller_code']]);
    $affected = $updateInvStmt->rowCount();
    echo "  ✓ Assigned $affected inventory items to chiller {$availableChiller['chiller_code']}\n";
    
    // Update chiller count
    $db->exec("
        UPDATE chiller_locations c
        SET current_count = (
            SELECT COALESCE(SUM(quantity_available), 0)
            FROM finished_goods_inventory
            WHERE chiller_id = c.id AND status = 'available'
        )
        WHERE is_active = 1
    ");
    echo "  ✓ Updated chiller inventory counts\n";
} else {
    echo "  ⚠ No available chiller found\n";
}

// ================================================
// FIX 4: Create Temperature Logging Table
// ================================================
echo "\n--- Fix 4: Creating Temperature Logging Table ---\n";

$tempLogExists = $db->query("SHOW TABLES LIKE 'chiller_temperature_logs'")->fetch();

if (!$tempLogExists) {
    $db->exec("
        CREATE TABLE `chiller_temperature_logs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `chiller_id` INT(11) NOT NULL,
            `temperature_celsius` DECIMAL(4,1) NOT NULL,
            `is_alert` TINYINT(1) DEFAULT 0 COMMENT 'True if temp out of range',
            `logged_by` INT(11) NOT NULL,
            `logged_at` DATETIME NOT NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_chiller` (`chiller_id`),
            INDEX `idx_logged_at` (`logged_at`),
            FOREIGN KEY (`chiller_id`) REFERENCES `chiller_locations`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`logged_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ Created chiller_temperature_logs table\n";
} else {
    echo "  Table already exists\n";
}

// ================================================
// FIX 5: Create Product Returns Table
// ================================================
echo "\n--- Fix 5: Creating Product Returns Table ---\n";

$returnsExists = $db->query("SHOW TABLES LIKE 'product_returns'")->fetch();

if (!$returnsExists) {
    $db->exec("
        CREATE TABLE `product_returns` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `return_code` VARCHAR(30) NOT NULL UNIQUE COMMENT 'e.g., RET-20260118-001',
            `dr_id` INT(11) NULL COMMENT 'Original delivery receipt',
            `dr_number` VARCHAR(30) NULL,
            `customer_id` INT(11) NULL,
            `customer_name` VARCHAR(200) NOT NULL,
            `return_date` DATE NOT NULL,
            `return_reason` ENUM('damaged_transit', 'expired', 'customer_rejection', 'quality_issue', 'overage', 'other') NOT NULL,
            `disposition` ENUM('return_to_inventory', 'hold_for_qc', 'dispose', 'rework') NULL,
            `total_items` INT(11) NOT NULL DEFAULT 0,
            `total_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `status` ENUM('pending', 'inspected', 'processed', 'closed') NOT NULL DEFAULT 'pending',
            `received_by` INT(11) NOT NULL,
            `processed_by` INT(11) NULL,
            `processed_at` DATETIME NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_return_code` (`return_code`),
            INDEX `idx_dr` (`dr_id`),
            INDEX `idx_customer` (`customer_id`),
            INDEX `idx_return_date` (`return_date`),
            INDEX `idx_status` (`status`),
            FOREIGN KEY (`dr_id`) REFERENCES `delivery_receipts`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ Created product_returns table\n";
    
    // Create return items table
    $db->exec("
        CREATE TABLE `product_return_items` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `return_id` INT(11) NOT NULL,
            `inventory_id` INT(11) NULL,
            `product_name` VARCHAR(150) NOT NULL,
            `batch_code` VARCHAR(50) NULL,
            `quantity` INT(11) NOT NULL,
            `quantity_boxes` INT(11) NOT NULL DEFAULT 0,
            `quantity_pieces` INT(11) NOT NULL DEFAULT 0,
            `unit_value` DECIMAL(10,2) NOT NULL,
            `line_total` DECIMAL(12,2) NOT NULL,
            `condition_status` ENUM('good', 'damaged', 'expired', 'questionable') NOT NULL,
            `disposition` ENUM('return_to_inventory', 'hold_for_qc', 'dispose', 'rework') NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_return` (`return_id`),
            INDEX `idx_inventory` (`inventory_id`),
            FOREIGN KEY (`return_id`) REFERENCES `product_returns`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`inventory_id`) REFERENCES `finished_goods_inventory`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ Created product_return_items table\n";
} else {
    echo "  Table already exists\n";
}

// ================================================
// FIX 6: Create Sales Orders Tables
// ================================================
echo "\n--- Fix 6: Creating Sales Orders Tables ---\n";

$salesOrdersExists = $db->query("SHOW TABLES LIKE 'sales_orders'")->fetch();

if (!$salesOrdersExists) {
    $db->exec("
        CREATE TABLE `sales_orders` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `order_number` VARCHAR(30) NOT NULL UNIQUE COMMENT 'e.g., SO-20260118-001',
            `customer_id` INT(11) NULL,
            `customer_name` VARCHAR(150) NOT NULL,
            `customer_type` ENUM('supermarket', 'school', 'feeding_program', 'restaurant', 'distributor', 'walk_in', 'other') NOT NULL DEFAULT 'other',
            `customer_po_number` VARCHAR(50) NULL COMMENT 'Customer PO reference',
            `contact_person` VARCHAR(100) NULL,
            `contact_number` VARCHAR(20) NULL,
            `delivery_address` TEXT NULL,
            `delivery_date` DATE NULL,
            `total_items` INT(11) NOT NULL DEFAULT 0,
            `total_quantity` INT(11) NOT NULL DEFAULT 0,
            `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `status` ENUM('draft', 'pending', 'approved', 'preparing', 'partially_fulfilled', 'fulfilled', 'cancelled') NOT NULL DEFAULT 'pending',
            `priority` ENUM('normal', 'rush', 'urgent') NOT NULL DEFAULT 'normal',
            `created_by` INT(11) NOT NULL,
            `approved_by` INT(11) NULL,
            `approved_at` DATETIME NULL,
            `assigned_to` INT(11) NULL,
            `dr_id` INT(11) NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_order_number` (`order_number`),
            INDEX `idx_customer` (`customer_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_delivery_date` (`delivery_date`),
            FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`dr_id`) REFERENCES `delivery_receipts`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ Created sales_orders table\n";
    
    $db->exec("
        CREATE TABLE `sales_order_items` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `order_id` INT(11) NOT NULL,
            `product_id` INT(11) NOT NULL,
            `product_name` VARCHAR(150) NOT NULL,
            `variant` VARCHAR(100) NULL,
            `size_value` DECIMAL(10,2) NOT NULL,
            `size_unit` VARCHAR(10) NOT NULL,
            `quantity_ordered` INT(11) NOT NULL,
            `quantity_fulfilled` INT(11) NOT NULL DEFAULT 0,
            `unit_price` DECIMAL(10,2) NOT NULL,
            `line_total` DECIMAL(12,2) NOT NULL,
            `status` ENUM('pending', 'partial', 'fulfilled', 'out_of_stock', 'cancelled') NOT NULL DEFAULT 'pending',
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_order` (`order_id`),
            INDEX `idx_product` (`product_id`),
            INDEX `idx_status` (`status`),
            FOREIGN KEY (`order_id`) REFERENCES `sales_orders`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ Created sales_order_items table\n";
} else {
    echo "  Tables already exist\n";
}

// ================================================
// FIX 7: Fix DR Items Count Mismatch
// ================================================
echo "\n--- Fix 7: Fixing DR Items Count ---\n";

$db->exec("
    UPDATE delivery_receipts dr
    SET total_items = (
        SELECT COUNT(*) FROM delivery_receipt_items WHERE dr_id = dr.id
    )
");
echo "  ✓ Updated DR total_items counts\n";

// ================================================
// Summary
// ================================================
echo "\n=== Fix Summary ===\n";

$finalChillers = $db->query("SELECT COUNT(*) as count FROM chiller_locations WHERE is_active = 1")->fetch()['count'];
$configuredProducts = $db->query("SELECT COUNT(*) FROM products WHERE pieces_per_box > 1")->fetch()[0];
$assignedInventory = $db->query("SELECT COUNT(*) FROM finished_goods_inventory WHERE chiller_id IS NOT NULL")->fetch()[0];

echo "Chillers: $finalChillers active\n";
echo "Products with multi-unit: $configuredProducts configured\n";
echo "Inventory with chiller: $assignedInventory items assigned\n";

echo "\n✓ All fixes applied successfully!\n";
echo "Please re-run the integration test to verify.\n";
