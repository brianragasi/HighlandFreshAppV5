/**
 * Highland Fresh System - API Configuration
 * 
 * @package HighlandFresh
 * @version 4.0
 */

/**
 * Resolve the application folder from the loaded script path.
 *
 * This keeps API requests on the same server and folder whether the app is
 * opened through localhost, a LAN address, or a hosted domain.
 */
function resolveAppBase(scriptSource = '', pagePath = '', explicitBase = null) {
    if (explicitBase !== null && explicitBase !== undefined) {
        const configuredBase = String(explicitBase).trim();
        if (configuredBase === '' || configuredBase === '/') {
            return '';
        }
        return `/${configuredBase.replace(/^\/+|\/+$/g, '')}`;
    }

    if (scriptSource) {
        try {
            const scriptPath = new URL(scriptSource, 'http://localhost').pathname;
            const scriptMarker = '/js/config/api.js';
            const markerIndex = scriptPath.lastIndexOf(scriptMarker);
            if (markerIndex >= 0) {
                return scriptPath.slice(0, markerIndex).replace(/\/$/, '');
            }
        } catch (error) {
            // Fall through to the page-path check below.
        }
    }

    const htmlMarker = '/html/';
    const htmlIndex = String(pagePath).indexOf(htmlMarker);
    return htmlIndex >= 0 ? String(pagePath).slice(0, htmlIndex).replace(/\/$/, '') : '';
}

function detectAppBase() {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return '';
    }

    const configuredMeta = document.querySelector('meta[name="app-base"]');
    const explicitBase = configuredMeta ? configuredMeta.getAttribute('content') : null;
    const apiScript = document.currentScript
        || Array.from(document.scripts || []).find((script) => /\/js\/config\/api\.js(?:\?|$)/.test(script.src));

    return resolveAppBase(
        apiScript ? apiScript.src : '',
        window.location.pathname,
        explicitBase
    );
}

const APP_BASE = detectAppBase();

/**
 * Shared browser-side output encoding and notification rendering.
 *
 * Values returned by our own API are still untrusted: names, notes, error
 * messages, and codes may contain stored markup. Page templates should use
 * escapeHtml() for intentional HTML templates and renderToastContent() when
 * the value is only text.
 */
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[character]);
}

function renderToastContent(element, message, iconClass = '', messageClass = '') {
    if (!element) return;

    element.replaceChildren();
    if (iconClass) {
        const icon = document.createElement('i');
        icon.className = String(iconClass)
            .split(/\s+/)
            .filter((token) => /^[a-zA-Z0-9_-]+$/.test(token))
            .join(' ');
        icon.setAttribute('aria-hidden', 'true');
        element.appendChild(icon);
    }

    const text = document.createElement('span');
    text.textContent = String(message ?? '');
    if (messageClass) {
        text.className = String(messageClass)
            .split(/\s+/)
            .filter((token) => /^[a-zA-Z0-9_:/[\].-]+$/.test(token))
            .join(' ');
    }
    element.appendChild(text);
}

/**
 * Keep Philippine contact-number fields in the local numeric format used by
 * the application. This runs while typing and on paste, so an invalid string
 * never grows into an extremely long value before submit-time validation.
 */
function normalizePhilippinePhoneInput(value) {
    let digits = String(value ?? '').replace(/\D/g, '');
    if (/^63\d{10}$/.test(digits)) {
        digits = `0${digits.slice(2)}`;
    }
    return digits.slice(0, 11);
}

function constrainPhilippinePhoneInput(input) {
    if (!input) return '';
    const normalized = normalizePhilippinePhoneInput(input.value);
    if (input.value !== normalized) input.value = normalized;
    input.type = 'tel';
    input.inputMode = 'numeric';
    input.maxLength = 11;
    input.setAttribute('autocomplete', 'tel-national');
    input.setAttribute('pattern', '(?:09\\d{9}|02\\d{8}|0[3-8]\\d{8,9})');
    return normalized;
}

function installPhilippinePhoneInputLimits() {
    if (typeof document === 'undefined') return;
    const selector = 'input[type="tel"], input[data-philippine-phone]';
    const prepare = (input) => constrainPhilippinePhoneInput(input);

    document.querySelectorAll(selector).forEach(prepare);
    document.addEventListener('input', (event) => {
        if (event.target?.matches?.(selector)) prepare(event.target);
    }, true);
}

