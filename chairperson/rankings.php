<?php
require_once '../config/config.php';

requireLogin();

if (!hasRole('chairperson')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$chairperson_id = $_SESSION['user_id'];
$department = $_SESSION['department'];


// Get current academic period
$query = "SELECT * FROM academic_periods WHERE is_active = 1 LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$active_period = $stmt->fetch(PDO::FETCH_ASSOC);

// Auto-generate rankings if none exist for current period
if ($active_period) {
    try {
        // Check if rankings already exist for this period and department
        $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM honor_rankings WHERE academic_period_id = :period_id AND department = :department");
        $check_stmt->bindParam(':period_id', $active_period['id']);
        $check_stmt->bindParam(':department', $department);
        $check_stmt->execute();
        $existing_rankings = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // If no rankings exist, generate them automatically
        if ($existing_rankings == 0) {

            // Calculate rankings directly from grades data
            $period_semester = $active_period['semester'] . ' Semester SY ' . $active_period['school_year'];

            // Get all students with their GWA for this period
            $students_query = "
                SELECT
                    u.id as user_id,
                    u.year_level,
                    u.section,
                    SUM(g.grade * g.units) / SUM(g.units) as calculated_gwa,
                    SUM(g.units) as total_units,
                    COUNT(g.id) as subject_count,
                    MAX(CASE WHEN g.grade > 2.5 THEN 1 ELSE 0 END) as has_grade_above_25,
                    MAX(CASE WHEN g.grade >= 5.0 THEN 1 ELSE 0 END) as has_failed_subject,
                    (SELECT COUNT(DISTINCT CONCAT(ap.school_year, '-', ap.semester))
                     FROM grade_submissions gs2
                     JOIN academic_periods ap ON gs2.academic_period_id = ap.id
                     JOIN grades g2 ON gs2.id = g2.submission_id
                     WHERE gs2.user_id = u.id
                     AND gs2.status = 'processed'
                     AND g2.grade > 0) as completed_semesters,
                    (SELECT COUNT(*)
                     FROM grades g3
                     JOIN grade_submissions gs3 ON g3.submission_id = gs3.id
                     WHERE gs3.user_id = u.id
                     AND gs3.academic_period_id = :period_id
                     AND g3.grade = 0) as ongoing_grades
                FROM users u
                JOIN grade_submissions gs ON u.id = gs.user_id
                JOIN grades g ON gs.id = g.submission_id
                WHERE u.department = :department
                AND u.role = 'student'
                AND u.status = 'active'
                AND gs.academic_period_id = :period_id
                AND gs.status = 'processed'
                AND g.semester_taken = :semester
                AND g.grade > 0
                AND NOT (UPPER(g.subject_name) LIKE '%NSTP%' OR UPPER(g.subject_name) LIKE '%NATIONAL SERVICE TRAINING%')
                GROUP BY u.id, u.year_level, u.section
                HAVING total_units > 0
            ";

            $students_stmt = $db->prepare($students_query);
            $students_stmt->bindParam(':department', $department);
            $students_stmt->bindParam(':period_id', $active_period['id']);
            $students_stmt->bindParam(':semester', $period_semester);
            $students_stmt->execute();
            $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Debug: Check how many students were found
            $debug_students_count = count($students);

            // Debug: Check total students in department
            $debug_total_students_query = "SELECT COUNT(*) as count FROM users WHERE department = :department AND role = 'student' AND status = 'active'";
            $debug_total_stmt = $db->prepare($debug_total_students_query);
            $debug_total_stmt->bindParam(':department', $department);
            $debug_total_stmt->execute();
            $debug_total_students = $debug_total_stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Debug: Check total grade submissions for this period
            $debug_submissions_query = "SELECT COUNT(*) as count FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.department = :department AND gs.academic_period_id = :period_id AND gs.status = 'processed'";
            $debug_submissions_stmt = $db->prepare($debug_submissions_query);
            $debug_submissions_stmt->bindParam(':department', $department);
            $debug_submissions_stmt->bindParam(':period_id', $active_period['id']);
            $debug_submissions_stmt->execute();
            $debug_submissions = $debug_submissions_stmt->fetch(PDO::FETCH_ASSOC)['count'];

            $debug_message = "Dept: $department, Period: {$active_period['id']}, Total students: $debug_total_students, Processed submissions: $debug_submissions, Students with grades: $debug_students_count. ";

            // Calculate rankings for each eligible student
            foreach ($students as $student) {
                $gwa = floor($student['calculated_gwa'] * 100) / 100; // Truncate to 2 decimal places
                $ranking_types = [];

                // Determine eligibility for different honor lists
                if ($gwa <= 1.75 && $student['has_grade_above_25'] == 0 && $student['has_failed_subject'] == 0) {
                    $ranking_types[] = 'deans_list';
                }

                // Latin Honors (for graduating students - year level 4) - using 'presidents_list' as the database value
                // Requirements: Year 4, 8 completed semesters, no ongoing grades, no grades above 2.5, no failed subjects
                if ($student['year_level'] == 4 &&
                    $student['completed_semesters'] >= 8 &&
                    $student['ongoing_grades'] == 0 &&
                    $student['has_grade_above_25'] == 0 &&
                    $student['has_failed_subject'] == 0) {
                    if ($gwa >= 1.00 && $gwa <= 1.25) {
                        $ranking_types[] = 'presidents_list'; // Summa Cum Laude (stored as presidents_list)
                    } elseif ($gwa >= 1.26 && $gwa <= 1.45) {
                        $ranking_types[] = 'presidents_list'; // Magna Cum Laude (stored as presidents_list)
                    } elseif ($gwa >= 1.46 && $gwa <= 1.75) {
                        $ranking_types[] = 'presidents_list'; // Cum Laude (stored as presidents_list)
                    }
                }

                // Insert rankings for each type
                foreach ($ranking_types as $ranking_type) {
                    // Calculate rank position before inserting
                    $rank_calc_query = "
                        SELECT COUNT(*) + 1 as rank_position
                        FROM honor_rankings
                        WHERE academic_period_id = :period_id
                        AND department = :department
                        AND ranking_type = :ranking_type
                        AND year_level = :year_level
                        AND IFNULL(section, '') = IFNULL(:section, '')
                        AND gwa < :gwa
                    ";

                    $rank_calc_stmt = $db->prepare($rank_calc_query);
                    $rank_calc_stmt->bindParam(':period_id', $active_period['id']);
                    $rank_calc_stmt->bindParam(':department', $department);
                    $rank_calc_stmt->bindParam(':ranking_type', $ranking_type);
                    $rank_calc_stmt->bindParam(':year_level', $student['year_level']);
                    $rank_calc_stmt->bindParam(':section', $student['section']);
                    $rank_calc_stmt->bindParam(':gwa', $gwa);
                    $rank_calc_stmt->execute();
                    $calculated_rank = $rank_calc_stmt->fetch(PDO::FETCH_ASSOC)['rank_position'] ?? 1;

                    // Calculate total students in this group
                    $total_calc_query = "
                        SELECT COUNT(*) + 1 as total_students
                        FROM honor_rankings
                        WHERE academic_period_id = :period_id
                        AND department = :department
                        AND ranking_type = :ranking_type
                        AND year_level = :year_level
                        AND IFNULL(section, '') = IFNULL(:section, '')
                    ";

                    $total_calc_stmt = $db->prepare($total_calc_query);
                    $total_calc_stmt->bindParam(':period_id', $active_period['id']);
                    $total_calc_stmt->bindParam(':department', $department);
                    $total_calc_stmt->bindParam(':ranking_type', $ranking_type);
                    $total_calc_stmt->bindParam(':year_level', $student['year_level']);
                    $total_calc_stmt->bindParam(':section', $student['section']);
                    $total_calc_stmt->execute();
                    $calculated_total = $total_calc_stmt->fetch(PDO::FETCH_ASSOC)['total_students'] ?? 1;

                    $insert_ranking = "
                        INSERT INTO honor_rankings (
                            academic_period_id, department, year_level, section,
                            ranking_type, user_id, gwa, rank_position, total_students
                        ) VALUES (
                            :period_id, :department, :year_level, :section,
                            :ranking_type, :user_id, :gwa, :rank_position, :total_students
                        )
                    ";

                    $insert_stmt = $db->prepare($insert_ranking);
                    $insert_stmt->bindParam(':period_id', $active_period['id']);
                    $insert_stmt->bindParam(':department', $department);
                    $insert_stmt->bindParam(':year_level', $student['year_level']);
                    $insert_stmt->bindParam(':section', $student['section']);
                    $insert_stmt->bindParam(':ranking_type', $ranking_type);
                    $insert_stmt->bindParam(':user_id', $student['user_id']);
                    $insert_stmt->bindParam(':gwa', $gwa);
                    $insert_stmt->bindParam(':rank_position', $calculated_rank);
                    $insert_stmt->bindParam(':total_students', $calculated_total);
                    $insert_stmt->execute();
                }
            }

            // Rankings are now calculated during insertion, so no separate update needed
            
            // Update generated_by field
            $update_generated_by = "
                UPDATE honor_rankings 
                SET generated_by = :chairperson_id 
                WHERE academic_period_id = :period_id 
                AND department = :department 
                AND generated_by IS NULL";
            
            $stmt = $db->prepare($update_generated_by);
            $stmt->bindParam(':chairperson_id', $chairperson_id);
            $stmt->bindParam(':period_id', $active_period['id']);
            $stmt->bindParam(':department', $department);
            $stmt->execute();
        }
    } catch (Exception $e) {
        // Silent failure for auto-generation - just continue to display page
    }
}

// Get filters
$filter_type = $_GET['type'] ?? 'all';
$filter_year = $_GET['year'] ?? 'all';
$filter_section = $_GET['section'] ?? 'all';

// Get ranking data
$where_conditions = ["hr.department = :department"];
$params = [':department' => $department];

if ($active_period) {
    $where_conditions[] = "hr.academic_period_id = :period_id";
    $params[':period_id'] = $active_period['id'];
}

if ($filter_type !== 'all') {
    if ($filter_type === 'presidents_list') {
        // Latin Honor List filter - show only Year 4 students with presidents_list ranking
        $where_conditions[] = "hr.ranking_type = :ranking_type AND hr.year_level = 4";
        $params[':ranking_type'] = $filter_type;
    } else {
        // Regular filter for other ranking types
        $where_conditions[] = "hr.ranking_type = :ranking_type";
        $params[':ranking_type'] = $filter_type;
    }
}

if ($filter_year !== 'all') {
    $where_conditions[] = "hr.year_level = :year_level";
    $params[':year_level'] = $filter_year;
}

if ($filter_section !== 'all') {
    $where_conditions[] = "hr.section = :section";
    $params[':section'] = $filter_section;
}

$where_clause = implode(' AND ', $where_conditions);

// Try a simpler query first to test
$query = "
    SELECT
        hr.*,
        u.student_id,
        u.first_name,
        u.last_name,
        hr.year_level,
        hr.section,
        ap.period_name,
        ap.school_year,
        ap.semester
    FROM honor_rankings hr
    LEFT JOIN users u ON hr.user_id = u.id
    LEFT JOIN academic_periods ap ON hr.academic_period_id = ap.id
    WHERE {$where_clause}
    ORDER BY hr.ranking_type, hr.year_level, hr.section, hr.rank_position
";

$stmt = $db->prepare($query);
// Use bindValue instead of bindParam to avoid reference issues
foreach ($params as $param => $value) {
    $stmt->bindValue($param, $value);
}
$stmt->execute();
$rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Store the actual query that was executed
$debug_actual_query = $query;

// Debug: Execute the exact same query manually to test
$debug_manual_query = "
    SELECT
        hr.*,
        u.student_id,
        u.first_name,
        u.last_name,
        hr.year_level,
        hr.section,
        ap.period_name,
        ap.school_year,
        ap.semester
    FROM honor_rankings hr
    LEFT JOIN users u ON hr.user_id = u.id
    LEFT JOIN academic_periods ap ON hr.academic_period_id = ap.id
    WHERE hr.department = :department AND hr.academic_period_id = :period_id
    ORDER BY hr.ranking_type, hr.year_level, hr.section, hr.rank_position
";

$debug_manual_stmt = $db->prepare($debug_manual_query);
$debug_manual_stmt->bindParam(':department', $department);
$debug_manual_stmt->bindParam(':period_id', $active_period['id']);
$debug_manual_stmt->execute();
$debug_manual_results = $debug_manual_stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Check if we can find rankings in the display query
$debug_display_count = count($rankings);
$debug_direct_query = "SELECT COUNT(*) as count FROM honor_rankings WHERE department = :department AND academic_period_id = :period_id";
$debug_direct_stmt = $db->prepare($debug_direct_query);
$debug_direct_stmt->bindParam(':department', $department);
$debug_direct_stmt->bindParam(':period_id', $active_period['id']);
$debug_direct_stmt->execute();
$debug_direct_count = $debug_direct_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Debug: Check the actual rankings data and JOIN issue
$debug_rankings_query = "SELECT hr.*, u.id as user_exists FROM honor_rankings hr LEFT JOIN users u ON hr.user_id = u.id WHERE hr.department = :department AND hr.academic_period_id = :period_id";
$debug_rankings_stmt = $db->prepare($debug_rankings_query);
$debug_rankings_stmt->bindParam(':department', $department);
$debug_rankings_stmt->bindParam(':period_id', $active_period['id']);
$debug_rankings_stmt->execute();
$debug_rankings_data = $debug_rankings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Test the full JOIN with academic_periods
$debug_full_join_query = "
    SELECT hr.*, u.first_name, u.last_name, ap.period_name
    FROM honor_rankings hr
    LEFT JOIN users u ON hr.user_id = u.id
    LEFT JOIN academic_periods ap ON hr.academic_period_id = ap.id
    WHERE hr.department = :department AND hr.academic_period_id = :period_id
";
$debug_full_join_stmt = $db->prepare($debug_full_join_query);
$debug_full_join_stmt->bindParam(':department', $department);
$debug_full_join_stmt->bindParam(':period_id', $active_period['id']);
$debug_full_join_stmt->execute();
$debug_full_join_data = $debug_full_join_stmt->fetchAll(PDO::FETCH_ASSOC);

// Store debug info for display
$display_debug_info = [
    'display_query_results' => $debug_display_count,
    'direct_count_query' => $debug_direct_count,
    'where_clause' => $where_clause,
    'params' => $params,
    'rankings_data' => $debug_rankings_data,
    'full_join_data' => $debug_full_join_data,
    'actual_query' => $debug_actual_query,
    'manual_query_results' => count($debug_manual_results),
    'manual_data' => $debug_manual_results,
    'filter_type' => $filter_type,
    'filter_year' => $filter_year,
    'filter_section' => $filter_section
];

// Get available year levels and sections for filters
$year_query = "SELECT DISTINCT year_level FROM users WHERE department = :department AND role = 'student' ORDER BY year_level";
$year_stmt = $db->prepare($year_query);
$year_stmt->bindParam(':department', $department);
$year_stmt->execute();
$available_years = $year_stmt->fetchAll(PDO::FETCH_ASSOC);

$section_query = "SELECT DISTINCT section FROM users WHERE department = :department AND role = 'student' AND section IS NOT NULL ORDER BY section";
$section_stmt = $db->prepare($section_query);
$section_stmt->bindParam(':department', $department);
$section_stmt->execute();
$available_sections = $section_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ranking statistics
$stats_query = "
    SELECT 
        ranking_type,
        COUNT(*) as total_students,
        MIN(gwa) as best_gwa,
        MAX(gwa) as lowest_gwa,
        AVG(gwa) as average_gwa
    FROM honor_rankings hr
    WHERE hr.department = :department
    AND hr.academic_period_id = :period_id
    GROUP BY ranking_type
";

$stats_params = [':department' => $department];
if ($active_period) {
    $stats_params[':period_id'] = $active_period['id'];
    $stats_stmt = $db->prepare($stats_query);
    foreach ($stats_params as $param => $value) {
        $stats_stmt->bindParam($param, $value);
    }
    $stats_stmt->execute();
    $ranking_stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $ranking_stats = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Honor Rankings - CTU Honor System</title>
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
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="hidden md:flex md:w-64 md:flex-col">
            <div class="flex flex-col flex-grow bg-white border-r border-gray-200 pt-5 pb-4 overflow-y-auto">
                <div class="flex items-center flex-shrink-0 px-4">
                    <div class="w-8 h-8 bg-purple-600 rounded-xl flex items-center justify-center">
                        <i data-lucide="graduation-cap" class="w-5 h-5 text-white"></i>
                    </div>
                    <span class="ml-2 text-xl font-bold text-gray-900">CTU Honor</span>
                </div>
                
                <!-- User Profile -->
                <div class="mt-8 px-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                            <i data-lucide="crown" class="w-5 h-5 text-purple-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-semibold text-gray-900"><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></p>
                            <p class="text-xs text-gray-500">Chairperson</p>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="mt-8 flex-1 px-2 space-y-1">
                    <a href="dashboard.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="layout-dashboard" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Dashboard
                    </a>
                    <a href="applications.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="trophy" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Honor Applications
                    </a>
                    <a href="rankings.php" class="bg-purple-50 border-r-2 border-purple-600 text-purple-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="trophy" class="text-purple-500 mr-3 h-5 w-5"></i>
                        Rankings
                    </a>
                    <a href="advisers.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="user-check" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Advisers
                    </a>
                    <a href="reports.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="file-text" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Reports
                    </a>
                    <a href="settings.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="settings" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Settings
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

        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex items-center">
                        <button type="button" class="md:hidden -ml-0.5 -mt-0.5 h-12 w-12 inline-flex items-center justify-center rounded-xl text-gray-500 hover:text-gray-900">
                            <i data-lucide="menu" class="h-6 w-6"></i>
                        </button>
                        <div class="ml-4 md:ml-0">
                            <h1 class="text-2xl font-bold text-gray-900">Honor Rankings</h1>
                            <p class="text-sm text-gray-500">
                                <?php if ($active_period): ?>
                                    <?php echo htmlspecialchars($active_period['period_name']); ?> - <?php echo htmlspecialchars($department); ?> Department
                                <?php else: ?>
                                    No active academic period
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-4 sm:p-6 lg:p-8">

                    <?php if (!$active_period): ?>
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-r-lg mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i data-lucide="alert-triangle" class="h-5 w-5 text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700">
                                        <strong>No Active Academic Period:</strong> Rankings cannot be generated without an active academic period.
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <?php if (!empty($ranking_stats)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                            <?php foreach ($ranking_stats as $stat): ?>
                                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="w-8 h-8 bg-purple-100 rounded-xl flex items-center justify-center">
                                                <i data-lucide="trophy" class="w-4 h-4 text-purple-600"></i>
                                            </div>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">
                                                    <?php echo ucfirst(str_replace('_', ' ', $stat['ranking_type'])); ?>
                                                </dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900">
                                                        <?php echo $stat['total_students']; ?>
                                                    </div>
                                                    <div class="ml-2 text-sm text-gray-500">
                                                        students
                                                    </div>
                                                </dd>
                                                <dd class="text-xs text-gray-500 mt-1">
                                                    Best GWA: <?php echo number_format(floor($stat['best_gwa'] * 100) / 100, 2); ?>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Filters -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 mb-6">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter Rankings</h3>
                            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Ranking Type</label>
                                    <select name="type" id="type" class="block w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                        <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                                        <option value="deans_list" <?php echo $filter_type === 'deans_list' ? 'selected' : ''; ?>>Dean's List</option>
                                        <option value="presidents_list" <?php echo $filter_type === 'presidents_list' ? 'selected' : ''; ?>>Latin Honor List</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                                    <select name="year" id="year" class="block w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                        <option value="all" <?php echo $filter_year === 'all' ? 'selected' : ''; ?>>All Years</option>
                                        <?php foreach ($available_years as $year): ?>
                                            <option value="<?php echo $year['year_level']; ?>" <?php echo $filter_year == $year['year_level'] ? 'selected' : ''; ?>>
                                                Year <?php echo $year['year_level']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="section" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                                    <select name="section" id="section" class="block w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                        <option value="all" <?php echo $filter_section === 'all' ? 'selected' : ''; ?>>All Sections</option>
                                        <?php foreach ($available_sections as $section): ?>
                                            <option value="<?php echo $section['section']; ?>" <?php echo $filter_section === $section['section'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($section['section']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Rankings Table -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">
                                Honor Rankings 
                                <span class="text-sm font-normal text-gray-500">(<?php echo count($rankings); ?> students)</span>
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <?php if (empty($rankings)): ?>
                                <div class="text-center py-12">
                                    <i data-lucide="trophy" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                                    <h4 class="text-xl font-medium text-gray-900 mb-2">No Rankings Found</h4>
                                    <p class="text-gray-500">
                                        <?php if (!$active_period): ?>
                                            Set an active academic period to see rankings.
                                        <?php else: ?>
                                            Rankings are automatically generated from student grades.
                                        <?php endif; ?>
                                    </p>

                                </div>
                            <?php else: ?>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year & Section</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Honor Type</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GWA</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentile</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php 
                                        $current_type = '';
                                        $current_section = '';
                                        foreach ($rankings as $ranking): 
                                            $section_key = $ranking['year_level'] . '-' . $ranking['section'];
                                            $type_section_key = $ranking['ranking_type'] . '-' . $section_key;
                                            
                                            // Add section header if needed
                                            if ($current_type . $current_section !== $type_section_key):
                                                $current_type = $ranking['ranking_type'];
                                                $current_section = $section_key;
                                        ?>
                                                <tr class="bg-purple-50">
                                                    <td colspan="6" class="px-6 py-3 text-sm font-semibold text-purple-800">
                                                        <?php echo ucfirst(str_replace('_', ' ', $ranking['ranking_type'])); ?> - 
                                                        Year <?php echo $ranking['year_level']; ?> Section <?php echo htmlspecialchars($ranking['section']); ?>
                                                    </td>
                                                </tr>
                                        <?php endif; ?>
                                        
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <?php if ($ranking['rank_position'] <= 3): ?>
                                                        <div class="w-8 h-8 <?php echo $ranking['rank_position'] == 1 ? 'bg-yellow-100 text-yellow-800' : ($ranking['rank_position'] == 2 ? 'bg-gray-100 text-gray-800' : 'bg-orange-100 text-orange-800'); ?> rounded-full flex items-center justify-center text-sm font-bold">
                                                            <?php echo $ranking['rank_position']; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-sm font-medium text-gray-900"><?php echo $ranking['rank_position']; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($ranking['first_name'] . ' ' . $ranking['last_name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($ranking['student_id']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                Year <?php echo $ranking['year_level']; ?> - <?php echo htmlspecialchars($ranking['section']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php echo $ranking['ranking_type'] === 'deans_list' ? 'bg-purple-100 text-purple-800' : (($ranking['ranking_type'] === 'presidents_list' && $ranking['year_level'] == 4) ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                                                    <?php
                                                    if ($ranking['ranking_type'] === 'presidents_list' && $ranking['year_level'] == 4) {
                                                        // This is actually a Latin Honor (stored as presidents_list)
                                                        $gwa = $ranking['gwa'];
                                                        if ($gwa >= 1.00 && $gwa <= 1.25) {
                                                            echo 'Summa Cum Laude';
                                                        } elseif ($gwa >= 1.26 && $gwa <= 1.45) {
                                                            echo 'Magna Cum Laude';
                                                        } elseif ($gwa >= 1.46 && $gwa <= 1.75) {
                                                            echo 'Cum Laude';
                                                        } else {
                                                            echo 'Latin Honors';
                                                        }
                                                    } elseif ($ranking['ranking_type'] === 'deans_list') {
                                                        echo "Dean's List";
                                                    } else {
                                                        echo ucfirst(str_replace('_', ' ', $ranking['ranking_type']));
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo number_format(floor($ranking['gwa'] * 100) / 100, 2); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo $ranking['percentile'] ? number_format($ranking['percentile'], 1) . '%' : 'N/A'; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Auto-submit filter form when dropdown values change
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.querySelector('form[method="GET"]');
            if (filterForm) {
                const selectElements = filterForm.querySelectorAll('select');

                selectElements.forEach(select => {
                    select.addEventListener('change', function() {
                        filterForm.submit();
                    });
                });
            }
        });
    </script>
</body>
</html>