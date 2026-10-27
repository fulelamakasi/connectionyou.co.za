(function () {
    const form = document.getElementById('enquiry-form');

    if (!form) {
        return;
    }

    const status = document.getElementById('enquiry-status');
    const submitButton = document.getElementById('enquiry-submit');
    const idInput = document.getElementById('id_number');

    idInput.addEventListener('input', function () {
        idInput.value = idInput.value.replace(/\D/g, '').slice(0, 13);
    });

    function showStatus(tone, heading, message) {
        status.className = 'form-status form-status-' + tone;
        status.innerHTML = '<strong>' + escapeHtml(heading) + '</strong> ' + escapeHtml(message);
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        status.className = 'form-status';
        status.textContent = '';

        const fields = readFormFields(form);
        const errors = validateUserFields(fields, true);

        setFieldErrors(form, errors);

        if (Object.keys(errors).length > 0) {
            return;
        }

        setButtonBusy(submitButton, true, 'Sending…');
        const result = await createUser(fields);
        setButtonBusy(submitButton, false);

        if (isSuccessCode(result.code)) {
            form.reset();
            form.classList.add('is-sent');
            showStatus('success', 'Enquiry sent.', 'Thanks, ' + fields.first_name + '. We’ll be in touch soon.');
            status.focus();
            return;
        }

        showStatus('danger', (result.heading || 'Couldn’t send your enquiry') + '.', result.message || 'Try again in a moment.');
    });

    form.addEventListener('input', function () {
        form.classList.remove('is-sent');
    });
})();
