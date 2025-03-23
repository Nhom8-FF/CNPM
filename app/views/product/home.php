<?php
// Check if session is already started before starting it
if (session_status() == PHP_SESSION_NONE) {
    // Configure session to be more compatible with AJAX requests
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    
    // Set cookie path to root of site for better compatibility
    session_set_cookie_params([
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
    
    error_log("Started session in home.php with ID: " . session_id());
}

// Don't redefine constants if already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/WebCourses/');
}
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 3));
}
$_SESSION['redirect_count'] = 0;

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Debug information
error_log("Home.php loaded. REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data: " . print_r($_POST, true));
}

// Check for login/register error messages
$loginError = $_SESSION['loginError'] ?? '';
$registerError = $_SESSION['registerError'] ?? '';
$registerSuccess = $_SESSION['registerSuccess'] ?? '';

// Clear session messages after displaying them
if (isset($_SESSION['loginError'])) unset($_SESSION['loginError']);
if (isset($_SESSION['registerError'])) unset($_SESSION['registerError']);
if (isset($_SESSION['registerSuccess'])) unset($_SESSION['registerSuccess']);

// Kết nối database và khởi tạo controllers - Make sure these are loaded in the right order
require_once ROOT_DIR . '/app/config/connect.php';
require_once ROOT_DIR . '/app/helpers/functions.php'; // Make sure helper functions are included
require_once ROOT_DIR . '/app/controllers/AppController.php'; // Load first
require_once ROOT_DIR . '/app/controllers/CourseController.php';
require_once ROOT_DIR . '/app/controllers/CategoryController.php';
require_once ROOT_DIR . '/app/controllers/ReviewController.php';
require_once ROOT_DIR . '/app/controllers/ChatbotController.php';
require_once ROOT_DIR . '/app/controllers/AuthController.php'; // Load after AppController

$db = $conn; // Giả sử $conn từ connect.php
$reviewController = new ReviewController($db);
$chatbotController = new ChatbotController($db);
$authController = new AuthController($db);
$courseController = new CourseController($db);
$categoryController = new CategoryController($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['review_submit'])) {
        $reviewController->submitReview();
    } elseif (isset($_POST['register'])) {
        $authController->register();
    } elseif (isset($_POST['login'])) {
        $authController->login();
    } elseif (isset($_POST['forgot_password'])) {
        $authController->forgotPassword();
    } elseif (isset($_POST['reset-password'])) {
        $authController->resetPassword();
    }
    exit();
}

// Lấy dữ liệu từ controllers
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? null;
$courses = ($userRole === 'student' || !$isLoggedIn) ? $courseController->getCoursesWithDetails() : []; // Lấy 3 khóa học nổi bật với thông tin chi tiết
$categories = $categoryController->getCategories();
$reviews = $reviewController->getReviews();

// Lấy thông báo từ session
$registerError = $_SESSION['registerError'] ?? null;
$registerSuccess = $_SESSION['registerSuccess'] ?? null;
$loginError = $_SESSION['loginError'] ?? null;
$forgotError = $_SESSION['forgotError'] ?? null;
$forgotSuccess = $_SESSION['forgotSuccess'] ?? null;
$resetError = $_SESSION['resetError'] ?? null;
$resetSuccess = $_SESSION['resetSuccess'] ?? null;
$reviewError = $_SESSION['reviewError'] ?? null;
$reviewSuccess = $_SESSION['reviewSuccess'] ?? null;

// Xóa thông báo sau khi sử dụng
unset($_SESSION['registerError'], $_SESSION['registerSuccess'], $_SESSION['loginError'], 
      $_SESSION['forgotError'], $_SESSION['forgotSuccess'], $_SESSION['resetError'], 
      $_SESSION['resetSuccess'], $_SESSION['reviewError'], $_SESSION['reviewSuccess']);

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

