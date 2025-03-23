<?php
/**
 * AppController - Base controller for the application
 * 
 * This controller serves as the parent class for all other controllers
 * and provides common functionality needed across the application.
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}

// Include configuration and helper functions
require_once ROOT_DIR . '/app/config/config.php';

class AppController {
    protected $conn;
    protected $data = [];
    
    /**
     * Constructor
     * 
     * @param object $db Database connection object
     */
    public function __construct($db = null) {
        $this->conn = $db;
        // Initialize common data for views
        $this->data['BASE_URL'] = BASE_URL;
        $this->data['page_title'] = 'Học Tập Trực Tuyến';
        $this->data['current_year'] = date('Y');
        $this->setupSession();
    }
    
    /**
     * Set up session if not already started
     */
    protected function setupSession() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Generate CSRF token if not exists
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = generateCsrfToken();
        }
        
        $this->data['csrf_token'] = $_SESSION['csrf_token'];
        $this->data['is_logged_in'] = isset($_SESSION['user_id']);
        $this->data['user_id'] = $_SESSION['user_id'] ?? null;
        $this->data['username'] = $_SESSION['username'] ?? null;
        $this->data['role'] = $_SESSION['role'] ?? null;
    }
    
    /**
     * Render a view with data
     * 
     * @param string $view Path to the view file
     * @param array $data Additional data to pass to the view
     */
    public function render($view, $data = []) {
        // Merge default data with provided data
        $viewData = array_merge($this->data, $data);
        
        // Extract variables from the array to make them accessible in the view
        extract($viewData);
        
        // Build the path to the view file
        // Always ensure the .php extension is added only once
        $view = str_replace('.php', '', $view);
        $viewPath = ROOT_DIR . '/app/views/' . $view . '.php';
        
        // Check if the view exists
        if (!file_exists($viewPath)) {
            error_log("View not found: $viewPath");
            
            // Check if there's a different case of the same file
            $dir = dirname($viewPath);
            $basename = basename($viewPath);
            $found = false;
            
            if (is_dir($dir)) {
                $files = scandir($dir);
                foreach ($files as $file) {
                    if (strtolower($file) === strtolower($basename)) {
                        $viewPath = $dir . '/' . $file;
                        $found = true;
                        error_log("Found view with different case: $viewPath");
                        break;
                    }
                }
            }
            
            if (!$found) {
                die("View not found: $viewPath");
            }
        }
        
        // Include the view
        include $viewPath;
    }
    
    /**
     * Handle 'views' action - this provides a fallback for direct view access
     */
    public function views() {
        // Get the view file path from URL segments
        $urlParts = explode('/', trim($_GET['url'] ?? '', '/'));
        
        // Remove 'views' from the parts
        if (!empty($urlParts) && $urlParts[0] === 'views') {
            array_shift($urlParts);
        }
        
        // Build the view path
        $viewPath = implode('/', $urlParts);
        
        // Default to home if no path
        if (empty($viewPath)) {
            $viewPath = 'product/home';
        }
        
        // Render the view
        $this->render($viewPath);
    }
    
    /**
     * Redirect to a given page
     * 
     * @param string $path The path to redirect to
     */
    protected function redirect($path) {
        // Reset redirect count if not set or if this is a new redirect chain
        if (!isset($_SESSION['redirect_count']) || isset($_SESSION['last_redirect']) && $_SESSION['last_redirect'] != $path) {
            $_SESSION['redirect_count'] = 0;
        }
        
        // Track the current redirect to detect loops
        $_SESSION['last_redirect'] = $path;
        
        // Increment redirect count
        $_SESSION['redirect_count'] = ($_SESSION['redirect_count'] ?? 0) + 1;
        
        // Prevent redirect loops (stop after 5 consecutive redirects to the same path)
        if ($_SESSION['redirect_count'] > 5) {
            error_log("Phát hiện vòng lặp chuyển hướng. Dừng chuyển hướng đến: " . $path);
            echo "Lỗi: Quá nhiều lần chuyển hướng. Vui lòng xóa cookie trình duyệt và thử lại.";
            exit();
        }
        
        // Check if path is a full URL
        if (strpos($path, 'http') === 0) {
            error_log("Chuyển hướng đến URL đầy đủ: " . $path);
            header("Location: " . $path);
            exit();
        }
        
        // Build the redirect URL properly
        $redirectUrl = '';
        
        // Handle different path formats
        if (strpos($path, BASE_URL) === 0) {
            // Path already includes BASE_URL
            $redirectUrl = $path;
        } else if (strpos($path, '/') === 0) {
            // Path starts with /, so it's relative to domain root
            $redirectUrl = BASE_URL . ltrim($path, '/');
        } else {
            // Path is relative, redirect to product views by default
            $redirectUrl = BASE_URL . 'app/views/product/' . $path;
        }
        
        error_log("Chuyển hướng đến: " . $redirectUrl);
        header("Location: " . $redirectUrl);
        exit();
    }
    
    /**
     * Get the POST data with proper sanitization
     * 
     * @param string $key The POST parameter key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Sanitized value or default
     */
    protected function getPostData($key, $default = null) {
        return isset($_POST[$key]) ? sanitizeInput($_POST[$key]) : $default;
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string $token Token to validate
     * @return bool Whether token is valid
     */
    protected function validateCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Check if user is logged in
     * 
     * @return bool Whether user is logged in
     */
    protected function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Check if user has specific role
     * 
     * @param string|array $roles Role(s) to check
     * @return bool Whether user has role
     */
    protected function hasRole($roles) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $roles = is_array($roles) ? $roles : [$roles];
        return in_array($_SESSION['role'], $roles);
    }
    
    /**
     * Redirect based on user role
     * 
     * @param string $role User role
     */
    protected function redirectBasedOnRole($role) {
        switch ($role) {
            case 'student':
                $this->redirect('student_dashboard.php');
                break;
            case 'instructor':
                $this->redirect('instructor_dashboard.php');
                break;
            case 'admin':
                $this->redirect('admin_dashboard.php');
                break;
            default:
                $this->redirect('home.php');
        }
    }
} 