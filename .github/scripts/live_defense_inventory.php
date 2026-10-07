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
require_once __DIR__ . '/warehouse/fg/inventory_helpers.php';

$mode = $_POST['mode'] ?? 'inspect';
if (!in_array($mode, ['inspect', 'validate', 'apply', 'inspect_locations', 'validate_locations', 'apply_locations', 'inspect_raw'], true)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid mode']));
}

$db = Database::getInstance()->getConnection();
if ($mode === 'inspect_raw') {
    try {
        $items = $db->query("SELECT i.id, i.ingredient_code, i.ingredient_name,
                i.current_stock, i.unit_of_measure, i.is_perishable, i.shelf_life_days,
                i.minimum_stock, i.reorder_point, i.maximum_stock,
                i.is_active, i.category_id, i.packaging_role,
                COUNT(ib.id) AS batch_count,
                COALESCE(SUM(CASE WHEN ib.status IN ('available','partially_used')
                    AND ib.remaining_quantity > 0 AND
                    (i.is_perishable = 0 OR (ib.expiry_date > CURDATE()
                    AND NULLIF(TRIM(ib.supplier_batch_no), '') IS NOT NULL))
                    THEN ib.remaining_quantity ELSE 0 END),0) AS usable,
                COALESCE(SUM(CASE WHEN ib.status IN ('available','partially_used','quarantine','expired')
                    AND ib.remaining_quantity > 0 THEN ib.remaining_quantity ELSE 0 END),0) AS accounted,
                COALESCE(SUM(CASE WHEN ib.expiry_date <= CURDATE() AND i.is_perishable = 1
                    AND ib.remaining_quantity > 0 AND ib.status IN ('available','partially_used','quarantine','expired')
                    THEN ib.remaining_quantity ELSE 0 END),0) AS expired
            FROM ingredients i LEFT JOIN ingredient_batches ib ON ib.ingredient_id = i.id
            WHERE i.is_active = 1 GROUP BY i.id ORDER BY i.ingredient_name")->fetchAll(PDO::FETCH_ASSOC);
        $lots = $db->query("SELECT ib.id, i.ingredient_code, ib.batch_code, ib.remaining_quantity,
                ib.quantity, ib.status, ib.qc_status, ib.supplier_batch_no,
                ib.expiry_date, ib.received_date, ib.po_id, ib.rr_id,
                LEFT(ib.notes, 120) AS notes
            FROM ingredient_batches ib JOIN ingredients i ON i.id = ib.ingredient_id
            WHERE i.is_active = 1 AND ib.remaining_quantity > 0
              AND (ib.expiry_date <= CURDATE() OR i.ingredient_code IN
                   ('ING-005','ING-003','ING-004','ING-0097','DEMO-WRAP-BUT-250','DEMO-LBL-YOG-250')
                   OR i.ingredient_code LIKE 'TST-%' OR i.ingredient_code LIKE 'MOCK-%')
            ORDER BY i.ingredient_code, ib.id")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['mode' => $mode, 'items' => $items, 'lots' => $lots]);
    } catch (Throwable $error) {
        http_response_code(500);
        echo json_encode(['error' => $error->getMessage()]);
    }
    exit;
}
function defenseDemoLocation(PDO $db): array {
    $stmt = $db->query("SELECT id, chiller_name, capacity, temperature_celsius, status
        FROM chiller_locations WHERE chiller_code = 'CHILL-A2' AND is_active = 1 FOR UPDATE");
    $location = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$location || in_array($location['status'], ['maintenance', 'offline', 'full'], true)) {
        throw new RuntimeException('Chiller A - Section 2 is unavailable');
    }
    $temperature = (float) $location['temperature_celsius'];
    if ($temperature < 2 || $temperature > 4) {
        throw new RuntimeException('Chiller A - Section 2 is outside the required 2-4 C range');
    }
    return $location;
}
if ($mode === 'validate_locations' || $mode === 'apply_locations') {
    try {
        $db->beginTransaction();
        $location = defenseDemoLocation($db);
        $rows = $db->query("SELECT fg.id, fg.product_id, fg.quantity_available,
                fg.quantity_boxes, fg.quantity_pieces
            FROM finished_goods_inventory fg JOIN production_batches pb ON pb.id = fg.batch_id
            WHERE pb.batch_code LIKE 'DEF26-%' AND fg.chiller_id IS NULL
              AND fg.status = 'available' AND fg.quantity_available > 0
              AND fg.expiry_date > CURDATE() FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
        $needed = array_sum(array_map(static fn($row) => (int) $row['quantity_available'], $rows));
        $occupied = fgChillerAuthoritativeCount($db, (int) $location['id']);
        if ((int) $location['capacity'] > 0 && $occupied + $needed > (int) $location['capacity']) {
            throw new RuntimeException('Chiller A - Section 2 has insufficient free capacity');
        }
        $warehouseUserId = defenseRoleId($db, 'warehouse_fg');
        $putAway = $db->prepare('UPDATE finished_goods_inventory
            SET chiller_id = ?, chiller_location = ?, last_movement_at = NOW()
            WHERE id = ? AND chiller_id IS NULL');
        $log = $db->prepare("INSERT INTO fg_inventory_transactions
            (transaction_code, transaction_type, inventory_id, product_id, quantity,
             boxes_quantity, pieces_quantity, quantity_before, quantity_after,
             boxes_before, pieces_before, boxes_after, pieces_after,
             to_chiller_id, performed_by, reason)
            VALUES (?, 'transfer', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $quantity = (int) $row['quantity_available'];
            $boxes = (int) $row['quantity_boxes'];
            $pieces = (int) $row['quantity_pieces'];
            $putAway->execute([(int) $location['id'], $location['chiller_name'], $id]);
            $log->execute([
                'DEMO-PUT-' . $id, $id, (int) $row['product_id'], $quantity,
                $boxes, $pieces, $quantity, $quantity, $boxes, $pieces,
                $boxes, $pieces, (int) $location['id'], $warehouseUserId,
                'Capstone demo stock put-away; synthetic classroom inventory only.'
            ]);
        }
        $newOccupancy = fgSyncChillerCount($db, (int) $location['id']);
        if ($mode === 'apply_locations') $db->commit();
        else $db->rollBack();
        echo json_encode(['mode' => $mode, 'assigned' => count($rows),
            'units' => $needed, 'location' => $location['chiller_name'],
            'occupancy' => $newOccupancy, 'capacity' => (int) $location['capacity']]);
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        http_response_code(500);
        error_log('Defense inventory location job: ' . $error->getMessage());
        echo json_encode(['error' => $error->getMessage()]);
    }
    exit;
}
if ($mode === 'inspect_locations') {
    try {
        $locations = $db->query("SELECT c.id, c.chiller_code, c.chiller_name, c.capacity,
                c.current_count AS cached_count, c.temperature_celsius,
                c.min_temperature, c.max_temperature, c.status, c.is_active,
                COALESCE((SELECT SUM(GREATEST(COALESCE(fg.quantity_available, 0),
                    COALESCE(fg.remaining_quantity, 0),
                    COALESCE(fg.boxes_available, 0) * COALESCE(NULLIF(p.pieces_per_box, 0), 1)
                        + COALESCE(fg.pieces_available, 0)))
                    FROM finished_goods_inventory fg LEFT JOIN products p ON p.id = fg.product_id
                    WHERE fg.chiller_id = c.id AND fg.status IN ('available', 'low_stock')), 0) AS occupied
            FROM chiller_locations c WHERE c.is_active = 1 ORDER BY c.chiller_code")->fetchAll(PDO::FETCH_ASSOC);
        $demo = $db->query("SELECT fg.id, fg.product_id, fg.product_name, fg.product_type,
                fg.quantity_available, fg.expiry_date, fg.chiller_id, fg.chiller_location,
                pb.batch_code, p.storage_temp_min, p.storage_temp_max
            FROM finished_goods_inventory fg
            JOIN production_batches pb ON pb.id = fg.batch_id
            LEFT JOIN products p ON p.id = fg.product_id
            WHERE pb.batch_code LIKE 'DEF26-%' AND fg.status = 'available'
              AND fg.quantity_available > 0 ORDER BY fg.id")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['mode' => $mode, 'locations' => $locations, 'demo_rows' => $demo]);
    } catch (Throwable $error) {
        http_response_code(500);
        echo json_encode(['error' => $error->getMessage()]);
    }
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
    $location = defenseDemoLocation($db);
    $needed = array_sum(array_map(static fn($product) => (int) $product['demo_quantity'], $products));
    $occupied = fgChillerAuthoritativeCount($db, (int) $location['id']);
    if ((int) $location['capacity'] > 0 && $occupied + $needed > (int) $location['capacity']) {
        throw new RuntimeException('Chiller A - Section 2 has insufficient free capacity for demo batches');
    }
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
                    ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?,
                    'available', ?)");
        $inventoryInsert->execute([
            $batchId, $releaseId, (int) $product['id'], (int) $product['milk_type_id'],
            $product['product_name'], $product['category'], $size,
            $quantity, $quantity, $quantity, $boxes, $pieces, $boxes, $pieces,
            $product['base_unit'] ?: 'piece', (float) $product['selling_price'],
            $today->format('Y-m-d'), $expiry, 'DEMO-FG-' . $batchCode,
            (int) $location['id'], $location['chiller_name'],
            $warehouseUserId, $note,
        ]);
        $created[] = ['code' => $batchCode, 'product' => $product['product_name'], 'quantity' => $quantity, 'expiry' => $expiry];
    }

    $newOccupancy = fgSyncChillerCount($db, (int) $location['id']);
    if ((int) $location['capacity'] > 0 && $newOccupancy > (int) $location['capacity']) {
        throw new RuntimeException('Demo stock exceeds chiller capacity');
    }
    if ($mode === 'apply') $db->commit();
    else $db->rollBack();
    echo json_encode(['mode' => $mode, 'created' => $created, 'already_present' => $existing,
        'location' => $location['chiller_name'], 'occupancy' => $newOccupancy]);
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    error_log('Defense inventory job: ' . $error->getMessage());
    echo json_encode(['error' => $error->getMessage()]);
}
