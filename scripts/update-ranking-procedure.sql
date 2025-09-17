-- Enhanced Honor Rankings Stored Procedure
-- Includes proper eligibility checks and comprehensive ranking logic

DELIMITER //

-- Drop existing procedure
DROP PROCEDURE IF EXISTS GenerateHonorRankings //

-- Create enhanced procedure for generating honor rankings
CREATE PROCEDURE GenerateHonorRankings(IN p_academic_period_id INT, IN p_department VARCHAR(100))
BEGIN
    -- Clear existing rankings for this period and department
    DELETE FROM honor_rankings 
    WHERE academic_period_id = p_academic_period_id 
    AND department = p_department;
    
    -- Generate Dean's List rankings (GWA <= 1.75 and no grade above 2.5)
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
    LEFT JOIN (
        -- Check for grades above 2.5
        SELECT gs.user_id, COUNT(*) as high_grades
        FROM grade_submissions gs
        JOIN grades g ON gs.id = g.submission_id
        WHERE gs.academic_period_id = p_academic_period_id
        AND g.grade > 2.5
        GROUP BY gs.user_id
    ) high_grade_check ON u.id = high_grade_check.user_id
    WHERE u.department = p_department
    AND u.role = 'student'
    AND u.status = 'active'
    AND gwa.academic_period_id = p_academic_period_id
    AND gwa.gwa <= 1.75
    AND (high_grade_check.high_grades IS NULL OR high_grade_check.high_grades = 0)
    AND gwa.failed_subjects = 0
    AND gwa.incomplete_subjects = 0;
    
    -- Generate President's List rankings (GWA <= 1.25 and no grade above 2.5)
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
    LEFT JOIN (
        -- Check for grades above 2.5
        SELECT gs.user_id, COUNT(*) as high_grades
        FROM grade_submissions gs
        JOIN grades g ON gs.id = g.submission_id
        WHERE gs.academic_period_id = p_academic_period_id
        AND g.grade > 2.5
        GROUP BY gs.user_id
    ) high_grade_check ON u.id = high_grade_check.user_id
    WHERE u.department = p_department
    AND u.role = 'student'
    AND u.status = 'active'
    AND gwa.academic_period_id = p_academic_period_id
    AND gwa.gwa <= 1.25
    AND (high_grade_check.high_grades IS NULL OR high_grade_check.high_grades = 0)
    AND gwa.failed_subjects = 0
    AND gwa.incomplete_subjects = 0;
    
    -- Generate Overall rankings (all students with valid GWA)
    INSERT INTO honor_rankings (
        academic_period_id, department, year_level, section, 
        ranking_type, user_id, gwa, rank_position, total_students
    )
    SELECT 
        p_academic_period_id,
        p_department,
        u.year_level,
        u.section,
        'overall',
        u.id,
        gwa.gwa,
        ROW_NUMBER() OVER (PARTITION BY u.year_level, u.section ORDER BY gwa.gwa ASC),
        COUNT(*) OVER (PARTITION BY u.year_level, u.section)
    FROM users u
    JOIN gwa_calculations gwa ON u.id = gwa.user_id
    WHERE u.department = p_department
    AND u.role = 'student'
    AND u.status = 'active'
    AND gwa.academic_period_id = p_academic_period_id;
    
    -- Generate Department-wide rankings (no section filter)
    INSERT INTO honor_rankings (
        academic_period_id, department, year_level, section, 
        ranking_type, user_id, gwa, rank_position, total_students
    )
    SELECT 
        p_academic_period_id,
        p_department,
        u.year_level,
        NULL as section,
        'deans_list',
        u.id,
        gwa.gwa,
        ROW_NUMBER() OVER (PARTITION BY u.year_level ORDER BY gwa.gwa ASC),
        COUNT(*) OVER (PARTITION BY u.year_level)
    FROM users u
    JOIN gwa_calculations gwa ON u.id = gwa.user_id
    LEFT JOIN (
        SELECT gs.user_id, COUNT(*) as high_grades
        FROM grade_submissions gs
        JOIN grades g ON gs.id = g.submission_id
        WHERE gs.academic_period_id = p_academic_period_id
        AND g.grade > 2.5
        GROUP BY gs.user_id
    ) high_grade_check ON u.id = high_grade_check.user_id
    WHERE u.department = p_department
    AND u.role = 'student'
    AND u.status = 'active'
    AND gwa.academic_period_id = p_academic_period_id
    AND gwa.gwa <= 1.75
    AND (high_grade_check.high_grades IS NULL OR high_grade_check.high_grades = 0)
    AND gwa.failed_subjects = 0
    AND gwa.incomplete_subjects = 0;
    
    -- Generate Year-level wide rankings
    INSERT INTO honor_rankings (
        academic_period_id, department, year_level, section, 
        ranking_type, user_id, gwa, rank_position, total_students
    )
    SELECT 
        p_academic_period_id,
        p_department,
        NULL as year_level,
        NULL as section,
        'deans_list',
        u.id,
        gwa.gwa,
        ROW_NUMBER() OVER (ORDER BY gwa.gwa ASC),
        COUNT(*) OVER ()
    FROM users u
    JOIN gwa_calculations gwa ON u.id = gwa.user_id
    LEFT JOIN (
        SELECT gs.user_id, COUNT(*) as high_grades
        FROM grade_submissions gs
        JOIN grades g ON gs.id = g.submission_id
        WHERE gs.academic_period_id = p_academic_period_id
        AND g.grade > 2.5
        GROUP BY gs.user_id
    ) high_grade_check ON u.id = high_grade_check.user_id
    WHERE u.department = p_department
    AND u.role = 'student'
    AND u.status = 'active'
    AND gwa.academic_period_id = p_academic_period_id
    AND gwa.gwa <= 1.75
    AND (high_grade_check.high_grades IS NULL OR high_grade_check.high_grades = 0)
    AND gwa.failed_subjects = 0
    AND gwa.incomplete_subjects = 0;

