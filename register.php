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

    $errors = [];
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
            'department'  => $_POST['department'] ?? '', // This will be set by JavaScript based on course selection
            'college'     => $_POST['college'] ?? '',
            'course'      => $_POST['course'] ?? '',
            'major'       => $_POST['major'] ?? ''
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

        // Enhanced Validation
        if (empty($data['first_name'])) {
            $errors['first_name'] = 'First name is required';
        } elseif (strlen($data['first_name']) < 2) {
            $errors['first_name'] = 'First name must be at least 2 characters long';
        } elseif (!preg_match("/^[a-zA-ZñÑáéíóúÁÉÍÓÚ\s'-]+$/u", $data['first_name'])) {
            $errors['first_name'] = 'First name can only contain letters, spaces, hyphens, and apostrophes';
        }

        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Last name is required';
        } elseif (strlen($data['last_name']) < 2) {
            $errors['last_name'] = 'Last name must be at least 2 characters long';
        } elseif (!preg_match("/^[a-zA-ZñÑáéíóúÁÉÍÓÚ\s'-]+$/u", $data['last_name'])) {
            $errors['last_name'] = 'Last name can only contain letters, spaces, hyphens, and apostrophes';
        }

        if (empty($data['email'])) {
            $errors['email'] = 'Email address is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        } elseif (strlen($data['email']) > 100) {
            $errors['email'] = 'Email address is too long (maximum 100 characters)';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long';
        } elseif (strlen($data['password']) > 100) {
            $errors['password'] = 'Password is too long (maximum 100 characters)';
        } elseif (!preg_match('/[A-Za-z]/', $data['password'])) {
            $errors['password'] = 'Password must contain at least one letter';
        } elseif (!preg_match('/[0-9]/', $data['password'])) {
            $errors['password'] = 'Password must contain at least one number';
        }

        if (empty($data['college'])) {
            $errors['college'] = 'Please select a college';
        }

        if (empty($data['course'])) {
            $errors['course'] = 'Please select a course';
        }

        // Student-specific validation
        if ($role === 'student') {
            if (empty($data['student_id'])) {
                $errors['student_id'] = 'Student ID is required for students';
            } elseif (!preg_match('/^[0-9]{7}$/', $data['student_id'])) {
                $errors['student_id'] = 'Student ID must be exactly 7 digits';
            }

            if (empty($data['year_level'])) {
                $errors['year_level'] = 'Please select your year level';
            } elseif (!in_array($data['year_level'], ['1', '2', '3', '4'])) {
                $errors['year_level'] = 'Please select a valid year level (1-4)';
            }

            if (!empty($data['section']) && !preg_match('/^[A-Z]$/', $data['section'])) {
                $errors['section'] = 'Section must be a single letter (A, B, C, etc.)';
            }
        }

        // If no validation errors, attempt registration
        if (empty($errors)) {
            // Debug: Log the data being sent to registration
            error_log("Registration attempt with data: " . json_encode($data));

            $result = $auth->register($data);

            // Debug: Log the result
            error_log("Registration result: " . json_encode($result));

            if ($result['success']) {
                $success = $result['message'];
            } else {
                // Check for specific database errors
                if (strpos($result['message'], 'email already exists') !== false ||
                    strpos($result['message'], 'Email already exists') !== false ||
                    strpos($result['message'], 'Duplicate entry') !== false) {
                    $errors['email'] = 'This email address is already registered. Please use a different email or try logging in.';
                } elseif (strpos($result['message'], 'student_id') !== false ||
                          strpos($result['message'], 'Student ID already exists') !== false) {
                    $errors['student_id'] = 'This student ID is already registered. Please check your student ID or contact support.';
                } else {
                    // Show the actual error message for debugging
                    $errors['general'] = 'Registration failed: ' . $result['message'] . '. Please check all fields and try again.';
                }
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
                        fontFamily: {
                            sans: ['Poppins', 'system-ui', 'sans-serif'],
                        },
                        colors: {
                            ctu: {
                                50: '#fef8f8',
                                100: '#fef0f0',
                                200: '#fde1e1',
                                300: '#fbc5c5',
                                400: '#f79c9c',
                                500: '#ef6b6b',
                                600: '#dc4444',
                                700: '#b83333',
                                800: '#9a2c2c',
                                900: '#7f2727',
                            },
                            gold: {
                                50: '#fffdf7',
                                100: '#fffaeb',
                                200: '#fef3c7',
                                300: '#fde68a',
                                400: '#fcd34d',
                                500: '#f59e0b',
                                600: '#d97706',
                                700: '#b45309',
                                800: '#92400e',
                                900: '#78350f',
                            },
                            navy: {
                                50: '#f8fafc',
                                100: '#f1f5f9',
                                200: '#e2e8f0',
                                300: '#cbd5e1',
                                400: '#94a3b8',
                                500: '#64748b',
                                600: '#475569',
                                700: '#334155',
                                800: '#1e293b',
                                900: '#0f172a',
                            },
                            sage: {
                                50: '#f6f8f6',
                                100: '#e8f0e8',
                                200: '#d1e1d1',
                                300: '#aac8aa',
                                400: '#7aa67a',
                                500: '#548754',
                                600: '#426b42',
                                700: '#365636',
                                800: '#2d452d',
                                900: '#263a26',
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
                <div class="mx-auto w-16 h-16 bg-ctu-600 rounded-2xl flex items-center justify-center mb-6">
                    <i data-lucide="graduation-cap" class="w-8 h-8 text-white"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Create your account</h2>
                <p class="text-gray-600">Join the CTU Honor System</p>
            </div>

            <!-- Registration Form -->
            <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                <?php if (!empty($errors)): ?>
                    <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
                        <div class="flex items-start">
                            <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 mr-3 mt-0.5 flex-shrink-0"></i>
                            <div class="flex-1">
                                <h4 class="text-red-800 font-semibold text-sm mb-2">Please fix the following errors:</h4>
                                <ul class="text-red-700 text-sm space-y-1">
                                    <?php foreach ($errors as $field => $message): ?>
                                        <li class="flex items-start">
                                            <span class="w-1 h-1 bg-red-500 rounded-full mt-2 mr-2 flex-shrink-0"></span>
                                            <?php echo htmlspecialchars($message); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="mb-6 bg-green-50 border border-green-200 rounded-xl p-4">
                        <div class="flex items-start">
                            <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0"></i>
                            <div class="flex-1">
                                <h4 class="text-green-800 font-semibold text-sm mb-1">Registration Successful!</h4>
                                <p class="text-green-700 text-sm mb-3"><?php echo htmlspecialchars($success); ?></p>
                                <a href="login.php" class="inline-flex items-center text-green-600 hover:text-green-700 font-semibold text-sm transition-colors">
                                    <i data-lucide="log-in" class="w-4 h-4 mr-1"></i>
                                    Login to your account →
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <!-- Name Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-sm font-semibold text-gray-700 mb-2">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required
                                class="block w-full px-3 py-3 border <?php echo isset($errors['first_name']) ? 'border-red-300 bg-red-50' : 'border-gray-300'; ?> rounded-xl focus:ring-2 focus:ring-ctu-500 focus:border-ctu-500 transition-colors"
                                placeholder="Enter your first name"
                                value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                            <?php if (isset($errors['first_name'])): ?>
                                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['first_name']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-semibold text-gray-700 mb-2">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required
                                class="block w-full px-3 py-3 border <?php echo isset($errors['last_name']) ? 'border-red-300 bg-red-50' : 'border-gray-300'; ?> rounded-xl focus:ring-2 focus:ring-ctu-500 focus:border-ctu-500 transition-colors"
                                placeholder="Enter your last name"
                                value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                            <?php if (isset($errors['last_name'])): ?>
                                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['last_name']); ?></p>
                            <?php endif; ?>
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
                                class="block w-full pl-10 pr-3 py-3 border <?php echo isset($errors['email']) ? 'border-red-300 bg-red-50' : 'border-gray-300'; ?> rounded-xl focus:ring-2 focus:ring-ctu-500 focus:border-ctu-500 transition-colors"
                                placeholder="Enter your email address"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            <?php if (isset($errors['email'])): ?>
                                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['email']); ?></p>
                            <?php endif; ?>
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
                                class="block w-full pl-10 pr-3 py-3 border <?php echo isset($errors['password']) ? 'border-red-300 bg-red-50' : 'border-gray-300'; ?> rounded-xl focus:ring-2 focus:ring-ctu-500 focus:border-ctu-500 transition-colors"
                                placeholder="Create a strong password">
                            <?php if (isset($errors['password'])): ?>
                                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['password']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (!isset($errors['password'])): ?>
                            <p class="mt-1 text-sm text-gray-500">Password must be at least 8 characters long and contain both letters and numbers</p>
                        <?php endif; ?>
                    </div>

                    <!-- Role -->
                    <div>
                        <label for="role" class="block text-sm font-semibold text-gray-700 mb-2">Role</label>
                        <select id="role" name="role" onchange="toggleStudentFields()" 
                                class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-ctu-500 focus:border-ctu-500 transition-colors">
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
                                class="block w-full px-3 py-3 border <?php echo isset($errors['student_id']) ? 'border-red-300 bg-red-50' : 'border-gray-300'; ?> rounded-xl focus:ring-2 focus:ring-ctu-500 focus:border-ctu-500 transition-colors"
                                placeholder="Enter your student ID"
                                value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>">
                            <?php if (isset($errors['student_id'])): ?>
                                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['student_id']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="year_level" class="block text-sm font-semibold text-gray-700 mb-2">Year Level</label>
                                <select id="year_level" name="year_level"
                                        class="block w-full px-3 py-3 border <?php echo isset($errors['year_level']) ? 'border-red-300 bg-red-50' : 'border-gray-300'; ?> rounded-xl focus:ring-2 focus:ring-ctu-500 focus:border-ctu-500 transition-colors">
                                    <option value="">Select Year</option>
                                    <option value="1" <?php echo ($_POST['year_level'] ?? '') === '1' ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2" <?php echo ($_POST['year_level'] ?? '') === '2' ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3" <?php echo ($_POST['year_level'] ?? '') === '3' ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4" <?php echo ($_POST['year_level'] ?? '') === '4' ? 'selected' : ''; ?>>4th Year</option>
                                </select>
                                <?php if (isset($errors['year_level'])): ?>
                                    <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['year_level']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label for="section" class="block text-sm font-semibold text-gray-700 mb-2">Section</label>
                                <input type="text" id="section" name="section" maxlength="1" pattern="[A-Z]"
                                    class="block w-full px-3 py-3 border <?php echo isset($errors['section']) ? 'border-red-300 bg-red-50' : 'border-gray-300'; ?> rounded-xl focus:ring-2 focus:ring-ctu-500 focus:border-ctu-500 transition-colors uppercase text-center"
                                    placeholder="A"
                                    style="max-width: 80px;"
                                    value="<?php echo htmlspecialchars($_POST['section'] ?? ''); ?>">
                                <?php if (isset($errors['section'])): ?>
                                    <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['section']); ?></p>
                                <?php endif; ?>
                                <p class="mt-1 text-sm text-gray-500">Enter a single letter (A, B, C, etc.)</p>
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

                    <!-- College, Course, and Major -->
                    <div class="space-y-4">
                        <div>
                            <label for="college" class="block text-sm font-semibold text-gray-700 mb-2">College *</label>
                            <select id="college" name="college" onchange="updateCourses()" required
                                    class="block w-full px-3 py-3 border <?php echo isset($errors['college']) ? 'border-red-300 bg-red-50' : 'border-gray-300'; ?> rounded-xl focus:ring-2 focus:ring-ctu-500 focus:border-ctu-500 transition-colors">
                                <option value="">Select College</option>
                                <option value="College of Agriculture" <?php echo ($_POST['college'] ?? '') === 'College of Agriculture' ? 'selected' : ''; ?>>College of Agriculture</option>
                                <option value="College of Arts and Sciences" <?php echo ($_POST['college'] ?? '') === 'College of Arts and Sciences' ? 'selected' : ''; ?>>College of Arts and Sciences</option>
                                <option value="College of Education" <?php echo ($_POST['college'] ?? '') === 'College of Education' ? 'selected' : ''; ?>>College of Education</option>
                                <option value="College of Engineering" <?php echo ($_POST['college'] ?? '') === 'College of Engineering' ? 'selected' : ''; ?>>College of Engineering</option>
                                <option value="College of Technology" <?php echo ($_POST['college'] ?? '') === 'College of Technology' ? 'selected' : ''; ?>>College of Technology</option>
                            </select>
                            <?php if (isset($errors['college'])): ?>
                                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['college']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label for="course" class="block text-sm font-semibold text-gray-700 mb-2">Course *</label>
                            <select id="course" name="course" onchange="updateMajors()" required
                                    class="block w-full px-3 py-3 border <?php echo isset($errors['course']) ? 'border-red-300 bg-red-50' : 'border-gray-300'; ?> rounded-xl focus:ring-2 focus:ring-ctu-500 focus:border-ctu-500 transition-colors">
                                <option value="">Select Course</option>
                            </select>
                            <?php if (isset($errors['course'])): ?>
                                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['course']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div id="majorDiv" style="display: none;">
                            <label for="major" class="block text-sm font-semibold text-gray-700 mb-2">Major</label>
                            <select id="major" name="major"
                                    class="block w-full px-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-ctu-500 focus:border-ctu-500 transition-colors">
                                <option value="">Select Major</option>
                            </select>
                        </div>

                        <!-- Hidden field for backward compatibility with department -->
                        <input type="hidden" id="department" name="department" value="">
                    </div>

                    <button type="submit" 
                            class="w-full bg-ctu-600 hover:bg-ctu-700 text-white font-semibold py-3 px-4 rounded-xl transition-colors focus:ring-2 focus:ring-ctu-500 focus:ring-offset-2">
                        <i data-lucide="user-plus" class="w-5 h-5 inline mr-2"></i>
                        Create Account
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-gray-600">
                        Already have an account? 
                        <a href="login.php" class="text-ctu-600 hover:text-ctu-700 font-semibold">Sign in here</a>
                    </p>
                    <a href="index.php" class="text-gray-500 hover:text-gray-700 text-sm mt-2 inline-block">
                        <i data-lucide="arrow-left" class="w-4 h-4 inline mr-1"></i>
                        Back to Home
                    </a>
                </div>
            </div>
        </div>

        <script>
            // Academic program data structure
            const academicPrograms = {
                "College of Agriculture": {
                    "Bachelor of Science in Agriculture": {
                        majors: ["Agronomy", "Animal Science", "Horticulture"]
                    }
                },
                "College of Arts and Sciences": {
                    "Bachelor of Arts in English Language": {
                        majors: []
                    },
                    "Bachelor of Arts in Literature": {
                        majors: []
                    }
                },
                "College of Education": {
                    "Bachelor of Elementary Education": {
                        majors: []
                    },
                    "Bachelor of Secondary Education": {
                        majors: ["Mathematics", "English"]
                    },
                    "Bachelor of Technology and Livelihood Education": {
                        majors: []
                    }
                },
                "College of Engineering": {
                    "Bachelor of Science in Civil Engineering": {
                        majors: []
                    },
                    "Bachelor of Science in Electrical Engineering": {
                        majors: []
                    },
                    "Bachelor of Science in Mechanical Engineering": {
                        majors: []
                    },
                    "Bachelor of Science in Industrial Engineering": {
                        majors: []
                    }
                },
                "College of Technology": {
                    "Bachelor of Industrial Technology": {
                        majors: [
                            "Automotive Technology",
                            "Computer Technology",
                            "Drafting Technology",
                            "Electrical Technology",
                            "Electronics Technology",
                            "Food Preparation and Services Technology",
                            "Furniture and Cabinet Making Technology",
                            "Garments Technology",
                            "Machine Shop Technology",
                            "Welding and Fabrication Technology"
                        ]
                    },
                    "Bachelor of Science in Information Technology": {
                        majors: []
                    },
                    "Bachelor of Science in Hospitality Management": {
                        majors: []
                    }
                }
            };

            function updateCourses() {
                const collegeSelect = document.getElementById("college");
                const courseSelect = document.getElementById("course");
                const majorSelect = document.getElementById("major");
                const majorDiv = document.getElementById("majorDiv");
                const departmentInput = document.getElementById("department");

                const selectedCollege = collegeSelect.value;
                courseSelect.innerHTML = '<option value="">Select Course</option>';
                majorSelect.innerHTML = '<option value="">Select Major</option>';
                majorDiv.style.display = "none";

                if (selectedCollege && academicPrograms[selectedCollege]) {
                    Object.keys(academicPrograms[selectedCollege]).forEach(course => {
                        const option = document.createElement("option");
                        option.value = course;
                        option.textContent = course;
                        // preserve previously selected course
                        if ("<?php echo $_POST['course'] ?? ''; ?>" === course) {
                            option.selected = true;
                        }
                        courseSelect.appendChild(option);
                    });
                }

                // update department (just mirror selected college for now)
                departmentInput.value = selectedCollege;

                // also refresh majors if user already had a course selected
                updateMajors();
            }

            function updateMajors() {
                const collegeSelect = document.getElementById("college");
                const courseSelect = document.getElementById("course");
                const majorSelect = document.getElementById("major");
                const majorDiv = document.getElementById("majorDiv");

                const selectedCollege = collegeSelect.value;
                const selectedCourse = courseSelect.value;

                majorSelect.innerHTML = '<option value="">Select Major</option>';
                majorDiv.style.display = "none";

                if (
                    selectedCollege &&
                    selectedCourse &&
                    academicPrograms[selectedCollege] &&
                    academicPrograms[selectedCollege][selectedCourse]
                ) {
                    const majors = academicPrograms[selectedCollege][selectedCourse].majors;
                    if (majors && majors.length > 0) {
                        majors.forEach(major => {
                            const option = document.createElement("option");
                            option.value = major;
                            option.textContent = major;
                            if ("<?php echo $_POST['major'] ?? ''; ?>" === major) {
                                option.selected = true;
                            }
                            majorSelect.appendChild(option);
                        });
                        majorDiv.style.display = "block";
                    }
                }
            }

            function toggleStudentFields() {
                const roleSelect = document.getElementById("role").value;
                document.getElementById("studentFields").style.display =
                    roleSelect === "student" ? "block" : "none";
                document.getElementById("adviserFields").style.display =
                    roleSelect === "adviser" ? "block" : "none";
            }

            // Run on page load (preserve selections after validation errors)
            document.addEventListener("DOMContentLoaded", () => {
                updateCourses();
                updateMajors();
                toggleStudentFields();
            });

            function updateCourses() {
                const collegeSelect = document.getElementById('college');
                const courseSelect = document.getElementById('course');
                const majorDiv = document.getElementById('majorDiv');
                const majorSelect = document.getElementById('major');
                const departmentField = document.getElementById('department');

                // Clear existing options
                courseSelect.innerHTML = '<option value="">Select Course</option>';
                majorSelect.innerHTML = '<option value="">Select Major</option>';
                majorDiv.style.display = 'none';

                const selectedCollege = collegeSelect.value;

                if (selectedCollege && academicPrograms[selectedCollege]) {
                    // Populate courses
                    Object.keys(academicPrograms[selectedCollege]).forEach(course => {
                        const option = document.createElement('option');
                        option.value = course;
                        option.textContent = course;
                        courseSelect.appendChild(option);
                    });

                    // Update department field for backward compatibility
                    departmentField.value = selectedCollege;
                }
            }

            function updateMajors() {
                const collegeSelect = document.getElementById('college');
                const courseSelect = document.getElementById('course');
                const majorDiv = document.getElementById('majorDiv');
                const majorSelect = document.getElementById('major');
                const departmentField = document.getElementById('department');

                // Clear existing majors
                majorSelect.innerHTML = '<option value="">Select Major</option>';
                majorDiv.style.display = 'none';

                const selectedCollege = collegeSelect.value;
                const selectedCourse = courseSelect.value;

                if (selectedCollege && selectedCourse && academicPrograms[selectedCollege][selectedCourse]) {
                    const majors = academicPrograms[selectedCollege][selectedCourse].majors;

                    if (majors && majors.length > 0) {
                        // Show major dropdown if majors are available
                        majorDiv.style.display = 'block';
                        majors.forEach(major => {
                            const option = document.createElement('option');
                            option.value = major;
                            option.textContent = major;
                            majorSelect.appendChild(option);
                        });
                    }

                    // Update department field (use course as department for more specificity)
                    departmentField.value = selectedCourse;
                }
            }

            // Section input validation and formatting
            function setupSectionInput() {
                const sectionInput = document.getElementById('section');
                if (sectionInput) {
                    sectionInput.addEventListener('input', function() {
                        // Convert to uppercase and limit to single letter
                        let value = this.value.toUpperCase().replace(/[^A-Z]/g, '').substring(0, 1);
                        this.value = value;
                    });
                }
            }

            // Initialize form on page load
            document.addEventListener('DOMContentLoaded', function() {
                setupSectionInput();
                // Restore form state if there are existing values (after form error)
                const collegeSelect = document.getElementById('college');
                const courseSelect = document.getElementById('course');
                const majorSelect = document.getElementById('major');

                if (collegeSelect.value) {
                    updateCourses();

                    // Set course value if it exists
                    const savedCourse = '<?php echo htmlspecialchars($_POST['course'] ?? ''); ?>';
                    if (savedCourse) {
                        courseSelect.value = savedCourse;
                        updateMajors();

                        // Set major value if it exists
                        const savedMajor = '<?php echo htmlspecialchars($_POST['major'] ?? ''); ?>';
                        if (savedMajor) {
                            majorSelect.value = savedMajor;
                        }
                    }
                }
            });

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
