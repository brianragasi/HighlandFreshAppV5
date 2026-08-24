(function () {
    'use strict';

    const DEFAULT_INTERVAL_MS = 15000;

    function isUserBusy() {
        if (document.querySelector('dialog[open], .modal.modal-open, [data-live-refresh-pause="true"]')) {
            return true;
        }

        const active = document.activeElement;
        return active instanceof HTMLInputElement
            || active instanceof HTMLTextAreaElement
            || active instanceof HTMLSelectElement;
    }

    function start(refresh, options = {}) {
        if (typeof refresh !== 'function') {
            throw new TypeError('Live refresh requires a refresh function.');
        }

        const intervalMs = Math.max(5000, Number(options.intervalMs) || DEFAULT_INTERVAL_MS);
        let stopped = false;
        let refreshing = false;
        let lastRefreshAt = Date.now();
        let timerId = null;

        async function run(force = false) {
            if (stopped || refreshing || document.visibilityState !== 'visible') return;
            if (!force && isUserBusy()) return;

            refreshing = true;
            try {
                await refresh();
                lastRefreshAt = Date.now();
            } catch (error) {
                console.warn('Background refresh skipped:', error);
            } finally {
                refreshing = false;
            }
        }

        function schedule() {
            timerId = window.setInterval(() => run(false), intervalMs);
        }

        function refreshAfterReturn() {
            if (document.visibilityState !== 'visible') return;
            if (Date.now() - lastRefreshAt >= Math.min(intervalMs, 5000)) {
                run(false);
            }
        }

        document.addEventListener('visibilitychange', refreshAfterReturn);
        window.addEventListener('focus', refreshAfterReturn);
        schedule();

        return function stop() {
            stopped = true;
            if (timerId !== null) window.clearInterval(timerId);
            document.removeEventListener('visibilitychange', refreshAfterReturn);
            window.removeEventListener('focus', refreshAfterReturn);
        };
    }

    window.HighlandLiveRefresh = { start };
})();
