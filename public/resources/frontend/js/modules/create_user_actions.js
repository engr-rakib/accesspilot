window.initCreateUser = function() {
    const createUserForm = document.getElementById('createUserForm');
    if (!createUserForm || createUserForm.dataset.initialized === 'true') {
        return;
    }
    createUserForm.dataset.initialized = 'true';

    const resolvedBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');
    const createUserApiUrl = `${resolvedBaseUrl}/api/index.php?endpoint=create_user_action`;
    const hrmsLookupUrl = `${resolvedBaseUrl}/api/index.php?endpoint=execute_action`;
    const userManagementPageUrl = (window.APP_CONFIG && window.APP_CONFIG.userManagementPageUrl) || `${resolvedBaseUrl}/index.php?page=user_management`;

    const actionTakenCardContainer = document.getElementById('actionTakenCardContainer');
    const actionTakenTitleSpan = document.getElementById('actionTakenTitle');
    const actionTakenMessageDisplay = document.getElementById('actionTakenMessageDisplay');
    const createFromHrmsCheck = document.getElementById('create_from_hrms_check');
    const manualFieldsContainer = document.getElementById('manual_fields_container');
    const fetchCreateHrmsDataBtn = document.getElementById('fetchCreateHrmsDataBtn');
    const hrmsIdInput = document.getElementById('hrms_id');
    const usernameInput = document.getElementById('username');
    const emailInput = document.getElementById('email');
    const systemAccessCheckbox = document.getElementById('system_access');
    const generalInfoHr = document.getElementById('general_info_hr');
    const generalInfoH3 = document.getElementById('general_info_h3');
    const hrmsFetchHint = document.getElementById('hrms_fetch_hint');

    const wrappers = {
        username: document.getElementById('username_wrapper'),
        fullName: document.getElementById('full_name_wrapper'),
        role: document.getElementById('role_wrapper'),
        email: document.getElementById('email_wrapper'),
        systemAccess: document.getElementById('system_access_wrapper'),
        enableMailbox: document.getElementById('enable_mailbox_wrapper'),
        mobile: document.getElementById('mobile_wrapper'),
        hrmsId: document.getElementById('hrms_id_wrapper'),
        designation: document.getElementById('designation_wrapper'),
        designationOrder: document.getElementById('designation_order_wrapper'),
        operatingUnit: document.getElementById('operating_unit_wrapper'),
        location: document.getElementById('location_wrapper'),
        department: document.getElementById('department_wrapper'),
        section: document.getElementById('section_wrapper'),
        product: document.getElementById('product_wrapper'),
        subSection: document.getElementById('sub_section_wrapper'),
        joiningDate: document.getElementById('joining_date_wrapper'),
        dob: document.getElementById('dob_wrapper'),
        gender: document.getElementById('gender_wrapper'),
        age: document.getElementById('age_wrapper')
    };

    function setActionCard(title, message, type) {
        if (!actionTakenCardContainer || !actionTakenTitleSpan || !actionTakenMessageDisplay) {
            return;
        }
        actionTakenCardContainer.style.display = 'block';
        actionTakenTitleSpan.textContent = title;
        actionTakenMessageDisplay.innerHTML = message;
        actionTakenMessageDisplay.className = `alert alert-${type}`;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function getHrmsValue(api, keys, fallback = '') {
        for (const key of keys) {
            if (api[key] !== undefined && api[key] !== null && api[key] !== '') {
                return api[key];
            }
        }
        return fallback;
    }

    function populateFromHrms(api, requestedIdentifier) {
        const fieldMap = [
            ['username', ['EMP_CODE', 'HRMS_ID', 'EMP_ID']],
            ['full_name', ['EMP_NAME', 'FULL_NAME', 'NAME']],
            ['email', ['EMAIL', 'EMAIL_ADDRESS']],
            ['mobile', ['MOBILE', 'MOBILE_NO', 'PHONE']],
            ['hrms_id', ['EMP_CODE', 'HRMS_ID', 'EMP_ID']],
            ['designation', ['DESIGNATION', 'DESIGNATION_TITLE']],
            ['designation_order', ['DESIGNATION_ORDER', 'RANK']],
            ['operating_unit', ['OPERATING_UNIT_TITLE', 'OPERATING_UNIT']],
            ['location', ['LOCATION_TITLE', 'LOCATION']],
            ['department', ['DEPARTMENT_TITLE', 'DEPARTMENT']],
            ['section', ['SECTION_TITLE', 'SECTION']],
            ['product', ['PRODUCT_TITLE', 'PRODUCT']],
            ['sub_section', ['SUB_SECTION_TITLE', 'SUB_SECTION']],
            ['joining_date', ['JOINING_DT', 'JOINING_DATE']],
            ['dob', ['DOB', 'DATE_OF_BIRTH']],
            ['gender', ['GENDER', 'SEX']],
            ['age', ['AGE']]
        ];

        fieldMap.forEach(([fieldId, keys]) => {
            const field = document.getElementById(fieldId);
            if (!field) {
                return;
            }
            const value = getHrmsValue(api, keys, fieldId === 'hrms_id' ? requestedIdentifier : '');
            if (value !== '') {
                field.value = value;
            }
        });
    }

    function toggleManualFields() {
        const hrmsOnlyMode = !!(createFromHrmsCheck && createFromHrmsCheck.checked);
        const allWrappers = Object.values(wrappers).filter(Boolean);

        allWrappers.forEach((wrapper) => {
            wrapper.style.display = 'block';
        });

        if (manualFieldsContainer) {
            manualFieldsContainer.style.display = 'block';
        }

        if (hrmsOnlyMode) {
            createUserForm.noValidate = true;
            allWrappers.forEach((wrapper) => {
                if (wrapper !== wrappers.hrmsId) {
                    wrapper.style.display = 'none';
                }
            });

            if (generalInfoHr) generalInfoHr.style.display = 'none';
            if (generalInfoH3) generalInfoH3.style.display = 'none';
            if (fetchCreateHrmsDataBtn) fetchCreateHrmsDataBtn.style.display = 'none';
            if (hrmsFetchHint) hrmsFetchHint.style.display = 'none';

            if (usernameInput) {
                usernameInput.required = false;
                usernameInput.value = '';
            }
            if (emailInput) {
                emailInput.required = false;
                emailInput.value = '';
            }
            if (systemAccessCheckbox) {
                systemAccessCheckbox.checked = false;
            }
            if (hrmsIdInput) {
                hrmsIdInput.required = true;
            }
        } else {
            createUserForm.noValidate = false;
            if (generalInfoHr) generalInfoHr.style.display = '';
            if (generalInfoH3) generalInfoH3.style.display = '';
            if (fetchCreateHrmsDataBtn) fetchCreateHrmsDataBtn.style.display = '';
            if (hrmsFetchHint) hrmsFetchHint.style.display = '';
            if (usernameInput) usernameInput.required = true;
            if (emailInput) emailInput.required = true;
            if (hrmsIdInput) hrmsIdInput.required = false;
            if (systemAccessCheckbox) systemAccessCheckbox.checked = true;
        }
    }

    async function fetchHrmsForCreate() {
        const identifier = hrmsIdInput ? hrmsIdInput.value.trim() : '';
        if (!identifier) {
            setActionCard('Create User', 'HRMS ID required.', 'danger');
            return;
        }

        if (fetchCreateHrmsDataBtn) {
            fetchCreateHrmsDataBtn.disabled = true;
            fetchCreateHrmsDataBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Fetching...';
        }

        try {
            const response = await fetch(hrmsLookupUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `username=${encodeURIComponent(identifier)}&action=info&part=hrms_info`
            });

            const result = await response.json();
            const api = result?.data?.apiData || null;

            if (!result.success || !api) {
                setActionCard('Create User', result.message || 'HRMS data not found.', 'danger');
                return;
            }

            populateFromHrms(api, identifier);
            setActionCard('Create User', 'HRMS data fetched and fields populated.', 'success');
        } catch (error) {
            setActionCard('Create User', `HRMS fetch failed: ${error.message}`, 'danger');
        } finally {
            if (fetchCreateHrmsDataBtn) {
                fetchCreateHrmsDataBtn.disabled = false;
                fetchCreateHrmsDataBtn.innerHTML = '<i class="fas fa-cloud-download-alt me-1"></i> Fetch';
            }
        }
    }

    if (createFromHrmsCheck) {
        createFromHrmsCheck.addEventListener('change', toggleManualFields);
    }

    if (fetchCreateHrmsDataBtn) {
        fetchCreateHrmsDataBtn.addEventListener('click', fetchHrmsForCreate);
    }

    toggleManualFields();

    createUserForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());

        setActionCard(
            'Creating User',
            typeof window.showLoadingAnimation === 'function' ? '' : '<div class="alert-loading-content"><div class="loading-dots"><span></span><span></span><span></span></div><div class="loading-text">Your request is underway...</div></div>',
            'info'
        );
        if (typeof window.showLoadingAnimation === 'function') {
            const msgDisplay = document.getElementById('actionTakenMessageDisplay');
            if (msgDisplay) window.showLoadingAnimation(msgDisplay);
        }

        try {
            const response = await fetch(createUserApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const raw = await response.text();
            let result = null;

            try {
                result = JSON.parse(raw);
            } catch (error) {
                setActionCard('Error', `Server response invalid: ${raw.substring(0, 300)}`, 'danger');
                return;
            }

            if (result.success) {
                setActionCard(
                    'Create User',
                    `${result.message}<br><br><a class="btn btn-sm btn-primary" href="${userManagementPageUrl}">Open User Management</a>`,
                    'success'
                );
                createUserForm.reset();
                if (createFromHrmsCheck) {
                    createFromHrmsCheck.checked = false;
                }
                toggleManualFields();
                return;
            }

            setActionCard('Error', result.message || 'User creation failed.', 'danger');
        } catch (error) {
            setActionCard('Error', `An error occurred: ${error.message}`, 'danger');
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.initCreateUser);
} else {
    window.initCreateUser();
}
