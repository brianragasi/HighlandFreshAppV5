/**
 * Highland Fresh - Production Module Services
 * 
 * API service layer for production management
 * Uses the global 'api' object from api.js which handles auth tokens
 * 
 * @version 4.0
 */

const ProductionService = {
    baseUrl: '/production',

    // ========================================
    // Dashboard
    // ========================================
    
    /**
     * Get production dashboard statistics
     */
    async getDashboardStats() {
        return await api.get(`${this.baseUrl}/dashboard.php`);
    },

    // ========================================
    // Recipes
    // ========================================
    
    /**
     * Get all recipes
     * @param {Object} params - Filter parameters
     */
    async getRecipes(params = {}) {
        return await api.get(`${this.baseUrl}/recipes.php`, { params });
    },

    /**
     * Get single recipe with ingredients
     * @param {number} id - Recipe ID
     */
    async getRecipe(id) {
        return await api.get(`${this.baseUrl}/recipes.php`, { params: { id } });
    },

    /**
     * Create new recipe (GM only)
     * @param {Object} recipeData - Recipe data with ingredients
     */
    async createRecipe(recipeData) {
        return await api.post(`${this.baseUrl}/recipes.php`, recipeData);
    },

    // ========================================
    // Production Runs
    // ========================================
    
    /**
     * Get all production runs
     * @param {Object} params - Filter parameters
     */
    async getRuns(params = {}) {
        return await api.get(`${this.baseUrl}/runs.php`, { params });
    },

    /**
     * Get single production run with details
     * @param {number} id - Run ID
     */
    async getRun(id) {
        return await api.get(`${this.baseUrl}/runs.php`, { params: { id } });
    },

    /**
     * Create new production run
     * @param {Object} runData - Run data
     */
    async createRun(runData) {
        return await api.post(`${this.baseUrl}/runs.php`, runData);
    },

    /**
     * Start a production run
     * @param {number} id - Run ID
     */
    async startRun(id) {
        return await api.put(`${this.baseUrl}/runs.php`, { id, action: 'start' });
    },

    /**
     * Update run status during production
     * @param {number} id - Run ID
     * @param {string} status - New status
     */
    async updateRunStatus(id, status) {
        return await api.put(`${this.baseUrl}/runs.php`, { id, action: 'update_status', status });
    },

    /**
     * Complete a production run
     * @param {number} id - Run ID
     * @param {number} actualQuantity - Actual output quantity
     * @param {string} varianceReason - Reason for variance (optional)
     */
    async completeRun(id, actualQuantity, varianceReason = '') {
        return await api.put(`${this.baseUrl}/runs.php`, {
            id,
            action: 'complete',
            actual_quantity: actualQuantity,
            variance_reason: varianceReason
        });
    },

    /**
     * Cancel a production run
     * @param {number} id - Run ID
     */
    async cancelRun(id) {
        return await api.put(`${this.baseUrl}/runs.php`, { id, action: 'cancel' });
    },

    /**
     * Get available QC-approved milk for production
     */
    async getAvailableMilk() {
        return await api.get(`${this.baseUrl}/runs.php`, { params: { action: 'available_milk' } });
    },

    // ========================================
    // CCP Logs
    // ========================================
    
    /**
     * Get CCP logs
     * @param {Object} params - Filter parameters
     */
    async getCCPLogs(params = {}) {
        return await api.get(`${this.baseUrl}/ccp_logs.php`, { params });
    },

    /**
     * Record CCP check
     * @param {Object} logData - CCP log data
     */
    async recordCCPCheck(logData) {
        return await api.post(`${this.baseUrl}/ccp_logs.php`, logData);
    },

    // ========================================
    // Requisitions
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
     * Create new requisition
     * @param {Object} requisitionData - Requisition data with items
     */
    async createRequisition(requisitionData) {
        return await api.post(`${this.baseUrl}/requisitions.php`, requisitionData);
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
    async rejectRequisition(id, reason = '') {
        return await api.put(`${this.baseUrl}/requisitions.php`, {
            id,
            action: 'reject',
            rejection_reason: reason
        });
    },

    /**
     * Fulfill requisition (Warehouse only)
     * @param {number} id - Requisition ID
     */
    async fulfillRequisition(id) {
        return await api.put(`${this.baseUrl}/requisitions.php`, { id, action: 'fulfill' });
    },

    /**
     * Cancel requisition
     * @param {number} id - Requisition ID
     */
    async cancelRequisition(id) {
        return await api.put(`${this.baseUrl}/requisitions.php`, { id, action: 'cancel' });
    },

    // ========================================
    // Byproducts
    // ========================================
    
    /**
     * Get byproducts
     * @param {Object} params - Filter parameters
     */
    async getByproducts(params = {}) {
        return await api.get(`${this.baseUrl}/byproducts.php`, { params });
    },

    /**
     * Record byproduct
     * @param {Object} byproductData - Byproduct data
     */
    async recordByproduct(byproductData) {
        return await api.post(`${this.baseUrl}/byproducts.php`, byproductData);
    },

    /**
     * Transfer byproduct to warehouse
     * @param {number} id - Byproduct ID
     */
    async transferByproduct(id) {
        return await api.put(`${this.baseUrl}/byproducts.php`, { id, action: 'transfer_to_warehouse' });
    }
};
