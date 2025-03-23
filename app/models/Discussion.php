<?php
class Discussion {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($course_id, $user_id, $title, $content) {
        try {
            if (empty($title) || strlen($title) > 100 || empty($content) || strlen($content) > 65535) {
                return ['success' => false, 'message' => 'Tiêu đề tối đa 100 ký tự, nội dung tối đa 65535 ký tự, không được để trống.'];
            }

            $stmt = $this->conn->prepare("SELECT course_id FROM courses WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Khóa học không tồn tại.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("INSERT INTO discussion (course_id, user_id, title, content, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiss", $course_id, $user_id, $title, $content);
            $success = $stmt->execute();
            $discussion_id = $this->conn->insert_id;
            $stmt->close();
            return ['success' => $success, 'discussion_id' => $discussion_id, 'message' => $success ? 'Chủ đề đã được tạo!' : 'Tạo thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function getByCourse($course_id, $limit = 10, $offset = 0) {
        try {
            $stmt = $this->conn->prepare("SELECT course_id FROM courses WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return [];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("SELECT d.discussion_id, d.title, d.content, u.username AS author, d.created_at 
                FROM discussion d 
                JOIN users u ON d.user_id = u.user_id 
                WHERE d.course_id = ? 
                ORDER BY d.created_at DESC 
                LIMIT ? OFFSET ?");
            $stmt->bind_param("iii", $course_id, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $discussions = [];
            while ($row = $result->fetch_assoc()) {
                $discussions[] = $row;
            }
            $stmt->close();
            return $discussions;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }
}