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
    async completeRun(id, actualQuantity, varianceReason = '', reconciliationNotes = '') {
        const payload = {
            id,
            action: 'complete',
            actual_quantity: actualQuantity,
            variance_reason: varianceReason
        };
        if (reconciliationNotes) {
            payload.reconciliation_notes = reconciliationNotes;
        }
        return await api.put(`${this.baseUrl}/runs.php`, payload);
    },

    /**
     * Mark material reconciliation for a production run
     * @param {number} id
     * @param {string} notes
     * @param {boolean} force - allow over-tolerance with notes
     */
    async reconcileRun(id, notes = '', force = false) {
        return await api.put(`${this.baseUrl}/runs.php`, {
            id,
            action: 'reconcile',
            reconciliation_notes: notes,
            force: force
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
     * Get available QC-approved milk for production (already issued via requisitions)
     */
    async getAvailableMilk() {
        return await api.get(`${this.baseUrl}/runs.php`, { params: { action: 'available_milk' } });
    },

    /**
     * Get available raw milk in warehouse tanks (for requisition requests)
     * This is milk that production can REQUEST from Warehouse Raw
     */
    async getWarehouseMilk() {
        return await api.get('/api/warehouse/raw/tanks.php', { params: { action: 'available_milk' } });
    },

    /**
     * Get available PASTEURIZED milk for yogurt production
     * Yogurt requires pasteurized milk, not raw milk
     */
    async getAvailablePasteurizedMilk() {
        return await api.get(`${this.baseUrl}/runs.php`, { params: { action: 'available_pasteurized_milk' } });
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
     * Get ingredient catalog for requisition dropdowns
     */
    async getIngredientCatalog() {
        return await api.get('/warehouse/raw/ingredients.php', { params: { action: 'list' } });
    },

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
     * Get recipe-based item requirements for a selected production run
     * @param {number} runId - Production Run ID
     */
    async getRunRecipeRequisitionItems(runId) {
        return await api.get(`${this.baseUrl}/requisitions.php`, {
            params: { action: 'run_recipe_items', run_id: runId }
        });
    },

    /**
     * Get recipe-based item requirements before a production run exists
     * @param {number} recipeId - Recipe ID
     * @param {number} plannedQuantity - Planned output quantity
     */
    async getPlannedRecipeRequisitionItems(recipeId, plannedQuantity) {
        return await api.get(`${this.baseUrl}/requisitions.php`, {
            params: { action: 'planned_recipe_items', recipe_id: recipeId, planned_quantity: plannedQuantity }
        });
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
    },

    // ========================================
    // Ingredients (for requisitions)
    // ========================================

    /**
     * Get all ingredients for requisition dropdown
     * NOTE: Does NOT include raw milk - milk is delivered by farmers, not requested.
     * Per system_context/production_staff.md: Production requests Sugar, Cocoa powder, 
     * Milk powder, Flavorings, Salt, Rennet, etc. from Warehouse.
     */
    async getIngredients() {
        return await api.get('/warehouse/raw/ingredients.php', { params: { action: 'list' } });
    },

    /**
     * Get low stock ingredients (for alerts)
     */
    async getLowStockIngredients() {
        return await api.get('/warehouse/raw/ingredients.php', { params: { action: 'list', low_stock: '1' } });
    },

    // ========================================
    // Production Losses
    // ========================================

    async recordLoss(data) {
        return await api.post(`${this.baseUrl}/losses.php`, data);
    },

    async getLosses(runId, stage = null) {
        const params = { run_id: runId };
        if (stage) params.stage = stage;
        return await api.get(`${this.baseUrl}/losses.php`, { params });
    },

    async deleteLoss(id) {
        return await api.delete(`${this.baseUrl}/losses.php`, { params: { id } });
    },

    // ========================================
    // Yield Calculation
    // ========================================

    async getYield(runId) {
        return await api.get(`${this.baseUrl}/yield.php`, { params: { run_id: runId } });
    },

    async getReconciliation(runId) {
        return await api.get(`${this.baseUrl}/yield.php`, { params: { run_id: runId, action: 'summary' } });
    },

    async recalculateYield(runId) {
        return await api.post(`${this.baseUrl}/yield.php`, { action: 'calculate', production_run_id: runId });
    },

    // ========================================
    // Packaging Estimates
    // ========================================

    async getEstimates(runId) {
        return await api.get(`${this.baseUrl}/packaging-estimate.php`, { params: { run_id: runId } });
    },

    async generateEstimate(runId, estimateType = null, basisVolumeMl = null) {
        const data = { production_run_id: runId };
        if (estimateType) data.estimate_type = estimateType;
        if (basisVolumeMl) data.basis_volume_ml = basisVolumeMl;
        return await api.post(`${this.baseUrl}/packaging-estimate.php`, data);
    },

    async updateActualUnits(id, actualUnits) {
        return await api.put(`${this.baseUrl}/packaging-estimate.php`, { id, actual_units: actualUnits });
    },

    // ========================================
    // Run Volume (extends runs)
    // ========================================

    async startRunWithVolume(id, initialVolumeMl) {
        return await api.put(`${this.baseUrl}/runs.php`, { id, action: 'start', initial_volume_ml: initialVolumeMl });
    },

    async setRunVolume(id, initialVolumeMl) {
        return await api.put(`${this.baseUrl}/runs.php`, { id, action: 'set_volume', initial_volume_ml: initialVolumeMl });
    },

    /**
     * Load run + yield + packaging estimates for the Active Run Workbench.
     * Failures on optional endpoints are soft so the hub still opens.
     */
    async getRunWorkbenchData(runId) {
        const runRes = await this.getRun(runId);
        if (!runRes.success) {
            return runRes;
        }
        const run = runRes.data;
        let yieldData = null;
        let estimates = null;
        let reconciliation = null;

        try {
            if (run.initial_volume_ml) {
                const y = await this.getYield(runId);
                if (y.success) yieldData = y.data;
            }
        } catch (e) { /* optional */ }

        try {
            const e = await this.getEstimates(runId);
            if (e.success) estimates = e.data;
        } catch (e) { /* optional */ }

        try {
            if (run.initial_volume_ml) {
                const r = await this.getReconciliation(runId);
                if (r.success) reconciliation = r.data;
            }
        } catch (e) { /* optional */ }

        return {
            success: true,
            data: {
                run,
                yieldData,
                estimates,
                reconciliation,
                nextStep: (typeof ProductionRunFlow !== 'undefined')
                    ? ProductionRunFlow.getNextStep(run, { yieldData, estimates })
                    : null,
            },
            message: 'Workbench data loaded',
        };
    },
};
