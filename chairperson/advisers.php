<?php
require_once '../config/config.php';

requireLogin();

if (!hasRole('chairperson')) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$chairperson_id = $_SESSION['user_id'];
$department = $_SESSION['department'];

$message = '';
$message_type = '';

// Handle adviser management actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $adviser_id = $_POST['adviser_id'] ?? 0;
    
    if ($action === 'activate') {
        $query = "UPDATE users SET status = 'active' WHERE id = :adviser_id AND role = 'adviser' AND department = :department";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':adviser_id', $adviser_id);
        $stmt->bindParam(':department', $department);
        
        if ($stmt->execute()) {
            $message = 'Adviser activated successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to activate adviser.';
            $message_type = 'error';
        }
    } elseif ($action === 'deactivate') {
        $query = "UPDATE users SET status = 'inactive' WHERE id = :adviser_id AND role = 'adviser' AND department = :department";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':adviser_id', $adviser_id);
        $stmt->bindParam(':department', $department);
        
        if ($stmt->execute()) {
            $message = 'Adviser deactivated successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to deactivate adviser.';
            $message_type = 'error';
        }
    } elseif ($action === 'assign_section') {
        $section = $_POST['section'] ?? '';
        $year_level = $_POST['year_level'] ?? null;

        if (empty($section)) {
            $message = 'Section is required for assignment.';
            $message_type = 'error';
        } else {
            // Get current adviser's sections
            $query = "SELECT section FROM users WHERE id = :adviser_id AND role = 'adviser' AND department = :department";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':adviser_id', $adviser_id);
            $stmt->bindParam(':department', $department);
            $stmt->execute();
            $current_adviser = $stmt->fetch(PDO::FETCH_ASSOC);

            $current_sections = [];
            if (!empty($current_adviser['section'])) {
                $sections_data = json_decode($current_adviser['section'], true);
                if (!is_array($sections_data)) {
                    $sections_data = array_map('trim', explode(',', $current_adviser['section']));
                }
                $current_sections = $sections_data;
            }

            // Check if section is already assigned to this adviser
            if (in_array($section, $current_sections)) {
                $message = "Section {$section} is already assigned to this adviser.";
                $message_type = 'error';
            } else {
                // Add new section to the list
                $current_sections[] = $section;
                $sections_json = json_encode($current_sections);

                // Update adviser sections
                $query = "UPDATE users SET section = :sections WHERE id = :adviser_id AND role = 'adviser' AND department = :department";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':sections', $sections_json);
                $stmt->bindParam(':adviser_id', $adviser_id);
                $stmt->bindParam(':department', $department);

                if ($stmt->execute()) {
                    $message = "Section {$section} assigned successfully!";
                    $message_type = 'success';

                    // Add notification to adviser about assignment
                    $notification_query = "INSERT INTO notifications (user_id, title, message, type, category, created_at)
                                          VALUES (:user_id, :title, :message, :type, :category, NOW())";
                    $notification_stmt = $db->prepare($notification_query);
                    $notification_title = "Section Assignment";
                    $notification_message = "You have been assigned to section {$section}" . ($year_level ? " (Year {$year_level})" : "") . " by the department chairperson.";
                    $notification_type = "system";
                    $notification_category = "system_update";

                    $notification_stmt->bindParam(':user_id', $adviser_id);
                    $notification_stmt->bindParam(':title', $notification_title);
                    $notification_stmt->bindParam(':message', $notification_message);
                    $notification_stmt->bindParam(':type', $notification_type);
                    $notification_stmt->bindParam(':category', $notification_category);
                    $notification_stmt->execute();
                } else {
                    $message = 'Failed to assign section.';
                    $message_type = 'error';
                }
            }
        }
    } elseif ($action === 'remove_section') {
        $section_to_remove = $_POST['section_to_remove'] ?? '';

        if (empty($section_to_remove)) {
            $message = 'Section is required for removal.';
            $message_type = 'error';
        } else {
            // Get current adviser's sections
            $query = "SELECT section FROM users WHERE id = :adviser_id AND role = 'adviser' AND department = :department";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':adviser_id', $adviser_id);
            $stmt->bindParam(':department', $department);
            $stmt->execute();
            $current_adviser = $stmt->fetch(PDO::FETCH_ASSOC);

            $current_sections = [];
            if (!empty($current_adviser['section'])) {
                $sections_data = json_decode($current_adviser['section'], true);
                if (!is_array($sections_data)) {
                    $sections_data = array_map('trim', explode(',', $current_adviser['section']));
                }
                $current_sections = $sections_data;
            }

            // Remove section from the list
            $current_sections = array_filter($current_sections, function($s) use ($section_to_remove) {
                return $s !== $section_to_remove;
            });

            $sections_json = empty($current_sections) ? null : json_encode(array_values($current_sections));

            // Update adviser sections
            $query = "UPDATE users SET section = :sections WHERE id = :adviser_id AND role = 'adviser' AND department = :department";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':sections', $sections_json);
            $stmt->bindParam(':adviser_id', $adviser_id);
            $stmt->bindParam(':department', $department);

            if ($stmt->execute()) {
                $message = "Section {$section_to_remove} removed successfully!";
                $message_type = 'success';
            } else {
                $message = 'Failed to remove section.';
                $message_type = 'error';
            }
        }
    } elseif ($action === 'unassign_section') {
        $query = "UPDATE users SET section = NULL, year_level = NULL WHERE id = :adviser_id AND role = 'adviser' AND department = :department";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':adviser_id', $adviser_id);
        $stmt->bindParam(':department', $department);
        
        if ($stmt->execute()) {
            $message = 'Section assignment removed successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to remove section assignment.';
            $message_type = 'error';
        }
    }
}

