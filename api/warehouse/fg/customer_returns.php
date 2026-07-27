<?php
/**
 * Highland Fresh - Customer Returns API
 *
 * Handles formal customer returns workflow (Steps 1-3 of Return & Disposal):
 *  Step 1: Create Return Receipt linked to DR#, Batch ID, Expiry
 *  Step 2: Categorize return reason (damaged, expired, quality, rejection)
 *  Step 3: QC disposition — restock / hold / dispose → routes to disposal API
 *
 * When disposition=dispose, automatically creates a disposal record (status=pending)
 * so the item enters the GM approval workflow before physical write-off.
 *
 * GET    ?action=list                     — Paginated list of returns
 * GET    ?id=N                            — Single return with items
 * GET    ?action=stats                    — Dashboard stats
 * GET    ?action=lookup                   — Return reasons / dispositions
 * POST   ?action=record                   — Create return receipt with items
 * PUT    ?action=qc_inspect&id=N          — QC officer inspects and disposes
 * PUT    ?action=update_disposition&id=N  — Change disposition before QC
 * DELETE ?id=N                            — Void a pending return
 *
 * @package HighlandFresh
 * @version 1.0
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

$currentUser = Auth::requireRole(['warehouse_fg', 'qc_officer', 'general_manager', 'admin']);
$action = getParam('action', '');

// ── Constants ───────────────────────────────────────────────────────────────
$RETURN_REASONS = [
    'damaged_transit'     => 'Damaged in Transit',
    'expired'             => 'Expired / Near-Expiry',
    'quality_issue'       => 'Quality Issue',
    'customer_rejection'  => 'Customer Rejection',
    'wrong_order'         => 'Wrong Order Delivered',
    'overage'             => 'Overage / Excess Delivered',
    'other'               => 'Other',
];

$DISPOSITIONS = [
    'return_to_inventory' => 'Return to Saleable Stock',
    'hold_for_qc'         => 'Hold for QC Inspection',
    'dispose'             => 'Dispose / Write-Off',
    'rework'              => 'Rework',
];

$CONDITIONS = [
    'good'          => 'Good / Resellable',
    'damaged'       => 'Damaged',
    'expired'       => 'Expired',
    'questionable'  => 'Questionable',
];

$DISPOSAL_CATEGORY_MAP = [
    'damaged_transit'    => 'damaged',
    'expired'            => 'expired',
    'quality_issue'      => 'spoiled',
    'customer_rejection' => 'rejected_receipt',
    'wrong_order'        => 'other',
    'overage'            => 'other',
    'other'              => 'other',
];

// ── Routing ─────────────────────────────────────────────────────────────────
try {
    $db = Database::getInstance()->getConnection();

    switch ($requestMethod) {
        case 'GET':  handleGet($db); break;
        case 'POST': handlePost($db, $currentUser); break;
        case 'PUT':  handlePut($db, $currentUser); break;
        case 'DELETE': handleDelete($db, $currentUser); break;
        default: Response::error('Method not allowed', 405);
    }
} catch (PDOException $e) {
    error_log("Customer Returns API Error: " . $e->getMessage());
    Response::error('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    error_log("Customer Returns API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

// ═════════════════════════════════════════════════════════════════════════════
// GET
// ═════════════════════════════════════════════════════════════════════════════
function handleGet(PDO $db) {
    global $RETURN_REASONS, $DISPOSITIONS, $CONDITIONS;

    $action = getParam('action', '');
    $id     = getParam('id');

    // ── Single return ──
    if ($id) {
        $stmt = $db->prepare("
            SELECT cr.*,
                   u1.first_name AS received_by_name,
                   u2.first_name AS qc_inspected_by_name,
                   u3.first_name AS processed_by_name
            FROM customer_returns cr
            LEFT JOIN users u1 ON cr.received_by = u1.id
            LEFT JOIN users u2 ON cr.qc_inspected_by = u2.id
            LEFT JOIN users u3 ON cr.processed_by = u3.id
            WHERE cr.id = ?
        ");
        $stmt->execute([$id]);
        $return = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$return) Response::notFound('Return not found');

        $itemsStmt = $db->prepare("SELECT * FROM customer_return_items WHERE return_id = ? ORDER BY id");
        $itemsStmt->execute([$id]);
        $return['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success($return, 'Return retrieved');
    }

    // ── Stats ──
    if ($action === 'stats') {
        $stats = $db->query("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'pending') AS pending,
                SUM(status = 'received') AS received,
                SUM(status = 'inspected') AS inspected,
                SUM(status = 'processed') AS processed,
                SUM(status = 'closed') AS closed,
                SUM(total_value) AS total_value
            FROM customer_returns
        ")->fetch(PDO::FETCH_ASSOC);

        $byReason = $db->query("
            SELECT return_reason, COUNT(*) AS cnt, SUM(total_value) AS val
            FROM customer_returns GROUP BY return_reason ORDER BY cnt DESC
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        $pendingDisposal = (int) $db->query("
            SELECT COUNT(*) FROM customer_returns
            WHERE disposition = 'dispose' AND status IN ('pending','received','inspected')
        ")->fetchColumn();

        Response::success([
            'stats' => $stats,
            'by_reason' => $byReason,
            'pending_disposal' => $pendingDisposal,
        ], 'Stats retrieved');
    }

    // ── Lookup ──
    if ($action === 'lookup') {
        Response::success([
            'return_reasons' => $RETURN_REASONS,
            'dispositions'   => $DISPOSITIONS,
            'conditions'     => $CONDITIONS,
        ], 'Lookup data');
    }

    // ── List ──
    $status = getParam('status');
    $page   = max(1, (int) getParam('page', 1));
    $limit  = max(1, min(100, (int) getParam('limit', 20)));
    $offset = ($page - 1) * $limit;

    $where = '1=1';
    $params = [];
    if ($status) {
        $where .= ' AND cr.status = ?';
        $params[] = $status;
    }

    $total = (int) $db->prepare("SELECT COUNT(*) FROM customer_returns cr WHERE $where")->execute($params)
        ? $db->prepare("SELECT COUNT(*) FROM customer_returns cr WHERE $where") : null;
    // Re-execute for count
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM customer_returns cr WHERE $where");
    $cntStmt->execute($params);
    $total = (int) $cntStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT cr.*,
               u1.first_name AS received_by_name,
               u2.first_name AS qc_inspected_by_name
        FROM customer_returns cr
        LEFT JOIN users u1 ON cr.received_by = u1.id
        LEFT JOIN users u2 ON cr.qc_inspected_by = u2.id
        WHERE $where
        ORDER BY cr.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::paginated($rows, $total, $page, $limit, 'Returns retrieved');
}

// ═════════════════════════════════════════════════════════════════════════════
// POST — Create Return Receipt
// ═════════════════════════════════════════════════════════════════════════════
function handlePost(PDO $db, array $currentUser) {
    global $RETURN_REASONS, $DISPOSITIONS, $CONDITIONS, $DISPOSAL_CATEGORY_MAP;

    $action = getParam('action', '');

    // ── Record a customer return ──
    if ($action === 'record') {
        $drNumber   = trim(getParam('dr_number', ''));
        $customerId = (int) getParam('customer_id', 0);
        $customerName = trim(getParam('customer_name', ''));
        $returnReason = getParam('return_reason');
        $items      = getParam('items', []);
        $notes      = trim(getParam('notes', ''));
        $dispositionOverride = getParam('disposition');

        $errors = [];
        if (empty($drNumber))   $errors['dr_number'] = 'DR number is required';
        if ($customerId <= 0 && empty($customerName)) $errors['customer_id'] = 'Customer is required';
        if (!isset($RETURN_REASONS[$returnReason]))   $errors['return_reason'] = 'Valid return reason is required';
        if (empty($items) || !is_array($items))        $errors['items'] = 'At least one returned item is required';
        if (!empty($errors)) Response::validationError($errors);

        // Validate DR exists
        $dr = null;
        if ($drNumber) {
            $drStmt = $db->prepare("SELECT * FROM delivery_receipts WHERE dr_number = ? LIMIT 1");
            $drStmt->execute([$drNumber]);
            $dr = $drStmt->fetch(PDO::FETCH_ASSOC);
        }

        // Resolve customer from DR if not provided
        if ($customerId <= 0 && $dr) {
            $customerId = (int) ($dr['customer_id'] ?? 0);
            $customerName = $customerName ?: ($dr['customer_name'] ?? '');
        }

        $db->beginTransaction();
        try {
            // Generate return code
            $today = date('Ymd');
            $codeStmt = $db->prepare("SELECT COUNT(*) FROM customer_returns WHERE return_code LIKE ?");
            $codeStmt->execute(["RET-{$today}-%"]);
            $codeCount = (int) $codeStmt->fetchColumn() + 1;
            $returnCode = "RET-{$today}-" . str_pad($codeCount, 3, '0', STR_PAD_LEFT);

            // Determine overall disposition from reason (unless overridden)
            $overallDisposition = $dispositionOverride ?: 'hold_for_qc';
            if ($returnReason === 'damaged_transit' || $returnReason === 'expired' || $returnReason === 'quality_issue') {
                $overallDisposition = 'dispose';
            } elseif ($returnReason === 'customer_rejection' || $returnReason === 'wrong_order') {
                $overallDisposition = 'return_to_inventory';
            }

            $totalValue = 0;
            $totalItems = 0;

            // Insert header
            $hdr = $db->prepare("
                INSERT INTO customer_returns
                    (return_code, dr_number, customer_id, customer_name,
                     return_date, return_reason, disposition, status,
                     total_items, total_value, received_by, notes)
                VALUES (?, ?, ?, ?, CURDATE(), ?, ?, 'received', 0, 0, ?, ?)
            ");
            $hdr->execute([
                $returnCode, $drNumber, $customerId ?: null, $customerName,
                $returnReason, $overallDisposition,
                $currentUser['user_id'], $notes,
            ]);
            $returnId = $db->lastInsertId();

            // Insert items
            $itemStmt = $db->prepare("
                INSERT INTO customer_return_items
                    (return_id, delivery_item_id, inventory_id, product_name,
                     batch_code, quantity, unit_value, line_total,
                     condition_status, disposition, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                $qty = (int) ($item['quantity'] ?? 0);
                if ($qty <= 0) continue;

                $unitVal  = (float) ($item['unit_value'] ?? 0);
                $lineTotal = $qty * $unitVal;
                $totalValue += $lineTotal;
                $totalItems += $qty;

                $itemCondition = $item['condition_status'] ?? ($item['condition'] ?? 'good');
                $itemDisposition = $item['disposition'] ?? $overallDisposition;

                // Hard rules: damaged/expired never restock
                if (in_array($itemCondition, ['damaged', 'expired'], true)) {
                    $itemDisposition = 'dispose';
                }

                $itemStmt->execute([
                    $returnId,
                    $item['delivery_item_id'] ?? null,
                    $item['inventory_id'] ?? null,
                    $item['product_name'] ?? '',
                    $item['batch_code'] ?? '',
                    $qty,
                    $unitVal,
                    $lineTotal,
                    $itemCondition,
                    $itemDisposition,
                    $item['notes'] ?? null,
                ]);
            }

            // Update totals
            $db->prepare("UPDATE customer_returns SET total_items = ?, total_value = ? WHERE id = ?")
               ->execute([$totalItems, $totalValue, $returnId]);

            // Mark DR as returns_processed
            if ($dr && empty($dr['returns_processed'])) {
                $db->prepare("UPDATE delivery_receipts SET returns_processed = 1, returns_processed_at = NOW(), returns_processed_by = ? WHERE id = ?")
                   ->execute([$currentUser['user_id'], $dr['id']]);
            }

            logAudit($currentUser['user_id'], 'CREATE', 'customer_returns', $returnId, null, [
                'return_code' => $returnCode,
                'dr_number' => $drNumber,
                'customer' => $customerName,
                'reason' => $returnReason,
                'items' => count($items),
                'total_value' => $totalValue,
            ]);

            $db->commit();

            // Fetch created return
            $fetch = $db->prepare("SELECT * FROM customer_returns WHERE id = ?");
            $fetch->execute([$returnId]);
            $created = $fetch->fetch(PDO::FETCH_ASSOC);
            $itemsFetch = $db->prepare("SELECT * FROM customer_return_items WHERE return_id = ?");
            $itemsFetch->execute([$returnId]);
            $created['items'] = $itemsFetch->fetchAll(PDO::FETCH_ASSOC);

            Response::created($created, "Return {$returnCode} created. {$totalItems} items worth ₱" . number_format($totalValue, 2));

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    Response::error('Invalid action', 400);
}

// ═════════════════════════════════════════════════════════════════════════════
// PUT — QC Inspection / Disposition
// ═════════════════════════════════════════════════════════════════════════════
function handlePut(PDO $db, array $currentUser) {
    global $DISPOSITIONS, $CONDITIONS, $DISPOSAL_CATEGORY_MAP;

    $action = getParam('action', '');
    $id     = (int) getParam('id', 0);

    if (!$id) Response::error('Return ID is required', 400);

    $stmt = $db->prepare("SELECT * FROM customer_returns WHERE id = ?");
    $stmt->execute([$id]);
    $return = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$return) Response::notFound('Return not found');

    // ── QC Inspect: decide restock / dispose / rework ──
    if ($action === 'qc_inspect') {
        requireActionRole($currentUser, ['qc_officer', 'general_manager', 'admin']);

        $qcDecision = getParam('qc_decision'); // restock, dispose, rework
        $inspectionNotes = trim(getParam('inspection_notes', ''));

        if (!isset($DISPOSITIONS[$qcDecision === 'restock' ? 'return_to_inventory' : ($qcDecision === 'dispose' ? 'dispose' : 'rework')])) {
            Response::validationError(['qc_decision' => 'Valid QC decision required: restock, dispose, or rework']);
        }

        $db->beginTransaction();
        try {
            $disposition = $qcDecision === 'restock' ? 'return_to_inventory' : ($qcDecision === 'dispose' ? 'dispose' : 'rework');

            $db->prepare("
                UPDATE customer_returns SET
                    status = 'inspected',
                    qc_inspected_by = ?,
                    qc_inspected_at = NOW(),
                    qc_decision = ?,
                    disposition = ?
                WHERE id = ?
            ")->execute([$currentUser['user_id'], $qcDecision, $disposition, $id]);

            // Update individual items to match QC decision
            $db->prepare("
                UPDATE customer_return_items SET disposition = ? WHERE return_id = ?
            ")->execute([$disposition, $id]);

            // If QC says dispose → create disposal record (enters GM approval workflow)
            $disposalId = null;
            if ($disposition === 'dispose') {
                $disposalId = createDisposalFromReturn($db, $return, $currentUser);
            }

            logAudit($currentUser['user_id'], 'UPDATE', 'customer_returns', $id, [
                'status' => $return['status'],
            ], [
                'status' => 'inspected',
                'qc_decision' => $qcDecision,
                'disposition' => $disposition,
                'disposal_id' => $disposalId,
            ]);

            $db->commit();

            $msg = $disposition === 'dispose'
                ? "QC inspected — marked for disposal. Disposal #{$disposalId} pending GM approval."
                : "QC inspected — disposition: {$disposition}";

            Response::success([
                'id' => $id,
                'status' => 'inspected',
                'disposition' => $disposition,
                'disposal_id' => $disposalId,
            ], $msg);

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ── Update disposition before QC ──
    if ($action === 'update_disposition') {
        $newDisposition = getParam('disposition');
        if (!isset($DISPOSITIONS[$newDisposition])) {
            Response::validationError(['disposition' => 'Invalid disposition']);
        }

        $db->prepare("UPDATE customer_returns SET disposition = ? WHERE id = ? AND status IN ('pending','received')")
           ->execute([$newDisposition, $id]);

        if ($db->rowCount() === 0) {
            Response::error('Return cannot be modified in its current status', 400);
        }

        Response::success(['id' => $id, 'disposition' => $newDisposition], 'Disposition updated');
    }

    Response::error('Invalid action', 400);
}

// ═════════════════════════════════════════════════════════════════════════════
// DELETE — Void pending return
// ═════════════════════════════════════════════════════════════════════════════
function handleDelete(PDO $db, array $currentUser) {
    $id = (int) getParam('id', 0);
    if (!$id) Response::error('Return ID is required', 400);

    $stmt = $db->prepare("SELECT * FROM customer_returns WHERE id = ?");
    $stmt->execute([$id]);
    $return = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$return) Response::notFound('Return not found');

    if (!in_array($return['status'], ['pending', 'received'], true)) {
        Response::error('Only pending/received returns can be voided', 400);
    }

    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM customer_return_items WHERE return_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM customer_returns WHERE id = ?")->execute([$id]);

        logAudit($currentUser['user_id'], 'DELETE', 'customer_returns', $id, [
            'return_code' => $return['return_code'],
            'status' => $return['status'],
        ], null);

        $db->commit();
        Response::success(null, 'Return voided');
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// Helper: Create disposal record from a customer return
// ═════════════════════════════════════════════════════════════════════════════
function createDisposalFromReturn(PDO $db, array $return, array $currentUser): int {
    global $DISPOSAL_CATEGORY_MAP;

    $reasonKey = $return['return_reason'] ?? 'other';
    $category  = $DISPOSAL_CATEGORY_MAP[$reasonKey] ?? 'other';

    // Generate disposal code
    $today = date('Ymd');
    $codeStmt = $db->prepare("SELECT COUNT(*) FROM disposals WHERE disposal_code LIKE ?");
    $codeStmt->execute(["DSP-{$today}-%"]);
    $dCount = (int) $codeStmt->fetchColumn() + 1;
    $disposalCode = "DSP-{$today}-" . str_pad($dCount, 3, '0', STR_PAD_LEFT);

    // Aggregate items for total qty and value
    $itemsStmt = $db->prepare("SELECT * FROM customer_return_items WHERE return_id = ?");
    $itemsStmt->execute([$return['id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalQty = 0;
    $totalValue = 0;
    $productName = '';
    foreach ($items as $item) {
        $totalQty += (int) ($item['quantity'] ?? 0);
        $totalValue += (float) ($item['line_total'] ?? 0);
        if (!$productName) $productName = $item['product_name'] ?? '';
    }
    if ($totalQty <= 0) $totalQty = (int) ($return['total_items'] ?? 0);
    if ($totalValue <= 0) $totalValue = (float) ($return['total_value'] ?? 0);

    // Insert disposal header
    $stmt = $db->prepare("
        INSERT INTO disposals
            (disposal_code, source_type, source_id, source_reference,
             product_name, quantity, unit, unit_cost, total_value,
             disposal_category, disposal_reason, disposal_method,
             status, initiated_by, initiated_at, notes)
        VALUES (?, 'finished_goods', ?, ?, ?, ?, 'pcs', 0, ?, ?, ?, 'drain', 'pending', ?, NOW(), ?)
    ");
    $stmt->execute([
        $disposalCode,
        $return['id'],
        $return['return_code'] ?? $return['return_code'] ?? "RET-{$return['id']}",
        $productName,
        $totalQty,
        $totalValue,
        $category,
        "Customer return {$return['return_code']}: {$return['return_reason']}",
        $currentUser['user_id'],
        "Auto-created from customer return {$return['return_code']}. Reason: {$return['return_reason']}."
    ]);

    $disposalId = (int) $db->lastInsertId();

    // Insert disposal_items (one per return item)
    $itemIns = $db->prepare("
        INSERT INTO disposal_items
            (disposal_id, source_type, source_id, product_name, batch_code,
             quantity, unit, unit_cost, line_total, reason)
        VALUES (?, 'finished_goods', ?, ?, ?, ?, 'pcs', 0, ?, ?)
    ");
    foreach ($items as $item) {
        $itemIns->execute([
            $disposalId,
            $item['inventory_id'] ?? null,
            $item['product_name'] ?? $productName,
            $item['batch_code'] ?? '',
            $item['quantity'] ?? 0,
            $item['line_total'] ?? 0,
            "Customer return: {$return['return_reason']}",
        ]);
    }

    // Link disposal back to return
    $db->prepare("UPDATE customer_returns SET disposal_id = ? WHERE id = ?")
       ->execute([$disposalId, $return['id']]);

    logAudit($currentUser['user_id'], 'CREATE', 'disposals', $disposalId, null, [
        'disposal_code' => $disposalCode,
        'source' => "customer_return:{$return['id']}",
        'category' => $category,
        'qty' => $totalQty,
        'value' => $totalValue,
    ]);

    return $disposalId;
}
