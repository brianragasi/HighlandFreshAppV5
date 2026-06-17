<?php
/**
 * Highland Fresh System - Warehouse Raw Requisitions API
 *
 * Manages requisition fulfillment from production/maintenance.
 *
 * Workflow (V4.0 — no GM approval gate):
 *   1. Production creates a requisition   -> status 'pending' (see
 *      api/production/requisitions.php). Warehouse Raw sees it immediately.
 *   2. Warehouse Raw issues the materials -> status 'fulfilled' (all lines
 *      full) or 'partial' (some lines issued, top-up expected).
 *
 * GET    - List requisitions, get details
 * PUT    - Fulfill, partial fulfill, reject_individual_item
 *
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
require_once __DIR__ . '/ingredient_stock_helpers.php';

// Require Warehouse Raw role
$currentUser = Auth::requireRole(['warehouse_raw', 'general_manager', 'production_staff', 'maintenance_head']);

function ensureWarehouseRequisitionQuantityPrecision($db) {
    $precisionStmt = $db->prepare("
        SELECT COLUMN_NAME, COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'requisition_items'
          AND COLUMN_NAME IN ('requested_quantity', 'issued_quantity')
    ");
    $precisionStmt->execute();
    $columnTypes = [];
    foreach ($precisionStmt->fetchAll() as $column) {
        $columnTypes[$column['COLUMN_NAME']] = strtolower($column['COLUMN_TYPE']);
    }

    if (($columnTypes['requested_quantity'] ?? '') !== 'decimal(10,3)') {
        $db->exec("ALTER TABLE requisition_items MODIFY requested_quantity DECIMAL(10,3) NOT NULL");
    }
    if (($columnTypes['issued_quantity'] ?? '') !== 'decimal(10,3)') {
        $db->exec("ALTER TABLE requisition_items MODIFY issued_quantity DECIMAL(10,3) DEFAULT 0.000");
    }
}

try {
    $db = Database::getInstance()->getConnection();
    ensureWarehouseRequisitionQuantityPrecision($db);
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $currentUser);
            break;
        case 'PUT':
            handlePut($db, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Warehouse Raw Requisitions API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}

/**
 * Handle GET requests
 */
