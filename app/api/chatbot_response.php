<?php
/**
 * Simple API endpoint for chatbot responses
 * Handles text-only questions with fallback responses
 */

// Define constants
define('BASE_URL', isset($_SERVER['BASE_URL']) ? $_SERVER['BASE_URL'] : '/');
define('ROOT_DIR', realpath(dirname(__FILE__) . '/../../'));

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    // Ensure cookies are set for the right path
    $cookiePath = parse_url(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/', PHP_URL_PATH);
    $cookiePath = rtrim(dirname($cookiePath), '/') . '/';
    
    // Configure session to be more reliable
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_path', $cookiePath);
    ini_set('session.cookie_httponly', 1);
    
    session_start();
    
    // Generate CSRF token if not exists
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        api_log("Generated new CSRF token: " . substr($_SESSION['csrf_token'], 0, 10) . '...');
    }
}

// Debug session
api_log("Session status", [
    'session_id' => session_id(),
    'token_exists' => isset($_SESSION['csrf_token']),
    'cookie_path' => ini_get('session.cookie_path'),
    'cookie_domain' => ini_get('session.cookie_domain')
]);

// Set content type to JSON
header('Content-Type: application/json');

// Configure error handling
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("Chatbot API initializing");

// Create logs directory if it doesn't exist
$logDir = ROOT_DIR . '/app/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/api_error.log');

/**
 * Helper function to log API activity
 */
function api_log($message, $data = null) {
    $logMessage = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $logMessage .= " - " . json_encode($data);
    }
    error_log($logMessage);
}

api_log("Chatbot API request received");

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token with better debugging and development mode
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $error_details = [
        'post_token_exists' => isset($_POST['csrf_token']),
        'session_token_exists' => isset($_SESSION['csrf_token']),
        'session_id' => session_id(),
        'post_token_prefix' => isset($_POST['csrf_token']) ? substr($_POST['csrf_token'], 0, 10) . '...' : 'N/A',
        'session_token_prefix' => isset($_SESSION['csrf_token']) ? substr($_SESSION['csrf_token'], 0, 10) . '...' : 'N/A',
        'match' => isset($_POST['csrf_token']) && isset($_SESSION['csrf_token']) ? 
                  ($_POST['csrf_token'] === $_SESSION['csrf_token'] ? 'Yes' : 'No') : 'N/A',
        'post_token_length' => isset($_POST['csrf_token']) ? strlen($_POST['csrf_token']) : 0,
        'session_token_length' => isset($_SESSION['csrf_token']) ? strlen($_SESSION['csrf_token']) : 0,
        'cookie_path' => session_get_cookie_params()['path'],
        'cookie_domain' => session_get_cookie_params()['domain'],
        'referrer' => $_SERVER['HTTP_REFERER'] ?? 'none',
        'host' => $_SERVER['HTTP_HOST'] ?? 'unknown'
    ];
    
    api_log("CSRF token validation failed", $error_details);
    
    // Check for development mode (localhost)
    $is_local_dev = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                    strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false);
    
    if ($is_local_dev) {
        api_log("Development mode detected, bypassing strict CSRF for debugging");
        
        // Generate a temporary CSRF token for this session if needed
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            api_log("Created new CSRF token for development: " . substr($_SESSION['csrf_token'], 0, 10) . '...');
        }
    } else {
        // In production, enforce strict CSRF validation
        echo json_encode([
            'status' => 'error', 
            'reply' => 'Xin lỗi, có lỗi xác thực. Vui lòng làm mới trang và thử lại.',
            'debug' => $error_details
        ]);
        exit;
    }
}

// Log request info
$request_data = [
    'POST' => array_keys($_POST),
    'FILES' => !empty($_FILES) ? array_keys($_FILES) : "No files",
    'CSRF' => "Validated successfully",
    'IP' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
];
api_log("Request details", $request_data);

// Get prompt from request
$prompt = isset($_POST['prompt']) ? trim($_POST['prompt']) : '';
api_log("Processing prompt: " . (strlen($prompt) > 100 ? substr($prompt, 0, 100) . '...' : $prompt));

// Get saved language preference
$preferredLanguage = $_SESSION['preferred_language'] ?? 'vi';
api_log("Using language: $preferredLanguage");

// Load API configuration
$configPath = ROOT_DIR . '/app/config/api_config.php';
if (file_exists($configPath)) {
    $config = require $configPath;
    $googleApiKey = $config['google_api_key'] ?? '';
} else {
    $googleApiKey = '';
}

if (empty($googleApiKey)) {
    api_log("Missing Google API key");
    echo json_encode([
        'status' => 'success',
        'reply' => getFallbackResponse($prompt, $preferredLanguage)
    ]);
    exit;
}

// Process any language command
if (preg_match('/^\/lang\s+([a-z]{2})$/', $prompt, $matches)) {
    $lang = $matches[1];
    $supportedLanguages = ['vi', 'en', 'zh', 'ja', 'ko', 'fr', 'es'];
    
    if (in_array($lang, $supportedLanguages)) {
        $_SESSION['preferred_language'] = $lang;
        echo json_encode([
            'status' => 'success',
            'reply' => getLanguageChangedResponse($lang)
        ]);
        exit;
    }
}

// Process images if present
$imageData = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $imageData = processUploadedImage($_FILES['image']);
}

