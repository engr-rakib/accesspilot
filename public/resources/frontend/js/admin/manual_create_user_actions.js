window.initManualCreateUser = function() {
    
    // Always re-find global UI elements to avoid "detached element" bugs after SPA updates
    window.actionTakenCardContainer = document.getElementById('actionTakenCardContainer');
    window.actionTakenTitleSpan = document.getElementById('actionTakenTitle');
    window.actionTakenIcon = document.getElementById('actionTakenIcon');
    window.actionTakenMessageDisplay = document.getElementById('actionTakenMessageDisplay');

    const resolvedBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');
    const getOusApiUrl = `${resolvedBaseUrl}/api/index.php?endpoint=get_ous`;
    const getGroupsApiUrl = `${resolvedBaseUrl}/api/index.php?endpoint=get_groups`;
    const getGroupMembersApiBaseUrl = `${resolvedBaseUrl}/api/index.php?endpoint=get_group_members`;
    const updateGroupMembersApiUrl = `${resolvedBaseUrl}/api/index.php?endpoint=update_group_members`;
    const createDirectoryObjectApiUrl = `${resolvedBaseUrl}/api/index.php?endpoint=create_directory_object`;
    const deleteDirectoryObjectApiUrl = `${resolvedBaseUrl}/api/index.php?endpoint=delete_directory_object`;
    
    // Core Buttons
    const manualCreateButton = document.getElementById('ADmanualUserCreateButton');
    const modifyUserButton = document.getElementById('ADmodifyUserButton');
    const directoryBuilderButton = document.getElementById('ADdirectoryBuilderButton');
    const mainUsernameInput = document.getElementById('username');

    // Manual Create Form Elements
    const manualCreateFormContainerRow = document.getElementById('manualCreateFormContainerRow');
    const submitManualCreateButton = document.getElementById('submitManualCreate');
    const manualCreateFormTitle = document.getElementById('manualCreateFormTitle');
    const manualUsernameInput = document.getElementById('manualUsername');
    const originalUsernameInput = document.getElementById('originalUsername');
    const manualDisplayNameInput = document.getElementById('manualDisplayName');
    const manualDescriptionInput = document.getElementById('manualDescription');
    const manualOUDisplay = document.getElementById('manualOUDisplay');
    const manualOUInput = document.getElementById('manualOU');
    const manualOUDropdown = document.getElementById('manualOUDropdown');
    const manualOUList = document.getElementById('manualOUList');
    const manualGroupMemberDisplay = document.getElementById('manualGroupMemberDisplay');
    const manualGroupMemberDropdown = document.getElementById('manualGroupMemberDropdown');
    const manualGroupMemberList = document.getElementById('manualGroupMemberList');
    const selectedGroupMembersTagsContainer = document.getElementById('selectedGroupMembersTags');
    const cancelManualCreateButton = document.getElementById('cancelManualCreateButton');

    // Direct. (OU/Group Manager) Form Elements
    const directoryBuilderFormContainerRow = document.getElementById('directoryBuilderFormContainerRow');
    const directoryObjectTypeSelect = document.getElementById('directoryObjectType');
    const directoryObjectNameInput = document.getElementById('directoryObjectName');
    const directoryObjectDescription = document.getElementById('directoryObjectDescription');
    const directoryParentOUDisplay = document.getElementById('directoryParentOUDisplay');
    const directoryParentOUInput = document.getElementById('directoryParentOU');
    const directoryParentOUDropdown = document.getElementById('directoryParentOUDropdown');
    const directoryParentOUList = document.getElementById('directoryParentOUList');
    const directoryBuilderInlineStatus = document.getElementById('directoryBuilderInlineStatus');
    const createFieldsContainer = document.getElementById('createFieldsContainer');
    const submitDirectoryBuilderButton = document.getElementById('submitDirectoryBuilder');
    const submitDirectoryDeleter = document.getElementById('submitDirectoryDeleter');
    const cancelDirectoryBuilderFooterButton = document.getElementById('cancelDirectoryBuilderFooterButton');

    // Manage Mode Elements
    const modeCreateRadio = document.getElementById('modeCreate');
    const modeManageRadio = document.getElementById('modeManage');
    const modeDeleteRadio = document.getElementById('modeDelete');
    const createModeSection = document.getElementById('createModeSection');
    const manageModeSection = document.getElementById('manageModeSection');
    const deleteModeSection = document.getElementById('deleteModeSection');
    
    const manageGroupTargetDisplay = document.getElementById('manageGroupTargetDisplay');
    const manageGroupTargetDN = document.getElementById('manageGroupTargetDN');
    const manageGroupTargetDropdown = document.getElementById('manageGroupTargetDropdown');
    const manageGroupTargetList = document.getElementById('manageGroupTargetList');
    const manageGroupMembersArea = document.getElementById('manageGroupMembersArea');
    const manageGroupNewMemberInput = document.getElementById('manageGroupNewMemberInput');
    const btnManageGroupAddMember = document.getElementById('btnManageGroupAddMember');
    const manageGroupMembersTableBody = document.getElementById('manageGroupMembersTableBody');
    const submitGroupManagerUpdate = document.getElementById('submitGroupManagerUpdate');

    // --- Dropdown toggling: workspace scroll overflow override for absolute dropdown ---
    let _ddCount = 0;

    function setWsOverflow(open) {
        const ws = document.querySelector('.workspace-content-scroll');
        const sw = document.querySelector('.shell-workspace');
        const card = document.querySelector('.overflow-visible-card');
        if (!ws) return;
        _ddCount = Math.max(0, _ddCount + (open ? 1 : -1));
        if (_ddCount > 0) {
            ws.style.setProperty('overflow-y', 'visible', 'important');
            ws.style.setProperty('overflow-x', 'visible', 'important');
            if (sw) sw.style.setProperty('overflow', 'visible', 'important');
            if (card) card.style.setProperty('overflow', 'visible', 'important');
        } else {
            ws.style.removeProperty('overflow-y');
            ws.style.removeProperty('overflow-x');
            if (sw) sw.style.removeProperty('overflow');
            if (card) card.style.removeProperty('overflow');
        }
    }

    function openDropdownAbs(dropdown) {
        if (!dropdown) return;
        dropdown.style.display = 'block';
        var rect = dropdown.getBoundingClientRect();
        var bottomSpace = window.innerHeight - rect.top;
        var rowLimit = 10 * 36;
        dropdown.style.maxHeight = Math.min(rowLimit, Math.max(120, bottomSpace - 20)) + 'px';
    }
    function closeDropdown(dropdown) {
        if (dropdown) dropdown.style.display = 'none';
        setWsOverflow(false);
    }

    // Shared Cache
    if (!window._adTreeCache) window._adTreeCache = { unified: [] };
    if (!window._selectedGroupsForManualCreate) window._selectedGroupsForManualCreate = [];
    if (!window._manualCreateMode) window._manualCreateMode = 'create';
    let currentManagedGroupMembers = [];

    function showLoading(element) {
        if (typeof window.showLoadingAnimation === 'function') {
            window.showLoadingAnimation(element);
        } else {
            element.innerHTML = 'Loading...';
        }
    }

    function clearManualCreateForm() {
        if (manualUsernameInput) manualUsernameInput.value = '';
        if (originalUsernameInput) originalUsernameInput.value = '';
        if (manualDisplayNameInput) manualDisplayNameInput.value = '';
        if (manualDescriptionInput) manualDescriptionInput.value = '';
        if (manualOUDisplay) manualOUDisplay.value = '';
        if (manualOUInput) manualOUInput.value = '';
        window._selectedGroupsForManualCreate = [];
        renderSelectedGroupTags();
        if (manualOUDropdown) closeDropdown(manualOUDropdown);
        if (manualGroupMemberDropdown) closeDropdown(manualGroupMemberDropdown);

        const pwdSection = document.getElementById('modifyPasswordSection');
        if (pwdSection) pwdSection.style.display = 'none';
        const resetChk = document.getElementById('modifyResetPassword');
        if (resetChk) resetChk.checked = false;
        const pwdOpts = document.getElementById('modifyPasswordOptions');
        if (pwdOpts) pwdOpts.style.display = 'none';
        const tempPwd = document.getElementById('modifyTemporaryPassword');
        if (tempPwd) tempPwd.value = '';
        const forceChk = document.getElementById('modifyForcePasswordChange');
        if (forceChk) forceChk.checked = true;
        const defPwdChk = document.getElementById('modifyUseDefaultPassword');
        if (defPwdChk) defPwdChk.checked = false;

        const svcSection = document.getElementById('serviceAccountSection');
        if (svcSection) svcSection.style.display = 'none';
        const svcFields = document.getElementById('serviceAccountFields');
        if (svcFields) svcFields.style.display = 'none';
        const svcCheck = document.getElementById('serviceAccountCheck');
        if (svcCheck) svcCheck.checked = false;
        const svcOp = document.getElementById('serverOperation');
        if (svcOp) svcOp.value = '';
        const svcPwdNever = document.getElementById('servicePwdNeverExpires');
        if (svcPwdNever) svcPwdNever.checked = true;

        const mbSection = document.getElementById('modifyMailboxSection');
        if (mbSection) mbSection.style.display = 'none';
        const mbHas = document.getElementById('mailboxHasMailbox');
        if (mbHas) mbHas.style.display = 'none';
        const mbNone = document.getElementById('mailboxNoMailbox');
        if (mbNone) mbNone.style.display = 'none';
        const exIdentity = document.getElementById('exchangeIdentitySection');
        if (exIdentity) exIdentity.style.display = 'none';
        const stdFields = document.getElementById('standardFormFields');
        if (stdFields) stdFields.style.display = '';
    }

    // --- 1. CORE ACTION LISTENERS --- 

    if (manualCreateButton) {
        manualCreateButton.addEventListener('click', () => {
            const row = manualCreateFormContainerRow;
            const alreadyOpenCreate = row && (row.style.display === 'block' || row.classList.contains('visible'));
            if (!alreadyOpenCreate) {
                // Fresh open: reset previous / modify-mode data first.
                clearManualCreateForm();
                window._manualCreateMode = 'create';
                if (manualCreateFormTitle) manualCreateFormTitle.innerHTML = '<i class="fas fa-user-plus"></i> Manual User Creation';
                if (submitManualCreateButton) submitManualCreateButton.innerHTML = '<i class="fas fa-user-check"></i> Create User Manually';
                const svcSection = document.getElementById('serviceAccountSection');
                if (svcSection) svcSection.style.display = 'block';
                syncMailboxCheckboxDomain();
                fetchUnifiedTree();
            } else {
                // Already open in create mode: do NOT wipe typed data — just focus back.
                row.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }
            if (row) { row.classList.add('visible'); row.style.display = 'block'; }
        });
    }
    if (modifyUserButton) {
        modifyUserButton.addEventListener('click', () => {
            const username = mainUsernameInput?.value.trim();
            if (!username) {
                // EFFECT: shake animation | Purpose: validation feedback for empty username
                mainUsernameInput?.classList.add('shake'); setTimeout(() => mainUsernameInput?.classList.remove('shake'), 500); return; }
            fetchAndPopulateUserData(username);
        });
    }

    // --- Service Account toggle ---
    const svcCheckEl = document.getElementById('serviceAccountCheck');
    const manualUsernameInputLocal = document.getElementById('manualUsername');
    if (svcCheckEl) {
        svcCheckEl.addEventListener('change', () => {
            const svcFields = document.getElementById('serviceAccountFields');
            if (svcFields) svcFields.style.display = svcCheckEl.checked ? 'block' : 'none';
            const unameInput = document.getElementById('manualUsername');
            if (svcCheckEl.checked && unameInput && !unameInput.value.startsWith('svc_')) {
                unameInput.value = 'svc_' + unameInput.value;
            } else if (!svcCheckEl.checked && unameInput && unameInput.value.startsWith('svc_')) {
                unameInput.value = unameInput.value.substring(4);
            }
            // Auto-fill OU to Service Accounts if available in the OU tree
            const svcOuInput = document.getElementById('manualOU');
            const svcOuDisplay = document.getElementById('manualOUDisplay');
            if (svcCheckEl.checked && svcOuInput && svcOuDisplay) {
                const svcOuOption = window._adTreeCache?.unified?.find(o => o.Name === 'Service Accounts' || o.Name === 'ServiceAccount' || (o.DistinguishedName && o.DistinguishedName.match(/OU=Service\s*Accounts/i)));
                if (svcOuOption && svcOuOption.DistinguishedName) {
                    svcOuInput.value = svcOuOption.DistinguishedName;
                    svcOuDisplay.value = svcOuOption.DistinguishedName.replace(/,DC=/g, ' > DC=').replace(/OU=/g, '').replace(/,/g, ' > ');
                }
            }
        });
    }

    // --- Password Reset toggle in modify mode ---
    const modifyResetPwdChk = document.getElementById('modifyResetPassword');
    if (modifyResetPwdChk) {
        modifyResetPwdChk.addEventListener('change', () => {
            const pwdOpts = document.getElementById('modifyPasswordOptions');
            if (pwdOpts) pwdOpts.style.display = modifyResetPwdChk.checked ? 'block' : 'none';
        });
    }

    // --- Mailbox save/create event listeners ---
    const mbAddProxyBtn = document.getElementById('mbAddProxyBtn');
    if (mbAddProxyBtn) {
        mbAddProxyBtn.addEventListener('click', () => {
            const input = document.getElementById('mbEditNewProxy');
            if (!input || !input.value.trim()) return;
            const proxy = input.value.trim();
            if (!window._pendingProxies) window._pendingProxies = [];
            window._pendingProxies.push(proxy);
            input.value = '';
            renderProxyBadges(window._pendingProxies);
        });
    }
    const mbSaveChangesBtn = document.getElementById('mbSaveChangesBtn');
    if (mbSaveChangesBtn) {
        mbSaveChangesBtn.addEventListener('click', saveMailboxChanges);
    }
    const mbCreateBtn = document.getElementById('mbCreateBtn');
    if (mbCreateBtn) {
        mbCreateBtn.addEventListener('click', createMailbox);
    }

    // (Distribution group event listeners removed — use Group Members section)

    if (submitManualCreateButton) {
        submitManualCreateButton.addEventListener('click', async () => {
            const endpoint = window._manualCreateMode === 'create' ? 'manual_create_user' : 'modify_ad_user';
            const username = manualUsernameInput.value.trim();
            const displayName = manualDisplayNameInput.value.trim();
            const ou = manualOUInput.value.trim();
            const description = manualDescriptionInput.value.trim();
            const originalUsername = originalUsernameInput.value.trim();
            const groupMembers = window._selectedGroupsForManualCreate.map(g => g.DistinguishedName).join(';');
            const ouDisplay = manualOUDisplay ? manualOUDisplay.value.trim() : '';

            // Required: username + display name always; OU must resolve to a DN.
            if (!username || !displayName || (!ou && !ouDisplay)) {
                alert('Please fill in all required fields (Username, Display Name, and OU).');
                return;
            }

            let resolvedOu = ou;
            if (!resolvedOu && ouDisplay) {
                try {
                    const r = await fetch(`${resolvedBaseUrl}/api/index.php?endpoint=get_ous`, { method: 'GET' });
                    const j = await r.json();
                    const list = (j && j.ous) || [];
                    const hit = list.find(o => (o.Name && o.Name === ouDisplay) || (o.DistinguishedName && o.DistinguishedName === ouDisplay));
                    if (hit && hit.DistinguishedName) {
                        resolvedOu = hit.DistinguishedName;
                        manualOUInput.value = resolvedOu;
                    }
                } catch (e) { /* fall through */ }
                if (!resolvedOu) {
                    alert('Could not resolve the OU "' + ouDisplay + '". Please select it from the OU dropdown.');
                    if (manualOUDisplay) manualOUDisplay.focus();
                    return;
                }
            }

            submitManualCreateButton.disabled = true;
            if (window.actionTakenCardContainer) {
                window.actionTakenCardContainer.classList.add('visible');
                if (window.actionTakenMessageDisplay) window.showLoadingAnimation(window.actionTakenMessageDisplay);
                window.actionTakenMessageDisplay.className = 'alert alert-info';
            }

            const svcCheck = document.getElementById('serviceAccountCheck');
            const svcOpInput = document.getElementById('serverOperation');
            const svcPwdNever = document.getElementById('servicePwdNeverExpires');
            const enableMailboxEl = document.getElementById('enableMailboxCheck');
            const isSvc = svcCheck?.checked ? 'true' : 'false';
            const svcOp = svcOpInput?.value.trim() || '';
            const svcPwdNeverVal = svcPwdNever?.checked ? 'true' : 'false';
            const exchangeOff = window._exchangeEnabled === false;
            const enableMailboxVal = (!exchangeOff && enableMailboxEl?.checked) ? 'true' : 'false';
            let body = `username=${encodeURIComponent(username)}&displayName=${encodeURIComponent(displayName)}&ou=${encodeURIComponent(resolvedOu)}&description=${encodeURIComponent(description)}&manualGroupMembers=${encodeURIComponent(groupMembers)}&isServiceAccount=${encodeURIComponent(isSvc)}&serverOperation=${encodeURIComponent(svcOp)}&passwordNeverExpires=${encodeURIComponent(svcPwdNeverVal)}&enable_mailbox=${encodeURIComponent(enableMailboxVal)}`;
            if (window._manualCreateMode === 'modify') {
                const resetPwd = document.getElementById('modifyResetPassword');
                const forceChange = document.getElementById('modifyForcePasswordChange');
                const tempPwd = document.getElementById('modifyTemporaryPassword');
                const useDefault = document.getElementById('modifyUseDefaultPassword');
                const pwdMustChange = document.getElementById('modifyPwdMustChange');
                const pwdCantChange = document.getElementById('modifyPwdCantChange');
                const pwdNeverExpires = document.getElementById('modifyPwdNeverExpires');
                const resetPasswordVal = resetPwd?.checked ? 'true' : 'false';
                const forcePasswordChangeVal = forceChange?.checked ? 'true' : 'false';
                const tempPasswordVal = tempPwd ? tempPwd.value.trim() : '';
                const useDefaultVal = useDefault?.checked ? 'true' : 'false';
                const pwdMustChangeVal = pwdMustChange?.checked ? 'true' : 'false';
                const pwdCantChangeVal = pwdCantChange?.checked ? 'true' : 'false';
                const pwdNeverExpiresVal = pwdNeverExpires?.checked ? 'true' : 'false';
                // Mailbox fields for modify mode
                const mb = window._lastFetchedUserData?.exchange_mailbox || {};
                const modifyEnableMailbox = document.getElementById('modifyEnableMailbox');
                const modifyMailboxAlias = document.getElementById('modifyMailboxAlias');
                const mbEnableVal = (!mb.has_mailbox && modifyEnableMailbox?.checked) ? 'true' : 'false';
                const mbAliasVal = (!mb.has_mailbox && modifyMailboxAlias?.value.trim()) ? modifyMailboxAlias.value.trim() : '';
                // Org fields
                const exTitle = document.getElementById('exTitle')?.value.trim() || '';
                const exDepartment = document.getElementById('exDepartment')?.value.trim() || '';
                const exCompany = document.getElementById('exCompany')?.value.trim() || '';
                const exOffice = document.getElementById('exOffice')?.value.trim() || '';
                const exPhone = document.getElementById('exPhone')?.value.trim() || '';
                body = `originalUsername=${encodeURIComponent(originalUsername)}&newUsername=${encodeURIComponent(username)}&displayName=${encodeURIComponent(displayName)}&ou=${encodeURIComponent(ou)}&description=${encodeURIComponent(description)}&manualGroupMembers=${encodeURIComponent(groupMembers)}&resetPassword=${encodeURIComponent(resetPasswordVal)}&forcePasswordChange=${encodeURIComponent(forcePasswordChangeVal)}&temporaryPassword=${encodeURIComponent(tempPasswordVal)}&useDefaultPassword=${encodeURIComponent(useDefaultVal)}&pwdMustChange=${encodeURIComponent(pwdMustChangeVal)}&pwdCantChange=${encodeURIComponent(pwdCantChangeVal)}&pwdNeverExpires=${encodeURIComponent(pwdNeverExpiresVal)}&enable_mailbox=${encodeURIComponent(mbEnableVal)}&mailboxAlias=${encodeURIComponent(mbAliasVal)}&title=${encodeURIComponent(exTitle)}&department=${encodeURIComponent(exDepartment)}&company=${encodeURIComponent(exCompany)}&physicalDeliveryOfficeName=${encodeURIComponent(exOffice)}&telephoneNumber=${encodeURIComponent(exPhone)}`;
            }

            fetch(`${resolvedBaseUrl}/api/index.php?endpoint=${endpoint}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(r => r.json())
            .then(data => {
                if (window.actionTakenCardContainer) {
                    window.actionTakenMessageDisplay.innerHTML = styleFeedbackMessage(data.message);
                    window.actionTakenMessageDisplay.className = `alert alert-${data.success ? 'success' : 'danger'}`;
                    if (typeof autoHideActionCard === 'function') autoHideActionCard();
                }
                submitManualCreateButton.disabled = false;
                if (data.success && window._manualCreateMode === 'create') {
                    clearManualCreateForm();
                    if (typeof window.refreshInfoCards === 'function') window.refreshInfoCards(username);
                }
            })
            .catch(err => {
                if (window.actionTakenMessageDisplay) {
                    window.actionTakenMessageDisplay.innerHTML = 'Error communicating with server.';
                    window.actionTakenMessageDisplay.className = 'alert alert-danger';
                }
                submitManualCreateButton.disabled = false;
            });
        });
    }

    if (directoryBuilderButton) {
        directoryBuilderButton.addEventListener('click', () => {
            if (directoryBuilderFormContainerRow) { directoryBuilderFormContainerRow.classList.add('visible'); directoryBuilderFormContainerRow.style.display = 'block'; }
            fetchUnifiedTree();
        });
    }

    // --- 2. DIRECT. MANAGER LOGIC ---

    if (directoryObjectTypeSelect) {
        directoryObjectTypeSelect.addEventListener('change', () => {
            const val = directoryObjectTypeSelect.value;
            const isGroup = val === 'Group';
            const isValid = val === 'OU' || val === 'Group';
            const lblParent = document.getElementById('lblParentOU');
            const lblName = document.getElementById('lblObjectName');
            if (lblParent) lblParent.textContent = isGroup ? 'Select Parent OU for New Group' : 'Select Parent OU';
            if (lblName) lblName.textContent = isGroup ? 'New Group Name' : 'New OU Name';
            if (createFieldsContainer) createFieldsContainer.style.display = isValid ? 'flex' : 'none';
            if (submitDirectoryBuilderButton) {
                submitDirectoryBuilderButton.style.display = isValid ? 'inline-flex' : 'none';
                submitDirectoryBuilderButton.innerHTML = isGroup ? '<i class="fas fa-plus-circle me-1"></i>Create Security Group' : '<i class="fas fa-plus-circle me-1"></i>Create Directory Object';
            }
        });
    }

    function resetDirectoryBuilderFields() {
        if (directoryObjectTypeSelect) directoryObjectTypeSelect.value = '';
        if (createFieldsContainer) createFieldsContainer.style.display = 'none';
        if (directoryObjectNameInput) directoryObjectNameInput.value = '';
        if (directoryObjectDescription) directoryObjectDescription.value = '';
        if (directoryParentOUDisplay) directoryParentOUDisplay.value = '';
        if (directoryParentOUInput) directoryParentOUInput.value = '';
        if (manageGroupTargetDisplay) { manageGroupTargetDisplay.value = ''; manageGroupTargetDN.value = ''; }
        if (manageGroupMembersArea) manageGroupMembersArea.style.display = 'none';
        if (document.getElementById('deleteTargetDisplay')) document.getElementById('deleteTargetDisplay').value = '';
        if (document.getElementById('deleteTargetDN')) document.getElementById('deleteTargetDN').value = '';
        if (document.getElementById('deleteTargetType')) document.getElementById('deleteTargetType').value = '';
        const safetyCheck = document.getElementById('deleteSafetyCheck');
        if (safetyCheck) safetyCheck.checked = false;
        if (submitDirectoryBuilderButton) { submitDirectoryBuilderButton.style.display = 'none'; }
        if (submitGroupManagerUpdate) submitGroupManagerUpdate.style.display = 'none';
        if (submitDirectoryDeleter) { submitDirectoryDeleter.style.display = 'none'; submitDirectoryDeleter.disabled = true; }
    }

    if (modeCreateRadio && modeDeleteRadio) {
        const modeMessages = {
            create: 'Create a new Organizational Unit (OU) or Security Group in Active Directory.',
            manage: 'Manage group membership — add or remove users and nested groups.',
            delete: 'Permanently remove an OU or Security Group. This action is irreversible.'
        };
        [modeCreateRadio, modeManageRadio, modeDeleteRadio].forEach(radio => {
            radio?.addEventListener('change', () => {
                const isDelete = modeDeleteRadio.checked;
                const isManage = modeManageRadio?.checked;
                const isCreate = modeCreateRadio?.checked;
                
                resetDirectoryBuilderFields();
                
                if (createModeSection) createModeSection.style.display = isCreate ? 'block' : 'none';
                if (manageModeSection) manageModeSection.style.display = isManage ? 'block' : 'none';
                if (deleteModeSection) deleteModeSection.style.display = isDelete ? 'block' : 'none';
                
                // All action buttons hidden by default — only Close visible
                // They appear dynamically based on user interaction
                
                if (directoryBuilderInlineStatus) {
                    const msg = isCreate ? modeMessages.create : isManage ? modeMessages.manage : modeMessages.delete;
                    directoryBuilderInlineStatus.textContent = msg;
                    directoryBuilderInlineStatus.className = 'alert alert-' + (isDelete ? 'danger' : 'info') + ' mb-3';
                    directoryBuilderInlineStatus.style.display = 'block';
                }
            });
        });
    }

    // Safety Check Listener
    const deleteSafetyCheck = document.getElementById('deleteSafetyCheck');
    if (deleteSafetyCheck) {
        deleteSafetyCheck.addEventListener('change', () => {
            const hasTarget = document.getElementById('deleteTargetDN')?.value;
            if (submitDirectoryDeleter) {
                if (deleteSafetyCheck.checked && hasTarget) {
                    submitDirectoryDeleter.style.display = 'inline-flex';
                    submitDirectoryDeleter.disabled = false;
                } else {
                    submitDirectoryDeleter.style.display = 'none';
                    submitDirectoryDeleter.disabled = true;
                }
            }
        });
    }

    // --- 3. UNIFIED TREE ENGINE ---
    // (shared via window.adTreeDropdown for use by export_user_dropdowns.js and others)

    window.adTreeDropdown = window.adTreeDropdown || {};

    function filterTree(items, term) {
        if (!term) return items;
        const filtered = [];
        const lowerTerm = term.toLowerCase();
        for (const item of items) {
            const newItem = { ...item, children: [] };
            if (item.children && item.children.length > 0) newItem.children = filterTree(item.children, term);
            if (item.Name.toLowerCase().includes(lowerTerm) || newItem.children.length > 0) {
                newItem.shouldExpand = true;
                filtered.push(newItem);
            }
        }
        return filtered;
    }

    function prependClearItem(list, onSelect) {
        if (!list) return;
        const existing = list.querySelector('.clear-selection-item');
        if (existing) return;
        const li = document.createElement('li');
        li.className = 'tree-item clear-selection-item';
        const div = document.createElement('div');
        div.className = 'custom-select-item';
        div.innerHTML = '<i class="fas fa-times me-2" style="color:#dc3545;font-size:0.8rem;"></i><span class="text-muted">Clear selection</span>';
        div.addEventListener('click', (e) => { e.stopPropagation(); if (onSelect) onSelect({ clear: true }); });
        li.appendChild(div);
        list.insertBefore(li, list.firstChild);
    }

    function fetchUnifiedTree(targetList = null, onSelect = null, selectableTypes = ['OU', 'Group', 'Domain'], term = '') {
        if (window._adTreeCache.unified.length > 0) {
            const filtered = filterTree(window._adTreeCache.unified, term);
            if (targetList) { renderTree(filtered, targetList, onSelect, selectableTypes); prependClearItem(targetList, onSelect); }
            return;
        }
        if (targetList) {
            const clone = document.getElementById('fetchingPreview').content.cloneNode(true);
            targetList.innerHTML = '';
            const li = document.createElement('li');
            li.className = 'p-2';
            li.style.listStyle = 'none';
            li.appendChild(clone);
            targetList.appendChild(li);
        }
        Promise.all([fetch(getOusApiUrl).then(r => r.json()), fetch(getGroupsApiUrl).then(r => r.json())])
        .then(([ouData, groupData]) => {
            if (ouData.success && groupData.success) {
                const itemMap = {};
                [...ouData.ous, ...groupData.groups].forEach(item => {
                    const lowerDn = item.DistinguishedName.toLowerCase();
                    if (!itemMap[lowerDn] || (item.Type === 'Group' && itemMap[lowerDn].Type !== 'Group')) itemMap[lowerDn] = { ...item, children: [] };
                });
                const roots = [];
                Object.values(itemMap).forEach(item => {
                    if (item.Parent && itemMap[item.Parent.toLowerCase()]) itemMap[item.Parent.toLowerCase()].children.push(item);
                    else roots.push(item);
                });
                window._adTreeCache.unified = roots;
                const filtered = filterTree(roots, term);
                if (targetList) { renderTree(filtered, targetList, onSelect, selectableTypes); prependClearItem(targetList, onSelect); }
            } else {
                console.error('[AD Tree] API returned errors:', { ouData, groupData });
                const ouErr = ouData.success ? '' : (ouData.message || 'OU fetch failed');
                const grpErr = groupData.success ? '' : (groupData.message || 'Group fetch failed');
                const errors = [ouErr, grpErr].filter(Boolean).join(' | ');
                if (targetList) targetList.innerHTML = `<li class="p-2 text-danger small text-center">${errors}</li>`;
            }
        })
        .catch(err => {
            console.error('[AD Tree] Fetch network error:', err);
            if (targetList) targetList.innerHTML = '<li class="p-2 text-muted small text-center">Failed to load data.</li>';
        });
    }

    function renderTree(items, parentUl, onSelect, selectableTypes = ['OU', 'Group', 'Domain']) {
        if (!parentUl) return;
        if (items.length === 0) { parentUl.innerHTML = '<li class="p-2 text-muted small text-center">No results</li>'; return; }
        function hasSelectableRecursive(nodes) {
            return nodes.some(i => (selectableTypes && selectableTypes.includes(i.Type)) || (i.children && i.children.length > 0 && hasSelectableRecursive(i.children)));
        }
        if (selectableTypes && !hasSelectableRecursive(items)) {
            parentUl.innerHTML = `<li class="p-2 text-muted small text-center">No ${selectableTypes.join('/')} found</li>`;
            return;
        }
        parentUl.innerHTML = '';
        items.forEach(item => {
            const li = document.createElement('li');
            li.className = 'tree-item';
            const itemDiv = document.createElement('div');
            itemDiv.className = 'custom-select-item';
            const isSelectable = selectableTypes && selectableTypes.includes(item.Type);
            let icon = 'fa-sitemap'; let color = '#ffc107';
            if (item.Type === 'Group') { icon = 'fa-users'; color = '#183593'; }
            else if (item.Type === 'Domain') { icon = 'fa-globe'; color = '#64748b'; }
            const expandHTML = (item.children && item.children.length > 0) ? `<i class="fas ${item.shouldExpand ? 'fa-chevron-down' : 'fa-chevron-right'} toggle-icon me-1"></i>` : '<span style="display:inline-block; width:15px;"></span>';
            itemDiv.innerHTML = `${expandHTML}<i class="fas ${icon} me-2" style="color:${color}; font-size:0.8rem;"></i><span>${item.Name}</span>`;
            if (isSelectable) {
                itemDiv.addEventListener('click', (e) => { e.stopPropagation(); onSelect(item); });
            } else {
                itemDiv.classList.add('non-selectable');
                itemDiv.style.opacity = '0.4';
                itemDiv.addEventListener('click', (e) => { e.stopPropagation(); itemDiv.querySelector('.toggle-icon')?.click(); });
            }
            li.appendChild(itemDiv);
            parentUl.appendChild(li);
            if (item.children && item.children.length > 0) {
                const sub = document.createElement('ul');
                sub.className = 'tree-subtree';
                sub.style.display = item.shouldExpand ? 'block' : 'none';
                li.appendChild(sub);
                // UI: tree expand/collapse | Purpose: toggle chevron icon on folder expand
                itemDiv.querySelector('.toggle-icon')?.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const hidden = sub.style.display === 'none';
                    sub.style.display = hidden ? 'block' : 'none';
                    e.target.classList.toggle('fa-chevron-right', !hidden);
                    e.target.classList.toggle('fa-chevron-down', hidden);
                });
                renderTree(item.children, sub, onSelect, selectableTypes);
            }
        });
    }

    const bindSearch = (display, dropdown, list, types, onSelect) => {
        if (!display) return;
        if (dropdown && !dropdown._overflowObserved) {
            dropdown._overflowObserved = true;
            const obs = new MutationObserver(() => {
                if (dropdown.style.display === 'none') {
                    dropdown._ddOpen = false;
                    setWsOverflow(false);
                }
            });
            obs.observe(dropdown, { attributes: true, attributeFilter: ['style'] });
        }
        const openHandler = () => {
            if (!dropdown) return;
            openDropdownAbs(dropdown);
            if (!dropdown._ddOpen) {
                dropdown._ddOpen = true;
                setWsOverflow(true);
            }
            fetchUnifiedTree(list, onSelect, types, display.value);
        };
        display.addEventListener('focus', openHandler);
        display.addEventListener('input', openHandler);
        display.addEventListener('click', () => {
            if (dropdown && dropdown.style.display === 'none') openHandler();
        });
        display.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeDropdown(dropdown);
        });
    };

    // Expose shared tree engine for other modules
    window.adTreeDropdown.filterTree = filterTree;
    window.adTreeDropdown.fetchUnifiedTree = fetchUnifiedTree;
    window.adTreeDropdown.renderTree = renderTree;
    window.adTreeDropdown.bindSearch = bindSearch;
    window.adTreeDropdown.openDropdownAbs = openDropdownAbs;
    window.adTreeDropdown.closeDropdown = closeDropdown;
    window.adTreeDropdown.setWsOverflow = setWsOverflow;

    // Document click: close all dropdowns if click is outside their container
    if (!window._dropdownDocCloseAttached) {
        window._dropdownDocCloseAttached = true;
        document.addEventListener('click', (e) => {
            document.querySelectorAll('.custom-select-dropdown').forEach(dd => {
                if (dd.style.display !== 'none') {
                    const container = dd.closest('.custom-select-container');
                    if (container && !container.contains(e.target)) {
                        closeDropdown(dd);
                    }
                }
            });
        });
    }

    // --- 4. ATTACH SEARCHES ---
    bindSearch(manualOUDisplay, manualOUDropdown, manualOUList, ['OU', 'Domain'], i => { if (i.clear) { manualOUDisplay.value = ''; manualOUInput.value = ''; } else { manualOUDisplay.value = i.Name; manualOUInput.value = i.DistinguishedName; } manualOUDropdown.style.display = 'none'; });
    bindSearch(manualGroupMemberDisplay, manualGroupMemberDropdown, manualGroupMemberList, ['Group'], i => { if (i.clear) return; if (!window._selectedGroupsForManualCreate.some(g => g.DistinguishedName === i.DistinguishedName)) { window._selectedGroupsForManualCreate.push(i); renderSelectedGroupTags(); } });
    bindSearch(directoryParentOUDisplay, directoryParentOUDropdown, directoryParentOUList, ['OU', 'Domain'], i => { if (i.clear) { directoryParentOUDisplay.value = ''; directoryParentOUInput.value = ''; } else { directoryParentOUDisplay.value = i.Name; directoryParentOUInput.value = i.DistinguishedName; } directoryParentOUDropdown.style.display = 'none'; });
    
    // Manage Mode Search
    bindSearch(manageGroupTargetDisplay, manageGroupTargetDropdown, manageGroupTargetList, ['Group'], i => {
        if (i.clear) { manageGroupTargetDisplay.value = ''; manageGroupTargetDN.value = ''; manageGroupTargetDropdown.style.display = 'none'; if (submitGroupManagerUpdate) submitGroupManagerUpdate.style.display = 'none'; return; }
        manageGroupTargetDisplay.value = i.Name;
        manageGroupTargetDN.value = i.DistinguishedName;
        manageGroupTargetDropdown.style.display = 'none';
        if (submitGroupManagerUpdate) submitGroupManagerUpdate.style.display = 'inline-flex';
        loadGroupMembershipForManager(i.DistinguishedName);
    });

    bindSearch(document.getElementById('deleteTargetDisplay'), document.getElementById('deleteTargetDropdown'), document.getElementById('deleteTargetList'), ['OU', 'Group'], i => { 
        if (i.clear) { document.getElementById('deleteTargetDisplay').value = ''; document.getElementById('deleteTargetDN').value = ''; document.getElementById('deleteTargetType').value = ''; document.getElementById('deleteTargetDropdown').style.display = 'none'; if (submitDirectoryDeleter) submitDirectoryDeleter.style.display = 'none'; return; }
        const d = document.getElementById('deleteTargetDisplay');
        d.value = i.Name; document.getElementById('deleteTargetDN').value = i.DistinguishedName; document.getElementById('deleteTargetType').value = i.Type; document.getElementById('deleteTargetDropdown').style.display = 'none'; 
        if (submitDirectoryDeleter && document.getElementById('deleteSafetyCheck')?.checked) {
            submitDirectoryDeleter.style.display = 'inline-flex';
            submitDirectoryDeleter.disabled = false;
        }
    });

    // --- 5. MEMBERSHIP MANAGER LOGIC ---

    function loadGroupMembershipForManager(identity) {
        if (manageGroupMembersArea) {
            manageGroupMembersArea.style.removeProperty('display');
            manageGroupMembersArea.style.removeProperty('opacity');
            void manageGroupMembersArea.offsetHeight;
            manageGroupMembersArea.style.display = 'block';
            manageGroupMembersArea.style.opacity = '0';
            requestAnimationFrame(() => { manageGroupMembersArea.style.opacity = '1'; });
        }
        if (manageGroupMembersTableBody) {
            const clone = document.getElementById('fetchingPreview').content.cloneNode(true);
            manageGroupMembersTableBody.innerHTML = '';
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 4;
            td.style.textAlign = 'center';
            td.style.padding = '20px';
            td.style.border = 'none';
            td.appendChild(clone);
            tr.appendChild(td);
            manageGroupMembersTableBody.appendChild(tr);
        }
        
        fetch(`${getGroupMembersApiBaseUrl}&group=${encodeURIComponent(identity)}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    currentManagedGroupMembers = data.members.map(m => ({ ...m, status: 'existing' }));
                    
                    // Update Summary Counts
                    const total = data.members.length;
                    const users = data.members.filter(m => (m.ObjectClass || m.Type || '').toLowerCase() === 'user').length;
                    const groups = data.members.filter(m => (m.ObjectClass || m.Type || '').toLowerCase() === 'group').length;
                    
                    if (document.getElementById('managedGroupTotalCount')) document.getElementById('managedGroupTotalCount').textContent = total;
                    if (document.getElementById('managedGroupUsersCount')) document.getElementById('managedGroupUsersCount').textContent = users;
                    if (document.getElementById('managedGroupSubGroupsCount')) document.getElementById('managedGroupSubGroupsCount').textContent = groups;

                    renderManagedMembersTable();
                } else {
                    manageGroupMembersTableBody.innerHTML = `<tr><td colspan="4" class="text-danger">${data.message}</td></tr>`;
                }
            });
    }

    function renderManagedMembersTable() {
        if (!manageGroupMembersTableBody) return;
        manageGroupMembersTableBody.innerHTML = '';
        currentManagedGroupMembers.forEach((member, index) => {
            const row = document.createElement('tr');
            if (member.status === 'removed') row.style.opacity = '0.5';
            if (member.status === 'new') row.classList.add('table-success');

            const memberType = (member.ObjectClass || member.Type || 'Unknown').toLowerCase();
            const isUser = memberType === 'user';
            const isGroup = memberType === 'group';

            // Extract OU path from DN
            const dn = member.DistinguishedName || '';
            const ouMatch = dn.match(/OU=[^,]+(?:,OU=[^,]+)*,DC=[^,]+(?:,DC=[^,]+)*/i);
            const ouPath = ouMatch ? ouMatch[0] : 'Root';

            const modifyBtn = isUser ? `<button type="button" class="btn btn-icon btn-sm btn-outline-secondary" onclick="window.fetchAndPopulateUserData('${member.SamAccountName}')" title="Modify User"><i class="fas fa-user-edit"></i></button>` : '';
            const actionBtn = member.status === 'removed' ? 
                `<button type="button" class="btn btn-icon btn-sm btn-success" onclick="window.restoreMember(${index})" title="Restore"><i class="fas fa-undo"></i></button>` :
                `<button type="button" class="btn btn-icon btn-sm btn-danger" onclick="window.removeMember(${index})" title="Remove"><i class="fas fa-trash-alt"></i></button>`;

            row.innerHTML = `
                <td>
                    <div class="fw-bold">${member.Name || member.DisplayName || 'Unknown Member'}</div>
                    <div class="text-muted" style="font-size: 0.65rem; max-width: 350px; overflow: hidden; text-overflow: ellipsis;" title="${ouPath}">${ouPath}</div>
                </td>
                <td><code>${member.SamAccountName || member.Identifier || 'N/A'}</code></td>
                <td><span class="badge ${isGroup ? 'bg-info' : 'bg-secondary'}">${memberType.toUpperCase()}</span></td>
                <td class="text-end user-mgmt-action-cell">
                    <div class="app-action-buttons">
                        ${modifyBtn}
                        ${actionBtn}
                    </div>
                </td>
            `;
            row.style.animation = 'fadeIn 0.25s ease forwards';
            row.style.animationDelay = (index * 30) + 'ms';
            manageGroupMembersTableBody.appendChild(row);
        });
    }

    window.removeMember = (index) => {
        const member = currentManagedGroupMembers[index];
        if (member.status === 'new') currentManagedGroupMembers.splice(index, 1);
        else member.status = 'removed';
        renderManagedMembersTable();
    };

    window.restoreMember = (index) => {
        currentManagedGroupMembers[index].status = 'existing';
        renderManagedMembersTable();
    };

    if (btnManageGroupAddMember) {
        btnManageGroupAddMember.addEventListener('click', async () => {
            const val = manageGroupNewMemberInput.value.trim();
            if (!val) return;

            const idents = val.split(/[,\s]+/).filter(i => i.trim() !== '');
            if (idents.length === 0) return;

            btnManageGroupAddMember.disabled = true;
            const originalText = btnManageGroupAddMember.innerHTML;
            btnManageGroupAddMember.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            let resolvedCount = 0;
            let failedCount = 0;

            for (const ident of idents) {
                try {
                    const response = await fetch(`${resolvedBaseUrl}/api/index.php?endpoint=resolve_directory_principal&identity=${encodeURIComponent(ident)}`);
                    const data = await response.json();

                    if (data.success && data.member) {
                        const m = data.member;
                        const exists = currentManagedGroupMembers.some(existing => 
                            existing.DistinguishedName.toLowerCase() === m.DistinguishedName.toLowerCase() && 
                            existing.status !== 'removed'
                        );

                        if (!exists) {
                            currentManagedGroupMembers.push({
                                Name: m.DisplayName || m.Name,
                                Type: m.ObjectClass || m.Type,
                                SamAccountName: m.SamAccountName || m.Name,
                                DistinguishedName: m.DistinguishedName,
                                status: 'new'
                            });
                            resolvedCount++;
                        }
                    } else {
                        failedCount++;
                    }
                } catch (err) {
                    failedCount++;
                }
            }

            renderManagedMembersTable();
            manageGroupNewMemberInput.value = '';
            if (failedCount > 0) alert(`Batch complete. Added ${resolvedCount} members. Failed to resolve ${failedCount} entries.`);
            btnManageGroupAddMember.disabled = false;
            btnManageGroupAddMember.innerHTML = originalText;
        });
    }

    if (submitGroupManagerUpdate) {
        submitGroupManagerUpdate.addEventListener('click', () => {
            const identity = manageGroupTargetDN.value;
            const adds = currentManagedGroupMembers.filter(m => m.status === 'new').map(m => m.DistinguishedName);
            const removes = currentManagedGroupMembers.filter(m => m.status === 'removed').map(m => m.DistinguishedName);
            
            submitGroupManagerUpdate.disabled = true;
            if (window.actionTakenCardContainer) {
                window.actionTakenCardContainer.classList.add('visible');
                if (window.actionTakenMessageDisplay) {
                    window.showLoadingAnimation(window.actionTakenMessageDisplay);
                    window.actionTakenMessageDisplay.className = 'alert alert-info';
                }
            }

            fetch(updateGroupMembersApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `groupIdentity=${encodeURIComponent(identity)}&membersToAdd=${encodeURIComponent(adds.join(';'))}&membersToRemove=${encodeURIComponent(removes.join(';'))}`
            }).then(r => r.json()).then(data => {
                if (window.actionTakenCardContainer) {
                    window.actionTakenCardContainer.classList.add('visible');
                    window.actionTakenMessageDisplay.innerHTML = styleFeedbackMessage(data.message);
                    window.actionTakenMessageDisplay.className = `alert alert-${data.success ? 'success' : 'danger'}`;
                    if (typeof autoHideActionCard === 'function') autoHideActionCard();
                }
                submitGroupManagerUpdate.disabled = false;
                if (data.success) loadGroupMembershipForManager(identity);
            }).catch(() => {
                submitGroupManagerUpdate.disabled = false;
                if (window.actionTakenCardContainer) {
                    window.actionTakenMessageDisplay.innerHTML = 'Network error: could not reach the server.';
                    window.actionTakenMessageDisplay.className = 'alert alert-danger';
                }
            });
        });
    }

    // Submission: Create
    if (submitDirectoryBuilderButton) {
        submitDirectoryBuilderButton.addEventListener('click', () => {
            const body = `objectType=${encodeURIComponent(directoryObjectTypeSelect.value)}&objectName=${encodeURIComponent(directoryObjectNameInput.value)}&parentOU=${encodeURIComponent(directoryParentOUInput.value)}&description=${encodeURIComponent(directoryObjectDescription.value)}`;
            
            submitDirectoryBuilderButton.disabled = true;
            if (window.actionTakenCardContainer) {
                window.actionTakenCardContainer.classList.add('visible');
                if (window.actionTakenMessageDisplay) window.showLoadingAnimation(window.actionTakenMessageDisplay);
                window.actionTakenMessageDisplay.className = 'alert alert-info';
            }

            fetch(createDirectoryObjectApiUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body }).then(r => r.json()).then(data => {
                if (window.actionTakenCardContainer) {
                    window.actionTakenMessageDisplay.innerHTML = styleFeedbackMessage(data.message);
                    window.actionTakenMessageDisplay.className = `alert alert-${data.success ? 'success' : 'danger'}`;
                    if (typeof autoHideActionCard === 'function') autoHideActionCard();
                }
                if (data.success) { allOUsAndGroups = []; window._adTreeCache.unified = []; }
                submitDirectoryBuilderButton.disabled = false;
            }).catch(() => {
                submitDirectoryBuilderButton.disabled = false;
                if (window.actionTakenCardContainer) {
                    window.actionTakenMessageDisplay.innerHTML = 'Network error: could not reach the server.';
                    window.actionTakenMessageDisplay.className = 'alert alert-danger';
                }
            });
        });
    }

    if (submitDirectoryDeleter) {
        submitDirectoryDeleter.addEventListener('click', () => {
            const dn = document.getElementById('deleteTargetDN').value;
            const type = document.getElementById('deleteTargetType').value;
            if (!dn) return;
            if (!confirm(`Are you sure you want to PERMANENTLY DELETE this ${type}?\n\nTarget: ${dn}`)) return;
            
            submitDirectoryDeleter.disabled = true;
            if (window.actionTakenCardContainer) {
                window.actionTakenCardContainer.classList.add('visible');
                if (window.actionTakenMessageDisplay) window.showLoadingAnimation(window.actionTakenMessageDisplay);
                window.actionTakenMessageDisplay.className = 'alert alert-info';
            }

            fetch(deleteDirectoryObjectApiUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `objectDN=${encodeURIComponent(dn)}&objectType=${encodeURIComponent(type)}` }).then(r => r.json()).then(data => {
                if (window.actionTakenCardContainer) {
                    window.actionTakenMessageDisplay.innerHTML = styleFeedbackMessage(data.message);
                    window.actionTakenMessageDisplay.className = `alert alert-${data.success ? 'success' : 'danger'}`;
                    if (typeof autoHideActionCard === 'function') autoHideActionCard();
                }
                if (data.success) { window._adTreeCache.unified = []; }
                submitDirectoryDeleter.disabled = false;
            }).catch(() => {
                submitDirectoryDeleter.disabled = false;
                if (window.actionTakenCardContainer) {
                    window.actionTakenMessageDisplay.innerHTML = 'Network error: could not reach the server.';
                    window.actionTakenMessageDisplay.className = 'alert alert-danger';
                }
            });
        });
    }

    [cancelManualCreateButton, cancelDirectoryBuilderFooterButton].forEach(b => b?.addEventListener('click', () => { 
        [manualCreateFormContainerRow, directoryBuilderFormContainerRow].forEach(r => { 
            if (r) { 
                r.classList.remove('visible'); 
                r.style.display = 'none'; 
            } 
        }); 
        clearManualCreateForm();
        resetDirectoryBuilderFields();
        setWsOverflow(false);
    }));
};

