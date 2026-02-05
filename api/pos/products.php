<?php
/**
 * Highland Fresh System - POS Products API
 * 
 * Product lookup for Point of Sale
 * Lists available products with current stock, prices, batch/expiry info
 * 
 * GET - List products, search, lookup by barcode
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Cashier or GM role
$currentUser = Auth::requireRole(['cashier', 'general_manager']);

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
    error_log("POS Products API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

// ========================================
// HELPER FUNCTIONS
// ========================================

/**
 * Get product price (could be extended to support price lists)
 */
function getProductPrice($db, $productId) {
    // Check if there's a prices table
    try {
        $stmt = $db->prepare("
            SELECT selling_price 
            FROM product_prices 
            WHERE product_id = ? AND is_active = 1 
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$productId]);
        $price = $stmt->fetch();
        if ($price) {
            return floatval($price['selling_price']);
        }
    } catch (Exception $e) {
        // Table might not exist, continue with default
    }
    
    // Fallback: Check products table for price column
    try {
        $stmt = $db->prepare("SELECT unit_price, selling_price FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if ($product) {
            return floatval($product['selling_price'] ?? $product['unit_price'] ?? 0);
        }
    } catch (Exception $e) {
        // Column might not exist
    }
    
    return 0;
}

/**
 * Format multi-unit display string
 */
function formatMultiUnitDisplay($boxes, $pieces, $boxUnit = 'Box', $baseUnit = 'Piece') {
    $boxLabel = $boxes == 1 ? ucfirst($boxUnit) : ucfirst($boxUnit) . 's';
    $pieceLabel = $pieces == 1 ? ucfirst($baseUnit) : ucfirst($baseUnit) . 's';
    
    if ($boxes > 0 && $pieces > 0) {
        return "{$boxes} {$boxLabel} + {$pieces} {$pieceLabel}";
    } elseif ($boxes > 0) {
        return "{$boxes} {$boxLabel}";
    } elseif ($pieces > 0) {
        return "{$pieces} {$pieceLabel}";
    }
    return "0 {$baseUnit}s";
}

