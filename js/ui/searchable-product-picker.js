(function (global) {
    'use strict';

    const instances = new WeakMap();
    let pickerSequence = 0;

    function normalizeSearchText(value) {
        return String(value ?? '')
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, ' ')
            .trim();
    }

    function readOptions(select) {
        return Array.from(select.options)
            .filter(option => String(option.value) !== '')
            .map(option => {
                const group = option.parentElement?.tagName === 'OPTGROUP'
                    ? option.parentElement.label
                    : '';
                const label = String(option.textContent || '').trim();
                return {
                    value: String(option.value),
                    label,
                    group,
                    disabled: Boolean(option.disabled || option.parentElement?.disabled),
                    searchText: normalizeSearchText(`${label} ${option.value} ${group}`)
                };
            });
    }

    function filterOptionRecords(records, query) {
        const terms = normalizeSearchText(query).split(/\s+/).filter(Boolean);
        if (!terms.length) return [...records];
        return records.filter(record => terms.every(term => record.searchText.includes(term)));
    }

    function enhance(select, options = {}) {
        if (!select || select.tagName !== 'SELECT') return null;
        if (instances.has(select)) {
            const existing = instances.get(select);
            existing.refresh();
            return existing;
        }

        const pickerId = `product-search-picker-${++pickerSequence}`;
        const maxResults = Math.max(10, Number(options.maxResults || select.dataset.searchMaxResults || 40));
        const placeholder = options.placeholder
            || select.dataset.searchPlaceholder
            || 'Search by product name, SKU, or size...';
        const noResultsText = options.noResultsText || 'No matching products';
        const originalTabIndex = select.getAttribute('tabindex');

        const wrapper = document.createElement('div');
        wrapper.className = 'product-search-picker';
        const inputWrap = document.createElement('div');
        inputWrap.className = 'product-search-picker__input-wrap';
        const icon = document.createElement('i');
        icon.className = 'fas fa-search product-search-picker__icon';
        icon.setAttribute('aria-hidden', 'true');
        const input = document.createElement('input');
        input.type = 'search';
        input.className = `input input-bordered w-full product-search-picker__input ${select.classList.contains('select-sm') ? 'input-sm' : ''}`;
        input.placeholder = placeholder;
        input.autocomplete = 'off';
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', `${pickerId}-listbox`);
        const chevron = document.createElement('i');
        chevron.className = 'fas fa-chevron-down product-search-picker__chevron';
        chevron.setAttribute('aria-hidden', 'true');
        const panel = document.createElement('div');
        panel.id = `${pickerId}-listbox`;
        panel.className = 'product-search-picker__panel';
        panel.setAttribute('role', 'listbox');

        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(inputWrap);
        inputWrap.append(icon, input, chevron);
        wrapper.appendChild(select);
        // A native <dialog> is rendered in the browser's top layer. A panel
        // appended to <body> cannot appear above that dialog, even with a very
        // large z-index, which made searches inside modals look unresponsive.
        // Keep the floating results in the same top-layer dialog when present.
        const panelHost = select.closest('dialog') || document.body;
        panelHost.appendChild(panel);
        select.classList.add('product-search-picker__native');
        select.tabIndex = -1;

        let records = [];
        let visibleRecords = [];
        let activeIndex = -1;
        let open = false;

        function selectedRecord() {
            return records.find(record => record.value === String(select.value)) || null;
        }

        function positionPanel() {
            if (!open) return;
            const rect = input.getBoundingClientRect();
            const viewportGap = 8;
            panel.style.left = `${Math.max(viewportGap, rect.left)}px`;
            panel.style.width = `${Math.max(280, rect.width)}px`;
            const desiredHeight = Math.min(panel.scrollHeight || 320, window.innerHeight * 0.45);
            const roomBelow = window.innerHeight - rect.bottom - viewportGap;
            const showAbove = roomBelow < desiredHeight && rect.top > roomBelow;
            panel.style.top = showAbove
                ? `${Math.max(viewportGap, rect.top - desiredHeight - 4)}px`
                : `${rect.bottom + 4}px`;
        }

        function moveActive(direction) {
            const enabledIndexes = visibleRecords
                .map((record, index) => record.disabled ? -1 : index)
                .filter(index => index >= 0);
            if (!enabledIndexes.length) {
                activeIndex = -1;
                input.removeAttribute('aria-activedescendant');
                return;
            }
            const currentPosition = enabledIndexes.indexOf(activeIndex);
            const requestedPosition = currentPosition < 0
                ? (direction > 0 ? 0 : enabledIndexes.length - 1)
                : currentPosition + direction;
            activeIndex = enabledIndexes[(requestedPosition + enabledIndexes.length) % enabledIndexes.length];
            panel.querySelectorAll('.product-search-picker__option').forEach(button => {
                button.classList.toggle('is-active', Number(button.dataset.resultIndex) === activeIndex);
            });
            const activeButton = panel.querySelector(`[data-result-index="${activeIndex}"]`);
            if (activeButton) {
                input.setAttribute('aria-activedescendant', activeButton.id);
                activeButton.scrollIntoView({ block: 'nearest' });
            }
        }

        function choose(record) {
            if (!record || record.disabled) return;
            select.value = record.value;
            input.value = record.label;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            closePanel();
        }

        function render(query = '') {
            panel.replaceChildren();
            visibleRecords = filterOptionRecords(records, query).slice(0, maxResults);
            activeIndex = -1;
            input.removeAttribute('aria-activedescendant');

            if (!visibleRecords.length) {
                const empty = document.createElement('span');
                empty.className = 'product-search-picker__empty';
                empty.textContent = `${noResultsText}. Try a product name, SKU code, or size.`;
                panel.appendChild(empty);
                positionPanel();
                return;
            }

            let lastGroup = null;
            visibleRecords.forEach((record, index) => {
                if (record.group && record.group !== lastGroup) {
                    const group = document.createElement('span');
                    group.className = 'product-search-picker__group';
                    group.textContent = record.group;
                    panel.appendChild(group);
                    lastGroup = record.group;
                }
                const button = document.createElement('button');
                button.type = 'button';
                button.id = `${pickerId}-option-${index}`;
                button.className = 'product-search-picker__option';
                button.dataset.resultIndex = String(index);
                button.textContent = record.label;
                button.disabled = record.disabled;
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', record.value === String(select.value) ? 'true' : 'false');
                button.classList.toggle('is-selected', record.value === String(select.value));
                button.addEventListener('mousedown', event => event.preventDefault());
                button.addEventListener('click', () => choose(record));
                panel.appendChild(button);
            });

            const totalMatches = filterOptionRecords(records, query).length;
            if (totalMatches > visibleRecords.length) {
                const more = document.createElement('span');
                more.className = 'product-search-picker__more';
                more.textContent = `${totalMatches - visibleRecords.length} more matches — type more to narrow the list.`;
                panel.appendChild(more);
            }
            positionPanel();
        }

        function openPanel(query = '') {
            if (select.disabled) return;
            open = true;
            panel.classList.add('is-open');
            input.setAttribute('aria-expanded', 'true');
            render(query);
            window.addEventListener('resize', positionPanel);
            window.addEventListener('scroll', positionPanel, true);
        }

        function closePanel() {
            open = false;
            panel.classList.remove('is-open');
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            window.removeEventListener('resize', positionPanel);
            window.removeEventListener('scroll', positionPanel, true);
        }

        function syncFromSelect() {
            const selected = selectedRecord();
            input.value = selected?.label || '';
            input.disabled = select.disabled;
            input.setAttribute('aria-label', select.getAttribute('aria-label') || 'Search and select product');
        }

        function refresh() {
            records = readOptions(select);
            syncFromSelect();
            if (open) render(input.value);
        }

        input.addEventListener('focus', () => {
            openPanel('');
            input.select();
        });
        input.addEventListener('input', () => openPanel(input.value));
        input.addEventListener('blur', () => {
            window.setTimeout(() => {
                closePanel();
                syncFromSelect();
            }, 100);
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (!open) openPanel(input.value);
                moveActive(1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                if (!open) openPanel(input.value);
                moveActive(-1);
            } else if (event.key === 'Enter' && open && activeIndex >= 0) {
                event.preventDefault();
                choose(visibleRecords[activeIndex]);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                closePanel();
                syncFromSelect();
            }
        });
        select.addEventListener('change', syncFromSelect);

        const observer = new MutationObserver(refresh);
        observer.observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled'] });

        function destroy() {
            observer.disconnect();
            closePanel();
            panel.remove();
            select.classList.remove('product-search-picker__native');
            if (originalTabIndex === null) select.removeAttribute('tabindex');
            else select.setAttribute('tabindex', originalTabIndex);
            if (wrapper.isConnected) {
                wrapper.parentNode.insertBefore(select, wrapper);
                wrapper.remove();
            }
            instances.delete(select);
        }

        const instance = { refresh, close: closePanel, destroy, input, select, panel };
        instances.set(select, instance);
        refresh();
        return instance;
    }

    function enhanceAll(root = document, options = {}) {
        return Array.from(root.querySelectorAll('select[data-searchable-product]'))
            .map(select => enhance(select, options));
    }

    function destroyAll(root = document) {
        root.querySelectorAll('select[data-searchable-product]').forEach(select => {
            instances.get(select)?.destroy();
        });
    }

    const api = { enhance, enhanceAll, destroyAll, normalizeSearchText, filterOptionRecords };
    global.SearchableProductPicker = api;
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
