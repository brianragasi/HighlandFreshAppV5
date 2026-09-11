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
require_once dirname(__DIR__) . '/helpers/sellable_expiry_policy.php';
require_once dirname(__DIR__) . '/helpers/pos_wholesale.php';

// Require Cashier or GM role
$currentUser = Auth::requireRole(['cashier', 'general_manager']);

$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    hfEnsurePosWholesaleSchema($db);
    
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

/**
 * Sellable unit count for one product row (pieces/bottles).
 * quantity_available is the authoritative sellable ledger. Pack columns are
 * presentation mirrors and must never resurrect sold/disposed stock.
 */
function posSellableUnits(array $p): int {
    if (array_key_exists('stock_available', $p)) {
        return max(0, (int)$p['stock_available']);
    }
    if (array_key_exists('quantity_available', $p)) {
        return max(0, (int)$p['quantity_available']);
    }

    $ppb = max(1, (int) ($p['pieces_per_box'] ?? 1));
    $boxes = (int) ($p['boxes_available'] ?? 0);
    $pieces = (int) ($p['pieces_available'] ?? 0);
    $fromMulti = ($boxes * $ppb) + $pieces;
    if ($fromMulti > 0) {
        return $fromMulti;
    }
    return max(0, (int) ($p['remaining_quantity'] ?? 0));
}

/**
 * Aggregated FG stock subquery for POS — one row per product_id (no card duplication).
 * Only sellable FG: status=available, more than seven days before expiry,
 * active product, chiller not offline.
 */
function posInventoryJoinSql(): string {
    $sellableExpiry = hfSellableExpirySql('fgi.expiry_date');
    return "
        LEFT JOIN (
            SELECT
                fgi.product_id,
                SUM(GREATEST(COALESCE(fgi.quantity_available, 0), 0)) AS total_available,
                SUM(FLOOR(
                    GREATEST(COALESCE(fgi.quantity_available, 0), 0)
                    / GREATEST(COALESCE(p2.pieces_per_box, 1), 1)
                )) AS total_boxes,
                SUM(MOD(
                    GREATEST(COALESCE(fgi.quantity_available, 0), 0),
                    GREATEST(COALESCE(p2.pieces_per_box, 1), 1)
                )) AS total_pieces,
                MIN(fgi.expiry_date) AS earliest_expiry,
                COUNT(*) AS batch_count
            FROM finished_goods_inventory fgi
            INNER JOIN products p2 ON p2.id = fgi.product_id AND p2.is_active = 1
            LEFT JOIN chiller_locations cl ON cl.id = fgi.chiller_id
            WHERE fgi.status = 'available'
              AND {$sellableExpiry}
              AND COALESCE(fgi.quantity_available, 0) > 0
              AND (cl.id IS NULL OR (cl.is_active = 1 AND cl.status IN ('available', 'full')))
              -- Exclude pure freezer storage from walk-in POS (still sellable via Dispatch/Main FG)
              AND (cl.id IS NULL OR cl.chiller_code NOT LIKE 'FREEZE%')
              AND NOT EXISTS (
                  SELECT 1
                  FROM disposals open_disposal
                  WHERE open_disposal.source_type = 'finished_goods'
                    AND open_disposal.source_id = fgi.id
                    AND open_disposal.status IN ('pending', 'approved')
                    AND COALESCE(open_disposal.notes, '') NOT LIKE 'Auto-created from delivery return.%'
              )
            GROUP BY fgi.product_id
        ) inv ON inv.product_id = p.id
    ";
}

/**
 * Enrich product rows for POS UI. Always unset by-ref after loop (PHP trap).
 */
