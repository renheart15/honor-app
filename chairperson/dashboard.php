<?php
require_once '../config/config.php';

requireLogin();

if (!hasRole('chairperson')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();
$notificationManager = new NotificationManager($db);

$chairperson_id = $_SESSION['user_id'];
$department = $_SESSION['department'];

// Get notifications
$notifications = $notificationManager->getUserNotifications($chairperson_id, 5);
$unread_count = $notificationManager->getUnreadCount($chairperson_id);

// Get comprehensive statistics
$stats = [];

// Total students in department
$query = "SELECT COUNT(*) as count FROM users WHERE role = 'student' AND department = :department AND status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$stats['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total advisers in department
$query = "SELECT COUNT(*) as count FROM users WHERE role = 'adviser' AND department = :department AND status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$stats['total_advisers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Honor applications pending approval (using actual database statuses)
$query = "SELECT COUNT(*) as count FROM honor_applications ha
          JOIN users u ON ha.user_id = u.id
          WHERE ha.status IN ('submitted', 'under_review', 'pending') AND u.department = :department";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$stats['pending_applications'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Approved applications (using actual database status)
$query = "SELECT COUNT(*) as count FROM honor_applications ha
          JOIN users u ON ha.user_id = u.id
          WHERE ha.status = 'approved' AND u.department = :department";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$stats['approved_applications'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Students eligible for honors this semester
$query = "SELECT COUNT(*) as count FROM gwa_calculations gwa
          JOIN users u ON gwa.user_id = u.id
          WHERE u.department = :department AND u.status = 'active' AND gwa.gwa <= 1.75";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$stats['eligible_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Recent honor applications
$query = "SELECT ha.*, u.first_name, u.last_name, u.student_id, u.section 
          FROM honor_applications ha 
          JOIN users u ON ha.user_id = u.id 
          WHERE u.department = :department 
          ORDER BY ha.submitted_at DESC 
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$recent_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top performing students (one entry per student with their best GWA)
$query = "SELECT u.first_name, u.last_name, u.student_id, u.section, MIN(gwa.gwa) as gwa
          FROM gwa_calculations gwa
          JOIN users u ON gwa.user_id = u.id
          WHERE u.department = :department AND u.status = 'active'
          GROUP BY u.id, u.first_name, u.last_name, u.student_id, u.section
          ORDER BY gwa ASC
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$top_students = $stmt->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chairperson Dashboard - CTU Honor System</title>
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
                    <a href="dashboard.php" class="bg-purple-50 border-r-2 border-purple-600 text-purple-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="layout-dashboard" class="text-purple-500 mr-3 h-5 w-5"></i>
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
                    <a href="reports.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="bar-chart-3" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
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
                            <h1 class="text-2xl font-bold text-gray-900">Department Overview</h1>
                            <p class="text-sm text-gray-500">Welcome back, <?php echo $_SESSION['first_name']; ?>! Here's your <?php echo $department; ?> department summary.</p>
                        </div>
                    </div>

                    <!-- Notifications & Profile -->
                    <div class="flex items-center space-x-4">
                        <!-- Notifications Dropdown -->
                        <div class="relative">
                            <button type="button" id="notificationButton" class="bg-white p-2 rounded-xl text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 relative">
                                <i data-lucide="bell" class="h-6 w-6"></i>
                                <?php if ($unread_count > 0): ?>
                                    <span class="absolute -top-1 -right-1 h-5 w-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">
                                        <?php echo $unread_count; ?>
                                    </span>
                                <?php endif; ?>
                            </button>

                            <!-- Notification Dropdown Panel -->
                            <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-200 z-50">
                                <div class="p-4 border-b border-gray-200">
                                    <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
                                </div>
                                <div class="max-h-96 overflow-y-auto">
                                    <?php if (empty($notifications)): ?>
                                        <div class="p-8 text-center">
                                            <i data-lucide="bell-off" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                                            <p class="text-sm text-gray-500">No notifications yet</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($notifications as $notification): ?>
                                            <div class="p-4 border-b border-gray-100 hover:bg-gray-50 <?php echo $notification['is_read'] ? '' : 'bg-blue-50'; ?>">
                                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notification['title']); ?></p>
                                                <p class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <p class="text-xs text-gray-400 mt-2"><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($notifications)): ?>
                                    <div class="p-3 border-t border-gray-200 text-center">
                                        <a href="notifications.php" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View all notifications</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- User Profile Dropdown -->
                        <div class="relative">
                            <button type="button" id="profileButton" class="bg-white flex text-sm rounded-xl focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <div class="w-8 h-8 bg-purple-100 rounded-xl flex items-center justify-center">
                                    <i data-lucide="user" class="w-4 h-4 text-purple-600"></i>
                                </div>
                            </button>

                            <!-- Profile Dropdown Menu -->
                            <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-200 z-50">
                                <div class="p-4 border-b border-gray-200">
                                    <p class="text-sm font-semibold text-gray-900"><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></p>
                                    <p class="text-xs text-gray-500"><?php echo $_SESSION['email']; ?></p>
                                </div>
                                <div class="py-2">
                                    <a href="dashboard.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i data-lucide="layout-dashboard" class="w-4 h-4 mr-3 text-gray-400"></i>
                                        Dashboard
                                    </a>
                                    <a href="applications.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i data-lucide="trophy" class="w-4 h-4 mr-3 text-gray-400"></i>
                                        Applications
                                    </a>
                                    <a href="settings.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i data-lucide="settings" class="w-4 h-4 mr-3 text-gray-400"></i>
                                        Settings
                                    </a>
                                </div>
                                <div class="border-t border-gray-200 py-2">
                                    <a href="../logout.php" class="flex items-center px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                                        <i data-lucide="log-out" class="w-4 h-4 mr-3 text-red-500"></i>
                                        Sign Out
                                    </a>
                                </div>
                            </div>
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
                                <div class="w-12 h-12 bg-green-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="user-check" class="w-6 h-6 text-green-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Advisers</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_advisers']; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-yellow-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="clock" class="w-6 h-6 text-yellow-600"></i>
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
                                    <i data-lucide="check-circle" class="w-6 h-6 text-green-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Approved Applications</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['approved_applications'] ?? 0; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Recent Applications -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="trophy" class="w-5 h-5 text-yellow-600 mr-2"></i>
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
                                                        <?php echo ucfirst(str_replace('_', ' ', $application['application_type'])); ?> • 
                                                        GWA: <?php echo formatGWA($application['gwa_achieved']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500">
                                                        <?php echo date('M d, Y', strtotime($application['submitted_at'])); ?>
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

                        <!-- Top Students -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="star" class="w-5 h-5 text-green-600 mr-2"></i>
                                    Top Performing Students
                                </h3>
                            </div>
                            <div class="p-6">
                                <?php if (empty($top_students)): ?>
                                    <div class="text-center py-6">
                                        <i data-lucide="trending-up" class="w-12 h-12 text-gray-300 mx-auto mb-4"></i>
                                        <p class="text-gray-500">No GWA data available</p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <?php foreach ($top_students as $index => $student): ?>
                                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-xl">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold <?php echo $index === 0 ? 'bg-yellow-100 text-yellow-800' : ($index === 1 ? 'bg-gray-100 text-gray-800' : ($index === 2 ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800')); ?>">
                                                        #<?php echo $index + 1; ?>
                                                    </div>
                                                    <div class="ml-3">
                                                        <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($student['student_id'] . ' • ' . formatSectionDisplay($student['section'])); ?></p>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-lg font-bold text-primary-600"><?php echo formatGWA($student['gwa']); ?></div>
                                                    <div class="text-xs text-gray-500">GWA</div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
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
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <a href="applications.php" class="flex items-center p-4 bg-yellow-50 hover:bg-yellow-100 rounded-xl transition-colors group">
                                    <div class="w-10 h-10 bg-yellow-600 rounded-xl flex items-center justify-center group-hover:bg-yellow-700 transition-colors">
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

                                <a href="advisers.php" class="flex items-center p-4 bg-green-50 hover:bg-green-100 rounded-xl transition-colors group">
                                    <div class="w-10 h-10 bg-green-600 rounded-xl flex items-center justify-center group-hover:bg-green-700 transition-colors">
                                        <i data-lucide="user-check" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-semibold text-gray-900">Manage Advisers</p>
                                        <p class="text-xs text-gray-500">Faculty management</p>
                                    </div>
                                </a>

                                <a href="reports.php" class="flex items-center p-4 bg-purple-50 hover:bg-purple-100 rounded-xl transition-colors group">
                                    <div class="w-10 h-10 bg-purple-600 rounded-xl flex items-center justify-center group-hover:bg-purple-700 transition-colors">
                                        <i data-lucide="bar-chart-3" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-semibold text-gray-900">Generate Reports</p>
                                        <p class="text-xs text-gray-500">Analytics & reports</p>
                                    </div>
                                </a>

                                <a href="rankings.php" class="flex items-center p-4 bg-indigo-50 hover:bg-indigo-100 rounded-xl transition-colors group">
                                    <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center group-hover:bg-indigo-700 transition-colors">
                                        <i data-lucide="list" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-semibold text-gray-900">Honor Rankings</p>
                                        <p class="text-xs text-gray-500">Student rankings</p>
                                    </div>
                                </a>

                                <a href="settings.php" class="flex items-center p-4 bg-gray-50 hover:bg-gray-100 rounded-xl transition-colors group">
                                    <div class="w-10 h-10 bg-gray-600 rounded-xl flex items-center justify-center group-hover:bg-gray-700 transition-colors">
                                        <i data-lucide="settings" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-semibold text-gray-900">Settings</p>
                                        <p class="text-xs text-gray-500">System configuration</p>
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

        // Dropdown functionality
        const notificationButton = document.getElementById('notificationButton');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const profileButton = document.getElementById('profileButton');
        const profileDropdown = document.getElementById('profileDropdown');

        // Toggle notification dropdown
        notificationButton.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('hidden');
            profileDropdown.classList.add('hidden');
            lucide.createIcons();
        });

        // Toggle profile dropdown
        profileButton.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
            notificationDropdown.classList.add('hidden');
            lucide.createIcons();
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationButton.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.add('hidden');
            }
            if (!profileButton.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.add('hidden');
            }
        });

        // Close dropdowns on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                notificationDropdown.classList.add('hidden');
                profileDropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
