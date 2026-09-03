/**
 * Highland Fresh — canonical Purchasing module sidebar.
 *
 * This renderer owns the menu order, labels, active page, user identity, and
 * responsive drawer behavior for every interactive page under html/purchasing/.
 */
(function () {
    'use strict';

    if (window.PurchasingSidebar) return;

    function appBase() {
        if (typeof APP_BASE === 'string' && APP_BASE.length) {
            return APP_BASE.replace(/\/$/, '');
        }
        const host = (window.location.hostname || '').toLowerCase();
        return host === 'localhost' || host === '127.0.0.1'
            ? '/HighlandFreshAppV4'
            : '';
    }

    function purchasingBase() {
        return appBase() + '/html/purchasing/';
    }

    function htmlBase() {
        return appBase() + '/html/';
    }

    function resolveHref(href) {
        return purchasingBase() + String(href || '').replace(/^\//, '');
    }

    const NAV = [
        {
            title: 'Main Menu',
            items: [
                { id: 'dashboard', label: 'Dashboard', icon: 'fa-th-large', href: 'dashboard.html' },
            ],
        },
        {
            title: 'Procurement',
            items: [
                { id: 'purchase_orders', label: 'Purchase Orders', icon: 'fa-file-invoice-dollar', href: 'purchase_orders.html', elementId: 'navPurchaseOrders', badgeId: 'pendingPOBadge', badgeClass: 'border-warning bg-warning/10 text-base-content' },
                { id: 'approved_suppliers', label: 'Approved Suppliers', icon: 'fa-truck-field', href: 'suppliers.html' },
            ],
        },
        {
            title: 'Inventory Alerts',
            items: [
                { id: 'requisitions', label: 'Requisitions', icon: 'fa-clipboard-list', href: 'dashboard.html#requisitionsSection', badgeId: 'requisitionBadge', badgeClass: 'badge-info' },
            ],
        },
    ];

    function routeState() {
        const path = (window.location.pathname || '').replace(/\\/g, '/').toLowerCase();
        const hash = (window.location.hash || '').toLowerCase();

        if (path.endsWith('/purchasing/canvassing.html')) return { activeId: 'purchase_orders' };
        if (path.endsWith('/purchasing/purchase_orders.html')) {
            return { activeId: 'purchase_orders' };
        }
        if (path.endsWith('/purchasing/suppliers.html')) return { activeId: 'approved_suppliers' };
        if (path.endsWith('/purchasing/dashboard.html')) {
            return { activeId: hash === '#requisitionssection' ? 'requisitions' : 'dashboard' };
        }
        return { activeId: '' };
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
            const items = section.items.map((item) => {
                const active = item.id === state.activeId;
                const badge = item.badgeId
                    ? `<span class="badge badge-sm ${item.badgeClass || 'badge-ghost'}" id="${item.badgeId}">0</span>`
                    : '';
                return `
                    <li>
                        <a href="${resolveHref(item.href)}"
                           class="${linkClass(active)}"
                           data-nav-id="${item.id}"
                           ${item.elementId ? `id="${item.elementId}"` : ''}
                           ${active ? 'aria-current="page"' : ''}>
                            <i class="${iconClass(item.icon, active)}" aria-hidden="true"></i>
                            <span class="flex-1">${item.label}</span>
                            ${badge}
                        </a>
                    </li>`;
            }).join('');

            const sectionId = `purchasing-nav-${section.title.toLowerCase().replace(/[^a-z]+/g, '-')}`;
            return `
                <section aria-labelledby="${sectionId}">
                    <h2 id="${sectionId}" class="text-xs font-semibold text-base-content/60 uppercase tracking-wider mb-2 px-3">${section.title}</h2>
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
                        <p class="text-xs text-base-content/60 truncate">Purchasing Department</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm btn-square lg:hidden" data-purchasing-sidebar-close aria-label="Close Purchasing navigation">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <nav aria-label="Purchasing navigation" class="flex-1 overflow-y-auto p-4 space-y-6 scrollbar-thin">
                ${renderNav(state)}
            </nav>

            <div class="p-4 border-t border-base-300">
                <div class="flex items-center gap-3">
                    <div class="avatar placeholder">
                        <div class="w-10 rounded-xl bg-primary text-primary-content">
                            <span class="text-sm font-semibold" id="userInitials">PU</span>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm truncate" id="sidebarUserName">Purchaser</p>
                        <p class="text-xs text-base-content/60 truncate">Purchasing</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm btn-square text-error" data-purchasing-logout aria-label="Log out">
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
        document.querySelectorAll('[data-purchasing-sidebar-toggle]').forEach((button) => {
            button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        });
        document.body.classList.toggle('overflow-hidden', shouldOpen && !isDesktop());

        if (shouldOpen && !isDesktop() && options.focus !== false) {
            sidebar.querySelector('[data-purchasing-sidebar-close]')?.focus();
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
            const name = `${first} ${last}`.trim() || user.full_name || user.username || 'Purchaser';
            const initials = ((first[0] || '') + (last[0] || '')).toUpperCase()
                || (user.username || 'PU').slice(0, 2).toUpperCase();
            document.getElementById('sidebarUserName').textContent = name;
            document.getElementById('userInitials').textContent = initials;
        } catch (error) {
            // The rendered fallback identity keeps navigation usable.
        }
    }

    function wireControls(sidebar, backdrop) {
        sidebar.querySelector('[data-purchasing-sidebar-close]')?.addEventListener('click', () => setDrawer(false));
        sidebar.querySelector('[data-purchasing-logout]')?.addEventListener('click', () => {
            if (typeof AuthService !== 'undefined') AuthService.logout();
        });
        backdrop.addEventListener('click', () => setDrawer(false));

        document.querySelectorAll('[onclick*="toggleSidebar"], [data-purchasing-sidebar-toggle]').forEach((button) => {
            if (!(button instanceof HTMLElement) || button.closest('#sidebar')) return;
            button.dataset.purchasingSidebarToggle = '';
            button.setAttribute('aria-label', button.getAttribute('aria-label') || 'Open Purchasing navigation');
            button.setAttribute('aria-controls', 'sidebar');
            button.setAttribute('aria-expanded', isDesktop() ? 'true' : 'false');
        });
    }

    function mount() {
        document.getElementById('sidebar')?.remove();
        document.getElementById('sidebarBackdrop')?.remove();

        const backdrop = document.createElement('button');
        backdrop.type = 'button';
        backdrop.id = 'sidebarBackdrop';
        backdrop.className = 'fixed inset-0 bg-black/50 z-40 lg:hidden hidden';
        backdrop.setAttribute('aria-label', 'Close Purchasing navigation');

        const sidebar = document.createElement('aside');
        sidebar.id = 'sidebar';
        sidebar.dataset.navManaged = 'true';
        sidebar.className = 'fixed left-0 top-0 h-full w-72 bg-base-100 border-r border-base-300 z-50 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col';
        sidebar.innerHTML = renderSidebar(routeState());

        document.body.insertBefore(backdrop, document.body.firstChild);
        document.body.insertBefore(sidebar, backdrop.nextSibling);
        wireControls(sidebar, backdrop);
        fillUser();
    }

    function setBadge(id, value) {
        const badge = document.getElementById(id);
        if (!badge) return;
        badge.textContent = String(Math.max(0, Number(value) || 0));
    }

    async function refreshBadges(force = false) {
        if (!force && Date.now() - badgeLoadedAt < 30000) return;
        if (typeof PurchasingService === 'undefined' || !PurchasingService.getDashboardStats) return;
        badgeLoadedAt = Date.now();
        try {
            const response = await PurchasingService.getDashboardStats();
            const stats = response?.data || {};
            setBadge('pendingPOBadge', stats.pending_pos);
            setBadge('requisitionBadge', stats.pending_requisitions ?? stats.prs_inbox);
        } catch (error) {
            // Navigation remains usable when badge data is unavailable.
        }
    }

    function setActive(activeId) {
        document.querySelectorAll('#sidebar [data-nav-id]').forEach((link) => {
            const active = link.dataset.navId === activeId;
            link.className = linkClass(active);
            if (active) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
            const icon = link.querySelector('i');
            if (icon) icon.className = iconClass(icon.className.match(/fa-[\w-]+/)?.[0] || 'fa-circle', active);
        });
    }

    function refresh() {
        const badges = {};
        document.querySelectorAll('#sidebar [id$="Badge"]').forEach((badge) => {
            badges[badge.id] = badge.textContent;
        });
        mount();
        Object.entries(badges).forEach(([id, value]) => setBadge(id, value));
    }

    window.toggleSidebar = function (trigger) {
        toggle(trigger || document.activeElement);
    };

    window.PurchasingSidebar = { NAV, mount, refresh, refreshBadges, routeState, setActive, setBadge, setDrawer };

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !isDesktop()) setDrawer(false);
    });
    window.addEventListener('resize', () => setDrawer(isDesktop(), { focus: false, restoreFocus: false }));
    window.addEventListener('hashchange', refresh);

    if (document.readyState === 'loading') {
        // This script is loaded at the end of each Purchasing page, so the
        // body already exists. Mount now to avoid flashing legacy inline nav.
        mount();
        document.addEventListener('DOMContentLoaded', () => refreshBadges());
    } else {
        mount();
        refreshBadges();
    }
})();
