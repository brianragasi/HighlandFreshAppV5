<?php
/**
 * Test Farmers API - Debug Script
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Test database connection
try {
    $host = 'localhost';
    $dbname = 'highland_fresh';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Check if farmers table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'farmers'")->fetchAll();
    
    if (count($tables) === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Farmers table does not exist',
            'message' => 'You need to run import_rawmilk.php first to create the farmers table and import data'
        ]);
        exit;
    }
    
    // Check farmer count
    $count = $pdo->query("SELECT COUNT(*) as total FROM farmers")->fetch();
    
    // Get all farmers
    $stmt = $pdo->query("SELECT id, farmer_code, first_name, last_name, membership_type, base_price_per_liter, is_active FROM farmers ORDER BY farmer_code");
    $farmers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'farmers_table_exists' => true,
        'total_farmers' => $count['total'],
        'farmers' => $farmers
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
