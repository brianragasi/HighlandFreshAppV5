<?php
/**
 * Highland Fresh System - Delivery Returns API
 *
 * Handles driver returns after delivery:
 *  - Resellable → restock FG inventory (correct packs/loose)
 *  - Damaged/spoiled → disposal log (never back into saleable stock)
 *  - QC hold → quarantine disposition (no silent restock)
 *  - Reconcile DR lines, sales order status, fulfilled qty, and billing totals
 *
 * @package HighlandFresh
 * @version 4.1
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
require_once __DIR__ . '/inventory_helpers.php';

$currentUser = Auth::requireRole(['warehouse_fg', 'general_manager']);

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
    error_log("Delivery Returns API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            $drId = getParam('dr_id');
            if (!$drId) {
                Response::error('DR ID required', 400);
            }

            $stmt = $db->prepare("
                SELECT
                    dr.*,
                    p.product_name,
                    pb.batch_code,
                    u.first_name as created_by_name
                FROM delivery_returns dr
                LEFT JOIN products p ON dr.product_id = p.id
                LEFT JOIN production_batches pb ON dr.batch_id = pb.id
                LEFT JOIN users u ON dr.created_by = u.id
                WHERE dr.delivery_receipt_id = ?
                ORDER BY dr.created_at DESC
            ");
            $stmt->execute([$drId]);
            Response::success($stmt->fetchAll(), 'Returns retrieved');
            break;

        case 'summary':
            $drId = getParam('dr_id');
            if (!$drId) {
                Response::error('DR ID required', 400);
            }

            $stmt = $db->prepare("
                SELECT
                    COUNT(*) as total_returns,
                    SUM(quantity_returned) as total_quantity_returned,
                    GROUP_CONCAT(DISTINCT return_reason) as reasons
                FROM delivery_returns
                WHERE delivery_receipt_id = ?
            ");
            $stmt->execute([$drId]);
            Response::success($stmt->fetch(), 'Return summary retrieved');
            break;

        default:
            Response::error('Invalid action', 400);
    }
}

function handlePost($db, $action, $currentUser) {
    $data = getRequestBody();

    switch ($action) {
        case 'record_returns':
            recordReturnsAndReconcile($db, $data, $currentUser);
            break;

        case 'no_returns':
            markNoReturnsAndReconcile($db, $data, $currentUser);
            break;

        default:
            Response::error('Invalid action', 400);
    }
}

function handlePut($db, $action, $currentUser) {
    $data = getRequestBody();

    switch ($action) {
        case 'update_disposition':
            $id = $data['id'] ?? null;
            $disposition = $data['disposition'] ?? null;

            if (!$id || !$disposition) {
                Response::error('Return ID and disposition required', 400);
            }

            $stmt = $db->prepare("
                UPDATE delivery_returns
                SET disposition = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$disposition, $id]);

            Response::success(null, 'Disposition updated');
            break;

        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Map return reason → default condition + disposition (stock destination).
 * Matches frontend RETURN_REASON_ROUTING; backend is authoritative.
 */
function mapReasonToRouting($reason) {
    $map = [
        // Spoilage / damage → disposals table (never saleable inventory)
        'damaged_in_transit' => ['condition' => 'damaged', 'disposition' => 'dispose', 'route' => 'disposal'],
        'quality_issue' => ['condition' => 'damaged', 'disposition' => 'dispose', 'route' => 'disposal'],
        'expired_near_expiry' => ['condition' => 'expired', 'disposition' => 'dispose', 'route' => 'disposal'],
        // Customer refused / logistics → restock FG inventory
        'customer_rejection' => ['condition' => 'resellable', 'disposition' => 'return_to_inventory', 'route' => 'inventory'],
        'wrong_order' => ['condition' => 'resellable', 'disposition' => 'return_to_inventory', 'route' => 'inventory'],
        'customer_not_available' => ['condition' => 'resellable', 'disposition' => 'return_to_inventory', 'route' => 'inventory'],
        'wrong_address' => ['condition' => 'resellable', 'disposition' => 'return_to_inventory', 'route' => 'inventory'],
        // Ambiguous → QC hold, not restocked
        'other' => ['condition' => 'qc_hold', 'disposition' => 'qc_review', 'route' => 'qc'],
    ];
    return $map[$reason] ?? null;
}

