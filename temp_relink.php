<?php
require_once __DIR__ . '/api/bootstrap.php';
$db = Database::getInstance()->getConnection();

echo "=== master_recipes schema (relevant cols) ===\n";
$cols = $db->query("SHOW COLUMNS FROM master_recipes")->fetchAll(PDO::FETCH_COLUMN);
$relevant = ['id','product_id','product_name','product_type','base_product_id','is_active'];
foreach ($relevant as $c) { echo "  $c: " . (in_array($c, $cols) ? 'EXISTS' : 'MISSING') . "\n"; }

echo "\n=== products schema (relevant cols) ===\n";
$cols = $db->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
$relevant2 = ['id','product_name','category','recipe_id','base_product_id','milk_type_id','is_active'];
foreach ($relevant2 as $c) { echo "  $c: " . (in_array($c, $cols) ? 'EXISTS' : 'MISSING') . "\n"; }

echo "\n=== base_products table ===\n";
try { $db->query("SELECT id FROM base_products LIMIT 0"); echo "EXISTS\n"; } catch (Throwable $e) { echo "MISSING\n"; }

echo "\n=== All active recipes ===\n";
$rows = $db->query("
    SELECT mr.id, mr.recipe_code, mr.product_name, mr.product_id, mr.product_type, mr.is_active
    FROM master_recipes mr WHERE mr.is_active = 1 ORDER BY mr.product_name
")->fetchAll(PDO::FETCH_ASSOC);
echo "  " . count($rows) . " recipes\n";
foreach ($rows as $r) {
    echo "  Recipe #{$r['id']} '{$r['product_name']}' ({$r['recipe_code']}) product_id=" . ($r['product_id'] ?: 'NULL') . " type={$r['product_type']}\n";
}

echo "\n=== All active products ===\n";
$rows = $db->query("
    SELECT p.id, p.product_name, p.category, p.milk_type_id, p.is_active
    FROM products p WHERE p.is_active = 1 ORDER BY p.product_name
")->fetchAll(PDO::FETCH_ASSOC);
echo "  " . count($rows) . " products\n";
foreach ($rows as $r) {
    echo "  Product #{$r['id']} '{$r['product_name']}' category=" . ($r['category'] ?: 'NULL') . " milk_type=" . ($r['milk_type_id'] ?: 'NULL') . "\n";
}

echo "\n=== Linkage status ===\n";
$rows = $db->query("
    SELECT mr.id AS recipe_id, mr.product_name AS recipe_name, mr.product_id,
           p.product_name AS linked_product, p.category
    FROM master_recipes mr
    LEFT JOIN products p ON mr.product_id = p.id
    WHERE mr.is_active = 1
    ORDER BY mr.product_name
")->fetchAll(PDO::FETCH_ASSOC);
$linked = 0; $unlinked = 0;
foreach ($rows as $r) {
    if ($r['product_id'] && $r['linked_product']) { $linked++; }
    else { $unlinked++; echo "  UNLINKED: Recipe #{$r['recipe_id']} '{$r['recipe_name']}' product_id=" . ($r['product_id'] ?: 'NULL') . "\n"; }
}
echo "  Linked: $linked, Unlinked: $unlinked\n";

echo "\n=== Products with NULL category ===\n";
$rows = $db->query("SELECT id, product_name, category FROM products WHERE is_active = 1 AND (category IS NULL OR category = '')")->fetchAll(PDO::FETCH_ASSOC);
echo "  " . count($rows) . " products with null/empty category\n";
foreach ($rows as $r) {
    echo "  - Product #{$r['id']} '{$r['product_name']}'\n";
}
