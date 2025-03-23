<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}
require_once ROOT_DIR . '/app/models/Lesson.php';
require_once ROOT_DIR . '/app/models/User.php'; // Thêm User model để ghi log

class LessonController {
    private $conn;
    private $model;
    private $userModel;

    public function __construct($db) {
        $this->conn = $db;
        $this->model = new Lesson($db);
        $this->userModel = new User($db);
    }

    public function manageLessons($course_id) {
        session_start();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'instructor') {
            $this->redirect('home.php');
        }

        $course_id = filter_var($course_id, FILTER_VALIDATE_INT);
        if (!$course_id) {
            $_SESSION['lessonError'] = "ID khóa học không hợp lệ.";
            $this->redirect('home.php');
        }

        // Kiểm tra quyền sở hữu khóa học
        $stmt = $this->conn->prepare("SELECT course_id FROM courses WHERE course_id = ? AND instructor_id = ?");
        $stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            $_SESSION['lessonError'] = "Bạn không có quyền quản lý bài giảng của khóa học này.";
            $this->redirect('home.php');
        }
        $stmt->close();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
                $_SESSION['lessonError'] = "Yêu cầu không hợp lệ.";
                $this->redirect("course_management.php?course_id=$course_id");
            }

            if (isset($_POST['create'])) {
                $title = trim($_POST['title']);
                $content = trim($_POST['content']);
                $order_index = filter_var($_POST['order_index'], FILTER_VALIDATE_INT);
                $duration = filter_var($_POST['duration'], FILTER_VALIDATE_INT);
                if (empty($title) || strlen($title) > 100 || empty($content) || strlen($content) > 65535 || $order_index < 0 || $duration < 0) {
                    $_SESSION['lessonError'] = "Dữ liệu không hợp lệ: tiêu đề tối đa 100 ký tự, nội dung tối đa 65535 ký tự, thứ tự và thời lượng không âm.";
                } else {
                    $result = $this->model->create($course_id, $title, $content, $order_index, $duration);
                    if ($result['success']) {
                        $this->userModel->logAction($_SESSION['user_id'], 'create_lesson', $result['lesson_id'], "Đã tạo bài giảng '$title' trong khóa học ID $course_id");
                        $_SESSION['lessonSuccess'] = $result['message'];
                    } else {
                        $_SESSION['lessonError'] = $result['message'];
                    }
                }
            } elseif (isset($_POST['update'])) {
                $lesson_id = filter_var($_POST['lesson_id'], FILTER_VALIDATE_INT);
                $title = trim($_POST['title']);
                $content = trim($_POST['content']);
                $order_index = filter_var($_POST['order_index'], FILTER_VALIDATE_INT);
                $duration = filter_var($_POST['duration'], FILTER_VALIDATE_INT);
                if (!$lesson_id || empty($title) || strlen($title) > 100 || empty($content) || strlen($content) > 65535 || $order_index < 0 || $duration < 0) {
                    $_SESSION['lessonError'] = "Dữ liệu không hợp lệ.";
                } else {
                    $result = $this->model->update($lesson_id, $course_id, $title, $content, $order_index, $duration); // Thêm course_id
                    if ($result['success']) {
                        $this->userModel->logAction($_SESSION['user_id'], 'update_lesson', $lesson_id, "Đã cập nhật bài giảng '$title'");
                        $_SESSION['lessonSuccess'] = $result['message'];
                    } else {
                        $_SESSION['lessonError'] = $result['message'];
                    }
                }
            } elseif (isset($_POST['delete'])) {
                $lesson_id = filter_var($_POST['lesson_id'], FILTER_VALIDATE_INT);
                if (!$lesson_id) {
                    $_SESSION['lessonError'] = "ID bài giảng không hợp lệ.";
                } else {
                    $result = $this->model->delete($lesson_id, $course_id); // Thêm course_id
                    if ($result['success']) {
                        $this->userModel->logAction($_SESSION['user_id'], 'delete_lesson', $lesson_id, "Đã xóa bài giảng ID $lesson_id");
                        $_SESSION['lessonSuccess'] = $result['message'];
                    } else {
                        $_SESSION['lessonError'] = $result['message'];
                    }
                }
            }
            $this->redirect("course_management.php?course_id=$course_id");
        }

        return $this->model->getByCourse($course_id);
    }

    public function getLessons($course_id) {
        $course_id = filter_var($course_id, FILTER_VALIDATE_INT);
        return $course_id ? $this->model->getByCourse($course_id) : [];
    }

    private function redirect($path) {
        header("Location: " . BASE_URL . "app/views/product/$path");
        exit();
    }
}