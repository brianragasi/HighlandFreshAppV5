<?php
/**
 * Highland Fresh System - Suppliers API
 * 
 * GET - Read-only list of accredited suppliers, details, and search
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/helpers/supplier_ingredient_catalog.php';
require_once dirname(__DIR__) . '/helpers/supplier_mro_catalog.php';
require_once dirname(__DIR__) . '/helpers/supplier_price_list_history.php';
require_once dirname(__DIR__) . '/helpers/supplier_delivery_terms.php';
require_once dirname(__DIR__) . '/warehouse/raw/ingredient_stock_helpers.php';
require_once dirname(__DIR__) . '/helpers/early_reorder.php';

// Supplier changes are handled only by the GM/Admin endpoint.
$currentUser = Auth::requireRole(['purchaser', 'general_manager', 'admin']);

$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    ensureSupplierIngredientCatalog($db);
    ensureSupplierMroCatalog($db);
    ensurePurchaserPriceHistory($db);
    ensureSupplierPriceListHistory($db);
    ensureSupplierDeliveryTerms($db);
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action, $currentUser);
            break;
        case 'PUT':
            if ($action !== 'update_item_price') {
                Response::error('Action not available', 405);
            }
            updateSupplierItemPrice($db, $currentUser);
            break;
        default:
            Response::error('This supplier directory is read-only', 405);
    }
} catch (Exception $e) {
    error_log("Suppliers API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action, $currentUser) {
    $isPurchaser = ($currentUser['role'] ?? '') === 'purchaser';

    switch ($action) {
        case 'list':
            $status = getParam('status');
            $search = getParam('search');
            $orderable = filter_var(getParam('orderable', false), FILTER_VALIDATE_BOOLEAN);
            $ingredientIds = [];
            $ingredientIdsParam = trim((string) getParam('ingredient_ids', ''));
            if ($ingredientIdsParam !== '') {
                foreach (explode(',', $ingredientIdsParam) as $ingredientId) {
                    $ingredientId = filter_var(trim($ingredientId), FILTER_VALIDATE_INT, [
                        'options' => ['min_range' => 1],
                    ]);
                    if ($ingredientId !== false) {
                        $ingredientIds[(int) $ingredientId] = (int) $ingredientId;
                    }
                }
                $ingredientIds = array_slice(array_values($ingredientIds), 0, 200);
            }
            $mroIds = [];
            $mroIdsParam = trim((string) getParam('mro_ids', ''));
            if ($mroIdsParam !== '') {
                foreach (explode(',', $mroIdsParam) as $mroId) {
                    $mroId = filter_var(trim($mroId), FILTER_VALIDATE_INT, [
                        'options' => ['min_range' => 1],
                    ]);
                    if ($mroId !== false) {
                        $mroIds[(int) $mroId] = (int) $mroId;
                    }
                }
                $mroIds = array_slice(array_values($mroIds), 0, 200);
            }
            $hasRequestedItems = (bool) ($ingredientIds || $mroIds);

            $params = [];
            $sql = "SELECT suppliers.*";
            if ($ingredientIds) {
                $matchingPlaceholders = implode(',', array_fill(0, count($ingredientIds), '?'));
                $sql .= ", (
                    SELECT GROUP_CONCAT(DISTINCT matching_offer.ingredient_id ORDER BY matching_offer.ingredient_id)
                    FROM supplier_ingredients matching_offer
                    JOIN ingredients matching_ingredient
                      ON matching_ingredient.id = matching_offer.ingredient_id
                     AND matching_ingredient.is_active = 1
                    WHERE matching_offer.supplier_id = suppliers.id
                      AND matching_offer.is_active = 1
                      AND matching_offer.ingredient_id IN ($matchingPlaceholders)
                ) AS matching_ingredient_ids";
                array_push($params, ...$ingredientIds);
            }
            if ($mroIds) {
                $matchingMroPlaceholders = implode(',', array_fill(0, count($mroIds), '?'));
                $sql .= ", (
                    SELECT GROUP_CONCAT(DISTINCT matching_offer.mro_item_id ORDER BY matching_offer.mro_item_id)
                    FROM supplier_mro_items matching_offer
                    JOIN mro_items matching_item
                      ON matching_item.id = matching_offer.mro_item_id
                     AND matching_item.is_active = 1
                    WHERE matching_offer.supplier_id = suppliers.id
                      AND matching_offer.is_active = 1
                      AND matching_offer.mro_item_id IN ($matchingMroPlaceholders)
                ) AS matching_mro_ids";
                array_push($params, ...$mroIds);
            }
            $sql .= " FROM suppliers WHERE 1=1";
            
            if ($isPurchaser || $status === 'active') {
                $sql .= " AND is_active = 1";
            } elseif ($status === 'inactive') {
                $sql .= " AND is_active = 0";
            }

            if ($orderable) {
                $orderableParts = [];
                if (!$hasRequestedItems || $ingredientIds) {
                    $ingredientClause = "EXISTS (
                        SELECT 1
                        FROM supplier_ingredients si
                        JOIN ingredients i ON i.id = si.ingredient_id AND i.is_active = 1
                        WHERE si.supplier_id = suppliers.id AND si.is_active = 1";
                    if ($ingredientIds) {
                        $ingredientClause .= " AND si.ingredient_id IN ($matchingPlaceholders)";
                        array_push($params, ...$ingredientIds);
                    }
                    $orderableParts[] = $ingredientClause . ")";
                }
                if (!$hasRequestedItems || $mroIds) {
                    $mroClause = "EXISTS (
                        SELECT 1
                        FROM supplier_mro_items smi
                        JOIN mro_items m ON m.id = smi.mro_item_id AND m.is_active = 1
                        WHERE smi.supplier_id = suppliers.id AND smi.is_active = 1";
                    if ($mroIds) {
                        $mroClause .= " AND smi.mro_item_id IN ($matchingMroPlaceholders)";
                        array_push($params, ...$mroIds);
                    }
                    $orderableParts[] = $mroClause . ")";
                }
                $sql .= " AND (" . implode(' OR ', $orderableParts) . ")";
            }
            
            if ($search) {
                $sql .= " AND (supplier_name LIKE ? OR supplier_code LIKE ? OR contact_person LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY supplier_name ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $suppliers = $stmt->fetchAll();
            foreach ($suppliers as &$supplier) {
                foreach (['matching_ingredient_ids', 'matching_mro_ids'] as $matchingField) {
                    $supplier[$matchingField] = empty($supplier[$matchingField])
                        ? []
                        : array_map('intval', explode(',', (string) $supplier[$matchingField]));
                }
                $supplier['matching_ingredient_count'] = count($supplier['matching_ingredient_ids']);
                $supplier['matching_mro_count'] = count($supplier['matching_mro_ids']);
            }
            unset($supplier);
            
            Response::success($suppliers, 'Suppliers retrieved');
            break;
            
        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('Supplier ID required', 400);
            }
            
            $detailSql = "SELECT * FROM suppliers WHERE id = ?";
            if ($isPurchaser) {
                $detailSql .= " AND is_active = 1";
            }
            $stmt = $db->prepare($detailSql);
            $stmt->execute([$id]);
            $supplier = $stmt->fetch();
            
            if (!$supplier) {
                Response::error('Supplier not found', 404);
            }
            
            // Get recent POs for this supplier
            $posStmt = $db->prepare("
                SELECT 
                    po_number, order_date, status, total_amount, payment_status
                FROM purchase_orders
                WHERE supplier_id = ?
                ORDER BY order_date DESC
                LIMIT 10
            ");
            $posStmt->execute([$id]);
            $supplier['recent_orders'] = $posStmt->fetchAll();
            
            // Get total business stats
            $statsStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(total_amount), 0) as total_business,
                    COALESCE(AVG(total_amount), 0) as avg_order_value,
                    MAX(order_date) as last_order_date
                FROM purchase_orders
                WHERE supplier_id = ?
                AND status != 'cancelled'
            ");
            $statsStmt->execute([$id]);
            $supplier['business_stats'] = $statsStmt->fetch();
            $supplier['ingredients'] = addIngredientDemandEvidence(
                $db,
                supplierCatalogGetSupplierIngredients($db, (int) $id),
                (int) ($supplier['lead_time_days'] ?? 0)
            );
            $supplier['mro_items'] = supplierMroGetSupplierItems($db, (int) $id);
            
            Response::success($supplier, 'Supplier details retrieved');
            break;
            
        case 'search':
            $query = getParam('q');
            if (!$query || strlen($query) < 2) {
                Response::success([], 'Search query too short');
                break;
            }
            
            $stmt = $db->prepare("
                SELECT id, supplier_code, supplier_name, contact_person, phone, payment_terms
                FROM suppliers
                WHERE is_active = 1
                AND (supplier_name LIKE ? OR supplier_code LIKE ? OR contact_person LIKE ?)
                ORDER BY supplier_name ASC
                LIMIT 20
            ");
            $searchTerm = "%$query%";
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
            $results = $stmt->fetchAll();
            
            Response::success($results, 'Search results');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

function ensurePurchaserPriceHistory(PDO $db): void {
    $definitions = [
        'ingredient_price_history' => 'ingredient_id',
        'mro_price_history' => 'mro_item_id',
    ];
    foreach ($definitions as $table => $itemColumn) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS {$table} (
                id INT NOT NULL AUTO_INCREMENT,
                {$itemColumn} INT NOT NULL,
                old_price DECIMAL(12,2) NOT NULL,
                new_price DECIMAL(12,2) NOT NULL,
                price_change DECIMAL(12,2) GENERATED ALWAYS AS (new_price - old_price) STORED,
                change_percent DECIMAL(5,2) GENERATED ALWAYS AS (
                    CASE WHEN old_price = 0 THEN 0 ELSE ((new_price - old_price) / old_price) * 100 END
                ) STORED,
                po_id INT NULL,
                supplier_id INT NULL,
                reason VARCHAR(255) NULL,
                updated_by INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_{$table}_item ({$itemColumn}),
                KEY idx_{$table}_supplier (supplier_id),
                KEY idx_{$table}_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
}

function updateSupplierItemPrice(PDO $db, array $currentUser): void {
    if (!in_array(($currentUser['role'] ?? ''), ['purchaser', 'general_manager'], true)) {
        Response::error('Only Purchasing or the General Manager can update supplier prices', 403);
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $supplierId = (int) ($data['supplier_id'] ?? 0);
    $itemType = strtolower(trim((string) ($data['item_type'] ?? '')));
    $itemId = (int) ($data['item_id'] ?? 0);
    $rawPrice = trim((string) ($data['price'] ?? ''));
    $reason = trim((string) ($data['reason'] ?? ''));
    if ($supplierId <= 0 || $itemId <= 0 || !in_array($itemType, ['ingredient', 'mro'], true)) {
        Response::error('Choose a supplier item', 400);
    }
    if (mb_strlen($reason) < 10 || mb_strlen($reason) > 255) {
        Response::error('Explain the price change in 10 to 255 characters', 400);
    }

    $db->beginTransaction();
    try {
        if ($itemType === 'ingredient') {
            $stmt = $db->prepare("
                SELECT si.*, i.ingredient_name
                FROM supplier_ingredients si
                JOIN suppliers s ON s.id = si.supplier_id AND s.is_active = 1
                JOIN ingredients i ON i.id = si.ingredient_id AND i.is_active = 1
                WHERE si.supplier_id = ? AND si.ingredient_id = ? AND si.is_active = 1
                FOR UPDATE
            ");
            $stmt->execute([$supplierId, $itemId]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$offer) throw new InvalidArgumentException('This supplier is not linked to that item');
            $packaged = ($offer['purchase_format'] ?? 'direct_unit') === 'packaged';
            $maxDecimals = $packaged ? 2 : 6;
            if (!supplierCatalogIsPlainDecimal($rawPrice, $maxDecimals)) {
                throw new InvalidArgumentException($offer['ingredient_name'] . ' price must be an ordinary number with no more than ' . $maxDecimals . ' decimal places');
            }
            $price = (float) $rawPrice;
            if ($price <= 0 || $price > 999999.999999) throw new InvalidArgumentException('Price must be greater than zero and no more than PHP 999,999.99');
            $oldUnitPrice = (float) ($offer['reference_unit_price'] ?? 0);
            $oldDisplayedPrice = $packaged
                ? (float) ($offer['quoted_price'] ?? 0)
                : $oldUnitPrice;
            $stockPerPackage = $packaged ? (float) ($offer['package_quantity_in_stock_unit'] ?? 0) : 1.0;
            if ($stockPerPackage <= 0) throw new InvalidArgumentException('The saved package size is incomplete');
            $newUnitPrice = $packaged ? round($price / $stockPerPackage, 6) : $price;
            $quotedPrice = $packaged ? $price : $price;
            $db->prepare("
                UPDATE supplier_ingredients
                SET quoted_price = ?, reference_unit_price = ?, updated_at = NOW()
                WHERE supplier_id = ? AND ingredient_id = ?
            ")->execute([$quotedPrice, $newUnitPrice, $supplierId, $itemId]);
            $db->prepare("
                INSERT INTO ingredient_price_history
                    (ingredient_id, old_price, new_price, po_id, supplier_id, reason, updated_by)
                VALUES (?, ?, ?, NULL, ?, ?, ?)
            ")->execute([$itemId, round($oldUnitPrice, 2), round($newUnitPrice, 2), $supplierId, $reason, (int) $currentUser['user_id']]);
            $db->prepare("
                INSERT INTO supplier_price_list_history
                    (supplier_id, item_type, item_id, old_price, new_price, price_basis, reason, updated_by)
                VALUES (?, 'ingredient', ?, ?, ?, ?, ?, ?)
            ")->execute([
                $supplierId, $itemId, $oldDisplayedPrice, $price,
                $packaged ? ('per ' . (trim((string) ($offer['package_type'] ?? 'package')) ?: 'package')) : 'per stock unit',
                $reason, (int) $currentUser['user_id'],
            ]);
            $result = [
                'item_type' => 'ingredient', 'item_id' => $itemId,
                'quoted_price' => $quotedPrice, 'reference_unit_price' => $newUnitPrice,
                'price_label' => $packaged ? 'whole package' : 'stock unit',
            ];
        } else {
            $stmt = $db->prepare("
                SELECT smi.*, m.item_name
                FROM supplier_mro_items smi
                JOIN suppliers s ON s.id = smi.supplier_id AND s.is_active = 1
                JOIN mro_items m ON m.id = smi.mro_item_id AND m.is_active = 1
                WHERE smi.supplier_id = ? AND smi.mro_item_id = ? AND smi.is_active = 1
                FOR UPDATE
            ");
            $stmt->execute([$supplierId, $itemId]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$offer) throw new InvalidArgumentException('This supplier is not linked to that MRO item');
            if (!supplierMroIsPlainDecimal($rawPrice, 6)) {
                throw new InvalidArgumentException($offer['item_name'] . ' price must be an ordinary number with no more than 6 decimal places');
            }
            $price = (float) $rawPrice;
            if ($price <= 0 || $price > 999999.999999) throw new InvalidArgumentException('Price must be greater than zero and no more than PHP 999,999.99');
            $oldPrice = (float) $offer['reference_unit_price'];
            $db->prepare("UPDATE supplier_mro_items SET reference_unit_price = ?, updated_at = NOW() WHERE supplier_id = ? AND mro_item_id = ?")
                ->execute([$price, $supplierId, $itemId]);
            $db->prepare("
                INSERT INTO mro_price_history
                    (mro_item_id, old_price, new_price, po_id, supplier_id, reason, updated_by)
                VALUES (?, ?, ?, NULL, ?, ?, ?)
            ")->execute([$itemId, round($oldPrice, 2), round($price, 2), $supplierId, $reason, (int) $currentUser['user_id']]);
            $db->prepare("
                INSERT INTO supplier_price_list_history
                    (supplier_id, item_type, item_id, old_price, new_price, price_basis, reason, updated_by)
                VALUES (?, 'mro', ?, ?, ?, 'per stock unit', ?, ?)
            ")->execute([$supplierId, $itemId, $oldPrice, $price, $reason, (int) $currentUser['user_id']]);
            $result = [
                'item_type' => 'mro', 'item_id' => $itemId,
                'quoted_price' => $price, 'reference_unit_price' => $price,
                'price_label' => 'stock unit',
            ];
        }

        logAudit((int) $currentUser['user_id'], 'UPDATE_SUPPLIER_PRICE', 'suppliers', $supplierId, null, [
            'item_type' => $itemType, 'item_id' => $itemId, 'reason' => $reason,
            'new_price' => $result['quoted_price'],
        ]);
        $db->commit();
        Response::success($result, 'Supplier price list updated');
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($e instanceof InvalidArgumentException) Response::error($e->getMessage(), 400);
        throw $e;
    }
}

/**
 * Attach auditable early-reorder evidence for the selected supplier.
 * This is a deterministic calculation, not an opaque AI forecast:
 * usable stock + active PO balance - (30-day daily use x supplier lead time).
 */
