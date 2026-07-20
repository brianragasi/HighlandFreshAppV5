<?php
require_once __DIR__ . '/api/bootstrap.php';
$db = Database::getInstance()->getConnection();

echo "=== master_recipes.product_id (FK to products) ===\n";
$stmt = $db->query("SELECT id, recipe_code, product_name, product_id, product_type FROM master_recipes WHERE is_active = 1 ORDER BY product_name");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  Recipe #{$r['id']} '{$r['product_name']}' ({$r['recipe_code']}) -> product_id=" . ($r['product_id'] ?: 'NULL') . "\n";
}

echo "\n=== All products ===\n";
$stmt = $db->query("SELECT id, product_name, category, is_active FROM products ORDER BY product_name");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  Product #{$r['id']} '{$r['product_name']}' (category={$r['category']}, active={$r['is_active']})\n";
}

echo "\n=== Match candidates (recipe product_name matches product product_name) ===\n";
$stmt = $db->query("
    SELECT mr.id AS recipe_id, mr.product_name AS recipe_name, mr.product_id AS current_product_id,
           p.id AS matched_product_id, p.product_name AS product_name
    FROM master_recipes mr
    JOIN products p ON LOWER(TRIM(mr.product_name)) = LOWER(TRIM(p.product_name))
    WHERE mr.is_active = 1 AND p.is_active = 1
    ORDER BY mr.product_name
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $status = ($r['current_product_id'] == $r['matched_product_id']) ? 'OK' : 'NEEDS FIX (current=' . ($r['current_product_id'] ?: 'NULL') . ')';
    echo "  Recipe '{$r['recipe_name']}' -> Product #{$r['matched_product_id']} '{$r['product_name']}' [$status]\n";
}
