<?php
require_once 'classes/GradeExtractor.php';

// Test with the most recent PDF
$pdfPath = 'uploads/grades/grade_49_1764676663.pdf';

echo "DEEP INVESTIGATION: Grade Extraction Analysis\n";
echo "================================================\n\n";
echo "Testing PDF: $pdfPath\n\n";

$extractor = new GradeExtractor();
$result = $extractor->extractGradesFromPDF($pdfPath);

echo "EXTRACTION SUMMARY:\n";
echo "===================\n";
echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
echo "Message: " . $result['message'] . "\n";
echo "Total Grades Extracted: " . count($result['grades']) . "\n\n";

echo "RAW TEXT PREVIEW (First 5000 chars):\n";
echo "====================================\n";
if (isset($result['debug']['raw_text_preview'])) {
    echo $result['debug']['raw_text_preview'] . "\n\n";
}

echo "\nFIRST 30 RAW LINES (as extracted from PDF):\n";
echo "============================================\n";
if (isset($result['debug']['first_20_raw_lines'])) {
    foreach (array_slice($result['debug']['first_20_raw_lines'], 0, 30) as $lineInfo) {
        echo "Line {$lineInfo['line_num']}: {$lineInfo['content']}\n";
        echo "  Length: {$lineInfo['length']}, Hex: {$lineInfo['hex_sample']}\n";
    }
}

echo "\n\nGRADES WITH ZERO VALUES (PROBLEMATIC):\n";
echo "========================================\n";
$zeroGrades = array_filter($result['grades'], function($g) { return $g['grade'] == 0.0; });
foreach ($zeroGrades as $grade) {
    echo "CODE: {$grade['subject_code']} | NAME: {$grade['subject_name']} | GRADE: {$grade['grade']}\n";
    if (isset($grade['source_line'])) {
        echo "  Source: {$grade['source_line']}\n";
    }
}

echo "\n\nGRADES WITH NON-ZERO VALUES (WORKING):\n";
echo "=========================================\n";
$nonZeroGrades = array_filter($result['grades'], function($g) { return $g['grade'] > 0.0; });
foreach (array_slice($nonZeroGrades, 0, 10) as $grade) {
    echo "CODE: {$grade['subject_code']} | NAME: {$grade['subject_name']} | GRADE: {$grade['grade']}\n";
    if (isset($grade['source_line'])) {
        echo "  Source: {$grade['source_line']}\n";
    }
}

echo "\n\nSPECIFIC LINE TRACKING (CS lines):\n";
echo "====================================\n";
if (isset($result['debug']['specific_line_tracking'])) {
    foreach ($result['debug']['specific_line_tracking'] as $track) {
        echo "Line {$track['line_number']}:\n";
        echo "  Content: {$track['content']}\n";
        echo "  Has tabs: {$track['has_tabs']}\n";
        echo "  Regex match: {$track['regex_match']}\n";
        echo "  Tab parts count: {$track['tab_parts_count']}\n";
        echo "  Tab parts: " . json_encode($track['tab_parts']) . "\n";
        echo "  Full hex: " . substr($track['full_hex'], 0, 200) . "...\n\n";
    }
}

echo "\n\nUNPARSED CS LINES:\n";
echo "===================\n";
if (isset($result['debug']['unparsed_cs_lines'])) {
    foreach (array_values($result['debug']['unparsed_cs_lines']) as $idx => $unparsed) {
        if ($idx >= 10) break; // Limit to 10
        echo "Line {$unparsed['line_number']}:\n";
        echo "  Content: {$unparsed['content']}\n";
        echo "  Length: {$unparsed['length']}\n";
        echo "  Hex: {$unparsed['hex_preview']}\n\n";
    }
}

echo "\n\nPARSEGRADELINE CALLS (Debug tracking):\n";
echo "========================================\n";
if (isset($result['debug']['parseGradeLine_calls'])) {
    foreach (array_slice($result['debug']['parseGradeLine_calls'], 0, 20) as $call) {
        echo "Method: {$call['method_called']}\n";
        echo "  Input: {$call['input_line']}\n";
        if (isset($call['final_result'])) {
            echo "  Result: {$call['final_result']}\n";
            if (isset($call['reason'])) {
                echo "  Reason: {$call['reason']}\n";
            }
        }
        if (isset($call['returning_result'])) {
            echo "  Returning: " . json_encode($call['returning_result']) . "\n";
        }
        echo "\n";
    }
}

echo "\n\nNSTP IN NON-EMPTY LINES (main function):\n";
echo "=========================================\n";
if (isset($result['debug']['nstp_in_nonempty'])) {
    foreach ($result['debug']['nstp_in_nonempty'] as $line) {
        echo "Index {$line['index']}: [{$line['line']}]\n";
    }
    echo "Total: " . count($result['debug']['nstp_in_nonempty']) . "\n";
} else {
    echo "No NSTP lines in nonEmptyLines\n";
}

