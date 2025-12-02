<?php
require_once 'vendor/autoload.php';
use Smalot\PdfParser\Parser;

$parser = new Parser();
$pdf = $parser->parseFile('uploads/grades/grade_49_1764680269.pdf');
$text = $pdf->getText();
$lines = explode("\n", $text);

echo "1st Semester SY 2024-2025 section:\n";
echo str_repeat('=', 100) . "\n\n";

$in1st2024 = false;
foreach ($lines as $idx => $line) {
    $trimmed = trim($line);

    if (strpos($trimmed, '1st Semester SY 2024-2025') !== false) {
        $in1st2024 = true;
        echo "Found semester marker at line $idx\n\n";
        continue;
    }

    if ($in1st2024 && preg_match('/^CS\d+/', $trimmed)) {
        echo "Line $idx: $trimmed\n";
        // Show next 2 lines
        if (isset($lines[$idx + 1])) {
            echo "  +1: " . trim($lines[$idx + 1]) . "\n";
        }
        if (isset($lines[$idx + 2])) {
            echo "  +2: " . trim($lines[$idx + 2]) . "\n";
        }
        echo "\n";
    }

    if ($in1st2024 && (preg_match('/^2nd Semester/', $trimmed) || preg_match('/^Total Units/', $trimmed))) {
        echo "End of section at line $idx: $trimmed\n";
        break;
    }
}
