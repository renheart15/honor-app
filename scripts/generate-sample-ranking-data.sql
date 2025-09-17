-- Generate Sample Data for Testing Honor Rankings
-- This script creates sample students, grades, and GWA calculations

-- Clean existing test data
DELETE FROM honor_rankings WHERE department IN ('Computer Science', 'Information Technology');
DELETE FROM honor_applications WHERE user_id IN (SELECT id FROM users WHERE role = 'student');
DELETE FROM gwa_calculations WHERE user_id IN (SELECT id FROM users WHERE role = 'student');
DELETE FROM grades WHERE submission_id IN (SELECT id FROM grade_submissions WHERE user_id IN (SELECT id FROM users WHERE role = 'student'));
DELETE FROM grade_submissions WHERE user_id IN (SELECT id FROM users WHERE role = 'student');
DELETE FROM users WHERE role = 'student';

-- Insert sample students for Computer Science Department
INSERT INTO users (student_id, email, password, first_name, last_name, role, department, year_level, section, contact_number, status, email_verified) VALUES
-- CS Year 3 Section A (Top performers for Dean's List)
('2024-CS-001', 'alice.johnson@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice', 'Johnson', 'student', 'Computer Science', 3, 'CS-3A', '09123456701', 'active', TRUE),
('2024-CS-002', 'bob.smith@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob', 'Smith', 'student', 'Computer Science', 3, 'CS-3A', '09123456702', 'active', TRUE),
('2024-CS-003', 'charlie.brown@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Charlie', 'Brown', 'student', 'Computer Science', 3, 'CS-3A', '09123456703', 'active', TRUE),
('2024-CS-004', 'diana.wilson@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Diana', 'Wilson', 'student', 'Computer Science', 3, 'CS-3A', '09123456704', 'active', TRUE),
('2024-CS-005', 'edward.davis@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Edward', 'Davis', 'student', 'Computer Science', 3, 'CS-3A', '09123456705', 'active', TRUE),

-- CS Year 3 Section B
('2024-CS-006', 'fiona.miller@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Fiona', 'Miller', 'student', 'Computer Science', 3, 'CS-3B', '09123456706', 'active', TRUE),
('2024-CS-007', 'george.taylor@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'George', 'Taylor', 'student', 'Computer Science', 3, 'CS-3B', '09123456707', 'active', TRUE),
('2024-CS-008', 'helen.anderson@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Helen', 'Anderson', 'student', 'Computer Science', 3, 'CS-3B', '09123456708', 'active', TRUE),

-- CS Year 4 (Latin Honors candidates)
('2024-CS-009', 'ivan.garcia@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ivan', 'Garcia', 'student', 'Computer Science', 4, 'CS-4A', '09123456709', 'active', TRUE),
('2024-CS-010', 'julia.martinez@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Julia', 'Martinez', 'student', 'Computer Science', 4, 'CS-4A', '09123456710', 'active', TRUE),

-- Information Technology students
('2024-IT-001', 'kevin.rodriguez@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kevin', 'Rodriguez', 'student', 'Information Technology', 2, 'IT-2A', '09123456711', 'active', TRUE),
('2024-IT-002', 'linda.hernandez@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Linda', 'Hernandez', 'student', 'Information Technology', 2, 'IT-2A', '09123456712', 'active', TRUE),
('2024-IT-003', 'mike.lopez@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike', 'Lopez', 'student', 'Information Technology', 2, 'IT-2B', '09123456713', 'active', TRUE),
('2024-IT-004', 'nancy.gonzalez@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nancy', 'Gonzalez', 'student', 'Information Technology', 2, 'IT-2B', '09123456714', 'active', TRUE);

-- Get the active academic period ID
SET @active_period_id = (SELECT id FROM academic_periods WHERE is_active = 1 LIMIT 1);

-- Insert grade submissions for each student
INSERT INTO grade_submissions (user_id, academic_period_id, file_path, file_name, file_size, status, processed_at, processed_by) 
SELECT 
    u.id,
    @active_period_id,
    CONCAT('uploads/grades/', u.student_id, '_grades.pdf'),
    CONCAT(u.first_name, '_', u.last_name, '_grades.pdf'),
    FLOOR(1000000 + RAND() * 2000000),
    'processed',
    NOW(),
    (SELECT id FROM users WHERE role = 'adviser' LIMIT 1)
FROM users u 
WHERE u.role = 'student';

-- Insert sample grades for each student
-- Alice Johnson - President's List candidate (GWA: 1.150)
INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, letter_grade, remarks) VALUES
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-001'), 'CS301', 'Data Structures and Algorithms', 3.0, 1.00, 'A', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-001'), 'CS302', 'Software Engineering', 3.0, 1.25, 'A-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-001'), 'CS303', 'Database Systems', 3.0, 1.25, 'A-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-001'), 'MATH301', 'Statistics', 3.0, 1.00, 'A', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-001'), 'ENG301', 'Technical Writing', 3.0, 1.25, 'A-', 'PASSED');

