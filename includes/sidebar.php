<?php
/**
 * Highland Fresh - Sidebar Component
 * 
 * Usage: include this file and set $currentModule and $currentPage
 * Example: $currentModule = 'warehouse_raw'; $currentPage = 'dashboard';
 */

// Get user info from session/token (in real app, this comes from auth)
$userRole = $userRole ?? 'staff';
$userName = $userName ?? 'User';
$userInitials = $userInitials ?? 'U';

// Define navigation by role
$roleNavigation = [
    'warehouse_raw' => [
        'title' => 'Warehouse Raw',
        'icon' => 'fa-warehouse',
        'base' => 'warehouse/raw',
        'sections' => [
            'main' => [
                'title' => 'Main Menu',
                'items' => [
                    ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-th-large', 'href' => 'dashboard.html'],
                ]
            ],
            'inventory' => [
                'title' => 'Inventory',
                'items' => [
                    ['id' => 'milk_storage', 'label' => 'Milk Storage', 'icon' => 'fa-tint', 'href' => 'milk_storage.html', 'badge' => 'pendingMilk'],
                    ['id' => 'ingredients', 'label' => 'Ingredients', 'icon' => 'fa-box-open', 'href' => 'ingredients.html'],
                    ['id' => 'mro', 'label' => 'MRO Supplies', 'icon' => 'fa-tools', 'href' => 'mro.html'],
                ]
            ],
            'operations' => [
                'title' => 'Operations',
                'items' => [
                    ['id' => 'requisitions', 'label' => 'Requisitions', 'icon' => 'fa-clipboard-check', 'href' => 'requisitions.html', 'badge' => 'pendingReqs'],
                    ['id' => 'receive', 'label' => 'Receive Stock', 'icon' => 'fa-truck-loading', 'href' => 'receive.html'],
                ]
            ],
            'reports' => [
                'title' => 'Reports',
                'items' => [
                    ['id' => 'inventory_report', 'label' => 'Inventory Report', 'icon' => 'fa-chart-bar', 'href' => 'reports/inventory.html'],
                    ['id' => 'movements', 'label' => 'Stock Movements', 'icon' => 'fa-exchange-alt', 'href' => 'reports/movements.html'],
                ]
            ],
        ]
    ],
    'qc_officer' => [
        'title' => 'Quality Control',
        'icon' => 'fa-flask',
        'base' => 'qc',
        'sections' => [
            'main' => [
                'title' => 'Main Menu',
                'items' => [
                    ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-th-large', 'href' => 'dashboard.html'],
                ]
            ],
            'receiving' => [
                'title' => 'Milk Receiving',
                'items' => [
                    ['id' => 'receive', 'label' => 'Receive Milk', 'icon' => 'fa-truck', 'href' => 'receive.html'],
                    ['id' => 'grading', 'label' => 'Grading', 'icon' => 'fa-star', 'href' => 'grading.html'],
                    ['id' => 'farmers', 'label' => 'Farmers', 'icon' => 'fa-user-tie', 'href' => 'farmers.html'],
                ]
            ],
            'batches' => [
                'title' => 'Batch Management',
                'items' => [
                    ['id' => 'batches', 'label' => 'All Batches', 'icon' => 'fa-boxes-stacked', 'href' => 'batches.html'],
                    ['id' => 'release', 'label' => 'Batch Release', 'icon' => 'fa-check-double', 'href' => 'release.html'],
                    ['id' => 'expiry', 'label' => 'Expiry Mgmt', 'icon' => 'fa-calendar-times', 'href' => 'expiry.html'],
                ]
            ],
        ]
    ],
    'production_staff' => [
        'title' => 'Production',
        'icon' => 'fa-industry',
        'base' => 'production',
        'sections' => [
            'main' => [
                'title' => 'Main Menu',
                'items' => [
                    ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-th-large', 'href' => 'dashboard.html'],
                ]
            ],
            'production' => [
                'title' => 'Production',
                'items' => [
                    ['id' => 'batches', 'label' => 'Batches', 'icon' => 'fa-boxes-stacked', 'href' => 'batches.html'],
                    ['id' => 'recipes', 'label' => 'Recipes', 'icon' => 'fa-book', 'href' => 'recipes.html'],
                    ['id' => 'ccp', 'label' => 'CCP Logging', 'icon' => 'fa-temperature-high', 'href' => 'ccp.html'],
                ]
            ],
            'materials' => [
                'title' => 'Materials',
                'items' => [
                    ['id' => 'requisitions', 'label' => 'Requisitions', 'icon' => 'fa-clipboard-list', 'href' => 'requisitions.html'],
                    ['id' => 'byproducts', 'label' => 'Byproducts', 'icon' => 'fa-recycle', 'href' => 'byproducts.html'],
                ]
            ],
        ]
    ],
];

