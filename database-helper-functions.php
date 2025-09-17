<?php
/**
 * Database Helper Functions for CTU Honor Application System
 * 
 * This file contains utility functions for working with database tables and columns
 */

class DatabaseTableHelper {
    
    /**
     * Get all table names in the database
     */
    public static function getAllTables() {
        return [
            'users',
            'academic_periods', 
            'grade_submissions',
            'grades',
            'gwa_calculations',
            'honor_applications',
            'notifications',
            'system_settings',
            'honor_rankings',
            'audit_logs'
        ];
    }
    
    /**
     * Get column information for a specific table
     */
    public static function getTableColumns($tableName) {
        $columns = [];
        
        switch ($tableName) {
            case 'users':
                $columns = [
                    'id' => ['type' => 'INT', 'key' => 'PRIMARY', 'auto_increment' => true],
                    'student_id' => ['type' => 'VARCHAR(20)', 'key' => 'UNIQUE', 'nullable' => true],
                    'email' => ['type' => 'VARCHAR(100)', 'key' => 'UNIQUE', 'required' => true],
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
                ];
                break;
                
            case 'academic_periods':
                $columns = [
                    'id' => ['type' => 'INT', 'key' => 'PRIMARY', 'auto_increment' => true],
                    'period_name' => ['type' => 'VARCHAR(50)', 'required' => true],
                    'school_year' => ['type' => 'VARCHAR(20)', 'required' => true],
                    'semester' => ['type' => 'ENUM', 'values' => ['1st', '2nd', 'summer'], 'required' => true],
                    'start_date' => ['type' => 'DATE', 'required' => true],
                    'end_date' => ['type' => 'DATE', 'required' => true],
                    'is_active' => ['type' => 'BOOLEAN', 'default' => false],
                    'registration_deadline' => ['type' => 'DATE', 'nullable' => true],
                    'application_deadline' => ['type' => 'DATE', 'nullable' => true],
                    'created_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
                    'updated_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP']
                ];
                break;
                
            case 'grade_submissions':
                $columns = [
                    'id' => ['type' => 'INT', 'key' => 'PRIMARY', 'auto_increment' => true],
                    'user_id' => ['type' => 'INT', 'required' => true, 'foreign_key' => 'users.id'],
                    'academic_period_id' => ['type' => 'INT', 'required' => true, 'foreign_key' => 'academic_periods.id'],
                    'file_path' => ['type' => 'VARCHAR(500)', 'required' => true],
                    'file_name' => ['type' => 'VARCHAR(255)', 'required' => true],
                    'file_size' => ['type' => 'INT', 'required' => true],
                    'mime_type' => ['type' => 'VARCHAR(100)', 'required' => true, 'default' => 'application/pdf'],
                    'upload_date' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
                    'status' => ['type' => 'ENUM', 'values' => ['pending', 'processing', 'processed', 'rejected', 'failed'], 'default' => 'pending'],
                    'processed_at' => ['type' => 'TIMESTAMP', 'nullable' => true],
                    'processed_by' => ['type' => 'INT', 'nullable' => true, 'foreign_key' => 'users.id'],
                    'rejection_reason' => ['type' => 'TEXT', 'nullable' => true],
                    'notes' => ['type' => 'TEXT', 'nullable' => true]
                ];
                break;
                
            case 'grades':
                $columns = [
                    'id' => ['type' => 'INT', 'key' => 'PRIMARY', 'auto_increment' => true],
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
                ];
                break;
                
            case 'gwa_calculations':
                $columns = [
                    'id' => ['type' => 'INT', 'key' => 'PRIMARY', 'auto_increment' => true],
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
                ];
                break;
                
            case 'honor_applications':
                $columns = [
                    'id' => ['type' => 'INT', 'key' => 'PRIMARY', 'auto_increment' => true],
                    'user_id' => ['type' => 'INT', 'required' => true, 'foreign_key' => 'users.id'],
                    'academic_period_id' => ['type' => 'INT', 'required' => true, 'foreign_key' => 'academic_periods.id'],
                    'gwa_calculation_id' => ['type' => 'INT', 'required' => true, 'foreign_key' => 'gwa_calculations.id'],
                    'application_type' => ['type' => 'ENUM', 'values' => ['deans_list', 'presidents_list', 'magna_cum_laude', 'summa_cum_laude'], 'required' => true],
                    'gwa_achieved' => ['type' => 'DECIMAL(4,3)', 'required' => true],
                    'required_gwa' => ['type' => 'DECIMAL(4,3)', 'required' => true],
                    'status' => ['type' => 'ENUM', 'values' => ['submitted', 'under_review', 'approved', 'denied', 'cancelled'], 'default' => 'submitted'],
                    'submitted_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
                    'reviewed_at' => ['type' => 'TIMESTAMP', 'nullable' => true],
                    'reviewed_by' => ['type' => 'INT', 'nullable' => true, 'foreign_key' => 'users.id'],
                    'approval_date' => ['type' => 'DATE', 'nullable' => true],
                    'remarks' => ['type' => 'TEXT', 'nullable' => true],
                    'certificate_generated' => ['type' => 'BOOLEAN', 'default' => false],
                    'certificate_path' => ['type' => 'VARCHAR(500)', 'nullable' => true],
                    'ranking_position' => ['type' => 'INT', 'nullable' => true]
                ];
                break;
                
            case 'notifications':
                $columns = [
                    'id' => ['type' => 'INT', 'key' => 'PRIMARY', 'auto_increment' => true],
                    'user_id' => ['type' => 'INT', 'required' => true, 'foreign_key' => 'users.id'],
                    'title' => ['type' => 'VARCHAR(255)', 'required' => true],
                    'message' => ['type' => 'TEXT', 'required' => true],
                    'type' => ['type' => 'ENUM', 'values' => ['info', 'success', 'warning', 'error', 'system'], 'default' => 'info'],
                    'category' => ['type' => 'ENUM', 'values' => ['grade_upload', 'gwa_calculation', 'honor_application', 'system_update', 'general'], 'default' => 'general'],
                    'is_read' => ['type' => 'BOOLEAN', 'default' => false],
                    'is_email_sent' => ['type' => 'BOOLEAN', 'default' => false],
                    'email_sent_at' => ['type' => 'TIMESTAMP', 'nullable' => true],
                    'action_url' => ['type' => 'VARCHAR(500)', 'nullable' => true],
                    'action_text' => ['type' => 'VARCHAR(50)', 'nullable' => true],
                    'expires_at' => ['type' => 'TIMESTAMP', 'nullable' => true],
                    'created_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
                    'read_at' => ['type' => 'TIMESTAMP', 'nullable' => true]
                ];
                break;
                
            case 'system_settings':
                $columns = [
                    'id' => ['type' => 'INT', 'key' => 'PRIMARY', 'auto_increment' => true],
                    'setting_key' => ['type' => 'VARCHAR(100)', 'key' => 'UNIQUE', 'required' => true],
                    'setting_value' => ['type' => 'TEXT', 'required' => true],
                    'setting_type' => ['type' => 'ENUM', 'values' => ['string', 'number', 'boolean', 'json'], 'default' => 'string'],
                    'category' => ['type' => 'VARCHAR(50)', 'default' => 'general'],
                    'description' => ['type' => 'TEXT', 'nullable' => true],
                    'is_public' => ['type' => 'BOOLEAN', 'default' => false],
                    'created_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
                    'updated_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
                    'updated_by' => ['type' => 'INT', 'nullable' => true, 'foreign_key' => 'users.id']
                ];
                break;
                
            case 'honor_rankings':
                $columns = [
                    'id' => ['type' => 'INT', 'key' => 'PRIMARY', 'auto_increment' => true],
                    'academic_period_id' => ['type' => 'INT', 'required' => true, 'foreign_key' => 'academic_periods.id'],
                    'user_id' => ['type' => 'INT', 'required' => true, 'foreign_key' => 'users.id'],
                    'department' => ['type' => 'VARCHAR(100)', 'required' => true],
                    'year_level' => ['type' => 'INT', 'nullable' => true],
                    'section' => ['type' => 'VARCHAR(10)', 'nullable' => true],
                    'ranking_type' => ['type' => 'ENUM', 'values' => ['deans_list', 'presidents_list', 'overall'], 'required' => true],
                    'gwa' => ['type' => 'DECIMAL(4,3)', 'required' => true],
                    'rank_position' => ['type' => 'INT', 'required' => true],
                    'total_students' => ['type' => 'INT', 'required' => true],
                    'percentile' => ['type' => 'DECIMAL(5,2)', 'nullable' => true],
                    'generated_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
                    'generated_by' => ['type' => 'INT', 'nullable' => true, 'foreign_key' => 'users.id']
                ];
                break;
                
            case 'audit_logs':
                $columns = [
                    'id' => ['type' => 'INT', 'key' => 'PRIMARY', 'auto_increment' => true],
                    'user_id' => ['type' => 'INT', 'nullable' => true, 'foreign_key' => 'users.id'],
                    'action' => ['type' => 'VARCHAR(100)', 'required' => true],
                    'table_name' => ['type' => 'VARCHAR(50)', 'nullable' => true],
                    'record_id' => ['type' => 'INT', 'nullable' => true],
                    'old_values' => ['type' => 'JSON', 'nullable' => true],
                    'new_values' => ['type' => 'JSON', 'nullable' => true],
                    'ip_address' => ['type' => 'VARCHAR(45)', 'nullable' => true],
                    'user_agent' => ['type' => 'TEXT', 'nullable' => true],
                    'session_id' => ['type' => 'VARCHAR(128)', 'nullable' => true],
                    'created_at' => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP']
                ];
                break;
        }
        
