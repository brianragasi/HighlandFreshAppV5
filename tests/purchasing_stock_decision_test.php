<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$root = dirname(__DIR__);
require_once $root . '/api/bootstrap.php';
require_once $root . '/api/helpers/stock_validation_support.php';

$support = file_get_contents($root . '/api/helpers/stock_validation_support.php');
$validationApi = file_get_contents($root . '/api/warehouse/raw/stock_validations.php');
$poApi = file_get_contents($root . '/api/purchasing/purchase_orders.php');
$poPage = file_get_contents($root . '/html/purchasing/purchase_orders.html');
$dashboardPage = file_get_contents($root . '/html/purchasing/dashboard.html');
$warehousePage = file_get_contents($root . '/html/warehouse/raw/reorder_alerts.html');

$checks = [
    'decision history fields exist' =>
        str_contains($support, 'purchasing_decision')
        && str_contains($support, 'purchasing_decision_reason')
        && str_contains($support, 'purchasing_decided_by')
        && str_contains($support, 'purchasing_decided_at'),
    'Purchasing can defer or close with a reason' =>
        str_contains($validationApi, 'function savePurchasingDecision')
        && str_contains($validationApi, "['defer', 'close', 'reopen']")
        && str_contains($validationApi, 'Enter a clear reason between 10 and 500 characters'),
    'future deferrals leave the active Purchasing list' =>
        str_contains($validationApi, "svi.purchasing_decision = 'deferred' AND svi.deferred_until <= CURDATE()"),
    'closed and future-deferred items cannot be forced onto a PO' =>
        str_contains($poApi, "purchasing_decision'] === 'closed_without_order'")
        && str_contains($poApi, "purchasing_decision'] === 'deferred'"),
    'Purchasing UI offers three plain-language decisions' =>
        str_contains($poPage, 'Order now')
        && str_contains($poPage, 'Defer')
        && str_contains($poPage, 'Close without order')
        && str_contains($poPage, 'decideConfirmedLowStock'),
    'Purchasing can recover a deferred or closed item' =>
        str_contains($validationApi, 'function listPurchasingDecisions')
        && str_contains($poPage, 'Deferred or closed items')
        && str_contains($poPage, 'openReopenStockDecision')
        && str_contains($poPage, "decision: 'reopen'"),
    'dashboard uses product names rather than old document codes' =>
        str_contains($dashboardPage, 'confirmed items need')
        && str_contains($dashboardPage, 'item.item_name')
        && !str_contains($dashboardPage, '${escapeHtml(pr.pr_number)} - ${escapeHtml(getPurchaseRequestItemPreview(pr))}'),
    'Warehouse can see the Purchasing outcome' =>
        str_contains($warehousePage, 'Closed by Purchasing')
        && str_contains($warehousePage, 'stockOutcomeDialog')
        && str_contains($warehousePage, 'Purchasing can reopen it'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if (!$failed) {
    $db = Database::getInstance()->getConnection();
    ensureStockValidationSupport($db);
    $columns = $db->query("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'stock_validation_items'
          AND COLUMN_NAME IN ('purchasing_decision','deferred_until','purchasing_decision_reason','purchasing_decided_by','purchasing_decided_at')
    ")->fetchAll(PDO::FETCH_COLUMN);
    $schemaPassed = count($columns) === 5;
    echo ($schemaPassed ? '[PASS] ' : '[FAIL] ') . 'live database has all decision fields' . PHP_EOL;
    if (!$schemaPassed) $failed[] = 'live database has all decision fields';

    if ($schemaPassed) {
        $db->beginTransaction();
        try {
            $number = 'TEST-DECISION-' . bin2hex(random_bytes(4));
            $db->prepare("INSERT INTO stock_validations (validation_number, validated_by, status) VALUES (?, 1, 'open')")
                ->execute([$number]);
            $validationId = (int) $db->lastInsertId();
            $db->prepare("
                INSERT INTO stock_validation_items
                    (stock_validation_id, item_description, unit, system_stock_before, physical_stock,
                     stock_variance, reorder_point_at_validation, target_stock_at_validation, quantity_needed)
                VALUES (?, 'Decision test item', 'kg', 10, 10, 0, 20, 50, 40)
            ")->execute([$validationId]);
            $itemId = (int) $db->lastInsertId();

            $db->prepare("
                UPDATE stock_validation_items
                SET purchasing_decision = 'closed_without_order', purchasing_decision_reason = 'Test business reason',
                    purchasing_decided_by = 1, purchasing_decided_at = NOW()
                WHERE id = ?
            ")->execute([$itemId]);
            $closedState = recomputeStockValidationState($db, $validationId);

            $db->prepare("
                UPDATE stock_validation_items
                SET purchasing_decision = 'pending', deferred_until = NULL
                WHERE id = ?
            ")->execute([$itemId]);
            $reopenedState = recomputeStockValidationState($db, $validationId);
            $statePassed = $closedState === 'cancelled' && $reopenedState === 'open';
            echo ($statePassed ? '[PASS] ' : '[FAIL] ') . 'closing resolves the shortage and reopening restores it' . PHP_EOL;
            if (!$statePassed) $failed[] = 'closing resolves the shortage and reopening restores it';
        } finally {
            if ($db->inTransaction()) $db->rollBack();
        }
    }
}

if ($failed) {
    fwrite(STDERR, "Purchasing stock-decision checks failed.\n");
    exit(1);
}

echo "Purchasing stock-decision checks passed.\n";
