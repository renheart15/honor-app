<?php
session_start();
require_once '../config/config.php';
require_once '../classes/GradeProcessor.php'; // Contains the GradeProcessor class definition
require_once '../includes/application-periods.php'; // Helper functions for application periods

$database = new Database();
$db = $database->getConnection();

// Verify that the user is logged in
requireLogin();

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply'])) {
    $user_id = $_SESSION['user_id'];
    $application_type = $_POST['application_type'] ?? '';

    // Check if applications are open
    $department = $_SESSION['department'] ?? '';
    $can_apply = canStudentApply($db, $user_id);

    if (!$can_apply['can_apply']) {
        $message = $can_apply['reason'];
        $message_type = 'error';
    } elseif (empty($application_type)) {
        $message = 'Please select an honor type.';
        $message_type = 'error';
    } else {
        // Check if user already has a pending application for this period
        $active_period = $can_apply['active_period'];
        $check_query = "SELECT COUNT(*) as count FROM honor_applications
                       WHERE user_id = :user_id
                       AND status IN ('submitted', 'under_review', 'approved', 'final_approved')
                       AND submitted_at BETWEEN :start_date AND :end_date";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->bindParam(':start_date', $active_period['start_date']);
        $check_stmt->bindParam(':end_date', $active_period['end_date']);
        $check_stmt->execute();
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing['count'] > 0) {
            $message = 'You already have a pending application for this period.';
            $message_type = 'error';
        } else {
            // Get student's current GWA for the application using accurate calculation
            $current_period = $_SESSION['current_period'] ?? null;
            $gwa_data_for_submission = null;

            if ($current_period) {
                $submission_gwa_query = "
                    SELECT g.*, g.semester_taken
                    FROM grades g
                    JOIN grade_submissions gs ON g.submission_id = gs.id
                    WHERE gs.user_id = :user_id
                    AND gs.academic_period_id = :current_period
                    AND gs.status = 'processed'
                ";

                $submission_gwa_stmt = $db->prepare($submission_gwa_query);
                $submission_gwa_stmt->bindParam(':user_id', $user_id);
                $submission_gwa_stmt->bindParam(':current_period', $current_period);
                $submission_gwa_stmt->execute();
                $submission_grades = $submission_gwa_stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($submission_grades)) {
                    $total_grade_points = 0;
                    $total_units = 0;
                    $valid_grades_count = 0;

                    foreach ($submission_grades as $grade) {
                        // Skip NSTP subjects and ongoing subjects (grade = 0) from GWA calculation
                        if (strpos($grade['subject_name'], 'NSTP') !== false ||
                            strpos($grade['subject_name'], 'NATIONAL SERVICE TRAINING') !== false ||
                            $grade['grade'] == 0) {
                            continue;
                        }

                        $total_grade_points += ($grade['grade'] * $grade['units']);
                        $total_units += $grade['units'];
                        $valid_grades_count++;
                    }

                    if ($total_units > 0) {
                        $gwa_exact = $total_grade_points / $total_units;
                        $gwa_calculated = floor($gwa_exact * 100) / 100; // Apply proper truncation
                        $gwa_data_for_submission = [
                            'gwa' => $gwa_calculated,
                            'total_units' => $total_units,
                            'subjects_count' => $valid_grades_count,
                            'id' => 1 // Dummy ID for compatibility
                        ];
                    }
                }
            }

            if ($gwa_data_for_submission) {
                // Validate required data
                if (!$current_period || !is_numeric($current_period)) {
                    $message = 'Invalid academic period. Please contact administrator.';
                    $message_type = 'error';
                } else {
                    // Insert the application
                    $insert_query = "INSERT INTO honor_applications
                                   (user_id, academic_period_id, gwa_calculation_id, application_type, gwa_achieved, required_gwa, status)
                                   VALUES (:user_id, :academic_period_id, :gwa_calculation_id, :application_type, :gwa_achieved, :required_gwa, 'submitted')";
                    $insert_stmt = $db->prepare($insert_query);

                    // Set required GWA based on application type
                    $required_gwa_map = [
                        'deans_list' => 1.75,
                        'cum_laude' => 1.75,
                        'magna_cum_laude' => 1.45,
                        'summa_cum_laude' => 1.25
                    ];
                    $required_gwa = $required_gwa_map[$application_type] ?? 1.75;

                    // Create or get GWA calculation record
                    $gwa_achieved = $gwa_data_for_submission['gwa'];

                    // Check if a GWA calculation record exists for this user and period
                    $check_gwa_query = "SELECT id FROM gwa_calculations WHERE user_id = :user_id AND academic_period_id = :academic_period_id";
                    $check_gwa_stmt = $db->prepare($check_gwa_query);
                    $check_gwa_stmt->bindParam(':user_id', $user_id);
                    $check_gwa_stmt->bindParam(':academic_period_id', $current_period);
                    $check_gwa_stmt->execute();
                    $existing_calculation = $check_gwa_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing_calculation) {
                        $gwa_calculation_id = $existing_calculation['id'];

                        // Update the existing calculation with accurate GWA
                        $total_units = $gwa_data_for_submission['total_units'];
                        $subjects_count = $gwa_data_for_submission['subjects_count'];
                        $total_grade_points = $total_units * $gwa_achieved;

                        $update_gwa_query = "UPDATE gwa_calculations
                                           SET gwa = :gwa, total_units = :total_units, subjects_count = :subjects_count,
                                               total_grade_points = :total_grade_points, recalculated_at = NOW()
                                           WHERE id = :id";
                        $update_gwa_stmt = $db->prepare($update_gwa_query);
                        $update_gwa_stmt->bindParam(':gwa', $gwa_achieved);
                        $update_gwa_stmt->bindParam(':total_units', $total_units);
                        $update_gwa_stmt->bindParam(':subjects_count', $subjects_count);
                        $update_gwa_stmt->bindParam(':total_grade_points', $total_grade_points);
                        $update_gwa_stmt->bindParam(':id', $gwa_calculation_id);
                        $update_gwa_stmt->execute();
                    } else {
                        // Create a new GWA calculation record
                        $insert_gwa_query = "INSERT INTO gwa_calculations
                                           (user_id, academic_period_id, total_units, total_grade_points, gwa, subjects_count,
                                            failed_subjects, incomplete_subjects, has_grade_above_25, calculation_method, calculated_at)
                                           VALUES
                                           (:user_id, :academic_period_id, :total_units, :total_grade_points, :gwa, :subjects_count,
                                            0, 0, 0, 'direct_calculation', NOW())";

                        // Prepare variables for binding (cannot bind expressions directly)
                        $total_units = $gwa_data_for_submission['total_units'];
                        $subjects_count = $gwa_data_for_submission['subjects_count'];
                        $total_grade_points = $total_units * $gwa_achieved;

                        $insert_gwa_stmt = $db->prepare($insert_gwa_query);
                        $insert_gwa_stmt->bindParam(':user_id', $user_id);
                        $insert_gwa_stmt->bindParam(':academic_period_id', $current_period);
                        $insert_gwa_stmt->bindParam(':total_units', $total_units);
                        $insert_gwa_stmt->bindParam(':total_grade_points', $total_grade_points);
                        $insert_gwa_stmt->bindParam(':gwa', $gwa_achieved);
                        $insert_gwa_stmt->bindParam(':subjects_count', $subjects_count);

                        if ($insert_gwa_stmt->execute()) {
                            $gwa_calculation_id = $db->lastInsertId();
                        } else {
                            $message = 'Failed to create GWA calculation record.';
                            $message_type = 'error';
                            $gwa_calculation_id = null;
                        }
                    }

                    // Only proceed with application submission if we have a valid GWA calculation ID
                    if ($gwa_calculation_id) {

                    $insert_stmt->bindParam(':user_id', $user_id);
                    $insert_stmt->bindParam(':academic_period_id', $current_period);
                    $insert_stmt->bindParam(':gwa_calculation_id', $gwa_calculation_id);
                    $insert_stmt->bindParam(':application_type', $application_type);
                    $insert_stmt->bindParam(':gwa_achieved', $gwa_achieved);
                    $insert_stmt->bindParam(':required_gwa', $required_gwa);

                        if ($insert_stmt->execute()) {
                            $message = 'Honor application submitted successfully!';
                            $message_type = 'success';
                        } else {
                            $message = 'Failed to submit application. Please try again.';
                            $message_type = 'error';
                        }
                    }
                }
            } else {
                $message = 'Unable to retrieve your GWA data. Please upload your grades first.';
                $message_type = 'error';
            }
        }
    }
}