function renderSelectedGroupTags() {
    const container = document.getElementById('selectedGroupMembersTags');
    if (!container) return;
    container.innerHTML = '';
    window._selectedGroupsForManualCreate.forEach(g => {
        const tag = document.createElement('span'); tag.className = 'tag-badge';
        tag.style.cssText = 'background:rgba(139,46,184,0.1);color:#8b2eb8;border:1px solid rgba(139,46,184,0.2);';
        tag.innerHTML = `${g.Name} <i class="fas fa-times-circle ms-1 cursor-pointer remove-tag" data-dn="${g.DistinguishedName}"></i>`;
        tag.querySelector('.remove-tag').addEventListener('click', () => { window._selectedGroupsForManualCreate = window._selectedGroupsForManualCreate.filter(item => item.DistinguishedName !== g.DistinguishedName); renderSelectedGroupTags(); });
        container.appendChild(tag);
    });
}

window.fetchAndPopulateUserData = function(username) {
    window.actionTakenCardContainer = document.getElementById('actionTakenCardContainer');
    window.actionTakenMessageDisplay = document.getElementById('actionTakenMessageDisplay');
    
    const resolvedBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');

    if (window.actionTakenCardContainer) { 
        window.actionTakenCardContainer.classList.add('visible'); 
        if (window.actionTakenMessageDisplay) {
            window.showLoadingAnimation(window.actionTakenMessageDisplay);
            window.actionTakenMessageDisplay.className = 'alert alert-info';
        }
    }

    // Show form immediately when user data arrives — do NOT wait for exchange status
    fetch(`${resolvedBaseUrl}/api/index.php?endpoint=get_user_info&username=${encodeURIComponent(username)}`)
        .then(r => r.json())
        .then(function(data) {
            if (data.success && data.user) {
                const svcSection = document.getElementById('serviceAccountSection');
                if (svcSection) svcSection.style.display = 'none';
                const svcFields = document.getElementById('serviceAccountFields');
                if (svcFields) svcFields.style.display = 'none';
                window._manualCreateMode = 'modify';
                window._lastFetchedUserData = data.user;

                // Hide both identity sections initially
                document.getElementById('exchangeIdentitySection').style.display = 'none';
                document.getElementById('standardFormFields').style.display = 'none';

                // Populate basic fields
                document.getElementById('manualUsername').value = data.user.SamAccountName || '';
                document.getElementById('originalUsername').value = data.user.SamAccountName || '';
                document.getElementById('manualDisplayName').value = data.user.DisplayName || '';
                document.getElementById('manualDescription').value = data.user.Description || '';
                document.getElementById('manualOUDisplay').value = data.user.OU || '';
                var ouHidden = document.getElementById('manualOU');
                if (ouHidden) ouHidden.value = (data.user.DistinguishedName || '').match(/OU=[^,]+(?:,OU=[^,]+)*,DC=[^,]+(?:,DC=[^,]+)*/i)?.[0] || '';

                // Groups
                if (!window._selectedGroupsForManualCreate) window._selectedGroupsForManualCreate = [];
                window._selectedGroupsForManualCreate.length = 0;
                if (data.user.MemberOf) {
                    data.user.MemberOf.split(';').filter(Boolean).forEach(function(gdn) {
                        var cn = gdn.match(/^CN=([^,]+)/i);
                        window._selectedGroupsForManualCreate.push({ Name: cn ? cn[1] : gdn, DistinguishedName: gdn });
                    });
                }
                if (typeof renderSelectedGroupTags === 'function') renderSelectedGroupTags();

                // Password section
                document.getElementById('modifyPasswordSection').style.display = 'block';
                document.getElementById('modifyResetPassword').checked = false;
                document.getElementById('modifyPasswordOptions').style.display = 'none';
                document.getElementById('modifyTemporaryPassword').value = '';
                document.getElementById('modifyForcePasswordChange').checked = true;
                document.getElementById('modifyUseDefaultPassword').checked = false;

                var mpc = document.getElementById('modifyPwdMustChange');
                if (mpc) mpc.checked = data.user.pwdMustChange === true;
                var mpc2 = document.getElementById('modifyPwdCantChange');
                if (mpc2) mpc2.checked = data.user.pwdCantChange === true;
                var mpc3 = document.getElementById('modifyPwdNeverExpires');
                if (mpc3) mpc3.checked = data.user.pwdNeverExpires === true;

                // Decide which identity section to show
                if (window._exchangeEnabled) {
                    var mbData = data.user.exchange_mailbox || {};
                    populateExchangeIdentitySection(data.user, mbData);
                    populateMailboxSection(mbData, data.user);
                    document.getElementById('standardFormFields').style.display = 'none';
                } else {
                    document.getElementById('standardFormFields').style.display = '';
                }

                // Start exchange status check if not yet known
                if (window._exchangeEnabled === undefined) {
                    checkExchangeStatus().then(function() {
                        if (window._exchangeEnabled) {
                            var mbd = data.user.exchange_mailbox || {};
                            populateExchangeIdentitySection(data.user, mbd);
                            populateMailboxSection(mbd, data.user);
                            // Hide standard form fields when exchange is available
                            document.getElementById('standardFormFields').style.display = 'none';
                        } else {
                            document.getElementById('standardFormFields').style.display = '';
                        }
                    });
                }

                if (window.actionTakenCardContainer) {
                    window.actionTakenCardContainer.classList.remove('visible');
                    window.actionTakenCardContainer.style.display = 'none';
                }

                var row = document.getElementById('manualCreateFormContainerRow');
                var title = document.getElementById('manualCreateFormTitle');
                var submitBtn = document.getElementById('submitManualCreate');
                if (title) title.innerHTML = '<i class="fas fa-user-edit"></i> Modify Active Directory User';
                if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-save"></i> Update User';
                if (row) { row.classList.add('visible'); row.style.display = 'block'; }
            } else {
                if (window.actionTakenMessageDisplay) {
                    window.actionTakenMessageDisplay.innerHTML = styleFeedbackMessage(data.message || 'Failed to fetch user data.');
                    window.actionTakenMessageDisplay.className = 'alert alert-danger';
                }
            }
        }).catch(function(err) {
            if (window.actionTakenMessageDisplay) {
                window.actionTakenMessageDisplay.innerHTML = 'Error communicating with server.';
                window.actionTakenMessageDisplay.className = 'alert alert-danger';
            }
            console.error(err);
        });
};

