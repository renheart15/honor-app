<?php
session_start();
// Simulate logged in adviser
$_SESSION['user_id'] = 48; // achie@gmail.com
$_SESSION['role'] = 'adviser';
$_SESSION['first_name'] = 'achie';
$_SESSION['department'] = 'Bachelor of Science in Information Technology';

require_once 'config/config.php';

$database = new Database();
$db = $database->getConnection();

$adviser_id = $_SESSION['user_id'];

echo "<h2>Debug Adviser Dashboard Data</h2>";

// Get adviser info
$query = "SELECT id, first_name, last_name, department, section, year_level FROM users WHERE id = :adviser_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':adviser_id', $adviser_id);
$stmt->execute();
$adviser_info = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>1. Adviser Information</h3>";
echo "<pre>";
print_r($adviser_info);
echo "</pre>";

$adviser_section = $adviser_info['section'] ?? null;
$department = $adviser_info['department'];

echo "<p><strong>Raw Section:</strong> " . htmlspecialchars($adviser_section) . "</p>";

// Decode JSON section
if ($adviser_section) {
    $sections_array = json_decode($adviser_section, true);
    echo "<p><strong>Decoded Section Array:</strong> ";
    print_r($sections_array);
    echo "</p>";
    $adviser_section = is_array($sections_array) && !empty($sections_array) ? $sections_array[0] : $adviser_section;
}

echo "<p><strong>Final Section to Query:</strong> [" . htmlspecialchars($adviser_section) . "]</p>";
echo "<p><strong>Department:</strong> " . htmlspecialchars($department) . "</p>";

// Check all students in department
echo "<h3>2. All Students in Department</h3>";
$query = "SELECT id, first_name, last_name, section, year_level, status FROM users WHERE role = 'student' AND department = :department";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$all_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Total students in " . htmlspecialchars($department) . ": <strong>" . count($all_students) . "</strong></p>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Name</th><th>Section</th><th>Year</th><th>Status</th></tr>";
foreach ($all_students as $s) {
    echo "<tr>";
    echo "<td>" . $s['id'] . "</td>";
    echo "<td>" . htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) . "</td>";
    echo "<td>[" . htmlspecialchars($s['section']) . "]</td>";
    echo "<td>" . $s['year_level'] . "</td>";
    echo "<td>" . $s['status'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Try the exact query from dashboard
echo "<h3>3. Dashboard Query Results</h3>";

if ($adviser_section) {
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'student' AND department = :department AND section = :section AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->bindParam(':section', $adviser_section);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<p><strong>Query:</strong> SELECT COUNT(*) FROM users WHERE role='student' AND department='" . htmlspecialchars($department) . "' AND section='" . htmlspecialchars($adviser_section) . "' AND status='active'</p>";
    echo "<p><strong>Result:</strong> " . $result['count'] . "</p>";
}

// Check grade submissions
echo "<h3>4. Grade Submissions</h3>";
$query = "SELECT COUNT(*) as count FROM grade_submissions";
$stmt = $db->query($query);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<p>Total grade submissions in system: <strong>" . $result['count'] . "</strong></p>";

// Check honor applications
echo "<h3>5. Honor Applications</h3>";
$query = "SELECT COUNT(*) as count FROM honor_applications";
$stmt = $db->query($query);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<p>Total honor applications in system: <strong>" . $result['count'] . "</strong></p>";

// Check GWA calculations
echo "<h3>6. GWA Calculations</h3>";
$query = "SELECT COUNT(*) as count FROM gwa_calculations";
$stmt = $db->query($query);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<p>Total GWA calculations in system: <strong>" . $result['count'] . "</strong></p>";
?>
