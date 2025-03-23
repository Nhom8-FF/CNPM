<?php
// Log that logout was called
error_log("Logout script called directly at " . date('Y-m-d H:i:s'));

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
    error_log("Started new session in logout.php");
} else {
    error_log("Using existing session in logout.php");
}

// Define BASE_URL if not defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/WebCourses/'); 
    error_log("Defined BASE_URL in logout.php");
}

// Define ROOT_DIR if not defined
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
    error_log("Defined ROOT_DIR in logout.php");
}

// Log user info before logout
if (isset($_SESSION['user_id'])) {
    error_log("Logging out user ID: " . $_SESSION['user_id'] . ", username: " . ($_SESSION['username'] ?? 'unknown') . ", role: " . ($_SESSION['role'] ?? 'unknown'));
} else {
    error_log("No user ID found in session");
}

// Unset all session variables
$_SESSION = [];
error_log("Session variables cleared");

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
    error_log("Session cookie destroyed");
}

// Destroy the session
session_destroy();
error_log("Session destroyed successfully");

// Redirect to home page with a simple path
header("Location: /WebCourses/");
exit();
?>