// Get navigation for current role
$nav = $roleNavigation[$userRole] ?? $roleNavigation['warehouse_raw'];
?>

<!-- Mobile Sidebar Backdrop -->
<div id="sidebarBackdrop" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<aside id="sidebar" class="fixed left-0 top-0 h-full w-72 bg-base-100 border-r border-base-300 z-50 transform -translate-x-full lg:translate-x-0 sidebar-transition flex flex-col">
    
    <!-- Sidebar Header -->
    <div class="p-4 border-b border-base-300">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                <i class="fas <?php echo $nav['icon']; ?> text-primary text-lg"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="font-bold text-base-content truncate">Highland Fresh</h1>
                <p class="text-xs text-base-content/60 truncate"><?php echo $nav['title']; ?></p>
            </div>
            <!-- Mobile close button -->
            <button class="btn btn-ghost btn-sm btn-square lg:hidden" onclick="toggleSidebar()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto p-4 space-y-6 scrollbar-thin">
        <?php foreach ($nav['sections'] as $sectionKey => $section): ?>
        <div>
            <h2 class="text-xs font-semibold text-base-content/40 uppercase tracking-wider mb-2 px-3">
                <?php echo $section['title']; ?>
            </h2>
            <ul class="menu menu-sm p-0 gap-1">
                <?php foreach ($section['items'] as $item): 
                    $isActive = ($currentPage ?? '') === $item['id'];
                ?>
                <li>
                    <a href="<?php echo $item['href']; ?>" 
                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?php echo $isActive ? 'bg-primary text-primary-content' : 'hover:bg-base-200'; ?>">
                        <i class="fas <?php echo $item['icon']; ?> w-5 text-center <?php echo $isActive ? '' : 'text-base-content/60'; ?>"></i>
                        <span class="flex-1"><?php echo $item['label']; ?></span>
                        <?php if (isset($item['badge'])): ?>
                        <span class="badge badge-sm <?php echo $isActive ? 'badge-primary-content' : 'badge-warning'; ?>" id="<?php echo $item['badge']; ?>Badge">0</span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    </nav>
    
    <!-- User Section -->
    <div class="p-4 border-t border-base-300">
        <div class="flex items-center gap-3">
            <div class="avatar placeholder">
                <div class="w-10 rounded-xl bg-primary text-primary-content">
                    <span class="text-sm font-semibold" id="userInitials"><?php echo $userInitials; ?></span>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-sm truncate" id="sidebarUserName"><?php echo $userName; ?></p>
                <p class="text-xs text-base-content/60 truncate"><?php echo $nav['title']; ?></p>
            </div>
            <div class="dropdown dropdown-top dropdown-end">
                <label tabindex="0" class="btn btn-ghost btn-sm btn-square">
                    <i class="fas fa-ellipsis-v"></i>
                </label>
                <ul tabindex="0" class="dropdown-content menu menu-sm bg-base-100 rounded-box shadow-lg border border-base-300 w-44 p-2">
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
    </div>
</aside>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    
    sidebar.classList.toggle('-translate-x-full');
    backdrop.classList.toggle('hidden');
}
</script>
