-- =====================================================
-- APPLICATION PERIODS TABLE
-- =====================================================
-- This table allows chairpersons to declare when honor applications
-- are open for each semester

CREATE TABLE application_periods (
    -- Primary Key
    id INT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique application period identifier',

    -- Period Information
    semester VARCHAR(20) NOT NULL COMMENT 'Semester (1st Semester, 2nd Semester, Summer)',
    academic_year VARCHAR(20) NOT NULL COMMENT 'Academic year (e.g., 2024-2025)',
    department VARCHAR(100) NOT NULL COMMENT 'Department this period applies to',

    -- Date Range
    start_date DATE NOT NULL COMMENT 'When applications open',
    end_date DATE NOT NULL COMMENT 'When applications close',

    -- Status
    status ENUM('open', 'closed') DEFAULT 'closed' COMMENT 'Whether applications are currently accepted',

    -- Audit Fields
    created_by INT NOT NULL COMMENT 'Chairperson who created this period',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When period was created',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modification',

    -- Constraints and Indexes
    INDEX idx_department (department),
    INDEX idx_semester_year (semester, academic_year),
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_active_periods (department, status, start_date, end_date),

    -- Foreign Key
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,

    -- Unique constraint to prevent duplicate periods per department
    UNIQUE KEY unique_period_dept (semester, academic_year, department)

) ENGINE=InnoDB COMMENT='Honor application periods declared by chairpersons';

-- Insert sample data for testing
INSERT INTO application_periods (semester, academic_year, department, start_date, end_date, status, created_by) VALUES
('1st Semester', '2024-2025', 'Computer Science', '2024-10-01', '2024-10-15', 'open', 1),
('2nd Semester', '2024-2025', 'Computer Science', '2025-03-01', '2025-03-15', 'closed', 1),
('1st Semester', '2024-2025', 'Information Technology', '2024-10-01', '2024-10-15', 'closed', 1);