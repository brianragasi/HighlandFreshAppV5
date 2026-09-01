<?php
/**
 * Highland Fresh System - Database Configuration
 * 
 * @package HighlandFresh
 * @version 4.0
 */

// Prevent direct access
if (!defined('HIGHLAND_FRESH')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

// Detect environment
$isAzure = getenv('WEBSITE_SITE_NAME') !== false;
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isInfinityFree = (strpos($httpHost, 'infinityfree.io') !== false)
    || (strpos($httpHost, 'epizy.com') !== false)
    || (getenv('INFINITYFREE') !== false);
$isGoogieHost = str_ends_with(strtolower($httpHost), '.whf.bz')
    || (getenv('GOOGIEHOST') !== false);

function loadLocalEnvFiles() {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    // Values injected by the operating system/hosting panel must win over a
    // file. Within the selected .env file, however, use normal dotenv
    // semantics: a later duplicate replaces an earlier line. This prevents an
    // obsolete hosting block near the top of a copied .env from silently
    // overriding the current deployment values appended below it.
    $initialEnvironmentNames = [];
    $processEnvironment = getenv();
    if (is_array($processEnvironment)) {
        $initialEnvironmentNames += array_fill_keys(array_keys($processEnvironment), true);
    }
    if (is_array($_ENV)) {
        $initialEnvironmentNames += array_fill_keys(array_keys($_ENV), true);
    }
    if (is_array($_SERVER)) {
        $initialEnvironmentNames += array_fill_keys(array_keys($_SERVER), true);
    }

    // Search several candidate locations because some hosts (notably
    // InfinityFree) map the document root in a way that makes
    // dirname(__DIR__, 2) land one level above the actual project root.
    $projectRoot = dirname(__DIR__, 2);
    $accountRoot = dirname($projectRoot);
    $candidates = [
        $projectRoot . '/.env',
        $projectRoot . '/.env.local',
        $accountRoot . '/.env',
        $accountRoot . '/.env.local',
        __DIR__ . '/../.env',
        __DIR__ . '/../.env.local',
    ];

    foreach ($candidates as $envFile) {
        if (!is_readable($envFile)) {
            continue;
        }
        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            continue;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $separatorPos = strpos($line, '=');
            if ($separatorPos === false) {
                continue;
            }

            $name = trim(substr($line, 0, $separatorPos));
            $value = trim(substr($line, $separatorPos + 1));

            if ($name === '' || isset($initialEnvironmentNames[$name])) {
                continue;
            }

            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
        return; // first readable .env wins
    }
}

loadLocalEnvFiles();

function envOrDefault($name, $default) {
    // Check all three storage locations. Some shared hosts (notably
    // InfinityFree) disable putenv() for security, so the .env loader
    // falls back to populating $_ENV / $_SERVER only — envOrDefault
    // needs to look in all of them.
    $value = getenv($name);
    if ($value === false || $value === '') {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? false;
    }
    if ($value === false || $value === '') {
        $value = $default;
    }
    return $value;
}

// Database Configuration
if ($isAzure) {
    // Azure MySQL Configuration
    define('DB_HOST', envOrDefault('DB_HOST', 'localhost'));
    define('DB_NAME', envOrDefault('DB_NAME', 'highland_fresh'));
    define('DB_USER', envOrDefault('DB_USERNAME', 'root'));
    define('DB_PASS', envOrDefault('DB_PASSWORD', ''));
    define('DB_PORT', (int) envOrDefault('DB_PORT', 3306));
    define('DB_SSL_CERT', '/home/site/wwwroot/api/config/DigiCertGlobalRootCA.crt.pem');
} elseif ($isInfinityFree) {
    // InfinityFree Production Configuration
    define('DB_HOST', envOrDefault('DB_HOST', 'sql112.infinityfree.com'));
    define('DB_NAME', envOrDefault('DB_NAME', 'if0_42204813_highland_fresh'));
    define('DB_USER', envOrDefault('DB_USERNAME', 'if0_42204813'));
    // Production passwords must exist only in the host's private .env file.
    define('DB_PASS', envOrDefault('DB_PASSWORD', ''));
    define('DB_PORT', (int) envOrDefault('DB_PORT', 3306));
    define('DB_SSL_CERT', null);
} elseif ($isGoogieHost) {
    // GoogieHost serves PHP and MySQL from the same hosting account. Keep the
    // account-specific database name, user, and password in public_html/.env.
    define('DB_HOST', envOrDefault('DB_HOST', 'localhost'));
    define('DB_NAME', envOrDefault('DB_NAME', ''));
    define('DB_USER', envOrDefault('DB_USERNAME', ''));
    define('DB_PASS', envOrDefault('DB_PASSWORD', ''));
    define('DB_PORT', (int) envOrDefault('DB_PORT', 3306));
    define('DB_SSL_CERT', null);
} else {
    // Local Development Configuration (XAMPP)
    define('DB_HOST', envOrDefault('DB_HOST', 'localhost'));
    define('DB_NAME', envOrDefault('DB_NAME', 'highland_fresh'));
    define('DB_USER', envOrDefault('DB_USERNAME', 'root'));
    define('DB_PASS', envOrDefault('DB_PASSWORD', ''));
    define('DB_PORT', (int) envOrDefault('DB_PORT', 3306));
    define('DB_SSL_CERT', null);
}
define('DB_CHARSET', 'utf8mb4');
define('IS_AZURE', $isAzure);

// Application Settings
define('APP_NAME', 'Highland Fresh System');
define('APP_VERSION', '4.0');
define('APP_TIMEZONE', 'Asia/Manila');

// Security Settings
$isProductionHost = $isAzure || $isInfinityFree || $isGoogieHost;
$jwtSecret = envOrDefault(
    'JWT_SECRET',
    $isProductionHost ? '' : 'highland_fresh_local_development_secret_only'
);
if ($isProductionHost && strlen($jwtSecret) < 32) {
    throw new RuntimeException('JWT_SECRET must be set to at least 32 characters in the production .env file.');
}
define('JWT_SECRET', $jwtSecret);
define('JWT_EXPIRY', 28800); // 8 hours in seconds
define('SESSION_IDLE_TIMEOUT', 900); // 15 minutes in seconds
define('STEP_UP_TOKEN_EXPIRY', 300); // 5 minutes in seconds
$auditLogSecret = envOrDefault('AUDIT_LOG_SECRET', $isProductionHost ? '' : JWT_SECRET);
if ($isProductionHost && strlen($auditLogSecret) < 32) {
    throw new RuntimeException('AUDIT_LOG_SECRET must be set to at least 32 characters in the production .env file.');
}
if ($isProductionHost && hash_equals(JWT_SECRET, $auditLogSecret)) {
    throw new RuntimeException('AUDIT_LOG_SECRET must be different from JWT_SECRET in the production .env file.');
}
define('AUDIT_LOG_SECRET', $auditLogSecret);
define('PASSWORD_COST', 12);

// Password Policy
define('PASSWORD_MIN_LENGTH', 12);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_SPECIAL', true);
define('PASSWORD_MAX_LENGTH', 128);

// Rate Limiting
define('RATE_LIMIT_SET_PASSWORD_ATTEMPTS', 5); // Max attempts per window
define('RATE_LIMIT_SET_PASSWORD_WINDOW', 900); // 15 minutes in seconds

// Email / SMTP Settings (Gmail)
define('SMTP_HOST', envOrDefault('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int) envOrDefault('SMTP_PORT', 587));
define('SMTP_USERNAME', envOrDefault('SMTP_USERNAME', $isInfinityFree ? 'ragasibrian2@gmail.com' : 'highlandfreshdairy@gmail.com'));
define('SMTP_PASSWORD', envOrDefault('SMTP_PASSWORD', ''));
define('SMTP_FROM_EMAIL', envOrDefault('SMTP_FROM_EMAIL', $isInfinityFree ? 'ragasibrian2@gmail.com' : 'highlandfreshdairy@gmail.com'));
define('SMTP_FROM_NAME', envOrDefault('SMTP_FROM_NAME', 'Highland Fresh Dairy'));
define('SMTP_ENCRYPTION', envOrDefault('SMTP_ENCRYPTION', 'tls'));
define('SMTP_VERIFY_PEER', filter_var(
    envOrDefault('SMTP_VERIFY_PEER', $isProductionHost ? 'true' : 'false'),
    FILTER_VALIDATE_BOOLEAN
));

// Institutional customer PO inbox (POP3, read-only; messages are never deleted)
define('ORDER_MAILBOX_ENABLED', filter_var(
    envOrDefault('ORDER_MAILBOX_ENABLED', 'false'),
    FILTER_VALIDATE_BOOLEAN
));
define('ORDER_MAILBOX_HOST', envOrDefault('ORDER_MAILBOX_HOST', 'pop.gmail.com'));
define('ORDER_MAILBOX_PORT', (int) envOrDefault('ORDER_MAILBOX_PORT', 995));
define('ORDER_MAILBOX_ENCRYPTION', envOrDefault('ORDER_MAILBOX_ENCRYPTION', 'ssl'));
define('ORDER_MAILBOX_USERNAME', envOrDefault('ORDER_MAILBOX_USERNAME', SMTP_USERNAME));
define('ORDER_MAILBOX_PASSWORD', envOrDefault('ORDER_MAILBOX_PASSWORD', SMTP_PASSWORD));
define('ORDER_MAILBOX_RECENT_MODE', filter_var(
    envOrDefault('ORDER_MAILBOX_RECENT_MODE', 'false'),
    FILTER_VALIDATE_BOOLEAN
));
define('ORDER_MAILBOX_MAX_MESSAGES', max(1, min(
    100,
    (int) envOrDefault('ORDER_MAILBOX_MAX_MESSAGES', 20)
)));

// Invitation Token Settings
define('INVITE_TOKEN_EXPIRY_HOURS', 48);   // Invite links valid for 48 hours
define('TEMP_CREDENTIAL_LENGTH', 10);      // Length of auto-generated temp passwords

// Application URL (for building invite links)
if ($isAzure) {
    define('APP_URL', envOrDefault('APP_URL', 'https://highlandfresh.codes'));
} elseif ($isInfinityFree) {
    define('APP_URL', envOrDefault('APP_URL', 'http://highlandfresh.infinityfree.io'));
} elseif ($isGoogieHost) {
    define('APP_URL', envOrDefault('APP_URL', 'https://highlandfresh.whf.bz'));
} else {
    define('APP_URL', envOrDefault('APP_URL', 'http://localhost/HighlandFreshAppV4'));
}
define('IS_INFINITYFREE', $isInfinityFree);
define('IS_GOOGIEHOST', $isGoogieHost);

// Business Rules — HTST pasteurization (production_staff.md / ccp_standards.php)
// 75°C for 15 seconds, NOT 81°C for 15 minutes
define('PASTEURIZATION_TEMP', 75.0); // Celsius
define('MAX_COOLING_TEMP', 4.0); // Celsius
define('MEMBER_PRICE', 40.00);
define('NON_MEMBER_PRICE', 38.00);

// File Upload Settings
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// Set timezone
date_default_timezone_set(APP_TIMEZONE);
