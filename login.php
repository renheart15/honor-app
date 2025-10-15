<?php
require_once 'config/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        $auth = new Auth($db);

        if ($auth->login($email, $password)) {
            // Redirect based on role
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
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - CTU Honor System</title>
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
    <div class="max-w-md w-full space-y-8">
        <!-- Header -->
        <div class="text-center">
            <div class="mx-auto w-16 h-16 bg-ctu-600 rounded-2xl flex items-center justify-center mb-6">
                <img src="img/cebu-technological-university-seeklogo.png" alt="CTU Logo" class="w-16 h-16">
            </div>
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Welcome back</h2>
            <p class="text-gray-600">Sign in to your CTU Honor System account</p>
        </div>

        <!-- Login Form -->
        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
                    <div class="flex items-center">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 mr-2"></i>
                        <span class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-lucide="mail" class="w-5 h-5 text-gray-400"></i>
                        </div>
                        <input type="email" id="email" name="email" required
                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-ctu-500 focus:border-ctu-500 transition-colors"
                               placeholder="Enter your email"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-lucide="lock" class="w-5 h-5 text-gray-400"></i>
                        </div>
                        <input type="password" id="password" name="password" required
                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-ctu-500 focus:border-ctu-500 transition-colors"
                               placeholder="Enter your password">
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-ctu-600 hover:bg-ctu-700 text-white font-semibold py-3 px-4 rounded-xl transition-colors focus:ring-2 focus:ring-ctu-500 focus:ring-offset-2">
                    <i data-lucide="log-in" class="w-5 h-5 inline mr-2"></i>
                    Sign In
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-gray-600">
                    Don't have an account?
                    <a href="register.php" class="text-ctu-600 hover:text-ctu-700 font-semibold">Create one here</a>
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
        
        function fillDemo(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
        }
    </script>
</body>
</html>
