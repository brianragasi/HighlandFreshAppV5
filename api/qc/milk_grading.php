<?php
/**
 * Highland Fresh System - Milk Grading API (QC Test)
 * ANNEX B Pricing Implementation
 * 
 * UPDATED: Uses milk_receiving and receiving_id (revised schema)
 * 
 * Endpoints:
 * GET  - List all QC tests / Get single test
 * POST - Create new QC test (Grade milk receiving)
 * 
 * Pricing Structure (ANNEX B):
 * - Base Price: ₱25.00 + Incentive ₱5.00 = ₱30.00/L
 * - Fat Adjustment: -₱1.00 to +₱2.25 based on fat %
 * - Acidity Deduction: ₱0.25 to ₱1.50 based on titratable acidity %
 * - Sediment Deduction: Grade 2 = -₱0.50, Grade 3 = -₱1.00
 * 
 * Rejection Criteria:
 * - APT Result: Positive
 * - Titratable Acidity: >= 0.25%
 * - Specific Gravity: < 1.025
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require QC role
$currentUser = Auth::requireRole(['qc_officer', 'general_manager']);

// ANNEX B Pricing Constants
define('BASE_PRICE', 25.00);
define('INCENTIVE', 5.00);
define('STANDARD_PRICE', 30.00); // BASE_PRICE + INCENTIVE

/**
 * Calculate fat adjustment based on ANNEX B
 */
function calculateFatAdjustment($fatPercentage) {
    $fat = floatval($fatPercentage);
    
    if ($fat >= 1.5 && $fat < 2.0) return -1.00;
    if ($fat >= 2.0 && $fat < 2.5) return -0.75;
    if ($fat >= 2.5 && $fat < 3.0) return -0.50;
    if ($fat >= 3.0 && $fat < 3.5) return -0.25;
    if ($fat >= 3.5 && $fat <= 4.0) return 0.00;  // Standard
    if ($fat > 4.0 && $fat <= 4.5) return 0.25;
    if ($fat > 4.5 && $fat <= 5.0) return 0.50;
    if ($fat > 5.0 && $fat <= 5.5) return 0.75;
    if ($fat > 5.5 && $fat <= 6.0) return 1.00;
    if ($fat > 6.0 && $fat <= 6.5) return 1.25;
    if ($fat > 6.5 && $fat <= 7.0) return 1.50;
    if ($fat > 7.0 && $fat <= 7.5) return 1.75;
    if ($fat > 7.5 && $fat <= 8.0) return 2.00;
    if ($fat > 8.0 && $fat <= 8.5) return 2.25;
    
    return 0.00;
}

/**
 * Calculate acidity deduction based on ANNEX B
 */
function calculateAcidityDeduction($titratableAcidity) {
    $acidity = floatval($titratableAcidity);
    
    // Standard range: 0.14% - 0.18% (no deduction)
    if ($acidity <= 0.18) return 0.00;
    
    if ($acidity >= 0.19 && $acidity < 0.20) return 0.25;
    if ($acidity >= 0.20 && $acidity < 0.21) return 0.50;
    if ($acidity >= 0.21 && $acidity < 0.22) return 0.75;
    if ($acidity >= 0.22 && $acidity < 0.23) return 1.00;
    if ($acidity >= 0.23 && $acidity < 0.24) return 1.25;
    if ($acidity >= 0.24 && $acidity < 0.25) return 1.50;
    
    // >= 0.25% should be rejected before this function is called
    return 0.00;
}

/**
 * Calculate sediment deduction based on ANNEX B
 */
function calculateSedimentDeduction($sedimentGrade) {
    $grade = intval($sedimentGrade);
    
    switch ($grade) {
        case 1: return 0.00;  // Grade 1 - Clean
        case 2: return 0.50;  // Grade 2 - Slight
        case 3: return 1.00;  // Grade 3 - Dirty
        default: return 0.00;
    }
}

/**
 * Calculate milk quality grade (A, B, C, D) based on test parameters
 * 
 * Grade A: fat >= 4.0%, acidity <= 0.16%, sediment grade 1
 * Grade B: fat >= 3.5%, acidity <= 0.18%, sediment grade 1-2
 * Grade C: fat >= 3.0%, acidity <= 0.20%, sediment grade 1-2
 * Grade D: Everything else that passes
 */
