<?php
require_once '../config/config.php';
require_once '../classes/GradeExtractor.php'; // Include the grade extractor

requireLogin();

if (!hasRole('adviser')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();
$gradeProcessor = new GradeProcessor($db);
$gradeExtractor = new GradeExtractor();

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

// Handle submission processing
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $submission_id = $_POST['submission_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        // Get submission details
        $sub_query = "SELECT * FROM grade_submissions WHERE id = :submission_id";
        $sub_stmt = $db->prepare($sub_query);
        $sub_stmt->bindParam(':submission_id', $submission_id);
        $sub_stmt->execute();
        $submission_data = $sub_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$submission_data) {
            $message = 'Submission not found.';
            $message_type = 'error';
        } else {
            // Extract grades from PDF first
            $possiblePaths = [
                __DIR__ . '/../uploads/' . $submission_data['file_path'],
                __DIR__ . '/../uploads/grades/' . $submission_data['file_path'],
                __DIR__ . '/../uploads/grades/' . basename($submission_data['file_path']),
                __DIR__ . '/../' . $submission_data['file_path']
            ];

            $pdfPath = null;
            foreach ($possiblePaths as $testPath) {
                if (file_exists($testPath) && is_readable($testPath)) {
                    $pdfPath = $testPath;
                    break;
                }
            }

            if (!$pdfPath) {
                $message = 'PDF file not found for extraction.';
                $message_type = 'error';
            } else {
                // Extract grades from PDF
                $extractedData = $gradeExtractor->extractGradesFromPDF($pdfPath);

                if (!$extractedData['success'] || empty($extractedData['grades'])) {
                    $message = 'Failed to extract grades from PDF: ' . ($extractedData['message'] ?? 'Unknown error');
                    $message_type = 'error';
                } else {
                    // Clear existing grades for this submission (in case of re-approval)
                    $clear_query = "DELETE FROM grades WHERE submission_id = :submission_id";
                    $clear_stmt = $db->prepare($clear_query);
                    $clear_stmt->bindParam(':submission_id', $submission_id);
                    $clear_stmt->execute();

                    // Insert extracted grades into grades table
                    $insert_grade_query = "INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, semester_taken)
                                          VALUES (:submission_id, :subject_code, :subject_name, :units, :grade, :semester_taken)";
                    $insert_stmt = $db->prepare($insert_grade_query);

                    $grades_inserted = 0;
                    foreach ($extractedData['grades'] as $grade) {
                        $insert_stmt->execute([
                            ':submission_id' => $submission_id,
                            ':subject_code' => $grade['subject_code'] ?? '',
                            ':subject_name' => $grade['subject_name'] ?? '',
                            ':units' => $grade['units'] ?? 0,
                            ':grade' => $grade['grade'] ?? 0,
                            ':semester_taken' => $grade['semester'] ?? ''
                        ]);
                        $grades_inserted++;
                    }

                    // Update submission status to processed
                    $query = "UPDATE grade_submissions
                              SET status = 'processed', processed_at = NOW(), processed_by = :adviser_id
                              WHERE id = :submission_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':adviser_id', $adviser_id);
                    $stmt->bindParam(':submission_id', $submission_id);

                    if ($stmt->execute()) {
                        // Calculate GWA
                        $gradeProcessor->calculateGWA($submission_id);
                        $message = "Submission approved successfully! Extracted {$grades_inserted} grades and calculated GWA.";
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to update submission status.';
                        $message_type = 'error';
                    }
                }
            }
        }
    } elseif ($action === 'reject') {
        $rejection_reason = $_POST['rejection_reason'] ?? '';
        
        $query = "UPDATE grade_submissions 
                  SET status = 'rejected', processed_at = NOW(), processed_by = :adviser_id, rejection_reason = :reason 
                  WHERE id = :submission_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':adviser_id', $adviser_id);
        $stmt->bindParam(':submission_id', $submission_id);
        $stmt->bindParam(':reason', $rejection_reason);
        
        if ($stmt->execute()) {
            $message = 'Submission rejected successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to reject submission.';
            $message_type = 'error';
        }
    }
}

// Get current academic period
$current_period_query = "SELECT id FROM academic_periods WHERE is_active = 1 LIMIT 1";
$current_period_stmt = $db->prepare($current_period_query);
$current_period_stmt->execute();
$current_period = $current_period_stmt->fetch(PDO::FETCH_ASSOC);
$current_academic_period_id = $current_period['id'] ?? 1;

