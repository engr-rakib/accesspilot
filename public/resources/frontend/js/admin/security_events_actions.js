/**
 * Security Events Card Handler
 * Mirrors user_report_actions.js design pattern
 */
(function() {
    'use strict';

    function initSecurityEvents() {
        const resolvedBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');
        const eventsButton = document.getElementById('userSecurityEventsButton');
        const eventsCard = document.getElementById('securityEventsCardContainer');
        const cancelBtn = document.getElementById('cancelSecurityEvents');
        const submitBtn = document.getElementById('submitSecurityEvents');
        const resultsSection = document.getElementById('securityEventsResults');
        const tbody = document.getElementById('securityEventsTbody');
        const summaryBadge = document.getElementById('secEventsSummary');
        const downloadBtn = document.getElementById('downloadSecurityEvents');

        if (!eventsButton) return;

        // CSV Download
        downloadBtn.addEventListener('click', () => {
            const rows = Array.from(tbody.querySelectorAll('tr'));
            if (rows.length === 0 || rows[0].cells.length < 2) {
                alert('No data to download.');
                return;
            }
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += '"Time","Event ID","Event Label","User","Workstation","Source IP","Logon Type"\n';
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
            link.setAttribute("download", `Security_Events_${timestamp}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });

        // Workstation Lookup
        const lookupBtn = document.getElementById('secEventsLookupWs');
        const wsLookupResults = document.getElementById('secEventsWsLookupResults');
        const wsLookupList = document.getElementById('secEventsWsLookupList');
        const wsInput = document.getElementById('secEventsWorkstation');

        if (lookupBtn) {
            lookupBtn.addEventListener('click', async () => {
                const username = document.getElementById('secEventsUsername').value.trim();
                if (!username) { alert('Enter a username first.'); return; }

                lookupBtn.disabled = true;
                lookupBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                try {
                    const response = await fetch(`${resolvedBaseUrl}/api/index.php?endpoint=lookup_user_workstations`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ username }).toString()
                    });
                    const data = await response.json();

                    wsLookupList.innerHTML = '';
                    if (data.success && data.workstations.length > 0) {
                        data.workstations.forEach(ws => {
                            const badge = document.createElement('span');
                            badge.className = 'badge bg-info text-dark cursor-pointer';
                            badge.textContent = ws;
                            badge.style.cursor = 'pointer';
                            badge.addEventListener('click', () => {
                                wsInput.value = ws;
                            });
                            wsLookupList.appendChild(badge);
                        });
                        wsLookupResults.style.display = 'block';
                    } else {
                        wsLookupList.innerHTML = '<span class="text-muted small">No workstations found.</span>';
                        wsLookupResults.style.display = 'block';
                    }
                } catch (err) {
                    console.error('WS Lookup Error:', err);
                    alert('Failed to look up workstations.');
                } finally {
                    lookupBtn.disabled = false;
                    lookupBtn.innerHTML = '<i class="fas fa-search"></i> WS';
                }
            });
        }

        // Event ID Reference Modal
        const refBtn = document.getElementById('secEventsIdRefBtn');
        if (refBtn) {
            refBtn.addEventListener('click', () => {
                const modalEl = document.getElementById('secEventsIdRefModal');
                if (modalEl && modalEl.parentElement !== document.body) {
                    document.body.appendChild(modalEl);
                }
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            });
        }

        // Toggle Card
        eventsButton.addEventListener('click', () => {
            eventsCard.style.display = eventsCard.style.display === 'none' ? 'block' : 'none';
            if (eventsCard.style.display === 'block') {
                const today = new Date().toISOString().split('T')[0];
                const sevenDaysAgo = new Date(Date.now() - 7 * 86400000).toISOString().split('T')[0];
                const fromEl = document.getElementById('secEventsDateFrom');
                const toEl = document.getElementById('secEventsDateTo');
                if (fromEl) fromEl.value = sevenDaysAgo;
                if (toEl) toEl.value = today;
                // scrollIntoView intentionally omitted — avoids yanking workspace scroll
            }
        });

        cancelBtn.addEventListener('click', () => {
            eventsCard.style.display = 'none';
            resultsSection.style.display = 'none';
            document.getElementById('secEventsWorkstations').style.display = 'none';
        });

        // Fetch Events
        submitBtn.addEventListener('click', async () => {
            const username = document.getElementById('secEventsUsername').value.trim();
            const dateFrom = document.getElementById('secEventsDateFrom').value;
            const dateTo = document.getElementById('secEventsDateTo').value;
            const eventIds = document.getElementById('secEventsEventIds').value.trim();
            const workstation = document.getElementById('secEventsWorkstation').value.trim();

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching...';
            resultsSection.style.display = 'none';

            try {
                const params = { username, event_ids: eventIds, workstation, days_back: '7', max_results: '300', date_from: dateFrom, date_to: dateTo };
                Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });

                const response = await fetch(`${resolvedBaseUrl}/api/index.php?endpoint=get_user_security_events`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(params).toString()
                });
                const data = await response.json();

                if (data.success) {
                    renderSecurityEventsResults(data, username);
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Security Events Error:', error);
                alert('An error occurred while fetching events.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-search me-1"></i> Fetch Events';
            }
        });

        function renderSecurityEventsResults(data, username) {
            const events = data.data.events || [];
            const total = data.data.total || 0;
            const queryTime = data.data.queryTime || 'N/A';
            const dc = data.data.domainController || 'N/A';
            const searchUser = username || '(all users)';
            const workstations = data.data.workstations || [];

            tbody.innerHTML = '';
            resultsSection.style.display = 'block';
            summaryBadge.textContent = `${total} events (${searchUser}, DC: ${dc}, ${queryTime})`;

            // Show associated workstations
            const wsSection = document.getElementById('secEventsWorkstations');
            const wsList = document.getElementById('secEventsWsList');
            if (workstations.length > 0) {
                wsList.innerHTML = workstations.map(ws => `<span class="badge bg-secondary me-1">${ws}</span>`).join(' ');
                wsSection.style.display = 'block';
            } else {
                wsSection.style.display = 'none';
            }

            if (events.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">No security events found for the given filters.</td></tr>';
                return;
            }

            events.forEach(ev => {
                const row = document.createElement('tr');
                const esc = str => { if (!str) return '-'; return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m])); };
                const eventId = ev.EventId || '';
                const label = ev.EventLabel || '';
                const time = ev.TimeCreated || '';
                const user = ev.TargetUser || '';
                const ws = ev.Workstation || '';
                const ip = ev.SourceIp || '';
                const lt = ev.LogonTypeDesc || ev.LogonType || '';

                let rowClass = '';
                if (eventId === 4625) rowClass = 'table-danger';
                else if (eventId === 4720 || eventId === 4722) rowClass = 'table-success';
                else if (eventId === 4725 || eventId === 4726) rowClass = 'table-danger';
                else if (eventId === 4740) rowClass = 'table-warning';

                row.className = rowClass;
                row.innerHTML = `
                    <td>${esc(time)}</td>
                    <td><strong>${eventId}</strong> ${esc(label)}</td>
                    <td>${esc(user)}</td>
                    <td>${esc(ws)}</td>
                    <td>${esc(ip)}</td>
                    <td>${esc(lt)}</td>
                `;
                tbody.appendChild(row);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', initSecurityEvents);
    window.initSecurityEvents = initSecurityEvents;
})();
