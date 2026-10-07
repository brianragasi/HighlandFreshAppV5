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
if (!in_array($mode, ['inspect', 'validate', 'apply', 'inspect_locations', 'validate_locations', 'apply_locations', 'inspect_raw', 'validate_raw', 'apply_raw', 'inspect_gap_packaging', 'validate_gap_packaging', 'apply_gap_packaging', 'inspect_low_stock_scenario', 'validate_low_stock_scenario', 'apply_low_stock_scenario'], true)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid mode']));
}

$db = Database::getInstance()->getConnection();
if ($mode === 'validate_low_stock_scenario' || $mode === 'apply_low_stock_scenario') {
    try {
        $db->beginTransaction();
        $item = $db->query("SELECT id, ingredient_code, ingredient_name, unit_of_measure,
                current_stock, minimum_stock, reorder_point, maximum_stock, is_active
            FROM ingredients WHERE ingredient_code = 'ING-0101' FOR UPDATE")
            ->fetch(PDO::FETCH_ASSOC);
        if (!$item || (int) $item['is_active'] !== 1 || $item['unit_of_measure'] !== 'pcs'
            || (float) $item['current_stock'] !== 300.0) {
            throw new RuntimeException('Pineapple yogurt label is no longer at the reviewed 300-piece balance');
        }
        $id = (int) $item['id'];
        $usable = $db->prepare("SELECT COALESCE(SUM(remaining_quantity), 0)
            FROM ingredient_batches WHERE ingredient_id = ?
              AND status IN ('available','partially_used') AND remaining_quantity > 0
              AND (expiry_date IS NULL OR expiry_date > CURDATE())");
        $usable->execute([$id]);
        if ((float) $usable->fetchColumn() !== 300.0) {
            throw new RuntimeException('Usable lot balance changed; review it before preparing the walkthrough');
        }
        $supplier = $db->prepare("SELECT s.supplier_code, s.supplier_name, si.reference_unit_price
            FROM supplier_ingredients si JOIN suppliers s ON s.id = si.supplier_id
            WHERE si.ingredient_id = ? AND si.is_active = 1 AND s.is_active = 1
              AND si.reference_unit_price > 0 ORDER BY si.reference_unit_price LIMIT 1");
        $supplier->execute([$id]);
        $offer = $supplier->fetch(PDO::FETCH_ASSOC);
        if (!$offer) throw new RuntimeException('No active supplier with a reference price is linked');
        $onOrder = $db->prepare("SELECT COALESCE(SUM(GREATEST(poi.quantity
                - COALESCE(poi.quantity_received, 0)
                - COALESCE(poi.quantity_rejected, 0)
                - COALESCE(poi.quantity_short_closed, 0), 0)), 0)
            FROM purchase_order_items poi JOIN purchase_orders po ON po.id = poi.po_id
            WHERE poi.ingredient_id = ?
              AND po.status IN ('pending','approved','ordered','partial_received')");
        $onOrder->execute([$id]);
        if ((float) $onOrder->fetchColumn() !== 0.0) {
            throw new RuntimeException('An active purchase order now covers this item');
        }
        $validation = $db->prepare("SELECT COUNT(*) FROM stock_validation_items svi
            JOIN stock_validations sv ON sv.id = svi.stock_validation_id
            WHERE svi.ingredient_id = ? AND svi.is_queue_active = 1
              AND sv.status IN ('open','partially_ordered')");
        $validation->execute([$id]);
        if ((int) $validation->fetchColumn() !== 0) {
            throw new RuntimeException('Warehouse has already confirmed this shortage');
        }
        $alreadyPrepared = (float) $item['minimum_stock'] === 150.0
            && (float) $item['reorder_point'] === 350.0
            && (float) $item['maximum_stock'] === 500.0;
        if (!$alreadyPrepared) {
            if ((float) $item['minimum_stock'] !== 50.0
                || (float) $item['reorder_point'] !== 100.0
                || (float) $item['maximum_stock'] !== 400.0) {
                throw new RuntimeException('Existing thresholds changed; review before overwriting them');
            }
            $db->prepare('UPDATE ingredients SET minimum_stock = 150,
                    reorder_point = 350, maximum_stock = 500, updated_at = NOW()
                WHERE id = ?')->execute([$id]);
        }
        if ($mode === 'apply_low_stock_scenario') $db->commit();
        else $db->rollBack();
        echo json_encode([
            'mode' => $mode, 'item' => $item['ingredient_code'],
            'name' => $item['ingredient_name'], 'usable_stock_pcs' => 300,
            'minimum_stock_pcs' => 150, 'reorder_point_pcs' => 350,
            'restock_target_pcs' => 500, 'suggested_shortage_pcs' => 200,
            'supplier' => $offer['supplier_name'],
            'reference_unit_price_php' => (float) $offer['reference_unit_price'],
            'already_prepared' => $alreadyPrepared,
            'warehouse_validation_created' => false,
            'purchase_order_created' => false,
        ]);
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        http_response_code(500);
        error_log('Defense low-stock scenario job: ' . $error->getMessage());
        echo json_encode(['error' => $error->getMessage()]);
    }
    exit;
}
if ($mode === 'inspect_low_stock_scenario') {
    try {
        $rows = $db->query("SELECT i.id, i.ingredient_code, i.ingredient_name,
                i.unit_of_measure, i.current_stock, i.minimum_stock,
                i.reorder_point, i.maximum_stock, i.unit_cost, i.is_active,
                (SELECT COALESCE(SUM(ib.remaining_quantity),0) FROM ingredient_batches ib
                 WHERE ib.ingredient_id = i.id AND ib.status IN ('available','partially_used')
                   AND ib.remaining_quantity > 0 AND
                   (i.is_perishable = 0 OR (ib.expiry_date > CURDATE()
                    AND NULLIF(TRIM(ib.supplier_batch_no), '') IS NOT NULL))) AS usable,
                (SELECT GROUP_CONCAT(CONCAT(s.supplier_code, ':', s.supplier_name,
                    ':', si.reference_unit_price) SEPARATOR '; ')
                 FROM supplier_ingredients si JOIN suppliers s ON s.id = si.supplier_id
                 WHERE si.ingredient_id = i.id AND si.is_active = 1 AND s.is_active = 1) AS suppliers,
                (SELECT COALESCE(SUM(GREATEST(poi.quantity
                    - COALESCE(poi.quantity_received,0)
                    - COALESCE(poi.quantity_rejected,0)
                    - COALESCE(poi.quantity_short_closed,0),0)),0)
                 FROM purchase_order_items poi JOIN purchase_orders po ON po.id = poi.po_id
                 WHERE poi.ingredient_id = i.id
                   AND po.status IN ('pending','approved','ordered','partial_received')) AS on_order,
                (SELECT COUNT(*) FROM stock_validation_items svi
                 JOIN stock_validations sv ON sv.id = svi.stock_validation_id
                 WHERE svi.ingredient_id = i.id AND svi.is_queue_active = 1
                   AND sv.status IN ('open','partially_ordered')) AS active_validations
            FROM ingredients i WHERE i.ingredient_code IN
                ('ING-0096','ING-0097','ING-0099','ING-0101','ING-0076',
                 'TST-PKG-BTL-250','ING-003','ING-002','ING-007')
            ORDER BY i.ingredient_code")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['mode' => $mode, 'candidates' => $rows]);
    } catch (Throwable $error) {
        http_response_code(500);
        echo json_encode(['error' => $error->getMessage()]);
    }
    exit;
}
if ($mode === 'validate_gap_packaging' || $mode === 'apply_gap_packaging') {
    try {
        $db->beginTransaction();
        $warehouseUserId = defenseRoleId($db, 'warehouse_raw');
        $gmUserId = defenseRoleId($db, 'general_manager');
        $materials = [
            'DEMO-LBL-YOG-250' => [
                'product_code' => 'YOG-500', 'name' => 'Plain Yogurt 250 mL Label (Demo)',
                'role' => 'label', 'form' => 'label', 'size' => 250.0, 'measure' => 'ml',
                'cost' => 0.75, 'suffix' => 'YOG250',
            ],
            'DEMO-WRAP-BUT-250' => [
                'product_code' => 'BUT-250', 'name' => '250 g Butter Wrapper (Demo)',
                'role' => 'container', 'form' => 'wrapper', 'size' => 250.0, 'measure' => 'g',
                'cost' => 1.50, 'suffix' => 'BUT250',
            ],
        ];
        $lookup = $db->prepare('SELECT id, ingredient_code, ingredient_name, current_stock,
                unit_of_measure, packaging_role, packaging_form, packaging_capacity_value,
                packaging_capacity_unit, is_active FROM ingredients
            WHERE ingredient_code = ? FOR UPDATE');
        $product = $db->prepare('SELECT id, product_code, unit_size, unit_measure,
                primary_container_id, is_active FROM products WHERE product_code = ?');
        $bom = $db->prepare('SELECT quantity_per_unit, waste_percent, unit
            FROM sku_packaging_bom_items WHERE product_id = ? AND ingredient_id = ? AND is_active = 1');
        $batchCount = $db->prepare("SELECT COALESCE(SUM(remaining_quantity), 0)
            FROM ingredient_batches WHERE ingredient_id = ?
              AND status IN ('available','partially_used','quarantine','expired')");
        $update = $db->prepare('UPDATE ingredients SET ingredient_name = ?, current_stock = 500,
            minimum_stock = 50, reorder_point = 100, maximum_stock = 500,
            unit_cost = ?, packaging_capacity_confirmed = 1, updated_at = NOW()
            WHERE id = ? AND current_stock = 0');
        $addLot = $db->prepare("INSERT INTO ingredient_batches
            (batch_code, ingredient_id, quantity, remaining_quantity, unit_cost,
             supplier_batch_no, received_date, expiry_date, qc_status, received_by, status, notes)
            VALUES (?, ?, 500, 500, ?, ?, CURDATE(), NULL, 'approved', ?, 'available', ?)");
        $transaction = $db->prepare("INSERT INTO inventory_transactions
            (transaction_code, transaction_type, item_type, item_id, batch_id,
             quantity, unit_of_measure, quantity_before, quantity_after,
             reference_type, reference_id, performed_by, approved_by, reason)
            VALUES (?, 'physical_adjust', 'packaging', ?, ?, 500, 'pcs', 0, 500,
                    'capstone_demo_opening', ?, ?, ?, ?)");
        $created = [];
        $alreadyPresent = [];
        foreach ($materials as $code => $spec) {
            $lookup->execute([$code]);
            $row = $lookup->fetch(PDO::FETCH_ASSOC);
            $product->execute([$spec['product_code']]);
            $sku = $product->fetch(PDO::FETCH_ASSOC);
            if (!$row || !$sku || (int) $row['is_active'] !== 1 || (int) $sku['is_active'] !== 1
                || $row['unit_of_measure'] !== 'pcs' || $row['packaging_role'] !== $spec['role']
                || $row['packaging_form'] !== $spec['form']
                || (float) $row['packaging_capacity_value'] !== $spec['size']
                || strtolower((string) $row['packaging_capacity_unit']) !== $spec['measure']
                || (float) $sku['unit_size'] !== $spec['size']
                || strtolower((string) $sku['unit_measure']) !== $spec['measure']) {
                throw new RuntimeException("{$code} or its SKU differs from the reviewed demo specification");
            }
            if ($code === 'DEMO-WRAP-BUT-250'
                && (int) $sku['primary_container_id'] !== (int) $row['id']) {
                throw new RuntimeException('Butter 250 g is no longer linked to the reviewed wrapper');
            }
            $bom->execute([(int) $sku['id'], (int) $row['id']]);
            $bomRow = $bom->fetch(PDO::FETCH_ASSOC);
            if (!$bomRow || (float) $bomRow['quantity_per_unit'] !== 1.0
                || (float) $bomRow['waste_percent'] !== 0.0 || $bomRow['unit'] !== 'pcs') {
                throw new RuntimeException("{$code} packaging BOM differs from the reviewed one-per-unit plan");
            }
            $batchCount->execute([(int) $row['id']]);
            $accounted = (float) $batchCount->fetchColumn();
            if ((float) $row['current_stock'] >= 500.0 && $accounted >= 500.0) {
                $alreadyPresent[] = $code;
                continue;
            }
            if ((float) $row['current_stock'] !== 0.0 || $accounted !== 0.0) {
                throw new RuntimeException("{$code} now has stock; refresh its count before applying demo opening stock");
            }
            $lotCode = 'DEMO-PKG-' . date('Ymd') . '-' . $spec['suffix'];
            $existing = $db->prepare('SELECT id FROM ingredient_batches WHERE batch_code = ?');
            $existing->execute([$lotCode]);
            if ($existing->fetchColumn()) throw new RuntimeException("Demo lot {$lotCode} already exists");
            $update->execute([$spec['name'], $spec['cost'], (int) $row['id']]);
            if ($update->rowCount() !== 1) throw new RuntimeException("Could not update {$code}");
            $addLot->execute([
                $lotCode, (int) $row['id'], $spec['cost'],
                'DEMO-OPEN-' . date('Ymd') . '-' . $spec['suffix'], $warehouseUserId,
                'CAPSTONE DEMO ONLY: synthetic 500-piece opening balance; planned size and estimated cost, not a physical count, supplier quote, delivery, or actual QC result.',
            ]);
            $batchId = (int) $db->lastInsertId();
            $transaction->execute([
                'DEMO-PKG-OPEN-' . $batchId, (int) $row['id'], $batchId,
                $batchId, $warehouseUserId, $gmUserId,
                'CAPSTONE DEMO synthetic packaging opening balance and estimated cost; not physical stock or a supplier delivery.',
            ]);
            $created[] = ['code' => $code, 'sku' => $spec['product_code'],
                'quantity' => 500, 'estimated_unit_cost' => $spec['cost'],
                'lot_code' => $lotCode];
        }
        if ($mode === 'apply_gap_packaging') $db->commit();
        else $db->rollBack();
        echo json_encode(['mode' => $mode, 'created' => $created, 'already_present' => $alreadyPresent]);
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        http_response_code(500);
        error_log('Defense packaging gap job: ' . $error->getMessage());
        echo json_encode(['error' => $error->getMessage()]);
    }
    exit;
}
if ($mode === 'inspect_gap_packaging') {
    try {
        $materials = $db->query("SELECT i.id, i.ingredient_code, i.ingredient_name,
                i.unit_of_measure, i.current_stock, i.unit_cost, i.market_price,
                i.packaging_role, i.packaging_form, i.packaging_capacity_value,
                i.packaging_capacity_unit, i.packaging_capacity_confirmed,
                i.minimum_stock, i.reorder_point, i.maximum_stock, i.is_active,
                COUNT(DISTINCT si.supplier_id) AS supplier_count
            FROM ingredients i LEFT JOIN supplier_ingredients si
                ON si.ingredient_id = i.id AND si.is_active = 1
            WHERE i.ingredient_code IN ('DEMO-LBL-YOG-250','DEMO-WRAP-BUT-250')
            GROUP BY i.id ORDER BY i.ingredient_code")->fetchAll(PDO::FETCH_ASSOC);
        $skus = $db->query("SELECT p.id, p.product_code, p.product_name,
                p.unit_size, p.unit_measure, p.base_unit, p.primary_container_id,
                b.ingredient_id, b.quantity_per_unit, b.waste_percent, b.unit,
                b.is_active AS bom_active, i.ingredient_code AS material_code
            FROM products p LEFT JOIN sku_packaging_bom_items b ON b.product_id = p.id
            LEFT JOIN ingredients i ON i.id = b.ingredient_id
            WHERE p.product_code IN ('YOG-500','BUT-250')
            ORDER BY p.product_code, b.id")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['mode' => $mode, 'materials' => $materials, 'sku_bom' => $skus]);
    } catch (Throwable $error) {
        http_response_code(500);
        echo json_encode(['error' => $error->getMessage()]);
    }
    exit;
}
if ($mode === 'validate_raw' || $mode === 'apply_raw') {
    try {
        // These are classroom records. Keep every retired lot and correction
        // traceable; never change an expiry date to make old stock usable.
        $db->beginTransaction();
        $db->query('SELECT id FROM raw_material_waste LIMIT 1');
        $warehouseUserId = defenseRoleId($db, 'warehouse_raw');
        $gmUserId = defenseRoleId($db, 'general_manager');
        $retired = [];
        $rounded = [];
        $reconciled = [];
        $renamed = [];
        $replenished = [];
        $retiredByIngredient = [];
        $roundedByIngredient = [];
        $expiredCodes = ['ING-0092','ING-003','ING-005','ING-0094','ING-006','ING-004','ING-001'];
        $marks = implode(',', array_fill(0, count($expiredCodes), '?'));
        $expired = $db->prepare("SELECT ib.*, i.ingredient_code, i.ingredient_name, i.unit_of_measure
            FROM ingredient_batches ib JOIN ingredients i ON i.id = ib.ingredient_id
            WHERE i.ingredient_code IN ($marks) AND i.is_active = 1
              AND ib.remaining_quantity > 0 AND ib.expiry_date <= CURDATE()
              AND ib.status IN ('available','partially_used','quarantine','expired')
            ORDER BY ib.id FOR UPDATE");
        $expired->execute($expiredCodes);
        $retireBatch = $db->prepare("UPDATE ingredient_batches
            SET remaining_quantity = 0, status = 'consumed',
                notes = CONCAT(COALESCE(notes, ''), '\nCAPSTONE DEMO: simulated expiry disposal; no physical disposal claimed.'),
                updated_at = NOW() WHERE id = ? AND remaining_quantity = ?");
        $waste = $db->prepare("INSERT INTO raw_material_waste
            (waste_code, item_type, item_id, batch_id, rr_id, po_id, supplier_id,
             batch_code, item_name, quantity, unit, unit_cost, total_value,
             reason_category, reason, waste_date, status, recorded_by, approved_by, approved_at)
            VALUES (?, 'ingredient', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    'expired', ?, CURDATE(), 'approved', ?, ?, NOW())");
        $transaction = $db->prepare("INSERT INTO inventory_transactions
            (transaction_code, transaction_type, item_type, item_id, batch_id,
             quantity, unit_of_measure, quantity_before, quantity_after,
             reference_type, reference_id, performed_by, approved_by, reason)
            VALUES (?, ?, 'ingredient', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($expired->fetchAll(PDO::FETCH_ASSOC) as $lot) {
            $id = (int) $lot['id'];
            $ingredientId = (int) $lot['ingredient_id'];
            $quantity = (float) $lot['remaining_quantity'];
            $retireBatch->execute([$id, $lot['remaining_quantity']]);
            if ($retireBatch->rowCount() !== 1) throw new RuntimeException("Expired lot {$id} changed during cleanup");
            $waste->execute([
                'DEMO-EXP-' . $id, $ingredientId, $id, $lot['rr_id'], $lot['po_id'],
                $lot['supplier_id'], $lot['batch_code'], $lot['ingredient_name'],
                $quantity, $lot['unit_of_measure'], (float) $lot['unit_cost'],
                round($quantity * (float) $lot['unit_cost'], 2),
                'CAPSTONE DEMO: simulated expiry disposal of historical test stock; no physical disposal claimed.',
                $warehouseUserId, $gmUserId,
            ]);
            $wasteId = (int) $db->lastInsertId();
            $transaction->execute([
                'DEMO-EXP-TX-' . $id, 'dispose', $ingredientId, $id,
                $quantity, $lot['unit_of_measure'], $quantity, 0,
                'raw_material_waste', $wasteId, $warehouseUserId, $gmUserId,
                'CAPSTONE DEMO simulated expiry disposal; no physical disposal claimed.',
            ]);
            $retiredByIngredient[$ingredientId] = ($retiredByIngredient[$ingredientId] ?? 0) + $quantity;
            $retired[] = ['code' => $lot['ingredient_code'], 'batch' => $lot['batch_code'], 'quantity' => $quantity];
        }

        // Historical fractional pieces came from a packaging allowance that
        // previously rounded counts as decimals. Write off the fraction only.
        $discrete = $db->query("SELECT ib.id, ib.ingredient_id, ib.remaining_quantity,
                i.ingredient_code, i.unit_of_measure
            FROM ingredient_batches ib JOIN ingredients i ON i.id = ib.ingredient_id
            WHERE i.ingredient_code IN ('TST-PKG-CAP-28','TST-PKG-BTL-500')
              AND ib.remaining_quantity > 0 AND ib.status IN ('available','partially_used')
            FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
        $roundLot = $db->prepare('UPDATE ingredient_batches SET remaining_quantity = ?, updated_at = NOW() WHERE id = ?');
        foreach ($discrete as $lot) {
            $before = (float) $lot['remaining_quantity'];
            $after = floor($before + 0.0000001);
            $difference = round($before - $after, 2);
            if ($difference < 0.005) continue;
            $id = (int) $lot['id'];
            $ingredientId = (int) $lot['ingredient_id'];
            $roundLot->execute([$after, $id]);
            $transaction->execute([
                'DEMO-ROUND-' . $id, 'physical_adjust', $ingredientId, $id,
                $difference, $lot['unit_of_measure'], $before, $after,
                'capstone_demo_rounding', $id, $warehouseUserId, $gmUserId,
                'CAPSTONE DEMO ledger correction: fractional counted piece removed; not a physical loss claim.',
            ]);
            $roundedByIngredient[$ingredientId] = ($roundedByIngredient[$ingredientId] ?? 0) + $difference;
            $rounded[] = ['code' => $lot['ingredient_code'], 'batch_id' => $id, 'before' => $before, 'after' => $after];
        }

        $masters = $db->query("SELECT i.id, i.ingredient_code, i.current_stock, i.unit_of_measure,
                COALESCE(SUM(CASE WHEN ib.status IN ('available','partially_used','quarantine','expired')
                    AND ib.remaining_quantity > 0 THEN ib.remaining_quantity ELSE 0 END),0) AS accounted
            FROM ingredients i LEFT JOIN ingredient_batches ib ON ib.ingredient_id = i.id
            WHERE i.is_active = 1 GROUP BY i.id ORDER BY i.id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
        $setStock = $db->prepare('UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?');
        foreach ($masters as $master) {
            $id = (int) $master['id'];
            $before = (float) $master['current_stock'];
            $after = (float) $master['accounted'];
            if ($before + 0.005 < $after) {
                throw new RuntimeException("Unexplained batch surplus for {$master['ingredient_code']}; cleanup stopped");
            }
            if (abs($before - $after) < 0.005) continue;
            $phantom = round($before - $after - ($retiredByIngredient[$id] ?? 0)
                - ($roundedByIngredient[$id] ?? 0), 2);
            if ($phantom < -0.005) throw new RuntimeException("Unexpected stock movement for {$master['ingredient_code']}");
            $setStock->execute([$after, $id]);
            if ($phantom > 0.005) {
                $transaction->execute([
                    'DEMO-RECON-' . $id, 'physical_adjust', $id, null,
                    $phantom, $master['unit_of_measure'], $before, $after,
                    'capstone_demo_reconciliation', $id, $warehouseUserId, $gmUserId,
                    'CAPSTONE DEMO ledger correction: unsupported summary excess removed; no physical count claimed.',
                ]);
            }
            $reconciled[] = ['code' => $master['ingredient_code'], 'before' => $before,
                'after' => $after, 'unsupported_excess' => max(0, $phantom)];
        }

        // Clearly label intentional demo materials without suggesting that
        // an unverified wrapper or label has become available for production.
        $rename = $db->prepare('UPDATE ingredients SET ingredient_name = ?, updated_at = NOW()
            WHERE ingredient_code = ? AND ingredient_name = ?');
        $names = [
            'TST-LBL-CHO-1L' => '1000 mL Chocolate Milk Label [CHO-1L]',
            'TST-PKG-BTL-1000' => '1000 mL Food-Grade Bottle',
            'TST-LBL-CRM-1L' => '1000 mL Fresh Cream Label [CRM-1L]',
            'TST-LBL-FMK-1L' => '1000 mL Fresh Milk Label [FMK-1L]',
            'TST-PKG-BTL-250' => '250 mL Food-Grade Bottle',
            'MOCK-PKG-CAP-28' => '28 mm Bottle Cap',
            'TST-PKG-CAP-28' => '28 mm Tamper-Evident Bottle Cap',
            'TST-PKG-BTL-500' => '500 mL Food-Grade Bottle',
            'TST-LBL-FMK-500' => '500 mL Fresh Milk Label [FMK-500]',
            'TST-PKG-FILM' => 'Clear Shrink Film Roll',
            'ING-0113' => 'Avocado Flavored Milk 250 mL Label',
            'ING-0106' => 'Choco Flavored Milk 250 mL Label',
            'ING-0111' => 'Durian Flavored Milk 250 mL Label',
            'ING-0108' => 'Melon Flavored Milk 250 mL Label',
            'ING-0110' => 'Strawberry Flavored Milk 250 mL Label',
            'ING-0115' => 'Ube Flavored Milk 250 mL Label',
            'ING-0097' => 'Yogurt Durian Flavor 250 mL Label',
            'ING-0101' => 'Yogurt Pineapple Flavor 250 mL Label',
            'ING-0100' => 'Yogurt Strawberry Flavor 250 mL Label',
        ];
        $nameRows = $db->query('SELECT ingredient_code, ingredient_name FROM ingredients WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($nameRows as $row) {
            $code = $row['ingredient_code'];
            if (!isset($names[$code]) || $names[$code] === $row['ingredient_name']) continue;
            $rename->execute([$names[$code], $code, $row['ingredient_name']]);
            if ($rename->rowCount() === 1) $renamed[] = $code;
        }

        $demoTargets = ['ING-005' => 58, 'ING-003' => 80, 'ING-0097' => 400];
        $demoMaster = $db->prepare('SELECT id, ingredient_name, unit_of_measure, current_stock,
                shelf_life_days, is_perishable, unit_cost FROM ingredients
            WHERE ingredient_code = ? AND is_active = 1 FOR UPDATE');
        $addLot = $db->prepare("INSERT INTO ingredient_batches
            (batch_code, ingredient_id, quantity, remaining_quantity, unit_cost,
             supplier_batch_no, received_date, expiry_date, qc_status, received_by, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, 'approved', ?, 'available', ?)");
        $addStock = $db->prepare('UPDATE ingredients SET current_stock = current_stock + ?, updated_at = NOW() WHERE id = ?');
        foreach ($demoTargets as $code => $target) {
            $demoMaster->execute([$code]);
            $master = $demoMaster->fetch(PDO::FETCH_ASSOC);
            if (!$master) throw new RuntimeException("Demo ingredient {$code} is missing");
            $before = (float) $master['current_stock'];
            $quantity = round(max(0, $target - $before), 2);
            if ($quantity < 0.005) continue;
            $batchCode = 'DEMO-RAW-' . date('Ymd') . '-' . $code;
            $existing = $db->prepare('SELECT id FROM ingredient_batches WHERE batch_code = ?');
            $existing->execute([$batchCode]);
            if ($existing->fetchColumn()) throw new RuntimeException("Demo lot {$batchCode} already exists but stock remains below target");
            $expiry = (int) $master['is_perishable'] === 1
                ? date('Y-m-d', strtotime('+' . max(1, (int) $master['shelf_life_days']) . ' days')) : null;
            $addLot->execute([
                $batchCode, (int) $master['id'], $quantity, $quantity,
                (float) $master['unit_cost'], 'DEMO-OPEN-' . date('Ymd') . '-' . $code,
                $expiry, $warehouseUserId,
                'CAPSTONE DEMO STOCK ONLY: simulated opening balance, not physical stock or a supplier delivery; simulated QC approval.',
            ]);
            $batchId = (int) $db->lastInsertId();
            $addStock->execute([$quantity, (int) $master['id']]);
            $transaction->execute([
                'DEMO-OPEN-' . $batchId, 'physical_adjust', (int) $master['id'], $batchId,
                $quantity, $master['unit_of_measure'], $before, $before + $quantity,
                'capstone_demo_opening', $batchId, $warehouseUserId, $gmUserId,
                'CAPSTONE DEMO simulated opening stock; no physical count or supplier delivery claimed.',
            ]);
            $replenished[] = ['code' => $code, 'quantity' => $quantity, 'expiry' => $expiry];
        }
        if ($mode === 'apply_raw') $db->commit();
        else $db->rollBack();
        echo json_encode(['mode' => $mode, 'retired_expired_lots' => $retired,
            'rounded_piece_lots' => $rounded, 'reconciled' => $reconciled,
            'renamed_materials' => $renamed, 'synthetic_replenishment' => $replenished]);
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        http_response_code(500);
        error_log('Defense raw cleanup: ' . $error->getMessage());
        echo json_encode(['error' => $error->getMessage()]);
    }
    exit;
}
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
