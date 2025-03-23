<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}

require_once ROOT_DIR . '/app/models/Review.php';
require_once ROOT_DIR . '/app/models/Course.php';
require_once ROOT_DIR . '/app/models/Notification.php';

class ReviewController {
    private $conn;
    private $reviewModel;
    private $courseModel;
    private $notificationModel;

    public function __construct($db) {
        $this->conn = $db;
        $this->reviewModel = new Review($db);
        $this->courseModel = new Course($db);
        $this->notificationModel = new Notification($db);
    }

    public function submitReview() {
        session_start();
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['review_submit']) && isset($_SESSION['user_id'])) {
            if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
                $_SESSION['reviewError'] = "Yêu cầu không hợp lệ.";
                header("Location: " . $_SERVER['PHP_SELF'] . "#reviews");
                exit();
            }

            $review_text = trim($_POST['comment'] ?? '');
            $review_rating = intval($_POST['rating'] ?? 5);
            $user_id = $_SESSION['user_id'];
            $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : null;

            if (empty($review_text)) {
                $_SESSION['reviewError'] = "Vui lòng nhập bình luận.";
            } else {
                $result = $this->reviewModel->add($course_id, $user_id, $review_text, $review_rating);
                if ($result['success']) {
                    $_SESSION['reviewSuccess'] = "Đánh giá đã được gửi! Đang chờ phê duyệt.";
                    // Cập nhật rating trung bình của khóa học
                    if ($course_id) {
                        $this->courseModel->updateRating($course_id);
                    }
                    // Gửi thông báo cho admin
                    $adminUser = $this->conn->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1")->fetch_assoc();
                    if ($adminUser) {
                        $this->notificationModel->create($adminUser['user_id'], $course_id, 'Đánh giá mới', "Một đánh giá mới đã được gửi cho khóa học ID $course_id.");
                    }
                } else {
                    $_SESSION['reviewError'] = $result['message'];
                }
            }
            header("Location: " . $_SERVER['PHP_SELF'] . "#reviews");
            exit();
        }
    }

    public function getReviews($course_id = null) {
        $reviews = $this->reviewModel->getByCourse($course_id);
        return $reviews;
    }
}