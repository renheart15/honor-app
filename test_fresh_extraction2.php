<?php
require_once 'classes/GradeExtractor.php';

$pdfPath = 'uploads/grades/grade_49_1764667013.pdf';
$extractor = new GradeExtractor();
$result = $extractor->extractGradesFromPDF($pdfPath);

echo "Extraction Result:\n";
echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
echo "Message: " . $result['message'] . "\n";
echo "Grades count: " . count($result['grades']) . "\n\n";

if (!empty($result['debug'])) {
    echo "Debug info keys: " . implode(', ', array_keys($result['debug'])) . "\n\n";

    if (isset($result['debug']['non_empty_lines'])) {
        echo "Non-empty lines count: " . $result['debug']['non_empty_lines'] . "\n\n";
    }

    if (isset($result['debug']['total_grades_found'])) {
        echo "Total grades found: " . $result['debug']['total_grades_found'] . "\n\n";
    }
}

// Check for PHP errors
if (isset($result['debug']['error'])) {
    echo "ERROR: " . $result['debug']['error'] . "\n";
}