window.initManualCreateUser();

// Pre-fetch exchange status at page load — cache result so modify user form shows instantly
if (window._exchangeEnabled === undefined) {
    // No timeout — if exchange is slow, form shows standard fields first and upgrades later
    checkExchangeStatus();
}

// ===== Mailbox helper functions =====

function renderProxyBadges(proxies) {
    const list = document.getElementById('mbProxyList');
    if (!list) return;
    list.innerHTML = '';
    if (!proxies || proxies.length === 0) {
        list.innerHTML = '<span class="text-muted small">None</span>';
        window._pendingProxies = [];
        return;
    }
    const arr = Array.isArray(proxies) ? proxies : (proxies.proxy_addresses || []);
    window._pendingProxies = [];
    arr.forEach(p => {
        const addr = typeof p === 'string' ? p : p.address;
        const isPrimary = typeof p === 'string' ? false : !!p.is_primary;
        window._pendingProxies.push(addr);
        const badge = document.createElement('span');
        badge.className = 'badge bg-secondary position-relative';
        badge.style.paddingRight = '1.5rem';
        badge.textContent = addr + (isPrimary ? ' [PRIMARY]' : '');
        const rmBtn = document.createElement('button');
        rmBtn.type = 'button';
        rmBtn.className = 'btn-close btn-close-white';
        rmBtn.style.cssText = 'position:absolute;top:2px;right:4px;font-size:0.6rem;padding:2px;';
        rmBtn.setAttribute('aria-label', 'Remove');
        rmBtn.addEventListener('click', () => {
            window._pendingProxies = window._pendingProxies.filter(x => x !== addr);
            renderProxyBadges(window._pendingProxies);
        });
        badge.appendChild(rmBtn);
        list.appendChild(badge);
    });
}

