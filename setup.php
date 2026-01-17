<?php
/**
 * Highland Fresh System - Database Setup Script
 * Run this once to initialize the database
 * Access: http://localhost/HighLandFreshAppV4/setup.php
 */

// Configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'highland_fresh';

// SQL files to import in order
$sqlFiles = [
    'database/highland_fresh.sql',
    'database/migration_warehouse_raw.sql',
    'database/migration_v4_schema_sync.sql'
];

$messages = [];
$errors = [];

// Create connection without database
try {
    $pdo = new PDO("mysql:host=$host", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $messages[] = "Connected to MySQL server successfully.";
} catch (PDOException $e) {
    $errors[] = "Connection failed: " . $e->getMessage();
    $pdo = null;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_db') {
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $messages[] = "Database '$dbname' created successfully.";
        } catch (PDOException $e) {
            $errors[] = "Failed to create database: " . $e->getMessage();
        }
    }
    
    if ($action === 'import_sql') {
        try {
            $pdo->exec("USE `$dbname`");
            
            foreach ($sqlFiles as $sqlFile) {
                $filePath = __DIR__ . '/' . $sqlFile;
                if (file_exists($filePath)) {
                    $sql = file_get_contents($filePath);
                    // Split by semicolons (basic splitting)
                    $pdo->exec($sql);
                    $messages[] = "Imported: $sqlFile";
                } else {
                    $errors[] = "File not found: $sqlFile";
                }
            }
            
            $messages[] = "All SQL files imported successfully!";
        } catch (PDOException $e) {
            $errors[] = "SQL Import Error: " . $e->getMessage();
        }
    }
    
    if ($action === 'add_users') {
        try {
            $pdo->exec("USE `$dbname`");
            
            // Hash for 'password'
            $hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
            
            $users = [
                ['EMP-004', 'production_staff', $hash, 'Ana', 'Reyes', 'production@highlandfresh.com', 'production_staff'],
                ['EMP-005', 'general_manager', $hash, 'Roberto', 'Cruz', 'gm@highlandfresh.com', 'general_manager'],
                ['EMP-006', 'warehouse_fg', $hash, 'Elena', 'Torres', 'warehouse.fg@highlandfresh.com', 'warehouse_fg'],
            ];
            
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (employee_id, username, password, first_name, last_name, email, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($users as $user) {
                $stmt->execute($user);
            }
            
            $messages[] = "Additional test users added successfully!";
        } catch (PDOException $e) {
            $errors[] = "Failed to add users: " . $e->getMessage();
        }
    }
    
    if ($action === 'check_status') {
        // Check will be done in display section
    }
}

// Check database status
$dbExists = false;
$tableCount = 0;
$userCount = 0;
$users = [];

