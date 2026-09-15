/* Same-origin, authenticated downloads. No print-window or external PDF service. */
(function (root) {
    'use strict';
    async function download({ format, startDate, endDate }) {
        if (!['pdf', 'csv'].includes(format)) throw new Error('Choose PDF or CSV.');
        const params = new URLSearchParams({ action: 'export_' + format, start_date: startDate, end_date: endDate, limit: '10' });
        const response = await fetch(ApiConfig.baseUrl + '/sales/reports.php?' + params, {
            credentials: 'same-origin', headers: { ...ApiConfig.getHeaders(), Accept: format === 'pdf' ? 'application/pdf' : 'text/csv' },
        });
        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw new Error(body.message || 'Could not export the sales report. Try again.');
        }
        const expected = format === 'pdf' ? 'application/pdf' : 'text/csv';
        if (!(response.headers.get('content-type') || '').startsWith(expected)) throw new Error('The server did not return a report file. Reload and try again.');
        const blob = await response.blob();
        if (format === 'pdf' && !(await blob.slice(0, 5).text()).startsWith('%PDF-')) throw new Error('The PDF was incomplete. Try exporting again.');
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `Highland-Fresh-Sales-${startDate}-to-${endDate}.${format}`;
        document.body.append(anchor);
        anchor.click();
        anchor.remove();
        setTimeout(() => URL.revokeObjectURL(url), 10000);
    }
    root.SalesReportExport = { download };
    if (typeof module !== 'undefined' && module.exports) module.exports = { download };
})(typeof window !== 'undefined' ? window : globalThis);
