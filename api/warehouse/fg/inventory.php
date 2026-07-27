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
require_once __DIR__ . '/inventory_helpers.php';

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
 * Check if an optional table exists in the current database.
 * @param PDO $db Database connection
 * @param string $tableName Table name
 * @return bool
 */
function fgInventoryTableExists($db, $tableName) {
    static $cache = [];

    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }

    $stmt = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$tableName]);
    $cache[$tableName] = (bool) $stmt->fetchColumn();

    return $cache[$tableName];
}

/**
 * Get product unit configuration
 * @param PDO $db Database connection
 * @param int $productId Product ID
 * @return array Unit config with pieces_per_box, base_unit, box_unit
 */
function getProductUnitConfig($db, $productId) {
    $defaultConfig = [
        'base_unit' => 'piece',
        'box_unit' => 'box',
        'pieces_per_box' => 1
    ];

    if (empty($productId)) {
        return $defaultConfig;
    }

    // Some installs use the products table only; newer installs may also have product_units.
    if (fgInventoryTableExists($db, 'product_units')) {
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
    }
    
    // Fallback to products table columns
    $stmt = $db->prepare("
        SELECT id, product_name,
               COALESCE(base_unit, 'piece') as base_unit,
               COALESCE(box_unit, 'box') as box_unit,
               COALESCE(pieces_per_box, 1) as pieces_per_box
        FROM products 
        WHERE id = ?
    ");
    $stmt->execute([$productId]);
    return $stmt->fetch() ?: $defaultConfig;
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
 * @return int Total pieces (minimum 0)
 */
function boxesToPieces($boxes, $pieces, $piecesPerBox) {
    if ($piecesPerBox <= 0) $piecesPerBox = 1;
    $boxes = max(0, (int)$boxes);
    $pieces = max(0, (int)$pieces);
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
        SET quantity_boxes = GREATEST(0, quantity_boxes - ?),
            boxes_available = GREATEST(0, boxes_available - ?),
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

    if (($inventory['status'] ?? '') !== 'available') {
        throw new Exception('Only available inventory can be released.');
    }

    if (!empty($inventory['expiry_date']) && $inventory['expiry_date'] <= date('Y-m-d')) {
        throw new Exception('Expired inventory cannot be released. Dispose or quarantine this stock instead.');
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
            quantity_available = GREATEST(0, COALESCE(quantity_available, 0) - ?),
            remaining_quantity = GREATEST(0, COALESCE(remaining_quantity, 0) - ?)
        WHERE id = ?
    ");
    $updateStmt->execute([
        $boxesAfter,
        $boxesAfter,
        $piecesAfter,
        $piecesAfter,
        $totalReleasedPieces,
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
            $includeExpired = in_array((string)getParam('include_expired', '0'), ['1', 'true', 'yes'], true);
            
            $sql = "
                SELECT 
                    fg.*,
                    COALESCE(p.product_name, fg.product_name) as product_name,
                    COALESCE(p.variant, fg.product_variant, fg.variant) as variant,
                    COALESCE(p.unit_size, fg.size_ml) as size_value,
                    COALESCE(p.unit_measure, fg.unit, 'ml') as size_unit,
                    p.category,
                    COALESCE(p.base_unit, 'piece') as base_unit,
                    COALESCE(p.box_unit, 'box') as box_unit,
                    COALESCE(p.pieces_per_box, 1) as pieces_per_box,
                    c.chiller_code,
                    c.chiller_name,
                    COALESCE(pb.batch_code, CONCAT('PKG-', fg.id)) as batch_code,
                    DATEDIFF(fg.expiry_date, CURDATE()) as days_until_expiry,
                    CASE
                        WHEN fg.status <> 'available' THEN fg.status
                        WHEN fg.expiry_date < CURDATE() THEN 'expired'
                        WHEN DATEDIFF(fg.expiry_date, CURDATE()) <= 3 THEN 'expiring'
                        ELSE 'good'
                    END as effective_status,
                    CASE
                        WHEN fg.status = 'available'
                         AND fg.expiry_date >= CURDATE()
                         AND (
                            COALESCE(fg.quantity_available, 0) > 0
                            OR COALESCE(fg.boxes_available, 0) > 0
                            OR COALESCE(fg.pieces_available, 0) > 0
                         )
                        THEN 1 ELSE 0
                    END as is_dispatchable,
                    -- Multi-unit: keep raw (may be negative) for integrity UI.
                    -- If multi-unit columns are both zero, fall back to legacy base qty.
                    COALESCE(fg.boxes_available, fg.quantity_boxes, 0) as quantity_boxes_raw,
                    COALESCE(fg.pieces_available, fg.quantity_pieces, 0) as quantity_pieces_raw,
                    CASE
                        WHEN COALESCE(fg.boxes_available, 0) <> 0 OR COALESCE(fg.pieces_available, 0) <> 0
                             OR COALESCE(fg.quantity_boxes, 0) <> 0 OR COALESCE(fg.quantity_pieces, 0) <> 0
                        THEN COALESCE(fg.boxes_available, fg.quantity_boxes, 0)
                        ELSE 0
                    END as quantity_boxes,
                    CASE
                        WHEN COALESCE(fg.boxes_available, 0) <> 0 OR COALESCE(fg.pieces_available, 0) <> 0
                             OR COALESCE(fg.quantity_boxes, 0) <> 0 OR COALESCE(fg.quantity_pieces, 0) <> 0
                        THEN COALESCE(fg.pieces_available, fg.quantity_pieces, 0)
                        ELSE COALESCE(fg.quantity_available, fg.remaining_quantity, 0)
                    END as quantity_pieces,
                    CASE
                        WHEN COALESCE(fg.boxes_available, 0) <> 0 OR COALESCE(fg.pieces_available, 0) <> 0
                             OR COALESCE(fg.quantity_boxes, 0) <> 0 OR COALESCE(fg.quantity_pieces, 0) <> 0
                        THEN COALESCE(fg.boxes_available, fg.quantity_boxes, 0)
                        ELSE 0
                    END as boxes_available,
                    CASE
                        WHEN COALESCE(fg.boxes_available, 0) <> 0 OR COALESCE(fg.pieces_available, 0) <> 0
                             OR COALESCE(fg.quantity_boxes, 0) <> 0 OR COALESCE(fg.quantity_pieces, 0) <> 0
                        THEN COALESCE(fg.pieces_available, fg.quantity_pieces, 0)
                        ELSE COALESCE(fg.quantity_available, fg.remaining_quantity, 0)
                    END as pieces_available,
                    CASE WHEN fg.chiller_id IS NULL THEN 1 ELSE 0 END as awaiting_putaway
                FROM finished_goods_inventory fg
                LEFT JOIN products p ON fg.product_id = p.id
                LEFT JOIN chiller_locations c ON fg.chiller_id = c.id
                LEFT JOIN production_batches pb ON fg.batch_id = pb.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if ($status) {
                $sql .= " AND fg.status = ?";
                $params[] = $status;
            }

            // Default list excludes only fully-consumed expired items.
            // Expired batches that still hold stock must remain visible so the
            // "Expired" filter pill can count and display them.
            if (!$includeExpired) {
                $sql .= " AND (fg.expiry_date IS NULL OR fg.expiry_date >= CURDATE()
                    OR (fg.expiry_date < CURDATE()
                        AND (COALESCE(fg.quantity_available, 0) > 0
                             OR COALESCE(fg.boxes_available, 0) > 0
                             OR COALESCE(fg.pieces_available, 0) > 0)))";
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
            
            // Add formatted display strings + integrity flags
            foreach ($inventory as &$item) {
                $piecesPerBox = max(1, (int) ($item['pieces_per_box'] ?? 1));
                $boxesAvailable = (int) ($item['boxes_available'] ?? 0);
                $piecesAvailable = (int) ($item['pieces_available'] ?? 0);
                $booked = (int) ($item['quantity_available'] ?? $item['remaining_quantity'] ?? 0);

                $rawBoxes = (int) ($item['quantity_boxes_raw'] ?? $boxesAvailable);
                $rawPieces = (int) ($item['quantity_pieces_raw'] ?? $piecesAvailable);

                $integrityIssues = [];
                if ($rawBoxes < 0) {
                    $integrityIssues[] = "Negative boxes_available ({$rawBoxes})";
                }
                if ($rawPieces < 0) {
                    $integrityIssues[] = "Negative pieces_available ({$rawPieces})";
                }
                if ((int)($item['quantity_available'] ?? 0) < 0) {
                    $integrityIssues[] = 'Negative quantity_available';
                }

                // Display split: if only loose base units and pack configured, split for presentation
                $displayBoxes = $boxesAvailable;
                $displayPieces = $piecesAvailable;
                if ($boxesAvailable === 0 && $piecesAvailable > 0 && $piecesPerBox > 1) {
                    $converted = piecesToBoxes($piecesAvailable, $piecesPerBox);
                    $displayBoxes = $converted['boxes'];
                    $displayPieces = $converted['pieces'];
                    $item['boxes_available'] = $displayBoxes;
                    $item['pieces_available'] = $displayPieces;
                    $item['quantity_boxes'] = $displayBoxes;
                    $item['quantity_pieces'] = $displayPieces;
                }

                $multiTotal = boxesToPieces($displayBoxes, $displayPieces, $piecesPerBox);
                if ($multiTotal > 0 && $booked > 0 && $multiTotal !== $booked && ($rawBoxes !== 0 || $rawPieces !== 0)) {
                    // Only flag when multi-unit columns were actually used (not pure legacy)
                    if ($rawBoxes != 0 || ($item['boxes_available'] !== null && $rawPieces != $booked && $rawBoxes != 0)) {
                        if ($rawBoxes != 0 || (isset($item['quantity_boxes_raw']) && (int)$item['quantity_boxes_raw'] != 0)) {
                            $integrityIssues[] = "Booked {$booked} base units vs multi-unit {$multiTotal}";
                        }
                    }
                }
                if ($booked === 0 && $displayBoxes > 0) {
                    $integrityIssues[] = "quantity_available is 0 but {$displayBoxes} pack(s) still shown";
                }

                $item['inventory_display'] = formatMultiUnitDisplay(
                    max(0, $displayBoxes),
                    max(0, $displayPieces),
                    $item['box_unit'],
                    $item['base_unit']
                );
                $item['total_pieces'] = max(0, $multiTotal > 0 ? $multiTotal : $booked);
                $item['pack_config_label'] = $piecesPerBox > 1
                    ? ('1 ' . rtrim($item['box_unit'] ?? 'box', 's') . ' = ' . $piecesPerBox . ' ' . ($item['base_unit'] ?? 'piece') . ( $piecesPerBox == 1 ? '' : 's'))
                    : ('Stored as individual ' . ($item['base_unit'] ?? 'piece') . 's (no pack size configured)');
                $item['has_negative_qty'] = ($rawBoxes < 0 || $rawPieces < 0 || (int)($item['quantity_available'] ?? 0) < 0);
                $item['integrity_ok'] = empty($integrityIssues);
                $item['integrity_issues'] = $integrityIssues;
                $item['integrity_message'] = empty($integrityIssues) ? null : implode('; ', $integrityIssues);
                $item['integrity_error'] = !empty($integrityIssues);
            }
            unset($item);
            
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
                    COALESCE(p.product_name, fg.product_name) as product_name,
                    COALESCE(p.variant, fg.product_variant, fg.variant) as variant,
                    COALESCE(p.unit_size, fg.size_ml) as unit_size,
                    COALESCE(p.unit_measure, fg.unit, 'ml') as unit_measure,
                    COALESCE(p.base_unit, 'piece') as base_unit,
                    COALESCE(p.box_unit, 'box') as box_unit,
                    COALESCE(p.pieces_per_box, 1) as pieces_per_box,
                    c.chiller_code,
                    c.chiller_name,
                    COALESCE(pb.batch_code, CONCAT('PKG-', fg.id)) as batch_code,
                    GREATEST(0, COALESCE(fg.quantity_boxes, 0)) as quantity_boxes,
                    GREATEST(0, COALESCE(fg.quantity_pieces, fg.quantity_available, 0)) as quantity_pieces,
                    GREATEST(0, COALESCE(fg.boxes_available, 0)) as boxes_available,
                    GREATEST(0, COALESCE(fg.pieces_available, fg.quantity_available, 0)) as pieces_available
                FROM finished_goods_inventory fg
                LEFT JOIN products p ON fg.product_id = p.id
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
                    COALESCE(fg.pieces_available, 0) as pieces_available,
                    COALESCE(
                        NULLIF(pb.batch_code, ''),
                        CONCAT(
                            'BATCH-',
                            DATE_FORMAT(COALESCE(pb.manufacturing_date, fg.manufacturing_date, CURDATE()), '%y%m'),
                            '-A',
                            LPAD(COALESCE(fg.batch_id, fg.id), 2, '0')
                        )
                    ) as batch_code
                FROM finished_goods_inventory fg
                JOIN products p ON fg.product_id = p.id
                LEFT JOIN chiller_locations c ON fg.chiller_id = c.id
                LEFT JOIN production_batches pb ON fg.batch_id = pb.id
                WHERE fg.status = 'available'
                AND fg.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                AND fg.expiry_date >= CURDATE()
                AND (fg.quantity_available > 0 OR fg.boxes_available > 0 OR fg.pieces_available > 0)
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
            
            $sql .= " GROUP BY p.id, p.product_name, p.variant, p.base_unit, p.box_unit, p.pieces_per_box ORDER BY p.product_name";
            
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

        case 'putaway_count':
            try {
                if (!fgInventoryTableExists($db, 'put_away_queue')) {
                    Response::success(['count' => 0, 'items' => []], 'Put-away queue empty');
                    break;
                }
                $countStmt = $db->prepare("
                    SELECT COUNT(*) as count
                    FROM put_away_queue
                    WHERE status = 'pending'
                ");
                $countStmt->execute();
                $count = (int)$countStmt->fetchColumn();
                Response::success(['count' => $count], 'Put-away count retrieved');
            } catch (Throwable $e) {
                Response::success(['count' => 0], 'Put-away queue not available');
            }
            break;

        case 'pending_returns':
            try {
                if (!fgInventoryTableExists($db, 'put_away_queue')) {
                    Response::success(['items' => [], 'count' => 0], 'Put-away queue empty');
                    break;
                }
                $stmt = $db->prepare("
                    SELECT
                        pq.*,
                        p.product_name,
                        p.pieces_per_box,
                        p.base_unit,
                        p.box_unit,
                        pb.expiry_date as batch_expiry_date,
                        cl.chiller_name as chiller_location_name,
                        cl.location as chiller_location_detail,
                        CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                    FROM put_away_queue pq
                    LEFT JOIN products p ON pq.product_id = p.id
                    LEFT JOIN production_batches pb ON pq.batch_id = pb.id
                    LEFT JOIN chiller_locations cl ON pq.chiller_id = cl.id
                    LEFT JOIN users u ON pq.created_by = u.id
                    WHERE pq.status = 'pending'
                    ORDER BY pq.created_at ASC
                ");
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($items as &$item) {
                    $ppb = max(1, (int)($item['pieces_per_box'] ?? 1));
                    $qty = (int)$item['quantity'];
                    $converted = piecesToBoxes($qty, $ppb);
                    $item['display_boxes'] = $converted['boxes'];
                    $item['display_pieces'] = $converted['pieces'];
                    $item['display_label'] = formatMultiUnitDisplay(
                        $converted['boxes'],
                        $converted['pieces'],
                        $item['box_unit'] ?? 'box',
                        $item['base_unit'] ?? 'piece'
                    );
                    if (empty($item['expiry_date']) && !empty($item['batch_expiry_date'])) {
                        $item['expiry_date'] = $item['batch_expiry_date'];
                    }
                    $locParts = array_filter([
                        $item['chiller_location_name'] ?? null,
                        $item['chiller_location_detail'] ?? null
                    ]);
                    $item['location_label'] = $locParts
                        ? implode(' - ', $locParts)
                        : ($item['chiller_name'] ?? null);
                }
                unset($item);

                Response::success([
                    'items' => $items,
                    'count' => count($items)
                ], 'Pending put-away items retrieved');
            } catch (Throwable $e) {
                error_log('pending_returns query failed: ' . $e->getMessage());
                Response::success(['items' => [], 'count' => 0], 'Put-away queue not available');
            }
            break;
            
        case 'pending_batches':
            // V4.1: QC-released production batches awaiting warehouse receive.
            // Source of truth: production_batches with qc_status='released' and not yet
            // received into FG (fg_received = 0). FG inventory rows are created HERE,
            // per packaging line, when the warehouse receives the batch into a chiller.
            try {
                $stmt = $db->prepare("
                    SELECT
                        pb.id,
                        pb.id AS batch_id,
                        pb.batch_code,
                        pb.batch_code AS batch_number,
                        pb.product_id,
                        pb.product_type,
                        pb.product_variant,
                        COALESCE(p.product_name, mr.product_name, pb.product_type) AS product_name,
                        COALESCE(p.pieces_per_box, 1) AS pieces_per_box,
                        COALESCE(p.base_unit, 'piece') AS base_unit,
                        COALESCE(p.box_unit, 'box') AS box_unit,
                        pb.manufacturing_date,
                        pb.manufacturing_date AS production_date,
                        pb.expiry_date,
                        pb.qc_status,
                        pb.actual_yield,
                        pb.expected_yield,
                        pb.packaging_run_id,
                        pb.run_id,
                        qbr.release_decision AS qc_decision,
                        qbr.inspection_datetime AS qc_release_date,
                        (SELECT COALESCE(SUM(pri.quantity), 0)
                           FROM packaging_run_items pri
                           JOIN packaging_runs pr ON pri.packaging_run_id = pr.id
                          WHERE pr.batch_id = pb.id OR pr.production_run_id = pb.run_id) AS packed_total,
                        (SELECT MAX(pr.created_at)
                           FROM packaging_runs pr
                          WHERE pr.batch_id = pb.id OR pr.production_run_id = pb.run_id) AS packaged_at
                    FROM production_batches pb
                    LEFT JOIN products p ON pb.product_id = p.id
                    LEFT JOIN master_recipes mr ON pb.recipe_id = mr.id
                    LEFT JOIN qc_batch_release qbr ON qbr.batch_id = pb.id
                    WHERE pb.qc_status = 'released'
                      AND COALESCE(pb.fg_received, 0) = 0
                    ORDER BY pb.manufacturing_date ASC, pb.id ASC
                ");
                $stmt->execute();
                $pendingItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                error_log('pending_batches query failed: ' . $e->getMessage());
                Response::error('Could not load receive-from-production queue: ' . $e->getMessage(), 500);
                break;
            }

            // Attach packaging lines (per SKU/size) to each batch for display
            $lineStmt = $db->prepare("
                SELECT pri.product_name, pri.product_variant, pri.size_ml, pri.unit_measure, pri.quantity
                FROM packaging_run_items pri
                JOIN packaging_runs pr ON pri.packaging_run_id = pr.id
                WHERE pr.batch_id = ? OR pr.production_run_id = ?
                ORDER BY pri.size_ml DESC, pri.id ASC
            ");

            foreach ($pendingItems as &$pending) {
                $batchId = (int) $pending['id'];
                $runId = (int) ($pending['run_id'] ?? 0);
                $lineStmt->execute([$batchId, $runId]);
                $lines = $lineStmt->fetchAll(PDO::FETCH_ASSOC);

                // Total pieces: prefer sum of packaging lines, fall back to batch yield
                $totalPieces = 0;
                foreach ($lines as $l) {
                    $totalPieces += (int) ($l['quantity'] ?? 0);
                }
                if ($totalPieces <= 0) {
                    $totalPieces = (int) ($pending['actual_yield'] ?? $pending['expected_yield'] ?? 0);
                }

                $ppb = max(1, (int) ($pending['pieces_per_box'] ?? 1));
                $boxes = $ppb > 1 ? intdiv($totalPieces, $ppb) : 0;
                $pieces = $ppb > 1 ? ($totalPieces % $ppb) : $totalPieces;

                // Representative size/variant for single-line batches
                $pending['size_ml'] = count($lines) === 1 ? ($lines[0]['size_ml'] ?? null) : null;
                $pending['variant'] = $pending['product_variant']
                    ?? (count($lines) === 1 ? ($lines[0]['product_variant'] ?? null) : null);
                $pending['packaging_lines'] = $lines;
                $pending['line_count'] = count($lines);
                $pending['quantity_available'] = $totalPieces;
                $pending['total_pieces'] = $totalPieces;
                $pending['actual_yield'] = $totalPieces;
                $pending['boxes_available'] = $boxes;
                $pending['pieces_available'] = $pieces;
                $pending['quantity_boxes'] = $boxes;
                $pending['quantity_pieces'] = $pieces;
                $pending['inventory_display'] = formatMultiUnitDisplay(
                    $boxes,
                    $pieces,
                    $pending['box_unit'] ?? 'box',
                    $pending['base_unit'] ?? 'piece'
                );
                $pending['pack_config_label'] = $ppb > 1
                    ? ('1 ' . rtrim($pending['box_unit'] ?? 'box', 's') . ' = ' . $ppb . ' ' . ($pending['base_unit'] ?? 'piece') . 's')
                    : ('Stored as individual ' . ($pending['base_unit'] ?? 'piece') . 's (no pack size configured)');
                $pending['awaiting_putaway'] = true;
            }
            unset($pending);

            Response::success([
                'batches' => $pendingItems,
                'count' => count($pendingItems)
            ], 'QC-released batches awaiting receive retrieved');
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
                    pb.batch_code,
                    t.boxes_quantity as quantity_boxes,
                    t.pieces_quantity as quantity_pieces,
                    COALESCE(p.pieces_per_box, 1) as pieces_per_box,
                    COALESCE(p.base_unit, 'piece') as base_unit,
                    COALESCE(p.box_unit, 'box') as box_unit,
                    COALESCE(c.chiller_name, c2.chiller_name, 'N/A') as chiller_name,
                    c.chiller_code as from_chiller,
                    c2.chiller_code as to_chiller,
                    CONCAT(u.first_name, ' ', u.last_name) as received_by_name
                FROM fg_inventory_transactions t
                LEFT JOIN finished_goods_inventory fg ON t.inventory_id = fg.id
                LEFT JOIN production_batches pb ON fg.batch_id = pb.id
                LEFT JOIN products p ON t.product_id = p.id
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
            
        case 'assign_to_chiller':
            // Assign packaged FG inventory items to a chiller (new flow: Packaging creates FG records, Warehouse assigns to chiller)
            if (empty($data['inventory_id'])) {
                Response::error('Inventory ID is required', 400);
            }
            if (empty($data['chiller_id'])) {
                Response::error('Chiller ID is required', 400);
            }
            
            $inventoryId = $data['inventory_id'];
            $chillerId = $data['chiller_id'];
            $notes = $data['notes'] ?? '';
            
            // Verify the inventory item exists and has no chiller assigned yet
            $checkStmt = $db->prepare("
                SELECT id, product_id, product_name, remaining_quantity, quantity_available, status,
                       boxes_available, pieces_available
                FROM finished_goods_inventory
                WHERE id = ? AND status = 'available' AND chiller_id IS NULL
            ");
            $checkStmt->execute([$inventoryId]);
            $fgItem = $checkStmt->fetch();
            
            if (!$fgItem) {
                Response::error('Inventory item not found, already assigned, or not available', 404);
            }
            
            // Verify chiller exists and is available
            $chillerStmt = $db->prepare("
                SELECT id, chiller_name, chiller_code, capacity,
                       COALESCE((SELECT SUM(GREATEST(fi.quantity_available, 0))
                                 FROM finished_goods_inventory fi
                                 WHERE fi.chiller_id = c.id
                                   AND fi.status = 'available'
                                   AND fi.quantity_available > 0), 0) as current_count
                FROM chiller_locations c
                WHERE c.id = ? AND (c.status = 'available' OR c.is_active = 1)
            ");
            $chillerStmt->execute([$chillerId]);
            $chiller = $chillerStmt->fetch();
            
            if (!$chiller) {
                Response::error('Chiller not found or not available', 404);
            }
            
            // Resolve on-hand base units, preferring multi-unit when already set
            // (packaging often writes correct boxes/pieces; never re-inflate booked to a stale total).
            $unitConfig = getProductUnitConfig($db, $fgItem['product_id']);
            $piecesPerBox = max(1, (int) ($unitConfig['pieces_per_box'] ?? 1));
            $multiTotal = boxesToPieces(
                $fgItem['boxes_available'] ?? 0,
                $fgItem['pieces_available'] ?? 0,
                $piecesPerBox
            );
            $bookedTotal = (int) ($fgItem['quantity_available'] ?? 0);
            if ($bookedTotal <= 0) {
                $bookedTotal = (int) ($fgItem['remaining_quantity'] ?? 0);
            }
            // Prefer multi-unit when it has stock; else booked; else multi again
            $totalPieces = $multiTotal > 0 ? $multiTotal : $bookedTotal;
            if ($totalPieces <= 0) {
                $totalPieces = $multiTotal;
            }
            $converted = piecesToBoxes($totalPieces, $piecesPerBox);

            // Capacity guard: prevent overfilling a chiller
            $chillerCurrentCount = (int) ($chiller['current_count'] ?? 0);
            $chillerCapacity = (int) ($chiller['capacity'] ?? 0);
            if ($chillerCapacity > 0 && ($chillerCurrentCount + $totalPieces) > $chillerCapacity) {
                $remaining = max(0, $chillerCapacity - $chillerCurrentCount);
                Response::error(
                    "Chiller \"{$chiller['chiller_name']}\" is full ({$chillerCurrentCount}/{$chillerCapacity}). "
                    . "Only {$remaining} unit(s) can fit. Choose another chiller.",
                    400
                );
            }

            $db->beginTransaction();

            try {
                // Keep booked base qty and multi-unit columns in lockstep on put-away
                $updateStmt = $db->prepare("
                    UPDATE finished_goods_inventory
                    SET chiller_id = ?,
                        chiller_location = ?,
                        quantity = GREATEST(COALESCE(quantity, 0), ?),
                        remaining_quantity = ?,
                        quantity_available = ?,
                        quantity_boxes = ?,
                        quantity_pieces = ?,
                        boxes_available = ?,
                        pieces_available = ?,
                        last_movement_at = NOW(),
                        notes = CONCAT(COALESCE(notes, ''), '\n', ?)
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $chillerId,
                    $chiller['chiller_name'],
                    $totalPieces,
                    $totalPieces,
                    $totalPieces,
                    $converted['boxes'],
                    $converted['pieces'],
                    $converted['boxes'],
                    $converted['pieces'],
                    "Assigned to {$chiller['chiller_name']} by warehouse on " . date('Y-m-d H:i:s') . ($notes ? " - {$notes}" : ''),
                    $inventoryId
                ]);

                $db->prepare("UPDATE chiller_locations SET current_count = current_count + ? WHERE id = ?")
                   ->execute([$totalPieces, $chillerId]);

                // Mark production batch as FG-received once stock has a chiller location
                try {
                    $batchLink = $db->prepare("SELECT batch_id FROM finished_goods_inventory WHERE id = ?");
                    $batchLink->execute([$inventoryId]);
                    $linkedBatchId = (int) ($batchLink->fetchColumn() ?: 0);
                    if ($linkedBatchId > 0) {
                        $db->prepare("
                            UPDATE production_batches
                            SET fg_received = 1,
                                fg_received_at = NOW(),
                                fg_received_by = ?
                            WHERE id = ?
                        ")->execute([(int) $currentUser['user_id'], $linkedBatchId]);
                    }
                } catch (Throwable $e) {
                    // optional columns
                }

                // Log put-away (location assignment). Type remains 'receive' for schema compat;
                // reason text clarifies internal put-away.
                if (!empty($fgItem['product_id'])) {
                    $logStmt = $db->prepare("
                        INSERT INTO fg_inventory_transactions
                        (transaction_code, transaction_type, inventory_id, product_id, quantity,
                         boxes_quantity, pieces_quantity, quantity_before, quantity_after,
                         boxes_before, pieces_before, boxes_after, pieces_after,
                         to_chiller_id, performed_by, reason, created_at)
                        VALUES (?, 'receive', ?, ?, ?, ?, ?, 0, ?, 0, 0, ?, ?, ?, ?, ?, NOW())
                    ");
                    $logStmt->execute([
                        'FGT-' . date('Ymd') . '-' . uniqid(),
                        $inventoryId,
                        $fgItem['product_id'],
                        $totalPieces,
                        $converted['boxes'],
                        $converted['pieces'],
                        $totalPieces,
                        $converted['boxes'],
                        $converted['pieces'],
                        $chillerId,
                        $currentUser['user_id'],
                        $notes ?: 'Put-away: assigned packaged stock to chiller'
                    ]);
                }

                $db->commit();

                $display = formatMultiUnitDisplay(
                    $converted['boxes'],
                    $converted['pieces'],
                    $unitConfig['box_unit'] ?? 'box',
                    $unitConfig['base_unit'] ?? 'piece'
                );

                Response::success([
                    'inventory_id' => $inventoryId,
                    'chiller_id' => $chillerId,
                    'chiller_name' => $chiller['chiller_name'],
                    'boxes' => $converted['boxes'],
                    'pieces' => $converted['pieces'],
                    'total_pieces' => $totalPieces,
                    'pieces_per_box' => $piecesPerBox,
                    'base_unit' => $unitConfig['base_unit'] ?? 'piece',
                    'box_unit' => $unitConfig['box_unit'] ?? 'box',
                    'display' => $display,
                    'transaction_logged' => !empty($fgItem['product_id'])
                ], 'Put-away complete: assigned to chiller');
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'receive_batch':
            // V4.1: Receive a QC-released batch into FG inventory.
            // Creates one FG row per packaging line (per SKU/size) instead of one row for the whole batch.
            if (empty($data['batch_id'])) {
                Response::error('Batch ID is required', 400);
            }
            if (empty($data['chiller_id'])) {
                Response::error('Chiller ID is required', 400);
            }

            $batchId = (int) $data['batch_id'];
            $chillerId = (int) $data['chiller_id'];
            $notes = $data['notes'] ?? '';

            // Get batch — must be QC released
            $batchStmt = $db->prepare("
                SELECT pb.*, mr.product_name AS recipe_product_name, mr.product_type AS recipe_product_type
                FROM production_batches pb
                LEFT JOIN master_recipes mr ON pb.recipe_id = mr.id
                WHERE pb.id = ? AND pb.qc_status = 'released'
            ");
            $batchStmt->execute([$batchId]);
            $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);

            if (!$batch) {
                Response::error('Batch not found or not yet released by QC', 404);
            }

            // Check if already received
            $checkStmt = $db->prepare("SELECT id FROM finished_goods_inventory WHERE batch_id = ? LIMIT 1");
            $checkStmt->execute([$batchId]);
            if ($checkStmt->fetch()) {
                Response::error('This batch has already been received into FG inventory', 400);
            }

            // Get packaging lines for this batch (what production actually packed)
            $pkgStmt = $db->prepare("
                SELECT pri.id AS item_id, pri.product_id, pri.product_name, pri.product_variant,
                       pri.size_ml, pri.unit_measure, pri.quantity
                FROM packaging_run_items pri
                JOIN packaging_runs pr ON pri.packaging_run_id = pr.id
                WHERE pr.batch_id = ? OR pr.production_run_id = ?
                ORDER BY pri.id
            ");
            $pkgStmt->execute([$batchId, (int) ($batch['run_id'] ?? 0)]);
            $packagingLines = $pkgStmt->fetchAll(PDO::FETCH_ASSOC);

            // Fallback: if no packaging records (legacy batch), create a single row from batch totals
            if (empty($packagingLines)) {
                $fallbackQty = (int) ($batch['actual_yield'] ?? $batch['expected_yield'] ?? 0);
                if ($fallbackQty <= 0) {
                    Response::error('No packaging records and no yield on this batch. Cannot receive.', 400);
                }
                $packagingLines = [[
                    'item_id' => null,
                    'product_id' => $batch['product_id'] ?? null,
                    'product_name' => $batch['recipe_product_name'] ?? $batch['product_type'] ?? 'Product',
                    'product_variant' => null,
                    'size_ml' => null,
                    'unit_measure' => 'ml',
                    'quantity' => $fallbackQty,
                ]];
            }

            // Resolve QC release ID
            $qcReleaseId = null;
            $qcStmt = $db->prepare("SELECT id FROM qc_batch_release WHERE batch_id = ? ORDER BY id DESC LIMIT 1");
            $qcStmt->execute([$batchId]);
            $qcRelease = $qcStmt->fetch();
            $qcReleaseId = $qcRelease ? $qcRelease['id'] : null;

            $db->beginTransaction();

            try {
                $createdIds = [];
                $totalReceived = 0;

                foreach ($packagingLines as $line) {
                    $qty = (int) $line['quantity'];
                    if ($qty <= 0) continue;

                    $productId = $line['product_id'] ? (int) $line['product_id'] : null;
                    $productName = $line['product_name'] ?: ($batch['recipe_product_name'] ?? 'Product');
                    $variant = $line['product_variant'] ?? null;
                    $sizeMl = $line['size_ml'] ? (float) $line['size_ml'] : null;

                    // Resolve product if missing
                    if (!$productId) {
                        $prodLookup = $db->prepare("
                            SELECT id FROM products
                            WHERE product_name = ? OR category = ?
                            LIMIT 1
                        ");
                        $prodLookup->execute([$productName, $batch['product_type'] ?? 'bottled_milk']);
                        $found = $prodLookup->fetchColumn();
                        $productId = $found ? (int) $found : null;
                    }

                    // Compute boxes/pieces from product pack config
                    $unitConfig = $productId ? getProductUnitConfig($db, $productId) : ['pieces_per_box' => 1];
                    $piecesPerBox = max(1, (int) ($unitConfig['pieces_per_box'] ?? 1));
                    $converted = piecesToBoxes($qty, $piecesPerBox);
                    $boxes = $converted['boxes'];
                    $pieces = $converted['pieces'];

                    // Product type mapping
                    $productTypeMap = [
                        'bottled_milk' => 'bottled_milk', 'pasteurized_milk' => 'bottled_milk',
                        'flavored_milk' => 'flavored_milk', 'yogurt' => 'yogurt',
                        'cheese' => 'cheese', 'butter' => 'butter', 'cream' => 'cream', 'milk_bar' => 'milk_bar',
                    ];
                    $fgProductType = $productTypeMap[$batch['product_type'] ?? ''] ?? 'bottled_milk';

                    // Create FG inventory row
                    $insStmt = $db->prepare("
                        INSERT INTO finished_goods_inventory
                        (product_id, batch_id, qc_release_id, milk_type_id, product_name, product_type,
                         product_variant, variant, size_ml,
                         quantity, quantity_available, remaining_quantity,
                         quantity_boxes, quantity_pieces, boxes_available, pieces_available,
                         unit, manufacturing_date, expiry_date, chiller_id, status,
                         received_by, received_at, last_movement_at, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pcs', ?, ?, ?, 'available', ?, NOW(), NOW(), ?)
                    ");
                    $insStmt->execute([
                        $productId,
                        $batchId,
                        $qcReleaseId,
                        $batch['milk_type_id'] ?? null,
                        $productName,
                        $fgProductType,
                        $variant,
                        $variant,
                        $sizeMl,
                        $qty, $qty, $qty,
                        $boxes, $pieces, $boxes, $pieces,
                        $batch['manufacturing_date'],
                        $batch['expiry_date'],
                        $chillerId,
                        $currentUser['user_id'],
                        trim(($notes ? $notes . ' · ' : '') . 'Batch ' . ($batch['batch_code'] ?? $batchId) . ' · ' . $productName . ($sizeMl ? " {$sizeMl}ml" : ''))
                    ]);

                    $fgId = (int) $db->lastInsertId();
                    $createdIds[] = $fgId;
                    $totalReceived += $qty;

                    // Link back to packaging_run_item
                    if ($line['item_id']) {
                        $db->prepare("UPDATE packaging_run_items SET fg_inventory_id = ? WHERE id = ?")
                           ->execute([$fgId, (int) $line['item_id']]);
                    }

                    // Log transaction per line
                    $logStmt = $db->prepare("
                        INSERT INTO fg_inventory_transactions
                        (transaction_code, transaction_type, inventory_id, product_id, quantity,
                         boxes_quantity, pieces_quantity, quantity_before, quantity_after,
                         boxes_before, pieces_before, boxes_after, pieces_after,
                         to_chiller_id, performed_by, reason)
                        VALUES (?, 'receive', ?, ?, ?, ?, ?, 0, ?, 0, 0, ?, ?, ?, ?, ?)
                    ");
                    $logStmt->execute([
                        'FGT-' . date('Ymd') . '-' . str_pad($fgId, 6, '0', STR_PAD_LEFT),
                        $fgId,
                        $productId,
                        $qty,
                        $boxes,
                        $pieces,
                        $qty,
                        $boxes,
                        $pieces,
                        $chillerId,
                        $currentUser['user_id'],
                        'Received from batch ' . ($batch['batch_code'] ?? $batchId) . ' · ' . $productName
                    ]);
                }

                // Mark batch as fully received
                $db->prepare("UPDATE production_batches SET fg_received = 1, updated_at = NOW() WHERE id = ?")
                   ->execute([$batchId]);

                // Update chiller count
                $db->prepare("UPDATE chiller_locations SET current_count = current_count + ? WHERE id = ?")
                   ->execute([$totalReceived, $chillerId]);

                $db->commit();

                Response::success([
                    'inventory_ids' => $createdIds,
                    'batch_code' => $batch['batch_code'],
                    'lines_received' => count($createdIds),
                    'total_pieces' => $totalReceived,
                    'chiller_id' => $chillerId,
                ], "Batch received: {$totalReceived} pieces across " . count($createdIds) . " product line(s) into chiller.", 201);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        case 'confirm_putaway':
            $queueId = $data['queue_id'] ?? $data['id'] ?? null;
            if (!$queueId) {
                Response::error('Queue ID required', 400);
            }

            if (!fgInventoryTableExists($db, 'put_away_queue')) {
                Response::error('Put-away queue not initialized', 500);
            }

            $db->beginTransaction();
            try {
                $qStmt = $db->prepare("SELECT * FROM put_away_queue WHERE id = ? AND status = 'pending' FOR UPDATE");
                $qStmt->execute([$queueId]);
                $queueItem = $qStmt->fetch(PDO::FETCH_ASSOC);

                if (!$queueItem) {
                    $db->rollBack();
                    Response::error('Queue item not found or already processed', 404);
                }

                $productId = (int)$queueItem['product_id'];
                $batchId = !empty($queueItem['batch_id']) ? (int)$queueItem['batch_id'] : null;
                $inventoryId = !empty($queueItem['inventory_id']) ? (int)$queueItem['inventory_id'] : null;
                $qty = (int)$queueItem['quantity'];

                // Find the existing FG inventory row for this batch/product
                $fgId = null;
                if ($inventoryId) {
                    $s = $db->prepare("SELECT id FROM finished_goods_inventory WHERE id = ? LIMIT 1");
                    $s->execute([$inventoryId]);
                    $fgId = $s->fetchColumn() ?: null;
                }
                if (!$fgId && $batchId) {
                    $s = $db->prepare("SELECT id FROM finished_goods_inventory WHERE product_id = ? AND batch_id = ? ORDER BY id DESC LIMIT 1");
                    $s->execute([$productId, $batchId]);
                    $fgId = $s->fetchColumn() ?: null;
                }
                if (!$fgId) {
                    $s = $db->prepare("SELECT id FROM finished_goods_inventory WHERE product_id = ? ORDER BY id DESC LIMIT 1");
                    $s->execute([$productId]);
                    $fgId = $s->fetchColumn() ?: null;
                }

                if (!$fgId) {
                    $db->rollBack();
                    Response::error("No FG inventory row found for product #{$productId} — cannot restock", 404);
                }

                // Restock the existing FG row — this is the ONLY place on-hand stock changes
                $restock = fgInventoryRestockBaseUnits($db, $fgId, $qty);

                // Log transaction
                try {
                    $txnCode = 'PAW-' . date('Ymd') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                    $txn = $db->prepare("
                        INSERT INTO fg_inventory_transactions
                        (transaction_code, inventory_id, product_id, transaction_type, quantity,
                         reference_type, reference_id, performed_by, reason)
                        VALUES (?, ?, ?, 'receive', ?, 'put_away_queue', ?, ?, ?)
                    ");
                    $txn->execute([
                        $txnCode,
                        $fgId,
                        $productId,
                        $qty,
                        $queueId,
                        $currentUser['user_id'],
                        'Put-away confirmed from delivery return queue'
                    ]);
                } catch (Exception $logEx) {
                    error_log('Put-away txn log skipped: ' . $logEx->getMessage());
                }

                // Mark queue item as confirmed
                $db->prepare("
                    UPDATE put_away_queue
                    SET status = 'confirmed', confirmed_at = NOW(), confirmed_by = ?
                    WHERE id = ?
                ")->execute([$currentUser['user_id'], $queueId]);

                $db->commit();

                Response::success([
                    'queue_id' => (int)$queueId,
                    'inventory_id' => $fgId,
                    'product_id' => $productId,
                    'quantity_added' => $qty,
                    'restock' => $restock
                ], "Put-away confirmed: {$qty} units added to on-hand stock");

            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
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
            LEFT JOIN products p ON fg.product_id = p.id
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
                if (($current['status'] ?? '') !== 'available') {
                    Response::error('Only available inventory can be transferred', 400);
                }

                if (!empty($current['expiry_date']) && $current['expiry_date'] <= date('Y-m-d')) {
                    Response::error('Expired inventory should be disposed or quarantined, not transferred as usable stock', 400);
                }

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
                        pieces_available = 0,
                        disposed_at = NOW(),
                        disposal_reason = ?
                    WHERE id = ?
                ");
                $stmt->execute([$data['reason'] ?? 'Disposed', $id]);
                
                // Update chiller count
                if ($current['chiller_id'] && $oldTotal > 0) {
                    $db->prepare("UPDATE chiller_locations SET current_count = GREATEST(0, current_count - ?) WHERE id = ?")
                       ->execute([$oldTotal, $current['chiller_id']]);
                }
                
                // Log transaction (product_id may be NULL for corrupted items)
                $productId = $current['product_id'] ?? 0;
                $logStmt = $db->prepare("
                    INSERT INTO fg_inventory_transactions
                    (transaction_code, transaction_type, inventory_id, product_id, quantity,
                     boxes_quantity, pieces_quantity, quantity_before, quantity_after,
                     boxes_before, pieces_before, boxes_after, pieces_after,
                     performed_by, reason)
                    VALUES (?, 'disposal', ?, ?, ?, ?, ?, ?, 0, ?, ?, 0, 0, ?, ?)
                ");
                
                $logStmt->execute([
                    'FGT-' . date('Ymd') . '-' . uniqid(),
                    $id,
                    $productId,
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

        case 'force_delete':
            // Force-delete a corrupted inventory record.
            // Removes all FK-linked children first, then the inventory row itself.
            // Requires GM role to prevent accidental data loss.
            if (($currentUser['role'] ?? '') !== 'general_manager') {
                Response::error('Only GM can force-delete inventory records', 403);
            }

            $db->beginTransaction();
            try {
                // Log what we're about to delete
                error_log("FG Inventory force_delete: id={$id}, product={$current['product_name']}, batch_id={$current['batch_id']}");

                // 1. Delete dispatch log references
                $db->prepare("DELETE FROM fg_dispatch_log WHERE inventory_id = ?")->execute([$id]);

                // 2. Delete disposal item references
                $db->prepare("DELETE FROM disposal_items WHERE source_id = ? AND source_type = 'finished_goods'")->execute([$id]);

                // 3. Delete inventory transaction log
                $db->prepare("DELETE FROM fg_inventory_transactions WHERE inventory_id = ?")->execute([$id]);

                // 4. Update chiller count if assigned
                if ($current['chiller_id'] && ($current['boxes_available'] > 0 || $current['pieces_available'] > 0)) {
                    $totalPieces = boxesToPieces($current['boxes_available'] ?? 0, $current['pieces_available'] ?? 0, $current['pieces_per_box'] ?? 1);
                    $db->prepare("UPDATE chiller_locations SET current_count = GREATEST(0, current_count - ?) WHERE id = ?")
                       ->execute([$totalPieces, $current['chiller_id']]);
                }

                // 5. Delete the inventory row
                $db->prepare("DELETE FROM finished_goods_inventory WHERE id = ?")->execute([$id]);

                // 6. Clean up orphaned batch if it has no other FG references
                if ($current['batch_id']) {
                    $checkBatch = $db->prepare("SELECT COUNT(*) FROM finished_goods_inventory WHERE batch_id = ?");
                    $checkBatch->execute([$current['batch_id']]);
                    if ($checkBatch->fetchColumn() == 0) {
                        $db->prepare("DELETE FROM production_batches WHERE id = ?")->execute([$current['batch_id']]);
                    }
                }

                $db->commit();
                Response::success(null, 'Corrupted inventory record force-deleted successfully');

            } catch (Exception $e) {
                $db->rollBack();
                Response::error('Force delete failed: ' . $e->getMessage(), 500);
            }
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
