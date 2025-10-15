<?php
require_once '../config/config.php';
require_once '../classes/GradeExtractor.php'; // Include the new grade extractor

requireLogin();

if (!hasRole('adviser')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$adviser_id = $_SESSION['user_id'];
$department = $_SESSION['department'];
$submission_id = $_GET['id'] ?? 0;

// Get submission details
$query = "SELECT gs.*, u.first_name, u.last_name, u.student_id, u.section, u.year_level, u.email,
                 processor.first_name as processor_first_name, processor.last_name as processor_last_name
          FROM grade_submissions gs 
          JOIN users u ON gs.user_id = u.id 
          LEFT JOIN users processor ON gs.processed_by = processor.id
          WHERE gs.id = :submission_id AND u.department = :department";
$stmt = $db->prepare($query);
$stmt->bindParam(':submission_id', $submission_id);
$stmt->bindParam(':department', $department);
$stmt->execute();
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission) {
    redirect('submissions.php');
}

// Get student's GWA if available
$query = "SELECT * FROM gwa_calculations WHERE user_id = :user_id ORDER BY calculated_at DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $submission['user_id']);
$stmt->execute();
$gwa_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Extract grades from PDF
$gradeExtractor = new GradeExtractor();

// Try different possible paths for the PDF file (same logic as submissions.php)
$possiblePaths = [
    __DIR__ . '/../uploads/' . $submission['file_path'],
    __DIR__ . '/../uploads/grades/' . $submission['file_path'],
    __DIR__ . '/../uploads/grades/' . basename($submission['file_path']),
    __DIR__ . '/../' . $submission['file_path']
];

$pdfPath = null;
foreach ($possiblePaths as $testPath) {
    if (file_exists($testPath) && is_readable($testPath)) {
        $pdfPath = $testPath;
        break;
    }
}

$extractedData = ['success' => false, 'grades' => [], 'semesters' => []];
if ($pdfPath) {
    $extractedData = $gradeExtractor->extractGradesFromPDF($pdfPath);
}

$grades = $extractedData['grades'] ?? [];
$semesters = $extractedData['semesters'] ?? [];

// DEBUG: Add debugging for final grades array (COMMENTED OUT - KEEP FOR LATER)
/*
if (!empty($extractedData['debug'])) {
    $extractedData['debug']['final_grades_array'] = [
        'total_count' => count($grades),
        'sample_grades' => array_slice($grades, 0, 5),
        'cs_grades_found' => array_filter($grades, function($g) { return strpos($g['subject_code'], 'CS') === 0; }),
        'semesters_count' => count($semesters)
    ];
}
*/

// Calculate GWA from extracted grades if available
$calculatedGWA = null;
$semesterGWAs = null;
if (!empty($grades)) {
    $calculatedGWA = $gradeExtractor->calculateGWA($grades);
    $semesterGWAs = $gradeExtractor->calculateGWAPerSemester($semesters);
}

