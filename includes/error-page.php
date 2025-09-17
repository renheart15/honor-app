<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Error - CTU Honor System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full text-center">
        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
            <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                <i data-lucide="alert-triangle" class="w-8 h-8 text-red-600"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 mb-4">System Error</h1>
            <p class="text-gray-600 mb-6">We're sorry, but something went wrong. Our team has been notified and is working to fix the issue.</p>
            <div class="space-y-3">
                <a href="javascript:history.back()" class="block bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-xl font-medium transition-colors">
                    <i data-lucide="arrow-left" class="w-4 h-4 inline mr-2"></i>
                    Go Back
                </a>
                <a href="/" class="block bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-xl font-medium transition-colors">
                    <i data-lucide="home" class="w-4 h-4 inline mr-2"></i>
                    Go Home
                </a>
            </div>
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