function numberInputAllowsNegative(input) {
    if (!input) return true;
    if (input.hasAttribute?.('data-allow-negative')) return true;
    if (!input.hasAttribute?.('min')) return true;
    const minimum = Number(input.getAttribute('min'));
    return Number.isFinite(minimum) && minimum < 0;
}

const MAX_PLAIN_NUMBER_DIGITS = 12;

function plainNumberTextIsWithinDigitLimit(value, maximumDigits = MAX_PLAIN_NUMBER_DIGITS) {
    return (String(value ?? '').match(/\d/g) || []).length <= maximumDigits;
}

function projectNumberInputValue(input, insertedText) {
    const current = String(input?.value ?? '');
    const start = Number.isInteger(input?.selectionStart) ? input.selectionStart : current.length;
    const end = Number.isInteger(input?.selectionEnd) ? input.selectionEnd : start;
    return `${current.slice(0, start)}${insertedText}${current.slice(end)}`;
}

function numberInputExceedsDeclaredMaximum(input, value) {
    if (!input?.hasAttribute?.('max')) return false;
    const maximum = Number(input.getAttribute('max'));
    const candidate = Number(String(value ?? '').trim());
    return Number.isFinite(maximum) && Number.isFinite(candidate) && candidate > maximum;
}

/**
 * Native number inputs accept exponent notation (for example 2e10). The
 * application stores ordinary business decimals, so block exponent and plus
 * notation everywhere. A minus sign is blocked only when the field declares a
 * non-negative minimum; genuine readings such as freezing points remain valid.
 */
function installPlainNumberInputGuards() {
    if (typeof document === 'undefined') return;
    const selector = 'input[type="number"]';
    const containsForbiddenCharacters = (input, text) => {
        const value = String(text ?? '');
        if (/[eE+]/.test(value)) return true;
        return value.includes('-') && !numberInputAllowsNegative(input);
    };

    document.addEventListener('keydown', (event) => {
        const input = event.target;
        if (!input?.matches?.(selector)) return;
        const inserted = event.key.length === 1 ? event.key : '';
        const projected = projectNumberInputValue(input, inserted);
        if (inserted && (containsForbiddenCharacters(input, inserted)
            || !plainNumberTextIsWithinDigitLimit(projected)
            || numberInputExceedsDeclaredMaximum(input, projected))) {
            event.preventDefault();
        }
    }, true);

    document.addEventListener('beforeinput', (event) => {
        const input = event.target;
        if (!input?.matches?.(selector) || event.data == null) return;
        const projected = projectNumberInputValue(input, event.data);
        if (containsForbiddenCharacters(input, event.data)
            || !plainNumberTextIsWithinDigitLimit(projected)
            || numberInputExceedsDeclaredMaximum(input, projected)) {
            event.preventDefault();
        }
    }, true);

    document.addEventListener('paste', (event) => {
        const input = event.target;
        if (!input?.matches?.(selector)) return;
        const pasted = event.clipboardData?.getData('text') ?? '';
        const projected = projectNumberInputValue(input, pasted);
        if (containsForbiddenCharacters(input, pasted)
            || !plainNumberTextIsWithinDigitLimit(projected)
            || numberInputExceedsDeclaredMaximum(input, projected)) {
            event.preventDefault();
            input.setCustomValidity(input.hasAttribute('max')
                ? `Enter a regular number no greater than ${input.getAttribute('max')}.`
                : `Enter a regular number using at most ${MAX_PLAIN_NUMBER_DIGITS} digits and no exponent notation.`);
            input.reportValidity?.();
            setTimeout(() => input.setCustomValidity(''), 1500);
        }
    }, true);

    // Fallback for browsers or accessibility tools that bypass beforeinput.
    document.addEventListener('input', (event) => {
        const input = event.target;
        if (!input?.matches?.(selector) || !numberInputExceedsDeclaredMaximum(input, input.value)) return;
        input.value = input.getAttribute('max');
        input.setCustomValidity(`Maximum allowed: ${input.getAttribute('max')}.`);
        input.reportValidity?.();
        setTimeout(() => input.setCustomValidity(''), 1500);
    }, true);
}

function personNameHasLetter(value) {
    const name = String(value ?? '').trim();
    if (!name) return false;
    try {
        return /\p{L}/u.test(name);
    } catch (error) {
        return /[A-Za-z]/.test(name);
    }
}

