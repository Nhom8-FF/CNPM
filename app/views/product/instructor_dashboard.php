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

// Kiểm tra xem người dùng đã đăng nhập và có vai trò giảng viên chưa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'instructor') {
    // Set error message in session
    $_SESSION['error'] = "Bạn không có quyền truy cập trang này. Vui lòng đăng nhập với tài khoản giảng viên.";
    // Redirect to home page
    header("Location: " . BASE_URL . "app/views/product/home.php");
    exit();
}

// Lấy thông tin user_id và username từ session
$user_id   = $_SESSION['user_id'];
$username  = $_SESSION['username'];

// Lấy danh sách khóa học do giảng viên này tạo
$stmt = $conn->prepare("
    SELECT course_id, title, description, image, price, level, rating, students, created_at 
    FROM Courses 
    WHERE instructor_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$courses = $stmt->get_result();

// Đếm số khóa học
$totalCourses = $courses->num_rows;

// Lấy tổng số học viên đăng ký các khóa học của giảng viên
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT e.user_id) as total_students
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    WHERE c.instructor_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$studentResult = $stmt->get_result();
$studentData = $studentResult->fetch_assoc();
$totalStudents = $studentData['total_students'] ?? 0;

// Lấy đánh giá trung bình
$stmt = $conn->prepare("
    SELECT AVG(r.rating) as avg_rating
    FROM reviews r
    JOIN courses c ON r.course_id = c.course_id
    WHERE c.instructor_id = ? AND r.status = 'approved'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ratingResult = $stmt->get_result();
$ratingData = $ratingResult->fetch_assoc();
$avgRating = number_format($ratingData['avg_rating'] ?? 0, 1);

// Lấy số lượng thông báo chưa đọc
$stmt = $conn->prepare("
    SELECT COUNT(*) as unread_count 
    FROM notifications 
    WHERE user_id = ? AND is_read = 0
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifResult = $stmt->get_result();
$notifData = $notifResult->fetch_assoc();
$unreadNotifs = $notifData['unread_count'] ?? 0;

// Lấy thống kê thu nhập
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(c.price), 0) as total_income 
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    WHERE c.instructor_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$incomeResult = $stmt->get_result();
$incomeData = $incomeResult->fetch_assoc();
$totalIncome = number_format($incomeData['total_income'] ?? 0, 0, ',', '.');

// Dữ liệu cho biểu đồ (mẫu - trong thực tế sẽ lấy từ database)
$monthlyData = array(
    'enrollments' => [4, 7, 12, 15, 10, 16, 19, 25, 22, 20, 28, 35],
    'income' => [400000, 700000, 1200000, 1500000, 1000000, 1600000, 1900000, 2500000, 2200000, 2000000, 2800000, 3500000]
);
$chartData = json_encode($monthlyData);

// Set page title and specific variables for header
$page_title = 'Trang Quản Lý Giảng Viên';
$include_chart_js = true;
$page_specific_script = "
    // Logout function
    function handleLogout() {
        console.log('Handling instructor logout');
        // Fix the URL construction to avoid duplicate hostname
        const protocol = window.location.protocol;
        const host = window.location.host;
        // Remove leading slash from BASE_URL when combining with host
        const baseUrl = '" . BASE_URL . "'.replace(/^\\//, '');
        const logoutUrl = protocol + '//' + host + '/' + baseUrl + 'app/controllers/logout.php';
        console.log('Redirecting to: ' + logoutUrl);
        window.location.href = logoutUrl;
    }
";

// Include header
include_once ROOT_DIR . '/app/includes/header.php';
?>

<h2>Bảng Điều Khiển Giảng Viên</h2>
    
<!-- Khu vực thống kê -->
<div class="stats">
  <div class="stat-card">
    <div class="stat-header">
      <h3>Tổng Khoá Học</h3>
      <i class="fas fa-book"></i>
    </div>
    <p><?php echo $totalCourses; ?></p>
    <div class="stat-trend up">
      <i class="fas fa-arrow-up"></i> 12% so với tháng trước
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-header">
      <h3>Số Học Viên</h3>
      <i class="fas fa-users"></i>
    </div>
    <p><?php echo $totalStudents; ?></p>
    <div class="stat-trend up">
      <i class="fas fa-arrow-up"></i> 8% so với tháng trước
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-header">
      <h3>Đánh Giá Trung Bình</h3>
      <i class="fas fa-star"></i>
    </div>
    <p><?php echo $avgRating; ?>/5</p>
    <div class="stat-trend up">
      <i class="fas fa-arrow-up"></i> 0.2 so với tháng trước
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-header">
      <h3>Tổng Thu Nhập</h3>
      <i class="fas fa-money-bill-wave"></i>
    </div>
    <p><?php echo $totalIncome; ?> ₫</p>
    <div class="stat-trend up">
      <i class="fas fa-arrow-up"></i> 15% so với tháng trước
    </div>
  </div>
</div>
    
<!-- Khu vực biểu đồ -->
<div class="charts-container">
  <div class="chart-card">
    <h3>Ghi Danh Theo Tháng</h3>
    <canvas id="enrollmentChart"></canvas>
  </div>
  <div class="chart-card">
    <h3>Thu Nhập Theo Tháng</h3>
    <canvas id="incomeChart"></canvas>
  </div>
</div>

<!-- Khu vực khoá học -->
<h2 class="section-title">
  Khoá Học Của Bạn
  <a href="<?php echo BASE_URL; ?>app/views/product/create_course.php" class="add-course-btn">
    <i class="fas fa-plus"></i> Tạo Khoá Học
  </a>
</h2>
    
<div class="courses-container">
  <?php 
  // Reset course result pointer
  $courses->data_seek(0);
  if ($courses->num_rows === 0): ?>
  <div class="empty-state">
    <i class="fas fa-book-open"></i>
    <p>Bạn chưa có khóa học nào. Hãy tạo khóa học đầu tiên!</p>
  </div>
  <?php else: ?>
  <?php while ($course = $courses->fetch_assoc()): ?>
    <div class="course-card">
      <?php if (!empty($course['image'])): ?>
        <?php
          // Check if image path already contains BASE_URL
          $imagePath = $course['image'];
          if (strpos($imagePath, 'http') !== 0 && strpos($imagePath, '/') !== 0) {
            $imagePath = BASE_URL . $imagePath;
          }
        ?>
        <img src="<?php echo htmlspecialchars($imagePath); ?>" 
             alt="<?php echo htmlspecialchars($course['title']); ?>">
      <?php else: ?>
        <img src="<?php echo BASE_URL; ?>public/images/course-placeholder.jpg" alt="Course Thumbnail">
      <?php endif; ?>

      <div class="course-content">
        <h3><?php echo htmlspecialchars($course['title']); ?></h3>
        
        <div class="course-meta">
          <div class="meta-item price">
            <i class="fas fa-tag"></i> <?php echo number_format($course['price'], 0, ',', '.'); ?> ₫
          </div>
          <div class="meta-item rating">
            <i class="fas fa-star"></i> <?php echo $course['rating'] ?: '0.0'; ?>
          </div>
          <div class="meta-item students">
            <i class="fas fa-user-graduate"></i> <?php echo $course['students']; ?>
          </div>
        </div>
        
        <p><?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?></p>
        
        <div class="course-actions">
          <a href="<?php echo BASE_URL; ?>app/views/product/course_management.php?course_id=<?php echo $course['course_id']; ?>" 
              class="course-btn">
            <i class="fas fa-cog"></i> Quản Lý
          </a>
          <a href="<?php echo BASE_URL; ?>app/views/product/lecture_management.php?course_id=<?php echo $course['course_id']; ?>" 
              class="course-btn">
            <i class="fas fa-book"></i> Bài Giảng
          </a>
          <a href="<?php echo BASE_URL; ?>app/views/product/course_analytics.php?course_id=<?php echo $course['course_id']; ?>" 
              class="course-btn outline">
            <i class="fas fa-chart-line"></i> Thống Kê
          </a>
        </div>
      </div>
    </div>
  <?php endwhile; ?>
  <?php endif; ?>
</div>

<!-- Analytics Overview Section -->
<h2 class="section-title" style="margin-top: 40px;">Tổng Quan Phân Tích</h2>

<div class="charts-container">
    <div class="chart-card">
        <h3>Hoạt Động Khóa Học</h3>
        <canvas id="courseActivityChart" height="300"></canvas>
    </div>
    
    <div class="chart-card">
        <h3>Phân Bố Học Viên</h3>
        <canvas id="studentDistributionChart" height="300"></canvas>
    </div>
</div>

<div class="analytics-summary">
    <h3>Phân Tích Khóa Học</h3>
    <div class="table-responsive">
        <table class="analytics-table">
            <thead>
                <tr>
                    <th>Khóa Học</th>
                    <th>Học Viên</th>
                    <th>Hoàn Thành</th>
                    <th>Đánh Giá</th>
                    <th>Doanh Thu</th>
                    <th>Chi Tiết</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $courses->data_seek(0);
                while ($course = $courses->fetch_assoc()): 
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($course['title']); ?></td>
                    <td><?php echo $course['students']; ?></td>
                    <td><?php echo rand(5, max(5, $course['students'])); ?> (<?php echo rand(10, 90); ?>%)</td>
                    <td><?php echo number_format($course['rating'] ?: 0, 1); ?> <small>(<?php echo rand(5, 50); ?>)</small></td>
                    <td><?php echo number_format($course['price'] * $course['students'], 0, ',', '.'); ?>đ</td>
                    <td>
                        <a href="<?php echo BASE_URL; ?>app/views/product/course_analytics.php?course_id=<?php echo $course['course_id']; ?>" class="btn-analytics-small">
                            <i class="fas fa-chart-line"></i> Chi Tiết
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Assignments & Assessments Section -->
<div class="assignments-section">
  <h2 class="section-title">
    Bài Tập & Đánh Giá
    <a href="<?php echo BASE_URL; ?>app/views/product/create_assignment.php" class="add-btn">
      <i class="fas fa-plus"></i> Tạo Bài Tập Mới
    </a>
  </h2>
  
  <div class="tabs">
    <button class="tab-btn active" data-tab="assignments">Bài Tập</button>
    <button class="tab-btn" data-tab="quizzes">Bài Kiểm Tra</button>
    <button class="tab-btn" data-tab="exams">Bài Thi</button>
  </div>
  
  <div class="tab-content active" id="assignments-tab">
    <div class="assignment-grid">
      <?php
      // Here we would normally fetch assignments from database
      // For now, just display placeholder content
      $placeholderAssignments = [
        ['id' => 1, 'title' => 'Bài tập: Xây dựng trang web cá nhân', 'course' => 'Lập trình Web', 'due_date' => '2023-12-15', 'submissions' => 24],
        ['id' => 2, 'title' => 'Bài tập: Thiết kế database', 'course' => 'Cơ sở dữ liệu', 'due_date' => '2023-12-20', 'submissions' => 18],
        ['id' => 3, 'title' => 'Bài tập: Giải thuật tìm kiếm', 'course' => 'Cấu trúc dữ liệu', 'due_date' => '2023-12-22', 'submissions' => 15]
      ];
      
      foreach ($placeholderAssignments as $assignment):
      ?>
      <div class="assignment-card">
        <div class="assignment-header">
          <h3><?php echo $assignment['title']; ?></h3>
          <div class="assignment-course"><?php echo $assignment['course']; ?></div>
        </div>
        <div class="assignment-details">
          <div class="detail-item">
            <i class="fas fa-calendar"></i> Hạn nộp: <?php echo $assignment['due_date']; ?>
          </div>
          <div class="detail-item">
            <i class="fas fa-users"></i> Đã nộp: <?php echo $assignment['submissions']; ?>
          </div>
        </div>
        <div class="assignment-actions">
          <a href="#" class="assignment-btn"><i class="fas fa-edit"></i> Chỉnh Sửa</a>
          <a href="#" class="assignment-btn"><i class="fas fa-eye"></i> Xem Bài Nộp</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  
  <div class="tab-content" id="quizzes-tab">
    <div class="assignment-grid">
      <?php
      // Placeholder quizzes
      $placeholderQuizzes = [
        ['id' => 1, 'title' => 'Kiểm tra: HTML & CSS cơ bản', 'course' => 'Lập trình Web', 'time_limit' => 30, 'questions' => 15, 'attempts' => 28],
        ['id' => 2, 'title' => 'Kiểm tra: SQL Queries', 'course' => 'Cơ sở dữ liệu', 'time_limit' => 45, 'questions' => 20, 'attempts' => 22]
      ];
      
      foreach ($placeholderQuizzes as $quiz):
      ?>
      <div class="assignment-card">
        <div class="assignment-header">
          <h3><?php echo $quiz['title']; ?></h3>
          <div class="assignment-course"><?php echo $quiz['course']; ?></div>
        </div>
        <div class="assignment-details">
          <div class="detail-item">
            <i class="fas fa-clock"></i> Thời gian: <?php echo $quiz['time_limit']; ?> phút
          </div>
          <div class="detail-item">
            <i class="fas fa-question-circle"></i> Câu hỏi: <?php echo $quiz['questions']; ?>
          </div>
          <div class="detail-item">
            <i class="fas fa-users"></i> Lượt làm: <?php echo $quiz['attempts']; ?>
          </div>
        </div>
        <div class="assignment-actions">
          <a href="#" class="assignment-btn"><i class="fas fa-edit"></i> Chỉnh Sửa</a>
          <a href="#" class="assignment-btn"><i class="fas fa-chart-bar"></i> Kết Quả</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  
  <div class="tab-content" id="exams-tab">
    <div class="assignment-grid">
      <?php
      // Placeholder exams
      $placeholderExams = [
        ['id' => 1, 'title' => 'Bài thi cuối kỳ: Lập trình Web', 'course' => 'Lập trình Web', 'time_limit' => 120, 'date' => '2023-12-25', 'participants' => 30],
        ['id' => 2, 'title' => 'Bài thi giữa kỳ: Cơ sở dữ liệu', 'course' => 'Cơ sở dữ liệu', 'time_limit' => 90, 'date' => '2023-11-15', 'participants' => 25]
      ];
      
      foreach ($placeholderExams as $exam):
      ?>
      <div class="assignment-card">
        <div class="assignment-header">
          <h3><?php echo $exam['title']; ?></h3>
          <div class="assignment-course"><?php echo $exam['course']; ?></div>
        </div>
        <div class="assignment-details">
          <div class="detail-item">
            <i class="fas fa-calendar"></i> Ngày thi: <?php echo $exam['date']; ?>
          </div>
          <div class="detail-item">
            <i class="fas fa-clock"></i> Thời gian: <?php echo $exam['time_limit']; ?> phút
          </div>
          <div class="detail-item">
            <i class="fas fa-users"></i> Thí sinh: <?php echo $exam['participants']; ?>
          </div>
        </div>
        <div class="assignment-actions">
          <a href="#" class="assignment-btn"><i class="fas fa-edit"></i> Chỉnh Sửa</a>
          <a href="#" class="assignment-btn"><i class="fas fa-chart-bar"></i> Kết Quả</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Define enrollmentData for the charts before loading the scripts -->
<script>
    // Initialize enrollment data for charts
    const enrollmentData = {
        enrollments: [<?php echo implode(',', array_map(function($m) { return isset($monthly_enrollments[$m]) ? $monthly_enrollments[$m] : 0; }, range(1, 12))); ?>],
        income: [<?php echo implode(',', array_map(function($m) { return isset($monthly_income[$m]) ? $monthly_income[$m] : 0; }, range(1, 12))); ?>]
    };
</script>

<!-- Include the necessary JavaScript files -->
<script src="<?= BASE_URL ?>public/js/instructor_dashboard.js"></script>

<script>
// Chart data and initialization for analytics overview
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if Chart.js is available and we have canvas elements
    if (typeof Chart !== 'undefined' && document.getElementById('courseActivityChart') && document.getElementById('studentDistributionChart')) {
        initializeAnalyticsCharts();
    }
});

