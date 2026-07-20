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

    async getIngredientCatalog() {
        return await api.get('/warehouse/raw/ingredients.php', { params: { action: 'list' } });
    },

    async getMroCatalog() {
        return await api.get('/warehouse/raw/mro.php', { params: { action: 'list' } });
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

    /**
     * Phase 1 (multi-supplier): create one or more POs from a single approved PR.
     * Items with the same supplier are consolidated into a single PO;
     * items with different suppliers yield separate POs in one transaction.
     * Backend route: POST /purchasing/purchase_orders.php?action=create_from_pr
     * Expected payload:
     *   {
     *     purchase_request_id: <int>,
     *     payment_terms, order_date, expected_delivery, delivery_details, notes,
     *     items: [
     *       { purchase_request_item_id, supplier_id, unit_price, quantity?, is_vat_item?, notes? }, ...
     *     ]
     *   }
     */
    async createPurchaseOrdersFromPR(data) {
        return await api.post('/purchasing/purchase_orders.php?action=create_from_pr', data);
    },


    async submitPO(id) {
        return await api.put(`/purchasing/purchase_orders.php?action=submit&id=${id}`, {});
    },

    async approvePO(id, stepUpToken, approvalRemarks = '') {
        return await api.put(`/purchasing/purchase_orders.php?action=approve&id=${id}`, {
            step_up_token: stepUpToken,
            approval_remarks: approvalRemarks
        });
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

    async closePO(id) {
        return await api.put(`/purchasing/purchase_orders.php?action=close&id=${id}`, {});
    },

    async updatePaymentStatus(id, paymentStatus) {
        return await api.put(`/purchasing/purchase_orders.php?action=update_payment&id=${id}`, { payment_status: paymentStatus });
    },

    async receivePOWithPrices(id, priceUpdates = [], receivingItems = [], receivingMeta = {}) {
        return await api.put(`/purchasing/purchase_orders.php?action=receive_with_prices&id=${id}`, {
            price_updates: priceUpdates,
            receiving_items: receivingItems,
            receiving_meta: receivingMeta
        });
    },

    /**
     * Receive a PO with per-line evidence photos attached (multipart upload).
     * The order of `evidenceFiles` must match `receivingItems[]`; files for
     * lines with `rejected <= 0` are silently ignored on the server.
     */
    async receivePOWithPricesAndEvidence(id, priceUpdates = [], receivingItems = [], receivingMeta = {}, evidenceFiles = []) {
        const formData = new FormData();
        formData.append('price_updates', JSON.stringify(priceUpdates || []));
        formData.append('receiving_items', JSON.stringify(receivingItems || []));
        formData.append('receiving_meta', JSON.stringify(receivingMeta || {}));
        (evidenceFiles || []).forEach((file) => {
            if (file) formData.append('evidence_photos[]', file);
        });
        // Use PUT with the X-HTTP-Method-Override trick (matches receivePOWithPrices).
        // Forcing the method to 'put' lets the global axios interceptor downgrade it to
        // POST while still sending the X-HTTP-Method-Override header, so the server
        // routes into handlePut where 'receive_with_prices' is implemented.
        return await api.put(`/purchasing/purchase_orders.php?action=receive_with_prices&id=${id}`, formData);
    },

    /**
     * Build the URL for the evidence photo of a supplier rejection. The auth
     * token is appended as ?token= so the URL works as <img src>.
     */
    getRejectionEvidenceUrl(rejectionId) {
        const baseUrl = (typeof api !== 'undefined' && api && api.defaults && api.defaults.baseURL) || '';
        const token = localStorage.getItem('highland_token') || '';
        return `${baseUrl}/purchasing/purchase_orders.php?action=rejection_evidence&id=${encodeURIComponent(rejectionId)}${token ? `&token=${encodeURIComponent(token)}` : ''}`;
    },

    // ========================================
    // PURCHASE REQUESTS (Phase 1 PR Flow)
    // ========================================

    async getPurchaseRequests(filters = {}) {
        const params = new URLSearchParams({ action: 'list', ...filters });
        return await api.get(`/purchasing/purchase_requests.php?${params}`);
    },

    async getPurchaseRequestDetail(id) {
        return await api.get(`/purchasing/purchase_requests.php?action=detail&id=${id}`);
    },

    async getNextPRNumber() {
        return await api.get('/purchasing/purchase_requests.php?action=next_number');
    },

    async createPurchaseRequest(data) {
        return await api.post('/purchasing/purchase_requests.php?action=create', data);
    },

    async updatePurchaseRequest(id, data) {
        return await api.put(`/purchasing/purchase_requests.php?action=update&id=${id}`, data);
    },

    async gmUpdatePurchaseRequest(id, data) {
        return await api.put(`/purchasing/purchase_requests.php?action=gm_update&id=${id}`, data);
    },

    async submitPR(id) {
        return await api.put(`/purchasing/purchase_requests.php?action=submit&id=${id}`, {});
    },

    async reopenPR(id, reason) {
        return await api.put(`/purchasing/purchase_requests.php?action=reopen&id=${id}`, { reason });
    },

    async approvePR(id, approvalRemarks = '') {
        return await api.put(`/purchasing/purchase_requests.php?action=approve&id=${id}`, { approval_remarks: approvalRemarks });
    },

    async rejectPR(id, reason) {
        return await api.put(`/purchasing/purchase_requests.php?action=reject&id=${id}`, { reason });
    },

    async getApprovedPRsForPO() {
        return await api.get('/purchasing/purchase_requests.php?action=approved_for_po');
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

    async getGMUnifiedQueue() {
        return await api.get('/admin/gm_approvals.php?action=unified_queue');
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

    async getGMPendingPurchaseRequests() {
        return await api.get('/admin/gm_approvals.php?action=pending_purchase_requests');
    },

    async getGMPendingItemRequests() {
        return await api.get('/admin/gm_approvals.php?action=pending_item_requests');
    },

    async createItemRequest(data) {
        return await api.post('/purchasing/item_requests.php?action=create', data);
    },

    async approveItemRequest(id) {
        return await api.put(`/purchasing/item_requests.php?action=approve&id=${id}`, {});
    },

    async rejectItemRequest(id, reason) {
        return await api.put(`/purchasing/item_requests.php?action=reject&id=${id}`, { reason });
    },

    // ========================================
    // HELPERS
    // ========================================

    getStatusBadgeClass(status) {
        const map = {
            'draft': 'badge-ghost',
            'pending': 'badge-warning',
            'approved': 'badge-info',
            'rejected': 'badge-error',
            'ordered': 'badge-primary',
            'partial_received': 'badge-accent',
            'received': 'badge-success',
            'closed': 'badge-neutral',
            'cancelled': 'badge-error',
            'fulfilled': 'badge-success',
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
            'low': { class: 'badge-warning', label: 'Low Stock' },
            'reorder': { class: 'badge-info', label: 'Reorder' },
            'ok': { class: 'badge-success', label: 'OK' },
        };
        return map[status] || { class: 'badge-ghost', label: status };
    },

    formatStatus(status) {
        const labels = {
            'draft': 'Draft',
            'pending': 'Pending GM Approval',
            'approved': 'Approved',
            'rejected': 'Rejected',
            'partial_received': 'Partially Received',
            'received': 'Fully Received',
            'closed': 'Closed',
            'ordered': 'Approved',
            'cancelled': 'Cancelled'
        };
        if (labels[status]) return labels[status];
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
