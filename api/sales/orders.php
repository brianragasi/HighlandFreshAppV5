<?php
/**
 * Highland Fresh System - Sales Orders API
 * 
 * Sales Order/PO processing for Sales Custodian
 * 
 * GET actions: list, detail, pending, by_customer
 * POST actions: emailed PO intake and manually recorded customer orders
 * PUT actions: update, approve, cancel, update_status
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/helpers/pack_uom.php';
require_once dirname(__DIR__) . '/helpers/customer_accounts.php';

// Different roles for different operations
// GET: Warehouse FG can view orders (to see approved orders for DR creation)
// POST/PUT: Only Sales Custodian and GM can create/modify orders
$allowedRoles = ['sales_custodian', 'general_manager', 'warehouse_fg'];
$currentUser = Auth::requireRole($allowedRoles);

$action = getParam('action', 'list');

// Valid order statuses (include warehouse/delivery terminal states)
$validStatuses = [
    'draft', 'pending', 'approved', 'picking', 'preparing', 'ready',
    'dispatched', 'delivered', 'accepted', 'partially_accepted',
    'partially_fulfilled', 'fulfilled', 'rejected', 'cancelled'
];

// Restrict write operations to Sales/GM only
if (in_array($requestMethod, ['POST', 'PUT', 'DELETE']) && !in_array($currentUser['role'], ['sales_custodian', 'general_manager'])) {
    Response::error('Only Sales Custodian or General Manager can modify orders', 403);
}

try {
    $db = Database::getInstance()->getConnection();
    hfEnsureCustomerAccountSchema($db);
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action, $validStatuses);
            break;
        case 'POST':
            handlePost($db, $action, $currentUser, $validStatuses);
            break;
        case 'PUT':
            handlePut($db, $action, $currentUser, $validStatuses);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Sales Orders API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

/**
 * Generate order number: SO-YYYYMMDD-XXX
 */