function addIngredientDemandEvidence(PDO $db, array $ingredients, int $supplierLeadDays = 0): array {
    if (!$ingredients) return $ingredients;
    $ingredientIds = array_values(array_filter(array_map('intval', array_column($ingredients, 'ingredient_id'))));
    $baseEvidence = ingredientEarlyReorderEvidence($db, $ingredientIds);

    foreach ($ingredients as &$ingredient) {
        $ingredientId = (int) ($ingredient['ingredient_id'] ?? 0);
        if (isset($baseEvidence[$ingredientId])) {
            $base = $baseEvidence[$ingredientId];
            $evidence = calculateIngredientEarlyReorder([
                'usable_stock' => $base['usable_stock'],
                'minimum_stock' => $base['minimum_stock'],
                'reorder_point' => $base['reorder_point'],
                'maximum_stock' => $base['maximum_stock'],
                'issued_quantity_30d' => $base['issued_quantity_30d'],
                'active_po_balance' => $base['on_order_quantity'],
            ], $supplierLeadDays > 0 ? $supplierLeadDays : null);
            $evidence['issue_transaction_count_30d'] = $base['issue_transaction_count_30d'];
            $evidence['current_stock'] = $base['usable_stock'];
            $evidence['lead_time_days'] = $evidence['supplier_lead_days'];
            $evidence['suggested_lead_time_buffer'] = $evidence['lead_time_demand'];
            $ingredient = array_merge($ingredient, $evidence);
        }
    }
    unset($ingredient);

    return $ingredients;
}
