/**
 * Page-level role guard for static HTML screens.
 * API calls are still protected server-side; this stops the wrong role from
 * seeing a protected page just by typing the URL.
 */
(function () {
    const script = document.currentScript;
    const allowedRoles = (script?.dataset.allowedRoles || '')
        .split(',')
        .map(role => role.trim())
        .filter(Boolean);

    if (!allowedRoles.length || typeof AuthService === 'undefined') {
        return;
    }

    if (!AuthService.requireAuth()) {
        document.body && (document.body.style.display = 'none');
        return;
    }

    const user = AuthService.getCurrentUser();
    if (!user || !allowedRoles.includes(user.role)) {
        document.body && (document.body.style.display = 'none');
        sessionStorage.setItem('highland_denied_from', window.location.pathname + window.location.search);
        sessionStorage.setItem('highland_denied_role', user?.role || '');
        AuthService.redirectToAccessDenied(allowedRoles);
        return;
    }

    window.HIGHLAND_ROLE_GUARD_PASSED = true;
})();
