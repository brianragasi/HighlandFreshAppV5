/**
 * Highland Fresh — canonical Sales module sidebar.
 *
 * One renderer owns menu order, labels, active state, user identity, and the
 * responsive drawer for every page under html/sales/ (including reports/).
 */
(function () {
    'use strict';

    if (window.SalesSidebar) return;

    function appBase() {
        if (typeof APP_BASE === 'string' && APP_BASE.length) {
            return APP_BASE.replace(/\/$/, '');
        }
        const host = (window.location.hostname || '').toLowerCase();
        return host === 'localhost' || host === '127.0.0.1'
            ? '/HighlandFreshAppV4'
            : '';
    }

    function salesBase() {
        return appBase() + '/html/sales/';
    }

    function htmlBase() {
        return appBase() + '/html/';
    }

    function resolveHref(href) {
        return salesBase() + String(href || '').replace(/^\//, '');
    }

    const NAV = [
        {
            title: 'Main Menu',
            items: [
                { id: 'dashboard', label: 'Dashboard', icon: 'fa-th-large', href: 'dashboard.html' },
            ],
        },
        {
            title: 'Customers',
            items: [
                { id: 'customers', label: 'Customer List', icon: 'fa-users', href: 'customers.html' },
            ],
        },
        {
            title: 'Orders',
            action: { id: 'direct_order', label: 'Record Customer Order', icon: 'fa-user-tag' },
            items: [
                { id: 'order_inbox', label: 'Customer PO Inbox', icon: 'fa-inbox', href: 'order_inbox.html' },
                { id: 'pending_orders', label: 'Pending Orders', icon: 'fa-clock', href: 'orders.html?status=pending', badgeId: 'pendingOrdersBadge', badgeClass: 'badge-warning' },
                { id: 'order_history', label: 'Order History', icon: 'fa-history', href: 'orders.html' },
            ],
        },
        {
            title: 'Receivables',
            items: [
                { id: 'aging', label: 'Aging Report', icon: 'fa-chart-pie', href: 'aging.html' },
                { id: 'collections', label: 'Collections Due', icon: 'fa-money-check-alt', href: 'collections.html' },
            ],
        },
        {
            title: 'Reports',
            items: [
                { id: 'sales_report', label: 'Sales Report', icon: 'fa-chart-line', href: 'reports/sales.html' },
                { id: 'customer_performance', label: 'Customer Performance', icon: 'fa-chart-column', href: 'reports/customer_performance.html' },
            ],
        },
    ];

    function routeState() {
        const path = (window.location.pathname || '').replace(/\\/g, '/').toLowerCase();
        const params = new URLSearchParams(window.location.search);
        const directOrderOpen = path.endsWith('/sales/orders.html') && ['record', 'direct'].includes(params.get('action'));

        if (directOrderOpen) return { activeId: '', directOrderOpen: true };
        if (path.endsWith('/sales/dashboard.html')) return { activeId: 'dashboard', directOrderOpen: false };
        if (path.endsWith('/sales/customers.html')) return { activeId: 'customers', directOrderOpen: false };
        if (path.endsWith('/sales/wholesalers.html')) return { activeId: 'customers', directOrderOpen: false };
        if (path.endsWith('/sales/order_inbox.html')) return { activeId: 'order_inbox', directOrderOpen: false };
        if (path.endsWith('/sales/aging.html')) return { activeId: 'aging', directOrderOpen: false };
        if (path.endsWith('/sales/collections.html')) return { activeId: 'collections', directOrderOpen: false };
        if (path.endsWith('/sales/reports/sales.html')) return { activeId: 'sales_report', directOrderOpen: false };
        if (path.endsWith('/sales/reports/customer_performance.html')) return { activeId: 'customer_performance', directOrderOpen: false };
        if (path.endsWith('/sales/orders.html')) {
            return {
                activeId: params.get('status') === 'pending' ? 'pending_orders' : 'order_history',
                directOrderOpen: false,
            };
        }
        return { activeId: '', directOrderOpen: false };
    }

    function linkClass(active) {
        return active
            ? 'flex items-center gap-3 px-3 py-2.5 rounded-xl bg-primary text-primary-content font-semibold'
            : 'flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-base-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary';
    }

    function iconClass(icon, active) {
        return active
            ? `fas ${icon} w-5 text-center`
            : `fas ${icon} w-5 text-center text-base-content/60`;
    }

    function renderNav(state) {
        return NAV.map((section) => {
            const action = section.action ? `
                <button type="button"
                        class="w-full flex items-center gap-3 px-3 py-2.5 mb-1 rounded-xl border ${state.directOrderOpen ? 'bg-primary text-primary-content border-primary font-semibold' : 'border-primary/30 text-primary hover:bg-primary/10'} focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        data-sales-action="direct-order"
                        aria-pressed="${state.directOrderOpen ? 'true' : 'false'}">
                    <i class="fas ${section.action.icon} w-5 text-center"></i>
                    <span class="flex-1 text-left">${section.action.label}</span>
                    <i class="fas fa-plus text-xs" aria-hidden="true"></i>
                </button>` : '';

            const items = section.items.map((item) => {
                const active = item.id === state.activeId;
                const badge = item.badgeId
                    ? `<span class="badge badge-sm ${item.badgeClass || 'badge-ghost'} hidden" id="${item.badgeId}">0</span>`
                    : '';
                return `
                    <li>
                        <a href="${resolveHref(item.href)}" class="${linkClass(active)}" data-nav-id="${item.id}"${active ? ' aria-current="page"' : ''}>
                            <i class="${iconClass(item.icon, active)}"></i>
                            <span class="flex-1">${item.label}</span>
                            ${badge}
                        </a>
                    </li>`;
            }).join('');

            return `
                <section aria-labelledby="sales-nav-${section.title.toLowerCase().replace(/[^a-z]+/g, '-')}">
                    <h2 id="sales-nav-${section.title.toLowerCase().replace(/[^a-z]+/g, '-')}" class="text-xs font-semibold text-base-content/60 uppercase tracking-wider mb-2 px-3">${section.title}</h2>
                    ${action}
                    <ul class="p-0 m-0 list-none space-y-1">${items}</ul>
                </section>`;
        }).join('');
    }

    function renderSidebar(state) {
        return `
            <div class="p-4 border-b border-base-300">
                <div class="flex items-center gap-3">
                    <img src="${htmlBase()}images/logo.jpg" alt="Highland Fresh Logo" class="w-10 h-10 rounded-xl object-cover">
                    <div class="flex-1 min-w-0">
                        <h1 class="font-bold text-base-content truncate">Highland Fresh</h1>
                        <p class="text-xs text-base-content/60 truncate">Sales Custodian</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm btn-square lg:hidden" data-sales-sidebar-close aria-label="Close Sales navigation">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <nav aria-label="Sales navigation" class="flex-1 overflow-y-auto p-4 space-y-6">
                ${renderNav(state)}
            </nav>

            <div class="p-4 border-t border-base-300">
                <div class="flex items-center gap-3">
                    <div class="avatar placeholder">
                        <div class="w-10 rounded-xl bg-primary text-primary-content">
                            <span class="text-sm font-semibold" id="userInitials">SC</span>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm truncate" id="sidebarUserName">Sales Custodian</p>
                        <p class="text-xs text-base-content/60 truncate">Sales &amp; Receivables</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm btn-square text-error" data-sales-logout aria-label="Log out">
                        <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                    </button>
                </div>
            </div>`;
    }

    let lastTrigger = null;
    let badgeLoadedAt = 0;

    function isDesktop() {
        return window.matchMedia('(min-width: 1024px)').matches;
    }

    function setDrawer(open, options = {}) {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (!sidebar || !backdrop) return;

        const shouldOpen = isDesktop() ? true : Boolean(open);
        sidebar.classList.toggle('-translate-x-full', !shouldOpen);
        backdrop.classList.toggle('hidden', !shouldOpen || isDesktop());
        document.querySelectorAll('[data-sales-sidebar-toggle]').forEach((button) => {
            button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        });

        if (!isDesktop()) {
            document.body.classList.toggle('overflow-hidden', shouldOpen);
        } else {
            document.body.classList.remove('overflow-hidden');
        }

        if (shouldOpen && !isDesktop() && options.focus !== false) {
            sidebar.querySelector('[data-sales-sidebar-close]')?.focus();
        } else if (!shouldOpen && options.restoreFocus !== false && lastTrigger) {
            lastTrigger.focus();
        }
    }

    function toggle(trigger) {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        if (trigger instanceof HTMLElement) lastTrigger = trigger;
        setDrawer(sidebar.classList.contains('-translate-x-full'));
    }

    function fillUser() {
        try {
            if (typeof AuthService === 'undefined' || !AuthService.getCurrentUser) return;
            const user = AuthService.getCurrentUser();
            if (!user) return;
            const first = user.first_name || '';
            const last = user.last_name || '';
            const name = `${first} ${last}`.trim() || user.username || 'Sales Custodian';
            const initials = ((first[0] || '') + (last[0] || '')).toUpperCase()
                || (user.username || 'SC').slice(0, 2).toUpperCase();
            document.getElementById('sidebarUserName').textContent = name;
            document.getElementById('userInitials').textContent = initials;
        } catch (error) { /* identity fallback is already rendered */ }
    }

    function openDirectOrder() {
        const path = (window.location.pathname || '').replace(/\\/g, '/').toLowerCase();
        if (path.endsWith('/sales/orders.html') && typeof window.openDirectOrderFromNavigation === 'function') {
            window.openDirectOrderFromNavigation();
            return;
        }
        window.location.href = resolveHref('orders.html?action=record');
    }

    function wireControls(sidebar, backdrop) {
        sidebar.querySelector('[data-sales-sidebar-close]')?.addEventListener('click', () => setDrawer(false));
        sidebar.querySelector('[data-sales-action="direct-order"]')?.addEventListener('click', openDirectOrder);
        sidebar.querySelector('[data-sales-logout]')?.addEventListener('click', () => {
            if (typeof AuthService !== 'undefined') AuthService.logout();
        });
        backdrop.addEventListener('click', () => setDrawer(false));

        document.querySelectorAll('[onclick*="toggleSidebar"], [data-sales-sidebar-toggle]').forEach((button) => {
            if (!(button instanceof HTMLElement) || button.closest('#sidebar')) return;
            button.dataset.salesSidebarToggle = '';
            button.setAttribute('aria-label', button.getAttribute('aria-label') || 'Open Sales navigation');
            button.setAttribute('aria-controls', 'sidebar');
            button.setAttribute('aria-expanded', isDesktop() ? 'true' : 'false');
        });
    }

    function scrollActiveIntoView(sidebar) {
        const nav = sidebar.querySelector('nav');
        const active = sidebar.querySelector('[aria-current="page"]');
        if (!nav || !active) return;
        requestAnimationFrame(() => {
            const navRect = nav.getBoundingClientRect();
            const activeRect = active.getBoundingClientRect();
            if (activeRect.top < navRect.top || activeRect.bottom > navRect.bottom) {
                nav.scrollTop += (activeRect.top + activeRect.height / 2) - (navRect.top + navRect.height / 2);
            }
        });
    }

    function mount() {
        document.getElementById('sidebar')?.remove();
        document.getElementById('sidebarBackdrop')?.remove();

        const backdrop = document.createElement('button');
        backdrop.type = 'button';
        backdrop.id = 'sidebarBackdrop';
        const isReport = (window.location.pathname || '').replace(/\\/g, '/').toLowerCase().includes('/sales/reports/');
        backdrop.className = `fixed inset-0 bg-black/50 z-40 lg:hidden hidden${isReport ? ' no-print' : ''}`;
        backdrop.setAttribute('aria-label', 'Close Sales navigation');

        const sidebar = document.createElement('aside');
        sidebar.id = 'sidebar';
        sidebar.dataset.navManaged = 'true';
        sidebar.className = `fixed left-0 top-0 h-full w-72 bg-base-100 border-r border-base-300 z-50 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col${isReport ? ' no-print' : ''}`;
        sidebar.innerHTML = renderSidebar(routeState());

        document.body.insertBefore(backdrop, document.body.firstChild);
        document.body.insertBefore(sidebar, backdrop.nextSibling);
        wireControls(sidebar, backdrop);
        fillUser();
        scrollActiveIntoView(sidebar);
    }

    function setBadge(id, value) {
        const badge = document.getElementById(id);
        if (!badge) return;
        const number = Math.max(0, Number(value) || 0);
        badge.textContent = String(number);
        badge.classList.toggle('hidden', number === 0);
    }

    async function refreshBadges(force = false) {
        if (!force && Date.now() - badgeLoadedAt < 30000) return;
        if (typeof SalesService === 'undefined' || !SalesService.getDashboardStats) return;
        badgeLoadedAt = Date.now();
        try {
            const response = await SalesService.getDashboardStats();
            setBadge('pendingOrdersBadge', response?.data?.pending_orders || 0);
        } catch (error) {
            // Navigation remains usable when a badge request is unavailable.
        }
    }

    function refresh() {
        const oldBadges = {};
        document.querySelectorAll('#sidebar [id$="Badge"]').forEach((badge) => {
            oldBadges[badge.id] = { text: badge.textContent, hidden: badge.classList.contains('hidden') };
        });
        mount();
        Object.entries(oldBadges).forEach(([id, value]) => {
            const badge = document.getElementById(id);
            if (!badge) return;
            badge.textContent = value.text;
            badge.classList.toggle('hidden', value.hidden);
        });
    }

    window.toggleSidebar = function (trigger) {
        toggle(trigger || document.activeElement);
    };

    window.SalesSidebar = { mount, refresh, setBadge, refreshBadges, setDrawer, routeState, NAV };

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !isDesktop()) setDrawer(false);
    });
    window.addEventListener('resize', () => setDrawer(isDesktop(), { focus: false, restoreFocus: false }));

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            mount();
            refreshBadges();
        });
    } else {
        mount();
        refreshBadges();
    }
})();