function installPersonNameInputGuards() {
    if (typeof document === 'undefined') return;
    const selector = 'input[data-person-name]';
    const validate = (input) => {
        const value = String(input.value ?? '').trim();
        const valid = value === '' || personNameHasLetter(value);
        input.setCustomValidity(valid ? '' : 'Enter a person name containing at least one letter.');
    };

    document.querySelectorAll(selector).forEach(validate);
    document.addEventListener('input', (event) => {
        if (event.target?.matches?.(selector)) validate(event.target);
    }, true);
}

function normalizeBankAccountNumber(value, maximumDigits = 20) {
    return String(value ?? '').replace(/\D/g, '').slice(0, maximumDigits);
}

function constrainBankAccountInput(input) {
    if (!input) return '';
    const normalized = normalizeBankAccountNumber(input.value);
    if (input.value !== normalized) input.value = normalized;
    input.inputMode = 'numeric';
    input.maxLength = 20;
    input.setAttribute('pattern', '[0-9]{6,20}');
    input.setAttribute('title', 'Enter 6 to 20 digits');
    input.setAttribute('autocomplete', 'off');
    return normalized;
}

function installBankAccountInputLimits() {
    if (typeof document === 'undefined') return;
    const selector = 'input[data-bank-account]';
    document.querySelectorAll(selector).forEach(constrainBankAccountInput);
    document.addEventListener('input', (event) => {
        if (event.target?.matches?.(selector)) constrainBankAccountInput(event.target);
    }, true);
}

/**
 * Keep notifications in the same browser top layer as the newest modal.
 * A z-index alone cannot place a normal element above a modal <dialog>.
 */
function installHighlandNotificationLayer() {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return null;
    }
    if (window.HighlandNotificationLayer?.version >= 2) {
        window.HighlandNotificationLayer.sync();
        return window.HighlandNotificationLayer;
    }

    const openOrder = new WeakMap();
    const modalDialogs = new WeakSet();
    let sequence = 0;

    const isModalDialog = (dialog) => {
        if (!(dialog instanceof HTMLDialogElement) || !dialog.open) return false;
        if (modalDialogs.has(dialog)) return true;
        try {
            return dialog.matches(':modal');
        } catch (error) {
            return false;
        }
    };

    const rememberOpenDialogs = () => {
        document.querySelectorAll('dialog[open]').forEach((dialog) => {
            if (isModalDialog(dialog) && !openOrder.has(dialog)) {
                openOrder.set(dialog, ++sequence);
            }
        });
    };

    const getTopDialog = () => {
        rememberOpenDialogs();
        return Array.from(document.querySelectorAll('dialog[open]'))
            .filter(isModalDialog)
            .sort((a, b) => (openOrder.get(a) || 0) - (openOrder.get(b) || 0))
            .pop() || null;
    };

    const getContainers = () => Array.from(document.querySelectorAll(
        '#toastContainer, .notification-container, [data-notification-layer]'
    ));

    const sync = () => {
        if (!document.body) return;
        const host = getTopDialog() || document.body;
        getContainers().forEach((container) => {
            if (container.parentElement !== host) host.appendChild(container);
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-relevant', 'additions');
        });
    };

    const style = document.getElementById('highland-notification-layer-style')
        || document.createElement('style');
    style.id = 'highland-notification-layer-style';
    style.textContent = `
        #toastContainer,
        .notification-container,
        [data-notification-layer] {
            position: fixed !important;
            top: 1rem !important;
            right: 1rem !important;
            bottom: auto !important;
            left: auto !important;
            z-index: 2147483647 !important;
            width: min(26rem, calc(100vw - 2rem));
            max-width: calc(100vw - 2rem);
            pointer-events: none;
        }
        #toastContainer.toast-bottom {
            top: auto !important;
            bottom: 1rem !important;
        }
        #toastContainer > *,
        .notification-container > *,
        [data-notification-layer] > * {
            width: 100%;
            pointer-events: auto;
            overflow-wrap: anywhere;
        }
    `;
    if (!style.isConnected) document.head.appendChild(style);

    if (typeof HTMLDialogElement !== 'undefined') {
        const prototype = HTMLDialogElement.prototype;
        if (!prototype.__highlandLayerPatched) {
            const nativeShowModal = prototype.showModal;
            const nativeClose = prototype.close;

            prototype.showModal = function (...args) {
                const result = nativeShowModal.apply(this, args);
                modalDialogs.add(this);
                openOrder.set(this, ++sequence);
                sync();
                return result;
            };
            prototype.close = function (...args) {
                const result = nativeClose.apply(this, args);
                openOrder.delete(this);
                sync();
                return result;
            };
            Object.defineProperty(prototype, '__highlandLayerPatched', {
                value: true,
                configurable: false,
                enumerable: false,
                writable: false
            });
        }
    }

    const observer = new MutationObserver((records) => {
        records.forEach((record) => {
            if (record.type !== 'attributes' || !(record.target instanceof HTMLDialogElement)) return;
            if (isModalDialog(record.target)) {
                if (!openOrder.has(record.target)) openOrder.set(record.target, ++sequence);
            } else {
                openOrder.delete(record.target);
            }
        });
        sync();
    });
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['open'],
        childList: true,
        subtree: true
    });

    document.addEventListener('close', sync, true);
    const api = { version: 2, sync, getTopDialog };
    window.HighlandNotificationLayer = api;
    window.HighlandSecurity = Object.assign(window.HighlandSecurity || {}, {
        escapeHtml,
        renderToastContent
    });
    sync();
    return api;
}

