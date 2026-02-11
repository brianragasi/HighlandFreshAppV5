<?php
/**
 * Highland Fresh System - Sales Dashboard API
 * 
 * Dashboard data for Sales Custodian
 * Uses existing tables: customers, delivery_receipts, payment_collections
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Sales Custodian or GM role
$currentUser = Auth::requireRole(['sales_custodian', 'general_manager']);

$action = getParam('action', 'summary');

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
    error_log("Sales Dashboard API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'summary':
            getSummary($db);
            break;
        case 'aging_summary':
            getAgingSummary($db);
            break;
        case 'aging':
            getAgingReport($db);
            break;
        case 'top_customers':
            getTopCustomers($db);
            break;
        case 'recent_orders':
            getRecentOrders($db);
            break;
        case 'daily_collection':
            getDailyCollection($db);
            break;
        case 'collections_due':
            getCollectionsDue($db);
            break;
        case 'sales_trend':
            getSalesTrend($db);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function getSummary($db) {
    $period = getParam('period', 'month');
    $today = date('Y-m-d');
    
    switch ($period) {
        case 'day':
            $startDate = $today;
            break;
        case 'week':
            $startDate = date('Y-m-d', strtotime('monday this week'));
            break;
        case 'year':
            $startDate = date('Y-01-01');
            break;
        default:
            $startDate = date('Y-m-01');
            break;
    }
    
    // Sales from delivery receipts
    $salesStmt = $db->prepare("
        SELECT 
            COUNT(*) as dr_count, 
            COALESCE(SUM(total_amount), 0) as total_sales, 
            COALESCE(SUM(amount_paid), 0) as total_collected
        FROM delivery_receipts 
        WHERE status NOT IN ('cancelled', 'draft') 
        AND DATE(created_at) BETWEEN ? AND ?
    ");
    $salesStmt->execute([$startDate, $today]);
    $salesData = $salesStmt->fetch();
    
    // Total Receivables
    $receivablesStmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount - amount_paid), 0) as total_receivables
        FROM delivery_receipts 
        WHERE status NOT IN ('cancelled', 'draft') 
        AND payment_status != 'paid'
    ");
    $receivablesStmt->execute();
    $receivables = $receivablesStmt->fetch()['total_receivables'];
    
    // Overdue (more than 30 days since delivery)
    $overdueStmt = $db->prepare("
        SELECT 
            COUNT(*) as overdue_count, 
            COALESCE(SUM(total_amount - amount_paid), 0) as overdue_amount
        FROM delivery_receipts 
        WHERE status = 'delivered' 
        AND payment_status != 'paid' 
        AND delivered_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $overdueStmt->execute();
    $overdueData = $overdueStmt->fetch();
    
    // DR counts by status
    $orderStatsStmt = $db->prepare("
        SELECT status, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
        FROM delivery_receipts 
        WHERE DATE(created_at) BETWEEN ? AND ? 
        GROUP BY status
    ");
    $orderStatsStmt->execute([$startDate, $today]);
    $orderStats = [];
    while ($row = $orderStatsStmt->fetch()) {
        $orderStats[$row['status']] = [
            'count' => (int)$row['count'],
            'total' => (float)$row['total']
        ];
    }
    
    // Customer counts
    $customerStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_customers, 
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_customers 
        FROM customers
    ");
    $customerStmt->execute();
    $customerData = $customerStmt->fetch();
    
    $newCustomerStmt = $db->prepare("
        SELECT COUNT(*) as new_customers 
        FROM customers 
        WHERE created_at >= ?
    ");
    $newCustomerStmt->execute([$startDate]);
    $newCustomers = $newCustomerStmt->fetch()['new_customers'];
    
    Response::success([
        'period' => $period,
        'date_range' => ['start' => $startDate, 'end' => $today],
        'sales' => [
            'invoice_count' => (int)$salesData['dr_count'],
            'total_sales' => (float)$salesData['total_sales'],
            'total_collected' => (float)$salesData['total_collected']
        ],
        'receivables' => [
            'total_receivables' => (float)$receivables,
            'overdue_count' => (int)$overdueData['overdue_count'],
            'overdue_amount' => (float)$overdueData['overdue_amount']
        ],
        'orders' => $orderStats,
        'customers' => [
            'total' => (int)$customerData['total_customers'],
            'active' => (int)$customerData['active_customers'],
            'new_this_period' => (int)$newCustomers
        ]
    ], 'Dashboard summary retrieved');
}

function getAgingSummary($db) {
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN delivered_at IS NULL OR DATEDIFF(CURDATE(), delivered_at) <= 0 THEN (total_amount - amount_paid) ELSE 0 END), 0) as current_amount,
            COUNT(CASE WHEN delivered_at IS NULL OR DATEDIFF(CURDATE(), delivered_at) <= 0 THEN 1 END) as current_count,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), delivered_at) BETWEEN 1 AND 30 THEN (total_amount - amount_paid) ELSE 0 END), 0) as days_1_30_amount,
            COUNT(CASE WHEN DATEDIFF(CURDATE(), delivered_at) BETWEEN 1 AND 30 AND (total_amount - amount_paid) > 0 THEN 1 END) as days_1_30_count,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), delivered_at) BETWEEN 31 AND 60 THEN (total_amount - amount_paid) ELSE 0 END), 0) as days_31_60_amount,
            COUNT(CASE WHEN DATEDIFF(CURDATE(), delivered_at) BETWEEN 31 AND 60 AND (total_amount - amount_paid) > 0 THEN 1 END) as days_31_60_count,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), delivered_at) BETWEEN 61 AND 90 THEN (total_amount - amount_paid) ELSE 0 END), 0) as days_61_90_amount,
            COUNT(CASE WHEN DATEDIFF(CURDATE(), delivered_at) BETWEEN 61 AND 90 AND (total_amount - amount_paid) > 0 THEN 1 END) as days_61_90_count,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), delivered_at) > 90 THEN (total_amount - amount_paid) ELSE 0 END), 0) as days_91_plus_amount,
            COUNT(CASE WHEN DATEDIFF(CURDATE(), delivered_at) > 90 AND (total_amount - amount_paid) > 0 THEN 1 END) as days_91_plus_count,
            COALESCE(SUM(total_amount - amount_paid), 0) as total_outstanding
        FROM delivery_receipts 
        WHERE status NOT IN ('cancelled', 'draft') 
        AND payment_status != 'paid'
    ");
    $stmt->execute();
    $aging = $stmt->fetch();
    
    // By customer type
    $byTypeStmt = $db->prepare("
        SELECT 
            c.customer_type, 
            COUNT(DISTINCT c.id) as customer_count, 
            COALESCE(SUM(dr.total_amount - dr.amount_paid), 0) as total_outstanding
        FROM customers c 
        LEFT JOIN delivery_receipts dr ON c.id = dr.customer_id 
            AND dr.status NOT IN ('cancelled', 'draft') 
            AND dr.payment_status != 'paid'
        WHERE c.status = 'active' 
        GROUP BY c.customer_type 
        HAVING total_outstanding > 0 
        ORDER BY total_outstanding DESC
    ");
    $byTypeStmt->execute();
    $byType = $byTypeStmt->fetchAll();
    
    Response::success([
        'buckets' => [
            'current' => ['amount' => (float)$aging['current_amount'], 'count' => (int)$aging['current_count']],
            'days_1_30' => ['amount' => (float)$aging['days_1_30_amount'], 'count' => (int)$aging['days_1_30_count']],
            'days_31_60' => ['amount' => (float)$aging['days_31_60_amount'], 'count' => (int)$aging['days_31_60_count']],
            'days_61_90' => ['amount' => (float)$aging['days_61_90_amount'], 'count' => (int)$aging['days_61_90_count']],
            'days_91_plus' => ['amount' => (float)$aging['days_91_plus_amount'], 'count' => (int)$aging['days_91_plus_count']]
        ],
        'total_outstanding' => (float)$aging['total_outstanding'],
        'by_customer_type' => $byType
    ], 'Aging summary retrieved');
}

function getAgingReport($db) {
    $minBalance = (float)getParam('min_balance', 0);
    $limit = (int)getParam('limit', 10);
    
    // Get customers with outstanding balance
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.customer_code,
            c.name as customer_name,
            c.customer_type,
            c.credit_limit,
            COALESCE(SUM(dr.total_amount - dr.amount_paid), 0) as outstanding_balance,
            COUNT(dr.id) as invoice_count,
            MAX(dr.delivered_at) as last_invoice_date
        FROM customers c
        LEFT JOIN delivery_receipts dr ON c.id = dr.customer_id 
            AND dr.status NOT IN ('cancelled', 'draft')
            AND dr.payment_status != 'paid'
        WHERE c.status = 'active'
        GROUP BY c.id, c.customer_code, c.name, c.customer_type, c.credit_limit
        HAVING outstanding_balance >= ?
        ORDER BY outstanding_balance DESC
        LIMIT ?
    ");
    $stmt->execute([$minBalance, $limit]);
    $customers = $stmt->fetchAll();
    
    Response::success([
        'customers' => $customers,
        'total_count' => count($customers)
    ], 'Aging report retrieved');
}

function getTopCustomers($db) {
    $limit = (int)getParam('limit', 10);
    $period = getParam('period', 'month');
    $today = date('Y-m-d');
    
    switch ($period) {
        case 'week':
            $startDate = date('Y-m-d', strtotime('monday this week'));
            break;
        case 'year':
            $startDate = date('Y-01-01');
            break;
        case 'all':
            $startDate = '2000-01-01';
            break;
        default:
            $startDate = date('Y-m-01');
            break;
    }
    
    $stmt = $db->prepare("
        SELECT 
            c.id, 
            c.customer_code, 
            c.name as customer_name, 
            c.customer_type,
            COUNT(DISTINCT dr.id) as invoice_count, 
            COALESCE(SUM(dr.total_amount), 0) as total_sales,
            COALESCE(SUM(dr.amount_paid), 0) as total_paid, 
            COALESCE(SUM(dr.total_amount - dr.amount_paid), 0) as outstanding_balance
        FROM customers c 
        LEFT JOIN delivery_receipts dr ON c.id = dr.customer_id 
            AND dr.status NOT IN ('cancelled', 'draft') 
            AND DATE(dr.created_at) BETWEEN ? AND ?
        WHERE c.status = 'active' 
        GROUP BY c.id, c.customer_code, c.name, c.customer_type 
        ORDER BY total_sales DESC 
        LIMIT ?
    ");
    $stmt->execute([$startDate, $today, $limit]);
    $customers = $stmt->fetchAll();
    
    Response::success([
        'period' => $period,
        'date_range' => ['start' => $startDate, 'end' => $today],
        'customers' => $customers
    ], 'Top customers retrieved');
}

function getRecentOrders($db) {
    $limit = (int)getParam('limit', 20);
    $status = getParam('status');
    
    $sql = "
        SELECT 
            dr.id, 
            dr.dr_number as order_number, 
            dr.customer_id, 
            dr.customer_name, 
            dr.total_amount, 
            dr.amount_paid,
            (dr.total_amount - dr.amount_paid) as balance_due, 
            dr.status, 
            dr.payment_status, 
            dr.created_at, 
            dr.delivered_at,
            c.customer_type, 
            c.customer_code 
        FROM delivery_receipts dr 
        LEFT JOIN customers c ON dr.customer_id = c.id 
        WHERE dr.status != 'draft'
    ";
    $params = [];
    
    if ($status) {
        $sql .= " AND dr.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY dr.created_at DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    // Status summary
    $summaryStmt = $db->prepare("
        SELECT status, COUNT(*) as count 
        FROM delivery_receipts 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
        AND status != 'draft' 
        GROUP BY status
    ");
    $summaryStmt->execute();
    $statusSummary = [];
    while ($row = $summaryStmt->fetch()) {
        $statusSummary[$row['status']] = (int)$row['count'];
    }
    
    Response::success([
        'orders' => $orders,
        'status_summary' => $statusSummary
    ], 'Recent orders retrieved');
}

function getDailyCollection($db) {
    $date = getParam('date', date('Y-m-d'));
    
    $stmt = $db->prepare("
        SELECT 
            pc.id, 
            pc.collected_at as payment_date, 
            pc.amount_collected as amount, 
            pc.payment_method, 
            pc.or_number as reference_number,
            pc.dr_number, 
            dr.total_amount as invoice_total, 
            pc.customer_name, 
            u.username as recorded_by_name
        FROM payment_collections pc 
        LEFT JOIN delivery_receipts dr ON pc.dr_id = dr.id 
        LEFT JOIN users u ON pc.collected_by = u.id
        WHERE pc.status = 'confirmed' 
        AND DATE(pc.collected_at) = ? 
        ORDER BY pc.collected_at DESC
    ");
    $stmt->execute([$date]);
    $payments = $stmt->fetchAll();
    
    $methodSummary = [];
    $totalCollected = 0;
    
    foreach ($payments as $payment) {
        $method = $payment['payment_method'];
        if (!isset($methodSummary[$method])) {
            $methodSummary[$method] = ['count' => 0, 'amount' => 0];
        }
        $methodSummary[$method]['count']++;
        $methodSummary[$method]['amount'] += $payment['amount'];
        $totalCollected += $payment['amount'];
    }
    
    Response::success([
        'date' => $date,
        'payments' => $payments,
        'summary' => [
            'total_collected' => $totalCollected,
            'payment_count' => count($payments),
            'by_method' => $methodSummary
        ]
    ], 'Daily collection report retrieved');
}

function getCollectionsDue($db) {
    $today = date('Y-m-d');
    $nextWeek = date('Y-m-d', strtotime('+7 days'));
    $monthStart = date('Y-m-01');
    
    // Due today
    $dueTodayStmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount - amount_paid), 0) as amount
        FROM delivery_receipts 
        WHERE payment_status != 'paid' 
        AND status NOT IN ('cancelled', 'draft')
        AND DATE(delivered_at) = ?
    ");
    $dueTodayStmt->execute([$today]);
    $dueToday = $dueTodayStmt->fetch()['amount'];
    
    // Due this week
    $dueWeekStmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount - amount_paid), 0) as amount
        FROM delivery_receipts 
        WHERE payment_status != 'paid' 
        AND status NOT IN ('cancelled', 'draft')
        AND DATE(delivered_at) BETWEEN ? AND ?
    ");
    $dueWeekStmt->execute([$today, $nextWeek]);
    $dueThisWeek = $dueWeekStmt->fetch()['amount'];
    
    // Overdue (delivered more than 30 days ago and not paid)
    $overdueStmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount - amount_paid), 0) as amount
        FROM delivery_receipts 
        WHERE payment_status != 'paid' 
        AND status = 'delivered'
        AND delivered_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $overdueStmt->execute();
    $overdue = $overdueStmt->fetch()['amount'];
    
    // Collected this month
    $collectedMTDStmt = $db->prepare("
        SELECT COALESCE(SUM(amount_collected), 0) as amount
        FROM payment_collections 
        WHERE status = 'confirmed'
        AND collected_at >= ?
    ");
    $collectedMTDStmt->execute([$monthStart]);
    $collectedMTD = $collectedMTDStmt->fetch()['amount'];
    
    // Get unpaid invoices (delivery receipts)
    $invoicesStmt = $db->prepare("
        SELECT 
            dr.id,
            dr.dr_number as invoice_number,
            dr.dr_number as order_number,
            dr.customer_id,
            dr.customer_name,
            c.customer_type,
            dr.created_at as invoice_date,
            dr.delivered_at as due_date,
            dr.total_amount,
            dr.amount_paid as paid_amount,
            (dr.total_amount - dr.amount_paid) as balance_due,
            dr.payment_status
        FROM delivery_receipts dr
        LEFT JOIN customers c ON dr.customer_id = c.id
        WHERE dr.payment_status != 'paid'
        AND dr.status NOT IN ('cancelled', 'draft')
        ORDER BY dr.delivered_at ASC, dr.total_amount DESC
        LIMIT 100
    ");
    $invoicesStmt->execute();
    $invoices = $invoicesStmt->fetchAll();
    
    Response::success([
        'due_today' => (float)$dueToday,
        'due_this_week' => (float)$dueThisWeek,
        'overdue' => (float)$overdue,
        'collected_mtd' => (float)$collectedMTD,
        'invoices' => $invoices
    ], 'Collections due retrieved');
}

function getSalesTrend($db) {
    $days = (int)getParam('days', 30);
    
    $stmt = $db->prepare("
        SELECT 
            DATE(created_at) as date, 
            COUNT(*) as invoice_count, 
            COALESCE(SUM(total_amount), 0) as total_sales, 
            COALESCE(SUM(amount_paid), 0) as collected
        FROM delivery_receipts 
        WHERE status NOT IN ('cancelled', 'draft') 
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) 
        GROUP BY DATE(created_at) 
        ORDER BY date ASC
    ");
    $stmt->execute([$days]);
    $trend = $stmt->fetchAll();
    
    Response::success([
        'period_days' => $days,
        'data' => $trend
    ], 'Sales trend retrieved');
}
