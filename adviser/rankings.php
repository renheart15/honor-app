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

// Get adviser's assigned section
$query = "SELECT section, year_level FROM users WHERE id = :adviser_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':adviser_id', $adviser_id);
$stmt->execute();
$adviser_info = $stmt->fetch(PDO::FETCH_ASSOC);

$adviser_section = $adviser_info['section'] ?? null;
$adviser_year_level = $adviser_info['year_level'] ?? null;

// Get current academic period
$query = "SELECT * FROM academic_periods WHERE is_active = 1 LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$active_period = $stmt->fetch(PDO::FETCH_ASSOC);

// Get filters
$filter_type = $_GET['type'] ?? 'all';

$section_rankings = [];
$department_stats = [];

if ($active_period && $adviser_section) {
    // Build query based on adviser assignment - only if section is assigned
    $where_conditions = ["hr.department = :department", "hr.academic_period_id = :period_id"];
    $params = [':department' => $department, ':period_id' => $active_period['id']];
    
    // Show only adviser's assigned section
    $where_conditions[] = "hr.section = :section";
    $params[':section'] = $adviser_section;
    
    if ($adviser_year_level) {
        $where_conditions[] = "hr.year_level = :year_level";
        $params[':year_level'] = $adviser_year_level;
    }
    
    if ($filter_type !== 'all') {
        $where_conditions[] = "hr.ranking_type = :ranking_type";
        $params[':ranking_type'] = $filter_type;
    }
    
    $where_clause = implode(' AND ', $where_conditions);

    // Get rankings
    $ranking_query = "
        SELECT 
            hr.*,
            u.student_id,
            u.first_name,
            u.last_name,
            u.year_level,
            u.section,
            ap.period_name,
            ap.school_year,
            ap.semester
        FROM honor_rankings hr
        JOIN users u ON hr.user_id = u.id
        JOIN academic_periods ap ON hr.academic_period_id = ap.id
        WHERE {$where_clause}
        ORDER BY hr.ranking_type, u.year_level, u.section, hr.rank_position
    ";
    
    $stmt = $db->prepare($ranking_query);
    foreach ($params as $param => $value) {
        $stmt->bindParam($param, $value);
    }
    $stmt->execute();
    $section_rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get department ranking statistics - only for assigned section
    $stats_query = "
        SELECT 
            ranking_type,
            year_level,
            section,
            COUNT(*) as total_students,
            MIN(gwa) as best_gwa,
            MAX(gwa) as lowest_gwa,
            AVG(gwa) as average_gwa
        FROM honor_rankings hr
        WHERE hr.department = :department AND hr.academic_period_id = :period_id AND hr.section = :section
        AND hr.year_level IS NOT NULL
        AND hr.section IS NOT NULL
        GROUP BY ranking_type, year_level, section
        ORDER BY ranking_type, year_level, section
    ";
    
    $stmt = $db->prepare($stats_query);
    $stmt->bindParam(':department', $department);
    $stmt->bindParam(':period_id', $active_period['id']);
    $stmt->bindParam(':section', $adviser_section);
    $stmt->execute();
    $department_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // If no active period or no section assigned, show NO rankings
    $section_rankings = [];
    $department_stats = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Section Rankings - CTU Honor System</title>
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
                    <div class="w-8 h-8 bg-green-600 rounded-xl flex items-center justify-center">
                        <i data-lucide="graduation-cap" class="w-5 h-5 text-white"></i>
                    </div>
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
                    <a href="rankings.php" class="bg-green-50 border-r-2 border-green-600 text-green-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="bar-chart-3" class="text-green-500 mr-3 h-5 w-5"></i>
                        Section Rankings
                    </a>
                    <a href="reports.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="file-bar-chart" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
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
                            <h1 class="text-2xl font-bold text-gray-900">
                                <?php if ($adviser_section): ?>
                                    Section <?php echo htmlspecialchars($adviser_section); ?> Rankings
                                <?php else: ?>
                                    Department Rankings
                                <?php endif; ?>
                            </h1>
                            <p class="text-sm text-gray-500">
                                <?php if ($active_period): ?>
                                    <?php echo htmlspecialchars($active_period['period_name']); ?> - <?php echo htmlspecialchars($department); ?> Department
                                    <?php if (!$adviser_section): ?>
                                        <span class="inline-flex items-center ml-2 px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                            <i data-lucide="alert-circle" class="w-3 h-3 mr-1"></i>
                                            No Section Assigned
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    No active academic period
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <form method="GET" class="flex items-center space-x-2">
                            <select name="type" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Rankings</option>
                                <option value="deans_list" <?php echo $filter_type === 'deans_list' ? 'selected' : ''; ?>>Dean's List</option>
                                <option value="presidents_list" <?php echo $filter_type === 'presidents_list' ? 'selected' : ''; ?>>President's List</option>
                                <option value="overall" <?php echo $filter_type === 'overall' ? 'selected' : ''; ?>>Overall</option>
                            </select>
                        </form>
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
                                        <strong>No Active Academic Period:</strong> Rankings are not available when there's no active academic period.
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php elseif (!$adviser_section): ?>
                        <div class="mb-6 bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-lg">
                            <div class="flex">
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-amber-800">No Section Assigned</p>
                                    <p class="mt-1 text-sm text-amber-700">You cannot view any honor rankings until the chairperson assigns you to a section. Contact your department chairperson for section assignment.</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <?php if (!empty($department_stats)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <?php
                            $stats_summary = [];
                            foreach ($department_stats as $stat) {
                                if (!isset($stats_summary[$stat['ranking_type']])) {
                                    $stats_summary[$stat['ranking_type']] = [
                                        'total' => 0,
                                        'best_gwa' => null,
                                        'avg_gwa' => []
                                    ];
                                }
                                $stats_summary[$stat['ranking_type']]['total'] += $stat['total_students'];
                                if ($stats_summary[$stat['ranking_type']]['best_gwa'] === null || $stat['best_gwa'] < $stats_summary[$stat['ranking_type']]['best_gwa']) {
                                    $stats_summary[$stat['ranking_type']]['best_gwa'] = $stat['best_gwa'];
                                }
                                $stats_summary[$stat['ranking_type']]['avg_gwa'][] = $stat['average_gwa'];
                            }
                            
                            foreach ($stats_summary as $type => $summary):
                                $avg_gwa = array_sum($summary['avg_gwa']) / count($summary['avg_gwa']);
                            ?>
                                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="w-8 h-8 <?php echo $type === 'deans_list' ? 'bg-blue-100 text-blue-600' : ($type === 'presidents_list' ? 'bg-purple-100 text-purple-600' : 'bg-green-100 text-green-600'); ?> rounded-xl flex items-center justify-center">
                                                <i data-lucide="trophy" class="w-4 h-4"></i>
                                            </div>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">
                                                    <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                                                </dt>
                                                <dd class="flex items-baseline">
                                                    <div class="text-2xl font-semibold text-gray-900">
                                                        <?php echo $summary['total']; ?>
                                                    </div>
                                                    <div class="ml-2 text-sm text-gray-500">
                                                        students
                                                    </div>
                                                </dd>
                                                <dd class="text-xs text-gray-500 mt-1">
                                                    Best GWA: <?php echo number_format($summary['best_gwa'], 3); ?> | 
                                                    Avg: <?php echo number_format($avg_gwa, 3); ?>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Rankings Table -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">
                                Honor Rankings 
                                <span class="text-sm font-normal text-gray-500">(<?php echo count($section_rankings); ?> students)</span>
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <?php if (empty($section_rankings)): ?>
                                <div class="text-center py-12">
                                    <i data-lucide="trophy" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                                    <h4 class="text-xl font-medium text-gray-900 mb-2">No Rankings Found</h4>
                                    <p class="text-gray-500">
                                        <?php if (!$active_period): ?>
                                            Set an active academic period to see honor roll students.
                                        <?php elseif (!$adviser_section): ?>
                                            No honor rankings available for the department yet. Rankings need to be generated by the chairperson.
                                        <?php else: ?>
                                            No honor rankings available for section <?php echo htmlspecialchars($adviser_section); ?> yet. Rankings need to be generated by the chairperson.
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
                                        foreach ($section_rankings as $ranking): 
                                            $section_key = $ranking['year_level'] . '-' . $ranking['section'];
                                            $type_section_key = $ranking['ranking_type'] . '-' . $section_key;
                                            
                                            // Add section header if needed
                                            if ($current_type . $current_section !== $type_section_key):
                                                $current_type = $ranking['ranking_type'];
                                                $current_section = $section_key;
                                        ?>
                                                <tr class="bg-green-50">
                                                    <td colspan="6" class="px-6 py-3 text-sm font-semibold text-green-800">
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
                                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php echo $ranking['ranking_type'] === 'deans_list' ? 'bg-blue-100 text-blue-800' : ($ranking['ranking_type'] === 'presidents_list' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $ranking['ranking_type'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo number_format($ranking['gwa'], 3); ?>
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
    </script>
</body>
</html>