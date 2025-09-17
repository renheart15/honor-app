# CTU Honor Application System - Database Columns Reference

## Table Overview
The system uses 10 main tables to manage all aspects of the honor application process.

---

## 1. USERS Table
**Purpose**: Store all user accounts (students, advisers, chairpersons)

| Column Name | Data Type | Constraints | Description |
|-------------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Unique user identifier |
| `student_id` | VARCHAR(20) | UNIQUE, NULL | Student ID number (null for non-students) |
| `email` | VARCHAR(100) | UNIQUE, NOT NULL | User email address (login credential) |
| `password` | VARCHAR(255) | NOT NULL | Hashed password using password_hash() |
| `first_name` | VARCHAR(50) | NOT NULL | User first name |
| `last_name` | VARCHAR(50) | NOT NULL | User last name |
| `middle_name` | VARCHAR(50) | NULL | User middle name (optional) |
| `role` | ENUM | NOT NULL, DEFAULT 'student' | User role: student, adviser, chairperson |
| `department` | VARCHAR(100) | NULL | Academic department |
| `year_level` | INT | NULL | Student year level (1-4, null for non-students) |
| `section` | VARCHAR(10) | NULL | Student section (e.g., CS-3A) |
| `contact_number` | VARCHAR(15) | NULL | Phone/mobile number |
| `address` | TEXT | NULL | Complete address |
| `status` | ENUM | DEFAULT 'active' | Account status: active, inactive, suspended |
| `email_verified` | BOOLEAN | DEFAULT FALSE | Email verification status |
| `last_login` | TIMESTAMP | NULL | Last login timestamp |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Account creation date |
| `updated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE | Last update date |

**Indexes**: email, student_id, role, department, status, dept_year_section

---

## 2. ACADEMIC_PERIODS Table
**Purpose**: Manage academic periods/semesters

| Column Name | Data Type | Constraints | Description |
|-------------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Unique period identifier |
| `period_name` | VARCHAR(50) | NOT NULL | Display name (e.g., First Semester 2024-2025) |
| `school_year` | VARCHAR(20) | NOT NULL | Academic year (e.g., 2024-2025) |
| `semester` | ENUM | NOT NULL | Semester type: 1st, 2nd, summer |
| `start_date` | DATE | NOT NULL | Period start date |
| `end_date` | DATE | NOT NULL | Period end date |
| `is_active` | BOOLEAN | DEFAULT FALSE | Only one period can be active at a time |
| `registration_deadline` | DATE | NULL | Deadline for grade submissions |
| `application_deadline` | DATE | NULL | Deadline for honor applications |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Record creation date |
| `updated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE | Last update date |

**Indexes**: school_year, is_active, dates
**Constraints**: unique_active_period (is_active, school_year, semester)

---

## 3. GRADE_SUBMISSIONS Table
**Purpose**: Track uploaded grade report files

| Column Name | Data Type | Constraints | Description |
|-------------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Unique submission identifier |
| `user_id` | INT | NOT NULL, FOREIGN KEY | Student who submitted the grades |
| `academic_period_id` | INT | NOT NULL, FOREIGN KEY | Academic period for this submission |
| `file_path` | VARCHAR(500) | NOT NULL | Server path to uploaded PDF file |
| `file_name` | VARCHAR(255) | NOT NULL | Original filename from upload |
| `file_size` | INT | NOT NULL | File size in bytes |
| `mime_type` | VARCHAR(100) | NOT NULL, DEFAULT 'application/pdf' | File MIME type |
| `upload_date` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | When file was uploaded |
| `status` | ENUM | DEFAULT 'pending' | Processing status: pending, processing, processed, rejected, failed |
| `processed_at` | TIMESTAMP | NULL | When processing completed |
| `processed_by` | INT | NULL, FOREIGN KEY | ID of user who processed |
| `rejection_reason` | TEXT | NULL | Reason for rejection if status is rejected |
| `notes` | TEXT | NULL | Additional notes from processor |

**Indexes**: status, upload_date, processed_by
**Constraints**: unique_user_period (user_id, academic_period_id)

---

## 4. GRADES Table
**Purpose**: Store individual subject grades extracted from submissions

| Column Name | Data Type | Constraints | Description |
|-------------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Unique grade record identifier |
| `submission_id` | INT | NOT NULL, FOREIGN KEY | Reference to grade submission |
| `subject_code` | VARCHAR(20) | NOT NULL | Subject code (e.g., CS101, MATH201) |
| `subject_name` | VARCHAR(150) | NOT NULL | Full subject name |
| `units` | DECIMAL(3,1) | NOT NULL | Credit units (e.g., 3.0, 1.5) |
| `grade` | DECIMAL(3,2) | NOT NULL | Numerical grade (e.g., 1.25, 2.50) |
| `letter_grade` | VARCHAR(5) | NULL | Letter equivalent (A, B+, etc.) |
| `remarks` | VARCHAR(50) | NULL | PASSED, FAILED, INC, etc. |
| `semester_taken` | VARCHAR(20) | NULL | When subject was taken |
| `adviser_name` | VARCHAR(100) | NULL | Subject adviser |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Record creation date |

