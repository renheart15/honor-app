<?php
require_once 'vendor/autoload.php';
use Smalot\PdfParser\Parser;

$parser = new Parser();
$pdf = $parser->parseFile('uploads/grades/grade_49_1764667013.pdf');
$text = $pdf->getText();
$lines = explode("\n", $text);

// Manually test the combination logic for CS27 NSTP
echo "Testing NSTP combination logic:\n";
echo "================================\n\n";

// Simulate what should happen for lines 9, 10, 11
$line9 = $lines[9];  // CS27	NSTP 1	NATIONAL SERVICE TRAINING PROGRAM
$line10 = $lines[10]; // 1
$line11 = $lines[11]; // 3.00	08:00AM-11:00AM	Sat	1.9

echo "Line 9:  [$line9]\n";
echo "Line 10: [$line10]\n";
echo "Line 11: [$line11]\n\n";

// Check patterns
echo "Line 10 matches single digit: " . (preg_match('/^\d+$/', $line10) && strlen($line10) <= 2 ? 'YES' : 'NO') . "\n";
echo "Line 11 matches tab-separated grade: " . (preg_match('/^(\d+\.\d+)\t(\d+:\d+[AP]M-\d+:\d+[AP]M)\t([A-Za-z]+)\t(\d+\.\d+)$/', $line11) ? 'YES' : 'NO') . "\n\n";

// Simulate combination
$combined = $line9;
echo "Starting with: [$combined]\n\n";

// Step 1: Append line 10 (the "1")
if (preg_match('/^\d+$/', $line10) && strlen($line10) <= 2) {
    if (strpos($combined, "\t") !== false) {
        $parts = explode("\t", $combined);
        if (count($parts) >= 3) {
            $parts[2] .= " " . $line10;
            $combined = implode("\t", $parts);
            echo "After appending '1' to subject name: [$combined]\n\n";
        }
    }
}

// Step 2: Append line 11 (the grade info)
if (preg_match('/^(\d+\.\d+)\t(\d+:\d+[AP]M-\d+:\d+[AP]M)\t([A-Za-z]+)\t(\d+\.\d+)$/', $line11)) {
    if (strpos($combined, "\t") !== false) {
        $combined .= "\t" . $line11;
        echo "After appending grade info: [$combined]\n\n";
    }
}

// Parse the combined line
$parts = explode("\t", $combined);
echo "Final parts count: " . count($parts) . "\n";
echo "Parts:\n";
foreach ($parts as $i => $part) {
    echo "  [$i]: [$part]\n";
}

// Test if this would extract correctly
if (count($parts) >= 7) {
    echo "\nWould extract as:\n";
    echo "  Code: " . trim($parts[0]) . "\n";
    echo "  Type: " . trim($parts[1]) . "\n";
    echo "  Name: " . trim($parts[2]) . "\n";
    echo "  Units: " . floatval($parts[3]) . "\n";
    echo "  Time: " . trim($parts[4]) . "\n";
    echo "  Day: " . trim($parts[5]) . "\n";
    echo "  Grade: " . floatval($parts[6]) . "\n";
} else {
    echo "\nNOT ENOUGH PARTS TO EXTRACT GRADE!\n";
}
