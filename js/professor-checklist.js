(function () {
    'use strict';
    const { groups, items } = window.ProfessorChecklistData;
    const key = 'hf_professor_checklist_v1';
    const byId = id => document.getElementById(id);
    const labels = { unchecked: 'Not checked', working: 'Observed working', followup: 'Needs follow-up' };
    let records = {};
    try {
        const saved = JSON.parse(localStorage.getItem(key) || '{}');
        if (saved && typeof saved === 'object' && !Array.isArray(saved)) records = saved;
    } catch (_) { /* A fresh checklist remains usable when storage is unavailable. */ }
    let groupId = 'login';
    let run = null;
    let running = false;

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }
    function record(id) {
        const saved = records[id];
        return saved && typeof saved === 'object' ? saved : {};
    }
    function status(id) {
        const value = record(id).status;
        return Object.hasOwn(labels, value) ? value : 'unchecked';
    }
    function dateText(value) {
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '' : date.toLocaleString();
    }
    function save(id, changes) {
        records[id] = { ...record(id), ...changes, at: new Date().toISOString() };
        try { localStorage.setItem(key, JSON.stringify(records)); } catch (_) {
            byId('groupHelp').textContent = 'Browser storage is unavailable. Download your notes before closing this page.';
        }
    }
    function autoStatus(id) {
        if (running) return { text: 'Running…', className: '' };
        const result = run && run.results && run.results[id];
        if (!result || !['PASS', 'FAIL'].includes(result.status)) return { text: 'Not run', className: '' };
        return { text: result.status === 'PASS' ? 'Passed this check' : 'Failed this check', className: result.status.toLowerCase() };
    }
    function updateProgress() {
        const screenItems = items.filter(item => item.id.startsWith('ST-'));
        const count = screenItems.filter(item => status(item.id) === 'working').length;
        byId('progressCount').textContent = `${count} / ${screenItems.length}`;
        byId('progressBar').value = count;
        byId('progressBar').max = screenItems.length;
        renderNav();
    }
    function renderNav() {
        const nav = byId('groupNav');
        nav.replaceChildren();
        groups.forEach(group => {
            const button = el('button', `nav-button${groupId === group.id && !byId('checkSearch').value.trim() ? ' active' : ''}`, group.title);
            button.type = 'button';
            button.dataset.group = group.id;
            if (groupId === group.id) button.setAttribute('aria-current', 'page');
            const groupItems = items.filter(item => item.group === group.id);
            const marked = groupItems.filter(item => !item.kind && status(item.id) === 'working').length;
            const caption = group.id === 'automatic' ? '7 named checks · run on demand' : group.id === 'uat' ? 'Real participants · original forms' : `${marked} / ${groupItems.length} observed working`;
            button.append(el('small', '', caption));
            button.addEventListener('click', () => {
                groupId = group.id;
                byId('checkSearch').value = '';
                render();
            });
            nav.append(button);
        });
    }
    function link(label, path, className) {
        const a = el('a', className, label + ' ↗');
        a.href = path;
        a.target = '_blank';
        a.rel = 'noopener';
        return a;
    }
    function paragraph(parent, className, lead, text) {
        const p = el('p', className);
        if (lead) p.append(el('strong', '', lead + ' '));
        p.append(document.createTextNode(text));
        parent.append(p);
    }
    function renderCard(item, open) {
        const card = el('details', 'test-card');
        card.dataset.check = item.id;
        card.open = open;
        const summary = el('summary');
        summary.append(el('span', 'test-id', item.id), el('span', 'test-title', item.title));
        const state = item.kind === 'auto' ? autoStatus(item.id) : item.kind === 'uat' ? { text: 'Participant form', className: '' } : { text: labels[status(item.id)], className: status(item.id) };
        const badge = el('span', 'status ' + state.className, state.text);
        summary.append(badge);
        card.append(summary);
        const body = el('div', 'test-body');
        if (item.page) {
            const line = el('div', 'screen-line');
            line.append(el('span', 'role-chip', 'Account: ' + item.role), el('strong', '', item.screen));
            body.append(line);
            const links = el('div', 'link-row');
            links.append(link('Open screen', item.page, 'primary'));
            if (item.extraPage) links.append(link(item.extraLabel, item.extraPage, 'secondary'));
            body.append(links);
        }
        if (item.prep) paragraph(body, 'prep', 'Have ready:', item.prep);
        if (item.steps) {
            const list = el('ol');
            item.steps.forEach(step => list.append(el('li', '', step)));
            body.append(list);
        }
        paragraph(body, 'expected', 'What to show:', item.expected);
        if (item.note) paragraph(body, 'important', 'Important:', item.note);
        if (item.criteria) {
            const list = el('ul', 'criteria');
            item.criteria.forEach(criterion => list.append(el('li', '', criterion)));
            body.append(list);
        }
        if (!item.kind || item.kind === 'uat') {
            const observation = el('div', 'observation');
            if (!item.kind) {
                observation.append(el('strong', '', 'After you actually check it:'));
                const buttons = el('div', 'observation-buttons');
                ['working', 'followup', 'unchecked'].forEach(value => {
                    const button = el('button', status(item.id) === value ? 'selected' : '', labels[value]);
                    button.type = 'button';
                    button.setAttribute('aria-pressed', String(status(item.id) === value));
                    button.addEventListener('click', () => {
                        save(item.id, { status: value });
                        badge.textContent = labels[value];
                        badge.className = 'status ' + value;
                        buttons.querySelectorAll('button').forEach(b => {
                            const selected = b === button;
                            b.classList.toggle('selected', selected);
                            b.setAttribute('aria-pressed', String(selected));
                        });
                        timestamp.textContent = 'Last note: ' + dateText(record(item.id).at);
                        updateProgress();
                    });
                    buttons.append(button);
                });
                observation.append(buttons);
            }
            const noteId = 'note-' + item.id;
            const label = el('label', '', item.kind === 'uat' ? 'Preparation notes (not participant ratings)' : 'What did you see? (optional)');
            label.htmlFor = noteId;
            const textarea = el('textarea');
            textarea.id = noteId;
            textarea.maxLength = 1000;
            textarea.rows = 2;
            textarea.placeholder = 'Short notes only. Do not enter passwords, signatures, or private credentials.';
            textarea.value = typeof record(item.id).notes === 'string' ? record(item.id).notes.slice(0, 1000) : '';
            const timestamp = el('p', 'recorded-at', record(item.id).at ? 'Last note: ' + dateText(record(item.id).at) : 'No observation recorded yet.');
            textarea.addEventListener('input', () => {
                save(item.id, { notes: textarea.value });
                timestamp.textContent = 'Last note: ' + dateText(record(item.id).at);
            });
            observation.append(label, textarea, timestamp);
            body.append(observation);
        }
        card.append(body);
        return card;
    }
    function renderRunner() {
        const box = el('section', 'runner');
        const top = el('div', 'runner-top');
        const text = el('div');
        text.append(el('h3', '', 'Run the seven named safety checks'));
        let message = 'No automatic checks have been run on this page yet.';
        if (running) message = 'Running local checks. This does not change business records…';
        else if (run) {
            const named = items.filter(item => item.kind === 'auto');
            const passed = named.filter(item => run.results?.[item.id]?.status === 'PASS').length;
            message = run.complete ? `${passed} / 7 named checks passed. Run time: ${dateText(run.ran_at)}.` : 'The run did not finish correctly. Do not treat missing results as passed.';
            if (run.error) message += ' ' + run.error;
        }
        text.append(el('p', '', message));
        const button = el('button', 'primary', running ? 'Running…' : 'Run safety checks');
        button.id = 'runSafetyChecks';
        button.type = 'button';
        button.disabled = running;
        button.addEventListener('click', runChecks);
        top.append(text, button);
        box.append(top);
        paragraph(box, 'small', '', 'These are small rule checks, not a full employee-workflow test. Screen checks still need to be demonstrated. Extra existing checks appear in the details below.');
        if (run && run.output) {
            const details = el('details', 'runner-details');
            details.append(el('summary', '', 'See the complete run details'), el('pre', '', run.output));
            box.append(details);
        }
        return box;
    }
    async function runChecks() {
        if (running) return;
        running = true;
        run = null;
        render();
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 25000);
        try {
            const response = await fetch('professor-checklist.php', {
                method: 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: { 'Content-Type': 'application/json', 'X-Checklist-Token': document.querySelector('meta[name="checklist-token"]').content },
                body: JSON.stringify({ action: 'run_security' }),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'The checks could not run.');
            run = result;
        } catch (error) {
            run = { complete: false, results: {}, error: error.name === 'AbortError' ? 'The runner took too long. Reload and try again.' : error.message };
        } finally {
            clearTimeout(timeout);
            running = false;
            render();
        }
    }
    function render() {
        const query = byId('checkSearch').value.trim().toLowerCase();
        const group = groups.find(value => value.id === groupId);
        const filtered = items.filter(item => query ? [item.id, item.title, item.role, item.screen, item.expected, item.note, ...(item.steps || [])].join(' ').toLowerCase().includes(query) : item.group === groupId);
        byId('groupEyebrow').textContent = query ? 'Search across the testing plan' : group.level;
        byId('groupTitle').textContent = query ? 'Search results' : group.title;
        byId('groupHelp').textContent = query ? 'Choose a matching check below. Clear the search to return to your section.' : group.help;
        byId('groupCount').textContent = `${filtered.length} checks`;
        byId('readinessNotes').replaceChildren();
        if (!query) byId('readinessNotes').append(el('p', 'section-note', group.hint));
        const list = byId('testList');
        list.replaceChildren();
        if (filtered.some(item => item.kind === 'auto')) list.append(renderRunner());
        filtered.forEach((item, index) => list.append(renderCard(item, index === 0)));
        if (!filtered.length) list.append(el('p', 'empty-state', 'No matching check. Try Login, PDF, search, or ST-401.'));
        updateProgress();
    }
    function currentAccount() {
        let text = 'No staff account signed in';
        try {
            const user = JSON.parse(localStorage.getItem('highland_user') || 'null');
            if (user && typeof user === 'object') {
                const name = user.full_name || [user.first_name, user.last_name].filter(Boolean).join(' ') || user.username || 'Staff';
                const role = user.role_name || user.role || 'employee';
                text = `Current account: ${name} · ${role}`;
            }
        } catch (_) { /* Do not expose storage contents on parse failure. */ }
        byId('signedIn').textContent = text;
    }
    function observed(item) {
        if (item.kind === 'auto') return run?.results?.[item.id]?.status || 'Not run';
        if (item.kind === 'uat') return 'Participant form — no rating recorded here';
        return labels[status(item.id)];
    }
    function csvCell(value) {
        let text = String(value ?? '');
        if (/^[\s]*[=+@-]/.test(text)) text = "'" + text;
        return '"' + text.replaceAll('"', '""') + '"';
    }
    byId('downloadNotes').addEventListener('click', () => {
        const rows = [['Highland Fresh classroom observations — not official acceptance'], ['ID', 'Check', 'Account', 'Screen', 'What to show', 'Observation', 'Recorded at', 'Notes', 'Important difference']];
        items.forEach(item => rows.push([item.id, item.title, item.role, item.page, item.expected, observed(item), item.kind === 'auto' ? run?.ran_at : record(item.id).at, record(item.id).notes, item.note]));
        const url = URL.createObjectURL(new Blob(['\ufeff' + rows.map(row => row.map(csvCell).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' }));
        const a = el('a');
        a.href = url;
        a.download = 'Highland-Fresh-professor-observations.csv';
        a.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
    byId('printNotes').addEventListener('click', () => {
        const sheet = byId('printSheet');
        sheet.replaceChildren(el('h1', '', 'Highland Fresh — Professor demonstration guide'), el('p', '', 'Classroom observations, not official participant ratings or acceptance. Printed ' + new Date().toLocaleString()));
        groups.forEach(group => {
            sheet.append(el('h2', '', group.title), el('p', '', group.hint));
            items.filter(item => item.group === group.id).forEach(item => {
                const article = el('article');
                article.append(el('h3', '', `${item.id} · ${item.title}`));
                if (item.page) paragraph(article, '', 'Account / Screen:', `${item.role} / ${item.screen} (${item.page})`);
                if (item.prep) paragraph(article, '', 'Have ready:', item.prep);
                const list = el('ol');
                (item.steps || []).forEach(step => list.append(el('li', '', step)));
                article.append(list);
                paragraph(article, '', 'What to show:', item.expected);
                if (item.note) paragraph(article, '', 'Important:', item.note);
                if (item.criteria) item.criteria.forEach(value => paragraph(article, '', '', value));
                paragraph(article, '', 'Observation:', observed(item));
                if (record(item.id).notes) paragraph(article, '', 'Notes:', record(item.id).notes);
                sheet.append(article);
            });
        });
        window.print();
    });
    byId('resetNotes').addEventListener('click', () => { byId('resetDialog').returnValue = ''; byId('resetDialog').showModal(); });
    byId('resetDialog').addEventListener('close', () => {
        if (byId('resetDialog').returnValue !== 'reset') return;
        records = {};
        run = null;
        try { localStorage.removeItem(key); } catch (_) { /* In-memory reset still works. */ }
        render();
    });
    byId('checkSearch').addEventListener('input', render);
    window.addEventListener('focus', currentAccount);
    window.addEventListener('storage', currentAccount);
    currentAccount();
    render();
})();
