/**
 * Highland Fresh System - Disposal Service
 * 
 * Handles API calls for the QC Disposal/Write-Off module
 * Includes GM approval workflow support
 * 
 * @package HighlandFresh
 * @version 4.0
 */

import api from '../../api.service.js';

class DisposalService {
    constructor() {
        this.baseUrl = '/api/qc/disposals.php';
    }

    // ===================
    // Lookup Data
    // ===================

    /**
     * Get disposal categories and methods for dropdowns
     */
    async getLookupData() {
        return await api.get(this.baseUrl, {
            params: { action: 'lookup' }
        });
    }

    /**
     * Disposal categories with labels
     */
    static CATEGORIES = {
        qc_failed: { label: 'QC Test Failed', icon: 'fa-times-circle', color: 'error' },
        expired: { label: 'Expired', icon: 'fa-calendar-times', color: 'warning' },
        spoiled: { label: 'Spoiled', icon: 'fa-biohazard', color: 'error' },
        contaminated: { label: 'Contaminated', icon: 'fa-skull-crossbones', color: 'error' },
        damaged: { label: 'Damaged', icon: 'fa-box-open', color: 'warning' },
        rejected_receipt: { label: 'Rejected at Receiving', icon: 'fa-truck-loading', color: 'info' },
        production_waste: { label: 'Production Waste', icon: 'fa-industry', color: 'secondary' },
        other: { label: 'Other', icon: 'fa-question-circle', color: 'ghost' }
    };

    /**
     * Disposal methods
     */
    static METHODS = {
        drain: { label: 'Drain (Liquid)', icon: 'fa-faucet' },
        incinerate: { label: 'Incinerate', icon: 'fa-fire' },
        animal_feed: { label: 'Convert to Animal Feed', icon: 'fa-cow' },
        compost: { label: 'Compost', icon: 'fa-seedling' },
        special_waste: { label: 'Special Waste Contractor', icon: 'fa-truck' },
        other: { label: 'Other Method', icon: 'fa-ellipsis-h' }
    };

    /**
     * Source types
     */
    static SOURCE_TYPES = {
        raw_milk: { label: 'Raw Milk Inventory', icon: 'fa-tint' },
        finished_goods: { label: 'Finished Goods', icon: 'fa-box' },
        ingredients: { label: 'Ingredients', icon: 'fa-flask' },
        production_batch: { label: 'Production Batch', icon: 'fa-industry' },
        milk_receiving: { label: 'Milk Receiving', icon: 'fa-truck-loading' }
    };

    /**
     * Status labels and colors
     */
    static STATUSES = {
        pending: { label: 'Pending Approval', color: 'warning', icon: 'fa-clock' },
        approved: { label: 'Approved', color: 'info', icon: 'fa-check' },
        rejected: { label: 'Rejected', color: 'error', icon: 'fa-times' },
        completed: { label: 'Completed', color: 'success', icon: 'fa-check-circle' },
        cancelled: { label: 'Cancelled', color: 'ghost', icon: 'fa-ban' }
    };

    // ===================
    // CRUD Operations
    // ===================

    /**
     * Get all disposals with optional filters
     * @param {Object} params - Filter parameters
     */
    async getAll(params = {}) {
        return await api.get(this.baseUrl, { params });
    }

    /**
     * Get a single disposal by ID
     * @param {number} id - Disposal ID
     */
    async getById(id) {
        return await api.get(this.baseUrl, {
            params: { id }
        });
    }

    /**
     * Get disposal statistics
     * @param {string} period - 'today', 'week', 'month', 'year'
     */
    async getStats(period = 'month') {
        return await api.get(this.baseUrl, {
            params: { action: 'stats', period }
        });
    }

    /**
     * Get pending approvals (for GM dashboard)
     */
    async getPendingApprovals() {
        return await api.get(this.baseUrl, {
            params: { action: 'pending' }
        });
    }

    /**
     * Create a new disposal request
     * @param {Object} data - Disposal data
     */
    async create(data) {
        return await api.post(this.baseUrl, data);
    }

    /**
     * Approve a disposal (GM only)
     * @param {number} id - Disposal ID
     * @param {string} approvalNotes - Optional approval notes
     */
    async approve(id, approvalNotes = '') {
        return await api.put(this.baseUrl, {
            id,
            action: 'approve',
            approval_notes: approvalNotes
        });
    }

