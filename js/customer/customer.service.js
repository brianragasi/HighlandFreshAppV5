/**
 * Highland Fresh - Customer Portal Service
 * 
 * API service layer for customer self-service portal
 * Uses the global 'api' object from api.js which handles auth tokens
 * 
 * Supports:
 * - Customer authentication (login/register)
 * - Product catalog browsing
 * - Order placement and tracking
 * - Profile management
 * - Shopping cart (local storage)
 * 
 * @version 4.0
 */

const CustomerService = {
    baseUrl: '/customer',
    
    // ========================================
    // Authentication
    // ========================================
    
    /**
     * Customer login
     * @param {string} email - Customer email
     * @param {string} password - Customer password
     */
    async login(email, password) {
        const response = await api.post(`${this.baseUrl}/auth.php`, {
            action: 'login',
            email,
            password
        });
        
        if (response.success) {
            // Store token and customer data
            localStorage.setItem('customer_token', response.data.token);
            localStorage.setItem('customer_data', JSON.stringify(response.data.customer));
        }
        
        return response;
    },
    
    /**
     * Customer registration
     * @param {Object} data - Registration data
     */
    async register(data) {
        const response = await api.post(`${this.baseUrl}/auth.php`, {
            action: 'register',
            ...data
        });
        
        if (response.success) {
            // Store token and customer data
            localStorage.setItem('customer_token', response.data.token);
            localStorage.setItem('customer_data', JSON.stringify(response.data.customer));
        }
        
        return response;
    },
    
    /**
     * Customer logout
     */
    logout() {
        localStorage.removeItem('customer_token');
        localStorage.removeItem('customer_data');
        localStorage.removeItem('customer_cart');
        window.location.href = '/HighlandFreshAppV4/html/customer/login.html';
    },
    
    /**
     * Check if customer is logged in
     */
    isLoggedIn() {
        return !!localStorage.getItem('customer_token');
    },
    
    /**
     * Get current customer data
     */
    getCurrentCustomer() {
        const data = localStorage.getItem('customer_data');
        return data ? JSON.parse(data) : null;
    },
    
    /**
     * Require authentication - redirect if not logged in
     */
    requireAuth() {
        if (!this.isLoggedIn()) {
            window.location.href = '/HighlandFreshAppV4/html/customer/login.html';
            return false;
        }
        return true;
    },
    
    // ========================================
    // Products
    // ========================================
    
    /**
     * Get product list
     * @param {Object} params - Filter parameters (category, search, limit, offset)
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
     * Get product categories
     */
    async getCategories() {
        return await api.get(`${this.baseUrl}/products.php`, { 
            params: { action: 'categories' } 
        });
    },
    
    /**
     * Get featured products
     */
    async getFeaturedProducts() {
        return await api.get(`${this.baseUrl}/products.php`, { 
            params: { action: 'featured' } 
        });
    },
    
    /**
     * Search products
     * @param {string} query - Search query
     */
    async searchProducts(query) {
        return await api.get(`${this.baseUrl}/products.php`, { 
            params: { action: 'search', q: query } 
        });
    },
    
    // ========================================
    // Orders
    // ========================================
    
    /**
     * Get customer orders
     * @param {Object} params - Filter parameters (status, limit, offset)
     */
    async getOrders(params = {}) {
        return await api.get(`${this.baseUrl}/orders.php`, { 
            params: { action: 'list', ...params } 
        });
    },
    
    /**
     * Get single order details
     * @param {number} id - Order ID
     */
    async getOrder(id) {
        return await api.get(`${this.baseUrl}/orders.php`, { 
            params: { action: 'detail', id } 
        });
    },
    
    /**
     * Track order by order number
     * @param {string} orderNumber - Order number
     */
    async trackOrder(orderNumber) {
        return await api.get(`${this.baseUrl}/orders.php`, { 
            params: { action: 'track', order_number: orderNumber } 
        });
    },
    
    /**
     * Get reorder items from last order
     */
    async getReorderItems() {
        return await api.get(`${this.baseUrl}/orders.php`, { 
            params: { action: 'reorder' } 
        });
    },
    
    /**
     * Place new order
     * @param {Object} orderData - Order data
     */
    async placeOrder(orderData) {
        return await api.post(`${this.baseUrl}/orders.php`, {
            action: 'place',
            ...orderData
        });
    },
    
    /**
     * Cancel order
     * @param {number} id - Order ID
     * @param {string} reason - Cancellation reason
     */
    async cancelOrder(id, reason = '') {
        return await api.put(`${this.baseUrl}/orders.php`, {
            action: 'cancel',
            id,
            reason
        });
    },
    
    // ========================================
    // Profile
    // ========================================
    
    /**
     * Get customer profile
     */
    async getProfile() {
        return await api.get(`${this.baseUrl}/profile.php`, { 
            params: { action: 'me' } 
        });
    },
    
    /**
     * Get dashboard statistics
     */
    async getDashboardStats() {
        return await api.get(`${this.baseUrl}/profile.php`, { 
            params: { action: 'dashboard' } 
        });
    },
    
    /**
     * Update customer profile
     * @param {Object} data - Profile data to update
     */
    async updateProfile(data) {
        return await api.put(`${this.baseUrl}/profile.php`, {
            action: 'update',
            ...data
        });
    },
    
    /**
     * Change password
     * @param {string} currentPassword - Current password
     * @param {string} newPassword - New password
     */
    async changePassword(currentPassword, newPassword) {
        return await api.put(`${this.baseUrl}/profile.php`, {
            action: 'password',
            current_password: currentPassword,
            new_password: newPassword
        });
    },
    
    /**
     * Get saved addresses
     */
    async getAddresses() {
        return await api.get(`${this.baseUrl}/profile.php`, { 
            params: { action: 'addresses' } 
        });
    },
    
    /**
     * Update delivery address
     * @param {string} address - New address
     */
    async updateAddress(address) {
        return await api.put(`${this.baseUrl}/profile.php`, {
            action: 'address',
            address
        });
    },
    
    // ========================================
    // Shopping Cart (Local Storage)
    // ========================================
    
    /**
     * Get cart items
     */
    getCart() {
        const cart = localStorage.getItem('customer_cart');
        return cart ? JSON.parse(cart) : [];
    },
    
    /**
     * Save cart to local storage
     * @param {Array} cart - Cart items
     */
    saveCart(cart) {
        localStorage.setItem('customer_cart', JSON.stringify(cart));
        this.updateCartBadge();
    },
    
    /**
     * Add item to cart
     * @param {Object} product - Product to add
     * @param {number} quantity - Quantity
     */
    addToCart(product, quantity = 1) {
        const cart = this.getCart();
        
        const existingIndex = cart.findIndex(item => item.product_id === product.id);
        
        if (existingIndex >= 0) {
            cart[existingIndex].quantity += quantity;
        } else {
            cart.push({
                product_id: product.id,
                product_code: product.product_code,
                name: product.name,
                price: product.price,
                unit: product.unit,
                image_url: product.image_url,
                quantity: quantity
            });
        }
        
        this.saveCart(cart);
        return cart;
    },
    
    /**
     * Update cart item quantity
     * @param {number} productId - Product ID
     * @param {number} quantity - New quantity
     */
    updateCartQuantity(productId, quantity) {
        const cart = this.getCart();
        
        const existingIndex = cart.findIndex(item => item.product_id === productId);
        
        if (existingIndex >= 0) {
            if (quantity <= 0) {
                cart.splice(existingIndex, 1);
            } else {
                cart[existingIndex].quantity = quantity;
            }
        }
        
        this.saveCart(cart);
        return cart;
    },
    
    /**
     * Remove item from cart
     * @param {number} productId - Product ID
     */
    removeFromCart(productId) {
        const cart = this.getCart().filter(item => item.product_id !== productId);
        this.saveCart(cart);
        return cart;
    },
    
    /**
     * Clear cart
     */
    clearCart() {
        localStorage.removeItem('customer_cart');
        this.updateCartBadge();
    },
    
    /**
     * Get cart totals
     */
    getCartTotals() {
        const cart = this.getCart();
        
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
        
        return {
            items: cart.length,
            itemCount: itemCount,
            subtotal: subtotal,
            deliveryFee: 0, // Free delivery
            total: subtotal
        };
    },
    
    /**
     * Update cart badge in header
     */
    updateCartBadge() {
        const badge = document.getElementById('cartBadge');
        if (badge) {
            const totals = this.getCartTotals();
            badge.textContent = totals.itemCount;
            badge.classList.toggle('hidden', totals.itemCount === 0);
        }
    },
    
    // ========================================
    // Utility Functions
    // ========================================
    
    /**
     * Format currency
     * @param {number} amount - Amount to format
     */
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP'
        }).format(amount);
    },
    
    /**
     * Format date
     * @param {string} dateStr - Date string
     * @param {boolean} includeTime - Include time
     */
    formatDate(dateStr, includeTime = false) {
        if (!dateStr) return '--';
        const date = new Date(dateStr);
        const options = { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric'
        };
        if (includeTime) {
            options.hour = '2-digit';
            options.minute = '2-digit';
        }
        return date.toLocaleDateString('en-PH', options);
    },
    
    /**
     * Get order status color for badges
     * @param {string} status - Order status
     */
    getStatusColor(status) {
        const colors = {
            'pending': 'warning',
            'confirmed': 'info',
            'preparing': 'info',
            'out_for_delivery': 'primary',
            'delivered': 'success',
            'cancelled': 'error'
        };
        return colors[status] || 'ghost';
    },
    
    /**
     * Get order status display text
     * @param {string} status - Order status
     */
    getStatusText(status) {
        const texts = {
            'pending': 'Pending Confirmation',
            'confirmed': 'Order Confirmed',
            'preparing': 'Preparing Order',
            'out_for_delivery': 'Out for Delivery',
            'delivered': 'Delivered',
            'cancelled': 'Cancelled'
        };
        return texts[status] || status;
    },
    
    /**
     * Get payment status color
     * @param {string} status - Payment status
     */
    getPaymentStatusColor(status) {
        const colors = {
            'unpaid': 'warning',
            'paid': 'success',
            'refunded': 'error'
        };
        return colors[status] || 'ghost';
    }
};

// Initialize cart badge on page load
document.addEventListener('DOMContentLoaded', () => {
    CustomerService.updateCartBadge();
});
