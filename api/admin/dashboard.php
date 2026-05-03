<?php
/**
 * Admin Dashboard API
 * Returns summary statistics for the admin panel
 */

require_once __DIR__ . '/../bootstrap.php';

// SECURITY: Restrict admin dashboard to GM/Admin roles
$currentUser = Auth::requireRole(['general_manager', 'admin']);

// Get database connection
$pdo = Database::getInstance()->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    $stats = [];
    
    // Users count
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
                         FROM users");
    $stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Farmers count
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                            SUM(CASE WHEN membership_type = 'member' THEN 1 ELSE 0 END) as members
                         FROM farmers");
    $stats['farmers'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Customers count
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                            SUM(CASE WHEN customer_type = 'wholesale' THEN 1 ELSE 0 END) as wholesale,
                            SUM(CASE WHEN customer_type = 'retail' THEN 1 ELSE 0 END) as retail,
                            SUM(CASE WHEN default_payment_type = 'credit' THEN 1 ELSE 0 END) as credit
                         FROM customers");
    $stats['customers'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Products count
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
                         FROM products");
    $stats['products'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Recipes count  
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
                         FROM master_recipes");
    $stats['recipes'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ingredients count
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
                         FROM ingredients");
    $stats['ingredients'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Storage tanks
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                            SUM(capacity_liters) as total_capacity,
                            SUM(current_volume) as total_volume
                         FROM storage_tanks");
    $stats['tanks'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Chillers
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available
                         FROM chiller_locations");
    $stats['chillers'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // QC Standards (grading standards count)
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
                         FROM milk_grading_standards");
    $stats['qc_standards'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Recent users (last 5)
    $stmt = $pdo->query("SELECT id, username, full_name, role, is_active, created_at 
                         FROM users ORDER BY created_at DESC LIMIT 5");
    $stats['recent_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Role distribution
    $stmt = $pdo->query("SELECT role, COUNT(*) as count 
                         FROM users WHERE is_active = 1 
                         GROUP BY role ORDER BY count DESC");
    $stats['role_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

   // Low stock alerts (ingredients + MRO)
   $lowStockStmt = $pdo->prepare("
      SELECT
         'ingredient' as item_type,
         ingredient_code as item_code,
         ingredient_name as item_name,
         current_stock,
         minimum_stock,
         unit_of_measure
      FROM ingredients
      WHERE is_active = 1 AND current_stock <= minimum_stock
      UNION ALL
      SELECT
         'mro' as item_type,
         item_code as item_code,
         item_name as item_name,
         current_stock,
         minimum_stock,
         unit_of_measure
      FROM mro_items
      WHERE is_active = 1 AND current_stock <= minimum_stock
      ORDER BY (current_stock / NULLIF(minimum_stock, 0)) ASC
      LIMIT 10
   ");
   $lowStockStmt->execute();
   $stats['low_stock_alerts'] = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);
   $stats['low_stock_count'] = count($stats['low_stock_alerts']);
    
    Response::success($stats);
    
} catch (Exception $e) {
    Response::error($e->getMessage(), 500);
}
