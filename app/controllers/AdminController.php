<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}
require_once ROOT_DIR . '/app/models/User.php';
require_once ROOT_DIR . '/app/models/Review.php';
require_once ROOT_DIR . '/app/models/Comment.php';

class AdminController {
    private $conn;
    private $userModel;
    private $reviewModel;
    private $commentModel;

    public function __construct($db) {
        $this->conn = $db;
        $this->userModel = new User($db);
        $this->reviewModel = new Review($db);
        $this->commentModel = new Comment($db);
    }

    public function manageUsers() {
        session_start();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header("Location: " . BASE_URL . "app/views/product/home.php");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
                $_SESSION['adminMessage'] = "Yêu cầu không hợp lệ.";
                header("Location: " . BASE_URL . "app/views/product/admin_dashboard.php");
                exit();
            }

            if (isset($_POST['toggle_lock'])) {
                $user_id = $_POST['user_id'];
                $is_locked = $_POST['is_locked'] ? 1 : 0;
                $result = $this->userModel->toggleLockUser($user_id, $is_locked);
                $_SESSION['adminMessage'] = $result['message'];
            } elseif (isset($_POST['change_role'])) {
                $user_id = $_POST['user_id'];
                $role = $_POST['role'];
                $result = $this->userModel->changeRole($user_id, $role);
                $_SESSION['adminMessage'] = $result['message'];
            } elseif (isset($_POST['delete_user'])) {
                $user_id = $_POST['user_id'];
                $result = $this->userModel->deleteUser($user_id);
                $_SESSION['adminMessage'] = $result['message'];
            }
            header("Location: " . BASE_URL . "app/views/product/admin_dashboard.php");
            exit();
        }

        $users = $this->userModel->getAllUsers(10, 0);
        $logs = $this->userModel->getUserLogs(10, 0);
        return ['users' => $users, 'logs' => $logs];
    }

    public function manageContent() {
        session_start();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $this->redirect('home.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
                $_SESSION['adminMessage'] = "Yêu cầu không hợp lệ.";
                $this->redirect('admin_dashboard.php');
            }

            if (isset($_POST['approve_content'])) {
                $content_id = filter_var($_POST['content_id'], FILTER_VALIDATE_INT);
                $content_type = $_POST['content_type'];
                $status = $_POST['status'];
                if (!$content_id || !in_array($content_type, ['comment', 'review']) || !in_array($status, ['approved', 'rejected'])) {
                    $_SESSION['adminMessage'] = "Dữ liệu không hợp lệ.";
                } else {
                    $result = $content_type === 'comment' 
                        ? $this->commentModel->approve($content_id, $status) 
                        : $this->reviewModel->approve($content_id, $status);
                    $_SESSION['adminMessage'] = $result['message'];
                }
            } elseif (isset($_POST['delete_comment'])) {
                $comment_id = filter_var($_POST['comment_id'], FILTER_VALIDATE_INT);
                if (!$comment_id) {
                    $_SESSION['adminMessage'] = "ID bình luận không hợp lệ.";
                } else {
                    $result = $this->commentModel->delete($comment_id);
                    $_SESSION['adminMessage'] = $result['message'];
                }
            } elseif (isset($_POST['delete_review'])) {
                $review_id = filter_var($_POST['review_id'], FILTER_VALIDATE_INT);
                if (!$review_id) {
                    $_SESSION['adminMessage'] = "ID đánh giá không hợp lệ.";
                } else {
                    $result = $this->reviewModel->delete($review_id);
                    $_SESSION['adminMessage'] = $result['message'];
                }
            }
            $this->redirect('admin_dashboard.php');
        }

        $pendingReviews = $this->reviewModel->getPending();
        $pendingComments = $this->commentModel->getPending();
        return ['pendingReviews' => $pendingReviews, 'pendingComments' => $pendingComments];
    }
    
    private function redirect($path) {
        header("Location: " . BASE_URL . "app/views/product/$path");
        exit();
    }
}