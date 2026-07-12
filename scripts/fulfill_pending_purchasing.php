<?php
/**
 * Fulfill open purchasing work for end-to-end testing:
 *  1) Convert remaining approved PRs → POs (supplier assigned)
 *  2) Finalize draft POs → approved → ordered
 *  3) Warehouse receive remaining qty on ordered/partial POs (stock-in)
 *
 * Usage: php scripts/fulfill_pending_purchasing.php
 */
define('HIGHLAND_FRESH', true);
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once dirname(__DIR__) . '/api/config/config.php';
require_once dirname(__DIR__) . '/api/config/database.php';

$base = 'https://localhost/HighlandFreshAppV4/api';
// Disable SSL verify for local XAMPP
stream_context_set_default([
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
]);

function api(string $method, string $url, ?array $body = null, ?string $token = null): array {
    $headers = ["Content-Type: application/json", "Accept: application/json"];
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
        $headers[] = "X-Auth-Token: {$token}";
    }
    if (in_array(strtoupper($method), ['PUT', 'DELETE'], true)) {
        $headers[] = 'X-HTTP-Method-Override: ' . strtoupper($method);
        $method = 'POST';
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 60,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        return ['http' => 0, 'success' => false, 'message' => $err ?: 'curl failed'];
    }
    $raw = ltrim($raw, "\xEF\xBB\xBF");
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['http' => $code, 'success' => false, 'message' => 'non-json', 'raw' => substr($raw, 0, 300)];
    }
    $json['http'] = $code;
    return $json;
}

function login(string $base, string $user, string $pass): string {
    $res = api('POST', $base . '/auth/login.php', ['identifier' => $user, 'password' => $pass]);
    if (empty($res['success']) || empty($res['data']['token'])) {
        throw new RuntimeException("Login failed for {$user}: " . ($res['message'] ?? json_encode($res)));
    }
    echo "OK login {$user}\n";
    return $res['data']['token'];
}

function out(string $msg): void { echo $msg . "\n"; }

$db = Database::getInstance()->getConnection();

// Default supplier for PR conversion (Anco Merchandising is used often)
$defaultSupplierId = (int)$db->query("SELECT id FROM suppliers WHERE COALESCE(is_active,1)=1 ORDER BY id LIMIT 1")->fetchColumn();
if ($defaultSupplierId <= 0) {
    throw new RuntimeException('No active supplier found');
}
out("Default supplier_id={$defaultSupplierId}");

$purchaserToken = login($base, 'purchaser', 'password');
$warehouseToken = login($base, 'warehouse_raw', 'password');

