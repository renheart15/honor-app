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

// Get adviser's assigned section and year level
$query = "SELECT section, year_level FROM users WHERE id = :adviser_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':adviser_id', $adviser_id);
$stmt->execute();
$adviser_info = $stmt->fetch(PDO::FETCH_ASSOC);

$adviser_section = $adviser_info['section'] ?? null;
$adviser_year_level = $adviser_info['year_level'] ?? null;

// Decode JSON section if it exists
if ($adviser_section) {
    $sections_array = json_decode($adviser_section, true);
    $adviser_section = is_array($sections_array) && !empty($sections_array) ? $sections_array[0] : $adviser_section;
}

// Build flexible section matching conditions
$section_conditions = [];
$section_params = [];
if ($adviser_section) {
    // Try exact match and partial matches
    $section_conditions[] = "u.section = ?";
    $section_params[] = $adviser_section;

    // If section is like "C-4", also try "C" and "4"
    if (strpos($adviser_section, '-') !== false) {
        $parts = explode('-', $adviser_section);
        foreach ($parts as $part) {
            $section_conditions[] = "u.section = ?";
            $section_params[] = trim($part);
        }
    }

    // Also try partial matches using LIKE
    $section_conditions[] = "u.section LIKE ?";
    $section_params[] = '%' . $adviser_section . '%';
}

// Get section statistics
$stats = [];

