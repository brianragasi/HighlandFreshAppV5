<?php
/**
 * Fix Database Structure
 * Access: http://localhost/HighlandFreshAppV4/api/fix_db.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=UTF-8');

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'highland_fresh';

echo "<h1>Highland Fresh - Database Fix</h1>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fix 1: Create audit_logs table
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
    echo "<p style='color:green'>✓ audit_logs table created</p>";
    
    // Fix 2: Add missing columns to users table
    $columns = ['first_name', 'last_name', 'email', 'employee_id'];
    
    foreach ($columns as $col) {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE '$col'");
        if (!$stmt->fetch()) {
            $type = ($col === 'email') ? 'VARCHAR(150) NULL' : 'VARCHAR(100) NULL';
            $pdo->exec("ALTER TABLE users ADD COLUMN `$col` $type");
            echo "<p style='color:green'>✓ Added column: $col</p>";
        } else {
            echo "<p style='color:blue'>ℹ Column exists: $col</p>";
        }
    }
    
    // Fix 3: Update QC officer with name
    $pdo->exec("UPDATE users SET first_name = 'Maria', last_name = 'Santos' WHERE username = 'qc_officer' AND (first_name IS NULL OR first_name = '')");
    $pdo->exec("UPDATE users SET first_name = 'System', last_name = 'Admin' WHERE username = 'gm' AND (first_name IS NULL OR first_name = '')");
    $pdo->exec("UPDATE users SET first_name = 'Production', last_name = 'Staff' WHERE username = 'production' AND (first_name IS NULL OR first_name = '')");
    echo "<p style='color:green'>✓ Updated user names</p>";
    
    // Reset password for qc_officer
    $passwordHash = password_hash('password', PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->exec("UPDATE users SET password = '$passwordHash' WHERE username = 'qc_officer'");
    echo "<p style='color:green'>✓ Reset qc_officer password to 'password'</p>";
    
    echo "<hr>";
    echo "<h2 style='color:green'>✓ All Fixes Applied!</h2>";
    echo "<p><strong>Login with:</strong></p>";
    echo "<ul>";
    echo "<li>Username: <code>qc_officer</code></li>";
    echo "<li>Password: <code>password</code></li>";
    echo "</ul>";
    echo "<br>";
    echo "<a href='../html/login.html' style='display:inline-block; padding:10px 20px; background:#16a34a; color:white; text-decoration:none; border-radius:5px;'>Go to Login Page →</a>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
