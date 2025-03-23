<?php
/**
 * Analytics Controller
 * Handles course analytics data retrieval and recording
 */
class AnalyticsController {
    private $conn;
    
    /**
     * Constructor
     * 
     * @param mysqli $conn Database connection object
     */
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Get summary statistics for a course
     * 
     * @param int $course_id Course ID
     * @return array Course summary data
     */
    public function getCourseSummary($course_id) {
        $course_id = intval($course_id);
        $summary = [];
        
        // Get total students enrolled
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total_students FROM enrollments WHERE course_id = ?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $summary['total_students'] = $row['total_students'];
        
        // Get completed students (who have completed all lessons)
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT e.user_id) as completed_students
            FROM enrollments e
            WHERE e.course_id = ? 
            AND (
                SELECT COUNT(*) 
                FROM lessons l 
                WHERE l.course_id = e.course_id
            ) = (
                SELECT COUNT(*) 
                FROM lecture_progress lp
                JOIN lessons l ON lp.lecture_id = l.lesson_id
                WHERE l.course_id = e.course_id 
                AND lp.user_id = e.user_id 
                AND lp.is_completed = 1
            )
        ");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $summary['completed_students'] = $row['completed_students'];
        
        // Get review count and average rating
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS review_count, AVG(rating) AS average_rating 
            FROM reviews 
            WHERE course_id = ?
        ");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $summary['review_count'] = $row['review_count'];
        $summary['average_rating'] = $row['average_rating'];
        
        // Get total lessons
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total_lessons FROM lessons WHERE course_id = ?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $summary['total_lessons'] = $row['total_lessons'];
        
        // Get course price
        $stmt = $this->conn->prepare("SELECT price FROM courses WHERE course_id = ?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $summary['price'] = $row['price'];
        
        // Get view statistics
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS total_views, 
                   COUNT(DISTINCT user_id) AS unique_viewers
            FROM course_views 
            WHERE course_id = ?
        ");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $summary['total_views'] = $row['total_views'];
        $summary['unique_viewers'] = $row['unique_viewers'];
        
        return $summary;
    }
    
    /**
     * Get daily statistics for a course over a date range
     * 
     * @param int $course_id Course ID
     * @param string $start_date Optional start date (YYYY-MM-DD)
     * @param string $end_date Optional end date (YYYY-MM-DD)
     * @return array Daily stats
     */
    public function getDailyStats($course_id, $start_date = null, $end_date = null) {
        $course_id = intval($course_id);
        
        // Default to last 30 days if no dates provided
        if (!$start_date) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }
        
        $stats = [];
        
        // Check if there are any stats for this course
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as stat_count 
            FROM course_daily_stats 
            WHERE course_id = ?
        ");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // If no stats exist, generate some demo data for display
        if ($row['stat_count'] == 0) {
            // Generate demo data for the requested period
            $current_date = new DateTime($start_date);
            $end = new DateTime($end_date);
            
            while ($current_date <= $end) {
                $date_str = $current_date->format('Y-m-d');
                
                // Generate random stats that look somewhat realistic
                $views = mt_rand(5, 30);
                $unique_viewers = mt_rand(ceil($views * 0.6), $views);
                $enrollments = mt_rand(0, ceil($views * 0.2));
                $lesson_completions = mt_rand(0, ceil($views * 0.3));
                
                $stats[] = [
                    'stat_date' => $date_str,
                    'views' => $views,
                    'unique_viewers' => $unique_viewers,
                    'enrollments' => $enrollments,
                    'lesson_completions' => $lesson_completions
                ];
                
                $current_date->modify('+1 day');
            }
            
            return $stats;
        }
        
