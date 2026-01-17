<?php
/**
 * Quick test to check database tables
 */
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain');

try {
    $db = Database::getInstance()->getConnection();
    
    echo "=== DATABASE TABLES ===\n\n";
    
    $stmt = $db->query("SHOW TABLES");
    $tables = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    echo "Tables found: " . count($tables) . "\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    
    echo "\n\n=== CHECKING REQUIRED TABLES ===\n\n";
    
    $required = ['farmers', 'milk_deliveries', 'qc_milk_tests', 'production_runs', 'production_run_milk_usage'];
    foreach ($required as $table) {
        $exists = in_array($table, $tables);
        echo "$table: " . ($exists ? "✓ EXISTS" : "✗ MISSING") . "\n";
    }
    
    echo "\n\n=== TESTING AVAILABLE MILK QUERY ===\n\n";
    
    // Check if farmers table exists
    if (!in_array('farmers', $tables)) {
        echo "WARNING: farmers table doesn't exist!\n";
        echo "The available_milk query will fail because it JOINs with farmers table.\n";
    } else {
        // Try the query
        try {
            $stmt = $db->query("
                SELECT COUNT(*) as count
                FROM milk_deliveries md
                JOIN qc_milk_tests qmt ON qmt.delivery_id = md.id
                LEFT JOIN farmers f ON md.farmer_id = f.id
                WHERE md.status = 'accepted'
            ");
            $result = $stmt->fetch();
            echo "Query works! Found {$result['count']} accepted deliveries with QC tests.\n";
        } catch (Exception $e) {
            echo "Query failed: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n\n=== MILK DELIVERIES STATUS ===\n\n";
    
    $stmt = $db->query("SELECT status, COUNT(*) as cnt FROM milk_deliveries GROUP BY status");
    while ($row = $stmt->fetch()) {
        echo "  {$row['status']}: {$row['cnt']}\n";
    }
    
    echo "\n\n=== QC TESTS ===\n\n";
    
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM qc_milk_tests");
    $result = $stmt->fetch();
    echo "Total QC tests: {$result['cnt']}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
