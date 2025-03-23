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

// Include the AnalyticsController
require_once ROOT_DIR . '/app/controllers/AnalyticsController.php';
$analyticsController = new AnalyticsController($conn);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has instructor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'instructor') {
    // Set error message
    $_SESSION['error'] = "Bạn không có quyền truy cập trang này. Vui lòng đăng nhập với tài khoản giảng viên.";
    // Redirect to home page
    header("Location: " . BASE_URL . "app/views/product/home.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

// Check if course exists and belongs to the instructor
$stmt = $conn->prepare("SELECT title FROM courses WHERE course_id = ? AND instructor_id = ?");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    $_SESSION['error'] = "Không thể tải thông tin khóa học.";
    header("Location: instructor_dashboard.php");
    exit();
}

$stmt->bind_param("ii", $course_id, $user_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    $_SESSION['error'] = "Không thể tải thông tin khóa học.";
    header("Location: instructor_dashboard.php");
    exit();
}

$result = $stmt->get_result();
$course = $result->fetch_assoc();

if (!$course) {
    $_SESSION['error'] = "Khóa học không tồn tại hoặc bạn không có quyền truy cập.";
    header("Location: instructor_dashboard.php");
    exit();
}

// Get course statistics
$course_summary = $analyticsController->getCourseSummary($course_id);

// Get daily stats for the past 30 days by default, or use user selected date range
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

switch ($period) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'month':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        break;
    case 'quarter':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        break;
    case 'year':
        $start_date = date('Y-m-d', strtotime('-365 days'));
        break;
}

$daily_stats = $analyticsController->getDailyStats($course_id, $start_date, $end_date);

// Get lesson progress statistics
$lesson_stats = $analyticsController->getLessonProgressStats($course_id);

// Get engagement metrics - make it dynamic based on period
$engagement_data = $analyticsController->getEngagementMetrics($course_id, 'daily', 30);

// Get demographic data if available
$demographics = $analyticsController->getDemographicData($course_id);

