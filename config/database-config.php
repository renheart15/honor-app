<?php
/**
 * Database Configuration for CTU Honor Application System
 * 
 * This file contains all database-related configurations including
 * connection settings, table definitions, and helper functions.
 */

// =====================================================
// DATABASE CONNECTION SETTINGS
// =====================================================

class DatabaseConfig {
    // Primary Database Settings
    const DB_HOST = 'localhost';
    const DB_NAME = 'honor_app';
    const DB_USER = 'root';
    const DB_PASS = '';
    const DB_CHARSET = 'utf8mb4';
    const DB_COLLATION = 'utf8mb4_unicode_ci';
    
    // Connection Options
    const DB_OPTIONS = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    
    // Connection Pool Settings
    const MAX_CONNECTIONS = 10;
    const CONNECTION_TIMEOUT = 30;
    const RETRY_ATTEMPTS = 3;
    
    // Table Names (for easy reference and maintenance)
    const TABLES = [
        'users' => 'users',
        'academic_periods' => 'academic_periods',
        'grade_submissions' => 'grade_submissions',
        'grades' => 'grades',
        'gwa_calculations' => 'gwa_calculations',
        'honor_applications' => 'honor_applications',
        'notifications' => 'notifications',
        'system_settings' => 'system_settings',
        'honor_rankings' => 'honor_rankings',
        'audit_logs' => 'audit_logs'
    ];
    
    // Database Views
    const VIEWS = [
        'student_gwa_summary' => 'student_gwa_summary',
        'honor_applications_detailed' => 'honor_applications_detailed'
    ];
    
    // Stored Procedures
    const PROCEDURES = [
        'calculate_gwa' => 'CalculateGWA',
        'generate_rankings' => 'GenerateHonorRankings'
    ];
}

// =====================================================
// DATABASE CONNECTION CLASS
// =====================================================

