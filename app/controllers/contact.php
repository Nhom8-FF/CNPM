<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}

// Include configuration and helper functions
require_once ROOT_DIR . '/app/config/config.php';
require_once ROOT_DIR . '/app/helpers/functions.php';
require_once ROOT_DIR . '/app/models/Contact.php';
require_once ROOT_DIR . '/app/models/Notification.php';
require_once ROOT_DIR . '/app/models/User.php'; 
require_once ROOT_DIR . '/src/PHPMailer.php';
require_once ROOT_DIR . '/src/Exception.php';
require_once ROOT_DIR . '/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


// Process contact form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['contactError'] = "Yêu cầu không hợp lệ.";
        redirect('home.php');
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $_SESSION['contactError'] = "Vui lòng điền đầy đủ thông tin.";
        redirect('home.php');
    } 
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['contactError'] = "Email không hợp lệ.";
        redirect('home.php');
    } 
    
    if (strlen($name) > 100 || strlen($email) > 100 || strlen($message) > 65535) {
        $_SESSION['contactError'] = "Thông tin vượt quá độ dài cho phép.";
        redirect('home.php');
    }

    // Create models
    $contactModel = new Contact($db);
    $notificationModel = new Notification($db);
    $userModel = new User($db);
    
    // Save contact to database
    $result = $contactModel->create($name, $email, $message);
    
    if ($result['success']) {
        // Send email notification
        $emailResult = sendContactEmail($name, $email, $message);
        
        if ($emailResult['success']) {
            $_SESSION['contactSuccess'] = "Tin nhắn của bạn đã được gửi thành công!";
            
            // Notify admin
            $adminUser = $db->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1")->fetch_assoc();
            if ($adminUser) {
                $notificationModel->create($adminUser['user_id'], null, 'Liên hệ mới', "Bạn nhận được liên hệ từ $name ($email).");
            }
            
            // Log action if user is logged in
            if (isset($_SESSION['user_id'])) {
                $userModel->logAction($_SESSION['user_id'], 'send_contact', null, "Đã gửi liên hệ từ $email");
            }
        } else {
            $_SESSION['contactError'] = $emailResult['message'];
        }
    } else {
        $_SESSION['contactError'] = $result['message'];
    }
    
    redirect('home.php');
}

// Send contact email
function sendContactEmail($name, $email, $message) {
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

// Redirect helper
function redirect($path) {
    header("Location: " . BASE_URL . "app/views/product/$path");
    exit();
}
?> 