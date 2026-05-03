<?php
/**
 * Highland Fresh - Packaging API
 *
 * Step 2 of Production: Taking a completed production run's output
 * and recording how many of each bottle/size were packed into FG inventory.
 *
 * GET  ?action=ready_batches  - List completed production runs ready to package
 * GET  ?action=logs           - List past packaging runs
 * GET  ?id=X                  - Single packaging run details
 * POST (action=package)       - Create packaging run → adds to finished_goods_inventory
 *
 * @version 1.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Only production_staff and GM can access packaging
$currentUser = Auth::requireRole(['production_staff', 'general_manager']);

try {
    $db = Database::getInstance()->getConnection();

    switch ($requestMethod) {

        // ─────────────────────────────────────────────────────────────────
        // GET
        // ─────────────────────────────────────────────────────────────────
        case 'GET':
            $action = getParam('action');
            $id     = getParam('id');

            // ── List completed runs that haven't been fully packaged ──────
            if ($action === 'ready_batches') {
                $stmt = $db->prepare("
                    SELECT
                        pr.id             AS run_id,
                        pr.run_code,
                        pr.planned_quantity,
                        pr.actual_quantity,
                        pr.output_breakdown,
                        pr.milk_liters_used,
                        pr.end_datetime,
                        pr.status         AS run_status,
                        mr.id             AS recipe_id,
                        mr.recipe_code,
                        mr.product_name,
                        mr.product_type,
                        mr.yield_unit,
                        pb.id             AS batch_id,
                        pb.batch_code,
                        pb.qc_status,
                        pb.actual_yield,
                        pb.expiry_date,
                        pb.manufacturing_date,
                        qbr.id            AS qc_release_id,
                        qbr.release_decision,
                        -- how many already packaged from this run
                        COALESCE((
                            SELECT SUM(pkr.total_pieces_packaged)
                            FROM packaging_runs pkr
                            WHERE pkr.production_run_id = pr.id
                              AND pkr.status = 'completed'
                        ), 0) AS already_packaged
                    FROM production_runs pr
                    JOIN master_recipes  mr ON pr.recipe_id = mr.id
                    LEFT JOIN production_batches pb ON pb.run_id = pr.id
                    LEFT JOIN qc_batch_release qbr ON qbr.batch_id = pb.id
                                                  AND qbr.release_decision = 'approved'
                    WHERE pr.status = 'completed'
                    ORDER BY pr.end_datetime DESC
                    LIMIT 50
                ");
                $stmt->execute();
                $rows = $stmt->fetchAll();

                // Parse JSON output_breakdown
                foreach ($rows as &$row) {
                    if ($row['output_breakdown']) {
                        $row['output_breakdown'] = json_decode($row['output_breakdown'], true);
                    }
                    $row['remaining_to_package'] = max(0, (int)$row['actual_quantity'] - (int)$row['already_packaged']);
                    $row['fully_packaged']        = $row['remaining_to_package'] <= 0;
                }
                unset($row);

                Response::success(['batches' => $rows], 'Ready batches retrieved');
            }

            // ── List past packaging runs ──────────────────────────────────
            if ($action === 'logs' || !$id) {
                $limit  = (int) getParam('limit', 20);
                $page   = (int) getParam('page', 1);
                $offset = ($page - 1) * $limit;

                $stmt = $db->prepare("
                    SELECT
                        pkr.id, pkr.packaging_code, pkr.batch_code,
                        pkr.product_type, pkr.total_pieces_packaged,
                        pkr.packaging_date, pkr.status, pkr.notes,
                        pkr.created_at,
                        CONCAT(u.first_name,' ',u.last_name) AS packaged_by_name,
                        pr.run_code
                    FROM packaging_runs pkr
                    LEFT JOIN users u ON pkr.packaged_by = u.id
                    LEFT JOIN production_runs pr ON pkr.production_run_id = pr.id
                    ORDER BY pkr.created_at DESC
                    LIMIT {$limit} OFFSET {$offset}
                ");
                $stmt->execute();
                $logs = $stmt->fetchAll();

                $countStmt = $db->query("SELECT COUNT(*) FROM packaging_runs");
                $total     = $countStmt->fetchColumn();

                Response::paginated($logs, $total, $page, $limit, 'Packaging logs retrieved');
            }

            // ── Single packaging run ──────────────────────────────────────
            if ($id) {
                $stmt = $db->prepare("
                    SELECT pkr.*,
                        CONCAT(u.first_name,' ',u.last_name) AS packaged_by_name,
                        pr.run_code
                    FROM packaging_runs pkr
                    LEFT JOIN users u ON pkr.packaged_by = u.id
                    LEFT JOIN production_runs pr ON pkr.production_run_id = pr.id
                    WHERE pkr.id = ?
                ");
                $stmt->execute([$id]);
                $pkgRun = $stmt->fetch();
                if (!$pkgRun) Response::notFound('Packaging run not found');

                // Items
                $itemStmt = $db->prepare("
                    SELECT pri.*, p.product_code
                    FROM packaging_run_items pri
                    LEFT JOIN products p ON pri.product_id = p.id
                    WHERE pri.packaging_run_id = ?
                    ORDER BY pri.id
                ");
                $itemStmt->execute([$id]);
                $pkgRun['items'] = $itemStmt->fetchAll();

                Response::success($pkgRun, 'Packaging run retrieved');
            }
            break;

        // ─────────────────────────────────────────────────────────────────
        // POST  — Create packaging run
        // ─────────────────────────────────────────────────────────────────
        case 'POST':
            $productionRunId = getParam('production_run_id');
            $batchId         = getParam('batch_id');
            $notes           = trim(getParam('notes', ''));
            $items           = getParam('items', []); // [{product_id, product_name, product_variant, size_ml, unit_measure, quantity, expiry_date}]

            // Validation
            $errors = [];
            if (!$productionRunId) $errors['production_run_id'] = 'Production run is required';
            if (empty($items))     $errors['items']             = 'At least one packaged item is required';

            if (!empty($errors)) Response::validationError($errors);

            // Verify the production run exists and is completed
            $runStmt = $db->prepare("
                SELECT pr.*, mr.product_type, mr.product_name AS recipe_product_name,
                       mr.milk_type_id, mr.yield_unit
                FROM production_runs pr
                JOIN master_recipes mr ON pr.recipe_id = mr.id
                WHERE pr.id = ?
            ");
            $runStmt->execute([$productionRunId]);
            $run = $runStmt->fetch();

            if (!$run) Response::notFound('Production run not found');
            if ($run['status'] !== 'completed') {
                Response::validationError(['production_run_id' => 'Production run must be completed before packaging']);
            }

            // Look up QC batch release for this batch (if a batch is linked)
            $qcReleaseId = null;
            if ($batchId) {
                $qcStmt = $db->prepare("
                    SELECT id, release_decision
                    FROM qc_batch_release
                    WHERE batch_id = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $qcStmt->execute([$batchId]);
                $qcRelease = $qcStmt->fetch();

                if ($qcRelease) {
                    if ($qcRelease['release_decision'] !== 'approved') {
                        Response::validationError([
                            'batch_id' => 'QC has not approved this batch. Current QC decision: ' . $qcRelease['release_decision']
                        ]);
                    }
                    $qcReleaseId = (int)$qcRelease['id'];
                }
                // If no QC release record exists yet, allow packaging but qc_release_id stays NULL
            }

            // Validate item quantities
            $totalPiecesNow = 0;
            foreach ($items as $item) {
                $qty = (int)($item['quantity'] ?? 0);
                if ($qty <= 0) {
                    $errors['items'] = 'All quantities must be greater than 0';
                    break;
                }
                $totalPiecesNow += $qty;
            }
            if (!empty($errors)) Response::validationError($errors);

            // Check we're not packaging more than what was actually produced
            $alreadyPackaged = 0;
            $apStmt = $db->prepare("
                SELECT COALESCE(SUM(total_pieces_packaged),0)
                FROM packaging_runs
                WHERE production_run_id = ? AND status='completed'
            ");
            $apStmt->execute([$productionRunId]);
            $alreadyPackaged = (int)$apStmt->fetchColumn();

            $available = (int)$run['actual_quantity'] - $alreadyPackaged;
            if ($totalPiecesNow > $available) {
                Response::validationError([
                    'items' => "Total pieces ({$totalPiecesNow}) exceeds available output ({$available}). Already packaged: {$alreadyPackaged}."
                ]);
            }

            // Generate packaging code
            $today = date('Ymd');
            $codeStmt = $db->prepare("SELECT COUNT(*) FROM packaging_runs WHERE packaging_code LIKE ?");
            $codeStmt->execute(["PKG-{$today}-%"]);
            $count          = (int)$codeStmt->fetchColumn() + 1;
            $packagingCode  = "PKG-{$today}-" . str_pad($count, 3, '0', STR_PAD_LEFT);
            $batchCode      = $batchId ? null : null;

            // Get batch_code if batch_id supplied
            if ($batchId) {
                $bcStmt = $db->prepare("SELECT batch_code FROM production_batches WHERE id = ?");
                $bcStmt->execute([$batchId]);
                $batchCode = $bcStmt->fetchColumn() ?: null;
            }

            $db->beginTransaction();
            try {
                // Insert packaging run header
                $ins = $db->prepare("
                    INSERT INTO packaging_runs
                        (packaging_code, production_run_id, batch_id, batch_code,
                         product_type, total_pieces_packaged, packaging_date,
                         packaged_by, notes, status)
                    VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, 'completed')
                ");
                $ins->execute([
                    $packagingCode,
                    $productionRunId,
                    $batchId ?: null,
                    $batchCode,
                    $run['product_type'],
                    $totalPiecesNow,
                    $currentUser['user_id'],
                    $notes
                ]);
                $packagingRunId = $db->lastInsertId();

                // Insert items + create FG inventory records
                $itemIns = $db->prepare("
                    INSERT INTO packaging_run_items
                        (packaging_run_id, product_id, product_name, product_variant,
                         size_ml, unit_measure, quantity, fg_inventory_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($items as $item) {
                    $productId   = ($item['product_id'] && $item['product_id'] != 'null') ? (int)$item['product_id'] : null;
                    $productName = $item['product_name'] ?? $run['recipe_product_name'];
                    $qty         = (int)$item['quantity'];
                    $sizeMl      = isset($item['size_ml']) ? (float)$item['size_ml'] : null;
                    $unitMeasure = $item['unit_measure'] ?? 'ml';
                    $variant     = $item['product_variant'] ?? null;
                    $expiryDate  = $item['expiry_date'] ?? date('Y-m-d', strtotime('+7 days'));

                    // Map product_type to FG inventory ENUM values
                    $productTypeMap = [
                        'bottled_milk'  => 'bottled_milk',
                        'pasteurized_milk' => 'bottled_milk',
                        'flavored_milk' => 'flavored_milk',
                        'yogurt'        => 'yogurt',
                        'cheese'        => 'cheese',
                        'butter'        => 'butter',
                        'cream'         => 'cream',
                        'milk_bar'      => 'milk_bar',
                    ];
                    $fgProductType = $productTypeMap[$run['product_type']] ?? 'bottled_milk';

                    // Create finished_goods_inventory record
                    // Write to BOTH old-style (quantity_available) and new-style
                    // (pieces_available) columns for full compatibility.
                    $fgIns = $db->prepare("
                        INSERT INTO finished_goods_inventory
                            (batch_id, qc_release_id, product_id, milk_type_id,
                             product_name, product_type, product_variant, variant,
                             size_ml, quantity, remaining_quantity, quantity_available,
                             quantity_pieces, pieces_available, boxes_available,
                             unit, manufacturing_date, expiry_date,
                             received_by, received_at, last_movement_at,
                             status, notes)
                        VALUES (?, ?, ?, ?,
                                ?, ?, ?, ?,
                                ?, ?, ?, ?,
                                ?, ?, 0,
                                'pcs', CURDATE(), ?,
                                ?, NOW(), NOW(),
                                'available', ?)
                    ");
                    $fgIns->execute([
                        $batchId ?: null,
                        $qcReleaseId,
                        $productId,
                        $run['milk_type_id'] ?: null,
                        $productName,
                        $fgProductType,
                        $variant,
                        $variant,
                        $sizeMl,
                        $qty, $qty, $qty,  // quantity, remaining_quantity, quantity_available
                        $qty, $qty,        // quantity_pieces, pieces_available
                        $expiryDate,
                        $currentUser['user_id'],
                        "From packaging run {$packagingCode} · batch {$batchCode}"
                    ]);
                    $fgInventoryId = $db->lastInsertId();

                    $itemIns->execute([
                        $packagingRunId,
                        $productId, $productName, $variant,
                        $sizeMl, $unitMeasure, $qty, $fgInventoryId
                    ]);
                }

                $db->commit();

                Response::created([
                    'id'             => $packagingRunId,
                    'packaging_code' => $packagingCode,
                    'total_packaged' => $totalPiecesNow,
                    'items_count'    => count($items),
                    'status'         => 'completed'
                ], "Packaging complete! {$totalPiecesNow} units added to Finished Goods inventory.");

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    error_log("Packaging API Error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
