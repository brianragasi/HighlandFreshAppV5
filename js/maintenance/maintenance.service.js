/**
 * Highland Fresh - Maintenance Module Services
 * 
 * API service layer for maintenance management
 * Uses the global 'api' object from api.js which handles auth tokens
 * 
 * @version 4.0
 */

const MaintenanceService = {
    baseUrl: '/maintenance',

    // ========================================
    // Dashboard
    // ========================================

    /**
     * Get maintenance dashboard statistics
     */
    async getDashboardStats() {
        return await api.get(`${this.baseUrl}/dashboard.php`);
    },

    /**
     * Get recent repairs for dashboard
     * @param {number} limit - Number of repairs to fetch
     */
    async getRecentRepairs(limit = 5) {
        return await api.get(`${this.baseUrl}/dashboard.php`, { 
            params: { action: 'recent_repairs', limit } 
        });
    },

    /**
     * Get upcoming maintenance schedule
     * @param {number} days - Days ahead to look
     */
    async getUpcomingMaintenance(days = 7) {
        return await api.get(`${this.baseUrl}/dashboard.php`, { 
            params: { action: 'upcoming_maintenance', days } 
        });
    },

    /**
     * Get low stock MRO items
     */
    async getLowStockMRO() {
        return await api.get(`${this.baseUrl}/dashboard.php`, { 
            params: { action: 'low_stock_mro' } 
        });
    },

    // ========================================
    // Machines/Equipment
    // ========================================

    /**
     * Get all machines
     * @param {Object} params - Filter parameters
     */
    async getMachines(params = {}) {
        return await api.get(`${this.baseUrl}/machines.php`, { params });
    },

    /**
     * Get single machine with details
     * @param {number} id - Machine ID
     */
    async getMachine(id) {
        return await api.get(`${this.baseUrl}/machines.php`, { params: { id } });
    },

    /**
     * Add new machine (GM only)
     * @param {Object} machineData - Machine data
     */
    async createMachine(machineData) {
        return await api.post(`${this.baseUrl}/machines.php`, machineData);
    },

    /**
     * Update machine status
     * @param {number} id - Machine ID
     * @param {string} status - New status
     */
    async updateMachineStatus(id, status) {
        return await api.put(`${this.baseUrl}/machines.php`, { 
            id, action: 'update_status', status 
        });
    },

    /**
     * Record maintenance performed on machine
     * @param {number} id - Machine ID
     */
    async recordMaintenance(id) {
        return await api.put(`${this.baseUrl}/machines.php`, { 
            id, action: 'record_maintenance' 
        });
    },

    /**
     * Update machine details
     * @param {number} id - Machine ID
     * @param {Object} data - Machine data to update
     */
    async updateMachine(id, data) {
        return await api.put(`${this.baseUrl}/machines.php`, { id, ...data });
    },

    // ========================================
    // Repairs
    // ========================================

    /**
     * Get all repairs
     * @param {Object} params - Filter parameters
     */
    async getRepairs(params = {}) {
        return await api.get(`${this.baseUrl}/repairs.php`, { params });
    },

    /**
     * Get single repair with details
     * @param {number} id - Repair ID
     */
    async getRepair(id) {
        return await api.get(`${this.baseUrl}/repairs.php`, { params: { id } });
    },

    /**
     * Report new repair
     * @param {Object} repairData - Repair data
     */
    async createRepair(repairData) {
        return await api.post(`${this.baseUrl}/repairs.php`, repairData);
    },

    /**
     * Start a repair
     * @param {number} id - Repair ID
     */
    async startRepair(id) {
        return await api.put(`${this.baseUrl}/repairs.php`, { id, action: 'start' });
    },

    /**
     * Record diagnosis for repair
     * @param {number} id - Repair ID
     * @param {string} diagnosis - Diagnosis description
     */
    async diagnoseRepair(id, diagnosis) {
        return await api.put(`${this.baseUrl}/repairs.php`, { 
            id, action: 'diagnose', diagnosis 
        });
    },

    /**
     * Mark repair as awaiting parts
     * @param {number} id - Repair ID
     */
    async markAwaitingParts(id) {
        return await api.put(`${this.baseUrl}/repairs.php`, { 
            id, action: 'awaiting_parts' 
        });
    },

    /**
     * Complete a repair
     * @param {number} id - Repair ID
     * @param {Object} completionData - Repair completion details
     */
    async completeRepair(id, completionData) {
        return await api.put(`${this.baseUrl}/repairs.php`, { 
            id, action: 'complete', ...completionData 
        });
    },

    /**
     * Add part used in repair
     * @param {number} repairId - Repair ID
     * @param {number} mroItemId - MRO item ID
     * @param {number} quantityUsed - Quantity used
     */
    async addPartToRepair(repairId, mroItemId, quantityUsed) {
        return await api.put(`${this.baseUrl}/repairs.php`, { 
            id: repairId, 
            action: 'add_part', 
            mro_item_id: mroItemId, 
            quantity_used: quantityUsed 
        });
    },

    /**
     * Cancel a repair
     * @param {number} id - Repair ID
     */
    async cancelRepair(id) {
        return await api.put(`${this.baseUrl}/repairs.php`, { id, action: 'cancel' });
    },

    /**
     * Update repair details
     * @param {number} id - Repair ID
     * @param {Object} data - Repair data to update
     */
    async updateRepair(id, data) {
        return await api.put(`${this.baseUrl}/repairs.php`, { id, ...data });
    },

    // ========================================
    // MRO Requisitions
    // ========================================

    /**
     * Get all requisitions
     * @param {Object} params - Filter parameters
     */
    async getRequisitions(params = {}) {
        return await api.get(`${this.baseUrl}/requisitions.php`, { params });
    },

    /**
     * Get single requisition with items
     * @param {number} id - Requisition ID
     */
    async getRequisition(id) {
        return await api.get(`${this.baseUrl}/requisitions.php`, { params: { id } });
    },

    /**
     * Create new MRO requisition
     * @param {Object} reqData - Requisition data with items
     */
    async createRequisition(reqData) {
        return await api.post(`${this.baseUrl}/requisitions.php`, reqData);
    },

    /**
     * Approve requisition (GM only)
     * @param {number} id - Requisition ID
     */
    async approveRequisition(id) {
        return await api.put(`${this.baseUrl}/requisitions.php`, { id, action: 'approve' });
    },

    /**
     * Reject requisition (GM only)
     * @param {number} id - Requisition ID
     * @param {string} reason - Rejection reason
     */
    async rejectRequisition(id, reason) {
        return await api.put(`${this.baseUrl}/requisitions.php`, { 
            id, action: 'reject', reason 
        });
    },

    /**
     * Fulfill requisition (Warehouse)
     * @param {number} id - Requisition ID
     * @param {Array} items - Items with issued quantities
     */
    async fulfillRequisition(id, items) {
        return await api.put(`${this.baseUrl}/requisitions.php`, { 
            id, action: 'fulfill', items 
        });
    },

    /**
     * Cancel requisition
     * @param {number} id - Requisition ID
     */
    async cancelRequisition(id) {
        return await api.put(`${this.baseUrl}/requisitions.php`, { id, action: 'cancel' });
    },

    // ========================================
    // MRO Inventory (Read-only)
    // ========================================

    /**
     * Get MRO categories
     */
    async getMROCategories() {
        return await api.get(`${this.baseUrl}/mro_inventory.php`, { 
            params: { action: 'categories' } 
        });
    },

    /**
     * Get MRO items
     * @param {Object} params - Filter parameters
     */
    async getMROItems(params = {}) {
        return await api.get(`${this.baseUrl}/mro_inventory.php`, { params });
    },

    /**
     * Get single MRO item with batches and usage
     * @param {number} id - Item ID
     */
    async getMROItem(id) {
        return await api.get(`${this.baseUrl}/mro_inventory.php`, { 
            params: { action: 'detail', id } 
        });
    },

    /**
     * Get low stock MRO items
     */
    async getLowStockItems() {
        return await api.get(`${this.baseUrl}/mro_inventory.php`, { 
            params: { action: 'low_stock' } 
        });
    },

    // ========================================
    // Utility Methods
    // ========================================

    /**
     * Format machine status for display
     * @param {string} status - Machine status
     */
    formatMachineStatus(status) {
        const statusMap = {
            'operational': { text: 'Operational', class: 'badge-success' },
            'needs_maintenance': { text: 'Needs Maintenance', class: 'badge-warning' },
            'under_repair': { text: 'Under Repair', class: 'badge-error' },
            'offline': { text: 'Offline', class: 'badge-neutral' },
            'decommissioned': { text: 'Decommissioned', class: 'badge-ghost' }
        };
        return statusMap[status] || { text: status, class: 'badge-neutral' };
    },

    /**
     * Format repair status for display
     * @param {string} status - Repair status
     */
    formatRepairStatus(status) {
        const statusMap = {
            'reported': { text: 'Reported', class: 'badge-info' },
            'diagnosed': { text: 'Diagnosed', class: 'badge-warning' },
            'in_progress': { text: 'In Progress', class: 'badge-primary' },
            'awaiting_parts': { text: 'Awaiting Parts', class: 'badge-warning' },
            'completed': { text: 'Completed', class: 'badge-success' },
            'cancelled': { text: 'Cancelled', class: 'badge-ghost' }
        };
        return statusMap[status] || { text: status, class: 'badge-neutral' };
    },

    /**
     * Format requisition status for display
     * @param {string} status - Requisition status
     */
    formatRequisitionStatus(status) {
        const statusMap = {
            'pending': { text: 'Pending Approval', class: 'badge-warning' },
            'approved': { text: 'Approved', class: 'badge-info' },
            'rejected': { text: 'Rejected', class: 'badge-error' },
            'fulfilled': { text: 'Fulfilled', class: 'badge-success' },
            'partially_fulfilled': { text: 'Partial', class: 'badge-warning' },
            'cancelled': { text: 'Cancelled', class: 'badge-ghost' }
        };
        return statusMap[status] || { text: status, class: 'badge-neutral' };
    },

    /**
     * Format priority for display
     * @param {string} priority - Priority level
     */
    formatPriority(priority) {
        const priorityMap = {
            'low': { text: 'Low', class: 'badge-ghost' },
            'normal': { text: 'Normal', class: 'badge-info' },
            'high': { text: 'High', class: 'badge-warning' },
            'urgent': { text: 'Urgent', class: 'badge-error' },
            'critical': { text: 'Critical', class: 'badge-error' }
        };
        return priorityMap[priority] || { text: priority, class: 'badge-neutral' };
    }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MaintenanceService;
}
