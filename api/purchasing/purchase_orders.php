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
$currentUser = Auth::requireRole(['purchaser', 'general_manager', 'warehouse_raw']);

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
                    pr.pr_number,
                    pr.purpose as pr_purpose,
                    (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = po.id) as item_count,
                    (SELECT SUM(quantity_received) FROM purchase_order_items WHERE po_id = po.id) as total_received
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                LEFT JOIN users u ON po.created_by = u.id
                LEFT JOIN users ua ON po.approved_by = ua.id
                LEFT JOIN material_requisitions mr ON po.requisition_id = mr.id
                LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
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
                    ua.full_name as approved_by_name,
                    pr.pr_number,
                    pr.purpose as pr_purpose
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                LEFT JOIN users u ON po.created_by = u.id
                LEFT JOIN users ua ON po.approved_by = ua.id
                LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
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
            // ===== PURCHASER ONLY — Phase 1 Enforcement =====
            requireActionRole($currentUser, ['purchaser'], 'Only the Purchaser can create Purchase Orders. GM should approve, not create.');

            $required = ['supplier_id', 'items'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::error("$field is required", 400);
                }
            }

            if (!is_array($data['items']) || count($data['items']) === 0) {
                Response::error('At least one item is required', 400);
            }

            // ===== REQUIRE APPROVED PURCHASE REQUEST — Phase 1 Enforcement =====
            $purchaseRequestId = $data['purchase_request_id'] ?? null;
            if (empty($purchaseRequestId)) {
                Response::error('A Purchase Order must be linked to an approved Purchase Request (PR). Please select a PR first.', 400);
            }

            // Verify the PR exists and is approved
            $prCheck = $db->prepare("SELECT id, pr_number, status FROM purchase_requests WHERE id = ?");
            $prCheck->execute([$purchaseRequestId]);
            $prData = $prCheck->fetch();

            if (!$prData) {
                Response::error('Purchase Request not found', 404);
            }

            if ($prData['status'] !== 'approved') {
                Response::error('Cannot create PO: Purchase Request ' . $prData['pr_number'] . ' is not approved (current status: ' . $prData['status'] . ')', 400);
            }

            // Check if this PR already has an active PO
            $existingPO = $db->prepare("SELECT id, po_number FROM purchase_orders WHERE purchase_request_id = ? AND status NOT IN ('cancelled')");
            $existingPO->execute([$purchaseRequestId]);
            $existingPOData = $existingPO->fetch();
            if ($existingPOData) {
                Response::error('Purchase Request ' . $prData['pr_number'] . ' already has an active PO: ' . $existingPOData['po_number'], 400);
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

                // Calculate due date based on payment terms
                $paymentTerms = $data['payment_terms'] ?? 'cash';
                $orderDate = $data['order_date'] ?? date('Y-m-d');
                $dueDate = null;
                if ($paymentTerms !== 'cash') {
                    $days = (int) str_replace('credit_', '', $paymentTerms);
                    $dueDate = date('Y-m-d', strtotime($orderDate . " + $days days"));
                }

                // Create PO with purchase_request_id link
                $stmt = $db->prepare("
                    INSERT INTO purchase_orders
                    (po_number, supplier_id, order_date, expected_delivery, status,
                     subtotal, vat_amount, total_amount, payment_status, payment_terms, due_date,
                     notes, requisition_id, purchase_request_id, created_by)
                    VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, 'unpaid', ?, ?, ?, ?, ?, ?)
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
                    $purchaseRequestId,
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

                logAudit($currentUser['user_id'], 'CREATE', 'purchase_orders', $poId, null, [
                    'po_number' => $poNumber,
                    'supplier_id' => $data['supplier_id'],
                    'purchase_request_id' => $purchaseRequestId,
                    'pr_number' => $prData['pr_number'],
                    'total_amount' => $totalAmount,
                    'payment_terms' => $paymentTerms,
                    'items_count' => count($data['items'])
                ]);

                Response::success([
                    'id' => $poId,
                    'po_number' => $poNumber,
                    'purchase_request_id' => $purchaseRequestId,
                    'pr_number' => $prData['pr_number'],
                    'total_amount' => $totalAmount,
                    'payment_terms' => $paymentTerms,
                    'due_date' => $dueDate
                ], 'Purchase order created from PR ' . $prData['pr_number'], 201);

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
            requireActionRole($currentUser, ['purchaser'], 'Only the Purchaser can submit purchase orders for approval');

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
            requireActionRole($currentUser, ['general_manager'], 'Access forbidden');

            // Only GM can approve
            if ($currentUser['role'] !== 'general_manager') {
                Response::error('Only the General Manager can approve purchase orders', 403);
            }

            $stepUpToken = $data['step_up_token'] ?? getParam('step_up_token');
            Auth::requireStepUp($currentUser, 'po_approval', $stepUpToken);

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
                ['status' => 'pending'],
                ['status' => 'approved', 'approved_by' => $currentUser['user_id'], 'step_up_verified' => true]
            );

            Response::success(null, 'Purchase order approved');
            break;

        case 'reject':
            requireActionRole($currentUser, ['general_manager'], 'Access forbidden');

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
            requireActionRole($currentUser, ['purchaser'], 'Only the Purchaser can mark purchase orders as ordered');

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
            requireActionRole($currentUser, ['warehouse_raw'], 'Only Warehouse Raw can receive purchase orders');

            if (!in_array($current['status'], ['ordered', 'partial_received', 'approved'])) {
                Response::error('This PO cannot be marked as received', 400);
            }

            $db->beginTransaction();
            try {
                $stmt = $db->prepare("
                    UPDATE purchase_orders
                    SET status = 'received',
                        received_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$id]);

                // Auto stock-in all PO items to warehouse inventory
                $stockInResults = stockInPOItems($db, $id, $current['supplier_id'], $currentUser);

                $db->commit();

                logAudit($currentUser['user_id'], 'RECEIVE', 'purchase_orders', $id,
                    ['status' => $current['status']],
                    ['status' => 'received', 'stocked_in_items' => count($stockInResults)]);

                Response::success([
                    'stocked_in' => $stockInResults
                ], 'PO received and items stocked in to warehouse');
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        case 'cancel':
            requireActionRole($currentUser, ['purchaser', 'general_manager'], 'Only Purchaser or General Manager can cancel purchase orders');

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
            requireActionRole($currentUser, ['finance_officer', 'bookkeeper'], 'Only Finance or Bookkeeper can update payment status');

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
            requireActionRole($currentUser, ['warehouse_raw'], 'Only Warehouse Raw can receive purchase orders');

            // Enhanced receiving: per-item accept/reject + price updates + auto stock-in
            if (!in_array($current['status'], ['ordered', 'partial_received', 'approved'])) {
                Response::error('This PO cannot be marked as received', 400);
            }

            $db->beginTransaction();
            try {
                // Get receiving_items from request body: [{item_id, accepted, rejected, rejection_reason, rejection_category, new_price, lot_number, expiry_date, condition}]
                $receivingItems = $data['receiving_items'] ?? [];
                $receivingMeta = $data['receiving_meta'] ?? [];

                if (empty($receivingItems)) {
                    // Fallback: old behavior (accept all, just process price updates)
                    $stmt = $db->prepare("
                        UPDATE purchase_orders
                        SET status = 'received',
                            received_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$id]);

                    // Process legacy price updates
                    if (!empty($data['price_updates']) && is_array($data['price_updates'])) {
                        foreach ($data['price_updates'] as $update) {
                            processItemPriceUpdate($db, $update, $id, $current['supplier_id'], $currentUser);
                        }
                    }

                    $stockInResults = stockInPOItems($db, $id, $current['supplier_id'], $currentUser);

                    $db->commit();
                    logAudit($currentUser['user_id'], 'RECEIVE', 'purchase_orders', $id,
                        ['status' => $current['status']],
                        ['status' => 'received', 'mode' => 'legacy_full_accept']);

                    Response::success(['stocked_in' => $stockInResults], 'PO received (all items accepted)');
                }

                // --- New per-item receiving logic ---
                $totalAccepted = 0;
                $totalRejected = 0;
                $stockInResults = [];
                $allFullyProcessed = true;

                // Get all PO items
                $poItemsStmt = $db->prepare("
                    SELECT poi.*, po.po_number, po.order_date
                    FROM purchase_order_items poi
                    JOIN purchase_orders po ON poi.po_id = po.id
                    WHERE poi.po_id = ?
                ");
                $poItemsStmt->execute([$id]);
                $allPOItems = $poItemsStmt->fetchAll();

                // Index receiving data by item_id
                $receivingMap = [];
                foreach ($receivingItems as $ri) {
                    $receivingMap[$ri['item_id']] = $ri;
                }

                foreach ($allPOItems as $poItem) {
                    $itemData = $receivingMap[$poItem['id']] ?? null;
                    if (!$itemData) {
                        // Item not in this receiving batch — check if already fully received
                        $prevReceived = (float)$poItem['quantity_received'] + (float)$poItem['quantity_rejected'];
                        if ($prevReceived < (float)$poItem['quantity']) {
                            $allFullyProcessed = false;
                        }
                        continue;
                    }

                    $accepted = (float)($itemData['accepted'] ?? 0);
                    $rejected = (float)($itemData['rejected'] ?? 0);
                    $rejectionReason = $itemData['rejection_reason'] ?? null;
                    $rejectionCategory = $itemData['rejection_category'] ?? null;
                    $receivingNotes = buildReceivingNotes($receivingMeta, $itemData);

                    // Validate quantities
                    $orderedQty = (float)$poItem['quantity'];
                    $prevAccepted = (float)$poItem['quantity_received'];
                    $prevRejected = (float)$poItem['quantity_rejected'];
                    $remaining = $orderedQty - $prevAccepted - $prevRejected;

                    if (($accepted + $rejected) > $remaining + 0.001) {
                        throw new Exception("Item '{$poItem['item_description']}': accepted ({$accepted}) + rejected ({$rejected}) exceeds remaining ({$remaining})");
                    }

                    // Update PO item
                    $db->prepare("
                        UPDATE purchase_order_items
                        SET quantity_received = quantity_received + ?,
                            quantity_rejected = quantity_rejected + ?,
                            rejection_reason = CASE WHEN ? > 0 THEN ? ELSE rejection_reason END
                        WHERE id = ?
                    ")->execute([$accepted, $rejected, $rejected, $rejectionReason, $poItem['id']]);

                    // Insert receiving log
                    $db->prepare("
                        INSERT INTO po_receiving_log
                        (po_id, po_item_id, quantity_accepted, quantity_rejected, rejection_reason, rejection_category, received_by, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $id, $poItem['id'], $accepted, $rejected,
                        $rejectionReason, $rejectionCategory,
                        $currentUser['user_id'], $receivingNotes
                    ]);

                    // Process price update if provided
                    if (!empty($itemData['new_price']) && $itemData['new_price'] > 0) {
                        $itemType = $poItem['ingredient_id'] ? 'ingredient' : ($poItem['mro_item_id'] ? 'mro' : null);
                        $itemRefId = $poItem['ingredient_id'] ?: $poItem['mro_item_id'];
                        if ($itemType && $itemRefId) {
                            processItemPriceUpdate($db, [
                                'item_id' => $itemRefId,
                                'item_type' => $itemType,
                                'new_price' => $itemData['new_price'],
                                'reason' => 'Price update on receiving'
                            ], $id, $current['supplier_id'], $currentUser);
                        }
                    }

                    // Stock-in only the ACCEPTED quantity
                    if ($accepted > 0) {
                        $result = stockInSingleItem($db, $poItem, $accepted, $current['supplier_id'], $currentUser, $itemData['new_price'] ?? null, $itemData);
                        if ($result) $stockInResults[] = $result;
                    }

                    // Track rejected
                    if ($rejected > 0) {
                        $stockInResults[] = [
                            'type' => 'rejected',
                            'item' => $poItem['item_description'],
                            'quantity' => $rejected,
                            'reason' => $rejectionReason ?? 'No reason',
                            'category' => $rejectionCategory
                        ];
                    }

                    $totalAccepted += $accepted;
                    $totalRejected += $rejected;

                    // Check if this item is fully processed
                    $newTotal = $prevAccepted + $accepted + $prevRejected + $rejected;
                    if ($newTotal < $orderedQty - 0.001) {
                        $allFullyProcessed = false;
                    }
                }

                // Update PO status
                $newStatus = $allFullyProcessed ? 'received' : 'partial_received';
                $stmt = $db->prepare("
                    UPDATE purchase_orders
                    SET status = ?,
                        received_at = CASE WHEN ? = 'received' THEN NOW() ELSE received_at END,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$newStatus, $newStatus, $id]);

                $db->commit();

                logAudit($currentUser['user_id'], 'RECEIVE', 'purchase_orders', $id,
                    ['status' => $current['status']],
                    ['status' => $newStatus, 'accepted' => $totalAccepted, 'rejected' => $totalRejected,
                     'stocked_in_items' => count($stockInResults)]);

                $msg = "PO receiving recorded: {$totalAccepted} accepted, {$totalRejected} rejected.";
                if ($newStatus === 'partial_received') {
                    $msg .= ' Some items still pending delivery.';
                }

                Response::success([
                    'status' => $newStatus,
                    'total_accepted' => $totalAccepted,
                    'total_rejected' => $totalRejected,
                    'stocked_in' => $stockInResults
                ], $msg);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Auto stock-in PO items to warehouse inventory
 * Creates ingredient_batches or mro_inventory records for each PO line item
 * and updates the current_stock in the respective item tables.
 */
function stockInPOItems($db, $poId, $supplierId, $currentUser) {
    // Get all PO items
    $items = $db->prepare("
        SELECT poi.*, po.po_number, po.order_date
        FROM purchase_order_items poi
        JOIN purchase_orders po ON poi.po_id = po.id
        WHERE poi.po_id = ?
        ORDER BY poi.id ASC
    ");
    $items->execute([$poId]);
    $itemList = $items->fetchAll();

    $results = [];

    foreach ($itemList as $item) {
        $qty = (float) $item['quantity'];
        $unitPrice = (float) $item['unit_price'];

        // Update quantity_received on the PO item
        $db->prepare("
            UPDATE purchase_order_items SET quantity_received = ? WHERE id = ?
        ")->execute([$qty, $item['id']]);

        if ($item['ingredient_id']) {
            // --- INGREDIENT: Create batch + update stock ---
            $batchCode = 'IB-PO' . $item['po_id'] . '-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

            // Get ingredient info for shelf life
            $ingStmt = $db->prepare("SELECT * FROM ingredients WHERE id = ?");
            $ingStmt->execute([$item['ingredient_id']]);
            $ingData = $ingStmt->fetch();

            $shelfLife = $ingData ? ($ingData['shelf_life_days'] ?? 365) : 365;
            $expiryDate = date('Y-m-d', strtotime("+{$shelfLife} days"));

            $db->prepare("
                INSERT INTO ingredient_batches
                (batch_code, ingredient_id, po_id, quantity, remaining_quantity, unit_cost,
                 supplier_id, received_date, expiry_date, qc_status, status, received_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'approved', 'available', ?, ?)
            ")->execute([
                $batchCode,
                $item['ingredient_id'],
                $poId,
                $qty,
                $qty,
                $unitPrice,
                $supplierId,
                $expiryDate,
                $currentUser['user_id'],
                'Auto stocked-in from PO#' . $item['po_number']
            ]);
            $batchId = $db->lastInsertId();

            // Update ingredient current stock
            $db->prepare("
                UPDATE ingredients
                SET current_stock = current_stock + ?,
                    unit_cost = COALESCE(?, unit_cost),
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$qty, $unitPrice, $item['ingredient_id']]);

            // Create inventory transaction
            $txCode = generateCode('TX');
            $db->prepare("
                INSERT INTO inventory_transactions
                (transaction_code, transaction_type, item_type, item_id, batch_id,
                 quantity, unit_of_measure, reference_type, reference_id,
                 to_location, performed_by, reason)
                VALUES (?, 'po_receive', 'ingredient', ?, ?, ?, ?, 'purchase_order', ?, ?, ?, ?)
            ")->execute([
                $txCode,
                $item['ingredient_id'],
                $batchId,
                $qty,
                $item['unit'],
                $poId,
                $ingData['storage_location'] ?? 'Warehouse Raw',
                $currentUser['user_id'],
                'Received from PO#' . $item['po_number']
            ]);

            $results[] = [
                'type' => 'ingredient',
                'item' => $ingData['ingredient_name'] ?? $item['item_description'],
                'quantity' => $qty,
                'batch_code' => $batchCode,
                'transaction_code' => $txCode
            ];

        } elseif ($item['mro_item_id']) {
            // --- MRO: Create inventory record + update stock ---
            $batchCode = 'MRO-PO' . $item['po_id'] . '-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

            $mroStmt = $db->prepare("SELECT * FROM mro_items WHERE id = ?");
            $mroStmt->execute([$item['mro_item_id']]);
            $mroData = $mroStmt->fetch();

            // Get supplier name for the record
            $supplierNameForMRO = null;
            if ($supplierId) {
                $supStmt = $db->prepare("SELECT supplier_name FROM suppliers WHERE id = ?");
                $supStmt->execute([$supplierId]);
                $supRow = $supStmt->fetch();
                $supplierNameForMRO = $supRow ? $supRow['supplier_name'] : null;
            }

            $db->prepare("
                INSERT INTO mro_inventory
                (batch_code, mro_item_id, po_id, quantity, remaining_quantity, unit_cost,
                 supplier_name, supplier_id, received_date, received_by, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'available', ?)
            ")->execute([
                $batchCode,
                $item['mro_item_id'],
                $poId,
                $qty,
                $qty,
                $unitPrice,
                $supplierNameForMRO,
                $supplierId,
                $currentUser['user_id'],
                'Auto stocked-in from PO#' . $item['po_number']
            ]);
            $batchId = $db->lastInsertId();

            // Update MRO current stock
            $db->prepare("
                UPDATE mro_items
                SET current_stock = current_stock + ?,
                    unit_cost = COALESCE(?, unit_cost),
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$qty, $unitPrice, $item['mro_item_id']]);

            // Create inventory transaction
            $txCode = generateCode('TX');
            $db->prepare("
                INSERT INTO inventory_transactions
                (transaction_code, transaction_type, item_type, item_id, batch_id,
                 quantity, unit_of_measure, reference_type, reference_id,
                 to_location, performed_by, reason)
                VALUES (?, 'po_receive', 'mro', ?, ?, ?, ?, 'purchase_order', ?, ?, ?, ?)
            ")->execute([
                $txCode,
                $item['mro_item_id'],
                $batchId,
                $qty,
                $item['unit'],
                $poId,
                $mroData['storage_location'] ?? 'Warehouse Raw',
                $currentUser['user_id'],
                'Received from PO#' . $item['po_number']
            ]);

            $results[] = [
                'type' => 'mro',
                'item' => $mroData['item_name'] ?? $item['item_description'],
                'quantity' => $qty,
                'batch_code' => $batchCode,
                'transaction_code' => $txCode
            ];

        } else {
            // Unlinked item (legacy PO items without ingredient_id or mro_item_id)
            $results[] = [
                'type' => 'unlinked',
                'item' => $item['item_description'],
                'quantity' => $qty,
                'note' => 'Not auto-stocked (no ingredient_id or mro_item_id linked)'
            ];
        }
    }

    return $results;
}