        // Get real stats from database
        $stmt = $this->conn->prepare("
            SELECT 
                stat_date, 
                views, 
                unique_viewers, 
                enrollments, 
                lesson_completions
            FROM course_daily_stats 
            WHERE course_id = ? 
            AND stat_date BETWEEN ? AND ?
            ORDER BY stat_date
        ");
        $stmt->bind_param("iss", $course_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        
        return $stats;
    }
    
    /**
     * Get lesson progress statistics for a course
     * 
     * @param int $course_id Course ID
     * @return array Lesson progress stats
     */
    public function getLessonProgressStats($course_id) {
        $course_id = intval($course_id);
        $stats = [];
        
        // Get lesson progress stats
        $stmt = $this->conn->prepare("
            SELECT 
                l.lesson_id,
                l.title,
                COUNT(DISTINCT lp.user_id) AS total_viewers,
                SUM(CASE WHEN lp.is_completed = 1 THEN 1 ELSE 0 END) AS completions,
                AVG(lp.progress_percentage) AS avg_progress
            FROM lessons l
            LEFT JOIN lecture_progress lp ON l.lesson_id = lp.lecture_id
            WHERE l.course_id = ?
            GROUP BY l.lesson_id
            ORDER BY l.order_index
        ");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        
        // If no data exists yet, generate some demo data
        if (empty($stats)) {
            // Get lessons for this course
            $stmt = $this->conn->prepare("
                SELECT lesson_id, title 
                FROM lessons 
                WHERE course_id = ? 
                ORDER BY order_index
            ");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                // Generate random stats
                $total_viewers = mt_rand(10, 50);
                $completions = mt_rand(0, $total_viewers);
                $avg_progress = mt_rand($completions * 2, 100);
                
                $stats[] = [
                    'lesson_id' => $row['lesson_id'],
                    'title' => $row['title'],
                    'total_viewers' => $total_viewers,
                    'completions' => $completions,
                    'avg_progress' => $avg_progress
                ];
            }
        }
        
        return $stats;
    }
    
    /**
     * Get engagement metrics for a course
     * 
     * @param int $course_id Course ID
     * @param string $period Period to group by (daily, weekly, monthly)
     * @param int $limit Number of data points to return
     * @return array Engagement metrics
     */
    public function getEngagementMetrics($course_id, $period = 'daily', $limit = 30) {
        $course_id = intval($course_id);
        $metrics = [];
        
        // Define the date format based on the period
        $date_format = '';
        $group_by = '';
        
        switch ($period) {
            case 'weekly':
                $date_format = '%Y-%u'; // Year and week number
                $group_by = "DATE_FORMAT(engagement_date, '%Y-%u')";
                break;
            case 'monthly':
                $date_format = '%Y-%m'; // Year and month
                $group_by = "DATE_FORMAT(engagement_date, '%Y-%m')";
                break;
            case 'daily':
            default:
                $date_format = '%Y-%m-%d'; // Year, month, day
                $group_by = "DATE(engagement_date)";
                break;
        }
        
        // Check if there's any engagement data
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM course_engagement 
            WHERE course_id = ?
        ");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // If no data exists, generate some demo data
        if ($row['count'] == 0) {
            // Generate demo data
            $end_date = new DateTime();
            $current_date = clone $end_date;
            
            switch ($period) {
                case 'weekly':
                    $current_date->modify('-' . ($limit * 7) . ' days');
                    $interval = 'P7D'; // 7 days
                    break;
                case 'monthly':
                    $current_date->modify('-' . $limit . ' months');
                    $interval = 'P1M'; // 1 month
                    break;
                case 'daily':
                default:
                    $current_date->modify('-' . $limit . ' days');
                    $interval = 'P1D'; // 1 day
                    break;
            }
            
            while ($current_date <= $end_date) {
                $date_key = $current_date->format('Y-m-d');
                
                // Generate random engagement counts
                $comment_count = mt_rand(0, 5);
                $question_count = mt_rand(0, 3);
                $note_count = mt_rand(0, 7);
                $download_count = mt_rand(0, 10);
                $bookmark_count = mt_rand(0, 4);
                $share_count = mt_rand(0, 2);
                
                $metrics[] = [
                    'date_key' => $date_key,
                    'comment_count' => $comment_count,
                    'question_count' => $question_count,
                    'note_count' => $note_count,
                    'download_count' => $download_count,
                    'bookmark_count' => $bookmark_count,
                    'share_count' => $share_count,
                    'total' => $comment_count + $question_count + $note_count + $download_count + $bookmark_count + $share_count
                ];
                
                // Increment date by interval
                $current_date->add(new DateInterval($interval));
            }
            
            return $metrics;
        }
        
        // Get real engagement metrics from database
        $stmt = $this->conn->prepare("
            SELECT 
                $group_by AS date_key,
                SUM(CASE WHEN engagement_type = 'comment' THEN 1 ELSE 0 END) AS comment_count,
                SUM(CASE WHEN engagement_type = 'question' THEN 1 ELSE 0 END) AS question_count,
                SUM(CASE WHEN engagement_type = 'note' THEN 1 ELSE 0 END) AS note_count,
                SUM(CASE WHEN engagement_type = 'download' THEN 1 ELSE 0 END) AS download_count,
                SUM(CASE WHEN engagement_type = 'bookmark' THEN 1 ELSE 0 END) AS bookmark_count,
                SUM(CASE WHEN engagement_type = 'share' THEN 1 ELSE 0 END) AS share_count,
                COUNT(*) AS total
            FROM course_engagement
            WHERE course_id = ?
            GROUP BY date_key
            ORDER BY date_key DESC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $course_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $metrics[] = $row;
        }
        
        return $metrics;
    }
    
    /**
     * Get demographic data for a course
     * 
     * @param int $course_id Course ID
     * @return array|null Demographic data or null if not available
     */
    public function getDemographicData($course_id) {
        $course_id = intval($course_id);
        
        // Get the latest demographic data
        $stmt = $this->conn->prepare("
            SELECT 
                age_ranges,
                gender_distribution,
                country_distribution,
                experience_level
            FROM course_demographics
            WHERE course_id = ?
            ORDER BY snapshot_date DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Parse JSON fields
            $demographics = [
                'age_ranges' => json_decode($row['age_ranges'], true),
                'gender_distribution' => json_decode($row['gender_distribution'], true),
                'country_distribution' => json_decode($row['country_distribution'], true),
                'experience_level' => json_decode($row['experience_level'], true)
            ];
            
            return $demographics;
        }
        
        // If no data exists, generate some demo data
        $demographics = [
            'age_ranges' => [
                '18-24' => mt_rand(5, 20),
                '25-34' => mt_rand(15, 40),
                '35-44' => mt_rand(10, 30),
                '45-54' => mt_rand(5, 15),
                '55+' => mt_rand(2, 10)
            ],
            'gender_distribution' => [
                'Nam' => mt_rand(40, 70),
                'Nữ' => mt_rand(30, 60),
                'Khác' => mt_rand(1, 5)
            ],
            'country_distribution' => [
                'Việt Nam' => mt_rand(50, 100),
                'Hoa Kỳ' => mt_rand(5, 20),
                'Nhật Bản' => mt_rand(3, 15),
                'Hàn Quốc' => mt_rand(2, 10),
                'Singapore' => mt_rand(1, 8),
                'Khác' => mt_rand(1, 10)
            ],
            'experience_level' => [
                'Mới bắt đầu' => mt_rand(20, 40),
                'Trung cấp' => mt_rand(30, 50),
                'Nâng cao' => mt_rand(10, 30),
                'Chuyên gia' => mt_rand(1, 10)
            ]
        ];
        
        return $demographics;
    }
    
    /**
     * Record a view of a course
     * 
     * @param int $course_id Course ID
     * @param int|null $user_id User ID (optional)
     * @param string $device_type Device type (optional)
     * @param string $source Traffic source (optional)
     * @return bool Success status
     */
    public function recordCourseView($course_id, $user_id = null, $device_type = null, $source = null) {
        $course_id = intval($course_id);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Prepare statement
        $stmt = $this->conn->prepare("
            INSERT INTO course_views (
                course_id, 
                user_id, 
                view_date, 
                ip_address, 
                device_type, 
                source
            ) VALUES (?, ?, NOW(), ?, ?, ?)
        ");
        
        $stmt->bind_param("iisss", $course_id, $user_id, $ip_address, $device_type, $source);
        $result = $stmt->execute();
        
        // Update daily stats for today
        if ($result) {
            $this->updateDailyStats($course_id);
        }
        
        return $result;
    }
    
    /**
     * Call the stored procedure to update daily statistics for a course
     * 
     * @param int $course_id Course ID
     * @param string|null $stat_date Date to update (default: today)
     * @return bool Success status
     */
    public function updateDailyStats($course_id, $stat_date = null) {
        $course_id = intval($course_id);
        
        if (!$stat_date) {
            $stat_date = date('Y-m-d');
        }
        
        // Call the stored procedure
        $stmt = $this->conn->prepare("CALL update_course_daily_stats(?, ?)");
        $stmt->bind_param("is", $course_id, $stat_date);
        return $stmt->execute();
    }
    
    /**
     * Record or update progress of a lesson for a user
     * 
     * @param int $user_id User ID
     * @param int $lesson_id Lesson ID
     * @param float $progress_percent Progress percentage (0-100)
     * @param bool $is_completed Whether the lesson is marked as completed
     * @param int $duration_watched Duration watched in seconds
     * @return bool Success status
     */
    public function recordLectureProgress($user_id, $lesson_id, $progress_percent, $is_completed = false, $duration_watched = 0) {
        $user_id = intval($user_id);
        $lesson_id = intval($lesson_id);
        $progress_percent = floatval($progress_percent);
        $is_completed = $is_completed ? 1 : 0;
        $duration_watched = intval($duration_watched);
        
        // Get course_id for this lesson
        $stmt = $this->conn->prepare("SELECT course_id FROM lessons WHERE lesson_id = ?");
        $stmt->bind_param("i", $lesson_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $course_id = $row['course_id'] ?? 0;
        
        if (!$course_id) {
            return false;
        }
        
        // Check if a progress record already exists
        $stmt = $this->conn->prepare("
            SELECT progress_id, progress_percentage, is_completed, duration_watched
            FROM lecture_progress
            WHERE user_id = ? AND lecture_id = ?
        ");
        $stmt->bind_param("ii", $user_id, $lesson_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing progress
            $row = $result->fetch_assoc();
            
            // Only update if the new progress is greater
            if ($progress_percent > $row['progress_percentage'] || $duration_watched > $row['duration_watched'] || ($is_completed && !$row['is_completed'])) {
                
                $stmt = $this->conn->prepare("
                    UPDATE lecture_progress
                    SET progress_percentage = ?,
                        is_completed = ?,
                        duration_watched = ?,
                        last_access_time = NOW(),
                        completion_time = IF(? AND NOT is_completed, NOW(), completion_time)
                    WHERE user_id = ? AND lecture_id = ?
                ");
                $stmt->bind_param("diiiii", $progress_percent, $is_completed, $duration_watched, $is_completed, $user_id, $lesson_id);
                return $stmt->execute();
            }
            
            return true; // No update needed
        } else {
            // Insert new progress record
            $stmt = $this->conn->prepare("
                INSERT INTO lecture_progress (
                    user_id,
                    course_id,
                    lecture_id,
                    progress_percentage,
                    start_time,
                    last_access_time,
                    completion_time,
                    is_completed,
                    duration_watched
                ) VALUES (?, ?, ?, ?, NOW(), NOW(), IF(?, NOW(), NULL), ?, ?)
            ");
            $stmt->bind_param("iiidiii", $user_id, $course_id, $lesson_id, $progress_percent, $is_completed, $is_completed, $duration_watched);
            return $stmt->execute();
        }
    }
} 