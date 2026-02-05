<?php
/**
 * Sales Reports API
 * Highland Fresh Dairy - Sales Custodian Module
 * 
 * Provides reporting endpoints for sales analysis
 * NOTE: sales_orders table uses created_at (not order_date), delivery_date, and due_date
 */

require_once dirname(__DIR__) . '/bootstrap.php';

$currentUser = Auth::requireRole(['sales_custodian', 'general_manager', 'admin']);
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
    error_log("Sales Reports API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'summary':
            getSalesSummary($db);
            break;
        case 'trend':
            getSalesTrend($db);
            break;
        case 'by_customer':
            getSalesByCustomer($db);
            break;
        case 'by_product':
            getSalesByProduct($db);
            break;
        case 'by_type':
            getSalesByType($db);
            break;
        case 'customer_performance':
            getCustomerPerformance($db);
            break;
        case 'collections':
            getCollectionsReport($db);
            break;
        case 'weekly_comparison':
            getWeeklyComparison($db);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function getSalesSummary($db) {
    $startDate = getParam('start_date', date('Y-m-01'));
    $endDate = getParam('end_date', date('Y-m-d'));
    
    // Total sales for period (using created_at)
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_sales,
            COUNT(*) as total_orders,
            COALESCE(AVG(total_amount), 0) as avg_order_value
        FROM sales_orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'voided')
    ");
    $stmt->execute([$startDate, $endDate]);
    $summary = $stmt->fetch();
    
    // Previous period for comparison
    $periodDays = (strtotime($endDate) - strtotime($startDate)) / 86400;
    $prevStartDate = date('Y-m-d', strtotime($startDate . ' - ' . ($periodDays + 1) . ' days'));
    $prevEndDate = date('Y-m-d', strtotime($startDate . ' - 1 day'));
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as prev_total_sales
        FROM sales_orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'voided')
    ");
    $stmt->execute([$prevStartDate, $prevEndDate]);
    $prevData = $stmt->fetch();
    
    // Calculate change percentage
    $prevSales = floatval($prevData['prev_total_sales']);
    $currentSales = floatval($summary['total_sales']);
    $changePercent = $prevSales > 0 ? (($currentSales - $prevSales) / $prevSales) * 100 : 0;
    
    // Collections for period
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_collections
        FROM payment_collections 
        WHERE DATE(collected_at) BETWEEN ? AND ?
        AND status = 'confirmed'
    ");
    $stmt->execute([$startDate, $endDate]);
    $collections = $stmt->fetch();
    
    // Sales by type
    $stmt = $db->prepare("
        SELECT 
            c.customer_type as type,
            COALESCE(SUM(o.total_amount), 0) as amount
        FROM sales_orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'voided')
        GROUP BY c.customer_type
    ");
    $stmt->execute([$startDate, $endDate]);
    $byType = $stmt->fetchAll();
    
    Response::success([
        'total_sales' => floatval($summary['total_sales']),
        'total_orders' => intval($summary['total_orders']),
        'avg_order_value' => floatval($summary['avg_order_value']),
        'collections' => floatval($collections['total_collections']),
        'change_percent' => round($changePercent, 1),
        'by_type' => $byType,
        'period' => [
            'start' => $startDate,
            'end' => $endDate
        ]
    ], 'Sales summary retrieved');
}

function getSalesTrend($db) {
    $startDate = getParam('start_date', date('Y-m-01'));
    $endDate = getParam('end_date', date('Y-m-d'));
    
    $stmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            COALESCE(SUM(total_amount), 0) as total,
            COUNT(*) as orders
        FROM sales_orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND status NOT IN ('cancelled', 'voided')
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$startDate, $endDate]);
    $data = $stmt->fetchAll();
    
    Response::success($data, 'Sales trend retrieved');
}

function getSalesByCustomer($db) {
    $startDate = getParam('start_date', date('Y-m-01'));
    $endDate = getParam('end_date', date('Y-m-d'));
    $limit = min((int)getParam('limit', 20), 100);
    
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.name as customer_name,
            c.customer_code,
            c.customer_type,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_amount,
            COALESCE(AVG(o.total_amount), 0) as avg_order_value
        FROM customers c
        LEFT JOIN sales_orders o ON c.id = o.customer_id 
            AND DATE(o.created_at) BETWEEN ? AND ?
            AND o.status NOT IN ('cancelled', 'voided')
        GROUP BY c.id, c.name, c.customer_code, c.customer_type
        HAVING total_amount > 0
        ORDER BY total_amount DESC
        LIMIT ?
    ");
    $stmt->execute([$startDate, $endDate, $limit]);
    $data = $stmt->fetchAll();
    
    Response::success($data, 'Sales by customer retrieved');
}

