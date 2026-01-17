<?php
/**
 * Create missing tables for batch release functionality
 */
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain');

try {
    $db = Database::getInstance()->getConnection();
    
    echo "=== CREATING MISSING TABLES ===\n\n";
    
    // Create products table
    echo "Creating products table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT(11) NOT NULL AUTO_INCREMENT,
            product_code VARCHAR(50) NOT NULL UNIQUE,
            product_name VARCHAR(100) NOT NULL,
            category ENUM('pasteurized_milk', 'flavored_milk', 'yogurt', 'cheese', 'butter', 'cream') NOT NULL,
            variant VARCHAR(50) DEFAULT NULL,
            description TEXT,
            unit_size DECIMAL(10,2) DEFAULT NULL,
            unit_measure VARCHAR(20) DEFAULT 'ml',
            shelf_life_days INT(11) DEFAULT 7,
            storage_temp_min DECIMAL(4,2) DEFAULT 2.00,
            storage_temp_max DECIMAL(4,2) DEFAULT 6.00,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_category (category),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ products table created\n\n";
    
    // Create qc_batch_release table
    echo "Creating qc_batch_release table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS qc_batch_release (
            id INT(11) NOT NULL AUTO_INCREMENT,
            batch_id INT(11) NOT NULL,
            qc_officer_id INT(11) NOT NULL,
            verification_datetime DATETIME DEFAULT NULL,
            is_released TINYINT(1) DEFAULT 0,
            release_datetime DATETIME DEFAULT NULL,
            manufacturing_date DATE DEFAULT NULL,
            expiry_date DATE DEFAULT NULL,
            barcode VARCHAR(100) DEFAULT NULL,
            sensory_appearance VARCHAR(50) DEFAULT NULL,
            sensory_odor VARCHAR(50) DEFAULT NULL,
            sensory_taste VARCHAR(50) DEFAULT NULL,
            sensory_texture VARCHAR(50) DEFAULT NULL,
            sensory_notes TEXT,
            packaging_integrity VARCHAR(50) DEFAULT NULL,
            label_accuracy VARCHAR(50) DEFAULT NULL,
            seal_quality VARCHAR(50) DEFAULT NULL,
            ccp_compliance VARCHAR(50) DEFAULT NULL,
            release_decision ENUM('approved', 'rejected', 'hold') DEFAULT NULL,
            rejection_reason TEXT,
            corrective_action TEXT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_batch (batch_id),
            INDEX idx_qc_officer (qc_officer_id),
            INDEX idx_release_date (release_datetime),
            FOREIGN KEY (batch_id) REFERENCES production_batches(id) ON DELETE CASCADE,
            FOREIGN KEY (qc_officer_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ qc_batch_release table created\n\n";
    
    // Create batch_ccp_logs table
    echo "Creating batch_ccp_logs table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS batch_ccp_logs (
            id INT(11) NOT NULL AUTO_INCREMENT,
            batch_id INT(11) NOT NULL,
            ccp_type ENUM('pasteurization', 'cooling', 'storage', 'packaging') NOT NULL,
            start_time DATETIME DEFAULT NULL,
            end_time DATETIME DEFAULT NULL,
            temperature DECIMAL(5,2) DEFAULT NULL,
            duration_minutes INT(11) DEFAULT NULL,
            target_temp DECIMAL(5,2) DEFAULT NULL,
            target_duration INT(11) DEFAULT NULL,
            is_compliant TINYINT(1) DEFAULT NULL,
            recorded_by INT(11) DEFAULT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_batch (batch_id),
            INDEX idx_ccp_type (ccp_type),
            FOREIGN KEY (batch_id) REFERENCES production_batches(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ batch_ccp_logs table created\n\n";
    
    // Insert some sample products
    echo "Inserting sample products...\n";
    $db->exec("
        INSERT IGNORE INTO products (product_code, product_name, category, variant, unit_size, unit_measure, shelf_life_days) VALUES
        ('PM-1000', 'Highland Fresh Pasteurized Milk', 'pasteurized_milk', '1 Liter', 1000, 'ml', 7),
        ('PM-500', 'Highland Fresh Pasteurized Milk', 'pasteurized_milk', '500ml', 500, 'ml', 7),
        ('PM-250', 'Highland Fresh Pasteurized Milk', 'pasteurized_milk', '250ml', 250, 'ml', 7),
        ('FM-CHOC-500', 'Highland Fresh Chocolate Milk', 'flavored_milk', 'Chocolate 500ml', 500, 'ml', 5),
        ('FM-STRW-500', 'Highland Fresh Strawberry Milk', 'flavored_milk', 'Strawberry 500ml', 500, 'ml', 5),
        ('YG-PLAIN-150', 'Highland Fresh Plain Yogurt', 'yogurt', 'Plain 150g', 150, 'g', 14),
        ('YG-STRW-150', 'Highland Fresh Strawberry Yogurt', 'yogurt', 'Strawberry 150g', 150, 'g', 14),
        ('YG-MANGO-150', 'Highland Fresh Mango Yogurt', 'yogurt', 'Mango 150g', 150, 'g', 14)
    ");
    echo "✓ Sample products inserted\n\n";
    
    echo "=== VERIFICATION ===\n\n";
    
    $tables = ['products', 'qc_batch_release', 'batch_ccp_logs'];
    foreach ($tables as $table) {
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM $table");
        $result = $stmt->fetch();
        echo "$table: {$result['cnt']} rows\n";
    }
    
    echo "\n✅ All tables created successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
