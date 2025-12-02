<?php
require_once 'vendor/autoload.php';
use Smalot\PdfParser\Parser;

// Get raw lines
$parser = new Parser();
$pdf = $parser->parseFile('uploads/grades/grade_49_1764680269.pdf');
$text = $pdf->getText();
$rawLines = explode("\n", $text);

// Clean and filter like the extractor does
$cleanLines = array_map('trim', $rawLines);
$nonEmptyLines = array_filter($cleanLines, function($line) {
    return !empty($line);
});
$nonEmptyLines = array_values($nonEmptyLines);

echo "Checking if CS74 and CS75 exist in nonEmptyLines:\n";
echo str_repeat('=', 100) . "\n\n";

foreach ($nonEmptyLines as $idx => $line) {
    if (strpos($line, 'CS74') === 0 || strpos($line, 'CS75') === 0) {
        echo "Index $idx: [$line]\n";
    }
}

// Now simulate what combineMultilineSubjects does
echo "\n\nSimulating combineMultilineSubjects for CS74:\n";
echo str_repeat('=', 100) . "\n";

$cs74Idx = null;
foreach ($nonEmptyLines as $idx => $line) {
    if (strpos($line, 'CS74') === 0) {
        $cs74Idx = $idx;
        break;
    }
}

if ($cs74Idx !== null) {
    echo "Found CS74 at index $cs74Idx\n";
    echo "Line $cs74Idx: [{$nonEmptyLines[$cs74Idx]}]\n";
    if (isset($nonEmptyLines[$cs74Idx + 1])) echo "Line " . ($cs74Idx + 1) . ": [{$nonEmptyLines[$cs74Idx + 1]}]\n";
    if (isset($nonEmptyLines[$cs74Idx + 2])) echo "Line " . ($cs74Idx + 2) . ": [{$nonEmptyLines[$cs74Idx + 2]}]\n";

    // Check if line matches CS pattern
    $matches = preg_match('/^CS\d+[\s\t]/', $nonEmptyLines[$cs74Idx]);
    echo "\nMatches CS pattern: " . ($matches ? 'YES' : 'NO') . "\n";

    // Check if has grade at end
    $hasGrade = preg_match('/\d+\.\d+\s*$/', $nonEmptyLines[$cs74Idx]);
    echo "Has grade at end: " . ($hasGrade ? 'YES' : 'NO') . "\n";

    // Should combine next lines
    if (!$hasGrade) {
        $nextLine = isset($nonEmptyLines[$cs74Idx + 1]) ? trim($nonEmptyLines[$cs74Idx + 1]) : '';
        echo "\nNext line: [$nextLine]\n";
        echo "Next line is 'INTERACTION': " . ($nextLine === 'INTERACTION' ? 'YES' : 'NO') . "\n";

        $gradeLine = isset($nonEmptyLines[$cs74Idx + 2]) ? trim($nonEmptyLines[$cs74Idx + 2]) : '';
        echo "Grade line: [$gradeLine]\n";

        // Check if grade line matches the primary pattern
        $matchesPrimary = preg_match('/^\d+\.\d+\s+\d+:\d+[AP]M-\d+:\d+[AP]M\s+[A-Za-z]+\s+\d+\.\d+$/', $gradeLine) ||
            preg_match('/^\d+\.\d+\s{4,}\d+:\d+[AP]M-\d+:\d+[AP]M\s+[A-Za-z]+\s+\d+\.\d+$/', $gradeLine) ||
            preg_match('/^(\d+\.\d+)\t(\d+:\d+[AP]M-\d+:\d+[AP]M)\t([A-Za-z]+)\t(\d+\.\d+)$/', $gradeLine);
        echo "Grade line matches primary pattern: " . ($matchesPrimary ? 'YES' : 'NO') . "\n";
    }
}
