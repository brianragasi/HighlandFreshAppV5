<?php
/**
 * Highland Fresh System - Suppliers API
 * 
 * GET - List suppliers
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

// Require Warehouse Raw role or general manager
$currentUser = Auth::requireRole(['warehouse_raw', 'warehouse_fg', 'general_manager', 'purchaser']);

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
    error_log("Suppliers API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            $activeOnly = getParam('active', '1');
            
            $sql = "SELECT id, supplier_code, supplier_name, contact_person, phone, email, address, payment_terms, is_active, notes
                    FROM suppliers";
            
            if ($activeOnly === '1') {
                $sql .= " WHERE is_active = 1";
            }
            
            $sql .= " ORDER BY supplier_name ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $suppliers = $stmt->fetchAll();
            
            Response::success($suppliers, 'Suppliers retrieved');
            break;
            
        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('Supplier ID required', 400);
            }
            
            $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            $supplier = $stmt->fetch();
            
            if (!$supplier) {
                Response::error('Supplier not found', 404);
            }
            
            Response::success($supplier, 'Supplier details retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
