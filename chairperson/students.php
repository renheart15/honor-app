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

// Get students in the department with comprehensive data
$query = "SELECT u.*,
                 (SELECT COUNT(*) FROM grade_submissions gs WHERE gs.user_id = u.id) as submission_count
          FROM users u
          WHERE u.role = 'student' AND u.department = :department AND u.status = 'active'
          ORDER BY u.last_name, u.first_name";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current active academic period
$active_period_query = "SELECT * FROM academic_periods WHERE is_active = 1 LIMIT 1";
$active_period_stmt = $db->prepare($active_period_query);
$active_period_stmt->execute();
$active_period = $active_period_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate period-specific GWA for each student
foreach ($students as &$student) {
    if ($active_period) {
        $semester_string = $active_period['semester'] . ' Semester SY ' . $active_period['school_year'];

        // Calculate period-specific GWA
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
        $gwa_stmt->bindParam(':user_id', $student['id']);
        $gwa_stmt->bindParam(':semester_taken', $semester_string);
        $gwa_stmt->execute();
        $gwa_data = $gwa_stmt->fetch(PDO::FETCH_ASSOC);

        if ($gwa_data && $gwa_data['total_units'] > 0) {
            $period_gwa = $gwa_data['total_grade_points'] / $gwa_data['total_units'];
            $student['gwa'] = floor($period_gwa * 100) / 100; // Truncate to 2 decimal places
        } else {
            $student['gwa'] = null; // No grades found for this period
        }
    } else {
        $student['gwa'] = null; // No active period
    }
}
unset($student); // Break the reference

// Sort students by GWA (ascending, nulls last)
usort($students, function($a, $b) {
    if ($a['gwa'] === null && $b['gwa'] === null) return 0;
    if ($a['gwa'] === null) return 1;
    if ($b['gwa'] === null) return -1;
    return $a['gwa'] <=> $b['gwa'];
});

// Filter students
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

if ($filter !== 'all') {
    $students = array_filter($students, function($student) use ($filter) {
        switch ($filter) {
            case 'with_gwa':
                return $student['gwa'] !== null;
            case 'dean_list':
                return $student['gwa'] !== null && $student['gwa'] <= 1.75;
            default:
                return true;
        }
    });
}

