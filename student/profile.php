<?php
require_once '../config/config.php';

requireLogin();

if (!hasRole('student')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$user_id = $_SESSION['user_id'];
$user_data = $user->findById($user_id);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $update_data = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'department' => $_POST['department'] ?? '',
        'year_level' => $_POST['year_level'] ?? null,
        'section' => $_POST['section'] ?? ''
    ];

    if ($user->updateProfile($user_id, $update_data)) {
        $message = 'Profile updated successfully!';
        $message_type = 'success';
        
        // Update session data
        $_SESSION['first_name'] = $update_data['first_name'];
        $_SESSION['last_name'] = $update_data['last_name'];
        $_SESSION['department'] = $update_data['department'];
        $_SESSION['section'] = $update_data['section'];
        
        // Refresh user data
        $user_data = $user->findById($user_id);
    } else {
        $message = 'Failed to update profile. Please try again.';
        $message_type = 'error';
    }
}

function getOrdinalSuffix($number) {
    $ends = array('th','st','nd','rd','th','th','th','th','th','th');
    if ((($number % 100) >= 11) && (($number % 100) <= 13))
        return 'th';
    else
        return $ends[$number % 10];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - CTU Honor System</title>
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
                    <a href="dashboard.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="layout-dashboard" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
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
                    <a href="profile.php" class="bg-primary-50 border-r-2 border-primary-600 text-primary-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="settings" class="text-primary-500 mr-3 h-5 w-5"></i>
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
                            <h1 class="text-2xl font-bold text-gray-900">Profile Settings</h1>
                            <p class="text-sm text-gray-500">Manage your account information and preferences</p>
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
                        <!-- Profile Form -->
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                                <div class="p-6 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <i data-lucide="user" class="w-5 h-5 text-primary-600 mr-2"></i>
                                        Personal Information
                                    </h3>
                                </div>
                                <div class="p-6">
                                    <form method="POST" class="space-y-6">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label for="first_name" class="block text-sm font-semibold text-gray-700 mb-2">First Name</label>
                                                <input type="text" id="first_name" name="first_name" required
                                                       class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                                                       value="<?php echo htmlspecialchars($user_data['first_name']); ?>">
                                            </div>
                                            <div>
                                                <label for="last_name" class="block text-sm font-semibold text-gray-700 mb-2">Last Name</label>
                                                <input type="text" id="last_name" name="last_name" required
                                                       class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                                                       value="<?php echo htmlspecialchars($user_data['last_name']); ?>">
                                            </div>
                                        </div>

                                        <div>
                                            <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                                            <input type="email" id="email" readonly
                                                   class="block w-full px-3 py-3 border border-gray-300 rounded-xl bg-gray-50 text-gray-500"
                                                   value="<?php echo htmlspecialchars($user_data['email']); ?>">
                                            <p class="mt-1 text-sm text-gray-500">Email cannot be changed. Contact administrator if needed.</p>
                                        </div>

                                        <div>
                                            <label for="student_id" class="block text-sm font-semibold text-gray-700 mb-2">Student ID</label>
                                            <input type="text" id="student_id" readonly
                                                   class="block w-full px-3 py-3 border border-gray-300 rounded-xl bg-gray-50 text-gray-500"
                                                   value="<?php echo htmlspecialchars($user_data['student_id'] ?: 'Not set'); ?>">
                                        </div>

                                        <div>
                                            <label for="department" class="block text-sm font-semibold text-gray-700 mb-2">Department</label>
                                            <select id="department" name="department" 
                                                    class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                                <option value="">Select Department</option>
                                                <option value="Computer Science" <?php echo $user_data['department'] === 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
                                                <option value="Information Technology" <?php echo $user_data['department'] === 'Information Technology' ? 'selected' : ''; ?>>Information Technology</option>
                                                <option value="Engineering" <?php echo $user_data['department'] === 'Engineering' ? 'selected' : ''; ?>>Engineering</option>
                                                <option value="Business Administration" <?php echo $user_data['department'] === 'Business Administration' ? 'selected' : ''; ?>>Business Administration</option>
                                                <option value="Education" <?php echo $user_data['department'] === 'Education' ? 'selected' : ''; ?>>Education</option>
                                            </select>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label for="year_level" class="block text-sm font-semibold text-gray-700 mb-2">Year Level</label>
                                                <select id="year_level" name="year_level" 
                                                        class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                                    <option value="">Select Year</option>
                                                    <option value="1" <?php echo $user_data['year_level'] == 1 ? 'selected' : ''; ?>>1st Year</option>
                                                    <option value="2" <?php echo $user_data['year_level'] == 2 ? 'selected' : ''; ?>>2nd Year</option>
                                                    <option value="3" <?php echo $user_data['year_level'] == 3 ? 'selected' : ''; ?>>3rd Year</option>
                                                    <option value="4" <?php echo $user_data['year_level'] == 4 ? 'selected' : ''; ?>>4th Year</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label for="section" class="block text-sm font-semibold text-gray-700 mb-2">Section</label>
                                                <input type="text" id="section" name="section" 
                                                       class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                                                       placeholder="e.g., CS-3A" value="<?php echo htmlspecialchars($user_data['section'] ?: ''); ?>">
                                            </div>
                                        </div>

                                        <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
                                            <i data-lucide="save" class="w-5 h-5 inline mr-2"></i>
                                            Update Profile
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Summary -->
                        <div class="space-y-6">
                            <!-- Profile Card -->
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                                <div class="p-6 text-center">
                                    <div class="w-20 h-20 bg-gradient-to-br from-primary-500 to-primary-700 rounded-2xl mx-auto mb-4 flex items-center justify-center">
                                        <i data-lucide="user" class="w-10 h-10 text-white"></i>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-1"><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></h3>
                                    <p class="text-sm text-gray-500 mb-2"><?php echo htmlspecialchars($user_data['student_id'] ?: 'Student ID not set'); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($user_data['email']); ?></p>
                                    
                                    <div class="mt-6 pt-6 border-t border-gray-200">
                                        <div class="grid grid-cols-2 gap-4 text-center">
                                            <div>
                                                <div class="text-sm font-semibold text-primary-600"><?php echo htmlspecialchars($user_data['department'] ?: 'Not set'); ?></div>
                                                <div class="text-xs text-gray-500">Department</div>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-green-600"><?php echo $user_data['year_level'] ? $user_data['year_level'] . getOrdinalSuffix($user_data['year_level']) . ' Year' : 'Not set'; ?></div>
                                                <div class="text-xs text-gray-500">Year Level</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Account Status -->
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                                <div class="p-6 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <i data-lucide="shield-check" class="w-5 h-5 text-green-600 mr-2"></i>
                                        Account Status
                                    </h3>
                                </div>
                                <div class="p-6 space-y-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Account Status</span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $user_data['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo ucfirst($user_data['status']); ?>
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Email Verified</span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $user_data['email_verified'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                            <?php echo $user_data['email_verified'] ? 'Verified' : 'Pending'; ?>
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Member Since</span>
                                        <span class="text-sm text-gray-500"><?php echo date('M Y', strtotime($user_data['created_at'])); ?></span>
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
    </script>
</body>
</html>