function loadMailboxDatabases(dropdownId, selectedValue) {
    const sel = document.getElementById(dropdownId);
    if (!sel) return;
    fetch(`${window.APP_CONFIG?.baseUrl || baseURL}/api/index.php?endpoint=exchange`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window._csrfToken || window.APP_CONFIG?.csrfToken || '' },
        body: JSON.stringify({ action: 'discover' })
    })
    .then(r => r.json())
    .then(d => {
        if (d.databases && d.databases.length > 0) {
            sel.innerHTML = '<option value="">Default</option>';
            d.databases.forEach(db => {
                const opt = document.createElement('option');
                opt.value = db.name;
                opt.textContent = db.name + (db.server ? ' (' + db.server + ')' : '');
                if (selectedValue && (db.name === selectedValue || db.dn === selectedValue)) opt.selected = true;
                sel.appendChild(opt);
            });
        }
    })
    .catch(() => {});
}

function saveMailboxChanges() {
    const identity = document.getElementById('manualUsername')?.value.trim();
    if (!identity) return;
    const alias = document.getElementById('mbEditAlias')?.value.trim() || '';
    const primarySmtp = document.getElementById('mbEditPrimarySmtp')?.value.trim() || '';
    const hiddenGal = document.getElementById('mbEditHiddenGal')?.checked ? 'true' : 'false';
    const database = document.getElementById('mbEditDatabase')?.value || '';
    const existingProxies = window._pendingProxies || [];
    const originalProxies = window._originalProxies || [];

    // Determine what to add and remove
    const toAdd = existingProxies.filter(p => !originalProxies.includes(p));
    const toRemove = originalProxies.filter(p => !existingProxies.includes(p));

    const fb = document.getElementById('mbSaveFeedback');
    if (fb) { fb.textContent = 'Saving...'; fb.className = 'ms-2 text-info'; }

    const body = new URLSearchParams({
        action: 'save_mailbox',
        identity: identity,
        alias: alias,
        primary_smtp: primarySmtp,
        hidden_gal: hiddenGal,
        add_proxies: toAdd.join(','),
        remove_proxies: toRemove.join(',')
    });

    fetch(`${window.APP_CONFIG?.baseUrl || baseURL}/api/index.php?endpoint=modify_mailbox`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window._csrfToken || window.APP_CONFIG?.csrfToken || '' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(d => {
        if (fb) {
            fb.textContent = d.message || (d.success ? 'Saved' : 'Failed');
            fb.className = 'ms-2 text-' + (d.success ? 'success' : 'danger');
        }
        if (d.success) window._originalProxies = [...(window._pendingProxies || [])];
    })
    .catch(() => {
        if (fb) { fb.textContent = 'Error saving'; fb.className = 'ms-2 text-danger'; }
    });
}

function createMailbox() {
    const identity = document.getElementById('manualUsername')?.value.trim();
    if (!identity) return;
    const alias = document.getElementById('modifyMailboxAlias')?.value.trim() || '';
    const database = document.getElementById('modifyMailboxDatabase')?.value || '';

    const fb = document.getElementById('mbCreateFeedback');
    if (fb) { fb.textContent = 'Creating...'; fb.className = 'ms-2 text-info'; }

    const body = new URLSearchParams({
        action: 'create_mailbox',
        identity: identity,
        alias: alias,
        database: database,
    });

    fetch(`${window.APP_CONFIG?.baseUrl || baseURL}/api/index.php?endpoint=modify_mailbox`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window._csrfToken || window.APP_CONFIG?.csrfToken || '' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(d => {
        if (fb) {
            fb.textContent = d.message || (d.success ? 'Created' : 'Failed');
            fb.className = 'ms-2 text-' + (d.success ? 'success' : 'danger');
        }
        if (d.success) {
            // Refetch user data to show the hasMailbox view
            const username = document.getElementById('manualUsername')?.value.trim();
            if (username && typeof window.fetchAndPopulateUserData === 'function') {
                setTimeout(() => window.fetchAndPopulateUserData(username), 2000);
            }
        }
    })
    .catch(() => {
        if (fb) { fb.textContent = 'Error creating'; fb.className = 'ms-2 text-danger'; }
    });
}

// ===== Exchange management UI =====

// Hides the Enable Mailbox checkbox when the active AD domain has no Exchange.
function syncMailboxCheckboxDomain() {
    const wrap = document.getElementById('enableMailboxWrap');
    const chk = document.getElementById('enableMailboxCheck');
    if (!chk) return;
    const off = window._exchangeEnabled === false;
    if (wrap) wrap.style.display = off ? 'none' : '';
    if (off) chk.checked = false;
}

function checkExchangeStatus() {
    // Return cached promise if already in-flight — prevents duplicate calls AND
    // lets all callers wait for the same result
    if (window._exchangeStatusPromise) return window._exchangeStatusPromise;

    const baseUrl = window.APP_CONFIG?.baseUrl || (typeof baseURL === 'string' ? baseURL : '');
    const mbSection = document.getElementById('modifyMailboxSection');

    // Only hide mailbox section if the form is visible (not during pre-fetch)
    const formRow = document.getElementById('manualCreateFormContainerRow');
    if (formRow && formRow.style.display !== 'none' && mbSection) {
        mbSection.style.display = 'none';
    }

    window._exchangeStatusPromise = fetch(baseUrl + '/api/index.php?endpoint=modify_mailbox', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window._csrfToken || window.APP_CONFIG?.csrfToken || '' },
        body: 'action=get_status'
    })
    .then(r => r.json())
    .then(d => {
        window._exchangeEnabled = d.success === true && d.exchange_enabled !== false;
        window._exchangeDatabases = d.databases || [];
        syncMailboxCheckboxDomain();
        return d;
    })
    .catch(() => {
        window._exchangeEnabled = false;
        window._exchangeDatabases = [];
    });
    return window._exchangeStatusPromise;
}

