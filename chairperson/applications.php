<?php
require_once '../config/config.php';
require_once '../classes/AcademicPeriod.php';

requireLogin();

if (!hasRole('chairperson')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();
$academicPeriod = new AcademicPeriod($db);

$chairperson_id = $_SESSION['user_id'];
$department = $_SESSION['department'];

$message = '';
$message_type = '';

// Handle application processing and academic period management
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $application_id = $_POST['application_id'] ?? 0;
    $action = $_POST['action'] ?? '';

    if ($action === 'create_period') {
        $data = [
            'semester' => trim($_POST['semester'] ?? ''),
            'school_year' => trim($_POST['school_year'] ?? ''),
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'is_active' => isset($_POST['is_active'])
        ];

        if ($academicPeriod->create($data)) {
            $message = 'Academic period created successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to create academic period.';
            $message_type = 'error';
        }
    } elseif ($action === 'update_period') {
        $period_id = $_POST['period_id'] ?? 0;
        $data = [
            'semester' => trim($_POST['semester'] ?? ''),
            'school_year' => trim($_POST['school_year'] ?? ''),
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'is_active' => isset($_POST['is_active'])
        ];

        if ($academicPeriod->update($period_id, $data)) {
            $message = 'Academic period updated successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to update academic period.';
            $message_type = 'error';
        }
    } elseif ($action === 'delete_period') {
        $period_id = $_POST['period_id'] ?? 0;
        $result = $academicPeriod->delete($period_id);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
    } elseif ($action === 'set_active_period') {
        $period_id = $_POST['period_id'] ?? 0;
        if ($academicPeriod->setActive($period_id)) {
            $message = 'Active period updated successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to set active period.';
            $message_type = 'error';
        }
    }
    
}


// Get all academic periods
$academic_periods = $academicPeriod->getAll();

