-- Combined database script for online courses
-- Combines online_cources.sql and analytics_tables.sql

-- 1) Bảng users (lưu cả học viên, giảng viên và admin)
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    is_locked TINYINT(1) DEFAULT 0,
    role ENUM('student', 'instructor', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,              -- ID người dùng thực hiện hành động
    action VARCHAR(100) NOT NULL,  -- Hành động (ví dụ: "lock_user", "change_role")
    target_user_id INT,       -- ID người dùng bị ảnh hưởng (nếu có)
    details TEXT,             -- Chi tiết hành động
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (target_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS content_approvals (
    approval_id INT AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('comment', 'review') NOT NULL,  -- Loại nội dung
    content_id INT NOT NULL,  -- ID của bình luận hoặc đánh giá
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',  -- Trạng thái
    approved_by INT,          -- ID admin phê duyệt
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (approved_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- 2) Bảng categories
CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3) Bảng courses (sử dụng trường instructor_id tham chiếu đến users.user_id, chỉ những người có role = 'instructor' mới được phép tạo khoá học)
CREATE TABLE IF NOT EXISTS courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    instructor_id INT NOT NULL,
    category_id INT, 
    title VARCHAR(255) NOT NULL,
    description TEXT,
    video VARCHAR(255),
    duration VARCHAR(50),
    price DECIMAL(10,2) DEFAULT 0,
    image VARCHAR(255),
    level VARCHAR(50),
    language VARCHAR(50) DEFAULT NULL,
    requirements TEXT,
    learning_outcomes TEXT,
    tags VARCHAR(255) DEFAULT NULL,
    rating DECIMAL(3,2) DEFAULT 0,
    students INT DEFAULT 0,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES users(user_id),
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
) ENGINE=InnoDB;

-- 4) Bảng enrollments
CREATE TABLE IF NOT EXISTS enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    course_id INT,
    enrolled_date DATE,
    status ENUM('active', 'completed', 'dropped') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (course_id) REFERENCES courses(course_id)
) ENGINE=InnoDB;

-- 5) Bảng lessons (was previously named 'lectures' in some places, now standardized)
CREATE TABLE IF NOT EXISTS lessons (
    lesson_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    order_index INT,
    duration INT,  -- thời gian (phút)
    document_file VARCHAR(255) NULL,
    video_file VARCHAR(255) NULL, 
    presentation_file VARCHAR(255) NULL,
    additional_files TEXT NULL,
    FOREIGN KEY (course_id) REFERENCES courses(course_id)
) ENGINE=InnoDB;

-- 6) Bảng assignments
CREATE TABLE IF NOT EXISTS assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    lesson_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    instructions TEXT,
    due_date DATETIME,
    max_score DECIMAL(5,2),
    is_published TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id),
    FOREIGN KEY (lesson_id) REFERENCES lessons(lesson_id)
) ENGINE=InnoDB;

-- 7) Bảng assignment_files
CREATE TABLE IF NOT EXISTS assignment_files (
    file_id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT,
    file_path VARCHAR(255) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assignment_submissions (
    submission_id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    submission_text TEXT,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    score DECIMAL(5,2) DEFAULT NULL,
    feedback TEXT,
    graded_by INT DEFAULT NULL,
    graded_at TIMESTAMP NULL,
    status ENUM('submitted', 'graded', 'returned') DEFAULT 'submitted',
    FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Create submission_files table if it doesn't exist
CREATE TABLE IF NOT EXISTS submission_files (
    file_id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES assignment_submissions(submission_id) ON DELETE CASCADE
);

-- Create quizzes table if it doesn't exist
CREATE TABLE IF NOT EXISTS quizzes (
    quiz_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    lesson_id INT DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    instructions TEXT,
    time_limit INT DEFAULT NULL COMMENT 'Time limit in minutes',
    attempts_allowed INT DEFAULT 1,
    pass_score INT DEFAULT 60 COMMENT 'Minimum score to pass in percentage',
    is_published TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(lesson_id) ON DELETE SET NULL
);

-- Create quiz_questions table if it doesn't exist
CREATE TABLE IF NOT EXISTS quiz_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'short_answer', 'essay') NOT NULL,
    points INT DEFAULT 1,
    position INT DEFAULT 0,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE
);

