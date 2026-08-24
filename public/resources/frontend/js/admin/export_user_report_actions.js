(function() {
    'use strict';

    function initExportUserReportActions() {
        const resolvedBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');
        const exportBtn = document.getElementById('exportAdUsersButton');
        const card = document.getElementById('exportUserReportCardContainer');
        const cancelBtn = document.getElementById('cancelExportUserReport');
        const submitBtn = document.getElementById('submitExportUserReport');
        const ouDisplay = document.getElementById('exportReportOUDisplay');
        const ouInput = document.getElementById('exportReportOU');
        const groupDisplay = document.getElementById('exportReportGroupDisplay');
        const groupInput = document.getElementById('exportReportGroup');
        const resultsSection = document.getElementById('exportUserReportResults');
        const tbody = document.getElementById('exportUserReportTbody');
        const summaryBadge = document.getElementById('exportUserReportSummary');
        const downloadBtn = document.getElementById('downloadExportUserReport');

        if (!exportBtn || !card) return;

        // --- Bind OU and Group dropdown searches ---
        if (window.adTreeDropdown && window.adTreeDropdown.bindSearch) {
            const dt = window.adTreeDropdown;
            dt.bindSearch(ouDisplay, document.getElementById('exportReportOUDropdown'), document.getElementById('exportReportOUList'), ['OU', 'Domain'], (item) => {
                if (item.clear) { ouDisplay.value = ''; ouInput.value = ''; if (groupDisplay) groupDisplay.disabled = false; if (groupInput) groupInput.value = ''; } else { ouDisplay.value = item.Name; ouInput.value = item.DistinguishedName; if (groupDisplay) { groupDisplay.disabled = true; groupDisplay.value = ''; } if (groupInput) groupInput.value = ''; }
                const dd = document.getElementById('exportReportOUDropdown');
                if (dd) dd.style.display = 'none';
            });
            dt.bindSearch(groupDisplay, document.getElementById('exportReportGroupDropdown'), document.getElementById('exportReportGroupList'), ['Group'], (item) => {
                if (item.clear) { groupDisplay.value = ''; groupInput.value = ''; if (ouDisplay) ouDisplay.disabled = false; if (ouInput) ouInput.value = ''; } else { groupDisplay.value = item.Name; groupInput.value = item.DistinguishedName; if (ouDisplay) { ouDisplay.disabled = true; ouDisplay.value = ''; } if (ouInput) ouInput.value = ''; }
                const dd = document.getElementById('exportReportGroupDropdown');
                if (dd) dd.style.display = 'none';
            });
        }

        // --- Download CSV ---
        downloadBtn.addEventListener('click', () => {
            const rows = Array.from(tbody.querySelectorAll('tr'));
            if (rows.length === 0 || rows[0].cells.length < 2) {
                alert('No data to download.');
                return;
            }
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Source,Username,Display Name,Status,60d Logon,Last Logon,Created,Privilege,Member Of,OU Name\n";
            rows.forEach(row => {
                const cols = Array.from(row.cells).map(cell => {
                    let text = cell.innerText.trim();
                    if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                        text = '"' + text.replace(/"/g, '""') + '"';
                    }
                    return text;
                });
                csvContent += cols.join(",") + "\n";
            });
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `User_Export_${timestamp}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });

        // --- Toggle card ---
        exportBtn.addEventListener('click', () => {
            const reportCard = document.getElementById('userReportCardContainer');
            if (reportCard) reportCard.style.display = 'none';

            card.style.display = card.style.display === 'none' ? 'block' : 'none';
            if (card.style.display === 'block') {
                // scrollIntoView intentionally omitted — avoids yanking workspace scroll
                if (window.adTreeDropdown && window.adTreeDropdown.fetchUnifiedTree) {
                    window.adTreeDropdown.fetchUnifiedTree();
                }
            }
        });

        cancelBtn.addEventListener('click', () => {
            card.style.display = 'none';
            resultsSection.style.display = 'none';
            if (ouDisplay) { ouDisplay.value = ''; ouDisplay.disabled = false; }
            if (ouInput) ouInput.value = '';
            if (groupDisplay) { groupDisplay.value = ''; groupDisplay.disabled = false; }
            if (groupInput) groupInput.value = '';
        });

        // --- Submit ---
        submitBtn.addEventListener('click', async () => {
            const ou = ouInput.value.trim();
            const grp = groupInput.value.trim();

            if (!ou && !grp) {
                alert('Please select an OU or Group from the dropdown.');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching...';
            resultsSection.style.display = 'none';

            try {
                const response = await fetch(`${resolvedBaseUrl}/api/index.php?endpoint=get_ou_group_user_report`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `ouName=${encodeURIComponent(ou)}&groupName=${encodeURIComponent(grp)}`
                });
                const data = await response.json();

                if (data.success) {
                    renderResults(data.users);
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Export Error:', error);
                alert('An error occurred while fetching users.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-file-export"></i> Fetch Users';
            }
        });

        function renderResults(users) {
            tbody.innerHTML = '';
            resultsSection.style.display = 'block';
            const count = users ? users.length : 0;
            summaryBadge.textContent = count + ' users found';

            if (!users || users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center">No users found.</td></tr>';
                return;
            }

            users.forEach(user => {
                const row = document.createElement('tr');
                const esc = str => { if (!str) return 'N/A'; return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m])); };
                const statusClass = user.Enabled ? 'status-success' : 'status-failed';
                const statusText = user.Enabled ? 'Active' : 'Disabled';
                const activityClass = user.ActivityStatus === 'Active' ? 'status-success' : (user.ActivityStatus === 'Inactive' ? 'status-failed' : '');
                const sourceLabel = esc(user.SourceType || '') + ': ' + esc(user.SourceName || '');
                const src = sourceLabel || 'All AD Users';
                const uname = esc(user.SamAccountName);
                const dname = esc(user.DisplayName);
                const act = esc(user.ActivityStatus);
                const llog = esc(user.LastLogonDate);
                const crtd = esc(user.WhenCreated);
                const priv = esc(user.Privilege) || '-';
                const memb = esc(user.MemberOf);
                const ou = esc(user.OUName);
                row.innerHTML = `
                    <td title="${src}">${src}</td>
                    <td title="${uname}">${uname}</td>
                    <td title="${dname}">${dname}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td><span class="status-badge ${activityClass}">${act}</span></td>
                    <td title="${llog}">${llog}</td>
                    <td title="${crtd}">${crtd}</td>
                    <td title="${priv}">${priv}</td>
                    <td title="${memb}">${memb}</td>
                    <td title="${ou}">${ou}</td>
                `;
                tbody.appendChild(row);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', initExportUserReportActions);
    window.initExportUserReportActions = initExportUserReportActions;
})();
