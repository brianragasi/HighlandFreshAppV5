<?php
/**
 * Highland Fresh System - Finance Dashboard API
 * 
 * GET - Dashboard stats, payables, collections, farmer payment summaries
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/farmer_payment_helpers.php';

$currentUser = Auth::requireRole(['finance_officer', 'general_manager']);

$action = getParam('action', 'stats');

try {
    $db = Database::getInstance()->getConnection();
    ensureProcurementNotificationSupport($db);
    ensureFarmerPaymentTables($db);
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Finance Dashboard API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'stats':
            getDashboardStats($db);
            break;
        case 'payables_summary':
            getPayablesSummary($db);
            break;
        case 'collections_summary':
            getCollectionsSummary($db);
            break;
        case 'farmer_payment_summary':
            getFarmerPaymentSummary($db);
            break;
        case 'recent_disbursements':
            getRecentDisbursements($db);
            break;
        case 'receivables_aging':
            getReceivablesAging($db);
            break;
        case 'notifications':
            getFinanceNotifications($db);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function ensureProcurementNotificationSupport($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `procurement_notifications` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `target_role` VARCHAR(50) NOT NULL,
            `notification_type` VARCHAR(50) NOT NULL,
            `title` VARCHAR(150) NOT NULL,
            `message` TEXT NOT NULL,
            `reference_type` VARCHAR(50) DEFAULT NULL,
            `reference_id` INT(11) DEFAULT NULL,
            `is_read` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_procurement_notifications_role` (`target_role`, `is_read`),
            KEY `idx_procurement_notifications_reference` (`reference_type`, `reference_id`),
            KEY `idx_procurement_notifications_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function getFinanceNotifications($db) {
    $stmt = $db->prepare("
        SELECT *
        FROM procurement_notifications
        WHERE target_role = 'finance_officer'
          AND is_read = 0
        ORDER BY created_at DESC
        LIMIT 8
    ");
    $stmt->execute();
    Response::success($stmt->fetchAll(), 'Finance notifications retrieved');
}

function financeDashboardPayableTotalExpression(string $poAlias = 'po'): string {
    return "(SELECT COALESCE(SUM(
        (CASE
            WHEN {$poAlias}.status = 'partial_received' THEN IFNULL(poi.quantity_received, 0)
            WHEN IFNULL(poi.quantity_received, 0) > 0 THEN poi.quantity_received
            ELSE GREATEST(poi.quantity - IFNULL(poi.quantity_rejected, 0), 0)
        END) * poi.unit_price
    ), 0) FROM purchase_order_items poi WHERE poi.po_id = {$poAlias}.id)";
}

function financeDashboardPayableBalanceExpression(string $poAlias = 'po'): string {
    return 'GREATEST(' . financeDashboardPayableTotalExpression($poAlias)
        . " - COALESCE({$poAlias}.amount_paid, 0), 0)";
}

function financeDashboardDueDateExpression(string $poAlias = 'po'): string {
    return "CASE
        WHEN {$poAlias}.payment_terms = 'cash' THEN (
            SELECT DATE(COALESCE(rr_due.verified_at, rr_due.received_at))
            FROM receiving_reports rr_due
            WHERE rr_due.po_id = {$poAlias}.id
              AND rr_due.status IN ('verified', 'completed')
            ORDER BY rr_due.received_at DESC, rr_due.id DESC
            LIMIT 1
        )
        ELSE {$poAlias}.due_date
    END";
}

function getDashboardStats($db) {
    $stats = [];
    $payableBalance = financeDashboardPayableBalanceExpression('po');
    $dueDate = financeDashboardDueDateExpression('po');
    
    // === PAYABLES (What company owes) ===
    
    // Count only approved, received supplier liabilities using accepted quantity
    // and the remaining balance—the same definition used by Supplier Payables.
    $stmt = $db->query("
        SELECT COUNT(*) AS count, COALESCE(SUM(balance_due), 0) AS total
        FROM (
            SELECT {$payableBalance} AS balance_due
            FROM purchase_orders po
            WHERE po.payment_status IN ('unpaid', 'partial')
              AND po.status IN ('received', 'partial_received', 'closed')
              AND po.approved_by IS NOT NULL
              AND po.approved_at IS NOT NULL
        ) canonical_payables
        WHERE balance_due > 0
    ");
    $unpaidPOs = $stmt->fetch();
    $stats['unpaid_pos_count'] = (int) $unpaidPOs['count'];
    $stats['unpaid_pos_amount'] = (float) $unpaidPOs['total'];
    
    // Overdue means the contractual/COD due date passed and a canonical balance remains.
    $stmt = $db->query("
        SELECT COUNT(*) AS count, COALESCE(SUM(balance_due), 0) AS total
        FROM (
            SELECT {$payableBalance} AS balance_due, {$dueDate} AS effective_due_date
            FROM purchase_orders po
            WHERE po.payment_status IN ('unpaid', 'partial')
              AND po.status IN ('received', 'partial_received', 'closed')
              AND po.approved_by IS NOT NULL
              AND po.approved_at IS NOT NULL
        ) canonical_overdue
        WHERE balance_due > 0 AND effective_due_date < CURDATE()
    ");
    $overdue = $stmt->fetch();
    $stats['overdue_payables_count'] = (int) $overdue['count'];
    $stats['overdue_payables_amount'] = (float) $overdue['total'];
    
    // Supplier disbursements come from released payment ledger rows—not PO flags.
    $stmt = $db->query("
        SELECT COALESCE(SUM(amount_paid), 0) AS total
        FROM po_payments
        WHERE confirmed_release = 1
          AND YEAR(payment_date) = YEAR(CURDATE())
          AND MONTH(payment_date) = MONTH(CURDATE())
    ");
    $stats['monthly_disbursements'] = (float) $stmt->fetch()['total'];

    $stmt = $db->query("
        SELECT COALESCE(SUM(amount_paid), 0) as total
        FROM farmer_payments
        WHERE YEAR(payment_date) = YEAR(CURDATE())
        AND MONTH(payment_date) = MONTH(CURDATE())
        AND status = 'released'
    ");
    $stats['monthly_disbursements'] += (float) $stmt->fetch()['total'];

    // Keep pre-control records visible for reconciliation without treating them
    // as proof-backed disbursements or silently rewriting financial history.
    $stmt = $db->query("
        SELECT
            (SELECT COUNT(*) FROM po_payments
             WHERE confirmed_release <> 1 OR invoice_date IS NULL OR invoice_total IS NULL
                OR invoice_path IS NULL OR proof_path IS NULL) AS incomplete_payment_records,
            (SELECT COUNT(*) FROM purchase_orders po
             WHERE po.payment_status = 'paid'
               AND NOT EXISTS (SELECT 1 FROM po_payments pp WHERE pp.po_id = po.id)) AS paid_without_ledger
    ");
    $legacy = $stmt->fetch();
    $stats['legacy_payment_records'] = (int) ($legacy['incomplete_payment_records'] ?? 0)
        + (int) ($legacy['paid_without_ledger'] ?? 0);
    
    // === COLLECTIONS (What company is owed - read-only from Cashier) ===
    
    // Total outstanding receivables (unpaid sales invoices)
    $stmt = $db->query("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(balance_due), 0) as total
        FROM sales_invoices 
        WHERE payment_status IN ('unpaid', 'partial')
        AND status = 'active'
    ");
    $receivables = $stmt->fetch();
    $stats['outstanding_receivables_count'] = (int) $receivables['count'];
    $stats['outstanding_receivables_amount'] = (float) $receivables['total'];
    
    // Today's collections
    $stmt = $db->query("
        SELECT COALESCE(SUM(amount_collected), 0) as total, COUNT(*) as count
        FROM payment_collections 
        WHERE DATE(COALESCE(cleared_at, collected_at)) = CURDATE()
        AND status IN ('confirmed', 'cleared')
    ");
    $todayCollections = $stmt->fetch();
    $stats['today_collections_amount'] = (float) $todayCollections['total'];
    $stats['today_collections_count'] = (int) $todayCollections['count'];
    
    // This month collections
    $stmt = $db->query("
        SELECT COALESCE(SUM(amount_collected), 0) as total
        FROM payment_collections 
        WHERE YEAR(COALESCE(cleared_at, collected_at)) = YEAR(CURDATE())
        AND MONTH(COALESCE(cleared_at, collected_at)) = MONTH(CURDATE())
        AND status IN ('confirmed', 'cleared')
    ");
    $stats['monthly_collections'] = (float) $stmt->fetch()['total'];
    
    // === FARMER PAYMENTS ===
    
    // Pending farmer payments (accepted milk deliveries not yet paid)
    $stmt = $db->query("
        SELECT 
            COUNT(DISTINCT mr.farmer_id) as farmer_count,
            COALESCE(SUM(qmt.total_amount), 0) as total_amount,
            COALESCE(SUM(mr.accepted_liters), 0) as total_liters
        FROM milk_receiving mr
        " . farmerPaymentLatestAcceptedTestJoin() . "
        WHERE mr.status = 'accepted'
          AND NOT EXISTS (
              SELECT 1
              FROM farmer_payment_receipts fpr
              JOIN farmer_payments fp_reserved ON fp_reserved.id = fpr.farmer_payment_id
              WHERE fpr.receiving_id = mr.id
                AND fp_reserved.status IN ('pending_review', 'released')
          )
    ");
    $farmerPayments = $stmt->fetch();
    $stats['active_farmers'] = (int) $farmerPayments['farmer_count'];
    $stats['total_milk_procurement'] = (float) $farmerPayments['total_amount'];
    $stats['total_liters_received'] = (float) $farmerPayments['total_liters'];
    
    // Active suppliers count
    $stmt = $db->query("SELECT COUNT(*) as count FROM suppliers WHERE is_active = 1");
    $stats['active_suppliers'] = (int) $stmt->fetch()['count'];
    
    Response::success($stats, 'Finance dashboard stats retrieved');
}

function getPayablesSummary($db) {
    $payableBalance = financeDashboardPayableBalanceExpression('po');
    $stmt = $db->query("
        SELECT supplier_id, supplier_name, supplier_code, payment_terms,
               COUNT(po_id) AS po_count,
               COALESCE(SUM(balance_due), 0) AS total_amount,
               MIN(order_date) AS oldest_po_date,
               MAX(order_date) AS latest_po_date,
               GROUP_CONCAT(po_number ORDER BY order_date SEPARATOR ', ') AS po_numbers
        FROM (
            SELECT po.id AS po_id, po.po_number, po.order_date,
                   s.id AS supplier_id, s.supplier_name, s.supplier_code, s.payment_terms,
                   {$payableBalance} AS balance_due
            FROM purchase_orders po
            JOIN suppliers s ON po.supplier_id = s.id
            WHERE po.payment_status IN ('unpaid', 'partial')
              AND po.status IN ('received', 'partial_received', 'closed')
              AND po.approved_by IS NOT NULL
              AND po.approved_at IS NOT NULL
        ) canonical_supplier_payables
        WHERE balance_due > 0
        GROUP BY supplier_id, supplier_name, supplier_code, payment_terms
        ORDER BY total_amount DESC
    ");
    $payables = $stmt->fetchAll();
    
    Response::success($payables, 'Payables summary retrieved');
}

function getCollectionsSummary($db) {
    $period = getParam('period', 'month'); // today, week, month
    
    $effectiveDate = "COALESCE(pc.cleared_at, pc.collected_at)";
    $dateFilter = match($period) {
        'today' => "DATE({$effectiveDate}) = CURDATE()",
        'week' => "{$effectiveDate} >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
        'month' => "YEAR({$effectiveDate}) = YEAR(CURDATE()) AND MONTH({$effectiveDate}) = MONTH(CURDATE())",
        default => "YEAR({$effectiveDate}) = YEAR(CURDATE()) AND MONTH({$effectiveDate}) = MONTH(CURDATE())"
    };
    
    $stmt = $db->query("
        SELECT 
            pc.id,
            pc.or_number,
            pc.dr_number,
            pc.customer_name,
            pc.amount_collected,
            pc.payment_method,
            pc.balance_before,
            pc.balance_after,
            pc.status,
            pc.collected_at,
            pc.cleared_at,
            pc.notes,
            u.full_name as collected_by_name
        FROM payment_collections pc
        LEFT JOIN users u ON pc.collected_by = u.id
        WHERE {$dateFilter}
        ORDER BY COALESCE(pc.cleared_at, pc.collected_at) DESC
    ");
    $collections = $stmt->fetchAll();
    
    // Totals by payment method
    $dateFilterNoAlias = str_replace('pc.', '', $dateFilter);
    $stmt2 = $db->query("
        SELECT 
            payment_method,
            COUNT(*) as count,
            COALESCE(SUM(amount_collected), 0) as total
        FROM payment_collections 
        WHERE {$dateFilterNoAlias}
        AND status IN ('confirmed', 'cleared')
        GROUP BY payment_method
    ");
    $byMethod = $stmt2->fetchAll();
    
    Response::success([
        'collections' => $collections,
        'by_method' => $byMethod
    ], 'Collections summary retrieved');
}

function getFarmerPaymentSummary($db) {
    $farmers = getFarmerPaymentSummaryRows($db);
    
    Response::success($farmers, 'Farmer payment summary retrieved');
}

function getRecentDisbursements($db) {
    $limit = getParam('limit', 10);

    $stmt = $db->prepare("
        SELECT *
        FROM (
            SELECT
                pp.id,
                po.po_number,
                po.order_date,
                pp.amount_paid AS total_amount,
                CASE WHEN pp.confirmed_release = 1 THEN 'paid' ELSE 'unpaid' END AS payment_status,
                po.status,
                pp.payment_date,
                s.supplier_name,
                s.supplier_code,
                'supplier_payment' as transaction_type
            FROM po_payments pp
            JOIN purchase_orders po ON po.id = pp.po_id
            JOIN suppliers s ON po.supplier_id = s.id
            WHERE pp.confirmed_release = 1

            UNION ALL

            SELECT
                fp.id,
                fp.payment_code as po_number,
                fp.covered_from as order_date,
                fp.amount_paid as total_amount,
                'paid' as payment_status,
                'paid' as status,
                fp.payment_date,
                CONCAT(f.first_name, ' ', f.last_name) as supplier_name,
                f.farmer_code as supplier_code,
                'farmer_payment' as transaction_type
            FROM farmer_payments fp
            JOIN farmers f ON fp.farmer_id = f.id
            WHERE fp.status = 'released'
        ) recent
        ORDER BY payment_date DESC, id DESC
        LIMIT ?
    ");
    $stmt->execute([(int) $limit]);
    $disbursements = $stmt->fetchAll();
    
    Response::success($disbursements, 'Recent disbursements retrieved');
}

function getReceivablesAging($db) {
    // Aging of outstanding receivables
    $stmt = $db->query("
        SELECT 
            CASE 
                WHEN DATEDIFF(CURDATE(), si.due_date) <= 0 THEN 'current'
                WHEN DATEDIFF(CURDATE(), si.due_date) BETWEEN 1 AND 30 THEN '1-30'
                WHEN DATEDIFF(CURDATE(), si.due_date) BETWEEN 31 AND 60 THEN '31-60'
                WHEN DATEDIFF(CURDATE(), si.due_date) BETWEEN 61 AND 90 THEN '61-90'
                ELSE '91+'
            END as aging_bucket,
            COUNT(*) as invoice_count,
            COALESCE(SUM(si.balance_due), 0) as total_balance
        FROM sales_invoices si
        WHERE si.payment_status IN ('unpaid', 'partial')
        AND si.status = 'active'
        GROUP BY aging_bucket
        ORDER BY FIELD(aging_bucket, 'current', '1-30', '31-60', '61-90', '91+')
    ");
    $aging = $stmt->fetchAll();
    
    // Also get top debtors
    $stmt2 = $db->query("
        SELECT 
            fc.id as customer_id,
            fc.customer_name,
            COUNT(si.id) as invoice_count,
            COALESCE(SUM(si.balance_due), 0) as total_balance,
            MIN(si.due_date) as oldest_due_date
        FROM sales_invoices si
        JOIN fg_customers fc ON si.customer_id = fc.id
        WHERE si.payment_status IN ('unpaid', 'partial')
        AND si.status = 'active'
        GROUP BY fc.id, fc.customer_name
        ORDER BY total_balance DESC
        LIMIT 10
    ");
    $topDebtors = $stmt2->fetchAll();
    
    Response::success([
        'aging' => $aging,
        'top_debtors' => $topDebtors
    ], 'Receivables aging retrieved');
}
