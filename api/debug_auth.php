<?php
/**
 * TEMPORARY DEBUG ENDPOINT — DELETE AFTER DEBUGGING
 * Walks through each verifyToken() check one at a time and reports
 * which one returns false (or where the chain breaks).
 */
define('HIGHLAND_FRESH', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=UTF-8');

// Build the headers the same way Auth::extractBearerToken does
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $name = str_replace('_', '-', substr($key, 5));
        $name = ucwords(strtolower($name), '-');
        $headers[$name] = $value;
    } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
        $headers[str_replace('_', '-', $key)] = $value;
    }
}
if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

echo "=== Auth Debug Endpoint ===\n\n";
echo "Request method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "Authorization header present: " . (isset($headers['Authorization']) ? 'YES' : 'NO') . "\n";
if (isset($headers['Authorization'])) {
    $ah = $headers['Authorization'];
    echo "Authorization value (first 60 chars): " . substr($ah, 0, 60) . (strlen($ah) > 60 ? '...' : '') . "\n";
    echo "Authorization length: " . strlen($ah) . "\n";
}

$token = Auth::extractBearerToken();
echo "\n--- extractBearerToken() ---\n";
echo "Token extracted: " . ($token ? 'YES (len=' . strlen($token) . ')' : 'NO') . "\n";
if ($token) {
    echo "Token first 60: " . substr($token, 0, 60) . "...\n";
    echo "Token has 3 parts: " . (count(explode('.', $token)) === 3 ? 'YES' : 'NO') . "\n";
}

if (!$token) {
    echo "\n→ STOP: No bearer token. requireAuth() will return 401.\n";
    exit;
}

// Manually walk through verifyToken
echo "\n--- verifyToken() step by step ---\n";

$parts = explode('.', $token);
if (count($parts) !== 3) {
    echo "STRUCTURE CHECK: FAIL (not 3 parts)\n";
    exit;
}
echo "STRUCTURE CHECK: PASS (3 parts)\n";

list($base64Header, $base64Payload, $base64Signature) = $parts;

$signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
$expectedSignature = Auth::class . '::base64UrlEncode';  // can't call private, just for show
// Use reflection
$reflMethod = (new ReflectionClass('Auth'))->getMethod('base64UrlEncode');
$reflMethod->setAccessible(true);
$expectedSignature = $reflMethod->invoke(null, $signature);

if (!hash_equals($expectedSignature, $base64Signature)) {
    echo "SIGNATURE CHECK: FAIL (signature mismatch — token may have been tampered with, or JWT_SECRET differs between envs)\n";
    echo "  expected: " . substr($expectedSignature, 0, 20) . "...\n";
    echo "  got:      " . substr($base64Signature, 0, 20) . "...\n";
    exit;
}
echo "SIGNATURE CHECK: PASS\n";

$reflDecode = (new ReflectionClass('Auth'))->getMethod('base64UrlDecode');
$reflDecode->setAccessible(true);
$payload = json_decode($reflDecode->invoke(null, $base64Payload), true);

echo "PAYLOAD DECODE: " . (is_array($payload) ? 'PASS' : 'FAIL') . "\n";
if (is_array($payload)) {
    echo "  sid:      " . substr($payload['sid'] ?? '(none)', 0, 20) . "... (len=" . strlen($payload['sid'] ?? '') . ")\n";
    echo "  user_id:  " . ($payload['user_id'] ?? '(none)') . "\n";
    echo "  iat:      " . ($payload['iat'] ?? '(none)') . " (" . date('Y-m-d H:i:s', $payload['iat'] ?? 0) . ")\n";
    echo "  exp:      " . ($payload['exp'] ?? '(none)') . " (" . date('Y-m-d H:i:s', $payload['exp'] ?? 0) . ")\n";
    echo "  now:      " . time() . " (" . date('Y-m-d H:i:s') . ")\n";
    echo "  exp - now: " . (($payload['exp'] ?? 0) - time()) . " sec\n";
}

if (!is_array($payload) || !isset($payload['exp']) || $payload['exp'] < time()) {
    echo "EXPIRATION CHECK: FAIL (exp < now, or payload missing)\n";
    exit;
}
echo "EXPIRATION CHECK: PASS\n";

if (empty($payload['sid']) || empty($payload['user_id'])) {
    echo "SID/USER_ID CHECK: FAIL (one is empty)\n";
    exit;
}
echo "SID/USER_ID CHECK: PASS\n";

// Now the critical one — validateServerSession
echo "\n--- validateServerSession() — checking DB ---\n";
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT id, session_id, user_id, last_activity, expires_at, revoked_at, revoked_reason
        FROM auth_sessions
        WHERE session_id = ? AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$payload['sid'], (int) $payload['user_id']]);
    $session = $stmt->fetch();
    echo "DB session row found: " . ($session ? 'YES' : 'NO (THIS IS THE BUG)') . "\n";
    if ($session) {
        echo "  session_id in DB: " . substr($session['session_id'], 0, 20) . "...\n";
        echo "  user_id in DB:    " . $session['user_id'] . "\n";
        echo "  last_activity:    " . $session['last_activity'] . "\n";
        echo "  expires_at:       " . $session['expires_at'] . "\n";
        echo "  revoked_at:       " . ($session['revoked_at'] ?? '(NULL — good)') . "\n";
        echo "  revoked_reason:   " . ($session['revoked_reason'] ?? '(NULL)') . "\n";

        if (!empty($session['revoked_at'])) {
            echo "→ SESSION IS REVOKED. That's why verifyToken returns false.\n";
            exit;
        }
    } else {
        echo "\n  Querying with sid: '" . $payload['sid'] . "' (len=" . strlen($payload['sid']) . ")\n";
        echo "  Querying with user_id: " . (int) $payload['user_id'] . "\n";
        echo "→ Session row NOT in DB. That means the session was never created, or was deleted, or the sid/user_id in the token don't match the DB.\n";
    }
} catch (Exception $e) {
    echo "DB ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- Final result ---\n";
$result = Auth::verifyToken($token);
echo "verifyToken() returns: " . ($result ? 'TRUE (valid)' : 'FALSE (invalid)') . "\n";
