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

// Get adviser's assigned sections
$query = "SELECT section, year_level FROM users WHERE id = :adviser_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':adviser_id', $adviser_id);
$stmt->execute();
$adviser_info = $stmt->fetch(PDO::FETCH_ASSOC);

$adviser_sections = [];
if (!empty($adviser_info['section'])) {
    // Try to decode as JSON first
    $sections_data = json_decode($adviser_info['section'], true);
    if (!is_array($sections_data)) {
        // If not JSON, treat as comma-separated
        $sections_data = array_map('trim', explode(',', $adviser_info['section']));
    }
    $adviser_sections = $sections_data;
}

$adviser_year_level = $adviser_info['year_level'] ?? null;

$message = '';
$message_type = '';

// Handle application processing
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $application_id = $_POST['application_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        // Get application details first for notification
        $get_app_query = "SELECT ha.user_id, ha.application_type, u.first_name, u.last_name FROM honor_applications ha JOIN users u ON ha.user_id = u.id WHERE ha.id = :application_id";
        $get_app_stmt = $db->prepare($get_app_query);
        $get_app_stmt->bindParam(':application_id', $application_id);
        $get_app_stmt->execute();
        $application_data = $get_app_stmt->fetch(PDO::FETCH_ASSOC);

        $query = "UPDATE honor_applications
                  SET status = 'approved', reviewed_at = NOW(), reviewed_by = :adviser_id
                  WHERE id = :application_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':adviser_id', $adviser_id);
        $stmt->bindParam(':application_id', $application_id);

        if ($stmt->execute() && $application_data) {
            // Notify student about approval
            $notificationManager = new NotificationManager($db);
            $notificationManager->notifyApplicationStatus($application_data['user_id'], 'approved', $application_data['application_type']);

            // Notify chairperson about application status change
            $student_name = $application_data['first_name'] . ' ' . $application_data['last_name'];
            $chairperson_query = "SELECT id FROM users WHERE role = 'chairperson' AND department = :department AND status = 'active' LIMIT 1";
            $chairperson_stmt = $db->prepare($chairperson_query);
            $chairperson_stmt->bindParam(':department', $department);
            $chairperson_stmt->execute();
            $chairperson = $chairperson_stmt->fetch(PDO::FETCH_ASSOC);

            if ($chairperson) {
                $notificationManager->notifyChairpersonApplicationStatusChanged($student_name, $application_data['application_type'], 'approved', $department);
            }

            $message = 'Application approved successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to approve application.';
            $message_type = 'error';
        }
    } elseif ($action === 'reject') {
        $rejection_reason = $_POST['rejection_reason'] ?? '';

        // Get application details first for notification
        $get_app_query = "SELECT ha.user_id, ha.application_type, u.first_name, u.last_name FROM honor_applications ha JOIN users u ON ha.user_id = u.id WHERE ha.id = :application_id";
        $get_app_stmt = $db->prepare($get_app_query);
        $get_app_stmt->bindParam(':application_id', $application_id);
        $get_app_stmt->execute();
        $application_data = $get_app_stmt->fetch(PDO::FETCH_ASSOC);

        $query = "UPDATE honor_applications
                  SET status = 'denied', reviewed_at = NOW(), reviewed_by = :adviser_id, rejection_reason = :reason
                  WHERE id = :application_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':adviser_id', $adviser_id);
        $stmt->bindParam(':application_id', $application_id);
        $stmt->bindParam(':reason', $rejection_reason);

        if ($stmt->execute() && $application_data) {
            // Notify student about rejection
            $notificationManager = new NotificationManager($db);
            $notificationManager->notifyApplicationStatus($application_data['user_id'], 'rejected', $application_data['application_type']);

            // Notify chairperson about application status change
            $student_name = $application_data['first_name'] . ' ' . $application_data['last_name'];
            $chairperson_query = "SELECT id FROM users WHERE role = 'chairperson' AND department = :department AND status = 'active' LIMIT 1";
            $chairperson_stmt = $db->prepare($chairperson_query);
            $chairperson_stmt->bindParam(':department', $department);
            $chairperson_stmt->execute();
            $chairperson = $chairperson_stmt->fetch(PDO::FETCH_ASSOC);

            if ($chairperson) {
                $notificationManager->notifyChairpersonApplicationStatusChanged($student_name, $application_data['application_type'], 'denied', $department);
            }

            $message = 'Application rejected successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to reject application.';
            $message_type = 'error';
        }
    } elseif ($action === 'remove') {
        // FEATURE: Remove/Decline approved applications
        $removal_reason = $_POST['removal_reason'] ?? '';

        // Get application details first
        $get_app_query = "SELECT user_id, application_type, status FROM honor_applications WHERE id = :application_id";
        $get_app_stmt = $db->prepare($get_app_query);
        $get_app_stmt->bindParam(':application_id', $application_id);
        $get_app_stmt->execute();
        $application_data = $get_app_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$application_data) {
            $message = 'Application not found.';
            $message_type = 'error';
        } elseif ($application_data['status'] !== 'approved' && $application_data['status'] !== 'denied') {
            $message = 'Can only remove approved or denied applications.';
            $message_type = 'error';
        } else {
            // Remove the approved application
            // Using 'cancelled' status (compatible with current database schema)
            $query = "UPDATE honor_applications
                      SET status = 'cancelled',
                          reviewed_at = NOW(),
                          reviewed_by = :adviser_id,
                          rejection_reason = :reason,
                          ineligibility_reasons = CONCAT(COALESCE(ineligibility_reasons, ''),
                                IF(LENGTH(COALESCE(ineligibility_reasons, '')) > 0, '; ', ''),
                                'Removed by adviser on ', NOW())
                      WHERE id = :application_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':adviser_id', $adviser_id);
            $stmt->bindParam(':application_id', $application_id);
            $stmt->bindParam(':reason', $removal_reason);

            if ($stmt->execute()) {
                // Notify student about removal
                $notificationManager = new NotificationManager($db);
                $type_labels = [
                    'deans_list' => "Dean's List",
                    'cum_laude' => 'Cum Laude',
                    'magna_cum_laude' => 'Magna Cum Laude',
                    'summa_cum_laude' => 'Summa Cum Laude'
                ];
                $honor_type = $type_labels[$application_data['application_type']] ?? $application_data['application_type'];

                $notificationManager->createNotification(
                    $application_data['user_id'],
                    'Application Removed',
                    "Your approved application for $honor_type has been removed by your adviser. Reason: " . ($removal_reason ?: 'No reason provided'),
                    'warning',
                    'application'
                );

                $message = 'Approved application removed successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to remove application.';
                $message_type = 'error';
            }
        }
    }
}

