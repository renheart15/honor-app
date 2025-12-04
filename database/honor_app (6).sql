-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Dec 04, 2025 at 01:53 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `honor_app`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `GenerateHonorRankings` (IN `p_academic_period_id` INT, IN `p_department` VARCHAR(100))   BEGIN
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

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetRankingSummary` (IN `p_academic_period_id` INT, IN `p_department` VARCHAR(100))   BEGIN
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
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetStudentRankingDetails` (IN `p_user_id` INT, IN `p_academic_period_id` INT)   BEGIN
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
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateGradeAbove25Flags` ()   BEGIN
    UPDATE gwa_calculations gwa
    JOIN grade_submissions gs ON gwa.submission_id = gs.id
    SET gwa.has_grade_above_25 = (
        SELECT COUNT(*) > 0
        FROM grades g
        WHERE g.submission_id = gs.id
        AND g.grade > 2.5
    );
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `academic_periods`
--

CREATE TABLE `academic_periods` (
  `id` int NOT NULL COMMENT 'Unique period identifier',
  `period_name` varchar(50) NOT NULL COMMENT 'Display name (e.g., First Semester 2024-2025)',
  `school_year` varchar(20) NOT NULL COMMENT 'Academic year (e.g., 2024-2025)',
  `semester` enum('1st','2nd','summer') NOT NULL COMMENT 'Semester type',
  `start_date` date NOT NULL COMMENT 'Period start date',
  `end_date` date NOT NULL COMMENT 'Period end date',
  `is_active` tinyint(1) DEFAULT '0' COMMENT 'Only one period can be active at a time',
  `registration_deadline` date DEFAULT NULL COMMENT 'Deadline for grade submissions',
  `application_deadline` date DEFAULT NULL COMMENT 'Deadline for honor applications',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation date',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update date'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Academic periods/semesters management';

--
-- Dumping data for table `academic_periods`
--

INSERT INTO `academic_periods` (`id`, `period_name`, `school_year`, `semester`, `start_date`, `end_date`, `is_active`, `registration_deadline`, `application_deadline`, `created_at`, `updated_at`) VALUES
(1, 'First Semester 2024-2025', '2024-2025', '1st', '2024-01-01', '2025-12-03', 0, '2024-09-15', '2024-12-01', '2025-06-24 16:19:05', '2025-12-03 18:13:29'),
(2, 'First Semester 2025-2026', '2025-2026', '1st', '2026-01-02', '2026-06-01', 1, NULL, NULL, '2025-09-15 03:39:00', '2025-12-03 12:30:13'),
(9, 'Second Semester 2024-2025', '2024-2025', '2nd', '2025-10-01', '2025-12-04', 1, NULL, NULL, '2025-09-15 09:50:43', '2025-12-04 01:02:03'),
(10, 'First Semester 2023-2024', '2023-2024', '1st', '2025-12-01', '2025-12-03', 0, NULL, NULL, '2025-12-02 13:56:18', '2025-12-03 18:13:29'),
(11, 'Second Semester 2023-2024', '2023-2024', '2nd', '2025-12-02', '2025-12-03', 0, NULL, NULL, '2025-12-02 18:16:24', '2025-12-03 18:13:29');

-- --------------------------------------------------------

--
-- Table structure for table `application_periods`
--

CREATE TABLE `application_periods` (
  `id` int NOT NULL COMMENT 'Unique application period identifier',
  `semester` varchar(20) NOT NULL COMMENT 'Semester (1st Semester, 2nd Semester, Summer)',
  `academic_year` varchar(20) NOT NULL COMMENT 'Academic year (e.g., 2024-2025)',
  `department` varchar(100) NOT NULL COMMENT 'Department this period applies to',
  `start_date` date NOT NULL COMMENT 'When applications open',
  `end_date` date NOT NULL COMMENT 'When applications close',
  `status` enum('open','closed') DEFAULT 'closed' COMMENT 'Whether applications are currently accepted',
  `created_by` int NOT NULL COMMENT 'Chairperson who created this period',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When period was created',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modification'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Honor application periods declared by chairpersons';

--
-- Dumping data for table `application_periods`
--

INSERT INTO `application_periods` (`id`, `semester`, `academic_year`, `department`, `start_date`, `end_date`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(6, '1st Semester', '2024-2025', 'Bachelor of Science in Information Technology', '2025-12-01', '2025-12-31', 'open', 47, '2025-09-18 03:20:43', '2025-12-02 22:17:44'),
(7, '1st Semester', '2024-2025', 'Bachelor of Industrial Technology', '2025-12-01', '2025-12-31', 'open', 47, '2025-09-18 03:20:43', '2025-12-02 22:53:48'),
(8, '1st Semester', '2024-2025', 'Computer Science', '2025-12-01', '2025-12-31', 'open', 47, '2025-09-18 03:20:43', '2025-12-02 22:53:48');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int NOT NULL COMMENT 'Unique log entry identifier',
  `user_id` int DEFAULT NULL COMMENT 'User who performed the action (NULL for system actions)',
  `action` varchar(100) NOT NULL COMMENT 'Action performed (login, upload, approve, etc.)',
  `table_name` varchar(50) DEFAULT NULL COMMENT 'Database table affected',
  `record_id` int DEFAULT NULL COMMENT 'ID of affected record',
  `old_values` json DEFAULT NULL COMMENT 'Previous values (for updates)',
  `new_values` json DEFAULT NULL COMMENT 'New values (for inserts/updates)',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of user',
  `user_agent` text COMMENT 'Browser user agent string',
  `session_id` varchar(128) DEFAULT NULL COMMENT 'Session identifier',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When action was performed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Audit trail for system actions';

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int NOT NULL,
  `department_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `id` int NOT NULL COMMENT 'Unique grade record identifier',
  `submission_id` int NOT NULL COMMENT 'Reference to grade submission',
  `subject_code` varchar(20) NOT NULL COMMENT 'Subject code (e.g., CS101, MATH201)',
  `subject_name` varchar(150) NOT NULL COMMENT 'Full subject name',
  `units` decimal(3,1) NOT NULL COMMENT 'Credit units (e.g., 3.0, 1.5)',
  `grade` decimal(3,2) NOT NULL COMMENT 'Numerical grade (e.g., 1.25, 2.50)',
  `letter_grade` varchar(5) DEFAULT NULL COMMENT 'Letter equivalent (A, B+, etc.)',
  `remarks` varchar(50) DEFAULT NULL COMMENT 'PASSED, FAILED, INC, etc.',
  `semester_taken` varchar(50) DEFAULT NULL,
  `instructor_name` varchar(100) DEFAULT NULL COMMENT 'Subject instructor',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation date'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Individual subject grades extracted from submissions';

--
-- Dumping data for table `grades`
--

