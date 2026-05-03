<?php
/**
 * Highland Fresh System - Item Requests API
 *
 * Handles "Other" item requests from Purchaser for GM approval.
 *
 * GET  - List requests (GM only)
 * POST - Create request (Purchaser)
 * PUT  - Approve/Reject request (GM)
 */

require_once dirname(__DIR__) . '/bootstrap.php';

$currentUser = Auth::requireRole(['purchaser', 'general_manager']);

$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    ensureItemRequestsTable($db);

    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action, $currentUser);
            break;
        case 'POST':
            handlePost($db, $action, $currentUser);
            break;
        case 'PUT':
            handlePut($db, $action, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Item Requests API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function ensureItemRequestsTable($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS item_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            requested_by INT NOT NULL,
            item_name VARCHAR(150) NOT NULL,
            item_type ENUM('ingredient','mro') NOT NULL,
            unit_of_measure VARCHAR(50) NULL,
            notes TEXT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            approved_by INT NULL,
            approved_at DATETIME NULL,
            rejection_reason TEXT NULL,
            updated_at DATETIME NULL,
            INDEX idx_item_requests_status (status),
            INDEX idx_item_requests_requested_by (requested_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function handleGet($db, $action, $currentUser) {
    if ($currentUser['role'] !== 'general_manager') {
        Response::forbidden('Only the General Manager can view item requests');
    }

    $status = getParam('status', 'pending');

    $sql = "
        SELECT ir.*, u.full_name as requested_by_name
        FROM item_requests ir
        LEFT JOIN users u ON ir.requested_by = u.id
    ";

    $params = [];
    if ($status && $status !== 'all') {
        $sql .= " WHERE ir.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY ir.created_at ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();

    Response::success($requests, 'Item requests retrieved');
}

function handlePost($db, $action, $currentUser) {
    if ($action !== 'create') {
        Response::error('Invalid action', 400);
    }

    if ($currentUser['role'] !== 'purchaser') {
        Response::forbidden('Only the Purchaser can create item requests');
    }

    $itemName = trim(getParam('item_name', ''));
    $itemType = trim(getParam('item_type', ''));
    $unit = trim(getParam('unit_of_measure', ''));
    $notes = trim(getParam('notes', ''));

    if ($itemName === '') {
        Response::error('Item name is required', 400);
    }

    if (!in_array($itemType, ['ingredient', 'mro'], true)) {
        Response::error('Item type must be ingredient or mro', 400);
    }

    $stmt = $db->prepare("
        INSERT INTO item_requests (requested_by, item_name, item_type, unit_of_measure, notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        (int) $currentUser['user_id'],
        $itemName,
        $itemType,
        $unit ?: null,
        $notes ?: null
    ]);

    Response::success([
        'id' => $db->lastInsertId(),
        'status' => 'pending'
    ], 'Item request submitted for GM approval', 201);
}

function handlePut($db, $action, $currentUser) {
    if ($currentUser['role'] !== 'general_manager') {
        Response::forbidden('Only the General Manager can update item requests');
    }

    $id = getParam('id');
    if (!$id) {
        Response::error('Request ID is required', 400);
    }

    $stmt = $db->prepare("SELECT * FROM item_requests WHERE id = ?");
    $stmt->execute([$id]);
    $request = $stmt->fetch();

    if (!$request) {
        Response::error('Item request not found', 404);
    }

    if ($request['status'] !== 'pending') {
        Response::error('Only pending requests can be updated', 400);
    }

    if ($action === 'approve') {
        $stmt = $db->prepare("
            UPDATE item_requests
            SET status = 'approved', approved_by = ?, approved_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([(int) $currentUser['user_id'], $id]);
        Response::success(['status' => 'approved'], 'Item request approved');
    }

    if ($action === 'reject') {
        $reason = trim(getParam('reason', ''));
        $stmt = $db->prepare("
            UPDATE item_requests
            SET status = 'rejected', approved_by = ?, approved_at = NOW(),
                rejection_reason = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([(int) $currentUser['user_id'], $reason ?: null, $id]);
        Response::success(['status' => 'rejected'], 'Item request rejected');
    }

    Response::error('Invalid action', 400);
}