        return $columns;
    }
    
    /**
     * Get foreign key relationships for a table
     */
    public static function getForeignKeys($tableName) {
        $foreignKeys = [];
        
        switch ($tableName) {
            case 'grade_submissions':
                $foreignKeys = [
                    'user_id' => ['table' => 'users', 'column' => 'id', 'on_delete' => 'CASCADE'],
                    'academic_period_id' => ['table' => 'academic_periods', 'column' => 'id', 'on_delete' => 'RESTRICT'],
                    'processed_by' => ['table' => 'users', 'column' => 'id', 'on_delete' => 'SET NULL']
                ];
                break;
                
            case 'grades':
                $foreignKeys = [
                    'submission_id' => ['table' => 'grade_submissions', 'column' => 'id', 'on_delete' => 'CASCADE']
                ];
                break;
                
            case 'gwa_calculations':
                $foreignKeys = [
                    'user_id' => ['table' => 'users', 'column' => 'id', 'on_delete' => 'CASCADE'],
                    'academic_period_id' => ['table' => 'academic_periods', 'column' => 'id', 'on_delete' => 'RESTRICT'],
                    'submission_id' => ['table' => 'grade_submissions', 'column' => 'id', 'on_delete' => 'CASCADE']
                ];
                break;
                
            case 'honor_applications':
                $foreignKeys = [
                    'user_id' => ['table' => 'users', 'column' => 'id', 'on_delete' => 'CASCADE'],
                    'academic_period_id' => ['table' => 'academic_periods', 'column' => 'id', 'on_delete' => 'RESTRICT'],
                    'gwa_calculation_id' => ['table' => 'gwa_calculations', 'column' => 'id', 'on_delete' => 'RESTRICT'],
                    'reviewed_by' => ['table' => 'users', 'column' => 'id', 'on_delete' => 'SET NULL']
                ];
                break;
                
            case 'notifications':
                $foreignKeys = [
                    'user_id' => ['table' => 'users', 'column' => 'id', 'on_delete' => 'CASCADE']
                ];
                break;
                
            case 'system_settings':
                $foreignKeys = [
                    'updated_by' => ['table' => 'users', 'column' => 'id', 'on_delete' => 'SET NULL']
                ];
                break;
                
            case 'honor_rankings':
                $foreignKeys = [
                    'academic_period_id' => ['table' => 'academic_periods', 'column' => 'id', 'on_delete' => 'CASCADE'],
                    'user_id' => ['table' => 'users', 'column' => 'id', 'on_delete' => 'CASCADE'],
                    'generated_by' => ['table' => 'users', 'column' => 'id', 'on_delete' => 'SET NULL']
                ];
                break;
                
            case 'audit_logs':
                $foreignKeys = [
                    'user_id' => ['table' => 'users', 'column' => 'id', 'on_delete' => 'SET NULL']
                ];
                break;
        }
        
        return $foreignKeys;
    }
    
    /**
     * Get indexes for a table
     */
    public static function getTableIndexes($tableName) {
        $indexes = [];
        
        switch ($tableName) {
            case 'users':
                $indexes = [
                    'idx_email' => ['columns' => ['email'], 'unique' => true],
                    'idx_student_id' => ['columns' => ['student_id'], 'unique' => true],
                    'idx_role' => ['columns' => ['role']],
                    'idx_department' => ['columns' => ['department']],
                    'idx_status' => ['columns' => ['status']],
                    'idx_dept_year_section' => ['columns' => ['department', 'year_level', 'section']]
                ];
                break;
                
            case 'academic_periods':
                $indexes = [
                    'idx_school_year' => ['columns' => ['school_year']],
                    'idx_active' => ['columns' => ['is_active']],
                    'idx_dates' => ['columns' => ['start_date', 'end_date']],
                    'unique_active_period' => ['columns' => ['is_active', 'school_year', 'semester'], 'unique' => true]
                ];
                break;
                
            case 'grade_submissions':
                $indexes = [
                    'idx_status' => ['columns' => ['status']],
                    'idx_upload_date' => ['columns' => ['upload_date']],
                    'idx_processed_by' => ['columns' => ['processed_by']],
                    'unique_user_period' => ['columns' => ['user_id', 'academic_period_id'], 'unique' => true]
                ];
                break;
                
            case 'grades':
                $indexes = [
                    'idx_submission' => ['columns' => ['submission_id']],
                    'idx_subject_code' => ['columns' => ['subject_code']],
                    'idx_grade' => ['columns' => ['grade']],
                    'idx_submission_subject' => ['columns' => ['submission_id', 'subject_code']]
                ];
                break;
                
            case 'gwa_calculations':
                $indexes = [
                    'idx_gwa' => ['columns' => ['gwa']],
                    'idx_calculated_at' => ['columns' => ['calculated_at']],
                    'idx_user_period' => ['columns' => ['user_id', 'academic_period_id']],
                    'unique_user_period_gwa' => ['columns' => ['user_id', 'academic_period_id'], 'unique' => true]
                ];
                break;
                
            case 'honor_applications':
                $indexes = [
                    'idx_status' => ['columns' => ['status']],
                    'idx_application_type' => ['columns' => ['application_type']],
                    'idx_submitted_at' => ['columns' => ['submitted_at']],
                    'idx_gwa_achieved' => ['columns' => ['gwa_achieved']],
                    'idx_reviewed_by' => ['columns' => ['reviewed_by']],
                    'unique_user_period_type' => ['columns' => ['user_id', 'academic_period_id', 'application_type'], 'unique' => true]
                ];
                break;
                
            case 'notifications':
                $indexes = [
                    'idx_user_unread' => ['columns' => ['user_id', 'is_read']],
                    'idx_created_at' => ['columns' => ['created_at']],
                    'idx_type' => ['columns' => ['type']],
                    'idx_category' => ['columns' => ['category']],
                    'idx_expires_at' => ['columns' => ['expires_at']]
                ];
                break;
                
            case 'system_settings':
                $indexes = [
                    'idx_category' => ['columns' => ['category']],
                    'idx_public' => ['columns' => ['is_public']],
                    'idx_updated_by' => ['columns' => ['updated_by']],
                    'unique_setting_key' => ['columns' => ['setting_key'], 'unique' => true]
                ];
                break;
                
            case 'honor_rankings':
                $indexes = [
                    'idx_ranking_type' => ['columns' => ['ranking_type']],
                    'idx_department' => ['columns' => ['department']],
                    'idx_rank_position' => ['columns' => ['rank_position']],
                    'idx_gwa' => ['columns' => ['gwa']],
                    'idx_generated_by' => ['columns' => ['generated_by']],
                    'unique_ranking' => ['columns' => ['academic_period_id', 'department', 'year_level', 'section', 'ranking_type', 'user_id'], 'unique' => true]
                ];
                break;
                
            case 'audit_logs':
                $indexes = [
                    'idx_user_id' => ['columns' => ['user_id']],
                    'idx_action' => ['columns' => ['action']],
                    'idx_table_name' => ['columns' => ['table_name']],
                    'idx_created_at' => ['columns' => ['created_at']],
                    'idx_record_id' => ['columns' => ['record_id']]
                ];
                break;
        }
        
        return $indexes;
    }
    
    /**
     * Get table statistics
     */
    public static function getTableStats() {
        return [
            'users' => [
                'estimated_rows' => 1000,
                'primary_purpose' => 'User authentication and profile management',
                'key_relationships' => ['grade_submissions', 'gwa_calculations', 'honor_applications', 'notifications']
            ],
            'academic_periods' => [
                'estimated_rows' => 20,
                'primary_purpose' => 'Academic semester/period management',
                'key_relationships' => ['grade_submissions', 'gwa_calculations', 'honor_applications']
            ],
            'grade_submissions' => [
                'estimated_rows' => 500,
                'primary_purpose' => 'Track uploaded grade report files',
                'key_relationships' => ['users', 'academic_periods', 'grades', 'gwa_calculations']
            ],
            'grades' => [
                'estimated_rows' => 5000,
                'primary_purpose' => 'Individual subject grades',
                'key_relationships' => ['grade_submissions']
            ],
            'gwa_calculations' => [
                'estimated_rows' => 500,
                'primary_purpose' => 'Computed GWA results',
                'key_relationships' => ['users', 'academic_periods', 'grade_submissions', 'honor_applications']
            ],
            'honor_applications' => [
                'estimated_rows' => 200,
                'primary_purpose' => 'Honor applications tracking',
                'key_relationships' => ['users', 'academic_periods', 'gwa_calculations']
            ],
            'notifications' => [
                'estimated_rows' => 2000,
                'primary_purpose' => 'System notifications',
                'key_relationships' => ['users']
            ],
            'system_settings' => [
                'estimated_rows' => 50,
                'primary_purpose' => 'System configuration',
                'key_relationships' => ['users']
            ],
            'honor_rankings' => [
                'estimated_rows' => 1000,
                'primary_purpose' => 'Honor roll rankings',
                'key_relationships' => ['academic_periods', 'users']
            ],
            'audit_logs' => [
                'estimated_rows' => 10000,
                'primary_purpose' => 'System activity audit trail',
                'key_relationships' => ['users']
            ]
        ];
    }
    
    /**
     * Validate column data types
     */
    public static function validateColumnData($tableName, $columnName, $value) {
        $columns = self::getTableColumns($tableName);
        
        if (!isset($columns[$columnName])) {
            return ['valid' => false, 'error' => 'Column does not exist'];
        }
        
        $column = $columns[$columnName];
        $type = $column['type'];
        
        // Check if required field is empty
        if (isset($column['required']) && $column['required'] && empty($value)) {
            return ['valid' => false, 'error' => 'Field is required'];
        }
        
        // Check if nullable field is null
        if (isset($column['nullable']) && $column['nullable'] && is_null($value)) {
            return ['valid' => true];
        }
        
        // Validate based on data type
        if (strpos($type, 'VARCHAR') === 0) {
            $maxLength = (int) preg_replace('/[^0-9]/', '', $type);
            if (strlen($value) > $maxLength) {
                return ['valid' => false, 'error' => "Value exceeds maximum length of {$maxLength}"];
            }
        } elseif ($type === 'INT') {
            if (!filter_var($value, FILTER_VALIDATE_INT)) {
                return ['valid' => false, 'error' => 'Value must be an integer'];
            }
        } elseif (strpos($type, 'DECIMAL') === 0) {
            if (!is_numeric($value)) {
                return ['valid' => false, 'error' => 'Value must be numeric'];
            }
        } elseif ($type === 'BOOLEAN') {
            if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'])) {
                return ['valid' => false, 'error' => 'Value must be boolean'];
            }
        } elseif ($type === 'ENUM') {
            if (isset($column['values']) && !in_array($value, $column['values'])) {
                return ['valid' => false, 'error' => 'Value must be one of: ' . implode(', ', $column['values'])];
            }
        }
        
        return ['valid' => true];
    }
    
    /**
     * Generate CREATE TABLE statement for a table
     */
    public static function generateCreateTableSQL($tableName) {
        $columns = self::getTableColumns($tableName);
        $foreignKeys = self::getForeignKeys($tableName);
        $indexes = self::getTableIndexes($tableName);
        
        $sql = "CREATE TABLE {$tableName} (\n";
        
        // Add columns
        $columnDefinitions = [];
        foreach ($columns as $columnName => $columnInfo) {
            $definition = "    {$columnName} {$columnInfo['type']}";
            
            if (isset($columnInfo['auto_increment']) && $columnInfo['auto_increment']) {
                $definition .= " AUTO_INCREMENT";
            }
            
            if (isset($columnInfo['required']) && $columnInfo['required']) {
                $definition .= " NOT NULL";
            } elseif (isset($columnInfo['nullable']) && $columnInfo['nullable']) {
                $definition .= " NULL";
            }
            
            if (isset($columnInfo['default'])) {
                if (is_string($columnInfo['default']) && $columnInfo['default'] !== 'CURRENT_TIMESTAMP') {
                    $definition .= " DEFAULT '{$columnInfo['default']}'";
                } else {
                    $definition .= " DEFAULT {$columnInfo['default']}";
                }
            }
            
            if (isset($columnInfo['key']) && $columnInfo['key'] === 'PRIMARY') {
                $definition .= " PRIMARY KEY";
            }
            
            $columnDefinitions[] = $definition;
        }
        
        $sql .= implode(",\n", $columnDefinitions);
        
        // Add foreign keys
        if (!empty($foreignKeys)) {
            foreach ($foreignKeys as $column => $fkInfo) {
                $sql .= ",\n    FOREIGN KEY ({$column}) REFERENCES {$fkInfo['table']}({$fkInfo['column']})";
                if (isset($fkInfo['on_delete'])) {
                    $sql .= " ON DELETE {$fkInfo['on_delete']}";
                }
            }
        }
        
        // Add unique constraints
        if (!empty($indexes)) {
            foreach ($indexes as $indexName => $indexInfo) {
                if (isset($indexInfo['unique']) && $indexInfo['unique']) {
                    $columns = implode(', ', $indexInfo['columns']);
                    $sql .= ",\n    UNIQUE KEY {$indexName} ({$columns})";
                }
            }
        }
        
        $sql .= "\n) ENGINE=InnoDB;";
        
        return $sql;
    }
    
    /**
     * Get sample data for a table
     */
    public static function getSampleData($tableName, $limit = 5) {
        // This would typically query the database
        // For now, return structure information
        return [
            'table' => $tableName,
            'columns' => array_keys(self::getTableColumns($tableName)),
            'sample_count' => $limit,
            'note' => 'Use actual database query to get real sample data'
        ];
    }
}

