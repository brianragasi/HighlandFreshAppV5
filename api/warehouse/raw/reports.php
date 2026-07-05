<?php
/**
 * Warehouse Raw — Reports endpoint
 *
 * GET ?action=inventory  -> aggregated inventory snapshot (ingredients + MRO)
 * GET ?action=movements  -> stock movements from inventory_transactions
 *
 * Both return { success, data: { ... }, timestamp } via the Response helper.
 * Auth: warehouse_raw + general_manager (read-only reports).
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

$currentUser = Auth::requireRole(['warehouse_raw', 'general_manager', 'purchaser']);
$action = getParam('action', 'inventory');

try {
    $db = Database::getInstance()->getConnection();

    if ($requestMethod !== 'GET') {
        Response::error('Method not allowed', 405);
    }

    switch ($action) {
        case 'inventory':
            handleInventoryReport($db);
            break;
        case 'movements':
            handleMovementsReport($db);
            break;
        default:
            Response::error('Unknown action: ' . $action, 400);
    }
} catch (Throwable $e) {
    error_log('Raw material reports API error: ' . $e->getMessage());
    Response::error($e->getMessage(), 500);
}

/* ========================================================================
   Inventory report — UNION of ingredients + MRO items, with computed
   status (Out / Low / OK) and last-movement timestamp.
   ======================================================================== */
function handleInventoryReport($db) {
    $type = getParam('type', 'all');                 // all | ingredient | mro
    $categoryId = getParam('category_id', null);
    $status = getParam('status', 'all');            // all | out | low | ok
    $search = trim((string) getParam('search', ''));

    // Defensive table/column existence checks so a fresh install doesn't 500
    $hasIngredients = auditTableExists($db, 'ingredients');
    $hasMro = auditTableExists($db, 'mro_items');
    $hasIngCats = auditTableExists($db, 'ingredient_categories');
    $hasMroCats = auditTableExists($db, 'mro_categories');

    $rows = [];

    if ($hasIngredients && ($type === 'all' || $type === 'ingredient')) {
        $sql = "SELECT
                    'ingredient' AS item_type,
                    i.id,
                    i.ingredient_code AS code,
                    i.ingredient_name AS name,
                    i.category_id,
                    " . ($hasIngCats ? "c.category_name" : "NULL") . " AS category,
                    i.unit_of_measure AS unit,
                    i.current_stock,
                    i.minimum_stock,
                    i.maximum_stock,
                    i.unit_cost,
                    " . ($hasMro ? "0" : "0") . " AS is_critical,
                    (SELECT MAX(it.created_at) FROM inventory_transactions it
                     WHERE it.item_type = 'ingredient' AND it.item_id = i.id) AS last_movement
                FROM ingredients i
                " . ($hasIngCats ? "LEFT JOIN ingredient_categories c ON c.id = i.category_id" : "") . "
                WHERE i.is_active = 1";
        $rows = array_merge($rows, $db->query($sql)->fetchAll());
    }

    if ($hasMro && ($type === 'all' || $type === 'mro')) {
        $sql = "SELECT
                    'mro' AS item_type,
                    m.id,
                    m.item_code AS code,
                    m.item_name AS name,
                    m.category_id,
                    " . ($hasMroCats ? "c.category_name" : "NULL") . " AS category,
                    m.unit_of_measure AS unit,
                    m.current_stock,
                    m.minimum_stock,
                    m.maximum_stock,
                    m.unit_cost,
                    m.is_critical,
                    (SELECT MAX(it.created_at) FROM inventory_transactions it
                     WHERE it.item_type = 'mro' AND it.item_id = m.id) AS last_movement
                FROM mro_items m
                " . ($hasMroCats ? "LEFT JOIN mro_categories c ON c.id = m.category_id" : "") . "
                WHERE m.is_active = 1";
        $rows = array_merge($rows, $db->query($sql)->fetchAll());
    }

    // Normalize numeric fields and compute status in PHP
    foreach ($rows as &$row) {
        $row['current_stock'] = (float) ($row['current_stock'] ?? 0);
        $row['minimum_stock'] = (float) ($row['minimum_stock'] ?? 0);
        $row['maximum_stock'] = (float) ($row['maximum_stock'] ?? 0);
        $row['unit_cost']     = (float) ($row['unit_cost'] ?? 0);
        $row['is_critical']   = (int) ($row['is_critical'] ?? 0);
        $row['value']         = round($row['current_stock'] * $row['unit_cost'], 2);
        $row['status']        = computeStockStatus(
            $row['current_stock'],
            $row['minimum_stock'],
            $row['maximum_stock']
        );
        if ($row['last_movement']) {
            $row['last_movement'] = date('Y-m-d H:i:s', strtotime($row['last_movement']));
        } else {
            $row['last_movement'] = null;
        }
    }
    unset($row);

    // Apply filters (search / category / status)
    $filtered = array_values(array_filter($rows, function ($r) use ($search, $categoryId, $status) {
        if ($search !== '') {
            $hay = strtolower($r['name'] . ' ' . $r['code']);
            if (strpos($hay, strtolower($search)) === false) {
                return false;
            }
        }
        if ($categoryId !== null && $categoryId !== '' && (int) $r['category_id'] !== (int) $categoryId) {
            return false;
        }
        if ($status !== 'all' && $r['status'] !== $status) {
            return false;
        }
        return true;
    }));

    // Sort by status severity then name (out items first, OK last). Critical
    // was merged into Low Stock.
    usort($filtered, function ($a, $b) {
        $order = ['out' => 0, 'low' => 1, 'ok' => 2];
        $oa = $order[$a['status']] ?? 3;
        $ob = $order[$b['status']] ?? 3;
        if ($oa !== $ob) {
            return $oa <=> $ob;
        }
        return strcasecmp($a['name'], $b['name']);
    });

    // Summary stats. critical_count was merged into low_count.
    $stats = [
        'total_items'   => count($filtered),
        'total_value'   => round(array_sum(array_column($filtered, 'value')), 2),
        'out_count'     => count(array_filter($filtered, fn($r) => $r['status'] === 'out')),
        'low_count'     => count(array_filter($filtered, fn($r) => $r['status'] === 'low')),
        'ok_count'      => count(array_filter($filtered, fn($r) => $r['status'] === 'ok')),
    ];

    Response::success([
        'items' => $filtered,
        'stats' => $stats,
    ], 'Inventory report retrieved');
}