**Indexes**: submission, subject_code, grade, submission_subject

---

## 5. GWA_CALCULATIONS Table
**Purpose**: Store computed GWA results for each student per academic period

| Column Name | Data Type | Constraints | Description |
|-------------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Unique calculation identifier |
| `user_id` | INT | NOT NULL, FOREIGN KEY | Student for this GWA calculation |
| `academic_period_id` | INT | NOT NULL, FOREIGN KEY | Academic period |
| `submission_id` | INT | NOT NULL, FOREIGN KEY | Grade submission used for calculation |
| `total_units` | DECIMAL(6,1) | NOT NULL | Total credit units |
| `total_grade_points` | DECIMAL(10,2) | NOT NULL | Sum of (grade × units) |
| `gwa` | DECIMAL(4,3) | NOT NULL | General Weighted Average |
| `subjects_count` | INT | NOT NULL | Number of subjects |
| `failed_subjects` | INT | DEFAULT 0 | Number of failed subjects (grade >= 3.0) |
| `incomplete_subjects` | INT | DEFAULT 0 | Number of incomplete subjects |
| `calculation_method` | VARCHAR(50) | DEFAULT 'weighted_average' | Method used for calculation |
| `calculated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | When GWA was calculated |
| `recalculated_at` | TIMESTAMP | NULL ON UPDATE | When GWA was recalculated |

**Indexes**: gwa, calculated_at, user_period
**Constraints**: unique_user_period_gwa (user_id, academic_period_id)

---

## 6. HONOR_APPLICATIONS Table
**Purpose**: Track honor applications (Dean's List, President's List, etc.)

| Column Name | Data Type | Constraints | Description |
|-------------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Unique application identifier |
| `user_id` | INT | NOT NULL, FOREIGN KEY | Student applying for honor |
| `academic_period_id` | INT | NOT NULL, FOREIGN KEY | Academic period for application |
| `gwa_calculation_id` | INT | NOT NULL, FOREIGN KEY | GWA calculation used for application |
| `application_type` | ENUM | NOT NULL | Type: deans_list, presidents_list, magna_cum_laude, summa_cum_laude |
| `gwa_achieved` | DECIMAL(4,3) | NOT NULL | GWA at time of application |
| `required_gwa` | DECIMAL(4,3) | NOT NULL | Required GWA for this honor type |
| `status` | ENUM | DEFAULT 'submitted' | Status: submitted, under_review, approved, denied, cancelled |
| `submitted_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | When application was submitted |
| `reviewed_at` | TIMESTAMP | NULL | When application was reviewed |
| `reviewed_by` | INT | NULL, FOREIGN KEY | Adviser/Chairperson who reviewed |
| `approval_date` | DATE | NULL | Date of approval |
| `remarks` | TEXT | NULL | Review comments |
| `certificate_generated` | BOOLEAN | DEFAULT FALSE | Whether certificate was generated |
| `certificate_path` | VARCHAR(500) | NULL | Path to generated certificate |
| `ranking_position` | INT | NULL | Position in honor roll ranking |

**Indexes**: status, application_type, submitted_at, gwa_achieved, reviewed_by
**Constraints**: unique_user_period_type (user_id, academic_period_id, application_type)

---

## 7. NOTIFICATIONS Table
**Purpose**: System notifications for users

| Column Name | Data Type | Constraints | Description |
|-------------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Unique notification identifier |
| `user_id` | INT | NOT NULL, FOREIGN KEY | User receiving the notification |
| `title` | VARCHAR(255) | NOT NULL | Notification title |
| `message` | TEXT | NOT NULL | Notification message content |
| `type` | ENUM | DEFAULT 'info' | Type: info, success, warning, error, system |
| `category` | ENUM | DEFAULT 'general' | Category: grade_upload, gwa_calculation, honor_application, system_update, general |
| `is_read` | BOOLEAN | DEFAULT FALSE | Whether notification has been read |
| `is_email_sent` | BOOLEAN | DEFAULT FALSE | Whether email notification was sent |
| `email_sent_at` | TIMESTAMP | NULL | When email was sent |
| `action_url` | VARCHAR(500) | NULL | URL for notification action button |
| `action_text` | VARCHAR(50) | NULL | Text for action button |
| `expires_at` | TIMESTAMP | NULL | When notification expires |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | When notification was created |
| `read_at` | TIMESTAMP | NULL | When notification was read |

