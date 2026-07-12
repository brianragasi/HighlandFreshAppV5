/**
 * Highland Fresh — Warehouse Raw shared sidebar
 *
 * One nav structure for every raw warehouse page (including reports/).
 *
 * Include after auth.js / raw.service.js / api.js (needs APP_BASE):
 *   <script>window.WAREHOUSE_RAW_ACTIVE_PAGE = 'dashboard';</script>
 *   <script src=".../js/warehouse/raw-sidebar.js"></script>
 *   <script src=".../js/warehouse/sidebar.stats.js"></script>
 *
 * All nav hrefs and assets use absolute APP_BASE paths so links work from
 * both html/warehouse/raw/ and html/warehouse/raw/reports/ without relative
 * path bugs (which can look like a same-page “refresh”).
 */
(function () {
    'use strict';

    function appBase() {
        if (typeof APP_BASE === 'string' && APP_BASE.length) {
            return APP_BASE.replace(/\/$/, '');
        }
        // Fallback matches js/config/api.js local default
        const host = (window.location.hostname || '').toLowerCase();
        if (host === 'localhost' || host === '127.0.0.1') {
            return '/HighlandFreshAppV4';
        }
        return '';
    }

    /** Absolute base for raw warehouse HTML pages, e.g. /HighlandFreshAppV4/html/warehouse/raw/ */
    function rawBase() {
        return appBase() + '/html/warehouse/raw/';
    }

    /** Absolute base for html/ assets (logo, etc.) */
    function htmlBase() {
        return appBase() + '/html/';
    }

    const NAV = [
        {
            title: 'Main Menu',
            items: [
                { id: 'dashboard', label: 'Dashboard', icon: 'fa-th-large', href: 'dashboard.html' },
            ],
        },
        {
            title: 'Inventory',
            items: [
                { id: 'milk_storage', label: 'Milk Storage', icon: 'fa-tint', href: 'milk_storage.html', badgeId: 'pendingMilkBadge', badgeClass: 'badge-warning' },
                { id: 'ingredients', label: 'Ingredients', icon: 'fa-box-open', href: 'ingredients.html' },
                { id: 'mro', label: 'MRO Supplies', icon: 'fa-tools', href: 'mro.html' },
            ],
        },
        {
            title: 'Operations',
            items: [
                { id: 'requisitions', label: 'Requisitions', icon: 'fa-clipboard-check', href: 'requisitions.html', badgeId: 'pendingReqsBadge', badgeClass: 'badge-warning' },
                { id: 'purchase_requests', label: 'Purchase Requests', icon: 'fa-shopping-cart', href: 'purchase_requests.html' },
                { id: 'receive_deliveries', label: 'Receive Deliveries', icon: 'fa-truck-ramp-box', href: 'receive_deliveries.html' },
                { id: 'waste', label: 'Spoilage & Waste', icon: 'fa-trash-alt', href: 'waste.html' },
            ],
        },
        {
            title: 'Reports',
            items: [
                { id: 'reorder_alerts', label: 'Reorder Alerts', icon: 'fa-exclamation-triangle', href: 'reorder_alerts.html', badgeId: 'reorderAlertsBadge', badgeClass: 'badge-warning', iconAlwaysWarning: true },
                { id: 'inventory_report', label: 'Inventory Report', icon: 'fa-chart-bar', href: 'reports/inventory.html' },
                { id: 'movements', label: 'Stock Movements', icon: 'fa-exchange-alt', href: 'reports/movements.html' },
            ],
        },
    ];

    const PAGE_ALIASES = {
        'dashboard.html': 'dashboard',
        'milk_storage.html': 'milk_storage',
        'ingredients.html': 'ingredients',
        'mro.html': 'mro',
        'requisitions.html': 'requisitions',
        'purchase_requests.html': 'purchase_requests',
        'receive_deliveries.html': 'receive_deliveries',
        'waste.html': 'waste',
        'reorder_alerts.html': 'reorder_alerts',
        'inventory.html': 'inventory_report',
        'movements.html': 'movements',
    };

    function detectActivePage() {
        if (window.WAREHOUSE_RAW_ACTIVE_PAGE) {
            return window.WAREHOUSE_RAW_ACTIVE_PAGE;
        }
        // Prefer full path so nested reports/ pages never fall through to a wrong id
        const path = (window.location.pathname || '').replace(/\\/g, '/').toLowerCase();
        if (path.indexOf('/warehouse/raw/reports/inventory') !== -1) return 'inventory_report';
        if (path.indexOf('/warehouse/raw/reports/movements') !== -1) return 'movements';
        if (path.indexOf('/warehouse/raw/reorder_alerts') !== -1) return 'reorder_alerts';
        if (path.indexOf('/warehouse/raw/receive_deliveries') !== -1) return 'receive_deliveries';
        if (path.indexOf('/warehouse/raw/purchase_requests') !== -1) return 'purchase_requests';
        if (path.indexOf('/warehouse/raw/milk_storage') !== -1) return 'milk_storage';
        if (path.indexOf('/warehouse/raw/requisitions') !== -1) return 'requisitions';
        if (path.indexOf('/warehouse/raw/ingredients') !== -1) return 'ingredients';
        if (path.indexOf('/warehouse/raw/waste') !== -1) return 'waste';
        if (path.indexOf('/warehouse/raw/mro') !== -1) return 'mro';
        if (path.indexOf('/warehouse/raw/dashboard') !== -1) return 'dashboard';

        const file = path.split('/').pop() || '';
        return PAGE_ALIASES[file] || PAGE_ALIASES[file.toLowerCase()] || '';
    }

    /** Always absolute — works from raw/ and raw/reports/ alike. */
    function resolveHref(href) {
        const clean = String(href || '').replace(/^\//, '');
        return rawBase() + clean;
    }

    /**
     * Keep the active nav item visible inside the scrollable sidebar nav.
     * Reports items sit at the bottom; without this the nav resets to the top
     * (Dashboard) after every page load — feels like the menu "jumps up".
     * Only adjusts nav.scrollTop (never window.scrollIntoView).
     */
    function scrollActiveNavIntoView(sidebar) {
        if (!sidebar) return;
        const nav = sidebar.querySelector('nav');
        const active = sidebar.querySelector('[aria-current="page"]');
        if (!nav || !active) return;

        // Defer until layout is ready (flex + overflow measurements)
        requestAnimationFrame(() => {
            try {
                const navRect = nav.getBoundingClientRect();
                const activeRect = active.getBoundingClientRect();
                // Center active item in the nav viewport when possible
                const delta =
                    (activeRect.top + activeRect.height / 2) -
                    (navRect.top + navRect.height / 2);
                nav.scrollTop += delta;
            } catch (e) { /* ignore */ }
        });
    }

    function linkClass(isActive) {
        if (isActive) {
            return 'flex items-center gap-3 px-3 py-2.5 rounded-xl bg-primary text-primary-content font-semibold';
        }
        return 'flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-base-200';
    }

    function iconClass(item, isActive) {
        if (isActive) return `fas ${item.icon} w-5 text-center`;
        if (item.iconAlwaysWarning) return `fas ${item.icon} w-5 text-center text-warning`;
        return `fas ${item.icon} w-5 text-center text-base-content/60`;
    }

    function renderNav(activeId) {
        return NAV.map((section) => {
            const items = section.items.map((item) => {
                const isActive = item.id === activeId;
                const href = resolveHref(item.href);
                const badge = item.badgeId
                    ? `<span class="badge badge-sm ${isActive ? 'bg-white/20 text-white border-0' : (item.badgeClass || 'badge-ghost')}" id="${item.badgeId}">0</span>`
                    : '';
                // Use plain list (not DaisyUI .menu) so nested flex/badge markup
                // never intercepts navigation; links are real <a href> only.
                return `
                    <li>
                        <a href="${href}" class="${linkClass(isActive)}" data-nav-id="${item.id}"${isActive ? ' aria-current="page"' : ''}>
                            <i class="${iconClass(item, isActive)}"></i>
                            <span class="flex-1">${item.label}</span>
                            ${badge}
                        </a>
                    </li>`;
            }).join('');

            return `
            <div>
                <h2 class="text-xs font-semibold text-base-content/40 uppercase tracking-wider mb-2 px-3">${section.title}</h2>
                <ul class="p-0 m-0 list-none space-y-1">
                    ${items}
                </ul>
            </div>`;
        }).join('');
    }

    function renderSidebarHtml(activeId) {
        const img = htmlBase() + 'images/logo.jpg';
        return `
        <div class="p-4 border-b border-base-300">
            <div class="flex items-center gap-3">
                <img src="${img}" alt="Highland Fresh Logo" class="w-10 h-10 rounded-xl object-cover">
                <div class="flex-1 min-w-0">
                    <h1 class="font-bold text-base-content truncate">Highland Fresh</h1>
                    <p class="text-xs text-base-content/60 truncate">Warehouse Raw</p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm btn-square lg:hidden" onclick="typeof toggleSidebar==='function'&&toggleSidebar()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="grid grid-cols-4 gap-1 mt-3 pt-3 border-t border-base-200">
                <a href="${resolveHref('dashboard.html')}" class="flex flex-col items-center py-1.5 px-1 rounded-lg hover:bg-base-200" title="Raw Milk (L)">
                    <span class="text-base font-bold leading-none stat-value" id="sidebarStatMilk">0</span>
                    <span class="text-[9px] text-base-content/60 uppercase mt-0.5 tracking-wider">Milk</span>
                </a>
                <a href="${resolveHref('requisitions.html')}" class="flex flex-col items-center py-1.5 px-1 rounded-lg hover:bg-base-200" title="Pending Requisitions">
                    <span class="text-base font-bold leading-none stat-value" id="sidebarStatReq">0</span>
                    <span class="text-[9px] text-base-content/60 uppercase mt-0.5 tracking-wider">Req</span>
                </a>
                <a href="${resolveHref('reorder_alerts.html')}" class="flex flex-col items-center py-1.5 px-1 rounded-lg hover:bg-base-200" title="Low Stock Items">
                    <span class="text-base font-bold leading-none stat-value" id="sidebarStatLow">0</span>
                    <span class="text-[9px] text-base-content/60 uppercase mt-0.5 tracking-wider">Low</span>
                </a>
                <a href="${resolveHref('receive_deliveries.html')}" class="flex flex-col items-center py-1.5 px-1 rounded-lg hover:bg-base-200" title="Pending Deliveries">
                    <span class="text-base font-bold leading-none stat-value" id="sidebarStatDeliv">0</span>
                    <span class="text-[9px] text-base-content/60 uppercase mt-0.5 tracking-wider">Deliv</span>
                </a>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto p-4 space-y-6 scrollbar-thin">
            ${renderNav(activeId)}
        </nav>

        <div class="p-4 border-t border-base-300">
            <div class="flex items-center gap-3">
                <div class="avatar placeholder">
                    <div class="w-10 rounded-xl bg-primary text-primary-content">
                        <span class="text-sm font-semibold" id="userInitials">WR</span>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm truncate" id="sidebarUserName">Warehouse Staff</p>
                    <p class="text-xs text-base-content/60 truncate">Warehouse Raw</p>
                </div>
                <div class="dropdown dropdown-top dropdown-end">
                    <label tabindex="0" class="btn btn-ghost btn-sm btn-square">
                        <i class="fas fa-ellipsis-v"></i>
                    </label>
                    <ul tabindex="0" class="dropdown-content menu menu-sm bg-base-100 rounded-box shadow-lg border border-base-300 w-44 p-2 z-50">
                        <li>
                            <a href="#" role="button" onclick="event.preventDefault();typeof AuthService!=='undefined'&&AuthService.logout()" class="text-error">
                                <i class="fas fa-sign-out-alt w-4"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>`;
    }

    function fillUser() {
        try {
            if (typeof AuthService === 'undefined' || !AuthService.getCurrentUser) return;
            const user = AuthService.getCurrentUser();
            if (!user) return;
            const first = user.first_name || '';
            const last = user.last_name || '';
            const nameEl = document.getElementById('sidebarUserName');
            const initEl = document.getElementById('userInitials');
            if (nameEl) {
                nameEl.textContent = `${first} ${last}`.trim() || user.username || 'Warehouse Staff';
            }
            if (initEl) {
                const initials = ((first[0] || '') + (last[0] || '')).toUpperCase()
                    || (user.username || 'WR').slice(0, 2).toUpperCase();
                initEl.textContent = initials || 'WR';
            }
        } catch (e) { /* ignore */ }
    }

    function ensureBackdrop() {
        if (document.getElementById('sidebarBackdrop')) return;
        const backdrop = document.createElement('div');
        backdrop.id = 'sidebarBackdrop';
        backdrop.className = 'fixed inset-0 bg-black/50 z-40 lg:hidden hidden';
        backdrop.setAttribute('onclick', "typeof toggleSidebar==='function'&&toggleSidebar()");
        document.body.insertBefore(backdrop, document.body.firstChild);
    }

    function ensureToggleSidebar() {
        if (typeof window.toggleSidebar === 'function') return;
        window.toggleSidebar = function () {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar) sidebar.classList.toggle('-translate-x-full');
            if (backdrop) backdrop.classList.toggle('hidden');
        };
    }

    function mount() {
        ensureBackdrop();
        ensureToggleSidebar();

        let sidebar = document.getElementById('sidebar');
        if (!sidebar) {
            sidebar = document.createElement('aside');
            sidebar.id = 'sidebar';
            sidebar.className = 'fixed left-0 top-0 h-full w-72 bg-base-100 border-r border-base-300 z-50 transform -translate-x-full lg:translate-x-0 sidebar-transition flex flex-col';
            document.body.insertBefore(sidebar, document.body.firstChild);
        } else {
            sidebar.className = 'fixed left-0 top-0 h-full w-72 bg-base-100 border-r border-base-300 z-50 transform -translate-x-full lg:translate-x-0 sidebar-transition flex flex-col';
        }

        const activeId = detectActivePage();
        sidebar.innerHTML = renderSidebarHtml(activeId);
        fillUser();
        scrollActiveNavIntoView(sidebar);
        // Second pass after badges/stats may change layout heights
        setTimeout(() => scrollActiveNavIntoView(sidebar), 50);
        setTimeout(() => scrollActiveNavIntoView(sidebar), 350);

        function reloadStats() {
            if (typeof window.__warehouseRawReloadSidebarStats === 'function') {
                window.__warehouseRawReloadSidebarStats();
            }
        }
        setTimeout(reloadStats, 0);
        setTimeout(reloadStats, 300);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount);
    } else {
        mount();
    }

    window.WarehouseRawSidebar = { remount: mount, fillUser: fillUser };
})();
