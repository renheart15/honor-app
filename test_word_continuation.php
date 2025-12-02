<?php
require_once 'classes/GradeExtractor.php';

$pdfPath = 'uploads/grades/grade_49_1764680269.pdf';
echo "Testing Word Continuation Pattern\n";
echo str_repeat("=", 100) . "\n\n";

$extractor = new GradeExtractor();
$result = $extractor->extractGradesFromPDF($pdfPath);

echo "EXTRACTION SUMMARY:\n";
echo "Total grades extracted: " . count($result['grades']) . "\n";
echo "Expected: 51\n";
echo "Missing: " . (51 - count($result['grades'])) . "\n\n";

// Check if word continuation pattern was triggered
if (isset($result['debug']['word_continuation_triggered'])) {
    echo "WORD CONTINUATION PATTERN TRIGGERED:\n";
    echo str_repeat("-", 100) . "\n";
    foreach ($result['debug']['word_continuation_triggered'] as $wc) {
        echo "Line i={$wc['i']}, j={$wc['j']}:\n";
        echo "  NextLine: [{$wc['nextLine']}]\n";
        echo "  Combined before: [{$wc['combined_before']}]\n";
        echo "  Combined after: [{$wc['combined_after']}]\n";
        echo "  NextLine hex: {$wc['nextLine_hex']}\n\n";
    }
    echo "Total word continuations: " . count($result['debug']['word_continuation_triggered']) . "\n\n";
} else {
    echo "WORD CONTINUATION PATTERN NEVER TRIGGERED!\n\n";
}

// Check if CS74 and CS75 are in the extracted grades
echo "Checking for CS74 and CS75:\n";
echo str_repeat("-", 100) . "\n";
$cs74Found = false;
$cs75Found = false;
foreach ($result['grades'] as $g) {
    if ($g['subject_code'] === 'CS74') {
        $cs74Found = true;
        echo "CS74 FOUND: {$g['subject_name']} - Grade: {$g['grade']}\n";
    }
    if ($g['subject_code'] === 'CS75') {
        $cs75Found = true;
        echo "CS75 FOUND: {$g['subject_name']} - Grade: {$g['grade']}\n";
    }
}
if (!$cs74Found) echo "CS74 NOT FOUND\n";
if (!$cs75Found) echo "CS75 NOT FOUND\n";
echo "\n";

// Check if primary pattern was matched
if (isset($result['debug']['primary_pattern_matched'])) {
    echo "PRIMARY PATTERN MATCHES FOR CS74/CS75:\n";
    echo str_repeat("-", 100) . "\n";
    $cs74_cs75_matches = [];
    foreach ($result['debug']['primary_pattern_matched'] as $match) {
        if ($match['contains_cs74'] === 'YES' || $match['contains_cs75'] === 'YES') {
            $cs74_cs75_matches[] = $match;
            echo "Match at i={$match['i']}, j={$match['j']}:\n";
            echo "  NextLine: [{$match['nextLine']}]\n";
            echo "  Combined before: [{$match['combined_before']}]\n";
            echo "  Combined after: [{$match['combined_after']}]\n";
            echo "  Contains CS74: {$match['contains_cs74']}\n";
            echo "  Contains CS75: {$match['contains_cs75']}\n\n";
        }
    }
    if (empty($cs74_cs75_matches)) {
        echo "NO PRIMARY PATTERN MATCHES FOR CS74 OR CS75\n";
    }
    echo "Total primary pattern matches: " . count($result['debug']['primary_pattern_matched']) . "\n\n";
} else {
    echo "NO PRIMARY PATTERN MATCHES AT ALL\n\n";
}

// Check if CS74/CS75 were stored in combinedLines
if (isset($result['debug']['cs74_cs75_stored'])) {
    echo "CS74/CS75 STORAGE IN COMBINEDLINES:\n";
    echo str_repeat("-", 100) . "\n";
    foreach ($result['debug']['cs74_cs75_stored'] as $stored) {
        echo "Stored at i={$stored['i']}, j={$stored['j']}:\n";
        echo "  Combined: [{$stored['combined']}]\n";
        echo "  Has grade: {$stored['has_grade']}\n";
        echo "  Next i will be: {$stored['next_i_will_be']}\n\n";
    }
} else {
    echo "CS74/CS75 NOT STORED IN COMBINEDLINES!\n\n";
}

// Show combined lines sample to see if CS74/CS75 appear there
if (isset($result['debug']['combined_lines_sample'])) {
    echo "COMBINED LINES WITH CS74 OR CS75:\n";
    echo str_repeat("-", 100) . "\n";
    foreach ($result['debug']['combined_lines_sample'] as $sample) {
        if (strpos($sample['combined_line'], 'CS74') !== false ||
            strpos($sample['combined_line'], 'CS75') !== false) {
            echo "Line {$sample['line_number']}: {$sample['combined_line']}\n";
            echo "  Has grade: {$sample['has_grade']}\n\n";
        }
    }
}

// Check CS74/CS75 parsing results
if (isset($result['debug']['cs74_cs75_parsing'])) {
    echo "CS74/CS75 PARSING RESULTS:\n";
    echo str_repeat("-", 100) . "\n";
    foreach ($result['debug']['cs74_cs75_parsing'] as $parse) {
        echo "Line {$parse['line_number']}:\n";
        echo "  Line: [{$parse['line']}]\n";
        echo "  Current semester: [{$parse['current_semester']}]\n";
        echo "  Parse result: {$parse['parse_result']}\n";
        echo "  Grade data: {$parse['grade_data']}\n\n";
    }
} else {
    echo "CS74/CS75 PARSING INFO NOT AVAILABLE\n\n";
}

// Show subjects by semester to see what we're getting
echo "\nEXTRACTED SUBJECTS BY SEMESTER:\n";
echo str_repeat("=", 100) . "\n";
$bySemester = [];
foreach ($result['grades'] as $g) {
    $sem = $g['semester'];
    if (!isset($bySemester[$sem])) {
        $bySemester[$sem] = [];
    }
    $bySemester[$sem][] = $g['subject_code'];
}

foreach ($bySemester as $sem => $codes) {
    echo "$sem: " . count($codes) . " subjects\n";
    echo "  " . implode(", ", $codes) . "\n\n";
}
