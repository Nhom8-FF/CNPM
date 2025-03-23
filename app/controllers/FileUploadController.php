<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}

class FileUploadController {
    private $db;
    private $allowed_document_types = ['pdf', 'doc', 'docx', 'txt', 'odt'];
    private $allowed_video_types = ['mp4', 'webm', 'avi', 'mov', 'wmv'];
    private $allowed_presentation_types = ['ppt', 'pptx', 'odp', 'key'];
    private $allowed_additional_types = ['zip', 'rar', 'png', 'jpg', 'jpeg', 'gif', 'xlsx', 'xls', 'csv'];
    private $max_file_size = 52428800; // 50MB in bytes

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Handle file upload for a specific lecture and file type
     *
     * @param array $file The $_FILES array element for the uploaded file
     * @param int $lesson_id The ID of the lesson to attach the file to
     * @param string $file_type The type of file ('document', 'video', 'presentation', 'additional')
     * @return array Response with success status and message
     */
    public function uploadFile($file, $lesson_id, $file_type) {
        // Check if file is present
        if (!isset($file) || $file['error'] == UPLOAD_ERR_NO_FILE) {
            return [
                'success' => false,
                'message' => 'Không có tệp nào được chọn.'
            ];
        }

        // Check for errors
        if ($file['error'] != UPLOAD_ERR_OK) {
            $error_message = $this->getUploadErrorMessage($file['error']);
            return [
                'success' => false,
                'message' => 'Lỗi tải lên: ' . $error_message
            ];
        }

        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return [
                'success' => false,
                'message' => 'Tệp quá lớn. Kích thước tối đa là 50MB.'
            ];
        }

        // Get file extension
        $file_name = $file['name'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Validate file type
        $allowed_types = [];
        $db_column = '';
        switch ($file_type) {
            case 'document':
                $allowed_types = $this->allowed_document_types;
                $db_column = 'document_file';
                break;
            case 'video':
                $allowed_types = $this->allowed_video_types;
                $db_column = 'video_file';
                break;
            case 'presentation':
                $allowed_types = $this->allowed_presentation_types;
                $db_column = 'presentation_file';
                break;
            case 'additional':
                $allowed_types = $this->allowed_additional_types;
                $db_column = 'additional_files';
                break;
            default:
                return [
                    'success' => false,
                    'message' => 'Loại tệp không hợp lệ.'
                ];
        }

        if (!in_array($file_extension, $allowed_types)) {
            return [
                'success' => false,
                'message' => 'Loại tệp không được hỗ trợ. Các loại tệp được phép: ' . implode(', ', $allowed_types)
            ];
        }

        // Generate unique filename
        $new_filename = 'lesson_' . $lesson_id . '_' . $file_type . '_' . time() . '.' . $file_extension;
        $upload_path = ROOT_DIR . '/public/uploads/lessons/';
        $file_path = $upload_path . $new_filename;

        // Ensure the upload directory exists
        if (!is_dir($upload_path)) {
            if (!mkdir($upload_path, 0755, true)) {
                return [
                    'success' => false,
                    'message' => 'Không thể tạo thư mục tải lên.'
                ];
            }
        }

        // Move the file to the destination
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            return [
                'success' => false,
                'message' => 'Có lỗi khi lưu tệp. Vui lòng thử lại sau.'
            ];
        }

        // Store path in database
        $relative_path = 'public/uploads/lessons/' . $new_filename;
        