/**
 * Resolve final condition / disposition / is_resellable.
 * Priority: hard condition rules > reason map > UI disposition > UI condition.
 */
function resolveReturnRouting(array $return) {
    $reason = $return['return_reason'] ?? $return['reason'] ?? 'other';
    $condition = $return['condition'] ?? null;
    $disposition = $return['disposition'] ?? null;

    $fromReason = mapReasonToRouting($reason);

    // If UI sent incomplete data, seed from reason
    if ($fromReason) {
        if ($condition === null || $condition === '') {
            $condition = $fromReason['condition'];
        }
        if ($disposition === null || $disposition === '') {
            $disposition = $fromReason['disposition'];
        }
        // Spoilage reasons always force dispose even if UI said restock
        if ($fromReason['route'] === 'disposal') {
            $condition = $fromReason['condition'];
            $disposition = 'dispose';
        }
    }

    $condition = $condition ?: 'resellable';

    if (!$disposition) {
        if ($condition === 'resellable') {
            $disposition = 'return_to_inventory';
        } elseif ($condition === 'qc_hold') {
            $disposition = 'qc_review';
        } else {
            $disposition = 'dispose';
        }
    }

    // Hard rules: damaged/expired/spoiled never restock into saleable FG
    if (in_array($condition, ['damaged', 'expired', 'spoiled', 'contaminated'], true)) {
        $disposition = 'dispose';
    }
    if ($disposition === 'qc_review') {
        $condition = $condition === 'resellable' ? 'qc_hold' : $condition;
    }

    $isResellable = ($disposition === 'return_to_inventory' && $condition === 'resellable');

    return [
        'condition' => $condition,
        'disposition' => $disposition,
        'is_resellable' => $isResellable,
        'return_reason' => $reason,
        'route' => $isResellable ? 'inventory' : ($disposition === 'qc_review' ? 'qc' : 'disposal'),
    ];
}

/**
 * Shipped qty for a DR line (packed/picked may differ by flow).
 */
function driShippedQty(array $line) {
    return (int)max(
        (int)($line['quantity_packed'] ?? 0),
        (int)($line['quantity_picked'] ?? 0),
        0
    );
}

