<?php
class QuizQuestion {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $score) {
        try {
            if (empty($question_text) || strlen($question_text) > 65535 || 
                (strlen($option_a) > 255 || strlen($option_b) > 255 || strlen($option_c) > 255 || strlen($option_d) > 255) ||
                !in_array($correct_option, ['A', 'B', 'C', 'D']) || $score < 0) {
                return ['success' => false, 'message' => 'Dữ liệu không hợp lệ: câu hỏi tối đa 65535 ký tự, lựa chọn tối đa 255 ký tự, đáp án đúng phải là A/B/C/D, điểm không âm.'];
            }

            $stmt = $this->conn->prepare("SELECT quiz_id FROM quizzes WHERE quiz_id = ?");
            $stmt->bind_param("i", $quiz_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Bài kiểm tra không tồn tại.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, score) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssi", $quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $score);
            $success = $stmt->execute();
            $question_id = $this->conn->insert_id;
            $stmt->close();
            return ['success' => $success, 'question_id' => $question_id, 'message' => $success ? 'Câu hỏi đã được thêm!' : 'Thêm thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function update($question_id, $quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $score) {
        try {
            $stmt = $this->conn->prepare("SELECT question_id FROM quiz_questions WHERE question_id = ? AND quiz_id = ?");
            $stmt->bind_param("ii", $question_id, $quiz_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Câu hỏi không tồn tại hoặc không thuộc bài kiểm tra này.'];
            }
            $stmt->close();

            if (empty($question_text) || strlen($question_text) > 65535 || 
                (strlen($option_a) > 255 || strlen($option_b) > 255 || strlen($option_c) > 255 || strlen($option_d) > 255) ||
                !in_array($correct_option, ['A', 'B', 'C', 'D']) || $score < 0) {
                return ['success' => false, 'message' => 'Dữ liệu không hợp lệ.'];
            }

            $stmt = $this->conn->prepare("UPDATE quiz_questions SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?, score = ? WHERE question_id = ? AND quiz_id = ?");
            $stmt->bind_param("sssssssii", $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $score, $question_id, $quiz_id);
            $success = $stmt->execute();
            $stmt->close();
            return ['success' => $success, 'message' => $success ? 'Câu hỏi đã được cập nhật!' : 'Cập nhật thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function delete($question_id, $quiz_id) {
        try {
            $stmt = $this->conn->prepare("SELECT question_id FROM quiz_questions WHERE question_id = ? AND quiz_id = ?");
            $stmt->bind_param("ii", $question_id, $quiz_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Câu hỏi không tồn tại hoặc không thuộc bài kiểm tra này.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("DELETE FROM quiz_questions WHERE question_id = ? AND quiz_id = ?");
            $stmt->bind_param("ii", $question_id, $quiz_id);
            $success = $stmt->execute();
            $stmt->close();
            return ['success' => $success, 'message' => $success ? 'Câu hỏi đã được xóa!' : 'Xóa thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function getByQuiz($quiz_id) {
        try {
            $stmt = $this->conn->prepare("SELECT quiz_id FROM quizzes WHERE quiz_id = ?");
            $stmt->bind_param("i", $quiz_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return [];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("SELECT question_id, question_text, option_a, option_b, option_c, option_d, correct_option, score FROM quiz_questions WHERE quiz_id = ? ORDER BY question_id ASC");
            $stmt->bind_param("i", $quiz_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $questions = [];
            while ($row = $result->fetch_assoc()) {
                $questions[] = $row;
            }
            $stmt->close();
            return $questions;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }
}