// Get applications for adviser's assigned sections
// Only show applications if adviser has assigned sections
if (!empty($adviser_sections)) {
    // Build flexible section matching conditions
    $section_conditions = [];
    $params = [$department];

    foreach ($adviser_sections as $assigned_section) {
        // Try exact match and partial matches
        $section_conditions[] = "u.section = ?";
        $params[] = $assigned_section;

        // If section is like "C-4", also try "C" and "4"
        if (strpos($assigned_section, '-') !== false) {
            $parts = explode('-', $assigned_section);
            foreach ($parts as $part) {
                $section_conditions[] = "u.section = ?";
                $params[] = trim($part);
            }
        }

        // Also try partial matches using LIKE
        $section_conditions[] = "u.section LIKE ?";
        $params[] = '%' . $assigned_section . '%';
    }

    $section_where = '(' . implode(' OR ', $section_conditions) . ')';

    $query = "SELECT ha.*, u.first_name, u.last_name, u.student_id, u.section, u.year_level,
                     ap.semester as period_semester, ap.school_year as period_academic_year,
                     ha.is_eligible, ha.ineligibility_reasons
              FROM honor_applications ha
              JOIN users u ON ha.user_id = u.id
              JOIN academic_periods ap ON ha.academic_period_id = ap.id
              WHERE u.department = ? AND $section_where AND ha.status != 'cancelled'
              ORDER BY ha.is_eligible ASC, ha.submitted_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate period-specific GWA for each application
    foreach ($applications as &$application) {
        $period_semester = $application['period_semester'];
        $period_academic_year = $application['period_academic_year'];

        // Create semester string based on period (matching grades table format)
        $semester_string = $period_semester . ' Semester SY ' . $period_academic_year;

        // Calculate period-specific GWA using the same logic as student applications.php
        $gwa_query = "
            SELECT
                SUM(g.units * g.grade) as total_grade_points,
                SUM(g.units) as total_units
            FROM grades g
            JOIN grade_submissions gs ON g.submission_id = gs.id
            WHERE gs.user_id = :user_id
            AND g.semester_taken = :semester_taken
            AND g.grade > 0.00
            AND NOT (UPPER(g.subject_name) LIKE '%NSTP%' OR UPPER(g.subject_name) LIKE '%NATIONAL SERVICE TRAINING%')
        ";

        $gwa_stmt = $db->prepare($gwa_query);
        $gwa_stmt->bindParam(':user_id', $application['user_id']);
        $gwa_stmt->bindParam(':semester_taken', $semester_string);
        $gwa_stmt->execute();
        $gwa_data = $gwa_stmt->fetch(PDO::FETCH_ASSOC);

        if ($gwa_data && $gwa_data['total_units'] > 0) {
            $period_gwa = $gwa_data['total_grade_points'] / $gwa_data['total_units'];
            $application['period_specific_gwa'] = floor($period_gwa * 100) / 100; // Truncate to 2 decimal places
        } else {
            $application['period_specific_gwa'] = null; // No grades found for this period
        }
    }
    unset($application); // Break the reference
} else {
    // If no section assigned, show NO applications
    $applications = [];
}

