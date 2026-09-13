<?php
/**
 * Authenticated production email diagnostics.
 *
 * GET  returns non-secret email configuration readiness.
 * POST sends one test message to the signed-in GM/Admin's registered email.
 */

require_once __DIR__ . '/../bootstrap.php';

$currentUser = Auth::requireRole(['general_manager', 'admin']);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function emailServiceReadiness(): array
{
    $issues = [];
    $transport = defined('MAIL_TRANSPORT') ? strtolower((string) constant('MAIL_TRANSPORT')) : '';
    $senderEmail = defined('SMTP_FROM_EMAIL') ? trim((string) constant('SMTP_FROM_EMAIL')) : '';

    if ($transport === '') {
        $issues[] = 'The deployed email transport setting is missing. Redeploy the latest version.';
        return [
            'configured' => false,
            'transport' => 'unknown',
            'provider' => 'Not configured',
            'sender_domain' => '',
            'issues' => $issues,
        ];
    }

    if ($transport === 'brevo_api') {
        if (!function_exists('curl_init')) {
            $issues[] = 'PHP cURL is unavailable on this host.';
        }
        if (!defined('BREVO_API_KEY') || trim((string) constant('BREVO_API_KEY')) === '') {
            $issues[] = 'BREVO_API_KEY is not configured.';
        }
        if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            $issues[] = 'The sender email address is missing or invalid.';
        }

        return [
            'configured' => $issues === [],
            'transport' => 'brevo_api',
            'provider' => 'Brevo HTTPS API',
            'sender_domain' => substr(strrchr($senderEmail, '@') ?: '', 1),
            'issues' => $issues,
        ];
    }
    if ($transport !== 'smtp') {
        $issues[] = 'MAIL_TRANSPORT must be brevo_api or smtp.';
    }
    if (!function_exists('stream_socket_client')) {
        $issues[] = 'PHP stream sockets are unavailable on this host.';
    }
    if (!function_exists('stream_socket_enable_crypto')) {
        $issues[] = 'PHP TLS support is unavailable on this host.';
    }
    if (!defined('SMTP_USERNAME') || !filter_var(constant('SMTP_USERNAME'), FILTER_VALIDATE_EMAIL)) {
        $issues[] = 'SMTP_USERNAME is missing or invalid.';
    }
    if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
        $issues[] = 'SMTP_FROM_EMAIL is missing or invalid.';
    }
    if (!defined('SMTP_PASSWORD') || constant('SMTP_PASSWORD') === '') {
        $issues[] = 'SMTP_PASSWORD is not configured.';
    }
    $smtpEncryption = defined('SMTP_ENCRYPTION') ? strtolower((string) constant('SMTP_ENCRYPTION')) : '';
    $smtpPort = defined('SMTP_PORT') ? (int) constant('SMTP_PORT') : 0;
    if (!in_array($smtpEncryption, ['tls', 'ssl'], true)) {
        $issues[] = 'Production SMTP should use STARTTLS (587) or implicit SSL (465).';
    }
    if ($smtpEncryption === 'tls' && $smtpPort !== 587) {
        $issues[] = 'STARTTLS should normally use port 587.';
    }
    if ($smtpEncryption === 'ssl' && $smtpPort !== 465) {
        $issues[] = 'Implicit SSL should normally use port 465.';
    }

    return [
        'configured' => $issues === [],
        'transport' => 'smtp',
        'provider' => 'Authenticated SMTP',
        'host' => defined('SMTP_HOST') ? constant('SMTP_HOST') : '',
        'port' => $smtpPort,
        'encryption' => $smtpEncryption,
        'tls_certificate_verification' => defined('SMTP_VERIFY_PEER') && (bool) constant('SMTP_VERIFY_PEER'),
        'sender_domain' => substr(strrchr($senderEmail, '@') ?: '', 1),
        'issues' => $issues,
    ];
}

function safeSmtpTransportDetail(Throwable $error): string
{
    $message = trim(preg_replace('/\s+/', ' ', (string) $error->getMessage()));
    if (!str_starts_with($message, 'SMTP connection failed:')) {
        return '';
    }

    // Connection error text contains route outcomes only. Still redact any
    // resolved addresses before returning it to the authenticated admin UI.
    $message = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[resolved IPv4]', $message);
    return substr($message, 0, 500);
}

