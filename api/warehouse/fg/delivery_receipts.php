<?php
/**
 * Highland Fresh System - Warehouse FG Delivery Receipts API
 * 
 * GET - List DRs, get details, pending DRs
 * POST - Create DR
 * PUT - Update DR, release DR
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
require_once __DIR__ . '/inventory_helpers.php';

// Different roles for different operations:
// - GET: Sales can view DRs (to track delivery status)
// - POST: Only Warehouse FG can create DRs
// - PUT deliver: Only Warehouse FG can mark as delivered (after driver returns)
$currentUser = Auth::requireRole(['warehouse_fg', 'general_manager', 'sales_custodian']);

$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            // All allowed roles can view
            handleGet($db, $action);
            break;
        case 'POST':
            // Only Warehouse FG and GM can create DRs
            if (!in_array($currentUser['role'], ['warehouse_fg', 'general_manager'])) {
                Response::error('Only Warehouse FG can create Delivery Receipts', 403);
            }
            handlePost($db, $action, $currentUser);
            break;
        case 'PUT':
            handlePut($db, $action, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Delivery Receipts API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            $status = getParam('status');
            $customerId = getParam('customer_id');
            $fromDate = getParam('from_date');
            $toDate = getParam('to_date');
            
            $sql = "
                SELECT 
                    dr.*,
                    c.customer_type,
                    c.name as customer_name_ref,
                    u.first_name as prepared_by_name,
                    u.last_name as prepared_by_lastname,
                    d.first_name as dispatched_by_name,
                    (SELECT COUNT(*) FROM delivery_receipt_items WHERE delivery_receipt_id = dr.id) as item_count,
                    (SELECT COALESCE(SUM(quantity_ordered), 0) FROM delivery_receipt_items WHERE delivery_receipt_id = dr.id) as total_quantity
                FROM delivery_receipts dr
                LEFT JOIN customers c ON dr.customer_id = c.id
                LEFT JOIN users u ON dr.created_by = u.id
                LEFT JOIN users d ON dr.dispatched_by = d.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if ($status) {
                $sql .= " AND dr.status = ?";
                $params[] = $status;
            }
            
            if ($customerId) {
                $sql .= " AND dr.customer_id = ?";
                $params[] = $customerId;
            }
            
            if ($fromDate) {
                $sql .= " AND DATE(dr.created_at) >= ?";
                $params[] = $fromDate;
            }
            
            if ($toDate) {
                $sql .= " AND DATE(dr.created_at) <= ?";
                $params[] = $toDate;
            }
            
            $sql .= " ORDER BY dr.created_at DESC LIMIT 100";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $drs = $stmt->fetchAll();
            
            Response::success($drs, 'Delivery receipts retrieved');
            break;
            
        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('DR ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT dr.*,
                    u.first_name as prepared_by_name,
                    d.first_name as dispatched_by_name
                FROM delivery_receipts dr
                LEFT JOIN users u ON dr.created_by = u.id
                LEFT JOIN users d ON dr.dispatched_by = d.id
                WHERE dr.id = ?
            ");
            $stmt->execute([$id]);
            $dr = $stmt->fetch();
            
            if (!$dr) {
                Response::error('Delivery receipt not found', 404);
            }
            
            // Get line items with batch info.
            // Batch may live on dri.batch_id (release flow) OR via inventory_id (picking flow).
            // Also fall back to fg_dispatch_log for multi-batch dispatches.
            $itemsStmt = $db->prepare("
                SELECT dri.*,
                       dri.quantity_ordered as quantity,
                       p.product_name,
                       p.product_code as product_sku,
                       COALESCE(dri.batch_id, fgi.batch_id) as batch_id,
                       COALESCE(
                           pb_direct.batch_code,
                           pb_inv.batch_code,
                           dl.batch_codes,
                           fgi.barcode
                       ) as batch_code,
                       COALESCE(pb_direct.barcode, pb_inv.barcode) as batch_barcode,
                       COALESCE(pb_direct.expiry_date, pb_inv.expiry_date) as batch_expiry
                FROM delivery_receipt_items dri
                LEFT JOIN products p ON dri.product_id = p.id
                LEFT JOIN production_batches pb_direct ON dri.batch_id = pb_direct.id
                LEFT JOIN finished_goods_inventory fgi ON dri.inventory_id = fgi.id
                LEFT JOIN production_batches pb_inv ON fgi.batch_id = pb_inv.id
                LEFT JOIN (
                    SELECT dr_id, product_id,
                           GROUP_CONCAT(DISTINCT batch_code ORDER BY batch_code SEPARATOR ', ') as batch_codes
                    FROM fg_dispatch_log
                    WHERE batch_code IS NOT NULL AND batch_code <> ''
                    GROUP BY dr_id, product_id
                ) dl ON dl.dr_id = dri.delivery_receipt_id AND dl.product_id = dri.product_id
                WHERE dri.delivery_receipt_id = ?
            ");
            $itemsStmt->execute([$id]);
            $dr['items'] = $itemsStmt->fetchAll();

            // Print is for physical driver/customer signatures — allowed pre-delivery
            $printable = ['ready', 'dispatched', 'delivered'];
            $dr['print_allowed'] = in_array($dr['status'], $printable, true);
            $dr['is_printed'] = !empty($dr['printed_at']);
            $dr['requires_print_before_dispatch'] = ($dr['status'] === 'ready' && empty($dr['printed_at']));
            
            Response::success($dr, 'Delivery receipt details retrieved');
            break;
            
        case 'pending':
            $stmt = $db->prepare("
                SELECT 
                    dr.*,
                    u.first_name as prepared_by_name,
                    (SELECT COUNT(*) FROM delivery_receipt_items dri WHERE dri.delivery_receipt_id = dr.id AND dri.quantity_picked > 0) as items_picked,
                    (SELECT COUNT(*) FROM delivery_receipt_items dri WHERE dri.delivery_receipt_id = dr.id) as total_items_count
                FROM delivery_receipts dr
                LEFT JOIN users u ON dr.created_by = u.id
                WHERE dr.status IN ('pending', 'picking', 'preparing', 'draft')
                ORDER BY dr.picking_started_at ASC, dr.created_at ASC
            ");
            $stmt->execute();
            $drs = $stmt->fetchAll();
            
            Response::success($drs, 'Pending DRs retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Flag a DR as printed (paper form generated for driver/customer signatures).
 * Callable from POST or PUT so nginx method-override clients always work.
 *
 * @return array Success payload for Response::success
 */