function populateMailboxSection(mb, user) {
    const mbSection = document.getElementById('modifyMailboxSection');
    const mbHas = document.getElementById('mailboxHasMailbox');
    const mbNone = document.getElementById('mailboxNoMailbox');
    if (mbSection) mbSection.style.display = 'block';

    // Populate EAC-style identity section
    populateExchangeIdentitySection(user, mb);

    if (mb.has_mailbox) {
        if (mbHas) mbHas.style.display = 'block';
        if (mbNone) mbNone.style.display = 'none';
        const statusEl = document.getElementById('mbStatus');
        if (statusEl) statusEl.textContent = mb.mailbox_disabled ? 'Disabled' : 'Enabled';
        if (statusEl) statusEl.className = 'badge bg-' + (mb.mailbox_disabled ? 'danger' : 'success');
        const rtypeEl = document.getElementById('mbRecipientType');
        if (rtypeEl) rtypeEl.textContent = mb.recipient_type || '';
        renderMailboxAdvancedUI(user);
        // Populate mailbox/litigation hold status display
        renderMbActionsStatus(mb.mailbox_disabled, mb.litigation_hold);
    } else {
        if (mbHas) mbHas.style.display = 'none';
        if (mbNone) mbNone.style.display = 'block';
        const aliasInput = document.getElementById('modifyMailboxAlias');
        if (aliasInput) aliasInput.value = user.SamAccountName || '';
        if (window._exchangeDatabases && window._exchangeDatabases.length > 0) {
            populateDbSelect('modifyMailboxDatabase', window._exchangeDatabases, '');
        }
    }
}

