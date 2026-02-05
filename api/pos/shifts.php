<?php
/**
 * Highland Fresh System - Cashier Shifts API
 * 
 * Shift/session management for cashiers
 * Start/end shift, cash handling, adjustments
 * 
 * GET - Current shift, shift history
 * POST - Start/end shift, record cash adjustments
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Cashier or GM role
$currentUser = Auth::requireRole(['cashier', 'general_manager']);

$action = getParam('action', 'current');

try {
    $db = Database::getInstance()->getConnection();
    
    // Ensure necessary tables exist
    ensureShiftTables($db);
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action, $currentUser);
            break;
        case 'POST':
            handlePost($db, $action, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Shifts API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

/**
 * Ensure shift tables exist
 */
function ensureShiftTables($db) {
    // Cashier shifts table
    $db->exec("
        CREATE TABLE IF NOT EXISTS cashier_shifts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            shift_code VARCHAR(30) NOT NULL UNIQUE,
            cashier_id INT NOT NULL,
            
            start_time DATETIME NOT NULL,
            end_time DATETIME NULL,
            
            opening_cash DECIMAL(12,2) NOT NULL DEFAULT 0,
            expected_cash DECIMAL(12,2) NULL,
            actual_cash DECIMAL(12,2) NULL,
            cash_variance DECIMAL(12,2) NULL,
            
            total_sales DECIMAL(12,2) DEFAULT 0,
            total_collections DECIMAL(12,2) DEFAULT 0,
            total_transactions INT DEFAULT 0,
            
            cash_in DECIMAL(12,2) DEFAULT 0,
            cash_out DECIMAL(12,2) DEFAULT 0,
            
            status ENUM('active', 'closed', 'reconciled') DEFAULT 'active',
            
            opening_notes TEXT,
            closing_notes TEXT,
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_cashier (cashier_id),
            INDEX idx_date (start_time),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Cash adjustments table
    $db->exec("
        CREATE TABLE IF NOT EXISTS cash_adjustments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            shift_id INT NULL,
            adjustment_type ENUM('in', 'out') NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            reason VARCHAR(255) NOT NULL,
            reference_number VARCHAR(50) NULL,
            
            performed_by INT NOT NULL,
            approved_by INT NULL,
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_shift (shift_id),
            INDEX idx_date (created_at),
            INDEX idx_type (adjustment_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/**
 * Generate shift code
 */
function generateShiftCode($db, $cashierId) {
    $date = date('Ymd');
    $prefix = "SHIFT-{$date}-";
    
    // Get the last shift number for today
    $stmt = $db->prepare("
        SELECT shift_code 
        FROM cashier_shifts 
        WHERE shift_code LIKE ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetch();
    
    if ($last) {
        $lastNum = intval(substr($last['shift_code'], -3));
        $newNum = $lastNum + 1;
    } else {
        $newNum = 1;
    }
    
    return $prefix . str_pad($newNum, 3, '0', STR_PAD_LEFT);
}

// ========================================
// GET HANDLERS
// ========================================

function handleGet($db, $action, $currentUser) {
    switch ($action) {
        case 'current':
            // Get current active shift for the cashier
            $stmt = $db->prepare("
                SELECT 
                    cs.*,
                    u.first_name as cashier_first_name,
                    u.last_name as cashier_last_name,
                    u.username as cashier_username
                FROM cashier_shifts cs
                LEFT JOIN users u ON cs.cashier_id = u.id
                WHERE cs.cashier_id = ?
                AND cs.status = 'active'
                ORDER BY cs.start_time DESC
                LIMIT 1
            ");
            $stmt->execute([$currentUser['user_id']]);
            $shift = $stmt->fetch();
            
            if (!$shift) {
                Response::success([
                    'has_active_shift' => false,
                    'shift' => null
                ], 'No active shift found');
            }
            
            // Get shift transactions summary
            $txSummary = getShiftTransactions($db, $shift['id'], $shift['start_time']);
            
            // Get cash adjustments
            $adjustStmt = $db->prepare("
                SELECT * FROM cash_adjustments 
                WHERE shift_id = ?
                ORDER BY created_at ASC
            ");
            $adjustStmt->execute([$shift['id']]);
            $adjustments = $adjustStmt->fetchAll();
            
            $shift['transactions'] = $txSummary;
            $shift['adjustments'] = $adjustments;
            $shift['has_active_shift'] = true;
            
            // Calculate current expected cash
            $expectedCash = floatval($shift['opening_cash']) 
                + floatval($txSummary['cash_sales']) 
                + floatval($txSummary['cash_collections'])
                + floatval($shift['cash_in'])
                - floatval($shift['cash_out']);
            
            $shift['current_expected_cash'] = $expectedCash;
            
            Response::success($shift, 'Current shift retrieved');
            break;
            
        case 'history':
            // Get shift history
            $cashierId = getParam('cashier_id', $currentUser['user_id']);
            $fromDate = getParam('from_date');
            $toDate = getParam('to_date');
            $limit = min(100, max(10, intval(getParam('limit', 30))));
            
            $sql = "
                SELECT 
                    cs.*,
                    u.first_name as cashier_first_name,
                    u.last_name as cashier_last_name,
                    u.username as cashier_username
                FROM cashier_shifts cs
                LEFT JOIN users u ON cs.cashier_id = u.id
                WHERE 1=1
            ";
            $params = [];
            
            // Only GM can see all shifts
            if ($currentUser['role'] !== 'general_manager') {
                $sql .= " AND cs.cashier_id = ?";
                $params[] = $currentUser['user_id'];
            } else if ($cashierId) {
                $sql .= " AND cs.cashier_id = ?";
                $params[] = $cashierId;
            }
            
            if ($fromDate) {
                $sql .= " AND DATE(cs.start_time) >= ?";
                $params[] = $fromDate;
            }
            
            if ($toDate) {
                $sql .= " AND DATE(cs.start_time) <= ?";
                $params[] = $toDate;
            }
            
            $sql .= " ORDER BY cs.start_time DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $shifts = $stmt->fetchAll();
            
            Response::success([
                'shifts' => $shifts,
                'count' => count($shifts)
            ], 'Shift history retrieved');
            break;
            
        case 'detail':
            // Get shift details
            $id = getParam('id');
            $code = getParam('code');
            
            if (!$id && !$code) {
                Response::error('Shift ID or code required', 400);
            }
            
            $sql = "
                SELECT 
                    cs.*,
                    u.first_name as cashier_first_name,
                    u.last_name as cashier_last_name
                FROM cashier_shifts cs
                LEFT JOIN users u ON cs.cashier_id = u.id
                WHERE " . ($id ? "cs.id = ?" : "cs.shift_code = ?");
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$id ?: $code]);
            $shift = $stmt->fetch();
            
            if (!$shift) {
                Response::error('Shift not found', 404);
            }
            
            // Security check
            if ($currentUser['role'] !== 'general_manager' && $shift['cashier_id'] != $currentUser['user_id']) {
                Response::error('Access denied', 403);
            }
            
            // Get transactions
            $shift['transactions'] = getShiftTransactions($db, $shift['id'], $shift['start_time'], $shift['end_time']);
            
            // Get adjustments
            $adjustStmt = $db->prepare("SELECT * FROM cash_adjustments WHERE shift_id = ? ORDER BY created_at");
            $adjustStmt->execute([$shift['id']]);
            $shift['adjustments'] = $adjustStmt->fetchAll();
            
            Response::success($shift, 'Shift details retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

// ========================================
// POST HANDLERS
// ========================================

function handlePost($db, $action, $currentUser) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'start':
            // Start a new shift
            $openingCash = floatval($data['opening_cash'] ?? 0);
            $notes = $data['notes'] ?? null;
            
            // Check if there's already an active shift
            $checkStmt = $db->prepare("
                SELECT id FROM cashier_shifts 
                WHERE cashier_id = ? AND status = 'active'
            ");
            $checkStmt->execute([$currentUser['user_id']]);
            if ($checkStmt->fetch()) {
                Response::error('You already have an active shift. Please end it before starting a new one.', 400);
            }
            
            // Generate shift code
            $shiftCode = generateShiftCode($db, $currentUser['user_id']);
            
            // Create shift
            $stmt = $db->prepare("
                INSERT INTO cashier_shifts 
                (shift_code, cashier_id, start_time, opening_cash, status, opening_notes)
                VALUES (?, ?, NOW(), ?, 'active', ?)
            ");
            $stmt->execute([
                $shiftCode,
                $currentUser['user_id'],
                $openingCash,
                $notes
            ]);
            
            $shiftId = $db->lastInsertId();
            
            logAudit(
                $currentUser['user_id'],
                'start_shift',
                'cashier_shifts',
                $shiftId,
                null,
                ['opening_cash' => $openingCash]
            );
            
            Response::created([
                'shift_id' => $shiftId,
                'shift_code' => $shiftCode,
                'start_time' => date('Y-m-d H:i:s'),
                'opening_cash' => $openingCash
            ], 'Shift started successfully');
            break;
            
        case 'end':
            // End current shift
            $actualCash = isset($data['actual_cash']) ? floatval($data['actual_cash']) : null;
            $notes = $data['notes'] ?? null;
            
            $db->beginTransaction();
            
            try {
                // Get current active shift
                $shiftStmt = $db->prepare("
                    SELECT * FROM cashier_shifts 
                    WHERE cashier_id = ? AND status = 'active'
                    FOR UPDATE
                ");
                $shiftStmt->execute([$currentUser['user_id']]);
                $shift = $shiftStmt->fetch();
                
                if (!$shift) {
                    throw new Exception('No active shift found');
                }
                
                // Calculate shift totals
                $txSummary = getShiftTransactions($db, $shift['id'], $shift['start_time']);
                
                $totalSales = floatval($txSummary['total_sales']);
                $totalCollections = floatval($txSummary['total_collections']);
                $totalTransactions = intval($txSummary['sales_count']) + intval($txSummary['collections_count']);
                
                // Calculate expected cash
                $expectedCash = floatval($shift['opening_cash']) 
                    + floatval($txSummary['cash_sales']) 
                    + floatval($txSummary['cash_collections'])
                    + floatval($shift['cash_in'])
                    - floatval($shift['cash_out']);
                
                $variance = null;
                if ($actualCash !== null) {
                    $variance = $actualCash - $expectedCash;
                }
                
                // Update shift
                $updateStmt = $db->prepare("
                    UPDATE cashier_shifts SET
                        end_time = NOW(),
                        expected_cash = ?,
                        actual_cash = ?,
                        cash_variance = ?,
                        total_sales = ?,
                        total_collections = ?,
                        total_transactions = ?,
                        status = 'closed',
                        closing_notes = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $expectedCash,
                    $actualCash,
                    $variance,
                    $totalSales,
                    $totalCollections,
                    $totalTransactions,
                    $notes,
                    $shift['id']
                ]);
                
                logAudit(
                    $currentUser['user_id'],
                    'end_shift',
                    'cashier_shifts',
                    $shift['id'],
                    ['status' => 'active'],
                    [
                        'status' => 'closed',
                        'expected_cash' => $expectedCash,
                        'actual_cash' => $actualCash,
                        'variance' => $variance
                    ]
                );
                
                $db->commit();
                
                Response::success([
                    'shift_id' => $shift['id'],
                    'shift_code' => $shift['shift_code'],
                    'start_time' => $shift['start_time'],
                    'end_time' => date('Y-m-d H:i:s'),
                    'opening_cash' => floatval($shift['opening_cash']),
                    'total_sales' => $totalSales,
                    'total_collections' => $totalCollections,
                    'total_transactions' => $totalTransactions,
                    'cash_summary' => [
                        'opening' => floatval($shift['opening_cash']),
                        'cash_sales' => floatval($txSummary['cash_sales']),
                        'cash_collections' => floatval($txSummary['cash_collections']),
                        'cash_in' => floatval($shift['cash_in']),
                        'cash_out' => floatval($shift['cash_out']),
                        'expected' => $expectedCash,
                        'actual' => $actualCash,
                        'variance' => $variance
                    ],
                    'status' => 'closed'
                ], 'Shift ended successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'cash_adjustment':
            // Record cash in/out adjustment
            $type = $data['type'] ?? null; // 'in' or 'out'
            $amount = floatval($data['amount'] ?? 0);
            $reason = $data['reason'] ?? null;
            $reference = $data['reference_number'] ?? null;
            
            if (!in_array($type, ['in', 'out'])) {
                Response::error('Adjustment type must be "in" or "out"', 400);
            }
            
            if ($amount <= 0) {
                Response::error('Amount must be greater than 0', 400);
            }
            
            if (!$reason) {
                Response::error('Reason is required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Get current active shift
                $shiftStmt = $db->prepare("
                    SELECT id, cash_in, cash_out FROM cashier_shifts 
                    WHERE cashier_id = ? AND status = 'active'
                    FOR UPDATE
                ");
                $shiftStmt->execute([$currentUser['user_id']]);
                $shift = $shiftStmt->fetch();
                
                $shiftId = $shift ? $shift['id'] : null;
                
                // Create adjustment record
                $insertStmt = $db->prepare("
                    INSERT INTO cash_adjustments 
                    (shift_id, adjustment_type, amount, reason, reference_number, performed_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([
                    $shiftId,
                    $type,
                    $amount,
                    $reason,
                    $reference,
                    $currentUser['user_id']
                ]);
                
                $adjustmentId = $db->lastInsertId();
                
                // Update shift totals if there's an active shift
                if ($shiftId) {
                    $column = $type === 'in' ? 'cash_in' : 'cash_out';
                    $db->prepare("
                        UPDATE cashier_shifts 
                        SET {$column} = {$column} + ?
                        WHERE id = ?
                    ")->execute([$amount, $shiftId]);
                }
                
                logAudit(
                    $currentUser['user_id'],
                    'cash_adjustment',
                    'cash_adjustments',
                    $adjustmentId,
                    null,
                    ['type' => $type, 'amount' => $amount, 'reason' => $reason]
                );
                
                $db->commit();
                
                Response::created([
                    'adjustment_id' => $adjustmentId,
                    'shift_id' => $shiftId,
                    'type' => $type,
                    'amount' => $amount,
                    'reason' => $reason,
                    'created_at' => date('Y-m-d H:i:s')
                ], 'Cash adjustment recorded');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Helper function to get shift transactions summary
 */
function getShiftTransactions($db, $shiftId, $startTime, $endTime = null) {
    $endCondition = $endTime ? " AND created_at <= ?" : "";
    $params = [$startTime];
    if ($endTime) $params[] = $endTime;
    
    // Sales summary
    $salesStmt = $db->prepare("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(total_amount), 0) as total_amount,
            COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount_paid ELSE 0 END), 0) as cash_amount,
            COALESCE(SUM(CASE WHEN payment_method = 'gcash' THEN amount_paid ELSE 0 END), 0) as gcash_amount,
            COALESCE(SUM(CASE WHEN payment_method = 'check' THEN amount_paid ELSE 0 END), 0) as check_amount,
            COALESCE(SUM(CASE WHEN payment_method = 'bank_transfer' THEN amount_paid ELSE 0 END), 0) as bank_amount
        FROM sales_transactions 
        WHERE created_at >= ? {$endCondition}
        AND payment_status != 'voided'
    ");
    $salesStmt->execute($params);
    $sales = $salesStmt->fetch();
    
    // Collections summary
    $collParams = [$startTime];
    if ($endTime) $collParams[] = $endTime;
    
    $collEndCondition = $endTime ? " AND collected_at <= ?" : "";
    $collStmt = $db->prepare("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(amount_collected), 0) as total_amount,
            COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount_collected ELSE 0 END), 0) as cash_amount,
            COALESCE(SUM(CASE WHEN payment_method = 'gcash' THEN amount_collected ELSE 0 END), 0) as gcash_amount,
            COALESCE(SUM(CASE WHEN payment_method = 'check' THEN amount_collected ELSE 0 END), 0) as check_amount,
            COALESCE(SUM(CASE WHEN payment_method = 'bank_transfer' THEN amount_collected ELSE 0 END), 0) as bank_amount
        FROM payment_collections 
        WHERE collected_at >= ? {$collEndCondition}
        AND status = 'confirmed'
    ");
    $collStmt->execute($collParams);
    $collections = $collStmt->fetch();
    
    return [
        'sales_count' => intval($sales['count']),
        'total_sales' => floatval($sales['total_amount']),
        'cash_sales' => floatval($sales['cash_amount']),
        'gcash_sales' => floatval($sales['gcash_amount']),
        'check_sales' => floatval($sales['check_amount']),
        'bank_sales' => floatval($sales['bank_amount']),
        
        'collections_count' => intval($collections['count']),
        'total_collections' => floatval($collections['total_amount']),
        'cash_collections' => floatval($collections['cash_amount']),
        'gcash_collections' => floatval($collections['gcash_amount']),
        'check_collections' => floatval($collections['check_amount']),
        'bank_collections' => floatval($collections['bank_amount'])
    ];
}
