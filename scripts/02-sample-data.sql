-- Insert additional sample data for testing

-- Insert more academic periods
INSERT INTO academic_periods (period_name, school_year, semester, start_date, end_date, is_active, registration_deadline, application_deadline) VALUES
('Summer 2024', '2023-2024', 'summer', '2024-05-15', '2024-07-30', FALSE, '2024-08-05', '2024-08-10'),
('Second Semester 2023-2024', '2023-2024', '2nd', '2024-01-15', '2024-05-30', FALSE, '2024-06-05', '2024-06-10');

-- Insert more sample users
INSERT INTO users (student_id, email, password, first_name, last_name, role, department, year_level, section, contact_number, status, email_verified) VALUES
-- More Students
('2024-004', 'alice.brown@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice', 'Brown', 'student', 'Computer Science', 4, 'CS-4A', '09123456796', 'active', TRUE),
('2024-005', 'bob.wilson@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob', 'Wilson', 'student', 'Computer Science', 2, 'CS-2B', '09123456797', 'active', TRUE),
('2024-006', 'carol.davis@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carol', 'Davis', 'student', 'Information Technology', 3, 'IT-3A', '09123456798', 'active', TRUE),
('2024-007', 'david.miller@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'David', 'Miller', 'student', 'Information Technology', 1, 'IT-1A', '09123456799', 'active', TRUE),
('2024-008', 'eva.garcia@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Eva', 'Garcia', 'student', 'Engineering', 3, 'ENG-3A', '09123456800', 'active', TRUE),

-- More Advisers
('ADV-003', 'prof.santos@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Prof. Carlos', 'Santos', 'adviser', 'Engineering', NULL, NULL, '09123456801', 'active', TRUE),
('ADV-004', 'prof.reyes@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Prof. Ana', 'Reyes', 'adviser', 'Business Administration', NULL, NULL, '09123456802', 'active', TRUE),

-- More Chairpersons
('CHAIR-003', 'dean.engineering@ctu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Michael', 'Torres', 'chairperson', 'Engineering', NULL, NULL, '09123456803', 'active', TRUE);

-- Insert more grade submissions
INSERT INTO grade_submissions (user_id, academic_period_id, file_path, file_name, file_size, status, processed_at, processed_by) VALUES
(2, 1, 'uploads/grades/grade_2_sample.pdf', 'jane_smith_grades_sem1.pdf', 1156000, 'processed', NOW(), 4),
(3, 1, 'uploads/grades/grade_3_sample.pdf', 'mike_johnson_grades_sem1.pdf', 987000, 'processed', NOW(), 5),
(4, 1, 'uploads/grades/grade_4_sample.pdf', 'alice_brown_grades_sem1.pdf', 1234000, 'pending', NULL, NULL),
(5, 1, 'uploads/grades/grade_5_sample.pdf', 'bob_wilson_grades_sem1.pdf', 1098000, 'processed', NOW(), 4);

-- Insert more grades for different students
INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, letter_grade, remarks) VALUES
-- Grades for Jane Smith (submission_id = 2)
(2, 'CS102', 'Data Structures and Algorithms', 3.0, 1.00, 'A', 'PASSED'),
(2, 'MATH102', 'Discrete Mathematics', 3.0, 1.25, 'A-', 'PASSED'),
(2, 'ENG102', 'Technical Writing', 3.0, 1.50, 'B+', 'PASSED'),
(2, 'PE102', 'Physical Education 2', 2.0, 1.00, 'A', 'PASSED'),
(2, 'NSTP102', 'NSTP 2', 3.0, 1.00, 'A', 'PASSED'),

-- Grades for Mike Johnson (submission_id = 3)
(3, 'IT101', 'Introduction to Information Technology', 3.0, 1.75, 'B', 'PASSED'),
(3, 'PROG101', 'Programming Logic and Design', 3.0, 2.00, 'B-', 'PASSED'),
(3, 'MATH101', 'College Algebra', 3.0, 1.50, 'B+', 'PASSED'),
(3, 'ENG101', 'English Communication', 3.0, 1.75, 'B', 'PASSED'),
(3, 'PE101', 'Physical Education 1', 2.0, 1.25, 'A-', 'PASSED'),

