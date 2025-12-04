<?php
require_once '../config/config.php';

requireLogin();

if (!hasRole('student')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();
$gradeProcessor = new GradeProcessor($db);
$notificationManager = new NotificationManager($db);

// Get ALL student's grades across all periods for dashboard overview
$user_id = $_SESSION['user_id'];

// Get all processed grades for the student
$all_grades_query = "
    SELECT g.*, ap.period_name, gs.academic_period_id
    FROM grades g
    JOIN grade_submissions gs ON g.submission_id = gs.id
    JOIN academic_periods ap ON gs.academic_period_id = ap.id
    WHERE gs.user_id = :user_id
    AND gs.status = 'processed'
    ORDER BY ap.school_year DESC, ap.semester DESC, g.subject_code ASC
";

$all_grades_stmt = $db->prepare($all_grades_query);
$all_grades_stmt->bindParam(':user_id', $user_id);
$all_grades_stmt->execute();
$grades = $all_grades_stmt->fetchAll(PDO::FETCH_ASSOC);

// GWA data is now calculated from all grades below

// Calculate overall GWA (across all periods) for dashboard display
$overall_gwa_data = null;
$all_grades_query = "
    SELECT g.*, g.semester_taken
    FROM grades g
    JOIN grade_submissions gs ON g.submission_id = gs.id
    JOIN academic_periods ap ON gs.academic_period_id = ap.id
    WHERE gs.user_id = :user_id
    AND gs.status = 'processed'
";

$all_grades_stmt = $db->prepare($all_grades_query);
$all_grades_stmt->bindParam(':user_id', $user_id);
$all_grades_stmt->execute();
$all_grades = $all_grades_stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($all_grades)) {
    $total_grade_points = 0;
    $total_units = 0;
    $periods_set = [];

    foreach ($all_grades as $grade) {
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
        $overall_gwa_calculated = floor($overall_gwa_exact * 100) / 100; // Apply truncation
        $overall_gwa_data = [
            'gwa' => $overall_gwa_calculated,
            'total_units' => $total_units,
            'periods_count' => count($periods_set)
        ];
    }
}

$notifications = $notificationManager->getUserNotifications($user_id, 5);
$unread_count = $notificationManager->getUnreadCount($user_id);

// Check eligibility for honors
$eligible_for_deans = false;
$user_data = (new User($db))->findById($user_id);
$user_year_level = $user_data['year_level'] ?? null;
$eligible_for_latin = false;

if ($overall_gwa_data && $user_year_level == 4) {
    $gwa = $overall_gwa_data['gwa'];
    if ($gwa >= 1.00 && $gwa <= 1.25) {
        $eligible_for_latin = true;
    }
}

