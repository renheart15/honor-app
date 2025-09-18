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

// Get statistics for adviser's assigned section only
$stats = [];

// Total students in adviser's section
if ($adviser_section) {
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'student' AND department = :department AND section = :section AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->bindParam(':section', $adviser_section);
} else {
    // If no section assigned, show department-wide
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'student' AND department = :department AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
}
$stmt->execute();
$stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Pending grade submissions from adviser's section
if ($adviser_section) {
    $query = "SELECT COUNT(*) as count FROM grade_submissions gs 
              JOIN users u ON gs.user_id = u.id 
              WHERE gs.status = 'pending' AND u.department = :department AND u.section = :section";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->bindParam(':section', $adviser_section);
} else {
    $query = "SELECT COUNT(*) as count FROM grade_submissions gs 
              JOIN users u ON gs.user_id = u.id 
              WHERE gs.status = 'pending' AND u.department = :department";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
}
$stmt->execute();
$stats['pending_submissions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Honor applications to review from adviser's section
if ($adviser_section) {
    $query = "SELECT COUNT(*) as count FROM honor_applications ha 
              JOIN users u ON ha.user_id = u.id 
              WHERE ha.status = 'submitted' AND u.department = :department AND u.section = :section";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->bindParam(':section', $adviser_section);
} else {
    $query = "SELECT COUNT(*) as count FROM honor_applications ha 
              JOIN users u ON ha.user_id = u.id 
              WHERE ha.status = 'submitted' AND u.department = :department";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
}
$stmt->execute();
$stats['pending_applications'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Students with GWA in adviser's section
if ($adviser_section) {
    $query = "SELECT COUNT(DISTINCT gwa.user_id) as count FROM gwa_calculations gwa 
              JOIN users u ON gwa.user_id = u.id 
              WHERE u.department = :department AND u.section = :section AND u.status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->bindParam(':section', $adviser_section);
} else {
    $query = "SELECT COUNT(DISTINCT gwa.user_id) as count FROM gwa_calculations gwa 
              JOIN users u ON gwa.user_id = u.id 
              WHERE u.department = :department AND u.status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
}
$stmt->execute();
$stats['students_with_gwa'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Recent submissions from adviser's section
if ($adviser_section) {
    $query = "SELECT gs.*, u.first_name, u.last_name, u.student_id, u.section
              FROM grade_submissions gs
              JOIN users u ON gs.user_id = u.id
              WHERE u.department = :department AND u.section = :section
              ORDER BY gs.upload_date DESC
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->bindParam(':section', $adviser_section);
} else {
    $query = "SELECT gs.*, u.first_name, u.last_name, u.student_id, u.section
              FROM grade_submissions gs
              JOIN users u ON gs.user_id = u.id
              WHERE u.department = :department
              ORDER BY gs.upload_date DESC
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
}
$stmt->execute();
$recent_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent applications from adviser's section
if ($adviser_section) {
    $query = "SELECT ha.*, u.first_name, u.last_name, u.student_id, u.section
              FROM honor_applications ha
              JOIN users u ON ha.user_id = u.id
              WHERE u.department = :department AND u.section = :section
              ORDER BY ha.submitted_at DESC
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->bindParam(':section', $adviser_section);
} else {
    $query = "SELECT ha.*, u.first_name, u.last_name, u.student_id, u.section
              FROM honor_applications ha
              JOIN users u ON ha.user_id = u.id
              WHERE u.department = :department
              ORDER BY ha.submitted_at DESC
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
}
$stmt->execute();
$recent_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adviser Dashboard - CTU Honor System</title>
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
                    <a href="dashboard.php" class="bg-green-50 border-r-2 border-green-600 text-green-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="layout-dashboard" class="text-green-500 mr-3 h-5 w-5"></i>
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
                            <h1 class="text-2xl font-bold text-gray-900">Adviser Dashboard</h1>
                            <?php if ($adviser_section): ?>
                                <p class="text-sm text-gray-500">Welcome back, <?php echo $_SESSION['first_name']; ?>! Managing section <span class="font-semibold text-primary-600"><?php echo htmlspecialchars($adviser_section); ?></span> <?php echo $adviser_year_level ? '(Year ' . $adviser_year_level . ')' : ''; ?> in <?php echo $department; ?>.</p>
                            <?php else: ?>
                                <div class="mt-2 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                                    <div class="flex items-center">
                                        <i data-lucide="alert-circle" class="w-4 h-4 text-orange-600 mr-2"></i>
                                        <span class="text-sm font-medium text-orange-800">Section Assignment Pending</span>
                                    </div>
                                    <p class="text-sm text-orange-700 mt-1">Your section assignment is pending. Contact your department chairperson to get assigned to a section. Currently showing department-wide data.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-4 sm:p-6 lg:p-8">
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
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

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-yellow-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="clock" class="w-6 h-6 text-yellow-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Pending Submissions</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['pending_submissions']; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-purple-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="trophy" class="w-6 h-6 text-purple-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Pending Applications</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['pending_applications']; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-green-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="trending-up" class="w-6 h-6 text-green-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Students with GWA</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['students_with_gwa']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Recent Submissions -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="file-text" class="w-5 h-5 text-yellow-600 mr-2"></i>
                                    Recent Grade Submissions
                                </h3>
                            </div>
                            <div class="p-6">
                                <?php if (empty($recent_submissions)): ?>
                                    <div class="text-center py-6">
                                        <i data-lucide="inbox" class="w-12 h-12 text-gray-300 mx-auto mb-4"></i>
                                        <p class="text-gray-500">No recent submissions</p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <?php foreach ($recent_submissions as $submission): ?>
                                            <div class="flex justify-between items-start p-3 bg-gray-50 rounded-xl">
                                                <div>
                                                    <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?></h4>
                                                    <p class="text-sm text-gray-600">
                                                        <?php echo htmlspecialchars($submission['student_id']); ?> • <?php echo htmlspecialchars($submission['section']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500">
                                                        <?php echo date('M d, Y g:i A', strtotime($submission['upload_date'])); ?>
                                                    </p>
                                                </div>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getStatusBadge($submission['status']) === 'success' ? 'bg-green-100 text-green-800' : ($submission['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                                    <?php echo ucfirst($submission['status']); ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-6 text-center">
                                        <a href="submissions.php" class="text-primary-600 hover:text-primary-700 text-sm font-medium">View All Submissions →</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Applications -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="trophy" class="w-5 h-5 text-purple-600 mr-2"></i>
                                    Recent Honor Applications
                                </h3>
                            </div>
                            <div class="p-6">
                                <?php if (empty($recent_applications)): ?>
                                    <div class="text-center py-6">
                                        <i data-lucide="inbox" class="w-12 h-12 text-gray-300 mx-auto mb-4"></i>
                                        <p class="text-gray-500">No recent applications</p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <?php foreach ($recent_applications as $application): ?>
                                            <div class="flex justify-between items-start p-3 bg-gray-50 rounded-xl">
                                                <div>
                                                    <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></h4>
                                                    <p class="text-sm text-gray-600">
                                                        <?php echo ucfirst(str_replace('_', ' ', $application['application_type'])); ?> • GWA: <?php echo formatGWA($application['gwa_achieved']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500">
                                                        <?php echo date('M d, Y g:i A', strtotime($application['submitted_at'])); ?>
                                                    </p>
                                                </div>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getStatusBadge($application['status']) === 'success' ? 'bg-green-100 text-green-800' : ($application['status'] === 'submitted' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $application['status'])); ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-6 text-center">
                                        <a href="applications.php" class="text-primary-600 hover:text-primary-700 text-sm font-medium">View All Applications →</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="mt-8 bg-white rounded-2xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i data-lucide="zap" class="w-5 h-5 text-primary-600 mr-2"></i>
                                Quick Actions
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <a href="submissions.php" class="flex items-center p-4 bg-yellow-50 hover:bg-yellow-100 rounded-xl transition-colors group">
                                    <div class="w-10 h-10 bg-yellow-600 rounded-xl flex items-center justify-center group-hover:bg-yellow-700 transition-colors">
                                        <i data-lucide="file-text" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-semibold text-gray-900">Review Submissions</p>
                                        <p class="text-xs text-gray-500">Process grade uploads</p>
                                    </div>
                                </a>

                                <a href="applications.php" class="flex items-center p-4 bg-purple-50 hover:bg-purple-100 rounded-xl transition-colors group">
                                    <div class="w-10 h-10 bg-purple-600 rounded-xl flex items-center justify-center group-hover:bg-purple-700 transition-colors">
                                        <i data-lucide="trophy" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-semibold text-gray-900">Review Applications</p>
                                        <p class="text-xs text-gray-500">Honor applications</p>
                                    </div>
                                </a>

                                <a href="students.php" class="flex items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-xl transition-colors group">
                                    <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center group-hover:bg-blue-700 transition-colors">
                                        <i data-lucide="users" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-semibold text-gray-900">View Students</p>
                                        <p class="text-xs text-gray-500">Student management</p>
                                    </div>
                                </a>

                                <a href="reports.php" class="flex items-center p-4 bg-green-50 hover:bg-green-100 rounded-xl transition-colors group">
                                    <div class="w-10 h-10 bg-green-600 rounded-xl flex items-center justify-center group-hover:bg-green-700 transition-colors">
                                        <i data-lucide="bar-chart-3" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-semibold text-gray-900">Generate Reports</p>
                                        <p class="text-xs text-gray-500">Analytics & reports</p>
                                    </div>
                                </a>
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
