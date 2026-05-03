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

// Detect Azure environment
$isAzure = getenv('WEBSITE_SITE_NAME') !== false;

function loadLocalEnvFiles() {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $projectRoot = dirname(__DIR__, 2);
    $envFiles = [
        $projectRoot . '/.env',
        $projectRoot . '/.env.local',
    ];

    foreach ($envFiles as $envFile) {
        if (!is_readable($envFile)) {
            continue;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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

            if ($name === '' || getenv($name) !== false) {
                continue;
            }

            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadLocalEnvFiles();

function envOrDefault($name, $default) {
    $value = getenv($name);
    return $value === false ? $default : $value;
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
} else {
    // Local Development Configuration
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
define('JWT_SECRET', 'highland_fresh_secret_key_2024_change_in_production');
define('JWT_EXPIRY', 28800); // 8 hours in seconds
define('SESSION_IDLE_TIMEOUT', 900); // 15 minutes in seconds
define('STEP_UP_TOKEN_EXPIRY', 300); // 5 minutes in seconds
define('AUDIT_LOG_SECRET', envOrDefault('AUDIT_LOG_SECRET', JWT_SECRET));
define('PASSWORD_COST', 12);

// Email / SMTP Settings (Gmail)
define('SMTP_HOST', envOrDefault('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int) envOrDefault('SMTP_PORT', 587));
define('SMTP_USERNAME', envOrDefault('SMTP_USERNAME', 'highlandfreshdairy@gmail.com'));
define('SMTP_PASSWORD', envOrDefault('SMTP_PASSWORD', ''));  // Gmail App Password
define('SMTP_FROM_EMAIL', envOrDefault('SMTP_FROM_EMAIL', 'highlandfreshdairy@gmail.com'));
define('SMTP_FROM_NAME', envOrDefault('SMTP_FROM_NAME', 'Highland Fresh Dairy'));
define('SMTP_ENCRYPTION', envOrDefault('SMTP_ENCRYPTION', 'tls'));

// Invitation Token Settings
define('INVITE_TOKEN_EXPIRY_HOURS', 48);   // Invite links valid for 48 hours
define('TEMP_CREDENTIAL_LENGTH', 10);      // Length of auto-generated temp passwords

// Application URL (for building invite links)
if ($isAzure) {
    define('APP_URL', envOrDefault('APP_URL', 'https://highlandfresh.codes'));
} else {
    define('APP_URL', envOrDefault('APP_URL', 'http://localhost/HighlandFreshAppV4'));
}

// Business Rules
define('PASTEURIZATION_TEMP', 81.0); // Celsius
define('MAX_COOLING_TEMP', 4.0); // Celsius
define('MEMBER_PRICE', 40.00);
define('NON_MEMBER_PRICE', 38.00);

// File Upload Settings
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// Set timezone
date_default_timezone_set(APP_TIMEZONE);