if ($method === 'GET') {
    try {
        Response::success(emailServiceReadiness(), 'Email configuration inspected. No email was sent.');
    } catch (Throwable $error) {
        error_log('Email readiness check failed: ' . $error->getMessage());
        Response::error('The email readiness check could not finish. Redeploy the latest version and try again.', 424);
    }
}

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

$readiness = emailServiceReadiness();
if (!$readiness['configured']) {
    Response::error('Email service is not fully configured. Review the reported configuration issues.', 424, $readiness['issues']);
}

$db = Database::getInstance()->getConnection();
$userId = (int) ($currentUser['user_id'] ?? 0);
$emailStmt = $db->prepare('SELECT email, full_name FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
$emailStmt->execute([$userId]);
$account = $emailStmt->fetch(PDO::FETCH_ASSOC);
$recipient = trim((string) ($account['email'] ?? ''));

if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    Response::error('Your GM/Admin account needs a valid email address before a test can be sent.', 422);
}

$rateLimitKey = 'smtp_test:v2:user:' . $userId;
$limit = RateLimiter::check($rateLimitKey, 5, 600);
if (!$limit['allowed']) {
    $retryAfter = max(1, (int) $limit['retryAfter']);
    $retryMinutes = max(1, (int) ceil($retryAfter / 60));
    header('Retry-After: ' . $retryAfter);
    Response::error(
        'Email test limit reached. Try again in about ' . $retryMinutes
        . ' minute' . ($retryMinutes === 1 ? '.' : 's.'),
        429
    );
}

try {
    $sentAt = date('Y-m-d H:i:s T');
    $name = htmlspecialchars((string) ($account['full_name'] ?? 'Administrator'), ENT_QUOTES, 'UTF-8');
    $body = Mailer::buildTemplate(
        'Highland Fresh Email Test',
        '<p style="margin:0 0 16px;color:#33443a;line-height:1.6;">Hello ' . $name . ',</p>'
        . '<p style="margin:0;color:#33443a;line-height:1.6;">The live server successfully submitted this test message through the configured email service at <strong>'
        . htmlspecialchars($sentAt, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
    );
    Mailer::send($recipient, 'Highland Fresh Live Email Test', $body);

    logAudit($userId, 'SMTP_TEST_SENT', 'system', null, null, [
        'recipient_domain' => substr(strrchr($recipient, '@') ?: '', 1),
    ]);

    Response::success([
        'recipient' => preg_replace('/(^.).*(@.*$)/', '$1***$2', $recipient),
        'accepted_at' => $sentAt,
        'provider' => $readiness['provider'],
    ], 'Email service accepted the test message. Check the account inbox and spam folder.');
} catch (Throwable $error) {
    error_log('Email diagnostics failed: ' . $error->getMessage());
    logAudit($userId, 'SMTP_TEST_FAILED', 'system', null, null, [
        'error_type' => get_class($error),
    ]);

    $message = $error->getMessage();
    if (stripos($message, 'Brevo API request failed') !== false) {
        if (stripos($message, 'HTTP 401') !== false || stripos($message, 'HTTP 403') !== false) {
            Response::error('Brevo rejected the saved API key or sender. Check the API key and verify the sender in Brevo.', 424);
        }
        Response::error('Brevo rejected the test email. Check that the sender address is verified in Brevo.', 424);
    }
    if (stripos($message, 'Brevo HTTPS connection failed') !== false) {
        Response::error('The live server could not reach Brevo through HTTPS. Try again, then check the server error log.', 424);
    }
    if (stripos($message, 'connection failed') !== false || stripos($message, 'timed out') !== false) {
        $detail = safeSmtpTransportDetail($error);
        $summary = 'The live host could not connect to ' . SMTP_HOST . ':' . SMTP_PORT
            . ' using ' . strtoupper(SMTP_ENCRYPTION) . '.';
        Response::error($summary . ($detail !== '' ? ' Server detail: ' . $detail : ''), 424);
    }
    if (stripos($message, 'expected 235') !== false) {
        Response::error('The configured SMTP server rejected the login. Verify the mailbox address and password.', 424);
    }
    if (stripos($message, 'TLS') !== false || stripos($message, 'crypto') !== false) {
        Response::error('The live host could not establish a verified TLS connection to the configured SMTP server.', 424);
    }
    Response::error('The email test failed. Check the server error log for the technical detail.', 424);
}