// Retrieve required session values and/or fetch them from the database as needed
$user_id = $_SESSION['user_id'];
$user_query = $db->prepare("SELECT year_level FROM users WHERE id = :id LIMIT 1");
$user_query->bindParam(':id', $user_id);
$user_query->execute();
$user = $user_query->fetch(PDO::FETCH_ASSOC);
$user_year_level = $user['year_level'] ?? null;

// (Optional) Update session too if needed later
$_SESSION['year_level'] = $user_year_level;

// Get the current active academic period
$active_period_query = "SELECT * FROM academic_periods WHERE is_active = 1 LIMIT 1";
$active_period_stmt = $db->prepare($active_period_query);
$active_period_stmt->execute();
$active_academic_period = $active_period_stmt->fetch(PDO::FETCH_ASSOC);

// Set current period for session compatibility
$current_period = $active_academic_period['id'] ?? null;
$_SESSION['current_period'] = $current_period;

// Calculate GWA directly from grades table for accuracy (like grades.php)
$gwa_data = null;
if ($current_period) {
    // Get the academic period info to build the correct semester filter
    $period_info_query = "SELECT semester, school_year FROM academic_periods WHERE id = :current_period";
    $period_info_stmt = $db->prepare($period_info_query);
    $period_info_stmt->bindParam(':current_period', $current_period);
    $period_info_stmt->execute();
    $period_info = $period_info_stmt->fetch(PDO::FETCH_ASSOC);

    if ($period_info) {
        // Build semester string to match grades table format (e.g., "1st Semester SY 2024-2025")
        $current_semester_filter = $period_info['semester'] . ' Semester SY ' . $period_info['school_year'];

        $current_gwa_query = "
            SELECT g.*, g.semester_taken
            FROM grades g
            JOIN grade_submissions gs ON g.submission_id = gs.id
            WHERE gs.user_id = :user_id
            AND gs.academic_period_id = :current_period
            AND gs.status = 'processed'
            AND g.semester_taken = :semester_filter
        ";

        $current_gwa_stmt = $db->prepare($current_gwa_query);
        $current_gwa_stmt->bindParam(':user_id', $user_id);
        $current_gwa_stmt->bindParam(':current_period', $current_period);
        $current_gwa_stmt->bindParam(':semester_filter', $current_semester_filter);
        $current_gwa_stmt->execute();
        $current_period_grades = $current_gwa_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $current_period_grades = [];
    }

    if (!empty($current_period_grades)) {
        $total_grade_points = 0;
        $total_units = 0;
        $valid_grades_count = 0;

        foreach ($current_period_grades as $grade) {
            // Skip NSTP subjects and ongoing subjects (grade = 0) from GWA calculation
            if (strpos($grade['subject_name'], 'NSTP') !== false ||
                strpos($grade['subject_name'], 'NATIONAL SERVICE TRAINING') !== false ||
                $grade['grade'] == 0) {
                continue;
            }

            $total_grade_points += ($grade['grade'] * $grade['units']);
            $total_units += $grade['units'];
            $valid_grades_count++;
        }

        if ($total_units > 0) {
            $gwa_exact = $total_grade_points / $total_units;
            $gwa_calculated = floor($gwa_exact * 100) / 100; // Apply proper truncation
            $gwa_data = [
                'gwa' => $gwa_calculated,
                'total_units' => $total_units,
                'subjects_count' => $valid_grades_count,
                'calculated_at' => date('Y-m-d H:i:s')
            ];
        }
    }
}

// Get GWA for the designated application period (most recent period with grades)
$application_period_gwa = null;
$designated_academic_period = null;

// Check if applications are open and get the application period
$department = $_SESSION['department'] ?? '';
$application_status = canStudentApply($db, $user_id);