// Handle form submissions for approve/reject
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        // Extract grades from PDF and save to database
        if (!$pdfPath) {
            $message = 'PDF file not found for extraction.';
            $message_type = 'error';
        } else if (!$extractedData['success'] || empty($extractedData['grades'])) {
            $message = 'Failed to extract grades from PDF: ' . ($extractedData['message'] ?? 'Unknown error');
            $message_type = 'error';
        } else {
            // Clear existing grades for this submission (in case of re-approval)
            $clear_query = "DELETE FROM grades WHERE submission_id = :submission_id";
            $clear_stmt = $db->prepare($clear_query);
            $clear_stmt->bindParam(':submission_id', $submission_id);
            $clear_stmt->execute();

            // Insert extracted grades into grades table
            $insert_grade_query = "INSERT INTO grades (submission_id, subject_code, subject_name, units, grade, semester_taken, remarks)
                                  VALUES (:submission_id, :subject_code, :subject_name, :units, :grade, :semester_taken, :remarks)";
            $insert_stmt = $db->prepare($insert_grade_query);

            $grades_inserted = 0;
            foreach ($extractedData['grades'] as $grade) {
                $insert_stmt->execute([
                    ':submission_id' => $submission_id,
                    ':subject_code' => $grade['subject_code'] ?? '',
                    ':subject_name' => $grade['subject_name'] ?? '',
                    ':units' => $grade['units'] ?? 0,
                    ':grade' => $grade['grade'] ?? 0,
                    ':semester_taken' => $grade['semester'] ?? '',
                    ':remarks' => $grade['remarks'] ?? ''
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
                // Calculate GWA using GradeProcessor
                require_once '../classes/GradeProcessor.php';
                $gradeProcessor = new GradeProcessor($db);
                $gradeProcessor->calculateGWA($submission_id);

                $message = "Submission approved successfully! Extracted {$grades_inserted} grades and calculated GWA.";
                $message_type = 'success';

                // Refresh submission data
                header("Location: view-submission.php?id=" . $submission_id . "&approved=1");
                exit();
            } else {
                $message = 'Failed to update submission status.';
                $message_type = 'error';
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
            
            header("Location: view-submission.php?id=" . $submission_id . "&rejected=1");
            exit();
        }
    }
}

if (isset($_GET['approved'])) {
    $message = 'Submission approved and processed successfully!';
    $message_type = 'success';
} elseif (isset($_GET['rejected'])) {
    $message = 'Submission has been rejected.';
    $message_type = 'success';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Submission - CTU Honor System</title>
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
    <div class="min-h-screen py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 mb-8">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <a href="submissions.php" class="mr-4 p-2 text-gray-400 hover:text-gray-600 rounded-xl hover:bg-gray-100">
                                <i data-lucide="arrow-left" class="w-5 h-5"></i>
                            </a>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Grade Submission Review</h1>
                                <p class="text-sm text-gray-500">Review and process student grade submission</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $submission['status'] === 'processed' ? 'bg-green-100 text-green-800' : ($submission['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                            <?php echo ucfirst($submission['status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-xl border <?php echo $message_type === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'; ?>">
                    <div class="flex items-center">
                        <i data-lucide="<?php echo $message_type === 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5 <?php echo $message_type === 'success' ? 'text-green-500' : 'text-red-500'; ?> mr-2"></i>
                        <span class="<?php echo $message_type === 'success' ? 'text-green-700' : 'text-red-700'; ?> text-sm"><?php echo htmlspecialchars($message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-8">
                    <!-- Student Information -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i data-lucide="user" class="w-5 h-5 text-primary-600 mr-2"></i>
                                Student Information
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Full Name</p>
                                    <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Student ID</p>
                                    <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($submission['student_id']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Section</p>
                                    <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars(formatSectionDisplay($submission['section'])); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Year Level</p>
                                    <p class="text-lg font-semibold text-gray-900">Year <?php echo $submission['year_level']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Extracted Grades Table -->
                    <?php if (!empty($grades)): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <i data-lucide="list" class="w-5 h-5 text-primary-600 mr-2"></i>
                                        Extracted Grades
                                    </h3>
                                    <span class="text-sm text-gray-500"><?php echo count($grades); ?> subjects found</span>
                                </div>                                
                                <!-- Semester Filter -->
                                <div class="flex items-center space-x-4">
                                    <label for="semesterFilter" class="text-sm font-medium text-gray-700">Filter by Semester:</label>
                                    <select id="semesterFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                        <option value="all">All Semesters</option>
                                        <?php foreach (array_keys($semesters) as $semesterName): ?>
                                            <option value="<?php echo htmlspecialchars($semesterName); ?>">
                                                <?php echo htmlspecialchars($semesterName); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span id="filteredCount" class="text-sm text-gray-500"></span>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <?php foreach ($semesters as $semesterName => $semesterGrades): ?>
                                    <?php if (!empty($semesterGrades)): ?>
                                        <div class="semester-section p-6 border-b border-gray-100 last:border-b-0" data-semester="<?php echo htmlspecialchars($semesterName); ?>">
                                            <div class="flex items-center justify-between mb-4">
                                                <h4 class="text-md font-semibold text-gray-800"><?php echo htmlspecialchars($semesterName); ?></h4>
                                                <?php if (!empty($semesterGWAs[$semesterName]) && $semesterGWAs[$semesterName]['gwa'] > 0): ?>
                                                    <div class="flex items-center space-x-4">
                                                        <div class="text-right">
                                                            <div class="text-lg font-bold text-primary-600">
                                                                <?php echo sprintf('%.2f', floor($semesterGWAs[$semesterName]['gwa'] * 100) / 100); ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">Semester GWA</div>
                                                        </div>
                                                        <div class="text-right">
                                                            <div class="text-sm font-semibold text-blue-600">
                                                                <?php echo $semesterGWAs[$semesterName]['total_units']; ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">Units</div>
                                                        </div>
                                                        <div class="text-right">
                                                            <div class="text-sm font-semibold text-green-600">
                                                                <?php echo $semesterGWAs[$semesterName]['completed_subjects']; ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">Academic</div>
                                                        </div>
                                                        <?php if ($semesterGWAs[$semesterName]['nstp_subjects'] > 0): ?>
                                                            <div class="text-right">
                                                                <div class="text-sm font-semibold text-orange-600">
                                                                    <?php echo $semesterGWAs[$semesterName]['nstp_subjects']; ?>
                                                                </div>
                                                                <div class="text-xs text-gray-500">NSTP</div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($semesterGWAs[$semesterName]['incomplete_subjects'] > 0): ?>
                                                            <div class="text-right">
                                                                <div class="text-sm font-semibold text-gray-600">
                                                                    <?php echo $semesterGWAs[$semesterName]['incomplete_subjects']; ?>
                                                                </div>
                                                                <div class="text-xs text-gray-500">Ongoing</div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php elseif (!empty($semesterGWAs[$semesterName])): ?>
                                                    <div class="text-right">
                                                        <div class="text-sm font-semibold text-gray-500">No GWA</div>
                                                        <div class="text-xs text-gray-400">All subjects ongoing/NSTP</div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject Code</th>
                                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject Name</th>
                                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units</th>
                                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                        <?php foreach ($semesterGrades as $grade): ?>
                                                            <tr class="hover:bg-gray-50">
                                                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                    <?php echo htmlspecialchars($grade['subject_code']); ?>
                                                                </td>
                                                                <td class="px-4 py-4 text-sm text-gray-900">
                                                                    <div class="font-medium"><?php echo htmlspecialchars($grade['subject_name']); ?></div>
                                                                    <?php if (!empty($grade['subject_type'])): ?>
                                                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($grade['subject_type']); ?></div>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    <?php echo number_format($grade['units'], 2); ?>
                                                                </td>
                                                                <td class="px-4 py-4 whitespace-nowrap">
                                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $grade['grade'] <= 1.5 ? 'bg-green-100 text-green-800' : ($grade['grade'] <= 2.0 ? 'bg-yellow-100 text-yellow-800' : ($grade['grade'] <= 3.0 ? 'bg-orange-100 text-orange-800' : 'bg-red-100 text-red-800')); ?>">
                                                                        <?php echo number_format($grade['grade'], 1); ?>
                                                                    </span>
                                                                </td>
                                                                <td class="px-4 py-4 whitespace-nowrap">
                                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $grade['remarks'] === 'PASSED' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                                        <?php echo htmlspecialchars($grade['remarks']); ?>
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
                            
                            <!-- DEBUG INFO (COMMENTED OUT - KEEP FOR LATER) -->
                            <?php /* DEBUG BLOCK - UNCOMMENT WHEN NEEDED
                            if (!empty($extractedData['debug'])): ?>
                                <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                                    <h5 class="font-semibold mb-2 text-sm">Debug Information:</h5>
                                    <div class="text-xs space-y-2">
                                        <div><strong>Total grades found:</strong> <?php echo $extractedData['debug']['total_grades_found'] ?? 0; ?></div>
                                        <div><strong>Non-empty lines:</strong> <?php echo $extractedData['debug']['non_empty_lines'] ?? 0; ?></div>
                                        
                                        <?php if (!empty($extractedData['debug']['unparsed_cs_lines'])): ?>
                                            <div>
                                                <strong>CS subject lines that weren't parsed (<?php echo count($extractedData['debug']['unparsed_cs_lines']); ?> missing):</strong>
                                                <ul class="bg-white p-2 rounded text-xs max-h-40 overflow-y-auto">
                                                    <?php foreach ($extractedData['debug']['unparsed_cs_lines'] as $line): ?>
                                                        <li class="mb-1 p-1 bg-red-50">Line <?php echo $line['line_number']; ?>: <?php echo htmlspecialchars($line['content']); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                        
                                        [ALL OTHER DEBUG SECTIONS PRESERVED HERE]
                                        
                                    </div>
                                </div>
                            <?php endif; 
                            END DEBUG BLOCK */ ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-12 text-center">
                                <i data-lucide="file-x" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">No Grades Extracted</h4>
                                <p class="text-gray-500">
                                    <?php echo $extractedData['success'] ? 'No valid grade data could be extracted from the PDF file.' : htmlspecialchars($extractedData['message']); ?>
                                </p>
                                
                                <?php /* DEBUG BLOCK FOR NO GRADES - COMMENTED OUT - KEEP FOR LATER
                                if (!empty($extractedData['debug'])): ?>
                                    <div class="mt-6 text-left bg-gray-50 p-4 rounded-lg">
                                        <h5 class="font-semibold mb-2">Debug Information:</h5>
                                        <div class="text-xs space-y-2">
                                            <?php if (isset($extractedData['debug']['raw_text_preview'])): ?>
                                                <div>
                                                    <strong>Raw text preview:</strong>
                                                    <pre class="bg-white p-2 rounded text-xs max-h-40 overflow-y-auto"><?php echo htmlspecialchars($extractedData['debug']['raw_text_preview']); ?></pre>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($extractedData['debug']['first_10_lines'])): ?>
                                                <div>
                                                    <strong>First 10 non-empty lines:</strong>
                                                    <ol class="bg-white p-2 rounded text-xs max-h-40 overflow-y-auto">
                                                        <?php foreach ($extractedData['debug']['first_10_lines'] as $i => $line): ?>
                                                            <li><?php echo htmlspecialchars($line); ?></li>
                                                        <?php endforeach; ?>
                                                    </ol>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($extractedData['debug']['potential_grade_lines'])): ?>
                                                <div>
                                                    <strong>Potential grade lines found:</strong>
                                                    <ul class="bg-white p-2 rounded text-xs max-h-40 overflow-y-auto">
                                                        <?php foreach ($extractedData['debug']['potential_grade_lines'] as $line): ?>
                                                            <li>Line <?php echo $line['line_number']; ?>: <?php echo htmlspecialchars($line['content']); ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; 
                                END DEBUG BLOCK FOR NO GRADES */ ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Calculated GWA Summary -->
                    <?php if ($calculatedGWA): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="calculator" class="w-5 h-5 text-green-600 mr-2"></i>
                                    Calculated GWA
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-2 lg:grid-cols-5 gap-6">
                                    <div class="text-center">
                                        <div class="text-3xl font-bold text-primary-600">
                                            <?php echo sprintf('%.2f', floor($calculatedGWA['gwa'] * 100) / 100); ?>
                                        </div>
                                        <p class="text-sm text-gray-500">GWA</p>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-blue-600">
                                            <?php echo $calculatedGWA['total_units']; ?>
                                        </div>
                                        <p class="text-sm text-gray-500">Completed Units</p>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-green-600">
                                            <?php echo $calculatedGWA['completed_subjects']; ?>
                                        </div>
                                        <p class="text-sm text-gray-500">Completed</p>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-orange-600">
                                            <?php echo $calculatedGWA['incomplete_subjects']; ?>
                                        </div>
                                        <p class="text-sm text-gray-500">Ongoing</p>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-red-600">
                                            <?php echo $calculatedGWA['failed_subjects']; ?>
                                        </div>
                                        <p class="text-sm text-gray-500">Failed</p>
                                    </div>
                                </div>
                                
                                <!-- Additional Information -->
                                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                                    <p class="text-sm text-gray-600">
                                        <strong>Note:</strong> GWA calculation excludes:
                                    </p>
                                    <ul class="text-sm text-gray-600 mt-2 ml-4 list-disc">
                                        <li>Ongoing subjects: <?php echo $calculatedGWA['incomplete_subjects']; ?> subjects</li>
                                        <?php if ($calculatedGWA['nstp_subjects'] > 0): ?>
                                            <li>NSTP subjects: <?php echo $calculatedGWA['nstp_subjects']; ?> subjects</li>
                                        <?php endif; ?>
                                    </ul>
                                    <p class="text-sm text-gray-600 mt-2">
                                        <strong>GWA based on:</strong> <?php echo $calculatedGWA['completed_subjects']; ?> academic subjects with <?php echo $calculatedGWA['total_units']; ?> units.
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- GWA Per Semester Summary -->
                    <?php if (!empty($semesterGWAs)): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="trending-up" class="w-5 h-5 text-green-600 mr-2"></i>
                                    GWA Progress by Semester
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <?php foreach ($semesterGWAs as $semesterName => $semesterGWA): ?>
                                        <div class="bg-gray-50 rounded-lg p-4">
                                            <h4 class="text-sm font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($semesterName); ?></h4>
                                            <?php if ($semesterGWA['gwa'] > 0): ?>
                                                <div class="text-2xl font-bold text-primary-600 mb-1">
                                                    <?php echo sprintf('%.2f', floor($semesterGWA['gwa'] * 100) / 100); ?>
                                                </div>
                                                <div class="text-xs text-gray-600">
                                                    <?php echo $semesterGWA['completed_subjects']; ?> subjects, 
                                                    <?php echo $semesterGWA['total_units']; ?> units
                                                    <?php if ($semesterGWA['nstp_subjects'] > 0): ?>
                                                        <br><span class="text-orange-600"><?php echo $semesterGWA['nstp_subjects']; ?> NSTP excluded</span>
                                                    <?php endif; ?>
                                                    <?php if ($semesterGWA['incomplete_subjects'] > 0): ?>
                                                        <br><span class="text-gray-500"><?php echo $semesterGWA['incomplete_subjects']; ?> ongoing</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-lg font-semibold text-gray-500 mb-1">No GWA</div>
                                                <div class="text-xs text-gray-500">
                                                    <?php if ($semesterGWA['incomplete_subjects'] > 0): ?>
                                                        <?php echo $semesterGWA['incomplete_subjects']; ?> ongoing subjects
                                                    <?php endif; ?>
                                                    <?php if ($semesterGWA['nstp_subjects'] > 0): ?>
                                                        <?php echo $semesterGWA['incomplete_subjects'] > 0 ? ', ' : ''; ?><?php echo $semesterGWA['nstp_subjects']; ?> NSTP subjects
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Submission Details -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">Submission Details</h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500">File Name</p>
                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($submission['file_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">File Size</p>
                                <p class="text-sm text-gray-900"><?php echo number_format($submission['file_size'] / 1024, 1); ?> KB</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Upload Date</p>
                                <p class="text-sm text-gray-900"><?php echo date('M d, Y g:i A', strtotime($submission['upload_date'])); ?></p>
                            </div>
                            <?php if ($submission['processed_by']): ?>
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Processed By</p>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($submission['processor_first_name'] . ' ' . $submission['processor_last_name']); ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if ($submission['rejection_reason']): ?>
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Rejection Reason</p>
                                    <p class="text-sm text-red-600"><?php echo htmlspecialchars($submission['rejection_reason']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <?php if ($submission['status'] === 'pending'): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900">Actions</h3>
                            </div>
                            <div class="p-6 space-y-3">
                                <form method="POST" class="w-full">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" onclick="return confirm('Are you sure you want to approve this submission?')" 
                                            class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-xl font-medium transition-colors flex items-center justify-center">
                                        <i data-lucide="check" class="w-4 h-4 mr-2"></i>
                                        Approve Submission
                                    </button>
                                </form>
                                <button onclick="showRejectModal()" 
                                        class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-xl font-medium transition-colors flex items-center justify-center">
                                    <i data-lucide="x" class="w-4 h-4 mr-2"></i>
                                    Reject Submission
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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

        function showRejectModal() {
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