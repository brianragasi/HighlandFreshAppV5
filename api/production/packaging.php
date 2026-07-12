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
require_once dirname(__DIR__) . '/helpers/pack_uom.php';

// Only production_staff and GM can access packaging
$currentUser = Auth::requireRole(['production_staff', 'general_manager']);

/**
 * Resolve FG product master for a packaging line.
 * Frontend often sends product_id=null when accepting a yield plan — we must
 * still link the correct SKU so pack config (1 box = N bottles) works.
 *
 * Priority:
 *  1) Explicit product_id on the item
 *  2) Active product matching unit_size ≈ size_ml (prefer same recipe product / name)
 *  3) Recipe product_id from the production run
 *
 * @return array|null product row or null
 */
function resolvePackagingProduct(PDO $db, $itemProductId, $sizeMl, $productName, $recipeProductId, $baseProductId = null) {
    // 1) Explicit SKU id
    if (!empty($itemProductId) && $itemProductId !== 'null') {
        try {
            $stmt = $db->prepare("
                SELECT id, product_code, product_name, category, variant,
                       unit_size, unit_measure, base_unit, box_unit, base_product_id,
                       COALESCE(pieces_per_box, 1) AS pieces_per_box, is_active
                FROM products WHERE id = ? LIMIT 1
            ");
            $stmt->execute([(int) $itemProductId]);
        } catch (Throwable $e) {
            $stmt = $db->prepare("
                SELECT id, product_code, product_name, category, variant,
                       unit_size, unit_measure, base_unit, box_unit,
                       COALESCE(pieces_per_box, 1) AS pieces_per_box, is_active
                FROM products WHERE id = ? LIMIT 1
            ");
            $stmt->execute([(int) $itemProductId]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }

    $size = $sizeMl !== null && $sizeMl !== '' ? (float) $sizeMl : null;
    $name = trim((string) $productName);
    $recipeId = !empty($recipeProductId) ? (int) $recipeProductId : null;
    $baseId = !empty($baseProductId) ? (int) $baseProductId : null;

    // 1b) Prefer SKUs under the same base liquid product (bulk batch architecture)
    if ($baseId && $size !== null && $size > 0) {
        try {
            $stmt = $db->prepare("
                SELECT id, product_code, product_name, category, variant,
                       unit_size, unit_measure, base_unit, box_unit, base_product_id,
                       COALESCE(pieces_per_box, 1) AS pieces_per_box, is_active
                FROM products
                WHERE base_product_id = ? AND is_active = 1
                  AND ABS(unit_size - ?) < 0.01
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute([$baseId, $size]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        } catch (Throwable $e) {
            // base_product_id column may be missing on legacy DBs
        }
    }

    // 2) Match by size among active products
    if ($size !== null && $size > 0) {
        // Prefer recipe product when its unit_size matches this bottle size
        if ($recipeId) {
            $stmt = $db->prepare("
                SELECT id, product_code, product_name, category, variant,
                       unit_size, unit_measure, base_unit, box_unit,
                       COALESCE(pieces_per_box, 1) AS pieces_per_box, is_active
                FROM products
                WHERE id = ? AND is_active = 1
                  AND ABS(unit_size - ?) < 0.01
                LIMIT 1
            ");
            $stmt->execute([$recipeId, $size]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        // Match by size + name fragment (e.g. "Fresh Milk" + 1000 → Fresh Milk 1L)
        if ($name !== '') {
            $stmt = $db->prepare("
                SELECT id, product_code, product_name, category, variant,
                       unit_size, unit_measure, base_unit, box_unit,
                       COALESCE(pieces_per_box, 1) AS pieces_per_box, is_active
                FROM products
                WHERE is_active = 1
                  AND ABS(unit_size - ?) < 0.01
                  AND (
                    product_name LIKE CONCAT('%', ?, '%')
                    OR ? LIKE CONCAT('%', product_name, '%')
                    OR SUBSTRING_INDEX(product_name, ' ', 2) = SUBSTRING_INDEX(?, ' ', 2)
                  )
                ORDER BY pieces_per_box DESC, id ASC
                LIMIT 1
            ");
            // Use first two words of name for matching when full name is short
            $nameKey = $name;
            $stmt->execute([$size, $nameKey, $nameKey, $nameKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        // Any active product with that unit size (last resort for size)
        $stmt = $db->prepare("
            SELECT id, product_code, product_name, category, variant,
                   unit_size, unit_measure, base_unit, box_unit,
                   COALESCE(pieces_per_box, 1) AS pieces_per_box, is_active
            FROM products
            WHERE is_active = 1 AND ABS(unit_size - ?) < 0.01
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute([$size]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }

    // 3) Recipe product even if size differs (better than null)
    if ($recipeId) {
        $stmt = $db->prepare("
            SELECT id, product_code, product_name, category, variant,
                   unit_size, unit_measure, base_unit, box_unit,
                   COALESCE(pieces_per_box, 1) AS pieces_per_box, is_active
            FROM products WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$recipeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }

    return null;
}

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
            // NOTE: Do NOT join qc_batch_release here — that table can block under
            // lock contention. QC state is already denormalized on production_batches.qc_status.
            if ($action === 'ready_batches') {
                // bulk-batch fields optional (base_product_id / bulk_yield_liters)
                $bulkCols = ', NULL AS base_product_id, NULL AS bulk_yield_liters, NULL AS base_product_name';
                $bulkJoin = '';
                try {
                    $db->query('SELECT base_product_id, bulk_yield_liters FROM master_recipes LIMIT 0');
                    $db->query('SELECT id FROM base_products LIMIT 0');
                    $bulkCols = ', mr.base_product_id, mr.bulk_yield_liters, bp.name AS base_product_name';
                    $bulkJoin = ' LEFT JOIN base_products bp ON bp.id = mr.base_product_id ';
                } catch (Throwable $e) {
                    // legacy schema
                }

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
                        mr.product_id     AS product_id,
                        mr.product_id     AS recipe_product_id,
                        mr.yield_unit
                        {$bulkCols},
                        pb.id             AS batch_id,
                        pb.batch_code,
                        pb.qc_status,
                        pb.actual_yield,
                        pb.expiry_date,
                        pb.manufacturing_date,
                        COALESCE(pkg.already_packaged, 0) AS already_packaged
                    FROM production_runs pr
                    JOIN master_recipes  mr ON pr.recipe_id = mr.id
                    {$bulkJoin}
                    LEFT JOIN production_batches pb ON pb.run_id = pr.id
                    LEFT JOIN (
                        SELECT production_run_id, SUM(total_pieces_packaged) AS already_packaged
                        FROM packaging_runs
                        WHERE status = 'completed'
                        GROUP BY production_run_id
                    ) pkg ON pkg.production_run_id = pr.id
                    WHERE pr.status = 'completed'
                    ORDER BY pr.end_datetime DESC
                    LIMIT 50
                ");
                $stmt->execute();
                $rows = $stmt->fetchAll();

                // Parse JSON output_breakdown + packaging flags
                foreach ($rows as &$row) {
                    if ($row['output_breakdown']) {
                        $decoded = json_decode($row['output_breakdown'], true);
                        $row['output_breakdown'] = is_array($decoded) ? $decoded : null;
                    }
                    // Map batch QC status for UI (release_decision is derived, not joined)
                    $qc = $row['qc_status'] ?? 'pending';
                    if ($row['batch_id'] === null) {
                        $qc = 'no_batch';
                    }
                    $row['qc_status'] = $qc;
                    $row['release_decision'] = ($qc === 'released') ? 'approved'
                        : (($qc === 'rejected') ? 'rejected' : null);
                    $row['qc_release_id'] = null;
                    $row['qc_cleared'] = ($qc === 'released');
                    $row['remaining_to_package'] = max(0, (int)$row['actual_quantity'] - (int)$row['already_packaged']);
                    $row['fully_packaged']        = $row['remaining_to_package'] <= 0;
                }
                unset($row);

                Response::success([
                    'batches' => $rows,
                    'note' => 'Finished-goods packaging requires production_batches.qc_status = released. Production-stage "packaging" on a run is separate from FG packaging.',
                ], 'Ready batches retrieved');
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
            try {
                $runStmt = $db->prepare("
                    SELECT pr.*, mr.product_type, mr.product_name AS recipe_product_name,
                           mr.product_id AS recipe_product_id,
                           mr.base_product_id, mr.bulk_yield_liters,
                           mr.milk_type_id, mr.yield_unit
                    FROM production_runs pr
                    JOIN master_recipes mr ON pr.recipe_id = mr.id
                    WHERE pr.id = ?
                ");
                $runStmt->execute([$productionRunId]);
            } catch (Throwable $e) {
                $runStmt = $db->prepare("
                    SELECT pr.*, mr.product_type, mr.product_name AS recipe_product_name,
                           mr.product_id AS recipe_product_id,
                           mr.milk_type_id, mr.yield_unit
                    FROM production_runs pr
                    JOIN master_recipes mr ON pr.recipe_id = mr.id
                    WHERE pr.id = ?
                ");
                $runStmt->execute([$productionRunId]);
            }
            $run = $runStmt->fetch();

            if (!$run) Response::notFound('Production run not found');
            if ($run['status'] !== 'completed') {
                Response::validationError(['production_run_id' => 'Production run must be completed before packaging']);
            }

            // ── HARD QC GATE ──────────────────────────────────────────────
            // Finished-goods packaging is NEVER allowed without QC release.
            // Resolve batch by id or by production_run_id (do not skip when batch_id is 0/null).
            $qcReleaseId = null;
            $batchRow = null;

            if ($batchId) {
                $qcStmt = $db->prepare("
                    SELECT id, qc_status, batch_code, run_id
                    FROM production_batches
                    WHERE id = ?
                    LIMIT 1
                ");
                $qcStmt->execute([(int) $batchId]);
                $batchRow = $qcStmt->fetch(PDO::FETCH_ASSOC);
                if ($batchRow && (int) ($batchRow['run_id'] ?? 0) !== (int) $productionRunId) {
                    Response::validationError([
                        'batch_id' => 'Batch does not belong to this production run',
                    ]);
                }
            }

            if (!$batchRow) {
                $qcStmt = $db->prepare("
                    SELECT id, qc_status, batch_code, run_id
                    FROM production_batches
                    WHERE run_id = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $qcStmt->execute([(int) $productionRunId]);
                $batchRow = $qcStmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$batchRow) {
                Response::validationError([
                    'batch_id' => 'No production batch found for this run. Complete the run so a batch is created for QC, then wait for QC release before packaging.',
                ]);
            }

            $batchId = (int) $batchRow['id'];
            $qcStatus = $batchRow['qc_status'] ?? 'pending';

            if ($qcStatus === 'rejected') {
                Response::validationError([
                    'batch_id' => 'QC has rejected batch ' . ($batchRow['batch_code'] ?? $batchId) . '. Packaging is not allowed.',
                ]);
            }

            if ($qcStatus !== 'released') {
                Response::validationError([
                    'batch_id' => 'QC has not released this batch yet (status: ' . $qcStatus . '). '
                        . 'Production cannot package into finished goods until a QC officer releases the batch.',
                    'qc_status' => $qcStatus,
                    'batch_code' => $batchRow['batch_code'] ?? null,
                ]);
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
                    $qty         = (int)$item['quantity'];
                    $sizeMl      = isset($item['size_ml']) ? (float)$item['size_ml'] : null;
                    $unitMeasure = $item['unit_measure'] ?? 'ml';
                    $variant     = $item['product_variant'] ?? null;
                    $expiryDate  = $item['expiry_date'] ?? date('Y-m-d', strtotime('+7 days'));
                    $rawName     = $item['product_name'] ?? $run['recipe_product_name'];

                    // Always resolve to product master (pack rules + correct name)
                    // Prefer SKUs under the same base liquid product when available
                    $resolved = resolvePackagingProduct(
                        $db,
                        $item['product_id'] ?? null,
                        $sizeMl,
                        $rawName,
                        $run['recipe_product_id'] ?? null,
                        $run['base_product_id'] ?? $item['base_product_id'] ?? null
                    );

                    $productId   = $resolved ? (int) $resolved['id'] : null;
                    $productName = $resolved ? $resolved['product_name'] : $rawName;
                    // Pack UOM always from product master (unified helper)
                    $packCfg = $resolved
                        ? hf_pack_config_from_row($resolved)
                        : hf_pack_config_from_row(null);
                    $piecesPerBox = max(1, (int)$packCfg['units_per_pack']);
                    $baseUnit    = $packCfg['base_unit'];
                    $boxUnit     = $packCfg['pack_name'];

                    // Prefer product unit size when packaging size missing
                    if (($sizeMl === null || $sizeMl <= 0) && $resolved && !empty($resolved['unit_size'])) {
                        $sizeMl = (float) $resolved['unit_size'];
                        $unitMeasure = $resolved['unit_measure'] ?: $unitMeasure;
                    }

                    // Clearer variant when empty
                    if ($variant === null || $variant === '') {
                        $variant = $resolved['variant'] ?? null;
                        if (!$variant && $sizeMl) {
                            $variant = rtrim(rtrim(number_format($sizeMl, 0, '.', ''), '0'), '.') . ($unitMeasure ?: 'ml');
                        }
                    }

                    // Map product_type / category to FG inventory ENUM values
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
                    $cat = $resolved['category'] ?? $run['product_type'] ?? '';
                    $fgProductType = $productTypeMap[$cat] ?? ($productTypeMap[$run['product_type']] ?? 'bottled_milk');

                    $qtyBoxes = $piecesPerBox > 1 ? intdiv($qty, $piecesPerBox) : 0;
                    $qtyLoose = $piecesPerBox > 1 ? ($qty % $piecesPerBox) : $qty;
                    if ($piecesPerBox <= 1) {
                        $qtyBoxes = 0;
                        $qtyLoose = $qty;
                    }

                    $packNote = $piecesPerBox > 1
                        ? "pack 1 {$boxUnit} = {$piecesPerBox} {$baseUnit}(s)"
                        : 'no pack size on SKU';
                    $linkNote = $productId
                        ? "SKU #{$productId} {$productName}"
                        : 'UNLINKED (no matching product master)';

                    // Create finished_goods_inventory record
                    // Base qty columns + multi-unit from product pack config.
                    // chiller_id left NULL until warehouse put-away.
                    $fgIns = $db->prepare("
                        INSERT INTO finished_goods_inventory
                            (batch_id, qc_release_id, product_id, milk_type_id,
                             product_name, product_type, product_variant, variant,
                             size_ml, quantity, remaining_quantity, quantity_available,
                             quantity_boxes, quantity_pieces, boxes_available, pieces_available,
                             unit, manufacturing_date, expiry_date,
                             received_by, received_at, last_movement_at,
                             status, notes)
                        VALUES (?, ?, ?, ?,
                                ?, ?, ?, ?,
                                ?, ?, ?, ?,
                                ?, ?, ?, ?,
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
                        $qty, $qty, $qty,  // quantity, remaining_quantity, quantity_available (base)
                        $qtyBoxes, $qtyLoose, $qtyBoxes, $qtyLoose,
                        $expiryDate,
                        $currentUser['user_id'],
                        "From packaging run {$packagingCode} · batch {$batchCode} · awaiting put-away · {$linkNote} · {$packNote}"
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
