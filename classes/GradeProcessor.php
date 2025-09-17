<?php

class GradeProcessor {

    /**
     * @var PDO Database connection
     */
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Uploads a grade PDF file for a user in a given academic period.
     * Validates file type, size, and moves file to uploads folder.
     */
    public function uploadGradeFile($user_id, $academic_period_id, $file) {
        // Validate file upload success
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload failed.'];
        }

        // Check if file is PDF
        $file_type = mime_content_type($file['tmp_name']);
        if ($file_type !== 'application/pdf') {
            return ['success' => false, 'message' => 'Only PDF files are allowed.'];
        }

        // Limit file size to 5MB
        if ($file['size'] > 5 * 1024 * 1024) {
            return ['success' => false, 'message' => 'File must be less than 5MB.'];
        }

        // Create upload directory if not exists
        $upload_dir = __DIR__ . '/../uploads/grades/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Generate unique filename for new upload
        $filename = 'grade_' . $user_id . '_' . time() . '.pdf';
        $target_path = $upload_dir . $filename;

        // Check if a submission already exists for this user and period
        $checkStmt = $this->conn->prepare("
            SELECT * FROM grade_submissions 
            WHERE user_id = :user_id AND academic_period_id = :academic_period_id
        ");
        $checkStmt->execute([
            ':user_id' => $user_id,
            ':academic_period_id' => $academic_period_id
        ]);
        $existingSubmission = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingSubmission) {
            // No existing submission, proceed with insert

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $stmt = $this->conn->prepare("
                    INSERT INTO grade_submissions (user_id, academic_period_id, file_path, file_name, file_size)
                    VALUES (:user_id, :period_id, :file_path, :file_name, :file_size)
                ");

                $fileNameToSave = is_array($file['name']) ? 'unknown.pdf' : $file['name'];

                $stmt->execute([
                    ':user_id' => $user_id,
                    ':period_id' => $academic_period_id,
                    ':file_path' => $filename,
                    ':file_name' => $fileNameToSave,
                    ':file_size' => $file['size'],
                ]);

                return ['success' => true, 'message' => 'Grade report uploaded successfully.'];
            }

            return ['success' => false, 'message' => 'Failed to move uploaded file.'];

        } else {
            // Existing submission found — update record and replace file

            // Delete old file if exists
            $oldFilePath = $upload_dir . $existingSubmission['file_path'];
            if (file_exists($oldFilePath)) {
                @unlink($oldFilePath);
            }

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $stmt = $this->conn->prepare("
                    UPDATE grade_submissions
                    SET file_path = :file_path, file_name = :file_name, file_size = :file_size, uploaded_at = NOW()
                    WHERE id = :id
                ");

                $fileNameToSave = is_array($file['name']) ? 'unknown.pdf' : $file['name'];

                $stmt->execute([
                    ':file_path' => $filename,
                    ':file_name' => $fileNameToSave,
                    ':file_size' => $file['size'],
                    ':id' => $existingSubmission['id'],
                ]);

                return ['success' => true, 'message' => 'Existing grade report updated successfully.'];
            }

            return ['success' => false, 'message' => 'Failed to move uploaded file.'];
        }
    }


    /**
     * Retrieves all grades for a user in an academic period.
     */
    public function getStudentGrades($user_id, $academic_period_id) {
        $sql = "
            SELECT g.*
            FROM grades g
            JOIN grade_submissions gs ON g.submission_id = gs.id
            WHERE gs.user_id = :user_id
            AND gs.academic_period_id = :academic_period_id
            AND gs.status = 'processed'
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':academic_period_id', $academic_period_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculates GWA and updates/inserts result into gwa_calculations table.
     */
    public function calculateGWA($submissionId) {
        try {
            $db = $this->conn;

            // Fetch all grades for this submission
            $stmt = $db->prepare("SELECT * FROM grades WHERE submission_id = :submissionId");
            $stmt->execute([':submissionId' => $submissionId]);
            $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($grades)) {
                throw new Exception('No grades found for this submission');
            }

            $totalUnits = 0;
            $totalGradePoints = 0;
            $subjectsCount = count($grades);
            $failedSubjects = 0;
            $incompleteSubjects = 0;
            $hasGradeAbove25 = false;

            $validGradesCount = 0;
            foreach ($grades as $grade) {
                $units = floatval($grade['units']);
                $gradeValue = floatval($grade['grade']);
                $subjectName = strtoupper($grade['subject_name'] ?? '');

                // Skip ongoing subjects (grade = 0.00)
                if ($gradeValue == 0.00) {
                    continue;
                }

                // Skip NSTP courses
                if (strpos($subjectName, 'NSTP') !== false ||
                    strpos($subjectName, 'NATIONAL SERVICE TRAINING') !== false) {
                    continue;
                }

                // This is a valid grade for GWA calculation
                $validGradesCount++;

                if ($gradeValue > 2.5) {
                    $hasGradeAbove25 = true;
                }

                $totalUnits += $units;
                $totalGradePoints += ($gradeValue * $units);

                $remarks = strtoupper(trim($grade['remarks'] ?? ''));

                if ($gradeValue >= 4.0) {
                    $failedSubjects++;
                } elseif (in_array($remarks, ['INC', 'INCOMPLETE'])) {
                    $incompleteSubjects++;
                }
            }

            // Update subjects count to reflect only valid grades
            $subjectsCount = $validGradesCount;

            if ($totalUnits == 0) {
                throw new Exception('Total units cannot be zero');
            }

            $gwa = $totalGradePoints / $totalUnits;

            // Fetch submission details + year level
            $stmt = $db->prepare("
                SELECT gs.*, u.year_level 
                FROM grade_submissions gs
                JOIN users u ON gs.user_id = u.id
                WHERE gs.id = :submissionId
            ");
            $stmt->execute([':submissionId' => $submissionId]);
            $submission = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$submission) {
                throw new Exception('Submission not found');
            }

            $yearLevel = $submission['year_level'] ?? null;

            // Check if a GWA calculation record exists for this user and period
            $stmt = $db->prepare("
                SELECT * FROM gwa_calculations
                WHERE user_id = :user_id AND academic_period_id = :academic_period_id
            ");
            $stmt->execute([
                ':user_id' => $submission['user_id'],
                ':academic_period_id' => $submission['academic_period_id']
            ]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            $calculationData = [
                ':user_id' => $submission['user_id'],
                ':academic_period_id' => $submission['academic_period_id'],
                ':submission_id' => $submissionId,
                ':total_units' => $totalUnits,
                ':total_grade_points' => $totalGradePoints,
                ':gwa' => $gwa,
                ':subjects_count' => $subjectsCount,
                ':failed_subjects' => $failedSubjects,
                ':incomplete_subjects' => $incompleteSubjects,
                ':has_grade_above_25' => $hasGradeAbove25 ? 1 : 0,
                ':calculation_method' => 'weighted_average',
                ':calculated_at' => date('Y-m-d H:i:s')
            ];

            if ($existing) {
                // Update existing record
                $stmt = $db->prepare("
                    UPDATE gwa_calculations SET 
                        total_units = :total_units,
                        total_grade_points = :total_grade_points,
                        gwa = :gwa,
                        subjects_count = :subjects_count,
                        failed_subjects = :failed_subjects,
                        incomplete_subjects = :incomplete_subjects,
                        has_grade_above_25 = :has_grade_above_25,
                        recalculated_at = :calculated_at
                    WHERE user_id = :user_id AND academic_period_id = :academic_period_id
                ");
                $stmt->execute($calculationData);
                $calculationId = $existing['id'];
            } else {
                // Insert new record
                $stmt = $db->prepare("
                    INSERT INTO gwa_calculations (
                        user_id, academic_period_id, submission_id,
                        total_units, total_grade_points, gwa,
                        subjects_count, failed_subjects, incomplete_subjects,
                        has_grade_above_25, calculation_method, calculated_at
                    ) VALUES (
                        :user_id, :academic_period_id, :submission_id,
                        :total_units, :total_grade_points, :gwa,
                        :subjects_count, :failed_subjects, :incomplete_subjects,
                        :has_grade_above_25, :calculation_method, :calculated_at
                    )
                ");
                $stmt->execute($calculationData);
                $calculationId = $db->lastInsertId();
            }

            return [
                'success' => true,
                'gwa' => floor($gwa * 100) / 100, // Truncate to 2 decimal places
                'total_units' => $totalUnits,
                'total_grade_points' => round($totalGradePoints, 2),
                'subjects_count' => $subjectsCount,
                'failed_subjects' => $failedSubjects,
                'incomplete_subjects' => $incompleteSubjects,
                'has_grade_above_25' => $hasGradeAbove25,
                'year_level' => $yearLevel,
                'calculation_id' => $calculationId,
                'honor_eligibility' => self::checkHonorEligibility($gwa, $yearLevel, $hasGradeAbove25)
            ];

        } catch (Exception $e) {
            error_log("GWA Calculation Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Checks honor eligibility based on GWA, year level, and grade flags.
     */
    public static function checkHonorEligibility($gwa, $yearLevel = null, $hasGradeAbove25 = false) {
        if ($gwa <= 1.75 && !$hasGradeAbove25) {
            if ($yearLevel == 4) {
                if ($gwa >= 1.00 && $gwa <= 1.25) {
                    return 'summa_cum_laude';
                } elseif ($gwa >= 1.26 && $gwa <= 1.45) {
                    return 'magna_cum_laude';
                } elseif ($gwa >= 1.46 && $gwa <= 1.75) {
                    return 'cum_laude';
                }
            }
            return 'deans_list';
        }
        return null;
    }

    /**
     * Gets the student's GWA for a given academic period.
     */
    public function getStudentGWA($user_id, $period) {
        if (is_array($period)) {
            error_log("Warning: getStudentGWA called with array for period parameter");
            // Optionally throw exception or convert to string if appropriate:
            $period = implode(',', $period); // or just pick first element
        }

        if (is_array($user_id)) {
            error_log("Warning: getStudentGWA called with array for user_id parameter");
            $user_id = $user_id[0]; // or throw exception
        }

        // Use the stored GWA calculation instead of recalculating
        $query = "
            SELECT gwa, total_units, subjects_count, calculated_at
            FROM gwa_calculations
            WHERE user_id = :user_id
            AND academic_period_id = :period
            ORDER BY calculated_at DESC
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':period', $period);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

}
