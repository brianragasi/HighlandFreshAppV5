<?php
/**
 * Authenticated production email diagnostics.
 *
 * GET  returns non-secret SMTP configuration readiness.
 * POST sends one test message to the signed-in GM/Admin's registered email.
 */

require_once __DIR__ . '/../bootstrap.php';

$currentUser = Auth::requireRole(['general_manager', 'admin']);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function smtpReadiness(): array
{
    $issues = [];
    if (!function_exists('stream_socket_client')) {
        $issues[] = 'PHP stream sockets are unavailable on this host.';
    }
    if (!function_exists('stream_socket_enable_crypto')) {
        $issues[] = 'PHP TLS support is unavailable on this host.';
    }
    if (!filter_var(SMTP_USERNAME, FILTER_VALIDATE_EMAIL)) {
        $issues[] = 'SMTP_USERNAME is missing or invalid.';
    }
    if (!filter_var(SMTP_FROM_EMAIL, FILTER_VALIDATE_EMAIL)) {
        $issues[] = 'SMTP_FROM_EMAIL is missing or invalid.';
    }
    if (SMTP_PASSWORD === '') {
        $issues[] = 'SMTP_PASSWORD is not configured.';
    }
    if (SMTP_ENCRYPTION !== 'tls') {
        $issues[] = 'Production Gmail SMTP should use STARTTLS.';
    }

    return [
        'configured' => $issues === [],
        'host' => SMTP_HOST,
        'port' => SMTP_PORT,
        'encryption' => SMTP_ENCRYPTION,
        'tls_certificate_verification' => SMTP_VERIFY_PEER,
        'sender_domain' => substr(strrchr(SMTP_FROM_EMAIL, '@') ?: '', 1),
        'issues' => $issues,
    ];
}

if ($method === 'GET') {
    Response::success(smtpReadiness(), 'SMTP configuration inspected. No email was sent.');
}

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

$readiness = smtpReadiness();
if (!$readiness['configured']) {
    Response::error('SMTP is not fully configured. Review the reported configuration issues.', 503, $readiness['issues']);
}

$db = Database::getInstance()->getConnection();
$userId = (int) ($currentUser['user_id'] ?? 0);
$emailStmt = $db->prepare('SELECT email, full_name FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
$emailStmt->execute([$userId]);
$account = $emailStmt->fetch(PDO::FETCH_ASSOC);
$recipient = trim((string) ($account['email'] ?? ''));

if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    Response::error('Your GM/Admin account needs a valid email address before an SMTP test can be sent.', 422);
}

$limit = RateLimiter::check('smtp_test:user:' . $userId, 3, 3600);
if (!$limit['allowed']) {
    header('Retry-After: ' . (int) $limit['retryAfter']);
    Response::error('SMTP test limit reached. Try again after the rate-limit window resets.', 429);
}

try {
    $sentAt = date('Y-m-d H:i:s T');
    $name = htmlspecialchars((string) ($account['full_name'] ?? 'Administrator'), ENT_QUOTES, 'UTF-8');
    $body = Mailer::buildTemplate(
        'Highland Fresh SMTP Test',
        '<p style="margin:0 0 16px;color:#33443a;line-height:1.6;">Hello ' . $name . ',</p>'
        . '<p style="margin:0;color:#33443a;line-height:1.6;">The live server successfully authenticated with the configured SMTP account and submitted this test message at <strong>'
        . htmlspecialchars($sentAt, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
    );
    Mailer::send($recipient, 'Highland Fresh Live SMTP Test', $body);

    logAudit($userId, 'SMTP_TEST_SENT', 'system', null, null, [
        'recipient_domain' => substr(strrchr($recipient, '@') ?: '', 1),
    ]);

    Response::success([
        'recipient' => preg_replace('/(^.).*(@.*$)/', '$1***$2', $recipient),
        'accepted_at' => $sentAt,
    ], 'SMTP accepted the test email. Check the account inbox and spam folder for final delivery.');
} catch (Throwable $error) {
    error_log('SMTP diagnostics failed: ' . $error->getMessage());
    logAudit($userId, 'SMTP_TEST_FAILED', 'system', null, null, [
        'error_type' => get_class($error),
    ]);

    $message = $error->getMessage();
    if (stripos($message, 'connection failed') !== false || stripos($message, 'timed out') !== false) {
        Response::error('The live host could not reach Gmail SMTP. GoogieHost may be blocking outbound port 587.', 502);
    }
    if (stripos($message, 'expected 235') !== false) {
        Response::error('Gmail rejected the SMTP login. Verify the email address and 16-character App Password.', 502);
    }
    if (stripos($message, 'TLS') !== false || stripos($message, 'crypto') !== false) {
        Response::error('The live host could not establish a verified TLS connection to Gmail SMTP.', 502);
    }
    Response::error('The SMTP test failed. Check the server error log for the technical detail.', 502);
}
