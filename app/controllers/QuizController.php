<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}
require_once ROOT_DIR . '/app/models/Quiz.php';
require_once ROOT_DIR . '/app/models/User.php'; // Để ghi log

class QuizController {
    private $conn;
    private $model;
    private $userModel;

    public function __construct($db) {
        $this->conn = $db;
        $this->model = new Quiz($db);
        $this->userModel = new User($db);
    }

    public function manageQuizzes($course_id, $lesson_id) {
        session_start();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'instructor') {
            $this->redirect('home.php');
        }

        $course_id = filter_var($course_id, FILTER_VALIDATE_INT);
        $lesson_id = filter_var($lesson_id, FILTER_VALIDATE_INT);
        if (!$course_id || !$lesson_id) {
            $_SESSION['quizError'] = "ID khóa học hoặc bài giảng không hợp lệ.";
            $this->redirect('home.php');
        }

        // Kiểm tra quyền sở hữu khóa học
        $stmt = $this->conn->prepare("SELECT course_id FROM courses WHERE course_id = ? AND instructor_id = ?");
        $stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            $_SESSION['quizError'] = "Bạn không có quyền quản lý khóa học này.";
            $this->redirect('home.php');
        }
        $stmt->close();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
                $_SESSION['quizError'] = "Yêu cầu không hợp lệ.";
                $this->redirect("course_management.php?course_id=$course_id");
            }

            if (isset($_POST['create'])) {
                $title = trim($_POST['title']);
                $time_limit = filter_var($_POST['time_limit'], FILTER_VALIDATE_INT);
                $total_questions = filter_var($_POST['total_questions'], FILTER_VALIDATE_INT);
                if (empty($title) || strlen($title) > 200 || $time_limit < 0 || $total_questions < 0) {
                    $_SESSION['quizError'] = "Dữ liệu không hợp lệ: tiêu đề tối đa 200 ký tự, thời gian và số câu hỏi không âm.";
                } else {
                    $result = $this->model->create($course_id, $lesson_id, $title, $time_limit, $total_questions);
                    if ($result['success']) {
                        $this->userModel->logAction($_SESSION['user_id'], 'create_quiz', $result['quiz_id'], "Đã tạo bài kiểm tra '$title' trong khóa học ID $course_id");
                        $_SESSION['quizSuccess'] = $result['message'];
                    } else {
                        $_SESSION['quizError'] = $result['message'];
                    }
                }
            } elseif (isset($_POST['update'])) {
                $quiz_id = filter_var($_POST['quiz_id'], FILTER_VALIDATE_INT);
                $title = trim($_POST['title']);
                $time_limit = filter_var($_POST['time_limit'], FILTER_VALIDATE_INT);
                $total_questions = filter_var($_POST['total_questions'], FILTER_VALIDATE_INT);
                if (!$quiz_id || empty($title) || strlen($title) > 200 || $time_limit < 0 || $total_questions < 0) {
                    $_SESSION['quizError'] = "Dữ liệu không hợp lệ.";
                } else {
                    $result = $this->model->update($quiz_id, $course_id, $title, $time_limit, $total_questions); // Thêm course_id
                    if ($result['success']) {
                        $this->userModel->logAction($_SESSION['user_id'], 'update_quiz', $quiz_id, "Đã cập nhật bài kiểm tra '$title'");
                        $_SESSION['quizSuccess'] = $result['message'];
                    } else {
                        $_SESSION['quizError'] = $result['message'];
                    }
                }
            } elseif (isset($_POST['delete'])) {
                $quiz_id = filter_var($_POST['quiz_id'], FILTER_VALIDATE_INT);
                if (!$quiz_id) {
                    $_SESSION['quizError'] = "ID bài kiểm tra không hợp lệ.";
                } else {
                    $result = $this->model->delete($quiz_id, $course_id); // Thêm course_id
                    if ($result['success']) {
                        $this->userModel->logAction($_SESSION['user_id'], 'delete_quiz', $quiz_id, "Đã xóa bài kiểm tra ID $quiz_id");
                        $_SESSION['quizSuccess'] = $result['message'];
                    } else {
                        $_SESSION['quizError'] = $result['message'];
                    }
                }
            }
            $this->redirect("course_management.php?course_id=$course_id");
        }

        return $this->model->getByLesson($lesson_id);
    }

    private function redirect($path) {
        header("Location: " . BASE_URL . "app/views/product/$path");
        exit();
    }
}