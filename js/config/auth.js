/**
 * Highland Fresh System - Authentication Service
 * 
 * @package HighlandFresh
 * @version 4.0
 */

const AuthService = {
    IDLE_TIMEOUT_MS: 15 * 60 * 1000,
    ABSOLUTE_TIMEOUT_MS: 8 * 60 * 60 * 1000,
    ACTIVITY_SYNC_THROTTLE_MS: 5000,
    TIMEOUT_CHECK_INTERVAL_MS: 30000,
    SERVER_SYNC_INTERVAL_MS: 120000,
    SESSION_STARTED_KEY: 'highland_session_started_at',
    SESSION_EXPIRES_KEY: 'highland_session_expires_at',
    LAST_ACTIVITY_KEY: 'highland_last_activity_at',
    IDLE_TIMEOUT_KEY: 'highland_idle_timeout_ms',
    _monitorTimer: null,
    _activityHandler: null,
    _lastSyncedActivityAt: 0,
    _lastServerSyncAt: 0,
    _timeoutProcessing: false,

    /**
     * Login user
     */
    async login(identifier, password) {
        const response = await api.post('/auth/login.php', { identifier, password });

        if (response.success) {
            // Store token and user data
            localStorage.setItem('highland_token', response.data.token);
            localStorage.setItem('highland_user', JSON.stringify(response.data.user));
            this.persistSessionCookie(response.data.token);
            this.persistSessionWindow(response.data.token, response.data);

            // Store must_change_password flag
            if (response.data.must_change_password) {
                localStorage.setItem('highland_must_change_password', '1');
            } else {
                localStorage.removeItem('highland_must_change_password');
                this.startSessionMonitor();
            }
        }

        return response;
    },
    
    /**
     * Logout user
     */
    logout() {
        this.handleForcedRelogin('manual_logout');
    },
    
    /**
     * Get current user
     */
    getCurrentUser() {
        const userStr = localStorage.getItem('highland_user');
        return userStr ? JSON.parse(userStr) : null;
    },
    
    /**
     * Check if user is authenticated
     */
    isAuthenticated() {
        return !!localStorage.getItem('highland_token');
    },
    
    /**
     * Check if user has role
     */
    hasRole(roles) {
        const user = this.getCurrentUser();
        if (!user) return false;
        
        if (typeof roles === 'string') {
            roles = [roles];
        }
        
        return roles.includes(user.role);
    },
    
    /**
     * Get current user from API
     */
    async fetchCurrentUser() {
        return await api.get('/auth/me.php');
    },

    /**
     * Perform step-up verification and get one-time token
     */
    async requestStepUp(scope, password) {
        return await api.post('/auth/step_up.php', { scope, password });
    },

    /**
     * Prompt for password and obtain step-up token
     */
    async promptStepUp(scope, actionLabel = 'this action') {
        const password = await this.promptText({
            title: 'Security Check',
            message: `Re-enter your password to continue with ${actionLabel}.`,
            label: 'Password',
            type: 'password',
            confirmText: 'Continue',
            icon: 'fa-shield-halved',
            required: true
        });
        if (password === null) {
            return null;
        }

        if (!password.trim()) {
            throw new Error('Password is required for step-up authentication');
        }

        const response = await this.requestStepUp(scope, password);
        const token = response?.data?.step_up_token;
        if (!token) {
            throw new Error('Step-up authentication failed');
        }

        return token;
    },

    async confirmAction(options = {}) {
        const result = await this.openActionDialog({
            title: options.title || 'Confirm Action',
            message: options.message || 'Continue with this action?',
            highlight: options.highlight || '',
            highlightLabel: options.highlightLabel || '',
            callout: options.callout || '',
            calloutTone: options.calloutTone || 'warning',
            confirmText: options.confirmText || 'Confirm',
            confirmIcon: options.confirmIcon || 'fa-check',
            cancelText: options.cancelText || 'Cancel',
            icon: options.icon || 'fa-circle-question',
            tone: options.tone || 'primary',
            mode: 'confirm'
        });
        return result === true;
    },

    async promptText(options = {}) {
        return await this.openActionDialog({
            title: options.title || 'Required Information',
            message: options.message || '',
            label: options.label || 'Value',
            placeholder: options.placeholder || '',
            confirmText: options.confirmText || 'Submit',
            confirmIcon: options.confirmIcon || 'fa-check',
            cancelText: options.cancelText || 'Cancel',
            icon: options.icon || 'fa-pen-to-square',
            tone: options.tone || 'primary',
            mode: 'prompt',
            type: options.type || 'text',
            required: options.required !== false
        });
    },

    async showMessage(options = {}) {
        return await this.openActionDialog({
            title: options.title || 'Notice',
            message: options.message || '',
            confirmText: options.confirmText || 'OK',
            confirmIcon: options.confirmIcon || 'fa-check',
            icon: options.icon || 'fa-circle-info',
            tone: options.tone || 'primary',
            mode: 'message'
        });
    },

    /** Map tone → icon shell + text color classes (DaisyUI / Tailwind). */
    dialogToneClasses(tone) {
        const map = {
            primary: {
                shell: 'bg-primary/12 text-primary ring-primary/15',
                title: 'text-base-content',
                confirm: 'btn-primary'
            },
            success: {
                shell: 'bg-success/15 text-success ring-success/20',
                title: 'text-base-content',
                confirm: 'btn-success'
            },
            warning: {
                shell: 'bg-warning/15 text-warning ring-warning/25',
                title: 'text-base-content',
                confirm: 'btn-warning'
            },
            error: {
                shell: 'bg-error/12 text-error ring-error/20',
                title: 'text-base-content',
                confirm: 'btn-error'
            },
            info: {
                shell: 'bg-info/12 text-info ring-info/20',
                title: 'text-base-content',
                confirm: 'btn-info'
            }
        };
        return map[tone] || map.primary;
    },

    openActionDialog(options) {
        return new Promise((resolve) => {
            const dialog = this.ensureActionDialog();
            const title = dialog.querySelector('[data-dialog-title]');
            const message = dialog.querySelector('[data-dialog-message]');
            const icon = dialog.querySelector('[data-dialog-icon]');
            const iconShell = dialog.querySelector('[data-dialog-icon-shell]');
            const highlightWrap = dialog.querySelector('[data-dialog-highlight-wrap]');
            const highlightLabel = dialog.querySelector('[data-dialog-highlight-label]');
            const highlightValue = dialog.querySelector('[data-dialog-highlight]');
            const calloutWrap = dialog.querySelector('[data-dialog-callout-wrap]');
            const calloutText = dialog.querySelector('[data-dialog-callout]');
            const calloutIcon = dialog.querySelector('[data-dialog-callout-icon]');
            const inputWrap = dialog.querySelector('[data-dialog-input-wrap]');
            const inputLabel = dialog.querySelector('[data-dialog-label]');
            const input = dialog.querySelector('[data-dialog-input]');
            const error = dialog.querySelector('[data-dialog-error]');
            const confirmBtn = dialog.querySelector('[data-dialog-confirm]');
            const cancelBtn = dialog.querySelector('[data-dialog-cancel]');
            const backdropBtn = dialog.querySelector('[data-dialog-backdrop]');
            const form = dialog.querySelector('form');
            const tone = options.tone || 'primary';
            const toneCls = this.dialogToneClasses(tone);

            title.textContent = options.title;
            message.textContent = options.message || '';
            message.classList.toggle('hidden', !options.message);

            // Icon shell + icon
            if (iconShell) {
                iconShell.className =
                    'aad-icon-shell w-14 h-14 rounded-full flex items-center justify-center shrink-0 ring-4 ' +
                    toneCls.shell;
            }
            icon.className = `fas ${options.icon || 'fa-circle-question'} text-xl`;

            // Optional target highlight (e.g. DR-20260713-001)
            const hasHighlight = !!(options.highlight && String(options.highlight).trim());
            if (highlightWrap) {
                highlightWrap.classList.toggle('hidden', !hasHighlight);
                if (hasHighlight) {
                    if (highlightLabel) {
                        highlightLabel.textContent = options.highlightLabel || 'Reference';
                    }
                    if (highlightValue) {
                        highlightValue.textContent = String(options.highlight).trim();
                    }
                }
            }

            // Optional hard-stop / constraint callout
            const hasCallout = !!(options.callout && String(options.callout).trim());
            if (calloutWrap) {
                calloutWrap.classList.toggle('hidden', !hasCallout);
                if (hasCallout) {
                    const ct = options.calloutTone || 'warning';
                    calloutWrap.className =
                        'aad-callout flex items-start gap-2.5 rounded-xl px-3.5 py-3 text-sm leading-snug ' +
                        (ct === 'info'
                            ? 'bg-info/10 text-base-content border border-info/25'
                            : ct === 'error'
                                ? 'bg-error/10 text-base-content border border-error/25'
                                : 'bg-warning/15 text-base-content border border-warning/30');
                    if (calloutIcon) {
                        calloutIcon.className =
                            'fas mt-0.5 shrink-0 ' +
                            (ct === 'info'
                                ? 'fa-circle-info text-info'
                                : ct === 'error'
                                    ? 'fa-triangle-exclamation text-error'
                                    : 'fa-lock text-warning');
                    }
                    if (calloutText) {
                        calloutText.textContent = String(options.callout).trim();
                    }
                }
            }

            const confirmIcon = options.confirmIcon || 'fa-check';
            confirmBtn.className = `btn ${toneCls.confirm} aad-btn-primary gap-2 min-w-[9rem]`;
            confirmBtn.innerHTML = `<i class="fas ${confirmIcon}"></i><span>${this.escapeHtml(options.confirmText || 'Confirm')}</span>`;

            cancelBtn.className = 'btn btn-ghost aad-btn-cancel border border-base-300/80 hover:bg-base-200';
            cancelBtn.innerHTML = `<span>${this.escapeHtml(options.cancelText || 'Cancel')}</span>`;
            error.classList.add('hidden');
            error.textContent = '';

            const isPrompt = options.mode === 'prompt';
            const isMessage = options.mode === 'message';
            inputWrap.classList.toggle('hidden', !isPrompt);
            cancelBtn.classList.toggle('hidden', isMessage);
            input.value = '';
            input.type = options.type || 'text';
            input.placeholder = options.placeholder || '';
            input.required = !!options.required;
            input.autocomplete = input.type === 'password' ? 'current-password' : 'off';
            inputLabel.textContent = options.label || 'Value';
            dialog.dataset.mode = options.mode;

            const cleanup = () => {
                form.removeEventListener('submit', onSubmit);
                cancelBtn.removeEventListener('click', onCancel);
                backdropBtn.removeEventListener('click', onCancel);
                dialog.removeEventListener('cancel', onCancel);
            };

            const onCancel = (event) => {
                event?.preventDefault();
                cleanup();
                dialog.close();
                resolve(isMessage ? true : null);
            };

            const onSubmit = (event) => {
                event.preventDefault();
                if (isPrompt) {
                    const value = input.value;
                    if (options.required && !value.trim()) {
                        error.textContent = `${options.label || 'Value'} is required.`;
                        error.classList.remove('hidden');
                        input.focus();
                        return;
                    }
                    cleanup();
                    dialog.close();
                    resolve(value);
                    return;
                }

                cleanup();
                dialog.close();
                resolve(true);
            };

            form.addEventListener('submit', onSubmit);
            cancelBtn.addEventListener('click', onCancel);
            backdropBtn.addEventListener('click', onCancel);
            dialog.addEventListener('cancel', onCancel);
            dialog.showModal();
            if (isPrompt) {
                setTimeout(() => input.focus(), 50);
            } else {
                setTimeout(() => confirmBtn.focus(), 50);
            }
        });
    },

    ensureActionDialog() {
        let dialog = document.getElementById('appActionDialog');
        // Rebuild if an older shell is still in the page (missing enterprise layout hooks)
        if (dialog && dialog.dataset.dialogVersion !== '3') {
            dialog.remove();
            dialog = null;
        }
        if (dialog) return dialog;

        this.ensureActionDialogStyles();

        dialog = document.createElement('dialog');
        dialog.id = 'appActionDialog';
        dialog.className = 'modal';
        dialog.dataset.dialogVersion = '3';
        dialog.innerHTML = `
            <div class="modal-box aad-box max-w-md p-0 overflow-hidden shadow-2xl border border-base-300/60">
                <form class="aad-form">
                    <div class="aad-header px-6 pt-6 pb-4">
                        <div class="flex items-start gap-4">
                            <div data-dialog-icon-shell class="aad-icon-shell w-14 h-14 rounded-full flex items-center justify-center shrink-0 ring-4 bg-primary/12 text-primary ring-primary/15">
                                <i data-dialog-icon class="fas fa-circle-question text-xl"></i>
                            </div>
                            <div class="min-w-0 flex-1 pt-0.5">
                                <h3 data-dialog-title class="aad-title font-extrabold text-lg leading-snug tracking-tight text-base-content">
                                    Confirm Action
                                </h3>
                                <div data-dialog-highlight-wrap class="hidden mt-2.5">
                                    <span data-dialog-highlight-label class="block text-[0.65rem] font-semibold uppercase tracking-wider text-base-content/45 mb-1"></span>
                                    <span data-dialog-highlight class="aad-highlight inline-flex items-center gap-1.5 font-mono text-sm font-bold tracking-wide px-2.5 py-1 rounded-lg bg-base-200 text-primary border border-primary/20"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 pb-2 space-y-3">
                        <p data-dialog-message class="aad-message text-sm text-base-content/70 leading-relaxed whitespace-pre-line m-0"></p>
                        <div data-dialog-callout-wrap class="aad-callout hidden flex items-start gap-2.5 rounded-xl px-3.5 py-3 text-sm leading-snug bg-warning/15 border border-warning/30">
                            <i data-dialog-callout-icon class="fas fa-lock text-warning mt-0.5 shrink-0"></i>
                            <span data-dialog-callout class="font-medium text-base-content/90"></span>
                        </div>
                    </div>

                    <div data-dialog-input-wrap class="form-control px-6 mb-1 hidden">
                        <label class="label py-1"><span data-dialog-label class="label-text font-medium">Value</span></label>
                        <input data-dialog-input class="input input-bordered w-full" autocomplete="off">
                    </div>
                    <p data-dialog-error class="hidden text-sm text-error px-6 mb-2"></p>

                    <div class="aad-footer modal-action mt-0 px-6 py-4 bg-base-200/40 border-t border-base-300/50 gap-2 sm:justify-end">
                        <button type="button" data-dialog-cancel class="btn btn-ghost aad-btn-cancel border border-base-300/80">
                            Cancel
                        </button>
                        <button type="submit" data-dialog-confirm class="btn btn-primary aad-btn-primary gap-2">
                            <i class="fas fa-check"></i> Confirm
                        </button>
                    </div>
                </form>
            </div>
            <form class="modal-backdrop"><button type="button" data-dialog-backdrop>close</button></form>
        `;
        document.body.appendChild(dialog);
        return dialog;
    },

    ensureActionDialogStyles() {
        if (document.getElementById('appActionDialogStyles')) return;
        const style = document.createElement('style');
        style.id = 'appActionDialogStyles';
        style.textContent = `
            #appActionDialog.modal { align-items: center; }
            #appActionDialog .aad-box {
                border-radius: 1rem;
            }
            #appActionDialog .aad-icon-shell {
                transition: transform 0.15s ease, box-shadow 0.15s ease;
            }
            #appActionDialog[open] .aad-icon-shell {
                box-shadow: 0 8px 24px -8px oklch(var(--p) / 0.35);
            }
            #appActionDialog .aad-title {
                letter-spacing: -0.01em;
            }
            #appActionDialog .aad-highlight {
                max-width: 100%;
                word-break: break-all;
            }
            #appActionDialog .aad-btn-primary,
            #appActionDialog .aad-btn-cancel {
                transition: transform 0.12s ease, box-shadow 0.12s ease, background-color 0.12s ease;
            }
            #appActionDialog .aad-btn-primary:hover:not(:disabled) {
                transform: translateY(-1px);
                box-shadow: 0 6px 16px -6px oklch(var(--p) / 0.45);
            }
            #appActionDialog .aad-btn-primary:focus-visible,
            #appActionDialog .aad-btn-cancel:focus-visible {
                outline: 2px solid oklch(var(--p));
                outline-offset: 2px;
            }
            #appActionDialog .aad-btn-cancel:hover:not(:disabled) {
                transform: translateY(-1px);
            }
        `;
        document.head.appendChild(style);
    },

    escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    },
    
    /**
     * Change password (authenticated)
     */
    async changePassword(currentPassword, newPassword, confirmPassword) {
        return await api.post('/auth/change_password.php', {
            current_password: currentPassword,
            new_password: newPassword,
            confirm_password: confirmPassword
        });
    },

    /**
     * Check if user must change password before accessing the app
     */
    mustChangePassword() {
        return localStorage.getItem('highland_must_change_password') === '1';
    },

    /**
     * Clear the must_change_password flag after successful change
     */
    clearMustChangePassword() {
        localStorage.removeItem('highland_must_change_password');
        // Also update the stored user object
        const user = this.getCurrentUser();
        if (user) {
            user.must_change_password = 0;
            localStorage.setItem('highland_user', JSON.stringify(user));
        }
        this.startSessionMonitor();
    },

    /**
     * Require authentication - redirect if not logged in
     * Also redirects to force-change-password if must_change_password=1
     */
    requireAuth() {
        if (!this.isAuthenticated()) {
            window.location.href = APP_BASE + '/html/login.html';
            return false;
        }

        if (this.isSessionExpiredClientSide()) {
            this.handleForcedRelogin('session_expired');
            return false;
        }

        // Force password change redirect (don't redirect if already on that page)
        const isChangePasswordPage = window.location.pathname.toLowerCase().includes('/change-password.html');
        if (this.mustChangePassword() && !isChangePasswordPage) {
            window.location.href = APP_BASE + '/html/change-password.html';
            return false;
        }

        this.recordActivity();
        this.startSessionMonitor();
        return true;
    },
    
    /**
     * Require specific role(s)
     */
    requireRole(roles) {
        if (!this.requireAuth()) return false;
        
        if (!this.hasRole(roles)) {
            showNotification('Access Denied', 'You do not have permission to access this page.', 'error');
            window.location.href = APP_BASE + '/html/dashboard.html';
            return false;
        }
        
        return true;
    },
    
    /**
     * Get role display name
     */
    getRoleDisplayName(role) {
        const roleNames = {
            'general_manager': 'General Manager',
            'qc_officer': 'QC Officer',
            'production_staff': 'Production Staff',
            'warehouse_raw': 'Warehouse (Raw Materials)',
            'warehouse_fg': 'Warehouse (Finished Goods)',
            'sales_custodian': 'Sales Custodian',
            'cashier': 'Cashier',
            'purchaser': 'Purchaser',
            'finance_officer': 'Finance Officer',
            'bookkeeper': 'Bookkeeper',
            'maintenance_head': 'Maintenance Head'
        };
        return roleNames[role] || role;
    },

    persistSessionWindow(token, responseData = {}) {
        const payload = this.parseJwtPayload(token);
        const now = Date.now();
        const startedAt = payload?.iat ? payload.iat * 1000 : now;
        const expiresAt = payload?.exp
            ? payload.exp * 1000
            : startedAt + (responseData.expires_in ? responseData.expires_in * 1000 : this.ABSOLUTE_TIMEOUT_MS);
        const idleTimeout = responseData.idle_timeout ? responseData.idle_timeout * 1000 : this.IDLE_TIMEOUT_MS;

        localStorage.setItem(this.SESSION_STARTED_KEY, String(startedAt));
        localStorage.setItem(this.SESSION_EXPIRES_KEY, String(expiresAt));
        localStorage.setItem(this.IDLE_TIMEOUT_KEY, String(idleTimeout));
        localStorage.setItem(this.LAST_ACTIVITY_KEY, String(now));
    },

    parseJwtPayload(token) {
        if (!token) return null;

        const parts = token.split('.');
        if (parts.length !== 3) return null;

        try {
            const base64Payload = parts[1].replace(/-/g, '+').replace(/_/g, '/');
            const padding = '='.repeat((4 - (base64Payload.length % 4)) % 4);
            const payloadString = atob(base64Payload + padding);
            return JSON.parse(payloadString);
        } catch (error) {
            return null;
        }
    },

    getSessionExpiryMs() {
        const token = localStorage.getItem('highland_token');
        const payload = this.parseJwtPayload(token);
        if (payload?.exp) {
            return payload.exp * 1000;
        }

        const storedExpiresAt = Number(localStorage.getItem(this.SESSION_EXPIRES_KEY) || 0);
        if (storedExpiresAt > 0) {
            return storedExpiresAt;
        }

        const startedAt = Number(localStorage.getItem(this.SESSION_STARTED_KEY) || Date.now());
        return startedAt + this.ABSOLUTE_TIMEOUT_MS;
    },

    getIdleTimeoutMs() {
        const stored = Number(localStorage.getItem(this.IDLE_TIMEOUT_KEY) || 0);
        return stored > 0 ? stored : this.IDLE_TIMEOUT_MS;
    },

    getLastActivityMs() {
        const stored = Number(localStorage.getItem(this.LAST_ACTIVITY_KEY) || 0);
        return stored > 0 ? stored : Date.now();
    },

    recordActivity(force = false) {
        if (!this.isAuthenticated()) return;

        const now = Date.now();
        if (!force && (now - this._lastSyncedActivityAt) < this.ACTIVITY_SYNC_THROTTLE_MS) {
            return;
        }

        localStorage.setItem(this.LAST_ACTIVITY_KEY, String(now));
        this._lastSyncedActivityAt = now;
    },

    isSessionExpiredClientSide() {
        if (!this.isAuthenticated()) {
            return true;
        }

        const now = Date.now();
        const expiresAt = this.getSessionExpiryMs();
        const lastActivity = this.getLastActivityMs();
        const idleTimeoutMs = this.getIdleTimeoutMs();

        return now >= expiresAt || (now - lastActivity) >= idleTimeoutMs;
    },

    startSessionMonitor() {
        if (!this.isAuthenticated()) {
            return;
        }

        if (!this._activityHandler) {
            this._activityHandler = () => this.recordActivity();
            ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll'].forEach((eventName) => {
                window.addEventListener(eventName, this._activityHandler, { passive: true });
            });
        }

        if (this._monitorTimer) {
            return;
        }

        this.recordActivity(true);
        this._monitorTimer = setInterval(() => {
            if (!this.isAuthenticated()) {
                this.stopSessionMonitor();
                return;
            }

            const now = Date.now();
            const expiresAt = this.getSessionExpiryMs();
            if (now >= expiresAt) {
                this.handleForcedRelogin('absolute_timeout');
                return;
            }

            const lastActivity = this.getLastActivityMs();
            if ((now - lastActivity) >= this.getIdleTimeoutMs()) {
                this.handleForcedRelogin('idle_timeout');
                return;
            }

            this.syncSessionActivity(now);
        }, this.TIMEOUT_CHECK_INTERVAL_MS);
    },

    stopSessionMonitor() {
        if (this._monitorTimer) {
            clearInterval(this._monitorTimer);
            this._monitorTimer = null;
        }

        if (this._activityHandler) {
            ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll'].forEach((eventName) => {
                window.removeEventListener(eventName, this._activityHandler);
            });
            this._activityHandler = null;
        }
    },

    persistSessionCookie(token) {
        if (!token) {
            return;
        }

        document.cookie = `highland_token=${encodeURIComponent(token)}; path=/; SameSite=Lax`;
    },

    clearSessionCookie() {
        document.cookie = 'highland_token=; path=/; SameSite=Lax; Max-Age=0';
    },

    clearSessionData() {
        localStorage.removeItem('highland_token');
        localStorage.removeItem('highland_user');
        localStorage.removeItem('highland_must_change_password');
        localStorage.removeItem(this.SESSION_STARTED_KEY);
        localStorage.removeItem(this.SESSION_EXPIRES_KEY);
        localStorage.removeItem(this.LAST_ACTIVITY_KEY);
        localStorage.removeItem(this.IDLE_TIMEOUT_KEY);
        this.clearSessionCookie();
        this._lastServerSyncAt = 0;
        this.stopSessionMonitor();
    },

    syncSessionActivity(now = Date.now()) {
        if (!this.isAuthenticated() || this._timeoutProcessing) {
            return;
        }

        if ((now - this._lastServerSyncAt) < this.SERVER_SYNC_INTERVAL_MS) {
            return;
        }

        const token = localStorage.getItem('highland_token');
        if (!token) {
            return;
        }

        this._lastServerSyncAt = now;
        fetch(`${API_BASE_URL}/auth/me.php`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`,
                // PHP-FPM workaround: see api.js comment
                'X-Auth-Token': token
            }
        })
            .then((response) => {
                if (response.status === 401) {
                    this.handleForcedRelogin('session_expired');
                }
            })
            .catch(() => null);
    },

    notifyServerLogout(reason = 'logout') {
        const token = localStorage.getItem('highland_token');
        if (!token) {
            return Promise.resolve();
        }

        return fetch(`${API_BASE_URL}/auth/logout.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`,
                'X-Auth-Token': token
            },
            body: JSON.stringify({ reason }),
            keepalive: true
        });
    },

    handleForcedRelogin(reason = 'session_expired') {
        if (this._timeoutProcessing) {
            return;
        }

        this._timeoutProcessing = true;
        this.notifyServerLogout(reason)
            .catch(() => null)
            .finally(() => {
                this.clearSessionData();
                const encodedReason = encodeURIComponent(reason);
                window.location.href = `${APP_BASE}/html/login.html?reason=${encodedReason}`;
            });
    },

    initializeSessionMonitor() {
        const isLoginPage = window.location.pathname.toLowerCase().includes('/login.html');
        if (isLoginPage || !this.isAuthenticated()) {
            return;
        }

        if (this.isSessionExpiredClientSide()) {
            this.handleForcedRelogin('session_expired');
            return;
        }

        this.startSessionMonitor();
    }
};

AuthService.initializeSessionMonitor();

// Shared sidebar UX for every role: absolute nav links + keep active item
// visible (so long menus / report pages don't jump back up to Dashboard).
// Loaded once after auth.js; skipped on login / password screens.
(function loadHighlandSidebarNav() {
    try {
        const path = (window.location.pathname || '').toLowerCase();
        if (
            path.includes('/login.html')
            || path.includes('/forgot-password')
            || path.includes('/reset-password')
            || path.includes('/set-password')
            || path.includes('/change-password')
        ) {
            return;
        }
        if (window.HighlandSidebarNav || window.__highlandSidebarNavLoading) return;
        window.__highlandSidebarNavLoading = true;

        const base = (typeof APP_BASE === 'string' ? APP_BASE : '').replace(/\/$/, '');
        const script = document.createElement('script');
        script.src = base + '/js/ui/sidebar-nav.js?v=20260712d';
        script.async = false;
        script.onerror = function () {
            window.__highlandSidebarNavLoading = false;
            console.warn('HighlandSidebarNav failed to load');
        };
        script.onload = function () {
            window.__highlandSidebarNavLoading = false;
        };
        (document.head || document.documentElement).appendChild(script);
    } catch (e) { /* ignore */ }
})();