function enrichPosProducts(PDO $db, array &$products): void {
    foreach ($products as &$p) {
        $p['unit_price'] = getProductPrice($db, $p['id']);
        // Prefer catalog selling_price when getProductPrice returns 0
        if ((float) $p['unit_price'] <= 0) {
            $p['unit_price'] = (float) ($p['selling_price'] ?? 0);
        }
        $p['selling_price'] = (float) ($p['selling_price'] ?? $p['unit_price']);

        $p['stock_available'] = (int) ($p['stock_available'] ?? 0);
        $p['boxes_available'] = (int) ($p['boxes_available'] ?? 0);
        $p['pieces_available'] = (int) ($p['pieces_available'] ?? 0);
        $p['total_pieces'] = posSellableUnits($p);
        $p['stock_display'] = formatMultiUnitDisplay(
            $p['boxes_available'],
            $p['pieces_available'],
            $p['box_unit'] ?? 'box',
            $p['base_unit'] ?? 'piece'
        );
        $p['full_boxes_available'] = $p['pieces_per_box'] > 1
            ? intdiv($p['total_pieces'], (int) $p['pieces_per_box'])
            : 0;

        if (!empty($p['earliest_expiry'])) {
            $daysToExpiry = (strtotime($p['earliest_expiry']) - strtotime('today')) / 86400;
            $p['expiring_soon'] = $daysToExpiry <= 3;
            $p['days_to_expiry'] = (int) $daysToExpiry;
        } else {
            $p['expiring_soon'] = false;
            $p['days_to_expiry'] = null;
        }
    }
    unset($p); // CRITICAL: without this, a following foreach corrupts/duplicates the last row

    // Safety: unique by product id (defensive against accidental JOIN fan-out)
    $seen = [];
    $unique = [];
    foreach ($products as $row) {
        $pid = (int) ($row['id'] ?? 0);
        if ($pid <= 0 || isset($seen[$pid])) {
            continue;
        }
        $seen[$pid] = true;
        $unique[] = $row;
    }
    $products = $unique;
}

// ========================================
// GET HANDLERS
// ========================================

