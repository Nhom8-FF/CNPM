<?php

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}
require_once ROOT_DIR . '/app/models/Assignment.php';

class AssignmentController {
    private $conn;
    private $model;

    public function __construct($db) {
        $this->conn = $db;
        $this->model = new Assignment($db);
    }

    public function manageAssignments($course_id, $lesson_id) {
        session_start();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'instructor') {
            $this->redirect('home.php');
        }
    
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
                $_SESSION['assignmentError'] = "Yêu cầu không hợp lệ.";
                $this->redirect("course_management.php?course_id=$course_id");
            }
    
            if (isset($_POST['create'])) {
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $due_date = $_POST['due_date'];
                $max_score = filter_var($_POST['max_score'], FILTER_VALIDATE_INT);
                if (empty($title) || $max_score === false) {
                    $_SESSION['assignmentError'] = "Dữ liệu không hợp lệ.";
                } else {
                    $result = $this->model->create($course_id, $lesson_id, $title, $description, $due_date, $max_score);
                    $_SESSION['assignmentMessage'] = $result['message'];
                }
            } elseif (isset($_POST['update'])) {
                $assignment_id = filter_var($_POST['assignment_id'], FILTER_VALIDATE_INT);
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $due_date = $_POST['due_date'];
                $max_score = filter_var($_POST['max_score'], FILTER_VALIDATE_INT);
                if (!$assignment_id || empty($title) || $max_score === false) {
                    $_SESSION['assignmentError'] = "Dữ liệu không hợp lệ.";
                } else {
                    $result = $this->model->update($assignment_id, $title, $description, $due_date, $max_score);
                    $_SESSION['assignmentMessage'] = $result['message'];
                }
            } elseif (isset($_POST['delete'])) {
                $assignment_id = filter_var($_POST['assignment_id'], FILTER_VALIDATE_INT);
                if (!$assignment_id) {
                    $_SESSION['assignmentError'] = "ID bài tập không hợp lệ.";
                } else {
                    $result = $this->model->delete($assignment_id);
                    $_SESSION['assignmentMessage'] = $result['message'];
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