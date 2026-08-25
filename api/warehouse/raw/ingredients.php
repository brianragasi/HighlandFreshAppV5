<?php
/**
 * Highland Fresh System - Warehouse Raw Ingredients API
 *
 * REVISED: Updated for new schema (Feb 2026)
 * - Updated transaction types: po_receive, production_issue, physical_adjust, dispose
 * - Added QC status handling for ingredient batches (quarantine flow)
 * - Added po_id reference when receiving batches
 * - Added supplier_id reference
 *
 * Manages ingredients inventory (sugar, powder, flavors, rennet, salt, packaging)
 *
 * GET    - List ingredients, get details, check stock
 * POST   - Receive new ingredient batch
 * PUT    - Issue ingredients, adjust stock
 *
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/helpers/plain_text.php';
require_once __DIR__ . '/ingredient_stock_helpers.php';
require_once dirname(dirname(__DIR__)) . '/helpers/ingredient_opening_stock.php';
require_once dirname(dirname(__DIR__)) . '/helpers/procurement_notifications.php';

// Require Warehouse Raw role
$currentUser = Auth::requireRole(['warehouse_raw', 'general_manager', 'production_staff', 'purchaser']);

try {
    $db = Database::getInstance()->getConnection();
    ensureIngredientPerishabilitySupport($db);
    ensureIngredientOpeningStockSupport($db);
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $currentUser);
            break;
        case 'POST':
            handlePost($db, $currentUser);
            break;
        case 'PUT':
            handlePut($db, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Warehouse Raw Ingredients API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}

function ensureIngredientPerishabilitySupport($db) {
    if (!auditColumnExists($db, 'ingredients', 'is_perishable')) {
        $db->exec("ALTER TABLE `ingredients` ADD COLUMN `is_perishable` TINYINT(1) NOT NULL DEFAULT 1 AFTER `shelf_life_days`");
    }

    if (!auditColumnExists($db, 'ingredients', 'maximum_stock')) {
        $db->exec("ALTER TABLE `ingredients` ADD COLUMN `maximum_stock` DECIMAL(10,2) DEFAULT NULL COMMENT 'Par level / order-up-to stock' AFTER `reorder_point`");
    }

    $db->exec("
        UPDATE ingredients i
        JOIN ingredient_categories c ON c.id = i.category_id
        SET i.is_perishable = 0,
            i.shelf_life_days = NULL
        WHERE LOWER(TRIM(COALESCE(c.category_name, ''))) LIKE '%packaging%'
          AND (COALESCE(i.is_perishable, 1) <> 0 OR i.shelf_life_days IS NOT NULL)
    ");
}

/**
 * Handle GET requests
 */
