<?php
/**
 * Small security helpers used by the security unit tests.
 *
 * These functions mirror the login/security rules in a test-friendly way so
 * they can be checked without creating users or touching the database.
 */

if (!function_exists('validateEmailFormat')) {
    function validateEmailFormat($email) {
        $email = trim((string) $email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $domain = substr(strrchr($email, '@') ?: '', 1);
        if ($domain === '' || strpos($domain, '.') === false) {
            return false;
        }

        $parts = explode('.', $domain);
        $topLevelDomain = end($parts);
        return strlen($topLevelDomain) >= 2;
    }
}

if (!function_exists('validatePasswordStrength')) {
    function validatePasswordStrength($pwd) {
        $pwd = (string) $pwd;
        if (strlen($pwd) < 8 || strlen($pwd) > 128) {
            return false;
        }

        return preg_match('/[A-Z]/', $pwd) === 1
            && preg_match('/[a-z]/', $pwd) === 1
            && preg_match('/[0-9]/', $pwd) === 1
            && preg_match('/[^A-Za-z0-9]/', $pwd) === 1;
    }
}

if (!function_exists('hashUserPassword')) {
    function hashUserPassword($plainText) {
        $cost = defined('PASSWORD_COST') ? PASSWORD_COST : 12;
        return password_hash((string) $plainText, PASSWORD_BCRYPT, ['cost' => $cost]);
    }
}

if (!function_exists('verifyPasswordMatch')) {
    function verifyPasswordMatch($plain, $hash) {
        return password_verify((string) $plain, (string) $hash);
    }
}

if (!function_exists('securityUnitBase64UrlEncode')) {
    function securityUnitBase64UrlEncode($value) {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

if (!function_exists('generateSessionToken')) {
    function generateSessionToken($userId) {
        $secret = defined('JWT_SECRET') ? JWT_SECRET : 'security_unit_test_secret';
        $now = time();
        $expiry = $now + (defined('JWT_EXPIRY') ? JWT_EXPIRY : 28800);

        $header = securityUnitBase64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256',
        ]));
        $payload = securityUnitBase64UrlEncode(json_encode([
            'user_id' => (int) $userId,
            'iat' => $now,
            'exp' => $expiry,
            'nonce' => bin2hex(random_bytes(16)),
        ]));
        $signature = securityUnitBase64UrlEncode(hash_hmac('sha256', $header . '.' . $payload, $secret, true));

        return $header . '.' . $payload . '.' . $signature;
    }
}

if (!function_exists('isTokenExpired')) {
    function isTokenExpired($timestamp) {
        return (int) $timestamp < time();
    }
}

if (!function_exists('sanitizeHtmlInput')) {
    function sanitizeHtmlInput($userInput) {
        return htmlspecialchars((string) $userInput, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
