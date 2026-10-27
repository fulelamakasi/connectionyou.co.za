function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function parseDbDate(value) {
    if (!value) {
        return null;
    }

    const parts = String(value).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);

    if (!parts) {
        return null;
    }

    return new Date(+parts[1], +parts[2] - 1, +parts[3], +parts[4], +parts[5], +parts[6]);
}

function formatDateShort(value) {
    const date = parseDbDate(value);

    if (!date) {
        return '';
    }

    const today = new Date();

    if (date.toDateString() === today.toDateString()) {
        return date.toLocaleTimeString('en-ZA', { hour: '2-digit', minute: '2-digit' });
    }

    if (date.getFullYear() === today.getFullYear()) {
        return date.toLocaleDateString('en-ZA', { day: 'numeric', month: 'short' });
    }

    return date.toLocaleDateString('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatDateLong(value) {
    const date = parseDbDate(value);

    if (!date) {
        return '';
    }

    return date.toLocaleString('en-ZA', {
        weekday: 'short', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
}

function formatPhone(value) {
    const matches = String(value || '').replace(/\s+/g, '').match(/^\+27(\d{2})(\d{3})(\d{4})$/);

    if (!matches) {
        return value || '';
    }

    return '0' + matches[1] + ' ' + matches[2] + ' ' + matches[3];
}

function showToast(message, tone) {
    const region = document.getElementById('toast-region');

    if (!region) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = 'toast toast-' + (tone || 'neutral');
    toast.textContent = message;
    region.appendChild(toast);

    setTimeout(function () {
        toast.classList.add('toast-leaving');
        setTimeout(function () { toast.remove(); }, 250);
    }, 4200);
}

function setFieldErrors(form, errors) {
    form.querySelectorAll('.field-error').forEach(function (el) {
        el.textContent = '';
    });

    form.querySelectorAll('[aria-invalid]').forEach(function (el) {
        el.removeAttribute('aria-invalid');
        el.removeAttribute('aria-describedby');
    });

    let firstInvalid = null;

    Object.keys(errors).forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        const errorEl = form.querySelector('[data-error-for="' + field + '"]');

        if (errorEl) {
            errorEl.textContent = errors[field];
            errorEl.id = errorEl.id || ('error-' + field + '-' + Math.random().toString(36).slice(2, 7));
        }

        if (input) {
            input.setAttribute('aria-invalid', 'true');
            if (errorEl) {
                input.setAttribute('aria-describedby', errorEl.id);
            }
            if (!firstInvalid) {
                firstInvalid = input;
            }
        }
    });

    if (firstInvalid) {
        firstInvalid.focus();
    }
}

function readFormFields(form) {
    const fields = {};

    new FormData(form).forEach(function (value, key) {
        fields[key] = String(value).trim();
    });

    return fields;
}

function confirmAction(title, message, confirmLabel) {
    const dialog = document.getElementById('confirm-dialog');

    if (!dialog || typeof dialog.showModal !== 'function') {
        return Promise.resolve(window.confirm(message));
    }

    document.getElementById('confirm-title').textContent = title;
    document.getElementById('confirm-message').textContent = message;
    document.getElementById('confirm-ok').textContent = confirmLabel;

    return new Promise(function (resolve) {
        dialog.addEventListener('close', function onClose() {
            dialog.removeEventListener('close', onClose);
            resolve(dialog.returnValue === 'confirm');
        });
        dialog.returnValue = 'cancel';
        dialog.showModal();
    });
}

function setButtonBusy(button, busy, busyLabel) {
    if (busy) {
        button.dataset.label = button.textContent;
        button.textContent = busyLabel;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    } else {
        button.textContent = button.dataset.label || button.textContent;
        button.disabled = false;
        button.removeAttribute('aria-busy');
    }
}
