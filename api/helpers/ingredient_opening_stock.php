<?php

/**
 * Safe intake for physical ingredient stock that exists on the shelf but has
 * no PO receipt in the system. Nothing becomes usable until the GM approves.
 */

function ensureIngredientOpeningStockSupport(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS ingredient_opening_stock_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_code VARCHAR(30) NOT NULL UNIQUE,
            ingredient_id INT NOT NULL,
            system_quantity DECIMAL(12,3) NOT NULL,
            counted_quantity DECIMAL(12,3) NOT NULL,
            quantity_to_add DECIMAL(12,3) NOT NULL,
            unit VARCHAR(20) NOT NULL,
            source_type ENUM('opening_balance','unrecorded_delivery') NOT NULL,
            supplier_id INT NULL,
            source_reference VARCHAR(100) NOT NULL,
            supplier_batch_no VARCHAR(50) NULL,
            received_date DATE NOT NULL,
            expiry_date DATE NULL,
            unit_cost DECIMAL(10,2) NULL,
            price_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            price_verified_by INT NULL,
            price_verified_at DATETIME NULL,
            price_reference VARCHAR(100) NULL,
            matched_po_id INT NULL,
            qc_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            qc_verified_by INT NULL,
            qc_verified_at DATETIME NULL,
            qc_notes VARCHAR(500) NULL,
            reason VARCHAR(500) NOT NULL,
            status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
            requested_by INT NOT NULL,
            decided_by INT NULL,
            decided_at DATETIME NULL,
            decision_notes VARCHAR(1000) NULL,
            created_batch_id INT NULL,
            request_purpose VARCHAR(30) NOT NULL DEFAULT 'found_stock',
            held_batch_id INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_opening_stock_status (status, created_at),
            INDEX idx_opening_stock_ingredient (ingredient_id, status),
            CONSTRAINT fk_opening_stock_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id),
            CONSTRAINT fk_opening_stock_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
            CONSTRAINT fk_opening_stock_requester FOREIGN KEY (requested_by) REFERENCES users(id),
            CONSTRAINT fk_opening_stock_decider FOREIGN KEY (decided_by) REFERENCES users(id),
            CONSTRAINT fk_opening_stock_batch FOREIGN KEY (created_batch_id) REFERENCES ingredient_batches(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $columns = [
        'price_status' => "ALTER TABLE ingredient_opening_stock_requests ADD COLUMN price_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER unit_cost",
        'price_verified_by' => "ALTER TABLE ingredient_opening_stock_requests ADD COLUMN price_verified_by INT NULL AFTER price_status",
        'price_verified_at' => "ALTER TABLE ingredient_opening_stock_requests ADD COLUMN price_verified_at DATETIME NULL AFTER price_verified_by",
        'price_reference' => "ALTER TABLE ingredient_opening_stock_requests ADD COLUMN price_reference VARCHAR(100) NULL AFTER price_verified_at",
        'matched_po_id' => "ALTER TABLE ingredient_opening_stock_requests ADD COLUMN matched_po_id INT NULL AFTER price_reference",
        'qc_status' => "ALTER TABLE ingredient_opening_stock_requests ADD COLUMN qc_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER matched_po_id",
        'qc_verified_by' => "ALTER TABLE ingredient_opening_stock_requests ADD COLUMN qc_verified_by INT NULL AFTER qc_status",
        'qc_verified_at' => "ALTER TABLE ingredient_opening_stock_requests ADD COLUMN qc_verified_at DATETIME NULL AFTER qc_verified_by",
        'qc_notes' => "ALTER TABLE ingredient_opening_stock_requests ADD COLUMN qc_notes VARCHAR(500) NULL AFTER qc_verified_at",
        'request_purpose' => "ALTER TABLE ingredient_opening_stock_requests ADD COLUMN request_purpose VARCHAR(30) NOT NULL DEFAULT 'found_stock' AFTER created_batch_id",
        'held_batch_id' => "ALTER TABLE ingredient_opening_stock_requests ADD COLUMN held_batch_id INT NULL AFTER request_purpose",
    ];
    foreach ($columns as $column => $sql) {
        $columnStmt = $db->query("SHOW COLUMNS FROM ingredient_opening_stock_requests LIKE " . $db->quote($column));
        if (!$columnStmt->fetch(PDO::FETCH_ASSOC)) {
            $db->exec($sql);
        }
    }

    $unitCostColumn = $db->query("SHOW COLUMNS FROM ingredient_opening_stock_requests LIKE 'unit_cost'")->fetch(PDO::FETCH_ASSOC);
    if ($unitCostColumn && strtoupper((string) ($unitCostColumn['Null'] ?? 'NO')) !== 'YES') {
        $db->exec('ALTER TABLE ingredient_opening_stock_requests MODIFY unit_cost DECIMAL(10,2) NULL');
    }
}