error_log("Đang chuyển hướng đến home.php");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''; ?>">
    <title>Học Tập Trực Tuyến - Nâng Cao</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/review.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/chatbot.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/ScrollTrigger.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        // Ensure CSRF token is available to all scripts
        window.CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
        window.BASE_URL = '<?php echo BASE_URL; ?>';
        console.log("BASE_URL:", window.BASE_URL); // Debug
    </script>
    <script>
        // Debug helper function for chatbot
        function debugLog(message, data = null) {
            const logMsg = `[BotEdu] ${message}` + (data ? `: ${JSON.stringify(data)}` : '');
            
            // Log to console 
            console.log(logMsg);
            
            // Store logs in session storage for debugging
            try {
                const logs = JSON.parse(sessionStorage.getItem('botEduLogs') || '[]');
                logs.push({
                    time: new Date().toISOString(),
                    message: logMsg
                });
                
                // Keep only the last 50 logs
                while (logs.length > 50) {
                    logs.shift();
                }
                
                sessionStorage.setItem('botEduLogs', JSON.stringify(logs));
            } catch (e) {
                console.error('Error saving log:', e);
            }
        }

        // Direct chatbot response helper
        window.getChatbotResponse = async function(prompt) {
            const formData = new FormData();
            formData.append('prompt', prompt);
            formData.append('csrf_token', window.CSRF_TOKEN);
            
            debugLog(`Sending prompt to chatbot API`, { prompt });
            
            try {
                // First try the simplified response API
                const apiUrl = new URL('WebCourses/app/api/chatbot_response.php', window.location.origin).href;
                
                debugLog(`Calling API at ${apiUrl}`);
                
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    debugLog(`API response not OK: ${response.status}`, { status: response.status });
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const responseText = await response.text();
                debugLog(`API raw response`, { text: responseText.substring(0, 100) });
                
                try {
                    const result = JSON.parse(responseText);
                    debugLog(`API response parsed`, { status: result.status });
                    return result;
                } catch (parseError) {
                    debugLog(`Error parsing API response`, { error: parseError.message });
                    throw new Error(`Could not parse response: ${parseError.message}`);
                }
            } catch (error) {
                debugLog('Error in getChatbotResponse', { error: error.message });
                console.error('Detailed error:', error);
                
                return {
                    status: 'success',
                    reply: 'Xin lỗi, tôi đang gặp vấn đề kết nối. Vui lòng thử lại sau.'
                };
            }
        };

        // Add event listener to check session status
        window.addEventListener('load', function() {
            // Verify CSRF token exists and is valid
            if (!window.CSRF_TOKEN || window.CSRF_TOKEN.length < 32) {
                console.error('CSRF token missing or invalid, reloading page');
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }
            
            // Add debugging for CSRF
            debugLog('Session ready with CSRF token', {
                token_prefix: window.CSRF_TOKEN ? window.CSRF_TOKEN.substring(0, 10) + '...' : 'Missing'
            });
        });
    </script>