function populateExchangeIdentitySection(user, mb) {
    const section = document.getElementById('exchangeIdentitySection');
    if (!section) return;

    // Don't hide standardFormFields here — already done in fetchAndPopulateUserData
    section.style.display = 'block';

    document.getElementById('exFirstName').value = user.firstName && user.firstName !== 'N/A' ? user.firstName : '';
    document.getElementById('exInitials').value = user.initials || '';
    document.getElementById('exLastName').value = user.lastName && user.lastName !== 'N/A' ? user.lastName : '';
    document.getElementById('exFullName').value = user.DisplayName || '';
    document.getElementById('exDisplayName').value = user.DisplayName || '';
    document.getElementById('exAlias').value = mb.alias || user.SamAccountName || '';
    document.getElementById('exLogonName').value = user.SamAccountName || '';
    document.getElementById('exDomainSuffix').value = (user.userPrincipalName || '').split('@')[1] || '';
    document.getElementById('exTitle').value = user.jobTitle && user.jobTitle !== 'N/A' ? user.jobTitle : '';
    document.getElementById('exDepartment').value = user.department && user.department !== 'N/A' ? user.department : '';
    document.getElementById('exCompany').value = user.company && user.company !== 'N/A' ? user.company : '';
    document.getElementById('exOffice').value = user.office && user.office !== 'N/A' ? user.office : '';
    document.getElementById('exPhone').value = user.phoneNumber && user.phoneNumber !== 'N/A' ? user.phoneNumber : '';

    // OU — bind search
    const exOUDisp = document.getElementById('exOUDisplay');
    const exOUDd = document.getElementById('exOUDropdown');
    const exOUList = document.getElementById('exOUList');
    const exOUHidden = document.getElementById('exOU');
    if (exOUDisp && exOUDd && exOUList && window.adTreeDropdown?.bindSearch) {
        const ou = user.OU || '';
        exOUDisp.value = ou;
        if (exOUHidden) exOUHidden.value = user.DistinguishedName ? user.DistinguishedName.replace(/^CN=[^,]+,/i, '') : '';
        window.adTreeDropdown.bindSearch(exOUDisp, exOUDd, exOUList, ['OU', 'Domain'], i => {
            if (i.clear) { exOUDisp.value = ''; if (exOUHidden) exOUHidden.value = ''; }
            else { exOUDisp.value = i.Name; if (exOUHidden) exOUHidden.value = i.DistinguishedName; }
            exOUDd.style.display = 'none';
        });
    }

    // Mailbox database — populate dropdown
    const dbEl = document.getElementById('exMailboxDb');
    if (dbEl) {
        if (window._exchangeDatabases && window._exchangeDatabases.length > 0) {
            populateDbSelect('exMailboxDb', window._exchangeDatabases, mb.home_database || '');
        } else {
            // Show current database name as a label+dropdown even without db list
            const shortDbName = (mb.home_database || '').replace(/^CN=([^,]+).*/i, '$1') || 'Default';
            dbEl.innerHTML = '<option value="">' + escHtml(shortDbName) + '</option>';
            // Also try to fetch databases in background
            fetchDatabasesBackground();
        }
    }

    // Hide from GAL checkbox
    const hideGal = document.getElementById('exHideFromGal');
    if (hideGal) hideGal.checked = mb.hidden_from_gal === true;

    // Hide from GAL checkbox sync with modify action
    const hideGalChk = document.getElementById('exHideFromGal');
    if (hideGalChk) {
        hideGalChk.addEventListener('change', function() {
            const identity = user.SamAccountName || document.getElementById('manualUsername')?.value.trim();
            if (!identity) return;
            const hidden = this.checked;
            const baseUrl = window.APP_CONFIG?.baseUrl || (typeof baseURL === 'string' ? baseURL : '');
            const fb = document.getElementById('mbProfileFeedback');
            if (fb) fb.textContent = 'Updating GAL visibility...';
            fetch(baseUrl + '/api/index.php?endpoint=modify_mailbox', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window._csrfToken || window.APP_CONFIG?.csrfToken || '' },
                body: 'action=set_hidden_gal&identity=' + encodeURIComponent(identity) + '&hidden=' + (hidden ? '1' : '0')
            })
            .then(r => r.json())
            .then(d => {
                if (fb) { fb.textContent = d.message || (d.success ? 'Done' : 'Failed'); fb.className = 'small text-' + (d.success ? 'success' : 'danger'); }
            })
            .catch(() => { if (fb) { fb.textContent = 'Error'; fb.className = 'small text-danger'; } });
        });
    }

    // Save Identity button
    document.getElementById('mbSaveProfileBtn')?.addEventListener('click', () => saveMbProfile(user.SamAccountName));
}

function fetchDatabasesBackground() {
    if (window._exchangeDatabases && window._exchangeDatabases.length > 0) return;
    const baseUrl = window.APP_CONFIG?.baseUrl || (typeof baseURL === 'string' ? baseURL : '');
    fetch(baseUrl + '/api/index.php?endpoint=modify_mailbox', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window._csrfToken || window.APP_CONFIG?.csrfToken || '' },
        body: 'action=get_status'
    })
    .then(r => r.json())
    .then(d => {
        if (d.databases && d.databases.length > 0) {
            window._exchangeDatabases = d.databases;
            populateDbSelect('exMailboxDb', d.databases, '');
            populateDbSelect('modifyMailboxDatabase', d.databases, '');
        }
    })
    .catch(() => {});
}

function populateDbSelect(selectId, databases, selected) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    sel.innerHTML = '<option value="">Default</option>';
    // Extract short name from DN-like selected value
    const selectedShort = selected ? selected.replace(/^CN=([^,]+).*/i, '$1') : '';
    databases.forEach(db => {
        const opt = document.createElement('option');
        opt.value = db.name;
        opt.textContent = db.name + (db.server ? ' (' + db.server + ')' : '');
        if (selectedShort && (db.name === selectedShort || db.name === selected)) opt.selected = true;
        sel.appendChild(opt);
    });
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function renderMailboxAdvancedUI(user) {
    const body = document.getElementById('mbAdvancedBody');
    if (!body) return;
    const mb = user.exchange_mailbox || {};

    body.innerHTML = `
        <div class="row g-2 mt-1" style="user-select:text">

            <!-- COL 1 -->
            <div class="col-md-6 d-flex flex-column gap-2">

                <!-- EMAIL ADDRESSES -->
                <div class="p-2" style="border:1px solid var(--border-color);border-radius:8px;background:var(--ws-card-bg)">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <i class="fas fa-at" style="font-size:13px;color:var(--primary-color)"></i>
                        <span style="font-size:12px;font-weight:600;color:var(--text-muted)">EMAIL</span>
                    </div>
                    <div id="mbEmailTable" class="small"></div>
                    <div class="d-flex gap-1 mt-1">
                        <input type="text" id="mbNewEmailInput" placeholder="alias@domain.com" style="flex:1;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;max-width:200px" data-noc-tip="Type a new email alias and click + to add">
                        <button id="mbAddEmailBtn" style="background:none;border:none;color:var(--primary);font-size:16px;padding:0 4px;cursor:pointer;line-height:1" data-noc-tip="Add this email address as an alias to the mailbox"><i class="fas fa-plus"></i></button>
                    </div>
                    <span id="mbEmailFeedback" class="small"></span>
                </div>
            </div>

            <!-- COL 2 -->
            <div class="col-md-6 d-flex flex-column gap-2">

                <!-- QUOTA -->
                <div class="p-2" style="border:1px solid var(--border-color);border-radius:8px;background:var(--ws-card-bg)">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <i class="fas fa-chart-pie" style="font-size:13px;color:var(--primary-color)"></i>
                        <span style="font-size:12px;font-weight:600;color:var(--text-muted)">QUOTA</span>
                    </div>
                    <div id="mbStatsBody" class="small" style="font-size:11px;color:var(--text-muted)">Loading...</div>
                    <div class="d-flex gap-1 mt-1" style="flex-wrap:nowrap;align-items:flex-start">
                        <div style="flex:1;min-width:0">
                            <input type="number" id="mbQuotaWarn" value="5" min="0" step="0.1" style="width:100%;height:26px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 6px;" data-noc-tip="Issue warning quota — warn when mailbox exceeds this size">
                            <div style="font-size:9px;color:var(--text-muted);text-align:center;line-height:1">Warn</div>
                        </div>
                        <div style="flex:1;min-width:0">
                            <input type="number" id="mbQuotaSend" value="6" min="0" step="0.1" style="width:100%;height:26px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 6px;" data-noc-tip="Prohibit send quota — cannot send when exceeding this size">
                            <div style="font-size:9px;color:var(--text-muted);text-align:center;line-height:1">Send block</div>
                        </div>
                        <div style="flex:1;min-width:0">
                            <input type="number" id="mbQuotaRecv" value="8" min="0" step="0.1" style="width:100%;height:26px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 6px;" data-noc-tip="Prohibit send/receive quota — cannot send or receive when exceeding this size">
                            <div style="font-size:9px;color:var(--text-muted);text-align:center;line-height:1">S/R block</div>
                        </div>
                        <select id="mbQuotaUnit" style="height:26px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;width:48px;flex-shrink:0" data-noc-tip="Unit of measurement for quota values">
                            <option value="MB">MB</option><option value="GB" selected>GB</option><option value="TB">TB</option>
                        </select>
                        <button id="mbSaveQuotaBtn" style="background:none;border:none;color:var(--warning);font-size:14px;padding:0;cursor:pointer;line-height:26px;height:26px;flex-shrink:0" data-noc-tip="Save quota limits to the mailbox"><i class="fas fa-save"></i></button>
                    </div>
                    <span id="mbQuotaFeedback" class="small"></span>
                </div>

                <!-- ARCHIVE -->
                <div class="p-2" style="border:1px solid var(--border-color);border-radius:8px;background:var(--ws-card-bg)">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <i class="fas fa-archive" style="font-size:13px;color:var(--primary-color)"></i>
                        <span style="font-size:12px;font-weight:600;color:var(--text-muted)">IN-PLACE ARCHIVE</span>
                    </div>
                    <div id="mbArchiveBody" class="d-flex align-items-center gap-2" style="font-size:11px">
                        <span>Archiving:</span>
                        <span id="mbArchiveStatus" class="badge bg-secondary">Checking...</span>
                        <button id="mbArchiveEnableBtn" style="background:none;border:none;color:var(--success);font-size:11px;padding:0 4px;cursor:pointer;line-height:1;display:none" data-noc-tip="Enable archive mailbox"><i class="fas fa-archive"></i> Enable</button>
                        <button id="mbArchiveDisableBtn" style="background:none;border:none;color:var(--danger);font-size:11px;padding:0 4px;cursor:pointer;line-height:1;display:none" data-noc-tip="Disable archive mailbox"><i class="fas fa-archive"></i> Disable</button>
                    </div>
                    <span id="mbArchiveFeedback" class="small"></span>
                </div>

                <!-- MOBILE DEVICES / CAS -->
                <div class="p-2" style="border:1px solid var(--border-color);border-radius:8px;background:var(--ws-card-bg)">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <i class="fas fa-mobile-alt" style="font-size:13px;color:var(--primary-color)"></i>
                        <span style="font-size:12px;font-weight:600;color:var(--text-muted)">MOBILE DEVICES</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center" id="mbCasBody" style="font-size:11px">
                        <span>Loading...</span>
                    </div>
                    <span id="mbCasFeedback" class="small"></span>
                </div>

                <!-- FORWARDING & ACTIONS -->
                <div class="p-2" style="border:1px solid var(--border-color);border-radius:8px;background:var(--ws-card-bg)">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <i class="fas fa-exchange-alt" style="font-size:13px;color:var(--primary-color)"></i>
                        <span style="font-size:12px;font-weight:600;color:var(--text-muted)">FORWARDING</span>
                    </div>
                    <div class="d-flex gap-1">
                        <input type="text" id="mbFwdInput" placeholder="user@domain.com" style="flex:1;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;max-width:200px" data-noc-tip="Forward all incoming emails to this address">
                        <button id="mbFwdSetBtn" style="background:none;border:none;color:var(--success);font-size:14px;padding:0 4px;cursor:pointer;line-height:1" data-noc-tip="Set email forwarding to the specified address"><i class="fas fa-check"></i></button>
                        <button id="mbFwdClearBtn" style="background:none;border:none;color:var(--danger);font-size:14px;padding:0 4px;cursor:pointer;line-height:1" data-noc-tip="Remove email forwarding"><i class="fas fa-times"></i></button>
                    </div>
                    <div id="mbActionsFeedback" class="small" style="margin-top:4px"></div>
                </div>

                <!-- MAILBOX STATUS & LITIGATION HOLD -->
                <div class="p-2" style="border:1px solid var(--border-color);border-radius:8px;background:var(--ws-card-bg)">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <i class="fas fa-shield-alt" style="font-size:13px;color:var(--primary-color)"></i>
                        <span style="font-size:12px;font-weight:600;color:var(--text-muted)">MAILBOX STATUS</span>
                    </div>
                    <div style="font-size:10px">
                        <div class="d-flex align-items-center gap-1" style="padding:2px 0">
                            <span>Mailbox:</span>
                            <span id="mbStatusDisplay" class="badge bg-success" style="font-size:9px">Enabled</span>
                            <button id="mbToggleMailboxBtn" style="background:none;border:1px solid var(--border-color);border-radius:3px;padding:1px 8px;font-size:9px;cursor:pointer;margin-left:auto" data-noc-tip="Enable or disable this Exchange mailbox">Disable</button>
                        </div>
                        <div class="d-flex align-items-center gap-1" style="padding:2px 0">
                            <span>Litigation Hold:</span>
                            <span id="mbLitStatusDisplay" class="badge bg-secondary" style="font-size:9px">Off</span>
                            <button id="mbToggleLitBtn" style="background:none;border:1px solid var(--border-color);border-radius:3px;padding:1px 8px;font-size:9px;cursor:pointer;margin-left:auto" data-noc-tip="Litigation hold preserves deleted/edited mailbox items for e-discovery. Safe to enable.">Enable</button>
                        </div>
                    </div>
                    <span id="mbActionsFeedback2" class="small" style="margin-top:4px"></span>
                </div>
            </div>
        </div>
    `;

    renderMbEmailTable(mb.proxy_addresses);
    loadMbStats(user.SamAccountName);

    // Pre-populate quota from LDAP data (available in exchange_mailbox)
    const pq = (v) => { if (!v || v === 'N/A' || v === 'Unlimited') return ''; const m = String(v).match(/^([\d.]+)/); return m ? m[1] : ''; };
    const warnInp = document.getElementById('mbQuotaWarn');
    const sendInp = document.getElementById('mbQuotaSend');
    const recvInp = document.getElementById('mbQuotaRecv');
    if (warnInp && mb.issue_warning_quota) warnInp.value = pq(mb.issue_warning_quota) || '5';
    if (sendInp && mb.prohibit_send_quota) sendInp.value = pq(mb.prohibit_send_quota) || '6';
    if (recvInp && mb.prohibit_send_receive_quota) recvInp.value = pq(mb.prohibit_send_receive_quota) || '8';
    // Also show database in stats body from LDAP home_database
    const bodyEl = document.getElementById('mbStatsBody');
    if (bodyEl && mb.home_database) {
        var tempShort = function(n) {
            if (!n) return '';
            if (n.indexOf('/cn=') !== -1) return n.split('/cn=').pop();
            var m = n.match(/CN=([^,]+)/i);
            return m ? m[1] : n;
        };
        var shortDbName = tempShort(mb.home_database);
        if (shortDbName) bodyEl.innerHTML = 'DB: ' + escHtml(shortDbName) + ' | Loading mailbox stats...';
    }

    document.getElementById('mbAddEmailBtn')?.addEventListener('click', () => addMbEmail(user.SamAccountName));
    document.getElementById('mbSaveQuotaBtn')?.addEventListener('click', () => saveMbQuota(user.SamAccountName));
    document.getElementById('mbFwdSetBtn')?.addEventListener('click', () => setMbForward(user.SamAccountName, true));
    document.getElementById('mbFwdClearBtn')?.addEventListener('click', () => setMbForward(user.SamAccountName, false));
    document.getElementById('mbToggleMailboxBtn')?.addEventListener('click', () => toggleMbMailbox(user.SamAccountName));
    document.getElementById('mbToggleLitBtn')?.addEventListener('click', () => toggleMbLitigationHold(user.SamAccountName));
    document.getElementById('mbArchiveEnableBtn')?.addEventListener('click', () => toggleMbArchive(user.SamAccountName, true));
    document.getElementById('mbArchiveDisableBtn')?.addEventListener('click', () => toggleMbArchive(user.SamAccountName, false));

    if (typeof NocTooltip !== 'undefined' && NocTooltip.init) NocTooltip.init();
}

