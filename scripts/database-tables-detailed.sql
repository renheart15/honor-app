-- =====================================================
-- CTU HONOR APPLICATION SYSTEM - COMPLETE TABLE STRUCTURE
-- =====================================================

-- =====================================================
-- 1. USERS TABLE
-- =====================================================
CREATE TABLE users (
    -- Primary Key
    id INT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique user identifier',
    
    -- Authentication Fields
    student_id VARCHAR(20) UNIQUE NULL COMMENT 'Student ID number (null for advisers/chairpersons)',
    email VARCHAR(100) UNIQUE NOT NULL COMMENT 'User email address (login credential)',
    password VARCHAR(255) NOT NULL COMMENT 'Hashed password using password_hash()',
    
    -- Personal Information
    first_name VARCHAR(50) NOT NULL COMMENT 'User first name',
    last_name VARCHAR(50) NOT NULL COMMENT 'User last name',
    middle_name VARCHAR(50) NULL COMMENT 'User middle name (optional)',
    
    -- Role and Academic Info
    role ENUM('student', 'adviser', 'chairperson') NOT NULL DEFAULT 'student' COMMENT 'User role in system',
    department VARCHAR(100) NULL COMMENT 'Academic department (e.g., Computer Science)',
    year_level INT NULL COMMENT 'Student year level (1-4, null for non-students)',
    section VARCHAR(10) NULL COMMENT 'Student section (e.g., CS-3A, null for non-students)',
    
    -- Contact Information
    contact_number VARCHAR(15) NULL COMMENT 'Phone/mobile number',
    address TEXT NULL COMMENT 'Complete address',
    
    -- Account Status
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active' COMMENT 'Account status',
    email_verified BOOLEAN DEFAULT FALSE COMMENT 'Email verification status',
    last_login TIMESTAMP NULL COMMENT 'Last login timestamp',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Account creation date',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update date',
    
    -- Indexes for performance
    INDEX idx_email (email),
    INDEX idx_student_id (student_id),
    INDEX idx_role (role),
    INDEX idx_department (department),
    INDEX idx_status (status),
    INDEX idx_dept_year_section (department, year_level, section)
) ENGINE=InnoDB COMMENT='User accounts for students, advisers, and chairpersons';

