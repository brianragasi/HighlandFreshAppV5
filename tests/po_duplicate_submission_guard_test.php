<?php

$root = dirname(__DIR__);
$apiSource = file_get_contents($root . '/api/purchasing/purchase_orders.php');
$pageSource = file_get_contents($root . '/html/purchasing/purchase_orders.html');

if ($apiSource === false || $pageSource === false) {
    fwrite(STDERR, "Unable to read Purchase Order sources.\n");
    exit(1);
}

$sourceChecks = [
    'Browser submission lock' =>
        str_contains($pageSource, 'createPOInFlight')
        && str_contains($pageSource, 'createButton.disabled = true'),
    'Server exact-duplicate guard' =>
        str_contains($apiSource, 'findExactActiveDuplicatePO')
        && str_contains($apiSource, 'An identical active Purchase Order already exists'),
    'Allocation query uses a locking current read' =>
        str_contains($apiSource, 'SELECT prip.quantity')
        && str_contains($apiSource, "po.status NOT IN ('cancelled', 'rejected')")
        && str_contains($apiSource, 'FOR UPDATE'),
];

foreach ($sourceChecks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Failed: {$label}.\n");
        exit(1);
    }
}

// Load only the two pure duplicate-guard functions without executing the API
// route/authentication bootstrap around them.
$helperStart = strpos($apiSource, 'function buildPurchaseOrderLineSignature');
$helperEnd = strpos($apiSource, 'function validateAssignmentsAgainstSelectedCanvassQuotes', $helperStart ?: 0);
if ($helperStart === false || $helperEnd === false || $helperEnd <= $helperStart) {
    fwrite(STDERR, "Unable to isolate duplicate-guard helpers.\n");
    exit(1);
}
eval(substr($apiSource, $helperStart, $helperEnd - $helperStart));

define('HIGHLAND_FRESH', true);
require_once $root . '/api/config/config.php';
require_once $root . '/api/config/database.php';

$db = Database::getInstance()->getConnection();
$temporaryTables = ['purchase_order_items', 'purchase_orders'];

try {
    $db->exec("
        CREATE TEMPORARY TABLE purchase_orders (
            id INT PRIMARY KEY,
            po_number VARCHAR(30) NOT NULL,
            purchase_request_id INT NOT NULL,
            supplier_id INT NOT NULL,
            expected_delivery DATE NULL,
            payment_terms VARCHAR(30) NOT NULL,
            status VARCHAR(30) NOT NULL
        ) ENGINE=InnoDB
    ");
    $db->exec("
        CREATE TEMPORARY TABLE purchase_order_items (
            id INT PRIMARY KEY,
            po_id INT NOT NULL,
            purchase_request_item_id INT NOT NULL,
            quantity DECIMAL(12,2) NOT NULL,
            unit_price DECIMAL(12,2) NOT NULL,
            INDEX idx_test_po (po_id),
            INDEX idx_test_pr_item (purchase_request_item_id)
        ) ENGINE=InnoDB
    ");
    $db->exec("
        INSERT INTO purchase_orders
            (id, po_number, purchase_request_id, supplier_id, expected_delivery, payment_terms, status)
        VALUES (1, 'TEST-PO-1', 9001, 77, NULL, 'credit_30', 'pending')
    ");
    $db->exec("
        INSERT INTO purchase_order_items
            (id, po_id, purchase_request_item_id, quantity, unit_price)
        VALUES
            (1, 1, 101, 10.00, 50.00),
            (2, 1, 102, 10.00, 60.00)
    ");

    $sameLines = [
        ['pr_item_id' => 101, 'quantity' => 10, 'unit_price' => 50],
        ['pr_item_id' => 102, 'quantity' => 10, 'unit_price' => 60],
    ];
    $duplicate = findExactActiveDuplicatePO($db, 9001, 77, $sameLines, null, 'credit_30');
    if (($duplicate['po_number'] ?? null) !== 'TEST-PO-1') {
        throw new RuntimeException('Exact active duplicate was not detected');
    }

    $differentQuantity = $sameLines;
    $differentQuantity[0]['quantity'] = 5;
    if (findExactActiveDuplicatePO($db, 9001, 77, $differentQuantity, null, 'credit_30') !== null) {
        throw new RuntimeException('A legitimate different partial quantity was treated as an exact duplicate');
    }

    if (findExactActiveDuplicatePO($db, 9001, 78, $sameLines, null, 'credit_30') !== null) {
        throw new RuntimeException('A different supplier was treated as an exact duplicate');
    }

    $db->exec("UPDATE purchase_orders SET status = 'closed' WHERE id = 1");
    if (findExactActiveDuplicatePO($db, 9001, 77, $sameLines, null, 'credit_30') !== null) {
        throw new RuntimeException('A completed historical PO blocked a later legitimate order');
    }
} catch (Throwable $e) {
    foreach ($temporaryTables as $table) {
        try {
            $db->exec("DROP TEMPORARY TABLE IF EXISTS {$table}");
        } catch (Throwable $ignored) {
        }
    }
    fwrite(STDERR, 'Failed PO duplicate-submission guard: ' . $e->getMessage() . "\n");
    exit(1);
}

foreach ($temporaryTables as $table) {
    $db->exec("DROP TEMPORARY TABLE IF EXISTS {$table}");
}

echo "PO duplicate-submission guard tests passed.\n";