function initializeAnalyticsCharts() {
    const colors = getThemeColors();
    
    // Course Activity Chart
    const activityCtx = document.getElementById('courseActivityChart').getContext('2d');
    new Chart(activityCtx, {
        type: 'line',
        data: {
            labels: ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12'],
            datasets: [{
                label: 'Lượt Xem',
                data: [150, 220, 180, 260, 320, 280, 350, 410, 380, 420, 450, 500],
                borderColor: colors.viewsBorder,
                backgroundColor: colors.views,
                tension: 0.3,
                fill: true
            }, {
                label: 'Đăng Ký Mới',
                data: [30, 40, 35, 50, 65, 55, 70, 80, 75, 85, 90, 100],
                borderColor: colors.enrollmentsBorder,
                backgroundColor: colors.enrollments,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        color: colors.text
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: colors.grid
                    },
                    ticks: {
                        color: colors.text
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: colors.grid
                    },
                    ticks: {
                        color: colors.text
                    }
                }
            }
        }
    });
    
    // Student Distribution Chart
    const distributionCtx = document.getElementById('studentDistributionChart').getContext('2d');
    new Chart(distributionCtx, {
        type: 'pie',
        data: {
            labels: <?php 
                $courses->data_seek(0);
                $courseLabels = [];
                while ($c = $courses->fetch_assoc()) {
                    $courseLabels[] = $c['title'];
                }
                echo json_encode($courseLabels);
            ?>,
            datasets: [{
                data: <?php 
                $courses->data_seek(0);
                $studentData = [];
                while ($c = $courses->fetch_assoc()) {
                    $studentData[] = $c['students'];
                }
                echo json_encode($studentData);
            ?>,
                backgroundColor: [
                    'rgba(98, 74, 242, 0.7)',
                    'rgba(49, 151, 149, 0.7)',
                    'rgba(76, 201, 240, 0.7)',
                    'rgba(247, 37, 133, 0.7)',
                    'rgba(255, 159, 28, 0.7)',
                    'rgba(16, 185, 129, 0.7)'
                ],
                borderColor: [
                    'rgba(98, 74, 242, 1)',
                    'rgba(49, 151, 149, 1)',
                    'rgba(76, 201, 240, 1)',
                    'rgba(247, 37, 133, 1)',
                    'rgba(255, 159, 28, 1)',
                    'rgba(16, 185, 129, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: colors.text
                    }
                }
            }
        }
    });
}
</script>

<?php
// Include footer
include_once ROOT_DIR . '/app/includes/footer.php';
?>