/**
 * Process a price update for a single ingredient or MRO item.
 * Records price history and updates the item's current price.
 */
function processItemPriceUpdate($db, $update, $poId, $supplierId, $currentUser) {
    if (empty($update['item_id']) || empty($update['new_price'])) return;

    $itemType = $update['item_type'] ?? 'ingredient';
    $newPrice = (float) $update['new_price'];

    if ($itemType === 'ingredient') {
        $priceCheck = $db->prepare("SELECT unit_cost FROM ingredients WHERE id = ?");
        $priceCheck->execute([$update['item_id']]);
        $oldPrice = (float) $priceCheck->fetchColumn();

        if ($oldPrice != $newPrice) {
            $db->prepare("
                INSERT INTO ingredient_price_history
                (ingredient_id, old_price, new_price, po_id, supplier_id, reason, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $update['item_id'], $oldPrice, $newPrice, $poId,
                $supplierId, $update['reason'] ?? 'Price update on receiving',
                $currentUser['user_id']
            ]);

            $db->prepare("
                UPDATE ingredients SET unit_cost = ?, market_price = ?, last_price_update = CURDATE() WHERE id = ?
            ")->execute([$newPrice, $newPrice, $update['item_id']]);
        }
    } elseif ($itemType === 'mro') {
        $priceCheck = $db->prepare("SELECT unit_cost FROM mro_items WHERE id = ?");
        $priceCheck->execute([$update['item_id']]);
        $oldPrice = (float) $priceCheck->fetchColumn();

        if ($oldPrice != $newPrice) {
            $db->prepare("
                INSERT INTO mro_price_history
                (mro_item_id, old_price, new_price, po_id, supplier_id, reason, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $update['item_id'], $oldPrice, $newPrice, $poId,
                $supplierId, $update['reason'] ?? 'Price update on receiving',
                $currentUser['user_id']
            ]);

            $db->prepare("
                UPDATE mro_items SET unit_cost = ?, market_price = ?, last_price_update = CURDATE() WHERE id = ?
            ")->execute([$newPrice, $newPrice, $update['item_id']]);
        }
    }
}

/**
 * Stock in a specific quantity for a single PO item.
 * Used by the per-item receiving flow (only accepted qty gets stocked in).
 */
function stockInSingleItem($db, $poItem, $qty, $supplierId, $currentUser, $overridePrice = null, $receivingData = []) {
    $unitPrice = $overridePrice ? (float)$overridePrice : (float)$poItem['unit_price'];
    $lotNumber = trim((string)($receivingData['lot_number'] ?? ''));
    $condition = trim((string)($receivingData['condition'] ?? ''));
    $itemNotes = trim((string)($receivingData['notes'] ?? ''));

    if ($poItem['ingredient_id']) {
        $batchCode = 'IB-PO' . $poItem['po_id'] . '-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

        $ingStmt = $db->prepare("SELECT * FROM ingredients WHERE id = ?");
        $ingStmt->execute([$poItem['ingredient_id']]);
        $ingData = $ingStmt->fetch();

        $shelfLife = $ingData ? ($ingData['shelf_life_days'] ?? 365) : 365;
        $expiryDate = !empty($receivingData['expiry_date'])
            ? $receivingData['expiry_date']
            : date('Y-m-d', strtotime("+{$shelfLife} days"));
        $batchNotes = 'Received from PO#' . $poItem['po_number'];
        if ($lotNumber !== '') $batchNotes .= " | Supplier lot: {$lotNumber}";
        if ($condition !== '') $batchNotes .= " | Condition: {$condition}";
        if ($itemNotes !== '') $batchNotes .= " | Notes: {$itemNotes}";

        $db->prepare("
            INSERT INTO ingredient_batches
            (batch_code, ingredient_id, po_id, quantity, remaining_quantity, unit_cost,
             supplier_id, received_date, expiry_date, qc_status, status, received_by, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'approved', 'available', ?, ?)
        ")->execute([
            $batchCode, $poItem['ingredient_id'], $poItem['po_id'],
            $qty, $qty, $unitPrice, $supplierId, $expiryDate,
            $currentUser['user_id'],
            $batchNotes
        ]);
        $batchId = $db->lastInsertId();

        $db->prepare("
            UPDATE ingredients SET current_stock = current_stock + ?, unit_cost = COALESCE(?, unit_cost), updated_at = NOW() WHERE id = ?
        ")->execute([$qty, $unitPrice, $poItem['ingredient_id']]);

        $txCode = generateCode('TX');
        $db->prepare("
            INSERT INTO inventory_transactions
            (transaction_code, transaction_type, item_type, item_id, batch_id,
             quantity, unit_of_measure, reference_type, reference_id,
             to_location, performed_by, reason)
            VALUES (?, 'po_receive', 'ingredient', ?, ?, ?, ?, 'purchase_order', ?, ?, ?, ?)
        ")->execute([
            $txCode, $poItem['ingredient_id'], $batchId, $qty, $poItem['unit'],
            $poItem['po_id'], $ingData['storage_location'] ?? 'Warehouse Raw',
            $currentUser['user_id'], 'Received from PO#' . $poItem['po_number']
        ]);

        return [
            'type' => 'ingredient', 'item' => $ingData['ingredient_name'] ?? $poItem['item_description'],
            'quantity' => $qty, 'batch_code' => $batchCode, 'transaction_code' => $txCode
        ];

    } elseif ($poItem['mro_item_id']) {
        $batchCode = 'MRO-PO' . $poItem['po_id'] . '-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

        $mroStmt = $db->prepare("SELECT * FROM mro_items WHERE id = ?");
        $mroStmt->execute([$poItem['mro_item_id']]);
        $mroData = $mroStmt->fetch();

        $supplierName = null;
        if ($supplierId) {
            $supStmt = $db->prepare("SELECT supplier_name FROM suppliers WHERE id = ?");
            $supStmt->execute([$supplierId]);
            $supRow = $supStmt->fetch();
            $supplierName = $supRow ? $supRow['supplier_name'] : null;
        }

        $batchNotes = 'Received from PO#' . $poItem['po_number'];
        if ($lotNumber !== '') $batchNotes .= " | Supplier lot: {$lotNumber}";
        if ($condition !== '') $batchNotes .= " | Condition: {$condition}";
        if ($itemNotes !== '') $batchNotes .= " | Notes: {$itemNotes}";

        $db->prepare("
            INSERT INTO mro_inventory
            (batch_code, mro_item_id, po_id, quantity, remaining_quantity, unit_cost,
             supplier_name, supplier_id, received_date, received_by, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'available', ?)
        ")->execute([
            $batchCode, $poItem['mro_item_id'], $poItem['po_id'],
            $qty, $qty, $unitPrice, $supplierName, $supplierId,
            $currentUser['user_id'], $batchNotes
        ]);
        $batchId = $db->lastInsertId();

        $db->prepare("
            UPDATE mro_items SET current_stock = current_stock + ?, unit_cost = COALESCE(?, unit_cost), updated_at = NOW() WHERE id = ?
        ")->execute([$qty, $unitPrice, $poItem['mro_item_id']]);

        $txCode = generateCode('TX');
        $db->prepare("
            INSERT INTO inventory_transactions
            (transaction_code, transaction_type, item_type, item_id, batch_id,
             quantity, unit_of_measure, reference_type, reference_id,
             to_location, performed_by, reason)
            VALUES (?, 'po_receive', 'mro', ?, ?, ?, ?, 'purchase_order', ?, ?, ?, ?)
        ")->execute([
            $txCode, $poItem['mro_item_id'], $batchId, $qty, $poItem['unit'],
            $poItem['po_id'], $mroData['storage_location'] ?? 'Warehouse Raw',
            $currentUser['user_id'], 'Received from PO#' . $poItem['po_number']
        ]);

        return [
            'type' => 'mro', 'item' => $mroData['item_name'] ?? $poItem['item_description'],
            'quantity' => $qty, 'batch_code' => $batchCode, 'transaction_code' => $txCode
        ];
    }

    return [
        'type' => 'unlinked', 'item' => $poItem['item_description'],
        'quantity' => $qty, 'note' => 'Not auto-stocked (no ingredient_id or mro_item_id linked)'
    ];
}

