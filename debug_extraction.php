<?php
require_once 'config/config.php';
require_once 'classes/GradeExtractor.php';

$database = new Database();
$db = $database->getConnection();

$extractor = new GradeExtractor();

// Test with submission 15
$submission_id = 15;

// Get the file path for submission 15
$stmt = $db->prepare("SELECT file_path FROM grade_submissions WHERE id = ?");
$stmt->execute([$submission_id]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission) {
    die("Submission not found");
}

$possiblePaths = [
    __DIR__ . '/uploads/' . $submission['file_path'],
    __DIR__ . '/uploads/grades/' . $submission['file_path'],
    __DIR__ . '/uploads/grades/' . basename($submission['file_path']),
    __DIR__ . '/' . $submission['file_path']
];

$pdfPath = null;
foreach ($possiblePaths as $testPath) {
    if (file_exists($testPath) && is_readable($testPath)) {
        $pdfPath = $testPath;
        break;
    }
}

if (!$pdfPath) {
    die("PDF file not found. Tried paths: " . implode(", ", $possiblePaths));
}

echo "Found PDF at: $pdfPath\n\n";

$result = $extractor->extractGradesFromPDF($pdfPath);

echo "=== EXTRACTION RESULT ===\n";
echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
echo "Message: " . $result['message'] . "\n";
echo "Grades found: " . count($result['grades']) . "\n\n";

if (isset($result['debug'])) {
    echo "=== DEBUG INFO ===\n";
    echo "Raw text length: " . ($result['debug']['raw_text_length'] ?? 'N/A') . "\n";
    echo "Non-empty lines: " . ($result['debug']['non_empty_lines'] ?? 'N/A') . "\n";
    echo "Total grades found: " . ($result['debug']['total_grades_found'] ?? 'N/A') . "\n\n";

    if (isset($result['debug']['first_20_raw_lines'])) {
        echo "=== FIRST 20 RAW LINES ===\n";
        foreach ($result['debug']['first_20_raw_lines'] as $line) {
            echo $line['line_num'] . ": " . $line['content'] . " (len: " . $line['length'] . ", trimmed: " . $line['trimmed'] . ")\n";
        }
        echo "\n";
    }

    if (isset($result['debug']['first_10_lines'])) {
        echo "=== FIRST 10 PROCESSED LINES ===\n";
        foreach ($result['debug']['first_10_lines'] as $line) {
            echo "- " . $line . "\n";
        }
        echo "\n";
    }

    if (isset($result['debug']['unparsed_cs_lines'])) {
        echo "=== UNPARSED CS LINES ===\n";
        foreach ($result['debug']['unparsed_cs_lines'] as $line) {
            echo "Line " . $line['line_number'] . ": " . $line['content'] . " (len: " . $line['length'] . ")\n";
        }
        echo "\n";
    }

    if (isset($result['debug']['combined_lines_sample'])) {
        echo "=== COMBINED LINES SAMPLE ===\n";
        foreach ($result['debug']['combined_lines_sample'] as $line) {
            echo "Line " . $line['line_number'] . ": " . $line['combined_line'] . " (len: " . strlen($line['combined_line']) . ", has_grade: " . $line['has_grade'] . ")\n";
        }
        echo "\n";
    }

    if (isset($result['debug']['specific_line_tracking'])) {
        echo "=== SPECIFIC LINE TRACKING ===\n";
        foreach ($result['debug']['specific_line_tracking'] as $line) {
            echo "Line " . $line['line_number'] . ": " . $line['content'] . "\n";
            echo "  Has tabs: " . $line['has_tabs'] . ", Regex match: " . $line['regex_match'] . "\n";
            echo "  Tab parts count: " . $line['tab_parts_count'] . "\n";
            if (!empty($line['tab_parts'])) {
                echo "  Tab parts: " . implode(" | ", array_map(function($part) { return "'$part'"; }, $line['tab_parts'])) . "\n";
            }
            echo "\n";
        }
    }

    if (isset($result['debug']['test_lines'])) {
        echo "=== TEST LINES ===\n";
        foreach ($result['debug']['test_lines'] as $line) {
            echo "Original: " . $line['original'] . "\n";
            echo "  Length: " . $line['length'] . "\n";
            echo "  Test regex1: " . $line['test_regex1'] . "\n";
            echo "  Test regex2: " . $line['test_regex2'] . "\n\n";
        }
    }
}

echo "=== EXTRACTED GRADES ===\n";
foreach ($result['grades'] as $grade) {
    $highlight = ($grade['grade'] == 0.0) ? " *** ZERO GRADE ***" : "";
    printf("%-10s %-50s %-7.2f %-7.2f %s%s\n",
        $grade['subject_code'],
        substr($grade['subject_name'], 0, 50),
        $grade['units'],
        $grade['grade'],
        $grade['semester'] ?? '',
        $highlight
    );
}
