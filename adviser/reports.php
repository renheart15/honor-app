<?php
require_once '../config/config.php';

requireLogin();

if (!hasRole('adviser')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$adviser_id = $_SESSION['user_id'];
$department = $_SESSION['department'];

// Get filter parameters
$year_filter = $_GET['year'] ?? '1'; // Default to first year
$section_filter = $_GET['section'] ?? 'all';
$ranking_type_filter = $_GET['ranking_type'] ?? 'deans_list'; // Default to Dean's List
$period_filter = $_GET['period'] ?? 'all';

// Get the academic period to use for GWA calculations
$academic_period_id = null;
$semester_string = null;
if ($period_filter !== 'all') {
    $academic_period_id = $period_filter;
    // Get period details
    $period_query = "SELECT * FROM academic_periods WHERE id = :period_id";
    $period_stmt = $db->prepare($period_query);
    $period_stmt->bindParam(':period_id', $academic_period_id);
    $period_stmt->execute();
    $selected_period = $period_stmt->fetch(PDO::FETCH_ASSOC);
    if ($selected_period) {
        $semester_string = $selected_period['semester'] . ' Semester SY ' . $selected_period['school_year'];
    }
} else {
    // Get active period
    $active_query = "SELECT * FROM academic_periods WHERE is_active = 1 LIMIT 1";
    $active_stmt = $db->prepare($active_query);
    $active_stmt->execute();
    $active_period_data = $active_stmt->fetch(PDO::FETCH_ASSOC);
    if ($active_period_data) {
        $academic_period_id = $active_period_data['id'];
        $semester_string = $active_period_data['semester'] . ' Semester SY ' . $active_period_data['school_year'];
    }
}

// Build WHERE clause for filters
$filter_conditions = ["u.department = :department", "u.status = 'active'"];
$filter_params = [':department' => $department];

// Always filter by year level (no 'all' option)
$filter_conditions[] = "u.year_level = :year_level";
$filter_params[':year_level'] = $year_filter;

if ($section_filter !== 'all') {
    $filter_conditions[] = "u.section = :section";
    $filter_params[':section'] = $section_filter;
}

$student_filter = implode(' AND ', $filter_conditions);

// Get comprehensive department statistics
$stats = [];

// Total students (filtered by honor eligibility based on ranking type and academic period)
// Calculate GWA directly from grades table like rankings.php does
if ($semester_string) {
    $query = "SELECT COUNT(DISTINCT u.id) as count
              FROM users u
              JOIN grade_submissions gs ON gs.user_id = u.id
              JOIN grades g ON g.submission_id = gs.id
              WHERE " . $student_filter . "
                AND u.role = 'student'
                AND g.semester_taken = :semester_string
                AND g.grade > 0.00
                AND NOT (UPPER(g.subject_name) LIKE '%NSTP%' OR UPPER(g.subject_name) LIKE '%NATIONAL SERVICE TRAINING%')
              GROUP BY u.id
              HAVING (SUM(g.units * g.grade) / SUM(g.units)) <= 1.75";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':semester_string', $semester_string);
    foreach ($filter_params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $stats['total_students'] = $stmt->rowCount();
} else {
    $stats['total_students'] = 0;
}

// Total advisers
$query = "SELECT COUNT(*) as count FROM users WHERE role = 'adviser' AND department = :department AND status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$stats['total_advisers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Honor applications statistics
$app_conditions = $filter_conditions;
// Always filter by ranking type (no 'all' option)
if ($ranking_type_filter == 'deans_list') {
    $app_conditions[] = "ha.application_type IN ('deans_list')";
} elseif ($ranking_type_filter == 'latin_honors') {
    $app_conditions[] = "ha.application_type IN ('cum_laude', 'magna_cum_laude', 'summa_cum_laude')";
}
$app_filter = implode(' AND ', $app_conditions);

$query = "SELECT
            COUNT(*) as total_applications,
            SUM(CASE WHEN ha.status = 'final_approved' THEN 1 ELSE 0 END) as approved_applications,
            SUM(CASE WHEN ha.status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications,
            SUM(CASE WHEN ha.status IN ('submitted', 'approved') THEN 1 ELSE 0 END) as pending_applications
          FROM honor_applications ha
          JOIN users u ON ha.user_id = u.id
          WHERE " . $app_filter;
$stmt = $db->prepare($query);
foreach ($filter_params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$app_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$stats = array_merge($stats, $app_stats);

// Calculate Total Disapproved and Total Approved based on Honor Eligible Students
// Total Disapproved = Total Applications - Honor Eligible Students
$stats['disapproved_applications'] = max(0, ($stats['total_applications'] ?? 0) - ($stats['total_students'] ?? 0));
// Total Approved = Total Applications - Total Disapproved
$stats['approved_applications'] = max(0, ($stats['total_applications'] ?? 0) - ($stats['disapproved_applications'] ?? 0));

// GWA distribution (filtered by ranking type and academic period)
// Calculate GWA directly from grades table like rankings.php does
if ($semester_string) {
    if ($ranking_type_filter == 'deans_list') {
        // For Dean's List, show only students with GWA <= 1.75
        $query = "SELECT
                    CASE
                        WHEN calculated_gwa <= 1.00 THEN 'Outstanding (1.00)'
                        WHEN calculated_gwa <= 1.25 THEN 'Excellent (1.25)'
                        WHEN calculated_gwa <= 1.50 THEN 'Very Good (1.50)'
                        WHEN calculated_gwa <= 1.75 THEN 'Good (1.75)'
                    END as grade_category,
                    COUNT(DISTINCT user_id) as student_count,
                    MIN(calculated_gwa) as min_gwa,
                    MAX(calculated_gwa) as max_gwa,
                    AVG(calculated_gwa) as avg_gwa
                  FROM (
                    SELECT u.id as user_id,
                           SUM(g.units * g.grade) / SUM(g.units) as calculated_gwa
                    FROM users u
                    JOIN grade_submissions gs ON gs.user_id = u.id
                    JOIN grades g ON g.submission_id = gs.id
                    WHERE " . $student_filter . "
                      AND u.role = 'student'
                      AND g.semester_taken = :semester_string
                      AND g.grade > 0.00
                      AND NOT (UPPER(g.subject_name) LIKE '%NSTP%' OR UPPER(g.subject_name) LIKE '%NATIONAL SERVICE TRAINING%')
                    GROUP BY u.id
                    HAVING calculated_gwa <= 1.75
                  ) as gwa_data
                  GROUP BY grade_category
                  ORDER BY MIN(calculated_gwa)";
    } else {
        // For Latin Honors, show only students with GWA <= 1.75
        $query = "SELECT
                    CASE
                        WHEN calculated_gwa <= 1.00 THEN 'Summa Cum Laude'
                        WHEN calculated_gwa <= 1.45 THEN 'Magna Cum Laude'
                        WHEN calculated_gwa <= 1.75 THEN 'Cum Laude'
                    END as grade_category,
                    COUNT(DISTINCT user_id) as student_count,
                    MIN(calculated_gwa) as min_gwa,
                    MAX(calculated_gwa) as max_gwa,
                    AVG(calculated_gwa) as avg_gwa
                  FROM (
                    SELECT u.id as user_id,
                           SUM(g.units * g.grade) / SUM(g.units) as calculated_gwa
                    FROM users u
                    JOIN grade_submissions gs ON gs.user_id = u.id
                    JOIN grades g ON g.submission_id = gs.id
                    WHERE " . $student_filter . "
                      AND u.role = 'student'
                      AND g.semester_taken = :semester_string
                      AND g.grade > 0.00
                      AND NOT (UPPER(g.subject_name) LIKE '%NSTP%' OR UPPER(g.subject_name) LIKE '%NATIONAL SERVICE TRAINING%')
                    GROUP BY u.id
                    HAVING calculated_gwa <= 1.75
                  ) as gwa_data
                  GROUP BY grade_category
                  ORDER BY MIN(calculated_gwa)";
    }
    $stmt = $db->prepare($query);
    $stmt->bindValue(':semester_string', $semester_string);
    foreach ($filter_params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $gwa_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $gwa_distribution = [];
}

// Year level performance (filtered by ranking type and academic period)
// Calculate GWA directly from grades table like rankings.php does
if ($semester_string) {
    $query = "SELECT year_level,
                     COUNT(DISTINCT user_id) as student_count,
                     AVG(calculated_gwa) as avg_gwa,
                     MIN(calculated_gwa) as best_gwa,
                     COUNT(DISTINCT CASE WHEN calculated_gwa <= 1.75 THEN user_id END) as honor_eligible
              FROM (
                SELECT u.id as user_id,
                       u.year_level,
                       SUM(g.units * g.grade) / SUM(g.units) as calculated_gwa
                FROM users u
                JOIN grade_submissions gs ON gs.user_id = u.id
                JOIN grades g ON g.submission_id = gs.id
                WHERE " . $student_filter . "
                  AND u.role = 'student'
                  AND g.semester_taken = :semester_string
                  AND g.grade > 0.00
                  AND NOT (UPPER(g.subject_name) LIKE '%NSTP%' OR UPPER(g.subject_name) LIKE '%NATIONAL SERVICE TRAINING%')
                GROUP BY u.id, u.year_level
              ) as gwa_data
              GROUP BY year_level
              ORDER BY year_level";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':semester_string', $semester_string);
    foreach ($filter_params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $year_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $year_performance = [];
}

// Monthly application trends (last 12 months) - filtered by ranking type
if ($ranking_type_filter == 'deans_list') {
    $query = "SELECT
                DATE_FORMAT(ha.submitted_at, '%Y-%m') as month,
                COUNT(*) as application_count,
                COUNT(CASE WHEN ha.status = 'final_approved' THEN 1 END) as approved_count
              FROM honor_applications ha
              JOIN users u ON ha.user_id = u.id
              WHERE u.department = :department
                AND ha.application_type = 'deans_list'
                AND ha.submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
              GROUP BY DATE_FORMAT(ha.submitted_at, '%Y-%m')
              ORDER BY month";
} else {
    $query = "SELECT
                DATE_FORMAT(ha.submitted_at, '%Y-%m') as month,
                COUNT(*) as application_count,
                COUNT(CASE WHEN ha.status = 'final_approved' THEN 1 END) as approved_count
              FROM honor_applications ha
              JOIN users u ON ha.user_id = u.id
              WHERE u.department = :department
                AND ha.application_type IN ('cum_laude', 'magna_cum_laude', 'summa_cum_laude')
                AND ha.submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
              GROUP BY DATE_FORMAT(ha.submitted_at, '%Y-%m')
              ORDER BY month";
}
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top performing students (filtered by ranking type and academic period)
// Calculate GWA directly from grades table like rankings.php does
if ($semester_string) {
    if ($ranking_type_filter == 'deans_list') {
        $query = "SELECT u.first_name, u.last_name, u.student_id, u.section, u.year_level,
                         SUM(g.units * g.grade) / SUM(g.units) as gwa,
                         COUNT(DISTINCT ha.id) as application_count,
                         COUNT(DISTINCT CASE WHEN ha.status = 'final_approved' THEN ha.id END) as approved_count
                  FROM users u
                  JOIN grade_submissions gs ON gs.user_id = u.id
                  JOIN grades g ON g.submission_id = gs.id
                  LEFT JOIN honor_applications ha ON u.id = ha.user_id
                    AND ha.application_type = 'deans_list'
                    AND ha.academic_period_id = :period_id_ha
                  WHERE " . $student_filter . "
                    AND u.role = 'student'
                    AND g.semester_taken = :semester_string
                    AND g.grade > 0.00
                    AND NOT (UPPER(g.subject_name) LIKE '%NSTP%' OR UPPER(g.subject_name) LIKE '%NATIONAL SERVICE TRAINING%')
                  GROUP BY u.id, u.first_name, u.last_name, u.student_id, u.section, u.year_level
                  HAVING gwa IS NOT NULL AND gwa <= 1.75
                  ORDER BY gwa ASC
                  LIMIT 10";
    } else {
        $query = "SELECT u.first_name, u.last_name, u.student_id, u.section, u.year_level,
                         SUM(g.units * g.grade) / SUM(g.units) as gwa,
                         COUNT(DISTINCT ha.id) as application_count,
                         COUNT(DISTINCT CASE WHEN ha.status = 'final_approved' THEN ha.id END) as approved_count
                  FROM users u
                  JOIN grade_submissions gs ON gs.user_id = u.id
                  JOIN grades g ON g.submission_id = gs.id
                  LEFT JOIN honor_applications ha ON u.id = ha.user_id
                    AND ha.application_type IN ('cum_laude', 'magna_cum_laude', 'summa_cum_laude')
                    AND ha.academic_period_id = :period_id_ha
                  WHERE " . $student_filter . "
                    AND u.role = 'student'
                    AND g.semester_taken = :semester_string
                    AND g.grade > 0.00
                    AND NOT (UPPER(g.subject_name) LIKE '%NSTP%' OR UPPER(g.subject_name) LIKE '%NATIONAL SERVICE TRAINING%')
                  GROUP BY u.id, u.first_name, u.last_name, u.student_id, u.section, u.year_level
                  HAVING gwa IS NOT NULL AND gwa <= 1.75
                  ORDER BY gwa ASC
                  LIMIT 10";
    }
    $stmt = $db->prepare($query);
    $stmt->bindValue(':semester_string', $semester_string);
    if ($academic_period_id) {
        $stmt->bindValue(':period_id_ha', $academic_period_id);
    }
    foreach ($filter_params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $top_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Truncate GWA to 2 decimal places like rankings.php does
    foreach ($top_students as &$student) {
        $student['gwa'] = floor($student['gwa'] * 100) / 100;
    }
    unset($student);
} else {
    $top_students = [];
}

// Adviser performance
$query = "SELECT u.first_name, u.last_name,
                 COUNT(DISTINCT gs.id) as processed_submissions,
                 COUNT(DISTINCT ha.id) as reviewed_applications,
                 AVG(DATEDIFF(gs.processed_at, gs.upload_date)) as avg_processing_days
          FROM users u
          LEFT JOIN grade_submissions gs ON u.id = gs.processed_by
          LEFT JOIN honor_applications ha ON u.id = ha.reviewed_by
          WHERE u.role = 'adviser' AND u.department = :department AND u.status = 'active'
          GROUP BY u.id, u.first_name, u.last_name
          ORDER BY processed_submissions DESC, reviewed_applications DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$adviser_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Get academic periods
$periods_query = "SELECT DISTINCT ap.id, ap.period_name, ap.school_year, ap.semester
                  FROM academic_periods ap
                  ORDER BY ap.school_year DESC, ap.semester DESC";
$periods_stmt = $db->prepare($periods_query);
$periods_stmt->execute();
$available_periods = $periods_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Reports - CTU Honor System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <img src="../img/cebu-technological-university-seeklogo.png" alt="CTU Logo" class="w-8 h-8">
                    <span class="ml-2 text-xl font-bold text-gray-900">CTU Honor</span>
                </div>
                
                <!-- User Profile -->
                <div class="mt-8 px-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                            <i data-lucide="user-check" class="w-5 h-5 text-green-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-semibold text-gray-900"><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></p>
                            <p class="text-xs text-gray-500">Adviser</p>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="mt-8 flex-1 px-2 space-y-1">
                    <a href="dashboard.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="layout-dashboard" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Dashboard
                    </a>
                    <a href="submissions.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="file-text" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Grade Submissions
                    </a>
                    <a href="applications.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="trophy" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Honor Applications
                    </a>
                    <a href="students.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="users" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Students
                    </a>
                    <a href="rankings.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="award" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Honor Rankings
                    </a>
                    <a href="reports.php" class="bg-green-50 border-r-2 border-green-600 text-green-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="bar-chart-3" class="text-green-500 mr-3 h-5 w-5"></i>
                        Reports
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
                            <h1 class="text-2xl font-bold text-gray-900">Department Reports</h1>
                            <p class="text-sm text-gray-500">Comprehensive analytics for <?php echo $department; ?> department</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors flex items-center">
                                <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                                Export Report
                                <i data-lucide="chevron-down" class="w-4 h-4 ml-2"></i>
                            </button>
                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-200 py-2 z-10">
                                <button onclick="exportWord()" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center">
                                    <i data-lucide="file-text" class="w-4 h-4 mr-2"></i>
                                    Export as Word
                                </button>
                                <button onclick="exportPDF(false)" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center">
                                    <i data-lucide="file-text" class="w-4 h-4 mr-2"></i>
                                    Export as PDF
                                </button>
                                <button onclick="exportPDF(true)" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center">
                                    <i data-lucide="printer" class="w-4 h-4 mr-2"></i>
                                    Print Report
                                </button>
                            </div>
                        </div>
                    </div>

                    <?php include 'includes/header.php'; ?>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-4 sm:p-6 lg:p-8">
                    <!-- Filters Section -->
                    <div class="mb-6">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <header class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900">Report Filters</h3>
                                <p class="text-sm text-gray-500 mt-1">Filter the data to customize your report</p>
                            </header>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                                    <!-- Year Level Filter -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Year Level</label>
                                        <select id="year_filter" onchange="applyFilters()"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                            <?php foreach ($available_years as $year): ?>
                                                <option value="<?php echo $year['year_level']; ?>" <?php echo $year_filter == $year['year_level'] ? 'selected' : ''; ?>>
                                                    Year <?php echo $year['year_level']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Section Filter -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Section</label>
                                        <select id="section_filter" onchange="applyFilters()"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                            <option value="all" <?php echo $section_filter === 'all' ? 'selected' : ''; ?>>All Sections</option>
                                            <?php foreach ($available_sections as $section): ?>
                                                <option value="<?php echo $section['section']; ?>" <?php echo $section_filter == $section['section'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars(formatSectionDisplay($section['section'])); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Honor Type Filter -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Honor Type</label>
                                        <select id="ranking_type_filter" onchange="applyFilters()"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                            <option value="deans_list" <?php echo $ranking_type_filter === 'deans_list' ? 'selected' : ''; ?>>Dean's List</option>
                                            <option value="latin_honors" <?php echo $ranking_type_filter === 'latin_honors' ? 'selected' : ''; ?>>Latin Honors</option>
                                        </select>
                                    </div>

                                    <!-- Period Filter -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Academic Period</label>
                                        <select id="period_filter" onchange="applyFilters()"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                            <option value="all" <?php echo $period_filter === 'all' ? 'selected' : ''; ?>>Current Period</option>
                                            <?php foreach ($available_periods as $period): ?>
                                                <option value="<?php echo $period['id']; ?>" <?php echo $period_filter == $period['id'] ? 'selected' : ''; ?>>
                                                    <?php echo $period['semester']; ?> Sem SY <?php echo $period['school_year']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Clear Filters Button -->
                                <div class="flex justify-center mt-6">
                                    <button type="button" onclick="clearFilters()"
                                            class="inline-flex items-center px-6 py-3 text-sm font-medium text-white bg-gradient-to-r from-red-500 to-red-600 border border-transparent rounded-xl hover:from-red-600 hover:to-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 shadow-sm hover:shadow-md transform hover:scale-105 transition-all duration-200">
                                        <i data-lucide="filter-x" class="w-4 h-4 mr-2"></i>
                                        Clear All Filters
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Executive Summary -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="users" class="w-6 h-6 text-blue-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500 whitespace-nowrap">Honor Eligible Students</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_students']; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-green-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="user-check" class="w-6 h-6 text-green-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Advisers</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_advisers']; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-purple-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="trophy" class="w-6 h-6 text-purple-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Applications</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_applications']; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-green-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="check-circle" class="w-6 h-6 text-green-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Approved</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['approved_applications'] ?? 0; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Year Level Performance -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 mb-8">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i data-lucide="bar-chart-3" class="w-5 h-5 text-blue-600 mr-2"></i>
                                Year Level Performance
                            </h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($year_performance)): ?>
                                <div class="text-center py-8">
                                    <i data-lucide="graduation-cap" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                                    <p class="text-gray-500">No year level data available yet</p>
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                    <?php foreach ($year_performance as $year): ?>
                                        <div class="bg-gray-50 rounded-xl p-6 text-center">
                                            <h4 class="text-2xl font-bold text-primary-600 mb-2">Year <?php echo $year['year_level']; ?></h4>
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="text-lg font-bold text-gray-900"><?php echo $year['student_count']; ?></div>
                                                    <div class="text-xs text-gray-500">Students</div>
                                                </div>
                                                <div>
                                                    <div class="text-lg font-bold text-green-600"><?php echo $year['honor_eligible']; ?></div>
                                                    <div class="text-xs text-gray-500">Honor Eligible</div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Top Students -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="star" class="w-5 h-5 text-yellow-600 mr-2"></i>
                                    Ranking
                                </h3>
                            </div>
                            <div class="p-6">
                                <?php if (empty($top_students)): ?>
                                    <div class="text-center py-8">
                                        <i data-lucide="users" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                                        <p class="text-gray-500">No student data available yet</p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <?php foreach (array_slice($top_students, 0, 5) as $index => $student): ?>
                                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold <?php echo $index === 0 ? 'bg-yellow-100 text-yellow-800' : ($index === 1 ? 'bg-gray-100 text-gray-800' : ($index === 2 ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800')); ?>">
                                                        #<?php echo $index + 1; ?>
                                                    </div>
                                                    <div class="ml-3">
                                                        <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($student['student_id'] . ' • ' . formatSectionDisplay($student['section'])); ?></p>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-lg font-bold text-primary-600"><?php echo formatGWA($student['gwa']); ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Adviser Performance -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="user-check" class="w-5 h-5 text-green-600 mr-2"></i>
                                    Adviser Performance
                                </h3>
                            </div>
                            <div class="p-6">
                                <?php if (empty($adviser_performance)): ?>
                                    <div class="text-center py-8">
                                        <i data-lucide="user-check" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                                        <p class="text-gray-500">No adviser data available yet</p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <?php foreach ($adviser_performance as $adviser): ?>
                                            <div class="p-3 bg-gray-50 rounded-xl">
                                                <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($adviser['first_name'] . ' ' . $adviser['last_name']); ?></h4>
                                                <div class="grid grid-cols-3 gap-4 mt-2 text-sm">
                                                    <div>
                                                        <div class="font-bold text-blue-600"><?php echo $adviser['processed_submissions']; ?></div>
                                                        <div class="text-gray-500">Submissions</div>
                                                    </div>
                                                    <div>
                                                        <div class="font-bold text-purple-600"><?php echo $adviser['reviewed_applications']; ?></div>
                                                        <div class="text-gray-500">Applications</div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // GWA Distribution Chart
        <?php if (!empty($gwa_distribution)): ?>
        const gwaCtx = document.getElementById('gwaChart').getContext('2d');
        const gwaChart = new Chart(gwaCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($gwa_distribution, 'grade_category')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($gwa_distribution, 'student_count')); ?>,
                    backgroundColor: [
                        '#8B5CF6', '#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#6B7280', '#9CA3AF'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>

        // Application Trends Chart
        <?php if (!empty($monthly_trends)): ?>
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        const trendsChart = new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_trends, 'month')); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo json_encode(array_column($monthly_trends, 'application_count')); ?>,
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Approved',
                    data: <?php echo json_encode(array_column($monthly_trends, 'approved_count')); ?>,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Export functions
        function exportWord() {
            showNotification('Generating Word document...', 'info');

            // Build export URL with current filters
            const params = new URLSearchParams({
                ranking_type: document.getElementById('ranking_type_filter').value,
                year: document.getElementById('year_filter').value,
                section: document.getElementById('section_filter').value,
                period: document.getElementById('period_filter').value
            });

            const exportUrl = 'export_report.php?' + params.toString();

            // Create temporary link and trigger download
            const link = document.createElement('a');
            link.href = exportUrl;
            link.download = '';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            setTimeout(() => {
                showNotification('Word document exported successfully!', 'success');
            }, 1000);
        }

        function exportPDF(isPrint = false) {
            showNotification(isPrint ? 'Preparing report for printing...' : 'Generating PDF file...', 'info');

            // Build export URL with current filters
            const params = new URLSearchParams({
                ranking_type: document.getElementById('ranking_type_filter').value,
                year: document.getElementById('year_filter').value,
                section: document.getElementById('section_filter').value,
                period: document.getElementById('period_filter').value
            });

            const exportUrl = 'export_report_pdf.php?' + params.toString();

            if (isPrint) {
                // Open PDF in new tab and trigger print
                const printWindow = window.open(exportUrl, '_blank');
                if (printWindow) {
                    const checkLoaded = setInterval(() => {
                        if (printWindow.document.readyState === 'complete') {
                            clearInterval(checkLoaded);
                            printWindow.focus();
                            printWindow.print();
                        }
                    }, 500);
                } else {
                    showNotification('Popup blocked! Please allow popups to print the report.', 'error');
                }
            } else {
                // Download PDF
                const link = document.createElement('a');
                link.href = exportUrl;
                link.download = '';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                setTimeout(() => {
                    showNotification('PDF file exported successfully!', 'success');
                }, 1000);
            }
        }


        function printReport() {
            // Hide unnecessary elements for printing
            const sidebar = document.querySelector('.md\\:w-64');
            const header = document.querySelector('header');
            const filters = document.querySelector('.mb-6');
            const exportButtons = document.querySelector('.relative.x-data');

            // Store original display values
            const originalDisplay = {
                sidebar: sidebar ? sidebar.style.display : '',
                header: header ? header.style.display : '',
                filters: filters ? filters.style.display : '',
                buttons: exportButtons ? exportButtons.style.display : ''
            };

            // Hide elements
            if (sidebar) sidebar.style.display = 'none';
            if (header) header.style.display = 'none';
            if (filters) filters.style.display = 'none';
            if (exportButtons) exportButtons.style.display = 'none';

            // Add print-specific styles
            const printStyles = document.createElement('style');
            printStyles.id = 'print-styles';
            printStyles.innerHTML = `
                @media print {
                    body {
                        font-size: 12pt;
                        background: white;
                    }
                    .shadow-sm { box-shadow: none !important; }
                    .rounded-2xl { border-radius: 0 !important; }
                    .p-6 { padding: 0.5rem !important; }
                    .mb-8 { margin-bottom: 1rem !important; }
                    .text-2xl { font-size: 1.5rem !important; }
                    .bg-gray-50 { background: white !important; }
                    canvas {
                        max-width: 100% !important;
                        height: auto !important;
                    }
                    .page-break-before { page-break-before: always; }
                    .page-break-after { page-break-after: always; }
                    .no-page-break { page-break-inside: avoid; }
                }
            `;
            document.head.appendChild(printStyles);

            // Trigger print
            setTimeout(() => {
                window.print();

                // Restore original display values after print
                setTimeout(() => {
                    if (sidebar) sidebar.style.display = originalDisplay.sidebar;
                    if (header) header.style.display = originalDisplay.header;
                    if (filters) filters.style.display = originalDisplay.filters;
                    if (exportButtons) exportButtons.style.display = originalDisplay.buttons;

                    const addedStyles = document.getElementById('print-styles');
                    if (addedStyles) addedStyles.remove();
                }, 100);
            }, 100);
        }

        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-xl shadow-lg z-50 flex items-center space-x-2 transform translate-x-full transition-transform duration-300`;

            // Set color based on type
            const colors = {
                success: 'bg-green-500 text-white',
                error: 'bg-red-500 text-white',
                info: 'bg-blue-500 text-white',
                warning: 'bg-yellow-500 text-white'
            };

            notification.className += ' ' + (colors[type] || colors.info);

            // Set icon based on type
            const icons = {
                success: 'check-circle',
                error: 'x-circle',
                info: 'info',
                warning: 'alert-triangle'
            };

            notification.innerHTML = `
                <i data-lucide="${icons[type] || icons.info}" class="w-5 h-5"></i>
                <span>${message}</span>
            `;

            // Add to page
            document.body.appendChild(notification);
            lucide.createIcons();

            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);

            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        // Apply all filters
        function applyFilters() {
            const url = new URL(window.location);

            // Get all filter values
            const year_filter = document.getElementById('year_filter').value;
            const section_filter = document.getElementById('section_filter').value;
            const ranking_type_filter = document.getElementById('ranking_type_filter').value;
            const period_filter = document.getElementById('period_filter').value;

            // Set or remove parameters
            // Always set year filter (no 'all' option)
            url.searchParams.set('year', year_filter);

            if (section_filter !== 'all') {
                url.searchParams.set('section', section_filter);
            } else {
                url.searchParams.delete('section');
            }

            // Always set ranking type filter (no 'all' option)
            url.searchParams.set('ranking_type', ranking_type_filter);

            if (period_filter !== 'all') {
                url.searchParams.set('period', period_filter);
            } else {
                url.searchParams.delete('period');
            }

            window.location.href = url.toString();
        }

        // Clear all filters
        function clearFilters() {
            const url = new URL(window.location);
            // Keep year filter with default value (Year 1)
            url.searchParams.set('year', '1');
            url.searchParams.delete('section');
            // Keep ranking type with default value (Dean's List)
            url.searchParams.set('ranking_type', 'deans_list');
            url.searchParams.delete('period');
            window.location.href = url.toString();
        }
    </script>
</body>
</html>
