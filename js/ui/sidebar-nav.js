/**
 * Highland Fresh — Shared sidebar navigation fixups (all roles)
 *
 * Applied on every authenticated page that loads auth.js:
 *  1. Rewrites relative sidebar hrefs to absolute paths (works from reports/)
 *  2. Marks the current page with aria-current
 *  3. Scrolls the active item into view inside the sidebar nav
 *     (so long menus don't jump back up to Dashboard)
 *
 * Safe with shared sidebars (raw/production) that remount via MutationObserver.
 */
(function () {
    'use strict';

    if (window.HighlandSidebarNav) return;

    const SKIP_HREF = /^(#|javascript:|mailto:|tel:|data:)/i;

    function absolutizeHref(href) {
        if (!href || SKIP_HREF.test(href.trim())) return href;
        if (/^https?:\/\//i.test(href)) return href;
        try {
            const u = new URL(href, window.location.href);
            // Same-origin absolute path only (keeps ?query and #hash)
            return u.pathname + u.search + u.hash;
        } catch (e) {
            return href;
        }
    }

    function pathOnly(pathname) {
        return String(pathname || '')
            .replace(/\\/g, '/')
            .replace(/\/+$/, '')
            .toLowerCase();
    }

    function linkPathname(a) {
        try {
            const raw = a.getAttribute('href') || '';
            if (!raw || SKIP_HREF.test(raw.trim())) return '';
            return pathOnly(new URL(raw, window.location.href).pathname);
        } catch (e) {
            return '';
        }
    }

    function isNavLink(a) {
        if (!a || a.tagName !== 'A') return false;
        const href = a.getAttribute('href') || '';
        if (!href || SKIP_HREF.test(href.trim())) return false;
        // Skip logout / actions that only have onclick
        if (/logout/i.test(href) || /logout/i.test(a.getAttribute('onclick') || '')) return false;
        return true;
    }

    function findSidebarRoots() {
        const roots = [];
        const main = document.getElementById('sidebar');
        if (main) roots.push(main);
        // Some pages use a secondary drawer — still only enhance #sidebar
        return roots;
    }

    function enhanceSidebar(sidebar) {
        if (!sidebar) return;
        const nav = sidebar.querySelector('nav');
        if (!nav) return;

        const current = pathOnly(window.location.pathname);
        const links = Array.prototype.slice.call(nav.querySelectorAll('a[href]')).filter(isNavLink);
        let activeLinks = [];

        links.forEach((a) => {
            const abs = absolutizeHref(a.getAttribute('href'));
            if (abs && abs !== a.getAttribute('href')) {
                a.setAttribute('href', abs);
            }

            const lp = linkPathname(a);
            const matches = lp && lp === current;
            if (matches) {
                activeLinks.push(a);
                a.setAttribute('aria-current', 'page');
            } else if (a.getAttribute('aria-current') === 'page') {
                // Only strip if we successfully matched something else, or link clearly wrong
                // Keep page-authored aria-current if no path match yet
            }
        });

        // Fallback: page already painted the active style (bg-primary)
        if (!activeLinks.length) {
            activeLinks = links.filter((a) => {
                const cls = a.className || '';
                return cls.indexOf('bg-primary') !== -1
                    || a.getAttribute('aria-current') === 'page';
            });
        }

        scrollActiveIntoNav(nav, activeLinks[0] || null);
        sidebar.dataset.hfNavEnhanced = '1';
        delete sidebar.dataset.hfNavDirty;
    }

    /**
     * Scroll only the nav overflow container — never window.scrollIntoView
     * (which can jump the main page and feel like "going up to Dashboard").
     */
    function scrollActiveIntoNav(nav, active) {
        if (!nav || !active) return;

        const run = () => {
            try {
                const navRect = nav.getBoundingClientRect();
                const activeRect = active.getBoundingClientRect();
                if (!navRect.height) return;
                const delta =
                    (activeRect.top + activeRect.height / 2) -
                    (navRect.top + navRect.height / 2);
                // Only adjust when the item is outside a comfortable band
                const edgePad = 24;
                const fullyVisible =
                    activeRect.top >= navRect.top + edgePad
                    && activeRect.bottom <= navRect.bottom - edgePad;
                if (!fullyVisible) {
                    nav.scrollTop += delta;
                }
            } catch (e) { /* ignore */ }
        };

        requestAnimationFrame(run);
    }

    function enhanceAll() {
        findSidebarRoots().forEach(enhanceSidebar);
    }

    let observeTimer = null;
    let observer = null;

    function watchSidebarRemounts() {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar || observer) return;

        observer = new MutationObserver(() => {
            sidebar.dataset.hfNavDirty = '1';
            clearTimeout(observeTimer);
            observeTimer = setTimeout(enhanceAll, 40);
        });
        observer.observe(sidebar, { childList: true, subtree: true });
    }

    function boot() {
        enhanceAll();
        watchSidebarRemounts();
        // Shared sidebars often remount + fill badges shortly after load
        setTimeout(enhanceAll, 80);
        setTimeout(enhanceAll, 400);
    }

    window.HighlandSidebarNav = {
        enhance: enhanceSidebar,
        enhanceAll: enhanceAll,
        scrollActiveIntoNav: scrollActiveIntoNav,
        absolutizeHref: absolutizeHref,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
