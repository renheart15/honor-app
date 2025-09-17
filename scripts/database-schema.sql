-- =====================================================
-- CTU Honor Application System Database Schema
-- =====================================================

-- Create database
CREATE DATABASE IF NOT EXISTS honor_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE honor_app;

-- =====================================================
-- 1. USERS TABLE
-- =====================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(20) UNIQUE NULL COMMENT 'Student ID number (null for non-students)',
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL COMMENT 'Hashed password using password_hash()',
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NULL,
    role ENUM('student', 'adviser', 'chairperson') NOT NULL DEFAULT 'student',
    department VARCHAR(100) NULL,
    year_level INT NULL COMMENT 'Student year level (1-4)',
    section VARCHAR(10) NULL COMMENT 'Student section (e.g., CS-3A)',
    contact_number VARCHAR(15) NULL,
    address TEXT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_student_id (student_id),
    INDEX idx_role (role),
    INDEX idx_department (department),
    INDEX idx_status (status)
) ENGINE=InnoDB COMMENT='User accounts for students, advisers, and chairpersons';

-- =====================================================
-- 2. ACADEMIC PERIODS TABLE
-- =====================================================
CREATE TABLE academic_periods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    period_name VARCHAR(50) NOT NULL COMMENT 'e.g., First Semester 2024-2025',
    school_year VARCHAR(20) NOT NULL COMMENT 'e.g., 2024-2025',
    semester ENUM('1st', '2nd', 'summer') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT FALSE COMMENT 'Only one period can be active at a time',
    registration_deadline DATE NULL COMMENT 'Deadline for grade submissions',
    application_deadline DATE NULL COMMENT 'Deadline for honor applications',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_active_period (is_active, school_year, semester),
    INDEX idx_school_year (school_year),
    INDEX idx_active (is_active)
) ENGINE=InnoDB COMMENT='Academic periods/semesters management';

-- =====================================================
-- 3. GRADE SUBMISSIONS TABLE
-- =====================================================
CREATE TABLE grade_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    academic_period_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL COMMENT 'Path to uploaded PDF file',
    file_name VARCHAR(255) NOT NULL COMMENT 'Original filename',
    file_size INT NOT NULL COMMENT 'File size in bytes',
    mime_type VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'processing', 'processed', 'rejected', 'failed') DEFAULT 'pending',
    processed_at TIMESTAMP NULL,
    processed_by INT NULL COMMENT 'ID of user who processed (adviser/chairperson)',
    rejection_reason TEXT NULL,
    notes TEXT NULL COMMENT 'Additional notes from processor',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE RESTRICT,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_user_period (user_id, academic_period_id),
    INDEX idx_status (status),
    INDEX idx_upload_date (upload_date)
) ENGINE=InnoDB COMMENT='Student grade report submissions';

-- =====================================================
-- 4. GRADES TABLE (Individual Subject Grades)
-- =====================================================
CREATE TABLE grades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    submission_id INT NOT NULL,
    subject_code VARCHAR(20) NOT NULL COMMENT 'e.g., CS101, MATH201',
    subject_name VARCHAR(150) NOT NULL,
    units DECIMAL(3,1) NOT NULL COMMENT 'Credit units (e.g., 3.0, 1.5)',
    grade DECIMAL(3,2) NOT NULL COMMENT 'Numerical grade (e.g., 1.25, 2.50)',
    letter_grade VARCHAR(5) NULL COMMENT 'Letter equivalent (A, B+, etc.)',
    remarks VARCHAR(50) NULL COMMENT 'PASSED, FAILED, INC, etc.',
    semester_taken VARCHAR(20) NULL,
    adviser_name VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (submission_id) REFERENCES grade_submissions(id) ON DELETE CASCADE,
    
    INDEX idx_submission (submission_id),
    INDEX idx_subject_code (subject_code),
    INDEX idx_grade (grade)
) ENGINE=InnoDB COMMENT='Individual subject grades extracted from submissions';

