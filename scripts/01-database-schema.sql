-- Add column to track if student has any grade above 2.5 (affects Dean's List eligibility)
ALTER TABLE gwa_calculations ADD COLUMN has_grade_above_25 BOOLEAN DEFAULT FALSE AFTER incomplete_subjects;

-- Update the honor_applications table to include cum_laude
ALTER TABLE honor_applications MODIFY COLUMN application_type ENUM('deans_list', 'cum_laude', 'magna_cum_laude', 'summa_cum_laude') NOT NULL;

-- Update system settings for new honor requirements
UPDATE system_settings SET 
    setting_value = '1.75',
    description = 'Maximum GWA for Deans List (1.75 and below, no grade above 2.5)'
WHERE setting_key = 'deans_list_threshold';

INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description) VALUES
('summa_cum_laude_min', '1.00', 'number', 'honors', 'Minimum GWA for Summa Cum Laude'),
('summa_cum_laude_max', '1.25', 'number', 'honors', 'Maximum GWA for Summa Cum Laude'),
('magna_cum_laude_min', '1.26', 'number', 'honors', 'Minimum GWA for Magna Cum Laude'),
('magna_cum_laude_max', '1.45', 'number', 'honors', 'Maximum GWA for Magna Cum Laude'),
('cum_laude_min', '1.46', 'number', 'honors', 'Minimum GWA for Cum Laude'),
('cum_laude_max', '1.75', 'number', 'honors', 'Maximum GWA for Cum Laude'),
('latin_honors_year_requirement', '4', 'number', 'honors', 'Required year level for Latin Honors'),
('deans_list_max_grade', '2.5', 'number', 'honors', 'Maximum individual grade allowed for Deans List')
ON DUPLICATE KEY UPDATE 
setting_value = VALUES(setting_value),
description = VALUES(description);

-- Update the honor_applications table application_type enum
ALTER TABLE honor_applications 
MODIFY COLUMN application_type ENUM('deans_list', 'cum_laude', 'magna_cum_laude', 'summa_cum_laude') NOT NULL;

-- Update the student_gwa_summary view to reflect new honor classifications
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
        WHEN gwa.gwa <= 1.75 AND gwa.has_grade_above_25 = 0 THEN 'Deans List'
        ELSE 'Regular'
    END as honor_classification
FROM users u
JOIN gwa_calculations gwa ON u.id = gwa.user_id
JOIN academic_periods ap ON gwa.academic_period_id = ap.id
WHERE u.role = 'student' AND u.status = 'active';
