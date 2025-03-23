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

// Include the FileUploadController
require_once ROOT_DIR . '/app/controllers/FileUploadController.php';
$fileUploadController = new FileUploadController($conn);

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
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$lecture_id = isset($_GET['lecture_id']) ? intval($_GET['lecture_id']) : 0;

// Check if course exists and belongs to the instructor
$stmt = $conn->prepare("SELECT title FROM courses WHERE course_id = ? AND instructor_id = ?");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    $_SESSION['error'] = "Không thể tải thông tin khóa học.";
    header("Location: instructor_dashboard.php");
    exit();
}

$stmt->bind_param("ii", $course_id, $user_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    $_SESSION['error'] = "Không thể tải thông tin khóa học.";
    header("Location: instructor_dashboard.php");
    exit();
}

$result = $stmt->get_result();
$course = $result->fetch_assoc();

if (!$course) {
    $_SESSION['error'] = "Khóa học không tồn tại hoặc bạn không có quyền truy cập.";
    header("Location: instructor_dashboard.php");
    exit();
}

// Get full course details
$stmt = $conn->prepare("SELECT course_id, title, description, image, level, language, price FROM courses WHERE course_id = ? AND instructor_id = ?");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    $_SESSION['error'] = "Không thể tải thông tin khóa học.";
    header("Location: instructor_dashboard.php");
    exit();
}

$stmt->bind_param("ii", $course_id, $user_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    $_SESSION['error'] = "Không thể tải thông tin khóa học.";
    header("Location: instructor_dashboard.php");
    exit();
}

$result = $stmt->get_result();
$course = $result->fetch_assoc();

if (!$course) {
    $_SESSION['error'] = "Khóa học không tồn tại hoặc bạn không có quyền truy cập.";
    header("Location: instructor_dashboard.php");
    exit();
}

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

