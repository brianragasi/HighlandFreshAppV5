<?php
/**
 * Highland Fresh - Credit Memos API
 *
 * Manages customer credit memos for returns, price adjustments, and write-offs.
 * Part of the Return & Disposal system (Step 6: Finance & Reconciliation).
 *
 * GET    ?action=list         — Paginated list with filters
 * GET    ?id=N                — Single credit memo
 * GET    ?action=stats        — Dashboard stats
 * POST                        — Create credit memo (warehouse/finance)
 * PUT    ?action=approve&id=N — GM approve credit memo
 * PUT    ?action=apply&id=N   — Apply approved credit to an invoice
 * DELETE ?id=N                — Void a pending credit memo
 *
 * @package HighlandFresh
 * @version 1.0
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

$currentUser = Auth::requireRole(['warehouse_fg', 'qc_officer', 'finance_officer', 'general_manager', 'admin']);
$action = getParam('action', '');

$CREDIT_REASONS = [
    'return_damaged'    => 'Return — Damaged Goods',
    'return_expired'    => 'Return — Expired Goods',
    'return_quality'    => 'Return — Quality Issue',
    'price_adjustment'  => 'Price Adjustment',
    'overcharge'        => 'Overcharge Correction',
    'other'             => 'Other',
];

// ── Routing ─────────────────────────────────────────────────────────────────
try {
    $db = Database::getInstance()->getConnection();

    switch ($requestMethod) {
        case 'GET':    handleGet($db); break;
        case 'POST':   handlePost($db, $currentUser); break;
        case 'PUT':    handlePut($db, $currentUser); break;
        case 'DELETE': handleDelete($db, $currentUser); break;
        default: Response::error('Method not allowed', 405);
    }
} catch (PDOException $e) {
    error_log("Credit Memos API Error: " . $e->getMessage());
    Response::error('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    error_log("Credit Memos API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

// ═════════════════════════════════════════════════════════════════════════════
// GET
// ═════════════════════════════════════════════════════════════════════════════
function handleGet(PDO $db) {
    global $CREDIT_REASONS;
    $action = getParam('action', '');
    $id     = getParam('id');

    // ── Single credit memo ──
    if ($id) {
        $stmt = $db->prepare("
            SELECT cm.*,
                   u1.first_name AS initiated_by_name,
                   u2.first_name AS approved_by_name
            FROM credit_memos cm
            LEFT JOIN users u1 ON cm.initiated_by = u1.id
            LEFT JOIN users u2 ON cm.approved_by = u2.id
            WHERE cm.id = ?
        ");
        $stmt->execute([$id]);
        $cm = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cm) Response::notFound('Credit memo not found');
        Response::success($cm, 'Credit memo retrieved');
    }

    // ── Stats ──
    if ($action === 'stats') {
        $stats = $db->query("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'pending') AS pending,
                SUM(status = 'approved') AS approved,
                SUM(status = 'applied') AS applied,
                SUM(status = 'voided') AS voided,
                COALESCE(SUM(credit_amount), 0) AS total_value,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN credit_amount ELSE 0 END), 0) AS pending_value
            FROM credit_memos
        ")->fetch(PDO::FETCH_ASSOC);

        $byReason = $db->query("
            SELECT reason, COUNT(*) AS cnt, SUM(credit_amount) AS val
            FROM credit_memos WHERE status != 'voided'
            GROUP BY reason ORDER BY val DESC
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        Response::success([
            'stats' => $stats,
            'by_reason' => $byReason,
        ], 'Stats retrieved');
    }

    // ── Lookup ──
    if ($action === 'lookup') {
        Response::success([
            'reasons' => $CREDIT_REASONS,
        ], 'Lookup data');
    }

    // ── List ──
    $status = getParam('status');
    $customerId = getParam('customer_id');
    $page   = max(1, (int) getParam('page', 1));
    $limit  = max(1, min(100, (int) getParam('limit', 20)));
    $offset = ($page - 1) * $limit;

    $where = '1=1';
    $params = [];
    if ($status) {
        $where .= ' AND cm.status = ?';
        $params[] = $status;
    }
    if ($customerId) {
        $where .= ' AND cm.customer_id = ?';
        $params[] = (int) $customerId;
    }

    $cntStmt = $db->prepare("SELECT COUNT(*) FROM credit_memos cm WHERE $where");
    $cntStmt->execute($params);
    $total = (int) $cntStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT cm.*,
               u1.first_name AS initiated_by_name,
               u2.first_name AS approved_by_name
        FROM credit_memos cm
        LEFT JOIN users u1 ON cm.initiated_by = u1.id
        LEFT JOIN users u2 ON cm.approved_by = u2.id
        WHERE $where
        ORDER BY cm.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::paginated($rows, $total, $page, $limit, 'Credit memos retrieved');
}

// ═════════════════════════════════════════════════════════════════════════════
// POST — Create Credit Memo
// ═════════════════════════════════════════════════════════════════════════════
function handlePost(PDO $db, array $currentUser) {
    global $CREDIT_REASONS;

    $customerId   = (int) getParam('customer_id', 0);
    $customerName = trim(getParam('customer_name', ''));
    $creditAmount = (float) getParam('credit_amount', 0);
    $reason       = getParam('reason');
    $description  = trim(getParam('description', ''));
    $returnId     = getParam('return_id') ? (int) getParam('return_id') : null;
    $disposalId   = getParam('disposal_id') ? (int) getParam('disposal_id') : null;
    $deliveryId   = getParam('delivery_id') ? (int) getParam('delivery_id') : null;
    $drNumber     = trim(getParam('dr_number', ''));
    $orderId      = getParam('order_id') ? (int) getParam('order_id') : null;
    $invoiceId    = getParam('invoice_id') ? (int) getParam('invoice_id') : null;
    $notes        = trim(getParam('notes', ''));

    $errors = [];
    if ($customerId <= 0)      $errors['customer_id'] = 'Customer is required';
    if ($creditAmount <= 0)    $errors['credit_amount'] = 'Credit amount must be > 0';
    if (!isset($CREDIT_REASONS[$reason])) $errors['reason'] = 'Valid reason is required';
    if (!empty($errors)) Response::validationError($errors);

    // Resolve customer name if not provided
    if (empty($customerName)) {
        $custStmt = $db->prepare("SELECT name FROM customers WHERE id = ?");
        $custStmt->execute([$customerId]);
        $customerName = $custStmt->fetchColumn() ?: "Customer #{$customerId}";
    }

    // Generate credit code
    $today = date('Ymd');
    $codeStmt = $db->prepare("SELECT COUNT(*) FROM credit_memos WHERE credit_code LIKE ?");
    $codeStmt->execute(["CRM-{$today}-%"]);
    $cmCount = (int) $codeStmt->fetchColumn() + 1;
    $creditCode = "CRM-{$today}-" . str_pad($cmCount, 3, '0', STR_PAD_LEFT);

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO credit_memos
                (credit_code, customer_id, customer_name, return_id, disposal_id,
                 delivery_id, dr_number, order_id, credit_amount, reason,
                 description, status, initiated_by, initiated_at, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), ?)
        ");
        $stmt->execute([
            $creditCode, $customerId, $customerName,
            $returnId, $disposalId,
            $deliveryId, $drNumber, $orderId,
            $creditAmount, $reason,
            $description, $currentUser['user_id'], $notes,
        ]);

        $cmId = (int) $db->lastInsertId();

        logAudit($currentUser['user_id'], 'CREATE', 'credit_memos', $cmId, null, [
            'credit_code' => $creditCode,
            'customer' => $customerName,
            'amount' => $creditAmount,
            'reason' => $reason,
        ]);

        $db->commit();

        $created = $db->prepare("SELECT * FROM credit_memos WHERE id = ?");
        $created->execute([$cmId]);

        Response::created($created->fetch(PDO::FETCH_ASSOC),
            "Credit memo {$creditCode} created for {$customerName} — ₱" . number_format($creditAmount, 2) . ". Pending GM approval.");

    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// PUT — Approve / Apply / Void
// ═════════════════════════════════════════════════════════════════════════════
function handlePut(PDO $db, array $currentUser) {
    $action = getParam('action', '');
    $id     = (int) getParam('id', 0);

    if (!$id) Response::error('Credit memo ID is required', 400);

    $stmt = $db->prepare("SELECT * FROM credit_memos WHERE id = ?");
    $stmt->execute([$id]);
    $cm = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cm) Response::notFound('Credit memo not found');

    // ── GM Approve ──
    if ($action === 'approve') {
        requireActionRole($currentUser, ['general_manager', 'admin']);

        if ($cm['status'] !== 'pending') {
            Response::error('Only pending credit memos can be approved', 400);
        }

        $stepUpToken = getParam('step_up_token');
        Auth::requireStepUp($currentUser, 'credit_memo_approval', $stepUpToken);

        $approvalNotes = trim(getParam('approval_notes', ''));

        $db->beginTransaction();
        try {
            $db->prepare("
                UPDATE credit_memos SET
                    status = 'approved',
                    approved_by = ?,
                    approved_at = NOW(),
                    notes = CONCAT(COALESCE(notes, ''), '\n[Approved] ', ?)
                WHERE id = ?
            ")->execute([$currentUser['user_id'], $approvalNotes, $id]);

            // Update customer balance (credit = reduce what they owe)
            $db->prepare("
                UPDATE customers SET
                    current_balance = GREATEST(0, current_balance - ?),
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$cm['credit_amount'], $cm['customer_id']]);

            logAudit($currentUser['user_id'], 'APPROVE', 'credit_memos', $id, [
                'status' => 'pending',
            ], [
                'status' => 'approved',
                'approved_by' => $currentUser['user_id'],
                'amount' => $cm['credit_amount'],
            ]);

            $db->commit();
            Response::success(['id' => $id, 'status' => 'approved'],
                "Credit memo approved. ₱" . number_format($cm['credit_amount'], 2) . " credited to {$cm['customer_name']}.");

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ── Apply to Invoice ──
    if ($action === 'apply') {
        $invoiceId = (int) getParam('invoice_id', 0);
        if (!$invoiceId) Response::error('Invoice ID is required', 400);

        if (!in_array($cm['status'], ['approved', 'applied'], true)) {
            Response::error('Only approved credit memos can be applied', 400);
        }

        // Fetch invoice
        $invStmt = $db->prepare("SELECT * FROM sales_invoices WHERE id = ?");
        $invStmt->execute([$invoiceId]);
        $invoice = $invStmt->fetch(PDO::FETCH_ASSOC);
        if (!$invoice) Response::notFound('Invoice not found');

        if ($invoice['customer_id'] != $cm['customer_id']) {
            Response::error('Credit memo customer does not match invoice customer', 400);
        }

        $db->beginTransaction();
        try {
            $applyAmount = min($cm['credit_amount'], (float) $invoice['balance_due']);
            $newBalance = max(0, (float) $invoice['balance_due'] - $applyAmount);

            $db->prepare("
                UPDATE sales_invoices SET
                    amount_paid = amount_paid + ?,
                    balance_due = ?,
                    payment_status = CASE WHEN ? <= 0 THEN 'paid' ELSE 'partial' END,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$applyAmount, $newBalance, $newBalance, $invoiceId]);

            // Also update linked sales_order if present
            if (!empty($invoice['order_id'])) {
                $db->prepare("
                    UPDATE sales_orders SET
                        amount_paid = amount_paid + ?,
                        balance_due = GREATEST(0, balance_due - ?),
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([$applyAmount, $applyAmount, $invoice['order_id']]);
            }

            $db->prepare("
                UPDATE credit_memos SET
                    status = 'applied',
                    applied_to_invoice = ?,
                    applied_at = NOW(),
                    notes = CONCAT(COALESCE(notes, ''), '\n[Applied] Invoice #', ?, ' — ₱', ?)
                WHERE id = ?
            ")->execute([$invoiceId, $invoice['csi_number'] ?? $invoiceId, number_format($applyAmount, 2), $id]);

            logAudit($currentUser['user_id'], 'UPDATE', 'credit_memos', $id, [
                'status' => $cm['status'],
            ], [
                'status' => 'applied',
                'applied_to_invoice' => $invoiceId,
                'apply_amount' => $applyAmount,
            ]);

            $db->commit();
            Response::success(['id' => $id, 'status' => 'applied', 'applied_amount' => $applyAmount],
                "₱" . number_format($applyAmount, 2) . " applied to invoice.");

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    Response::error('Invalid action', 400);
}

// ═════════════════════════════════════════════════════════════════════════════
// DELETE — Void pending credit memo
// ═════════════════════════════════════════════════════════════════════════════
function handleDelete(PDO $db, array $currentUser) {
    $id = (int) getParam('id', 0);
    if (!$id) Response::error('Credit memo ID is required', 400);

    $stmt = $db->prepare("SELECT * FROM credit_memos WHERE id = ?");
    $stmt->execute([$id]);
    $cm = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cm) Response::notFound('Credit memo not found');

    if ($cm['status'] !== 'pending') {
        Response::error('Only pending credit memos can be voided', 400);
    }

    $db->prepare("UPDATE credit_memos SET status = 'voided' WHERE id = ?")->execute([$id]);

    logAudit($currentUser['user_id'], 'DELETE', 'credit_memos', $id, [
        'status' => $cm['status'],
        'credit_code' => $cm['credit_code'],
    ], ['status' => 'voided']);

    Response::success(null, 'Credit memo voided');
}