function handleGet($db, $currentUser) {
    $action = getParam('action', 'list');
    $id = getParam('id');
    
    switch ($action) {
        case 'list':
            // Get all ingredients with stock info
            $categoryId = getParam('category_id');
            $lowStockOnly = getParam('low_stock') === '1';
            $search = getParam('search');
            $usableStockSql = usableIngredientBatchStockSql('i.id', 'usable_ib');
            
            $sql = "
                SELECT 
                    i.*,
                    ic.category_name,
                    CASE
                        WHEN {$usableStockSql} <= 0 THEN 'out_of_stock'
                        WHEN {$usableStockSql} <= " . StockRule::lowThresholdSql('i.reorder_point', 'i.minimum_stock') . " THEN 'low_stock'
                        ELSE 'ok'
                    END as stock_status,
                    (SELECT COUNT(*) FROM ingredient_batches ib
                     WHERE ib.ingredient_id = i.id
                     AND ib.status IN ('available', 'partially_used')
                     AND ib.remaining_quantity > 0
                     AND (COALESCE(i.is_perishable, 1) = 0 OR ib.expiry_date > CURDATE())
                     AND (COALESCE(i.is_perishable, 1) = 0 OR
                          (NULLIF(TRIM(ib.supplier_batch_no), '') IS NOT NULL AND ib.expiry_date IS NOT NULL))) as batch_count,
                    {$usableStockSql} as batch_stock,
                    (SELECT COALESCE(SUM(ib.remaining_quantity), 0) FROM ingredient_batches ib
                     WHERE ib.ingredient_id = i.id
                     AND ib.status IN ('available', 'partially_used')
                     AND ib.remaining_quantity > 0
                     AND (ib.expiry_date IS NULL OR ib.expiry_date > CURDATE())
                     AND COALESCE(i.is_perishable, 1) = 1
                     AND NULLIF(TRIM(ib.supplier_batch_no), '') IS NULL) as untraceable_batch_stock,
                    (SELECT COALESCE(SUM(ib.remaining_quantity), 0) FROM ingredient_batches ib
                     WHERE ib.ingredient_id = i.id
                     AND ib.status IN ('available', 'partially_used', 'quarantine', 'expired')
                     AND ib.remaining_quantity > 0) as accounted_batch_stock,
                    (SELECT COALESCE(SUM(ib.remaining_quantity), 0) FROM ingredient_batches ib
                     WHERE ib.ingredient_id = i.id
                     AND ib.status IN ('available', 'partially_used', 'quarantine', 'expired')
                     AND ib.remaining_quantity > 0
                     AND ib.expiry_date IS NOT NULL
                     AND COALESCE(i.is_perishable, 1) = 1
                     AND ib.expiry_date <= CURDATE()) as expired_batch_stock,
                    (SELECT MIN(expiry_date) FROM ingredient_batches ib
                     WHERE ib.ingredient_id = i.id
                     AND ib.status IN ('available', 'partially_used')
                     AND ib.remaining_quantity > 0
                     AND ib.expiry_date IS NOT NULL
                     AND ib.expiry_date > CURDATE()
                     AND (COALESCE(i.is_perishable, 1) = 0 OR NULLIF(TRIM(ib.supplier_batch_no), '') IS NOT NULL)) as nearest_expiry
                FROM ingredients i
                LEFT JOIN ingredient_categories ic ON i.category_id = ic.id
                WHERE i.is_active = 1
            ";
            $params = [];
            
            if ($categoryId) {
                $sql .= " AND i.category_id = ?";
                $params[] = $categoryId;
            }
            
            if ($lowStockOnly) {
                $sql .= " AND {$usableStockSql} <= " . StockRule::lowThresholdSql('i.reorder_point', 'i.minimum_stock');
            }
            
            if ($search) {
                $sql .= " AND (i.ingredient_code LIKE ? OR i.ingredient_name LIKE ?)";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
            }
            
            $sql .= " ORDER BY i.ingredient_name ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $ingredients = $stmt->fetchAll();

            foreach ($ingredients as &$ingredient) {
                $onFileStock = (float) ($ingredient['current_stock'] ?? 0);
                $usableStock = (float) ($ingredient['batch_stock'] ?? 0);
                $accountedStock = (float) ($ingredient['accounted_batch_stock'] ?? 0);
                $expiredStock = (float) ($ingredient['expired_batch_stock'] ?? 0);
                $untraceableStock = (float) ($ingredient['untraceable_batch_stock'] ?? 0);
                $ingredient['current_stock_on_file'] = $onFileStock;
                $ingredient['current_stock'] = $usableStock;
                $ingredient['stock_variance'] = round($onFileStock - $usableStock, 3);
                $ingredient['missing_batch_stock'] = max(0, round($onFileStock - $accountedStock, 3));
                $ingredient['batch_stock_surplus'] = max(0, round($accountedStock - $onFileStock, 3));
                $ingredient['restricted_stock'] = max(0, round($accountedStock - $usableStock, 3));
                $ingredient['unexplained_batch_surplus'] = max(0, round(
                    $ingredient['batch_stock_surplus'] - $ingredient['restricted_stock'],
                    3
                ));
                $ingredient['expired_batch_stock'] = $expiredStock;
                $ingredient['untraceable_batch_stock'] = $untraceableStock;
                $ingredient['needs_stock_check'] = $ingredient['missing_batch_stock'] > 0.0005
                    || $ingredient['unexplained_batch_surplus'] > 0.0005;
            }
            unset($ingredient);
            
            Response::success(['ingredients' => $ingredients], 'Ingredients retrieved successfully');
            break;
            
        case 'detail':
            if (!$id) {
                Response::error('Ingredient ID is required', 400);
            }
            
            // Get ingredient details
            $ingredient = $db->prepare("
                SELECT i.*, ic.category_name
                FROM ingredients i
                LEFT JOIN ingredient_categories ic ON i.category_id = ic.id
                WHERE i.id = ? AND i.is_active = 1
            ");
            $ingredient->execute([$id]);
            $ingredientData = $ingredient->fetch();
            
            if (!$ingredientData) {
                Response::error('Ingredient not found', 404);
            }
            
            // Show every batch that is still physically in the warehouse.
            // Expired and quarantined batches stay visible but are never
            // included in usable FIFO stock.
            $batches = $db->prepare("
                SELECT 
                    ib.*,
                    u.first_name as received_by_first,
                    u.last_name as received_by_last,
                    DATEDIFF(ib.expiry_date, CURDATE()) as days_until_expiry,
                    CASE
                        WHEN ib.status IN ('available', 'partially_used')
                         AND (COALESCE(i.is_perishable, 1) = 0 OR ib.expiry_date > CURDATE())
                         AND (COALESCE(i.is_perishable, 1) = 0 OR
                              (NULLIF(TRIM(ib.supplier_batch_no), '') IS NOT NULL AND ib.expiry_date IS NOT NULL))
                        THEN 1 ELSE 0
                    END AS is_usable,
                    CASE
                        WHEN COALESCE(i.is_perishable, 1) = 1
                         AND NULLIF(TRIM(ib.supplier_batch_no), '') IS NULL
                         AND (ib.expiry_date IS NULL OR ib.expiry_date > CURDATE())
                        THEN 1 ELSE 0
                    END AS has_traceability_issue,
                    CASE
                        WHEN COALESCE(i.is_perishable, 1) = 1
                         AND ib.expiry_date IS NOT NULL AND ib.expiry_date <= CURDATE()
                        THEN 1 ELSE 0
                    END AS is_expired
                FROM ingredient_batches ib
                JOIN ingredients i ON i.id = ib.ingredient_id
                LEFT JOIN users u ON ib.received_by = u.id
                WHERE ib.ingredient_id = ?
                AND ib.status IN ('available', 'partially_used', 'quarantine', 'expired')
                AND ib.remaining_quantity > 0
                ORDER BY
                    CASE WHEN COALESCE(i.is_perishable, 1) = 1 AND ib.expiry_date IS NOT NULL AND ib.expiry_date <= CURDATE() THEN 0 ELSE 1 END,
                    ib.expiry_date ASC,
                    ib.received_date ASC,
                    ib.id ASC
            ");
            $batches->execute([$id]);
            $batchList = $batches->fetchAll();
            $accountedBatchStock = array_reduce($batchList, function ($sum, $batch) {
                return $sum + (float) ($batch['remaining_quantity'] ?? 0);
            }, 0.0);
            $usableBatchStock = array_reduce($batchList, function ($sum, $batch) {
                return $sum + ((int) ($batch['is_usable'] ?? 0) === 1
                    ? (float) ($batch['remaining_quantity'] ?? 0)
                    : 0);
            }, 0.0);
            $expiredBatchStock = array_reduce($batchList, function ($sum, $batch) {
                return $sum + ((int) ($batch['is_expired'] ?? 0) === 1
                    ? (float) ($batch['remaining_quantity'] ?? 0)
                    : 0);
            }, 0.0);
            $untraceableBatchStock = array_reduce($batchList, function ($sum, $batch) {
                return $sum + ((int) ($batch['has_traceability_issue'] ?? 0) === 1
                    ? (float) ($batch['remaining_quantity'] ?? 0)
                    : 0);
            }, 0.0);
            $onFileStock = (float) ($ingredientData['current_stock'] ?? 0);
            $ingredientData['batch_stock'] = $usableBatchStock;
            $ingredientData['accounted_batch_stock'] = $accountedBatchStock;
            $ingredientData['expired_batch_stock'] = $expiredBatchStock;
            $ingredientData['untraceable_batch_stock'] = $untraceableBatchStock;
            $ingredientData['restricted_stock'] = max(0, round($accountedBatchStock - $usableBatchStock, 3));
            $ingredientData['stock_variance'] = round($onFileStock - $usableBatchStock, 3);
            $ingredientData['missing_batch_stock'] = max(0, round($onFileStock - $accountedBatchStock, 3));
            $ingredientData['batch_stock_surplus'] = max(0, round($accountedBatchStock - $onFileStock, 3));
            $ingredientData['unexplained_batch_surplus'] = max(0, round(
                $ingredientData['batch_stock_surplus'] - $ingredientData['restricted_stock'],
                3
            ));
            $ingredientData['batch_count'] = count($batchList);
            
            // Get recent transactions
            $transactions = $db->prepare("
                SELECT 
                    it.*,
                    u.first_name,
                    u.last_name
                FROM inventory_transactions it
                JOIN users u ON it.performed_by = u.id
                WHERE it.item_type = 'ingredient' AND it.item_id = ?
                ORDER BY it.created_at DESC
                LIMIT 20
            ");
            $transactions->execute([$id]);
            $txList = $transactions->fetchAll();
            
            Response::success([
                'ingredient' => $ingredientData,
                'batches' => $batchList,
                'transactions' => $txList
            ], 'Ingredient details retrieved successfully');
            break;
            
        case 'categories':
            // Get all categories
            $stmt = $db->prepare("
                SELECT ic.*, 
                    (SELECT COUNT(*) FROM ingredients WHERE category_id = ic.id AND is_active = 1) as item_count
                FROM ingredient_categories ic
                WHERE ic.is_active = 1
                ORDER BY ic.category_name ASC
            ");
            $stmt->execute();
            $categories = $stmt->fetchAll();
            
            Response::success(['categories' => $categories], 'Categories retrieved successfully');
            break;

        case 'opening_stock_options':
            if (!in_array($currentUser['role'], ['warehouse_raw', 'general_manager'], true)) {
                Response::error('Only Warehouse Raw or GM can record unlisted stock', 403);
            }
            $ingredientId = (int) getParam('ingredient_id', 0);
            if ($ingredientId <= 0) {
                Response::error('Choose an ingredient first', 400);
            }
            $supplierStmt = $db->prepare("
                SELECT s.id, s.supplier_code, s.supplier_name
                FROM supplier_ingredients si
                JOIN suppliers s ON s.id = si.supplier_id
                WHERE si.ingredient_id = ?
                  AND si.is_active = 1
                  AND s.is_active = 1
                ORDER BY s.supplier_name
            ");
            $supplierStmt->execute([$ingredientId]);
            $documentStmt = $db->prepare("
                SELECT po.id AS po_id, po.po_number, po.supplier_id, po.order_date,
                       po.status, MAX(poi.unit_price) AS unit_price,
                       MAX(poi.unit) AS unit
                FROM purchase_orders po
                JOIN purchase_order_items poi ON poi.po_id = po.id
                JOIN suppliers s ON s.id = po.supplier_id AND s.is_active = 1
                JOIN supplier_ingredients si
                  ON si.supplier_id = po.supplier_id
                 AND si.ingredient_id = poi.ingredient_id
                 AND si.is_active = 1
                WHERE poi.ingredient_id = ?
                  AND po.status IN ('approved', 'ordered', 'partial_received', 'received', 'closed')
                GROUP BY po.id, po.po_number, po.supplier_id, po.order_date, po.status
                ORDER BY po.order_date DESC, po.id DESC
                LIMIT 100
            ");
            $documentStmt->execute([$ingredientId]);
            $documents = array_map(static function (array $row): array {
                $row['reference'] = (string) $row['po_number'];
                return $row;
            }, $documentStmt->fetchAll(PDO::FETCH_ASSOC));
            $pendingStmt = $db->prepare("
                SELECT osr.id, osr.request_code, osr.ingredient_id, osr.counted_quantity,
                       osr.quantity_to_add, osr.unit, osr.status, osr.created_at,
                       osr.held_batch_id, osr.price_status, osr.qc_status,
                       i.ingredient_name
                FROM ingredient_opening_stock_requests osr
                JOIN ingredients i ON i.id = osr.ingredient_id
                WHERE osr.status = 'pending'
                  AND (? = 'general_manager' OR osr.requested_by = ?)
                ORDER BY osr.created_at DESC
            ");
            $pendingStmt->execute([$currentUser['role'], (int) $currentUser['user_id']]);
            Response::success([
                'suppliers' => $supplierStmt->fetchAll(PDO::FETCH_ASSOC),
                'documents' => $documents,
                'pending_requests' => $pendingStmt->fetchAll(PDO::FETCH_ASSOC),
            ], 'Opening-stock options retrieved');
            break;
            
        case 'expiring':
            // Get ingredients expiring within specified days
            $days = getParam('days', 7);
            
            $stmt = $db->prepare("
                SELECT 
                    ib.id as batch_id,
                    ib.batch_code,
                    i.ingredient_code,
                    i.ingredient_name,
                    ic.category_name,
                    ib.remaining_quantity,
                    i.unit_of_measure,
                    ib.expiry_date,
                    DATEDIFF(ib.expiry_date, CURDATE()) as days_until_expiry
                FROM ingredient_batches ib
                JOIN ingredients i ON ib.ingredient_id = i.id
                LEFT JOIN ingredient_categories ic ON i.category_id = ic.id
                WHERE ib.status IN ('available', 'partially_used')
                AND ib.expiry_date IS NOT NULL
                AND ib.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY ib.expiry_date ASC
            ");
            $stmt->execute([$days]);
            $expiring = $stmt->fetchAll();
            
            Response::success(['expiring_ingredients' => $expiring], 'Expiring ingredients retrieved successfully');
            break;

        case 'expired':
            // Get expired batches that are still in stock
            $stmt = $db->prepare("
                SELECT 
                    ib.id as batch_id,
                    ib.batch_code,
                    ib.expiry_date,
                    ib.received_date,
                    ib.remaining_quantity,
                    ib.status,
                    ib.qc_status,
                    DATEDIFF(CURDATE(), ib.expiry_date) as days_expired,
                    i.ingredient_code,
                    i.ingredient_name,
                    i.unit_of_measure,
                    ic.category_name
                FROM ingredient_batches ib
                JOIN ingredients i ON ib.ingredient_id = i.id
                LEFT JOIN ingredient_categories ic ON i.category_id = ic.id
                WHERE ib.expiry_date IS NOT NULL
                AND ib.expiry_date <= CURDATE()
                AND ib.remaining_quantity > 0
                AND ib.status IN ('available', 'partially_used', 'quarantine')
                ORDER BY ib.expiry_date ASC, ib.received_date ASC, ib.id ASC
            ");
            $stmt->execute();
            $expired = $stmt->fetchAll();

            Response::success(['expired_batches' => $expired], 'Expired batches retrieved successfully');
            break;
            
        case 'check_stock':
            // Check if sufficient stock available for a list of items
            $items = getParam('items'); // Array of {ingredient_id, quantity}
            
            if (!$items || !is_array($items)) {
                Response::error('Items array is required', 400);
            }
            
            $stockCheck = [];
            $allAvailable = true;
            $checkUsableStockSql = usableIngredientBatchStockSql('i.id', 'check_ib');
            
            foreach ($items as $item) {
                $stmt = $db->prepare("
                    SELECT i.*, {$checkUsableStockSql} AS available_quantity
                    FROM ingredients i
                    WHERE i.id = ?
                ");
                $stmt->execute([$item['ingredient_id']]);
                $ing = $stmt->fetch();
                
                $available = $ing ? (float)$ing['available_quantity'] : 0;
                $needed = (float)$item['quantity'];
                $sufficient = $available >= $needed;
                
                if (!$sufficient) $allAvailable = false;
                
                $stockCheck[] = [
                    'ingredient_id' => $item['ingredient_id'],
                    'ingredient_name' => $ing ? $ing['ingredient_name'] : 'Unknown',
                    'needed' => $needed,
                    'available' => $available,
                    'sufficient' => $sufficient,
                    'shortage' => $sufficient ? 0 : ($needed - $available)
                ];
            }
            
            Response::success([
                'all_available' => $allAvailable,
                'items' => $stockCheck
            ], 'Stock check completed');
            break;
            
        case 'reorder_alerts':
            // Get all items below reorder threshold (for Reorder Alert Report)
            $includeOk = getParam('include_ok') === '1';
            $usableStockSql = usableIngredientBatchStockSql('i.id', 'alert_ib');
            
            $sql = "
                SELECT 
                    'ingredient' AS item_type,
                    i.id AS item_id,
                    i.ingredient_code AS item_code,
                    i.ingredient_name AS item_name,
                    ic.category_name,
                    i.unit_of_measure,
                    i.current_stock AS current_stock_on_file,
                    {$usableStockSql} AS current_stock,
                    i.minimum_stock,
                    " . StockRule::lowThresholdSql('i.reorder_point', 'i.minimum_stock') . " AS reorder_point,
                    i.maximum_stock,
                    i.is_perishable,
                    COALESCE(i.lead_time_days, 7) AS lead_time_days,
                    i.unit_cost,
                    GREATEST(0, (SELECT COALESCE(SUM(restricted_ib.remaining_quantity), 0)
                        FROM ingredient_batches restricted_ib
                        WHERE restricted_ib.ingredient_id = i.id
                          AND restricted_ib.status IN ('available', 'partially_used', 'quarantine', 'expired')
                          AND restricted_ib.remaining_quantity > 0) - {$usableStockSql}) AS restricted_stock,
                    " . StockRule::statusCaseSql($usableStockSql, 'i.reorder_point', 'i.minimum_stock') . " AS stock_status,
                    CASE
                        WHEN {$usableStockSql} <= 0 THEN 0
                        ELSE ROUND(({$usableStockSql} / NULLIF(i.minimum_stock, 0)) * 100, 1)
                    END AS stock_percentage,
                    " . StockRule::reorderQtySql($usableStockSql, 'i.reorder_point', 'i.maximum_stock', 'i.minimum_stock') . " AS qty_to_reorder
                FROM ingredients i
                LEFT JOIN ingredient_categories ic ON i.category_id = ic.id
                WHERE i.is_active = 1
            ";
            
            if (!$includeOk) {
                $sql .= " AND {$usableStockSql} <= " . StockRule::lowThresholdSql('i.reorder_point', 'i.minimum_stock');
            }

            $sql .= " ORDER BY
                CASE
                    WHEN {$usableStockSql} <= 0 THEN 1
                    WHEN {$usableStockSql} <= " . StockRule::lowThresholdSql('i.reorder_point', 'i.minimum_stock') . " THEN 2
                    ELSE 3
                END,
                i.ingredient_name ASC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $alerts = $stmt->fetchAll();
            
            // Summary counts. Critical was merged into Low Stock — the single
            // low-inventory tier that triggers a Purchase Request.
            $summary = [
                'out_of_stock' => 0,
                'low' => 0,
                'ok' => 0,
                'total_alerts' => 0
            ];
            
            foreach ($alerts as $alert) {
                $status = strtolower($alert['stock_status']);
                if (isset($summary[$status])) {
                    $summary[$status]++;
                }
                if ($status !== 'ok') {
                    $summary['total_alerts']++;
                }
            }
            
            Response::success([
                'alerts' => $alerts,
                'summary' => $summary
            ], 'Reorder alerts retrieved successfully');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle POST requests - Receive new batch, create ingredient
 */
function handlePost($db, $currentUser) {
    // Only warehouse_raw and GM can receive stock
    if (!in_array($currentUser['role'], ['warehouse_raw', 'general_manager', 'purchaser'])) {
        Response::error('Permission denied', 403);
    }
    
    $action = getParam('action', 'receive');
    
    switch ($action) {
        case 'request_opening_stock':
            if (!in_array($currentUser['role'], ['warehouse_raw', 'general_manager'], true)) {
                Response::error('Only Warehouse Raw or GM can record unlisted stock', 403);
            }

            $ingredientId = (int) getParam('ingredient_id', 0);
            $sourceType = hfPlainText(getParam('source_type'), 30, false);
            $sourceReference = hfPlainText(getParam('source_reference'), 100, false);
            $supplierBatchNo = hfPlainText(getParam('supplier_batch_no'), 50, false);
            $receivedDate = hfPlainText(getParam('received_date'), 10, false);
            $expiryDate = hfPlainText(getParam('expiry_date'), 10, false);
            $reason = hfPlainText(getParam('reason'), 500, false);
            $supplierId = (int) getParam('supplier_id', 0);
            $requestedHeldBatchId = (int) getParam('held_batch_id', 0);
            try {
                $countedQuantity = hfParseBusinessDecimal(getParam('counted_quantity'), 'Counted quantity', 0.01, 99999999.99, 2);
            } catch (InvalidArgumentException $error) {
                Response::error($error->getMessage(), 400);
            }

            if ($ingredientId <= 0 || !in_array($sourceType, ['opening_balance', 'unrecorded_delivery'], true)) {
                Response::error('Choose the ingredient and where the unrecorded stock came from', 400);
            }
            if ($reason === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $receivedDate)) {
                Response::error('Received date and explanation are required', 400);
            }
            if ($receivedDate > date('Y-m-d')) {
                Response::error('Received date cannot be in the future', 400);
            }
            if ($sourceType === 'unrecorded_delivery' && $supplierId <= 0) {
                Response::error('Choose the supplier for an unrecorded delivery', 400);
            }
            if ($sourceType === 'unrecorded_delivery' && $sourceReference === '') {
                Response::error('Choose the saved order or enter the delivery document number', 400);
            }

            try {
                $db->beginTransaction();
                $ingredientStmt = $db->prepare('SELECT * FROM ingredients WHERE id = ? AND is_active = 1 FOR UPDATE');
                $ingredientStmt->execute([$ingredientId]);
                $ingredient = $ingredientStmt->fetch(PDO::FETCH_ASSOC);
                if (!$ingredient) throw new RuntimeException('Ingredient not found');

                $isPerishable = (int) ($ingredient['is_perishable'] ?? 1) === 1;
                if ($isPerishable && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiryDate)) {
                    throw new RuntimeException('Perishable stock requires an expiry date');
                }
                if ($expiryDate !== '' && ($expiryDate <= date('Y-m-d') || $expiryDate < $receivedDate)) {
                    throw new RuntimeException('Expiry date must be after today and cannot be before the received date');
                }

                $heldBatchId = null;
                $requestPurpose = 'found_stock';
                $systemQuantity = max(
                    (float) ($ingredient['current_stock'] ?? 0),
                    getAccountedIngredientBatchStock($db, $ingredientId)
                );
                $quantityToAdd = round($countedQuantity - $systemQuantity, 3);

                if ($requestedHeldBatchId > 0) {
                    if (!$isPerishable) {
                        throw new RuntimeException('This item does not need a perishable lot correction');
                    }
                    $heldStmt = $db->prepare("SELECT id, remaining_quantity
                        FROM ingredient_batches
                        WHERE id = ? AND ingredient_id = ?
                          AND status IN ('available', 'partially_used')
                          AND remaining_quantity > 0
                          AND NULLIF(TRIM(supplier_batch_no), '') IS NULL
                        FOR UPDATE");
                    $heldStmt->execute([$requestedHeldBatchId, $ingredientId]);
                    $selectedHeldBatch = $heldStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$selectedHeldBatch) {
                        throw new RuntimeException('That held batch is no longer waiting for lot details. Refresh and try again.');
                    }
                    $systemQuantity = getUsableIngredientBatchStock($db, $ingredientId);
                    $quantityToAdd = (float) $selectedHeldBatch['remaining_quantity'];
                    $expectedCount = round($systemQuantity + $quantityToAdd, 3);
                    if (abs($countedQuantity - $expectedCount) > 0.0005) {
                        throw new RuntimeException("This batch contains {$quantityToAdd} {$ingredient['unit_of_measure']}. The checked total must be {$expectedCount} {$ingredient['unit_of_measure']}.");
                    }
                    $heldBatchId = (int) $selectedHeldBatch['id'];
                    $requestPurpose = 'traceability_correction';
                } elseif ($isPerishable) {
                    $heldStmt = $db->prepare("SELECT id, remaining_quantity
                        FROM ingredient_batches
                        WHERE ingredient_id = ?
                          AND status IN ('available', 'partially_used')
                          AND remaining_quantity > 0
                          AND NULLIF(TRIM(supplier_batch_no), '') IS NULL
                        ORDER BY id");
                    $heldStmt->execute([$ingredientId]);
                    $heldBatches = $heldStmt->fetchAll(PDO::FETCH_ASSOC);
                    if (count($heldBatches) > 1) {
                        throw new RuntimeException('More than one batch is missing its lot details. Open the item and choose Record Lot Details beside one batch at a time.');
                    }
                    if (count($heldBatches) === 1) {
                        $systemQuantity = getUsableIngredientBatchStock($db, $ingredientId);
                        $heldQuantity = (float) $heldBatches[0]['remaining_quantity'];
                        $quantityToAdd = $heldQuantity;
                        if (abs($countedQuantity - ($systemQuantity + $heldQuantity)) > 0.0005) {
                            throw new RuntimeException("This item already has {$heldQuantity} {$ingredient['unit_of_measure']} on hold. Enter the full shelf count of " . ($systemQuantity + $heldQuantity) . " {$ingredient['unit_of_measure']} to correct that held batch first.");
                        }
                        $heldBatchId = (int) $heldBatches[0]['id'];
                        $requestPurpose = 'traceability_correction';
                    }
                }

                if ($quantityToAdd <= 0.0005) {
                    throw new RuntimeException($requestPurpose === 'traceability_correction'
                        ? 'This held batch no longer has stock to correct'
                        : 'The counted quantity must be higher than the stock currently on file');
                }

                $pendingStmt = $db->prepare("SELECT request_code FROM ingredient_opening_stock_requests WHERE ingredient_id = ? AND status = 'pending' LIMIT 1 FOR UPDATE");
                $pendingStmt->execute([$ingredientId]);
                $pendingCode = $pendingStmt->fetchColumn();
                if ($pendingCode) throw new RuntimeException("{$pendingCode} is already moving through price, QC, or GM review for this ingredient");

                if ($supplierId > 0) {
                    $supplierStmt = $db->prepare("
                        SELECT s.id
                        FROM supplier_ingredients si
                        JOIN suppliers s ON s.id = si.supplier_id
                        WHERE si.ingredient_id = ? AND si.supplier_id = ?
                          AND si.is_active = 1 AND s.is_active = 1
                    ");
                    $supplierStmt->execute([$ingredientId, $supplierId]);
                    if (!$supplierStmt->fetchColumn()) {
                        throw new RuntimeException('That supplier is not approved to provide this ingredient');
                    }
                }

                $requestCode = ingredientOpeningStockCode($db);
                if ($sourceType === 'opening_balance') {
                    $sourceReference = 'Opening count ' . $requestCode;
                }
                if ($isPerishable && $supplierBatchNo === '') {
                    throw new RuntimeException('Perishable stock needs the real lot number from the package or supplier document. Hold it and contact QC if the lot is missing or unreadable.');
                }

                $priceMatch = $sourceType === 'unrecorded_delivery'
                    ? findIngredientOpeningStockPrice($db, $ingredientId, $supplierId, $sourceReference)
                    : null;
                $unitCost = $priceMatch['unit_cost'] ?? null;
                $priceStatus = $priceMatch ? 'matched_po' : 'pending';
                $priceReference = $priceMatch['price_reference'] ?? null;
                $matchedPoId = $priceMatch['po_id'] ?? null;
                $qcStatus = $isPerishable ? 'pending' : 'not_required';

                $insert = $db->prepare("INSERT INTO ingredient_opening_stock_requests
                    (request_code, ingredient_id, system_quantity, counted_quantity, quantity_to_add,
                     unit, source_type, supplier_id, source_reference, supplier_batch_no,
                     received_date, expiry_date, unit_cost, price_status, price_reference,
                      matched_po_id, qc_status, reason, requested_by, request_purpose, held_batch_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insert->execute([
                    $requestCode,
                    $ingredientId,
                    $systemQuantity,
                    $countedQuantity,
                    $quantityToAdd,
                    $ingredient['unit_of_measure'],
                    $sourceType,
                    $supplierId ?: null,
                    $sourceReference,
                    $supplierBatchNo ?: null,
                    $receivedDate,
                    $expiryDate ?: null,
                    $unitCost,
                    $priceStatus,
                    $priceReference,
                    $matchedPoId,
                    $qcStatus,
                    $reason,
                    (int) $currentUser['user_id'],
                    $requestPurpose,
                    $heldBatchId,
                ]);
                $requestId = (int) $db->lastInsertId();
                logAudit($currentUser['user_id'], 'REQUEST_OPENING_STOCK', 'ingredient_opening_stock_requests', $requestId, null, [
                    'request_code' => $requestCode,
                    'ingredient_id' => $ingredientId,
                    'quantity_to_add' => $quantityToAdd,
                    'request_purpose' => $requestPurpose,
                    'status' => 'pending',
                ]);
                if ($priceStatus === 'pending') {
                    writeProcurementNotification(
                        $db,
                        'purchaser',
                        'found_stock_price_check',
                        'Found stock needs a price check',
                        "{$requestCode}: {$ingredient['ingredient_name']} needs its PO/invoice cost verified.",
                        'ingredient_opening_stock',
                        $requestId
                    );
                }
                if ($isPerishable) {
                    writeProcurementNotification(
                        $db,
                        'qc_officer',
                        'found_stock_qc_check',
                        'Found perishable stock needs QC',
                        "{$requestCode}: inspect {$ingredient['ingredient_name']} before it can become usable.",
                        'ingredient_opening_stock',
                        $requestId
                    );
                }
                if ($priceStatus === 'matched_po' && !$isPerishable) {
                    writeProcurementNotification(
                        $db,
                        'general_manager',
                        'found_stock_ready_for_gm',
                        'Found stock ready for final review',
                        "{$requestCode}: the PO price was matched and no QC check is required.",
                        'ingredient_opening_stock',
                        $requestId
                    );
                }
                $db->commit();
                Response::success([
                    'id' => $requestId,
                    'request_code' => $requestCode,
                    'quantity_to_add' => $quantityToAdd,
                    'status' => 'pending',
                    'price_status' => $priceStatus,
                    'qc_status' => $qcStatus,
                ], $requestPurpose === 'traceability_correction'
                    ? "{$requestCode} sent for review. The held batch will become usable only after Purchasing, QC, and GM finish their checks."
                    : ($priceMatch
                        ? "{$requestCode} matched to {$priceMatch['po_number']}. Required checks will continue before GM approval."
                        : "{$requestCode} sent for price and safety checks. Stock will change only after GM approval."));
            } catch (Throwable $error) {
                if ($db->inTransaction()) $db->rollBack();
                Response::error($error->getMessage(), 400);
            }
            break;

        case 'receive':
            Response::error('Manual receiving is disabled. Use the PO receiving workflow.', 403);
            break;
            
        case 'create':
            // Create new ingredient (GM/Purchaser only)
            if (!in_array($currentUser['role'], ['general_manager', 'purchaser'])) {
                Response::error('Only GM or Purchaser can create ingredients', 403);
            }

            $ingredientCode = hfPlainText(getParam('ingredient_code'), 40, false);
            $ingredientName = hfPlainText(getParam('ingredient_name'), 160, false);
            $categoryId = getParam('category_id');
            $unitOfMeasure = hfPlainText(getParam('unit_of_measure'), 40, false);
            $minimumStock = getParam('minimum_stock', 0);
            $maximumStock = getParam('maximum_stock');
            $storageLocation = hfPlainText(getParam('storage_location'), 160, false);
            $storageRequirements = hfPlainText(getParam('storage_requirements'), 1000, true);
            $shelfLifeDays = getParam('shelf_life_days');
            $isPerishable = getParam('is_perishable', 1);
            $packSizeValue = getParam('pack_size_value');
            $packSizeUnit = hfPlainText(getParam('pack_size_unit'), 40, false);
            $packLabel = hfPlainText(getParam('pack_label'), 50, false);

            if ($categoryId) {
                $categoryStmt = $db->prepare('SELECT category_name FROM ingredient_categories WHERE id = ?');
                $categoryStmt->execute([(int) $categoryId]);
                $categoryName = strtolower(trim((string) $categoryStmt->fetchColumn()));
                if ($categoryName !== '' && strpos($categoryName, 'packaging') !== false) {
                    $isPerishable = 0;
                    $shelfLifeDays = null;
                }
            }

            if (!$ingredientCode || !$ingredientName || !$unitOfMeasure) {
                Response::error('Ingredient code, name, and unit of measure are required', 400);
            }

            // Pack size sanity: if any pack field is provided, value+unit must both be present and positive.
            $hasAnyPack = ($packSizeValue !== null && $packSizeValue !== '')
                       || ($packSizeUnit !== null && $packSizeUnit !== '')
                       || ($packLabel !== null && $packLabel !== '');
            $hasAllPack = $packSizeValue !== null && $packSizeValue !== ''
                       && $packSizeUnit !== null && $packSizeUnit !== '';
            if ($hasAnyPack && !$hasAllPack) {
                Response::error('Pack size requires both a value and a unit', 400);
            }
            if ($hasAllPack && floatval($packSizeValue) <= 0) {
                Response::error('Pack size value must be greater than 0', 400);
            }

            // Check duplicate
            $check = $db->prepare("SELECT id FROM ingredients WHERE ingredient_code = ?");
            $check->execute([$ingredientCode]);
            if ($check->fetch()) {
                Response::error('Ingredient code already exists', 400);
            }

            $stmt = $db->prepare("
                INSERT INTO ingredients
                (ingredient_code, ingredient_name, category_id, unit_of_measure,
                 pack_size_value, pack_size_unit, pack_label,
                 minimum_stock, maximum_stock, storage_location, storage_requirements, shelf_life_days, is_perishable)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $ingredientCode,
                $ingredientName,
                $categoryId,
                $unitOfMeasure,
                $hasAllPack ? floatval($packSizeValue) : null,
                $hasAllPack ? $packSizeUnit : null,
                $packLabel ?: null,
                $minimumStock,
                $maximumStock !== null && $maximumStock !== '' ? $maximumStock : null,
                $storageLocation,
                $storageRequirements,
                $shelfLifeDays,
                $isPerishable ? 1 : 0
            ]);

            Response::success(['id' => $db->lastInsertId()], 'Ingredient created successfully');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle PUT requests - Issue, adjust stock
 */
function handlePut($db, $currentUser) {
    $action = getParam('action');
    
    if (!$action) {
        Response::error('Action is required', 400);
    }
    
    switch ($action) {
        case 'issue':
            // Issue ingredients (FIFO)
            $ingredientId = getParam('ingredient_id');
            $quantity = getParam('quantity');
            $requisitionId = getParam('requisition_id');
            $reason = getParam('reason', 'Issued for production');
            
            if (!$ingredientId || !$quantity || $quantity <= 0) {
                Response::error('Ingredient ID and valid quantity are required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Get ingredient info
                $ingredient = $db->prepare("SELECT * FROM ingredients WHERE id = ? AND is_active = 1 FOR UPDATE");
                $ingredient->execute([$ingredientId]);
                $ingredientData = $ingredient->fetch();
                
                if (!$ingredientData) {
                    throw new Exception('Ingredient not found');
                }
                
                $batchList = getUsableIngredientBatches($db, $ingredientId, true);
                
                $totalAvailable = array_sum(array_column($batchList, 'remaining_quantity'));
                
                if ($totalAvailable < $quantity) {
                    throw new Exception("Insufficient stock. Available: {$totalAvailable} {$ingredientData['unit_of_measure']}, Needed: {$quantity}");
                }
                
                $remainingToIssue = $quantity;
                $issuedBatches = [];
                
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
                    
                    // Create transaction record
                    $txCode = generateCode('TX');
                    $stmt = $db->prepare("
                        INSERT INTO inventory_transactions 
                        (transaction_code, transaction_type, item_type, item_id, batch_id,
                         quantity, unit_of_measure, reference_type, reference_id,
                         from_location, performed_by, reason)
                        VALUES (?, 'production_issue', 'ingredient', ?, ?, ?, ?, 'requisition', ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $txCode,
                        $ingredientId,
                        $batch['id'],
                        $issueFromBatch,
                        $ingredientData['unit_of_measure'],
                        $requisitionId,
                        $ingredientData['storage_location'],
                        $currentUser['user_id'],
                        $reason
                    ]);
                    
                    $issuedBatches[] = [
                        'batch_id' => $batch['id'],
                        'batch_code' => $batch['batch_code'],
                        'quantity_issued' => $issueFromBatch,
                        'transaction_code' => $txCode
                    ];
                    
                    $remainingToIssue -= $issueFromBatch;
                }
                
                // Update ingredient current stock
                $stmt = $db->prepare("
                    UPDATE ingredients 
                    SET current_stock = GREATEST(current_stock - ?, 0), updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$quantity, $ingredientId]);
                
                $db->commit();

                Response::success([
                    'ingredient_code' => $ingredientData['ingredient_code'],
                    'total_issued' => $quantity,
                    'batches' => $issuedBatches
                ], 'Ingredients issued successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;

        case 'repair_batches':
            if (!in_array($currentUser['role'], ['warehouse_raw', 'general_manager'])) {
                Response::error('Only Warehouse Raw or GM can repair stock records', 403);
            }

            $ingredientId = getParam('ingredient_id');
            if (!$ingredientId) {
                Response::error('Ingredient ID is required', 400);
            }

            try {
                $db->beginTransaction();

                $ingredient = $db->prepare("SELECT * FROM ingredients WHERE id = ? AND is_active = 1 FOR UPDATE");
                $ingredient->execute([$ingredientId]);
                $ingredientData = $ingredient->fetch();

                if (!$ingredientData) {
                    throw new Exception('Ingredient not found');
                }

                if ((int) ($ingredientData['is_perishable'] ?? 1) === 1) {
                    throw new Exception(
                        'Perishable stock cannot be repaired automatically. Use Record Found Stock and provide the real supplier lot and printed expiry for review.'
                    );
                }

                $repair = reconcileIngredientSummaryToBatches(
                    $db,
                    $ingredientData,
                    $currentUser,
                    'Created to repair missing FIFO batch from existing stock on file'
                );

                if (!$repair) {
                    $usableStock = getUsableIngredientBatchStock($db, $ingredientId);
                    $accountedStock = getAccountedIngredientBatchStock($db, $ingredientId);
                    $expiredStock = getExpiredIngredientBatchStock($db, $ingredientId);
                    if ($expiredStock > 0.0005 && abs((float) $ingredientData['current_stock'] - $accountedStock) <= 0.0005) {
                        throw new Exception(
                            "No batch record is missing. {$expiredStock} {$ingredientData['unit_of_measure']} is expired and blocked from use. Confirm the physical stock, then record it as waste."
                        );
                    }
                    throw new Exception(
                        "No missing batch record to repair. Stock on file: {$ingredientData['current_stock']}, physically accounted: {$accountedStock}, usable: {$usableStock}"
                    );
                }

                $db->commit();

                Response::success($repair, 'Missing FIFO batch repaired');
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'adjust':
            // Adjust stock (physical count correction)
            if (!in_array($currentUser['role'], ['warehouse_raw', 'general_manager'])) {
                Response::error('Only Warehouse Raw or GM can adjust stock', 403);
            }
            
            $ingredientId = (int) getParam('ingredient_id', 0);
            $newQuantity = getParam('new_quantity');
            $reason = hfPlainText(getParam('reason'), 500, true);
            
            if ($ingredientId <= 0 || $newQuantity === null || $reason === '') {
                Response::error('Ingredient ID, new quantity, and reason are required', 400);
            }
            try {
                $newQuantity = hfParseBusinessDecimal(
                    $newQuantity,
                    'New physical stock quantity',
                    0.00,
                    99999999.99,
                    2
                );
            } catch (InvalidArgumentException $error) {
                Response::error($error->getMessage(), 400);
            }
            
            try {
                $db->beginTransaction();
                // Get current stock
                $ingredient = $db->prepare("SELECT * FROM ingredients WHERE id = ? AND is_active = 1");
                $ingredient->execute([$ingredientId]);
                $ingredientData = $ingredient->fetch();
                
                if (!$ingredientData) {
                    throw new Exception('Ingredient not found');
                }
                
                $oldQuantity = (float) $ingredientData['current_stock'];
                $difference = $newQuantity - $oldQuantity;

                if ($newQuantity < 0) {
                    throw new Exception('New quantity cannot be negative');
                }

                if (abs($difference) <= 0.0005) {
                    throw new Exception('The new physical count is the same as the current stock on file.');
                }

                $accountedStock = getAccountedIngredientBatchStock($db, $ingredientId);
                $usableStock = getUsableIngredientBatchStock($db, $ingredientId);
                $restrictedStock = max(0, round($accountedStock - $usableStock, 3));
                if ($difference < -0.0005 && $restrictedStock > 0.0005) {
                    throw new Exception(
                        "This item has {$restrictedStock} {$ingredientData['unit_of_measure']} of expired or held stock. " .
                        'Record the affected batch through Spoilage & Waste instead of using a general stock adjustment.'
                    );
                }

                if ($difference > 0.0005) {
                    if (stripos($reason, 'damage') !== false || stripos($reason, 'spoil') !== false) {
                        throw new Exception('Damage or spoilage cannot increase stock. Enter the physical count or choose the correct reason.');
                    }
                    $adjustedBatches = increaseIngredientBatchesToQuantity(
                        $db,
                        $ingredientData,
                        $newQuantity,
                        $currentUser,
                        $reason
                    );
                } else {
                    $adjustedBatches = reduceIngredientBatchesToQuantity(
                        $db,
                        $ingredientData,
                        $newQuantity,
                        $currentUser,
                        $reason
                    );
                }
                
                // Update ingredient stock
                $stmt = $db->prepare("
                    UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?
                ");
                $stmt->execute([$newQuantity, $ingredientId]);
                
                // Create adjustment transaction
                $txCode = generateCode('TX');
                $stmt = $db->prepare("
                    INSERT INTO inventory_transactions 
                    (transaction_code, transaction_type, item_type, item_id,
                     quantity, unit_of_measure, quantity_before, quantity_after,
                     performed_by, reason)
                    VALUES (?, 'physical_adjust', 'ingredient', ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $txCode,
                    $ingredientId,
                    $difference,
                    $ingredientData['unit_of_measure'],
                    $oldQuantity,
                    $newQuantity,
                    $currentUser['user_id'],
                    "Stock adjustment: $reason (Old: $oldQuantity, New: $newQuantity)"
                ]);
                
                // Log audit
                logAudit($currentUser['user_id'], 'adjust_stock', 'ingredients', $ingredientId, 
                    ['current_stock' => $oldQuantity], 
                    ['current_stock' => $newQuantity, 'reason' => $reason]
                );
                
                $db->commit();

                Response::success([
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $newQuantity,
                    'difference' => $difference,
                    'adjusted_batches' => $adjustedBatches,
                    'transaction_code' => $txCode
                ], 'Stock adjusted successfully');
                
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'update':
            // Update ingredient details (GM/Purchaser only)
            if (!in_array($currentUser['role'], ['general_manager', 'purchaser'])) {
                Response::error('Only GM or Purchaser can update ingredients', 403);
            }
            
            $id = getParam('id');
            if (!$id) {
                Response::error('Ingredient ID is required', 400);
            }
            
            $updateFields = [];
            $params = [];
            
            $allowedFields = ['ingredient_name', 'category_id', 'minimum_stock', 'maximum_stock',
                             'storage_location', 'storage_requirements', 'shelf_life_days', 'is_perishable',
                             'pack_size_value', 'pack_size_unit', 'pack_label'];
            $plainTextFields = [
                'ingredient_name' => [160, false],
                'storage_location' => [160, false],
                'storage_requirements' => [1000, true],
                'pack_size_unit' => [40, false],
                'pack_label' => [50, false]
            ];

            foreach ($allowedFields as $field) {
                $value = getParam($field);
                if ($value !== null) {
                    if (isset($plainTextFields[$field])) {
                        [$limit, $preserveNewlines] = $plainTextFields[$field];
                        $value = hfPlainText($value, $limit, $preserveNewlines);
                    }
                    if ($field === 'is_perishable') {
                        $value = $value ? 1 : 0;
                    }
                    if ($field === 'pack_size_value') {
                        // Empty string clears the pack size; positive number sets it.
                        $value = ($value === '' || $value === null) ? null : floatval($value);
                        if ($value !== null && $value <= 0) {
                            Response::error('Pack size value must be greater than 0', 400);
                        }
                    }
                    if ($field === 'pack_size_unit' && $value === '') {
                        $value = null;
                    }
                    if ($field === 'pack_label' && $value === '') {
                        $value = null;
                    }
                    $updateFields[] = "$field = ?";
                    $params[] = $value;
                }
            }
            
            if (empty($updateFields)) {
                Response::error('No fields to update', 400);
            }
            
            $updateFields[] = "updated_at = NOW()";
            $params[] = $id;
            
            $stmt = $db->prepare("
                UPDATE ingredients 
                SET " . implode(', ', $updateFields) . "
                WHERE id = ?
            ");
            $stmt->execute($params);
            
            Response::success(null, 'Ingredient updated successfully');
            break;
            
        case 'dispose':
            // Dispose expired or damaged batch
            $batchId = getParam('batch_id');
            $reason = getParam('reason');
            
            if (!$batchId || !$reason) {
                Response::error('Batch ID and reason are required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Get batch info
                $batch = $db->prepare("
                    SELECT ib.*, i.unit_of_measure, i.ingredient_code
                    FROM ingredient_batches ib
                    JOIN ingredients i ON ib.ingredient_id = i.id
                    WHERE ib.id = ?
                ");
                $batch->execute([$batchId]);
                $batchData = $batch->fetch();
                
                if (!$batchData) {
                    throw new Exception('Batch not found');
                }
                
                if ($batchData['status'] === 'consumed') {
                    throw new Exception('Batch already consumed');
                }
                
                $disposedQuantity = $batchData['remaining_quantity'];
                
                // Update batch status
                $stmt = $db->prepare("
                    UPDATE ingredient_batches 
                    SET status = 'returned', remaining_quantity = 0, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$batchId]);
                
                // Update ingredient stock
                $stmt = $db->prepare("
                    UPDATE ingredients 
                    SET current_stock = current_stock - ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$disposedQuantity, $batchData['ingredient_id']]);
                
                // Create transaction
                $txCode = generateCode('TX');
                $stmt = $db->prepare("
                    INSERT INTO inventory_transactions 
                    (transaction_code, transaction_type, item_type, item_id, batch_id,
                     quantity, unit_of_measure, performed_by, reason)
                    VALUES (?, 'dispose', 'ingredient', ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $txCode,
                    $batchData['ingredient_id'],
                    $batchId,
                    $disposedQuantity,
                    $batchData['unit_of_measure'],
                    $currentUser['user_id'],
                    $reason
                ]);
                
                $db->commit();

                Response::success([
                    'disposed_quantity' => $disposedQuantity,
                    'transaction_code' => $txCode
                ], 'Batch disposed successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'update_settings':
            // Update ingredient settings (min stock, lead time, reorder point)
            $settingsInput = getRequestBody();
            $ingredientId = getParam('ingredient_id');
            $minimumStock = getParam('minimum_stock');
            $leadTimeDays = getParam('lead_time_days');
            $reorderPoint = getParam('reorder_point');
            $maximumStock = getParam('maximum_stock');
            $hasReorderPoint = array_key_exists('reorder_point', $settingsInput);
            $hasMaximumStock = array_key_exists('maximum_stock', $settingsInput);
            
            if (!$ingredientId) {
                Response::error('Ingredient ID is required', 400);
            }
            
            // Verify ingredient exists
            $checkStmt = $db->prepare("SELECT * FROM ingredients WHERE id = ?");
            $checkStmt->execute([$ingredientId]);
            $ingredient = $checkStmt->fetch();
            
            if (!$ingredient) {
                Response::notFound('Ingredient not found');
            }

            $nextMinimumStock = ($minimumStock !== null && $minimumStock !== '')
                ? (float) $minimumStock
                : (float) $ingredient['minimum_stock'];
            $nextReorderPoint = $hasReorderPoint
                ? (($reorderPoint === null || $reorderPoint === '') ? null : (float) $reorderPoint)
                : ((float) ($ingredient['reorder_point'] ?? 0) > 0 ? (float) $ingredient['reorder_point'] : null);
            $nextMaximumStock = $hasMaximumStock
                ? (($maximumStock === null || $maximumStock === '') ? null : (float) $maximumStock)
                : ((float) ($ingredient['maximum_stock'] ?? 0) > 0 ? (float) $ingredient['maximum_stock'] : null);
            $thresholdError = StockRule::thresholdValidationError(
                $nextMinimumStock,
                $nextReorderPoint,
                $nextMaximumStock
            );
            if ($thresholdError !== null) {
                Response::error($thresholdError, 400);
            }
            
            // Build dynamic update query
            $updates = [];
            $params = [];
            
            if ($minimumStock !== null && $minimumStock !== '') {
                $updates[] = "minimum_stock = ?";
                $params[] = floatval($minimumStock);
            }
            
            if ($leadTimeDays !== null && $leadTimeDays !== '') {
                $updates[] = "lead_time_days = ?";
                $params[] = intval($leadTimeDays);
            }
            
            if ($hasReorderPoint) {
                $updates[] = "reorder_point = ?";
                $params[] = ($reorderPoint === null || $reorderPoint === '') ? null : floatval($reorderPoint);
            }

            if ($hasMaximumStock) {
                $updates[] = "maximum_stock = ?";
                $params[] = ($maximumStock === null || $maximumStock === '') ? null : floatval($maximumStock);
            }
            
            if (empty($updates)) {
                Response::error('No settings provided to update', 400);
            }
            
            $params[] = $ingredientId;
            
            $sql = "UPDATE ingredients SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            // Log the update
            logAudit($currentUser['user_id'], 'UPDATE_SETTINGS', 'ingredients', $ingredientId, null, [
                'minimum_stock' => $minimumStock,
                'lead_time_days' => $leadTimeDays,
                'reorder_point' => $reorderPoint,
                'maximum_stock' => $maximumStock
            ]);
            
            // Get updated ingredient
            $stmt = $db->prepare("SELECT * FROM ingredients WHERE id = ?");
            $stmt->execute([$ingredientId]);
            $updated = $stmt->fetch();
            
            Response::success([
                'id' => $ingredientId,
                'minimum_stock' => $updated['minimum_stock'],
                'lead_time_days' => $updated['lead_time_days'],
                'reorder_point' => $updated['reorder_point'],
                'maximum_stock' => $updated['maximum_stock']
            ], 'Ingredient settings updated successfully');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
