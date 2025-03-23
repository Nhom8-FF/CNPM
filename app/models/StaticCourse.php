<?php
class StaticCourse {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Thêm khóa học tĩnh
    public function create($title, $description, $drive_link) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO static_courses (title, description, drive_link, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("sss", $title, $description, $drive_link);
            $success = $stmt->execute();
            $static_course_id = $this->conn->insert_id;
            $stmt->close();

            return ['success' => $success, 'static_course_id' => $static_course_id, 'message' => $success ? 'Khóa học tĩnh đã được thêm!' : 'Thêm thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    // Cập nhật khóa học tĩnh
    public function update($static_course_id, $title, $description, $drive_link) {
        try {
            $stmt = $this->conn->prepare("UPDATE static_courses SET title = ?, description = ?, drive_link = ? WHERE static_course_id = ?");
            $stmt->bind_param("sssi", $title, $description, $drive_link, $static_course_id);
            $success = $stmt->execute();
            $stmt->close();

            return ['success' => $success, 'message' => $success ? 'Khóa học tĩnh đã được cập nhật!' : 'Cập nhật thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    // Xóa khóa học tĩnh
    public function delete($static_course_id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM static_courses WHERE static_course_id = ?");
            $stmt->bind_param("i", $static_course_id);
            $success = $stmt->execute();
            $stmt->close();

            return ['success' => $success, 'message' => $success ? 'Khóa học tĩnh đã được xóa!' : 'Xóa thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    // Lấy tất cả khóa học tĩnh
    public function getAll($limit = 10, $offset = 0) {
        try {
            $stmt = $this->conn->prepare("SELECT static_course_id, title, description, drive_link FROM static_courses ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $static_courses = [];
            while ($row = $result->fetch_assoc()) {
                $static_courses[] = $row;
            }
            $stmt->close();
            return $static_courses;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    // Lấy thông tin khóa học tĩnh theo ID
    public function getById($static_course_id) {
        try {
            $stmt = $this->conn->prepare("SELECT static_course_id, title, description, drive_link FROM static_courses WHERE static_course_id = ?");
            $stmt->bind_param("i", $static_course_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $result ?: null;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }
}