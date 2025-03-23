<?php
// student_dashboard.php
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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
  $_SESSION['error'] = "Vui lòng đăng nhập với vai trò sinh viên để truy cập dashboard.";
  header("Location: " . BASE_URL . "app/views/product/home.php");
  exit();
}
$user_id = $_SESSION['user_id'];

// Phân trang cho khóa học đã đăng ký (động)
$limit = 5;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("
    SELECT c.course_id, c.title, c.description, e.status 
    FROM Enrollments e 
    JOIN Courses c ON e.course_id = c.course_id 
    WHERE e.user_id = ? AND e.status = 'active'
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $user_id, $limit, $offset);
$stmt->execute();
$enrollments = $stmt->get_result();

$resultTotal = $conn->query("
    SELECT COUNT(*) AS total 
    FROM Enrollments 
    WHERE user_id = $user_id AND status = 'active'
");
$totalData = $resultTotal->fetch_assoc()['total'];
$totalPages = ceil($totalData / $limit);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Sinh Viên - Học Tập Trực Tuyến</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Font từ Google -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
  <style>
    /* Reset mặc định */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Open Sans', sans-serif; background-color: #f2f2f2; }
    /* Header */
    header { background-color: #2c3e50; color: #fff; padding: 20px 0; }
    .header-container { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; padding: 0 20px; }
    .logo { font-size: 1.5rem; font-weight: bold; font-family: 'Montserrat', sans-serif; }
    nav ul { list-style: none; display: flex; gap: 20px; }
    nav ul li a { color: #fff; text-decoration: none; font-weight: 600; transition: color 0.3s; }
    nav ul li a:hover { color: #ff7f50; }
    /* Layout: sidebar + main content */
    .dashboard-container { display: flex; width: 90%; max-width: 1200px; margin: 40px auto; gap: 20px; }
    .sidebar { width: 250px; background: #2c3e50; color: #ecf0f1; padding: 20px; border-radius: 8px; min-height: 600px; }
    .sidebar h2 { margin-bottom: 20px; font-size: 1.4rem; text-align: center; }
    .sidebar ul { list-style: none; padding-left: 0; }
    .sidebar ul li { margin-bottom: 10px; }
    .sidebar ul li a { color: #ecf0f1; text-decoration: none; font-size: 1rem; display: block; padding: 8px 12px; border-radius: 4px; transition: background 0.3s ease; }
    .sidebar ul li a:hover { background: #34495e; }
    .main-content { flex: 1; background: #fff; padding: 20px; border-radius: 8px; min-height: 600px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .main-content h2 { font-family: 'Montserrat', sans-serif; font-size: 1.6rem; color: #2980b9; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
    /* Thẻ khóa học */
    .course-card { background: #fdfdfd; border: 1px solid #ddd; border-radius: 6px; padding: 15px; margin-bottom: 15px; transition: box-shadow 0.3s ease; }
    .course-card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    .course-card h3 { margin: 0 0 10px 0; font-size: 1.2rem; color: #ff7f50; }
    .course-btn { display: inline-block; background: #ff7f50; color: #fff; padding: 8px 12px; text-decoration: none; border-radius: 4px; transition: background 0.3s ease; font-weight: 600; }
    .course-btn:hover { background: #ff5722; }
    /* Phân trang */
    .pagination { display: flex; justify-content: center; margin-top: 20px; }
    .pagination a { margin: 0 5px; padding: 8px 12px; background: #ff7f50; color: #fff; text-decoration: none; border-radius: 4px; }
    .pagination a.active, .pagination a:hover { background: #ff5722; }
    /* Grid cho khóa học tĩnh */
    .courses-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
    footer { background-color: #2c3e50; color: #fff; text-align: center; padding: 15px 0; margin-top: 20px; }
    /* Responsive */
    @media (max-width: 768px) {
      .dashboard-container { flex-direction: column; }
      .sidebar { width: 100%; margin-bottom: 20px; }
      .main-content { width: 100%; }
    }
  </style>
</head>
<body>
  <!-- Header -->
  <header>
    <div class="header-container">
      <div class="logo">Học Tập</div>
      <nav>
        <ul>
          <li><a href="home.php">Trang Chủ</a></li>
          <li><a href="student_dashboard.php#courses" class="btn">Khoá Học Của Tôi</a></li>
          <li>
            <form method="post" action="<?php echo BASE_URL; ?>app/controllers/logout.php" style="display:inline;">
              <button type="submit" class="btn" style="background:none;border:none;color:white;cursor:pointer;font-weight:600;font-family:inherit;padding:0;">Đăng xuất</button>
            </form>
          </li>
        </ul>
      </nav>
    </div>
  </header>

  <!-- Layout chính: sidebar + nội dung -->
  <div class="dashboard-container">
    <aside class="sidebar">
      <h2>Menu Sinh Viên</h2>
      <ul>
        <li><a href="#courses">Khóa Học</a></li>
        <li><a href="#lessons">Bài Học</a></li>
        <li><a href="#assignments">Bài Tập</a></li>
        <li><a href="#quizzes">Trắc Nghiệm</a></li>
        <li><a href="#discussions">Diễn Đàn</a></li>
      </ul>
    </aside>

    <!-- Khu vực nội dung chính -->
    <main class="main-content">
      <!-- PHẦN 1: Khóa học đăng ký (động) -->
      <section id="courses" class="section">
        <?php if ($enrollments->num_rows > 0): ?>
          <?php while ($enrollment = $enrollments->fetch_assoc()): ?>
            <div class="course-card">
              <h3><?php echo htmlspecialchars($enrollment['title']); ?></h3>
              <p><?php echo htmlspecialchars($enrollment['description']); ?></p>
              <p><strong>Trạng thái:</strong> <?php echo htmlspecialchars($enrollment['status']); ?></p>
              <a href="course_detail.php?course_id=<?php echo $enrollment['course_id']; ?>" class="course-btn">Xem chi tiết</a>
            </div>
          <?php endwhile; ?>
          <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <a href="?page=<?php echo $i; ?>" <?php if ($i == $page) echo 'class="active"'; ?>>
                <?php echo $i; ?>
              </a>
            <?php endfor; ?>
          </div>
        <?php else: ?>
          <p>Chưa có khóa học đăng ký nào.</p>
        <?php endif; ?>
      </section>

      <!-- PHẦN 2: 10 Khóa học tĩnh (Google Drive) -->
      <section id="static-courses" class="section">
        <h2>Khóa Học Tham Khảo Thêm</h2>
        <div class="courses-grid">
          <?php 
          // Mảng chứa tên của 10 khóa học (theo Google Drive)
          $staticCourses = [
              1 => "Xây dựng hệ thống bảo vệ thông tin",
              2 => "Phân tích tài chính",
              3 => "Mô hình hóa phần mềm",
              4 => "Lý thuyết mật mã",
              5 => "Lý thuyết cơ sỡ dữ liệu",
              6 => "Lập trình nhúng",
              7 => "Kinh tế vĩ mô",
              8 => "Hệ thống thiết bị di động",
              9 => "Cơ sở dữ liệu phân tán và hướng đối tượng",
              10 => "Cảm biến và kỹ thuật đo lường"
          ];
          foreach ($staticCourses as $id => $title):
          ?>
          <div class="course-card">
            <h3><?php echo $title; ?></h3>
            <p>Hiển thị video và tài liệu PDF từ Google Drive</p>
            <a href="static_course_detail.php?course_id=<?php echo $id; ?>" class="course-btn">Xem chi tiết</a>
          </div>
          <?php endforeach; ?>
        </div>
      </section>
    </main>
  </div>

  <!-- Footer -->
  <footer>
    <p>© 2025 Học Tập Trực Tuyến. All Rights Reserved.</p>
  </footer>

  <script>
    // Logout function
    function handleLogout() {
      console.log("Handling student logout");
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