// Get advisers in the department with their activity stats
$query = "SELECT u.*,
                 (SELECT COUNT(*) FROM grade_submissions gs
                  JOIN users s ON gs.user_id = s.id
                  WHERE gs.processed_by = u.id AND s.department = :department) as processed_submissions,
                 (SELECT COUNT(*) FROM honor_applications ha
                  JOIN users s ON ha.user_id = s.id
                  WHERE ha.reviewed_by = u.id AND s.department = :department) as reviewed_applications,
                 (SELECT COUNT(*) FROM users s WHERE s.role = 'student' AND s.department = :department AND s.status = 'active') as total_students
          FROM users u
          WHERE u.role = 'adviser' AND u.department = :department
          ORDER BY u.status DESC, u.last_name, u.first_name";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assigned sections for each adviser (using a JSON field or comma-separated sections)
foreach ($advisers as &$adviser) {
    // If sections are stored as JSON or comma-separated in the section field
    if (!empty($adviser['section'])) {
        // Try to decode as JSON first
        $sections = json_decode($adviser['section'], true);
        if (!is_array($sections)) {
            // If not JSON, treat as comma-separated
            $sections = array_map('trim', explode(',', $adviser['section']));
        }
        $adviser['assigned_sections'] = $sections;
    } else {
        $adviser['assigned_sections'] = [];
    }
}

// Get available sections in the department for assignment
$query = "SELECT DISTINCT section, year_level FROM users 
          WHERE role = 'student' AND department = :department AND section IS NOT NULL AND status = 'active'
          ORDER BY year_level, section";
