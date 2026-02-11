/**
 * Highland Fresh - Finance Service
 * API communication layer for the Finance module
 * Uses the global `api` axios instance from api.js
 */

const FinanceService = {

    // ========================================
    // DASHBOARD
    // ========================================

    getDashboardStats() {
        return api.get('/finance/dashboard.php?action=stats');
    },

    getPayablesSummary() {
        return api.get('/finance/dashboard.php?action=payables_summary');
    },

    getCollectionsSummary(period = 'month') {
        return api.get(`/finance/dashboard.php?action=collections_summary&period=${period}`);
    },

    getFarmerPaymentSummary() {
        return api.get('/finance/dashboard.php?action=farmer_payment_summary');
    },

    getRecentDisbursements(limit = 10) {
        return api.get(`/finance/dashboard.php?action=recent_disbursements&limit=${limit}`);
    },

    getReceivablesAging() {
        return api.get('/finance/dashboard.php?action=receivables_aging');
    },

    // ========================================
    // PAYABLES
    // ========================================

    getPayables(filters = {}) {
        const params = new URLSearchParams({ action: 'list', ...filters });
        return api.get(`/finance/payables.php?${params}`);
    },

    getPayableDetail(id) {
        return api.get(`/finance/payables.php?action=detail&id=${id}`);
    },

    getSupplierLedger(supplierId) {
        return api.get(`/finance/payables.php?action=supplier_ledger&supplier_id=${supplierId}`);
    },

    recordPayment(data) {
        return api.post('/finance/payables.php?action=record_payment', data);
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
            'active': 'badge-success',
            'voided': 'badge-error'
        };
        return map[status] || 'badge-ghost';
    },

    getPaymentBadgeClass(status) {
        const map = {
            'unpaid': 'badge-error',
            'partial': 'badge-warning',
            'paid': 'badge-success'
        };
        return map[status] || 'badge-ghost';
    },

    formatStatus(status) {
        if (!status) return '-';
        return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
};
