window.initChangePassword = function () {
    const resolvedBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');
    const userManagementApiUrl = `${resolvedBaseUrl}/api/index.php?endpoint=user_management_action`;
    const changePasswordForm = document.getElementById('changePasswordForm');
    if (!changePasswordForm || changePasswordForm.dataset.initialized === 'true') {
        return;
    }
    changePasswordForm.dataset.initialized = 'true';

    const submitBtn = document.getElementById('submitChangePassword');
    const currentPasswordInput = document.getElementById('current_password');
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const meterBar = document.getElementById('passwordMeterBar');
    const meterLabel = document.getElementById('passwordMeterLabel');

    const showResult = (title, message, isSuccess) => {
        if (typeof window.displayActionTakenResult === 'function') {
            window.displayActionTakenResult(title, message, isSuccess);
            const container = document.getElementById('actionTakenCardContainer');
            if (container) {
                container.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            return;
        }
        window.alert(message);
    };

    const updateRule = (ruleName, isValid) => {
        const ruleNode = document.querySelector(`.password-rule[data-rule="${ruleName}"]`);
        if (!ruleNode) {
            return;
        }
        ruleNode.classList.toggle('is-valid', isValid);
        const icon = ruleNode.querySelector('i');
        if (icon) {
            icon.className = isValid ? 'fas fa-check-circle' : 'fas fa-times-circle';
        }
    };

    const evaluatePassword = () => {
        const password = newPasswordInput ? newPasswordInput.value : '';
        const confirm = confirmPasswordInput ? confirmPasswordInput.value : '';

        const rules = {
            length: password.length >= 8,
            upper: /[A-Z]/.test(password),
            lower: /[a-z]/.test(password),
            number: /\d/.test(password),
            match: password.length > 0 && password === confirm
        };

        Object.entries(rules).forEach(([name, valid]) => updateRule(name, valid));

        // --- NEW: REACTIVE COMPLEXITY STATUS BOX ---
        const allValid = Object.values(rules).every(Boolean);
        const complexityWrapper = document.getElementById('passwordComplexityWrapper');
        if (complexityWrapper) {
            complexityWrapper.classList.toggle('complexity-valid', allValid);
        }

        const score = Object.values(rules).filter(Boolean).length;
        const width = `${Math.max(score * 20, password ? 18 : 0)}%`;
        if (meterBar) {
            meterBar.style.width = width;
        }
        if (meterLabel) {
            const labels = ['Password strength will appear here.', 'Very weak', 'Weak', 'Fair', 'Strong', 'Very strong'];
            meterLabel.textContent = password ? labels[score] : labels[0];
        }

        return rules;
    };

    changePasswordForm.querySelectorAll('.password-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.dataset.toggleTarget;
            const input = targetId ? document.getElementById(targetId) : null;
            const icon = button.querySelector('i');
            if (!input || !icon) {
                return;
            }

            const isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            icon.className = isVisible ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
    });

    [newPasswordInput, confirmPasswordInput].forEach((input) => {
        if (input) {
            input.addEventListener('input', evaluatePassword);
        }
    });
    evaluatePassword();

    changePasswordForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const currentPassword = currentPasswordInput ? currentPasswordInput.value : '';
        const newPassword = newPasswordInput ? newPasswordInput.value : '';
        const confirmPassword = confirmPasswordInput ? confirmPasswordInput.value : '';
        const rules = evaluatePassword();

        if (newPassword !== confirmPassword) {
            showResult('Password Mismatch', 'New password and confirmation do not match.', false);
            return;
        }

        if (!rules.length || !rules.upper || !rules.lower || !rules.number) {
            showResult('Weak Password', 'Use at least 8 characters with uppercase, lowercase, and a number.', false);
            return;
        }

        if (currentPassword === newPassword) {
            showResult('Password Reuse', 'New password must be different from the current password.', false);
            return;
        }

        submitBtn.disabled = true;
        const originalBtnContent = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Updating...';

        showResult('Change Password', 'Verifying your current password and saving the new one...', true);

        try {
            const response = await fetch(userManagementApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'change_own_password',
                    current_password: currentPassword,
                    new_password: newPassword
                })
            });

            const data = await response.json();
            showResult('Change Password', data.message, !!data.success);

            if (data.success) {
                changePasswordForm.reset();
                evaluatePassword();
            }
        } catch (error) {
            console.error('Error changing password:', error);
            showResult('Error', 'An unexpected error occurred: ' + error.message, false);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnContent;
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.initChangePassword);
} else {
    window.initChangePassword();
}
