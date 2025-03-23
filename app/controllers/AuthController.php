<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}

// Include configuration and helper functions
require_once ROOT_DIR . '/app/config/config.php';
require_once ROOT_DIR . '/app/helpers/functions.php';

require_once ROOT_DIR . '/app/models/User.php';
require_once ROOT_DIR . '/app/models/Notification.php';
require_once ROOT_DIR . '/app/controllers/AppController.php';
require_once ROOT_DIR . '/src/PHPMailer.php';
require_once ROOT_DIR . '/src/Exception.php';
require_once ROOT_DIR . '/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AuthController extends AppController {
    private $userModel;
    private $notificationModel;

    public function __construct($db) {
        parent::__construct($db);
        $this->userModel = new User($db);
        $this->notificationModel = new Notification($db);
    }

    public function index() {
        $this->redirect('home.php');
    }

    public function register() {
        // Check if form was submitted with register button
        if (!isset($_POST['register'])) {
            $this->sendJsonResponse('error', 'Biểu mẫu đăng ký không hợp lệ.');
            return;
        }
        
        // Check if action is AJAX request
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        try {
            // Get data from form
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = isset($_POST['role']) ? trim($_POST['role']) : 'student';
            
            // Validate data
            if (empty($username) || empty($email) || empty($password)) {
                if ($isAjax) {
                    $this->sendJsonResponse('error', 'Vui lòng điền đầy đủ thông tin đăng ký.');
                } else {
                    $_SESSION['registerError'] = 'Vui lòng điền đầy đủ thông tin đăng ký.';
                    header("Location: " . BASE_URL . "app/views/product/home.php");
                    exit();
                }
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                if ($isAjax) {
                    $this->sendJsonResponse('error', 'Địa chỉ email không hợp lệ.');
                } else {
                    $_SESSION['registerError'] = 'Địa chỉ email không hợp lệ.';
                    header("Location: " . BASE_URL . "app/views/product/home.php");
                    exit();
                }
            }
            
            // Validate role
            $validRoles = ['student', 'instructor'];
            if (!in_array($role, $validRoles)) {
                $role = 'student'; // Default to student if role is invalid
            }
            
            // Check if username exists
            $sql = "SELECT * FROM users WHERE username = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                if ($isAjax) {
                    $this->sendJsonResponse('error', 'Tên đăng nhập đã tồn tại.');
                } else {
                    $_SESSION['registerError'] = 'Tên đăng nhập đã tồn tại.';
                    header("Location: " . BASE_URL . "app/views/product/home.php");
                    exit();
                }
            }
            
            // Check if email exists
            $sql = "SELECT * FROM users WHERE email = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                if ($isAjax) {
                    $this->sendJsonResponse('error', 'Email đã tồn tại.');
                } else {
                    $_SESSION['registerError'] = 'Email đã tồn tại.';
                    header("Location: " . BASE_URL . "app/views/product/home.php");
                    exit();
                }
            }
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
            
            if ($stmt->execute()) {
                // Registration successful
                if ($isAjax) {
                    $this->sendJsonResponse('success', 'Đăng ký thành công! Vui lòng đăng nhập.', null, BASE_URL . "app/views/product/home.php");
                } else {
                    $_SESSION['registerSuccess'] = 'Đăng ký thành công! Vui lòng đăng nhập.';
                    header("Location: " . BASE_URL . "app/views/product/home.php");
                    exit();
                }
            } else {
                // Registration failed
                if ($isAjax) {
                    $this->sendJsonResponse('error', 'Đăng ký thất bại. Vui lòng thử lại sau.');
                } else {
                    $_SESSION['registerError'] = 'Đăng ký thất bại. Vui lòng thử lại sau.';
                    header("Location: " . BASE_URL . "app/views/product/home.php");
                    exit();
                }
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            
            if ($isAjax) {
                $this->sendJsonResponse('error', 'Có lỗi xảy ra khi đăng ký. Vui lòng thử lại sau.');
            } else {
                $_SESSION['registerError'] = 'Có lỗi xảy ra khi đăng ký. Vui lòng thử lại sau.';
                header("Location: " . BASE_URL . "app/views/product/home.php");
                exit();
            }
        }
    }

    public function login() {
        // Check if form was submitted with login button
        if (!isset($_POST['login'])) {
            $this->sendJsonResponse('error', 'Biểu mẫu đăng nhập không hợp lệ.');
            return;
        }
        
        // Validate incoming data
        if (!isset($_POST['username']) || !isset($_POST['password'])) {
            $this->sendJsonResponse('error', 'Vui lòng nhập tên đăng nhập và mật khẩu.');
            return;
        }
        
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($password)) {
            $this->sendJsonResponse('error', 'Vui lòng nhập tên đăng nhập và mật khẩu.');
            return;
        }
        
        // Check if action is AJAX request
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        try {
            // Check user credentials
            $sql = "SELECT * FROM users WHERE username = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // No user found with this username
                error_log("Login failed: User not found: $username");
                
                if ($isAjax) {
                    $this->sendJsonResponse('error', 'Tên đăng nhập hoặc mật khẩu không đúng.');
                } else {
                    $_SESSION['loginError'] = 'Tên đăng nhập hoặc mật khẩu không đúng.';
                    header("Location: " . BASE_URL . "app/views/product/home.php");
                    exit();
                }
            }
            
            $user = $result->fetch_assoc();
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                // Password is incorrect
                error_log("Login failed: Incorrect password for user: $username");
                
                if ($isAjax) {
                    $this->sendJsonResponse('error', 'Tên đăng nhập hoặc mật khẩu không đúng.');
                } else {
                    $_SESSION['loginError'] = 'Tên đăng nhập hoặc mật khẩu không đúng.';
                    header("Location: " . BASE_URL . "app/views/product/home.php");
                    exit();
                }
            }
            
            // Login successful
            error_log("Login successful for user: $username");
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Determine redirect based on role
            $redirect = BASE_URL;
            if ($user['role'] === 'admin') {
                $redirect .= 'app/views/product/admin_dashboard.php';
            } elseif ($user['role'] === 'instructor') {
                $redirect .= 'app/views/product/instructor_dashboard.php';
            } elseif ($user['role'] === 'student') {
                $redirect .= 'app/views/product/student_dashboard.php';
            }
            
            if ($isAjax) {
                $this->sendJsonResponse('success', 'Đăng nhập thành công!', null, $redirect);
            } else {
                header("Location: $redirect");
                exit();
            }
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            
            if ($isAjax) {
                $this->sendJsonResponse('error', 'Có lỗi xảy ra khi đăng nhập. Vui lòng thử lại sau.');
            } else {
                $_SESSION['loginError'] = 'Có lỗi xảy ra khi đăng nhập. Vui lòng thử lại sau.';
                header("Location: " . BASE_URL . "app/views/product/home.php");
                exit();
            }
        }
    }

    public function forgotPassword() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['forgot_password'])) {
            return;
        }

        if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
            $_SESSION['forgotError'] = "Yêu cầu không hợp lệ.";
            $this->redirect('home.php');
        }

        $email = trim($_POST['forgot_email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['forgotError'] = "Email không hợp lệ.";
            $this->redirect('home.php');
        }

        $stmt = $this->conn->prepare("SELECT user_id, username FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            $verification_code = sprintf("%05d", rand(0, 99999));
            $_SESSION['forgot_user_id'] = $user['user_id'];
            $_SESSION['verification_code'] = $verification_code;
            $_SESSION['reset_email'] = $email;
            $_SESSION['code_expiry'] = time() + 60;

            $result = $this->sendVerificationEmail($email, $verification_code);
            $_SESSION['forgot' . ($result['success'] ? 'Success' : 'Error')] = $result['message'];
        } else {
            $_SESSION['forgotError'] = "Email không tồn tại trong hệ thống.";
        }
        $this->redirect('home.php');
    }

    public function resetPassword() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['reset-password'])) {
            return;
        }

        if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
            $_SESSION['resetError'] = "Yêu cầu không hợp lệ.";
            $this->redirect('home.php');
        }

        $code = trim($_POST['verification_code']);
        $new_password = trim($_POST['new_password']);

        if (!isset($_SESSION['code_expiry']) || time() > $_SESSION['code_expiry']) {
            $_SESSION['resetError'] = "Mã xác nhận đã hết hạn. Vui lòng yêu cầu mã mới.";
            $this->cleanupSession();
        } elseif ($code !== $_SESSION['verification_code']) {
            $_SESSION['resetError'] = "Mã xác nhận không đúng.";
        } elseif (strlen($new_password) < 6) {
            $_SESSION['resetError'] = "Mật khẩu mới phải ít nhất 6 ký tự.";
        } else {
            $result = $this->userModel->resetPassword($_SESSION['forgot_user_id'], $new_password);
            $_SESSION['reset' . ($result['success'] ? 'Success' : 'Error')] = $result['message'];
            if ($result['success']) $this->cleanupSession();
        }
        $this->redirect('home.php');
    }

    public function logout() {
        
        // Unset all session variables
        $_SESSION = array();
        
        // Destroy the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy the session
        session_destroy();
        
        // Redirect to home page
        error_log("User logged out successfully");
        header("Location: " . BASE_URL . "app/views/product/home.php");
        exit();
    }

    private function sendVerificationEmail($email, $code) {
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
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = "Mã xác nhận khôi phục mật khẩu";
            $mail->Body = "<h2>Mã xác nhận</h2><p>Mã của bạn là: <strong>{$code}</strong></p><p>Hiệu lực: 5 phút.</p>";
            $mail->AltBody = "Mã xác nhận: {$code}\nHiệu lực: 5 phút.";
            $mail->send();
            return ['success' => true, 'message' => "Mã xác nhận đã được gửi đến email của bạn."];
        } catch (Exception $e) {
            return ['success' => false, 'message' => "Không thể gửi email. Lỗi: {$mail->ErrorInfo}"];
        }
    }

    private function cleanupSession() {
        unset($_SESSION['forgot_user_id'], $_SESSION['verification_code'], $_SESSION['reset_email'], $_SESSION['code_expiry']);
    }

    protected function redirect($path) {
        // Theo dõi số lần chuyển hướng để phát hiện vòng lặp
        $_SESSION['redirect_count'] = ($_SESSION['redirect_count'] ?? 0) + 1;

        if ($_SESSION['redirect_count'] > 5) {
            error_log("Phát hiện vòng lặp chuyển hướng. Dừng chuyển hướng đến: " . BASE_URL . "app/views/product/$path");
            die("Lỗi: Quá nhiều lần chuyển hướng. Vui lòng xóa cookie trình duyệt và thử lại.");
        }

        error_log("Chuyển hướng đến: " . BASE_URL . "app/views/product/$path");
        header("Location: " . BASE_URL . "app/views/product/$path");
        exit();
    }

    protected function redirectBasedOnRole($role) {
        switch ($role) {
            case 'student':
                $this->redirect('student_dashboard.php');
                break;
            case 'instructor':
                $this->redirect('instructor_dashboard.php');
                break;
            case 'admin':
                $this->redirect('admin_dashboard.php');
                break;
            default:
                $this->redirect('home.php');
        }
    }

    // Add this method to the AuthController class to handle JSON responses
    private function sendJsonResponse($status, $message, $data = null, $redirect = null) {
        header('Content-Type: application/json');
        $response = [
            'status' => $status,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if ($redirect !== null) {
            $response['redirect'] = $redirect;
        }
        
        echo json_encode($response);
        exit();
    }
}