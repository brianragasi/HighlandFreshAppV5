/* CSV export for the visible Accounts Receivable Aging worklist. */
(function (root) {
    'use strict';

    function textCell(value) {
        let text = String(value ?? '');
        // Spreadsheet applications can execute formulas in imported text cells.
        if (/^[\s\u0000-\u001f]*[=+@-]/.test(text)) text = "'" + text;
        return '"' + text.replace(/"/g, '""') + '"';
    }

    function amount(value) {
        return (Math.round((Number(value) || 0) * 100) / 100).toFixed(2);
    }

    function buildCsv({ customers = [], asOf, filters = {}, typeLabels = {}, bucketLabels = {} }) {
        const rows = [
            ['Highland Fresh', 'Accounts Receivable Aging'],
            ['As of', asOf],
            ['Search', filters.search || 'All customers'],
            ['Type', filters.type ? (typeLabels[filters.type] || filters.type) : 'All types'],
            ['Focus', bucketLabels[filters.aging || ''] || 'All unpaid balances'],
            [],
            ['Customer Code', 'Customer', 'Type', 'Not Yet Due (PHP)', '1-30 Days Late (PHP)',
                '31-60 Days Late (PHP)', '61-90 Days Late (PHP)', 'Over 90 Days Late (PHP)',
                'Total Owed (PHP)', 'Credit Limit (PHP)', 'Over Limit', 'Over By (PHP)']
        ];
        const totals = [0, 0, 0, 0, 0];

        for (const customer of customers) {
            const buckets = ['current', 'days_1_30', 'days_31_60', 'days_61_90', 'over_90']
                .map(key => Number(customer[key]) || 0);
            buckets.forEach((value, index) => { totals[index] += value; });
            const total = buckets.reduce((sum, value) => sum + value, 0);
            const limit = Number(customer.credit_limit) || 0;
            const overBy = limit > 0 ? Math.max(0, total - limit) : 0;
            rows.push([
                customer.customer_code || `CUS${String(customer.id).padStart(5, '0')}`,
                customer.customer_name || customer.name || '',
                typeLabels[customer.customer_type] || customer.customer_type || 'Customer',
                ...buckets.map(amount), amount(total), limit > 0 ? amount(limit) : '',
                overBy > 0 ? 'Yes' : 'No', overBy > 0 ? amount(overBy) : ''
            ]);
        }

        rows.push(['TOTAL', '', '', ...totals.map(amount), amount(totals.reduce((sum, value) => sum + value, 0)), '', '', '']);
        const numericColumns = new Set([3, 4, 5, 6, 7, 8, 9, 11]);
        return rows.map((row, rowIndex) => row.map((value, columnIndex) =>
            rowIndex > 6 && numericColumns.has(columnIndex) && value !== ''
                ? value : textCell(value)
        ).join(',')).join('\r\n') + '\r\n';
    }

    root.AgingReportExport = { buildCsv };
    if (typeof module !== 'undefined' && module.exports) module.exports = { buildCsv };
})(typeof window !== 'undefined' ? window : globalThis);
