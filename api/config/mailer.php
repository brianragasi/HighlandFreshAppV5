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

        // Connect with a short timeout so UI callers (forgot-password) cannot hang
        $socket = @fsockopen($host, $port, $errno, $errstr, 8);
        if (!$socket) {
            throw new Exception("SMTP connection failed: {$errstr} ({$errno})");
        }

        // I/O timeout per read/write (seconds)
        stream_set_timeout($socket, 12);

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
        $guard = 0;
        while ($guard++ < 50) {
            $line = fgets($socket, 512);
            if ($line === false) {
                $meta = stream_get_meta_data($socket);
                if (!empty($meta['timed_out'])) {
                    throw new Exception('SMTP read timed out');
                }
                break;
            }
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

        if ($response === '') {
            throw new Exception('SMTP error: empty response from server');
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
        $appName = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');
        $titleSafe = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $year = date('Y');
        $footer = $footerText ?: "&copy; {$year} {$appName}. All rights reserved.";
        $fontStack = "system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

        // Table-based layout for Gmail / Outlook / Apple Mail compatibility.
        // Inline styles only; max-width fluid card on light gray canvas.
        return <<<HTML
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="x-apple-disable-message-reformatting" />
    <meta name="color-scheme" content="light" />
    <meta name="supported-color-schemes" content="light" />
    <title>{$titleSafe}</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td { font-family: Arial, Helvetica, sans-serif !important; }
    </style>
    <![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#F4F6F8;font-family:{$fontStack};-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">
        {$titleSafe} &mdash; Highland Fresh Dairy
    </div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#F4F6F8;margin:0;padding:0;width:100%;border-collapse:collapse;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <!-- Card container -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border:1px solid #E5E9ED;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(23,33,27,0.06);border-collapse:separate;">
                    <!-- Header -->
                    <tr>
                        <td align="center" bgcolor="#1f7a4d" style="background-color:#1f7a4d;background:linear-gradient(135deg,#1f7a4d 0%,#25965c 100%);padding:28px 32px 26px 32px;text-align:center;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;margin:0 auto;">
                                <tr>
                                    <td align="center" style="padding:0 0 10px 0;font-size:28px;line-height:1;">&#127859;</td>
                                </tr>
                                <tr>
                                    <td align="center" style="font-family:{$fontStack};color:#ffffff;font-size:22px;font-weight:700;letter-spacing:-0.3px;line-height:1.3;padding:0;">
                                        {$appName}
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="font-family:{$fontStack};color:rgba(255,255,255,0.88);font-size:13px;font-weight:500;letter-spacing:0.04em;line-height:1.4;padding:6px 0 0 0;">
                                        Dairy Operations System
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding:36px 32px 32px 32px;font-family:{$fontStack};background-color:#ffffff;">
                            <h1 style="margin:0 0 20px 0;font-family:{$fontStack};color:#17211b;font-size:22px;font-weight:700;letter-spacing:-0.3px;line-height:1.3;">
                                {$titleSafe}
                            </h1>
                            {$bodyHtml}
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color:#FAFBFC;padding:20px 28px 24px 28px;text-align:center;border-top:1px solid #EEF1F4;">
                            <p style="margin:0;font-family:{$fontStack};color:#8A9590;font-size:11px;font-weight:400;line-height:1.6;">
                                {$footer}
                            </p>
                            <p style="margin:8px 0 0 0;font-family:{$fontStack};color:#A8B2AD;font-size:11px;font-weight:400;line-height:1.5;">
                                This is an automated message. Please do not reply to this email.
                            </p>
                        </td>
                    </tr>
                </table>
                <!-- /Card -->
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