class Database {
    private static $instance = null;
    private $connection;
    private $statement;
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            DatabaseConfig::DB_HOST,
            DatabaseConfig::DB_NAME,
            DatabaseConfig::DB_CHARSET
        );
        
        try {
            $this->connection = new PDO(
                $dsn,
                DatabaseConfig::DB_USER,
                DatabaseConfig::DB_PASS,
                DatabaseConfig::DB_OPTIONS
            );
            
            // Set timezone
            $this->connection->exec("SET time_zone = '+08:00'");
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Prepared statement methods
    public function query($sql, $params = []) {
        $this->statement = $this->connection->prepare($sql);
        $this->statement->execute($params);
        return $this;
    }
    
    public function fetch() {
        return $this->statement->fetch();
    }
    
    public function fetchAll() {
        return $this->statement->fetchAll();
    }
    
    public function rowCount() {
        return $this->statement->rowCount();
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    // Transaction methods
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
    
    // Utility methods
    public function tableExists($tableName) {
        $sql = "SHOW TABLES LIKE :table";
        $this->query($sql, [':table' => $tableName]);
        return $this->rowCount() > 0;
    }
    
    public function getTableColumns($tableName) {
        $sql = "DESCRIBE " . $tableName;
        $this->query($sql);
        return $this->fetchAll();
    }
    
    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {}
}

// =====================================================
// TABLE SCHEMA DEFINITIONS
// =====================================================

class TableSchemas {
    
    public static function getUsersSchema() {
        return [
            'table_name' => 'users',
            'primary_key' => 'id',
            'columns' => [
                'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                'student_id' => ['type' => 'VARCHAR(20)', 'unique' => true, 'nullable' => true],
                'email' => ['type' => 'VARCHAR(100)', 'unique' => true, 'required' => true],
                'password' => ['type' => 'VARCHAR(255)', 'required' => true],
                'first_name' => ['type' => 'VARCHAR(50)', 'required' => true],
                'last_name' => ['type' => 'VARCHAR(50)', 'required' => true],
                'middle_name' => ['type' => 'VARCHAR(50)', 'nullable' => true],
                'role' => ['type' => 'ENUM', 'values' => ['student', 'adviser', 'chairperson'], 'default' => 'student'],
                'department' => ['type' => 'VARCHAR(100)', 'nullable' => true],
                'year_level' => ['type' => 'INT', 'nullable' => true],
                'section' => ['type' => 'VARCHAR(10)', 'nullable' => true],
                'contact_number' => ['type' => 'VARCHAR(15)', 'nullable' => true],
                'address' => ['type' => 'TEXT', 'nullable' => true],
                'status' => ['type' => 'ENUM', 'values' => ['active', 'inactive', 'suspended'], 'default' => 'active'],
                'email_verified' => ['type' => 'BOOLEAN', 'default' => false],
                'last_login' => ['type' => 'TIMESTAMP', 'nullable' => true],
                'created_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
                'updated_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP']
            ],
            'indexes' => [
                'idx_email' => ['columns' => ['email']],
                'idx_student_id' => ['columns' => ['student_id']],
                'idx_role' => ['columns' => ['role']],
                'idx_department' => ['columns' => ['department']],
                'idx_status' => ['columns' => ['status']]
            ]
        ];
    }
    
    public static function getGradesSchema() {
        return [
            'table_name' => 'grades',
            'primary_key' => 'id',
            'columns' => [
                'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                'submission_id' => ['type' => 'INT', 'required' => true, 'foreign_key' => 'grade_submissions.id'],
                'subject_code' => ['type' => 'VARCHAR(20)', 'required' => true],
                'subject_name' => ['type' => 'VARCHAR(150)', 'required' => true],
                'units' => ['type' => 'DECIMAL(3,1)', 'required' => true],
                'grade' => ['type' => 'DECIMAL(3,2)', 'required' => true],
                'letter_grade' => ['type' => 'VARCHAR(5)', 'nullable' => true],
                'remarks' => ['type' => 'VARCHAR(50)', 'nullable' => true],
                'semester_taken' => ['type' => 'VARCHAR(20)', 'nullable' => true],
                'adviser_name' => ['type' => 'VARCHAR(100)', 'nullable' => true],
                'created_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP']
            ],
            'indexes' => [
                'idx_submission' => ['columns' => ['submission_id']],
                'idx_subject_code' => ['columns' => ['subject_code']],
                'idx_grade' => ['columns' => ['grade']]
            ]
        ];
    }
    
    public static function getGWACalculationsSchema() {
        return [
            'table_name' => 'gwa_calculations',
            'primary_key' => 'id',
            'columns' => [
                'id' => ['type' => 'INT', 'auto_increment' => true, 'primary' => true],
                'user_id' => ['type' => 'INT', 'required' => true, 'foreign_key' => 'users.id'],
                'academic_period_id' => ['type' => 'INT', 'required' => true, 'foreign_key' => 'academic_periods.id'],
                'submission_id' => ['type' => 'INT', 'required' => true, 'foreign_key' => 'grade_submissions.id'],
                'total_units' => ['type' => 'DECIMAL(6,1)', 'required' => true],
                'total_grade_points' => ['type' => 'DECIMAL(10,2)', 'required' => true],
                'gwa' => ['type' => 'DECIMAL(4,3)', 'required' => true],
                'subjects_count' => ['type' => 'INT', 'required' => true],
                'failed_subjects' => ['type' => 'INT', 'default' => 0],
                'incomplete_subjects' => ['type' => 'INT', 'default' => 0],
                'calculation_method' => ['type' => 'VARCHAR(50)', 'default' => 'weighted_average'],
                'calculated_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
                'recalculated_at' => ['type' => 'TIMESTAMP', 'nullable' => true]
            ],
            'indexes' => [
                'unique_user_period_gwa' => ['columns' => ['user_id', 'academic_period_id'], 'unique' => true],
                'idx_gwa' => ['columns' => ['gwa']],
                'idx_calculated_at' => ['columns' => ['calculated_at']]
            ]
        ];
    }
    
    // Add more schema definitions as needed...
}

// =====================================================
// DATABASE VALIDATION RULES
// =====================================================

class DatabaseValidation {
    
    public static function validateUser($data) {
        $rules = [
            'email' => ['required', 'email', 'max:100'],
            'password' => ['required', 'min:8'],
            'first_name' => ['required', 'max:50'],
            'last_name' => ['required', 'max:50'],
            'role' => ['required', 'in:student,adviser,chairperson'],
            'student_id' => ['nullable', 'max:20', 'unique:users'],
            'department' => ['nullable', 'max:100'],
            'year_level' => ['nullable', 'integer', 'min:1', 'max:4'],
            'section' => ['nullable', 'max:10']
        ];
        
        return self::validate($data, $rules);
    }
    
    public static function validateGrade($data) {
        $rules = [
            'subject_code' => ['required', 'max:20'],
            'subject_name' => ['required', 'max:150'],
            'units' => ['required', 'numeric', 'min:0.5', 'max:6.0'],
            'grade' => ['required', 'numeric', 'min:1.00', 'max:5.00'],
            'letter_grade' => ['nullable', 'max:5'],
            'remarks' => ['nullable', 'max:50']
        ];
        
        return self::validate($data, $rules);
    }
    
    public static function validateGWAThresholds() {
        return [
            // Latin Honors (4th year only)
            'summa_cum_laude' => 1.25,      // 1.00 - 1.25
            'magna_cum_laude' => 1.45,      // 1.26 - 1.45  
            'cum_laude' => 1.75,            // 1.46 - 1.75
            // Dean's List (any year level)
            'deans_list' => 1.75            // 1.75 and below, no grade above 2.5
        ];
    }
    
