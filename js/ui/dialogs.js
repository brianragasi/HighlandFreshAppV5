(function () {
    if (window.AppDialogs) return;

    const ensureDialog = () => {
        let dialog = document.getElementById('appDialogModal');
        if (dialog) return dialog;

        dialog = document.createElement('dialog');
        dialog.id = 'appDialogModal';
        dialog.className = 'modal';
        dialog.innerHTML = `
            <div class="modal-box max-w-md">
                <form method="dialog">
                    <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2" aria-label="Close">
                        <i class="fas fa-xmark"></i>
                    </button>
                </form>
                <div class="flex items-start gap-3">
                    <div id="appDialogIcon" class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <i class="fas fa-circle-question"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 id="appDialogTitle" class="font-bold text-lg">Confirm Action</h3>
                        <p id="appDialogMessage" class="mt-2 text-sm leading-6 text-base-content/70"></p>
                    </div>
                </div>
                <div id="appDialogPromptWrap" class="form-control mt-5 hidden">
                    <label class="label" for="appDialogInput">
                        <span id="appDialogInputLabel" class="label-text">Reason</span>
                    </label>
                    <textarea id="appDialogInput" class="textarea textarea-bordered min-h-24" rows="3"></textarea>
                    <p id="appDialogError" class="mt-2 hidden text-sm text-error"></p>
                </div>
                <div class="modal-action">
                    <button id="appDialogCancel" type="button" class="btn btn-ghost">Cancel</button>
                    <button id="appDialogConfirm" type="button" class="btn btn-primary">Confirm</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button>close</button></form>
        `;
        document.body.appendChild(dialog);
        return dialog;
    };

    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value || '';
    };

    const open = (options = {}) => new Promise((resolve) => {
        const dialog = ensureDialog();
        const promptWrap = document.getElementById('appDialogPromptWrap');
        const input = document.getElementById('appDialogInput');
        const error = document.getElementById('appDialogError');
        const confirmButton = document.getElementById('appDialogConfirm');
        const cancelButton = document.getElementById('appDialogCancel');
        const icon = document.getElementById('appDialogIcon');
        const isPrompt = options.mode === 'prompt';
        let settled = false;

        setText('appDialogTitle', options.title || 'Confirm Action');
        setText('appDialogMessage', options.message || '');
        setText('appDialogInputLabel', options.label || 'Reason');
        setText('appDialogError', options.requiredMessage || 'This field is required.');
        confirmButton.textContent = options.confirmText || 'Confirm';
        cancelButton.textContent = options.cancelText || 'Cancel';
        confirmButton.className = `btn ${options.variant === 'danger' ? 'btn-error' : options.variant === 'warning' ? 'btn-warning' : 'btn-primary'}`;
        icon.className = `mt-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${
            options.variant === 'danger'
                ? 'bg-error/10 text-error'
                : options.variant === 'warning'
                    ? 'bg-warning/10 text-warning'
                    : 'bg-primary/10 text-primary'
        }`;
        icon.innerHTML = `<i class="fas ${options.icon || (isPrompt ? 'fa-pen-to-square' : 'fa-circle-question')}"></i>`;

        promptWrap.classList.toggle('hidden', !isPrompt);
        error.classList.add('hidden');
        input.value = options.defaultValue || '';
        input.placeholder = options.placeholder || '';

        const closeWith = (value) => {
            if (settled) return;
            settled = true;
            dialog.close();
            resolve(value);
        };

        const onConfirm = () => {
            if (!isPrompt) {
                closeWith(true);
                return;
            }
            const value = input.value.trim();
            if (options.required !== false && !value) {
                error.classList.remove('hidden');
                input.focus();
                return;
            }
            closeWith(value);
        };

        const onCancel = () => closeWith(isPrompt ? null : false);
        const onClose = () => closeWith(isPrompt ? null : false);

        confirmButton.onclick = onConfirm;
        cancelButton.onclick = onCancel;
        dialog.onclose = onClose;
        dialog.oncancel = (event) => {
            event.preventDefault();
            onCancel();
        };

        dialog.showModal();
        if (isPrompt) {
            setTimeout(() => input.focus(), 0);
        } else {
            setTimeout(() => confirmButton.focus(), 0);
        }
    });

    window.AppDialogs = {
        confirm(options) {
            return open({ ...options, mode: 'confirm' });
        },
        prompt(options) {
            return open({ ...options, mode: 'prompt' });
        }
    };
})();
