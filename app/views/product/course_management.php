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

// Kiểm tra xem khóa học có thuộc về giảng viên không
$stmt = $conn->prepare("SELECT course_id, title, description, category_id, price, image, level, language FROM courses WHERE course_id = ? AND instructor_id = ?");
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
    header("Location: instructor_dashboard.php");
    exit();
}

// Xử lý cập nhật khóa học
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : NULL;
    $price = !empty($_POST['price']) ? floatval($_POST['price']) : 0;
    $level = trim($_POST['level']);
    $language = trim($_POST['language']);
    
    // Keep existing image by default
    $image = $course['image'];
    
    // Process image upload if a new file is provided
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == UPLOAD_ERR_OK) {
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['image_file']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            $error = "Loại file không được hỗ trợ. Vui lòng tải lên file JPEG, PNG hoặc GIF.";
        }
        
        // Validate file size (2MB max)
        elseif ($_FILES['image_file']['size'] > 2 * 1024 * 1024) {
            $error = "Kích thước file quá lớn. Vui lòng tải lên file dưới 2MB.";
        }
        
        else {
            $upload_dir = ROOT_DIR . '/public/uploads/courses/';
            
            // Ensure directory exists
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Sanitize filename
            $image_name = time() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', basename($_FILES['image_file']['name']));
            $image_path = $upload_dir . $image_name;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $image_path)) {
                // Store relative path
                $image = 'public/uploads/courses/' . $image_name;
                
                // Log successful upload
                error_log("Course image uploaded successfully: {$image} for course {$course_id}");
            } else {
                $error = "Lỗi khi upload hình ảnh: " . error_get_last()['message'];
                error_log("Failed to upload course image: " . error_get_last()['message']);
            }
        }
    } elseif (isset($_POST['image']) && !empty($_POST['image'])) {
        // If a URL is manually entered, use that
        $image = trim($_POST['image']);
    }
    
    if (!isset($error)) {
        $stmt = $conn->prepare("UPDATE courses SET title = ?, description = ?, category_id = ?, price = ?, image = ?, level = ?, language = ? WHERE course_id = ? AND instructor_id = ?");
        $stmt->bind_param("ssidsssii", $title, $description, $category_id, $price, $image, $level, $language, $course_id, $user_id);

        if ($stmt->execute()) {
            $success = "Khóa học đã được cập nhật!";
            
            // Refresh course data with a new SELECT query
            $refresh = $conn->prepare("SELECT course_id, title, description, category_id, price, image, level, language FROM courses WHERE course_id = ? AND instructor_id = ?");
            $refresh->bind_param("ii", $course_id, $user_id);
            $refresh->execute();
            $course = $refresh->get_result()->fetch_assoc();
        } else {
            $error = "Cập nhật thất bại: " . $stmt->error;
        }
    }
}

// Xử lý xóa khóa học
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete'])) {
    $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ? AND instructor_id = ?");
    $stmt->bind_param("ii", $course_id, $user_id);
    if ($stmt->execute()) {
        header("Location: instructor_dashboard.php");
        exit();
    } else {
        $error = "Xóa thất bại.";
    }
}

