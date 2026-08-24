/**
 * system_config_domains.js
 * Professional Domains Management UI
 * - Modal-based edit form
 * - Live connection testing
 * - User lookup testing
 * - Dynamic table updates with status
 */

(function() {
    'use strict';

    if (window.AccessPilotInlineDomainManager === true) {
        return;
    }

    const DomainManager = {
        modal: null,
        currentKey: null,
        baseURL: (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : window.location.origin),

        init: function() {
            var modalEl = document.getElementById('domainModal');
            if (!modalEl) return; // Only init on pages that have the modal
            this.modal = new bootstrap.Modal(modalEl);
            this.setupEventListeners();
            this.loadDomainsStatus();
        },

        setupEventListeners: function() {
            // Add Domain button
            document.getElementById('btnAddDomain')?.addEventListener('click', () => {
                this.openAddModal();
            });

            // Edit domain buttons
            document.querySelectorAll('.domain-edit-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const key = e.currentTarget.dataset.key;
                    this.openEditModal(key);
                });
            });

            // Test domain buttons
            document.querySelectorAll('.domain-test-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const key = e.currentTarget.dataset.key;
                    this.testDomainConnection(key);
                });
            });

            // Switch domain buttons
            document.querySelectorAll('.domain-switch-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const key = e.currentTarget.dataset.key;
                    this.switchDomain(key);
                });
            });

            // Delete domain buttons
            document.querySelectorAll('.domain-delete-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const key = e.currentTarget.dataset.key;
                    if (confirm(`Delete domain "${key}"?`)) {
                        this.deleteDomain(key);
                    }
                });
            });

            // Modal form handlers
            document.getElementById('btnSaveDomain')?.addEventListener('click', () => {
                this.saveDomain();
            });

            document.getElementById('btnDomainTestConnect')?.addEventListener('click', () => {
                this.testConnectionInModal();
            });

            document.getElementById('btnDomainTestUser')?.addEventListener('click', () => {
                this.testUserLookup();
            });

            // Auto-populate Base DN from host
            document.getElementById('domainFormHost')?.addEventListener('change', (e) => {
                this.autoPopulateBaseDn(e.target.value);
            });

            // Toggle password visibility
            document.querySelectorAll('[data-toggle-pw]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const fieldId = e.currentTarget.dataset.togglePw;
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.type = field.type === 'password' ? 'text' : 'password';
                        e.currentTarget.innerHTML = field.type === 'password' 
                            ? '<i class="fas fa-eye"></i>' 
                            : '<i class="fas fa-eye-slash"></i>';
                    }
                });
            });

            // Modal close handlers
            document.getElementById('domainModal')?.addEventListener('hidden.bs.modal', () => {
                this.currentKey = null;
                this.resetModal();
            });
        },

        openAddModal: function() {
            this.currentKey = null;
            this.resetModal();
            document.getElementById('domainFormKeyInput').disabled = false;
            document.getElementById('domainModalTitle').innerHTML = '<i class="fas fa-plus text-primary me-2"></i>Add New Domain';
            this.modal.show();
        },

        openEditModal: function(key) {
            this.currentKey = key;
            this.resetModal();
            document.getElementById('domainFormKeyInput').disabled = true;

            // Get domain data from table row
            const row = document.querySelector(`tr[data-key="${key}"]`);
            if (!row) {
                this.showError('Domain not found');
                return;
            }

            // Extract data from table row attributes
            const host = row.dataset.host || '';
            const port = row.dataset.port || '389';

            // Try to fetch full domain data via API
            fetch(`${this.baseURL}/api/index.php?endpoint=system_config_api&action=get_domain&key=${encodeURIComponent(key)}`, {
                method: 'GET',
                headers: {
                    'X-CSRF-Token': window._csrfToken || ''
                }
            })
                .then(r => r.json())
                .then(data => {
                    if (data.domain) {
                        const d = data.domain;
                        document.getElementById('domainFormKey').value = d.key || '';
                        document.getElementById('domainFormKeyInput').value = d.key || '';
                        document.getElementById('domainFormLabel').value = d.label || '';
                        document.getElementById('domainFormHost').value = d.host || '';
                        document.getElementById('domainFormPort').value = d.port || 389;
                        document.getElementById('domainFormUseTls').checked = !!d.use_tls;
                        document.getElementById('domainFormBaseDn').value = d.base_dn || '';
                        document.getElementById('domainFormUserSearchBase').value = d.user_search_base || '';
                        document.getElementById('domainFormBindDn').value = d.bind_dn || '';

                        var naming = d.naming || {};
                        var nm = document.getElementById('domainNamingMode');
                        if (nm) nm.value = naming.mode || 'first_non_prefix_id';
                        var ne = document.getElementById('domainNamingExcludePrefixes');
                        if (ne) ne.value = (naming.exclude_prefixes || []).join(', ');
                        var nc = document.getElementById('domainNamingCase');
                        if (nc) nc.value = naming.case || 'lowercase';
                        var ns = document.getElementById('domainNamingSeparator');
                        if (ns) ns.value = naming.separator || '';
                        var nsu = document.getElementById('domainSurnameMode');
                        if (nsu) nsu.value = naming.surname_mode || 'last_part';

                        // Show password status
                        const pwStatus = document.getElementById('domainFormPwStatus');
                        if (d.has_password) {
                            pwStatus.classList.remove('sys-hidden');
                            pwStatus.textContent = 'SET';
                            pwStatus.className = 'badge rounded-pill bg-success sys-badge-sm';
                        }

                        document.getElementById('domainModalTitle').innerHTML = `<i class="fas fa-edit text-primary me-2"></i>Edit Domain: <code>${d.key}</code>`;
                        this.modal.show();
                    } else {
                        // Fallback: use table data only
                        this.showWarning('Using table data (full domain config not available)');
                        document.getElementById('domainFormKeyInput').value = key;
                        document.getElementById('domainFormHost').value = host;
                        document.getElementById('domainFormPort').value = port;
                        document.getElementById('domainModalTitle').innerHTML = `<i class="fas fa-edit text-primary me-2"></i>Edit Domain: <code>${key}</code>`;
                        this.modal.show();
                    }
                })
                .catch(e => {
                    // Fallback: use table data
                    document.getElementById('domainFormKeyInput').value = key;
                    document.getElementById('domainFormHost').value = host;
                    document.getElementById('domainFormPort').value = port;
                    document.getElementById('domainModalTitle').innerHTML = `<i class="fas fa-edit text-primary me-2"></i>Edit Domain: <code>${key}</code>`;
                    this.modal.show();
                });
        },

        resetModal: function() {
            document.getElementById('domainFormKey').value = '';
            document.getElementById('domainFormKeyInput').value = '';
            document.getElementById('domainFormLabel').value = '';
            document.getElementById('domainFormHost').value = '';
            document.getElementById('domainFormPort').value = 389;
            document.getElementById('domainFormUseTls').checked = false;
            document.getElementById('domainFormBaseDn').value = '';
            document.getElementById('domainFormUserSearchBase').value = '';
            document.getElementById('domainFormBindDn').value = '';
            document.getElementById('domainFormBindPassword').value = '';
            var nm = document.getElementById('domainNamingMode');
            if (nm) nm.value = 'first_non_prefix_id';
            var ne = document.getElementById('domainNamingExcludePrefixes');
            if (ne) ne.value = 'md., md, mr., mrs., dr., prof.';
            var nc = document.getElementById('domainNamingCase');
            if (nc) nc.value = 'lowercase';
            var ns = document.getElementById('domainNamingSeparator');
            if (ns) ns.value = '';
            var nsu = document.getElementById('domainSurnameMode');
            if (nsu) nsu.value = 'last_part';
            document.getElementById('domainFormFeedback').classList.add('sys-hidden');
            document.getElementById('domainTestConnResult').classList.add('sys-hidden');
            document.getElementById('domainTestUserResult').classList.add('sys-hidden');
            document.getElementById('domainTestUsername').value = '';
        },

        autoPopulateBaseDn: function(host) {
            const baseDnField = document.getElementById('domainFormBaseDn');
            if (!host || baseDnField.value) return;

            // Extract domain from host (e.g., "dc01.example.com" -> "example.com" -> "DC=example,DC=com")
            const parts = host.split('.');
            if (parts.length >= 2) {
                const domainParts = parts.slice(-2);
                const baseDn = domainParts.map(p => `DC=${p}`).join(',');
                baseDnField.value = baseDn;
            }
        },

        testConnectionInModal: function() {
            const host = document.getElementById('domainFormHost').value.trim();
            const port = parseInt(document.getElementById('domainFormPort').value) || 389;
            const useTls = document.getElementById('domainFormUseTls').checked;
            const baseDn = document.getElementById('domainFormBaseDn').value.trim();
            const bindDn = document.getElementById('domainFormBindDn').value.trim();
            const bindPassword = document.getElementById('domainFormBindPassword').value;

            if (!host || !baseDn || !bindDn) {
                this.showError('Host, Base DN, and Bind DN are required');
                return;
            }

            const resultDiv = document.getElementById('domainTestConnResult');
            resultDiv.classList.remove('sys-hidden');
            document.getElementById('domainTestConnStatus').textContent = 'Testing...';
            document.getElementById('domainTestConnIcon').innerHTML = '<i class="fas fa-spinner fa-spin text-muted"></i>';
            document.getElementById('domainTestConnMsg').textContent = 'Connecting to LDAP server...';

            // Build URL with parameters
            const params = new URLSearchParams({
                endpoint: 'system_config_api',
                action: 'ldap_test_connect',
                ldap_host: host,
                ldap_port: port,
                ldap_use_tls: useTls ? '1' : '0',
                ldap_base_dn: baseDn,
                ldap_bind_dn: bindDn,
                ldap_bind_password: bindPassword
            });

            fetch(`${this.baseURL}/api/index.php?${params}`, {
                method: 'GET',
                headers: {
                    'X-CSRF-Token': window._csrfToken || ''
                }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.ldap?.success) {
                    const ldap = data.ldap;
                    document.getElementById('domainTestConnIcon').innerHTML = '<i class="fas fa-check-circle text-success"></i>';
                    document.getElementById('domainTestConnStatus').textContent = '✅ Connected';
                    document.getElementById('domainTestConnMsg').textContent = ldap.message || 'Connection successful';
                    
                    let details = `<div class="row g-2">
                        <div class="col-6"><strong>Host:</strong> ${host}</div>
                        <div class="col-6"><strong>Port:</strong> ${port}</div>
                        <div class="col-6"><strong>Latency:</strong> ${ldap.latency_ms}ms</div>
                        <div class="col-6"><strong>TLS:</strong> ${useTls ? 'Enabled' : 'Disabled'}</div>`;
                    if (ldap.server_naming_context) {
                        details += `<div class="col-12"><strong>Naming Context:</strong> <code>${ldap.server_naming_context}</code></div>`;
                    }
                    details += `</div>`;
                    document.getElementById('domainTestConnDetails').innerHTML = details;
                } else {
                    document.getElementById('domainTestConnIcon').innerHTML = '<i class="fas fa-exclamation-circle text-danger"></i>';
                    document.getElementById('domainTestConnStatus').textContent = '❌ Failed';
                    document.getElementById('domainTestConnMsg').textContent = data.ldap?.message || 'Connection failed';
                    document.getElementById('domainTestConnDetails').innerHTML = '';
                }
            })
            .catch(e => {
                document.getElementById('domainTestConnIcon').innerHTML = '<i class="fas fa-times-circle text-danger"></i>';
                document.getElementById('domainTestConnStatus').textContent = '❌ Error';
                document.getElementById('domainTestConnMsg').textContent = e.message;
            });
        },

        testUserLookup: function() {
            const username = document.getElementById('domainTestUsername').value.trim();
            if (!username) {
                this.showError('Enter a username to test');
                return;
            }

            document.getElementById('domainTestUserResult').classList.remove('sys-hidden');
            document.getElementById('domainTestUserDetails').innerHTML = '<div class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Looking up user...</div>';

            fetch(`${this.baseURL}/api/index.php?endpoint=system_config_api&action=ldap_test_user&username=${encodeURIComponent(username)}`, {
                method: 'GET',
                headers: {
                    'X-CSRF-Token': window._csrfToken || ''
                }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.user) {
                    const user = data.user;
                    let html = '';
                    
                    // Display key user fields
                    const fields = [
                        { label: 'Account Name', key: 'sAMAccountName' },
                        { label: 'Display Name', key: 'displayName' },
                        { label: 'Email', key: 'mail' },
                        { label: 'Phone', key: 'telephoneNumber' },
                        { label: 'Title', key: 'title' },
                        { label: 'Department', key: 'department' },
                        { label: 'Manager', key: 'manager' },
                        { label: 'Status', key: 'enabled' }
                    ];

                    fields.forEach(f => {
                        const value = user[f.key];
                        if (value !== undefined && value !== null && value !== '') {
                            const displayVal = f.key === 'enabled' 
                                ? (value ? '✅ Enabled' : '❌ Disabled')
                                : value;
                            html += `<div class="col-6"><small><strong>${f.label}:</strong></small><br><small class="text-muted">${displayVal}</small></div>`;
                        }
                    });

                    document.getElementById('domainTestUserDetails').innerHTML = html || '<div class="text-muted">User found but no details available</div>';
                } else {
                    document.getElementById('domainTestUserDetails').innerHTML = `<div class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>${data.message || 'User not found'}</div>`;
                }
            })
            .catch(e => {
                document.getElementById('domainTestUserDetails').innerHTML = `<div class="text-danger"><i class="fas fa-times-circle me-1"></i>Error: ${e.message}</div>`;
            });
        },

        saveDomain: function() {
            const key = document.getElementById('domainFormKeyInput').value.trim();
            const label = document.getElementById('domainFormLabel').value.trim();
            const host = document.getElementById('domainFormHost').value.trim();
            const port = parseInt(document.getElementById('domainFormPort').value) || 389;
            const useTls = document.getElementById('domainFormUseTls').checked;
            const baseDn = document.getElementById('domainFormBaseDn').value.trim();
            const userSearchBase = document.getElementById('domainFormUserSearchBase').value.trim();
            const bindDn = document.getElementById('domainFormBindDn').value.trim();
            const bindPassword = document.getElementById('domainFormBindPassword').value;

            const namingMode = document.getElementById('domainNamingMode');
            const namingExclude = document.getElementById('domainNamingExcludePrefixes');
            const namingCase = document.getElementById('domainNamingCase');
            const namingSep = document.getElementById('domainNamingSeparator');
            const namingSurnameMode = document.getElementById('domainSurnameMode');

            const mode = namingMode ? namingMode.value : 'first_non_prefix_id';
            const caseVal = namingCase ? namingCase.value : 'lowercase';
            const sep = namingSep ? namingSep.value.trim() : '';
            const surnameMode = namingSurnameMode ? namingSurnameMode.value : 'last_part';
            const rawExclude = namingExclude ? namingExclude.value.trim() : '';
            const excludePrefixes = rawExclude
                ? rawExclude.split(',').map(function(s) { return s.trim(); }).filter(Boolean)
                : ['md.', 'md', 'mr.', 'mrs.', 'dr.', 'prof.'];

            if (!key || !host || !baseDn || !bindDn) {
                this.showError('Key, Host, Base DN, and Bind DN are required');
                return;
            }

            const backendInput = document.getElementById('ldapBackendModeInput');
            const payload = {
                key: key,
                label: label,
                host: host,
                port: port,
                use_tls: useTls,
                base_dn: baseDn,
                user_search_base: userSearchBase,
                bind_dn: bindDn,
                backend: (backendInput || {}).value || 'ldap',
                naming: {
                    mode: mode,
                    exclude_prefixes: excludePrefixes,
                    case: caseVal,
                    separator: sep,
                    surname_mode: surnameMode,
                }
            };

            if (bindPassword) {
                payload.bind_password = bindPassword;
            }

            const feedback = document.getElementById('domainFormFeedback');
            feedback.classList.add('sys-hidden');

            fetch(`${this.baseURL}/api/index.php?endpoint=system_config_api&action=save_domain`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window._csrfToken || ''
                },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.showSuccess('Domain saved successfully');
                    this.modal.hide();
                    setTimeout(() => location.reload(), 800);
                } else {
                    this.showError(data.message || 'Failed to save domain');
                }
            })
            .catch(e => {
                this.showError('Error saving domain: ' + e.message);
            });
        },

        testDomainConnection: function(key) {
            const row = document.querySelector(`tr[data-key="${key}"]`);
            const badge = row?.querySelector('.domain-conn-badge');
            const latency = row?.querySelector('.domain-latency');

            if (badge) badge.textContent = 'Testing...';
            if (latency) latency.textContent = '—';

            fetch(`${this.baseURL}/api/index.php?endpoint=system_config_api&action=ldap_test_connect`, {
                method: 'GET',
                headers: {
                    'X-CSRF-Token': window._csrfToken || ''
                }
            })
                .then(r => r.json())
                .then(data => {
                    if (data.ldap?.success) {
                        if (badge) {
                            badge.textContent = '✅ Online';
                            badge.className = 'domain-conn-badge badge rounded-pill bg-success sys-badge-sm';
                        }
                        if (latency) latency.textContent = `${data.ldap.latency_ms}ms`;
                    } else {
                        if (badge) {
                            badge.textContent = '❌ Failed';
                            badge.className = 'domain-conn-badge badge rounded-pill bg-danger sys-badge-sm';
                        }
                    }
                })
                .catch(() => {
                    if (badge) {
                        badge.textContent = '⚠️ Error';
                        badge.className = 'domain-conn-badge badge rounded-pill bg-warning sys-badge-sm';
                    }
                });
        },

        switchDomain: function(key) {
            // TODO: Implement domain switching via API
            alert('Domain switching not yet implemented');
        },

        deleteDomain: function(key) {
            // TODO: Implement domain deletion via API
            alert('Domain deletion not yet implemented');
        },

        loadDomainsStatus: function() {
            // Load status for all domains
            document.querySelectorAll('.domain-row').forEach(row => {
                const key = row.dataset.key;
                this.testDomainConnection(key);
            });
        },

        showError: function(msg) {
            const feedback = document.getElementById('domainFormFeedback');
            feedback.innerHTML = `<div class="alert alert-danger mb-0"><i class="fas fa-exclamation-circle me-1"></i>${msg}</div>`;
            feedback.classList.remove('sys-hidden');
        },

        showSuccess: function(msg) {
            const feedback = document.getElementById('domainFormFeedback');
            feedback.innerHTML = `<div class="alert alert-success mb-0"><i class="fas fa-check-circle me-1"></i>${msg}</div>`;
            feedback.classList.remove('sys-hidden');
        },

        showWarning: function(msg) {
            const feedback = document.getElementById('domainFormFeedback');
            feedback.innerHTML = `<div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle me-1"></i>${msg}</div>`;
            feedback.classList.remove('sys-hidden');
        }
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => DomainManager.init());
    } else {
        DomainManager.init();
    }

    // Expose globally
    window.DomainManager = DomainManager;
})();
