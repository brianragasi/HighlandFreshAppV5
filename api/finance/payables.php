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
}

function handleGet($db, $action) {
    global $currentUser;

    switch ($action) {
        case 'list':
            requireActionRole($currentUser, ['finance_officer', 'general_manager'], 'Access forbidden');
            getPayablesList($db);
            break;
        case 'detail':
            requireActionRole($currentUser, ['finance_officer', 'general_manager'], 'Access forbidden');
            getPayableDetail($db);
            break;
        case 'supplier_ledger':
            requireActionRole($currentUser, ['finance_officer', 'general_manager'], 'Access forbidden');
            getSupplierLedger($db);
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

function getPayablesList($db) {
    $status = getParam('payment_status', '');
    $search = getParam('search', '');
    $page = max(1, (int) getParam('page', 1));
    $limit = min(50, max(10, (int) getParam('limit', 15)));
    $offset = ($page - 1) * $limit;

    $hasReceivingReports = tableExists($db, 'receiving_reports');
    $rrSelect = $hasReceivingReports
        ? "(SELECT rr_number FROM receiving_reports WHERE po_id = po.id ORDER BY received_at DESC, id DESC LIMIT 1) as rr_number,"
        : "NULL as rr_number,";
    $invoiceSelect = $hasReceivingReports
        ? "(SELECT invoice_number FROM receiving_reports WHERE po_id = po.id ORDER BY received_at DESC, id DESC LIMIT 1) as invoice_number,"
        : "NULL as invoice_number,";
    
    $where = ["po.status IN ('received','partial_received')", "po.approved_by IS NOT NULL", "po.approved_at IS NOT NULL"]; 
    $params = [];
    
    if ($status) {
        $where[] = "po.payment_status = ?";
        $params[] = $status;
    } else {
        $where[] = "po.payment_status IN ('unpaid', 'partial')";
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
            po.amount_paid,
            po.last_payment_date,
            po.received_at,
            po.approved_at,
            po.notes,
            s.supplier_name,
            s.supplier_code,
            s.payment_terms,
            u.full_name as created_by_name,
            (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = po.id) as item_count,
            (SELECT GROUP_CONCAT(item_description SEPARATOR ', ') FROM purchase_order_items WHERE po_id = po.id) as item_summary,
            (SELECT COALESCE(SUM(
                CASE 
                    WHEN IFNULL(quantity_received, 0) > 0 THEN quantity_received
                    ELSE GREATEST(quantity - IFNULL(quantity_rejected, 0), 0)
                END * unit_price
            ), 0) FROM purchase_order_items WHERE po_id = po.id) as payable_total,
            {$rrSelect}
            {$invoiceSelect}
            CASE 
                WHEN po.payment_status != 'paid' AND po.due_date IS NOT NULL AND po.due_date < CURDATE()
                THEN 1
                WHEN po.status = 'received' AND po.payment_status != 'paid' 
                     AND po.due_date IS NULL AND po.expected_delivery < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
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
                WHEN 'partial_received' THEN 2
                WHEN 'ordered' THEN 3
                WHEN 'approved' THEN 4
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
            u2.full_name as approved_by_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN users u1 ON po.created_by = u1.id
        LEFT JOIN users u2 ON po.approved_by = u2.id
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
        }
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
    $data = getRequestBody();
    
    $poId = $data['po_id'] ?? null;
    if (!$poId) Response::error('PO ID required', 400);

    $stepUpToken = $data['step_up_token'] ?? null;
    Auth::requireStepUp($user, 'payment_release', $stepUpToken);
    
    // Get PO
    $stmt = $db->prepare("SELECT * FROM purchase_orders WHERE id = ?");
    $stmt->execute([$poId]);
    $po = $stmt->fetch();
    
    if (!$po) Response::error('Purchase order not found', 404);
    if (in_array($po['status'], ['cancelled', 'rejected'])) Response::error('Cannot release payment for rejected or cancelled POs', 400);
    if (!in_array($po['status'], ['received', 'partial_received'])) Response::error('Only received or partially received POs are payable', 400);
    if (empty($po['approved_by']) || empty($po['approved_at'])) Response::error('PO must be GM-approved before payment', 400);
    if ($po['payment_status'] === 'paid') Response::error('This PO is already fully paid', 400);
    if ($po['payment_status'] === 'cancelled') Response::error('Payment is cancelled for this PO', 400);

    $verifyPo = isTruthy($data['verify_po'] ?? null);
    $verifyRR = isTruthy($data['verify_rr'] ?? null);
    $verifyInvoice = isTruthy($data['verify_invoice'] ?? null);
    if (!$verifyPo || !$verifyRR || !$verifyInvoice) {
        Response::error('Payment requires verification of PO, Receiving Report, and invoice', 400);
    }

    $paymentDate = $data['payment_date'] ?? date('Y-m-d');
    $paymentAmount = (float) ($data['payment_amount'] ?? 0);
    if ($paymentAmount <= 0) Response::error('Payment amount must be greater than zero', 400);

    $latestRR = null;
    if (tableExists($db, 'receiving_reports')) {
        $rrStmt = $db->prepare("SELECT rr_number, invoice_number FROM receiving_reports WHERE po_id = ? ORDER BY received_at DESC, id DESC LIMIT 1");
        $rrStmt->execute([$poId]);
        $latestRR = $rrStmt->fetch();
    }
    if (!$latestRR) Response::error('Receiving Report is required before payment', 400);
    if (empty($latestRR['invoice_number'])) Response::error('Invoice number is required before payment', 400);

    $payableStmt = $db->prepare("
        SELECT COALESCE(SUM(
            CASE 
                WHEN IFNULL(quantity_received, 0) > 0 THEN quantity_received
                ELSE GREATEST(quantity - IFNULL(quantity_rejected, 0), 0)
            END * unit_price
        ), 0) as payable_total
        FROM purchase_order_items
        WHERE po_id = ?
    ");
    $payableStmt->execute([$poId]);
    $payableTotal = (float) ($payableStmt->fetchColumn() ?? 0);
    if ($payableTotal <= 0) Response::error('Payable amount is not available for this PO', 400);

    $paidStmt = $db->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM po_payments WHERE po_id = ?");
    $paidStmt->execute([$poId]);
    $amountPaid = (float) ($paidStmt->fetchColumn() ?? 0);

    $balanceDue = max($payableTotal - $amountPaid, 0);
    if ($paymentAmount > $balanceDue) Response::error('Payment exceeds remaining balance', 400);

    $db->beginTransaction();
    try {
        $insertStmt = $db->prepare("
            INSERT INTO po_payments
            (po_id, payment_date, amount_paid, payment_method, reference_number, remarks, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([
            $poId,
            $paymentDate,
            $paymentAmount,
            $data['payment_method'] ?? 'cash',
            $data['reference_number'] ?? null,
            $data['notes'] ?? null,
            $user['user_id']
        ]);

        $newAmountPaid = $amountPaid + $paymentAmount;
        $newStatus = $newAmountPaid >= $payableTotal ? 'paid' : 'partial';

        $updateStmt = $db->prepare("
            UPDATE purchase_orders
            SET payment_status = ?,
                amount_paid = ?,
                last_payment_date = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$newStatus, $newAmountPaid, $paymentDate, $poId]);

        logAudit($user['user_id'], 'PAYMENT_RELEASE', 'purchase_orders', $poId, [
            'payment_status' => $po['payment_status'],
            'amount_paid' => $amountPaid
        ], [
            'payment_status' => $newStatus,
            'amount_paid' => $newAmountPaid,
            'payment_method' => $data['payment_method'] ?? 'cash',
            'reference_number' => $data['reference_number'] ?? null,
            'payment_date' => $paymentDate,
            'payment_amount' => $paymentAmount,
            'verify_po' => $verifyPo,
            'verify_rr' => $verifyRR,
            'verify_invoice' => $verifyInvoice
        ]);

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

    Response::success([
        'po_id' => $poId,
        'po_number' => $po['po_number'],
        'payment_amount' => $paymentAmount,
        'balance_due' => max($balanceDue - $paymentAmount, 0)
    ], 'Payment recorded successfully');
}

function isTruthy($value) {
    return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
}
