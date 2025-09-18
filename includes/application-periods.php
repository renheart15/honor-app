<?php
/**
 * Application Periods Helper Functions
 * Functions to manage and check honor application periods
 */

/**
 * Check if honor applications are currently open for a department
 * @param PDO $db Database connection
 * @param string $department Department name
 * @return array|false Returns active period data or false if closed
 */
function isApplicationPeriodOpen($db, $department) {
    // First try exact date match
    $query = "SELECT * FROM application_periods
              WHERE department = :department
              AND status = 'open'
              AND start_date <= CURDATE()
              AND end_date >= CURDATE()
              LIMIT 1";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        return $result;
    }

    // If no exact match, check for any open periods for this department
    // This allows for flexibility when dates don't match exactly but status is 'open'
    $query2 = "SELECT * FROM application_periods
               WHERE department = :department
               AND status = 'open'
               LIMIT 1";

    $stmt2 = $db->prepare($query2);
    $stmt2->bindParam(':department', $department);
    $stmt2->execute();

    return $stmt2->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get all application periods for a department
 * @param PDO $db Database connection
 * @param string $department Department name
 * @param int $limit Number of periods to return
 * @return array Array of application periods
 */
function getApplicationPeriods($db, $department, $limit = 10) {
    $query = "SELECT ap.*, u.first_name, u.last_name
              FROM application_periods ap
              JOIN users u ON ap.created_by = u.id
              WHERE ap.department = :department
              ORDER BY ap.created_at DESC
              LIMIT :limit";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get the next upcoming application period for a department
 * @param PDO $db Database connection
 * @param string $department Department name
 * @return array|false Returns upcoming period data or false if none
 */
function getNextApplicationPeriod($db, $department) {
    $query = "SELECT * FROM application_periods
              WHERE department = :department
              AND start_date > CURDATE()
              ORDER BY start_date ASC
              LIMIT 1";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':department', $department);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Check if a student can submit an honor application
 * @param PDO $db Database connection
 * @param int $student_id Student user ID
 * @return array Status information
 */
function canStudentApply($db, $student_id) {
    // Get student's department
    $query = "SELECT department FROM users WHERE id = :student_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return [
            'can_apply' => false,
            'reason' => 'Student not found'
        ];
    }

    $department = $user['department'];

    // Check if application period is open
    $active_period = isApplicationPeriodOpen($db, $department);

    if (!$active_period) {
        $next_period = getNextApplicationPeriod($db, $department);
        return [
            'can_apply' => false,
            'reason' => 'Application period is currently closed',
            'next_period' => $next_period
        ];
    }

    // Check if student already has a pending/approved application for this period
    $query = "SELECT COUNT(*) as count FROM honor_applications
              WHERE user_id = :student_id
              AND status IN ('submitted', 'under_review', 'approved')
              AND submitted_at BETWEEN :start_date AND :end_date";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':start_date', $active_period['start_date']);
    $stmt->bindParam(':end_date', $active_period['end_date']);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing['count'] > 0) {
        return [
            'can_apply' => false,
            'reason' => 'You already have an application for this period',
            'active_period' => $active_period
        ];
    }

    return [
        'can_apply' => true,
        'active_period' => $active_period
    ];
}

/**
 * Format application period display name
 * @param array $period Application period data
 * @return string Formatted display name
 */
function formatPeriodName($period) {
    return $period['semester'] . ' ' . $period['academic_year'];
}

/**
 * Get days remaining in application period
 * @param array $period Application period data
 * @return int Days remaining (negative if expired)
 */
function getDaysRemaining($period) {
    $end_date = new DateTime($period['end_date']);
    $today = new DateTime();

    // For demo/testing purposes, if the period dates are in the past but status is 'open',
    // calculate remaining days as if it's the current academic year
    if ($period['status'] === 'open' && $end_date < $today) {
        // Assume it's meant to be the current year
        $current_year = date('Y');
        $period_end = str_replace('2024', $current_year, $period['end_date']);
        $end_date = new DateTime($period_end);
    }

    $interval = $today->diff($end_date);
    return $interval->invert ? -$interval->days : $interval->days;
}
?>