<?php
// Ensure notification manager is initialized
if (!isset($notificationManager)) {
    $notificationManager = new NotificationManager($db);
}

// Get notifications for current user
$user_id = $_SESSION['user_id'];
$notifications = $notificationManager->getUserNotifications($user_id, 20);
$unread_count = $notificationManager->getUnreadCount($user_id);
?>

<!-- Notification Bell -->
<div class="relative">
    <button type="button" id="notificationButton" class="bg-white p-2 rounded-xl text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 relative">
        <i data-lucide="bell" class="h-6 w-6"></i>
        <?php if ($unread_count > 0): ?>
            <span class="absolute -top-1 -right-1 h-5 w-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">
                <?php echo $unread_count; ?>
            </span>
        <?php endif; ?>
    </button>

    <!-- Notification Dropdown Panel -->
    <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-200 z-50">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
        </div>
        <div class="max-h-96 overflow-y-auto">
            <?php if (empty($notifications)): ?>
                <div class="p-8 text-center">
                    <i data-lucide="bell-off" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                    <p class="text-sm text-gray-500">No notifications yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="p-4 border-b border-gray-100 hover:bg-gray-50 <?php echo $notification['is_read'] ? '' : 'bg-blue-50'; ?>">
                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notification['title']); ?></p>
                        <p class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                        <p class="text-xs text-gray-400 mt-2"><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if (!empty($notifications)): ?>
            <div class="p-3 border-t border-gray-200 text-center">
                <a href="notifications.php" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View all notifications</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Profile Icon -->
<div class="relative">
    <button type="button" id="profileButton" class="bg-white flex text-sm rounded-xl focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
        <div class="w-8 h-8 bg-purple-100 rounded-xl flex items-center justify-center">
            <i data-lucide="user" class="w-4 h-4 text-purple-600"></i>
        </div>
    </button>

    <!-- Profile Dropdown Menu -->
    <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-200 z-50">
        <div class="p-4 border-b border-gray-200">
            <p class="text-sm font-semibold text-gray-900"><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></p>
            <p class="text-xs text-gray-500"><?php echo $_SESSION['email']; ?></p>
        </div>
        <div class="py-2">
            <a href="dashboard.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                <i data-lucide="layout-dashboard" class="w-4 h-4 mr-3 text-gray-400"></i>
                Dashboard
            </a>
            <a href="applications.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                <i data-lucide="trophy" class="w-4 h-4 mr-3 text-gray-400"></i>
                Applications
            </a>
            <a href="settings.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                <i data-lucide="settings" class="w-4 h-4 mr-3 text-gray-400"></i>
                Settings
            </a>
        </div>
        <div class="border-t border-gray-200 py-2">
            <a href="../logout.php" class="flex items-center px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                <i data-lucide="log-out" class="w-4 h-4 mr-3 text-red-500"></i>
                Sign Out
            </a>
        </div>
    </div>
</div>

<script>
// Dropdown functionality (will be initialized after DOM loads)
document.addEventListener('DOMContentLoaded', function() {
    const notificationButton = document.getElementById('notificationButton');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const profileButton = document.getElementById('profileButton');
    const profileDropdown = document.getElementById('profileDropdown');

    if (notificationButton && notificationDropdown && profileButton && profileDropdown) {
        // Toggle notification dropdown
        notificationButton.addEventListener('click', function(e) {
            e.stopPropagation();

            // Mark all notifications as read if there are unread ones
            const unreadBadge = notificationButton.querySelector('span');
            if (unreadBadge && unreadBadge.textContent.trim() !== '') {
                fetch('../api/notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'mark_all_read'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the unread badge
                        if (unreadBadge) {
                            unreadBadge.remove();
                        }
                        // Update notification items to remove "new" styling
                        const notificationItems = notificationDropdown.querySelectorAll('.bg-blue-50');
                        notificationItems.forEach(item => {
                            item.classList.remove('bg-blue-50');
                        });
                    }
                })
                .catch(error => {
                    console.error('Error marking notifications as read:', error);
                });
            }

            notificationDropdown.classList.toggle('hidden');
            profileDropdown.classList.add('hidden');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });

        // Toggle profile dropdown
        profileButton.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
            notificationDropdown.classList.add('hidden');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationButton.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.add('hidden');
            }
            if (!profileButton.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.add('hidden');
            }
        });

        // Close dropdowns on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                notificationDropdown.classList.add('hidden');
                profileDropdown.classList.add('hidden');
            }
        });
    }
});
</script>
