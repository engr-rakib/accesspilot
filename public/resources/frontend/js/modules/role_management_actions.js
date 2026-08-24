// assets/js/role_management_actions.js

function getRoleApiBase() {
    const resolvedBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');
    return `${resolvedBaseUrl}/role.php`;
}

function buildRoleApiUrl(action, extraQuery = '') {
    const separator = extraQuery ? '&' : '';
    return `${getRoleApiBase()}?action=${encodeURIComponent(action)}${separator}${extraQuery}`;
}

function showRoleActionResult(title, result) {
    if (typeof displayApiResponse === 'function') {
        displayApiResponse(title, result);
        return;
    }

    const container = document.getElementById('actionTakenCardContainer');
    const titleSpan = document.getElementById('actionTakenTitle');
    const msgDisplay = document.getElementById('actionTakenMessageDisplay');
    if (!container || !titleSpan || !msgDisplay) {
        return;
    }

    container.style.display = 'block';
    container.classList.add('visible');
    titleSpan.textContent = title;
    msgDisplay.innerHTML = result.message || (result.success ? 'Success' : 'Failed');
    msgDisplay.className = result.success ? 'alert alert-success' : 'alert alert-danger';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function getAdminPageUrl(page, params = {}) {
    const appConfig = window.APP_CONFIG || {};
    const adminPageUrl = appConfig.adminPageUrl || 'index.php';
    const target = new URL(adminPageUrl, window.location.origin);

    if (page) {
        target.searchParams.set('page', page);
    }

    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && typeof value !== 'undefined') {
            target.searchParams.set(key, value);
        }
    });

    return target.pathname + target.search;
}

/**
 * Main initialization for Role Management table view
 */
window.initRoleManagement = function() {
    
    const rolesTableBody = document.getElementById('rolesTableBody');
    const createNewRoleBtn = document.getElementById('createNewRoleBtn');
    
    if (!rolesTableBody) return;

    // Handle Delete from table (Edit is now a direct link)
    rolesTableBody.addEventListener('click', async (e) => {
        const deleteButton = e.target.closest('.delete-role-btn');
        if (deleteButton) {
            const roleName = deleteButton.dataset.roleName;
            if (confirm(`Are you sure you want to delete the role '${roleName}'?`)) {
                try {
                    const response = await fetch(buildRoleApiUrl('delete_role'), {
                        method: 'POST', 
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ role_name: roleName }) 
                    });
                    const result = await response.json();
                    showRoleActionResult('Delete Role', result);
                    if (result.success) loadRoleData();
                } catch (error) { console.error(error); }
            }
        }
    });

    if (createNewRoleBtn) {
        createNewRoleBtn.onclick = (e) => {
            e.preventDefault();
            loadSPAPage(getAdminPageUrl('create_role'));
        };
    }

    const loadRoleData = async () => {
        try {
            const response = await fetch(buildRoleApiUrl('get_all_data', `_=${new Date().getTime()}`));
            const data = await response.json();
            if (data.success) {
                const perms = data.user_permissions || { can_edit: false, can_delete: false };
                renderRolesTable(data.roles, perms);
            }
        } catch (error) { console.error('Role Load Error:', error); }
    };

    const renderRolesTable = (roles, permissions) => {
        rolesTableBody.innerHTML = '';
        for (const roleName in roles) {
            const role = roles[roleName];
            const isProtected = roleName === 'core_admin' || roleName === 'View only';
            let buttons = '';
            
            if (permissions.can_edit) {
                buttons += `<a href="${getAdminPageUrl('edit_role', { role_name: roleName })}" class="btn btn-icon btn-primary btn-sm" title="Edit Role"><i class="fas fa-edit"></i></a>`;
            }
            if (permissions.can_delete) {
                buttons += `<button class="btn btn-icon btn-danger btn-sm delete-role-btn" data-role-name="${escapeHTML(roleName)}" ${isProtected ? 'disabled' : ''} title="Delete Role"><i class="fas fa-trash-alt"></i></button>`;
            }

            rolesTableBody.insertAdjacentHTML('beforeend', `
                <tr>
                    <td><strong>${escapeHTML(roleName)}</strong></td>
                    <td>${escapeHTML(role.description)}</td>
                    <td class="user-mgmt-action-cell" style="white-space: nowrap; width: 1%; min-width: 100px;">
                        <div class="user-mgmt-action-buttons">
                            ${buttons}
                        </div>
                    </td>
                </tr>`);
        }
    };

    loadRoleData();
};

