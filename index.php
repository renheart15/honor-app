<?php
require_once 'config/config.php';

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
                        ocean: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        azure: {
                            50: '#f0f8ff',
                            100: '#e0f1fe',
                            200: '#bae4fc',
                            300: '#7ccefb',
                            400: '#36b5f7',
                            500: '#0c9ce8',
                            600: '#0080c7',
                            700: '#0267a1',
                            800: '#065785',
                            900: '#0b496e',
                        },
                        slate: {
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
                        }
                    },
                    animation: {
                        'slide-in-right': 'slideInRight 0.8s ease-out',
                        'slide-in-left': 'slideInLeft 0.8s ease-out',
                        'bounce-slow': 'bounce 3s infinite',
                        'pulse-slow': 'pulse 4s infinite',
                    },
                    backgroundImage: {
                        'ocean-gradient': 'linear-gradient(135deg, #0ea5e9 0%, #0284c7 50%, #0369a1 100%)',
                        'azure-gradient': 'linear-gradient(135deg, #36b5f7 0%, #0c9ce8 50%, #0080c7 100%)',
                        'wave-pattern': 'url("data:image/svg+xml,%3Csvg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="%23bae6fd" fill-opacity="0.1"%3E%3Cpath d="M20 20c0 20-8.02 20-20 20s-20 0-20-20 8.02-20 20-20 20 0 20 20zm0-20v20c0 8.02-8.02 0-20 0s-20-8.02-20 0v-20c0-20 8.02-20 20-20s20 0 20 20z"/%3E%3C/g%3E%3C/svg%3E")',
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
        .ocean-text {
            background: linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%);
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
<body class="bg-gradient-to-br from-ocean-50 to-azure-100 font-sans">
    <!-- Curved Navigation -->
    <nav class="relative bg-white shadow-2xl rounded-b-[3rem] mx-4 mt-4 mb-8">
        <div class="max-w-6xl mx-auto px-8 py-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <div class="w-14 h-14 bg-ocean-gradient rounded-full flex items-center justify-center shadow-xl">
                            <i data-lucide="waves" class="w-8 h-8 text-white"></i>
                        </div>
                        <div class="absolute -bottom-1 -right-1 w-6 h-6 bg-azure-400 rounded-full border-2 border-white flex items-center justify-center">
                            <i data-lucide="sparkles" class="w-3 h-3 text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold ocean-text">CTU</h1>
                        <p class="text-sm text-slate-500 font-medium -mt-1">Honor Portal</p>
                    </div>
                </div>
                <div class="hidden md:flex items-center space-x-2">
                    <div class="flex items-center space-x-1 px-4 py-2 bg-ocean-50 rounded-2xl">
                        <i data-lucide="users" class="w-4 h-4 text-ocean-600"></i>
                        <span class="text-sm font-medium text-ocean-700">1,247 Students</span>
                    </div>
                    <div class="flex items-center space-x-1 px-4 py-2 bg-azure-50 rounded-2xl">
                        <i data-lucide="award" class="w-4 h-4 text-azure-600"></i>
                        <span class="text-sm font-medium text-azure-700">532 Honors</span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="login.php" class="px-6 py-3 text-slate-700 hover:text-ocean-600 font-semibold rounded-2xl hover:bg-ocean-50 transition-all duration-300">
                        Access Portal
                    </a>
                    <a href="register.php" class="px-8 py-3 bg-ocean-gradient hover:shadow-xl text-white font-bold rounded-2xl transition-all duration-300 transform hover:scale-105">
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
                            <div class="w-3 h-3 bg-ocean-400 rounded-full animate-pulse"></div>
                            <span class="text-ocean-700 font-semibold text-sm">Next-Gen Academic Platform</span>
                        </div>
                    </div>

                    <h1 class="text-6xl lg:text-7xl font-bold leading-tight">
                        <span class="text-slate-900">Smart</span><br/>
                        <span class="ocean-text">Academic</span><br/>
                        <span class="text-slate-900">Excellence</span>
                    </h1>

                    <p class="text-xl text-slate-600 leading-relaxed max-w-lg">
                        Revolutionary GWA computation system with AI-powered insights, automated honor applications, and real-time academic performance tracking.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="login.php" class="group px-8 py-4 bg-ocean-gradient hover:shadow-2xl text-white font-bold rounded-2xl transition-all duration-500 transform hover:scale-105 hover:rotate-1">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="zap" class="w-6 h-6 group-hover:animate-bounce"></i>
                            <span>Start Your Journey</span>
                            <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                        </div>
                    </a>
                    <a href="login.php" class="group px-8 py-4 bg-white/90 hover:bg-white text-slate-700 font-bold rounded-2xl border-2 border-ocean-200 hover:border-ocean-400 transition-all duration-300 shadow-lg hover:shadow-xl">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="shield" class="w-6 h-6 text-azure-600"></i>
                            <span>Faculty Access</span>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Right Visual -->
            <div class="relative">
                <div class="absolute inset-0 bg-ocean-gradient rounded-[3rem] blur-3xl opacity-20 transform rotate-6"></div>
                <div class="relative bg-white rounded-[3rem] p-8 shadow-2xl">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-slate-900">Live Dashboard</h3>
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 bg-green-400 rounded-full animate-pulse"></div>
                                <span class="text-sm font-medium text-slate-500">Real-time</span>
                            </div>
                        </div>

                        <!-- Mock Dashboard Cards -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-gradient-to-br from-ocean-50 to-ocean-100 rounded-2xl">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-ocean-500 rounded-xl flex items-center justify-center">
                                        <i data-lucide="trending-up" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-ocean-700">3.85</p>
                                        <p class="text-sm text-slate-600">Current GWA</p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 bg-gradient-to-br from-azure-50 to-azure-100 rounded-2xl">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-azure-500 rounded-xl flex items-center justify-center">
                                        <i data-lucide="star" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-azure-700">Dean's</p>
                                        <p class="text-sm text-slate-600">Eligible</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                                <span class="text-sm font-medium text-slate-700">Mathematics IV</span>
                                <span class="px-3 py-1 bg-ocean-100 text-ocean-700 rounded-full text-xs font-bold">A</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                                <span class="text-sm font-medium text-slate-700">Computer Science</span>
                                <span class="px-3 py-1 bg-ocean-100 text-ocean-700 rounded-full text-xs font-bold">A+</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                                <span class="text-sm font-medium text-slate-700">Physics II</span>
                                <span class="px-3 py-1 bg-azure-100 text-azure-700 rounded-full text-xs font-bold">B+</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Diagonal Features Section -->
    <div class="relative overflow-hidden py-32 bg-white">
        <div class="absolute inset-0 bg-wave-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-6">
            <div class="text-center mb-20">
                <div class="inline-block px-6 py-3 bg-ocean-gradient rounded-full mb-6">
                    <span class="text-white font-bold text-sm flex items-center">
                        <i data-lucide="cpu" class="w-4 h-4 mr-2"></i>
                        Powered by Advanced AI
                    </span>
                </div>
                <h2 class="text-5xl font-bold text-slate-900 mb-6">
                    Built for <span class="ocean-text">Excellence</span>
                </h2>
                <p class="text-xl text-slate-600 max-w-2xl mx-auto">
                    Experience the future of academic management with our cutting-edge platform
                </p>
            </div>

            <!-- Diagonal Grid -->
            <div class="grid md:grid-cols-3 gap-8 transform rotate-2">
                <div class="transform -rotate-2">
                    <div class="bg-white rounded-3xl p-8 shadow-2xl hover:shadow-3xl transition-all duration-500 transform hover:-rotate-1 hover:scale-105 border border-ocean-100">
                        <div class="w-16 h-16 bg-gradient-to-br from-ocean-400 to-ocean-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg">
                            <i data-lucide="brain" class="w-8 h-8 text-white"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-900 mb-4">AI-Powered Analytics</h3>
                        <p class="text-slate-600">Machine learning algorithms analyze your academic patterns and provide personalized improvement recommendations.</p>
                    </div>
                </div>

                <div class="transform -rotate-2 mt-8">
                    <div class="bg-white rounded-3xl p-8 shadow-2xl hover:shadow-3xl transition-all duration-500 transform hover:-rotate-1 hover:scale-105 border border-azure-100">
                        <div class="w-16 h-16 bg-gradient-to-br from-azure-400 to-azure-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg">
                            <i data-lucide="rocket" class="w-8 h-8 text-white"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-900 mb-4">Lightning Fast</h3>
                        <p class="text-slate-600">Process thousands of grades instantly with our optimized computation engine and real-time synchronization.</p>
                    </div>
                </div>

                <div class="transform -rotate-2">
                    <div class="bg-white rounded-3xl p-8 shadow-2xl hover:shadow-3xl transition-all duration-500 transform hover:-rotate-1 hover:scale-105 border border-ocean-100">
                        <div class="w-16 h-16 bg-gradient-to-br from-emerald-400 to-emerald-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg">
                            <i data-lucide="shield-check" class="w-8 h-8 text-white"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-900 mb-4">Bank-Level Security</h3>
                        <p class="text-slate-600">Military-grade encryption and multi-layer authentication protect your sensitive academic information.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Process Steps -->
    <div class="py-32 bg-gradient-to-br from-slate-900 to-ocean-900 relative overflow-hidden">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-ocean-400 rounded-full blur-3xl"></div>
            <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-azure-400 rounded-full blur-3xl"></div>
        </div>
        <div class="relative max-w-7xl mx-auto px-6">
            <div class="text-center mb-20">
                <h2 class="text-5xl font-bold text-white mb-6">
                    Simple <span class="text-ocean-300">3-Step</span> Process
                </h2>
                <p class="text-xl text-slate-300 max-w-2xl mx-auto">
                    Get started with your academic excellence journey in minutes
                </p>
            </div>

            <div class="grid lg:grid-cols-3 gap-12">
                <!-- Step 1 -->
                <div class="text-center group">
                    <div class="relative mb-8">
                        <div class="w-24 h-24 bg-ocean-gradient rounded-full flex items-center justify-center mx-auto shadow-2xl group-hover:scale-110 transition-transform duration-300">
                            <span class="text-3xl font-bold text-white">1</span>
                        </div>
                        <div class="absolute -inset-4 bg-ocean-400 rounded-full opacity-20 group-hover:opacity-30 transition-opacity"></div>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4">Upload Grades</h3>
                    <p class="text-slate-300 leading-relaxed">Simply upload your transcript or enter grades manually. Our system supports all major formats and automatically validates data.</p>
                </div>

                <!-- Step 2 -->
                <div class="text-center group">
                    <div class="relative mb-8">
                        <div class="w-24 h-24 bg-azure-gradient rounded-full flex items-center justify-center mx-auto shadow-2xl group-hover:scale-110 transition-transform duration-300">
                            <span class="text-3xl font-bold text-white">2</span>
                        </div>
                        <div class="absolute -inset-4 bg-azure-400 rounded-full opacity-20 group-hover:opacity-30 transition-opacity"></div>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4">AI Analysis</h3>
                    <p class="text-slate-300 leading-relaxed">Our advanced algorithms compute your GWA, check honor eligibility, and generate comprehensive performance insights.</p>
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
                    <p class="text-slate-300 leading-relaxed">Receive instant results, apply for honors with one click, and download official certificates with digital verification.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modern CTA -->
    <div class="py-32 bg-gradient-to-r from-ocean-50 to-azure-50">
        <div class="max-w-4xl mx-auto text-center px-6">
            <div class="inline-block p-2 bg-white rounded-2xl shadow-xl mb-12">
                <div class="flex items-center space-x-4 px-6 py-4">
                    <div class="flex -space-x-2">
                        <div class="w-8 h-8 bg-ocean-400 rounded-full border-2 border-white"></div>
                        <div class="w-8 h-8 bg-azure-400 rounded-full border-2 border-white"></div>
                        <div class="w-8 h-8 bg-emerald-400 rounded-full border-2 border-white"></div>
                    </div>
                    <span class="text-sm font-semibold text-slate-700">Join 2,500+ students already using CTU Honor Portal</span>
                </div>
            </div>

            <h2 class="text-6xl font-bold text-slate-900 mb-8">
                Ready to <span class="ocean-text">Excel?</span>
            </h2>

            <p class="text-2xl text-slate-600 mb-12 max-w-2xl mx-auto">
                Transform your academic journey with the most advanced honor application system ever built.
            </p>

            <div class="flex flex-col sm:flex-row gap-6 justify-center">
                <a href="register.php" class="group px-12 py-5 bg-ocean-gradient hover:shadow-2xl text-white text-xl font-bold rounded-2xl transition-all duration-500 transform hover:scale-105 hover:rotate-1">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="sparkles" class="w-6 h-6 group-hover:animate-spin"></i>
                        <span>Begin Excellence</span>
                    </div>
                </a>
                <a href="login.php" class="group px-12 py-5 bg-white hover:bg-slate-50 text-slate-700 text-xl font-bold rounded-2xl transition-all duration-300 border-2 border-ocean-200 hover:border-ocean-400 shadow-xl hover:shadow-2xl">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="log-in" class="w-6 h-6"></i>
                        <span>Sign In</span>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Modern Footer -->
    <footer class="bg-slate-900 pt-20 pb-12">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid lg:grid-cols-4 gap-12 mb-12">
                <!-- Brand -->
                <div class="lg:col-span-2">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-16 h-16 bg-ocean-gradient rounded-2xl flex items-center justify-center">
                            <i data-lucide="waves" class="w-10 h-10 text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-white">CTU Honor Portal</h3>
                            <p class="text-ocean-300 font-medium">Excellence in Every Click</p>
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
                        <li><a href="#" class="text-slate-400 hover:text-ocean-400 transition-colors font-medium">Documentation</a></li>
                        <li><a href="#" class="text-slate-400 hover:text-ocean-400 transition-colors font-medium">API Access</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div>
                    <h4 class="text-xl font-bold text-white mb-6">Connect</h4>
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="map-pin" class="w-5 h-5 text-ocean-400"></i>
                            <span class="text-slate-400">CTU Tuburan Campus</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <i data-lucide="mail" class="w-5 h-5 text-ocean-400"></i>
                            <span class="text-slate-400">honors@ctu.edu.ph</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <i data-lucide="phone" class="w-5 h-5 text-ocean-400"></i>
                            <span class="text-slate-400">+63 32 123 4567</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-800 pt-8">
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
