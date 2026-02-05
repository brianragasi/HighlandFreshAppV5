<?php
/**
 * Highland Fresh System - Warehouse FG Inventory API
 * Multi-Unit Support: Boxes and Pieces
 * 
 * GET - List inventory, get details, expiring items, convert units
 * POST - Receive inventory, open box
 * PUT - Transfer, adjust, dispose, release
 * 
 * @package HighlandFresh
 * @version 4.1
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

// Require Warehouse FG role
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
    error_log("FG Inventory API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

// ========================================
// MULTI-UNIT HELPER FUNCTIONS
// ========================================

/**
 * Get product unit configuration
 * @param PDO $db Database connection
 * @param int $productId Product ID
 * @return array Unit config with pieces_per_box, base_unit, box_unit
 */
function getProductUnitConfig($db, $productId) {
    // First try product_units table
    $stmt = $db->prepare("
        SELECT pu.*, p.product_name
        FROM product_units pu
        JOIN products p ON pu.product_id = p.id
        WHERE pu.product_id = ? AND pu.is_active = 1
    ");
    $stmt->execute([$productId]);
    $unitConfig = $stmt->fetch();
    
    if ($unitConfig) {
        return $unitConfig;
    }
    
    // Fallback to products table columns
    $stmt = $db->prepare("
        SELECT id, name as product_name, 
               COALESCE(base_unit, 'piece') as base_unit,
               COALESCE(box_unit, 'box') as box_unit,
               COALESCE(pieces_per_box, 1) as pieces_per_box
        FROM products 
        WHERE id = ?
    ");
    $stmt->execute([$productId]);
    return $stmt->fetch() ?: [
        'base_unit' => 'piece',
        'box_unit' => 'box',
        'pieces_per_box' => 1
    ];
}

/**
 * Convert total pieces to boxes + remaining pieces
 * @param int $totalPieces Total piece count
 * @param int $piecesPerBox Conversion ratio
 * @return array ['boxes' => int, 'pieces' => int]
 */
function piecesToBoxes($totalPieces, $piecesPerBox) {
    if ($piecesPerBox <= 0) $piecesPerBox = 1;
    return [
        'boxes' => intdiv($totalPieces, $piecesPerBox),
        'pieces' => $totalPieces % $piecesPerBox
    ];
}

/**
 * Convert boxes + pieces to total pieces
 * @param int $boxes Box count
 * @param int $pieces Loose piece count
 * @param int $piecesPerBox Conversion ratio
 * @return int Total pieces
 */
function boxesToPieces($boxes, $pieces, $piecesPerBox) {
    if ($piecesPerBox <= 0) $piecesPerBox = 1;
    return ($boxes * $piecesPerBox) + $pieces;
}

/**
 * Format inventory display string
 * @param int $boxes Box count
 * @param int $pieces Loose piece count
 * @param string $boxUnit Box unit name
 * @param string $baseUnit Base unit name
 * @return string Formatted display e.g., "8 Boxes + 14 Pieces"
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
 * Open a box - convert sealed box to loose pieces
 * @param PDO $db Database connection
 * @param int $inventoryId Inventory record ID
 * @param int $boxesToOpen Number of boxes to open
 * @param int $userId User performing the action
 * @param string $reason Reason for opening
 * @param string|null $referenceType Reference type (e.g., delivery_receipt)
 * @param int|null $referenceId Reference ID
 * @return array Result with new box/piece counts
 */
function openBox($db, $inventoryId, $boxesToOpen, $userId, $reason = 'partial_sale', $referenceType = null, $referenceId = null) {
    // Get current inventory
    $stmt = $db->prepare("
        SELECT fg.*, p.pieces_per_box, p.base_unit, p.box_unit
        FROM finished_goods_inventory fg
        JOIN products p ON fg.product_id = p.id
        WHERE fg.id = ?
    ");
    $stmt->execute([$inventoryId]);
    $inventory = $stmt->fetch();
    
    if (!$inventory) {
        throw new Exception('Inventory item not found');
    }
    
    $piecesPerBox = $inventory['pieces_per_box'] ?: 1;
    $currentBoxes = $inventory['boxes_available'] ?? $inventory['quantity_boxes'] ?? 0;
    $currentPieces = $inventory['pieces_available'] ?? $inventory['quantity_pieces'] ?? 0;
    
    if ($boxesToOpen > $currentBoxes) {
        throw new Exception("Cannot open {$boxesToOpen} boxes. Only {$currentBoxes} sealed boxes available.");
    }
    
    // Calculate new values
    $piecesFromOpening = $boxesToOpen * $piecesPerBox;
    $newBoxes = $currentBoxes - $boxesToOpen;
    $newPieces = $currentPieces + $piecesFromOpening;
    
    // Update inventory
    $updateStmt = $db->prepare("
        UPDATE finished_goods_inventory 
        SET quantity_boxes = quantity_boxes - ?,
            boxes_available = boxes_available - ?,
            quantity_pieces = quantity_pieces + ?,
            pieces_available = pieces_available + ?
        WHERE id = ?
    ");
    $updateStmt->execute([$boxesToOpen, $boxesToOpen, $piecesFromOpening, $piecesFromOpening, $inventoryId]);
    
    // Log the box opening
    $logStmt = $db->prepare("
        INSERT INTO box_opening_log 
        (opening_code, inventory_id, product_id, boxes_opened, pieces_from_opening, 
         reason, reference_type, reference_id, opened_by, opened_at, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ");
    
    $openingCode = 'BOX-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    $logStmt->execute([
        $openingCode,
        $inventoryId,
        $inventory['product_id'],
        $boxesToOpen,
        $piecesFromOpening,
        $reason,
        $referenceType,
        $referenceId,
        $userId,
        "Opened {$boxesToOpen} box(es) yielding {$piecesFromOpening} pieces"
    ]);
    
    return [
        'opening_code' => $openingCode,
        'boxes_opened' => $boxesToOpen,
        'pieces_from_opening' => $piecesFromOpening,
        'boxes_before' => $currentBoxes,
        'boxes_after' => $newBoxes,
        'pieces_before' => $currentPieces,
        'pieces_after' => $newPieces,
        'display' => formatMultiUnitDisplay($newBoxes, $newPieces, $inventory['box_unit'] ?? 'box', $inventory['base_unit'] ?? 'piece')
    ];
}

/**
 * Release inventory with multi-unit support
 * Handles automatic box opening when releasing partial boxes
 * 
 * @param PDO $db Database connection
 * @param int $inventoryId Inventory record ID
 * @param int $releasedBoxes Number of full boxes to release
 * @param int $releasedPieces Number of loose pieces to release
 * @param int $userId User performing the release
 * @param string $reason Release reason
 * @param string|null $referenceType Reference type
 * @param int|null $referenceId Reference ID
 * @return array Release result
 */
function releaseMultiUnit($db, $inventoryId, $releasedBoxes, $releasedPieces, $userId, $reason = 'dispatch', $referenceType = null, $referenceId = null) {
    // Get current inventory with product info
    $stmt = $db->prepare("
        SELECT fg.*, p.pieces_per_box, p.base_unit, p.box_unit, p.product_name
        FROM finished_goods_inventory fg
        JOIN products p ON fg.product_id = p.id
        WHERE fg.id = ?
    ");
    $stmt->execute([$inventoryId]);
    $inventory = $stmt->fetch();
    
    if (!$inventory) {
        throw new Exception('Inventory item not found');
    }
    
    $piecesPerBox = $inventory['pieces_per_box'] ?: 1;
    $currentBoxes = $inventory['boxes_available'] ?? $inventory['quantity_boxes'] ?? 0;
    $currentPieces = $inventory['pieces_available'] ?? $inventory['quantity_pieces'] ?? 0;
    
    $boxesAfter = $currentBoxes;
    $piecesAfter = $currentPieces;
    $boxesOpened = 0;
    $openingCode = null;
    
    // Step 1: Deduct full boxes
    if ($releasedBoxes > 0) {
        if ($releasedBoxes > $currentBoxes) {
            throw new Exception("Cannot release {$releasedBoxes} boxes. Only {$currentBoxes} boxes available.");
        }
        $boxesAfter -= $releasedBoxes;
    }
    
    // Step 2: Handle pieces
    if ($releasedPieces > 0) {
        // Check if we have enough loose pieces
        if ($releasedPieces > $piecesAfter) {
            // Need to open boxes to get more pieces
            $piecesNeeded = $releasedPieces - $piecesAfter;
            $boxesToOpen = ceil($piecesNeeded / $piecesPerBox);
            
            if ($boxesToOpen > $boxesAfter) {
                $totalAvailable = ($boxesAfter * $piecesPerBox) + $piecesAfter;
                throw new Exception("Insufficient inventory. Need {$releasedPieces} pieces but only {$totalAvailable} total pieces available.");
            }
            
            // Open the required boxes
            $piecesFromOpening = $boxesToOpen * $piecesPerBox;
            $boxesAfter -= $boxesToOpen;
            $piecesAfter += $piecesFromOpening;
            $boxesOpened = $boxesToOpen;
            
            // Log box opening
            $logOpenStmt = $db->prepare("
                INSERT INTO box_opening_log 
                (opening_code, inventory_id, product_id, boxes_opened, pieces_from_opening, 
                 reason, reference_type, reference_id, opened_by, opened_at, notes)
                VALUES (?, ?, ?, ?, ?, 'partial_sale', ?, ?, ?, NOW(), ?)
            ");
            
            $openingCode = 'BOX-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            
            $logOpenStmt->execute([
                $openingCode,
                $inventoryId,
                $inventory['product_id'],
                $boxesToOpen,
                $piecesFromOpening,
                $referenceType,
                $referenceId,
                $userId,
                "Auto-opened {$boxesToOpen} box(es) for release of {$releasedPieces} pieces"
            ]);
        }
        
        // Now deduct the pieces
        $piecesAfter -= $releasedPieces;
    }
    
    // Update inventory
    $totalReleasedPieces = ($releasedBoxes * $piecesPerBox) + $releasedPieces;
    
    $updateStmt = $db->prepare("
        UPDATE finished_goods_inventory 
        SET quantity_boxes = ?,
            boxes_available = ?,
            quantity_pieces = ?,
            pieces_available = ?,
            quantity_available = quantity_available - ?
        WHERE id = ?
    ");
    $updateStmt->execute([
        $boxesAfter,
        $boxesAfter,
        $piecesAfter,
        $piecesAfter,
        $totalReleasedPieces,
        $inventoryId
    ]);
    
    // Update chiller count if applicable
    if ($inventory['chiller_id']) {
        $db->prepare("UPDATE chiller_locations SET current_count = current_count - ? WHERE id = ?")
           ->execute([$totalReleasedPieces, $inventory['chiller_id']]);
    }
    
    // Log transaction
    $logStmt = $db->prepare("
        INSERT INTO fg_inventory_transactions
        (transaction_code, transaction_type, inventory_id, product_id, quantity, 
         boxes_quantity, pieces_quantity, quantity_before, quantity_after, 
         boxes_before, pieces_before, boxes_after, pieces_after,
         from_chiller_id, performed_by, reason, reference_type, reference_id)
        VALUES (?, 'release', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $transCode = 'FGT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    $logStmt->execute([
        $transCode,
        $inventoryId,
        $inventory['product_id'],
        $totalReleasedPieces,
        $releasedBoxes,
        $releasedPieces,
        ($currentBoxes * $piecesPerBox) + $currentPieces,
        ($boxesAfter * $piecesPerBox) + $piecesAfter,
        $currentBoxes,
        $currentPieces,
        $boxesAfter,
        $piecesAfter,
        $inventory['chiller_id'],
        $userId,
        $reason,
        $referenceType,
        $referenceId
    ]);
    
    return [
        'transaction_code' => $transCode,
        'released_boxes' => $releasedBoxes,
        'released_pieces' => $releasedPieces,
        'total_pieces_released' => $totalReleasedPieces,
        'boxes_opened' => $boxesOpened,
        'opening_code' => $openingCode,
        'inventory_before' => [
            'boxes' => $currentBoxes,
            'pieces' => $currentPieces,
            'display' => formatMultiUnitDisplay($currentBoxes, $currentPieces, $inventory['box_unit'] ?? 'box', $inventory['base_unit'] ?? 'piece')
        ],
        'inventory_after' => [
            'boxes' => $boxesAfter,
            'pieces' => $piecesAfter,
            'display' => formatMultiUnitDisplay($boxesAfter, $piecesAfter, $inventory['box_unit'] ?? 'box', $inventory['base_unit'] ?? 'piece')
        ]
    ];
}

// ========================================
// GET HANDLERS
// ========================================

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            $productId = getParam('product_id');
            $chillerId = getParam('chiller_id');
            $status = getParam('status', 'available');
            
            $sql = "
                SELECT 
                    fg.*,
                    p.product_name,
                    p.variant,
                    p.unit_size as size_value,
                    p.unit_measure as size_unit,
                    p.category,
                    COALESCE(p.base_unit, 'piece') as base_unit,
                    COALESCE(p.box_unit, 'box') as box_unit,
                    COALESCE(p.pieces_per_box, 1) as pieces_per_box,
                    c.chiller_code,
                    c.chiller_name,
                    pb.batch_code,
                    DATEDIFF(fg.expiry_date, CURDATE()) as days_until_expiry,
                    -- Multi-unit calculated fields
                    COALESCE(fg.quantity_boxes, 0) as quantity_boxes,
                    COALESCE(fg.quantity_pieces, 0) as quantity_pieces,
                    COALESCE(fg.boxes_available, 0) as boxes_available,
                    COALESCE(fg.pieces_available, 0) as pieces_available
                FROM finished_goods_inventory fg
                JOIN products p ON fg.product_id = p.id
                LEFT JOIN chiller_locations c ON fg.chiller_id = c.id
                LEFT JOIN production_batches pb ON fg.batch_id = pb.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if ($status) {
                $sql .= " AND fg.status = ?";
                $params[] = $status;
            }
            
            if ($productId) {
                $sql .= " AND fg.product_id = ?";
                $params[] = $productId;
            }
            
            if ($chillerId) {
                $sql .= " AND fg.chiller_id = ?";
                $params[] = $chillerId;
            }
            
            $sql .= " ORDER BY fg.expiry_date ASC, fg.manufacturing_date ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $inventory = $stmt->fetchAll();
            
            // Add formatted display strings
            foreach ($inventory as &$item) {
                $item['inventory_display'] = formatMultiUnitDisplay(
                    $item['boxes_available'],
                    $item['pieces_available'],
                    $item['box_unit'],
                    $item['base_unit']
                );
                $item['total_pieces'] = boxesToPieces(
                    $item['boxes_available'],
                    $item['pieces_available'],
                    $item['pieces_per_box']
                );
            }
            
            Response::success($inventory, 'Inventory retrieved successfully');
            break;
            
        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('Inventory ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT 
                    fg.*,
                    p.product_name,
                    p.variant,
                    p.unit_size,
                    p.unit_measure,
                    COALESCE(p.base_unit, 'piece') as base_unit,
                    COALESCE(p.box_unit, 'box') as box_unit,
                    COALESCE(p.pieces_per_box, 1) as pieces_per_box,
                    c.chiller_code,
                    c.chiller_name,
                    pb.batch_code,
                    COALESCE(fg.quantity_boxes, 0) as quantity_boxes,
                    COALESCE(fg.quantity_pieces, 0) as quantity_pieces,
                    COALESCE(fg.boxes_available, 0) as boxes_available,
                    COALESCE(fg.pieces_available, 0) as pieces_available
                FROM finished_goods_inventory fg
                JOIN products p ON fg.product_id = p.id
                LEFT JOIN chiller_locations c ON fg.chiller_id = c.id
                LEFT JOIN production_batches pb ON fg.batch_id = pb.id
                WHERE fg.id = ?
            ");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                Response::error('Inventory item not found', 404);
            }
            
            // Add formatted display
            $item['inventory_display'] = formatMultiUnitDisplay(
                $item['boxes_available'],
                $item['pieces_available'],
                $item['box_unit'],
                $item['base_unit']
            );
            $item['total_pieces'] = boxesToPieces(
                $item['boxes_available'],
                $item['pieces_available'],
                $item['pieces_per_box']
            );
            
            // Get box opening history
            $historyStmt = $db->prepare("
                SELECT * FROM box_opening_log 
                WHERE inventory_id = ? 
                ORDER BY opened_at DESC LIMIT 10
            ");
            $historyStmt->execute([$id]);
            $item['box_opening_history'] = $historyStmt->fetchAll();
            
            Response::success($item, 'Inventory details retrieved');
            break;
            
        case 'expiring':
            $days = getParam('days', 3);
            
            $stmt = $db->prepare("
                SELECT 
                    fg.*,
                    p.product_name,
                    p.variant,
                    p.unit_size,
                    p.unit_measure,
                    COALESCE(p.base_unit, 'piece') as base_unit,
                    COALESCE(p.box_unit, 'box') as box_unit,
                    COALESCE(p.pieces_per_box, 1) as pieces_per_box,
                    c.chiller_code,
                    DATEDIFF(fg.expiry_date, CURDATE()) as days_until_expiry,
                    COALESCE(fg.boxes_available, 0) as boxes_available,
                    COALESCE(fg.pieces_available, 0) as pieces_available
                FROM finished_goods_inventory fg
                JOIN products p ON fg.product_id = p.id
                LEFT JOIN chiller_locations c ON fg.chiller_id = c.id
                WHERE fg.status = 'available'
                AND fg.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                AND fg.expiry_date >= CURDATE()
                ORDER BY fg.expiry_date ASC
            ");
            $stmt->execute([$days]);
            $items = $stmt->fetchAll();
            
            // Add formatted display strings
            foreach ($items as &$item) {
                $item['inventory_display'] = formatMultiUnitDisplay(
                    $item['boxes_available'],
                    $item['pieces_available'],
                    $item['box_unit'],
                    $item['base_unit']
                );
            }
            
            Response::success($items, 'Expiring items retrieved');
            break;
            
        case 'available':
            $productId = getParam('product_id');
            
            $sql = "
                SELECT 
                    fg.*,
                    p.product_name,
                    p.variant,
                    COALESCE(p.base_unit, 'piece') as base_unit,
                    COALESCE(p.box_unit, 'box') as box_unit,
                    COALESCE(p.pieces_per_box, 1) as pieces_per_box,
                    c.chiller_code,
                    DATEDIFF(fg.expiry_date, CURDATE()) as days_until_expiry,
                    COALESCE(fg.boxes_available, 0) as boxes_available,
                    COALESCE(fg.pieces_available, 0) as pieces_available
                FROM finished_goods_inventory fg
                JOIN products p ON fg.product_id = p.id
                LEFT JOIN chiller_locations c ON fg.chiller_id = c.id
                WHERE fg.status = 'available'
                AND (fg.quantity_available > 0 OR fg.boxes_available > 0 OR fg.pieces_available > 0)
                AND fg.expiry_date > CURDATE()
            ";
            
            $params = [];
            if ($productId) {
                $sql .= " AND fg.product_id = ?";
                $params[] = $productId;
            }
            
            $sql .= " ORDER BY fg.expiry_date ASC"; // FIFO
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll();
            
            // Add formatted display and totals
            foreach ($items as &$item) {
                $item['inventory_display'] = formatMultiUnitDisplay(
                    $item['boxes_available'],
                    $item['pieces_available'],
                    $item['box_unit'],
                    $item['base_unit']
                );
                $item['total_pieces'] = boxesToPieces(
                    $item['boxes_available'],
                    $item['pieces_available'],
                    $item['pieces_per_box']
                );
            }
            
            Response::success($items, 'Available inventory retrieved');
            break;
            
        case 'convert':
            // Unit conversion helper
            $productId = getParam('product_id');
            $boxes = intval(getParam('boxes', 0));
            $pieces = intval(getParam('pieces', 0));
            $direction = getParam('direction', 'to_pieces'); // to_pieces or to_boxes
            
            if (!$productId) {
                Response::error('Product ID required for conversion', 400);
            }
            
            $unitConfig = getProductUnitConfig($db, $productId);
            $piecesPerBox = $unitConfig['pieces_per_box'] ?: 1;
            
            if ($direction === 'to_pieces') {
                $totalPieces = boxesToPieces($boxes, $pieces, $piecesPerBox);
                Response::success([
                    'input' => ['boxes' => $boxes, 'pieces' => $pieces],
                    'total_pieces' => $totalPieces,
                    'pieces_per_box' => $piecesPerBox,
                    'base_unit' => $unitConfig['base_unit'],
                    'box_unit' => $unitConfig['box_unit']
                ], 'Conversion successful');
            } else {
                $totalPieces = boxesToPieces($boxes, $pieces, $piecesPerBox);
                $converted = piecesToBoxes($totalPieces, $piecesPerBox);
                Response::success([
                    'input_total_pieces' => $totalPieces,
                    'boxes' => $converted['boxes'],
                    'pieces' => $converted['pieces'],
                    'display' => formatMultiUnitDisplay($converted['boxes'], $converted['pieces'], $unitConfig['box_unit'], $unitConfig['base_unit']),
                    'pieces_per_box' => $piecesPerBox
                ], 'Conversion successful');
            }
            break;
            
        case 'summary':
            // Product inventory summary with multi-unit totals
            $productId = getParam('product_id');
            
            $sql = "
                SELECT 
                    p.id as product_id,
                    p.product_name,
                    p.variant,
                    COALESCE(p.base_unit, 'piece') as base_unit,
                    COALESCE(p.box_unit, 'box') as box_unit,
                    COALESCE(p.pieces_per_box, 1) as pieces_per_box,
                    SUM(COALESCE(fg.boxes_available, 0)) as total_boxes,
                    SUM(COALESCE(fg.pieces_available, 0)) as total_pieces,
                    SUM(fg.quantity_available) as total_quantity,
                    MIN(fg.expiry_date) as earliest_expiry,
                    COUNT(fg.id) as batch_count
                FROM products p
                LEFT JOIN finished_goods_inventory fg ON p.id = fg.product_id 
                    AND fg.status = 'available' 
                    AND fg.expiry_date > CURDATE()
                WHERE p.category IS NOT NULL
            ";
            
            $params = [];
            if ($productId) {
                $sql .= " AND p.id = ?";
                $params[] = $productId;
            }
            
            $sql .= " GROUP BY p.id ORDER BY p.product_name";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $summary = $stmt->fetchAll();
            
            foreach ($summary as &$item) {
                $item['inventory_display'] = formatMultiUnitDisplay(
                    $item['total_boxes'],
                    $item['total_pieces'],
                    $item['box_unit'],
                    $item['base_unit']
                );
                $item['grand_total_pieces'] = boxesToPieces(
                    $item['total_boxes'],
                    $item['total_pieces'],
                    $item['pieces_per_box']
                );
            }
            
            Response::success($summary, 'Inventory summary retrieved');
            break;
            
        case 'pending_batches':
            // Get QC-released batches not yet received into FG warehouse
            // The fg_received flag is the primary indicator, but we also check
            // fg_receiving and finished_goods_inventory tables as fallback
            $stmt = $db->prepare("
                SELECT 
                    pb.id,
                    pb.batch_code,
                    pb.product_type,
                    pb.product_variant,
                    COALESCE(mr.product_name, CONCAT(COALESCE(pb.product_type, 'Unknown'), COALESCE(CONCAT(' - ', pb.product_variant), ''))) as product_name,
                    COALESCE(mr.variant, pb.product_variant) as variant,
                    pb.expected_yield as quantity_produced,
                    pb.actual_yield,
                    COALESCE(pb.actual_yield, pb.expected_yield, 0) as total_pieces,
                    0 as quantity_boxes,
                    COALESCE(pb.actual_yield, pb.expected_yield, 0) as quantity_pieces,
                    COALESCE(p.pieces_per_box, 1) as pieces_per_box,
                    COALESCE(p.base_unit, 'pcs') as unit,
                    COALESCE(pb.manufacturing_date, pb.created_at) as production_date,
                    pb.expiry_date,
                    pb.qc_status as status,
                    COALESCE(pb.qc_released_at, pb.released_at) as qc_release_date,
                    pb.released_by,
                    mr.id as recipe_id,
                    pb.barcode
                FROM production_batches pb
                LEFT JOIN master_recipes mr ON pb.recipe_id = mr.id
                LEFT JOIN products p ON (mr.id IS NOT NULL AND p.id = mr.id) OR p.id = pb.product_id
                WHERE pb.qc_status = 'released'
                AND (pb.fg_received IS NULL OR pb.fg_received = 0)
                AND NOT EXISTS (SELECT 1 FROM fg_receiving fr WHERE fr.batch_id = pb.id)
                AND NOT EXISTS (SELECT 1 FROM finished_goods_inventory fgi WHERE fgi.batch_id = pb.id)
                ORDER BY COALESCE(pb.qc_released_at, pb.released_at, pb.created_at) ASC
            ");
            $stmt->execute();
            $pendingBatches = $stmt->fetchAll();
            
            Response::success([
                'batches' => $pendingBatches,
                'count' => count($pendingBatches)
            ], 'Pending batches retrieved');
            break;
            
        case 'transactions':
            // Get inventory transaction history
            $type = getParam('type');
            $productId = getParam('product_id');
            $limit = getParam('limit', 50);
            $fromDate = getParam('from_date');
            $toDate = getParam('to_date');
            
            $sql = "
                SELECT 
                    t.*,
                    fg.product_name,
                    fg.batch_id,
                    u.first_name,
                    u.last_name,
                    c.chiller_code as from_chiller,
                    c2.chiller_code as to_chiller
                FROM fg_inventory_transactions t
                LEFT JOIN finished_goods_inventory fg ON t.inventory_id = fg.id
                LEFT JOIN users u ON t.performed_by = u.id
                LEFT JOIN chiller_locations c ON t.from_chiller_id = c.id
                LEFT JOIN chiller_locations c2 ON t.to_chiller_id = c2.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if ($type) {
                $sql .= " AND t.transaction_type = ?";
                $params[] = $type;
            }
            
            if ($productId) {
                $sql .= " AND t.product_id = ?";
                $params[] = $productId;
            }
            
            if ($fromDate) {
                $sql .= " AND DATE(t.created_at) >= ?";
                $params[] = $fromDate;
            }
            
            if ($toDate) {
                $sql .= " AND DATE(t.created_at) <= ?";
                $params[] = $toDate;
            }
            
            $sql .= " ORDER BY t.created_at DESC LIMIT " . intval($limit);
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $transactions = $stmt->fetchAll();
            
            Response::success([
                'transactions' => $transactions,
                'count' => count($transactions)
            ], 'Transactions retrieved');
            break;
            
        case 'transaction_detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('Transaction ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT 
                    t.*,
                    fg.product_name,
                    fg.batch_id,
                    u.first_name,
                    u.last_name,
                    c.chiller_code as from_chiller,
                    c2.chiller_code as to_chiller
                FROM fg_inventory_transactions t
                LEFT JOIN finished_goods_inventory fg ON t.inventory_id = fg.id
                LEFT JOIN users u ON t.performed_by = u.id
                LEFT JOIN chiller_locations c ON t.from_chiller_id = c.id
                LEFT JOIN chiller_locations c2 ON t.to_chiller_id = c2.id
                WHERE t.id = ?
            ");
            $stmt->execute([$id]);
            $transaction = $stmt->fetch();
            
            if (!$transaction) {
                Response::error('Transaction not found', 404);
            }
            
            Response::success($transaction, 'Transaction details retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

// ========================================
// POST HANDLERS
// ========================================

function handlePost($db, $action, $currentUser) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'receive':
            // Receive inventory from production with multi-unit support
            $required = ['product_id', 'manufacturing_date', 'expiry_date'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::error("$field is required", 400);
                }
            }
            
            // Accept either quantity OR boxes+pieces
            $boxes = intval($data['boxes'] ?? $data['quantity_boxes'] ?? 0);
            $pieces = intval($data['pieces'] ?? $data['quantity_pieces'] ?? 0);
            $quantity = intval($data['quantity'] ?? 0);
            
            // Get product unit config
            $unitConfig = getProductUnitConfig($db, $data['product_id']);
            $piecesPerBox = $unitConfig['pieces_per_box'] ?: 1;
            
            // Calculate total quantity
            if ($boxes > 0 || $pieces > 0) {
                $totalQuantity = boxesToPieces($boxes, $pieces, $piecesPerBox);
            } elseif ($quantity > 0) {
                // Convert total quantity to boxes+pieces
                $totalQuantity = $quantity;
                $converted = piecesToBoxes($quantity, $piecesPerBox);
                $boxes = $converted['boxes'];
                $pieces = $converted['pieces'];
            } else {
                Response::error('Quantity (boxes and/or pieces) is required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Create inventory record
                $stmt = $db->prepare("
                    INSERT INTO finished_goods_inventory 
                    (product_id, batch_id, quantity, quantity_available, remaining_quantity,
                     quantity_boxes, quantity_pieces, boxes_available, pieces_available,
                     manufacturing_date, expiry_date, chiller_id, status, received_by, received_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', ?, NOW())
                ");
                
                $stmt->execute([
                    $data['product_id'],
                    $data['batch_id'] ?? null,
                    $totalQuantity,
                    $totalQuantity,
                    $totalQuantity,
                    $boxes,
                    $pieces,
                    $boxes,
                    $pieces,
                    $data['manufacturing_date'],
                    $data['expiry_date'],
                    $data['chiller_id'] ?? null,
                    $currentUser['user_id']
                ]);
                
                $inventoryId = $db->lastInsertId();
                
                // Update chiller count if assigned
                if (!empty($data['chiller_id'])) {
                    $updateChiller = $db->prepare("
                        UPDATE chiller_locations 
                        SET current_count = current_count + ?
                        WHERE id = ?
                    ");
                    $updateChiller->execute([$totalQuantity, $data['chiller_id']]);
                }
                
                // Log transaction
                $logStmt = $db->prepare("
                    INSERT INTO fg_inventory_transactions
                    (transaction_code, transaction_type, inventory_id, product_id, quantity, 
                     boxes_quantity, pieces_quantity, quantity_before, quantity_after,
                     boxes_before, pieces_before, boxes_after, pieces_after,
                     to_chiller_id, performed_by, reason)
                    VALUES (?, 'receive', ?, ?, ?, ?, ?, 0, ?, 0, 0, ?, ?, ?, ?, ?)
                ");
                
                $logStmt->execute([
                    'FGT-' . date('Ymd') . '-' . str_pad($inventoryId, 4, '0', STR_PAD_LEFT),
                    $inventoryId,
                    $data['product_id'],
                    $totalQuantity,
                    $boxes,
                    $pieces,
                    $totalQuantity,
                    $boxes,
                    $pieces,
                    $data['chiller_id'] ?? null,
                    $currentUser['user_id'],
                    $data['notes'] ?? 'Received into inventory'
                ]);
                
                $db->commit();
                
                Response::success([
                    'id' => $inventoryId,
                    'boxes' => $boxes,
                    'pieces' => $pieces,
                    'total_quantity' => $totalQuantity,
                    'display' => formatMultiUnitDisplay($boxes, $pieces, $unitConfig['box_unit'], $unitConfig['base_unit'])
                ], 'Inventory received successfully', 201);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'open_box':
            // Digitally open a sealed box to pieces
            if (empty($data['inventory_id'])) {
                Response::error('Inventory ID required', 400);
            }
            
            $boxesToOpen = intval($data['boxes'] ?? $data['boxes_to_open'] ?? 1);
            $reason = $data['reason'] ?? 'partial_sale';
            
            $db->beginTransaction();
            
            try {
                $result = openBox(
                    $db,
                    $data['inventory_id'],
                    $boxesToOpen,
                    $currentUser['user_id'],
                    $reason,
                    $data['reference_type'] ?? null,
                    $data['reference_id'] ?? null
                );
                
                $db->commit();
                
                Response::success($result, "Successfully opened {$boxesToOpen} box(es)", 200);
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'receive_batch':
            // Receive a production batch from QC into FG inventory
            if (empty($data['batch_id'])) {
                Response::error('Batch ID is required', 400);
            }
            if (empty($data['chiller_id'])) {
                Response::error('Chiller ID is required', 400);
            }
            
            $batchId = $data['batch_id'];
            $chillerId = $data['chiller_id'];
            $notes = $data['notes'] ?? '';
            
            // Get batch details with QC release info
            $batchStmt = $db->prepare("
                SELECT 
                    pb.*,
                    pb.product_id as batch_product_id,
                    COALESCE(pb.actual_yield, pb.expected_yield, 0) as quantity,
                    COALESCE(mr.product_id, pb.product_id) as resolved_product_id,
                    COALESCE(mr.product_name, pb.product_type) as product_name,
                    COALESCE(mr.product_type, pb.product_type) as product_type,
                    mr.variant,
                    qbr.id as qc_release_id
                FROM production_batches pb
                LEFT JOIN master_recipes mr ON pb.recipe_id = mr.id
                LEFT JOIN qc_batch_release qbr ON qbr.batch_id = pb.id AND qbr.release_decision = 'approved'
                WHERE pb.id = ? AND pb.qc_status = 'released'
            ");
            $batchStmt->execute([$batchId]);
            $batch = $batchStmt->fetch();
            
            if (!$batch) {
                Response::error('Batch not found or not released by QC', 404);
            }
            
            // Check if already received
            $checkStmt = $db->prepare("SELECT id FROM finished_goods_inventory WHERE batch_id = ?");
            $checkStmt->execute([$batchId]);
            if ($checkStmt->fetch()) {
                Response::error('This batch has already been received', 400);
            }
            
            // Get product_id - prefer batch's own product_id, then recipe's, then lookup
            $productId = $batch['batch_product_id'] ?? $batch['resolved_product_id'];
            if (!$productId) {
                // Look up by product_type or product_name in products table
                $prodStmt = $db->prepare("
                    SELECT id FROM products 
                    WHERE product_name = ? OR category = ? OR product_name LIKE ? 
                    LIMIT 1
                ");
                $prodStmt->execute([
                    $batch['product_name'], 
                    $batch['product_type'], 
                    '%' . $batch['product_type'] . '%'
                ]);
                $prod = $prodStmt->fetch();
                if (!$prod) {
                    Response::error('Cannot find matching product in products table for this batch. Please create the product first.', 400);
                }
                $productId = $prod['id'];
            }
            
            // Get qc_release_id - if not already in batch, look it up
            $qcReleaseId = $batch['qc_release_id'];
            if (!$qcReleaseId) {
                $qcStmt = $db->prepare("SELECT id FROM qc_batch_release WHERE batch_id = ? ORDER BY id DESC LIMIT 1");
                $qcStmt->execute([$batchId]);
                $qcRelease = $qcStmt->fetch();
                $qcReleaseId = $qcRelease ? $qcRelease['id'] : null;
            }
            
            // Ensure we have qc_release_id (required field)
            if (!$qcReleaseId) {
                // Create a placeholder QC release record
                $insertQcStmt = $db->prepare("
                    INSERT INTO qc_batch_release (release_code, batch_id, inspection_datetime, release_decision, inspected_by)
                    VALUES (?, ?, NOW(), 'approved', ?)
                ");
                $qcCode = 'QCR-' . date('Ymd') . '-' . str_pad($batchId, 4, '0', STR_PAD_LEFT);
                $insertQcStmt->execute([$qcCode, $batchId, $currentUser['user_id']]);
                $qcReleaseId = $db->lastInsertId();
            }
            
            $quantity = intval($batch['quantity']);
            // For batches from production, treat everything as individual pieces
            // Box/piece conversion happens at the warehouse level
            $piecesPerBox = 1;
            
            // All items treated as pieces initially
            $boxes = 0;
            $pieces = $quantity;
            
            $db->beginTransaction();
            
            try {
                // Create inventory record with all required fields
                $stmt = $db->prepare("
                    INSERT INTO finished_goods_inventory 
                    (product_id, batch_id, qc_release_id, milk_type_id, product_name, product_type, product_variant,
                     quantity, quantity_available, remaining_quantity,
                     quantity_boxes, quantity_pieces, boxes_available, pieces_available,
                     manufacturing_date, expiry_date, chiller_id, status, received_by, received_at, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', ?, NOW(), ?)
                ");
                
                $stmt->execute([
                    $productId,
                    $batchId,
                    $qcReleaseId,
                    $batch['milk_type_id'],
                    $batch['product_name'] ?? 'Unknown Product',
                    $batch['product_type'] ?? 'bottled_milk',
                    $batch['variant'] ?? null,
                    $quantity,
                    $quantity,
                    $quantity,
                    $boxes,
                    $pieces,
                    $boxes,
                    $pieces,
                    $batch['manufacturing_date'],
                    $batch['expiry_date'],
                    $chillerId,
                    $currentUser['user_id'],
                    $notes
                ]);
                
                $inventoryId = $db->lastInsertId();
                
                // Mark batch as received
                $db->prepare("UPDATE production_batches SET fg_received = 1 WHERE id = ?")->execute([$batchId]);
                
                // Update chiller count
                $db->prepare("UPDATE chiller_locations SET current_count = current_count + ? WHERE id = ?")
                   ->execute([$quantity, $chillerId]);
                
                // Log transaction
                $logStmt = $db->prepare("
                    INSERT INTO fg_inventory_transactions
                    (transaction_code, transaction_type, inventory_id, product_id, quantity, 
                     boxes_quantity, pieces_quantity, quantity_before, quantity_after,
                     boxes_before, pieces_before, boxes_after, pieces_after,
                     to_chiller_id, performed_by, reason)
                    VALUES (?, 'receive', ?, ?, ?, ?, ?, 0, ?, 0, 0, ?, ?, ?, ?, ?)
                ");
                
                $logStmt->execute([
                    'FGT-' . date('Ymd') . '-' . str_pad($inventoryId, 4, '0', STR_PAD_LEFT),
                    $inventoryId,
                    $productId,
                    $quantity,
                    $boxes,
                    $pieces,
                    $quantity,
                    $boxes,
                    $pieces,
                    $chillerId,
                    $currentUser['user_id'],
                    'Received from production batch ' . $batch['batch_code']
                ]);
                
                $db->commit();
                
                Response::success([
                    'id' => $inventoryId,
                    'batch_code' => $batch['batch_code'],
                    'product_name' => $batch['product_name'] ?? $batch['product_type'],
                    'quantity' => $quantity,
                    'boxes' => $boxes,
                    'pieces' => $pieces,
                    'chiller_id' => $chillerId
                ], 'Batch received successfully into FG inventory', 201);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

// ========================================
// PUT HANDLERS
// ========================================

function handlePut($db, $action, $currentUser) {
    $data = getRequestBody();
    $id = getParam('id') ?? ($data['id'] ?? $data['inventory_id'] ?? null);
    
    if (!$id && $action !== 'release') {
        Response::error('Inventory ID required', 400);
    }
    
    // Get current inventory for most actions
    $current = null;
    if ($id) {
        $check = $db->prepare("
            SELECT fg.*, p.pieces_per_box, p.base_unit, p.box_unit
            FROM finished_goods_inventory fg
            JOIN products p ON fg.product_id = p.id
            WHERE fg.id = ?
        ");
        $check->execute([$id]);
        $current = $check->fetch();
        
        if (!$current && $action !== 'release') {
            Response::error('Inventory item not found', 404);
        }
    }
    
    switch ($action) {
        case 'release':
            // Release inventory with multi-unit support
            $inventoryId = $id ?? $data['inventory_id'];
            if (!$inventoryId) {
                Response::error('Inventory ID required', 400);
            }
            
            $releasedBoxes = intval($data['boxes'] ?? $data['release_boxes'] ?? 0);
            $releasedPieces = intval($data['pieces'] ?? $data['release_pieces'] ?? 0);
            
            if ($releasedBoxes <= 0 && $releasedPieces <= 0) {
                Response::error('Specify boxes and/or pieces to release', 400);
            }
            
            $db->beginTransaction();
            
            try {
                $result = releaseMultiUnit(
                    $db,
                    $inventoryId,
                    $releasedBoxes,
                    $releasedPieces,
                    $currentUser['user_id'],
                    $data['reason'] ?? 'dispatch',
                    $data['reference_type'] ?? null,
                    $data['reference_id'] ?? null
                );
                
                $db->commit();
                
                Response::success($result, 'Inventory released successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'transfer':
            // Transfer to another chiller
            if (empty($data['to_chiller_id'])) {
                Response::error('Target chiller is required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Update inventory chiller
                $stmt = $db->prepare("UPDATE finished_goods_inventory SET chiller_id = ? WHERE id = ?");
                $stmt->execute([$data['to_chiller_id'], $id]);
                
                // Calculate total for chiller count
                $totalPieces = boxesToPieces(
                    $current['boxes_available'] ?? 0,
                    $current['pieces_available'] ?? 0,
                    $current['pieces_per_box'] ?? 1
                );
                
                // Update old chiller count
                if ($current['chiller_id']) {
                    $db->prepare("UPDATE chiller_locations SET current_count = current_count - ? WHERE id = ?")
                       ->execute([$totalPieces, $current['chiller_id']]);
                }
                
                // Update new chiller count
                $db->prepare("UPDATE chiller_locations SET current_count = current_count + ? WHERE id = ?")
                   ->execute([$totalPieces, $data['to_chiller_id']]);
                
                // Log transaction
                $logStmt = $db->prepare("
                    INSERT INTO fg_inventory_transactions
                    (transaction_code, transaction_type, inventory_id, product_id, quantity,
                     boxes_quantity, pieces_quantity, quantity_before, quantity_after, 
                     boxes_before, pieces_before, boxes_after, pieces_after,
                     from_chiller_id, to_chiller_id, performed_by, reason)
                    VALUES (?, 'transfer', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $logStmt->execute([
                    'FGT-' . date('Ymd') . '-' . uniqid(),
                    $id,
                    $current['product_id'],
                    $totalPieces,
                    $current['boxes_available'] ?? 0,
                    $current['pieces_available'] ?? 0,
                    $totalPieces,
                    $totalPieces,
                    $current['boxes_available'] ?? 0,
                    $current['pieces_available'] ?? 0,
                    $current['boxes_available'] ?? 0,
                    $current['pieces_available'] ?? 0,
                    $current['chiller_id'],
                    $data['to_chiller_id'],
                    $currentUser['user_id'],
                    $data['reason'] ?? 'Chiller transfer'
                ]);
                
                $db->commit();
                
                Response::success(null, 'Inventory transferred successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'adjust':
            // Stock adjustment with multi-unit support
            $newBoxes = isset($data['boxes']) ? intval($data['boxes']) : ($current['boxes_available'] ?? 0);
            $newPieces = isset($data['pieces']) ? intval($data['pieces']) : ($current['pieces_available'] ?? 0);
            
            // Also support new_quantity for backwards compatibility
            if (isset($data['new_quantity']) && !isset($data['boxes']) && !isset($data['pieces'])) {
                $converted = piecesToBoxes($data['new_quantity'], $current['pieces_per_box'] ?? 1);
                $newBoxes = $converted['boxes'];
                $newPieces = $converted['pieces'];
            }
            
            $piecesPerBox = $current['pieces_per_box'] ?? 1;
            $newTotalPieces = boxesToPieces($newBoxes, $newPieces, $piecesPerBox);
            $oldTotalPieces = boxesToPieces(
                $current['boxes_available'] ?? 0, 
                $current['pieces_available'] ?? 0, 
                $piecesPerBox
            );
            $difference = $newTotalPieces - $oldTotalPieces;
            
            $db->beginTransaction();
            
            try {
                $stmt = $db->prepare("
                    UPDATE finished_goods_inventory 
                    SET quantity_available = ?,
                        quantity_boxes = ?,
                        boxes_available = ?,
                        quantity_pieces = ?,
                        pieces_available = ?
                    WHERE id = ?
                ");
                $stmt->execute([$newTotalPieces, $newBoxes, $newBoxes, $newPieces, $newPieces, $id]);
                
                // Update chiller count
                if ($current['chiller_id']) {
                    $db->prepare("UPDATE chiller_locations SET current_count = current_count + ? WHERE id = ?")
                       ->execute([$difference, $current['chiller_id']]);
                }
                
                // Log transaction
                $logStmt = $db->prepare("
                    INSERT INTO fg_inventory_transactions
                    (transaction_code, transaction_type, inventory_id, product_id, quantity,
                     boxes_quantity, pieces_quantity, quantity_before, quantity_after,
                     boxes_before, pieces_before, boxes_after, pieces_after,
                     performed_by, reason)
                    VALUES (?, 'adjust', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $logStmt->execute([
                    'FGT-' . date('Ymd') . '-' . uniqid(),
                    $id,
                    $current['product_id'],
                    abs($difference),
                    abs($newBoxes - ($current['boxes_available'] ?? 0)),
                    abs($newPieces - ($current['pieces_available'] ?? 0)),
                    $oldTotalPieces,
                    $newTotalPieces,
                    $current['boxes_available'] ?? 0,
                    $current['pieces_available'] ?? 0,
                    $newBoxes,
                    $newPieces,
                    $currentUser['user_id'],
                    $data['reason'] ?? 'Stock adjustment'
                ]);
                
                $db->commit();
                
                Response::success([
                    'boxes' => $newBoxes,
                    'pieces' => $newPieces,
                    'total_quantity' => $newTotalPieces,
                    'display' => formatMultiUnitDisplay($newBoxes, $newPieces, $current['box_unit'] ?? 'box', $current['base_unit'] ?? 'piece'),
                    'adjustment' => $difference
                ], 'Inventory adjusted successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'dispose':
            $db->beginTransaction();
            
            try {
                $oldBoxes = $current['boxes_available'] ?? 0;
                $oldPieces = $current['pieces_available'] ?? 0;
                $oldTotal = boxesToPieces($oldBoxes, $oldPieces, $current['pieces_per_box'] ?? 1);
                
                $stmt = $db->prepare("
                    UPDATE finished_goods_inventory 
                    SET status = 'disposed', 
                        quantity_available = 0,
                        quantity_boxes = 0,
                        boxes_available = 0,
                        quantity_pieces = 0,
                        pieces_available = 0
                    WHERE id = ?
                ");
                $stmt->execute([$id]);
                
                // Update chiller count
                if ($current['chiller_id']) {
                    $db->prepare("UPDATE chiller_locations SET current_count = current_count - ? WHERE id = ?")
                       ->execute([$oldTotal, $current['chiller_id']]);
                }
                
                // Log transaction
                $logStmt = $db->prepare("
                    INSERT INTO fg_inventory_transactions
                    (transaction_code, transaction_type, inventory_id, product_id, quantity,
                     boxes_quantity, pieces_quantity, quantity_before, quantity_after,
                     boxes_before, pieces_before, boxes_after, pieces_after,
                     performed_by, reason)
                    VALUES (?, 'dispose', ?, ?, ?, ?, ?, ?, 0, ?, ?, 0, 0, ?, ?)
                ");
                
                $logStmt->execute([
                    'FGT-' . date('Ymd') . '-' . uniqid(),
                    $id,
                    $current['product_id'],
                    $oldTotal,
                    $oldBoxes,
                    $oldPieces,
                    $oldTotal,
                    $oldBoxes,
                    $oldPieces,
                    $currentUser['user_id'],
                    $data['reason'] ?? 'Disposed'
                ]);
                
                $db->commit();
                
                Response::success([
                    'disposed_boxes' => $oldBoxes,
                    'disposed_pieces' => $oldPieces,
                    'disposed_total' => $oldTotal
                ], 'Inventory disposed successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