if ($application_status['can_apply'] && isset($application_status['active_period'])) {
    $active_app_period = $application_status['active_period'];

    // Try to find matching academic period for the application period
    $matching_academic_query = "
        SELECT * FROM academic_periods
        WHERE (semester = :semester OR semester = :semester_alt)
        AND (school_year = :school_year OR school_year = :school_year_alt)
        LIMIT 1
    ";

    $semester_clean = str_replace(' Semester', '', $active_app_period['semester']);
    $matching_stmt = $db->prepare($matching_academic_query);
    $matching_stmt->bindParam(':semester', $semester_clean);
    $matching_stmt->bindParam(':semester_alt', $active_app_period['semester']);
    $matching_stmt->bindParam(':school_year', $active_app_period['academic_year']);
    $matching_stmt->bindParam(':school_year_alt', $active_app_period['academic_year']);
    $matching_stmt->execute();
    $matching_academic = $matching_stmt->fetch(PDO::FETCH_ASSOC);

    // Check if the matching period has grades
    $designated_period_id = null;
    if ($matching_academic) {
        $grades_check_query = "
            SELECT COUNT(*) as grade_count
            FROM grades g
            JOIN grade_submissions gs ON g.submission_id = gs.id
            WHERE gs.user_id = :user_id
            AND gs.academic_period_id = :academic_period_id
            AND gs.status = 'processed'
        ";

        $grades_check_stmt = $db->prepare($grades_check_query);
        $grades_check_stmt->bindParam(':user_id', $user_id);
        $grades_check_stmt->bindParam(':academic_period_id', $matching_academic['id']);
        $grades_check_stmt->execute();
        $grades_check = $grades_check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($grades_check['grade_count'] > 0) {
            // Use the matching academic period
            $designated_period_id = $matching_academic['id'];
            $designated_academic_period = $matching_academic;
        }
    }

    // If no grades in the application period, use the most recent period with grades
    if (!$designated_period_id) {
        $recent_period_query = "
            SELECT ap.*
            FROM academic_periods ap
            JOIN grade_submissions gs ON ap.id = gs.academic_period_id
            JOIN grades g ON gs.id = g.submission_id
            WHERE gs.user_id = :user_id AND gs.status = 'processed'
            GROUP BY ap.id
            ORDER BY ap.school_year DESC, ap.semester DESC
            LIMIT 1
        ";

        $recent_stmt = $db->prepare($recent_period_query);
        $recent_stmt->bindParam(':user_id', $user_id);
        $recent_stmt->execute();
        $recent_period = $recent_stmt->fetch(PDO::FETCH_ASSOC);

        if ($recent_period) {
            $designated_period_id = $recent_period['id'];
            $designated_academic_period = $recent_period;
        }
    }

    // Calculate GWA for the designated period
    if ($designated_period_id) {
        // Build semester string to match grades table format
        $semester_filter = $active_app_period['semester'] . ' SY ' . $active_app_period['academic_year'];

        $app_period_query = "
            SELECT g.*, g.semester_taken
            FROM grades g
            JOIN grade_submissions gs ON g.submission_id = gs.id
            WHERE gs.user_id = :user_id
            AND gs.academic_period_id = :designated_period_id
            AND gs.status = 'processed'
            AND g.semester_taken = :semester_filter
        ";

        $app_period_stmt = $db->prepare($app_period_query);
        $app_period_stmt->bindParam(':user_id', $user_id);
        $app_period_stmt->bindParam(':designated_period_id', $designated_period_id);
        $app_period_stmt->bindParam(':semester_filter', $semester_filter);
        $app_period_stmt->execute();
        $app_period_grades = $app_period_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($app_period_grades)) {
            $total_grade_points = 0;
            $total_units = 0;
            $valid_grades_count = 0;

            foreach ($app_period_grades as $grade) {
                // Skip NSTP subjects and ongoing subjects (grade = 0) from GWA calculation
                if (strpos($grade['subject_name'], 'NSTP') !== false ||
                    strpos($grade['subject_name'], 'NATIONAL SERVICE TRAINING') !== false ||
                    $grade['grade'] == 0) {
                    continue;
                }

                $total_grade_points += ($grade['grade'] * $grade['units']);
                $total_units += $grade['units'];
                $valid_grades_count++;
            }

            if ($total_units > 0) {
                $gwa_exact = $total_grade_points / $total_units;
                $gwa_calculated = floor($gwa_exact * 100) / 100; // Apply proper truncation
                $application_period_gwa = [
                    'gwa' => $gwa_calculated,
                    'total_units' => $total_units,
                    'subjects_count' => $valid_grades_count,
                    'calculated_at' => date('Y-m-d H:i:s'),
                    'period_name' => $designated_academic_period['period_name'] ?? 'Unknown Period',
                    'period_id' => $designated_period_id
                ];
            }
        }
    }
}

// Count total semesters completed by student using the accurate grades table method
$semester_count_query = "
    SELECT g.semester_taken
    FROM grades g
    JOIN grade_submissions gs ON g.submission_id = gs.id
    WHERE gs.user_id = :user_id
    AND gs.status = 'processed'
    GROUP BY g.semester_taken
";

$semester_count_stmt = $db->prepare($semester_count_query);
$semester_count_stmt->bindParam(':user_id', $user_id);
$semester_count_stmt->execute();
$semester_results = $semester_count_stmt->fetchAll(PDO::FETCH_ASSOC);

// Count only regular semesters (1st and 2nd), exclude summer
$total_semesters = 0;
foreach ($semester_results as $semester) {
    $semester_taken = $semester['semester_taken'];
    if (strpos($semester_taken, '1st Semester') !== false || strpos($semester_taken, '2nd Semester') !== false) {
        $total_semesters++;
    }
}
$calculated_year_level = ceil($total_semesters / 2); // 2 semesters per year

// Check if student is in 2nd semester 4th year based on semester count and current period
$overall_gwa_data = null;
$show_overall_gwa = false;
$is_2nd_sem_4th_year = ($calculated_year_level >= 4 && $active_academic_period && $active_academic_period['semester'] === '2nd');

