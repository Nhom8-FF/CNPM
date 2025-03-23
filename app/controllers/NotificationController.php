<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}
require_once ROOT_DIR . '/app/models/Notification.php';
require_once ROOT_DIR . '/app/models/User.php'; // Để ghi log và lấy role

class NotificationController {
    private $conn;
    private $model;
    private $userModel;

    public function __construct($db) {
        $this->conn = $db;
        $this->model = new Notification($db);
        $this->userModel = new User($db);
    }

    public function getNotifications($user_id) {
        $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
        if (!$user_id) {
            return [];
        }
        return $this->model->getByUser($user_id);
    }

    public function markAsRead($notification_id) {
        session_start();
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('home.php');
        }

        $notification_id = filter_var($notification_id, FILTER_VALIDATE_INT);
        if (!$notification_id) {
            $_SESSION['notificationError'] = "ID thông báo không hợp lệ.";
            $this->redirectBasedOnRole();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
                $_SESSION['notificationError'] = "Yêu cầu không hợp lệ.";
                $this->redirectBasedOnRole();
            }

            $result = $this->model->markAsRead($notification_id, $_SESSION['user_id']);
            if ($result['success']) {
                $this->userModel->logAction(
                    $_SESSION['user_id'],
                    'mark_notification_read',
                    null,
                    "Đã đánh dấu thông báo ID $notification_id là đã đọc"
                );
                $_SESSION['notificationSuccess'] = $result['message'];
            } else {
                $_SESSION['notificationError'] = $result['message'];
            }
        }
        $this->redirectBasedOnRole();
    }

    private function redirectBasedOnRole() {
        $role = $_SESSION['role'] ?? 'student';
        $redirectMap = [
            'student' => 'student_dashboard.php',
            'instructor' => 'instructor_dashboard.php',
            'admin' => 'admin_dashboard.php'
        ];
        $path = $redirectMap[$role] ?? 'home.php';
        header("Location: " . BASE_URL . "app/views/product/$path");
        exit();
    }

    private function redirect($path) {
        header("Location: " . BASE_URL . "app/views/product/$path");
        exit();
    }
}