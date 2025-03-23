<?php
/**
 * Application configuration file
 */

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1); // Set to 0 in production, 1 in development
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__, 2) . '/php_error.log');

// Define base constants
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}

if (!defined('BASE_URL')) {
    // Auto-detect the base URL
    $base_dir = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', ROOT_DIR);
    $base_url = '//' . $_SERVER['HTTP_HOST'] . $base_dir;
    if ($base_dir == ROOT_DIR) {
        // If we couldn't detect it, use a default
        $base_url = '//' . $_SERVER['HTTP_HOST'] . '/WebCourses/';
    }
    define('BASE_URL', rtrim($base_url, '/') . '/');
}

// Define application directories
define('APP_DIR', ROOT_DIR . '/app');
define('CONFIG_DIR', APP_DIR . '/config');
define('CONTROLLERS_DIR', APP_DIR . '/controllers');
define('MODELS_DIR', APP_DIR . '/models');
define('VIEWS_DIR', APP_DIR . '/views');
define('UPLOADS_DIR', ROOT_DIR . '/public/uploads');

// Include helper functions
require_once APP_DIR . '/helpers/functions.php';

// Session configuration
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
} 