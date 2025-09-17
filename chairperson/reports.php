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

// Get comprehensive department statistics
$stats = [];

// Total students
$query = "SELECT COUNT(*) as count FROM users WHERE role = 'student' AND department = :department AND status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total advisers
$query = "SELECT COUNT(*) as count FROM users WHERE role = 'adviser' AND department = :department AND status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$stats['total_advisers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Honor applications statistics
$query = "SELECT
            COUNT(*) as total_applications,
            SUM(CASE WHEN ha.status = 'final_approved' THEN 1 ELSE 0 END) as approved_applications,
            SUM(CASE WHEN ha.status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications,
            SUM(CASE WHEN ha.status IN ('submitted', 'approved') THEN 1 ELSE 0 END) as pending_applications
          FROM honor_applications ha
          JOIN users u ON ha.user_id = u.id
          WHERE u.department = :department";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$app_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$stats = array_merge($stats, $app_stats);

// GWA distribution
$query = "SELECT 
            CASE 
                WHEN gwa.gwa <= 1.00 THEN 'Summa Cum Laude'
                WHEN gwa.gwa <= 1.45 THEN 'Magna Cum Laude'
                WHEN gwa.gwa <= 1.75 THEN 'Cum Laude'
                WHEN gwa.gwa <= 2.00 THEN 'Very Good'
                WHEN gwa.gwa <= 2.50 THEN 'Good'
                WHEN gwa.gwa <= 3.00 THEN 'Fair'
                ELSE 'Needs Improvement'
            END as grade_category,
            COUNT(*) as student_count,
            MIN(gwa.gwa) as min_gwa,
            MAX(gwa.gwa) as max_gwa,
            AVG(gwa.gwa) as avg_gwa
          FROM gwa_calculations gwa 
          JOIN users u ON gwa.user_id = u.id 
          WHERE u.department = :department AND u.status = 'active'
          GROUP BY grade_category
          ORDER BY MIN(gwa.gwa)";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$gwa_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Year level performance
$query = "SELECT u.year_level, 
                 COUNT(*) as student_count, 
                 AVG(gwa.gwa) as avg_gwa,
                 MIN(gwa.gwa) as best_gwa,
                 COUNT(CASE WHEN gwa.gwa <= 1.75 THEN 1 END) as honor_eligible
          FROM users u 
          LEFT JOIN gwa_calculations gwa ON u.id = gwa.user_id
          WHERE u.role = 'student' AND u.department = :department AND u.status = 'active'
          GROUP BY u.year_level 
          ORDER BY u.year_level";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$year_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly application trends (last 12 months)
$query = "SELECT 
            DATE_FORMAT(ha.submitted_at, '%Y-%m') as month,
            COUNT(*) as application_count,
            COUNT(CASE WHEN ha.status = 'final_approved' THEN 1 END) as approved_count
          FROM honor_applications ha 
          JOIN users u ON ha.user_id = u.id 
          WHERE u.department = :department 
            AND ha.submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
          GROUP BY DATE_FORMAT(ha.submitted_at, '%Y-%m')
          ORDER BY month";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top performing students
$query = "SELECT u.first_name, u.last_name, u.student_id, u.section, gwa.gwa,
                 COUNT(ha.id) as application_count,
                 COUNT(CASE WHEN ha.status = 'final_approved' THEN 1 END) as approved_count
          FROM users u 
          JOIN gwa_calculations gwa ON u.id = gwa.user_id
          LEFT JOIN honor_applications ha ON u.id = ha.user_id
          WHERE u.department = :department AND u.status = 'active' AND u.role = 'student'
          GROUP BY u.id
          ORDER BY gwa.gwa ASC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$top_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Adviser performance
$query = "SELECT u.first_name, u.last_name,
                 COUNT(DISTINCT gs.id) as processed_submissions,
                 COUNT(DISTINCT ha.id) as reviewed_applications,
                 AVG(DATEDIFF(gs.processed_at, gs.upload_date)) as avg_processing_days
          FROM users u 
          LEFT JOIN grade_submissions gs ON u.id = gs.processed_by
          LEFT JOIN honor_applications ha ON u.id = ha.reviewed_by
          WHERE u.role = 'adviser' AND u.department = :department AND u.status = 'active'
          GROUP BY u.id
          ORDER BY processed_submissions DESC, reviewed_applications DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$adviser_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Reports - CTU Honor System</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                    <a href="rankings.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="trophy" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Rankings
                    </a>
                    <a href="advisers.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="user-check" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Advisers
                    </a>
                    <a href="reports.php" class="bg-purple-50 border-r-2 border-purple-600 text-purple-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="bar-chart-3" class="text-purple-500 mr-3 h-5 w-5"></i>
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
                            <h1 class="text-2xl font-bold text-gray-900">Department Reports</h1>
                            <p class="text-sm text-gray-500">Comprehensive analytics for <?php echo $department; ?> department</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <button onclick="exportReport()" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors">
                            <i data-lucide="download" class="w-4 h-4 inline mr-2"></i>
                            Export Report
                        </button>
                        <button onclick="printReport()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors">
                            <i data-lucide="printer" class="w-4 h-4 inline mr-2"></i>
                            Print
                        </button>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-4 sm:p-6 lg:p-8">
                    <!-- Executive Summary -->
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
                                <div class="w-12 h-12 bg-yellow-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="check-circle" class="w-6 h-6 text-yellow-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Approval Rate</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_applications'] > 0 ? round(($stats['approved_applications'] / $stats['total_applications']) * 100, 1) : 0; ?>%</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
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

                        <!-- Application Trends -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="trending-up" class="w-5 h-5 text-green-600 mr-2"></i>
                                    Application Trends (12 Months)
                                </h3>
                            </div>
                            <div class="p-6">
                                <canvas id="trendsChart" width="400" height="300"></canvas>
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
                                                <div class="text-lg font-bold text-gray-900"><?php echo $year['avg_gwa'] ? formatGWA($year['avg_gwa']) : 'N/A'; ?></div>
                                                <div class="text-xs text-gray-500">Avg GWA</div>
                                            </div>
                                            <div>
                                                <div class="text-lg font-bold text-green-600"><?php echo $year['honor_eligible']; ?></div>
                                                <div class="text-xs text-gray-500">Honor Eligible</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Top Students -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="star" class="w-5 h-5 text-yellow-600 mr-2"></i>
                                    Top Performing Students
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="space-y-4">
                                    <?php foreach (array_slice($top_students, 0, 5) as $index => $student): ?>
                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold <?php echo $index === 0 ? 'bg-yellow-100 text-yellow-800' : ($index === 1 ? 'bg-gray-100 text-gray-800' : ($index === 2 ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800')); ?>">
                                                    #<?php echo $index + 1; ?>
                                                </div>
                                                <div class="ml-3">
                                                    <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($student['student_id'] . ' • ' . $student['section']); ?></p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-lg font-bold text-primary-600"><?php echo formatGWA($student['gwa']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo $student['approved_count']; ?> honors</div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
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
                                                <div>
                                                    <div class="font-bold text-gray-900"><?php echo $adviser['avg_processing_days'] ? round($adviser['avg_processing_days'], 1) : 'N/A'; ?></div>
                                                    <div class="text-gray-500">Avg Days</div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
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

        // Application Trends Chart
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

        function exportReport() {
            // Implement export functionality
            alert('Export functionality will be implemented');
        }

        function printReport() {
            window.print();
        }
    </script>
</body>
</html>
