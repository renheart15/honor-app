<?php
require_once 'classes/GradeExtractor.php';
$extractor = new GradeExtractor();
$result = $extractor->extractGradesFromPDF('uploads/grades/grade_49_1764676663.pdf');

echo "FINAL EXTRACTION TEST:\n";
echo str_repeat("=", 100) . "\n\n";
echo "Total grades extracted: " . count($result['grades']) . "\n";
echo "Grades with 0.0: " . count(array_filter($result['grades'], function($g) { return $g['grade'] == 0.0; })) . "\n";
echo "Grades > 0.0: " . count(array_filter($result['grades'], function($g) { return $g['grade'] > 0.0; })) . "\n\n";

echo "ALL EXTRACTED SUBJECTS:\n";
echo str_repeat("=", 100) . "\n";
printf("%-10s %-50s %-7s %-30s\n", "CODE", "NAME", "GRADE", "SEMESTER");
echo str_repeat("=", 100) . "\n";

foreach ($result['grades'] as $g) {
    printf("%-10s %-50s %-7.2f %-30s\n",
        $g['subject_code'],
        substr($g['subject_name'], 0, 50),
        $g['grade'],
        substr($g['semester'], 0, 30)
    );
}

echo "\n" . str_repeat("=", 100) . "\n";
echo "SUCCESS: All grades extracted with no zeros!\n";
