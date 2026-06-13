<?php
/**
 * Highland Fresh System - Farmer Payments API
 *
 * GET  - Preview unpaid farmer deliveries and payout history
 * POST - Record farmer milk payout
 *
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/farmer_payment_helpers.php';

$currentUser = Auth::requireRole(['finance_officer', 'general_manager']);

$action = getParam('action', 'unpaid_deliveries');

try {
    $db = Database::getInstance()->getConnection();
    ensureFarmerPaymentTables($db);

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
    error_log("Farmer Payments API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    global $currentUser;

    switch ($action) {
        case 'unpaid_deliveries':
            requireActionRole($currentUser, ['finance_officer', 'general_manager'], 'Access forbidden');
            getUnpaidDeliveries($db);
            break;
        case 'history':
            requireActionRole($currentUser, ['finance_officer', 'general_manager'], 'Access forbidden');
            getPaymentHistory($db);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function handlePost($db, $action, $user) {
    switch ($action) {
        case 'record_payment':
            requireActionRole($user, ['finance_officer', 'general_manager'], 'Access forbidden');
            recordFarmerPayment($db, $user);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function getUnpaidDeliveries($db) {
    $farmerId = (int) getParam('farmer_id', 0);
    if ($farmerId <= 0) {
        Response::error('Farmer ID required', 400);
    }

    $coveredFrom = normalizePaymentDate(getParam('covered_from'), 'covered_from');
    $coveredTo = normalizePaymentDate(getParam('covered_to'), 'covered_to');
    if ($coveredFrom && $coveredTo && $coveredFrom > $coveredTo) {
        Response::error('Covered from date cannot be later than covered to date', 400);
    }

    $rows = getUnpaidFarmerPaymentRows($db, $farmerId, $coveredFrom, $coveredTo);
    Response::success([
        'summary' => summarizeFarmerPaymentRows($rows),
        'deliveries' => $rows
    ], 'Unpaid farmer deliveries retrieved');
}

function getPaymentHistory($db) {
    $farmerId = (int) getParam('farmer_id', 0);
    $limit = min(50, max(1, (int) getParam('limit', 10)));

    $where = [];
    $params = [];
    if ($farmerId > 0) {
        $where[] = 'fp.farmer_id = ?';
        $params[] = $farmerId;
    }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("
        SELECT
            fp.*,
            CONCAT(f.first_name, ' ', f.last_name) as farmer_name,
            f.farmer_code,
            u.full_name as created_by_name
        FROM farmer_payments fp
        JOIN farmers f ON fp.farmer_id = f.id
        LEFT JOIN users u ON fp.created_by = u.id
        {$whereClause}
        ORDER BY fp.payment_date DESC, fp.id DESC
        LIMIT ?
    ");
    $stmt->execute(array_merge($params, [$limit]));

    Response::success($stmt->fetchAll(), 'Farmer payment history retrieved');
}

function recordFarmerPayment($db, $user) {
    $data = getRequestBody();

    $farmerId = (int) ($data['farmer_id'] ?? 0);
    if ($farmerId <= 0) {
        Response::error('Farmer ID required', 400);
    }

    $paymentDate = normalizePaymentDate($data['payment_date'] ?? date('Y-m-d'), 'payment_date') ?: date('Y-m-d');
    $coveredFrom = normalizePaymentDate($data['covered_from'] ?? null, 'covered_from');
    $coveredTo = normalizePaymentDate($data['covered_to'] ?? null, 'covered_to');
    if ($coveredFrom && $coveredTo && $coveredFrom > $coveredTo) {
        Response::error('Covered from date cannot be later than covered to date', 400);
    }

    $paymentMethod = $data['payment_method'] ?? 'bank_transfer';
    $allowedMethods = ['cash', 'check', 'bank_transfer', 'e_wallet'];
    if (!in_array($paymentMethod, $allowedMethods, true)) {
        Response::error('Invalid payment method', 400);
    }

    $referenceNumber = trim((string) ($data['reference_number'] ?? ''));
    if ($paymentMethod !== 'cash' && $referenceNumber === '') {
        Response::error('Reference number is required for non-cash payouts', 400);
    }

    $verifyDeliveries = farmerPaymentTruthy($data['verify_deliveries'] ?? null);
    $verifyBankInfo = farmerPaymentTruthy($data['verify_bank_info'] ?? null);
    $verifyTransfer = farmerPaymentTruthy($data['verify_transfer'] ?? null);
    if (!$verifyDeliveries || !$verifyBankInfo || !$verifyTransfer) {
        Response::error('Payout requires delivery, payee, and transfer verification', 400);
    }

    Auth::requireStepUp($user, 'payment_release', $data['step_up_token'] ?? null);

    $db->beginTransaction();
    try {
        $farmerStmt = $db->prepare("
            SELECT *
            FROM farmers
            WHERE id = ?
            FOR UPDATE
        ");
        $farmerStmt->execute([$farmerId]);
        $farmer = $farmerStmt->fetch();
        if (!$farmer) {
            farmerPaymentRollbackAndError($db, 'Farmer not found', 404);
        }
        if ((int) ($farmer['is_active'] ?? 0) !== 1) {
            farmerPaymentRollbackAndError($db, 'Cannot release payout for an inactive farmer', 400);
        }

        $rows = getUnpaidFarmerPaymentRows($db, $farmerId, $coveredFrom, $coveredTo, true);
        if (count($rows) === 0) {
            farmerPaymentRollbackAndError($db, 'No unpaid accepted deliveries found for this range', 400);
        }

        $summary = summarizeFarmerPaymentRows($rows);
        if ((float) $summary['total_amount'] <= 0) {
            farmerPaymentRollbackAndError($db, 'Payout amount must be greater than zero', 400);
        }

        $paymentCode = generateFarmerPaymentCode($db, $paymentDate);
        $insertStmt = $db->prepare("
            INSERT INTO farmer_payments
            (
                payment_code,
                farmer_id,
                covered_from,
                covered_to,
                delivery_count,
                total_liters,
                gross_amount,
                amount_paid,
                payment_date,
                payment_method,
                reference_number,
                remarks,
                created_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([
            $paymentCode,
            $farmerId,
            $summary['covered_from'],
            $summary['covered_to'],
            $summary['delivery_count'],
            $summary['total_liters'],
            $summary['total_amount'],
            $summary['total_amount'],
            $paymentDate,
            $paymentMethod,
            $referenceNumber ?: null,
            $data['notes'] ?? null,
            $user['user_id']
        ]);
        $paymentId = (int) $db->lastInsertId();

        $receiptStmt = $db->prepare("
            INSERT INTO farmer_payment_receipts
            (farmer_payment_id, receiving_id, qc_test_id, amount_paid)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($rows as $row) {
            $receiptStmt->execute([
                $paymentId,
                $row['receiving_id'],
                $row['qc_test_id'],
                $row['total_amount']
            ]);
        }

        logAudit($user['user_id'], 'FARMER_PAYMENT_RELEASE', 'farmer_payments', $paymentId, null, [
            'payment_code' => $paymentCode,
            'farmer_id' => $farmerId,
            'farmer_code' => $farmer['farmer_code'] ?? null,
            'covered_from' => $summary['covered_from'],
            'covered_to' => $summary['covered_to'],
            'delivery_count' => $summary['delivery_count'],
            'total_liters' => $summary['total_liters'],
            'amount_paid' => $summary['total_amount'],
            'payment_method' => $paymentMethod,
            'reference_number' => $referenceNumber ?: null,
            'payment_date' => $paymentDate,
            'step_up_verified' => true
        ]);

        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    Response::success([
        'payment_id' => $paymentId,
        'payment_code' => $paymentCode,
        'farmer_id' => $farmerId,
        'delivery_count' => $summary['delivery_count'],
        'total_liters' => $summary['total_liters'],
        'amount_paid' => $summary['total_amount'],
        'covered_from' => $summary['covered_from'],
        'covered_to' => $summary['covered_to']
    ], 'Farmer payout recorded successfully');
}

function farmerPaymentTruthy($value) {
    return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
}

function farmerPaymentRollbackAndError($db, $message, $code = 400) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    Response::error($message, $code);
}

