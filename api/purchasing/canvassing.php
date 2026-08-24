<?php
/**
 * Highland Fresh System - Price Canvassing API
 * 
 * GET - List canvass requests, get quotes
 * POST - Prepare supplier reviews and record approved-supplier selections
 * PUT - Select quote, complete canvass
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/helpers/supplier_ingredient_catalog.php';

// Require Purchaser or GM role
$currentUser = Auth::requireRole(['purchaser', 'general_manager']);

$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    ensureCanvassingTables($db);
    ensureSupplierIngredientCatalog($db);
    
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
    error_log("Canvassing API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            $status = getParam('status');
            $search = getParam('search');
            $page = max(1, (int) getParam('page', 1));
            $limit = min(50, max(10, (int) getParam('limit', 20)));
            $offset = ($page - 1) * $limit;
            
            $where = "1=1";
            $params = [];
            
            if ($status) {
                $where .= " AND c.status = ?";
                $params[] = $status;
            }
            
            if ($search) {
                $where .= " AND (c.canvass_code LIKE ? OR c.item_description LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Get total count
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM price_canvass c WHERE $where");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetch()['total'];
            
            // Get paginated results
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $db->prepare("
                SELECT 
                    c.*,
                    i.ingredient_name,
                    m.item_name as mro_item_name,
                    u.full_name as created_by_name,
                    (
                        SELECT COUNT(DISTINCT q.supplier_id)
                        FROM canvass_quotes q
                        JOIN suppliers qs ON qs.id = q.supplier_id AND qs.is_active = 1
                        LEFT JOIN ingredients qi ON qi.id = c.ingredient_id AND qi.is_active = 1
                        LEFT JOIN supplier_ingredients qsi
                          ON qsi.supplier_id = q.supplier_id
                         AND qsi.ingredient_id = c.ingredient_id
                         AND qsi.is_active = 1
                        WHERE q.canvass_id = c.id
                          AND (c.ingredient_id IS NULL OR (qi.id IS NOT NULL AND qsi.id IS NOT NULL))
                    ) as quote_count,
                    (
                        SELECT MIN(q.unit_price)
                        FROM canvass_quotes q
                        JOIN suppliers qs ON qs.id = q.supplier_id AND qs.is_active = 1
                        LEFT JOIN ingredients qi ON qi.id = c.ingredient_id AND qi.is_active = 1
                        LEFT JOIN supplier_ingredients qsi
                          ON qsi.supplier_id = q.supplier_id
                         AND qsi.ingredient_id = c.ingredient_id
                         AND qsi.is_active = 1
                        WHERE q.canvass_id = c.id
                          AND (c.ingredient_id IS NULL OR (qi.id IS NOT NULL AND qsi.id IS NOT NULL))
                    ) as lowest_price,
                    (
                        SELECT MAX(q.unit_price)
                        FROM canvass_quotes q
                        JOIN suppliers qs ON qs.id = q.supplier_id AND qs.is_active = 1
                        LEFT JOIN ingredients qi ON qi.id = c.ingredient_id AND qi.is_active = 1
                        LEFT JOIN supplier_ingredients qsi
                          ON qsi.supplier_id = q.supplier_id
                         AND qsi.ingredient_id = c.ingredient_id
                         AND qsi.is_active = 1
                        WHERE q.canvass_id = c.id
                          AND (c.ingredient_id IS NULL OR (qi.id IS NOT NULL AND qsi.id IS NOT NULL))
                    ) as highest_price
                FROM price_canvass c
                LEFT JOIN ingredients i ON c.ingredient_id = i.id
                LEFT JOIN mro_items m ON c.mro_item_id = m.id
                LEFT JOIN users u ON c.created_by = u.id
                WHERE $where
                ORDER BY c.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute($params);
            $canvasses = $stmt->fetchAll();
            
            Response::paginated($canvasses, $total, $page, $limit, 'Canvass requests retrieved');
            break;
            
        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('Canvass ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT 
                    c.*,
                    i.ingredient_name,
                    i.unit_cost as current_ingredient_price,
                    m.item_name as mro_item_name,
                    m.unit_cost as current_mro_price,
                    u.full_name as created_by_name
                FROM price_canvass c
                LEFT JOIN ingredients i ON c.ingredient_id = i.id
                LEFT JOIN mro_items m ON c.mro_item_id = m.id
                LEFT JOIN users u ON c.created_by = u.id
                WHERE c.id = ?
            ");
            $stmt->execute([$id]);
            $canvass = $stmt->fetch();
            
            if (!$canvass) {
                Response::error('Canvass not found', 404);
            }
            
            // Get quotes
            $quotesStmt = $db->prepare("
                SELECT 
                    q.*,
                    s.supplier_name,
                    s.supplier_code,
                    s.contact_person,
                    s.phone
                FROM canvass_quotes q
                JOIN price_canvass quote_canvass ON quote_canvass.id = q.canvass_id
                JOIN suppliers s ON q.supplier_id = s.id AND s.is_active = 1
                LEFT JOIN ingredients active_ingredient
                    ON active_ingredient.id = quote_canvass.ingredient_id
                   AND active_ingredient.is_active = 1
                LEFT JOIN supplier_ingredients active_link
                    ON active_link.supplier_id = q.supplier_id
                   AND active_link.ingredient_id = quote_canvass.ingredient_id
                   AND active_link.is_active = 1
                WHERE q.canvass_id = ?
                  AND (
                      quote_canvass.ingredient_id IS NULL
                      OR (active_ingredient.id IS NOT NULL AND active_link.id IS NOT NULL)
                  )
                ORDER BY q.unit_price ASC
            ");
            $quotesStmt->execute([$id]);
            $canvass['quotes'] = $quotesStmt->fetchAll();
            
            Response::success($canvass, 'Canvass details retrieved');
            break;

        case 'prs_workbench':
            $prId = getParam('pr_id') ?? getParam('id');
            if (!$prId) {
                Response::error('PRS ID required', 400);
            }

            $prs = getSubmittedPRSForCanvassing($db, (int) $prId);
            Response::success($prs, 'PRS canvassing details retrieved');
            break;
            
        case 'price_history':
            $type = getParam('type', 'ingredient');
            $item_id = getParam('item_id');
            $limit = min(50, max(10, (int) getParam('limit', 20)));
            
            if (!$item_id) {
                Response::error('Item ID required', 400);
            }
            
            if ($type === 'ingredient') {
                $stmt = $db->prepare("
                    SELECT 
                        ph.*,
                        i.ingredient_name as item_name,
                        s.supplier_name,
                        u.full_name as updated_by_name,
                        po.po_number
                    FROM ingredient_price_history ph
                    JOIN ingredients i ON ph.ingredient_id = i.id
                    LEFT JOIN suppliers s ON ph.supplier_id = s.id
                    LEFT JOIN users u ON ph.updated_by = u.id
                    LEFT JOIN purchase_orders po ON ph.po_id = po.id
                    WHERE ph.ingredient_id = ?
                    ORDER BY ph.created_at DESC
                    LIMIT ?
                ");
            } else {
                $stmt = $db->prepare("
                    SELECT 
                        ph.*,
                        m.item_name,
                        s.supplier_name,
                        u.full_name as updated_by_name,
                        po.po_number
                    FROM mro_price_history ph
                    JOIN mro_items m ON ph.mro_item_id = m.id
                    LEFT JOIN suppliers s ON ph.supplier_id = s.id
                    LEFT JOIN users u ON ph.updated_by = u.id
                    LEFT JOIN purchase_orders po ON ph.po_id = po.id
                    WHERE ph.mro_item_id = ?
                    ORDER BY ph.created_at DESC
                    LIMIT ?
                ");
            }
            $stmt->execute([$item_id, $limit]);
            $history = $stmt->fetchAll();
            
            Response::success($history, 'Price history retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

function handlePost($db, $action, $currentUser) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'create':
            $required = ['item_description', 'quantity', 'unit'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::error("$field is required", 400);
                }
            }
            try {
                $data['quantity'] = hfParseBusinessDecimal(
                    $data['quantity'],
                    'Canvass quantity',
                    0.01,
                    1000000.00,
                    2
                );
            } catch (InvalidArgumentException $error) {
                Response::validationError(['quantity' => $error->getMessage()]);
            }
            
            $canvassCode = nextCanvassCode($db);
            
            $stmt = $db->prepare("
                INSERT INTO price_canvass 
                (canvass_code, item_type, ingredient_id, mro_item_id, item_description, quantity, unit, status, created_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?, ?)
            ");
            
            $stmt->execute([
                $canvassCode,
                $data['item_type'] ?? 'ingredient',
                $data['ingredient_id'] ?? null,
                $data['mro_item_id'] ?? null,
                $data['item_description'],
                $data['quantity'],
                $data['unit'],
                $currentUser['user_id'],
                $data['notes'] ?? null
            ]);
            
            $id = $db->lastInsertId();
            
            Response::success([
                'id' => $id,
                'canvass_code' => $canvassCode
            ], 'Canvass request created', 201);
            break;
            
        case 'add_quote':
            $required = ['canvass_id', 'supplier_id', 'unit_price'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::error("$field is required", 400);
                }
            }
            
            // Check canvass exists and is open
            $check = $db->prepare("SELECT id, status, ingredient_id, mro_item_id FROM price_canvass WHERE id = ?");
            $check->execute([$data['canvass_id']]);
            $canvass = $check->fetch();
            
            if (!$canvass) {
                Response::error('Canvass not found', 404);
            }
            if (!in_array($canvass['status'], ['open', 'completed'], true)) {
                Response::error('Canvass is no longer open for quotes', 400);
            }

            $supplierId = (int) $data['supplier_id'];
            $supplierCheck = $db->prepare("SELECT id FROM suppliers WHERE id = ? AND is_active = 1");
            $supplierCheck->execute([$supplierId]);
            if (!$supplierCheck->fetch()) {
                Response::error('Choose an accredited supplier', 400);
            }

            if (!empty($canvass['ingredient_id'])) {
                $catalogCheck = $db->prepare("
                    SELECT si.id
                    FROM supplier_ingredients si
                    JOIN ingredients i ON i.id = si.ingredient_id AND i.is_active = 1
                    WHERE si.supplier_id = ?
                      AND si.ingredient_id = ?
                      AND si.is_active = 1
                    LIMIT 1
                ");
                $catalogCheck->execute([$supplierId, $canvass['ingredient_id']]);
                if (!$catalogCheck->fetch()) {
                    Response::error('This supplier is not accredited to supply this ingredient', 400);
                }
            }

            try {
                $unitPrice = hfParseBusinessDecimal(
                    $data['unit_price'],
                    'Quoted unit price',
                    0.01,
                    999999.99,
                    2
                );
                $deliveryDays = hfParseBusinessInteger(
                    $data['delivery_days'] ?? 7,
                    'Delivery days',
                    0,
                    3650
                );
            } catch (InvalidArgumentException $error) {
                Response::validationError(['quote' => $error->getMessage()]);
            }
            
            // Check if supplier already quoted
            $dupCheck = $db->prepare("SELECT id FROM canvass_quotes WHERE canvass_id = ? AND supplier_id = ?");
            $dupCheck->execute([$data['canvass_id'], $supplierId]);
            if ($dupCheck->fetch()) {
                Response::error('This supplier has already submitted a quote', 400);
            }
            
            $stmt = $db->prepare("
                INSERT INTO canvass_quotes 
                (canvass_id, supplier_id, unit_price, delivery_days, payment_terms, validity_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['canvass_id'],
                $supplierId,
                $unitPrice,
                $deliveryDays,
                $data['payment_terms'] ?? 'cash',
                $data['validity_date'] ?? null,
                $data['notes'] ?? null
            ]);

            $quoteId = (int) $db->lastInsertId();
            $autoSelection = autoSelectCheapestQuoteIfReady($db, (int) $data['canvass_id']);
            $wasAutoSelected = $autoSelection && ($autoSelection['selection_method'] ?? null) === 'auto_cheapest';

            Response::success([
                'id' => $quoteId,
                'auto_selected' => $wasAutoSelected,
                'selected_quote_id' => $autoSelection['quote_id'] ?? null,
                'selected_supplier_id' => $autoSelection['supplier_id'] ?? null,
                'selected_unit_price' => $autoSelection['unit_price'] ?? null
            ], $wasAutoSelected ? 'Quote added. Cheapest quote was selected automatically.' : 'Quote added', 201);
            break;

        case 'ensure_prs_canvass':
            $prId = (int) ($data['purchase_request_id'] ?? $data['pr_id'] ?? 0);
            if ($prId <= 0) {
                Response::error('PRS ID required', 400);
            }

            $prs = getSubmittedPRSForCanvassing($db, $prId);
            $created = 0;

            foreach ($prs['items'] as $item) {
                $canvassId = (int) ($item['canvass']['id'] ?? 0);
                if ($canvassId <= 0) {
                    $canvassId = createCanvassForPRSItem($db, $prs, $item, $currentUser);
                    $created++;
                }
                syncRegisteredPartnerPricesForCanvass($db, $canvassId);
            }

            $fresh = getSubmittedPRSForCanvassing($db, $prId);
            Response::success([
                'created' => $created,
                'prs' => $fresh
            ], $created > 0 ? 'Supplier review prepared' : 'Supplier review already prepared', 201);
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

function ensureCanvassingTables(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `price_canvass` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `canvass_code` VARCHAR(30) NOT NULL,
            `item_type` ENUM('ingredient','mro','other') DEFAULT 'ingredient',
            `ingredient_id` INT(11) DEFAULT NULL,
            `mro_item_id` INT(11) DEFAULT NULL,
            `purchase_request_id` INT(11) DEFAULT NULL,
            `purchase_request_item_id` INT(11) DEFAULT NULL,
            `item_description` VARCHAR(255) NOT NULL,
            `quantity` DECIMAL(12,2) NOT NULL,
            `unit` VARCHAR(30) NOT NULL DEFAULT 'units',
            `status` ENUM('open','completed','cancelled') DEFAULT 'open',
            `selected_quote_id` INT(11) DEFAULT NULL,
            `created_by` INT(11) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_canvass_code` (`canvass_code`),
            KEY `idx_canvass_pr` (`purchase_request_id`),
            KEY `idx_canvass_pr_item` (`purchase_request_item_id`),
            KEY `idx_canvass_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS `canvass_quotes` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `canvass_id` INT(11) NOT NULL,
            `supplier_id` INT(11) NOT NULL,
            `unit_price` DECIMAL(12,2) NOT NULL,
            `delivery_days` INT(11) DEFAULT 7,
            `payment_terms` VARCHAR(30) DEFAULT 'cash',
            `validity_date` DATE DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `is_selected` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_canvass_supplier` (`canvass_id`, `supplier_id`),
            KEY `idx_quote_canvass` (`canvass_id`),
            KEY `idx_quote_supplier` (`supplier_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    if (!auditColumnExists($db, 'price_canvass', 'purchase_request_id')) {
        $db->exec("ALTER TABLE `price_canvass` ADD COLUMN `purchase_request_id` INT(11) DEFAULT NULL AFTER `mro_item_id`");
    }
    if (!auditColumnExists($db, 'price_canvass', 'purchase_request_item_id')) {
        $db->exec("ALTER TABLE `price_canvass` ADD COLUMN `purchase_request_item_id` INT(11) DEFAULT NULL AFTER `purchase_request_id`");
    }
    if (!auditColumnExists($db, 'price_canvass', 'selected_quote_id')) {
        $db->exec("ALTER TABLE `price_canvass` ADD COLUMN `selected_quote_id` INT(11) DEFAULT NULL AFTER `status`");
    }
    if (!auditColumnExists($db, 'price_canvass', 'selection_method')) {
        $db->exec("ALTER TABLE `price_canvass` ADD COLUMN `selection_method` VARCHAR(30) DEFAULT NULL AFTER `selected_quote_id`");
    }
    if (!auditColumnExists($db, 'price_canvass', 'selection_reason')) {
        $db->exec("ALTER TABLE `price_canvass` ADD COLUMN `selection_reason` TEXT DEFAULT NULL AFTER `selection_method`");
    }

    if (!canvassIndexExists($db, 'canvass_quotes', 'uk_canvass_supplier')) {
        $duplicate = $db->query("
            SELECT 1
            FROM canvass_quotes
            GROUP BY canvass_id, supplier_id
            HAVING COUNT(*) > 1
            LIMIT 1
        ")->fetchColumn();

        if (!$duplicate) {
            $db->exec("ALTER TABLE `canvass_quotes` ADD UNIQUE KEY `uk_canvass_supplier` (`canvass_id`, `supplier_id`)");
        } else {
            error_log('Cannot add unique canvass supplier rule until duplicate quote records are resolved.');
        }
    }
}

function canvassIndexExists(PDO $db, string $tableName, string $indexName): bool {
    $stmt = $db->prepare("
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$tableName, $indexName]);
    return (bool) $stmt->fetchColumn();
}

function nextCanvassCode(PDO $db): string {
    $stmt = $db->query("SELECT canvass_code FROM price_canvass ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    $year = date('Y');
    $seq = 1;
    if ($last && preg_match('/CNV-(\d{4})-(\d+)/', $last['canvass_code'], $matches)) {
        $seq = ($matches[1] === $year) ? ((int) $matches[2] + 1) : 1;
    }
    return sprintf('CNV-%s-%04d', $year, $seq);
}

function getSubmittedPRSForCanvassing(PDO $db, int $prId): array {
    $stmt = $db->prepare("
        SELECT pr.*, u.full_name as requested_by_name
        FROM purchase_requests pr
        LEFT JOIN users u ON pr.requested_by = u.id
        WHERE pr.id = ?
          AND pr.status IN ('pending', 'approved')
    ");
    $stmt->execute([$prId]);
    $prs = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prs) {
        Response::error('Submitted PRS not found or already converted to PO', 404);
    }

    $itemsStmt = $db->prepare("
        SELECT
            pri.*,
            i.ingredient_name,
            i.ingredient_code,
            m.item_name as mro_item_name,
            m.item_code as mro_item_code
        FROM purchase_request_items pri
        LEFT JOIN ingredients i ON pri.ingredient_id = i.id
        LEFT JOIN mro_items m ON pri.mro_item_id = m.id
        WHERE pri.purchase_request_id = ?
        ORDER BY pri.id ASC
    ");
    $itemsStmt->execute([$prId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['eligible_suppliers'] = !empty($item['ingredient_id'])
            ? supplierCatalogGetIngredientSuppliers($db, (int) $item['ingredient_id'])
            : [];

        $canvassStmt = $db->prepare("
            SELECT *
            FROM price_canvass
            WHERE purchase_request_item_id = ?
              AND status != 'cancelled'
            ORDER BY id DESC
            LIMIT 1
        ");
        $canvassStmt->execute([$item['id']]);
        $canvass = $canvassStmt->fetch(PDO::FETCH_ASSOC);

        if ($canvass) {
            syncRegisteredPartnerPricesForCanvass($db, (int) $canvass['id']);
            autoSelectCheapestQuoteIfReady($db, (int) $canvass['id']);

            // Return the selection made above in this same response.
            $canvassStmt->execute([$item['id']]);
            $canvass = $canvassStmt->fetch(PDO::FETCH_ASSOC);

            $quotesStmt = $db->prepare("
                SELECT
                    q.*,
                    s.supplier_name,
                    s.supplier_code,
                    s.contact_person,
                    s.phone
                FROM canvass_quotes q
                JOIN price_canvass quote_canvass ON quote_canvass.id = q.canvass_id
                JOIN suppliers s ON q.supplier_id = s.id AND s.is_active = 1
                LEFT JOIN ingredients active_ingredient
                    ON active_ingredient.id = quote_canvass.ingredient_id
                   AND active_ingredient.is_active = 1
                LEFT JOIN supplier_ingredients active_link
                    ON active_link.supplier_id = q.supplier_id
                   AND active_link.ingredient_id = quote_canvass.ingredient_id
                   AND active_link.is_active = 1
                WHERE q.canvass_id = ?
                  AND (
                      quote_canvass.ingredient_id IS NULL
                      OR (active_ingredient.id IS NOT NULL AND active_link.id IS NOT NULL)
                  )
                ORDER BY q.is_selected DESC, q.unit_price ASC
            ");
            $quotesStmt->execute([$canvass['id']]);
            $canvass['quotes'] = $quotesStmt->fetchAll(PDO::FETCH_ASSOC);
            $canvass['quote_count'] = count($canvass['quotes']);
        }

        $item['canvass'] = $canvass ?: null;
    }
    unset($item);

    $prs['items'] = $items;
    return $prs;
}

function autoSelectCheapestQuoteIfReady(PDO $db, int $canvassId): ?array {
    $stmt = $db->prepare("
        SELECT
            q.id as quote_id,
            q.supplier_id,
            q.unit_price,
            q.delivery_days,
            q.is_selected,
            c.status as canvass_status,
            c.selected_quote_id,
            c.selection_method
        FROM canvass_quotes q
        JOIN price_canvass c ON c.id = q.canvass_id
        JOIN suppliers s ON s.id = q.supplier_id AND s.is_active = 1
        LEFT JOIN ingredients active_ingredient
            ON active_ingredient.id = c.ingredient_id
           AND active_ingredient.is_active = 1
        LEFT JOIN supplier_ingredients active_link
            ON active_link.supplier_id = q.supplier_id
           AND active_link.ingredient_id = c.ingredient_id
           AND active_link.is_active = 1
        WHERE q.canvass_id = ?
          AND (
              c.ingredient_id IS NULL
              OR (active_ingredient.id IS NOT NULL AND active_link.id IS NOT NULL)
          )
        ORDER BY q.unit_price ASC, q.delivery_days ASC, q.id ASC
    ");
    $stmt->execute([$canvassId]);
    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $canvassStmt = $db->prepare("SELECT id, ingredient_id, mro_item_id FROM price_canvass WHERE id = ?");
    $canvassStmt->execute([$canvassId]);
    $canvass = $canvassStmt->fetch(PDO::FETCH_ASSOC);
    if (!$canvass || !$quotes) {
        return null;
    }

    $quoteCount = count(array_unique(array_column($quotes, 'supplier_id')));
    $eligibleCount = getCanvassEligibleSupplierCount($db, $canvass);
    if (!empty($canvass['ingredient_id']) && ($eligibleCount < 1 || $quoteCount < $eligibleCount)) {
        return null;
    }
    if (empty($canvass['ingredient_id']) && $quoteCount < 3) {
        return null;
    }

    $selected = null;
    foreach ($quotes as $quote) {
        if ((int) ($quote['is_selected'] ?? 0) === 1) {
            $selected = $quote;
            break;
        }
    }

    if ($selected && (($selected['selection_method'] ?? null) === 'manual_override'
        || (($selected['selection_method'] ?? null) === null && ($selected['canvass_status'] ?? null) === 'completed'))) {
        return $selected;
    }

    $best = $quotes[0];
    $selectionMethod = $eligibleCount < 3 ? 'limited_market' : 'registered_price';
    $selectionReason = $eligibleCount < 3
        ? sprintf(
            'Registered partner comparison: %d accredited supplier(s) provide this item. All available saved prices were compared automatically.',
            $eligibleCount
        )
        : sprintf(
            'Registered partner comparison: all %d accredited supplier prices were compared. Lowest unit price recommended; ties use faster delivery.',
            $eligibleCount
        );

    if ((int) ($best['is_selected'] ?? 0) === 1
        && (int) ($best['selected_quote_id'] ?? 0) === (int) $best['quote_id']
        && ($best['selection_method'] ?? null) === $selectionMethod) {
        return $best;
    }

    return selectCanvassQuote($db, $canvassId, (int) $best['quote_id'], $selectionMethod, $selectionReason);
}

function normalizePartnerPaymentTerms(?string $terms): string {
    $value = strtolower(trim((string) $terms));
    if ($value === 'cod' || $value === 'cash') {
        return 'cash';
    }
    if (preg_match('/(7|15|30|45|60)/', $value, $matches)) {
        return 'credit_' . $matches[1];
    }
    return 'credit_30';
}

function resolveRegisteredPartnerPrice(PDO $db, int $ingredientId, int $supplierId, $savedPrice): ?array {
    if ($savedPrice !== null && (float) $savedPrice > 0) {
        return ['unit_price' => (float) $savedPrice, 'source' => 'supplier agreement'];
    }

    $quoteStmt = $db->prepare("
        SELECT q.unit_price, q.delivery_days, q.payment_terms
        FROM canvass_quotes q
        JOIN price_canvass c ON c.id = q.canvass_id
        WHERE c.ingredient_id = ? AND q.supplier_id = ? AND q.unit_price > 0
        ORDER BY q.id DESC
        LIMIT 1
    ");
    $quoteStmt->execute([$ingredientId, $supplierId]);
    $price = $quoteStmt->fetch(PDO::FETCH_ASSOC);

    if (!$price) {
        $poStmt = $db->prepare("
            SELECT poi.unit_price,
                   GREATEST(0, DATEDIFF(po.expected_delivery, po.order_date)) AS delivery_days,
                   po.payment_terms
            FROM purchase_order_items poi
            JOIN purchase_orders po ON po.id = poi.po_id
            WHERE poi.ingredient_id = ? AND po.supplier_id = ? AND poi.unit_price > 0
            ORDER BY poi.id DESC
            LIMIT 1
        ");
        $poStmt->execute([$ingredientId, $supplierId]);
        $price = $poStmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$price) {
        return null;
    }

    $db->prepare("
        UPDATE supplier_ingredients
        SET reference_unit_price = ?
        WHERE ingredient_id = ? AND supplier_id = ? AND is_active = 1
    ")->execute([(float) $price['unit_price'], $ingredientId, $supplierId]);

    return [
        'unit_price' => (float) $price['unit_price'],
        'delivery_days' => (int) ($price['delivery_days'] ?? 7),
        'payment_terms' => $price['payment_terms'] ?? null,
        'source' => 'latest approved purchasing record'
    ];
}

function syncRegisteredPartnerPricesForCanvass(PDO $db, int $canvassId): array {
    $stmt = $db->prepare("
        SELECT c.ingredient_id, COALESCE(i.lead_time_days, 7) AS lead_time_days,
               i.unit_of_measure AS stock_unit
        FROM price_canvass c
        LEFT JOIN ingredients i ON i.id = c.ingredient_id
        WHERE c.id = ?
    ");
    $stmt->execute([$canvassId]);
    $canvass = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$canvass || empty($canvass['ingredient_id'])) {
        return ['eligible' => 0, 'priced' => 0];
    }

    $suppliers = supplierCatalogGetIngredientSuppliers($db, (int) $canvass['ingredient_id']);
    $priced = 0;
    foreach ($suppliers as $supplier) {
        $price = resolveRegisteredPartnerPrice(
            $db,
            (int) $canvass['ingredient_id'],
            (int) $supplier['supplier_id'],
            $supplier['reference_unit_price'] ?? null
        );
        if (!$price) {
            continue;
        }

        $deliveryDays = max(0, (int) ($price['delivery_days'] ?? $canvass['lead_time_days'] ?? 7));
        $paymentTerms = normalizePartnerPaymentTerms($price['payment_terms'] ?? $supplier['payment_terms'] ?? null);
        $stockUnit = $canvass['stock_unit'] ?: 'stock unit';
        if (($supplier['purchase_format'] ?? 'direct_unit') === 'packaged') {
            $offerLabel = $supplier['offer_label'] ?: 'supplier package';
            $quotedPrice = (float) ($supplier['quoted_price'] ?? 0);
            $notes = sprintf(
                'Supplier offer: %s at PHP %s; normalized to PHP %s per %s for comparison.',
                $offerLabel,
                number_format($quotedPrice, 2),
                number_format((float) $price['unit_price'], 2),
                $stockUnit
            );
        } else {
            $notes = sprintf(
                'Supplier offer: direct/bulk at PHP %s per %s.',
                number_format((float) $price['unit_price'], 2),
                $stockUnit
            );
        }
        $quoteStmt = $db->prepare("
            INSERT INTO canvass_quotes
                (canvass_id, supplier_id, unit_price, delivery_days, payment_terms, notes)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                unit_price = VALUES(unit_price),
                delivery_days = VALUES(delivery_days),
                payment_terms = VALUES(payment_terms),
                notes = VALUES(notes)
        ");
        $quoteStmt->execute([
            $canvassId,
            (int) $supplier['supplier_id'],
            (float) $price['unit_price'],
            $deliveryDays,
            $paymentTerms,
            $notes
        ]);
        $priced++;
    }

    return ['eligible' => count($suppliers), 'priced' => $priced];
}

function getCanvassEligibleSupplierCount(PDO $db, array $canvass): int {
    if (!empty($canvass['ingredient_id'])) {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT si.supplier_id)
            FROM supplier_ingredients si
            JOIN ingredients i ON i.id = si.ingredient_id AND i.is_active = 1
            JOIN suppliers s ON s.id = si.supplier_id AND s.is_active = 1
            WHERE si.ingredient_id = ? AND si.is_active = 1
        ");
        $stmt->execute([(int) $canvass['ingredient_id']]);
        return (int) $stmt->fetchColumn();
    }

    return (int) $db->query("SELECT COUNT(*) FROM suppliers WHERE is_active = 1")->fetchColumn();
}

function selectLimitedMarketQuote(PDO $db, int $canvassId, string $reason): array {
    // Kept for older clients. Limited markets are now detected from supplier accreditation,
    // so the Purchaser no longer has to explain why only one or two partners are registered.
    syncRegisteredPartnerPricesForCanvass($db, $canvassId);
    $selected = autoSelectCheapestQuoteIfReady($db, $canvassId);
    if (!$selected) {
        Response::error('Complete the supplier accreditation and agreed prices for this item first', 400);
    }
    return $selected;
}

function selectCanvassQuote(PDO $db, int $canvassId, int $quoteId, string $method, ?string $reason = null): array {
    $quoteStmt = $db->prepare("
        SELECT q.id as quote_id, q.supplier_id, q.unit_price, q.delivery_days
        FROM canvass_quotes q
        JOIN price_canvass c ON c.id = q.canvass_id
        JOIN suppliers s ON s.id = q.supplier_id AND s.is_active = 1
        LEFT JOIN ingredients active_ingredient
            ON active_ingredient.id = c.ingredient_id
           AND active_ingredient.is_active = 1
        LEFT JOIN supplier_ingredients active_link
            ON active_link.supplier_id = q.supplier_id
           AND active_link.ingredient_id = c.ingredient_id
           AND active_link.is_active = 1
        WHERE q.id = ?
          AND q.canvass_id = ?
          AND (
              c.ingredient_id IS NULL
              OR (active_ingredient.id IS NOT NULL AND active_link.id IS NOT NULL)
          )
        LIMIT 1
    ");
    $quoteStmt->execute([$quoteId, $canvassId]);
    $quote = $quoteStmt->fetch(PDO::FETCH_ASSOC);
    if (!$quote) {
        Response::error('Quote not found for this canvass', 404);
    }

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE canvass_quotes SET is_selected = 0 WHERE canvass_id = ?")
           ->execute([$canvassId]);

        $db->prepare("UPDATE canvass_quotes SET is_selected = 1 WHERE id = ?")
           ->execute([$quoteId]);

        $db->prepare("
            UPDATE price_canvass
            SET selected_quote_id = ?,
                selection_method = ?,
                selection_reason = ?,
                status = 'completed'
            WHERE id = ?
        ")->execute([$quoteId, $method, $reason, $canvassId]);

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

    $quote['selection_method'] = $method;
    $quote['selection_reason'] = $reason;
    return $quote;
}

function createCanvassForPRSItem(PDO $db, array $prs, array $item, array $currentUser): int {
    $itemType = !empty($item['ingredient_id']) ? 'ingredient' : (!empty($item['mro_item_id']) ? 'mro' : 'other');
    $itemName = $item['ingredient_name'] ?? $item['mro_item_name'] ?? $item['item_description'] ?? 'Requested item';
    $notes = 'Created from ' . ($prs['pr_number'] ?? ('PRS #' . $prs['id']));

    $stmt = $db->prepare("
        INSERT INTO price_canvass
            (canvass_code, item_type, ingredient_id, mro_item_id, purchase_request_id, purchase_request_item_id,
             item_description, quantity, unit, status, created_by, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, ?)
    ");
    $stmt->execute([
        nextCanvassCode($db),
        $itemType,
        $item['ingredient_id'] ?? null,
        $item['mro_item_id'] ?? null,
        $prs['id'],
        $item['id'],
        $itemName,
        $item['quantity'],
        $item['unit'],
        $currentUser['user_id'],
        $notes
    ]);

    return (int) $db->lastInsertId();
}

function handlePut($db, $action, $currentUser) {
    $data = getRequestBody();
    $id = getParam('id') ?? ($data['id'] ?? null);
    
    switch ($action) {
        case 'select_quote':
            $quoteId = $data['quote_id'] ?? null;
            if (!$quoteId) {
                Response::error('Quote ID required', 400);
            }
            
            // Get quote details
            $quoteCheck = $db->prepare("
                SELECT q.*, c.id as canvass_id, c.status as canvass_status
                FROM canvass_quotes q
                JOIN price_canvass c ON q.canvass_id = c.id
                WHERE q.id = ?
            ");
            $quoteCheck->execute([$quoteId]);
            $quote = $quoteCheck->fetch();
            
            if (!$quote) {
                Response::error('Quote not found', 404);
            }
            if (!in_array($quote['canvass_status'], ['open', 'completed'], true)) {
                Response::error('Canvass is no longer available for selection', 400);
            }

            $autoSelection = autoSelectCheapestQuoteIfReady($db, (int) $quote['canvass_id']);
            if (!$autoSelection) {
                Response::error('Every accredited supplier must have a saved agreed price before a recommendation can be made', 400);
            }
            Response::success([
                'canvass_id' => $quote['canvass_id'],
                'selected_quote_id' => $autoSelection['quote_id'] ?? null,
                'selected_supplier_id' => $autoSelection['supplier_id'] ?? null,
                'unit_price' => $autoSelection['unit_price'] ?? null
            ], 'Registered supplier recommendation saved');
            break;

        case 'override_quote':
            $quoteId = (int) ($data['quote_id'] ?? 0);
            $reason = trim((string) ($data['reason'] ?? ''));
            if ($quoteId <= 0) {
                Response::error('Quote ID required', 400);
            }
            if ($reason === '') {
                Response::error('Reason is required when choosing a supplier other than the system recommendation', 400);
            }

            $quoteCheck = $db->prepare("
                SELECT q.*, c.id as canvass_id, c.status as canvass_status
                FROM canvass_quotes q
                JOIN price_canvass c ON q.canvass_id = c.id
                WHERE q.id = ?
            ");
            $quoteCheck->execute([$quoteId]);
            $quote = $quoteCheck->fetch(PDO::FETCH_ASSOC);
            if (!$quote) {
                Response::error('Quote not found', 404);
            }
            if (!in_array($quote['canvass_status'], ['open', 'completed'], true)) {
                Response::error('Canvass is no longer available for supplier choice', 400);
            }

            $selected = selectCanvassQuote($db, (int) $quote['canvass_id'], $quoteId, 'manual_override', $reason);
            Response::success([
                'canvass_id' => (int) $quote['canvass_id'],
                'selected_quote_id' => $selected['quote_id'],
                'selected_supplier_id' => $selected['supplier_id'],
                'unit_price' => $selected['unit_price'],
                'reason' => $reason
            ], 'Supplier choice saved');
            break;

        case 'confirm_limited_market':
            $canvassId = (int) ($data['canvass_id'] ?? 0);
            $reason = trim((string) ($data['reason'] ?? ''));
            if ($canvassId <= 0) {
                Response::error('Canvass ID required', 400);
            }

            $selected = selectLimitedMarketQuote($db, $canvassId, $reason);
            Response::success([
                'canvass_id' => $canvassId,
                'selected_quote_id' => (int) $selected['quote_id'],
                'selected_supplier_id' => (int) $selected['supplier_id'],
                'unit_price' => (float) $selected['unit_price'],
                'requires_gm_review' => true
            ], 'Registered supplier comparison saved. The GM will see the limited market on the Purchase Order.');
            break;
            
        case 'cancel':
            if (!$id) {
                Response::error('Canvass ID required', 400);
            }
            
            $check = $db->prepare("SELECT status FROM price_canvass WHERE id = ?");
            $check->execute([$id]);
            $canvass = $check->fetch();
            
            if (!$canvass) {
                Response::error('Canvass not found', 404);
            }
            if ($canvass['status'] === 'completed') {
                Response::error('Cannot cancel completed canvass', 400);
            }
            
            $db->prepare("UPDATE price_canvass SET status = 'cancelled' WHERE id = ?")
               ->execute([$id]);
            
            Response::success(null, 'Canvass cancelled');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
