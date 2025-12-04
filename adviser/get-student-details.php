<?php
require_once '../config/config.php';
require_once '../classes/GradeProcessor.php';

header('Content-Type: application/json');

requireLogin();

if (!hasRole('adviser')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$student_id = $_GET['id'] ?? null;

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Student ID required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $gradeProcessor = new GradeProcessor($db);

    // Get adviser info
    $adviser_id = $_SESSION['user_id'];
    $department = $_SESSION['department'];

    // Get adviser's section to verify access
    $adviser_query = "SELECT section, year_level FROM users WHERE id = :adviser_id";
    $adviser_stmt = $db->prepare($adviser_query);
    $adviser_stmt->bindParam(':adviser_id', $adviser_id);
    $adviser_stmt->execute();
    $adviser_info = $adviser_stmt->fetch(PDO::FETCH_ASSOC);

    // Decode adviser section from JSON if needed
    $adviser_section = $adviser_info['section'] ?? null;
    if ($adviser_section) {
        $sections_array = json_decode($adviser_section, true);
        $adviser_section = is_array($sections_array) && !empty($sections_array) ? $sections_array[0] : $adviser_section;
    }

    // Build flexible section matching conditions
    $section_conditions = [];
    $section_params = [];
    if ($adviser_section) {
        // Try exact match and partial matches
        $section_conditions[] = "u.section = ?";
        $section_params[] = $adviser_section;

        // If section is like "C-4", also try "C" and "4"
        if (strpos($adviser_section, '-') !== false) {
            $parts = explode('-', $adviser_section);
            foreach ($parts as $part) {
                $section_conditions[] = "u.section = ?";
                $section_params[] = trim($part);
            }
        }

        // Also try partial matches using LIKE
        $section_conditions[] = "u.section LIKE ?";
        $section_params[] = '%' . $adviser_section . '%';
    }

    // Get comprehensive student information
    $student_query = "
        SELECT u.*,
               -- Calculate overall GWA from all grades across all semesters
               (SELECT
                   CASE WHEN SUM(g.units) > 0 THEN
                       SUM(g.units * g.grade) / SUM(g.units)
                   ELSE NULL END
                FROM grades g
                JOIN grade_submissions gs ON g.submission_id = gs.id
                WHERE gs.user_id = u.id
                AND g.grade > 0.00
                AND NOT (UPPER(g.subject_name) LIKE '%NSTP%' OR UPPER(g.subject_name) LIKE '%NATIONAL SERVICE TRAINING%')
               ) as gwa,
               -- Get total units and subjects count from all grades
               (SELECT SUM(g.units) FROM grades g
                JOIN grade_submissions gs ON g.submission_id = gs.id
                WHERE gs.user_id = u.id AND g.grade > 0.00
                AND NOT (UPPER(g.subject_name) LIKE '%NSTP%' OR UPPER(g.subject_name) LIKE '%NATIONAL SERVICE TRAINING%')
               ) as total_units,
               (SELECT COUNT(*) FROM grades g
                JOIN grade_submissions gs ON g.submission_id = gs.id
                WHERE gs.user_id = u.id AND g.grade > 0.00
                AND NOT (UPPER(g.subject_name) LIKE '%NSTP%' OR UPPER(g.subject_name) LIKE '%NATIONAL SERVICE TRAINING%')
               ) as subjects_count,
               -- Get most recent calculation date
               (SELECT calculated_at FROM gwa_calculations WHERE user_id = u.id ORDER BY calculated_at DESC LIMIT 1) as calculated_at,
               (SELECT COUNT(*) FROM grade_submissions gs WHERE gs.user_id = u.id) as total_submissions,
               (SELECT COUNT(*) FROM grade_submissions gs WHERE gs.user_id = u.id AND gs.status = 'processed') as processed_submissions,
               (SELECT COUNT(*) FROM grade_submissions gs WHERE gs.user_id = u.id AND gs.status = 'pending') as pending_submissions,
               (SELECT COUNT(*) FROM honor_applications ha WHERE ha.user_id = u.id) as total_applications,
               (SELECT COUNT(*) FROM honor_applications ha WHERE ha.user_id = u.id AND ha.status = 'final_approved') as approved_applications,
               (SELECT COUNT(*) FROM honor_applications ha WHERE ha.user_id = u.id AND ha.status = 'pending') as pending_applications
        FROM users u
        WHERE u.id = ?
        AND u.role = 'student'
        AND u.department = ?
        AND u.status = 'active'
    ";

    // Build query parameters
    $params = [$student_id, $department];

    // If adviser has a specific section, only show students from that section
    if ($adviser_section && !empty($section_conditions)) {
        $section_where = '(' . implode(' OR ', $section_conditions) . ')';
        $student_query .= " AND $section_where";
        $params = array_merge($params, $section_params);
    }

    $stmt = $db->prepare($student_query);
    $stmt->execute($params);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found or access denied']);
        exit;
    }

    // Get recent grade submissions
    $submissions_query = "
        SELECT gs.*, ap.period_name, ap.semester, ap.school_year,
               (SELECT COUNT(*) FROM grades g WHERE g.submission_id = gs.id) as grades_count
        FROM grade_submissions gs
        LEFT JOIN academic_periods ap ON gs.academic_period_id = ap.id
        WHERE gs.user_id = :student_id
        ORDER BY gs.upload_date DESC
        LIMIT 5
    ";
    $submissions_stmt = $db->prepare($submissions_query);
    $submissions_stmt->bindParam(':student_id', $student_id);
    $submissions_stmt->execute();
    $submissions = $submissions_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent honor applications
    $applications_query = "
        SELECT ha.*, ap.period_name, ap.semester, ap.school_year
        FROM honor_applications ha
        LEFT JOIN academic_periods ap ON ha.academic_period_id = ap.id
        WHERE ha.user_id = :student_id
        ORDER BY ha.submitted_at DESC
        LIMIT 5
    ";
    $applications_stmt = $db->prepare($applications_query);
    $applications_stmt->bindParam(':student_id', $student_id);
    $applications_stmt->execute();
    $applications = $applications_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get student's grades for current period (if any)
    $current_grades = [];
    if ($student['processed_submissions'] > 0) {
        // Get most recent processed submission
        $recent_submission_query = "
            SELECT id, academic_period_id FROM grade_submissions
            WHERE user_id = :student_id AND status = 'processed'
            ORDER BY processed_at DESC LIMIT 1
        ";
        $recent_stmt = $db->prepare($recent_submission_query);
        $recent_stmt->bindParam(':student_id', $student_id);
        $recent_stmt->execute();
        $recent_submission = $recent_stmt->fetch(PDO::FETCH_ASSOC);

        if ($recent_submission) {
            $current_grades = $gradeProcessor->getStudentGrades($student_id, $recent_submission['academic_period_id']);
        }
    }

    // Honor eligibility calculation
    $honor_status = 'Not Eligible';
    $honor_color = 'gray';
    $honor_criteria = '';

    if ($student['gwa']) {
        $gwa = floatval($student['gwa']);

        // Check for approved Latin honors first
        $latin_honors_query = "
            SELECT COUNT(*) as has_latin_honors
            FROM honor_applications ha
            WHERE ha.user_id = :user_id
            AND ha.application_type IN ('cum_laude', 'magna_cum_laude', 'summa_cum_laude')
            AND ha.status = 'final_approved'
        ";
        $latin_stmt = $db->prepare($latin_honors_query);
        $latin_stmt->bindParam(':user_id', $student_id);
        $latin_stmt->execute();
        $latin_result = $latin_stmt->fetch(PDO::FETCH_ASSOC);

        if ($latin_result['has_latin_honors'] > 0) {
            $honor_status = 'Latin Honors';
            $honor_color = 'yellow';
            $honor_criteria = 'Approved Latin Honors application';
        } elseif ($gwa <= 1.45) {
            $honor_status = "Dean's List";
            $honor_color = 'green';
            $honor_criteria = 'GWA ≤ 1.45';
        } elseif ($gwa <= 1.75) {
            $honor_status = 'Honor Eligible';
            $honor_color = 'blue';
            $honor_criteria = 'GWA ≤ 1.75';
        } else {
            $honor_criteria = 'GWA > 1.75';
        }
    }

    // Generate HTML content
    ob_start();
    ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Basic Info -->
        <div class="lg:col-span-1">
            <div class="bg-gray-50 rounded-xl p-6">
                <div class="text-center mb-6">
                    <div class="w-20 h-20 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="user" class="w-10 h-10 text-primary-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h3>
                    <p class="text-gray-600"><?php echo htmlspecialchars($student['student_id']); ?></p>
                </div>

                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Email:</span>
                        <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['email']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Department:</span>
                        <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars(getDepartmentAbbreviation($student['department'])); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Section:</span>
                        <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars(formatSectionDisplay($student['section'])); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Year Level:</span>
                        <span class="text-sm font-medium text-gray-900">Year <?php echo $student['year_level']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Status:</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            <?php echo ucfirst($student['status']); ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Joined:</span>
                        <span class="text-sm font-medium text-gray-900"><?php echo date('M j, Y', strtotime($student['created_at'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Academic Info -->
        <div class="lg:col-span-2">
            <!-- GWA & Honor Status -->
            <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
                <h4 class="text-lg font-semibold text-gray-900 mb-4">Academic Performance</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold <?php echo $student['gwa'] ? 'text-primary-600' : 'text-gray-400'; ?>">
                            <?php echo $student['gwa'] ? formatGWA($student['gwa']) : 'N/A'; ?>
                        </div>
                        <div class="text-sm text-gray-500">Overall GWA</div>
                        <?php if ($student['calculated_at']): ?>
                            <div class="text-xs text-gray-400">Updated <?php echo date('M j', strtotime($student['calculated_at'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600"><?php echo $student['total_units'] ?? '0'; ?></div>
                        <div class="text-sm text-gray-500">Total Units</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600"><?php echo $student['subjects_count'] ?? '0'; ?></div>
                        <div class="text-sm text-gray-500">Subjects</div>
                    </div>
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
                </div>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-blue-50 rounded-xl p-4">
                    <h5 class="font-semibold text-blue-900 mb-2">Grade Submissions</h5>
                    <div class="space-y-1">
                        <div class="flex justify-between text-sm">
                            <span>Total:</span>
                            <span class="font-medium"><?php echo $student['total_submissions']; ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>Processed:</span>
                            <span class="font-medium text-green-600"><?php echo $student['processed_submissions']; ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>Pending:</span>
                            <span class="font-medium text-orange-600"><?php echo $student['pending_submissions']; ?></span>
                        </div>
                    </div>
                </div>

                <div class="bg-green-50 rounded-xl p-4">
                    <h5 class="font-semibold text-green-900 mb-2">Honor Applications</h5>
                    <div class="space-y-1">
                        <div class="flex justify-between text-sm">
                            <span>Total:</span>
                            <span class="font-medium"><?php echo $student['total_applications']; ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>Approved:</span>
                            <span class="font-medium text-green-600"><?php echo $student['approved_applications']; ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span>Pending:</span>
                            <span class="font-medium text-orange-600"><?php echo $student['pending_applications']; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="space-y-6">
                <!-- Recent Grade Submissions -->
                <?php if (!empty($submissions)): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Recent Grade Submissions</h4>
                    <div class="space-y-3">
                        <?php foreach ($submissions as $submission): ?>
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-b-0">
                            <div>
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($submission['period_name'] ?? 'Unknown Period'); ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    Uploaded: <?php echo date('M j, Y', strtotime($submission['upload_date'])); ?> •
                                    <?php echo $submission['grades_count']; ?> grades
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                <?php echo $submission['status'] === 'processed' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'; ?>">
                                <?php echo ucfirst($submission['status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Honor Applications -->
                <?php if (!empty($applications)): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Recent Honor Applications</h4>
                    <div class="space-y-3">
                        <?php foreach ($applications as $application): ?>
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-b-0">
                            <div>
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($application['period_name'] ?? 'Unknown Period'); ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    Applied: <?php echo date('M j, Y', strtotime($application['submitted_at'])); ?>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                <?php
                                    $status_colors = [
                                        'final_approved' => 'bg-green-100 text-green-800',
                                        'pending' => 'bg-orange-100 text-orange-800',
                                        'denied' => 'bg-red-100 text-red-800',
                                        'under_review' => 'bg-blue-100 text-blue-800'
                                    ];
                                    echo $status_colors[$application['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $application['status'])); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    $html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'html' => $html
    ]);

} catch (Exception $e) {
    error_log("Error fetching student details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching student details.'
    ]);
}
?>