function renderMbEmailTable(proxyAddresses) {
    const table = document.getElementById('mbEmailTable');
    if (!table) return;
    if (!proxyAddresses || proxyAddresses.length === 0) {
        table.innerHTML = '<span class="text-muted" style="font-size:11px">No email addresses.</span>';
        return;
    }
    let html = '<div style="max-height:160px;overflow-y:auto;font-size:11px">';
    proxyAddresses.forEach(pa => {
        const isPrimary = pa.is_primary;
        html += '<div class="d-flex align-items-center" style="border-bottom:1px solid var(--border-color);padding:4px 2px">'
            + '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + escHtml(pa.address) + '">' + escHtml(pa.address) + '</span>'
            + '<span style="flex-shrink:0;font-size:9px;padding:1px 6px;border-radius:3px;background:' + (isPrimary ? 'var(--primary-color)' : 'var(--border-color)') + ';color:' + (isPrimary ? '#fff' : 'var(--text-muted)') + ';margin-left:6px">' + (isPrimary ? 'Primary' : 'Alias') + '</span>'
            + (isPrimary ? '' : '<button class="mbMakePrimaryBtn" data-email="' + escHtml(pa.address) + '" style="background:none;border:none;padding:2px 6px;cursor:pointer;font-size:10px;color:var(--primary-color)" title="Make primary"><i class="fas fa-star"></i></button>')
            + '<button class="mbRemoveEmailBtn" data-email="' + escHtml(pa.address) + '" style="background:none;border:none;padding:2px 6px;cursor:pointer;font-size:10px;color:#dc3545" title="Remove"><i class="fas fa-times"></i></button>'
            + '</div>';
    });
    html += '</div>';
    table.innerHTML = html;

    if (window.NocTooltip && typeof window.NocTooltip.init === 'function') window.NocTooltip.init();

    table.querySelectorAll('.mbMakePrimaryBtn').forEach(btn => {
        btn.addEventListener('click', function() {
            const email = this.dataset.email;
            const identity = document.getElementById('manualUsername')?.value.trim();
            if (identity && email && confirm('Make ' + email + ' the primary SMTP address?')) {
                doMbModifyAction('set_primary_smtp', identity, email, 'mbEmailFeedback');
            }
        });
    });
    table.querySelectorAll('.mbRemoveEmailBtn').forEach(btn => {
        btn.addEventListener('click', function() {
            const email = this.dataset.email;
            const identity = document.getElementById('manualUsername')?.value.trim();
            if (identity && email && confirm('Remove ' + email + '?')) {
                doMbModifyAction('remove_email', identity, email, 'mbEmailFeedback');
            }
        });
    });
}

function loadMbStats(identity) {
    const baseUrl = window.APP_CONFIG?.baseUrl || (typeof baseURL === 'string' ? baseURL : '');
    const bodyEl = document.getElementById('mbStatsBody');
    const warn = document.getElementById('mbQuotaWarn');
    const send = document.getElementById('mbQuotaSend');
    const recv = document.getElementById('mbQuotaRecv');

    fetch(baseUrl + '/api/index.php?endpoint=modify_mailbox', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window._csrfToken || window.APP_CONFIG?.csrfToken || '' },
        body: 'action=get_stats&identity=' + encodeURIComponent(identity)
    })
    .then(r => r.json())
    .then(d => {
        try {
            // Extract quota values first (used by both inputs and progress bar)
            var qw2 = '', qs2 = '', qr2 = '';
            if (d.mailbox) {
                var pq = function(v) { if (!v || v === 'N/A' || v === 'Unlimited') return ''; var m2 = String(v).match(/^([\d.]+)/); return m2 ? m2[1] : ''; };
                qw2 = pq(d.mailbox.issue_warning_quota);
                qs2 = pq(d.mailbox.prohibit_send_quota);
                qr2 = pq(d.mailbox.prohibit_send_receive_quota);
                if (qw2 && warn) warn.value = qw2;
                if (qs2 && send) send.value = qs2;
                if (qr2 && recv) recv.value = qr2;
            }

            // Stats body + progress bar
            if (bodyEl) {
                var shortDb = function(n) {
                    if (!n) return '';
                    if (n.indexOf('/cn=') !== -1) return n.split('/cn=').pop();
                    var m = n.match(/CN=([^,]+)/i);
                    if (m) return m[1];
                    if (n.indexOf('\\') !== -1) return n.split('\\').pop();
                    if (n.indexOf('/') !== -1) return n.split('/').pop();
                    return n;
                };
                if (d.stats) {
                    var s = d.stats;
                    var parts = [];
                    if (s.total_item_size && s.total_item_size !== 'N/A') parts.push('Used: ' + escHtml(s.total_item_size));
                    if (s.item_count && s.item_count !== 'N/A') parts.push('Items: ' + escHtml(s.item_count));
                    if (s.database_name && s.database_name !== 'N/A') {
                        parts.push('DB: ' + escHtml(shortDb(s.database_name)));
                    }
                    if (parts.length > 0) bodyEl.innerHTML = parts.join(' | ');
                    // Quota usage bar
                    if (s.total_item_size && s.total_item_size !== 'N/A') {
                        var usedText = String(s.total_item_size);
                        var usedVal = parseFloat(usedText.replace(/^([\d.]+).*/, '$1'));
                        var usedUnit = (usedText.match(/([KMGT]?B)/i) || [])[1] || 'GB';
                        // Convert everything to GB
                        if (usedUnit === 'B') usedVal = usedVal / (1024*1024*1024);
                        else if (usedUnit === 'KB') usedVal = usedVal / (1024*1024);
                        else if (usedUnit === 'MB') usedVal = usedVal / 1024;
                        else if (usedUnit === 'TB') usedVal = usedVal * 1024;
                        var maxQuota = parseFloat(qr2) || parseFloat(qs2) || parseFloat(qw2) || parseFloat(recv ? recv.value : 0) || parseFloat(send ? send.value : 0) || parseFloat(warn ? warn.value : 0) || 0;
                        if (usedVal > 0 && maxQuota > 0) {
                            var pct = Math.min(100, (usedVal / maxQuota) * 100);
                            var barW = Math.max(2, pct);
                            var barColor = pct > 90 ? '#dc3545' : pct > 75 ? '#ffc107' : '#198754';
                            bodyEl.innerHTML += '<div style="margin-top:4px;height:4px;background:var(--border-color,#e0e0e0);border-radius:2px;overflow:hidden"><div style="height:100%;width:' + barW.toFixed(0) + '%;background:' + barColor + ';border-radius:2px"></div></div>' +
                                '<div style="font-size:9px;color:var(--text-muted);margin-top:1px">' + pct.toFixed(1) + '% of ' + maxQuota.toFixed(1) + ' GB used</div>';
                        }
                    }
                } else if (d.mailbox && d.mailbox.database) {
                    bodyEl.innerHTML = 'DB: ' + escHtml(shortDb(d.mailbox.database)) + ' | Usage: Unavailable';
                } else {
                    bodyEl.innerHTML = 'Stats unavailable.';
                }
            }
            if (d.mailbox) {
                if (bodyEl && (!d.stats || !d.stats.database_name || d.stats.database_name === 'N/A')) {
                    var dbN = d.mailbox.database ? shortDb(d.mailbox.database) : '';
                    if (dbN) bodyEl.innerHTML = 'DB: ' + escHtml(dbN) + ' | Usage: Unavailable';
                }
            }
            var mailboxDb2 = d.mailbox ? d.mailbox.database || '' : '';
            if (mailboxDb2 && window._exchangeDatabases && window._exchangeDatabases.length > 0) {
                populateDbSelect('exMailboxDb', window._exchangeDatabases, shortDb(mailboxDb2));
            } else if (mailboxDb2) {
                var shortName2 = shortDb(mailboxDb2);
                var dbEl2 = document.getElementById('exMailboxDb');
                if (dbEl2 && dbEl2.options.length <= 1) {
                    dbEl2.innerHTML = '<option value="">' + escHtml(shortName2) + '</option>';
                }
            }
            if (d.mailbox) {
                var fwdInput2 = document.getElementById('mbFwdInput');
                if (fwdInput2 && d.mailbox.forwarding_smtp) fwdInput2.value = d.mailbox.forwarding_smtp;
            }
            renderArchiveSection(d.archive);
            renderCasSection(d.cas);
            if (d.error) console.warn('modify_mailbox API:', d.error);
        } catch (ex) {
            console.error('loadMbStats .then error:', ex);
        }
    })
    .catch(function(err) {
        if (bodyEl) bodyEl.innerHTML = 'Failed to load stats.';
        console.error('loadMbStats error:', err);
        // Show error in feedback elements if they exist
        ['mbActionsFeedback','mbArchiveFeedback','mbCasFeedback','mbEmailFeedback','mbQuotaFeedback'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el && !el.textContent) el.textContent = 'API error. Check console.';
        });
    });
}

function renderArchiveSection(archive) {
    const statusEl = document.getElementById('mbArchiveStatus');
    const enableBtn = document.getElementById('mbArchiveEnableBtn');
    const disableBtn = document.getElementById('mbArchiveDisableBtn');
    if (!statusEl) return;

    if (archive && archive.archive_status === 'Active') {
        statusEl.textContent = 'Active' + (archive.archive_name ? ' (' + archive.archive_name + ')' : '');
        statusEl.className = 'badge bg-success';
        if (enableBtn) enableBtn.style.display = 'none';
        if (disableBtn) disableBtn.style.display = 'inline-block';
    } else {
        statusEl.textContent = 'Disabled';
        statusEl.className = 'badge bg-secondary';
        if (enableBtn) enableBtn.style.display = 'inline-block';
        if (disableBtn) disableBtn.style.display = 'none';
    }
}

function renderCasSection(cas) {
    const body = document.getElementById('mbCasBody');
    if (!body) return;
    if (!cas) {
        body.innerHTML = '<span class="text-muted">CAS info unavailable.</span>';
        return;
    }

    const settings = [
        { key: 'active_sync_enabled', label: 'Exchange ActiveSync', icon: 'fa-sync-alt' },
        { key: 'owa_enabled', label: 'Outlook Web App', icon: 'fa-globe' },
        { key: 'owa_for_devices_enabled', label: 'OWA for Devices', icon: 'fa-mobile-alt' },
        { key: 'mapi_enabled', label: 'MAPI (Outlook)', icon: 'fa-envelope' },
        { key: 'pop_enabled', label: 'POP3', icon: 'fa-inbox' },
        { key: 'imap_enabled', label: 'IMAP4', icon: 'fa-download' },
        { key: 'ews_enabled', label: 'EWS (Web Services)', icon: 'fa-plug' },
    ];

    body.innerHTML = settings.map(s => {
        const enabled = cas[s.key] === true;
        return '<div class="d-flex align-items-center gap-1" style="padding:2px 0">' +
            '<i class="fas ' + s.icon + '" style="font-size:11px;color:var(--text-muted);width:14px;text-align:center"></i>' +
            '<span style="font-size:10px;flex:1">' + s.label + '</span>' +
            '<span class="badge bg-' + (enabled ? 'success' : 'secondary') + '" style="font-size:9px;min-width:50px">' + (enabled ? 'Enabled' : 'Disabled') + '</span>' +
            '<button class="cas-toggle-btn" data-cas-setting="' + s.key + '" data-cas-enabled="' + (enabled ? '1' : '0') + '" style="background:none;border:1px solid var(--border-color);border-radius:3px;padding:1px 8px;font-size:9px;cursor:pointer;color:' + (enabled ? '#dc3545' : '#198754') + '">' + (enabled ? 'Disable' : 'Enable') + '</button>' +
        '</div>';
    }).join('') + '<div class="mt-1"><button id="mbMobileDevicesBtn" style="background:none;border:1px solid var(--border-color);border-radius:4px;color:var(--text-muted);font-size:10px;padding:2px 8px;cursor:pointer" data-noc-tip="View mobile devices paired with this mailbox"><i class="fas fa-phone"></i> View mobile devices</button></div>';

    // Event delegation on body — survives innerHTML replacement
    if (!body._casListener) {
        body.addEventListener('click', function(e) {
            var btn = e.target.closest('.cas-toggle-btn');
            if (!btn) {
                if (e.target.closest('#mbMobileDevicesBtn')) {
                    var id = document.getElementById('manualUsername')?.value.trim();
                    if (id) loadMobileDevices(id);
                }
                return;
            }
            var setting = btn.dataset.casSetting;
            var currentlyEnabled = btn.dataset.casEnabled === '1';
            var newEnabled = !currentlyEnabled;
            var identity = document.getElementById('manualUsername')?.value.trim();
            if (!identity) return;

            var fb = document.getElementById('mbCasFeedback');
            if (fb) fb.textContent = 'Updating...';

            var params = new URLSearchParams({
                action: 'set_cas',
                identity: identity,
                setting: setting,
                enabled: newEnabled ? '1' : '0'
            });

            fetch((window.APP_CONFIG?.baseUrl || baseURL) + '/api/index.php?endpoint=modify_mailbox', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window._csrfToken || window.APP_CONFIG?.csrfToken || '' },
                body: params.toString()
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (fb) { fb.textContent = d.message || (d.success ? 'Done' : 'Failed'); fb.className = 'small text-' + (d.success ? 'success' : 'danger'); }
                if (d.success) loadCasStatus(identity);
            })
            .catch(function() { if (fb) { fb.textContent = 'Error'; fb.className = 'small text-danger'; } });
        });
        body._casListener = true;
    }
}

