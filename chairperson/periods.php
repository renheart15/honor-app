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
$message = '';
$message_type = '';

// Handle period management actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $period_name = $_POST['period_name'] ?? '';
        $semester = $_POST['semester'] ?? '';
        $school_year = $_POST['school_year'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';

        if (empty($period_name) || empty($semester) || empty($school_year) || empty($start_date) || empty($end_date)) {
            $message = 'All fields are required.';
            $message_type = 'error';
        } else {
            // Check for overlapping dates with active periods
            $overlap_query = "SELECT * FROM academic_periods
                             WHERE is_active = 1
                             AND ((start_date BETWEEN :start_date AND :end_date)
                                  OR (end_date BETWEEN :start_date AND :end_date)
                                  OR (:start_date BETWEEN start_date AND end_date)
                                  OR (:end_date BETWEEN start_date AND end_date))";
            $overlap_stmt = $db->prepare($overlap_query);
            $overlap_stmt->bindParam(':start_date', $start_date);
            $overlap_stmt->bindParam(':end_date', $end_date);
            $overlap_stmt->execute();

            if ($overlap_stmt->rowCount() > 0) {
                $message = 'Date range overlaps with an existing active period. Please choose different dates.';
                $message_type = 'error';
            } else {
                $insert_query = "INSERT INTO academic_periods (period_name, semester, school_year, start_date, end_date, is_active, created_at)
                                VALUES (:period_name, :semester, :school_year, :start_date, :end_date, 0, NOW())";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(':period_name', $period_name);
                $insert_stmt->bindParam(':semester', $semester);
                $insert_stmt->bindParam(':school_year', $school_year);
                $insert_stmt->bindParam(':start_date', $start_date);
                $insert_stmt->bindParam(':end_date', $end_date);

                if ($insert_stmt->execute()) {
                    $message = 'Academic period created successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to create academic period.';
                    $message_type = 'error';
                }
            }
        }
    }

    elseif ($action === 'activate') {
        $period_id = $_POST['period_id'] ?? 0;

        // Check for date overlaps before activating
        $period_query = "SELECT * FROM academic_periods WHERE id = :period_id";
        $period_stmt = $db->prepare($period_query);
        $period_stmt->bindParam(':period_id', $period_id);
        $period_stmt->execute();
        $period = $period_stmt->fetch(PDO::FETCH_ASSOC);

        if ($period) {
            $overlap_query = "SELECT * FROM academic_periods
                             WHERE is_active = 1
                             AND id != :period_id
                             AND ((start_date BETWEEN :start_date AND :end_date)
                                  OR (end_date BETWEEN :start_date AND :end_date)
                                  OR (:start_date BETWEEN start_date AND end_date)
                                  OR (:end_date BETWEEN start_date AND end_date))";
            $overlap_stmt = $db->prepare($overlap_query);
            $overlap_stmt->bindParam(':period_id', $period_id);
            $overlap_stmt->bindParam(':start_date', $period['start_date']);
            $overlap_stmt->bindParam(':end_date', $period['end_date']);
            $overlap_stmt->execute();

            if ($overlap_stmt->rowCount() > 0) {
                $overlapping = $overlap_stmt->fetch(PDO::FETCH_ASSOC);
                $message = 'Cannot activate: Date range overlaps with "' . $overlapping['period_name'] . '" which is currently active.';
                $message_type = 'error';
            } else {
                $update_query = "UPDATE academic_periods SET is_active = 1 WHERE id = :period_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':period_id', $period_id);

                if ($update_stmt->execute()) {
                    // Notify all students about new application period
                    $student_query = "SELECT id FROM users WHERE role = 'student' AND status = 'active'";
                    $student_stmt = $db->query($student_query);

                    while ($student = $student_stmt->fetch(PDO::FETCH_ASSOC)) {
                        $notificationManager->createNotification(
                            $student['id'],
                            'New Application Period Open',
                            "The application period for {$period['period_name']} is now open. Submit your honor application now!",
                            'info',
                            'application_period'
                        );
                    }

                    $message = 'Academic period activated successfully. All students have been notified.';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to activate academic period.';
                    $message_type = 'error';
                }
            }
        }
    }

    elseif ($action === 'deactivate') {
        $period_id = $_POST['period_id'] ?? 0;

        $update_query = "UPDATE academic_periods SET is_active = 0 WHERE id = :period_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':period_id', $period_id);

        if ($update_stmt->execute()) {
            // Notify students about closed period
            $period_query = "SELECT * FROM academic_periods WHERE id = :period_id";
            $period_stmt = $db->prepare($period_query);
            $period_stmt->bindParam(':period_id', $period_id);
            $period_stmt->execute();
            $period = $period_stmt->fetch(PDO::FETCH_ASSOC);

            $student_query = "SELECT id FROM users WHERE role = 'student' AND status = 'active'";
            $student_stmt = $db->query($student_query);

            while ($student = $student_stmt->fetch(PDO::FETCH_ASSOC)) {
                $notificationManager->createNotification(
                    $student['id'],
                    'Application Period Closed',
                    "The application period for {$period['period_name']} has been closed.",
                    'warning',
                    'application_period'
                );
            }

            $message = 'Academic period deactivated successfully.';
            $message_type = 'success';
        } else {
            $message = 'Failed to deactivate academic period.';
            $message_type = 'error';
        }
    }

    elseif ($action === 'update') {
        $period_id = $_POST['period_id'] ?? 0;
        $period_name = $_POST['period_name'] ?? '';
        $semester = $_POST['semester'] ?? '';
        $school_year = $_POST['school_year'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';

        if (empty($period_name) || empty($semester) || empty($school_year) || empty($start_date) || empty($end_date)) {
            $message = 'All fields are required.';
            $message_type = 'error';
        } else {
            $update_query = "UPDATE academic_periods
                           SET period_name = :period_name,
                               semester = :semester,
                               school_year = :school_year,
                               start_date = :start_date,
                               end_date = :end_date
                           WHERE id = :period_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':period_id', $period_id);
            $update_stmt->bindParam(':period_name', $period_name);
            $update_stmt->bindParam(':semester', $semester);
            $update_stmt->bindParam(':school_year', $school_year);
            $update_stmt->bindParam(':start_date', $start_date);
            $update_stmt->bindParam(':end_date', $end_date);

            if ($update_stmt->execute()) {
                $message = 'Academic period updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to update academic period.';
                $message_type = 'error';
            }
        }
    }

    elseif ($action === 'delete') {
        $period_id = $_POST['period_id'] ?? 0;

        // Check if period has any submissions or applications
        $check_query = "SELECT
                        (SELECT COUNT(*) FROM grade_submissions WHERE academic_period_id = :period_id1) as submission_count,
                        (SELECT COUNT(*) FROM honor_applications WHERE academic_period_id = :period_id2) as application_count";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':period_id1', $period_id);
        $check_stmt->bindParam(':period_id2', $period_id);
        $check_stmt->execute();
        $counts = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($counts['submission_count'] > 0 || $counts['application_count'] > 0) {
            $message = 'Cannot delete period: It has ' . $counts['submission_count'] . ' grade submission(s) and ' . $counts['application_count'] . ' application(s). Deactivate it instead.';
            $message_type = 'error';
        } else {
            $delete_query = "DELETE FROM academic_periods WHERE id = :period_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':period_id', $period_id);

            if ($delete_stmt->execute()) {
                $message = 'Academic period deleted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to delete academic period.';
                $message_type = 'error';
            }
        }
    }
}