function markDeliveryReceiptPrinted(PDO $db, array $current, $currentUser) {
    if (!in_array($currentUser['role'], ['warehouse_fg', 'general_manager'], true)) {
        Response::error('Only Warehouse FG can mark a DR as printed', 403);
    }

    $printableStatuses = ['ready', 'dispatched', 'delivered'];
    if (!in_array($current['status'], $printableStatuses, true)) {
        Response::error(
            'DR can only be printed when status is Ready, Dispatched, or Delivered (current: ' .
            $current['status'] . '). Complete picking first.',
            400
        );
    }

    $id = (int)$current['id'];

    // Ensure columns exist (idempotent)
    try {
        $db->exec("ALTER TABLE delivery_receipts ADD COLUMN printed_at DATETIME NULL DEFAULT NULL");
    } catch (Exception $e) { /* exists */ }
    try {
        $db->exec("ALTER TABLE delivery_receipts ADD COLUMN printed_by INT NULL DEFAULT NULL");
    } catch (Exception $e) { /* exists */ }

    // Always stamp print time (re-print refreshes the audit timestamp)
    $stmt = $db->prepare("
        UPDATE delivery_receipts
        SET printed_at = NOW(),
            printed_by = ?
        WHERE id = ?
    ");
    $stmt->execute([(int)$currentUser['user_id'], $id]);

    if ($stmt->rowCount() < 1) {
        // Row exists but values identical — still treat as success; re-read
        $check = $db->prepare("SELECT printed_at FROM delivery_receipts WHERE id = ?");
        $check->execute([$id]);
        if (!$check->fetchColumn()) {
            Response::error('Failed to update print status for this Delivery Receipt', 500);
        }
    }

    $read = $db->prepare("SELECT printed_at, status, dr_number FROM delivery_receipts WHERE id = ?");
    $read->execute([$id]);
    $row = $read->fetch(PDO::FETCH_ASSOC);

    return [
        'id' => $id,
        'dr_number' => $row['dr_number'] ?? null,
        'status' => $row['status'] ?? $current['status'],
        'printed_at' => $row['printed_at'] ?? date('Y-m-d H:i:s'),
        'is_printed' => true,
        'print_allowed' => true
    ];
}

function handlePost($db, $action, $currentUser) {
    $data = getRequestBody();

    // Print status flag (also available via PUT mark_printed)
    if ($action === 'mark_printed') {
        $id = getParam('id') ?? ($data['id'] ?? null);
        $drNumber = getParam('dr_number') ?? ($data['dr_number'] ?? null);

        if (!$id && $drNumber) {
            $find = $db->prepare("SELECT id FROM delivery_receipts WHERE dr_number = ? LIMIT 1");
            $find->execute([$drNumber]);
            $id = $find->fetchColumn();
        }
        if (!$id) {
            Response::error('DR id or dr_number is required', 400);
        }

        $check = $db->prepare("SELECT * FROM delivery_receipts WHERE id = ?");
        $check->execute([$id]);
        $current = $check->fetch(PDO::FETCH_ASSOC);
        if (!$current) {
            Response::error('Delivery receipt not found', 404);
        }

        $payload = markDeliveryReceiptPrinted($db, $current, $currentUser);
        Response::success($payload, 'Delivery Receipt marked as printed. Dispatch is now unlocked.');
    }
    
    if ($action === 'create_from_order') {
        // Create DR from an approved sales order
        $orderId = $data['order_id'] ?? null;
        if (!$orderId) {
            Response::error('Order ID required', 400);
        }
        
        // Get the order
        $orderStmt = $db->prepare("
            SELECT o.*, c.name as customer_name, c.customer_type, c.address, c.contact_number
            FROM sales_orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE o.id = ?
        ");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch();
        
        if (!$order) {
            Response::error('Order not found', 404);
        }
        
        if ($order['status'] !== 'approved') {
            Response::error('Only approved orders can be converted to delivery receipts', 400);
        }
        
        // Check if DR already exists for this order
        $existCheck = $db->prepare("SELECT id, dr_number FROM delivery_receipts WHERE order_id = ?");
        $existCheck->execute([$orderId]);
        $existing = $existCheck->fetch();
        if ($existing) {
            Response::error("DR already exists for this order: {$existing['dr_number']}", 400);
        }
        
        // Get order items
        $itemsStmt = $db->prepare("
            SELECT oi.*, p.product_name, COALESCE(p.pieces_per_box, 12) as pieces_per_box
            FROM sales_order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $itemsStmt->execute([$orderId]);
        $orderItems = $itemsStmt->fetchAll();
        
        // =====================================================
        // STOCK VALIDATION: Check if FG inventory has enough stock
        // =====================================================
        $stockWarnings = [];
        foreach ($orderItems as $item) {
            // Get available stock for this product.
            // CASE logic avoids double-counting:
            //  - If new-style columns (boxes/pieces) are populated → use them directly
            //  - Otherwise fall back to legacy quantity_available column
            $stockStmt = $db->prepare("
                SELECT 
                    COALESCE(SUM(
                        CASE
                            WHEN fgi.boxes_available > 0 OR fgi.pieces_available > 0
                            THEN (fgi.boxes_available * COALESCE(p.pieces_per_box, 12))
                                 + fgi.pieces_available
                            ELSE GREATEST(0, COALESCE(
                                NULLIF(fgi.quantity_pieces, 0),
                                fgi.quantity_available,
                                fgi.remaining_quantity,
                                0
                            ))
                        END
                    ), 0) as total_available
                FROM finished_goods_inventory fgi
                LEFT JOIN products p ON fgi.product_id = p.id
                LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                WHERE fgi.product_id = ?
                  AND pb.qc_status = 'released'
                  AND (pb.expiry_date IS NULL OR pb.expiry_date >= CURDATE())
            ");
            $stockStmt->execute([$item['product_id']]);
            $stock = $stockStmt->fetch();
            
            $available = (int)($stock['total_available'] ?? 0);
            $ordered = (int)$item['quantity_ordered'];
            
            if ($available < $ordered) {
                $stockWarnings[] = "{$item['product_name']}: need {$ordered}, only {$available} in FG";
            }
        }
        
        // If insufficient stock, return error
        if (!empty($stockWarnings)) {
            Response::error(
                "⚠️ INSUFFICIENT FG INVENTORY:\n" . 
                implode("\n", $stockWarnings) . 
                "\n\nPlease ensure products are produced, QC-released, and received in FG warehouse before creating DR.",
                400
            );
        }
        
        $db->beginTransaction();
        
        try {
            // Create PICKING TICKET (not DR yet!)
            // DR number will be assigned when picking is complete (finalize_picking action)
            $pickingNumber = 'PICK-' . $orderId . '-' . date('His');
            
            // Status = 'picking' - items must be scanned/picked before DR is generated
            $stmt = $db->prepare("
                INSERT INTO delivery_receipts 
                (dr_number, order_id, customer_id, customer_name, delivery_address, 
                 contact_number, total_items, total_amount, status, picking_started_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'picking', NOW(), ?)
            ");
            
            $stmt->execute([
                $pickingNumber,
                $orderId,
                $order['customer_id'],
                $order['customer_name'],
                $order['delivery_address'] ?? $order['address'],
                $order['contact_number'],
                count($orderItems),
                $order['total_amount'],
                $currentUser['user_id']
            ]);
            
            $drId = $db->lastInsertId();
            
            // Create DR items from order items
            $itemStmt = $db->prepare("
                INSERT INTO delivery_receipt_items 
                (delivery_receipt_id, product_id, quantity_ordered, unit_price, total_price)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($orderItems as $item) {
                $itemStmt->execute([
                    $drId,
                    $item['product_id'],
                    $item['quantity_ordered'],
                    $item['unit_price'],
                    $item['line_total']
                ]);
            }
            
            // Update order status to picking
            $updateOrder = $db->prepare("UPDATE sales_orders SET status = 'picking' WHERE id = ?");
            $updateOrder->execute([$orderId]);
            
            $db->commit();
            
            // Relative path only — frontend resolves under app base (e.g. /HighlandFreshAppV4/)
            // Absolute /html/... breaks when the app is not at domain root.
            Response::success([
                'id' => (int)$drId,
                'picking_number' => $pickingNumber,
                'redirect_url' => 'dispatch.html?pick=' . (int)$drId
            ], 'Picking ticket created. Proceed to scan items.', 201);
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
    
    if ($action === 'create') {
        $required = ['customer_type', 'customer_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                Response::error("$field is required", 400);
            }
        }
        
        $db->beginTransaction();
        
        try {
            // Generate DR number
            $drNumber = 'DR-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("
                INSERT INTO delivery_receipts 
                (dr_number, customer_type, customer_name, sub_location, contact_number, 
                 delivery_address, status, created_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?)
            ");
            
            $stmt->execute([
                $drNumber,
                $data['customer_type'],
                $data['customer_name'],
                $data['sub_location'] ?? null,
                $data['contact_number'] ?? null,
                $data['delivery_address'] ?? null,
                $currentUser['user_id'],
                $data['notes'] ?? null
            ]);
            
            $drId = $db->lastInsertId();
            
            $db->commit();
            
            Response::success(['id' => $drId, 'dr_number' => $drNumber], 'Delivery receipt created', 201);
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
    
    Response::error('Invalid action', 400);
}

function handlePut($db, $action, $currentUser) {
    $data = getRequestBody();
    $id = getParam('id') ?? ($data['id'] ?? null);
    
    if (!$id) {
        Response::error('DR ID required', 400);
    }
    
    // Get current DR
    $check = $db->prepare("SELECT * FROM delivery_receipts WHERE id = ?");
    $check->execute([$id]);
    $current = $check->fetch();
    
    if (!$current) {
        Response::error('Delivery receipt not found', 404);
    }
    
    switch ($action) {
        case 'update':
            if (!in_array($current['status'], ['draft', 'pending'])) {
                Response::error('Cannot update DR in current status', 400);
            }
            
            $stmt = $db->prepare("
                UPDATE delivery_receipts SET
                    customer_name = COALESCE(?, customer_name),
                    sub_location = COALESCE(?, sub_location),
                    contact_number = COALESCE(?, contact_number),
                    delivery_address = COALESCE(?, delivery_address),
                    notes = COALESCE(?, notes)
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['customer_name'] ?? null,
                $data['sub_location'] ?? null,
                $data['contact_number'] ?? null,
                $data['delivery_address'] ?? null,
                $data['notes'] ?? null,
                $id
            ]);
            
            Response::success(null, 'Delivery receipt updated');
            break;
            
        case 'finalize_picking':
            // GENERATE DR NUMBER - This is called AFTER all items have been scanned/picked
            // Only Warehouse FG can finalize picking
            if (!in_array($currentUser['role'], ['warehouse_fg', 'general_manager'])) {
                Response::error('Only Warehouse FG can finalize picking', 403);
            }
            
            // Must be in 'picking' status
            if ($current['status'] !== 'picking') {
                Response::error('This picking ticket is not in picking status', 400);
            }
            
            // Process scanned items from frontend
            $scannedItems = $data['items'] ?? [];
            if (empty($scannedItems)) {
                Response::error('No items have been scanned. Please scan items first.', 400);
            }
            
            $db->beginTransaction();
            try {
                // Lock ticket line items and load ordered quantities (never trust frontend alone)
                $orderedStmt = $db->prepare("
                    SELECT dri.id, dri.product_id, dri.quantity_ordered,
                           p.product_name
                    FROM delivery_receipt_items dri
                    LEFT JOIN products p ON dri.product_id = p.id
                    WHERE dri.delivery_receipt_id = ?
                    FOR UPDATE
                ");
                $orderedStmt->execute([$id]);
                $drLines = $orderedStmt->fetchAll();

                if (empty($drLines)) {
                    throw new Exception('Picking ticket has no line items.');
                }

                $orderedByProduct = [];
                $productNames = [];
                foreach ($drLines as $line) {
                    $pid = (int)$line['product_id'];
                    $orderedByProduct[$pid] = (int)$line['quantity_ordered'];
                    $productNames[$pid] = $line['product_name'] ?? ('Product #' . $pid);
                }

                // Tally unique inventory rows / bottles submitted (and total pieces per product)
                $scannedByProduct = [];   // product_id => total pieces
                $seenInventoryIds = [];   // inventory_id => qty (detect double-submit of same row)
                $lastInventoryByProduct = [];
                $lastBatchByProduct = []; // production_batches.id resolved from FG inventory

                $invLookup = $db->prepare("
                    SELECT fgi.id, fgi.product_id, fgi.batch_id, p.product_name, pb.batch_code
                    FROM finished_goods_inventory fgi
                    LEFT JOIN products p ON fgi.product_id = p.id
                    LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                    WHERE fgi.id = ?
                ");

                foreach ($scannedItems as $scanned) {
                    $inventoryId = (int)($scanned['inventory_id'] ?? 0);
                    $qty = (int)($scanned['total_pieces'] ?? $scanned['quantity'] ?? 0);

                    if ($inventoryId <= 0 || $qty <= 0) {
                        throw new Exception('Each scanned item must include a valid inventory_id and positive quantity.');
                    }

                    // Same FG inventory row cannot be submitted twice in one finalize payload
                    if (isset($seenInventoryIds[$inventoryId])) {
                        throw new Exception(
                            "Duplicate scan for inventory row #{$inventoryId}. " .
                            "Each unique bottle/batch scan may only be counted once per Generate DR."
                        );
                    }
                    $seenInventoryIds[$inventoryId] = $qty;

                    $invLookup->execute([$inventoryId]);
                    $inv = $invLookup->fetch();
                    if (!$inv) {
                        throw new Exception("Inventory item #{$inventoryId} not found.");
                    }

                    $productId = (int)$inv['product_id'];
                    $productName = $inv['product_name'] ?? $productNames[$productId] ?? 'Unknown product';

                    if (!isset($orderedByProduct[$productId])) {
                        throw new Exception(
                            "Product '{$productName}' is NOT on this picking ticket. " .
                            "Only ordered products may be picked."
                        );
                    }

                    if (!isset($scannedByProduct[$productId])) {
                        $scannedByProduct[$productId] = 0;
                    }
                    $scannedByProduct[$productId] += $qty;
                    $lastInventoryByProduct[$productId] = $inventoryId;
                    if (!empty($inv['batch_id'])) {
                        $lastBatchByProduct[$productId] = (int)$inv['batch_id'];
                    }

                    // Per-product hard cap: total unique bottles/pieces vs ordered
                    if ($scannedByProduct[$productId] > $orderedByProduct[$productId]) {
                        $required = $orderedByProduct[$productId];
                        $attempted = $scannedByProduct[$productId];
                        throw new Exception(
                            "Cannot exceed ordered quantity for '{$productName}': " .
                            "scanned {$attempted}, ordered {$required}."
                        );
                    }
                }

                // Absolute guard: overall total scanned must not exceed overall ordered
                $totalScanned = array_sum($scannedByProduct);
                $totalOrdered = array_sum($orderedByProduct);
                if ($totalScanned > $totalOrdered) {
                    throw new Exception(
                        "Cannot exceed ordered quantity: scanned {$totalScanned}, ordered {$totalOrdered}."
                    );
                }
                if ($totalScanned <= 0) {
                    throw new Exception('Failed to record picked items.');
                }

                // SET (not +=) picked qty from this submission — avoids double-count on retry
                // Persist batch_id so DR Details can show printed production batch codes
                $resetPicked = $db->prepare("
                    UPDATE delivery_receipt_items
                    SET quantity_picked = 0, inventory_id = NULL, batch_id = NULL, picked_at = NULL
                    WHERE delivery_receipt_id = ?
                ");
                $resetPicked->execute([$id]);

                $updateItem = $db->prepare("
                    UPDATE delivery_receipt_items
                    SET quantity_picked = ?,
                        inventory_id = ?,
                        batch_id = ?,
                        picked_at = NOW()
                    WHERE delivery_receipt_id = ? AND product_id = ?
                ");
                foreach ($scannedByProduct as $productId => $pickedQty) {
                    $updateItem->execute([
                        $pickedQty,
                        $lastInventoryByProduct[$productId] ?? null,
                        $lastBatchByProduct[$productId] ?? null,
                        $id,
                        $productId
                    ]);
                }

                // Re-check pick status after updating (must never show over-pick in DB)
                $pickedCheck = $db->prepare("
                    SELECT COUNT(*) as item_count,
                           COALESCE(SUM(quantity_picked), 0) as total_picked,
                           COALESCE(SUM(quantity_ordered), 0) as total_ordered,
                           SUM(CASE WHEN COALESCE(quantity_picked, 0) > quantity_ordered THEN 1 ELSE 0 END) as over_pick_lines
                    FROM delivery_receipt_items
                    WHERE delivery_receipt_id = ?
                ");
                $pickedCheck->execute([$id]);
                $pickStatus = $pickedCheck->fetch();

                if ((int)$pickStatus['total_picked'] <= 0) {
                    throw new Exception('Failed to record picked items.');
                }
                if ((int)$pickStatus['over_pick_lines'] > 0
                    || (int)$pickStatus['total_picked'] > (int)$pickStatus['total_ordered']) {
                    throw new Exception('Cannot exceed ordered quantity. Transaction aborted.');
                }

                // All lines exact match (optional strict completeness flag)
                $allPickedCheck = $db->prepare("
                    SELECT COUNT(*) as total_items,
                           SUM(CASE WHEN COALESCE(quantity_picked, 0) = quantity_ordered THEN 1 ELSE 0 END) as exact_items
                    FROM delivery_receipt_items
                    WHERE delivery_receipt_id = ?
                ");
                $allPickedCheck->execute([$id]);
                $exactStatus = $allPickedCheck->fetch();
                $allPicked = ((int)$exactStatus['total_items'] > 0
                    && (int)$exactStatus['exact_items'] === (int)$exactStatus['total_items']);
            
                // Generate SEQUENTIAL DR number (DR-YYYYMMDD-XXX)
                $datePrefix = 'DR-' . date('Ymd') . '-';
                $seqStmt = $db->prepare("
                    SELECT MAX(CAST(SUBSTRING(dr_number, 13) AS UNSIGNED)) as max_seq 
                    FROM delivery_receipts 
                    WHERE dr_number LIKE ?
                ");
                $seqStmt->execute([$datePrefix . '%']);
                $maxSeq = $seqStmt->fetch()['max_seq'] ?? 0;
                $drNumber = $datePrefix . str_pad($maxSeq + 1, 3, '0', STR_PAD_LEFT);
                
                // Update the record with final DR number
                $updateDR = $db->prepare("
                    UPDATE delivery_receipts SET
                        dr_number = ?,
                        status = 'ready',
                        prepared_by = ?,
                        prepared_at = NOW()
                    WHERE id = ?
                ");
                $updateDR->execute([$drNumber, $currentUser['user_id'], $id]);
                
                // DEDUCT INVENTORY — base units only, then recompute packs + loose.
                // Broken prior logic zeroed boxes/pieces without reducing quantity_available.
                $itemsStmt = $db->prepare("
                    SELECT dri.*, p.product_name
                    FROM delivery_receipt_items dri
                    LEFT JOIN products p ON dri.product_id = p.id
                    WHERE dri.delivery_receipt_id = ?
                ");
                $itemsStmt->execute([$id]);
                $items = $itemsStmt->fetchAll();

                $deductionLog = [];

                foreach ($items as $item) {
                    $needBase = (int)($item['quantity_picked'] ?? 0);
                    if ($needBase <= 0) {
                        continue;
                    }

                    $productName = $item['product_name'] ?? ('Product #' . $item['product_id']);
                    $remaining = $needBase;

                    // 1) Prefer the exact scanned inventory row (batch) when present
                    $candidateIds = [];
                    if (!empty($item['inventory_id'])) {
                        $candidateIds[] = (int)$item['inventory_id'];
                    }

                    // 2) FIFO fill remaining from other released FG batches of same product
                    $fifoStmt = $db->prepare("
                        SELECT fgi.id
                        FROM finished_goods_inventory fgi
                        LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                        LEFT JOIN products p ON fgi.product_id = p.id
                        WHERE fgi.product_id = ?
                          AND (pb.qc_status = 'released' OR pb.qc_status IS NULL)
                          AND (
                              fgi.boxes_available > 0
                              OR fgi.pieces_available > 0
                              OR fgi.quantity_available > 0
                              OR fgi.remaining_quantity > 0
                              OR fgi.quantity > 0
                          )
                        ORDER BY
                            CASE WHEN fgi.id = ? THEN 0 ELSE 1 END,
                            pb.manufacturing_date ASC,
                            pb.created_at ASC,
                            fgi.id ASC
                    ");
                    $preferId = !empty($item['inventory_id']) ? (int)$item['inventory_id'] : 0;
                    $fifoStmt->execute([(int)$item['product_id'], $preferId]);
                    foreach ($fifoStmt->fetchAll(PDO::FETCH_COLUMN) as $fid) {
                        $fid = (int)$fid;
                        if (!in_array($fid, $candidateIds, true)) {
                            $candidateIds[] = $fid;
                        }
                    }

                    if (empty($candidateIds)) {
                        throw new Exception(
                            "No FG inventory available to fulfill {$needBase} units of '{$productName}'. " .
                            "Transaction rolled back."
                        );
                    }

                    foreach ($candidateIds as $invId) {
                        if ($remaining <= 0) {
                            break;
                        }

                        // Lock + measure effective base for this row
                        $lockStmt = $db->prepare("
                            SELECT fgi.*, COALESCE(p.pieces_per_box, 12) AS pieces_per_box
                            FROM finished_goods_inventory fgi
                            LEFT JOIN products p ON fgi.product_id = p.id
                            WHERE fgi.id = ?
                            FOR UPDATE
                        ");
                        $lockStmt->execute([$invId]);
                        $invRow = $lockStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$invRow) {
                            continue;
                        }

                        $available = fgInventoryEffectiveBaseUnits($invRow, (int)$invRow['pieces_per_box']);
                        if ($available <= 0) {
                            continue;
                        }

                        $toDeduct = min($remaining, $available);
                        $result = fgInventoryDeductBaseUnits($db, $invId, $toDeduct);
                        $deductionLog[] = $result;
                        $remaining -= $toDeduct;
                    }

                    if ($remaining > 0) {
                        throw new Exception(
                            "Insufficient FG stock for '{$productName}': short by {$remaining} base unit(s) " .
                            "of {$needBase} required. Entire finalize transaction rolled back."
                        );
                    }
                }
                
                // Update linked Sales Order status
                if (!empty($current['order_id'])) {
                    $updateOrder = $db->prepare("
                        UPDATE sales_orders SET status = 'ready', updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateOrder->execute([$current['order_id']]);
                }
                
                $db->commit();
                
                Response::success([
                    'id' => $id,
                    'dr_number' => $drNumber,
                    'status' => 'ready',
                    'items_picked' => $pickStatus['total_picked'],
                    'all_items_picked' => $allPicked,
                    'inventory_deductions' => $deductionLog
                ], "Picking complete! DR {$drNumber} generated. Inventory has been deducted.");
                
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                // Business-rule failures (over-pick, wrong product, etc.) → HTTP 400 JSON
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'mark_printed':
            // Record paper DR print — shared helper (also exposed on POST)
            $payload = markDeliveryReceiptPrinted($db, $current, $currentUser);
            Response::success(
                $payload,
                'Delivery Receipt marked as printed. Paper copy is ready for driver signatures.'
            );
            break;

        case 'release':
        case 'dispatch':
            // Only Warehouse FG can dispatch
            if (!in_array($currentUser['role'], ['warehouse_fg', 'general_manager'])) {
                Response::error('Only Warehouse FG can dispatch deliveries', 403);
            }
            
            // Must be 'ready' status (items have been picked) to dispatch
            if ($current['status'] !== 'ready') {
                if ($current['status'] === 'pending' || $current['status'] === 'preparing') {
                    Response::error('Items must be picked first. Use Dispatch page to scan and release items before dispatching.', 400);
                }
                Response::error('DR must be in ready status to dispatch (was: ' . $current['status'] . ')', 400);
            }

            // HARD RULE: physical DR (Delivered By / Received By signatures) must print before truck leaves
            $printedAt = $current['printed_at'] ?? null;
            if (empty($printedAt)) {
                // Re-read in case column was just added / race with mark_printed
                try {
                    $printCheck = $db->prepare("SELECT printed_at FROM delivery_receipts WHERE id = ?");
                    $printCheck->execute([$id]);
                    $printedAt = $printCheck->fetchColumn();
                } catch (Exception $e) {
                    $printedAt = null;
                }
            }
            if (empty($printedAt)) {
                Response::error(
                    'Print the Delivery Receipt before dispatching. ' .
                    'The paper form has Driver (Delivered By) and Customer (Received By) signature lines ' .
                    'and must leave with the truck.',
                    400
                );
            }
            
            // Check if DR has items to dispatch
            $itemsCheck = $db->prepare("
                SELECT COUNT(*) as item_count, COALESCE(SUM(quantity_ordered), 0) as total_qty
                FROM delivery_receipt_items WHERE delivery_receipt_id = ?
            ");
            $itemsCheck->execute([$id]);
            $itemsInfo = $itemsCheck->fetch();
            
            if ($itemsInfo['item_count'] == 0 || $itemsInfo['total_qty'] == 0) {
                Response::error('Cannot dispatch - no items have been added to this delivery receipt.', 400);
            }
            
            $db->beginTransaction();
            try {
                // Note: Inventory was already deducted during the picking process in dispatch.html
                // when status changed from pending -> ready. 
                // This dispatch action marks the truck as officially out (after paper DR printed).
                
                // Update DR status
                $stmt = $db->prepare("
                    UPDATE delivery_receipts SET
                        status = 'dispatched',
                        dispatched_at = NOW(),
                        dispatched_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$currentUser['user_id'], $id]);
                
                // Also update linked Sales Order if exists
                if (!empty($current['order_id'])) {
                    $updateOrder = $db->prepare("
                        UPDATE sales_orders SET status = 'dispatched', updated_at = NOW()
                        WHERE id = ? AND status IN ('approved', 'preparing', 'partially_fulfilled', 'picking', 'ready')
                    ");
                    $updateOrder->execute([$current['order_id']]);
                }
                
                $db->commit();
                Response::success([
                    'id' => (int)$id,
                    'status' => 'dispatched',
                    'printed_at' => $printedAt
                ], 'Delivery receipt dispatched successfully. Truck may leave with the signed paper DR.');
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'deliver':
            // Only Warehouse FG can confirm delivery
            if (!in_array($currentUser['role'], ['warehouse_fg', 'general_manager'])) {
                Response::error('Only Warehouse FG can confirm delivery', 403);
            }
            
            if ($current['status'] !== 'dispatched') {
                Response::error('DR must be dispatched first', 400);
            }
            
            // Check if returns have been processed (required before marking delivered)
            if (!$current['returns_processed']) {
                Response::error(
                    'Cannot mark as delivered: Returns processing not completed. ' .
                    'Please process returns (or mark "No Returns") before confirming delivery.',
                    400
                );
            }
            
            // Returns already reconciled inventory/billing; close DR + order state machine
            $db->beginTransaction();
            try {
                // Accepted vs shipped for partial close
                $agg = $db->prepare("
                    SELECT
                        COALESCE(SUM(COALESCE(quantity_delivered, 0)), 0) AS qty_accepted,
                        COALESCE(SUM(
                            GREATEST(
                                COALESCE(quantity_packed, 0),
                                COALESCE(quantity_picked, 0),
                                COALESCE(quantity_ordered, 0)
                            )
                        ), 0) AS qty_shipped,
                        COALESCE(SUM(COALESCE(quantity_delivered, 0) * COALESCE(unit_price, 0)), 0) AS amount_accepted
                    FROM delivery_receipt_items
                    WHERE delivery_receipt_id = ?
                ");
                $agg->execute([$id]);
                $t = $agg->fetch(PDO::FETCH_ASSOC);
                $qtyAccepted = (int)$t['qty_accepted'];
                $qtyShipped = (int)$t['qty_shipped'];
                $amountAccepted = round((float)$t['amount_accepted'], 2);
                $isPartial = $qtyAccepted < $qtyShipped;

                $stmt = $db->prepare("
                    UPDATE delivery_receipts SET
                        status = 'delivered',
                        delivered_at = NOW(),
                        total_amount = ?
                    WHERE id = ?
                ");
                $stmt->execute([$amountAccepted, $id]);

                // Close sales order: full delivery vs partially accepted
                if (!empty($current['order_id'])) {
                    $orderStatus = $isPartial ? 'partially_accepted' : 'delivered';
                    $updateOrder = $db->prepare("
                        UPDATE sales_orders SET
                            status = ?,
                            total_amount = CASE WHEN ? > 0 THEN ? ELSE total_amount END,
                            balance_due = GREATEST(0, (CASE WHEN ? > 0 THEN ? ELSE total_amount END) - COALESCE(amount_paid, 0)),
                            payment_status = CASE
                                WHEN COALESCE(amount_paid, 0) <= 0 THEN 'unpaid'
                                WHEN COALESCE(amount_paid, 0) + 0.009 >= (CASE WHEN ? > 0 THEN ? ELSE total_amount END) THEN 'paid'
                                ELSE 'partial'
                            END,
                            updated_at = NOW()
                        WHERE id = ?
                          AND status IN ('dispatched', 'ready', 'picking', 'preparing', 'approved')
                    ");
                    $updateOrder->execute([
                        $orderStatus,
                        $amountAccepted, $amountAccepted,
                        $amountAccepted, $amountAccepted,
                        $amountAccepted, $amountAccepted,
                        $current['order_id']
                    ]);
                }

                $db->commit();
                Response::success([
                    'id' => (int)$id,
                    'status' => 'delivered',
                    'qty_accepted' => $qtyAccepted,
                    'qty_shipped' => $qtyShipped,
                    'amount_accepted' => $amountAccepted,
                    'order_status' => $isPartial ? 'partially_accepted' : 'delivered',
                    'is_partial' => $isPartial
                ], $isPartial
                    ? 'Delivery confirmed as partially accepted (returns applied to billing).'
                    : 'Delivery confirmed — full acceptance.');
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'cancel':
            if (in_array($current['status'], ['delivered', 'cancelled'])) {
                Response::error('Cannot cancel DR in current status', 400);
            }
            
            $stmt = $db->prepare("UPDATE delivery_receipts SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$id]);
            
            Response::success(null, 'Delivery receipt cancelled');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