if (typeof window !== 'undefined') {
    window.HighlandSecurity = Object.assign(window.HighlandSecurity || {}, {
        escapeHtml,
        renderToastContent,
        normalizePhilippinePhoneInput,
        constrainPhilippinePhoneInput,
        numberInputAllowsNegative,
        numberInputExceedsDeclaredMaximum,
        plainNumberTextIsWithinDigitLimit,
        personNameHasLetter,
        normalizeBankAccountNumber,
        constrainBankAccountInput
    });
    installHighlandNotificationLayer();
    installPhilippinePhoneInputLimits();
    installPlainNumberInputGuards();
    installPersonNameInputGuards();
    installBankAccountInputLimits();
}

// API Base URL
const API_BASE_URL = APP_BASE + '/api';

// ApiConfig for fetch-based services (used by admin.service.js)
const ApiConfig = {
    baseUrl: API_BASE_URL,
    getHeaders() {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        const token = localStorage.getItem('highland_token');
        if (token) {
            // PHP-FPM (InfinityFree) does NOT forward the Authorization
            // request header to PHP, so also send the token in X-Auth-Token
            // (a custom header that PHP-FPM forwards as HTTP_X_AUTH_TOKEN).
            // Backend (Auth::extractBearerToken) reads from either, or the
            // highland_token cookie as a last-resort fallback.
            headers['Authorization'] = `Bearer ${token}`;
            headers['X-Auth-Token'] = token;
        }
        return headers;
    }
};

// Create Axios instance with default configuration (if axios is available)
let api = null;

if (typeof axios !== 'undefined') {
    api = axios.create({
        baseURL: API_BASE_URL,
        timeout: 30000,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    });

    // Request interceptor - add auth token and handle method override for nginx
    api.interceptors.request.use(
        (config) => {
            const token = localStorage.getItem('highland_token');
            if (token) {
                // See ApiConfig.getHeaders comment for why both Authorization
                // and X-Auth-Token are sent (PHP-FPM workaround).
                config.headers.Authorization = `Bearer ${token}`;
                config.headers['X-Auth-Token'] = token;
            }
            
            // Convert PUT/DELETE to POST with X-HTTP-Method-Override for nginx compatibility
            if (config.method === 'put' || config.method === 'delete') {
                config.headers['X-HTTP-Method-Override'] = config.method.toUpperCase();
                config.method = 'post';
            }
            
            return config;
        },
        (error) => {
            return Promise.reject(error);
        }
    );

    // Response interceptor - handle errors
    api.interceptors.response.use(
        (response) => {
            return response.data;
        },
        (error) => {
            if (error.code === 'ECONNABORTED') {
                return Promise.reject({
                    type: 'timeout',
                    message: 'The server took too long to answer. Please check that Apache and MySQL are running, then try again.'
                });
            }

            if (error.response) {
                // Server responded with error
                const { status, data } = error.response;
            
            if (status === 401) {
                // Unauthorized - redirect to login
                localStorage.removeItem('highland_token');
                localStorage.removeItem('highland_user');
                localStorage.removeItem('highland_must_change_password');
                localStorage.removeItem('highland_session_started_at');
                localStorage.removeItem('highland_session_expires_at');
                localStorage.removeItem('highland_last_activity_at');
                localStorage.removeItem('highland_idle_timeout_ms');
                document.cookie = 'highland_token=; path=/; SameSite=Lax; Max-Age=0';
                window.location.href = APP_BASE + '/html/login.html';
            } else if (status === 403) {
                // Forbidden - show access denied
                showNotification('Access Denied', 'You do not have permission to perform this action.', 'error');
            } else if (status === 422) {
                // Validation error
                return Promise.reject({
                    type: 'validation',
                    errors: data.errors,
                    message: data.message
                });
            }
            
            return Promise.reject({
                type: 'error',
                message: data.message || 'An error occurred',
                status: status
            });
        } else if (error.request) {
            // Network error
            const serverAddress = typeof window !== 'undefined'
                ? `${window.location.origin}${API_BASE_URL}`
                : API_BASE_URL;
            return Promise.reject({
                type: 'network',
                message: `Cannot reach the server at ${serverAddress}. Make sure this device is on the same network and the server computer has Apache and MySQL running.`
            });
        }
        
        return Promise.reject(error);
        }
    );
}