    private static function validate($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            
            foreach ($fieldRules as $rule) {
                if (is_string($rule)) {
                    $ruleParts = explode(':', $rule);
                    $ruleName = $ruleParts[0];
                    $ruleValue = $ruleParts[1] ?? null;
                    
                    switch ($ruleName) {
                        case 'required':
                            if (empty($value)) {
                                $errors[$field][] = ucfirst($field) . ' is required';
                            }
                            break;
                        case 'email':
                            if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                $errors[$field][] = ucfirst($field) . ' must be a valid email';
                            }
                            break;
                        case 'min':
                            if (!empty($value) && (is_string($value) ? strlen($value) : $value) < $ruleValue) {
                                $errors[$field][] = ucfirst($field) . ' must be at least ' . $ruleValue;
                            }
                            break;
                        case 'max':
                            if (!empty($value) && (is_string($value) ? strlen($value) : $value) > $ruleValue) {
                                $errors[$field][] = ucfirst($field) . ' must not exceed ' . $ruleValue;
                            }
                            break;
                        case 'numeric':
                            if (!empty($value) && !is_numeric($value)) {
                                $errors[$field][] = ucfirst($field) . ' must be a number';
                            }
                            break;
                        case 'integer':
                            if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                                $errors[$field][] = ucfirst($field) . ' must be an integer';
                            }
                            break;
                    }
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

// =====================================================
// DATABASE QUERY BUILDER
// =====================================================

class QueryBuilder {
    private $db;
    private $table;
    private $select = '*';
    private $where = [];
    private $joins = [];
    private $orderBy = [];
    private $groupBy = [];
    private $having = [];
    private $limit;
    private $offset;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    public function table($table) {
        $this->table = $table;
        return $this;
    }
    
    public function select($columns) {
        $this->select = is_array($columns) ? implode(', ', $columns) : $columns;
        return $this;
    }
    
    public function where($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->where[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];
        
        return $this;
    }
    
    public function orWhere($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->where[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR'
        ];
        
        return $this;
    }
    
    public function join($table, $first, $operator, $second) {
        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];
        
        return $this;
    }
    
    public function leftJoin($table, $first, $operator, $second) {
        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];
        
        return $this;
    }
    
    public function orderBy($column, $direction = 'ASC') {
        $this->orderBy[] = $column . ' ' . strtoupper($direction);
        return $this;
    }
    
    public function groupBy($columns) {
        $this->groupBy = is_array($columns) ? $columns : [$columns];
        return $this;
    }
    
    public function limit($limit, $offset = 0) {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }
    
    public function get() {
        $sql = $this->buildSelectQuery();
        $params = $this->getWhereParams();
        
        $this->db->query($sql, $params);
        return $this->db->fetchAll();
    }
    
    public function first() {
        $this->limit(1);
        $results = $this->get();
        return !empty($results) ? $results[0] : null;
    }
    
    public function count() {
        $originalSelect = $this->select;
        $this->select = 'COUNT(*) as count';
        
        $sql = $this->buildSelectQuery();
        $params = $this->getWhereParams();
        
        $this->db->query($sql, $params);
        $result = $this->db->fetch();
        
        $this->select = $originalSelect;
        
        return $result['count'] ?? 0;
    }
    
    private function buildSelectQuery() {
        $sql = "SELECT {$this->select} FROM {$this->table}";
        
        // Add joins
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }
        
        // Add where clauses
        if (!empty($this->where)) {
            $sql .= " WHERE ";
            $whereClauses = [];
            
            foreach ($this->where as $index => $condition) {
                $placeholder = ":where_{$index}";
                $clause = "{$condition['column']} {$condition['operator']} {$placeholder}";
                
                if ($index > 0) {
                    $clause = "{$condition['boolean']} {$clause}";
                }
                
                $whereClauses[] = $clause;
            }
            
            $sql .= implode(' ', $whereClauses);
        }
        
        // Add group by
        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . implode(', ', $this->groupBy);
        }
        
        // Add having
        if (!empty($this->having)) {
            $sql .= " HAVING " . implode(' AND ', $this->having);
        }
        
        // Add order by
        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }
        
        // Add limit
        if ($this->limit) {
            $sql .= " LIMIT {$this->limit}";
            if ($this->offset) {
                $sql .= " OFFSET {$this->offset}";
            }
        }
        
        return $sql;
    }
    
    private function getWhereParams() {
        $params = [];
        
        foreach ($this->where as $index => $condition) {
            $params[":where_{$index}"] = $condition['value'];
        }
        
        return $params;
    }
    
    // Reset builder for reuse
    public function reset() {
        $this->table = null;
        $this->select = '*';
        $this->where = [];
        $this->joins = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->having = [];
        $this->limit = null;
        $this->offset = null;
        
        return $this;
    }
}