/** Find an exact PO/receiving reference for the same supplier and ingredient. */
function findIngredientOpeningStockPrice(PDO $db, int $ingredientId, int $supplierId, string $reference): ?array {
    $reference = trim($reference);
    if ($ingredientId <= 0 || $supplierId <= 0 || $reference === '') return null;

    $stmt = $db->prepare("
        SELECT po.id AS po_id, po.po_number,
               COALESCE(NULLIF(rri.unit_price, 0), NULLIF(poi.unit_price, 0)) AS unit_cost
        FROM purchase_orders po
        JOIN purchase_order_items poi
          ON poi.po_id = po.id AND poi.ingredient_id = ?
        LEFT JOIN receiving_reports rr ON rr.po_id = po.id
        LEFT JOIN receiving_report_items rri
          ON rri.rr_id = rr.id AND rri.po_item_id = poi.id
        WHERE po.supplier_id = ?
          AND po.status NOT IN ('cancelled', 'rejected')
          AND (
              po.po_number = ? OR rr.rr_number = ? OR
              rr.delivery_reference = ? OR rr.invoice_number = ?
          )
        ORDER BY rr.received_at DESC, po.id DESC
        LIMIT 1
    ");
    $stmt->execute([$ingredientId, $supplierId, $reference, $reference, $reference, $reference]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$match || (float) ($match['unit_cost'] ?? 0) <= 0) return null;

    return [
        'po_id' => (int) $match['po_id'],
        'po_number' => (string) $match['po_number'],
        'unit_cost' => round((float) $match['unit_cost'], 2),
        'price_reference' => 'Matched to ' . $match['po_number'],
    ];
}

function ingredientOpeningStockCode(PDO $db): string {
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $code = 'OPEN-' . date('Ymd-His') . '-' . random_int(10, 99);
        $stmt = $db->prepare('SELECT COUNT(*) FROM ingredient_opening_stock_requests WHERE request_code = ?');
        $stmt->execute([$code]);
        if ((int) $stmt->fetchColumn() === 0) return $code;
    }
    throw new RuntimeException('Could not create an opening-stock reference');
}

function ingredientOpeningStockTransactionCode(PDO $db): string {
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $code = 'TX-OPEN-' . date('ymd') . '-' . random_int(1000, 9999);
        $stmt = $db->prepare('SELECT COUNT(*) FROM inventory_transactions WHERE transaction_code = ?');
        $stmt->execute([$code]);
        if ((int) $stmt->fetchColumn() === 0) return $code;
    }
    throw new RuntimeException('Could not create a stock movement reference');
}

