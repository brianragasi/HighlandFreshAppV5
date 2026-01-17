/**
 * Highland Fresh System - QC Batch Release Service
 * 
 * @package HighlandFresh
 * @version 4.0
 */

const BatchReleaseService = {
    /**
     * Get all batches with pagination
     */
    async getAll(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await api.get(`/qc/batch_release.php?${queryString}`);
    },
    
    /**
     * Get batch details by ID
     */
    async getById(batchId) {
        return await api.get(`/qc/batch_release.php?batch_id=${batchId}`);
    },
    
    /**
     * Get batch details by ID (alias)
     */
    async getByBatchId(batchId) {
        return await this.getById(batchId);
    },
    
    /**
     * Get stats
     */
    async getStats() {
        return await api.get('/qc/batch_release.php?action=stats');
    },
    
    /**
     * Complete QC verification (release or reject)
     */
    async verify(batchId, data) {
        return await api.put('/qc/batch_release.php', {
            batch_id: batchId,
            ...data
        });
    },
    
    /**
     * Get pending batches
     */
    async getPending() {
        return await this.getAll({ status: 'pending' });
    },
    
    /**
     * Get released batches
     */
    async getReleased(params = {}) {
        return await this.getAll({ status: 'released', ...params });
    }
};
