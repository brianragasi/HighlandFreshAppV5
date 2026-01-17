<?php
/**
 * Highland Fresh System - Customer Product Catalog API
 * 
 * GET - List products, get product details
 * 
 * Public access for browsing, some features require customer login
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Customer Products API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            getProductList($db);
            break;
        case 'detail':
            getProductDetail($db);
            break;
        case 'categories':
            getCategories($db);
            break;
        case 'featured':
            getFeaturedProducts($db);
            break;
        case 'search':
            searchProducts($db);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function getProductList($db) {
    $category = getParam('category');
    $search = getParam('search');
    $limit = min((int)getParam('limit', 50), 100);
    $offset = (int)getParam('offset', 0);
    
    $sql = "
        SELECT 
            p.id,
            p.product_code,
            p.name,
            p.description,
            p.category,
            p.unit,
            p.price,
            p.image_url,
            COALESCE(
                (SELECT SUM(fgi.quantity_available) 
                 FROM finished_goods_inventory fgi 
                 WHERE fgi.product_id = p.id AND fgi.status = 'available'),
                0
            ) as stock_available,
            p.is_active
        FROM products p
        WHERE p.is_active = 1
    ";
    $params = [];
    
    if ($category) {
        $sql .= " AND p.category = ?";
        $params[] = $category;
    }
    
    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.product_code LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " ORDER BY p.name ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM products p WHERE p.is_active = 1";
    $countParams = [];
    if ($category) {
        $countSql .= " AND p.category = ?";
        $countParams[] = $category;
    }
    if ($search) {
        $countSql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $searchTerm = "%$search%";
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
    }
    
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetchColumn();
    
    Response::success([
        'products' => $products,
        'pagination' => [
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ]
    ], 'Products retrieved');
}

function getProductDetail($db) {
    $id = getParam('id');
    
    if (!$id) {
        Response::error('Product ID required', 400);
    }
    
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.product_code,
            p.name,
            p.description,
            p.category,
            p.unit,
            p.price,
            p.image_url,
            p.shelf_life_days,
            COALESCE(
                (SELECT SUM(fgi.quantity_available) 
                 FROM finished_goods_inventory fgi 
                 WHERE fgi.product_id = p.id AND fgi.status = 'available'),
                0
            ) as stock_available
        FROM products p
        WHERE p.id = ? AND p.is_active = 1
    ");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        Response::error('Product not found', 404);
    }
    
    // Get variants if any
    $variantStmt = $db->prepare("
        SELECT id, variant_name, variant_value, price_modifier, sku
        FROM product_variants
        WHERE product_id = ? AND is_active = 1
    ");
    $variantStmt->execute([$id]);
    $variants = $variantStmt->fetchAll();
    
    $product['variants'] = $variants;
    
    Response::success($product, 'Product details retrieved');
}

function getCategories($db) {
    $stmt = $db->prepare("
        SELECT DISTINCT category, COUNT(*) as product_count
        FROM products
        WHERE is_active = 1 AND category IS NOT NULL
        GROUP BY category
        ORDER BY category
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    Response::success($categories, 'Categories retrieved');
}

function getFeaturedProducts($db) {
    // Get top-selling or featured products
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.product_code,
            p.name,
            p.description,
            p.category,
            p.unit,
            p.price,
            p.image_url,
            COALESCE(
                (SELECT SUM(fgi.quantity_available) 
                 FROM finished_goods_inventory fgi 
                 WHERE fgi.product_id = p.id AND fgi.status = 'available'),
                0
            ) as stock_available
        FROM products p
        WHERE p.is_active = 1
        ORDER BY p.id DESC
        LIMIT 8
    ");
    $stmt->execute();
    $products = $stmt->fetchAll();
    
    Response::success($products, 'Featured products retrieved');
}

function searchProducts($db) {
    $query = getParam('q');
    
    if (empty($query) || strlen($query) < 2) {
        Response::error('Search query must be at least 2 characters', 400);
    }
    
    $searchTerm = "%$query%";
    
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.product_code,
            p.name,
            p.category,
            p.price,
            p.unit,
            p.image_url
        FROM products p
        WHERE p.is_active = 1
        AND (p.name LIKE ? OR p.description LIKE ? OR p.product_code LIKE ?)
        ORDER BY 
            CASE WHEN p.name LIKE ? THEN 1 ELSE 2 END,
            p.name
        LIMIT 20
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $query . '%']);
    $products = $stmt->fetchAll();
    
    Response::success($products, 'Search results');
}