// Filter applications
$filter = $_GET['filter'] ?? 'all';
if ($filter !== 'all') {
    $applications = array_filter($applications, function($application) use ($filter) {
        return $application['status'] === $filter;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Honor Applications - CTU Honor System</title>
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
                    <a href="applications.php" class="bg-green-50 border-r-2 border-green-600 text-green-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="trophy" class="text-green-500 mr-3 h-5 w-5"></i>
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
                    <div class="flex items-center flex-1">
                        <button type="button" class="md:hidden -ml-0.5 -mt-0.5 h-12 w-12 inline-flex items-center justify-center rounded-xl text-gray-500 hover:text-gray-900">
                            <i data-lucide="menu" class="h-6 w-6"></i>
                        </button>
                        <div class="ml-4 md:ml-0">
                            <h1 class="text-2xl font-bold text-gray-900">Honor Applications</h1>
                            <?php if (!empty($adviser_sections)): ?>
                                <p class="text-sm text-gray-500">
                                    Processing applications from sections:
                                    <?php foreach ($adviser_sections as $index => $section): ?>
                                        <span class="font-semibold text-primary-600"><?php echo htmlspecialchars(formatSectionDisplay($section)); ?></span><?php if ($index < count($adviser_sections) - 1) echo ', '; ?>
                                    <?php endforeach; ?>
                                    <?php echo $adviser_year_level ? ' (Year ' . $adviser_year_level . ')' : ''; ?> -
                                    <?php echo count($applications); ?> applications
                                </p>
                            <?php else: ?>
                                <div class="flex items-center">
                                    <p class="text-sm text-gray-500 mr-2">
                                        No applications available - section assignment required
                                    </p>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                        <i data-lucide="alert-circle" class="w-3 h-3 mr-1"></i>
                                        No Section Assigned
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <select onchange="filterApplications(this.value)"
                                class="px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Applications</option>
                            <option value="submitted" <?php echo $filter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                            <option value="approved" <?php echo $filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>

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

                    <?php if (empty($adviser_sections)): ?>
                        <div class="mb-6 bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-lg">
                            <div class="flex">
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-amber-800">No Section Assigned</p>
                                    <p class="mt-1 text-sm text-amber-700">You cannot view any honor applications until the chairperson assigns you to a section. Contact your department chairperson for section assignment.</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Applications Table -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                        <?php if (empty($applications)): ?>
                            <div class="text-center py-12">
                                <i data-lucide="trophy" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                                <h4 class="text-xl font-medium text-gray-900 mb-2">No applications found</h4>
                                <p class="text-gray-500">No honor applications match your current filter.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Student
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Application Type
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                GWA
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Status
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Submitted
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($applications as $application): ?>
                                            <tr class="hover:bg-gray-50 transition-colors <?php echo !$application['is_eligible'] ? 'bg-amber-50 border-l-4 border-amber-500' : ''; ?>">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="w-10 h-10 <?php echo !$application['is_eligible'] ? 'bg-amber-100' : 'bg-primary-100'; ?> rounded-full flex items-center justify-center">
                                                            <?php if (!$application['is_eligible']): ?>
                                                                <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600"></i>
                                                            <?php else: ?>
                                                                <span class="text-sm font-medium text-primary-600">
                                                                    <?php echo strtoupper(substr($application['first_name'], 0, 1) . substr($application['last_name'], 0, 1)); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>
                                                                <?php if (!$application['is_eligible']): ?>
                                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">
                                                                        Not Eligible
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($application['student_id']); ?> • <?php echo htmlspecialchars(formatSectionDisplay($application['section'])); ?>
                                                            </div>
                                                            <?php if (!$application['is_eligible'] && $application['ineligibility_reasons']): ?>
                                                                <div class="mt-1 text-xs text-amber-700 font-medium">
                                                                    ⚠ <?php
                                                                    // Update the ineligibility message to show current GWA instead of historical value
                                                                    $ineligibility_message = $application['ineligibility_reasons'];
                                                                    if ($application['period_specific_gwa'] !== null) {
                                                                        // Replace any GWA value in the message with the current calculated GWA
                                                                        $ineligibility_message = preg_replace(
                                                                            '/GWA of \d+\.\d+/',
                                                                            'GWA of ' . formatGWA($application['period_specific_gwa']),
                                                                            $ineligibility_message
                                                                        );
                                                                    }
                                                                    echo htmlspecialchars($ineligibility_message);
                                                                    ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900 font-medium">
                                                        <?php echo ucfirst(str_replace('_', ' ', $application['application_type'])); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-bold text-primary-600">
                                                        <?php
                                                        if ($application['period_specific_gwa'] !== null) {
                                                            echo formatGWA($application['period_specific_gwa']);
                                                        } else {
                                                            echo '<span class="text-gray-400">N/A</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo htmlspecialchars($application['period_semester'] . ' Sem SY ' . $application['period_academic_year']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php
                                                        echo $application['status'] === 'approved' ? 'bg-green-100 text-green-800' :
                                                            ($application['status'] === 'submitted' ? 'bg-blue-100 text-blue-800' :
                                                            ($application['status'] === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'));
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $application['status'])); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo date('M d, Y', strtotime($application['submitted_at'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                                    <?php if ($application['status'] === 'submitted'): ?>
                                                        <div class="flex justify-center space-x-2">
                                                            <button onclick="processApplication(<?php echo $application['id']; ?>, 'approve', <?php echo $application['is_eligible'] ? 'true' : 'false'; ?>, '<?php echo addslashes($application['ineligibility_reasons'] ?? ''); ?>')"
                                                                    class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg transition-colors">
                                                                <i data-lucide="check" class="w-3 h-3 mr-1"></i>
                                                                Approve
                                                            </button>
                                                            <button onclick="showRejectModal(<?php echo $application['id']; ?>)"
                                                                    class="inline-flex items-center px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded-lg transition-colors">
                                                                <i data-lucide="x" class="w-3 h-3 mr-1"></i>
                                                                Reject
                                                            </button>
                                                        </div>
                                                    <?php elseif ($application['status'] === 'approved' || $application['status'] === 'denied'): ?>
                                                        <button onclick="showRemoveModal(<?php echo $application['id']; ?>)"
                                                                class="inline-flex items-center px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-medium rounded-lg transition-colors">
                                                            <i data-lucide="trash-2" class="w-3 h-3 mr-1"></i>
                                                            Remove
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-500 text-xs font-medium rounded-lg">
                                                            <i data-lucide="lock" class="w-3 h-3 mr-1"></i>
                                                            Processed
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-2xl bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Reject Application</h3>
                    <button onclick="hideRejectModal()" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <form method="POST">
                    <input type="hidden" name="application_id" id="rejectApplicationId">
                    <input type="hidden" name="action" value="reject">
                    <div class="mb-4">
                        <label for="rejection_reason" class="block text-sm font-semibold text-gray-700 mb-2">Reason for Rejection</label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="3" required 
                                  class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                                  placeholder="Please provide a reason for rejecting this application..."></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideRejectModal()" 
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl transition-colors">
                            Reject Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Remove Application Modal -->
    <div id="removeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-2xl bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Remove Application</h3>
                    <button onclick="hideRemoveModal()" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
                    <div class="flex">
                        <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 mr-2 flex-shrink-0"></i>
                        <p class="text-sm text-amber-800">
                            <strong>Warning:</strong> This will remove an already processed application. The student will be notified.
                        </p>
                    </div>
                </div>
                <form method="POST">
                    <input type="hidden" name="application_id" id="removeApplicationId">
                    <input type="hidden" name="action" value="remove">
                    <div class="mb-4">
                        <label for="removal_reason" class="block text-sm font-semibold text-gray-700 mb-2">Reason for Removal</label>
                        <textarea id="removal_reason" name="removal_reason" rows="3" required
                                  class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-colors"
                                  placeholder="Please provide a reason for removing this application..."></textarea>
                        <p class="mt-1 text-xs text-gray-500">The student will see this reason in their notification.</p>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideRemoveModal()"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl transition-colors">
                            Remove Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Approve Ineligible Application Modal -->
    <div id="approveIneligibleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-2xl bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Approve Ineligible Application</h3>
                    <button onclick="hideApproveIneligibleModal()" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                    <div class="flex">
                        <i data-lucide="alert-triangle" class="w-5 h-5 text-red-600 mr-2 flex-shrink-0"></i>
                        <p class="text-sm text-red-800 mb-2">
                            <strong>Warning:</strong> This application is not eligible for approval based on current criteria.
                        </p>
                        <div id="ineligibilityReasons" class="text-sm text-red-700">
                            <!-- Reasons will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
                <p class="text-sm text-gray-600 mb-4">
                    Are you sure you want to approve this application despite the ineligibility?
                </p>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideApproveIneligibleModal()"
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                        Cancel
                    </button>
                    <button type="button" onclick="confirmApproveIneligible()"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl transition-colors">
                        Approve Anyway
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function filterApplications(filter) {
            window.location.href = 'applications.php?filter=' + filter;
        }

        let pendingApprovalId = null;

        function processApplication(applicationId, action, isEligible, ineligibilityReasons) {
            if (action === 'approve') {
                if (isEligible) {
                    // For eligible applications, use simple confirmation
                    if (confirm('Are you sure you want to approve this application?')) {
                        submitApproval(applicationId);
                    }
                } else {
                    // For ineligible applications, show detailed modal
                    pendingApprovalId = applicationId;
                    showApproveIneligibleModal(ineligibilityReasons);
                }
            }
        }

        function submitApproval(applicationId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="application_id" value="${applicationId}">
                <input type="hidden" name="action" value="approve">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function showRejectModal(applicationId) {
            document.getElementById('rejectApplicationId').value = applicationId;
            document.getElementById('rejectModal').classList.remove('hidden');
        }

        function hideRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
        }

        function showRemoveModal(applicationId) {
            document.getElementById('removeApplicationId').value = applicationId;
            document.getElementById('removeModal').classList.remove('hidden');
            lucide.createIcons();
        }

        function hideRemoveModal() {
            document.getElementById('removeModal').classList.add('hidden');
        }

        function showApproveIneligibleModal(ineligibilityReasons) {
            // Format the ineligibility reasons for better display
            let formattedReasons = '';
            if (ineligibilityReasons && ineligibilityReasons.trim()) {
                // Split by semicolon and create bullet points
                const reasons = ineligibilityReasons.split(';').map(reason => reason.trim()).filter(reason => reason.length > 0);
                if (reasons.length > 0) {
                    formattedReasons = '<ul class="list-disc list-inside space-y-1 mt-2">';
                    reasons.forEach(reason => {
                        formattedReasons += '<li>' + reason + '</li>';
                    });
                    formattedReasons += '</ul>';
                }
            }
            document.getElementById('ineligibilityReasons').innerHTML = formattedReasons;
            document.getElementById('approveIneligibleModal').classList.remove('hidden');
            lucide.createIcons();
        }

        function hideApproveIneligibleModal() {
            document.getElementById('approveIneligibleModal').classList.add('hidden');
        }

        function confirmApproveIneligible() {
            if (pendingApprovalId) {
                submitApproval(pendingApprovalId);
            }
            hideApproveIneligibleModal();
        }

    </script>
</body>
</html>
