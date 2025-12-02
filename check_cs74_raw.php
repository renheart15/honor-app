<?php
require_once 'vendor/autoload.php';
use Smalot\PdfParser\Parser;

$pdfPath = 'uploads/grades/grade_49_1764680269.pdf';
$parser = new Parser();
$pdf = $parser->parseFile($pdfPath);
$text = $pdf->getText();
$lines = explode("\n", $text);

echo "RAW LINES AROUND CS74:\n";
echo str_repeat("=", 100) . "\n\n";

$found = false;
foreach ($lines as $idx => $line) {
    $trimmed = trim($line);

    if (strpos($trimmed, 'CS74') !== false) {
        $found = true;
        // Show 2 lines before CS74
        if ($idx >= 2) {
            echo "Line " . ($idx - 2) . ": [" . trim($lines[$idx - 2]) . "]\n";
            echo "Line " . ($idx - 1) . ": [" . trim($lines[$idx - 1]) . "]\n";
        }

        // Show CS74 and 5 lines after
        for ($i = 0; $i <= 5; $i++) {
            if (isset($lines[$idx + $i])) {
                $current = trim($lines[$idx + $i]);
                echo "Line " . ($idx + $i) . ": [$current]\n";
                echo "  Length: " . strlen($current) . "\n";
                echo "  Hex: " . bin2hex($current) . "\n";

                // Check if it matches the tab-separated grade pattern
                if (preg_match('/^(\d+\.\d+)\t(\d+:\d+[AP]M-\d+:\d+[AP]M)\t([A-Za-z]+)\t(\d+\.\d+)$/', $current)) {
                    echo "  ✓ MATCHES TAB-SEPARATED GRADE PATTERN\n";
                }

                // Check if it matches the word continuation pattern
                if (preg_match('/^[A-Z][A-Z\s]+$/', $current) && strlen($current) < 50) {
                    echo "  ✓ MATCHES WORD CONTINUATION PATTERN\n";
                }

                echo "\n";
            }
        }
        break;
    }
}

if (!$found) {
    echo "CS74 not found in PDF\n";
}

echo "\nRAW LINES AROUND CS75:\n";
echo str_repeat("=", 100) . "\n\n";

$found = false;
foreach ($lines as $idx => $line) {
    $trimmed = trim($line);

    if (strpos($trimmed, 'CS75') !== false) {
        $found = true;
        // Show CS75 and 3 lines after
        for ($i = 0; $i <= 3; $i++) {
            if (isset($lines[$idx + $i])) {
                $current = trim($lines[$idx + $i]);
                echo "Line " . ($idx + $i) . ": [$current]\n";
                echo "  Length: " . strlen($current) . "\n";
                echo "  Hex: " . bin2hex($current) . "\n\n";
            }
        }
        break;
    }
}

if (!$found) {
    echo "CS75 not found in PDF\n";
}
