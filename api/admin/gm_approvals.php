<?php
/**
 * Highland Fresh System - GM Approvals API
 * 
 * Centralized approval dashboard for General Manager
 * GET - List all pending approvals across modules
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/helpers/plain_text.php';
require_once dirname(__DIR__) . '/helpers/procurement_notifications.php';

// Require GM role only
$currentUser = Auth::requireRole(['general_manager']);

$action = getParam('action', 'dashboard');

try {
    $db = Database::getInstance()->getConnection();
    
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
    error_log("GM Approvals API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action, $currentUser) {
    switch ($action) {
        case 'dashboard':
            $stats = buildGmApprovalStats($db);
            Response::success($stats, 'Dashboard stats retrieved');
            break;

        case 'unified_queue':
            // Full action list for the GM Approvals workspace (synced with admin dashboard)
            $items = buildGmUnifiedQueue($db);
            $stats = buildGmApprovalStats($db);
            // Ensure stats reflect actual items (including server-side fallbacks)
            $stats['sales_orders'] = count(array_filter($items, fn($i) => ($i['category'] ?? '') === 'credit'));
            $stats['credit_overrides'] = count(array_filter($items, fn($i) => ($i['type'] ?? '') === 'credit_override'));
            $stats['disposals'] = count(array_filter($items, fn($i) => ($i['category'] ?? '') === 'disposal'));
            $stats['procurement'] = count(array_filter($items, fn($i) => ($i['category'] ?? '') === 'procurement'));
            $stats['production_materials'] = count(array_filter($items, fn($i) => ($i['category'] ?? '') === 'production'));
            $stats['all_queues'] = count($items);
            Response::success([
                'items' => $items,
                'stats' => $stats,
            ], 'Unified approval queue retrieved');
            break;
            
        case 'pending_pos':
            $stmt = $db->query("
                SELECT 
                    po.*,
                    s.supplier_name,
                    s.supplier_code,
                    u.full_name as requested_by,
                    (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = po.id) as item_count
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                LEFT JOIN users u ON po.created_by = u.id
                WHERE po.status = 'pending'
                ORDER BY po.created_at ASC
            ");
            $orders = $stmt->fetchAll();
            
            // Get items for each order
            foreach ($orders as &$order) {
                $itemsStmt = $db->prepare("
                    SELECT item_description, quantity, unit, unit_price, total_amount,
                           supplier_order_quantity, supplier_order_unit,
                           supplier_order_unit_price, stock_quantity_per_supplier_unit
                    FROM purchase_order_items WHERE po_id = ?
                ");
                $itemsStmt->execute([$order['id']]);
                $order['items'] = $itemsStmt->fetchAll();
                addProcurementQuantityDisplays($order['items']);
            }
            
            Response::success($orders, 'Pending POs retrieved');
            break;

        case 'decision_history':
            $limit = min(50, max(5, (int) getParam('limit', 12)));
            $stmt = $db->prepare("
                SELECT
                    a.id AS audit_id,
                    a.action AS decision,
                    a.entry_hash AS audit_hash,
                    a.created_at AS decision_at,
                    po.id AS po_id,
                    po.po_number,
                    po.status AS current_status,
                    po.total_amount,
                    po.payment_terms,
                    po.approval_remarks,
                    po.rejection_reason,
                    po.purchase_request_id,
                    pr.pr_number,
                    s.supplier_name,
                    s.supplier_code,
                    COALESCE(u.full_name, po.approver_name, 'General Manager') AS decided_by
                FROM audit_logs a
                JOIN purchase_orders po ON po.id = a.record_id
                JOIN suppliers s ON s.id = po.supplier_id
                LEFT JOIN purchase_requests pr ON pr.id = po.purchase_request_id
                LEFT JOIN users u ON u.id = a.user_id
                WHERE a.table_name = 'purchase_orders'
                  AND a.action IN ('APPROVE', 'REJECT')
                ORDER BY a.id DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            Response::success($stmt->fetchAll(PDO::FETCH_ASSOC), 'GM Purchase Order decision history retrieved');
            break;
            
        case 'pending_requisitions':
            $stmt = $db->query("
                SELECT 
                    mr.*,
                    pmr.recipe_code as planned_recipe_code,
                    pmr.product_name as planned_product_name,
                    pmr.variant as planned_variant,
                    u.full_name as requested_by_name
                FROM material_requisitions mr
                LEFT JOIN master_recipes pmr ON mr.planned_recipe_id = pmr.id
                LEFT JOIN users u ON mr.requested_by = u.id
                WHERE mr.status = 'pending'
                ORDER BY 
                    FIELD(mr.priority, 'urgent', 'high', 'normal', 'low'),
                    mr.created_at ASC
            ");
            $requisitions = $stmt->fetchAll();
            
            // Get items for each requisition
            foreach ($requisitions as &$req) {
                $itemsStmt = $db->prepare("
                    SELECT item_name, requested_quantity as quantity, unit_of_measure as unit, notes
                    FROM requisition_items WHERE requisition_id = ?
                ");
                $itemsStmt->execute([$req['id']]);
                $req['items'] = $itemsStmt->fetchAll();
            }
            
            Response::success($requisitions, 'Pending requisitions retrieved');
            break;

        case 'pending_item_requests':
            try {
                $stmt = $db->query("
                    SELECT ir.*, u.full_name as requested_by_name
                    FROM item_requests ir
                    LEFT JOIN users u ON ir.requested_by = u.id
                    WHERE ir.status = 'pending'
                    ORDER BY ir.created_at ASC
                ");
                $requests = $stmt->fetchAll();
                Response::success($requests, 'Pending item requests retrieved');
            } catch (Exception $e) {
                Response::success([], 'Pending item requests retrieved');
            }
            break;
            
        case 'price_alerts':
            // Get recent significant price changes
            $stmt = $db->query("
                SELECT 
                    'ingredient' as item_type,
                    ph.id,
                    i.ingredient_code as item_code,
                    i.ingredient_name as item_name,
                    ph.old_price,
                    ph.new_price,
                    ph.price_change,
                    ph.change_percent,
                    s.supplier_name,
                    po.po_number,
                    ph.reason,
                    u.full_name as updated_by,
                    ph.created_at
                FROM ingredient_price_history ph
                JOIN ingredients i ON ph.ingredient_id = i.id
                LEFT JOIN suppliers s ON ph.supplier_id = s.id
                LEFT JOIN purchase_orders po ON ph.po_id = po.id
                LEFT JOIN users u ON ph.updated_by = u.id
                WHERE ph.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY ABS(ph.change_percent) DESC
                LIMIT 20
            ");
            $alerts = $stmt->fetchAll();
            
            Response::success($alerts, 'Price alerts retrieved');
            break;
            
        case 'all_pending':
            // Combined view of all pending approvals
            $pending = [];
            
            // Pending POs
            $stmt = $db->query("
                SELECT 
                    'purchase_order' as type,
                    po.id,
                    po.po_number as reference,
                    CONCAT('PO for ', s.supplier_name) as description,
                    po.total_amount as amount,
                    po.payment_terms,
                    u.full_name as requested_by,
                    po.created_at,
                    'pending' as status,
                    'high' as priority
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                LEFT JOIN users u ON po.created_by = u.id
                WHERE po.status = 'pending'
            ");
            $pos = $stmt->fetchAll();
            $pending = array_merge($pending, $pos);
            
            // Pending Requisitions
            $stmt = $db->query("
                SELECT 
                    'requisition' as type,
                    mr.id,
                    mr.requisition_code as reference,
                    CONCAT(mr.department, ': ', COALESCE(mr.purpose, 'Material request')) as description,
                    NULL as amount,
                    NULL as payment_terms,
                    u.full_name as requested_by,
                    mr.created_at,
                    mr.status,
                    mr.priority
                FROM material_requisitions mr
                LEFT JOIN users u ON mr.requested_by = u.id
                WHERE mr.status = 'pending'
            ");
            $reqs = $stmt->fetchAll();
            $pending = array_merge($pending, $reqs);
            
            // Sort by priority and date
            usort($pending, function($a, $b) {
                $priorityOrder = ['urgent' => 0, 'high' => 1, 'normal' => 2, 'low' => 3];
                $aPri = $priorityOrder[$a['priority']] ?? 2;
                $bPri = $priorityOrder[$b['priority']] ?? 2;
                if ($aPri !== $bPri) return $aPri - $bPri;
                return strtotime($a['created_at']) - strtotime($b['created_at']);
            });
            
            Response::success($pending, 'All pending approvals retrieved');
            break;
            
        case 'pending_purchase_requests':
            Response::success([], 'PRS records route to Purchaser; GM approval happens on the Purchase Order');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Aggregate stats aligned with Admin Dashboard Action Center.
 * Categories: all, sales orders, disposals, procurement, production materials.
 */
