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

// Get adviser's assigned sections and year level
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

// Get students in adviser's sections with their GWA
// Only show students if adviser has assigned sections
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

    $query = "SELECT u.*, gwa.gwa, gwa.calculated_at,
                     (SELECT COUNT(*) FROM grade_submissions gs WHERE gs.user_id = u.id) as submission_count,
                     (SELECT COUNT(*) FROM honor_applications ha WHERE ha.user_id = u.id) as application_count
              FROM users u
              LEFT JOIN gwa_calculations gwa ON u.id = gwa.user_id
              WHERE u.role = 'student' AND u.department = ? AND $section_where AND u.status = 'active'
              ORDER BY u.last_name, u.first_name";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // If no section assigned, show NO students
    $students = [];
}

// Filter students
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

if ($filter !== 'all') {
    $students = array_filter($students, function($student) use ($filter) {
        switch ($filter) {
            case 'with_gwa':
                return !empty($student['gwa']);
            case 'honor_eligible':
                return !empty($student['gwa']) && $student['gwa'] <= 1.75;
            case 'dean_list':
                return !empty($student['gwa']) && $student['gwa'] <= 1.45;
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
    <title>Students - CTU Honor System</title>
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
                    <a href="applications.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="trophy" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Honor Applications
                    </a>
                    <a href="students.php" class="bg-green-50 border-r-2 border-green-600 text-green-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="users" class="text-green-500 mr-3 h-5 w-5"></i>
                        Students
                    </a>
                    <a href="rankings.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="award" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Honor Rankings
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
                            <h1 class="text-2xl font-bold text-gray-900">Students</h1>
                            <?php if (!empty($adviser_sections)): ?>
                                <p class="text-sm text-gray-500">
                                    Managing sections:
                                    <?php foreach ($adviser_sections as $index => $section): ?>
                                        <span class="font-semibold text-primary-600"><?php echo htmlspecialchars(formatSectionDisplay($section)); ?></span><?php if ($index < count($adviser_sections) - 1) echo ', '; ?>
                                    <?php endforeach; ?>
                                    <?php echo $adviser_year_level ? ' (Year ' . $adviser_year_level . ')' : ''; ?> -
                                    <?php echo count($students); ?> students
                                </p>
                            <?php else: ?>
                                <div class="flex items-center">
                                    <p class="text-sm text-gray-500 mr-2">
                                        No students available - section assignment required
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
                            <option value="honor_eligible" <?php echo $filter === 'honor_eligible' ? 'selected' : ''; ?>>Honor Eligible</option>
                            <option value="dean_list" <?php echo $filter === 'dean_list' ? 'selected' : ''; ?>>Dean's List</option>
                        </select>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-4 sm:p-6 lg:p-8">
                    <?php if (empty($adviser_sections)): ?>
                        <!-- Unassigned Adviser Notice -->
                        <div class="mb-6 bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-lg">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-amber-800">
                                        No Section Assigned
                                    </p>
                                    <p class="mt-1 text-sm text-amber-700">
                                        You cannot view any students until the chairperson assigns you to a section. 
                                        Contact your department chairperson for section assignment.
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year Level</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GWA</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($students as $student): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                                                                <i data-lucide="user" class="w-5 h-5 text-primary-600"></i>
                                                            </div>
                                                            <div class="ml-4">
                                                                <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['student_id']); ?></div>
                                                                <div class="text-xs text-gray-400"><?php echo htmlspecialchars($student['email']); ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars(formatSectionDisplay($student['section'])); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900">Year <?php echo $student['year_level']; ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-bold <?php echo !empty($student['gwa']) ? ($student['gwa'] <= 1.45 ? 'text-green-600' : ($student['gwa'] <= 1.75 ? 'text-blue-600' : 'text-gray-900')) : 'text-gray-400'; ?>">
                                                            <?php echo !empty($student['gwa']) ? number_format(floor($student['gwa'] * 100) / 100, 2) : 'No GWA'; ?>
                                                        </div>
                                                        <?php if (!empty($student['calculated_at'])): ?>
                                                            <div class="text-xs text-gray-400">
                                                                Updated: <?php echo date('M j, Y', strtotime($student['calculated_at'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <div class="flex items-center justify-end space-x-2">
                                                            <button onclick="viewStudentDetails(<?php echo $student['id']; ?>)"
                                                                    class="text-gray-600 hover:text-gray-900 p-1 rounded-lg hover:bg-gray-50 transition-colors"
                                                                    title="View Details">
                                                                <i data-lucide="eye" class="w-4 h-4"></i>
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

    <!-- Student Details Modal -->
    <div id="studentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl max-w-4xl w-full max-h-screen overflow-y-auto">
                <div class="p-6">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-2xl font-bold text-gray-900">Student Details</h3>
                        <button onclick="closeStudentModal()" class="text-gray-400 hover:text-gray-600 p-2 rounded-lg hover:bg-gray-100">
                            <i data-lucide="x" class="w-6 h-6"></i>
                        </button>
                    </div>

                    <!-- Loading State -->
                    <div id="modalLoading" class="text-center py-8">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mx-auto"></div>
                        <p class="mt-4 text-gray-500">Loading student details...</p>
                    </div>

                    <!-- Modal Content -->
                    <div id="modalContent" class="hidden">
                        <!-- Content will be loaded here -->
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

        function viewStudentDetails(studentId) {
            // Show modal
            document.getElementById('studentModal').classList.remove('hidden');
            document.getElementById('modalLoading').classList.remove('hidden');
            document.getElementById('modalContent').classList.add('hidden');

            // Fetch student details
            fetch('get-student-details.php?id=' + studentId)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('modalLoading').classList.add('hidden');
                    document.getElementById('modalContent').classList.remove('hidden');

                    if (data.success) {
                        document.getElementById('modalContent').innerHTML = data.html;
                        lucide.createIcons(); // Re-initialize icons for new content
                    } else {
                        document.getElementById('modalContent').innerHTML =
                            '<div class="text-center py-8"><p class="text-red-500">Error loading student details: ' + data.message + '</p></div>';
                    }
                })
                .catch(error => {
                    document.getElementById('modalLoading').classList.add('hidden');
                    document.getElementById('modalContent').classList.remove('hidden');
                    document.getElementById('modalContent').innerHTML =
                        '<div class="text-center py-8"><p class="text-red-500">Error loading student details. Please try again.</p></div>';
                });
        }

        function closeStudentModal() {
            document.getElementById('studentModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('studentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStudentModal();
            }
        });

        // Search on Enter key
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchStudents();
            }
        });
    </script>
</body>
</html>