// ========================================
// GET HANDLERS
// ========================================

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            // List all products with current available stock
            $category = getParam('category');
            $inStockOnly = getParam('in_stock', '1') === '1';
            
            $sql = "
                SELECT 
                    p.id,
                    p.product_code,
                    p.product_name,
                    p.category,
                    p.variant,
                    p.description,
                    p.unit_size,
                    p.unit_measure,
                    p.shelf_life_days,
                    COALESCE(p.base_unit, 'piece') as base_unit,
                    COALESCE(p.box_unit, 'box') as box_unit,
                    COALESCE(p.pieces_per_box, 1) as pieces_per_box,
                    COALESCE(p.selling_price, p.unit_price, 0) as selling_price,
                    COALESCE(p.unit_price, 0) as unit_price,
                    p.is_active,
                    COALESCE(inv.total_available, 0) as stock_available,
                    COALESCE(inv.total_boxes, 0) as boxes_available,
                    COALESCE(inv.total_pieces, 0) as pieces_available,
                    inv.earliest_expiry,
                    inv.batch_count
                FROM products p
                LEFT JOIN (
                    SELECT 
                        product_id,
                        SUM(COALESCE(quantity_available, 0)) as total_available,
                        SUM(COALESCE(boxes_available, 0)) as total_boxes,
                        SUM(COALESCE(pieces_available, 0)) as total_pieces,
                        MIN(expiry_date) as earliest_expiry,
                        COUNT(*) as batch_count
                    FROM finished_goods_inventory
                    WHERE status = 'available'
                    AND expiry_date > CURDATE()
                    AND (quantity_available > 0 OR boxes_available > 0 OR pieces_available > 0)
                    GROUP BY product_id
                ) inv ON p.id = inv.product_id
                WHERE p.is_active = 1
            ";
            $params = [];
            
            if ($category) {
                $sql .= " AND p.category = ?";
                $params[] = $category;
            }
            
            if ($inStockOnly) {
                $sql .= " AND (inv.total_available > 0 OR inv.total_boxes > 0 OR inv.total_pieces > 0)";
            }
            
            $sql .= " ORDER BY p.category, p.product_name, p.variant";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll();
            
            // Add price and display info
            foreach ($products as &$p) {
                $p['unit_price'] = getProductPrice($db, $p['id']);
                $p['stock_display'] = formatMultiUnitDisplay(
                    intval($p['boxes_available']),
                    intval($p['pieces_available']),
                    $p['box_unit'],
                    $p['base_unit']
                );
                
                // Calculate total pieces for simple comparison
                $piecesPerBox = intval($p['pieces_per_box']) ?: 1;
                $p['total_pieces'] = (intval($p['boxes_available']) * $piecesPerBox) + intval($p['pieces_available']);
                
                // Check if expiring soon (within 3 days)
                if ($p['earliest_expiry']) {
                    $daysToExpiry = (strtotime($p['earliest_expiry']) - strtotime('today')) / 86400;
                    $p['expiring_soon'] = $daysToExpiry <= 3;
                    $p['days_to_expiry'] = intval($daysToExpiry);
                } else {
                    $p['expiring_soon'] = false;
                    $p['days_to_expiry'] = null;
                }
            }
            
            // Group by category for easier UI
            $byCategory = [];
            foreach ($products as $p) {
                $cat = $p['category'] ?? 'uncategorized';
                if (!isset($byCategory[$cat])) {
                    $byCategory[$cat] = [];
                }
                $byCategory[$cat][] = $p;
            }
            
            Response::success([
                'products' => $products,
                'by_category' => $byCategory,
                'total_count' => count($products)
            ], 'Products retrieved successfully');
            break;
            
        case 'search':
            $query = getParam('q', getParam('query', ''));
            
            if (strlen($query) < 2) {
                Response::error('Search query must be at least 2 characters', 400);
            }
            
            $sql = "
                SELECT 
                    p.id,
                    p.product_code,
                    p.product_name,
                    p.category,
                    p.variant,
                    p.unit_size,
                    p.unit_measure,
                    COALESCE(p.base_unit, 'piece') as base_unit,
                    COALESCE(p.box_unit, 'box') as box_unit,
                    COALESCE(p.pieces_per_box, 1) as pieces_per_box,
                    COALESCE(inv.total_available, 0) as stock_available,
                    COALESCE(inv.total_boxes, 0) as boxes_available,
                    COALESCE(inv.total_pieces, 0) as pieces_available,
                    inv.earliest_expiry
                FROM products p
                LEFT JOIN (
                    SELECT 
                        product_id,
                        SUM(COALESCE(quantity_available, 0)) as total_available,
                        SUM(COALESCE(boxes_available, 0)) as total_boxes,
                        SUM(COALESCE(pieces_available, 0)) as total_pieces,
                        MIN(expiry_date) as earliest_expiry
                    FROM finished_goods_inventory
                    WHERE status = 'available'
                    AND expiry_date > CURDATE()
                    GROUP BY product_id
                ) inv ON p.id = inv.product_id
                WHERE p.is_active = 1
                AND (
                    p.product_name LIKE ? 
                    OR p.product_code LIKE ?
                    OR p.variant LIKE ?
                    OR p.category LIKE ?
                )
                ORDER BY 
                    CASE WHEN p.product_name LIKE ? THEN 0 ELSE 1 END,
                    p.product_name
                LIMIT 20
            ";
            
            $searchPattern = "%{$query}%";
            $exactPattern = "{$query}%";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $searchPattern, 
                $searchPattern, 
                $searchPattern, 
                $searchPattern,
                $exactPattern
            ]);
            $products = $stmt->fetchAll();
            
            // Add price and display info
            foreach ($products as &$p) {
                $p['unit_price'] = getProductPrice($db, $p['id']);
                $p['stock_display'] = formatMultiUnitDisplay(
                    intval($p['boxes_available']),
                    intval($p['pieces_available']),
                    $p['box_unit'],
                    $p['base_unit']
                );
                $piecesPerBox = intval($p['pieces_per_box']) ?: 1;
                $p['total_pieces'] = (intval($p['boxes_available']) * $piecesPerBox) + intval($p['pieces_available']);
            }
            
            Response::success([
                'products' => $products,
                'count' => count($products),
                'query' => $query
            ], 'Search results');
            break;
            
        case 'by_barcode':
            $barcode = getParam('barcode');
            
            if (!$barcode) {
                Response::error('Barcode is required', 400);
            }
            
            // Search in products table first
            $stmt = $db->prepare("
                SELECT 
                    p.id,
                    p.product_code,
                    p.product_name,
                    p.category,
                    p.variant,
                    p.unit_size,
                    p.unit_measure,
                    COALESCE(p.base_unit, 'piece') as base_unit,
                    COALESCE(p.box_unit, 'box') as box_unit,
                    COALESCE(p.pieces_per_box, 1) as pieces_per_box
                FROM products p
                WHERE p.product_code = ? 
                   OR p.id = (
                       SELECT product_id FROM finished_goods_inventory 
                       WHERE barcode = ? LIMIT 1
                   )
                   OR p.id = (
                       SELECT product_id FROM finished_goods_inventory fgi
                       JOIN production_batches pb ON fgi.batch_id = pb.id
                       WHERE pb.barcode = ? LIMIT 1
                   )
                LIMIT 1
            ");
            $stmt->execute([$barcode, $barcode, $barcode]);
            $product = $stmt->fetch();
            
            if (!$product) {
                Response::error('Product not found for barcode: ' . $barcode, 404);
            }
            
            // Get inventory details
            $invStmt = $db->prepare("
                SELECT 
                    SUM(COALESCE(quantity_available, 0)) as total_available,
                    SUM(COALESCE(boxes_available, 0)) as total_boxes,
                    SUM(COALESCE(pieces_available, 0)) as total_pieces,
                    MIN(expiry_date) as earliest_expiry,
                    COUNT(*) as batch_count
                FROM finished_goods_inventory
                WHERE product_id = ?
                AND status = 'available'
                AND expiry_date > CURDATE()
            ");
            $invStmt->execute([$product['id']]);
            $inventory = $invStmt->fetch();
            
            $product['stock_available'] = intval($inventory['total_available'] ?? 0);
            $product['boxes_available'] = intval($inventory['total_boxes'] ?? 0);
            $product['pieces_available'] = intval($inventory['total_pieces'] ?? 0);
            $product['earliest_expiry'] = $inventory['earliest_expiry'];
            $product['batch_count'] = intval($inventory['batch_count'] ?? 0);
            $product['unit_price'] = getProductPrice($db, $product['id']);
            $product['stock_display'] = formatMultiUnitDisplay(
                $product['boxes_available'],
                $product['pieces_available'],
                $product['box_unit'],
                $product['base_unit']
            );
            
            $piecesPerBox = intval($product['pieces_per_box']) ?: 1;
            $product['total_pieces'] = ($product['boxes_available'] * $piecesPerBox) + $product['pieces_available'];
            $product['scanned_barcode'] = $barcode;
            
            Response::success($product, 'Product found');
            break;
            
        case 'detail':
            $id = getParam('id');
            
            if (!$id) {
                Response::error('Product ID is required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT 
                    p.*,
                    COALESCE(p.base_unit, 'piece') as base_unit,
                    COALESCE(p.box_unit, 'box') as box_unit,
                    COALESCE(p.pieces_per_box, 1) as pieces_per_box
                FROM products p
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                Response::error('Product not found', 404);
            }
            
            // Get available batches with expiry info
            $batchesStmt = $db->prepare("
                SELECT 
                    fg.id as inventory_id,
                    fg.batch_id,
                    pb.batch_code,
                    fg.quantity_available,
                    fg.boxes_available,
                    fg.pieces_available,
                    fg.manufacturing_date,
                    fg.expiry_date,
                    DATEDIFF(fg.expiry_date, CURDATE()) as days_until_expiry,
                    c.chiller_code,
                    c.chiller_name
                FROM finished_goods_inventory fg
                LEFT JOIN production_batches pb ON fg.batch_id = pb.id
                LEFT JOIN chiller_locations c ON fg.chiller_id = c.id
                WHERE fg.product_id = ?
                AND fg.status = 'available'
                AND fg.expiry_date > CURDATE()
                AND (fg.quantity_available > 0 OR fg.boxes_available > 0 OR fg.pieces_available > 0)
                ORDER BY fg.expiry_date ASC
            ");
            $batchesStmt->execute([$id]);
            $batches = $batchesStmt->fetchAll();
            
            // Calculate totals
            $totalBoxes = 0;
            $totalPieces = 0;
            foreach ($batches as &$b) {
                $totalBoxes += intval($b['boxes_available']);
                $totalPieces += intval($b['pieces_available']);
                $b['display'] = formatMultiUnitDisplay(
                    intval($b['boxes_available']),
                    intval($b['pieces_available']),
                    $product['box_unit'],
                    $product['base_unit']
                );
            }
            
            $piecesPerBox = intval($product['pieces_per_box']) ?: 1;
            $grandTotalPieces = ($totalBoxes * $piecesPerBox) + $totalPieces;
            
            $product['batches'] = $batches;
            $product['inventory_summary'] = [
                'total_boxes' => $totalBoxes,
                'total_pieces' => $totalPieces,
                'grand_total_pieces' => $grandTotalPieces,
                'display' => formatMultiUnitDisplay($totalBoxes, $totalPieces, $product['box_unit'], $product['base_unit']),
                'batch_count' => count($batches),
                'earliest_expiry' => count($batches) > 0 ? $batches[0]['expiry_date'] : null
            ];
            $product['unit_price'] = getProductPrice($db, $id);
            
            Response::success($product, 'Product detail retrieved');
            break;
            
        case 'categories':
            // Get list of product categories with counts
            $stmt = $db->prepare("
                SELECT 
                    p.category,
                    COUNT(p.id) as product_count,
                    COUNT(DISTINCT CASE WHEN inv.product_id IS NOT NULL THEN p.id END) as in_stock_count
                FROM products p
                LEFT JOIN (
                    SELECT DISTINCT product_id
                    FROM finished_goods_inventory
                    WHERE status = 'available'
                    AND expiry_date > CURDATE()
                    AND (quantity_available > 0 OR boxes_available > 0 OR pieces_available > 0)
                ) inv ON p.id = inv.product_id
                WHERE p.is_active = 1
                GROUP BY p.category
                ORDER BY p.category
            ");
            $stmt->execute();
            $categories = $stmt->fetchAll();
            
            Response::success($categories, 'Categories retrieved');
            break;
            
        case 'low_stock':
            // Products with low stock (for alerts)
            $threshold = intval(getParam('threshold', 10));
            
            $stmt = $db->prepare("
                SELECT 
                    p.id,
                    p.product_code,
                    p.product_name,
                    p.variant,
                    p.category,
                    COALESCE(p.base_unit, 'piece') as base_unit,
                    COALESCE(p.box_unit, 'box') as box_unit,
                    COALESCE(p.pieces_per_box, 1) as pieces_per_box,
                    COALESCE(inv.total_available, 0) as stock_available,
                    COALESCE(inv.total_boxes, 0) as boxes_available,
                    COALESCE(inv.total_pieces, 0) as pieces_available
                FROM products p
                LEFT JOIN (
                    SELECT 
                        product_id,
                        SUM(COALESCE(quantity_available, 0)) as total_available,
                        SUM(COALESCE(boxes_available, 0)) as total_boxes,
                        SUM(COALESCE(pieces_available, 0)) as total_pieces
                    FROM finished_goods_inventory
                    WHERE status = 'available'
                    AND expiry_date > CURDATE()
                    GROUP BY product_id
                ) inv ON p.id = inv.product_id
                WHERE p.is_active = 1
                AND COALESCE(inv.total_available, 0) < ?
                ORDER BY inv.total_available ASC, p.product_name
            ");
            $stmt->execute([$threshold]);
            $lowStock = $stmt->fetchAll();
            
            foreach ($lowStock as &$p) {
                $p['stock_display'] = formatMultiUnitDisplay(
                    intval($p['boxes_available']),
                    intval($p['pieces_available']),
                    $p['box_unit'],
                    $p['base_unit']
                );
            }
            
            Response::success([
                'products' => $lowStock,
                'count' => count($lowStock),
                'threshold' => $threshold
            ], 'Low stock products retrieved');
            break;
            
        case 'expiring_soon':
            // Products expiring within specified days
            $days = intval(getParam('days', 3));
            
            $stmt = $db->prepare("
                SELECT 
                    fg.id as inventory_id,
                    fg.product_id,
                    p.product_code,
                    p.product_name,
                    p.variant,
                    p.category,
                    COALESCE(p.base_unit, 'piece') as base_unit,
                    COALESCE(p.box_unit, 'box') as box_unit,
                    fg.quantity_available,
                    fg.boxes_available,
                    fg.pieces_available,
                    fg.expiry_date,
                    DATEDIFF(fg.expiry_date, CURDATE()) as days_until_expiry,
                    pb.batch_code
                FROM finished_goods_inventory fg
                JOIN products p ON fg.product_id = p.id
                LEFT JOIN production_batches pb ON fg.batch_id = pb.id
                WHERE fg.status = 'available'
                AND fg.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                AND fg.expiry_date >= CURDATE()
                AND (fg.quantity_available > 0 OR fg.boxes_available > 0 OR fg.pieces_available > 0)
                ORDER BY fg.expiry_date ASC
            ");
            $stmt->execute([$days]);
            $expiring = $stmt->fetchAll();
            
            foreach ($expiring as &$e) {
                $e['stock_display'] = formatMultiUnitDisplay(
                    intval($e['boxes_available']),
                    intval($e['pieces_available']),
                    $e['box_unit'],
                    $e['base_unit']
                );
                $e['unit_price'] = getProductPrice($db, $e['product_id']);
            }
            
            Response::success([
                'items' => $expiring,
                'count' => count($expiring),
                'days_threshold' => $days
            ], 'Expiring products retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
