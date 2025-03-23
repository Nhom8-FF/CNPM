<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}
require_once ROOT_DIR . '/app/models/Contact.php';
require_once ROOT_DIR . '/app/models/Notification.php';
require_once ROOT_DIR . '/app/models/User.php'; 
require_once ROOT_DIR . '/src/PHPMailer.php';
require_once ROOT_DIR . '/src/Exception.php';
require_once ROOT_DIR . '/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class ContactController {
    private $conn;
    private $contactModel;
    private $notificationModel;
    private $userModel;

    public function __construct($db) {
        $this->conn = $db;
        $this->contactModel = new Contact($db);
        $this->notificationModel = new Notification($db);
        $this->userModel = new User($db);
    }

    public function processContact() {
        session_start();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
            if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
                $_SESSION['contactError'] = "Yêu cầu không hợp lệ.";
                $this->redirect('home.php');
            }

            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $message = trim($_POST['message'] ?? '');

            if (empty($name) || empty($email) || empty($message)) {
                $_SESSION['contactError'] = "Vui lòng điền đầy đủ thông tin.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['contactError'] = "Email không hợp lệ.";
            } elseif (strlen($name) > 100 || strlen($email) > 100 || strlen($message) > 65535) {
                $_SESSION['contactError'] = "Thông tin vượt quá độ dài cho phép.";
            } else {
                $result = $this->contactModel->create($name, $email, $message); // Sửa create thành save
                if ($result['success']) {
                    $emailResult = $this->sendContactEmail($name, $email, $message);
                    if ($emailResult['success']) {
                        $_SESSION['contactSuccess'] = "Tin nhắn của bạn đã được gửi thành công!";
                        $adminUser = $this->conn->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1")->fetch_assoc();
                        if ($adminUser) {
                            $this->notificationModel->create($adminUser['user_id'], null, 'Liên hệ mới', "Bạn nhận được liên hệ từ $name ($email).");
                        }
                        // Ghi log nếu người dùng đã đăng nhập
                        if (isset($_SESSION['user_id'])) {
                            $this->userModel->logAction($_SESSION['user_id'], 'send_contact', null, "Đã gửi liên hệ từ $email");
                        }
                    } else {
                        $_SESSION['contactError'] = $emailResult['message'];
                    }
                } else {
                    $_SESSION['contactError'] = $result['message'];
                }
            }
            $this->redirect('home.php'); // Có thể thay bằng trang liên hệ
        }
    }

    private function sendContactEmail($name, $email, $message) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'hoctap435@gmail.com';
            $mail->Password = 'vznk pkkp iety fzkm';
            $mail->SMTPSecure = 'ssl';
            $mail->Port = 465;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom('hoctap435@gmail.com', 'Học Tập Trực Tuyến');
            $mail->addReplyTo($email, $name);
            $mail->addAddress('hoctap435@gmail.com');
            $mail->Subject = 'Liên hệ từ khách hàng: ' . $name;
            $mail->Body = "Họ và tên: $name\nEmail: $email\nNội dung:\n$message";

            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => "Không thể gửi email. Lỗi: " . $mail->ErrorInfo];
        }
    }

    private function redirect($path) {
        header("Location: " . BASE_URL . "app/views/product/$path");
        exit();
    }
}