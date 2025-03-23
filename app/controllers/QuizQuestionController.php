<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}
require_once ROOT_DIR . '/app/models/QuizQuestion.php';
require_once ROOT_DIR . '/app/models/User.php'; // Để ghi log

class QuizQuestionController {
    private $conn;
    private $model;
    private $userModel;

    public function __construct($db) {
        $this->conn = $db;
        $this->model = new QuizQuestion($db);
        $this->userModel = new User($db);
    }

    public function manageQuestions($quiz_id) {
        session_start();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'instructor') {
            $this->redirect('home.php');
        }

        $quiz_id = filter_var($quiz_id, FILTER_VALIDATE_INT);
        if (!$quiz_id) {
            $_SESSION['questionError'] = "ID bài kiểm tra không hợp lệ.";
            $this->redirect('home.php');
        }

        // Lấy course_id từ quiz_id để kiểm tra quyền
        $stmt = $this->conn->prepare("SELECT q.course_id FROM quizzes q JOIN courses c ON q.course_id = c.course_id WHERE q.quiz_id = ? AND c.instructor_id = ?");
        $stmt->bind_param("ii", $quiz_id, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if (!$result) {
            $_SESSION['questionError'] = "Bạn không có quyền quản lý bài kiểm tra này.";
            $this->redirect('home.php');
        }
        $course_id = $result['course_id'];
        $stmt->close();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
                $_SESSION['questionError'] = "Yêu cầu không hợp lệ.";
                $this->redirect("course_management.php?course_id=$course_id&quiz_id=$quiz_id");
            }

            if (isset($_POST['create'])) {
                $question_text = trim($_POST['question_text']);
                $option_a = trim($_POST['option_a']);
                $option_b = trim($_POST['option_b']);
                $option_c = trim($_POST['option_c']);
                $option_d = trim($_POST['option_d']);
                $correct_option = strtoupper($_POST['correct_option']);
                $score = filter_var($_POST['score'], FILTER_VALIDATE_INT);
                if (empty($question_text) || strlen($question_text) > 65535 || 
                    (strlen($option_a) > 255 || strlen($option_b) > 255 || strlen($option_c) > 255 || strlen($option_d) > 255) ||
                    !in_array($correct_option, ['A', 'B', 'C', 'D']) || $score < 0) {
                    $_SESSION['questionError'] = "Dữ liệu không hợp lệ: câu hỏi tối đa 65535 ký tự, lựa chọn tối đa 255 ký tự, đáp án đúng phải là A/B/C/D, điểm không âm.";
                } else {
                    $result = $this->model->create($quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $score);
                    if ($result['success']) {
                        $this->userModel->logAction($_SESSION['user_id'], 'create_quiz_question', $result['question_id'], "Đã tạo câu hỏi trong bài kiểm tra ID $quiz_id");
                        $_SESSION['questionSuccess'] = $result['message'];
                    } else {
                        $_SESSION['questionError'] = $result['message'];
                    }
                }
            } elseif (isset($_POST['update'])) {
                $question_id = filter_var($_POST['question_id'], FILTER_VALIDATE_INT);
                $question_text = trim($_POST['question_text']);
                $option_a = trim($_POST['option_a']);
                $option_b = trim($_POST['option_b']);
                $option_c = trim($_POST['option_c']);
                $option_d = trim($_POST['option_d']);
                $correct_option = strtoupper($_POST['correct_option']);
                $score = filter_var($_POST['score'], FILTER_VALIDATE_INT);
                if (!$question_id || empty($question_text) || strlen($question_text) > 65535 || 
                    (strlen($option_a) > 255 || strlen($option_b) > 255 || strlen($option_c) > 255 || strlen($option_d) > 255) ||
                    !in_array($correct_option, ['A', 'B', 'C', 'D']) || $score < 0) {
                    $_SESSION['questionError'] = "Dữ liệu không hợp lệ.";
                } else {
                    $result = $this->model->update($question_id, $quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $score); // Thêm quiz_id
                    if ($result['success']) {
                        $this->userModel->logAction($_SESSION['user_id'], 'update_quiz_question', $question_id, "Đã cập nhật câu hỏi ID $question_id");
                        $_SESSION['questionSuccess'] = $result['message'];
                    } else {
                        $_SESSION['questionError'] = $result['message'];
                    }
                }
            } elseif (isset($_POST['delete'])) {
                $question_id = filter_var($_POST['question_id'], FILTER_VALIDATE_INT);
                if (!$question_id) {
                    $_SESSION['questionError'] = "ID câu hỏi không hợp lệ.";
                } else {
                    $result = $this->model->delete($question_id, $quiz_id); // Thêm quiz_id
                    if ($result['success']) {
                        $this->userModel->logAction($_SESSION['user_id'], 'delete_quiz_question', $question_id, "Đã xóa câu hỏi ID $question_id");
                        $_SESSION['questionSuccess'] = $result['message'];
                    } else {
                        $_SESSION['questionError'] = $result['message'];
                    }
                }
            }
            $this->redirect("course_management.php?course_id=$course_id&quiz_id=$quiz_id");
        }

        return $this->model->getByQuiz($quiz_id);
    }

    private function redirect($path) {
        header("Location: " . BASE_URL . "app/views/product/$path");
        exit();
    }
}