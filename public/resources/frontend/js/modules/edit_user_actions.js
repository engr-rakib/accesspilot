window.initEditUser = function() {
    const resolvedBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');
    const userManagementPageUrl = (window.APP_CONFIG && window.APP_CONFIG.userManagementPageUrl) || 'index.php?page=user_management';
    const userManagementApiUrl = `${resolvedBaseUrl}/api/index.php?endpoint=user_management_action`;
    const editUserForm = document.getElementById('editUserForm');
    if (!editUserForm || editUserForm.dataset.initialized === 'true') {
        return;
    }
    editUserForm.dataset.initialized = 'true';

    const actionTakenCardContainer = document.getElementById('actionTakenCardContainer');
    const actionTakenTitleSpan = document.getElementById('actionTakenTitle');
    const actionTakenMessageDisplay = document.getElementById('actionTakenMessageDisplay');
    const passwordToggleButtons = editUserForm.querySelectorAll('.password-toggle-btn');
    const useDefaultPasswordCheckbox = editUserForm.querySelector('#use_default_password');
    const temporaryPasswordInput = editUserForm.querySelector('#temporary_password');
    const updatePasswordOnlyBtn = document.getElementById('updatePasswordOnlyBtn');
    const fetchHrmsBtn = document.getElementById('fetchHrmsDataBtn');
    const hrmsStatusInput = document.getElementById('hrms_status');
    const hrmsIdField = document.getElementById('hrms_id');
    const usernameField = document.getElementById('new_username');

    function getHrmsIdentifier() {
        const storedLookupId = hrmsIdField ? (hrmsIdField.dataset.lookupId || '').trim() : '';
        const hrmsId = hrmsIdField ? hrmsIdField.value.trim() : '';
        const loginId = usernameField ? usernameField.value.trim() : '';
        return hrmsId || storedLookupId || loginId || editUserForm.querySelector('input[name="old_username"]')?.value || '';
    }

    function applyHrmsData(api, fetchIdentifier) {
        const getValue = (...keys) => {
            for (const key of keys) {
                if (api[key] !== undefined && api[key] !== null && api[key] !== '') {
                    return api[key];
                }
            }
            return '';
        };

        const mappings = [
            ['full_name', getValue('EMP_NAME', 'FULL_NAME', 'NAME')],
            ['email', getValue('EMAIL', 'EMAIL_ADDRESS')],
            ['mobile', getValue('MOBILE', 'MOBILE_NO', 'PHONE')],
            ['hrms_id', getValue('EMP_CODE', 'HRMS_ID', 'EMP_ID', fetchIdentifier)],
            ['designation', getValue('DESIGNATION', 'DESIGNATION_TITLE')],
            ['designation_order', getValue('DESIGNATION_ORDER', 'RANK')],
            ['operating_unit', getValue('OPERATING_UNIT_TITLE', 'OPERATING_UNIT')],
            ['location', getValue('LOCATION_TITLE', 'LOCATION')],
            ['department', getValue('DEPARTMENT_TITLE', 'DEPARTMENT')],
            ['section', getValue('SECTION_TITLE', 'SECTION')],
            ['product', getValue('PRODUCT_TITLE', 'PRODUCT')],
            ['sub_section', getValue('SUB_SECTION_TITLE', 'SUB_SECTION')],
            ['joining_date', getValue('JOINING_DT', 'JOINING_DATE')],
            ['dob', getValue('DOB', 'DATE_OF_BIRTH')],
            ['gender', getValue('GENDER', 'SEX')],
            ['age', getValue('AGE')],
        ];

        mappings.forEach(([fieldId, value]) => {
            const field = document.getElementById(fieldId);
            if (field && value !== '') {
                field.value = value;
            }
        });

        if (hrmsIdField) {
            const displayHrmsId = getValue('EMP_CODE', 'HRMS_ID', 'EMP_ID');
            const lookupHrmsId = getValue('EMP_CODE', 'HRMS_ID', 'EMP_ID', fetchIdentifier);
            if (displayHrmsId !== '') {
                hrmsIdField.value = displayHrmsId;
            }
            hrmsIdField.dataset.lookupId = lookupHrmsId || fetchIdentifier;
        }

        if (hrmsStatusInput) {
            const liveStatus = getValue('EMP_STS', 'HRMS_STATUS', 'STATUS');
            hrmsStatusInput.value = liveStatus || 'Not available';
        }
    }

    async function fetchHrmsData(showCard = false) {
        const fetchIdentifier = getHrmsIdentifier();
        if (!fetchIdentifier || !resolvedBaseUrl) {
            return;
        }

        if (hrmsStatusInput) {
            hrmsStatusInput.value = 'Loading...';
        }

        if (fetchHrmsBtn) {
            fetchHrmsBtn.disabled = true;
            fetchHrmsBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Fetching...';
        }

        try {
            const response = await fetch(`${resolvedBaseUrl}/api/index.php?endpoint=execute_action`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `username=${encodeURIComponent(fetchIdentifier)}&action=info&part=hrms_info`
            });
            const data = await response.json();
            const api = data?.data?.apiData || null;

            if (data.success && api && typeof api === 'object') {
                applyHrmsData(api, fetchIdentifier);

                if (showCard && actionTakenCardContainer && actionTakenTitleSpan && actionTakenMessageDisplay) {
                    actionTakenCardContainer.style.display = 'block';
                    actionTakenCardContainer.classList.add('visible');
                    actionTakenTitleSpan.textContent = 'Update from HRMS';
                    actionTakenMessageDisplay.innerHTML = 'User data refreshed from HRMS.';
                    actionTakenMessageDisplay.className = 'alert alert-success';
                }
            } else {
                if (hrmsStatusInput) {
                    hrmsStatusInput.value = 'Not available';
                }
                if (showCard && actionTakenCardContainer && actionTakenTitleSpan && actionTakenMessageDisplay) {
                    actionTakenCardContainer.style.display = 'block';
                    actionTakenCardContainer.classList.add('visible');
                    actionTakenTitleSpan.textContent = 'Update from HRMS';
                    actionTakenMessageDisplay.innerHTML = data.message || 'Failed to fetch HRMS data.';
                    actionTakenMessageDisplay.className = 'alert alert-danger';
                }
            }
        } catch (error) {
            if (hrmsStatusInput) {
                hrmsStatusInput.value = 'Error';
            }
            if (showCard && actionTakenCardContainer && actionTakenTitleSpan && actionTakenMessageDisplay) {
                actionTakenCardContainer.style.display = 'block';
                actionTakenCardContainer.classList.add('visible');
                actionTakenTitleSpan.textContent = 'Update from HRMS';
                actionTakenMessageDisplay.innerHTML = `An error occurred while fetching HRMS data: ${error.message}`;
                actionTakenMessageDisplay.className = 'alert alert-danger';
            }
        } finally {
            if (fetchHrmsBtn) {
                fetchHrmsBtn.disabled = false;
                fetchHrmsBtn.innerHTML = '<i class="fas fa-sync-alt me-1"></i> Update from HRMS';
            }
        }
    }

    async function updatePasswordOnly() {
        const usernameToReset = (usernameField ? usernameField.value.trim() : '') || editUserForm.querySelector('input[name="old_username"]')?.value || '';
        const useDefaultPassword = useDefaultPasswordCheckbox?.checked || false;
        const newPassword = temporaryPasswordInput ? temporaryPasswordInput.value.trim() : '';
        const forcePasswordChange = editUserForm.querySelector('#force_password_change')?.checked || false;

        if (!usernameToReset) {
            return;
        }

        if (!useDefaultPassword && newPassword === '') {
            if (actionTakenCardContainer && actionTakenTitleSpan && actionTakenMessageDisplay) {
                actionTakenCardContainer.style.display = 'block';
                actionTakenTitleSpan.textContent = 'Update Password';
                actionTakenMessageDisplay.innerHTML = 'New password dao, or `Use default password` check koro.';
                actionTakenMessageDisplay.className = 'alert alert-danger';
            }
            return;
        }

        if (updatePasswordOnlyBtn) {
            updatePasswordOnlyBtn.disabled = true;
            updatePasswordOnlyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Updating...';
        }

        if (actionTakenCardContainer && actionTakenTitleSpan && actionTakenMessageDisplay) {
            actionTakenCardContainer.style.display = 'block';
            actionTakenCardContainer.classList.add('visible');
            actionTakenTitleSpan.textContent = 'Update Password';
            actionTakenMessageDisplay.innerHTML = '<div class="alert-loading-content"><div class="loading-dots"><span style="background-color: #1976D2;"></span><span style="background-color: #AA3A46;"></span><span style="background-color: #1B5E20;"></span></div><div class="loading-text">Applying password change...</div></div>';
            actionTakenMessageDisplay.className = 'alert alert-info';
        }

        try {
            const resetResponse = await fetch(userManagementApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'reset_password',
                    username: usernameToReset,
                    new_password: newPassword,
                    use_default_password: useDefaultPassword,
                    force_password_change: forcePasswordChange
                })
            });

            const resetResult = await resetResponse.json();
            if (actionTakenCardContainer && actionTakenTitleSpan && actionTakenMessageDisplay) {
                actionTakenCardContainer.style.display = 'block';
                actionTakenCardContainer.classList.add('visible');
                actionTakenTitleSpan.textContent = resetResult.success ? 'Update Password' : 'Error';
                actionTakenMessageDisplay.innerHTML = resetResult.message || 'Password update failed.';
                actionTakenMessageDisplay.className = resetResult.success ? 'alert alert-success' : 'alert alert-danger';
            }

            if (resetResult.success && temporaryPasswordInput) {
                temporaryPasswordInput.value = '';
            }

            if (resetResult.success && useDefaultPasswordCheckbox) {
                useDefaultPasswordCheckbox.checked = false;
                temporaryPasswordInput.disabled = false;
            }
        } catch (error) {
            if (actionTakenCardContainer && actionTakenTitleSpan && actionTakenMessageDisplay) {
                actionTakenCardContainer.style.display = 'block';
                actionTakenTitleSpan.textContent = 'Error';
                actionTakenMessageDisplay.innerHTML = `An error occurred: ${error.message}`;
                actionTakenMessageDisplay.className = 'alert alert-danger';
            }
        } finally {
            if (updatePasswordOnlyBtn) {
                updatePasswordOnlyBtn.disabled = false;
                updatePasswordOnlyBtn.innerHTML = '<i class="fas fa-key me-1"></i> Update Password';
            }
        }
    }

    passwordToggleButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.dataset.toggleTarget;
            const input = targetId ? document.getElementById(targetId) : null;
            const icon = button.querySelector('i');
            if (!input || !icon) {
                return;
            }

            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            icon.className = showing ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
    });

    if (useDefaultPasswordCheckbox && temporaryPasswordInput) {
        useDefaultPasswordCheckbox.addEventListener('change', () => {
            temporaryPasswordInput.disabled = useDefaultPasswordCheckbox.checked;
            if (useDefaultPasswordCheckbox.checked) {
                temporaryPasswordInput.value = '';
            }
        });
        temporaryPasswordInput.disabled = useDefaultPasswordCheckbox.checked;
    }

    if (fetchHrmsBtn) {
        fetchHrmsBtn.addEventListener('click', () => fetchHrmsData(true));
    }

    if (updatePasswordOnlyBtn) {
        updatePasswordOnlyBtn.addEventListener('click', updatePasswordOnly);
    }

    if (hrmsStatusInput && hrmsStatusInput.value.trim() === '') {
        fetchHrmsData(false);
    }

    editUserForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(editUserForm);
        const data = Object.fromEntries(formData.entries());
        data.action = 'update_user';
        data.system_access = editUserForm.querySelector('#system_access')?.checked || false;
        data.force_password_change = editUserForm.querySelector('#force_password_change')?.checked || false;

        const temporaryPassword = (data.temporary_password || '').trim();
        delete data.temporary_password;

        const submitBtn = editUserForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';
        }

        if (actionTakenCardContainer && actionTakenTitleSpan && actionTakenMessageDisplay) {
            actionTakenCardContainer.style.display = 'block';
            actionTakenCardContainer.classList.add('visible');
            actionTakenTitleSpan.textContent = 'Updating User';
            if (typeof window.showLoadingAnimation === 'function') {
                window.showLoadingAnimation(actionTakenMessageDisplay);
            } else {
                actionTakenMessageDisplay.innerHTML = '<div class="alert-loading-content"><div class="loading-dots"><span style="background-color: #1976D2;"></span><span style="background-color: #AA3A46;"></span><span style="background-color: #1B5E20;"></span></div><div class="loading-text">Your request is underway...</div></div>';
            }
            actionTakenMessageDisplay.className = 'alert alert-info';
        }

        try {
            const updateResponse = await fetch(userManagementApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const updateResult = await updateResponse.json();
            let finalResult = updateResult;

            const useDefaultPassword = useDefaultPasswordCheckbox?.checked || false;
            if (updateResult.success && (temporaryPassword !== '' || useDefaultPassword)) {
                if (actionTakenCardContainer && actionTakenTitleSpan && actionTakenMessageDisplay) {
                    actionTakenTitleSpan.textContent = 'Updating Password';
                    actionTakenMessageDisplay.innerHTML = '<div class="alert-loading-content"><div class="loading-dots"><span style="background-color: #1976D2;"></span><span style="background-color: #AA3A46;"></span><span style="background-color: #1B5E20;"></span></div><div class="loading-text">Applying temporary password...</div></div>';
                    actionTakenMessageDisplay.className = 'alert alert-warning';
                }

                const usernameToReset = (data.new_username || data.old_username || '').trim();
                const resetResponse = await fetch(userManagementApiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'reset_password',
                        username: usernameToReset,
                        new_password: temporaryPassword,
                        use_default_password: useDefaultPassword,
                        force_password_change: data.force_password_change
                    })
                });

                const resetResult = await resetResponse.json();
                if (resetResult.success) {
                    finalResult = {
                        success: true,
                        message: `${updateResult.message} Password updated for <strong>${usernameToReset}</strong>.`
                    };
                    editUserForm.querySelector('#temporary_password').value = '';
                    if (useDefaultPasswordCheckbox) {
                        useDefaultPasswordCheckbox.checked = false;
                        temporaryPasswordInput.disabled = false;
                    }
                } else {
                    finalResult = {
                        success: false,
                        message: `${updateResult.message} However, the temporary password could not be updated. ${resetResult.message || ''}`.trim()
                    };
                }
            }

            if (actionTakenCardContainer && actionTakenTitleSpan && actionTakenMessageDisplay) {
                actionTakenCardContainer.style.display = 'block';
                actionTakenCardContainer.classList.add('visible');
                actionTakenTitleSpan.textContent = finalResult.success ? 'Update User' : 'Error';
                actionTakenMessageDisplay.innerHTML = finalResult.message || (finalResult.success ? 'User updated successfully.' : 'Failed to update user.');
                actionTakenMessageDisplay.className = finalResult.success ? 'alert alert-success' : 'alert alert-danger';
                actionTakenCardContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            if (finalResult.success) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Update User';
                }
                window.setTimeout(() => {
                    if (window.loadSPAPage) {
                        window.loadSPAPage(userManagementPageUrl);
                        return;
                    }
                    window.location.href = userManagementPageUrl;
                }, 4500);
            } else if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Update User';
            }
        } catch (error) {
            if (actionTakenCardContainer && actionTakenTitleSpan && actionTakenMessageDisplay) {
                actionTakenCardContainer.style.display = 'block';
                actionTakenCardContainer.classList.add('visible');
                actionTakenTitleSpan.textContent = 'Error';
                actionTakenMessageDisplay.innerHTML = 'An error occurred: ' + error.message;
                actionTakenMessageDisplay.className = 'alert alert-danger';
                actionTakenCardContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Update User';
            }
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.initEditUser);
} else {
    window.initEditUser();
}
