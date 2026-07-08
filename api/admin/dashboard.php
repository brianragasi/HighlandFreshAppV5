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

      // Low stock alerts (ingredients + MRO) using reorder point
      $ingredientReorderExpr = auditColumnExists($pdo, 'ingredients', 'reorder_point')
         ? 'COALESCE(i.reorder_point, i.minimum_stock * 1.5)'
         : 'i.minimum_stock * 1.5';
      $mroReorderExpr = auditColumnExists($pdo, 'mro_items', 'reorder_point')
         ? 'COALESCE(m.reorder_point, m.minimum_stock * 1.5)'
         : 'm.minimum_stock * 1.5';

      $lowStockSql = "
         SELECT * FROM (
            SELECT
               'ingredient' as item_type,
               i.ingredient_code as item_code,
               i.ingredient_name as item_name,
               i.current_stock,
               i.minimum_stock,
               {$ingredientReorderExpr} as reorder_point,
               i.unit_of_measure,
               CASE
                  WHEN i.current_stock <= 0 THEN 'OUT_OF_STOCK'
                  WHEN i.current_stock <= {$ingredientReorderExpr} THEN 'LOW'
                  ELSE 'OK'
               END as stock_status,
               (i.current_stock / NULLIF({$ingredientReorderExpr}, 0)) as stock_ratio
            FROM ingredients i
            WHERE i.is_active = 1
              AND i.current_stock <= {$ingredientReorderExpr}

            UNION ALL

            SELECT
               'mro' as item_type,
               m.item_code as item_code,
               m.item_name as item_name,
               m.current_stock,
               m.minimum_stock,
               {$mroReorderExpr} as reorder_point,
               m.unit_of_measure,
               CASE
                  WHEN m.current_stock <= 0 THEN 'OUT_OF_STOCK'
                  WHEN m.current_stock <= {$mroReorderExpr} THEN 'LOW'
                  ELSE 'OK'
               END as stock_status,
               (m.current_stock / NULLIF({$mroReorderExpr}, 0)) as stock_ratio
            FROM mro_items m
            WHERE m.is_active = 1
              AND m.current_stock <= {$mroReorderExpr}
         ) as low_stock
         ORDER BY
            CASE stock_status
               WHEN 'OUT_OF_STOCK' THEN 1
               WHEN 'LOW' THEN 2
               ELSE 3
            END,
            stock_ratio ASC,
            item_name ASC
         LIMIT 15
      ";

      $lowStockStmt = $pdo->prepare($lowStockSql);
      $lowStockStmt->execute();
      $stats['low_stock_alerts'] = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);
      $stats['low_stock_count'] = count($stats['low_stock_alerts']);
    
    Response::success($stats);
    
} catch (Exception $e) {
    Response::error($e->getMessage(), 500);
}