if (!empty($search)) {
    $students = array_filter($students, function($student) use ($search) {
        $searchTerm = strtolower($search);
        return strpos(strtolower($student['first_name'] . ' ' . $student['last_name']), $searchTerm) !== false ||
               strpos(strtolower($student['student_id']), $searchTerm) !== false ||
               strpos(strtolower($student['email']), $searchTerm) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Ranking - CTU Honor System</title>
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
                    <a href="rankings.php" class="bg-purple-50 border-r-2 border-purple-600 text-purple-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="trophy" class="text-purple-500 mr-3 h-5 w-5"></i>
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
                            <h1 class="text-2xl font-bold text-gray-900">Student Ranking</h1>
                            <p class="text-sm text-gray-500">
                                Honor roll and ranking for <?php echo $department; ?> department
                                <?php if ($active_period): ?>
                                    • <?php echo $active_period['semester']; ?> Semester SY <?php echo $active_period['school_year']; ?>
                                <?php else: ?>
                                    • No active period
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Search students..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   class="pl-10 pr-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                            <i data-lucide="search" class="absolute left-3 top-2.5 h-4 w-4 text-gray-400"></i>
                        </div>
                        <select onchange="filterStudents(this.value)"
                                class="px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Students</option>
                            <option value="with_gwa" <?php echo $filter === 'with_gwa' ? 'selected' : ''; ?>>With GWA</option>
                            <option value="dean_list" <?php echo $filter === 'dean_list' ? 'selected' : ''; ?>>Dean's List</option>
                        </select>
                        <button onclick="exportStudentList()" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors">
                            <i data-lucide="download" class="w-4 h-4 inline mr-2"></i>
                            Export
                        </button>
                    </div>

                    <?php include 'includes/header.php'; ?>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-4 sm:p-6 lg:p-8">
                    <!-- Summary Stats -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="users" class="w-6 h-6 text-blue-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Students</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo count($students); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-green-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="star" class="w-6 h-6 text-green-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Dean's List</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo count(array_filter($students, function($s) { return $s['gwa'] !== null && $s['gwa'] <= 1.75; })); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-yellow-100 rounded-2xl flex items-center justify-center">
                                    <img src="../img/cebu-technological-university-seeklogo.png" alt="CTU Logo" class="w-12 h-12">
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">With GWA</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo count(array_filter($students, function($s) { return $s['gwa'] !== null; })); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-purple-100 rounded-2xl flex items-center justify-center">
                                    <i data-lucide="book-open" class="w-6 h-6 text-purple-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Average GWA</p>
                                    <p class="text-2xl font-bold text-gray-900">
                                        <?php
                                        $students_with_gwa = array_filter($students, function($s) { return $s['gwa'] !== null; });
                                        if (count($students_with_gwa) > 0) {
                                            $total_gwa = array_sum(array_column($students_with_gwa, 'gwa'));
                                            $avg_gwa = $total_gwa / count($students_with_gwa);
                                            echo number_format($avg_gwa, 2);
                                        } else {
                                            echo "N/A";
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Students Table -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                        <div class="p-6">
                            <?php if (empty($students)): ?>
                                <div class="text-center py-12">
                                    <i data-lucide="users" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                                    <h4 class="text-xl font-medium text-gray-900 mb-2">No students found</h4>
                                    <p class="text-gray-500">No students match your current filter or search criteria.</p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GWA</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Honor Status</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php
                                            $rank = 1;
                                            foreach ($students as $student):
                                                if ($student['gwa'] === null) continue;
                                            ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold <?php echo $rank <= 3 ? ($rank === 1 ? 'bg-yellow-100 text-yellow-800' : ($rank === 2 ? 'bg-gray-100 text-gray-800' : 'bg-orange-100 text-orange-800')) : 'bg-blue-100 text-blue-800'; ?>">
                                                                #<?php echo $rank; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                                                                <i data-lucide="user" class="w-5 h-5 text-primary-600"></i>
                                                            </div>
                                                            <div class="ml-4">
                                                                <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['student_id']); ?> • Year <?php echo $student['year_level']; ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($student['gwa'] !== null): ?>
                                                            <div class="text-lg font-bold <?php echo $student['gwa'] <= 1.00 ? 'text-purple-600' : ($student['gwa'] <= 1.45 ? 'text-green-600' : ($student['gwa'] <= 1.75 ? 'text-blue-600' : 'text-gray-900')); ?>">
                                                                <?php echo formatGWA($student['gwa']); ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-gray-400 text-sm">No grades</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($student['gwa'] !== null): ?>
                                                            <?php if ($student['gwa'] <= 1.75): ?>
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                    <i data-lucide="star" class="w-3 h-3 mr-1"></i>
                                                                    Dean's List
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                                    Regular
                                                                </span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                                No Status
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <div class="font-medium">
                                                            <?php echo htmlspecialchars(formatSectionDisplay($student['section'])); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <div class="flex space-x-2">
                                                            <button onclick="viewStudentDetails(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'], ENT_QUOTES); ?>')"
                                                                    class="text-gray-600 hover:text-gray-700">
                                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php 
                                                $rank++;
                                            endforeach; 
                                            ?>
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

    <!-- Student Details Modal -->
    <div id="studentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-2xl bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 id="modalTitle" class="text-lg font-semibold text-gray-900">Student Grades</h3>
                    <button onclick="hideStudentModal()" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div id="modalContent" class="space-y-4">
                    <div class="flex items-center justify-center py-8">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function filterStudents(filter) {
            const search = document.getElementById('searchInput').value;
            const url = new URL(window.location);
            url.searchParams.set('filter', filter);
            if (search) {
                url.searchParams.set('search', search);
            } else {
                url.searchParams.delete('search');
            }
            window.location.href = url.toString();
        }

        function searchStudents() {
            const search = document.getElementById('searchInput').value;
            const filter = document.querySelector('select').value;
            const url = new URL(window.location);
            url.searchParams.set('filter', filter);
            if (search) {
                url.searchParams.set('search', search);
            } else {
                url.searchParams.delete('search');
            }
            window.location.href = url.toString();
        }

        function viewStudentDetails(studentId, studentName) {
            document.getElementById('modalTitle').textContent = studentName + ' - Grade Details';
            document.getElementById('studentModal').classList.remove('hidden');

            // Show loading spinner
            document.getElementById('modalContent').innerHTML = `
                <div class="flex items-center justify-center py-8">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600"></div>
                </div>
            `;

            // Fetch student grades via AJAX
            fetch('get_student_grades.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'student_id=' + encodeURIComponent(studentId)
            })
            .then(response => response.json())
            .then(data => {
                displayStudentGrades(data);
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('modalContent').innerHTML = `
                    <div class="text-center py-8">
                        <div class="text-red-600 mb-2">
                            <i data-lucide="alert-circle" class="w-8 h-8 mx-auto mb-2"></i>
                            Error loading grades
                        </div>
                        <p class="text-gray-500">Please try again later.</p>
                    </div>
                `;
                lucide.createIcons();
            });
        }

        function displayStudentGrades(data) {
            if (data.success && data.grades && data.grades.length > 0) {
                let html = `
                    <div class="mb-4 p-4 bg-gray-50 rounded-xl">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <span class="text-gray-500">Period:</span>
                                <span class="font-medium ml-2">${data.period}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">GWA:</span>
                                <span class="font-bold text-purple-600 ml-2">${data.gwa}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Total Units:</span>
                                <span class="font-medium ml-2">${data.total_units}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Subjects:</span>
                                <span class="font-medium ml-2">${data.subjects_count}</span>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Units</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Grade</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remarks</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                `;

                data.grades.forEach(grade => {
                    const gradeColor = grade.grade <= 1.00 ? 'text-purple-600' :
                                     grade.grade <= 1.75 ? 'text-green-600' :
                                     grade.grade <= 3.00 ? 'text-blue-600' : 'text-red-600';

                    html += `
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">${grade.subject_name}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">${grade.subject_code || '-'}</td>
                            <td class="px-4 py-3 text-sm text-center text-gray-900">${grade.units}</td>
                            <td class="px-4 py-3 text-sm text-center font-semibold ${gradeColor}">${grade.grade}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">${grade.remarks || '-'}</td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                `;

                document.getElementById('modalContent').innerHTML = html;
            } else {
                let debugInfo = '';
                if (data.debug_info) {
                    debugInfo = `
                        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-xs">
                            <strong>Debug Info:</strong><br>
                            Active Period: ${data.debug_info.active_period_semester}<br>
                            Available Semesters: ${data.debug_info.available_semesters ? data.debug_info.available_semesters.join(', ') : 'None'}<br>
                            Total Grades Found: ${data.debug_info.total_grades_found}<br>
                            Student ID: ${data.debug_info.student_id}
                        </div>
                    `;
                }

                document.getElementById('modalContent').innerHTML = `
                    <div class="text-center py-8">
                        <div class="text-gray-400 mb-2">
                            <i data-lucide="book-open" class="w-12 h-12 mx-auto mb-2"></i>
                            No grades found
                        </div>
                        <p class="text-gray-500">No grades available for the current period.</p>
                        ${debugInfo}
                    </div>
                `;
            }
            lucide.createIcons();
        }

        function hideStudentModal() {
            document.getElementById('studentModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('studentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideStudentModal();
            }
        });

        function exportStudentList() {
            // Implement export functionality
            alert('Export functionality will be implemented');
        }

        // Search on Enter key
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchStudents();
            }
        });
    </script>
</body>
</html>
