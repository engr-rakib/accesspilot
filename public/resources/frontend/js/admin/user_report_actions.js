/**
 * User Report Actions Handler
 */
(function() {
    'use strict';

    function initUserReportActions() {
        const resolvedBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');
        const userReportButton = document.getElementById('userReportButton');
        const userReportCard = document.getElementById('userReportCardContainer');
        const cancelBtn = document.getElementById('cancelUserReport');
        const submitBtn = document.getElementById('submitUserReport');
        const statusSelect = document.getElementById('userStatus');
        const daysSelect = document.getElementById('reportDays');
        const daysGroup = document.getElementById('daysDropdownGroup');
        const customDaysGroup = document.getElementById('customDaysGroup');
        const customDaysInput = document.getElementById('customDays');
        const resultsSection = document.getElementById('userReportResults');
        const tbody = document.getElementById('userReportTbody');
        const summaryBadge = document.getElementById('reportSummary');
        const bulkActions = document.getElementById('bulkActions');
        const disableAllBtn = document.getElementById('disableAllInactive');
        const downloadBtn = document.getElementById('downloadUserReport');

        if (!userReportButton) return;

        // --- CSV Download Logic ---
        downloadBtn.addEventListener('click', () => {
            const rows = Array.from(tbody.querySelectorAll('tr'));
            if (rows.length === 0 || rows[0].cells.length < 2) {
                alert('No data to download.');
                return;
            }

            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Username,Display Name,Last Logon,Status,OU Path\n";

            rows.forEach(row => {
                const cols = Array.from(row.cells).map(cell => {
                    let text = cell.innerText.trim();
                    // Escape quotes and handle commas
                    if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                        text = '"' + text.replace(/"/g, '""') + '"';
                    }
                    return text;
                });
                csvContent += cols.join(",") + "\n";
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            const status = statusSelect.value;
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `User_Report_${status}_${timestamp}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });

        // Toggle Card Visibility
        userReportButton.addEventListener('click', () => {
            userReportCard.style.display = userReportCard.style.display === 'none' ? 'block' : 'none';
            if (userReportCard.style.display === 'block') {
                // scrollIntoView intentionally omitted — avoids yanking workspace scroll
            }
        });

        cancelBtn.addEventListener('click', () => {
            userReportCard.style.display = 'none';
            resultsSection.style.display = 'none';
        });

        // Handle Status Change
        statusSelect.addEventListener('change', () => {
            if (statusSelect.value === 'disabled') {
                daysGroup.style.display = 'none';
                customDaysGroup.style.display = 'none';
            } else {
                daysGroup.style.display = 'block';
                if (daysSelect.value === 'custom') {
                    customDaysGroup.style.display = 'block';
                }
            }
        });

        // Handle Days Change
        daysSelect.addEventListener('change', () => {
            if (daysSelect.value === 'custom') {
                customDaysGroup.style.display = 'block';
            } else {
                customDaysGroup.style.display = 'none';
            }
        });

        // Generate Report
        submitBtn.addEventListener('click', async () => {
            const status = statusSelect.value;
            let days = daysSelect.value;
            if (days === 'custom') {
                days = customDaysInput.value;
                if (!days) {
                    alert('Please enter custom days.');
                    return;
                }
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            resultsSection.style.display = 'none';

            try {
                const response = await fetch(`${resolvedBaseUrl}/api/index.php?endpoint=get_user_report`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `status=${status}&days=${days}`
                });
                const data = await response.json();

                if (data.success) {
                    renderResults(data.users, status);
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Report Generation Error:', error);
                alert('An error occurred while generating the report.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-sync"></i> Generate Report';
            }
        });

        function renderResults(users, status) {
            tbody.innerHTML = '';
            resultsSection.style.display = 'block';
            summaryBadge.textContent = users.length + ' users found';

            if (status === 'inactive' && users.length > 0) {
                bulkActions.style.display = 'block';
            } else {
                bulkActions.style.display = 'none';
            }

            if (users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No users found matching the criteria.</td></tr>';
                return;
            }

            users.forEach(user => {
                const row = document.createElement('tr');
                const esc = str => { if (!str) return 'N/A'; return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m])); };
                const statusClass = user.Enabled ? 'status-success' : 'status-failed';
                const statusText = user.Enabled ? 'Active' : 'Disabled';
                row.innerHTML = `
                    <td>${esc(user.SamAccountName)}</td>
                    <td>${esc(user.DisplayName)}</td>
                    <td>${esc(user.LastLogonDate)}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td>${esc(user.OU)}</td>
                `;
                tbody.appendChild(row);
            });
        }

        // Bulk Disable Action
        disableAllBtn.addEventListener('click', async () => {
            const usernames = Array.from(tbody.querySelectorAll('tr td:first-child')).map(td => td.textContent);
            if (usernames.length === 0) return;

            if (!confirm(`Are you sure you want to disable ALL ${usernames.length} inactive users?`)) {
                return;
            }

            disableAllBtn.disabled = true;
            disableAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Disabling...';

            let successCount = 0;
            let failCount = 0;

            // Process in chunks or sequentially
            for (const username of usernames) {
                try {
                    const response = await fetch(`${resolvedBaseUrl}/api/index.php?endpoint=execute_action`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `username=${encodeURIComponent(username)}&action=disableUser`
                    });
                    const result = await response.json();
                    if (result.success) successCount++;
                    else failCount++;
                } catch (e) {
                    failCount++;
                }
            }

            alert(`Bulk Disable Complete.\nSuccess: ${successCount}\nFailed: ${failCount}`);
            disableAllBtn.disabled = false;
            disableAllBtn.innerHTML = '<i class="fas fa-user-slash"></i> Disable All Listed Users';
            
            // Refresh report
            submitBtn.click();
        });
    }

    // Initialize on DOM load
    document.addEventListener('DOMContentLoaded', initUserReportActions);
    
    // SPA Re-initialization
    window.initUserReportActions = initUserReportActions;

})();
