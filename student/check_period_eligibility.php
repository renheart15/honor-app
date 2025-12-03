<?php
require_once '../config/config.php';

header('Content-Type: application/json');

requireLogin();

if (!hasRole('student')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$period_id = $_GET['period_id'] ?? null;

if (!$period_id) {
    echo json_encode(['success' => false, 'message' => 'Period ID is required']);
    exit;
}

// Get the academic period
$period_query = "SELECT * FROM academic_periods WHERE id = :period_id AND is_active = 1";
$period_stmt = $db->prepare($period_query);
$period_stmt->bindParam(':period_id', $period_id);
$period_stmt->execute();
$period = $period_stmt->fetch(PDO::FETCH_ASSOC);

if (!$period) {
    echo json_encode(['success' => false, 'message' => 'Invalid or inactive period']);
    exit;
}

// Get student's year level
$user_query = "SELECT year_level FROM users WHERE id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $user_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
$year_level = $user['year_level'] ?? 1;

// Check if student has ANY grades for this period (don't require exact semester string match)
// This allows for cases where the PDF semester doesn't exactly match the period name
// Include ALL grades including zero grades (ongoing courses)

// Build the expected semester string for this period
$expected_semester_string = $period['semester'] . ' Semester SY ' . $period['school_year'];

// First, check for grades linked to submissions for this specific period (processed status)
$grades_query = "SELECT g.*, g.semester_taken
                 FROM grades g
                 JOIN grade_submissions gs ON g.submission_id = gs.id
                 WHERE gs.user_id = :user_id
                 AND gs.academic_period_id = :period_id
                 AND gs.status = 'processed'"; // Include all grades, even zero/ongoing ones

$grades_stmt = $db->prepare($grades_query);
$grades_stmt->bindParam(':user_id', $user_id);
$grades_stmt->bindParam(':period_id', $period_id);
$grades_stmt->execute();
$period_grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

// If no grades found for this period, check for grades with the correct semester string
// (this handles cases where grades were extracted but linked to wrong period)
if (empty($period_grades)) {
    $semester_grades_query = "SELECT g.*, g.semester_taken
                             FROM grades g
                             JOIN grade_submissions gs ON g.submission_id = gs.id
                             WHERE gs.user_id = :user_id
                             AND g.semester_taken = :expected_semester
                             AND gs.status = 'processed'";

    $semester_grades_stmt = $db->prepare($semester_grades_query);
    $semester_grades_stmt->bindParam(':user_id', $user_id);
    $semester_grades_stmt->bindParam(':expected_semester', $expected_semester_string);
    $semester_grades_stmt->execute();
    $period_grades = $semester_grades_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$grades = $period_grades;

// Debug output
error_log("Period grades count: " . count($period_grades));
error_log("Expected semester: " . $expected_semester_string);

$has_grades = !empty($grades);

if (!$has_grades) {
    echo json_encode([
        'success' => true,
        'has_grades' => false,
        'message' => 'No grades found for this period'
    ]);
    exit;
}

// Calculate GWA from grades
$total_grade_points = 0;
$total_units = 0;
$valid_grades_count = 0;
$has_grade_above_25 = false;

foreach ($grades as $grade) {
    // Skip NSTP subjects and ongoing subjects (grade = 0) from GWA calculation
    if (stripos($grade['subject_name'], 'NSTP') !== false ||
        stripos($grade['subject_name'], 'NATIONAL SERVICE TRAINING') !== false ||
        $grade['grade'] == 0) {
        continue;
    }

    if ($grade['grade'] > 2.5) {
        $has_grade_above_25 = true;
    }

    $total_grade_points += ($grade['grade'] * $grade['units']);
    $total_units += $grade['units'];
    $valid_grades_count++;
}

$gwa = 0;
if ($total_units > 0) {
    $gwa_exact = $total_grade_points / $total_units;
    $gwa = floor($gwa_exact * 100) / 100; // Truncate to 2 decimal places
}

// Count total semesters completed by student
$semester_count_query = "SELECT COUNT(DISTINCT g.semester_taken) as semester_count
                         FROM grades g
                         JOIN grade_submissions gs ON g.submission_id = gs.id
                         WHERE gs.user_id = :user_id
                         AND gs.status = 'processed'";
$semester_count_stmt = $db->prepare($semester_count_query);
$semester_count_stmt->bindParam(':user_id', $user_id);
$semester_count_stmt->execute();
$semester_result = $semester_count_stmt->fetch(PDO::FETCH_ASSOC);
$total_semesters = $semester_result['semester_count'] ?? 0;

// Check eligibility for each honor type
$eligible_for_deans = ($gwa <= 1.75 && !$has_grade_above_25);
$eligible_for_summa = ($gwa >= 1.00 && $gwa <= 1.25 && !$has_grade_above_25);
$eligible_for_magna = ($gwa > 1.25 && $gwa <= 1.45 && !$has_grade_above_25);
$eligible_for_cum_laude = ($gwa > 1.45 && $gwa <= 1.75 && !$has_grade_above_25);

// Check if student can apply for Latin Honors
$can_apply_latin_honors = false;
$latin_honors_message = '';

if ($year_level < 4) {
    $latin_honors_message = "Latin honors are only available for graduating students (Year 4).";
} elseif ($total_semesters < 8) {
    $latin_honors_message = "Requirements not met:<br>• Need at least 8 semesters completed (currently: {$total_semesters})<br>Latin honors are only available for graduating students with all grades completed.";
    $can_apply_latin_honors = false;
} else {
    $can_apply_latin_honors = true;
}

echo json_encode([
    'success' => true,
    'has_grades' => true,
    'gwa' => $gwa,
    'total_semesters' => $total_semesters,
    'year_level' => $year_level,
    'eligible_for_deans' => $eligible_for_deans,
    'eligible_for_summa' => $eligible_for_summa,
    'eligible_for_magna' => $eligible_for_magna,
    'eligible_for_cum_laude' => $eligible_for_cum_laude,
    'can_apply_latin_honors' => $can_apply_latin_honors,
    'latin_honors_message' => $latin_honors_message,
    'has_grade_above_25' => $has_grade_above_25
]);