/**
 * Database Column Types Reference
 */
class DatabaseColumnTypes {
    
    const COLUMN_TYPES = [
        'INT' => [
            'description' => 'Integer number',
            'size' => '4 bytes',
            'range' => '-2,147,483,648 to 2,147,483,647',
            'example' => '123'
        ],
        'VARCHAR' => [
            'description' => 'Variable-length string',
            'size' => '1 to 65,535 characters',
            'range' => 'Depends on length specified',
            'example' => 'VARCHAR(100) for email addresses'
        ],
        'TEXT' => [
            'description' => 'Long text data',
            'size' => 'Up to 65,535 characters',
            'range' => '0 to 65,535 characters',
            'example' => 'Long descriptions, comments'
        ],
        'DECIMAL' => [
            'description' => 'Fixed-point decimal number',
            'size' => 'Varies based on precision',
            'range' => 'Depends on precision and scale',
            'example' => 'DECIMAL(4,3) for GWA values like 1.250'
        ],
        'BOOLEAN' => [
            'description' => 'True/false value',
            'size' => '1 byte',
            'range' => 'TRUE (1) or FALSE (0)',
            'example' => 'is_active, email_verified'
        ],
        'TIMESTAMP' => [
            'description' => 'Date and time',
            'size' => '4 bytes',
            'range' => '1970-01-01 00:00:01 to 2038-01-19 03:14:07',
            'example' => '2024-12-20 15:30:45'
        ],
        'DATE' => [
            'description' => 'Date only',
            'size' => '3 bytes',
            'range' => '1000-01-01 to 9999-12-31',
            'example' => '2024-12-20'
        ],
        'ENUM' => [
            'description' => 'Predefined set of values',
            'size' => '1 or 2 bytes',
            'range' => 'Up to 65,535 distinct values',
            'example' => "ENUM('student', 'adviser', 'chairperson')"
        ],
        'JSON' => [
            'description' => 'JSON document',
            'size' => 'Variable',
            'range' => 'Up to 1GB',
            'example' => '{"old_grade": 1.5, "new_grade": 1.25}'
        ]
    ];
    
    public static function getTypeInfo($type) {
        $baseType = preg_replace('/$$[^)]*$$/', '', $type);
        return self::COLUMN_TYPES[$baseType] ?? null;
    }
}

?>
