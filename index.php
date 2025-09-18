<?php
require_once 'config/config.php';

// Fetch real-time statistics
$total_students = 0;
$total_honors = 0;

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($conn) {
        // Count active students
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND status = 'active'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_students = $result['count'];

        // Count approved honor applications for active academic period
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM honor_applications ha
            JOIN academic_periods ap ON ha.academic_period_id = ap.id
            WHERE ha.status = 'approved' AND ap.is_active = 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_honors = $result['count'];

        // If no active period, get total approved honors
        if ($total_honors == 0) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM honor_applications WHERE status = 'approved'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_honors = $result['count'];
        }
    }
} catch (Exception $e) {
    // Use default values if database query fails
    $total_students = 1247; // Fallback value
    $total_honors = 532;    // Fallback value
}

// Redirect to appropriate dashboard if logged in
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CTU Honor Application System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
                    },
                    animation: {
                        'slide-in-right': 'slideInRight 0.8s ease-out',
                        'slide-in-left': 'slideInLeft 0.8s ease-out',
                        'bounce-slow': 'bounce 3s infinite',
                        'pulse-slow': 'pulse 4s infinite',
                    },
                    backgroundImage: {
                        'ctu-gradient': 'linear-gradient(135deg, #dc4444 0%, #b83333 50%, #9a2c2c 100%)',
                        'gold-gradient': 'linear-gradient(135deg, #f59e0b 0%, #d97706 50%, #b45309 100%)',
                        'sage-gradient': 'linear-gradient(135deg, #548754 0%, #426b42 50%, #365636 100%)',
                        'wave-pattern': 'url("data:image/svg+xml,%3Csvg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="%23fbc5c5" fill-opacity="0.1"%3E%3Cpath d="M20 20c0 20-8.02 20-20 20s-20 0-20-20 8.02-20 20-20 20 0 20 20zm0-20v20c0 8.02-8.02 0-20 0s-20-8.02-20 0v-20c0-20 8.02-20 20-20s20 0 20 20z"/%3E%3C/g%3E%3C/svg%3E")',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        .ctu-text {
            background: linear-gradient(135deg, #dc4444 0%, #9a2c2c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .glass-card {
            backdrop-filter: blur(16px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-ctu-50 to-gold-100 font-sans">
    <!-- Curved Navigation -->
    <nav class="relative bg-white shadow-2xl rounded-b-[3rem] mx-4 mt-4 mb-8">
        <div class="max-w-6xl mx-auto px-8 py-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <div class="w-14 h-14 rounded-full flex items-center justify-center overflow-hidden">
                            <img src="img/cebu-technological-university-seeklogo.png" alt="CTU Logo" class="w-14 h-14 object-contain">
                        </div>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold ctu-text">CTU</h1>
                        <p class="text-sm text-navy-500 font-medium -mt-1">Honor Portal</p>
                    </div>
                </div>
                <div class="hidden md:flex items-center space-x-2">
                    <div class="flex items-center space-x-1 px-4 py-2 bg-ctu-50 rounded-2xl">
                        <i data-lucide="users" class="w-4 h-4 text-ctu-600"></i>
                        <span class="text-sm font-medium text-ctu-700"><?php echo number_format($total_students); ?> Students</span>
                    </div>
                    <div class="flex items-center space-x-1 px-4 py-2 bg-gold-50 rounded-2xl">
                        <i data-lucide="award" class="w-4 h-4 text-gold-600"></i>
                        <span class="text-sm font-medium text-gold-700"><?php echo number_format($total_honors); ?> Honors</span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="login.php" class="px-6 py-3 text-navy-700 hover:text-ctu-600 font-semibold rounded-2xl hover:bg-ctu-50 transition-all duration-300">
                        Access Portal
                    </a>
                    <a href="register.php" class="px-8 py-3 bg-ctu-gradient hover:shadow-xl text-white font-bold rounded-2xl transition-all duration-300 transform hover:scale-105">
                        Join Now
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Side-by-Side Hero -->
    <div class="max-w-7xl mx-auto px-6 py-20">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <!-- Left Content -->
            <div class="space-y-8">
                <div class="space-y-6">
                    <div class="inline-block px-6 py-3 bg-white/80 backdrop-blur-sm rounded-full shadow-lg">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 bg-ctu-400 rounded-full animate-pulse"></div>
                            <span class="text-ctu-700 font-semibold text-sm">Next-Gen Academic Platform</span>
                        </div>
                    </div>

                    <h1 class="text-6xl lg:text-7xl font-bold leading-tight">
                        <span class="text-navy-900">Smart</span><br/>
                        <span class="ctu-text">Academic</span><br/>
                        <span class="text-navy-900">Excellence</span>
                    </h1>

                    <p class="text-xl text-navy-600 leading-relaxed max-w-lg">
                        Revolutionary GWA computation system, automated honor applications, and real-time academic performance tracking.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="login.php" class="group px-8 py-4 bg-ctu-gradient hover:shadow-2xl text-white font-bold rounded-2xl transition-all duration-500 transform hover:scale-105 hover:rotate-1">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="zap" class="w-6 h-6 group-hover:animate-bounce"></i>
                            <span>Start Your Journey</span>
                            <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                        </div>
                    </a>
                    <a href="login.php" class="group px-8 py-4 bg-white/90 hover:bg-white text-navy-700 font-bold rounded-2xl border-2 border-ctu-200 hover:border-ctu-400 transition-all duration-300 shadow-lg hover:shadow-xl">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="shield" class="w-6 h-6 text-gold-600"></i>
                            <span>Faculty Access</span>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Right Visual -->
            <div class="relative">
                <div class="absolute inset-0 bg-ctu-gradient rounded-[3rem] blur-3xl opacity-20 transform rotate-6"></div>
                <div class="relative bg-white rounded-[3rem] p-8 shadow-2xl">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-navy-900">Live Dashboard</h3>
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 bg-green-400 rounded-full animate-pulse"></div>
                                <span class="text-sm font-medium text-navy-500">Real-time</span>
                            </div>
                        </div>

                        <!-- Mock Dashboard Cards -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-gradient-to-br from-ctu-50 to-ctu-100 rounded-2xl">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-ctu-500 rounded-xl flex items-center justify-center">
                                        <i data-lucide="trending-up" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-ctu-700">1.75</p>
                                        <p class="text-sm text-navy-600">Current GWA</p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 bg-gradient-to-br from-gold-50 to-gold-100 rounded-2xl">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-gold-500 rounded-xl flex items-center justify-center">
                                        <i data-lucide="star" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-gold-700">Dean's</p>
                                        <p class="text-sm text-navy-600">Eligible</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                                <span class="text-sm font-medium text-navy-700">Programming</span>
                                <span class="px-3 py-1 bg-ctu-100 text-ctu-700 rounded-full text-xs font-bold">A</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                                <span class="text-sm font-medium text-navy-700">Calculus</span>
                                <span class="px-3 py-1 bg-ctu-100 text-ctu-700 rounded-full text-xs font-bold">A+</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                                <span class="text-sm font-medium text-navy-700">Ethics</span>
                                <span class="px-3 py-1 bg-gold-100 text-gold-700 rounded-full text-xs font-bold">B+</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Process Steps -->
    <div class="py-32 bg-gradient-to-br from-navy-900 to-ctu-900 relative overflow-hidden">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-ctu-400 rounded-full blur-3xl"></div>
            <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-gold-400 rounded-full blur-3xl"></div>
        </div>
        <div class="relative max-w-7xl mx-auto px-6">
            <div class="text-center mb-20">
                <h2 class="text-5xl font-bold text-white mb-6">
                    Simple <span class="text-ctu-300">3-Step</span> Process
                </h2>
                <p class="text-xl text-navy-300 max-w-2xl mx-auto">
                    Get started with your academic excellence journey in minutes
                </p>
            </div>

            <div class="grid lg:grid-cols-3 gap-12">
                <!-- Step 1 -->
                <div class="text-center group">
                    <div class="relative mb-8">
                        <div class="w-24 h-24 bg-ctu-gradient rounded-full flex items-center justify-center mx-auto shadow-2xl group-hover:scale-110 transition-transform duration-300">
                            <span class="text-3xl font-bold text-white">1</span>
                        </div>
                        <div class="absolute -inset-4 bg-ocean-400 rounded-full opacity-20 group-hover:opacity-30 transition-opacity"></div>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4">Upload Grades</h3>
                    <p class="text-navy-300 leading-relaxed">Simply upload your transcript from SIS. Our system supports all major formats and automatically validates data.</p>
                </div>

                <!-- Step 2 -->
                <div class="text-center group">
                    <div class="relative mb-8">
                        <div class="w-24 h-24 bg-gold-gradient rounded-full flex items-center justify-center mx-auto shadow-2xl group-hover:scale-110 transition-transform duration-300">
                            <span class="text-3xl font-bold text-white">2</span>
                        </div>
                        <div class="absolute -inset-4 bg-azure-400 rounded-full opacity-20 group-hover:opacity-30 transition-opacity"></div>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4">Grade Analysis</h3>
                    <p class="text-navy-300 leading-relaxed">Our advanced algorithms compute your GWA and check honor eligibility.</p>
                </div>

                <!-- Step 3 -->
                <div class="text-center group">
                    <div class="relative mb-8">
                        <div class="w-24 h-24 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-full flex items-center justify-center mx-auto shadow-2xl group-hover:scale-110 transition-transform duration-300">
                            <span class="text-3xl font-bold text-white">3</span>
                        </div>
                        <div class="absolute -inset-4 bg-emerald-400 rounded-full opacity-20 group-hover:opacity-30 transition-opacity"></div>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4">Get Results</h3>
                    <p class="text-navy-300 leading-relaxed">Receive instant results and apply for honors with one click.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modern CTA -->
    <div class="py-32 bg-gradient-to-r from-ctu-50 to-gold-50">
        <div class="max-w-4xl mx-auto text-center px-6">
            <div class="inline-block p-2 bg-white rounded-2xl shadow-xl mb-12">
                <div class="flex items-center space-x-4 px-6 py-4">
                    <div class="flex -space-x-2">
                        <div class="w-8 h-8 bg-ctu-400 rounded-full border-2 border-white"></div>
                        <div class="w-8 h-8 bg-gold-400 rounded-full border-2 border-white"></div>
                        <div class="w-8 h-8 bg-emerald-400 rounded-full border-2 border-white"></div>
                    </div>
                    <span class="text-sm font-semibold text-slate-700">Join <?php echo number_format($total_students); ?>+ students already using CTU Honor Portal</span>
                </div>
            </div>

            <h2 class="text-6xl font-bold text-navy-900 mb-8">
                Ready to <span class="ctu-text">Excel?</span>
            </h2>

            <p class="text-2xl text-navy-600 mb-12 max-w-2xl mx-auto">
                Transform your academic journey with the most advanced honor application system ever built.
            </p>

            <div class="flex flex-col sm:flex-row gap-6 justify-center">
                <a href="register.php" class="group px-12 py-5 bg-ctu-gradient hover:shadow-2xl text-white text-xl font-bold rounded-2xl transition-all duration-500 transform hover:scale-105 hover:rotate-1">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="sparkles" class="w-6 h-6 group-hover:animate-spin"></i>
                        <span>Begin Excellence</span>
                    </div>
                </a>
                <a href="login.php" class="group px-12 py-5 bg-white hover:bg-navy-50 text-slate-700 text-xl font-bold rounded-2xl transition-all duration-300 border-2 border-ctu-200 hover:border-ctu-400 shadow-xl hover:shadow-2xl">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="log-in" class="w-6 h-6"></i>
                        <span>Sign In</span>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Modern Footer -->
    <footer class="bg-navy-900 pt-20 pb-12">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid lg:grid-cols-3 gap-12 mb-12">
                <!-- Brand -->
                <div>
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center overflow-hidden">
                            <img src="img/cebu-technological-university-seeklogo.png" alt="CTU Logo" class="w-14 h-14 object-contain">
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-white">CTU Honor Portal</h3>
                            <p class="text-ctu-300 font-medium">Excellence in Every Click</p>
                        </div>
                    </div>
                    <p class="text-slate-400 text-lg leading-relaxed max-w-md">
                        Empowering the next generation of scholars with intelligent academic management solutions.
                    </p>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="text-xl font-bold text-white mb-6">Platform</h4>
                    <ul class="space-y-3">
                        <li><a href="login.php" class="text-slate-400 hover:text-ocean-400 transition-colors font-medium">Student Portal</a></li>
                        <li><a href="register.php" class="text-slate-400 hover:text-ocean-400 transition-colors font-medium">Create Account</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div>
                    <h4 class="text-xl font-bold text-white mb-6">Connect</h4>
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="map-pin" class="w-5 h-5 text-ctu-400"></i>
                            <span class="text-navy-400">Brgy. 8, Tuburan, Cebu, Philippines 6043</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <i data-lucide="mail" class="w-5 h-5 text-ctu-400"></i>
                            <span class="text-navy-400">honors@ctu.edu.ph</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <i data-lucide="phone" class="w-5 h-5 text-ctu-400"></i>
                            <span class="text-navy-400">(032) 463-9313</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-navy-800 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div class="text-slate-400 mb-4 md:mb-0">
                        © 2025 Cebu Technological University - Tuburan Campus
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
