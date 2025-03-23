<?php
class User {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Đăng ký người dùng
    public function register($username, $email, $password, $role = 'student') {
        error_log("User Model - Register: Starting registration for $username, $email, $role");
        
        try {
            // Validate role is one of the allowed values in the ENUM
            $valid_roles = ['student', 'instructor', 'admin'];
            if (!in_array($role, $valid_roles)) {
                error_log("User Model - Invalid role value: $role, defaulting to 'student'");
                $role = 'student'; // Default to student if invalid
            }
            
            // Find the correct table name (case-sensitive)
            $tables = $this->conn->query("SHOW TABLES");
            $users_table = "users"; // Default
            error_log("User Model - Finding correct table name");
            
            while($table = $tables->fetch_row()) {
                if (strtolower($table[0]) == 'users') {
                    $users_table = $table[0];
                    error_log("User Model - Found actual table name: " . $users_table);
                    break;
                }
            }
            
            // Check if username or email already exists
            $checkSql = "SELECT * FROM $users_table WHERE username = ? OR email = ?";
            error_log("User Model - Check SQL: " . $checkSql);
            
            $checkStmt = $this->conn->prepare($checkSql);
            if (!$checkStmt) {
                error_log("User Model - Prepare check statement failed: " . $this->conn->error);
                return ['success' => false, 'message' => 'Database error: ' . $this->conn->error];
            }
            
            $checkStmt->bind_param("ss", $username, $email);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row['username'] === $username) {
                    error_log("User Model - Username already exists: $username");
                    return ['success' => false, 'message' => 'Tên người dùng đã tồn tại.'];
                } else {
                    error_log("User Model - Email already exists: $email");
                    return ['success' => false, 'message' => 'Email đã được sử dụng.'];
                }
            }
            
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            if (!$hashed_password) {
                error_log("User Model - Password hashing failed");
                return ['success' => false, 'message' => 'Lỗi xử lý mật khẩu.'];
            }
            
            // Insert the new user
            $insertSql = "INSERT INTO $users_table (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())";
            error_log("User Model - Insert SQL: " . $insertSql . " with role=$role");
            
            $insertStmt = $this->conn->prepare($insertSql);
            if (!$insertStmt) {
                error_log("User Model - Prepare insert statement failed: " . $this->conn->error);
                return ['success' => false, 'message' => 'Database error: ' . $this->conn->error];
            }
            
            $insertStmt->bind_param("ssss", $username, $email, $hashed_password, $role);
            