if ($is_2nd_sem_4th_year) {
    // Check if student has completed at least 4 semesters (2nd year equivalent)
    // For overall GWA display, they need at least 4 semesters completed
    $has_sufficient_semesters = ($total_semesters >= 4);

    if ($has_sufficient_semesters) {
        $show_overall_gwa = true;

        // Calculate overall GWA using the accurate grades table method
        $overall_gwa_query = "
            SELECT g.*, g.semester_taken
            FROM grades g
            JOIN grade_submissions gs ON g.submission_id = gs.id
            WHERE gs.user_id = :user_id
            AND gs.status = 'processed'
            ORDER BY
                CASE
                    WHEN g.semester_taken REGEXP '[0-9]{4}-[0-9]{4}' THEN SUBSTRING(g.semester_taken, -9)
                    ELSE '0000-0000'
                END DESC,
                CASE
                    WHEN g.semester_taken LIKE '%2nd%' THEN 1
                    WHEN g.semester_taken LIKE '%1st%' THEN 2
                    WHEN g.semester_taken LIKE '%Summer%' OR g.semester_taken LIKE '%summer%' THEN 3
                    ELSE 4
                END ASC
        ";

        $overall_gwa_stmt = $db->prepare($overall_gwa_query);
        $overall_gwa_stmt->bindParam(':user_id', $user_id);
        $overall_gwa_stmt->execute();
        $all_student_grades = $overall_gwa_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($all_student_grades)) {
            $total_grade_points = 0;
            $total_units = 0;
            $periods_set = [];

            foreach ($all_student_grades as $grade) {
                // Skip NSTP subjects and ongoing subjects (grade = 0) from GWA calculation
                if (strpos($grade['subject_name'], 'NSTP') !== false ||
                    strpos($grade['subject_name'], 'NATIONAL SERVICE TRAINING') !== false ||
                    $grade['grade'] == 0) {
                    continue;
                }

                $total_grade_points += ($grade['grade'] * $grade['units']);
                $total_units += $grade['units'];

                // Track unique academic periods
                $period_key = $grade['semester_taken'];
                $periods_set[$period_key] = true;
            }

            if ($total_units > 0) {
                $overall_gwa_exact = $total_grade_points / $total_units;
                $overall_gwa_calculated = floor($overall_gwa_exact * 100) / 100; // Apply proper truncation
                $overall_gwa_data = [
                    'gwa' => $overall_gwa_calculated,
                    'total_units' => $total_units,
                    'periods_count' => count($periods_set),
                    'total_semesters' => $total_semesters,
                    'calculated_year' => $calculated_year_level
                ];
            }
        }
    }
}

// Initialize eligibility flags
$eligible_for_deans  = false;
$eligible_for_summa  = false;
$eligible_for_magna  = false;
$eligible_for_cum_laude = false;
$has_grade_above_25  = false;

