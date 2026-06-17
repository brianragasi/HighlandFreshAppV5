/**
 * Highland Fresh - Warehouse Raw Sidebar Stats Widget
 *
 * Renders 4 at-a-glance numbers in the sidebar header on every page:
 *   - Raw Milk (liters)
 *   - Pending Requisitions
 *   - Low Stock Items (ingredients + MRO combined)
 *   - Pending Deliveries
 *
 * Loaded by every warehouse raw page after raw.service.js. The widget
 * elements (id="sidebarStatMilk|Req|Low|Deliv") are embedded in the
 * sidebar header block of each page; this script just fills the numbers.
 */

(function () {
    'use strict';

    function setStat(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        const num = Number(value) || 0;
        // Use compact formatting (no trailing .0 for whole numbers).
        el.textContent = num.toLocaleString(undefined, { maximumFractionDigits: 1 });
    }

    async function loadSidebarStats() {
        // Bail if the page doesn't have the widget (e.g., the user customized
        // a page and didn't include the 4 ids). Keep this defensive so we
        // never break a page by adding a new shared script.
        if (!document.getElementById('sidebarStatMilk') &&
            !document.getElementById('sidebarStatReq') &&
            !document.getElementById('sidebarStatLow') &&
            !document.getElementById('sidebarStatDeliv')) {
            return;
        }

        try {
            const response = await WarehouseRawService.getDashboardStats();
            if (!response || !response.success || !response.data) return;

            const d = response.data;
            const milk   = d.raw_milk?.total_liters || 0;
            const reqs   = d.requisitions?.pending_count || 0;
            const low    = (d.ingredients?.low_stock_count || 0)
                         + (d.mro?.low_stock_count || 0);
            const deliv  = d.pending_deliveries?.count || 0;

            setStat('sidebarStatMilk',  milk);
            setStat('sidebarStatReq',   reqs);
            setStat('sidebarStatLow',   low);
            setStat('sidebarStatDeliv', deliv);
        } catch (err) {
            // Silently keep the "0" placeholders; the dashboard page itself
            // surfaces the real error to the user via its own try/catch.
            console.warn('Sidebar stats failed to load:', err);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadSidebarStats);
    } else {
        loadSidebarStats();
    }
})();
