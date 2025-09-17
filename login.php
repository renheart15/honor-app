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
    <div class="max-w-md w-full space-y-8">
        <!-- Header -->
        <div class="text-center">
            <div class="mx-auto w-16 h-16 bg-primary-600 rounded-2xl flex items-center justify-center mb-6">
                <i data-lucide="graduation-cap" class="w-8 h-8 text-white"></i>
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
                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
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
                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                               placeholder="Enter your password">
                    </div>
                </div>

                <button type="submit" 
                        class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-4 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    <i data-lucide="log-in" class="w-5 h-5 inline mr-2"></i>
                    Sign In
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-gray-600">
                    Don't have an account? 
                    <a href="register.php" class="text-primary-600 hover:text-primary-700 font-semibold">Create one here</a>
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
