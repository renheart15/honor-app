-- Add new academic fields to users table
ALTER TABLE users ADD COLUMN college VARCHAR(100) NULL COMMENT 'College (e.g., College of Technology)' AFTER department;

ALTER TABLE users ADD COLUMN course VARCHAR(150) NULL COMMENT 'Course/Program (e.g., Bachelor of Science in Information Technology)' AFTER college;

ALTER TABLE users ADD COLUMN major VARCHAR(100) NULL COMMENT 'Major/Specialization (e.g., Computer Technology)' AFTER course;

-- Update section field to be a single character
ALTER TABLE users MODIFY COLUMN section CHAR(1) NULL COMMENT 'Student section letter (A, B, C, etc.)';

-- Update existing records to populate new fields based on department
-- This is optional - you can leave existing records with NULL values
-- UPDATE users SET
--     college = CASE
--         WHEN department = 'Computer Science' THEN 'College of Technology'
--         WHEN department = 'Information Technology' THEN 'College of Technology'
--         WHEN department = 'Engineering' THEN 'College of Engineering'
--         WHEN department = 'Business Administration' THEN 'College of Arts and Sciences'
--         WHEN department = 'Education' THEN 'College of Education'
--         ELSE NULL
--     END,
--     course = CASE
--         WHEN department = 'Computer Science' THEN 'Bachelor of Science in Computer Science'
--         WHEN department = 'Information Technology' THEN 'Bachelor of Science in Information Technology'
--         WHEN department = 'Engineering' THEN 'Bachelor of Science in Engineering'
--         WHEN department = 'Business Administration' THEN 'Bachelor of Science in Business Administration'
--         WHEN department = 'Education' THEN 'Bachelor of Education'
--         ELSE NULL
--     END
-- WHERE college IS NULL AND course IS NULL;