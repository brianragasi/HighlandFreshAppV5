/**
 * Highland Fresh System - QC Dashboard Service
 * 
 * @package HighlandFresh
 * @version 4.0
 */

const QCDashboardService = {
    /**
     * Get dashboard statistics
     */
    async getStats() {
        return await api.get('/qc/dashboard.php');
    },
    
    /**
     * Refresh dashboard data
     */
    async refresh() {
        return await this.getStats();
    },

    async approveFoundStock(requestId, notes = '') {
        return await api.post('/qc/dashboard.php?action=approve_found_stock', {
            request_id: requestId,
            notes
        });
    },

    async rejectFoundStock(requestId, notes) {
        return await api.post('/qc/dashboard.php?action=reject_found_stock', {
            request_id: requestId,
            notes
        });
    }
};
