document.addEventListener('DOMContentLoaded', function() {
    const actionButtons = document.querySelectorAll('.action-button');
    const usernameInput = document.getElementById('username');
    const serverUserInfoSection = document.querySelector('.output-section:nth-child(1)');
    const employeeInfoSection = document.querySelector('.output-section:nth-child(2)');
    const actionTakenCard = document.getElementById('actionTakenCard');
    const actionTakenTitleSpan = document.getElementById('actionTakenTitle');
    const actionTakenMessageDiv = actionTakenCard ? actionTakenCard.querySelector('.copy-content') : null;
    const actionTakenMessageDisplay = document.getElementById('actionTakenMessageDisplay');

    // Define the fields you want to display from the HRMS API
    const hrmsDisplayFields = [
        
        'EMP_CODE',
        // 'EMP_ID',
        'EMP_NAME',
        'EMP_STS',
        'DESIGNATION',
        'DESIGNATION_ORDER',
        'RANK',
        'ROLE_TITLE',
        'EMAIL',
        'MOBILE',
        'OPERATING_UNIT_TITLE',
        'LOCATION_TITLE',
        'JOB_LOCATION_ID',
        'DEPARTMENT_TITLE',
        'SECTION_TITLE',
        'SUB_SECTION_TITLE',
        'PRODUCT_TITLE',
        'PRODUCT_GROUP_TITLE',
        'TEAM_TITLE',
        'SUB_TEAM_TITLE',
        'EMP_CAT_TITLE',
        'JOINING_DT',
        'JOINING_DATE',
        'DOB',
        'AGE',
        'GENDER',
        'LAST_EDU_TITLE',
        'RESPONSIBILITY',
        'ADDRESS_PERMANENT',
        'PIC_URL_',
        'DATA_SOURCE',
        'ALL_ORG_MST_ID',
        'ALL_ORG_MST_TEAM_ID',
        'ALL_ORG_MST_DEPARTMENT_ID',
        'ALL_ORG_MST_SECTION_ID',
        'ALL_ORG_MST_PRODUCT_ID',
        'ALL_ORG_MST_OPERATING_UNIT_ID',
        'SECTION_ID',
        'DEPARTMENT_ID',
        'PRODUCT_ID',
        'ALL_ORG_MST_SUB_SECTION_ID',
        'ALKP_PRODUCT_GROUP_ID'
    ];

    function showLoading(element) {
        element.innerHTML = `
            <div class="loading-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <div class="loading-text">Your Operation is Coocking...</div>
        `;
    }

    actionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const username = usernameInput.value.trim();
            const action = this.value;

            if (!username) {
                alert('Please enter a username.');
                return;
            }

            // Show loading indicators
            showLoading(serverUserInfoSection);
            showLoading(employeeInfoSection);

            // Hide action taken card initially for new action
            actionTakenCard.style.display = 'none';
            actionTakenCard.classList.remove('fade-in');

            this.disabled = true;
            fetch(baseURL + '/assets/api/execute_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `username=${encodeURIComponent(username)}&action=${encodeURIComponent(action)}`
            })
            .then(response => {
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parsing error:', e);
                        throw new Error('Invalid JSON response from server: ' + text.substring(0, 100) + '...');
                    }
                });
            })
            .then(data => {
                this.disabled = false;

                // Update Action Taken section
                if (actionTakenCard) {
                    if (action === 'info') {
                        actionTakenCard.style.display = 'none'; // Hide for 'info' action
                    } else {
                        actionTakenCard.style.display = 'block'; // Show for other actions
                        actionTakenCard.classList.add('fade-in'); // Add fade-in animation
                        if (actionTakenTitleSpan) {
                            actionTakenTitleSpan.textContent = action.charAt(0).toUpperCase() + action.slice(1);
                        }
                        if (actionTakenMessageDisplay) {
                            actionTakenMessageDisplay.innerHTML = data.message.replace(/\n/g, '<br>');
                            // Apply success/error classes directly to the message display div
                            if (data.success) {
                                actionTakenMessageDisplay.classList.remove('alert-error');
                                actionTakenMessageDisplay.classList.add('alert-success');
                            } else {
                                actionTakenMessageDisplay.classList.remove('alert-success');
                                actionTakenMessageDisplay.classList.add('alert-error');
                            }
                        }
                        if (actionTakenMessageDiv) {
                            actionTakenMessageDiv.innerHTML = data.message;
                            actionTakenMessageDiv.style.display = 'none';
                        }

                        // Remove success/error classes from the main card, as they are now on the message div
                        actionTakenCard.classList.remove('alert-success', 'alert-error');
                    }
                }

                // Update Server User Information
                if (serverUserInfoSection) {
                    const infoClass = data.data.infoOutputSuccess ? 'alert-success' : 'alert-error';
                    const infoMessage = data.data.infoOutput
                        ? `<pre>${htmlspecialchars(data.data.infoOutput)}</pre>`
                        : 'No Server information available for this user';
                    serverUserInfoSection.innerHTML = `
                        <h3>Server User Information</h3>
                        <div class="alert ${infoClass}">
                            ${infoMessage}
                        </div>
                    `;
                }

                // Update Employee Information
                if (employeeInfoSection) {
                    const apiClass = data.data.apiDataSuccess ? 'alert-success' : 'alert-error';
                    if (data.data.apiData && Object.keys(data.data.apiData).length > 0) {
                        let hrmsHtml = `
                            <h3>Employee Information</h3>
                            <div class="alert ${apiClass} hrms-side-by-side">
                        `;
                        // Iterate over the predefined display fields
                        hrmsDisplayFields.forEach(key => {
                            const value = data.data.apiData[key];
                            // Only display if the value exists and is not empty (unless it's RESPONSIBILITY and empty, which is handled by PHP)
                            if (value !== undefined && value !== null && value !== '') {
                                hrmsHtml += `
                                    <div class="hrms-row">
                                        <div class="hrms-item${key === 'RESPONSIBILITY' ? ' full-width' : ''}">
                                            <span class="hrms-label">${key.replace(/_/g, ' ')}:</span>
                                            <span class="hrms-value">${htmlspecialchars(value)}</span>
                                        </div>
                                    </div>
                                `;
                            }
                        });
                        hrmsHtml += `</div>`;
                        employeeInfoSection.innerHTML = hrmsHtml;
                    } else {
                        employeeInfoSection.innerHTML = `
                            <h3>Employee Information</h3>
                            <div class="alert ${apiClass}">
                                No HRMS data available for the provided ID
                            </div>
                        `;
                    }
                }

            })
            .catch(error => {
                console.error('Error:', error);
                this.disabled = false;
                // Display error in both sections
                serverUserInfoSection.innerHTML = `
                    <h3>Server User Information</h3>
                    <div class="alert alert-error">
                        An error occurred: ${error.message}
                    </div>
                `;
                employeeInfoSection.innerHTML = `
                    <h3>Employee Information</h3>
                    <div class="alert alert-error">
                        An error occurred: ${error.message}
                    </div>
                `;

                if (actionTakenCard) {
                    actionTakenCard.style.display = 'block';
                    actionTakenCard.classList.add('fade-in');
                    if (actionTakenTitleSpan) {
                        actionTakenTitleSpan.textContent = action.charAt(0).toUpperCase() + action.slice(1);
                    }
                    if (actionTakenMessageDisplay) {
                        actionTakenMessageDisplay.innerHTML = `<div class='alert alert-error'>An error occurred: ${error.message}</div>`;
                    }
                    if (actionTakenMessageDiv) {
                        actionTakenMessageDiv.innerHTML = `<div class='alert alert-error'>An error occurred: ${error.message}</div>`;
                        actionTakenMessageDiv.style.display = 'none';
                    }
                    actionTakenCard.classList.remove('alert-success');
                    actionTakenCard.classList.add('alert-error');
                }
            });
        });
    });

    function htmlspecialchars(str) {
        if (str === null || typeof str === 'undefined') {
            return '';
        }
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return str.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Copy to clipboard function for Action Taken section
    function copyMessageToClipboard(buttonElement) {
        const card = buttonElement.closest('.card');
        const contentToCopy = card.querySelector('#actionTakenMessageDisplay');
        if (contentToCopy) {
            const tempTextarea = document.createElement('textarea');
            tempTextarea.value = contentToCopy.textContent.trim();
            document.body.appendChild(tempTextarea);
            tempTextarea.select();
            try {
                document.execCommand('copy');
                buttonElement.dataset.tooltip = 'Copied';
                buttonElement.style.setProperty('--tooltip-color', '#28a745'); // Green for success
                setTimeout(() => {
                    buttonElement.dataset.tooltip = 'Copy message'; // Reset to original tooltip
                    buttonElement.style.setProperty('--tooltip-color', 'var(--primary)'); // Reset to default color
                }, 2000);
            } catch (err) {
                console.error('Failed to copy using execCommand: ', err);
                // Fallback to navigator.clipboard.writeText
                navigator.clipboard.writeText(contentToCopy.textContent.trim()).then(() => {
                    buttonElement.dataset.tooltip = 'Copied';
                    buttonElement.style.setProperty('--tooltip-color', '#28a745'); // Green for success
                    setTimeout(() => {
                        buttonElement.dataset.tooltip = 'Copy message'; // Reset to original tooltip
                        buttonElement.style.setProperty('--tooltip-color', 'var(--primary)'); // Reset
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy using navigator.clipboard.writeText: ', err);
                    buttonElement.dataset.tooltip = 'Copy failed';
                    buttonElement.style.setProperty('--tooltip-color', '#dc3545'); // Red for failure
                    setTimeout(() => {
                        buttonElement.dataset.tooltip = 'Copy message'; // Reset to original tooltip
                        buttonElement.style.setProperty('--tooltip-color', 'var(--primary)'); // Reset
                    }, 2000);
                });
            } finally {
                document.body.removeChild(tempTextarea);
            }
        } else {
            console.warn('No #actionTakenMessageDisplay element found for copying');
        }
    }

    // Attach event listener to the copy button
    const copyButton = document.querySelector('.btn-copy');
    if (copyButton) {
        copyButton.addEventListener('click', function() {
            copyMessageToClipboard(this);
        });
    }
});