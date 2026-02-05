/**
 * Highland Fresh - POS/Cashier Module Services
 * 
 * API service layer for Point of Sale and Cashier operations
 * Uses the global 'api' object from api.js which handles auth tokens
 * 
 * Supports:
 * - Cash sales transactions
 * - Product lookup and barcode scanning
 * - Collection management (AR payments)
 * - Transaction history
 * - Dashboard statistics
 * 
 * @version 4.0
 */

const POSService = {
    baseUrl: '/pos',

    // ========================================
    // Dashboard
    // ========================================

    /**
     * Get POS dashboard summary statistics
     */
    async getDashboardSummary() {
        try {
            return await api.get(`${this.baseUrl}/dashboard.php`, { 
                params: { action: 'summary' } 
            });
        } catch (error) {
            console.error('Error fetching dashboard summary:', error);
            throw error;
        }
    },

    /**
     * Get recent transactions for dashboard
     * @param {number} limit - Number of transactions to return
     */
    async getRecentTransactions(limit = 10) {
        try {
            return await api.get(`${this.baseUrl}/dashboard.php`, { 
                params: { action: 'recent_transactions', limit } 
            });
        } catch (error) {
            console.error('Error fetching recent transactions:', error);
            throw error;
        }
    },

    /**
     * Get pending collections for dashboard
     */
    async getPendingCollections() {
        try {
            return await api.get(`${this.baseUrl}/dashboard.php`, { 
                params: { action: 'pending_collections' } 
            });
        } catch (error) {
            console.error('Error fetching pending collections:', error);
            throw error;
        }
    },

    /**
     * Get current cash position
     */
    async getCashPosition() {
        try {
            return await api.get(`${this.baseUrl}/dashboard.php`, { 
                params: { action: 'cash_position' } 
            });
        } catch (error) {
            console.error('Error fetching cash position:', error);
            throw error;
        }
    },

    /**
     * Get today's sales summary
     */
    async getTodaySummary() {
        try {
            return await api.get(`${this.baseUrl}/dashboard.php`, { 
                params: { action: 'summary' } 
            });
        } catch (error) {
            console.error('Error fetching today summary:', error);
            throw error;
        }
    },

    // ========================================
    // Cash Sales
    // ========================================

    /**
     * Create new sale transaction
     * @param {Object} data - Sale data (items, customer_name, payment_method, amount_paid, notes)
     */
    async createSale(data) {
        try {
            return await api.post(`${this.baseUrl}/transactions.php`, {
                action: 'create_cash_sale',
                ...data
            });
        } catch (error) {
            console.error('Error creating sale:', error);
            throw error;
        }
    },

    /**
     * Create new cash sale transaction (alias for createSale)
     * @param {Object} data - Sale data (items, customer_name, payment_method, amount_tendered, notes)
     */
    async createCashSale(data) {
        return this.createSale(data);
    },

    /**
     * Get transactions list
     * @param {Object} params - Filter parameters (type, from_date, to_date, payment_method, page, limit)
     */
    async getTransactions(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/transactions.php`, { 
                params: { action: 'list', ...params } 
            });
        } catch (error) {
            console.error('Error fetching transactions:', error);
            throw error;
        }
    },

    /**
     * Get single transaction details
     * @param {number} id - Transaction ID
     */
    async getTransaction(id) {
        try {
            return await api.get(`${this.baseUrl}/transactions.php`, { 
                params: { action: 'detail', id } 
            });
        } catch (error) {
            console.error('Error fetching transaction:', error);
            throw error;
        }
    },

    /**
     * Get today's transactions
     */
    async getTodayTransactions() {
        try {
            return await api.get(`${this.baseUrl}/transactions.php`, { 
                params: { action: 'today' } 
            });
        } catch (error) {
            console.error('Error fetching today transactions:', error);
            throw error;
        }
    },

    /**
     * Void a transaction
     * @param {number} id - Transaction ID
     * @param {string} reason - Void reason
     */
    async voidTransaction(id, reason) {
        try {
            return await api.post(`${this.baseUrl}/transactions.php?action=void`, {
                id,
                reason
            });
        } catch (error) {
            console.error('Error voiding transaction:', error);
            throw error;
        }
    },

    /**
     * Get printable receipt data
     * @param {number} id - Transaction ID
     */
    async printReceipt(id) {
        try {
            return await api.get(`${this.baseUrl}/transactions.php`, { 
                params: { action: 'receipt', id } 
            });
        } catch (error) {
            console.error('Error fetching printable receipt:', error);
            throw error;
        }
    },

    // ========================================
    // Product Lookup
    // ========================================

    /**
     * Get available products for POS
     * @param {Object} params - Filter parameters (category, search, in_stock)
     */
    async getProducts(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/products.php`, { 
                params: { action: 'list', ...params } 
            });
        } catch (error) {
            console.error('Error fetching products:', error);
            throw error;
        }
    },

    /**
     * Search products by name, code, or description
     * @param {string} query - Search query
     */
    async searchProducts(query) {
        try {
            return await api.get(`${this.baseUrl}/products.php`, { 
                params: { action: 'search', q: query } 
            });
        } catch (error) {
            console.error('Error searching products:', error);
            throw error;
        }
    },

    /**
     * Get product by barcode
     * @param {string} barcode - Product barcode
     */
    async getProductByBarcode(barcode) {
        try {
            return await api.get(`${this.baseUrl}/products.php`, { 
                params: { action: 'by_barcode', barcode } 
            });
        } catch (error) {
            console.error('Error fetching product by barcode:', error);
            throw error;
        }
    },

    /**
     * Get product categories
     */
    async getProductCategories() {
        try {
            return await api.get(`${this.baseUrl}/products.php`, { 
                params: { action: 'categories' } 
            });
        } catch (error) {
            console.error('Error fetching product categories:', error);
            throw error;
        }
    },

    /**
     * Get product price and availability
     * @param {number} productId - Product ID
     */
    async getProductPricing(productId) {
        try {
            return await api.get(`${this.baseUrl}/products.php`, { 
                params: { action: 'detail', id: productId } 
            });
        } catch (error) {
            console.error('Error fetching product pricing:', error);
            throw error;
        }
    },

    // ========================================
    // Collections (AR Payments)
    // ========================================

    /**
     * Search for delivery receipt by DR number
     * @param {string} drNumber - Delivery Receipt number
     */
    async searchByDR(drNumber) {
        try {
            return await api.get(`${this.baseUrl}/collections.php`, { 
                params: { action: 'search_by_dr', dr_number: drNumber } 
            });
        } catch (error) {
            console.error('Error searching by DR:', error);
            throw error;
        }
    },

    /**
     * Get outstanding receivables
     * @param {Object} params - Filter parameters (customer_id, overdue_only)
     */
    async getOutstandingReceivables(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/collections.php`, { 
                params: { action: 'outstanding', ...params } 
            });
        } catch (error) {
            console.error('Error fetching outstanding receivables:', error);
            throw error;
        }
    },

    /**
     * Get customer balance
     * @param {number} customerId - Customer ID
     */
    async getCustomerBalance(customerId) {
        try {
            return await api.get(`${this.baseUrl}/collections.php`, { 
                params: { action: 'customer_balance', customer_id: customerId } 
            });
        } catch (error) {
            console.error('Error fetching customer balance:', error);
            throw error;
        }
    },

    /**
     * Record a collection payment
     * @param {Object} data - Collection data (dr_id, amount_collected, payment_method, notes, etc.)
     */
    async recordCollection(data) {
        try {
            return await api.post(`${this.baseUrl}/collections.php`, {
                action: 'record_collection',
                ...data
            });
        } catch (error) {
            console.error('Error recording collection:', error);
            throw error;
        }
    },

    /**
     * Get collection history
     * @param {Object} params - Filter parameters (customer_id, from_date, to_date, payment_method, page, limit)
     */
    async getCollectionHistory(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/collections.php`, { 
                params: { action: 'collection_history', ...params } 
            });
        } catch (error) {
            console.error('Error fetching collection history:', error);
            throw error;
        }
    },

    /**
     * Get collection receipt data
     * @param {number} id - Collection ID
     */
    async getCollectionReceipt(id) {
        try {
            return await api.get(`${this.baseUrl}/collections.php`, { 
                params: { action: 'or_detail', id } 
            });
        } catch (error) {
            console.error('Error fetching collection receipt:', error);
            throw error;
        }
    },

    /**
     * Void/Cancel a collection
     * @param {number} id - Collection ID
     * @param {string} reason - Void reason
     */
    async voidCollection(id, reason) {
        try {
            return await api.post(`${this.baseUrl}/collections.php`, {
                action: 'cancel',
                id,
                reason
            });
        } catch (error) {
            console.error('Error voiding collection:', error);
            throw error;
        }
    },

    /**
     * Search customers for collection (uses customer_balance with name)
     * @param {string} query - Search query
     */
    async searchCustomers(query) {
        try {
            return await api.get(`${this.baseUrl}/collections.php`, { 
                params: { action: 'customer_balance', customer_name: query } 
            });
        } catch (error) {
            console.error('Error searching customers:', error);
            throw error;
        }
    },

    // ========================================
    // Cash Management
    // ========================================

    /**
     * Start a new shift/session
     * @param {Object} data - Shift data (opening_cash, notes)
     */
    async startShift(data) {
        try {
            return await api.post(`${this.baseUrl}/shifts.php`, {
                action: 'start',
                ...data
            });
        } catch (error) {
            console.error('Error starting shift:', error);
            throw error;
        }
    },

    /**
     * End current shift
     * @param {Object} data - Shift data (closing_cash, notes)
     */
    async endShift(data) {
        try {
            return await api.post(`${this.baseUrl}/shifts.php`, {
                action: 'end',
                ...data
            });
        } catch (error) {
            console.error('Error ending shift:', error);
            throw error;
        }
    },

    /**
     * Get current shift details
     */
    async getCurrentShift() {
        try {
            return await api.get(`${this.baseUrl}/shifts.php`, { 
                params: { action: 'current' } 
            });
        } catch (error) {
            console.error('Error fetching current shift:', error);
            throw error;
        }
    },

    /**
     * Get shift history
     * @param {Object} params - Filter parameters (from_date, to_date, cashier_id)
     */
    async getShiftHistory(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/shifts.php`, { 
                params: { action: 'history', ...params } 
            });
        } catch (error) {
            console.error('Error fetching shift history:', error);
            throw error;
        }
    },

    /**
     * Record cash in/out adjustment
     * @param {Object} data - Adjustment data (type, amount, reason)
     */
    async recordCashAdjustment(data) {
        try {
            return await api.post(`${this.baseUrl}/shifts.php`, {
                action: 'cash_adjustment',
                ...data
            });
        } catch (error) {
            console.error('Error recording cash adjustment:', error);
            throw error;
        }
    },

    // ========================================
    // Reports
    // ========================================

    /**
     * Get daily sales report
     * @param {string} date - Date in YYYY-MM-DD format (defaults to today)
     */
    async getDailySalesReport(date = null) {
        try {
            const params = { action: 'daily_sales' };
            if (date) params.date = date;
            return await api.get(`${this.baseUrl}/reports.php`, { params });
        } catch (error) {
            console.error('Error fetching daily sales report:', error);
            throw error;
        }
    },

    /**
     * Get collections summary report
     * @param {Object} params - Report parameters (from_date, to_date)
     */
    async getCollectionsSummaryReport(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/reports.php`, { 
                params: { action: 'collections_summary', ...params } 
            });
        } catch (error) {
            console.error('Error fetching collections summary report:', error);
            throw error;
        }
    },

    /**
     * Get cashier performance report
     * @param {Object} params - Report parameters (cashier_id, from_date, to_date)
     */
    async getCashierReport(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/reports.php`, { 
                params: { action: 'cashier_performance', ...params } 
            });
        } catch (error) {
            console.error('Error fetching cashier report:', error);
            throw error;
        }
    },

    /**
     * Get X-Reading (mid-day report)
     */
    async getXReading() {
        try {
            return await api.get(`${this.baseUrl}/reports.php`, { 
                params: { action: 'x_reading' } 
            });
        } catch (error) {
            console.error('Error fetching X-Reading:', error);
            throw error;
        }
    },

    /**
     * Get Z-Reading (end-of-day report)
     * @param {string} date - Date in YYYY-MM-DD format (defaults to today)
     */
    async getZReading(date = null) {
        try {
            const params = { action: 'z_reading' };
            if (date) params.date = date;
            return await api.get(`${this.baseUrl}/reports.php`, { params });
        } catch (error) {
            console.error('Error fetching Z-Reading:', error);
            throw error;
        }
    },

    /**
     * Get cash position report
     * @param {string} date - Date in YYYY-MM-DD format (defaults to today)
     */
    async getCashPositionReport(date = null) {
        try {
            const params = { action: 'cash_position' };
            if (date) params.date = date;
            return await api.get(`${this.baseUrl}/reports.php`, { params });
        } catch (error) {
            console.error('Error fetching cash position report:', error);
            throw error;
        }
    }
};

// Make service available globally
window.POSService = POSService;
