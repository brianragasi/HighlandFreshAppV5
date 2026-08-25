<?php

$root = dirname(__DIR__);
$source = file_get_contents($root . '/api/purchasing/purchase_orders.php');
$supplierSource = file_get_contents($root . '/api/purchasing/suppliers.php');
$forecastSource = file_get_contents($root . '/api/helpers/early_reorder.php');
$pageSource = file_get_contents($root . '/html/purchasing/purchase_orders.html');
if ($source === false || $supplierSource === false || $forecastSource === false || $pageSource === false) {
    fwrite(STDERR, "Unable to load Purchase Order API source.\n");
    exit(1);
}

function extractFunctionBlock(string $source, string $startName, string $nextName): string {
    $start = strpos($source, "function {$startName}");
    $end = strpos($source, "function {$nextName}", $start === false ? 0 : $start);
    if ($start === false || $end === false || $end <= $start) {
        throw new RuntimeException("Unable to isolate {$startName}");
    }
    return substr($source, $start, $end - $start);
}

define('HIGHLAND_FRESH', true);
require_once $root . '/api/config/config.php';
require_once $root . '/api/config/database.php';
require_once $root . '/api/helpers/numeric_validation.php';

eval(extractFunctionBlock($source, 'loadAndValidateSupplierForecastItems', 'findExactActiveSupplierFirstPO'));
eval(extractFunctionBlock($source, 'buildPurchaseOrderLineSignature', 'findExactActiveDuplicatePO'));
eval(extractFunctionBlock($source, 'insertPOItemFromSplit', 'insertForecastPOItem'));
eval(extractFunctionBlock($source, 'insertForecastPOItem', 'insertPRItemPOAllocation'));

$db = Database::getInstance()->getConnection();
$temporaryTables = ['purchase_order_items', 'supplier_ingredients', 'ingredients', 'suppliers'];

