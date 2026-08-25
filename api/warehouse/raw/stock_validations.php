<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/ingredient_stock_helpers.php';
require_once dirname(__DIR__, 2) . '/helpers/stock_validation_support.php';
require_once dirname(__DIR__, 2) . '/helpers/procurement_notifications.php';

$currentUser = Auth::requireRole(['warehouse_raw', 'purchaser', 'general_manager']);
$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    ensureStockValidationSupport($db);

    if ($requestMethod === 'GET' && $action === 'list') {
        listStockValidations($db, $currentUser);
    } elseif ($requestMethod === 'GET' && $action === 'inbox') {
        stockValidationInbox($db, $currentUser);
    } elseif ($requestMethod === 'GET' && $action === 'open_item_refs') {
        openStockValidationItemRefs($db, $currentUser);
    } elseif ($requestMethod === 'GET' && $action === 'decisions') {
        listPurchasingDecisions($db, $currentUser);
    } elseif ($requestMethod === 'POST' && $action === 'validate') {
        createStockValidation($db, $currentUser);
    } elseif ($requestMethod === 'PUT' && $action === 'decide') {
        savePurchasingDecision($db, $currentUser);
    } else {
        Response::error('Action not available', 405);
    }
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400);
} catch (Throwable $e) {
    error_log('Stock validation error: ' . $e->getMessage());
    Response::error('Could not save the stock confirmation. Please try again.', 500);
}