function calculateMilkGrade($fatPercentage, $titratableAcidity, $sedimentGrade) {
    $fat = floatval($fatPercentage);
    $acidity = floatval($titratableAcidity);
    $sediment = intval($sedimentGrade);
    
    // Grade A: Premium quality
    if ($fat >= 4.0 && $acidity <= 0.16 && $sediment == 1) {
        return 'A';
    }
    
    // Grade B: Good quality
    if ($fat >= 3.5 && $acidity <= 0.18 && $sediment <= 2) {
        return 'B';
    }
    
    // Grade C: Standard quality
    if ($fat >= 3.0 && $acidity <= 0.20 && $sediment <= 2) {
        return 'C';
    }
    
    // Grade D: Everything else that passes acceptance criteria
    return 'D';
}

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            $testId = getParam('id');
            
            if ($testId) {
                // Get single test (using milk_receiving and receiving_id - revised schema)
                $stmt = $db->prepare("
                    SELECT qmt.*, 
                           mr.receiving_code, mr.volume_liters, mr.receiving_date,
                           mr.rmr_number, mr.visual_inspection as apt_result,
                           f.farmer_code, 
                           COALESCE(CONCAT(f.first_name, ' ', COALESCE(f.last_name, '')), f.first_name, '') as farmer_name,
                           f.first_name as farmer_first_name, COALESCE(f.last_name, '') as farmer_last_name,
                           f.membership_type,
                           u.first_name as tester_first_name, u.last_name as tester_last_name
                    FROM qc_milk_tests qmt
                    LEFT JOIN milk_receiving mr ON qmt.receiving_id = mr.id
                    LEFT JOIN farmers f ON mr.farmer_id = f.id
                    LEFT JOIN users u ON qmt.tested_by = u.id
                    WHERE qmt.id = ?
                ");
                $stmt->execute([$testId]);
                $test = $stmt->fetch();
                
                if (!$test) {
                    Response::notFound('Test not found');
                }
                
                Response::success($test, 'Test retrieved successfully');
            }
            
            // List tests (using milk_receiving - revised schema)
            $receivingId = getParam('receiving_id');
            $farmerId = getParam('farmer_id');
            $status = getParam('status'); // 'accepted' or 'rejected'
            $dateFrom = getParam('date_from');
            $dateTo = getParam('date_to');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $where = "WHERE 1=1";
            $params = [];
            
            if ($receivingId) {
                $where .= " AND qmt.receiving_id = ?";
                $params[] = $receivingId;
            }
            
            if ($farmerId) {
                $where .= " AND mr.farmer_id = ?";
                $params[] = $farmerId;
            }
            
            if ($status === 'accepted') {
                $where .= " AND qmt.is_accepted = 1";
            } elseif ($status === 'rejected') {
                $where .= " AND qmt.is_accepted = 0";
            }
            
            if ($dateFrom) {
                $where .= " AND DATE(qmt.test_datetime) >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $where .= " AND DATE(qmt.test_datetime) <= ?";
                $params[] = $dateTo;
            }
            
            // Get total count (using milk_receiving - revised schema)
            $countStmt = $db->prepare("
                SELECT COUNT(*) as total 
                FROM qc_milk_tests qmt 
                LEFT JOIN milk_receiving mr ON qmt.receiving_id = mr.id
                {$where}
            ");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get tests (using milk_receiving - revised schema)
            $stmt = $db->prepare("
                SELECT qmt.*, 
                       mr.receiving_code, mr.volume_liters, mr.receiving_date, mr.rmr_number,
                       f.farmer_code, 
                       COALESCE(f.first_name, '') as farmer_first_name,
                       COALESCE(f.last_name, '') as farmer_last_name,
                       CONCAT(COALESCE(f.first_name, ''), ' ', COALESCE(f.last_name, '')) as farmer_name,
                       u.first_name as tester_first_name, u.last_name as tester_last_name
                FROM qc_milk_tests qmt
                LEFT JOIN milk_receiving mr ON qmt.receiving_id = mr.id
                LEFT JOIN farmers f ON mr.farmer_id = f.id
                LEFT JOIN users u ON qmt.tested_by = u.id
                {$where}
                ORDER BY qmt.test_datetime DESC
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $tests = $stmt->fetchAll();
            
            Response::paginated($tests, $total, $page, $limit, 'Tests retrieved successfully');
            break;
            
        case 'POST':
            // Create QC test (Grade milk) with ANNEX B pricing
            // Updated: Uses receiving_id (revised schema)
            $receivingId = getParam('receiving_id');
            
            // Primary test parameters
            $fatPercentage = getParam('fat_percentage');
            $titratableAcidity = getParam('titratable_acidity');
            $temperatureCelsius = getParam('temperature_celsius');
            $sedimentGrade = getParam('sediment_grade', 1);
            $density = getParam('density'); // Specific Gravity
            
            // Milkosonic SL50 optional parameters
            $proteinPercentage = getParam('protein_percentage');
            $lactosePercentage = getParam('lactose_percentage');
            $snfPercentage = getParam('snf_percentage');
            $saltsPercentage = getParam('salts_percentage');
            $totalSolidsPercentage = getParam('total_solids_percentage');
            $addedWaterPercentage = getParam('added_water_percentage');
            $freezingPoint = getParam('freezing_point');
            $sampleTemperature = getParam('sample_temperature');
            
            $notes = trim(getParam('notes', ''));
            
            // Validation (updated for receiving_id - revised schema)
            $errors = [];
            if (empty($receivingId)) $errors['receiving_id'] = 'Receiving record is required';
            if (!isset($fatPercentage) || $fatPercentage < 0) {
                $errors['fat_percentage'] = 'Valid fat percentage is required';
            }
            if (!isset($titratableAcidity) || $titratableAcidity < 0) {
                $errors['titratable_acidity'] = 'Valid titratable acidity is required';
            }
            if (!isset($temperatureCelsius)) {
                $errors['temperature_celsius'] = 'Temperature is required';
            }
            if (!in_array($sedimentGrade, [1, 2, 3, '1', '2', '3'])) {
                $errors['sediment_grade'] = 'Sediment grade must be 1, 2, or 3';
            }
            if (isset($density) && $density !== '' && $density !== null && floatval($density) < 1.0) {
                $errors['density'] = 'Invalid specific gravity';
            }
            
            // Check receiving exists and is pending (using milk_receiving - revised schema)
            $receiving = null;
            if ($receivingId) {
                $receivingStmt = $db->prepare("
                    SELECT mr.*, f.membership_type, f.base_price_per_liter, mr.milk_type_id
                    FROM milk_receiving mr
                    LEFT JOIN farmers f ON mr.farmer_id = f.id
                    WHERE mr.id = ?
                ");
                $receivingStmt->execute([$receivingId]);
                $receiving = $receivingStmt->fetch();
                
                if (!$receiving) {
                    $errors['receiving_id'] = 'Receiving record not found';
                } elseif (!in_array($receiving['status'], ['pending_qc', 'in_testing'])) {
                    $errors['receiving_id'] = 'Receiving record has already been tested';
                }
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Check rejection criteria (ANNEX B)
            $isAccepted = true;
            $rejectionReasons = [];
            
            // 1. Visual inspection failed = Reject
            if (isset($receiving['visual_inspection']) && $receiving['visual_inspection'] === 'fail') {
                $isAccepted = false;
                $rejectionReasons[] = 'Visual inspection failed';
            }
            
            // 2. Titratable Acidity >= 0.25% = Reject
            if (floatval($titratableAcidity) >= 0.25) {
                $isAccepted = false;
                $rejectionReasons[] = 'Titratable acidity too high (≥0.25%) - will clot in pasteurizer';
            }
            
            // 3. Specific Gravity < 1.025 = Reject
            if (!empty($density) && floatval($density) < 1.025) {
                $isAccepted = false;
                $rejectionReasons[] = 'Specific gravity below 1.025 (suspected adulteration)';
            }
            
            $rejectionReason = !empty($rejectionReasons) ? implode('; ', $rejectionReasons) : null;
            
            // Calculate pricing (ANNEX B) - use receiving data
            if ($isAccepted) {
                $fatAdjustment = calculateFatAdjustment($fatPercentage);
                $acidityDeduction = calculateAcidityDeduction($titratableAcidity);
                $sedimentDeduction = calculateSedimentDeduction($sedimentGrade);
                
                $finalPricePerLiter = STANDARD_PRICE + $fatAdjustment - $acidityDeduction - $sedimentDeduction;
                $totalAmount = floatval($receiving['volume_liters']) * $finalPricePerLiter;
                
                // Calculate milk quality grade (A, B, C, D)
                $milkGrade = calculateMilkGrade($fatPercentage, $titratableAcidity, $sedimentGrade);
            } else {
                $fatAdjustment = 0;
                $acidityDeduction = 0;
                $sedimentDeduction = 0;
                $finalPricePerLiter = 0;
                $totalAmount = 0;
                $milkGrade = null; // No grade for rejected milk
            }
            
            // Generate test code
            $codeStmt = $db->query("SELECT MAX(CAST(SUBSTRING(test_code, 5) AS UNSIGNED)) as max_num FROM qc_milk_tests WHERE test_code LIKE 'QCT-%'");
            $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
            $testCode = 'QCT-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);
            
            // Begin transaction
            $db->beginTransaction();
            
            try {
                // Insert test (using receiving_id and milk_type_id - revised schema)
                $stmt = $db->prepare("
                    INSERT INTO qc_milk_tests (
                        test_code, receiving_id, milk_type_id, tested_by, test_datetime,
                        fat_percentage, titratable_acidity, temperature_celsius, sediment_grade,
                        specific_gravity, protein_percentage, lactose_percentage, snf_percentage,
                        total_solids_percentage, added_water_percentage, freezing_point,
                        base_price_per_liter, fat_adjustment, acidity_deduction, sediment_deduction,
                        final_price_per_liter, total_amount, is_accepted, grade, rejection_reason, notes
                    ) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $testCode, 
                    $receivingId, 
                    $receiving['milk_type_id'],
                    $currentUser['user_id'],
                    $fatPercentage, 
                    $titratableAcidity, 
                    $temperatureCelsius, 
                    $sedimentGrade,
                    $density ?: null, 
                    $proteinPercentage ?: null, 
                    $lactosePercentage ?: null, 
                    $snfPercentage ?: null,
                    $totalSolidsPercentage ?: null,
                    $addedWaterPercentage ?: null,
                    $freezingPoint ?: null,
                    STANDARD_PRICE, 
                    $fatAdjustment, 
                    $acidityDeduction,
                    $sedimentDeduction,
                    $finalPricePerLiter, 
                    $totalAmount, 
                    $isAccepted ? 1 : 0,
                    $milkGrade,
                    $rejectionReason, 
                    $notes
                ]);
                
                $testId = $db->lastInsertId();
                
                // Update receiving status (using milk_receiving - revised schema)
                $receivingStatus = $isAccepted ? 'accepted' : 'rejected';
                $acceptedLiters = $isAccepted ? $receiving['volume_liters'] : 0;
                $updateStmt = $db->prepare("
                    UPDATE milk_receiving 
                    SET status = ?, 
                        accepted_liters = ?,
                        rejected_liters = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $receivingStatus, 
                    $acceptedLiters,
                    $isAccepted ? 0 : $receiving['volume_liters'],
                    $receivingId
                ]);
                
                // If accepted, add to raw milk inventory (using revised schema)
                if ($isAccepted) {
                    $batchCode = 'RAW-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                    $expiryDate = date('Y-m-d', strtotime('+2 days'));
                    $invStmt = $db->prepare("
                        INSERT INTO raw_milk_inventory (
                            batch_code, receiving_id, qc_test_id, milk_type_id, tank_id,
                            volume_liters, remaining_liters, received_date, expiry_date,
                            fat_percentage, grade, unit_cost, status, qc_status, received_by
                        ) VALUES (?, ?, ?, ?, NULL, ?, ?, CURDATE(), ?, ?, ?, ?, 'available', 'approved', ?)
                    ");
                    $invStmt->execute([
                        $batchCode,
                        $receivingId,
                        $testId,
                        $receiving['milk_type_id'],
                        $receiving['volume_liters'],
                        $receiving['volume_liters'],
                        $expiryDate,
                        $fatPercentage,
                        $milkGrade,
                        $finalPricePerLiter,
                        $currentUser['user_id']
                    ]);
                }
                
                $db->commit();
                
                // Log audit
                if (function_exists('logAudit')) {
                    logAudit($currentUser['user_id'], 'CREATE', 'qc_milk_tests', $testId, null, [
                        'test_code' => $testCode,
                        'receiving_id' => $receivingId,
                        'is_accepted' => $isAccepted,
                        'final_price_per_liter' => $finalPricePerLiter,
                        'total_amount' => $totalAmount
                    ]);
                }
                
                // Get created test with all details (using milk_receiving - revised schema)
                $stmt = $db->prepare("
                    SELECT qmt.*, 
                           mr.receiving_code, mr.volume_liters, mr.receiving_date, mr.rmr_number,
                           f.farmer_code, COALESCE(f.first_name, '') as farmer_name
                    FROM qc_milk_tests qmt
                    LEFT JOIN milk_receiving mr ON qmt.receiving_id = mr.id
                    LEFT JOIN farmers f ON mr.farmer_id = f.id
                    WHERE qmt.id = ?
                ");
                $stmt->execute([$testId]);
                $test = $stmt->fetch();
                
                Response::success($test, 'Milk graded successfully', 201);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Milk Grading API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
