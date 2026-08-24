// Vendor License Console — Full CRUD Module
(function() {
    var BASE_URL = (window.APP_CONFIG && APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : window.location.origin);
    var API_URL = BASE_URL + '/api/index.php?endpoint=vendor_license_api';
    var CSRF_TOKEN = (window.APP_CONFIG && APP_CONFIG.csrfToken) || '';

    var countdownInterval = null;
    var cachedLicenses = [];
    var deployDecodeTimer = null;
    var deployDecodeStatus = null;

    // ── DOM refs (re-bound on each SPA init) ───────────────
    var fieldClient = null;
    var fieldDomain = null;
    var fieldDeployId = null;
    var fieldExpiry = null;
    var fieldMaxDomains = null;
    var fieldType = null;
    var btnSave = null;
    var btnReset = null;
    var btnRefresh = null;
    var btnClearLog = null;
    var genStatus = null;
    var licenseBody = null;
    var licenseCount = null;
    var consoleLog = null;
    var activeInlinePanelId = null;
    var activeInlinePanelMode = null;
    var verifyResultCache = {};

    function $(id) {
        return document.getElementById(id);
    }

    // ── Helpers ────────────────────────────────────────────
    function vendorLog(message, type) {
        if (!consoleLog) return;
        var line = document.createElement('div');
        line.className = 'vendor-console-line';
        var icon = '', color = '';
        if (type === 'ok') { icon = '✓'; color = '#16a34a'; }
        else if (type === 'err') { icon = '✗'; color = '#dc2626'; }
        else if (type === 'warn') { icon = '⚠'; color = '#f59e0b'; }
        else if (type === 'info') { icon = '→'; color = '#06b6d4'; }
        else { icon = '•'; color = '#94a3b8'; }
        line.innerHTML = '<span style="color:' + color + ';">' + icon + '</span> ' + message;
        consoleLog.appendChild(line);
        consoleLog.scrollTop = consoleLog.scrollHeight;
    }

    function vendorShowStatus(el, msg, type) {
        if (!el) return;
        el.textContent = msg;
        el.className = 'vendor-console-status mt-2';
        var colors = { ok: '#16a34a', err: '#dc2626', warn: '#f59e0b', info: '#06b6d4' };
        el.style.color = colors[type] || '';
    }

    function getCsrfHeaders() {
        var h = { 'Content-Type': 'application/json' };
        if (CSRF_TOKEN) h['X-CSRF-Token'] = CSRF_TOKEN;
        return h;
    }

    function apiCall(action, method, body) {
        var url = API_URL + '&action=' + encodeURIComponent(action);
        var opts = {
            method: method,
            headers: getCsrfHeaders(),
            credentials: 'same-origin'
        };
        if (body && method !== 'GET') opts.body = JSON.stringify(body);
        return fetch(url, opts).then(function(r) { return r.json(); });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function formatDatePicker(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    // ── Form ───────────────────────────────────────────────
    function setDefaultExpiry() {
        if (!fieldExpiry) return;
        var d = new Date();
        d.setFullYear(d.getFullYear() + 1);
        fieldExpiry.value = formatDatePicker(d);
    }

    function validateForm() {
        var missing = [];
        if (!fieldClient.value.trim()) missing.push('Client Name');
        if (!fieldDomain.value.trim()) missing.push('Domain Name');
        if (!fieldDeployId.value.trim()) missing.push('Deployment ID');
        if (!fieldExpiry.value) missing.push('Expiry Date');
        if (missing.length) {
            vendorShowStatus(genStatus, 'Missing: ' + missing.join(', '), 'err');
            return false;
        }
        return true;
    }

    function resetForm() {
        [fieldClient, fieldDomain, fieldDeployId].forEach(function(el) { if (el) el.value = ''; });
        if (fieldMaxDomains) fieldMaxDomains.value = '1';
        if (fieldType) fieldType.value = 'issue';
        setDefaultExpiry();
        if (genStatus) { genStatus.textContent = ''; genStatus.style.color = ''; }
        vendorLog('Form reset', 'info');
    }

    // ── Save License ───────────────────────────────────────
    function saveLicense() {
        if (!validateForm()) return;

        btnSave.disabled = true;
        vendorShowStatus(genStatus, 'Saving license...', 'info');

        var payload = {
            issued_to: fieldClient.value.trim(),
            domain_name: fieldDomain.value.trim(),
            deployment_id: fieldDeployId.value.trim(),
            expires_on: fieldExpiry.value,
            max_domains: parseInt(fieldMaxDomains.value) || 1,
            type: fieldType.value
        };

        apiCall('vendor_save', 'POST', payload).then(function(res) {
            if (res.success) {
                var savedId = (res.license && (res.license.license_id || res.license.id)) || 'unknown';
                vendorShowStatus(genStatus, 'License ' + savedId + ' saved securely.', 'ok');
                vendorLog('Saved license ' + savedId + ' for ' + payload.issued_to, 'ok');
                resetForm();
                loadLicenses();
            } else {
                vendorShowStatus(genStatus, res.message || 'Save failed.', 'err');
                vendorLog('Save failed: ' + (res.message || 'Unknown error'), 'err');
            }
        }).catch(function(err) {
            vendorShowStatus(genStatus, 'Network error.', 'err');
            vendorLog('Save network error: ' + err.message, 'err');
        }).then(function() {
            btnSave.disabled = false;
        });
    }

    // ── Load Licenses ──────────────────────────────────────
    function loadLicenses() {
        apiCall('vendor_list', 'GET').then(function(res) {
            if (res.success && res.licenses) {
                cachedLicenses = res.licenses;
                renderLicenses(res.licenses);
                if (licenseCount) licenseCount.textContent = res.licenses.length;
                vendorLog('Loaded ' + res.licenses.length + ' license(s)', 'info');
            } else {
                licenseBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Could not load licenses.</td></tr>';
            }
        }).catch(function(err) {
            licenseBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Network error loading licenses.</td></tr>';
            vendorLog('Load error: ' + err.message, 'err');
        });
    }

    // ── Render Licenses ────────────────────────────────────
    function renderLicenses(licenses) {
        if (!licenseBody) return;
        if (!licenses || !licenses.length) {
            licenseBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No licenses generated yet. Use the form above to create one.</td></tr>';
            return;
        }

        licenseBody.innerHTML = licenses.map(function(lic) {
            var expiresOn = lic.expires_on || '';
            var typeLabel = lic.type === 'renew' ? 'Renew' : 'Issue';
            var typeClass = lic.type === 'renew' ? 'vendor-badge-renew' : 'vendor-badge-issue';

            var panelOpen = activeInlinePanelId === lic.id;
            var isEditing = panelOpen && activeInlinePanelMode === 'edit';
            var isVerifying = panelOpen && activeInlinePanelMode === 'verify';
            var panelContent = '';
            if (isEditing) panelContent = buildInlineEditPanel(lic);
            else if (isVerifying) panelContent = buildInlineVerifyPanel(lic, verifyResultCache[lic.id]);

            return '<tr class="vendor-lic-row' + (panelOpen ? ' vendor-lic-row-active' : '') + '" data-lic-id="' + escapeHtml(lic.id) + '">' +
                '<td><span class="vendor-lic-id">' + escapeHtml(lic.id) + '</span></td>' +
                '<td><strong>' + escapeHtml(lic.issued_to) + '</strong></td>' +
                '<td>' + escapeHtml(lic.domain_name) + '</td>' +
                '<td><span class="vendor-badge ' + typeClass + '">' + typeLabel + '</span></td>' +
                '<td class="text-muted vendor-date-cell">' + escapeHtml(lic.created_at || lic.issued_at || '') + '</td>' +
                '<td class="text-muted vendor-date-cell">' + escapeHtml(expiresOn) + '</td>' +
                '<td class="vendor-countdown" data-expires="' + escapeHtml(expiresOn) + '">' + calcDurationText(expiresOn) + '</td>' +
                '<td class="vendor-actions-cell">' +
                    '<button type="button" class="btn btn-outline-primary btn-sm px-2 py-0 vendor-license-action-btn" title="Download PEM" data-action="dl-pem" data-id="' + escapeHtml(lic.id) + '"><i class="fas fa-file-archive"></i></button>' +
                    '<button type="button" class="btn btn-outline-primary btn-sm px-2 py-0 vendor-license-action-btn' + (isEditing ? ' active' : '') + '" title="Edit" data-action="edit" data-id="' + escapeHtml(lic.id) + '"><i class="fas fa-pen"></i></button>' +
                    '<button type="button" class="btn btn-outline-primary btn-sm px-2 py-0 vendor-license-action-btn' + (isVerifying ? ' active' : '') + '" title="Verify" data-action="verify" data-id="' + escapeHtml(lic.id) + '"><i class="fas fa-shield-alt"></i></button>' +
                    '<button type="button" class="btn btn-outline-danger btn-sm px-2 py-0 vendor-license-action-btn" title="Delete" data-action="delete" data-id="' + escapeHtml(lic.id) + '"><i class="fas fa-trash"></i></button>' +
                '</td>' +
                '</tr>' +
                '<tr class="vendor-lic-panel-row" data-lic-id="' + escapeHtml(lic.id) + '"' + (panelOpen ? '' : ' style="display:none;"') + '>' +
                '<td colspan="8">' + panelContent + '</td>' +
                '</tr>';
        }).join('');

        attachActionHandlers();
        attachInlineEditHandlers();
        if (activeInlinePanelId) attachInlinePanelHandlers();
    }

    function lockedField(label, value) {
        return '<div class="vendor-inline-field">' +
            '<label class="form-label"><i class="fas fa-lock text-muted me-1" style="font-size:0.65rem;"></i>' + label + '</label>' +
            '<input type="text" class="form-control vendor-field-locked" readonly tabindex="-1" value="' + escapeHtml(value || '') + '">' +
        '</div>';
    }

    function buildInlineEditPanel(lic) {
        var status = lic.status || 'active';
        return '<div class="vendor-inline-edit-panel" data-lic-id="' + escapeHtml(lic.id) + '">' +
            '<div class="vendor-inline-edit-head">' +
                '<span class="vendor-inline-edit-title"><i class="fas fa-edit me-1"></i>Edit License</span>' +
                '<span class="vendor-inline-edit-id">' + escapeHtml(lic.id) + '</span>' +
            '</div>' +
            '<div class="vendor-inline-edit-grid">' +
                '<div class="vendor-inline-field">' +
                    '<label class="form-label">Client Name</label>' +
                    '<input type="text" class="form-control" data-field="issued_to" value="' + escapeHtml(lic.issued_to || '') + '">' +
                '</div>' +
                lockedField('Domain Name', lic.domain_name) +
                '<div class="vendor-inline-field">' +
                    '<label class="form-label">Expiry Date</label>' +
                    '<input type="date" class="form-control" data-field="expires_on" value="' + escapeHtml(lic.expires_on || '') + '">' +
                '</div>' +
                '<div class="vendor-inline-field">' +
                    '<label class="form-label">Max Domains</label>' +
                    '<input type="number" class="form-control" data-field="max_domains" min="0" value="' + escapeHtml(String(lic.max_domains != null ? lic.max_domains : 1)) + '">' +
                '</div>' +
                '<div class="vendor-inline-field">' +
                    '<label class="form-label">Status</label>' +
                    '<select class="form-control" data-field="status">' +
                        '<option value="active"' + (status === 'active' ? ' selected' : '') + '>Active</option>' +
                        '<option value="expired"' + (status === 'expired' ? ' selected' : '') + '>Expired</option>' +
                        '<option value="revoked"' + (status === 'revoked' ? ' selected' : '') + '>Revoked</option>' +
                    '</select>' +
                '</div>' +
                lockedField('Product Name', lic.product_name || 'AccessPilot') +
                '<div class="vendor-inline-field vendor-field-full">' +
                    '<label class="form-label"><i class="fas fa-lock text-muted me-1" style="font-size:0.65rem;"></i>Deployment ID</label>' +
                    '<input type="text" class="form-control vendor-field-locked vendor-deploy-input" readonly tabindex="-1" value="' + escapeHtml(lic.deployment_id || '') + '">' +
                '</div>' +
            '</div>' +
            '<div class="app-form-actions vendor-inline-panel-actions">' +
                '<button type="button" class="btn btn-primary vendor-inline-save"><i class="fas fa-save"></i> Save Changes</button>' +
                '<button type="button" class="btn btn-secondary vendor-inline-cancel"><i class="fas fa-times"></i> Cancel</button>' +
            '</div>' +
            '<div class="vendor-inline-edit-status"></div>' +
        '</div>';
    }

    function buildInlineVerifyPanel(lic, result) {
        var bodyHtml = '<div class="vendor-inline-verify-loading text-center py-3"><i class="fas fa-spinner fa-spin me-1"></i> Verifying...</div>';

        if (result && result === 'error') {
            bodyHtml = '<div class="vendor-inline-verify-error text-danger py-2">Network error during verification.</div>';
        } else if (result && result.success === false) {
            bodyHtml = '<div class="vendor-inline-verify-error text-danger py-2">' + escapeHtml(result.message || 'Verification failed.') + '</div>';
        } else if (result && result.checks) {
            var overallIcon = result.overall === 'pass' ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-warning';
            var overallText = result.overall === 'pass' ? 'All checks passed' : 'Warnings found';
            bodyHtml = '<div class="vendor-inline-verify-summary">' +
                '<i class="fas ' + overallIcon + '"></i>' +
                '<span class="vendor-inline-verify-summary-text">' + overallText + '</span>' +
            '</div><div class="vendor-verify-list">';
            result.checks.forEach(function(check) {
                var icon = check.status === 'pass' ? 'fa-check-circle text-success' :
                    (check.status === 'warn' ? 'fa-exclamation-triangle text-warning' : 'fa-times-circle text-danger');
                bodyHtml += '<div class="vendor-verify-item">' +
                    '<i class="fas ' + icon + '"></i>' +
                    '<span class="vendor-verify-item-label">' + escapeHtml(check.label) + '</span>';
                if (check.note) bodyHtml += '<span class="vendor-verify-item-note">' + escapeHtml(check.note) + '</span>';
                bodyHtml += '</div>';
            });
            bodyHtml += '</div>';
        }

        return '<div class="vendor-inline-verify-panel" data-lic-id="' + escapeHtml(lic.id) + '">' +
            '<div class="vendor-inline-edit-head">' +
                '<span class="vendor-inline-edit-title"><i class="fas fa-shield-alt me-1"></i>Verification Results</span>' +
                '<span class="vendor-inline-edit-id">' + escapeHtml(lic.id) + '</span>' +
            '</div>' +
            '<div class="vendor-inline-verify-body">' + bodyHtml + '</div>' +
            '<div class="app-form-actions vendor-inline-panel-actions">' +
                '<button type="button" class="btn btn-secondary vendor-inline-close"><i class="fas fa-times"></i> Close</button>' +
            '</div>' +
        '</div>';
    }

    function getCachedLicense(id) {
        for (var i = 0; i < cachedLicenses.length; i++) {
            if (cachedLicenses[i].id === id) return cachedLicenses[i];
        }
        return null;
    }

    function closeInlinePanels() {
        activeInlinePanelId = null;
        activeInlinePanelMode = null;
        document.querySelectorAll('.vendor-lic-panel-row').forEach(function(row) {
            row.style.display = 'none';
        });
        document.querySelectorAll('.vendor-lic-row').forEach(function(row) {
            row.classList.remove('vendor-lic-row-active');
        });
        document.querySelectorAll('.vendor-license-action-btn').forEach(function(btn) {
            btn.classList.remove('active');
        });
    }

    function scrollToPanel(id) {
        var panelRow = document.querySelector('.vendor-lic-panel-row[data-lic-id="' + id + '"]');
        if (panelRow) panelRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function openInlineEdit(id) {
        if (activeInlinePanelId === id && activeInlinePanelMode === 'edit') {
            closeInlinePanels();
            return;
        }
        closeInlinePanels();
        activeInlinePanelId = id;
        activeInlinePanelMode = 'edit';
        renderLicenses(cachedLicenses);
        scrollToPanel(id);
        vendorLog('Editing license ' + id + ' inline', 'info');
    }

    function openInlineVerify(id) {
        if (activeInlinePanelId === id && activeInlinePanelMode === 'verify') {
            closeInlinePanels();
            return;
        }
        closeInlinePanels();
        activeInlinePanelId = id;
        activeInlinePanelMode = 'verify';
        verifyResultCache[id] = 'loading';
        renderLicenses(cachedLicenses);
        scrollToPanel(id);
        vendorLog('Verifying license ' + id + '...', 'info');

        apiCall('vendor_verify', 'POST', { id: id }).then(function(res) {
            verifyResultCache[id] = res;
            if (activeInlinePanelId === id && activeInlinePanelMode === 'verify') {
                var cell = document.querySelector('.vendor-lic-panel-row[data-lic-id="' + id + '"] td');
                var lic = getCachedLicense(id);
                if (cell && lic) {
                    cell.innerHTML = buildInlineVerifyPanel(lic, res);
                    attachInlinePanelHandlers(cell);
                }
                vendorLog('Verification complete for ' + id + ' (' + (res.overall || 'unknown') + ')', res.overall === 'pass' ? 'ok' : 'warn');
            }
        }).catch(function() {
            verifyResultCache[id] = 'error';
            if (activeInlinePanelId === id && activeInlinePanelMode === 'verify') {
                var cell = document.querySelector('.vendor-lic-panel-row[data-lic-id="' + id + '"] td');
                var lic = getCachedLicense(id);
                if (cell && lic) {
                    cell.innerHTML = buildInlineVerifyPanel(lic, 'error');
                    attachInlinePanelHandlers(cell);
                }
            }
            vendorLog('Verify network error', 'err');
        });
    }

    function attachInlinePanelHandlers(scope) {
        var root = scope || licenseBody;
        if (!root) return;
        root.querySelectorAll('.vendor-inline-cancel, .vendor-inline-close').forEach(function(btn) {
            btn.onclick = function() {
                closeInlinePanels();
                renderLicenses(cachedLicenses);
                vendorLog('Panel closed', 'info');
            };
        });
        root.querySelectorAll('.vendor-inline-save').forEach(function(btn) {
            btn.onclick = function() {
                saveInlineEdit(btn.closest('.vendor-inline-edit-panel'));
            };
        });
    }

    function attachInlineEditHandlers() {
        if (!licenseBody || licenseBody.dataset.inlineBound === 'true') return;
        licenseBody.dataset.inlineBound = 'true';
        attachInlinePanelHandlers(licenseBody);
    }

    function readInlineField(panel, field) {
        var el = panel.querySelector('[data-field="' + field + '"]');
        return el ? el.value.trim() : '';
    }

    function saveInlineEdit(panel) {
        if (!panel) return;
        var id = panel.getAttribute('data-lic-id');
        if (!id) return;

        var statusEl = panel.querySelector('.vendor-inline-edit-status');
        var saveBtn = panel.querySelector('.vendor-inline-save');

        var lic = getCachedLicense(id);
        if (!lic) return;

        var data = {
            id: id,
            issued_to: readInlineField(panel, 'issued_to'),
            domain_name: lic.domain_name || '',
            deployment_id: lic.deployment_id || '',
            expires_on: readInlineField(panel, 'expires_on'),
            max_domains: parseInt(readInlineField(panel, 'max_domains'), 10) || 1,
            status: readInlineField(panel, 'status'),
            product_name: lic.product_name || 'AccessPilot'
        };

        if (saveBtn) saveBtn.disabled = true;
        vendorShowStatus(statusEl, 'Saving...', 'info');

        apiCall('vendor_update', 'POST', data).then(function(res) {
            if (res.success) {
                vendorShowStatus(statusEl, 'Saved successfully.', 'ok');
                vendorLog('Updated license ' + id, 'ok');
                closeInlinePanels();
                loadLicenses();
            } else {
                vendorShowStatus(statusEl, res.message || 'Update failed.', 'err');
                vendorLog('Update failed: ' + (res.message || 'Unknown error'), 'err');
            }
        }).catch(function(err) {
            vendorShowStatus(statusEl, 'Network error.', 'err');
            vendorLog('Update network error: ' + err.message, 'err');
        }).then(function() {
            if (saveBtn) saveBtn.disabled = false;
        });
    }

    // ── Countdown Calculation ───────────────────────────────
    function calcDurationText(expiresOn) {
        if (!expiresOn) return '<span class="text-muted">N/A</span>';
        var exp = new Date(expiresOn + 'T23:59:59');
        var now = new Date();
        if (isNaN(exp.getTime())) return '<span class="text-muted">Invalid</span>';
        if (now >= exp) return '<span class="text-danger fw-bold">Expired</span>';

        var y = exp.getFullYear() - now.getFullYear();
        var mo = exp.getMonth() - now.getMonth();
        var d = exp.getDate() - now.getDate();
        var h = exp.getHours() - now.getHours();
        var mi = exp.getMinutes() - now.getMinutes();
        var s = exp.getSeconds() - now.getSeconds();

        if (s < 0) { mi--; s += 60; }
        if (mi < 0) { h--; mi += 60; }
        if (h < 0) { d--; h += 24; }
        if (d < 0) { mo--;
            var pm = new Date(exp.getFullYear(), exp.getMonth() - 1, 1);
            d += new Date(pm.getFullYear(), pm.getMonth() + 1, 0).getDate();
        }
        if (mo < 0) { y--; mo += 12; }

        var parts = [];
        if (y > 0) parts.push(y + 'y');
        if (mo > 0) parts.push(mo + 'mo');
        if (d > 0) parts.push(d + 'd');
        if (h > 0) parts.push(h + 'h');
        if (mi > 0) parts.push(mi + 'm');
        if (s >= 0) parts.push(s + 's');

        var color = y > 0 ? '#16a34a' : (mo > 0 ? '#16a34a' : (d > 30 ? '#f59e0b' : (d > 7 ? '#f59e0b' : '#dc2626')));
        return '<span style="color:' + color + ';font-weight:600;">' + parts.join(' ') + '</span>';
    }

    function updateCountdowns() {
        var cells = document.querySelectorAll('.vendor-countdown');
        cells.forEach(function(cell) {
            var exp = cell.getAttribute('data-expires');
            cell.innerHTML = calcDurationText(exp);
        });
    }

    function startCountdownTimer() {
        if (countdownInterval) clearInterval(countdownInterval);
        countdownInterval = setInterval(updateCountdowns, 1000);
    }

    // ── Action Handlers ────────────────────────────────────
    function attachActionHandlers() {
        document.querySelectorAll('.vendor-license-action-btn').forEach(function(btn) {
            btn.removeEventListener('click', handleAction);
            btn.addEventListener('click', handleAction);
        });
    }

    function handleAction(e) {
        var btn = e.currentTarget;
        var action = btn.getAttribute('data-action');
        var id = btn.getAttribute('data-id');
        if (!id) return;

        if (action === 'dl-pem') downloadLicense(id);
        else if (action === 'edit') openInlineEdit(id);
        else if (action === 'verify') openInlineVerify(id);
        else if (action === 'delete') deleteLicense(id);
    }

    // ── Download PEM ─────────────────────────────────────────
    function downloadLicense(id) {
        vendorLog('Downloading ' + id + ' as PEM', 'info');
        var url = API_URL + '&action=vendor_download&id=' + encodeURIComponent(id);
        window.open(url, '_blank');
    }

    // ── Delete ──────────────────────────────────────────────
    function deleteLicense(id) {
        if (!confirm('Are you sure you want to delete license ' + id + '? This cannot be undone.')) return;

        vendorLog('Deleting license ' + id + '...', 'warn');

        apiCall('vendor_delete', 'POST', { id: id }).then(function(res) {
            if (res.success) {
                vendorLog('Deleted license ' + id, 'ok');
                loadLicenses();
            } else {
                vendorLog('Delete failed: ' + (res.message || 'Unknown error'), 'err');
            }
        }).catch(function(err) {
            vendorLog('Delete network error: ' + err.message, 'err');
        });
    }

    // ── Key Management ─────────────────────────────────────
    var keyStatusBadge = null;
    var keyInput = null;
    var btnSaveKey = null;
    var btnDeleteKey = null;
    var vendorKeyStatus = null;
    var keyInfo = null;
    var btnBuildRelease = null;
    var vendorReleaseStatus = null;

    // ── Client Release Pack ──────────────────────────────
    function buildRelease() {
        if (!btnBuildRelease || !vendorReleaseStatus) return;

        var orgInput = $('vFieldOrgName');
        var orgName = orgInput ? orgInput.value.trim() : '';
        if (!orgName) {
            vendorReleaseStatus.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Enter organization name.';
            vendorReleaseStatus.style.color = '#dc2626';
            return;
        }

        btnBuildRelease.disabled = true;
        btnBuildRelease.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Packaging...';
        vendorReleaseStatus.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Building release package...';
        vendorReleaseStatus.style.color = '#06b6d4';
        vendorLog('Building release for ' + orgName, 'info');

        apiCall('vendor_build_release', 'POST', { org_name: orgName }).then(function(res) {
            if (res.success) {
                vendorReleaseStatus.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> ' + res.zip_name + ' (' + (res.file_count || '') + ' files) — starting download...';
                vendorReleaseStatus.style.color = '#16a34a';
                vendorLog('Release ready: ' + res.zip_name, 'ok');

                setTimeout(function() {
                    var url = API_URL + '&action=vendor_download_release';
                    window.location.href = url;
                    btnBuildRelease.disabled = false;
                    btnBuildRelease.innerHTML = '<i class="fas fa-cube me-1"></i>Build &amp; Download';
                }, 600);
            } else {
                vendorReleaseStatus.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> ' + (res.message || 'Build failed.');
                vendorReleaseStatus.style.color = '#dc2626';
                vendorLog('Release build failed: ' + (res.message || 'Unknown'), 'err');
                btnBuildRelease.disabled = false;
                btnBuildRelease.innerHTML = '<i class="fas fa-cube me-1"></i>Build &amp; Download';
            }
        }).catch(function(err) {
            vendorReleaseStatus.innerHTML = '<i class="fas fa-times-circle me-1"></i> Network error: ' + (err.message || '');
            vendorReleaseStatus.style.color = '#dc2626';
            vendorLog('Release build error: ' + err.message, 'err');
            btnBuildRelease.disabled = false;
            btnBuildRelease.innerHTML = '<i class="fas fa-cube me-1"></i>Build &amp; Download';
        });
    }

    function checkKeyStatus() {
        apiCall('vendor_key_status', 'GET').then(function(res) {
            if (res.success && res.has_private_key) {
                var bits = (res.key_info && res.key_info.bits) || '?';
                keyStatusBadge.textContent = 'Active (' + bits + '-bit RSA)';
                keyStatusBadge.className = 'vendor-key-badge active';
                if (keyInfo) keyInfo.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Private key is configured. Licenses will be signed with RSA-SHA256 on download.';
                vendorLog('Private key detected (' + bits + '-bit RSA)', 'ok');
            } else {
                keyStatusBadge.textContent = 'Not Configured';
                keyStatusBadge.className = 'vendor-key-badge inactive';
                if (keyInfo) keyInfo.innerHTML = '<i class="fas fa-info-circle me-1"></i> Upload your RSA private key (PEM) to enable automatic RSA-SHA256 signing on download.';
                vendorLog('No private key configured — licenses will be unsigned', 'warn');
            }
        }).catch(function() {
            keyStatusBadge.textContent = 'Error';
            keyStatusBadge.className = 'vendor-key-badge inactive';
        });
    }

    function saveKey() {
        var content = keyInput ? keyInput.value.trim() : '';
        if (!content) {
            vendorShowStatus(vendorKeyStatus, 'Paste your private key PEM content first.', 'err');
            return;
        }
        btnSaveKey.disabled = true;
        vendorShowStatus(vendorKeyStatus, 'Validating and saving key...', 'info');
        apiCall('vendor_save_key', 'POST', { private_key: content }).then(function(res) {
            if (res.success) {
                vendorShowStatus(vendorKeyStatus, res.message, 'ok');
                vendorLog('Private key saved (' + (res.bits || '?') + '-bit RSA)', 'ok');
                keyInput.value = '';
                checkKeyStatus();
            } else {
                vendorShowStatus(vendorKeyStatus, res.message || 'Failed to save key.', 'err');
                vendorLog('Key save failed: ' + (res.message || 'Unknown error'), 'err');
            }
        }).catch(function(err) {
            vendorShowStatus(vendorKeyStatus, 'Network error.', 'err');
            vendorLog('Key save network error: ' + err.message, 'err');
        }).then(function() { btnSaveKey.disabled = false; });
    }

    function deleteKey() {
        if (!confirm('Remove the private key? Licenses will no longer be signed on download.')) return;
        apiCall('vendor_delete_key', 'POST', {}).then(function(res) {
            if (res.success) {
                vendorShowStatus(vendorKeyStatus, 'Key removed.', 'ok');
                vendorLog('Private key deleted', 'warn');
                checkKeyStatus();
            } else {
                vendorShowStatus(vendorKeyStatus, res.message || 'Failed to remove key.', 'err');
            }
        }).catch(function() {
            vendorShowStatus(vendorKeyStatus, 'Network error.', 'err');
        });
    }

    // ── Deployment ID Auto-Decode ─────────────────────────
    function decodeDeploymentId() {
        var val = fieldDeployId ? fieldDeployId.value.trim() : '';
        if (!val || val.length < 20) {
            deployDecodeStatus.textContent = '';
            deployDecodeStatus.style.color = '';
            return;
        }
        var url = API_URL + '&action=vendor_decode_deploy&deployment_id=' + encodeURIComponent(val);
        fetch(url, { method: 'GET', headers: getCsrfHeaders(), credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success && res.org_name) {
                if (fieldClient) fieldClient.value = res.org_name;
                if (fieldDomain) fieldDomain.value = res.domain_name;
                deployDecodeStatus.innerHTML = '<i class="fas fa-check-circle"></i> Decoded: <strong>' + escapeHtml(res.org_name) + '</strong> / ' + escapeHtml(res.domain_name);
                deployDecodeStatus.style.color = '#16a34a';
                vendorLog('Deployment ID decoded → ' + res.org_name + ' / ' + res.domain_name, 'ok');
            } else {
                deployDecodeStatus.innerHTML = '<i class="fas fa-info-circle"></i> ' + escapeHtml(res.message || 'Could not decode');
                deployDecodeStatus.style.color = '#f59e0b';
            }
        }).catch(function() {
            deployDecodeStatus.textContent = 'Decode check failed';
            deployDecodeStatus.style.color = '#dc2626';
        });
    }

    function onDeployIdChange() {
        if (deployDecodeTimer) clearTimeout(deployDecodeTimer);
        deployDecodeTimer = setTimeout(decodeDeploymentId, 600);
    }

    function insertDeployStatus() {
        if (!fieldDeployId || !fieldDeployId.parentNode) return;
        if (!deployDecodeStatus) {
            deployDecodeStatus = document.createElement('div');
            deployDecodeStatus.className = 'vendor-deploy-status';
        }
        if (deployDecodeStatus.parentNode !== fieldDeployId.parentNode) {
            fieldDeployId.parentNode.appendChild(deployDecodeStatus);
        }
    }

    function bindVendorRefs() {
        fieldClient = $('vFieldClient');
        fieldDomain = $('vFieldDomain');
        fieldDeployId = $('vFieldDeployId');
        fieldExpiry = $('vFieldExpiry');
        fieldMaxDomains = $('vFieldMaxDomains');
        fieldType = $('vFieldType');
        btnSave = $('vBtnSave');
        btnReset = $('vBtnReset');
        btnRefresh = $('vBtnRefreshList');
        btnClearLog = $('vBtnClearLog');
        genStatus = $('vendorGenStatus');
        licenseBody = $('vLicenseBody');
        licenseCount = $('vLicenseCount');
        consoleLog = $('vendorConsoleLog');
        keyStatusBadge = $('vKeyStatusBadge');
        keyInput = $('vKeyInput');
        btnSaveKey = $('vBtnSaveKey');
        btnDeleteKey = $('vBtnDeleteKey');
        vendorKeyStatus = $('vendorKeyStatus');
        keyInfo = $('vKeyInfo');
        btnBuildRelease = $('vBtnBuildRelease');
        vendorReleaseStatus = $('vendorReleaseStatus');
    }

    // ── Clear Console ──────────────────────────────────────
    function clearConsole() {
        if (!consoleLog) return;
        consoleLog.innerHTML = '<div class="vendor-console-line text-muted">// Console cleared</div>';
    }

    // ── Credential Verification ────────────────────────────
    function vendorVerifyCredentials() {
        var uid = $('vendorCredUserId');
        var pwd = $('vendorCredPassword');
        var feedback = $('vendorCredFeedback');
        var confirmBtn = $('vendorCredConfirm');

        if (!uid || !pwd || !feedback || !confirmBtn) return;

        var userId = uid.value.trim();
        var password = pwd.value;

        if (!userId || !password) {
            feedback.textContent = 'Enter both User ID and Password.';
            return;
        }

        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

        var url = API_URL + '&action=vendor_verify_creds';

        fetch(url, {
            method: 'POST',
            headers: getCsrfHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ user_id: userId, password: password })
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                var modalEl = document.getElementById('vendorCredentialModal');
                if (modalEl) {
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                vendorRevealContent();
                vendorLog('Credential verified — page unlocked', 'ok');
            } else {
                feedback.textContent = res.message || 'Invalid credentials.';
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i>Verify';
            }
        })
        .catch(function() {
            feedback.textContent = 'Network error. Try again.';
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i>Verify';
        });
    }

    function vendorRevealContent() {
        var root = document.querySelector('.vendor-console-container');
        if (!root || root.dataset.initialized === 'true') return;
        root.dataset.initialized = 'true';

        root.classList.add('vendor-console-unlocked');

        bindVendorRefs();

        vendorLog('Vendor Console initialized', 'info');
        setDefaultExpiry();
        loadLicenses();
        startCountdownTimer();
        checkKeyStatus();
        insertDeployStatus();

        if (btnSave) btnSave.addEventListener('click', saveLicense);
        if (btnReset) btnReset.addEventListener('click', resetForm);
        if (btnRefresh) btnRefresh.addEventListener('click', loadLicenses);
        if (btnClearLog) btnClearLog.addEventListener('click', clearConsole);
        if (btnSaveKey) btnSaveKey.addEventListener('click', saveKey);
        if (btnDeleteKey) btnDeleteKey.addEventListener('click', deleteKey);
        if (btnBuildRelease) btnBuildRelease.addEventListener('click', buildRelease);
        if (fieldDeployId) {
            fieldDeployId.addEventListener('input', onDeployIdChange);
            fieldDeployId.addEventListener('change', onDeployIdChange);
        }
    }

    function relocatePageModals(ids) {
        ids.forEach(function(id) {
            var nodes = document.querySelectorAll('#' + id);
            if (!nodes.length) return;
            var target = nodes[nodes.length - 1];
            nodes.forEach(function(node) {
                if (node !== target) node.remove();
            });
            if (target.parentElement !== document.body) {
                document.body.appendChild(target);
            }
        });
    }

    function showCredentialModal() {
        var modalEl = document.getElementById('vendorCredentialModal');
        if (!modalEl) return;

        relocatePageModals(['vendorCredentialModal']);

        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        var confirmBtn = $('vendorCredConfirm');
        var cancelBtn = $('vendorCredCancel');

        function onConfirm() { vendorVerifyCredentials(); }
        function onCancel() {
            var m = bootstrap.Modal.getInstance(modalEl);
            if (m) m.hide();
        }
        function onKeydown(e) {
            if (e.key === 'Enter') vendorVerifyCredentials();
        }

        if (confirmBtn) {
            confirmBtn.removeEventListener('click', onConfirm);
            confirmBtn.addEventListener('click', onConfirm);
        }
        if (cancelBtn) {
            cancelBtn.removeEventListener('click', onCancel);
            cancelBtn.addEventListener('click', onCancel);
        }

        var uidInput = $('vendorCredUserId');
        var pwdInput = $('vendorCredPassword');
        if (uidInput) {
            uidInput.removeEventListener('keydown', onKeydown);
            uidInput.addEventListener('keydown', onKeydown);
            setTimeout(function() { uidInput.focus(); }, 300);
        }
        if (pwdInput) {
            pwdInput.removeEventListener('keydown', onKeydown);
            pwdInput.addEventListener('keydown', onKeydown);
        }

        var feedback = $('vendorCredFeedback');
        if (feedback) feedback.textContent = '';

        var toggleBtn = $('vendorCredTogglePw');
        if (toggleBtn) {
            toggleBtn.onclick = function() {
                var inp = $('vendorCredPassword');
                if (!inp) return;
                var isPw = inp.type === 'password';
                inp.type = isPw ? 'text' : 'password';
                toggleBtn.innerHTML = isPw ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
            };
        }
    }

    function initVendorConsole() {
        var root = document.querySelector('.vendor-console-container');
        if (!root || root.dataset.initialized === 'true') return;

        var modalEl = document.getElementById('vendorCredentialModal');
        if (!modalEl) {
            vendorRevealContent();
            return;
        }

        var url = API_URL + '&action=vendor_check_creds';
        var opts = { method: 'GET', headers: getCsrfHeaders(), credentials: 'same-origin' };
        fetch(url, opts)
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success && res.verified) {
                vendorRevealContent();
            } else {
                showCredentialModal();
            }
        })
        .catch(function() {
            showCredentialModal();
        });
    }

    document.addEventListener('DOMContentLoaded', initVendorConsole);
    document.addEventListener('spaContentUpdated', initVendorConsole);
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initVendorConsole();
    }
})();
