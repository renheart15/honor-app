<?php
require_once '../config/config.php';

requireLogin();

if (!hasRole('student')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();
$gradeProcessor = new GradeProcessor($db);

$user_id = $_SESSION['user_id'];
$current_period = 1; // Get current academic period

// Get student's grades and GWA
$grades = $gradeProcessor->getStudentGrades($user_id, $current_period);
$gwa_data = $gradeProcessor->getStudentGWA($user_id, $current_period);

// Get student information
$query = "SELECT u.*, p.program_name, d.department_name 
          FROM users u 
          LEFT JOIN programs p ON u.program_id = p.id 
          LEFT JOIN departments d ON p.department_id = d.id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get academic period info
$query = "SELECT * FROM academic_periods WHERE id = :period_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':period_id', $current_period);
$stmt->execute();
$period = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gwa_data || empty($grades)) {
    $_SESSION['error'] = 'No GWA data available for download.';
    redirect('grades.php');
}

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="GWA_Report_' . $student['student_id'] . '_' . date('Y-m-d') . '.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Simple PDF generation (you can replace this with a proper PDF library like TCPDF or FPDF)
// For now, we'll create an HTML version that can be printed as PDF
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GWA Report - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: white;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0369a1;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #0369a1;
            margin-bottom: 10px;
        }
        .university-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .report-title {
            font-size: 16px;
            color: #666;
            margin-top: 10px;
        }
        .student-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
        }
        .info-group {
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            color: #374151;
            margin-bottom: 2px;
        }
        .info-value {
            color: #6b7280;
        }
        .gwa-summary {
            text-align: center;
            padding: 30px;
            background: linear-gradient(135deg, #0ea5e9, #0369a1);
            color: white;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .gwa-value {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .gwa-label {
            font-size: 18px;
            opacity: 0.9;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 20px 0;
        }
        .stat-item {
            text-align: center;
            padding: 15px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.8;
        }
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .grades-table th {
            background: #f1f5f9;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }
        .grades-table td {
            padding: 12px;
            border-bottom: 1px solid #f3f4f6;
        }
        .grades-table tr:hover {
            background: #f8fafc;
        }
        .grade-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .grade-excellent {
            background: #dcfce7;
            color: #166534;
        }
        .grade-good {
            background: #fef3c7;
            color: #92400e;
        }
        .grade-fair {
            background: #fee2e2;
            color: #991b1b;
        }
        .grade-failed {
            background: #f3f4f6;
            color: #374151;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 12px;
        }
        .signature-section {
            margin-top: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }
        .signature-box {
            text-align: center;
            padding: 20px;
        }
        .signature-line {
            border-top: 1px solid #374151;
            margin-top: 40px;
            padding-top: 10px;
            font-weight: bold;
        }
        @media print {
            body {
                margin: 0;
                padding: 15px;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo">🎓 CTU Honor System</div>
        <div class="university-name">Cebu Technological University</div>
        <div class="report-title">General Weighted Average (GWA) Report</div>
    </div>

    <!-- Student Information -->
    <div class="student-info">
        <div>
            <div class="info-group">
                <div class="info-label">Student Name:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']); ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">Student ID:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['student_id']); ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">Email:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['email']); ?></div>
            </div>
        </div>
        <div>
            <div class="info-group">
                <div class="info-label">Program:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['program_name'] ?: 'Not specified'); ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">Department:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['department_name'] ?: 'Not specified'); ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">Academic Period:</div>
                <div class="info-value"><?php echo htmlspecialchars($period['period_name'] ?? 'Current Period'); ?></div>
            </div>
        </div>
    </div>

    <!-- GWA Summary -->
    <div class="gwa-summary">
        <div class="gwa-value"><?php echo formatGWA($gwa_data['gwa']); ?></div>
        <div class="gwa-label">General Weighted Average</div>
        
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-value"><?php echo $gwa_data['total_units']; ?></div>
                <div class="stat-label">Total Units</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo count($grades); ?></div>
                <div class="stat-label">Subjects</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo count(array_filter($grades, function($g) { return $g['grade'] > 3.0; })); ?></div>
                <div class="stat-label">Failed</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo number_format($gwa_data['total_grade_points'], 2); ?></div>
                <div class="stat-label">Grade Points</div>
            </div>
        </div>
    </div>

    <!-- Grades Table -->
    <table class="grades-table">
        <thead>
            <tr>
                <th>Subject Code</th>
                <th>Subject Name</th>
                <th>Units</th>
                <th>Grade</th>
                <th>Letter Grade</th>
                <th>Remarks</th>
                <th>Grade Points</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grades as $grade): ?>
                <tr>
                    <td><?php echo htmlspecialchars($grade['subject_code']); ?></td>
                    <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                    <td><?php echo $grade['units']; ?></td>
                    <td>
                        <span class="grade-badge <?php 
                            if ($grade['grade'] <= 1.5) echo 'grade-excellent';
                            elseif ($grade['grade'] <= 2.0) echo 'grade-good';
                            elseif ($grade['grade'] <= 3.0) echo 'grade-fair';
                            else echo 'grade-failed';
                        ?>">
                            <?php echo $grade['grade']; ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($grade['letter_grade'] ?: 'N/A'); ?></td>
                    <td>
                        <span class="grade-badge <?php echo $grade['grade'] <= 3.0 ? 'grade-excellent' : 'grade-failed'; ?>">
                            <?php echo $grade['grade'] <= 3.0 ? 'PASSED' : 'FAILED'; ?>
                        </span>
                    </td>
                    <td><?php echo number_format($grade['grade'] * $grade['units'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot style="background: #f1f5f9; font-weight: bold;">
            <tr>
                <td colspan="2">TOTAL</td>
                <td><?php echo $gwa_data['total_units']; ?></td>
                <td colspan="3">GWA: <?php echo formatGWA($gwa_data['gwa']); ?></td>
                <td><?php echo number_format($gwa_data['total_grade_points'], 2); ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">Student Signature</div>
            <div style="margin-top: 10px; color: #6b7280; font-size: 12px;">
                Date: <?php echo date('F d, Y'); ?>
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">Registrar Signature</div>
            <div style="margin-top: 10px; color: #6b7280; font-size: 12px;">
                Official Seal
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>This is an official document generated by the CTU Honor Application System.</p>
        <p>Generated on: <?php echo date('F d, Y g:i A'); ?></p>
        <p>Document ID: GWA-<?php echo $student['student_id']; ?>-<?php echo date('Ymd-His'); ?></p>
    </div>

    <script>
        // Auto-print when page loads (for PDF generation)
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
