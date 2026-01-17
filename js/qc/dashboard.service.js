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
    }
};
