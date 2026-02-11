<?php
/**
 * Highland Fresh System - Warehouse FG Dashboard API
 * 
 * GET - Get warehouse finished goods dashboard statistics
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

// Require Warehouse FG role
$currentUser = Auth::requireRole(['warehouse_fg', 'general_manager']);

if ($requestMethod !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    $db = Database::getInstance()->getConnection();
    
    $today = date('Y-m-d');
    
    // === FINISHED GOODS INVENTORY STATISTICS ===
    
    // Total FG in inventory - use multi-unit calculation
    $fgStats = $db->prepare("
        SELECT 
            COALESCE(SUM((COALESCE(fgi.boxes_available, 0) * COALESCE(p.pieces_per_box, 1)) + COALESCE(fgi.pieces_available, 0)), 0) as total_units,
            COUNT(DISTINCT fgi.product_id) as product_count
        FROM finished_goods_inventory fgi
        LEFT JOIN products p ON fgi.product_id = p.id
        WHERE fgi.status = 'available'
    ");
    $fgStats->execute();
    $fgData = $fgStats->fetch();
    
    // === CHILLER STATISTICS ===
    
    $chillerStats = $db->prepare("
        SELECT 
            COUNT(*) as total_chillers,
            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN status = 'full' THEN 1 ELSE 0 END) as full,
            SUM(CASE WHEN status IN ('maintenance', 'offline') THEN 1 ELSE 0 END) as offline,
            COALESCE(SUM(capacity), 0) as total_capacity,
            COALESCE(SUM(current_count), 0) as current_count
        FROM chiller_locations
        WHERE is_active = 1
    ");
    $chillerStats->execute();
    $chillerData = $chillerStats->fetch();
    
    // === EXPIRY STATISTICS ===
    
    // Items expiring within 3 days - use multi-unit calculation
    $expiringItems = $db->prepare("
        SELECT COUNT(*) as count, 
            COALESCE(SUM((COALESCE(fgi.boxes_available, 0) * COALESCE(p.pieces_per_box, 1)) + COALESCE(fgi.pieces_available, 0)), 0) as units
        FROM finished_goods_inventory fgi
        LEFT JOIN products p ON fgi.product_id = p.id
        WHERE fgi.status = 'available'
        AND fgi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
        AND fgi.expiry_date >= CURDATE()
    ");
    $expiringItems->execute();
    $expiringData = $expiringItems->fetch();
    
    // === RECEIVING STATISTICS ===
    
    // Pending batches from production (QC released but not yet received)
    // Uses NOT EXISTS for reliable checking even if fg_received column is NULL
    $pendingReceiving = $db->prepare("
        SELECT COUNT(*) as count
        FROM production_batches pb
        WHERE pb.qc_status = 'released'
        AND (pb.fg_received IS NULL OR pb.fg_received = 0)
        AND NOT EXISTS (SELECT 1 FROM fg_receiving fr WHERE fr.batch_id = pb.id)
        AND NOT EXISTS (SELECT 1 FROM finished_goods_inventory fgi WHERE fgi.batch_id = pb.id)
    ");
    $pendingReceiving->execute();
    $pendingData = $pendingReceiving->fetch();
    
    // Received today
    $receivedToday = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(quantity_received), 0) as units
        FROM fg_receiving
        WHERE DATE(received_at) = CURDATE()
    ");
    $receivedToday->execute();
    $receivedTodayData = $receivedToday->fetch();
    
    // === DELIVERY RECEIPT STATISTICS ===
    
    // Pending DRs
    $pendingDRs = $db->prepare("
        SELECT COUNT(*) as count
        FROM delivery_receipts
        WHERE status IN ('pending', 'preparing', 'draft')
    ");
    $pendingDRs->execute();
    $pendingDRData = $pendingDRs->fetch();
    
    // Released today
    $releasedToday = $db->prepare("
        SELECT COUNT(*) as dr_count, COALESCE(SUM(total_items), 0) as units
        FROM delivery_receipts
        WHERE DATE(dispatched_at) = CURDATE()
        AND status IN ('dispatched', 'delivered')
    ");
    $releasedToday->execute();
    $releasedData = $releasedToday->fetch();
    
    // === RECENT ACTIVITY ===
    
    $recentActivity = $db->prepare("
        SELECT 
            'receiving' as type,
            CONCAT('Received ', quantity_received, ' units') as description,
            received_at as timestamp
        FROM fg_receiving
        WHERE DATE(received_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 
            'dispatch' as type,
            CONCAT('DR ', dr_number, ' dispatched') as description,
            dispatched_at as timestamp
        FROM delivery_receipts
        WHERE dispatched_at IS NOT NULL
        AND DATE(dispatched_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        
        ORDER BY timestamp DESC
        LIMIT 10
    ");
    $recentActivity->execute();
    $activities = $recentActivity->fetchAll();
    
    // Build response
    $response = [
        'inventory' => [
            'total_units' => (int) ($fgData['total_units'] ?? 0),
            'product_count' => (int) ($fgData['product_count'] ?? 0)
        ],
        'chillers' => [
            'total' => (int) ($chillerData['total_chillers'] ?? 0),
            'available' => (int) ($chillerData['available'] ?? 0),
            'full' => (int) ($chillerData['full'] ?? 0),
            'offline' => (int) ($chillerData['offline'] ?? 0),
            'capacity' => (int) ($chillerData['total_capacity'] ?? 0),
            'current' => (int) ($chillerData['current_count'] ?? 0),
            'utilization' => $chillerData['total_capacity'] > 0 
                ? round(($chillerData['current_count'] / $chillerData['total_capacity']) * 100, 1) 
                : 0
        ],
        'expiring' => [
            'count' => (int) ($expiringData['count'] ?? 0),
            'units' => (int) ($expiringData['units'] ?? 0)
        ],
        'receiving' => [
            'pending' => (int) ($pendingData['count'] ?? 0),
            'received_today' => (int) ($receivedTodayData['count'] ?? 0),
            'units_today' => (int) ($receivedTodayData['units'] ?? 0)
        ],
        'delivery' => [
            'pending_drs' => (int) ($pendingDRData['count'] ?? 0),
            'released_today' => (int) ($releasedData['dr_count'] ?? 0),
            'units_released' => (int) ($releasedData['units'] ?? 0)
        ],
        'recent_activity' => $activities,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    Response::success($response, 'Dashboard data retrieved successfully');
    
} catch (Exception $e) {
    error_log("Warehouse FG Dashboard Error: " . $e->getMessage());
    Response::error('Failed to load dashboard data: ' . $e->getMessage(), 500);
}
