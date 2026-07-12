/**
 * Highland Fresh — Production module shared sidebar
 *
 * Floor-first navigation: daily work up top, less-used tools under "More".
 * Include after auth.js / api.js (needs APP_BASE):
 *   <script>window.PRODUCTION_ACTIVE_PAGE = 'dashboard';</script>
 *   <script src="../../js/production/sidebar.js"></script>
 *
 * Uses absolute APP_BASE hrefs + scroll-active (same pattern as warehouse raw).
 */
(function () {
    'use strict';

    function appBase() {
        if (typeof APP_BASE === 'string' && APP_BASE.length) {
            return APP_BASE.replace(/\/$/, '');
        }
        const host = (window.location.hostname || '').toLowerCase();
        if (host === 'localhost' || host === '127.0.0.1') {
            return '/HighlandFreshAppV4';
        }
        return '';
    }

    function moduleBase() {
        return appBase() + '/html/production/';
    }

    function htmlBase() {
        return appBase() + '/html/';
    }

    function resolveHref(href) {
        const clean = String(href || '').replace(/^\//, '');
        return moduleBase() + clean;
    }

    const NAV = [
        {
            title: 'Main Menu',
            items: [
                { id: 'dashboard', label: 'Dashboard', icon: 'fa-th-large', href: 'dashboard.html' },
                { id: 'workbench', label: 'My Active Runs', icon: 'fa-screwdriver-wrench', href: 'run-workbench.html', badgeId: 'activeRunsBadge', badgeClass: 'badge-warning' },
            ],
        },
        {
            title: 'Daily Floor Work',
            items: [
                { id: 'ccp', label: 'CCP / Temperatures', icon: 'fa-thermometer-half', href: 'ccp_logging.html', badgeId: 'ccpAlertsBadge', badgeClass: 'badge-error' },
                { id: 'packaging', label: 'Packaging', icon: 'fa-boxes', href: 'packaging.html' },
                { id: 'requisitions', label: 'Request Materials', icon: 'fa-clipboard-list', href: 'requisitions.html', badgeId: 'pendingReqsBadge', badgeClass: 'badge-warning' },
            ],
        },
        {
            title: 'More',
            items: [
                { id: 'batches', label: 'All Batches', icon: 'fa-box', href: 'batches.html' },
                { id: 'pasteurization', label: 'Pasteurization', icon: 'fa-fire-alt', href: 'pasteurization.html', badgeId: 'pasteurizationBadge', badgeClass: 'badge-info' },
                { id: 'yield_tracking', label: 'Yield Tracking', icon: 'fa-chart-line', href: 'yield-tracking.html' },
                { id: 'reconciliation', label: 'Reconciliation', icon: 'fa-balance-scale', href: 'reconciliation.html' },
                { id: 'recipes', label: 'Recipes', icon: 'fa-book', href: 'recipes.html' },
                { id: 'byproducts', label: 'Byproducts', icon: 'fa-recycle', href: 'byproducts.html' },
            ],
        },
    ];

    const PAGE_ALIASES = {
        'dashboard.html': 'dashboard',
        'run-workbench.html': 'workbench',
        'batches.html': 'batches',
        'recipes.html': 'recipes',
        'pasteurization.html': 'pasteurization',
        'ccp_logging.html': 'ccp',
        'ccp.html': 'ccp',
        'yield-tracking.html': 'yield_tracking',
        'packaging.html': 'packaging',
        'reconciliation.html': 'reconciliation',
        'requisitions.html': 'requisitions',
        'byproducts.html': 'byproducts',
    };

    function detectActivePage() {
        if (window.PRODUCTION_ACTIVE_PAGE) {
            return window.PRODUCTION_ACTIVE_PAGE;
        }
        const path = (window.location.pathname || '').replace(/\\/g, '/').toLowerCase();
        if (path.indexOf('/production/run-workbench') !== -1) return 'workbench';
        if (path.indexOf('/production/ccp') !== -1) return 'ccp';
        if (path.indexOf('/production/yield-tracking') !== -1) return 'yield_tracking';
        if (path.indexOf('/production/packaging') !== -1) return 'packaging';
        if (path.indexOf('/production/requisitions') !== -1) return 'requisitions';
        if (path.indexOf('/production/pasteurization') !== -1) return 'pasteurization';
        if (path.indexOf('/production/reconciliation') !== -1) return 'reconciliation';
        if (path.indexOf('/production/byproducts') !== -1) return 'byproducts';
        if (path.indexOf('/production/batches') !== -1) return 'batches';
        if (path.indexOf('/production/recipes') !== -1) return 'recipes';
        if (path.indexOf('/production/dashboard') !== -1) return 'dashboard';
        const file = path.split('/').pop() || '';
        return PAGE_ALIASES[file] || PAGE_ALIASES[file.toLowerCase()] || '';
    }

    function linkClass(isActive) {
        if (isActive) {
            return 'flex items-center gap-3 px-3 py-2.5 rounded-xl bg-primary text-primary-content font-semibold';
        }
        return 'flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-base-200';
    }

    function iconClass(icon, isActive) {
        return isActive
            ? `fas ${icon} w-5 text-center`
            : `fas ${icon} w-5 text-center text-base-content/60`;
    }

    function scrollActiveNavIntoView(sidebar) {
        if (!sidebar) return;
        const nav = sidebar.querySelector('nav');
        const active = sidebar.querySelector('[aria-current="page"]');
        if (!nav || !active) return;
        requestAnimationFrame(() => {
            try {
                const navRect = nav.getBoundingClientRect();
                const activeRect = active.getBoundingClientRect();
                const delta =
                    (activeRect.top + activeRect.height / 2) -
                    (navRect.top + navRect.height / 2);
                nav.scrollTop += delta;
            } catch (e) { /* ignore */ }
        });
    }

    function renderNav(activeId) {
        return NAV.map((section) => {
            const items = section.items.map((item) => {
                const isActive = item.id === activeId;
                const badge = item.badgeId
                    ? `<span class="badge badge-sm ${item.badgeClass || 'badge-ghost'}" id="${item.badgeId}">0</span>`
                    : '';
                return `
                    <li>
                        <a href="${resolveHref(item.href)}" class="${linkClass(isActive)}" data-nav-id="${item.id}"${isActive ? ' aria-current="page"' : ''}>
                            <i class="${iconClass(item.icon, isActive)}"></i>
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
                    <p class="text-xs text-base-content/60 truncate">Production Floor</p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm btn-square lg:hidden" onclick="typeof toggleSidebar==='function'&&toggleSidebar()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto p-4 space-y-6 scrollbar-thin">
            ${renderNav(activeId)}
        </nav>

        <div class="p-4 border-t border-base-300">
            <div class="flex items-center gap-3">
                <div class="avatar placeholder">
                    <div class="w-10 rounded-xl bg-primary text-primary-content">
                        <span class="text-sm font-semibold" id="userInitials">PS</span>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm truncate" id="sidebarUserName">Production Staff</p>
                    <p class="text-xs text-base-content/60 truncate">Production</p>
                </div>
                <div class="dropdown dropdown-top dropdown-end">
                    <label tabindex="0" class="btn btn-ghost btn-sm btn-square">
                        <i class="fas fa-ellipsis-v"></i>
                    </label>
                    <ul tabindex="0" class="dropdown-content menu menu-sm bg-base-100 rounded-box shadow-lg border border-base-300 w-44 p-2 z-50">
                        <li class="border-t border-base-300 mt-1 pt-1">
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
                nameEl.textContent = `${first} ${last}`.trim() || user.username || 'Production Staff';
            }
            if (initEl) {
                const initials = ((first[0] || '') + (last[0] || '')).toUpperCase()
                    || (user.username || 'PS').slice(0, 2).toUpperCase();
                initEl.textContent = initials;
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
        const activeId = detectActivePage();
        let sidebar = document.getElementById('sidebar');

        if (!sidebar) {
            sidebar = document.createElement('aside');
            sidebar.id = 'sidebar';
            document.body.insertBefore(sidebar, document.body.firstChild);
        }

        sidebar.className = 'fixed left-0 top-0 h-full w-72 bg-base-100 border-r border-base-300 z-50 transform -translate-x-full lg:translate-x-0 sidebar-transition flex flex-col';
        sidebar.innerHTML = renderSidebarHtml(activeId);

        ensureBackdrop();
        ensureToggleSidebar();
        fillUser();
        scrollActiveNavIntoView(sidebar);
        setTimeout(() => scrollActiveNavIntoView(sidebar), 50);
        setTimeout(() => scrollActiveNavIntoView(sidebar), 350);
        if (window.HighlandSidebarNav && typeof HighlandSidebarNav.enhanceAll === 'function') {
            HighlandSidebarNav.enhanceAll();
        }
    }

    window.ProductionSidebar = {
        mount,
        fillUser,
        showBadge(id, value) {
            const el = document.getElementById(id);
            if (!el) return;
            const n = Number(value) || 0;
            el.textContent = String(n);
            el.style.display = n > 0 ? '' : 'none';
        },
        NAV,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount);
    } else {
        mount();
    }
})();
