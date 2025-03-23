<?php
class Notification {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($user_id, $course_id, $title, $content, $type = 'info') {
        try {
            if (empty($title) || strlen($title) > 100 || empty($content) || strlen($content) > 65535) {
                return ['success' => false, 'message' => 'Tiêu đề tối đa 100 ký tự, nội dung tối đa 65535 ký tự, không được để trống.'];
            }
            if (!in_array($type, ['info', 'warning', 'error'])) {
                return ['success' => false, 'message' => 'Loại thông báo không hợp lệ.'];
            }

            $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Người dùng không tồn tại.'];
            }
            $stmt->close();

            if ($course_id) {
                $stmt = $this->conn->prepare("SELECT course_id FROM courses WHERE course_id = ? AND status = 1");
                $stmt->bind_param("i", $course_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows == 0) {
                    return ['success' => false, 'message' => 'Khóa học không tồn tại hoặc không hoạt động.'];
                }
                $stmt->close();
            }

            $stmt = $this->conn->prepare("INSERT INTO notifications (user_id, course_id, title, content, type, is_read, created_at) VALUES (?, ?, ?, ?, ?, FALSE, NOW())");
            $stmt->bind_param("iisss", $user_id, $course_id, $title, $content, $type);
            $success = $stmt->execute();
            $notification_id = $this->conn->insert_id;
            $stmt->close();
            return ['success' => $success, 'notification_id' => $notification_id, 'message' => $success ? 'Thông báo đã được tạo!' : 'Tạo thông báo thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function markAsRead($notification_id, $user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT notification_id FROM notifications WHERE notification_id = ? AND user_id = ? AND is_read = FALSE");
            $stmt->bind_param("ii", $notification_id, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Thông báo không tồn tại, không thuộc về bạn hoặc đã được đọc.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("UPDATE notifications SET is_read = TRUE WHERE notification_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $notification_id, $user_id);
            $success = $stmt->execute();
            $stmt->close();
            return ['success' => $success, 'message' => $success ? 'Thông báo đã được đánh dấu là đã đọc!' : 'Đánh dấu thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function getByUser($user_id, $limit = 10, $offset = 0) {
        try {
            $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return [];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("SELECT notification_id, course_id, title, content, type, is_read, created_at 
                FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?");
            $stmt->bind_param("iii", $user_id, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            $stmt->close();
            return $notifications;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }
}