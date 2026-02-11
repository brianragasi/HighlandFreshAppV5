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
            $stats = [];
            
            // Pending POs
            $stmt = $db->query("
                SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total_amount
                FROM purchase_orders WHERE status = 'pending'
            ");
            $poStats = $stmt->fetch();
            $stats['pending_pos'] = [
                'count' => (int) $poStats['count'],
                'total_amount' => (float) $poStats['total_amount']
            ];
            
            // Pending Requisitions
            $stmt = $db->query("
                SELECT COUNT(*) as count
                FROM material_requisitions WHERE status = 'pending'
            ");
            $stats['pending_requisitions'] = (int) $stmt->fetch()['count'];
            
            // Pending Disposals (if exists)
            try {
                $stmt = $db->query("
                    SELECT COUNT(*) as count
                    FROM disposals WHERE status = 'pending'
                ");
                $stats['pending_disposals'] = (int) $stmt->fetch()['count'];
            } catch (Exception $e) {
                $stats['pending_disposals'] = 0;
            }
            
            // Today's approvals
            $stmt = $db->query("
                SELECT COUNT(*) as count
                FROM purchase_orders 
                WHERE status = 'approved' 
                AND DATE(approved_at) = CURDATE()
            ");
            $stats['approved_today'] = (int) $stmt->fetch()['count'];
            
            // This month's approved spending
            $stmt = $db->query("
                SELECT COALESCE(SUM(total_amount), 0) as total
                FROM purchase_orders 
                WHERE status IN ('approved', 'ordered', 'received')
                AND YEAR(approved_at) = YEAR(CURDATE())
                AND MONTH(approved_at) = MONTH(CURDATE())
            ");
            $stats['monthly_approved_spending'] = (float) $stmt->fetch()['total'];
            
            // Price alerts (significant increases)
            $stmt = $db->query("
                SELECT COUNT(*) as count
                FROM ingredient_price_history
                WHERE change_percent > 10
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stats['price_alerts'] = (int) $stmt->fetch()['count'];
            
            Response::success($stats, 'Dashboard stats retrieved');
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
                    u.full_name as requested_by_name
                FROM material_requisitions mr
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
                    SELECT item_name, quantity, unit, notes
                    FROM requisition_items WHERE requisition_id = ?
                ");
                $itemsStmt->execute([$req['id']]);
                $req['items'] = $itemsStmt->fetchAll();
            }
            
            Response::success($requisitions, 'Pending requisitions retrieved');
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
            
        default:
            Response::error('Invalid action', 400);
    }
}
