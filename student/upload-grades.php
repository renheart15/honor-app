<?php
require_once '../config/config.php';

requireLogin();

if (!hasRole('student')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();
$gradeProcessor = new GradeProcessor($db);
$notificationManager = new NotificationManager($db);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['grade_file'])) {
    $user_id = $_SESSION['user_id'];
    $academic_period_id = 1; // Current period
    
    $result = $gradeProcessor->uploadGradeFile($user_id, $academic_period_id, $_FILES['grade_file']);
    
    if ($result['success']) {
        $message = $result['message'];
        $message_type = 'success';
        
        // Create notification
        $notificationManager->createNotification(
            $user_id,
            'Grade File Uploaded',
            'Your grade file has been uploaded and is being processed.',
            'success',
            'grade_upload'
        );
        
        // Redirect to prevent resubmission
        header("Location: upload-grades.php?success=1");
        exit();
    } else {
        $message = $result['message'];
        $message_type = 'error';
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = 'Grade file uploaded successfully and is being processed.';
    $message_type = 'success';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Grades - CTU Honor System</title>
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
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="hidden md:flex md:w-64 md:flex-col">
            <div class="flex flex-col flex-grow bg-white border-r border-gray-200 pt-5 pb-4 overflow-y-auto">
                <div class="flex items-center flex-shrink-0 px-4">
                    <img src="../img/cebu-technological-university-seeklogo.png" alt="CTU Logo" class="w-8 h-8">
                    <span class="ml-2 text-xl font-bold text-gray-900">CTU Honor</span>
                </div>
                
                <!-- User Profile -->
                <div class="mt-8 px-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-primary-100 rounded-xl flex items-center justify-center">
                            <i data-lucide="user" class="w-5 h-5 text-primary-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-semibold text-gray-900"><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></p>
                            <p class="text-xs text-gray-500">Student</p>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="mt-8 flex-1 px-2 space-y-1">
                    <a href="dashboard.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="layout-dashboard" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Dashboard
                    </a>
                    <a href="grades.php" class="bg-primary-50 border-r-2 border-primary-600 text-primary-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="bar-chart-3" class="text-primary-500 mr-3 h-5 w-5"></i>
                        My Grades
                    </a>
                    <a href="applications.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="trophy" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Applications
                    </a>
                    <a href="profile.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="settings" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Profile
                    </a>
                </nav>

                <!-- Logout -->
                <div class="px-2 pb-2">
                    <a href="../logout.php" class="text-gray-600 hover:bg-red-50 hover:text-red-700 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="log-out" class="text-gray-400 group-hover:text-red-500 mr-3 h-5 w-5"></i>
                        Sign Out
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex items-center">
                        <button type="button" class="md:hidden -ml-0.5 -mt-0.5 h-12 w-12 inline-flex items-center justify-center rounded-xl text-gray-500 hover:text-gray-900">
                            <i data-lucide="menu" class="h-6 w-6"></i>
                        </button>
                        <div class="ml-4 md:ml-0">
                            <h1 class="text-2xl font-bold text-gray-900">Upload Grade Report</h1>
                            <p class="text-sm text-gray-500">Upload your official grade report for GWA computation</p>
                        </div>
                    </div>
                    
                    <a href="dashboard.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl text-sm font-medium transition-colors flex items-center">
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                        Back to Dashboard
                    </a>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-4 sm:p-6 lg:p-8">
                    <?php if ($message): ?>
                        <div class="mb-6 p-4 rounded-xl border <?php echo $message_type === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'; ?>">
                            <div class="flex items-center">
                                <i data-lucide="<?php echo $message_type === 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5 <?php echo $message_type === 'success' ? 'text-green-500' : 'text-red-500'; ?> mr-2"></i>
                                <span class="<?php echo $message_type === 'success' ? 'text-green-700' : 'text-red-700'; ?> text-sm"><?php echo htmlspecialchars($message); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="max-w-4xl mx-auto">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="upload" class="w-5 h-5 text-primary-600 mr-2"></i>
                                    Grade Report Upload
                                </h3>
                            </div>
                            <div class="p-6">
                                <form method="POST" enctype="multipart/form-data" id="uploadForm" class="space-y-6">
                                    <!-- Upload Area -->
                                    <div class="border-2 border-dashed border-gray-300 rounded-2xl p-12 text-center hover:border-primary-400 transition-colors" id="uploadArea">
                                        <i data-lucide="cloud-upload" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                                        <h4 class="text-xl font-semibold text-gray-900 mb-2">Drag and drop your grade report here</h4>
                                        <p class="text-gray-500 mb-6">or click to browse files</p>
                                        <input type="file" name="grade_file" id="gradeFile" accept=".pdf" class="hidden" required>
                                        <button type="button" onclick="document.getElementById('gradeFile').click()" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-xl font-medium transition-colors">
                                            <i data-lucide="folder-open" class="w-5 h-5 inline mr-2"></i>
                                            Choose File
                                        </button>
                                    </div>

                                    <!-- File Info -->
                                    <div id="fileInfo" class="hidden bg-blue-50 border border-blue-200 rounded-xl p-4">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <i data-lucide="file-text" class="w-5 h-5 text-blue-600 mr-2"></i>
                                                <span id="fileName" class="text-blue-900 font-medium"></span>
                                            </div>
                                            <button type="button" onclick="clearFile()" class="text-red-600 hover:text-red-700">
                                                <i data-lucide="x" class="w-5 h-5"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Requirements -->
                                    <div class="bg-gray-50 rounded-xl p-6">
                                        <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
                                            <i data-lucide="info" class="w-5 h-5 text-blue-600 mr-2"></i>
                                            Requirements
                                        </h4>
                                        <ul class="space-y-2 text-sm text-gray-600">
                                            <li class="flex items-center">
                                                <i data-lucide="check" class="w-4 h-4 text-green-500 mr-2"></i>
                                                File must be in PDF format
                                            </li>
                                            <li class="flex items-center">
                                                <i data-lucide="check" class="w-4 h-4 text-green-500 mr-2"></i>
                                                Maximum file size: 5MB
                                            </li>
                                            <li class="flex items-center">
                                                <i data-lucide="check" class="w-4 h-4 text-green-500 mr-2"></i>
                                                Must be an official grade report
                                            </li>
                                            <li class="flex items-center">
                                                <i data-lucide="check" class="w-4 h-4 text-green-500 mr-2"></i>
                                                All subjects and grades must be clearly visible
                                            </li>
                                        </ul>
                                    </div>

                                    <!-- Submit Button -->
                                    <button type="submit" id="submitBtn" disabled 
                                            class="w-full bg-primary-600 hover:bg-primary-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold py-4 px-6 rounded-xl transition-colors">
                                        <i data-lucide="upload" class="w-5 h-5 inline mr-2"></i>
                                        Upload Grade Report
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Instructions -->
                        <div class="mt-8 bg-white rounded-2xl shadow-sm border border-gray-200">
                            <div class="p-6 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i data-lucide="help-circle" class="w-5 h-5 text-blue-600 mr-2"></i>
                                    Upload Instructions
                                </h3>
                            </div>
                            <div class="p-6">
                                <ol class="space-y-3 text-sm text-gray-600">
                                    <li class="flex items-start">
                                        <span class="bg-primary-100 text-primary-600 rounded-full w-6 h-6 flex items-center justify-center text-xs font-semibold mr-3 mt-0.5">1</span>
                                        <span>Ensure your grade report is the official document from the registrar</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="bg-primary-100 text-primary-600 rounded-full w-6 h-6 flex items-center justify-center text-xs font-semibold mr-3 mt-0.5">2</span>
                                        <span>The document should contain all subjects for the current semester</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="bg-primary-100 text-primary-600 rounded-full w-6 h-6 flex items-center justify-center text-xs font-semibold mr-3 mt-0.5">3</span>
                                        <span>Make sure all grades and subject codes are clearly readable</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="bg-primary-100 text-primary-600 rounded-full w-6 h-6 flex items-center justify-center text-xs font-semibold mr-3 mt-0.5">4</span>
                                        <span>The system will automatically extract grades and compute your GWA</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="bg-primary-100 text-primary-600 rounded-full w-6 h-6 flex items-center justify-center text-xs font-semibold mr-3 mt-0.5">5</span>
                                        <span>You will receive a notification once processing is complete</span>
                                    </li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('gradeFile');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const submitBtn = document.getElementById('submitBtn');

        // Drag and drop functionality
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('border-primary-400', 'bg-primary-50');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('border-primary-400', 'bg-primary-50');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('border-primary-400', 'bg-primary-50');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect();
            }
        });

        // File input change
        fileInput.addEventListener('change', handleFileSelect);

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                if (file.type !== 'application/pdf') {
                    alert('Please select a PDF file only.');
                    clearFile();
                    return;
                }
                
                if (file.size > 5242880) { // 5MB
                    alert('File size must be less than 5MB.');
                    clearFile();
                    return;
                }

                fileName.textContent = file.name;
                fileInfo.classList.remove('hidden');
                submitBtn.disabled = false;
                submitBtn.classList.remove('bg-gray-300', 'cursor-not-allowed');
                submitBtn.classList.add('bg-primary-600', 'hover:bg-primary-700');
            }
        }

        function clearFile() {
            fileInput.value = '';
            fileInfo.classList.add('hidden');
            submitBtn.disabled = true;
            submitBtn.classList.add('bg-gray-300', 'cursor-not-allowed');
            submitBtn.classList.remove('bg-primary-600', 'hover:bg-primary-700');
        }

        // Click to upload
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        // Form submission
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            if (!fileInput.files[0]) {
                e.preventDefault();
                alert('Please select a file to upload.');
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 inline mr-2 animate-spin"></i>Uploading...';
            lucide.createIcons();
        });
    </script>
</body>
</html>
