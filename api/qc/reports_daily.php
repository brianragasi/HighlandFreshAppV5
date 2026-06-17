<?php
/**
 * Highland Fresh System - QC Daily Report API
 *
 * Aggregates all QC-related activity for a single date into a daily report
 * consumed by html/qc/reports/daily.html
 *
 * GET - Daily report data
 *   ?date=YYYY-MM-DD   (optional, default = today)
 *
 * Sections returned:
 *   - date            (string)  the report date
 *   - receiving       (array)   today's milk receiving summary
 *   - grading         (array)   today's QC test results breakdown by grade
 *   - top_farmers     (array)   top 5 farmers today by accepted liters
 *   - recent_tests    (array)   all QC tests performed today
 *   - disposals       (array)   today's disposal summary + by-category breakdown
 *   - batch_releases  (array)   today's QC batch release activity
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

// QC Officer, General Manager, Admin, and Finance can view
$currentUser = Auth::requireRole(['qc_officer', 'general_manager', 'admin', 'finance_officer']);

if ($requestMethod !== 'GET') {
    Response::error('Method not allowed', 405);
}

// Parse date (default = today)
$dateParam = getParam('date');
if ($dateParam && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
    $reportDate = $dateParam;
} else {
    $reportDate = date('Y-m-d');
}

// Validate date isn't in the far future
$today = date('Y-m-d');
if ($reportDate > $today) {
    Response::error('Report date cannot be in the future', 400);
}

try {
    $db = Database::getInstance()->getConnection();

    // ============================================
    // 1. Receiving summary (milk_receiving)
    // ============================================
    $receiving = $db->prepare("
        SELECT
            COUNT(*) as total_deliveries,
            SUM(CASE WHEN status = 'pending_qc' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(volume_liters) as total_liters,
            SUM(CASE WHEN status = 'accepted' THEN accepted_liters ELSE 0 END) as accepted_liters,
            SUM(CASE WHEN status = 'rejected' THEN volume_liters ELSE 0 END) as rejected_liters
        FROM milk_receiving
        WHERE receiving_date = ?
    ");
    $receiving->execute([$reportDate]);
    $receivingData = $receiving->fetch() ?: [];
    $totalDel = (int) ($receivingData['total_deliveries'] ?? 0);
    $acceptedDel = (int) ($receivingData['accepted'] ?? 0);
    $receivingData['acceptance_rate'] = $totalDel > 0
        ? round(($acceptedDel / $totalDel) * 100, 1)
        : 0.0;

    // ============================================
    // 2. Grading breakdown (qc_milk_tests)
    // ============================================
    $grading = $db->prepare("
        SELECT
            grade,
            COUNT(*) as count,
            AVG(fat_percentage) as avg_fat,
            AVG(titratable_acidity) as avg_ta,
            AVG(specific_gravity) as avg_sg,
            SUM(total_amount) as total_value
        FROM qc_milk_tests
        WHERE DATE(test_datetime) = ?
        GROUP BY grade
        ORDER BY FIELD(grade, 'A', 'B', 'C', 'D', 'Rejected')
    ");
    $grading->execute([$reportDate]);
    $gradingData = $grading->fetchAll();

    // ============================================
    // 3. Top farmers today (by accepted liters)
    // ============================================
    $topFarmers = $db->prepare("
        SELECT
            f.farmer_code,
            COALESCE(f.first_name, '') as farmer_name,
            COUNT(mr.id) as deliveries,
            SUM(CASE WHEN mr.status = 'accepted' THEN mr.accepted_liters ELSE 0 END) as total_liters,
            AVG(qmt.fat_percentage) as avg_fat
        FROM farmers f
        JOIN milk_receiving mr ON f.id = mr.farmer_id AND mr.receiving_date = ?
        LEFT JOIN qc_milk_tests qmt ON mr.id = qmt.receiving_id
        GROUP BY f.id, f.farmer_code, f.first_name
        ORDER BY total_liters DESC
        LIMIT 5
    ");
    $topFarmers->execute([$reportDate]);
    $topFarmersData = $topFarmers->fetchAll();

    // ============================================
    // 4. All tests performed today
    // ============================================
    $recentTests = $db->prepare("
        SELECT
            qmt.test_code,
            qmt.grade,
            qmt.fat_percentage,
            qmt.titratable_acidity,
            qmt.specific_gravity,
            qmt.total_amount,
            qmt.test_datetime,
            f.farmer_code,
            COALESCE(f.first_name, '') as farmer_name,
            mr.receiving_date,
            mr.volume_liters
        FROM qc_milk_tests qmt
        LEFT JOIN milk_receiving mr ON qmt.receiving_id = mr.id
        LEFT JOIN farmers f ON mr.farmer_id = f.id
        WHERE DATE(qmt.test_datetime) = ?
        ORDER BY qmt.test_datetime DESC
    ");
    $recentTests->execute([$reportDate]);
    $recentTestsData = $recentTests->fetchAll();

    // ============================================
    // 5. Disposals today
    // ============================================
    $disposals = $db->prepare("
        SELECT
            disposal_code,
            product_name,
            quantity,
            unit,
            disposal_category,
            disposal_method,
            status,
            total_value,
            initiated_at,
            source_type
        FROM disposals
        WHERE DATE(created_at) = ?
        ORDER BY created_at DESC
    ");
    $disposals->execute([$reportDate]);
    $disposalsList = $disposals->fetchAll();

    // Aggregate disposal stats
    $disposalCount = count($disposalsList);
    $disposalPending = 0;
    $disposalApproved = 0;
    $disposalCompleted = 0;
    $disposalValue = 0.0;
    $disposalQty = 0.0;
    $disposalByCategory = [];
    foreach ($disposalsList as $d) {
        $status = $d['status'] ?? 'pending';
        if ($status === 'pending') $disposalPending++;
        elseif ($status === 'approved') $disposalApproved++;
        elseif ($status === 'completed') {
            $disposalCompleted++;
            $disposalValue += (float) ($d['total_value'] ?? 0);
        }
        $disposalQty += (float) ($d['quantity'] ?? 0);
        $cat = $d['disposal_category'] ?? 'other';
        if (!isset($disposalByCategory[$cat])) {
            $disposalByCategory[$cat] = ['count' => 0, 'quantity' => 0.0, 'value' => 0.0];
        }
        $disposalByCategory[$cat]['count']++;
        $disposalByCategory[$cat]['quantity'] += (float) ($d['quantity'] ?? 0);
        $disposalByCategory[$cat]['value'] += (float) ($d['total_value'] ?? 0);
    }

    // ============================================
    // 6. Batch release activity today
    // ============================================
    $batchReleases = $db->prepare("
        SELECT
            pb.batch_code,
            mr.product_name,
            pb.qc_status,
            pb.qc_released_at,
            pb.released_by as qc_released_by,
            COALESCE(released_user.first_name, '') as released_by_name,
            pb.expected_yield as planned_quantity,
            pb.actual_yield as actual_quantity
        FROM production_batches pb
        LEFT JOIN master_recipes mr ON pb.recipe_id = mr.id
        LEFT JOIN users released_user ON pb.released_by = released_user.id
        WHERE DATE(pb.qc_released_at) = ?
        ORDER BY pb.qc_released_at DESC
    ");
    $batchReleases->execute([$reportDate]);
    $batchList = $batchReleases->fetchAll();

    // Aggregate batch release counts
    $batchReleased = 0;
    $batchRejected = 0;
    $batchTotalYield = 0;
    foreach ($batchList as $b) {
        if (($b['qc_status'] ?? '') === 'released') {
            $batchReleased++;
            $batchTotalYield += (int) ($b['actual_quantity'] ?? 0);
        } elseif (($b['qc_status'] ?? '') === 'rejected') {
            $batchRejected++;
        }
    }

    Response::success([
        'date' => $reportDate,
        'receiving' => $receivingData,
        'grading' => $gradingData,
        'top_farmers' => $topFarmersData,
        'recent_tests' => $recentTestsData,
        'disposals' => [
            'list' => $disposalsList,
            'total' => $disposalCount,
            'pending' => $disposalPending,
            'approved' => $disposalApproved,
            'completed' => $disposalCompleted,
            'total_quantity' => round($disposalQty, 2),
            'total_value' => round($disposalValue, 2),
            'by_category' => $disposalByCategory
        ],
        'batch_releases' => [
            'list' => $batchList,
            'released' => $batchReleased,
            'rejected' => $batchRejected,
            'total_yield' => $batchTotalYield
        ]
    ], 'Daily report data retrieved successfully');

} catch (Exception $e) {
    error_log("QC Daily Report API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
