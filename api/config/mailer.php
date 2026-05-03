<?php
/**
 * Highland Fresh System - SMTP Email Helper
 *
 * Lightweight SMTP mailer using PHP sockets for Gmail.
 * No external dependencies required.
 *
 * @package HighlandFresh
 * @version 4.0
 */

// Prevent direct access
if (!defined('HIGHLAND_FRESH')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

class Mailer {

    /**
     * Send an email via SMTP (Gmail)
     *
     * @param string $to        Recipient email
     * @param string $subject   Email subject
     * @param string $htmlBody  HTML body
     * @param string $textBody  Plain-text fallback (optional)
     * @return bool             True on success
     * @throws Exception        On failure
     */
    public static function send($to, $subject, $htmlBody, $textBody = '') {
        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $username = SMTP_USERNAME;
        $password = SMTP_PASSWORD;
        $fromEmail = SMTP_FROM_EMAIL;
        $fromName = SMTP_FROM_NAME;

        if (empty($password)) {
            throw new Exception('SMTP password (Gmail App Password) is not configured. Set SMTP_PASSWORD environment variable.');
        }

        // Connect
        $socket = @fsockopen($host, $port, $errno, $errstr, 15);
        if (!$socket) {
            throw new Exception("SMTP connection failed: {$errstr} ({$errno})");
        }

        // Set timeout
        stream_set_timeout($socket, 30);

        // Read greeting
        self::readResponse($socket, 220);

        // EHLO
        self::sendCommand($socket, "EHLO " . gethostname(), 250);

        // STARTTLS
        if (SMTP_ENCRYPTION === 'tls') {
            self::sendCommand($socket, "STARTTLS", 220);
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
            if (!$crypto) {
                throw new Exception('Failed to enable TLS encryption');
            }
            // Re-EHLO after TLS
            self::sendCommand($socket, "EHLO " . gethostname(), 250);
        }

        // AUTH LOGIN
        self::sendCommand($socket, "AUTH LOGIN", 334);
        self::sendCommand($socket, base64_encode($username), 334);
        self::sendCommand($socket, base64_encode($password), 235);

        // MAIL FROM
        self::sendCommand($socket, "MAIL FROM:<{$fromEmail}>", 250);

        // RCPT TO
        self::sendCommand($socket, "RCPT TO:<{$to}>", 250);

        // DATA
        self::sendCommand($socket, "DATA", 354);

        // Build message
        $boundary = 'boundary_' . md5(uniqid(mt_rand(), true));
        $date = date('r');
        $messageId = '<' . md5(uniqid(mt_rand(), true)) . '@' . parse_url(APP_URL, PHP_URL_HOST) . '>';

        $headers = [];
        $headers[] = "Date: {$date}";
        $headers[] = "From: {$fromName} <{$fromEmail}>";
        $headers[] = "To: {$to}";
        $headers[] = "Subject: {$subject}";
        $headers[] = "Message-ID: {$messageId}";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

        $body = implode("\r\n", $headers) . "\r\n\r\n";

        // Plain text part
        if (empty($textBody)) {
            $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
        }
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($textBody) . "\r\n\r\n";

        // HTML part
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($htmlBody) . "\r\n\r\n";

        $body .= "--{$boundary}--\r\n";

        // Escape dots at start of lines (SMTP transparency)
        $body = str_replace("\r\n.\r\n", "\r\n..\r\n", $body);

        // Send message data
        fwrite($socket, $body . "\r\n.\r\n");
        self::readResponse($socket, 250);

        // QUIT
        self::sendCommand($socket, "QUIT", 221);

        fclose($socket);
        return true;
    }

    /**
     * Send SMTP command and check response
     */
    private static function sendCommand($socket, $command, $expectedCode) {
        fwrite($socket, $command . "\r\n");
        return self::readResponse($socket, $expectedCode);
    }

    /**
     * Read SMTP response and verify status code
     */
    private static function readResponse($socket, $expectedCode) {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            // Multi-line responses have a dash after the code, final line has a space
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
            // Also break if line is short (edge case)
            if (strlen($line) < 4) {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new Exception("SMTP error: expected {$expectedCode}, got {$code}. Response: " . trim($response));
        }

        return $response;
    }

    /**
     * Build a styled HTML email for Highland Fresh
     *
     * @param string $title       Email title/heading
     * @param string $bodyHtml    Inner HTML content
     * @param string $footerText  Optional footer override
     * @return string             Full HTML email
     */
    public static function buildTemplate($title, $bodyHtml, $footerText = null) {
        $appName = APP_NAME;
        $year = date('Y');
        $footer = $footerText ?: "&copy; {$year} {$appName}. All rights reserved.";

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f5f7;">
        <tr>
            <td align="center" style="padding:40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background-color:#ffffff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.08);overflow:hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg,#2b7a3e 0%,#3da553 100%);padding:32px 40px;text-align:center;">
                            <h1 style="color:#ffffff;margin:0;font-size:24px;font-weight:700;letter-spacing:-0.5px;">
                                🥛 {$appName}
                            </h1>
                            <p style="color:rgba(255,255,255,0.8);margin:4px 0 0;font-size:13px;">
                                Dairy Production System
                            </p>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding:40px;">
                            <h2 style="color:#1a1a2e;margin:0 0 20px;font-size:20px;font-weight:600;">
                                {$title}
                            </h2>
                            {$bodyHtml}
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f8f9fa;padding:24px 40px;text-align:center;border-top:1px solid #e9ecef;">
                            <p style="color:#6c757d;margin:0;font-size:12px;">
                                {$footer}
                            </p>
                            <p style="color:#adb5bd;margin:8px 0 0;font-size:11px;">
                                This is an automated message. Please do not reply directly to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