-- Bob Smith - Dean's List candidate (GWA: 1.450)
INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, letter_grade, remarks) VALUES
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-002'), 'CS301', 'Data Structures and Algorithms', 3.0, 1.50, 'B+', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-002'), 'CS302', 'Software Engineering', 3.0, 1.25, 'A-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-002'), 'CS303', 'Database Systems', 3.0, 1.75, 'B', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-002'), 'MATH301', 'Statistics', 3.0, 1.25, 'A-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-002'), 'ENG301', 'Technical Writing', 3.0, 1.50, 'B+', 'PASSED');

-- Charlie Brown - Dean's List candidate (GWA: 1.650)
INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, letter_grade, remarks) VALUES
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-003'), 'CS301', 'Data Structures and Algorithms', 3.0, 1.75, 'B', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-003'), 'CS302', 'Software Engineering', 3.0, 1.50, 'B+', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-003'), 'CS303', 'Database Systems', 3.0, 1.75, 'B', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-003'), 'MATH301', 'Statistics', 3.0, 1.50, 'B+', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-003'), 'ENG301', 'Technical Writing', 3.0, 1.75, 'B', 'PASSED');

-- Diana Wilson - Not eligible (has grade above 2.5)
INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, letter_grade, remarks) VALUES
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-004'), 'CS301', 'Data Structures and Algorithms', 3.0, 1.50, 'B+', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-004'), 'CS302', 'Software Engineering', 3.0, 1.25, 'A-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-004'), 'CS303', 'Database Systems', 3.0, 2.75, 'C+', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-004'), 'MATH301', 'Statistics', 3.0, 1.25, 'A-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-004'), 'ENG301', 'Technical Writing', 3.0, 1.50, 'B+', 'PASSED');

-- Edward Davis - Not eligible (GWA too high: 2.100)
INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, letter_grade, remarks) VALUES
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-005'), 'CS301', 'Data Structures and Algorithms', 3.0, 2.00, 'B-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-005'), 'CS302', 'Software Engineering', 3.0, 2.25, 'C+', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-005'), 'CS303', 'Database Systems', 3.0, 2.00, 'B-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-005'), 'MATH301', 'Statistics', 3.0, 2.25, 'C+', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-005'), 'ENG301', 'Technical Writing', 3.0, 2.00, 'B-', 'PASSED');

-- Fiona Miller - President's List candidate (GWA: 1.200)
INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, letter_grade, remarks) VALUES
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-006'), 'CS301', 'Data Structures and Algorithms', 3.0, 1.25, 'A-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-006'), 'CS302', 'Software Engineering', 3.0, 1.00, 'A', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-006'), 'CS303', 'Database Systems', 3.0, 1.25, 'A-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-006'), 'MATH301', 'Statistics', 3.0, 1.25, 'A-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-006'), 'ENG301', 'Technical Writing', 3.0, 1.25, 'A-', 'PASSED');

-- George Taylor - Dean's List candidate (GWA: 1.500)
INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, letter_grade, remarks) VALUES
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-007'), 'CS301', 'Data Structures and Algorithms', 3.0, 1.50, 'B+', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-007'), 'CS302', 'Software Engineering', 3.0, 1.50, 'B+', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-007'), 'CS303', 'Database Systems', 3.0, 1.50, 'B+', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-007'), 'MATH301', 'Statistics', 3.0, 1.50, 'B+', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-007'), 'ENG301', 'Technical Writing', 3.0, 1.50, 'B+', 'PASSED');

-- Helen Anderson - Dean's List candidate (GWA: 1.750)
INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, letter_grade, remarks) VALUES
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-008'), 'CS301', 'Data Structures and Algorithms', 3.0, 1.75, 'B', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-008'), 'CS302', 'Software Engineering', 3.0, 1.75, 'B', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-008'), 'CS303', 'Database Systems', 3.0, 1.75, 'B', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-008'), 'MATH301', 'Statistics', 3.0, 1.75, 'B', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-008'), 'ENG301', 'Technical Writing', 3.0, 1.75, 'B', 'PASSED');

-- Ivan Garcia - Summa Cum Laude candidate (GWA: 1.200, Year 4)
INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, letter_grade, remarks) VALUES
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-009'), 'CS401', 'Thesis Project 1', 3.0, 1.25, 'A-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-009'), 'CS402', 'Systems Administration', 3.0, 1.00, 'A', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-009'), 'CS403', 'Advanced Algorithms', 3.0, 1.25, 'A-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-009'), 'ELEC401', 'Elective Course', 3.0, 1.25, 'A-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-009'), 'PRAC401', 'Practicum', 3.0, 1.25, 'A-', 'PASSED');

-- Julia Martinez - Magna Cum Laude candidate (GWA: 1.350, Year 4)
INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, letter_grade, remarks) VALUES
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-010'), 'CS401', 'Thesis Project 1', 3.0, 1.50, 'B+', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-010'), 'CS402', 'Systems Administration', 3.0, 1.25, 'A-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-010'), 'CS403', 'Advanced Algorithms', 3.0, 1.25, 'A-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-010'), 'ELEC401', 'Elective Course', 3.0, 1.50, 'B+', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-010'), 'PRAC401', 'Practicum', 3.0, 1.25, 'A-', 'PASSED');

