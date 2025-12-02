<?php
require_once 'vendor/autoload.php';
use Smalot\PdfParser\Parser;

// Use the latest PDF
$pdfPath = 'uploads/grades/grade_49_1764680269.pdf';
echo "Testing PDF: $pdfPath\n\n";

$parser = new Parser();
$pdf = $parser->parseFile($pdfPath);
$text = $pdf->getText();
$lines = explode("\n", $text);

echo "RAW PDF ANALYSIS - COMPLETE LINE BY LINE\n";
echo str_repeat("=", 100) . "\n\n";

// Find all CS lines
$csLines = [];
foreach ($lines as $idx => $line) {
    if (preg_match('/^CS\d+/', trim($line))) {
        $csLines[] = [
            'index' => $idx,
            'line' => trim($line),
            'length' => strlen(trim($line))
        ];
    }
}

echo "Found " . count($csLines) . " lines starting with CS code\n\n";

// Check specifically for 2nd Semester SY 2024-2025
echo "2nd Semester SY 2024-2025 subjects:\n";
echo str_repeat("-", 100) . "\n";

$in2024_2025_2nd = false;
foreach ($lines as $idx => $line) {
    $trimmed = trim($line);

    if (strpos($trimmed, '2nd Semester SY 2024-2025') !== false) {
        $in2024_2025_2nd = true;
        echo "Found semester marker at line $idx\n\n";
        continue;
    }

    if ($in2024_2025_2nd && preg_match('/^CS\d+/', $trimmed)) {
        echo "Line $idx: $trimmed\n";

        // Show next 2 lines as well
        if (isset($lines[$idx + 1])) {
            echo "  +1: " . trim($lines[$idx + 1]) . "\n";
        }
        if (isset($lines[$idx + 2])) {
            echo "  +2: " . trim($lines[$idx + 2]) . "\n";
        }
        echo "\n";
    }

    // Stop if we hit the next section or end
    if ($in2024_2025_2nd && (preg_match('/^Total Units/', $trimmed) || preg_match('/Note:/', $trimmed))) {
        break;
    }
}

// Now test extraction
echo "\n\n" . str_repeat("=", 100) . "\n";
echo "EXTRACTION TEST:\n";
echo str_repeat("=", 100) . "\n\n";

require_once 'classes/GradeExtractor.php';
$extractor = new GradeExtractor();
$result = $extractor->extractGradesFromPDF($pdfPath);

echo "Total extracted: " . count($result['grades']) . "\n";
echo "Expected: ~51 subjects\n";
echo "Missing: " . (51 - count($result['grades'])) . " subjects\n\n";

// Check which CS codes are in 2nd semester 2024-2025
echo "Extracted subjects in 2nd Semester SY 2024-2025:\n";
foreach ($result['grades'] as $g) {
    if ($g['semester'] === '2nd Semester SY 2024-2025') {
        echo "  {$g['subject_code']} - {$g['subject_name']}: Grade {$g['grade']}\n";
    }
}
