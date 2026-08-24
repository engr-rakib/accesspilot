document.addEventListener('DOMContentLoaded', function() {
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');

    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            const actionTakenCardContainer = document.getElementById('actionTakenCardContainer');
            const actionTakenTitleSpan = document.getElementById('actionTakenTitle');
            const actionTakenMessageDisplay = document.getElementById('actionTakenMessageDisplay');

            if (actionTakenCardContainer) {
                actionTakenCardContainer.style.display = 'block';
                actionTakenTitleSpan.textContent = 'Sending Reset Link';
                actionTakenMessageDisplay.innerHTML = '<div class="alert-loading-content"><div class="loading-dots"><span style="background-color: #1976D2;"></span><span style="background-color: #AA3A46;"></span><span style="background-color: #1B5E20;"></span></div><div class="loading-text">Processing your request...</div></div>';
                actionTakenMessageDisplay.className = 'alert alert-info';
            }

            try {
                const response = await fetch('forgot_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (actionTakenCardContainer) {
                    actionTakenTitleSpan.textContent = result.success ? 'Success' : 'Error';
                    actionTakenMessageDisplay.innerHTML = result.message;
                    actionTakenMessageDisplay.className = result.success ? 'alert alert-success' : 'alert alert-danger';
                }

            } catch (error) {
                if (actionTakenCardContainer) {
                    actionTakenTitleSpan.textContent = 'Error';
                    actionTakenMessageDisplay.innerHTML = 'An error occurred: ' + error.message;
                    actionTakenMessageDisplay.className = 'alert alert-danger';
                }
            }
        });
    }
});