/**
 * Show notification toast
 */
function showNotification(title, message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    const iconWrap = document.createElement('div');
    iconWrap.className = 'notification-icon';
    const icon = document.createElement('i');
    icon.className = `fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}`;
    iconWrap.appendChild(icon);

    const content = document.createElement('div');
    content.className = 'notification-content';
    const titleElement = document.createElement('div');
    titleElement.className = 'notification-title';
    titleElement.textContent = String(title ?? '');
    const messageElement = document.createElement('div');
    messageElement.className = 'notification-message';
    messageElement.textContent = String(message ?? '');
    content.append(titleElement, messageElement);

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'notification-close';
    closeButton.setAttribute('aria-label', 'Dismiss notification');
    closeButton.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';
    closeButton.addEventListener('click', () => notification.remove());
    notification.append(iconWrap, content, closeButton);
    
    // Add to container or create one
    let container = document.querySelector('.notification-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'notification-container';
        document.body.appendChild(container);
    }

    window.HighlandNotificationLayer?.sync();
    container.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.classList.add('notification-fade-out');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

/**
 * Format currency
 */
function formatCurrency(amount, currency = '₱') {
    return currency + parseFloat(amount || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/**
 * Format date
 */
function formatDate(dateString, format = 'medium') {
    if (!dateString) return '-';
    const date = new Date(dateString);
    
    const options = {
        short: { month: 'short', day: 'numeric' },
        medium: { year: 'numeric', month: 'short', day: 'numeric' },
        long: { year: 'numeric', month: 'long', day: 'numeric' },
        full: { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }
    };
    
    return date.toLocaleDateString('en-PH', options[format] || options.medium);
}

/**
 * Format time
 */
function formatTime(timeString) {
    if (!timeString) return '-';
    const [hours, minutes] = timeString.split(':');
    const date = new Date();
    date.setHours(parseInt(hours), parseInt(minutes));
    return date.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}

/**
 * Get grade badge class
 */
function getGradeBadgeClass(grade) {
    const classes = {
        'A': 'badge-success',
        'B': 'badge-info',
        'C': 'badge-warning',
        'D': 'badge-orange',
        'Rejected': 'badge-danger'
    };
    return classes[grade] || 'badge-secondary';
}

/**
 * Get status badge class
 */
function getStatusBadgeClass(status) {
    const classes = {
        'pending_test': 'badge-warning',
        'accepted': 'badge-success',
        'rejected': 'badge-danger',
        'pending_qc': 'badge-warning',
        'released': 'badge-success',
        'qc_rejected': 'badge-danger',
        'available': 'badge-success',
        'expired': 'badge-danger',
        'critical': 'badge-danger',
        'warning': 'badge-warning',
        'ok': 'badge-success'
    };
    return classes[status] || 'badge-secondary';
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        api,
        APP_BASE,
        API_BASE_URL,
        resolveAppBase,
        escapeHtml,
        renderToastContent,
        normalizePhilippinePhoneInput,
        constrainPhilippinePhoneInput,
        installPhilippinePhoneInputLimits,
        numberInputAllowsNegative,
        numberInputExceedsDeclaredMaximum,
        plainNumberTextIsWithinDigitLimit,
        installPlainNumberInputGuards,
        personNameHasLetter,
        installPersonNameInputGuards,
        normalizeBankAccountNumber,
        constrainBankAccountInput,
        installBankAccountInputLimits,
        installHighlandNotificationLayer,
        showNotification,
        formatCurrency,
        formatDate,
        formatTime,
        getGradeBadgeClass,
        getStatusBadgeClass
    };
}
