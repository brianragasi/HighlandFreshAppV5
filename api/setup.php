<?php
/**
 * Highland Fresh System - Database Setup
 * 
 * Run this once to set up the database
 * Access: http://localhost/HighlandFreshAppV4/api/setup.php
 * 
 * @package HighlandFresh
 * @version 4.0
 */

header('Content-Type: text/html; charset=UTF-8');

// Database credentials
$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'highland_fresh';

echo "<h1>Highland Fresh - Database Setup</h1>";

try {
    // Connect without database
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color:green'>✓ Database '$dbName' created or already exists</p>";
    
    // Connect to database
    $pdo->exec("USE `$dbName`");
    
    // Create audit_logs table FIRST (required for login)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `audit_logs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `action` VARCHAR(50) NOT NULL,
            `table_name` VARCHAR(50) NOT NULL,
            `record_id` INT(11) NOT NULL,
            `old_values` JSON NULL,
            `new_values` JSON NULL,
            `ip_address` VARCHAR(45) NULL,
            `user_agent` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Audit logs table ready</p>";
    
    // Check if users table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
    
    if (count($tables) == 0) {
        // Create users table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `employee_id` VARCHAR(20) NULL,
                `username` VARCHAR(50) NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `first_name` VARCHAR(100) NOT NULL,
                `last_name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(150) NULL,
                `role` ENUM('general_manager', 'qc_officer', 'production_staff', 'warehouse_raw', 'warehouse_fg', 'sales_custodian', 'cashier', 'purchaser', 'finance_officer', 'bookkeeper', 'maintenance_head') NOT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `last_login` DATETIME NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<p style='color:green'>✓ Users table created</p>";
    } else {
        echo "<p style='color:blue'>ℹ Users table already exists</p>";
        
        // Check if employee_id column exists
        $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'employee_id'")->fetchAll();
        if (count($columns) == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN `employee_id` VARCHAR(20) NULL AFTER `id`");
            echo "<p style='color:green'>✓ Added employee_id column to users table</p>";
        }
    }
    
    // Check if QC user exists
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE username = ?");
    $stmt->execute(['qc_officer']);
    $qcUser = $stmt->fetch();
    
    if (!$qcUser) {
        // Create QC officer user
        $passwordHash = password_hash('password', PASSWORD_BCRYPT, ['cost' => 12]);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (employee_id, username, password, first_name, last_name, email, role)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'EMP-002',
            'qc_officer',
            $passwordHash,
            'Maria',
            'Santos',
            'qc@highlandfresh.com',
            'qc_officer'
        ]);
        echo "<p style='color:green'>✓ QC Officer user created</p>";
    } else {
        echo "<p style='color:blue'>ℹ QC Officer user already exists (ID: {$qcUser['id']})</p>";
        
        // Update password to ensure it works
        $passwordHash = password_hash('password', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
        $stmt->execute([$passwordHash, 'qc_officer']);
        echo "<p style='color:green'>✓ QC Officer password reset to 'password'</p>";
    }
    
    // Check admin user
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $adminUser = $stmt->fetch();
    
    if (!$adminUser) {
        $passwordHash = password_hash('password', PASSWORD_BCRYPT, ['cost' => 12]);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (employee_id, username, password, first_name, last_name, email, role)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'EMP-001',
            'admin',
            $passwordHash,
            'System',
            'Administrator',
            'admin@highlandfresh.com',
            'general_manager'
        ]);
        echo "<p style='color:green'>✓ Admin user created</p>";
    } else {
        $passwordHash = password_hash('password', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
        $stmt->execute([$passwordHash, 'admin']);
        echo "<p style='color:green'>✓ Admin password reset to 'password'</p>";
    }
    
    echo "<hr>";
    echo "<h2>✓ Setup Complete!</h2>";
    echo "<h3>Login Credentials:</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Role</th><th>Username</th><th>Password</th></tr>";
    echo "<tr><td>QC Officer</td><td><strong>qc_officer</strong></td><td><strong>password</strong></td></tr>";
    echo "<tr><td>Admin / GM</td><td><strong>admin</strong></td><td><strong>password</strong></td></tr>";
    echo "</table>";
    echo "<br>";
    echo "<a href='../html/login.html' style='display:inline-block; padding:10px 20px; background:#16a34a; color:white; text-decoration:none; border-radius:5px;'>Go to Login Page →</a>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Make sure:</p>";
    echo "<ul>";
    echo "<li>XAMPP MySQL is running</li>";
    echo "<li>Database credentials in this file are correct</li>";
    echo "</ul>";
}
?>
