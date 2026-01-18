<?php
/**
 * Check Yogurt Transformation Implementation
 */
define('HIGHLAND_FRESH', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance()->getConnection();

echo "=== YOGURT TRANSFORMATION IMPLEMENTATION CHECK ===\n\n";

// 1. Check if table exists
echo "1. TABLE CHECK:\n";
$t = $db->query("SHOW TABLES LIKE 'yogurt_transformations'");
$tableExists = $t->rowCount() > 0;
echo "   yogurt_transformations table: " . ($tableExists ? "EXISTS" : "MISSING") . "\n\n";

if ($tableExists) {
    // 2. Show columns
    echo "2. TABLE STRUCTURE:\n";
    $cols = $db->query('DESCRIBE yogurt_transformations');
    foreach($cols as $c) {
        echo "   - {$c['Field']} ({$c['Type']})\n";
    }
    
    // 3. Check for any data
    echo "\n3. EXISTING DATA:\n";
    $count = $db->query("SELECT COUNT(*) as cnt FROM yogurt_transformations")->fetch()['cnt'];
    echo "   Records in table: {$count}\n";
    
    if ($count > 0) {
        echo "\n   Sample records:\n";
        $records = $db->query("SELECT * FROM yogurt_transformations LIMIT 5")->fetchAll();
        foreach ($records as $r) {
            echo "   - Code: {$r['transformation_code']}, Status: {$r['status']}\n";
        }
    }
}

// 4. Check API file
echo "\n4. API FILE CHECK:\n";
$apiFile = __DIR__ . '/qc/expiry_management.php';
echo "   expiry_management.php: " . (file_exists($apiFile) ? "EXISTS" : "MISSING") . "\n";

if (file_exists($apiFile)) {
    $content = file_get_contents($apiFile);
    
    // Check for key implementations
    $hasTransformInsert = strpos($content, "INSERT INTO yogurt_transformations") !== false;
    $hasInventoryDeduct = strpos($content, "quantity_available - ?") !== false;
    $hasGetExpiring = strpos($content, "expiry_date") !== false;
    $hasProductionLink = strpos($content, "production_run") !== false;
    
    echo "   - Has transformation INSERT: " . ($hasTransformInsert ? "YES" : "NO") . "\n";
    echo "   - Has FG inventory deduction: " . ($hasInventoryDeduct ? "YES" : "NO") . "\n";
    echo "   - Has expiry date check: " . ($hasGetExpiring ? "YES" : "NO") . "\n";
    echo "   - Links to production_runs: " . ($hasProductionLink ? "YES" : "NO") . "\n";
}

// 5. Check HTML UI
echo "\n5. UI FILE CHECK:\n";
$uiFile = dirname(__DIR__) . '/html/qc/expiry_management.html';
echo "   expiry_management.html: " . (file_exists($uiFile) ? "EXISTS" : "MISSING") . "\n";

// 6. Check production runs for yogurt type
echo "\n6. YOGURT PRODUCTION CAPABILITY:\n";
$yogurtRecipes = $db->query("SELECT COUNT(*) as cnt FROM master_recipes WHERE product_type = 'yogurt'")->fetch()['cnt'];
echo "   Yogurt recipes in master_recipes: {$yogurtRecipes}\n";

// 7. Check finished_goods_inventory for milk products (transformable)
echo "\n7. TRANSFORMABLE INVENTORY:\n";
try {
    $fgCheck = $db->query("
        SELECT COUNT(*) as cnt, SUM(quantity_available) as qty
        FROM finished_goods_inventory fgi
        WHERE fgi.status = 'available' 
        AND fgi.quantity_available > 0
        AND fgi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ");
    $fg = $fgCheck->fetch();
    echo "   Near-expiry FG items (≤7 days): {$fg['cnt']} items, " . ($fg['qty'] ?? 0) . " units\n";
} catch (Exception $e) {
    echo "   Error checking FG: " . $e->getMessage() . "\n";
}

// 8. Summary
echo "\n=== IMPLEMENTATION STATUS ===\n";
echo "✓ Table structure: " . ($tableExists ? "COMPLETE" : "MISSING") . "\n";
echo "✓ API endpoints: " . (file_exists($apiFile) ? "EXIST" : "MISSING") . "\n";
echo "✓ Transformation logic: " . ($hasTransformInsert ? "IMPLEMENTED" : "MISSING") . "\n";
echo "✓ FG deduction: " . ($hasInventoryDeduct ? "IMPLEMENTED" : "MISSING") . "\n";
echo "⚠ Production run link: " . ($hasProductionLink ? "IMPLEMENTED" : "NOT IMPLEMENTED") . "\n";
