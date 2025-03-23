<?php
// Don't redefine constants if already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/WebCourses/');
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3));
}

// Include database connection
include ROOT_DIR . '/app/config/connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Reset redirect count to prevent redirect loops
$_SESSION['redirect_count'] = 0;

// Check if user is logged in and has instructor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'instructor') {
    // Set error message
    $_SESSION['error'] = "Bạn không có quyền truy cập trang này. Vui lòng đăng nhập với tài khoản giảng viên.";
    // Redirect to home page
    header("Location: " . BASE_URL . "app/views/product/home.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get the number of unread notifications
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

// Get all courses taught by this instructor
$stmt = $conn->prepare("
    SELECT course_id, title, image 
    FROM courses 
    WHERE instructor_id = ? 
    ORDER BY title ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$courses = $stmt->get_result();

// Initialize array to store lectures grouped by courses
$courseLectures = [];

// Fetch lectures for each course
if ($courses->num_rows > 0) {
    while ($course = $courses->fetch_assoc()) {
        $course_id = $course['course_id'];
        
        $stmt = $conn->prepare("
            SELECT l.*, c.title as course_title, c.image as course_image
            FROM lessons l
            JOIN courses c ON l.course_id = c.course_id
            WHERE l.course_id = ?
            ORDER BY l.order_index ASC
        ");
        $stmt->bind_param("i", $course_id);
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("Query failed: " . $stmt->error);
            continue;
        }
        
        $lectures = $stmt->get_result();
        $lectureList = [];
        
        while ($lecture = $lectures->fetch_assoc()) {
            $lectureList[] = $lecture;
        }
        
        // Add course and its lectures to the array
        $courseLectures[] = [
            'course' => $course,
            'lectures' => $lectureList
        ];
    }
}

// Custom CSS for this page
$page_specific_css = "
    .course-section {
        background: var(--card-light);
        border-radius: 16px;
        box-shadow: var(--shadow-light);
        padding: 20px;
        margin-bottom: 30px;
    }
    
    .dark-mode .course-section {
        background: var(--card-dark);
        box-shadow: var(--shadow-dark);
    }
    
    .course-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(0,0,0,0.1);
    }
    
    .dark-mode .course-header {
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .course-image {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        object-fit: cover;
    }
    
    .course-info {
        flex: 1;
    }
    
    .course-title {
        margin: 0 0 5px 0;
        font-size: 1.3rem;
        color: var(--text-dark);
    }
    
    .dark-mode .course-title {
        color: var(--text-light);
    }
    
    .course-actions {
        display: flex;
        gap: 10px;
    }
    
    .course-actions a {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 8px;
        font-weight: 500;
        font-size: 0.9rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s;
    }
    
    .course-actions a:hover {
        background: #513dd8;
        transform: translateY(-2px);
    }
    
    .lesson-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .lesson-table th {
        text-align: left;
        padding: 12px 15px;
        background: rgba(0,0,0,0.05);
        color: var(--text-dark);
        font-weight: 600;
    }
    
    .dark-mode .lesson-table th {
        background: rgba(255,255,255,0.05);
        color: var(--text-light);
    }
    
    .lesson-table td {
        padding: 12px 15px;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    
    .dark-mode .lesson-table td {
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    
    .lesson-table tr:last-child td {
        border-bottom: none;
    }
    
    .lesson-table tr:hover td {
        background: rgba(0,0,0,0.02);
    }
    
    .dark-mode .lesson-table tr:hover td {
        background: rgba(255,255,255,0.02);
    }
    
    .lesson-actions {
        display: flex;
        gap: 10px;
    }
    
    .lesson-actions a {
        color: var(--primary-color);
        text-decoration: none;
        font-size: 1rem;
        transition: all 0.2s;
    }
    
    .lesson-actions a:hover {
        color: #513dd8;
        transform: scale(1.1);
    }
    
    .no-lessons {
        padding: 20px;
        text-align: center;
        color: var(--text-secondary);
        font-style: italic;
    }
    
    .page-actions {
        margin-bottom: 20px;
    }
    
    .add-course-btn {
        background: var(--primary-color);
        color: white;
        text-decoration: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
    }
    
    .add-course-btn:hover {
        background: #513dd8;
        transform: translateY(-2px);
    }
    
    .no-courses {
        background: var(--card-light);
        border-radius: 16px;
        box-shadow: var(--shadow-light);
        padding: 50px 30px;
        text-align: center;
        color: var(--text-secondary);
    }
    
    .dark-mode .no-courses {
        background: var(--card-dark);
        box-shadow: var(--shadow-dark);
    }
    
    .no-courses i {
        font-size: 3rem;
        color: var(--primary-color);
        margin-bottom: 20px;
    }
    
    .no-courses h3 {
        font-size: 1.5rem;
        margin-bottom: 15px;
        color: var(--text-dark);
    }
    
    .dark-mode .no-courses h3 {
        color: var(--text-light);
    }
    
    .no-courses p {
        margin-bottom: 25px;
    }
";

// Set page title and specific CSS for header
$page_title = 'Quản Lý Bài Giảng';

// Include header
include_once ROOT_DIR . '/app/includes/header.php';
?>

<h2>Quản Lý Bài Giảng</h2>

<div class="page-actions">
    <a href="<?php echo BASE_URL; ?>app/views/product/create_course.php" class="add-course-btn">
        <i class="fas fa-plus"></i> Tạo Khóa Học Mới
    </a>
</div>

<?php if (count($courseLectures) > 0): ?>
    <?php foreach ($courseLectures as $courseData): ?>
        <div class="course-section">
            <div class="course-header">
                <?php if (!empty($courseData['course']['image'])): ?>
                    <img src="<?php echo BASE_URL . htmlspecialchars($courseData['course']['image']); ?>" alt="<?php echo htmlspecialchars($courseData['course']['title']); ?>" class="course-image">
                <?php else: ?>
                    <img src="<?php echo BASE_URL; ?>public/images/course-placeholder.jpg" alt="Course Thumbnail" class="course-image">
                <?php endif; ?>
                
                <div class="course-info">
                    <h3 class="course-title"><?php echo htmlspecialchars($courseData['course']['title']); ?></h3>
                </div>
                
                <div class="course-actions">
                    <a href="<?php echo BASE_URL; ?>app/views/product/lecture_management.php?course_id=<?php echo $courseData['course']['course_id']; ?>">
                        <i class="fas fa-plus"></i> Thêm Bài Giảng
                    </a>
                    <a href="<?php echo BASE_URL; ?>app/views/product/course_management.php?course_id=<?php echo $courseData['course']['course_id']; ?>">
                        <i class="fas fa-cog"></i> Quản Lý Khóa Học
                    </a>
                </div>
            </div>
            
            <?php if (count($courseData['lectures']) > 0): ?>
                <table class="lesson-table">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Tiêu Đề</th>
                            <th>Thời Lượng</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courseData['lectures'] as $lecture): ?>
                            <tr>
                                <td><?php echo $lecture['order_index']; ?></td>
                                <td><?php echo htmlspecialchars($lecture['title']); ?></td>
                                <td><?php echo $lecture['duration']; ?> phút</td>
                                <td class="lesson-actions">
                                    <a href="<?php echo BASE_URL; ?>app/views/product/lecture_management.php?course_id=<?php echo $courseData['course']['course_id']; ?>&lecture_id=<?php echo $lecture['lesson_id']; ?>" title="Chỉnh sửa">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-lessons">
                    <p>Chưa có bài giảng nào trong khóa học này.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="no-courses">
        <i class="fas fa-book"></i>
        <h3>Bạn chưa có khóa học nào</h3>
        <p>Hãy tạo một khóa học mới để bắt đầu thêm bài giảng.</p>
        <a href="<?php echo BASE_URL; ?>app/views/product/create_course.php" class="add-course-btn">
            <i class="fas fa-plus"></i> Tạo Khóa Học Mới
        </a>
    </div>
<?php endif; ?>

<?php
// Include footer
include_once ROOT_DIR . '/app/includes/footer.php';
?> 