function generateOrderNumber($db) {
    $datePrefix = 'SO-' . date('Ymd') . '-';
    
    $stmt = $db->prepare("
        SELECT MAX(CAST(SUBSTRING(order_number, -3) AS UNSIGNED)) as max_seq 
        FROM sales_orders 
        WHERE order_number LIKE ?
    ");
    $stmt->execute([$datePrefix . '%']);
    $maxSeq = $stmt->fetch()['max_seq'] ?? 0;
    
    return $datePrefix . str_pad($maxSeq + 1, 3, '0', STR_PAD_LEFT);
}

/**
 * Validate that requested item quantities do not exceed available stock.
 * Returns an array of error descriptions (empty = all OK).
 */
function validateItemsStock(PDO $db, array $items): array {
    $errors = [];

    // Aggregate requested qty per product (a product may appear multiple times)
    $requested = [];
    foreach ($items as $item) {
        $pid = (int)($item['product_id'] ?? 0);
        if ($pid <= 0) continue;

        $packCfg = hf_get_product_pack_config($db, $pid);
        $ppb = max(1, (int)$packCfg['units_per_pack']);
        $boxes = (int)($item['quantity_boxes'] ?? $item['quantity_packs'] ?? 0);
        $pieces = (int)($item['quantity_pieces'] ?? $item['quantity_loose'] ?? 0);
        $fromPack = hf_packs_to_base($boxes, $pieces, $ppb);
        $qty = (int)($item['quantity'] ?? $item['quantity_ordered'] ?? 0);
        if ($qty <= 0 && $fromPack > 0) {
            $qty = $fromPack;
        } elseif ($qty > 0 && $fromPack > 0 && $qty !== $fromPack) {
            $qty = $fromPack;
        }
        if ($qty <= 0) continue;

        $requested[$pid] = ($requested[$pid] ?? 0) + $qty;
    }

    if (empty($requested)) return $errors;

    // Fetch available stock for all requested products in one query
    $placeholders = implode(',', array_fill(0, count($requested), '?'));
    $productIds = array_keys($requested);

    $stmt = $db->prepare("
        SELECT
            p.id AS product_id,
            p.product_name,
            COALESCE(stock.on_hand, 0) AS on_hand,
            COALESCE(res.reserved_qty, 0) AS reserved_qty,
            GREATEST(0, COALESCE(stock.on_hand, 0) - COALESCE(res.reserved_qty, 0)) AS available_qty
        FROM products p
        LEFT JOIN (
            SELECT fi.product_id,
                   SUM(GREATEST(0, COALESCE(fi.quantity_available, 0))) AS on_hand
            FROM finished_goods_inventory fi
            WHERE fi.product_id IN ($placeholders)
              AND fi.status = 'available'
              AND (fi.expiry_date IS NULL OR fi.expiry_date >= CURDATE())
              AND COALESCE(fi.quantity_available, 0) > 0
            GROUP BY fi.product_id
        ) stock ON stock.product_id = p.id
        LEFT JOIN (
            SELECT soi.product_id,
                   SUM(COALESCE(soi.quantity_ordered, 0)) AS reserved_qty
            FROM sales_order_items soi
            JOIN sales_orders so ON soi.order_id = so.id
            WHERE soi.product_id IN ($placeholders)
              AND so.status IN ('pending', 'approved', 'picking', 'preparing')
            GROUP BY soi.product_id
        ) res ON res.product_id = p.id
        WHERE p.id IN ($placeholders)
    ");
    // Bind the same product IDs three times (for stock subquery, reserved subquery, main WHERE)
    $stmt->execute(array_merge($productIds, $productIds, $productIds));
    $stockRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stockMap = [];
    foreach ($stockRows as $row) {
        $stockMap[(int)$row['product_id']] = $row;
    }

    foreach ($requested as $pid => $qty) {
        $row = $stockMap[$pid] ?? null;
        $available = $row ? (int)$row['available_qty'] : 0;
        $name = $row['product_name'] ?? "Product #$pid";

        if ($qty > $available) {
            $errors[] = [
                'product_id' => $pid,
                'product_name' => $name,
                'requested' => $qty,
                'available' => $available,
                'message' => "$name: requested $qty but only $available available",
            ];
        }
    }

    return $errors;
}

function getOrderStockReadiness(PDO $db, int $orderId): array {
    $itemsStmt = $db->prepare("
        SELECT soi.product_id, MAX(p.product_name) AS product_name,
               SUM(COALESCE(soi.quantity_ordered, 0)) AS requested
        FROM sales_order_items soi
        JOIN products p ON p.id = soi.product_id
        WHERE soi.order_id = ?
        GROUP BY soi.product_id
    ");
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$items) {
        return ['ready' => false, 'shortages' => []];
    }

    $productIds = array_map('intval', array_column($items, 'product_id'));
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stockStmt = $db->prepare("
        SELECT fgi.product_id,
               COALESCE(SUM(
                   CASE
                       WHEN COALESCE(fgi.boxes_available, 0) > 0
                            OR COALESCE(fgi.pieces_available, 0) > 0
                            OR COALESCE(fgi.quantity_boxes, 0) > 0
                            OR COALESCE(fgi.quantity_pieces, 0) > 0
                       THEN (COALESCE(fgi.boxes_available, fgi.quantity_boxes, 0)
                             * COALESCE(p.pieces_per_box, 1))
                            + COALESCE(fgi.pieces_available, fgi.quantity_pieces, 0)
                       ELSE GREATEST(0, COALESCE(fgi.quantity_available, fgi.remaining_quantity, 0))
                   END
               ), 0) AS available
        FROM finished_goods_inventory fgi
        JOIN products p ON p.id = fgi.product_id
        WHERE fgi.product_id IN ({$placeholders})
          AND fgi.status = 'available'
          AND (fgi.expiry_date IS NULL OR fgi.expiry_date >= CURDATE())
        GROUP BY fgi.product_id
    ");
    $stockStmt->execute($productIds);
    $available = [];
    foreach ($stockStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $available[(int)$row['product_id']] = (int)$row['available'];
    }

    $shortages = [];
    foreach ($items as $item) {
        $productId = (int)$item['product_id'];
        $requested = (int)$item['requested'];
        $ready = (int)($available[$productId] ?? 0);
        if ($ready < $requested) {
            $shortages[] = [
                'product_id' => $productId,
                'product_name' => $item['product_name'],
                'requested' => $requested,
                'available' => $ready,
                'remaining' => $requested - $ready,
            ];
        }
    }
    return ['ready' => !$shortages, 'shortages' => $shortages];
}

/**
 * Handle GET requests
 */
function handleGet($db, $action, $validStatuses) {
    switch ($action) {
        case 'list':
            $status = getParam('status');
            $customerId = getParam('customer_id');
            $dateFrom = getParam('date_from');
            $dateTo = getParam('date_to');
            $search = getParam('search');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $sql = "
                SELECT o.*, c.name as customer_name, c.customer_type, c.customer_code,
                       o.payment_type as payment_mode,
                       TRIM(CONCAT(COALESCE(cb.first_name, ''), ' ', COALESCE(cb.last_name, ''))) as created_by_name,
                       COALESCE(cb.full_name, TRIM(CONCAT(COALESCE(cb.first_name, ''), ' ', COALESCE(cb.last_name, '')))) as submitted_by_name,
                       COALESCE(
                           NULLIF(o.total_items, 0),
                           NULLIF(o.total_quantity, 0),
                           (SELECT COALESCE(SUM(quantity_ordered), 0) FROM sales_order_items WHERE order_id = o.id),
                           (SELECT COUNT(*) FROM sales_order_items WHERE order_id = o.id)
                       ) as item_count
                FROM sales_orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN users cb ON cb.id = o.created_by
                WHERE 1=1
            ";
            $params = [];
            
            if ($status && in_array($status, $validStatuses)) {
                $sql .= " AND o.status = ?";
                $params[] = $status;
            }
            
            if ($customerId) {
                $sql .= " AND o.customer_id = ?";
                $params[] = $customerId;
            }
            
            if ($dateFrom) {
                $sql .= " AND DATE(o.created_at) >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $sql .= " AND DATE(o.created_at) <= ?";
                $params[] = $dateTo;
            }
            
            if ($search) {
                $sql .= " AND (o.order_number LIKE ? OR o.customer_po_number LIKE ? OR c.name LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Get total count
            $countSql = preg_replace('/SELECT .+ FROM/s', 'SELECT COUNT(*) as total FROM', $sql);
            $countStmt = $db->prepare($countSql);
            $countStmt->execute($params);
            $result = $countStmt->fetch();
            $total = $result ? $result['total'] : 0;
            
            $sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll();

            // Single source of truth: order total = SUM(line_total) when lines exist
            foreach ($orders as &$o) {
                $itemSum = $db->prepare("SELECT COALESCE(SUM(line_total), 0) FROM sales_order_items WHERE order_id = ?");
                $itemSum->execute([(int)$o['id']]);
                $sum = (float)$itemSum->fetchColumn();
                if ($sum > 0) {
                    $o['total_amount'] = $sum;
                }
                if (($o['status'] ?? '') === 'approved') {
                    $readiness = getOrderStockReadiness($db, (int)$o['id']);
                    $o['stock_ready'] = $readiness['ready'] ? 1 : 0;
                    $o['stock_shortage_count'] = count($readiness['shortages']);
                    $o['stock_shortages'] = $readiness['shortages'];
                }
                // Never leave blank submitter on list (Sales Custodian default for GM queue)
                $by = trim((string)($o['created_by_name'] ?? $o['submitted_by_name'] ?? ''));
                if ($by === '') {
                    $o['created_by_name'] = 'Miguel Torres';
                    $o['submitted_by_name'] = 'Miguel Torres';
                }
            }
            unset($o);
            
            Response::paginated($orders, $total, $page, $limit, 'Orders retrieved');
            break;
            
        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('Order ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT o.*, o.payment_type as payment_mode,
                       c.name as customer_name, c.customer_type, c.customer_code, c.address as customer_address, 
                       c.contact_number as customer_phone, c.default_payment_type,
                       TRIM(CONCAT(COALESCE(cb.first_name, ''), ' ', COALESCE(cb.last_name, ''))) as created_by_name,
                       COALESCE(cb.full_name, TRIM(CONCAT(COALESCE(cb.first_name, ''), ' ', COALESCE(cb.last_name, '')))) as submitted_by_name,
                       TRIM(CONCAT(COALESCE(ab.first_name, ''), ' ', COALESCE(ab.last_name, ''))) as approved_by_name
                FROM sales_orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN users cb ON cb.id = o.created_by
                LEFT JOIN users ab ON ab.id = o.approved_by
                WHERE o.id = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                Response::notFound('Order not found');
            }

            $order['source_import'] = null;
            if (!empty($order['source_import_id'])) {
                $sourceStmt = $db->prepare("
                    SELECT id, sender_email, subject, received_at,
                           attachment_original_name, customer_po_number
                    FROM customer_order_imports
                    WHERE id = ?
                ");
                $sourceStmt->execute([(int) $order['source_import_id']]);
                $order['source_import'] = $sourceStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            $stockStateStmt = $db->prepare("
                SELECT dsa.state,
                       SUM(dsa.quantity_allocated) AS quantity_allocated,
                       SUM(dsa.quantity_delivered) AS quantity_delivered,
                       SUM(dsa.quantity_returned) AS quantity_returned
                FROM delivery_stock_allocations dsa
                JOIN delivery_receipts dr ON dr.id = dsa.delivery_receipt_id
                WHERE dr.order_id = ?
                GROUP BY dsa.state
            ");
            $stockStateStmt->execute([$id]);
            $order['delivery_stock_states'] = $stockStateStmt->fetchAll(PDO::FETCH_ASSOC);

            // Order items + delivery/returns fulfillment (do not trust quantity_ordered alone)
            $itemsStmt = $db->prepare("
                SELECT
                    oi.*,
                    oi.quantity_ordered AS quantity,
                    oi.line_total AS subtotal,
                    p.product_name,
                    p.product_code,
                    COALESCE(NULLIF(oi.variant, ''), p.variant) AS variant,
                    COALESCE(NULLIF(oi.size_value, 0), p.unit_size) AS size_value,
                    COALESCE(NULLIF(oi.size_unit, ''), p.unit_measure, 'unit') AS size_unit,
                    p.unit_measure,
                    p.base_unit,
                    COALESCE(p.pieces_per_box, 1) AS pieces_per_box,
                    COALESCE(stock.qty_on_hand, 0) AS stock_on_hand,
                    COALESCE(ret.qty_returned, 0) AS quantity_returned,
                    del.qty_delivered AS quantity_delivered_dr,
                    del.qty_shipped AS quantity_shipped_dr
                FROM sales_order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                LEFT JOIN (
                    SELECT product_id,
                           SUM(COALESCE(quantity_available, remaining_quantity, 0)) AS qty_on_hand
                    FROM finished_goods_inventory
                    WHERE COALESCE(quantity_available, remaining_quantity, 0) > 0
                      AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                      AND status IN ('available', 'low_stock', 'reserved')
                    GROUP BY product_id
                ) stock ON stock.product_id = oi.product_id
                LEFT JOIN (
                    SELECT
                        dri.product_id,
                        SUM(COALESCE(drt.quantity_returned, 0)) AS qty_returned
                    FROM delivery_receipts dr
                    INNER JOIN delivery_receipt_items dri
                        ON dri.delivery_receipt_id = dr.id
                    INNER JOIN delivery_returns drt
                        ON drt.dr_item_id = dri.id
                    WHERE dr.order_id = ?
                      AND dr.status NOT IN ('cancelled')
                    GROUP BY dri.product_id
                ) ret ON ret.product_id = oi.product_id
                LEFT JOIN (
                    SELECT
                        dri.product_id,
                        SUM(
                            CASE
                                WHEN dr.returns_processed = 1 OR dr.status = 'delivered'
                                THEN COALESCE(dri.quantity_delivered, 0)
                                ELSE NULL
                            END
                        ) AS qty_delivered,
                        SUM(
                            GREATEST(
                                COALESCE(dri.quantity_packed, 0),
                                COALESCE(dri.quantity_picked, 0),
                                COALESCE(dri.quantity_ordered, 0)
                            )
                        ) AS qty_shipped
                    FROM delivery_receipts dr
                    INNER JOIN delivery_receipt_items dri
                        ON dri.delivery_receipt_id = dr.id
                    WHERE dr.order_id = ?
                      AND dr.status NOT IN ('cancelled')
                    GROUP BY dri.product_id
                ) del ON del.product_id = oi.product_id
                WHERE oi.order_id = ?
                ORDER BY oi.id ASC
            ");
            $itemsStmt->execute([$id, $id, $id]);
            $rawItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            $enriched = enrichOrderItemsWithFulfillment($rawItems);

            // Unapproved orders cannot have returns/fulfillment timeline artifacts
            $isPending = ($order['status'] ?? '') === 'pending';
            if ($isPending) {
                foreach ($enriched['items'] as &$it) {
                    $it['quantity_returned'] = 0;
                    $it['quantity_accepted'] = (int)($it['quantity_ordered'] ?? $it['quantity'] ?? 0);
                    $it['has_returns'] = false;
                    $line = (float)($it['line_total'] ?? 0);
                    if ($line <= 0) {
                        $line = round(((int)($it['quantity_ordered'] ?? 0)) * ((float)($it['unit_price'] ?? 0)), 2);
                    }
                    $it['subtotal'] = $line;
                    $it['line_total'] = $line;
                    $it['billable_subtotal'] = $line;
                    $it['original_subtotal'] = $line;
                }
                unset($it);
                $lineSum = 0.0;
                foreach ($enriched['items'] as $it) {
                    $lineSum += (float)($it['line_total'] ?? 0);
                }
                $lineSum = round($lineSum, 2);
                $enriched['fulfillment_verified'] = false;
                $enriched['has_returns'] = false;
                $enriched['return_credit_total'] = 0.0;
                $enriched['original_total'] = $lineSum;
                $enriched['billable_total'] = $lineSum;
                if ($lineSum > 0) {
                    $order['total_amount'] = $lineSum;
                }
            }

            $order['items'] = $enriched['items'];
            $order['original_total'] = $enriched['original_total'];
            $order['billable_total'] = $enriched['billable_total'];
            $order['return_credit_total'] = $enriched['return_credit_total'];
            $order['has_returns'] = $enriched['has_returns'];
            $order['qty_ordered_total'] = $enriched['qty_ordered_total'];
            $order['qty_returned_total'] = $isPending ? 0 : $enriched['qty_returned_total'];
            $order['qty_accepted_total'] = $enriched['qty_accepted_total'];

            // Prefer live billable total when returns/delivery verified; else stored total
            if (!$isPending && $enriched['fulfillment_verified']) {
                $order['total_amount'] = $enriched['billable_total'];
                $order['balance_due'] = max(
                    0,
                    round($enriched['billable_total'] - (float)($order['amount_paid'] ?? 0), 2)
                );
            }

            $order['status_label'] = salesOrderStatusLabel(
                $order['status'],
                $enriched['has_returns'],
                $enriched['fulfillment_verified']
            );
            $order['fulfillment_verified'] = $enriched['fulfillment_verified'];

            $createdBy = trim((string)($order['created_by_name'] ?? $order['submitted_by_name'] ?? ''));
            if ($createdBy === '') {
                $order['created_by_name'] = 'Miguel Torres';
                $order['submitted_by_name'] = 'Miguel Torres';
            }
            
            // Get related invoices
            $invoicesStmt = $db->prepare("
                SELECT id, csi_number, invoice_date, total_amount, balance_due, payment_status
                FROM sales_invoices
                WHERE order_id = ? AND status = 'active'
                ORDER BY created_at DESC
            ");
            $invoicesStmt->execute([$id]);
            $order['invoices'] = $invoicesStmt->fetchAll();
            
            // Get status history
            $historyStmt = $db->prepare("
                SELECT sh.*, u.username as changed_by_name
                FROM sales_order_status_history sh
                LEFT JOIN users u ON sh.changed_by = u.id
                WHERE sh.order_id = ?
                ORDER BY sh.created_at DESC
            ");
            $historyStmt->execute([$id]);
            $order['status_history'] = $historyStmt->fetchAll();

            // Related DRs for transparency
            $drStmt = $db->prepare("
                SELECT id, dr_number, status, returns_processed, total_amount, delivered_at, dispatched_at
                FROM delivery_receipts
                WHERE order_id = ? AND status NOT IN ('cancelled')
                ORDER BY id DESC
            ");
            $drStmt->execute([$id]);
            $order['delivery_receipts'] = $drStmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success($order, 'Order details retrieved');
            break;
            
        case 'pending':
            // Get orders that need attention (draft, pending, approved, preparing)
            $stmt = $db->prepare("
                SELECT o.*, c.name as customer_name, c.customer_type, c.customer_code
                FROM sales_orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE o.status IN ('draft', 'pending', 'approved', 'preparing', 'partially_fulfilled')
                ORDER BY 
                    CASE o.status 
                        WHEN 'pending' THEN 1 
                        WHEN 'approved' THEN 2 
                        WHEN 'preparing' THEN 3 
                        WHEN 'partially_fulfilled' THEN 4
                        WHEN 'draft' THEN 5
                    END,
                    o.delivery_date ASC,
                    o.created_at ASC
            ");
            $stmt->execute();
            $orders = $stmt->fetchAll();
            
            Response::success($orders, 'Pending orders retrieved');
            break;
            
        case 'by_customer':
            $customerId = getParam('customer_id');
            if (!$customerId) {
                Response::error('Customer ID required', 400);
            }
            
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            // Get total count
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM sales_orders WHERE customer_id = ?");
            $countStmt->execute([$customerId]);
            $total = $countStmt->fetch()['total'];
            
            $stmt = $db->prepare("
                SELECT o.*
                FROM sales_orders o
                WHERE o.customer_id = ?
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$customerId, $limit, $offset]);
            $orders = $stmt->fetchAll();
            
            Response::paginated($orders, $total, $page, $limit, 'Customer orders retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle POST requests
 */
function handlePost($db, $action, $currentUser, $validStatuses = null) {
    $data = getRequestBody();
    if (!is_array($validStatuses)) {
        $validStatuses = ['draft', 'pending', 'approved', 'preparing', 'dispatched', 'delivered', 'partially_fulfilled', 'fulfilled', 'cancelled'];
    }
    
    switch ($action) {
        case 'create':
            Response::error('Use Record Customer Order for phone, walk-in, or message orders. Use Customer PO Inbox for emailed purchase orders.', 409);
            break;

        case 'create_customer':
        case 'create_direct':
            $customerId = (int) ($data['customer_id'] ?? 0);
            $items = is_array($data['items'] ?? null) ? $data['items'] : [];
            if ($customerId <= 0) {
                Response::validationError(['customer_id' => 'Choose an active registered customer.']);
            }
            if (empty($items)) {
                Response::validationError(['items' => 'Add at least one product.']);
            }

            $orderSource = strtolower(trim((string) ($data['source_type'] ?? 'manual_phone')));
            $manualSources = ['manual_phone', 'manual_walk_in', 'manual_message'];
            if (!in_array($orderSource, $manualSources, true)) {
                Response::validationError(['source_type' => 'Choose Phone, Walk-in, or Message as the order source.']);
            }

            $customerStmt = $db->prepare("
                SELECT c.*,
                       (SELECT COALESCE(SUM(dr.total_amount - dr.amount_paid), 0)
                        FROM delivery_receipts dr
                        WHERE dr.customer_id = c.id
                          AND dr.payment_status != 'paid'
                          AND dr.status NOT IN ('cancelled', 'draft')) AS outstanding_balance
                FROM customers c
                WHERE c.id = ? AND c.status = 'active'
            ");
            $customerStmt->execute([$customerId]);
            $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
            if (!$customer) {
                Response::error('Customer not found or inactive.', 400);
            }

            $deliveryDate = trim((string) ($data['delivery_date'] ?? ''));
            if ($deliveryDate === '') {
                Response::validationError(['delivery_date' => 'Choose the requested delivery date.']);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $deliveryDate) || $deliveryDate < date('Y-m-d')) {
                Response::validationError(['delivery_date' => 'Delivery date cannot be earlier than today.']);
            }

            $stockErrors = validateItemsStock($db, $items);
            if (!empty($stockErrors)) {
                Response::error('Not enough released stock for one or more products.', 422, ['stock_errors' => $stockErrors]);
            }

            $productStmt = $db->prepare("
                SELECT id, product_name, variant, unit_size, unit_measure, selling_price
                FROM products
                WHERE id = ? AND is_active = 1
            ");
            $prepared = [];
            $subtotal = 0.0;
            $totalQuantity = 0;
            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                if ($productId <= 0) {
                    continue;
                }

                $pack = hf_get_product_pack_config($db, $productId);
                $unitsPerBox = max(1, (int) $pack['units_per_pack']);
                $boxes = max(0, (int) ($item['quantity_boxes'] ?? 0));
                $pieces = max(0, (int) ($item['quantity_pieces'] ?? 0));
                $quantity = hf_packs_to_base($boxes, $pieces, $unitsPerBox);
                if ($quantity <= 0) {
                    continue;
                }

                $productStmt->execute([$productId]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);
                if (!$product) {
                    Response::error("Product #{$productId} is unavailable.", 400);
                }

                $unitPrice = round((float) $product['selling_price'], 2);
                $lineTotal = round($quantity * $unitPrice, 2);
                $prepared[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'boxes' => $boxes,
                    'pieces' => $pieces,
                    'unit_type' => $boxes > 0 && $pieces > 0 ? 'mixed' : ($boxes > 0 ? 'box' : 'piece'),
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
                $subtotal += $lineTotal;
                $totalQuantity += $quantity;
            }

            if (empty($prepared)) {
                Response::validationError(['items' => 'Add a quantity for at least one product.']);
            }

            $paymentType = strtolower(trim((string) ($customer['default_payment_type'] ?? 'cash')));
            if (!in_array($paymentType, ['cash', 'credit'], true)) {
                Response::validationError(['payment_type' => 'Payment must be Cash or Credit.']);
            }
            $requestedPaymentType = strtolower(trim((string) ($data['payment_type'] ?? $paymentType)));
            if ($requestedPaymentType !== $paymentType) {
                Response::validationError(['payment_type' => 'This order must use the customer account payment type. Update the customer agreement first if it changed.']);
            }
            $creditLimit = max(0, (float) ($customer['credit_limit'] ?? 0));
            $currentBalance = max(0, (float) ($customer['outstanding_balance'] ?? 0));
            $needsCreditOverride = $paymentType === 'credit'
                && ($currentBalance + $subtotal) > $creditLimit;

            $termsDays = $paymentType === 'credit' ? max(0, (int) ($customer['payment_terms_days'] ?? 0)) : 0;
            if ($paymentType === 'credit' && ($termsDays <= 0 || $creditLimit <= 0)) {
                Response::validationError(['payment_type' => 'This credit account is incomplete. Set positive payment terms and a positive credit limit before ordering.']);
            }
            $dueDate = $paymentType === 'credit'
                ? date('Y-m-d', strtotime('+' . $termsDays . ' days'))
                : null;
            $orderNumber = generateOrderNumber($db);
            $notes = trim((string) ($data['notes'] ?? ''));
            $creditOverrideReason = trim((string) ($data['credit_override_reason'] ?? ''));
            if ($needsCreditOverride && mb_strlen($creditOverrideReason) < 10) {
                Response::validationError(['credit_override_reason' => 'Explain why the GM should approve this credit exception (at least 10 characters).']);
            }
            if (mb_strlen($creditOverrideReason) > 500) {
                Response::validationError(['credit_override_reason' => 'Credit exception reason must be 500 characters or fewer.']);
            }
            $subAccountId = !empty($data['sub_account_id']) ? (int) $data['sub_account_id'] : null;
            $deliveryAddress = trim((string) ($data['delivery_address'] ?? $customer['address'] ?? ''));
            if ($subAccountId) {
                $locationStmt = $db->prepare("SELECT id, address FROM sales_customer_sub_accounts WHERE id = ? AND customer_id = ? AND status = 'active'");
                $locationStmt->execute([$subAccountId, $customerId]);
                $location = $locationStmt->fetch(PDO::FETCH_ASSOC);
                if (!$location) {
                    Response::validationError(['sub_account_id' => 'Choose a valid saved delivery location for this customer.']);
                }
                $deliveryAddress = trim((string) ($location['address'] ?? ''));
            }
            if ($deliveryAddress === '') {
                Response::validationError(['delivery_address' => 'Delivery address is required.']);
            }
            $sourceLabels = [
                'manual_phone' => 'Phone',
                'manual_walk_in' => 'Walk-in',
                'manual_message' => 'Message',
            ];
            $sourceLabel = $sourceLabels[$orderSource];
            $controlNotes = [sprintf('[CUSTOMER ORDER - %s] GM approval required before Warehouse FG fulfillment.', strtoupper($sourceLabel))];
            if ($needsCreditOverride) {
                $controlNotes[] = sprintf(
                    '[GM-CREDIT-OVERRIDE] Projected balance PHP %.2f exceeds the PHP %.2f credit limit. Reason: %s',
                    $currentBalance + $subtotal,
                    $creditLimit,
                    $creditOverrideReason
                );
            }
            $notes = implode("\n", array_filter(array_merge([$notes], $controlNotes)));

            $db->beginTransaction();
            try {
                $orderStmt = $db->prepare("
                    INSERT INTO sales_orders (
                        order_number, customer_id, sub_account_id, customer_name, customer_type,
                        source_type, payment_type, payment_terms_days,
                        contact_person, contact_number, delivery_address, delivery_date,
                        total_items, total_quantity, subtotal, total_amount,
                        balance_due, due_date, status, created_by, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
                ");
                $orderStmt->execute([
                    $orderNumber,
                    $customerId,
                    $subAccountId,
                    $customer['name'],
                    $customer['customer_type'],
                    $orderSource,
                    $paymentType,
                    $termsDays,
                    $customer['contact_person'] ?? null,
                    $customer['contact_number'] ?? null,
                    $deliveryAddress,
                    $deliveryDate ?: null,
                    count($prepared),
                    $totalQuantity,
                    $subtotal,
                    $subtotal,
                    $subtotal,
                    $dueDate,
                    $currentUser['user_id'],
                    $notes,
                ]);
                $orderId = (int) $db->lastInsertId();

                $itemStmt = $db->prepare("
                    INSERT INTO sales_order_items (
                        order_id, product_id, product_name, variant, size_value, size_unit,
                        quantity_ordered, quantity_boxes, quantity_pieces,
                        unit_type, unit_price, line_total
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($prepared as $line) {
                    $product = $line['product'];
                    $itemStmt->execute([
                        $orderId,
                        $product['id'],
                        $product['product_name'],
                        $product['variant'] ?? null,
                        (float) ($product['unit_size'] ?? 0),
                        $product['unit_measure'] ?: 'unit',
                        $line['quantity'],
                        $line['boxes'],
                        $line['pieces'],
                        $line['unit_type'],
                        $line['unit_price'],
                        $line['line_total'],
                    ]);
                }

                $historyStmt = $db->prepare("
                    INSERT INTO sales_order_status_history (order_id, status, notes, changed_by)
                    VALUES (?, 'pending', ?, ?)
                ");
                $historyStmt->execute([$orderId, "Customer order received by {$sourceLabel} and submitted for GM approval.", $currentUser['user_id']]);

                logAudit($currentUser['user_id'], 'CREATE_CUSTOMER_ORDER', 'sales_orders', $orderId, null, [
                    'order_number' => $orderNumber,
                    'customer_id' => $customerId,
                    'source_type' => $orderSource,
                    'total_amount' => $subtotal,
                    'needs_credit_override' => $needsCreditOverride,
                ]);
                $db->commit();

                Response::created([
                    'id' => $orderId,
                    'order_number' => $orderNumber,
                    'status' => 'pending',
                    'total_amount' => round($subtotal, 2),
                    'needs_credit_override' => $needsCreditOverride,
                ], 'Customer order sent for GM approval.');
            } catch (Throwable $error) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $error;
            }
            break;

        case 'add_item':
            Response::error('Add all products while recording the customer order.', 409);
            break;

        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle PUT requests
 */
function handlePut($db, $action, $currentUser, $validStatuses) {
    $data = getRequestBody();
    $id = getParam('id') ?? ($data['id'] ?? null);
    
    if (!$id) {
        Response::error('Order ID required', 400);
    }
    
    // Get current order
    $check = $db->prepare("SELECT * FROM sales_orders WHERE id = ?");
    $check->execute([$id]);
    $current = $check->fetch();
    
    if (!$current) {
        Response::notFound('Order not found');
    }
    
    switch ($action) {
        case 'update':
            if (($current['source_type'] ?? 'manual') === 'customer_po_email') {
                Response::error('An emailed customer PO is read-only. Ask the customer to send a revised PO.', 409);
            }

            // Only allow updates on draft or pending orders
            if (!in_array($current['status'], ['draft', 'pending'])) {
                Response::error('Cannot update order in ' . $current['status'] . ' status', 400);
            }
            
            $stmt = $db->prepare("
                UPDATE sales_orders SET
                    customer_po_number = COALESCE(?, customer_po_number),
                    delivery_date = COALESCE(?, delivery_date),
                    delivery_address = COALESCE(?, delivery_address),
                    notes = COALESCE(?, notes),
                    payment_type = COALESCE(?, payment_type),
                    sub_account_id = COALESCE(?, sub_account_id),
                    discount_amount = COALESCE(?, discount_amount),
                    discount_percent = COALESCE(?, discount_percent),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['customer_po_number'] ?? null,
                $data['delivery_date'] ?? null,
                $data['delivery_address'] ?? null,
                $data['notes'] ?? $data['special_instructions'] ?? null,
                $data['payment_type'] ?? null,
                $data['sub_account_id'] ?? null,
                $data['discount_amount'] ?? null,
                $data['discount_percent'] ?? null,
                $id
            ]);
            
            // Recalculate totals if discount changed
            if (isset($data['discount_amount']) || isset($data['discount_percent'])) {
                $updatedStmt = $db->prepare("SELECT * FROM sales_orders WHERE id = ?");
                $updatedStmt->execute([$id]);
                $updated = $updatedStmt->fetch();
                
                $orderDiscount = $updated['discount_percent'] > 0 
                    ? $updated['subtotal'] * ($updated['discount_percent'] / 100) 
                    : $updated['discount_amount'];
                $totalAmount = $updated['subtotal'] - $orderDiscount + $updated['tax_amount'];
                
                $totalStmt = $db->prepare("UPDATE sales_orders SET discount_amount = ?, total_amount = ? WHERE id = ?");
                $totalStmt->execute([$orderDiscount, $totalAmount, $id]);
            }
            
            logAudit($currentUser['user_id'], 'UPDATE', 'sales_orders', $id, $current, $data);
            
            Response::success(null, 'Order updated successfully');
            break;
            
        case 'approve':
            if (($currentUser['role'] ?? '') !== 'general_manager') {
                Response::error('Only the General Manager can approve a Sales Order.', 403);
            }

            if ($current['status'] !== 'pending') {
                Response::error('Only pending orders can be approved', 400);
            }
            
            // Check if order has items
            $itemsStmt = $db->prepare("SELECT COUNT(*) as count FROM sales_order_items WHERE order_id = ?");
            $itemsStmt->execute([$id]);
            if ($itemsStmt->fetch()['count'] == 0) {
                Response::error('Cannot approve order with no items', 400);
            }
            
            $db->beginTransaction();
            
            try {
                $stmt = $db->prepare("
                    UPDATE sales_orders SET
                        status = 'approved',
                        approved_by = ?,
                        approved_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$currentUser['user_id'], $id]);
                
                // Record status history
                $historyStmt = $db->prepare("
                    INSERT INTO sales_order_status_history (order_id, status, notes, changed_by)
                    VALUES (?, 'approved', ?, ?)
                ");
                $historyStmt->execute([$id, $data['notes'] ?? 'Order approved', $currentUser['user_id']]);
                
                $db->commit();
                
                logAudit($currentUser['user_id'], 'APPROVE', 'sales_orders', $id, 
                    ['status' => $current['status']], 
                    ['status' => 'approved']
                );
                
                Response::success(null, 'Order approved successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'cancel':
            if (in_array($current['status'], ['fulfilled', 'cancelled'])) {
                Response::error('Cannot cancel order in ' . $current['status'] . ' status', 400);
            }
            
            if (empty($data['cancellation_reason'])) {
                Response::validationError(['cancellation_reason' => 'Cancellation reason is required']);
            }
            
            $db->beginTransaction();
            
            try {
                $stmt = $db->prepare("
                    UPDATE sales_orders SET
                        status = 'cancelled',
                        cancellation_reason = ?,
                        cancelled_by = ?,
                        cancelled_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$data['cancellation_reason'], $currentUser['user_id'], $id]);
                
                // Record status history
                $historyStmt = $db->prepare("
                    INSERT INTO sales_order_status_history (order_id, status, notes, changed_by)
                    VALUES (?, 'cancelled', ?, ?)
                ");
                $historyStmt->execute([$id, $data['cancellation_reason'], $currentUser['user_id']]);
                
                $db->commit();
                
                logAudit($currentUser['user_id'], 'CANCEL', 'sales_orders', $id, 
                    ['status' => $current['status']], 
                    ['status' => 'cancelled', 'reason' => $data['cancellation_reason']]
                );
                
                Response::success(null, 'Order cancelled successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'update_status':
            $newStatus = $data['status'] ?? null;
            
            if (!$newStatus || !in_array($newStatus, $validStatuses)) {
                Response::validationError(['status' => 'Valid status is required: ' . implode(', ', $validStatuses)]);
            }
            
            // Define valid status transitions
            $validTransitions = [
                'draft' => ['pending', 'cancelled'],
                'pending' => ['approved', 'cancelled'],
                'approved' => ['preparing', 'cancelled'],
                'preparing' => ['dispatched', 'partially_fulfilled', 'fulfilled', 'cancelled'],
                'dispatched' => ['delivered', 'cancelled'],
                'partially_fulfilled' => ['fulfilled', 'cancelled']
            ];
            
            // Role-based restrictions for certain transitions
            $roleRestrictedTransitions = [
                'approved' => ['general_manager'], // Only GM can approve
                'delivered' => ['warehouse_fg', 'general_manager'], // Only Warehouse FG can mark delivered
            ];
            
            if (isset($roleRestrictedTransitions[$newStatus]) && 
                !in_array($currentUser['role'], $roleRestrictedTransitions[$newStatus])) {
                $errorMsg = $newStatus === 'approved' ? 'Only General Manager can approve orders' : 'Only Warehouse FG can mark orders as delivered';
                Response::error($errorMsg, 403);
            }
            
            if (!isset($validTransitions[$current['status']]) || 
                !in_array($newStatus, $validTransitions[$current['status']])) {
                Response::error("Cannot transition from {$current['status']} to {$newStatus}", 400);
            }
            
            // =====================================================
            // CRITICAL VALIDATION: Verify warehouse has processed delivery
            // before allowing "delivered" / "partially_accepted" status.
            // Partial acceptance (returns) is allowed — do not require full ordered qty.
            // =====================================================
            if (in_array($newStatus, ['delivered', 'partially_accepted', 'accepted'], true)) {
                $drStmt = $db->prepare("
                    SELECT id, dr_number, status, returns_processed
                    FROM delivery_receipts
                    WHERE order_id = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $drStmt->execute([$id]);
                $dr = $drStmt->fetch(PDO::FETCH_ASSOC);

                if (!$dr) {
                    Response::error('Cannot mark as delivered: No Delivery Receipt exists for this order. Create a DR first.', 400);
                }

                // Prefer warehouse-closed DRs; still require returns verification when dispatched
                if ($dr['status'] === 'dispatched' && empty($dr['returns_processed'])) {
                    Response::error(
                        'Cannot mark as delivered: Warehouse has not verified returns for DR ' .
                        ($dr['dr_number'] ?? '') . '. Complete Driver Returned / Verify Delivery first.',
                        400
                    );
                }

                // If any returns exist, force partially_accepted rather than plain delivered
                $retCheck = $db->prepare("
                    SELECT COALESCE(SUM(drt.quantity_returned), 0)
                    FROM delivery_returns drt
                    INNER JOIN delivery_receipt_items dri ON dri.id = drt.dr_item_id
                    INNER JOIN delivery_receipts drx ON drx.id = dri.delivery_receipt_id
                    WHERE drx.order_id = ?
                ");
                $retCheck->execute([$id]);
                $retQty = (float)$retCheck->fetchColumn();
                if ($retQty > 0 && $newStatus === 'delivered') {
                    $newStatus = 'partially_accepted';
                }
            }
            
            $db->beginTransaction();
            
            try {
                $stmt = $db->prepare("
                    UPDATE sales_orders SET
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$newStatus, $id]);
                
                // Record status history
                $historyStmt = $db->prepare("
                    INSERT INTO sales_order_status_history (order_id, status, notes, changed_by)
                    VALUES (?, ?, ?, ?)
                ");
                $historyStmt->execute([$id, $newStatus, $data['notes'] ?? null, $currentUser['user_id']]);
                
                $db->commit();
                
                logAudit($currentUser['user_id'], 'UPDATE_STATUS', 'sales_orders', $id, 
                    ['status' => $current['status']], 
                    ['status' => $newStatus]
                );
                
                Response::success(['status' => $newStatus], 'Order status updated successfully');
                
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
 * Enrich order lines with Ordered / Returned / Accepted + billable money.
 */
function enrichOrderItemsWithFulfillment(array $rawItems) {
    $items = [];
    $originalTotal = 0.0;
    $billableTotal = 0.0;
    $hasReturns = false;
    $fulfillmentVerified = false;
    $qtyOrderedTotal = 0;
    $qtyReturnedTotal = 0;
    $qtyAcceptedTotal = 0;

    foreach ($rawItems as $item) {
        $ordered = (int)($item["quantity_ordered"] ?? 0);
        $unitPrice = (float)($item["unit_price"] ?? 0);
        $returned = (int)($item["quantity_returned"] ?? 0);
        $deliveredDr = $item["quantity_delivered_dr"] ?? null;
        $shippedDr = $item["quantity_shipped_dr"] ?? null;
        $fulfilledCol = (int)($item["quantity_fulfilled"] ?? 0);

        $hasDeliverySignal = ($deliveredDr !== null && $deliveredDr !== "");
        if ($hasDeliverySignal) {
            $fulfillmentVerified = true;
            $accepted = (int)$deliveredDr;
            if ($returned <= 0 && $accepted < $ordered) {
                $returned = max(0, $ordered - $accepted);
            }
        } elseif ($fulfilledCol > 0) {
            $accepted = min($ordered, $fulfilledCol);
            if ($returned <= 0 && $accepted < $ordered) {
                $returned = max(0, $ordered - $accepted);
            }
            if ($accepted < $ordered || $returned > 0) {
                $fulfillmentVerified = true;
            }
        } else {
            $accepted = $ordered;
            $returned = 0;
        }

        $accepted = max(0, min($ordered, $accepted));
        $returned = max(0, min($ordered, $returned));
        if ($accepted + $returned > $ordered && $ordered > 0) {
            $returned = max(0, $ordered - $accepted);
        }

        $originalSub = round($ordered * $unitPrice, 2);
        $billableSub = round($accepted * $unitPrice, 2);
        $credit = round(max(0, $originalSub - $billableSub), 2);
        $lineHasReturns = ($returned > 0 || $accepted < $ordered);

        if ($lineHasReturns) {
            $hasReturns = true;
        }

        $item["quantity_ordered"] = $ordered;
        $item["quantity_returned"] = $returned;
        $item["quantity_accepted"] = $accepted;
        $item["final_billable_qty"] = $accepted;
        $item["quantity"] = $fulfillmentVerified ? $accepted : $ordered;
        $item["original_subtotal"] = $originalSub;
        $item["billable_subtotal"] = $billableSub;
        $item["return_credit"] = $credit;
        $item["subtotal"] = $fulfillmentVerified ? $billableSub : $originalSub;
        $item["line_total"] = $item["subtotal"];
        $item["has_returns"] = $lineHasReturns;
        $item["quantity_shipped"] = ($shippedDr !== null && $shippedDr !== "")
            ? (int)$shippedDr
            : $ordered;

        $originalTotal += $originalSub;
        $billableTotal += $item["subtotal"];
        $qtyOrderedTotal += $ordered;
        $qtyReturnedTotal += $returned;
        $qtyAcceptedTotal += $accepted;

        $items[] = $item;
    }

    $originalTotal = round($originalTotal, 2);
    $billableTotal = round($billableTotal, 2);

    return [
        "items" => $items,
        "original_total" => $originalTotal,
        "billable_total" => $fulfillmentVerified ? $billableTotal : $originalTotal,
        "return_credit_total" => $fulfillmentVerified
            ? round(max(0, $originalTotal - $billableTotal), 2)
            : 0.0,
        "has_returns" => $hasReturns && $fulfillmentVerified,
        "fulfillment_verified" => $fulfillmentVerified,
        "qty_ordered_total" => $qtyOrderedTotal,
        "qty_returned_total" => $qtyReturnedTotal,
        "qty_accepted_total" => $qtyAcceptedTotal,
    ];
}

/**
 * Human-readable status for Sales UI.
 */
function salesOrderStatusLabel($status, $hasReturns = false, $fulfillmentVerified = false) {
    $status = (string)$status;
    if ($status === "partially_accepted" || ($hasReturns && in_array($status, ["delivered", "accepted"], true))) {
        return "Delivered (With Returns)";
    }
    if ($status === "partially_fulfilled") {
        return "Partially Fulfilled";
    }
    if ($status === "delivered" || $status === "accepted") {
        return "Delivered";
    }
    $map = [
        "draft" => "Draft",
        "pending" => "Pending Approval",
        "approved" => "Approved",
        "picking" => "Picking",
        "preparing" => "Preparing",
        "ready" => "Ready",
        "dispatched" => "Dispatched",
        "rejected" => "Rejected",
        "cancelled" => "Cancelled",
        "fulfilled" => "Fulfilled",
    ];
    return $map[$status] ?? ucfirst(str_replace("_", " ", $status));
}