if ($gwa_data) {
    // Check if the student has any individual grade above 2.5 (only from processed submissions)
    $grade_check_query = "SELECT COUNT(*) as count
                          FROM grades g
                          JOIN grade_submissions gs ON g.submission_id = gs.id
                          WHERE gs.user_id = :user_id
                          AND g.grade > 2.5
                          AND gs.status = 'processed'";
    $grade_check_stmt = $db->prepare($grade_check_query);
    $grade_check_stmt->bindParam(':user_id', $user_id);
    $grade_check_stmt->execute();
    $grade_check = $grade_check_stmt->fetch(PDO::FETCH_ASSOC);
    $has_grade_above_25 = ($grade_check['count'] > 0);

    // Determine Dean's List eligibility (for all year levels)
    $eligible_for_deans = ($gwa_data['gwa'] >= 1.00 && $gwa_data['gwa'] <= 1.75) && !$has_grade_above_25;

    // Check for ongoing grades (grade = 0.00)
    $ongoing_grades_query = "SELECT COUNT(*) as count
                            FROM grades g
                            JOIN grade_submissions gs ON g.submission_id = gs.id
                            WHERE gs.user_id = :user_id
                            AND g.grade = 0.00
                            AND gs.status = 'processed'";
    $ongoing_grades_stmt = $db->prepare($ongoing_grades_query);
    $ongoing_grades_stmt->bindParam(':user_id', $user_id);
    $ongoing_grades_stmt->execute();
    $ongoing_grades = $ongoing_grades_stmt->fetch(PDO::FETCH_ASSOC);
    $has_ongoing_grades = ($ongoing_grades['count'] > 0);

    // Determine Latin Honors eligibility (only for students with 8+ semesters and no ongoing grades)
    if ($total_semesters >= 8 && !$has_ongoing_grades && !$has_grade_above_25) {
        $eligible_for_summa = ($gwa_data['gwa'] >= 1.00 && $gwa_data['gwa'] <= 1.25);
        $eligible_for_magna = ($gwa_data['gwa'] >= 1.26 && $gwa_data['gwa'] <= 1.45);
        $eligible_for_cum_laude = ($gwa_data['gwa'] >= 1.46 && $gwa_data['gwa'] <= 1.75);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades - CTU Honor System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .form-section label {
            margin-bottom: 0.5rem;
        }

        .form-section select {
            padding: 0.75rem 1rem;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="hidden md:flex md:w-64 md:flex-col">
            <div class="flex flex-col flex-grow bg-white border-r border-gray-200 pt-5 pb-4 overflow-y-auto">
                <div class="flex items-center flex-shrink-0 px-4">
                    <div class="w-8 h-8 bg-primary-600 rounded-xl flex items-center justify-center">
                        <i data-lucide="graduation-cap" class="w-5 h-5 text-white"></i>
                    </div>
                    <span class="ml-2 text-xl font-bold text-gray-900">CTU Honor</span>
                </div>

                <!-- User Profile -->
                <div class="mt-8 px-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i data-lucide="user" class="w-5 h-5 text-primary-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-semibold text-gray-900"><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></p>
                            <p class="text-xs text-gray-500">Student</p>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="mt-8 flex-1 px-2 space-y-1">
                    <a href="dashboard.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="layout-dashboard" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Dashboard
                    </a>
                    <a href="grades.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="bar-chart-3" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        My Grades
                    </a>
                    <a href="applications.php" class="bg-primary-50 border-r-2 border-primary-600 text-primary-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="trophy" class="text-primary-500 mr-3 h-5 w-5"></i>
                        Applications
                    </a>
                    <a href="profile.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="settings" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Profile
                    </a>
                </nav>

                <!-- Logout -->
                <div class="px-2 pb-2">
                    <a href="../logout.php" class="text-gray-600 hover:bg-red-50 hover:text-red-700 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="log-out" class="text-gray-400 group-hover:text-red-500 mr-3 h-5 w-5"></i>
                        Sign Out
                    </a>
                </div>
            </div>
        </div>
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex items-center">
                        <button type="button" class="md:hidden -ml-0.5 -mt-0.5 h-12 w-12 inline-flex items-center justify-center rounded-xl text-gray-500 hover:text-gray-900">
                            <i data-lucide="menu" class="h-6 w-6"></i>
                        </button>
                        <div class="ml-4 md:ml-0">
                            <h1 class="text-2xl font-bold text-gray-900">Applications</h1>
                            <p class="text-sm text-gray-500">Submit your application here</p>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto bg-gray-50">
                <div class="p-6 sm:p-8 lg:p-10 max-w-6xl mx-auto">
                    <?php if ($message): ?>
                        <div class="mb-8 p-5 rounded-2xl border-2 shadow-sm <?php echo $message_type === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'; ?>">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i data-lucide="<?php echo $message_type === 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-6 h-6 <?php echo $message_type === 'success' ? 'text-green-500' : 'text-red-500'; ?>"></i>
                                </div>
                                <div class="ml-3">
                                    <span class="<?php echo $message_type === 'success' ? 'text-green-800' : 'text-red-800'; ?> font-medium"><?php echo htmlspecialchars($message); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Application Period Status -->
                    <?php
                    $department = $_SESSION['department'] ?? '';
                    $application_period_status = canStudentApply($db, $_SESSION['user_id']);
                    ?>

                    <?php if (!$application_period_status['can_apply']): ?>
                        <div class="mb-8 p-8 bg-gradient-to-br from-amber-50 to-orange-50 border-2 border-amber-200 rounded-3xl shadow-sm">
                            <div class="flex items-center mb-6">
                                <div class="flex-shrink-0 w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center">
                                    <i data-lucide="calendar-x" class="w-6 h-6 text-amber-600"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-xl font-bold text-amber-900">Applications Currently Closed</h3>
                                    <p class="text-amber-700 mt-1"><?php echo htmlspecialchars($application_period_status['reason']); ?></p>
                                </div>
                            </div>

                            <?php if (isset($application_period_status['next_period']) && $application_period_status['next_period']): ?>
                                <div class="bg-white bg-opacity-60 rounded-2xl p-6 border border-amber-200">
                                    <h4 class="font-bold text-amber-900 mb-4 flex items-center">
                                        <i data-lucide="calendar-clock" class="w-5 h-5 mr-2"></i>
                                        Next Application Period
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-amber-800">
                                        <div class="text-center">
                                            <p class="text-sm font-medium text-amber-600">Period</p>
                                            <p class="font-bold"><?php echo htmlspecialchars($application_period_status['next_period']['semester'] . ' ' . $application_period_status['next_period']['academic_year']); ?></p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-sm font-medium text-amber-600">Opens</p>
                                            <p class="font-bold"><?php echo date('M d, Y', strtotime($application_period_status['next_period']['start_date'])); ?></p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-sm font-medium text-amber-600">Closes</p>
                                            <p class="font-bold"><?php echo date('M d, Y', strtotime($application_period_status['next_period']['end_date'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php $active_period = $application_period_status['active_period']; ?>
                        <div class="mb-8 p-8 bg-gradient-to-br from-emerald-50 to-green-50 border-2 border-emerald-200 rounded-3xl shadow-sm">
                            <div class="flex items-center mb-6">
                                <div class="flex-shrink-0 w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center">
                                    <i data-lucide="calendar-check" class="w-6 h-6 text-emerald-600"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-xl font-bold text-emerald-900">Applications Now Open!</h3>
                                    <p class="text-emerald-700 mt-1">Submit your honor application now</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="bg-white bg-opacity-60 rounded-2xl p-4 border border-emerald-200 text-center">
                                    <p class="text-sm font-medium text-emerald-600 mb-1">Current Period</p>
                                    <p class="font-bold text-emerald-900"><?php echo htmlspecialchars($active_period['semester'] . ' ' . $active_period['academic_year']); ?></p>
                                </div>
                                <div class="bg-white bg-opacity-60 rounded-2xl p-4 border border-emerald-200 text-center">
                                    <p class="text-sm font-medium text-emerald-600 mb-1">Deadline</p>
                                    <p class="font-bold text-emerald-900"><?php echo date('M d, Y', strtotime($active_period['end_date'])); ?></p>
                                </div>
                                <?php
                                $days_remaining = getDaysRemaining($active_period);
                                if ($days_remaining > 0):
                                ?>
                                    <div class="bg-white bg-opacity-60 rounded-2xl p-4 border border-emerald-200 text-center">
                                        <p class="text-sm font-medium text-emerald-600 mb-1">Days Left</p>
                                        <p class="font-bold text-emerald-900 text-2xl"><?php echo $days_remaining; ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!$gwa_data): ?>
                        <div class="text-center py-6">
                            <p class="text-gray-500">Upload your grades first to apply for honors.</p>
                        </div>
                    <?php else: ?>
                        <!-- GWA Information Section -->
                        <div class="space-y-6 mt-6">
                            <!-- Application Period GWA (Always show if available) -->
                            <?php if ($application_period_gwa): ?>
                                <div class="bg-gradient-to-br from-emerald-50 to-green-50 rounded-3xl shadow-lg border-2 border-emerald-200 p-6">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center mr-4">
                                                <i data-lucide="calendar-check" class="w-6 h-6 text-emerald-600"></i>
                                            </div>
                                            <div>
                                                <div class="text-3xl font-bold text-emerald-600"><?php echo formatGWA($application_period_gwa['gwa']); ?></div>
                                                <p class="text-sm font-semibold text-emerald-900">Application Period GWA</p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-medium text-emerald-700">
                                                <?php
                                                if (isset($application_period_gwa['period_name'])) {
                                                    echo htmlspecialchars($application_period_gwa['period_name']);
                                                } else {
                                                    $semester_display = $active_academic_period['semester'] === '1st' ? '1st Semester' :
                                                                      ($active_academic_period['semester'] === '2nd' ? '2nd Semester' :
                                                                      ucfirst($active_academic_period['semester']));
                                                    echo htmlspecialchars($semester_display . ' ' . $active_academic_period['school_year']);
                                                }
                                                ?>
                                            </p>
                                            <p class="text-xs text-emerald-600">
                                                <?php
                                                if (isset($application_period_gwa['period_id']) && $designated_academic_period) {
                                                    echo "Most Recent Grades Period";
                                                } else {
                                                    echo "Open for Applications";
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Main GWA and Eligibility Section -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-stretch">
                                <!-- Current/Selected Period GWA Card -->
                                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-3xl shadow-lg border-2 border-blue-200 p-8 text-center h-full">
                                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i data-lucide="graduation-cap" class="w-8 h-8 text-blue-600"></i>
                                    </div>
                                    <!-- Current Period GWA -->
                                    <div class="text-5xl font-bold text-blue-600 mb-2"><?php echo formatGWA($gwa_data['gwa']); ?></div>
                                    <p class="text-lg font-semibold text-blue-900 mb-1">
                                        <?php if ($application_period_gwa && $current_period == $active_academic_period['id']): ?>
                                            Current Period GWA
                                        <?php elseif ($application_period_gwa): ?>
                                            Selected Period GWA
                                        <?php else: ?>
                                            Current Period GWA
                                        <?php endif; ?>
                                    </p>
                                    <p class="text-blue-700 mb-1">Calculated Year: <?php echo $calculated_year_level; ?> (<?php echo $total_semesters; ?> semesters)</p>
                                    <p class="text-blue-600 text-sm">Registered Year: <?php echo htmlspecialchars((string)($user_year_level ?? 'N/A')); ?></p>

                                <?php if ($active_academic_period): ?>
                                    <div class="mt-4 pt-4 border-t border-blue-200">
                                        <p class="text-sm font-medium text-blue-600 mb-1">Academic Period</p>
                                        <p class="text-sm font-bold text-blue-900">
                                            <?php
                                            $semester_display = $active_academic_period['semester'] === '1st' ? '1st Semester' :
                                                              ($active_academic_period['semester'] === '2nd' ? '2nd Semester' :
                                                              ucfirst($active_academic_period['semester']));
                                            echo htmlspecialchars($semester_display . ' ' . $active_academic_period['school_year']);
                                            ?>
                                        </p>
                                        <p class="text-xs text-blue-600 mt-1">
                                            <?php echo date('M d', strtotime($active_academic_period['start_date'])); ?> -
                                            <?php echo date('M d, Y', strtotime($active_academic_period['end_date'])); ?>
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-4 pt-4 border-t border-blue-200">
                                        <p class="text-sm text-blue-600">No active academic period</p>
                                    </div>
                                <?php endif; ?>

                                <!-- Overall GWA for 2nd Semester 4th Year Students (only if they have 2nd year grades) -->
                                <?php if ($is_2nd_sem_4th_year && $show_overall_gwa && $overall_gwa_data): ?>
                                    <div class="mt-4 pt-4 border-t border-purple-200 bg-gradient-to-r from-purple-50 to-indigo-50 rounded-2xl p-4 -mx-2">
                                        <div class="flex items-center justify-center mb-2">
                                            <i data-lucide="trophy" class="w-5 h-5 text-purple-600 mr-2"></i>
                                            <p class="text-sm font-bold text-purple-800">Overall Current GWA</p>
                                        </div>
                                        <div class="text-3xl font-bold text-purple-600 mb-1"><?php echo formatGWA($overall_gwa_data['gwa']); ?></div>
                                        <p class="text-xs text-purple-700">
                                            Across <?php echo $overall_gwa_data['total_semesters']; ?> semesters (<?php echo $overall_gwa_data['calculated_year']; ?> years)
                                        </p>
                                        <p class="text-xs text-purple-600 mt-1">
                                            Total Units: <?php echo number_format($overall_gwa_data['total_units'], 1); ?>
                                        </p>
                                    </div>
                                <?php elseif ($is_2nd_sem_4th_year && !$show_overall_gwa): ?>
                                    <!-- Message for 4th year students without 2nd year grades -->
                                    <div class="mt-4 pt-4 border-t border-amber-200 bg-gradient-to-r from-amber-50 to-yellow-50 rounded-2xl p-4 -mx-2">
                                        <div class="flex items-center justify-center mb-2">
                                            <i data-lucide="info" class="w-5 h-5 text-amber-600 mr-2"></i>
                                            <p class="text-sm font-bold text-amber-800">Application Period GWA Only</p>
                                        </div>
                                        <p class="text-xs text-amber-700 text-center">
                                            Overall GWA unavailable - need at least 4 semesters (currently <?php echo $total_semesters; ?>)
                                        </p>
                                    </div>
                                <?php endif; ?>
                                </div>

                                <!-- Eligibility Information -->
                                <div class="space-y-4 h-full">
                                <!-- Dean's List -->
                                <div class="bg-green-50 rounded-xl p-5 shadow-sm">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-base font-semibold text-gray-900">Dean's List</h4>
                                            <p class="text-sm text-gray-600">GWA ≤ 1.75, no grade > 2.5</p>
                                        </div>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $eligible_for_deans ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo $eligible_for_deans ? 'Eligible' : 'Not Eligible'; ?>
                                        </span>
                                    </div>
                                    <?php if (!$eligible_for_deans): ?>
                                        <p class="text-xs text-red-500 mt-1">
                                            <?php if ($gwa_data['gwa'] > 1.75): ?>
                                                GWA too high (<?php echo formatGWA($gwa_data['gwa']); ?>)
                                            <?php elseif ($has_grade_above_25): ?>
                                                Has grade(s) above 2.5
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <!-- Latin Honors if 8+ semesters completed and no ongoing grades -->
                                <?php if ($total_semesters >= 8 && !$has_ongoing_grades): ?>
                                    <div class="bg-purple-50 rounded-xl p-5 shadow-sm">
                                        <h4 class="text-base font-semibold text-purple-900 mb-3">Latin Honors</h4>
                                        <div class="space-y-2 text-sm">
                                            <?php
                                                $honors = [
                                                    'Summa Cum Laude (1.00–1.25)' => $eligible_for_summa,
                                                    'Magna Cum Laude (1.26–1.45)' => $eligible_for_magna,
                                                    'Cum Laude (1.46–1.75)' => $eligible_for_cum_laude
                                                ];
                                                foreach ($honors as $label => $isEligible):
                                            ?>
                                                <div class="flex justify-between items-center">
                                                    <span><?php echo $label; ?></span>
                                                    <span class="px-2 py-1 rounded text-xs <?php echo $isEligible ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-600'; ?>">
                                                        <?php echo $isEligible ? 'Eligible' : 'Not Eligible'; ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="bg-amber-50 rounded-xl p-5 shadow-sm border border-amber-200">
                                        <h4 class="text-base font-semibold text-amber-900 mb-3">Latin Honors</h4>
                                        <div class="text-sm text-amber-800">
                                            <?php if ($total_semesters < 8): ?>
                                                <p class="mb-2"><strong>Requirements not met:</strong></p>
                                                <p>• Need at least 8 semesters completed (currently: <?php echo $total_semesters; ?>)</p>
                                            <?php endif; ?>
                                            <?php if ($has_ongoing_grades): ?>
                                                <p class="mb-2"><strong>Requirements not met:</strong></p>
                                                <p>• Cannot have ongoing grades (0.00)</p>
                                            <?php endif; ?>
                                            <p class="text-xs text-amber-700 mt-2">Latin honors are only available for graduating students with all grades completed.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!$eligible_for_deans && !$eligible_for_summa && !$eligible_for_magna && !$eligible_for_cum_laude): ?>
                            <div class="text-center py-12 bg-white rounded-3xl shadow-sm border border-gray-200 mt-8">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                    <i data-lucide="alert-circle" class="w-8 h-8 text-gray-400"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-2">Not Currently Eligible</h3>
                                <p class="text-gray-600 mb-4">Your current GWA and grades do not meet the requirements for any honor.</p>
                                <p class="text-sm text-gray-500">Keep working hard to improve your grades for the next application period!</p>
                            </div>
                        <?php else: ?>
                            <?php if (!$application_period_status['can_apply']): ?>
                                <div class="bg-gradient-to-br from-gray-50 to-gray-100 border-2 border-gray-200 rounded-3xl p-8 mt-8 shadow-sm max-w-lg mx-auto text-center">
                                    <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
                                        <i data-lucide="calendar-x" class="w-8 h-8 text-gray-500"></i>
                                    </div>
                                    <h4 class="text-xl font-bold text-gray-800 mb-3">Applications Currently Closed</h4>
                                    <p class="text-gray-600 mb-2">You are eligible for honors, but applications are currently closed.</p>
                                    <p class="text-sm text-gray-500">Please check back during the next application period.</p>
                                </div>
                            <?php else: ?>
                                <div class="bg-gradient-to-br from-white to-gray-50 border-2 border-primary-200 rounded-3xl p-8 mt-8 shadow-lg max-w-lg mx-auto">
                                    <div class="text-center mb-8">
                                        <div class="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                            <i data-lucide="send" class="w-8 h-8 text-primary-600"></i>
                                        </div>
                                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Submit Application</h3>
                                        <p class="text-gray-600">Choose the honor type you're eligible for</p>
                                    </div>
                                    <form method="POST" class="space-y-6">
                                        <div>
                                            <label for="application_type" class="block text-sm font-bold text-gray-800 mb-3">Select Honor Type</label>
                                            <select id="application_type" name="application_type" required
                                                    class="block w-full px-4 py-4 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all duration-200 bg-white text-gray-900 font-medium">
                                                <option value="">Choose honor type...</option>

                                                <?php if ($eligible_for_deans): ?>
                                                    <option value="deans_list">Dean's List (GWA ≤ 1.75, no grade > 2.5)</option>
                                                <?php endif; ?>

                                                <?php if ($user_year_level == 4): ?>
                                                    <?php if ($eligible_for_summa): ?>
                                                        <option value="summa_cum_laude">Summa Cum Laude (1.00-1.25 GWA)</option>
                                                    <?php endif; ?>
                                                    <?php if ($eligible_for_magna): ?>
                                                        <option value="magna_cum_laude">Magna Cum Laude (1.26-1.45 GWA)</option>
                                                    <?php endif; ?>
                                                    <?php if ($eligible_for_cum_laude): ?>
                                                        <option value="cum_laude">Cum Laude (1.46-1.75 GWA)</option>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <button type="submit" name="apply"
                                            class="w-full bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700 text-white font-bold py-4 px-6 rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105">
                                            <i data-lucide="send" class="w-5 h-5 inline mr-2"></i>
                                            Submit Application
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Student's Application History -->
                    <?php
                    $history_query = "SELECT ha.*, ap.period_name, ap.semester, ap.school_year
                                     FROM honor_applications ha
                                     LEFT JOIN academic_periods ap ON ha.academic_period_id = ap.id
                                     WHERE ha.user_id = :user_id
                                     ORDER BY ha.submitted_at DESC
                                     LIMIT 5";
                    $history_stmt = $db->prepare($history_query);
                    $history_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $history_stmt->execute();
                    $applications = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($applications)): ?>
                        <div class="mt-12 bg-white rounded-3xl shadow-lg border-2 border-gray-200">
                            <div class="p-8 border-b border-gray-200">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                                        <i data-lucide="history" class="w-6 h-6 text-blue-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-2xl font-bold text-gray-900">Application History</h3>
                                        <p class="text-gray-600">Track your previous honor applications</p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-8">
                                <div class="space-y-6">
                                    <?php foreach ($applications as $app): ?>
                                        <div class="bg-gradient-to-r from-gray-50 to-white rounded-2xl p-6 border border-gray-200 hover:shadow-md transition-all duration-200">
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <div class="flex items-center space-x-3 mb-3">
                                                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                            <i data-lucide="award" class="w-5 h-5 text-blue-600"></i>
                                                        </div>
                                                        <div>
                                                            <span class="font-bold text-lg text-gray-900">
                                                                <?php
                                                                $type_labels = [
                                                                    'deans_list' => "Dean's List",
                                                                    'cum_laude' => 'Cum Laude',
                                                                    'magna_cum_laude' => 'Magna Cum Laude',
                                                                    'summa_cum_laude' => 'Summa Cum Laude'
                                                                ];
                                                                echo $type_labels[$app['application_type']] ?? ucwords(str_replace('_', ' ', $app['application_type']));
                                                                ?>
                                                            </span>
                                                            <p class="text-sm text-gray-600">GWA: <?php echo formatGWA($app['gwa_achieved']); ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="text-sm text-gray-500 bg-gray-100 rounded-lg px-3 py-2">
                                                        <span class="font-medium">Submitted:</span> <?php echo date('M d, Y', strtotime($app['submitted_at'])); ?>
                                                        <?php if ($app['period_name']): ?>
                                                            <span class="mx-2">•</span>
                                                            <span class="font-medium">Period:</span> <?php echo htmlspecialchars($app['period_name']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="ml-6">
                                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold border-2
                                                        <?php
                                                        $status_colors = [
                                                            'submitted' => 'bg-blue-50 text-blue-800 border-blue-200',
                                                            'under_review' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
                                                            'approved' => 'bg-green-50 text-green-800 border-green-200',
                                                            'final_approved' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                                            'rejected' => 'bg-red-50 text-red-800 border-red-200'
                                                        ];
                                                        echo $status_colors[$app['status']] ?? 'bg-gray-50 text-gray-800 border-gray-200';
                                                        ?>">
                                                        <i data-lucide="<?php
                                                            $status_icons = [
                                                                'submitted' => 'clock',
                                                                'under_review' => 'eye',
                                                                'approved' => 'check',
                                                                'final_approved' => 'check-check',
                                                                'rejected' => 'x'
                                                            ];
                                                            echo $status_icons[$app['status']] ?? 'help-circle';
                                                        ?>" class="w-4 h-4 mr-2"></i>
                                                        <?php
                                                        $status_labels = [
                                                            'submitted' => 'Submitted',
                                                            'under_review' => 'Under Review',
                                                            'approved' => 'Approved',
                                                            'final_approved' => 'Final Approved',
                                                            'rejected' => 'Rejected'
                                                        ];
                                                        echo $status_labels[$app['status']] ?? ucfirst($app['status']);
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Honor Requirements Information -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-16 max-w-6xl mx-auto">
                        <div class="bg-gradient-to-br from-emerald-50 to-green-50 rounded-3xl p-8 border-2 border-emerald-200 shadow-lg">
                            <div class="flex items-center mb-6">
                                <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center mr-4">
                                    <i data-lucide="award" class="w-6 h-6 text-emerald-600"></i>
                                </div>
                                <div>
                                    <h4 class="text-xl font-bold text-emerald-900">Dean's List</h4>
                                    <p class="text-emerald-700">Available for all year levels</p>
                                </div>
                            </div>
                            <ul class="space-y-4 text-emerald-800">
                                <li class="flex items-start">
                                    <div class="w-6 h-6 bg-emerald-200 rounded-full flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                                        <i data-lucide="check" class="w-3 h-3 text-emerald-700"></i>
                                    </div>
                                    <span class="font-medium">General Weighted Average (GWA) of 1.75 or better</span>
                                </li>
                                <li class="flex items-start">
                                    <div class="w-6 h-6 bg-emerald-200 rounded-full flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                                        <i data-lucide="check" class="w-3 h-3 text-emerald-700"></i>
                                    </div>
                                    <span class="font-medium">No individual grade above 2.5</span>
                                </li>
                                <li class="flex items-start">
                                    <div class="w-6 h-6 bg-emerald-200 rounded-full flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                                        <i data-lucide="check" class="w-3 h-3 text-emerald-700"></i>
                                    </div>
                                    <span class="font-medium">Enrolled in at least 15 units for the semester</span>
                                </li>
                                <li class="flex items-start">
                                    <div class="w-6 h-6 bg-emerald-200 rounded-full flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                                        <i data-lucide="check" class="w-3 h-3 text-emerald-700"></i>
                                    </div>
                                    <span class="font-medium">No disciplinary sanctions</span>
                                </li>
                            </ul>
                        </div>

                        <div class="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-3xl p-8 border-2 border-purple-200 shadow-lg">
                            <div class="flex items-center mb-6">
                                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                                    <i data-lucide="crown" class="w-6 h-6 text-purple-600"></i>
                                </div>
                                <div>
                                    <h4 class="text-xl font-bold text-purple-900">Latin Honors</h4>
                                    <p class="text-purple-700">Available for 4th year students only</p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="bg-white bg-opacity-60 rounded-2xl p-4 border border-purple-200">
                                    <div class="flex items-center mb-2">
                                        <i data-lucide="star" class="w-5 h-5 text-purple-600 mr-2"></i>
                                        <span class="font-bold text-purple-900">Summa Cum Laude</span>
                                    </div>
                                    <p class="text-purple-700 font-medium">1.00 - 1.25 GWA</p>
                                </div>
                                <div class="bg-white bg-opacity-60 rounded-2xl p-4 border border-purple-200">
                                    <div class="flex items-center mb-2">
                                        <i data-lucide="star" class="w-5 h-5 text-purple-600 mr-2"></i>
                                        <span class="font-bold text-purple-900">Magna Cum Laude</span>
                                    </div>
                                    <p class="text-purple-700 font-medium">1.26 - 1.45 GWA</p>
                                </div>
                                <div class="bg-white bg-opacity-60 rounded-2xl p-4 border border-purple-200">
                                    <div class="flex items-center mb-2">
                                        <i data-lucide="star" class="w-5 h-5 text-purple-600 mr-2"></i>
                                        <span class="font-bold text-purple-900">Cum Laude</span>
                                    </div>
                                    <p class="text-purple-700 font-medium">1.46 - 1.75 GWA</p>
                                </div>
                                <div class="mt-4 p-3 bg-purple-100 rounded-xl">
                                    <p class="text-sm text-purple-800 font-medium flex items-center">
                                        <i data-lucide="info" class="w-4 h-4 mr-2"></i>
                                        Must be a 4th year student with no disciplinary sanctions
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>