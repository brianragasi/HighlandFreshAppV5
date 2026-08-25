<?php
/**
 * Highland Fresh System - QC Dashboard API
 * 
 * GET - Get QC dashboard statistics
 * 
 * UPDATED: Uses milk_receiving table (revised schema)
 * 
 * @package HighlandFresh
 * @version 4.0
 * @deployed 2026-02-11 v4 - Added error logging
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
            'error_line' => $error['line']
        ]);
    }
});

// Enable error reporting for this file
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/helpers/ingredient_opening_stock.php';
require_once dirname(__DIR__) . '/helpers/procurement_notifications.php';
require_once dirname(__DIR__) . '/helpers/plain_text.php';

// Require QC role
$currentUser = Auth::requireRole(['qc_officer', 'general_manager']);

try {
    $db = Database::getInstance()->getConnection();
    ensureIngredientOpeningStockSupport($db);

    if ($requestMethod === 'POST') {
        handleFoundStockQcDecision($db, $currentUser);
        exit;
    }
    if ($requestMethod !== 'GET') Response::error('Method not allowed', 405);
    
    // Today's date
    $today = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $monthStart = date('Y-m-01');
    
    // Today's receiving stats (using milk_receiving - revised schema)
    $todayReceiving = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending_qc' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'accepted' THEN accepted_liters ELSE 0 END) as accepted_liters,
            SUM(CASE WHEN status = 'rejected' THEN volume_liters ELSE 0 END) as rejected_liters
        FROM milk_receiving
        WHERE receiving_date = ?
    ");
    $todayReceiving->execute([$today]);
    $receivingStats = $todayReceiving->fetch();
    
    // Week's grading summary (using titratable_acidity - revised schema)
    $weekGrading = $db->prepare("
        SELECT 
            grade,
            COUNT(*) as count,
            AVG(fat_percentage) as avg_fat,
            AVG(titratable_acidity) as avg_ta,
            SUM(total_amount) as total_value
        FROM qc_milk_tests
        WHERE DATE(test_datetime) >= ?
        GROUP BY grade
    ");
    $weekGrading->execute([$weekStart]);
    $gradeStats = $weekGrading->fetchAll();
    
    // Pending batch releases (use qc_status column - actual column in DB)
    $pendingBatches = $db->prepare("
        SELECT COUNT(*) as count
        FROM production_batches
        WHERE qc_status = 'pending'
    ");
    $pendingBatches->execute();
    $batchStats = $pendingBatches->fetch();
    
    // Expiry alerts (products expiring in next 3 days) - use multi-unit calculation
    $expiryAlerts = $db->prepare("
        SELECT COUNT(*) as count,
               COALESCE(SUM((COALESCE(fgi.boxes_available, 0) * COALESCE(p.pieces_per_box, 1)) + COALESCE(fgi.pieces_available, 0)), 0) as total_quantity
        FROM finished_goods_inventory fgi
        LEFT JOIN products p ON fgi.product_id = p.id
        WHERE fgi.status = 'available'
          AND fgi.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ");
    $expiryAlerts->execute();
    $expiryStats = $expiryAlerts->fetch();
    
    // Top farmers this week (using milk_receiving - revised schema)
    $topFarmers = $db->prepare("
        SELECT 
            f.farmer_code,
            COALESCE(f.first_name, '') as farmer_name,
            COUNT(mr.id) as deliveries,
            SUM(CASE WHEN mr.status = 'accepted' THEN mr.accepted_liters ELSE 0 END) as total_liters,
            AVG(qmt.fat_percentage) as avg_fat,
            AVG(qmt.final_price_per_liter) as avg_price
        FROM farmers f
        LEFT JOIN milk_receiving mr ON f.id = mr.farmer_id AND mr.receiving_date >= ?
        LEFT JOIN qc_milk_tests qmt ON mr.id = qmt.receiving_id
        GROUP BY f.id, f.farmer_code, f.first_name
        HAVING total_liters > 0
        ORDER BY total_liters DESC
        LIMIT 5
    ");
    $topFarmers->execute([$weekStart]);
    $farmerRankings = $topFarmers->fetchAll();
    
    // Recent tests (using milk_receiving and receiving_id - revised schema)
    $recentTests = $db->prepare("
        SELECT 
            qmt.test_code,
            qmt.grade,
            qmt.fat_percentage,
            qmt.total_amount,
            qmt.test_datetime,
            f.farmer_code,
            COALESCE(f.first_name, '') as farmer_name
        FROM qc_milk_tests qmt
        LEFT JOIN milk_receiving mr ON qmt.receiving_id = mr.id
        LEFT JOIN farmers f ON mr.farmer_id = f.id
        ORDER BY qmt.test_datetime DESC
        LIMIT 10
    ");
    $recentTests->execute();
    $recentTestsList = $recentTests->fetchAll();

    $notificationStmt = $db->prepare("
        SELECT id, notification_type, title, message, reference_type, reference_id, created_at
        FROM procurement_notifications
        WHERE target_role = 'qc_officer' AND is_read = 0
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $notificationStmt->execute();
    $notifications = $notificationStmt->fetchAll(PDO::FETCH_ASSOC);

    $foundStockStmt = $db->query("
        SELECT osr.id, osr.request_code, osr.quantity_to_add, osr.unit,
               osr.source_type, osr.source_reference, osr.supplier_batch_no,
               osr.received_date, osr.expiry_date, osr.reason, osr.created_at,
               osr.price_status, i.ingredient_code, i.ingredient_name,
               s.supplier_name
        FROM ingredient_opening_stock_requests osr
        JOIN ingredients i ON i.id = osr.ingredient_id
        LEFT JOIN suppliers s ON s.id = osr.supplier_id
        WHERE osr.status = 'pending'
          AND i.is_perishable = 1
          AND osr.qc_status = 'pending'
        ORDER BY osr.created_at ASC
    ");
    $foundStockChecks = $foundStockStmt->fetchAll(PDO::FETCH_ASSOC);
    
    Response::success([
        'today' => [
            'date' => $today,
            'total_deliveries' => (int) ($receivingStats['total'] ?? 0),
            'pending_tests' => (int) ($receivingStats['pending'] ?? 0),
            'accepted' => (int) ($receivingStats['accepted'] ?? 0),
            'rejected' => (int) ($receivingStats['rejected'] ?? 0),
            'accepted_liters' => (float) ($receivingStats['accepted_liters'] ?? 0),
            'rejected_liters' => (float) ($receivingStats['rejected_liters'] ?? 0)
        ],
        'week_grades' => $gradeStats,
        'pending_batch_releases' => (int) ($batchStats['count'] ?? 0),
        'expiry_alerts' => [
            'count' => (int) ($expiryStats['count'] ?? 0),
            'quantity' => (int) ($expiryStats['total_quantity'] ?? 0)
        ],
        'top_farmers' => $farmerRankings,
        'recent_tests' => $recentTestsList,
        'notifications' => $notifications,
        'found_stock_checks' => $foundStockChecks
    ], 'Dashboard data retrieved successfully');
    
} catch (Exception $e) {
    error_log("Dashboard API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}

function handleFoundStockQcDecision(PDO $db, array $currentUser): void {
    $action = getParam('action');
    if (!in_array($action, ['approve_found_stock', 'reject_found_stock'], true)) {
        Response::error('Invalid QC action', 400);
    }
    $requestId = (int) getParam('request_id', 0);
    $notes = hfPlainText(getParam('notes'), 500, false);
    if ($requestId <= 0) Response::error('Found-stock request is required', 400);
    if ($action === 'reject_found_stock' && mb_strlen($notes) < 5) {
        Response::error('Explain why the stock failed QC', 400);
    }

    try {
        $db->beginTransaction();
        $stmt = $db->prepare("
            SELECT osr.*, i.ingredient_name, i.is_perishable
            FROM ingredient_opening_stock_requests osr
            JOIN ingredients i ON i.id = osr.ingredient_id
            WHERE osr.id = ? FOR UPDATE
        ");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$request || $request['status'] !== 'pending' || $request['qc_status'] !== 'pending'
            || (int) $request['is_perishable'] !== 1) {
            throw new RuntimeException('This QC check was already completed or is no longer available');
        }

        $supplierLot = trim((string) ($request['supplier_batch_no'] ?? ''));
        if ($action === 'approve_found_stock'
            && ($supplierLot === '' || str_starts_with($supplierLot, 'INTERNAL-'))) {
            throw new RuntimeException('The real supplier lot number is required. Keep this material on hold until Warehouse or Purchasing obtains it.');
        }

        if ($action === 'reject_found_stock') {
            $db->prepare("UPDATE ingredient_opening_stock_requests
                SET qc_status = 'rejected', qc_verified_by = ?, qc_verified_at = NOW(),
                    qc_notes = ?, status = 'rejected', decided_by = ?, decided_at = NOW(),
                    decision_notes = ? WHERE id = ? AND status = 'pending'")
                ->execute([(int) $currentUser['user_id'], $notes, (int) $currentUser['user_id'],
                    'QC rejected the found stock: ' . $notes, $requestId]);
            writeProcurementNotification($db, 'warehouse_raw', 'found_stock_rejected',
                'Found stock failed QC',
                "{$request['request_code']} failed QC: {$notes}",
                'ingredient_opening_stock', $requestId);
            $message = 'Stock rejected by QC and returned to Warehouse';
        } else {
            $db->prepare("UPDATE ingredient_opening_stock_requests
                SET qc_status = 'approved', qc_verified_by = ?, qc_verified_at = NOW(), qc_notes = ?
                WHERE id = ? AND status = 'pending' AND qc_status = 'pending'")
                ->execute([(int) $currentUser['user_id'], $notes ?: 'Physical lot and expiry verified by QC', $requestId]);
            if (in_array((string) $request['price_status'], ['matched_po', 'verified'], true)) {
                writeProcurementNotification($db, 'general_manager', 'found_stock_ready_for_gm',
                    'Found stock ready for final review',
                    "{$request['request_code']}: price and required safety checks are complete.",
                    'ingredient_opening_stock', $requestId);
            }
            $message = 'QC check completed. GM will receive it after the price is verified.';
        }

        $db->prepare("UPDATE procurement_notifications SET is_read = 1
            WHERE target_role = 'qc_officer' AND notification_type = 'found_stock_qc_check'
              AND reference_type = 'ingredient_opening_stock' AND reference_id = ?")
            ->execute([$requestId]);
        logAudit($currentUser['user_id'], 'QC_FOUND_STOCK', 'ingredient_opening_stock_requests', $requestId, null, [
            'action' => $action,
            'notes' => $notes,
        ]);
        $db->commit();
        Response::success(['id' => $requestId], $message);
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        Response::error($error->getMessage(), 400);
    }
}