-- =====================================================
-- 5. GWA CALCULATIONS TABLE
-- =====================================================
CREATE TABLE gwa_calculations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    academic_period_id INT NOT NULL,
    submission_id INT NOT NULL,
    total_units DECIMAL(6,1) NOT NULL COMMENT 'Total credit units',
    total_grade_points DECIMAL(10,2) NOT NULL COMMENT 'Sum of (grade × units)',
    gwa DECIMAL(4,3) NOT NULL COMMENT 'General Weighted Average',
    subjects_count INT NOT NULL COMMENT 'Number of subjects',
    failed_subjects INT DEFAULT 0 COMMENT 'Number of failed subjects',
    incomplete_subjects INT DEFAULT 0 COMMENT 'Number of incomplete subjects',
    calculation_method VARCHAR(50) DEFAULT 'weighted_average',
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    recalculated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE RESTRICT,
    FOREIGN KEY (submission_id) REFERENCES grade_submissions(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_user_period_gwa (user_id, academic_period_id),
    INDEX idx_gwa (gwa),
    INDEX idx_calculated_at (calculated_at)
) ENGINE=InnoDB COMMENT='Computed GWA for each student per academic period';

-- =====================================================
-- 6. HONOR APPLICATIONS TABLE
-- =====================================================
CREATE TABLE honor_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    academic_period_id INT NOT NULL,
    gwa_calculation_id INT NOT NULL,
    application_type ENUM('deans_list', 'presidents_list', 'magna_cum_laude', 'summa_cum_laude') NOT NULL,
    gwa_achieved DECIMAL(4,3) NOT NULL COMMENT 'GWA at time of application',
    required_gwa DECIMAL(4,3) NOT NULL COMMENT 'Required GWA for this honor type',
    status ENUM('submitted', 'under_review', 'approved', 'denied', 'cancelled') DEFAULT 'submitted',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL COMMENT 'Adviser/Chairperson who reviewed',
    approval_date DATE NULL,
    remarks TEXT NULL COMMENT 'Review comments',
    certificate_generated BOOLEAN DEFAULT FALSE,
    certificate_path VARCHAR(500) NULL,
    ranking_position INT NULL COMMENT 'Position in honor roll ranking',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE RESTRICT,
    FOREIGN KEY (gwa_calculation_id) REFERENCES gwa_calculations(id) ON DELETE RESTRICT,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_user_period_type (user_id, academic_period_id, application_type),
    INDEX idx_status (status),
    INDEX idx_application_type (application_type),
    INDEX idx_submitted_at (submitted_at),
    INDEX idx_gwa_achieved (gwa_achieved)
) ENGINE=InnoDB COMMENT='Honor applications (Deans List, Presidents List, etc.)';

-- =====================================================
-- 7. NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'system') DEFAULT 'info',
    category ENUM('grade_upload', 'gwa_calculation', 'honor_application', 'system_update', 'general') DEFAULT 'general',
    is_read BOOLEAN DEFAULT FALSE,
    is_email_sent BOOLEAN DEFAULT FALSE,
    email_sent_at TIMESTAMP NULL,
    action_url VARCHAR(500) NULL COMMENT 'URL for notification action button',
    action_text VARCHAR(50) NULL COMMENT 'Text for action button',
    expires_at TIMESTAMP NULL COMMENT 'When notification expires',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_created_at (created_at),
    INDEX idx_type (type),
    INDEX idx_category (category)
) ENGINE=InnoDB COMMENT='System notifications for users';

-- =====================================================
-- 8. SYSTEM SETTINGS TABLE
-- =====================================================
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    category VARCHAR(50) DEFAULT 'general',
    description TEXT NULL,
    is_public BOOLEAN DEFAULT FALSE COMMENT 'Can be accessed by non-admin users',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_category (category),
    INDEX idx_public (is_public)
) ENGINE=InnoDB COMMENT='System configuration settings';

-- =====================================================
-- 9. HONOR RANKINGS TABLE
-- =====================================================
CREATE TABLE honor_rankings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    academic_period_id INT NOT NULL,
    department VARCHAR(100) NOT NULL,
    year_level INT NULL COMMENT 'NULL for department-wide ranking',
    section VARCHAR(10) NULL COMMENT 'NULL for year-level ranking',
    ranking_type ENUM('deans_list', 'presidents_list', 'overall') NOT NULL,
    user_id INT NOT NULL,
    gwa DECIMAL(4,3) NOT NULL,
    rank_position INT NOT NULL,
    total_students INT NOT NULL COMMENT 'Total students in this ranking category',
    percentile DECIMAL(5,2) NULL COMMENT 'Percentile ranking',
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    generated_by INT NULL,
    
    FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_ranking (academic_period_id, department, year_level, section, ranking_type, user_id),
    INDEX idx_ranking_type (ranking_type),
    INDEX idx_department (department),
    INDEX idx_rank_position (rank_position),
    INDEX idx_gwa (gwa)
) ENGINE=InnoDB COMMENT='Honor roll rankings by department, year, and section';

