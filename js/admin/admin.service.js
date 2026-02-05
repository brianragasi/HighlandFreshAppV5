/**
 * Admin Service
 * Handles all admin/settings API calls for master data management
 */

const AdminService = {
    
    // ========================
    // USER MANAGEMENT
    // ========================
    
    async getUsers(params = {}) {
        try {
            const queryString = new URLSearchParams(params).toString();
            const url = `${ApiConfig.baseUrl}/admin/users.php${queryString ? '?' + queryString : ''}`;
            const response = await fetch(url, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching users:', error);
            throw error;
        }
    },
    
    async getUser(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/users.php?id=${id}`, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching user:', error);
            throw error;
        }
    },
    
    async createUser(userData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/users.php`, {
                method: 'POST',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(userData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error creating user:', error);
            throw error;
        }
    },
    
    async updateUser(id, userData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/users.php?id=${id}`, {
                method: 'PUT',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(userData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating user:', error);
            throw error;
        }
    },
    
    async deleteUser(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/users.php?id=${id}`, {
                method: 'DELETE',
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting user:', error);
            throw error;
        }
    },
    
    // ========================
    // FARMER MANAGEMENT
    // ========================
    
    async getFarmers(params = {}) {
        try {
            const queryString = new URLSearchParams(params).toString();
            const url = `${ApiConfig.baseUrl}/admin/farmers.php${queryString ? '?' + queryString : ''}`;
            const response = await fetch(url, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching farmers:', error);
            throw error;
        }
    },
    
    async getFarmer(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/farmers.php?id=${id}`, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching farmer:', error);
            throw error;
        }
    },
    
    async createFarmer(farmerData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/farmers.php`, {
                method: 'POST',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(farmerData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error creating farmer:', error);
            throw error;
        }
    },
    
    async updateFarmer(id, farmerData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/farmers.php?id=${id}`, {
                method: 'PUT',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(farmerData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating farmer:', error);
            throw error;
        }
    },
    
    async deleteFarmer(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/farmers.php?id=${id}`, {
                method: 'DELETE',
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting farmer:', error);
            throw error;
        }
    },
    
    // ========================
    // CUSTOMER MANAGEMENT
    // ========================
    
    async getCustomers(params = {}) {
        try {
            const queryString = new URLSearchParams(params).toString();
            const url = `${ApiConfig.baseUrl}/admin/customers.php${queryString ? '?' + queryString : ''}`;
            const response = await fetch(url, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching customers:', error);
            throw error;
        }
    },
    
    async getCustomer(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/customers.php?id=${id}`, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching customer:', error);
            throw error;
        }
    },
    
    async createCustomer(customerData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/customers.php`, {
                method: 'POST',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(customerData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error creating customer:', error);
            throw error;
        }
    },
    
    async updateCustomer(id, customerData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/customers.php?id=${id}`, {
                method: 'PUT',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(customerData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating customer:', error);
            throw error;
        }
    },
    
    async deleteCustomer(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/customers.php?id=${id}`, {
                method: 'DELETE',
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting customer:', error);
            throw error;
        }
    },
    
    // ========================
    // PRODUCT MANAGEMENT
    // ========================
    
    async getProducts(params = {}) {
        try {
            const queryString = new URLSearchParams(params).toString();
            const url = `${ApiConfig.baseUrl}/admin/products.php${queryString ? '?' + queryString : ''}`;
            const response = await fetch(url, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching products:', error);
            throw error;
        }
    },
    
    async getProduct(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/products.php?id=${id}`, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching product:', error);
            throw error;
        }
    },
    
    async createProduct(productData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/products.php`, {
                method: 'POST',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(productData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error creating product:', error);
            throw error;
        }
    },
    
    async updateProduct(id, productData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/products.php?id=${id}`, {
                method: 'PUT',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(productData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating product:', error);
            throw error;
        }
    },
    
    async deleteProduct(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/products.php?id=${id}`, {
                method: 'DELETE',
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting product:', error);
            throw error;
        }
    },
    
    // ========================
    // RECIPE MANAGEMENT
    // ========================
    
    async getRecipes(params = {}) {
        try {
            const queryString = new URLSearchParams(params).toString();
            const url = `${ApiConfig.baseUrl}/admin/recipes.php${queryString ? '?' + queryString : ''}`;
            const response = await fetch(url, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching recipes:', error);
            throw error;
        }
    },
    
    async getRecipe(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/recipes.php?id=${id}`, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching recipe:', error);
            throw error;
        }
    },
    
    async createRecipe(recipeData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/recipes.php`, {
                method: 'POST',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(recipeData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error creating recipe:', error);
            throw error;
        }
    },
    
    async updateRecipe(id, recipeData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/recipes.php?id=${id}`, {
                method: 'PUT',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(recipeData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating recipe:', error);
            throw error;
        }
    },
    
    async deleteRecipe(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/recipes.php?id=${id}`, {
                method: 'DELETE',
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting recipe:', error);
            throw error;
        }
    },
    
    // ========================
    // INGREDIENT MANAGEMENT
    // ========================
    
    async getIngredients(params = {}) {
        try {
            const queryString = new URLSearchParams(params).toString();
            const url = `${ApiConfig.baseUrl}/admin/ingredients.php${queryString ? '?' + queryString : ''}`;
            const response = await fetch(url, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching ingredients:', error);
            throw error;
        }
    },
    
    async getIngredient(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/ingredients.php?id=${id}`, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching ingredient:', error);
            throw error;
        }
    },
    
    async createIngredient(ingredientData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/ingredients.php`, {
                method: 'POST',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(ingredientData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error creating ingredient:', error);
            throw error;
        }
    },
    
    async updateIngredient(id, ingredientData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/ingredients.php?id=${id}`, {
                method: 'PUT',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(ingredientData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating ingredient:', error);
            throw error;
        }
    },
    
    async deleteIngredient(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/ingredients.php?id=${id}`, {
                method: 'DELETE',
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting ingredient:', error);
            throw error;
        }
    },
    
    // ========================
    // STORAGE/TANKS MANAGEMENT
    // ========================
    
    async getStorageTanks(params = {}) {
        try {
            const queryString = new URLSearchParams(params).toString();
            const url = `${ApiConfig.baseUrl}/admin/storage.php${queryString ? '?' + queryString : ''}`;
            const response = await fetch(url, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching storage tanks:', error);
            throw error;
        }
    },
    
    async getStorageTank(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/storage.php?id=${id}`, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching storage tank:', error);
            throw error;
        }
    },
    
    async createStorageTank(tankData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/storage.php`, {
                method: 'POST',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(tankData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error creating storage tank:', error);
            throw error;
        }
    },
    
    async updateStorageTank(id, tankData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/storage.php?id=${id}`, {
                method: 'PUT',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(tankData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating storage tank:', error);
            throw error;
        }
    },
    
    async deleteStorageTank(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/storage.php?id=${id}`, {
                method: 'DELETE',
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting storage tank:', error);
            throw error;
        }
    },
    
    // ========================
    // CHILLER MANAGEMENT
    // ========================
    
    async getChillers(params = {}) {
        try {
            const queryString = new URLSearchParams(params).toString();
            const url = `${ApiConfig.baseUrl}/admin/chillers.php${queryString ? '?' + queryString : ''}`;
            const response = await fetch(url, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching chillers:', error);
            throw error;
        }
    },
    
    async getChiller(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/chillers.php?id=${id}`, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching chiller:', error);
            throw error;
        }
    },
    
    async createChiller(chillerData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/chillers.php`, {
                method: 'POST',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(chillerData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error creating chiller:', error);
            throw error;
        }
    },
    
    async updateChiller(id, chillerData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/chillers.php?id=${id}`, {
                method: 'PUT',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(chillerData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating chiller:', error);
            throw error;
        }
    },
    
    async deleteChiller(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/chillers.php?id=${id}`, {
                method: 'DELETE',
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting chiller:', error);
            throw error;
        }
    },
    
    // ========================
    // QC STANDARDS MANAGEMENT
    // ========================
    
    async getQcStandards(params = {}) {
        try {
            const queryString = new URLSearchParams(params).toString();
            const url = `${ApiConfig.baseUrl}/admin/qc-standards.php${queryString ? '?' + queryString : ''}`;
            const response = await fetch(url, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching QC standards:', error);
            throw error;
        }
    },
    
    async getQcStandard(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/qc-standards.php?id=${id}`, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching QC standard:', error);
            throw error;
        }
    },
    
    async createQcStandard(standardData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/qc-standards.php`, {
                method: 'POST',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(standardData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error creating QC standard:', error);
            throw error;
        }
    },
    
    async updateQcStandard(id, standardData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/qc-standards.php?id=${id}`, {
                method: 'PUT',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(standardData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating QC standard:', error);
            throw error;
        }
    },
    
    async deleteQcStandard(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/qc-standards.php?id=${id}`, {
                method: 'DELETE',
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting QC standard:', error);
            throw error;
        }
    },
    
    // ========================
    // GRADING STANDARDS
    // ========================
    
    async getGradingStandards(params = {}) {
        try {
            params.type = 'grading';
            const queryString = new URLSearchParams(params).toString();
            const url = `${ApiConfig.baseUrl}/admin/qc-standards.php?${queryString}`;
            const response = await fetch(url, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching grading standards:', error);
            throw error;
        }
    },
    
    async getGradingStandard(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/qc-standards.php?type=grading&id=${id}`, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching grading standard:', error);
            throw error;
        }
    },
    
    async createGradingStandard(standardData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/qc-standards.php?type=grading`, {
                method: 'POST',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(standardData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error creating grading standard:', error);
            throw error;
        }
    },
    
    async updateGradingStandard(id, standardData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/qc-standards.php?type=grading&id=${id}`, {
                method: 'PUT',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(standardData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating grading standard:', error);
            throw error;
        }
    },
    
    async deleteGradingStandard(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/qc-standards.php?type=grading&id=${id}`, {
                method: 'DELETE',
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting grading standard:', error);
            throw error;
        }
    },
    
    // ========================
    // CCP STANDARDS
    // ========================
    
    async getCcpStandards(params = {}) {
        try {
            params.type = 'ccp';
            const queryString = new URLSearchParams(params).toString();
            const url = `${ApiConfig.baseUrl}/admin/qc-standards.php?${queryString}`;
            const response = await fetch(url, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching CCP standards:', error);
            throw error;
        }
    },
    
    async getCcpStandard(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/qc-standards.php?type=ccp&id=${id}`, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching CCP standard:', error);
            throw error;
        }
    },
    
    async createCcpStandard(standardData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/qc-standards.php?type=ccp`, {
                method: 'POST',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(standardData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error creating CCP standard:', error);
            throw error;
        }
    },
    
    async updateCcpStandard(id, standardData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/qc-standards.php?type=ccp&id=${id}`, {
                method: 'PUT',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(standardData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating CCP standard:', error);
            throw error;
        }
    },
    
    async deleteCcpStandard(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/qc-standards.php?type=ccp&id=${id}`, {
                method: 'DELETE',
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting CCP standard:', error);
            throw error;
        }
    },
    
    // ========================
    // SUPPLIER MANAGEMENT
    // ========================
    
    async getSuppliers(params = {}) {
        try {
            const queryString = new URLSearchParams(params).toString();
            const url = `${ApiConfig.baseUrl}/admin/suppliers.php${queryString ? '?' + queryString : ''}`;
            const response = await fetch(url, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching suppliers:', error);
            throw error;
        }
    },
    
    async getSupplier(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/suppliers.php?id=${id}`, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching supplier:', error);
            throw error;
        }
    },
    
    async createSupplier(supplierData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/suppliers.php`, {
                method: 'POST',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(supplierData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error creating supplier:', error);
            throw error;
        }
    },
    
    async updateSupplier(id, supplierData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/suppliers.php?id=${id}`, {
                method: 'PUT',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(supplierData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating supplier:', error);
            throw error;
        }
    },
    
    async deleteSupplier(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/suppliers.php?id=${id}`, {
                method: 'DELETE',
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting supplier:', error);
            throw error;
        }
    },
    
    // ========================
    // MILK TYPES MANAGEMENT
    // ========================
    
    async getMilkTypes(params = {}) {
        try {
            const queryString = new URLSearchParams(params).toString();
            const url = `${ApiConfig.baseUrl}/admin/milk-types.php${queryString ? '?' + queryString : ''}`;
            const response = await fetch(url, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching milk types:', error);
            throw error;
        }
    },
    
    async getMilkType(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/milk-types.php?id=${id}`, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching milk type:', error);
            throw error;
        }
    },
    
    async createMilkType(milkTypeData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/milk-types.php`, {
                method: 'POST',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(milkTypeData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error creating milk type:', error);
            throw error;
        }
    },
    
    async updateMilkType(id, milkTypeData) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/milk-types.php?id=${id}`, {
                method: 'PUT',
                headers: ApiConfig.getHeaders(),
                body: JSON.stringify(milkTypeData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating milk type:', error);
            throw error;
        }
    },
    
    async deleteMilkType(id) {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/milk-types.php?id=${id}`, {
                method: 'DELETE',
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error deleting milk type:', error);
            throw error;
        }
    },
    
    // ========================
    // DASHBOARD STATS
    // ========================
    
    async getDashboardStats() {
        try {
            const response = await fetch(`${ApiConfig.baseUrl}/admin/dashboard.php`, {
                headers: ApiConfig.getHeaders()
            });
            return await response.json();
        } catch (error) {
            console.error('Error fetching dashboard stats:', error);
            throw error;
        }
    }
};