END //

-- Create procedure to get ranking summary
CREATE PROCEDURE GetRankingSummary(IN p_academic_period_id INT, IN p_department VARCHAR(100))
BEGIN
    SELECT 
        ranking_type,
        year_level,
        section,
        COUNT(*) as total_students,
        MIN(gwa) as best_gwa,
        MAX(gwa) as lowest_gwa,
        AVG(gwa) as average_gwa,
        MIN(rank_position) as top_rank,
        MAX(rank_position) as last_rank
    FROM honor_rankings
    WHERE academic_period_id = p_academic_period_id
    AND department = p_department
    GROUP BY ranking_type, year_level, section
    ORDER BY ranking_type, year_level, section;
END //

-- Create procedure to get student ranking details
CREATE PROCEDURE GetStudentRankingDetails(IN p_user_id INT, IN p_academic_period_id INT)
BEGIN
    SELECT 
        hr.*,
        u.student_id,
        u.first_name,
        u.last_name,
        u.department,
        u.year_level,
        u.section,
        ap.period_name,
        ap.school_year,
        ap.semester
    FROM honor_rankings hr
    JOIN users u ON hr.user_id = u.id
    JOIN academic_periods ap ON hr.academic_period_id = ap.id
    WHERE hr.user_id = p_user_id
    AND hr.academic_period_id = p_academic_period_id
    ORDER BY hr.ranking_type, hr.year_level, hr.section;
END //

DELIMITER ;

-- Update the has_grade_above_25 column in gwa_calculations if it doesn't exist
ALTER TABLE gwa_calculations 
ADD COLUMN IF NOT EXISTS has_grade_above_25 BOOLEAN DEFAULT FALSE 
AFTER incomplete_subjects;

-- Create a procedure to update the has_grade_above_25 flag
DELIMITER //

CREATE PROCEDURE UpdateGradeAbove25Flags()
BEGIN
    UPDATE gwa_calculations gwa
    JOIN grade_submissions gs ON gwa.submission_id = gs.id
    SET gwa.has_grade_above_25 = (
        SELECT COUNT(*) > 0
        FROM grades g
        WHERE g.submission_id = gs.id
        AND g.grade > 2.5
    );
END //

DELIMITER ;

-- Update the student_gwa_summary view to include the new logic
DROP VIEW IF EXISTS student_gwa_summary;

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
    gwa.failed_subjects,
    gwa.has_grade_above_25,
    gwa.calculated_at,
    CASE 
        WHEN u.year_level = 4 AND gwa.gwa >= 1.00 AND gwa.gwa <= 1.25 THEN 'Summa Cum Laude'
        WHEN u.year_level = 4 AND gwa.gwa >= 1.26 AND gwa.gwa <= 1.45 THEN 'Magna Cum Laude'
        WHEN u.year_level = 4 AND gwa.gwa >= 1.46 AND gwa.gwa <= 1.75 THEN 'Cum Laude'
        WHEN gwa.gwa <= 1.25 AND gwa.has_grade_above_25 = 0 AND gwa.failed_subjects = 0 THEN 'Presidents List'
        WHEN gwa.gwa <= 1.75 AND gwa.has_grade_above_25 = 0 AND gwa.failed_subjects = 0 THEN 'Deans List'
        ELSE 'Regular'
    END as honor_classification,
    CASE 
        WHEN gwa.gwa <= 1.25 AND gwa.has_grade_above_25 = 0 AND gwa.failed_subjects = 0 THEN 1
        WHEN gwa.gwa <= 1.75 AND gwa.has_grade_above_25 = 0 AND gwa.failed_subjects = 0 THEN 1
        ELSE 0
    END as is_honor_eligible
FROM users u
JOIN gwa_calculations gwa ON u.id = gwa.user_id
JOIN academic_periods ap ON gwa.academic_period_id = ap.id
WHERE u.role = 'student' AND u.status = 'active';

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_honor_rankings_type_dept_year_section 
ON honor_rankings(ranking_type, department, year_level, section);

CREATE INDEX IF NOT EXISTS idx_honor_rankings_gwa_rank 
ON honor_rankings(gwa, rank_position);

CREATE INDEX IF NOT EXISTS idx_gwa_calculations_has_grade_above_25 
ON gwa_calculations(has_grade_above_25);