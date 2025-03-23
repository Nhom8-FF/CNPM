<?php
// Don't redefine constants if already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/WebCourses/');
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3));
}

// Include database connection
require_once ROOT_DIR . '/app/config/connect.php';

/**
 * API Controller for Lesson Files
 */
class LessonFilesAPI {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Get all files associated with a lesson
     * 
     * @param int $lesson_id The ID of the lesson
     * @return array The API response
     */
    public function getFiles($lesson_id) {
        // Validate lesson_id
        if (!$lesson_id || !is_numeric($lesson_id)) {
            return [
                'success' => false,
                'message' => 'Invalid lesson ID'
            ];
        }
        
        // Get lesson files
        $stmt = $this->conn->prepare("
            SELECT document_file, video_file, presentation_file, additional_files 
            FROM lessons 
            WHERE lesson_id = ?
        ");
        
        $stmt->bind_param("i", $lesson_id);
        
        if (!$stmt->execute()) {
            error_log("Failed to fetch lesson files: " . $stmt->error);
            return [
                'success' => false,
                'message' => 'Failed to fetch lesson files'
            ];
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'message' => 'Lesson not found'
            ];
        }
        
        $lesson = $result->fetch_assoc();
        $files = [];
        
        // Process document file
        if (!empty($lesson['document_file'])) {
            $files[] = [
                'type' => 'document',
                'name' => basename($lesson['document_file']),
                'url' => BASE_URL . $lesson['document_file'],
                'extension' => pathinfo($lesson['document_file'], PATHINFO_EXTENSION)
            ];
        }
        
        // Process video file
        if (!empty($lesson['video_file'])) {
            $files[] = [
                'type' => 'video',
                'name' => basename($lesson['video_file']),
                'url' => BASE_URL . $lesson['video_file'],
                'extension' => pathinfo($lesson['video_file'], PATHINFO_EXTENSION)
            ];
        }
        
        // Process presentation file
        if (!empty($lesson['presentation_file'])) {
            $files[] = [
                'type' => 'presentation',
                'name' => basename($lesson['presentation_file']),
                'url' => BASE_URL . $lesson['presentation_file'],
                'extension' => pathinfo($lesson['presentation_file'], PATHINFO_EXTENSION)
            ];
        }
        
        // Process additional files
        if (!empty($lesson['additional_files'])) {
            $additionalFiles = json_decode($lesson['additional_files'], true);
            
            if (is_array($additionalFiles)) {
                foreach ($additionalFiles as $index => $file) {
                    $files[] = [
                        'type' => 'additional',
                        'index' => $index,
                        'name' => basename($file),
                        'url' => BASE_URL . $file,
                        'extension' => pathinfo($file, PATHINFO_EXTENSION)
                    ];
                }
            }
        }
        
        return [
            'success' => true,
            'files' => $files
        ];
    }

    private function callGeminiWithImage($prompt, $imageData, $googleApiKey) {
        if (empty($googleApiKey)) {
            error_log("Missing Google API key");
            return null;
        }
        
        try {
            $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=$googleApiKey";
            $parts = [['text' => $prompt]];
            
            if ($imageData) {
                // Properly format the image data according to Gemini API requirements
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $imageData['mime_type'],
                        'data' => $imageData['data']
                    ]
                ];
            }
            
            $postData = ['contents' => [['parts' => $parts]]];
            
            // Debug the request payload size
            error_log("Gemini request payload size: " . strlen(json_encode($postData)) . " bytes");
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, 
                CURLOPT_POST => true, 
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_TIMEOUT => 60
            ]);
            
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($response === false) {
                error_log("Curl error in callGeminiWithImage: " . $error);
                curl_close($ch);
                return null;
            }
            
            curl_close($ch);
            
            error_log("Gemini API response code: $httpCode");
            if ($httpCode != 200) {
                error_log("Gemini API error: HTTP $httpCode, Response: " . substr($response, 0, 500));
                return null;
            }
            
            $result = json_decode($response, true);
            return $result;
        } catch (Exception $e) {
            error_log("Exception in callGeminiWithImage: " . $e->getMessage());
            return null;
        }
    }

    private function resizeImage($filepath, $mime_type, $max_dimension) {
        try {
            list($width, $height) = getimagesize($filepath);
            
            if ($width <= $max_dimension && $height <= $max_dimension) {
                return file_get_contents($filepath);
            }
            
            // Calculate new dimensions
            if ($width > $height) {
                $new_width = $max_dimension;
                $new_height = floor($height * ($max_dimension / $width));
            } else {
                $new_height = $max_dimension;
                $new_width = floor($width * ($max_dimension / $height));
            }
            
            // Create new image
            $source = null;
            switch ($mime_type) {
                case 'image/jpeg':
                    $source = imagecreatefromjpeg($filepath);
                    break;
                case 'image/png':
                    $source = imagecreatefrompng($filepath);
                    break;
                default:
                    return null;
            }
            
            if (!$source) {
                return null;
            }
            
            $destination = imagecreatetruecolor($new_width, $new_height);
            
            // Preserve transparency for PNG
            if ($mime_type === 'image/png') {
                imagealphablending($destination, false);
                imagesavealpha($destination, true);
                $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
                imagefilledrectangle($destination, 0, 0, $new_width, $new_height, $transparent);
            }
            
            // Resize
            imagecopyresampled($destination, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            
            // Output to buffer
            ob_start();
            if ($mime_type === 'image/jpeg') {
                imagejpeg($destination, null, 85);
            } else {
                imagepng($destination, null, 6);
            }
            $image_data = ob_get_contents();
            ob_end_clean();
            
            // Free memory
            imagedestroy($source);
            imagedestroy($destination);
            
            return $image_data;
        } catch (Exception $e) {
            error_log("Error resizing image: " . $e->getMessage());
            return null;
        }
    }
} 