function buildReceivingNotes($receivingMeta, $itemData) {
    $parts = [];

    if (!empty($receivingMeta['delivery_doc_number'])) {
        $parts[] = 'Delivery doc: ' . $receivingMeta['delivery_doc_number'];
    }
    if (!empty($receivingMeta['invoice_number'])) {
        $parts[] = 'Invoice: ' . $receivingMeta['invoice_number'];
    }
    if (!empty($receivingMeta['vehicle_plate'])) {
        $parts[] = 'Vehicle: ' . $receivingMeta['vehicle_plate'];
    }
    if (!empty($receivingMeta['received_condition'])) {
        $parts[] = 'Delivery condition: ' . $receivingMeta['received_condition'];
    }
    if (!empty($receivingMeta['notes'])) {
        $parts[] = 'Receiving notes: ' . $receivingMeta['notes'];
    }
    if (!empty($itemData['condition'])) {
        $parts[] = 'Item condition: ' . $itemData['condition'];
    }
    if (!empty($itemData['lot_number'])) {
        $parts[] = 'Supplier lot: ' . $itemData['lot_number'];
    }
    if (!empty($itemData['expiry_date'])) {
        $parts[] = 'Expiry: ' . $itemData['expiry_date'];
    }
    if (!empty($itemData['notes'])) {
        $parts[] = $itemData['notes'];
    }

    return empty($parts) ? null : implode(' | ', $parts);
}