// =====================================================
// USAGE EXAMPLES AND HELPER FUNCTIONS
// =====================================================

class DatabaseHelpers {
    
    public static function getSystemSettings() {
        $db = Database::getInstance();
        $query = new QueryBuilder($db);
        
        $settings = $query->table('system_settings')
                         ->select('setting_key, setting_value, setting_type')
                         ->get();
        
        $result = [];
        foreach ($settings as $setting) {
            $value = $setting['setting_value'];
            
            // Convert based on type
            switch ($setting['setting_type']) {
                case 'number':
                    $value = is_numeric($value) ? (float)$value : $value;
                    break;
                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            
            $result[$setting['setting_key']] = $value;
        }
        
        return $result;
    }
    
    public static function getCurrentAcademicPeriod() {
        $db = Database::getInstance();
        $query = new QueryBuilder($db);
        
        return $query->table('academic_periods')
                    ->where('is_active', true)
                    ->first();
    }
    
    public static function getHonorThresholds() {
        $settings = self::getSystemSettings();
        
        return [
            'summa_cum_laude' => $settings['summa_cum_laude_threshold'] ?? 1.25,
            'magna_cum_laude' => $settings['magna_cum_laude_threshold'] ?? 1.45,
            'cum_laude' => $settings['cum_laude_threshold'] ?? 1.75,
            'deans_list' => $settings['deans_list_threshold'] ?? 1.75
        ];
    }
    
    public static function checkHonorEligibility($gwa, $yearLevel = null, $hasGradeAbove25 = false) {
        $thresholds = self::getHonorThresholds();
        
        // Dean's List eligibility (any year level)
        if ($gwa <= $thresholds['deans_list'] && !$hasGradeAbove25) {
            // Check for Latin Honors if 4th year
            if ($yearLevel == 4) {
                if ($gwa >= 1.00 && $gwa <= $thresholds['summa_cum_laude']) {
                    return 'summa_cum_laude';
                } elseif ($gwa >= 1.26 && $gwa <= $thresholds['magna_cum_laude']) {
                    return 'magna_cum_laude';
                } elseif ($gwa >= 1.46 && $gwa <= $thresholds['cum_laude']) {
                    return 'cum_laude';
                }
            }
            
            // Regular Dean's List
            return 'deans_list';
        }
        
        return null;
    }
    
    public static function formatGWA($gwa, $decimals = 2) {
        // Truncate to specified decimal places without rounding
        $multiplier = pow(10, $decimals);
        $truncated = floor((float)$gwa * $multiplier) / $multiplier;
        return number_format($truncated, $decimals);
    }
    
    public static function getGradePointEquivalent($letterGrade) {
        $gradePoints = [
            'A' => 1.00, 'A-' => 1.25,
            'B+' => 1.50, 'B' => 1.75, 'B-' => 2.00,
            'C+' => 2.25, 'C' => 2.50, 'C-' => 2.75,
            'D+' => 3.00, 'D' => 3.25, 'D-' => 3.50,
            'F' => 5.00, 'INC' => 0.00, 'W' => 0.00
        ];
        
        return $gradePoints[$letterGrade] ?? null;
    }
    
    public static function getLetterGradeEquivalent($gradePoint) {
        if ($gradePoint >= 1.00 && $gradePoint < 1.125) return 'A';
        if ($gradePoint >= 1.125 && $gradePoint < 1.375) return 'A-';
        if ($gradePoint >= 1.375 && $gradePoint < 1.625) return 'B+';
        if ($gradePoint >= 1.625 && $gradePoint < 1.875) return 'B';
        if ($gradePoint >= 1.875 && $gradePoint < 2.125) return 'B-';
        if ($gradePoint >= 2.125 && $gradePoint < 2.375) return 'C+';
        if ($gradePoint >= 2.375 && $gradePoint < 2.625) return 'C';
        if ($gradePoint >= 2.625 && $gradePoint < 2.875) return 'C-';
        if ($gradePoint >= 2.875 && $gradePoint < 3.125) return 'D+';
        if ($gradePoint >= 3.125 && $gradePoint < 3.375) return 'D';
        if ($gradePoint >= 3.375 && $gradePoint < 4.00) return 'D-';
        if ($gradePoint >= 4.00) return 'F';
        
        return 'N/A';
    }
}

// =====================================================
// INITIALIZE DATABASE CONNECTION
// =====================================================

// Auto-initialize database connection when this file is included
try {
    $database = Database::getInstance();
    $queryBuilder = new QueryBuilder($database);
    
    // Test connection
    $database->query("SELECT 1");
    
} catch (Exception $e) {
    error_log("Database initialization failed: " . $e->getMessage());
    // Handle gracefully in production
}

?>
