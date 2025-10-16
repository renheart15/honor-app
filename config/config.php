<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'honor_app');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('BASE_URL', 'http://localhost/honor-app');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
}
if (!file_exists(UPLOAD_PATH . 'grades/')) {
    mkdir(UPLOAD_PATH . 'grades/', 0777, true);
}

// Include required files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/GradeProcessor.php';
require_once __DIR__ . '/../classes/NotificationManager.php';

// Helper functions
function redirect($url) {
    $base_url = rtrim(BASE_URL, '/');
    if (strpos($url, 'http') !== 0) {
        $url = $base_url . '/' . ltrim($url, '/');
    }
    header("Location: " . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        // Use BASE_URL to ensure absolute path works from any directory
        redirect(BASE_URL . '/login.php');
    }
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function formatGWA($gwa) {
    // Truncate to 2 decimal places without rounding
    $truncated = floor((float)$gwa * 100) / 100;
    return number_format($truncated, 2);
}

function formatSectionDisplay($section) {
    if (!$section) return 'N/A';

    // Handle different section formats
    // Convert C-4 format to 4C format
    if (preg_match('/^([A-Z])-(\d+)$/', $section, $matches)) {
        return $matches[2] . $matches[1];
    }

    // Handle formats like "bsit-3a" -> "BSIT-3A"
    $parts = explode('-', $section);
    if (count($parts) === 2) {
        return strtoupper($parts[0]) . '-' . strtoupper($parts[1]);
    }

    return strtoupper($section);
}

function getDepartmentAbbreviation($department) {
    // Convert full department names to abbreviations
    $abbreviations = [
        // College of Agriculture
        'Bachelor of Science in Agriculture' => 'BS Agri',

        // College of Arts and Sciences
        'Bachelor of Arts in English Language' => 'AB English',
        'Bachelor of Arts in Literature' => 'AB Lit',

        // College of Education
        'Bachelor of Elementary Education' => 'BEEd',
        'Bachelor of Secondary Education' => 'BSEd',

        // College of Engineering
        'Bachelor of Science in Civil Engineering' => 'BSCE',
        'Bachelor of Science in Electrical Engineering' => 'BSEE',
        'Bachelor of Science in Industrial Engineering' => 'BSIE',
        'Bachelor of Science in Mechanical Engineering' => 'BSME',

        // College of Technology
        'Bachelor of Industrial Technology' => 'BIT',
        'Bachelor of Science in Information Technology' => 'BSIT',
        'Bachelor of Science in Hospitality Management' => 'BSHM',

        // Other common programs
        'Bachelor of Science in Computer Science' => 'BSCS',
        'Bachelor of Science in Information Systems' => 'BSIS',
        'Bachelor of Science in Computer Engineering' => 'BSCpE',
        'Bachelor of Science in Electronics Engineering' => 'BSECE',
        'Bachelor of Science in Architecture' => 'BS Arch',
        'Bachelor of Science in Business Administration' => 'BSBA',
        'Bachelor of Science in Accountancy' => 'BSA',
        'Bachelor of Science in Tourism Management' => 'BSTM',
        'Bachelor of Science in Nursing' => 'BSN',
        'Bachelor of Science in Psychology' => 'BS Psych',
    ];

    return $abbreviations[$department] ?? $department;
}

function getStatusBadge($status) {
    $badges = [
        'submitted' => 'primary',
        'under_review' => 'warning',
        'approved' => 'success',
        'denied' => 'danger',
        'pending' => 'secondary',
        'processed' => 'success',
        'rejected' => 'danger',
        'active' => 'success',
        'inactive' => 'secondary'
    ];
    
    return $badges[$status] ?? 'secondary';
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Error reporting for development
$http_host = $_SERVER['HTTP_HOST'] ?? '';
if ($http_host === 'localhost' || strpos($http_host, '127.0.0.1') !== false) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
?>
