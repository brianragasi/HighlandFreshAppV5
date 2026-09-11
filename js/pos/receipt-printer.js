/**
 * Dedicated thermal receipt renderer for POS sales.
 * Printing an isolated iframe prevents the application shell and modal from
 * leaking into the customer's receipt.
 */
(function (global) {
    'use strict';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function money(value) {
        const amount = Number(value);
        return `₱${Number.isFinite(amount) ? amount.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) : '0.00'}`;
    }

    function paymentLabel(value) {
        const labels = {
            cash: 'Cash',
            gcash: 'GCash',
            bank_transfer: 'Bank Transfer',
            bank: 'Bank Transfer',
            check: 'Check'
        };
        return labels[String(value || '').toLowerCase()] || String(value || 'Unknown');
    }

    function buildSalesReceiptHtml(receipt) {
        const data = receipt || {};
        const header = data.header || {};
        const transaction = data.transaction || {};
        const payment = data.payment || {};
        const footer = data.footer || {};
        const items = Array.isArray(data.items) ? data.items : [];
        const discount = Number(payment.discount ?? transaction.discount_amount ?? 0) || 0;
        const total = Number(payment.total ?? transaction.total_amount ?? 0) || 0;
        const taxAmount = Math.max(0, Number(payment.tax_amount ?? payment.tax ?? transaction.tax_amount ?? 0) || 0);
        const vatableSales = Math.max(0, Number(payment.vatable_sales ?? transaction.vatable_sales ?? (total - taxAmount)) || 0);
        const vatInclusive = Boolean(payment.vat_inclusive ?? transaction.vat_inclusive ?? taxAmount > 0);
        const tendered = Number(payment.amount_tendered ?? payment.amount_paid ?? transaction.amount_paid ?? total) || 0;
        const change = Math.max(0, Number(payment.change ?? transaction.change_amount ?? (tendered - total)) || 0);
        const method = payment.method || transaction.payment_method || 'cash';
        const isCash = String(method).toLowerCase() === 'cash';
        const itemRows = items.map(item => {
            const baseQuantity = Number(item.quantity) || 0;
            const savedSaleQuantity = Number(item.sale_quantity);
            const quantity = Number.isFinite(savedSaleQuantity) && savedSaleQuantity > 0
                ? savedSaleQuantity
                : baseQuantity;
            const saleUnit = String(item.sale_unit || 'piece').toLowerCase() === 'box' ? 'box' : 'piece';
            const piecesPerBox = Math.max(1, Number(item.pieces_per_box_snapshot || item.pieces_per_box) || 1);
            const unitPrice = Number(item.unit_price) || 0;
            const lineTotal = Number(item.line_total ?? item.total_price ?? (quantity * unitPrice)) || 0;
            const variant = String(item.variant || '').trim();
            const quantityLabel = `${quantity} ${quantity === 1 ? saleUnit : (saleUnit === 'box' ? 'boxes' : 'pieces')}`;
            const boxDetail = saleUnit === 'box'
                ? `<div class="muted">${piecesPerBox} units/box · ${baseQuantity || quantity * piecesPerBox} units deducted</div>`
                : '';
            return `<div class="item">
                <div class="item-name">${escapeHtml(item.product_name || item.name || 'Product')}${variant ? ` <span class="muted">(${escapeHtml(variant)})</span>` : ''}</div>
                <div class="item-calc"><span>${escapeHtml(quantityLabel)} x ${money(unitPrice)}</span><strong>${money(lineTotal)}</strong></div>
                ${boxDetail}
            </div>`;
        }).join('');

        const receiptDate = footer.date || transaction.created_at || new Date().toISOString();
        const parsedDate = new Date(receiptDate);
        const dateLabel = Number.isNaN(parsedDate.getTime())
            ? String(receiptDate)
            : parsedDate.toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });

        return `<!doctype html>
<html><head><meta charset="utf-8"><title>${escapeHtml(header.si_number || transaction.transaction_code || 'Sales Receipt')}</title>
<style>
    @page { size: 80mm auto; margin: 3mm; }
    * { box-sizing: border-box; }
    html, body { width: 74mm; margin: 0; padding: 0; background: #fff; color: #000; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; line-height: 1.35; }
    .center { text-align: center; }
    .company { font-size: 16px; font-weight: 700; }
    .document { margin-top: 4px; font-size: 12px; font-weight: 700; letter-spacing: .08em; }
    .rule { border-top: 1px dashed #000; margin: 8px 0; }
    .meta-row, .total-row, .item-calc { display: flex; justify-content: space-between; gap: 8px; }
    .meta-row span:first-child, .muted { color: #444; }
    .item { margin: 7px 0; break-inside: avoid; }
    .item-name { font-weight: 700; }
    .item-calc { margin-top: 2px; }
    .total-row { margin: 3px 0; }
    .grand { margin-top: 5px; font-size: 14px; font-weight: 700; }
    .change { font-size: 14px; font-weight: 700; }
    .footer { margin-top: 10px; }
</style></head><body>
    <header class="center">
        <div class="company">${escapeHtml(header.company_name || 'Highland Fresh')}</div>
        <div>${escapeHtml(header.address || '')}</div>
        <div class="document">${escapeHtml(header.document_type || 'SALES INVOICE')}</div>
        <div>${escapeHtml(header.si_number || transaction.transaction_code || '')}</div>
    </header>
    <div class="rule"></div>
    <div class="meta-row"><span>Date</span><strong>${escapeHtml(dateLabel)}</strong></div>
    <div class="meta-row"><span>Cashier</span><strong>${escapeHtml(footer.cashier || transaction.cashier_name || '')}</strong></div>
    <div class="meta-row"><span>Customer</span><strong>${escapeHtml(transaction.customer_name || 'Walk-in Customer')}</strong></div>
    <div class="rule"></div>
    ${itemRows || '<div class="center muted">No line items</div>'}
    <div class="rule"></div>
    <div class="total-row"><span>Subtotal (VAT inclusive)</span><strong>${money(payment.subtotal ?? transaction.subtotal_amount ?? total)}</strong></div>
    ${discount > 0 ? `<div class="total-row"><span>Discount</span><strong>- ${money(discount)}</strong></div>` : ''}
    ${vatInclusive ? `<div class="total-row"><span>VATable Sales</span><strong>${money(vatableSales)}</strong></div>
    <div class="total-row"><span>VAT (12%, included)</span><strong>${money(taxAmount)}</strong></div>` : ''}
    <div class="total-row grand"><span>TOTAL</span><span>${money(total)}</span></div>
    <div class="total-row"><span>Payment</span><strong>${escapeHtml(paymentLabel(method))}</strong></div>
    ${isCash ? `<div class="total-row"><span>Cash received</span><strong>${money(tendered)}</strong></div>
    <div class="total-row change"><span>CHANGE</span><span>${money(change)}</span></div>` : ''}
    <div class="rule"></div>
    <footer class="center footer">${escapeHtml(footer.message || 'Thank you for your purchase!')}</footer>
</body></html>`;
    }

    function printSalesReceipt(receipt) {
        if (typeof document === 'undefined') return false;

        const frame = document.createElement('iframe');
        frame.setAttribute('aria-hidden', 'true');
        frame.style.position = 'fixed';
        frame.style.width = '1px';
        frame.style.height = '1px';
        frame.style.right = '0';
        frame.style.bottom = '0';
        frame.style.opacity = '0';
        frame.style.pointerEvents = 'none';

        let removed = false;
        const cleanup = () => {
            if (removed) return;
            removed = true;
            frame.remove();
        };

        frame.onload = () => {
            const printWindow = frame.contentWindow;
            if (!printWindow) { cleanup(); return; }
            printWindow.addEventListener('afterprint', cleanup, { once: true });
            setTimeout(() => {
                printWindow.focus();
                printWindow.print();
            }, 50);
            setTimeout(cleanup, 60000);
        };
        frame.srcdoc = buildSalesReceiptHtml(receipt);
        document.body.appendChild(frame);
        return true;
    }

    const POSReceiptPrinter = { buildSalesReceiptHtml, printSalesReceipt };
    global.POSReceiptPrinter = POSReceiptPrinter;
    if (typeof module !== 'undefined' && module.exports) module.exports = POSReceiptPrinter;
})(typeof globalThis !== 'undefined' ? globalThis : window);
