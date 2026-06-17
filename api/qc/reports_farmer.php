<?php
/**
 * Highland Fresh System - QC Farmer Summary Report API
 *
 * Aggregates per-farmer delivery + quality data for the QC team
 * consumed by html/qc/reports/farmer_summary.html
 *
 * GET endpoints:
 *   ?action=summary   (default) — per-farmer aggregate over a date range
 *     &from=YYYY-MM-DD          (default: first day of current month)
 *     &to=YYYY-MM-DD            (default: today)
 *     &membership=member|non_member  (optional filter)
 *     &milk_type_id=N           (optional filter)
 *     &search=string            (optional filter on farmer name/code)
 *
 *   ?action=detail             — single farmer profile + paginated delivery history
 *     &farmer_id=N              (required)
 *     &limit=N                  (default 20)
 *     &offset=N                 (default 0)
 *
 * @package HighlandFresh
 * @version 4.0
 * @added   2026-06-16
 */

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Fatal PHP error',
            'error_detail' => $error['message'],
            'error_file' => basename($error['file']),
            'error_line' => $error['error_line']
        ]);
    }
});

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/bootstrap.php';

// QC Officer, General Manager, Admin, Finance can view
$currentUser = Auth::requireRole(['qc_officer', 'general_manager', 'admin', 'finance_officer']);

if ($requestMethod !== 'GET') {
    Response::error('Method not allowed', 405);
}

$action = getParam('action', 'summary');

