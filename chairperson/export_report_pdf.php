<?php
require_once '../config/config.php';
require_once '../vendor/autoload.php';

use Mpdf\Mpdf;

requireLogin();

if (!hasRole('chairperson')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$chairperson_id = $_SESSION['user_id'];
$department = $_SESSION['department'];

// Department to abbreviation mapping
$department_abbr_map = [
    // College of Agriculture
    'Bachelor of Science in Agriculture' => 'BSA',

    // College of Arts and Sciences
    'Bachelor of Arts in English Language' => 'BA English',
    'Bachelor of Arts in Literature' => 'BA Literature',

    // College of Education
    'Bachelor of Elementary Education' => 'BEEd',
    'Bachelor of Secondary Education' => 'BSEd',
    'Bachelor of Technology and Livelihood Education' => 'BTLEd',

    // College of Engineering
    'Bachelor of Science in Civil Engineering' => 'BSCE',
    'Bachelor of Science in Electrical Engineering' => 'BSEE',
    'Bachelor of Science in Industrial Engineering' => 'BSIE',
    'Bachelor of Science in Mechanical Engineering' => 'BSME',

    // College of Technology
    'Bachelor of Industrial Technology' => 'BIT',
    'Bachelor of Science in Information Technology' => 'BSIT',
    'Bachelor of Science in Hospitality Management' => 'BSHM',
];

// Automatically match abbreviation (default to full name if not found)
$department_abbr = $department_abbr_map[$department] ?? $department;

// Get filter parameters from query string
$year_filter = $_GET['year'] ?? 'all';
$section_filter = $_GET['section'] ?? 'all';
$ranking_type_filter = $_GET['ranking_type'] ?? 'deans_list';
$period_filter = $_GET['period'] ?? 'all';

// Build WHERE clause for filters
$filter_conditions = ["u.department = :department", "u.status = 'active'", "u.role = 'student'"];
$filter_params = [':department' => $department];

if ($year_filter !== 'all') {
    $filter_conditions[] = "u.year_level = :year_level";
    $filter_params[':year_level'] = $year_filter;
}

if ($section_filter !== 'all') {
    $filter_conditions[] = "u.section = :section";
    $filter_params[':section'] = $section_filter;
}

// Get academic period information and semester string for GWA calculation
$period_name = "Current Academic Year";
$semester_text = "1st Semester";
$school_year = "2024-2025";
$semester_string = null;

if ($period_filter !== 'all') {
    $period_query = "SELECT * FROM academic_periods WHERE id = :period_id";
    $period_stmt = $db->prepare($period_query);
    $period_stmt->bindParam(':period_id', $period_filter);
    $period_stmt->execute();
    $period_info = $period_stmt->fetch(PDO::FETCH_ASSOC);

    if ($period_info) {
        $semester_text = $period_info['semester'];
        $school_year = $period_info['school_year'];
        $semester_string = $semester_text . ' Semester SY ' . $school_year;
    }
} else {
    // Get active period for semester string
    $active_query = "SELECT * FROM academic_periods WHERE is_active = 1 LIMIT 1";
    $active_stmt = $db->prepare($active_query);
    $active_stmt->execute();
    $active_period = $active_stmt->fetch(PDO::FETCH_ASSOC);
    if ($active_period) {
        $semester_string = $active_period['semester'] . ' Semester SY ' . $active_period['school_year'];
    }
}

$student_filter = implode(' AND ', $filter_conditions);

// Calculate GWA directly from grades table like rankings.php does
if ($semester_string) {
    if ($ranking_type_filter === 'deans_list' || $ranking_type_filter === 'all') {
        // Query for Dean's Listers
        $query = "SELECT
                    u.first_name,
                    u.middle_name,
                    u.last_name,
                    u.section,
                    u.year_level,
                    SUM(g.units * g.grade) / SUM(g.units) as gpa
                  FROM users u
                  JOIN grade_submissions gs ON gs.user_id = u.id
                  JOIN grades g ON g.submission_id = gs.id
                  WHERE " . $student_filter . "
                    AND g.semester_taken = :semester_string
                    AND g.grade > 0.00
                    AND NOT (UPPER(g.subject_name) LIKE '%NSTP%' OR UPPER(g.subject_name) LIKE '%NATIONAL SERVICE TRAINING%')
                  GROUP BY u.id, u.first_name, u.middle_name, u.last_name, u.section, u.year_level
                  HAVING gpa IS NOT NULL AND gpa <= 1.75
                  ORDER BY gpa ASC, u.last_name ASC";
    } else {
        // Latin Honors query
        $query = "SELECT
                    u.first_name,
                    u.middle_name,
                    u.last_name,
                    u.section,
                    u.year_level,
                    SUM(g.units * g.grade) / SUM(g.units) as gpa
                  FROM users u
                  JOIN grade_submissions gs ON gs.user_id = u.id
                  JOIN grades g ON g.submission_id = gs.id
                  LEFT JOIN honor_applications ha ON u.id = ha.user_id
                    AND ha.application_type IN ('cum_laude', 'magna_cum_laude', 'summa_cum_laude')
                    AND ha.status = 'final_approved'
                  WHERE " . $student_filter . "
                    AND g.semester_taken = :semester_string
                    AND g.grade > 0.00
                    AND NOT (UPPER(g.subject_name) LIKE '%NSTP%' OR UPPER(g.subject_name) LIKE '%NATIONAL SERVICE TRAINING%')
                    AND ha.id IS NOT NULL
                  GROUP BY u.id, u.first_name, u.middle_name, u.last_name, u.section, u.year_level
                  HAVING gpa IS NOT NULL AND gpa <= 1.75
                  ORDER BY gpa ASC, u.last_name ASC";
    }

    $stmt = $db->prepare($query);
    $stmt->bindValue(':semester_string', $semester_string);
    foreach ($filter_params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Truncate GWA to 2 decimal places like rankings.php does
    foreach ($students as &$student) {
        $student['gpa'] = floor($student['gpa'] * 100) / 100;
    }
    unset($student);
} else {
    $students = [];
}

// Get chairperson information
$chair_query = "SELECT first_name, middle_name, last_name FROM users WHERE id = :chair_id";
$chair_stmt = $db->prepare($chair_query);
$chair_stmt->bindParam(':chair_id', $chairperson_id);
$chair_stmt->execute();
$chairperson_info = $chair_stmt->fetch(PDO::FETCH_ASSOC);

$chairperson_name = strtoupper($chairperson_info['first_name'] . ' ' .
                               ($chairperson_info['middle_name'] ? substr($chairperson_info['middle_name'], 0, 1) . '. ' : '') .
                               $chairperson_info['last_name']);

// Program text
$program_text = strtoupper($department) . ' (' . strtoupper($department_abbr) . ')';

// Title text
$title_text = $ranking_type_filter === 'latin_honors' ? "LATIN HONORS" : "DEAN'S LISTERS";

// Year level text
$year_level_text = "";
if ($year_filter !== 'all') {
    $year_level_map = [
        '1' => 'FIRST YEAR LEVEL',
        '2' => 'SECOND YEAR LEVEL',
        '3' => 'THIRD YEAR LEVEL',
        '4' => 'FOURTH YEAR LEVEL'
    ];
    $year_level_text = $year_level_map[$year_filter] ?? 'ALL YEAR LEVELS';
} else {
    $year_level_text = 'ALL YEAR LEVELS';
}

// Generate filename
$filename = strtolower(str_replace(' ', '_', $title_text)) . '_' .
            strtolower(str_replace(' ', '_', $semester_text)) . '_' .
            str_replace('-', '', $school_year) . '.pdf';

// Build HTML content
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .header-table .logo-left {
            width: 95px;
            text-align: left;
        }
        .header-table .logo-right {
            width: 95px;
            text-align: right;
        }
        .header-table .header-text {
            text-align: center;
            line-height: 1.2;
        }
        .header-text p {
            margin: 0;
            padding: 0;
        }
        .title-section {
            text-align: center;
            margin-bottom: 0;
        }
        .year-level {
            text-align: left;
            font-weight: bold;
            font-size: 11pt;
            margin-top: 14pt;
            margin-bottom: 14pt;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .data-table th {
            border-top: none;
            border-left: none;
            border-right: none;
            border-bottom: 1px solid #000;
            padding: 8px;
            font-weight: bold;
            font-size: 10pt;
            text-align: center;
        }
        .data-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            font-size: 11pt;
        }
        .data-table .rank-col {
            width: 13%;
            text-align: center;
        }
        .data-table .name-col {
            width: 37%;
            text-align: left;
        }
        .data-table .section-col {
            width: 25%;
            text-align: center;
        }
        .data-table .gpa-col {
            width: 20%;
            text-align: center;
        }
        .footer-section {
            margin-top: 12pt;
        }
        .footer-section p {
            margin: 0;
            font-size: 11pt;
            line-height: 1.3;
        }
        .footer-logos {
            margin-top: 30pt;
            text-align: center;
            padding-top: 20pt;
            border-top: 1px solid #ccc;
        }
        .footer-logos img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <!-- HEADER WITH LOGOS -->
    <table class="header-table">
        <tr>
            <td class="logo-left">
                <img src="' . realpath(__DIR__ . '/../img/cebu-technological-university-seeklogo.png') . '" width="95" height="95" />
            </td>
            <td class="header-text">
                <p style="font-size: 12pt;">Republic of the Philippines</p>
                <p style="font-size: 12pt; font-weight: bold;">CEBU TECHNOLOGICAL UNIVERSITY</p>
                <p style="font-size: 11pt; font-weight: bold;">TUBURAN CAMPUS</p>
                <p style="font-size: 10pt;">Brgy 8, Poblacion Tuburan, Cebu, Philippines</p>
                <p style="font-size: 8pt;">Website: <span style="text-decoration: underline; color: blue;">http://www.ctu.edu.ph</span> E-mail: <span style="text-decoration: underline; color: blue;">tuburan.campus@ctu.edu.ph</span></p>
                <p style="font-size: 9pt;">Phone: +6332 463 9313 loc. 1523</p>
            </td>
            <td class="logo-right">
                <img src="' . realpath(__DIR__ . '/../img/Bagong-Pilipinas-Logo-1536x1444.png') . '" width="95" height="90" />
            </td>
        </tr>
    </table>

    <!-- PROGRAM NAME -->
    <div class="title-section">
        <p style="font-size: 10pt; margin: 0; padding: 0;">' . htmlspecialchars($program_text) . '</p>
    </div>

    <!-- TITLE -->
    <div class="title-section">
        <p style="font-size: 11pt; font-weight: bold; margin: 0; padding: 0;">' . htmlspecialchars($title_text) . '</p>
    </div>

    <!-- SEMESTER INFO -->
    <div class="title-section">
        <p style="font-size: 11pt; font-weight: bold; margin: 0 0 14pt 0; padding: 0;">' . htmlspecialchars($semester_text) . ' Semester, A.Y. ' . htmlspecialchars($school_year) . '</p>
    </div>

    <!-- YEAR LEVEL -->
    <div class="year-level">
        ' . htmlspecialchars($year_level_text) . '
    </div>

    <!-- DATA TABLE -->
    <table class="data-table">
        <thead>
            <tr>
                <th class="rank-col">RANK</th>
                <th class="name-col">COMPLETE NAME</th>
                <th class="section-col">BLOCK SECTION</th>
                <th class="gpa-col">GPA</th>
            </tr>
        </thead>
        <tbody>';

// Add student data
$rank = 1;
$previous_gpa = null;
$same_rank_count = 0;

foreach ($students as $index => $student) {
    // Format name
    $middle_initial = $student['middle_name'] ? strtoupper(substr($student['middle_name'], 0, 1)) . '.' : '';
    $full_name = ' ' . $student['last_name'] . ', ' . $student['first_name'] . ' ' . $middle_initial;

    // Format section
    $year = $student['year_level'];
    $student_section = strtoupper(trim($student['section']));
    $section_display = "{$department_abbr}-{$year}{$student_section}";

    // Format GPA
    $gpa = number_format($student['gpa'], 3);

    // Handle ranking (same GPA gets same rank)
    if ($previous_gpa !== null && $gpa == $previous_gpa) {
        $same_rank_count++;
        $display_rank = '';
    } else {
        $rank = $index + 1 - $same_rank_count;
        $display_rank = $rank . '.';
        $same_rank_count = 0;
    }
    $previous_gpa = $gpa;

    $html .= '
            <tr>
                <td class="rank-col">' . htmlspecialchars($display_rank) . '</td>
                <td class="name-col">' . htmlspecialchars($full_name) . '</td>
                <td class="section-col">' . htmlspecialchars($section_display) . '</td>
                <td class="gpa-col">' . htmlspecialchars($gpa) . '</td>
            </tr>';
}

$html .= '
        </tbody>
    </table>

    <!-- FOOTER -->
    <div class="footer-section">
        <p style="margin: 0; padding: 0;">Prepared by:</p>
        <br/>
        <p style="font-weight: bold; margin: 0; padding: 0;">' . htmlspecialchars($chairperson_name) . '</p>
        <p style="margin: 0; padding: 0;">Chairperson, ' . htmlspecialchars($department_abbr) . '</p>
        <br/><br/><br/>
        <p style="margin: 0; padding: 0;">_________________________________</p>
        <p style="margin: 0; padding: 0;">Dean of Technology</p>
    </div>
</body>
</html>';

// Initialize mPDF
$mpdf = new Mpdf([
    'format' => 'Legal',
    'margin_left' => 25.4,    // 1 inch = 25.4mm
    'margin_right' => 25.4,
    'margin_top' => 12.7,     // ~0.5 inch
    'margin_bottom' => 35,    // Increased to accommodate footer logos
    'tempDir' => __DIR__ . '/../tmp'
]);

// Set footer HTML with logos
$footer_html = '<div style="text-align: center; padding-top: 10px; border-top: 1px solid #ccc;">
    <img src="' . realpath(__DIR__ . '/../img/footer-logos.png') . '" style="max-width: 100%; height: auto;" />
</div>';
$mpdf->SetHTMLFooter($footer_html);

// Write HTML content
$mpdf->WriteHTML($html);

// Output to browser
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
$mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);

exit();
?>
