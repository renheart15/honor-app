<?php
require_once 'config/config.php';
$db = new Database();
$conn = $db->getConnection();

// Check what grades are in submission 15
$stmt = $conn->prepare("SELECT subject_code, subject_name, units, grade, semester_taken FROM grades WHERE submission_id = 15 ORDER BY id LIMIT 60");
$stmt->execute();
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Current extracted grades for submission 15:\n";
echo str_repeat("=", 100) . "\n";
printf("%-10s %-50s %-7s %-7s %s\n", "CODE", "NAME", "UNITS", "GRADE", "SEMESTER");
echo str_repeat("=", 100) . "\n";

foreach ($grades as $g) {
    $highlight = ($g['grade'] == 0.0) ? " *** ZERO ***" : "";
    printf("%-10s %-50s %-7.2f %-7.2f %s%s\n", 
        $g['subject_code'], 
        substr($g['subject_name'], 0, 50), 
        $g['units'], 
        $g['grade'],
        substr($g['semester_taken'], 0, 30),
        $highlight
    );
}