        // Different handling for additional files (can have multiple)
        if ($file_type == 'additional') {
            // Get current additional files
            $stmt = $this->db->prepare("SELECT additional_files FROM lessons WHERE lesson_id = ?");
            $stmt->bind_param("i", $lesson_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $lesson = $result->fetch_assoc();
            
            $current_files = $lesson['additional_files'] ? json_decode($lesson['additional_files'], true) : [];
            
            // Add new file
            $current_files[] = [
                'path' => $relative_path,
                'name' => $file_name,
                'type' => $file_extension
            ];
            
            $files_json = json_encode($current_files);
            
            $stmt = $this->db->prepare("UPDATE lessons SET additional_files = ? WHERE lesson_id = ?");
            $stmt->bind_param("si", $files_json, $lesson_id);
        } else {
            // Standard handling for single file types
            $stmt = $this->db->prepare("UPDATE lessons SET $db_column = ? WHERE lesson_id = ?");
            $stmt->bind_param("si", $relative_path, $lesson_id);
        }
        
        if (!$stmt->execute()) {
            // If database update fails, remove the uploaded file
            @unlink($file_path);
            return [
                'success' => false,
                'message' => 'Lỗi cập nhật cơ sở dữ liệu: ' . $stmt->error
            ];
        }

        return [
            'success' => true,
            'message' => 'Tệp đã được tải lên thành công.',
            'file_path' => $relative_path
        ];
    }

    /**
     * Remove a file from a lesson
     *
     * @param int $lesson_id The ID of the lesson
     * @param string $file_type The type of file to remove
     * @param int $file_index For additional files, the index of the file to remove
     * @return array Response with success status and message
     */
    public function removeFile($lesson_id, $file_type, $file_index = null) {
        // Determine database column
        $db_column = '';
        switch ($file_type) {
            case 'document':
                $db_column = 'document_file';
                break;
            case 'video':
                $db_column = 'video_file';
                break;
            case 'presentation':
                $db_column = 'presentation_file';
                break;
            case 'additional':
                $db_column = 'additional_files';
                break;
            default:
                return [
                    'success' => false,
                    'message' => 'Loại tệp không hợp lệ.'
                ];
        }

        // Get current file information
        $stmt = $this->db->prepare("SELECT $db_column FROM lessons WHERE lesson_id = ?");
        $stmt->bind_param("i", $lesson_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $lesson = $result->fetch_assoc();

        if ($file_type == 'additional' && $file_index !== null) {
            // Handle removal of a specific additional file
            $files = json_decode($lesson[$db_column], true);
            
            if (!isset($files[$file_index])) {
                return [
                    'success' => false,
                    'message' => 'Tệp không tồn tại.'
                ];
            }
            
            $file_path = ROOT_DIR . '/' . $files[$file_index]['path'];
            
            // Delete the file
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
            
            // Remove from the array
            array_splice($files, $file_index, 1);
            
            // Update database
            $files_json = !empty($files) ? json_encode($files) : null;
            $stmt = $this->db->prepare("UPDATE lessons SET $db_column = ? WHERE lesson_id = ?");
            $stmt->bind_param("si", $files_json, $lesson_id);
        } else {
            // Handle single file types
            $file_path = ROOT_DIR . '/' . $lesson[$db_column];
            
            // Delete the file
            if (!empty($lesson[$db_column]) && file_exists($file_path)) {
                @unlink($file_path);
            }
            
            // Update database with NULL
            $null_value = null;
            $stmt = $this->db->prepare("UPDATE lessons SET $db_column = ? WHERE lesson_id = ?");
            $stmt->bind_param("si", $null_value, $lesson_id);
        }
        
        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Lỗi cập nhật cơ sở dữ liệu: ' . $stmt->error
            ];
        }

        return [
            'success' => true,
            'message' => 'Tệp đã được xóa thành công.'
        ];
    }

    /**
     * Get an error message for an upload error code
     *
     * @param int $error_code The upload error code
     * @return string Human-readable error message
     */
    private function getUploadErrorMessage($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'Tệp vượt quá kích thước tối đa được định nghĩa trong php.ini.';
            case UPLOAD_ERR_FORM_SIZE:
                return 'Tệp vượt quá kích thước tối đa được định nghĩa trong form HTML.';
            case UPLOAD_ERR_PARTIAL:
                return 'Tệp chỉ được tải lên một phần.';
            case UPLOAD_ERR_NO_FILE:
                return 'Không có tệp nào được tải lên.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Thư mục tạm thời không được tìm thấy.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Không thể ghi tệp vào đĩa.';
            case UPLOAD_ERR_EXTENSION:
                return 'Tải lên bị dừng bởi một extension của PHP.';
            default:
                return 'Lỗi không xác định.';
        }
    }
} 