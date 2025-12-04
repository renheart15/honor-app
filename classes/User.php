<?php
class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $student_id;
    public $email;
    public $password;
    public $first_name;
    public $last_name;
    public $middle_name;
    public $role;
    public $department;
    public $college;
    public $course;
    public $major;
    public $year_level;
    public $section;
    public $contact_number;
    public $address;
    public $status;
    public $email_verified;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET student_id=:student_id, email=:email, password=:password,
                    first_name=:first_name, last_name=:last_name, middle_name=:middle_name,
                    role=:role, department=:department, college=:college, course=:course, major=:major,
                    year_level=:year_level, section=:section, contact_number=:contact_number, address=:address";

        $stmt = $this->conn->prepare($query);

        // If role is student, keep values; otherwise set NULL
        $this->student_id = ($this->role === 'student') ? htmlspecialchars(strip_tags($this->student_id)) : null;
        $this->year_level = ($this->role === 'student') ? (int)$this->year_level : null;
        $this->section    = ($this->role === 'student') ? htmlspecialchars(strip_tags($this->section)) : null;

        $this->email = htmlspecialchars(strip_tags($this->email ?? ''));
        $this->password = password_hash($this->password ?? '', PASSWORD_DEFAULT);
        $this->first_name = htmlspecialchars(strip_tags($this->first_name ?? ''));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name ?? ''));
        $this->middle_name = htmlspecialchars(strip_tags($this->middle_name ?? ''));
        $this->role = htmlspecialchars(strip_tags($this->role ?? ''));
        $this->department = htmlspecialchars(strip_tags($this->department ?? ''));
        $this->college = htmlspecialchars(strip_tags($this->college ?? ''));
        $this->course = htmlspecialchars(strip_tags($this->course ?? ''));
        $this->major = htmlspecialchars(strip_tags($this->major ?? ''));
        $this->contact_number = htmlspecialchars(strip_tags($this->contact_number ?? ''));
        $this->address = htmlspecialchars(strip_tags($this->address ?? ''));

        // Bind values (use PDO::PARAM_NULL for nulls)
        $stmt->bindValue(":student_id", $this->student_id, $this->student_id === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":middle_name", $this->middle_name);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":department", $this->department);
        $stmt->bindParam(":college", $this->college);
        $stmt->bindParam(":course", $this->course);
        $stmt->bindParam(":major", $this->major);
        $stmt->bindValue(":year_level", $this->year_level, $this->year_level === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(":section", $this->section, $this->section === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(":contact_number", $this->contact_number);
        $stmt->bindParam(":address", $this->address);

        try {
            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return true;
            }
        } catch (PDOException $e) {
            error_log("User creation error: " . $e->getMessage());
            return false;
        }

        return false;
    }


    public function findByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByStudentId($student_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE student_id = :student_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":student_id", $student_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateProfile($id, $data) {
        // Build dynamic query based on available fields
        $set_parts = [];
        $params = [];

        // Always update these core fields
        $set_parts[] = "first_name=:first_name";
        $set_parts[] = "last_name=:last_name";
        $set_parts[] = "middle_name=:middle_name";
        $set_parts[] = "year_level=:year_level";
        $set_parts[] = "section=:section";
        $set_parts[] = "contact_number=:contact_number";
        $set_parts[] = "address=:address";

        // Only update department if provided
        if (isset($data['department'])) {
            $set_parts[] = "department=:department";
        }

        // Add email and student_id if provided
        if (isset($data['email'])) {
            $set_parts[] = "email=:email";
        }
        if (isset($data['student_id'])) {
            $set_parts[] = "student_id=:student_id";
        }

        $set_parts[] = "updated_at=NOW()";

        $query = "UPDATE " . $this->table_name . " SET " . implode(", ", $set_parts) . " WHERE id=:id";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $first_name = htmlspecialchars(strip_tags($data['first_name']));
        $last_name = htmlspecialchars(strip_tags($data['last_name']));
        $middle_name = htmlspecialchars(strip_tags($data['middle_name'] ?? ''));
        $year_level = $data['year_level'] ? (int)$data['year_level'] : null;
        $section = htmlspecialchars(strip_tags($data['section']));
        $contact_number = htmlspecialchars(strip_tags($data['contact_number'] ?? ''));
        $address = htmlspecialchars(strip_tags($data['address'] ?? ''));

        // Bind values
        $stmt->bindParam(":first_name", $first_name);
        $stmt->bindParam(":last_name", $last_name);
        $stmt->bindParam(":middle_name", $middle_name);
        $stmt->bindParam(":year_level", $year_level);
        $stmt->bindParam(":section", $section);
        $stmt->bindParam(":contact_number", $contact_number);
        $stmt->bindParam(":address", $address);

        // Bind optional fields only if present
        if (isset($data['department'])) {
            $department = htmlspecialchars(strip_tags($data['department']));
            $stmt->bindParam(":department", $department);
        }
        if (isset($data['email'])) {
            $email = htmlspecialchars(strip_tags($data['email']));
            $stmt->bindParam(":email", $email);
        }
        if (isset($data['student_id'])) {
            $student_id = htmlspecialchars(strip_tags($data['student_id']));
            $stmt->bindParam(":student_id", $student_id);
        }

        $stmt->bindParam(":id", $id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            return false;
        }
    }

    public function updateLastLogin($id) {
        $query = "UPDATE " . $this->table_name . " SET last_login=NOW() WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Last login update error: " . $e->getMessage());
            return false;
        }
    }

    public function changePassword($id, $new_password) {
        $query = "UPDATE " . $this->table_name . " SET password=:password WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt->bindParam(":password", $hashed_password);
        $stmt->bindParam(":id", $id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Password change error: " . $e->getMessage());
            return false;
        }
    }

    public function getAllByRole($role, $department = null) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE role = :role";
        
        if ($department) {
            $query .= " AND department = :department";
        }
        
        $query .= " ORDER BY last_name, first_name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":role", $role);
        
        if ($department) {
            $stmt->bindParam(":department", $department);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " SET status=:status WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Status update error: " . $e->getMessage());
            return false;
        }
    }
}
?>
