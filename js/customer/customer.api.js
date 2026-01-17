/**
 * Highland Fresh System - Customer API Configuration
 * 
 * Overrides the default api interceptor to use customer_token
 * for customer portal authentication
 * 
 * @package HighlandFresh
 * @version 4.0
 */

// Override request interceptor for customer portal
if (typeof api !== 'undefined') {
    // Remove existing interceptor and add customer-specific one
    api.interceptors.request.handlers = [];
    
    api.interceptors.request.use(
        (config) => {
            // Use customer token for customer portal
            const token = localStorage.getItem('customer_token');
            if (token) {
                config.headers.Authorization = `Bearer ${token}`;
            }
            return config;
        },
        (error) => {
            return Promise.reject(error);
        }
    );
    
    // Update response interceptor to redirect to customer login
    api.interceptors.response.handlers = [];
    
    api.interceptors.response.use(
        (response) => {
            return response.data;
        },
        (error) => {
            if (error.response) {
                const { status, data } = error.response;
                
                if (status === 401) {
                    // Unauthorized - redirect to customer login
                    localStorage.removeItem('customer_token');
                    localStorage.removeItem('customer_data');
                    window.location.href = '/HighlandFreshAppV4/html/customer/login.html';
                } else if (status === 403) {
                    showNotification('Access Denied', 'You do not have permission to perform this action.', 'error');
                } else if (status === 422) {
                    return Promise.reject({
                        type: 'validation',
                        errors: data.errors,
                        message: data.message
                    });
                }
                
                return Promise.reject({
                    type: 'error',
                    message: data.message || 'An error occurred',
                    status: status
                });
            } else if (error.request) {
                return Promise.reject({
                    type: 'network',
                    message: 'Network error. Please check your connection.'
                });
            }
            
            return Promise.reject(error);
        }
    );
}
