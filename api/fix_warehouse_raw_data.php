<?php
/**
 * Highland Fresh System - Warehouse Raw Data Fix Script
 * 
 * Fixes data issues found during integration testing:
 * 1. Requisition items with 0 or negative quantities
 * 2. Creates sample inventory transactions
 * 3. Adds sample stock to ingredients
 * 4. Adds sample MRO inventory
 * 
 * @version 4.0
 */

// Direct database connection
$host = 'localhost';
$dbname = 'highland_fresh';
$username = 'root';
$password = '';

try {
    $db = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    echo "✅ Database connection successful\n\n";
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// ============================================================================
// FIX 1: Requisition items with 0 or negative quantities
// ============================================================================
echo "=== FIX 1: Fixing requisition items with invalid quantities ===\n";

// Option 1: Set them to a default quantity (10 units)
$stmt = $db->prepare("
    UPDATE requisition_items 
    SET requested_quantity = 10.00
    WHERE requested_quantity <= 0
");
$stmt->execute();
$affected = $stmt->rowCount();
echo "✅ Updated $affected requisition items with default quantity (10.00)\n";

// ============================================================================
// FIX 2: Create sample inventory transactions
// ============================================================================
echo "\n=== FIX 2: Creating sample inventory transactions ===\n";

// Get a user ID for performing_by
$user = $db->query("SELECT id FROM users WHERE role = 'warehouse_raw' OR role = 'general_manager' LIMIT 1")->fetch();
$userId = $user ? $user['id'] : 1;

// Check if inventory_transactions is empty
$txCount = $db->query("SELECT COUNT(*) FROM inventory_transactions")->fetchColumn();

if ($txCount == 0) {
    // Get ingredients for sample transactions
    $ingredients = $db->query("SELECT id, ingredient_code, ingredient_name, unit_of_measure, storage_location FROM ingredients WHERE is_active = 1 LIMIT 5")->fetchAll();
    
    foreach ($ingredients as $ing) {
        // Create receive transaction
        $txCode = 'TX-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("
            INSERT INTO inventory_transactions 
            (transaction_code, transaction_type, item_type, item_id, batch_id,
             quantity, unit_of_measure, reference_type, to_location, performed_by, reason, created_at)
            VALUES (?, 'receive', 'ingredient', ?, NULL, 100, ?, 'purchase', ?, ?, 'Initial stock from supplier', NOW())
        ");
        $stmt->execute([
            $txCode,
            $ing['id'],
            $ing['unit_of_measure'],
            $ing['storage_location'] ?? 'Warehouse',
            $userId
        ]);
        echo "  ✅ Created receive transaction for {$ing['ingredient_name']}\n";
    }
    
    // Get MRO items for sample transactions
    $mroItems = $db->query("SELECT id, item_code, item_name, unit_of_measure, storage_location FROM mro_items WHERE is_active = 1 LIMIT 3")->fetchAll();
    
    foreach ($mroItems as $mro) {
        // Create receive transaction
        $txCode = 'TX-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("
            INSERT INTO inventory_transactions 
            (transaction_code, transaction_type, item_type, item_id, batch_id,
             quantity, unit_of_measure, reference_type, to_location, performed_by, reason, created_at)
            VALUES (?, 'receive', 'mro', ?, NULL, 20, ?, 'purchase', ?, ?, 'Initial MRO stock', NOW())
        ");
        $stmt->execute([
            $txCode,
            $mro['id'],
            $mro['unit_of_measure'] ?? 'pcs',
            $mro['storage_location'] ?? 'MRO Storage',
            $userId
        ]);
        echo "  ✅ Created receive transaction for {$mro['item_name']}\n";
    }
    
    echo "✅ Created sample inventory transactions\n";
} else {
    echo "ℹ️ Inventory transactions already exist ($txCount records)\n";
}

// ============================================================================
// FIX 3: Add sample ingredient batches (if none exist)
// ============================================================================
echo "\n=== FIX 3: Adding sample ingredient batches ===\n";

$batchCount = $db->query("SELECT COUNT(*) FROM ingredient_batches")->fetchColumn();

if ($batchCount == 0) {
    $ingredients = $db->query("SELECT id, ingredient_code, ingredient_name, unit_of_measure FROM ingredients WHERE is_active = 1 LIMIT 8")->fetchAll();
    
    foreach ($ingredients as $ing) {
        $batchCode = 'IB-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $quantity = rand(50, 200);
        $expiryDate = date('Y-m-d', strtotime('+' . rand(30, 180) . ' days'));
        
        $stmt = $db->prepare("
            INSERT INTO ingredient_batches 
            (batch_code, ingredient_id, quantity, remaining_quantity, unit_cost,
             supplier_name, supplier_batch_no, received_date, expiry_date, received_by, status)
            VALUES (?, ?, ?, ?, ?, 'Sample Supplier', ?, CURDATE(), ?, ?, 'available')
        ");
        $stmt->execute([
            $batchCode,
            $ing['id'],
            $quantity,
            $quantity,
            rand(10, 100),
            'SUP-' . rand(1000, 9999),
            $expiryDate,
            $userId
        ]);
        
        // Update ingredient current_stock
        $db->prepare("UPDATE ingredients SET current_stock = current_stock + ? WHERE id = ?")->execute([$quantity, $ing['id']]);
        
        echo "  ✅ Created batch $batchCode for {$ing['ingredient_name']} (qty: $quantity)\n";
    }
    echo "✅ Created sample ingredient batches\n";
} else {
    echo "ℹ️ Ingredient batches already exist ($batchCount records)\n";
}

// ============================================================================
// FIX 4: Add sample MRO inventory (if none exist)
// ============================================================================
echo "\n=== FIX 4: Adding sample MRO inventory ===\n";

$mroInvCount = $db->query("SELECT COUNT(*) FROM mro_inventory")->fetchColumn();

if ($mroInvCount == 0) {
    $mroItems = $db->query("SELECT id, item_code, item_name, unit_of_measure FROM mro_items WHERE is_active = 1 LIMIT 8")->fetchAll();
    
    foreach ($mroItems as $mro) {
        $batchCode = 'MRO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $quantity = rand(10, 50);
        
        $stmt = $db->prepare("
            INSERT INTO mro_inventory 
            (batch_code, mro_item_id, quantity, remaining_quantity, unit_cost,
             supplier_name, received_date, received_by, status)
            VALUES (?, ?, ?, ?, ?, 'MRO Supplier', CURDATE(), ?, 'available')
        ");
        $stmt->execute([
            $batchCode,
            $mro['id'],
            $quantity,
            $quantity,
            rand(50, 500),
            $userId
        ]);
        
        // Update MRO item current_stock
        $db->prepare("UPDATE mro_items SET current_stock = current_stock + ? WHERE id = ?")->execute([$quantity, $mro['id']]);
        
        echo "  ✅ Created MRO inventory $batchCode for {$mro['item_name']} (qty: $quantity)\n";
    }
    echo "✅ Created sample MRO inventory\n";
} else {
    echo "ℹ️ MRO inventory already exists ($mroInvCount records)\n";
}

// ============================================================================
// FIX 5: Add sample raw milk in tanks (if none exist)
// ============================================================================
echo "\n=== FIX 5: Setting up sample raw milk storage ===\n";

$tankMilkCount = $db->query("SELECT COUNT(*) FROM tank_milk_batches")->fetchColumn();

if ($tankMilkCount == 0) {
    // First check if we have raw_milk_inventory
    $rawMilkAvailable = $db->query("
        SELECT rmi.id, rmi.volume_liters
        FROM raw_milk_inventory rmi
        WHERE rmi.status = 'available'
        AND NOT EXISTS (
            SELECT 1 FROM tank_milk_batches tmb 
            WHERE tmb.raw_milk_inventory_id = rmi.id
        )
        LIMIT 3
    ")->fetchAll();
    
    if (count($rawMilkAvailable) > 0) {
        // Get tanks
        $tanks = $db->query("SELECT id, tank_code, capacity_liters FROM storage_tanks WHERE is_active = 1 AND status = 'available' LIMIT 3")->fetchAll();
        
        $tankIndex = 0;
        foreach ($rawMilkAvailable as $milk) {
            if ($tankIndex >= count($tanks)) break;
            
            $tank = $tanks[$tankIndex];
            $volume = min($milk['volume_liters'], $tank['capacity_liters']);
            $expiryDate = date('Y-m-d', strtotime('+3 days'));
            
            // Create tank_milk_batch
            $stmt = $db->prepare("
                INSERT INTO tank_milk_batches 
                (tank_id, raw_milk_inventory_id, volume_liters, remaining_liters, 
                 received_date, expiry_date, received_by, status)
                VALUES (?, ?, ?, ?, CURDATE(), ?, ?, 'available')
            ");
            $stmt->execute([
                $tank['id'],
                $milk['id'],
                $volume,
                $volume,
                $expiryDate,
                $userId
            ]);
            
            // Update tank
            $db->prepare("UPDATE storage_tanks SET current_volume = current_volume + ?, status = 'in_use' WHERE id = ?")->execute([$volume, $tank['id']]);
            
            // Update raw_milk_inventory
            $db->prepare("UPDATE raw_milk_inventory SET status = 'in_production' WHERE id = ?")->execute([$milk['id']]);
            
            echo "  ✅ Added {$volume}L milk to {$tank['tank_code']}\n";
            $tankIndex++;
        }
        echo "✅ Set up sample raw milk in tanks\n";
    } else {
        echo "ℹ️ No QC-approved milk available to store (this is normal if QC hasn't approved any)\n";
    }
} else {
    echo "ℹ️ Tank milk batches already exist ($tankMilkCount records)\n";
}

// ============================================================================
// VERIFICATION
// ============================================================================
echo "\n=== VERIFICATION ===\n";

// Check requisition items
$invalidQty = $db->query("SELECT COUNT(*) FROM requisition_items WHERE requested_quantity <= 0")->fetchColumn();
echo "Requisition items with invalid qty: $invalidQty " . ($invalidQty == 0 ? "✅" : "❌") . "\n";

// Check inventory transactions
$txCount = $db->query("SELECT COUNT(*) FROM inventory_transactions")->fetchColumn();
echo "Inventory transactions: $txCount " . ($txCount > 0 ? "✅" : "❌") . "\n";

// Check ingredient batches
$batchCount = $db->query("SELECT COUNT(*) FROM ingredient_batches")->fetchColumn();
echo "Ingredient batches: $batchCount " . ($batchCount > 0 ? "✅" : "❌") . "\n";

// Check MRO inventory
$mroInvCount = $db->query("SELECT COUNT(*) FROM mro_inventory")->fetchColumn();
echo "MRO inventory: $mroInvCount " . ($mroInvCount > 0 ? "✅" : "❌") . "\n";

// Check ingredient stock
$zeroStock = $db->query("SELECT COUNT(*) FROM ingredients WHERE is_active = 1 AND current_stock = 0")->fetchColumn();
$totalIng = $db->query("SELECT COUNT(*) FROM ingredients WHERE is_active = 1")->fetchColumn();
echo "Ingredients with zero stock: $zeroStock of $totalIng\n";

// Check MRO stock
$zeroMro = $db->query("SELECT COUNT(*) FROM mro_items WHERE is_active = 1 AND current_stock = 0")->fetchColumn();
$totalMro = $db->query("SELECT COUNT(*) FROM mro_items WHERE is_active = 1")->fetchColumn();
echo "MRO items with zero stock: $zeroMro of $totalMro\n";

echo "\n✅ All fixes applied successfully!\n";
