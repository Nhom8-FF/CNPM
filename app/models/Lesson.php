<?php
class Lesson {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($course_id, $title, $content, $order_index, $duration) {
        try {
            if (empty($title) || strlen($title) > 100 || empty($content) || strlen($content) > 65535 || $order_index < 0 || $duration < 0) {
                return ['success' => false, 'message' => 'Dữ liệu không hợp lệ: tiêu đề tối đa 100 ký tự, nội dung tối đa 65535 ký tự, thứ tự và thời lượng không âm.'];
            }

            $stmt = $this->conn->prepare("SELECT course_id FROM courses WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Khóa học không tồn tại.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("INSERT INTO lessons (course_id, title, content, order_index, duration) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issii", $course_id, $title, $content, $order_index, $duration);
            $success = $stmt->execute();
            $lesson_id = $this->conn->insert_id;
            $stmt->close();
            return ['success' => $success, 'lesson_id' => $lesson_id, 'message' => $success ? 'Bài giảng đã được thêm!' : 'Thêm bài giảng thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function update($lesson_id, $course_id, $title, $content, $order_index, $duration) {
        try {
            $stmt = $this->conn->prepare("SELECT lesson_id FROM lessons WHERE lesson_id = ? AND course_id = ?");
            $stmt->bind_param("ii", $lesson_id, $course_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Bài giảng không tồn tại hoặc không thuộc khóa học này.'];
            }
            $stmt->close();

            if (empty($title) || strlen($title) > 100 || empty($content) || strlen($content) > 65535 || $order_index < 0 || $duration < 0) {
                return ['success' => false, 'message' => 'Dữ liệu không hợp lệ.'];
            }

            $stmt = $this->conn->prepare("UPDATE lessons SET title = ?, content = ?, order_index = ?, duration = ? WHERE lesson_id = ? AND course_id = ?");
            $stmt->bind_param("ssiiii", $title, $content, $order_index, $duration, $lesson_id, $course_id);
            $success = $stmt->execute();
            $stmt->close();
            return ['success' => $success, 'message' => $success ? 'Bài giảng đã được cập nhật!' : 'Cập nhật thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function delete($lesson_id, $course_id) {
        try {
            $stmt = $this->conn->prepare("SELECT lesson_id FROM lessons WHERE lesson_id = ? AND course_id = ?");
            $stmt->bind_param("ii", $lesson_id, $course_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Bài giảng không tồn tại hoặc không thuộc khóa học này.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("DELETE FROM lessons WHERE lesson_id = ? AND course_id = ?");
            $stmt->bind_param("ii", $lesson_id, $course_id);
            $success = $stmt->execute();
            $stmt->close();
            return ['success' => $success, 'message' => $success ? 'Bài giảng đã được xóa!' : 'Xóa thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function getByCourse($course_id) {
        try {
            $stmt = $this->conn->prepare("SELECT course_id FROM courses WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return [];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("SELECT lesson_id, title, content, order_index, duration FROM lessons WHERE course_id = ? ORDER BY order_index ASC");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $lessons = [];
            while ($row = $result->fetch_assoc()) {
                $lessons[] = $row;
            }
            $stmt->close();
            return $lessons;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }
}