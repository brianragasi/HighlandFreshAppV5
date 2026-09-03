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
require_once __DIR__ . '/payment_reference_helpers.php';

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
        case 'statement':
            requireActionRole($currentUser, ['finance_officer', 'general_manager'], 'Access forbidden');
            getPaymentStatement($db);
            break;
        case 'proof':
            requireActionRole($currentUser, ['finance_officer', 'general_manager'], 'Access forbidden');
            streamPaymentProof($db);
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
        case 'review_payment':
            requireActionRole($user, ['general_manager'], 'Only the General Manager can complete the second review');
            reviewFarmerPayment($db, $user);
            break;
        case 'change_status':
            requireActionRole($user, ['finance_officer', 'general_manager'], 'Access forbidden');
            changeFarmerPaymentStatus($db, $user);
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
            u.full_name as created_by_name,
            reviewer.full_name as reviewed_by_name,
            changer.full_name as status_changed_by_name
        FROM farmer_payments fp
        JOIN farmers f ON fp.farmer_id = f.id
        LEFT JOIN users u ON fp.created_by = u.id
        LEFT JOIN users reviewer ON fp.reviewed_by = reviewer.id
        LEFT JOIN users changer ON fp.status_changed_by = changer.id
        {$whereClause}
        ORDER BY fp.payment_date DESC, fp.id DESC
        LIMIT ?
    ");
    $stmt->execute(array_merge($params, [$limit]));

    Response::success($stmt->fetchAll(), 'Farmer payment history retrieved');
}

function recordFarmerPayment($db, $user) {
    $data = getParams();

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

    $externalReceiptNumber = trim((string) ($data['external_receipt_number'] ?? ''));
    if ($paymentMethod === 'cash') {
        if (!financeReferenceIsValid($externalReceiptNumber, false)) {
            Response::error('Official Receipt number must be 3 to 100 valid characters when provided', 400);
        }
        $referenceNumber = financeGenerateCashVoucherNumber($paymentDate);
    } else {
        $referenceNumber = trim((string) ($data['reference_number'] ?? ''));
        if (!financeReferenceIsValid($referenceNumber, true)) {
            Response::error('Enter a valid payment reference number (3 to 100 characters)', 400);
        }
        $externalReceiptNumber = '';
    }

    $verifyDeliveries = farmerPaymentTruthy($data['verify_deliveries'] ?? null);
    $verifyBankInfo = farmerPaymentTruthy($data['verify_bank_info'] ?? null);
    $verifyTransfer = farmerPaymentTruthy($data['verify_transfer'] ?? null);
    if (!$verifyDeliveries || !$verifyBankInfo || !$verifyTransfer) {
        Response::error('Payout requires delivery, payee, and transfer verification', 400);
    }

    Auth::requireStepUp($user, 'payment_release', $data['step_up_token'] ?? null);

    $proof = null;
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

        $destination = validateFarmerPaymentDestination($farmer, $paymentMethod);

        $rows = getUnpaidFarmerPaymentRows($db, $farmerId, $coveredFrom, $coveredTo, true);
        if (count($rows) === 0) {
            farmerPaymentRollbackAndError($db, 'No unpaid accepted deliveries found for this range', 400);
        }

        $summary = summarizeFarmerPaymentRows($rows);
        if ((float) $summary['total_amount'] <= 0) {
            farmerPaymentRollbackAndError($db, 'Payout amount must be greater than zero', 400);
        }

        $referenceStmt = $db->prepare('SELECT id FROM farmer_payments WHERE payment_method = ? AND reference_number = ? LIMIT 1');
        $referenceStmt->execute([$paymentMethod, $referenceNumber]);
        if ($referenceStmt->fetchColumn()) {
            farmerPaymentRollbackAndError($db, 'That payment reference has already been used for this payment method', 409);
        }

        $proof = saveFarmerPaymentProof($_FILES['proof_file'] ?? null);
        if (!$proof) {
            farmerPaymentRollbackAndError($db, 'Attach a PDF, JPG, or PNG payment proof (maximum 5 MB)', 400);
        }

        $paymentCode = generateFarmerPaymentCode($db, $paymentDate);
        $requiresReview = (float) $summary['total_amount'] >= 50000;
        $paymentStatus = $requiresReview ? 'pending_review' : 'released';
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
                status,
                reference_number,
                external_receipt_number,
                payee_name,
                destination_provider,
                destination_account,
                destination_mobile,
                proof_path,
                proof_original_name,
                proof_mime,
                remarks,
                created_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $paymentStatus,
            $referenceNumber ?: null,
            $externalReceiptNumber ?: null,
            $destination['payee_name'],
            $destination['provider'],
            $destination['account'],
            $destination['mobile'],
            $proof['path'],
            $proof['original_name'],
            $proof['mime'],
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

        logAudit($user['user_id'], $requiresReview ? 'FARMER_PAYMENT_PREPARE' : 'FARMER_PAYMENT_RELEASE', 'farmer_payments', $paymentId, null, [
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
            'external_receipt_number' => $externalReceiptNumber ?: null,
            'payment_date' => $paymentDate,
            'status' => $paymentStatus,
            'proof_attached' => true,
            'step_up_verified' => true
        ]);

        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        deleteFarmerPaymentProof($proof['path'] ?? null);
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            Response::error('That payment reference has already been used for this payment method', 409);
        }
        throw $e;
    }

    Response::success([
        'payment_id' => $paymentId,
        'payment_code' => $paymentCode,
        'payment_reference' => $referenceNumber,
        'external_receipt_number' => $externalReceiptNumber ?: null,
        'farmer_id' => $farmerId,
        'delivery_count' => $summary['delivery_count'],
        'total_liters' => $summary['total_liters'],
        'amount_paid' => $summary['total_amount'],
        'covered_from' => $summary['covered_from'],
        'covered_to' => $summary['covered_to'],
        'status' => $paymentStatus,
        'requires_second_review' => $requiresReview
    ], $requiresReview ? 'Payout prepared and reserved for a second review' : 'Farmer payout recorded successfully');
}

