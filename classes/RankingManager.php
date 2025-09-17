<?php
/**
 * Honor Ranking Management System
 * Handles automated ranking generation and management
 */

class RankingManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Generate comprehensive honor rankings for a department
     */
    public function generateRankings($academic_period_id, $department) {
        try {
            $this->db->beginTransaction();
            
            // Call the enhanced stored procedure
            $stmt = $this->db->prepare("CALL GenerateHonorRankings(:period_id, :department)");
            $stmt->bindParam(':period_id', $academic_period_id);
            $stmt->bindParam(':department', $department);
            $stmt->execute();
            
            // Update percentiles for all rankings
            $this->updatePercentiles($academic_period_id, $department);
            
            // Update generated_by field
            $this->markGeneratedBy($academic_period_id, $department, $_SESSION['user_id']);
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Rankings generated successfully'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Error generating rankings: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update percentile rankings for all students
     */
    private function updatePercentiles($academic_period_id, $department) {
        $query = "
            UPDATE honor_rankings hr1 
            SET percentile = (
                SELECT (
                    (SELECT COUNT(*) FROM honor_rankings hr2 
                     WHERE hr2.academic_period_id = hr1.academic_period_id 
                     AND hr2.department = hr1.department 
                     AND hr2.ranking_type = hr1.ranking_type
                     AND COALESCE(hr2.year_level, 0) = COALESCE(hr1.year_level, 0)
                     AND COALESCE(hr2.section, '') = COALESCE(hr1.section, '')
                     AND hr2.gwa > hr1.gwa) / 
                    (SELECT COUNT(*) FROM honor_rankings hr3 
                     WHERE hr3.academic_period_id = hr1.academic_period_id 
                     AND hr3.department = hr1.department 
                     AND hr3.ranking_type = hr1.ranking_type
                     AND COALESCE(hr3.year_level, 0) = COALESCE(hr1.year_level, 0)
                     AND COALESCE(hr3.section, '') = COALESCE(hr1.section, ''))
                ) * 100
            ) 
            WHERE hr1.academic_period_id = :period_id 
            AND hr1.department = :department";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':period_id', $academic_period_id);
        $stmt->bindParam(':department', $department);
        $stmt->execute();
    }
    
    /**
     * Mark who generated the rankings
     */
    private function markGeneratedBy($academic_period_id, $department, $user_id) {
        $query = "
            UPDATE honor_rankings 
            SET generated_by = :user_id 
            WHERE academic_period_id = :period_id 
            AND department = :department 
            AND generated_by IS NULL";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':period_id', $academic_period_id);
        $stmt->bindParam(':department', $department);
        $stmt->execute();
    }
    
    /**
     * Get ranking statistics for a department
     */
    public function getRankingStats($academic_period_id, $department) {
        $query = "
            SELECT 
                ranking_type,
                COUNT(*) as total_students,
                MIN(gwa) as best_gwa,
                MAX(gwa) as lowest_gwa,
                AVG(gwa) as average_gwa,
                COUNT(DISTINCT CONCAT(COALESCE(year_level, 'ALL'), '-', COALESCE(section, 'ALL'))) as sections_count
            FROM honor_rankings hr
            WHERE hr.department = :department
            AND hr.academic_period_id = :period_id
            GROUP BY ranking_type
            ORDER BY ranking_type";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':department', $department);
        $stmt->bindParam(':period_id', $academic_period_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get detailed rankings with filters
     */
    public function getRankings($academic_period_id, $department, $filters = []) {
        $where_conditions = ["hr.department = :department", "hr.academic_period_id = :period_id"];
        $params = [':department' => $department, ':period_id' => $academic_period_id];
        
        // Apply filters
        if (!empty($filters['ranking_type']) && $filters['ranking_type'] !== 'all') {
            $where_conditions[] = "hr.ranking_type = :ranking_type";
            $params[':ranking_type'] = $filters['ranking_type'];
        }
        
        if (!empty($filters['year_level']) && $filters['year_level'] !== 'all') {
            $where_conditions[] = "u.year_level = :year_level";
            $params[':year_level'] = $filters['year_level'];
        }
        
        if (!empty($filters['section']) && $filters['section'] !== 'all') {
            $where_conditions[] = "u.section = :section";
            $params[':section'] = $filters['section'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "
            SELECT 
                hr.*,
                u.student_id,
                u.first_name,
                u.last_name,
                u.year_level,
                u.section,
                ap.period_name,
                ap.school_year,
                ap.semester
            FROM honor_rankings hr
            JOIN users u ON hr.user_id = u.id
            JOIN academic_periods ap ON hr.academic_period_id = ap.id
            WHERE {$where_clause}
            ORDER BY hr.ranking_type, u.year_level, u.section, hr.rank_position
        ";
        
        $stmt = $this->db->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindParam($param, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get student's individual rankings
     */
    public function getStudentRankings($user_id, $academic_period_id) {
        $query = "
            SELECT 
                hr.*,
                u.student_id,
                u.first_name,
                u.last_name,
                u.department,
                u.year_level,
                u.section,
                ap.period_name,
                ap.school_year,
                ap.semester
            FROM honor_rankings hr
            JOIN users u ON hr.user_id = u.id
            JOIN academic_periods ap ON hr.academic_period_id = ap.id
            WHERE hr.user_id = :user_id
            AND hr.academic_period_id = :period_id
            ORDER BY hr.ranking_type, hr.year_level, hr.section
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':period_id', $academic_period_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if student is eligible for honor rankings
     */
    public function checkHonorEligibility($user_id, $academic_period_id) {
        $query = "
            SELECT 
                gwa.gwa,
                gwa.failed_subjects,
                gwa.incomplete_subjects,
                COALESCE(grade_check.has_high_grades, 0) as has_grade_above_25,
                CASE 
                    WHEN gwa.gwa <= 1.25 AND gwa.failed_subjects = 0 AND gwa.incomplete_subjects = 0 AND COALESCE(grade_check.has_high_grades, 0) = 0 THEN 'presidents_list'
                    WHEN gwa.gwa <= 1.75 AND gwa.failed_subjects = 0 AND gwa.incomplete_subjects = 0 AND COALESCE(grade_check.has_high_grades, 0) = 0 THEN 'deans_list'
                    ELSE 'not_eligible'
                END as eligibility_level
            FROM gwa_calculations gwa
            LEFT JOIN (
                SELECT gs.user_id, IF(COUNT(*) > 0, 1, 0) as has_high_grades
                FROM grade_submissions gs
                JOIN grades g ON gs.id = g.submission_id
                WHERE gs.academic_period_id = :period_id
                AND g.grade > 2.5
                GROUP BY gs.user_id
            ) grade_check ON gwa.user_id = grade_check.user_id
            WHERE gwa.user_id = :user_id
            AND gwa.academic_period_id = :period_id
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':period_id', $academic_period_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update has_grade_above_25 flags for all GWA calculations
     */
    public function updateGradeFlags() {
        $query = "
            UPDATE gwa_calculations gwa
            JOIN grade_submissions gs ON gwa.submission_id = gs.id
            SET gwa.has_grade_above_25 = (
                SELECT COUNT(*) > 0
                FROM grades g
                WHERE g.submission_id = gs.id
                AND g.grade > 2.5
            )
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->rowCount();
    }
    
    /**
     * Get available filter options for rankings
     */
    public function getFilterOptions($department) {
        $year_query = "
            SELECT DISTINCT year_level 
            FROM users 
            WHERE department = :department 
            AND role = 'student' 
            AND year_level IS NOT NULL
            ORDER BY year_level";
        
        $section_query = "
            SELECT DISTINCT section 
            FROM users 
            WHERE department = :department 
            AND role = 'student' 
            AND section IS NOT NULL
            ORDER BY section";
        
        $stmt = $this->db->prepare($year_query);
        $stmt->bindParam(':department', $department);
        $stmt->execute();
        $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $this->db->prepare($section_query);
        $stmt->bindParam(':department', $department);
        $stmt->execute();
        $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return [
            'year_levels' => $years,
            'sections' => $sections
        ];
    }
    
    /**
     * Export rankings to CSV format
     */
    public function exportRankingsCSV($academic_period_id, $department, $filters = []) {
        $rankings = $this->getRankings($academic_period_id, $department, $filters);
        
        $csv_data = "Rank,Student ID,Name,Year Level,Section,Honor Type,GWA,Percentile,Generated Date\n";
        
        foreach ($rankings as $ranking) {
            $csv_data .= sprintf(
                "%d,%s,%s %s,%d,%s,%s,%.3f,%.1f%%,%s\n",
                $ranking['rank_position'],
                $ranking['student_id'],
                $ranking['first_name'],
                $ranking['last_name'],
                $ranking['year_level'],
                $ranking['section'],
                ucfirst(str_replace('_', ' ', $ranking['ranking_type'])),
                $ranking['gwa'],
                $ranking['percentile'] ?? 0,
                date('Y-m-d', strtotime($ranking['generated_at']))
            );
        }
        
        return $csv_data;
    }
    
    /**
     * Get ranking trends over multiple periods
     */
    public function getRankingTrends($department, $limit = 5) {
        $query = "
            SELECT 
                ap.period_name,
                ap.school_year,
                ap.semester,
                hr.ranking_type,
                COUNT(*) as total_students,
                MIN(hr.gwa) as best_gwa,
                AVG(hr.gwa) as average_gwa
            FROM honor_rankings hr
            JOIN academic_periods ap ON hr.academic_period_id = ap.id
            WHERE hr.department = :department
            GROUP BY ap.id, hr.ranking_type
            ORDER BY ap.start_date DESC, hr.ranking_type
            LIMIT :limit
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':department', $department);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get top performers across all time
     */
    public function getTopPerformers($department, $limit = 10) {
        $query = "
            SELECT 
                u.student_id,
                u.first_name,
                u.last_name,
                u.year_level,
                u.section,
                hr.ranking_type,
                hr.gwa,
                hr.rank_position,
                ap.period_name,
                ap.school_year
            FROM honor_rankings hr
            JOIN users u ON hr.user_id = u.id
            JOIN academic_periods ap ON hr.academic_period_id = ap.id
            WHERE hr.department = :department
            AND hr.rank_position = 1
            ORDER BY hr.gwa ASC, ap.start_date DESC
            LIMIT :limit
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':department', $department);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}