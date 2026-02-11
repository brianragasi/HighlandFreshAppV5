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

$currentUser = Auth::requireRole(['finance_officer', 'general_manager']);

$action = getParam('action', 'stats');

try {
    $db = Database::getInstance()->getConnection();
    
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
        default:
            Response::error('Invalid action', 400);
    }
}

function getDashboardStats($db) {
    $stats = [];
    
    // === PAYABLES (What company owes) ===
    
    // Total unpaid supplier POs
    $stmt = $db->query("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(total_amount), 0) as total
        FROM purchase_orders 
        WHERE payment_status IN ('unpaid', 'partial')
        AND status != 'cancelled'
    ");
    $unpaidPOs = $stmt->fetch();
    $stats['unpaid_pos_count'] = (int) $unpaidPOs['count'];
    $stats['unpaid_pos_amount'] = (float) $unpaidPOs['total'];
    
    // Overdue POs (received but not paid, past expected delivery + 30 days)
    $stmt = $db->query("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM purchase_orders 
        WHERE payment_status IN ('unpaid', 'partial')
        AND status IN ('received', 'partial_received')
        AND expected_delivery < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $overdue = $stmt->fetch();
    $stats['overdue_payables_count'] = (int) $overdue['count'];
    $stats['overdue_payables_amount'] = (float) $overdue['total'];
    
    // Monthly disbursements (POs paid this month)
    $stmt = $db->query("
        SELECT COALESCE(SUM(total_amount), 0) as total
        FROM purchase_orders 
        WHERE payment_status = 'paid'
        AND status != 'cancelled'
        AND YEAR(updated_at) = YEAR(CURDATE()) 
        AND MONTH(updated_at) = MONTH(CURDATE())
    ");
    $stats['monthly_disbursements'] = (float) $stmt->fetch()['total'];
    
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
        WHERE DATE(collected_at) = CURDATE()
        AND status = 'confirmed'
    ");
    $todayCollections = $stmt->fetch();
    $stats['today_collections_amount'] = (float) $todayCollections['total'];
    $stats['today_collections_count'] = (int) $todayCollections['count'];
    
    // This month collections
    $stmt = $db->query("
        SELECT COALESCE(SUM(amount_collected), 0) as total
        FROM payment_collections 
        WHERE YEAR(collected_at) = YEAR(CURDATE()) 
        AND MONTH(collected_at) = MONTH(CURDATE())
        AND status = 'confirmed'
    ");
    $stats['monthly_collections'] = (float) $stmt->fetch()['total'];
    
    // === FARMER PAYMENTS ===
    
    // Pending farmer payments (accepted milk deliveries not yet paid)
    // We track this by checking milk_receiving entries that are accepted
    $stmt = $db->query("
        SELECT 
            COUNT(DISTINCT mr.farmer_id) as farmer_count,
            COALESCE(SUM(qmt.total_amount), 0) as total_amount,
            COALESCE(SUM(mr.accepted_liters), 0) as total_liters
        FROM milk_receiving mr
        JOIN qc_milk_tests qmt ON qmt.receiving_id = mr.id AND qmt.is_accepted = 1
        WHERE mr.status = 'accepted'
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
    // Get all unpaid/partial POs grouped by supplier
    $stmt = $db->query("
        SELECT 
            s.id as supplier_id,
            s.supplier_name,
            s.supplier_code,
            s.payment_terms,
            COUNT(po.id) as po_count,
            COALESCE(SUM(po.total_amount), 0) as total_amount,
            MIN(po.order_date) as oldest_po_date,
            MAX(po.order_date) as latest_po_date,
            GROUP_CONCAT(po.po_number ORDER BY po.order_date SEPARATOR ', ') as po_numbers
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        WHERE po.payment_status IN ('unpaid', 'partial')
        AND po.status != 'cancelled'
        GROUP BY s.id
        ORDER BY total_amount DESC
    ");
    $payables = $stmt->fetchAll();
    
    Response::success($payables, 'Payables summary retrieved');
}

function getCollectionsSummary($db) {
    $period = getParam('period', 'month'); // today, week, month
    
    $dateFilter = match($period) {
        'today' => "DATE(pc.collected_at) = CURDATE()",
        'week' => "pc.collected_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
        'month' => "YEAR(pc.collected_at) = YEAR(CURDATE()) AND MONTH(pc.collected_at) = MONTH(CURDATE())",
        default => "YEAR(pc.collected_at) = YEAR(CURDATE()) AND MONTH(pc.collected_at) = MONTH(CURDATE())"
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
            pc.notes,
            u.full_name as collected_by_name
        FROM payment_collections pc
        LEFT JOIN users u ON pc.collected_by = u.id
        WHERE {$dateFilter}
        ORDER BY pc.collected_at DESC
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
        AND status = 'confirmed'
        GROUP BY payment_method
    ");
    $byMethod = $stmt2->fetchAll();
    
    Response::success([
        'collections' => $collections,
        'by_method' => $byMethod
    ], 'Collections summary retrieved');
}

function getFarmerPaymentSummary($db) {
    // Get aggregated farmer payment data from milk receiving + QC
    $stmt = $db->query("
        SELECT 
            f.id as farmer_id,
            f.farmer_code,
            CONCAT(f.first_name, ' ', f.last_name) as farmer_name,
            f.membership_type,
            f.bank_name,
            f.bank_account_number,
            mt.type_name as milk_type,
            COUNT(mr.id) as delivery_count,
            COALESCE(SUM(mr.accepted_liters), 0) as total_liters,
            COALESCE(AVG(qmt.fat_percentage), 0) as avg_fat,
            COALESCE(AVG(qmt.titratable_acidity), 0) as avg_acidity,
            COALESCE(AVG(qmt.final_price_per_liter), 0) as avg_price_per_liter,
            COALESCE(SUM(qmt.total_amount), 0) as total_amount,
            MIN(mr.receiving_date) as first_delivery,
            MAX(mr.receiving_date) as last_delivery
        FROM farmers f
        LEFT JOIN milk_receiving mr ON mr.farmer_id = f.id AND mr.status = 'accepted'
        LEFT JOIN qc_milk_tests qmt ON qmt.receiving_id = mr.id AND qmt.is_accepted = 1
        LEFT JOIN milk_types mt ON f.milk_type_id = mt.id
        WHERE f.is_active = 1
        GROUP BY f.id
        ORDER BY total_amount DESC
    ");
    $farmers = $stmt->fetchAll();
    
    Response::success($farmers, 'Farmer payment summary retrieved');
}

function getRecentDisbursements($db) {
    $limit = getParam('limit', 10);
    
    // Recent paid POs (supplier disbursements)
    $stmt = $db->prepare("
        SELECT 
            po.id,
            po.po_number,
            po.order_date,
            po.total_amount,
            po.payment_status,
            po.status,
            po.updated_at as payment_date,
            s.supplier_name,
            s.supplier_code,
            'supplier_payment' as transaction_type
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        WHERE po.payment_status = 'paid'
        AND po.status != 'cancelled'
        ORDER BY po.updated_at DESC
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
        GROUP BY fc.id
        ORDER BY total_balance DESC
        LIMIT 10
    ");
    $topDebtors = $stmt2->fetchAll();
    
    Response::success([
        'aging' => $aging,
        'top_debtors' => $topDebtors
    ], 'Receivables aging retrieved');
}
