<?php
require_once 'config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'student':
            redirect('student/dashboard.php');
            break;
        case 'adviser':
            redirect('adviser/dashboard.php');
            break;
        case 'chairperson':
            redirect('chairperson/dashboard.php');
            break;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);

    $role = $_POST['role'] ?? 'student';

    $data = [
        'email'       => $_POST['email'] ?? '',
        'password'    => $_POST['password'] ?? '',
        'first_name'  => $_POST['first_name'] ?? '',
        'last_name'   => $_POST['last_name'] ?? '',
        'role'        => $role,
        'department'  => $_POST['department'] ?? ''
    ];

    // Add role-specific fields
    if ($role === 'student') {
        $data['student_id'] = $_POST['student_id'] ?? '';
        $data['year_level'] = $_POST['year_level'] ?? null;
        $data['section']    = $_POST['section'] ?? '';
    } elseif ($role === 'adviser') {
        // Advisers will be assigned sections by chairperson after registration
        $data['student_id'] = null;
        $data['year_level'] = null;
        $data['section']    = null;
    } else {
        // Chairpersons don't need section assignments
        $data['student_id'] = null;
        $data['year_level'] = null;
        $data['section']    = null;
    }

    // Validation
    if (empty($data['email']) || empty($data['password']) || empty($data['first_name']) || empty($data['last_name'])) {
        $error = 'Please fill in all required fields';
    } elseif (strlen($data['password']) < 8) {
        $error = 'Password must be at least 8 characters long';
    } else {
        $result = $auth->register($data);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

$role = $_GET['role'] ?? 'student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - CTU Honor System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl w-full space-y-8">
        <!-- Header -->
        <div class="text-center">
            <div class="mx-auto w-16 h-16 bg-primary-600 rounded-2xl flex items-center justify-center mb-6">
                <i data-lucide="graduation-cap" class="w-8 h-8 text-white"></i>
            </div>
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Create your account</h2>
            <p class="text-gray-600">Join the CTU Honor System</p>
        </div>

        <!-- Registration Form -->
        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
                    <div class="flex items-center">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 mr-2"></i>
                        <span class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-6 bg-green-50 border border-green-200 rounded-xl p-4">
                    <div class="flex items-center">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-2"></i>
                        <span class="text-green-700 text-sm"><?php echo htmlspecialchars($success); ?></span>
                    </div>
                    <div class="mt-2">
                        <a href="login.php" class="text-green-600 hover:text-green-700 font-semibold text-sm">Login now →</a>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Name Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="block text-sm font-semibold text-gray-700 mb-2">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required
                               class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                               placeholder="Enter your first name"
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-semibold text-gray-700 mb-2">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required
                               class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                               placeholder="Enter your last name"
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address *</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-lucide="mail" class="w-5 h-5 text-gray-400"></i>
                        </div>
                        <input type="email" id="email" name="email" required
                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                               placeholder="Enter your email address"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password *</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-lucide="lock" class="w-5 h-5 text-gray-400"></i>
                        </div>
                        <input type="password" id="password" name="password" required minlength="8"
                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                               placeholder="Create a strong password">
                    </div>
                    <p class="mt-1 text-sm text-gray-500">Password must be at least 8 characters long</p>
                </div>

                <!-- Role -->
                <div>
                    <label for="role" class="block text-sm font-semibold text-gray-700 mb-2">Role</label>
                    <select id="role" name="role" onchange="toggleStudentFields()" 
                            class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                        <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="adviser" <?php echo $role === 'adviser' ? 'selected' : ''; ?>>Adviser</option>
                        <option value="chairperson" <?php echo $role === 'chairperson' ? 'selected' : ''; ?>>Chairperson</option>
                    </select>
                </div>

                <!-- Student-specific fields -->
                <div id="studentFields" style="display: <?php echo $role === 'student' ? 'block' : 'none'; ?>;">
                    <div class="mb-4">
                        <label for="student_id" class="block text-sm font-semibold text-gray-700 mb-2">Student ID</label>
                        <input type="text" id="student_id" name="student_id"
                               class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                               placeholder="Enter your student ID"
                               value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="year_level" class="block text-sm font-semibold text-gray-700 mb-2">Year Level</label>
                            <select id="year_level" name="year_level" 
                                    class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                                <option value="">Select Year</option>
                                <option value="1" <?php echo ($_POST['year_level'] ?? '') === '1' ? 'selected' : ''; ?>>1st Year</option>
                                <option value="2" <?php echo ($_POST['year_level'] ?? '') === '2' ? 'selected' : ''; ?>>2nd Year</option>
                                <option value="3" <?php echo ($_POST['year_level'] ?? '') === '3' ? 'selected' : ''; ?>>3rd Year</option>
                                <option value="4" <?php echo ($_POST['year_level'] ?? '') === '4' ? 'selected' : ''; ?>>4th Year</option>
                            </select>
                        </div>
                        <div>
                            <label for="section" class="block text-sm font-semibold text-gray-700 mb-2">Section</label>
                            <input type="text" id="section" name="section"
                                   class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                                   placeholder="e.g., CS-3A"
                                   value="<?php echo htmlspecialchars($_POST['section'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Adviser Information -->
                <div id="adviserFields" style="display: <?php echo $role === 'adviser' ? 'block' : 'none'; ?>;">
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                        <div class="flex items-center mb-2">
                            <i data-lucide="info" class="w-4 h-4 text-blue-600 mr-2"></i>
                            <span class="text-sm font-medium text-blue-800">Section Assignment</span>
                        </div>
                        <p class="text-sm text-blue-700">Your section assignment will be made by the department chairperson after registration. You'll be notified once assigned.</p>
                    </div>
                </div>

                <!-- Department -->
                <div>
                    <label for="department" class="block text-sm font-semibold text-gray-700 mb-2">Department</label>
                    <select id="department" name="department" 
                            class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                        <option value="">Select Department</option>
                        <option value="Computer Science" <?php echo ($_POST['department'] ?? '') === 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
                        <option value="Information Technology" <?php echo ($_POST['department'] ?? '') === 'Information Technology' ? 'selected' : ''; ?>>Information Technology</option>
                        <option value="Engineering" <?php echo ($_POST['department'] ?? '') === 'Engineering' ? 'selected' : ''; ?>>Engineering</option>
                        <option value="Business Administration" <?php echo ($_POST['department'] ?? '') === 'Business Administration' ? 'selected' : ''; ?>>Business Administration</option>
                        <option value="Education" <?php echo ($_POST['department'] ?? '') === 'Education' ? 'selected' : ''; ?>>Education</option>
                    </select>
                </div>

                <button type="submit" 
                        class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-4 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    <i data-lucide="user-plus" class="w-5 h-5 inline mr-2"></i>
                    Create Account
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-gray-600">
                    Already have an account? 
                    <a href="login.php" class="text-primary-600 hover:text-primary-700 font-semibold">Sign in here</a>
                </p>
                <a href="index.php" class="text-gray-500 hover:text-gray-700 text-sm mt-2 inline-block">
                    <i data-lucide="arrow-left" class="w-4 h-4 inline mr-1"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function toggleStudentFields() {
            const role = document.getElementById('role').value;
            const studentFields = document.getElementById('studentFields');
            const adviserFields = document.getElementById('adviserFields');
            
            // Hide all role-specific fields first
            studentFields.style.display = 'none';
            adviserFields.style.display = 'none';
            
            if (role === 'student') {
                studentFields.style.display = 'block';
            } else if (role === 'adviser') {
                adviserFields.style.display = 'block';
                // Clear student fields
                document.getElementById('student_id').value = '';
                document.getElementById('year_level').value = '';
                document.getElementById('section').value = '';
            } else {
                // Clear all role-specific fields for chairperson
                document.getElementById('student_id').value = '';
                document.getElementById('year_level').value = '';
                document.getElementById('section').value = '';
            }
        }
    </script>
</body>
</html>
