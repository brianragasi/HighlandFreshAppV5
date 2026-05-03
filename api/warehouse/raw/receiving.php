<?php
/**
 * Highland Fresh System - Receiving Report API
 * 
 * Handles goods receiving from suppliers with rejection tracking
 * 
 * GET  - List pending POs to receive, get RR details, list receiving history
 * POST - Create receiving report with items (stock-in + rejection handling)
 * PUT  - Verify RR, update status
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

// Require Warehouse Raw, Purchaser, Finance, or GM role
$currentUser = Auth::requireRole(['warehouse_raw', 'purchaser', 'finance_officer', 'general_manager']);

$action = getParam('action', 'pending_pos');

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
    error_log("Receiving Report API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

/**
 * Handle GET requests
 */
function handleGet($db, $action) {
    switch ($action) {
        case 'pending_pos':
            getPendingPOs($db);
            break;
            
        case 'po_detail':
            getPODetailForReceiving($db);
            break;
            
        case 'list':
            getReceivingReports($db);
            break;
            
        case 'detail':
            getReceivingReportDetail($db);
            break;
            
        case 'history':
            getReceivingHistory($db);
            break;
            
        case 'rejections':
            getSupplierRejections($db);
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Get POs pending receiving
 */
function getPendingPOs($db) {
    $status = getParam('status', 'ordered');
    $search = getParam('search', '');
    
    $where = "po.status IN ('approved', 'ordered', 'partial_received')";
    $params = [];
    
    if ($search) {
        $where .= " AND (po.po_number LIKE ? OR s.supplier_name LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    $stmt = $db->prepare("
        SELECT 
            po.id,
            po.po_number,
            po.order_date,
            po.expected_delivery,
            po.total_amount,
            po.payment_terms,
            po.status,
            s.id as supplier_id,
            s.supplier_name,
            s.supplier_code,
            s.contact_person,
            s.phone,
            (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = po.id) as item_count,
            CASE 
                WHEN po.expected_delivery < CURDATE() AND po.status = 'ordered' THEN 1 
                ELSE 0 
            END as is_overdue,
            (SELECT SUM(quantity_received) FROM purchase_order_items WHERE po_id = po.id) as total_received
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        WHERE {$where}
        ORDER BY 
            is_overdue DESC,
            po.expected_delivery ASC,
            po.order_date ASC
    ");
    $stmt->execute($params);
    $pos = $stmt->fetchAll();
    
    Response::success($pos, 'Pending POs retrieved');
}

/**
 * Get PO detail for receiving form
 */
function getPODetailForReceiving($db) {
    $id = getParam('id');
    if (!$id) {
        Response::error('PO ID required', 400);
    }
    
    // Get PO details
    $stmt = $db->prepare("
        SELECT 
            po.*,
            s.supplier_name,
            s.supplier_code,
            s.contact_person,
            s.phone,
            s.address
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        WHERE po.id = ?
    ");
    $stmt->execute([$id]);
    $po = $stmt->fetch();
    
    if (!$po) {
        Response::error('Purchase order not found', 404);
    }
    
    // Get PO items with current prices
    $itemsStmt = $db->prepare("
        SELECT 
            poi.*,
            i.ingredient_name,
            i.unit_of_measure as ingredient_unit,
            i.current_stock as ingredient_current_stock,
            i.unit_cost as ingredient_unit_cost,
            m.item_name as mro_item_name,
            m.unit_of_measure as mro_unit,
            m.current_stock as mro_current_stock
        FROM purchase_order_items poi
        LEFT JOIN ingredients i ON poi.ingredient_id = i.id
        LEFT JOIN mro_items m ON poi.mro_item_id = m.id
        WHERE poi.po_id = ?
        ORDER BY poi.id ASC
    ");
    $itemsStmt->execute([$id]);
    $po['items'] = $itemsStmt->fetchAll();
    
    // Check if there's an existing RR for this PO
    $rrStmt = $db->prepare("
        SELECT * FROM receiving_reports 
        WHERE po_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $rrStmt->execute([$id]);
    $po['last_rr'] = $rrStmt->fetch();
    
    Response::success($po, 'PO details retrieved');
}

/**
 * Get list of receiving reports
 */
function getReceivingReports($db) {
    $status = getParam('status', '');
    $search = getParam('search', '');
    $date_from = getParam('date_from', '');
    $date_to = getParam('date_to', '');
    $page = max(1, (int) getParam('page', 1));
    $limit = min(50, max(10, (int) getParam('limit', 20)));
    $offset = ($page - 1) * $limit;
    
    $where = "1=1";
    $params = [];
    
    if ($status) {
        $where .= " AND rr.status = ?";
        $params[] = $status;
    }
    
    if ($search) {
        $where .= " AND (rr.rr_number LIKE ? OR po.po_number LIKE ? OR s.supplier_name LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($date_from) {
        $where .= " AND DATE(rr.received_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where .= " AND DATE(rr.received_at) <= ?";
        $params[] = $date_to;
    }
    
    // Count
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM receiving_reports rr
        JOIN purchase_orders po ON rr.po_id = po.id
        JOIN suppliers s ON rr.supplier_id = s.id
        WHERE {$where}
    ");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetch()['total'];
    
    // Data
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare("
        SELECT 
            rr.*,
            po.po_number,
            s.supplier_name,
            s.supplier_code,
            u1.full_name as received_by_name,
            u2.full_name as verified_by_name
        FROM receiving_reports rr
        JOIN purchase_orders po ON rr.po_id = po.id
        JOIN suppliers s ON rr.supplier_id = s.id
        JOIN users u1 ON rr.received_by = u1.id
        LEFT JOIN users u2 ON rr.verified_by = u2.id
        WHERE {$where}
        ORDER BY rr.received_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
    
    Response::paginated($reports, $total, $page, $limit, 'Receiving reports retrieved');
}

/**
 * Get single receiving report detail
 */
function getReceivingReportDetail($db) {
    $id = getParam('id');
    if (!$id) {
        Response::error('RR ID required', 400);
    }
    
    $stmt = $db->prepare("
        SELECT 
            rr.*,
            po.po_number,
            po.order_date,
            po.expected_delivery,
            po.total_amount as po_total,
            po.payment_terms,
            s.supplier_name,
            s.supplier_code,
            s.contact_person,
            s.phone,
            s.address,
            u1.full_name as received_by_name,
            u2.full_name as verified_by_name
        FROM receiving_reports rr
        JOIN purchase_orders po ON rr.po_id = po.id
        JOIN suppliers s ON rr.supplier_id = s.id
        JOIN users u1 ON rr.received_by = u1.id
        LEFT JOIN users u2 ON rr.verified_by = u2.id
        WHERE rr.id = ?
    ");
    $stmt->execute([$id]);
    $rr = $stmt->fetch();
    
    if (!$rr) {
        Response::error('Receiving report not found', 404);
    }
    
    // Get items
    $itemsStmt = $db->prepare("
        SELECT 
            rri.*,
            poi.unit_price as po_unit_price,
            i.ingredient_name,
            m.item_name as mro_item_name
        FROM receiving_report_items rri
        JOIN purchase_order_items poi ON rri.po_item_id = poi.id
        LEFT JOIN ingredients i ON rri.ingredient_id = i.id
        LEFT JOIN mro_items m ON rri.mro_item_id = m.id
        WHERE rri.rr_id = ?
        ORDER BY rri.id ASC
    ");
    $itemsStmt->execute([$id]);
    $rr['items'] = $itemsStmt->fetchAll();
    
    // Get rejections if any
    $rejStmt = $db->prepare("
        SELECT * FROM supplier_rejections WHERE rr_id = ?
    ");
    $rejStmt->execute([$id]);
    $rr['rejections'] = $rejStmt->fetchAll();
    
    Response::success($rr, 'Receiving report details retrieved');
}

/**
 * Get receiving history
 */
function getReceivingHistory($db) {
    $limit = min(50, max(10, (int) getParam('limit', 20)));
    
    $stmt = $db->query("
        SELECT 
            rr.id,
            rr.rr_number,
            rr.received_at,
            rr.status,
            rr.total_ordered,
            rr.total_received,
            rr.total_rejected,
            rr.verified_at,
            po.po_number,
            s.supplier_name,
            u1.full_name as received_by_name,
            u2.full_name as verified_by_name
        FROM receiving_reports rr
        JOIN purchase_orders po ON rr.po_id = po.id
        JOIN suppliers s ON rr.supplier_id = s.id
        JOIN users u1 ON rr.received_by = u1.id
        LEFT JOIN users u2 ON rr.verified_by = u2.id
        ORDER BY rr.received_at DESC
        LIMIT {$limit}
    ");
    $history = $stmt->fetchAll();
    
    Response::success($history, 'Receiving history retrieved');
}

/**
 * Get supplier rejections
 */
function getSupplierRejections($db) {
    $supplier_id = getParam('supplier_id');
    $status = getParam('status', '');
    
    $where = "1=1";
    $params = [];
    
    if ($supplier_id) {
        $where .= " AND sr.supplier_id = ?";
        $params[] = $supplier_id;
    }
    
    if ($status) {
        $where .= " AND sr.status = ?";
        $params[] = $status;
    }
    
    $stmt = $db->prepare("
        SELECT 
            sr.*,
            s.supplier_name,
            rr.rr_number,
            po.po_number
        FROM supplier_rejections sr
        JOIN suppliers s ON sr.supplier_id = s.id
        JOIN receiving_reports rr ON sr.rr_id = rr.id
        JOIN purchase_orders po ON rr.po_id = po.id
        WHERE {$where}
        ORDER BY sr.created_at DESC
    ");
    $stmt->execute($params);
    $rejections = $stmt->fetchAll();
    
    Response::success($rejections, 'Supplier rejections retrieved');
}

/**
 * Handle POST requests - Create receiving report
 */
function handlePost($db, $action, $currentUser) {
    switch ($action) {
        case 'create':
            createReceivingReport($db, $currentUser);
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Create receiving report with items
 */
function createReceivingReport($db, $currentUser) {
    $data = getRequestBody();
    
    // Validate required fields
    $poId = $data['po_id'] ?? null;
    $items = $data['items'] ?? [];
    
    if (!$poId) {
        Response::error('PO ID is required', 400);
    }
    
    if (empty($items)) {
        Response::error('At least one item is required', 400);
    }
    
    // Get PO details
    $poStmt = $db->prepare("SELECT * FROM purchase_orders WHERE id = ?");
    $poStmt->execute([$poId]);
    $po = $poStmt->fetch();
    
    if (!$po) {
        Response::error('Purchase order not found', 404);
    }
    
    if (!in_array($po['status'], ['approved', 'ordered', 'partial_received'])) {
        Response::error('This PO cannot be received (invalid status)', 400);
    }
    
    $db->beginTransaction();
    
    try {
        // Generate RR number
        $rrNumber = generateRRNumber($db);
        
        // Calculate totals
        $totalOrdered = 0;
        $totalReceived = 0;
        $totalRejected = 0;
        
        foreach ($items as $item) {
            $totalOrdered += (float) ($item['quantity_ordered'] ?? 0);
            $totalReceived += (float) ($item['quantity_received'] ?? 0);
            $totalRejected += (float) ($item['quantity_rejected'] ?? 0);
        }
        
        // Determine status
        $status = 'pending_verification';
        if ($totalRejected > 0) {
            $status = 'discrepancy';
        }
        
        // Create receiving report
        $rrStmt = $db->prepare("
            INSERT INTO receiving_reports 
            (rr_number, po_id, supplier_id, received_by, status, total_ordered, total_received, total_rejected, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $rrStmt->execute([
            $rrNumber,
            $poId,
            $po['supplier_id'],
            $currentUser['user_id'],
            $status,
            $totalOrdered,
            $totalReceived,
            $totalRejected,
            $data['notes'] ?? null
        ]);
        $rrId = $db->lastInsertId();
        
        // Process items
        foreach ($items as $item) {
            $qtyOrdered = (float) ($item['quantity_ordered'] ?? 0);
            $qtyReceived = (float) ($item['quantity_received'] ?? 0);
            $qtyRejected = (float) ($item['quantity_rejected'] ?? 0);
            
            // Create RR item
            $itemStmt = $db->prepare("
                INSERT INTO receiving_report_items 
                (rr_id, po_item_id, ingredient_id, mro_item_id, item_description, 
                 quantity_ordered, quantity_received, quantity_rejected, unit, unit_price,
                 rejection_reason, rejection_notes, batch_code, expiry_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $itemStmt->execute([
                $rrId,
                $item['po_item_id'],
                $item['ingredient_id'] ?? null,
                $item['mro_item_id'] ?? null,
                $item['item_description'],
                $qtyOrdered,
                $qtyReceived,
                $qtyRejected,
                $item['unit'],
                $item['unit_price'],
                $qtyRejected > 0 ? ($item['rejection_reason'] ?? 'other') : null,
                $qtyRejected > 0 ? ($item['rejection_notes'] ?? null) : null,
                $item['batch_code'] ?? null,
                $item['expiry_date'] ?? null
            ]);
            $rrItemId = $db->lastInsertId();
            
            // Stock in the received quantity
            if ($qtyReceived > 0) {
                stockInItem($db, $item, $qtyReceived, $poId, $rrId, $currentUser);
            }
            
            // Create rejection record if there's rejected quantity
            if ($qtyRejected > 0) {
                createRejectionRecord($db, $rrId, $rrItemId, $item, $qtyRejected, $currentUser);
            }
            
            // Update PO item received/rejected quantities
            $db->prepare("
                UPDATE purchase_order_items 
                SET quantity_received = quantity_received + ?,
                    quantity_rejected = quantity_rejected + ?
                WHERE id = ?
            ")->execute([$qtyReceived, $qtyRejected, $item['po_item_id']]);
        }
        
        // Update PO status
        $newStatus = $po['status'];
        if ($totalReceived < $totalOrdered) {
            $newStatus = 'partial_received';
        } else {
            $newStatus = 'received';
        }
        
        $db->prepare("
            UPDATE purchase_orders 
            SET status = ?, received_at = NOW(), updated_at = NOW() 
            WHERE id = ?
        ")->execute([$newStatus, $poId]);
        
        // Log audit
        logAudit($currentUser['user_id'], 'CREATE', 'receiving_reports', $rrId, null, [
            'rr_number' => $rrNumber,
            'po_id' => $poId,
            'total_received' => $totalReceived,
            'total_rejected' => $totalRejected
        ]);
        
        $db->commit();
        
        Response::success([
            'rr_id' => $rrId,
            'rr_number' => $rrNumber,
            'total_received' => $totalReceived,
            'total_rejected' => $totalRejected,
            'status' => $status
        ], 'Receiving report created successfully', 201);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Stock in an item
 */
function stockInItem($db, $item, $quantity, $poId, $rrId, $currentUser) {
    $ingredientId = $item['ingredient_id'] ?? null;
    $mroItemId = $item['mro_item_id'] ?? null;
    $unitPrice = (float) $item['unit_price'];
    
    if ($ingredientId) {
        // Get ingredient info
        $ingStmt = $db->prepare("SELECT * FROM ingredients WHERE id = ?");
        $ingStmt->execute([$ingredientId]);
        $ing = $ingStmt->fetch();
        
        if (!$ing) return;
        
        // Generate batch code
        $batchCode = 'IB-RR' . $rrId . '-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Calculate expiry
        $shelfLife = $ing['shelf_life_days'] ?? 365;
        $expiryDate = date('Y-m-d', strtotime("+{$shelfLife} days"));
        
        // Create batch
        $db->prepare("
            INSERT INTO ingredient_batches
            (batch_code, ingredient_id, po_id, rr_id, quantity, remaining_quantity, unit_cost,
             supplier_id, received_date, expiry_date, qc_status, status, received_by, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'approved', 'available', ?, ?)
        ")->execute([
            $batchCode,
            $ingredientId,
            $poId,
            $rrId,
            $quantity,
            $quantity,
            $unitPrice,
            $item['supplier_id'] ?? null,
            $expiryDate,
            $currentUser['user_id'],
            'Received via RR#' . $batchCode
        ]);
        $batchId = $db->lastInsertId();
        
        // Update ingredient stock
        $db->prepare("
            UPDATE ingredients 
            SET current_stock = current_stock + ?, 
                unit_cost = COALESCE(?, unit_cost),
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$quantity, $unitPrice, $ingredientId]);
        
        // Create inventory transaction
        $txCode = generateCode('TX');
        $db->prepare("
            INSERT INTO inventory_transactions 
            (transaction_code, transaction_type, item_type, item_id, batch_id,
             quantity, unit_of_measure, reference_type, reference_id,
             to_location, performed_by, reason)
            VALUES (?, 'po_receive', 'ingredient', ?, ?, ?, ?, 'receiving_report', ?, ?, ?, ?)
        ")->execute([
            $txCode,
            $ingredientId,
            $batchId,
            $quantity,
            $item['unit'],
            $rrId,
            $ing['storage_location'] ?? 'Warehouse Raw',
            $currentUser['user_id'],
            'Received via RR'
        ]);
        
    } elseif ($mroItemId) {
        // Get MRO info
        $mroStmt = $db->prepare("SELECT * FROM mro_items WHERE id = ?");
        $mroStmt->execute([$mroItemId]);
        $mro = $mroStmt->fetch();
        
        if (!$mro) return;
        
        // Generate batch code
        $batchCode = 'MRO-RR' . $rrId . '-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Create MRO inventory record
        $db->prepare("
            INSERT INTO mro_inventory
            (batch_code, mro_item_id, po_id, quantity, remaining_quantity, unit_cost,
             supplier_id, received_date, received_by, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'available', ?)
        ")->execute([
            $batchCode,
            $mroItemId,
            $poId,
            $quantity,
            $quantity,
            $unitPrice,
            $item['supplier_id'] ?? null,
            $currentUser['user_id'],
            'Received via RR'
        ]);
        
        // Update MRO stock
        $db->prepare("
            UPDATE mro_items 
            SET current_stock = current_stock + ?,
                unit_cost = COALESCE(?, unit_cost),
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$quantity, $unitPrice, $mroItemId]);
        
        // Create inventory transaction
        $txCode = generateCode('TX');
        $db->prepare("
            INSERT INTO inventory_transactions 
            (transaction_code, transaction_type, item_type, item_id,
             quantity, unit_of_measure, reference_type, reference_id,
             to_location, performed_by, reason)
            VALUES (?, 'po_receive', 'mro', ?, ?, ?, 'receiving_report', ?, ?, ?, ?)
        ")->execute([
            $txCode,
            $mroItemId,
            $quantity,
            $item['unit'],
            $rrId,
            $mro['storage_location'] ?? 'Warehouse Raw',
            $currentUser['user_id'],
            'Received via RR'
        ]);
    }
}

/**
 * Create rejection record for supplier tracking
 */
function createRejectionRecord($db, $rrId, $rrItemId, $item, $quantity, $currentUser) {
    $rejectionCode = 'REJ-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    $unitPrice = (float) $item['unit_price'];
    $totalValue = $quantity * $unitPrice;
    
    $db->prepare("
        INSERT INTO supplier_rejections
        (rejection_code, rr_id, rr_item_id, supplier_id, ingredient_id, mro_item_id,
         item_description, quantity, unit, unit_price, total_value,
         rejection_type, rejection_reason, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $rejectionCode,
        $rrId,
        $rrItemId,
        $item['supplier_id'] ?? null,
        $item['ingredient_id'] ?? null,
        $item['mro_item_id'] ?? null,
        $item['item_description'],
        $quantity,
        $item['unit'],
        $unitPrice,
        $totalValue,
        $item['rejection_reason'] ?? 'other',
        $item['rejection_notes'] ?? null,
        $currentUser['user_id']
    ]);
}

/**
 * Handle PUT requests
 */
function handlePut($db, $action, $currentUser) {
    switch ($action) {
        case 'verify':
            requireActionRole($currentUser, ['purchaser', 'general_manager'], 'Only Purchaser or GM can verify receiving reports');
            verifyReceivingReport($db, $currentUser);
            break;
            
        case 'update_payment':
            requireActionRole($currentUser, ['finance_officer', 'general_manager'], 'Only Finance or GM can update payment details');
            updatePaymentMetadata($db, $currentUser);
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Verify receiving report (Purchaser action)
 */
function verifyReceivingReport($db, $currentUser) {
    // Only purchaser or GM can verify
    if (!in_array($currentUser['role'], ['purchaser', 'general_manager'])) {
        Response::error('Only Purchaser or GM can verify receiving reports', 403);
    }
    
    $id = getParam('id');
    if (!$id) {
        Response::error('RR ID required', 400);
    }
    
    $data = getRequestBody();
    
    // Get RR
    $stmt = $db->prepare("SELECT * FROM receiving_reports WHERE id = ?");
    $stmt->execute([$id]);
    $rr = $stmt->fetch();
    
    if (!$rr) {
        Response::error('Receiving report not found', 404);
    }
    
    if ($rr['status'] !== 'pending_verification' && $rr['status'] !== 'discrepancy') {
        Response::error('This receiving report cannot be verified', 400);
    }
    
    // Update RR status
    $newStatus = 'verified';
    $notes = $rr['notes'];
    
    if (!empty($data['notes'])) {
        $notes = ($notes ? $notes . "\n" : '') . '[Verification] ' . $data['notes'];
    }
    
    $db->prepare("
        UPDATE receiving_reports 
        SET status = ?, verified_by = ?, verified_at = NOW(), notes = ?, updated_at = NOW()
        WHERE id = ?
    ")->execute([$newStatus, $currentUser['user_id'], $notes, $id]);
    
    // Adjust payable amount based on actual received
    if ($rr['total_rejected'] > 0) {
        adjustPayableForRejection($db, $rr);
    }
    
    // Log audit
    logAudit($currentUser['user_id'], 'VERIFY', 'receiving_reports', $id, 
        ['status' => $rr['status']], 
        ['status' => 'verified']
    );
    
    Response::success(null, 'Receiving report verified successfully');
}

/**
 * Adjust payable amount for rejected items
 */
function adjustPayableForRejection($db, $rr) {
    // Get rejections for this RR
    $stmt = $db->prepare("
        SELECT SUM(total_value) as total_rejection_value 
        FROM supplier_rejections 
        WHERE rr_id = ?
    ");
    $stmt->execute([$rr['id']]);
    $result = $stmt->fetch();
    
    $rejectionValue = (float) ($result['total_rejection_value'] ?? 0);
    
    if ($rejectionValue > 0) {
        // Update PO totals to reflect accepted quantities
        $db->prepare("
            UPDATE purchase_orders 
            SET subtotal = GREATEST(subtotal - ?, 0),
                total_amount = GREATEST(total_amount - ?, 0), 
                notes = CONCAT(COALESCE(notes, ''), '\n[Adjusted for rejections: -₱', ?, ']'),
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$rejectionValue, $rejectionValue, number_format($rejectionValue, 2), $rr['po_id']]);
    }
}

/**
 * Update payment metadata (Finance action)
 */
function updatePaymentMetadata($db, $currentUser) {
    // Only finance or GM can update payment
    if (!in_array($currentUser['role'], ['finance_officer', 'general_manager'])) {
        Response::error('Only Finance or GM can update payment details', 403);
    }
    
    $id = getParam('id');
    if (!$id) {
        Response::error('PO ID required', 400);
    }
    
    $data = getRequestBody();
    
    // Get PO
    $stmt = $db->prepare("SELECT * FROM purchase_orders WHERE id = ?");
    $stmt->execute([$id]);
    $po = $stmt->fetch();
    
    if (!$po) {
        Response::error('Purchase order not found', 404);
    }
    
    // Update payment metadata
    $updateFields = [];
    $params = [];
    
    $allowedFields = ['check_number', 'bank_name', 'payment_date', 'payment_reference', 'maturity_date', 'payment_status'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($updateFields)) {
        Response::error('No fields to update', 400);
    }
    
    $updateFields[] = "updated_at = NOW()";
    $params[] = $id;
    
    $db->prepare("
        UPDATE purchase_orders 
        SET " . implode(', ', $updateFields) . "
        WHERE id = ?
    ")->execute($params);
    
    $oldPaymentValues = [];
    $newPaymentValues = [];
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $oldPaymentValues[$field] = $po[$field] ?? null;
            $newPaymentValues[$field] = $data[$field];
        }
    }

    // Log audit
    logAudit($currentUser['user_id'], 'UPDATE_PAYMENT', 'purchase_orders', $id, $oldPaymentValues, $newPaymentValues);
    
    Response::success(null, 'Payment details updated successfully');
}

/**
 * Generate RR number
 */
function generateRRNumber($db) {
    $stmt = $db->query("SELECT rr_number FROM receiving_reports ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetch();
    
    if ($last) {
        preg_match('/RR-(\d{6})-(\d+)/', $last['rr_number'], $matches);
        $month = $matches[1] ?? '';
        $seq = ($matches[2] ?? 0) + 1;
        
        // Reset sequence if new month
        if ($month !== date('Ym')) {
            $seq = 1;
        }
    } else {
        $seq = 1;
    }
    
    return sprintf('RR-%s-%04d', date('Ym'), $seq);
}
