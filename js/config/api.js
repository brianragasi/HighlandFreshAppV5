/**
 * Highland Fresh System - API Configuration
 * 
 * @package HighlandFresh
 * @version 4.0
 */

// API Base URL
const API_BASE_URL = '/HighlandFreshAppV4/api';

// Create Axios instance with default configuration
const api = axios.create({
    baseURL: API_BASE_URL,
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// Request interceptor - add auth token
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('highland_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
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
        if (error.response) {
            // Server responded with error
            const { status, data } = error.response;
            
            if (status === 401) {
                // Unauthorized - redirect to login
                localStorage.removeItem('highland_token');
                localStorage.removeItem('highland_user');
                window.location.href = '/HighlandFreshAppV4/html/login.html';
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
            return Promise.reject({
                type: 'network',
                message: 'Network error. Please check your connection.'
            });
        }
        
        return Promise.reject(error);
    }
);

/**
 * Show notification toast
 */
function showNotification(title, message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-icon">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        </div>
        <div class="notification-content">
            <div class="notification-title">${title}</div>
            <div class="notification-message">${message}</div>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add to container or create one
    let container = document.querySelector('.notification-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'notification-container';
        document.body.appendChild(container);
    }
    
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
    module.exports = { api, showNotification, formatCurrency, formatDate, formatTime, getGradeBadgeClass, getStatusBadgeClass };
}
