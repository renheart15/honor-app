<?php
require_once '../config/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and has chairperson role
if (!isLoggedIn() || !hasRole('chairperson')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Check if student_id is provided
if (!isset($_POST['student_id']) || empty($_POST['student_id'])) {
    echo json_encode(['success' => false, 'error' => 'Student ID is required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$student_id = $_POST['student_id'];
$department = $_SESSION['department'];

try {
    // Verify student belongs to chairperson's department
    $verify_query = "SELECT * FROM users WHERE id = :student_id AND department = :department AND role = 'student'";
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->bindParam(':student_id', $student_id);
    $verify_stmt->bindParam(':department', $department);
    $verify_stmt->execute();

    if ($verify_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Student not found or access denied']);
        exit;
    }

    // Get current active academic period
    $active_period_query = "SELECT * FROM academic_periods WHERE is_active = 1 LIMIT 1";
    $active_period_stmt = $db->prepare($active_period_query);
    $active_period_stmt->execute();
    $active_period = $active_period_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$active_period) {
        echo json_encode(['success' => false, 'error' => 'No active academic period found']);
        exit;
    }

    // Create semester string
    $semester_string = $active_period['semester'] . ' Semester SY ' . $active_period['school_year'];

    // First, let's get all available semester strings for this student to debug
    $debug_query = "
        SELECT DISTINCT g.semester_taken
        FROM grades g
        JOIN grade_submissions gs ON g.submission_id = gs.id
        WHERE gs.user_id = :student_id
        ORDER BY g.semester_taken DESC
    ";

    $debug_stmt = $db->prepare($debug_query);
    $debug_stmt->bindParam(':student_id', $student_id);
    $debug_stmt->execute();
    $available_semesters = $debug_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get student grades for the current period
    $grades_query = "
        SELECT g.*, gs.submitted_at
        FROM grades g
        JOIN grade_submissions gs ON g.submission_id = gs.id
        WHERE gs.user_id = :student_id
        AND g.semester_taken = :semester_taken
        ORDER BY g.subject_name ASC
    ";

    $grades_stmt = $db->prepare($grades_query);
    $grades_stmt->bindParam(':student_id', $student_id);
    $grades_stmt->bindParam(':semester_taken', $semester_string);
    $grades_stmt->execute();
    $grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no grades found with exact match, try to find the closest match
    if (empty($grades) && !empty($available_semesters)) {
        // Let's try with just the most recent semester for this student
        $recent_semester = $available_semesters[0];

        $grades_stmt = $db->prepare("
            SELECT g.*, gs.submitted_at
            FROM grades g
            JOIN grade_submissions gs ON g.submission_id = gs.id
            WHERE gs.user_id = :student_id
            AND g.semester_taken = :semester_taken
            ORDER BY g.subject_name ASC
        ");
        $grades_stmt->bindParam(':student_id', $student_id);
        $grades_stmt->bindParam(':semester_taken', $recent_semester);
        $grades_stmt->execute();
        $grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Update semester string to what we actually found
        if (!empty($grades)) {
            $semester_string = $recent_semester . ' (Most Recent Available)';
        }
    }

    // Calculate GWA for this period
    $total_grade_points = 0;
    $total_units = 0;
    $valid_grades = [];

    foreach ($grades as $grade) {
        // Skip ongoing subjects (grade = 0.00)
        if ($grade['grade'] == 0.00) {
            continue;
        }

        // Skip NSTP courses
        $subject_name = strtoupper($grade['subject_name'] ?? '');
        if (strpos($subject_name, 'NSTP') !== false ||
            strpos($subject_name, 'NATIONAL SERVICE TRAINING') !== false) {
            continue;
        }

        $valid_grades[] = $grade;
        $total_units += floatval($grade['units']);
        $total_grade_points += (floatval($grade['grade']) * floatval($grade['units']));
    }

    $gwa = $total_units > 0 ? floor(($total_grade_points / $total_units) * 100) / 100 : 0;

    // Format the response
    $response = [
        'success' => true,
        'period' => $semester_string,
        'gwa' => number_format($gwa, 2),
        'total_units' => $total_units,
        'subjects_count' => count($valid_grades),
        'grades' => $grades,
        'debug_info' => [
            'active_period_semester' => $active_period['semester'] . ' Semester SY ' . $active_period['school_year'],
            'available_semesters' => $available_semesters,
            'total_grades_found' => count($grades),
            'student_id' => $student_id
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error fetching student grades: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>