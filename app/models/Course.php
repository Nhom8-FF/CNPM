<?php
class Course {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($instructor_id, $title, $description, $category_id, $price, $image, $level, $video = '', $duration = '') {
        try {
            if (empty($title) || strlen($title) > 100 || strlen($description) > 65535 || $price < 0) {
                return ['success' => false, 'message' => 'Dữ liệu không hợp lệ: tiêu đề tối đa 100 ký tự, mô tả tối đa 65535 ký tự, giá không âm.'];
            }

            $stmt = $this->conn->prepare("SELECT category_id FROM categories WHERE category_id = ?");
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Danh mục không tồn tại.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("INSERT INTO courses (instructor_id, category_id, title, description, video, duration, price, image, level, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iissssds", $instructor_id, $category_id, $title, $description, $video, $duration, $price, $image, $level);
            $success = $stmt->execute();
            $course_id = $this->conn->insert_id;
            $stmt->close();
            return ['success' => $success, 'course_id' => $course_id, 'message' => $success ? 'Khóa học đã được tạo!' : 'Tạo khóa học thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function update($course_id, $instructor_id, $title, $description, $category_id, $price, $image, $level, $video = '', $duration = '', $status = 1) {
        try {
            if (!$this->getById($course_id)) {
                return ['success' => false, 'message' => 'Khóa học không tồn tại.'];
            }
            if (empty($title) || strlen($title) > 100 || strlen($description) > 65535 || $price < 0) {
                return ['success' => false, 'message' => 'Dữ liệu không hợp lệ.'];
            }

            $stmt = $this->conn->prepare("UPDATE courses SET title = ?, description = ?, category_id = ?, price = ?, image = ?, level = ?, video = ?, duration = ?, status = ? WHERE course_id = ? AND instructor_id = ?");
            $stmt->bind_param("ssisdsssiii", $title, $description, $category_id, $price, $image, $level, $video, $duration, $status, $course_id, $instructor_id);
            $success = $stmt->execute();
            $stmt->close();
            return ['success' => $success, 'message' => $success ? 'Khóa học đã được cập nhật!' : 'Cập nhật thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function delete($course_id, $instructor_id) {
        try {
            if (!$this->getById($course_id)) {
                return ['success' => false, 'message' => 'Khóa học không tồn tại.'];
            }

            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            if ($stmt->get_result()->fetch_row()[0] > 0) {
                return ['success' => false, 'message' => 'Không thể xóa khóa học vì đã có học viên đăng ký.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("DELETE FROM courses WHERE course_id = ? AND instructor_id = ?");
            $stmt->bind_param("ii", $course_id, $instructor_id);
            $success = $stmt->execute();
            $stmt->close();
            return ['success' => $success, 'message' => $success ? 'Khóa học đã được xóa!' : 'Xóa thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function getByInstructor($instructor_id) {
        try {
            $stmt = $this->conn->prepare("SELECT course_id, title, description, image, price, level, video, duration, rating FROM courses WHERE instructor_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $instructor_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $courses = [];
            while ($row = $result->fetch_assoc()) {
                $courses[] = $row;
            }
            $stmt->close();
            return $courses;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    public function getById($course_id) {
        try {
            $stmt = $this->conn->prepare("SELECT course_id, title, description, category_id, price, image, level, video, duration, instructor_id, rating, students FROM courses WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $result ?: null;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    public function getAll($category_id = null) {
        try {
            $query = "SELECT course_id, title, description, image, price, level, video, duration, rating FROM courses WHERE status = 1";
            if ($category_id) {
                $query .= " AND category_id = ?";
            }
            $query .= " ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            if ($category_id) {
                $stmt->bind_param("i", $category_id);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $courses = [];
            while ($row = $result->fetch_assoc()) {
                $courses[] = $row;
            }
            $stmt->close();
            return $courses;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    public function updateRating($course_id) {
        try {
            $stmt = $this->conn->prepare("UPDATE courses SET rating = (SELECT AVG(rating) FROM reviews WHERE course_id = ? AND status = 'approved') WHERE course_id = ?");
            $stmt->bind_param("ii", $course_id, $course_id);
            $success = $stmt->execute();
            $stmt->close();
            return ['success' => $success, 'message' => $success ? 'Rating đã được cập nhật!' : 'Cập nhật rating thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }
}