function recordReturnsAndReconcile(PDO $db, array $data, array $currentUser) {
    $drId = $data['dr_id'] ?? null;
    $returns = $data['returns'] ?? [];

    if (!$drId || empty($returns)) {
        Response::error('DR ID and returns data required', 400);
    }

    $db->beginTransaction();

    try {
        $drCheck = $db->prepare("SELECT * FROM delivery_receipts WHERE id = ? FOR UPDATE");
        $drCheck->execute([$drId]);
        $dr = $drCheck->fetch(PDO::FETCH_ASSOC);

        if (!$dr) {
            throw new Exception('Delivery receipt not found');
        }
        if ($dr['status'] !== 'dispatched') {
            throw new Exception('Can only record returns for dispatched deliveries');
        }
        if (!empty($dr['returns_processed'])) {
            throw new Exception('Returns already processed for this DR');
        }

        // Load DR lines keyed by id
        $linesStmt = $db->prepare("SELECT * FROM delivery_receipt_items WHERE delivery_receipt_id = ? FOR UPDATE");
        $linesStmt->execute([$drId]);
        $lines = [];
        foreach ($linesStmt->fetchAll(PDO::FETCH_ASSOC) as $line) {
            $lines[(int)$line['id']] = $line;
        }

        $restockLog = [];
        $disposalLog = [];
        $totalReturnedQty = 0;
        $totalCreditAmount = 0.0;

        // Accumulate returns per DR line (multiple batches theoretically)
        $returnedByLine = [];

        foreach ($returns as $return) {
            $drItemId = (int)($return['dr_item_id'] ?? 0);
            $qty = (int)($return['quantity_returned'] ?? 0);
            $productId = (int)($return['product_id'] ?? 0);

            if ($qty <= 0 || !$drItemId || !$productId) {
                throw new Exception('Each return requires dr_item_id, product_id, and quantity_returned > 0');
            }
            if (!isset($lines[$drItemId])) {
                throw new Exception("DR line #{$drItemId} not found on this delivery receipt");
            }

            $line = $lines[$drItemId];
            // Strict DB-backed dispatched qty (never trust client max alone)
            $shipped = driShippedQty($line);
            if ($shipped <= 0) {
                $shipped = (int)($line['quantity_ordered'] ?? 0);
            }
            if ($shipped <= 0) {
                throw new Exception(
                    "Cannot record return: no dispatched quantity on DR line #{$drItemId}."
                );
            }

            // HARD RULE: qty_returned <= qty_dispatched (including cumulative returns on same line)
            $already = $returnedByLine[$drItemId] ?? 0;
            if ($qty > $shipped) {
                throw new Exception(
                    "Cannot return {$qty} units: only {$shipped} were dispatched " .
                    "(product line #{$drItemId}). qty_returned must be ≤ qty_dispatched."
                );
            }
            if ($already + $qty > $shipped) {
                throw new Exception(
                    "Return qty {$qty} exceeds remaining shipped qty for product line #{$drItemId} " .
                    "(dispatched {$shipped}, already returning {$already})."
                );
            }
            $returnedByLine[$drItemId] = $already + $qty;

            // Require reason when returning
            $reason = $return['return_reason'] ?? $return['reason'] ?? '';
            if ($reason === '' || $reason === null) {
                throw new Exception('Return reason is required for each returned line.');
            }

            $routing = resolveReturnRouting($return);
            $unitPrice = (float)($line['unit_price'] ?? 0);
            $creditLine = $qty * $unitPrice;
            $totalReturnedQty += $qty;
            $totalCreditAmount += $creditLine;

            $batchId = !empty($return['batch_id'])
                ? (int)$return['batch_id']
                : (!empty($line['batch_id']) ? (int)$line['batch_id'] : null);

            // 1) Persist return row
            $stmt = $db->prepare("
                INSERT INTO delivery_returns
                (delivery_receipt_id, dr_item_id, product_id, batch_id, quantity_returned,
                 return_reason, `condition`, disposition, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $drId,
                $drItemId,
                $productId,
                $batchId,
                $qty,
                $return['return_reason'] ?? 'other',
                $routing['condition'],
                $routing['disposition'],
                $return['notes'] ?? null,
                $currentUser['user_id']
            ]);
            $returnId = (int)$db->lastInsertId();

            // 2) Update delivered qty on DR line
            $delivered = max(0, $shipped - $returnedByLine[$drItemId]);
            $updLine = $db->prepare("
                UPDATE delivery_receipt_items
                SET quantity_delivered = ?,
                    total_price = ? * COALESCE(unit_price, 0)
                WHERE id = ?
            ");
            $updLine->execute([$delivered, $delivered, $drItemId]);
            $lines[$drItemId]['quantity_delivered'] = $delivered;

            // 3) Inventory routing
            if ($routing['is_resellable'] && $routing['disposition'] === 'return_to_inventory') {
                $invId = fgInventoryFindRowForRestock(
                    $db,
                    $productId,
                    $batchId,
                    $line['inventory_id'] ?? null
                );
                if (!$invId) {
                    throw new Exception(
                        "Cannot restock product #{$productId}: no FG inventory row for batch. " .
                        "Transaction rolled back."
                    );
                }

                $restock = fgInventoryRestockBaseUnits($db, $invId, $qty);
                $restockLog[] = $restock;

                // Movement log (best-effort if table shape allows)
                try {
                    $txnCode = 'RTN-' . date('Ymd') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                    $txn = $db->prepare("
                        INSERT INTO fg_inventory_transactions
                        (transaction_code, inventory_id, product_id, transaction_type, quantity,
                         reference_type, reference_id, performed_by, reason)
                        VALUES (?, ?, ?, 'return', ?, 'delivery_return', ?, ?, ?)
                    ");
                    $txn->execute([
                        $txnCode,
                        $invId,
                        $productId,
                        $qty,
                        $returnId,
                        $currentUser['user_id'],
                        'Resellable delivery return restocked'
                    ]);
                } catch (Exception $logEx) {
                    // Non-fatal: restock itself is authoritative
                    error_log('FG return txn log skipped: ' . $logEx->getMessage());
                }
            } elseif ($routing['disposition'] === 'dispose'
                || in_array($routing['condition'], ['damaged', 'expired'], true)) {
                // Spoilage path — never restock saleable inventory
                $disposalLog[] = createDisposalFromReturn(
                    $db,
                    $returnId,
                    $drId,
                    $return,
                    $routing,
                    $productId,
                    $batchId,
                    $qty,
                    $unitPrice,
                    $currentUser
                );
            } elseif ($routing['disposition'] === 'qc_review') {
                // Hold out of saleable stock + open disposal/qc pending record
                $disposalLog[] = createDisposalFromReturn(
                    $db,
                    $returnId,
                    $drId,
                    $return,
                    array_merge($routing, ['condition' => 'qc_hold']),
                    $productId,
                    $batchId,
                    $qty,
                    $unitPrice,
                    $currentUser,
                    'qc_hold'
                );
            }
        }

        // Lines with no returns → full delivered = shipped
        foreach ($lines as $lineId => $line) {
            if (!isset($returnedByLine[$lineId])) {
                $shipped = driShippedQty($line);
                if ($shipped <= 0) {
                    $shipped = (int)($line['quantity_ordered'] ?? 0);
                }
                $db->prepare("
                    UPDATE delivery_receipt_items
                    SET quantity_delivered = ?,
                        total_price = ? * COALESCE(unit_price, 0)
                    WHERE id = ?
                ")->execute([$shipped, $shipped, $lineId]);
            }
        }

        // Flag DR returns processed
        $db->prepare("
            UPDATE delivery_receipts
            SET returns_processed = 1,
                returns_processed_at = NOW(),
                returns_processed_by = ?
            WHERE id = ?
        ")->execute([$currentUser['user_id'], $drId]);

        // Reconcile sales order + financials (still dispatched until Mark Delivered,
        // but set fulfilled qty / accepted totals now)
        $recon = reconcileOrderAfterReturns($db, $dr, $totalReturnedQty, $totalCreditAmount, $currentUser);

        $db->commit();

        Response::success([
            'dr_id' => (int)$drId,
            'returns_count' => count($returns),
            'total_qty_returned' => $totalReturnedQty,
            'credit_amount' => round($totalCreditAmount, 2),
            'restocks' => $restockLog,
            'disposals' => $disposalLog,
            'order_reconciliation' => $recon
        ], 'Returns recorded. Inventory routed and order/billing reconciled.', 201);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        Response::error($e->getMessage(), 400);
    }
}

function markNoReturnsAndReconcile(PDO $db, array $data, array $currentUser) {
    $drId = $data['dr_id'] ?? null;
    if (!$drId) {
        Response::error('DR ID required', 400);
    }

    $db->beginTransaction();
    try {
        $drCheck = $db->prepare("SELECT * FROM delivery_receipts WHERE id = ? FOR UPDATE");
        $drCheck->execute([$drId]);
        $dr = $drCheck->fetch(PDO::FETCH_ASSOC);

        if (!$dr || $dr['status'] !== 'dispatched') {
            throw new Exception('DR not found or not dispatched');
        }
        if (!empty($dr['returns_processed'])) {
            throw new Exception('Returns already processed for this DR');
        }

        // All lines fully delivered
        $db->prepare("
            UPDATE delivery_receipt_items
            SET quantity_delivered = GREATEST(
                    COALESCE(quantity_packed, 0),
                    COALESCE(quantity_picked, 0),
                    COALESCE(quantity_ordered, 0)
                ),
                total_price = GREATEST(
                    COALESCE(quantity_packed, 0),
                    COALESCE(quantity_picked, 0),
                    COALESCE(quantity_ordered, 0)
                ) * COALESCE(unit_price, 0)
            WHERE delivery_receipt_id = ?
        ")->execute([$drId]);

        $db->prepare("
            UPDATE delivery_receipts
            SET returns_processed = 1,
                returns_processed_at = NOW(),
                returns_processed_by = ?
            WHERE id = ?
        ")->execute([$currentUser['user_id'], $drId]);

        $recon = reconcileOrderAfterReturns($db, $dr, 0, 0.0, $currentUser);

        $db->commit();
        Response::success([
            'dr_id' => (int)$drId,
            'returns_count' => 0,
            'credit_amount' => 0,
            'order_reconciliation' => $recon
        ], 'Marked as no returns — full delivery accepted');
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        Response::error($e->getMessage(), 400);
    }
}

/**
 * Create disposal (spoilage) record — stock already left FG at pick time, so we only log.
 */
function createDisposalFromReturn(
    PDO $db,
    $returnId,
    $drId,
    array $return,
    array $routing,
    $productId,
    $batchId,
    $qty,
    $unitPrice,
    array $currentUser,
    $forceCategory = null
) {
    $prodStmt = $db->prepare("SELECT product_name, selling_price FROM products WHERE id = ?");
    $prodStmt->execute([$productId]);
    $product = $prodStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $batchRef = null;
    if ($batchId) {
        $batchStmt = $db->prepare("SELECT batch_code FROM production_batches WHERE id = ?");
        $batchStmt->execute([$batchId]);
        $batchRef = $batchStmt->fetchColumn() ?: null;
    }

    $categoryMap = [
        'damaged' => 'damaged',
        'expired' => 'expired',
        'spoiled' => 'spoiled',
        'contaminated' => 'contaminated',
        'qc_hold' => 'other',
        'resellable' => 'other'
    ];
    $category = $forceCategory
        ? ($categoryMap[$forceCategory] ?? 'other')
        : ($categoryMap[$routing['condition']] ?? 'other');

    $methodMap = [
        'damaged' => 'special_waste',
        'expired' => 'animal_feed',
        'spoiled' => 'animal_feed',
        'contaminated' => 'incinerate',
        'other' => 'other'
    ];
    $method = $methodMap[$category] ?? 'other';

    $unitCost = (float)($unitPrice ?: ($product['selling_price'] ?? 0));
    $totalValue = $qty * $unitCost;
    $disposalCode = generateDisposalCode($db);

    $dispStmt = $db->prepare("
        INSERT INTO disposals (
            disposal_code, source_type, source_id, source_reference,
            product_id, product_name, quantity, unit,
            unit_cost, total_value, disposal_category, disposal_reason,
            disposal_method, status, initiated_by, initiated_at, notes
        ) VALUES (
            ?, 'finished_goods', ?, ?, ?, ?, ?, 'pcs',
            ?, ?, ?, ?, ?, 'pending', ?, NOW(), ?
        )
    ");

    $reason = 'Delivery return: ' . ($return['return_reason'] ?? 'Unknown') .
        ' | condition=' . ($routing['condition'] ?? '') .
        ' | ' . ($return['notes'] ?? '');

    $dispStmt->execute([
        $disposalCode,
        $returnId,
        $batchRef,
        $productId,
        $product['product_name'] ?? 'Unknown Product',
        $qty,
        $unitCost,
        $totalValue,
        $category,
        $reason,
        $method,
        $currentUser['user_id'],
        'Auto-created from delivery return. DR ID: ' . $drId .
            ($forceCategory === 'qc_hold' ? ' (QC hold — not restocked)' : ' (spoilage — not restocked)')
    ]);

    return [
        'disposal_id' => (int)$db->lastInsertId(),
        'disposal_code' => $disposalCode,
        'quantity' => $qty,
        'total_value' => $totalValue,
        'category' => $category
    ];
}

/**
 * Reconcile linked sales order + invoice after returns verification.
 * Does NOT set final "delivered" — that remains Mark Delivered — but updates
 * fulfilled quantities, accepted totals, and credit so AR is correct.
 */
function reconcileOrderAfterReturns(PDO $db, array $dr, $totalReturnedQty, $totalCreditAmount, array $currentUser) {
    $drId = (int)$dr['id'];
    $orderId = !empty($dr['order_id']) ? (int)$dr['order_id'] : null;

    // Accepted goods value from DR lines
    $agg = $db->prepare("
        SELECT
            COALESCE(SUM(COALESCE(quantity_delivered, 0)), 0) AS qty_accepted,
            COALESCE(SUM(COALESCE(quantity_delivered, 0) * COALESCE(unit_price, 0)), 0) AS amount_accepted,
            COALESCE(SUM(
                GREATEST(COALESCE(quantity_packed,0), COALESCE(quantity_picked,0), COALESCE(quantity_ordered,0))
            ), 0) AS qty_shipped,
            COALESCE(SUM(
                GREATEST(COALESCE(quantity_packed,0), COALESCE(quantity_picked,0), COALESCE(quantity_ordered,0))
                * COALESCE(unit_price, 0)
            ), 0) AS amount_shipped
        FROM delivery_receipt_items
        WHERE delivery_receipt_id = ?
    ");
    $agg->execute([$drId]);
    $totals = $agg->fetch(PDO::FETCH_ASSOC);

    $qtyAccepted = (int)$totals['qty_accepted'];
    $amountAccepted = round((float)$totals['amount_accepted'], 2);
    $qtyShipped = (int)$totals['qty_shipped'];
    $amountShipped = round((float)$totals['amount_shipped'], 2);
    $credit = round(max(0, $amountShipped - $amountAccepted), 2);
    if ($totalCreditAmount > 0) {
        $credit = round(max($credit, (float)$totalCreditAmount), 2);
    }

    $isPartial = ($qtyAccepted < $qtyShipped) || ($credit > 0.009);

    // Update DR header amount to accepted goods only
    $db->prepare("
        UPDATE delivery_receipts
        SET total_amount = ?,
            payment_status = CASE
                WHEN COALESCE(amount_paid, 0) <= 0 THEN 'unpaid'
                WHEN COALESCE(amount_paid, 0) + 0.009 >= ? THEN 'paid'
                ELSE 'partial'
            END
        WHERE id = ?
    ")->execute([$amountAccepted, $amountAccepted, $drId]);

    $orderResult = null;
    if ($orderId) {
        $orderStmt = $db->prepare("SELECT * FROM sales_orders WHERE id = ? FOR UPDATE");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // Sync order item fulfilled qty from this DR's delivered lines by product
            $itemMap = $db->prepare("
                SELECT product_id,
                       SUM(COALESCE(quantity_delivered, 0)) AS qty_delivered
                FROM delivery_receipt_items
                WHERE delivery_receipt_id = ?
                GROUP BY product_id
            ");
            $itemMap->execute([$drId]);
            foreach ($itemMap->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $db->prepare("
                    UPDATE sales_order_items
                    SET quantity_fulfilled = LEAST(quantity_ordered, ?),
                        status = CASE
                            WHEN ? <= 0 THEN 'pending'
                            WHEN ? < quantity_ordered THEN 'partial'
                            ELSE 'fulfilled'
                        END,
                        updated_at = NOW()
                    WHERE order_id = ? AND product_id = ?
                ")->execute([
                    (int)$row['qty_delivered'],
                    (int)$row['qty_delivered'],
                    (int)$row['qty_delivered'],
                    $orderId,
                    (int)$row['product_id']
                ]);
            }

            // Financials: bill only accepted goods
            $originalTotal = (float)$order['total_amount'];
            $amountPaid = (float)($order['amount_paid'] ?? 0);
            $newTotal = $amountAccepted > 0 ? $amountAccepted : 0.0;
            // Prefer SO line recalculation if DR has no prices
            if ($newTotal <= 0) {
                $soAgg = $db->prepare("
                    SELECT COALESCE(SUM(quantity_fulfilled * unit_price), 0)
                    FROM sales_order_items WHERE order_id = ?
                ");
                $soAgg->execute([$orderId]);
                $newTotal = round((float)$soAgg->fetchColumn(), 2);
            }

            $newBalance = max(0, round($newTotal - $amountPaid, 2));
            $payStatus = 'unpaid';
            if ($amountPaid <= 0) {
                $payStatus = 'unpaid';
            } elseif ($newBalance <= 0.009) {
                $payStatus = 'paid';
            } else {
                $payStatus = 'partial';
            }

            // Status: keep operational path at dispatched until Mark Delivered;
            // stamp fulfillment outcome into notes + use partially_accepted only when deliver closes.
            // Here we store credit in discount_amount increase and note for accounting.
            $creditNote = $credit > 0
                ? sprintf(
                    "\n[Delivery credit %s] Returned goods credit ₱%s (shipped ₱%s → accepted ₱%s). DR %s.",
                    date('Y-m-d H:i'),
                    number_format($credit, 2),
                    number_format($amountShipped, 2),
                    number_format($amountAccepted, 2),
                    $dr['dr_number'] ?? $drId
                )
                : sprintf(
                    "\n[Delivery verified %s] Full acceptance. DR %s.",
                    date('Y-m-d H:i'),
                    $dr['dr_number'] ?? $drId
                );

            $db->prepare("
                UPDATE sales_orders
                SET total_amount = ?,
                    balance_due = ?,
                    payment_status = ?,
                    discount_amount = GREATEST(COALESCE(discount_amount, 0), ?),
                    notes = CONCAT(COALESCE(notes, ''), ?),
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $newTotal,
                $newBalance,
                $payStatus,
                $credit, // surface credit for finance reports
                $creditNote,
                $orderId
            ]);

            // Adjust linked active invoice if present
            $inv = $db->prepare("
                SELECT id, total_amount, amount_paid, balance_due
                FROM sales_invoices
                WHERE order_id = ? AND status = 'active'
                ORDER BY id DESC
                LIMIT 1
            ");
            $inv->execute([$orderId]);
            $invoice = $inv->fetch(PDO::FETCH_ASSOC);

            // Also try by dr_id
            if (!$invoice) {
                $inv2 = $db->prepare("
                    SELECT id, total_amount, amount_paid, balance_due
                    FROM sales_invoices
                    WHERE dr_id = ? AND status = 'active'
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $inv2->execute([$drId]);
                $invoice = $inv2->fetch(PDO::FETCH_ASSOC);
            }

            if ($invoice) {
                $invPaid = (float)($invoice['amount_paid'] ?? 0);
                $invBalance = max(0, round($newTotal - $invPaid, 2));
                $invPayStatus = $invPaid <= 0 ? 'unpaid' : ($invBalance <= 0.009 ? 'paid' : 'partial');
                $db->prepare("
                    UPDATE sales_invoices
                    SET total_amount = ?,
                        subtotal = ?,
                        balance_due = ?,
                        payment_status = ?,
                        notes = CONCAT(COALESCE(notes, ''), ?),
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([
                    $newTotal,
                    $newTotal,
                    $invBalance,
                    $invPayStatus,
                    $creditNote,
                    $invoice['id']
                ]);
            }

            $orderResult = [
                'order_id' => $orderId,
                'original_total' => $originalTotal,
                'accepted_total' => $newTotal,
                'credit_amount' => $credit,
                'balance_due' => $newBalance,
                'payment_status' => $payStatus,
                'is_partial' => $isPartial,
                'invoice_adjusted' => !empty($invoice)
            ];
        }
    }

    return [
        'qty_accepted' => $qtyAccepted,
        'qty_shipped' => $qtyShipped,
        'amount_accepted' => $amountAccepted,
        'amount_shipped' => $amountShipped,
        'credit_amount' => $credit,
        'is_partial' => $isPartial,
        'sales_order' => $orderResult
    ];
}

function generateDisposalCode($db) {
    $date = date('Ymd');
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM disposals
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $count = (int)$stmt->fetchColumn() + 1;
    return 'DSP-' . $date . '-' . str_pad((string)$count, 4, '0', STR_PAD_LEFT);
}
