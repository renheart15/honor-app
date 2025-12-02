<?php
require_once 'vendor/autoload.php';
use Smalot\PdfParser\Parser;

$parser = new Parser();
$pdf = $parser->parseFile('uploads/grades/grade_49_1764667013.pdf');
$text = $pdf->getText();
$lines = explode("\n", $text);

// Clean and re-index
$cleanLines = array_map('trim', $lines);
$nonEmptyLines = array_filter($cleanLines, function($line) {
    return !empty($line);
});
$nonEmptyLines = array_values($nonEmptyLines);

echo "Checking lines around NSTP entries:\n";
echo "====================================\n\n";

// Find NSTP lines
for ($i = 0; $i < count($nonEmptyLines); $i++) {
    if (strpos($nonEmptyLines[$i], 'NSTP') !== false) {
        echo "Found NSTP at index $i:\n";
        for ($j = $i - 1; $j <= $i + 3; $j++) {
            if ($j >= 0 && $j < count($nonEmptyLines)) {
                $line = $nonEmptyLines[$j];
                echo "  [$j]: [$line]\n";
                echo "      Starts with CS: " . (preg_match('/^CS\d+\s/', $line) ? 'YES' : 'NO') . "\n";
                echo "      Starts with CS (tab): " . (preg_match('/^CS\d+\t/', $line) ? 'YES' : 'NO') . "\n";
                echo "      Is single digit: " . (preg_match('/^\d+$/', $line) && strlen($line) <= 2 ? 'YES' : 'NO') . "\n";
                echo "      Matches grade pattern: " . (preg_match('/^(\d+\.\d+)\t(\d+:\d+[AP]M-\d+:\d+[AP]M)\t([A-Za-z]+)\t(\d+\.\d+)$/', $line) ? 'YES' : 'NO') . "\n";
            }
        }
        echo "\n";
    }
}