try {
    $db = Database::getInstance()->getConnection();

    if ($action === 'summary') {
        handleSummary($db);
    } elseif ($action === 'detail') {
        handleDetail($db);
    } else {
        Response::error('Unknown action: ' . $action, 400);
    }
} catch (Exception $e) {
    error_log("QC Farmer Summary API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}

/**
 * Per-farmer aggregate over a date range
 */
function handleSummary($db) {
    // Parse date range (default: current month to today)
    $today = date('Y-m-d');
    $fromParam = getParam('from');
    $toParam = getParam('to');

    if ($fromParam && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromParam)) {
        $fromDate = $fromParam;
    } else {
        $fromDate = date('Y-m-01');
    }
    if ($toParam && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toParam)) {
        $toDate = $toParam;
    } else {
        $toDate = $today;
    }
    if ($fromDate > $toDate) {
        Response::error('From date cannot be after To date', 400);
    }

    $membership = getParam('membership'); // member | non_member | null
    $milkTypeId = getParam('milk_type_id');
    $search = trim(getParam('search', ''));

    $where = ["mr.receiving_date BETWEEN ? AND ?", "mr.farmer_id IS NOT NULL"];
    $params = [$fromDate, $toDate];

    if ($membership === 'member' || $membership === 'non_member') {
        $where[] = "f.membership_type = ?";
        $params[] = $membership;
    }
    if ($milkTypeId && is_numeric($milkTypeId)) {
        $where[] = "f.milk_type_id = ?";
        $params[] = intval($milkTypeId);
    }
    if ($search !== '') {
        $where[] = "(f.farmer_code LIKE ? OR f.first_name LIKE ? OR f.last_name LIKE ?)";
        $like = "%{$search}%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    $whereSql = implode(' AND ', $where);

    // Get per-farmer aggregate
    $stmt = $db->prepare("
        SELECT
            f.id as farmer_id,
            f.farmer_code,
            COALESCE(f.first_name, '') as first_name,
            COALESCE(f.last_name, '') as last_name,
            f.membership_type,
            f.base_price_per_liter,
            f.contact_number,
            mt.type_name as milk_type_name,

            COUNT(mr.id) as delivery_count,
            COALESCE(SUM(mr.volume_liters), 0) as total_volume,
            COALESCE(SUM(CASE WHEN mr.status = 'accepted' THEN mr.accepted_liters ELSE 0 END), 0) as accepted_liters,
            COALESCE(SUM(CASE WHEN mr.status = 'rejected' THEN mr.rejected_liters ELSE 0 END), 0) as rejected_liters,
            COALESCE(SUM(CASE WHEN mr.status = 'pending_qc' THEN 1 ELSE 0 END), 0) as pending_count,
            COALESCE(SUM(CASE WHEN mr.status = 'rejected' THEN 1 ELSE 0 END), 0) as rejected_count,
            COALESCE(SUM(CASE WHEN mr.status = 'accepted' THEN 1 ELSE 0 END), 0) as accepted_count,

            MAX(mr.receiving_date) as last_delivery_date,

            -- Grade distribution from latest accepted QC test per delivery
            SUM(CASE WHEN qmt.grade = 'A' THEN 1 ELSE 0 END) as grade_a_count,
            SUM(CASE WHEN qmt.grade = 'B' THEN 1 ELSE 0 END) as grade_b_count,
            SUM(CASE WHEN qmt.grade = 'C' THEN 1 ELSE 0 END) as grade_c_count,
            SUM(CASE WHEN qmt.grade = 'D' THEN 1 ELSE 0 END) as grade_d_count,
            SUM(CASE WHEN qmt.grade = 'Rejected' THEN 1 ELSE 0 END) as grade_rejected_count,
            COALESCE(AVG(qmt.fat_percentage), 0) as avg_fat,
            COALESCE(AVG(qmt.titratable_acidity), 0) as avg_ta,
            COALESCE(AVG(qmt.specific_gravity), 0) as avg_sg,

            -- Total earnings (sum of total_amount on accepted tests in the range)
            COALESCE(SUM(CASE WHEN qmt.is_accepted = 1 THEN qmt.total_amount ELSE 0 END), 0) as total_earnings
        FROM farmers f
        LEFT JOIN milk_receiving mr ON f.id = mr.farmer_id
        LEFT JOIN qc_milk_tests qmt ON qmt.receiving_id = mr.id
            AND qmt.id = (
                SELECT MAX(id) FROM qc_milk_tests WHERE receiving_id = mr.id
            )
        LEFT JOIN milk_types mt ON f.milk_type_id = mt.id
        WHERE $whereSql
        GROUP BY f.id, f.farmer_code, f.first_name, f.last_name, f.membership_type,
                 f.base_price_per_liter, f.contact_number, mt.type_name
        ORDER BY total_volume DESC, f.farmer_code ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Compute derived fields
    $farmers = [];
    $totals = [
        'farmers_count' => 0,
        'delivery_count' => 0,
        'accepted_count' => 0,
        'rejected_count' => 0,
        'total_volume' => 0.0,
        'accepted_liters' => 0.0,
        'rejected_liters' => 0.0,
        'total_earnings' => 0.0,
        'avg_acceptance_rate' => 0.0
    ];
    $acceptanceSum = 0;
    $farmersWithDeliveries = 0;

    foreach ($rows as $r) {
        $totalDel = (int) $r['delivery_count'];
        $accDel = (int) $r['accepted_count'];
        $accRate = $totalDel > 0 ? round(($accDel / $totalDel) * 100, 1) : 0.0;
        $totalEarnings = (float) $r['total_earnings'];

        $farmers[] = [
            'farmer_id' => (int) $r['farmer_id'],
            'farmer_code' => $r['farmer_code'],
            'first_name' => $r['first_name'],
            'last_name' => $r['last_name'],
            'full_name' => trim($r['first_name'] . ' ' . $r['last_name']),
            'membership_type' => $r['membership_type'],
            'milk_type_name' => $r['milk_type_name'],
            'base_price_per_liter' => (float) $r['base_price_per_liter'],
            'contact_number' => $r['contact_number'],
            'delivery_count' => $totalDel,
            'accepted_count' => $accDel,
            'rejected_count' => (int) $r['rejected_count'],
            'pending_count' => (int) $r['pending_count'],
            'total_volume' => (float) $r['total_volume'],
            'accepted_liters' => (float) $r['accepted_liters'],
            'rejected_liters' => (float) $r['rejected_liters'],
            'acceptance_rate' => $accRate,
            'last_delivery_date' => $r['last_delivery_date'],
            'grade_a_count' => (int) $r['grade_a_count'],
            'grade_b_count' => (int) $r['grade_b_count'],
            'grade_c_count' => (int) $r['grade_c_count'],
            'grade_d_count' => (int) $r['grade_d_count'],
            'grade_rejected_count' => (int) $r['grade_rejected_count'],
            'avg_fat' => round((float) $r['avg_fat'], 2),
            'avg_ta' => round((float) $r['avg_ta'], 3),
            'avg_sg' => round((float) $r['avg_sg'], 3),
            'total_earnings' => round($totalEarnings, 2)
        ];

        if ($totalDel > 0) {
            $totals['farmers_count']++;
            $farmersWithDeliveries++;
            $acceptanceSum += $accRate;
        }
        $totals['delivery_count'] += $totalDel;
        $totals['accepted_count'] += $accDel;
        $totals['rejected_count'] += (int) $r['rejected_count'];
        $totals['total_volume'] += (float) $r['total_volume'];
        $totals['accepted_liters'] += (float) $r['accepted_liters'];
        $totals['rejected_liters'] += (float) $r['rejected_liters'];
        $totals['total_earnings'] += $totalEarnings;
    }
    $totals['farmers_count'] = count($rows);
    $totals['avg_acceptance_rate'] = $farmersWithDeliveries > 0
        ? round($acceptanceSum / $farmersWithDeliveries, 1)
        : 0.0;
    $totals['total_volume'] = round($totals['total_volume'], 2);
    $totals['accepted_liters'] = round($totals['accepted_liters'], 2);
    $totals['rejected_liters'] = round($totals['rejected_liters'], 2);
    $totals['total_earnings'] = round($totals['total_earnings'], 2);

    // Overall date range info
    Response::success([
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'totals' => $totals,
        'farmers' => $farmers
    ], 'Farmer summary retrieved successfully');
}

/**
 * Single farmer profile + paginated delivery history
 */
function handleDetail($db) {
    $farmerId = getParam('farmer_id');
    if (!$farmerId || !is_numeric($farmerId)) {
        Response::error('farmer_id is required', 400);
    }
    $farmerId = intval($farmerId);
    $limit = min(100, max(1, intval(getParam('limit', 20))));
    $offset = max(0, intval(getParam('offset', 0)));

    // Farmer profile
    $stmt = $db->prepare("
        SELECT
            f.id as farmer_id,
            f.farmer_code,
            f.first_name,
            f.last_name,
            f.contact_number,
            f.address,
            f.membership_type,
            f.base_price_per_liter,
            f.bank_name,
            f.bank_account_number,
            f.is_active,
            f.created_at,
            mt.type_name as milk_type_name
        FROM farmers f
        LEFT JOIN milk_types mt ON f.milk_type_id = mt.id
        WHERE f.id = ?
    ");
    $stmt->execute([$farmerId]);
    $farmer = $stmt->fetch();
    if (!$farmer) {
        Response::notFound('Farmer not found');
    }

    // Lifetime aggregates
    $lifeStmt = $db->prepare("
        SELECT
            COUNT(mr.id) as total_deliveries,
            COALESCE(SUM(mr.volume_liters), 0) as total_volume,
            COALESCE(SUM(CASE WHEN mr.status = 'accepted' THEN mr.accepted_liters ELSE 0 END), 0) as accepted_liters,
            COALESCE(SUM(CASE WHEN mr.status = 'rejected' THEN mr.rejected_liters ELSE 0 END), 0) as rejected_liters,
            COALESCE(SUM(CASE WHEN mr.status = 'accepted' THEN 1 ELSE 0 END), 0) as accepted_count,
            COALESCE(SUM(CASE WHEN mr.status = 'rejected' THEN 1 ELSE 0 END), 0) as rejected_count,
            MIN(mr.receiving_date) as first_delivery_date,
            MAX(mr.receiving_date) as last_delivery_date,
            COALESCE(AVG(qmt.fat_percentage), 0) as avg_fat,
            COALESCE(AVG(qmt.titratable_acidity), 0) as avg_ta,
            COALESCE(AVG(qmt.specific_gravity), 0) as avg_sg,
            COALESCE(SUM(CASE WHEN qmt.is_accepted = 1 THEN qmt.total_amount ELSE 0 END), 0) as total_earnings,
            SUM(CASE WHEN qmt.grade = 'A' THEN 1 ELSE 0 END) as grade_a_count,
            SUM(CASE WHEN qmt.grade = 'B' THEN 1 ELSE 0 END) as grade_b_count,
            SUM(CASE WHEN qmt.grade = 'C' THEN 1 ELSE 0 END) as grade_c_count,
            SUM(CASE WHEN qmt.grade = 'D' THEN 1 ELSE 0 END) as grade_d_count,
            SUM(CASE WHEN qmt.grade = 'Rejected' THEN 1 ELSE 0 END) as grade_rejected_count
        FROM milk_receiving mr
        LEFT JOIN qc_milk_tests qmt ON qmt.receiving_id = mr.id
            AND qmt.id = (SELECT MAX(id) FROM qc_milk_tests WHERE receiving_id = mr.id)
        WHERE mr.farmer_id = ?
    ");
    $lifeStmt->execute([$farmerId]);
    $lifetime = $lifeStmt->fetch();

    $totalDel = (int) ($lifetime['total_deliveries'] ?? 0);
    $accDel = (int) ($lifetime['accepted_count'] ?? 0);
    $lifetime['acceptance_rate'] = $totalDel > 0 ? round(($accDel / $totalDel) * 100, 1) : 0.0;
    $lifetime['total_volume'] = round((float) $lifetime['total_volume'], 2);
    $lifetime['accepted_liters'] = round((float) $lifetime['accepted_liters'], 2);
    $lifetime['rejected_liters'] = round((float) $lifetime['rejected_liters'], 2);
    $lifetime['avg_fat'] = round((float) $lifetime['avg_fat'], 2);
    $lifetime['avg_ta'] = round((float) $lifetime['avg_ta'], 3);
    $lifetime['avg_sg'] = round((float) $lifetime['avg_sg'], 3);
    $lifetime['total_earnings'] = round((float) $lifetime['total_earnings'], 2);

    // Total count for pagination
    $countStmt = $db->prepare("SELECT COUNT(*) FROM milk_receiving WHERE farmer_id = ?");
    $countStmt->execute([$farmerId]);
    $totalRows = (int) $countStmt->fetchColumn();

    // Paginated delivery history
    $historyStmt = $db->prepare("
        SELECT
            mr.id as receiving_id,
            mr.receiving_code,
            mr.receiving_date,
            mr.receiving_time,
            mr.volume_liters,
            mr.accepted_liters,
            mr.rejected_liters,
            mr.status,
            qmt.grade,
            qmt.fat_percentage,
            qmt.titratable_acidity,
            qmt.specific_gravity,
            qmt.total_amount,
            qmt.final_price_per_liter,
            qmt.test_datetime,
            qmt.test_code
        FROM milk_receiving mr
        LEFT JOIN qc_milk_tests qmt ON qmt.receiving_id = mr.id
            AND qmt.id = (SELECT MAX(id) FROM qc_milk_tests WHERE receiving_id = mr.id)
        WHERE mr.farmer_id = ?
        ORDER BY mr.receiving_date DESC, mr.receiving_time DESC
        LIMIT ? OFFSET ?
    ");
    $historyStmt->bindValue(1, $farmerId, PDO::PARAM_INT);
    $historyStmt->bindValue(2, $limit, PDO::PARAM_INT);
    $historyStmt->bindValue(3, $offset, PDO::PARAM_INT);
    $historyStmt->execute();
    $history = $historyStmt->fetchAll();

    // Quality trend data (last 20 deliveries) for chart
    $trendStmt = $db->prepare("
        SELECT
            mr.receiving_date,
            qmt.fat_percentage,
            qmt.grade
        FROM milk_receiving mr
        INNER JOIN qc_milk_tests qmt ON qmt.receiving_id = mr.id
            AND qmt.id = (SELECT MAX(id) FROM qc_milk_tests WHERE receiving_id = mr.id)
        WHERE mr.farmer_id = ?
        ORDER BY mr.receiving_date DESC
        LIMIT 20
    ");
    $trendStmt->execute([$farmerId]);
    $trend = array_reverse($trendStmt->fetchAll());

    Response::success([
        'farmer' => $farmer,
        'lifetime' => $lifetime,
        'deliveries' => $history,
        'quality_trend' => $trend,
        'pagination' => [
            'total' => $totalRows,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalRows
        ]
    ], 'Farmer detail retrieved successfully');
}