function handleGet($db, $action) {
    $fgiSellableExpiry = hfSellableExpirySql('fgi.expiry_date');
    $fgSellableExpiry = hfSellableExpirySql('fg.expiry_date');
    $plainSellableExpiry = hfSellableExpirySql('expiry_date');

    switch ($action) {
        case 'list':
            // One card per sellable SKU (products.id) — inventory batches are aggregated
            $category = getParam('category');
            $inStockOnly = getParam('in_stock', '1') === '1';
            $invJoin = posInventoryJoinSql();

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
                    COALESCE(p.base_unit, 'piece') AS base_unit,
                    COALESCE(p.box_unit, 'box') AS box_unit,
                    COALESCE(p.pieces_per_box, 1) AS pieces_per_box,
                    COALESCE(p.selling_price, p.unit_price, 0) AS selling_price,
                    p.wholesale_box_price,
                    COALESCE(p.unit_price, 0) AS unit_price,
                    p.is_active,
                    COALESCE(inv.total_available, 0) AS stock_available,
                    COALESCE(inv.total_boxes, 0) AS boxes_available,
                    COALESCE(inv.total_pieces, 0) AS pieces_available,
                    inv.earliest_expiry,
                    COALESCE(inv.batch_count, 0) AS batch_count
                FROM products p
                {$invJoin}
                WHERE p.is_active = 1
            ";
            $params = [];

            if ($category) {
                $sql .= " AND p.category = ?";
                $params[] = $category;
            }

            if ($inStockOnly) {
                $sql .= " AND COALESCE(inv.total_available, 0) > 0";
            }

            // Stable order; GROUP BY not needed — inv is pre-aggregated by product_id
            $sql .= " ORDER BY p.category, p.product_name, p.unit_size, p.id";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            enrichPosProducts($db, $products);

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
            
            $invJoin = posInventoryJoinSql();
            $sql = "
                SELECT
                    p.id,
                    p.product_code,
                    p.product_name,
                    p.category,
                    p.variant,
                    p.unit_size,
                    p.unit_measure,
                    COALESCE(p.base_unit, 'piece') AS base_unit,
                    COALESCE(p.box_unit, 'box') AS box_unit,
                    COALESCE(p.pieces_per_box, 1) AS pieces_per_box,
                    COALESCE(p.selling_price, p.unit_price, 0) AS selling_price,
                    p.wholesale_box_price,
                    COALESCE(inv.total_available, 0) AS stock_available,
                    COALESCE(inv.total_boxes, 0) AS boxes_available,
                    COALESCE(inv.total_pieces, 0) AS pieces_available,
                    inv.earliest_expiry
                FROM products p
                {$invJoin}
                WHERE p.is_active = 1
                AND COALESCE(inv.total_available, 0) > 0
                AND (
                    p.product_name LIKE ?
                    OR p.product_code LIKE ?
                    OR p.variant LIKE ?
                    OR p.category LIKE ?
                )
                ORDER BY
                    CASE WHEN p.product_name LIKE ? THEN 0 ELSE 1 END,
                    p.product_name,
                    p.id
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
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            enrichPosProducts($db, $products);

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
                    SUM(GREATEST(COALESCE(fgi.quantity_available, 0), 0)) as total_available,
                    SUM(FLOOR(GREATEST(COALESCE(fgi.quantity_available, 0), 0) / GREATEST(COALESCE(p.pieces_per_box, 1), 1))) as total_boxes,
                    SUM(MOD(GREATEST(COALESCE(fgi.quantity_available, 0), 0), GREATEST(COALESCE(p.pieces_per_box, 1), 1))) as total_pieces,
                    MIN(fgi.expiry_date) as earliest_expiry,
                    COUNT(*) as batch_count
                FROM finished_goods_inventory fgi
                INNER JOIN products p ON p.id = fgi.product_id AND p.is_active = 1
                LEFT JOIN chiller_locations cl ON cl.id = fgi.chiller_id
                WHERE fgi.product_id = ?
                AND fgi.status = 'available'
                AND {$fgiSellableExpiry}
                AND COALESCE(fgi.quantity_available, 0) > 0
                AND (cl.id IS NULL OR (cl.is_active = 1 AND cl.status IN ('available', 'full')))
                AND (cl.id IS NULL OR cl.chiller_code NOT LIKE 'FREEZE%')
                AND NOT EXISTS (
                    SELECT 1 FROM disposals open_disposal
                    WHERE open_disposal.source_type = 'finished_goods'
                      AND open_disposal.source_id = fgi.id
                      AND open_disposal.status IN ('pending', 'approved')
                      AND COALESCE(open_disposal.notes, '') NOT LIKE 'Auto-created from delivery return.%'
                )
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

            if ($product['total_pieces'] <= 0) {
                Response::error(
                    'This product has no POS-sellable stock. Batches with 7 days or less before expiry are handled by QC.',
                    409
                );
            }
            
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
                AND {$fgSellableExpiry}
                AND fg.quantity_available > 0
                AND NOT EXISTS (
                    SELECT 1 FROM disposals open_disposal
                    WHERE open_disposal.source_type = 'finished_goods'
                      AND open_disposal.source_id = fg.id
                      AND open_disposal.status IN ('pending', 'approved')
                      AND COALESCE(open_disposal.notes, '') NOT LIKE 'Auto-created from delivery return.%'
                )
                ORDER BY fg.expiry_date ASC
            ");
            $batchesStmt->execute([$id]);
            $batches = $batchesStmt->fetchAll();
            
            // Calculate totals
            $totalBoxes = 0;
            $totalPieces = 0;
            foreach ($batches as &$b) {
                $baseQty = max(0, intval($b['quantity_available']));
                $batchBoxes = intdiv($baseQty, max(1, intval($product['pieces_per_box'])));
                $batchPieces = $baseQty % max(1, intval($product['pieces_per_box']));
                $b['boxes_available'] = $batchBoxes;
                $b['pieces_available'] = $batchPieces;
                $totalBoxes += $batchBoxes;
                $totalPieces += $batchPieces;
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
                    AND {$plainSellableExpiry}
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
                    AND {$plainSellableExpiry}
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
