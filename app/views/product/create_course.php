<?php
// Don't redefine constants if already defined
if (!defined('BASE_URL')) {
define('BASE_URL', '/WebCourses/');
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3));
}

// Database connection
include ROOT_DIR . '/app/config/connect.php';

// Check if session is already started before starting it
if (session_status() == PHP_SESSION_NONE) {
session_start();
}

// Reset redirect count to prevent redirect loops
$_SESSION['redirect_count'] = 0;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'instructor') {
    // Set error message in session
    $_SESSION['error'] = "Bạn không có quyền truy cập trang này. Vui lòng đăng nhập với tài khoản giảng viên.";
    // Redirect to home page
    header("Location: " . BASE_URL . "app/views/product/home.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

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

// Xử lý thêm danh mục mới
if (isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    $category_description = trim($_POST['category_description'] ?? '');
    
    if (!empty($category_name)) {
        // Kiểm tra xem danh mục đã tồn tại chưa
        $check_stmt = $conn->prepare("SELECT category_id FROM categories WHERE name = ?");
        $check_stmt->bind_param("s", $category_name);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Thêm danh mục mới
            $cat_stmt = $conn->prepare("INSERT INTO categories (name, description, status) VALUES (?, ?, 1)");
            $cat_stmt->bind_param("ss", $category_name, $category_description);
            
            if ($cat_stmt->execute()) {
                $category_success = "Danh mục '$category_name' đã được thêm thành công!";
                $new_category_id = $conn->insert_id;
            } else {
                $category_error = "Không thể thêm danh mục: " . $conn->error;
            }
            $cat_stmt->close();
        } else {
            $category_error = "Danh mục '$category_name' đã tồn tại!";
        }
        $check_stmt->close();
    } else {
        $category_error = "Vui lòng nhập tên danh mục!";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['add_category'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : NULL;
    $price = !empty($_POST['price']) ? floatval($_POST['price']) : 0;
    $level = trim($_POST['level']);
    $language = trim($_POST['language'] ?? '');
    $learning_outcomes = trim($_POST['learning_outcomes'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $tags = trim($_POST['tags'] ?? '');

    // Xử lý upload hình ảnh
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../../../public/uploads/courses/'; // Thư mục lưu hình ảnh
        
        // Tạo thư mục nếu chưa tồn tại
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $image_path = $upload_dir . $image_name;

        // Di chuyển file vào thư mục uploads
        if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
            $image = 'public/uploads/courses/' . $image_name; // Lưu đường dẫn tương đối
        } else {
            $error = "Lỗi khi upload hình ảnh.";
        }
    }

    if (empty($error)) {
        // Chuẩn bị truy vấn thêm khóa học mới
        $stmt = $conn->prepare("INSERT INTO courses (instructor_id, category_id, title, description, price, image, level, language, requirements, learning_outcomes, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissdssssss", $user_id, $category_id, $title, $description, $price, $image, $level, $language, $requirements, $learning_outcomes, $tags);

        if ($stmt->execute()) {
            $success = "Khóa học đã được tạo thành công!";
        } else {
            $error = "Có lỗi xảy ra khi tạo khóa học: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Lấy danh sách danh mục từ database
$categories = $conn->query("SELECT category_id, name FROM categories WHERE status = 1 ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tạo Khóa Học Mới</title>
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
        
        .form-control-file {
            padding: 8px 0;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
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
        
        .error-message, .success-message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .success-message {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        
        .dark-mode .error-message {
            background: rgba(239, 68, 68, 0.2);
        }
        
        .dark-mode .success-message {
            background: rgba(34, 197, 94, 0.2);
        }
        
        .form-subtitle {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: var(--primary-color);
        }
        
        .dark-mode .form-subtitle {
            border-bottom-color: #333;
        }
        
        .hint-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        
        .dark-mode .hint-text {
            color: #999;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: var(--card-light);
            margin: 10% auto;
            padding: 25px;
            border-radius: 16px;
            box-shadow: var(--shadow-light);
            width: 500px;
            max-width: 90%;
            animation: modalFadeIn 0.3s;
        }
        
        .dark-mode .modal-content {
            background: var(--card-dark);
            box-shadow: var(--shadow-dark);
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.5rem;
        }
        
        .modal-close {
            font-size: 24px;
            color: #aaa;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .modal-close:hover {
            color: var(--primary-color);
        }
        
        .close-icon {
            font-size: 1.5rem;
        }
        
        .category-row {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .category-dropdown {
            flex: 1;
        }
        
        .add-category-btn {
            background: var(--card-light);
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            padding: 12px 15px;
            margin-left: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .dark-mode .add-category-btn {
            background: var(--card-dark);
        }
        
        .add-category-btn:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .dark-mode .btn-secondary {
            background: #475569;
            color: #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        
        .dark-mode .btn-secondary:hover {
            background: #334155;
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
            <form method="post" action="<?php echo BASE_URL; ?>app/controllers/logout.php" style="display:inline;">
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
            <li><a href="<?php echo BASE_URL; ?>app/views/product/create_course.php" class="active"><i class="fas fa-plus-circle"></i> Thêm Khoá Học</a></li>
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
                <h2>Tạo Khóa Học Mới</h2>
        
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
        
        <?php if (isset($category_success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo $category_success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($category_error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $category_error; ?>
            </div>
                <?php endif; ?>
        
        <div class="form-card">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-subtitle">Thông Tin Cơ Bản</div>
                
                <div class="form-group">
                    <label for="title">Tiêu Đề Khóa Học</label>
                    <input type="text" id="title" name="title" class="form-control" placeholder="Ví dụ: Lập Trình PHP Cơ Bản" required>
                    </div>
                
                <div class="form-group">
                    <label for="description">Mô Tả Khóa Học</label>
                    <textarea id="description" name="description" class="form-control" placeholder="Mô tả chi tiết về khóa học của bạn..." required></textarea>
                    <div class="hint-text">Viết mô tả hấp dẫn, nêu rõ những gì học viên sẽ học được</div>
                    </div>
                
                <div class="form-group">
                    <label for="category_id">Danh Mục</label>
                    <div class="category-row">
                        <div class="category-dropdown">
                            <select id="category_id" name="category_id" class="form-control" required>
                                <option value="">-- Chọn danh mục --</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo $cat['category_id']; ?>" <?php echo (isset($new_category_id) && $new_category_id == $cat['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                        <button type="button" id="openCategoryModal" class="add-category-btn">
                            <i class="fas fa-plus"></i> Thêm Mới
                        </button>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Giá Khóa Học (VNĐ)</label>
                        <input type="number" id="price" name="price" class="form-control" placeholder="Nhập 0 nếu miễn phí" required>
                    </div>
                
                    <div class="form-group">
                        <label for="level">Cấp Độ</label>
                        <select id="level" name="level" class="form-control" required>
                            <option value="">-- Chọn cấp độ --</option>
                            <option value="Cơ bản">Cơ bản</option>
                            <option value="Trung cấp">Trung cấp</option>
                            <option value="Nâng cao">Nâng cao</option>
                            <option value="Tất cả">Tất cả trình độ</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="language">Ngôn Ngữ Giảng Dạy</label>
                        <select id="language" name="language" class="form-control">
                            <option value="Tiếng Việt">Tiếng Việt</option>
                            <option value="Tiếng Anh">Tiếng Anh</option>
                            <option value="Song ngữ">Song ngữ (Việt - Anh)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tags">Từ Khóa (Tags)</label>
                        <input type="text" id="tags" name="tags" class="form-control" placeholder="Ví dụ: php, web, cơ bản, lập trình">
                        <div class="hint-text">Các từ khóa cách nhau bằng dấu phẩy</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="image">Hình Ảnh Khóa Học</label>
                    <input type="file" id="image" name="image" class="form-control form-control-file" accept="image/*" required>
                    <div class="hint-text">Kích thước khuyến nghị: 1280x720 pixel (tỷ lệ 16:9)</div>
                </div>
                
                <div class="form-subtitle">Thông Tin Chi Tiết</div>
                
                <div class="form-group">
                    <label for="learning_outcomes">Kết Quả Học Tập</label>
                    <textarea id="learning_outcomes" name="learning_outcomes" class="form-control" placeholder="Liệt kê những gì học viên sẽ học được sau khóa học..."></textarea>
                    <div class="hint-text">Liệt kê dưới dạng danh sách, mỗi dòng một kết quả học tập</div>
                </div>
                
                <div class="form-group">
                    <label for="requirements">Yêu Cầu Tiên Quyết</label>
                    <textarea id="requirements" name="requirements" class="form-control" placeholder="Những kiến thức hoặc kỹ năng học viên cần có trước khi tham gia khóa học..."></textarea>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-plus-circle"></i> Tạo Khóa Học
                </button>
                </form>
            </div>
        </div>
    
    <!-- Modal Thêm Danh Mục -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-folder-plus"></i> Thêm Danh Mục Mới</h3>
                <span class="modal-close" id="closeModal"><i class="fas fa-times close-icon"></i></span>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label for="category_name">Tên Danh Mục</label>
                    <input type="text" id="category_name" name="category_name" class="form-control" placeholder="Nhập tên danh mục mới" required>
                </div>
                <div class="form-group">
                    <label for="category_description">Mô Tả (Tùy chọn)</label>
                    <textarea id="category_description" name="category_description" class="form-control" placeholder="Mô tả ngắn về danh mục"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="cancelModal">Hủy</button>
                    <button type="submit" name="add_category" class="btn-submit">
                        <i class="fas fa-plus-circle"></i> Thêm Danh Mục
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>public/js/instructor_dashboard.js"></script>
    <script>
        // Modal handling
        const modal = document.getElementById('categoryModal');
        const openModalBtn = document.getElementById('openCategoryModal');
        const closeModalBtn = document.getElementById('closeModal');
        const cancelModalBtn = document.getElementById('cancelModal');
        
        openModalBtn.addEventListener('click', function(e) {
            e.preventDefault();
            modal.style.display = 'block';
            document.getElementById('category_name').focus();
        });
        
        closeModalBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        cancelModalBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    </script>
</body>
</html>