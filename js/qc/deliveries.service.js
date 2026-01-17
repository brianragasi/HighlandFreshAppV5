/**
 * Highland Fresh System - QC Deliveries Service
 * 
 * @package HighlandFresh
 * @version 4.0
 */

const DeliveriesService = {
    /**
     * Get all deliveries with pagination
     */
    async getAll(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await api.get(`/qc/deliveries.php?${queryString}`);
    },
    
    /**
     * Create new delivery
     */
    async create(data) {
        return await api.post('/qc/deliveries.php', data);
    },
    
    /**
     * Get deliveries for a specific farmer
     */
    async getByFarmer(farmerId, params = {}) {
        return await this.getAll({ farmer_id: farmerId, ...params });
    },
    
    /**
     * Get today's deliveries
     */
    async getToday() {
        const today = new Date().toISOString().split('T')[0];
        return await this.getAll({ date_from: today, date_to: today });
    },
    
    /**
     * Get pending deliveries (not yet tested)
     */
    async getPending() {
        return await this.getAll({ status: 'pending_test' });
    }
};
