(function() {
    'use strict';

    function init() {
        bindTabs();
        bindDnsLookup();
        bindHeaderParse();
        bindBlacklistCheck();
        bindEmailValidate();
        bindSmtpTest();
        bindPortScan();
        bindBimiCheck();
        bindMtaStsCheck();
    }

    function bindTabs() {
        var tabs = document.querySelectorAll('#emailTabs .noc-tab-item');
        var panes = document.querySelectorAll('.noc-tab-content .tab-pane');
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                var target = this.dataset.tab;
                tabs.forEach(function(t) { t.classList.remove('active'); });
                this.classList.add('active');
                panes.forEach(function(p) {
                    p.style.display = p.id === 'tab-' + target ? '' : 'none';
                });
            });
        });
    }

    function getCsrfToken() {
        return (window.APP_CONFIG && window.APP_CONFIG.csrfToken) || '';
    }

    function htmlspecialchars(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    function statusBadge(result) {
        var map = {
            'pass': 'status-success',
            'fail': 'status-failed',
            'softfail': 'status-warning',
            'neutral': 'status-info',
            'none': 'status-info',
            'temperror': 'status-warning',
            'permerror': 'status-failed',
        };
        var cls = map[result.toLowerCase()] || 'status-info';
        return '<span class="status-badge ' + cls + '">' + result.toUpperCase() + '</span>';
    }

    // ===================== DNS Lookup =====================
    function bindDnsLookup() {
        var go = document.getElementById('dnsLookupGo');
        var input = document.getElementById('dnsDomainInput');
        var handler = function() {
            var domain = input ? input.value.trim() : '';
            if (domain) doDnsLookup(domain);
        };
        if (go) go.addEventListener('click', handler);
        if (input) input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') handler();
        });
    }

    function doDnsLookup(domain) {
        var resultsDiv = document.getElementById('dnsResults');
        if (resultsDiv) resultsDiv.style.display = 'block';

        ['dnsMxResults', 'dnsSpfResults', 'dnsDkimResults', 'dnsDmarcResults'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.innerHTML = '<div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        });

        fetch('/api/index.php?endpoint=email_tools', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'dns_lookup', domain: domain })
        })
        .then(function(r) {
            return r.text().then(function(text) {
                try { return JSON.parse(text); }
                catch(e) { throw new Error('Invalid JSON response (' + r.status + '): ' + text.slice(0, 200)); }
            });
        })
        .then(function(data) {
            if (data.success) {
                renderDnsMx(data);
                renderDnsSpf(data);
                renderDnsDkim(data);
                renderDnsDmarc(data);
                renderDnsBimi(data);
                renderDnsMtaSts(data);
            } else {
                ['dnsMxResults', 'dnsSpfResults', 'dnsDkimResults', 'dnsDmarcResults'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.innerHTML = '<div class="alert alert-danger mb-0">' + htmlspecialchars(data.message) + '</div>';
                });
            }
        })
        .catch(function(err) {
            ['dnsMxResults', 'dnsSpfResults', 'dnsDkimResults', 'dnsDmarcResults'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + htmlspecialchars(err.message) + '</div>';
            });
        });
    }

    function renderDnsMx(data) {
        var el = document.getElementById('dnsMxResults');
        if (!el) return;
        if (!data.mx || data.mx.length === 0) {
            el.innerHTML = '<div class="alert alert-warning mb-0">No MX records found.</div>';
            return;
        }
        var html = '<div class="table-responsive"><table class="app-data-table" style="width:100%;">';
        html += '<thead><tr><th>Preference</th><th>Mail Server</th><th>IP Addresses</th></tr></thead><tbody>';
        data.mx.forEach(function(m) {
            var ips = '';
            if (data.mx_ips && data.mx_ips[m.host]) {
                ips = data.mx_ips[m.host].join(', ');
            }
            html += '<tr><td>' + m.preference + '</td><td>' + htmlspecialchars(m.host) + '</td><td style="font-size:var(--font-xs);">' + htmlspecialchars(ips) + '</td></tr>';
        });
        html += '</tbody></table></div>';
        html += '<p class="text-muted mt-2 mb-0" style="font-size:var(--font-xs);">Found ' + data.mx_count + ' MX record(s) in ' + data.duration + 'ms</p>';
        el.innerHTML = html;
    }

    function renderDnsSpf(data) {
        var el = document.getElementById('dnsSpfResults');
        if (!el) return;
        if (!data.spf || !data.spf.records || data.spf.records.length === 0) {
            el.innerHTML = '<div class="alert alert-warning mb-0">No SPF record found.</div>';
            return;
        }
        var spf = data.spf.records[0];
        var parsed = data.spf.parsed;
        var html = '<div><code style="word-break:break-all;font-size:var(--font-xs);">' + htmlspecialchars(spf) + '</code></div>';
        if (parsed && parsed.mechanisms) {
            html += '<div class="mt-2" style="font-size:var(--font-xs);"><strong>Mechanisms:</strong><ul class="mb-0">';
            parsed.mechanisms.forEach(function(m) {
                html += '<li><code>' + htmlspecialchars(m) + '</code></li>';
            });
            html += '</ul></div>';
            if (parsed.all) {
                var allLabel = parsed.all;
                var allColor = 'status-info';
                if (allLabel === '-all') allColor = 'status-failed';
                else if (allLabel === '~all') allColor = 'status-warning';
                else if (allLabel === '+all') allColor = 'status-failed';
                html += '<div class="mt-1"><span class="status-badge ' + allColor + '">' + htmlspecialchars(allLabel) + '</span></div>';
            }
        }
        el.innerHTML = html;
    }

    function renderDnsDkim(data) {
        var el = document.getElementById('dnsDkimResults');
        if (!el) return;
        if (!data.dkim || !data.dkim.records || data.dkim.records.length === 0) {
            el.innerHTML = '<div class="alert alert-warning mb-0">No DKIM records found (checked selectors: google, default, selector1, selector2).</div>';
            return;
        }
        var html = '';
        data.dkim.records.forEach(function(dk) {
            html += '<div class="mb-2"><strong>Selector:</strong> ' + htmlspecialchars(dk.selector) + '<br>';
            html += '<code style="word-break:break-all;font-size:var(--font-xs);">' + htmlspecialchars(dk.record) + '</code></div>';
        });
        el.innerHTML = html;
    }

    function renderDnsDmarc(data) {
        var el = document.getElementById('dnsDmarcResults');
        if (!el) return;
        if (!data.dmarc || !data.dmarc.records || data.dmarc.records.length === 0) {
            el.innerHTML = '<div class="alert alert-warning mb-0">No DMARC record found.</div>';
            return;
        }
        var html = '<div><code style="word-break:break-all;font-size:var(--font-xs);">' + htmlspecialchars(data.dmarc.records[0]) + '</code></div>';
        el.innerHTML = html;
    }

    function renderDnsBimi(data) {
        var el = document.getElementById('dnsBimiResults');
        if (!el) return;
        if (!data.bimi || !data.bimi.has_bimi) {
            el.innerHTML = '<div class="alert alert-warning mb-0">No BIMI record found.</div>';
            return;
        }
        var h = '<div style="font-size:var(--font-sm);">';
        data.bimi.records.forEach(function(r) {
            h += '<div style="font-family:monospace;font-size:var(--font-xs);background:var(--input-bg, rgba(0,0,0,0.1));padding:8px;border-radius:6px;word-break:break-all;margin-bottom:4px;">' + htmlspecialchars(r) + '</div>';
        });
        if (data.bimi.logo_url) {
            h += '<p class="mt-2"><strong>Logo URL:</strong> <a href="' + htmlspecialchars(data.bimi.logo_url) + '" target="_blank">' + htmlspecialchars(data.bimi.logo_url) + '</a></p>';
        }
        h += '</div>';
        el.innerHTML = h;
    }

    function renderDnsMtaSts(data) {
        var el = document.getElementById('dnsMtaStsResults');
        if (!el) return;
        if (!data.mta_sts || !data.mta_sts.has_sts) {
            el.innerHTML = '<div class="alert alert-warning mb-0">No MTA-STS record found.</div>';
            return;
        }
        var h = '<div style="font-size:var(--font-sm);">';
        data.mta_sts.dns_records.forEach(function(r) {
            h += '<div style="font-family:monospace;font-size:var(--font-xs);background:var(--input-bg, rgba(0,0,0,0.1));padding:8px;border-radius:6px;word-break:break-all;margin-bottom:4px;">' + htmlspecialchars(r) + '</div>';
        });
        if (data.mta_sts.policy_error) {
            h += '<p class="mt-1"><span class="status-badge status-failed">' + htmlspecialchars(data.mta_sts.policy_error) + '</span></p>';
        } else if (data.mta_sts.policy_content) {
            h += '<p class="mt-1"><span class="status-badge status-success">Policy available</span>';
            if (data.mta_sts.mode) h += ' Mode: <strong>' + htmlspecialchars(data.mta_sts.mode) + '</strong>';
            h += '</p>';
        }
        h += '</div>';
        el.innerHTML = h;
    }

    // ===================== Header Parse =====================
    function bindHeaderParse() {
        var go = document.getElementById('headerParseGo');
        var clear = document.getElementById('headerClearBtn');
        var textarea = document.getElementById('headerRawInput');
        if (go && textarea) {
            go.addEventListener('click', function() {
                var raw = textarea.value.trim();
                if (raw) doHeaderParse(raw);
            });
        }
        if (clear && textarea) {
            clear.addEventListener('click', function() {
                textarea.value = '';
                var resultsDiv = document.getElementById('headerResults');
                if (resultsDiv) resultsDiv.style.display = 'none';
            });
        }
    }

    function doHeaderParse(rawHeaders) {
        var resultsDiv = document.getElementById('headerResults');
        if (resultsDiv) resultsDiv.style.display = 'block';

        ['headerEnvelopeResults', 'headerAuthResults', 'headerReceivedResults'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.innerHTML = '<div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i> Parsing...</div>';
        });

        fetch('/api/index.php?endpoint=email_tools', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'header_parse', raw_headers: rawHeaders })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.analysis) {
                renderHeaderEnvelope(data.analysis);
                renderHeaderAuth(data.analysis);
                renderHeaderReceived(data.analysis);
            } else {
                ['headerEnvelopeResults', 'headerAuthResults', 'headerReceivedResults'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.innerHTML = '<div class="alert alert-danger mb-0">' + htmlspecialchars(data.message) + '</div>';
                });
            }
        })
        .catch(function(err) {
            ['headerEnvelopeResults', 'headerAuthResults', 'headerReceivedResults'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + htmlspecialchars(err.message) + '</div>';
            });
        });
    }

    function renderHeaderEnvelope(analysis) {
        var el = document.getElementById('headerEnvelopeResults');
        if (!el) return;
        var env = analysis.envelope || {};
        var html = '<div class="row g-2" style="font-size:var(--font-sm);">';
        var fields = [
            { label: 'From', value: env.from },
            { label: 'To', value: env.to },
            { label: 'Subject', value: env.subject },
            { label: 'Date', value: env.date },
            { label: 'Message-ID', value: env.message_id },
            { label: 'Reply-To', value: env.reply_to },
            { label: 'Return-Path', value: env.return_path },
            { label: 'Content-Type', value: env.content_type },
        ];
        fields.forEach(function(f) {
            if (f.value) {
                html += '<div class="col-12"><strong>' + f.label + ':</strong> ' + htmlspecialchars(f.value) + '</div>';
            }
        });
        html += '</div>';
        el.innerHTML = html;
    }

    function renderHeaderAuth(analysis) {
        var el = document.getElementById('headerAuthResults');
        if (!el) return;
        var html = '<div class="d-flex gap-3 flex-wrap align-items-center">';
        if (analysis.spf) html += '<div><strong>SPF:</strong> ' + statusBadge(analysis.spf) + '</div>';
        if (analysis.dkim) html += '<div><strong>DKIM:</strong> ' + statusBadge(analysis.dkim) + '</div>';
        if (analysis.dmarc) html += '<div><strong>DMARC:</strong> ' + statusBadge(analysis.dmarc) + '</div>';
        if (analysis.spoof_score != null) {
            var scoreColor = analysis.spoof_score > 50 ? 'var(--danger)' : (analysis.spoof_score > 20 ? 'var(--warning, #ffc107)' : 'var(--success, #10b981)');
            html += '<div><strong>Spoof Score:</strong> <span style="color:' + scoreColor + ';font-weight:bold;">' + analysis.spoof_score + '/100</span></div>';
        }
        html += '</div>';
        if (analysis.auth_results && analysis.auth_results.length > 0) {
            html += '<div class="mt-2" style="font-size:var(--font-xs);">';
            analysis.auth_results.forEach(function(ar) {
                html += '<div class="mt-1"><code style="word-break:break-all;">' + htmlspecialchars(ar.raw) + '</code></div>';
            });
            html += '</div>';
        }
        el.innerHTML = html;
    }

    function renderHeaderReceived(analysis) {
        var el = document.getElementById('headerReceivedResults');
        if (!el) return;
        var chain = analysis.received_chain || {};
        var hops = chain.hops || [];
        if (hops.length === 0) {
            el.innerHTML = '<p class="text-muted mb-0">No Received headers found.</p>';
            return;
        }
        var html = '<div style="font-size:var(--font-xs);">';
        html += '<p><strong>Hops:</strong> ' + hops.length + '</p>';
        html += '<div class="table-responsive"><table class="app-data-table" style="width:100%;">';
        html += '<thead><tr><th>#</th><th>From</th><th>By</th><th>With</th><th>For</th></tr></thead><tbody>';
        hops.forEach(function(h, i) {
            html += '<tr>';
            html += '<td>' + (i + 1) + '</td>';
            html += '<td>' + htmlspecialchars(h.from || '-') + '</td>';
            html += '<td>' + htmlspecialchars(h.by || '-') + '</td>';
            html += '<td>' + htmlspecialchars(h['with'] || '-') + '</td>';
            html += '<td>' + htmlspecialchars(h['for'] || '-') + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        html += '</div>';
        el.innerHTML = html;
    }

    // ===================== Blacklist Check =====================
    function bindBlacklistCheck() {
        var go = document.getElementById('blacklistCheckGo');
        var input = document.getElementById('blacklistTargetInput');
        var handler = function() {
            var target = input ? input.value.trim() : '';
            if (target) doBlacklistCheck(target);
        };
        if (go) go.addEventListener('click', handler);
        if (input) input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') handler();
        });
    }

    function doBlacklistCheck(target) {
        var resultsDiv = document.getElementById('blacklistResults');
        var tableEl = document.getElementById('blacklistResultsTable');
        var summaryEl = document.getElementById('blSummary');
        if (resultsDiv) resultsDiv.style.display = 'block';
        if (tableEl) tableEl.innerHTML = '<div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i> Checking ' + htmlspecialchars(target) + ' against blacklists...</div>';

        fetch('/api/index.php?endpoint=email_tools', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'blacklist_check', target: target })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (tableEl && summaryEl) {
                if (data.success) {
                    summaryEl.textContent = data.listed + '/' + data.total;
                    summaryEl.className = 'badge ' + (data.listed > 0 ? 'bg-danger' : 'bg-success') + ' ms-2';
                    renderBlacklistTable(data, tableEl);
                } else {
                    tableEl.innerHTML = '<div class="alert alert-danger mb-0">' + htmlspecialchars(data.message) + '</div>';
                }
            }
        })
        .catch(function(err) {
            if (tableEl) tableEl.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + htmlspecialchars(err.message) + '</div>';
        });
    }

    function renderBlacklistTable(data, tableEl) {
        if (!data.results || data.results.length === 0) {
            tableEl.innerHTML = '<p class="text-muted mb-0">No blacklist results.</p>';
            return;
        }
        if (data.listed === 0) {
            tableEl.innerHTML = '<div class="alert alert-success mb-3"><i class="fas fa-check-circle me-1"></i>Not listed on any of ' + data.total + ' blacklists.</div>';
        } else {
            tableEl.innerHTML = '<div class="alert alert-danger mb-3"><i class="fas fa-exclamation-triangle me-1"></i>Listed on ' + data.listed + ' of ' + data.total + ' blacklists.</div>';
        }
        var html = '<div class="table-responsive" style="max-height:400px;overflow-y:auto;"><table class="app-data-table" style="width:100%;font-size:var(--font-xs);">';
        html += '<thead><tr><th>Blacklist</th><th>Status</th><th>Response</th><th>Latency</th></tr></thead><tbody>';
        data.results.forEach(function(r) {
            var statusHtml = r.listed
                ? '<span class="status-badge status-failed">LISTED</span>'
                : '<span class="status-badge status-success">CLEAR</span>';
            html += '<tr>';
            html += '<td>' + htmlspecialchars(r.blacklist) + '</td>';
            html += '<td>' + statusHtml + '</td>';
            html += '<td>' + htmlspecialchars(r.response || '-') + '</td>';
            html += '<td>' + r.latency + 'ms</td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        tableEl.innerHTML = html;
    }

    // ===================== Email Validate =====================
    function bindEmailValidate() {
        var go = document.getElementById('emailValidateGo');
        var input = document.getElementById('emailValidateInput');
        var handler = function() {
            var email = input ? input.value.trim() : '';
            if (email) doEmailValidate(email);
        };
        if (go) go.addEventListener('click', handler);
        if (input) input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') handler();
        });
    }

    function doEmailValidate(email) {
        var resultsDiv = document.getElementById('emailValidateResults');
        var checksEl = document.getElementById('emailValidateChecks');
        var scoreEl = document.getElementById('emailValScore');
        if (resultsDiv) resultsDiv.style.display = 'block';
        if (checksEl) checksEl.innerHTML = '<div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i> Validating...</div>';

        fetch('/api/index.php?endpoint=email_tools', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'email_validate', email: email })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (checksEl && scoreEl) {
                if (data.success) {
                    scoreEl.textContent = data.score + '/100';
                    scoreEl.className = 'badge ' + (data.score >= 70 ? 'bg-success' : (data.score >= 40 ? 'bg-warning text-dark' : 'bg-danger')) + ' ms-2';
                    renderEmailChecks(data, checksEl);
                } else {
                    checksEl.innerHTML = '<div class="alert alert-danger mb-0">' + htmlspecialchars(data.message) + '</div>';
                }
            }
        })
        .catch(function(err) {
            if (checksEl) checksEl.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + htmlspecialchars(err.message) + '</div>';
        });
    }

    function renderEmailChecks(data, checksEl) {
        if (!data.checks || data.checks.length === 0) {
            checksEl.innerHTML = '<p class="text-muted mb-0">No checks performed.</p>';
            return;
        }
        var html = '<div class="list-group" style="font-size:var(--font-sm);">';
        data.checks.forEach(function(c) {
            var icon = c.passed ? '<i class="fas fa-check-circle" style="color:var(--success, #10b981);"></i>' : '<i class="fas fa-times-circle" style="color:var(--danger, #ef4444);"></i>';
            html += '<div class="list-group-item d-flex align-items-start gap-2" style="background:transparent;border:1px solid var(--border-color);">';
            html += '<div class="mt-1">' + icon + '</div>';
            html += '<div><strong>' + htmlspecialchars(c.check) + '</strong><br><span class="text-muted">' + htmlspecialchars(c.message) + '</span></div>';
            html += '</div>';
        });
        html += '</div>';
        if (data.smtp_result) {
            html += '<div class="mt-2 p-2" style="background:var(--input-bg, rgba(255,255,255,0.05));border-radius:6px;font-size:var(--font-xs);">';
            html += '<strong>SMTP Details:</strong> Server: ' + htmlspecialchars(data.smtp_result.mx_host || '-') + ' | Latency: ' + (data.smtp_result.latency || '-') + 'ms';
            if (data.smtp_result.banner) html += '<br>Banner: ' + htmlspecialchars(data.smtp_result.banner);
            if (data.smtp_result.response) html += '<br>Response: ' + htmlspecialchars(data.smtp_result.response);
            html += '</div>';
        }
        html += '<p class="text-muted mt-2 mb-0" style="font-size:var(--font-xs);">Completed in ' + data.duration + 'ms</p>';
        checksEl.innerHTML = html;
    }

    // ===================== SMTP Test =====================
    function bindSmtpTest() {
        var go = document.getElementById('smtpTestGo');
        var input = document.getElementById('smtpHostInput');
        var handler = function() {
            var host = input ? input.value.trim() : '';
            var port = document.getElementById('smtpPortInput') ? document.getElementById('smtpPortInput').value : 25;
            if (host) doSmtpTest(host, port);
        };
        if (go) go.addEventListener('click', handler);
        if (input) input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') handler();
        });
    }

    function doSmtpTest(host, port) {
        var resultsDiv = document.getElementById('smtpTestResults');
        if (resultsDiv) resultsDiv.style.display = 'block';
        var infoEl = document.getElementById('smtpServerInfo');
        var ehloEl = document.getElementById('smtpEhloInfo');
        if (infoEl) infoEl.innerHTML = '<div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i> Connecting...</div>';
        if (ehloEl) ehloEl.innerHTML = '';

        fetch('/api/index.php?endpoint=email_tools', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'smtp_test', host: host, port: parseInt(port) || 25 })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var icon = data.reachable ? '<i class="fas fa-check-circle" style="color:var(--success, #10b981);"></i>' : '<i class="fas fa-times-circle" style="color:var(--danger, #ef4444);"></i>';
                if (infoEl) {
                    var html = '<div style="font-size:var(--font-sm);">';
                    html += '<p>' + icon + ' <strong>' + htmlspecialchars(host) + ':' + data.port + '</strong>';
                    html += data.reachable ? ' <span class="status-badge status-success">OPEN</span>' : ' <span class="status-badge status-failed">CLOSED</span>';
                    html += ' (' + data.latency + 'ms)</p>';
                    if (data.reachable) {
                        if (data.banner) html += '<p><strong>Banner:</strong> ' + htmlspecialchars(data.banner) + '</p>';
                        html += '<p><strong>STARTTLS:</strong> ' + (data.supports_starttls ? '<span class="status-badge status-success">Supported</span>' : '<span class="status-badge status-info">Not advertised</span>') + '</p>';
                    } else {
                        html += '<p><strong>Error:</strong> ' + htmlspecialchars(data.error || 'Unknown') + '</p>';
                    }
                    html += '</div>';
                    infoEl.innerHTML = html;
                }
                if (ehloEl && data.ehlo && data.ehlo.length) {
                    var h = '<div style="font-family:monospace;font-size:var(--font-xs);background:var(--input-bg, rgba(0,0,0,0.1));padding:8px;border-radius:6px;white-space:pre-wrap;">';
                    data.ehlo.forEach(function(l) { h += htmlspecialchars(l) + '\n'; });
                    h += '</div>';
                    ehloEl.innerHTML = h;
                }
            } else {
                if (infoEl) infoEl.innerHTML = '<div class="alert alert-danger mb-0">' + htmlspecialchars(data.message) + '</div>';
            }
        })
        .catch(function(err) {
            if (infoEl) infoEl.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + htmlspecialchars(err.message) + '</div>';
        });
    }

    // ===================== Port Scan =====================
    function bindPortScan() {
        var go = document.getElementById('portScanGo');
        var input = document.getElementById('portScanInput');
        var handler = function() {
            var host = input ? input.value.trim() : '';
            if (host) doPortScan(host);
        };
        if (go) go.addEventListener('click', handler);
        if (input) input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') handler();
        });
    }

    function doPortScan(host) {
        var el = document.getElementById('portScanResults');
        if (el) el.innerHTML = '<div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i> Scanning ports...</div>';

        fetch('/api/index.php?endpoint=email_tools', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'port_scan', host: host })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!el) return;
            if (!data.success) {
                el.innerHTML = '<div class="alert alert-danger mb-0">' + htmlspecialchars(data.message) + '</div>';
                return;
            }
            if (!data.results || data.results.length === 0) {
                el.innerHTML = '<p class="text-muted mb-0">No results.</p>';
                return;
            }
            var openCount = data.results.filter(function(r) { return r.open; }).length;
            var html = '<div class="mb-2">Open: ' + openCount + '/' + data.results.length + '</div>';
            html += '<div class="table-responsive"><table class="app-data-table" style="width:100%;font-size:var(--font-xs);">';
            html += '<thead><tr><th>Port</th><th>Service</th><th>Status</th><th>Banner</th><th>Latency</th></tr></thead><tbody>';
            data.results.forEach(function(r) {
                var statusHtml = r.open
                    ? '<span class="status-badge status-success">OPEN</span>'
                    : '<span class="status-badge status-failed">CLOSED</span>';
                html += '<tr>';
                html += '<td>' + r.port + '</td>';
                html += '<td>' + htmlspecialchars(r.service) + '</td>';
                html += '<td>' + statusHtml + '</td>';
                html += '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + htmlspecialchars(r.banner || r.error || '-') + '</td>';
                html += '<td>' + r.latency + 'ms</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            el.innerHTML = html;
        })
        .catch(function(err) {
            if (el) el.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + htmlspecialchars(err.message) + '</div>';
        });
    }

    // ===================== BIMI Check =====================
    function bindBimiCheck() {
        var go = document.getElementById('bimiCheckGo');
        var input = document.getElementById('bimiDomainInput');
        var handler = function() {
            var domain = input ? input.value.trim() : '';
            if (domain) doBimiCheck(domain);
        };
        if (go) go.addEventListener('click', handler);
        if (input) input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') handler();
        });
    }

    function doBimiCheck(domain) {
        var resultsDiv = document.getElementById('bimiResults');
        var contentEl = document.getElementById('bimiResultContent');
        if (resultsDiv) resultsDiv.style.display = 'block';
        if (contentEl) contentEl.innerHTML = '<div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i> Checking...</div>';

        fetch('/api/index.php?endpoint=email_tools', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'bimi_check', domain: domain })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!contentEl) return;
            if (!data.success) {
                contentEl.innerHTML = '<div class="alert alert-danger mb-0">' + htmlspecialchars(data.message) + '</div>';
                return;
            }
            if (data.has_bimi) {
                var h = '<div style="font-size:var(--font-sm);">';
                h += '<p><span class="status-badge status-success">BIMI Found</span></p>';
                data.records.forEach(function(r) {
                    h += '<div style="font-family:monospace;font-size:var(--font-xs);background:var(--input-bg, rgba(0,0,0,0.1));padding:8px;border-radius:6px;word-break:break-all;margin-bottom:4px;">' + htmlspecialchars(r) + '</div>';
                });
                if (data.logo_url) {
                    h += '<p class="mt-2"><strong>Logo URL:</strong> <a href="' + htmlspecialchars(data.logo_url) + '" target="_blank">' + htmlspecialchars(data.logo_url) + '</a></p>';
                    h += '<div><img src="' + htmlspecialchars(data.logo_url) + '" style="max-width:100px;max-height:100px;border-radius:8px;" onerror="this.style.display=\'none\'"></div>';
                }
                h += '</div>';
                contentEl.innerHTML = h;
            } else {
                contentEl.innerHTML = '<div class="alert alert-warning mb-0">No BIMI record found for <strong>' + htmlspecialchars(domain) + '</strong>.</div>';
            }
        })
        .catch(function(err) {
            if (contentEl) contentEl.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + htmlspecialchars(err.message) + '</div>';
        });
    }

    // ===================== MTA-STS Check =====================
    function bindMtaStsCheck() {
        var go = document.getElementById('mtaStsCheckGo');
        var input = document.getElementById('mtaStsDomainInput');
        var handler = function() {
            var domain = input ? input.value.trim() : '';
            if (domain) doMtaStsCheck(domain);
        };
        if (go) go.addEventListener('click', handler);
        if (input) input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') handler();
        });
    }

    function doMtaStsCheck(domain) {
        var resultsDiv = document.getElementById('mtaStsResults');
        var contentEl = document.getElementById('mtaStsResultContent');
        if (resultsDiv) resultsDiv.style.display = 'block';
        if (contentEl) contentEl.innerHTML = '<div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i> Checking...</div>';

        fetch('/api/index.php?endpoint=email_tools', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'mta_sts_check', domain: domain })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!contentEl) return;
            if (!data.success) {
                contentEl.innerHTML = '<div class="alert alert-danger mb-0">' + htmlspecialchars(data.message) + '</div>';
                return;
            }
            var h = '<div style="font-size:var(--font-sm);">';
            if (data.has_sts) {
                h += '<p><span class="status-badge status-success">MTA-STS DNS Record Found</span></p>';
                data.dns_records.forEach(function(r) {
                    h += '<div style="font-family:monospace;font-size:var(--font-xs);background:var(--input-bg, rgba(0,0,0,0.1));padding:8px;border-radius:6px;word-break:break-all;margin-bottom:4px;">' + htmlspecialchars(r) + '</div>';
                });
            } else {
                h += '<p><span class="status-badge status-warning">No MTA-STS DNS Record</span></p>';
            }
            h += '<p class="mt-2"><strong>Policy URL:</strong> <a href="' + htmlspecialchars(data.policy_url) + '" target="_blank">' + htmlspecialchars(data.policy_url) + '</a></p>';
            if (data.policy_content) {
                h += '<p><span class="status-badge status-success">Policy Fetched</span>';
                if (data.mode) h += ' Mode: <strong>' + htmlspecialchars(data.mode) + '</strong>';
                h += '</p>';
                h += '<div style="font-family:monospace;font-size:var(--font-xs);background:var(--input-bg, rgba(0,0,0,0.1));padding:8px;border-radius:6px;white-space:pre-wrap;word-break:break-all;max-height:200px;overflow:auto;">';
                h += htmlspecialchars(data.policy_content);
                h += '</div>';
            } else if (data.policy_error) {
                h += '<p><span class="status-badge status-failed">' + htmlspecialchars(data.policy_error) + '</span></p>';
            }
            h += '</div>';
            contentEl.innerHTML = h;
        })
        .catch(function(err) {
            if (contentEl) contentEl.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + htmlspecialchars(err.message) + '</div>';
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
