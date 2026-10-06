<?php
require_once __DIR__ . '/../api/helpers/master_archive_guards.php';
require_once __DIR__ . '/../api/helpers/supplier_mro_catalog.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach ([
    'CREATE TABLE base_products (id INTEGER PRIMARY KEY, name TEXT, is_active INTEGER)',
    'CREATE TABLE products (id INTEGER PRIMARY KEY, base_product_id INTEGER, product_code TEXT, product_name TEXT, primary_container_id INTEGER, is_active INTEGER)',
    'CREATE TABLE sku_packaging_bom_items (product_id INTEGER, ingredient_id INTEGER, is_active INTEGER)',
    'CREATE TABLE master_recipes (id INTEGER PRIMARY KEY, base_product_id INTEGER, recipe_code TEXT, product_name TEXT, is_active INTEGER)',
    'CREATE TABLE recipe_ingredients (recipe_id INTEGER, ingredient_id INTEGER)',
    'CREATE TABLE purchase_orders (id INTEGER PRIMARY KEY, supplier_id INTEGER, po_number TEXT, status TEXT)',
    'CREATE TABLE purchase_order_items (po_id INTEGER, ingredient_id INTEGER)',
    'CREATE TABLE suppliers (id INTEGER PRIMARY KEY, is_active INTEGER)',
    'CREATE TABLE mro_items (id INTEGER PRIMARY KEY, item_name TEXT, is_active INTEGER)',
    'CREATE TABLE supplier_mro_items (supplier_id INTEGER, mro_item_id INTEGER, is_active INTEGER)',
] as $sql) {
    $db->exec($sql);
}
$db->exec("INSERT INTO base_products VALUES (1, 'Plain Yogurt', 1), (2, 'Archived Formula', 0)");
$db->exec("INSERT INTO products VALUES
    (1, 1, 'YOG-250', 'Plain Yogurt', NULL, 1),
    (2, 2, 'OLD-250', 'Archived Formula', NULL, 1),
    (3, 1, 'YOG-500', 'Plain Yogurt', 50, 1)");
$db->exec('INSERT INTO sku_packaging_bom_items VALUES (1, 10, 1), (2, 40, 1)');
$db->exec("INSERT INTO master_recipes VALUES
    (1, 1, 'RCP-YOG', 'Plain Yogurt', 1),
    (2, 1, 'RCP-OLD', 'Old Recipe', 0)");
$db->exec('INSERT INTO recipe_ingredients VALUES (1, 20), (2, 40)');
$db->exec("INSERT INTO purchase_orders VALUES
    (1, 20, 'PO-OPEN', 'ordered'),
    (2, 21, 'PO-CLOSED', 'closed')");
$db->exec('INSERT INTO purchase_order_items VALUES (1, 30), (2, 40)');
$db->exec('INSERT INTO suppliers VALUES (20, 1), (21, 1)');
$db->exec("INSERT INTO mro_items VALUES (1, 'Pasteurizer Gasket', 1), (2, 'Safety Goggles', 1)");
$db->exec('INSERT INTO supplier_mro_items VALUES (20, 1, 1), (20, 2, 1), (21, 2, 1)');

function checkArchiveGuard(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

checkArchiveGuard(str_contains(implode(' ', hfIngredientArchiveBlockers($db, 10)), 'YOG-250'),
    'Active SKU packaging must block an ingredient archive');
checkArchiveGuard(str_contains(implode(' ', hfIngredientArchiveBlockers($db, 50)), 'YOG-500'),
    'Primary package references must block an ingredient archive');
checkArchiveGuard(str_contains(implode(' ', hfIngredientArchiveBlockers($db, 20)), 'RCP-YOG'),
    'Active recipe BOM must block an ingredient archive');
checkArchiveGuard(str_contains(implode(' ', hfIngredientArchiveBlockers($db, 30)), 'PO-OPEN'),
    'Open purchase order must block an ingredient archive');
checkArchiveGuard(hfIngredientArchiveBlockers($db, 40) === [],
    'Archived formulas, retired recipes, and closed POs must not block an archive');
checkArchiveGuard(hfSupplierOpenPurchaseOrderCount($db, 20) === 1,
    'Open purchase orders must block supplier archiving');
checkArchiveGuard(hfSupplierOpenPurchaseOrderCount($db, 21) === 0,
    'Closed purchase orders must not block supplier archiving');
checkArchiveGuard(supplierMroCoverageGapsAfterChange($db, 20, [], false) === ['Pasteurizer Gasket'],
    'Archiving a sole MRO supplier must identify the uncovered item');
checkArchiveGuard(supplierMroCoverageGapsAfterChange($db, 20,
    [['mro_item_id' => 1], ['mro_item_id' => 2]], true) === [],
    'Keeping an active supplier and its MRO offers must preserve coverage');

echo "Master archive guards passed.\n";
