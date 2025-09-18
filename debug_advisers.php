<?php
require_once 'config/config.php';

echo "<h2>Debug: Advisers Page Data</h2>";

// Check session data
echo "<h3>1. Session Information:</h3>";
session_start();
echo "<pre>";
echo "Session data:\n";
print_r($_SESSION);
echo "</pre>";

$database = new Database();
$db = $database->getConnection();

$chairperson_id = $_SESSION['user_id'] ?? 'NOT_SET';
$department = $_SESSION['department'] ?? 'NOT_SET';

echo "<p><strong>Chairperson ID:</strong> $chairperson_id</p>";
echo "<p><strong>Department:</strong> $department</p>";

// Test the exact query from advisers.php
echo "<h3>2. Advisers Query Results:</h3>";

$query = "SELECT u.*,
                 (SELECT COUNT(*) FROM grade_submissions gs
                  JOIN users s ON gs.user_id = s.id
                  WHERE gs.processed_by = u.id AND s.department = :department) as processed_submissions,
                 (SELECT COUNT(*) FROM honor_applications ha
                  JOIN users s ON ha.user_id = s.id
                  WHERE ha.reviewed_by = u.id AND s.department = :department) as reviewed_applications,
                 (SELECT COUNT(*) FROM users s WHERE s.role = 'student' AND s.department = :department AND s.status = 'active') as total_students
          FROM users u
          WHERE u.role = 'adviser' AND u.department = :department
          ORDER BY u.status DESC, u.last_name, u.first_name";

try {
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->execute();
    $advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>Query executed successfully. Found " . count($advisers) . " advisers.</strong></p>";

    echo "<h4>Raw query results:</h4>";
    echo "<pre>";
    foreach ($advisers as $i => $adviser) {
        echo "Adviser #" . ($i + 1) . ":\n";
        echo "  ID: " . $adviser['id'] . "\n";
        echo "  Name: " . $adviser['first_name'] . " " . $adviser['last_name'] . "\n";
        echo "  Email: " . $adviser['email'] . "\n";
        echo "  Status: " . $adviser['status'] . "\n";
        echo "  Section: " . $adviser['section'] . "\n";
        echo "  Processed Submissions: " . $adviser['processed_submissions'] . "\n";
        echo "  Reviewed Applications: " . $adviser['reviewed_applications'] . "\n";
        echo "  Total Students: " . $adviser['total_students'] . "\n";
        echo "\n";
    }
    echo "</pre>";

    // Process sections like in the original file
    echo "<h4>After section processing:</h4>";
    foreach ($advisers as &$adviser) {
        if (!empty($adviser['section'])) {
            $sections = json_decode($adviser['section'], true);
            if (!is_array($sections)) {
                $sections = array_map('trim', explode(',', $adviser['section']));
            }
            $adviser['assigned_sections'] = $sections;
        } else {
            $adviser['assigned_sections'] = [];
        }
    }

    echo "<pre>";
    foreach ($advisers as $i => $adviser) {
        echo "Adviser #" . ($i + 1) . " (Processed):\n";
        echo "  ID: " . $adviser['id'] . "\n";
        echo "  Name: " . $adviser['first_name'] . " " . $adviser['last_name'] . "\n";
        echo "  Assigned Sections: " . json_encode($adviser['assigned_sections']) . "\n";
        echo "\n";
    }
    echo "</pre>";

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Query Error:</strong> " . $e->getMessage() . "</p>";
}

// Check available sections query
echo "<h3>3. Available Sections Query:</h3>";
$query = "SELECT DISTINCT section, year_level FROM users
          WHERE role = 'student' AND department = :department AND section IS NOT NULL AND status = 'active'
          ORDER BY year_level, section";
try {
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->execute();
    $available_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Found " . count($available_sections) . " available sections.</p>";
    echo "<pre>";
    print_r($available_sections);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Sections Query Error:</strong> " . $e->getMessage() . "</p>";
}

// Check filter parameter
echo "<h3>4. Filter Parameter:</h3>";
$filter = $_GET['filter'] ?? 'all';
echo "<p><strong>Filter:</strong> $filter</p>";

if ($filter !== 'all') {
    $filtered_advisers = array_filter($advisers, function($adviser) use ($filter) {
        return $adviser['status'] === $filter;
    });
    echo "<p>After filtering: " . count($filtered_advisers) . " advisers</p>";
    echo "<pre>";
    foreach ($filtered_advisers as $adviser) {
        echo "  - " . $adviser['first_name'] . " " . $adviser['last_name'] . " (" . $adviser['status'] . ")\n";
    }
    echo "</pre>";
}
?>