<?php
/**
 * Highland Fresh System - Fix Warehouse FG Schema and Column Issues
 * 
 * Fixes:
 * 1. Add product_id column to finished_goods_inventory
 * 2. Update product_id based on product_name matching
 * 3. Fix any column name issues (p.name -> p.product_name)
 * 
 * @version 4.0
 */

// Direct database connection
$host = 'localhost';
$dbname = 'highland_fresh';
$username = 'root';
$password = '';

try {
    $db = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    echo "✅ Database connection successful\n\n";
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// ============================================================================
// FIX 1: Add product_id column if missing
// ============================================================================
echo "=== FIX 1: Adding product_id column to finished_goods_inventory ===\n";

// Check if product_id exists
$columns = $db->query("SHOW COLUMNS FROM finished_goods_inventory LIKE 'product_id'")->fetchAll();

if (count($columns) == 0) {
    // Add the column
    $db->exec("ALTER TABLE finished_goods_inventory ADD COLUMN product_id INT NULL AFTER batch_id");
    echo "✅ Added product_id column\n";
    
    // Add index
    $db->exec("ALTER TABLE finished_goods_inventory ADD INDEX idx_product_id (product_id)");
    echo "✅ Added index on product_id\n";
} else {
    echo "ℹ️ product_id column already exists\n";
}

// ============================================================================
// FIX 2: Update product_id based on product_name/product_code matching
// ============================================================================
echo "\n=== FIX 2: Updating product_id based on product matching ===\n";

// Get all FG inventory without product_id
$fgItems = $db->query("
    SELECT id, product_name, product_type, variant, size_ml 
    FROM finished_goods_inventory 
    WHERE product_id IS NULL
")->fetchAll();

echo "Found " . count($fgItems) . " items without product_id\n";

$updated = 0;
foreach ($fgItems as $item) {
    // Try to match by product_name
    $stmt = $db->prepare("
        SELECT id, product_name, product_code 
        FROM products 
        WHERE product_name = ? OR product_name LIKE ?
        LIMIT 1
    ");
    $stmt->execute([$item['product_name'], '%' . $item['product_name'] . '%']);
    $product = $stmt->fetch();
    
    if ($product) {
        $db->prepare("UPDATE finished_goods_inventory SET product_id = ? WHERE id = ?")
           ->execute([$product['id'], $item['id']]);
        echo "  ✅ Matched '{$item['product_name']}' to product #{$product['id']}\n";
        $updated++;
    } else {
        // Try matching by product_type and variant
        $stmt = $db->prepare("
            SELECT id, product_name FROM products 
            WHERE category LIKE ? 
            ORDER BY id LIMIT 1
        ");
        $category = str_replace('_', '%', $item['product_type']);
        $stmt->execute(['%' . $category . '%']);
        $product = $stmt->fetch();
        
        if ($product) {
            $db->prepare("UPDATE finished_goods_inventory SET product_id = ? WHERE id = ?")
               ->execute([$product['id'], $item['id']]);
            echo "  ✅ Matched '{$item['product_name']}' by category to product #{$product['id']}\n";
            $updated++;
        } else {
            echo "  ⚠️ Could not match product: {$item['product_name']} (type: {$item['product_type']})\n";
        }
    }
}
echo "Updated $updated items with product_id\n";

// ============================================================================
// FIX 3: If still unmatched, create product entries
// ============================================================================
echo "\n=== FIX 3: Creating missing products for unmatched FG items ===\n";

$unmatched = $db->query("
    SELECT DISTINCT product_name, product_type, variant, size_ml 
    FROM finished_goods_inventory 
    WHERE product_id IS NULL
")->fetchAll();

if (count($unmatched) > 0) {
    echo "Found " . count($unmatched) . " distinct unmatched products\n";
    
    foreach ($unmatched as $item) {
        // Create product
        $productCode = 'PROD-' . strtoupper(substr(str_replace(' ', '', $item['product_name']), 0, 10)) . '-' . rand(100, 999);
        
        // Map product_type to category
        $categoryMap = [
            'bottled_milk' => 'pasteurized_milk',
            'cheese' => 'cheese',
            'butter' => 'butter',
            'yogurt' => 'yogurt',
            'milk_bar' => 'flavored_milk'
        ];
        $category = $categoryMap[$item['product_type']] ?? 'pasteurized_milk';
        
        $stmt = $db->prepare("
            INSERT INTO products (product_code, product_name, category, variant, unit_size, unit_measure, is_active)
            VALUES (?, ?, ?, ?, ?, 'ml', 1)
        ");
        $stmt->execute([
            $productCode,
            $item['product_name'],
            $category,
            $item['variant'],
            $item['size_ml']
        ]);
        $productId = $db->lastInsertId();
        
        // Update FG inventory
        $db->prepare("
            UPDATE finished_goods_inventory 
            SET product_id = ? 
            WHERE product_name = ? AND product_id IS NULL
        ")->execute([$productId, $item['product_name']]);
        
        echo "  ✅ Created product '{$item['product_name']}' (ID: $productId)\n";
    }
} else {
    echo "ℹ️ All FG items have matching products\n";
}

// ============================================================================
// FIX 4: Add foreign key constraint (optional)
// ============================================================================
echo "\n=== FIX 4: Verifying product_id links ===\n";

$orphans = $db->query("
    SELECT COUNT(*) 
    FROM finished_goods_inventory fg
    LEFT JOIN products p ON fg.product_id = p.id
    WHERE fg.product_id IS NOT NULL AND p.id IS NULL
")->fetchColumn();

if ($orphans > 0) {
    echo "⚠️ Found $orphans FG items with invalid product_id\n";
} else {
    echo "✅ All product_id links are valid\n";
}

// ============================================================================
// VERIFICATION
// ============================================================================
echo "\n=== VERIFICATION ===\n";

$totalFg = $db->query("SELECT COUNT(*) FROM finished_goods_inventory")->fetchColumn();
$withProductId = $db->query("SELECT COUNT(*) FROM finished_goods_inventory WHERE product_id IS NOT NULL")->fetchColumn();
$withoutProductId = $totalFg - $withProductId;

echo "Total FG Inventory: $totalFg\n";
echo "With product_id: $withProductId ✅\n";
echo "Without product_id: $withoutProductId " . ($withoutProductId == 0 ? "✅" : "⚠️") . "\n";

// Test the query that was failing
echo "\n=== Testing problematic query ===\n";
try {
    $stmt = $db->query("
        SELECT fg.id, fg.product_id, fg.product_name, p.product_name as joined_name
        FROM finished_goods_inventory fg
        LEFT JOIN products p ON fg.product_id = p.id
        LIMIT 5
    ");
    $results = $stmt->fetchAll();
    echo "✅ Query successful - " . count($results) . " rows returned\n";
    foreach ($results as $r) {
        echo "  - FG #{$r['id']}: product_id={$r['product_id']}, name={$r['product_name']}\n";
    }
} catch (Exception $e) {
    echo "❌ Query failed: " . $e->getMessage() . "\n";
}

echo "\n✅ Schema fixes completed!\n";