if ($pdo) {
    try {
        $result = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
        $dbExists = $result->rowCount() > 0;
        
        if ($dbExists) {
            $pdo->exec("USE `$dbname`");
            
            // Count tables
            $result = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$dbname'");
            $tableCount = $result->fetchColumn();
            
            // Count and list users
            try {
                $result = $pdo->query("SELECT id, employee_id, username, first_name, last_name, role, is_active FROM users ORDER BY id");
                $users = $result->fetchAll(PDO::FETCH_ASSOC);
                $userCount = count($users);
            } catch (PDOException $e) {
                // Users table might not exist yet
            }
        }
    } catch (PDOException $e) {
        $errors[] = "Status check error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="emerald">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Highland Fresh - Database Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-base-200 min-h-screen p-8">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-primary mb-2">
                <i class="fas fa-database mr-2"></i>Highland Fresh - Database Setup
            </h1>
            <p class="text-base-content/70">Initialize and configure your database</p>
        </div>
        
        <!-- Messages -->
        <?php if (!empty($messages)): ?>
        <div class="alert alert-success mb-4">
            <i class="fas fa-check-circle"></i>
            <div>
                <?php foreach ($messages as $msg): ?>
                <p><?= htmlspecialchars($msg) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-4">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <?php foreach ($errors as $err): ?>
                <p><?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Status Card -->
        <div class="card bg-base-100 shadow-xl mb-6">
            <div class="card-body">
                <h2 class="card-title"><i class="fas fa-info-circle text-info mr-2"></i>Current Status</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div class="stat bg-base-200 rounded-lg">
                        <div class="stat-title">MySQL Connection</div>
                        <div class="stat-value text-lg <?= $pdo ? 'text-success' : 'text-error' ?>">
                            <?= $pdo ? 'Connected' : 'Failed' ?>
                        </div>
                    </div>
                    <div class="stat bg-base-200 rounded-lg">
                        <div class="stat-title">Database '<?= $dbname ?>'</div>
                        <div class="stat-value text-lg <?= $dbExists ? 'text-success' : 'text-warning' ?>">
                            <?= $dbExists ? 'Exists' : 'Not Found' ?>
                        </div>
                    </div>
                    <div class="stat bg-base-200 rounded-lg">
                        <div class="stat-title">Tables</div>
                        <div class="stat-value text-lg"><?= $tableCount ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Setup Actions -->
        <div class="card bg-base-100 shadow-xl mb-6">
            <div class="card-body">
                <h2 class="card-title"><i class="fas fa-cogs text-primary mr-2"></i>Setup Actions</h2>
                <p class="text-base-content/70 mb-4">Run these in order if setting up for the first time:</p>
                
                <div class="space-y-4">
                    <!-- Step 1: Create Database -->
                    <div class="flex items-center gap-4 p-4 bg-base-200 rounded-lg">
                        <div class="badge badge-primary badge-lg">1</div>
                        <div class="flex-1">
                            <h3 class="font-semibold">Create Database</h3>
                            <p class="text-sm text-base-content/70">Creates the 'highland_fresh' database if it doesn't exist</p>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="create_db">
                            <button type="submit" class="btn btn-primary btn-sm" <?= !$pdo ? 'disabled' : '' ?>>
                                <i class="fas fa-plus mr-1"></i>Create
                            </button>
                        </form>
                    </div>
                    
                    <!-- Step 2: Import SQL -->
                    <div class="flex items-center gap-4 p-4 bg-base-200 rounded-lg">
                        <div class="badge badge-primary badge-lg">2</div>
                        <div class="flex-1">
                            <h3 class="font-semibold">Import SQL Files</h3>
                            <p class="text-sm text-base-content/70">Imports schema and seed data from database/*.sql files</p>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="import_sql">
                            <button type="submit" class="btn btn-secondary btn-sm" <?= !$dbExists ? 'disabled' : '' ?>>
                                <i class="fas fa-file-import mr-1"></i>Import
                            </button>
                        </form>
                    </div>
                    
                    <!-- Step 3: Add Test Users -->
                    <div class="flex items-center gap-4 p-4 bg-base-200 rounded-lg">
                        <div class="badge badge-primary badge-lg">3</div>
                        <div class="flex-1">
                            <h3 class="font-semibold">Add Additional Test Users</h3>
                            <p class="text-sm text-base-content/70">Adds production_staff, general_manager, warehouse_fg users</p>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_users">
                            <button type="submit" class="btn btn-accent btn-sm" <?= $tableCount < 1 ? 'disabled' : '' ?>>
                                <i class="fas fa-users mr-1"></i>Add Users
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Users Table -->
        <?php if (!empty($users)): ?>
        <div class="card bg-base-100 shadow-xl mb-6">
            <div class="card-body">
                <h2 class="card-title"><i class="fas fa-users text-success mr-2"></i>System Users (<?= $userCount ?>)</h2>
                <p class="text-base-content/70 mb-4">All passwords are: <code class="bg-base-200 px-2 py-1 rounded">password</code></p>
                
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['employee_id']) ?></td>
                                <td><code class="bg-primary/10 px-2 py-1 rounded"><?= htmlspecialchars($user['username']) ?></code></td>
                                <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                <td><span class="badge badge-outline"><?= htmlspecialchars($user['role']) ?></span></td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                    <span class="badge badge-success badge-sm">Active</span>
                                    <?php else: ?>
                                    <span class="badge badge-error badge-sm">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Quick Links -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title"><i class="fas fa-link text-info mr-2"></i>Quick Links</h2>
                <div class="flex flex-wrap gap-2 mt-2">
                    <a href="html/login.html" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt mr-1"></i>Go to Login
                    </a>
                    <a href="http://localhost/phpmyadmin" target="_blank" class="btn btn-outline">
                        <i class="fas fa-external-link-alt mr-1"></i>phpMyAdmin
                    </a>
                    <button onclick="location.reload()" class="btn btn-ghost">
                        <i class="fas fa-sync-alt mr-1"></i>Refresh Status
                    </button>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-8 text-base-content/50 text-sm">
            <p>Highland Fresh System v4.0 - Setup Utility</p>
            <p class="mt-1">Delete this file (setup.php) after installation for security.</p>
        </div>
    </div>
</body>
</html>
