<?php
/**
 * Check tables for batch_release.php
 */
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain');

try {
    $db = Database::getInstance()->getConnection();
    
    $tables = [
        'production_batches', 
        'products', 
        'qc_batch_release', 
        'batch_ccp_logs'
    ];
    
    echo "=== CHECKING TABLES FOR BATCH RELEASE ===\n\n";
    
    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as cnt FROM $table");
            $result = $stmt->fetch();
            echo "$table: EXISTS (rows: {$result['cnt']})\n";
        } catch (Exception $e) {
            echo "$table: MISSING! Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== CHECKING PRODUCTION_BATCHES QUERY ===\n";
    
    try {
        $stmt = $db->query("
            SELECT pb.*,
                   mr.product_name, mr.product_type as recipe_type, mr.variant as recipe_variant,
                   u.first_name as created_by_first, u.last_name as created_by_last
            FROM production_batches pb
            LEFT JOIN master_recipes mr ON pb.recipe_id = mr.id
            LEFT JOIN users u ON pb.created_by = u.id
            WHERE (pb.qc_status = 'pending' OR pb.qc_status = 'on_hold')
            LIMIT 5
        ");
        $batches = $stmt->fetchAll();
        echo "Query works! Found " . count($batches) . " pending batches.\n";
        foreach ($batches as $b) {
            echo "  - {$b['batch_code']} ({$b['qc_status']})\n";
        }
    } catch (Exception $e) {
        echo "Query failed: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
