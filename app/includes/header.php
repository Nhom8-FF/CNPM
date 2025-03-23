<?php
// Don't redefine constants if already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/WebCourses/');
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}

// Include database connection
require_once ROOT_DIR . '/app/config/connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    // Set error message in session
    $_SESSION['error'] = "Bạn không có quyền truy cập trang này. Vui lòng đăng nhập với tài khoản giảng viên.";
    // Redirect to home page
    header("Location: " . BASE_URL . "app/views/product/home.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Instructor';

// Get unread notifications count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$unread_count = $result->fetch_assoc()['count'];

// Get page title from the calling page or set a default
$page_title = $page_title ?? 'Instructor Dashboard';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/instructor_dashboard.css">
    <?php if (isset($include_assignments_css) && $include_assignments_css): ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/assignments.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <?php if (isset($include_chart_js) && $include_chart_js): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
    <?php if (isset($page_specific_css)): ?>
    <style>
        <?php echo $page_specific_css; ?>
    </style>
    <?php endif; ?>
    <script>
        // Define BASE_URL for JavaScript
        const BASE_URL = "<?php echo BASE_URL; ?>";
    </script>
</head>
<body class="<?php echo isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'true' ? 'dark-mode' : ''; ?>">
    <!-- Header -->
    <header class="header">
        <div class="mobile-menu-toggle" id="mobile-toggle">
            <i class="fas fa-bars"></i>
        </div>
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <span>Học Tập</span>
        </div>
        <div class="user-actions">
            <div class="notification-icon">
                <i class="fas fa-bell"></i>
                <?php if ($unread_count > 0): ?>
                <span class="badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </div>
            <button class="mode-toggle" id="mode-toggle">
                <i class="fas fa-moon"></i>
            </button>
            <div class="teacher-name">Xin chào, <strong><?php echo htmlspecialchars($username); ?></strong></div>
            <button onclick="handleLogout()" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Đăng Xuất
            </button>
        </div>
    </header>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'instructor_dashboard.php' ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>app/views/product/instructor_dashboard.php"><i class="fas fa-tachometer-alt"></i> Tổng Quan</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'create_course.php' ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>app/views/product/create_course.php"><i class="fas fa-plus-circle"></i> Thêm Khoá Học</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'lecture_list.php' || basename($_SERVER['PHP_SELF']) === 'lecture_management.php' ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>app/views/product/lecture_list.php"><i class="fas fa-book"></i> Quản Lý Bài Giảng</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'students.php' ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>app/views/product/students.php"><i class="fas fa-users"></i> Danh Sách Học Viên</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'discussions.php' ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>app/views/product/discussions.php"><i class="fas fa-comments"></i> Thảo Luận</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'certificates.php' ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>app/views/product/certificates.php"><i class="fas fa-certificate"></i> Chứng Chỉ</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>app/views/product/notifications.php"><i class="fas fa-bell"></i> Thông Báo</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'assignments.php' || basename($_SERVER['PHP_SELF']) === 'create_assignment.php' ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>app/views/product/assignments.php"><i class="fas fa-tasks"></i> Bài Tập & Đánh Giá</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'course_analytics.php' ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>app/views/product/course_analytics.php"><i class="fas fa-chart-line"></i> Phân Tích Dữ Liệu</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'income.php' ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>app/views/product/income.php"><i class="fas fa-money-bill-wave"></i> Thu Nhập</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>app/views/product/settings.php"><i class="fas fa-cog"></i> Cài Đặt</a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'support.php' ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>app/views/product/support.php"><i class="fas fa-question-circle"></i> Hỗ Trợ</a>
            </li>
        </ul>
        <div class="sidebar-footer">
            © 2025 Học Tập Trực Tuyến
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content"> 