function getSalesByProduct($db) {
    $startDate = getParam('start_date', date('Y-m-01'));
    $endDate = getParam('end_date', date('Y-m-d'));
    $limit = min((int)getParam('limit', 20), 100);
    
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.product_name,
            p.product_code as sku,
            SUM(oi.quantity) as quantity_sold,
            SUM(oi.quantity * oi.unit_price) as revenue
        FROM sales_order_items oi
        JOIN sales_orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        AND o.status NOT IN ('cancelled', 'voided')
        GROUP BY p.id, p.product_name, p.product_code
        ORDER BY revenue DESC
        LIMIT ?
    ");
    $stmt->execute([$startDate, $endDate, $limit]);
    $data = $stmt->fetchAll();
    
    Response::success($data, 'Sales by product retrieved');
}

function getSalesByType($db) {
    $startDate = getParam('start_date', date('Y-m-01'));
    $endDate = getParam('end_date', date('Y-m-d'));
    
    $stmt = $db->prepare("
        SELECT 
            COALESCE(c.customer_type, 'unknown') as type,
            COUNT(DISTINCT c.id) as customer_count,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_amount,
            COALESCE(AVG(o.total_amount), 0) as avg_order_value
        FROM customers c
        LEFT JOIN sales_orders o ON c.id = o.customer_id 
            AND DATE(o.created_at) BETWEEN ? AND ?
            AND o.status NOT IN ('cancelled', 'voided')
        GROUP BY c.customer_type
        ORDER BY total_amount DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    $data = $stmt->fetchAll();
    
    Response::success($data, 'Sales by type retrieved');
}

function getCustomerPerformance($db) {
    $period = getParam('period', 'year');
    $startDate = getStartDateByPeriod($period);
    $endDate = date('Y-m-d');
    
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.name as customer_name,
            c.customer_code,
            c.customer_type,
            c.credit_limit,
            c.current_balance as outstanding_balance,
            c.status,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_sales,
            COALESCE(AVG(o.total_amount), 0) as avg_order_value,
            MAX(o.created_at) as last_order_date,
            CASE 
                WHEN c.credit_limit > 0 AND c.current_balance > c.credit_limit THEN 30
                WHEN c.credit_limit > 0 AND c.current_balance > c.credit_limit * 0.8 THEN 60
                WHEN c.credit_limit > 0 AND c.current_balance > c.credit_limit * 0.5 THEN 80
                ELSE 100
            END as payment_score
        FROM customers c
        LEFT JOIN sales_orders o ON c.id = o.customer_id 
            AND DATE(o.created_at) BETWEEN ? AND ?
            AND o.status NOT IN ('cancelled', 'voided')
        WHERE c.status = 'active'
        GROUP BY c.id, c.name, c.customer_code, c.customer_type, 
                 c.credit_limit, c.current_balance, c.status
        ORDER BY total_sales DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    $data = $stmt->fetchAll();
    
    // Add summary stats
    $activeCount = count(array_filter($data, fn($c) => $c['status'] === 'active'));
    $topPerformers = count(array_filter($data, fn($c) => $c['payment_score'] >= 90));
    $atRisk = count(array_filter($data, fn($c) => $c['payment_score'] >= 50 && $c['payment_score'] < 70));
    $overLimit = count(array_filter($data, fn($c) => 
        floatval($c['credit_limit']) > 0 && floatval($c['outstanding_balance']) > floatval($c['credit_limit'])
    ));
    
    Response::success([
        'customers' => $data,
        'summary' => [
            'active_customers' => $activeCount,
            'top_performers' => $topPerformers,
            'at_risk' => $atRisk,
            'over_limit' => $overLimit
        ]
    ], 'Customer performance retrieved');
}

function getCollectionsReport($db) {
    $startDate = getParam('start_date', date('Y-m-01'));
    $endDate = getParam('end_date', date('Y-m-d'));
    
    // Collection summary
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(amount_collected), 0) as total_collected,
            COUNT(*) as payment_count
        FROM payment_collections 
        WHERE DATE(collected_at) BETWEEN ? AND ?
        AND status = 'confirmed'
    ");
    $stmt->execute([$startDate, $endDate]);
    $summary = $stmt->fetch();
    
    // Collections by payment method
    $stmt = $db->prepare("
        SELECT 
            COALESCE(payment_method, 'cash') as method,
            COALESCE(SUM(amount_collected), 0) as total,
            COUNT(*) as count
        FROM payment_collections 
        WHERE DATE(collected_at) BETWEEN ? AND ?
        AND status = 'confirmed'
        GROUP BY payment_method
    ");
    $stmt->execute([$startDate, $endDate]);
    $byMethod = $stmt->fetchAll();
    
    // Daily collections trend
    $stmt = $db->prepare("
        SELECT 
            DATE(collected_at) as date,
            COALESCE(SUM(amount_collected), 0) as total
        FROM payment_collections 
        WHERE DATE(collected_at) BETWEEN ? AND ?
        AND status = 'confirmed'
        GROUP BY DATE(collected_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$startDate, $endDate]);
    $trend = $stmt->fetchAll();
    
    Response::success([
        'total_collected' => floatval($summary['total_collected']),
        'payment_count' => intval($summary['payment_count']),
        'by_method' => $byMethod,
        'trend' => $trend
    ], 'Collections report retrieved');
}

function getStartDateByPeriod($period) {
    switch ($period) {
        case 'week':
            return date('Y-m-d', strtotime('monday this week'));
        case 'month':
            return date('Y-m-01');
        case 'quarter':
            $month = date('n');
            $quarterMonth = (ceil($month / 3) - 1) * 3 + 1;
            return date('Y-') . str_pad($quarterMonth, 2, '0', STR_PAD_LEFT) . '-01';
        case 'year':
            return date('Y-01-01');
        case 'all':
            return '2020-01-01';
        default:
            return date('Y-m-01');
    }
}

/**
 * Weekly Sales Comparison Report
 * Compares current week vs previous week by customer
 * Classifies as: strong (>20% increase), weak (>20% decrease), or stable
 */
function getWeeklyComparison($db) {
    $customerType = getParam('customer_type'); // Optional filter
    
    // Current week: Monday to Sunday
    $currentWeekStart = date('Y-m-d', strtotime('monday this week'));
    $currentWeekEnd = date('Y-m-d', strtotime('sunday this week'));
    
    // Previous week
    $prevWeekStart = date('Y-m-d', strtotime('monday last week'));
    $prevWeekEnd = date('Y-m-d', strtotime('sunday last week'));
    
    $sql = "
        SELECT 
            c.id as customer_id,
            c.customer_code,
            c.name as customer_name,
            c.customer_type,
            COALESCE(curr.total, 0) as current_week_sales,
            COALESCE(curr.order_count, 0) as current_week_orders,
            COALESCE(prev.total, 0) as previous_week_sales,
            COALESCE(prev.order_count, 0) as previous_week_orders,
            CASE 
                WHEN COALESCE(prev.total, 0) = 0 AND COALESCE(curr.total, 0) > 0 THEN 'new'
                WHEN COALESCE(prev.total, 0) > 0 AND COALESCE(curr.total, 0) = 0 THEN 'inactive'
                WHEN COALESCE(prev.total, 0) > 0 
                    AND ((COALESCE(curr.total, 0) - prev.total) / prev.total) > 0.2 THEN 'strong'
                WHEN COALESCE(prev.total, 0) > 0 
                    AND ((COALESCE(curr.total, 0) - prev.total) / prev.total) < -0.2 THEN 'weak'
                ELSE 'stable'
            END as performance,
            CASE 
                WHEN COALESCE(prev.total, 0) = 0 THEN NULL
                ELSE ROUND(((COALESCE(curr.total, 0) - prev.total) / prev.total) * 100, 1)
            END as change_percent
        FROM customers c
        LEFT JOIN (
            SELECT customer_id, 
                   SUM(total_amount) as total,
                   COUNT(*) as order_count
            FROM sales_orders 
            WHERE DATE(created_at) BETWEEN ? AND ?
            AND status NOT IN ('cancelled', 'voided')
            GROUP BY customer_id
        ) curr ON c.id = curr.customer_id
        LEFT JOIN (
            SELECT customer_id, 
                   SUM(total_amount) as total,
                   COUNT(*) as order_count
            FROM sales_orders 
            WHERE DATE(created_at) BETWEEN ? AND ?
            AND status NOT IN ('cancelled', 'voided')
            GROUP BY customer_id
        ) prev ON c.id = prev.customer_id
        WHERE c.status = 'active'
        AND (COALESCE(curr.total, 0) > 0 OR COALESCE(prev.total, 0) > 0)
    ";
    
    $params = [$currentWeekStart, $currentWeekEnd, $prevWeekStart, $prevWeekEnd];
    
    if ($customerType) {
        $sql .= " AND c.customer_type = ?";
        $params[] = $customerType;
    }
    
    $sql .= " ORDER BY current_week_sales DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll();
    
    // Summary stats
    $summary = [
        'current_week' => [
            'start' => $currentWeekStart,
            'end' => $currentWeekEnd,
            'total_sales' => 0,
            'order_count' => 0
        ],
        'previous_week' => [
            'start' => $prevWeekStart,
            'end' => $prevWeekEnd,
            'total_sales' => 0,
            'order_count' => 0
        ],
        'performance_breakdown' => [
            'strong' => 0,
            'stable' => 0,
            'weak' => 0,
            'new' => 0,
            'inactive' => 0
        ]
    ];
    
    foreach ($customers as $customer) {
        $summary['current_week']['total_sales'] += floatval($customer['current_week_sales']);
        $summary['current_week']['order_count'] += intval($customer['current_week_orders']);
        $summary['previous_week']['total_sales'] += floatval($customer['previous_week_sales']);
        $summary['previous_week']['order_count'] += intval($customer['previous_week_orders']);
        $summary['performance_breakdown'][$customer['performance']]++;
    }
    
    // Overall change percentage
    $summary['overall_change_percent'] = $summary['previous_week']['total_sales'] > 0 
        ? round((($summary['current_week']['total_sales'] - $summary['previous_week']['total_sales']) 
            / $summary['previous_week']['total_sales']) * 100, 1)
        : null;
    
    Response::success([
        'summary' => $summary,
        'customers' => $customers
    ], 'Weekly comparison retrieved');
}
