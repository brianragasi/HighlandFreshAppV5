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

    async getNotifications() {
        return await api.get('/purchasing/dashboard.php?action=notifications');
    },

    async getFoundStockPriceChecks() {
        return await api.get('/purchasing/dashboard.php?action=found_stock_price_checks');
    },

    async verifyFoundStockPrice(data) {
        return await api.post('/purchasing/dashboard.php?action=verify_found_stock_price', data);
    },

    async rejectFoundStockPrice(data) {
        return await api.post('/purchasing/dashboard.php?action=reject_found_stock_price', data);
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

    async updateSupplierItemPrice(data) {
        return await api.put('/purchasing/suppliers.php?action=update_item_price', data);
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

    async getSupplierDeliveryCalendar() {
        return await api.get('/purchasing/purchase_orders.php?action=delivery_calendar');
    },

    async createPurchaseOrder(data) {
        return await api.post('/purchasing/purchase_orders.php?action=create', data);
    },

    /** Create one supplier-specific PO from manually selected remaining PRS items. */
    async createPurchaseOrderFromPRS(data) {
        return await api.post('/purchasing/purchase_orders.php?action=create_from_pr', data);
    },

    /** Create one supplier PO from selected outstanding Warehouse-requested lines. */
    async createSupplierPurchaseOrder(data) {
        return await api.post('/purchasing/purchase_orders.php?action=create_supplier_po', data);
    },

    // Backward-compatible alias for older clients.
    async createPurchaseOrdersFromPR(data) {
        return await this.createPurchaseOrderFromPRS(data);
    },


    async submitPO(id) {
        return await api.put(`/purchasing/purchase_orders.php?action=submit&id=${id}`, {});
    },

    async submitPOGroup(id) {
        return await api.put(`/purchasing/purchase_orders.php?action=submit_pr_group&id=${id}`, {});
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

    async getReceivingReportDetail(id) {
        return await api.get(`/purchasing/purchase_orders.php?action=rr_detail&id=${id}`);
    },

    async verifyReceivingReport(poId, rrId, notes = '', resolution = 'exact_match') {
        return await api.put(`/purchasing/purchase_orders.php?action=verify_rr&id=${poId}`, {
            rr_id: rrId,
            notes,
            resolution
        });
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

    async submitPR(id, data = {}) {
        return await api.put(`/purchasing/purchase_requests.php?action=submit&id=${id}`, data);
    },

    async reopenPR(id, reason) {
        return await api.put(`/purchasing/purchase_requests.php?action=reopen&id=${id}`, { reason });
    },

    async getPRSInbox() {
        return await api.get('/purchasing/purchase_requests.php?action=prs_inbox');
    },

    async getConfirmedLowStock() {
        return await api.get('/warehouse/raw/stock_validations.php?action=inbox');
    },

    async decideConfirmedLowStock(data) {
        return await api.put('/warehouse/raw/stock_validations.php?action=decide', data);
    },

    async getConfirmedStockDecisions() {
        return await api.get('/warehouse/raw/stock_validations.php?action=decisions');
    },

    async getStockValidations() {
        return await api.get('/warehouse/raw/stock_validations.php?action=list');
    },

    async confirmLowStock(data) {
        return await api.post('/warehouse/raw/stock_validations.php?action=validate', data);
    },

    // ========================================
    // REGISTERED SUPPLIER REVIEW
    // ========================================

    async getCanvassList(filters = {}) {
        const params = new URLSearchParams({ action: 'list', ...filters });
        return await api.get(`/purchasing/canvassing.php?${params}`);
    },

    async getCanvassDetail(id) {
        return await api.get(`/purchasing/canvassing.php?action=detail&id=${id}`);
    },

    async getPRSCanvassingWorkbench(prId) {
        return await api.get(`/purchasing/canvassing.php?action=prs_workbench&pr_id=${encodeURIComponent(prId)}`);
    },

    async preparePRSCanvassing(prId) {
        return await api.post('/purchasing/canvassing.php?action=ensure_prs_canvass', {
            purchase_request_id: prId
        });
    },

    async createCanvass(data) {
        return await api.post('/purchasing/canvassing.php?action=create', data);
    },

    async addCanvassQuote(data) {
        return await api.post('/purchasing/canvassing.php?action=add_quote', data);
    },

    async overrideCanvassQuote(quoteId, reason) {
        return await api.put('/purchasing/canvassing.php?action=override_quote', {
            quote_id: quoteId,
            reason
        });
    },

    async confirmLimitedSupplierMarket(canvassId, reason) {
        return await api.put('/purchasing/canvassing.php?action=confirm_limited_market', {
            canvass_id: canvassId,
            reason
        });
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
            'pending': 'border-warning bg-warning/10 text-base-content',
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
            'partial': 'border-warning bg-warning/10 text-base-content',
            'paid': 'badge-success',
        };
        return map[status] || 'badge-ghost';
    },

    getPriorityBadgeClass(priority) {
        const map = {
            'low': 'badge-ghost',
            'normal': 'badge-info',
            'high': 'border-warning bg-warning/10 text-base-content',
            'urgent': 'badge-error',
        };
        return map[priority] || 'badge-ghost';
    },

    getStockStatusBadge(status) {
        const map = {
            'low': { class: 'border-warning bg-warning/10 text-base-content', label: 'Low Stock' },
            'reorder': { class: 'badge-info', label: 'Reorder' },
            'ok': { class: 'badge-success', label: 'OK' },
        };
        return map[status] || { class: 'badge-ghost', label: status };
    },

    formatStatus(status) {
        const labels = {
            'draft': 'Draft',
            'pending': 'Pending GM Approval',
            'approved': 'Approved - Email Retry Needed',
            'rejected': 'Rejected',
            'partial_received': 'Partially Received',
            'received': 'Fully Received',
            'closed': 'Completed',
            'ordered': 'Approved / Sent to Supplier',
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
        return 'border-warning bg-warning/10 text-base-content';
    }
};
