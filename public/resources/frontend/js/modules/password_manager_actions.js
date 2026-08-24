window.initPasswordManager = function() {
    const resolvedBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');
    
    // --- Personal Passwords Elements ---
    const passwordTableBody = document.getElementById('passwordTableBody');
    const createNewPasswordBtn = document.getElementById('createNewPasswordBtn');
    
    // Exit if not on the Password Manager page
    if (!passwordTableBody) return;
    if (passwordTableBody.dataset.initialized === 'true') return;
    passwordTableBody.dataset.initialized = 'true';

    // --- Global Passwords Elements ---
    const globalPasswordTableBody = document.getElementById('globalPasswordTableBody');

    // --- Modal Elements ---
    const passwordModalEl = document.getElementById('passwordModal');
    if (!passwordModalEl) return;
    const passwordModal = new bootstrap.Modal(passwordModalEl);
    const passwordModalLabel = document.getElementById('passwordModalLabel');
    const passwordForm = document.getElementById('passwordForm');
    const savePasswordEntryBtn = document.getElementById('savePasswordEntry');
    const passwordEntryId = document.getElementById('passwordEntryId');
    const parentIdDropdown = document.getElementById('passwordParent');
    const entryTypeDropdown = document.getElementById('passwordEntryType');

    const hasAdminView = document.querySelector('#passwordTable thead th:nth-child(2)')?.textContent === 'Creator';

    let currentPasswordEntries = [];
    let globallySharedIds = new Set();

    const api = {
        get: () => callApi({ action: 'get_passwords' }),
        getGlobal: () => callApi({ action: 'get_global_passwords' }),
        save: (data, scope) => callApi({ action: 'save_password', scope: scope, ...data }),
        delete: (id, scope) => callApi({ action: 'delete_password', scope: scope, id: id }),
        toggleShare: (id) => callApi({ action: 'toggle_share_password', id: id }),
    };

    async function callApi(payload) {
        try {
            const response = await fetch(`${resolvedBaseUrl}/password_api.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return await response.json();
        } catch (error) {
            console.error('API call failed:', error);
            return { success: false, message: error.message };
        }
    }

    // --- Personal Password Table Functions ---

    function populateParentDropdown(entries, currentEntryId = null) {
        if (!parentIdDropdown) return;
        parentIdDropdown.innerHTML = '<option value="">No Parent</option>';
        entries.forEach(entry => {
            if (entry.id !== currentEntryId) {
                const option = document.createElement('option');
                option.value = entry.id;
                option.textContent = `${entry.system_name} (${entry.entry_type || 'credential'})`;
                parentIdDropdown.appendChild(option);
            }
        });
    }

    function buildTree(entries) {
        const entryMap = {};
        const rootEntries = [];
        entries.forEach(entry => {
            entry.children = [];
            entryMap[entry.id] = entry;
        });
        entries.forEach(entry => {
            if (entry.parent_id && entryMap[entry.parent_id]) {
                entryMap[entry.parent_id].children.push(entry);
            } else {
                rootEntries.push(entry);
            }
        });
        function sortChildren(node) {
            if (node.children && node.children.length > 0) {
                node.children.sort((a, b) => a.system_name.localeCompare(b.system_name));
                node.children.forEach(sortChildren);
            }
        }
        rootEntries.sort((a, b) => a.system_name.localeCompare(b.system_name));
        rootEntries.forEach(sortChildren);
        return rootEntries;
    }

    function asTooltipText(value) {
        return String(value ?? '');
    }

    function renderTextCell(value, className = 'text-nowrap') {
        const safeValue = escapeHtml(asTooltipText(value));
        return `<td class="${className}"><span class="cell-text" title="${safeValue}">${safeValue}</span></td>`;
    }

    function renderTree(nodes, level = 0, parentVisible = true, globallySharedIds) {
        nodes.forEach((entry, index) => {
            const row = document.createElement('tr');
            row.dataset.entry = JSON.stringify(entry);
            row.dataset.id = entry.id;
            row.dataset.level = level;
            row.classList.add('tree-row');
            if (!parentVisible) row.classList.add('hidden');
            if (entry.parent_id) row.dataset.parentId = entry.parent_id;
            if (index === nodes.length - 1) row.classList.add('is-last-child');

            const isExpandable = entry.children && entry.children.length > 0;
            if (isExpandable) row.classList.add('parent-row', `parent-row-level-${level}`);

            const toggleIcon = isExpandable ? `<i class="fas fa-chevron-right tree-toggle"></i>` : '';
            const systemName = escapeHtml(entry.system_name);
            const creatorCell = hasAdminView ? renderTextCell(entry.creator) : '';
            const ownerCell = renderTextCell(entry.owner);

            const isShared = globallySharedIds.has(entry.id);
            const shareButtonHtml = isShared
                ? `<button class="btn btn-icon btn-primary btn-sm share-btn" data-noc-tip="Unshare from Global"><i class="fas fa-globe"></i></button>`
                : `<button class="btn btn-icon btn-primary btn-sm share-btn" data-noc-tip="Share to Global"><i class="fas fa-share-square"></i></button>`;

            row.innerHTML = `
                <td title="${systemName}">
                    <div class="system-name-cell" style="--tree-level:${level}; ${isExpandable ? 'font-weight: 600;' : ''}">
                        ${toggleIcon}
                        <span class="cell-text">${systemName}</span>
                    </div>
                </td>
                ${creatorCell}${ownerCell}
                ${renderTextCell(entry.entry_type || 'credential')}
                ${renderTextCell(entry.user_id)}
                <td class="password-cell">
                    <div class="password-inner">
                        <span class="password-text cell-text" data-password="${escapeHtml(entry.password)}" data-noc-tip="Hidden password">******</span>
                        <div class="password-actions">
                            <button class="btn btn-icon btn-sm toggle-vis-btn" data-noc-tip="Toggle Visibility"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-icon btn-sm copy-btn" data-noc-tip="Copy Password"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>
                </td>
                ${renderTextCell(entry.ip || '')}
                <td class="text-nowrap"><a href="${escapeHtml(entry.url)}" target="_blank" rel="noopener noreferrer" class="cell-text" title="${escapeHtml(entry.url)}">${escapeHtml(entry.url)}</a></td>
                ${renderTextCell(entry.remarks)}
                <td class="actions-col text-nowrap" style="text-align:right;">
                    ${shareButtonHtml}
                    <button class="btn btn-icon btn-primary btn-sm edit-btn" data-noc-tip="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-icon btn-danger btn-sm delete-btn" data-noc-tip="Delete"><i class="fas fa-trash-alt"></i></button>
                </td>
            `;
            passwordTableBody.appendChild(row);
            if (isExpandable) renderTree(entry.children, level + 1, false, globallySharedIds);
        });
    }

    function renderPersonalTable(passwords, globallySharedIds) {
        if (!passwordTableBody) return;
        passwordTableBody.innerHTML = '';
        if (!passwords || passwords.length === 0) {
            const colCount = hasAdminView ? 11 : 10;
            passwordTableBody.innerHTML = `<tr><td colspan="${colCount}" class="text-center">No password entries found.</td></tr>`;
            return;
        }
        const tree = buildTree(passwords);
        renderTree(tree, 0, true, globallySharedIds);
    }

    async function loadPasswords() {
        const [personalResult, globalResult] = await Promise.all([api.get(), api.getGlobal()]);
        if (personalResult.success) currentPasswordEntries = personalResult.passwords;
        if (globalResult.success) {
            globallySharedIds.clear();
            globalResult.passwords.forEach(entry => globallySharedIds.add(entry.id));
        }
        renderPersonalTable(currentPasswordEntries, globallySharedIds);
    }

    function renderGlobalTable(passwords) {
        if (!globalPasswordTableBody) return;
        globalPasswordTableBody.innerHTML = '';
        if (!passwords || passwords.length === 0) {
            globalPasswordTableBody.innerHTML = `<tr><td colspan="10" class="text-center">No global passwords found.</td></tr>`;
            return;
        }
        passwords.forEach(entry => {
            const row = document.createElement('tr');
            row.dataset.entry = JSON.stringify(entry);
            const systemName = escapeHtml(entry.system_name);
            row.innerHTML = `
                <td title="${systemName}"><span class="cell-text">${systemName}</span></td>
                ${renderTextCell(entry.creator)}
                ${renderTextCell(entry.owner)}
                ${renderTextCell(entry.entry_type || 'credential')}
                ${renderTextCell(entry.user_id)}
                <td class="password-cell">
                    <div class="password-inner">
                        <span class="password-text cell-text" data-password="${escapeHtml(entry.password)}" data-noc-tip="Hidden password">******</span>
                        <div class="password-actions">
                            <button class="btn btn-icon btn-sm toggle-vis-btn" data-noc-tip="Toggle Visibility"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-icon btn-sm copy-btn" data-noc-tip="Copy Password"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>
                </td>
                ${renderTextCell(entry.ip || '')}
                <td class="text-nowrap"><a href="${escapeHtml(entry.url)}" target="_blank" rel="noopener noreferrer" class="cell-text" title="${escapeHtml(entry.url)}">${escapeHtml(entry.url)}</a></td>
                ${renderTextCell(entry.remarks)}
                <td class="actions-col text-nowrap" style="text-align:right;">
                    <button class="btn btn-icon btn-primary btn-sm edit-btn" data-noc-tip="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-icon btn-danger btn-sm delete-btn" data-noc-tip="Delete"><i class="fas fa-trash-alt"></i></button>
                </td>
            `;
            globalPasswordTableBody.appendChild(row);
        });
    }

    async function loadGlobalPasswords() {
        if (!globalPasswordTableBody) return;
        const result = await api.getGlobal();
        if (result.success) renderGlobalTable(result.passwords);
    }

    function handleFormSubmit() {
        const scope = passwordModalLabel.textContent.includes('Global') ? 'global' : 'personal';
        const data = {
            id: document.getElementById('passwordEntryId').value,
            owner: document.getElementById('passwordOwner').value,
            system_name: document.getElementById('passwordSystemName').value,
            url: document.getElementById('passwordUrl').value,
            user_id: document.getElementById('passwordUserId').value,
            password: document.getElementById('passwordValue').value,
            ip: document.getElementById('passwordIp').value,
            remarks: document.getElementById('passwordRemarks').value,
            parent_id: document.getElementById('passwordParent').value,
            entry_type: document.getElementById('passwordEntryType').value,
        };
        api.save(data, scope).then(result => {
            if (result.success) {
                passwordModal.hide();
                loadPasswords();
                loadGlobalPasswords();
                displayActionTakenResult('Save Entry', result.message, true);
            } else {
                displayActionTakenResult('Save Entry', 'Failed to save entry: ' + result.message, false);
            }
        });
    }
    
    if (createNewPasswordBtn) {
        createNewPasswordBtn.addEventListener('click', () => {
            passwordForm.reset();
            passwordEntryId.value = '';
            passwordModalLabel.textContent = 'Create New Password Entry';
            document.getElementById('passwordOwner').value = ''; 
            parentIdDropdown.value = '';
            parentIdDropdown.disabled = false;
            entryTypeDropdown.value = 'credential';
            entryTypeDropdown.disabled = false;
            populateParentDropdown(currentPasswordEntries);
            passwordModal.show();
        });
    }

    if (savePasswordEntryBtn) savePasswordEntryBtn.addEventListener('click', handleFormSubmit);

    document.addEventListener('click', function(e) {
        const target = e.target;
        const visBtn = target.closest('#passwordValue ~ .toggle-vis-btn');
        if (visBtn) {
            const input = document.getElementById('passwordValue');
            const icon = visBtn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
            return;
        }
        const copyBtn = target.closest('#passwordValue ~ .copy-btn');
        if (copyBtn) {
            const input = document.getElementById('passwordValue');
            const icon = copyBtn.querySelector('i');
            navigator.clipboard.writeText(input.value).then(() => {
                icon.className = 'fas fa-check';
                setTimeout(() => { icon.className = 'fas fa-copy'; }, 1500);
            });
        }
    });

    passwordTableBody.addEventListener('click', function(e) {
        const target = e.target;
        const row = target.closest('tr');
        if (!row) return;

        if (target.closest('.tree-toggle')) {
            // UI: tree expand/collapse | Purpose: toggle password tree row visibility + chevron icon
            const toggleIcon = target.closest('.tree-toggle');
            toggleIcon.classList.toggle('fa-chevron-right');
            toggleIcon.classList.toggle('fa-chevron-down');
            const isCollapsing = toggleIcon.classList.contains('fa-chevron-right');
            const level = parseInt(row.dataset.level);
            let nextRow = row.nextElementSibling;
            while (nextRow && parseInt(nextRow.dataset.level) > level) {
                if (parseInt(nextRow.dataset.level) === level + 1) {
                    if (isCollapsing) {
                        nextRow.classList.add('hidden');
                        const childToggleIcon = nextRow.querySelector('.tree-toggle');
                        if (childToggleIcon && childToggleIcon.classList.contains('fa-chevron-down')) {
                            childToggleIcon.classList.remove('fa-chevron-down');
                            childToggleIcon.classList.add('fa-chevron-right');
                        }
                    } else nextRow.classList.remove('hidden');
                } else if (isCollapsing) nextRow.classList.add('hidden');
                nextRow = nextRow.nextElementSibling;
            }
        }
        if (target.closest('.toggle-vis-btn')) {
            // UI: password visibility toggle | Purpose: show/hide password text + eye icon swap
            const passwordTextSpan = row.querySelector('.password-text');
            const eyeIcon = target.closest('.toggle-vis-btn').querySelector('i');
            const actualPassword = passwordTextSpan.dataset.password;
            if (passwordTextSpan.textContent === '******') {
                passwordTextSpan.textContent = actualPassword;
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordTextSpan.textContent = '******';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
        if (target.closest('.copy-btn')) {
            const copyButton = target.closest('.copy-btn');
            const iconElement = copyButton.querySelector('i');
            const actualPassword = JSON.parse(row.dataset.entry).password;
            navigator.clipboard.writeText(actualPassword).then(() => {
                if (window.NocTooltip) NocTooltip.showTempText(copyButton, 'Copied!', 1500);
                iconElement.className = 'fas fa-check';
                setTimeout(() => {
                    iconElement.className = 'fas fa-copy';
                }, 1500);
            });
        }
        if (target.closest('.share-btn')) {
            const entry = JSON.parse(row.dataset.entry);
            const shareButton = target.closest('.share-btn');
            const confirmMsg = (shareButton.getAttribute('data-noc-tip') || '').includes('Unshare') ? `Unshare "${entry.system_name}" from global?` : `Share "${entry.system_name}" to global?`;
            if (confirm(confirmMsg)) {
                api.toggleShare(entry.id).then(result => {
                    if (result.success) {
                        loadPasswords(); loadGlobalPasswords();
                        displayActionTakenResult('Share Toggle', result.message, true);
                    } else displayActionTakenResult('Share Toggle', 'Error: ' + result.message, false);
                });
            }
        }
        if (target.closest('.edit-btn')) {
            const entry = JSON.parse(row.dataset.entry);
            passwordModalLabel.textContent = 'Edit Password Entry';
            passwordEntryId.value = entry.id;
            document.getElementById('passwordOwner').value = entry.owner;
            document.getElementById('passwordSystemName').value = entry.system_name;
            document.getElementById('passwordUrl').value = entry.url;
            document.getElementById('passwordUserId').value = entry.user_id;
            document.getElementById('passwordValue').value = entry.password;
            document.getElementById('passwordIp').value = entry.ip || '';
            document.getElementById('passwordRemarks').value = entry.remarks;
            populateParentDropdown(currentPasswordEntries, entry.id);
            parentIdDropdown.value = entry.parent_id || '';
            parentIdDropdown.disabled = false;
            entryTypeDropdown.value = entry.entry_type || 'credential';
            entryTypeDropdown.disabled = false;
            passwordModal.show();
        }
        if (target.closest('.delete-btn')) {
            const entry = JSON.parse(row.dataset.entry);
            if (confirm(`Delete "${entry.system_name}"?`)) {
                api.delete(entry.id, 'personal').then(result => {
                    if (result.success) { loadPasswords(); displayActionTakenResult('Delete Entry', result.message, true); }
                    else displayActionTakenResult('Delete Entry', 'Error: ' + result.message, false);
                });
            }
        }
    });

    if (globalPasswordTableBody) {
        globalPasswordTableBody.addEventListener('click', function(e) {
            const target = e.target;
            const row = target.closest('tr');
            if (!row) return;
            if (target.closest('.toggle-vis-btn')) {
                const passwordTextSpan = row.querySelector('.password-text');
                const eyeIcon = target.closest('.toggle-vis-btn').querySelector('i');
                const actualPassword = passwordTextSpan.dataset.password;
                if (passwordTextSpan.textContent === '******') { passwordTextSpan.textContent = actualPassword; eyeIcon.className = 'fas fa-eye-slash'; }
                else { passwordTextSpan.textContent = '******'; eyeIcon.className = 'fas fa-eye'; }
            }
            if (target.closest('.copy-btn')) {
                const copyButton = target.closest('.copy-btn');
                const iconElement = copyButton.querySelector('i');
                const actualPassword = JSON.parse(row.dataset.entry).password;
                let tooltip = bootstrap.Tooltip.getInstance(copyButton);
                if (!tooltip) tooltip = new bootstrap.Tooltip(copyButton, { title: 'Copy', placement: 'top', trigger: 'hover', container: 'body' });
                navigator.clipboard.writeText(actualPassword).then(() => {
                    tooltip.setContent({ '.tooltip-inner': 'Copied!' }); tooltip.show();
                    iconElement.className = 'fas fa-check';
                    setTimeout(() => { tooltip.hide(); tooltip.setContent({ '.tooltip-inner': 'Copy' }); iconElement.className = 'fas fa-copy'; }, 1500);
                });
            }
            if (target.closest('.edit-btn')) {
                const entry = JSON.parse(row.dataset.entry);
                passwordModalLabel.textContent = 'Edit Global Password Entry';
                passwordEntryId.value = entry.id;
                document.getElementById('passwordOwner').value = entry.owner;
                document.getElementById('passwordSystemName').value = entry.system_name;
                document.getElementById('passwordUrl').value = entry.url;
                document.getElementById('passwordUserId').value = entry.user_id;
                document.getElementById('passwordValue').value = entry.password;
                document.getElementById('passwordIp').value = entry.ip || '';
                document.getElementById('passwordRemarks').value = entry.remarks;
                parentIdDropdown.value = entry.parent_id || '';
                parentIdDropdown.disabled = true;
                entryTypeDropdown.value = entry.entry_type || 'credential';
                entryTypeDropdown.disabled = true;
                passwordModal.show();
            }
            if (target.closest('.delete-btn')) {
                const entry = JSON.parse(row.dataset.entry);
                if (confirm(`Delete global entry "${entry.system_name}"?`)) {
                    api.delete(entry.id, 'global').then(result => {
                        if (result.success) { loadGlobalPasswords(); displayActionTakenResult('Delete Global Entry', result.message, true); }
                        else displayActionTakenResult('Delete Global Entry', 'Error: ' + result.message, false);
                    });
                }
            }
        });
    }

    function escapeHtml(u) { return typeof u !== 'string' ? '' : u.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c])); }

    loadPasswords();
    loadGlobalPasswords();
};

// Initial call
window.initPasswordManager();
