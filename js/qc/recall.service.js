/**
 * Batch Recall Service
 * Highland Fresh Quality Control System
 * 
 * JavaScript service for managing product recalls
 * 
 * @package HighlandFresh
 * @version 4.0
 */

class RecallService {

    static API_PATH = '/qc/recalls.php';

    // Recall classification constants
    static CLASSES = {
        class_i: {
            label: 'Class I - Dangerous',
            shortLabel: 'Class I',
            description: 'Could cause serious health problems or death',
            color: 'error',
            icon: 'skull-crossbones',
            severity: 1
        },
        class_ii: {
            label: 'Class II - May Cause Harm',
            shortLabel: 'Class II',
            description: 'Might cause temporary or reversible health problems',
            color: 'warning',
            icon: 'exclamation-triangle',
            severity: 2
        },
        class_iii: {
            label: 'Class III - Unlikely Harm',
            shortLabel: 'Class III',
            description: 'Not likely to cause adverse health consequences',
            color: 'info',
            icon: 'info-circle',
            severity: 3
        }
    };

    // Status constants
    static STATUSES = {
        initiated: {
            label: 'Initiated',
            color: 'ghost',
            icon: 'plus-circle',
            description: 'Recall request created'
        },
        pending_approval: {
            label: 'Pending Approval',
            color: 'warning',
            icon: 'clock',
            description: 'Awaiting GM approval'
        },
        approved: {
            label: 'Approved',
            color: 'info',
            icon: 'check',
            description: 'Approved, ready for notifications'
        },
        in_progress: {
            label: 'In Progress',
            color: 'primary',
            icon: 'sync',
            description: 'Returns being collected'
        },
        completed: {
            label: 'Completed',
            color: 'success',
            icon: 'check-circle',
            description: 'Recall finished'
        },
        cancelled: {
            label: 'Cancelled',
            color: 'ghost',
            icon: 'ban',
            description: 'Recall cancelled'
        }
    };

    /**
     * Get list of recalls with optional filters
     */
    static async getList(filters = {}) {
        const params = {};
        if (filters.status) params.status = filters.status;
        if (filters.recall_class) params.recall_class = filters.recall_class;
        if (filters.date_from) params.date_from = filters.date_from;
        if (filters.date_to) params.date_to = filters.date_to;
        if (filters.limit) params.limit = filters.limit;
        if (filters.offset) params.offset = filters.offset;

        return await api.get(this.API_PATH, { params });
    }

    /**
     * Get single recall with full details
     */
    static async get(id) {
        return await api.get(this.API_PATH, { params: { id } });
    }

    /**
     * Get recall statistics
     */
    static async getStats() {
        return await api.get(this.API_PATH, { params: { action: 'stats' } });
    }

    /**
     * Get active recalls for dashboard alerts
     */
    static async getActive() {
        return await api.get(this.API_PATH, { params: { action: 'active' } });
    }

    /**
     * Create new recall
     */
    static async create(data) {
        return await api.post(this.API_PATH, {
            batch_id: data.batch_id,
            recall_class: data.recall_class,
            reason: data.reason,
            evidence_notes: data.evidence_notes || null
        });
    }

    /**
     * Approve recall (GM only)
     */
    static async approve(id, approvalNotes = '') {
        return await api.put(this.API_PATH, {
            id,
            action: 'approve',
            approval_notes: approvalNotes
        });
    }

    /**
     * Reject recall (GM only)
     */
    static async reject(id, rejectionReason) {
        return await api.put(this.API_PATH, {
            id,
            action: 'reject',
            rejection_reason: rejectionReason
        });
    }

    /**
     * Log product return
     */
    static async logReturn(recallId, data) {
        return await api.put(this.API_PATH, {
            id: recallId,
            action: 'log_return',
            affected_location_id: data.affected_location_id,
            units_returned: data.units_returned,
            return_date: data.return_date || new Date().toISOString().split('T')[0],
            condition_status: data.condition_status || 'unknown',
            condition_notes: data.condition_notes || null
        });
    }

    /**
     * Mark notification as sent
     */
    static async markNotificationSent(recallId, locationId, method = 'phone') {
        return await api.put(this.API_PATH, {
            id: recallId,
            action: 'send_notification',
            affected_location_id: locationId,
            notification_method: method
        });
    }

    /**
     * Complete recall
     */
    static async complete(id, completionNotes = '') {
        return await api.put(this.API_PATH, {
            id,
            action: 'complete',
            completion_notes: completionNotes
        });
    }

    /**
     * Cancel recall
     */
    static async cancel(id) {
        return await api.delete(this.API_PATH, { params: { id } });
    }

    // ==================== Helper Methods ====================

    /**
     * Get class badge HTML
     */
    static getClassBadge(recallClass) {
        const cls = this.CLASSES[recallClass];
        if (!cls) return recallClass;
        return `<span class="badge badge-${cls.color} gap-1">
            <i class="fas fa-${cls.icon}"></i>
            ${cls.shortLabel}
        </span>`;
    }

    /**
     * Get status badge HTML
     */
    static getStatusBadge(status) {
        const s = this.STATUSES[status];
        if (!s) return status;
        return `<span class="badge badge-${s.color} gap-1">
            <i class="fas fa-${s.icon}"></i>
            ${s.label}
        </span>`;
    }

    /**
     * Get recovery rate badge HTML
     */
    static getRecoveryBadge(rate) {
        let color = 'error';
        if (rate >= 80) color = 'success';
        else if (rate >= 50) color = 'warning';

        return `<div class="flex items-center gap-2">
            <progress class="progress progress-${color} w-20" value="${rate}" max="100"></progress>
            <span class="text-sm font-semibold">${rate}%</span>
        </div>`;
    }

    /**
     * Format date
     */
    static formatDate(dateStr) {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    /**
     * Format datetime
     */
    static formatDateTime(dateStr) {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleString('en-PH', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    /**
     * Check if recall needs urgent attention
     */
    static isUrgent(recall) {
        return recall.recall_class === 'class_i' &&
            !['completed', 'cancelled'].includes(recall.status);
    }

    /**
     * Get action buttons based on recall status and user role
     */
    static getActionButtons(recall, userRole) {
        const buttons = [];
        const isGM = ['general_manager', 'admin'].includes(userRole);

        // View button always shown
        buttons.push(`<button class="btn btn-ghost btn-xs" onclick="viewRecall(${recall.id})" title="View Details">
            <i class="fas fa-eye"></i>
        </button>`);

        if (recall.status === 'pending_approval' && isGM) {
            buttons.push(`<button class="btn btn-success btn-xs" onclick="approveRecall(${recall.id})" title="Approve">
                <i class="fas fa-check"></i>
            </button>`);
            buttons.push(`<button class="btn btn-error btn-xs" onclick="rejectRecall(${recall.id})" title="Reject">
                <i class="fas fa-times"></i>
            </button>`);
        }

        if (['approved', 'in_progress'].includes(recall.status)) {
            buttons.push(`<button class="btn btn-info btn-xs" onclick="logReturn(${recall.id})" title="Log Return">
                <i class="fas fa-box"></i>
            </button>`);
        }

        if (['initiated', 'pending_approval'].includes(recall.status)) {
            buttons.push(`<button class="btn btn-ghost btn-xs text-error" onclick="cancelRecall(${recall.id})" title="Cancel">
                <i class="fas fa-ban"></i>
            </button>`);
        }

        return buttons.join('');
    }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RecallService;
}
