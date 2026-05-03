<?php
/**
 * Highland Fresh System - Finance Payables API
 * 
 * GET  - List payables (unpaid POs), view details
 * POST - Record payment for PO
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

$currentUser = Auth::requireRole(['finance_officer', 'general_manager']);

$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    
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
    error_log("Finance Payables API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    global $currentUser;

    switch ($action) {
        case 'list':
            requireActionRole($currentUser, ['finance_officer', 'general_manager'], 'Access forbidden');
            getPayablesList($db);
            break;
        case 'detail':
            requireActionRole($currentUser, ['finance_officer', 'general_manager'], 'Access forbidden');
            getPayableDetail($db);
            break;
        case 'supplier_ledger':
            requireActionRole($currentUser, ['finance_officer', 'general_manager'], 'Access forbidden');
            getSupplierLedger($db);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function handlePost($db, $action, $user) {
    switch ($action) {
        case 'record_payment':
            requireActionRole($user, ['finance_officer', 'general_manager'], 'Access forbidden');
            recordPayment($db, $user);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function getPayablesList($db) {
    $status = getParam('payment_status', '');
    $search = getParam('search', '');
    $page = max(1, (int) getParam('page', 1));
    $limit = min(50, max(10, (int) getParam('limit', 15)));
    $offset = ($page - 1) * $limit;
    
    $where = ["po.status != 'cancelled'"];
    $params = [];
    
    if ($status) {
        $where[] = "po.payment_status = ?";
        $params[] = $status;
    } else {
        $where[] = "po.payment_status IN ('unpaid', 'partial')";
    }
    
    if ($search) {
        $where[] = "(
            po.po_number LIKE ?
            OR s.supplier_name LIKE ?
            OR EXISTS (
                SELECT 1
                FROM purchase_order_items poi_search
                WHERE poi_search.po_id = po.id
                AND poi_search.item_description LIKE ?
            )
        )";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Count
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM purchase_orders po 
        JOIN suppliers s ON po.supplier_id = s.id 
        WHERE {$whereClause}
    ");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetch()['total'];
    
    // Data
    $dataParams = array_merge($params, [$limit, $offset]);
    $stmt = $db->prepare("
        SELECT 
            po.id,
            po.po_number,
            po.order_date,
            po.expected_delivery,
            po.status,
            po.subtotal,
            po.vat_amount,
            po.total_amount,
            po.payment_status,
            po.received_at,
            po.notes,
            s.supplier_name,
            s.supplier_code,
            s.payment_terms,
            u.full_name as created_by_name,
            (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = po.id) as item_count,
            (SELECT GROUP_CONCAT(item_description SEPARATOR ', ') FROM purchase_order_items WHERE po_id = po.id) as item_summary,
            (SELECT COALESCE(SUM(
                CASE 
                    WHEN IFNULL(quantity_received, 0) > 0 THEN quantity_received
                    ELSE GREATEST(quantity - IFNULL(quantity_rejected, 0), 0)
                END * unit_price
            ), 0) FROM purchase_order_items WHERE po_id = po.id) as payable_total,
            CASE 
                WHEN po.status = 'received' AND po.payment_status != 'paid' 
                     AND po.expected_delivery < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                THEN 1 ELSE 0 
            END as is_overdue
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE {$whereClause}
        ORDER BY 
            CASE po.payment_status 
                WHEN 'unpaid' THEN 1 
                WHEN 'partial' THEN 2 
                ELSE 3 
            END,
            CASE po.status
                WHEN 'received' THEN 1
                WHEN 'partial_received' THEN 2
                WHEN 'ordered' THEN 3
                WHEN 'approved' THEN 4
                ELSE 5
            END,
            po.order_date ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($dataParams);
    $payables = $stmt->fetchAll();
    
    Response::paginated($payables, $total, $page, $limit, 'Payables list retrieved');
}

function getPayableDetail($db) {
    $id = getParam('id');
    if (!$id) Response::error('PO ID required', 400);
    
    $stmt = $db->prepare("
        SELECT 
            po.*,
            s.supplier_name,
            s.supplier_code,
            s.contact_person as supplier_contact,
            s.phone as supplier_phone,
            s.payment_terms as supplier_terms,
            u1.full_name as created_by_name,
            u2.full_name as approved_by_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN users u1 ON po.created_by = u1.id
        LEFT JOIN users u2 ON po.approved_by = u2.id
        WHERE po.id = ?
    ");
    $stmt->execute([$id]);
    $po = $stmt->fetch();
    
    if (!$po) Response::error('Purchase order not found', 404);
    
    // Get items
    $itemStmt = $db->prepare("
        SELECT 
            poi.*, 
            CASE 
                WHEN IFNULL(poi.quantity_received, 0) > 0 THEN poi.quantity_received
                ELSE GREATEST(poi.quantity - IFNULL(poi.quantity_rejected, 0), 0)
            END as payable_quantity,
            (CASE 
                WHEN IFNULL(poi.quantity_received, 0) > 0 THEN poi.quantity_received
                ELSE GREATEST(poi.quantity - IFNULL(poi.quantity_rejected, 0), 0)
            END * poi.unit_price) as payable_total
        FROM purchase_order_items poi
        WHERE poi.po_id = ?
        ORDER BY poi.id
    ");
    $itemStmt->execute([$id]);
    $po['items'] = $itemStmt->fetchAll();
    
    Response::success($po, 'Payable detail retrieved');
}

function getSupplierLedger($db) {
    $supplierId = getParam('supplier_id');
    if (!$supplierId) Response::error('Supplier ID required', 400);
    
    $stmt = $db->prepare("
        SELECT 
            po.id,
            po.po_number,
            po.order_date,
            po.total_amount,
            po.payment_status,
            po.status,
            po.updated_at
        FROM purchase_orders po
        WHERE po.supplier_id = ?
        AND po.status != 'cancelled'
        ORDER BY po.order_date DESC
        LIMIT 50
    ");
    $stmt->execute([$supplierId]);
    $ledger = $stmt->fetchAll();
    
    Response::success($ledger, 'Supplier ledger retrieved');
}

function recordPayment($db, $user) {
    $data = getRequestBody();
    
    $poId = $data['po_id'] ?? null;
    if (!$poId) Response::error('PO ID required', 400);

    $stepUpToken = $data['step_up_token'] ?? null;
    Auth::requireStepUp($user, 'payment_release', $stepUpToken);
    
    // Get PO
    $stmt = $db->prepare("SELECT * FROM purchase_orders WHERE id = ? AND status != 'cancelled'");
    $stmt->execute([$poId]);
    $po = $stmt->fetch();
    
    if (!$po) Response::error('Purchase order not found', 404);
    if ($po['payment_status'] === 'paid') Response::error('This PO is already fully paid', 400);
    
    // Mark as paid
    $updateStmt = $db->prepare("
        UPDATE purchase_orders 
        SET payment_status = 'paid',
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$poId]);
    
    logAudit($user['user_id'], 'PAYMENT_RELEASE', 'purchase_orders', $poId, [
        'payment_status' => $po['payment_status'],
        'total_amount' => $po['total_amount']
    ], [
        'payment_status' => 'paid',
        'total_amount' => $po['total_amount'],
        'payment_method' => $data['payment_method'] ?? 'cash',
        'reference_number' => $data['reference_number'] ?? null,
        'notes' => $data['notes'] ?? null
    ]);
    
    Response::success([
        'po_id' => $poId,
        'po_number' => $po['po_number'],
        'amount' => $po['total_amount']
    ], 'Payment recorded successfully');
}
