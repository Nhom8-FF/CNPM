<?php
// Don't redefine constants if already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/WebCourses/');
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3));
}

// Include config if needed and not already included
if (!defined('DB_HOST')) {
    require_once ROOT_DIR . '/app/config/config.php';
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Reset redirect count to prevent redirect loops
$_SESSION['redirect_count'] = 0;

// Get error message from query string or session
$errorMessage = htmlspecialchars($_GET['message'] ?? ($_SESSION['error'] ?? 'Không xác định'));

// Clear session error once displayed
if (isset($_SESSION['error'])) {
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lỗi - Học Tập Trực Tuyến</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .error-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 550px;
            width: 90%;
            text-align: center;
        }
        .error-code {
            font-size: 96px;
            font-weight: bold;
            color: #e74c3c;
            margin: 0 0 10px 0;
            line-height: 1;
        }
        .error-title {
            color: #e74c3c;
            margin: 0 0 20px 0;
            font-size: 28px;
        }
        .error-message {
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
            color: #555;
        }
        .home-button {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 12px 24px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        .home-button:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <h1 class="error-title">Có lỗi xảy ra</h1>
        <p class="error-message"><?php echo $errorMessage; ?></p>
        <a href="<?php echo BASE_URL; ?>app/views/product/home.php" class="home-button">Quay về trang chủ</a>
    </div>
</body>
</html>