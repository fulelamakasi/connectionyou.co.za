/**
 * Entries inbox (index.php?page=entries).
 * Loads everything once with getAllUsers, filters client-side for the tabs,
 * and uses the typed search endpoints for exact-match lookups.
 */
(function () {
    const list = document.getElementById('entry-list');

    if (!list) {
        return;
    }

    const state = {
        allUsers: [],
        searchResults: null,
        searchLabel: '',
        filter: 'open',
        selectedId: null,
        editing: false,
        revealed: {},
        pendingReveal: null
    };

    const listEmpty = document.getElementById('list-empty');
    const detail = document.getElementById('entry-detail');
    const workspace = document.getElementById('workspace');
    const filters = document.getElementById('entry-filters');
    const searchForm = document.getElementById('search-form');
    const searchType = document.getElementById('search-type');
    const searchQuery = document.getElementById('search-query');
    const searchClear = document.getElementById('search-clear');
    const searchSummary = document.getElementById('search-summary');

    const filterLabels = {
        open: 'open',
        active: 'active',
        inactive: 'inactive',
        archived: 'archived'
    };

    function isArchived(user) {
        return user.deleted_at !== null && user.deleted_at !== undefined && user.deleted_at !== '';
    }

    function isActive(user) {
        return String(user.is_active) === '1';
    }

    function matchesFilter(user, filter) {
        if (filter === 'archived') {
            return isArchived(user);
        }

        if (isArchived(user)) {
            return false;
        }

        if (filter === 'active') {
            return isActive(user);
        }

        if (filter === 'inactive') {
            return !isActive(user);
        }

        return true;
    }

    function baseUsers() {
        return state.searchResults !== null ? state.searchResults : state.allUsers;
    }

    function sortedNewestFirst(users) {
        return users.slice().sort(function (a, b) {
            return String(b.created_at).localeCompare(String(a.created_at)) || (b.id - a.id);
        });
    }

    function findUser(id) {
        const pool = state.allUsers.concat(state.searchResults || []);

        for (let i = 0; i < pool.length; i++) {
            if (String(pool[i].id) === String(id)) {
                return pool[i];
            }
        }

        return null;
    }

    async function loadAllUsers() {
        list.setAttribute('aria-busy', 'true');
        const result = await getAllUsers();
        list.setAttribute('aria-busy', 'false');

        if (result.code !== 200 && result.code !== 400) {
            showToast(result.message || 'Couldn’t load entries.', 'danger');
        }

        state.allUsers = result.data;
    }

    async function rerunSearch() {
        if (state.searchResults === null) {
            return;
        }

        const result = await searchUsers(searchType.value, searchQuery.value.trim());
        state.searchResults = result.data;
    }

    async function refresh() {
        state.revealed = {};
        await loadAllUsers();
        await rerunSearch();
        render();
    }

    function renderCounts() {
        const users = baseUsers();

        Object.keys(filterLabels).forEach(function (filter) {
            const count = users.filter(function (user) { return matchesFilter(user, filter); }).length;
            filters.querySelector('[data-count="' + filter + '"]').textContent = count;
        });

        filters.querySelectorAll('[data-filter]').forEach(function (button) {
            button.setAttribute('aria-selected', button.dataset.filter === state.filter ? 'true' : 'false');
        });
    }

    function renderList() {
        const users = sortedNewestFirst(baseUsers().filter(function (user) {
            return matchesFilter(user, state.filter);
        }));

        list.innerHTML = users.map(function (user) {
            const selected = String(user.id) === String(state.selectedId);
            const flags = [];

            if (isArchived(user)) {
                flags.push('<span class="tag tag-archived">Archived</span>');
            } else if (!isActive(user)) {
                flags.push('<span class="tag tag-inactive">Inactive</span>');
            }

            return '<li>' +
                '<button type="button" class="entry-row' + (selected ? ' is-selected' : '') + '" data-id="' + escapeHtml(user.id) + '"' + (selected ? ' aria-current="true"' : '') + '>' +
                    '<span class="entry-row-top">' +
                        '<span class="entry-row-name">' + escapeHtml(user.name) + '</span>' +
                        '<time class="entry-row-date">' + escapeHtml(formatDateShort(user.created_at)) + '</time>' +
                    '</span>' +
                    '<span class="entry-row-email">' + escapeHtml(user.email) + flags.join('') + '</span>' +
                    '<span class="entry-row-preview">' + escapeHtml(user.message) + '</span>' +
                '</button>' +
            '</li>';
        }).join('');

        if (users.length === 0) {
            listEmpty.hidden = false;
            listEmpty.innerHTML = state.searchResults !== null
                ? '<p>No ' + filterLabels[state.filter] + ' entries match this search.</p><button type="button" class="btn btn-ghost btn-small" data-action="clear-search">Clear search</button>'
                : '<p>No ' + filterLabels[state.filter] + ' entries yet.</p>';
        } else {
            listEmpty.hidden = true;
            listEmpty.innerHTML = '';
        }
    }

    function renderDetail() {
        const user = state.selectedId !== null ? findUser(state.selectedId) : null;

        if (!user) {
            workspace.classList.remove('show-detail');
            detail.innerHTML = '<div class="empty-state"><p>Select an entry to read it.</p></div>';
            return;
        }

        if (state.editing) {
            renderEditForm(user);
            return;
        }

        const archived = isArchived(user);
        const active = isActive(user);
        let statusText = active ? 'Active' : 'Inactive';

        if (archived) {
            statusText = 'Archived ' + formatDateLong(user.deleted_at);
        }

        detail.innerHTML =
            '<article class="entry">' +
                '<button type="button" class="btn btn-ghost btn-small back-to-list" data-action="back">Back to entries</button>' +
                '<header class="entry-header">' +
                    '<h1>' + escapeHtml(user.name) + '</h1>' +
                    '<p class="muted">Received ' + escapeHtml(formatDateLong(user.created_at)) + '</p>' +
                '</header>' +

                '<dl class="entry-meta">' +
                    '<div><dt>Email</dt><dd><a href="mailto:' + escapeHtml(user.email) + '">' + escapeHtml(user.email) + '</a></dd></div>' +
                    '<div><dt>Mobile</dt><dd class="reveal">' + renderPhone(user) + '</dd></div>' +
                    '<div><dt>ID number</dt><dd class="reveal">' + renderIdNumber(user) + '</dd></div>' +
                    '<div><dt>Status</dt><dd><span class="status status-' + (archived ? 'archived' : (active ? 'active' : 'inactive')) + '">' + escapeHtml(statusText) + '</span></dd></div>' +
                    (user.updated_at ? '<div><dt>Last updated</dt><dd>' + escapeHtml(formatDateLong(user.updated_at)) + (user.editedby ? ' <span class="muted">by ' + escapeHtml(user.editedby) + '</span>' : '') + '</dd></div>' : '') +
                '</dl>' +

                '<div class="entry-message">' + escapeHtml(user.message) + '</div>' +

                '<div class="entry-actions">' +
                    (archived
                        ? '<button type="button" class="btn btn-primary" data-action="restore">Restore entry</button>'
                        : '<button type="button" class="btn btn-primary" data-action="edit">Edit details</button>' +
                          '<button type="button" class="btn btn-secondary" data-action="toggle-status">' + (active ? 'Mark inactive' : 'Mark active') + '</button>' +
                          '<button type="button" class="btn btn-ghost btn-danger-text" data-action="archive">Archive</button>') +
                '</div>' +
            '</article>';
    }

    function revealedValue(user, field) {
        const value = state.revealed[user.id + ':' + field];
        return value === undefined ? null : value;
    }

    function revealButton(field, label) {
        return '<button type="button" class="btn btn-ghost btn-small" data-action="reveal" data-field="' + field + '">' + label + '</button>';
    }

    function hideButton(field) {
        return '<button type="button" class="btn btn-ghost btn-small" data-action="hide" data-field="' + field + '">Hide</button>';
    }

    function renderPhone(user) {
        const phone = revealedValue(user, 'phone');

        if (phone !== null) {
            return '<a href="tel:' + escapeHtml(phone.replace(/\s+/g, '')) + '">' + escapeHtml(formatPhone(phone)) + '</a>' + hideButton('phone');
        }

        return '<span class="masked">' + escapeHtml(user.phone_number) + '</span>' + revealButton('phone', 'Show number');
    }

    function renderIdNumber(user) {
        const idNumber = revealedValue(user, 'id');

        if (idNumber !== null) {
            return '<span class="masked">' + escapeHtml(idNumber) + '</span>' + hideButton('id');
        }

        if (!user.id_number_available) {
            return '<span class="muted">Not on file</span>';
        }

        return '<span class="masked">*************</span>' + revealButton('id', 'Show ID number');
    }

    function renderEditForm(user) {
        detail.innerHTML =
            '<form class="form entry-edit" id="edit-form" novalidate>' +
                '<header class="entry-header">' +
                    '<h1>Edit ' + escapeHtml(user.name) + '</h1>' +
                    '<p class="muted">Changes are saved to this entry only.</p>' +
                '</header>' +
                '<div class="form-row">' +
                    field('first_name', 'First name', user.first_name, 'text') +
                    field('last_name', 'Last name', user.last_name, 'text') +
                '</div>' +
                '<div class="form-row">' +
                    field('email', 'Email', user.email, 'email') +
                    '<div class="field">' +
                        '<label for="edit-phone_number">New mobile number</label>' +
                        '<input id="edit-phone_number" name="phone_number" type="tel" inputmode="tel" autocomplete="off" placeholder="' + escapeHtml(user.phone_number) + '">' +
                        '<p class="field-hint">Leave blank to keep the number on file.</p>' +
                        '<p class="field-error" data-error-for="phone_number"></p>' +
                    '</div>' +
                '</div>' +
                '<div class="field">' +
                    '<label for="edit-id_number">New ID number</label>' +
                    '<input id="edit-id_number" name="id_number" type="text" inputmode="numeric" maxlength="13" autocomplete="off">' +
                    '<p class="field-hint">Leave blank to keep the ID number on file.</p>' +
                    '<p class="field-error" data-error-for="id_number"></p>' +
                '</div>' +
                '<div class="field">' +
                    '<label for="edit-message">Message</label>' +
                    '<textarea id="edit-message" name="message" rows="8">' + escapeHtml(user.message) + '</textarea>' +
                    '<p class="field-error" data-error-for="message"></p>' +
                '</div>' +
                '<div class="entry-actions">' +
                    '<button type="submit" class="btn btn-primary" id="edit-save">Save changes</button>' +
                    '<button type="button" class="btn btn-ghost" data-action="cancel-edit">Cancel</button>' +
                '</div>' +
            '</form>';

        const form = document.getElementById('edit-form');
        form.querySelector('input').focus();
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            saveEdit(user, form);
        });
    }

    function field(name, label, value, type) {
        return '<div class="field">' +
            '<label for="edit-' + name + '">' + label + '</label>' +
            '<input id="edit-' + name + '" name="' + name + '" type="' + type + '" value="' + escapeHtml(value) + '">' +
            '<p class="field-error" data-error-for="' + name + '"></p>' +
        '</div>';
    }

    function render() {
        renderCounts();
        renderList();
        renderDetail();
    }

    async function runAction(promise, successFallback) {
        const result = await promise;

        if (isSuccessCode(result.code)) {
            showToast(result.message || successFallback, 'success');
            await refresh();
            return true;
        }

        showToast((result.heading ? result.heading + ': ' : '') + (result.message || 'That didn’t work. Try again.'), 'danger');
        return false;
    }

    async function saveEdit(user, form) {
        const fields = readFormFields(form);
        const errors = validateUserFields(fields, false);

        setFieldErrors(form, errors);

        if (Object.keys(errors).length > 0) {
            return;
        }

        const button = document.getElementById('edit-save');
        setButtonBusy(button, true, 'Saving…');
        state.editing = false;
        const ok = await runAction(updateUser(user.id, fields), 'Entry updated.');

        if (!ok) {
            state.editing = true;
            setButtonBusy(button, false);
        }
    }

    detail.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-action]');

        if (!button) {
            return;
        }

        const user = findUser(state.selectedId);
        const action = button.dataset.action;

        if (action === 'back') {
            state.selectedId = null;
            state.revealed = {};
            render();
            return;
        }

        if (!user) {
            return;
        }

        if (action === 'reveal') {
            openRevealDialog(user, button.dataset.field);
            return;
        }

        if (action === 'hide') {
            const field = button.dataset.field;
            delete state.revealed[user.id + ':' + field];
            renderDetail();
            focusRevealControl(field);
            return;
        }

        if (action === 'edit') {
            state.editing = true;
            state.revealed = {};
            renderDetail();
            return;
        }

        if (action === 'cancel-edit') {
            state.editing = false;
            renderDetail();
            return;
        }

        if (action === 'toggle-status') {
            button.disabled = true;
            await runAction(updateUserStatus(user.id, !isActive(user)), 'Status updated.');
            return;
        }

        if (action === 'archive') {
            const confirmed = await confirmAction('Archive this entry?', user.name + '’s entry moves to Archived. You can restore it later.', 'Archive');

            if (confirmed) {
                await runAction(updateUserArchived(user.id), 'Entry archived.');
            }
            return;
        }

        if (action === 'restore') {
            button.disabled = true;
            await runAction(updateUserUnArchived(user.id), 'Entry restored.');
        }
    });

    list.addEventListener('click', function (event) {
        const row = event.target.closest('.entry-row');

        if (!row) {
            return;
        }

        if (String(state.selectedId) !== String(row.dataset.id)) {
            state.revealed = {};
        }

        state.selectedId = row.dataset.id;
        state.editing = false;
        workspace.classList.add('show-detail');
        renderList();
        renderDetail();

        const heading = detail.querySelector('h1');
        if (heading && window.matchMedia('(max-width: 860px)').matches) {
            heading.setAttribute('tabindex', '-1');
            heading.focus();
        }
    });

    list.addEventListener('keydown', function (event) {
        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
            return;
        }

        const rows = Array.prototype.slice.call(list.querySelectorAll('.entry-row'));
        const index = rows.indexOf(document.activeElement);

        if (index === -1) {
            return;
        }

        event.preventDefault();
        const next = rows[index + (event.key === 'ArrowDown' ? 1 : -1)];

        if (next) {
            next.focus();
        }
    });

    filters.addEventListener('click', function (event) {
        const button = event.target.closest('[data-filter]');

        if (!button) {
            return;
        }

        state.filter = button.dataset.filter;
        render();
    });

    function updateSearchInputType() {
        const isDate = searchType.value === 'getAllUsersByDate';
        searchQuery.type = isDate ? 'date' : 'search';
        searchQuery.placeholder = isDate ? '' : 'Exact match';
    }

    searchType.addEventListener('change', updateSearchInputType);

    searchForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        const query = searchQuery.value.trim();

        if (query === '') {
            searchQuery.focus();
            showToast('Enter something to search for.', 'neutral');
            return;
        }

        const result = await searchUsers(searchType.value, query);

        if (result.code !== 200 && result.code !== 400) {
            showToast(result.message || 'Search failed.', 'danger');
            return;
        }

        state.searchResults = result.data;
        state.searchLabel = searchType.options[searchType.selectedIndex].text + ' is “' + query + '”';
        searchClear.hidden = false;
        searchSummary.hidden = false;
        searchSummary.textContent = result.data.length + (result.data.length === 1 ? ' entry' : ' entries') + ' where ' + state.searchLabel + '.';

        if (result.data.length > 0 && !result.data.some(function (user) { return matchesFilter(user, state.filter); })) {
            state.filter = isArchived(result.data[0]) ? 'archived' : 'open';
        }

        render();
    });

    function clearSearch() {
        state.searchResults = null;
        state.searchLabel = '';
        searchQuery.value = '';
        searchClear.hidden = true;
        searchSummary.hidden = true;
        render();
    }

    searchClear.addEventListener('click', clearSearch);

    listEmpty.addEventListener('click', function (event) {
        if (event.target.closest('[data-action="clear-search"]')) {
            clearSearch();
        }
    });

    // ---------- Reveal phone / ID behind REVEAL_PASSWORD ----------

    const revealDialog = document.getElementById('reveal-dialog');
    const revealForm = document.getElementById('reveal-form');
    const revealPassword = document.getElementById('reveal-password');
    const revealError = document.getElementById('reveal-error');
    const revealSubmit = document.getElementById('reveal-submit');
    const revealLabels = { phone: 'mobile number', id: 'ID number' };

    function focusRevealControl(field) {
        const control = detail.querySelector('[data-field="' + field + '"]');

        if (control) {
            control.focus();
        }
    }

    function setRevealError(message) {
        revealError.textContent = message || '';

        if (message) {
            revealPassword.setAttribute('aria-invalid', 'true');
        } else {
            revealPassword.removeAttribute('aria-invalid');
        }
    }

    function openRevealDialog(user, field) {
        state.pendingReveal = { userId: user.id, field: field };
        document.getElementById('reveal-message').textContent =
            'Enter the password to show ' + user.name + '’s ' + revealLabels[field] + '.';
        revealPassword.value = '';
        setRevealError('');
        revealSubmit.disabled = false;

        if (typeof revealDialog.showModal !== 'function') {
            const password = window.prompt('Password');

            if (password) {
                submitReveal(password);
            }
            return;
        }

        revealDialog.showModal();
        revealPassword.focus();
    }

    async function submitReveal(password) {
        const pending = state.pendingReveal;

        if (!pending) {
            return;
        }

        const usingDialog = revealDialog.open;

        if (usingDialog) {
            setButtonBusy(revealSubmit, true, 'Checking…');
        }

        const result = await revealUserField(pending.userId, pending.field, password);

        if (usingDialog) {
            setButtonBusy(revealSubmit, false);
        }

        if (result.code === 200) {
            state.revealed[pending.userId + ':' + pending.field] = result.value;
            state.pendingReveal = null;

            if (usingDialog) {
                revealDialog.close();
            }

            if (String(state.selectedId) === String(pending.userId) && !state.editing) {
                renderDetail();
                focusRevealControl(pending.field);
            }
            return;
        }

        let message = result.message || 'That didn’t work. Try again.';

        if (result.code === 401 && result.remaining !== undefined) {
            message = 'Incorrect password. ' + result.remaining + (Number(result.remaining) === 1 ? ' attempt' : ' attempts') + ' left.';
        }

        if (!usingDialog) {
            showToast(message, 'danger');
            return;
        }

        setRevealError(message);
        revealPassword.value = '';

        if (result.code === 429) {
            revealSubmit.disabled = true;
            return;
        }

        revealPassword.focus();
    }

    revealForm.addEventListener('submit', function (event) {
        event.preventDefault();
        const password = revealPassword.value;

        if (password === '') {
            setRevealError('Enter the password.');
            revealPassword.focus();
            return;
        }

        submitReveal(password);
    });

    document.getElementById('reveal-cancel').addEventListener('click', function () {
        revealDialog.close();
    });

    revealDialog.addEventListener('close', function () {
        const pending = state.pendingReveal;
        revealPassword.value = '';
        setRevealError('');
        state.pendingReveal = null;

        if (pending && String(state.selectedId) === String(pending.userId)) {
            focusRevealControl(pending.field);
        }
    });

    updateSearchInputType();
    refresh();
})();
