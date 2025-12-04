
<?php
class AcademicPeriod {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        // Auto-deactivate any expired active periods
        $this->deactivateExpiredPeriods();

        $stmt = $this->conn->query("SELECT * FROM academic_periods ORDER BY school_year DESC, semester DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM academic_periods WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getActivePeriod() {
        // First, auto-deactivate any expired active periods
        $this->deactivateExpiredPeriods();

        $stmt = $this->conn->prepare("SELECT * FROM academic_periods WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deactivateExpiredPeriods() {
        try {
            // Get all active periods that have expired before sending notifications
            $selectStmt = $this->conn->prepare("SELECT * FROM academic_periods WHERE is_active = 1 AND end_date < CURDATE()");
            $selectStmt->execute();
            $expiredPeriods = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

            // Deactivate any periods where end_date is before today
            $stmt = $this->conn->prepare("UPDATE academic_periods SET is_active = 0 WHERE is_active = 1 AND end_date < CURDATE()");
            $result = $stmt->execute();

            // Send notifications for each expired period
            if ($result && !empty($expiredPeriods)) {
                require_once 'NotificationManager.php';
                $notificationManager = new NotificationManager($this->conn);

                foreach ($expiredPeriods as $period) {
                    $semester = $period['semester'] === '1st' ? '1st' : ($period['semester'] === '2nd' ? '2nd' : ucfirst($period['semester']));

                    // Notify students
                    $notificationManager->notifyAcademicPeriodExpired($semester, $period['school_year']);

                    // Notify advisers
                    $notificationManager->notifyAdviserAcademicPeriod($semester, $period['school_year'], $period['end_date'], true);

                    // Notify chairpersons
                    $notificationManager->notifyChairpersonAcademicPeriod($semester, $period['school_year'], $period['end_date'], true);
                }
            }

            return $result;
        } catch (PDOException $e) {
            error_log('Failed to deactivate expired periods: ' . $e->getMessage());
            return false;
        }
    }

    public function isExpired($period) {
        if (!$period || !isset($period['end_date'])) {
            return false;
        }
        return strtotime($period['end_date']) < strtotime('today');
    }

    public function create($data) {
        try {
            // Generate period name from semester and school year
            $semester_display = $data['semester'] === '1st' ? 'First Semester' :
                              ($data['semester'] === '2nd' ? 'Second Semester' : 'Summer');
            $period_name = $semester_display . ' ' . $data['school_year'];

            $stmt = $this->conn->prepare("
                INSERT INTO academic_periods (period_name, semester, school_year, start_date, end_date, is_active)
                VALUES (:period_name, :semester, :school_year, :start_date, :end_date, :is_active)
            ");

            $result = $stmt->execute([
                ':period_name' => $period_name,
                ':semester' => $data['semester'],
                ':school_year' => $data['school_year'],
                ':start_date' => $data['start_date'],
                ':end_date' => $data['end_date'],
                ':is_active' => $data['is_active'] ? 1 : 0
            ]);

            if (!$result) {
                error_log('AcademicPeriod create failed: ' . json_encode($stmt->errorInfo()));
            }

            return $result;
        } catch (PDOException $e) {
            error_log('AcademicPeriod create exception: ' . $e->getMessage());
            return false;
        }
    }

    public function update($id, $data) {
        try {
            // Generate period name from semester and school year
            $semester_display = $data['semester'] === '1st' ? 'First Semester' :
                              ($data['semester'] === '2nd' ? 'Second Semester' : 'Summer');
            $period_name = $semester_display . ' ' . $data['school_year'];

            $stmt = $this->conn->prepare("
                UPDATE academic_periods SET
                    period_name = :period_name,
                    semester = :semester,
                    school_year = :school_year,
                    start_date = :start_date,
                    end_date = :end_date,
                    is_active = :is_active,
                    updated_at = NOW()
                WHERE id = :id
            ");

            return $stmt->execute([
                ':id' => $id,
                ':period_name' => $period_name,
                ':semester' => $data['semester'],
                ':school_year' => $data['school_year'],
                ':start_date' => $data['start_date'],
                ':end_date' => $data['end_date'],
                ':is_active' => $data['is_active'] ? 1 : 0
            ]);
        } catch (PDOException $e) {
            error_log('AcademicPeriod update exception: ' . $e->getMessage());
            return false;
        }
    }

    public function delete($id) {
        try {
            // Check if there are any applications or submissions using this period
            $checkStmt = $this->conn->prepare("
                SELECT
                    (SELECT COUNT(*) FROM honor_applications WHERE academic_period_id = :id) +
                    (SELECT COUNT(*) FROM grade_submissions WHERE academic_period_id = :id) as usage_count
            ");
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($result['usage_count'] > 0) {
                return ['success' => false, 'message' => 'Cannot delete period that has associated applications or submissions'];
            }

            $stmt = $this->conn->prepare("DELETE FROM academic_periods WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $success = $stmt->execute();

            return ['success' => $success, 'message' => $success ? 'Period deleted successfully' : 'Failed to delete period'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function setActive($id) {
        try {
            // Simply activate the selected period (allow multiple active periods)
            $stmt = $this->conn->prepare("UPDATE academic_periods SET is_active = 1, updated_at = NOW() WHERE id = :id");
            $stmt->bindParam(':id', $id);

            if ($stmt->execute()) {
                // Get period details for notification
                $period = $this->getById($id);
                if ($period) {
                    $semester = $period['semester'] === '1st' ? '1st' : ($period['semester'] === '2nd' ? '2nd' : ucfirst($period['semester']));

                    // Send notification to all students, advisers, and chairpersons
                    require_once 'NotificationManager.php';
                    $notificationManager = new NotificationManager($this->conn);
                    $notificationManager->notifyAcademicPeriodOpened($semester, $period['school_year'], $period['end_date']);
                    $notificationManager->notifyAdviserAcademicPeriod($semester, $period['school_year'], $period['end_date'], false);
                    $notificationManager->notifyChairpersonAcademicPeriod($semester, $period['school_year'], $period['end_date'], false);
                }
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log('AcademicPeriod setActive exception: ' . $e->getMessage());
            return false;
        }
    }

    public function setActiveWithDates($id, $start_date, $end_date) {
        try {
            // Validate dates
            if (empty($start_date) || empty($end_date)) {
                error_log('AcademicPeriod setActiveWithDates: Missing dates');
                return false;
            }

            // Validate end date is after start date
            if (strtotime($end_date) <= strtotime($start_date)) {
                error_log('AcademicPeriod setActiveWithDates: End date must be after start date');
                return false;
            }

            // Update and activate the selected period with new dates (allow multiple active periods)
            $stmt = $this->conn->prepare("
                UPDATE academic_periods
                SET is_active = 1,
                    start_date = :start_date,
                    end_date = :end_date,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);

            if ($stmt->execute()) {
                // Get period details for notification
                $period = $this->getById($id);
                if ($period) {
                    $semester = $period['semester'] === '1st' ? '1st' : ($period['semester'] === '2nd' ? '2nd' : ucfirst($period['semester']));

                    // Send notification to all students, advisers, and chairpersons
                    require_once 'NotificationManager.php';
                    $notificationManager = new NotificationManager($this->conn);
                    $notificationManager->notifyAcademicPeriodOpened($semester, $period['school_year'], $end_date);
                    $notificationManager->notifyAdviserAcademicPeriod($semester, $period['school_year'], $end_date, false);
                    $notificationManager->notifyChairpersonAcademicPeriod($semester, $period['school_year'], $end_date, false);
                }
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log('AcademicPeriod setActiveWithDates exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ensures only one active period exists at most
     * If multiple active periods found, deactivates all of them
     * @return array Status and message
     */
    public function enforceOneActivePeriod() {
        try {
            $stmt = $this->conn->query("SELECT id, semester, school_year FROM academic_periods WHERE is_active = 1");
            $activePeriods = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $count = count($activePeriods);

            if ($count === 0) {
                return ['success' => true, 'message' => 'No active period found', 'count' => 0];
            } elseif ($count === 1) {
                return ['success' => true, 'message' => 'Single active period verified', 'count' => 1];
            } else {
                // Multiple active periods detected - deactivate all
                $this->conn->exec("UPDATE academic_periods SET is_active = 0");
                error_log("Multiple active periods detected and deactivated: " . json_encode($activePeriods));
                return [
                    'success' => false,
                    'message' => "Multiple active periods detected and deactivated. Please manually activate the correct period.",
                    'count' => $count,
                    'periods' => $activePeriods
                ];
            }
        } catch (PDOException $e) {
            error_log('enforceOneActivePeriod exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
?>