echo "\n\nNSTP LINES BEFORE COMBINE (parseGradeLines):\n";
echo "=============================================\n";
if (isset($result['debug']['nstp_lines_before_combine'])) {
    foreach ($result['debug']['nstp_lines_before_combine'] as $line) {
        echo "Index {$line['index']}: [{$line['line']}]\n";
    }
    echo "Total NSTP lines found: " . count($result['debug']['nstp_lines_before_combine']) . "\n";
} else {
    echo "No NSTP lines found before combine\n";
}

echo "\n\nNSTP ENTERING COMBINE LOGIC:\n";
echo "=============================\n";
if (isset($result['debug']['nstp_entering_combine'])) {
    foreach ($result['debug']['nstp_entering_combine'] as $entry) {
        echo "i={$entry['i']}, j_initial={$entry['j_initial']}:\n";
        echo "  Line: [{$entry['currentLine']}]\n\n";
    }
} else {
    echo "No NSTP lines entered combine logic\n";
}

echo "\n\nNSTP COMBINATIONS DEBUG:\n";
echo "========================\n";
if (isset($result['debug']['nstp_combinations'])) {
    foreach ($result['debug']['nstp_combinations'] as $nstp) {
        echo "Index {$nstp['index']}:\n";
        echo "  Original: {$nstp['original_line']}\n";
        echo "  Combined: {$nstp['combined']}\n";
        echo "  j_value: {$nstp['j_value']}\n\n";
    }
} else {
    echo "No NSTP combinations found in debug info\n";
}

echo "\nNSTP WHILE LOOP ITERATIONS:\n";
if (isset($result['debug']['nstp_while_loop'])) {
    foreach ($result['debug']['nstp_while_loop'] as $iter) {
        echo "Iteration {$iter['iteration']}, j={$iter['j']}:\n";
        echo "  nextLine: [{$iter['nextLine']}]\n";
        echo "  nextLine_hex: {$iter['nextLine_hex']}\n";
        echo "  length: {$iter['nextLine_length']}\n";
        echo "  matches_digit_pattern: {$iter['matches_digit_pattern']}\n";
        echo "  combined_so_far: [{$iter['combined_so_far']}]\n\n";
    }
} else {
    echo "No while loop iterations tracked\n";
}

echo "\nNSTP FIXED (targeted fix):\n";
if (isset($result['debug']['nstp_fixed'])) {
    foreach ($result['debug']['nstp_fixed'] as $fix) {
        echo "Original: [{$fix['original_line']}]\n";
        echo "  + Continuation: [{$fix['continuation']}]\n";
        echo "  + Grade line: [{$fix['grade_line']}]\n";
        echo "  = Combined: [{$fix['combined']}]\n\n";
    }
    echo "Total NSTP subjects fixed: " . count($result['debug']['nstp_fixed']) . "\n";
} else {
    echo "No NSTP subjects fixed\n";
}

echo "\nNSTP PRIMARY PATTERN CHECKS:\n";
if (isset($result['debug']['nstp_primary_pattern'])) {
    foreach ($result['debug']['nstp_primary_pattern'] as $check) {
        echo "j={$check['j']}: nextLine=[{$check['nextLine']}]\n";
        echo "  Matches primary: {$check['matches_primary']}\n\n";
    }
} else {
    echo "No primary pattern checks\n";
}

echo "\nNSTP SINGLE DIGIT PATTERN MATCHES:\n";
if (isset($result['debug']['nstp_single_digit_match'])) {
    foreach ($result['debug']['nstp_single_digit_match'] as $match) {
        echo "j={$match['j']}: nextLine=[{$match['nextLine']}]\n";
        echo "  About to continue: {$match['about_to_continue']}\n\n";
    }
} else {
    echo "No single digit pattern matches\n";
}

echo "\n\nCOMBINED LINES SAMPLE:\n";
echo "=======================\n";
if (isset($result['debug']['combined_lines_sample'])) {
    foreach ($result['debug']['combined_lines_sample'] as $combined) {
        echo "Line {$combined['line_number']}:\n";
        echo "  Combined: {$combined['combined_line']}\n";
        echo "  Has grade: {$combined['has_grade']}\n\n";
    }
}

echo "\n\nTAB PARSING ATTEMPTS:\n";
echo "======================\n";
if (isset($result['debug']['tab_parsing_attempts'])) {
    foreach (array_slice($result['debug']['tab_parsing_attempts'], 0, 15) as $attempt) {
        echo "Line: {$attempt['line']}\n";
        echo "  Parts count: {$attempt['parts_count']}\n";
        echo "  Parts: " . json_encode($attempt['parts']) . "\n\n";
    }
}