/**
 * Initialization for Role Create/Edit Form View
 */
window.initRoleForm = function(config) {
    
    const form = document.getElementById('roleFormPage');
    const permissionsContainer = document.getElementById('permissionsContainer');
    const saveRoleBtn = document.getElementById('saveRoleBtn');
    
    if (!form || !permissionsContainer || form.dataset.initialized === 'true') return;
    form.dataset.initialized = 'true';

    // Use passed config OR read from data-config attribute (for SPA compatibility)
    let roleConfig = config;
    if (!roleConfig && form.dataset.config) {
        try {
            roleConfig = JSON.parse(form.dataset.config);
        } catch (e) {
            console.error('Failed to parse role form config from DOM:', e);
            return;
        }
    }

    if (!roleConfig) {
        console.error('Role Form Initialization failed: No config provided.');
        return;
    }

    let memberPermissions = {
        can_add_member: !!roleConfig.can_add_member,
        can_remove_member: !!roleConfig.can_remove_member
    };

    const loadPermissionsAndPopulate = async () => {
        try {
            const extraQueryParts = [`_=${new Date().getTime()}`];
            if (roleConfig.is_edit) {
                extraQueryParts.push(`role_name=${encodeURIComponent(roleConfig.role_name)}`);
            }
            const requestUrl = buildRoleApiUrl('get_all_data', extraQueryParts.join('&'));
            const response = await fetch(requestUrl);
            if (!response.ok) throw new Error('Failed to fetch data');
            const responseText = await response.text();
            let data;

            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error(`Role API returned invalid JSON. URL: ${requestUrl}. Response: ${responseText.slice(0, 500)}`);
            }
            
            if (data.success) {
                memberPermissions = {
                    can_add_member: !!(data.user_permissions && data.user_permissions.can_add_member),
                    can_remove_member: !!(data.user_permissions && data.user_permissions.can_remove_member)
                };
                renderPermissionTree(data.permissions, roleConfig.permissions);
                if (roleConfig.is_edit) {
                    renderMembersTable(data.members || []);
                    populateMemberSelect(data.non_members || []);
                }
            } else {
                permissionsContainer.innerHTML = `<div class="alert alert-danger">${escapeHTML(data.message || 'Error loading permissions.')}</div>`;
            }
        } catch (error) { 
            console.error('Permission Load Error:', error); 
            permissionsContainer.innerHTML = `<div class="alert alert-danger">Error loading permissions tree. Please check your connection or contact the administrator.</div>`;
        }
    };

    const renderMembersTable = (members) => {
        const tbody = document.getElementById('membersTableBody');
        if (!tbody) return;
        
        if (members.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No members assigned to this role.</td></tr>';
            return;
        }

        tbody.innerHTML = '';
        members.forEach(m => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHTML(m.username)}</td>
                <td>${escapeHTML(m.full_name)}</td>
                <td>${escapeHTML(m.email)}</td>
                <td class="text-end">
                    ${memberPermissions.can_remove_member ? `
                    <button type="button" class="btn btn-sm btn-outline-danger remove-member-btn" data-username="${escapeHTML(m.username)}">
                        <i class="fas fa-user-minus"></i>
                    </button>` : ''}
                </td>
            `;
            tbody.appendChild(tr);
        });

        if (!memberPermissions.can_remove_member) {
            return;
        }

        // Attach remove events
        tbody.querySelectorAll('.remove-member-btn').forEach(btn => {
            btn.onclick = async () => {
                const username = btn.dataset.username;
                if (confirm(`Are you sure you want to remove user '${username}' from this role?`)) {
                    try {
                        const res = await fetch(buildRoleApiUrl('remove_role_member'), {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ role_name: roleConfig.role_name, username })
                        });
                        const result = await res.json();
                        showRoleActionResult('Remove Member', result);
                        if (result.success) {
                            syncRoleMembers(result);
                        }
                    } catch (e) { console.error(e); }
                }
            };
        });
    };

    const populateMemberSelect = (nonMembers) => {
        const select = document.getElementById('memberSelect');
        if (!select) return;
        
        select.innerHTML = '<option value="">-- Select a User --</option>';
        nonMembers
            .sort((a, b) => String(a.username ?? '').localeCompare(String(b.username ?? '')))
            .forEach(m => {
            const opt = document.createElement('option');
            opt.value = String(m.username ?? '');
            opt.textContent = `${String(m.username ?? '')} (${String(m.full_name ?? '')})`;
            select.appendChild(opt);
            });
    };

    const syncRoleMembers = (result) => {
        if (!result || !Array.isArray(result.members) || !Array.isArray(result.non_members)) {
            loadPermissionsAndPopulate();
            return;
        }

        renderMembersTable(result.members);
        populateMemberSelect(result.non_members);
    };

    // Add Member UI Handlers
    const showAddMemberBtn = document.getElementById('showAddMemberBtn');
    const addMemberForm = document.getElementById('addMemberForm');
    const confirmAddMemberBtn = document.getElementById('confirmAddMemberBtn');

    if (showAddMemberBtn && addMemberForm) {
        showAddMemberBtn.onclick = () => {
            const isHidden = addMemberForm.style.display === 'none';
            addMemberForm.style.display = isHidden ? 'block' : 'none';
            showAddMemberBtn.innerHTML = isHidden ? '<i class="fas fa-times me-1"></i> Cancel' : '<i class="fas fa-user-plus me-1"></i> Add Member';
            showAddMemberBtn.className = isHidden ? 'btn btn-sm btn-outline-secondary ms-auto' : 'btn btn-sm btn-outline-success ms-auto';
        };
    }

    if (confirmAddMemberBtn) {
        confirmAddMemberBtn.onclick = async () => {
            const username = document.getElementById('memberSelect').value;
            if (!username) {
                alert('Please select a user first.');
                return;
            }

            confirmAddMemberBtn.disabled = true;
            confirmAddMemberBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const res = await fetch(buildRoleApiUrl('add_role_member'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ role_name: roleConfig.role_name, username })
                });
                const result = await res.json();
                showRoleActionResult('Add Member', result);
                if (result.success) {
                    addMemberForm.style.display = 'none';
                    showAddMemberBtn.innerHTML = '<i class="fas fa-user-plus me-1"></i> Add Member';
                    showAddMemberBtn.className = 'btn btn-sm btn-outline-success ms-auto';
                    syncRoleMembers(result);
                }
            } catch (e) { console.error(e); }
            finally {
                confirmAddMemberBtn.disabled = false;
                confirmAddMemberBtn.innerHTML = 'Add';
            }
        };
    }

    const renderPermissionTree = (tree, selectedPerms) => {
        permissionsContainer.innerHTML = '';
        if (!tree || typeof tree !== 'object') {
            throw new Error('Permission tree payload is missing or invalid.');
        }

        const preferredCategoryOrder = [
            'global_components',
            'page_ad_administration',
            'page_dashboard',
            'page_user_management',
            'page_password_manager',
            'page_role_management',
            'page_application_events',
            'page_monitoring',
            'page_system_config',
            'page_email_tools',
            'page_exchange',
            'page_license',
            'page_profile',
            'page_about_us',
            'page_documentation',
            'page_documentation_guide',
            'page_change_password',
            'page_employee_db'
        ];
        
        // Ensure selectedPerms is an array
        const perms = Array.isArray(selectedPerms) ? selectedPerms : [];
        const hasWildcard = perms.includes('*');

        const categoryKeys = [
            ...preferredCategoryOrder.filter(key => tree[key]),
            ...Object.keys(tree).filter(key => !preferredCategoryOrder.includes(key))
        ];

        for (const catKey of categoryKeys) {
            const categoryItems = Array.isArray(tree[catKey])
                ? tree[catKey].filter(item => item && typeof item === 'object')
                : [];

            if (categoryItems.length > 0) {
                const catName = typeof categoryItems[0].name === 'string' && categoryItems[0].name.length > 0
                    ? categoryItems[0].name
                    : catKey;
                const catDiv = document.createElement('div');
                catDiv.className = 'permission-category mb-3';
                catDiv.innerHTML = `<h4 class="border-bottom pb-2 mt-3">${escapeHTML(catName)}</h4>`;
                
                const ul = document.createElement('ul');
                ul.className = 'list-unstyled ps-3';
                categoryItems.forEach(item => {
                    const node = createPermissionNode(item, 0, perms, hasWildcard);
                    if (node) {
                        ul.appendChild(node);
                    }
                });
                
                catDiv.appendChild(ul);
                permissionsContainer.appendChild(catDiv);
            }
        }
    };

    const createPermissionNode = (item, level, selectedPerms, hasWildcard) => {
        if (!item || typeof item !== 'object') {
            return null;
        }

        const itemKey = typeof item.key === 'string' ? item.key : '';
        const itemName = typeof item.name === 'string' && item.name.length > 0
            ? item.name
            : itemKey || 'Unnamed Permission';

        const li = document.createElement('li');
        li.className = `permission-item mt-1`;
        const icon = item.icon ? `<i class="${escapeHTML(item.icon)} me-2 text-muted"></i>` : '';
        const isChecked = itemKey ? (hasWildcard || selectedPerms.includes(itemKey)) : false;
        const checkboxId = itemKey
            ? `perm_${escapeHTML(itemKey)}`
            : `perm_generated_${level}_${Math.random().toString(36).slice(2, 10)}`;
        
        li.innerHTML = `
            <div class="form-check">
                <input type="checkbox" id="${checkboxId}" value="${escapeHTML(itemKey)}" class="form-check-input" ${isChecked ? 'checked' : ''} ${roleConfig.role_name === 'core_admin' ? 'disabled' : ''} ${itemKey ? '' : 'data-invalid-permission="true"'}>
                <label class="form-check-label" for="${checkboxId}">${icon}${escapeHTML(itemName)}</label>
            </div>
        `;

        const childrenUl = document.createElement('ul');
        childrenUl.className = 'list-unstyled ps-4 border-start ms-2';
        
        const appendChildren = (items) => {
            if (!Array.isArray(items)) {
                return;
            }

            items.forEach(child => {
                const childNode = createPermissionNode(child, level + 1, selectedPerms, hasWildcard);
                if (childNode) {
                    childrenUl.appendChild(childNode);
                }
            });
        };

        appendChildren(item.cards);
        appendChildren(item.buttons);
        appendChildren(item.sub_actions);
        appendChildren(item.permissions);

        if (childrenUl.hasChildNodes()) li.appendChild(childrenUl);
        return li;
    };

    // Handle form submission
    form.onsubmit = async (e) => {
        e.preventDefault();
        
        const roleName = document.getElementById('roleName').value.trim();
        const selectedPermissions = [];
        permissionsContainer.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => selectedPermissions.push(cb.value));
        
        const roleData = {
            original_role_name: document.getElementById('roleNameHidden').value,
            role_name: roleName,
            description: document.getElementById('roleDescription').value,
            permissions: selectedPermissions
        };

        saveRoleBtn.disabled = true;
        saveRoleBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

        try {
            const response = await fetch(buildRoleApiUrl('save_role'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(roleData)
            });
            const result = await response.json();
            
            showRoleActionResult('Save Role', result);
            
            if (result.success) {
                setTimeout(() => {
                    loadSPAPage(getAdminPageUrl('user_management'));
                }, 1500);
            } else {
                saveRoleBtn.disabled = false;
                saveRoleBtn.innerHTML = `<i class="fas fa-save me-1"></i> ${roleConfig.is_edit ? 'Update Role' : 'Create Role'}`;
            }
        } catch (error) {
            console.error(error);
            saveRoleBtn.disabled = false;
            saveRoleBtn.innerHTML = `<i class="fas fa-save me-1"></i> ${roleConfig.is_edit ? 'Update Role' : 'Create Role'}`;
        }
    };

    // Cascade checkboxes
    permissionsContainer.onclick = (e) => {
        if (e.target.matches('input[type="checkbox"]')) {
            const item = e.target.closest('li');
            const childCheckboxes = item.querySelectorAll('ul input[type="checkbox"]');
            childCheckboxes.forEach(cb => cb.checked = e.target.checked);
        }
    };

    loadPermissionsAndPopulate();
};

function escapeHTML(str) {
    if (str === null || typeof str === 'undefined') return '';
    const p = document.createElement('p');
    p.textContent = str;
    return p.innerHTML;
}

// Initial call for table view if present
window.initRoleManagement();
