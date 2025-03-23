<?php
// Define constants if they're not already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/WebCourses/');
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(dirname(dirname(__DIR__))));
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

// Get instructor ID from session
$instructor_id = $_SESSION['user_id'];

// Get unread notifications count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$result = $stmt->get_result();
$unread_count = $result->fetch_assoc()['count'];

// Get instructor's courses
$stmt = $conn->prepare("SELECT c.course_id, c.title FROM courses c 
                        WHERE c.instructor_id = ?");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$result = $stmt->get_result();
$courses = [];
while ($row = $result->fetch_assoc()) {
    $courses[$row['course_id']] = $row['title'];
}

// Get notification message if any
$notification = isset($_GET['notification']) ? $_GET['notification'] : '';
$notification_type = isset($_GET['type']) ? $_GET['type'] : 'info';

// Get all assignments for this instructor
$assignments = [];
if (!empty($courses)) {
    $course_ids = array_keys($courses);
    $course_ids_str = implode(',', $course_ids);
    
    $query = "SELECT a.*, c.title as course_title 
              FROM assignments a 
              JOIN courses c ON a.course_id = c.course_id 
              WHERE a.course_id IN ($course_ids_str) 
              ORDER BY a.due_date DESC";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $assignments[] = $row;
        }
    }
}

// Placeholder data for quizzes and exams
// In a real application, these would be fetched from the database
$quizzes = [
    [
        'id' => 1,
        'title' => 'Kiểm tra: HTML & CSS cơ bản',
        'course_id' => array_key_first($courses) ?? 0,
        'course' => reset($courses) ?: 'Lập trình Web',
        'time_limit' => 30,
        'questions' => 15,
        'attempts' => 28,
        'status' => 'active'
    ],
    [
        'id' => 2,
        'title' => 'Kiểm tra: SQL Queries',
        'course_id' => array_key_first($courses) ?? 0,
        'course' => reset($courses) ?: 'Cơ sở dữ liệu',
        'time_limit' => 45,
        'questions' => 20,
        'attempts' => 22,
        'status' => 'completed'
    ]
];

// Fetch placeholder exams data
$exams = [
    [
        'id' => 1,
        'title' => 'Bài thi cuối kỳ: Lập trình Web',
        'course_id' => array_key_first($courses) ?? 0,
        'course' => reset($courses) ?: 'Lập trình Web',
        'time_limit' => 120,
        'date' => '2023-12-25',
        'participants' => 30,
        'status' => 'scheduled'
    ],
    [
        'id' => 2,
        'title' => 'Bài thi giữa kỳ: Cơ sở dữ liệu',
        'course_id' => array_key_first($courses) ?? 0,
        'course' => reset($courses) ?: 'Cơ sở dữ liệu',
        'time_limit' => 90,
        'date' => '2023-11-15',
        'participants' => 25,
        'status' => 'completed'
    ]
];

// Helper function to get status label
function getStatusLabel($status) {
    switch ($status) {
        case 'draft':
            return 'Bản nháp';
        case 'active':
            return 'Đang diễn ra';
        case 'scheduled':
            return 'Đã lên lịch';
        case 'completed':
            return 'Đã hoàn thành';
        default:
            return 'Không xác định';
    }
}

// Set up page title and CSS/JS includes for the header
$page_title = 'Bài Tập & Đánh Giá';
$include_assignments_css = true;
$include_assignments_js = true;

// Include header
include_once ROOT_DIR . '/app/includes/header.php';
?>

<h2 class="section-title">
    Bài Tập & Đánh Giá
    <a href="<?php echo BASE_URL; ?>app/views/product/create_assignment.php" class="add-btn">
        <i class="fas fa-plus"></i> Tạo Mới
    </a>
</h2>

<!-- Notification message if any -->
<?php if (!empty($notification)): ?>
<div class="alert alert-<?php echo $notification_type; ?>">
    <i class="fas fa-<?php echo $notification_type === 'success' ? 'check-circle' : 'info-circle'; ?>"></i>
    <div><?php echo htmlspecialchars($notification); ?></div>
</div>
<?php endif; ?>

<!-- Tabs for different assessment types -->
<div class="tabs">
    <button class="tab-btn active" data-tab="assignments">Bài Tập</button>
    <button class="tab-btn" data-tab="quizzes">Bài Kiểm Tra</button>
    <button class="tab-btn" data-tab="exams">Bài Thi</button>
</div>

