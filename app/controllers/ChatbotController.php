<?php
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}
require_once ROOT_DIR . '/app/models/Chatbot.php';

class ChatbotController {
    private $conn;
    private $model;

    public function __construct($db) {
        $this->conn = $db;
        $this->model = new Chatbot($db);
    }

    public function processChat() {
        // Buffer output from the very beginning to ensure clean JSON response
        ob_start();
        
        // Enable more detailed error reporting and logging
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        error_log("ChatbotController::processChat started");
        
        // Check for output buffering and headers
        $buffer = ob_get_contents();
        if (!empty($buffer)) {
            error_log("Warning: Output found before headers sent: " . substr($buffer, 0, 100));
            ob_clean();
        }
        
        if (headers_sent($filename, $linenum)) {
            error_log("Warning: Headers already sent in $filename on line $linenum");
        }
        
        // Restart session handling to ensure clean state
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_start();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error_log("Method not POST: " . $_SERVER['REQUEST_METHOD']);
            $this->sendErrorResponse('Phương thức không hợp lệ.');
            return;
        }
        
        if (!isset($_POST['prompt'])) {
            error_log("Missing prompt in POST data");
            $this->sendErrorResponse('Thiếu nội dung câu hỏi.');
            return;
        }
        
        if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
            error_log("Invalid CSRF token");
            $this->sendErrorResponse('Token không hợp lệ.');
            return;
        }

        // Lấy API keys từ file cấu hình
        try {
            $configPath = ROOT_DIR . '/app/config/api_config.php';
            if (!file_exists($configPath)) {
                error_log("API config file not found at: $configPath");
                $config = $this->getDefaultConfig();
            } else {
                error_log("Loading API config from: $configPath");
                $config = require $configPath;
                error_log("API config loaded: " . json_encode(array_keys($config)));
            }
            
            $googleApiKey = $config['google_api_key'] ?? '';
            $clarifaiApiKey = $config['clarifai_api_key'] ?? '';
            $openaiApiKey = $config['openai_api_key'] ?? '';
            
            // Log key presence but not the actual keys
            error_log("API keys loaded: " . 
                "Google API key: " . (!empty($googleApiKey) ? "Present" : "Missing") . ", " .
                "Clarifai API key: " . (!empty($clarifaiApiKey) ? "Present" : "Missing") . ", " .
                "OpenAI API key: " . (!empty($openaiApiKey) ? "Present" : "Missing")
            );
        } catch (Exception $e) {
            error_log("Error loading API configs: " . $e->getMessage());
            $config = $this->getDefaultConfig();
            $googleApiKey = $config['google_api_key'] ?? '';
            $clarifaiApiKey = $config['clarifai_api_key'] ?? '';
            $openaiApiKey = $config['openai_api_key'] ?? '';
        }

        $langInstructions = [
            'vi' => "Hãy trả lời bằng tiếng Việt.",
            'en' => "Please reply in English.",
            'ja' => "日本語で回答してください。",
            'fr' => "Veuillez répondre en français.",
            'zh' => "请用中文回答。",
            'ko' => "한국어로 답변해 주세요。",
            'es' => "Por favor responde en español.",
        ];

        $prompt = trim($_POST['prompt'] ?? '');
        if (empty($prompt)) {
            $this->sendErrorResponse('Câu hỏi không được để trống.');
            return;
        }
        
        if (strlen($prompt) > 65535) {
            $this->sendErrorResponse('Câu hỏi quá dài (tối đa 65535 ký tự).');
            return;
        }

        $parsedText = '';
        $imageForGemini = null;

        // Xử lý file tải lên
        try {
            if (!empty($_FILES['files']['name'][0])) {
                $allowedTypes = ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/pdf', 'image/jpeg', 'image/png'];
                $maxFileSize = 5 * 1024 * 1024; // 5MB
                
                for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
                    if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK && $_FILES['files']['size'][$i] <= $maxFileSize) {
                        $tmpName = $_FILES['files']['tmp_name'][$i];
                        $fileName = $_FILES['files']['name'][$i];
                        
                        if (!file_exists($tmpName)) {
                            error_log("File does not exist: $tmpName");
                            continue;
                        }
                        
                        $fileType = mime_content_type($tmpName);
                        error_log("Processing file: $fileName, type: $fileType");
                        
                        if (!in_array($fileType, $allowedTypes)) {
                            error_log("File type not allowed: $fileType");
                            continue;
                        }

                        if ($fileType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                            $parsedText .= "\n[DOCX: $fileName] " . $this->parseDocx($tmpName);
                        } elseif ($fileType === 'application/pdf') {
                            $parsedText .= "\n[PDF: $fileName] " . $this->parsePdf($tmpName);
                        } elseif (strpos($fileType, 'image/') === 0) {
                            $base64 = base64_encode(file_get_contents($tmpName));
                            $imageData = ['mime_type' => $fileType, 'data' => $base64];
                            
                            if (!$imageForGemini) {
                                $imageForGemini = $imageData; // Chỉ lấy hình ảnh đầu tiên
                            }
                            
                            $clarifaiResult = $this->callClarifai($imageData, $clarifaiApiKey);
                            if (!empty($clarifaiResult['outputs'][0]['data']['concepts'])) {
                                $concepts = array_map(fn($c) => $c['name'], $clarifaiResult['outputs'][0]['data']['concepts']);
                                $parsedText .= "\n[Image: $fileName] Objects detected: " . implode(", ", $concepts);
                            } else {
                                $parsedText .= "\n[Image: $fileName] Image processed successfully.";
                            }
                        }
                    } else {
                        error_log("File error: " . $_FILES['files']['error'][$i] . " or size too large: " . $_FILES['files']['size'][$i]);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error processing files: " . $e->getMessage());
            // Continue without file data
        }

        try {
            // Tìm kiếm trong cơ sở kiến thức
            $normalizedPrompt = $this->model->normalizeText($prompt);
            $knowledgeBaseResult = $this->model->searchKnowledgeBase($normalizedPrompt);
            
            if ($knowledgeBaseResult) {
                error_log("Found in knowledge base: " . substr($knowledgeBaseResult['answer'], 0, 100) . "...");
                $reply = $knowledgeBaseResult['answer'];
            } else {
                // Try to detect language (simplified logic)
                $chosenLang = 'vi'; // Default to Vietnamese
                if (preg_match('/[a-zA-Z]/', $prompt)) {
                    // If latin characters are present, try English
                    if (str_word_count($prompt) > (strlen($prompt) / 10)) {
                        $chosenLang = 'en';
                    }
                }
                
                $instruction = $langInstructions[$chosenLang];
                $finalPrompt = "$instruction\n$prompt\n$parsedText";
                
                // Try Gemini API
                $reply = null;
                $geminiResponse = null;
                
                try {
                    error_log("Calling Gemini API");
                    $geminiResponse = $this->callGeminiWithImage($finalPrompt, $imageForGemini, $googleApiKey);
                    
                    if ($geminiResponse && isset($geminiResponse['candidates'][0]['content']['parts'][0]['text'])) {
                        $reply = $geminiResponse['candidates'][0]['content']['parts'][0]['text'];
                        error_log("Gemini API returned: " . substr($reply, 0, 100) . "...");
                    } else {
                        error_log("Gemini API failed to return valid response");
                    }
                } catch (Exception $e) {
                    error_log("Error calling Gemini API: " . $e->getMessage());
                }
                
                // If Gemini API failed, use hardcoded fallback response
                if (!$reply) {
                    error_log("Using hardcoded fallback response");
                    $reply = $this->getFallbackResponse($prompt, $chosenLang);
                }
            }

            // Lưu cuộc hội thoại nếu người dùng đã đăng nhập
            if (isset($_SESSION['user_id'])) {
                try {
                    $this->model->saveChat($_SESSION['user_id'], $prompt, $reply, $chosenLang ?? 'vi');
                } catch (Exception $e) {
                    error_log("Error saving chat: " . $e->getMessage());
                    // Continue even if saving fails
                }
            }

            $this->sendSuccessResponse($reply);
            
        } catch (Exception $e) {
            error_log("Fatal error in processChat: " . $e->getMessage());
            $this->sendErrorResponse('Lỗi xử lý: ' . $e->getMessage());
        }
    }
    
    private function sendSuccessResponse($reply) {
        header('Content-Type: application/json');
        // Debug info
        error_log("[DEBUG] Sending success response: " . substr($reply, 0, 100));
        
        // Clean output buffer to ensure no previous output interferes with JSON
        if (ob_get_level()) ob_end_clean();
        
        // Make sure we're sending a proper JSON response
        $response = ['status' => 'success', 'reply' => nl2br(htmlspecialchars($reply))];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    private function sendErrorResponse($message) {
        header('Content-Type: application/json');
        // Debug info
        error_log("[DEBUG] Sending error response: " . $message);
        
        // Clean output buffer to ensure no previous output interferes with JSON
        if (ob_get_level()) ob_end_clean();
        
        // Make sure we're sending a proper JSON response
        echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    /**
     * Get default API config 
     */
    private function getDefaultConfig() {
        return [
            'google_api_key' => getenv('AIzaSyCGq0dnZduvms3Uhg3jZ6nicSWM0s_4rNA') ?: '',
            'clarifai_api_key' => getenv('115ff8c3a2094c7a928b3e3e8dbc7a78') ?: '',
            'models' => [
                'gemini' => [
                    'default' => 'gemini-2.0-flash'
                ]
            ],
            'settings' => [
                'max_tokens' => 1024,
                'temperature' => 0.7,
                'timeout' => 30
            ]
        ];
    }
    
    /**
     * Helper to get fallback response
     */
    private function getFallbackResponse($prompt, $lang = 'vi') {
        // Detect if asking for help
        $helpPhrases = [
            'en' => ['help', 'how to', 'what can you do', 'commands'],
            'vi' => ['giúp', 'trợ giúp', 'hướng dẫn', 'lệnh'],
            'zh' => ['帮助', '怎么做', '你能做什么', '命令'],
            'ja' => ['助けて', 'ヘルプ', '使い方', 'コマンド'],
            'ko' => ['도움', '도와줘', '어떻게', '명령어'],
            'fr' => ['aide', 'comment', 'que peux-tu faire', 'commandes'],
            'es' => ['ayuda', 'como', 'qué puedes hacer', 'comandos']
        ];
        
        $currentLangPhrases = $helpPhrases[$lang] ?? $helpPhrases['en'];
        $isHelpRequest = false;
        
        foreach ($currentLangPhrases as $phrase) {
            if (stripos($prompt, $phrase) !== false) {
                $isHelpRequest = true;
                break;
            }
        }
        
        if ($isHelpRequest) {
            switch ($lang) {
                case 'en':
                    return "I'm an AI assistant that can help answer questions about courses. You can: \n- Ask questions about courses\n- Get information about instructors\n- Change language with '/lang en', '/lang vi', etc.\n- Upload images for analysis";
                case 'vi':
                    return "Tôi là trợ lý AI có thể giúp trả lời câu hỏi về các khóa học. Bạn có thể: \n- Đặt câu hỏi về khóa học\n- Lấy thông tin về giảng viên\n- Thay đổi ngôn ngữ với '/lang vi', '/lang en', v.v.\n- Tải lên hình ảnh để phân tích";
                case 'zh':
                    return "我是一个AI助手，可以帮助回答关于课程的问题。您可以：\n- 询问有关课程的问题\n- 获取有关讲师的信息\n- 使用'/lang zh'、'/lang en'等更改语言\n- 上传图片进行分析";
                case 'ja':
                    return "私はコースに関する質問に答えるのを助けるAIアシスタントです。あなたは以下のことができます：\n- コースに関する質問をする\n- 講師に関する情報を取得する\n- '/lang ja'、'/lang en'などで言語を変更する\n- 分析のために画像をアップロードする";
                case 'ko':
                    return "저는 과정에 관한 질문에 답변을 도와주는 AI 어시스턴트입니다. 다음과 같은 일을 할 수 있습니다:\n- 과정에 관한 질문하기\n- 강사에 관한 정보 얻기\n- '/lang ko', '/lang en' 등으로 언어 변경하기\n- 분석을 위한 이미지 업로드하기";
                case 'fr':
                    return "Je suis un assistant IA qui peut aider à répondre aux questions sur les cours. Vous pouvez :\n- Poser des questions sur les cours\n- Obtenir des informations sur les instructeurs\n- Changer de langue avec '/lang fr', '/lang en', etc.\n- Télécharger des images pour analyse";
                case 'es':
                    return "Soy un asistente de IA que puede ayudar a responder preguntas sobre cursos. Puedes:\n- Hacer preguntas sobre cursos\n- Obtener información sobre instructores\n- Cambiar el idioma con '/lang es', '/lang en', etc.\n- Subir imágenes para análisis";
                default:
                    return "I'm an AI assistant that can help answer questions about courses. You can: \n- Ask questions about courses\n- Get information about instructors\n- Change language with '/lang en', '/lang vi', etc.\n- Upload images for analysis";
            }
        }
        
        // Default fallback responses
        $fallbackResponses = [
            'en' => "I apologize, but I'm having trouble processing your request at the moment. Please try again later or contact support if the problem persists.",
            'vi' => "Tôi xin lỗi, nhưng hiện tại tôi đang gặp sự cố khi xử lý yêu cầu của bạn. Vui lòng thử lại sau hoặc liên hệ với bộ phận hỗ trợ nếu sự cố vẫn tiếp diễn.",
            'zh' => "抱歉，我目前在处理您的请求时遇到了问题。请稍后再试，或者如果问题仍然存在，请联系支持团队。",
            'ja' => "申し訳ありませんが、現在リクエストの処理に問題が発生しています。後でもう一度お試しいただくか、問題が解決しない場合はサポートにお問い合わせください。",
            'ko' => "죄송합니다만, 현재 귀하의 요청을 처리하는 데 문제가 있습니다. 나중에 다시 시도하시거나 문제가 지속되면 지원팀에 문의하십시오.",
            'fr' => "Je m'excuse, mais j'ai des difficultés à traiter votre demande en ce moment. Veuillez réessayer plus tard ou contacter le support si le problème persiste.",
            'es' => "Me disculpo, pero estoy teniendo problemas para procesar su solicitud en este momento. Inténtelo de nuevo más tarde o póngase en contacto con el soporte si el problema persiste."
        ];
        
        return $fallbackResponses[$lang] ?? $fallbackResponses['en'];
    }
    
    private function parseDocx($filePath) {
        try {
            $zip = new ZipArchive;
            if ($zip->open($filePath) === true) {
                if (($index = $zip->locateName('word/document.xml')) !== false) {
                    $data = $zip->getFromIndex($index);
                    $xml = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
                    $text = strip_tags($xml->asXML());
                    $zip->close();
                    return $text;
                }
                $zip->close();
            }
            return '[DOCX contents could not be parsed]';
        } catch (Exception $e) {
            error_log("Exception in parseDocx: " . $e->getMessage());
            return '[Error parsing DOCX file]';
        }
    }

    private function parsePdf($filePath) {
        try {
            $output = null;
            $returnVar = null;
            $pdftotextPath = __DIR__ . '/../../Poppler/Library/bin/pdftotext.exe';
            
            if (!file_exists($pdftotextPath)) {
                error_log("pdftotext not found at: $pdftotextPath");
                return '[PDF extraction tool not found]';
            }
            
            exec($pdftotextPath . " " . escapeshellarg($filePath) . " -", $output, $returnVar);
            return ($returnVar === 0) ? implode("\n", $output) : '[PDF contents could not be extracted]';
        } catch (Exception $e) {
            error_log("Exception in parsePdf: " . $e->getMessage());
            return '[Error parsing PDF file]';
        }
    }

    private function callClarifai($imageData, $clarifaiApiKey) {
        if (empty($clarifaiApiKey)) {
            error_log("Missing Clarifai API key");
            return null;
        }
        
        try {
            $url = "https://api.clarifai.com/v2/models/general-image-detection/outputs";
            $postData = json_encode(["inputs" => [["data" => ["image" => ["base64" => $imageData['data']]]]]]);
            $headers = ['Authorization: Key ' . $clarifaiApiKey, 'Content-Type: application/json'];
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, 
                CURLOPT_POST => true, 
                CURLOPT_HTTPHEADER => $headers, 
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            if ($response === false) {
                error_log("Curl error in callClarifai: " . curl_error($ch));
                curl_close($ch);
                return null;
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode != 200) {
                error_log("Clarifai API error: HTTP $httpCode, Response: $response");
                return null;
            }
            
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON parse error in callClarifai: " . json_last_error_msg());
                return null;
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Exception in callClarifai: " . $e->getMessage());
            return null;
        }
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
                $parts[] = ['inline_data' => ['mime_type' => $imageData['mime_type'], 'data' => $imageData['data']]];
            }
            
            $postData = ['contents' => [['parts' => $parts]]];
            $jsonPostData = json_encode($postData);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON encoding error in callGeminiWithImage: " . json_last_error_msg());
                return null;
            }
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, 
                CURLOPT_POST => true, 
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $jsonPostData,
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            if ($response === false) {
                error_log("Curl error in callGeminiWithImage: " . curl_error($ch));
                curl_close($ch);
                return null;
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode != 200) {
                error_log("Gemini API error: HTTP $httpCode, Response: $response");
                return null;
            }
            
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON parse error in callGeminiWithImage: " . json_last_error_msg());
                return null;
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Exception in callGeminiWithImage: " . $e->getMessage());
            return null;
        }
    }

    public function debug() {
        // This function provides diagnostic information about the chatbot system
        header('Content-Type: application/json');
        
        // Clear any existing output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Test data
        $testResponse = [
            'status' => 'success',
            'system_info' => [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'session_status' => session_status(),
                'date' => date('Y-m-d H:i:s'),
                'memory_usage' => memory_get_usage(true),
                'timezone' => date_default_timezone_get()
            ],
            'config_status' => [
                'api_config_exists' => file_exists(ROOT_DIR . '/app/config/api_config.php'),
                'api_keys_set' => $this->checkApiKeys(),
            ],
            'test_message' => 'If you can see this message as proper JSON, basic JSON responses are working correctly.'
        ];
        
        // Send as JSON with proper encoding
        echo json_encode($testResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    private function checkApiKeys() {
        try {
            $configPath = ROOT_DIR . '/app/config/api_config.php';
            if (!file_exists($configPath)) {
                return false;
            }
            
            $config = require $configPath;
            $keysSet = !empty($config['google_api_key']) || 
                      !empty($config['openai_api_key']) || 
                      !empty($config['clarifai_api_key']);
                      
            return $keysSet;
        } catch (Exception $e) {
            return false;
        }
    }
}