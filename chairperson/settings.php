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

$message = '';
$message_type = '';

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (!empty($first_name) && !empty($last_name) && !empty($email)) {
            $query = "UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':user_id', $chairperson_id);
            
            if ($stmt->execute()) {
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                $_SESSION['email'] = $email;
                $message = 'Profile updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to update profile.';
                $message_type = 'error';
            }
        } else {
            $message = 'All fields are required.';
            $message_type = 'error';
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if ($new_password !== $confirm_password) {
            $message = 'New passwords do not match.';
            $message_type = 'error';
        } elseif (strlen($new_password) < 6) {
            $message = 'Password must be at least 6 characters long.';
            $message_type = 'error';
        } else {
            // Verify current password
            $query = "SELECT password FROM users WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $chairperson_id);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $query = "UPDATE users SET password = :password WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':user_id', $chairperson_id);
                
                if ($stmt->execute()) {
                    $message = 'Password changed successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to change password.';
                    $message_type = 'error';
                }
            } else {
                $message = 'Current password is incorrect.';
                $message_type = 'error';
            }
        }
    }
}

// Get current user data
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $chairperson_id);
$stmt->execute();
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Get system statistics for the department
$query = "SELECT
            (SELECT COUNT(*) FROM users WHERE role = 'student' AND department = :department AND status = 'active') as total_students,
            (SELECT COUNT(*) FROM users WHERE role = 'adviser' AND department = :department AND status = 'active') as total_advisers,
            (SELECT COUNT(*) FROM honor_applications ha JOIN users u ON ha.user_id = u.id WHERE u.department = :department) as total_applications,
            (SELECT COUNT(*) FROM grade_submissions gs JOIN users u ON gs.user_id = u.id WHERE u.department = :department) as total_submissions";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$system_stats = $stmt->fetch(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - CTU Honor System</title>
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
                    <a href="reports.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="bar-chart-3" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Reports
                    </a>
                    <a href="settings.php" class="bg-purple-50 border-r-2 border-purple-600 text-purple-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="settings" class="text-purple-500 mr-3 h-5 w-5"></i>
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
                            <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
                            <p class="text-sm text-gray-500">Manage your account and system preferences</p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-4 sm:p-6 lg:p-8">
                    <?php if ($message): ?>
                        <div class="mb-6 p-4 rounded-xl border <?php echo $message_type === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'; ?>">
                            <div class="flex items-center">
                                <i data-lucide="<?php echo $message_type === 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5 <?php echo $message_type === 'success' ? 'text-green-500' : 'text-red-500'; ?> mr-2"></i>
                                <span class="<?php echo $message_type === 'success' ? 'text-green-700' : 'text-red-700'; ?> text-sm"><?php echo htmlspecialchars($message); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <!-- Profile Settings -->
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                                <div class="p-6 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <i data-lucide="user" class="w-5 h-5 text-primary-600 mr-2"></i>
                                        Profile Information
                                    </h3>
                                </div>
                                <div class="p-6">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_profile">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label for="first_name" class="block text-sm font-semibold text-gray-700 mb-2">First Name</label>
                                                <input type="text" id="first_name" name="first_name" 
                                                       value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required
                                                       class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                            </div>
                                            <div>
                                                <label for="last_name" class="block text-sm font-semibold text-gray-700 mb-2">Last Name</label>
                                                <input type="text" id="last_name" name="last_name" 
                                                       value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required
                                                       class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                            </div>
                                            <div class="md:col-span-2">
                                                <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                                                <input type="email" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($user_data['email']); ?>" required
                                                       class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-semibold text-gray-700 mb-2">Department</label>
                                                <input type="text" value="<?php echo htmlspecialchars($user_data['department']); ?>" disabled
                                                       class="block w-full px-3 py-3 border border-gray-300 rounded-xl bg-gray-50 text-gray-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-semibold text-gray-700 mb-2">Role</label>
                                                <input type="text" value="Chairperson" disabled
                                                       class="block w-full px-3 py-3 border border-gray-300 rounded-xl bg-gray-50 text-gray-500">
                                            </div>
                                        </div>
                                        <div class="mt-6">
                                            <button type="submit" 
                                                    class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-xl font-medium transition-colors">
                                                <i data-lucide="save" class="w-4 h-4 inline mr-2"></i>
                                                Update Profile
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Change Password -->
                            <div class="mt-8 bg-white rounded-2xl shadow-sm border border-gray-200">
                                <div class="p-6 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <i data-lucide="lock" class="w-5 h-5 text-red-600 mr-2"></i>
                                        Change Password
                                    </h3>
                                </div>
                                <div class="p-6">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="change_password">
                                        <div class="space-y-6">
                                            <div>
                                                <label for="current_password" class="block text-sm font-semibold text-gray-700 mb-2">Current Password</label>
                                                <input type="password" id="current_password" name="current_password" required
                                                       class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                            </div>
                                            <div>
                                                <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                                                <input type="password" id="new_password" name="new_password" required
                                                       class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                            </div>
                                            <div>
                                                <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password</label>
                                                <input type="password" id="confirm_password" name="confirm_password" required
                                                       class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                            </div>
                                        </div>
                                        <div class="mt-6">
                                            <button type="submit" 
                                                    class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-xl font-medium transition-colors">
                                                <i data-lucide="key" class="w-4 h-4 inline mr-2"></i>
                                                Change Password
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- System Information -->
                        <div>
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                                <div class="p-6 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <i data-lucide="info" class="w-5 h-5 text-blue-600 mr-2"></i>
                                        System Information
                                    </h3>
                                </div>
                                <div class="p-6">
                                    <div class="space-y-4">
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-500">Department:</span>
                                            <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($department); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-500">Total Students:</span>
                                            <span class="text-sm font-bold text-blue-600"><?php echo $system_stats['total_students']; ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-500">Total Advisers:</span>
                                            <span class="text-sm font-bold text-green-600"><?php echo $system_stats['total_advisers']; ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-500">Total Applications:</span>
                                            <span class="text-sm font-bold text-purple-600"><?php echo $system_stats['total_applications']; ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-500">Total Submissions:</span>
                                            <span class="text-sm font-bold text-yellow-600"><?php echo $system_stats['total_submissions']; ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-500">Account Created:</span>
                                            <span class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($user_data['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="mt-6 bg-white rounded-2xl shadow-sm border border-gray-200">
                                <div class="p-6 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <i data-lucide="zap" class="w-5 h-5 text-primary-600 mr-2"></i>
                                        Quick Actions
                                    </h3>
                                </div>
                                <div class="p-6">
                                    <div class="space-y-3">
                                        <button onclick="exportData()" class="w-full flex items-center justify-center px-4 py-3 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-xl transition-colors">
                                            <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                                            Export Department Data
                                        </button>
                                        <button onclick="generateReport()" class="w-full flex items-center justify-center px-4 py-3 bg-green-50 hover:bg-green-100 text-green-700 rounded-xl transition-colors">
                                            <i data-lucide="file-text" class="w-4 h-4 mr-2"></i>
                                            Generate Report
                                        </button>
                                        <button onclick="backupData()" class="w-full flex items-center justify-center px-4 py-3 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-xl transition-colors">
                                            <i data-lucide="database" class="w-4 h-4 mr-2"></i>
                                            Backup Data
                                        </button>
                                        <button onclick="systemMaintenance()" class="w-full flex items-center justify-center px-4 py-3 bg-yellow-50 hover:bg-yellow-100 text-yellow-700 rounded-xl transition-colors">
                                            <i data-lucide="wrench" class="w-4 h-4 mr-2"></i>
                                            System Maintenance
                                        </button>
                                    </div>
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

        function exportData() {
            alert('Export data functionality will be implemented');
        }

        function generateReport() {
            window.location.href = 'reports.php';
        }

        function backupData() {
            alert('Backup data functionality will be implemented');
        }

        function systemMaintenance() {
            alert('System maintenance functionality will be implemented');
        }


        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;

            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

    </script>
</body>
</html>
