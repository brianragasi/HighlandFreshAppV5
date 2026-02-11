/**
 * Highland Fresh - Warehouse Finished Goods Module Services
 * 
 * API service layer for finished goods warehouse management
 * Uses the global 'api' object from api.js which handles auth tokens
 * 
 * Supports:
 * - Dashboard statistics
 * - Chiller management with temperature logging
 * - Product catalog and variants
 * - Finished goods inventory (FIFO)
 * - Delivery receipts (DR) management
 * - Order/PO fulfillment
 * - Barcode scanning and validation
 * - Customer management
 * 
 * @version 4.0
 */

const WarehouseFGService = {
    baseUrl: '/warehouse/fg',

    // ========================================
    // Dashboard
    // ========================================

    /**
     * Get warehouse FG dashboard statistics
     */
    async getDashboardStats() {
        return await api.get(`${this.baseUrl}/dashboard.php`);
    },

    /**
     * Refresh dashboard data (alias for getDashboardStats)
     */
    async refreshDashboard() {
        return await this.getDashboardStats();
    },

    // ========================================
    // Chillers
    // ========================================

    /**
     * Get all chillers
     * @param {Object} params - Filter parameters
     */
    async getChillers(params = {}) {
        return await api.get(`${this.baseUrl}/chillers.php`, { params: { action: 'list', ...params } });
    },

    /**
     * Get single chiller with inventory
     * @param {number} id - Chiller ID
     */
    async getChiller(id) {
        return await api.get(`${this.baseUrl}/chillers.php`, { params: { action: 'detail', id } });
    },

    /**
     * Get chiller summary statistics
     */
    async getChillerSummary() {
        return await api.get(`${this.baseUrl}/chillers.php`, { params: { action: 'summary' } });
    },

    /**
     * Create a new chiller
     * @param {Object} data - Chiller data
     */
    async createChiller(data) {
        return await api.post(`${this.baseUrl}/chillers.php`, {
            action: 'create',
            ...data
        });
    },

    /**
     * Update chiller details
     * @param {number} id - Chiller ID
     * @param {Object} data - Updated data
     */
    async updateChiller(id, data) {
        return await api.put(`${this.baseUrl}/chillers.php`, {
            action: 'update',
            id,
            ...data
        });
    },

    /**
     * Update chiller temperature
     * @param {number} id - Chiller ID
     * @param {number} temperature - New temperature reading
     */
    async updateChillerTemperature(id, temperature) {
        return await api.put(`${this.baseUrl}/chillers.php`, {
            action: 'update_temp',
            id,
            temperature_celsius: temperature
        });
    },

    /**
     * Get temperature logs for a chiller
     * @param {number} id - Chiller ID
     * @param {Object} params - Filter parameters (from_date, to_date, limit)
     */
    async getChillerTemperatureLogs(id, params = {}) {
        return await api.get(`${this.baseUrl}/chillers.php`, {
            params: { action: 'temp_logs', id, ...params }
        });
    },

    /**
     * Log a temperature reading for a chiller (creates history record)
     * @param {number} id - Chiller ID
     * @param {number} temperature - Temperature reading in Celsius
     * @param {string} notes - Optional notes
     */
    async logChillerTemperature(id, temperature, notes = '') {
        return await api.post(`${this.baseUrl}/chillers.php`, {
            action: 'log_temp',
            id,
            temperature_celsius: temperature,
            notes
        });
    },

    /**
     * Deactivate/delete a chiller (soft delete)
     * @param {number} id - Chiller ID
     */
    async deleteChiller(id) {
        return await api.put(`${this.baseUrl}/chillers.php`, {
            action: 'deactivate',
            id
        });
    },

    // ========================================
    // Products
    // ========================================

    /**
     * Get all products (finished goods catalog)
     * @param {Object} params - Filter parameters (category, search, active)
     */
    async getProducts(params = {}) {
        return await api.get(`${this.baseUrl}/products.php`, {
            params: { action: 'list', ...params }
        });
    },

    /**
     * Get single product details
     * @param {number} id - Product ID
     */
    async getProduct(id) {
        return await api.get(`${this.baseUrl}/products.php`, {
            params: { action: 'detail', id }
        });
    },

    /**
     * Get product variants (sizes, flavors)
     * @param {number} productId - Base product ID (optional)
     */
    async getProductVariants(productId = null) {
        const params = { action: 'variants' };
        if (productId) params.product_id = productId;
        return await api.get(`${this.baseUrl}/products.php`, { params });
    },

    /**
     * Get product categories
     */
    async getProductCategories() {
        return await api.get(`${this.baseUrl}/products.php`, {
            params: { action: 'categories' }
        });
    },

    // ========================================
    // Finished Goods Inventory
    // ========================================

    /**
     * Get FG inventory list
     * @param {Object} params - Filter parameters (product_id, chiller_id, status)
     */
    async getInventory(params = {}) {
        return await api.get(`${this.baseUrl}/inventory.php`, { params: { action: 'list', ...params } });
    },

    /**
     * Get single inventory item details
     * @param {number} id - Inventory ID
     */
    async getInventoryItem(id) {
        return await api.get(`${this.baseUrl}/inventory.php`, { params: { action: 'detail', id } });
    },

    /**
     * Get inventory by batch code
     * @param {string} batchCode - Production batch code
     */
    async getInventoryByBatch(batchCode) {
        return await api.get(`${this.baseUrl}/inventory.php`, {
            params: { action: 'by_batch', batch_code: batchCode }
        });
    },

    /**
     * Get items expiring within specified days
     * @param {number} days - Days until expiry (default 3)
     */
    async getExpiringInventory(days = 3) {
        return await api.get(`${this.baseUrl}/inventory.php`, { params: { action: 'expiring', days } });
    },

    /**
     * Get available inventory for orders (FIFO order)
     * @param {number} productId - Optional filter by product
     */
    async getAvailableInventory(productId = null) {
        const params = { action: 'available' };
        if (productId) params.product_id = productId;
        return await api.get(`${this.baseUrl}/inventory.php`, { params });
    },

    /**
     * Get oldest batches first for FIFO release
     * @param {number} productId - Product ID
     * @param {number} quantity - Required quantity (optional, for availability check)
     */
    async getFIFOBatches(productId, quantity = null) {
        const params = { action: 'fifo', product_id: productId };
        if (quantity) params.quantity_needed = quantity;
        return await api.get(`${this.baseUrl}/inventory.php`, { params });
    },

    /**
     * Get low stock alerts
     * @param {number} threshold - Stock threshold percentage (default 20)
     */
    async getLowStockAlerts(threshold = 20) {
        return await api.get(`${this.baseUrl}/inventory.php`, {
            params: { action: 'low_stock', threshold }
        });
    },

    /**
     * Receive inventory from production
     * @param {Object} data - Receiving data
     */
    async receiveInventory(data) {
        return await api.post(`${this.baseUrl}/inventory.php`, {
            action: 'receive',
            ...data
        });
    },

    /**
     * Receive a batch from production (after QC release)
     * @param {number} batchId - Production batch ID
     * @param {number} chillerId - Target chiller ID
     * @param {string} notes - Optional notes
     */
    async receiveBatchFromProduction(batchId, chillerId, notes = '') {
        return await api.post(`${this.baseUrl}/inventory.php`, {
            action: 'receive_batch',
            batch_id: batchId,
            chiller_id: chillerId,
            notes
        });
    },

    /**
     * Get pending batches from production (QC released, not yet received)
     */
    async getPendingBatches() {
        return await api.get(`${this.baseUrl}/inventory.php`, {
            params: { action: 'pending_batches' }
        });
    },

    /**
     * Transfer inventory between chillers
     * @param {number} inventoryId - Inventory item ID
     * @param {number} toChillerId - Destination chiller ID
     * @param {string} reason - Transfer reason
     */
    async transferInventory(inventoryId, toChillerId, reason = '') {
        return await api.put(`${this.baseUrl}/inventory.php`, {
            action: 'transfer',
            id: inventoryId,
            to_chiller_id: toChillerId,
            reason
        });
    },

    /**
     * Adjust inventory quantity (physical count discrepancy)
     * @param {number} inventoryId - Inventory item ID
     * @param {number} newQuantity - New quantity
     * @param {string} reason - Adjustment reason
     */
    async adjustInventory(inventoryId, newQuantity, reason) {
        return await api.put(`${this.baseUrl}/inventory.php`, {
            action: 'adjust',
            id: inventoryId,
            new_quantity: newQuantity,
            reason
        });
    },

    /**
     * Dispose expired/damaged inventory
     * @param {number} inventoryId - Inventory item ID
     * @param {string} reason - Disposal reason
     */
    async disposeInventory(inventoryId, reason) {
        return await api.put(`${this.baseUrl}/inventory.php`, {
            action: 'dispose',
            id: inventoryId,
            reason
        });
    },

    // ========================================
    // Inventory Transactions
    // ========================================

    /**
     * Get inventory transactions history
     * @param {Object} params - Filter parameters (type, product_id, from_date, to_date)
     */
    async getInventoryTransactions(params = {}) {
        return await api.get(`${this.baseUrl}/inventory.php`, {
            params: { action: 'transactions', ...params }
        });
    },

    /**
     * Get single transaction details
     * @param {number} id - Transaction ID
     */
    async getInventoryTransactionDetail(id) {
        return await api.get(`${this.baseUrl}/inventory.php`, {
            params: { action: 'transaction_detail', id }
        });
    },

    // ========================================
    // Delivery Receipts
    // ========================================

    /**
     * Get delivery receipts list
     * @param {Object} params - Filter parameters (status, customer_id, from_date, to_date)
     */
    async getDeliveryReceipts(params = {}) {
        return await api.get(`${this.baseUrl}/delivery_receipts.php`, { params: { action: 'list', ...params } });
    },

    /**
     * Get single delivery receipt with items
     * @param {number} id - DR ID
     */
    async getDeliveryReceipt(id) {
        return await api.get(`${this.baseUrl}/delivery_receipts.php`, { params: { action: 'detail', id } });
    },

    /**
     * Get pending delivery receipts (draft, pending, preparing)
     */
    async getPendingDRs() {
        return await api.get(`${this.baseUrl}/delivery_receipts.php`, { params: { action: 'pending' } });
    },

    /**
     * Create new delivery receipt
     * @param {Object} data - DR data (customer_type, customer_name, delivery_address, etc.)
     */
    async createDeliveryReceipt(data) {
        return await api.post(`${this.baseUrl}/delivery_receipts.php`, {
            action: 'create',
            ...data
        });
    },

    /**
     * Update delivery receipt
     * @param {number} id - DR ID
     * @param {Object} data - Updated data
     */
    async updateDeliveryReceipt(id, data) {
        return await api.put(`${this.baseUrl}/delivery_receipts.php`, {
            action: 'update',
            id,
            ...data
        });
    },

    /**
     * Add item to delivery receipt
     * @param {number} drId - DR ID
     * @param {Object} itemData - Item details (inventory_id, product_id, quantity, etc.)
     */
    async addDRItem(drId, itemData) {
        return await api.post(`${this.baseUrl}/delivery_receipts.php`, {
            action: 'add_item',
            dr_id: drId,
            ...itemData
        });
    },

    /**
     * Remove item from delivery receipt
     * @param {number} drId - DR ID
     * @param {number} itemId - DR Item ID
     */
    async removeDRItem(drId, itemId) {
        return await api.put(`${this.baseUrl}/delivery_receipts.php`, {
            action: 'remove_item',
            dr_id: drId,
            item_id: itemId
        });
    },

    /**
     * Update DR item quantity
     * @param {number} drId - DR ID
     * @param {number} itemId - DR Item ID
     * @param {number} quantity - New quantity
     */
    async updateDRItem(drId, itemId, quantity) {
        return await api.put(`${this.baseUrl}/delivery_receipts.php`, {
            action: 'update_item',
            dr_id: drId,
            item_id: itemId,
            quantity
        });
    },

    /**
     * Release/dispatch delivery receipt
     * @param {number} id - DR ID
     */
    async releaseDR(id) {
        return await api.put(`${this.baseUrl}/delivery_receipts.php`, {
            action: 'release',
            id
        });
    },

    /**
     * Mark DR as delivered
     * @param {number} id - DR ID
     * @param {string} receivedBy - Name of person who received
     * @param {string} notes - Delivery notes
     */
    async markDelivered(id, receivedBy = '', notes = '') {
        return await api.put(`${this.baseUrl}/delivery_receipts.php`, {
            action: 'deliver',
            id,
            received_by: receivedBy,
            notes
        });
    },

    /**
     * Cancel delivery receipt
     * @param {number} id - DR ID
     * @param {string} reason - Cancellation reason
     */
    async cancelDR(id, reason = '') {
        return await api.put(`${this.baseUrl}/delivery_receipts.php`, {
            action: 'cancel',
            id,
            reason
        });
    },

    /**
     * Get printable DR data
     * @param {number} id - DR ID
     */
    async printDeliveryReceipt(id) {
        return await api.get(`${this.baseUrl}/delivery_receipts.php`, {
            params: { action: 'print', id }
        });
    },

    // ========================================
    // Orders / PO Fulfillment
    // ========================================

    /**
     * Get pending orders for fulfillment (from Sales Custodian)
     * @param {Object} params - Filter parameters (customer_type, priority)
     */
    async getPendingOrders(params = {}) {
        return await api.get(`${this.baseUrl}/orders.php`, {
            params: { action: 'pending', ...params }
        });
    },

    /**
     * Get single order details
     * @param {number} id - Order/PO ID
     */
    async getOrder(id) {
        return await api.get(`${this.baseUrl}/orders.php`, {
            params: { action: 'detail', id }
        });
    },

    /**
     * Get pending order count
     */
    async getPendingOrderCount() {
        return await api.get(`${this.baseUrl}/orders.php`, {
            params: { action: 'pending_count' }
        });
    },

    /**
     * Pick items for an order (select inventory to fulfill)
     * @param {number} orderId - Order ID
     * @param {Array} items - Array of {order_item_id, inventory_id, quantity}
     */
    async pickOrderItems(orderId, items) {
        return await api.post(`${this.baseUrl}/orders.php`, {
            action: 'pick',
            order_id: orderId,
            items
        });
    },

    /**
     * Mark order as fulfilled (creates DR automatically)
     * @param {number} orderId - Order ID
     * @param {Object} options - Fulfillment options (create_dr, notes)
     */
    async fulfillOrder(orderId, options = {}) {
        return await api.put(`${this.baseUrl}/orders.php`, {
            action: 'fulfill',
            order_id: orderId,
            ...options
        });
    },

    // ========================================
    // Customers
    // ========================================

    /**
     * Get customers list
     * @param {Object} params - Filter parameters
     */
    async getCustomers(params = {}) {
        return await api.get(`${this.baseUrl}/customers.php`, { params: { action: 'list', ...params } });
    },

    /**
     * Get single customer
     * @param {number} id - Customer ID
     */
    async getCustomer(id) {
        return await api.get(`${this.baseUrl}/customers.php`, { params: { action: 'detail', id } });
    },

    /**
     * Search customers
     * @param {string} query - Search query
     */
    async searchCustomers(query) {
        return await api.get(`${this.baseUrl}/customers.php`, { params: { action: 'search', q: query } });
    },

    /**
     * Create new customer
     * @param {Object} data - Customer data
     */
    async createCustomer(data) {
        return await api.post(`${this.baseUrl}/customers.php`, {
            action: 'create',
            ...data
        });
    },

    /**
     * Update customer
     * @param {number} id - Customer ID
     * @param {Object} data - Updated data
     */
    async updateCustomer(id, data) {
        return await api.put(`${this.baseUrl}/customers.php`, {
            action: 'update',
            id,
            ...data
        });
    },

    // ========================================
    // Dispatch / Release / Barcode
    // ========================================

    /**
     * Get dispatch history
     * @param {Object} params - Filter parameters (from_date, to_date, dr_id)
     */
    async getDispatchHistory(params = {}) {
        return await api.get(`${this.baseUrl}/dispatch.php`, { params: { action: 'history', ...params } });
    },

    /**
     * Validate barcode scan for release
     * @param {string} barcode - Scanned barcode
     */
    async validateBarcode(barcode) {
        return await api.get(`${this.baseUrl}/dispatch.php`, { params: { action: 'validate_barcode', barcode } });
    },

    /**
     * Lookup batch/inventory by barcode
     * @param {string} barcode - Product barcode (contains mfg date, expiry, batch info)
     */
    async lookupBatchByBarcode(barcode) {
        return await api.get(`${this.baseUrl}/dispatch.php`, {
            params: { action: 'lookup_barcode', barcode }
        });
    },

    /**
     * Check FIFO compliance for release
     * @param {number} inventoryId - Inventory ID to release
     * @param {number} productId - Product ID
     */
    async checkFIFOCompliance(inventoryId, productId) {
        return await api.get(`${this.baseUrl}/dispatch.php`, {
            params: { action: 'check_fifo', inventory_id: inventoryId, product_id: productId }
        });
    },

    /**
     * Release item from inventory (deduct stock, link to DR)
     * @param {number} inventoryId - Inventory ID
     * @param {number} quantity - Quantity to release
     * @param {number} drId - Delivery receipt ID
     * @param {string} barcode - Scanned barcode (for verification)
     */
    async releaseItem(inventoryId, quantity, drId, barcode = null) {
        return await api.post(`${this.baseUrl}/dispatch.php`, {
            action: 'release',
            inventory_id: inventoryId,
            quantity,
            dr_id: drId,
            barcode
        });
    },

    /**
     * Bulk release items (multiple products in one transaction)
     * @param {number} drId - Delivery receipt ID
     * @param {Array} items - Array of {inventory_id, quantity, barcode}
     */
    async bulkReleaseItems(drId, items) {
        return await api.post(`${this.baseUrl}/dispatch.php`, {
            action: 'bulk_release',
            dr_id: drId,
            items
        });
    },

    /**
     * Finalize picking - generates official DR number and deducts inventory
     * Called when all items for a picking ticket (PICK-xxx) have been scanned
     * @param {number} drId - Picking ticket ID (with status='picking')
     * @param {Array} items - Array of {inventory_id, quantity_boxes, quantity_pieces, total_pieces}
     */
    async finalizePicking(drId, items) {
        return await api.put(`${this.baseUrl}/delivery_receipts.php`, {
            action: 'finalize_picking',
            id: drId,
            items
        });
    },

    /**
     * Get release summary for a DR
     * @param {number} drId - Delivery receipt ID
     */
    async getReleaseSummary(drId) {
        return await api.get(`${this.baseUrl}/dispatch.php`, {
            params: { action: 'release_summary', dr_id: drId }
        });
    }
};

// Make service available globally
window.WarehouseFGService = WarehouseFGService;
