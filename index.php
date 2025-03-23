<?php
// Include configuration and helper functions
require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/config/connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token for this request if needed
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateCsrfToken();
}

// Xử lý lỗi toàn cục
set_error_handler(function ($severity, $message, $file, $line) {
    // Log the error
    error_log("$message in $file on line $line");
    
    // For JSON API requests, return errors as JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => "$message in $file on line $line"]);
        exit();
    }
    
    // For regular requests, store error in session and redirect
    $_SESSION['error'] = "$message in $file on line $line";
    header("Location: " . BASE_URL . "app/views/product/error.php");
    exit();
});

set_exception_handler(function ($exception) {
    // Log the error
    error_log($exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine());
    
    // For JSON API requests, return errors as JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $exception->getMessage()]);
        exit();
    }
    
    // Store error in session and redirect
    $_SESSION['error'] = $exception->getMessage();
    header("Location: " . BASE_URL . "app/views/product/error.php");
    exit();
});

// Phân tích tham số url để xác định controller và action
$url = $_GET['url'] ?? '';
$urlParts = explode('/', trim($url, '/'));

// SIMPLIFIED DIRECT VIEW FILE HANDLING
// Check if this is a direct request for a view in the product directory
if (strpos($url, 'app/views/product/') === 0 || 
    (isset($urlParts[0]) && $urlParts[0] === 'app' && 
     isset($urlParts[1]) && $urlParts[1] === 'views' && 
     isset($urlParts[2]) && $urlParts[2] === 'product')) {
    
    // Extract the view name from the URL
    if (strpos($url, 'app/views/product/') === 0) {
        $viewName = substr($url, strlen('app/views/product/'));
    } else {
        // Skip app/views/product/ parts
        array_shift($urlParts); // Remove 'app'
        array_shift($urlParts); // Remove 'views'
        array_shift($urlParts); // Remove 'product'
        $viewName = implode('/', $urlParts);
    }
    
    // Remove .php extension if present
    $viewName = str_replace('.php', '', $viewName);
    
    // Default to home if empty
    if (empty($viewName)) {
        $viewName = 'home';
    }
    
    // Build the path to the view file
    $viewFile = __DIR__ . '/app/views/product/' . $viewName . '.php';
    
    // Check if the view exists
    if (file_exists($viewFile)) {
        // Set up CSRF token for JavaScript
        if (!headers_sent()) {
            echo "<script>window.CSRF_TOKEN = '" . ($_SESSION['csrf_token'] ?? generateCsrfToken()) . "';</script>";
            echo "<script>window.BASE_URL = '" . BASE_URL . "';</script>";
        }
        
        // Include the view
        include $viewFile;
        exit;
    } else {
        // Try case-insensitive match (for Windows)
        $productDir = __DIR__ . '/app/views/product/';
        if (is_dir($productDir)) {
            $files = scandir($productDir);
            foreach ($files as $file) {
                if (strtolower(pathinfo($file, PATHINFO_FILENAME)) === strtolower($viewName)) {
                    include $productDir . $file;
                    exit;
                }
            }
        }
        
        // If view file doesn't exist, redirect to error
        $_SESSION['error'] = "View not found: " . htmlspecialchars($viewName);
        header("Location: " . BASE_URL . "app/views/product/error.php");
        exit;
    }
}

// Process controllers if not a direct view request
$controller = !empty($urlParts[0]) ? $urlParts[0] : 'home';
$action = !empty($urlParts[1]) ? $urlParts[1] : 'index';

// Special case for direct access to auth.php or logout.php
if (strpos($url, 'app/controllers/auth.php') !== false || strpos($url, 'app/controllers/logout.php') !== false) {
    $scriptFile = __DIR__ . '/' . $url;
    if (file_exists($scriptFile)) {
        error_log("Direct access to controller script: " . $scriptFile);
        include $scriptFile;
        exit;
    }
}

// Special case for 'views' controller
if ($controller === 'views') {
    $controllerClass = 'ViewsController';
    $controllerFile = __DIR__ . '/app/controllers/' . $controllerClass . '.php';
    
    if (file_exists($controllerFile)) {
        require_once $controllerFile;
        $controllerObj = new ViewsController($conn);
        $controllerObj->index();
        exit;
    }
}

// Regular controller handling
// Đảm bảo controller và action không chứa ký tự không hợp lệ
$controller = preg_replace('/[^a-zA-Z0-9]/', '', $controller);
$action = preg_replace('/[^a-zA-Z0-9]/', '', $action);

// Format controller name properly and generate the file path
// Remove any 'php' string from controller name to prevent ErrorphpController issues
$controller = str_replace('php', '', $controller);
$controllerClass = ucfirst($controller) . 'Controller';
$controllerFile = __DIR__ . '/app/controllers/' . $controllerClass . '.php';

// Check if controller file exists
if (!file_exists($controllerFile)) {
    error_log("File controller '{$controllerClass}.php' không tồn tại.");
    
    // Try fallback to direct controller name (for legacy code)
    $legacyControllerFile = __DIR__ . '/app/controllers/' . $controller . '.php';
    if (file_exists($legacyControllerFile)) {
        $controllerFile = $legacyControllerFile;
        error_log("Using legacy controller file: $legacyControllerFile");
    } else {
        // Redirect to error page with message
        $_SESSION['error'] = "Controller '" . htmlspecialchars($controllerClass) . "' không tồn tại.";
        header("Location: " . BASE_URL . "app/views/product/error.php");
        exit();
    }
}

// Load the controller file
require_once $controllerFile;

// Determine which class to instantiate
if (class_exists($controllerClass)) {
    $controllerInstance = $controllerClass;
} elseif (class_exists($controller)) {
    $controllerInstance = $controller;
} else {
    throw new Exception("Controller class '{$controllerClass}' or '{$controller}' không tồn tại.");
}

// Create controller instance and call the action
$controllerObj = new $controllerInstance($conn);
if (method_exists($controllerObj, $action)) {
    // Make CSRF token available to JavaScript
    if (!headers_sent()) {
        echo "<script>window.CSRF_TOKEN = '" . ($_SESSION['csrf_token'] ?? generateCsrfToken()) . "';</script>";
        echo "<script>window.BASE_URL = '" . BASE_URL . "';</script>";
    }
    
    $controllerObj->$action();
} else {
    // Redirect to error page for missing action
    $_SESSION['error'] = "Hành động '{$action}' không tồn tại trong controller '{$controllerInstance}'.";
    header("Location: " . BASE_URL . "app/views/product/error.php");
    exit();
}