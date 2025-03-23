<?php
class Category {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($name, $description = '') {
        try {
            if (strlen($name) > 100) {
                return ['success' => false, 'message' => 'Tên danh mục tối đa 100 ký tự.'];
            }
            if (strlen($description) > 65535) {
                return ['success' => false, 'message' => 'Mô tả quá dài.'];
            }

            $stmt = $this->conn->prepare("SELECT category_id FROM categories WHERE name = ?");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                return ['success' => false, 'message' => 'Tên danh mục đã tồn tại.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("INSERT INTO categories (name, description, status, created_at) VALUES (?, ?, 1, NOW())");
            $stmt->bind_param("ss", $name, $description);
            $success = $stmt->execute();
            $category_id = $this->conn->insert_id;
            $stmt->close();

            return ['success' => $success, 'category_id' => $category_id, 'message' => $success ? 'Danh mục đã được tạo!' : 'Tạo danh mục thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function update($category_id, $name, $description = '', $status = 1) {
        try {
            if (!$this->getById($category_id)) {
                return ['success' => false, 'message' => 'Danh mục không tồn tại.'];
            }
            if (strlen($name) > 100) {
                return ['success' => false, 'message' => 'Tên danh mục tối đa 100 ký tự.'];
            }
            if (strlen($description) > 65535) {
                return ['success' => false, 'message' => 'Mô tả quá dài.'];
            }

            $stmt = $this->conn->prepare("SELECT category_id FROM categories WHERE name = ? AND category_id != ?");
            $stmt->bind_param("si", $name, $category_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                return ['success' => false, 'message' => 'Tên danh mục đã tồn tại.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("UPDATE categories SET name = ?, description = ?, status = ? WHERE category_id = ?");
            $stmt->bind_param("ssii", $name, $description, $status, $category_id);
            $success = $stmt->execute();
            $stmt->close();

            return ['success' => $success, 'message' => $success ? 'Danh mục đã được cập nhật!' : 'Cập nhật thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function delete($category_id) {
        try {
            if (!$this->getById($category_id)) {
                return ['success' => false, 'message' => 'Danh mục không tồn tại.'];
            }

            $stmt = $this->conn->prepare("SELECT course_id FROM courses WHERE category_id = ? AND status = 1");
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                return ['success' => false, 'message' => 'Không thể xóa danh mục vì có khóa học hoạt động liên quan.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("DELETE FROM categories WHERE category_id = ?");
            $stmt->bind_param("i", $category_id);
            $success = $stmt->execute();
            $stmt->close();

            return ['success' => $success, 'message' => $success ? 'Danh mục đã được xóa!' : 'Xóa thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function getAll($limit = 10, $offset = 0) {
        try {
            $stmt = $this->conn->prepare("SELECT category_id, name, description, status, created_at FROM categories ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $categories = [];
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
            $stmt->close();
            return $categories;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    public function getActive($limit = 10, $offset = 0) {
        try {
            $stmt = $this->conn->prepare("SELECT category_id, name, description, created_at FROM categories WHERE status = 1 ORDER BY name ASC LIMIT ? OFFSET ?");
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $categories = [];
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
            $stmt->close();
            return $categories;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    public function getById($category_id) {
        try {
            $stmt = $this->conn->prepare("SELECT category_id, name, description, status, created_at FROM categories WHERE category_id = ?");
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $result ?: null;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }
}