-- =====================================================
-- 2. ACADEMIC_PERIODS TABLE
-- =====================================================
CREATE TABLE academic_periods (
    -- Primary Key
    id INT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique period identifier',
    
    -- Period Information
    period_name VARCHAR(50) NOT NULL COMMENT 'Display name (e.g., First Semester 2024-2025)',
    school_year VARCHAR(20) NOT NULL COMMENT 'Academic year (e.g., 2024-2025)',
    semester ENUM('1st', '2nd', 'summer') NOT NULL COMMENT 'Semester type',
    
    -- Date Range
    start_date DATE NOT NULL COMMENT 'Period start date',
    end_date DATE NOT NULL COMMENT 'Period end date',
    
    -- Status and Deadlines
    is_active BOOLEAN DEFAULT FALSE COMMENT 'Only one period can be active at a time',
    registration_deadline DATE NULL COMMENT 'Deadline for grade submissions',
    application_deadline DATE NULL COMMENT 'Deadline for honor applications',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation date',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update date',
    
    -- Constraints and Indexes
    UNIQUE KEY unique_active_period (is_active, school_year, semester),
    INDEX idx_school_year (school_year),
    INDEX idx_active (is_active),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB COMMENT='Academic periods/semesters management';

-- =====================================================
-- 3. GRADE_SUBMISSIONS TABLE
-- =====================================================
CREATE TABLE grade_submissions (
    -- Primary Key
    id INT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique submission identifier',
    
    -- Foreign Keys
    user_id INT NOT NULL COMMENT 'Student who submitted the grades',
    academic_period_id INT NOT NULL COMMENT 'Academic period for this submission',
    
    -- File Information
    file_path VARCHAR(500) NOT NULL COMMENT 'Server path to uploaded PDF file',
    file_name VARCHAR(255) NOT NULL COMMENT 'Original filename from upload',
    file_size INT NOT NULL COMMENT 'File size in bytes',
    mime_type VARCHAR(100) NOT NULL DEFAULT 'application/pdf' COMMENT 'File MIME type',
    
    -- Processing Information
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When file was uploaded',
    status ENUM('pending', 'processing', 'processed', 'rejected', 'failed') DEFAULT 'pending' COMMENT 'Processing status',
    processed_at TIMESTAMP NULL COMMENT 'When processing completed',
    processed_by INT NULL COMMENT 'ID of user who processed (adviser/chairperson)',
    
    -- Additional Information
    rejection_reason TEXT NULL COMMENT 'Reason for rejection if status is rejected',
    notes TEXT NULL COMMENT 'Additional notes from processor',
    
    -- Foreign Key Constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE RESTRICT,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Constraints and Indexes
    UNIQUE KEY unique_user_period (user_id, academic_period_id),
    INDEX idx_status (status),
    INDEX idx_upload_date (upload_date),
    INDEX idx_processed_by (processed_by)
) ENGINE=InnoDB COMMENT='Student grade report submissions';

-- =====================================================
-- 4. GRADES TABLE
-- =====================================================
CREATE TABLE grades (
    -- Primary Key
    id INT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique grade record identifier',
    
    -- Foreign Key
    submission_id INT NOT NULL COMMENT 'Reference to grade submission',
    
    -- Subject Information
    subject_code VARCHAR(20) NOT NULL COMMENT 'Subject code (e.g., CS101, MATH201)',
    subject_name VARCHAR(150) NOT NULL COMMENT 'Full subject name',
    units DECIMAL(3,1) NOT NULL COMMENT 'Credit units (e.g., 3.0, 1.5)',
    
    -- Grade Information
    grade DECIMAL(3,2) NOT NULL COMMENT 'Numerical grade (e.g., 1.25, 2.50)',
    letter_grade VARCHAR(5) NULL COMMENT 'Letter equivalent (A, B+, etc.)',
    remarks VARCHAR(50) NULL COMMENT 'PASSED, FAILED, INC, etc.',
    
    -- Additional Information
    semester_taken VARCHAR(20) NULL COMMENT 'When subject was taken',
    adviser_name VARCHAR(100) NULL COMMENT 'Subject adviser',
    
    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation date',
    
    -- Foreign Key Constraint
    FOREIGN KEY (submission_id) REFERENCES grade_submissions(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_submission (submission_id),
    INDEX idx_subject_code (subject_code),
    INDEX idx_grade (grade),
    INDEX idx_submission_subject (submission_id, subject_code)
) ENGINE=InnoDB COMMENT='Individual subject grades extracted from submissions';

-- =====================================================
-- 5. GWA_CALCULATIONS TABLE
-- =====================================================
CREATE TABLE gwa_calculations (
    -- Primary Key
    id INT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique calculation identifier',
    
    -- Foreign Keys
    user_id INT NOT NULL COMMENT 'Student for this GWA calculation',
    academic_period_id INT NOT NULL COMMENT 'Academic period',
    submission_id INT NOT NULL COMMENT 'Grade submission used for calculation',
    
    -- Calculation Results
    total_units DECIMAL(6,1) NOT NULL COMMENT 'Total credit units',
    total_grade_points DECIMAL(10,2) NOT NULL COMMENT 'Sum of (grade × units)',
    gwa DECIMAL(4,3) NOT NULL COMMENT 'General Weighted Average',
    
    -- Additional Statistics
    subjects_count INT NOT NULL COMMENT 'Number of subjects',
    failed_subjects INT DEFAULT 0 COMMENT 'Number of failed subjects (grade >= 3.0)',
    incomplete_subjects INT DEFAULT 0 COMMENT 'Number of incomplete subjects',
    
    -- Calculation Metadata
    calculation_method VARCHAR(50) DEFAULT 'weighted_average' COMMENT 'Method used for calculation',
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When GWA was calculated',
    recalculated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'When GWA was recalculated',
    
    -- Foreign Key Constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE RESTRICT,
    FOREIGN KEY (submission_id) REFERENCES grade_submissions(id) ON DELETE CASCADE,
    
    -- Constraints and Indexes
    UNIQUE KEY unique_user_period_gwa (user_id, academic_period_id),
    INDEX idx_gwa (gwa),
    INDEX idx_calculated_at (calculated_at),
    INDEX idx_user_period (user_id, academic_period_id)
) ENGINE=InnoDB COMMENT='Computed GWA for each student per academic period';

-- =====================================================
-- 6. HONOR_APPLICATIONS TABLE
-- =====================================================
CREATE TABLE honor_applications (
    -- Primary Key
    id INT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique application identifier',
    
    -- Foreign Keys
    user_id INT NOT NULL COMMENT 'Student applying for honor',
    academic_period_id INT NOT NULL COMMENT 'Academic period for application',
    gwa_calculation_id INT NOT NULL COMMENT 'GWA calculation used for application',
    
    -- Application Details
    application_type ENUM('deans_list', 'presidents_list', 'magna_cum_laude', 'summa_cum_laude') NOT NULL COMMENT 'Type of honor applied for',
    gwa_achieved DECIMAL(4,3) NOT NULL COMMENT 'GWA at time of application',
    required_gwa DECIMAL(4,3) NOT NULL COMMENT 'Required GWA for this honor type',
    
    -- Application Status
    status ENUM('submitted', 'under_review', 'approved', 'denied', 'cancelled') DEFAULT 'submitted' COMMENT 'Application status',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When application was submitted',
    reviewed_at TIMESTAMP NULL COMMENT 'When application was reviewed',
    reviewed_by INT NULL COMMENT 'Adviser/Chairperson who reviewed',
    approval_date DATE NULL COMMENT 'Date of approval',
    
    -- Additional Information
    remarks TEXT NULL COMMENT 'Review comments',
    certificate_generated BOOLEAN DEFAULT FALSE COMMENT 'Whether certificate was generated',
    certificate_path VARCHAR(500) NULL COMMENT 'Path to generated certificate',
    ranking_position INT NULL COMMENT 'Position in honor roll ranking',
    
    -- Foreign Key Constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE RESTRICT,
    FOREIGN KEY (gwa_calculation_id) REFERENCES gwa_calculations(id) ON DELETE RESTRICT,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Constraints and Indexes
    UNIQUE KEY unique_user_period_type (user_id, academic_period_id, application_type),
    INDEX idx_status (status),
    INDEX idx_application_type (application_type),
    INDEX idx_submitted_at (submitted_at),
    INDEX idx_gwa_achieved (gwa_achieved),
    INDEX idx_reviewed_by (reviewed_by)
) ENGINE=InnoDB COMMENT='Honor applications (Deans List, Presidents List, etc.)';

-- =====================================================
-- 7. NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE notifications (
    -- Primary Key
    id INT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique notification identifier',
    
    -- Foreign Key
    user_id INT NOT NULL COMMENT 'User receiving the notification',
    
    -- Notification Content
    title VARCHAR(255) NOT NULL COMMENT 'Notification title',
    message TEXT NOT NULL COMMENT 'Notification message content',
    type ENUM('info', 'success', 'warning', 'error', 'system') DEFAULT 'info' COMMENT 'Notification type for styling',
    category ENUM('grade_upload', 'gwa_calculation', 'honor_application', 'system_update', 'general') DEFAULT 'general' COMMENT 'Notification category',
    
    -- Status Information
    is_read BOOLEAN DEFAULT FALSE COMMENT 'Whether notification has been read',
    is_email_sent BOOLEAN DEFAULT FALSE COMMENT 'Whether email notification was sent',
    email_sent_at TIMESTAMP NULL COMMENT 'When email was sent',
    
    -- Action Information
    action_url VARCHAR(500) NULL COMMENT 'URL for notification action button',
    action_text VARCHAR(50) NULL COMMENT 'Text for action button',
    
    -- Expiration
    expires_at TIMESTAMP NULL COMMENT 'When notification expires',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When notification was created',
    read_at TIMESTAMP NULL COMMENT 'When notification was read',
    
    -- Foreign Key Constraint
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_created_at (created_at),
    INDEX idx_type (type),
    INDEX idx_category (category),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB COMMENT='System notifications for users';

-- =====================================================
-- 8. SYSTEM_SETTINGS TABLE
-- =====================================================
CREATE TABLE system_settings (
    -- Primary Key
    id INT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique setting identifier',
    
    -- Setting Information
    setting_key VARCHAR(100) UNIQUE NOT NULL COMMENT 'Unique setting key',
    setting_value TEXT NOT NULL COMMENT 'Setting value (can be long text)',
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string' COMMENT 'Data type of setting value',
    category VARCHAR(50) DEFAULT 'general' COMMENT 'Setting category for organization',
    
    -- Metadata
    description TEXT NULL COMMENT 'Description of what this setting does',
    is_public BOOLEAN DEFAULT FALSE COMMENT 'Can be accessed by non-admin users',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When setting was created',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When setting was last updated',
    updated_by INT NULL COMMENT 'User who last updated this setting',
    
    -- Foreign Key Constraint
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_category (category),
    INDEX idx_public (is_public),
    INDEX idx_updated_by (updated_by)
) ENGINE=InnoDB COMMENT='System configuration settings';

-- =====================================================
-- 9. HONOR_RANKINGS TABLE
-- =====================================================
CREATE TABLE honor_rankings (
    -- Primary Key
    id INT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique ranking identifier',
    
    -- Foreign Keys
    academic_period_id INT NOT NULL COMMENT 'Academic period for ranking',
    user_id INT NOT NULL COMMENT 'Student being ranked',
    
    -- Ranking Scope
    department VARCHAR(100) NOT NULL COMMENT 'Department for ranking',
    year_level INT NULL COMMENT 'Year level (NULL for department-wide ranking)',
    section VARCHAR(10) NULL COMMENT 'Section (NULL for year-level ranking)',
    ranking_type ENUM('deans_list', 'presidents_list', 'overall') NOT NULL COMMENT 'Type of ranking',
    
    -- Ranking Information
    gwa DECIMAL(4,3) NOT NULL COMMENT 'Student GWA for ranking',
    rank_position INT NOT NULL COMMENT 'Position in ranking (1 = highest)',
    total_students INT NOT NULL COMMENT 'Total students in this ranking category',
    percentile DECIMAL(5,2) NULL COMMENT 'Percentile ranking (0-100)',
    
    -- Generation Information
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When ranking was generated',
    generated_by INT NULL COMMENT 'User who generated the ranking',
    
    -- Foreign Key Constraints
    FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Constraints and Indexes
    UNIQUE KEY unique_ranking (academic_period_id, department, year_level, section, ranking_type, user_id),
    INDEX idx_ranking_type (ranking_type),
    INDEX idx_department (department),
    INDEX idx_rank_position (rank_position),
    INDEX idx_gwa (gwa),
    INDEX idx_generated_by (generated_by)
) ENGINE=InnoDB COMMENT='Honor roll rankings by department, year, and section';

-- =====================================================
-- 10. AUDIT_LOGS TABLE
-- =====================================================
CREATE TABLE audit_logs (
    -- Primary Key
    id INT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique log entry identifier',
    
    -- User Information
    user_id INT NULL COMMENT 'User who performed the action (NULL for system actions)',
    
    -- Action Information
    action VARCHAR(100) NOT NULL COMMENT 'Action performed (login, upload, approve, etc.)',
    table_name VARCHAR(50) NULL COMMENT 'Database table affected',
    record_id INT NULL COMMENT 'ID of affected record',
    
    -- Change Information
    old_values JSON NULL COMMENT 'Previous values (for updates)',
    new_values JSON NULL COMMENT 'New values (for inserts/updates)',
    
    -- Session Information
    ip_address VARCHAR(45) NULL COMMENT 'IP address of user',
    user_agent TEXT NULL COMMENT 'Browser user agent string',
    session_id VARCHAR(128) NULL COMMENT 'Session identifier',
    
    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When action was performed',
    
    -- Foreign Key Constraint
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_table_name (table_name),
    INDEX idx_created_at (created_at),
    INDEX idx_record_id (record_id)
) ENGINE=InnoDB COMMENT='Audit trail for system actions';