/** Must be called inside the GM approval transaction. */
function decideIngredientOpeningStock(PDO $db, int $requestId, string $decision, int $gmId, string $remarks = ''): array {
    $stmt = $db->prepare('SELECT * FROM ingredient_opening_stock_requests WHERE id = ? FOR UPDATE');
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request || $request['status'] !== 'pending') {
        throw new RuntimeException('Opening-stock request was not found or was already decided');
    }

    if ($decision === 'reject') {
        $update = $db->prepare("UPDATE ingredient_opening_stock_requests
            SET status = 'rejected', decided_by = ?, decided_at = NOW(), decision_notes = ?
            WHERE id = ? AND status = 'pending'");
        $update->execute([$gmId, $remarks ?: 'Rejected by General Manager', $requestId]);
        return ['status' => 'rejected', 'request' => $request];
    }

    if (!in_array((string) ($request['price_status'] ?? ''), ['matched_po', 'verified'], true)
        || (float) ($request['unit_cost'] ?? 0) <= 0) {
        throw new RuntimeException('Purchasing must verify the unit cost before GM approval');
    }

    $ingredientStmt = $db->prepare('SELECT * FROM ingredients WHERE id = ? AND is_active = 1 FOR UPDATE');
    $ingredientStmt->execute([(int) $request['ingredient_id']]);
    $ingredient = $ingredientStmt->fetch(PDO::FETCH_ASSOC);
    if (!$ingredient) throw new RuntimeException('Ingredient is no longer active');

    $isTraceabilityCorrection = ($request['request_purpose'] ?? 'found_stock') === 'traceability_correction';
    $usableNow = getUsableIngredientBatchStock($db, (int) $ingredient['id']);
    $comparisonNow = $isTraceabilityCorrection
        ? $usableNow
        : max(
            (float) ($ingredient['current_stock'] ?? 0),
            getAccountedIngredientBatchStock($db, (int) $ingredient['id'])
        );
    $recordedAtRequest = (float) $request['system_quantity'];
    if (abs($comparisonNow - $recordedAtRequest) > 0.0005) {
        throw new RuntimeException('Stock changed after Warehouse counted it. Reject this request and ask Warehouse to recount.');
    }

    $target = (float) $request['counted_quantity'];
    $quantity = $isTraceabilityCorrection
        ? (float) $request['quantity_to_add']
        : round($target - $comparisonNow, 3);
    if ($quantity <= 0.0005) {
        throw new RuntimeException('The counted quantity is no longer higher than the saved stock');
    }

    $isPerishable = (int) ($ingredient['is_perishable'] ?? 1) === 1;
    if ($isPerishable && ($request['qc_status'] ?? '') !== 'approved') {
        throw new RuntimeException('QC must approve this perishable stock before GM approval');
    }
    $supplierLot = trim((string) ($request['supplier_batch_no'] ?? ''));
    if ($isPerishable && ($supplierLot === '' || str_starts_with($supplierLot, 'INTERNAL-') || empty($request['expiry_date']))) {
        throw new RuntimeException('A perishable batch requires the real supplier lot number and printed expiry date before it can become usable');
    }
    if (!empty($request['expiry_date']) && $request['expiry_date'] <= date('Y-m-d')) {
        throw new RuntimeException('Expired stock cannot be approved as usable inventory');
    }

    $heldBatchId = (int) ($request['held_batch_id'] ?? 0);
    $batchCode = null;
    if ($isTraceabilityCorrection && $heldBatchId > 0) {
        $heldStmt = $db->prepare("SELECT * FROM ingredient_batches
            WHERE id = ? AND ingredient_id = ? FOR UPDATE");
        $heldStmt->execute([$heldBatchId, (int) $ingredient['id']]);
        $heldBatch = $heldStmt->fetch(PDO::FETCH_ASSOC);
        if (!$heldBatch
            || !in_array((string) $heldBatch['status'], ['available', 'partially_used'], true)
            || trim((string) ($heldBatch['supplier_batch_no'] ?? '')) !== ''
            || abs((float) $heldBatch['remaining_quantity'] - $quantity) > 0.0005) {
            throw new RuntimeException('The held batch changed after Warehouse recorded its details. Reject this request and check the batch again.');
        }

        $batchId = $heldBatchId;
        $batchCode = (string) $heldBatch['batch_code'];
        $batchStmt = $db->prepare("UPDATE ingredient_batches SET
            po_id = ?, unit_cost = ?, supplier_id = ?, supplier_batch_no = ?,
            received_date = ?, expiry_date = ?, qc_status = 'approved',
            qc_tested_by = ?, qc_tested_at = ?, status = 'available',
            notes = CONCAT(COALESCE(notes, ''), CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE '\n' END, ?)
            WHERE id = ?");
        $batchStmt->execute([
            $request['matched_po_id'] ?: null,
            (float) $request['unit_cost'],
            $request['supplier_id'] ?: null,
            $request['supplier_batch_no'] ?: null,
            $request['received_date'],
            $request['expiry_date'] ?: null,
            $isPerishable ? (int) ($request['qc_verified_by'] ?? 0) : null,
            $isPerishable ? ($request['qc_verified_at'] ?? date('Y-m-d H:i:s')) : null,
            "Traceability corrected through {$request['request_code']}: {$request['reason']}",
            $batchId,
        ]);
    } else {
        $batchCode = generateIngredientBatchCode($db, 'IB-OPEN');
        $batchStmt = $db->prepare("INSERT INTO ingredient_batches
            (batch_code, ingredient_id, po_id, quantity, remaining_quantity, unit_cost,
             supplier_id, supplier_batch_no, received_date, expiry_date,
             qc_status, qc_tested_by, qc_tested_at, received_by, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, ?, 'available', ?)");
        $batchStmt->execute([
            $batchCode,
            (int) $ingredient['id'],
            $request['matched_po_id'] ?: null,
            $quantity,
            $quantity,
            (float) $request['unit_cost'],
            $request['supplier_id'] ?: null,
            $request['supplier_batch_no'] ?: null,
            $request['received_date'],
            $request['expiry_date'] ?: null,
            $isPerishable ? (int) ($request['qc_verified_by'] ?? 0) : null,
            $isPerishable ? ($request['qc_verified_at'] ?? date('Y-m-d H:i:s')) : null,
            (int) $request['requested_by'],
            "GM-approved unrecorded stock {$request['request_code']}: {$request['reason']}",
        ]);
        $batchId = (int) $db->lastInsertId();
    }

    $newStockOnFile = $isTraceabilityCorrection
        ? max(
            (float) $ingredient['current_stock'],
            getAccountedIngredientBatchStock($db, (int) $ingredient['id'])
        )
        : round($comparisonNow + $quantity, 3);
    $db->prepare('UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?')
        ->execute([$newStockOnFile, (int) $ingredient['id']]);

    $txStmt = $db->prepare("INSERT INTO inventory_transactions
        (transaction_code, transaction_type, item_type, item_id, batch_id, quantity,
         unit_of_measure, quantity_before, quantity_after, reference_type, reference_id,
         to_location, unit_cost, total_cost, performed_by, approved_by, reason)
        VALUES (?, 'physical_adjust', 'ingredient', ?, ?, ?, ?, ?, ?,
                'ingredient_opening_stock', ?, ?, ?, ?, ?, ?, ?)");
    $txStmt->execute([
        ingredientOpeningStockTransactionCode($db),
        (int) $ingredient['id'],
        $batchId,
        $quantity,
        $ingredient['unit_of_measure'],
        $comparisonNow,
        $isTraceabilityCorrection ? round($usableNow + $quantity, 3) : $newStockOnFile,
        $requestId,
        $ingredient['storage_location'] ?: null,
        (float) $request['unit_cost'],
        round($quantity * (float) $request['unit_cost'], 2),
        (int) $request['requested_by'],
        $gmId,
        "Approved unrecorded stock {$request['request_code']}: {$request['reason']}",
    ]);

    $update = $db->prepare("UPDATE ingredient_opening_stock_requests
        SET status = 'approved', decided_by = ?, decided_at = NOW(), decision_notes = ?, created_batch_id = ?
        WHERE id = ? AND status = 'pending'");
    $update->execute([$gmId, $remarks ?: 'Approved by General Manager', $batchId, $requestId]);

    return ['status' => 'approved', 'request' => $request, 'batch_id' => $batchId, 'batch_code' => $batchCode];
}