// Get historical trend data for views and enrollments
$historical_data = $conn->prepare("
    SELECT 
        DATE_FORMAT(stat_date, '%Y-%m') as month,
        SUM(views) as total_views,
        SUM(enrollments) as total_enrollments,
        SUM(lesson_completions) as total_completions
    FROM course_daily_stats
    WHERE course_id = ?
    GROUP BY DATE_FORMAT(stat_date, '%Y-%m')
    ORDER BY month ASC
    LIMIT 12
");
$historical_data->bind_param("i", $course_id);
$historical_data->execute();
$history_result = $historical_data->get_result();

$months = [];
$monthly_views = [];
$monthly_enrollments = [];
$monthly_completions = [];

while ($month_data = $history_result->fetch_assoc()) {
    $months[] = date('M Y', strtotime($month_data['month'] . '-01'));
    $monthly_views[] = (int)$month_data['total_views'];
    $monthly_enrollments[] = (int)$month_data['total_enrollments'];
    $monthly_completions[] = (int)$month_data['total_completions'];
}

// Calculate trend percentages based on real data
$trend_view_percentage = 0;
$trend_enrollment_percentage = 0;
$trend_completion_percentage = 0;
$trend_rating_percentage = 0;

// If we have enough data to calculate trends
if (count($monthly_views) >= 2) {
    $last_month_idx = count($monthly_views) - 1;
    $prev_month_idx = count($monthly_views) - 2;
    
    if ($monthly_views[$prev_month_idx] > 0) {
        $trend_view_percentage = round((($monthly_views[$last_month_idx] - $monthly_views[$prev_month_idx]) / $monthly_views[$prev_month_idx]) * 100);
    }
    
    if ($monthly_enrollments[$prev_month_idx] > 0) {
        $trend_enrollment_percentage = round((($monthly_enrollments[$last_month_idx] - $monthly_enrollments[$prev_month_idx]) / $monthly_enrollments[$prev_month_idx]) * 100);
    }
    
    if ($monthly_completions[$prev_month_idx] > 0) {
        $trend_completion_percentage = round((($monthly_completions[$last_month_idx] - $monthly_completions[$prev_month_idx]) / $monthly_completions[$prev_month_idx]) * 100);
    }
}

// Get chart data from daily_stats
$dates = [];
$views = [];
$enrollments = [];
$completions = [];

foreach ($daily_stats as $stat) {
    $dates[] = $stat['stat_date'];
    $views[] = $stat['views'];
    $enrollments[] = $stat['enrollments'];
    $completions[] = $stat['lesson_completions'];
}

// Format data for charts
$dates_json = json_encode($dates);
$views_json = json_encode($views);
$enrollments_json = json_encode($enrollments);
$completions_json = json_encode($completions);

// Format monthly data for charts
$months_json = json_encode($months);
$monthly_views_json = json_encode($monthly_views);
$monthly_enrollments_json = json_encode($monthly_enrollments);
$monthly_completions_json = json_encode($monthly_completions);

// Update course daily stats for today
$analyticsController->updateDailyStats($course_id);

// Set page title and specific variables for header
$page_title = 'Phân Tích Khóa Học - ' . htmlspecialchars($course['title']);
$include_chart_js = true;
$additional_scripts = ['public/js/course_analytics.js'];
$page_specific_css = 'public/css/course_analytics.css';

// Include header
include_once ROOT_DIR . '/app/includes/header.php';
?>

<div class="analytics-page-container">
    <div class="course-nav">
        <a href="<?php echo BASE_URL; ?>app/views/product/instructor_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Trở Về Tổng Quan
        </a>
        <span class="separator">|</span>
        <a href="<?php echo BASE_URL; ?>app/views/product/course_management.php?course_id=<?php echo $course_id; ?>" class="back-link">
            <i class="fas fa-cog"></i> Quản Lý Khóa Học
        </a>
        <span class="separator">|</span>
        <a href="<?php echo BASE_URL; ?>app/views/product/lecture_management.php?course_id=<?php echo $course_id; ?>" class="back-link">
            <i class="fas fa-book"></i> Quản Lý Bài Giảng
        </a>
    </div>

    <div class="analytics-header animated-fade-in">
        <h2 class="animated-fade-in"><i class="fas fa-chart-line"></i> Phân Tích Dữ Liệu: <?php echo htmlspecialchars($course['title']); ?></h2>
        <div class="last-updated animated-fade-in-right">
            <div class="last-updated-info">
                <i class="fas fa-clock"></i> 
                <span>Cập nhật lần cuối: <strong><?php echo date('d/m/Y H:i'); ?></strong></span>
            </div>
            <button id="refresh-data" class="btn-refresh" title="Làm mới dữ liệu">
                <i class="fas fa-sync-alt"></i>
                <span class="refresh-text">Làm mới</span>
            </button>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="analytics-container">
        <div class="stats animated-fade-in delay-100">
            <div class="stat-card">
                <div class="stat-header">
                    <h3>Tổng Học Viên</h3>
                    <i class="fas fa-users"></i>
                </div>
                <p><?php echo number_format($course_summary['total_students'] ?? 0); ?></p>
                <div class="stat-trend <?php echo $trend_enrollment_percentage >= 0 ? 'up' : 'down'; ?>">
                    <i class="fas fa-arrow-<?php echo $trend_enrollment_percentage >= 0 ? 'up' : 'down'; ?>"></i> 
                    <?php echo abs($trend_enrollment_percentage); ?>% so với tháng trước
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <h3>Lượt Xem</h3>
                    <i class="fas fa-eye"></i>
                </div>
                <p><?php echo number_format($course_summary['total_views'] ?? 0); ?></p>
                <div class="stat-trend <?php echo $trend_view_percentage >= 0 ? 'up' : 'down'; ?>">
                    <i class="fas fa-arrow-<?php echo $trend_view_percentage >= 0 ? 'up' : 'down'; ?>"></i> 
                    <?php echo abs($trend_view_percentage); ?>% so với tháng trước
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <h3>Đánh Giá</h3>
                    <i class="fas fa-star"></i>
                </div>
                <p><?php echo number_format($course_summary['average_rating'] ?? 0, 1); ?> <small>(<?php echo $course_summary['review_count'] ?? 0; ?>)</small></p>
                <div class="stat-trend <?php echo $trend_rating_percentage >= 0 ? 'up' : 'down'; ?>">
                    <i class="fas fa-arrow-<?php echo $trend_rating_percentage >= 0 ? 'up' : 'down'; ?>"></i> 
                    <?php echo $trend_rating_percentage ? abs($trend_rating_percentage) . '%' : 'Không thay đổi'; ?> so với tháng trước
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <h3>Hoàn Thành</h3>
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <p>
                    <?php 
                    $completion_rate = 0;
                    if (($course_summary['total_students'] ?? 0) > 0) {
                        $completion_rate = round(($course_summary['completed_students'] ?? 0) / $course_summary['total_students'] * 100);
                    }
                    echo $completion_rate . '%';
                    ?>
                </p>
                <div class="stat-trend <?php echo $trend_completion_percentage >= 0 ? 'up' : 'down'; ?>">
                    <i class="fas fa-arrow-<?php echo $trend_completion_percentage >= 0 ? 'up' : 'down'; ?>"></i> 
                    <?php echo abs($trend_completion_percentage); ?>% so với tháng trước
                </div>
            </div>
        </div>
        
        <!-- Period Selection with Tab Layout -->
        <div class="period-selector animated-fade-in delay-100">
            <div class="period-selector-label">
                <i class="fas fa-calendar-alt"></i> Chọn khoảng thời gian:
            </div>
            <div class="period-buttons">
                <a href="?course_id=<?php echo $course_id; ?>&period=week" class="period-btn <?php echo $period == 'week' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-week"></i> 7 ngày
                </a>
                <a href="?course_id=<?php echo $course_id; ?>&period=month" class="period-btn <?php echo $period == 'month' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i> 30 ngày
                </a>
                <a href="?course_id=<?php echo $course_id; ?>&period=quarter" class="period-btn <?php echo $period == 'quarter' ? 'active' : ''; ?>">
                    <i class="far fa-calendar-alt"></i> 90 ngày
                </a>
                <a href="?course_id=<?php echo $course_id; ?>&period=year" class="period-btn <?php echo $period == 'year' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar"></i> 365 ngày
                </a>
                <a href="#" class="period-btn <?php echo isset($_GET['start_date']) ? 'active' : ''; ?>" id="custom-period">
                    <i class="fas fa-sliders-h"></i> Tùy chỉnh
                </a>
            </div>
        </div>
        
        <!-- Date Range Form (hidden by default unless custom dates are selected) -->
        <div class="date-range-form animated-fade-in delay-200" id="date-range-form" style="display: <?php echo isset($_GET['start_date']) ? 'block' : 'none'; ?>;">
            <h3><i class="fas fa-calendar-day"></i> Chọn Khoảng Thời Gian Tùy Chỉnh</h3>
            <form action="" method="GET" class="filter-controls">
                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                <div class="filter-control-group">
                    <label for="start-date">Từ ngày</label>
                    <div class="date-input-container">
                        <i class="fas fa-calendar-alt date-icon"></i>
                        <input type="date" id="start-date" name="start_date" class="form-control date-input" 
                            value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="filter-control-group">
                    <label for="end-date">Đến ngày</label>
                    <div class="date-input-container">
                        <i class="fas fa-calendar-alt date-icon"></i>
                        <input type="date" id="end-date" name="end_date" class="form-control date-input" 
                            value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Áp dụng</button>
                    <button type="button" id="cancel-custom-date" class="btn btn-secondary"><i class="fas fa-times"></i> Hủy</button>
                </div>
            </form>
        </div>
        
        <!-- Analytics Dashboard Tabs -->
        <div class="analytics-tabs animated-fade-in delay-200">
            <div class="tabs-header">
                <button class="tab-btn active" data-tab="overview"><i class="fas fa-chart-pie"></i> Tổng Quan</button>
                <button class="tab-btn" data-tab="traffic"><i class="fas fa-chart-line"></i> Lưu Lượng</button>
                <button class="tab-btn" data-tab="lessons"><i class="fas fa-tasks"></i> Bài Học</button>
                <button class="tab-btn" data-tab="demographics"><i class="fas fa-users"></i> Học Viên</button>
                <button class="tab-btn" data-tab="sources"><i class="fas fa-link"></i> Nguồn Ghi Danh</button>
            </div>
            
            <div class="tab-content-container">
                <!-- Overview Tab -->
                <div class="tab-content active" id="overview-tab">
                    <div class="charts-container">
                        <div class="chart-card animated-fade-in delay-100">
                            <h3><i class="fas fa-chart-line"></i> Xu Hướng Gần Đây</h3>
                            <div class="filter-controls">
                                <div class="filter-control-group">
                                    <label for="chart-metric">Hiển thị</label>
                                    <select id="chart-metric" class="form-select">
                                        <option value="views">Lượt Xem</option>
                                        <option value="enrollments">Đăng Ký Mới</option>
                                        <option value="completions">Hoàn Thành Bài Học</option>
                                    </select>
                                </div>
                            </div>
                            <canvas id="trendsChart" height="300"></canvas>
                        </div>
                        
                        <div class="chart-card animated-fade-in delay-200">
                            <h3><i class="fas fa-chart-bar"></i> Xu Hướng Hàng Tháng</h3>
                            <div class="filter-controls">
                                <div class="filter-control-group">
                                    <label for="monthly-chart-metric">Hiển thị</label>
                                    <select id="monthly-chart-metric" class="form-select">
                                        <option value="views">Lượt Xem</option>
                                        <option value="enrollments">Đăng Ký Mới</option>
                                        <option value="completions">Hoàn Thành Bài Học</option>
                                    </select>
                                </div>
                            </div>
                            <canvas id="monthlyTrendsChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Traffic Tab -->
                <div class="tab-content" id="traffic-tab">
                    <div class="chart-card animated-fade-in-left">
                        <h3><i class="fas fa-globe"></i> Nguồn Truy Cập</h3>
                        <div class="filter-controls">
                            <div class="filter-control-group">
                                <label for="traffic-source">Hiển thị theo</label>
                                <select id="traffic-source" class="form-select">
                                    <option value="all">Tất cả nguồn</option>
                                    <option value="search">Công cụ tìm kiếm</option>
                                    <option value="direct">Truy cập trực tiếp</option>
                                    <option value="referral">Trang giới thiệu</option>
                                    <option value="social">Mạng xã hội</option>
                                </select>
                            </div>
                        </div>
                        <canvas id="trafficSourcesChart" height="300"></canvas>
                    </div>
                    
                    <div class="chart-card animated-fade-in-right">
                        <h3><i class="fas fa-clock"></i> Thời Gian Xem</h3>
                        <canvas id="viewTimeChart" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Lessons Tab -->
                <div class="tab-content" id="lessons-tab">
                    <div class="chart-card animated-fade-in">
                        <h3><i class="fas fa-tasks"></i> Tiến Độ Bài Học</h3>
                        <div class="filter-controls lesson-filters">
                            <div class="filter-control-group">
                                <label for="lesson-sort">Sắp xếp theo</label>
                                <select id="lesson-sort" class="form-select">
                                    <option value="order">Thứ tự bài học</option>
                                    <option value="views">Lượt xem (cao đến thấp)</option>
                                    <option value="completion">Tỷ lệ hoàn thành (cao đến thấp)</option>
                                    <option value="progress">Tiến độ trung bình (cao đến thấp)</option>
                                </select>
                            </div>
                            <div class="filter-control-group search-group">
                                <label for="lesson-search">Tìm kiếm bài học</label>
                                <div class="search-input-container">
                                    <input type="text" id="lesson-search" class="form-control" placeholder="Nhập tên bài học...">
                                    <i class="fas fa-search search-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive lesson-table-container">
                            <table class="lesson-progress-table">
                                <thead>
                                    <tr>
                                        <th width="35%">Bài Học</th>
                                        <th class="text-center" width="15%">
                                            <span class="sort-header" data-sort="views">
                                                Lượt Xem <i class="fas fa-sort"></i>
                                            </span>
                                        </th>
                                        <th class="text-center" width="15%">
                                            <span class="sort-header" data-sort="completions">
                                                Hoàn Thành <i class="fas fa-sort"></i>
                                            </span>
                                        </th>
                                        <th class="text-center" width="15%">
                                            <span class="sort-header" data-sort="rate">
                                                Tỷ Lệ <i class="fas fa-sort"></i>
                                            </span>
                                        </th>
                                        <th width="20%">
                                            <span class="sort-header" data-sort="progress">
                                                Tiến Độ <i class="fas fa-sort"></i>
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($lesson_stats)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Chưa có dữ liệu về tiến độ bài học</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($lesson_stats as $lesson): ?>
                                        <tr class="lesson-row">
                                            <td>
                                                <div class="lesson-title">
                                                    <span class="lesson-icon">
                                                        <?php
                                                        $completionRate = 0;
                                                        if (($lesson['total_viewers'] ?? 0) > 0) {
                                                            $completionRate = round(($lesson['completions'] ?? 0) / $lesson['total_viewers'] * 100);
                                                        }
                                                        if ($completionRate >= 70):
                                                        ?>
                                                            <i class="fas fa-check-circle text-success"></i>
                                                        <?php elseif ($completionRate >= 30): ?>
                                                            <i class="fas fa-adjust text-warning"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-times-circle text-danger"></i>
                                                        <?php endif; ?>
                                                    </span>
                                                    <strong><?php echo htmlspecialchars($lesson['title']); ?></strong>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="stat-value"><?php echo number_format($lesson['total_viewers'] ?? 0); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="stat-value"><?php echo number_format($lesson['completions'] ?? 0); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php 
                                                $completion_rate = 0;
                                                if (($lesson['total_viewers'] ?? 0) > 0) {
                                                    $completion_rate = round(($lesson['completions'] ?? 0) / $lesson['total_viewers'] * 100);
                                                }
                                                ?>
                                                <span class="completion-badge completion-<?php echo $completion_rate < 30 ? 'low' : ($completion_rate < 70 ? 'medium' : 'high'); ?>">
                                                    <?php echo $completion_rate; ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress-bar-container">
                                                    <div class="progress-bar" style="width: <?php echo round($lesson['avg_progress'] ?? 0); ?>%"></div>
                                                    <span class="progress-label"><?php echo round($lesson['avg_progress'] ?? 0); ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-pagination" id="lesson-pagination">
                            <div class="pagination-info">
                                Hiển thị <span id="showing-records">1-10</span> trên <span id="total-records"><?php echo count($lesson_stats); ?></span> bài học
                            </div>
                            <div class="pagination-controls">
                                <button class="pagination-btn" id="prev-page" disabled>
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <div class="page-numbers" id="page-numbers">
                                    <span class="page-number active">1</span>
                                </div>
                                <button class="pagination-btn" id="next-page">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Demographics Tab -->
                <div class="tab-content" id="demographics-tab">
                    <div class="demographic-container animated-fade-in">
                        <div class="chart-card">
                            <h3><i class="fas fa-globe-americas"></i> Quốc Gia Hàng Đầu</h3>
                            <?php if (isset($demographics['countries']) && !empty($demographics['countries'])): ?>
                                <div id="world-map" style="height: 350px;"></div>
                                <div class="country-list">
                                    <table class="country-table">
                                        <thead>
                                            <tr>
                                                <th>Quốc Gia</th>
                                                <th>Học Viên</th>
                                                <th>Tỷ Lệ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            // Sort countries by student count (descending)
                                            arsort($demographics['countries']);
                                            
                                            // Calculate total students
                                            $totalStudents = array_sum($demographics['countries']);
                                            
                                            // Display top 10 countries
                                            $count = 0;
                                            foreach ($demographics['countries'] as $country => $students): 
                                                $count++;
                                                if ($count > 10) break;
                                                $percentage = ($students / $totalStudents) * 100;
                                                $countryCode = getCountryCode($country);
                                            ?>
                                                <tr>
                                                    <td>
                                                        <?php if ($countryCode): ?>
                                                            <span class="country-flag fi fi-<?php echo strtolower($countryCode); ?>"></span>
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($country); ?>
                                                    </td>
                                                    <td><?php echo number_format($students); ?></td>
                                                    <td><?php echo round($percentage, 1); ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="map-error">
                                    <i class="fas fa-map-marked-alt"></i>
                                    <p>Không có dữ liệu quốc gia để hiển thị.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="charts-container">
                            <div class="chart-card animated-fade-in-left">
                                <h3><i class="fas fa-users"></i> Phân Bố Độ Tuổi</h3>
                                <?php if (isset($demographics['age_ranges']) && !empty($demographics['age_ranges'])): ?>
                                    <canvas id="ageChart" height="300"></canvas>
                                <?php else: ?>
                                    <div class="no-data">
                                        <i class="fas fa-chart-pie"></i>
                                        <p>Không có dữ liệu độ tuổi để hiển thị.</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="chart-card animated-fade-in-right">
                                <h3><i class="fas fa-venus-mars"></i> Phân Bố Giới Tính</h3>
                                <?php if (isset($demographics['genders']) && !empty($demographics['genders'])): ?>
                                    <canvas id="genderChart" height="300"></canvas>
                                <?php else: ?>
                                    <div class="no-data">
                                        <i class="fas fa-chart-pie"></i>
                                        <p>Không có dữ liệu giới tính để hiển thị.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sources Tab -->
                <div class="tab-content" id="sources-tab">
                    <div class="enrollment-sources-container animated-fade-in">
                        <div class="chart-card">
                            <h3><i class="fas fa-link"></i> Nguồn Ghi Danh</h3>
                            <?php if (isset($enrollment_sources) && !empty($enrollment_sources)): ?>
                                <canvas id="enrollmentSourcesChart" height="350"></canvas>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-chart-pie"></i>
                                    <p>Không có dữ liệu nguồn ghi danh để hiển thị.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Export and Share Section -->
        <div class="analytics-actions animated-fade-in delay-400">
            <div class="actions-header">
                <i class="fas fa-file-export"></i> Tùy chọn báo cáo
            </div>
            <div class="actions-content">
                <div class="export-options">
                    <button class="btn btn-action dropdown-toggle" id="export-options-btn">
                        <i class="fas fa-file-export"></i> Xuất Báo Cáo
                    </button>
                    <div class="export-dropdown" id="export-dropdown">
                        <a href="#" id="export-pdf" class="export-option">
                            <i class="fas fa-file-pdf"></i> Xuất PDF
                        </a>
                        <a href="#" id="export-csv" class="export-option">
                            <i class="fas fa-file-csv"></i> Xuất CSV
                        </a>
                        <a href="#" id="export-excel" class="export-option">
                            <i class="fas fa-file-excel"></i> Xuất Excel
                        </a>
                        <a href="#" id="export-print" class="export-option">
                            <i class="fas fa-print"></i> In báo cáo
                        </a>
                    </div>
                </div>
                <button class="btn btn-action" id="share-report">
                    <i class="fas fa-share-alt"></i> Chia Sẻ
                </button>
            </div>
            <div class="share-modal" id="share-modal">
                <div class="share-modal-content animated-fade-in">
                    <div class="share-modal-header">
                        <h3><i class="fas fa-share-alt"></i> Chia Sẻ Báo Cáo</h3>
                        <button class="share-modal-close" id="share-modal-close"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="share-modal-body">
                        <div class="share-options">
                            <div class="share-option">
                                <label for="report-url">Đường dẫn báo cáo:</label>
                                <div class="copy-url-container">
                                    <input type="text" id="report-url" class="report-url" value="<?php 
                                        // Create an absolute URL
                                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                                        $host = $_SERVER['HTTP_HOST'];
                                        $path = BASE_URL . 'app/views/product/course_analytics.php';
                                        $token = md5($course_id . $user_id . date('Ymd'));
                                        echo "{$protocol}://{$host}{$path}?course_id={$course_id}&token={$token}";
                                    ?>" readonly>
                                    <button id="copy-url-btn" class="copy-url-btn"><i class="fas fa-copy"></i></button>
                                </div>
                                <p class="url-help-text">Đường dẫn này có hiệu lực trong 24 giờ</p>
                            </div>
                            <div class="share-option">
                                <label>Chia sẻ qua email:</label>
                                <div class="email-share-form">
                                    <input type="email" id="share-email" placeholder="Nhập địa chỉ email" class="share-email">
                                    <button id="send-email-btn" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Gửi
                                    </button>
                                </div>
                            </div>
                            <div class="share-option">
                                <label>Chia sẻ qua mạng xã hội:</label>
                                <div class="social-share-buttons">
                                    <button class="social-btn facebook" title="Chia sẻ lên Facebook"><i class="fab fa-facebook-f"></i></button>
                                    <button class="social-btn twitter" title="Chia sẻ lên Twitter"><i class="fab fa-twitter"></i></button>
                                    <button class="social-btn linkedin" title="Chia sẻ lên LinkedIn"><i class="fab fa-linkedin-in"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container for Notifications -->
    <div class="toast-container" id="toast-container"></div>
</div>

<script>
    // Pass PHP data to JavaScript
    const courseId = <?= $course_id ?>;
    const dateLabels = <?= $dates_json ?: '[]' ?>;
    const viewsData = <?= $views_json ?: '[]' ?>;
    const enrollmentsData = <?= $enrollments_json ?: '[]' ?>;
    const completionsData = <?= $completions_json ?: '[]' ?>;
    const baseUrl = '<?= BASE_URL ?>';
    
    // Monthly data
    const monthLabels = <?= $months_json ?: '[]' ?>;
    const monthlyViewsData = <?= $monthly_views_json ?: '[]' ?>;
    const monthlyEnrollmentsData = <?= $monthly_enrollments_json ?: '[]' ?>;
    const monthlyCompletionsData = <?= $monthly_completions_json ?: '[]' ?>;
    
    // Tab navigation
    document.addEventListener('DOMContentLoaded', function() {
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons and contents
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                
                // Add active class to clicked button and corresponding content
                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId + '-tab').classList.add('active');
            });
        });
        
        // Refresh data button functionality
        document.getElementById('refresh-data').addEventListener('click', function() {
            this.classList.add('spin');
            
            // Construct absolute URL for API endpoint
            const protocol = window.location.protocol;
            const host = window.location.host;
            const apiPath = baseUrl + 'app/controllers/AnalyticsController.php';
            const apiUrl = `${protocol}//${host}${apiPath}?action=updateDailyStats&course_id=${courseId}`;
            
            // Send AJAX request to refresh data
            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        showToast('Dữ liệu đã được cập nhật thành công!', 'success');
                        
                        // Reload the page after a slight delay to show updated data
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showToast('Không thể cập nhật dữ liệu. Vui lòng thử lại sau.', 'error');
                        this.classList.remove('spin');
                    }
                })
                .catch(error => {
                    console.error('Error refreshing data:', error);
                    showToast('Đã xảy ra lỗi khi cập nhật dữ liệu.', 'error');
                    this.classList.remove('spin');
                });
        });
        
        // Lesson sort functionality
        document.getElementById('lesson-sort').addEventListener('change', function() {
            const sortValue = this.value;
            const tableBody = document.querySelector('.lesson-progress-table tbody');
            const rows = Array.from(tableBody.querySelectorAll('tr'));
            
            if (rows.length <= 1) return; // Don't sort if no data or only one row
            
            rows.sort((a, b) => {
                let valA, valB;
                
                switch(sortValue) {
                    case 'views':
                        valA = parseInt(a.cells[1].textContent.replace(/,/g, ''));
                        valB = parseInt(b.cells[1].textContent.replace(/,/g, ''));
                        return valB - valA; // Descending order
                    case 'completion':
                        valA = parseInt(a.cells[2].textContent.replace(/,/g, ''));
                        valB = parseInt(b.cells[2].textContent.replace(/,/g, ''));
                        return valB - valA; // Descending order
                    case 'progress':
                        valA = parseInt(a.cells[4].querySelector('.text-end').textContent);
                        valB = parseInt(b.cells[4].querySelector('.text-end').textContent);
                        return valB - valA; // Descending order
                    default: 
                        // Original order - nothing to do as rows are already in that order
                        return 0;
                }
            });
            
            // Clear and re-append rows in new order
            if (sortValue !== 'order') {
                rows.forEach(row => tableBody.appendChild(row));
            }
        });
        
        // Export dropdown toggle
        const exportOptionsBtn = document.getElementById('export-options-btn');
        const exportDropdown = document.getElementById('export-dropdown');
        
        exportOptionsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            exportDropdown.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#export-options-btn') && !e.target.closest('#export-dropdown')) {
                exportDropdown.classList.remove('show');
            }
        });
        
        // Export PDF functionality
        document.getElementById('export-pdf').addEventListener('click', function(e) {
            e.preventDefault();
            showToast('Đang chuẩn bị xuất PDF...', 'info');
            
            // Construct absolute URL for API endpoint
            const protocol = window.location.protocol;
            const host = window.location.host;
            const apiPath = baseUrl + 'app/controllers/AnalyticsController.php';
            const pdfUrl = `${protocol}//${host}${apiPath}?action=exportPDF&course_id=${courseId}`;
            
            // Simulate PDF generation with timeout
            setTimeout(() => {
                showToast('File PDF đã sẵn sàng tải xuống!', 'success');
                
                // Create a dummy link to simulate download
                const link = document.createElement('a');
                link.href = pdfUrl;
                link.setAttribute('download', `analytic_report_${courseId}.pdf`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }, 2000);
        });
        
        // Export CSV functionality
        document.getElementById('export-csv').addEventListener('click', function(e) {
            e.preventDefault();
            showToast('Đang chuẩn bị xuất CSV...', 'info');
            
            // Export CSV file
            exportTableToCSV('analytics_report.csv');
        });
        
        // Export Excel functionality
        document.getElementById('export-excel').addEventListener('click', function(e) {
            e.preventDefault();
            showToast('Đang chuẩn bị xuất Excel...', 'info');
            
            // Construct absolute URL for API endpoint
            const protocol = window.location.protocol;
            const host = window.location.host;
            const apiPath = baseUrl + 'app/controllers/AnalyticsController.php';
            const excelUrl = `${protocol}//${host}${apiPath}?action=exportExcel&course_id=${courseId}`;
            
            // Simulate Excel generation with timeout
            setTimeout(() => {
                showToast('File Excel đã sẵn sàng tải xuống!', 'success');
                
                // Create a dummy link to simulate download
                const link = document.createElement('a');
                link.href = excelUrl;
                link.setAttribute('download', `analytic_report_${courseId}.xlsx`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }, 1500);
        });
        
        // Print report functionality
        document.getElementById('export-print').addEventListener('click', function(e) {
            e.preventDefault();
            window.print();
        });
        
        // Share functionality
        const shareReportBtn = document.getElementById('share-report');
        const shareModal = document.getElementById('share-modal');
        const shareModalClose = document.getElementById('share-modal-close');
        
        shareReportBtn.addEventListener('click', function(e) {
            e.preventDefault();
            shareModal.style.display = 'flex';
        });
        
        shareModalClose.addEventListener('click', function() {
            shareModal.style.display = 'none';
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target === shareModal) {
                shareModal.style.display = 'none';
            }
        });
        
        // Copy URL functionality
        document.getElementById('copy-url-btn').addEventListener('click', function() {
            const urlInput = document.getElementById('report-url');
            urlInput.select();
            document.execCommand('copy');
            
            // Show success message
            showToast('Đã sao chép đường dẫn!', 'success');
        });
        
        // Email sharing functionality
        document.getElementById('send-email-btn').addEventListener('click', function() {
            const emailInput = document.getElementById('share-email');
            const email = emailInput.value.trim();
            
            if (!email) {
                showToast('Vui lòng nhập địa chỉ email!', 'error');
                return;
            }
            
            if (!isValidEmail(email)) {
                showToast('Địa chỉ email không hợp lệ!', 'error');
                return;
            }
            
            // Show sending message
            showToast('Đang gửi email...', 'info');
            
            // Simulate sending email
            setTimeout(() => {
                showToast('Đã gửi báo cáo thành công!', 'success');
                emailInput.value = '';
            }, 1500);
        });
        
        // Social share buttons
        document.querySelectorAll('.social-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const platform = this.classList.contains('facebook') ? 'Facebook' : 
                                 this.classList.contains('twitter') ? 'Twitter' : 'LinkedIn';
                
                showToast(`Đang mở cửa sổ chia sẻ ${platform}...`, 'info');
                
                // Get share URL
                const shareUrl = document.getElementById('report-url').value;
                const title = `Báo cáo phân tích khóa học: ${document.querySelector('h2').textContent.split(':')[1].trim()}`;
                
                // Open appropriate share dialog based on platform
                let url;
                if (this.classList.contains('facebook')) {
                    url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`;
                } else if (this.classList.contains('twitter')) {
                    url = `https://twitter.com/intent/tweet?url=${encodeURIComponent(shareUrl)}&text=${encodeURIComponent(title)}`;
                } else {
                    url = `https://www.linkedin.com/shareArticle?mini=true&url=${encodeURIComponent(shareUrl)}&title=${encodeURIComponent(title)}`;
                }
                
                window.open(url, '_blank', 'width=600,height=400');
            });
        });
        
        // Check if map loading fails and show fallback
        setTimeout(() => {
            const worldMap = document.getElementById('world-map');
            if (worldMap && worldMap.children.length === 0) {
                document.getElementById('country-map-error').style.display = 'flex';
                worldMap.style.display = 'none';
            }
        }, 2000);
    });
    
    // Helper functions for export and sharing
    
    // Validate email format
    function isValidEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
    
    // Export table data to CSV
    function exportTableToCSV(filename) {
        // Get the active tab
        const activeTab = document.querySelector('.tab-content.active');
        const tables = activeTab.querySelectorAll('table');
        
        if (tables.length === 0) {
            showToast('Không có dữ liệu bảng để xuất file!', 'error');
            return;
        }
        
        let csvContent = [];
        
        // Process each table in the active tab
        tables.forEach(table => {
            const rows = table.querySelectorAll('tr');
            
            // Get table header
            const headerRow = table.querySelector('thead tr');
            if (headerRow) {
                const headers = Array.from(headerRow.querySelectorAll('th')).map(th => 
                    `"${th.textContent.trim().replace(/"/g, '""')}"`
                );
                csvContent.push(headers.join(','));
            }
            
            // Get table body rows
            const bodyRows = table.querySelectorAll('tbody tr');
            bodyRows.forEach(row => {
                const rowData = Array.from(row.querySelectorAll('td')).map(cell => {
                    // Clean the text content to remove any HTML and trim whitespace
                    let cellText = cell.textContent.trim().replace(/"/g, '""');
                    // Remove percentage signs and other symbols for numeric columns
                    cellText = cellText.replace(/[%,]/g, '');
                    return `"${cellText}"`;
                });
                csvContent.push(rowData.join(','));
            });
            
            // Add an empty row between tables
            csvContent.push('');
        });
        
        // Create and trigger download
        const csvString = csvContent.join('\n');
        const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
        
        if (navigator.msSaveBlob) { // IE 10+
            navigator.msSaveBlob(blob, filename);
        } else {
            const link = document.createElement('a');
            if (link.download !== undefined) {
                // Feature detection for download attribute
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
                showToast('Tải xuống CSV thành công!', 'success');
            } else {
                showToast('Trình duyệt của bạn không hỗ trợ tải xuống file. Vui lòng thử lại với trình duyệt khác.', 'error');
            }
        }
    }
    
    // Show toast message
    function showToast(message, type = 'info') {
        // Check if toast container exists
        let toastContainer = document.querySelector('.toast-container');
        
        // Create container if it doesn't exist
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container';
            document.body.appendChild(toastContainer);
        }
        
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        // Add appropriate icon based on type
        let icon = 'info-circle';
        if (type === 'success') icon = 'check-circle';
        if (type === 'error') icon = 'exclamation-circle';
        if (type === 'warning') icon = 'exclamation-triangle';
        
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas fa-${icon}"></i>
            </div>
            <div class="toast-message">${message}</div>
        `;
        
        // Add to container
        toastContainer.appendChild(toast);
        
        // Show toast with animation
        setTimeout(() => toast.classList.add('show'), 10);
        
        // Remove after delay
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    // Custom date range toggle
    document.getElementById('custom-period').addEventListener('click', function(e) {
        e.preventDefault();
        const dateRangeForm = document.getElementById('date-range-form');
        
        if (dateRangeForm.style.display === 'none' || dateRangeForm.style.display === '') {
            dateRangeForm.style.display = 'block';
        } else {
            dateRangeForm.style.display = 'none';
        }
    });
    
    document.getElementById('cancel-custom-date').addEventListener('click', function() {
        document.getElementById('date-range-form').style.display = 'none';
    });
    
    // Date range validation and enhancement
    const startDateInput = document.getElementById('start-date');
    const endDateInput = document.getElementById('end-date');

    // Set min date on end date based on start date
    startDateInput.addEventListener('change', function() {
        endDateInput.min = this.value;
        
        // If end date is now before start date, update it
        if (endDateInput.value && endDateInput.value < this.value) {
            endDateInput.value = this.value;
        }
    });

    // Set max date on start date based on end date
    endDateInput.addEventListener('change', function() {
        if (this.value) {
            startDateInput.max = this.value;
            
            // If start date is now after end date, update it
            if (startDateInput.value && startDateInput.value > this.value) {
                startDateInput.value = this.value;
            }
        } else {
            // If end date is cleared, reset max to today
            startDateInput.max = '<?php echo date('Y-m-d'); ?>';
        }
    });
    
    <?php if (isset($demographics['age_ranges']) && !empty($demographics['age_ranges'])): ?>
    const ageLabels = [
        <?php foreach ($demographics['age_ranges'] as $range => $count): ?>
            '<?= $range ?>',
        <?php endforeach; ?>
    ];
    const ageData = [
        <?php foreach ($demographics['age_ranges'] as $count): ?>
            <?= $count ?>,
        <?php endforeach; ?>
    ];
    <?php endif; ?>
    
    <?php if (isset($demographics['gender_distribution']) && !empty($demographics['gender_distribution'])): ?>
    const genderLabels = [
        <?php foreach ($demographics['gender_distribution'] as $gender => $count): ?>
            '<?= $gender ?>',
        <?php endforeach; ?>
    ];
    const genderData = [
        <?php foreach ($demographics['gender_distribution'] as $count): ?>
            <?= $count ?>,
        <?php endforeach; ?>
    ];
    <?php endif; ?>
    
    // Get country data for the map
    <?php if (isset($demographics['country_distribution']) && !empty($demographics['country_distribution'])): ?>
    const countriesData = {
        <?php foreach ($demographics['country_distribution'] as $country => $count): 
            // Convert country names to ISO codes (simplified example)
            $countryCode = getCountryCode($country);
            if ($countryCode): 
        ?>
        '<?= $countryCode ?>': <?= $count ?>,
        <?php endif; endforeach; ?>
    };
    <?php else: ?>
    const countriesData = {};
    <?php endif; ?>
    
    // Enrollment sources
    const sourceLabels = ['Tìm kiếm', 'Trực tiếp', 'Mạng xã hội', 'Email', 'Giới thiệu'];
    const sourceData = [35, 25, 20, 10, 10]; // Placeholder data - would come from real DB
</script>

<?php
// Helper function to convert country names to ISO codes
function getCountryCode($countryName) {
    // This is a simplified mapping - in production you'd use a complete list
    $countryCodes = [
        'Việt Nam' => 'VN',
        'United States' => 'US',
        'Mỹ' => 'US',
        'Japan' => 'JP',
        'Nhật Bản' => 'JP',
        'China' => 'CN',
        'Trung Quốc' => 'CN',
        'Singapore' => 'SG',
        'South Korea' => 'KR',
        'Hàn Quốc' => 'KR',
        'Thailand' => 'TH',
        'Thái Lan' => 'TH',
        'Malaysia' => 'MY',
        'Indonesia' => 'ID',
        'Philippines' => 'PH',
        'Australia' => 'AU',
        'Úc' => 'AU',
        'India' => 'IN',
        'Ấn Độ' => 'IN',
        'United Kingdom' => 'GB',
        'Anh' => 'GB',
        'France' => 'FR',
        'Pháp' => 'FR',
        'Germany' => 'DE',
        'Đức' => 'DE',
        'Canada' => 'CA',
        'Brazil' => 'BR',
        'Russia' => 'RU',
        'Nga' => 'RU'
        // Add more mappings as needed
    ];
    
    return isset($countryCodes[$countryName]) ? $countryCodes[$countryName] : '';
}

// Include footer
include_once ROOT_DIR . '/app/includes/footer.php';
?> 