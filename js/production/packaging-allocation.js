(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }
    root.PackagingAllocation = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    function positiveNumber(value) {
        const number = Number(value);
        return Number.isFinite(number) && number > 0 ? number : 0;
    }

    /**
     * Create a deterministic plan without asking Production to calculate counts.
     * One selected SKU uses all available volume. Multiple selected SKUs receive
     * an equal share of the liquid volume, then any usable remainder is assigned
     * one package at a time to the best-fitting selected size.
     */
    function distributeEvenlyByVolume(basisVolumeMl, selectedSkus) {
        const basisMl = positiveNumber(basisVolumeMl);
        const skus = (Array.isArray(selectedSkus) ? selectedSkus : [])
            .map((sku, index) => ({
                key: String(sku.key ?? sku.product_id ?? index),
                size_ml: positiveNumber(sku.size_ml ?? sku.packaging_size_ml),
                index,
            }))
            .filter(sku => sku.size_ml > 0);

        if (basisMl <= 0 || skus.length === 0) {
            return { allocations: [], used_ml: 0, remainder_ml: basisMl };
        }

        const targetVolumePerSku = basisMl / skus.length;
        const allocations = skus.map(sku => ({
            ...sku,
            quantity: Math.floor(targetVolumePerSku / sku.size_ml),
        }));

        let usedMl = allocations.reduce(
            (total, item) => total + item.quantity * item.size_ml,
            0
        );
        let remainderMl = Math.max(0, basisMl - usedMl);

        // At this point the remainder is bounded by the sum of selected sizes,
        // so this loop is small. Prefer the closest-fitting package to minimize
        // unallocated milk while keeping the plan within the available volume.
        while (true) {
            const fitting = allocations
                .filter(item => item.size_ml <= remainderMl + 0.000001)
                .sort((a, b) => b.size_ml - a.size_ml || a.index - b.index)[0];
            if (!fitting) break;
            fitting.quantity += 1;
            usedMl += fitting.size_ml;
            remainderMl = Math.max(0, basisMl - usedMl);
        }

        allocations.sort((a, b) => a.index - b.index);
        return {
            allocations,
            used_ml: Math.round(usedMl * 1000) / 1000,
            remainder_ml: Math.round(remainderMl * 1000) / 1000,
        };
    }

    function summarize(basisVolumeMl, lines) {
        const basisMl = positiveNumber(basisVolumeMl);
        const usedMl = (Array.isArray(lines) ? lines : []).reduce((total, line) => {
            const sizeMl = positiveNumber(line.size_ml ?? line.packaging_size_ml);
            const quantity = Math.max(0, Number(line.quantity) || 0);
            return total + sizeMl * quantity;
        }, 0);
        const differenceMl = basisMl - usedMl;
        return {
            basis_ml: basisMl,
            used_ml: Math.round(usedMl * 1000) / 1000,
            remaining_ml: Math.round(Math.max(0, differenceMl) * 1000) / 1000,
            over_ml: Math.round(Math.max(0, -differenceMl) * 1000) / 1000,
        };
    }

    return { distributeEvenlyByVolume, summarize };
}));
