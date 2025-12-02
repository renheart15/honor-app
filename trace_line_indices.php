<?php
require_once 'vendor/autoload.php';
use Smalot\PdfParser\Parser;

$pdfPath = 'uploads/grades/grade_49_1764680269.pdf';
$parser = new Parser();
$pdf = $parser->parseFile($pdfPath);
$text = $pdf->getText();
$lines = explode("\n", $text);

// Filter to non-empty lines (same as GradeExtractor does)
$nonEmptyLines = [];
foreach ($lines as $line) {
    if (!empty(trim($line))) {
        $nonEmptyLines[] = trim($line);
    }
}

// Re-index (same as GradeExtractor does)
$nonEmptyLines = array_values($nonEmptyLines);

echo "LINE INDICES AROUND CS74 AND CS75:\n";
echo str_repeat("=", 120) . "\n\n";

// Find CS74 and CS75 in nonEmptyLines
foreach ($nonEmptyLines as $idx => $line) {
    if (strpos($line, 'CS73') !== false ||
        strpos($line, 'CS74') !== false ||
        strpos($line, 'CS75') !== false ||
        strpos($line, 'CS76') !== false) {
        echo "nonEmptyLines[$idx]: [$line]\n";
    }
}

echo "\n\nLINES 70-80 IN NONEMPTYLINES:\n";
echo str_repeat("=", 120) . "\n";
for ($i = 70; $i <= 80 && $i < count($nonEmptyLines); $i++) {
    $line = $nonEmptyLines[$i];
    $marker = "";
    if (strpos($line, 'CS74') !== false) $marker = " <<<< CS74";
    if (strpos($line, 'CS75') !== false) $marker = " <<<< CS75";
    if (strpos($line, 'INTERACTION') !== false) $marker = " <<<< INTERACTION";
    if (preg_match('/^3\.00\s*\t/', $line)) $marker = " <<<< GRADE LINE";
    if (strpos($line, 'Total Units') !== false) $marker = " <<<< TOTAL UNITS";

    echo sprintf("Line %3d: [%s]%s\n", $i, substr($line, 0, 100), $marker);
}
