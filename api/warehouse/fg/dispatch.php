<?php
/**
 * Highland Fresh System - Warehouse FG Dispatch API
 * 
 * Barcode-based dispatch operations with FIFO enforcement
 * 
 * GET - Lookup barcode, check FIFO, dispatch history
 * POST - Release items, bulk release
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

// Require Warehouse FG role
$currentUser = Auth::requireRole(['warehouse_fg', 'general_manager']);

$action = getParam('action', 'history');

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
    error_log("Dispatch API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'history':
            $fromDate = getParam('from_date');
            $toDate = getParam('to_date');
            $limit = getParam('limit', 50);
            
            $sql = "
                SELECT 
                    dl.*,
                    fgi.batch_code,
                    p.product_name,
                    dr.dr_number,
                    u.first_name as released_by_name
                FROM fg_dispatch_log dl
                LEFT JOIN finished_goods_inventory fgi ON dl.inventory_id = fgi.id
                LEFT JOIN products p ON fgi.product_id = p.id
                LEFT JOIN delivery_receipts dr ON dl.dr_id = dr.id
                LEFT JOIN users u ON dl.released_by = u.id
                WHERE 1=1
            ";
            $params = [];
            
            if ($fromDate) {
                $sql .= " AND DATE(dl.released_at) >= ?";
                $params[] = $fromDate;
            }
            
            if ($toDate) {
                $sql .= " AND DATE(dl.released_at) <= ?";
                $params[] = $toDate;
            }
            
            $sql .= " ORDER BY dl.released_at DESC LIMIT ?";
            $params[] = (int)$limit;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $history = $stmt->fetchAll();
            
            Response::success($history, 'Dispatch history retrieved');
            break;
            
        case 'lookup_barcode':
            $barcode = getParam('barcode');
            if (!$barcode) {
                Response::error('Barcode required', 400);
            }
            
            // Look up inventory by barcode or batch_code
            $stmt = $db->prepare("
                SELECT 
                    fgi.*,
                    p.product_name,
                    p.variant,
                    p.unit_size as size_value,
                    p.unit_measure as size_unit,
                    p.product_code as product_sku,
                    cl.chiller_name,
                    pb.batch_code,
                    pb.manufacturing_date,
                    pb.expiry_date as batch_expiry_date
                FROM finished_goods_inventory fgi
                LEFT JOIN products p ON fgi.product_id = p.id
                LEFT JOIN chiller_locations cl ON fgi.chiller_id = cl.id
                LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                WHERE (fgi.barcode = ? OR pb.barcode = ? OR pb.batch_code = ?)
                AND fgi.quantity_available > 0
                LIMIT 1
            ");
            $stmt->execute([$barcode, $barcode, $barcode]);
            $item = $stmt->fetch();
            
            if (!$item) {
                Response::error('Barcode not found in inventory', 404);
            }
            
            Response::success($item, 'Item found');
            break;
            
        case 'check_fifo':
            $inventoryId = getParam('inventory_id');
            $productId = getParam('product_id');
            
            if (!$inventoryId || !$productId) {
                Response::error('inventory_id and product_id required', 400);
            }
            
            // Get the expiry date of the selected item
            $selectedStmt = $db->prepare("
                SELECT fgi.id, pb.expiry_date
                FROM finished_goods_inventory fgi
                LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                WHERE fgi.id = ?
            ");
            $selectedStmt->execute([$inventoryId]);
            $selected = $selectedStmt->fetch();
            
            if (!$selected) {
                Response::error('Inventory item not found', 404);
            }
            
            // Check if there are older batches
            $olderStmt = $db->prepare("
                SELECT fgi.id, fgi.batch_code, pb.expiry_date
                FROM finished_goods_inventory fgi
                LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                WHERE fgi.product_id = ?
                AND fgi.quantity > 0
                AND fgi.id != ?
                AND pb.expiry_date < ?
                ORDER BY pb.expiry_date ASC
                LIMIT 1
            ");
            $olderStmt->execute([$productId, $inventoryId, $selected['expiry_date']]);
            $older = $olderStmt->fetch();
            
            if ($older) {
                Response::success([
                    'is_compliant' => false,
                    'oldest_batch' => $older['batch_code'],
                    'oldest_expiry' => $older['expiry_date'],
                    'selected_expiry' => $selected['expiry_date']
                ], 'FIFO violation detected');
            } else {
                Response::success([
                    'is_compliant' => true
                ], 'FIFO compliant');
            }
            break;
            
        case 'release_summary':
            $drId = getParam('dr_id');
            if (!$drId) {
                Response::error('DR ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT 
                    dl.*,
                    fgi.batch_code,
                    p.product_name
                FROM fg_dispatch_log dl
                LEFT JOIN finished_goods_inventory fgi ON dl.inventory_id = fgi.id
                LEFT JOIN products p ON fgi.product_id = p.id
                WHERE dl.dr_id = ?
                ORDER BY dl.released_at ASC
            ");
            $stmt->execute([$drId]);
            $releases = $stmt->fetchAll();
            
            $totalItems = count($releases);
            $totalQuantity = array_sum(array_column($releases, 'quantity'));
            
            Response::success([
                'dr_id' => $drId,
                'total_items' => $totalItems,
                'total_quantity' => $totalQuantity,
                'releases' => $releases
            ], 'Release summary');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

function handlePost($db, $action, $currentUser) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'release':
            // Single item release
            $required = ['inventory_id', 'quantity'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    Response::error("$field is required", 400);
                }
            }
            
            $inventoryId = $data['inventory_id'];
            $quantity = (int)$data['quantity'];
            $drId = $data['dr_id'] ?? null;
            $barcode = $data['barcode'] ?? null;
            
            // Check inventory availability
            $invStmt = $db->prepare("SELECT * FROM finished_goods_inventory WHERE id = ? FOR UPDATE");
            
            $db->beginTransaction();
            
            try {
                $invStmt->execute([$inventoryId]);
                $inventory = $invStmt->fetch();
                
                if (!$inventory) {
                    throw new Exception('Inventory item not found');
                }
                
                if ($inventory['quantity'] < $quantity) {
                    throw new Exception('Insufficient quantity available');
                }
                
                // Deduct from inventory
                $updateStmt = $db->prepare("
                    UPDATE finished_goods_inventory 
                    SET quantity = quantity - ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$quantity, $inventoryId]);
                
                // Generate dispatch code
                $dispatchCode = 'DSP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Log the dispatch
                $logStmt = $db->prepare("
                    INSERT INTO fg_dispatch_log 
                    (dispatch_code, inventory_id, product_id, batch_code, dr_id, quantity_released, released_by, released_at, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ");
                $logStmt->execute([
                    $dispatchCode,
                    $inventoryId,
                    $inventory['product_id'],
                    $inventory['batch_code'] ?? null,
                    $drId,
                    $quantity,
                    $currentUser['user_id'],
                    $data['notes'] ?? null
                ]);
                
                // Log transaction
                $txnStmt = $db->prepare("
                    INSERT INTO fg_inventory_transactions 
                    (inventory_id, transaction_type, quantity, reference_type, reference_id, created_by)
                    VALUES (?, 'dispatch', ?, 'delivery_receipt', ?, ?)
                ");
                $txnStmt->execute([$inventoryId, -$quantity, $drId, $currentUser['user_id']]);
                
                // If DR provided, add to DR items
                if ($drId) {
                    $driStmt = $db->prepare("
                        INSERT INTO delivery_receipt_items 
                        (delivery_receipt_id, product_id, batch_id, quantity_ordered, quantity_packed)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $driStmt->execute([
                        $drId,
                        $inventory['product_id'],
                        $inventory['batch_id'] ?? null,
                        $quantity,
                        $quantity
                    ]);
                }
                
                $db->commit();
                
                Response::success([
                    'released_quantity' => $quantity,
                    'remaining_quantity' => $inventory['quantity'] - $quantity
                ], 'Item released successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'bulk_release':
            // Multiple items release
            $items = $data['items'] ?? [];
            $drId = $data['dr_id'] ?? null;
            
            if (empty($items)) {
                Response::error('No items to release', 400);
            }
            
            $db->beginTransaction();
            
            try {
                $released = [];
                
                foreach ($items as $item) {
                    $inventoryId = $item['inventory_id'];
                    $quantity = (int)$item['quantity'];
                    $barcode = $item['barcode'] ?? null;
                    
                    // Check and deduct inventory
                    $invStmt = $db->prepare("SELECT * FROM finished_goods_inventory WHERE id = ? FOR UPDATE");
                    $invStmt->execute([$inventoryId]);
                    $inventory = $invStmt->fetch();
                    
                    if (!$inventory || $inventory['quantity'] < $quantity) {
                        throw new Exception("Insufficient quantity for item $inventoryId");
                    }
                    
                    // Deduct
                    $updateStmt = $db->prepare("
                        UPDATE finished_goods_inventory 
                        SET quantity = quantity - ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$quantity, $inventoryId]);
                    
                    // Log dispatch
                    $logStmt = $db->prepare("
                        INSERT INTO fg_dispatch_log 
                        (inventory_id, dr_id, quantity, barcode_scanned, released_by, released_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $logStmt->execute([$inventoryId, $drId, $quantity, $barcode, $currentUser['user_id']]);
                    
                    // Log transaction
                    $txnStmt = $db->prepare("
                        INSERT INTO fg_inventory_transactions 
                        (inventory_id, transaction_type, quantity, reference_type, reference_id, created_by)
                        VALUES (?, 'dispatch', ?, 'delivery_receipt', ?, ?)
                    ");
                    $txnStmt->execute([$inventoryId, -$quantity, $drId, $currentUser['user_id']]);
                    
                    // Add to DR items
                    if ($drId) {
                        $driStmt = $db->prepare("
                            INSERT INTO delivery_receipt_items 
                            (dr_id, product_id, batch_code, quantity, inventory_id)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $driStmt->execute([
                            $drId,
                            $inventory['product_id'],
                            $inventory['batch_code'],
                            $quantity,
                            $inventoryId
                        ]);
                    }
                    
                    $released[] = [
                        'inventory_id' => $inventoryId,
                        'quantity' => $quantity
                    ];
                }
                
                // Update DR status if provided
                if ($drId) {
                    $drUpdateStmt = $db->prepare("
                        UPDATE delivery_receipts 
                        SET status = 'preparing', updated_at = NOW()
                        WHERE id = ? AND status IN ('draft', 'pending')
                    ");
                    $drUpdateStmt->execute([$drId]);
                }
                
                $db->commit();
                
                Response::success([
                    'released_count' => count($released),
                    'released_items' => $released
                ], 'Items released successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