// Total students in adviser's section
if ($adviser_section) {
    $section_where = '(' . implode(' OR ', $section_conditions) . ')';
    $query = "SELECT COUNT(*) as count FROM users u WHERE u.role = 'student' AND u.department = ? AND $section_where AND u.status = 'active'";
    $params = array_merge([$department], $section_params);
    $stmt = $db->prepare($query);
    $stmt->execute($params);
} else {
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'student' AND department = :department AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->execute();
}
$stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Students with GWA in adviser's section
if ($adviser_section) {
    $section_where = '(' . implode(' OR ', $section_conditions) . ')';
    $query = "SELECT COUNT(DISTINCT gwa.user_id) as count FROM gwa_calculations gwa
              JOIN users u ON gwa.user_id = u.id
              WHERE u.department = ? AND $section_where AND u.status = 'active'";
    $params = array_merge([$department], $section_params);
    $stmt = $db->prepare($query);
    $stmt->execute($params);
} else {
    $query = "SELECT COUNT(DISTINCT gwa.user_id) as count FROM gwa_calculations gwa
              JOIN users u ON gwa.user_id = u.id
              WHERE u.department = :department AND u.status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->execute();
}
$stats['students_with_gwa'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Honor eligible students in adviser's section
if ($adviser_section) {
    $section_where = '(' . implode(' OR ', $section_conditions) . ')';
    $query = "SELECT COUNT(DISTINCT gwa.user_id) as count FROM gwa_calculations gwa
              JOIN users u ON gwa.user_id = u.id
              WHERE u.department = ? AND $section_where AND u.status = 'active' AND gwa.gwa <= 1.75";
    $params = array_merge([$department], $section_params);
    $stmt = $db->prepare($query);
    $stmt->execute($params);
} else {
    $query = "SELECT COUNT(DISTINCT gwa.user_id) as count FROM gwa_calculations gwa
              JOIN users u ON gwa.user_id = u.id
              WHERE u.department = :department AND u.status = 'active' AND gwa.gwa <= 1.75";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->execute();
}
$stats['honor_eligible'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Dean's list students in adviser's section
if ($adviser_section) {
    $section_where = '(' . implode(' OR ', $section_conditions) . ')';
    $query = "SELECT COUNT(DISTINCT gwa.user_id) as count FROM gwa_calculations gwa
              JOIN users u ON gwa.user_id = u.id
              WHERE u.department = ? AND $section_where AND u.status = 'active' AND gwa.gwa <= 1.45";
    $params = array_merge([$department], $section_params);
    $stmt = $db->prepare($query);
    $stmt->execute($params);
} else {
    $query = "SELECT COUNT(DISTINCT gwa.user_id) as count FROM gwa_calculations gwa
              JOIN users u ON gwa.user_id = u.id
              WHERE u.department = :department AND u.status = 'active' AND gwa.gwa <= 1.45";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->execute();
}
$stats['deans_list'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// GWA distribution
$query = "SELECT 
            CASE 
                WHEN gwa.gwa <= 1.00 THEN 'Summa Cum Laude (1.00)'
                WHEN gwa.gwa <= 1.45 THEN 'Magna Cum Laude (1.01-1.45)'
                WHEN gwa.gwa <= 1.75 THEN 'Cum Laude (1.46-1.75)'
                WHEN gwa.gwa <= 2.00 THEN 'Very Good (1.76-2.00)'
                WHEN gwa.gwa <= 2.50 THEN 'Good (2.01-2.50)'
                WHEN gwa.gwa <= 3.00 THEN 'Fair (2.51-3.00)'
                ELSE 'Needs Improvement (3.01+)'
            END as grade_category,
            COUNT(*) as student_count
          FROM gwa_calculations gwa 
          JOIN users u ON gwa.user_id = u.id 
          WHERE u.department = :department AND u.status = 'active'
          GROUP BY grade_category
          ORDER BY MIN(gwa.gwa)";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$gwa_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Year level breakdown
$query = "SELECT u.year_level, COUNT(*) as student_count, AVG(gwa.gwa) as avg_gwa
          FROM users u 
          LEFT JOIN gwa_calculations gwa ON u.id = gwa.user_id
          WHERE u.role = 'student' AND u.department = :department AND u.status = 'active'
          GROUP BY u.year_level 
          ORDER BY u.year_level";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$year_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent activity
$query = "SELECT 'submission' as type, gs.upload_date as date, u.first_name, u.last_name, gs.status
          FROM grade_submissions gs
          JOIN users u ON gs.user_id = u.id
          WHERE u.department = :department
          UNION ALL
          SELECT 'application' as type, ha.submitted_at as date, u.first_name, u.last_name, ha.status
          FROM honor_applications ha
          JOIN users u ON ha.user_id = u.id
          WHERE u.department = :department
          ORDER BY date DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get Dean's List students for export
$deans_list_students = [];
if ($adviser_section) {
    $section_where = '(' . implode(' OR ', $section_conditions) . ')';
    $query = "SELECT u.first_name, u.last_name, u.student_id, u.year_level, u.section, gwa.gwa
              FROM gwa_calculations gwa
              JOIN users u ON gwa.user_id = u.id
              WHERE u.department = ? AND $section_where AND u.status = 'active' AND gwa.gwa <= 1.75
              ORDER BY u.year_level, gwa.gwa ASC, u.last_name ASC";
    $params = array_merge([$department], $section_params);
    $stmt = $db->prepare($query);
    $stmt->execute($params);
} else {
    $query = "SELECT u.first_name, u.last_name, u.student_id, u.year_level, u.section, gwa.gwa
              FROM gwa_calculations gwa
              JOIN users u ON gwa.user_id = u.id
              WHERE u.department = :department AND u.status = 'active' AND gwa.gwa <= 1.75
              ORDER BY u.year_level, gwa.gwa ASC, u.last_name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->execute();
}
$deans_list_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current academic period for export header
$query = "SELECT * FROM academic_periods WHERE is_active = 1 LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$active_period = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - CTU Honor System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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
                            <p class="text-sm text-gray-500">Analytics and insights for <?php echo $department; ?> department</p>
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
                                <button onclick="exportCSV()" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center">
                                    <i data-lucide="file-text" class="w-4 h-4 mr-2"></i>
                                    Export as CSV
                                </button>
                                <button onclick="exportPDF()" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center">
                                    <i data-lucide="file" class="w-4 h-4 mr-2"></i>
                                    Export as PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-4 sm:p-6 lg:p-8">
                    <!-- Stats Overview -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="users" class="w-6 h-6 text-blue-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Students</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_students']; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-green-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="trending-up" class="w-6 h-6 text-green-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">With GWA</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['students_with_gwa']; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-purple-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="trophy" class="w-6 h-6 text-purple-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Honor Eligible</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['honor_eligible']; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-yellow-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="star" class="w-6 h-6 text-yellow-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Dean's List</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['deans_list']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- GWA Distribution Chart -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="pie-chart" class="w-5 h-5 text-primary-600 mr-2"></i>
                                    GWA Distribution
                                </h3>
                            </div>
                            <div class="p-6">
                                <canvas id="gwaChart" width="400" height="300"></canvas>
                            </div>
                        </div>

                        <!-- Year Level Breakdown -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="bar-chart-3" class="w-5 h-5 text-green-600 mr-2"></i>
                                    Year Level Breakdown
                                </h3>
                            </div>
                            <div class="p-6">
                                <canvas id="yearChart" width="400" height="300"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="mt-8 bg-white rounded-2xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i data-lucide="activity" class="w-5 h-5 text-blue-600 mr-2"></i>
                                Recent Activity
                            </h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($recent_activity)): ?>
                                <div class="text-center py-6">
                                    <i data-lucide="activity" class="w-12 h-12 text-gray-300 mx-auto mb-4"></i>
                                    <p class="text-gray-500">No recent activity</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 <?php echo $activity['type'] === 'submission' ? 'bg-blue-100' : 'bg-purple-100'; ?> rounded-lg flex items-center justify-center">
                                                    <i data-lucide="<?php echo $activity['type'] === 'submission' ? 'file-text' : 'trophy'; ?>" class="w-4 h-4 <?php echo $activity['type'] === 'submission' ? 'text-blue-600' : 'text-purple-600'; ?>"></i>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500">
                                                        <?php echo ucfirst($activity['type']); ?> • <?php echo date('M d, Y g:i A', strtotime($activity['date'])); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getStatusBadge($activity['status']) === 'success' ? 'bg-green-100 text-green-800' : ($activity['status'] === 'pending' || $activity['status'] === 'submitted' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                                <?php echo ucfirst($activity['status']); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // GWA Distribution Chart
        const gwaCtx = document.getElementById('gwaChart').getContext('2d');
        const gwaChart = new Chart(gwaCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($gwa_distribution, 'grade_category')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($gwa_distribution, 'student_count')); ?>,
                    backgroundColor: [
                        '#10B981', '#3B82F6', '#8B5CF6', '#F59E0B', '#EF4444', '#6B7280', '#9CA3AF'
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

        // Year Level Chart
        const yearCtx = document.getElementById('yearChart').getContext('2d');
        const yearChart = new Chart(yearCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(function($year) { return 'Year ' . $year['year_level']; }, $year_breakdown)); ?>,
                datasets: [{
                    label: 'Student Count',
                    data: <?php echo json_encode(array_column($year_breakdown, 'student_count')); ?>,
                    backgroundColor: '#3B82F6',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
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

        function exportCSV() {
            // Create CSV content
            const stats = {
                totalStudents: <?php echo $stats['total_students']; ?>,
                withGWA: <?php echo $stats['students_with_gwa']; ?>,
                honorEligible: <?php echo $stats['honor_eligible']; ?>,
                deansList: <?php echo $stats['deans_list']; ?>
            };

            const gwaDistribution = <?php echo json_encode($gwa_distribution); ?>;
            const yearBreakdown = <?php echo json_encode($year_breakdown); ?>;
            const department = <?php echo json_encode($department); ?>;
            const adviser = <?php echo json_encode($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>;
            const date = new Date().toLocaleDateString();

            let csv = 'CTU Honor System - Department Report\n';
            csv += `Generated by: ${adviser}\n`;
            csv += `Date: ${date}\n`;
            csv += `Department: ${department}\n\n`;

            csv += 'SUMMARY STATISTICS\n';
            csv += 'Metric,Count\n';
            csv += `Total Students,${stats.totalStudents}\n`;
            csv += `Students with GWA,${stats.withGWA}\n`;
            csv += `Honor Eligible,${stats.honorEligible}\n`;
            csv += `Dean's List,${stats.deansList}\n\n`;

            csv += 'GWA DISTRIBUTION\n';
            csv += 'Category,Student Count\n';
            gwaDistribution.forEach(item => {
                csv += `"${item.grade_category}",${item.student_count}\n`;
            });

            csv += '\nYEAR LEVEL BREAKDOWN\n';
            csv += 'Year Level,Student Count,Average GWA\n';
            yearBreakdown.forEach(item => {
                const avgGwa = item.avg_gwa ? parseFloat(item.avg_gwa).toFixed(2) : 'N/A';
                csv += `Year ${item.year_level},${item.student_count},${avgGwa}\n`;
            });

            // Create download link
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);

            link.setAttribute('href', url);
            link.setAttribute('download', `Department_Report_${date.replace(/\//g, '-')}.csv`);
            link.style.visibility = 'hidden';

            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function exportPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            const stats = {
                totalStudents: <?php echo $stats['total_students']; ?>,
                withGWA: <?php echo $stats['students_with_gwa']; ?>,
                honorEligible: <?php echo $stats['honor_eligible']; ?>,
                deansList: <?php echo $stats['deans_list']; ?>
            };

            const gwaDistribution = <?php echo json_encode($gwa_distribution); ?>;
            const yearBreakdown = <?php echo json_encode($year_breakdown); ?>;
            const department = <?php echo json_encode($department); ?>;
            const adviser = <?php echo json_encode($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>;
            const date = new Date().toLocaleDateString();

            // Title
            doc.setFontSize(18);
            doc.setFont(undefined, 'bold');
            doc.text('CTU Honor System', 105, 20, { align: 'center' });
            doc.setFontSize(14);
            doc.text('Department Report', 105, 28, { align: 'center' });

            // Metadata
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            doc.text(`Generated by: ${adviser}`, 20, 40);
            doc.text(`Date: ${date}`, 20, 46);
            doc.text(`Department: ${department}`, 20, 52);

            // Summary Statistics
            doc.setFontSize(12);
            doc.setFont(undefined, 'bold');
            doc.text('Summary Statistics', 20, 65);

            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            let y = 73;
            doc.text(`Total Students: ${stats.totalStudents}`, 25, y);
            y += 6;
            doc.text(`Students with GWA: ${stats.withGWA}`, 25, y);
            y += 6;
            doc.text(`Honor Eligible: ${stats.honorEligible}`, 25, y);
            y += 6;
            doc.text(`Dean's List: ${stats.deansList}`, 25, y);
            y += 12;

            // GWA Distribution
            doc.setFontSize(12);
            doc.setFont(undefined, 'bold');
            doc.text('GWA Distribution', 20, y);
            y += 8;

            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            gwaDistribution.forEach(item => {
                if (y > 270) {
                    doc.addPage();
                    y = 20;
                }
                doc.text(`${item.grade_category}: ${item.student_count} students`, 25, y);
                y += 6;
            });

            // Year Level Breakdown
            y += 6;
            if (y > 250) {
                doc.addPage();
                y = 20;
            }

            doc.setFontSize(12);
            doc.setFont(undefined, 'bold');
            doc.text('Year Level Breakdown', 20, y);
            y += 8;

            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            yearBreakdown.forEach(item => {
                if (y > 270) {
                    doc.addPage();
                    y = 20;
                }
                const avgGwa = item.avg_gwa ? parseFloat(item.avg_gwa).toFixed(2) : 'N/A';
                doc.text(`Year ${item.year_level}: ${item.student_count} students (Avg GWA: ${avgGwa})`, 25, y);
                y += 6;
            });

            // Save PDF
            doc.save(`Department_Report_${date.replace(/\//g, '-')}.pdf`);
        }
    </script>
</body>
</html>
