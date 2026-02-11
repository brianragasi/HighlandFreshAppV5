<?php
/**
 * Highland Fresh System - Purchase Orders API
 * 
 * GET - List POs, get details, get items
 * POST - Create PO, add items
 * PUT - Update PO, update status, approve
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Purchaser or GM role
$currentUser = Auth::requireRole(['purchaser', 'general_manager']);

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
        case 'PUT':
            handlePut($db, $action, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Purchase Orders API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            $status = getParam('status');
            $supplier_id = getParam('supplier_id');
            $payment_status = getParam('payment_status');
            $search = getParam('search');
            $date_from = getParam('date_from');
            $date_to = getParam('date_to');
            $page = max(1, (int) getParam('page', 1));
            $limit = min(50, max(10, (int) getParam('limit', 20)));
            $offset = ($page - 1) * $limit;
            
            $where = "1=1";
            $params = [];
            
            if ($status) {
                $where .= " AND po.status = ?";
                $params[] = $status;
            }
            
            if ($supplier_id) {
                $where .= " AND po.supplier_id = ?";
                $params[] = $supplier_id;
            }
            
            if ($payment_status) {
                $where .= " AND po.payment_status = ?";
                $params[] = $payment_status;
            }
            
            if ($search) {
                $where .= " AND (po.po_number LIKE ? OR s.supplier_name LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if ($date_from) {
                $where .= " AND po.order_date >= ?";
                $params[] = $date_from;
            }
            
            if ($date_to) {
                $where .= " AND po.order_date <= ?";
                $params[] = $date_to;
            }
            
            // Get total count
            $countStmt = $db->prepare("
                SELECT COUNT(*) as total 
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                WHERE $where
            ");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetch()['total'];
            
            // Get paginated results
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $db->prepare("
                SELECT 
                    po.*,
                    s.supplier_name,
                    s.supplier_code,
                    u.full_name as created_by_name,
                    ua.full_name as approved_by_name,
                    mr.requisition_code,
                    (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = po.id) as item_count,
                    (SELECT SUM(quantity_received) FROM purchase_order_items WHERE po_id = po.id) as total_received
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                LEFT JOIN users u ON po.created_by = u.id
                LEFT JOIN users ua ON po.approved_by = ua.id
                LEFT JOIN material_requisitions mr ON po.requisition_id = mr.id
                WHERE $where
                ORDER BY po.order_date DESC, po.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute($params);
            $orders = $stmt->fetchAll();
            
            Response::paginated($orders, $total, $page, $limit, 'Purchase orders retrieved');
            break;
            
        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('PO ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT 
                    po.*,
                    s.supplier_name,
                    s.supplier_code,
                    s.contact_person as supplier_contact,
                    s.phone as supplier_phone,
                    s.payment_terms as supplier_terms,
                    u.full_name as created_by_name,
                    ua.full_name as approved_by_name
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                LEFT JOIN users u ON po.created_by = u.id
                LEFT JOIN users ua ON po.approved_by = ua.id
                WHERE po.id = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch();
            
            if (!$order) {
                Response::error('Purchase order not found', 404);
            }
            
            // Get items
            $itemsStmt = $db->prepare("
                SELECT 
                    poi.*,
                    i.ingredient_name,
                    m.item_name as mro_item_name
                FROM purchase_order_items poi
                LEFT JOIN ingredients i ON poi.ingredient_id = i.id
                LEFT JOIN mro_items m ON poi.mro_item_id = m.id
                WHERE poi.po_id = ?
                ORDER BY poi.id ASC
            ");
            $itemsStmt->execute([$id]);
            $order['items'] = $itemsStmt->fetchAll();
            
            Response::success($order, 'Purchase order details retrieved');
            break;
            
        case 'next_number':
            // Generate next PO number
            $stmt = $db->query("
                SELECT po_number FROM purchase_orders 
                ORDER BY id DESC LIMIT 1
            ");
            $last = $stmt->fetch();
            
            if ($last) {
                $lastNum = (int) $last['po_number'];
                $nextNum = $lastNum + 1;
            } else {
                $nextNum = 5252;
            }
            
            Response::success(['next_number' => (string) $nextNum], 'Next PO number');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

function handlePost($db, $action, $currentUser) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'create':
            $required = ['supplier_id', 'items'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::error("$field is required", 400);
                }
            }
            
            if (!is_array($data['items']) || count($data['items']) === 0) {
                Response::error('At least one item is required', 400);
            }
            
            // Verify supplier exists
            $supplierCheck = $db->prepare("SELECT id, supplier_name FROM suppliers WHERE id = ? AND is_active = 1");
            $supplierCheck->execute([$data['supplier_id']]);
            if (!$supplierCheck->fetch()) {
                Response::error('Invalid or inactive supplier', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Generate PO number
                $stmt = $db->query("SELECT po_number FROM purchase_orders ORDER BY id DESC LIMIT 1");
                $last = $stmt->fetch();
                $poNumber = $last ? (string)((int)$last['po_number'] + 1) : '5252';
                
                // Calculate totals
                $subtotal = 0;
                $vatAmount = 0;
                foreach ($data['items'] as $item) {
                    $lineTotal = (float) $item['quantity'] * (float) $item['unit_price'];
                    $subtotal += $lineTotal;
                    if (!empty($item['is_vat_item'])) {
                        $vatAmount += $lineTotal;
                    }
                }
                $totalAmount = $subtotal;
                
                // Create PO
                // Calculate due date based on payment terms
                $paymentTerms = $data['payment_terms'] ?? 'cash';
                $orderDate = $data['order_date'] ?? date('Y-m-d');
                $dueDate = null;
                if ($paymentTerms !== 'cash') {
                    $days = (int) str_replace('credit_', '', $paymentTerms);
                    $dueDate = date('Y-m-d', strtotime($orderDate . " + $days days"));
                }
                
                $stmt = $db->prepare("
                    INSERT INTO purchase_orders 
                    (po_number, supplier_id, order_date, expected_delivery, status, 
                     subtotal, vat_amount, total_amount, payment_status, payment_terms, due_date, notes, requisition_id, created_by)
                    VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, 'unpaid', ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $poNumber,
                    $data['supplier_id'],
                    $orderDate,
                    $data['expected_delivery'] ?? null,
                    $subtotal,
                    $vatAmount,
                    $totalAmount,
                    $paymentTerms,
                    $dueDate,
                    $data['notes'] ?? null,
                    $data['requisition_id'] ?? null,
                    $currentUser['user_id']
                ]);
                
                $poId = $db->lastInsertId();
                
                // Insert items
                $itemStmt = $db->prepare("
                    INSERT INTO purchase_order_items 
                    (po_id, ingredient_id, mro_item_id, item_description, quantity, unit, 
                     unit_price, total_amount, is_vat_item, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($data['items'] as $item) {
                    $lineTotal = (float) $item['quantity'] * (float) $item['unit_price'];
                    $itemStmt->execute([
                        $poId,
                        $item['ingredient_id'] ?? null,
                        $item['mro_item_id'] ?? null,
                        $item['item_description'],
                        $item['quantity'],
                        $item['unit'],
                        $item['unit_price'],
                        $lineTotal,
                        $item['is_vat_item'] ?? 0,
                        $item['notes'] ?? null
                    ]);
                }
                
                $db->commit();
                
                // If linked to a requisition, update requisition status
                if (!empty($data['requisition_id'])) {
                    $db->prepare("UPDATE material_requisitions SET status = 'partial' WHERE id = ? AND status IN ('pending', 'approved')")
                       ->execute([$data['requisition_id']]);
                }
                
                logAudit($currentUser['user_id'], 'CREATE', 'purchase_orders', $poId, null, [
                    'po_number' => $poNumber,
                    'supplier_id' => $data['supplier_id'],
                    'total_amount' => $totalAmount,
                    'payment_terms' => $paymentTerms,
                    'items_count' => count($data['items'])
                ]);
                
                Response::success([
                    'id' => $poId, 
                    'po_number' => $poNumber,
                    'total_amount' => $totalAmount,
                    'payment_terms' => $paymentTerms,
                    'due_date' => $dueDate
                ], 'Purchase order created', 201);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

function handlePut($db, $action, $currentUser) {
    $data = getRequestBody();
    $id = getParam('id') ?? ($data['id'] ?? null);
    
    if (!$id) {
        Response::error('PO ID required', 400);
    }
    
    // Get current PO
    $check = $db->prepare("SELECT * FROM purchase_orders WHERE id = ?");
    $check->execute([$id]);
    $current = $check->fetch();
    
    if (!$current) {
        Response::error('Purchase order not found', 404);
    }
    
    switch ($action) {
        case 'submit':
            // Submit for approval (draft -> pending)
            if ($current['status'] !== 'draft') {
                Response::error('Only draft POs can be submitted', 400);
            }
            
            $stmt = $db->prepare("UPDATE purchase_orders SET status = 'pending', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            logAudit($currentUser['user_id'], 'UPDATE', 'purchase_orders', $id, 
                ['status' => 'draft'], ['status' => 'pending']);
            
            Response::success(null, 'Purchase order submitted for approval');
            break;
            
        case 'approve':
            // Only GM can approve
            if ($currentUser['role'] !== 'general_manager') {
                Response::error('Only the General Manager can approve purchase orders', 403);
            }
            
            if ($current['status'] !== 'pending') {
                Response::error('Only pending POs can be approved', 400);
            }
            
            $stmt = $db->prepare("
                UPDATE purchase_orders 
                SET status = 'approved', 
                    approved_by = ?,
                    approved_at = NOW(),
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$currentUser['user_id'], $id]);
            
            logAudit($currentUser['user_id'], 'APPROVE', 'purchase_orders', $id, 
                ['status' => 'pending'], ['status' => 'approved']);
            
            Response::success(null, 'Purchase order approved');
            break;
            
        case 'reject':
            if ($currentUser['role'] !== 'general_manager') {
                Response::error('Only the General Manager can reject purchase orders', 403);
            }
            
            if ($current['status'] !== 'pending') {
                Response::error('Only pending POs can be rejected', 400);
            }
            
            $stmt = $db->prepare("
                UPDATE purchase_orders 
                SET status = 'cancelled', 
                    notes = CONCAT(COALESCE(notes, ''), '\n[REJECTED: ', ?, ']'),
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$data['reason'] ?? 'No reason provided', $id]);
            
            logAudit($currentUser['user_id'], 'REJECT', 'purchase_orders', $id, 
                ['status' => 'pending'], ['status' => 'cancelled', 'reason' => $data['reason'] ?? '']);
            
            Response::success(null, 'Purchase order rejected');
            break;
            
        case 'mark_ordered':
            if (!in_array($current['status'], ['approved'])) {
                Response::error('Only approved POs can be marked as ordered', 400);
            }
            
            $stmt = $db->prepare("UPDATE purchase_orders SET status = 'ordered', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            logAudit($currentUser['user_id'], 'UPDATE', 'purchase_orders', $id, 
                ['status' => $current['status']], ['status' => 'ordered']);
            
            Response::success(null, 'PO marked as ordered');
            break;
            
        case 'mark_received':
            if (!in_array($current['status'], ['ordered', 'partial_received', 'approved'])) {
                Response::error('This PO cannot be marked as received', 400);
            }
            
            $stmt = $db->prepare("
                UPDATE purchase_orders 
                SET status = 'received', 
                    received_at = NOW(),
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            
            logAudit($currentUser['user_id'], 'UPDATE', 'purchase_orders', $id, 
                ['status' => $current['status']], ['status' => 'received']);
            
            Response::success(null, 'PO marked as received');
            break;
            
        case 'cancel':
            if (in_array($current['status'], ['received', 'cancelled'])) {
                Response::error('This PO cannot be cancelled', 400);
            }
            
            $stmt = $db->prepare("
                UPDATE purchase_orders 
                SET status = 'cancelled',
                    notes = CONCAT(COALESCE(notes, ''), '\n[CANCELLED: ', ?, ']'),
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$data['reason'] ?? 'No reason provided', $id]);
            
            logAudit($currentUser['user_id'], 'CANCEL', 'purchase_orders', $id, 
                ['status' => $current['status']], ['status' => 'cancelled']);
            
            Response::success(null, 'Purchase order cancelled');
            break;
            
        case 'update_payment':
            $newPaymentStatus = $data['payment_status'] ?? null;
            if (!in_array($newPaymentStatus, ['unpaid', 'partial', 'paid'])) {
                Response::error('Invalid payment status', 400);
            }
            
            $stmt = $db->prepare("
                UPDATE purchase_orders 
                SET payment_status = ?,
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$newPaymentStatus, $id]);
            
            logAudit($currentUser['user_id'], 'UPDATE', 'purchase_orders', $id, 
                ['payment_status' => $current['payment_status']], 
                ['payment_status' => $newPaymentStatus]);
            
            Response::success(null, 'Payment status updated');
            break;
        
        case 'receive_with_prices':
            // Enhanced receiving that allows price updates
            if (!in_array($current['status'], ['ordered', 'partial_received', 'approved'])) {
                Response::error('This PO cannot be marked as received', 400);
            }
            
            $db->beginTransaction();
            try {
                // Update PO status
                $stmt = $db->prepare("
                    UPDATE purchase_orders 
                    SET status = 'received', 
                        received_at = NOW(),
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$id]);
                
                // Process price updates if provided
                if (!empty($data['price_updates']) && is_array($data['price_updates'])) {
                    foreach ($data['price_updates'] as $update) {
                        if (empty($update['item_id']) || empty($update['new_price'])) continue;
                        
                        $itemType = $update['item_type'] ?? 'ingredient';
                        $newPrice = (float) $update['new_price'];
                        
                        if ($itemType === 'ingredient') {
                            // Get current price
                            $priceCheck = $db->prepare("SELECT unit_cost FROM ingredients WHERE id = ?");
                            $priceCheck->execute([$update['item_id']]);
                            $oldPrice = (float) $priceCheck->fetchColumn();
                            
                            if ($oldPrice != $newPrice) {
                                // Log price history
                                $db->prepare("
                                    INSERT INTO ingredient_price_history 
                                    (ingredient_id, old_price, new_price, po_id, supplier_id, reason, updated_by)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)
                                ")->execute([
                                    $update['item_id'], $oldPrice, $newPrice, $id, 
                                    $current['supplier_id'], $update['reason'] ?? 'Price update on receiving',
                                    $currentUser['user_id']
                                ]);
                                
                                // Update current price
                                $db->prepare("
                                    UPDATE ingredients 
                                    SET unit_cost = ?, market_price = ?, last_price_update = CURDATE()
                                    WHERE id = ?
                                ")->execute([$newPrice, $newPrice, $update['item_id']]);
                            }
                        } else if ($itemType === 'mro') {
                            // Get current price for MRO
                            $priceCheck = $db->prepare("SELECT unit_cost FROM mro_items WHERE id = ?");
                            $priceCheck->execute([$update['item_id']]);
                            $oldPrice = (float) $priceCheck->fetchColumn();
                            
                            if ($oldPrice != $newPrice) {
                                // Log price history
                                $db->prepare("
                                    INSERT INTO mro_price_history 
                                    (mro_item_id, old_price, new_price, po_id, supplier_id, reason, updated_by)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)
                                ")->execute([
                                    $update['item_id'], $oldPrice, $newPrice, $id,
                                    $current['supplier_id'], $update['reason'] ?? 'Price update on receiving',
                                    $currentUser['user_id']
                                ]);
                                
                                // Update current price
                                $db->prepare("
                                    UPDATE mro_items 
                                    SET unit_cost = ?, market_price = ?, last_price_update = CURDATE()
                                    WHERE id = ?
                                ")->execute([$newPrice, $newPrice, $update['item_id']]);
                            }
                        }
                    }
                }
                
                $db->commit();
                
                logAudit($currentUser['user_id'], 'RECEIVE', 'purchase_orders', $id, 
                    ['status' => $current['status']], 
                    ['status' => 'received', 'price_updates' => count($data['price_updates'] ?? [])]);
                
                Response::success(null, 'PO received and prices updated');
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
