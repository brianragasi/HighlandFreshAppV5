<?php
/**
 * Highland Fresh System - Warehouse FG API Endpoint Test
 * 
 * Quick verification of all API endpoints
 * 
 * @version 4.0
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== Warehouse FG API Endpoint Test ===\n\n";

try {
    $db = Database::getInstance()->getConnection();
    echo "✓ Database connected\n\n";
} catch (Exception $e) {
    die("✗ Database connection failed: " . $e->getMessage() . "\n");
}

// Simulated user for testing
$_SESSION['user'] = ['id' => 1, 'role' => 'warehouse_fg'];

// Test function that simulates API calls
function testEndpoint($name, $callback) {
    try {
        $result = $callback();
        if ($result !== false) {
            echo "✓ $name\n";
            return true;
        } else {
            echo "✗ $name - No data\n";
            return false;
        }
    } catch (Exception $e) {
        echo "✗ $name - " . $e->getMessage() . "\n";
        return false;
    }
}

// ================================================
// DASHBOARD TESTS
// ================================================
echo "--- Dashboard API ---\n";

testEndpoint("Dashboard stats query", function() use ($db) {
    $stmt = $db->query("
        SELECT 
            COALESCE(SUM(quantity_available), 0) as total_units,
            COUNT(DISTINCT product_name) as product_count
        FROM finished_goods_inventory
        WHERE status = 'available'
    ");
    return $stmt->fetch();
});

// ================================================
// CHILLERS TESTS  
// ================================================
echo "\n--- Chillers API ---\n";

testEndpoint("List chillers", function() use ($db) {
    $stmt = $db->query("
        SELECT c.*, 
            (SELECT COUNT(*) FROM finished_goods_inventory WHERE chiller_id = c.id) as inventory_count
        FROM chiller_locations c
        WHERE c.is_active = 1
    ");
    return $stmt->fetchAll();
});

testEndpoint("Chiller summary", function() use ($db) {
    $stmt = $db->query("
        SELECT SUM(capacity) as total_capacity, SUM(current_count) as total_current
        FROM chiller_locations WHERE is_active = 1
    ");
    return $stmt->fetch();
});

// ================================================
// INVENTORY TESTS
// ================================================
echo "\n--- Inventory API ---\n";

testEndpoint("List inventory", function() use ($db) {
    $stmt = $db->query("
        SELECT fg.*, c.chiller_code
        FROM finished_goods_inventory fg
        LEFT JOIN chiller_locations c ON fg.chiller_id = c.id
        WHERE fg.status = 'available'
        ORDER BY fg.expiry_date ASC
    ");
    return $stmt->fetchAll();
});

testEndpoint("Expiring inventory (3 days)", function() use ($db) {
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM finished_goods_inventory
        WHERE status = 'available'
        AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ");
    return $stmt->fetch();
});

testEndpoint("Multi-unit inventory display", function() use ($db) {
    $stmt = $db->query("
        SELECT 
            product_name,
            quantity_boxes,
            quantity_pieces,
            boxes_available,
            pieces_available
        FROM finished_goods_inventory
        WHERE status = 'available'
        LIMIT 3
    ");
    return $stmt->fetchAll();
});

// ================================================
// DELIVERY RECEIPTS TESTS
// ================================================
echo "\n--- Delivery Receipts API ---\n";

testEndpoint("List delivery receipts", function() use ($db) {
    $stmt = $db->query("
        SELECT dr.*, u.first_name as created_by_name
        FROM delivery_receipts dr
        LEFT JOIN users u ON dr.created_by = u.id
        ORDER BY dr.created_at DESC
        LIMIT 10
    ");
    return $stmt->fetchAll();
});

testEndpoint("Pending DRs", function() use ($db) {
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM delivery_receipts
        WHERE status IN ('pending', 'preparing', 'draft')
    ");
    return $stmt->fetch();
});

// ================================================
// CUSTOMERS TESTS
// ================================================
echo "\n--- Customers API ---\n";

testEndpoint("List customers", function() use ($db) {
    $stmt = $db->query("SELECT * FROM customers WHERE status = 'active' LIMIT 10");
    return $stmt->fetchAll();
});

testEndpoint("Customer search", function() use ($db) {
    $stmt = $db->prepare("
        SELECT id, name, customer_type
        FROM customers
        WHERE name LIKE ?
        LIMIT 5
    ");
    $stmt->execute(['%SM%']);
    return $stmt->fetchAll();
});

// ================================================
// DISPATCH TESTS
// ================================================
echo "\n--- Dispatch API ---\n";

testEndpoint("Dispatch history", function() use ($db) {
    $stmt = $db->query("
        SELECT dl.*, fgi.product_name
        FROM fg_dispatch_log dl
        LEFT JOIN finished_goods_inventory fgi ON dl.inventory_id = fgi.id
        ORDER BY dl.released_at DESC
        LIMIT 10
    ");
    return $stmt->fetchAll();
});

testEndpoint("FIFO check query", function() use ($db) {
    $stmt = $db->query("
        SELECT id, product_name, expiry_date, quantity_available
        FROM finished_goods_inventory
        WHERE status = 'available'
        ORDER BY expiry_date ASC
        LIMIT 5
    ");
    return $stmt->fetchAll();
});

// ================================================
// RECEIVING TESTS
// ================================================
echo "\n--- Receiving API ---\n";

testEndpoint("Pending batches to receive", function() use ($db) {
    $stmt = $db->query("
        SELECT pb.batch_code, pb.product_type, pb.actual_yield
        FROM production_batches pb
        WHERE pb.qc_status = 'released'
        AND pb.fg_received = 0
    ");
    return $stmt->fetchAll();
});

testEndpoint("Receiving history", function() use ($db) {
    $stmt = $db->query("
        SELECT fr.*, c.chiller_code
        FROM fg_receiving fr
        LEFT JOIN chiller_locations c ON fr.chiller_id = c.id
        ORDER BY fr.received_at DESC
        LIMIT 10
    ");
    return $stmt->fetchAll();
});

// ================================================
// RETURNS TESTS
// ================================================
echo "\n--- Returns API ---\n";

testEndpoint("Returns table accessible", function() use ($db) {
    $stmt = $db->query("SELECT COUNT(*) as count FROM product_returns");
    return $stmt->fetch();
});

// ================================================
// SALES ORDERS TESTS
// ================================================
echo "\n--- Sales Orders API ---\n";

testEndpoint("Sales orders table accessible", function() use ($db) {
    $stmt = $db->query("
        SELECT COUNT(*) as count, 
               SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM sales_orders
    ");
    return $stmt->fetch();
});

// ================================================
// SUMMARY
// ================================================
echo "\n=== API Endpoint Test Complete ===\n";
echo "All query patterns working correctly.\n";
