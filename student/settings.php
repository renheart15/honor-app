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

// Calculate overall GWA from all grades (same as adviser/students.php)
$gwa_query = "
    SELECT
        CASE WHEN SUM(g.units) > 0 THEN
            SUM(g.units * g.grade) / SUM(g.units)
        ELSE NULL END as gwa,
        SUM(g.units) as total_units,
        COUNT(*) as subjects_count
    FROM grades g
    JOIN grade_submissions gs ON g.submission_id = gs.id
    WHERE gs.user_id = :user_id
    AND gs.status = 'processed'
    AND g.grade > 0.00
    AND NOT (UPPER(g.subject_name) LIKE '%NSTP%' OR UPPER(g.subject_name) LIKE '%NATIONAL SERVICE TRAINING%')
";
$gwa_stmt = $db->prepare($gwa_query);
$gwa_stmt->bindParam(':user_id', $user_id);
$gwa_stmt->execute();
$gwa_data = $gwa_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate honor eligibility (same logic as adviser/students.php)
$honor_status = 'Not Eligible';
$honor_color = 'gray';
$honor_criteria = '';

if ($gwa_data['gwa']) {
    $gwa = floatval($gwa_data['gwa']);

    // Check for approved Latin honors first
    $latin_honors_query = "
        SELECT COUNT(*) as has_latin_honors
        FROM honor_applications ha
        WHERE ha.user_id = :user_id
        AND ha.application_type IN ('cum_laude', 'magna_cum_laude', 'summa_cum_laude')
        AND ha.status = 'final_approved'
    ";
    $latin_stmt = $db->prepare($latin_honors_query);
    $latin_stmt->bindParam(':user_id', $user_id);
    $latin_stmt->execute();
    $latin_result = $latin_stmt->fetch(PDO::FETCH_ASSOC);

    if ($latin_result['has_latin_honors'] > 0) {
        $honor_status = 'Latin Honors';
        $honor_color = 'yellow';
        $honor_criteria = 'Approved Latin Honors application';
    } else {
        // Check for grades above 2.5
        $grade_check_query = "SELECT COUNT(*) as count
                              FROM grades g
                              JOIN grade_submissions gs ON g.submission_id = gs.id
                              WHERE gs.user_id = :user_id
                              AND g.grade > 2.5
                              AND gs.status = 'processed'";
        $grade_check_stmt = $db->prepare($grade_check_query);
        $grade_check_stmt->bindParam(':user_id', $user_id);
        $grade_check_stmt->execute();
        $grade_check = $grade_check_stmt->fetch(PDO::FETCH_ASSOC);
        $has_grade_above_25 = ($grade_check['count'] > 0);

        // Check for ongoing grades
        $ongoing_grades_query = "SELECT COUNT(*) as count
                                FROM grades g
                                JOIN grade_submissions gs ON g.submission_id = gs.id
                                WHERE gs.user_id = :user_id
                                AND g.grade = 0.00
                                AND gs.status = 'processed'";
        $ongoing_grades_stmt = $db->prepare($ongoing_grades_query);
        $ongoing_grades_stmt->bindParam(':user_id', $user_id);
        $ongoing_grades_stmt->execute();
        $ongoing_grades = $ongoing_grades_stmt->fetch(PDO::FETCH_ASSOC);
        $has_ongoing_grades = ($ongoing_grades['count'] > 0);

        // Count total semesters completed
        $semester_count_query = "
            SELECT COUNT(DISTINCT g.semester_taken) as semesters
            FROM grades g
            JOIN grade_submissions gs ON g.submission_id = gs.id
            WHERE gs.user_id = :user_id AND gs.status = 'processed'
            AND (g.semester_taken LIKE '%1st Semester%' OR g.semester_taken LIKE '%2nd Semester%')
        ";
        $semester_count_stmt = $db->prepare($semester_count_query);
        $semester_count_stmt->bindParam(':user_id', $user_id);
        $semester_count_stmt->execute();
        $semester_data = $semester_count_stmt->fetch(PDO::FETCH_ASSOC);
        $total_semesters = $semester_data['semesters'] ?? 0;

        // Determine eligibility
        if (!$has_grade_above_25) {
            // Dean's List eligibility
            if ($gwa >= 1.00 && $gwa <= 1.75) {
                $honor_status = "Dean's List";
                $honor_color = 'green';
                $honor_criteria = 'GWA ≤ 1.75, no grade > 2.5';
            } else {
                $honor_criteria = 'GWA > 1.75 or has grade > 2.5';
            }

            // Latin Honors eligibility
            if ($total_semesters >= 8 && !$has_ongoing_grades && !$has_grade_above_25) {
                if ($gwa >= 1.00 && $gwa <= 1.25) {
                    $honor_status = 'Summa Cum Laude';
                    $honor_color = 'purple';
                    $honor_criteria = 'GWA ≤ 1.25, 8+ semesters';
                } elseif ($gwa >= 1.26 && $gwa <= 1.45) {
                    $honor_status = 'Magna Cum Laude';
                    $honor_color = 'purple';
                    $honor_criteria = 'GWA ≤ 1.45, 8+ semesters';
                } elseif ($gwa >= 1.46 && $gwa <= 1.75) {
                    $honor_status = 'Cum Laude';
                    $honor_color = 'purple';
                    $honor_criteria = 'GWA ≤ 1.75, 8+ semesters';
                }
            } elseif ($total_semesters < 8) {
                $honor_criteria = 'GWA > 1.75 or < 8 semesters completed';
            } elseif ($has_ongoing_grades) {
                $honor_criteria = 'GWA > 1.75 or has ongoing grades';
            }
        } else {
            $honor_criteria = 'Has grade(s) above 2.5';
        }
    }
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $update_data = [
            'first_name' => $_POST['first_name'] ?? '',
            'last_name' => $_POST['last_name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'student_id' => $_POST['student_id'] ?? '',
            'year_level' => $_POST['year_level'] ?? null,
            'section' => $_POST['section'] ?? ''
        ];

        if ($user->updateProfile($user_id, $update_data)) {
            $message = 'Profile updated successfully!';
            $message_type = 'success';

            // Update session data
            $_SESSION['first_name'] = $update_data['first_name'];
            $_SESSION['last_name'] = $update_data['last_name'];
            $_SESSION['section'] = $update_data['section'];

            // Refresh user data
            $user_data = $user->findById($user_id);
        } else {
            $message = 'Failed to update profile. Please try again.';
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
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $user_record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user_record && password_verify($current_password, $user_record['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $query = "UPDATE users SET password = :password WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':user_id', $user_id);

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
                    <a href="settings.php" class="bg-primary-50 border-r-2 border-primary-600 text-primary-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="settings" class="text-primary-500 mr-3 h-5 w-5"></i>
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

                    <div class="flex items-center space-x-4">
                        <?php include 'includes/header.php'; ?>
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
                                    <form method="POST" class="space-y-6">
                                        <input type="hidden" name="action" value="update_profile">
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
                                            <input type="email" id="email" name="email" required
                                                   class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                                                   value="<?php echo htmlspecialchars($user_data['email']); ?>">
                                        </div>

                                        <div>
                                            <label for="student_id" class="block text-sm font-semibold text-gray-700 mb-2">Student ID</label>
                                            <input type="text" id="student_id" name="student_id" required
                                                   class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                                                   value="<?php echo htmlspecialchars($user_data['student_id'] ?: ''); ?>">
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

                            <!-- Academic Performance -->
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                                <div class="p-6 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <i data-lucide="trophy" class="w-5 h-5 text-yellow-600 mr-2"></i>
                                        Academic Performance
                                    </h3>
                                </div>
                                <div class="p-6 space-y-4">
                                    <!-- Overall GWA -->
                                    <div class="text-center">
                                        <div class="text-3xl font-bold <?php echo $gwa_data['gwa'] ? 'text-primary-600' : 'text-gray-400'; ?>">
                                            <?php echo $gwa_data['gwa'] ? number_format(floor($gwa_data['gwa'] * 100) / 100, 2) : 'N/A'; ?>
                                        </div>
                                        <div class="text-sm text-gray-500">Overall GWA</div>
                                    </div>

                                    <!-- Honor Status -->
                                    <div class="text-center">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-<?php echo $honor_color; ?>-100 text-<?php echo $honor_color; ?>-800"
                                              title="<?php echo htmlspecialchars($honor_criteria); ?>">
                                            <?php echo $honor_status; ?>
                                        </span>
                                        <div class="text-sm text-gray-500 mt-1">Honor Status</div>
                                        <?php if ($honor_criteria): ?>
                                            <div class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($honor_criteria); ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Academic Stats -->
                                    <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-200">
                                        <div class="text-center">
                                            <div class="text-lg font-semibold text-blue-600"><?php echo $gwa_data['total_units'] ?? '0'; ?></div>
                                            <div class="text-xs text-gray-500">Total Units</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-lg font-semibold text-green-600"><?php echo $gwa_data['subjects_count'] ?? '0'; ?></div>
                                            <div class="text-xs text-gray-500">Subjects</div>
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