**Indexes**: user_unread, created_at, type, category, expires_at

---

## 8. SYSTEM_SETTINGS Table
**Purpose**: System configuration settings

| Column Name | Data Type | Constraints | Description |
|-------------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Unique setting identifier |
| `setting_key` | VARCHAR(100) | UNIQUE, NOT NULL | Unique setting key |
| `setting_value` | TEXT | NOT NULL | Setting value (can be long text) |
| `setting_type` | ENUM | DEFAULT 'string' | Data type: string, number, boolean, json |
| `category` | VARCHAR(50) | DEFAULT 'general' | Setting category for organization |
| `description` | TEXT | NULL | Description of what this setting does |
| `is_public` | BOOLEAN | DEFAULT FALSE | Can be accessed by non-admin users |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | When setting was created |
| `updated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE | When setting was last updated |
| `updated_by` | INT | NULL, FOREIGN KEY | User who last updated this setting |

**Indexes**: category, is_public, updated_by

---

## 9. HONOR_RANKINGS Table
**Purpose**: Honor roll rankings by department, year, and section

| Column Name | Data Type | Constraints | Description |
|-------------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Unique ranking identifier |
| `academic_period_id` | INT | NOT NULL, FOREIGN KEY | Academic period for ranking |
| `user_id` | INT | NOT NULL, FOREIGN KEY | Student being ranked |
| `department` | VARCHAR(100) | NOT NULL | Department for ranking |
| `year_level` | INT | NULL | Year level (NULL for department-wide ranking) |
| `section` | VARCHAR(10) | NULL | Section (NULL for year-level ranking) |
| `ranking_type` | ENUM | NOT NULL | Type: deans_list, presidents_list, overall |
| `gwa` | DECIMAL(4,3) | NOT NULL | Student GWA for ranking |
| `rank_position` | INT | NOT NULL | Position in ranking (1 = highest) |
| `total_students` | INT | NOT NULL | Total students in this ranking category |
| `percentile` | DECIMAL(5,2) | NULL | Percentile ranking (0-100) |
| `generated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | When ranking was generated |
| `generated_by` | INT | NULL, FOREIGN KEY | User who generated the ranking |

**Indexes**: ranking_type, department, rank_position, gwa, generated_by
**Constraints**: unique_ranking (academic_period_id, department, year_level, section, ranking_type, user_id)

---

## 10. AUDIT_LOGS Table
**Purpose**: Audit trail for system actions

| Column Name | Data Type | Constraints | Description |
|-------------|-----------|-------------|-------------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | Unique log entry identifier |
| `user_id` | INT | NULL, FOREIGN KEY | User who performed the action (NULL for system actions) |
| `action` | VARCHAR(100) | NOT NULL | Action performed (login, upload, approve, etc.) |
| `table_name` | VARCHAR(50) | NULL | Database table affected |
| `record_id` | INT | NULL | ID of affected record |
| `old_values` | JSON | NULL | Previous values (for updates) |
| `new_values` | JSON | NULL | New values (for inserts/updates) |
| `ip_address` | VARCHAR(45) | NULL | IP address of user |
| `user_agent` | TEXT | NULL | Browser user agent string |
| `session_id` | VARCHAR(128) | NULL | Session identifier |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | When action was performed |

**Indexes**: user_id, action, table_name, created_at, record_id

---

## Key Relationships

### Primary Relationships
- **users** → **grade_submissions** (1:M)
- **users** → **gwa_calculations** (1:M)
- **users** → **honor_applications** (1:M)
- **users** → **notifications** (1:M)
- **academic_periods** → **grade_submissions** (1:M)
- **academic_periods** → **gwa_calculations** (1:M)
- **academic_periods** → **honor_applications** (1:M)
- **grade_submissions** → **grades** (1:M)
- **gwa_calculations** → **honor_applications** (1:M)

### Important Constraints
1. **One active academic period**: Only one academic period can be active at a time
2. **One submission per period**: Each student can only have one grade submission per academic period
3. **One GWA per period**: Each student can only have one GWA calculation per academic period
4. **Unique honor applications**: Each student can only apply for each honor type once per academic period

### Data Types Explanation
- **DECIMAL(4,3)**: For GWA values (e.g., 1.250)
- **DECIMAL(3,2)**: For individual grades (e.g., 1.25)
- **DECIMAL(3,1)**: For credit units (e.g., 3.0)
- **ENUM**: For predefined choices
- **JSON**: For storing complex data structures in audit logs
- **TEXT**: For long text content
- **TIMESTAMP**: For date/time tracking with automatic updates
