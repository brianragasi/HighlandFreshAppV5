/**
 * Highland Fresh System - Authentication Service
 * 
 * @package HighlandFresh
 * @version 4.0
 */

const AuthService = {
    /**
     * Login user
     */
    async login(username, password) {
        const response = await api.post('/auth/login.php', { username, password });
        
        if (response.success) {
            // Store token and user data
            localStorage.setItem('highland_token', response.data.token);
            localStorage.setItem('highland_user', JSON.stringify(response.data.user));
        }
        
        return response;
    },
    
    /**
     * Logout user
     */
    logout() {
        localStorage.removeItem('highland_token');
        localStorage.removeItem('highland_user');
        window.location.href = APP_BASE + '/html/login.html';
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
     * Require authentication - redirect if not logged in
     */
    requireAuth() {
        if (!this.isAuthenticated()) {
            window.location.href = APP_BASE + '/html/login.html';
            return false;
        }
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
    }
};