// Create Gemini API request
try {
    api_log("Calling Gemini API");
    $response = callGeminiAPI($prompt, $imageData, $googleApiKey, $preferredLanguage);
    
    if ($response && isset($response['text'])) {
        api_log("Gemini API success: " . substr($response['text'], 0, 100) . "...");
        echo json_encode([
            'status' => 'success',
            'reply' => $response['text']
        ]);
        exit;
    } else {
        api_log("Gemini API returned invalid response");
        throw new Exception("Invalid API response");
    }
} catch (Exception $e) {
    api_log("Gemini API error: " . $e->getMessage());
    echo json_encode([
        'status' => 'success', 
        'reply' => getFallbackResponse($prompt, $preferredLanguage)
    ]);
    exit;
}

// Helper functions
function getLanguageChangedResponse($lang) {
    $responses = [
        'vi' => 'Đã chuyển sang tiếng Việt. Tôi có thể giúp gì cho bạn?',
        'en' => 'Switched to English. How can I help you?',
        'zh' => '已切换到中文。我能帮您什么？',
        'ja' => '日本語に切り替えました。どのようにお手伝いできますか？',
        'ko' => '한국어로 전환되었습니다. 어떻게 도와드릴까요?',
        'fr' => 'Passé au français. Comment puis-je vous aider?',
        'es' => 'Cambiado a español. ¿Cómo puedo ayudarte?'
    ];
    
    return $responses[$lang] ?? $responses['en'];
}

function getFallbackResponse($prompt, $lang = 'vi') {
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

/**
 * Process uploaded image
 */
function processUploadedImage($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        api_log("Image upload error: " . $file['error']);
        return null;
    }
    
    $fileType = $file['type'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    
    if (!in_array($fileType, $allowedTypes)) {
        api_log("Invalid image type: $fileType");
        return null;
    }
    
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        api_log("Image too large: " . $file['size'] . " bytes");
        return null;
    }
    
    try {
        // Resize image if needed
        $imageContent = resizeImage($file['tmp_name'], $fileType, 1024); // Max 1024px dimension
        
        if ($imageContent) {
            return [
                'mime_type' => $fileType,
                'data' => base64_encode($imageContent)
            ];
        }
    } catch (Exception $e) {
        api_log("Error processing image: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Resize image if needed
 */
function resizeImage($filePath, $fileType, $maxDimension) {
    list($width, $height) = getimagesize($filePath);
    
    // Don't resize if already smaller than max dimension
    if ($width <= $maxDimension && $height <= $maxDimension) {
        return file_get_contents($filePath);
    }
    
    // Calculate new dimensions
    if ($width > $height) {
        $newWidth = $maxDimension;
        $newHeight = floor($height * ($maxDimension / $width));
    } else {
        $newHeight = $maxDimension;
        $newWidth = floor($width * ($maxDimension / $height));
    }
    
    // Create image resource based on file type
    $sourceImage = null;
    switch ($fileType) {
        case 'image/jpeg':
        case 'image/jpg':
            $sourceImage = imagecreatefromjpeg($filePath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($filePath);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Create new image with resized dimensions
    $destImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Handle transparency for PNG images
    if ($fileType === 'image/png') {
        imagecolortransparent($destImage, imagecolorallocate($destImage, 0, 0, 0));
        imagealphablending($destImage, false);
        imagesavealpha($destImage, true);
    }
    
    // Resize the image
    imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Output the image to a buffer
    ob_start();
    switch ($fileType) {
        case 'image/jpeg':
        case 'image/jpg':
            imagejpeg($destImage, null, 85); // 85% quality
            break;
        case 'image/png':
            imagepng($destImage, null, 6); // Compression level 6
            break;
    }
    $imageData = ob_get_clean();
    
    // Free up memory
    imagedestroy($sourceImage);
    imagedestroy($destImage);
    
    return $imageData;
}

/**
 * Call Gemini API with or without image
 */
function callGeminiAPI($prompt, $imageData, $apiKey, $language = 'vi') {
    if (empty($apiKey)) {
        api_log("Missing Google API key");
        return null;
    }
    
    try {
        // Add language context to prompt
        $langContext = [
            'vi' => "Hãy trả lời bằng tiếng Việt. ",
            'en' => "Please respond in English. ",
            'zh' => "请用中文回答。",
            'ja' => "日本語で答えてください。",
            'ko' => "한국어로 대답해주세요。",
            'fr' => "Veuillez répondre en français. ",
            'es' => "Por favor, responde en español. "
        ];
        
        $finalPrompt = ($langContext[$language] ?? "") . $prompt;
        
        // Create API request URL
        $model = "gemini-2.0-flash";
        $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}";
        
        // Create the request payload
        $parts = [['text' => $finalPrompt]];
        
        // Add image if available
        if ($imageData) {
            api_log("Including image in request. MIME type: " . $imageData['mime_type']);
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $imageData['mime_type'],
                    'data' => $imageData['data']
                ]
            ];
            
            // Use vision model
            $model = "gemini-2.0-pro";
            $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}";
        }
        
        // Prepare request data
        $data = [
            'contents' => [
                [
                    'parts' => $parts
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 2048,
            ]
        ];
        
        $jsonData = json_encode($data);
        $dataSize = strlen($jsonData);
        api_log("Request payload size: {$dataSize} bytes");
        
        // Send request
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($response === false) {
            api_log("Curl error: " . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        if ($httpCode != 200) {
            api_log("API error: HTTP {$httpCode}, Response: " . substr($response, 0, 500));
            return null;
        }
        
        // Parse response
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            api_log("JSON parsing error: " . json_last_error_msg());
            return null;
        }
        
        // Extract text from response
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $text = $result['candidates'][0]['content']['parts'][0]['text'];
            return ['text' => $text];
        } else {
            api_log("Invalid API response structure: " . json_encode($result));
            return null;
        }
    } catch (Exception $e) {
        api_log("Exception in callGeminiAPI: " . $e->getMessage());
        return null;
    }
}
