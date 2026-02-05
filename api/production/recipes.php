<?php
/**
 * Highland Fresh System - Recipes API
 * 
 * GET  - List all recipes / Get single recipe with ingredients
 * POST - Create new recipe (GM only)
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Production or GM role
$currentUser = Auth::requireRole(['production_staff', 'general_manager', 'qc_officer']);

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            $recipeId = getParam('id');
            
            if ($recipeId) {
                // Get single recipe with ingredients
                $stmt = $db->prepare("
                    SELECT mr.*, 
                           u.first_name as creator_first_name, 
                           u.last_name as creator_last_name
                    FROM master_recipes mr
                    LEFT JOIN users u ON mr.created_by = u.id
                    WHERE mr.id = ?
                ");
                $stmt->execute([$recipeId]);
                $recipe = $stmt->fetch();
                
                if (!$recipe) {
                    Response::notFound('Recipe not found');
                }
                
                // Get ingredients
                $ingStmt = $db->prepare("
                    SELECT * FROM recipe_ingredients WHERE recipe_id = ? ORDER BY ingredient_category, ingredient_name
                ");
                $ingStmt->execute([$recipeId]);
                $recipe['ingredients'] = $ingStmt->fetchAll();
                
                Response::success($recipe, 'Recipe retrieved successfully');
            }
            
            // List recipes
            $productType = getParam('product_type');
            $status = getParam('status', 'active');
            $search = getParam('search', '');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $where = "WHERE 1=1";
            $params = [];
            
            if ($productType && in_array($productType, ['bottled_milk', 'cheese', 'butter', 'yogurt', 'milk_bar'])) {
                $where .= " AND mr.product_type = ?";
                $params[] = $productType;
            }
            
            if ($status === 'active') {
                $where .= " AND mr.is_active = 1";
            } elseif ($status === 'inactive') {
                $where .= " AND mr.is_active = 0";
            }
            
            if (!empty($search)) {
                $where .= " AND (mr.recipe_code LIKE ? OR mr.product_name LIKE ? OR mr.variant LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            }
            
            // Get total count
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM master_recipes mr {$where}");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get recipes
            $stmt = $db->prepare("
                SELECT mr.id, mr.recipe_code, mr.product_name, mr.product_type, mr.variant,
                       mr.description, mr.base_milk_liters, mr.expected_yield,
                       mr.yield_unit, mr.shelf_life_days, mr.is_active, mr.created_at,
                       (SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = mr.id) as ingredient_count
                FROM master_recipes mr
                {$where}
                ORDER BY mr.product_type, mr.product_name
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $recipes = $stmt->fetchAll();
            
            Response::paginated($recipes, $total, $page, $limit, 'Recipes retrieved successfully');
            break;
            
        case 'POST':
            // Only GM can create recipes
            if ($currentUser['role'] !== 'general_manager') {
                Response::forbidden('Only General Manager can create recipes');
            }
            
            $recipeCode = trim(getParam('recipe_code', ''));
            $productName = trim(getParam('product_name', ''));
            $productType = getParam('product_type', '');
            $variant = trim(getParam('variant', ''));
            $sizeMl = getParam('size_ml');
            $sizeGrams = getParam('size_grams');
            $baseMilkLiters = getParam('base_milk_liters', 0);
            $expectedYield = getParam('expected_yield', 0);
            $yieldUnit = getParam('yield_unit', 'units');
            $shelfLifeDays = getParam('shelf_life_days', 7);
            $pasteurizationTemp = getParam('pasteurization_temp', 81);
            $pasteurizationTimeMins = getParam('pasteurization_time_mins', 15);
            $coolingTemp = getParam('cooling_temp', 4);
            $specialInstructions = trim(getParam('special_instructions', ''));
            $ingredients = getParam('ingredients', []);
            
            // Validation
            $errors = [];
            if (empty($productName)) $errors['product_name'] = 'Product name is required';
            if (!in_array($productType, ['bottled_milk', 'cheese', 'butter', 'yogurt', 'milk_bar'])) {
                $errors['product_type'] = 'Invalid product type';
            }
            if ($baseMilkLiters <= 0) $errors['base_milk_liters'] = 'Base milk liters must be greater than 0';
            if ($expectedYield <= 0) $errors['expected_yield'] = 'Expected yield must be greater than 0';
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Generate recipe code if not provided
            if (empty($recipeCode)) {
                $codeStmt = $db->query("SELECT MAX(CAST(SUBSTRING(recipe_code, 5) AS UNSIGNED)) as max_num FROM master_recipes");
                $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
                $recipeCode = 'RCP-' . str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT);
            }
            
            // Insert recipe
            $stmt = $db->prepare("
                INSERT INTO master_recipes (
                    recipe_code, product_name, product_type, variant, size_ml, size_grams,
                    base_milk_liters, expected_yield, yield_unit, shelf_life_days,
                    pasteurization_temp, pasteurization_time_mins, cooling_temp,
                    special_instructions, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $recipeCode, $productName, $productType, $variant, $sizeMl, $sizeGrams,
                $baseMilkLiters, $expectedYield, $yieldUnit, $shelfLifeDays,
                $pasteurizationTemp, $pasteurizationTimeMins, $coolingTemp,
                $specialInstructions, $currentUser['user_id']
            ]);
            
            $recipeId = $db->lastInsertId();
            
            // Insert ingredients if provided
            if (!empty($ingredients)) {
                $ingStmt = $db->prepare("
                    INSERT INTO recipe_ingredients (recipe_id, ingredient_name, ingredient_category, quantity, unit, is_optional, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($ingredients as $ing) {
                    $ingStmt->execute([
                        $recipeId,
                        $ing['ingredient_name'],
                        $ing['ingredient_category'],
                        $ing['quantity'],
                        $ing['unit'],
                        $ing['is_optional'] ?? 0,
                        $ing['notes'] ?? null
                    ]);
                }
            }
            
            Response::created([
                'id' => $recipeId,
                'recipe_code' => $recipeCode
            ], 'Recipe created successfully');
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Recipes API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