INSERT INTO `grades` (`id`, `submission_id`, `subject_code`, `subject_name`, `units`, `grade`, `letter_grade`, `remarks`, `semester_taken`, `instructor_name`, `created_at`) VALUES
(172, 13, 'CS20', 'MULTIMEDIA', 3.0, 1.80, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(173, 13, 'CS21', 'INTRODUCTION TO COMPUTING', 3.0, 1.40, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(174, 13, 'CS22', '112	COMPUTER PROGRAMMING 1 (LEC)', 2.0, 2.40, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(175, 13, 'CS23', '112 L	COMPUTER PROGRAMMING 1 (LAB)', 3.0, 2.20, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(176, 13, 'CS24', 'MATHEMATICS IN THE MODERN WORLD', 3.0, 1.60, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(177, 13, 'CS25', 'READINGS IN PHILIPPINE HISTORY', 3.0, 1.70, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(178, 13, 'CS26', 'THE ENTREPRENEURIAL MIND', 3.0, 1.50, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(179, 13, 'CS27', 'NATIONAL SERVICE TRAINING PROGRAM 1', 3.0, 1.60, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(180, 13, 'CS28', '1	PHYSICAL EDUCATION 1', 2.0, 1.30, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(181, 13, 'CS21', '2	DIGITAL LOGIC DESIGN', 3.0, 1.70, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(182, 13, 'CS22', '123	COMPUTER PROGRAMMING 2 (LEC)', 2.0, 2.20, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(183, 13, 'CS23', '123L	COMPUTER PROGRAMMING 2 (LAB)', 3.0, 2.00, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(184, 13, 'CS24', 'PURPOSIVE COMMUNICATION', 3.0, 1.70, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(185, 13, 'CS25', 'SCIENCE, TECHNOLOGY AND SOCIETY', 3.0, 1.40, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(186, 13, 'CS26', 'UNDERSTANDING THE SELF', 3.0, 1.20, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(187, 13, 'CS27', 'GENDER AND SOCIETY WITH PEACE STUDIES', 3.0, 1.30, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(188, 13, 'CS28', 'NATIONAL SERVICE TRAINING PROGRAM 2', 3.0, 1.60, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(189, 13, 'CS29', 'DISCRETE MATHEMATICS', 3.0, 1.40, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(190, 13, 'CS30', '2	PHYSICAL EDUCATION 2', 2.0, 1.80, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(191, 13, 'CS102', 'ELEC 1	OBJECT - ORIENTED PROGRAMMING', 3.0, 1.50, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(192, 13, 'CS80', '214	DATA STRUCTURES AND ALGORITHMS (LEC)', 2.0, 1.30, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(193, 13, 'CS81', '214L	DATA STRUCTURES AND ALGORITHMS (LAB)', 3.0, 1.30, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(194, 13, 'CS82', 'ETHICS', 3.0, 1.30, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(195, 13, 'CS83', 'LIFE AND WORKS OF RIZAL', 3.0, 1.30, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(196, 13, 'CS84', 'ENVIRONMENTAL SCIENCE', 3.0, 1.50, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(197, 13, 'CS85', 'ELEC 2	WEB SYSTEMS AND TECHNOLOGIES', 3.0, 1.60, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(198, 13, 'CS86', '3	PHYSICAL EDUCATION 3', 2.0, 1.60, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(199, 13, 'CS87', '212	QUANTITATIVE METHODS (MODELING & SIMULATION)', 3.0, 1.40, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(200, 13, 'CS47', 'THE CONTEMPORARY WORLD', 3.0, 1.30, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:29'),
(201, 13, 'CS48', 'INTEGRATIVE PROGRAMMING AND TECHNOLOGIES 1', 3.0, 1.40, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(202, 13, 'CS49', '224	NETWORKING 1', 3.0, 1.50, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(203, 13, 'CS50', '225	INFORMATION MANAGEMENT (LEC)', 2.0, 1.70, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(204, 13, 'CS51', '225L	INFORMATION MANAGEMENT (LAB)', 3.0, 1.70, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(205, 13, 'CS52', 'Elec 3	PLATFORM TECHNOLOGIES', 3.0, 1.70, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(206, 13, 'CS53', '3	ASP.NET', 3.0, 1.60, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(207, 13, 'CS54', '4	PHYSICAL EDUCATION 4', 2.0, 1.20, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(208, 13, 'CS69', '316	APPLICATIONS DEVELOPMENT AND EMERGING TECHNOLOGIES', 3.0, 1.40, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(209, 13, 'CS70', 'FUNCTIONAL ENGLISH', 3.0, 1.30, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(210, 13, 'CS71', '315	NETWORKING 2 (LEC)', 2.0, 1.30, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(211, 13, 'CS72', '315L	NETWORKING 2 (LAB)', 3.0, 1.40, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(212, 13, 'CS73', 'SYSTEMS INTEGRATION AND ARCHITECTURE 1', 3.0, 1.50, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(213, 13, 'CS74', 'INTRODUCTION TO HUMAN COMPUTER INTERACTION', 3.0, 1.30, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(214, 13, 'CS75', '3180	DATABASE MANAGEMENT SYSTEMS', 3.0, 1.90, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(215, 13, 'CS53', 'IOS MOBILE APPLICATION DEVELOPMENT CROSS-PLATFORM', 3.0, 1.30, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(216, 13, 'CS54', '5	TECHNOLOGY AND THE APPLICATION OF THE INTERNET OF THINGS', 3.0, 1.80, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(217, 13, 'CS55', 'ART APPRECIATION', 3.0, 1.20, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(218, 13, 'CS56', 'PEOPLE AND THE EARTH\'S ECOSYSTEMS', 3.0, 1.40, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(219, 13, 'CS57', '3210	SOCIAL AND PROFESSIONAL ISSUES', 3.0, 1.60, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(220, 13, 'CS58', 'INFORMATION ASSURANCE AND SECURITY 1 (LEC)', 2.0, 2.00, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(221, 13, 'CS59', 'INFORMATION ASSURANCE AND SECURITY 1 (LAB)', 3.0, 1.70, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(222, 13, 'CS60', '329	CAPSTONE PROJECT AND RESEARCH 1', 3.0, 1.50, NULL, 'PASSED', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(223, 13, 'CS104', 'CROSS-PLATFORM SCRIPT DEVELOPMENT TECHNOLOGY', 3.0, 0.00, NULL, 'ONGOING', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(224, 13, 'CS105', 'SYSTEMS INTEGRATION AND ARCHITECTURE 2', 3.0, 0.00, NULL, 'ONGOING', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(225, 13, 'CS106', 'INFORMATION ASSURANCE AND SECURITY 2 (LEC)', 2.0, 0.00, NULL, 'ONGOING', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(226, 13, 'CS107', 'INFORMATION ASSURANCE AND SECURITY 2 (LAB)', 3.0, 0.00, NULL, 'ONGOING', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(227, 13, 'CS108', 'SYSTEMS ADMINISTRATION AND MAINTENANCE', 3.0, 0.00, NULL, 'ONGOING', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(228, 13, 'CS109', 'CAPSTONE PROJECT AND RESEARCH 2', 3.0, 0.00, NULL, 'ONGOING', '1st Semester SY 2024-2025', NULL, '2025-09-18 11:56:30'),
(1731, 18, 'CS20', 'MULTIMEDIA', 3.0, 1.80, NULL, NULL, '1st Semester SY 2022-2023', NULL, '2025-12-03 12:29:19'),
(1732, 18, 'CS21', 'INTRODUCTION TO COMPUTING', 3.0, 1.70, NULL, NULL, '1st Semester SY 2022-2023', NULL, '2025-12-03 12:29:19'),
(1733, 18, 'CS22', 'COMPUTER PROGRAMMING 1 (LEC)', 2.0, 2.50, NULL, NULL, '1st Semester SY 2022-2023', NULL, '2025-12-03 12:29:19'),
(1734, 18, 'CS23', 'COMPUTER PROGRAMMING 1 (LAB)', 3.0, 2.20, NULL, NULL, '1st Semester SY 2022-2023', NULL, '2025-12-03 12:29:19'),
(1735, 18, 'CS24', 'MATHEMATICS IN THE MODERN WORLD', 3.0, 2.60, NULL, NULL, '1st Semester SY 2022-2023', NULL, '2025-12-03 12:29:19'),
(1736, 18, 'CS25', 'READINGS IN PHILIPPINE HISTORY', 3.0, 1.90, NULL, NULL, '1st Semester SY 2022-2023', NULL, '2025-12-03 12:29:19'),
(1737, 18, 'CS26', 'THE ENTREPRENEURIAL MIND', 3.0, 1.50, NULL, NULL, '1st Semester SY 2022-2023', NULL, '2025-12-03 12:29:19'),
(1738, 18, 'CS27', 'NATIONAL SERVICE TRAINING PROGRAM 1', 3.0, 1.90, NULL, NULL, '1st Semester SY 2022-2023', NULL, '2025-12-03 12:29:19'),
(1739, 18, 'CS28', 'PHYSICAL EDUCATION 1', 2.0, 1.40, NULL, NULL, '1st Semester SY 2022-2023', NULL, '2025-12-03 12:29:19'),
(1740, 18, 'CS21', 'DIGITAL LOGIC DESIGN', 3.0, 1.80, NULL, NULL, '2nd Semester SY 2022-2023', NULL, '2025-12-03 12:29:19'),
(1741, 18, 'CS22', 'COMPUTER PROGRAMMING 2 (LEC)', 2.0, 2.00, NULL, NULL, '2nd Semester SY 2022-2023', NULL, '2025-12-03 12:29:19'),
(1742, 18, 'CS23', 'COMPUTER PROGRAMMING 2 (LAB)', 3.0, 2.00, NULL, NULL, '2nd Semester SY 2022-2023', NULL, '2025-12-03 12:29:20'),
(1743, 18, 'CS24', 'PURPOSIVE COMMUNICATION', 3.0, 1.90, NULL, NULL, '2nd Semester SY 2022-2023', NULL, '2025-12-03 12:29:20'),
(1744, 18, 'CS25', 'SCIENCE, TECHNOLOGY AND SOCIETY', 3.0, 1.30, NULL, NULL, '2nd Semester SY 2022-2023', NULL, '2025-12-03 12:29:20'),
(1745, 18, 'CS26', 'UNDERSTANDING THE SELF', 3.0, 1.50, NULL, NULL, '2nd Semester SY 2022-2023', NULL, '2025-12-03 12:29:20'),
(1746, 18, 'CS27', 'GENDER AND SOCIETY WITH PEACE STUDIES', 3.0, 1.60, NULL, NULL, '2nd Semester SY 2022-2023', NULL, '2025-12-03 12:29:20'),
(1747, 18, 'CS28', 'NATIONAL SERVICE TRAINING PROGRAM 2', 3.0, 1.60, NULL, NULL, '2nd Semester SY 2022-2023', NULL, '2025-12-03 12:29:20'),
(1748, 18, 'CS29', 'DISCRETE MATHEMATICS', 3.0, 1.40, NULL, NULL, '2nd Semester SY 2022-2023', NULL, '2025-12-03 12:29:20'),
(1749, 18, 'CS30', 'PHYSICAL EDUCATION 2', 2.0, 1.60, NULL, NULL, '2nd Semester SY 2022-2023', NULL, '2025-12-03 12:29:20'),
(1750, 18, 'CS80', 'DATA STRUCTURES AND ALGORITHMS (LEC)', 2.0, 1.80, NULL, NULL, '1st Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1751, 18, 'CS81', 'DATA STRUCTURES AND ALGORITHMS (LAB)', 3.0, 1.80, NULL, NULL, '1st Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1752, 18, 'CS82', 'ETHICS', 3.0, 1.50, NULL, NULL, '1st Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1753, 18, 'CS83', 'LIFE AND WORKS OF RIZAL', 3.0, 1.80, NULL, NULL, '1st Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1754, 18, 'CS84', 'ENVIRONMENTAL SCIENCE', 3.0, 1.90, NULL, NULL, '1st Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1755, 18, 'CS85', 'WEB SYSTEMS AND TECHNOLOGIES', 3.0, 1.80, NULL, NULL, '1st Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1756, 18, 'CS86', 'PHYSICAL EDUCATION 3', 2.0, 1.80, NULL, NULL, '1st Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1757, 18, 'CS87', 'QUANTITATIVE METHODS (MODELING & SIMULATION)', 3.0, 1.70, NULL, NULL, '1st Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1758, 18, 'CS102', 'OBJECT - ORIENTED PROGRAMMING', 3.0, 1.80, NULL, NULL, '1st Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1759, 18, 'CS47', 'THE CONTEMPORARY WORLD', 3.0, 1.50, NULL, NULL, '2nd Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1760, 18, 'CS48', 'INTEGRATIVE PROGRAMMING AND TECHNOLOGIES 1', 3.0, 1.90, NULL, NULL, '2nd Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1761, 18, 'CS49', 'NETWORKING 1', 3.0, 1.90, NULL, NULL, '2nd Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1762, 18, 'CS50', 'INFORMATION MANAGEMENT (LEC)', 2.0, 1.90, NULL, NULL, '2nd Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1763, 18, 'CS51', 'INFORMATION MANAGEMENT (LAB)', 3.0, 1.90, NULL, NULL, '2nd Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1764, 18, 'CS52', 'PLATFORM TECHNOLOGIES', 3.0, 2.50, NULL, NULL, '2nd Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1765, 18, 'CS53', 'ASP.NET', 3.0, 1.60, NULL, NULL, '2nd Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1766, 18, 'CS54', 'PHYSICAL EDUCATION 4', 2.0, 1.30, NULL, NULL, '2nd Semester SY 2023-2024', NULL, '2025-12-03 12:29:20'),
(1767, 18, 'CS69', 'APPLICATIONS DEVELOPMENT AND EMERGING TECHNOLOGIES', 3.0, 1.40, NULL, NULL, '1st Semester SY 2024-2025', NULL, '2025-12-03 12:29:20'),
(1768, 18, 'CS70', 'FUNCTIONAL ENGLISH', 3.0, 1.50, NULL, NULL, '1st Semester SY 2024-2025', NULL, '2025-12-03 12:29:20'),
(1769, 18, 'CS71', 'NETWORKING 2 (LEC)', 2.0, 1.90, NULL, NULL, '1st Semester SY 2024-2025', NULL, '2025-12-03 12:29:20'),
(1770, 18, 'CS72', 'NETWORKING 2 (LAB)', 3.0, 1.80, NULL, NULL, '1st Semester SY 2024-2025', NULL, '2025-12-03 12:29:20'),
(1771, 18, 'CS73', 'SYSTEMS INTEGRATION AND ARCHITECTURE 1', 3.0, 1.80, NULL, NULL, '1st Semester SY 2024-2025', NULL, '2025-12-03 12:29:20'),
(1772, 18, 'CS74', 'INTRODUCTION TO HUMAN COMPUTER INTERACTION', 3.0, 1.70, NULL, NULL, '1st Semester SY 2024-2025', NULL, '2025-12-03 12:29:20'),
(1773, 18, 'CS75', 'DATABASE MANAGEMENT SYSTEMS', 3.0, 1.70, NULL, NULL, '1st Semester SY 2024-2025', NULL, '2025-12-03 12:29:20'),
(1774, 18, 'CS53', 'IOS MOBILE APPLICATION DEVELOPMENT CROSS-PLATFORM', 3.0, 0.00, NULL, NULL, '2nd Semester SY 2024-2025', NULL, '2025-12-03 12:29:20'),
(1775, 18, 'CS54', 'TECHNOLOGY AND THE APPLICATION OF THE INTERNET OF THINGS', 3.0, 0.00, NULL, NULL, '2nd Semester SY 2024-2025', NULL, '2025-12-03 12:29:20'),
(1776, 18, 'CS55', 'ART APPRECIATION', 3.0, 0.00, NULL, NULL, '2nd Semester SY 2024-2025', NULL, '2025-12-03 12:29:20'),
(1777, 18, 'CS56', 'PEOPLE AND THE EARTH\'S ECOSYSTEMS', 3.0, 0.00, NULL, NULL, '2nd Semester SY 2024-2025', NULL, '2025-12-03 12:29:20'),
(1778, 18, 'CS57', 'SOCIAL AND PROFESSIONAL ISSUES', 3.0, 0.00, NULL, NULL, '2nd Semester SY 2024-2025', NULL, '2025-12-03 12:29:20'),
(1779, 18, 'CS58', 'INFORMATION ASSURANCE AND SECURITY 1 (LEC)', 3.0, 0.00, NULL, NULL, '2nd Semester SY 2024-2025', NULL, '2025-12-03 12:29:20'),
(1780, 18, 'CS59', 'INFORMATION ASSURANCE AND SECURITY 1 (LAB)', 2.0, 0.00, NULL, NULL, '2nd Semester SY 2024-2025', NULL, '2025-12-03 12:29:20'),
(1781, 18, 'CS60', 'CAPSTONE PROJECT AND RESEARCH 1', 3.0, 0.00, NULL, NULL, '2nd Semester SY 2024-2025', NULL, '2025-12-03 12:29:20');

-- --------------------------------------------------------

--
-- Table structure for table `grade_submissions`
--

CREATE TABLE `grade_submissions` (
  `id` int NOT NULL COMMENT 'Unique submission identifier',
  `user_id` int NOT NULL COMMENT 'Student who submitted the grades',
  `academic_period_id` int NOT NULL COMMENT 'Academic period for this submission',
  `file_path` varchar(500) NOT NULL COMMENT 'Server path to uploaded PDF file',
  `file_name` varchar(255) NOT NULL COMMENT 'Original filename from upload',
  `file_size` int NOT NULL COMMENT 'File size in bytes',
  `mime_type` varchar(100) NOT NULL DEFAULT 'application/pdf' COMMENT 'File MIME type',
  `upload_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When file was uploaded',
  `status` enum('pending','processing','processed','rejected','failed') DEFAULT 'pending' COMMENT 'Processing status',
  `processed_at` timestamp NULL DEFAULT NULL COMMENT 'When processing completed',
  `processed_by` int DEFAULT NULL COMMENT 'ID of user who processed (instructor/chairperson)',
  `rejection_reason` text COMMENT 'Reason for rejection if status is rejected',
  `notes` text COMMENT 'Additional notes from processor',
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Student grade report submissions';

--
-- Dumping data for table `grade_submissions`
--

INSERT INTO `grade_submissions` (`id`, `user_id`, `academic_period_id`, `file_path`, `file_name`, `file_size`, `mime_type`, `upload_date`, `status`, `processed_at`, `processed_by`, `rejection_reason`, `notes`, `uploaded_at`) VALUES
(12, 49, 1, 'grade_49_1764501494.pdf', 'MyGrades-15.pdf', 227232, 'application/pdf', '2025-11-30 11:18:14', 'rejected', '2025-11-30 11:19:00', 48, 'Not your grade\r\n', NULL, '2025-11-30 19:19:00'),
(13, 50, 1, 'grade_50_1758194484.pdf', 'MyGrades.pdf', 230658, 'application/pdf', '2025-09-18 11:21:24', 'processed', '2025-09-18 11:56:30', 51, NULL, NULL, '2025-09-18 19:56:30'),
(14, 53, 1, 'grade_53_1763700792.pdf', 'MyGrades.pdf', 230658, 'application/pdf', '2025-11-21 04:53:12', 'rejected', '2025-11-30 11:19:33', 48, 'Not your grade', NULL, '2025-11-30 19:19:33'),
(15, 49, 9, 'grade_49_1764527955.pdf', 'MyGrades-15.pdf', 227232, 'application/pdf', '2025-11-30 18:39:15', 'processed', '2025-11-30 18:39:34', 48, NULL, NULL, '2025-12-01 02:39:34'),
(16, 49, 2, 'grade_49_1764683578.pdf', 'MyGrades-15.pdf', 227232, 'application/pdf', '2025-12-02 13:52:58', 'processed', '2025-12-02 13:53:22', 48, NULL, NULL, '2025-12-02 21:53:22'),
(17, 49, 10, 'grade_49_1764699005.pdf', 'MyGrades-15.pdf', 227232, 'application/pdf', '2025-12-02 18:10:05', 'processed', '2025-12-02 18:11:28', 48, NULL, NULL, '2025-12-03 02:11:28'),
(18, 49, 11, 'grade_49_1764764933.pdf', 'MyGrades-15.pdf', 227232, 'application/pdf', '2025-12-03 12:28:53', 'processed', '2025-12-03 12:29:20', 48, NULL, NULL, '2025-12-03 20:29:20');

-- --------------------------------------------------------

--
-- Table structure for table `gwa_calculations`
--

CREATE TABLE `gwa_calculations` (
  `id` int NOT NULL COMMENT 'Unique calculation identifier',
  `user_id` int NOT NULL COMMENT 'Student for this GWA calculation',
  `academic_period_id` int NOT NULL COMMENT 'Academic period',
  `submission_id` int NOT NULL COMMENT 'Grade submission used for calculation',
  `total_units` decimal(6,1) NOT NULL COMMENT 'Total credit units',
  `total_grade_points` decimal(10,2) NOT NULL COMMENT 'Sum of (grade × units)',
  `gwa` decimal(4,2) DEFAULT NULL,
  `subjects_count` int NOT NULL COMMENT 'Number of subjects',
  `failed_subjects` int DEFAULT '0' COMMENT 'Number of failed subjects (grade >= 3.0)',
  `incomplete_subjects` int DEFAULT '0' COMMENT 'Number of incomplete subjects',
  `has_grade_above_25` tinyint(1) DEFAULT '0',
  `calculation_method` varchar(50) DEFAULT 'weighted_average' COMMENT 'Method used for calculation',
  `calculated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When GWA was calculated',
  `recalculated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'When GWA was recalculated'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Computed GWA for each student per academic period';

--
-- Dumping data for table `gwa_calculations`
--

INSERT INTO `gwa_calculations` (`id`, `user_id`, `academic_period_id`, `submission_id`, `total_units`, `total_grade_points`, `gwa`, `subjects_count`, `failed_subjects`, `incomplete_subjects`, `has_grade_above_25`, `calculation_method`, `calculated_at`, `recalculated_at`) VALUES
(12, 50, 1, 13, 137.0, 210.98, 1.54, 49, 0, 0, 0, 'weighted_average', '2025-09-18 03:56:30', '2025-09-18 11:57:31'),
(16, 49, 11, 18, 114.0, 201.78, 1.77, 41, 0, 0, 1, 'weighted_average', '2025-12-02 11:10:51', '2025-12-02 21:01:52'),
(17, 49, 1, 18, 20.0, 33.50, 1.67, 7, 0, 0, 0, 'weighted_average', '2025-12-03 12:51:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `honor_applications`
--

CREATE TABLE `honor_applications` (
  `id` int NOT NULL COMMENT 'Unique application identifier',
  `user_id` int NOT NULL COMMENT 'Student applying for honor',
  `academic_period_id` int NOT NULL COMMENT 'Academic period for application',
  `gwa_calculation_id` int NOT NULL COMMENT 'GWA calculation used for application',
  `application_type` enum('deans_list','cum_laude','magna_cum_laude','summa_cum_laude') NOT NULL,
  `gwa_achieved` decimal(4,3) NOT NULL COMMENT 'GWA at time of application',
  `required_gwa` decimal(4,3) NOT NULL COMMENT 'Required GWA for this honor type',
  `status` enum('submitted','under_review','approved','denied','cancelled') DEFAULT 'submitted' COMMENT 'Application status',
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When application was submitted',
  `reviewed_at` timestamp NULL DEFAULT NULL COMMENT 'When application was reviewed',
  `reviewed_by` int DEFAULT NULL COMMENT 'Instructor/Chairperson who reviewed',
  `approval_date` date DEFAULT NULL COMMENT 'Date of approval',
  `remarks` text COMMENT 'Review comments',
  `rejection_reason` text COMMENT 'Reason for rejection if status is rejected',
  `certificate_generated` tinyint(1) DEFAULT '0' COMMENT 'Whether certificate was generated',
  `certificate_path` varchar(500) DEFAULT NULL COMMENT 'Path to generated certificate',
  `ranking_position` int DEFAULT NULL COMMENT 'Position in honor roll ranking',
  `final_approved_by` int DEFAULT NULL,
  `is_eligible` tinyint(1) DEFAULT '1' COMMENT 'Whether the application meets eligibility requirements',
  `ineligibility_reasons` text COMMENT 'Details about why the application is not eligible'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Honor applications (Deans List, Presidents List, etc.)';

--
-- Dumping data for table `honor_applications`
--

INSERT INTO `honor_applications` (`id`, `user_id`, `academic_period_id`, `gwa_calculation_id`, `application_type`, `gwa_achieved`, `required_gwa`, `status`, `submitted_at`, `reviewed_at`, `reviewed_by`, `approval_date`, `remarks`, `rejection_reason`, `certificate_generated`, `certificate_path`, `ranking_position`, `final_approved_by`, `is_eligible`, `ineligibility_reasons`) VALUES
(5, 49, 1, 17, 'deans_list', 1.670, 1.750, 'approved', '2025-12-03 13:05:42', '2025-12-03 13:07:05', 48, NULL, NULL, NULL, 0, NULL, NULL, NULL, 1, NULL),
(6, 50, 1, 12, 'deans_list', 1.540, 1.750, 'approved', '2025-09-18 11:57:31', '2025-09-18 11:58:00', 51, NULL, NULL, NULL, 0, NULL, NULL, NULL, 1, NULL),
(14, 49, 11, 16, 'deans_list', 1.830, 1.750, 'denied', '2025-12-03 12:50:45', '2025-12-03 13:06:58', 48, NULL, NULL, 'Not Eligible', 0, NULL, NULL, NULL, 0, 'GWA of 1.83 exceeds required 1.75; Has 1 grade(s) above 2.5');

-- --------------------------------------------------------

--
-- Table structure for table `honor_rankings`
--

CREATE TABLE `honor_rankings` (
  `id` int NOT NULL COMMENT 'Unique ranking identifier',
  `academic_period_id` int NOT NULL COMMENT 'Academic period for ranking',
  `user_id` int NOT NULL COMMENT 'Student being ranked',
  `department` varchar(100) NOT NULL COMMENT 'Department for ranking',
  `year_level` int DEFAULT NULL COMMENT 'Year level (NULL for department-wide ranking)',
  `section` varchar(10) DEFAULT NULL COMMENT 'Section (NULL for year-level ranking)',
  `ranking_type` enum('deans_list','presidents_list','overall') NOT NULL COMMENT 'Type of ranking',
  `gwa` decimal(4,3) NOT NULL COMMENT 'Student GWA for ranking',
  `rank_position` int NOT NULL COMMENT 'Position in ranking (1 = highest)',
  `total_students` int NOT NULL COMMENT 'Total students in this ranking category',
  `percentile` decimal(5,2) DEFAULT NULL COMMENT 'Percentile ranking (0-100)',
  `generated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When ranking was generated',
  `generated_by` int DEFAULT NULL COMMENT 'User who generated the ranking'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Honor roll rankings by department, year, and section';

--
-- Dumping data for table `honor_rankings`
--

INSERT INTO `honor_rankings` (`id`, `academic_period_id`, `user_id`, `department`, `year_level`, `section`, `ranking_type`, `gwa`, `rank_position`, `total_students`, `percentile`, `generated_at`, `generated_by`) VALUES
(44, 1, 50, 'Bachelor of Science in Information Technology', 4, 'B', 'deans_list', 1.540, 1, 1, NULL, '2025-09-18 12:25:16', 47),
(45, 1, 50, 'Bachelor of Science in Information Technology', 4, 'B', 'presidents_list', 1.540, 1, 1, NULL, '2025-09-18 12:25:16', 47),
(46, 1, 49, 'Bachelor of Science in Information Technology', 4, 'C', 'deans_list', 1.500, 1, 1, NULL, '2025-09-18 12:25:16', 47),
(47, 1, 49, 'Bachelor of Science in Information Technology', 4, 'C', 'presidents_list', 1.500, 1, 1, NULL, '2025-09-18 12:25:16', 47);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL COMMENT 'Unique notification identifier',
  `user_id` int NOT NULL COMMENT 'User receiving the notification',
  `title` varchar(255) NOT NULL COMMENT 'Notification title',
  `message` text NOT NULL COMMENT 'Notification message content',
  `type` enum('info','success','warning','error','system') DEFAULT 'info' COMMENT 'Notification type for styling',
  `category` enum('grade_upload','gwa_calculation','honor_application','system_update','general') DEFAULT 'general' COMMENT 'Notification category',
  `is_read` tinyint(1) DEFAULT '0' COMMENT 'Whether notification has been read',
  `is_email_sent` tinyint(1) DEFAULT '0' COMMENT 'Whether email notification was sent',
  `email_sent_at` timestamp NULL DEFAULT NULL COMMENT 'When email was sent',
  `action_url` varchar(500) DEFAULT NULL COMMENT 'URL for notification action button',
  `action_text` varchar(50) DEFAULT NULL COMMENT 'Text for action button',
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'When notification expires',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When notification was created',
  `read_at` timestamp NULL DEFAULT NULL COMMENT 'When notification was read'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='System notifications for users';

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `category`, `is_read`, `is_email_sent`, `email_sent_at`, `action_url`, `action_text`, `expires_at`, `created_at`, `read_at`) VALUES
(34, 50, 'Grade File Uploaded', 'Your grade file has been uploaded and is being processed.', 'success', 'grade_upload', 0, 0, NULL, NULL, NULL, NULL, '2025-09-18 11:21:24', NULL),
(36, 53, 'Grade File Uploaded', 'Your grade file has been uploaded and is being processed.', 'success', 'grade_upload', 0, 0, NULL, NULL, NULL, NULL, '2025-11-21 04:53:12', NULL),
(52, 50, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2025-2026. Deadline: Dec 31, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 13:50:10', NULL),
(53, 52, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2025-2026. Deadline: Dec 31, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 13:50:10', NULL),
(54, 53, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2025-2026. Deadline: Dec 31, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 13:50:10', NULL),
(61, 50, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2024-2025. Deadline: Dec 02, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 13:54:36', NULL),
(62, 52, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2024-2025. Deadline: Dec 02, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 13:54:36', NULL),
(63, 53, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2024-2025. Deadline: Dec 02, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 13:54:36', NULL),
(68, 50, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2023-2024. Deadline: Dec 02, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 13:56:26', NULL),
(69, 52, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2023-2024. Deadline: Dec 02, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 13:56:26', NULL),
(70, 53, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2023-2024. Deadline: Dec 02, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 13:56:26', NULL),
(81, 50, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2024-2025. Deadline: Dec 02, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 15:35:46', NULL),
(82, 52, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2024-2025. Deadline: Dec 02, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 15:35:46', NULL),
(83, 53, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2024-2025. Deadline: Dec 02, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 15:35:46', NULL),
(96, 49, 'Grade File Uploaded', 'Your grade file has been uploaded and is pending adviser approval.', 'success', 'grade_upload', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 17:20:03', NULL),
(97, 49, 'Grade Report Approved', 'Your grade report has been approved and processed successfully.', 'success', 'gwa_calculation', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 17:20:27', NULL),
(98, 49, 'Grade File Uploaded', 'Your grade file has been uploaded and is pending adviser approval.', 'success', 'grade_upload', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 17:34:15', NULL),
(99, 49, 'Grade Report Approved', 'Your grade report has been approved and processed successfully.', 'success', 'gwa_calculation', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 17:34:37', NULL),
(100, 49, 'Grade File Uploaded', 'Your grade file has been uploaded and is pending adviser approval.', 'success', 'grade_upload', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 17:45:00', NULL),
(101, 49, 'Grade Report Approved', 'Your grade report has been approved and processed successfully.', 'success', 'gwa_calculation', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 17:45:17', NULL),
(102, 49, 'Grade File Uploaded', 'Your grade file has been uploaded and is pending adviser approval.', 'success', 'grade_upload', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 18:10:05', NULL),
(103, 49, 'Grade Report Approved', 'Your grade report has been approved and processed successfully.', 'success', 'gwa_calculation', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 18:11:28', NULL),
(104, 49, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2024-2025. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 18:13:42', NULL),
(105, 50, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2024-2025. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 18:13:42', NULL),
(106, 52, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2024-2025. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 18:13:42', NULL),
(107, 53, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2024-2025. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 18:13:42', NULL),
(111, 49, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 18:14:49', NULL),
(112, 50, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 18:14:49', NULL),
(113, 52, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 18:14:49', NULL),
(114, 53, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 18:14:49', NULL),
(118, 49, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 18:16:28', NULL),
(119, 50, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 18:16:28', NULL),
(120, 52, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 18:16:28', NULL),
(121, 53, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 18:16:28', NULL),
(125, 49, 'Grade File Uploaded', 'Your grade file has been uploaded and is pending adviser approval.', 'success', 'grade_upload', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 19:06:16', NULL),
(126, 49, 'Grade Report Approved', 'Your grade report has been approved and processed successfully.', 'success', 'gwa_calculation', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 19:10:51', NULL),
(127, 49, 'Grade File Uploaded', 'Your grade file has been uploaded and is pending adviser approval.', 'success', 'grade_upload', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 19:29:35', NULL),
(128, 49, 'Grade Report Approved', 'Your grade report has been approved and processed successfully.', 'success', 'gwa_calculation', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 19:29:55', NULL),
(129, 49, 'Grade File Uploaded', 'Your grade file has been uploaded and is pending adviser approval.', 'success', 'grade_upload', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 19:42:07', NULL),
(130, 49, 'Grade Report Approved', 'Your grade report has been approved and processed successfully.', 'success', 'gwa_calculation', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 19:44:17', NULL),
(131, 49, 'Grade File Uploaded', 'Your grade file has been uploaded and is pending adviser approval.', 'success', 'grade_upload', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 22:19:19', NULL),
(132, 49, 'Grade Report Approved', 'Your grade report has been approved and processed successfully.', 'success', 'gwa_calculation', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 22:19:47', NULL),
(133, 49, 'Application Rejected', 'Your Deans list application was rejected.', 'error', 'honor_application', 0, 0, NULL, NULL, NULL, NULL, '2025-12-02 23:07:18', NULL),
(134, 49, 'Grade File Uploaded', 'Your grade file has been uploaded and is pending adviser approval.', 'success', 'grade_upload', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 03:42:47', NULL),
(135, 49, 'Grade Report Approved', 'Your grade report has been approved and processed successfully.', 'success', 'gwa_calculation', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 03:43:10', NULL),
(136, 49, 'Grade File Uploaded', 'Your grade file has been uploaded and is pending adviser approval.', 'success', 'grade_upload', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 03:46:41', NULL),
(137, 49, 'Grade Report Approved', 'Your grade report has been approved and processed successfully.', 'success', 'gwa_calculation', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 03:47:24', NULL),
(138, 49, 'Grade File Uploaded', 'Your grade file has been uploaded and is pending adviser approval.', 'success', 'grade_upload', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 03:59:19', NULL),
(139, 49, 'Grade Report Approved', 'Your grade report has been approved and processed successfully.', 'success', 'gwa_calculation', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 03:59:43', NULL),
(140, 49, 'Grade File Uploaded', 'Your grade file has been uploaded and is pending adviser approval.', 'success', 'grade_upload', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 05:11:43', NULL),
(141, 49, 'Grade Report Approved', 'Your grade report has been approved and processed successfully.', 'success', 'gwa_calculation', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 05:12:04', NULL),
(142, 49, 'Grade Report Approved', 'Your grade report has been approved and processed successfully.', 'success', 'gwa_calculation', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 05:16:40', NULL),
(144, 49, 'Grade File Uploaded', 'Your grade file has been uploaded and is pending adviser approval.', 'success', 'grade_upload', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:28:53', NULL),
(145, 49, 'Grade Report Approved', 'Your grade report has been approved and processed successfully.', 'success', 'gwa_calculation', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:29:20', NULL),
(146, 49, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2025-2026. Deadline: Jun 01, 2026', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:13', NULL),
(147, 50, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2025-2026. Deadline: Jun 01, 2026', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:13', NULL),
(148, 52, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2025-2026. Deadline: Jun 01, 2026', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:13', NULL),
(149, 53, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2025-2026. Deadline: Jun 01, 2026', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:13', NULL),
(154, 51, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2025-2026. Deadline: Jun 01, 2026', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:13', NULL),
(156, 49, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2024-2025. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:19', NULL),
(157, 50, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2024-2025. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:19', NULL),
(158, 52, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2024-2025. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:19', NULL),
(159, 53, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2024-2025. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:19', NULL),
(164, 51, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2024-2025. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:19', NULL),
(166, 49, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:22', NULL),
(167, 50, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:22', NULL),
(168, 52, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:22', NULL),
(169, 53, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:22', NULL),
(174, 51, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:22', NULL),
(176, 49, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:25', NULL),
(177, 50, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:25', NULL),
(178, 52, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:25', NULL),
(179, 53, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:25', NULL),
(184, 51, 'Academic Period Opened', 'New academic period is now open: 1st Semester SY 2023-2024. Deadline: Dec 03, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:30:25', NULL),
(186, 49, 'Application Approved', 'Your Deans list application has been approved!', 'success', 'honor_application', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 12:49:34', NULL),
(187, 49, 'Application Rejected', 'Your Deans list application was rejected.', 'error', 'honor_application', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 13:06:58', NULL),
(188, 49, 'Application Approved', 'Your Deans list application has been approved!', 'success', 'honor_application', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 13:07:05', NULL),
(189, 49, 'Academic Period Expired', 'The academic period 1st Semester SY 2023-2024 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(190, 50, 'Academic Period Expired', 'The academic period 1st Semester SY 2023-2024 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(191, 52, 'Academic Period Expired', 'The academic period 1st Semester SY 2023-2024 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(192, 53, 'Academic Period Expired', 'The academic period 1st Semester SY 2023-2024 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(196, 48, 'Academic Period Expired', 'The academic period 1st Semester SY 2023-2024 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(197, 51, 'Academic Period Expired', 'The academic period 1st Semester SY 2023-2024 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(198, 54, 'Academic Period Expired', 'The academic period 1st Semester SY 2023-2024 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(199, 49, 'Academic Period Expired', 'The academic period 2nd Semester SY 2023-2024 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(200, 50, 'Academic Period Expired', 'The academic period 2nd Semester SY 2023-2024 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(201, 52, 'Academic Period Expired', 'The academic period 2nd Semester SY 2023-2024 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(202, 53, 'Academic Period Expired', 'The academic period 2nd Semester SY 2023-2024 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(206, 48, 'Academic Period Expired', 'The academic period 2nd Semester SY 2023-2024 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(207, 51, 'Academic Period Expired', 'The academic period 2nd Semester SY 2023-2024 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(208, 54, 'Academic Period Expired', 'The academic period 2nd Semester SY 2023-2024 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(209, 49, 'Academic Period Expired', 'The academic period 1st Semester SY 2024-2025 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(210, 50, 'Academic Period Expired', 'The academic period 1st Semester SY 2024-2025 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(211, 52, 'Academic Period Expired', 'The academic period 1st Semester SY 2024-2025 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(212, 53, 'Academic Period Expired', 'The academic period 1st Semester SY 2024-2025 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(216, 48, 'Academic Period Expired', 'The academic period 1st Semester SY 2024-2025 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(217, 51, 'Academic Period Expired', 'The academic period 1st Semester SY 2024-2025 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(218, 54, 'Academic Period Expired', 'The academic period 1st Semester SY 2024-2025 has expired.', 'warning', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-03 18:13:29', NULL),
(219, 49, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2024-2025. Deadline: Dec 04, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-04 01:02:03', NULL),
(220, 50, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2024-2025. Deadline: Dec 04, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-04 01:02:03', NULL),
(221, 52, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2024-2025. Deadline: Dec 04, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-04 01:02:03', NULL),
(222, 53, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2024-2025. Deadline: Dec 04, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-04 01:02:03', NULL),
(226, 48, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2024-2025. Deadline: Dec 04, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-04 01:02:03', NULL),
(227, 51, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2024-2025. Deadline: Dec 04, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-04 01:02:03', NULL),
(228, 54, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2024-2025. Deadline: Dec 04, 2025', 'info', 'system_update', 0, 0, NULL, NULL, NULL, NULL, '2025-12-04 01:02:03', NULL),
(229, 47, 'Academic Period Opened', 'New academic period is now open: 2nd Semester SY 2024-2025. Deadline: Dec 04, 2025', 'info', 'system_update', 1, 0, NULL, NULL, NULL, NULL, '2025-12-04 01:02:03', '2025-12-04 01:05:34');

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int NOT NULL,
  `program_name` varchar(255) NOT NULL,
  `department_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `student_gwa_summary`
-- (See below for the actual view)
--
CREATE TABLE `student_gwa_summary` (
`calculated_at` timestamp
,`department` varchar(100)
,`failed_subjects` int
,`first_name` varchar(50)
,`gwa` decimal(4,2)
,`has_grade_above_25` tinyint(1)
,`honor_classification` varchar(15)
,`is_honor_eligible` int
,`last_name` varchar(50)
,`period_name` varchar(50)
,`school_year` varchar(20)
,`section` varchar(100)
,`semester` enum('1st','2nd','summer')
,`student_id` varchar(255)
,`subjects_count` int
,`total_units` decimal(6,1)
,`user_id` int
,`year_level` int
);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int NOT NULL COMMENT 'Unique setting identifier',
  `setting_key` varchar(100) NOT NULL COMMENT 'Unique setting key',
  `setting_value` text NOT NULL COMMENT 'Setting value (can be long text)',
  `setting_type` enum('string','number','boolean','json') DEFAULT 'string' COMMENT 'Data type of setting value',
  `category` varchar(50) DEFAULT 'general' COMMENT 'Setting category for organization',
  `description` text COMMENT 'Description of what this setting does',
  `is_public` tinyint(1) DEFAULT '0' COMMENT 'Can be accessed by non-admin users',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When setting was created',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When setting was last updated',
  `updated_by` int DEFAULT NULL COMMENT 'User who last updated this setting'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='System configuration settings';

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL COMMENT 'Unique user identifier',
  `student_id` varchar(255) DEFAULT NULL,
  `email` varchar(100) NOT NULL COMMENT 'User email address (login credential)',
  `password` varchar(255) NOT NULL COMMENT 'Hashed password using password_hash()',
  `first_name` varchar(50) NOT NULL COMMENT 'User first name',
  `last_name` varchar(50) NOT NULL COMMENT 'User last name',
  `middle_name` varchar(50) DEFAULT NULL COMMENT 'User middle name (optional)',
  `role` enum('student','adviser','chairperson') NOT NULL DEFAULT 'student',
  `department` varchar(100) DEFAULT NULL COMMENT 'Academic department (e.g., Computer Science)',
  `college` varchar(100) DEFAULT NULL COMMENT 'College (e.g., College of Technology)',
  `course` varchar(150) DEFAULT NULL COMMENT 'Course/Program',
  `major` varchar(100) DEFAULT NULL COMMENT 'Major/Specialization',
  `year_level` int DEFAULT NULL COMMENT 'Student year level (1-4, null for non-students)',
  `section` varchar(100) DEFAULT NULL COMMENT 'Student/Adviser sections (JSON array or single value)',
  `contact_number` varchar(15) DEFAULT NULL COMMENT 'Phone/mobile number',
  `address` text COMMENT 'Complete address',
  `status` enum('active','inactive','suspended') DEFAULT 'active' COMMENT 'Account status',
  `email_verified` tinyint(1) DEFAULT '0' COMMENT 'Email verification status',
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'Last login timestamp',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Account creation date',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update date',
  `program_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='User accounts for students, instructors, and chairpersons';

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `student_id`, `email`, `password`, `first_name`, `last_name`, `middle_name`, `role`, `department`, `college`, `course`, `major`, `year_level`, `section`, `contact_number`, `address`, `status`, `email_verified`, `last_login`, `created_at`, `updated_at`, `program_id`) VALUES
(47, NULL, 'kyra@gmail.com', '$2y$10$0ouNWWgXldF.sEyXno1P3usfo/Lv6t9jVGkQduZPnd02ZN/LLpFLK', 'kyra', 'macapaz', '', 'chairperson', 'Bachelor of Science in Information Technology', 'College of Technology', 'Bachelor of Science in Information Technology', '', NULL, NULL, '', '', 'active', 0, NULL, '2025-09-17 16:06:14', '2025-12-03 13:41:30', NULL),
(48, NULL, 'achie@gmail.com', '$2y$10$ixcbL18p.9oKYlG71rFdq.4Q4oSMHWzs2R2DzOP.lVc5uBX8wORnK', 'achie', 'lauro', '', 'adviser', 'Bachelor of Science in Information Technology', 'College of Technology', 'Bachelor of Science in Information Technology', '', NULL, '[\"C-4\"]', '', '', 'active', 0, NULL, '2025-09-17 16:06:38', '2025-09-17 17:55:05', NULL),
(49, '5220091', 'jake@gmail.com', '$2y$10$nNpEPLVxd2JAu3MSZAU1megXueqVZFDvwEvvqwTMAzkQSQPhgESdi', 'Christian Jake', 'Lape', '', 'student', 'Bachelor of Science in Information Technology', 'College of Technology', 'Bachelor of Science in Information Technology', '', 4, 'C', '', '', 'active', 0, NULL, '2025-09-17 18:02:15', '2025-12-04 00:41:15', NULL),
(50, '5220092', 'karl@gmail.com', '$2y$10$O8uKOrPCU/mPjwPaMhZNbeE9FGy6kJ4w2FFnYl/BJ56yKGBRQCcry', 'karl', 'nuñez ', '', 'student', 'Bachelor of Science in Information Technology', 'College of Technology', 'Bachelor of Science in Information Technology', '', 4, 'B', '', '', 'active', 0, NULL, '2025-09-18 10:33:29', '2025-09-18 10:33:29', NULL),
(51, NULL, 'catherine@gmail.com', '$2y$10$TKX27BgkdVO04GjFngEhhOggzCLjYr/NcBKeFSPEsJEnyxia6.xHm', 'catherine', 'loseñara', '', 'adviser', 'Bachelor of Science in Information Technology', 'College of Technology', 'Bachelor of Science in Information Technology', '', NULL, '[\"B-4\"]', '', '', 'active', 0, NULL, '2025-09-18 11:25:16', '2025-09-18 11:26:29', NULL),
(52, '5220093', 'ira@gmail.com', '$2y$10$IYgmKKJOgXVqYjsOvxFu4Oh4W/iSeIXtKYOzyfGaZLWSB04uNWCOu', 'Ira', 'Gaviola', '', 'student', 'Bachelor of Science in Information Technology', 'College of Technology', 'Bachelor of Science in Information Technology', '', 4, 'C', '', '', 'active', 0, NULL, '2025-09-18 15:43:44', '2025-09-18 15:43:44', NULL),
(53, '5220096', 'ralfanta0112@gmail.com', '$2y$10$CSQbgVzuROlqkxnWiWegnOp.fx6/kXpz2FsGLxzXeron2EGhHCDza', 'Renheart', 'Alfanta', '', 'student', 'Bachelor of Science in Information Technology', 'College of Technology', 'Bachelor of Science in Information Technology', '', 4, 'C', '', '', 'active', 0, NULL, '2025-11-21 04:52:30', '2025-11-21 04:52:30', NULL),
(54, NULL, 'miccah@gmail.com', '$2y$10$14eX65SEN.gvOqWZvvCvgesRy0SiX9b1FDTd2n04NhjQmSV9fmcCS', 'Miccah', 'Jimenez', NULL, 'adviser', 'Bachelor of Science in Information Technology', 'College of Technology', 'Bachelor of Science in Information Technology', '', NULL, '[\"A-4\"]', NULL, NULL, 'active', 0, NULL, '2025-12-03 13:34:24', '2025-12-03 13:38:31', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_periods`
--
ALTER TABLE `academic_periods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_active_period` (`is_active`,`school_year`,`semester`),
  ADD KEY `idx_school_year` (`school_year`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Indexes for table `application_periods`
--
ALTER TABLE `application_periods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_period_dept` (`semester`,`academic_year`,`department`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_semester_year` (`semester`,`academic_year`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dates` (`start_date`,`end_date`),
  ADD KEY `idx_active_periods` (`department`,`status`,`start_date`,`end_date`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_table_name` (`table_name`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_record_id` (`record_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_submission` (`submission_id`),
  ADD KEY `idx_subject_code` (`subject_code`),
  ADD KEY `idx_grade` (`grade`),
  ADD KEY `idx_submission_subject` (`submission_id`,`subject_code`);

--
-- Indexes for table `grade_submissions`
--
ALTER TABLE `grade_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_period` (`user_id`,`academic_period_id`),
  ADD KEY `academic_period_id` (`academic_period_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_upload_date` (`upload_date`),
  ADD KEY `idx_processed_by` (`processed_by`);

--
-- Indexes for table `gwa_calculations`
--
ALTER TABLE `gwa_calculations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_period_gwa` (`user_id`,`academic_period_id`),
  ADD KEY `academic_period_id` (`academic_period_id`),
  ADD KEY `submission_id` (`submission_id`),
  ADD KEY `idx_gwa` (`gwa`),
  ADD KEY `idx_calculated_at` (`calculated_at`),
  ADD KEY `idx_user_period` (`user_id`,`academic_period_id`);

--
-- Indexes for table `honor_applications`
--
ALTER TABLE `honor_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_period_type` (`user_id`,`academic_period_id`,`application_type`),
  ADD KEY `academic_period_id` (`academic_period_id`),
  ADD KEY `gwa_calculation_id` (`gwa_calculation_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_application_type` (`application_type`),
  ADD KEY `idx_submitted_at` (`submitted_at`),
  ADD KEY `idx_gwa_achieved` (`gwa_achieved`),
  ADD KEY `idx_reviewed_by` (`reviewed_by`),
  ADD KEY `final_approved_by` (`final_approved_by`),
  ADD KEY `idx_honor_applications_eligibility` (`is_eligible`);

--
-- Indexes for table `honor_rankings`
--
ALTER TABLE `honor_rankings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ranking` (`academic_period_id`,`department`,`year_level`,`section`,`ranking_type`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_ranking_type` (`ranking_type`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_rank_position` (`rank_position`),
  ADD KEY `idx_gwa` (`gwa`),
  ADD KEY `idx_generated_by` (`generated_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_public` (`is_public`),
  ADD KEY `idx_updated_by` (`updated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dept_year_section` (`department`,`year_level`,`section`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_periods`
--
ALTER TABLE `academic_periods`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique period identifier', AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `application_periods`
--
ALTER TABLE `application_periods`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique application period identifier', AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique log entry identifier';

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique grade record identifier', AUTO_INCREMENT=1782;

--
-- AUTO_INCREMENT for table `grade_submissions`
--
ALTER TABLE `grade_submissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique submission identifier', AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `gwa_calculations`
--
ALTER TABLE `gwa_calculations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique calculation identifier', AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `honor_applications`
--
ALTER TABLE `honor_applications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique application identifier', AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `honor_rankings`
--
ALTER TABLE `honor_rankings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique ranking identifier', AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique notification identifier', AUTO_INCREMENT=230;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique setting identifier';

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique user identifier', AUTO_INCREMENT=55;

-- --------------------------------------------------------

--
-- Structure for view `student_gwa_summary`
--
DROP TABLE IF EXISTS `student_gwa_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `student_gwa_summary`  AS SELECT `u`.`id` AS `user_id`, `u`.`student_id` AS `student_id`, `u`.`first_name` AS `first_name`, `u`.`last_name` AS `last_name`, `u`.`department` AS `department`, `u`.`year_level` AS `year_level`, `u`.`section` AS `section`, `ap`.`period_name` AS `period_name`, `ap`.`school_year` AS `school_year`, `ap`.`semester` AS `semester`, `gwa`.`gwa` AS `gwa`, `gwa`.`total_units` AS `total_units`, `gwa`.`subjects_count` AS `subjects_count`, `gwa`.`failed_subjects` AS `failed_subjects`, `gwa`.`has_grade_above_25` AS `has_grade_above_25`, `gwa`.`calculated_at` AS `calculated_at`, (case when ((`u`.`year_level` = 4) and (`gwa`.`gwa` >= 1.00) and (`gwa`.`gwa` <= 1.25)) then 'Summa Cum Laude' when ((`u`.`year_level` = 4) and (`gwa`.`gwa` >= 1.26) and (`gwa`.`gwa` <= 1.45)) then 'Magna Cum Laude' when ((`u`.`year_level` = 4) and (`gwa`.`gwa` >= 1.46) and (`gwa`.`gwa` <= 1.75)) then 'Cum Laude' when ((`gwa`.`gwa` <= 1.25) and (`gwa`.`has_grade_above_25` = 0) and (`gwa`.`failed_subjects` = 0)) then 'Presidents List' when ((`gwa`.`gwa` <= 1.75) and (`gwa`.`has_grade_above_25` = 0) and (`gwa`.`failed_subjects` = 0)) then 'Deans List' else 'Regular' end) AS `honor_classification`, (case when ((`gwa`.`gwa` <= 1.25) and (`gwa`.`has_grade_above_25` = 0) and (`gwa`.`failed_subjects` = 0)) then 1 when ((`gwa`.`gwa` <= 1.75) and (`gwa`.`has_grade_above_25` = 0) and (`gwa`.`failed_subjects` = 0)) then 1 else 0 end) AS `is_honor_eligible` FROM ((`users` `u` join `gwa_calculations` `gwa` on((`u`.`id` = `gwa`.`user_id`))) join `academic_periods` `ap` on((`gwa`.`academic_period_id` = `ap`.`id`))) WHERE ((`u`.`role` = 'student') AND (`u`.`status` = 'active')) ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `application_periods`
--
ALTER TABLE `application_periods`
  ADD CONSTRAINT `application_periods_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `grade_submissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `grade_submissions`
--
ALTER TABLE `grade_submissions`
  ADD CONSTRAINT `grade_submissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grade_submissions_ibfk_2` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `grade_submissions_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `gwa_calculations`
--
ALTER TABLE `gwa_calculations`
  ADD CONSTRAINT `gwa_calculations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gwa_calculations_ibfk_2` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `gwa_calculations_ibfk_3` FOREIGN KEY (`submission_id`) REFERENCES `grade_submissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `honor_applications`
--
ALTER TABLE `honor_applications`
  ADD CONSTRAINT `honor_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `honor_applications_ibfk_2` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `honor_applications_ibfk_3` FOREIGN KEY (`gwa_calculation_id`) REFERENCES `gwa_calculations` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `honor_applications_ibfk_4` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `honor_applications_ibfk_5` FOREIGN KEY (`final_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `honor_rankings`
--
ALTER TABLE `honor_rankings`
  ADD CONSTRAINT `honor_rankings_ibfk_1` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `honor_rankings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `honor_rankings_ibfk_3` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