$stmt = $db->prepare($query);
$stmt->bindParam(':department', $department);
$stmt->execute();
$available_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter advisers
$filter = $_GET['filter'] ?? 'all';
if ($filter !== 'all') {
    $advisers = array_filter($advisers, function($adviser) use ($filter) {
        return $adviser['status'] === $filter;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advisers - CTU Honor System</title>
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
                    <div class="w-8 h-8 bg-purple-600 rounded-xl flex items-center justify-center">
                        <i data-lucide="graduation-cap" class="w-5 h-5 text-white"></i>
                    </div>
                    <span class="ml-2 text-xl font-bold text-gray-900">CTU Honor</span>
                </div>
                
                <!-- User Profile -->
                <div class="mt-8 px-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                            <i data-lucide="crown" class="w-5 h-5 text-purple-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-semibold text-gray-900"><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></p>
                            <p class="text-xs text-gray-500">Chairperson</p>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="mt-8 flex-1 px-2 space-y-1">
                    <a href="dashboard.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="layout-dashboard" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Dashboard
                    </a>
                    <a href="applications.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="trophy" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Honor Applications
                    </a>
                    <a href="rankings.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="trophy" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Rankings
                    </a>
                    <a href="advisers.php" class="bg-purple-50 border-r-2 border-purple-600 text-purple-700 group flex items-center px-2 py-2 text-sm font-medium rounded-l-xl">
                        <i data-lucide="user-check" class="text-purple-500 mr-3 h-5 w-5"></i>
                        Advisers
                    </a>
                    <a href="reports.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="bar-chart-3" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Reports
                    </a>
                    <a href="settings.php" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-xl">
                        <i data-lucide="settings" class="text-gray-400 group-hover:text-gray-500 mr-3 h-5 w-5"></i>
                        Settings
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
                            <h1 class="text-2xl font-bold text-gray-900">Department Advisers</h1>
                            <p class="text-sm text-gray-500">Manage advisers in <?php echo $department; ?> department</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <select onchange="filterAdvisers(this.value)" 
                                class="px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Advisers</option>
                            <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <button onclick="addAdviser()" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors">
                            <i data-lucide="plus" class="w-4 h-4 inline mr-2"></i>
                            Add Adviser
                        </button>
                    </div>
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

                    <!-- Advisers Table -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
                        <?php if (empty($advisers)): ?>
                            <div class="text-center py-12">
                                <i data-lucide="user-check" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                                <h4 class="text-xl font-medium text-gray-900 mb-2">No advisers found</h4>
                                <p class="text-gray-500">No advisers match your current filter.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Adviser</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Section</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($advisers as $adviser): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                                                            <i data-lucide="user-check" class="w-5 h-5 text-green-600"></i>
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($adviser['first_name'] . ' ' . $adviser['last_name']); ?></div>
                                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($adviser['email']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $adviser['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                        <?php echo ucfirst($adviser['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <?php if (!empty($adviser['assigned_sections'])): ?>
                                                        <div class="flex flex-wrap gap-1">
                                                            <?php foreach ($adviser['assigned_sections'] as $section): ?>
                                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800 font-medium">
                                                                    <?php echo htmlspecialchars($section); ?>
                                                                    <button onclick="removeSection(<?php echo $adviser['id']; ?>, '<?php echo htmlspecialchars($section, ENT_QUOTES); ?>')"
                                                                            class="ml-1 text-green-600 hover:text-green-800" title="Remove section">
                                                                        <i data-lucide="x" class="w-3 h-3"></i>
                                                                    </button>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-orange-600 font-medium">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <div class="flex items-center space-x-2">
                                                        <!-- Section Assignment Button -->
                                                        <button onclick="showAssignSection(<?php echo $adviser['id']; ?>)"
                                                                class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors" title="Add Section">
                                                            <i data-lucide="plus" class="w-3 h-3"></i>
                                                        </button>

                                                        <?php if (!empty($adviser['assigned_sections'])): ?>
                                                            <button onclick="manageAdviser(<?php echo $adviser['id']; ?>, 'unassign_section')"
                                                                    class="bg-gray-600 hover:bg-gray-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors" title="Remove All Sections">
                                                                <i data-lucide="trash-2" class="w-3 h-3"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <!-- Email Button -->
                                                        <a href="mailto:<?php echo htmlspecialchars($adviser['email']); ?>"
                                                           class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors" title="Send Email">
                                                            <i data-lucide="mail" class="w-3 h-3"></i>
                                                        </a>

                                                        <!-- Activate/Deactivate Button -->
                                                        <?php if ($adviser['status'] === 'active'): ?>
                                                            <button onclick="manageAdviser(<?php echo $adviser['id']; ?>, 'deactivate')"
                                                                    class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors" title="Deactivate">
                                                                <i data-lucide="user-x" class="w-3 h-3"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button onclick="manageAdviser(<?php echo $adviser['id']; ?>, 'activate')"
                                                                    class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors" title="Activate">
                                                                <i data-lucide="user-check" class="w-3 h-3"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function filterAdvisers(filter) {
            window.location.href = 'advisers.php?filter=' + filter;
        }

        function manageAdviser(adviserId, action) {
            let actionText = '';
            let confirmMessage = '';
            
            switch(action) {
                case 'activate':
                    actionText = 'activate';
                    confirmMessage = `Are you sure you want to activate this adviser?`;
                    break;
                case 'deactivate':
                    actionText = 'deactivate';
                    confirmMessage = `Are you sure you want to deactivate this adviser?`;
                    break;
                case 'unassign_section':
                    actionText = 'unassign section from';
                    confirmMessage = `Are you sure you want to remove the section assignment from this adviser?`;
                    break;
            }
            
            if (confirm(confirmMessage)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="adviser_id" value="${adviserId}">
                    <input type="hidden" name="action" value="${action}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addAdviser() {
            // Implement add adviser functionality
            alert('Add adviser functionality will be implemented');
        }

        function showAssignSection(adviserId) {
            const modal = document.getElementById('assignSectionModal');
            const adviserIdInput = document.getElementById('assignAdviserId');
            const sectionSelect = document.getElementById('assignSection');
            const yearSelect = document.getElementById('assignYear');

            adviserIdInput.value = adviserId;
            sectionSelect.value = '';
            yearSelect.value = '';

            modal.style.display = 'flex';
        }

        function removeSection(adviserId, section) {
            if (confirm(`Are you sure you want to remove section "${section}" from this adviser?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="adviser_id" value="${adviserId}">
                    <input type="hidden" name="action" value="remove_section">
                    <input type="hidden" name="section_to_remove" value="${section}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function hideAssignSection() {
            document.getElementById('assignSectionModal').style.display = 'none';
        }

        function assignSection() {
            const adviserId = document.getElementById('assignAdviserId').value;
            const section = document.getElementById('assignSection').value;
            const yearLevel = document.getElementById('assignYear').value;
            
            if (!section) {
                alert('Please select a section');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="adviser_id" value="${adviserId}">
                <input type="hidden" name="action" value="assign_section">
                <input type="hidden" name="section" value="${section}">
                <input type="hidden" name="year_level" value="${yearLevel}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    </script>

    <!-- Section Assignment Modal -->
    <div id="assignSectionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Add Section</h3>
                <button onclick="hideAssignSection()" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label for="assignSection" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                    <select id="assignSection" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Select Section</option>
                        <?php foreach ($available_sections as $section): ?>
                            <option value="<?php echo htmlspecialchars($section['section']); ?>" data-year="<?php echo $section['year_level']; ?>">
                                <?php echo htmlspecialchars($section['section']); ?> (Year <?php echo $section['year_level']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="assignYear" class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                    <select id="assignYear" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Select Year</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                    </select>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button onclick="hideAssignSection()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button onclick="assignSection()" class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                    Add Section
                </button>
            </div>
            
            <input type="hidden" id="assignAdviserId" value="">
        </div>
    </div>
</body>
</html>
