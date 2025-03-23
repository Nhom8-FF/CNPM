<?php
// Don't redefine constants if already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/WebCourses/');
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3));
}

// Include configuration properly
require_once ROOT_DIR . '/app/config/connect.php';

// Check if session is already started before starting it
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Reset redirect count to prevent redirect loops
$_SESSION['redirect_count'] = 0;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    $_SESSION['error'] = "Bạn không có quyền truy cập trang này. Vui lòng đăng nhập với tài khoản admin.";
    header("Location: " . BASE_URL . "app/views/product/home.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trang Quản Trị - Học Tập Trực Tuyến</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
  <header>
    <div class="container header-container">
      <div class="logo">Học Tập</div>
      <nav>
        <ul>
          <li><a href="admin_dashboard.php">Trang Chủ</a></li>
          <li><a href="manage_users.php" class="btn">Quản lý Người Dùng</a></li>
          <li><a href="manage_courses.php" class="btn">Quản lý Khóa Học</a></li>
          <li>
            <form method="post" action="<?php echo BASE_URL; ?>app/controllers/logout.php" style="display:inline;">
              <button type="submit" class="btn" style="background:none;border:none;color:white;cursor:pointer;font-weight:600;font-family:inherit;padding:0;">Đăng xuất</button>
            </form>
          </li>
        </ul>
      </nav>
    </div>
  </header>

  <section id="dashboard">
    <div class="container">
      <h2>Quản Lý Hệ Thống</h2>
      <p>Chào mừng Quản trị viên! Quản lý người dùng, khóa học và các chức năng khác ở đây.</p>
    </div>
  </section>

  <footer>
    <div class="container">
      <p>© 2025 Học Tập Trực Tuyến. All Rights Reserved.</p>
    </div>
  </footer>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/gsap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/ScrollTrigger.min.js"></script>
  <script type="module" src="<?php echo BASE_URL; ?>public/js/script.js"></script>
  <script>
    // Logout function
    function handleLogout() {
      console.log("Handling admin logout");
      // Fix the URL construction to avoid duplicate hostname
      const protocol = window.location.protocol;
      const host = window.location.host;
      // Remove leading slash from BASE_URL when combining with host
      const baseUrl = '<?php echo BASE_URL; ?>'.replace(/^\//, '');
      const logoutUrl = protocol + '//' + host + '/' + baseUrl + 'app/controllers/logout.php';
      console.log("Redirecting to: " + logoutUrl);
      window.location.href = logoutUrl;
    }
  </script>
</body>
</html>