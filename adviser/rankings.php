<?php
require_once '../config/config.php';
require_once '../includes/application-periods.php';

requireLogin();

if (!hasRole('adviser')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$adviser_id = $_SESSION['user_id'];
$department = $_SESSION['department'];

// Get adviser's assigned section
$adviser_query = "SELECT section, year_level FROM users WHERE id = :adviser_id AND role = 'adviser'";
$adviser_stmt = $db->prepare($adviser_query);
$adviser_stmt->bindParam(':adviser_id', $adviser_id);
$adviser_stmt->execute();
$adviser_data = $adviser_stmt->fetch(PDO::FETCH_ASSOC);

$adviser_section = $adviser_data['section'] ?? null;
$adviser_year_level = $adviser_data['year_level'] ?? null;

// Get current academic period
$query = "SELECT * FROM academic_periods WHERE is_active = 1 LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$active_period = $stmt->fetch(PDO::FETCH_ASSOC);

// Get application period information for this department
$application_period = isApplicationPeriodOpen($db, $department);
if (!$application_period) {
    $next_application_period = getNextApplicationPeriod($db, $department);
}

// Get students in the department (department-wide for rankings view)
$query = "SELECT u.*,
                 (SELECT COUNT(*) FROM grade_submissions gs WHERE gs.user_id = u.id) as submission_count
          FROM users u
          WHERE u.role = 'student' AND u.department = :department AND u.status = 'active'
          ORDER BY u.last_name, u.first_name";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate period-specific GWA for each student and check for approved applications
foreach ($students as &$student) {
    if ($active_period) {
        $semester_string = $active_period['semester'] . ' Semester SY ' . $active_period['school_year'];

        // Calculate period-specific GWA
        $gwa_query = "
            SELECT
                SUM(g.units * g.grade) as total_grade_points,
                SUM(g.units) as total_units
            FROM grades g
            JOIN grade_submissions gs ON g.submission_id = gs.id
            WHERE gs.user_id = :user_id
            AND g.semester_taken = :semester_taken
            AND g.grade > 0.00
            AND NOT (UPPER(g.subject_name) LIKE '%NSTP%' OR UPPER(g.subject_name) LIKE '%NATIONAL SERVICE TRAINING%')
        ";

        $gwa_stmt = $db->prepare($gwa_query);
        $gwa_stmt->bindParam(':user_id', $student['id']);
        $gwa_stmt->bindParam(':semester_taken', $semester_string);
        $gwa_stmt->execute();
        $gwa_data = $gwa_stmt->fetch(PDO::FETCH_ASSOC);

        if ($gwa_data && $gwa_data['total_units'] > 0) {
            $period_gwa = $gwa_data['total_grade_points'] / $gwa_data['total_units'];
            $student['gwa'] = floor($period_gwa * 100) / 100;
        } else {
            $student['gwa'] = null;
        }

        // Check for approved Latin honors application
        $latin_honors_query = "
            SELECT COUNT(*) as has_approved_latin_honors
            FROM honor_applications ha
            WHERE ha.user_id = :user_id
            AND ha.academic_period_id = :period_id
            AND ha.application_type IN ('cum_laude', 'magna_cum_laude', 'summa_cum_laude')
            AND ha.status = 'final_approved'
        ";

        $latin_honors_stmt = $db->prepare($latin_honors_query);
        $latin_honors_stmt->bindParam(':user_id', $student['id']);
        $latin_honors_stmt->bindParam(':period_id', $active_period['id']);
        $latin_honors_stmt->execute();
        $latin_honors_data = $latin_honors_stmt->fetch(PDO::FETCH_ASSOC);

        $student['has_approved_latin_honors'] = $latin_honors_data['has_approved_latin_honors'] > 0;
    } else {
        $student['gwa'] = null;
        $student['has_approved_latin_honors'] = false;
    }
}
unset($student);

// Sort students by GWA (ascending, nulls last)
usort($students, function($a, $b) {
    if ($a['gwa'] === null && $b['gwa'] === null) return 0;
    if ($a['gwa'] === null) return 1;
    if ($b['gwa'] === null) return -1;
    return $a['gwa'] <=> $b['gwa'];
});

// Filter students
$search = $_GET['search'] ?? '';
$year_filter = $_GET['year'] ?? 'all';
$section_filter = $_GET['section'] ?? 'all';
$ranking_type_filter = $_GET['ranking_type'] ?? 'all';
$filter_period = $_GET['period'] ?? 'all';

if ($year_filter !== 'all') {
    $students = array_filter($students, function($student) use ($year_filter) {
        return $student['year_level'] == $year_filter;
    });
}

if ($section_filter !== 'all') {
    $students = array_filter($students, function($student) use ($section_filter) {
        return $student['section'] == $section_filter;
    });
}

if ($ranking_type_filter !== 'all') {
    $students = array_filter($students, function($student) use ($ranking_type_filter) {
        if ($ranking_type_filter == 'deans_list') {
            return $student['gwa'] !== null && $student['gwa'] <= 1.75;
        } elseif ($ranking_type_filter == 'latin_honors') {
            return $student['has_approved_latin_honors'];
        }
        return true;
    });
}

if (!empty($search)) {
    $students = array_filter($students, function($student) use ($search) {
        return stripos($student['first_name'] . ' ' . $student['last_name'], $search) !== false ||
               stripos($student['student_id'], $search) !== false;
    });
}

// Convert to rankings format for display compatibility
$rankings = [];
foreach ($students as $student) {
    if ($student['gwa'] !== null) {
        $rankings[] = [
            'first_name' => $student['first_name'],
            'last_name' => $student['last_name'],
            'student_id' => $student['student_id'],
            'year_level' => $student['year_level'],
            'section' => $student['section'],
            'gwa' => $student['gwa'],
            'has_approved_latin_honors' => $student['has_approved_latin_honors'],
            'ranking_type' => ($student['has_approved_latin_honors']) ? 'latin_honors' : (($student['gwa'] <= 1.75) ? 'deans_list' : 'regular'),
            'rank_position' => 0,
            'total_students' => count($students),
            'percentile' => 0
        ];
    }
}

// Add debug message after we have the data
$debug_message = "Found " . count($students) . " total students, " . count($rankings) . " with GWA rankings. Using direct approach like chairperson.";

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

// Get academic periods that have ranking records for this department
$periods_query = "SELECT DISTINCT ap.id, ap.period_name, ap.school_year, ap.semester, COUNT(hr.id) as ranking_count
                  FROM academic_periods ap
                  JOIN honor_rankings hr ON ap.id = hr.academic_period_id
                  WHERE hr.department = :department
                  GROUP BY ap.id, ap.period_name, ap.school_year, ap.semester
                  ORDER BY ap.school_year DESC, ap.semester DESC";
$periods_stmt = $db->prepare($periods_query);
$periods_stmt->bindParam(':department', $department);
$periods_stmt->execute();
$available_periods = $periods_stmt->fetchAll(PDO::FETCH_ASSOC);
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
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
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
                        <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i data-lucide="user-check" class="w-5 h-5 text-primary-600"></i>
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
                    <a href="rankings.php" class="bg-primary-50 border-r-2 border-primary-600 text-primary-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="award" class="text-primary-500 mr-3 h-5 w-5"></i>
                        Honor Rankings
                    </a>
                    <a href="reports.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="bar-chart-3" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
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
                            <h1 class="text-2xl font-bold text-gray-900">Honor Rankings</h1>
                            <p class="text-sm text-gray-500">
                                Honor rankings and achievements for <?php echo $department; ?> department
                                <?php if ($active_period): ?>
                                    • <?php echo $active_period['semester']; ?> Semester SY <?php echo $active_period['school_year']; ?>
                                <?php else: ?>
                                    • No active period
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <?php include 'includes/header.php'; ?>
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
                                        No active academic period found. Rankings cannot be displayed without an active period.
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Filters and Search -->
                    <div class="mb-6">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <header class="px-6 py-4 border-b border-gray-200">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-900">Filters & Search</h3>
                                    <div class="flex items-center space-x-3">
                                        <input type="text" id="search" placeholder="Search students..." value="<?php echo htmlspecialchars($search); ?>"
                                               class="px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors w-64">
                                    </div>
                                </div>
                            </header>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-4 mb-4">
                                    <!-- Year Level Filter -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Year Level</label>
                                        <select id="year_filter" onchange="applyFilters()"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                            <option value="all" <?php echo $year_filter === 'all' ? 'selected' : ''; ?>>All Years</option>
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
                                            <option value="all" <?php echo $ranking_type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                                            <option value="deans_list" <?php echo $ranking_type_filter === 'deans_list' ? 'selected' : ''; ?>>Dean's List</option>
                                            <option value="latin_honors" <?php echo $ranking_type_filter === 'latin_honors' ? 'selected' : ''; ?>>Latin Honors</option>
                                        </select>
                                    </div>

                                    <!-- Period Filter -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Academic Period</label>
                                        <select id="period_filter" onchange="applyFilters()"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                            <option value="all" <?php echo $filter_period === 'all' ? 'selected' : ''; ?>>Current Period</option>
                                            <?php foreach ($available_periods as $period): ?>
                                                <option value="<?php echo $period['id']; ?>" <?php echo $filter_period == $period['id'] ? 'selected' : ''; ?>>
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

                    <!-- Rankings Table -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">
                                Honor Rankings
                                <span class="text-sm font-normal text-gray-500">(<?php echo count($rankings); ?> students)</span>
                            </h3>
                            <?php if (isset($debug_message)): ?>
                                <div class="text-xs text-gray-400 mt-2">
                                    Debug: <?php echo htmlspecialchars($debug_message); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="overflow-x-auto">
                            <?php if (empty($rankings)): ?>
                                <div class="text-center py-12">
                                    <i data-lucide="award" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
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
                                        $rank = 1;
                                        foreach ($rankings as $ranking):
                                            $total_eligible = count($rankings);
                                            $percentile = $total_eligible > 1 ? round(($total_eligible - $rank) * 100.0 / ($total_eligible - 1), 1) : 100.0;
                                        ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold <?php echo $rank <= 3 ? ($rank === 1 ? 'bg-yellow-100 text-yellow-800' : ($rank === 2 ? 'bg-gray-100 text-gray-800' : 'bg-orange-100 text-orange-800')) : 'bg-blue-100 text-blue-800'; ?>">
                                                            #<?php echo $rank; ?>
                                                        </div>
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
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="text-sm text-gray-900"><?php echo $ranking['year_level'] . htmlspecialchars(formatSectionDisplay($ranking['section'])); ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php if ($ranking['gwa'] !== null): ?>
                                                        <?php if (isset($ranking['has_approved_latin_honors']) && $ranking['has_approved_latin_honors']): ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                                <i data-lucide="crown" class="w-3 h-3 mr-1"></i>
                                                                Latin Honors
                                                            </span>
                                                        <?php elseif ($ranking['gwa'] <= 1.75): ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                <i data-lucide="star" class="w-3 h-3 mr-1"></i>
                                                                Dean's List
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                                Regular
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                            No Status
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo number_format($ranking['gwa'], 2); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo number_format($percentile, 1); ?>%
                                                </td>
                                            </tr>
                                        <?php
                                            $rank++;
                                        endforeach;
                                        ?>
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
        // Initialize Lucide icons
        lucide.createIcons();

        // Apply all filters
        function applyFilters() {
            const url = new URL(window.location);

            // Get all filter values
            const year_filter = document.getElementById('year_filter').value;
            const section_filter = document.getElementById('section_filter').value;
            const ranking_type_filter = document.getElementById('ranking_type_filter').value;
            const period_filter = document.getElementById('period_filter').value;
            const search = document.getElementById('search').value;

            // Set or remove parameters
            if (year_filter !== 'all') {
                url.searchParams.set('year', year_filter);
            } else {
                url.searchParams.delete('year');
            }

            if (section_filter !== 'all') {
                url.searchParams.set('section', section_filter);
            } else {
                url.searchParams.delete('section');
            }

            if (ranking_type_filter !== 'all') {
                url.searchParams.set('ranking_type', ranking_type_filter);
            } else {
                url.searchParams.delete('ranking_type');
            }

            if (period_filter !== 'all') {
                url.searchParams.set('period', period_filter);
            } else {
                url.searchParams.delete('period');
            }

            if (search) {
                url.searchParams.set('search', search);
            } else {
                url.searchParams.delete('search');
            }

            window.location.href = url.toString();
        }

        // Clear all filters
        function clearFilters() {
            const url = new URL(window.location);
            url.searchParams.delete('year');
            url.searchParams.delete('section');
            url.searchParams.delete('ranking_type');
            url.searchParams.delete('period');
            url.searchParams.delete('search');
            window.location.href = url.toString();
        }

        // Search functionality with debounce
        document.getElementById('search').addEventListener('input', function(e) {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                applyFilters();
            }, 500);
        });
    </script>
</body>
</html>