$categories = $conn->query("SELECT category_id, name FROM categories WHERE status = 1");
if (!$categories) {
    error_log("Categories query failed: " . $conn->error);
    $error = "Không thể tải danh mục. Vui lòng thử lại sau.";
    $categories = []; // Initialize as empty array to prevent errors
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Khóa Học</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/instructor_dashboard.css">
    <style>
        .form-card {
            background: var(--card-light);
            border-radius: 16px;
            box-shadow: var(--shadow-light);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .dark-mode .form-card {
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
            min-height: 120px;
            resize: vertical;
        }
        
        .btn-submit, .btn-delete {
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
            margin-right: 10px;
        }
        
        .btn-submit:hover {
            background: #513dd8;
            transform: translateY(-2px);
        }
        
        .btn-delete {
            background: #e74c3c;
        }
        
        .btn-delete:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        .btn-analytics {
            background: #3498db;
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
            margin-right: 10px;
            text-decoration: none;
        }
        
        .btn-analytics:hover {
            background: #2980b9;
            transform: translateY(-2px);
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
        
        /* Image Upload Styles */
        .current-image {
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(0,0,0,0.03);
            border-radius: 8px;
        }
        
        .dark-mode .current-image {
            background: rgba(255,255,255,0.05);
        }
        
        .current-image p, .image-upload p, .image-url p {
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .text-muted {
            color: #6c757d;
            font-size: 0.875rem;
            display: block;
            margin-top: 5px;
        }
        
        .dark-mode .text-muted {
            color: #adb5bd;
        }
        
        .image-upload, .image-url {
            padding: 15px;
            background: rgba(0,0,0,0.03);
            border-radius: 8px;
        }
        
        .dark-mode .image-upload, .dark-mode .image-url {
            background: rgba(255,255,255,0.05);
        }
        
        input[type="file"] {
            padding: 8px;
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
            <form method="post" action="<?php echo BASE_URL; ?>auth/logout" class="logout-form">
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
            <li><a href="#"><i class="fas fa-book"></i> Quản Lý Bài Giảng</a></li>
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
        <h2>Quản Lý Khóa Học: <?php echo htmlspecialchars($course['title']); ?></h2>
        
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
        
        <div class="form-card">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Tiêu Đề Khóa Học</label>
                    <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($course['title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Mô Tả Khóa Học</label>
                    <textarea id="description" name="description" class="form-control" required><?php echo htmlspecialchars($course['description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Danh Mục</label>
                    <select id="category_id" name="category_id" class="form-control">
                        <option value="">Chọn danh mục</option>
                        <?php 
                        if (is_object($categories) && method_exists($categories, 'fetch_assoc')) {
                            while ($cat = $categories->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $cat['category_id']; ?>" <?php echo $cat['category_id'] == $course['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php 
                            endwhile; 
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="price">Giá Khóa Học (VNĐ)</label>
                    <input type="number" step="0.01" id="price" name="price" class="form-control" value="<?php echo htmlspecialchars($course['price']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="image_file">Hình Ảnh Khóa Học</label>
                    
                    <?php if (!empty($course['image'])): ?>
                    <div class="current-image">
                        <p>Hình ảnh hiện tại:</p>
                        <?php
                        // Check if image path already contains BASE_URL
                        $imagePath = $course['image'];
                        if (strpos($imagePath, 'http') !== 0 && strpos($imagePath, '/') !== 0) {
                            $imagePath = BASE_URL . $imagePath;
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                             alt="<?php echo htmlspecialchars($course['title']); ?>" 
                             style="max-width: 300px; max-height: 200px; margin-bottom: 15px; border-radius: 8px;">
                    </div>
                    <?php endif; ?>
                    
                    <div class="image-upload">
                        <p>Tải lên hình ảnh mới:</p>
                        <input type="file" id="image_file" name="image_file" class="form-control" accept="image/jpeg,image/png,image/gif">
                        <small class="text-muted">Định dạng hỗ trợ: JPEG, PNG, GIF. Kích thước tối đa: 2MB</small>
                    </div>
                    
                    <div class="image-url" style="margin-top: 15px;">
                        <p>Hoặc nhập URL hình ảnh:</p>
                        <input type="text" id="image" name="image" class="form-control" value="<?php echo htmlspecialchars($course['image']); ?>" placeholder="Ví dụ: public/uploads/courses/my-image.jpg">
                        <small class="text-muted">Để trống nếu bạn đang tải lên hình ảnh mới.</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="level">Cấp Độ</label>
                    <select id="level" name="level" class="form-control">
                        <option value="">-- Chọn cấp độ --</option>
                        <option value="Cơ bản" <?php echo $course['level'] == 'Cơ bản' ? 'selected' : ''; ?>>Cơ bản</option>
                        <option value="Trung cấp" <?php echo $course['level'] == 'Trung cấp' ? 'selected' : ''; ?>>Trung cấp</option>
                        <option value="Nâng cao" <?php echo $course['level'] == 'Nâng cao' ? 'selected' : ''; ?>>Nâng cao</option>
                        <option value="Tất cả" <?php echo $course['level'] == 'Tất cả' ? 'selected' : ''; ?>>Tất cả trình độ</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="language">Ngôn Ngữ Giảng Dạy</label>
                    <select id="language" name="language" class="form-control">
                        <option value="Tiếng Việt" <?php echo $course['language'] == 'Tiếng Việt' ? 'selected' : ''; ?>>Tiếng Việt</option>
                        <option value="Tiếng Anh" <?php echo $course['language'] == 'Tiếng Anh' ? 'selected' : ''; ?>>Tiếng Anh</option>
                        <option value="Song ngữ" <?php echo $course['language'] == 'Song ngữ' ? 'selected' : ''; ?>>Song ngữ (Việt - Anh)</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update" class="btn-submit">
                        <i class="fas fa-save"></i> Cập Nhật Khóa Học
                    </button>
                    
                    <button type="submit" name="delete" class="btn-delete" onclick="return confirm('Bạn có chắc muốn xóa khóa học này?');">
                        <i class="fas fa-trash"></i> Xóa Khóa Học
                    </button>
                    
                    <a href="<?php echo BASE_URL; ?>app/views/product/course_analytics.php?course_id=<?php echo $course['course_id']; ?>" class="btn-analytics">
                        <i class="fas fa-chart-line"></i> Phân Tích Khóa Học
                    </a>
                </div>
            </form>
        </div>

        <!-- Course Actions Buttons -->
        <div class="course-actions-container">
            <a href="<?php echo BASE_URL; ?>app/views/product/lecture_management.php?course_id=<?php echo htmlspecialchars($course_id); ?>" class="btn-manage">
                <i class="fas fa-list-ul"></i> Quản Lý Bài Giảng
            </a>
            <a href="<?php echo BASE_URL; ?>app/views/product/assignments.php?course_id=<?php echo htmlspecialchars($course_id); ?>" class="btn-assignments">
                <i class="fas fa-tasks"></i> Bài Tập & Đánh Giá
            </a>
            <a href="<?php echo BASE_URL; ?>app/views/product/course_analytics.php?course_id=<?php echo htmlspecialchars($course_id); ?>" class="btn-analytics">
                <i class="fas fa-chart-line"></i> Phân Tích Dữ Liệu
            </a>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>public/js/instructor_dashboard.js"></script>
    <script>
        // Image preview functionality
        document.getElementById('image_file').addEventListener('change', function(e) {
            const fileInput = e.target;
            
            if (fileInput.files && fileInput.files[0]) {
                // Create or get preview container
                let previewContainer = document.querySelector('.image-preview');
                
                if (!previewContainer) {
                    previewContainer = document.createElement('div');
                    previewContainer.className = 'image-preview';
                    previewContainer.style.marginTop = '15px';
                    document.querySelector('.image-upload').appendChild(previewContainer);
                }
                
                // Check file size
                const fileSize = fileInput.files[0].size / 1024 / 1024; // size in MB
                if (fileSize > 2) {
                    previewContainer.innerHTML = '<p style="color: red;">Cảnh báo: File có kích thước ' + 
                        fileSize.toFixed(2) + 'MB, vượt quá giới hạn 2MB. Vui lòng chọn file nhỏ hơn.</p>';
                    return;
                }
                
                // Create preview
                const reader = new FileReader();
                reader.onload = function(event) {
                    previewContainer.innerHTML = '<p>Xem trước:</p>' +
                        '<img src="' + event.target.result + '" style="max-width: 300px; max-height: 200px; margin-top: 10px; border-radius: 8px;">';
                    
                    // Clear URL input when uploading a file
                    document.getElementById('image').value = '';
                };
                reader.readAsDataURL(fileInput.files[0]);
            }
        });
    </script>
</body>
</html>