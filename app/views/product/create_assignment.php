<?php
declare(strict_types=1);

// Configuration loading
if (!defined('BASE_URL')) {
    define('BASE_URL', '/WebCourses/');
}

// Define root directory if not already defined
if (!defined('ROOT_DIR')) {
    // Normalize path with proper directory separators for the OS
    $root = dirname(__DIR__, 3);
    // Ensure path has trailing slash
    $root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
    define('ROOT_DIR', $root);
}

// Include the database connection
require_once ROOT_DIR . '/app/config/connect.php';

// Start session and check if user is logged in as instructor
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Validate user authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    // Log access attempt
    error_log("Unauthorized access attempt to create_assignment.php by IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    header("Location: " . BASE_URL . "login.php");
    exit;
}

// Get instructor information
$instructor_id = (int)$_SESSION['user_id'];
try {
    $instructorQuery = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $instructorQuery->bind_param("i", $instructor_id);
    $instructorQuery->execute();
    $result = $instructorQuery->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Invalid instructor ID");
    }
    
    $instructor = $result->fetch_assoc();
    
    // Get courses taught by the instructor
    $coursesQuery = $conn->prepare("SELECT * FROM courses WHERE instructor_id = ?");
    $coursesQuery->bind_param("i", $instructor_id);
    $coursesQuery->execute();
    $courses = $coursesQuery->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Database error in create_assignment.php: " . $e->getMessage());
    header("Location: " . BASE_URL . "app/views/product/error.php?message=" . urlencode("Database error occurred"));
    exit;
}

// Prepare variables for form
$title = $description = '';
$course_id = $lesson_id = $max_score = 0;
$due_date = date('Y-m-d', strtotime('+1 week'));
$due_time = '23:59';
$is_published = 1;
$edit_mode = false;
$assignment_id = 0;
$errors = [];

