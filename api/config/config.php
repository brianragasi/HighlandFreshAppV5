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

// Database Configuration
if ($isAzure) {
    // Azure MySQL Configuration
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_NAME', getenv('DB_NAME') ?: 'highland_fresh');
    define('DB_USER', getenv('DB_USERNAME') ?: 'root');
    define('DB_PASS', getenv('DB_PASSWORD') ?: '');
    define('DB_PORT', getenv('DB_PORT') ?: 3306);
    define('DB_SSL_CERT', '/home/site/wwwroot/api/config/DigiCertGlobalRootCA.crt.pem');
} else {
    // Local Development Configuration
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'highland_fresh');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_PORT', 3306);
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
define('PASSWORD_COST', 12);

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
