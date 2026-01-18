/**
 * Highland Fresh System - QC Expiry Management Service
 * 
 * @package HighlandFresh
 * @version 4.0
 */

const ExpiryService = {
    /**
     * Get expiring/expired products
     */
    async getAll(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await api.get(`/qc/expiry_management.php?${queryString}`);
    },
    
    /**
     * Get expiring products within specified days
     */
    async getExpiring(params = {}) {
        return await api.get(`/qc/expiry_management.php?action=expiring&days=${params.days || 7}`);
    },
    
    /**
     * Get raw milk inventory
     */
    async getRawMilkInventory() {
        return await api.get('/qc/expiry_management.php?action=raw_milk');
    },
    
    /**
     * Get raw milk by ID
     */
    async getRawMilkById(id) {
        return await api.get(`/qc/expiry_management.php?action=raw_milk&id=${id}`);
    },
    
    /**
     * Get finished goods by ID
     */
    async getFinishedGoodsById(id) {
        return await api.get(`/qc/expiry_management.php?action=finished_goods&id=${id}`);
    },
    
    /**
     * Get yogurt transformation history
     */
    async getTransformations() {
        return await api.get('/qc/expiry_management.php?action=transformations');
    },
    
    /**
     * Get yogurt products (recipes)
     */
    async getYogurtProducts() {
        return await api.get('/qc/expiry_management.php?action=yogurt_products');
    },
    
    /**
     * Get warning items (4-7 days to expiry)
     */
    async getWarning() {
        return await this.getAll({ filter: 'warning' });
    },
    
    /**
     * Get critical items (0-3 days to expiry)
     */
    async getCritical() {
        return await this.getAll({ filter: 'critical' });
    },
    
    /**
     * Get expired items
     */
    async getExpired() {
        return await this.getAll({ filter: 'expired' });
    },
    
    /**
     * Transform near-expiry product to yogurt
     */
    async transform(data) {
        return await api.post('/qc/expiry_management.php', {
            action: 'transform',
            ...data
        });
    },
    
    /**
     * Dispose expired/spoiled product
     */
    async dispose(data) {
        return await api.post('/qc/expiry_management.php', {
            action: 'dispose',
            ...data
        });
    },
    
    /**
     * Initiate yogurt transformation (legacy method)
     */
    async initiateTransformation(inventoryId, quantity, notes = '') {
        return await api.post('/qc/expiry_management.php', {
            inventory_id: inventoryId,
            quantity: quantity,
            notes: notes
        });
    }
};
