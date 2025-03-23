<?php
// Don't redefine constants if already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/WebCourses/');
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2));
}

// Include database connection
require_once ROOT_DIR . '/app/config/connect.php';

// Include the LessonFilesAPI controller
require_once ROOT_DIR . '/app/controllers/api/LessonFilesAPI.php';

// Set headers
header('Content-Type: application/json');

// Handle CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Get lesson_id from the request
$lesson_id = isset($_GET['lesson_id']) ? intval($_GET['lesson_id']) : 0;

// Initialize the LessonFilesAPI controller
$lessonFilesAPI = new LessonFilesAPI($conn);

// Get files
$response = $lessonFilesAPI->getFiles($lesson_id);

// Output the response
echo json_encode($response);
exit; 