/* ========================================================================
   Movements report — inventory_transactions with optional date / type /
   item_type filters. Joined to item + user names for display.
   ======================================================================== */
function handleMovementsReport($db) {
    // Date range. Defaults to last 30 days. Accepts ISO dates (Y-m-d).
    $to   = (string) getParam('to',   date('Y-m-d'));
    $from = (string) getParam('from', date('Y-m-d', strtotime('-30 days')));
    $type     = getParam('type', 'all');          // all | receive | issue | adjust | waste | transfer
    $itemType = getParam('item_type', 'all');     // all | ingredient | mro | raw_milk
    $search   = trim((string) getParam('search', ''));
    $limit    = max(1, min(1000, (int) getParam('limit', 500)));

    $where = ["DATE(it.created_at) BETWEEN ? AND ?"];
    $params = [$from, $to];

    if ($type !== 'all') {
        $where[] = "it.transaction_type = ?";
        $params[] = $type;
    }
    if ($itemType !== 'all') {
        $where[] = "it.item_type = ?";
        $params[] = $itemType;
    }

    $whereSql = implode(' AND ', $where);

    $sql = "SELECT
                it.id,
                it.transaction_code,
                it.transaction_type,
                it.item_type,
                it.item_id,
                it.quantity,
                it.unit_of_measure,
                it.reference_type,
                it.reference_id,
                it.reason,
                it.created_at,
                u.first_name,
                u.last_name,
                CASE
                    WHEN it.item_type = 'ingredient' THEN i.ingredient_name
                    WHEN it.item_type = 'mro' THEN m.item_name
                    WHEN it.item_type = 'raw_milk' THEN 'Raw Milk'
                    ELSE it.item_type
                END AS item_name,
                CASE
                    WHEN it.item_type = 'ingredient' THEN i.ingredient_code
                    WHEN it.item_type = 'mro' THEN m.item_code
                    ELSE NULL
                END AS item_code
            FROM inventory_transactions it
            JOIN users u ON u.id = it.performed_by
            LEFT JOIN ingredients i ON it.item_type = 'ingredient' AND it.item_id = i.id
            LEFT JOIN mro_items m ON it.item_type = 'mro' AND it.item_id = m.id
            WHERE $whereSql
            ORDER BY it.created_at DESC
            LIMIT $limit";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Optional text search across transaction_code / item_name / reason
    if ($search !== '') {
        $needle = strtolower($search);
        $rows = array_values(array_filter($rows, function ($r) use ($needle) {
            $hay = strtolower(
                ($r['transaction_code'] ?? '') . ' ' .
                ($r['item_name'] ?? '') . ' ' .
                ($r['item_code'] ?? '') . ' ' .
                ($r['reason'] ?? '')
            );
            return strpos($hay, $needle) !== false;
        }));
    }

    // Normalize numeric fields
    foreach ($rows as &$r) {
        $r['quantity'] = (float) ($r['quantity'] ?? 0);
        $r['reference_id'] = $r['reference_id'] !== null ? (int) $r['reference_id'] : null;
        $r['item_id'] = (int) $r['item_id'];
    }
    unset($r);

    // Direction flag for the UI badge (in vs out)
    $inbound  = ['receive'];
    $outbound = ['issue', 'waste'];
    foreach ($rows as &$r) {
        $r['direction'] = in_array($r['transaction_type'], $inbound, true) ? 'in'
                         : (in_array($r['transaction_type'], $outbound, true) ? 'out' : 'neutral');
    }
    unset($r);

    // Summary stats (computed over the SAME filtered window)
    $sumIn  = 0.0; $sumOut = 0.0; $sumAdj = 0; $sumWst = 0; $sumRcv = 0; $sumIss = 0;
    foreach ($rows as $r) {
        if ($r['direction'] === 'in')  $sumIn += $r['quantity'];
        if ($r['direction'] === 'out') $sumOut += $r['quantity'];
        if ($r['transaction_type'] === 'adjust')   $sumAdj++;
        if ($r['transaction_type'] === 'waste')    $sumWst++;
        if ($r['transaction_type'] === 'receive')  $sumRcv++;
        if ($r['transaction_type'] === 'issue')    $sumIss++;
    }
    $stats = [
        'total_movements' => count($rows),
        'stock_in_total'  => round($sumIn, 2),
        'stock_out_total' => round($sumOut, 2),
        'receive_count'   => $sumRcv,
        'issue_count'     => $sumIss,
        'adjust_count'    => $sumAdj,
        'waste_count'     => $sumWst,
        'from'            => $from,
        'to'              => $to,
    ];

    Response::success([
        'movements' => $rows,
        'stats'     => $stats,
    ], 'Stock movements retrieved');
}

/* ========================================================================
   Helpers
   ======================================================================== */
function computeStockStatus($current, $min, $max) {
    $current = (float) $current;
    $min = (float) $min;
    $max = (float) $max > 0 ? (float) $max : ($min * 3);
    if ($current <= 0) {
        return 'out';
    }
    // Critical was merged into Low Stock — at/below minimum_stock is now Low,
    // the single low-inventory tier that triggers a Purchase Request.
    if ($min > 0 && $current <= $min * 1.5) {
        return 'low';
    }
    return 'ok';
}
