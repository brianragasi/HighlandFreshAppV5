/**
 * Highland Fresh System - QC Farmers Service
 * 
 * @package HighlandFresh
 * @version 4.0
 */

const FarmersService = {
    /**
     * Get all farmers with pagination
     */
    async getAll(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await api.get(`/qc/farmers.php?${queryString}`);
    },
    
    /**
     * Get single farmer by ID
     */
    async getById(id) {
        return await api.get(`/qc/farmer.php?id=${id}`);
    },
    
    /**
     * Create new farmer
     */
    async create(data) {
        return await api.post('/qc/farmers.php', data);
    },
    
    /**
     * Update farmer
     */
    async update(id, data) {
        return await api.put(`/qc/farmer.php?id=${id}`, data);
    },
    
    /**
     * Deactivate farmer
     */
    async deactivate(id) {
        return await api.delete(`/qc/farmer.php?id=${id}`);
    },
    
    /**
     * Search farmers
     */
    async search(query) {
        return await this.getAll({ search: query, limit: 50 });
    },
    
    /**
     * Get active farmers for dropdown
     */
    async getForDropdown() {
        return await this.getAll({ status: 'active', limit: 100 });
    }
};
