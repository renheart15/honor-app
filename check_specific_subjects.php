<?php
require_once 'classes/GradeExtractor.php';
$extractor = new GradeExtractor();
$result = $extractor->extractGradesFromPDF('uploads/grades/grade_49_1764667013.pdf');

echo "Looking for problematic subjects:\n";
echo "================================================================================\n\n";

$problematicCodes = ['CS27', 'CS28', 'CS48', 'CS73', 'CS53', 'CS54', 'CS58', 'CS59'];

foreach ($result['grades'] as $grade) {
    if (in_array($grade['subject_code'], $problematicCodes) ||
        ($grade['subject_code'] == 'CS22' && $grade['semester'] == '2nd Semester SY 2022-2023')) {
        echo "CODE: {$grade['subject_code']}\n";
        echo "  Name: {$grade['subject_name']}\n";
        echo "  Grade: {$grade['grade']}\n";
        echo "  Units: {$grade['units']}\n";
        echo "  Semester: {$grade['semester']}\n";
        if (isset($grade['source_line'])) {
            echo "  Source: {$grade['source_line']}\n";
            // Show hex of source
            echo "  Source hex: " . bin2hex(substr($grade['source_line'], 0, 100)) . "\n";
        }
        echo "\n";
    }
}

echo "\n\nTotal grades extracted: " . count($result['grades']) . "\n";
echo "Grades with 0.0: " . count(array_filter($result['grades'], function($g) { return $g['grade'] == 0.0; })) . "\n";
echo "Grades > 0.0: " . count(array_filter($result['grades'], function($g) { return $grade['grade'] > 0.0; })) . "\n";