-- =====================================================
-- 10. AUDIT LOGS TABLE
-- =====================================================
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL COMMENT 'User who performed the action',
    action VARCHAR(100) NOT NULL COMMENT 'Action performed (login, upload, approve, etc.)',
    table_name VARCHAR(50) NULL COMMENT 'Database table affected',
    record_id INT NULL COMMENT 'ID of affected record',
    old_values JSON NULL COMMENT 'Previous values (for updates)',
    new_values JSON NULL COMMENT 'New values (for inserts/updates)',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    session_id VARCHAR(128) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_table_name (table_name),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB COMMENT='Audit trail for system actions';

-- =====================================================
-- INSERT DEFAULT SYSTEM SETTINGS
-- =====================================================
INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description, is_public) VALUES
-- GWA Thresholds
('deans_list_threshold', '1.75', 'number', 'honors', 'Minimum GWA required for Dean\'s List eligibility', TRUE),
('presidents_list_threshold', '1.25', 'number', 'honors', 'Minimum GWA required for President\'s List eligibility', TRUE),
('magna_cum_laude_threshold', '1.45', 'number', 'honors', 'Minimum GWA required for Magna Cum Laude', TRUE),
('summa_cum_laude_threshold', '1.20', 'number', 'honors', 'Minimum GWA required for Summa Cum Laude', TRUE),

-- File Upload Settings
('max_file_size', '5242880', 'number', 'upload', 'Maximum file upload size in bytes (5MB)', FALSE),
('allowed_file_types', 'pdf', 'string', 'upload', 'Allowed file types for grade submissions', FALSE),
('upload_path', 'uploads/grades/', 'string', 'upload', 'Directory path for uploaded files', FALSE),

-- Email Settings
('smtp_enabled', 'true', 'boolean', 'email', 'Enable email notifications', FALSE),
('smtp_host', 'smtp.gmail.com', 'string', 'email', 'SMTP server host', FALSE),
('smtp_port', '587', 'number', 'email', 'SMTP server port', FALSE),
('smtp_encryption', 'tls', 'string', 'email', 'SMTP encryption type', FALSE),
('from_email', 'noreply@ctu.edu.ph', 'string', 'email', 'Default from email address', FALSE),
('from_name', 'CTU Honor System', 'string', 'email', 'Default from name', TRUE),

-- Application Settings
('application_name', 'CTU Honor Application System', 'string', 'general', 'System application name', TRUE),
('institution_name', 'Cebu Technological University - Tuburan Campus', 'string', 'general', 'Institution full name', TRUE),
('academic_year_format', 'YYYY-YYYY', 'string', 'general', 'Format for academic year display', TRUE),
('default_timezone', 'Asia/Manila', 'string', 'general', 'Default system timezone', FALSE),

-- Security Settings
('session_timeout', '3600', 'number', 'security', 'Session timeout in seconds (1 hour)', FALSE),
('max_login_attempts', '5', 'number', 'security', 'Maximum login attempts before lockout', FALSE),
('lockout_duration', '900', 'number', 'security', 'Account lockout duration in seconds (15 minutes)', FALSE),
('password_min_length', '8', 'number', 'security', 'Minimum password length', TRUE),

-- Notification Settings
('notification_retention_days', '90', 'number', 'notifications', 'Days to keep notifications before cleanup', FALSE),
('email_notification_enabled', 'true', 'boolean', 'notifications', 'Enable email notifications', TRUE),
('push_notification_enabled', 'false', 'boolean', 'notifications', 'Enable push notifications', TRUE),

-- Report Settings
('certificate_template_path', 'templates/certificates/', 'string', 'reports', 'Path to certificate templates', FALSE),
('report_logo_path', 'assets/images/ctu-logo.png', 'string', 'reports', 'Path to institution logo for reports', TRUE),
('watermark_enabled', 'true', 'boolean', 'reports', 'Enable watermark on certificates', FALSE);

-- =====================================================
-- INSERT SAMPLE ACADEMIC PERIOD
-- =====================================================
INSERT INTO academic_periods (period_name, school_year, semester, start_date, end_date, is_active, registration_deadline, application_deadline) VALUES
('First Semester 2024-2025', '2024-2025', '1st', '2024-08-01', '2024-12-15', TRUE, '2024-12-20', '2024-12-25'),
('Second Semester 2024-2025', '2024-2025', '2nd', '2025-01-15', '2025-05-30', FALSE, '2025-06-05', '2025-06-10');