function buildGmApprovalStats(PDO $db): array {
    $stats = [];

    // Procurement POs
    $stmt = $db->query("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total_amount
        FROM purchase_orders WHERE status = 'pending'
    ");
    $poStats = $stmt->fetch();
    $stats['pending_pos'] = [
        'count' => (int) $poStats['count'],
        'total_amount' => (float) $poStats['total_amount']
    ];
    $poCount = (int) $poStats['count'];

    // PRS records route to Purchaser. GM approval starts after a PO is submitted.
    $prCount = 0;
    $stats['pending_purchase_requests'] = 0;

    $stats['procurement'] = $poCount + $prCount;

    // Production material requisitions waiting for GM approval
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM material_requisitions WHERE status = 'pending'");
        $stats['production_materials'] = (int) $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['production_materials'] = 0;
    }

    // Disposals awaiting GM signature
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM disposals WHERE status = 'pending'");
        $stats['disposals'] = (int) $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['disposals'] = 0;
    }

    // Every pending Sales Order needs GM authorization. Credit overrides are the
    // higher-risk subset whose projected balance exceeds the customer's limit.
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM sales_orders WHERE status = 'pending'");
        $stats['sales_orders'] = (int) $stmt->fetch()['count'];
        $stmt = $db->query("
            SELECT COUNT(*) as count
            FROM sales_orders o
            LEFT JOIN customers c ON c.id = o.customer_id
            WHERE o.status = 'pending'
              AND LOWER(COALESCE(o.payment_type, '')) = 'credit'
              AND (
                  COALESCE((
                      SELECT SUM(dr.total_amount - dr.amount_paid)
                      FROM delivery_receipts dr
                      WHERE dr.customer_id = o.customer_id
                        AND dr.payment_status != 'paid'
                        AND dr.status NOT IN ('cancelled', 'draft')
                  ), 0) + COALESCE(o.total_amount, 0)
              ) > COALESCE(c.credit_limit, 0)
        ");
        $stats['credit_overrides'] = (int) $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['sales_orders'] = 0;
        $stats['credit_overrides'] = 0;
    }

    $stats['all_queues'] = (int)$stats['sales_orders']
        + (int)$stats['disposals']
        + (int)$stats['procurement']
        + (int)$stats['production_materials'];

    // Today's approvals
    try {
        $stmt = $db->query("
            SELECT COUNT(*) as count FROM purchase_orders
            WHERE status = 'approved' AND DATE(approved_at) = CURDATE()
        ");
        $stats['approved_today'] = (int) $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['approved_today'] = 0;
    }

    try {
        $stmt = $db->query("
            SELECT COALESCE(SUM(total_amount), 0) as total
            FROM purchase_orders
            WHERE status IN ('approved', 'ordered', 'received')
              AND YEAR(approved_at) = YEAR(CURDATE())
              AND MONTH(approved_at) = MONTH(CURDATE())
        ");
        $stats['monthly_approved_spending'] = (float) $stmt->fetch()['total'];
    } catch (Exception $e) {
        $stats['monthly_approved_spending'] = 0;
    }

    try {
        $stmt = $db->query("
            SELECT COUNT(*) as count FROM ingredient_price_history
            WHERE change_percent > 10
              AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stats['price_alerts'] = (int) $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['price_alerts'] = 0;
    }

    return $stats;
}

function handlePost($db, $action, $currentUser) {
    switch ($action) {
        case 'approve':
        case 'reject':
            processApprovalDecision($db, $action, $currentUser);
            break;
        case 'get_detail':
            fetchApprovalDetail($db);
            break;
        default:
            Response::error('Invalid POST action', 400);
    }
}

function fetchApprovalDetail(PDO $db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? '';
    $sourceId = (int)($input['source_id'] ?? 0);

    if (!$sourceId || !$type) {
        Response::error('Missing type or source_id', 400);
    }

    $detail = [];
    switch ($type) {
        case 'credit':
        case 'credit_override':
        case 'sales_order':
            $stmt = $db->prepare("
                SELECT o.*, COALESCE(c.name, o.customer_name) as customer_name,
                       c.credit_limit,
                       COALESCE((
                           SELECT SUM(dr.total_amount - dr.amount_paid)
                           FROM delivery_receipts dr
                           WHERE dr.customer_id = o.customer_id
                             AND dr.payment_status != 'paid'
                             AND dr.status NOT IN ('cancelled', 'draft')
                       ), 0) AS credit_balance,
                       c.customer_type
                FROM sales_orders o
                LEFT JOIN customers c ON c.id = o.customer_id
                WHERE o.id = ?
            ");
            $stmt->execute([$sourceId]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($detail) {
                $itemStmt = $db->prepare("
                    SELECT soi.*,
                           p.base_unit,
                           p.box_unit,
                           COALESCE(p.pieces_per_box, 1) AS pieces_per_box,
                           CASE
                               WHEN COALESCE(soi.quantity_boxes, 0) > 0 AND COALESCE(soi.quantity_pieces, 0) > 0
                                   THEN CONCAT(soi.quantity_boxes, ' ', COALESCE(p.box_unit, 'box'), ' + ',
                                               soi.quantity_pieces, ' ', COALESCE(p.base_unit, 'piece'),
                                               IF(soi.quantity_pieces = 1, '', 's'),
                                               ' (', soi.quantity_ordered, ' ', COALESCE(p.base_unit, 'piece'),
                                               IF(soi.quantity_ordered = 1, '', 's'), ')')
                               WHEN COALESCE(soi.quantity_boxes, 0) > 0
                                   THEN CONCAT(soi.quantity_boxes, ' ', COALESCE(p.box_unit, 'box'),
                                               IF(soi.quantity_boxes = 1, '', 's'),
                                               ' (', soi.quantity_ordered, ' ', COALESCE(p.base_unit, 'piece'),
                                               IF(soi.quantity_ordered = 1, '', 's'), ')')
                               ELSE CONCAT(soi.quantity_ordered, ' ', COALESCE(p.base_unit, soi.unit_type, 'piece'),
                                           IF(soi.quantity_ordered = 1, '', 's'))
                           END AS quantity_display
                    FROM sales_order_items soi
                    LEFT JOIN products p ON p.id = soi.product_id
                    WHERE soi.order_id = ?
                ");
                $itemStmt->execute([$sourceId]);
                $detail['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($detail['items'] as &$item) {
                    $boxes = (int)($item['quantity_boxes'] ?? 0);
                    $pieces = (int)($item['quantity_pieces'] ?? 0);
                    $total = (int)($item['quantity_ordered'] ?? 0);
                    $baseUnit = (string)($item['base_unit'] ?? $item['unit_type'] ?? 'piece');
                    $boxUnit = (string)($item['box_unit'] ?? 'box');
                    $baseLabel = approvalUnitLabel($baseUnit, $total);
                    $parts = [];

                    if ($boxes > 0) {
                        $parts[] = $boxes . ' ' . approvalUnitLabel($boxUnit, $boxes);
                    }
                    if ($pieces > 0) {
                        $parts[] = $pieces . ' ' . approvalUnitLabel($baseUnit, $pieces);
                    }

                    $item['quantity_display'] = $parts
                        ? implode(' + ', $parts) . " ({$total} {$baseLabel})"
                        : "{$total} {$baseLabel}";
                }
                unset($item);
            }
            break;

        case 'disposal':
            $stmt = $db->prepare("
                SELECT d.*, u.full_name as initiated_by_name,
                       COALESCE(
                           NULLIF(d.unit_cost, 0),
                           NULLIF(p.cost_price, 0),
                           NULLIF(p.unit_price, 0),
                           NULLIF(p.selling_price, 0),
                           0
                       ) AS display_unit_cost,
                       COALESCE(
                           NULLIF(d.total_value, 0),
                           d.quantity * COALESCE(
                               NULLIF(d.unit_cost, 0),
                               NULLIF(p.cost_price, 0),
                               NULLIF(p.unit_price, 0),
                               NULLIF(p.selling_price, 0)
                           ),
                           0
                       ) AS display_total_value,
                       CASE
                           WHEN d.notes LIKE '%catalog price%' THEN 1
                           WHEN COALESCE(NULLIF(d.unit_cost, 0), NULLIF(p.cost_price, 0), 0) > 0 THEN 0
                           WHEN COALESCE(p.unit_price, p.selling_price, 0) > 0 THEN 1
                           ELSE 0
                       END AS value_is_estimate
                FROM disposals d
                LEFT JOIN users u ON u.id = d.initiated_by
                LEFT JOIN products p ON p.id = d.product_id
                WHERE d.id = ?
            ");
            $stmt->execute([$sourceId]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($detail) {
                $detail['unit_cost'] = $detail['display_unit_cost'];
                $detail['total_value'] = $detail['display_total_value'];
            }
            break;

        case 'purchase_order':
            $stmt = $db->prepare("
                SELECT po.*, s.supplier_name, u.full_name as requested_by
                FROM purchase_orders po
                LEFT JOIN suppliers s ON s.id = po.supplier_id
                LEFT JOIN users u ON u.id = po.created_by
                WHERE po.id = ?
            ");
            $stmt->execute([$sourceId]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($detail) {
                $itemStmt = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id = ?");
                $itemStmt->execute([$sourceId]);
                $detail['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
                addProcurementQuantityDisplays($detail['items']);
            }
            break;

        case 'requisition':
            $stmt = $db->prepare("
                SELECT mr.*, u.full_name as requested_by_name
                FROM material_requisitions mr
                LEFT JOIN users u ON u.id = mr.requested_by
                WHERE mr.id = ?
            ");
            $stmt->execute([$sourceId]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($detail) {
                $itemStmt = $db->prepare("SELECT * FROM requisition_items WHERE requisition_id = ?");
                $itemStmt->execute([$sourceId]);
                $detail['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        default:
            Response::error('Unknown approval type', 400);
    }

    if (!$detail) {
        Response::error('Record not found', 404);
    }

    Response::success($detail);
}

function approvalCompactNumber($value, $decimals = 3) {
    return rtrim(rtrim(number_format((float) $value, $decimals, '.', ','), '0'), '.');
}

function addProcurementQuantityDisplays(&$items) {
    foreach ($items as &$item) {
        $warehouseQuantity = (float) ($item['quantity'] ?? 0);
        $warehouseUnit = trim((string) ($item['unit'] ?? 'unit')) ?: 'unit';
        $supplierQuantity = (float) ($item['supplier_order_quantity'] ?? 0);
        $supplierUnit = trim((string) ($item['supplier_order_unit'] ?? ''));
        $stockPerPackage = (float) ($item['stock_quantity_per_supplier_unit'] ?? 0);

        if ($supplierQuantity > 0 && $supplierUnit !== '' && $stockPerPackage > 0) {
            $coveredQuantity = $supplierQuantity * $stockPerPackage;
            $display = approvalCompactNumber($supplierQuantity) . ' ' . $supplierUnit
                . ' = ' . approvalCompactNumber($coveredQuantity) . ' ' . $warehouseUnit;
            $extra = $coveredQuantity - $warehouseQuantity;
            if ($extra > 0.0005) {
                $display .= ' (requested ' . approvalCompactNumber($warehouseQuantity) . ' ' . $warehouseUnit
                    . '; extra ' . approvalCompactNumber($extra) . ' from full packages)';
            }
            $item['quantity_display'] = $display;
            $item['price_display'] = 'PHP ' . number_format((float) ($item['supplier_order_unit_price'] ?? 0), 2)
                . ' / ' . $supplierUnit;
        } else {
            $item['quantity_display'] = approvalCompactNumber($warehouseQuantity) . ' ' . $warehouseUnit;
            $item['price_display'] = 'PHP ' . number_format((float) ($item['unit_price'] ?? 0), 2)
                . ' / ' . $warehouseUnit;
        }
    }
    unset($item);
}

function approvalUnitLabel(string $unit, int $quantity): string {
    if ($quantity === 1 || substr(strtolower($unit), -1) === 's') {
        return $unit;
    }
    if (strtolower($unit) === 'box') {
        return 'boxes';
    }
    return $unit . 's';
}

function processApprovalDecision(PDO $db, string $decision, $currentUser) {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? '';
    $sourceId = (int)($input['source_id'] ?? 0);
    $remarks = hfPlainText($input['remarks'] ?? '', 1000, true);

    if (!$sourceId || !$type) {
        Response::error('Missing type or source_id', 400);
    }

    $newStatus = ($decision === 'approve') ? 'approved' : 'rejected';
    $gmId = $currentUser['id'] ?? $currentUser['user_id'] ?? null;
    $now = date('Y-m-d H:i:s');

    $db->beginTransaction();
    try {
        switch ($type) {
            case 'credit':
            case 'credit_override':
            case 'sales_order':
                $orderCheck = $db->prepare("
                    SELECT COUNT(*)
                    FROM sales_order_items
                    WHERE order_id = ? AND quantity_ordered > 0
                ");
                $orderCheck->execute([$sourceId]);
                if ((int)$orderCheck->fetchColumn() === 0) {
                    throw new Exception('A Sales Order with no items cannot be approved.');
                }
                $stmt = $db->prepare("
                    UPDATE sales_orders
                    SET status = ?, approved_by = ?, approved_at = ?,
                        notes = CONCAT(COALESCE(notes, ''), '\n[GM ', ?, '] ', ?)
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$newStatus, $gmId, $now, ucfirst($decision), $remarks, $sourceId]);
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Order not found or already processed');
                }
                $historyStmt = $db->prepare("
                    INSERT INTO sales_order_status_history (order_id, status, notes, changed_by)
                    VALUES (?, ?, ?, ?)
                ");
                $historyStmt->execute([
                    $sourceId,
                    $newStatus,
                    $remarks !== '' ? $remarks : 'GM ' . $decision,
                    $gmId,
                ]);
                break;

            case 'disposal':
                $stmt = $db->prepare("
                    UPDATE disposals
                    SET status = ?, approved_by = ?, approved_at = ?, approval_notes = ?
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$newStatus, $gmId, $now, $remarks, $sourceId]);
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Disposal not found or already processed');
                }
                break;

            case 'purchase_order':
                // V4.1: PO approvals require the digital signature (password re-entry).
                // Frontends must call /purchasing/purchase_orders.php?action=approve which enforces it.
                if ($decision === 'approve') {
                    $poStepUp = $input['step_up_token'] ?? null;
                    Auth::requireStepUp($currentUser, 'po_approval', $poStepUp);
                }
                $stmt = $db->prepare("
                    UPDATE purchase_orders
                    SET status = ?, approved_by = ?, approved_at = ?,
                        notes = CONCAT(COALESCE(notes, ''), '\n[GM ', ?, '] ', ?)
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$newStatus, $gmId, $now, ucfirst($decision), $remarks, $sourceId]);
                if ($stmt->rowCount() === 0) {
                    throw new Exception('PO not found or already processed');
                }

                // V4.1: Notify warehouse + finance when GM approves a PO
                if ($newStatus === 'approved') {
                    $poStmt = $db->prepare("SELECT po_number, total_amount, payment_terms FROM purchase_orders WHERE id = ?");
                    $poStmt->execute([$sourceId]);
                    $poInfo = $poStmt->fetch(PDO::FETCH_ASSOC);
                    $poNum = $poInfo['po_number'] ?? ('#' . $sourceId);
                    $poAmt = number_format((float)($poInfo['total_amount'] ?? 0), 2);
                    $poTerms = $poInfo['payment_terms'] ?? 'N/A';

                    writeProcurementNotification($db, 'warehouse_raw', 'po_approved_pending_delivery', 'Approved PO pending delivery', "PO {$poNum} has been approved by GM and is ready for Warehouse receiving.", 'purchase_order', $sourceId);
                    writeProcurementNotification($db, 'finance_officer', 'po_approved_prepare_funds', 'PO approved - prepare funds', "PO {$poNum} ({$poAmt}) was approved by GM. Payment terms: {$poTerms}. Please prepare funds.", 'purchase_order', $sourceId);
                }
                break;

            case 'requisition':
                $stmt = $db->prepare("
                    UPDATE material_requisitions
                    SET status = ?, approved_by = ?, approved_at = ?,
                        notes = CONCAT(COALESCE(notes, ''), '\n[GM ', ?, '] ', ?)
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$newStatus, $gmId, $now, ucfirst($decision), $remarks, $sourceId]);
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Requisition not found or already processed');
                }
                break;

            default:
                throw new Exception('Unknown approval type: ' . $type);
        }

        // Audit log
        try {
            $auditStmt = $db->prepare("
                INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, created_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $tableName = $type === 'credit_override' ? 'sales_orders' :
                         ($type === 'purchase_order' ? 'purchase_orders' : $type . 's');
            $auditStmt->execute([
                $gmId,
                strtoupper($decision),
                $tableName,
                $sourceId,
                json_encode(['summary' => ucfirst($decision) . 'd ' . $type . ' #' . $sourceId . ($remarks ? " — $remarks" : ''), 'remarks' => $remarks]),
                $now,
            ]);
        } catch (Exception $e) { /* audit is best-effort */ }

        $db->commit();
        Response::success(['status' => $newStatus, 'source_id' => $sourceId, 'type' => $type], ucfirst($decision) . 'd successfully');

    } catch (Exception $e) {
        $db->rollBack();
        Response::error($e->getMessage(), 400);
    }
}

/**
 * Unified queue items — unique credit overrides + disposals + procurement.
 */
function buildGmUnifiedQueue(PDO $db): array {
    $items = [];

    // Credit overrides
    try {
        $stmt = $db->query("
            SELECT o.id, o.order_number, o.total_amount, o.created_at, o.notes, o.payment_type,
                   COALESCE(c.name, o.customer_name) as customer_name,
                   COALESCE(c.customer_type, o.customer_type) as customer_type,
                   COALESCE(c.credit_limit, 0) AS credit_limit,
                   COALESCE((
                       SELECT SUM(dr.total_amount - dr.amount_paid)
                       FROM delivery_receipts dr
                       WHERE dr.customer_id = o.customer_id
                         AND dr.payment_status != 'paid'
                         AND dr.status NOT IN ('cancelled', 'draft')
                   ), 0) AS current_balance
            FROM sales_orders o
            LEFT JOIN customers c ON c.id = o.customer_id
            WHERE o.status = 'pending'
            ORDER BY o.created_at DESC
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $on = $row['order_number'] ?: ('SO-' . $row['id']);
            $needsCreditOverride = strtolower((string)$row['payment_type']) === 'credit'
                && ((float)$row['current_balance'] + (float)$row['total_amount']) > (float)$row['credit_limit'];
            $items[] = [
                'id' => 'so-' . $row['id'],
                'source_id' => (int)$row['id'],
                'category' => 'credit',
                'type' => $needsCreditOverride ? 'credit_override' : 'sales_order',
                'priority' => $needsCreditOverride ? 'critical' : 'high',
                'reference' => $on,
                'title' => $needsCreditOverride
                    ? 'Order #' . $on . ' — Requires Credit Override'
                    : 'Order #' . $on . ' — Pending GM Approval',
                'detail' => ($row['customer_name'] ?: 'Customer') . ($needsCreditOverride
                    ? ' · Credit limit override required before fulfillment'
                    : ' · Sales order waiting for GM approval'),
                'amount' => (float)$row['total_amount'],
                'meta' => '₱' . number_format((float)$row['total_amount'], 2),
                'customer_name' => $row['customer_name'],
                'customer_type' => $row['customer_type'],
                'requested_at' => $row['created_at'],
                'href' => '../sales/orders.html?status=pending',
                'status' => 'pending',
            ];
        }
    } catch (Exception $e) { /* ignore */ }

    // Disposals
    try {
        $stmt = $db->query("
            SELECT d.id, d.disposal_code, d.product_name, d.total_value, d.disposal_reason,
                   d.quantity, d.unit, d.initiated_at, d.status, d.notes,
                   COALESCE(
                       NULLIF(d.total_value, 0),
                       d.quantity * COALESCE(
                           NULLIF(d.unit_cost, 0),
                           NULLIF(p.cost_price, 0),
                           NULLIF(p.unit_price, 0),
                           NULLIF(p.selling_price, 0)
                       ),
                       0
                   ) AS display_total_value,
                   CASE
                       WHEN d.notes LIKE '%catalog price%' THEN 1
                       WHEN COALESCE(NULLIF(d.unit_cost, 0), NULLIF(p.cost_price, 0), 0) > 0 THEN 0
                       WHEN COALESCE(p.unit_price, p.selling_price, 0) > 0 THEN 1
                       ELSE 0
                   END AS value_is_estimate
            FROM disposals d
            LEFT JOIN products p ON p.id = d.product_id
            WHERE d.status = 'pending'
            ORDER BY d.initiated_at ASC
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code = $row['disposal_code'] ?: ('DISP-' . $row['id']);
            $lossValue = (float)($row['display_total_value'] ?? 0);
            // Extract trailing number for display like #442
            $short = $code;
            if (preg_match('/(\d{3,})$/', $code, $m)) {
                $short = '#' . $m[1];
            }
            $items[] = [
                'id' => 'disp-' . $row['id'],
                'source_id' => (int)$row['id'],
                'category' => 'disposal',
                'type' => 'disposal',
                'priority' => 'high',
                'reference' => $code,
                'title' => 'Disposal Request ' . $short . ' — Pending Signature',
                'detail' => trim(($row['product_name'] ?? 'Inventory') . ' · ' . ($row['disposal_reason'] ?? 'Awaiting GM approval')),
                'amount' => $lossValue,
                'meta' => $lossValue > 0
                    ? (($row['value_is_estimate'] ?? false) ? 'Est. ' : '') . '₱' . number_format($lossValue, 2)
                    : 'Cost not recorded',
                'quantity' => $row['quantity'],
                'unit' => $row['unit'],
                'product_name' => $row['product_name'],
                'requested_at' => $row['initiated_at'],
                'href' => 'gm_approvals.html',
                'status' => 'pending',
            ];
        }
    } catch (Exception $e) { /* ignore */ }

    // Procurement POs
    try {
        $stmt = $db->query("
            SELECT po.id, po.po_number, po.total_amount, po.created_at, s.supplier_name
            FROM purchase_orders po
            LEFT JOIN suppliers s ON s.id = po.supplier_id
            WHERE po.status = 'pending'
            ORDER BY po.created_at ASC
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $po = $row['po_number'] ?: ('PO-' . $row['id']);
            $items[] = [
                'id' => 'po-' . $row['id'],
                'source_id' => (int)$row['id'],
                'category' => 'procurement',
                'type' => 'purchase_order',
                'priority' => 'high',
                'reference' => $po,
                'title' => 'Purchase Order #' . $po . ' — Awaiting Approval',
                'detail' => ($row['supplier_name'] ? $row['supplier_name'] . ' · ' : '') . 'Purchasing submission for GM sign-off',
                'amount' => (float)$row['total_amount'],
                'meta' => '₱' . number_format((float)$row['total_amount'], 2),
                'requested_at' => $row['created_at'],
                'href' => 'gm_approvals.html',
                'status' => 'pending',
            ];
        }
    } catch (Exception $e) { /* ignore */     }

    // Production material requisitions
    try {
        $stmt = $db->query("
            SELECT mr.id, mr.requisition_code, mr.purpose, mr.priority, mr.created_at,
                   mr.department, mr.planned_quantity, mr.planned_yield_unit,
                   pmr.product_name as planned_product_name,
                   pmr.variant as planned_variant,
                   u.full_name as requested_by_name,
                   (SELECT COUNT(*) FROM requisition_items ri WHERE ri.requisition_id = mr.id) as item_count
            FROM material_requisitions mr
            LEFT JOIN master_recipes pmr ON pmr.id = mr.planned_recipe_id
            LEFT JOIN users u ON u.id = mr.requested_by
            WHERE mr.status = 'pending'
            ORDER BY FIELD(mr.priority, 'urgent', 'high', 'normal', 'low'), mr.created_at ASC
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code = $row['requisition_code'] ?: ('REQ-' . $row['id']);
            $product = trim(($row['planned_product_name'] ?? '') . ($row['planned_variant'] ? ' (' . $row['planned_variant'] . ')' : ''));
            $qty = $row['planned_quantity'] ? trim($row['planned_quantity'] . ' ' . ($row['planned_yield_unit'] ?? '')) : '';
            $items[] = [
                'id' => 'req-' . $row['id'],
                'source_id' => (int)$row['id'],
                'category' => 'production',
                'type' => 'requisition',
                'priority' => in_array($row['priority'], ['urgent', 'high'], true) ? 'high' : 'medium',
                'reference' => $code,
                'title' => 'Production Requisition #' . $code . ' - Awaiting GM Approval',
                'detail' => trim(($product ?: ($row['purpose'] ?: 'Material request')) . ($qty ? ' - ' . $qty : '') . ' - ' . ($row['requested_by_name'] ?: 'Production')),
                'amount' => null,
                'meta' => ((int)($row['item_count'] ?? 0)) . ' item(s)',
                'requested_at' => $row['created_at'],
                'href' => 'gm_approvals.html',
                'status' => 'pending',
            ];
        }
    } catch (Exception $e) { /* ignore */ }

    $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
    usort($items, function ($a, $b) use ($rank) {
        $pa = $rank[$a['priority']] ?? 9;
        $pb = $rank[$b['priority']] ?? 9;
        if ($pa !== $pb) return $pa <=> $pb;
        return strcmp((string)($b['requested_at'] ?? ''), (string)($a['requested_at'] ?? ''));
    });

    return $items;
}
