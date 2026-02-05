<?php
/**
 * Highland Fresh System - POS Reports API
 * 
 * Reports for Cashier/POS module
 * Daily sales, collections summary, X/Z reading, cash position
 * 
 * GET - Generate various reports
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Cashier or GM role
$currentUser = Auth::requireRole(['cashier', 'general_manager']);

$action = getParam('action', 'daily_sales');

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
    error_log("POS Reports API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

// ========================================
// GET HANDLERS
// ========================================

function handleGet($db, $action, $currentUser) {
    switch ($action) {
        case 'daily_sales':
            // Daily sales report
            $date = getParam('date', date('Y-m-d'));
            
            // Get all sales transactions for the day
            $salesStmt = $db->prepare("
                SELECT 
                    st.*,
                    u.first_name as cashier_first_name,
                    u.last_name as cashier_last_name
                FROM sales_transactions st
                LEFT JOIN users u ON st.cashier_id = u.id
                WHERE DATE(st.created_at) = ?
                AND st.payment_status != 'voided'
                ORDER BY st.created_at ASC
            ");
            $salesStmt->execute([$date]);
            $transactions = $salesStmt->fetchAll();
            
            // Get sale items for each transaction
            foreach ($transactions as &$t) {
                $itemsStmt = $db->prepare("
                    SELECT 
                        sti.*,
                        p.category
                    FROM sales_transaction_items sti
                    LEFT JOIN products p ON sti.product_id = p.id
                    WHERE sti.transaction_id = ?
                ");
                $itemsStmt->execute([$t['id']]);
                $t['items'] = $itemsStmt->fetchAll();
            }
            
            // Calculate summary by payment method
            $paymentSummary = [
                'cash' => ['count' => 0, 'amount' => 0],
                'gcash' => ['count' => 0, 'amount' => 0],
                'check' => ['count' => 0, 'amount' => 0],
                'bank_transfer' => ['count' => 0, 'amount' => 0]
            ];
            
            $totalSales = 0;
            $totalDiscounts = 0;
            $totalReceived = 0;
            
            foreach ($transactions as $t) {
                $method = $t['payment_method'] ?? 'cash';
                if (isset($paymentSummary[$method])) {
                    $paymentSummary[$method]['count']++;
                    $paymentSummary[$method]['amount'] += floatval($t['amount_paid']);
                }
                $totalSales += floatval($t['total_amount']);
                $totalDiscounts += floatval($t['discount_amount']);
                $totalReceived += floatval($t['amount_paid']);
            }
            
            // Get product category breakdown
            $categoryStmt = $db->prepare("
                SELECT 
                    COALESCE(p.category, 'Other') as category,
                    SUM(sti.quantity) as quantity_sold,
                    SUM(sti.line_total) as total_sales
                FROM sales_transaction_items sti
                JOIN sales_transactions st ON sti.transaction_id = st.id
                LEFT JOIN products p ON sti.product_id = p.id
                WHERE DATE(st.created_at) = ?
                AND st.payment_status != 'voided'
                GROUP BY p.category
                ORDER BY total_sales DESC
            ");
            $categoryStmt->execute([$date]);
            $categoryBreakdown = $categoryStmt->fetchAll();
            
            // Get top products
            $topProductsStmt = $db->prepare("
                SELECT 
                    sti.product_id,
                    sti.product_name,
                    sti.variant,
                    SUM(sti.quantity) as quantity_sold,
                    SUM(sti.line_total) as total_sales,
                    COUNT(DISTINCT sti.transaction_id) as transaction_count
                FROM sales_transaction_items sti
                JOIN sales_transactions st ON sti.transaction_id = st.id
                WHERE DATE(st.created_at) = ?
                AND st.payment_status != 'voided'
                GROUP BY sti.product_id, sti.product_name, sti.variant
                ORDER BY quantity_sold DESC
                LIMIT 10
            ");
            $topProductsStmt->execute([$date]);
            $topProducts = $topProductsStmt->fetchAll();
            
            // Get voided transactions
            $voidedStmt = $db->prepare("
                SELECT 
                    st.transaction_code,
                    st.total_amount,
                    st.notes,
                    st.created_at,
                    u.first_name as cashier_first_name,
                    u.last_name as cashier_last_name
                FROM sales_transactions st
                LEFT JOIN users u ON st.cashier_id = u.id
                WHERE DATE(st.created_at) = ?
                AND st.payment_status = 'voided'
            ");
            $voidedStmt->execute([$date]);
            $voidedTransactions = $voidedStmt->fetchAll();
            
            // Get hourly sales breakdown
            $hourlyStmt = $db->prepare("
                SELECT 
                    HOUR(created_at) as hour,
                    SUM(amount_paid) as amount
                FROM sales_transactions 
                WHERE DATE(created_at) = ?
                AND payment_status != 'voided'
                GROUP BY HOUR(created_at)
            ");
            $hourlyStmt->execute([$date]);
            $hourlyResults = $hourlyStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $hourlyData = array_fill(0, 24, 0);
            foreach ($hourlyResults as $hour => $amount) {
                $hourlyData[(int)$hour] = floatval($amount);
            }
            
            // Get collections for the day
            $collStmt = $db->prepare("
                SELECT 
                    payment_method,
                    COUNT(*) as count,
                    COALESCE(SUM(amount_collected), 0) as amount
                FROM payment_collections 
                WHERE DATE(collected_at) = ?
                AND status = 'confirmed'
                GROUP BY payment_method
            ");
            $collStmt->execute([$date]);
            $collectionsResults = $collStmt->fetchAll();
            
            $collectionsSummary = [
                'cash' => ['count' => 0, 'amount' => 0],
                'gcash' => ['count' => 0, 'amount' => 0],
                'check' => ['count' => 0, 'amount' => 0],
                'bank_transfer' => ['count' => 0, 'amount' => 0]
            ];
            $totalCollections = 0;
            $collectionsCount = 0;
            
            foreach ($collectionsResults as $row) {
                $method = $row['payment_method'] ?? 'cash';
                if (isset($collectionsSummary[$method])) {
                    $collectionsSummary[$method]['count'] = intval($row['count']);
                    $collectionsSummary[$method]['amount'] = floatval($row['amount']);
                }
                $totalCollections += floatval($row['amount']);
                $collectionsCount += intval($row['count']);
            }
            
            // Format top_products for frontend (rename total_sales to revenue)
            $formattedTopProducts = array_map(function($p) {
                return [
                    'product_name' => $p['product_name'] . ($p['variant'] ? ' - ' . $p['variant'] : ''),
                    'quantity_sold' => intval($p['quantity_sold']),
                    'revenue' => floatval($p['total_sales'])
                ];
            }, $topProducts);
            
            Response::success([
                'date' => $date,
                // Frontend-expected format
                'sales' => [
                    'total_amount' => $totalSales,
                    'count' => count($transactions),
                    'by_payment' => $paymentSummary,
                    'hourly' => $hourlyData
                ],
                'collections' => [
                    'total_amount' => $totalCollections,
                    'count' => $collectionsCount,
                    'by_payment' => $collectionsSummary
                ],
                'top_products' => $formattedTopProducts,
                // Legacy/additional data
                'summary' => [
                    'transaction_count' => count($transactions),
                    'total_sales' => $totalSales,
                    'total_discounts' => $totalDiscounts,
                    'net_sales' => $totalSales - $totalDiscounts,
                    'total_received' => $totalReceived
                ],
                'payment_method_breakdown' => $paymentSummary,
                'category_breakdown' => $categoryBreakdown,
                'transactions' => $transactions,
                'voided_transactions' => $voidedTransactions,
                'generated_at' => date('Y-m-d H:i:s'),
                'generated_by' => $currentUser['username']
            ], 'Daily sales report generated');
            break;
            
        case 'cash_position':
            // Current cash position report
            $date = getParam('date', date('Y-m-d'));
            
            // Get opening cash from shift if available
            $openingCash = 0;
            $shiftStmt = $db->prepare("
                SELECT opening_cash 
                FROM cashier_shifts 
                WHERE DATE(start_time) = ? 
                AND cashier_id = ?
                ORDER BY start_time DESC
                LIMIT 1
            ");
            try {
                $shiftStmt->execute([$date, $currentUser['user_id']]);
                $shift = $shiftStmt->fetch();
                if ($shift) {
                    $openingCash = floatval($shift['opening_cash']);
                }
            } catch (Exception $e) {
                // Shift table might not exist yet
            }
            
            // Cash from sales
            $salesCashStmt = $db->prepare("
                SELECT COALESCE(SUM(amount_paid), 0) as total
                FROM sales_transactions 
                WHERE DATE(created_at) = ?
                AND payment_method = 'cash'
                AND payment_status != 'voided'
            ");
            $salesCashStmt->execute([$date]);
            $salesCash = floatval($salesCashStmt->fetchColumn());
            
            // Cash from collections
            $collectionsCashStmt = $db->prepare("
                SELECT COALESCE(SUM(amount_collected), 0) as total
                FROM payment_collections 
                WHERE DATE(collected_at) = ?
                AND payment_method = 'cash'
                AND status = 'confirmed'
            ");
            $collectionsCashStmt->execute([$date]);
            $collectionsCash = floatval($collectionsCashStmt->fetchColumn());
            
            // Cash adjustments (if table exists)
            $cashOut = 0;
            $cashIn = 0;
            try {
                $adjustStmt = $db->prepare("
                    SELECT 
                        COALESCE(SUM(CASE WHEN adjustment_type = 'in' THEN amount ELSE 0 END), 0) as cash_in,
                        COALESCE(SUM(CASE WHEN adjustment_type = 'out' THEN amount ELSE 0 END), 0) as cash_out
                    FROM cash_adjustments 
                    WHERE DATE(created_at) = ?
                ");
                $adjustStmt->execute([$date]);
                $adjustments = $adjustStmt->fetch();
                $cashIn = floatval($adjustments['cash_in'] ?? 0);
                $cashOut = floatval($adjustments['cash_out'] ?? 0);
            } catch (Exception $e) {
                // Table might not exist
            }
            
            // Non-cash received
            $nonCashStmt = $db->prepare("
                SELECT 
                    payment_method,
                    COALESCE(SUM(amount_paid), 0) as total
                FROM sales_transactions 
                WHERE DATE(created_at) = ?
                AND payment_method != 'cash'
                AND payment_status != 'voided'
                GROUP BY payment_method
            ");
            $nonCashStmt->execute([$date]);
            $nonCashSales = $nonCashStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Non-cash collections
            $nonCashCollStmt = $db->prepare("
                SELECT 
                    payment_method,
                    COALESCE(SUM(amount_collected), 0) as total
                FROM payment_collections 
                WHERE DATE(collected_at) = ?
                AND payment_method != 'cash'
                AND status = 'confirmed'
                GROUP BY payment_method
            ");
            $nonCashCollStmt->execute([$date]);
            $nonCashCollections = $nonCashCollStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Calculate totals
            $totalCashIn = $openingCash + $salesCash + $collectionsCash + $cashIn;
            $expectedCash = $totalCashIn - $cashOut;
            
            $nonCashTotal = [
                'gcash' => floatval($nonCashSales['gcash'] ?? 0) + floatval($nonCashCollections['gcash'] ?? 0),
                'check' => floatval($nonCashSales['check'] ?? 0) + floatval($nonCashCollections['check'] ?? 0),
                'bank_transfer' => floatval($nonCashSales['bank_transfer'] ?? 0) + floatval($nonCashCollections['bank_transfer'] ?? 0)
            ];
            
            Response::success([
                'date' => $date,
                'cash_position' => [
                    'opening_cash' => $openingCash,
                    'cash_sales' => $salesCash,
                    'cash_collections' => $collectionsCash,
                    'cash_adjustments_in' => $cashIn,
                    'cash_adjustments_out' => $cashOut,
                    'expected_cash_on_hand' => $expectedCash
                ],
                'non_cash_received' => [
                    'gcash' => $nonCashTotal['gcash'],
                    'check' => $nonCashTotal['check'],
                    'bank_transfer' => $nonCashTotal['bank_transfer'],
                    'total_non_cash' => array_sum($nonCashTotal)
                ],
                'total_position' => $expectedCash + array_sum($nonCashTotal),
                'generated_at' => date('Y-m-d H:i:s')
            ], 'Cash position report generated');
            break;
            
        case 'collections_summary':
            // Collections summary report
            $fromDate = getParam('from_date', date('Y-m-d'));
            $toDate = getParam('to_date', date('Y-m-d'));
            
            // Get all collections for the period
            $stmt = $db->prepare("
                SELECT 
                    pc.*,
                    u.first_name as collected_by_first_name,
                    u.last_name as collected_by_last_name
                FROM payment_collections pc
                LEFT JOIN users u ON pc.collected_by = u.id
                WHERE DATE(pc.collected_at) BETWEEN ? AND ?
                AND pc.status = 'confirmed'
                ORDER BY pc.collected_at ASC
            ");
            $stmt->execute([$fromDate, $toDate]);
            $collections = $stmt->fetchAll();
            
            // Summary by payment method
            $methodSummary = [
                'cash' => ['count' => 0, 'amount' => 0],
                'gcash' => ['count' => 0, 'amount' => 0],
                'check' => ['count' => 0, 'amount' => 0],
                'bank_transfer' => ['count' => 0, 'amount' => 0]
            ];
            
            $totalCollected = 0;
            
            foreach ($collections as $c) {
                $method = $c['payment_method'];
                if (isset($methodSummary[$method])) {
                    $methodSummary[$method]['count']++;
                    $methodSummary[$method]['amount'] += floatval($c['amount_collected']);
                }
                $totalCollected += floatval($c['amount_collected']);
            }
            
            // Daily breakdown
            $dailyStmt = $db->prepare("
                SELECT 
                    DATE(collected_at) as collection_date,
                    COUNT(*) as count,
                    SUM(amount_collected) as amount
                FROM payment_collections 
                WHERE DATE(collected_at) BETWEEN ? AND ?
                AND status = 'confirmed'
                GROUP BY DATE(collected_at)
                ORDER BY collection_date ASC
            ");
            $dailyStmt->execute([$fromDate, $toDate]);
            $dailyBreakdown = $dailyStmt->fetchAll();
            
            // Top customers
            $topCustomersStmt = $db->prepare("
                SELECT 
                    customer_name,
                    COUNT(*) as payment_count,
                    SUM(amount_collected) as total_collected
                FROM payment_collections 
                WHERE DATE(collected_at) BETWEEN ? AND ?
                AND status = 'confirmed'
                GROUP BY customer_name
                ORDER BY total_collected DESC
                LIMIT 10
            ");
            $topCustomersStmt->execute([$fromDate, $toDate]);
            $topCustomers = $topCustomersStmt->fetchAll();
            
            Response::success([
                'period' => [
                    'from_date' => $fromDate,
                    'to_date' => $toDate
                ],
                'summary' => [
                    'collection_count' => count($collections),
                    'total_collected' => $totalCollected
                ],
                'payment_method_breakdown' => $methodSummary,
                'daily_breakdown' => $dailyBreakdown,
                'top_customers' => $topCustomers,
                'collections' => $collections,
                'generated_at' => date('Y-m-d H:i:s')
            ], 'Collections summary report generated');
            break;
            
        case 'x_reading':
            // X-Reading (mid-day reading) - current totals without closing
            
            // Get current shift info
            $shiftInfo = null;
            try {
                $shiftStmt = $db->prepare("
                    SELECT * FROM cashier_shifts 
                    WHERE cashier_id = ? 
                    AND DATE(start_time) = CURDATE()
                    AND end_time IS NULL
                    ORDER BY start_time DESC
                    LIMIT 1
                ");
                $shiftStmt->execute([$currentUser['user_id']]);
                $shiftInfo = $shiftStmt->fetch();
            } catch (Exception $e) {
                // Table might not exist
            }
            
            // Get today's summary
            $summary = getDaySummary($db, date('Y-m-d'));
            
            // Count transactions since shift start or beginning of day
            $sinceTime = $shiftInfo ? $shiftInfo['start_time'] : date('Y-m-d 00:00:00');
            
            $txCountStmt = $db->prepare("
                SELECT 
                    MIN(transaction_code) as first_si,
                    MAX(transaction_code) as last_si,
                    COUNT(*) as transaction_count
                FROM sales_transactions 
                WHERE created_at >= ?
                AND payment_status != 'voided'
            ");
            $txCountStmt->execute([$sinceTime]);
            $txRange = $txCountStmt->fetch();
            
            Response::success([
                'reading_type' => 'X-Reading',
                'reading_time' => date('Y-m-d H:i:s'),
                'cashier' => [
                    'id' => $currentUser['user_id'],
                    'name' => $currentUser['username']
                ],
                'shift' => $shiftInfo ? [
                    'start_time' => $shiftInfo['start_time'],
                    'opening_cash' => floatval($shiftInfo['opening_cash'] ?? 0)
                ] : null,
                'transaction_range' => [
                    'first_si' => $txRange['first_si'],
                    'last_si' => $txRange['last_si'],
                    'count' => intval($txRange['transaction_count'])
                ],
                'sales_summary' => $summary['sales'],
                'collections_summary' => $summary['collections'],
                'cash_position' => $summary['cash_position'],
                'is_closing' => false
            ], 'X-Reading generated');
            break;
            
        case 'z_reading':
            // Z-Reading (end of day) - final reading
            $date = getParam('date', date('Y-m-d'));
            
            $summary = getDaySummary($db, $date);
            
            // Get transaction range for the day
            $txRangeStmt = $db->prepare("
                SELECT 
                    MIN(transaction_code) as first_si,
                    MAX(transaction_code) as last_si,
                    COUNT(*) as transaction_count
                FROM sales_transactions 
                WHERE DATE(created_at) = ?
                AND payment_status != 'voided'
            ");
            $txRangeStmt->execute([$date]);
            $txRange = $txRangeStmt->fetch();
            
            // Get OR range
            $orRangeStmt = $db->prepare("
                SELECT 
                    MIN(or_number) as first_or,
                    MAX(or_number) as last_or,
                    COUNT(*) as collection_count
                FROM payment_collections 
                WHERE DATE(collected_at) = ?
                AND status = 'confirmed'
            ");
            $orRangeStmt->execute([$date]);
            $orRange = $orRangeStmt->fetch();
            
            // Get voided transactions
            $voidedStmt = $db->prepare("
                SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as amount
                FROM sales_transactions 
                WHERE DATE(created_at) = ?
                AND payment_status = 'voided'
            ");
            $voidedStmt->execute([$date]);
            $voided = $voidedStmt->fetch();
            
            Response::success([
                'reading_type' => 'Z-Reading',
                'date' => $date,
                'reading_time' => date('Y-m-d H:i:s'),
                'generated_by' => $currentUser['username'],
                'sales' => [
                    'si_range' => [
                        'first' => $txRange['first_si'],
                        'last' => $txRange['last_si']
                    ],
                    'transaction_count' => intval($txRange['transaction_count']),
                    'gross_sales' => $summary['sales']['total_sales'],
                    'discounts' => $summary['sales']['total_discounts'],
                    'net_sales' => $summary['sales']['total_sales'] - $summary['sales']['total_discounts']
                ],
                'collections' => [
                    'or_range' => [
                        'first' => $orRange['first_or'],
                        'last' => $orRange['last_or']
                    ],
                    'count' => intval($orRange['collection_count']),
                    'amount' => $summary['collections']['total_collected']
                ],
                'voided' => [
                    'count' => intval($voided['count']),
                    'amount' => floatval($voided['amount'])
                ],
                'payment_breakdown' => [
                    'cash' => [
                        'sales' => $summary['sales']['by_method']['cash'],
                        'collections' => $summary['collections']['by_method']['cash'],
                        'total' => $summary['sales']['by_method']['cash'] + $summary['collections']['by_method']['cash']
                    ],
                    'gcash' => [
                        'sales' => $summary['sales']['by_method']['gcash'],
                        'collections' => $summary['collections']['by_method']['gcash'],
                        'total' => $summary['sales']['by_method']['gcash'] + $summary['collections']['by_method']['gcash']
                    ],
                    'check' => [
                        'sales' => $summary['sales']['by_method']['check'],
                        'collections' => $summary['collections']['by_method']['check'],
                        'total' => $summary['sales']['by_method']['check'] + $summary['collections']['by_method']['check']
                    ],
                    'bank_transfer' => [
                        'sales' => $summary['sales']['by_method']['bank_transfer'],
                        'collections' => $summary['collections']['by_method']['bank_transfer'],
                        'total' => $summary['sales']['by_method']['bank_transfer'] + $summary['collections']['by_method']['bank_transfer']
                    ]
                ],
                'grand_total' => $summary['sales']['total_received'] + $summary['collections']['total_collected'],
                'is_closing' => true
            ], 'Z-Reading generated');
            break;
            
        case 'cashier_performance':
            // Cashier performance report
            $cashierId = getParam('cashier_id', $currentUser['user_id']);
            $fromDate = getParam('from_date', date('Y-m-01'));
            $toDate = getParam('to_date', date('Y-m-d'));
            
            // Get cashier info
            $cashierStmt = $db->prepare("SELECT id, first_name, last_name, username FROM users WHERE id = ?");
            $cashierStmt->execute([$cashierId]);
            $cashier = $cashierStmt->fetch();
            
            if (!$cashier) {
                Response::error('Cashier not found', 404);
            }
            
            // Get sales stats
            $salesStmt = $db->prepare("
                SELECT 
                    COUNT(*) as transaction_count,
                    COALESCE(SUM(total_amount), 0) as total_sales,
                    COALESCE(SUM(amount_paid), 0) as total_received,
                    COALESCE(AVG(total_amount), 0) as average_transaction,
                    MAX(total_amount) as largest_transaction
                FROM sales_transactions 
                WHERE cashier_id = ?
                AND DATE(created_at) BETWEEN ? AND ?
                AND payment_status != 'voided'
            ");
            $salesStmt->execute([$cashierId, $fromDate, $toDate]);
            $salesStats = $salesStmt->fetch();
            
            // Get collections stats
            $collStmt = $db->prepare("
                SELECT 
                    COUNT(*) as collection_count,
                    COALESCE(SUM(amount_collected), 0) as total_collected
                FROM payment_collections 
                WHERE collected_by = ?
                AND DATE(collected_at) BETWEEN ? AND ?
                AND status = 'confirmed'
            ");
            $collStmt->execute([$cashierId, $fromDate, $toDate]);
            $collStats = $collStmt->fetch();
            
            // Get voided count
            $voidedStmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM sales_transactions 
                WHERE cashier_id = ?
                AND DATE(created_at) BETWEEN ? AND ?
                AND payment_status = 'voided'
            ");
            $voidedStmt->execute([$cashierId, $fromDate, $toDate]);
            $voidedCount = $voidedStmt->fetchColumn();
            
            // Daily breakdown
            $dailyStmt = $db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as transactions,
                    SUM(total_amount) as sales
                FROM sales_transactions 
                WHERE cashier_id = ?
                AND DATE(created_at) BETWEEN ? AND ?
                AND payment_status != 'voided'
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $dailyStmt->execute([$cashierId, $fromDate, $toDate]);
            $dailyBreakdown = $dailyStmt->fetchAll();
            
            Response::success([
                'cashier' => [
                    'id' => $cashier['id'],
                    'name' => trim($cashier['first_name'] . ' ' . $cashier['last_name']),
                    'username' => $cashier['username']
                ],
                'period' => [
                    'from_date' => $fromDate,
                    'to_date' => $toDate
                ],
                'sales' => [
                    'transaction_count' => intval($salesStats['transaction_count']),
                    'total_sales' => floatval($salesStats['total_sales']),
                    'total_received' => floatval($salesStats['total_received']),
                    'average_transaction' => round(floatval($salesStats['average_transaction']), 2),
                    'largest_transaction' => floatval($salesStats['largest_transaction'])
                ],
                'collections' => [
                    'count' => intval($collStats['collection_count']),
                    'total' => floatval($collStats['total_collected'])
                ],
                'voided_transactions' => intval($voidedCount),
                'daily_breakdown' => $dailyBreakdown,
                'generated_at' => date('Y-m-d H:i:s')
            ], 'Cashier performance report generated');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Helper function to get day summary
 */