            if ($insertStmt->execute()) {
                $user_id = $insertStmt->insert_id;
                error_log("User Model - User registered successfully with ID: " . $user_id);
                
                // Double-check that the user was actually inserted
                $verifySql = "SELECT user_id FROM $users_table WHERE username = ? LIMIT 1";
                $verifyStmt = $this->conn->prepare($verifySql);
                if ($verifyStmt) {
                    $verifyStmt->bind_param("s", $username);
                    $verifyStmt->execute();
                    $verifyResult = $verifyStmt->get_result();
                    
                    if ($verifyResult->num_rows > 0) {
                        $verifyData = $verifyResult->fetch_assoc();
                        error_log("User Model - Verified user exists with ID: " . $verifyData['user_id']);
                    } else {
                        error_log("User Model - WARNING: User was not found after insertion");
                    }
                    $verifyStmt->close();
                }
                
                return ['success' => true, 'message' => 'Đăng ký thành công!', 'user_id' => $user_id];
            } else {
                error_log("User Model - Insert execute failed: " . $insertStmt->error);
                return ['success' => false, 'message' => 'Lỗi đăng ký: ' . $insertStmt->error];
            }
        } catch (Exception $e) {
            error_log("User Model - Exception: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()];
        }
    }

    // Đăng nhập
    public function login($username, $password) {
        try {
            $stmt = $this->conn->prepare("SELECT user_id, username, password, role, is_locked FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    return ['success' => true, 'data' => $user];
                }
            }
            return ['success' => false, 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    // Đặt lại mật khẩu
    public function resetPassword($user_id, $new_password) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            $success = $stmt->execute();
            $stmt->close();
            return ['success' => $success, 'message' => $success ? 'Đặt lại mật khẩu thành công!' : 'Đặt lại mật khẩu thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    // Lấy thông tin người dùng theo ID
    public function getById($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT user_id, username, email, role, created_at FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $result ?: null;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    // Lấy tất cả người dùng (cho admin)
    public function getAllUsers($limit = 10, $offset = 0) {
        try {
            $stmt = $this->conn->prepare("SELECT user_id, username, email, role, created_at FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            $stmt->close();
            return $users;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    /**
     * Khóa hoặc mở tài khoản người dùng
     * @param int $user_id ID người dùng
     * @param bool $is_locked Trạng thái khóa (true = khóa, false = mở)
     * @return array Kết quả (success, message)
     */
    public function toggleLockUser($user_id, $is_locked) {
        try {
            $stmt = $this->conn->prepare("UPDATE users SET is_locked = ? WHERE user_id = ?");
            $stmt->bind_param("ii", $is_locked, $user_id);
            $success = $stmt->execute();
            $stmt->close();
            $status = $is_locked ? 'khóa' : 'mở';
            $this->logAction($this->getCurrentAdminId(), 'lock_user', $user_id, "Đã $status tài khoản ID $user_id");
            $this->sendNotificationOnAction($user_id, $is_locked ? 'lock' : 'unlock');
            return ['success' => $success, 'message' => $success ? "Tài khoản đã được $status thành công!" : "Thao tác $status thất bại."];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    /**
     * Phân quyền cho người dùng
     * @param int $user_id ID người dùng
     * @param string $role Vai trò mới (student, instructor, admin)
     * @return array Kết quả (success, message)
     */
    public function changeRole($user_id, $role) {
        try {
            $valid_roles = ['student', 'instructor', 'admin'];
            if (!in_array($role, $valid_roles)) {
                return ['success' => false, 'message' => 'Vai trò không hợp lệ.'];
            }

            $stmt = $this->conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
            $stmt->bind_param("si", $role, $user_id);
            $success = $stmt->execute();
            $stmt->close();
            $this->logAction($this->getCurrentAdminId(), 'change_role', $user_id, "Đã thay đổi vai trò của ID $user_id thành $role");
            return ['success' => $success, 'message' => $success ? "Phân quyền thành công thành $role!" : 'Phân quyền thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }


    /**
     * Cập nhật lại rating trung bình của khóa học sau khi xóa đánh giá
     * @param int $review_id ID đánh giá vừa xóa
     * @return void
     */
    private function updateCourseRating($review_id) {
        $stmt = $this->conn->prepare("SELECT course_id FROM reviews WHERE review_id = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        $course_id = $stmt->get_result()->fetch_assoc()['course_id'];
        $stmt->close();
        if ($course_id) {
            $courseModel = new Course($this->conn);
            $courseModel->updateRating($course_id);
        }
    }

    /**
     * Xóa tài khoản người dùng (bao gồm lịch sử liên quan)
     * @param int $user_id ID người dùng
     * @return array Kết quả (success, message)
     */
    public function deleteUser($user_id) {
        try {
            $this->conn->begin_transaction();

            $stmt = $this->conn->prepare("DELETE FROM enrollments WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            $stmt = $this->conn->prepare("DELETE FROM reviews WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            $stmt = $this->conn->prepare("DELETE FROM comments WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            $stmt = $this->conn->prepare("DELETE FROM discussion WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            $stmt = $this->conn->prepare("DELETE FROM chatbots WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            $stmt = $this->conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $success = $stmt->execute();
            $stmt->close();

            $this->logAction($this->getCurrentAdminId(), 'delete_user', $user_id, "Đã xóa tài khoản ID $user_id");
            $this->conn->commit();
            return ['success' => $success, 'message' => $success ? 'Tài khoản đã được xóa!' : 'Xóa tài khoản thất bại.'];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    /**
     * Gửi thông báo khi khóa/mở tài khoản
     * @param int $user_id ID người dùng
     * @param string $action Hành động (lock/unlock)
     * @return array Kết quả (success, message)
     */
    public function sendNotificationOnAction($user_id, $action) {
        try {
            $notificationModel = new Notification($this->conn); // Giả định có Model Notification
            $message = ($action === 'lock') ? 'Tài khoản của bạn đã bị khóa.' : 'Tài khoản của bạn đã được mở.';
            $notificationModel->create($user_id, 'Thông báo hệ thống', $message, 'warning');
            return ['success' => true, 'message' => 'Thông báo đã được gửi!'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi gửi thông báo: ' . $e->getMessage()];
        }
    }

    /**
     * Lấy lịch sử hành động của admin
     * @param int $limit Số bản ghi tối đa
     * @param int $offset Bắt đầu từ bản ghi nào
     * @return array Danh sách lịch sử hành động
     */
    public function getUserLogs($limit = 10, $offset = 0) {
        try {
            $stmt = $this->conn->prepare("SELECT log_id, user_id, action, target_user_id, details, created_at FROM user_logs ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $logs = [];
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
            $stmt->close();
            return $logs;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }


    /**
     * Ghi log hành động của admin
     * @param int $admin_id ID admin thực hiện hành động
     * @param string $action Hành động
     * @param int|null $target_user_id ID người dùng bị ảnh hưởng
     * @param string $details Chi tiết hành động
     * @return void
     */
    public function logAction($admin_id, $action, $target_user_id, $details) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO user_logs (user_id, action, target_user_id, details) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isis", $admin_id, $action, $target_user_id, $details);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Lỗi ghi log: " . $e->getMessage());
        }
    }

    /**
     * Lấy ID của admin hiện tại (giả định từ session)
     * @return int|null ID admin hoặc null nếu không xác định
     */
    public function getCurrentAdminId() {
        $admin_id = $_SESSION['user_id'] ?? null;
        if (!$admin_id || $this->getById($admin_id)['role'] !== 'admin') {
            throw new Exception("Không có quyền admin.");
        }
        return $admin_id;
    }
}