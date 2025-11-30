<?php
require_once '../config/config.php';
require_once '../vendor/autoload.php'; // For PHPWord

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Table;

requireLogin();

if (!hasRole('student')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$student_id = $_SESSION['user_id'];
$department = $_SESSION['department'];

// Get the chairperson for this department to show in footer
$chair_query_dept = "SELECT id FROM users WHERE role = 'chairperson' AND department = :department AND status = 'active' LIMIT 1";
$chair_stmt_dept = $db->prepare($chair_query_dept);
$chair_stmt_dept->bindParam(':department', $department);
$chair_stmt_dept->execute();
$chair_dept = $chair_stmt_dept->fetch(PDO::FETCH_ASSOC);
$chairperson_id = $chair_dept['id'] ?? null;

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
$ranking_type_filter = $_GET['ranking_type'] ?? 'deans_list'; // Default to Dean's List
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

// Create new PHPWord document
$phpWord = new PhpWord();

// Document settings
$phpWord->getSettings()->setZoom(100);
$section = $phpWord->addSection([
    'pageSizeW' => 12240,  // 8.5 inches
    'pageSizeH' => 18720,  // 13 inches
    'marginLeft' => 1440,   // 1 inch
    'marginRight' => 1440,  // 1 inch
    'marginTop' => 500,    // 1 inch
    'marginBottom' => 1440  // 1 inch
]);

// Add footer with logos to the section
$footer = $section->addFooter();

$footerLogoPath = realpath(__DIR__ . '/../img/footer-logos.png');
if ($footerLogoPath && file_exists($footerLogoPath)) {
    // Get image dimensions
    list($width, $height) = getimagesize($footerLogoPath);
    // Calculate proportional width in points (keeping aspect ratio)
    // Targeting around 500 points width
    $targetWidth = 500;
    $proportionalHeight = ($height / $width) * $targetWidth;

    // Use a centered paragraph to display the image
    $footerParagraph = $footer->addTextRun(['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    $footerParagraph->addImage(
        $footerLogoPath,
        [
            'width' => $targetWidth,
            'height' => $proportionalHeight
        ]
    );
}

// --- HEADER WITH LOGOS AND TEXT (CENTERED BETWEEN LOGOS) ---

// Create a table with 3 columns: left (CTU logo), middle (text), right (Bagong Pilipinas logo)
$headerTable = $section->addTable();
$headerTable->addRow();

// Left cell: CTU logo
$headerTable->addCell(3500, ['valign' => 'center'])->addImage(
    '../img/cebu-technological-university-seeklogo.png', // Update this path
    [
        'width' => 95,
        'height' => 95,
        'alignment' => Jc::LEFT
    ]
);

// Middle cell: Header text (centered)
$middleCell = $headerTable->addCell(9000, ['valign' => 'center']);

// Compact paragraph style (no after-space)
$noSpace = ['alignment' => Jc::CENTER, 'spaceAfter' => 0];

$middleCell->addText('Republic of the Philippines', ['size' => 12, 'name' => 'Arial'], $noSpace);
$middleCell->addText('CEBU TECHNOLOGICAL UNIVERSITY', ['bold' => true, 'size' => 12, 'name' => 'Arial'], $noSpace);
$middleCell->addText('TUBURAN CAMPUS', ['bold' => true, 'size' => 11, 'name' => 'Arial'], $noSpace);
$middleCell->addText('Brgy 8, Poblacion Tuburan, Cebu, Philippines', ['size' => 10, 'name' => 'Arial'], $noSpace);

// Website and Email — underline individually
$websiteRun = $middleCell->addTextRun(['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
$websiteRun->addText('Website: ', ['size' => 8, 'name' => 'Arial']);
$websiteRun->addText('http://www.ctu.edu.ph', [
    'size' => 8,
    'name' => 'Arial',
    'underline' => 'single',
    'color' => '0000FF'
]);
$websiteRun->addText(' E-mail: ', ['size' => 8, 'name' => 'Arial']);
$websiteRun->addText('tuburan.campus@ctu.edu.ph', [
    'size' => 8,
    'name' => 'Arial',
    'underline' => 'single',
    'color' => '0000FF'
]);

// Phone line
$middleCell->addText(
    'Phone: +6332 463 9313 loc. 1512',
    ['size' => 9, 'name' => 'Arial'],
    $noSpace
);

// Right cell: Bagong Pilipinas logo
$headerTable->addCell(3500, ['valign' => 'center'])->addImage(
    '../img/Bagong-Pilipinas-Logo-1536x1444.png', // Update this path
    
    [
        'width' => 95,
        'height' => 90,
        'alignment' => Jc::RIGHT
    ]
);

// Add space after header
$section->addTextBreak(0);


$program_text = strtoupper($department) . ' (' . strtoupper($department_abbr) . ')';

$section->addText(
    $program_text,
    ['size' => 10, 'name' => 'Arial'],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
);

// DEAN'S LISTERS
$title_text = $ranking_type_filter === 'latin_honors' ? "LATIN HONORS" : "DEAN'S LISTERS";
$section->addText(
    $title_text,
    ['bold' => true, 'size' => 11, 'name' => 'Arial'],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
);

// Semester info
$section->addText(
    $semester_text . ' Semester, A.Y. ' . $school_year,
    ['bold' => true, 'size' => 11, 'name' => 'Arial'],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
);

// Year level
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
$section->addText(
    $year_level_text,
    ['bold' => true, 'size' => 11, 'name' => 'Arial'],
    ['alignment' => Jc::START, 'spaceAfter' => 200]
);

// --- Table style that respects 1-inch left/right margins ---
// page width in twips (you set pageSizeW => 12240 earlier)
$pageWidth = 12240;
// margins (twips)
$marginLeft = 1440;
$marginRight = 1440;
// usable table width
$tableWidth = $pageWidth - ($marginLeft + $marginRight); // 12240 - 2880 = 9360

// Base column proportions (original widths you used: 1000,4000,2500,1500)
$baseCols = [1250, 3500, 2350, 1900];
$baseTotal = array_sum($baseCols);

// Compute scaled column widths so they sum to $tableWidth
$colWidths = [];
foreach ($baseCols as $w) {
    $colWidths[] = (int) round($w / $baseTotal * $tableWidth);
}
// Adjust last column to fix any rounding difference
$sumCols = array_sum($colWidths);
if ($sumCols !== $tableWidth) {
    $diff = $tableWidth - $sumCols;
    $colWidths[count($colWidths)-1] += $diff;
}

// Table style (use twips width)
$tableStyle = [
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 20,
    'alignment' => Jc::CENTER,
    'width' => $tableWidth, // explicit width in twips
    // don't rely on WIDTH_PERCENT constant — specifying twips is safest
];

$phpWord->addTableStyle('deansList', $tableStyle);

// Center the table manually
$table = $section->addTable('deansList', ['alignment' => Jc::CENTER]);

$phpWord->addTableStyle('deansList', $tableStyle);

// Add table
$table = $section->addTable('deansList');

// Header row
$table->addRow(300);
$headerStyle = ['bold' => true, 'size' => 10, 'name' => 'Arial'];
$headerCenterStyle = ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'spaceBefore' => 0];
$noBorderStyle = [
    'valign' => 'center',
    'borderTopSize' => 0,
    'borderTopColor' => 'FFFFFF',
    'borderBottomSize' => 6,
    'borderBottomColor' => '000000',
    'borderLeftSize' => 0,
    'borderLeftColor' => 'FFFFFF',
    'borderRightSize' => 0,
    'borderRightColor' => 'FFFFFF'
];

$table->addCell($colWidths[0], $noBorderStyle)->addText('RANK', $headerStyle, $headerCenterStyle);
$table->addCell($colWidths[1], $noBorderStyle)->addText('COMPLETE NAME', $headerStyle, $headerCenterStyle);
$table->addCell($colWidths[2], $noBorderStyle)->addText('BLOCK SECTION', $headerStyle, $headerCenterStyle);
$table->addCell($colWidths[3], $noBorderStyle)->addText('GPA', $headerStyle, $headerCenterStyle);

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
        // Same GPA as previous, don't increment rank
        $same_rank_count++;
        $display_rank = ''; // Don't show rank number for tied students after the first
    } else {
        // Different GPA, update rank
        $rank = $index + 1 - $same_rank_count;
        $display_rank = $rank . '.';
        $same_rank_count = 0;
    }
    $previous_gpa = $gpa;

    // Add row
    $table->addRow(250);
    $cellStyle = ['size' => 11, 'name' => 'Arial'];
    $cellLeftStyle = ['alignment' => Jc::LEFT, 'spaceAfter' => 0, 'spaceBefore' => 0];
    $cellCenterStyle = ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'spaceBefore' => 0];

    $table->addCell($colWidths[0], ['valign' => 'center'])->addText($display_rank, $cellStyle, $cellCenterStyle);
    $table->addCell($colWidths[1], ['valign' => 'center'])->addText($full_name, $cellStyle, $cellLeftStyle);
    $table->addCell($colWidths[2], ['valign' => 'center'])->addText($section_display, $cellStyle, $cellCenterStyle);
    $table->addCell($colWidths[3], ['valign' => 'center'])->addText($gpa, $cellStyle, $cellCenterStyle);
}

// Add footer
$section->addTextBreak(1);
$section->addText(
    'Prepared by:',
    ['size' => 11, 'name' => 'Arial'],
    ['alignment' => Jc::START, 'spaceAfter' => 0]
);
$section->addTextBreak(1);
$section->addText(
    $chairperson_name,
    ['bold' => true, 'size' => 11, 'name' => 'Arial'],
    ['alignment' => Jc::START, 'spaceAfter' => 0]
);
$section->addText(
    'Chairperson, ' . $department_abbr,
    ['size' => 11, 'name' => 'Arial'],
    ['alignment' => Jc::START, 'spaceAfter' => 0]
);


// Generate filename
$filename = strtolower(str_replace(' ', '_', $title_text)) . '_' .
            strtolower(str_replace(' ', '_', $semester_text)) . '_' .
            str_replace('-', '', $school_year) . '.docx';

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Save to output
$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save('php://output');
exit();
?>