function validateFarmerPaymentDestination($farmer, $method) {
    $payeeName = trim((string) ($farmer['payment_payee_name'] ?? ''));
    if ($payeeName === '') {
        Response::error('Add the farmer payment payee name before recording a payout', 400);
    }

    $destination = [
        'payee_name' => $payeeName,
        'provider' => null,
        'account' => null,
        'mobile' => null
    ];

    if ($method === 'bank_transfer') {
        $bank = trim((string) ($farmer['bank_name'] ?? ''));
        $account = preg_replace('/\D+/', '', (string) ($farmer['bank_account_number'] ?? ''));
        if ($bank === '' || !preg_match('/^\d{6,20}$/', $account)) {
            Response::error('Bank Transfer is unavailable until the farmer has a payee name, bank name, and valid 6 to 20 digit account number', 400);
        }
        $destination['provider'] = $bank;
        $destination['account'] = $account;
    } elseif ($method === 'e_wallet') {
        $provider = trim((string) ($farmer['ewallet_provider'] ?? ''));
        $accountName = trim((string) ($farmer['ewallet_account_name'] ?? ''));
        $mobile = preg_replace('/\D+/', '', (string) ($farmer['ewallet_mobile_number'] ?? ''));
        if ($provider === '' || $accountName === '' || !preg_match('/^09\d{9}$/', $mobile)) {
            Response::error('E-Wallet is unavailable until the farmer has a provider, registered account name, and valid 11 digit mobile number', 400);
        }
        $destination['payee_name'] = $accountName;
        $destination['provider'] = $provider;
        $destination['mobile'] = $mobile;
    } elseif ($method === 'check') {
        $destination['provider'] = 'Check';
    } else {
        $destination['provider'] = 'Cash';
    }

    return $destination;
}

function saveFarmerPaymentProof($file) {
    if (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }
    if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > 5 * 1024 * 1024) {
        return false;
    }

    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');
    if ($finfo) finfo_close($finfo);
    $extensions = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($extensions[$mime])) {
        return false;
    }

    $year = date('Y');
    $month = date('m');
    $relativeDir = "uploads/finance/farmer_payouts/{$year}/{$month}";
    $projectRoot = dirname(__DIR__, 2);
    $absoluteDir = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
    if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
        return false;
    }
    $guardFile = $absoluteDir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($guardFile)) {
        @file_put_contents($guardFile, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl|py|sh)$\">\n  Require all denied\n</FilesMatch>\n");
    }

    $filename = 'fpy_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $filename;
    if (!@move_uploaded_file($file['tmp_name'], $absolutePath)) {
        return false;
    }
    return [
        'path' => $relativeDir . '/' . $filename,
        'original_name' => mb_substr(basename((string) ($file['name'] ?? 'payment-proof')), 0, 255),
        'mime' => $mime
    ];
}

function deleteFarmerPaymentProof($relativePath) {
    if (!$relativePath || !preg_match('#^uploads/finance/farmer_payouts/#', $relativePath)) return;
    $absolute = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($absolute)) @unlink($absolute);
}

