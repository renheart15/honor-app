<?php
require_once 'classes/GradeExtractor.php';

$pdfPath = 'uploads/grades/grade_49_1764667013.pdf';
$extractor = new GradeExtractor();
$result = $extractor->extractGradesFromPDF($pdfPath);

echo "FRESH EXTRACTION RESULTS (not from database):\n";
echo str_repeat("=", 100) . "\n";
printf("%-10s %-50s %-7s %-7s %s\n", "CODE", "NAME", "UNITS", "GRADE", "SEMESTER");
echo str_repeat("=", 100) . "\n";

foreach ($result['grades'] as $g) {
    $highlight = ($g['grade'] == 0.0) ? " *** ZERO ***" : "";
    printf("%-10s %-50s %-7.2f %-7.2f %s%s\n",
        $g['subject_code'],
        substr($g['subject_name'], 0, 50),
        $g['units'],
        $g['grade'],
        substr($g['semester'], 0, 30),
        $highlight
    );
}

echo "\n\nSUMMARY:\n";
echo "Total subjects: " . count($result['grades']) . "\n";
echo "Subjects with grade 0: " . count(array_filter($result['grades'], function($g) { return $g['grade'] == 0.0; })) . "\n";
echo "Subjects with grade > 0: " . count(array_filter($result['grades'], function($g) { return $g['grade'] > 0.0; })) . "\n";
