/**
 * Highland Fresh - Sales Custodian Module Services
 * 
 * API service layer for sales custodian management
 * Uses the global 'api' object from api.js which handles auth tokens
 * 
 * Supports:
 * - Customer management and credit tracking
 * - Sales orders processing
 * - Invoice (CSI) generation and management
 * - Payment recording and aging reports
 * - Dashboard statistics
 * 
 * @version 4.0
 */

const SalesService = {
    baseUrl: '/sales',

    // ========================================
    // Dashboard
    // ========================================

    /**
     * Get sales dashboard summary statistics
     * @param {string} period - Period to get stats for (day, week, month, year)
     */
    async getDashboardStats(period = 'month') {
        try {
            const response = await api.get(`${this.baseUrl}/dashboard.php`, { 
                params: { action: 'summary', period } 
            });
            // Map response to expected format for dashboard
            if (response.data) {
                const data = response.data;
                return {
                    data: {
                        today_credit_sales: data.sales?.total_sales || 0,
                        today_order_count: data.sales?.invoice_count || 0,
                        total_receivables: data.receivables?.total_receivables || 0,
                        receivables_count: data.customers?.active || 0,
                        overdue_amount: data.receivables?.overdue_amount || 0,
                        overdue_count: data.receivables?.overdue_count || 0,
                        pending_orders: data.orders?.pending?.count || 0,
                        pipeline: {
                            draft: data.orders?.draft?.count || 0,
                            pending: data.orders?.pending?.count || 0,
                            approved: data.orders?.approved?.count || 0,
                            preparing: data.orders?.preparing?.count || 0,
                            dispatched: data.orders?.dispatched?.count || 0,
                            delivered: data.orders?.delivered?.count || 0
                        }
                    }
                };
            }
            return response;
        } catch (error) {
            console.error('Error fetching dashboard stats:', error);
            throw error;
        }
    },

    /**
     * Get sales dashboard summary statistics (raw)
     */
    async getDashboardSummary(period = 'month') {
        try {
            return await api.get(`${this.baseUrl}/dashboard.php`, { 
                params: { action: 'summary', period } 
            });
        } catch (error) {
            console.error('Error fetching dashboard summary:', error);
            throw error;
        }
    },

    /**
     * Get aging summary for receivables
     * Used by dashboard and aging report page
     */
    async getAgingSummary() {
        try {
            // Get both summary and customer details
            const [summaryRes, customersRes] = await Promise.all([
                api.get(`${this.baseUrl}/dashboard.php`, { params: { action: 'aging_summary' } }),
                api.get(`${this.baseUrl}/customers.php`, { params: { action: 'aging', min_balance: 0 } })
            ]);
            
            const buckets = summaryRes.data?.buckets || {};
            const customers = customersRes.data || [];
            
            // Transform customer data to expected format
            const transformedCustomers = customers.map(c => ({
                id: c.id,
                customer_code: c.customer_code,
                customer_name: c.customer_name,
                customer_type: c.customer_type,
                credit_limit: c.credit_limit || 0,
                current: (c.balance_0_30 || 0),
                days_31_60: c.balance_31_60 || 0,
                days_61_90: c.balance_61_90 || 0,
                over_90: c.balance_91_plus || 0,
                total: c.total_outstanding || 0
            }));
            
            return {
                data: {
                    // For dashboard
                    bucket_0_30: (buckets.current?.amount || 0) + (buckets.days_1_30?.amount || 0),
                    bucket_0_30_count: (buckets.current?.count || 0) + (buckets.days_1_30?.count || 0),
                    bucket_31_60: buckets.days_31_60?.amount || 0,
                    bucket_31_60_count: buckets.days_31_60?.count || 0,
                    bucket_61_90: buckets.days_61_90?.amount || 0,
                    bucket_61_90_count: buckets.days_61_90?.count || 0,
                    bucket_91_plus: buckets.days_91_plus?.amount || 0,
                    bucket_91_plus_count: buckets.days_91_plus?.count || 0,
                    total_outstanding: summaryRes.data?.total_outstanding || 0,
                    by_customer_type: summaryRes.data?.by_customer_type || [],
                    // For aging report page
                    customers: transformedCustomers,
                    summary: {
                        total: summaryRes.data?.total_outstanding || 0,
                        current: (buckets.current?.amount || 0) + (buckets.days_1_30?.amount || 0),
                        days_31_60: buckets.days_31_60?.amount || 0,
                        days_61_90: buckets.days_61_90?.amount || 0,
                        over_90: buckets.days_91_plus?.amount || 0
                    }
                }
            };
        } catch (error) {
            console.error('Error fetching aging summary:', error);
            return { data: { customers: [], summary: {} } };
        }
    },

    /**
     * Get detailed aging report
     */
    async getAgingReport(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/dashboard.php`, { 
                params: { action: 'aging_summary', ...params } 
            });
        } catch (error) {
            console.error('Error fetching aging report:', error);
            throw error;
        }
    },

    /**
     * Get top customers by revenue
     * @param {Object} params - Parameters (limit, period)
     */
    async getTopCustomers(params = {}) {
        try {
            const limit = params.limit || 10;
            const period = params.period || 'month';
            const response = await api.get(`${this.baseUrl}/dashboard.php`, { 
                params: { action: 'top_customers', limit, period } 
            });
            // API returns {data: {period, date_range, customers: [...]}} - extract customers array
            if (response.data && response.data.customers) {
                return { data: response.data.customers };
            }
            return { data: [] };
        } catch (error) {
            console.error('Error fetching top customers:', error);
            return { data: [] };
        }
    },

    /**
     * Get top customers by outstanding balance
     * @param {number} limit - Number of customers to return
     */
    async getTopCustomersByBalance(limit = 5) {
        try {
            const response = await api.get(`${this.baseUrl}/customers.php`, { 
                params: { action: 'aging', min_balance: 0 } 
            });
            // Return top N by outstanding balance
            if (response.data && Array.isArray(response.data)) {
                const customers = response.data.slice(0, limit).map(c => ({
                    id: c.id,
                    name: c.customer_name,
                    customer_type: c.customer_type,
                    outstanding_balance: c.total_outstanding || 0,
                    days_overdue: (c.balance_91_plus > 0) ? 91 : 
                                  (c.balance_61_90 > 0) ? 61 : 
                                  (c.balance_31_60 > 0) ? 31 : 0
                }));
                return { data: customers };
            }
            return { data: [] };
        } catch (error) {
            console.error('Error fetching top customers by balance:', error);
            throw error;
        }
    },

    /**
     * Get recent orders for dashboard
     * @param {number} limit - Number of orders to return
     */
    async getRecentOrders(limit = 10) {
        try {
            const response = await api.get(`${this.baseUrl}/dashboard.php`, { 
                params: { action: 'recent_orders', limit } 
            });
            // Map to expected format
            if (response.data && response.data.orders) {
                return { data: response.data.orders };
            }
            return response;
        } catch (error) {
            console.error('Error fetching recent orders:', error);
            throw error;
        }
    },

    /**
     * Get daily collection report
     * @param {string} date - Date in YYYY-MM-DD format
     */
    async getDailyCollection(date = null) {
        try {
            const params = { action: 'daily_collection' };
            if (date) params.date = date;
            return await api.get(`${this.baseUrl}/dashboard.php`, { params });
        } catch (error) {
            console.error('Error fetching daily collection:', error);
            throw error;
        }
    },

    /**
     * Get sales trend
     * @param {Object} params - Parameters (days, start_date, end_date)
     */
    async getSalesTrend(params = {}) {
        try {
            const days = params.days || 30;
            const response = await api.get(`${this.baseUrl}/dashboard.php`, { 
                params: { action: 'sales_trend', days } 
            });
            // API returns {data: {period_days, data: [...]}} - extract the data array
            if (response.data && response.data.data) {
                return { data: response.data.data };
            }
            return { data: [] };
        } catch (error) {
            console.error('Error fetching sales trend:', error);
            return { data: [] };
        }
    },

    // ========================================
    // Customer Management
    // ========================================

    /**
     * Get customers list
     * @param {Object} params - Filter parameters (status, credit_status, search, page, limit)
     */
    async getCustomers(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/customers.php`, { 
                params: { action: 'list', ...params } 
            });
        } catch (error) {
            console.error('Error fetching customers:', error);
            throw error;
        }
    },

    /**
     * Get single customer details
     * @param {number} id - Customer ID
     */
    async getCustomer(id) {
        try {
            return await api.get(`${this.baseUrl}/customers.php`, { 
                params: { action: 'detail', id } 
            });
        } catch (error) {
            console.error('Error fetching customer:', error);
            throw error;
        }
    },

    /**
     * Search customers by name, code, or contact
     * @param {string} query - Search query
     */
    async searchCustomers(query) {
        try {
            return await api.get(`${this.baseUrl}/customers.php`, { 
                params: { action: 'search', q: query } 
            });
        } catch (error) {
            console.error('Error searching customers:', error);
            throw error;
        }
    },

    /**
     * Create new customer
     * @param {Object} data - Customer data (name, contact_person, phone, email, address, credit_limit, payment_terms)
     */
    async createCustomer(data) {
        try {
            return await api.post(`${this.baseUrl}/customers.php`, {
                action: 'create',
                ...data
            });
        } catch (error) {
            console.error('Error creating customer:', error);
            throw error;
        }
    },

    /**
     * Update customer details
     * @param {number} id - Customer ID
     * @param {Object} data - Updated customer data
     */
    async updateCustomer(id, data) {
        try {
            return await api.put(`${this.baseUrl}/customers.php`, {
                action: 'update',
                id,
                ...data
            });
        } catch (error) {
            console.error('Error updating customer:', error);
            throw error;
        }
    },

    /**
     * Get customer aging report (receivables breakdown by age)
     * @param {number} customerId - Customer ID
     */
    async getCustomerAging(customerId) {
        try {
            return await api.get(`${this.baseUrl}/customers.php`, { 
                params: { action: 'aging', customer_id: customerId } 
            });
        } catch (error) {
            console.error('Error fetching customer aging:', error);
            throw error;
        }
    },

    /**
     * Get customer transaction history
     * @param {number} customerId - Customer ID
     * @param {Object} params - Filter parameters (from_date, to_date, type)
     */
    async getCustomerTransactions(customerId, params = {}) {
        try {
            return await api.get(`${this.baseUrl}/customers.php`, { 
                params: { action: 'transactions', customer_id: customerId, ...params } 
            });
        } catch (error) {
            console.error('Error fetching customer transactions:', error);
            throw error;
        }
    },

    /**
     * Get customer credit status
     * @param {number} customerId - Customer ID
     */
    async getCustomerCreditStatus(customerId) {
        try {
            return await api.get(`${this.baseUrl}/customers.php`, { 
                params: { action: 'credit_status', customer_id: customerId } 
            });
        } catch (error) {
            console.error('Error fetching customer credit status:', error);
            throw error;
        }
    },

    // ========================================
    // Sales Orders
    // ========================================

    /**
     * Get sales orders list
     * @param {Object} params - Filter parameters (status, customer_id, from_date, to_date, page, limit)
     */
    async getOrders(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/orders.php`, { 
                params: { action: 'list', ...params } 
            });
        } catch (error) {
            console.error('Error fetching orders:', error);
            throw error;
        }
    },

    /**
     * Get single order details with items
     * @param {number} id - Order ID
     */
    async getOrder(id) {
        try {
            return await api.get(`${this.baseUrl}/orders.php`, { 
                params: { action: 'detail', id } 
            });
        } catch (error) {
            console.error('Error fetching order:', error);
            throw error;
        }
    },

    /**
     * Get pending orders awaiting processing
     */
    async getPendingOrders() {
        try {
            return await api.get(`${this.baseUrl}/orders.php`, { 
                params: { action: 'pending' } 
            });
        } catch (error) {
            console.error('Error fetching pending orders:', error);
            throw error;
        }
    },

    /**
     * Create new sales order
     * @param {Object} data - Order data (customer_id, delivery_date, delivery_address, items, notes)
     */
    async createOrder(data) {
        try {
            return await api.post(`${this.baseUrl}/orders.php`, {
                action: 'create',
                ...data
            });
        } catch (error) {
            console.error('Error creating order:', error);
            throw error;
        }
    },

    /**
     * Add item to existing order
     * @param {number} orderId - Order ID
     * @param {Object} itemData - Item data (product_id, quantity, unit_price)
     */
    async addOrderItem(orderId, itemData) {
        try {
            return await api.post(`${this.baseUrl}/orders.php`, {
                action: 'add_item',
                order_id: orderId,
                ...itemData
            });
        } catch (error) {
            console.error('Error adding order item:', error);
            throw error;
        }
    },

    /**
     * Update order item
     * @param {number} orderId - Order ID
     * @param {number} itemId - Order item ID
     * @param {Object} itemData - Updated item data
     */
    async updateOrderItem(orderId, itemId, itemData) {
        try {
            return await api.put(`${this.baseUrl}/orders.php`, {
                action: 'update_item',
                order_id: orderId,
                item_id: itemId,
                ...itemData
            });
        } catch (error) {
            console.error('Error updating order item:', error);
            throw error;
        }
    },

    /**
     * Remove item from order
     * @param {number} orderId - Order ID
     * @param {number} itemId - Order item ID
     */
    async removeOrderItem(orderId, itemId) {
        try {
            return await api.put(`${this.baseUrl}/orders.php`, {
                action: 'remove_item',
                order_id: orderId,
                item_id: itemId
            });
        } catch (error) {
            console.error('Error removing order item:', error);
            throw error;
        }
    },

    /**
     * Update order status
     * @param {number} id - Order ID
     * @param {string} status - New status (draft, pending, approved, processing, fulfilled, cancelled)
     */
    async updateOrderStatus(id, status) {
        try {
            return await api.put(`${this.baseUrl}/orders.php`, {
                action: 'update_status',
                id,
                status
            });
        } catch (error) {
            console.error('Error updating order status:', error);
            throw error;
        }
    },

    /**
     * Approve sales order for fulfillment
     * @param {number} id - Order ID
     */
    async approveOrder(id) {
        try {
            return await api.put(`${this.baseUrl}/orders.php`, {
                action: 'approve',
                id
            });
        } catch (error) {
            console.error('Error approving order:', error);
            throw error;
        }
    },

    /**
     * Cancel sales order
     * @param {number} id - Order ID
     * @param {string} reason - Cancellation reason
     */
    async cancelOrder(id, reason) {
        try {
            return await api.put(`${this.baseUrl}/orders.php`, {
                action: 'cancel',
                id,
                reason
            });
        } catch (error) {
            console.error('Error cancelling order:', error);
            throw error;
        }
    },

    /**
     * Get printable order data
     * @param {number} id - Order ID
     */
    async printOrder(id) {
        try {
            return await api.get(`${this.baseUrl}/orders.php`, { 
                params: { action: 'print', id } 
            });
        } catch (error) {
            console.error('Error fetching printable order:', error);
            throw error;
        }
    },

    // ========================================
    // Invoices (CSI - Charge Sales Invoice)
    // ========================================

    /**
     * Get invoices list
     * @param {Object} params - Filter parameters (status, customer_id, from_date, to_date, page, limit)
     */
    async getInvoices(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/invoices.php`, { 
                params: { action: 'list', ...params } 
            });
        } catch (error) {
            console.error('Error fetching invoices:', error);
            throw error;
        }
    },

    /**
     * Get single invoice details
     * @param {number} id - Invoice ID
     */
    async getInvoice(id) {
        try {
            return await api.get(`${this.baseUrl}/invoices.php`, { 
                params: { action: 'detail', id } 
            });
        } catch (error) {
            console.error('Error fetching invoice:', error);
            throw error;
        }
    },

    /**
     * Get unpaid invoices
     * @param {Object} params - Filter parameters (customer_id, overdue_only)
     */
    async getUnpaidInvoices(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/invoices.php`, { 
                params: { action: 'unpaid', ...params } 
            });
        } catch (error) {
            console.error('Error fetching unpaid invoices:', error);
            throw error;
        }
    },

    /**
     * Get aging report for all receivables
     * @param {Object} params - Filter parameters (customer_id, as_of_date)
     */
    async getAgingReport(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/invoices.php`, { 
                params: { action: 'aging_report', ...params } 
            });
        } catch (error) {
            console.error('Error fetching aging report:', error);
            throw error;
        }
    },

    /**
     * Create Charge Sales Invoice from Delivery Receipt
     * @param {number} drId - Delivery Receipt ID
     * @param {Object} data - Invoice data (due_date, discount, notes)
     */
    async createCSI(drId, data = {}) {
        try {
            return await api.post(`${this.baseUrl}/invoices.php`, {
                action: 'create_csi',
                dr_id: drId,
                ...data
            });
        } catch (error) {
            console.error('Error creating CSI:', error);
            throw error;
        }
    },

    /**
     * Record payment against invoice
     * @param {number} invoiceId - Invoice ID
     * @param {Object} paymentData - Payment details (amount, payment_method, reference_number, payment_date, notes)
     */
    async recordPayment(invoiceId, paymentData) {
        try {
            return await api.post(`${this.baseUrl}/invoices.php`, {
                action: 'record_payment',
                invoice_id: invoiceId,
                ...paymentData
            });
        } catch (error) {
            console.error('Error recording payment:', error);
            throw error;
        }
    },

    /**
     * Get payment history for an invoice
     * @param {number} invoiceId - Invoice ID
     */
    async getInvoicePayments(invoiceId) {
        try {
            return await api.get(`${this.baseUrl}/invoices.php`, { 
                params: { action: 'payments', invoice_id: invoiceId } 
            });
        } catch (error) {
            console.error('Error fetching invoice payments:', error);
            throw error;
        }
    },

    /**
     * Void an invoice
     * @param {number} id - Invoice ID
     * @param {string} reason - Void reason
     */
    async voidInvoice(id, reason) {
        try {
            return await api.put(`${this.baseUrl}/invoices.php`, {
                action: 'void',
                id,
                reason
            });
        } catch (error) {
            console.error('Error voiding invoice:', error);
            throw error;
        }
    },

    /**
     * Get printable invoice data
     * @param {number} id - Invoice ID
     */
    async printInvoice(id) {
        try {
            return await api.get(`${this.baseUrl}/invoices.php`, { 
                params: { action: 'print', id } 
            });
        } catch (error) {
            console.error('Error fetching printable invoice:', error);
            throw error;
        }
    },

    // ========================================
    // Delivery Receipts (View Only for Sales)
    // ========================================

    /**
     * Get delivery receipts for invoicing
     * @param {Object} params - Filter parameters (status, customer_id, uninvoiced)
     */
    async getDeliveryReceipts(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/delivery_receipts.php`, { 
                params: { action: 'list', ...params } 
            });
        } catch (error) {
            console.error('Error fetching delivery receipts:', error);
            throw error;
        }
    },

    /**
     * Get uninvoiced delivery receipts
     */
    async getUninvoicedDRs() {
        try {
            return await api.get(`${this.baseUrl}/delivery_receipts.php`, { 
                params: { action: 'uninvoiced' } 
            });
        } catch (error) {
            console.error('Error fetching uninvoiced DRs:', error);
            throw error;
        }
    },

    /**
     * Get delivery receipt details
     * @param {number} id - DR ID
     */
    async getDeliveryReceipt(id) {
        try {
            return await api.get(`${this.baseUrl}/delivery_receipts.php`, { 
                params: { action: 'detail', id } 
            });
        } catch (error) {
            console.error('Error fetching delivery receipt:', error);
            throw error;
        }
    },

    // ========================================
    // Reports
    // ========================================

    /**
     * Get sales summary report
     * @param {Object} params - Report parameters (from_date, to_date, group_by)
     */
    async getSalesSummaryReport(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/reports.php`, { 
                params: { action: 'sales_summary', ...params } 
            });
        } catch (error) {
            console.error('Error fetching sales summary report:', error);
            throw error;
        }
    },

    /**
     * Get customer sales report
     * @param {Object} params - Report parameters (customer_id, from_date, to_date)
     */
    async getCustomerSalesReport(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/reports.php`, { 
                params: { action: 'customer_sales', ...params } 
            });
        } catch (error) {
            console.error('Error fetching customer sales report:', error);
            throw error;
        }
    },

    /**
     * Get product sales report
     * @param {Object} params - Report parameters (product_id, from_date, to_date)
     */
    async getProductSalesReport(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/reports.php`, { 
                params: { action: 'product_sales', ...params } 
            });
        } catch (error) {
            console.error('Error fetching product sales report:', error);
            throw error;
        }
    },

    /**
     * Get collections report
     * @param {Object} params - Report parameters (from_date, to_date, payment_method)
     */
    async getCollectionsReport(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/reports.php`, { 
                params: { action: 'collections', ...params } 
            });
        } catch (error) {
            console.error('Error fetching collections report:', error);
            throw error;
        }
    },

    // ========================================
    // Additional Methods
    // ========================================

    /**
     * Get daily collection target and due collections
     * @param {string} date - Optional date in YYYY-MM-DD format
     */
    async getDailyCollectionTarget(date = null) {
        try {
            // Use collections_due action which returns due invoices and amounts
            const params = { action: 'collections_due' };
            if (date) params.date = date;
            const response = await api.get(`${this.baseUrl}/dashboard.php`, { params });
            
            // Return the response directly as it already has the correct format
            if (response.data) {
                return {
                    data: {
                        due_today: response.data.due_today || 0,
                        due_this_week: response.data.due_this_week || 0,
                        overdue: response.data.overdue || 0,
                        collected_mtd: response.data.collected_mtd || 0,
                        invoices: response.data.invoices || []
                    }
                };
            }
            return response;
        } catch (error) {
            console.error('Error fetching daily collection target:', error);
            throw error;
        }
    },

    /**
     * Get orders for a specific customer
     * @param {number} customerId - Customer ID
     * @param {number} limit - Maximum number of orders to return
     */
    async getCustomerOrders(customerId, limit = 20) {
        try {
            return await api.get(`${this.baseUrl}/orders.php`, { 
                params: { action: 'by_customer', customer_id: customerId, limit } 
            });
        } catch (error) {
            console.error('Error fetching customer orders:', error);
            throw error;
        }
    },

    /**
     * Record a collection/payment
     * @param {Object} data - Collection data (customer_id, invoice_id, amount, collection_date, payment_method, reference)
     */
    async recordCollection(data) {
        try {
            return await api.post(`${this.baseUrl}/invoices.php`, {
                action: 'record_payment',
                invoice_id: data.invoice_id,
                amount: data.amount,
                payment_method: data.payment_method,
                payment_date: data.collection_date,
                reference_number: data.reference,
                customer_id: data.customer_id,
                notes: data.notes || ''
            });
        } catch (error) {
            console.error('Error recording collection:', error);
            throw error;
        }
    },

    /**
     * Get sales trend data
     * @param {Object} params - Parameters (start_date, end_date, group_by)
     */
    async getSalesTrendData(params = {}) {
        try {
            return await api.get(`${this.baseUrl}/reports.php`, { 
                params: { action: 'trend', ...params } 
            });
        } catch (error) {
            console.error('Error fetching sales trend data:', error);
            throw error;
        }
    },

    /**
     * Get aging report with customer details for aging page
     */
    async getAgingReportWithCustomers() {
        try {
            const response = await api.get(`${this.baseUrl}/dashboard.php`, { 
                params: { action: 'aging_summary' } 
            });
            
            // Get customer aging details
            const customersResponse = await api.get(`${this.baseUrl}/customers.php`, { 
                params: { action: 'aging' } 
            });
            
            const buckets = response.data?.buckets || {};
            const customers = customersResponse.data || [];
            
            return {
                data: {
                    summary: {
                        total: response.data?.total_outstanding || 0,
                        current: buckets.current?.amount || buckets.days_1_30?.amount || 0,
                        days_31_60: buckets.days_31_60?.amount || 0,
                        days_61_90: buckets.days_61_90?.amount || 0,
                        over_90: buckets.days_91_plus?.amount || 0
                    },
                    customers: customers.map(c => ({
                        id: c.id,
                        customer_name: c.customer_name,
                        customer_code: c.customer_code,
                        customer_type: c.customer_type,
                        credit_limit: c.credit_limit || 0,
                        current: c.balance_current || 0,
                        days_31_60: c.balance_31_60 || 0,
                        days_61_90: c.balance_61_90 || 0,
                        over_90: c.balance_91_plus || 0
                    }))
                }
            };
        } catch (error) {
            console.error('Error fetching aging report with customers:', error);
            throw error;
        }
    }
};

// Make service available globally
window.SalesService = SalesService;