// Get all academic periods with statistics
$periods_query = "SELECT
                    ap.*,
                    COUNT(DISTINCT gs.id) as submission_count,
                    COUNT(DISTINCT ha.id) as application_count,
                    COUNT(DISTINCT CASE WHEN ha.status = 'submitted' THEN ha.id END) as pending_applications,
                    COUNT(DISTINCT CASE WHEN ha.status = 'approved' THEN ha.id END) as approved_applications
                  FROM academic_periods ap
                  LEFT JOIN grade_submissions gs ON ap.id = gs.academic_period_id
                  LEFT JOIN honor_applications ha ON ap.id = ha.academic_period_id
                  GROUP BY ap.id
                  ORDER BY ap.school_year DESC,
                          CASE ap.semester
                            WHEN '1st' THEN 1
                            WHEN '2nd' THEN 2
                            WHEN 'Summer' THEN 3
                          END ASC";
$periods_stmt = $db->query($periods_query);
$periods = $periods_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Periods - CTU Honor System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include '../includes/chairperson_sidebar.php'; ?>

        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Academic Periods</h1>
                        <p class="text-sm text-gray-500">Manage application periods and schedules</p>
                    </div>
                    <button onclick="openCreateModal()" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg flex items-center">
                        <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                        Create New Period
                    </button>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6">
                <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-xl border-2 <?php echo $message_type === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'; ?>">
                        <div class="flex items-center">
                            <i data-lucide="<?php echo $message_type === 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5 <?php echo $message_type === 'success' ? 'text-green-500' : 'text-red-500'; ?> mr-2"></i>
                            <span class="<?php echo $message_type === 'success' ? 'text-green-700' : 'text-red-700'; ?> font-medium"><?php echo htmlspecialchars($message); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Periods</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo count($periods); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <i data-lucide="calendar" class="w-6 h-6 text-blue-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Active Periods</p>
                                <p class="text-2xl font-bold text-green-600">
                                    <?php echo count(array_filter($periods, function($p) { return $p['is_active'] == 1; })); ?>
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i data-lucide="check-circle" class="w-6 h-6 text-green-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Submissions</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?php echo array_sum(array_column($periods, 'submission_count')); ?>
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                                <i data-lucide="file-text" class="w-6 h-6 text-purple-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Applications</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?php echo array_sum(array_column($periods, 'application_count')); ?>
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center">
                                <i data-lucide="award" class="w-6 h-6 text-amber-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Periods List -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Academic Periods</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Semester</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">School Year</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statistics</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($periods as $period): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center mr-3">
                                                    <i data-lucide="calendar" class="w-5 h-5 text-primary-600"></i>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($period['period_name']); ?></div>
                                                    <div class="text-sm text-gray-500">ID: <?php echo $period['id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?php echo htmlspecialchars($period['semester']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?php echo htmlspecialchars($period['school_year']); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($period['start_date'])); ?></div>
                                            <div class="text-xs text-gray-500">to <?php echo date('M d, Y', strtotime($period['end_date'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($period['is_active']): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i>
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    <i data-lucide="circle" class="w-3 h-3 mr-1"></i>
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-wrap gap-2">
                                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs bg-blue-50 text-blue-700">
                                                    <i data-lucide="file-text" class="w-3 h-3 mr-1"></i>
                                                    <?php echo $period['submission_count']; ?> submissions
                                                </span>
                                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs bg-purple-50 text-purple-700">
                                                    <i data-lucide="award" class="w-3 h-3 mr-1"></i>
                                                    <?php echo $period['application_count']; ?> applications
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <?php if ($period['is_active']): ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
                                                        <button type="submit" onclick="return confirm('Deactivate this period? Students will no longer be able to submit applications.')"
                                                                class="text-amber-600 hover:text-amber-700 p-2 hover:bg-amber-50 rounded-lg">
                                                            <i data-lucide="pause-circle" class="w-4 h-4"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="action" value="activate">
                                                        <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
                                                        <button type="submit" onclick="return confirm('Activate this period? Students will be able to submit applications.')"
                                                                class="text-green-600 hover:text-green-700 p-2 hover:bg-green-50 rounded-lg">
                                                            <i data-lucide="play-circle" class="w-4 h-4"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($period)); ?>)"
                                                        class="text-blue-600 hover:text-blue-700 p-2 hover:bg-blue-50 rounded-lg">
                                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                                </button>

                                                <?php if ($period['submission_count'] == 0 && $period['application_count'] == 0): ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
                                                        <button type="submit" onclick="return confirm('Delete this period permanently?')"
                                                                class="text-red-600 hover:text-red-700 p-2 hover:bg-red-50 rounded-lg">
                                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (empty($periods)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center">
                                            <i data-lucide="calendar-x" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                                            <p class="text-gray-500">No academic periods found. Create one to get started.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Period Modal -->
    <div id="createModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-900">Create Academic Period</h3>
            </div>

            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="create">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Period Name</label>
                        <input type="text" name="period_name" required
                               placeholder="e.g., First Semester 2024-2025"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Semester</label>
                            <select name="semester" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Select semester...</option>
                                <option value="1st">1st Semester</option>
                                <option value="2nd">2nd Semester</option>
                                <option value="Summer">Summer</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">School Year</label>
                            <input type="text" name="school_year" required
                                   placeholder="e.g., 2024-2025"
                                   pattern="[0-9]{4}-[0-9]{4}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                            <input type="date" name="start_date" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                            <input type="date" name="end_date" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex">
                            <i data-lucide="info" class="w-5 h-5 text-blue-600 mr-2 flex-shrink-0"></i>
                            <div class="text-sm text-blue-700">
                                <p class="font-medium mb-1">Important Notes:</p>
                                <ul class="list-disc list-inside space-y-1">
                                    <li>New periods are created as inactive by default</li>
                                    <li>Activate the period when you're ready to open applications</li>
                                    <li>Dates should not overlap with other active periods</li>
                                    <li>Students will be notified when a period is activated</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeCreateModal()"
                            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                            class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                        Create Period
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Period Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-900">Edit Academic Period</h3>
            </div>

            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="period_id" id="edit_period_id">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Period Name</label>
                        <input type="text" name="period_name" id="edit_period_name" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Semester</label>
                            <select name="semester" id="edit_semester" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="1st">1st Semester</option>
                                <option value="2nd">2nd Semester</option>
                                <option value="Summer">Summer</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">School Year</label>
                            <input type="text" name="school_year" id="edit_school_year" required
                                   pattern="[0-9]{4}-[0-9]{4}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                            <input type="date" name="start_date" id="edit_start_date" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                            <input type="date" name="end_date" id="edit_end_date" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeEditModal()"
                            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                            class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function openCreateModal() {
            document.getElementById('createModal').classList.remove('hidden');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.add('hidden');
        }

        function openEditModal(period) {
            document.getElementById('edit_period_id').value = period.id;
            document.getElementById('edit_period_name').value = period.period_name;
            document.getElementById('edit_semester').value = period.semester;
            document.getElementById('edit_school_year').value = period.school_year;
            document.getElementById('edit_start_date').value = period.start_date;
            document.getElementById('edit_end_date').value = period.end_date;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // Close modals on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeCreateModal();
                closeEditModal();
            }
        });
    </script>
</body>
</html>