// Get current active period
$active_period = null;
foreach ($academic_periods as $period) {
    if ($period['is_active']) {
        $active_period = $period;
        break;
    }
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
                    <div class="w-8 h-8 bg-purple-600 rounded-xl flex items-center justify-center">
                        <i data-lucide="graduation-cap" class="w-5 h-5 text-white"></i>
                    </div>
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
                    <a href="applications.php" class="bg-purple-50 border-r-2 border-purple-600 text-purple-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="trophy" class="text-purple-500 mr-3 h-5 w-5"></i>
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
                            <h1 class="text-2xl font-bold text-gray-900">Academic Period Management</h1>
                            <p class="text-sm text-gray-500">Manage academic periods for applications and grade submissions</p>
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

                    <!-- Academic Period Management -->
                    <div class="mb-8 bg-white rounded-2xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <i data-lucide="calendar" class="w-5 h-5 text-purple-600 mr-2"></i>
                                        Academic Period Management
                                    </h3>
                                    <p class="text-sm text-gray-500 mt-1">Manage academic periods for applications and grade submissions</p>
                                </div>
                                <button onclick="showCreatePeriodModal()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-xl font-medium transition-colors flex items-center">
                                    <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                                    Add Period
                                </button>
                            </div>
                        </div>
                        <div class="p-6">
                            <?php if ($active_period): ?>
                                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl">
                                    <div class="flex items-center">
                                        <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-2"></i>
                                        <div>
                                            <p class="text-green-800 font-semibold">Currently Active Period</p>
                                            <p class="text-green-700 text-sm">
                                                <?php
                                                    $semester_display = $active_period['semester'] === '1st' ? '1st Semester' :
                                                                      ($active_period['semester'] === '2nd' ? '2nd Semester' :
                                                                      ucfirst($active_period['semester']));
                                                    echo htmlspecialchars($semester_display . ' ' . $active_period['school_year']);
                                                ?>
                                                (<?php echo date('M d', strtotime($active_period['start_date'])); ?> - <?php echo date('M d, Y', strtotime($active_period['end_date'])); ?>)
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (empty($academic_periods)): ?>
                                <div class="text-center py-12">
                                    <i data-lucide="calendar-x" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                                    <h4 class="text-xl font-medium text-gray-900 mb-2">No Academic Periods</h4>
                                    <p class="text-gray-500">Create an academic period to start managing applications.</p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Period</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($academic_periods as $period): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php
                                                            $semester_display = $period['semester'] === '1st' ? '1st Semester' :
                                                                              ($period['semester'] === '2nd' ? '2nd Semester' :
                                                                              ucfirst($period['semester']));
                                                            echo htmlspecialchars($semester_display . ' ' . $period['school_year']);
                                                        ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo date('M d, Y', strtotime($period['start_date'])); ?> -
                                                        <?php echo date('M d, Y', strtotime($period['end_date'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
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
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <div class="flex flex-col space-y-1 sm:flex-row sm:space-y-0 sm:space-x-2">
                                                            <?php if (!$period['is_active']): ?>
                                                                <button onclick="setActivePeriod(<?php echo $period['id']; ?>)"
                                                                        class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs transition-colors flex items-center justify-center">
                                                                    <i data-lucide="play" class="w-3 h-3 mr-1"></i>
                                                                    Activate
                                                                </button>
                                                            <?php endif; ?>
                                                            <button onclick="editPeriod(<?php echo htmlspecialchars(json_encode($period)); ?>)"
                                                                    class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs transition-colors flex items-center justify-center">
                                                                <i data-lucide="edit" class="w-3 h-3 mr-1"></i>
                                                                Edit
                                                            </button>
                                                            <button onclick="deletePeriod(<?php echo $period['id']; ?>, '<?php
                                                                $semester_display = $period['semester'] === '1st' ? '1st Semester' :
                                                                                  ($period['semester'] === '2nd' ? '2nd Semester' :
                                                                                  ucfirst($period['semester']));
                                                                echo htmlspecialchars($semester_display . ' ' . $period['school_year']);
                                                            ?>')"
                                                                    class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs transition-colors flex items-center justify-center">
                                                                <i data-lucide="trash-2" class="w-3 h-3 mr-1"></i>
                                                                Delete
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>


    <!-- Create/Edit Period Modal -->
    <div id="periodModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-2xl bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 id="modalTitle" class="text-lg font-semibold text-gray-900">Create Academic Period</h3>
                    <button onclick="hidePeriodModal()" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <form id="periodForm" method="POST">
                    <input type="hidden" name="action" id="formAction" value="create_period">
                    <input type="hidden" name="period_id" id="periodId">

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="semester" class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                                <select id="semester_modal" name="semester" required
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                    <option value="1st">1st Semester</option>
                                    <option value="2nd">2nd Semester</option>
                                    <option value="summer">Summer</option>
                                </select>
                            </div>

                            <div>
                                <label for="school_year" class="block text-sm font-medium text-gray-700 mb-1">School Year</label>
                                <input type="text" id="school_year" name="school_year" placeholder="2024-2025" required
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="start_date_modal" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                <input type="date" id="start_date_modal" name="start_date" required
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            </div>

                            <div>
                                <label for="end_date_modal" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                <input type="date" id="end_date_modal" name="end_date" required
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            </div>
                        </div>


                        <div class="flex items-center">
                            <input type="checkbox" id="is_active" name="is_active"
                                   class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                            <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                Set as active period
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="hidePeriodModal()"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" id="submitBtn"
                                class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                            Create Period
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();


        // Academic Period Management Functions
        function showCreatePeriodModal() {
            document.getElementById('modalTitle').textContent = 'Create Academic Period';
            document.getElementById('formAction').value = 'create_period';
            document.getElementById('submitBtn').textContent = 'Create Period';
            document.getElementById('periodForm').reset();
            document.getElementById('periodId').value = '';
            document.getElementById('periodModal').classList.remove('hidden');
        }

        function editPeriod(period) {
            document.getElementById('modalTitle').textContent = 'Edit Academic Period';
            document.getElementById('formAction').value = 'update_period';
            document.getElementById('submitBtn').textContent = 'Update Period';
            document.getElementById('periodId').value = period.id;
            document.getElementById('semester_modal').value = period.semester;
            document.getElementById('school_year').value = period.school_year;
            document.getElementById('start_date_modal').value = period.start_date;
            document.getElementById('end_date_modal').value = period.end_date;
            document.getElementById('is_active').checked = period.is_active == 1;
            document.getElementById('periodModal').classList.remove('hidden');
        }

        function hidePeriodModal() {
            document.getElementById('periodModal').classList.add('hidden');
        }

        function setActivePeriod(periodId) {
            if (confirm('Are you sure you want to set this as the active period? This will deactivate all other periods.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="set_active_period">
                    <input type="hidden" name="period_id" value="${periodId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deletePeriod(periodId, periodName) {
            if (confirm(`Are you sure you want to delete "${periodName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_period">
                    <input type="hidden" name="period_id" value="${periodId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        document.getElementById('periodModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hidePeriodModal();
            }
        });
    </script>
</body>
</html>
