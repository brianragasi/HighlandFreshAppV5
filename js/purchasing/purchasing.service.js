/**
 * Highland Fresh System - Purchasing Service
 * 
 * API client for the Purchasing module
 * 
 * @package HighlandFresh
 * @version 4.0
 */

const PurchasingService = {

    // ========================================
    // DASHBOARD
    // ========================================

    async getDashboardStats() {
        return await api.get('/purchasing/dashboard.php?action=stats');
    },

    async getLowStockAlerts() {
        return await api.get('/purchasing/dashboard.php?action=low_stock');
    },

    async getRecentPOs(limit = 10) {
        return await api.get(`/purchasing/dashboard.php?action=recent_pos&limit=${limit}`);
    },

    async getPendingRequisitions() {
        return await api.get('/purchasing/dashboard.php?action=pending_requisitions');
    },

    async getMonthlySpending(months = 6) {
        return await api.get(`/purchasing/dashboard.php?action=monthly_spending&months=${months}`);
    },

    // ========================================
    // SUPPLIERS
    // ========================================

    async getSuppliers(filters = {}) {
        const params = new URLSearchParams({ action: 'list', ...filters });
        return await api.get(`/purchasing/suppliers.php?${params}`);
    },

    async getSupplierDetail(id) {
        return await api.get(`/purchasing/suppliers.php?action=detail&id=${id}`);
    },

    async searchSuppliers(query) {
        return await api.get(`/purchasing/suppliers.php?action=search&q=${encodeURIComponent(query)}`);
    },

    async createSupplier(data) {
        return await api.post('/purchasing/suppliers.php?action=create', data);
    },

    async updateSupplier(id, data) {
        return await api.put(`/purchasing/suppliers.php?action=update&id=${id}`, data);
    },

    async toggleSupplierStatus(id) {
        return await api.put(`/purchasing/suppliers.php?action=toggle_status&id=${id}`, {});
    },

    // ========================================
    // PURCHASE ORDERS
    // ========================================

    async getPurchaseOrders(filters = {}) {
        const params = new URLSearchParams({ action: 'list', ...filters });
        return await api.get(`/purchasing/purchase_orders.php?${params}`);
    },

    async getPurchaseOrderDetail(id) {
        return await api.get(`/purchasing/purchase_orders.php?action=detail&id=${id}`);
    },

    async getNextPONumber() {
        return await api.get('/purchasing/purchase_orders.php?action=next_number');
    },

    async createPurchaseOrder(data) {
        return await api.post('/purchasing/purchase_orders.php?action=create', data);
    },

    async submitPO(id) {
        return await api.put(`/purchasing/purchase_orders.php?action=submit&id=${id}`, {});
    },

    async approvePO(id) {
        return await api.put(`/purchasing/purchase_orders.php?action=approve&id=${id}`, {});
    },

    async rejectPO(id, reason) {
        return await api.put(`/purchasing/purchase_orders.php?action=reject&id=${id}`, { reason });
    },

    async markPOOrdered(id) {
        return await api.put(`/purchasing/purchase_orders.php?action=mark_ordered&id=${id}`, {});
    },

    async markPOReceived(id) {
        return await api.put(`/purchasing/purchase_orders.php?action=mark_received&id=${id}`, {});
    },

    async cancelPO(id, reason) {
        return await api.put(`/purchasing/purchase_orders.php?action=cancel&id=${id}`, { reason });
    },

    async updatePaymentStatus(id, paymentStatus) {
        return await api.put(`/purchasing/purchase_orders.php?action=update_payment&id=${id}`, { payment_status: paymentStatus });
    },

    async receivePOWithPrices(id, priceUpdates = []) {
        return await api.put(`/purchasing/purchase_orders.php?action=receive_with_prices&id=${id}`, { price_updates: priceUpdates });
    },

    // ========================================
    // CANVASSING (Rule of 3)
    // ========================================

    async getCanvassList(filters = {}) {
        const params = new URLSearchParams({ action: 'list', ...filters });
        return await api.get(`/purchasing/canvassing.php?${params}`);
    },

    async getCanvassDetail(id) {
        return await api.get(`/purchasing/canvassing.php?action=detail&id=${id}`);
    },

    async createCanvass(data) {
        return await api.post('/purchasing/canvassing.php?action=create', data);
    },

    async addCanvassQuote(data) {
        return await api.post('/purchasing/canvassing.php?action=add_quote', data);
    },

    async selectCanvassQuote(quoteId) {
        return await api.put('/purchasing/canvassing.php?action=select_quote', { quote_id: quoteId });
    },

    async cancelCanvass(id) {
        return await api.put(`/purchasing/canvassing.php?action=cancel&id=${id}`, {});
    },

    async getPriceHistory(type, itemId, limit = 20) {
        return await api.get(`/purchasing/canvassing.php?action=price_history&type=${type}&item_id=${itemId}&limit=${limit}`);
    },

    // ========================================
    // GM APPROVALS
    // ========================================

    async getGMDashboard() {
        return await api.get('/admin/gm_approvals.php?action=dashboard');
    },

    async getGMPendingPOs() {
        return await api.get('/admin/gm_approvals.php?action=pending_pos');
    },

    async getGMPendingRequisitions() {
        return await api.get('/admin/gm_approvals.php?action=pending_requisitions');
    },

    async getGMAllPending() {
        return await api.get('/admin/gm_approvals.php?action=all_pending');
    },

    async getGMPriceAlerts() {
        return await api.get('/admin/gm_approvals.php?action=price_alerts');
    },

    // ========================================
    // HELPERS
    // ========================================

    getStatusBadgeClass(status) {
        const map = {
            'draft': 'badge-ghost',
            'pending': 'badge-warning',
            'approved': 'badge-info',
            'ordered': 'badge-primary',
            'partial_received': 'badge-accent',
            'received': 'badge-success',
            'cancelled': 'badge-error',
            'fulfilled': 'badge-success',
            'rejected': 'badge-error',
        };
        return map[status] || 'badge-ghost';
    },

    getPaymentBadgeClass(status) {
        const map = {
            'unpaid': 'badge-error',
            'partial': 'badge-warning',
            'paid': 'badge-success',
        };
        return map[status] || 'badge-ghost';
    },

    getPriorityBadgeClass(priority) {
        const map = {
            'low': 'badge-ghost',
            'normal': 'badge-info',
            'high': 'badge-warning',
            'urgent': 'badge-error',
        };
        return map[priority] || 'badge-ghost';
    },

    getStockStatusBadge(status) {
        const map = {
            'critical': { class: 'badge-error', label: 'Critical' },
            'low': { class: 'badge-warning', label: 'Low Stock' },
            'reorder': { class: 'badge-info', label: 'Reorder' },
            'ok': { class: 'badge-success', label: 'OK' },
        };
        return map[status] || { class: 'badge-ghost', label: status };
    },

    formatStatus(status) {
        return status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    },

    formatPaymentTerms(terms) {
        const map = {
            'cash': 'Cash (COD)',
            'credit_7': 'Credit - 7 Days',
            'credit_15': 'Credit - 15 Days',
            'credit_30': 'Credit - 30 Days',
            'credit_45': 'Credit - 45 Days',
            'credit_60': 'Credit - 60 Days',
        };
        return map[terms] || terms;
    },

    getPaymentTermsBadgeClass(terms) {
        if (terms === 'cash') return 'badge-success';
        return 'badge-warning';
    }
};