// Get submissions for adviser's assigned sections (current academic period only)
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

    $query = "SELECT gs.*, u.first_name, u.last_name, u.student_id, u.section, u.year_level,
                     processor.first_name as processor_first_name, processor.last_name as processor_last_name
              FROM grade_submissions gs
              JOIN users u ON gs.user_id = u.id
              LEFT JOIN users processor ON gs.processed_by = processor.id
              WHERE u.department = ? AND $section_where AND gs.academic_period_id = ?
              ORDER BY gs.upload_date DESC";

    $params[] = $current_academic_period_id;
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug: Check what sections and students exist
    $debug_query = "SELECT COUNT(*) as student_count, GROUP_CONCAT(DISTINCT section) as sections_found,
                           GROUP_CONCAT(DISTINCT CONCAT(first_name, ' ', last_name, ' (', section, ')')) as students_list
                    FROM users
                    WHERE department = ? AND role = 'student'";
    $debug_stmt = $db->prepare($debug_query);
    $debug_stmt->execute([$department]);
    $debug_result = $debug_stmt->fetch(PDO::FETCH_ASSOC);

    // Debug: Check total submissions in the academic period
    $total_submissions_query = "SELECT COUNT(*) as total_submissions FROM grade_submissions WHERE academic_period_id = ?";
    $total_stmt = $db->prepare($total_submissions_query);
    $total_stmt->execute([$current_academic_period_id]);
    $total_result = $total_stmt->fetch(PDO::FETCH_ASSOC);

    // Debug: Check all submissions regardless of section matching
    $all_submissions_query = "SELECT gs.*, u.first_name, u.last_name, u.student_id, u.section, u.year_level
                              FROM grade_submissions gs
                              JOIN users u ON gs.user_id = u.id
                              WHERE u.department = ? AND gs.academic_period_id = ?
                              ORDER BY gs.upload_date DESC";
    $all_stmt = $db->prepare($all_submissions_query);
    $all_stmt->execute([$department, $current_academic_period_id]);
    $all_submissions = $all_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Extract grade counts for each submission
    foreach ($submissions as $index => $submission) {
        // Try different possible paths for the PDF file
        $possiblePaths = [
            __DIR__ . '/../uploads/' . $submission['file_path'],
            __DIR__ . '/../uploads/grades/' . $submission['file_path'],
            __DIR__ . '/../uploads/grades/' . basename($submission['file_path']),
            __DIR__ . '/../' . $submission['file_path']
        ];

        $pdfPath = null;
        $extractedData = ['success' => false, 'grades' => []];

        // Find the correct path
        foreach ($possiblePaths as $testPath) {
            if (file_exists($testPath) && is_readable($testPath)) {
                $pdfPath = $testPath;
                break;
            }
        }

        // Extract grades if file found
        if ($pdfPath) {
            $extractedData = $gradeExtractor->extractGradesFromPDF($pdfPath);
        }

        $submissions[$index]['extracted_grades_count'] = count($extractedData['grades'] ?? []);
        $submissions[$index]['extraction_success'] = $extractedData['success'];
        $submissions[$index]['pdf_path_found'] = $pdfPath ? 'Yes' : 'No';
        $submissions[$index]['tested_path'] = $pdfPath;
    }
    unset($submission); // Clean up any reference
} else {
    $submissions = [];
}

// Filter submissions
$filter = $_GET['filter'] ?? 'all';
if ($filter !== 'all') {
    $submissions = array_filter($submissions, function($submission) use ($filter) {
        return $submission['status'] === $filter;
    });
    // Re-index array to avoid gaps in indices
    $submissions = array_values($submissions);
}

// Group submissions by section
$submissions_by_section = [];
foreach ($submissions as $submission) {
    $section = $submission['section'];
    if (!isset($submissions_by_section[$section])) {
        $submissions_by_section[$section] = [];
    }
    $submissions_by_section[$section][] = $submission;
}

// Sort sections alphabetically
ksort($submissions_by_section);