function getPaymentStatement($db) {
    $paymentId = (int) getParam('id', 0);
    if ($paymentId <= 0) Response::error('Payment ID required', 400);

    $stmt = $db->prepare("
        SELECT fp.*, f.farmer_code, CONCAT(f.first_name, ' ', f.last_name) farmer_name,
               u.full_name created_by_name, reviewer.full_name reviewed_by_name
        FROM farmer_payments fp
        JOIN farmers f ON f.id = fp.farmer_id
        LEFT JOIN users u ON u.id = fp.created_by
        LEFT JOIN users reviewer ON reviewer.id = fp.reviewed_by
        WHERE fp.id = ?
    ");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();
    if (!$payment) Response::error('Payment not found', 404);

    $rows = $db->prepare("
        SELECT mr.receiving_code, mr.rmr_number, mr.receiving_date, mr.accepted_liters,
               qmt.fat_percentage, qmt.titratable_acidity, qmt.sediment_grade,
               qmt.final_price_per_liter, fpr.amount_paid
        FROM farmer_payment_receipts fpr
        JOIN milk_receiving mr ON mr.id = fpr.receiving_id
        JOIN qc_milk_tests qmt ON qmt.id = fpr.qc_test_id
        WHERE fpr.farmer_payment_id = ?
        ORDER BY mr.receiving_date, mr.id
    ");
    $rows->execute([$paymentId]);
    Response::success(['payment' => $payment, 'deliveries' => $rows->fetchAll()], 'Payment statement retrieved');
}

function streamPaymentProof($db) {
    $paymentId = (int) getParam('id', 0);
    $stmt = $db->prepare('SELECT proof_path, proof_original_name, proof_mime FROM farmer_payments WHERE id = ?');
    $stmt->execute([$paymentId]);
    $proof = $stmt->fetch();
    if (!$proof || empty($proof['proof_path']) || !preg_match('#^uploads/finance/farmer_payouts/#', $proof['proof_path'])) {
        Response::error('Payment proof not found', 404);
    }
    $absolute = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $proof['proof_path']);
    if (!is_file($absolute)) Response::error('Payment proof file is missing', 404);
    header('Content-Type: ' . ($proof['proof_mime'] ?: 'application/octet-stream'));
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $proof['proof_original_name'] ?: 'payment-proof') . '"');
    header('Content-Length: ' . filesize($absolute));
    readfile($absolute);
    exit;
}

function reviewFarmerPayment($db, $user) {
    $data = getParams();
    $paymentId = (int) ($data['payment_id'] ?? 0);
    if ($paymentId <= 0) Response::error('Payment ID required', 400);
    Auth::requireStepUp($user, 'payment_release', $data['step_up_token'] ?? null);

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM farmer_payments WHERE id = ? FOR UPDATE');
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        if (!$payment) farmerPaymentRollbackAndError($db, 'Payment not found', 404);
        if ($payment['status'] !== 'pending_review') farmerPaymentRollbackAndError($db, 'Only a pending payout can be reviewed', 409);
        if ((int) $payment['created_by'] === (int) $user['user_id']) {
            farmerPaymentRollbackAndError($db, 'A different authorized person must complete the second review', 403);
        }
        $db->prepare("UPDATE farmer_payments SET status = 'released', reviewed_by = ?, reviewed_at = NOW(), status_reason = NULL WHERE id = ?")
            ->execute([$user['user_id'], $paymentId]);
        logAudit($user['user_id'], 'FARMER_PAYMENT_SECOND_REVIEW', 'farmer_payments', $paymentId, ['status' => 'pending_review'], ['status' => 'released']);
        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    Response::success(['payment_id' => $paymentId, 'status' => 'released'], 'Second review completed');
}

function changeFarmerPaymentStatus($db, $user) {
    $data = getParams();
    $paymentId = (int) ($data['payment_id'] ?? 0);
    $newStatus = trim((string) ($data['status'] ?? ''));
    $reason = trim((string) ($data['reason'] ?? ''));
    if ($paymentId <= 0) Response::error('Payment ID required', 400);
    if (!in_array($newStatus, ['failed', 'cancelled', 'reversed'], true)) Response::error('Invalid payment status', 400);
    if ($newStatus === 'reversed' && ($user['role'] ?? '') !== 'general_manager') {
        Response::error('Only the General Manager can reverse a released payout', 403);
    }
    if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) Response::error('Explain the change in 10 to 500 characters', 400);
    Auth::requireStepUp($user, 'payment_release', $data['step_up_token'] ?? null);

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM farmer_payments WHERE id = ? FOR UPDATE');
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        if (!$payment) farmerPaymentRollbackAndError($db, 'Payment not found', 404);
        $allowed = [
            'pending_review' => ['failed', 'cancelled'],
            'released' => ['failed', 'reversed']
        ];
        if (!in_array($newStatus, $allowed[$payment['status']] ?? [], true)) {
            farmerPaymentRollbackAndError($db, 'That status change is not allowed for this payout', 409);
        }
        $db->prepare('UPDATE farmer_payments SET status = ?, status_reason = ?, status_changed_by = ?, status_changed_at = NOW() WHERE id = ?')
            ->execute([$newStatus, $reason, $user['user_id'], $paymentId]);
        logAudit($user['user_id'], 'FARMER_PAYMENT_STATUS_CHANGE', 'farmer_payments', $paymentId,
            ['status' => $payment['status']], ['status' => $newStatus, 'reason' => $reason]);
        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    Response::success(['payment_id' => $paymentId, 'status' => $newStatus], 'Payment status updated');
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
