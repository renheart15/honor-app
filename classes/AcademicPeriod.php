
<?php
class AcademicPeriod {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
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
        $stmt = $this->conn->prepare("SELECT * FROM academic_periods WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        try {
            // Deactivate all other periods if this one is set as active
            if ($data['is_active']) {
                $this->conn->exec("UPDATE academic_periods SET is_active = 0");
            }

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
            // Deactivate all other periods if this one is set as active
            if ($data['is_active']) {
                $this->conn->exec("UPDATE academic_periods SET is_active = 0");
            }

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
            // Deactivate all periods
            $this->conn->exec("UPDATE academic_periods SET is_active = 0");

            // Activate the selected period
            $stmt = $this->conn->prepare("UPDATE academic_periods SET is_active = 1 WHERE id = :id");
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>