/**
 * Page-level role guard for static HTML screens.
 * API calls are still protected server-side; this stops the wrong role from
 * seeing a protected page just by typing the URL.
 */
(function () {
    const page = document.documentElement;
    page.style.visibility = 'hidden';

    const script = document.currentScript;
    const allowedRoles = (script?.dataset.allowedRoles || '')
        .split(',')
        .map(role => role.trim())
        .filter(Boolean);

    const recordDeniedAttempt = (role = '') => {
        sessionStorage.setItem('highland_denied_from', window.location.pathname + window.location.search);
        sessionStorage.setItem('highland_denied_role', role);
    };

    const denyAccess = (role = '') => {
        recordDeniedAttempt(role);
        if (typeof AuthService !== 'undefined') {
            AuthService.redirectToAccessDenied(allowedRoles);
            return;
        }

        const htmlIndex = window.location.pathname.toLowerCase().indexOf('/html/');
        const appBase = htmlIndex >= 0 ? window.location.pathname.slice(0, htmlIndex) : '';
        window.location.replace(appBase + '/html/access-denied.html');
    };

    // A protected page must never become visible when its guard is incomplete.
    if (!allowedRoles.length || typeof AuthService === 'undefined') {
        denyAccess('unknown');
        return;
    }

    if (!AuthService.requireAuth()) {
        return;
    }

    const browserUser = AuthService.getCurrentUser();
    if (!browserUser || !allowedRoles.includes(browserUser.role)) {
        denyAccess(browserUser?.role || '');
        return;
    }

    // Confirm the role from the server. Browser storage can be edited by a user,
    // while the server reads the signed session and the active database account.
    AuthService.fetchCurrentUser()
        .then(response => {
            const serverUser = response?.data;
            if (!serverUser || !allowedRoles.includes(serverUser.role)) {
                denyAccess(serverUser?.role || browserUser.role || '');
                return;
            }

            localStorage.setItem('highland_user', JSON.stringify({ ...browserUser, ...serverUser }));
            window.HIGHLAND_ROLE_GUARD_PASSED = true;
            document.getElementById('roleGuardPending')?.remove();
            page.style.visibility = '';
        })
        .catch(() => {
            denyAccess(browserUser.role || '');
        });
})();