function openStockValidationItemRefs(PDO $db, array $currentUser): void {
    if (!in_array(($currentUser['role'] ?? ''), ['warehouse_raw', 'general_manager'], true)) {
        Response::error('Only Warehouse Raw can view current stock confirmations', 403);
    }
    $stmt = $db->query("
        SELECT
            CASE WHEN svi.ingredient_id IS NOT NULL THEN 'ingredient' ELSE 'mro' END AS item_type,
            COALESCE(svi.ingredient_id, svi.mro_item_id) AS item_id,
            sv.id AS validation_id,
            sv.validation_number,
            sv.status,
            svi.purchasing_decision,
            svi.deferred_until,
            svi.purchasing_decision_reason,
            sv.created_at
        FROM stock_validation_items svi
        JOIN stock_validations sv ON sv.id = svi.stock_validation_id
        LEFT JOIN ingredients i ON i.id = svi.ingredient_id
        LEFT JOIN mro_items m ON m.id = svi.mro_item_id
        WHERE svi.is_queue_active = 1
          AND svi.quantity_needed > COALESCE((
              SELECT SUM(svip.quantity)
              FROM stock_validation_item_po svip
              JOIN purchase_orders po ON po.id = svip.po_id
              WHERE svip.stock_validation_item_id = svi.id
                AND po.status NOT IN ('cancelled','rejected')
          ), 0) + 0.0001
          AND (
              sv.status IN ('open','partially_ordered')
              OR (
                  svi.purchasing_decision = 'closed_without_order'
                  AND svi.purchasing_decided_at IS NOT NULL
                  AND svi.purchasing_decided_at >= COALESCE(i.updated_at, m.updated_at, svi.created_at)
              )
          )
        ORDER BY sv.created_at DESC
    ");
    Response::success($stmt->fetchAll(PDO::FETCH_ASSOC), 'Current stock confirmations retrieved');
}

function listStockValidations(PDO $db, array $currentUser): void {
    $where = '';
    $params = [];
    if (($currentUser['role'] ?? '') === 'warehouse_raw') {
        $where = 'WHERE sv.validated_by = ?';
        $params[] = (int) $currentUser['user_id'];
    }
    $stmt = $db->prepare("
        SELECT sv.*, u.full_name AS validated_by_name,
               COUNT(svi.id) AS item_count,
               COALESCE(SUM(svi.quantity_needed), 0) AS total_quantity_needed
        FROM stock_validations sv
        LEFT JOIN users u ON u.id = sv.validated_by
        LEFT JOIN stock_validation_items svi ON svi.stock_validation_id = sv.id AND svi.is_queue_active = 1
        $where
        GROUP BY sv.id
        ORDER BY sv.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    Response::success($stmt->fetchAll(PDO::FETCH_ASSOC), 'Stock confirmations retrieved');
}

function stockValidationInbox(PDO $db, array $currentUser): void {
    if (!in_array(($currentUser['role'] ?? ''), ['purchaser', 'general_manager'], true)) {
        Response::error('Only Purchasing can view confirmed shortages', 403);
    }
    $headers = $db->query("
        SELECT sv.*, u.full_name AS validated_by_name
        FROM stock_validations sv
        LEFT JOIN users u ON u.id = sv.validated_by
        WHERE sv.status IN ('open','partially_ordered')
        ORDER BY sv.created_at ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $itemStmt = $db->prepare("
        SELECT svi.*,
               i.ingredient_code, i.ingredient_name,
               m.item_code AS mro_item_code, m.item_name AS mro_item_name,
               COALESCE(SUM(CASE WHEN po.status NOT IN ('cancelled','rejected') THEN svip.quantity ELSE 0 END), 0) AS allocated_quantity
        FROM stock_validation_items svi
        LEFT JOIN ingredients i ON i.id = svi.ingredient_id
        LEFT JOIN mro_items m ON m.id = svi.mro_item_id
        LEFT JOIN stock_validation_item_po svip ON svip.stock_validation_item_id = svi.id
        LEFT JOIN purchase_orders po ON po.id = svip.po_id
        WHERE svi.stock_validation_id = ? AND svi.is_queue_active = 1
          AND (
              svi.purchasing_decision = 'pending'
              OR (svi.purchasing_decision = 'deferred' AND svi.deferred_until <= CURDATE())
          )
        GROUP BY svi.id
        ORDER BY svi.id
    ");
    foreach ($headers as &$header) {
        $itemStmt->execute([(int) $header['id']]);
        $items = [];
        foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $item['allocated_quantity'] = (float) $item['allocated_quantity'];
            $item['remaining_quantity'] = max(0, (float) $item['quantity_needed'] - $item['allocated_quantity']);
            $item['quantity'] = (float) $item['quantity_needed'];
            $item['audited_stock'] = (float) $item['physical_stock'];
            if ($item['remaining_quantity'] > 0.0001) $items[] = $item;
        }
        $header['items'] = $items;
        $header['remaining_item_count'] = count($items);
        $header['source_number'] = $header['validation_number'];
        $header['pr_number'] = $header['validation_number'];
        $header['purpose'] = 'Confirmed shelf count';
        $header['priority'] = 'normal';
    }
    unset($header);
    $headers = array_values(array_filter($headers, fn($header) => !empty($header['items'])));
    Response::success($headers, 'Confirmed low-stock items retrieved');
}

function listPurchasingDecisions(PDO $db, array $currentUser): void {
    if (!in_array(($currentUser['role'] ?? ''), ['purchaser', 'general_manager'], true)) {
        Response::error('Only Purchasing can view paused or closed shortages', 403);
    }
    $stmt = $db->query("
        SELECT
            svi.id,
            svi.stock_validation_id,
            svi.ingredient_id,
            svi.mro_item_id,
            svi.item_description,
            svi.unit,
            svi.physical_stock,
            svi.quantity_needed,
            svi.purchasing_decision,
            svi.deferred_until,
            svi.purchasing_decision_reason,
            svi.purchasing_decided_at,
            sv.validation_number,
            u.full_name AS decided_by_name,
            i.ingredient_name,
            m.item_name AS mro_item_name,
            COALESCE(SUM(CASE WHEN po.status NOT IN ('cancelled','rejected') THEN svip.quantity ELSE 0 END), 0) AS allocated_quantity
        FROM stock_validation_items svi
        JOIN stock_validations sv ON sv.id = svi.stock_validation_id
        LEFT JOIN users u ON u.id = svi.purchasing_decided_by
        LEFT JOIN ingredients i ON i.id = svi.ingredient_id
        LEFT JOIN mro_items m ON m.id = svi.mro_item_id
        LEFT JOIN stock_validation_item_po svip ON svip.stock_validation_item_id = svi.id
        LEFT JOIN purchase_orders po ON po.id = svip.po_id
        WHERE svi.is_queue_active = 1
          AND svi.purchasing_decision IN ('deferred','closed_without_order')
        GROUP BY svi.id
        HAVING svi.quantity_needed - allocated_quantity > 0.0001
        ORDER BY svi.purchasing_decided_at DESC, svi.id DESC
    ");
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $item['allocated_quantity'] = (float) $item['allocated_quantity'];
        $item['remaining_quantity'] = max(0, (float) $item['quantity_needed'] - $item['allocated_quantity']);
        $items[] = $item;
    }
    Response::success($items, 'Paused and closed shortages retrieved');
}

function savePurchasingDecision(PDO $db, array $currentUser): void {
    if (!in_array(($currentUser['role'] ?? ''), ['purchaser', 'general_manager'], true)) {
        Response::error('Only Purchasing can decide what to do with a confirmed shortage', 403);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $itemId = (int) ($data['item_id'] ?? 0);
    $decision = strtolower(trim((string) ($data['decision'] ?? '')));
    $reason = trim((string) ($data['reason'] ?? ''));
    $deferredUntil = trim((string) ($data['deferred_until'] ?? ''));
    if ($itemId <= 0 || !in_array($decision, ['defer', 'close', 'reopen'], true)) {
        throw new InvalidArgumentException('Choose a valid confirmed item and decision');
    }
    if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
        throw new InvalidArgumentException('Enter a clear reason between 10 and 500 characters');
    }

    $savedDecision = 'pending';
    $savedDate = null;
    if ($decision === 'defer') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $deferredUntil);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count'])) || $date->format('Y-m-d') !== $deferredUntil) {
            throw new InvalidArgumentException('Choose a valid review date');
        }
        $tomorrow = new DateTimeImmutable('tomorrow');
        $latest = new DateTimeImmutable('+365 days');
        if ($date < $tomorrow || $date > $latest) {
            throw new InvalidArgumentException('The review date must be between tomorrow and one year from today');
        }
        $savedDecision = 'deferred';
        $savedDate = $deferredUntil;
    } elseif ($decision === 'close') {
        $savedDecision = 'closed_without_order';
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            SELECT svi.*, sv.validation_number
            FROM stock_validation_items svi
            JOIN stock_validations sv ON sv.id = svi.stock_validation_id
            WHERE svi.id = ? AND svi.is_queue_active = 1
            FOR UPDATE
        ");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) throw new InvalidArgumentException('This confirmed shortage is no longer available');

        $allocatedStmt = $db->prepare("
            SELECT COALESCE(SUM(svip.quantity), 0)
            FROM stock_validation_item_po svip
            JOIN purchase_orders po ON po.id = svip.po_id
            WHERE svip.stock_validation_item_id = ?
              AND po.status NOT IN ('cancelled','rejected')
        ");
        $allocatedStmt->execute([$itemId]);
        if ((float) $allocatedStmt->fetchColumn() >= (float) $item['quantity_needed'] - 0.0001) {
            throw new InvalidArgumentException('This shortage is already fully covered by Purchase Orders');
        }

        $before = [
            'decision' => $item['purchasing_decision'],
            'deferred_until' => $item['deferred_until'],
            'reason' => $item['purchasing_decision_reason'],
        ];
        $db->prepare("
            UPDATE stock_validation_items
            SET purchasing_decision = ?, deferred_until = ?, purchasing_decision_reason = ?,
                purchasing_decided_by = ?, purchasing_decided_at = NOW()
            WHERE id = ?
        ")->execute([
            $savedDecision,
            $savedDate,
            $reason,
            (int) $currentUser['user_id'],
            $itemId,
        ]);
        $status = recomputeStockValidationState($db, (int) $item['stock_validation_id']);
        logAudit((int) $currentUser['user_id'], 'DECIDE_CONFIRMED_SHORTAGE', 'stock_validation_items', $itemId, $before, [
            'decision' => $savedDecision,
            'deferred_until' => $savedDate,
            'reason' => $reason,
        ]);
        $db->commit();

        $message = $savedDecision === 'deferred'
            ? "Review scheduled for {$savedDate}."
            : ($savedDecision === 'closed_without_order'
                ? 'The shortage was closed without ordering. Warehouse can still see the recorded decision.'
                : 'The shortage is available for ordering again.');
        Response::success([
            'item_id' => $itemId,
            'decision' => $savedDecision,
            'deferred_until' => $savedDate,
            'validation_status' => $status,
        ], $message);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function createStockValidation(PDO $db, array $currentUser): void {
    if (($currentUser['role'] ?? '') !== 'warehouse_raw') {
        Response::error('Only Warehouse Raw can confirm shelf counts', 403);
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    if (!$items || count($items) > 100) {
        throw new InvalidArgumentException('Choose between 1 and 100 low-stock items');
    }

    $db->beginTransaction();
    try {
        $number = nextStockValidationNumber();
        $headerStmt = $db->prepare("
            INSERT INTO stock_validations (validation_number, validated_by, status, notes)
            VALUES (?, ?, 'open', ?)
        ");
        $headerStmt->execute([
            $number,
            (int) $currentUser['user_id'],
            trim((string) ($data['notes'] ?? '')) ?: null,
        ]);
        $validationId = (int) $db->lastInsertId();
        $seen = [];

        foreach ($items as $index => $submitted) {
            $line = $index + 1;
            $ingredientId = (int) ($submitted['ingredient_id'] ?? 0);
            $mroItemId = (int) ($submitted['mro_item_id'] ?? 0);
            if (($ingredientId > 0) === ($mroItemId > 0)) {
                throw new InvalidArgumentException("Line {$line}: choose one registered stock item");
            }
            $type = $ingredientId > 0 ? 'ingredient' : 'mro';
            $itemId = $ingredientId ?: $mroItemId;
            $key = $type . ':' . $itemId;
            if (isset($seen[$key])) throw new InvalidArgumentException("Line {$line}: this item was selected twice");
            $seen[$key] = true;

            $table = $type === 'ingredient' ? 'ingredients' : 'mro_items';
            $nameColumn = $type === 'ingredient' ? 'ingredient_name' : 'item_name';
            $stockStmt = $db->prepare("SELECT * FROM {$table} WHERE id = ? AND is_active = 1 FOR UPDATE");
            $stockStmt->execute([$itemId]);
            $stock = $stockStmt->fetch(PDO::FETCH_ASSOC);
            if (!$stock) throw new InvalidArgumentException("Line {$line}: this stock item is no longer active");

            $closedStmt = $db->prepare("
                SELECT svi.purchasing_decision_reason
                FROM stock_validation_items svi
                WHERE svi.is_queue_active = 1
                  AND svi.purchasing_decision = 'closed_without_order'
                  AND " . ($type === 'ingredient' ? 'svi.ingredient_id' : 'svi.mro_item_id') . " = ?
                  AND svi.purchasing_decided_at IS NOT NULL
                  AND svi.purchasing_decided_at >= ?
                ORDER BY svi.purchasing_decided_at DESC
                LIMIT 1
            ");
            $closedStmt->execute([$itemId, $stock['updated_at'] ?? '1970-01-01 00:00:00']);
            if ($closedStmt->fetchColumn() !== false) {
                throw new InvalidArgumentException($stock[$nameColumn] . ' was closed by Purchasing. It can be checked again after its stock balance changes.');
            }

            $existingStmt = $db->prepare("
                SELECT sv.validation_number
                FROM stock_validation_items svi
                JOIN stock_validations sv ON sv.id = svi.stock_validation_id
                WHERE sv.status IN ('open','partially_ordered')
                  AND svi.is_queue_active = 1
                  AND " . ($type === 'ingredient' ? 'svi.ingredient_id' : 'svi.mro_item_id') . " = ?
                  AND svi.quantity_needed > COALESCE((
                      SELECT SUM(svip.quantity)
                      FROM stock_validation_item_po svip
                      JOIN purchase_orders po ON po.id = svip.po_id
                      WHERE svip.stock_validation_item_id = svi.id
                        AND po.status NOT IN ('cancelled','rejected')
                  ), 0) + 0.0001
                LIMIT 1
            ");
            $existingStmt->execute([$itemId]);
            $existingNumber = $existingStmt->fetchColumn();
            if ($existingNumber) {
                throw new InvalidArgumentException($stock[$nameColumn] . " is already confirmed as low stock ({$existingNumber})");
            }

            $stockOnFile = (float) $stock['current_stock'];
            $systemStock = $type === 'ingredient'
                ? getUsableIngredientBatchStock($db, $itemId)
                : $stockOnFile;
            if (!array_key_exists('audited_stock', $submitted) || $submitted['audited_stock'] === '') {
                throw new InvalidArgumentException("Line {$line}: enter the quantity counted on the shelf");
            }
            $physicalStock = (float) $submitted['audited_stock'];
            if (!is_finite($physicalStock) || $physicalStock < 0 || $physicalStock > $systemStock + 0.0005) {
                throw new InvalidArgumentException("Line {$line}: a higher shelf count must be recorded through Record Found Stock before this validation");
            }
            $variance = $physicalStock - $systemStock;
            $reason = trim((string) ($submitted['audit_reason'] ?? ''));
            if (abs($variance) > 0.0005 && $reason === '') {
                throw new InvalidArgumentException("Line {$line}: explain why the shelf count differs from the saved balance");
            }

            $minimum = (float) ($stock['minimum_stock'] ?? 0);
            $reorder = max((float) ($stock['reorder_point'] ?? 0), $minimum);
            $maximum = (float) ($stock['maximum_stock'] ?? 0);
            $target = $maximum > $reorder ? $maximum : ($reorder > 0 ? $reorder * 2 : 0);
            $quantityNeeded = round($target - $physicalStock, 3);
            if ($quantityNeeded <= 0.0005) {
                throw new InvalidArgumentException($stock[$nameColumn] . ' is already at or above its replenishment target');
            }

            if ($variance < -0.0005) {
                if ($type === 'ingredient') {
                    reduceIngredientBatchesToQuantity($db, $stock, $physicalStock, $currentUser, $reason);
                }
                $newStockOnFile = $type === 'ingredient'
                    ? max(0, round($stockOnFile + $variance, 3))
                    : $physicalStock;
                $db->prepare("UPDATE {$table} SET current_stock = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$newStockOnFile, $itemId]);
                $txStmt = $db->prepare("
                    INSERT INTO inventory_transactions
                        (transaction_code, transaction_type, item_type, item_id, quantity,
                         unit_of_measure, quantity_before, quantity_after, reference_type,
                         reference_id, performed_by, reason)
                    VALUES (?, 'physical_adjust', ?, ?, ?, ?, ?, ?, 'stock_validation', ?, ?, ?)
                ");
                $txStmt->execute([
                    generateCode('TX'), $type, $itemId, $variance,
                    $stock['unit_of_measure'], $systemStock, $physicalStock,
                    $validationId, (int) $currentUser['user_id'],
                    'Confirmed shelf count: ' . $reason,
                ]);
            }

            $itemStmt = $db->prepare("
                INSERT INTO stock_validation_items
                    (stock_validation_id, ingredient_id, mro_item_id, item_description, unit,
                     system_stock_before, physical_stock, stock_variance, variance_reason,
                     reorder_point_at_validation, target_stock_at_validation, quantity_needed)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $itemStmt->execute([
                $validationId,
                $ingredientId ?: null,
                $mroItemId ?: null,
                $stock[$nameColumn],
                $stock['unit_of_measure'] ?: 'units',
                $systemStock,
                $physicalStock,
                $variance,
                $reason ?: null,
                $reorder,
                $target,
                $quantityNeeded,
            ]);
        }

        writeProcurementNotification(
            $db,
            'purchaser',
            'stock_validated',
            'Low stock confirmed by Warehouse',
            count($items) . ' confirmed item' . (count($items) === 1 ? ' is' : 's are') . ' ready for supplier selection.',
            'stock_validation',
            $validationId
        );
        logAudit((int) $currentUser['user_id'], 'VALIDATE_LOW_STOCK', 'stock_validations', $validationId, null, [
            'validation_number' => $number,
            'item_count' => count($items),
        ]);
        $db->commit();
        Response::success([
            'validation_id' => $validationId,
            'validation_number' => $number,
            'item_count' => count($items),
        ], 'Stock check confirmed. Purchasing can now view the shortages.', 201);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
