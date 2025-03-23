<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}
require_once ROOT_DIR . '/app/models/Course.php';
require_once ROOT_DIR . '/app/models/Notification.php';
require_once ROOT_DIR . '/app/models/User.php'; 

class CourseController {
    private $conn;
    private $model;
    private $notificationModel;
    private $userModel;

    public function __construct($db) {
        $this->conn = $db;
        $this->model = new Course($db);
        $this->notificationModel = new Notification($db);
        $this->userModel = new User($db);
    }

    public function createCourse() {
        session_start();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'instructor') {
            $this->redirect('home.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
                $_SESSION['courseError'] = "Yêu cầu không hợp lệ.";
                $this->redirect('create_course.php');
            }

            $instructor_id = $_SESSION['user_id'];
            $category_id = filter_var($_POST['category_id'], FILTER_VALIDATE_INT);
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $video = trim($_POST['video'] ?? '');
            $duration = trim($_POST['duration'] ?? '');
            $price = floatval($_POST['price']);
            $image = trim($_POST['image'] ?? '');
            $level = trim($_POST['level'] ?? '');

            if (!$category_id || empty($title) || $price < 0 || strlen($title) > 100 || strlen($description) > 65535) {
                $_SESSION['courseError'] = "Dữ liệu không hợp lệ: tiêu đề tối đa 100 ký tự, mô tả tối đa 65535 ký tự, giá không âm.";
                $this->redirect('create_course.php');
            }

            $result = $this->model->create($instructor_id, $title, $description, $category_id, $price, $image, $level, $video, $duration);
            if ($result['success']) {
                $this->userModel->logAction($instructor_id, 'create_course', $result['course_id'], "Đã tạo khóa học '$title'");
                $_SESSION['courseSuccess'] = $result['message'];
                $adminUser = $this->conn->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1")->fetch_assoc();
                if ($adminUser) {
                    $this->notificationModel->create($adminUser['user_id'], $result['course_id'], 'Khóa học mới', "Khóa học '$title' vừa được tạo bởi giảng viên ID $instructor_id.");
                }
            } else {
                $_SESSION['courseError'] = $result['message'];
            }
            $this->redirect('instructor_dashboard.php');
        }
    }

    public function manageCourses() {
        session_start();
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'instructor' && $_SESSION['role'] !== 'admin')) {
            $this->redirect('home.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
                $_SESSION['courseError'] = "Yêu cầu không hợp lệ.";
                $this->redirect('course_management.php');
            }

            $user_id = $_SESSION['user_id'];
            if (isset($_POST['update'])) {
                $course_id = filter_var($_POST['course_id'], FILTER_VALIDATE_INT);
                $category_id = filter_var($_POST['category_id'], FILTER_VALIDATE_INT);
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $video = trim($_POST['video'] ?? '');
                $duration = trim($_POST['duration'] ?? '');
                $price = floatval($_POST['price']);
                $image = trim($_POST['image'] ?? '');
                $level = trim($_POST['level'] ?? '');
                $status = isset($_POST['status']) ? 1 : 0;

                if (!$course_id || !$category_id || empty($title) || $price < 0 || strlen($title) > 100 || strlen($description) > 65535) {
                    $_SESSION['courseError'] = "Dữ liệu không hợp lệ.";
                } else {
                    $result = $this->model->update($course_id, $user_id, $title, $description, $category_id, $price, $image, $level, $video, $duration, $status);
                    if ($result['success']) {
                        $this->userModel->logAction($user_id, 'update_course', $course_id, "Đã cập nhật khóa học '$title'");
                    }
                    $_SESSION['courseMessage'] = $result['message'];
                }
            } elseif (isset($_POST['delete'])) {
                $course_id = filter_var($_POST['course_id'], FILTER_VALIDATE_INT);
                if (!$course_id) {
                    $_SESSION['courseError'] = "ID khóa học không hợp lệ.";
                } else {
                    $result = $this->model->delete($course_id, $user_id);
                    if ($result['success']) {
                        $this->userModel->logAction($user_id, 'delete_course', $course_id, "Đã xóa khóa học ID $course_id");
                    }
                    $_SESSION['courseMessage'] = $result['message'];
                }
            }
            $this->redirect('course_management.php');
        }

        return $_SESSION['role'] === 'instructor' ? $this->model->getByInstructor($_SESSION['user_id']) : $this->model->getAll();
    }

    public function getCourses($category_id = null) {
        $category_id = $category_id ? filter_var($category_id, FILTER_VALIDATE_INT) : null;
        return $this->model->getAll($category_id);
    }

    public function getCoursesWithDetails($limit = 3) {
        try {
            $query = "SELECT c.course_id, c.title, c.description, c.image, c.price, c.level, 
                           c.video, c.duration, c.rating, c.students,
                           u.username AS instructor_name, u.user_id AS instructor_id,
                           cat.name AS category_name, cat.category_id
                    FROM courses c
                    LEFT JOIN users u ON c.instructor_id = u.user_id
                    LEFT JOIN categories cat ON c.category_id = cat.category_id
                    WHERE c.status = 1
                    ORDER BY c.created_at DESC
                    LIMIT ?";
                    
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $courses = [];
            while ($row = $result->fetch_assoc()) {
                $courses[] = $row;
            }
            $stmt->close();
            return $courses;
        } catch (Exception $e) {
            error_log("Error fetching courses with details: " . $e->getMessage());
            return [];
        }
    }

    public function getCourse($course_id) {
        $course_id = filter_var($course_id, FILTER_VALIDATE_INT);
        return $course_id ? $this->model->getById($course_id) : null;
    }

    private function redirect($path) {
        header("Location: " . BASE_URL . "app/views/product/$path");
        exit();
    }
}