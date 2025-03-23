<?php
/**
 * Common helper functions for the application
 */

if (!function_exists('generateCsrfToken')) {
    /**
     * Generate a CSRF token and store it in the session
     * 
     * @return string The generated CSRF token
     */
    function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCsrfToken')) {
    /**
     * Verify if the provided CSRF token matches the one in the session
     * 
     * @param string $token The token to verify
     * @return bool Whether the token is valid
     */
    function verifyCsrfToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            error_log('CSRF token empty - Session token: ' . (isset($_SESSION['csrf_token']) ? 'set' : 'not set') . ', Provided token: ' . (empty($token) ? 'empty' : 'provided'));
            return false;
        }
        
        $result = hash_equals($_SESSION['csrf_token'], $token);
        if (!$result) {
            error_log('CSRF token mismatch - Session: ' . substr($_SESSION['csrf_token'], 0, 8) . '... vs Provided: ' . substr($token, 0, 8) . '...');
        }
        return $result;
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect to a URL
     * 
     * @param string $path The path to redirect to
     * @return void
     */
    function redirect($path) {
        header("Location: " . $path);
        exit();
    }
}

if (!function_exists('sanitizeInput')) {
    /**
     * Sanitize user input to prevent XSS
     * 
     * @param mixed $input The input to sanitize
     * @return mixed The sanitized input
     */
    function sanitizeInput($input) {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$key] = sanitizeInput($value);
            }
            return $input;
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
} 