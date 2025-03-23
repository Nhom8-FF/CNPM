<?php
define('BASE_URL', '/WebCourses/');
include '../../config/connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: home.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($course_id > 0) {
    // Kiểm tra xem đã đăng ký chưa
    $stmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE user_id = ? AND course_id = ?");
    $stmt->bind_param("ii", $user_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        // Thêm bản ghi vào enrollments
        $stmt = $conn->prepare("INSERT INTO enrollments (user_id, course_id, enrolled_date) VALUES (?, ?, CURDATE())");
        $stmt->bind_param("ii", $user_id, $course_id);
        if ($stmt->execute()) {
            // Cập nhật số lượng students trong courses
            $stmt = $conn->prepare("UPDATE courses SET students = students + 1 WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $message = "Đăng ký khóa học thành công!";
        } else {
            $error = "Đăng ký thất bại. Vui lòng thử lại.";
        }
    } else {
        $error = "Bạn đã đăng ký khóa học này rồi.";
    }
    $stmt->close();
} else {
    $error = "Khóa học không hợp lệ.";
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký Khóa Học</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/style.css">
</head>
<body>
    <header>
        <div class="container header-container">
            <div class="logo">Học Tập</div>
            <nav>
                <ul>
                    <li><a href="student_dashboard.php">Trang Chủ</a></li>
                    <li><a href="<?php echo BASE_URL; ?>app/controllers/logout.php" class="btn">Đăng Xuất</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section>
        <div class="container">
            <?php if (isset($message)): ?>
                <p class="success-message"><?php echo $message; ?></p>
                <a href="student_dashboard.php" class="btn">Quay lại Dashboard</a>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <p class="error-message"><?php echo $error; ?></p>
                <a href="home.php#courses" class="btn">Quay lại danh sách khóa học</a>
            <?php endif; ?>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>© 2025 Học Tập Trực Tuyến. All Rights Reserved.</p>
        </div>
    </footer>
</body>
</html>