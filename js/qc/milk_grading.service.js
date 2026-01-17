/**
 * Highland Fresh System - QC Milk Grading Service
 * ANNEX B Pricing Implementation
 * 
 * @package HighlandFresh
 * @version 4.0
 */

const MilkGradingService = {
    // ANNEX B Base Pricing
    BASE_PRICE: 25.00,
    INCENTIVE: 5.00,
    STANDARD_PRICE: 30.00, // Base + Incentive
    
    // ANNEX B Fat Content Adjustments
    FAT_ADJUSTMENTS: {
        '1.5-1.9': -1.00,
        '2.0-2.4': -0.75,
        '2.5-2.9': -0.50,
        '3.0-3.4': -0.25,
        '3.5-4.0': 0.00,  // Standard - no adjustment
        '4.1-4.5': 0.25,
        '4.6-5.0': 0.50,
        '5.1-5.5': 0.75,
        '5.6-6.0': 1.00,
        '6.1-6.5': 1.25,
        '6.6-7.0': 1.50,
        '7.1-7.5': 1.75,
        '7.6-8.0': 2.00,
        '8.1-8.5': 2.25
    },
    
    // ANNEX B Acidity Deductions (Titratable Acidity %)
    ACIDITY_DEDUCTIONS: {
        '0.14-0.18': 0.00,  // Standard - no deduction
        '0.19': 0.25,
        '0.20': 0.50,
        '0.21': 0.75,
        '0.22': 1.00,
        '0.23': 1.25,
        '0.24': 1.50
        // 0.25 and above = REJECTED
    },
    
    // ANNEX B Sediment Grade Deductions
    SEDIMENT_DEDUCTIONS: {
        1: 0.00,  // Grade 1 - Clean
        2: 0.50,  // Grade 2 - Slight
        3: 1.00   // Grade 3 - Dirty
    },
    
    /**
     * Get all QC tests with pagination
     */
    async getAll(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await api.get(`/qc/milk_grading.php?${queryString}`);
    },
    
    /**
     * Get single test by ID
     */
    async getById(id) {
        return await api.get(`/qc/milk_grading.php?id=${id}`);
    },
    
    /**
     * Create new QC test (grade milk)
     */
    async create(data) {
        return await api.post('/qc/milk_grading.php', data);
    },
    
    /**
     * Get tests for a specific delivery
     */
    async getByDelivery(deliveryId) {
        return await this.getAll({ delivery_id: deliveryId });
    },
    
    /**
     * Get tests for a specific farmer
     */
    async getByFarmer(farmerId, params = {}) {
        return await this.getAll({ farmer_id: farmerId, ...params });
    },
    
    /**
     * Get tests by status
     */
    async getByStatus(status, params = {}) {
        return await this.getAll({ status: status, ...params });
    },
    
    /**
     * Get today's tests
     */
    async getToday() {
        const today = new Date().toISOString().split('T')[0];
        return await this.getAll({ date_from: today, date_to: today });
    },
    
    /**
     * Calculate fat adjustment based on ANNEX B
     * @param {number} fatPercentage - Fat content percentage
     * @returns {number} Price adjustment (positive = bonus, negative = deduction)
     */
    calculateFatAdjustment(fatPercentage) {
        const fat = parseFloat(fatPercentage);
        
        if (fat >= 1.5 && fat < 2.0) return -1.00;
        if (fat >= 2.0 && fat < 2.5) return -0.75;
        if (fat >= 2.5 && fat < 3.0) return -0.50;
        if (fat >= 3.0 && fat < 3.5) return -0.25;
        if (fat >= 3.5 && fat <= 4.0) return 0.00;
        if (fat > 4.0 && fat <= 4.5) return 0.25;
        if (fat > 4.5 && fat <= 5.0) return 0.50;
        if (fat > 5.0 && fat <= 5.5) return 0.75;
        if (fat > 5.5 && fat <= 6.0) return 1.00;
        if (fat > 6.0 && fat <= 6.5) return 1.25;
        if (fat > 6.5 && fat <= 7.0) return 1.50;
        if (fat > 7.0 && fat <= 7.5) return 1.75;
        if (fat > 7.5 && fat <= 8.0) return 2.00;
        if (fat > 8.0 && fat <= 8.5) return 2.25;
        
        // Below 1.5% or above 8.5% - return 0 (edge cases)
        return 0;
    },
    
    /**
     * Calculate acidity deduction based on ANNEX B
     * @param {number} titratabledAcidity - Titratable acidity percentage
     * @returns {object} { deduction: number, isRejected: boolean }
     */
    calculateAcidityDeduction(titratableAcidity) {
        const acidity = parseFloat(titratableAcidity);
        
        // REJECTED if acidity >= 0.25%
        if (acidity >= 0.25) {
            return { deduction: 0, isRejected: true, reason: 'Acidity too high (≥0.25%) - will clot in pasteurizer' };
        }
        
        // Standard range - no deduction
        if (acidity >= 0.14 && acidity <= 0.18) return { deduction: 0, isRejected: false };
        
        // Below standard - also acceptable
        if (acidity < 0.14) return { deduction: 0, isRejected: false };
        
        // Deduction ranges
        if (acidity >= 0.19 && acidity < 0.20) return { deduction: 0.25, isRejected: false };
        if (acidity >= 0.20 && acidity < 0.21) return { deduction: 0.50, isRejected: false };
        if (acidity >= 0.21 && acidity < 0.22) return { deduction: 0.75, isRejected: false };
        if (acidity >= 0.22 && acidity < 0.23) return { deduction: 1.00, isRejected: false };
        if (acidity >= 0.23 && acidity < 0.24) return { deduction: 1.25, isRejected: false };
        if (acidity >= 0.24 && acidity < 0.25) return { deduction: 1.50, isRejected: false };
        
        return { deduction: 0, isRejected: false };
    },
    
    /**
     * Calculate sediment deduction based on ANNEX B
     * @param {number} sedimentGrade - Sediment grade (1, 2, or 3)
     * @returns {number} Deduction amount
     */
    calculateSedimentDeduction(sedimentGrade) {
        const grade = parseInt(sedimentGrade);
        return this.SEDIMENT_DEDUCTIONS[grade] || 0;
    },
    
    /**
     * Check if milk should be rejected based on all criteria
     * @param {object} params - { aptResult, titratableAcidity, specificGravity }
     * @returns {object} { isRejected: boolean, reasons: string[] }
     */
    checkRejectionCriteria(params) {
        const reasons = [];
        
        // APT Test - Positive = Reject
        if (params.aptResult === 'positive') {
            reasons.push('APT test positive');
        }
        
        // Titratable Acidity >= 0.25% = Reject
        if (parseFloat(params.titratableAcidity) >= 0.25) {
            reasons.push('Titratable acidity too high (≥0.25%)');
        }
        
        // Specific Gravity < 1.025 = Reject (suspected adulteration)
        if (params.specificGravity && parseFloat(params.specificGravity) < 1.025) {
            reasons.push('Specific gravity below 1.025 (suspected adulteration)');
        }
        
        return {
            isRejected: reasons.length > 0,
            reasons: reasons
        };
    },
    
    /**
     * Calculate full price breakdown using ANNEX B
     * @param {object} params - Test parameters
     * @returns {object} Complete price calculation
     */
    calculatePricing(params) {
        const { fatPercentage, titratableAcidity, sedimentGrade, specificGravity, aptResult, volumeLiters } = params;
        
        // First check rejection criteria
        const rejection = this.checkRejectionCriteria({
            aptResult,
            titratableAcidity,
            specificGravity
        });
        
        if (rejection.isRejected) {
            return {
                isAccepted: false,
                rejectionReasons: rejection.reasons,
                basePrice: this.STANDARD_PRICE,
                fatAdjustment: 0,
                acidityDeduction: 0,
                sedimentDeduction: 0,
                finalPricePerLiter: 0,
                totalAmount: 0
            };
        }
        
        // Calculate adjustments
        const fatAdjustment = this.calculateFatAdjustment(fatPercentage);
        const acidityResult = this.calculateAcidityDeduction(titratableAcidity);
        const sedimentDeduction = this.calculateSedimentDeduction(sedimentGrade);
        
        // Final price per liter
        const finalPricePerLiter = this.STANDARD_PRICE + fatAdjustment - acidityResult.deduction - sedimentDeduction;
        
        // Total amount
        const totalAmount = parseFloat(volumeLiters) * finalPricePerLiter;
        
        return {
            isAccepted: true,
            rejectionReasons: [],
            basePrice: this.STANDARD_PRICE,
            fatAdjustment: fatAdjustment,
            acidityDeduction: acidityResult.deduction,
            sedimentDeduction: sedimentDeduction,
            finalPricePerLiter: finalPricePerLiter,
            totalAmount: totalAmount
        };
    }
};
