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
require_once __DIR__ . '/inventory_helpers.php';
require_once dirname(dirname(__DIR__)) . '/helpers/lookup_normalization.php';
require_once dirname(dirname(__DIR__)) . '/helpers/finished_goods_barcode.php';
require_once dirname(dirname(__DIR__)) . '/helpers/sellable_expiry_policy.php';

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
    $sellableExpiry = hfSellableExpirySql('fgi.expiry_date');

    switch ($action) {
        case 'history':
            $fromDate = getParam('from_date');
            $toDate = getParam('to_date');
            $limit = getParam('limit', 50);
            
            $sql = "
                SELECT 
                    dl.*,
                    pb.batch_code,
                    p.product_name,
                    dr.dr_number,
                    u.first_name as released_by_name
                FROM fg_dispatch_log dl
                LEFT JOIN finished_goods_inventory fgi ON dl.inventory_id = fgi.id
                LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
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
            $barcode = hfNormalizeBarcodeLookup(getParam('barcode'));
            if (!$barcode) {
                Response::error('Barcode required', 400);
            }
            
            // Check if this is a DR number (starts with DR-)
            if (preg_match('/^DR-/', $barcode)) {
                $drStmt = $db->prepare("
                    SELECT dr.*, c.name as customer_name, so.order_number
                    FROM delivery_receipts dr
                    LEFT JOIN customers c ON dr.customer_id = c.id
                    LEFT JOIN sales_orders so ON dr.order_id = so.id
                    WHERE dr.dr_number = ?
                ");
                $drStmt->execute([$barcode]);
                $dr = $drStmt->fetch();
                
                if ($dr) {
                    Response::success([
                        'type' => 'delivery_receipt',
                        'dr' => $dr,
                        'message' => 'This is a DR number. Please select it from the dropdown above, then scan product barcodes.'
                    ], 'Delivery Receipt found');
                } else {
                    Response::error('Delivery Receipt not found: ' . $barcode, 404);
                }
                break;
            }
            
            // SKU/product codes and PKG-{inventory id} identify one packaged SKU.
            // A production batch can legitimately contain several package sizes,
            // so batch matches must return every candidate instead of LIMIT 1.
            $pkgId = null;
            if (preg_match('/^PKG-(\d+)$/i', $barcode, $m)) {
                $pkgId = (int)$m[1];
            }
            $compactLabel = hfParseCompactFinishedGoodsLabel($barcode);
            $compactBatchId = (int) ($compactLabel['batch_id'] ?? 0);
            $compactProductId = (int) ($compactLabel['product_id'] ?? 0);

            $stmt = $db->prepare("
                SELECT
                    fgi.*,
                    p.product_name,
                    p.variant,
                    p.unit_size as size_value,
                    p.unit_measure as size_unit,
                    p.product_code as product_sku,
                    p.pieces_per_box,
                    cl.chiller_name,
                    COALESCE(pb.batch_code, CONCAT('PKG-', fgi.id)) as batch_code,
                    pb.manufacturing_date,
                    pb.expiry_date as batch_expiry_date
                FROM finished_goods_inventory fgi
                LEFT JOIN products p ON fgi.product_id = p.id
                LEFT JOIN chiller_locations cl ON fgi.chiller_id = cl.id
                LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                WHERE (
                    fgi.barcode = ? OR p.product_code = ? OR pb.barcode = ? OR pb.batch_code = ? OR fgi.id = ?
                    OR (? > 0 AND fgi.batch_id = ? AND fgi.product_id = ?)
                )
                  AND fgi.status = 'available'
                  AND {$sellableExpiry}
                  AND (
                      COALESCE(fgi.boxes_available, 0) > 0
                      OR COALESCE(fgi.pieces_available, 0) > 0
                      OR COALESCE(fgi.quantity_available, 0) > 0
                  )
                ORDER BY COALESCE(fgi.expiry_date, pb.expiry_date) ASC, fgi.id ASC
                LIMIT 50
            ");
            $stmt->execute([
                $barcode,
                $barcode,
                $barcode,
                $barcode,
                $pkgId ?? 0,
                $compactBatchId,
                $compactBatchId,
                $compactProductId,
            ]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($compactLabel) {
                // Do not allow an unrelated ordinary barcode match to win over
                // the exact batch and product carried by the compact label.
                $items = array_values(array_filter($items, static function (array $candidate) use ($compactLabel) {
                    return (int) ($candidate['batch_id'] ?? 0) === $compactLabel['batch_id']
                        && (int) ($candidate['product_id'] ?? 0) === $compactLabel['product_id'];
                }));
                foreach ($items as &$compactItem) {
                    $compactItem['serialized_unit_number'] = $compactLabel['sequence'];
                    $compactItem['scanned_barcode'] = $barcode;
                }
                unset($compactItem);
            }

            // QC prints one serialized label per finished unit. Its value adds
            // both the SKU token and unit sequence to the batch barcode, so it
            // cannot match the unsuffixed database values above. Resolve that
            // exact printed format back to one batch + SKU inventory row.
            if (!$items) {
                $serializedStmt = $db->prepare("
                    SELECT
                        fgi.*,
                        p.product_name,
                        p.variant,
                        p.unit_size as size_value,
                        p.unit_measure as size_unit,
                        p.product_code as product_sku,
                        p.pieces_per_box,
                        cl.chiller_name,
                        COALESCE(pb.batch_code, CONCAT('PKG-', fgi.id)) as batch_code,
                        pb.barcode as production_barcode,
                        pb.manufacturing_date,
                        pb.expiry_date as batch_expiry_date
                    FROM finished_goods_inventory fgi
                    JOIN products p ON fgi.product_id = p.id
                    JOIN production_batches pb ON fgi.batch_id = pb.id
                    LEFT JOIN chiller_locations cl ON fgi.chiller_id = cl.id
                    WHERE (
                        UPPER(?) LIKE CONCAT(REPLACE(UPPER(COALESCE(NULLIF(TRIM(pb.barcode), ''), pb.batch_code)), ' ', ''), '-%')
                        OR UPPER(?) LIKE CONCAT(REPLACE(UPPER(pb.batch_code), ' ', ''), '-%')
                    )
                      AND fgi.status = 'available'
                      AND {$sellableExpiry}
                      AND (
                          COALESCE(fgi.boxes_available, 0) > 0
                          OR COALESCE(fgi.pieces_available, 0) > 0
                          OR COALESCE(fgi.quantity_available, 0) > 0
                      )
                    ORDER BY COALESCE(fgi.expiry_date, pb.expiry_date) ASC, fgi.id ASC
                    LIMIT 50
                ");
                $serializedStmt->execute([$barcode, $barcode]);
                foreach ($serializedStmt->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
                    $unitSequence = hfMatchSerializedQcLabel(
                        $barcode,
                        $candidate['production_barcode'] ?? null,
                        $candidate['batch_code'] ?? null,
                        $candidate['product_sku'] ?? null,
                        (int) ($candidate['product_id'] ?? 0)
                    );
                    if ($unitSequence === null) {
                        continue;
                    }

                    $printedUnitCount = max(0, (int) ($candidate['quantity'] ?? 0));
                    if ($printedUnitCount > 0 && $unitSequence > $printedUnitCount) {
                        continue;
                    }

                    $candidate['serialized_unit_number'] = $unitSequence;
                    $candidate['scanned_barcode'] = $barcode;
                    $items = [$candidate];
                    break;
                }
            }

            if (!$items) {
                // A compact QC label can be perfectly readable while its stock
                // is intentionally unavailable for dispatch. Diagnose that
                // exact batch/SKU before falling back to the generic message so
                // phone users know whether the issue is receiving, stock, or
                // the seven-day QC/reprocessing window.
                if ($compactLabel) {
                    $diagnosticStmt = $db->prepare("
                        SELECT
                            pb.id AS batch_id,
                            pb.batch_code,
                            p.id AS product_id,
                            p.product_name,
                            fgi.id AS inventory_id,
                            fgi.status AS inventory_status,
                            fgi.expiry_date,
                            COALESCE(fgi.boxes_available, 0) AS boxes_available,
                            COALESCE(fgi.pieces_available, 0) AS pieces_available,
                            COALESCE(fgi.quantity_available, 0) AS quantity_available,
                            DATEDIFF(fgi.expiry_date, CURDATE()) AS days_until_expiry
                        FROM production_batches pb
                        JOIN products p ON p.id = ?
                        LEFT JOIN finished_goods_inventory fgi
                          ON fgi.batch_id = pb.id AND fgi.product_id = p.id
                        WHERE pb.id = ?
                        ORDER BY fgi.id DESC
                        LIMIT 1
                    ");
                    $diagnosticStmt->execute([$compactProductId, $compactBatchId]);
                    $diagnostic = $diagnosticStmt->fetch(PDO::FETCH_ASSOC);

                    if ($diagnostic && empty($diagnostic['inventory_id'])) {
                        Response::error(
                            'This QC label is valid, but its batch and SKU have not been received into Finished Goods yet.',
                            409
                        );
                    }

                    if ($diagnostic) {
                        $status = strtolower(trim((string) ($diagnostic['inventory_status'] ?? '')));
                        $availableUnits = (int) $diagnostic['quantity_available']
                            + (int) $diagnostic['boxes_available']
                            + (int) $diagnostic['pieces_available'];

                        if ($status !== 'available') {
                            Response::error(
                                'This label was recognized, but the Finished Goods stock is ' . ($status ?: 'not available') . ' and cannot be dispatched.',
                                409
                            );
                        }

                        if ($availableUnits <= 0) {
                            Response::error(
                                'This label was recognized, but no available stock remains for this batch and SKU.',
                                409
                            );
                        }

                        if (empty($diagnostic['expiry_date'])) {
                            Response::error(
                                'This label was recognized, but the Finished Goods expiry date is missing. Correct the receiving record before dispatch.',
                                409
                            );
                        }

                        $daysUntilExpiry = (int) $diagnostic['days_until_expiry'];
                        if ($daysUntilExpiry <= HF_NEAR_EXPIRY_DAYS) {
                            $expiryLabel = date('M j, Y', strtotime($diagnostic['expiry_date']));
                            if ($daysUntilExpiry < 0) {
                                $reason = "expired on {$expiryLabel}";
                            } elseif ($daysUntilExpiry === 0) {
                                $reason = "expires today ({$expiryLabel})";
                            } else {
                                $reason = "expires on {$expiryLabel} ({$daysUntilExpiry} day" . ($daysUntilExpiry === 1 ? '' : 's') . ' remaining)';
                            }
                            Response::error(
                                "This label was recognized, but the batch {$reason}. It is inside the 7-day QC/reprocessing window and cannot be dispatched.",
                                409
                            );
                        }
                    }
                }

                Response::error(
                    'Barcode is not dispatchable. Scan the full QC label and confirm the batch is received in Finished Goods with more than 7 days before expiry.',
                    404
                );
            }

            // Prefer a true inventory/SKU identifier. If several FIFO lots exist
            // for that SKU, the first expiring lot is the correct candidate.
            $normalizedBarcode = strtoupper($barcode);
            $specificItems = array_values(array_filter($items, static function (array $candidate) use ($normalizedBarcode, $pkgId) {
                return ($pkgId && (int)$candidate['id'] === $pkgId)
                    || strtoupper(trim((string)($candidate['barcode'] ?? ''))) === $normalizedBarcode
                    || strtoupper(trim((string)($candidate['product_sku'] ?? ''))) === $normalizedBarcode;
            }));
            if ($specificItems) {
                $item = $specificItems[0];
                $item['type'] = 'inventory_item';
                Response::success($item, 'SKU inventory found');
                break;
            }

            $productIds = array_values(array_unique(array_map(
                static fn(array $candidate) => (int)$candidate['product_id'],
                $items
            )));
            if (count($productIds) > 1) {
                foreach ($items as &$candidate) {
                    $candidate['type'] = 'inventory_item';
                }
                unset($candidate);
                Response::success([
                    'type' => 'inventory_choices',
                    'lookup_code' => $barcode,
                    'items' => $items,
                ], 'This production batch contains multiple packaged SKUs');
                break;
            }

            // Multiple lots of the same SKU are not ambiguous: use FIFO order.
            $item = $items[0];
            $item['type'] = 'inventory_item';
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
                  AND {$sellableExpiry}
            ");
            $selectedStmt->execute([$inventoryId]);
            $selected = $selectedStmt->fetch();
            
            if (!$selected) {
                Response::error('Inventory item not found', 404);
            }
            
            // Check if there are older (non-expired) batches
            $olderStmt = $db->prepare("
                SELECT fgi.id, pb.batch_code, pb.expiry_date
                FROM finished_goods_inventory fgi
                LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                WHERE fgi.product_id = ?
                AND (fgi.boxes_available > 0 OR fgi.pieces_available > 0)
                AND fgi.id != ?
                AND pb.expiry_date < ?
                AND {$sellableExpiry}
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
                    pb.batch_code,
                    p.product_name
                FROM fg_dispatch_log dl
                LEFT JOIN finished_goods_inventory fgi ON dl.inventory_id = fgi.id
                LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
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
    $sellableExpiry = hfSellableExpirySql('fgi.expiry_date');
    
    switch ($action) {
        case 'release':
            requireActionRole(
                $currentUser,
                ['warehouse_fg', 'general_manager'],
                'Only Warehouse FG or General Manager can release stock'
            );

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
            
            // Check inventory availability - include pieces_per_box from products table and batch_code from production_batches
            $invStmt = $db->prepare("
                SELECT fgi.*, COALESCE(p.pieces_per_box, 12) as pieces_per_box, pb.batch_code 
                FROM finished_goods_inventory fgi
                LEFT JOIN products p ON fgi.product_id = p.id
                LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                WHERE fgi.id = ?
                  AND fgi.status = 'available'
                  AND {$sellableExpiry}
                FOR UPDATE
            ");
            
            $db->beginTransaction();
            
            try {
                $invStmt->execute([$inventoryId]);
                $inventory = $invStmt->fetch();
                
                if (!$inventory) {
                    throw new Exception('Inventory is unavailable for dispatch. Batches with 7 days or less before expiry are handled by QC.');
                }
                
                // Effective base units (handles multi-unit + legacy columns)
                $piecesPerBox = max(1, (int)($inventory['pieces_per_box'] ?? 12));
                $boxesAvail = (int)($inventory['boxes_available'] ?? 0);
                $piecesAvail = (int)($inventory['pieces_available'] ?? 0);
                $totalAvailable = fgInventoryEffectiveBaseUnits($inventory, $piecesPerBox);
                $oldAuditValues = [
                    'boxes_available' => $boxesAvail,
                    'pieces_available' => $piecesAvail,
                    'quantity_available' => (int)($inventory['quantity_available'] ?? 0),
                    'total_available_pieces' => $totalAvailable
                ];
                
                if ($totalAvailable < $quantity) {
                    throw new Exception('Insufficient quantity available (have ' . $totalAvailable . ', need ' . $quantity . ')');
                }

                // Atomic base-unit deduction + pack/loose recompute
                $deductResult = fgInventoryDeductBaseUnits($db, $inventoryId, $quantity);
                $newBoxes = $deductResult['after_boxes'];
                $newPieces = $deductResult['after_loose'];
                $remaining = $deductResult['after_base'];
                $newAuditValues = [
                    'boxes_available' => (int)$newBoxes,
                    'pieces_available' => (int)$newPieces,
                    'quantity_available' => (int)$remaining,
                    'total_available_pieces' => (int)$remaining,
                    'released_quantity' => $quantity,
                    'dispatch_code' => null,
                    'dr_id' => $drId,
                    'barcode' => $barcode
                ];
                
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
                $newAuditValues['dispatch_code'] = $dispatchCode;
                
                // Log transaction
                $txnCode = 'TXN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $txnStmt = $db->prepare("
                    INSERT INTO fg_inventory_transactions 
                    (transaction_code, inventory_id, product_id, transaction_type, quantity, reference_type, reference_id, performed_by)
                    VALUES (?, ?, ?, 'sale', ?, 'delivery_receipt', ?, ?)
                ");
                $txnStmt->execute([$txnCode, $inventoryId, $inventory['product_id'], -$quantity, $drId, $currentUser['user_id']]);
                
                // If DR provided, update or add to DR items
                if ($drId) {
                    $drItemId = null;
                    // Check if item already exists in DR (from order creation)
                    $checkStmt = $db->prepare("
                        SELECT id, quantity_ordered, COALESCE(quantity_packed, 0) as quantity_packed
                        FROM delivery_receipt_items 
                        WHERE delivery_receipt_id = ? AND product_id = ?
                        LIMIT 1
                        FOR UPDATE
                    ");
                    $checkStmt->execute([$drId, $inventory['product_id']]);
                    $existingItem = $checkStmt->fetch();
                    
                    if ($existingItem) {
                        $orderedQty = (int)$existingItem['quantity_ordered'];
                        $alreadyPacked = (int)$existingItem['quantity_packed'];
                        $newPacked = $alreadyPacked + $quantity;

                        // Hard stop: never pack more than ordered
                        if ($newPacked > $orderedQty) {
                            throw new Exception(
                                "Cannot exceed ordered quantity (have {$alreadyPacked}, releasing {$quantity}, ordered {$orderedQty})."
                            );
                        }

                        // Accumulate packed quantity (do not overwrite prior batches)
                        $updateStmt = $db->prepare("
                            UPDATE delivery_receipt_items 
                            SET batch_id = ?, quantity_packed = ?, quantity_delivered = ?
                            WHERE id = ?
                        ");
                        $updateStmt->execute([
                            $inventory['batch_id'] ?? null,
                            $newPacked,
                            $newPacked,
                            $existingItem['id']
                        ]);
                        $drItemId = (int) $existingItem['id'];
                    } else {
                        // Ad-hoc DRs: first line sets ordered = released; still reject zero qty
                        $driStmt = $db->prepare("
                            INSERT INTO delivery_receipt_items 
                            (delivery_receipt_id, product_id, batch_id, quantity_ordered, quantity_packed, quantity_delivered)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $driStmt->execute([
                            $drId,
                            $inventory['product_id'],
                            $inventory['batch_id'] ?? null,
                            $quantity,
                            $quantity,
                            $quantity
                        ]);
                        $drItemId = (int) $db->lastInsertId();
                    }

                    fgDeliveryRecordAllocation(
                        $db,
                        $drId,
                        $drItemId,
                        $deductResult,
                        !empty($inventory['batch_id']) ? (int) $inventory['batch_id'] : null
                    );
                    
                    // Ready only when every line is exactly fully packed (not over)
                    $checkAllPickedStmt = $db->prepare("
                        SELECT 
                            COUNT(*) as total_items,
                            SUM(CASE WHEN COALESCE(quantity_packed, 0) = quantity_ordered THEN 1 ELSE 0 END) as picked_items,
                            SUM(CASE WHEN COALESCE(quantity_packed, 0) > quantity_ordered THEN 1 ELSE 0 END) as over_items
                        FROM delivery_receipt_items 
                        WHERE delivery_receipt_id = ?
                    ");
                    $checkAllPickedStmt->execute([$drId]);
                    $pickStatus = $checkAllPickedStmt->fetch();

                    if ((int)$pickStatus['over_items'] > 0) {
                        throw new Exception('Cannot exceed ordered quantity. Transaction aborted.');
                    }
                    
                    $allPicked = ($pickStatus['total_items'] > 0 && 
                                  (int)$pickStatus['picked_items'] === (int)$pickStatus['total_items']);
                    
                    if ($allPicked) {
                        $drUpdateStmt = $db->prepare("
                            UPDATE delivery_receipts 
                            SET status = 'ready', prepared_by = ?, prepared_at = NOW()
                            WHERE id = ? AND status IN ('draft', 'pending', 'preparing')
                        ");
                        $drUpdateStmt->execute([$currentUser['user_id'], $drId]);
                    }
                }
                
                $db->commit();

                logAudit(
                    $currentUser['user_id'],
                    'STOCK_RELEASE',
                    'finished_goods_inventory',
                    $inventoryId,
                    $oldAuditValues,
                    $newAuditValues
                );
                
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
            requireActionRole(
                $currentUser,
                ['warehouse_fg', 'general_manager'],
                'Only Warehouse FG or General Manager can release stock'
            );

            // Multiple items release
            $items = $data['items'] ?? [];
            $drId = $data['dr_id'] ?? null;
            
            if (empty($items)) {
                Response::error('No items to release', 400);
            }
            
            // If DR provided, get required products for validation (with ordered + already packed)
            $drRequiredProducts = [];
            $drOrderedQuantities = [];
            $drAlreadyPacked = [];
            if ($drId) {
                $drItemsStmt = $db->prepare("
                    SELECT dri.product_id, p.product_name, dri.quantity_ordered,
                           COALESCE(dri.quantity_packed, 0) as quantity_packed
                    FROM delivery_receipt_items dri 
                    LEFT JOIN products p ON dri.product_id = p.id
                    WHERE dri.delivery_receipt_id = ?
                ");
                $drItemsStmt->execute([$drId]);
                $drItems = $drItemsStmt->fetchAll();
                foreach ($drItems as $di) {
                    $pid = (int)$di['product_id'];
                    $drRequiredProducts[$pid] = $di['product_name'] ?? 'Unknown';
                    $drOrderedQuantities[$pid] = (int)($di['quantity_ordered'] ?? 0);
                    $drAlreadyPacked[$pid] = (int)($di['quantity_packed'] ?? 0);
                }
            }

            // Reject duplicate inventory_id rows in one bulk payload (unique bottle/batch lines)
            $seenBulkInventory = [];
            foreach ($items as $item) {
                $invId = (int)($item['inventory_id'] ?? 0);
                if ($invId <= 0) {
                    Response::error('Each item requires a valid inventory_id', 400);
                }
                if (isset($seenBulkInventory[$invId])) {
                    Response::error(
                        "Duplicate inventory row #{$invId} in release payload. Each unique bottle/batch may only appear once.",
                        400
                    );
                }
                $seenBulkInventory[$invId] = true;
            }
            
            $db->beginTransaction();
            
            try {
                $released = [];
                $releasedByProduct = []; // Track total released per product_id in this request
                $auditEntries = [];
                
                foreach ($items as $item) {
                    $inventoryId = $item['inventory_id'];
                    // Frontend sends total_pieces OR quantity
                    $quantity = (int)($item['total_pieces'] ?? $item['quantity'] ?? 0);
                    $barcode = $item['barcode'] ?? null;

                    if ($quantity <= 0) {
                        throw new Exception('Release quantity must be greater than zero');
                    }
                    
                    // Check and deduct inventory - include pieces_per_box from products table and batch_code from production_batches
                    $invStmt = $db->prepare("
                        SELECT fgi.*, COALESCE(p.pieces_per_box, 12) as pieces_per_box, pb.batch_code, p.product_name 
                        FROM finished_goods_inventory fgi 
                        LEFT JOIN products p ON fgi.product_id = p.id
                        LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                        WHERE fgi.id = ?
                          AND fgi.status = 'available'
                          AND {$sellableExpiry}
                        FOR UPDATE
                    ");
                    $invStmt->execute([$inventoryId]);
                    $inventory = $invStmt->fetch();

                    if (!$inventory) {
                        throw new Exception("Inventory item {$inventoryId} is unavailable for dispatch; near-expiry batches are handled by QC");
                    }
                    
                    // Validate product is on the DR
                    $productId = (int)$inventory['product_id'];
                    if ($drId && !empty($drRequiredProducts) && !isset($drRequiredProducts[$productId])) {
                        $productName = $inventory['product_name'] ?? 'Unknown Product';
                        $requiredNames = implode(', ', array_values($drRequiredProducts));
                        throw new Exception("Product '$productName' is NOT on this Delivery Receipt. Required products: $requiredNames");
                    }
                    
                    // Validate quantity doesn't exceed DR ordered amount (prior packed + this request)
                    if ($drId && isset($drOrderedQuantities[$productId])) {
                        if (!isset($releasedByProduct[$productId])) {
                            $releasedByProduct[$productId] = 0;
                        }
                        $alreadyPacked = $drAlreadyPacked[$productId] ?? 0;
                        $currentTotal = $alreadyPacked + $releasedByProduct[$productId] + $quantity;
                        $orderedQty = $drOrderedQuantities[$productId];
                        
                        if ($currentTotal > $orderedQty) {
                            $productName = $inventory['product_name'] ?? 'Unknown Product';
                            throw new Exception(
                                "Cannot exceed ordered quantity for '{$productName}': " .
                                "ordered {$orderedQty}, already packed {$alreadyPacked}, " .
                                "this release would total {$currentTotal}."
                            );
                        }
                        $releasedByProduct[$productId] += $quantity;
                    }
                    
                    // Effective base units (handles multi-unit + legacy columns)
                    $piecesPerBox = max(1, (int)($inventory['pieces_per_box'] ?? 12));
                    $boxesAvail = (int)($inventory['boxes_available'] ?? 0);
                    $piecesAvail = (int)($inventory['pieces_available'] ?? 0);
                    $totalAvailable = fgInventoryEffectiveBaseUnits($inventory, $piecesPerBox);
                    $oldAuditValues = [
                        'boxes_available' => $boxesAvail,
                        'pieces_available' => $piecesAvail,
                        'quantity_available' => (int)($inventory['quantity_available'] ?? 0),
                        'total_available_pieces' => $totalAvailable
                    ];
                    
                    if (!$inventory || $totalAvailable < $quantity) {
                        throw new Exception("Insufficient quantity for item $inventoryId (have $totalAvailable, need $quantity)");
                    }

                    // Atomic base-unit deduction + pack/loose recompute
                    $deductResult = fgInventoryDeductBaseUnits($db, $inventoryId, $quantity);
                    $newBoxes = $deductResult['after_boxes'];
                    $newPieces = $deductResult['after_loose'];
                    $remaining = $deductResult['after_base'];
                    $newAuditValues = [
                        'boxes_available' => (int)$newBoxes,
                        'pieces_available' => (int)$newPieces,
                        'quantity_available' => (int)$remaining,
                        'total_available_pieces' => (int)$remaining,
                        'released_quantity' => $quantity,
                        'dispatch_code' => null,
                        'dr_id' => $drId,
                        'barcode' => $barcode
                    ];
                    
                    // Log dispatch - generate dispatch code
                    $dispatchCode = 'DSP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $logStmt = $db->prepare("
                        INSERT INTO fg_dispatch_log 
                        (dispatch_code, inventory_id, product_id, batch_code, dr_id, quantity_released, released_by, released_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $logStmt->execute([$dispatchCode, $inventoryId, $inventory['product_id'], $inventory['batch_code'], $drId, $quantity, $currentUser['user_id']]);
                    $newAuditValues['dispatch_code'] = $dispatchCode;
                    
                    // Log transaction
                    $txnCode = 'TXN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $txnStmt = $db->prepare("
                        INSERT INTO fg_inventory_transactions 
                        (transaction_code, inventory_id, product_id, transaction_type, quantity, reference_type, reference_id, performed_by)
                        VALUES (?, ?, ?, 'sale', ?, 'delivery_receipt', ?, ?)
                    ");
                    $txnStmt->execute([$txnCode, $inventoryId, $inventory['product_id'], -$quantity, $drId, $currentUser['user_id']]);
                    
                    // Add to DR items (accumulate packed qty across batches for same product)
                    if ($drId) {
                        $drItemId = null;
                        $checkStmt = $db->prepare("
                            SELECT id, quantity_ordered, COALESCE(quantity_packed, 0) as quantity_packed
                            FROM delivery_receipt_items 
                            WHERE delivery_receipt_id = ? AND product_id = ?
                            LIMIT 1
                            FOR UPDATE
                        ");
                        $checkStmt->execute([$drId, $inventory['product_id']]);
                        $existingItem = $checkStmt->fetch();
                        
                        if ($existingItem) {
                            $newPacked = (int)$existingItem['quantity_packed'] + $quantity;
                            $orderedQty = (int)$existingItem['quantity_ordered'];
                            if ($newPacked > $orderedQty) {
                                throw new Exception(
                                    "Cannot exceed ordered quantity (would pack {$newPacked}, ordered {$orderedQty})."
                                );
                            }
                            $updateStmt = $db->prepare("
                                UPDATE delivery_receipt_items 
                                SET batch_id = ?, quantity_packed = ?, quantity_delivered = ?
                                WHERE id = ?
                            ");
                            $updateStmt->execute([
                                $inventory['batch_id'] ?? null,
                                $newPacked,
                                $newPacked,
                                $existingItem['id']
                            ]);
                            $drItemId = (int) $existingItem['id'];
                        } else {
                            // Insert new item (for ad-hoc DRs not from orders)
                            $driStmt = $db->prepare("
                                INSERT INTO delivery_receipt_items 
                                (delivery_receipt_id, product_id, batch_id, quantity_ordered, quantity_packed, quantity_delivered)
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            $driStmt->execute([
                                $drId,
                                $inventory['product_id'],
                                $inventory['batch_id'] ?? null,
                                $quantity,
                                $quantity,
                                $quantity
                            ]);
                            $drItemId = (int) $db->lastInsertId();
                        }

                        fgDeliveryRecordAllocation(
                            $db,
                            $drId,
                            $drItemId,
                            $deductResult,
                            !empty($inventory['batch_id']) ? (int) $inventory['batch_id'] : null
                        );
                    }
                    
                    $released[] = [
                        'inventory_id' => $inventoryId,
                        'quantity' => $quantity
                    ];
                    $auditEntries[] = [
                        'record_id' => $inventoryId,
                        'old' => $oldAuditValues,
                        'new' => $newAuditValues
                    ];
                }
                
                // Ready only when every line is exactly fully packed (never mark ready if over-packed)
                if ($drId) {
                    $checkAllPickedStmt = $db->prepare("
                        SELECT 
                            COUNT(*) as total_items,
                            SUM(CASE WHEN COALESCE(quantity_packed, 0) = quantity_ordered THEN 1 ELSE 0 END) as picked_items,
                            SUM(CASE WHEN COALESCE(quantity_packed, 0) > quantity_ordered THEN 1 ELSE 0 END) as over_items
                        FROM delivery_receipt_items 
                        WHERE delivery_receipt_id = ?
                    ");
                    $checkAllPickedStmt->execute([$drId]);
                    $pickStatus = $checkAllPickedStmt->fetch();

                    if ((int)$pickStatus['over_items'] > 0) {
                        throw new Exception('Cannot exceed ordered quantity. Transaction aborted.');
                    }
                    
                    $allPicked = ($pickStatus['total_items'] > 0 && 
                                  (int)$pickStatus['picked_items'] === (int)$pickStatus['total_items']);
                    
                    if ($allPicked) {
                        $drUpdateStmt = $db->prepare("
                            UPDATE delivery_receipts 
                            SET status = 'ready', prepared_by = ?, prepared_at = NOW()
                            WHERE id = ? AND status IN ('draft', 'pending', 'preparing')
                        ");
                        $drUpdateStmt->execute([$currentUser['user_id'], $drId]);
                    }
                }
                
                $db->commit();

                foreach ($auditEntries as $entry) {
                    logAudit(
                        $currentUser['user_id'],
                        'STOCK_RELEASE',
                        'finished_goods_inventory',
                        $entry['record_id'],
                        $entry['old'],
                        $entry['new']
                    );
                }
                
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