    /**
     * Reject a disposal (GM only)
     * @param {number} id - Disposal ID
     * @param {string} rejectionReason - Required rejection reason
     */
    async reject(id, rejectionReason) {
        return await api.put(this.baseUrl, {
            id,
            action: 'reject',
            rejection_reason: rejectionReason
        });
    }

    /**
     * Complete/Execute a disposal
     * @param {number} id - Disposal ID
     * @param {Object} executionData - Witness name, location, notes
     */
    async complete(id, executionData = {}) {
        return await api.put(this.baseUrl, {
            id,
            action: 'complete',
            ...executionData
        });
    }

    /**
     * Cancel a pending disposal
     * @param {number} id - Disposal ID
     */
    async cancel(id) {
        return await api.delete(this.baseUrl, {
            params: { id }
        });
    }

    // ===================
    // Helper Methods
    // ===================

    /**
     * Get category label
     * @param {string} category 
     */
    getCategoryLabel(category) {
        return DisposalService.CATEGORIES[category]?.label || category;
    }

    /**
     * Get category badge HTML
     * @param {string} category 
     */
    getCategoryBadge(category) {
        const cat = DisposalService.CATEGORIES[category];
        if (!cat) return `<span class="badge">${category}</span>`;
        return `<span class="badge badge-${cat.color}">
            <i class="fas ${cat.icon} mr-1"></i>${cat.label}
        </span>`;
    }

    /**
     * Get status badge HTML
     * @param {string} status 
     */
    getStatusBadge(status) {
        const st = DisposalService.STATUSES[status];
        if (!st) return `<span class="badge">${status}</span>`;
        return `<span class="badge badge-${st.color}">
            <i class="fas ${st.icon} mr-1"></i>${st.label}
        </span>`;
    }

    /**
     * Get source type label
     * @param {string} type 
     */
    getSourceTypeLabel(type) {
        return DisposalService.SOURCE_TYPES[type]?.label || type;
    }

    /**
     * Format currency
     * @param {number} amount 
     */
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP'
        }).format(amount);
    }

    /**
     * Format date
     * @param {string} dateStr 
     */
    formatDate(dateStr) {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('en-PH', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    /**
     * Format datetime
     * @param {string} dateStr 
     */
    formatDateTime(dateStr) {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleString('en-PH', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // ===================
    // Quick Disposal Helpers
    // ===================

    /**
     * Create disposal from rejected milk receiving
     * @param {Object} receiving - Milk receiving record
     * @param {string} reason - Disposal reason
     */
    async disposeRejectedMilk(receiving, reason) {
        return await this.create({
            source_type: 'milk_receiving',
            source_id: receiving.id,
            quantity: receiving.volume_liters || receiving.rejected_liters,
            unit: 'liters',
            disposal_category: 'qc_failed',
            disposal_reason: reason || 'QC test failed - ' + (receiving.rejection_reason || 'Not specified'),
            disposal_method: 'drain',
            unit_cost: 30.00,
            notes: `From receiving: ${receiving.receiving_code}`
        });
    }

    /**
     * Create disposal from expired finished goods
     * @param {Object} inventory - FG inventory record
     * @param {number} quantity - Quantity to dispose
     */
    async disposeExpiredProduct(inventory, quantity) {
        return await this.create({
            source_type: 'finished_goods',
            source_id: inventory.id,
            product_name: inventory.product_name,
            quantity: quantity || inventory.quantity_available,
            unit: 'pcs',
            disposal_category: 'expired',
            disposal_reason: `Product expired on ${inventory.expiry_date}`,
            disposal_method: inventory.product_type === 'bottled_milk' ? 'drain' : 'compost',
            unit_cost: inventory.cost_price || 0,
            notes: `Batch: ${inventory.batch_code || 'N/A'}`
        });
    }

    /**
     * Create disposal from failed production batch
     * @param {Object} batch - Production batch record
     * @param {string} reason - QC failure reason
     */
    async disposeFailedBatch(batch, reason) {
        return await this.create({
            source_type: 'production_batch',
            source_id: batch.id,
            product_name: batch.product_name,
            quantity: batch.actual_yield || batch.expected_yield,
            unit: 'pcs',
            disposal_category: 'qc_failed',
            disposal_reason: reason || 'Batch failed QC inspection',
            disposal_method: 'drain',
            notes: `Batch: ${batch.batch_code}`
        });
    }
}

// Export singleton instance
const disposalService = new DisposalService();
export default disposalService;
export { DisposalService };
