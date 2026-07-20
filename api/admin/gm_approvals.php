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
            $stats['credit_overrides'] = count(array_filter($items, fn($i) => ($i['category'] ?? '') === 'credit'));
            $stats['disposals'] = count(array_filter($items, fn($i) => ($i['category'] ?? '') === 'disposal'));
            $stats['procurement'] = count(array_filter($items, fn($i) => ($i['category'] ?? '') === 'procurement'));
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
                    SELECT item_description, quantity, unit, unit_price, total_amount
                    FROM purchase_order_items WHERE po_id = ?
                ");
                $itemsStmt->execute([$order['id']]);
                $order['items'] = $itemsStmt->fetchAll();
            }
            
            Response::success($orders, 'Pending POs retrieved');
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
            
            // Pending Purchase Requests (Phase 1)
            try {
                $stmt = $db->query("
                    SELECT 
                        'purchase_request' as type,
                        pr.id,
                        pr.pr_number as reference,
                        CONCAT('PR: ', COALESCE(pr.purpose, 'Purchase request')) as description,
                        (SELECT COALESCE(SUM(estimated_total), 0) FROM purchase_request_items WHERE purchase_request_id = pr.id) as amount,
                        NULL as payment_terms,
                        u.full_name as requested_by,
                        pr.created_at,
                        pr.status,
                        pr.priority
                    FROM purchase_requests pr
                    LEFT JOIN users u ON pr.requested_by = u.id
                    WHERE pr.status = 'pending'
                ");
                $prs = $stmt->fetchAll();
                $pending = array_merge($pending, $prs);
            } catch (Exception $e) {
                // purchase_requests table may not exist yet
            }
            
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
            // Phase 1: pending PRs from Warehouse Raw
            try {
                $stmt = $db->query("
                    SELECT 
                        pr.*,
                        u.full_name as requested_by_name,
                        (SELECT COUNT(*) FROM purchase_request_items WHERE purchase_request_id = pr.id) as item_count,
                        (SELECT COALESCE(SUM(estimated_total), 0) FROM purchase_request_items WHERE purchase_request_id = pr.id) as estimated_total
                    FROM purchase_requests pr
                    LEFT JOIN users u ON pr.requested_by = u.id
                    WHERE pr.status = 'pending'
                    ORDER BY 
                        FIELD(pr.priority, 'urgent', 'high', 'normal', 'low'),
                        pr.created_at ASC
                ");
                $prs = $stmt->fetchAll();

                foreach ($prs as &$pr) {
                    $itemsStmt = $db->prepare("SELECT * FROM purchase_request_items WHERE purchase_request_id = ?");
                    $itemsStmt->execute([$pr['id']]);
                    $pr['items'] = $itemsStmt->fetchAll();
                }

                Response::success($prs, 'Pending purchase requests retrieved');
            } catch (Exception $e) {
                Response::success([], 'No purchase requests table yet');
            }
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Aggregate stats aligned with Admin Dashboard Action Center.
 * Categories: all, credit_overrides, disposals, procurement.
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
    $stats['procurement'] = (int) $poStats['count'];

    // Requisitions (legacy)
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM material_requisitions WHERE status = 'pending'");
        $stats['pending_requisitions'] = (int) $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['pending_requisitions'] = 0;
    }

    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM purchase_requests WHERE status = 'pending'");
        $stats['pending_purchase_requests'] = (int) $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['pending_purchase_requests'] = 0;
    }

    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM item_requests WHERE status = 'pending'");
        $stats['pending_item_requests'] = (int) $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['pending_item_requests'] = 0;
    }

    // Disposals
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM disposals WHERE status = 'pending'");
        $stats['pending_disposals'] = (int) $stmt->fetch()['count'];
        $stats['disposals'] = $stats['pending_disposals'];
    } catch (Exception $e) {
        $stats['pending_disposals'] = 0;
        $stats['disposals'] = 0;
    }

    // Credit overrides = pending sales orders explicitly flagged for GM credit authorization
    try {
        $stmt = $db->query("
            SELECT COUNT(*) as count
            FROM sales_orders
            WHERE status = 'pending'
              AND (
                notes LIKE '%CREDIT%OVERRIDE%'
                OR notes LIKE '%credit override%'
                OR notes LIKE '%GM-CREDIT%'
              )
        ");
        $stats['credit_overrides'] = (int) $stmt->fetch()['count'];
        // Fallback: any pending sales order still counts as credit override work for GM
        if ($stats['credit_overrides'] === 0) {
            $stmt = $db->query("SELECT COUNT(*) as count FROM sales_orders WHERE status = 'pending'");
            $stats['credit_overrides'] = (int) $stmt->fetch()['count'];
        }
    } catch (Exception $e) {
        $stats['credit_overrides'] = 0;
    }

    $stats['all_queues'] = (int)$stats['credit_overrides']
        + (int)$stats['disposals']
        + (int)$stats['procurement'];

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
                       c.credit_limit, c.current_balance as credit_balance, c.customer_type
                FROM sales_orders o
                LEFT JOIN customers c ON c.id = o.customer_id
                WHERE o.id = ?
            ");
            $stmt->execute([$sourceId]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($detail) {
                $itemStmt = $db->prepare("SELECT * FROM sales_order_items WHERE order_id = ?");
                $itemStmt->execute([$sourceId]);
                $detail['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'disposal':
            $stmt = $db->prepare("
                SELECT d.*, u.full_name as initiated_by_name
                FROM disposals d
                LEFT JOIN users u ON u.id = d.initiated_by
                WHERE d.id = ?
            ");
            $stmt->execute([$sourceId]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
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

function processApprovalDecision(PDO $db, string $decision, $currentUser) {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? '';
    $sourceId = (int)($input['source_id'] ?? 0);
    $remarks = trim($input['remarks'] ?? '');

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
            $auditStmt->execute([
                $gmId,
                strtoupper($decision),
                $type === 'credit_override' ? 'sales_orders' : ($type === 'purchase_order' ? 'purchase_orders' : $type . 's'),
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
                   COALESCE(c.customer_type, o.customer_type) as customer_type
            FROM sales_orders o
            LEFT JOIN customers c ON c.id = o.customer_id
            WHERE o.status = 'pending'
            ORDER BY o.created_at DESC
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $on = $row['order_number'] ?: ('SO-' . $row['id']);
            $items[] = [
                'id' => 'so-' . $row['id'],
                'source_id' => (int)$row['id'],
                'category' => 'credit',
                'type' => 'credit_override',
                'priority' => 'critical',
                'reference' => $on,
                'title' => 'Order #' . $on . ' — Requires Credit Override',
                'detail' => ($row['customer_name'] ?: 'Customer') . ' · Credit authorization required before fulfillment',
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
            SELECT id, disposal_code, product_name, total_value, disposal_reason,
                   quantity, unit, initiated_at, status
            FROM disposals
            WHERE status = 'pending'
            ORDER BY initiated_at ASC
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code = $row['disposal_code'] ?: ('DISP-' . $row['id']);
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
                'amount' => (float)($row['total_value'] ?? 0),
                'meta' => $row['total_value'] !== null ? '₱' . number_format((float)$row['total_value'], 2) : null,
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

    $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
    usort($items, function ($a, $b) use ($rank) {
        $pa = $rank[$a['priority']] ?? 9;
        $pb = $rank[$b['priority']] ?? 9;
        if ($pa !== $pb) return $pa <=> $pb;
        return strcmp((string)($b['requested_at'] ?? ''), (string)($a['requested_at'] ?? ''));
    });

    return $items;
}
