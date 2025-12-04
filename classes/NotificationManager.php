<?php
class NotificationManager {
    private $conn;
    private $table_name = "notifications";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function createNotification($user_id, $title, $message, $type = 'info', $category = 'general') {
        $query = "INSERT INTO " . $this->table_name . "
                  SET user_id=:user_id, title=:title, message=:message,
                      type=:type, category=:category";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $title = htmlspecialchars(strip_tags($title));
        $message = htmlspecialchars(strip_tags($message));
        $type = htmlspecialchars(strip_tags($type));
        $category = htmlspecialchars(strip_tags($category));

        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":message", $message);
        $stmt->bindParam(":type", $type);
        $stmt->bindParam(":category", $category);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Notification creation error: " . $e->getMessage());
            return false;
        }
    }

    public function getUserNotifications($user_id, $limit = 10) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE user_id = :user_id
                  ORDER BY created_at DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnreadCount($user_id) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . "
                  WHERE user_id = :user_id AND is_read = 0";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    public function markAsRead($notification_id, $user_id) {
        $query = "UPDATE " . $this->table_name . "
                  SET is_read = 1, read_at = NOW()
                  WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $notification_id);
        $stmt->bindParam(":user_id", $user_id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Mark as read error: " . $e->getMessage());
            return false;
        }
    }

    public function markAllAsRead($user_id) {
        $query = "UPDATE " . $this->table_name . "
                  SET is_read = 1, read_at = NOW()
                  WHERE user_id = :user_id AND is_read = 0";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Mark all as read error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteNotification($notification_id, $user_id) {
        $query = "DELETE FROM " . $this->table_name . "
                  WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $notification_id);
        $stmt->bindParam(":user_id", $user_id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Delete notification error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteAllNotifications($user_id) {
        $query = "DELETE FROM " . $this->table_name . "
                  WHERE user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Delete all notifications error: " . $e->getMessage());
            return false;
        }
    }

    public function sendSystemNotification($title, $message, $type = 'system') {
        // Send notification to all active users
        $query = "INSERT INTO " . $this->table_name . " (user_id, title, message, type, category)
                  SELECT id, :title, :message, :type, 'system'
                  FROM users WHERE status = 'active'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":message", $message);
        $stmt->bindParam(":type", $type);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("System notification error: " . $e->getMessage());
            return false;
        }
    }

    // Student Notifications
    public function notifyGradeProcessed($user_id, $status, $rejection_reason = null) {
        if ($status === 'processed') {
            $title = "Grade Report Approved";
            $message = "Your grade report has been approved and processed successfully.";
            $type = 'success';
        } else {
            $title = "Grade Report Rejected";
            $message = "Your grade report was rejected. Reason: " . ($rejection_reason ?? 'Not specified');
            $type = 'error';
        }
        return $this->createNotification($user_id, $title, $message, $type, 'gwa_calculation');
    }

    public function notifyApplicationStatus($user_id, $status, $application_type) {
        $type_label = ucfirst(str_replace('_', ' ', $application_type));

        if ($status === 'approved') {
            $title = "Application Approved";
            $message = "Your {$type_label} application has been approved!";
            $type = 'success';
        } else {
            $title = "Application Rejected";
            $message = "Your {$type_label} application was rejected.";
            $type = 'error';
        }
        return $this->createNotification($user_id, $title, $message, $type, 'honor_application');
    }

    public function notifyAcademicPeriodOpened($semester, $school_year, $end_date) {
        $title = "Academic Period Opened";
        $message = "New academic period is now open: {$semester} Semester SY {$school_year}. Deadline: " . date('M d, Y', strtotime($end_date));

        // Notify all students
        $query = "INSERT INTO " . $this->table_name . " (user_id, title, message, type, category)
                  SELECT id, :title, :message, 'info', 'system_update'
                  FROM users WHERE role = 'student' AND status = 'active'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":message", $message);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Academic period notification error: " . $e->getMessage());
            return false;
        }
    }

    public function notifyAcademicPeriodExpired($semester, $school_year) {
        $title = "Academic Period Expired";
        $message = "The academic period {$semester} Semester SY {$school_year} has expired.";

        // Notify all students
        $query = "INSERT INTO " . $this->table_name . " (user_id, title, message, type, category)
                  SELECT id, :title, :message, 'warning', 'system_update'
                  FROM users WHERE role = 'student' AND status = 'active'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":message", $message);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Academic period expiry notification error: " . $e->getMessage());
            return false;
        }
    }

    // Adviser Notifications
    public function notifyAdviserGradeSubmission($adviser_id, $student_name, $submission_id) {
        $title = "New Grade Submission";
        $message = "{$student_name} has submitted their grade report for review.";
        return $this->createNotification($adviser_id, $title, $message, 'info', 'grade_submission');
    }

    public function notifyAdviserApplicationSubmission($adviser_id, $student_name, $application_type) {
        $type_label = ucfirst(str_replace('_', ' ', $application_type));
        $title = "New Application Submission";
        $message = "{$student_name} has submitted a {$type_label} application for review.";
        return $this->createNotification($adviser_id, $title, $message, 'info', 'application_submission');
    }

    public function notifyAdviserAcademicPeriod($semester, $school_year, $end_date, $is_expired = false) {
        if ($is_expired) {
            $title = "Academic Period Expired";
            $message = "The academic period {$semester} Semester SY {$school_year} has expired.";
            $type = 'warning';
        } else {
            $title = "Academic Period Opened";
            $message = "New academic period is now open: {$semester} Semester SY {$school_year}. Deadline: " . date('M d, Y', strtotime($end_date));
            $type = 'info';
        }

        // Notify all advisers
        $query = "INSERT INTO " . $this->table_name . " (user_id, title, message, type, category)
                  SELECT id, :title, :message, :type, 'system_update'
                  FROM users WHERE role = 'adviser' AND status = 'active'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":message", $message);
        $stmt->bindParam(":type", $type);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Adviser academic period notification error: " . $e->getMessage());
            return false;
        }
    }

    // Chairperson Notifications
    public function notifyChairpersonAcademicPeriod($semester, $school_year, $end_date, $is_expired = false) {
        if ($is_expired) {
            $title = "Academic Period Expired";
            $message = "The academic period {$semester} Semester SY {$school_year} has expired. Please review submissions and applications.";
            $type = 'warning';
        } else {
            $title = "Academic Period Opened";
            $message = "New academic period is now open: {$semester} Semester SY {$school_year}. Deadline: " . date('M d, Y', strtotime($end_date));
            $type = 'info';
        }

        // Notify all chairpersons
        $query = "INSERT INTO " . $this->table_name . " (user_id, title, message, type, category)
                  SELECT id, :title, :message, :type, 'system_update'
                  FROM users WHERE role = 'chairperson' AND status = 'active'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":message", $message);
        $stmt->bindParam(":type", $type);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Chairperson academic period notification error: " . $e->getMessage());
            return false;
        }
    }

    public function notifyChairpersonNewAdviser($adviser_name, $department) {
        $title = "New Adviser Registered";
        $message = "A new adviser, {$adviser_name}, has registered in the {$department} department and requires section assignment.";
        $type = 'info';

        // Notify chairpersons in the same department
        $query = "INSERT INTO " . $this->table_name . " (user_id, title, message, type, category)
                  SELECT id, :title, :message, :type, 'adviser_management'
                  FROM users WHERE role = 'chairperson' AND department = :department AND status = 'active'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":message", $message);
        $stmt->bindParam(":type", $type);
        $stmt->bindParam(":department", $department);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Chairperson new adviser notification error: " . $e->getMessage());
            return false;
        }
    }

    public function notifyChairpersonApplicationReview($application_count, $department) {
        $title = "Applications Pending Review";
        $message = "There are {$application_count} honor applications pending review in the {$department} department.";
        $type = 'warning';

        // Notify chairpersons in the same department
        $query = "INSERT INTO " . $this->table_name . " (user_id, title, message, type, category)
                  SELECT id, :title, :message, :type, 'application_review'
                  FROM users WHERE role = 'chairperson' AND department = :department AND status = 'active'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":message", $message);
        $stmt->bindParam(":type", $type);
        $stmt->bindParam(":department", $department);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Chairperson application review notification error: " . $e->getMessage());
            return false;
        }
    }

    public function notifyChairpersonRankingGenerated($period_name, $department) {
        $title = "Honor Rankings Generated";
        $message = "Honor rankings have been generated for {$period_name} in the {$department} department. Please review and finalize.";
        $type = 'success';

        // Notify chairpersons in the same department
        $query = "INSERT INTO " . $this->table_name . " (user_id, title, message, type, category)
                  SELECT id, :title, :message, :type, 'ranking_finalization'
                  FROM users WHERE role = 'chairperson' AND department = :department AND status = 'active'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":message", $message);
        $stmt->bindParam(":type", $type);
        $stmt->bindParam(":department", $department);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Chairperson ranking generated notification error: " . $e->getMessage());
            return false;
        }
    }
}
