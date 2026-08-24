document.addEventListener('DOMContentLoaded', function() {
    const actionTakenMessageDisplay = document.getElementById('actionTakenMessageDisplay');
    const resolvedBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');

    // Generic function to handle report actions
    async function handleReportAction(button, title, messageEndpoint, downloadEndpoint, payload = {}, isCsv = true, username = '') {
        clearReportButtons(); // Clear previous buttons

        const usernameInput = document.getElementById('username'); // Assuming username input is always present
        const groupNameForExportInput = document.getElementById('groupNameForExport'); // Assuming group name input is always present

        // Input validation for username/groupName if required by payload
        if (payload.username && !payload.username.trim()) {
            // EFFECT: shake animation | Purpose: validation feedback for empty inputs
            usernameInput.classList.add('shake');
            usernameInput.focus();
            setTimeout(() => { usernameInput.classList.remove('shake'); }, 820);
            return;
        }
        if (payload.groupName && !payload.groupName.trim()) {
            groupNameForExportInput.classList.add('shake');
            groupNameForExportInput.focus();
            setTimeout(() => { groupNameForExportInput.classList.remove('shake'); }, 820);
            return;
        }

        button.disabled = true;

        if (window.actionTakenCardContainer) {
            window.actionTakenCardContainer.classList.add('visible');
            window.actionTakenCardContainer.classList.add('slide-in-top');
            if (window.actionTakenTitleSpan) window.actionTakenTitleSpan.textContent = title;
            if (window.actionTakenMessageDisplay) {
                showLoading(window.actionTakenMessageDisplay);
            }
            // Removed scrollIntoView to avoid jumping, content appears at top
        }

        try {
            const response = await fetch(resolvedBaseUrl + messageEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(payload).toString()
            });

            const data = await response.json();

            if (window.actionTakenMessageDisplay) {
                window.actionTakenMessageDisplay.classList.remove('alert-success', 'alert-danger');
                if (data.success) {
                    // Display the actual report content using centralized render format
                    if (data.report_title && data.report_preview) {
                        window.actionTakenMessageDisplay.innerHTML = renderFeedbackMessage(data.report_preview);
                    } else {
                        window.actionTakenMessageDisplay.innerHTML = isCsv ? csvToHtmlTable(data.report_content) : renderFeedbackMessage(data.report_content);
                    }
                    window.actionTakenMessageDisplay.classList.add('alert-success');
                    window.actionTakenMessageDisplay.style.marginBottom = '0';

                    // Restructure header: remove copy icon, add download button top-right (like Export Users card)
                    const titleBar = document.querySelector('#actionTakenCardContent .log-title-wrapper');
                    if (titleBar) {
                        const oldRight = titleBar.querySelector('.d-flex.align-items-center.gap-1');
                        if (oldRight) { oldRight.remove(); }

                        // Add download + close buttons to header right (like Export Users card)
                        const downloadBtn = document.createElement('button');
                        downloadBtn.className = 'btn btn-sm btn-success';
                        downloadBtn.innerHTML = '<i class="fas fa-download me-1"></i> Download CSV';
                        downloadBtn.addEventListener('click', () => {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = resolvedBaseUrl + downloadEndpoint;
                            const iframe = document.createElement('iframe');
                            iframe.style.display = 'none';
                            iframe.name = 'download_iframe_' + title.replace(/\s/g, '');
                            document.body.appendChild(iframe);
                            form.target = iframe.name;
                            for (const key in payload) {
                                const input = document.createElement('input');
                                input.type = 'hidden'; input.name = key; input.value = payload[key];
                                form.appendChild(input);
                            }
                            document.body.appendChild(form);
                            form.submit();
                            setTimeout(() => {
                                if (document.body.contains(form)) document.body.removeChild(form);
                                if (document.body.contains(iframe)) document.body.removeChild(iframe);
                            }, 5000);
                        });

                        const closeBtn = document.createElement('button');
                        closeBtn.className = 'btn btn-sm btn-secondary';
                        closeBtn.innerHTML = '<i class="fas fa-times me-1"></i> Close';
                        closeBtn.addEventListener('click', () => {
                            if (window.actionTakenCardContainer) {
                                window.actionTakenCardContainer.classList.remove('visible');
                                window.actionTakenMessageDisplay.innerHTML = '';
                            }
                        });

                        const rightDiv = document.createElement('div');
                        rightDiv.id = 'syncMappingHeaderActions';
                        rightDiv.className = 'd-flex align-items-center gap-2';
                        rightDiv.appendChild(downloadBtn);
                        rightDiv.appendChild(closeBtn);
                        titleBar.appendChild(rightDiv);
                    }

                } else {
                    window.actionTakenMessageDisplay.innerHTML = renderFeedbackMessage(data.message);
                    window.actionTakenMessageDisplay.classList.add('alert-danger');
                }
            }

        } catch (error) {
            console.error(`Error during ${title} fetch:`, error);
            if (actionTakenMessageDisplay) {
                actionTakenMessageDisplay.classList.remove('alert-success', 'alert-danger');
                actionTakenMessageDisplay.innerHTML = renderFeedbackMessage(`An error occurred: ${error.message}`);
                actionTakenMessageDisplay.classList.add('alert-danger');
            }
        }
        button.disabled = false; // Re-enable button after process
    }

    const usernameInput = document.getElementById('username');
    const getHrmsAdReportButton = document.getElementById('getHrmsAdReportButton');

    if (getHrmsAdReportButton) {
        getHrmsAdReportButton.addEventListener('click', function() {
            const username = usernameInput.value.trim();
            if (!username) {
                usernameInput.classList.add('shake');
                usernameInput.focus();
                setTimeout(() => { usernameInput.classList.remove('shake'); }, 820);
                return;
            }
            handleReportAction(this, 'HRMS AD Report', '/api/index.php?endpoint=get_hrms_ad_report_message', '/api/index.php?endpoint=get_hrms_ad_report', { username: username }, true, username);
        });
    }



    const adHealthCheckButton = document.getElementById('adHealthCheckButton');

    if (adHealthCheckButton) {
        adHealthCheckButton.addEventListener('click', async function() {
            clearReportButtons();
            const button = this;
            button.disabled = true;

            if (window.actionTakenCardContainer) {
                window.actionTakenCardContainer.classList.add('visible', 'slide-in-top');
                if (window.actionTakenTitleSpan) window.actionTakenTitleSpan.textContent = 'AD Health Check Report';
                if (window.actionTakenMessageDisplay) showLoading(window.actionTakenMessageDisplay);
            }

            try {
                const basicResp = await fetch(resolvedBaseUrl + '/api/index.php?endpoint=get_ad_health_check_message', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                });
                const basicData = await basicResp.json();

                if (window.actionTakenMessageDisplay) {
                    window.actionTakenMessageDisplay.classList.remove('alert-success', 'alert-danger');
                    if (basicData.success && basicData.report_title) {
                        const fullMsg = (basicData.message || basicData.report_preview || '');
                        window.actionTakenMessageDisplay.innerHTML = renderFeedbackMessage(fullMsg);
                        window.actionTakenMessageDisplay.classList.add('alert-success');

                        // Auto-run deep check using bind credentials (Exchange-style Kerberos)
                        const dr = document.createElement('div');
                        dr.id = 'deepCheckResult';
                        dr.style.cssText = 'padding:12px 0.75rem;margin-top:4px;';
                        dr.innerHTML = '<p style="font-weight:600;margin-bottom:6px;font-size:0.85rem;font-family:\'Roboto\',sans-serif;"><i class="fas fa-shield-alt me-1"></i> Deep Health Check</p>'
                            + '<div class="text-center py-3" style="font-family:\'Roboto\',sans-serif;"><span class="spinner-border spinner-border-sm me-2"></span>Running deep diagnostics using LDAP bind account...</div>';
                        const acc = document.getElementById('actionTakenCardContent');
                        const msgWrapper = window.actionTakenMessageDisplay ? window.actionTakenMessageDisplay.parentNode : null;
                        if (acc && msgWrapper) acc.appendChild(dr);

                        try {
                            const deepResp = await fetch(resolvedBaseUrl + '/api/index.php?endpoint=get_ad_health_check_deep', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({ admin_username: '', admin_password: '' }).toString()
                            });
                            const deepData = await deepResp.json();
                            renderDeepCheckResult(deepData, dr);
                        } catch (e) {
                            dr.innerHTML = '<div class="alert alert-danger mb-0 py-2 px-3" style="font-size:0.85rem;font-family:\'Roboto\',sans-serif;">Error: ' + escapeHTML(e.message) + '</div>';
                        }
                    } else {
                        window.actionTakenMessageDisplay.innerHTML = styleFeedbackMessage(basicData.message || 'Health check failed.');
                        window.actionTakenMessageDisplay.classList.add('alert-danger');
                    }
                }
            } catch (error) {
                if (window.actionTakenMessageDisplay) {
                    window.actionTakenMessageDisplay.classList.remove('alert-success', 'alert-danger');
                    window.actionTakenMessageDisplay.innerHTML = 'An error occurred: ' + error.message;
                    window.actionTakenMessageDisplay.classList.add('alert-danger');
                }
            }
            button.disabled = false;
        });
    }

    function renderDeepCheckResult(deepData, dr) {
        if (deepData.success) {
            const preview = escapeHTML((deepData.message || deepData.report_preview || '').substring(0, 300));
            dr.innerHTML = '<p style="font-weight:600;margin-bottom:6px;font-size:0.85rem;font-family:\'Roboto\',sans-serif;"><i class="fas fa-check-circle me-1" style="color:#22c55e;"></i> Deep Health Check Complete</p>'
                + '<div class="alert alert-success mb-2 py-2 px-3" style="font-size:0.85rem;font-family:\'Roboto\',sans-serif;">' + preview + '</div>';

            const btnGroup = document.createElement('div');
            btnGroup.className = 'd-flex justify-content-end mt-2';

            const downloadBtn = document.createElement('button');
            downloadBtn.textContent = 'Download Report';
            downloadBtn.className = 'btn btn-success btn-sm me-2';
            downloadBtn.style.fontFamily = "'Roboto', sans-serif";
            downloadBtn.addEventListener('click', () => {
                const f = document.createElement('form');
                f.method = 'POST';
                f.action = resolvedBaseUrl + '/api/index.php?endpoint=get_ad_health_check_report';
                const ifr = document.createElement('iframe');
                ifr.style.display = 'none';
                ifr.name = 'download_iframe_health_deep';
                document.body.appendChild(ifr);
                f.target = ifr.name;
                document.body.appendChild(f);
                f.submit();
                setTimeout(() => {
                    if (document.body.contains(f)) document.body.removeChild(f);
                    if (document.body.contains(ifr)) document.body.removeChild(ifr);
                }, 5000);
            });

            const closeBtn = document.createElement('button');
            closeBtn.textContent = 'Close';
            closeBtn.className = 'btn btn-secondary btn-sm';
            closeBtn.style.fontFamily = "'Roboto', sans-serif";
            closeBtn.addEventListener('click', () => {
                if (window.actionTakenCardContainer) {
                    window.actionTakenCardContainer.classList.remove('visible');
                    window.actionTakenMessageDisplay.innerHTML = '';
                }
            });

            btnGroup.appendChild(downloadBtn);
            btnGroup.appendChild(closeBtn);
            dr.appendChild(btnGroup);
        } else {
            dr.innerHTML = '<div class="alert alert-danger mb-0 py-2 px-3" style="font-size:0.85rem;font-family:\'Roboto\',sans-serif;">' + escapeHTML(deepData.message) + '</div>';
        }
    }

});
