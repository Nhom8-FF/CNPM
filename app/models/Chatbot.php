<?php
class Chatbot {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function saveChat($user_id, $question, $response, $language = 'vi') {
        try {
            if (empty($question) || strlen($question) > 65535 || strlen($response) > 65535 || !preg_match('/^[a-z]{2}$/', $language)) {
                return ['success' => false, 'message' => 'Dữ liệu không hợp lệ: câu hỏi/phản hồi tối đa 65535 ký tự, ngôn ngữ phải là mã ISO 639-1 (2 ký tự).'];
            }

            $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Người dùng không tồn tại.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("INSERT INTO chatbots (user_id, question, response, language, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("isss", $user_id, $question, $response, $language);
            $success = $stmt->execute();
            $chat_id = $this->conn->insert_id;
            $stmt->close();

            return ['success' => $success, 'chat_id' => $chat_id, 'message' => $success ? 'Cuộc trò chuyện đã được lưu!' : 'Lưu thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function getChatHistory($user_id, $limit = 10, $offset = 0) {
        try {
            $stmt = $this->conn->prepare("SELECT chat_id, question, response, language, created_at, is_helpful FROM chatbots WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("iii", $user_id, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $chats = [];
            while ($row = $result->fetch_assoc()) {
                $chats[] = $row;
            }
            $stmt->close();
            return $chats;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    public function updateFeedback($chat_id, $isHelpful, $user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT chat_id FROM chatbots WHERE chat_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $chat_id, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Cuộc trò chuyện không tồn tại hoặc không thuộc về bạn.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("UPDATE chatbots SET is_helpful = ? WHERE chat_id = ?");
            $isHelpful = (int)$isHelpful;
            $stmt->bind_param("ii", $isHelpful, $chat_id);
            $success = $stmt->execute();
            $stmt->close();

            return ['success' => $success, 'message' => $success ? 'Đánh giá đã được cập nhật!' : 'Cập nhật thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function getKnowledgeBase($limit = 100, $offset = 0) {
        try {
            $stmt = $this->conn->prepare("SELECT kb_id, keywords, answer FROM knowledge_base ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $knowledgeBase = [];
            while ($row = $result->fetch_assoc()) {
                $keywords = json_decode($row['keywords'], true);
                $knowledgeBase[] = ['kb_id' => $row['kb_id'], 'keywords' => $keywords, 'answer' => $row['answer']];
            }
            $stmt->close();
            return $knowledgeBase;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    public function updateKnowledgeBase($keywords, $answer) {
        try {
            if (empty($keywords) || !is_array($keywords) || empty($answer) || strlen($answer) > 65535) {
                return ['success' => false, 'message' => 'Từ khóa phải là mảng không rỗng, phản hồi tối đa 65535 ký tự.'];
            }
            $jsonKeywords = json_encode($keywords);
            $stmt = $this->conn->prepare("INSERT INTO knowledge_base (keywords, answer, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ss", $jsonKeywords, $answer);
            $success = $stmt->execute();
            $stmt->close();

            return ['success' => $success, 'message' => $success ? 'Cơ sở tri thức đã được cập nhật!' : 'Cập nhật thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function searchKnowledgeBase($query) {
        try {
            $normalizedQuery = $this->normalizeText($query);
            $knowledgeBase = $this->getKnowledgeBase();
            foreach ($knowledgeBase as $entry) {
                $keywords = array_map([$this, 'normalizeText'], $entry['keywords']);
                $matchCount = 0;
                foreach ($keywords as $keyword) {
                    if (strpos($normalizedQuery, $keyword) !== false) $matchCount++;
                }
                if ($matchCount >= count($keywords) * 0.7) {
                    return ['answer' => $entry['answer'], 'kb_id' => $entry['kb_id']];
                }
            }
            return null;
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    public function getStats($user_id = null) {
        try {
            $query = "SELECT COUNT(*) AS total_chats, AVG(is_helpful) AS helpful_rate FROM chatbots";
            $params = [];
            if ($user_id) {
                $query .= " WHERE user_id = ?";
                $params = [$user_id];
            }
            $stmt = $this->conn->prepare($query);
            if ($user_id) {
                $stmt->bind_param("i", $user_id);
            }
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $totalChats = $result['total_chats'] ?? 0;
            $helpfulRate = $result['helpful_rate'] !== null ? round($result['helpful_rate'] * 100, 2) : 0;

            return [
                'total_chats' => $totalChats,
                'helpful_rate' => $helpfulRate . '%',
                'message' => 'Thống kê đã được lấy!'
            ];
        } catch (Exception $e) {
            throw new Exception("Lỗi: " . $e->getMessage());
        }
    }

    public function deleteChat($chat_id, $user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT chat_id FROM chatbots WHERE chat_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $chat_id, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows == 0) {
                return ['success' => false, 'message' => 'Cuộc trò chuyện không tồn tại hoặc không thuộc về bạn.'];
            }
            $stmt->close();

            $stmt = $this->conn->prepare("DELETE FROM chatbots WHERE chat_id = ?");
            $stmt->bind_param("i", $chat_id);
            $success = $stmt->execute();
            $stmt->close();

            return ['success' => $success, 'message' => $success ? 'Cuộc trò chuyện đã được xóa!' : 'Xóa thất bại.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    public function normalizeText($text) {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[đ]/u', 'd', $text);
        $text = preg_replace('/[áàảãạăắằẳẵặâấầẩẫậ]/u', 'a', $text);
        $text = preg_replace('/[éèẻẽẹêếềểễệ]/u', 'e', $text);
        $text = preg_replace('/[íìỉĩị]/u', 'i', $text);
        $text = preg_replace('/[óòỏõọôốồổỗộơớờởỡợ]/u', 'o', $text);
        $text = preg_replace('/[úùủũụưứừửữự]/u', 'u', $text);
        $text = preg_replace('/[ýỳỷỹỵ]/u', 'y', $text);
        return $text;
    }
}