/**
 * System Configuration — deployment identity, LDAP, PowerShell, storage.
 */
(function () {
    const API = 'system_config_api';

    const MODE_HINTS = {
        ldap: 'LDAP mode uses PHP ext-ldap for all directory operations. No PowerShell dependency.',
        powershell: 'PowerShell + IIS — all AD operations use deployed .ps1 scripts with stored credentials.',
    };

    function $(id) {
        return document.getElementById(id);
    }

    function initSystemConfig() {
        const form = $('systemConfigForm');
        if (!form || form.dataset.initialized === 'true') {
            return;
        }
        form.dataset.initialized = 'true';

        // Ribbon tab switching
        document.querySelectorAll('.noc-tab-item').forEach(function(t){
            t.addEventListener('click', function(){
                document.querySelectorAll('.noc-tab-item').forEach(function(x){ x.classList.remove('active'); });
                document.querySelectorAll('.noc-tab-content').forEach(function(x){ x.style.display = 'none'; });
                this.classList.add('active');
                var panel = document.getElementById('tab-' + this.dataset.tab);
                if (panel) { panel.style.display = 'block'; panel.classList.remove('app-hidden'); }
            });
        });

        const apiBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.apiBaseUrl) || 'api/index.php';
        const modalEl = $('credentialConfirmModal');
        let modal = null;
        if (modalEl && typeof bootstrap !== 'undefined') {
            if (!modalEl.parentElement || modalEl.parentElement === form) {
                document.body.appendChild(modalEl);
            }
            modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        }

        let pendingAction = null;
        let domainHint = { domain: '', base_dn: '' };
        let initialBackendMode = 'ldap';
        let diagTimer = null;

        function setBadge(el, ok, text) {
            if (!el) return;
            let cls = 'badge rounded-pill bg-secondary';
            if (ok === true) cls = 'badge rounded-pill bg-success';
            else if (ok === false) cls = 'badge rounded-pill bg-danger';
            el.className = cls;
            el.textContent = text;
        }

        function setStatusChip(el, level, text) {
            if (!el) return;
            const map = { ok: 'sys-chip-ok', warn: 'sys-chip-warn', crit: 'sys-chip-crit', info: 'sys-chip-info', neutral: 'sys-chip-neutral' };
            el.className = 'sys-status-chip ' + (map[level] || map.neutral);
            el.textContent = text;
            // Colorful badge for backend display
            if (el.id === 'ldap_card_backend' || el.classList.contains('sys-chip-backend')) {
                const t = (text || '').toUpperCase();
                if (t === 'LDAP') { el.className = 'sys-status-chip sys-brand-pill-ldap'; }
                else if (t === 'POWERSHELL') { el.className = 'sys-status-chip sys-brand-pill-ps'; }
                else if (t === 'AUTO') { el.className = 'sys-status-chip sys-brand-pill-auto'; }
            }
        }

        function renderIssuesList(issues) {
            const list = $('diag_issues_list');
            const hub = $('sys_health_hub');
            if (!list) return;

            const overall = (issues && issues.length) ? (window.__lastDiagHealth || 'healthy') : 'healthy';
            if (hub) {
                hub.classList.remove('sys-health-healthy', 'sys-health-warning', 'sys-health-critical', 'sys-health-unknown');
                hub.classList.add(
                    overall === 'critical' ? 'sys-health-critical'
                        : overall === 'warning' ? 'sys-health-warning'
                            : overall === 'healthy' ? 'sys-health-healthy' : 'sys-health-unknown'
                );
                hub.dataset.overall = overall;
            }

            const overallEl = $('diag_overall_val');
            if (overallEl) {
                const label = overall === 'critical' ? 'CRITICAL' : overall === 'warning' ? 'ATTENTION' : overall === 'healthy' ? 'HEALTHY' : 'CHECKING';
                const lvl = overall === 'critical' ? 'crit' : overall === 'warning' ? 'warn' : overall === 'healthy' ? 'ok' : 'neutral';
                overallEl.className = 'sys-status-chip sys-chip-overall sys-chip-' + (lvl === 'crit' ? 'crit' : lvl === 'warn' ? 'warn' : lvl === 'ok' ? 'ok' : 'neutral');
                overallEl.textContent = label;
            }

            if (!Array.isArray(issues) || issues.length === 0) {
                list.innerHTML = '<li class="sys-issue sys-issue-ok"><span class="sys-issue-dot"></span><div><strong>All clear</strong><p>No configuration issues detected.</p></div></li>';
                return;
            }

            list.innerHTML = issues.map((issue) => {
                const sev = issue.severity || 'info';
                const cls = sev === 'critical' ? 'sys-issue-critical'
                    : sev === 'warning' ? 'sys-issue-warning'
                        : sev === 'ok' ? 'sys-issue-ok' : 'sys-issue-info';
                const fix = issue.suggestion
                    ? `<p class="sys-issue-fix">${escapeHtml(issue.suggestion)}</p>`
                    : '';
                return `<li class="sys-issue ${cls}">
                    <span class="sys-issue-dot"></span>
                    <div>
                        <strong>${escapeHtml(issue.title || 'Notice')}</strong>
                        <p>${escapeHtml(issue.message || '')}</p>
                        ${fix}
                    </div>
                </li>`;
            }).join('');
        }

        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function showInline(el, message, type) {
            if (!el) return;
            el.classList.remove('sys-hidden', 'is-success', 'is-error', 'is-warn');
            el.style.display = 'block';
            el.textContent = message;
            el.classList.add(type === 'success' ? 'is-success' : type === 'warn' ? 'is-warn' : 'is-error');
        }

        function hideInline(el) {
            if (!el) el = null;
            if (el) {
                el.classList.add('sys-hidden');
                el.style.display = 'none';
            }
        }

        const STORED_PLACEHOLDER = '••••••••••••  (stored in secure vault)';

        function applyCredentialVaultUI(opts) {
            opts = opts || {};
            const psStored = !!opts.ps_password_stored;

            const psBadge = $('ps_admin_password_status');
            const psPass = $('config_admin_password');
            if (psBadge) {
                psBadge.classList.remove('sys-hidden');
                if (psStored) {
                    psBadge.className = 'badge rounded-pill bg-success sys-cred-badge';
                    psBadge.textContent = 'STORED IN VAULT';
                } else {
                    psBadge.className = 'badge rounded-pill bg-secondary sys-cred-badge';
                    psBadge.textContent = 'NOT SET';
                }
            }
            if (psPass) {
                psPass.value = '';
                psPass.dataset.stored = psStored ? '1' : '0';
                psPass.classList.toggle('stored-vault', psStored);
                psPass.placeholder = psStored ? STORED_PLACEHOLDER : 'Enter admin password to store in vault';
                psPass.required = !psStored;
            }
        }

        function clearStoredPasswordField(input) {
            if (!input || input.dataset.stored !== '1') return;
            input.dataset.stored = '0';
            input.classList.remove('stored-vault');
            input.value = '';
            input.placeholder = 'Enter admin password to store in vault';
        }

        function setBackendMode(mode, silent) {
            const input = $('ldapBackendModeInput');
            const badge = $('currentBackendBadge');
            if (input) input.value = mode;
            if (badge) {
                badge.textContent = mode.toUpperCase();
                badge.className = 'badge rounded-pill ' + (mode === 'ldap' ? 'sys-brand-pill-ldap' : 'sys-brand-pill-ps');
            }

            const hint = $('backend_mode_hint');
            if (hint) {
                hint.textContent = MODE_HINTS[mode] || '';
                hint.classList.remove('sys-hidden');
            }

            toggleBackendPanels(mode);
        }

        function toggleBackendPanels(mode) {
            var psContainer = $('ps_cred_fields_container');
            if (psContainer) psContainer.classList.toggle('sys-hidden', mode === 'ldap');
        }

        function unlockWorkspace() {
            ['password_row', 'storage_row', 'sys_status_dashboard'].forEach((id) => {
                const row = $(id);
                if (row) row.classList.remove('sys-hidden');
            });

            const selectors = 'input, select, textarea, button:not(#btnCancelStorage)';
            ['password_row', 'storage_row'].forEach((id) => {
                const row = $(id);
                if (!row) return;
                row.querySelectorAll(selectors).forEach((el) => {
                    if (el.id !== 'btnSaveCredentials' && el.id !== 'btnSavePasswords') {
                        el.disabled = false;
                    }
                });
            });

            const ackCred = $('ack_credentials');
            const ackPass = $('ack_passwords');
            if (ackCred) ackCred.disabled = false;
            if (ackPass) ackPass.disabled = false;

            syncAckButtons();
        }

        function syncAckButtons() {
            const btnCred = $('btnSaveCredentials');
            const ackCred = $('ack_credentials');
            if (btnCred && ackCred) btnCred.disabled = !ackCred.checked;

            const btnPass = $('btnSavePasswords');
            const ackPass = $('ack_passwords');
            if (btnPass && ackPass) btnPass.disabled = !ackPass.checked;
        }

        function updateStatusCardsFromConfig(s) {
            if (!s) return;
            // These update instantly from config API (no LDAP calls needed)
            const ext = s.ldap_extension;
            setBadge($('ldap_card_extension'), ext?.loaded, ext?.loaded ? 'LOADED' : '—');
            const lt = s.ldap_last_test;
            if (lt) {
                setBadge($('ldap_card_last_test'), lt.success, lt.success ? 'OK' : lt.at ? 'FAIL' : '—');
            }
            const ba = s.ldap_backend_active || 'powershell';
            const beEl = $('ldap_card_backend');
            if (beEl) {
                beEl.textContent = ba.toUpperCase();
                const b = ba.toLowerCase();
                if (b === 'ldap') beEl.className = 'badge rounded-pill sys-brand-pill-ldap';
                else if (b === 'powershell') beEl.className = 'badge rounded-pill sys-brand-pill-ps';
                else if (b === 'auto') beEl.className = 'badge rounded-pill sys-brand-pill-auto';
                else beEl.className = 'badge rounded-pill bg-secondary';
            }
        }

        function populateLdapUI(ldap, ldapStatus) {
            if (!ldap) return;

            const mode = ldap.backend || 'ldap';
            initialBackendMode = mode;
            setBackendMode(mode, true);

            applyCredentialVaultUI({
                ps_password_stored: window.__sysConfigData?.has_password,
            });

            syncAckButtons();

            const lt0 = ldapStatus?.ldap_last_test;
            setBadge($('status_ldap_connect'), lt0?.at ? lt0.success : null, lt0?.success ? 'BIND OK' : lt0?.at ? 'BIND FAILED' : 'NOT TESTED');

            updateStatusCardsFromConfig(ldapStatus);
        }

        function updateStorageBadges(status) {
            if (!status) return;
            setBadge($('status_secure_vault'), status.secure_vault?.connected, (status.secure_vault?.message || 'VAULT').toUpperCase().slice(0, 24));
            setBadge($('status_log_storage'), status.log_storage?.connected, (status.log_storage?.message || 'LOGS').toUpperCase().slice(0, 24));
        }

        function applyConfig(c) {
            if (!c) return;

            ['fld_org_name', 'fld_domain', 'fld_base_dn'].forEach((id) => {
                const el = $(id);
                const key = id.replace('fld_', '');
                if (el && c[key] !== undefined) el.value = c[key] || '';
            });

            const fMap = {
                config_default_password: 'default_password',
                config_application_user_password: 'application_user_password',
                config_admin_username: 'admin_username',
                config_secure_base_path: 'secure_base_path',
                config_base_log_path: 'base_log_path',
            };
            Object.keys(fMap).forEach((id) => {
                const el = $(id);
                if (el && c[fMap[id]] !== undefined) el.value = c[fMap[id]] || '';
            });

            if ($('pwd_reset_use_random') && c.pwd_reset_use_random !== undefined) {
                $('pwd_reset_use_random').checked = !!c.pwd_reset_use_random;
            }

            ['config_domain', 'config_base_dn'].forEach((id) => {
                const el = $(id);
                const key = id.replace('config_', '');
                if (el && c[key] !== undefined) el.value = c[key] || '';
            });

            applyCredentialVaultUI({
                ps_password_stored: !!c.has_password,
            });

            if (c.deployment_id && $('fld_deploy_id')) {
                $('fld_deploy_id').value = c.deployment_id;
                const gd = $('guide_deploy_id_txt');
                if (gd) gd.textContent = c.deployment_id;
                const ad = $('sys_activated_did');
                if (ad) ad.textContent = c.deployment_id;
            }

            if (c.org_name && c.org_name.trim() !== '') {
                if ($('lic_activated_org')) $('lic_activated_org').textContent = c.org_name;
                if ($('lic_activated_domain')) $('lic_activated_domain').textContent = c.domain || '—';
                const btnOrg = $('btnSubmitOrg');
                if (btnOrg) btnOrg.innerHTML = '<i class="fas fa-pen me-1"></i>Update';

                $('deploy_status_banner_row')?.classList.remove('sys-hidden');

                if (c.license_status) {
                    const st = c.license_status;
                    const ok = !st.is_restricted;
                    setBadge($('dash_license'), ok, ok ? 'ACTIVE' : 'RESTRICTED');
                    const ow = $('org_update_warning');
                    if (ok) {
                        ow?.classList.add('sys-hidden');
                        unlockWorkspace();
                    } else {
                        ow?.classList.remove('sys-hidden');
                    }

                    const banner = $('deploy_status_banner');
                    if (banner) {
                        banner.className = 'status-banner ' + (ok ? 'success' : 'danger');
                        const icon = banner.querySelector('.status-banner-icon i');
                        if (icon) icon.className = 'fas ' + (ok ? 'fa-check-circle' : 'fa-bug');
                        $('deploy_status_title').textContent = (ok ? 'ACTIVE' : 'INVALID SIGNATURE') + ' STATUS';
                        $('deploy_status_msg').textContent = ok
                            ? 'Your deployment is fully licensed and operational.'
                            : st.message || 'Your deployment is registered but unlicensed.';
                        const badge = $('deploy_status_badge');
                        if (badge) {
                            badge.innerHTML = ok
                                ? '<i class="fas fa-check-circle"></i>ACTIVE'
                                : '<i class="fas fa-ban"></i>RESTRICTED';
                            if (ok) {
                                badge.style.background = 'rgba(22,163,74,0.15)';
                                badge.style.color = '#16a34a';
                            }
                        }
                    }

                    const promptEl = $('lic_activate_prompt');
                    const activatedEl = $('lic_activated_msg');
                    if (promptEl && activatedEl) {
                        promptEl.classList.toggle('sys-hidden', ok);
                        activatedEl.classList.toggle('sys-hidden', !ok);
                    }

                    const certBadge = $('sys_cert_badge');
                    const certIdEl = $('sys_cert_id_text');
                    if (certBadge && certIdEl) {
                        if (ok && st.license_id) {
                            certBadge.classList.remove('sys-hidden');
                            certIdEl.textContent = st.license_id;
                        } else {
                            certBadge.classList.add('sys-hidden');
                            certIdEl.textContent = '—';
                        }
                    }

                    const orgInfo = $('sys_activated_org_info');
                    if (orgInfo) {
                        var orgName = c.org_name || st.issued_to || '—';
                        var domain = c.domain || st.domain_name || '—';
                        orgInfo.innerHTML = 'Organization <strong>' + orgName + '</strong> — bound to <strong>' + domain + '</strong>.';
                    }
                }
            }
        }

        async function loadConfig() {
            try {
                const response = await fetch(`${apiBaseUrl}?endpoint=${API}`);
                const result = await response.json();

                if (!result.success) return;

                window.__sysConfigData = result.config;
                domainHint = result.domain_hint || {
                    domain: result.config?.domain || '',
                    base_dn: result.config?.base_dn || '',
                };

                applyConfig(result.config);
                populateLdapUI(result.ldap, result.ldap_status);
                updateStorageBadges(result.status);
                applyCredentialVaultUI({
                    ps_password_stored: !!result.config?.has_password,
                });

                document.dispatchEvent(new CustomEvent('sysConfigLoaded', { detail: result.config }));
                runInfrastructureDiagnostics(false);
            } catch (error) {
                console.error('Failed to load system configuration:', error);
            }
        }

        function openConfirmModal(action) {
            pendingAction = action;
            const titles = {
                passwords: 'Confirm password update',
                identity: 'Confirm credential update',
                storage: 'Confirm storage mapping',
                integrations: 'Confirm API Integration update',
            };
            const descs = {
                passwords: 'Authorize updating application default passwords.',
                identity: 'Authorize updating PowerShell admin credentials (secure XML vault).',
                storage: 'Authorize updating vault and log storage paths.',
                integrations: 'Authorize updating HRMS API integration URLs.',
            };
            $('modalTitleText').textContent = titles[action] || 'Confirm change';
            $('modalDescText').textContent = descs[action] || 'Re-enter portal credentials.';
            hideInline($('modalFeedback'));
            $('confirm_user_id').value = '';
            $('confirm_password').value = '';

            // Ensure modal is on body (SPA stacking context fix)
            if (modalEl && modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
                modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            }
            modal?.show();

            // Focus first field after modal opens
            setTimeout(() => {
                const uid = $('confirm_user_id');
                if (uid) uid.focus();
            }, 300);
        }
        window.openConfirmModal = openConfirmModal;

        async function submitConfirmed() {
            const uid = $('confirm_user_id').value.trim();
            const pwd = $('confirm_password').value.trim();
            const feedback = $('modalFeedback');
            const confirmBtn = $('btnConfirmUpdate');

            if (!uid || !pwd) {
                showInline(feedback, 'Enter both User ID and Password.', 'warn');
                return;
            }

            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

            const base = { confirm_user_id: uid, confirm_password: pwd, action: pendingAction };

            let url = `${apiBaseUrl}?endpoint=${API}`;
            let body = {};

            if (pendingAction === 'passwords') {
                url += '&action=save_passwords';
                body = {
                    ...base,
                    application_user_password: $('config_application_user_password')?.value || '',
                    default_password: $('config_default_password')?.value || '',
                    pwd_reset_use_random: $('pwd_reset_use_random')?.checked || false,
                };
            } else if (pendingAction === 'storage') {
                url += '&action=save_storage';
                body = {
                    ...base,
                    secure_base_path: $('config_secure_base_path')?.value || '',
                    base_log_path: $('config_base_log_path')?.value || '',
                };
            } else if (pendingAction === 'identity') {
                url += '&action=save_config';
                const fd = new FormData(form);
                body = Object.fromEntries(fd.entries());
                Object.assign(body, base);
            } else if (pendingAction === 'integrations') {
                url += '&action=save_integrations';
                body = {
                    ...base,
                    api_paths: {
                        hrms_api_url: window.__pendingApiHrmsUrl || '',
                        hrms_img_url: window.__pendingApiImgUrl || '',
                        hrms_emp_sts_url: window.__pendingApiStsUrl || '',
                    },
                };
            }

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                });
                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid response: ' + text.slice(0, 120));
                }

                if (result.success) {
                    modal?.hide();
                    window.location.reload();
                } else {
                    showInline(feedback, result.message || 'Update failed.', 'error');
                }
            } catch (error) {
                showInline(feedback, 'Request: ' + error.message, 'error');
            } finally {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i>Confirm';
            }
        }

        async function saveOrg() {
            const btn = $('btnSubmitOrg');
            const org = $('fld_org_name').value.trim();
            const dom = $('fld_domain').value.trim();
            const dn = $('fld_base_dn').value.trim();
            const fb = $('org_feedback');

            if (!org || !dom || !dn) {
                showInline(fb, 'All organization fields are required.', 'error');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

            try {
                const response = await fetch(`${apiBaseUrl}?endpoint=${API}&action=save_org`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ org_name: org, domain: dom, base_dn: dn }),
                });
                const result = await response.json();
                if (result.success) {
                    hideInline(fb);
                    window.__sysConfigData = { ...(window.__sysConfigData || {}), org_name: org, domain: dom, base_dn: dn };
                    domainHint = { domain: dom, base_dn: dn };
                    applyConfig(window.__sysConfigData);
                    await loadConfig();
                    showInline(fb, 'Organization saved successfully.', 'success');
                    setTimeout(() => hideInline(fb), 3000);
                } else {
                    showInline(fb, result.message || 'Save failed.', 'error');
                }
            } catch (error) {
                showInline(fb, 'Request: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                const hasOrg = ($('fld_org_name')?.value || '').trim() !== '';
                btn.innerHTML = hasOrg
                    ? '<i class="fas fa-pen me-1"></i>Update'
                    : '<i class="fas fa-paper-plane me-1"></i>Register';
            }
        }

        function renderDiagnostics(result, apiLatencyMs) {
            const cred = result.credentials || {};
            const backend = (result.active_backend || cred.active_backend || 'powershell').toUpperCase();
            window.__lastDiagHealth = result.overall_health || 'healthy';

            // Active Domain card — show active domain label + reachable
            var activeDomain = null;
            if (result.domains) {
                for (var di = 0; di < result.domains.length; di++) {
                    if (result.domains[di].is_active) { activeDomain = result.domains[di]; break; }
                }
            }
            var domLabel = activeDomain ? (activeDomain.label || activeDomain.key || activeDomain.host || '—') : '—';
            var domReach = activeDomain ? activeDomain.reachable : null;
            var domReachCls = domReach === true ? 'ok' : domReach === false ? 'crit' : 'neutral';
            var domReachText = domReach === true ? 'REACHABLE' : domReach === false ? 'UNREACHABLE' : 'PENDING';
            var domEl = $('diag_domain_val');
            if (domEl) {
                domEl.textContent = domLabel + ' · ' + domReachText;
                domEl.className = 'sys-status-chip sys-chip-' + domReachCls + ' sys-dash-val';
            }

            // Backend card — show LDAP / PS / AUTO mode
            var beLabel = result.active_backend || cred.active_backend || 'powershell';
            var beUpper = beLabel.toUpperCase();
            var beEl = $('diag_pass_val');
            if (beEl) {
                beEl.textContent = beUpper;
                var beLower = beUpper.toLowerCase();
                if (beLower === 'ldap') beEl.className = 'sys-status-chip sys-brand-pill-ldap sys-dash-val';
                else if (beLower === 'powershell') beEl.className = 'sys-status-chip sys-brand-pill-ps sys-dash-val';
                else if (beLower === 'auto') beEl.className = 'sys-status-chip sys-brand-pill-auto sys-dash-val';
                else beEl.className = 'sys-status-chip sys-chip-neutral sys-dash-val';
            }

            const ttlEl = $('diag_ttl_display');
            const ttlHidden = $('diag_avg_ttl');
            const ttlText = apiLatencyMs + 'ms';
            const ttlLevel = apiLatencyMs < 500 ? 'ok' : apiLatencyMs < 1500 ? 'warn' : 'crit';
            setStatusChip(ttlEl, ttlLevel, ttlText);
            if (ttlHidden) ttlHidden.value = ttlText;

            renderIssuesList(result.issues || []);

            // Update PHP LDAP extension badge from diagnostics response
            if (result.ldap_extension) {
                setBadge($('ldap_card_extension'), result.ldap_extension.loaded, result.ldap_extension.loaded ? 'LOADED' : '—');
            }

            // Render per-domain cards
            renderDomainCards(result.domains, backend);

            applyCredentialVaultUI({
                ps_password_stored: cred.ps_password_stored,
            });
        }

        const DIAG_CACHE_KEY = 'sys_diagnostics_cache';

        function renderDiagnosticsCached(result, latencyMs) {
            try { sessionStorage.setItem(DIAG_CACHE_KEY, JSON.stringify({ result, latencyMs, at: Date.now() })); } catch (e) {}
            renderDiagnostics(result, latencyMs);
        }

        async function runInfrastructureDiagnostics(liveRefresh) {
            const start = performance.now();
            const btn = $('btnRefreshDiagnostics');
            if (btn && liveRefresh) {
                btn.disabled = true;
                btn.querySelector('i')?.classList.add('fa-spin');
            }

            // Show cached data immediately for instant display
            if (!liveRefresh) {
                try {
                    const cached = sessionStorage.getItem(DIAG_CACHE_KEY);
                    if (cached) {
                        const parsed = JSON.parse(cached);
                        if (parsed && parsed.result) {
                            renderDiagnostics(parsed.result, parsed.latencyMs || 0);
                        }
                    }
                } catch (e) {}
            }

            try {
                const qs = liveRefresh ? '&refresh=1' : '';
                const response = await fetch(`${apiBaseUrl}?endpoint=get_infrastructure_diagnostics${qs}`);
                const latency = Math.round(performance.now() - start);
                const result = await response.json();
                renderDiagnosticsCached(result, latency);
            } catch (error) {
                window.__lastDiagHealth = 'critical';
                var de = $('diag_domain_val'); if (de) { de.textContent = 'ERROR'; de.className = 'sys-status-chip sys-chip-crit sys-dash-val'; }
                var be = $('diag_pass_val'); if (be) { be.textContent = '—'; be.className = 'sys-status-chip sys-chip-crit sys-dash-val'; }
                setStatusChip($('diag_ttl_display'), 'crit', 'Timeout');
                renderIssuesList([{
                    severity: 'critical',
                    title: 'Diagnostics unavailable',
                    message: 'Could not reach the diagnostics API.',
                    suggestion: 'Check IIS application pool, PHP errors, and network connectivity to this server.',
                }]);
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.querySelector('i')?.classList.remove('fa-spin');
                }
            }
        }

        function copyDeployment() {
            const text = [
                'Organization: ' + ($('fld_org_name')?.value || ''),
                'Domain: ' + ($('fld_domain')?.value || ''),
                'Base DN: ' + ($('fld_base_dn')?.value || ''),
                'Deployment ID: ' + ($('fld_deploy_id')?.value || ''),
            ].join('\n');
            navigator.clipboard.writeText(text).catch(() => window.prompt('Copy:', text));
        }

        // Section collapse
        document.querySelectorAll('[data-toggle-section]').forEach((header) => {
            header.addEventListener('click', () => {
                const section = document.getElementById(header.dataset.toggleSection);
                if (!section) return;
                section.classList.toggle('collapsed');
            });
        });

        $('btnToggleBackend')?.addEventListener('click', function () {
            const input = $('ldapBackendModeInput');
            if (!input) return;
            const next = input.value === 'ldap' ? 'powershell' : 'ldap';
            setBackendMode(next, false);
        });

        $('btnTestBackend')?.addEventListener('click', function () {
            const input = $('ldapBackendModeInput');
            if (!input) return;
            const mode = input.value;

            const btn = this;
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

            const hint = $('backend_mode_hint');

            // Use GET endpoint — no credential confirmation needed
            var url = apiBaseUrl + '?endpoint=' + API + '&action=ldap_test_connect&_t=' + Date.now();
            if (mode !== 'ldap') {
                url += '&backend=powershell';
            }

            fetch(url, { method: 'GET', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (hint) {
                    var ok = res.success || (res.ldap && res.ldap.success);
                    hint.innerHTML = ok
                        ? '<span class="text-success"><i class="fas fa-check-circle me-1"></i>LDAP operational — ' + (res.message || 'connection OK') + '</span>'
                        : '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>LDAP test failed: ' + (res.message || 'Unknown error') + '</span>';
                    hint.classList.remove('sys-hidden');
                }
            })
            .catch(function () {
                if (hint) {
                    hint.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Network error during backend test.</span>';
                    hint.classList.remove('sys-hidden');
                }
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = orig;
            });
        });

        $('btnSaveBackendConfig')?.addEventListener('click', function () {
            pendingAction = 'save_ldap';
            openConfirmModal('save_ldap');
        });

        // Override submitConfirmed for save_ldap case
        var origSubmitConfirmed = submitConfirmed;
        submitConfirmed = async function () {
            if (pendingAction === 'save_ldap') {
                const uid = $('confirm_user_id').value.trim();
                const pwd = $('confirm_password').value.trim();
                const feedback = $('modalFeedback');
                const confirmBtn = $('btnConfirmUpdate');

                if (!uid || !pwd) {
                    showInline(feedback, 'Enter both User ID and Password.', 'warn');
                    return;
                }

                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

                const mode = ($('ldapBackendModeInput') || {}).value || 'ldap';

                try {
                    const response = await fetch(apiBaseUrl + '?endpoint=' + API + '&action=save_ldap', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            ldap_backend_mode: mode,
                            confirm_user_id: uid,
                            confirm_password: pwd,
                        }),
                    });
                    const res = await response.json();
                    if (res.success) {
                        if (modal) modal.hide();
                        var hint = $('backend_mode_hint');
                        if (hint) {
                            hint.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>' + (res.message || 'Backend saved') + '</span>';
                            hint.classList.remove('sys-hidden');
                        }
                    } else {
                        showInline(feedback, res.message || 'Save failed.', 'error');
                    }
                } catch (e) {
                    showInline(feedback, 'Request: ' + e.message, 'error');
                } finally {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i>Confirm';
                }
                return;
            }
            await origSubmitConfirmed.call(this);
        };

        $('ack_credentials')?.addEventListener('change', syncAckButtons);
        $('ack_passwords')?.addEventListener('change', syncAckButtons);

        $('btnSaveCredentials')?.addEventListener('click', () => openConfirmModal('identity'));
        $('btnSavePasswords')?.addEventListener('click', () => openConfirmModal('passwords'));
        $('btnSaveStorage')?.addEventListener('click', () => openConfirmModal('storage'));
        $('btnSubmitOrg')?.addEventListener('click', saveOrg);
        $('btnConfirmUpdate')?.addEventListener('click', submitConfirmed);
        $('btnRefreshDiagnostics')?.addEventListener('click', () => runInfrastructureDiagnostics(true));
        $('btnRefreshDomains')?.addEventListener('click', () => runInfrastructureDiagnostics(true));

        $('config_admin_password')?.addEventListener('focus', function () {
            clearStoredPasswordField(this);
        });

        document.querySelectorAll('[data-toggle-pw]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const input = $(btn.dataset.togglePw);
                if (!input || input.disabled) return;
                const icon = btn.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    if (icon) icon.className = 'fas fa-eye-slash';
                } else {
                    input.type = 'password';
                    if (icon) icon.className = 'fas fa-eye';
                }
            });
        });

        // Storage cancel
        const btnCancel = $('btnCancelStorage');
        const si = $('config_secure_base_path');
        const li = $('config_base_log_path');
        if (si && li && btnCancel) {
            let os = si.value;
            let ol = li.value;
            function checkStorageDirty() {
                const dirty = si.value !== os || li.value !== ol;
                btnCancel.classList.toggle('sys-hidden', !dirty);
            }
            si.addEventListener('input', checkStorageDirty);
            li.addEventListener('input', checkStorageDirty);
            btnCancel.addEventListener('click', () => {
                si.value = os;
                li.value = ol;
                btnCancel.classList.add('sys-hidden');
            });
        }

        modalEl?.addEventListener('hidden.bs.modal', () => {
            hideInline($('modalFeedback'));
            // Reset z-index so other modals can stack properly
            modalEl.style.zIndex = '';
        });

        function renderDomainCards(domains, backend) {
            var container = $('domain_dash_container');
            if (!container) return;

            var bLabel = backend || '—';
            var bCls = bLabel === 'LDAP' ? 'sys-brand-pill-ldap' : bLabel === 'POWERSHELL' ? 'sys-brand-pill-ps' : bLabel === 'AUTO' ? 'sys-brand-pill-auto' : 'bg-secondary';

            if (!domains || domains.length === 0) {
                container.innerHTML = '<div class="sys-domain-bar"><span class="sys-dbar-label"><i class="fas fa-sitemap me-1"></i>Backends</span><span class="badge rounded-pill ' + bCls + ' sys-badge-sm">' + bLabel + '</span></div><div class="sys-dcard sys-dcard-empty"><div class="sys-dcard-body"><span class="sys-dcard-key">No backends configured</span></div></div>';
                return;
            }

            var html = '<div class="sys-domain-bar"><span class="sys-dbar-label"><i class="fas fa-sitemap me-1"></i>Backends</span><span class="badge rounded-pill ' + bCls + ' sys-badge-sm">' + bLabel + '</span></div><div class="sys-domain-dash">';
            for (var i = 0; i < domains.length; i++) {
                var d = domains[i];
                var reachOk = d.reachable === true;
                var bindOk = d.bind_success === true;
                var isActive = d.is_active;

                var reachCls = d.reachable === null ? 'neutral' : reachOk ? 'ok' : 'crit';
                var reachText = d.reachable === null ? 'PENDING' : reachOk ? 'REACHABLE' : 'UNREACHABLE';
                var bindCls = d.bind_success === null ? 'neutral' : bindOk ? 'ok' : 'crit';
                var bindText = d.bind_success === null ? 'PENDING' : bindOk ? 'BIND OK' : 'BIND FAIL';

                var latMs = parseInt(d.latency_ms, 10) || 0;
                var hasErr = d.bind_success === false || d.reachable === false;
                var pingCls = !d.latency_ms && d.latency_ms !== 0 ? 'neutral' : hasErr ? 'crit' : latMs < 50 ? 'ok' : latMs < 200 ? 'warn' : 'crit';
                var pingText = d.latency_ms !== undefined && d.latency_ms !== null ? latMs + 'ms' : '—';

                var su = d.service_user;
                var svcHtml = '';
                if (su && !su.error) {
                    var enabled = su.accountStatus === 'Enabled';
                    var locked = su.accountLockStatus === 'Locked';
                    var pwdOk = su.passwordStatus === 'Valid';
                    var pwdCrit = su.passwordStatus === 'Expired';
                    if (!su.accountStatus) su.accountStatus = '—';
                    if (!su.accountLockStatus) su.accountLockStatus = '—';

                    svcHtml += '<span class="sys-domain-tag ' + (enabled ? 'ok' : 'crit') + '">' + escapeHtml(su.accountStatus) + '</span>';
                    svcHtml += '<span class="sys-domain-tag ' + (locked ? 'crit' : 'ok') + '">' + escapeHtml(su.accountLockStatus) + '</span>';
                    svcHtml += '<span class="sys-domain-tag ' + (pwdOk ? 'ok' : pwdCrit ? 'crit' : 'warn') + '">' + escapeHtml(su.passwordStatus || '—') + '</span>';
                } else if (su && su.error) {
                    svcHtml = '<span class="sys-domain-tag warn">LOOKUP FAILED</span>';
                } else if (d.has_password === false) {
                    svcHtml = '<span class="sys-domain-tag neutral">NO PASSWORD</span>';
                } else if (d.reachable === false) {
                    svcHtml = '<span class="sys-domain-tag warn">UNREACHABLE</span>';
                } else if (d.reachable === null) {
                    svcHtml = '<span class="sys-domain-tag neutral">PENDING</span>';
                } else {
                    svcHtml = '<span class="sys-domain-tag neutral">PENDING</span>';
                }

                html += '<div class="sys-dcard' + (isActive ? ' sys-dcard-active' : '') + '">';
                html += '<div class="sys-dcard-head"><span class="sys-dcard-label">' + (isActive ? '<i class="fas fa-check-circle me-1"></i>' : '') + escapeHtml(d.label || d.key) + '</span>';
                if (isActive) html += '<span class="badge rounded-pill bg-success sys-badge-sm me-1">ACTIVE</span>';
                html += '<span class="sys-status-chip sys-chip-' + pingCls + ' sys-ping-chip" title="Ping / Latency">' + pingText + '</span>';
                html += '</div>';
                html += '<div class="sys-dcard-body">';
                html += '<div class="sys-dcard-row"><span class="sys-dcard-key">Host</span><code class="sys-dcard-val">' + escapeHtml(d.host || '—') + '</code><span class="sys-status-chip sys-chip-' + reachCls + ' sys-dash-val">' + reachText + '</span></div>';
                html += '<div class="sys-dcard-row"><span class="sys-dcard-key">Bind</span><span class="sys-dcard-val sys-dcard-dn">' + escapeHtml(d.bind_dn || '—') + '</span><span class="sys-status-chip sys-chip-' + bindCls + ' sys-dash-val">' + bindText + '</span></div>';
                html += '<div class="sys-dcard-row"><span class="sys-dcard-key">Service User</span><div class="sys-dcard-tags">' + svcHtml + '</div></div>';
                if (d.message) {
                    var msgCls = bindOk ? 'sys-dcard-ok' : 'sys-dcard-err';
                    var msgLabel = bindOk ? 'Status' : 'Error';
                    html += '<div class="sys-dcard-row sys-dcard-msg"><span class="sys-dcard-key">' + msgLabel + '</span><span class="sys-dcard-val ' + msgCls + '">' + escapeHtml(d.message) + '</span></div>';
                }
                html += '</div></div>';
            }
            html += '</div>';
            container.innerHTML = html;
        }

        window.SysConfigUI = { copyDeployment, loadConfig, runDiagnostics: runInfrastructureDiagnostics };

        if (window.__sysConfigData) applyConfig(window.__sysConfigData);
        document.addEventListener('sysConfigLoaded', (e) => applyConfig(e.detail));

        loadConfig();
        diagTimer = window.setInterval(() => runInfrastructureDiagnostics(false), 45000);
        form.dataset.diagTimer = String(diagTimer);
    }

        document.addEventListener('DOMContentLoaded', initSystemConfig);
        document.addEventListener('spaContentUpdated', initSystemConfig);
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            initSystemConfig();
        }

    // AD Objects tab is now handled by the inline script in system_config_view.php
})();
