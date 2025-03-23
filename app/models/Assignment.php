<?php
class Assignment {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Thêm bài tập mới
    public function create($course_id, $lesson_id, $title, $description, $due_date, $max_score) {
        try {
            $stmt = $this->conn->prepare("SELECT instructor_id FROM courses WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $instructor_id = $stmt->get_result()->fetch_assoc()['instructor_id'];
            $stmt->close();

            if ($instructor_id !== $_SESSION['user_id']) {
                return ['success' => false, 'message' => 'Bạn không có quyền tạo bài tập cho khóa học này.'];
            }

            $stmt = $this->conn->prepare("INSERT INTO assignments (course_id, lesson_id, title, description, due_date, max_score) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssi", $course_id, $lesson_id, $title, $description, $due_date, $max_score);
            $success = $stmt->execute();
            $assignment_id = $this->conn->insert_id;
            $stmt->close();
            return ['success' => $success, 'assignment_id' => $assignment_id, 'message' => $success ? 'Bài tập đã được thêm!' : 'Thêm bài tập thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function update($assignment_id, $title, $description, $due_date, $max_score) {
        try {
            $stmt = $this->conn->prepare("SELECT course_id FROM assignments WHERE assignment_id = ?");
            $stmt->bind_param("i", $assignment_id);
            $stmt->execute();
            $course_id = $stmt->get_result()->fetch_assoc()['course_id'];
            $stmt->close();

            $stmt = $this->conn->prepare("SELECT instructor_id FROM courses WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $instructor_id = $stmt->get_result()->fetch_assoc()['instructor_id'];
            $stmt->close();

            if ($instructor_id !== $_SESSION['user_id']) {
                return ['success' => false, 'message' => 'Bạn không có quyền cập nhật bài tập này.'];
            }

            $stmt = $this->conn->prepare("UPDATE assignments SET title = ?, description = ?, due_date = ?, max_score = ? WHERE assignment_id = ?");
            $stmt->bind_param("sssii", $title, $description, $due_date, $max_score, $assignment_id);
            $success = $stmt->execute();
            $stmt->close();
            return ['success' => $success, 'message' => $success ? 'Bài tập đã được cập nhật!' : 'Cập nhật thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function delete($assignment_id) {
        try {
            $stmt = $this->conn->prepare("SELECT course_id FROM assignments WHERE assignment_id = ?");
            $stmt->bind_param("i", $assignment_id);
            $stmt->execute();
            $course_id = $stmt->get_result()->fetch_assoc()['course_id'];
            $stmt->close();

            $stmt = $this->conn->prepare("SELECT instructor_id FROM courses WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $instructor_id = $stmt->get_result()->fetch_assoc()['instructor_id'];
            $stmt->close();

            if ($instructor_id !== $_SESSION['user_id']) {
                return ['success' => false, 'message' => 'Bạn không có quyền xóa bài tập này.'];
            }

            $stmt = $this->conn->prepare("DELETE FROM assignments WHERE assignment_id = ?");
            $stmt->bind_param("i", $assignment_id);
            $success = $stmt->execute();
            $stmt->close();
            return ['success' => $success, 'message' => $success ? 'Bài tập đã được xóa!' : 'Xóa thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }
    // Lấy danh sách bài tập theo khóa học
    public function getByCourse($course_id) {
        try {
            $stmt = $this->conn->prepare("SELECT assignment_id, lesson_id, title, description, due_date, max_score FROM assignments WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $assignments = [];
            while ($row = $result->fetch_assoc()) {
                $assignments[] = $row;
            }
            $stmt->close();
            return $assignments;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    
    
    public function getByLesson($lesson_id) {
        try {
            $stmt = $this->conn->prepare("SELECT assignment_id, course_id, title, description, due_date, max_score FROM assignments WHERE lesson_id = ?");
            $stmt->bind_param("i", $lesson_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $assignments = [];
            while ($row = $result->fetch_assoc()) {
                $assignments[] = $row;
            }
            $stmt->close();
            return $assignments;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }
}