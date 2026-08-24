(function () {
    'use strict';

    // api.js installs this globally for every role-facing page. Keep this
    // compatibility file because older pages explicitly load it.
    if (typeof window.installHighlandNotificationLayer === 'function') {
        window.installHighlandNotificationLayer();
    } else {
        window.HighlandNotificationLayer?.sync?.();
    }
})();