-- Information Technology students (varied performance)
-- Kevin Rodriguez - President's List (GWA: 1.100)
INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, letter_grade, remarks) VALUES
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-001'), 'IT201', 'Web Development', 3.0, 1.00, 'A', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-001'), 'IT202', 'Network Fundamentals', 3.0, 1.25, 'A-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-001'), 'IT203', 'Database Design', 3.0, 1.00, 'A', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-001'), 'MATH201', 'Discrete Mathematics', 3.0, 1.25, 'A-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-001'), 'ENG201', 'Communication Skills', 3.0, 1.00, 'A', 'PASSED');

-- Linda Hernandez - Dean's List (GWA: 1.600)
INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, letter_grade, remarks) VALUES
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-002'), 'IT201', 'Web Development', 3.0, 1.50, 'B+', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-002'), 'IT202', 'Network Fundamentals', 3.0, 1.75, 'B', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-002'), 'IT203', 'Database Design', 3.0, 1.50, 'B+', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-002'), 'MATH201', 'Discrete Mathematics', 3.0, 1.75, 'B', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-002'), 'ENG201', 'Communication Skills', 3.0, 1.50, 'B+', 'PASSED');

-- Mike Lopez - Dean's List (GWA: 1.700)
INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, letter_grade, remarks) VALUES
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-003'), 'IT201', 'Web Development', 3.0, 1.75, 'B', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-003'), 'IT202', 'Network Fundamentals', 3.0, 1.75, 'B', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-003'), 'IT203', 'Database Design', 3.0, 1.50, 'B+', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-003'), 'MATH201', 'Discrete Mathematics', 3.0, 1.75, 'B', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-003'), 'ENG201', 'Communication Skills', 3.0, 1.75, 'B', 'PASSED');

-- Nancy Gonzalez - Not eligible (GWA: 2.000)
INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, letter_grade, remarks) VALUES
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-004'), 'IT201', 'Web Development', 3.0, 2.00, 'B-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-004'), 'IT202', 'Network Fundamentals', 3.0, 2.00, 'B-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-004'), 'IT203', 'Database Design', 3.0, 2.00, 'B-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-004'), 'MATH201', 'Discrete Mathematics', 3.0, 2.00, 'B-', 'PASSED'),
((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-004'), 'ENG201', 'Communication Skills', 3.0, 2.00, 'B-', 'PASSED');

-- Trigger will automatically calculate GWA for each submission
-- Let's also manually calculate to ensure consistency

-- Calculate GWA for each student
CALL CalculateGWA((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-001'));
CALL CalculateGWA((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-002'));
CALL CalculateGWA((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-003'));
CALL CalculateGWA((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-004'));
CALL CalculateGWA((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-005'));
CALL CalculateGWA((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-006'));
CALL CalculateGWA((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-007'));
CALL CalculateGWA((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-008'));
CALL CalculateGWA((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-009'));
CALL CalculateGWA((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-CS-010'));
CALL CalculateGWA((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-001'));
CALL CalculateGWA((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-002'));
CALL CalculateGWA((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-003'));
CALL CalculateGWA((SELECT id FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.student_id = '2024-IT-004'));

-- Update the has_grade_above_25 flag for all GWA calculations
UPDATE gwa_calculations gwa
JOIN grade_submissions gs ON gwa.submission_id = gs.id
SET gwa.has_grade_above_25 = (
    SELECT COUNT(*) > 0
    FROM grades g
    WHERE g.submission_id = gs.id
    AND g.grade > 2.5
);

-- Generate rankings for both departments
CALL GenerateHonorRankings(@active_period_id, 'Computer Science');
CALL GenerateHonorRankings(@active_period_id, 'Information Technology');

-- Verify the generated data
SELECT 'Sample Data Summary' as info;

SELECT 
    'Students Created' as category,
    COUNT(*) as count
FROM users 
WHERE role = 'student';

SELECT 
    'GWA Calculations' as category,
    COUNT(*) as count
FROM gwa_calculations;

SELECT 
    'Honor Rankings Generated' as category,
    COUNT(*) as count
FROM honor_rankings;

SELECT 
    'Rankings by Department and Type' as category,
    department,
    ranking_type,
    COUNT(*) as student_count
FROM honor_rankings
GROUP BY department, ranking_type
ORDER BY department, ranking_type;

-- Show top performers
SELECT 
    'Top Performers by Department' as info,
    u.department,
    u.student_id,
    CONCAT(u.first_name, ' ', u.last_name) as name,
    u.year_level,
    u.section,
    hr.ranking_type,
    hr.gwa,
    hr.rank_position
FROM honor_rankings hr
JOIN users u ON hr.user_id = u.id
WHERE hr.rank_position <= 3
ORDER BY u.department, hr.ranking_type, hr.rank_position;