function handleGet($db, $currentUser) {
    $action = getParam('action', 'list');
    $id = getParam('id');
    
    switch ($action) {
        case 'list':
            // Get all requisitions for warehouse to fulfill
            $status = getParam('status');
            $department = getParam('department');
            $priority = getParam('priority');
            
            $sql = "
                SELECT
                    ir.*,
                    pmr.recipe_code as planned_recipe_code,
                    pmr.product_name as planned_product_name,
                    pmr.variant as planned_variant,
                    u.first_name as requested_by_first,
                    u.last_name as requested_by_last,
                    ua.first_name as approved_by_first,
                    ua.last_name as approved_by_last,
                    uf.first_name as fulfilled_by_first,
                    uf.last_name as fulfilled_by_last,
                    (SELECT COUNT(*) FROM requisition_items ri WHERE ri.requisition_id = ir.id) as item_count,
                    (SELECT COUNT(*) FROM requisition_items ri
                     WHERE ri.requisition_id = ir.id AND ri.status IN ('fulfilled', 'partial')) as processed_count,
                    (SELECT COUNT(*) FROM requisition_items ri
                     WHERE ri.requisition_id = ir.id AND ri.status = 'fulfilled') as fulfilled_count,
                    (SELECT COUNT(*) FROM requisition_stock_warnings w
                     WHERE w.requisition_id = ir.id AND w.decision = 'overridden') AS stock_warning_count
                FROM material_requisitions ir
                JOIN users u ON ir.requested_by = u.id
                LEFT JOIN master_recipes pmr ON ir.planned_recipe_id = pmr.id
                LEFT JOIN users ua ON ir.approved_by = ua.id
                LEFT JOIN users uf ON ir.fulfilled_by = uf.id
                WHERE 1=1
            ";
            $params = [];

            // V4.0 — no GM approval gate. Warehouse sees 'pending' requests
            // immediately and can act on them. 'approved' is kept in the
            // filter for legacy rows that pre-date this change.
            if (!$status) {
                if (in_array($currentUser['role'], ['warehouse_raw', 'general_manager'])) {
                    $sql .= " AND ir.status IN ('pending', 'approved', 'partial', 'fulfilled')";
                } else {
                    // Requesters see their own requisitions
                    $sql .= " AND ir.requested_by = ?";
                    $params[] = $currentUser['user_id'];
                }
            } else {
                $sql .= " AND ir.status = ?";
                $params[] = $status;
            }

            if ($department) {
                $sql .= " AND ir.department = ?";
                $params[] = $department;
            }

            if ($priority) {
                $sql .= " AND ir.priority = ?";
                $params[] = $priority;
            }

            // 'pending' is the actionable state now. Sort it first so
            // warehouse staff see the queue in the right order.
            $sql .= " ORDER BY
                CASE ir.status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'partial' THEN 3 ELSE 4 END,
                CASE ir.priority
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'normal' THEN 3
                    ELSE 4
                END,
                ir.needed_by_date ASC,
                ir.created_at ASC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $requisitions = $stmt->fetchAll();
            
            Response::success(['requisitions' => $requisitions], 'Requisitions retrieved successfully');
            break;
            
        case 'detail':
            if (!$id) {
                Response::error('Requisition ID is required', 400);
            }

            // Get requisition details
            $requisition = $db->prepare("
                SELECT
                    ir.*,
                    pmr.recipe_code as planned_recipe_code,
                    pmr.product_name as planned_product_name,
                    pmr.variant as planned_variant,
                    u.first_name as requested_by_first,
                    u.last_name as requested_by_last,
                    ua.first_name as approved_by_first,
                    ua.last_name as approved_by_last,
                    uf.first_name as fulfilled_by_first,
                    uf.last_name as fulfilled_by_last,
                    ovr.first_name as stock_override_first,
                    ovr.last_name as stock_override_last,
                    ovr.role as stock_override_role
                FROM material_requisitions ir
                JOIN users u ON ir.requested_by = u.id
                LEFT JOIN master_recipes pmr ON ir.planned_recipe_id = pmr.id
                LEFT JOIN users ua ON ir.approved_by = ua.id
                LEFT JOIN users uf ON ir.fulfilled_by = uf.id
                LEFT JOIN users ovr ON ir.stock_override_by = ovr.id
                WHERE ir.id = ?
            ");
            $requisition->execute([$id]);
            $requisitionData = $requisition->fetch();

            if (!$requisitionData) {
                Response::error('Requisition not found', 404);
            }

            // Get requisition items
            $items = $db->prepare("
                SELECT
                    ri.*,
                    uf.first_name as fulfilled_by_first,
                    uf.last_name as fulfilled_by_last,
                    CASE
                        -- Explicit raw_milk type OR item_name matches raw milk patterns
                        WHEN ri.item_type = 'raw_milk' OR LOWER(ri.item_name) IN ('raw', 'raw milk', 'fresh milk', 'carabao', 'cow milk', 'goat milk', 'whole milk')
                            OR (LOWER(ri.item_name) LIKE '%milk%' AND LOWER(ri.item_name) NOT LIKE '%powder%' AND LOWER(ri.item_name) NOT LIKE '%chocolate%')
                        THEN (SELECT COALESCE(SUM(remaining_liters), 0) FROM raw_milk_inventory WHERE status IN ('available', 'reserved') AND remaining_liters > 0 AND expiry_date >= CURDATE())
                        WHEN ri.item_type = 'ingredient' THEN (
                            SELECT GREATEST(
                                COALESCE(i.current_stock, 0),
                                COALESCE((
                                    SELECT SUM(ib.remaining_quantity)
                                    FROM ingredient_batches ib
                                    WHERE ib.ingredient_id = i.id
                                      AND ib.status IN ('available', 'partially_used')
                                      AND ib.remaining_quantity > 0
                                ), 0)
                            )
                            FROM ingredients i
                            WHERE i.id = ri.item_id
                        )
                        WHEN ri.item_type = 'mro' THEN (SELECT COALESCE(current_stock, 0) FROM mro_items WHERE id = ri.item_id)
                        ELSE 0
                    END as available_stock,
                    CASE
                        WHEN ri.item_type = 'raw_milk' OR LOWER(ri.item_name) IN ('raw', 'raw milk', 'fresh milk', 'carabao', 'cow milk', 'goat milk', 'whole milk')
                            OR (LOWER(ri.item_name) LIKE '%milk%' AND LOWER(ri.item_name) NOT LIKE '%powder%' AND LOWER(ri.item_name) NOT LIKE '%chocolate%')
                        THEN 'raw_milk'
                        ELSE ri.item_type
                    END as effective_item_type
                FROM requisition_items ri
                LEFT JOIN users uf ON ri.fulfilled_by = uf.id
                WHERE ri.requisition_id = ?
                ORDER BY ri.id ASC
            ");
            $items->execute([$id]);
            $itemList = $items->fetchAll();

            // Compute a per-line shortage on the fly from the available_stock
            // already returned by the items query above. Lets the warehouse UI
            // show "this line was self-acknowledged as short" without an extra
            // round trip to requisition_stock_warnings.
            foreach ($itemList as &$row) {
                $requested = (float) ($row['requested_quantity'] ?? 0);
                $available = (float) ($row['available_stock'] ?? 0);
                $row['stock_shortage'] = $requested > $available ? ($requested - $available) : 0.0;
                $row['stock_sufficient'] = $requested <= $available;
            }
            unset($row);

            // Pull the audit rows for this requisition so the warehouse can see
            // exactly which lines were self-acknowledged and why.
            $warningsStmt = $db->prepare("
                SELECT id, requisition_item_id, ingredient_id, item_name,
                       requested_qty, available_qty, shortage, decision,
                       decided_role, override_reason, created_at
                FROM requisition_stock_warnings
                WHERE requisition_id = ?
                ORDER BY id ASC
            ");
            $warningsStmt->execute([$id]);
            $warnings = $warningsStmt->fetchAll();

            $requisitionData['stock_override'] = [
                'acknowledged' => (bool) ($requisitionData['stock_override_acknowledged'] ?? 0),
                'reason' => $requisitionData['stock_override_reason'] ?? null,
                'at' => $requisitionData['stock_override_at'] ?? null,
                'by' => $requisitionData['stock_override_first'] ? [
                    'first_name' => $requisitionData['stock_override_first'],
                    'last_name' => $requisitionData['stock_override_last'],
                    'role' => $requisitionData['stock_override_role'],
                ] : null,
                'warnings' => $warnings,
            ];
            unset(
                $requisitionData['stock_override_acknowledged'],
                $requisitionData['stock_override_by'],
                $requisitionData['stock_override_reason'],
                $requisitionData['stock_override_at'],
                $requisitionData['stock_override_first'],
                $requisitionData['stock_override_last'],
                $requisitionData['stock_override_role']
            );

            Response::success([
                'requisition' => $requisitionData,
                'items' => $itemList
            ], 'Requisition details retrieved successfully');
            break;
            
        case 'pending_count':
            // Get count of requisitions awaiting warehouse action.
            // V4.0 — 'pending' is the actionable state; 'approved' is kept
            // for legacy rows that pre-date the workflow change.
            $stmt = $db->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
                    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high,
                    SUM(CASE WHEN needed_by_date <= CURDATE() THEN 1 ELSE 0 END) as overdue
                FROM material_requisitions
                WHERE status IN ('pending', 'approved')
            ");
            $stmt->execute();
            $counts = $stmt->fetch();

            Response::success($counts, 'Pending count retrieved successfully');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle PUT requests - Fulfill requisitions
 */
function handlePut($db, $currentUser) {
    // Only warehouse_raw and GM can fulfill
    if (!in_array($currentUser['role'], ['warehouse_raw', 'general_manager'])) {
        Response::error('Only Warehouse Raw staff can fulfill requisitions', 403);
    }
    
    $action = getParam('action');
    $id = getParam('id');
    
    if (!$action || !$id) {
        Response::error('Action and requisition ID are required', 400);
    }
    
    switch ($action) {
        // V4.0 — Approve / Reject are no longer supported on this endpoint.
        // Production requisitions are visible to warehouse immediately on
        // submit. Kept as explicit 400 responses so any legacy client that
        // still POSTs these gets a clear migration message instead of a
        // silent 404.
        case 'approve':
            Response::error(
                "The 'approve' action is no longer required. Production requisitions become visible to Warehouse Raw as soon as production submits them (status 'pending').",
                400
            );
            break;

        case 'reject':
            Response::error(
                "The 'reject' action is no longer supported. To refuse a request, do not fulfill it; production can cancel their own pending request, or GM can cancel on their behalf.",
                400
            );
            break;

        case 'fulfill':
            // Fulfill entire requisition
            $db->beginTransaction();

            try {
                // V4.0 — no GM approval gate. 'pending' is the new actionable
                // state; 'approved' and 'in_progress' kept for legacy rows.
                $requisition = $db->prepare("
                    SELECT * FROM material_requisitions WHERE id = ? AND status IN ('pending', 'approved', 'partial', 'in_progress')
                ");
                $requisition->execute([$id]);
                $reqData = $requisition->fetch();

                if (!$reqData) {
                    throw new Exception('Requisition not found or not in a fulfillable status');
                }

                // Plan guard: a requisition must have a planned_recipe_id and
                // planned_quantity before warehouse raw can issue materials.
                // Otherwise production staff can't start a run from it after
                // fulfillment, and the issued materials are stuck in limbo.
                if (empty($reqData['planned_recipe_id']) || empty($reqData['planned_quantity']) || (float)$reqData['planned_quantity'] <= 0) {
                    throw new Exception(
                        'Cannot fulfill: this requisition is missing a planned recipe or planned quantity. ' .
                        'The requester must open the requisition and set the planned product + planned quantity first.'
                    );
                }

                // Get all pending items
                $items = $db->prepare("
                    SELECT ri.*, 
                        CASE ri.item_type
                            WHEN 'ingredient' THEN i.unit_of_measure
                            WHEN 'mro' THEN m.unit_of_measure
                        END as unit_of_measure
                    FROM requisition_items ri
                    LEFT JOIN ingredients i ON ri.item_type = 'ingredient' AND ri.item_id = i.id
                    LEFT JOIN mro_items m ON ri.item_type = 'mro' AND ri.item_id = m.id
                    WHERE ri.requisition_id = ? AND ri.status IN ('pending', 'partial')
                ");
                $items->execute([$id]);
                $itemList = $items->fetchAll();
                
                if (empty($itemList)) {
                    throw new Exception('No items to fulfill');
                }
                
                $fulfilledItems = [];
                $issuedQuantities = getParam('issued_quantities', []); // Optional: specify exact quantities
                
                foreach ($itemList as $item) {
                    $requestedQty = $item['requested_quantity'];
                    $issuedQty = isset($issuedQuantities[$item['id']]) 
                        ? (float)$issuedQuantities[$item['id']] 
                        : $requestedQty;
                    
                    // Determine effective item type - detect raw milk from item_name if not explicitly set
                    $effectiveItemType = $item['item_type'];
                    $itemNameLower = strtolower(trim($item['item_name'] ?? ''));
                    $rawMilkPatterns = ['raw', 'raw milk', 'fresh milk', 'carabao', 'cow milk', 'goat milk', 'whole milk'];
                    
                    foreach ($rawMilkPatterns as $pattern) {
                        if ($itemNameLower === $pattern || strpos($itemNameLower, $pattern) !== false) {
                            $effectiveItemType = 'raw_milk';
                            break;
                        }
                    }
                    // Also check for 'milk' but exclude processed products
                    if ($effectiveItemType !== 'raw_milk' && strpos($itemNameLower, 'milk') !== false) {
                        if (strpos($itemNameLower, 'powder') === false && strpos($itemNameLower, 'chocolate') === false) {
                            $effectiveItemType = 'raw_milk';
                        }
                    }
                    
                    if ($effectiveItemType === 'raw_milk') {
                        // Issue from tanks
                        $result = issueMilk($db, $issuedQty, $id, $currentUser);
                        $fulfilledItems[] = array_merge($item, ['issued' => $result]);
                    } elseif ($effectiveItemType === 'ingredient') {
                        // Issue ingredient
                        $result = issueIngredient($db, $item['item_id'], $issuedQty, $id, $currentUser);
                        $fulfilledItems[] = array_merge($item, ['issued' => $result]);
                    } elseif ($effectiveItemType === 'mro') {
                        // Issue MRO item
                        $result = issueMRO($db, $item['item_id'], $issuedQty, $id, $currentUser);
                        $fulfilledItems[] = array_merge($item, ['issued' => $result]);
                    }
                    
                    // Update requisition item - accumulate issued quantity
                    // V4.0.1 — DO NOT let the CASE expression re-add $issuedQty
                    // to the (newly written) issued_quantity. MySQL evaluates
                    // SET clauses left-to-right, so the CASE was reading the
                    // NEW value of issued_quantity and double-adding
                    // $issuedQty to it. The result: a partial issuance like
                    // 23.94 against a requested 25 was being marked
                    // 'fulfilled' because 23.94 + 23.94 >= 25. Same shape
                    // as the pasteurization phantom-debit pitfall. The fix
                    // is to compute the new total and the new status in
                    // PHP and bind them directly.
                    $newIssued = round((float)($item['issued_quantity'] ?? 0) + $issuedQty, 3);
                    $newStatus = $newIssued >= (float) $item['requested_quantity']
                        ? 'fulfilled'
                        : ($newIssued > 0 ? 'partial' : 'pending');
                    $stmt = $db->prepare("
                        UPDATE requisition_items
                        SET issued_quantity = ?,
                            status = ?,
                            fulfilled_by = ?,
                            fulfilled_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$newIssued, $newStatus, $currentUser['user_id'], $item['id']]);
                }
                
                // Check if all items are fulfilled to determine requisition status
                $checkItems = $db->prepare("
                    SELECT 
                        COUNT(*) as total_items,
                        SUM(CASE WHEN status = 'fulfilled' THEN 1 ELSE 0 END) as fulfilled_items,
                        SUM(CASE WHEN status IN ('partial', 'fulfilled') THEN 1 ELSE 0 END) as processed_items
                    FROM requisition_items WHERE requisition_id = ?
                ");
                $checkItems->execute([$id]);
                $itemCounts = $checkItems->fetch();
                
                // Determine requisition status based on item statuses
                $newReqStatus = 'approved'; // Default
                if ($itemCounts['fulfilled_items'] == $itemCounts['total_items']) {
                    $newReqStatus = 'fulfilled'; // All items fully fulfilled
                } elseif ($itemCounts['processed_items'] > 0) {
                    $newReqStatus = 'partial'; // Some items processed but not all complete
                }
                
                // Update requisition status
                $stmt = $db->prepare("
                    UPDATE material_requisitions 
                    SET status = ?,
                        fulfilled_by = ?,
                        fulfilled_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$newReqStatus, $currentUser['user_id'], $id]);
                
                // Log audit
                logAudit($currentUser['user_id'], 'fulfill_requisition', 'material_requisitions', $id, 
                    ['status' => 'approved'], 
                    ['status' => 'fulfilled']
                );
                
                $db->commit();
                
                Response::success([
                    'requisition_id' => $id,
                    'fulfilled_items' => $fulfilledItems
                ], 'Requisition fulfilled successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'fulfill_item':
            // Fulfill a single item from requisition
            $itemId = getParam('item_id');
            $issuedQuantity = getParam('issued_quantity');
            
            if (!$itemId || $issuedQuantity === null) {
                Response::error('Item ID and issued quantity are required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Get item
                $item = $db->prepare("
                    SELECT ri.*, ir.status as req_status,
                           ir.planned_recipe_id, ir.planned_quantity
                    FROM requisition_items ri
                    JOIN material_requisitions ir ON ri.requisition_id = ir.id
                    WHERE ri.id = ? AND ri.requisition_id = ?
                ");
                $item->execute([$itemId, $id]);
                $itemData = $item->fetch();

                if (!$itemData) {
                    throw new Exception('Item not found');
                }

                // V4.0 — see note in `fulfill` case. 'pending' is the new
                // actionable state; legacy statuses kept for back-compat.
                if (!in_array($itemData['req_status'], ['pending', 'approved', 'partial', 'in_progress'])) {
                    throw new Exception('Requisition is not in a fulfillable status');
                }

                // Plan guard: see the equivalent check in the 'fulfill' case above.
                if (empty($itemData['planned_recipe_id']) || empty($itemData['planned_quantity']) || (float)$itemData['planned_quantity'] <= 0) {
                    throw new Exception(
                        'Cannot fulfill: this requisition is missing a planned recipe or planned quantity. ' .
                        'The requester must open the requisition and set the planned product + planned quantity first.'
                    );
                }
                
                // Determine effective item type - detect raw milk from item_name if not explicitly set
                $effectiveItemType = $itemData['item_type'];
                $itemNameLower = strtolower(trim($itemData['item_name'] ?? ''));
                $rawMilkPatterns = ['raw', 'raw milk', 'fresh milk', 'carabao', 'cow milk', 'goat milk', 'whole milk'];
                
                foreach ($rawMilkPatterns as $pattern) {
                    if ($itemNameLower === $pattern || strpos($itemNameLower, $pattern) !== false) {
                        $effectiveItemType = 'raw_milk';
                        break;
                    }
                }
                // Also check for 'milk' but exclude processed products
                if ($effectiveItemType !== 'raw_milk' && strpos($itemNameLower, 'milk') !== false) {
                    if (strpos($itemNameLower, 'powder') === false && strpos($itemNameLower, 'chocolate') === false) {
                        $effectiveItemType = 'raw_milk';
                    }
                }
                
                // Issue based on type
                if ($effectiveItemType === 'raw_milk') {
                    issueMilk($db, $issuedQuantity, $id, $currentUser);
                } elseif ($effectiveItemType === 'ingredient') {
                    issueIngredient($db, $itemData['item_id'], $issuedQuantity, $id, $currentUser);
                } elseif ($effectiveItemType === 'mro') {
                    issueMRO($db, $itemData['item_id'], $issuedQuantity, $id, $currentUser);
                }
                
                // Update item - calculate new total and check against requested
                $currentIssued = floatval($itemData['issued_quantity'] ?? 0);
                $newTotalIssued = $currentIssued + floatval($issuedQuantity);
                $newStatus = $newTotalIssued >= floatval($itemData['requested_quantity']) ? 'fulfilled' : 'partial';
                
                $stmt = $db->prepare("
                    UPDATE requisition_items 
                    SET issued_quantity = ?, 
                        status = ?,
                        fulfilled_by = ?,
                        fulfilled_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$newTotalIssued, $newStatus, $currentUser['user_id'], $itemId]);
                
                // Check if all items are fulfilled
                $checkAll = $db->prepare("
                    SELECT 
                        COUNT(*) as total_items,
                        SUM(CASE WHEN status = 'fulfilled' THEN 1 ELSE 0 END) as fulfilled_items,
                        SUM(CASE WHEN status IN ('partial', 'fulfilled') THEN 1 ELSE 0 END) as processed_items
                    FROM requisition_items 
                    WHERE requisition_id = ?
                ");
                $checkAll->execute([$id]);
                $itemCounts = $checkAll->fetch();
                
                // Determine requisition status based on item statuses
                $newReqStatus = 'approved'; // Default - still being processed
                if ($itemCounts['fulfilled_items'] == $itemCounts['total_items']) {
                    $newReqStatus = 'fulfilled'; // All items fully fulfilled
                } elseif ($itemCounts['processed_items'] > 0) {
                    $newReqStatus = 'partial'; // Some items processed but not all complete
                }
                
                // Update requisition status
                $stmt = $db->prepare("
                    UPDATE material_requisitions 
                    SET status = ?, fulfilled_by = ?, fulfilled_at = NOW(), updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$newReqStatus, $currentUser['user_id'], $id]);
                
                $db->commit();
                
                Response::success([
                    'item_id' => $itemId,
                    'issued_quantity' => $issuedQuantity,
                    'status' => $newStatus
                ], 'Item fulfilled successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'reject_item':
            // Reject/cancel an item (out of stock, etc.)
            $itemId = getParam('item_id');
            $reason = getParam('reason');
            
            if (!$itemId || !$reason) {
                Response::error('Item ID and reason are required', 400);
            }
            
            $stmt = $db->prepare("
                UPDATE requisition_items 
                SET status = 'cancelled', notes = ?, updated_at = NOW()
                WHERE id = ? AND requisition_id = ?
            ");
            $stmt->execute([$reason, $itemId, $id]);
            
            Response::success(null, 'Item cancelled successfully');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Issue milk from tanks (FIFO)
 * REVISED: Uses raw_milk_inventory instead of tank_milk_batches
 */
function issueMilk($db, $liters, $requisitionId, $currentUser) {
    // Get available batches from raw_milk_inventory (FIFO).
    // V4.0 — also require expiry_date >= CURDATE() so the warehouse staff
    // can't issue milk that's past its prime. Previously the system let
    // requisition REQ-20260614-031 be fulfilled from batch RAW-RCV-000012
    // whose expiry was 2025-10-23 (8 months ago), leaving production with
    // an "issued" record but 0L of usable milk.
    $batches = $db->prepare("
        SELECT rmi.*, st.tank_code
        FROM raw_milk_inventory rmi
        LEFT JOIN storage_tanks st ON rmi.tank_id = st.id
        WHERE rmi.status IN ('available', 'reserved')
        AND rmi.remaining_liters > 0
        AND rmi.expiry_date >= CURDATE()
        ORDER BY rmi.expiry_date ASC, rmi.received_date ASC, rmi.id ASC
    ");
    $batches->execute();
    $batchList = $batches->fetchAll();
    
    $totalAvailable = array_sum(array_column($batchList, 'remaining_liters'));
    
    if ($totalAvailable < $liters) {
        throw new Exception("Insufficient milk. Available: {$totalAvailable}L, Needed: {$liters}L");
    }
    
    $remainingToIssue = $liters;
    $issued = [];
    
    foreach ($batchList as $batch) {
        if ($remainingToIssue <= 0) break;
        
        $issueFromBatch = min($batch['remaining_liters'], $remainingToIssue);
        $newRemaining = $batch['remaining_liters'] - $issueFromBatch;
        $newStatus = $newRemaining > 0 ? 'available' : 'depleted';
        
        // Update raw_milk_inventory batch
        $stmt = $db->prepare("
            UPDATE raw_milk_inventory 
            SET remaining_liters = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newRemaining, $newStatus, $batch['id']]);
        
        // Update tank volume if assigned to a tank
        if ($batch['tank_id']) {
            $stmt = $db->prepare("
                UPDATE storage_tanks 
                SET current_volume = current_volume - ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$issueFromBatch, $batch['tank_id']]);
        }
        
        // Create transaction
        $txCode = generateCode('TX');
        $stmt = $db->prepare("
            INSERT INTO inventory_transactions 
            (transaction_code, transaction_type, item_type, item_id, batch_id,
             quantity, unit_of_measure, reference_type, reference_id,
             from_location, performed_by, reason)
            VALUES (?, 'production_issue', 'raw_milk', ?, ?, ?, 'L', 'requisition', ?, ?, ?, 'Requisition fulfillment')
        ");
        $stmt->execute([
            $txCode,
            $batch['id'],
            $batch['id'],
            $issueFromBatch,
            $requisitionId,
            $batch['tank_code'] ?? 'Unassigned',
            $currentUser['user_id']
        ]);
        
        $issued[] = [
            'tank_code' => $batch['tank_code'] ?? 'Unassigned',
            'liters' => $issueFromBatch
        ];
        
        $remainingToIssue -= $issueFromBatch;
    }
    
    return ['total_liters' => $liters, 'from_tanks' => $issued];
}

/**
 * Issue ingredient (FIFO)
 */
function issueIngredient($db, $ingredientId, $quantity, $requisitionId, $currentUser) {
    // Validate ingredient ID
    if (!$ingredientId || $ingredientId <= 0) {
        throw new Exception("Invalid ingredient ID");
    }
    
    $ingredient = $db->prepare("SELECT * FROM ingredients WHERE id = ? AND is_active = 1 FOR UPDATE");
    $ingredient->execute([$ingredientId]);
    $ingredientData = $ingredient->fetch();
    
    if (!$ingredientData) {
        throw new Exception("Ingredient not found (ID: {$ingredientId})");
    }
    
    ensureIngredientBatchesForIssue($db, $ingredientData, $quantity, $currentUser);
    $batchList = getUsableIngredientBatches($db, $ingredientId, true);
    
    $totalAvailable = array_sum(array_column($batchList, 'remaining_quantity'));
    
    if ($totalAvailable < $quantity) {
        throw new Exception("Insufficient {$ingredientData['ingredient_name']}. Available: {$totalAvailable}, Needed: {$quantity}");
    }
    
    $remainingToIssue = $quantity;
    $issued = [];
    
    foreach ($batchList as $batch) {
        if ($remainingToIssue <= 0) break;
        
        $issueFromBatch = min($batch['remaining_quantity'], $remainingToIssue);
        $newRemaining = $batch['remaining_quantity'] - $issueFromBatch;
        $newStatus = $newRemaining > 0 ? 'partially_used' : 'consumed';
        
        // Update batch
        $stmt = $db->prepare("
            UPDATE ingredient_batches 
            SET remaining_quantity = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newRemaining, $newStatus, $batch['id']]);
        
        // Create transaction
        $txCode = generateCode('TX');
        $stmt = $db->prepare("
            INSERT INTO inventory_transactions 
            (transaction_code, transaction_type, item_type, item_id, batch_id,
             quantity, unit_of_measure, reference_type, reference_id,
             from_location, performed_by, reason)
            VALUES (?, 'production_issue', 'ingredient', ?, ?, ?, ?, 'requisition', ?, ?, ?, 'Requisition fulfillment')
        ");
        $stmt->execute([
            $txCode,
            $ingredientId,
            $batch['id'],
            $issueFromBatch,
            $ingredientData['unit_of_measure'],
            $requisitionId,
            $ingredientData['storage_location'],
            $currentUser['user_id']
        ]);
        
        $issued[] = [
            'batch_code' => $batch['batch_code'],
            'quantity' => $issueFromBatch
        ];
        
        $remainingToIssue -= $issueFromBatch;
    }
    
    // Update ingredient current stock
    $stmt = $db->prepare("
        UPDATE ingredients SET current_stock = GREATEST(current_stock - ?, 0), updated_at = NOW() WHERE id = ?
    ");
    $stmt->execute([$quantity, $ingredientId]);
    
    return ['total_quantity' => $quantity, 'from_batches' => $issued];
}

/**
 * Issue MRO item (FIFO)
 */
function issueMRO($db, $mroItemId, $quantity, $requisitionId, $currentUser) {
    $item = $db->prepare("SELECT * FROM mro_items WHERE id = ?");
    $item->execute([$mroItemId]);
    $itemData = $item->fetch();
    
    // Get available inventory
    $inventory = $db->prepare("
        SELECT * FROM mro_inventory
        WHERE mro_item_id = ?
        AND status IN ('available', 'partially_used')
        AND remaining_quantity > 0
        ORDER BY received_date ASC, id ASC
    ");
    $inventory->execute([$mroItemId]);
    $inventoryList = $inventory->fetchAll();
    
    $totalAvailable = array_sum(array_column($inventoryList, 'remaining_quantity'));
    
    if ($totalAvailable < $quantity) {
        throw new Exception("Insufficient {$itemData['item_name']}. Available: {$totalAvailable}, Needed: {$quantity}");
    }
    
    $remainingToIssue = $quantity;
    $issued = [];
    
    foreach ($inventoryList as $inv) {
        if ($remainingToIssue <= 0) break;
        
        $issueFromBatch = min($inv['remaining_quantity'], $remainingToIssue);
        $newRemaining = $inv['remaining_quantity'] - $issueFromBatch;
        $newStatus = $newRemaining > 0 ? 'partially_used' : 'consumed';
        
        // Update inventory
        $stmt = $db->prepare("
            UPDATE mro_inventory 
            SET remaining_quantity = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newRemaining, $newStatus, $inv['id']]);
        
        // Create transaction
        $txCode = generateCode('TX');
        $stmt = $db->prepare("
            INSERT INTO inventory_transactions 
            (transaction_code, transaction_type, item_type, item_id, batch_id,
             quantity, unit_of_measure, reference_type, reference_id,
             from_location, performed_by, reason)
            VALUES (?, 'production_issue', 'mro', ?, ?, ?, ?, 'requisition', ?, ?, ?, 'Requisition fulfillment')
        ");
        $stmt->execute([
            $txCode,
            $mroItemId,
            $inv['id'],
            $issueFromBatch,
            $itemData['unit_of_measure'],
            $requisitionId,
            $itemData['storage_location'],
            $currentUser['user_id']
        ]);
        
        $issued[] = [
            'batch_code' => $inv['batch_code'],
            'quantity' => $issueFromBatch
        ];
        
        $remainingToIssue -= $issueFromBatch;
    }
    
    // Update item current stock
    $stmt = $db->prepare("
        UPDATE mro_items SET current_stock = current_stock - ?, updated_at = NOW() WHERE id = ?
    ");
    $stmt->execute([$quantity, $mroItemId]);
    
    return ['total_quantity' => $quantity, 'from_batches' => $issued];
}

