<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}
require_once ROOT_DIR . '/app/models/Enrollment.php';
require_once ROOT_DIR . '/app/models/Notification.php';
require_once ROOT_DIR . '/app/models/User.php'; // Thêm User model để ghi log

class EnrollmentController {
    private $conn;
    private $model;
    private $notificationModel;
    private $userModel;

    public function __construct($db) {
        $this->conn = $db;
        $this->model = new Enrollment($db);
        $this->notificationModel = new Notification($db);
        $this->userModel = new User($db);
    }

    public function enroll() {
        session_start();
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
            $this->redirect('home.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {
            if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
                $_SESSION['enrollError'] = "Yêu cầu không hợp lệ.";
                $this->redirect('enroll.php');
            }

            $user_id = $_SESSION['user_id'];
            $course_id = filter_var($_POST['course_id'], FILTER_VALIDATE_INT);
            if (!$course_id || $course_id <= 0) {
                $_SESSION['enrollError'] = "ID khóa học không hợp lệ.";
                $this->redirect('enroll.php');
            }

            $result = $this->model->enroll($user_id, $course_id); // Sửa create thành enroll
            if ($result['success']) {
                $this->userModel->logAction($user_id, 'enroll_course', $course_id, "Đã đăng ký khóa học ID $course_id");
                $_SESSION['enrollSuccess'] = $result['message'];
                $stmt = $this->conn->prepare("SELECT instructor_id FROM courses WHERE course_id = ?");
                $stmt->bind_param("i", $course_id);
                $stmt->execute();
                $course = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($course) {
                    $this->notificationModel->create($course['instructor_id'], $course_id, 'Học viên mới', "Một học viên mới vừa đăng ký khóa học ID $course_id.");
                }
            } else {
                $_SESSION['enrollError'] = $result['message'];
            }
            $this->redirect('student_dashboard.php');
        }
    }

    public function getEnrollments($user_id) {
        $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
        return $user_id ? $this->model->getByStudent($user_id) : []; // Sửa getByUser thành getByStudent
    }

    private function redirect($path) {
        header("Location: " . BASE_URL . "app/views/product/$path");
        exit();
    }
}