-- Grades for Bob Wilson (submission_id = 5)
(5, 'CS201', 'Object-Oriented Programming', 3.0, 1.50, 'B+', 'PASSED'),
(5, 'MATH201', 'Statistics and Probability', 3.0, 1.75, 'B', 'PASSED'),
(5, 'DB101', 'Database Management Systems', 3.0, 1.25, 'A-', 'PASSED'),
(5, 'WEB101', 'Web Development', 3.0, 1.00, 'A', 'PASSED'),
(5, 'ELEC101', 'Technical Elective 1', 3.0, 1.50, 'B+', 'PASSED');

-- Insert more GWA calculations (these will be auto-calculated by triggers, but we'll insert manually for demo)
INSERT INTO gwa_calculations (user_id, academic_period_id, submission_id, total_units, total_grade_points, gwa, subjects_count, failed_subjects, incomplete_subjects) VALUES
(2, 1, 2, 14.0, 16.50, 1.179, 5, 0, 0),
(3, 1, 3, 14.0, 24.25, 1.732, 5, 0, 0),
(5, 1, 5, 15.0, 20.25, 1.350, 5, 0, 0);

-- Insert honor applications
INSERT INTO honor_applications (user_id, academic_period_id, gwa_calculation_id, application_type, gwa_achieved, required_gwa, status, reviewed_by, approval_date) VALUES
(2, 1, 2, 'presidents_list', 1.179, 1.25, 'approved', 6, CURDATE()),
(3, 1, 3, 'deans_list', 1.732, 1.75, 'approved', 6, CURDATE()),
(5, 1, 5, 'deans_list', 1.350, 1.75, 'submitted', NULL, NULL);

-- Insert notifications
INSERT INTO notifications (user_id, title, message, type, category, is_read) VALUES
(1, 'Grade Processing Complete', 'Your grade submission has been processed and your GWA has been calculated.', 'success', 'gwa_calculation', FALSE),
(1, 'Honor Application Approved', 'Congratulations! Your Dean\'s List application has been approved.', 'success', 'honor_application', FALSE),
(2, 'Grade Processing Complete', 'Your grade submission has been processed and your GWA has been calculated.', 'success', 'gwa_calculation', TRUE),
(2, 'Honor Application Approved', 'Congratulations! Your President\'s List application has been approved.', 'success', 'honor_application', FALSE),
(3, 'Grade Processing Complete', 'Your grade submission has been processed and your GWA has been calculated.', 'success', 'gwa_calculation', TRUE),
(4, 'Welcome to CTU Honor System', 'Welcome to the CTU Honor Application System. Please upload your grades to get started.', 'info', 'general', FALSE),
(5, 'Grade Processing Complete', 'Your grade submission has been processed and your GWA has been calculated.', 'success', 'gwa_calculation', FALSE);

-- Insert honor rankings
INSERT INTO honor_rankings (academic_period_id, department, year_level, section, ranking_type, user_id, gwa, rank_position, total_students, generated_by) VALUES
-- President's List Rankings
(1, 'Computer Science', 3, 'CS-3A', 'presidents_list', 2, 1.179, 1, 1, 6),

-- Dean's List Rankings
(1, 'Computer Science', 3, 'CS-3A', 'deans_list', 2, 1.179, 1, 3, 6),
(1, 'Computer Science', 3, 'CS-3A', 'deans_list', 1, 1.350, 2, 3, 6),
(1, 'Computer Science', 2, 'CS-2B', 'deans_list', 5, 1.350, 1, 1, 6),
(1, 'Information Technology', 2, 'IT-2B', 'deans_list', 3, 1.732, 1, 1, 7);

-- Insert audit logs
INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address, user_agent) VALUES
(1, 'login', 'users', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
(2, 'login', 'users', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
(4, 'grade_submission_processed', 'grade_submissions', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
(6, 'honor_application_approved', 'honor_applications', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
