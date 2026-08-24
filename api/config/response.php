<?php
/**
 * Highland Fresh System - Response Helper
 * 
 * Standardized JSON response format
 * 
 * @package HighlandFresh
 * @version 4.0
 */

// Prevent direct access
if (!defined('HIGHLAND_FRESH')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

class Response {

    /**
     * Convert internal database/runtime errors into messages that are safe and
     * useful to show to an employee. The original detail stays in the PHP log.
     */
    public static function safeErrorMessage($message, $code = 400) {
        $message = trim((string) $message);
        $technicalPatterns = [
            '/SQLSTATE/i',
            '/PDOException/i',
            '/Duplicate entry/i',
            '/Integrity constraint/i',
            '/foreign key constraint/i',
            '/Unknown column/i',
            '/Base table or view/i',
            '/Table [^\n]+ doesn[\x27\x{2019}]?t exist/iu',
            '/Column [^\n]+ cannot be null/i',
            '/SQL syntax/i',
            '/Stack trace/i',
            '/Call to undefined/i',
            '/Undefined (array key|variable|index)/i',
            '/in [A-Z]:\\\\[^\n]+ on line \d+/i',
            '/in \/[^\n]+ on line \d+/i'
        ];

        $isTechnical = false;
        foreach ($technicalPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                $isTechnical = true;
                break;
            }
        }

        if ($code >= 500 || $isTechnical) {
            self::logInternalError($message, $code);

            if ((int) $code === 409 || stripos($message, 'duplicate') !== false) {
                return 'This record already exists or conflicts with existing data. Check the entered information and try again.';
            }

            if ((int) $code === 400 || (int) $code === 422) {
                return 'The request could not be saved. Check the entered information and try again.';
            }

            return 'Something went wrong while processing the request. Please try again.';
        }

        return $message !== '' ? $message : 'The request could not be completed.';
    }

    private static function logInternalError($message, $code) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        error_log(sprintf(
            '[HighlandFresh API %d] %s %s: %s',
            (int) $code,
            $method,
            $uri,
            $message !== '' ? $message : 'No error detail provided'
        ));
    }
    
    /**
     * Send success response
     */
    public static function success($data = null, $message = 'Success', $code = 200) {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Send error response
     */
    public static function error($message = 'Error', $code = 400, $errors = null) {
        $safeMessage = self::safeErrorMessage($message, $code);
        if ((int) $code >= 500) {
            $errors = null;
        }
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $safeMessage,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Send paginated response
     */
    public static function paginated($data, $total, $page, $limit, $message = 'Success') {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'total' => (int) $total,
                'page' => (int) $page,
                'limit' => (int) $limit,
                'total_pages' => ceil($total / $limit)
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Send validation error
     */
    public static function validationError($errors) {
        self::error('Validation failed', 422, $errors);
    }
    
    /**
     * Send unauthorized error
     */
    public static function unauthorized($message = 'Unauthorized access') {
        self::error($message, 401);
    }
    
    /**
     * Send forbidden error
     */
    public static function forbidden($message = 'Access forbidden') {
        self::error($message, 403);
    }
    
    /**
     * Send not found error
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }
    
    /**
     * Send created response (201)
     */
    public static function created($data = null, $message = 'Created successfully') {
        self::success($data, $message, 201);
    }

    /**
     * Stream a file as the response (used for inline evidence photos).
     * Drops the default JSON Content-Type set by the bootstrap.
     */
    public static function file($absolutePath, $mime = 'application/octet-stream', $disposition = 'inline') {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            self::notFound('File not found');
        }
        if (function_exists('http_response_code')) {
            http_response_code(200);
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($absolutePath));
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($absolutePath) . '"');
        header('Cache-Control: private, max-age=300');
        header('X-Content-Type-Options: nosniff');
        readfile($absolutePath);
        exit;
    }
}
