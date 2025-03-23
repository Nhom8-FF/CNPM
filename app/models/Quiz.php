<?php
class Quiz {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($course_id, $lesson_id, $title, $time_limit, $total_questions) {
        try {
            if (empty($title) || strlen($title) > 200 || $time_limit < 0 || $total_questions < 0) {
                return ['success' => false, 'message' => 'Dữ liệu không hợp lệ: tiêu đề tối đa 200 ký tự, thời gian và số câu hỏi không âm.'];
            }

            $stmt = $this->conn->prepare("SELECT course_id FROM courses WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Khóa học không tồn tại.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("SELECT lesson_id FROM lessons WHERE lesson_id = ? AND course_id = ?");
            $stmt->bind_param("ii", $lesson_id, $course_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Bài giảng không tồn tại hoặc không thuộc khóa học này.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("INSERT INTO quizzes (course_id, lesson_id, title, time_limit, total_questions) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisii", $course_id, $lesson_id, $title, $time_limit, $total_questions);
            $success = $stmt->execute();
            $quiz_id = $this->conn->insert_id;
            $stmt->close();
            return ['success' => $success, 'quiz_id' => $quiz_id, 'message' => $success ? 'Bài kiểm tra đã được thêm!' : 'Thêm thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function update($quiz_id, $course_id, $title, $time_limit, $total_questions) {
        try {
            $stmt = $this->conn->prepare("SELECT quiz_id FROM quizzes WHERE quiz_id = ? AND course_id = ?");
            $stmt->bind_param("ii", $quiz_id, $course_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Bài kiểm tra không tồn tại hoặc không thuộc khóa học này.'];
            }
            $stmt->close();

            if (empty($title) || strlen($title) > 200 || $time_limit < 0 || $total_questions < 0) {
                return ['success' => false, 'message' => 'Dữ liệu không hợp lệ.'];
            }

            $stmt = $this->conn->prepare("UPDATE quizzes SET title = ?, time_limit = ?, total_questions = ? WHERE quiz_id = ? AND course_id = ?");
            $stmt->bind_param("siiii", $title, $time_limit, $total_questions, $quiz_id, $course_id);
            $success = $stmt->execute();
            $stmt->close();
            return ['success' => $success, 'message' => $success ? 'Bài kiểm tra đã được cập nhật!' : 'Cập nhật thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function delete($quiz_id, $course_id) {
        try {
            $stmt = $this->conn->prepare("SELECT quiz_id FROM quizzes WHERE quiz_id = ? AND course_id = ?");
            $stmt->bind_param("ii", $quiz_id, $course_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Bài kiểm tra không tồn tại hoặc không thuộc khóa học này.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("DELETE FROM quizzes WHERE quiz_id = ? AND course_id = ?");
            $stmt->bind_param("ii", $quiz_id, $course_id);
            $success = $stmt->execute();
            $stmt->close();
            return ['success' => $success, 'message' => $success ? 'Bài kiểm tra đã được xóa!' : 'Xóa thất bại.'];
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

            $stmt = $this->conn->prepare("SELECT quiz_id, lesson_id, title, time_limit, total_questions FROM quizzes WHERE course_id = ? ORDER BY quiz_id ASC");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $quizzes = [];
            while ($row = $result->fetch_assoc()) {
                $quizzes[] = $row;
            }
            $stmt->close();
            return $quizzes;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    public function getByLesson($lesson_id) {
        try {
            $stmt = $this->conn->prepare("SELECT lesson_id FROM lessons WHERE lesson_id = ?");
            $stmt->bind_param("i", $lesson_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return [];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("SELECT quiz_id, course_id, title, time_limit, total_questions FROM quizzes WHERE lesson_id = ? ORDER BY quiz_id ASC");
            $stmt->bind_param("i", $lesson_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $quizzes = [];
            while ($row = $result->fetch_assoc()) {
                $quizzes[] = $row;
            }
            $stmt->close();
            return $quizzes;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }
}