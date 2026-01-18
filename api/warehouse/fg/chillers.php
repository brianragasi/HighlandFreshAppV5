<?php
/**
 * Highland Fresh System - Warehouse FG Chillers API
 * 
 * GET - List chillers, get chiller details
 * POST - Create chiller
 * PUT - Update chiller, update temperature
 * 
 * @package HighlandFresh
 * @version 4.0
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
    error_log("Chillers API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            $stmt = $db->prepare("
                SELECT 
                    c.*,
                    (SELECT COUNT(*) FROM finished_goods_inventory WHERE chiller_id = c.id AND status = 'available') as inventory_count
                FROM chiller_locations c
                WHERE c.is_active = 1
                ORDER BY c.chiller_code
            ");
            $stmt->execute();
            $chillers = $stmt->fetchAll();
            Response::success($chillers, 'Chillers retrieved successfully');
            break;
            
        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('Chiller ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT c.*
                FROM chiller_locations c
                WHERE c.id = ? AND c.is_active = 1
            ");
            $stmt->execute([$id]);
            $chiller = $stmt->fetch();
            
            if (!$chiller) {
                Response::error('Chiller not found', 404);
            }
            
            // Get inventory in this chiller
            $invStmt = $db->prepare("
                SELECT 
                    fg.*,
                    p.product_name,
                    p.variant,
                    p.unit_size,
                    p.unit_measure
                FROM finished_goods_inventory fg
                JOIN products p ON fg.product_id = p.id
                WHERE fg.chiller_id = ? AND fg.status = 'available'
                ORDER BY fg.expiry_date ASC
            ");
            $invStmt->execute([$id]);
            $chiller['inventory'] = $invStmt->fetchAll();
            
            Response::success($chiller, 'Chiller details retrieved');
            break;
            
        case 'summary':
            $stmt = $db->prepare("
                SELECT 
                    SUM(capacity) as total_capacity,
                    SUM(current_count) as total_current,
                    COUNT(*) as total_chillers,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                    SUM(CASE WHEN status = 'full' THEN 1 ELSE 0 END) as full,
                    SUM(CASE WHEN status IN ('maintenance', 'offline') THEN 1 ELSE 0 END) as offline
                FROM chiller_locations
                WHERE is_active = 1
            ");
            $stmt->execute();
            $summary = $stmt->fetch();
            
            $summary['utilization'] = $summary['total_capacity'] > 0 
                ? round(($summary['total_current'] / $summary['total_capacity']) * 100, 1)
                : 0;
            
            Response::success($summary, 'Chiller summary retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

function handlePost($db, $action, $currentUser) {
    $data = getRequestBody();
    
    if ($action === 'create') {
        $required = ['chiller_code', 'chiller_name', 'capacity'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                Response::error("$field is required", 400);
            }
        }
        
        // Check for duplicate code
        $check = $db->prepare("SELECT id FROM chiller_locations WHERE chiller_code = ?");
        $check->execute([$data['chiller_code']]);
        if ($check->fetch()) {
            Response::error('Chiller code already exists', 400);
        }
        
        $stmt = $db->prepare("
            INSERT INTO chiller_locations 
            (chiller_code, chiller_name, capacity, temperature_celsius, min_temperature, max_temperature, location, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'available', ?)
        ");
        
        $stmt->execute([
            $data['chiller_code'],
            $data['chiller_name'],
            $data['capacity'],
            $data['temperature_celsius'] ?? 4.0,
            $data['min_temperature'] ?? 2.0,
            $data['max_temperature'] ?? 8.0,
            $data['location'] ?? null,
            $data['notes'] ?? null
        ]);
        
        $chillerId = $db->lastInsertId();
        
        logAudit($db, $currentUser['id'], 'create', 'chiller_locations', $chillerId, null, $data);
        
        Response::success(['id' => $chillerId], 'Chiller created successfully', 201);
    }
    
    Response::error('Invalid action', 400);
}

function handlePut($db, $action, $currentUser) {
    $data = getRequestBody();
    $id = getParam('id') ?? ($data['id'] ?? null);
    
    if (!$id) {
        Response::error('Chiller ID required', 400);
    }
    
    // Verify chiller exists
    $check = $db->prepare("SELECT * FROM chiller_locations WHERE id = ? AND is_active = 1");
    $check->execute([$id]);
    $oldData = $check->fetch();
    
    if (!$oldData) {
        Response::error('Chiller not found', 404);
    }
    
    switch ($action) {
        case 'update':
            $stmt = $db->prepare("
                UPDATE chiller_locations SET
                    chiller_name = COALESCE(?, chiller_name),
                    capacity = COALESCE(?, capacity),
                    temperature_celsius = COALESCE(?, temperature_celsius),
                    min_temperature = COALESCE(?, min_temperature),
                    max_temperature = COALESCE(?, max_temperature),
                    location = COALESCE(?, location),
                    status = COALESCE(?, status),
                    notes = COALESCE(?, notes),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['chiller_name'] ?? null,
                $data['capacity'] ?? null,
                $data['temperature_celsius'] ?? null,
                $data['min_temperature'] ?? null,
                $data['max_temperature'] ?? null,
                $data['location'] ?? null,
                $data['status'] ?? null,
                $data['notes'] ?? null,
                $id
            ]);
            
            logAudit($db, $currentUser['id'], 'update', 'chiller_locations', $id, $oldData, $data);
            
            Response::success(null, 'Chiller updated successfully');
            break;
            
        case 'update_temp':
            if (!isset($data['temperature_celsius'])) {
                Response::error('Temperature is required', 400);
            }
            
            $stmt = $db->prepare("
                UPDATE chiller_locations SET
                    temperature_celsius = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$data['temperature_celsius'], $id]);
            
            // Check if temperature is out of range
            if ($data['temperature_celsius'] < $oldData['min_temperature'] || 
                $data['temperature_celsius'] > $oldData['max_temperature']) {
                // TODO: Create alert
            }
            
            Response::success(null, 'Temperature updated');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
