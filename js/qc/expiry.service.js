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
     * Initiate yogurt transformation
     */
    async initiateTransformation(inventoryId, quantity, notes = '') {
        return await api.post('/qc/expiry_management.php', {
            inventory_id: inventoryId,
            quantity: quantity,
            notes: notes
        });
    }
};