<!-- Assignments Tab -->
<div class="tab-content active" id="assignments-tab">
    <?php if (empty($assignments)): ?>
        <div class="empty-state">
            <i class="fas fa-tasks"></i>
            <h3>Chưa có bài tập nào</h3>
            <p>Bắt đầu tạo bài tập cho học viên của bạn.</p>
            <a href="<?php echo BASE_URL; ?>app/views/product/create_assignment.php" class="btn-primary">
                <i class="fas fa-plus"></i> Tạo Bài Tập Mới
            </a>
        </div>
    <?php else: ?>
        <div class="assignment-grid">
            <?php foreach ($assignments as $assignment): ?>
                <div class="assignment-card">
                    <div class="assignment-header">
                        <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                        <div class="assignment-course"><?php echo htmlspecialchars($assignment['course_title']); ?></div>
                        <?php 
                        $status = $assignment['is_published'] ? 'active' : 'draft';
                        if ($assignment['is_published'] && strtotime($assignment['due_date']) < time()) {
                            $status = 'completed';
                        }
                        ?>
                        <div class="assignment-status <?php echo $status; ?>">
                            <?php echo getStatusLabel($status); ?>
                        </div>
                    </div>
                    <div class="assignment-details">
                        <div class="detail-item">
                            <i class="fas fa-calendar"></i> Hạn nộp: <?php echo date('d/m/Y H:i', strtotime($assignment['due_date'])); ?>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-star"></i> Điểm tối đa: <?php echo $assignment['max_score']; ?>
                        </div>
                        <?php if ($status === 'active' || $status === 'completed'): ?>
                        <div class="detail-item">
                            <i class="fas fa-users"></i> Đã nộp: <?php echo rand(0, 30); ?> bài
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="assignment-actions">
                        <a href="<?php echo BASE_URL; ?>app/views/product/edit_assignment.php?id=<?php echo $assignment['assignment_id']; ?>" class="assignment-btn">
                            <i class="fas fa-edit"></i> Chỉnh Sửa
                        </a>
                        <a href="<?php echo BASE_URL; ?>app/views/product/view_submissions.php?id=<?php echo $assignment['assignment_id']; ?>" class="assignment-btn">
                            <i class="fas fa-eye"></i> Xem Bài Nộp
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Quizzes Tab -->
<div class="tab-content" id="quizzes-tab">
    <?php if (empty($quizzes)): ?>
        <div class="empty-state">
            <i class="fas fa-question-circle"></i>
            <h3>Chưa có bài kiểm tra nào</h3>
            <p>Bắt đầu tạo bài kiểm tra cho học viên của bạn.</p>
            <a href="<?php echo BASE_URL; ?>app/views/product/create_quiz.php" class="btn-primary">
                <i class="fas fa-plus"></i> Tạo Bài Kiểm Tra Mới
            </a>
        </div>
    <?php else: ?>
        <div class="assignment-grid">
            <?php foreach ($quizzes as $quiz): ?>
                <div class="assignment-card">
                    <div class="assignment-header">
                        <h3><?php echo htmlspecialchars($quiz['title']); ?></h3>
                        <div class="assignment-course"><?php echo htmlspecialchars($quiz['course']); ?></div>
                        <div class="assignment-status <?php echo $quiz['status']; ?>">
                            <?php echo getStatusLabel($quiz['status']); ?>
                        </div>
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
                        <a href="<?php echo BASE_URL; ?>app/views/product/edit_quiz.php?id=<?php echo $quiz['id']; ?>" class="assignment-btn">
                            <i class="fas fa-edit"></i> Chỉnh Sửa
                        </a>
                        <a href="<?php echo BASE_URL; ?>app/views/product/quiz_results.php?id=<?php echo $quiz['id']; ?>" class="assignment-btn">
                            <i class="fas fa-chart-bar"></i> Kết Quả
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Exams Tab -->
<div class="tab-content" id="exams-tab">
    <?php if (empty($exams)): ?>
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <h3>Chưa có bài thi nào</h3>
            <p>Bắt đầu tạo bài thi cho học viên của bạn.</p>
            <a href="<?php echo BASE_URL; ?>app/views/product/create_exam.php" class="btn-primary">
                <i class="fas fa-plus"></i> Tạo Bài Thi Mới
            </a>
        </div>
    <?php else: ?>
        <div class="assignment-grid">
            <?php foreach ($exams as $exam): ?>
                <div class="assignment-card">
                    <div class="assignment-header">
                        <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
                        <div class="assignment-course"><?php echo htmlspecialchars($exam['course']); ?></div>
                        <div class="assignment-status <?php echo $exam['status']; ?>">
                            <?php echo getStatusLabel($exam['status']); ?>
                        </div>
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
                        <a href="<?php echo BASE_URL; ?>app/views/product/edit_exam.php?id=<?php echo $exam['id']; ?>" class="assignment-btn">
                            <i class="fas fa-edit"></i> Chỉnh Sửa
                        </a>
                        <a href="<?php echo BASE_URL; ?>app/views/product/exam_results.php?id=<?php echo $exam['id']; ?>" class="assignment-btn">
                            <i class="fas fa-chart-bar"></i> Kết Quả
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include_once ROOT_DIR . '/app/includes/footer.php';
?> 