function getDaySummary($db, $date) {
    // Sales summary
    $salesStmt = $db->prepare("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(total_amount), 0) as total,
            COALESCE(SUM(discount_amount), 0) as discounts,
            COALESCE(SUM(amount_paid), 0) as received,
            COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount_paid ELSE 0 END), 0) as cash,
            COALESCE(SUM(CASE WHEN payment_method = 'gcash' THEN amount_paid ELSE 0 END), 0) as gcash,
            COALESCE(SUM(CASE WHEN payment_method = 'check' THEN amount_paid ELSE 0 END), 0) as check_amt,
            COALESCE(SUM(CASE WHEN payment_method = 'bank_transfer' THEN amount_paid ELSE 0 END), 0) as bank_transfer
        FROM sales_transactions 
        WHERE DATE(created_at) = ?
        AND payment_status != 'voided'
    ");
    $salesStmt->execute([$date]);
    $sales = $salesStmt->fetch();
    
    // Collections summary
    $collStmt = $db->prepare("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(amount_collected), 0) as total,
            COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount_collected ELSE 0 END), 0) as cash,
            COALESCE(SUM(CASE WHEN payment_method = 'gcash' THEN amount_collected ELSE 0 END), 0) as gcash,
            COALESCE(SUM(CASE WHEN payment_method = 'check' THEN amount_collected ELSE 0 END), 0) as check_amt,
            COALESCE(SUM(CASE WHEN payment_method = 'bank_transfer' THEN amount_collected ELSE 0 END), 0) as bank_transfer
        FROM payment_collections 
        WHERE DATE(collected_at) = ?
        AND status = 'confirmed'
    ");
    $collStmt->execute([$date]);
    $collections = $collStmt->fetch();
    
    return [
        'sales' => [
            'transaction_count' => intval($sales['count']),
            'total_sales' => floatval($sales['total']),
            'total_discounts' => floatval($sales['discounts']),
            'total_received' => floatval($sales['received']),
            'by_method' => [
                'cash' => floatval($sales['cash']),
                'gcash' => floatval($sales['gcash']),
                'check' => floatval($sales['check_amt']),
                'bank_transfer' => floatval($sales['bank_transfer'])
            ]
        ],
        'collections' => [
            'count' => intval($collections['count']),
            'total_collected' => floatval($collections['total']),
            'by_method' => [
                'cash' => floatval($collections['cash']),
                'gcash' => floatval($collections['gcash']),
                'check' => floatval($collections['check_amt']),
                'bank_transfer' => floatval($collections['bank_transfer'])
            ]
        ],
        'cash_position' => [
            'cash_on_hand' => floatval($sales['cash']) + floatval($collections['cash']),
            'non_cash' => floatval($sales['gcash']) + floatval($sales['check_amt']) + floatval($sales['bank_transfer']) +
                         floatval($collections['gcash']) + floatval($collections['check_amt']) + floatval($collections['bank_transfer'])
        ]
    ];
}