</head>
<body>
    <header>
        <div class="container header-container">
            <div class="logo">Học Tập</div>
            <nav>
                <ul>
                    <li><a href="#hero">Trang Chủ</a></li>
                    <li class="has-submenu">
                        <a href="#courses">Khoá Học</a>
                        <ul class="submenu">
                            <?php foreach ($categories as $cat): ?>
                                <li><a href="courses_management.php?category_id=<?php echo $cat['category_id']; ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <li><a href="#reviews">Nhận Xét</a></li>
                    <li><a href="#faq">FAQ</a></li>
                    <li><a href="#blog">Blog</a></li>
                    <li><a href="#contact">Liên Hệ</a></li>
                    <li><a href="student_dashboard.php" class="btn btn-start">Bắt đầu ngay</a></li>
                    <?php if ($isLoggedIn): ?>
                        <li><span class="welcome-text">Xin chào, <?php echo htmlspecialchars($_SESSION['username']); ?></span></li>
                        <li><a href="<?php echo BASE_URL; ?>auth/logout" class="btn btn-logout">Đăng xuất</a></li>
                    <?php else: ?>
                        <li><a href="#" class="btn btn-login">Đăng Nhập</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <section id="hero">
        <div class="hero-content">
            <h1>Trải Nghiệm Học Tập Hiện Đại</h1>
            <p>Khám phá các khoá học chất lượng qua giao diện tối giản và hiệu ứng động mượt mà</p>
            <a href="#" class="btn btn-primary">Bắt đầu ngay</a>
        </div>
        <canvas id="robot"></canvas>
    </section>

    <section id="features">
        <div class="container">
            <h2>Tính Năng Nổi Bật</h2>
            <div class="features-container">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-vr-cardboard"></i>
                    </div>
                    <h3>Học Tập Thực Tế Ảo</h3>
                    <p>Trải nghiệm học tập sống động với công nghệ VR/AR tiên tiến, giúp bạn đắm chìm trong môi trường học tập 3D chân thực.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h3>Trí Tuệ Nhân Tạo</h3>
                    <p>Hệ thống AI thông minh phân tích phong cách học tập của bạn và đề xuất lộ trình phù hợp với khả năng tiếp thu.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <h3>Học Tập Cộng Tác</h3>
                    <p>Tham gia các dự án thực tế và làm việc nhóm với công nghệ đám mây, chia sẻ kiến thức và phát triển kỹ năng xã hội.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-medal"></i>
                    </div>
                    <h3>Chứng Chỉ Blockchain</h3>
                    <p>Nhận chứng chỉ được xác thực bằng công nghệ blockchain, đảm bảo tính minh bạch và giá trị thực tế trên thị trường.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- AI Learning Path Section - New for 2025 -->
    <section id="ai-learning">
        <div class="container">
            <div class="ai-learning-container">
                <div class="ai-learning-header">
                    <h2>Lộ Trình Học Tập <span>Cá Nhân Hóa</span></h2>
                    <p>Công nghệ AI của chúng tôi phân tích hành vi học tập, sở thích và mục tiêu nghề nghiệp để tạo ra lộ trình học tập tối ưu cho từng cá nhân.</p>
                </div>
                <div class="ai-path">
                    <div class="path-step">
                        <div class="step-number">1</div>
                        <h3>Đánh Giá Kỹ Năng</h3>
                        <p>Hệ thống AI đánh giá trình độ hiện tại của bạn thông qua các bài kiểm tra thích ứng và phân tích hành vi học tập.</p>
                    </div>
                    <div class="path-step">
                        <div class="step-number">2</div>
                        <h3>Xây Dựng Lộ Trình</h3>
                        <p>Dựa trên kết quả đánh giá, AI tạo ra lộ trình học tập cá nhân hóa với các khóa học phù hợp với phong cách học tập của bạn.</p>
                    </div>
                    <div class="path-step">
                        <div class="step-number">3</div>
                        <h3>Học Tập & Điều Chỉnh</h3>
                        <p>Khi bạn học tập, hệ thống liên tục điều chỉnh lộ trình dựa trên tiến độ và phản hồi, đảm bảo kết quả tối ưu.</p>
                    </div>
                </div>
                <?php if ($isLoggedIn && $userRole === 'student'): ?>
                <div class="ai-recommendation">
                    <h3>Khóa Học Đề Xuất Cho Bạn</h3>
                    <div class="ai-courses">
                        <?php 
                        // Lấy ra 3 khóa học với tỷ lệ matching cao nhất cho người dùng
                        $aiRecommendedCourses = array_slice($courses, 0, 3);
                        foreach ($aiRecommendedCourses as $index => $course): 
                            $matchRate = 85 + rand(0, 15); // Giả lập tỷ lệ phù hợp từ 85-100%
                        ?>
                        <div class="ai-course-card">
                            <div class="ai-course-image">
                                <?php if (!empty($course['image'])): ?>
                                    <img src="<?php echo BASE_URL . htmlspecialchars($course['image']); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>">
                                <?php else: ?>
                                    <img src="<?php echo BASE_URL; ?>public/images/course-default.jpg" alt="Course">
                                <?php endif; ?>
                                <span class="ai-match"><?php echo $matchRate; ?>% phù hợp</span>
                            </div>
                            <div class="ai-course-content">
                                <h4><?php echo htmlspecialchars($course['title']); ?></h4>
                                <div class="ai-course-meta">
                                    <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($course['instructor_name'] ?? 'Chưa có'); ?></span>
                                    <span><i class="fas fa-signal"></i> <?php echo htmlspecialchars($course['level'] ?? 'Cơ bản'); ?></span>
                                </div>
                                <a href="enroll.php?course_id=<?php echo $course['course_id']; ?>" class="ai-enroll-btn">Đăng ký ngay</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Featured Courses Section -->
    <section class="featured-courses" id="courses">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Khóa Học Nổi Bật</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">Khám phá các khóa học công nghệ tiên tiến được thiết kế bởi các chuyên gia hàng đầu trong ngành.</p>
            
            <div class="courses-container">
                <?php if (isset($_SESSION['user_id'])) : ?>
                    <?php foreach ($featuredCourses as $course) : ?>
                        <div class="course-card" data-aos="fade-up" data-aos-delay="150">
                            <div class="course-image">
                                <img src="<?= $course['image'] ?>" alt="<?= $course['title'] ?>">
                                <span class="course-tag"><?= $course['category'] ?></span>
                            </div>
                            <div class="course-content">
                                <div class="course-categories">
                                    <span class="course-category"><?= $course['category'] ?></span>
                                    <?php if ($course['level'] === 'Beginner') : ?>
                                        <span class="course-category">Cơ Bản</span>
                                    <?php elseif ($course['level'] === 'Intermediate') : ?>
                                        <span class="course-category">Trung Cấp</span>
                                    <?php else : ?>
                                        <span class="course-category">Cao Cấp</span>
                                    <?php endif; ?>
                                </div>
                                <h3><?= $course['title'] ?></h3>
                                <div class="course-meta">
                                    <div class="instructor">
                                        <i class="fas fa-user"></i>
                                        <span><?= $course['instructor'] ?></span>
                                    </div>
                                    <div class="duration">
                                        <i class="fas fa-clock"></i>
                                        <span><?= $course['duration'] ?> giờ</span>
                                    </div>
                                </div>
                                <p class="course-description"><?= substr($course['description'], 0, 120) ?>...</p>
                                <div class="course-rating">
                                    <div class="rating-stars">
                                        <?php for ($i = 0; $i < floor($course['rating']); $i++) : ?>
                                            <i class="fas fa-star"></i>
                                        <?php endfor; ?>
                                        <?php if ($course['rating'] - floor($course['rating']) >= 0.5) : ?>
                                            <i class="fas fa-star-half-alt"></i>
                                        <?php endif; ?>
                                    </div>
                                    <span class="rating-count">(<?= $course['reviews'] ?> đánh giá)</span>
                                </div>
                                <div class="course-footer">
                                    <?php if ($course['price'] > 0) : ?>
                                        <div class="course-price">
                                            <?php if (isset($course['old_price']) && $course['old_price'] > $course['price']) : ?>
                                                <span class="old-price"><?= number_format($course['old_price']) ?>đ</span>
                                            <?php endif; ?>
                                            <?= number_format($course['price']) ?>đ
                                        </div>
                                    <?php else : ?>
                                        <div class="course-price free-course">Miễn Phí</div>
                                    <?php endif; ?>
                                </div>
                                <button class="course-btn" 
                                        data-course-id="<?= $course['id'] ?>" 
                                        data-course-title="<?= $course['title'] ?>">
                                    <i class="fas fa-graduation-cap"></i> Đăng Ký Ngay
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <!-- Standard Featured Courses for Non-Logged-In Users -->
                    <div class="course-card" data-aos="fade-up" data-aos-delay="150">
                        <div class="course-image">
                            <img src="<?= BASE_URL ?>/public/images/courses/vr-course.jpg" alt="VR Development">
                            <span class="course-tag">VR/AR</span>
                        </div>
                        <div class="course-content">
                            <div class="course-categories">
                                <span class="course-category">VR/AR</span>
                                <span class="course-category">Cơ Bản</span>
                            </div>
                            <h3>Lập Trình Ứng Dụng Thực Tế Ảo</h3>
                            <div class="course-meta">
                                <div class="instructor">
                                    <i class="fas fa-user"></i>
                                    <span>Nguyễn Văn A</span>
                                </div>
                                <div class="duration">
                                    <i class="fas fa-clock"></i>
                                    <span>24 giờ</span>
                                </div>
                            </div>
                            <p class="course-description">Khám phá cách tạo ứng dụng thực tế ảo tương tác sử dụng Unity và các công cụ mới nhất trong ngành...</p>
                            <div class="course-rating">
                                <div class="rating-stars">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star-half-alt"></i>
                                </div>
                                <span class="rating-count">(128 đánh giá)</span>
                            </div>
                            <div class="course-footer">
                                <div class="course-price">
                                    <span class="old-price">1,800,000đ</span>
                                    1,500,000đ
                                </div>
                            </div>
                            <a href="<?= BASE_URL ?>/login" class="course-btn">
                                <i class="fas fa-lock"></i> Đăng Nhập Để Đăng Ký
                            </a>
                        </div>
                    </div>
                    
                    <div class="course-card" data-aos="fade-up" data-aos-delay="200">
                        <div class="course-image">
                            <img src="<?= BASE_URL ?>/public/images/courses/ai-course.jpg" alt="AI Development">
                            <span class="course-tag">AI</span>
                        </div>
                        <div class="course-content">
                            <div class="course-categories">
                                <span class="course-category">AI</span>
                                <span class="course-category">Trung Cấp</span>
                            </div>
                            <h3>Trí Tuệ Nhân Tạo Trong Giáo Dục</h3>
                            <div class="course-meta">
                                <div class="instructor">
                                    <i class="fas fa-user"></i>
                                    <span>Trần Thị B</span>
                                </div>
                                <div class="duration">
                                    <i class="fas fa-clock"></i>
                                    <span>36 giờ</span>
                                </div>
                            </div>
                            <p class="course-description">Tìm hiểu cách triển khai các thuật toán AI để tạo ra trải nghiệm học tập cá nhân hóa và hiệu quả...</p>
                            <div class="course-rating">
                                <div class="rating-stars">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                </div>
                                <span class="rating-count">(95 đánh giá)</span>
                            </div>
                            <div class="course-footer">
                                <div class="course-price">
                                    <span class="old-price">2,200,000đ</span>
                                    1,800,000đ
                                </div>
                            </div>
                            <a href="<?= BASE_URL ?>/login" class="course-btn">
                                <i class="fas fa-lock"></i> Đăng Nhập Để Đăng Ký
                            </a>
                        </div>
                    </div>
                    
                    <div class="course-card" data-aos="fade-up" data-aos-delay="250">
                        <div class="course-image">
                            <img src="<?= BASE_URL ?>/public/images/courses/blockchain-course.jpg" alt="Blockchain">
                            <span class="course-tag">Blockchain</span>
                        </div>
                        <div class="course-content">
                            <div class="course-categories">
                                <span class="course-category">Blockchain</span>
                                <span class="course-category">Cao Cấp</span>
                            </div>
                            <h3>Chứng Chỉ Blockchain Trong Giáo Dục</h3>
                            <div class="course-meta">
                                <div class="instructor">
                                    <i class="fas fa-user"></i>
                                    <span>Lê Văn C</span>
                                </div>
                                <div class="duration">
                                    <i class="fas fa-clock"></i>
                                    <span>30 giờ</span>
                                </div>
                            </div>
                            <p class="course-description">Học cách xây dựng hệ thống cấp chứng chỉ phi tập trung sử dụng công nghệ blockchain tiên tiến...</p>
                            <div class="course-rating">
                                <div class="rating-stars">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                </div>
                                <span class="rating-count">(76 đánh giá)</span>
                            </div>
                            <div class="course-footer">
                                <div class="course-price free-course">Miễn Phí</div>
                            </div>
                            <a href="<?= BASE_URL ?>/login" class="course-btn">
                                <i class="fas fa-lock"></i> Đăng Nhập Để Đăng Ký
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="view-all-courses">
                <a href="<?= BASE_URL ?>/courses" class="view-all-btn">
                    Xem Tất Cả Khóa Học
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <section id="testimonials">
        <div class="container">
            <h2>Nhận Xét Học Viên</h2>
            <div class="testimonials-container">
                <div class="testimonial">
                    <p>"Học tập với công nghệ VR tại đây thực sự đã thay đổi cách tôi tiếp cận kiến thức. Trải nghiệm 3D giúp tôi hiểu các khái niệm phức tạp chỉ trong thời gian ngắn."</p>
                    <h4>Nguyễn Văn Minh</h4>
                    <div class="testimonial-role">Kỹ sư phần mềm, FPT Software</div>
                </div>
                <div class="testimonial">
                    <p>"Lộ trình học tập cá nhân hóa giúp tôi tiết kiệm rất nhiều thời gian. AI đề xuất chính xác những khóa học phù hợp với nhu cầu và phong cách học tập của tôi."</p>
                    <h4>Trần Thị Hương</h4>
                    <div class="testimonial-role">Nhà phân tích dữ liệu, Viettel</div>
                </div>
                <div class="testimonial">
                    <p>"Chứng chỉ blockchain đã giúp tôi nổi bật trong quá trình xin việc. Các nhà tuyển dụng đánh giá cao tính xác thực và minh bạch của chứng chỉ này."</p>
                    <h4>Lê Văn Tùng</h4>
                    <div class="testimonial-role">UI/UX Designer, Tiki</div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section with Modern Styling -->
    <section id="faqs" class="faq-section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Câu Hỏi Thường Gặp</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">Những câu hỏi phổ biến về trải nghiệm học tập trên nền tảng của chúng tôi</p>
            
            <div class="faq-container">
                <div class="faq-accordion">
                    <div class="faq-item" data-aos="fade-up" data-aos-delay="150">
                        <div class="faq-question">
                            <h3>Làm thế nào để bắt đầu một khóa học?</h3>
                            <div class="faq-icon"><i class="fas fa-chevron-down"></i></div>
                        </div>
                        <div class="faq-answer">
                            <p>Để bắt đầu một khóa học, bạn cần đăng ký tài khoản trên nền tảng của chúng tôi, sau đó duyệt qua danh mục khóa học và chọn khóa học phù hợp. Bạn có thể thanh toán trực tuyến qua nhiều phương thức thanh toán khác nhau. Sau khi hoàn tất thanh toán, bạn sẽ có quyền truy cập vào tất cả các tài liệu khóa học.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item" data-aos="fade-up" data-aos-delay="200">
                        <div class="faq-question">
                            <h3>Tôi có cần thiết bị đặc biệt cho các khóa học VR/AR không?</h3>
                            <div class="faq-icon"><i class="fas fa-chevron-down"></i></div>
                        </div>
                        <div class="faq-answer">
                            <p>Đối với trải nghiệm tối ưu, chúng tôi khuyên bạn nên có kính thực tế ảo tương thích (như Oculus Quest, HTC Vive, hoặc Microsoft HoloLens). Tuy nhiên, nhiều khóa học của chúng tôi cũng có phiên bản không VR cho phép bạn tham gia bằng máy tính hoặc thiết bị di động thông thường. Thông tin về yêu cầu phần cứng cụ thể luôn được cung cấp trong mô tả khóa học.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item" data-aos="fade-up" data-aos-delay="250">
                        <div class="faq-question">
                            <h3>Chứng chỉ Blockchain có những lợi ích gì?</h3>
                            <div class="faq-icon"><i class="fas fa-chevron-down"></i></div>
                        </div>
                        <div class="faq-answer">
                            <p>Chứng chỉ Blockchain của chúng tôi cung cấp nhiều lợi ích nổi bật: xác thực không thể chối cãi về thành tích học tập của bạn, khả năng chia sẻ chứng chỉ trực tiếp với nhà tuyển dụng thông qua liên kết có thể xác minh, bảo mật dữ liệu và thông tin cá nhân, và có giá trị lâu dài do không thể bị làm giả hoặc sửa đổi.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item" data-aos="fade-up" data-aos-delay="300">
                        <div class="faq-question">
                            <h3>Làm thế nào để học tập theo nhóm trên nền tảng này?</h3>
                            <div class="faq-icon"><i class="fas fa-chevron-down"></i></div>
                        </div>
                        <div class="faq-answer">
                            <p>Nền tảng của chúng tôi hỗ trợ học tập theo nhóm thông qua các phòng học ảo, nơi bạn có thể tương tác với bạn học qua video, âm thanh và nhắn tin. Chúng tôi cũng cung cấp các công cụ cộng tác như bảng trắng ảo, chia sẻ tài liệu, và các dự án nhóm. Với tính năng đồng bộ hóa thời gian thực, tất cả các thành viên trong nhóm có thể làm việc cùng nhau dù ở bất kỳ đâu.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item" data-aos="fade-up" data-aos-delay="350">
                        <div class="faq-question">
                            <h3>Tôi có thể truy cập vào nội dung khóa học sau khi hoàn thành không?</h3>
                            <div class="faq-icon"><i class="fas fa-chevron-down"></i></div>
                        </div>
                        <div class="faq-answer">
                            <p>Có, bạn sẽ có quyền truy cập vĩnh viễn vào tất cả các khóa học đã mua, kể cả sau khi hoàn thành. Điều này cho phép bạn ôn tập kiến thức bất cứ khi nào cần thiết. Chúng tôi thường xuyên cập nhật nội dung khóa học với thông tin mới nhất, và với tư cách là học viên hiện tại, bạn sẽ được tiếp cận miễn phí với các cập nhật này.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Blog Section - New for 2025 -->
    <section id="blog">
        <div class="container">
            <h2>Bài Viết Mới Nhất</h2>
            <div class="blog-container">
                <div class="blog-card">
                    <div class="blog-image">
                        <img src="<?php echo BASE_URL; ?>public/images/blog-1.jpg" alt="VR Learning">
                        <span class="blog-category">Công Nghệ Mới</span>
                    </div>
                    <div class="blog-content">
                        <div class="blog-date">
                            <i class="fas fa-calendar-alt"></i> 12 Tháng 4, 2025
                        </div>
                        <h3>Cách Công Nghệ VR Đang Định Hình Lại Giáo Dục</h3>
                        <div class="blog-excerpt">
                            <p>Khám phá cách công nghệ thực tế ảo đang cách mạng hóa môi trường học tập, mang đến trải nghiệm đắm chìm và tương tác cao hơn bao giờ hết.</p>
                        </div>
                        <a href="#" class="blog-link">Đọc thêm <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <div class="blog-card">
                    <div class="blog-image">
                        <img src="<?php echo BASE_URL; ?>public/images/blog-2.jpg" alt="AI in Education">
                        <span class="blog-category">AI & ML</span>
                    </div>
                    <div class="blog-content">
                        <div class="blog-date">
                            <i class="fas fa-calendar-alt"></i> 5 Tháng 4, 2025
                        </div>
                        <h3>AI Cá Nhân Hóa: Tương Lai Của Học Tập Thích Ứng</h3>
                        <div class="blog-excerpt">
                            <p>Tìm hiểu cách các thuật toán học máy có thể tạo ra các trải nghiệm học tập được cá nhân hóa cao độ, giúp cải thiện kết quả học tập cho mọi học viên.</p>
                        </div>
                        <a href="#" class="blog-link">Đọc thêm <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <div class="blog-card">
                    <div class="blog-image">
                        <img src="<?php echo BASE_URL; ?>public/images/blog-3.jpg" alt="Blockchain Certification">
                        <span class="blog-category">Blockchain</span>
                    </div>
                    <div class="blog-content">
                        <div class="blog-date">
                            <i class="fas fa-calendar-alt"></i> 28 Tháng 3, 2025
                        </div>
                        <h3>Chứng Chỉ Blockchain: Xác Thực Bằng Cấp Trong Kỷ Nguyên Số</h3>
                        <div class="blog-excerpt">
                            <p>Tại sao chứng chỉ dựa trên blockchain đang trở nên phổ biến và làm thế nào công nghệ này đảm bảo tính xác thực cho thành tựu học tập của bạn.</p>
                        </div>
                        <a href="#" class="blog-link">Đọc thêm <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="contact">
        <div class="container">
            <h2>Liên Hệ</h2>
            <?php if (isset($_SESSION['contactSuccess'])): ?>
                <div class="success-message contact-response">
                    <?php echo $_SESSION['contactSuccess']; unset($_SESSION['contactSuccess']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['contactError'])): ?>
                <div class="error-message contact-response">
                    <?php echo $_SESSION['contactError']; unset($_SESSION['contactError']); ?>
                </div>
            <?php endif; ?>
            <form id="contact-form" class="contact-form" method="post" action="<?php echo BASE_URL; ?>app/controllers/contact.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="submit_contact" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label for="contact-name">Họ và tên</label>
                        <input type="text" id="contact-name" name="name" placeholder="Họ và tên" required>
                    </div>
                    <div class="form-group">
                        <label for="contact-email">Email</label>
                        <input type="email" id="contact-email" name="email" placeholder="Email" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="contact-message">Tin nhắn</label>
                    <textarea id="contact-message" name="message" rows="5" placeholder="Nội dung tin nhắn" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary" name="submit_contact">Gửi tin nhắn</button>
                <div id="contact-response" class="contact-response"></div>
            </form>
        </div>
    </section>

    <section id="reviews">
        <div class="review-section">
            <h2>Nhận Xét Học Viên</h2>
            <?php if ($reviewError): ?>
                <p style="color:red;"><?php echo $reviewError; ?></p>
            <?php elseif ($reviewSuccess): ?>
                <p style="color:green;"><?php echo $reviewSuccess; ?></p>
            <?php endif; ?>
            <div id="reviews-container">
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-card">
                            <p class="review-text">"<?php echo htmlspecialchars($review['review_text']); ?>"</p>
                            <p class="review-rating"><?php echo str_repeat('★', $review['rating']); ?></p>
                            <p class="review-author">- <?php echo htmlspecialchars($review['author']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Chưa có nhận xét nào. Hãy là người đầu tiên chia sẻ ý kiến của bạn!</p>
                <?php endif; ?>
            </div>
            <?php if ($isLoggedIn): ?>
                <div class="review-form">
                    <h3>Thêm đánh giá của bạn</h3>
                    <form id="reviewForm" method="post" action="<?php echo BASE_URL; ?>review/submitReview">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="review_submit" value="1">
                        <label for="comment">Bình luận:</label>
                        <textarea id="comment" name="comment" rows="4" placeholder="Viết bình luận của bạn..." required></textarea>
                        <label for="rating">Đánh giá sao:</label>
                        <select id="rating" name="rating">
                            <option value="5">5 sao</option>
                            <option value="4">4 sao</option>
                            <option value="3">3 sao</option>
                            <option value="2">2 sao</option>
                            <option value="1">1 sao</option>
                        </select>
                        <button type="submit">Gửi đánh giá</button>
                    </form>
                </div>
            <?php else: ?>
                <p>Vui lòng <a href="#" class="btn btn-login">đăng nhập</a> để thêm đánh giá.</p>
            <?php endif; ?>
        </div>
    </section>

    <div id="auth-modal" class="modal">
        <div class="modal-content">
            <span class="close">×</span>
            <div class="auth-form">
                <div class="auth-container">
                    <?php if ($loginError): ?>
                        <p class="error-message"><?php echo $loginError; ?></p>
                    <?php endif; ?>
                    <?php if ($registerError): ?>
                        <p class="error-message"><?php echo $registerError; ?></p>
                    <?php endif; ?>
                    <?php if ($registerSuccess): ?>
                        <p class="success-message"><?php echo $registerSuccess; ?></p>
                    <?php endif; ?>
                    <?php if ($forgotError): ?>
                        <p class="error-message"><?php echo $forgotError; ?></p>
                    <?php endif; ?>
                    <?php if ($forgotSuccess): ?>
                        <p class="success-message"><?php echo $forgotSuccess; ?></p>
                    <?php endif; ?>
                    <?php if ($resetError): ?>
                        <p class="error-message"><?php echo $resetError; ?></p>
                    <?php endif; ?>
                    <?php if ($resetSuccess): ?>
                        <p class="success-message"><?php echo $resetSuccess; ?></p>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['verification_code'])): ?>
                        <form action="<?php echo BASE_URL; ?>auth/resetPassword" method="POST" class="auth-form-content">
                            <h2 class="auth-title">Xác Nhận Mã</h2>
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="auth-input-group">
                                <i class="fas fa-key"></i>
                                <input type="text" name="verification_code" class="auth-input" placeholder="Nhập mã xác nhận" required>
                            </div>
                            <div class="auth-input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="new_password" class="auth-input" placeholder="Mật khẩu mới" required>
                            </div>
                            <button type="submit" name="reset-password" class="auth-submit">Đặt Lại Mật Khẩu</button>
                        </form>
                    <?php elseif (isset($_POST['forgot_password'])): ?>
                        <form action="<?php echo BASE_URL; ?>auth/forgotPassword" method="POST" class="auth-form-content" id="forgot-form">
                            <h2 class="auth-title">Quên Mật Khẩu</h2>
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="auth-input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="forgot_email" class="auth-input" placeholder="Email" required>
                            </div>
                            <button type="submit" name="forgot_password" class="auth-submit">Gửi Mã Xác Nhận</button>
                            <p class="auth-footer"><a href="#" class="link" id="login-link">Quay lại đăng nhập</a></p>
                        </form>
                    <?php else: ?>
                        <form action="<?php echo BASE_URL; ?>app/controllers/auth.php?action=login" method="POST" class="auth-form-content" id="login-form">
                            <h2 class="auth-title">Đăng Nhập</h2>
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="auth-input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" name="username" class="auth-input" placeholder="Tên đăng nhập" required>
                            </div>
                            <div class="auth-input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" class="auth-input" placeholder="Mật khẩu" required>
                            </div>
                            <button type="submit" name="login" value="1" class="auth-submit">Đăng Nhập</button>
                            <p class="auth-footer">Chưa có tài khoản? <a href="#" class="link" id="signup-link">Đăng ký ngay</a> | <a href="#" class="link" id="forgot-link">Quên mật khẩu?</a></p>
                        </form>
                        
                        <form action="<?php echo BASE_URL; ?>app/controllers/auth.php?action=register" method="POST" class="auth-form-content" id="signup-form" style="display:none;">
                            <h2 class="auth-title">Đăng Ký</h2>
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="auth-input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" name="username" class="auth-input" placeholder="Tên đăng nhập" required>
                            </div>
                            <div class="auth-input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" class="auth-input" placeholder="Email" required>
                            </div>
                            <div class="auth-input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" class="auth-input" placeholder="Mật khẩu" required minlength="8">
                            </div>
                            <div class="auth-input-group">
                                <i class="fas fa-user-tag"></i>
                                <select name="role" class="auth-input">
                                    <option value="student">Sinh viên</option>
                                    <option value="instructor">Giảng viên</option>
                                </select>
                            </div>
                            <button type="submit" name="register" value="1" class="auth-submit">Đăng Ký</button>
                            <p class="auth-footer">Đã có tài khoản? <a href="#" class="link" id="login-back-link">Đăng nhập</a></p>
                        </form>
                        
                        <form action="<?php echo BASE_URL; ?>app/controllers/auth.php?action=forgot" method="POST" class="auth-form-content" id="forgot-form" style="display:none;">
                            <h2 class="auth-title">Quên Mật Khẩu</h2>
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="auth-input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" class="auth-input" placeholder="Email" required>
                            </div>
                            <button type="submit" name="forgot_password" value="1" class="auth-submit">Gửi Yêu Cầu</button>
                            <p class="auth-footer">Nhớ mật khẩu? <a href="#" class="link" id="forgot-back-link">Quay lại đăng nhập</a></p>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="ai-bubble" class="ai-bubble">
        <div class="ai-logo">
            <svg class="message-icon" viewBox="0 0 1024 1024" fill="currentColor">
                <!-- SVG content giữ nguyên -->
            </svg>
        </div>
        <span class="bubble-text">BotEdu</span>
    </div>

    <div id="chat-widget" class="chat-widget minimized">
        <div class="chat-header">
            BotEdu
            <button id="chat-minimize" class="chat-minimize">×</button>
        </div>
        <div id="chat-messages" class="chat-messages">
            <div class="message bot-message">
                <div class="gpt-bubble">
                    <svg class="message-icon" viewBox="0 0 1024 1024" fill="currentColor">
                        <!-- SVG content giữ nguyên -->
                    </svg>
                    Chào bạn! Tôi là BotEdu, sẵn sàng hỗ trợ bạn trong học tập trực tuyến.
                    <span class="timestamp" data-timestamp=""></span>
                </div>
            </div>
            <div class="suggestions">
                <button class="suggestion-btn" data-question="Làm thế nào để đăng ký khóa học?">Đăng ký khóa học</button>
                <button class="suggestion-btn" data-question="Tôi có thể tìm tài liệu học ở đâu?">Tìm tài liệu học</button>
                <button class="suggestion-btn" data-question="Làm sao để liên hệ với giáo viên?">Liên hệ giáo viên</button>
                <button class="suggestion-btn" data-question="Tôi cần hỗ trợ kỹ thuật, bạn giúp được không?">Hỗ trợ kỹ thuật</button>
            </div>
        </div>
        <div class="chat-input-area">
            <form id="chat-form" method="post" enctype="multipart/form-data" action="<?php echo BASE_URL; ?>chatbot/processChat">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <label for="chat-files" class="upload-label">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 1a5 5 0 0 0-5 5v1h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a6 6 0 1 1 12 0v6a2.5 2.5 0 0 1-5 0V8a1 1 0 0 1 1-1h1V6a5 5 0 0 0-5-5z" />
                    </svg>
                    Tải file
                </label>
                <input type="file" id="chat-files" name="files[]" multiple />
                <input type="text" id="chat-input" name="prompt" placeholder="Nhập câu hỏi của bạn ở đây..." />
                <button type="submit" id="chat-send" class="chat-send">
                    <svg class="send-icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
                    </svg>
                    Gửi
                </button>
            </form>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>© 2025 Học Tập Trực Tuyến. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- THREE.js Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/three@0.160.1/build/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.160.1/examples/js/loaders/GLTFLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.160.1/examples/js/controls/OrbitControls.js"></script>
    
    <!-- Application Scripts -->
    <script src="<?php echo BASE_URL; ?>public/js/robot.js"></script>
    <script src="<?php echo BASE_URL; ?>public/js/script.js"></script>
    <script src="<?php echo BASE_URL; ?>public/js/login&&register.js"></script>
    <script src="<?php echo BASE_URL; ?>public/js/chatbot.js"></script>
</body>
</html>