<?php
/**
 * Highland Fresh - Top Navbar Component
 * 
 * Usage: include after sidebar.php
 */
?>

<!-- Top Navbar -->
<header class="navbar bg-base-100 border-b border-base-300 sticky top-0 z-30 px-4 lg:px-6">
    <!-- Mobile menu button -->
    <div class="flex-none lg:hidden">
        <button class="btn btn-ghost btn-square" onclick="toggleSidebar()">
            <i class="fas fa-bars text-lg"></i>
        </button>
    </div>
    
    <!-- Page title -->
    <div class="flex-1">
        <h1 class="text-lg lg:text-xl font-bold text-base-content" id="pageTitle">
            <?php echo $pageTitle ?? 'Dashboard'; ?>
        </h1>
    </div>
    
    <!-- Right side actions -->
    <div class="flex-none flex items-center gap-2">
        <!-- Search (desktop) -->
        <div class="hidden md:block">
            <div class="form-control">
                <label class="input input-bordered input-sm flex items-center gap-2 w-64">
                    <i class="fas fa-search text-base-content/40"></i>
                    <input type="text" placeholder="Search..." class="grow" />
                    <kbd class="kbd kbd-sm">⌘K</kbd>
                </label>
            </div>
        </div>
        
        <!-- Search (mobile) -->
        <button class="btn btn-ghost btn-circle md:hidden">
            <i class="fas fa-search"></i>
        </button>
        
        <!-- Notifications -->
        <div class="dropdown dropdown-end">
            <label tabindex="0" class="btn btn-ghost btn-circle">
                <div class="indicator">
                    <i class="fas fa-bell text-lg"></i>
                    <span class="indicator-item badge badge-primary badge-xs" id="notifCount"></span>
                </div>
            </label>
            <div tabindex="0" class="dropdown-content bg-base-100 rounded-box shadow-lg border border-base-300 w-80 mt-3">
                <div class="p-4 border-b border-base-300">
                    <div class="flex justify-between items-center">
                        <h3 class="font-semibold">Notifications</h3>
                        <a href="#" class="text-xs text-primary">Mark all read</a>
                    </div>
                </div>
                <ul class="menu menu-sm p-2 max-h-64 overflow-y-auto" id="notificationsList">
                    <li class="text-center py-4 text-base-content/60">
                        <span>No new notifications</span>
                    </li>
                </ul>
                <div class="p-2 border-t border-base-300">
                    <a href="#" class="btn btn-ghost btn-sm btn-block">View all notifications</a>
                </div>
            </div>
        </div>
        
        <!-- Theme toggle -->
        <label class="swap swap-rotate btn btn-ghost btn-circle">
            <input type="checkbox" id="themeToggle" />
            <i class="fas fa-sun swap-off text-lg"></i>
            <i class="fas fa-moon swap-on text-lg"></i>
        </label>
        
        <!-- User avatar (mobile) -->
        <div class="dropdown dropdown-end lg:hidden">
            <label tabindex="0" class="btn btn-ghost btn-circle avatar placeholder">
                <div class="w-10 rounded-full bg-primary text-primary-content">
                    <span id="navUserInitials">U</span>
                </div>
            </label>
            <ul tabindex="0" class="dropdown-content menu menu-sm bg-base-100 rounded-box shadow-lg border border-base-300 w-52 p-2 mt-3">
                <li class="menu-title px-2 py-1">
                    <span id="navUserName">User</span>
                </li>
                <li><a href="#"><i class="fas fa-user-cog w-4"></i> Profile</a></li>
                <li><a href="#"><i class="fas fa-cog w-4"></i> Settings</a></li>
                <li class="border-t border-base-300 mt-1 pt-1">
                    <a onclick="AuthService.logout()" class="text-error">
                        <i class="fas fa-sign-out-alt w-4"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>

<script>
// Theme toggle
document.getElementById('themeToggle').addEventListener('change', function() {
    document.documentElement.setAttribute('data-theme', this.checked ? 'forest' : 'emerald');
    localStorage.setItem('theme', this.checked ? 'forest' : 'emerald');
});

// Load saved theme
const savedTheme = localStorage.getItem('theme');
if (savedTheme === 'forest') {
    document.getElementById('themeToggle').checked = true;
    document.documentElement.setAttribute('data-theme', 'forest');
}
</script>