-- =====================================================
-- INSERT SAMPLE USERS (Password: 'password' hashed)
-- =====================================================
INSERT INTO users (student_id, email, password, first_name, last_name, role, department, year_level, section, contact_number, status, email_verified) VALUES
-- Students
('2024-001', 'student@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'Doe', 'student', 'Computer Science', 3, 'CS-3A', '09123456789', 'active', TRUE),
('2024-002', 'jane.smith@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane', 'Smith', 'student', 'Computer Science', 3, 'CS-3A', '09123456790', 'active', TRUE),
('2024-003', 'mike.johnson@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike', 'Johnson', 'student', 'Information Technology', 2, 'IT-2B', '09123456791', 'active', TRUE),

-- Advisers
('ADV-001', 'adviser@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Prof. Maria', 'Garcia', 'adviser', 'Computer Science', NULL, NULL, '09123456792', 'active', TRUE),
('ADV-002', 'advisor@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Prof. Robert', 'Cruz', 'adviser', 'Information Technology', NULL, NULL, '09123456793', 'active', TRUE),

-- Chairpersons
('CHAIR-001', 'chair@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Robert', 'Johnson', 'chairperson', 'Computer Science', NULL, NULL, '09123456794', 'active', TRUE),
('CHAIR-002', 'dean@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Elizabeth', 'Santos', 'chairperson', 'Information Technology', NULL, NULL, '09123456795', 'active', TRUE);

-- =====================================================
-- CREATE VIEWS FOR COMMON QUERIES
-- =====================================================

-- View for student GWA summary
CREATE VIEW student_gwa_summary AS
SELECT 
    u.id as user_id,
    u.student_id,
    u.first_name,
    u.last_name,
    u.department,
    u.year_level,
    u.section,
    ap.period_name,
    ap.school_year,
    ap.semester,
    gwa.gwa,
    gwa.total_units,
    gwa.subjects_count,
    gwa.calculated_at,
    CASE 
        WHEN gwa.gwa <= 1.20 THEN 'Summa Cum Laude'
        WHEN gwa.gwa <= 1.25 THEN 'Presidents List'
        WHEN gwa.gwa <= 1.45 THEN 'Magna Cum Laude'
        WHEN gwa.gwa <= 1.75 THEN 'Deans List'
        ELSE 'Regular'
    END as honor_classification
FROM users u
JOIN gwa_calculations gwa ON u.id = gwa.user_id
JOIN academic_periods ap ON gwa.academic_period_id = ap.id
WHERE u.role = 'student' AND u.status = 'active';

-- View for honor applications with user details
CREATE VIEW honor_applications_detailed AS
SELECT 
    ha.id,
    ha.application_type,
    ha.status,
    ha.gwa_achieved,
    ha.required_gwa,
    ha.submitted_at,
    ha.reviewed_at,
    ha.approval_date,
    ha.ranking_position,
    u.student_id,
    u.first_name,
    u.last_name,
    u.department,
    u.year_level,
    u.section,
    ap.period_name,
    ap.school_year,
    ap.semester,
    reviewer.first_name as reviewer_first_name,
    reviewer.last_name as reviewer_last_name
FROM honor_applications ha
JOIN users u ON ha.user_id = u.id
JOIN academic_periods ap ON ha.academic_period_id = ap.id
LEFT JOIN users reviewer ON ha.reviewed_by = reviewer.id;

-- =====================================================
-- CREATE STORED PROCEDURES
-- =====================================================

DELIMITER //

-- Procedure to calculate GWA
CREATE PROCEDURE CalculateGWA(IN p_submission_id INT)
BEGIN
    DECLARE v_user_id INT;
    DECLARE v_academic_period_id INT;
    DECLARE v_total_units DECIMAL(6,1) DEFAULT 0;
    DECLARE v_total_grade_points DECIMAL(10,2) DEFAULT 0;
    DECLARE v_gwa DECIMAL(4,3);
    DECLARE v_subjects_count INT DEFAULT 0;
    DECLARE v_failed_subjects INT DEFAULT 0;
    DECLARE v_incomplete_subjects INT DEFAULT 0;
    
    -- Get submission details
    SELECT user_id, academic_period_id 
    INTO v_user_id, v_academic_period_id
    FROM grade_submissions 
    WHERE id = p_submission_id;
    
    -- Calculate totals
    SELECT 
        SUM(units),
        SUM(grade * units),
        COUNT(*),
        SUM(CASE WHEN grade >= 3.0 THEN 1 ELSE 0 END),
        SUM(CASE WHEN remarks = 'INC' THEN 1 ELSE 0 END)
    INTO v_total_units, v_total_grade_points, v_subjects_count, v_failed_subjects, v_incomplete_subjects
    FROM grades 
    WHERE submission_id = p_submission_id;
    
    -- Calculate GWA
    IF v_total_units > 0 THEN
        SET v_gwa = v_total_grade_points / v_total_units;
        
        -- Insert or update GWA calculation
        INSERT INTO gwa_calculations (
            user_id, academic_period_id, submission_id, 
            total_units, total_grade_points, gwa, 
            subjects_count, failed_subjects, incomplete_subjects
        ) VALUES (
            v_user_id, v_academic_period_id, p_submission_id,
            v_total_units, v_total_grade_points, v_gwa,
            v_subjects_count, v_failed_subjects, v_incomplete_subjects
        ) ON DUPLICATE KEY UPDATE
            total_units = v_total_units,
            total_grade_points = v_total_grade_points,
            gwa = v_gwa,
            subjects_count = v_subjects_count,
            failed_subjects = v_failed_subjects,
            incomplete_subjects = v_incomplete_subjects,
            recalculated_at = CURRENT_TIMESTAMP;
    END IF;
END //

-- Procedure to generate honor rankings
CREATE PROCEDURE GenerateHonorRankings(IN p_academic_period_id INT, IN p_department VARCHAR(100))
BEGIN
    -- Clear existing rankings for this period and department
    DELETE FROM honor_rankings 
    WHERE academic_period_id = p_academic_period_id 
    AND department = p_department;
    
    -- Generate Dean's List rankings
    INSERT INTO honor_rankings (
        academic_period_id, department, year_level, section, 
        ranking_type, user_id, gwa, rank_position, total_students
    )
    SELECT 
        p_academic_period_id,
        p_department,
        u.year_level,
        u.section,
        'deans_list',
        u.id,
        gwa.gwa,
        ROW_NUMBER() OVER (PARTITION BY u.year_level, u.section ORDER BY gwa.gwa ASC),
        COUNT(*) OVER (PARTITION BY u.year_level, u.section)
    FROM users u
    JOIN gwa_calculations gwa ON u.id = gwa.user_id
    WHERE u.department = p_department
    AND u.role = 'student'
    AND u.status = 'active'
    AND gwa.academic_period_id = p_academic_period_id
    AND gwa.gwa <= 1.75;
    
    -- Generate President's List rankings
    INSERT INTO honor_rankings (
        academic_period_id, department, year_level, section, 
        ranking_type, user_id, gwa, rank_position, total_students
    )
    SELECT 
        p_academic_period_id,
        p_department,
        u.year_level,
        u.section,
        'presidents_list',
        u.id,
        gwa.gwa,
        ROW_NUMBER() OVER (PARTITION BY u.year_level, u.section ORDER BY gwa.gwa ASC),
        COUNT(*) OVER (PARTITION BY u.year_level, u.section)
    FROM users u
    JOIN gwa_calculations gwa ON u.id = gwa.user_id
    WHERE u.department = p_department
    AND u.role = 'student'
    AND u.status = 'active'
    AND gwa.academic_period_id = p_academic_period_id
    AND gwa.gwa <= 1.25;
END //

DELIMITER ;

-- =====================================================
-- CREATE TRIGGERS
-- =====================================================

DELIMITER //

-- Trigger to update GWA when grades are inserted/updated
CREATE TRIGGER tr_grades_after_insert
AFTER INSERT ON grades
FOR EACH ROW
BEGIN
    CALL CalculateGWA(NEW.submission_id);
END //

CREATE TRIGGER tr_grades_after_update
AFTER UPDATE ON grades
FOR EACH ROW
BEGIN
    CALL CalculateGWA(NEW.submission_id);
END //

-- Trigger to create notification when application status changes
CREATE TRIGGER tr_honor_applications_status_change
AFTER UPDATE ON honor_applications
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO notifications (user_id, title, message, type, category)
        VALUES (
            NEW.user_id,
            CONCAT('Honor Application ', UPPER(NEW.status)),
            CONCAT('Your ', REPLACE(NEW.application_type, '_', ' '), ' application has been ', NEW.status, '.'),
            CASE NEW.status
                WHEN 'approved' THEN 'success'
                WHEN 'denied' THEN 'error'
                ELSE 'info'
            END,
            'honor_application'
        );
    END IF;
END //

DELIMITER ;

-- =====================================================
-- CREATE INDEXES FOR PERFORMANCE
-- =====================================================

-- Additional composite indexes for common queries
CREATE INDEX idx_users_dept_year_section ON users(department, year_level, section);
CREATE INDEX idx_gwa_user_period ON gwa_calculations(user_id, academic_period_id);
CREATE INDEX idx_applications_user_period ON honor_applications(user_id, academic_period_id);
CREATE INDEX idx_notifications_user_unread_created ON notifications(user_id, is_read, created_at);
CREATE INDEX idx_grades_submission_subject ON grades(submission_id, subject_code);

-- =====================================================
-- GRANT PERMISSIONS (Adjust as needed)
-- =====================================================

-- Create application user (recommended for production)
-- CREATE USER 'honor_app_user'@'localhost' IDENTIFIED BY 'secure_password_here';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON honor_app.* TO 'honor_app_user'@'localhost';
-- FLUSH PRIVILEGES;

-- =====================================================
-- DATABASE OPTIMIZATION SETTINGS
-- =====================================================

-- Set optimal MySQL settings for the application
SET GLOBAL innodb_buffer_pool_size = 128M;
SET GLOBAL max_connections = 200;
SET GLOBAL query_cache_size = 32M;
SET GLOBAL query_cache_type = 1;

-- =====================================================
-- SAMPLE DATA FOR TESTING
-- =====================================================

-- Insert sample grade submission
INSERT INTO grade_submissions (user_id, academic_period_id, file_path, file_name, file_size, status, processed_at) VALUES
(1, 1, 'uploads/grades/grade_1_sample.pdf', 'john_doe_grades_sem1.pdf', 1024000, 'processed', NOW());

-- Insert sample grades
INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, letter_grade, remarks) VALUES
(1, 'CS101', 'Programming Fundamentals', 3.0, 1.25, 'A-', 'PASSED'),
(1, 'MATH101', 'College Algebra', 3.0, 1.50, 'B+', 'PASSED'),
(1, 'ENG101', 'English Communication', 3.0, 1.75, 'B', 'PASSED'),
(1, 'PE101', 'Physical Education', 2.0, 1.00, 'A', 'PASSED'),
(1, 'NSTP101', 'National Service Training Program', 3.0, 1.25, 'A-', 'PASSED');

