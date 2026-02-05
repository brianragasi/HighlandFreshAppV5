<?php
/**
 * Highland Fresh System - Cashier Dashboard API
 * 
 * Dashboard data for Cashier/POS module
 * Today's sales, collections, cash position, recent transactions
 * 
 * GET - Summary, recent transactions, pending collections
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Cashier or GM role
$currentUser = Auth::requireRole(['cashier', 'general_manager']);

$action = getParam('action', 'summary');

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("POS Dashboard API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

// ========================================
// GET HANDLERS
// ========================================

function handleGet($db, $action, $currentUser) {
    switch ($action) {
        case 'summary':
            // Get comprehensive dashboard summary
            $date = getParam('date', date('Y-m-d'));
            
            // Today's cash sales
            $salesStmt = $db->prepare("
                SELECT 
                    COUNT(*) as transaction_count,
                    COALESCE(SUM(total_amount), 0) as total_sales,
                    COALESCE(SUM(amount_paid), 0) as total_received,
                    COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount_paid ELSE 0 END), 0) as cash_received,
                    COALESCE(SUM(CASE WHEN payment_method = 'gcash' THEN amount_paid ELSE 0 END), 0) as gcash_received,
                    COALESCE(SUM(CASE WHEN payment_method = 'check' THEN amount_paid ELSE 0 END), 0) as check_received,
                    COALESCE(SUM(CASE WHEN payment_method = 'bank_transfer' THEN amount_paid ELSE 0 END), 0) as bank_transfer_received,
                    COALESCE(SUM(discount_amount), 0) as total_discounts
                FROM sales_transactions 
                WHERE DATE(created_at) = ?
                AND transaction_type = 'cash'
                AND payment_status != 'voided'
            ");
            $salesStmt->execute([$date]);
            $salesData = $salesStmt->fetch();
            
            // Today's collections (AR payments)
            $collectionsStmt = $db->prepare("
                SELECT 
                    COUNT(*) as collection_count,
                    COALESCE(SUM(amount_collected), 0) as total_collected,
                    COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount_collected ELSE 0 END), 0) as cash_collections,
                    COALESCE(SUM(CASE WHEN payment_method = 'gcash' THEN amount_collected ELSE 0 END), 0) as gcash_collections,
                    COALESCE(SUM(CASE WHEN payment_method = 'check' THEN amount_collected ELSE 0 END), 0) as check_collections,
                    COALESCE(SUM(CASE WHEN payment_method = 'bank_transfer' THEN amount_collected ELSE 0 END), 0) as bank_transfer_collections
                FROM payment_collections 
                WHERE DATE(collected_at) = ?
                AND status = 'confirmed'
            ");
            $collectionsStmt->execute([$date]);
            $collectionsData = $collectionsStmt->fetch();
            
            // Total outstanding receivables
            $arStmt = $db->prepare("
                SELECT 
                    COUNT(*) as outstanding_count,
                    COALESCE(SUM(total_amount - amount_paid), 0) as total_outstanding
                FROM delivery_receipts 
                WHERE payment_status IN ('unpaid', 'partial')
                AND status NOT IN ('cancelled')
            ");
            $arStmt->execute();
            $arData = $arStmt->fetch();
            
            // Voided transactions today
            $voidedStmt = $db->prepare("
                SELECT COUNT(*) as voided_count
                FROM sales_transactions 
                WHERE DATE(created_at) = ?
                AND payment_status = 'voided'
            ");
            $voidedStmt->execute([$date]);
            $voidedData = $voidedStmt->fetch();
            
            // Pending check collections (post-dated)
            $pendingChecksStmt = $db->prepare("
                SELECT 
                    COUNT(*) as pending_count,
                    COALESCE(SUM(amount_collected), 0) as pending_amount
                FROM payment_collections 
                WHERE payment_method = 'check'
                AND status = 'confirmed'
                AND JSON_UNQUOTE(JSON_EXTRACT(payment_metadata, '$.check_date')) > CURDATE()
            ");
            $pendingChecksStmt->execute();
            $pendingChecks = $pendingChecksStmt->fetch();
            
            // Calculate cash position
            $totalCashSales = floatval($salesData['cash_received']);
            $totalCashCollections = floatval($collectionsData['cash_collections'] ?? 0);
            $cashPosition = $totalCashSales + $totalCashCollections;
            
            // Non-cash position
            $nonCashPosition = floatval($salesData['gcash_received']) + 
                              floatval($salesData['check_received']) + 
                              floatval($salesData['bank_transfer_received']) +
                              floatval($collectionsData['gcash_collections'] ?? 0) +
                              floatval($collectionsData['check_collections'] ?? 0) +
                              floatval($collectionsData['bank_transfer_collections'] ?? 0);
            
            $summary = [
                'date' => $date,
                'sales' => [
                    'transaction_count' => intval($salesData['transaction_count']),
                    'total_sales' => floatval($salesData['total_sales']),
                    'total_received' => floatval($salesData['total_received']),
                    'total_discounts' => floatval($salesData['total_discounts']),
                    'by_payment_method' => [
                        'cash' => floatval($salesData['cash_received']),
                        'gcash' => floatval($salesData['gcash_received']),
                        'check' => floatval($salesData['check_received']),
                        'bank_transfer' => floatval($salesData['bank_transfer_received'])
                    ]
                ],
                'collections' => [
                    'collection_count' => intval($collectionsData['collection_count'] ?? 0),
                    'total_collected' => floatval($collectionsData['total_collected'] ?? 0),
                    'by_payment_method' => [
                        'cash' => floatval($collectionsData['cash_collections'] ?? 0),
                        'gcash' => floatval($collectionsData['gcash_collections'] ?? 0),
                        'check' => floatval($collectionsData['check_collections'] ?? 0),
                        'bank_transfer' => floatval($collectionsData['bank_transfer_collections'] ?? 0)
                    ]
                ],
                'receivables' => [
                    'outstanding_count' => intval($arData['outstanding_count']),
                    'total_outstanding' => floatval($arData['total_outstanding'])
                ],
                'cash_position' => [
                    'cash_on_hand' => $cashPosition,
                    'non_cash_received' => $nonCashPosition,
                    'total_position' => $cashPosition + $nonCashPosition
                ],
                'pending_checks' => [
                    'count' => intval($pendingChecks['pending_count'] ?? 0),
                    'amount' => floatval($pendingChecks['pending_amount'] ?? 0)
                ],
                'voided_transactions' => intval($voidedData['voided_count'])
            ];
            
            Response::success($summary, 'Dashboard summary retrieved');
            break;
            
        case 'cash_position':
            // Get current cash position with shift data and transactions for Today's Cash Flow
            $date = date('Y-m-d');
            
            // Get current active shift
            $shiftStmt = $db->prepare("
                SELECT 
                    cs.*,
                    u.first_name as cashier_first_name,
                    u.last_name as cashier_last_name
                FROM cashier_shifts cs
                LEFT JOIN users u ON cs.cashier_id = u.id
                WHERE cs.cashier_id = ?
                AND cs.status = 'active'
                ORDER BY cs.start_time DESC
                LIMIT 1
            ");
            $shiftStmt->execute([$currentUser['user_id']]);
            $shift = $shiftStmt->fetch();
            
            // If no active shift, check for any shift today
            if (!$shift) {
                $shiftStmt = $db->prepare("
                    SELECT 
                        cs.*,
                        u.first_name as cashier_first_name,
                        u.last_name as cashier_last_name
                    FROM cashier_shifts cs
                    LEFT JOIN users u ON cs.cashier_id = u.id
                    WHERE DATE(cs.start_time) = ?
                    ORDER BY cs.start_time DESC
                    LIMIT 1
                ");
                $shiftStmt->execute([$date]);
                $shift = $shiftStmt->fetch();
            }
            
            $openingCash = floatval($shift['opening_cash'] ?? 0);
            $cashIn = floatval($shift['cash_in'] ?? 0);
            $cashOut = floatval($shift['cash_out'] ?? 0);
            $shiftStart = $shift['start_time'] ?? date('Y-m-d 09:00:00');
            
            // Get cash sales for today
            $salesStmt = $db->prepare("
                SELECT COALESCE(SUM(amount_paid), 0) as total
                FROM sales_transactions 
                WHERE DATE(created_at) = ?
                AND payment_method = 'cash'
                AND payment_status != 'voided'
            ");
            $salesStmt->execute([$date]);
            $cashSales = floatval($salesStmt->fetchColumn());
            
            // Get cash collections for today
            $collStmt = $db->prepare("
                SELECT COALESCE(SUM(amount_collected), 0) as total
                FROM payment_collections 
                WHERE DATE(collected_at) = ?
                AND payment_method = 'cash'
                AND status = 'confirmed'
            ");
            $collStmt->execute([$date]);
            $cashCollections = floatval($collStmt->fetchColumn());
            
            $totalCashIn = $cashSales + $cashCollections + $cashIn;
            $expectedCash = $openingCash + $totalCashIn - $cashOut;
            
            // Get today's transactions for cash flow table
            $transactions = [];
            
            // Add opening entry
            $transactions[] = [
                'time' => date('H:i', strtotime($shiftStart)),
                'type' => 'opening',
                'reference' => '--',
                'description' => 'Shift Started',
                'amount' => $openingCash,
                'timestamp' => $shiftStart
            ];
            
            // Get sales transactions
            $salesTxStmt = $db->prepare("
                SELECT 
                    st.transaction_code,
                    st.customer_name,
                    st.amount_paid,
                    st.created_at
                FROM sales_transactions st
                WHERE DATE(st.created_at) = ?
                AND st.payment_method = 'cash'
                AND st.payment_status != 'voided'
                ORDER BY st.created_at ASC
            ");
            $salesTxStmt->execute([$date]);
            while ($row = $salesTxStmt->fetch()) {
                $transactions[] = [
                    'time' => date('H:i', strtotime($row['created_at'])),
                    'type' => 'sale',
                    'reference' => $row['transaction_code'],
                    'description' => $row['customer_name'] ?: 'Walk-in Customer',
                    'amount' => floatval($row['amount_paid']),
                    'timestamp' => $row['created_at']
                ];
            }
            
            // Get collection transactions
            $collTxStmt = $db->prepare("
                SELECT 
                    pc.or_number,
                    pc.customer_name,
                    pc.amount_collected,
                    pc.collected_at
                FROM payment_collections pc
                WHERE DATE(pc.collected_at) = ?
                AND pc.payment_method = 'cash'
                AND pc.status = 'confirmed'
                ORDER BY pc.collected_at ASC
            ");
            $collTxStmt->execute([$date]);
            while ($row = $collTxStmt->fetch()) {
                $transactions[] = [
                    'time' => date('H:i', strtotime($row['collected_at'])),
                    'type' => 'collection',
                    'reference' => $row['or_number'],
                    'description' => $row['customer_name'],
                    'amount' => floatval($row['amount_collected']),
                    'timestamp' => $row['collected_at']
                ];
            }
            
            // Sort by timestamp and calculate running balance
            usort($transactions, function($a, $b) {
                return strtotime($a['timestamp']) - strtotime($b['timestamp']);
            });
            
            $runningBalance = 0;
            foreach ($transactions as &$tx) {
                if ($tx['type'] === 'payout') {
                    $runningBalance -= $tx['amount'];
                } else {
                    $runningBalance += $tx['amount'];
                }
                $tx['balance'] = $runningBalance;
                unset($tx['timestamp']); // Remove timestamp from response
            }
            
            // Reverse for display (most recent first)
            $transactions = array_reverse($transactions);
            
            Response::success([
                'current_shift' => $shift ? [
                    'id' => $shift['id'],
                    'is_active' => $shift['status'] === 'active',
                    'opening_cash' => $openingCash,
                    'start_time' => $shift['start_time'],
                    'cashier_name' => trim(($shift['cashier_first_name'] ?? '') . ' ' . ($shift['cashier_last_name'] ?? '')) ?: 'Unknown'
                ] : null,
                'opening_cash' => $openingCash,
                'cash_sales' => $cashSales,
                'cash_collections' => $cashCollections,
                'total_cash_in' => $totalCashIn,
                'payouts' => $cashOut,
                'expected_cash' => $expectedCash,
                'transactions' => $transactions
            ], 'Cash position retrieved');
            break;
            
        case 'recent_transactions':
            // Get recent transactions (sales + collections)
            $limit = min(50, max(5, intval(getParam('limit', 20))));
            
            // Get recent sales
            $salesStmt = $db->prepare("
                SELECT 
                    'sale' as record_type,
                    st.id,
                    st.transaction_code as reference_code,
                    st.customer_name,
                    st.total_amount as amount,
                    st.payment_method,
                    st.payment_status,
                    st.created_at as transaction_time,
                    u.first_name as cashier_first_name,
                    u.last_name as cashier_last_name
                FROM sales_transactions st
                LEFT JOIN users u ON st.cashier_id = u.id
                WHERE DATE(st.created_at) = CURDATE()
                ORDER BY st.created_at DESC
                LIMIT ?
            ");
            $salesStmt->execute([$limit]);
            $recentSales = $salesStmt->fetchAll();
            
            // Get recent collections
            $collectionsStmt = $db->prepare("
                SELECT 
                    'collection' as record_type,
                    pc.id,
                    pc.or_number as reference_code,
                    pc.customer_name,
                    pc.amount_collected as amount,
                    pc.payment_method,
                    pc.status as payment_status,
                    pc.collected_at as transaction_time,
                    u.first_name as cashier_first_name,
                    u.last_name as cashier_last_name
                FROM payment_collections pc
                LEFT JOIN users u ON pc.collected_by = u.id
                WHERE DATE(pc.collected_at) = CURDATE()
                ORDER BY pc.collected_at DESC
                LIMIT ?
            ");
            $collectionsStmt->execute([$limit]);
            $recentCollections = $collectionsStmt->fetchAll();
            
            // Merge and sort by time
            $allTransactions = array_merge($recentSales, $recentCollections);
            usort($allTransactions, function($a, $b) {
                return strtotime($b['transaction_time']) - strtotime($a['transaction_time']);
            });
            
            // Limit combined results
            $allTransactions = array_slice($allTransactions, 0, $limit);
            
            Response::success([
                'transactions' => $allTransactions,
                'count' => count($allTransactions)
            ], 'Recent transactions retrieved');
            break;
            
        case 'pending_collections':
            // Get DRs that are due for collection
            $daysOverdue = intval(getParam('days_overdue', 0));
            $limit = min(100, max(10, intval(getParam('limit', 30))));
            
            $sql = "
                SELECT 
                    dr.id,
                    dr.dr_number,
                    dr.customer_name,
                    dr.delivery_address,
                    dr.total_amount,
                    dr.amount_paid,
                    (dr.total_amount - dr.amount_paid) as amount_due,
                    dr.payment_status,
                    dr.delivered_at,
                    DATEDIFF(CURDATE(), dr.delivered_at) as days_since_delivery,
                    COALESCE(c.payment_terms_days, 30) as payment_terms,
                    c.contact_person,
                    c.contact_number,
                    c.customer_type
                FROM delivery_receipts dr
                LEFT JOIN customers c ON dr.customer_id = c.id
                WHERE dr.payment_status IN ('unpaid', 'partial')
                AND dr.status NOT IN ('cancelled', 'pending', 'draft')
            ";
            $params = [];
            
            if ($daysOverdue > 0) {
                $sql .= " AND DATEDIFF(CURDATE(), dr.delivered_at) >= ?";
                $params[] = $daysOverdue;
            }
            
            $sql .= " ORDER BY dr.delivered_at ASC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $pending = $stmt->fetchAll();
            
            // Add overdue status
            foreach ($pending as &$p) {
                $paymentTerms = intval($p['payment_terms'] ?? 30);
                $daysSince = intval($p['days_since_delivery']);
                $p['is_overdue'] = $daysSince > $paymentTerms;
                $p['days_overdue'] = max(0, $daysSince - $paymentTerms);
            }
            
            $totalPending = array_sum(array_column($pending, 'amount_due'));
            $overdueCount = count(array_filter($pending, function($p) { return $p['is_overdue']; }));
            
            Response::success([
                'pending_collections' => $pending,
                'count' => count($pending),
                'total_pending' => $totalPending,
                'overdue_count' => $overdueCount
            ], 'Pending collections retrieved');
            break;
            
        case 'daily_breakdown':
            // Get breakdown by hour for today
            $date = getParam('date', date('Y-m-d'));
            
            $salesStmt = $db->prepare("
                SELECT 
                    HOUR(created_at) as hour,
                    COUNT(*) as transaction_count,
                    SUM(total_amount) as total_sales,
                    SUM(amount_paid) as total_received
                FROM sales_transactions 
                WHERE DATE(created_at) = ?
                AND payment_status != 'voided'
                GROUP BY HOUR(created_at)
                ORDER BY hour ASC
            ");
            $salesStmt->execute([$date]);
            $hourlyBreakdown = $salesStmt->fetchAll();
            
            // Fill in missing hours
            $fullBreakdown = [];
            for ($h = 6; $h <= 18; $h++) { // Business hours 6am to 6pm
                $found = false;
                foreach ($hourlyBreakdown as $hb) {
                    if (intval($hb['hour']) === $h) {
                        $fullBreakdown[] = [
                            'hour' => $h,
                            'hour_label' => date('g A', strtotime("{$h}:00")),
                            'transaction_count' => intval($hb['transaction_count']),
                            'total_sales' => floatval($hb['total_sales']),
                            'total_received' => floatval($hb['total_received'])
                        ];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $fullBreakdown[] = [
                        'hour' => $h,
                        'hour_label' => date('g A', strtotime("{$h}:00")),
                        'transaction_count' => 0,
                        'total_sales' => 0,
                        'total_received' => 0
                    ];
                }
            }
            
            Response::success([
                'date' => $date,
                'hourly_breakdown' => $fullBreakdown
            ], 'Daily breakdown retrieved');
            break;
            
        case 'top_products':
            // Top selling products today
            $date = getParam('date', date('Y-m-d'));
            $limit = min(20, max(5, intval(getParam('limit', 10))));
            
            $stmt = $db->prepare("
                SELECT 
                    sti.product_id,
                    sti.product_name,
                    sti.variant,
                    SUM(sti.quantity) as total_quantity,
                    SUM(sti.line_total) as total_sales,
                    COUNT(DISTINCT sti.transaction_id) as transaction_count
                FROM sales_transaction_items sti
                JOIN sales_transactions st ON sti.transaction_id = st.id
                WHERE DATE(st.created_at) = ?
                AND st.payment_status != 'voided'
                GROUP BY sti.product_id, sti.product_name, sti.variant
                ORDER BY total_quantity DESC
                LIMIT ?
            ");
            $stmt->execute([$date, $limit]);
            $topProducts = $stmt->fetchAll();
            
            Response::success([
                'date' => $date,
                'top_products' => $topProducts
            ], 'Top products retrieved');
            break;
            
        case 'week_comparison':
            // Compare this week vs last week
            $stmt = $db->prepare("
                SELECT 
                    CASE 
                        WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) THEN 'this_week'
                        ELSE 'last_week'
                    END as period,
                    COUNT(*) as transaction_count,
                    SUM(total_amount) as total_sales,
                    SUM(amount_paid) as total_received
                FROM sales_transactions 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL (WEEKDAY(CURDATE()) + 7) DAY)
                AND payment_status != 'voided'
                GROUP BY period
            ");
            $stmt->execute();
            $comparison = $stmt->fetchAll();
            
            $thisWeek = ['transaction_count' => 0, 'total_sales' => 0, 'total_received' => 0];
            $lastWeek = ['transaction_count' => 0, 'total_sales' => 0, 'total_received' => 0];
            
            foreach ($comparison as $c) {
                if ($c['period'] === 'this_week') {
                    $thisWeek = [
                        'transaction_count' => intval($c['transaction_count']),
                        'total_sales' => floatval($c['total_sales']),
                        'total_received' => floatval($c['total_received'])
                    ];
                } else {
                    $lastWeek = [
                        'transaction_count' => intval($c['transaction_count']),
                        'total_sales' => floatval($c['total_sales']),
                        'total_received' => floatval($c['total_received'])
                    ];
                }
            }
            
            // Calculate trends
            $salesTrend = $lastWeek['total_sales'] > 0 
                ? (($thisWeek['total_sales'] - $lastWeek['total_sales']) / $lastWeek['total_sales']) * 100 
                : 0;
            
            Response::success([
                'this_week' => $thisWeek,
                'last_week' => $lastWeek,
                'sales_trend_percent' => round($salesTrend, 2)
            ], 'Week comparison retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
