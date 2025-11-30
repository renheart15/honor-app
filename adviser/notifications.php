<?php
require_once '../config/config.php';

requireLogin();

if (!hasRole('adviser')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();
$notificationManager = new NotificationManager($db);

$user_id = $_SESSION['user_id'];

// Handle mark as read action
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $notification_id = $_POST['notification_id'] ?? 0;
        $notificationManager->markAsRead($notification_id, $user_id);
    } elseif ($action === 'mark_all_read') {
        $notificationManager->markAllAsRead($user_id);
    } elseif ($action === 'delete') {
        $notification_id = $_POST['notification_id'] ?? 0;
        $notificationManager->deleteNotification($notification_id, $user_id);
    }

    // Redirect to prevent form resubmission
    header("Location: notifications.php");
    exit();
}

// Get all notifications
$notifications = $notificationManager->getUserNotifications($user_id, 100);
$unread_count = $notificationManager->getUnreadCount($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - CTU Honor System</title>
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
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
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
                            <i data-lucide="user" class="w-5 h-5 text-green-600"></i>
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
                        Applications
                    </a>
                    <a href="students.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="users" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Students
                    </a>
                    <a href="rankings.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="award" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Honor Rankings
                    </a>
                    <a href="reports.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="file-bar-chart" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Reports
                    </a>
                    <a href="notifications.php" class="bg-green-50 border-r-2 border-green-600 text-green-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="bell" class="text-green-500 mr-3 h-5 w-5"></i>
                        Notifications
                        <?php if ($unread_count > 0): ?>
                            <span class="ml-auto bg-green-600 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?php echo $unread_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <a href="profile.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="settings" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Profile
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
                            <h1 class="text-2xl font-bold text-gray-900">Notifications</h1>
                            <p class="text-sm text-gray-500">View and manage your notifications</p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <?php if ($unread_count > 0): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors">
                                    <i data-lucide="check-check" class="w-4 h-4 inline mr-2"></i>
                                    Mark All as Read
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php include 'includes/header.php'; ?>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-4 sm:p-6 lg:p-8">
                    <?php if (empty($notifications)): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center">
                            <i data-lucide="bell-off" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No notifications yet</h3>
                            <p class="text-gray-500">You're all caught up! Check back later for updates.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow <?php echo $notification['is_read'] ? '' : 'border-l-4 border-l-green-500'; ?>">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center mb-2">
                                                <?php
                                                $icon_class = 'text-blue-600';
                                                $icon_name = 'bell';

                                                if ($notification['type'] === 'success') {
                                                    $icon_class = 'text-green-600';
                                                    $icon_name = 'check-circle';
                                                } elseif ($notification['type'] === 'error') {
                                                    $icon_class = 'text-red-600';
                                                    $icon_name = 'x-circle';
                                                } elseif ($notification['type'] === 'warning') {
                                                    $icon_class = 'text-yellow-600';
                                                    $icon_name = 'alert-triangle';
                                                }
                                                ?>
                                                <i data-lucide="<?php echo $icon_name; ?>" class="w-5 h-5 <?php echo $icon_class; ?> mr-2"></i>
                                                <h3 class="text-base font-semibold text-gray-900"><?php echo htmlspecialchars($notification['title']); ?></h3>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        New
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <p class="text-xs text-gray-400">
                                                <i data-lucide="clock" class="w-3 h-3 inline mr-1"></i>
                                                <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                            </p>
                                        </div>
                                        <div class="ml-4 flex space-x-2">
                                            <?php if (!$notification['is_read']): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="mark_read">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <button type="submit" class="text-green-600 hover:text-green-700 p-2 rounded-lg hover:bg-green-50" title="Mark as read">
                                                        <i data-lucide="check" class="w-4 h-4"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this notification?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-700 p-2 rounded-lg hover:bg-red-50" title="Delete">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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
