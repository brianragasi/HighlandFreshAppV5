/**
 * Highland Fresh - Warehouse Raw Module Services
 * 
 * API service layer for raw materials warehouse management
 * Uses the global 'api' object from api.js which handles auth tokens
 * 
 * @version 4.0
 */

const WarehouseRawService = {
    baseUrl: '/warehouse/raw',

    // ========================================
    // Dashboard
    // ========================================
    
    /**
     * Get warehouse raw dashboard statistics
     */
    async getDashboardStats() {
        return await api.get(`${this.baseUrl}/dashboard.php`);
    },

    // ========================================
    // Storage Tanks
    // ========================================
    
    /**
     * Get all storage tanks
     * @param {Object} params - Filter parameters
     */
    async getTanks(params = {}) {
        return await api.get(`${this.baseUrl}/tanks.php`, { params: { action: 'list', ...params } });
    },

    /**
     * Get single tank with batches
     * @param {number} id - Tank ID
     */
    async getTank(id) {
        return await api.get(`${this.baseUrl}/tanks.php`, { params: { action: 'detail', id } });
    },

    /**
     * Get available milk for production (FIFO order)
     */
    async getAvailableMilk() {
        return await api.get(`${this.baseUrl}/tanks.php`, { params: { action: 'available_milk' } });
    },

    /**
     * Get QC-approved milk pending storage
     */
    async getPendingMilkStorage() {
        return await api.get(`${this.baseUrl}/tanks.php`, { params: { action: 'pending_storage' } });
    },

    /**
     * Receive milk into tank from QC
     * @param {number} tankId - Target tank ID
     * @param {number} rawMilkInventoryId - Raw milk inventory ID from QC
     * @param {string} notes - Optional notes
     */
    async receiveMilkIntoTank(tankId, rawMilkInventoryId, notes = '') {
        return await api.post(`${this.baseUrl}/tanks.php`, {
            action: 'receive',
            tank_id: tankId,
            raw_milk_inventory_id: rawMilkInventoryId,
            notes
        });
    },

    /**
     * Issue milk from tanks for production
     * @param {number} liters - Liters needed
     * @param {number} requisitionId - Optional requisition ID
     * @param {number} tankId - Optional specific tank ID
     */
    async issueMilk(liters, requisitionId = null, tankId = null) {
        return await api.put(`${this.baseUrl}/tanks.php`, {
            action: 'issue_milk',
            liters,
            requisition_id: requisitionId,
            tank_id: tankId
        });
    },

    /**
     * Transfer milk between tanks
     * @param {number} fromTankId - Source tank
     * @param {number} toTankId - Destination tank
     * @param {number} liters - Liters to transfer
     */
    async transferMilk(fromTankId, toTankId, liters) {
        return await api.put(`${this.baseUrl}/tanks.php`, {
            action: 'transfer',
            from_tank_id: fromTankId,
            to_tank_id: toTankId,
            liters
        });
    },

    /**
     * Update tank status
     * @param {number} tankId - Tank ID
     * @param {string} status - New status
     * @param {string} notes - Optional notes
     */
    async updateTankStatus(tankId, status, notes = '') {
        return await api.put(`${this.baseUrl}/tanks.php`, {
            action: 'update_status',
            id: tankId,
            status,
            notes
        });
    },

    /**
     * Update tank temperature
     * @param {number} tankId - Tank ID
     * @param {number} temperature - Temperature in Celsius
     */
    async updateTankTemperature(tankId, temperature) {
        return await api.put(`${this.baseUrl}/tanks.php`, {
            action: 'update_temperature',
            id: tankId,
            temperature
        });
    },

    /**
     * Create new storage tank (GM only)
     * @param {Object} tankData - Tank data
     */
    async createTank(tankData) {
        return await api.post(`${this.baseUrl}/tanks.php`, {
            action: 'create_tank',
            ...tankData
        });
    },

    // ========================================
    // Ingredients
    // ========================================
    
    /**
     * Get all ingredients
     * @param {Object} params - Filter parameters
     */
    async getIngredients(params = {}) {
        return await api.get(`${this.baseUrl}/ingredients.php`, { params: { action: 'list', ...params } });
    },

    /**
     * Get single ingredient with batches
     * @param {number} id - Ingredient ID
     */
    async getIngredient(id) {
        return await api.get(`${this.baseUrl}/ingredients.php`, { params: { action: 'detail', id } });
    },

    /**
     * Get ingredient categories
     */
    async getIngredientCategories() {
        return await api.get(`${this.baseUrl}/ingredients.php`, { params: { action: 'categories' } });
    },

    /**
     * Get expiring ingredients
     * @param {number} days - Days until expiry (default 7)
     */
    async getExpiringIngredients(days = 7) {
        return await api.get(`${this.baseUrl}/ingredients.php`, { params: { action: 'expiring', days } });
    },

    /**
     * Check stock availability for multiple ingredients
     * @param {Array} items - Array of {ingredient_id, quantity}
     */
    async checkIngredientStock(items) {
        return await api.get(`${this.baseUrl}/ingredients.php`, { params: { action: 'check_stock', items } });
    },

    /**
     * Receive new ingredient batch
     * @param {Object} batchData - Batch data
     */
    async receiveIngredient(batchData) {
        return await api.post(`${this.baseUrl}/ingredients.php`, {
            action: 'receive',
            ...batchData
        });
    },

    /**
     * Issue ingredients
     * @param {number} ingredientId - Ingredient ID
     * @param {number} quantity - Quantity to issue
     * @param {number} requisitionId - Optional requisition ID
     * @param {string} reason - Reason for issuing
     */
    async issueIngredient(ingredientId, quantity, requisitionId = null, reason = 'Issued for production') {
        return await api.put(`${this.baseUrl}/ingredients.php`, {
            action: 'issue',
            ingredient_id: ingredientId,
            quantity,
            requisition_id: requisitionId,
            reason
        });
    },

    /**
     * Adjust ingredient stock (physical count)
     * @param {number} ingredientId - Ingredient ID
     * @param {number} newQuantity - New stock quantity
     * @param {string} reason - Reason for adjustment
     */
    async adjustIngredientStock(ingredientId, newQuantity, reason) {
        return await api.put(`${this.baseUrl}/ingredients.php`, {
            action: 'adjust',
            ingredient_id: ingredientId,
            new_quantity: newQuantity,
            reason
        });
    },

    /**
     * Dispose ingredient batch
     * @param {number} batchId - Batch ID
     * @param {string} reason - Reason for disposal
     */
    async disposeIngredientBatch(batchId, reason) {
        return await api.put(`${this.baseUrl}/ingredients.php`, {
            action: 'dispose',
            batch_id: batchId,
            reason
        });
    },

    /**
     * Create new ingredient (GM/Purchaser)
     * @param {Object} ingredientData - Ingredient data
     */
    async createIngredient(ingredientData) {
        return await api.post(`${this.baseUrl}/ingredients.php`, {
            action: 'create',
            ...ingredientData
        });
    },

    /**
     * Update ingredient
     * @param {number} id - Ingredient ID
     * @param {Object} updates - Fields to update
     */
    async updateIngredient(id, updates) {
        return await api.put(`${this.baseUrl}/ingredients.php`, {
            action: 'update',
            id,
            ...updates
        });
    },

    // ========================================
    // MRO Items
    // ========================================
    
    /**
     * Get all MRO items
     * @param {Object} params - Filter parameters
     */
    async getMROItems(params = {}) {
        return await api.get(`${this.baseUrl}/mro.php`, { params: { action: 'list', ...params } });
    },

    /**
     * Get single MRO item with inventory
     * @param {number} id - Item ID
     */
    async getMROItem(id) {
        return await api.get(`${this.baseUrl}/mro.php`, { params: { action: 'detail', id } });
    },

    /**
     * Get MRO categories
     */
    async getMROCategories() {
        return await api.get(`${this.baseUrl}/mro.php`, { params: { action: 'categories' } });
    },

    /**
     * Get critical MRO items low on stock
     */
    async getCriticalMROStock() {
        return await api.get(`${this.baseUrl}/mro.php`, { params: { action: 'critical_stock' } });
    },

    /**
     * Receive new MRO inventory
     * @param {Object} inventoryData - Inventory data
     */
    async receiveMROItem(inventoryData) {
        return await api.post(`${this.baseUrl}/mro.php`, {
            action: 'receive',
            ...inventoryData
        });
    },

    /**
     * Issue MRO items
     * @param {number} mroItemId - MRO Item ID
     * @param {number} quantity - Quantity to issue
     * @param {number} requisitionId - Optional requisition ID
     * @param {string} reason - Reason for issuing
     */
    async issueMROItem(mroItemId, quantity, requisitionId = null, reason = 'Issued for maintenance') {
        return await api.put(`${this.baseUrl}/mro.php`, {
            action: 'issue',
            mro_item_id: mroItemId,
            quantity,
            requisition_id: requisitionId,
            reason
        });
    },

    /**
     * Adjust MRO stock (physical count)
     * @param {number} mroItemId - MRO Item ID
     * @param {number} newQuantity - New stock quantity
     * @param {string} reason - Reason for adjustment
     */
    async adjustMROStock(mroItemId, newQuantity, reason) {
        return await api.put(`${this.baseUrl}/mro.php`, {
            action: 'adjust',
            mro_item_id: mroItemId,
            new_quantity: newQuantity,
            reason
        });
    },

    /**
     * Create new MRO item (GM/Purchaser)
     * @param {Object} itemData - Item data
     */
    async createMROItem(itemData) {
        return await api.post(`${this.baseUrl}/mro.php`, {
            action: 'create',
            ...itemData
        });
    },

    /**
     * Update MRO item
     * @param {number} id - Item ID
     * @param {Object} updates - Fields to update
     */
    async updateMROItem(id, updates) {
        return await api.put(`${this.baseUrl}/mro.php`, {
            action: 'update',
            id,
            ...updates
        });
    },

    // ========================================
    // Requisitions
    // ========================================
    
    /**
     * Get requisitions for warehouse to fulfill
     * @param {Object} params - Filter parameters
     */
    async getRequisitions(params = {}) {
        return await api.get(`${this.baseUrl}/requisitions.php`, { params: { action: 'list', ...params } });
    },

    /**
     * Get single requisition with items
     * @param {number} id - Requisition ID
     */
    async getRequisition(id) {
        return await api.get(`${this.baseUrl}/requisitions.php`, { params: { action: 'detail', id } });
    },

    /**
     * Get pending requisition count
     */
    async getPendingRequisitionCount() {
        return await api.get(`${this.baseUrl}/requisitions.php`, { params: { action: 'pending_count' } });
    },

    /**
     * Fulfill entire requisition
     * @param {number} requisitionId - Requisition ID
     * @param {Object} issuedQuantities - Optional map of item_id to issued_quantity
     */
    async fulfillRequisition(requisitionId, issuedQuantities = {}) {
        return await api.put(`${this.baseUrl}/requisitions.php`, {
            action: 'fulfill',
            id: requisitionId,
            issued_quantities: issuedQuantities
        });
    },

    /**
     * Fulfill single requisition item
     * @param {number} requisitionId - Requisition ID
     * @param {number} itemId - Item ID
     * @param {number} issuedQuantity - Quantity to issue
     */
    async fulfillRequisitionItem(requisitionId, itemId, issuedQuantity) {
        return await api.put(`${this.baseUrl}/requisitions.php`, {
            action: 'fulfill_item',
            id: requisitionId,
            item_id: itemId,
            issued_quantity: issuedQuantity
        });
    },

    /**
     * Cancel/reject requisition item
     * @param {number} requisitionId - Requisition ID
     * @param {number} itemId - Item ID
     * @param {string} reason - Reason for cancellation
     */
    async cancelRequisitionItem(requisitionId, itemId, reason) {
        return await api.put(`${this.baseUrl}/requisitions.php`, {
            action: 'reject_item',
            id: requisitionId,
            item_id: itemId,
            reason
        });
    }
};
