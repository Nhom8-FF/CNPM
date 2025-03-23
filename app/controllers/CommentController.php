<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}
require_once ROOT_DIR . '/app/models/Comment.php';
require_once ROOT_DIR . '/app/models/User.php';

class CommentController {
    private $conn;
    private $commentModel;
    private $userModel;

    public function __construct($db) {
        $this->conn = $db;
        $this->commentModel = new Comment($db);
        $this->userModel = new User($db);
    }

    public function approve($comment_id, $status) {
        session_start();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $_SESSION['commentError'] = "Bạn không có quyền thực hiện hành động này.";
            $this->redirect('home.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token']))) {
            $_SESSION['commentError'] = "Yêu cầu không hợp lệ.";
            $this->redirect('admin_dashboard.php');
        }

        $comment_id = filter_var($comment_id, FILTER_VALIDATE_INT);
        if (!$comment_id || !in_array($status, ['approved', 'rejected'])) {
            $_SESSION['commentError'] = "Dữ liệu không hợp lệ.";
            $this->redirect('admin_dashboard.php');
        }

        $result = $this->commentModel->approve($comment_id, $status);
        if ($result['success']) {
            $this->userModel->logAction(
                $this->userModel->getCurrentAdminId(),
                'approve_comment',
                null,
                "Đã $status bình luận ID $comment_id"
            );
            $_SESSION['commentSuccess'] = $result['message'];
        } else {
            $_SESSION['commentError'] = $result['message'];
        }
        $this->redirect('admin_dashboard.php'); // Có thể thêm return URL từ tham số
    }

    public function createComment($discussion_id, $content) {
        session_start();
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['commentError'] = "Vui lòng đăng nhập để bình luận.";
            $this->redirect('home.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token']))) {
            $_SESSION['commentError'] = "Yêu cầu không hợp lệ.";
            $this->redirect('home.php');
        }

        $discussion_id = filter_var($discussion_id, FILTER_VALIDATE_INT);
        $content = trim($content);
        if (!$discussion_id || empty($content)) {
            $_SESSION['commentError'] = "Dữ liệu không hợp lệ.";
            $this->redirect('home.php');
        }

        $result = $this->commentModel->create($discussion_id, $_SESSION['user_id'], $content);
        if ($result['success']) {
            $_SESSION['commentSuccess'] = $result['message'];
        } else {
            $_SESSION['commentError'] = $result['message'];
        }
        $this->redirect('home.php'); // Có thể redirect về trang discussion
    }

    public function deleteComment($comment_id) {
        session_start();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $_SESSION['commentError'] = "Bạn không có quyền thực hiện hành động này.";
            $this->redirect('home.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token']))) {
            $_SESSION['commentError'] = "Yêu cầu không hợp lệ.";
            $this->redirect('admin_dashboard.php');
        }

        $comment_id = filter_var($comment_id, FILTER_VALIDATE_INT);
        if (!$comment_id) {
            $_SESSION['commentError'] = "ID bình luận không hợp lệ.";
            $this->redirect('admin_dashboard.php');
        }

        $result = $this->commentModel->delete($comment_id);
        if ($result['success']) {
            $this->userModel->logAction(
                $this->userModel->getCurrentAdminId(),
                'delete_comment',
                null,
                "Đã xóa bình luận ID $comment_id"
            );
            $_SESSION['commentSuccess'] = $result['message'];
        } else {
            $_SESSION['commentError'] = $result['message'];
        }
        $this->redirect('admin_dashboard.php');
    }

    public function getComments($discussion_id) {
        $discussion_id = filter_var($discussion_id, FILTER_VALIDATE_INT);
        if (!$discussion_id) {
            return [];
        }
        return $this->commentModel->getByDiscussion($discussion_id);
    }

    private function redirect($path) {
        header("Location: " . BASE_URL . "app/views/product/$path");
        exit();
    }
}