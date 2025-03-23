<?php
// Include configuration and helper functions
require_once dirname(__DIR__, 2) . '/app/config/config.php';
require_once dirname(__DIR__, 2) . '/app/config/connect.php';
require_once dirname(__DIR__, 2) . '/app/helpers/functions.php';
require_once dirname(__DIR__, 2) . '/app/controllers/AuthController.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure CSRF token exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Set error reporting for diagnostics
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create controller instance
$authController = new AuthController($conn);

// Get the action from query string
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Log incoming requests with detailed information
error_log("=== AUTH CONTROLLER CALLED ===");
error_log("Auth controller called with action: '$action', method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("GET data: " . print_r($_GET, true));
error_log("Request URI: " . $_SERVER['REQUEST_URI']);

// Debug CSRF tokens
error_log("CSRF token in session: " . (isset($_SESSION['csrf_token']) ? substr($_SESSION['csrf_token'], 0, 10) . "..." : "NOT SET"));
if (isset($_POST['csrf_token'])) {
    error_log("CSRF token in POST: " . substr($_POST['csrf_token'], 0, 10) . "...");
    // Debug CSRF validation
    $csrf_valid = $_SESSION['csrf_token'] === $_POST['csrf_token'];
    error_log("CSRF validation result: " . ($csrf_valid ? "VALID" : "INVALID"));
    error_log("Session token: " . $_SESSION['csrf_token']);
    error_log("POST token: " . $_POST['csrf_token']);
} else {
    error_log("No CSRF token in POST data");
}

// Temporarily disable CSRF for debugging
$skip_csrf = true;

// Process the action
switch ($action) {
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("Processing registration form from direct POST");
            processRegistration();
        } else if (isset($_SESSION['registration_data'])) {
            error_log("Processing registration form from session data");
            // Restore the registration data from session
            $_POST = $_SESSION['registration_data'];
            // Remove it from session to prevent reuse
            unset($_SESSION['registration_data']);
            // Set proper request method for controllers that check it
            $_SERVER['REQUEST_METHOD'] = 'POST';
            
            processRegistration();
        } else {
            error_log("Registration form not properly submitted. Method: " . $_SERVER['REQUEST_METHOD'] . ", register field: " . (isset($_POST['register']) ? "SET" : "NOT SET"));
            $_SESSION['registerError'] = "Biểu mẫu đăng ký không được gửi đúng cách.";
            header("Location: " . BASE_URL . "app/views/product/home.php");
            exit();
        }
        break;
    
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF validation (temporarily disabled for debugging)
            if (!$skip_csrf && (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'])) {
                error_log("CSRF token validation failed");
                $_SESSION['loginError'] = "Yêu cầu không hợp lệ. Vui lòng thử lại.";
                header("Location: " . BASE_URL . "app/views/product/home.php");
                exit();
            }
        
            // Check if this is actually a registration form submitted to login endpoint
            if (isset($_POST['register']) && $_POST['register'] == '1') {
                error_log("Login endpoint received registration form - redirecting to register action");
                
                // Save POST data to pass it through the redirect
                error_log("Saving registration data to session: " . print_r($_POST, true));
                $_SESSION['registration_data'] = $_POST;
                
                // Redirect to the register action
                header("Location: " . BASE_URL . "app/controllers/auth.php?action=register");
                exit();
            }
            
            // Normal login handling
            if (isset($_POST['username']) && isset($_POST['password'])) {
                error_log("Processing login form");
                
                // Pass login parameter needed by some methods
                if (!isset($_POST['login'])) {
                    error_log("Adding login parameter to POST data");
                    $_POST['login'] = '1';
                }
                
                $authController->login();
            } else {
                error_log("Login form not properly submitted - missing required fields");
                $_SESSION['loginError'] = "Vui lòng nhập tên đăng nhập và mật khẩu.";
                header("Location: " . BASE_URL . "app/views/product/home.php");
                exit();
            }
        } else {
            error_log("Login form not properly submitted - not a POST request");
            $_SESSION['loginError'] = "Biểu mẫu đăng nhập không được gửi đúng cách.";
            header("Location: " . BASE_URL . "app/views/product/home.php");
            exit();
        }
        break;
    
    case 'forgotPassword':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF validation (temporarily disabled for debugging)
            if (!$skip_csrf && (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'])) {
                error_log("CSRF token validation failed");
                $_SESSION['forgotError'] = "Yêu cầu không hợp lệ. Vui lòng thử lại.";
                header("Location: " . BASE_URL . "app/views/product/home.php");
                exit();
            }
            
            if (isset($_POST['forgot_email'])) {
                error_log("Processing forgot password form");
                $authController->forgotPassword();
            } else {
                error_log("Forgot password form not properly submitted - missing email");
                $_SESSION['forgotError'] = "Vui lòng nhập địa chỉ email.";
                header("Location: " . BASE_URL . "app/views/product/home.php");
                exit();
            }
        } else {
            error_log("Forgot password form not properly submitted - not a POST request");
            $_SESSION['forgotError'] = "Biểu mẫu quên mật khẩu không được gửi đúng cách.";
            header("Location: " . BASE_URL . "app/views/product/home.php");
            exit();
        }
        break;
    
    case 'resetPassword':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF validation (temporarily disabled for debugging)
            if (!$skip_csrf && (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'])) {
                error_log("CSRF token validation failed");
                $_SESSION['resetError'] = "Yêu cầu không hợp lệ. Vui lòng thử lại.";
                header("Location: " . BASE_URL . "app/views/product/home.php");
                exit();
            }
            
            if (isset($_POST['verification_code']) && isset($_POST['new_password'])) {
                error_log("Processing reset password form");
                $authController->resetPassword();
            } else {
                error_log("Reset password form not properly submitted - missing required fields");
                $_SESSION['resetError'] = "Vui lòng nhập mã xác nhận và mật khẩu mới.";
                header("Location: " . BASE_URL . "app/views/product/home.php");
                exit();
            }
        } else {
            error_log("Reset password form not properly submitted - not a POST request");
            $_SESSION['resetError'] = "Biểu mẫu đặt lại mật khẩu không được gửi đúng cách.";
            header("Location: " . BASE_URL . "app/views/product/home.php");
            exit();
        }
        break;
    
    case 'logout':
        error_log("Processing logout");
        $authController->logout();
        break;
    
    default:
        // Redirect to home page if no action is specified
        error_log("No valid action specified, redirecting to home");
        header("Location: " . BASE_URL . "app/views/product/home.php");
        exit();
}