function getStatusBadgeClass($status) {
    switch($status) {
        case 'processed': return 'bg-green-100 text-green-800';
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'rejected': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Submissions - CTU Honor System</title>
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
                    <div class="w-8 h-8 bg-green-600 rounded-xl flex items-center justify-center">
                        <i data-lucide="graduation-cap" class="w-5 h-5 text-white"></i>
                    </div>
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
                    <a href="submissions.php" class="bg-green-50 border-r-2 border-green-600 text-green-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="file-text" class="text-green-500 mr-3 h-5 w-5"></i>
                        Grade Submissions
                    </a>
                    <a href="applications.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="trophy" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Honor Applications
                    </a>
                    <a href="students.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="users" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Students
                    </a>
                    <a href="reports.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="bar-chart-3" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Reports
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
                            <h1 class="text-2xl font-bold text-gray-900">Grade Submissions</h1>
                            <?php if (!empty($adviser_sections)): ?>
                                <p class="text-sm text-gray-500">
                                    Processing submissions from sections:
                                    <?php foreach ($adviser_sections as $index => $section): ?>
                                        <span class="font-semibold text-primary-600"><?php echo htmlspecialchars($section); ?></span><?php if ($index < count($adviser_sections) - 1) echo ', '; ?>
                                    <?php endforeach; ?>
                                    <?php echo $adviser_year_level ? ' (Year ' . $adviser_year_level . ')' : ''; ?> -
                                    <?php echo count($submissions); ?> submissions
                                </p>
                            <?php else: ?>
                                <div class="flex items-center">
                                    <p class="text-sm text-gray-500 mr-2">
                                        No submissions available - section assignment required
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
                        <select onchange="filterSubmissions(this.value)" 
                                class="px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Submissions</option>
                            <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processed" <?php echo $filter === 'processed' ? 'selected' : ''; ?>>Processed</option>
                            <option value="rejected" <?php echo $filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
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

                    <!-- Submissions by Section -->
                    <?php if (empty($submissions)): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="text-center py-12">
                                <i data-lucide="inbox" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                                <h4 class="text-xl font-medium text-gray-900 mb-2">No submissions found</h4>
                                <?php if (empty($adviser_sections)): ?>
                                    <p class="text-gray-500 mb-4">You haven't been assigned to any sections yet.</p>
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mx-8">
                                        <div class="flex items-start">
                                            <i data-lucide="info" class="w-5 h-5 text-yellow-600 mr-3 mt-0.5 flex-shrink-0"></i>
                                            <div class="text-left">
                                                <h5 class="text-yellow-800 font-semibold text-sm mb-1">Section Assignment Required</h5>
                                                <p class="text-yellow-700 text-sm">Contact your department chairperson to get assigned to student sections. Once assigned, you'll be able to view and process grade submissions from your students.</p>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="text-gray-500 mb-4">No grade submissions found for your assigned sections.</p>
                                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mx-8 mb-4">
                                        <div class="flex items-start">
                                            <i data-lucide="users" class="w-5 h-5 text-blue-600 mr-3 mt-0.5 flex-shrink-0"></i>
                                            <div class="text-left">
                                                <h5 class="text-blue-800 font-semibold text-sm mb-1">Your Assigned Sections</h5>
                                                <p class="text-blue-700 text-sm mb-2">
                                                    You are assigned to sections:
                                                    <?php foreach ($adviser_sections as $index => $section): ?>
                                                        <span class="font-semibold"><?php echo htmlspecialchars($section); ?></span><?php if ($index < count($adviser_sections) - 1) echo ', '; ?>
                                                    <?php endforeach; ?>
                                                    <?php echo $adviser_year_level ? ' (Year ' . $adviser_year_level . ')' : ''; ?>
                                                </p>
                                                <p class="text-blue-700 text-sm">Students in these sections can submit their grade documents, which will appear here for your review and approval.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (isset($debug_result)): ?>
                                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mx-8">
                                            <div class="text-left">
                                                <h5 class="text-gray-800 font-semibold text-sm mb-2">Debug Information</h5>
                                                <div class="text-gray-700 text-sm space-y-1">
                                                    <p><span class="font-medium">Total students in your department:</span> <?php echo $debug_result['student_count']; ?></p>
                                                    <p><span class="font-medium">Sections found in database:</span> <?php echo $debug_result['sections_found'] ?: 'None'; ?></p>
                                                    <p><span class="font-medium">Your department:</span> <?php echo htmlspecialchars($department); ?></p>
                                                    <p><span class="font-medium">Current academic period ID:</span> <?php echo $current_academic_period_id; ?></p>
                                                    <?php if (isset($total_result)): ?>
                                                        <p><span class="font-medium">Total submissions this period:</span> <?php echo $total_result['total_submissions']; ?></p>
                                                    <?php endif; ?>
                                                    <?php if (isset($all_submissions) && !empty($all_submissions)): ?>
                                                        <p><span class="font-medium">All submissions in your department:</span></p>
                                                        <div class="ml-4 mt-1 space-y-1">
                                                            <?php foreach ($all_submissions as $sub): ?>
                                                                <p class="text-xs">• <?php echo htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']); ?> (Section: <?php echo htmlspecialchars($sub['section'] ?: 'None'); ?>)</p>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (isset($debug_result['students_list']) && $debug_result['students_list']): ?>
                                                        <p><span class="font-medium">Students in your department:</span></p>
                                                        <div class="ml-4 mt-1 text-xs">
                                                            <?php
                                                            $students = explode(',', $debug_result['students_list']);
                                                            foreach ($students as $student): ?>
                                                                <p>• <?php echo htmlspecialchars(trim($student)); ?></p>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($submissions_by_section as $section => $section_submissions): ?>
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 mb-6">
                                <!-- Section Header -->
                                <div class="px-6 py-4 border-b border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-primary-100 rounded-xl flex items-center justify-center">
                                                <i data-lucide="users" class="w-4 h-4 text-primary-600"></i>
                                            </div>
                                            <div class="ml-3">
                                                <h3 class="text-lg font-semibold text-gray-900">Section <?php echo htmlspecialchars($section); ?></h3>
                                                <p class="text-sm text-gray-500"><?php echo count($section_submissions); ?> submissions</p>
                                            </div>
                                        </div>
                                        <div class="flex space-x-2">
                                            <?php
                                            $pending_count = count(array_filter($section_submissions, fn($s) => $s['status'] === 'pending'));
                                            $processed_count = count(array_filter($section_submissions, fn($s) => $s['status'] === 'processed'));
                                            $rejected_count = count(array_filter($section_submissions, fn($s) => $s['status'] === 'rejected'));
                                            ?>
                                            <?php if ($pending_count > 0): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    <?php echo $pending_count; ?> Pending
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($processed_count > 0): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <?php echo $processed_count; ?> Processed
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($rejected_count > 0): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    <?php echo $rejected_count; ?> Rejected
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section Submissions Table -->
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grades Extracted</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Upload Date</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Processed By</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($section_submissions as $submission): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div>
                                                            <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?></div>
                                                            <div class="text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($submission['student_id']); ?> •
                                                                Year <?php echo $submission['year_level']; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div>
                                                            <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($submission['file_name']); ?></div>
                                                            <div class="text-sm text-gray-500"><?php echo number_format($submission['file_size'] / 1024, 1); ?> KB</div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <?php if ($submission['extraction_success']): ?>
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800" title="File found and processed successfully">
                                                                    <i data-lucide="check" class="w-3 h-3 mr-1"></i>
                                                                    <?php echo $submission['extracted_grades_count']; ?> subjects
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800"
                                                                      title="File: <?php echo $submission['file_path']; ?> | Found: <?php echo $submission['pdf_path_found'] ?? 'Unknown'; ?>">
                                                                    <i data-lucide="alert-triangle" class="w-3 h-3 mr-1"></i>
                                                                    Extraction failed
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php echo date('M d, Y g:i A', strtotime($submission['upload_date'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getStatusBadgeClass($submission['status']); ?>">
                                                            <?php echo ucfirst($submission['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php if ($submission['processed_by']): ?>
                                                            <?php echo htmlspecialchars($submission['processor_first_name'] . ' ' . $submission['processor_last_name']); ?>
                                                        <?php else: ?>
                                                            Not processed
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <?php if ($submission['status'] === 'pending'): ?>
                                                            <div class="flex space-x-2">
                                                                <button onclick="processSubmission(<?php echo $submission['id']; ?>, 'approve')"
                                                                        class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-lg text-xs transition-colors">
                                                                    <i data-lucide="check" class="w-3 h-3 inline mr-1"></i>
                                                                    Approve
                                                                </button>
                                                                <button onclick="showRejectModal(<?php echo $submission['id']; ?>)"
                                                                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-lg text-xs transition-colors">
                                                                    <i data-lucide="x" class="w-3 h-3 inline mr-1"></i>
                                                                    Reject
                                                                </button>
                                                                <a href="view-submission.php?id=<?php echo $submission['id']; ?>"
                                                                   class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg text-xs transition-colors inline-flex items-center">
                                                                    <i data-lucide="eye" class="w-3 h-3 mr-1"></i>
                                                                    View
                                                                </a>
                                                            </div>
                                                        <?php else: ?>
                                                            <a href="view-submission.php?id=<?php echo $submission['id']; ?>"
                                                               class="text-primary-600 hover:text-primary-700 font-medium inline-flex items-center">
                                                                <i data-lucide="eye" class="w-4 h-4 mr-1"></i>
                                                                View
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-2xl bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Reject Submission</h3>
                    <button onclick="hideRejectModal()" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <form method="POST">
                    <input type="hidden" name="submission_id" id="rejectSubmissionId">
                    <input type="hidden" name="action" value="reject">
                    <div class="mb-4">
                        <label for="rejection_reason" class="block text-sm font-semibold text-gray-700 mb-2">Reason for Rejection</label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="3" required 
                                  class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                                  placeholder="Please provide a reason for rejecting this submission..."></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideRejectModal()" 
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl transition-colors">
                            Reject Submission
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function filterSubmissions(filter) {
            window.location.href = 'submissions.php?filter=' + filter;
        }

        function processSubmission(submissionId, action) {
            if (action === 'approve' && confirm('Are you sure you want to approve this submission?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="submission_id" value="${submissionId}">
                    <input type="hidden" name="action" value="approve">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function showRejectModal(submissionId) {
            document.getElementById('rejectSubmissionId').value = submissionId;
            document.getElementById('rejectModal').classList.remove('hidden');
        }

        function hideRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideRejectModal();
            }
        });
    </script>
</body>
</html>