<?php
class Contact {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($name, $email, $message) {
        try {
            if (empty($name) || empty($email) || empty($message)) {
                return ['success' => false, 'message' => 'Thông tin không được để trống.'];
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Email không hợp lệ.'];
            }
            if (strlen($name) > 100 || strlen($email) > 100 || strlen($message) > 65535) {
                return ['success' => false, 'message' => 'Thông tin vượt quá độ dài cho phép.'];
            }

            $stmt = $this->conn->prepare("INSERT INTO contacts (name, email, message, submitted_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("sss", $name, $email, $message);
            $success = $stmt->execute();
            $stmt->close();
            return ['success' => $success, 'message' => $success ? 'Tin nhắn đã được gửi!' : 'Gửi tin nhắn thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function getAll($limit = 10, $offset = 0) {
        try {
            $stmt = $this->conn->prepare("SELECT contact_id, name, email, message, submitted_at FROM contacts ORDER BY submitted_at DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $contacts = [];
            while ($row = $result->fetch_assoc()) {
                $contacts[] = $row;
            }
            $stmt->close();
            return $contacts;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }
}