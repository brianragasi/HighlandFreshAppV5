<?php
/**
 * Highland Fresh System - Price Canvassing API
 * 
 * GET - List canvass requests, get quotes
 * POST - Create canvass, add quotes
 * PUT - Select quote, complete canvass
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Purchaser or GM role
$currentUser = Auth::requireRole(['purchaser', 'general_manager']);

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
                    (SELECT COUNT(*) FROM canvass_quotes WHERE canvass_id = c.id) as quote_count,
                    (SELECT MIN(unit_price) FROM canvass_quotes WHERE canvass_id = c.id) as lowest_price,
                    (SELECT MAX(unit_price) FROM canvass_quotes WHERE canvass_id = c.id) as highest_price
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
                JOIN suppliers s ON q.supplier_id = s.id
                WHERE q.canvass_id = ?
                ORDER BY q.unit_price ASC
            ");
            $quotesStmt->execute([$id]);
            $canvass['quotes'] = $quotesStmt->fetchAll();
            
            Response::success($canvass, 'Canvass details retrieved');
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
            
            // Generate canvass code
            $stmt = $db->query("SELECT canvass_code FROM price_canvass ORDER BY id DESC LIMIT 1");
            $last = $stmt->fetch();
            if ($last) {
                preg_match('/CNV-(\d{4})-(\d+)/', $last['canvass_code'], $matches);
                $year = date('Y');
                $seq = ($matches[1] == $year) ? ((int)$matches[2] + 1) : 1;
            } else {
                $seq = 1;
            }
            $canvassCode = sprintf('CNV-%s-%04d', date('Y'), $seq);
            
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
            $check = $db->prepare("SELECT id, status FROM price_canvass WHERE id = ?");
            $check->execute([$data['canvass_id']]);
            $canvass = $check->fetch();
            
            if (!$canvass) {
                Response::error('Canvass not found', 404);
            }
            if ($canvass['status'] !== 'open') {
                Response::error('Canvass is no longer open for quotes', 400);
            }
            
            // Check if supplier already quoted
            $dupCheck = $db->prepare("SELECT id FROM canvass_quotes WHERE canvass_id = ? AND supplier_id = ?");
            $dupCheck->execute([$data['canvass_id'], $data['supplier_id']]);
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
                $data['supplier_id'],
                $data['unit_price'],
                $data['delivery_days'] ?? 7,
                $data['payment_terms'] ?? 'cash',
                $data['validity_date'] ?? null,
                $data['notes'] ?? null
            ]);
            
            Response::success(['id' => $db->lastInsertId()], 'Quote added', 201);
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
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
            if ($quote['canvass_status'] !== 'open') {
                Response::error('Canvass is no longer open', 400);
            }
            
            $db->beginTransaction();
            try {
                // Clear any previously selected quote
                $db->prepare("UPDATE canvass_quotes SET is_selected = 0 WHERE canvass_id = ?")
                   ->execute([$quote['canvass_id']]);
                
                // Select this quote
                $db->prepare("UPDATE canvass_quotes SET is_selected = 1 WHERE id = ?")
                   ->execute([$quoteId]);
                
                // Update canvass
                $db->prepare("UPDATE price_canvass SET selected_quote_id = ?, status = 'completed' WHERE id = ?")
                   ->execute([$quoteId, $quote['canvass_id']]);
                
                $db->commit();
                
                Response::success([
                    'canvass_id' => $quote['canvass_id'],
                    'selected_supplier_id' => $quote['supplier_id'],
                    'unit_price' => $quote['unit_price']
                ], 'Quote selected and canvass completed');
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
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
