<?php
class Comment {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($discussion_id, $user_id, $content) {
        try {
            if (empty($content) || strlen($content) > 65535) {
                return ['success' => false, 'message' => 'Nội dung không được để trống và tối đa 65535 ký tự.'];
            }

            $stmt = $this->conn->prepare("SELECT discussion_id FROM discussion WHERE discussion_id = ?");
            $stmt->bind_param("i", $discussion_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Thảo luận không tồn tại.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Người dùng không tồn tại.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("INSERT INTO comments (discussion_id, user_id, content, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
            $stmt->bind_param("iis", $discussion_id, $user_id, $content);
            $success = $stmt->execute();
            $comment_id = $this->conn->insert_id;
            $stmt->close();
            return ['success' => $success, 'comment_id' => $comment_id, 'message' => $success ? 'Bình luận đã được thêm và đang chờ phê duyệt!' : 'Thêm thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function getByDiscussion($discussion_id) {
        try {
            $stmt = $this->conn->prepare("SELECT c.comment_id, c.content, u.username AS author, c.created_at 
                FROM comments c 
                JOIN users u ON c.user_id = u.user_id 
                WHERE c.discussion_id = ? AND c.status = 'approved' 
                ORDER BY c.created_at ASC");
            $stmt->bind_param("i", $discussion_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $comments = [];
            while ($row = $result->fetch_assoc()) {
                $comments[] = $row;
            }
            $stmt->close();
            return $comments;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    public function delete($comment_id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM comments WHERE comment_id = ?");
            $stmt->bind_param("i", $comment_id);
            $success = $stmt->execute();
            $stmt->close();
            return ['success' => $success, 'message' => $success ? 'Bình luận đã được xóa!' : 'Xóa bình luận thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function approve($comment_id, $status) {
        try {
            $valid_status = ['approved', 'rejected'];
            if (!in_array($status, $valid_status)) {
                return ['success' => false, 'message' => 'Trạng thái không hợp lệ.'];
            }

            $stmt = $this->conn->prepare("SELECT comment_id FROM comments WHERE comment_id = ?");
            $stmt->bind_param("i", $comment_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Bình luận không tồn tại.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("UPDATE comments SET status = ? WHERE comment_id = ?");
            $stmt->bind_param("si", $status, $comment_id);
            $success = $stmt->execute();
            $stmt->close();

            return ['success' => $success, 'message' => $success ? "Bình luận đã được $status!" : "Phê duyệt thất bại."];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function getPending($limit = 10, $offset = 0) {
        try {
            $stmt = $this->conn->prepare("SELECT comment_id, content, user_id, discussion_id FROM comments WHERE status = 'pending' ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $comments = [];
            while ($row = $result->fetch_assoc()) {
                $comments[] = $row;
            }
            $stmt->close();
            return $comments;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    public function getByDiscussionIds(array $discussion_ids) {
        try {
            if (empty($discussion_ids)) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($discussion_ids), '?'));
            $stmt = $this->conn->prepare("SELECT c.comment_id, c.discussion_id, c.content, u.username AS author, c.created_at 
                FROM comments c 
                JOIN users u ON c.user_id = u.user_id 
                WHERE c.discussion_id IN ($placeholders) AND c.status = 'approved' 
                ORDER BY c.created_at ASC");
            $stmt->bind_param(str_repeat('i', count($discussion_ids)), ...$discussion_ids);
            $stmt->execute();
            $result = $stmt->get_result();
            $comments = [];
            while ($row = $result->fetch_assoc()) {
                $comments[] = $row;
            }
            $stmt->close();
            return $comments;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }
    
}