// Check if we're editing an existing assignment
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $assignment_id = (int)$_GET['id'];
    $edit_mode = true;
    
    try {
        // Fetch the assignment
        $stmt = $conn->prepare("SELECT * FROM assignments WHERE assignment_id = ? AND course_id IN (SELECT course_id FROM courses WHERE instructor_id = ?)");
        $stmt->bind_param("ii", $assignment_id, $instructor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $assignment = $result->fetch_assoc();
            $title = $assignment['title'];
            $description = $assignment['description'];
            $course_id = (int)$assignment['course_id'];
            $lesson_id = $assignment['lesson_id'] ? (int)$assignment['lesson_id'] : null;
            $max_score = (int)$assignment['max_score'];
            
            // Format date and time
            if (!empty($assignment['due_date'])) {
                $due_datetime = new DateTime($assignment['due_date']);
                $due_date = $due_datetime->format('Y-m-d');
                $due_time = $due_datetime->format('H:i');
            }
            
            $is_published = isset($assignment['is_published']) ? (int)$assignment['is_published'] : 1;
        } else {
            // Assignment not found or doesn't belong to this instructor
            error_log("Instructor ID {$instructor_id} attempted to edit unauthorized assignment ID {$assignment_id}");
            header("Location: " . BASE_URL . "app/views/product/assignments.php?error=unauthorized");
            exit;
        }
    } catch (Exception $e) {
        error_log("Database error in create_assignment.php: " . $e->getMessage());
        header("Location: " . BASE_URL . "app/views/product/assignments.php?error=database");
        exit;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF validation failed in create_assignment.php for user {$instructor_id}");
        $errors[] = "Security validation failed. Please try again.";
    } else {
        // Get and sanitize form data
        $title = filter_var(trim($_POST['title'] ?? ''), FILTER_SANITIZE_STRING);
        $description = filter_var(trim($_POST['description'] ?? ''), FILTER_SANITIZE_STRING);
        $course_id = filter_var($_POST['course'] ?? 0, FILTER_VALIDATE_INT);
        $lesson_id = !empty($_POST['lesson']) ? filter_var($_POST['lesson'], FILTER_VALIDATE_INT) : null;
        $max_score = filter_var($_POST['max_score'] ?? 0, FILTER_VALIDATE_INT);
        $due_date = filter_var($_POST['due_date'] ?? '', FILTER_SANITIZE_STRING);
        $due_time = filter_var($_POST['due_time'] ?? '', FILTER_SANITIZE_STRING);
        $is_published = isset($_POST['is_published']) ? 1 : 0;
        
        // Validate form data
        if (empty($title)) {
            $errors[] = "Vui lòng nhập tiêu đề bài tập";
        } elseif (strlen($title) > 255) {
            $errors[] = "Tiêu đề quá dài (tối đa 255 ký tự)";
        }
        
        if (!$course_id) {
            $errors[] = "Vui lòng chọn khóa học";
        } else {
            // Verify that this course belongs to the instructor
            $courseCheck = $conn->prepare("SELECT course_id FROM courses WHERE course_id = ? AND instructor_id = ?");
            $courseCheck->bind_param("ii", $course_id, $instructor_id);
            $courseCheck->execute();
            if ($courseCheck->get_result()->num_rows === 0) {
                $errors[] = "Khóa học không hợp lệ";
                error_log("Instructor {$instructor_id} attempted to add assignment to unauthorized course {$course_id}");
            }
        }
        
        if ($max_score === false || $max_score < 0) {
            $errors[] = "Điểm tối đa phải là số dương";
        }
        
        // Validate date format
        if (!empty($due_date) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $due_date)) {
            $errors[] = "Định dạng ngày không hợp lệ";
        }
        
        // Validate time format
        if (!empty($due_time) && !preg_match("/^\d{2}:\d{2}$/", $due_time)) {
            $errors[] = "Định dạng giờ không hợp lệ";
        }
        
        // Combine date and time
        $due_datetime = !empty($due_date) ? $due_date . ' ' . $due_time . ':00' : null;
        
        if (empty($errors)) {
            try {
                if ($edit_mode) {
                    // Update existing assignment
                    $stmt = $conn->prepare("UPDATE assignments SET course_id = ?, lesson_id = ?, title = ?, description = ?, due_date = ?, max_score = ?, is_published = ?, updated_at = NOW() WHERE assignment_id = ? AND course_id IN (SELECT course_id FROM courses WHERE instructor_id = ?)");
                    $stmt->bind_param("iisssdiiii", $course_id, $lesson_id, $title, $description, $due_datetime, $max_score, $is_published, $assignment_id, $instructor_id);
                } else {
                    // Create new assignment
                    $stmt = $conn->prepare("INSERT INTO assignments (course_id, lesson_id, title, description, due_date, max_score, is_published, created_at, updated_at) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                    $stmt->bind_param("iisssdi", $course_id, $lesson_id, $title, $description, $due_datetime, $max_score, $is_published);
                }
                
                if ($stmt->execute()) {
                    // Generate success message
                    $success_type = $edit_mode ? 'updated' : 'created';
                    
                    // Log the action
                    error_log("Assignment " . ($edit_mode ? "updated" : "created") . " by instructor {$instructor_id}: " . ($edit_mode ? $assignment_id : $conn->insert_id));
                    
                    // Redirect to assignments page
                    header("Location: " . BASE_URL . "app/views/product/assignments.php?success=" . $success_type);
                    exit;
                } else {
                    $errors[] = "Đã xảy ra lỗi: " . $conn->error;
                    error_log("Database error in create_assignment.php: " . $conn->error);
                }
            } catch (Exception $e) {
                $errors[] = "Đã xảy ra lỗi trong quá trình xử lý";
                error_log("Exception in create_assignment.php: " . $e->getMessage());
            }
        }
    }
}