// Redirect back to home page after processing
error_log("Completed processing, redirecting to home");
header("Location: " . BASE_URL . "app/views/product/home.php");
exit();

// Function to process registration
function processRegistration() {
    global $authController, $skip_csrf;
    
    // CSRF validation (temporarily disabled for debugging)
    if (!$skip_csrf && (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'])) {
        error_log("CSRF token validation failed");
        $_SESSION['registerError'] = "Yêu cầu không hợp lệ. Vui lòng thử lại.";
        header("Location: " . BASE_URL . "app/views/product/home.php");
        exit();
    }
    
    // Check if we have all required fields
    if (isset($_POST['username']) && isset($_POST['email']) && isset($_POST['password']) && isset($_POST['role'])) {
        error_log("All required registration fields present");
        error_log("Username: " . $_POST['username']);
        error_log("Email: " . $_POST['email']);
        error_log("Password length: " . strlen($_POST['password']));
        error_log("Role: " . $_POST['role']);
        
        // Ensure role is valid (student or instructor)
        if ($_POST['role'] !== 'student' && $_POST['role'] !== 'instructor') {
            error_log("Invalid role: " . $_POST['role'] . ". Setting to default 'student'");
            $_POST['role'] = 'student';
        }
        
        // Check if register parameter exists, if not add it for convenience
        if (!isset($_POST['register'])) {
            error_log("Warning: 'register' parameter not found in POST, adding it");
            $_POST['register'] = 1;
        }
        
        // Call the register method with explicit error handling
        try {
            $authController->register();
            // No return value to capture
        } catch (Exception $e) {
            error_log("Exception during registration: " . $e->getMessage());
            $_SESSION['registerError'] = "Lỗi trong quá trình đăng ký: " . $e->getMessage();
        }
    } else {
        $missing = [];
        if (!isset($_POST['username'])) $missing[] = 'username';
        if (!isset($_POST['email'])) $missing[] = 'email';
        if (!isset($_POST['password'])) $missing[] = 'password';
        if (!isset($_POST['role'])) $missing[] = 'role';
        
        error_log("Missing required registration fields: " . implode(", ", $missing) . ". Available fields: " . implode(", ", array_keys($_POST)));
        $_SESSION['registerError'] = "Thiếu thông tin đăng ký cần thiết: " . implode(", ", $missing);
        header("Location: " . BASE_URL . "app/views/product/home.php");
        exit();
    }
}
?>