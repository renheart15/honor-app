<?php
require_once 'classes/GradeExtractor.php';

$extractor = new GradeExtractor();
$result = $extractor->extractGradesFromPDF('uploads/grades/grade_49_1764680269.pdf');

echo "Debugging CS74 and CS75 extraction:\n";
echo str_repeat('=', 100) . "\n\n";

// Check if they appear in debug info
if (isset($result['debug']['combined_lines_sample'])) {
    echo "Checking combined lines for CS74/CS75:\n";
    foreach ($result['debug']['combined_lines_sample'] as $line) {
        if (strpos($line['combined_line'], 'CS74') === 0 || strpos($line['combined_line'], 'CS75') === 0) {
            echo "Found: Line {$line['line_number']}\n";
            echo "  Combined: {$line['combined_line']}\n";
            echo "  Has grade: {$line['has_grade']}\n\n";
        }
    }
}

// Check raw text for CS74 and CS75
require_once 'vendor/autoload.php';
use Smalot\PdfParser\Parser;

$parser = new Parser();
$pdf = $parser->parseFile('uploads/grades/grade_49_1764680269.pdf');
$text = $pdf->getText();
$lines = explode("\n", $text);

echo "\nRaw lines containing CS74 or CS75:\n";
foreach ($lines as $idx => $line) {
    if (strpos(trim($line), 'CS74') === 0 || strpos(trim($line), 'CS75') === 0) {
        echo "Line $idx: [" . trim($line) . "]\n";
        if (isset($lines[$idx + 1])) echo "  +1: [" . trim($lines[$idx + 1]) . "]\n";
        if (isset($lines[$idx + 2])) echo "  +2: [" . trim($lines[$idx + 2]) . "]\n";
        echo "\n";
    }
}

// Count tabs in CS75 line
foreach ($lines as $idx => $line) {
    if (strpos(trim($line), 'CS75') === 0) {
        $parts = explode("\t", trim($line));
        echo "CS75 tab-separated parts: " . count($parts) . "\n";
        foreach ($parts as $i => $part) {
            echo "  Part $i: [$part]\n";
        }
    }
}