// ---------------------------------------------------------------------------
// A) Convert approved PRs that still have unconverted lines into POs
// ---------------------------------------------------------------------------
$approvedPRs = $db->query("
    SELECT id, pr_number FROM purchase_requests WHERE status = 'approved' ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($approvedPRs as $pr) {
    $prId = (int)$pr['id'];
    $items = $db->prepare("
        SELECT id, item_description, quantity, estimated_unit_price, ingredient_id, mro_item_id
        FROM purchase_request_items WHERE purchase_request_id = ?
    ");
    $items->execute([$prId]);
    $lines = $items->fetchAll(PDO::FETCH_ASSOC);
    if (!$lines) {
        out("SKIP PR {$pr['pr_number']}: no lines");
        continue;
    }

    // Skip if already has active POs linked? create_from_pr may allow split remaining —
    // check if PR items already fully allocated via purchase_request_item_po
    $payloadItems = [];
    foreach ($lines as $line) {
        $price = (float)($line['estimated_unit_price'] ?? 0);
        if ($price <= 0) {
            $price = 1.0; // satisfy positive price validation for junk lines
        }
        $payloadItems[] = [
            'purchase_request_item_id' => (int)$line['id'],
            'supplier_id' => $defaultSupplierId,
            'unit_price' => $price,
            'quantity' => (float)$line['quantity'],
        ];
    }

    out("CONVERT PR {$pr['pr_number']} ({$prId}) lines=" . count($payloadItems));
    $res = api('POST', $base . '/purchasing/purchase_orders.php?action=create_from_pr', [
        'purchase_request_id' => $prId,
        'payment_terms' => 'cash',
        'order_date' => date('Y-m-d'),
        'expected_delivery' => date('Y-m-d', strtotime('+3 days')),
        'notes' => 'Auto-fulfilled for end-to-end test flow',
        'items' => $payloadItems,
    ], $purchaserToken);

    if (!empty($res['success'])) {
        $poCount = $res['data']['po_count'] ?? count($res['data']['purchase_orders'] ?? []);
        out("  OK created {$poCount} PO(s) → PR status " . ($res['data']['pr_status'] ?? '?'));
    } else {
        out("  FAIL: " . ($res['message'] ?? json_encode($res)));
    }
}

// ---------------------------------------------------------------------------
// B) Advance draft/approved open POs → ordered
// ---------------------------------------------------------------------------
$openPos = $db->query("
    SELECT id, po_number, status FROM purchase_orders
    WHERE status IN ('draft','pending','approved','ordered','partial_received')
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($openPos as $po) {
    $id = (int)$po['id'];
    $status = $po['status'];
    out("PO {$po['po_number']} #{$id} status={$status}");

    if (in_array($status, ['draft', 'pending'], true)) {
        $res = api('PUT', $base . '/purchasing/purchase_orders.php?action=submit&id=' . $id, [
            'approval_remarks' => 'Auto-finalized for E2E test',
        ], $purchaserToken);
        out("  submit: " . ($res['success'] ? 'OK' : ($res['message'] ?? 'fail')));
        if (!empty($res['success'])) {
            $status = 'approved';
        }
    }

    // refresh status
    $st = $db->prepare("SELECT status FROM purchase_orders WHERE id = ?");
    $st->execute([$id]);
    $status = $st->fetchColumn() ?: $status;

    if ($status === 'approved') {
        $res = api('PUT', $base . '/purchasing/purchase_orders.php?action=mark_ordered&id=' . $id, [], $purchaserToken);
        out("  mark_ordered: " . ($res['success'] ? 'OK' : ($res['message'] ?? 'fail')));
        if (!empty($res['success'])) {
            $status = 'ordered';
        }
    }
}

// ---------------------------------------------------------------------------
// C) Receive remaining qty on ordered / partial_received POs
// ---------------------------------------------------------------------------
$recvPos = $db->query("
    SELECT id, po_number, status FROM purchase_orders
    WHERE status IN ('ordered','partial_received','approved')
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($recvPos as $po) {
    $id = (int)$po['id'];
    $items = $db->prepare("
        SELECT poi.id, poi.item_description, poi.quantity, poi.quantity_received,
               poi.ingredient_id, poi.mro_item_id,
               COALESCE(i.is_perishable, 1) AS ingredient_is_perishable,
               COALESCE(m.is_perishable, 0) AS mro_is_perishable
        FROM purchase_order_items poi
        LEFT JOIN ingredients i ON i.id = poi.ingredient_id
        LEFT JOIN mro_items m ON m.id = poi.mro_item_id
        WHERE poi.po_id = ?
    ");
    $items->execute([$id]);
    $lines = $items->fetchAll(PDO::FETCH_ASSOC);

    $receivingItems = [];
    foreach ($lines as $line) {
        $ordered = (float)$line['quantity'];
        $prev = (float)$line['quantity_received'];
        $remaining = max(0, $ordered - $prev);
        if ($remaining <= 0.0001) {
            continue;
        }
        $isPerishable = !empty($line['ingredient_id'])
            ? ((string)$line['ingredient_is_perishable'] === '1')
            : ((string)$line['mro_is_perishable'] === '1');

        $row = [
            'item_id' => (int)$line['id'],
            'accepted' => $remaining,
            'rejected' => 0,
            'condition' => 'acceptable',
            'lot_number' => 'E2E-' . date('Ymd') . '-' . $line['id'],
        ];
        if ($isPerishable) {
            $row['expiry_date'] = date('Y-m-d', strtotime('+180 days'));
        }
        $receivingItems[] = $row;
    }

    if (!$receivingItems) {
        out("RECEIVE PO {$po['po_number']}: nothing remaining");
        continue;
    }

    out("RECEIVE PO {$po['po_number']} #{$id} lines=" . count($receivingItems));
    $res = api('PUT', $base . '/purchasing/purchase_orders.php?action=receive_with_prices&id=' . $id, [
        'receiving_items' => $receivingItems,
        'receiving_meta' => [
            'delivery_reference' => 'E2E-DR-' . $po['po_number'],
            'driver_name' => 'E2E Test Driver',
            'notes' => 'Auto-received for end-to-end plant flow testing',
        ],
    ], $warehouseToken);

    if (!empty($res['success'])) {
        out("  OK receive: " . ($res['message'] ?? 'success'));
        // show new status
        $st = $db->prepare("SELECT status FROM purchase_orders WHERE id = ?");
        $st->execute([$id]);
        out("  new status=" . $st->fetchColumn());
    } else {
        out("  FAIL receive: " . ($res['message'] ?? json_encode($res)));
        if (!empty($res['raw'])) out("  raw=" . $res['raw']);
    }
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
out("\n=== SUMMARY ===");
foreach ($db->query("SELECT status, COUNT(*) c FROM purchase_requests GROUP BY status") as $r) {
    out("PR {$r['status']}: {$r['c']}");
}
foreach ($db->query("SELECT status, COUNT(*) c FROM purchase_orders GROUP BY status") as $r) {
    out("PO {$r['status']}: {$r['c']}");
}

// stock snapshot for key ingredients used in cooking
out("\n=== Ingredient stock snapshot (key items) ===");
$sql = "
    SELECT id, ingredient_code, ingredient_name, current_stock, unit_of_measure
    FROM ingredients
    WHERE id IN (9,10,11,13,14,15,16,17)
       OR ingredient_name LIKE '%Sugar%'
       OR ingredient_name LIKE '%Vanilla%'
       OR ingredient_name LIKE '%Culture%'
       OR ingredient_name LIKE '%Salt%'
    ORDER BY ingredient_name
    LIMIT 30
";
foreach ($db->query($sql) as $r) {
    out(sprintf("  %s %s = %s %s", $r['ingredient_code'], $r['ingredient_name'], $r['current_stock'], $r['unit_of_measure']));
}

out("\nDone.");
