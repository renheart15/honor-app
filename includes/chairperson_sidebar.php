<div class="hidden md:flex md:w-64 md:flex-col">
    <div class="flex flex-col flex-grow bg-white border-r border-gray-200 pt-5 pb-4 overflow-y-auto">
        <div class="flex items-center flex-shrink-0 px-4">
            <img src="../img/cebu-technological-university-seeklogo.png" alt="CTU Logo" class="w-8 h-8">
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
            <?php
            $current_page = basename($_SERVER['PHP_SELF']);
            $nav_items = [
                ['dashboard.php', 'layout-dashboard', 'Dashboard'],
                ['applications.php', 'trophy', 'Honor Applications'],
                ['periods.php', 'calendar', 'Academic Periods'],
                ['rankings.php', 'award', 'Rankings'],
                ['advisers.php', 'user-check', 'Advisers'],
                ['reports.php', 'bar-chart-3', 'Reports'],
                ['settings.php', 'settings', 'Settings']
            ];

            foreach ($nav_items as $item) {
                list($page, $icon, $label) = $item;
                $is_active = $current_page === $page;
                $active_classes = 'bg-purple-50 border-r-2 border-purple-600 text-purple-700';
                $inactive_classes = 'text-gray-600 hover:bg-gray-50 hover:text-gray-900';
                $classes = $is_active ? $active_classes : $inactive_classes;
                $icon_classes = $is_active ? 'text-purple-500' : 'text-gray-400 group-hover:text-gray-500';
                ?>
                <a href="<?php echo $page; ?>" class="<?php echo $classes; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-<?php echo $is_active ? 'l' : ''; ?>-xl">
                    <i data-lucide="<?php echo $icon; ?>" class="<?php echo $icon_classes; ?> mr-3 h-5 w-5"></i>
                    <?php echo $label; ?>
                </a>
                <?php
            }
            ?>
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
