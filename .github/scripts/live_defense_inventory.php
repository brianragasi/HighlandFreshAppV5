<?php
/** One-time, token-protected defense inventory job. Uploaded and removed by CI. */
header('Content-Type: application/json; charset=utf-8');
$expectedToken = '__DEFENSE_JOB_TOKEN__';
$actualToken = $_SERVER['HTTP_X_DEFENSE_JOB_TOKEN'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($expectedToken, $actualToken)) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

define('HIGHLAND_FRESH', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$mode = $_POST['mode'] ?? 'inspect';
if (!in_array($mode, ['inspect', 'validate', 'apply', 'inspect_expired', 'validate_expired', 'clear_expired'], true)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid mode']));
}

$db = Database::getInstance()->getConnection();
if ($mode === 'inspect_expired') {
    $expired = $db->query("SELECT fg.id, fg.product_id, fg.product_name,
            COALESCE(pb.batch_code, CONCAT('FG-', fg.id)) AS batch_code,
            fg.status, fg.expiry_date, fg.quantity_available, fg.remaining_quantity,
            fg.quantity_reserved, fg.boxes_available, fg.pieces_available,
            fg.quantity_boxes, fg.quantity_pieces, fg.disposed_quantity,
            fg.chiller_id, COALESCE(c.chiller_name, fg.chiller_location, 'Unassigned') AS location_name,
            (SELECT COUNT(*) FROM disposals d WHERE d.source_type = 'finished_goods'
                AND d.source_id = fg.id AND d.status IN ('pending', 'approved')) AS open_disposals
            ,(SELECT CONCAT(d.disposal_code, ':', d.status, ':', d.quantity)
                FROM disposals d WHERE d.source_type = 'finished_goods'
                AND d.source_id = fg.id AND d.status IN ('pending', 'approved')
                ORDER BY d.id DESC LIMIT 1) AS open_disposal_detail
        FROM finished_goods_inventory fg
        LEFT JOIN production_batches pb ON pb.id = fg.batch_id
        LEFT JOIN chiller_locations c ON c.id = fg.chiller_id
        WHERE fg.expiry_date < CURDATE()
          AND fg.status IN ('available', 'low_stock', 'reserved', 'expired')
          AND (COALESCE(fg.quantity_available, 0) > 0
            OR COALESCE(fg.boxes_available, 0) > 0
            OR COALESCE(fg.pieces_available, 0) > 0)
        ORDER BY fg.expiry_date, fg.id")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['mode' => $mode, 'count' => count($expired), 'rows' => $expired]);
    exit;
}
$targets = [
    'BUT-250' => 24,
    'CHO-1L' => 24,
    'FM0015' => 24,
    'FMK-1L' => 24,
    'FMK-500' => 24,
    'PM0006' => 24,
];

function defenseRoleId(PDO $db, string $role): int {
    $stmt = $db->prepare('SELECT id FROM users WHERE role = ? AND is_active = 1 ORDER BY id LIMIT 1');
    $stmt->execute([$role]);
    $id = (int) $stmt->fetchColumn();
    if ($id < 1) throw new RuntimeException("No active {$role} account exists");
    return $id;
}

try {
    $today = new DateTimeImmutable('today');
    $products = [];
    $query = $db->prepare('SELECT id, base_product_id, product_code, product_name, category,
        milk_type_id, unit_size, unit_measure, base_unit, pieces_per_box,
        shelf_life_days, selling_price, is_active FROM products WHERE product_code = ? LIMIT 1');
    foreach ($targets as $code => $desiredAvailable) {
        $query->execute([$code]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException("Required demo SKU {$code} is missing");
        if ((int) $row['is_active'] !== 1 || (int) $row['base_product_id'] < 1
            || (int) $row['shelf_life_days'] < 8 || (float) $row['selling_price'] <= 0) {
            throw new RuntimeException("Demo SKU {$code} is inactive or lacks a catalog shelf life/price");
        }
        $row['demo_expiry'] = $today->modify('+' . (int) $row['shelf_life_days'] . ' days')->format('Y-m-d');
        $row['existing_demo_batch'] = null;
        $batchCode = 'DEF26-' . $today->format('Ymd') . '-' . $code;
        $existing = $db->prepare('SELECT id FROM production_batches WHERE batch_code = ? LIMIT 1');
        $existing->execute([$batchCode]);
        $row['existing_demo_batch'] = $existing->fetchColumn() ?: null;
        $stock = $db->prepare("SELECT COALESCE(SUM(GREATEST(0, quantity_available)), 0)
            FROM finished_goods_inventory
            WHERE product_id = ? AND status = 'available'
              AND expiry_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY)
              AND quantity_available > 0");
        $stock->execute([(int) $row['id']]);
        $row['sellable_on_hand'] = (int) $stock->fetchColumn();
        $reserved = $db->prepare("SELECT COALESCE(SUM(soi.quantity_ordered), 0)
            FROM sales_order_items soi JOIN sales_orders so ON so.id = soi.order_id
            WHERE soi.product_id = ? AND so.status IN ('pending', 'approved', 'picking', 'preparing')");
        $reserved->execute([(int) $row['id']]);
        $row['reserved_units'] = (int) $reserved->fetchColumn();
        $row['available_to_order'] = max(0, $row['sellable_on_hand'] - $row['reserved_units']);
        $row['demo_quantity'] = $row['available_to_order'] > 0
            ? 0 : max(0, $desiredAvailable + $row['reserved_units'] - $row['sellable_on_hand']);
        $products[] = $row;
    }

    if ($mode === 'inspect') {
        $catalog = $db->query("SELECT p.id, p.product_code, p.product_name, p.category,
                   p.base_product_id, p.shelf_life_days, p.selling_price,
                   p.unit_size, p.unit_measure, p.base_unit, p.pieces_per_box,
                   COALESCE(stock.on_hand, 0) AS sellable_on_hand,
                   COALESCE(res.reserved, 0) AS reserved_units
            FROM products p
            LEFT JOIN (
                SELECT product_id, SUM(GREATEST(0, quantity_available)) AS on_hand
                FROM finished_goods_inventory
                WHERE status = 'available'
                  AND expiry_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                  AND quantity_available > 0
                GROUP BY product_id
            ) stock ON stock.product_id = p.id
            LEFT JOIN (
                SELECT soi.product_id, SUM(soi.quantity_ordered) AS reserved
                FROM sales_order_items soi JOIN sales_orders so ON so.id = soi.order_id
                WHERE so.status IN ('pending', 'approved', 'picking', 'preparing')
                GROUP BY soi.product_id
            ) res ON res.product_id = p.id
            WHERE p.is_active = 1 ORDER BY p.product_code")->fetchAll(PDO::FETCH_ASSOC);
        $grey = [];
        $readyCount = 0;
        foreach ($catalog as $item) {
            $item['available_to_order'] = max(0, (int) $item['sellable_on_hand'] - (int) $item['reserved_units']);
            if ($item['available_to_order'] === 0) $grey[] = $item;
            else $readyCount++;
        }
        echo json_encode(['mode' => 'inspect', 'date' => $today->format('Y-m-d'),
            'ready_sku_count' => $readyCount, 'grey_products' => $grey, 'prior_demo_products' => $products]);
        exit;
    }

    $productionUserId = defenseRoleId($db, 'production_staff');
    $qcUserId = defenseRoleId($db, 'qc_officer');
    $warehouseUserId = defenseRoleId($db, 'warehouse_fg');
    $db->beginTransaction();
    $created = [];
    $existing = [];
    $note = 'CAPSTONE DEMO STOCK ONLY; synthetic classroom batch, not physical inventory or approved for real sale.';

    foreach ($products as $product) {
        $code = $product['product_code'];
        $batchCode = 'DEF26-' . $today->format('Ymd') . '-' . $code;
        if ((int) $product['demo_quantity'] === 0) {
            $existing[] = $code . ' already available';
            continue;
        }
        if ($product['existing_demo_batch']) {
            $existing[] = $batchCode;
            continue;
        }
        $quantity = (int) $product['demo_quantity'];
        $perBox = max(1, (int) $product['pieces_per_box']);
        $boxes = $perBox > 1 ? intdiv($quantity, $perBox) : 0;
        $pieces = $perBox > 1 ? $quantity % $perBox : $quantity;
        $size = (float) $product['unit_size'];
        $bulkLiters = strtolower((string) $product['unit_measure']) === 'ml'
            ? $quantity * $size / 1000 : 0;
        $expiry = $product['demo_expiry'];

        $batchInsert = $db->prepare("INSERT INTO production_batches
            (batch_code, product_id, base_product_id, milk_type_id, product_type,
             raw_milk_liters, manufacturing_date, manufacturing_time, expiry_date,
             packaging_integrity_passed, labeling_passed, qc_verified_boxes,
             qc_verified_pieces, qc_count_variance, qc_status, qc_released_at,
             qc_notes, fg_received, fg_received_at, fg_received_by, expected_yield,
             actual_yield, bulk_volume_liters, bulk_remaining_liters, barcode,
             created_by, released_by, released_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURTIME(), ?, 1, 1, ?, ?, 0,
                    'released', NOW(), ?, 1, NOW(), ?, ?, ?, ?, 0, ?, ?, ?, NOW())");
        $batchInsert->execute([
            $batchCode, (int) $product['id'], (int) $product['base_product_id'],
            (int) $product['milk_type_id'], $product['category'], $bulkLiters,
            $today->format('Y-m-d'), $expiry, $boxes, $pieces, $note,
            $warehouseUserId, $quantity, $quantity, $bulkLiters,
            $batchCode, $productionUserId, $qcUserId,
        ]);
        $batchId = (int) $db->lastInsertId();

        $releaseInsert = $db->prepare("INSERT INTO qc_batch_release
            (release_code, batch_id, inspection_datetime, packaging_integrity,
             label_accuracy, seal_quality, date_code_correct, ccp_records_complete,
             ccp_all_passed, release_decision, inspected_by, approved_by,
             approval_datetime, notes)
            VALUES (?, ?, NOW(), 'pass', 'pass', 'pass', 1, 1, 1,
                    'approved', ?, ?, NOW(), ?)");
        $releaseInsert->execute(['QCR-' . $batchCode, $batchId, $qcUserId, $qcUserId, $note]);
        $releaseId = (int) $db->lastInsertId();

        $inventoryInsert = $db->prepare("INSERT INTO finished_goods_inventory
            (batch_id, qc_release_id, product_id, milk_type_id, product_name,
             product_type, size_ml, quantity, remaining_quantity, quantity_available,
             disposed_quantity, quantity_reserved, quantity_boxes, quantity_pieces,
             boxes_available, pieces_available, unit, unit_price, manufacturing_date,
             expiry_date, barcode, chiller_id, chiller_location, received_at,
             last_movement_at, received_by, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, NULL, 'CAPSTONE DEMO', NOW(), NOW(), ?,
                    'available', ?)");
        $inventoryInsert->execute([
            $batchId, $releaseId, (int) $product['id'], (int) $product['milk_type_id'],
            $product['product_name'], $product['category'], $size,
            $quantity, $quantity, $quantity, $boxes, $pieces, $boxes, $pieces,
            $product['base_unit'] ?: 'piece', (float) $product['selling_price'],
            $today->format('Y-m-d'), $expiry, 'DEMO-FG-' . $batchCode,
            $warehouseUserId, $note,
        ]);
        $created[] = ['code' => $batchCode, 'product' => $product['product_name'], 'quantity' => $quantity, 'expiry' => $expiry];
    }

    if ($mode === 'apply') $db->commit();
    else $db->rollBack();
    echo json_encode(['mode' => $mode, 'created' => $created, 'already_present' => $existing]);
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    error_log('Defense inventory job: ' . $error->getMessage());
    echo json_encode(['error' => $error->getMessage()]);
}
