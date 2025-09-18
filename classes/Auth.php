<?php
class Auth {
    private $conn;
    private $user;

    public function __construct($db) {
        $this->conn = $db;
        $this->user = new User($db);
    }

    public function login($email, $password) {
        $user_data = $this->user->findByEmail($email);
        
        if ($user_data && password_verify($password, $user_data['password'])) {
            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['email'] = $user_data['email'];
            $_SESSION['role'] = $user_data['role'];
            $_SESSION['first_name'] = $user_data['first_name'];
            $_SESSION['last_name'] = $user_data['last_name'];
            $_SESSION['department'] = $user_data['department'];
            $_SESSION['section'] = $user_data['section'];
            
            return true;
        }
        
        return false;
    }

    public function register($data) {
        try {
            // Check if email already exists
            if ($this->user->findByEmail($data['email'])) {
                return ['success' => false, 'message' => 'Email already exists'];
            }

            // Check if student ID already exists (for students only)
            if (($data['role'] ?? 'student') === 'student' && !empty($data['student_id'])) {
                $existing_student = $this->user->findByStudentId($data['student_id']);
                if ($existing_student) {
                    return ['success' => false, 'message' => 'Student ID already exists'];
                }
            }

            // Set user properties
            $this->user->student_id = $data['student_id'];
            $this->user->email = $data['email'];
            $this->user->password = $data['password'];
            $this->user->first_name = $data['first_name'];
            $this->user->last_name = $data['last_name'];
            $this->user->role = $data['role'] ?? 'student';
            $this->user->department = $data['department'];
            $this->user->college = $data['college'] ?? null;
            $this->user->course = $data['course'] ?? null;
            $this->user->major = $data['major'] ?? null;
            $this->user->year_level = $data['year_level'];
            $this->user->section = $data['section'];

            if ($this->user->create()) {
                return ['success' => true, 'message' => 'Registration successful'];
            }

            return ['success' => false, 'message' => 'Registration failed - database error'];
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed - system error'];
        }
    }

    public function logout() {
        session_destroy();
        return true;
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return $this->user->findById($_SESSION['user_id']);
        }
        return null;
    }
}
?>
