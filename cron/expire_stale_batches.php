<?php
/**
 * Highland Fresh — Daily Stock Housekeeping
 *
 * Replaces the MySQL EVENT approach that the XAMPP MariaDB build rejects
 * with "Incorrect file format 'event'". Does the same job in pure PHP:
 *   1. Flip raw_milk_inventory rows past their expiry to 'expired'
 *   2. Flip ingredient_batches rows past their expiry to 'expired'
 *   3. Recompute ingredients.current_stock from surviving batches
 *
 * Three ways to run it:
 *   - Browser:        visit /cron/expire_stale_batches.php?key=<CRON_KEY>
 *   - CLI / cron:     php /path/to/cron/expire_stale_batches.php
 *   - Windows Task:   schtasks /create /tn "Highland Fresh Housekeeping"
 *                       /tr "C:\xampp\php\php.exe C:\xampp\htdocs\HighlandFreshAppV4\cron\expire_stale_batches.php"
 *                       /sc daily /st 02:00
 *
 * The CRON_KEY protects the browser endpoint. CLI calls don't need it.
 * In production, the key should live in a .env file; here it's a constant
 * in config/cron_key.php so the file can be edited without restarting the
 * server.
 *
 * @version 4.0
 */

if (PHP_SAPI === 'cli') {
    define('CRON_CONTEXT', true);
    // bootstrap.php expects HTTP-style $_SERVER keys. Set them BEFORE
    // the require_once so the warnings don't fire.
    $_SERVER['REQUEST_METHOD']    = 'CLI';
    $_SERVER['HTTP_HOST']         = 'localhost';
    $_SERVER['SERVER_NAME']       = 'localhost';
    $_SERVER['REMOTE_ADDR']       = '127.0.0.1';
    $_SERVER['REQUEST_URI']       = '/cron/expire_stale_batches.php';
} else {
    // Browser mode: require the key.
    $cronKeyFile = dirname(__DIR__) . '/config/cron_key.php';
    $expectedKey = file_exists($cronKeyFile) ? require $cronKeyFile : 'dev-cron-key';
    $provided = $_GET['key'] ?? '';
    if (!hash_equals($expectedKey, $provided)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

require_once dirname(__DIR__) . '/api/bootstrap.php';

$db = Database::getInstance()->getConnection();
$ranAt = date('Y-m-d H:i:s');
$rawMilkFlipped = 0;
$ingredientFlipped = 0;
$ingredientsRecomputed = 0;

// 1. Flip expired raw_milk_inventory rows
try {
    $stmt = $db->prepare("
        UPDATE raw_milk_inventory
        SET status = 'expired'
        WHERE expiry_date IS NOT NULL
          AND expiry_date < CURDATE()
          AND status NOT IN ('expired', 'depleted')
    ");
    $stmt->execute();
    $rawMilkFlipped = $stmt->rowCount();
} catch (Exception $e) {
    error_log("expire_stale_batches: raw_milk update failed: " . $e->getMessage());
}

// 2. Flip expired ingredient_batches rows
try {
    $stmt = $db->prepare("
        UPDATE ingredient_batches
        SET status = 'expired'
        WHERE expiry_date IS NOT NULL
          AND expiry_date < CURDATE()
          AND status NOT IN ('expired', 'consumed', 'returned')
    ");
    $stmt->execute();
    $ingredientFlipped = $stmt->rowCount();
} catch (Exception $e) {
    error_log("expire_stale_batches: ingredient_batches update failed: " . $e->getMessage());
}

// 3. Recompute current_stock for any ingredient whose batches were touched.
//    (Doing all ingredients is cheap; index on ingredient_batches.ingredient_id.)
try {
    $stmt = $db->prepare("
        UPDATE ingredients i
        SET current_stock = COALESCE((
            SELECT SUM(ib.remaining_quantity)
            FROM ingredient_batches ib
            WHERE ib.ingredient_id = i.id
              AND ib.status NOT IN ('expired', 'consumed', 'returned')
              AND ib.remaining_quantity > 0
        ), 0)
    ");
    $stmt->execute();
    $ingredientsRecomputed = $stmt->rowCount();
} catch (Exception $e) {
    error_log("expire_stale_batches: ingredients current_stock update failed: " . $e->getMessage());
}

// 4. Record the run in audit_logs. The audit_logs hash chain will break
//    if we insert an entry with a missing prev_hash, so we leave prev_hash
//    / entry_hash NULL — this is a system-generated row, not an
//    application-level action.
try {
    $stmt = $db->prepare("
        INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, created_at)
        VALUES (1, 'cron_expire_stale_batches', 'system', NULL, ?, NOW())
    ");
    $stmt->execute([json_encode([
        'ran_at' => $ranAt,
        'raw_milk_flipped' => $rawMilkFlipped,
        'ingredient_batches_flipped' => $ingredientFlipped,
        'ingredients_recomputed' => $ingredientsRecomputed,
        'php_sapi' => PHP_SAPI,
    ])]);
} catch (Exception $e) {
    // Non-fatal; the housekeeping itself succeeded
    error_log("expire_stale_batches: audit log insert failed: " . $e->getMessage());
}

// 5. Output
$summary = [
    'ran_at' => $ranAt,
    'raw_milk_flipped' => $rawMilkFlipped,
    'ingredient_batches_flipped' => $ingredientFlipped,
    'ingredients_recomputed' => $ingredientsRecomputed,
];

if (PHP_SAPI === 'cli') {
    echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
} else {
    header('Content-Type: application/json');
    echo json_encode($summary, JSON_PRETTY_PRINT);
}