// Helper function to get the appropriate Font Awesome icon for file types
function getFileIcon($file_type) {
    switch (strtolower($file_type)) {
        case 'pdf':
            return 'fa-file-pdf';
        case 'doc':
        case 'docx':
        case 'odt':
        case 'txt':
            return 'fa-file-word';
        case 'xls':
        case 'xlsx':
        case 'csv':
            return 'fa-file-excel';
        case 'ppt':
        case 'pptx':
        case 'odp':
        case 'key':
            return 'fa-file-powerpoint';
        case 'zip':
        case 'rar':
            return 'fa-file-archive';
        case 'png':
        case 'jpg':
        case 'jpeg':
        case 'gif':
            return 'fa-file-image';
        case 'mp4':
        case 'webm':
        case 'avi':
        case 'mov':
        case 'wmv':
            return 'fa-file-video';
        default:
            return 'fa-file';
    }
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle adding a new lecture
    if (isset($_POST['add_lecture'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $order_index = intval($_POST['order_index']);
        $duration = intval($_POST['duration']);
        
        if (empty($title)) {
            $error = "Tiêu đề bài giảng không được để trống.";
        } else {
            $stmt = $conn->prepare("INSERT INTO lessons (course_id, title, content, order_index, duration) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issis", $course_id, $title, $content, $order_index, $duration);
            
            if ($stmt->execute()) {
                $lecture_id = $conn->insert_id;
                $success = "Bài giảng đã được thêm thành công!";
                
                // Handle file uploads if present
                if (!empty($_FILES['document_file']['name'])) {
                    $upload_result = $fileUploadController->uploadFile($_FILES['document_file'], $lecture_id, 'document');
                    if (!$upload_result['success']) {
                        $error = $upload_result['message'];
                    }
                }
                
                if (!empty($_FILES['video_file']['name'])) {
                    $upload_result = $fileUploadController->uploadFile($_FILES['video_file'], $lecture_id, 'video');
                    if (!$upload_result['success']) {
                        $error = $upload_result['message'];
                    }
                }
                
                if (!empty($_FILES['presentation_file']['name'])) {
                    $upload_result = $fileUploadController->uploadFile($_FILES['presentation_file'], $lecture_id, 'presentation');
                    if (!$upload_result['success']) {
                        $error = $upload_result['message'];
                    }
                }
                
                if (!empty($_FILES['additional_file']['name'])) {
                    $upload_result = $fileUploadController->uploadFile($_FILES['additional_file'], $lecture_id, 'additional');
                    if (!$upload_result['success']) {
                        $error = $upload_result['message'];
                    }
                }
            } else {
                $error = "Không thể thêm bài giảng: " . $stmt->error;
                error_log("Insert lesson failed: " . $stmt->error);
            }
        }
    }
    
    // Handle updating an existing lecture
    if (isset($_POST['update_lecture'])) {
        $lecture_id = intval($_POST['lecture_id']);
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $order_index = intval($_POST['order_index']);
        $duration = intval($_POST['duration']);
        
        if (empty($title)) {
            $error = "Tiêu đề bài giảng không được để trống.";
        } else {
            // Validate order_index to ensure it's positive
            if ($order_index <= 0) {
                $order_index = 1;
            }
            
            // Validate duration to ensure it's positive
            if ($duration <= 0) {
                $duration = 15; // Default to 15 minutes
            }
            
            $stmt = $conn->prepare("UPDATE lessons SET title = ?, content = ?, order_index = ?, duration = ? WHERE lesson_id = ? AND course_id = ?");
            $stmt->bind_param("ssiiii", $title, $content, $order_index, $duration, $lecture_id, $course_id);
            
            if ($stmt->execute()) {
                $success = "Bài giảng đã được cập nhật thành công!";
                
                // Handle document file upload
                if (!empty($_FILES['document_file']['name'])) {
                    $upload_result = $fileUploadController->uploadFile($_FILES['document_file'], $lecture_id, 'document');
                    if (!$upload_result['success']) {
                        $error = $upload_result['message'];
                    } else {
                        $success .= " Tài liệu đã được tải lên.";
                    }
                }
                
                // Handle video file upload
                if (!empty($_FILES['video_file']['name'])) {
                    $upload_result = $fileUploadController->uploadFile($_FILES['video_file'], $lecture_id, 'video');
                    if (!$upload_result['success']) {
                        $error = $upload_result['message'];
                    } else {
                        $success .= " Video đã được tải lên.";
                    }
                }
                
                // Handle presentation file upload
                if (!empty($_FILES['presentation_file']['name'])) {
                    $upload_result = $fileUploadController->uploadFile($_FILES['presentation_file'], $lecture_id, 'presentation');
                    if (!$upload_result['success']) {
                        $error = $upload_result['message'];
                    } else {
                        $success .= " Bài trình bày đã được tải lên.";
                    }
                }
                
                // Handle additional file upload
                if (!empty($_FILES['additional_file']['name'])) {
                    $upload_result = $fileUploadController->uploadFile($_FILES['additional_file'], $lecture_id, 'additional');
                    if (!$upload_result['success']) {
                        $error = $upload_result['message'];
                    } else {
                        $success .= " Tệp bổ sung đã được tải lên.";
                    }
                }
                
                // Refresh the lecture data after update
                $stmt = $conn->prepare("SELECT * FROM lessons WHERE lesson_id = ? AND course_id = ?");
                $stmt->bind_param("ii", $lecture_id, $course_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $editLecture = $result->fetch_assoc();
                
                if (!empty($editLecture['additional_files'])) {
                    // Parse additional files JSON
                    $editLecture['additional_files_array'] = json_decode($editLecture['additional_files'], true);
                }
            } else {
                $error = "Không thể cập nhật bài giảng: " . $stmt->error;
                error_log("Update lesson failed: " . $stmt->error);
            }
        }
    }
    
    // Handle reordering lectures
    if (isset($_POST['reorder_lectures']) && isset($_POST['lesson_ids']) && isset($_POST['order_indices'])) {
        $lesson_ids = $_POST['lesson_ids'];
        $order_indices = $_POST['order_indices'];
        
        if (count($lesson_ids) === count($order_indices)) {
            try {
                // Begin transaction
                $conn->begin_transaction();
                
                // Prepare statement
                $update_stmt = $conn->prepare("
                    UPDATE lessons 
                    SET order_index = ? 
                    WHERE lesson_id = ?
                ");
                
                // Update each lesson's order
                for ($i = 0; $i < count($lesson_ids); $i++) {
                    $lesson_id = intval($lesson_ids[$i]);
                    $order_index = intval($order_indices[$i]);
                    
                    $update_stmt->bind_param("ii", $order_index, $lesson_id);
                    $update_stmt->execute();
                }
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['success'] = "Thứ tự bài giảng đã được cập nhật thành công.";
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                error_log("Lỗi khi cập nhật thứ tự bài giảng: " . $e->getMessage());
                $_SESSION['error'] = "Đã xảy ra lỗi khi cập nhật thứ tự bài giảng.";
            }
        } else {
            $_SESSION['error'] = "Dữ liệu không hợp lệ khi cập nhật thứ tự bài giảng.";
        }
        
        // Redirect back to the lecture management page
        header("Location: " . BASE_URL . "app/views/product/lecture_management.php?course_id=$course_id");
        exit();
    }
    
    // Handle deleting a lecture
    if (isset($_POST['delete_lecture'])) {
        $lecture_id = intval($_POST['lecture_id']);
        
        // First delete associated files
        $stmt = $conn->prepare("SELECT document_file, video_file, presentation_file, additional_files FROM lessons WHERE lesson_id = ? AND course_id = ?");
        $stmt->bind_param("ii", $lecture_id, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $lecture = $result->fetch_assoc();
        
        if ($lecture) {
            // Delete document file if exists
            if (!empty($lecture['document_file'])) {
                $fileUploadController->removeFile($lecture_id, 'document');
            }
            
            // Delete video file if exists
            if (!empty($lecture['video_file'])) {
                $fileUploadController->removeFile($lecture_id, 'video');
            }
            
            // Delete presentation file if exists
            if (!empty($lecture['presentation_file'])) {
                $fileUploadController->removeFile($lecture_id, 'presentation');
            }
            
            // Delete additional files if exist
            if (!empty($lecture['additional_files'])) {
                $files = json_decode($lecture['additional_files'], true);
                foreach ($files as $index => $file) {
                    $fileUploadController->removeFile($lecture_id, 'additional', $index);
                }
            }
        }
        
        // Now delete the lecture record
        $stmt = $conn->prepare("DELETE FROM lessons WHERE lesson_id = ? AND course_id = ?");
        $stmt->bind_param("ii", $lecture_id, $course_id);
        
        if ($stmt->execute()) {
            $success = "Bài giảng đã được xóa thành công!";
        } else {
            $error = "Không thể xóa bài giảng: " . $stmt->error;
            error_log("Delete lesson failed: " . $stmt->error);
        }
    }
    
    // Handle removing a specific file
    if (isset($_POST['remove_file'])) {
        $lecture_id = intval($_POST['lecture_id']);
        $file_type = $_POST['file_type'];
        $file_index = isset($_POST['file_index']) ? intval($_POST['file_index']) : null;
        
        $remove_result = $fileUploadController->removeFile($lecture_id, $file_type, $file_index);
        
        if ($remove_result['success']) {
            $success = $remove_result['message'];
        } else {
            $error = $remove_result['message'];
        }
    }
}

// Fetch lecture details if editing
$editLecture = null;
if ($lecture_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM lessons WHERE lesson_id = ? AND course_id = ?");
    $stmt->bind_param("ii", $lecture_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $editLecture = $result->fetch_assoc();
    
    if (!$editLecture) {
        $error = "Bài giảng không tồn tại hoặc không thuộc khóa học này.";
    } else if (!empty($editLecture['additional_files'])) {
        // Parse additional files JSON
        $editLecture['additional_files_array'] = json_decode($editLecture['additional_files'], true);
    }
}

// Get all lectures for this course
$stmt = $conn->prepare("
    SELECT * FROM lessons
    WHERE course_id = ?
    ORDER BY order_index ASC
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$lessons = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Bài Giảng - <?php echo htmlspecialchars($course['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/instructor_dashboard.css">
    <style>
        .lessons-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .lesson-card {
            background: var(--card-light);
            border-radius: 16px;
            box-shadow: var(--shadow-light);
            padding: 20px;
            position: relative;
            transition: all 0.3s;
        }
        
        .dark-mode .lesson-card {
            background: var(--card-dark);
            box-shadow: var(--shadow-dark);
        }
        
        .lesson-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
        }
        
        /* Course Details Card Styles */
        .course-details-card {
            background: var(--card-light);
            border-radius: 16px;
            box-shadow: var(--shadow-light);
            padding: 20px;
            margin-bottom: 25px;
            position: relative;
        }
        
        .dark-mode .course-details-card {
            background: var(--card-dark);
            box-shadow: var(--shadow-dark);
        }
        
        .course-details-header {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .course-image-container {
            flex-shrink: 0;
            width: 180px;
            height: 120px;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .course-detail-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .course-details-info {
            flex: 1;
        }
        
        .course-details-info h3 {
            margin: 0 0 10px 0;
            color: var(--text-dark);
            font-size: 1.4rem;
        }
        
        .dark-mode .course-details-info h3 {
            color: var(--text-light);
        }
        
        .course-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 12px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .course-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .course-meta i {
            color: var(--primary-color);
        }
        
        .course-description {
            color: var(--text-dark);
            line-height: 1.5;
            margin: 0;
            font-size: 0.95rem;
        }
        
        .dark-mode .course-description {
            color: var(--text-light);
        }
        
        .course-lesson-counts {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(0,0,0,0.1);
        }
        
        .dark-mode .course-lesson-counts {
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .counts-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: rgba(98, 74, 242, 0.1);
            padding: 10px 15px;
            border-radius: 8px;
            min-width: 80px;
        }
        
        .counts-item .count {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .counts-item .label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 3px;
        }
        
        @media (max-width: 768px) {
            .course-details-header {
                flex-direction: column;
            }
            
            .course-image-container {
                width: 100%;
                height: 160px;
            }
        }
        
        /* Existing lesson styles */
        .lesson-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .lesson-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
        }
        
        .dark-mode .lesson-title {
            color: var(--text-light);
        }
        
        .lesson-actions {
            display: flex;
            gap: 10px;
        }
        
        .lesson-actions a, .lesson-actions button {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-size: 0.9rem;
            padding: 5px;
            transition: all 0.2s;
        }
        
        .lesson-actions a:hover, .lesson-actions button:hover {
            color: #513dd8;
            transform: scale(1.1);
        }
        
        .lesson-actions .delete-btn {
            color: #e74c3c;
        }
        
        .lesson-actions .delete-btn:hover {
            color: #c0392b;
        }
        
        .lesson-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .lesson-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .lesson-content {
            color: var(--text-dark);
            line-height: 1.6;
            margin-top: 15px;
        }
        
        .dark-mode .lesson-content {
            color: var(--text-light);
        }
        
        .lesson-form {
            background: var(--card-light);
            border-radius: 16px;
            box-shadow: var(--shadow-light);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .dark-mode .lesson-form {
            background: var(--card-dark);
            box-shadow: var(--shadow-dark);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .dark-mode .form-group label {
            color: var(--text-light);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            font-size: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: var(--card-light);
            color: var(--text-dark);
            transition: all 0.3s;
        }
        
        .dark-mode .form-control {
            background: var(--card-dark);
            color: var(--text-light);
            border-color: #444;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(98, 74, 242, 0.2);
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .btn-submit {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            background: #513dd8;
            transform: translateY(-2px);
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            margin-left: 10px;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .no-lessons {
            background: var(--card-light);
            border-radius: 16px;
            box-shadow: var(--shadow-light);
            padding: 30px;
            text-align: center;
            color: var(--text-secondary);
        }
        
        .dark-mode .no-lessons {
            background: var(--card-dark);
            box-shadow: var(--shadow-dark);
        }
        
        .success-message, .error-message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .success-message {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .dark-mode .success-message {
            background: rgba(34, 197, 94, 0.2);
        }
        
        .dark-mode .error-message {
            background: rgba(239, 68, 68, 0.2);
        }
        
        .lesson-attachments {
            margin-top: 15px;
            border-top: 1px solid #e0e0e0;
            padding-top: 10px;
        }
        
        .lesson-attachments h5 {
            font-size: 14px;
            margin-bottom: 8px;
            color: #555;
        }
        
        .attachment-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .attachment-item {
            font-size: 13px;
            background-color: #f5f5f5;
            border-radius: 4px;
            padding: 5px 10px;
            display: flex;
            align-items: center;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .attachment-item i {
            margin-right: 5px;
            color: #0056b3;
        }
        
        .attachment-item a {
            color: #333;
            text-decoration: none;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .attachment-item a:hover {
            text-decoration: underline;
        }
        
        .more-files {
            background-color: #e8f4ff;
            cursor: pointer;
        }
        
        .more-files:hover {
            background-color: #d1e9ff;
        }
        
        .file-display {
            margin-top: 8px;
            padding: 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .file-display span {
            display: flex;
            align-items: center;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 80%;
        }
        
        .file-display span i {
            margin-right: 8px;
            color: #0056b3;
        }
        
        .file-display .file-actions {
            display: flex;
            gap: 8px;
        }
        
        .file-actions a, .file-actions button {
            background: none;
            border: none;
            color: #0056b3;
            cursor: pointer;
            padding: 0;
            font-size: 14px;
        }
        
        .file-actions button.delete-file {
            color: #dc3545;
        }
        
        .file-actions a:hover, .file-actions button:hover {
            opacity: 0.8;
        }
        
        .file-input {
            margin-bottom: 10px;
        }
        
        /* Additional files details styles */
        .files-details {
            margin-top: 10px;
            padding: 12px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #e9ecef;
            position: relative;
            max-width: 100%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .files-details .close-details {
            position: absolute;
            top: 8px;
            right: 8px;
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 0;
            font-size: 14px;
        }
        
        .files-details .close-details:hover {
            color: #343a40;
        }
        
        .files-details .file-item {
            margin-bottom: 8px;
            padding: 6px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
        }
        
        .files-details .file-item:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }
        
        .files-details .file-item i {
            margin-right: 10px;
            color: #0056b3;
            font-size: 16px;
        }
        
        .files-details .file-item a {
            color: #333;
            text-decoration: none;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .files-details .file-item a:hover {
            text-decoration: underline;
            color: #0056b3;
        }
        
        /* Lesson Order Styles */
        .lesson-order-toolbar {
            background: var(--card-light);
            border-radius: 16px;
            box-shadow: var(--shadow-light);
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }
        
        .dark-mode .lesson-order-toolbar {
            background: var(--card-dark);
            box-shadow: var(--shadow-dark);
        }
        
        .lesson-order-toolbar h4 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.1rem;
        }
        
        .lesson-order-toolbar p {
            margin: 0;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .btn-order-save {
            padding: 8px 15px;
            font-size: 0.9rem;
        }
        
        .lesson-order-controls {
            display: flex;
            align-items: center;
            position: absolute;
            top: -10px;
            right: -10px;
            background: var(--primary-color);
            border-radius: 20px;
            padding: 5px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.15);
            z-index: 2;
        }
        
        .order-number {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            background: white;
            color: var(--primary-color);
            border-radius: 50%;
            font-weight: 600;
            font-size: 0.9rem;
            margin-right: 5px;
        }
        
        .order-buttons {
            display: flex;
            gap: 5px;
        }
        
        .order-btn {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .order-btn:hover {
            background: rgba(255,255,255,0.4);
            transform: scale(1.1);
        }
        
        .order-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .order-btn:disabled:hover {
            background: rgba(255,255,255,0.2);
            transform: none;
        }
        
        .btn-order-save.highlight {
            animation: pulse 1.5s infinite;
            background-color: #10b981;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }
        
        .sortable-lessons {
            position: relative;
        }
        
        .sortable-lessons .lesson-card {
            cursor: grab;
        }
        
        .sortable-lessons .lesson-card.dragging {
            opacity: 0.7;
            cursor: grabbing;
        }
    </style>
</head>
<body>
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
                <?php if ($unreadNotifs > 0): ?>
                <span class="badge"><?php echo $unreadNotifs; ?></span>
                <?php endif; ?>
            </div>
            <button class="mode-toggle" id="mode-toggle">
                <i class="fas fa-moon"></i>
            </button>
            <div class="teacher-name">Xin chào, <strong><?php echo htmlspecialchars($username); ?></strong></div>
            <form method="post" action="<?php echo BASE_URL; ?>auth/logout" style="display:inline;">
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Đăng Xuất
                </button>
            </form>
        </div>
    </header>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul>
            <li><a href="<?php echo BASE_URL; ?>app/views/product/instructor_dashboard.php"><i class="fas fa-tachometer-alt"></i> Tổng Quan</a></li>
            <li><a href="<?php echo BASE_URL; ?>app/views/product/create_course.php"><i class="fas fa-plus-circle"></i> Thêm Khoá Học</a></li>
            <li><a href="<?php echo BASE_URL; ?>app/views/product/lecture_list.php"><i class="fas fa-book"></i> Quản Lý Bài Giảng</a></li>
            <li><a href="#"><i class="fas fa-users"></i> Danh Sách Học Viên</a></li>
            <li><a href="#"><i class="fas fa-tasks"></i> Bài Tập & Đánh Giá</a></li>
            <li><a href="#"><i class="fas fa-comments"></i> Thảo Luận</a></li>
            <li><a href="#"><i class="fas fa-certificate"></i> Chứng Chỉ</a></li>
            <li><a href="#"><i class="fas fa-bell"></i> Thông Báo</a></li>
            <li><a href="#"><i class="fas fa-chart-line"></i> Phân Tích Dữ Liệu</a></li>
            <li><a href="#"><i class="fas fa-money-bill-wave"></i> Thu Nhập</a></li>
            <li><a href="#"><i class="fas fa-cog"></i> Cài Đặt</a></li>
            <li><a href="#"><i class="fas fa-question-circle"></i> Hỗ Trợ</a></li>
        </ul>
        <div class="sidebar-footer">
            © 2025 Học Tập Trực Tuyến
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="course-nav">
            <a href="<?php echo BASE_URL; ?>app/views/product/instructor_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Trở Về Tổng Quan
            </a>
            <span class="separator">|</span>
            <a href="<?php echo BASE_URL; ?>app/views/product/course_management.php?course_id=<?php echo $course_id; ?>" class="back-link">
                <i class="fas fa-cog"></i> Quản Lý Khóa Học
            </a>
        </div>
        
        <h2>Quản Lý Bài Giảng: <?php echo htmlspecialchars($course['title']); ?></h2>
        
        <!-- Course Details Card -->
        <div class="course-details-card">
            <div class="course-details-header">
                <div class="course-image-container">
                    <?php if (!empty($course['image'])): ?>
                        <img src="<?php echo BASE_URL . htmlspecialchars($course['image']); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>" class="course-detail-image">
                    <?php else: ?>
                        <img src="<?php echo BASE_URL; ?>public/images/course-placeholder.jpg" alt="Course Thumbnail" class="course-detail-image">
                    <?php endif; ?>
                </div>
                <div class="course-details-info">
                    <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                    <div class="course-meta">
                        <span><i class="fas fa-layer-group"></i> Cấp độ: <?php echo htmlspecialchars($course['level']); ?></span>
                        <span><i class="fas fa-language"></i> Ngôn ngữ: <?php echo htmlspecialchars($course['language']); ?></span>
                        <span><i class="fas fa-tags"></i> Giá: <?php echo number_format($course['price'], 0, ',', '.'); ?> VNĐ</span>
                    </div>
                    <p class="course-description"><?php echo htmlspecialchars(substr($course['description'], 0, 250)); ?><?php echo (strlen($course['description']) > 250) ? '...' : ''; ?></p>
                </div>
            </div>
            <div class="course-lesson-counts">
                <div class="counts-item">
                    <span class="count"><?php echo $lessons->num_rows; ?></span>
                    <span class="label">Bài Giảng</span>
                </div>
                <?php 
                // Calculate total duration
                $total_duration = 0;
                $stmt = $conn->prepare("SELECT SUM(duration) as total FROM lessons WHERE course_id = ?");
                $stmt->bind_param("i", $course_id);
                $stmt->execute();
                $durationResult = $stmt->get_result();
                $durationData = $durationResult->fetch_assoc();
                $total_duration = $durationData['total'] ?? 0;
                ?>
                <div class="counts-item">
                    <span class="count"><?php echo $total_duration; ?></span>
                    <span class="label">Phút</span>
                </div>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Lecture Form (Add/Edit) -->
        <div class="lesson-form">
            <h3><?php echo $editLecture ? 'Chỉnh Sửa Bài Giảng' : 'Thêm Bài Giảng Mới'; ?></h3>
            <form method="POST" enctype="multipart/form-data">
                <?php if ($editLecture): ?>
                    <input type="hidden" name="lecture_id" value="<?php echo $editLecture['lesson_id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="title">Tiêu Đề Bài Giảng</label>
                    <input type="text" id="title" name="title" class="form-control" 
                           value="<?php echo $editLecture ? htmlspecialchars($editLecture['title']) : ''; ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="order_index">Thứ Tự</label>
                        <input type="number" id="order_index" name="order_index" min="1" class="form-control" 
                               value="<?php echo $editLecture ? $editLecture['order_index'] : ($lessons->num_rows + 1); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="duration">Thời Lượng (phút)</label>
                        <input type="number" id="duration" name="duration" min="1" class="form-control" 
                               value="<?php echo $editLecture ? $editLecture['duration'] : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="content">Nội Dung Bài Giảng</label>
                    <textarea id="content" name="content" class="form-control"><?php echo $editLecture ? htmlspecialchars($editLecture['content']) : ''; ?></textarea>
                </div>
                
                <!-- Document File Upload -->
                <div class="form-group">
                    <label for="document_file">Tài Liệu (PDF, DOC, DOCX, TXT)</label>
                    <input type="file" id="document_file" name="document_file" class="form-control file-input" accept=".pdf,.doc,.docx,.txt,.odt">
                    
                    <?php if ($editLecture && !empty($editLecture['document_file'])): ?>
                        <div class="file-display">
                            <span><i class="fas fa-file-alt"></i> <?php echo basename($editLecture['document_file']); ?></span>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa tệp này?');">
                                <input type="hidden" name="lecture_id" value="<?php echo $editLecture['lesson_id']; ?>">
                                <input type="hidden" name="file_type" value="document">
                                <button type="submit" name="remove_file" class="file-remove-btn">
                                    <i class="fas fa-times"></i> Xóa
                                </button>
                            </form>
                            <a href="<?php echo BASE_URL . $editLecture['document_file']; ?>" target="_blank" class="file-view-btn">
                                <i class="fas fa-eye"></i> Xem
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Video File Upload -->
                <div class="form-group">
                    <label for="video_file">Video (MP4, WEBM, AVI, MOV)</label>
                    <input type="file" id="video_file" name="video_file" class="form-control file-input" accept=".mp4,.webm,.avi,.mov,.wmv">
                    
                    <?php if ($editLecture && !empty($editLecture['video_file'])): ?>
                        <div class="file-display">
                            <span><i class="fas fa-file-video"></i> <?php echo basename($editLecture['video_file']); ?></span>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa tệp này?');">
                                <input type="hidden" name="lecture_id" value="<?php echo $editLecture['lesson_id']; ?>">
                                <input type="hidden" name="file_type" value="video">
                                <button type="submit" name="remove_file" class="file-remove-btn">
                                    <i class="fas fa-times"></i> Xóa
                                </button>
                            </form>
                            <a href="<?php echo BASE_URL . $editLecture['video_file']; ?>" target="_blank" class="file-view-btn">
                                <i class="fas fa-eye"></i> Xem
                            </a>
                        </div>
                        
                        <div class="video-preview">
                            <video controls width="100%" height="auto">
                                <source src="<?php echo BASE_URL . $editLecture['video_file']; ?>" type="video/mp4">
                                Trình duyệt của bạn không hỗ trợ thẻ video.
                            </video>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Presentation File Upload -->
                <div class="form-group">
                    <label for="presentation_file">Bài Trình Bày (PPT, PPTX)</label>
                    <input type="file" id="presentation_file" name="presentation_file" class="form-control file-input" accept=".ppt,.pptx,.odp,.key">
                    
                    <?php if ($editLecture && !empty($editLecture['presentation_file'])): ?>
                        <div class="file-display">
                            <span><i class="fas fa-file-powerpoint"></i> <?php echo basename($editLecture['presentation_file']); ?></span>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa tệp này?');">
                                <input type="hidden" name="lecture_id" value="<?php echo $editLecture['lesson_id']; ?>">
                                <input type="hidden" name="file_type" value="presentation">
                                <button type="submit" name="remove_file" class="file-remove-btn">
                                    <i class="fas fa-times"></i> Xóa
                                </button>
                            </form>
                            <a href="<?php echo BASE_URL . $editLecture['presentation_file']; ?>" target="_blank" class="file-view-btn">
                                <i class="fas fa-eye"></i> Xem
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Additional Files Upload -->
                <div class="form-group">
                    <label for="additional_file">Tệp Bổ Sung (PNG, JPG, ZIP, RAR, XLSX, CSV)</label>
                    <input type="file" id="additional_file" name="additional_file" class="form-control file-input" accept=".zip,.rar,.png,.jpg,.jpeg,.gif,.xlsx,.xls,.csv">
                    
                    <?php if ($editLecture && !empty($editLecture['additional_files_array'])): ?>
                        <div class="additional-files">
                            <h4>Các Tệp Đã Tải Lên</h4>
                            <ul class="file-list">
                                <?php foreach ($editLecture['additional_files_array'] as $index => $file): ?>
                                    <li>
                                        <span>
                                            <i class="fas <?php echo getFileIcon($file['type']); ?>"></i> 
                                            <?php echo htmlspecialchars($file['name']); ?>
                                        </span>
                                        <div class="file-actions">
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa tệp này?');">
                                                <input type="hidden" name="lecture_id" value="<?php echo $editLecture['lesson_id']; ?>">
                                                <input type="hidden" name="file_type" value="additional">
                                                <input type="hidden" name="file_index" value="<?php echo $index; ?>">
                                                <button type="submit" name="remove_file" class="file-remove-btn">
                                                    <i class="fas fa-times"></i> Xóa
                                                </button>
                                            </form>
                                            <a href="<?php echo BASE_URL . $file['path']; ?>" target="_blank" class="file-view-btn">
                                                <i class="fas fa-eye"></i> Xem
                                            </a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <?php if ($editLecture): ?>
                        <button type="submit" name="update_lecture" class="btn-submit">
                            <i class="fas fa-save"></i> Cập Nhật Bài Giảng
                        </button>
                        <a href="<?php echo BASE_URL; ?>app/views/product/lecture_management.php?course_id=<?php echo $course_id; ?>" class="btn-cancel">
                            <i class="fas fa-times"></i> Hủy
                        </a>
                    <?php else: ?>
                        <button type="submit" name="add_lecture" class="btn-submit">
                            <i class="fas fa-plus"></i> Thêm Bài Giảng
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Lessons List -->
        <h3>Danh Sách Bài Giảng</h3>
        
        <?php if ($lessons->num_rows > 0): ?>
            <div class="lesson-order-toolbar">
                <h4>Sắp xếp bài giảng</h4>
                <p>Dùng các nút ↑ và ↓ để sắp xếp thứ tự các bài giảng trong khóa học này.</p>
                <form method="POST" class="order-form">
                    <input type="hidden" name="reorder_lectures" value="1">
                    <button type="submit" class="btn-submit btn-order-save">
                        <i class="fas fa-save"></i> Lưu Thứ Tự
                    </button>
                </form>
            </div>
            
            <div class="lessons-container sortable-lessons">
                <?php 
                // Re-fetch lessons to ensure latest order
                $stmt = $conn->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index ASC");
                $stmt->bind_param("i", $course_id);
                $stmt->execute();
                $lessons = $stmt->get_result();
                
                while ($lesson = $lessons->fetch_assoc()): 
                ?>
                    <div class="lesson-card" data-lesson-id="<?php echo $lesson['lesson_id']; ?>">
                        <div class="lesson-order-controls">
                            <span class="order-number"><?php echo $lesson['order_index']; ?></span>
                            <div class="order-buttons">
                                <button type="button" class="order-btn move-up" title="Di chuyển lên"><i class="fas fa-arrow-up"></i></button>
                                <button type="button" class="order-btn move-down" title="Di chuyển xuống"><i class="fas fa-arrow-down"></i></button>
                            </div>
                        </div>
                        
                        <div class="lesson-header">
                            <h4 class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></h4>
                            <div class="lesson-actions">
                                <a href="<?php echo BASE_URL; ?>app/views/product/lecture_management.php?course_id=<?php echo $course_id; ?>&lecture_id=<?php echo $lesson['lesson_id']; ?>" title="Chỉnh sửa">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa bài giảng này?');">
                                    <input type="hidden" name="lecture_id" value="<?php echo $lesson['lesson_id']; ?>">
                                    <button type="submit" name="delete_lecture" class="delete-btn" title="Xóa">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="lesson-meta">
                            <span><i class="fas fa-sort-numeric-up"></i> Thứ tự: <?php echo $lesson['order_index']; ?></span>
                            <span><i class="fas fa-clock"></i> Thời lượng: <?php echo $lesson['duration']; ?> phút</span>
                        </div>
                        
                        <div class="lesson-content">
                            <?php 
                            $content = htmlspecialchars($lesson['content'] ?? '');
                            echo strlen($content) > 200 ? substr($content, 0, 200) . '...' : $content; 
                            ?>
                        </div>
                        
                        <?php if (!empty($lesson['document_file']) || !empty($lesson['video_file']) || !empty($lesson['presentation_file']) || !empty($lesson['additional_files'])): ?>
                            <div class="lesson-attachments">
                                <h5>Tài Liệu Đính Kèm</h5>
                                <div class="attachment-list">
                                    <?php if (!empty($lesson['document_file'])): ?>
                                        <div class="attachment-item">
                                            <i class="fas <?php echo getFileIcon(pathinfo($lesson['document_file'], PATHINFO_EXTENSION)); ?>"></i>
                                            <a href="<?php echo BASE_URL . $lesson['document_file']; ?>" target="_blank">
                                                Tài liệu: <?php echo basename($lesson['document_file']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($lesson['video_file'])): ?>
                                        <div class="attachment-item">
                                            <i class="fas <?php echo getFileIcon(pathinfo($lesson['video_file'], PATHINFO_EXTENSION)); ?>"></i>
                                            <a href="<?php echo BASE_URL . $lesson['video_file']; ?>" target="_blank">
                                                Video: <?php echo basename($lesson['video_file']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($lesson['presentation_file'])): ?>
                                        <div class="attachment-item">
                                            <i class="fas <?php echo getFileIcon(pathinfo($lesson['presentation_file'], PATHINFO_EXTENSION)); ?>"></i>
                                            <a href="<?php echo BASE_URL . $lesson['presentation_file']; ?>" target="_blank">
                                                Bài trình bày: <?php echo basename($lesson['presentation_file']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    if (!empty($lesson['additional_files'])) {
                                        $additional_files = json_decode($lesson['additional_files'], true);
                                        if (!empty($additional_files)) {
                                            echo '<div class="attachment-item more-files" data-lesson-id="' . $lesson['lesson_id'] . '">';
                                            echo '<i class="fas fa-plus-circle"></i>';
                                            echo '<span>Có ' . count($additional_files) . ' tệp bổ sung</span>';
                                            echo '</div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-lessons">
                <i class="fas fa-info-circle fa-2x mb-3"></i>
                <p>Chưa có bài giảng nào trong khóa học này. Hãy thêm bài giảng mới.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="<?php echo BASE_URL; ?>public/js/instructor_dashboard.js"></script>
    <script>
        // Handle additional files display
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.more-files').forEach(function(element) {
                element.addEventListener('click', function() {
                    const lessonId = this.getAttribute('data-lesson-id');
                    
                    // Toggle details visibility
                    const detailsElement = document.getElementById('files-details-' + lessonId);
                    
                    if (detailsElement) {
                        // If details already exist, toggle visibility
                        detailsElement.style.display = detailsElement.style.display === 'none' ? 'block' : 'none';
                    } else {
                        // Otherwise, fetch details via AJAX
                        fetch('<?php echo BASE_URL; ?>api/lessons/get_files?lesson_id=' + lessonId)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success && data.files.length > 0) {
                                    // Create details element
                                    const detailsDiv = document.createElement('div');
                                    detailsDiv.id = 'files-details-' + lessonId;
                                    detailsDiv.className = 'files-details';
                                    
                                    // Create a close button
                                    const closeButton = document.createElement('button');
                                    closeButton.className = 'close-details';
                                    closeButton.innerHTML = '<i class="fas fa-times"></i>';
                                    closeButton.addEventListener('click', function(e) {
                                        e.stopPropagation();
                                        detailsDiv.style.display = 'none';
                                    });
                                    
                                    detailsDiv.appendChild(closeButton);
                                    
                                    // Add file items
                                    data.files.forEach(file => {
                                        const fileItem = document.createElement('div');
                                        fileItem.className = 'file-item';
                                        
                                        const iconClass = getFileIconClass(file.extension);
                                        
                                        fileItem.innerHTML = `
                                            <i class="fas ${iconClass}"></i>
                                            <a href="${file.url}" target="_blank">${file.name}</a>
                                        `;
                                        
                                        detailsDiv.appendChild(fileItem);
                                    });
                                    
                                    // Add details after the clicked element
                                    this.parentNode.insertBefore(detailsDiv, this.nextSibling);
                                } else {
                                    console.error('No files found or request failed');
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching file details:', error);
                            });
                    }
                });
            });
            
            // Lesson order functionality
            initLessonOrderControls();
        });
        
        // Initialize lecture order controls
        function initLessonOrderControls() {
            const lessonCards = document.querySelectorAll('.lesson-card');
            const orderForm = document.querySelector('.order-form');
            let changed = false;
            
            // Click handlers for up/down buttons
            document.querySelectorAll('.move-up').forEach((btn, index) => {
                btn.disabled = index === 0;
                btn.addEventListener('click', function() {
                    const currentCard = this.closest('.lesson-card');
                    const prevCard = currentCard.previousElementSibling;
                    
                    if (prevCard) {
                        // Swap the cards
                        currentCard.parentNode.insertBefore(currentCard, prevCard);
                        
                        // Update buttons enabled state
                        updateButtonStates();
                        
                        // Mark as changed
                        changed = true;
                        
                        // Highlight the save button
                        document.querySelector('.btn-order-save').classList.add('highlight');
                    }
                });
            });
            
            document.querySelectorAll('.move-down').forEach((btn, index) => {
                btn.disabled = index === lessonCards.length - 1;
                btn.addEventListener('click', function() {
                    const currentCard = this.closest('.lesson-card');
                    const nextCard = currentCard.nextElementSibling;
                    
                    if (nextCard) {
                        // Swap the cards
                        currentCard.parentNode.insertBefore(nextCard, currentCard);
                        
                        // Update buttons enabled state
                        updateButtonStates();
                        
                        // Mark as changed
                        changed = true;
                        
                        // Highlight the save button
                        document.querySelector('.btn-order-save').classList.add('highlight');
                    }
                });
            });
            
            // Update the order numbers and enabled state of buttons
            function updateButtonStates() {
                const updatedCards = document.querySelectorAll('.lesson-card');
                
                updatedCards.forEach((card, index) => {
                    const orderNumber = card.querySelector('.order-number');
                    orderNumber.textContent = index + 1;
                    
                    const upButton = card.querySelector('.move-up');
                    const downButton = card.querySelector('.move-down');
                    
                    upButton.disabled = index === 0;
                    downButton.disabled = index === updatedCards.length - 1;
                });
            }
            
            // Add form submit handler to save the new order
            orderForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!changed) {
                    alert('Thứ tự bài giảng chưa thay đổi.');
                    return;
                }
                
                // Collect the new order
                const newOrder = [];
                document.querySelectorAll('.lesson-card').forEach((card, index) => {
                    newOrder.push({
                        lesson_id: card.getAttribute('data-lesson-id'),
                        order_index: index + 1
                    });
                });
                
                // Create hidden inputs with the new order
                newOrder.forEach(item => {
                    const lessonIdInput = document.createElement('input');
                    lessonIdInput.type = 'hidden';
                    lessonIdInput.name = 'lesson_ids[]';
                    lessonIdInput.value = item.lesson_id;
                    orderForm.appendChild(lessonIdInput);
                    
                    const orderInput = document.createElement('input');
                    orderInput.type = 'hidden';
                    orderInput.name = 'order_indices[]';
                    orderInput.value = item.order_index;
                    orderForm.appendChild(orderInput);
                });
                
                // Submit the form
                orderForm.submit();
            });
        }
        
        // Helper function to get file icon based on extension
        function getFileIconClass(extension) {
            extension = extension.toLowerCase();
            
            if (['pdf'].includes(extension)) {
                return 'fa-file-pdf';
            } else if (['doc', 'docx', 'odt', 'txt'].includes(extension)) {
                return 'fa-file-word';
            } else if (['xls', 'xlsx', 'csv'].includes(extension)) {
                return 'fa-file-excel';
            } else if (['ppt', 'pptx', 'odp'].includes(extension)) {
                return 'fa-file-powerpoint';
            } else if (['mp4', 'avi', 'mov', 'webm'].includes(extension)) {
                return 'fa-file-video';
            } else if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) {
                return 'fa-file-image';
            } else if (['zip', 'rar'].includes(extension)) {
                return 'fa-file-archive';
            } else {
                return 'fa-file';
            }
        }
    </script>
</body>
</html> 