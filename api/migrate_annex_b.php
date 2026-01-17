<?php
/**
 * Highland Fresh - Database Migration for ANNEX B Quality Control
 * 
 * This script updates the database schema to support:
 * - APT (Alcohol Precipitation Test) result
 * - Titratable Acidity (instead of pH)
 * - Sediment Grade (1, 2, 3 instead of text levels)
 * - Additional Milkosonic SL50 analyzer parameters
 * - ANNEX B pricing fields
 * 
 * Run this script once to migrate existing tables.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=UTF-8');

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'highland_fresh';

echo "<h1>Highland Fresh - ANNEX B Quality Control Migration</h1>";
echo "<p>Updating database schema for ANNEX B pricing implementation...</p>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>1. Updating milk_deliveries table</h2>";
    
    // Add apt_result column if not exists
    try {
        $pdo->exec("ALTER TABLE milk_deliveries ADD COLUMN apt_result ENUM('positive', 'negative') NULL AFTER notes");
        echo "<p style='color:green'>✓ Added apt_result column</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p style='color:orange'>⚠ apt_result column already exists</p>";
        } else {
            throw $e;
        }
    }
    
    // Add unit_price column if not exists
    try {
        $pdo->exec("ALTER TABLE milk_deliveries ADD COLUMN unit_price DECIMAL(10,2) NULL AFTER total_amount");
        echo "<p style='color:green'>✓ Added unit_price column</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p style='color:orange'>⚠ unit_price column already exists</p>";
        } else {
            echo "<p style='color:orange'>⚠ Could not add unit_price: " . $e->getMessage() . "</p>";
        }
    }
    
    // Add grade column if not exists
    try {
        $pdo->exec("ALTER TABLE milk_deliveries ADD COLUMN grade VARCHAR(20) NULL AFTER status");
        echo "<p style='color:green'>✓ Added grade column</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p style='color:orange'>⚠ grade column already exists</p>";
        } else {
            echo "<p style='color:orange'>⚠ Could not add grade: " . $e->getMessage() . "</p>";
        }
    }
    
    // Add total_amount column if not exists
    try {
        $pdo->exec("ALTER TABLE milk_deliveries ADD COLUMN total_amount DECIMAL(12,2) NULL AFTER unit_price");
        echo "<p style='color:green'>✓ Added total_amount column</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p style='color:orange'>⚠ total_amount column already exists</p>";
        } else {
            echo "<p style='color:orange'>⚠ Could not add total_amount: " . $e->getMessage() . "</p>";
        }
    }
    
    // Add rejection_reason column if not exists
    try {
        $pdo->exec("ALTER TABLE milk_deliveries ADD COLUMN rejection_reason TEXT NULL AFTER total_amount");
        echo "<p style='color:green'>✓ Added rejection_reason column</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p style='color:orange'>⚠ rejection_reason column already exists</p>";
        } else {
            echo "<p style='color:orange'>⚠ Could not add rejection_reason: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h2>2. Updating qc_milk_tests table</h2>";
    
    // Add titratable_acidity column
    try {
        $pdo->exec("ALTER TABLE qc_milk_tests ADD COLUMN titratable_acidity DECIMAL(5,4) NULL AFTER fat_percentage");
        echo "<p style='color:green'>✓ Added titratable_acidity column</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p style='color:orange'>⚠ titratable_acidity column already exists</p>";
        } else {
            throw $e;
        }
    }
    
    // Add sediment_grade column (1, 2, 3)
    try {
        $pdo->exec("ALTER TABLE qc_milk_tests ADD COLUMN sediment_grade TINYINT(1) DEFAULT 1 AFTER sediment_level");
        echo "<p style='color:green'>✓ Added sediment_grade column</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p style='color:orange'>⚠ sediment_grade column already exists</p>";
        } else {
            throw $e;
        }
    }
    
    // Add Milkosonic SL50 additional parameters
    $milkosonicColumns = [
        'salts_percentage' => 'DECIMAL(5,2) NULL',
        'total_solids_percentage' => 'DECIMAL(5,2) NULL',
        'added_water_percentage' => 'DECIMAL(5,2) NULL',
        'freezing_point' => 'DECIMAL(6,4) NULL',
        'sample_temperature' => 'DECIMAL(4,1) NULL'
    ];
    
    foreach ($milkosonicColumns as $column => $definition) {
        try {
            $pdo->exec("ALTER TABLE qc_milk_tests ADD COLUMN {$column} {$definition}");
            echo "<p style='color:green'>✓ Added {$column} column</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "<p style='color:orange'>⚠ {$column} column already exists</p>";
            } else {
                throw $e;
            }
        }
    }
    
    // Add ANNEX B pricing columns
    $pricingColumns = [
        'fat_adjustment' => 'DECIMAL(10,2) DEFAULT 0',
        'acidity_deduction' => 'DECIMAL(10,2) DEFAULT 0',
        'sediment_deduction' => 'DECIMAL(10,2) DEFAULT 0'
    ];
    
    foreach ($pricingColumns as $column => $definition) {
        try {
            $pdo->exec("ALTER TABLE qc_milk_tests ADD COLUMN {$column} {$definition}");
            echo "<p style='color:green'>✓ Added {$column} column</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "<p style='color:orange'>⚠ {$column} column already exists</p>";
            } else {
                throw $e;
            }
        }
    }
    
    // Remove old grade column if exists and add is_accepted if not exists
    try {
        $pdo->exec("ALTER TABLE qc_milk_tests MODIFY COLUMN grade VARCHAR(20) NULL");
        echo "<p style='color:green'>✓ Modified grade column to nullable</p>";
    } catch (PDOException $e) {
        echo "<p style='color:orange'>⚠ Could not modify grade column: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>3. Verifying qc_milk_tests structure</h2>";
    
    $columns = $pdo->query("DESCRIBE qc_milk_tests")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>4. Updating quality_standards table</h2>";
    
    // Check if quality_standards table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'quality_standards'");
    if ($tableCheck->rowCount() == 0) {
        // Create quality_standards table
        $pdo->exec("
            CREATE TABLE quality_standards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                parameter_name VARCHAR(50) NOT NULL,
                parameter_label VARCHAR(100) NOT NULL,
                min_value DECIMAL(10,4) NULL,
                max_value DECIMAL(10,4) NULL,
                standard_value DECIMAL(10,4) NULL,
                unit VARCHAR(20) NULL,
                rejection_threshold DECIMAL(10,4) NULL,
                is_active TINYINT(1) DEFAULT 1,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        echo "<p style='color:green'>✓ Created quality_standards table</p>";
    }
    
    // Insert/Update ANNEX B quality standards
    $standards = [
        ['titratable_acidity', 'Titratable Acidity', 0.14, 0.18, 0.16, '%', 0.25],
        ['fat_percentage', 'Butter Fat Content', 3.5, 4.0, 3.75, '%', null],
        ['specific_gravity', 'Specific Gravity', 1.025, 1.032, 1.028, '', 1.025],
        ['temperature', 'Receiving Temperature', 0, 8, 4, '°C', null],
    ];
    
    $insertStmt = $pdo->prepare("
        INSERT INTO quality_standards (parameter_name, parameter_label, min_value, max_value, standard_value, unit, rejection_threshold)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            parameter_label = VALUES(parameter_label),
            min_value = VALUES(min_value),
            max_value = VALUES(max_value),
            standard_value = VALUES(standard_value),
            unit = VALUES(unit),
            rejection_threshold = VALUES(rejection_threshold)
    ");
    
    foreach ($standards as $std) {
        $insertStmt->execute($std);
        echo "<p style='color:green'>✓ Updated standard: {$std[1]}</p>";
    }
    
    echo "<h2>Migration Complete!</h2>";
    echo "<p style='color:green; font-size: 1.2em;'>✓ Database has been updated for ANNEX B Quality Control</p>";
    
    echo "<h3>Summary of Changes:</h3>";
    echo "<ul>";
    echo "<li><strong>milk_deliveries</strong>: Added apt_result, unit_price columns</li>";
    echo "<li><strong>qc_milk_tests</strong>: Added titratable_acidity, sediment_grade, Milkosonic parameters, ANNEX B pricing columns</li>";
    echo "<li><strong>quality_standards</strong>: Updated with ANNEX B standards</li>";
    echo "</ul>";
    
    echo "<h3>ANNEX B Pricing Structure:</h3>";
    echo "<ul>";
    echo "<li>Base Price: ₱25.00 + Incentive ₱5.00 = <strong>₱30.00/L</strong></li>";
    echo "<li>Fat Adjustment: -₱1.00 to +₱2.25 based on fat %</li>";
    echo "<li>Acidity Deduction: ₱0.25 to ₱1.50 based on titratable acidity %</li>";
    echo "<li>Sediment Deduction: Grade 2 = -₱0.50, Grade 3 = -₱1.00</li>";
    echo "</ul>";
    
    echo "<h3>Rejection Criteria:</h3>";
    echo "<ul>";
    echo "<li>APT Result: Positive</li>";
    echo "<li>Titratable Acidity: ≥ 0.25%</li>";
    echo "<li>Specific Gravity: < 1.025</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