if ($overall_gwa_data) {
    $gwa = $overall_gwa_data['gwa'];

    // Only consider GWA within the valid range (1.00 - 5.00)
    if ($gwa >= 1.00 && $gwa <= 1.75) {
        $eligible_for_deans = $gwa <= 1.75;
        $eligible_for_latin = $gwa <= 1.25;
    } else {
        // Optional: handle invalid GWA case
        $eligible_for_deans = false;
        $eligible_for_presidents = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CTU Honor System</title>
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
                    <img src="../img/cebu-technological-university-seeklogo.png" alt="CTU Logo" class="w-8 h-8">
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
                    <a href="dashboard.php" class="bg-primary-50 border-r-2 border-primary-600 text-primary-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="layout-dashboard" class="text-primary-500 mr-3 h-5 w-5"></i>
                        Dashboard
                    </a>
                    <a href="grades.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="bar-chart-3" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        My Grades
                    </a>
                    <a href="applications.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="trophy" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Applications
                    </a>
                    <a href="rankings.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="award" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Honor Rankings
                    </a>
                    <a href="reports.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="file-bar-chart" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
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
                        <button type="button" class="md:hidden -ml-0.5 -mt-0.5 h-12 w-12 inline-flex items-center justify-center rounded-xl text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500">
                            <i data-lucide="menu" class="h-6 w-6"></i>
                        </button>
                        <div class="ml-4 md:ml-0">
                            <h1 class="text-2xl font-bold text-gray-900">Welcome back, <?php echo $_SESSION['first_name']; ?>!</h1>
                            <p class="text-sm text-gray-500">Here's your academic overview</p>
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
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <!-- GWA Card -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-primary-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="calculator" class="w-6 h-6 text-primary-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Overall GWA</p>
                                    <p class="text-2xl font-bold text-gray-900">
                                        <?php echo $overall_gwa_data ? formatGWA($overall_gwa_data['gwa']) : 'N/A'; ?>
                                    </p>
                                    <?php if ($overall_gwa_data): ?>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <?php echo $overall_gwa_data['periods_count']; ?> semesters • <?php echo number_format($overall_gwa_data['total_units'], 0); ?> total units
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Subjects Card -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-green-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="book-open" class="w-6 h-6 text-green-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Subjects</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo count($grades); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Total Units -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-yellow-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="award" class="w-6 h-6 text-yellow-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Units</p>
                                    <p class="text-2xl font-bold text-gray-900">
                                        <?php echo $overall_gwa_data ? number_format($overall_gwa_data['total_units'], 0) : '0'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Year and Section -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-purple-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="crown" class="w-6 h-6 text-purple-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Year and Section</p>
                                    <p class="text-2xl font-bold text-gray-900">
                                        <?php echo $user_data['year_level'] ? 'Year ' . $user_data['year_level'] : 'N/A'; ?><?php echo $user_data['section'] ? ' - ' . htmlspecialchars($user_data['section']) : ''; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Quick Actions -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="zap" class="w-5 h-5 text-primary-600 mr-2"></i>
                                    Quick Actions
                                </h3>
                            </div>
                            <div class="p-6 space-y-4">
                                <a href="upload-grades.php" class="flex items-center p-4 bg-primary-50 hover:bg-primary-100 rounded-xl transition-colors group">
                                    <div class="w-10 h-10 bg-primary-600 rounded-xl flex items-center justify-center group-hover:bg-primary-700 transition-colors">
                                        <i data-lucide="upload" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-semibold text-gray-900">Upload Grade Report</p>
                                        <p class="text-xs text-gray-500">Submit your latest grades</p>
                                    </div>
                                </a>

                                <?php if ($eligible_for_deans): ?>
                                <a href="applications.php" class="flex items-center p-4 bg-green-50 hover:bg-green-100 rounded-xl transition-colors group">
                                    <div class="w-10 h-10 bg-green-600 rounded-xl flex items-center justify-center group-hover:bg-green-700 transition-colors">
                                        <i data-lucide="trophy" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-semibold text-gray-900">Apply for Dean's List</p>
                                        <p class="text-xs text-gray-500">You're eligible to apply!</p>
                                    </div>
                                </a>
                                <?php endif; ?>

                                <a href="download-gwa.php" class="flex items-center p-4 bg-gray-50 hover:bg-gray-100 rounded-xl transition-colors group">
                                    <div class="w-10 h-10 bg-gray-600 rounded-xl flex items-center justify-center group-hover:bg-gray-700 transition-colors">
                                        <i data-lucide="download" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-semibold text-gray-900">Download GWA Report</p>
                                        <p class="text-xs text-gray-500">Get your official report</p>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- Recent Grades -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="bar-chart-3" class="w-5 h-5 text-green-600 mr-2"></i>
                                    Recent Grades
                                </h3>
                            </div>
                            <div class="p-6">
                                <?php if (empty($grades)): ?>
                                    <div class="text-center py-8">
                                        <i data-lucide="inbox" class="w-12 h-12 text-gray-300 mx-auto mb-4"></i>
                                        <h4 class="text-lg font-medium text-gray-900 mb-2">No grades uploaded yet</h4>
                                        <p class="text-gray-500 mb-4">Upload your grade report to see your subjects and grades here.</p>
                                        <a href="upload-grades.php" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors">
                                            Upload Now
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <?php foreach (array_slice($grades, 0, 5) as $grade): ?>
                                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($grade['subject_code']); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($grade['subject_name']); ?></p>
                                                </div>
                                                <div class="text-right">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $grade['grade'] <= 1.5 ? 'bg-green-100 text-green-800' : ($grade['grade'] <= 2.0 ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                                                        <?php echo $grade['grade']; ?>
                                                    </span>
                                                    <p class="text-xs text-gray-500 mt-1"><?php echo $grade['units']; ?> units</p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-6 text-center">
                                        <a href="grades.php" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                                            View All Grades →
                                        </a>
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
    </script>
</body>
</html>