-- Create quiz_answers table if it doesn't exist
CREATE TABLE IF NOT EXISTS quiz_answers (
    answer_id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    answer_text TEXT NOT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(question_id) ON DELETE CASCADE
);

-- Create quiz_attempts table if it doesn't exist
CREATE TABLE IF NOT EXISTS quiz_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    student_id INT NOT NULL,
    start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    score INT DEFAULT NULL,
    status ENUM('in_progress', 'completed', 'abandoned') DEFAULT 'in_progress',
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Create quiz_answers_submitted table if it doesn't exist
CREATE TABLE IF NOT EXISTS quiz_answers_submitted (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_id INT DEFAULT NULL,
    answer_text TEXT DEFAULT NULL,
    points_earned DECIMAL(5,2) DEFAULT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    instructor_feedback TEXT,
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(attempt_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(question_id) ON DELETE CASCADE,
    FOREIGN KEY (answer_id) REFERENCES quiz_answers(answer_id) ON DELETE SET NULL
); 

-- 10) Bảng discussion
CREATE TABLE IF NOT EXISTS discussion (
    discussion_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    user_id INT,
    title VARCHAR(200),
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- 11) Bảng comments
CREATE TABLE IF NOT EXISTS comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    discussion_id INT,
    user_id INT,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    FOREIGN KEY (discussion_id) REFERENCES discussion(discussion_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- 11) Bảng notifications
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    course_id INT,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    type ENUM('info', 'warning', 'error') DEFAULT 'info',
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (course_id) REFERENCES courses(course_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contacts (
    contact_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 13) Bảng reviews (đánh giá khoá học)
CREATE TABLE IF NOT EXISTS reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,            -- Khoá học được đánh giá
    user_id INT,              -- Người đánh giá (tham chiếu đến bảng users)
    rating INT NOT NULL,      -- Số sao đánh giá (ví dụ: 1 đến 5)
    review_text TEXT NOT NULL, -- Nội dung đánh giá
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    FOREIGN KEY (course_id) REFERENCES courses(course_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chatbots (
    chat_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,             
    question TEXT NOT NULL,   
    response TEXT NOT NULL,  
    language VARCHAR(10),    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,  
    is_helpful BOOLEAN DEFAULT NULL,  
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS knowledge_base (
    kb_id INT AUTO_INCREMENT PRIMARY KEY,
    keywords JSON NOT NULL,
    answer TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Course Analytics Tables

-- Table for storing course view statistics
CREATE TABLE IF NOT EXISTS course_views (
    view_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    user_id INT NULL,  -- NULL for anonymous views
    view_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    device_type VARCHAR(50),
    source VARCHAR(100),  -- Where the view came from (direct, search, etc.)
    ip_address VARCHAR(45) NULL,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Table for tracking student progress through lessons
CREATE TABLE IF NOT EXISTS lecture_progress (
    progress_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    lecture_id INT NOT NULL,  -- References lesson_id in lessons table
    progress_percentage INT DEFAULT 0,  -- 0-100
    start_time TIMESTAMP NULL,
    last_access_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completion_time TIMESTAMP NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    duration_watched INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (lecture_id) REFERENCES lessons(lesson_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Create index for lecture_progress
CREATE INDEX idx_lecture_progress_lecture_id ON lecture_progress(lecture_id);

-- Table for storing course engagement metrics
CREATE TABLE IF NOT EXISTS course_engagement (
    engagement_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    date DATE NOT NULL,
    engagement_type VARCHAR(20) NULL,
    engagement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_enrollments INT DEFAULT 0,
    active_students INT DEFAULT 0,
    completed_lessons INT DEFAULT 0,
    avg_completion_rate DECIMAL(5,2) DEFAULT 0,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    UNIQUE KEY (course_id, date)
) ENGINE=InnoDB;

-- Table for storing daily aggregated analytics
CREATE TABLE IF NOT EXISTS course_daily_stats (
    stat_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    stat_date DATE NOT NULL,
    views INT DEFAULT 0,
    unique_viewers INT DEFAULT 0,
    enrollments INT DEFAULT 0,
    lesson_completions INT DEFAULT 0,
    quiz_completions INT DEFAULT 0,
    assignment_submissions INT DEFAULT 0,
    revenue DECIMAL(10,2) DEFAULT 0,
    discussion_posts INT DEFAULT 0,
    avg_rating DECIMAL(3,2) DEFAULT 0,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    UNIQUE KEY (course_id, stat_date)
) ENGINE=InnoDB;

-- Table for storing aggregated student demographic data
CREATE TABLE IF NOT EXISTS course_demographics (
    demographic_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    update_date DATE NOT NULL,
    age_ranges JSON NULL,  -- Store age distribution as JSON
    gender_distribution JSON NULL,  -- Store gender distribution as JSON
    country_distribution JSON NULL,  -- Store geographic distribution as JSON
    experience_level JSON NULL,  -- Store experience level distribution
    snapshot_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    UNIQUE KEY (course_id, update_date)
) ENGINE=InnoDB;

-- Create triggers and stored procedures
-- ===================================

-- Drop any existing triggers
DROP TRIGGER IF EXISTS after_lecture_completion;

-- Recreate the lecture completion trigger
DELIMITER //
CREATE TRIGGER after_lecture_completion
AFTER UPDATE ON lecture_progress
FOR EACH ROW
BEGIN
    IF NEW.is_completed = TRUE AND OLD.is_completed = FALSE THEN
        -- Update the course_engagement table
        INSERT INTO course_engagement (course_id, date, completed_lessons)
        VALUES (NEW.course_id, CURDATE(), 1)
        ON DUPLICATE KEY UPDATE
            completed_lessons = completed_lessons + 1;
    END IF;
END //
DELIMITER ;

-- Drop any existing procedures
DROP PROCEDURE IF EXISTS update_course_daily_stats;

-- Create stored procedure to refresh daily course stats
DELIMITER //
CREATE PROCEDURE update_course_daily_stats(IN target_course_id INT, IN target_date DATE)
BEGIN
    -- Delete existing record for the date
    DELETE FROM course_daily_stats 
    WHERE course_id = target_course_id AND stat_date = target_date;
    
    -- Insert new aggregated data
    INSERT INTO course_daily_stats (
        course_id, 
        stat_date, 
        views, 
        unique_viewers,
        enrollments, 
        lesson_completions,
        quiz_completions,
        assignment_submissions,
        revenue,
        discussion_posts,
        avg_rating
    )
    SELECT
        target_course_id,
        target_date,
        -- Count views
        (SELECT COUNT(*) FROM course_views 
         WHERE course_id = target_course_id 
         AND DATE(view_date) = target_date),
        -- Count unique viewers
        (SELECT COUNT(DISTINCT user_id) FROM course_views 
         WHERE course_id = target_course_id 
         AND DATE(view_date) = target_date 
         AND user_id IS NOT NULL),
        -- Count enrollments
        (SELECT COUNT(*) FROM enrollments 
         WHERE course_id = target_course_id 
         AND DATE(enrolled_date) = target_date),
        -- Count completed lessons
        (SELECT COUNT(*) FROM lecture_progress 
         WHERE course_id = target_course_id 
         AND is_completed = TRUE 
         AND DATE(completion_time) = target_date),
        -- Quiz completions
        (SELECT COUNT(*) FROM quiz_attempts 
         WHERE quiz_id IN (SELECT quiz_id FROM quizzes WHERE course_id = target_course_id)
         AND status = 'completed' 
         AND DATE(end_time) = target_date),
        -- Assignment submissions
        (SELECT COUNT(*) FROM assignment_submissions 
         WHERE assignment_id IN (SELECT assignment_id FROM assignments WHERE course_id = target_course_id)
         AND DATE(submission_date) = target_date),
        -- Revenue calculation - assume zero for now - would need to integrate with payment system
        0,
        -- Discussion posts
        (SELECT COUNT(*) FROM discussion 
         WHERE course_id = target_course_id 
         AND DATE(created_at) = target_date),
        -- Average rating
        (SELECT AVG(rating) FROM reviews 
         WHERE course_id = target_course_id 
         AND status = 'approved')
    ;
END //
DELIMITER ;

-- Create analytics views
-- ===================================

-- Create a view for easy access to key analytics
CREATE OR REPLACE VIEW course_analytics_summary AS
SELECT 
    c.course_id,
    c.title,
    c.instructor_id,
    u.username AS instructor_name,
    c.created_at AS course_created,
    COUNT(DISTINCT e.user_id) AS total_students,
    SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) AS completed_students,
    (SELECT COUNT(*) FROM lessons WHERE course_id = c.course_id) AS total_lessons,
    (SELECT COUNT(*) FROM reviews WHERE course_id = c.course_id AND status = 'approved') AS review_count,
    c.rating AS average_rating,
    (SELECT SUM(views) FROM course_daily_stats WHERE course_id = c.course_id) AS total_views,
    c.price AS course_price
FROM 
    courses c
LEFT JOIN 
    users u ON c.instructor_id = u.user_id
LEFT JOIN 
    enrollments e ON c.course_id = e.course_id
GROUP BY 
    c.course_id;

-- Create a view for lesson progress analytics to simplify access
CREATE OR REPLACE VIEW lesson_progress_analytics AS
SELECT 
    lp.progress_id,
    lp.user_id,
    lp.course_id,
    lp.lecture_id AS lesson_id,
    l.title AS lesson_title,
    l.order_index,
    lp.progress_percentage,
    lp.start_time,
    lp.last_access_time,
    lp.completion_time,
    lp.is_completed,
    lp.duration_watched,
    u.username,
    c.title AS course_title
FROM 
    lecture_progress lp
JOIN 
    lessons l ON lp.lecture_id = l.lesson_id
JOIN 
    users u ON lp.user_id = u.user_id
JOIN 
    courses c ON lp.course_id = c.course_id;

-- Add initial admin user
INSERT INTO users (username, password, email, role, created_at) 
VALUES (
    'admin', 
    '$2y$10$HNWNMUb2V591/U4DUbiwFerk1a6sB0NKQzSVfuEP0MJx7oINcwbLK', /*admin123*/
    'hoctap435@gmail.com', 
    'admin', 
    NOW()
);

-- Add sample knowledge base data
INSERT INTO knowledge_base (keywords, answer) VALUES
('["đăng ký", "khóa học"]', 'Để đăng ký khóa học, bạn hãy nhấp vào nút "Đăng ký khóa học" ở trang khóa học, sau đó làm theo hướng dẫn. Nếu bạn chưa đăng nhập, hãy đăng nhập với vai trò sinh viên trước nhé!'),
('["tài liệu", "học tập", "tìm"]', 'Tài liệu học tập có thể được tìm thấy trong trang khóa học mà bạn đã đăng ký. Sau khi đăng nhập, vào phần "Khoá Học Của Tôi" để truy cập tài liệu.'),
('["lịch học"]', 'Lịch học của bạn có thể được xem trong phần "Lịch Học" sau khi đăng nhập. Vào trang "Dashboard" của bạn và chọn mục "Lịch Học" để xem chi tiết.'),
('["liên hệ", "giáo viên"]', 'Bạn có thể liên hệ với giáo viên qua phần "Liên Hệ Giáo Viên" trong trang khóa học. Ngoài ra, bạn cũng có thể gửi tin nhắn qua mục "Liên Hệ" ở cuối trang.'),
('["hỗ trợ", "kỹ thuật"]', 'Tất nhiên rồi! Chúng tôi cung cấp hỗ trợ kỹ thuật 24/7 qua email và chat trực tiếp. Bạn có thể gửi email đến hoctap435@gmail.com hoặc tiếp tục chat với tôi để được hỗ trợ ngay.'),
('["nội dung", "cập nhật"]', 'Có, các khóa học được cập nhật định kỳ để đảm bảo thông tin luôn mới và phù hợp với nhu cầu học tập.'),
('["đặt lại", "mật khẩu"]', 'Để đặt lại mật khẩu, nhấp vào "Quên Mật Khẩu" ở form đăng nhập, nhập email của bạn và làm theo hướng dẫn để nhận mã xác nhận.'),
('["thanh toán", "học phí"]', 'Bạn có thể thanh toán học phí qua mục "Thanh Toán" trong trang khóa học. Chúng tôi hỗ trợ nhiều phương thức như chuyển khoản ngân hàng và ví điện tử.'),
('["chứng chỉ", "nhận"]', 'Sau khi hoàn thành khóa học, bạn có thể nhận chứng chỉ bằng cách vào phần "Chứng Chỉ" trong "Khoá Học Của Tôi" và tải về.'),
('["lỗi", "hệ thống"]', 'Nếu gặp lỗi hệ thống, hãy chụp màn hình và gửi đến email hoctap435@gmail.com để được hỗ trợ nhanh nhất.'); 