<?php
class Review {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Thêm đánh giá
    public function add($user_id, $course_id, $review_text, $rating) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO reviews (course_id, user_id, review_text, rating, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iisi", $course_id, $user_id, $review_text, $rating);
            $success = $stmt->execute();
            $stmt->close();
            return ['success' => $success, 'message' => $success ? 'Đánh giá đã được gửi!' : 'Gửi đánh giá thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    // Lấy danh sách đánh giá theo khóa học
    public function getByCourse($course_id) {
        try {
            $stmt = $this->conn->prepare("SELECT r.review_text, r.rating, u.username AS author 
                FROM reviews r 
                JOIN users u ON r.user_id = u.user_id 
                WHERE r.course_id = ? 
                ORDER BY r.created_at DESC");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $reviews = [];
            while ($row = $result->fetch_assoc()) {
                $reviews[] = $row;
            }
            $stmt->close();
            return $reviews;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    public function delete($review_id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM reviews WHERE review_id = ?");
            $stmt->bind_param("i", $review_id);
            $success = $stmt->execute();
            $stmt->close();
            $this->updateCourseRating($review_id); // Cập nhật rating sau khi xóa
            return ['success' => $success, 'message' => $success ? 'Đánh giá đã được xóa!' : 'Xóa đánh giá thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function approve($review_id, $status) {
        try {
            $valid_status = ['approved', 'rejected'];
            if (!in_array($status, $valid_status)) {
                return ['success' => false, 'message' => 'Trạng thái không hợp lệ.'];
            }
            $stmt = $this->conn->prepare("UPDATE reviews SET status = ? WHERE review_id = ?");
            $stmt->bind_param("si", $status, $review_id);
            $success = $stmt->execute();
            $stmt->close();
            if ($success && $status === 'approved') {
                $this->updateCourseRating($review_id); // Cập nhật rating khi phê duyệt
            }
            return ['success' => $success, 'message' => $success ? "Đánh giá đã được $status!" : "Phê duyệt thất bại."];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function getPending() {
        try {
            $stmt = $this->conn->prepare("SELECT review_id, review_text, rating, user_id, course_id FROM reviews WHERE status = 'pending'");
            $stmt->execute();
            $result = $stmt->get_result();
            $reviews = [];
            while ($row = $result->fetch_assoc()) {
                $reviews[] = $row;
            }
            $stmt->close();
            return $reviews;
        } catch (Exception $e) {                                                            
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    private function updateCourseRating($review_id) {
        try {
            $stmt = $this->conn->prepare("SELECT course_id FROM reviews WHERE review_id = ?");
            $stmt->bind_param("i", $review_id);
            $stmt->execute();
            $course_id = $stmt->get_result()->fetch_assoc()['course_id'] ?? null;
            $stmt->close();

            if ($course_id) {
                $stmt = $this->conn->prepare("UPDATE courses SET rating = (SELECT AVG(rating) FROM reviews WHERE course_id = ? AND status = 'approved') WHERE course_id = ?");
                $stmt->bind_param("ii", $course_id, $course_id);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Lỗi cập nhật rating khóa học: " . $e->getMessage());
        }
    }

}