<?php
/**
 * Highland Fresh System - Finance Payables API
 * 
 * GET  - List payables (unpaid POs), view details
 * POST - Record payment for PO
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/payment_reference_helpers.php';

$currentUser = Auth::requireRole(['finance_officer', 'general_manager']);

$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    ensurePayablesTables($db);
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action);
            break;
        case 'POST':
            handlePost($db, $action, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Finance Payables API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function tableExists($db, $tableName) {
    $stmt = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$tableName]);
    return (bool) $stmt->fetchColumn();
}

function payablesIndexExists($db, $tableName, $indexName) {
    $stmt = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$tableName, $indexName]);
    return (bool) $stmt->fetchColumn();
}

function payablesColumnLength($db, $tableName, $columnName) {
    $stmt = $db->prepare("
        SELECT CHARACTER_MAXIMUM_LENGTH
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$tableName, $columnName]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function ensurePayablesTables($db) {
    if (!auditColumnExists($db, 'purchase_orders', 'amount_paid')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `amount_paid` DECIMAL(12,2) DEFAULT 0.00 AFTER `total_amount`");
    }

    if (!auditColumnExists($db, 'purchase_orders', 'payment_terms')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `payment_terms` ENUM('cash','credit_7','credit_15','credit_30','credit_45','credit_60') DEFAULT 'cash' AFTER `payment_status`");
    }

    if (!auditColumnExists($db, 'purchase_orders', 'due_date')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `due_date` DATE DEFAULT NULL AFTER `payment_terms`");
    }

    if (!auditColumnExists($db, 'purchase_orders', 'last_payment_date')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `last_payment_date` DATE DEFAULT NULL AFTER `amount_paid`");
    }

    $db->exec("ALTER TABLE `purchase_orders` MODIFY COLUMN `payment_status` ENUM('unpaid','partial','paid','cancelled') DEFAULT 'unpaid'");

    if (!tableExists($db, 'po_payments')) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `po_payments` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `po_id` INT(11) NOT NULL,
                `payment_date` DATE NOT NULL,
                `amount_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `payment_method` VARCHAR(50) DEFAULT NULL,
                `reference_number` VARCHAR(100) DEFAULT NULL,
                `remarks` TEXT DEFAULT NULL,
                `created_by` INT(11) DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_po_payments_po` (`po_id`),
                KEY `idx_po_payments_date` (`payment_date`),
                CONSTRAINT `fk_po_payments_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_po_payments_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    $paymentColumns = [
        'reference_key' => "ALTER TABLE `po_payments` ADD COLUMN `reference_key` VARCHAR(191) NULL AFTER `reference_number`",
        'external_receipt_number' => "ALTER TABLE `po_payments` ADD COLUMN `external_receipt_number` VARCHAR(100) NULL AFTER `reference_key`",
        'invoice_number_snapshot' => "ALTER TABLE `po_payments` ADD COLUMN `invoice_number_snapshot` VARCHAR(100) NULL AFTER `external_receipt_number`",
        'invoice_date' => "ALTER TABLE `po_payments` ADD COLUMN `invoice_date` DATE NULL AFTER `invoice_number_snapshot`",
        'invoice_total' => "ALTER TABLE `po_payments` ADD COLUMN `invoice_total` DECIMAL(12,2) NULL AFTER `invoice_date`",
        'invoice_path' => "ALTER TABLE `po_payments` ADD COLUMN `invoice_path` VARCHAR(500) NULL AFTER `invoice_total`",
        'invoice_original_name' => "ALTER TABLE `po_payments` ADD COLUMN `invoice_original_name` VARCHAR(255) NULL AFTER `invoice_path`",
        'invoice_mime' => "ALTER TABLE `po_payments` ADD COLUMN `invoice_mime` VARCHAR(100) NULL AFTER `invoice_original_name`",
        'proof_path' => "ALTER TABLE `po_payments` ADD COLUMN `proof_path` VARCHAR(500) NULL AFTER `invoice_mime`",
        'proof_original_name' => "ALTER TABLE `po_payments` ADD COLUMN `proof_original_name` VARCHAR(255) NULL AFTER `proof_path`",
        'proof_mime' => "ALTER TABLE `po_payments` ADD COLUMN `proof_mime` VARCHAR(100) NULL AFTER `proof_original_name`",
        'confirmed_release' => "ALTER TABLE `po_payments` ADD COLUMN `confirmed_release` TINYINT(1) NOT NULL DEFAULT 0 AFTER `proof_mime`"
    ];
    foreach ($paymentColumns as $column => $sql) {
        if (!auditColumnExists($db, 'po_payments', $column)) {
            $db->exec($sql);
        }
    }
    if (payablesColumnLength($db, 'po_payments', 'invoice_number_snapshot') < 500) {
        $db->exec("ALTER TABLE `po_payments` MODIFY COLUMN `invoice_number_snapshot` VARCHAR(500) NULL");
    }

    // Old blank or duplicate references remain as historical evidence. Only new
    // records populate reference_key, which gives them database-level uniqueness.
    if (!payablesIndexExists($db, 'po_payments', 'uq_po_payments_reference_key')) {
        $db->exec("ALTER TABLE `po_payments` ADD UNIQUE KEY `uq_po_payments_reference_key` (`reference_key`)");
    }
}

function handleGet($db, $action) {
    global $currentUser;

    switch ($action) {
        case 'list':
            requireActionRole($currentUser, ['finance_officer', 'general_manager'], 'Access forbidden');
            getPayablesList($db);
            break;
        case 'overview':
            requireActionRole($currentUser, ['finance_officer', 'general_manager'], 'Access forbidden');
            getPayablesOverview($db);
            break;
        case 'detail':
            requireActionRole($currentUser, ['finance_officer', 'general_manager'], 'Access forbidden');
            getPayableDetail($db);
            break;
        case 'supplier_ledger':
            requireActionRole($currentUser, ['finance_officer', 'general_manager'], 'Access forbidden');
            getSupplierLedger($db);
            break;
        case 'payment_file':
            requireActionRole($currentUser, ['finance_officer', 'general_manager'], 'Access forbidden');
            streamSupplierPaymentFile($db);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function handlePost($db, $action, $user) {
    switch ($action) {
        case 'record_payment':
            requireActionRole($user, ['finance_officer', 'general_manager'], 'Access forbidden');
            recordPayment($db, $user);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function buildPayablesDueDateExpression(bool $hasReceivingReports): string {
    if (!$hasReceivingReports) {
        return "po.due_date";
    }

    $verifiedReceiptDate = "(
        SELECT DATE(COALESCE(rr_due.verified_at, rr_due.received_at))
        FROM receiving_reports rr_due
        WHERE rr_due.po_id = po.id
          AND rr_due.status IN ('verified', 'completed')
        ORDER BY rr_due.received_at DESC, rr_due.id DESC
        LIMIT 1
    )";

    return "CASE
        WHEN po.payment_terms = 'cash' THEN {$verifiedReceiptDate}
        ELSE po.due_date
    END";
}

function getPayablesOverview($db) {
    $hasReceivingReports = tableExists($db, 'receiving_reports');
    $effectiveDueDateExpr = buildPayablesDueDateExpression($hasReceivingReports);
    $rrExpr = $hasReceivingReports
        ? "(SELECT NULLIF(TRIM(rr_number), '') FROM receiving_reports WHERE po_id = po.id ORDER BY received_at DESC, id DESC LIMIT 1)"
        : "NULL";
    $invoiceExpr = $hasReceivingReports
        ? "(SELECT NULLIF(TRIM(invoice_number), '') FROM receiving_reports WHERE po_id = po.id ORDER BY received_at DESC, id DESC LIMIT 1)"
        : "NULL";
    $rrStatusExpr = $hasReceivingReports
        ? "(SELECT status FROM receiving_reports WHERE po_id = po.id ORDER BY received_at DESC, id DESC LIMIT 1)"
        : "NULL";
    $rrCountExpr = $hasReceivingReports
        ? "(SELECT COUNT(*) FROM receiving_reports WHERE po_id = po.id)"
        : "0";
    $rrReadyCountExpr = $hasReceivingReports
        ? "(SELECT COUNT(*) FROM receiving_reports WHERE po_id = po.id AND status IN ('verified', 'completed') AND COALESCE(NULLIF(TRIM(invoice_number), ''), '') <> '')"
        : "0";
    $payableExpr = "(SELECT COALESCE(SUM(
        CASE
            WHEN po.status = 'partial_received' THEN IFNULL(quantity_received, 0)
            WHEN IFNULL(quantity_received, 0) > 0 THEN quantity_received
            ELSE GREATEST(quantity - IFNULL(quantity_rejected, 0), 0)
        END * unit_price
    ), 0) FROM purchase_order_items WHERE po_id = po.id)";
    $overdueExpr = "CASE
        WHEN {$effectiveDueDateExpr} IS NOT NULL AND {$effectiveDueDateExpr} < CURDATE() THEN 1
        ELSE 0
    END";

    $stmt = $db->query("
        SELECT
            SUM(CASE WHEN balance_due > 0 THEN 1 ELSE 0 END) AS open_count,
            COALESCE(SUM(balance_due), 0) AS open_amount,
            SUM(CASE WHEN rr_count > 0 AND rr_ready_count = rr_count AND balance_due > 0 THEN 1 ELSE 0 END) AS ready_count,
            COALESCE(SUM(CASE WHEN rr_count > 0 AND rr_ready_count = rr_count THEN balance_due ELSE 0 END), 0) AS ready_amount,
            SUM(CASE WHEN (rr_count = 0 OR rr_ready_count <> rr_count) AND balance_due > 0 THEN 1 ELSE 0 END) AS waiting_count,
            COALESCE(SUM(CASE WHEN rr_count = 0 OR rr_ready_count <> rr_count THEN balance_due ELSE 0 END), 0) AS waiting_amount,
            SUM(CASE WHEN is_overdue = 1 AND balance_due > 0 THEN 1 ELSE 0 END) AS overdue_count,
            COALESCE(SUM(CASE WHEN is_overdue = 1 THEN balance_due ELSE 0 END), 0) AS overdue_amount
        FROM (
            SELECT
                GREATEST({$payableExpr} - COALESCE(po.amount_paid, 0), 0) AS balance_due,
                {$rrExpr} AS rr_number,
                {$invoiceExpr} AS invoice_number,
                {$rrStatusExpr} AS rr_status,
                {$rrCountExpr} AS rr_count,
                {$rrReadyCountExpr} AS rr_ready_count,
                {$overdueExpr} AS is_overdue
            FROM purchase_orders po
            WHERE po.status IN ('received', 'partial_received', 'closed')
              AND po.approved_by IS NOT NULL
              AND po.approved_at IS NOT NULL
              AND po.payment_status IN ('unpaid', 'partial')
        ) payable_overview
    ");

    Response::success($stmt->fetch(), 'Payables overview retrieved');
}

function getPayablesList($db) {
    $status = getParam('payment_status', '');
    $queue = getParam('queue', 'all');
    $search = getParam('search', '');
    $page = max(1, (int) getParam('page', 1));
    $limit = min(50, max(10, (int) getParam('limit', 15)));
    $offset = ($page - 1) * $limit;

    $hasReceivingReports = tableExists($db, 'receiving_reports');
    $effectiveDueDateExpr = buildPayablesDueDateExpression($hasReceivingReports);
    $rrSelect = $hasReceivingReports
        ? "(SELECT rr_number FROM receiving_reports WHERE po_id = po.id ORDER BY received_at DESC, id DESC LIMIT 1) as rr_number,"
        : "NULL as rr_number,";
    $invoiceSelect = $hasReceivingReports
        ? "(SELECT invoice_number FROM receiving_reports WHERE po_id = po.id ORDER BY received_at DESC, id DESC LIMIT 1) as invoice_number,"
        : "NULL as invoice_number,";
    $rrStatusSelect = $hasReceivingReports
        ? "(SELECT status FROM receiving_reports WHERE po_id = po.id ORDER BY received_at DESC, id DESC LIMIT 1) as rr_status,"
        : "NULL as rr_status,";
    $rrCountSelect = $hasReceivingReports
        ? "(SELECT COUNT(*) FROM receiving_reports WHERE po_id = po.id) as rr_count,"
        : "0 as rr_count,";
    $rrReadyCountSelect = $hasReceivingReports
        ? "(SELECT COUNT(*) FROM receiving_reports WHERE po_id = po.id AND status IN ('verified', 'completed') AND COALESCE(NULLIF(TRIM(invoice_number), ''), '') <> '') as rr_ready_count,"
        : "0 as rr_ready_count,";
    $payableBalanceExpr = "(SELECT COALESCE(SUM(
        CASE
            WHEN po.status = 'partial_received' THEN IFNULL(quantity_received, 0)
            WHEN IFNULL(quantity_received, 0) > 0 THEN quantity_received
            ELSE GREATEST(quantity - IFNULL(quantity_rejected, 0), 0)
        END * unit_price
    ), 0) FROM purchase_order_items WHERE po_id = po.id) - COALESCE(po.amount_paid, 0)";
    
    // Purchaser verification closes the procurement work, but Finance may still
    // need to settle the supplier invoice afterward.
    $where = ["po.status IN ('received','partial_received','closed')", "po.approved_by IS NOT NULL", "po.approved_at IS NOT NULL"];
    $params = [];
    
    if ($status) {
        $where[] = "po.payment_status = ?";
        $params[] = $status;
    } else {
        $where[] = "po.payment_status IN ('unpaid', 'partial')";
        $where[] = "{$payableBalanceExpr} > 0";
    }
    
    if ($search) {
        $where[] = "(
            po.po_number LIKE ?
            OR s.supplier_name LIKE ?
            OR EXISTS (
                SELECT 1
                FROM purchase_order_items poi_search
                WHERE poi_search.po_id = po.id
                AND poi_search.item_description LIKE ?
            )
        )";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    if ($queue === 'ready') {
        if ($hasReceivingReports) {
            $where[] = "EXISTS (SELECT 1 FROM receiving_reports WHERE po_id = po.id)";
            $where[] = "NOT EXISTS (
                SELECT 1 FROM receiving_reports
                WHERE po_id = po.id
                  AND (status NOT IN ('verified', 'completed') OR COALESCE(NULLIF(TRIM(invoice_number), ''), '') = '')
            )";
        } else {
            $where[] = "1 = 0";
        }
    } elseif ($queue === 'waiting_documents') {
        if ($hasReceivingReports) {
            $where[] = "(
                NOT EXISTS (SELECT 1 FROM receiving_reports WHERE po_id = po.id)
                OR EXISTS (
                    SELECT 1 FROM receiving_reports
                    WHERE po_id = po.id
                      AND (status NOT IN ('verified', 'completed') OR COALESCE(NULLIF(TRIM(invoice_number), ''), '') = '')
                )
            )";
        }
    } elseif ($queue === 'overdue') {
        $where[] = "({$effectiveDueDateExpr} IS NOT NULL AND {$effectiveDueDateExpr} < CURDATE())";
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Count
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM purchase_orders po 
        JOIN suppliers s ON po.supplier_id = s.id 
        WHERE {$whereClause}
    ");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetch()['total'];
    
    // Data
    $dataParams = array_merge($params, [$limit, $offset]);
    $stmt = $db->prepare("
        SELECT 
            po.id,
            po.po_number,
            po.order_date,
            po.expected_delivery,
            po.status,
            po.subtotal,
            po.vat_amount,
            po.total_amount,
            po.payment_status,
            po.payment_terms,
            po.due_date,
            {$effectiveDueDateExpr} as payment_due_date,
            po.amount_paid,
            po.last_payment_date,
            po.received_at,
            po.approved_at,
            po.notes,
            s.supplier_name,
            s.supplier_code,
            s.payment_terms as supplier_terms,
            u.full_name as created_by_name,
            (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = po.id) as item_count,
            (SELECT GROUP_CONCAT(item_description SEPARATOR ', ') FROM purchase_order_items WHERE po_id = po.id) as item_summary,
            (SELECT COALESCE(SUM(
                CASE 
                    WHEN po.status = 'partial_received' THEN IFNULL(quantity_received, 0)
                    WHEN IFNULL(quantity_received, 0) > 0 THEN quantity_received
                    ELSE GREATEST(quantity - IFNULL(quantity_rejected, 0), 0)
                END * unit_price
            ), 0) FROM purchase_order_items WHERE po_id = po.id) as payable_total,
            {$rrSelect}
            {$invoiceSelect}
            {$rrStatusSelect}
            {$rrCountSelect}
            {$rrReadyCountSelect}
            CASE
                WHEN po.payment_status != 'paid'
                     AND {$effectiveDueDateExpr} IS NOT NULL
                     AND {$effectiveDueDateExpr} < CURDATE()
                THEN 1 ELSE 0
            END as is_overdue
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE {$whereClause}
        ORDER BY 
            CASE po.payment_status 
                WHEN 'unpaid' THEN 1 
                WHEN 'partial' THEN 2 
                ELSE 3 
            END,
            CASE po.status
                WHEN 'received' THEN 1
                WHEN 'closed' THEN 2
                WHEN 'partial_received' THEN 3
                WHEN 'ordered' THEN 4
                WHEN 'approved' THEN 5
                ELSE 5
            END,
            po.order_date DESC,
            po.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($dataParams);
    $payables = $stmt->fetchAll();
    
    Response::paginated($payables, $total, $page, $limit, 'Payables list retrieved');
}

function getPayableDetail($db) {
    $id = getParam('id');
    if (!$id) Response::error('PO ID required', 400);
    
    $stmt = $db->prepare("
        SELECT 
            po.*,
            s.supplier_name,
            s.supplier_code,
            s.contact_person as supplier_contact,
            s.phone as supplier_phone,
            s.payment_terms as supplier_terms,
            u1.full_name as created_by_name,
            u2.full_name as approved_by_name,
            pr.pr_number,
            pr.purpose as pr_purpose,
            pr.requested_by as pr_requested_by,
            pr.approver_name as pr_approver_name,
            ur.full_name as pr_requested_by_name,
            ug.full_name as pr_approved_by_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN users u1 ON po.created_by = u1.id
        LEFT JOIN users u2 ON po.approved_by = u2.id
        LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
        LEFT JOIN users ur ON pr.requested_by = ur.id
        LEFT JOIN users ug ON pr.approved_by = ug.id
        WHERE po.id = ?
    ");
    $stmt->execute([$id]);
    $po = $stmt->fetch();
    
    if (!$po) Response::error('Purchase order not found', 404);
    
    // Get items
    $itemStmt = $db->prepare("
        SELECT 
            poi.*, 
            CASE 
                WHEN IFNULL(poi.quantity_received, 0) > 0 THEN poi.quantity_received
                ELSE GREATEST(poi.quantity - IFNULL(poi.quantity_rejected, 0), 0)
            END as payable_quantity,
            (CASE 
                WHEN IFNULL(poi.quantity_received, 0) > 0 THEN poi.quantity_received
                ELSE GREATEST(poi.quantity - IFNULL(poi.quantity_rejected, 0), 0)
            END * poi.unit_price) as payable_total
        FROM purchase_order_items poi
        WHERE poi.po_id = ?
        ORDER BY poi.id
    ");
    $itemStmt->execute([$id]);
    $po['items'] = $itemStmt->fetchAll();

    $hasReceivingReports = tableExists($db, 'receiving_reports');
    if ($hasReceivingReports) {
        $rrStmt = $db->prepare("
            SELECT rr.*,
                   u.full_name as received_by_name
            FROM receiving_reports rr
            LEFT JOIN users u ON rr.received_by = u.id
            WHERE rr.po_id = ?
            ORDER BY rr.received_at DESC, rr.id DESC
            LIMIT 1
        ");
        $rrStmt->execute([$id]);
        $latestRR = $rrStmt->fetch();
        if ($latestRR) {
            $po['latest_receiving_report'] = $latestRR;
            $po['rr_number'] = $latestRR['rr_number'] ?? null;
            $po['invoice_number'] = $latestRR['invoice_number'] ?? null;
            if (($po['payment_terms'] ?? '') === 'cash'
                && empty($po['due_date'])
                && in_array(($latestRR['status'] ?? ''), ['verified', 'completed'], true)) {
                $codDueAt = $latestRR['verified_at'] ?? $latestRR['received_at'] ?? null;
                $po['due_date'] = $codDueAt ? date('Y-m-d', strtotime($codDueAt)) : null;
            }
        }
        $allRRStmt = $db->prepare("
            SELECT rr.id, rr.rr_number, rr.invoice_number, rr.status, rr.received_at,
                   rr.verified_at, rr.total_ordered, rr.total_received, rr.total_rejected,
                   u.full_name as received_by_name
            FROM receiving_reports rr
            LEFT JOIN users u ON rr.received_by = u.id
            WHERE rr.po_id = ?
            ORDER BY rr.received_at, rr.id
        ");
        $allRRStmt->execute([$id]);
        $po['receiving_reports'] = $allRRStmt->fetchAll();
    }

    $payments = [];
    if (tableExists($db, 'po_payments')) {
        $paymentStmt = $db->prepare("
            SELECT pp.*, u.full_name as paid_by_name
            FROM po_payments pp
            LEFT JOIN users u ON pp.created_by = u.id
            WHERE pp.po_id = ?
            ORDER BY pp.payment_date DESC, pp.id DESC
        ");
        $paymentStmt->execute([$id]);
        $payments = $paymentStmt->fetchAll();
        foreach ($payments as &$payment) {
            $payment['has_invoice_file'] = !empty($payment['invoice_path']);
            $payment['has_proof_file'] = !empty($payment['proof_path']);
            unset($payment['invoice_path'], $payment['proof_path']);
        }
        unset($payment);
    }

    $payableTotal = 0;
    foreach ($po['items'] as $item) {
        $payableTotal += (float) ($item['payable_total'] ?? 0);
    }
    $amountPaid = (float) ($po['amount_paid'] ?? 0);
    if (!empty($payments)) {
        $amountPaid = array_reduce($payments, function ($sum, $p) {
            return $sum + (float) ($p['amount_paid'] ?? 0);
        }, 0);
    }
    $po['payments'] = $payments;
    $po['invoice_evidence'] = null;
    foreach ($payments as $payment) {
        if (!empty($payment['has_invoice_file'])) {
            $po['invoice_evidence'] = [
                'payment_id' => (int) $payment['id'],
                'invoice_number' => $payment['invoice_number_snapshot'] ?? null,
                'invoice_date' => $payment['invoice_date'] ?? null,
                'invoice_total' => $payment['invoice_total'] ?? null,
                'original_name' => $payment['invoice_original_name'] ?? null
            ];
            break;
        }
    }
    $po['payable_total'] = $payableTotal;
    $po['amount_paid'] = $amountPaid;
    $po['balance_due'] = max($payableTotal - $amountPaid, 0);
    
    Response::success($po, 'Payable detail retrieved');
}

function getSupplierLedger($db) {
    $supplierId = getParam('supplier_id');
    if (!$supplierId) Response::error('Supplier ID required', 400);
    
    $stmt = $db->prepare("
        SELECT 
            po.id,
            po.po_number,
            po.order_date,
            po.total_amount,
            po.payment_status,
            po.status,
            po.updated_at
        FROM purchase_orders po
        WHERE po.supplier_id = ?
        AND po.status != 'cancelled'
        ORDER BY po.order_date DESC
        LIMIT 50
    ");
    $stmt->execute([$supplierId]);
    $ledger = $stmt->fetchAll();
    
    Response::success($ledger, 'Supplier ledger retrieved');
}

function recordPayment($db, $user) {
    $data = getParams();
    $poId = (int) ($data['po_id'] ?? 0);
    if ($poId <= 0) Response::error('PO ID required', 400);

    $paymentMethod = trim((string) ($data['payment_method'] ?? ''));
    if (!in_array($paymentMethod, ['cash', 'check', 'bank_transfer'], true)) {
        Response::error('Choose Cash, Check, or Bank Transfer', 400);
    }
    $paymentDate = normalizeSupplierPaymentDate($data['payment_date'] ?? null, 'Payment date');
    $invoiceDate = normalizeSupplierPaymentDate($data['invoice_date'] ?? null, 'Invoice date');
    if ($paymentDate > date('Y-m-d')) Response::error('Payment date cannot be in the future', 400);
    if ($invoiceDate > $paymentDate) Response::error('Invoice date cannot be after the payment date', 400);

    $externalReceiptNumber = trim((string) ($data['external_receipt_number'] ?? ''));
    if ($paymentMethod === 'cash') {
        if (!financeReferenceIsValid($externalReceiptNumber, false)) {
            Response::error('Official Receipt number must be 3 to 100 valid characters when provided', 400);
        }
        $referenceNumber = financeGenerateCashVoucherNumber($paymentDate);
    } else {
        $referenceNumber = trim((string) ($data['reference_number'] ?? ''));
        if (!financeReferenceIsValid($referenceNumber, true)) {
            Response::error('Enter the 3 to 100 character check or bank-transfer reference', 400);
        }
        $externalReceiptNumber = '';
    }
    $referenceKey = mb_strtolower($paymentMethod . ':' . preg_replace('/\s+/', '', $referenceNumber));

    try {
        $paymentAmount = hfParseBusinessDecimal($data['payment_amount'] ?? null, 'Payment amount', 0.01, 9999999999.99, 2);
        $invoiceTotal = hfParseBusinessDecimal($data['invoice_total'] ?? null, 'Supplier invoice total', 0.01, 9999999999.99, 2);
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage(), 400);
    }
    if (!isTruthy($data['confirm_release'] ?? null)) {
        Response::error('Confirm that the funds were released using the selected method', 400);
    }

    Auth::requireStepUp($user, 'payment_release', $data['step_up_token'] ?? null);

    $newInvoiceFile = null;
    $newProofFile = null;
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$poId]);
        $po = $stmt->fetch();
        if (!$po) supplierPaymentFail($db, 'Purchase order not found', 404);
        if (!in_array($po['status'], ['received', 'partial_received', 'closed'], true)) {
            supplierPaymentFail($db, 'Only received purchase orders are payable', 400);
        }
        if (empty($po['approved_by']) || empty($po['approved_at'])) {
            supplierPaymentFail($db, 'PO must be GM-approved before payment', 400);
        }
        if ((int) $po['approved_by'] === (int) $user['user_id']) {
            supplierPaymentFail($db, 'The person who approved this PO cannot also release its payment', 403);
        }
        if (in_array($po['payment_status'], ['paid', 'cancelled'], true)) {
            supplierPaymentFail($db, $po['payment_status'] === 'paid' ? 'This PO is already fully paid' : 'Payment is cancelled for this PO', 409);
        }

        $rrStmt = $db->prepare("
            SELECT id, rr_number, invoice_number, status, received_at, verified_at
            FROM receiving_reports
            WHERE po_id = ?
            ORDER BY received_at, id
            FOR UPDATE
        ");
        $rrStmt->execute([$poId]);
        $receivingReports = $rrStmt->fetchAll();
        if (!$receivingReports) supplierPaymentFail($db, 'Receiving Report is required before payment', 400);
        $invoiceNumbers = [];
        $releaseReadyDate = '';
        foreach ($receivingReports as $report) {
            if (!in_array($report['status'] ?? '', ['verified', 'completed'], true)) {
                supplierPaymentFail($db, 'Every Receiving Report must be verified before payment. ' . ($report['rr_number'] ?? 'One report') . ' is not ready.', 400);
            }
            $reportInvoice = trim((string) ($report['invoice_number'] ?? ''));
            if ($reportInvoice === '') {
                supplierPaymentFail($db, 'Every Receiving Report needs its supplier invoice number before payment. ' . ($report['rr_number'] ?? 'One report') . ' is missing it.', 400);
            }
            $invoiceNumbers[] = $reportInvoice;
            $reportReadyDate = substr((string) ($report['verified_at'] ?: $report['received_at']), 0, 10);
            if ($reportReadyDate > $releaseReadyDate) $releaseReadyDate = $reportReadyDate;
        }
        if ($releaseReadyDate && $paymentDate < $releaseReadyDate) {
            supplierPaymentFail($db, 'Payment date cannot be earlier than the latest verified receiving date', 400);
        }

        $payableStmt = $db->prepare("
            SELECT COALESCE(SUM(
                CASE
                    WHEN ? = 'partial_received' THEN IFNULL(quantity_received, 0)
                    WHEN IFNULL(quantity_received, 0) > 0 THEN quantity_received
                    ELSE GREATEST(quantity - IFNULL(quantity_rejected, 0), 0)
                END * unit_price
            ), 0)
            FROM purchase_order_items
            WHERE po_id = ?
        ");
        $payableStmt->execute([$po['status'], $poId]);
        $payableTotal = round((float) ($payableStmt->fetchColumn() ?? 0), 2);
        if ($payableTotal <= 0) supplierPaymentFail($db, 'Payable amount is not available for this PO', 400);
        if (abs($invoiceTotal - $payableTotal) > 0.01) {
            supplierPaymentFail($db, 'Supplier invoice total must match the system payable total of PHP ' . number_format($payableTotal, 2), 400);
        }

        // Locking the PO serializes payment attempts. Locking its payment rows
        // also makes the recalculated balance explicit and auditable.
        $paidStmt = $db->prepare('SELECT id, amount_paid FROM po_payments WHERE po_id = ? FOR UPDATE');
        $paidStmt->execute([$poId]);
        $amountPaid = 0.0;
        foreach ($paidStmt->fetchAll() as $existingPayment) {
            $amountPaid += (float) $existingPayment['amount_paid'];
        }
        $balanceDue = round(max($payableTotal - $amountPaid, 0), 2);
        if ($paymentAmount > $balanceDue + 0.001) supplierPaymentFail($db, 'Payment exceeds the current remaining balance', 409);

        $normalizedReference = mb_strtolower(preg_replace('/\s+/', '', $referenceNumber));
        $duplicateStmt = $db->prepare("
            SELECT id
            FROM po_payments
            WHERE reference_key = ?
               OR (payment_method = ? AND LOWER(REPLACE(TRIM(reference_number), ' ', '')) = ?)
            LIMIT 1
            FOR UPDATE
        ");
        $duplicateStmt->execute([$referenceKey, $paymentMethod, $normalizedReference]);
        if ($duplicateStmt->fetchColumn()) {
            supplierPaymentFail($db, 'That reference has already been used for this payment method', 409);
        }

        $invoiceNumber = implode(', ', array_values(array_unique($invoiceNumbers)));
        $evidenceStmt = $db->prepare("
            SELECT invoice_path, invoice_original_name, invoice_mime
            FROM po_payments
            WHERE po_id = ?
              AND invoice_number_snapshot = ?
              AND ABS(invoice_total - ?) <= 0.01
              AND invoice_path IS NOT NULL
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");
        $evidenceStmt->execute([$poId, $invoiceNumber, $invoiceTotal]);
        $invoiceEvidence = $evidenceStmt->fetch() ?: null;
        if (isset($_FILES['invoice_file']) && ($_FILES['invoice_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $newInvoiceFile = saveSupplierPaymentFile($_FILES['invoice_file'], 'invoice');
            if (!$newInvoiceFile) supplierPaymentFail($db, 'Attach a valid PDF, JPG, or PNG supplier invoice (maximum 5 MB)', 400);
            $invoiceEvidence = $newInvoiceFile;
        }
        if (!$invoiceEvidence) {
            supplierPaymentFail($db, 'Attach the supplier invoice before releasing this payment', 400);
        }

        $newProofFile = saveSupplierPaymentFile($_FILES['proof_file'] ?? null, 'proof');
        if (!$newProofFile) {
            supplierPaymentFail($db, 'Attach a PDF, JPG, or PNG payment proof (maximum 5 MB)', 400, [$newInvoiceFile['path'] ?? null]);
        }

        $insertStmt = $db->prepare("
            INSERT INTO po_payments
            (
                po_id, payment_date, amount_paid, payment_method, reference_number, reference_key,
                external_receipt_number,
                invoice_number_snapshot, invoice_date, invoice_total,
                invoice_path, invoice_original_name, invoice_mime,
                proof_path, proof_original_name, proof_mime,
                confirmed_release, remarks, created_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
        ");
        $insertStmt->execute([
            $poId, $paymentDate, $paymentAmount, $paymentMethod, $referenceNumber, $referenceKey,
            $externalReceiptNumber ?: null,
            $invoiceNumber, $invoiceDate, $invoiceTotal,
            $invoiceEvidence['path'] ?? $invoiceEvidence['invoice_path'],
            $invoiceEvidence['original_name'] ?? $invoiceEvidence['invoice_original_name'],
            $invoiceEvidence['mime'] ?? $invoiceEvidence['invoice_mime'],
            $newProofFile['path'], $newProofFile['original_name'], $newProofFile['mime'],
            trim((string) ($data['notes'] ?? '')) ?: null, $user['user_id']
        ]);
        $paymentId = (int) $db->lastInsertId();

        $newAmountPaid = round($amountPaid + $paymentAmount, 2);
        $newStatus = $newAmountPaid >= $payableTotal - 0.01 ? 'paid' : 'partial';
        $db->prepare("
            UPDATE purchase_orders
            SET payment_status = ?, amount_paid = ?, last_payment_date = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$newStatus, $newAmountPaid, $paymentDate, $poId]);

        logAudit($user['user_id'], 'PAYMENT_RELEASE', 'po_payments', $paymentId, null, [
            'po_id' => $poId,
            'po_number' => $po['po_number'],
            'payment_status' => $newStatus,
            'payment_amount' => $paymentAmount,
            'payment_method' => $paymentMethod,
            'reference_number' => $referenceNumber,
            'external_receipt_number' => $externalReceiptNumber ?: null,
            'payment_date' => $paymentDate,
            'invoice_number' => $invoiceNumber,
            'invoice_total' => $invoiceTotal,
            'invoice_attached' => true,
            'payment_proof_attached' => true,
            'release_confirmed' => true,
            'step_up_verified' => true
        ]);
        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        deleteSupplierPaymentFile($newInvoiceFile['path'] ?? null);
        deleteSupplierPaymentFile($newProofFile['path'] ?? null);
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            Response::error('That reference has already been used for this payment method', 409);
        }
        throw $e;
    }

    Response::success([
        'payment_id' => $paymentId,
        'po_id' => $poId,
        'po_number' => $po['po_number'],
        'payment_reference' => $referenceNumber,
        'external_receipt_number' => $externalReceiptNumber ?: null,
        'payment_amount' => $paymentAmount,
        'balance_due' => max($balanceDue - $paymentAmount, 0)
    ], 'Payment and supporting evidence recorded successfully');
}

function isTruthy($value) {
    return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
}

function normalizeSupplierPaymentDate($value, $label) {
    $raw = trim((string) $value);
    $date = DateTime::createFromFormat('!Y-m-d', $raw);
    $errors = DateTime::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d') !== $raw) {
        Response::error($label . ' must be a valid date', 400);
    }
    return $raw;
}

function supplierPaymentFail($db, $message, $status = 400, $newFiles = []) {
    if ($db->inTransaction()) $db->rollBack();
    foreach ($newFiles as $path) deleteSupplierPaymentFile($path);
    Response::error($message, $status);
}

function saveSupplierPaymentFile($file, $kind) {
    if (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) return false;
    if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > 5 * 1024 * 1024) return false;
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');
    if ($finfo) finfo_close($finfo);
    $extensions = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($extensions[$mime])) return false;

    $relativeDir = 'uploads/finance/supplier_payments/' . date('Y') . '/' . date('m');
    $absoluteDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
    if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) return false;
    $guardFile = $absoluteDir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($guardFile)) {
        @file_put_contents($guardFile, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl|py|sh)$\">\n  Require all denied\n</FilesMatch>\n");
    }
    $filename = ($kind === 'invoice' ? 'inv_' : 'pay_') . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!@move_uploaded_file($file['tmp_name'], $absoluteDir . DIRECTORY_SEPARATOR . $filename)) return false;
    return [
        'path' => $relativeDir . '/' . $filename,
        'original_name' => mb_substr(basename((string) ($file['name'] ?? $kind)), 0, 255),
        'mime' => $mime
    ];
}

function deleteSupplierPaymentFile($relativePath) {
    if (!$relativePath || !preg_match('#^uploads/finance/supplier_payments/#', $relativePath)) return;
    $absolute = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($absolute)) @unlink($absolute);
}

function streamSupplierPaymentFile($db) {
    $paymentId = (int) getParam('id', 0);
    $kind = getParam('kind', 'proof');
    if ($paymentId <= 0 || !in_array($kind, ['invoice', 'proof'], true)) Response::error('Valid payment file is required', 400);
    $prefix = $kind === 'invoice' ? 'invoice' : 'proof';
    $stmt = $db->prepare("SELECT {$prefix}_path file_path, {$prefix}_original_name original_name, {$prefix}_mime mime FROM po_payments WHERE id = ?");
    $stmt->execute([$paymentId]);
    $file = $stmt->fetch();
    if (!$file || empty($file['file_path']) || !preg_match('#^uploads/finance/supplier_payments/#', $file['file_path'])) {
        Response::error(ucfirst($kind) . ' file not found', 404);
    }
    $absolute = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file['file_path']);
    if (!is_file($absolute)) Response::error(ucfirst($kind) . ' file is missing', 404);
    header('Content-Type: ' . ($file['mime'] ?: 'application/octet-stream'));
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file['original_name'] ?: $kind) . '"');
    header('Content-Length: ' . filesize($absolute));
    readfile($absolute);
    exit;
}