try {
    $db->exec("CREATE TEMPORARY TABLE suppliers (id INT PRIMARY KEY, is_active TINYINT NOT NULL)");
    $db->exec("CREATE TEMPORARY TABLE ingredients (id INT PRIMARY KEY, ingredient_name VARCHAR(100), unit_of_measure VARCHAR(30), is_active TINYINT NOT NULL)");
    $db->exec("
        CREATE TEMPORARY TABLE supplier_ingredients (
            supplier_id INT, ingredient_id INT, purchase_format VARCHAR(30), package_type VARCHAR(30),
            package_quantity_in_stock_unit DECIMAL(12,6), quoted_price DECIMAL(12,6),
            reference_unit_price DECIMAL(12,6), enforce_whole_packages TINYINT, is_active TINYINT
        )
    ");
    $db->exec("
        CREATE TEMPORARY TABLE purchase_order_items (
            id INT AUTO_INCREMENT PRIMARY KEY, po_id INT, purchase_request_item_id INT NULL,
            stock_validation_item_id INT NULL,
            procurement_source VARCHAR(30), forecast_reason TEXT, ingredient_id INT, mro_item_id INT NULL,
            item_description VARCHAR(100), quantity DECIMAL(12,3), unit VARCHAR(30),
            supplier_order_quantity DECIMAL(12,3), supplier_order_unit VARCHAR(30),
            supplier_order_unit_price DECIMAL(12,6), stock_quantity_per_supplier_unit DECIMAL(12,6),
            unit_price DECIMAL(12,6), total_amount DECIMAL(12,2), is_vat_item TINYINT, notes TEXT
        )
    ");
    $db->exec("INSERT INTO suppliers VALUES (7, 1)");
    $db->exec("INSERT INTO ingredients VALUES (11, 'Chocolate Powder X', 'kg', 1)");
    $db->exec("INSERT INTO supplier_ingredients VALUES (7, 11, 'packaged', 'pail', 10, 1000, 100, 1, 1)");

    $items = loadAndValidateSupplierForecastItems($db, [
        'forecast_items' => [[
            'ingredient_id' => 11,
            'supplier_order_quantity' => 4,
            'forecast_reason' => 'Fast usage trend needs a two-week safety buffer',
        ]],
    ], 7);
    if (count($items) !== 1 || abs((float) $items[0]['quantity'] - 40.0) > 0.0001) {
        throw new RuntimeException('Forecast package quantity was not converted to the stock unit');
    }
    if (($items[0]['procurement_source'] ?? '') !== 'purchaser_forecast') {
        throw new RuntimeException('Forecast source was not preserved');
    }

    insertForecastPOItem($db, 99, $items[0]);
    $saved = $db->query("SELECT * FROM purchase_order_items LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$saved || $saved['purchase_request_item_id'] !== null || $saved['procurement_source'] !== 'purchaser_forecast') {
        throw new RuntimeException('Forecast PO line was not stored separately from Warehouse demand');
    }
    if (abs((float) $saved['total_amount'] - 4000.0) > 0.001) {
        throw new RuntimeException('Forecast supplier package total is incorrect');
    }

    insertPOItemFromSplit($db, 100, [
        'pr_item_id' => 55,
        'quantity' => 38,
        'supplier_order_quantity' => 38,
        'supplier_order_unit' => 'bag',
        'supplier_order_unit_price' => 320,
        'stock_quantity_per_supplier_unit' => 1,
        'unit_price' => 320,
    ], [
        'legacy_purchase_request_item_id' => 154,
        'ingredient_id' => 11,
        'mro_item_id' => null,
        'item_description' => 'Chocolate Powder X',
        'unit' => 'kg',
        'notes' => null,
    ]);
    $confirmed = $db->query("SELECT * FROM purchase_order_items WHERE procurement_source = 'stock_validation' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$confirmed
        || (int) $confirmed['purchase_request_item_id'] !== 154
        || (int) $confirmed['stock_validation_item_id'] !== 55
        || abs((float) $confirmed['total_amount'] - 12160.0) > 0.001) {
        throw new RuntimeException('Confirmed Warehouse PO line was not stored with its source links and correct total');
    }

    $signature = buildPurchaseOrderLineSignature($items);
    if (!str_contains($signature, 'forecast:11')) {
        throw new RuntimeException('Forecast line is missing from duplicate-PO signature');
    }
    if (!str_contains($supplierSource, 'ingredientEarlyReorderEvidence')
        || !str_contains($forecastSource, "transaction_type = 'production_issue'")
        || !str_contains($forecastSource, 'active_po_balance')
        || !str_contains($forecastSource, 'projected_stock_at_delivery')) {
        throw new RuntimeException('Recorded usage, active PO balance, and projected-delivery evidence are not attached to supplier items');
    }
    if (!str_contains($pageSource, 'function applyForecastEvidence')
        || !str_contains($pageSource, 'System early-reorder evidence:')
        || !str_contains($pageSource, 'projected at delivery')) {
        throw new RuntimeException('Purchaser cannot see or apply the evidence-based early-reorder calculation');
    }
    if (!str_contains($pageSource, 'id="extraItemDialog"')
        || !str_contains($pageSource, 'What item do you need?')
        || !str_contains($pageSource, 'How many should be purchased?')
        || !str_contains($pageSource, 'Why add it before low stock?')
        || !str_contains($pageSource, 'Added early by Purchasing')) {
        throw new RuntimeException('Extra-item entry is not presented as a clear guided form');
    }
} catch (Throwable $error) {
    foreach ($temporaryTables as $table) {
        try { $db->exec("DROP TEMPORARY TABLE IF EXISTS {$table}"); } catch (Throwable $ignored) {}
    }
    fwrite(STDERR, 'Supplier forecast PO test failed: ' . $error->getMessage() . "\n");
    exit(1);
}

foreach ($temporaryTables as $table) {
    $db->exec("DROP TEMPORARY TABLE IF EXISTS {$table}");
}

echo "Supplier forecast PO tests passed.\n";
