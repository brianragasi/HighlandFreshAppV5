<?php
require_once __DIR__ . '/bootstrap.php';

$db = Database::getInstance()->getConnection();

echo "<h2>Updating production_ccp_logs for Process Flow...</h2>";

try {
    // 1. Update check_type enum to include all process stages
    $db->exec("ALTER TABLE production_ccp_logs MODIFY COLUMN check_type ENUM('chilling','preheating','homogenization','pasteurization','cooling','storage','intermediate') NOT NULL");
    echo "<p style='color:green'>✓ Updated check_type enum with all process stages</p>";
    
    // 2. Add pressure_psi column for homogenization
    $stmt = $db->query("SHOW COLUMNS FROM production_ccp_logs LIKE 'pressure_psi'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE production_ccp_logs ADD COLUMN pressure_psi INT NULL AFTER temperature");
        echo "<p style='color:green'>✓ Added pressure_psi column</p>";
    } else {
        echo "<p style='color:orange'>⚠ pressure_psi column already exists</p>";
    }
    
    // 3. Add hold_time_secs for HTST (keep hold_time_mins for backwards compatibility)
    $stmt = $db->query("SHOW COLUMNS FROM production_ccp_logs LIKE 'hold_time_secs'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE production_ccp_logs ADD COLUMN hold_time_secs INT NULL DEFAULT 0 AFTER hold_time_mins");
        echo "<p style='color:green'>✓ Added hold_time_secs column</p>";
    } else {
        echo "<p style='color:orange'>⚠ hold_time_secs column already exists</p>";
    }
    
    // 4. Make temperature nullable (for homogenization which uses pressure)
    $db->exec("ALTER TABLE production_ccp_logs MODIFY COLUMN temperature DECIMAL(5,2) NULL");
    echo "<p style='color:green'>✓ Made temperature column nullable</p>";
    
    echo "<h2>Updated Table Structure:</h2>";
    $stmt = $db->query("DESCRIBE production_ccp_logs");
    echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Type</th><th>Null</th></tr>";
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td></tr>";
    }
    echo "</table>";
    
    echo "<h2 style='color:green'>✓ Database migration complete!</h2>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