-- The CalculateGWA procedure will be automatically called by the trigger

-- Insert sample honor application
INSERT INTO honor_applications (user_id, academic_period_id, gwa_calculation_id, application_type, gwa_achieved, required_gwa, status) VALUES
(1, 1, 1, 'deans_list', 1.350, 1.75, 'approved');

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================

-- Verify table creation
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    DATA_LENGTH,
    INDEX_LENGTH,
    CREATE_TIME
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'honor_app'
ORDER BY TABLE_NAME;

-- Verify sample data
SELECT 'Users' as table_name, COUNT(*) as record_count FROM users
UNION ALL
SELECT 'Academic Periods', COUNT(*) FROM academic_periods
UNION ALL
SELECT 'Grade Submissions', COUNT(*) FROM grade_submissions
UNION ALL
SELECT 'Grades', COUNT(*) FROM grades
UNION ALL
SELECT 'GWA Calculations', COUNT(*) FROM gwa_calculations
UNION ALL
SELECT 'Honor Applications', COUNT(*) FROM honor_applications
UNION ALL
SELECT 'System Settings', COUNT(*) FROM system_settings;

-- Test the student GWA summary view
SELECT * FROM student_gwa_summary LIMIT 5;

-- =====================================================
-- BACKUP AND MAINTENANCE COMMANDS
-- =====================================================

-- Create backup (run from command line)
-- mysqldump -u root -p honor_app > honor_app_backup_$(date +%Y%m%d).sql

-- Restore backup (run from command line)
-- mysql -u root -p honor_app < honor_app_backup_YYYYMMDD.sql

-- Regular maintenance queries
-- OPTIMIZE TABLE users, grades, gwa_calculations, honor_applications;
-- ANALYZE TABLE users, grades, gwa_calculations, honor_applications;

-- =====================================================
-- END OF SCHEMA
-- =====================================================
