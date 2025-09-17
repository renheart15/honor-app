<?php
require_once '../config/config.php';
require_once '../classes/AcademicPeriod.php';
require_once '../classes/GradeProcessor.php';

requireLogin();

if (!hasRole('student')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();
$academicPeriod = new AcademicPeriod($db);
$gradeProcessor = new GradeProcessor($db);

$user_id = $_SESSION['user_id'];

// Get all student's grades first for GWA calculation
$all_grades_query = "
    SELECT g.*, g.semester_taken,
           CASE
               WHEN g.semester_taken LIKE '%1st%' THEN '1st'
               WHEN g.semester_taken LIKE '%2nd%' THEN '2nd'
               WHEN g.semester_taken LIKE '%Summer%' OR g.semester_taken LIKE '%summer%' THEN 'summer'
               ELSE '1st'
           END as semester_type,
           CASE
               WHEN g.semester_taken REGEXP '[0-9]{4}-[0-9]{4}' THEN SUBSTRING(g.semester_taken, -9)
               ELSE 'Unknown'
           END as school_year_taken
    FROM grades g
    JOIN grade_submissions gs ON g.submission_id = gs.id
    JOIN academic_periods ap ON gs.academic_period_id = ap.id
    WHERE gs.user_id = :user_id
    AND gs.status = 'processed'
    ORDER BY
        CASE
            WHEN g.semester_taken REGEXP '[0-9]{4}-[0-9]{4}' THEN SUBSTRING(g.semester_taken, -9)
            ELSE '0000-0000'
        END DESC,
        CASE
            WHEN g.semester_taken LIKE '%2nd%' THEN 1
            WHEN g.semester_taken LIKE '%1st%' THEN 2
            WHEN g.semester_taken LIKE '%Summer%' OR g.semester_taken LIKE '%summer%' THEN 3
            ELSE 4
        END ASC
";

$all_grades_stmt = $db->prepare($all_grades_query);
$all_grades_stmt->bindParam(':user_id', $user_id);
$all_grades_stmt->execute();
$all_grades = $all_grades_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall GWA from actual grades displayed on the page
$overall_gwa_data = null;
if (!empty($all_grades)) {
    $total_grade_points = 0;
    $total_units = 0;
    $periods_set = [];

    foreach ($all_grades as $grade) {
        // Skip NSTP subjects and ongoing subjects (grade = 0) from GWA calculation
        if (strpos($grade['subject_name'], 'NSTP') !== false ||
            strpos($grade['subject_name'], 'NATIONAL SERVICE TRAINING') !== false ||
            $grade['grade'] == 0) {
            continue;
        }

        $total_grade_points += ($grade['grade'] * $grade['units']);
        $total_units += $grade['units'];

        // Track unique academic periods
        $period_key = $grade['semester_taken'];
        $periods_set[$period_key] = true;
    }

    if ($total_units > 0) {
        $overall_gwa_exact = $total_grade_points / $total_units;
        $overall_gwa_calculated = floor($overall_gwa_exact * 100) / 100; // Apply truncation
        $overall_gwa_data = [
            'gwa' => $overall_gwa_calculated,
            'total_units' => $total_units,
            'periods_count' => count($periods_set)
        ];
    }
}

// Group grades by semester and school year
$grades_by_semester = [];
foreach ($all_grades as $grade) {
    // Use the actual semester_taken field from the grades table
    $semester_key = $grade['semester_taken'];

    if (!isset($grades_by_semester[$semester_key])) {
        $grades_by_semester[$semester_key] = [];
    }
    $grades_by_semester[$semester_key][] = $grade;
}

// Sort semesters by year and semester (most recent first)
uksort($grades_by_semester, function($a, $b) {
    // Extract year from semester_taken strings like "1st Semester SY 2024-2025"
    preg_match('/(\d{4}-\d{4})$/', $a, $matches_a);
    preg_match('/(\d{4}-\d{4})$/', $b, $matches_b);

    $year_a = $matches_a[1] ?? '0000-0000';
    $year_b = $matches_b[1] ?? '0000-0000';

    // Sort by year first (most recent first)
    if ($year_a !== $year_b) {
        return $year_b <=> $year_a;
    }

    // Then sort by semester within the same year (2nd semester first, then 1st, then summer)
    $semester_order = ['2nd Semester' => 1, '1st Semester' => 2, 'Summer' => 3];

    $sem_a = 'Unknown';
    $sem_b = 'Unknown';

    if (strpos($a, '2nd Semester') !== false) $sem_a = '2nd Semester';
    elseif (strpos($a, '1st Semester') !== false) $sem_a = '1st Semester';
    elseif (strpos($a, 'Summer') !== false) $sem_a = 'Summer';

    if (strpos($b, '2nd Semester') !== false) $sem_b = '2nd Semester';
    elseif (strpos($b, '1st Semester') !== false) $sem_b = '1st Semester';
    elseif (strpos($b, 'Summer') !== false) $sem_b = 'Summer';

    $order_a = $semester_order[$sem_a] ?? 999;
    $order_b = $semester_order[$sem_b] ?? 999;

    return $order_a <=> $order_b;
});

// Semester grouping complete
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades - CTU Honor System</title>
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
                    <div class="w-8 h-8 bg-primary-600 rounded-xl flex items-center justify-center">
                        <i data-lucide="graduation-cap" class="w-5 h-5 text-white"></i>
                    </div>
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
                    <a href="grades.php" class="bg-primary-50 border-r-2 border-primary-600 text-primary-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="bar-chart-3" class="text-primary-500 mr-3 h-5 w-5"></i>
                        My Grades
                    </a>
                    <a href="applications.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="trophy" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Applications
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
                            <h1 class="text-2xl font-bold text-gray-900">My Grades</h1>
                            <p class="text-sm text-gray-500">View your academic performance and GWA</p>
                        </div>
                    </div>

                    <a href="upload-grades.php" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors flex items-center">
                        <i data-lucide="upload" class="w-4 h-4 mr-2"></i>
                        Upload Grades
                    </a>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-4 sm:p-6 lg:p-8">


                    <!-- Overall GWA Summary Card -->
                    <?php if ($overall_gwa_data): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 mb-8">
                            <div class="flex flex-col lg:flex-row items-center lg:items-start">
                                <div class="text-center lg:text-left mb-6 lg:mb-0 lg:mr-8">
                                    <div class="text-6xl font-bold text-primary-600 mb-2">
                                        <?php echo formatGWA($overall_gwa_data['gwa']); ?>
                                    </div>
                                    <p class="text-lg text-gray-600">Overall GWA</p>
                                </div>
                                <div class="flex-1 grid grid-cols-2 lg:grid-cols-3 gap-6 w-full">
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-green-600">
                                            <?php echo number_format($overall_gwa_data['total_units'], 1); ?>
                                        </div>
                                        <p class="text-sm text-gray-500">Total Units</p>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-blue-600">
                                            <?php echo $overall_gwa_data['periods_count']; ?>
                                        </div>
                                        <p class="text-sm text-gray-500">Academic Periods</p>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-purple-600">
                                            <?php echo count($grades_by_semester); ?>
                                        </div>
                                        <p class="text-sm text-gray-500">Semesters</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Subject Grades -->
                    <?php if (!empty($all_grades)): ?>
                        <!-- Semester Filter -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 mb-6">
                            <div class="p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <i data-lucide="list" class="w-5 h-5 text-primary-600 mr-2"></i>
                                        All Subject Grades
                                    </h3>
                                    <span class="text-sm text-gray-500"><?php echo count($all_grades); ?> subjects found</span>
                                </div>

                                <div class="flex items-center space-x-4">
                                    <label for="semesterFilter" class="text-sm font-medium text-gray-700">Filter by Semester:</label>
                                    <select id="semesterFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                        <option value="all">All Semesters</option>
                                        <?php foreach (array_keys($grades_by_semester) as $semesterName): ?>
                                            <option value="<?php echo htmlspecialchars($semesterName); ?>">
                                                <?php echo htmlspecialchars($semesterName); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span id="filteredCount" class="text-sm text-gray-500"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Grades by Semester Sections -->
                        <div class="space-y-8">
                            <?php foreach ($grades_by_semester as $semesterName => $semesterGrades): ?>
                                <?php if (!empty($semesterGrades)): ?>
                                    <?php
                                        // Calculate semester stats
                                        $semester_total_units = 0;
                                        $semester_total_points = 0;
                                        $semester_completed = 0;
                                        $semester_nstp = 0;
                                        $semester_ongoing = 0;

                                        foreach ($semesterGrades as $grade) {
                                            if (strpos($grade['subject_name'], 'NSTP') !== false || strpos($grade['subject_name'], 'NATIONAL SERVICE TRAINING') !== false) {
                                                $semester_nstp++;
                                            } elseif ($grade['grade'] == 0) {
                                                $semester_ongoing++;
                                            } else {
                                                $semester_total_units += $grade['units'];
                                                $semester_total_points += ($grade['grade'] * $grade['units']);
                                                $semester_completed++;
                                            }
                                        }

                                        $semester_gwa = $semester_total_units > 0 ? $semester_total_points / $semester_total_units : 0;
                                    ?>
                                    <div class="semester-section bg-white rounded-2xl shadow-sm border border-gray-200" data-semester="<?php echo htmlspecialchars($semesterName); ?>">
                                        <!-- Semester Header -->
                                        <div class="p-6 border-b border-gray-200">
                                            <div class="flex items-center justify-between">
                                                <h4 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($semesterName); ?></h4>
                                                <?php if ($semester_gwa > 0): ?>
                                                    <div class="flex items-center space-x-6">
                                                        <div class="text-center">
                                                            <div class="text-2xl font-bold text-primary-600">
                                                                <?php echo formatGWA($semester_gwa); ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">GWA</div>
                                                        </div>
                                                        <div class="text-center">
                                                            <div class="text-lg font-semibold text-blue-600">
                                                                <?php echo $semester_total_units; ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">Units</div>
                                                        </div>
                                                        <div class="text-center">
                                                            <div class="text-lg font-semibold text-green-600">
                                                                <?php echo $semester_completed; ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">Subjects</div>
                                                        </div>
                                                        <?php if ($semester_nstp > 0): ?>
                                                            <div class="text-center">
                                                                <div class="text-lg font-semibold text-orange-600">
                                                                    <?php echo $semester_nstp; ?>
                                                                </div>
                                                                <div class="text-xs text-gray-500">NSTP</div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php elseif ($semester_ongoing > 0 || $semester_nstp > 0): ?>
                                                    <div class="text-center">
                                                        <div class="text-sm font-semibold text-gray-500">No GWA</div>
                                                        <div class="text-xs text-gray-400">Ongoing/NSTP only</div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Subjects Table -->
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject Code</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject Name</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    <?php foreach ($semesterGrades as $grade): ?>
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($grade['subject_code']); ?>
                                                            </td>
                                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                                <div class="font-medium"><?php echo htmlspecialchars($grade['subject_name']); ?></div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                <?php echo number_format($grade['units'], 1); ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $grade['grade'] <= 1.5 ? 'bg-green-100 text-green-800' : ($grade['grade'] <= 2.0 ? 'bg-yellow-100 text-yellow-800' : ($grade['grade'] <= 3.0 ? 'bg-orange-100 text-orange-800' : 'bg-red-100 text-red-800')); ?>">
                                                                    <?php echo number_format($grade['grade'], 1); ?>
                                                                </span>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $grade['remarks'] === 'PASSED' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                                    <?php echo htmlspecialchars($grade['remarks'] ?: 'N/A'); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-12 text-center">
                                <i data-lucide="inbox" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                                <h4 class="text-xl font-medium text-gray-900 mb-2">No grades available</h4>
                                <p class="text-gray-600 mb-4">No processed grade submissions found.</p>
                                <a href="upload-grades.php" class="inline-flex items-center px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-xl transition-colors">
                                    <i data-lucide="upload" class="w-5 h-5 mr-2"></i>
                                    Upload Grades
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Semester Filter Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const semesterFilter = document.getElementById('semesterFilter');
            const semesterSections = document.querySelectorAll('.semester-section');
            const filteredCount = document.getElementById('filteredCount');

            if (semesterFilter && semesterSections.length > 0) {
                // Update filtered count on page load
                updateFilteredCount();

                semesterFilter.addEventListener('change', function() {
                    const selectedSemester = this.value;
                    let visibleSections = 0;
                    let totalSubjects = 0;

                    semesterSections.forEach(function(section) {
                        const sectionSemester = section.getAttribute('data-semester');
                        const subjectsInSection = section.querySelectorAll('tbody tr').length;

                        if (selectedSemester === 'all' || selectedSemester === sectionSemester) {
                            section.style.display = 'block';
                            visibleSections++;
                            totalSubjects += subjectsInSection;
                        } else {
                            section.style.display = 'none';
                        }
                    });

                    updateFilteredCount(totalSubjects, visibleSections, selectedSemester);
                });
            }

            function updateFilteredCount(subjects = null, sections = null, semester = 'all') {
                if (subjects === null) {
                    // Calculate initial counts
                    subjects = 0;
                    sections = 0;
                    semesterSections.forEach(function(section) {
                        if (section.style.display !== 'none') {
                            subjects += section.querySelectorAll('tbody tr').length;
                            sections++;
                        }
                    });
                }

                if (semester === 'all') {
                    filteredCount.textContent = `Showing all ${subjects} subjects across ${sections} semesters`;
                } else {
                    filteredCount.textContent = `Showing ${subjects} subjects in ${semester}`;
                }
            }
        });
    </script>
</body>
</html>