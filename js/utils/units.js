/**
 * Highland Fresh — shared multi-unit pack helpers
 *
 * Single source of display logic for base units + pack (box/crate/case).
 * Avoids misleading "1 Box = 1 pcs" when pack size is not configured.
 *
 * Expected product / inventory fields (any subset works):
 *   pieces_per_box | base_per_pack
 *   base_unit | box_unit (pack unit)
 *   boxes_available / quantity_boxes
 *   pieces_available / quantity_pieces
 *   quantity_available / remaining_quantity / total_pieces
 */
(function (global) {
    'use strict';

    function singularize(label) {
        if (!label) return label;
        const s = String(label);
        if (s.length > 1 && s.toLowerCase().endsWith('s') && !s.toLowerCase().endsWith('ss')) {
            return s.slice(0, -1);
        }
        return s;
    }

    function pluralize(label, count) {
        const base = singularize(label || 'unit');
        if (count === 1) return base;
        const lower = base.toLowerCase();
        if (lower.endsWith('s') || lower.endsWith('x') || lower.endsWith('ch') || lower.endsWith('sh')) {
            return base + 'es';
        }
        return base + 's';
    }

    function capitalize(s) {
        if (!s) return '';
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    function packSize(item) {
        const n = parseInt(item?.pieces_per_box ?? item?.base_per_pack ?? item?.piecesPerBox, 10);
        return n > 0 ? n : 1;
    }

    function baseUnit(item) {
        return String(item?.base_unit || item?.baseUnit || 'piece').toLowerCase();
    }

    function packUnit(item) {
        return String(item?.box_unit || item?.pack_unit || item?.boxUnit || 'box').toLowerCase();
    }

    function hasPackConfig(item) {
        return packSize(item) > 1;
    }

    /**
     * Pack formula text, or null when pack is not configured.
     * e.g. "1 crate = 24 bottles"
     */
    function formatPackConfig(item) {
        const ppb = packSize(item);
        if (ppb <= 1) {
            return null;
        }
        const pack = singularize(packUnit(item));
        const base = pluralize(baseUnit(item), ppb);
        return `1 ${pack} = ${ppb} ${base}`;
    }

    /**
     * Human message when pack is not configured.
     */
    function formatNoPackMessage(item) {
        const base = pluralize(baseUnit(item), 2);
        return `Stored as individual ${base} (no pack size configured)`;
    }

    /**
     * Compact pack line for info badges.
     */
    function formatPackLine(item) {
        const cfg = formatPackConfig(item);
        return cfg || formatNoPackMessage(item);
    }

    function readBoxes(item) {
        const v = item?.boxes_available ?? item?.quantity_boxes ?? item?.boxes ?? 0;
        return parseInt(v, 10) || 0;
    }

    function readLoosePieces(item) {
        const v = item?.pieces_available ?? item?.quantity_pieces ?? item?.pieces ?? 0;
        return parseInt(v, 10) || 0;
    }

    function readBookedBase(item) {
        const candidates = [
            item?.quantity_available,
            item?.remaining_quantity,
            item?.total_pieces,
            item?.quantity,
            item?.actual_yield
        ];
        for (const c of candidates) {
            if (c !== undefined && c !== null && c !== '') {
                const n = parseInt(c, 10);
                if (!Number.isNaN(n)) return n;
            }
        }
        return null;
    }

    /**
     * Total base units from multi-unit columns (may differ from booked qty).
     */
    function totalBaseFromMultiUnit(item) {
        const ppb = packSize(item);
        return (readBoxes(item) * ppb) + readLoosePieces(item);
    }

    /**
     * Best-effort total base quantity for display.
     * Prefer multi-unit when pack columns have data; else booked base.
     */
    function totalBase(item) {
        const boxes = readBoxes(item);
        const loose = readLoosePieces(item);
        const multi = totalBaseFromMultiUnit(item);
        if (boxes !== 0 || loose !== 0) {
            return multi;
        }
        const booked = readBookedBase(item);
        return booked !== null ? booked : multi;
    }

    /**
     * Split total base into packs + loose using pack size.
     */
    function splitToPack(total, item) {
        const ppb = packSize(item);
        const t = Math.max(0, parseInt(total, 10) || 0);
        if (ppb <= 1) {
            return { packs: 0, loose: t, total: t };
        }
        return {
            packs: Math.floor(t / ppb),
            loose: t % ppb,
            total: t
        };
    }

    /**
     * Primary quantity string, e.g. "2 crates + 4 bottles (28 bottles)"
     * or "50 bottles" when no pack config.
     */
    function formatQuantity(item, options) {
        const opts = options || {};
        const ppb = packSize(item);
        const base = baseUnit(item);
        const pack = packUnit(item);

        let packs = readBoxes(item);
        let loose = readLoosePieces(item);
        let total = totalBase(item);

        // If only total is known (e.g. pending put-away as pieces only), split for display
        if (opts.preferSplit && (packs === 0 && loose === 0) && total > 0 && ppb > 1) {
            const s = splitToPack(total, item);
            packs = s.packs;
            loose = s.loose;
        }

        // If multi-unit empty but booked total exists
        if (packs === 0 && loose === 0 && total > 0 && ppb > 1 && opts.preferSplit !== false) {
            const s = splitToPack(total, item);
            packs = s.packs;
            loose = s.loose;
        }

        const baseLabel = pluralize(base, total === 1 ? 1 : 2);
        const packLabel = pluralize(pack, packs === 1 ? 1 : 2);
        const looseLabel = pluralize(base, loose === 1 ? 1 : 2);

        if (ppb <= 1) {
            return `${total.toLocaleString()} ${baseLabel}`;
        }

        if (packs > 0 && loose > 0) {
            return `${packs.toLocaleString()} ${packLabel} + ${loose.toLocaleString()} ${looseLabel} (${total.toLocaleString()} ${baseLabel})`;
        }
        if (packs > 0) {
            return `${packs.toLocaleString()} ${packLabel} (${total.toLocaleString()} ${baseLabel})`;
        }
        return `${total.toLocaleString()} ${baseLabel}`;
    }

    /**
     * Short on-hand: "50 bottles · 2 crates + 2 loose"
     */
    function formatOnHand(item) {
        const ppb = packSize(item);
        const total = totalBase(item);
        const base = pluralize(baseUnit(item), total === 1 ? 1 : 2);
        if (ppb <= 1) {
            return `${total.toLocaleString()} ${base}`;
        }
        let packs = readBoxes(item);
        let loose = readLoosePieces(item);
        if (packs === 0 && loose === 0 && total > 0) {
            const s = splitToPack(total, item);
            packs = s.packs;
            loose = s.loose;
        }
        const pack = pluralize(packUnit(item), packs === 1 ? 1 : 2);
        if (packs > 0 && loose > 0) {
            return `${total.toLocaleString()} ${base} · ${packs} ${pack} + ${loose} loose`;
        }
        if (packs > 0) {
            return `${total.toLocaleString()} ${base} · ${packs} ${pack}`;
        }
        return `${total.toLocaleString()} ${base}`;
    }

    /**
     * Integrity check: multi-unit vs booked, negatives.
     * @returns {{ ok: boolean, issues: string[], severity: 'ok'|'warn'|'error' }}
     */
    function checkIntegrity(item) {
        const issues = [];
        const boxes = readBoxes(item);
        const loose = readLoosePieces(item);
        const multi = totalBaseFromMultiUnit(item);
        const booked = readBookedBase(item);
        const rawBoxes = item?.boxes_available ?? item?.quantity_boxes;
        const rawLoose = item?.pieces_available ?? item?.quantity_pieces;
        const rawAvail = item?.quantity_available;
        const rawRem = item?.remaining_quantity;

        const asNum = (v) => (v === undefined || v === null || v === '' ? null : Number(v));

        [rawBoxes, rawLoose, rawAvail, rawRem].forEach((v, i) => {
            const n = asNum(v);
            if (n !== null && n < 0) {
                const names = ['boxes_available', 'pieces_available', 'quantity_available', 'remaining_quantity'];
                issues.push(`Negative ${names[i]} (${n})`);
            }
        });

        // Also check raw item if API exposes integrity flags
        if (item?.has_negative_qty || item?.integrity_error) {
            issues.push(item.integrity_message || 'Quantity columns out of sync');
        }

        if (booked !== null && (boxes !== 0 || loose !== 0 || multi !== 0)) {
            if (multi !== booked && multi > 0 && booked >= 0) {
                // Allow when multi was never populated (both zero multi already handled)
                if (!(boxes === 0 && loose === 0)) {
                    issues.push(
                        `Booked ${booked.toLocaleString()} base units vs multi-unit ${multi.toLocaleString()}`
                    );
                }
            }
        }

        // Special case: available 0 but boxes still positive
        if (asNum(rawAvail) === 0 && boxes > 0) {
            issues.push(`quantity_available is 0 but ${boxes} pack(s) still shown`);
        }

        const severity = issues.length === 0 ? 'ok' : (issues.some(i => i.startsWith('Negative')) ? 'error' : 'warn');
        return { ok: issues.length === 0, issues, severity };
    }

    /**
     * HTML badge for integrity (safe for innerHTML).
     */
    function integrityBadgeHtml(item) {
        const r = checkIntegrity(item);
        if (r.ok) return '';
        const cls = r.severity === 'error' ? 'badge-error' : 'badge-warning';
        const title = r.issues.join('; ').replace(/"/g, '&quot;');
        return `<span class="badge ${cls} badge-sm gap-1" title="${title}"><i class="fas fa-exclamation-triangle"></i> Qty issue</span>`;
    }

    /**
     * Labels for form fields (Boxes / Pieces → Crates / Bottles).
     */
    function unitLabels(item) {
        const ppb = packSize(item);
        return {
            pack: capitalize(pluralize(packUnit(item), 2)),
            packSingular: capitalize(singularize(packUnit(item))),
            base: capitalize(pluralize(baseUnit(item), 2)),
            baseSingular: capitalize(singularize(baseUnit(item))),
            hasPack: ppb > 1,
            piecesPerPack: ppb
        };
    }

    const PackUnits = {
        singularize,
        pluralize,
        capitalize,
        packSize,
        baseUnit,
        packUnit,
        hasPackConfig,
        formatPackConfig,
        formatNoPackMessage,
        formatPackLine,
        formatQuantity,
        formatOnHand,
        totalBase,
        totalBaseFromMultiUnit,
        splitToPack,
        checkIntegrity,
        integrityBadgeHtml,
        unitLabels,
        readBoxes,
        readLoosePieces
    };

    global.PackUnits = PackUnits;
})(typeof window !== 'undefined' ? window : globalThis);
