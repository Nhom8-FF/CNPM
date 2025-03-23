<?php
class Enrollment {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function enroll($user_id, $course_id) { // Giữ tên enroll để đồng bộ với controller
        try {
            $this->conn->begin_transaction();

            // Kiểm tra user_id và course_id
            $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'student'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                $this->conn->rollback();
                return ['success' => false, 'message' => 'Người dùng không tồn tại hoặc không phải học viên.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("SELECT course_id FROM courses WHERE course_id = ? AND status = 1");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                $this->conn->rollback();
                return ['success' => false, 'message' => 'Khóa học không tồn tại hoặc không hoạt động.'];
            }
            $stmt->close();

            // Kiểm tra trùng lặp
            $stmt = $this->conn->prepare("SELECT enrollment_id FROM enrollments WHERE user_id = ? AND course_id = ?");
            $stmt->bind_param("ii", $user_id, $course_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $this->conn->rollback();
                return ['success' => false, 'message' => 'Bạn đã đăng ký khóa học này rồi.'];
            }
            $stmt->close();

            // Thêm bản ghi đăng ký
            $stmt = $this->conn->prepare("INSERT INTO enrollments (user_id, course_id, enrolled_date, status) VALUES (?, ?, CURDATE(), 'active')");
            $stmt->bind_param("ii", $user_id, $course_id);
            $success = $stmt->execute();
            $stmt->close();

            // Cập nhật số lượng học viên
            if ($success) {
                $stmt = $this->conn->prepare("UPDATE courses SET students = students + 1 WHERE course_id = ?");
                $stmt->bind_param("i", $course_id);
                $stmt->execute();
                $stmt->close();
            }

            if ($success) {
                $this->conn->commit();
                return ['success' => true, 'message' => 'Đăng ký thành công!'];
            } else {
                $this->conn->rollback();
                return ['success' => false, 'message' => 'Đăng ký thất bại.'];
            }
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function getByStudent($user_id, $limit = 5, $offset = 0) {
        try {
            $stmt = $this->conn->prepare("SELECT c.course_id, c.title, c.description, e.status 
                FROM enrollments e 
                JOIN courses c ON e.course_id = c.course_id 
                WHERE e.user_id = ? AND e.status = 'active' 
                ORDER BY c.created_at DESC 
                LIMIT ? OFFSET ?");
            $stmt->bind_param("iii", $user_id, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $enrollments = [];
            while ($row = $result->fetch_assoc()) {
                $enrollments[] = $row;
            }
            $stmt->close();
            return $enrollments;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    public function countByStudent($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM enrollments WHERE user_id = ? AND status = 'active'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    public function delete($user_id, $course_id) {
        try {
            $this->conn->begin_transaction();

            $stmt = $this->conn->prepare("SELECT enrollment_id FROM enrollments WHERE user_id = ? AND course_id = ? AND status = 'active'");
            $stmt->bind_param("ii", $user_id, $course_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                $this->conn->rollback();
                return ['success' => false, 'message' => 'Bạn chưa đăng ký khóa học này hoặc đã hủy trước đó.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("UPDATE enrollments SET status = 'inactive' WHERE user_id = ? AND course_id = ?");
            $stmt->bind_param("ii", $user_id, $course_id);
            $success = $stmt->execute();
            $stmt->close();

            if ($success) {
                $stmt = $this->conn->prepare("UPDATE courses SET students = students - 1 WHERE course_id = ? AND students > 0");
                $stmt->bind_param("i", $course_id);
                $stmt->execute();
                $stmt->close();
            }

            if ($success) {
                $this->conn->commit();
                return ['success' => true, 'message' => 'Hủy đăng ký thành công!'];
            } else {
                $this->conn->rollback();
                return ['success' => false, 'message' => 'Hủy đăng ký thất bại.'];
            }
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }
}