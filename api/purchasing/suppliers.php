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

// Supplier changes are handled only by the GM/Admin endpoint.
$currentUser = Auth::requireRole(['purchaser', 'general_manager', 'admin']);

$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    ensureSupplierIngredientCatalog($db);
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action, $currentUser);
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

            $params = [];
            $sql = "SELECT suppliers.*";
            if ($ingredientIds) {
                $matchingPlaceholders = implode(',', array_fill(0, count($ingredientIds), '?'));
                $sql .= ", (
                    SELECT COUNT(DISTINCT matching_offer.ingredient_id)
                    FROM supplier_ingredients matching_offer
                    JOIN ingredients matching_ingredient
                      ON matching_ingredient.id = matching_offer.ingredient_id
                     AND matching_ingredient.is_active = 1
                    WHERE matching_offer.supplier_id = suppliers.id
                      AND matching_offer.is_active = 1
                      AND matching_offer.ingredient_id IN ($matchingPlaceholders)
                ) AS matching_ingredient_count";
                array_push($params, ...$ingredientIds);
            }
            $sql .= " FROM suppliers WHERE 1=1";
            
            if ($isPurchaser || $status === 'active') {
                $sql .= " AND is_active = 1";
            } elseif ($status === 'inactive') {
                $sql .= " AND is_active = 0";
            }

            if ($orderable) {
                $sql .= " AND EXISTS (
                    SELECT 1
                    FROM supplier_ingredients si
                    JOIN ingredients i ON i.id = si.ingredient_id AND i.is_active = 1
                    WHERE si.supplier_id = suppliers.id
                      AND si.is_active = 1
                ";
                if ($ingredientIds) {
                    $sql .= " AND si.ingredient_id IN ($matchingPlaceholders)";
                    array_push($params, ...$ingredientIds);
                }
                $sql .= ")";
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
            $supplier['ingredients'] = supplierCatalogGetSupplierIngredients($db, (int) $id);
            
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
