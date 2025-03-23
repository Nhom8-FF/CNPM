<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}
require_once ROOT_DIR . '/app/models/Discussion.php';
require_once ROOT_DIR . '/app/models/Comment.php';
require_once ROOT_DIR . '/app/models/User.php'; // Thêm User model để ghi log

class DiscussionController {
    private $conn;
    private $discussionModel;
    private $commentModel;
    private $userModel;

    public function __construct($db) {
        $this->conn = $db;
        $this->discussionModel = new Discussion($db);
        $this->commentModel = new Comment($db);
        $this->userModel = new User($db);
    }

    public function manageDiscussions($course_id) {
        session_start();
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('home.php');
        }

        $course_id = filter_var($course_id, FILTER_VALIDATE_INT);
        if (!$course_id) {
            $_SESSION['discussionError'] = "ID khóa học không hợp lệ.";
            $this->redirect('home.php');
        }

        // Kiểm tra quyền tham gia khóa học
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
        $stmt->execute();
        if ($stmt->get_result()->fetch_row()[0] == 0) {
            $_SESSION['discussionError'] = "Bạn không có quyền truy cập thảo luận của khóa học này.";
            $this->redirect('home.php');
        }
        $stmt->close();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
                $_SESSION['discussionError'] = "Yêu cầu không hợp lệ.";
                $this->redirect("discussion.php?course_id=$course_id");
            }

            if (isset($_POST['create_discussion'])) {
                $user_id = $_SESSION['user_id'];
                $title = trim($_POST['title']);
                $content = trim($_POST['content']);
                if (empty($title) || strlen($title) > 100 || empty($content) || strlen($content) > 65535) {
                    $_SESSION['discussionError'] = "Tiêu đề tối đa 100 ký tự, nội dung tối đa 65535 ký tự, không được để trống.";
                } else {
                    $result = $this->discussionModel->create($course_id, $user_id, $title, $content);
                    if ($result['success']) {
                        $this->userModel->logAction($user_id, 'create_discussion', $result['discussion_id'], "Đã tạo thảo luận '$title' trong khóa học ID $course_id");
                        $_SESSION['discussionSuccess'] = $result['message'];
                    } else {
                        $_SESSION['discussionError'] = $result['message'];
                    }
                }
            } elseif (isset($_POST['create_comment'])) {
                $discussion_id = filter_var($_POST['discussion_id'], FILTER_VALIDATE_INT);
                $user_id = $_SESSION['user_id'];
                $content = trim($_POST['content']);
                if (!$discussion_id || empty($content) || strlen($content) > 65535) {
                    $_SESSION['discussionError'] = "Dữ liệu không hợp lệ: nội dung tối đa 65535 ký tự, không được để trống.";
                } else {
                    $result = $this->commentModel->create($discussion_id, $user_id, $content);
                    if ($result['success']) {
                        $this->userModel->logAction($user_id, 'create_comment', $result['comment_id'], "Đã thêm bình luận vào thảo luận ID $discussion_id");
                        $_SESSION['discussionSuccess'] = $result['message'];
                    } else {
                        $_SESSION['discussionError'] = $result['message'];
                    }
                }
            }
            $this->redirect("discussion.php?course_id=$course_id");
        }

        $discussions = $this->discussionModel->getByCourse($course_id);
        if (!empty($discussions)) {
            $discussion_ids = array_column($discussions, 'discussion_id');
            $comments = $this->commentModel->getByDiscussionIds($discussion_ids); // Giả định thêm phương thức mới
            foreach ($discussions as &$discussion) {
                $discussion['comments'] = array_filter($comments, function($comment) use ($discussion) {
                    return $comment['discussion_id'] == $discussion['discussion_id'];
                });
            }
        }
        return $discussions;
    }

    private function redirect($path) {
        header("Location: " . BASE_URL . "app/views/product/$path");
        exit();
    }
}