function renderMobileDeviceSection(devices) {
    if (!devices || devices.length === 0) {
        const body = document.getElementById('mbMobileDeviceBody');
        if (body) body.innerHTML = '<span class="text-muted" style="font-size:10px">No mobile devices paired with this mailbox.</span>';
        return;
    }
    const html = devices.map(function(d) {
        return `<div style="font-size:10px;border-bottom:1px solid var(--border-color);padding:4px 0">
            <div><strong>${escHtml(d.friendly_name)}</strong> <span class="badge bg-${d.status === 'OK' ? 'success' : 'secondary'}" style="font-size:8px">${escHtml(d.status)}</span></div>
            <div style="color:var(--text-muted)">${d.device_type ? escHtml(d.device_type) + ' / ' : ''}${d.device_os ? escHtml(d.device_os) : ''}</div>
            <div style="color:var(--text-muted)">Last sync: ${d.last_sync_time ? new Date(d.last_sync_time).toLocaleString() : 'never'}</div>
        </div>`;
    }).join('');
    const body = document.getElementById('mbMobileDeviceBody');
    if (body) body.innerHTML = html;
}

function loadMobileDevices(identity) {
    // Create modal if not exists
    let modal = document.getElementById('mbMobileDeviceModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'mbMobileDeviceModal';
        modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;z-index:99999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center';
        modal.innerHTML = '<div style="background:var(--bg-color, #fff);border-radius:8px;max-width:500px;width:90%;max-height:80vh;overflow-y:auto;padding:16px;box-shadow:0 4px 24px rgba(0,0,0,0.3)">' +
            '<div class="d-flex justify-content-between align-items-center mb-2"><strong style="font-size:13px"><i class="fas fa-phone"></i> Mobile Devices</strong><button id="mbMobileDeviceClose" style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--text-muted)">&times;</button></div>' +
            '<div id="mbMobileDeviceBody" style="font-size:10px">Loading...</div></div>';
        document.body.appendChild(modal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal || e.target.id === 'mbMobileDeviceClose') {
                modal.style.display = 'none';
            }
        });
    }
    modal.style.display = 'flex';

    const body = document.getElementById('mbMobileDeviceBody');
    if (body) body.innerHTML = 'Loading...';

    const baseUrl = window.APP_CONFIG?.baseUrl || (typeof baseURL === 'string' ? baseURL : '');
    fetch(baseUrl + '/api/index.php?endpoint=modify_mailbox', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window._csrfToken || window.APP_CONFIG?.csrfToken || '' },
        body: 'action=get_mobile_devices&identity=' + encodeURIComponent(identity)
    })
    .then(r => r.json())
    .then(d => {
        window._lastMobileDevices = d.devices || [];
        renderMobileDeviceSection(window._lastMobileDevices);
    })
    .catch(function() {
        if (body) body.innerHTML = '<span class="text-danger" style="font-size:10px">Failed to load mobile devices.</span>';
    });
}

function loadCasStatus(identity) {
    const baseUrl = window.APP_CONFIG?.baseUrl || (typeof baseURL === 'string' ? baseURL : '');
    fetch(baseUrl + '/api/index.php?endpoint=modify_mailbox', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window._csrfToken || window.APP_CONFIG?.csrfToken || '' },
        body: 'action=get_cas&identity=' + encodeURIComponent(identity)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) renderCasSection(d.cas);
    })
    .catch(() => {});
}

function saveMbProfile(identity) {
    const baseUrl = window.APP_CONFIG?.baseUrl || (typeof baseURL === 'string' ? baseURL : '');
    const fb = document.getElementById('mbProfileFeedback');
    if (fb) fb.textContent = 'Saving...';

    const fields = {
        givenName: document.getElementById('exFirstName')?.value.trim() || '',
        sn: document.getElementById('exLastName')?.value.trim() || '',
        displayName: document.getElementById('exDisplayName')?.value.trim() || '',
        alias: document.getElementById('exAlias')?.value.trim() || '',
        fullName: document.getElementById('exFullName')?.value.trim() || '',
        title: document.getElementById('exTitle')?.value.trim() || '',
        department: document.getElementById('exDepartment')?.value.trim() || '',
        company: document.getElementById('exCompany')?.value.trim() || '',
        physicalDeliveryOfficeName: document.getElementById('exOffice')?.value.trim() || '',
        telephoneNumber: document.getElementById('exPhone')?.value.trim() || '',
    };
    // Also read additional profile fields from the form if they exist
    document.querySelectorAll('.mbProfInp').forEach(inp => {
        fields[inp.dataset.attr] = inp.value.trim();
    });

    const params = new URLSearchParams({ action: 'save_profile', identity: identity });
    Object.keys(fields).forEach(k => { params.append(k, fields[k]); });

    // logon name (rename sAMAccountName)
    const newLogon = document.getElementById('exLogonName')?.value.trim();
    if (newLogon) params.append('samAccountName', newLogon);

    // OU + mailbox database changes
    const exOUHidden = document.getElementById('exOU');
    if (exOUHidden?.value) params.append('ou', exOUHidden.value);
    const exDb = document.getElementById('exMailboxDb');
    if (exDb?.value) params.append('mailbox_database', exDb.value);

    fetch(baseUrl + '/api/index.php?endpoint=modify_mailbox', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window._csrfToken || window.APP_CONFIG?.csrfToken || '' },
        body: params.toString()
    })
    .then(r => r.json())
    .then(d => {
        if (fb) { fb.textContent = d.message || (d.success ? 'Saved' : 'Failed'); fb.className = 'ms-2 small text-' + (d.success ? 'success' : 'danger'); }
        if (d.success) { const u = document.getElementById('manualUsername')?.value.trim(); if (u) setTimeout(() => window.fetchAndPopulateUserData(u), 1500); }
    })
    .catch(() => { if (fb) { fb.textContent = 'Error'; fb.className = 'ms-2 small text-danger'; } });
}

function addMbEmail(identity) {
    const input = document.getElementById('mbNewEmailInput');
    const email = input?.value.trim();
    if (!identity || !email) return;
    doMbModifyAction('add_email', identity, email, 'mbEmailFeedback');
}

function saveMbQuota(identity) {
    const warn = document.getElementById('mbQuotaWarn')?.value || '5';
    const send = document.getElementById('mbQuotaSend')?.value || '6';
    const recv = document.getElementById('mbQuotaRecv')?.value || '8';
    const unit = document.getElementById('mbQuotaUnit')?.value || 'GB';
    doMbModifyAction('set_quota', identity, '', 'mbQuotaFeedback', { issue_warning_quota: warn, prohibit_send_quota: send, prohibit_send_receive_quota: recv, quota_unit: unit });
}

function setMbForward(identity, isSet) {
    if (isSet) {
        const fwd = document.getElementById('mbFwdInput')?.value.trim();
        if (!fwd) return;
        doMbModifyAction('set_forward', identity, '', 'mbActionsFeedback', { forward_to: fwd, deliver_to_mailbox: '1' });
    } else {
        doMbModifyAction('set_forward', identity, '', 'mbActionsFeedback', { forward_to: '', deliver_to_mailbox: '1' });
    }
}

function renderMbActionsStatus(mailboxDisabled, litHoldEnabled) {
    var mbStatusEl = document.getElementById('mbStatusDisplay');
    var mbBtn = document.getElementById('mbToggleMailboxBtn');
    if (mbStatusEl) {
        mbStatusEl.textContent = mailboxDisabled ? 'Disabled' : 'Enabled';
        mbStatusEl.className = 'badge bg-' + (mailboxDisabled ? 'danger' : 'success');
    }
    if (mbBtn) {
        mbBtn.textContent = mailboxDisabled ? 'Enable' : 'Disable';
        mbBtn.style.color = mailboxDisabled ? '#198754' : '#dc3545';
        if (mailboxDisabled) mbBtn.dataset.nocTip = 'Re-enable the Exchange mailbox for this user';
        else mbBtn.dataset.nocTip = 'Disable the Exchange mailbox — user keeps AD account but loses email access';
    }
    var litStatusEl = document.getElementById('mbLitStatusDisplay');
    var litBtn = document.getElementById('mbToggleLitBtn');
    if (litStatusEl) {
        litStatusEl.textContent = litHoldEnabled ? 'On' : 'Off';
        litStatusEl.className = 'badge bg-' + (litHoldEnabled ? 'warning' : 'secondary');
    }
    if (litBtn) {
        litBtn.textContent = litHoldEnabled ? 'Disable' : 'Enable';
        litBtn.style.color = litHoldEnabled ? '#dc3545' : '#ffc107';
    }
    if (typeof NocTooltip !== 'undefined' && NocTooltip.init) NocTooltip.init();
}

function toggleMbMailbox(identity) {
    var btn = document.getElementById('mbToggleMailboxBtn');
    var isDisabled = btn ? btn.textContent === 'Enable' : false;
    var fb = document.getElementById('mbActionsFeedback2');
    if (fb) { fb.textContent = ''; fb.className = 'small'; }

    if (isDisabled) {
        // Enable mailbox — safe, no confirmation needed
        doMbModifyAction('enable_mailbox', identity, '', 'mbActionsFeedback2');
    } else {
        // Disable mailbox — DANGEROUS: user loses email access
        // Require typing the username to confirm
        var name = prompt('⚠️ DANGER: This will permanently disable the Exchange mailbox.\nThe user will lose email access.\n\nType the username to confirm:\n("' + identity + '")');
        if (name !== identity) {
            if (fb) { fb.textContent = 'Cancelled — name did not match.'; fb.className = 'small text-warning'; }
            return;
        }
        doMbModifyAction('disable_mailbox', identity, '', 'mbActionsFeedback2');
    }
}

function toggleMbLitigationHold(identity) {
    var btn = document.getElementById('mbToggleLitBtn');
    var enable = btn ? btn.textContent === 'Enable' : false;
    if (enable) {
        if (!confirm('Enable Litigation Hold for ' + identity + '?\n\nLitigation Hold preserves all mailbox content including deleted/edited items for e-discovery. This is a safety feature — no data loss.')) return;
    } else {
        if (!confirm('Disable Litigation Hold for ' + identity + '?\n\nDeleted/edited items will no longer be preserved. Existing preserved items remain accessible to e-discovery until the hold is removed by legal/compliance.')) return;
    }
    doMbModifyAction('set_litigation_hold', identity, '', 'mbActionsFeedback2', { enabled: enable ? '1' : '0' });
}

function toggleMbArchive(identity, enable) {
    const fb = document.getElementById('mbArchiveFeedback');
    if (fb) fb.textContent = 'Processing...';
    const baseUrl = window.APP_CONFIG?.baseUrl || (typeof baseURL === 'string' ? baseURL : '');
    const action = enable ? 'enable_archive' : 'disable_archive';

    fetch(baseUrl + '/api/index.php?endpoint=modify_mailbox', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window._csrfToken || window.APP_CONFIG?.csrfToken || '' },
        body: 'action=' + action + '&identity=' + encodeURIComponent(identity)
    })
    .then(r => r.json())
    .then(d => {
        if (fb) { fb.textContent = d.message || (d.success ? 'Done' : 'Failed'); fb.className = 'small text-' + (d.success ? 'success' : 'danger'); }
        if (d.success) {
            const u = document.getElementById('manualUsername')?.value.trim();
            if (u) setTimeout(() => window.fetchAndPopulateUserData(u), 1500);
        }
    })
    .catch(() => { if (fb) { fb.textContent = 'Error'; fb.className = 'small text-danger'; } });
}

function doMbModifyAction(action, identity, extraValue, feedbackId, extraParams) {
    const baseUrl = window.APP_CONFIG?.baseUrl || (typeof baseURL === 'string' ? baseURL : '');
    const fb = document.getElementById(feedbackId);
    if (fb) fb.textContent = 'Processing...';

    const params = { action: action, identity: identity };
    if (extraValue) params.email = extraValue;
    if (extraParams) Object.assign(params, extraParams);

    const body = new URLSearchParams(params);

    fetch(baseUrl + '/api/index.php?endpoint=modify_mailbox', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window._csrfToken || window.APP_CONFIG?.csrfToken || '' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(d => {
        if (fb) { fb.textContent = d.message || (d.success ? 'Done' : 'Failed'); fb.className = 'ms-2 small text-' + (d.success ? 'success' : 'danger'); }
        if (d.success) {
            const u = document.getElementById('manualUsername')?.value.trim();
            if (u) setTimeout(() => window.fetchAndPopulateUserData(u), 1500);
        }
    })
    .catch(() => { if (fb) { fb.textContent = 'Error'; fb.className = 'ms-2 small text-danger'; } });
}
