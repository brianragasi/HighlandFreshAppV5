<?php
/**
 * Fix existing tables with missing columns
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=UTF-8');

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'highland_fresh';

echo "<h1>Highland Fresh - Fix Table Columns</h1>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fix production_batches - add status column if missing
    echo "<h3>Fixing production_batches table:</h3>";
    try {
        $pdo->exec("ALTER TABLE production_batches ADD COLUMN `status` ENUM('in_production', 'pending_qc', 'qc_passed', 'qc_failed', 'released', 'on_hold') DEFAULT 'in_production' AFTER `unit`");
        echo "<p style='color:green'>✓ Added status column</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p style='color:blue'>ℹ status column already exists</p>";
        } else {
            echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
        }
    }
    
    // Show production_batches structure
    $stmt = $pdo->query("DESCRIBE production_batches");
    echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Type</th></tr>";
    foreach ($stmt->fetchAll() as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td></tr>";
    }
    echo "</table>";
    
    // Fix finished_goods_inventory 
    echo "<h3>Fixing finished_goods_inventory table:</h3>";
    
    // Check current structure
    $stmt = $pdo->query("DESCRIBE finished_goods_inventory");
    $columns = [];
    foreach ($stmt->fetchAll() as $col) {
        $columns[] = $col['Field'];
    }
    echo "<p>Current columns: " . implode(', ', $columns) . "</p>";
    
    // Add missing columns
    $columnsToAdd = [
        'quantity_available' => "INT(11) NOT NULL DEFAULT 0",
        'quantity_sold' => "INT(11) DEFAULT 0",
        'quantity_damaged' => "INT(11) DEFAULT 0",
        'quantity_expired' => "INT(11) DEFAULT 0",
        'status' => "ENUM('available', 'low_stock', 'out_of_stock', 'expired', 'recalled') DEFAULT 'available'"
    ];
    
    foreach ($columnsToAdd as $col => $definition) {
        if (!in_array($col, $columns)) {
            try {
                $pdo->exec("ALTER TABLE finished_goods_inventory ADD COLUMN `$col` $definition");
                echo "<p style='color:green'>✓ Added $col column</p>";
            } catch (Exception $e) {
                echo "<p style='color:red'>Error adding $col: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color:blue'>ℹ $col column already exists</p>";
        }
    }
    
    // Update quantity_available if quantity column exists
    if (in_array('quantity', $columns)) {
        $pdo->exec("UPDATE finished_goods_inventory SET quantity_available = quantity WHERE quantity_available = 0");
        echo "<p style='color:green'>✓ Copied quantity to quantity_available</p>";
    }
    
    // Show updated structure
    echo "<h4>Updated finished_goods_inventory structure:</h4>";
    $stmt = $pdo->query("DESCRIBE finished_goods_inventory");
    echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Type</th></tr>";
    foreach ($stmt->fetchAll() as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td></tr>";
    }
    echo "</table>";
    
    echo "<p style='color:green; font-size:1.2em; margin-top:20px'>✅ Tables fixed!</p>";
    echo "<p><a href='/HighlandFreshAppV4/html/qc/dashboard.html'>Go to QC Dashboard →</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}