// Get lessons for course selection
$lessons_by_course = [];
try {
    foreach ($courses as $course) {
        $course_id_for_lessons = (int)$course['course_id'];
        $lessonsQuery = $conn->prepare("SELECT lesson_id, title FROM lessons WHERE course_id = ?");
        $lessonsQuery->bind_param("i", $course_id_for_lessons);
        $lessonsQuery->execute();
        $lessons_by_course[$course_id_for_lessons] = $lessonsQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching lessons in create_assignment.php: " . $e->getMessage());
    // Continue without lessons if there's an error
}

// Generate new CSRF token if needed
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($edit_mode ? 'Chỉnh sửa bài tập' : 'Tạo bài tập mới'); ?> | WebCourses</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL); ?>public/css/main.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL); ?>public/css/instructor_dashboard.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL); ?>public/css/assignments.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- CSP Security Header -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com">
</head>
<body>
    <!-- Header -->
    <?php include ROOT_DIR . '/app/includes/header.php'; ?>
    
    <div class="container">
        <div class="breadcrumb">
            <a href="<?php echo htmlspecialchars(BASE_URL); ?>app/views/product/instructor_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span>/</span>
            <a href="<?php echo htmlspecialchars(BASE_URL); ?>app/views/product/assignments.php">Bài tập & Đánh giá</a>
            <span>/</span>
            <span><?php echo htmlspecialchars($edit_mode ? 'Chỉnh sửa bài tập' : 'Tạo bài tập mới'); ?></span>
        </div>
        
        <div class="content-wrapper">
            <div class="page-header">
                <h1><?php echo htmlspecialchars($edit_mode ? 'Chỉnh sửa bài tập' : 'Tạo bài tập mới'); ?></h1>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        
                        <div class="form-group">
                            <label for="title">Tiêu đề <span class="required">*</span></label>
                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="course">Khóa học <span class="required">*</span></label>
                                <select id="course" name="course" required>
                                    <option value="">-- Chọn khóa học --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['course_id']; ?>" <?php echo $course['course_id'] == $course_id ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group col-md-6">
                                <label for="lesson">Bài học (tùy chọn)</label>
                                <select id="lesson" name="lesson" <?php echo empty($course_id) ? 'disabled' : ''; ?>>
                                    <option value="">-- Chọn bài học --</option>
                                    <?php if (!empty($course_id) && !empty($lessons_by_course[$course_id])): ?>
                                        <?php foreach ($lessons_by_course[$course_id] as $lesson): ?>
                                            <option value="<?php echo $lesson['lesson_id']; ?>" <?php echo $lesson['lesson_id'] == $lesson_id ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($lesson['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Mô tả</label>
                            <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="due_date">Hạn nộp (ngày)</label>
                                <input type="date" id="due_date" name="due_date" value="<?php echo $due_date; ?>">
                            </div>
                            
                            <div class="form-group col-md-4">
                                <label for="due_time">Giờ nộp</label>
                                <input type="time" id="due_time" name="due_time" value="<?php echo $due_time; ?>">
                            </div>
                            
                            <div class="form-group col-md-4">
                                <label for="max_score">Điểm tối đa <span class="required">*</span></label>
                                <input type="number" id="max_score" name="max_score" value="<?php echo $max_score; ?>" required min="0" step="1">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_published" <?php echo $is_published ? 'checked' : ''; ?>>
                                <span>Xuất bản ngay (hiển thị với học viên)</span>
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <a href="<?php echo htmlspecialchars(BASE_URL); ?>app/views/product/assignments.php" class="btn btn-secondary">Hủy</a>
                            <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($edit_mode ? 'Cập nhật' : 'Tạo bài tập'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include ROOT_DIR . '/app/includes/footer.php'; ?>
    
    <script>
        // Pass lesson data to JavaScript
        const courseLessons = <?php echo json_encode($lessons_by_course); ?>;
    </script>
    <script src="<?php echo htmlspecialchars(BASE_URL); ?>public/js/assignments.js"></script>
</body>
</html> 