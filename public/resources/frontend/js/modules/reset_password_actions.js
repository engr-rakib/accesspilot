document.addEventListener('DOMContentLoaded', function() {
    const resolvedBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');
    const resetPasswordApiUrl = `${resolvedBaseUrl}/api/index.php?endpoint=reset_password_api`;

    const resetPasswordModalElement = document.getElementById('resetPasswordModal');
    const defaultPasswordCheck = document.getElementById('default_password_check');
    const newPasswordGroup = document.getElementById('new_password_group');
    const newPasswordInput = document.getElementById('new_password');
    const resetPasswordForm = document.getElementById('resetPasswordForm');

    const actionTakenCardContainer = document.getElementById('actionTakenCardContainer');
    const actionTakenCardContent = actionTakenCardContainer ? actionTakenCardContainer.querySelector('.card-body') : null;
    const actionTakenTitleSpan = actionTakenCardContent ? actionTakenCardContent.querySelector('h2 span') : null;
    const actionTakenMessageDiv = actionTakenCardContent ? actionTakenCardContent.querySelector('.copy-content') : null;
    const actionTakenMessageDisplay = actionTakenCardContent ? actionTakenCardContent.querySelector('#actionTakenMessageDisplay') : null;

    function showLoadingMessage(title) {
        if (actionTakenCardContainer) {
            actionTakenCardContainer.style.display = 'block';
            actionTakenTitleSpan.textContent = title;
            if (typeof window.showLoadingAnimation === 'function') {
                window.showLoadingAnimation(actionTakenMessageDisplay);
            } else {
                actionTakenMessageDisplay.innerHTML = `
                    <div class="alert-loading-content">
                        <div class="loading-dots">
                            <span></span><span></span><span></span>
                        </div>
                        <div class="loading-text">Your request is underway...</div>
                    </div>`;
            }
            actionTakenMessageDisplay.className = 'alert alert-info';
            actionTakenCardContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function displayApiResponse(title, data) {
        if (actionTakenCardContainer) {
            actionTakenCardContainer.style.display = 'block';
            actionTakenTitleSpan.textContent = title;
            actionTakenMessageDisplay.innerHTML = data.message;
            actionTakenMessageDisplay.className = data.success ? 'alert alert-success' : 'alert alert-danger';
            if(actionTakenMessageDiv) {
                actionTakenMessageDiv.innerHTML = data.message;
            }
            const copyButton = actionTakenCardContent.querySelector('.btn-copy');
            if (copyButton) {
                bootstrap.Tooltip.getOrCreateInstance(copyButton);
            }
        }
    }

    function handleFetchError(title, error) {
        console.error(`Error during ${title} fetch:`, error);
        if (actionTakenCardContainer) {
            actionTakenCardContainer.style.display = 'block';
            actionTakenTitleSpan.textContent = 'Error';
            actionTakenMessageDisplay.innerHTML = `An error occurred: ${error.message}`;
            actionTakenMessageDisplay.className = 'alert alert-danger';
        }
    }

    let myModal = null;
    if (resetPasswordModalElement) {
        myModal = new bootstrap.Modal(resetPasswordModalElement);
    }

    const resetPasswordTriggerButtons = document.querySelectorAll('.reset-password-trigger-btn');

    resetPasswordTriggerButtons.forEach(button => {
        button.addEventListener('click', function() {
            const username = this.getAttribute('data-username');

            const modalUsernameInput = resetPasswordModalElement.querySelector('#reset-username');
            if (modalUsernameInput) {
                modalUsernameInput.value = username;
            }

            defaultPasswordCheck.checked = false;
            newPasswordGroup.style.display = 'block';
            newPasswordInput.required = true;
            newPasswordInput.value = '';

            if (resetPasswordModalElement) {
                const showEvent = new Event('show.bs.modal', { bubbles: true, cancelable: true });
                resetPasswordModalElement.dispatchEvent(showEvent);
            }

            if (myModal) {
                myModal.show();
            }
        });
    });

    if (resetPasswordForm) {
        resetPasswordForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const username = resetPasswordModalElement.querySelector('#reset-username').value;
            const useDefaultPassword = defaultPasswordCheck.checked;
            const newPassword = newPasswordInput.value;

            if (!useDefaultPassword && newPassword.trim() === '') {
                alert('Please enter a new password or select "Use Default Password".');
                return;
            }

            const bootstrapModal = bootstrap.Modal.getInstance(resetPasswordModalElement);
            if (bootstrapModal) {
                document.body.focus();
                setTimeout(() => {
                    bootstrapModal.hide();
                }, 50);
            }

            if (typeof showLoadingMessage === 'function') {
                showLoadingMessage('Resetting Password');
            } else {
                console.error('reset_password_actions.js: showLoadingMessage function not found.');
            }

            try {
                const payload = { username: username };
                if (useDefaultPassword) {
                    payload.use_default_password = true;
                } else {
                    payload.new_password = newPassword;
                }

                const responsePromise = fetch(resetPasswordApiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    }).then(response => response.json());

                const [apiData] = await Promise.all([
                    responsePromise,
                    new Promise(resolve => setTimeout(resolve, 1500))
                ]);

                if (typeof displayApiResponse === 'function') {
                    displayApiResponse('Reset Password', apiData);
                } else {
                    console.error('reset_password_actions.js: displayApiResponse function not found.');
                }

            } catch (error) {
                console.error('reset_password_actions.js: Fetch Error:', error);
                if (typeof handleFetchError === 'function') {
                    handleFetchError('Reset Password', error);
                } else {
                    console.error('reset_password_actions.js: handleFetchError function not found.');
                }
            }
        });
    }
});
