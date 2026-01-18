<?php
/**
 * Highland Fresh - Fix Yogurt Transformation Implementation
 * 
 * This script:
 * 1. Updates the yogurt_transformations table schema
 * 2. Adds missing columns for production run linking
 * 3. Adds pressure column to CCP logs for homogenization
 * 
 * @package HighlandFresh
 * @version 4.0
 */

header('Content-Type: text/html; charset=UTF-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('HIGHLAND_FRESH', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html><html><head><title>Fix Transformation Schema</title>
<style>
    body { font-family: 'Segoe UI', sans-serif; padding: 30px; background: #f8f9fa; }
    .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #6f42c1; }
    .success { color: #28a745; }
    .error { color: #dc3545; }
    .info { color: #17a2b8; }
    pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
    th { background: #f8f9fa; }
</style></head><body><div class='container'>";

echo "<h1>🔧 Fix Yogurt Transformation Schema</h1>";

try {
    $db = Database::getInstance()->getConnection();
    
    // Step 1: Check current columns
    echo "<h2>Step 1: Current Table Structure</h2>";
    $cols = $db->query("DESCRIBE yogurt_transformations")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Current columns: <code>" . implode(', ', $cols) . "</code></p>";
    
    // Step 2: Add missing columns
    echo "<h2>Step 2: Add Missing Columns</h2>";
    
    $columnsToAdd = [
        'source_volume_liters' => "ALTER TABLE yogurt_transformations ADD COLUMN source_volume_liters DECIMAL(10,2) NULL AFTER source_quantity",
        'approved_by' => "ALTER TABLE yogurt_transformations ADD COLUMN approved_by INT(11) NULL AFTER status",
        'approval_datetime' => "ALTER TABLE yogurt_transformations ADD COLUMN approval_datetime DATETIME NULL AFTER approved_by",
        'safety_verified' => "ALTER TABLE yogurt_transformations ADD COLUMN safety_verified TINYINT(1) DEFAULT 0 AFTER approval_datetime",
        'production_run_id' => "ALTER TABLE yogurt_transformations ADD COLUMN production_run_id INT(11) NULL AFTER safety_verified",
        'target_recipe_id' => "ALTER TABLE yogurt_transformations ADD COLUMN target_recipe_id INT(11) NULL AFTER production_run_id"
    ];
    
    foreach ($columnsToAdd as $colName => $sql) {
        if (!in_array($colName, $cols)) {
            try {
                $db->exec($sql);
                echo "<p class='success'>✓ Added column: <code>{$colName}</code></p>";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                    echo "<p class='info'>→ Column <code>{$colName}</code> already exists</p>";
                } else {
                    echo "<p class='error'>✗ Error adding {$colName}: " . $e->getMessage() . "</p>";
                }
            }
        } else {
            echo "<p class='info'>→ Column <code>{$colName}</code> already exists</p>";
        }
    }
    
    // Step 3: Add index for production_run_id
    echo "<h2>Step 3: Add Indexes</h2>";
    try {
        $db->exec("ALTER TABLE yogurt_transformations ADD INDEX idx_production_run (production_run_id)");
        echo "<p class='success'>✓ Added index on production_run_id</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "<p class='info'>→ Index already exists</p>";
        } else {
            echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
        }
    }
    
    // Step 4: Add pressure column to CCP logs
    echo "<h2>Step 4: Add Pressure Column to CCP Logs</h2>";
    $ccpCols = $db->query("DESCRIBE production_ccp_logs")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('pressure', $ccpCols)) {
        try {
            $db->exec("ALTER TABLE production_ccp_logs ADD COLUMN pressure DECIMAL(6,2) NULL AFTER temperature COMMENT 'For homogenization: 1000-1500 psi'");
            echo "<p class='success'>✓ Added pressure column to production_ccp_logs</p>";
        } catch (PDOException $e) {
            echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p class='info'>→ Pressure column already exists</p>";
    }
    
    // Step 5: Verify final structure
    echo "<h2>Step 5: Final Table Structure</h2>";
    $finalCols = $db->query("DESCRIBE yogurt_transformations")->fetchAll();
    
    echo "<table><tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    foreach ($finalCols as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Default']}</td></tr>";
    }
    echo "</table>";
    
    echo "<h2 class='success'>✅ Schema Update Complete!</h2>";
    echo "<p>The yogurt_transformations table now supports:</p>";
    echo "<ul>";
    echo "<li>Volume tracking (source_volume_liters)</li>";
    echo "<li>Approval workflow (approved_by, approval_datetime)</li>";
    echo "<li>Safety verification (safety_verified)</li>";
    echo "<li><strong>Production run linking (production_run_id)</strong></li>";
    echo "<li>Target recipe reference (target_recipe_id)</li>";
    echo "</ul>";
    
    echo "<p>The CCP logs table now supports:</p>";
    echo "<ul>";
    echo "<li><strong>Homogenization pressure logging (1000-1500 psi)</strong></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div></body></html>";
?>
