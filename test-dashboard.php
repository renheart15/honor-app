<?php
require_once 'config/config.php';

$database = new Database();
$db = $database->getConnection();

// Check all students
$query = "SELECT id, first_name, last_name, section, year_level, department FROM users WHERE role = 'student'";
$stmt = $db->query($query);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>All Students (" . count($students) . ")</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Name</th><th>Section</th><th>Year</th><th>Department</th></tr>";
foreach ($students as $student) {
    echo "<tr>";
    echo "<td>" . $student['id'] . "</td>";
    echo "<td>" . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . "</td>";
    echo "<td>[" . htmlspecialchars($student['section']) . "]</td>";
    echo "<td>" . $student['year_level'] . "</td>";
    echo "<td>" . htmlspecialchars($student['department']) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check advisers
echo "<h2>Advisers</h2>";
$query = "SELECT id, first_name, last_name, section, department FROM users WHERE role = 'adviser'";
$stmt = $db->query($query);
$advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1'>";
echo "<tr><th>ID</th><th>Name</th><th>Assigned Sections</th><th>Department</th></tr>";
foreach ($advisers as $adviser) {
    echo "<tr>";
    echo "<td>" . $adviser['id'] . "</td>";
    echo "<td>" . htmlspecialchars($adviser['first_name'] . ' ' . $adviser['last_name']) . "</td>";
    echo "<td>" . htmlspecialchars($adviser['section']) . "</td>";
    echo "<td>" . htmlspecialchars($adviser['department']) . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
