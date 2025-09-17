<?php
require_once '../config/config.php';

requireLogin();

if (!hasRole('student')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$student_id = $_SESSION['user_id'];
$department = $_SESSION['department'];

// Get current academic period
$query = "SELECT * FROM academic_periods WHERE is_active = 1 LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$active_period = $stmt->fetch(PDO::FETCH_ASSOC);

$student_rankings = [];
$department_stats = [];

if ($active_period) {
    // Get student's rankings
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
        WHERE hr.user_id = :student_id
        AND hr.academic_period_id = :period_id
        ORDER BY hr.ranking_type, hr.year_level, hr.section
    ";
    
    $stmt = $db->prepare($ranking_query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':period_id', $active_period['id']);
    $stmt->execute();
    $student_rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get department ranking statistics
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
        WHERE hr.department = :department
        AND hr.academic_period_id = :period_id
        AND hr.year_level IS NOT NULL
        AND hr.section IS NOT NULL
        GROUP BY ranking_type, year_level, section
        ORDER BY ranking_type, year_level, section
    ";
    
    $stmt = $db->prepare($stats_query);
    $stmt->bindParam(':department', $department);
    $stmt->bindParam(':period_id', $active_period['id']);
    $stmt->execute();
    $department_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get student's GWA information
$gwa_query = "
    SELECT 
        gwa.*,
        ap.period_name,
        ap.school_year,
        ap.semester
    FROM gwa_calculations gwa
    JOIN academic_periods ap ON gwa.academic_period_id = ap.id
    WHERE gwa.user_id = :student_id
    ORDER BY gwa.calculated_at DESC
    LIMIT 1
";

$stmt = $db->prepare($gwa_query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$student_gwa = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Rankings - CTU Honor System</title>
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
                        <i data-lucide="file-text" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        My Grades
                    </a>
                    <a href="applications.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="trophy" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Honor Applications
                    </a>
                    <a href="rankings.php" class="bg-primary-50 border-r-2 border-primary-600 text-primary-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="bar-chart-3" class="text-primary-500 mr-3 h-5 w-5"></i>
                        My Rankings
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
                            <h1 class="text-2xl font-bold text-gray-900">My Rankings</h1>
                            <p class="text-sm text-gray-500">
                                <?php if ($active_period): ?>
                                    <?php echo htmlspecialchars($active_period['period_name']); ?>
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
                                        <strong>No Active Academic Period:</strong> Rankings are not available when there's no active academic period.
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Current GWA Card -->
                    <?php if ($student_gwa): ?>
                        <div class="bg-gradient-to-r from-primary-500 to-primary-600 rounded-2xl p-6 text-white mb-8">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold mb-2">Current Academic Performance</h2>
                                    <div class="flex items-baseline">
                                        <span class="text-4xl font-bold"><?php echo number_format($student_gwa['gwa'], 3); ?></span>
                                        <span class="ml-2 text-primary-100">GWA</span>
                                    </div>
                                    <div class="mt-2 grid grid-cols-3 gap-4 text-sm">
                                        <div>
                                            <div class="text-primary-100">Total Units</div>
                                            <div class="font-medium"><?php echo $student_gwa['total_units']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-primary-100">Subjects</div>
                                            <div class="font-medium"><?php echo $student_gwa['subjects_count']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-primary-100">Failed</div>
                                            <div class="font-medium"><?php echo $student_gwa['failed_subjects']; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="w-16 h-16 bg-white bg-opacity-20 rounded-xl flex items-center justify-center">
                                        <i data-lucide="trophy" class="w-8 h-8 text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Student Rankings -->
                    <?php if (!empty($student_rankings)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                            <?php foreach ($student_rankings as $ranking): ?>
                                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 <?php echo $ranking['ranking_type'] === 'deans_list' ? 'bg-blue-100 text-blue-600' : ($ranking['ranking_type'] === 'presidents_list' ? 'bg-purple-100 text-purple-600' : 'bg-green-100 text-green-600'); ?> rounded-xl flex items-center justify-center">
                                                <i data-lucide="award" class="w-5 h-5"></i>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-2xl font-bold text-gray-900">#<?php echo $ranking['rank_position']; ?></div>
                                            <div class="text-xs text-gray-500">of <?php echo $ranking['total_students']; ?></div>
                                        </div>
                                    </div>
                                    
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                        <?php echo ucfirst(str_replace('_', ' ', $ranking['ranking_type'])); ?>
                                    </h3>
                                    
                                    <div class="space-y-2 text-sm">
                                        <?php if ($ranking['year_level'] && $ranking['section']): ?>
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Section:</span>
                                                <span class="font-medium">Year <?php echo $ranking['year_level']; ?> - <?php echo htmlspecialchars($ranking['section']); ?></span>
                                            </div>
                                        <?php elseif ($ranking['year_level']): ?>
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Scope:</span>
                                                <span class="font-medium">Year <?php echo $ranking['year_level']; ?> Department-wide</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Scope:</span>
                                                <span class="font-medium">Department-wide</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="flex justify-between">
                                            <span class="text-gray-500">GWA:</span>
                                            <span class="font-bold text-primary-600"><?php echo number_format($ranking['gwa'], 3); ?></span>
                                        </div>
                                        
                                        <?php if ($ranking['percentile']): ?>
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Percentile:</span>
                                                <span class="font-medium"><?php echo number_format($ranking['percentile'], 1); ?>%</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mt-4 pt-4 border-t border-gray-100">
                                        <div class="flex items-center text-xs text-gray-500">
                                            <i data-lucide="calendar" class="w-3 h-3 mr-1"></i>
                                            <?php echo date('M d, Y', strtotime($ranking['generated_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($active_period): ?>
                        <div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-200 mb-8">
                            <i data-lucide="trophy" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                            <h3 class="text-xl font-medium text-gray-900 mb-2">No Rankings Available</h3>
                            <p class="text-gray-500 mb-4">
                                You don't have any honor rankings for the current academic period. Rankings are generated based on your GWA performance.
                            </p>
                            <div class="text-sm text-gray-400">
                                Rankings criteria:
                                <ul class="mt-2 space-y-1">
                                    <li>• Dean's List: GWA ≤ 1.75, no grade above 2.5, no failed subjects</li>
                                    <li>• President's List: GWA ≤ 1.25, no grade above 2.5, no failed subjects</li>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Department Statistics -->
                    <?php if (!empty($department_stats)): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($department); ?> Department Rankings Overview</h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <?php foreach ($department_stats as $stat): ?>
                                        <div class="bg-gray-50 rounded-xl p-4">
                                            <div class="flex items-center justify-between mb-2">
                                                <h4 class="text-sm font-semibold text-gray-900">
                                                    <?php echo ucfirst(str_replace('_', ' ', $stat['ranking_type'])); ?>
                                                </h4>
                                                <span class="text-xs <?php echo $stat['ranking_type'] === 'deans_list' ? 'bg-blue-100 text-blue-800' : ($stat['ranking_type'] === 'presidents_list' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'); ?> px-2 py-1 rounded-full">
                                                    Year <?php echo $stat['year_level']; ?> - <?php echo htmlspecialchars($stat['section']); ?>
                                                </span>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2 text-xs">
                                                <div>
                                                    <div class="text-gray-500">Total Students</div>
                                                    <div class="font-medium"><?php echo $stat['total_students']; ?></div>
                                                </div>
                                                <div>
                                                    <div class="text-gray-500">Best GWA</div>
                                                    <div class="font-medium"><?php echo number_format($stat['best_gwa'], 3); ?></div>
                                                </div>
                                                <div>
                                                    <div class="text-gray-500">Average GWA</div>
                                                    <div class="font-medium"><?php echo number_format($stat['average_gwa'], 3); ?></div>
                                                </div>
                                                <div>
                                                    <div class="text-gray-500">Lowest GWA</div>
                                                    <div class="font-medium"><?php echo number_format($stat['lowest_gwa'], 3); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>