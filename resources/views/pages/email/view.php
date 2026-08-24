<div class="page-container email-page">
    <div class="page-header d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="fw-bold m-0" style="font-size:var(--font-xxl);">
                <i class="fas fa-envelope-open-text me-2" style="color:var(--primary-color);"></i>Email Analysis Tools
            </h2>
            <p class="text-muted m-0" style="font-size:var(--font-sm);">DNS record analysis, header inspection, SPF/DKIM/DMARC validation, blacklist checks</p>
        </div>
    </div>

    <div class="noc-tabs-bar" id="emailTabs">
        <div class="noc-tab-item active" data-tab="dns"><i class="fas fa-globe me-1"></i> DNS Lookup</div>
        <div class="noc-tab-item" data-tab="header"><i class="fas fa-heading me-1"></i> Header Analysis</div>
        <div class="noc-tab-item" data-tab="blacklist"><i class="fas fa-ban me-1"></i> Blacklist</div>
        <div class="noc-tab-item" data-tab="validate"><i class="fas fa-check-circle me-1"></i> Email Validate</div>
        <div class="noc-tab-item" data-tab="smtp"><i class="fas fa-server me-1"></i> SMTP Test</div>
    </div>

    <div class="noc-tab-content">
        <!-- TAB: DNS Lookup -->
        <div class="tab-pane active" id="tab-dns">
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid var(--primary-color);">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-globe me-2"></i>DNS Record Lookup</h3>
                    <button class="app-table-btn" id="dnsLookupBtn" style="height:36px;">
                        <i class="fas fa-search me-1"></i>Lookup
                    </button>
                </div>
                <div class="p-3">
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <input type="text" class="app-table-input" id="dnsDomainInput"
                            placeholder="example.com"
                            style="flex:1;min-width:200px;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                        <button class="btn btn-primary" id="dnsLookupGo" style="height:36px;border-radius:6px;">
                            <i class="fas fa-search me-1"></i>Lookup
                        </button>
                    </div>
                </div>
            </div>

            <div id="dnsResults" style="display:none;">
                <!-- MX Records -->
                <div class="app-table-card ws-card mb-3" style="border-top:3px solid var(--primary-color);">
                    <div class="log-title-wrapper app-table-title">
                        <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-mail-bulk me-2"></i>MX Records</h3>
                    </div>
                    <div class="p-3" id="dnsMxResults">-</div>
                </div>
                <!-- SPF -->
                <div class="app-table-card ws-card mb-3" style="border-top:3px solid #0d6efd;">
                    <div class="log-title-wrapper app-table-title">
                        <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-shield-alt me-2"></i>SPF Record</h3>
                    </div>
                    <div class="p-3" id="dnsSpfResults">-</div>
                </div>
                <!-- DKIM -->
                <div class="app-table-card ws-card mb-3" style="border-top:3px solid #6f42c1;">
                    <div class="log-title-wrapper app-table-title">
                        <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-key me-2"></i>DKIM Record</h3>
                    </div>
                    <div class="p-3" id="dnsDkimResults">-</div>
                </div>
                <!-- DMARC -->
                <div class="app-table-card ws-card mb-3" style="border-top:3px solid #198754;">
                    <div class="log-title-wrapper app-table-title">
                        <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-shield me-2"></i>DMARC Record</h3>
                    </div>
                    <div class="p-3" id="dnsDmarcResults">-</div>
                </div>
                <!-- BIMI -->
                <div class="app-table-card ws-card mb-3" style="border-top:3px solid #f59e0b;">
                    <div class="log-title-wrapper app-table-title">
                        <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-image me-2"></i>BIMI Record</h3>
                    </div>
                    <div class="p-3" id="dnsBimiResults">-</div>
                </div>
                <!-- MTA-STS -->
                <div class="app-table-card ws-card mb-3" style="border-top:3px solid #8b5cf6;">
                    <div class="log-title-wrapper app-table-title">
                        <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-shield-alt me-2"></i>MTA-STS</h3>
                    </div>
                    <div class="p-3" id="dnsMtaStsResults">-</div>
                </div>
            </div>
        </div>

        <!-- TAB: Header Analysis -->
        <div class="tab-pane" id="tab-header" style="display:none;">
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid var(--primary-color);">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-heading me-2"></i>Email Header Analyzer</h3>
                    <button class="app-table-btn" id="headerParseBtn" style="height:36px;">
                        <i class="fas fa-cog me-1"></i>Parse
                    </button>
                </div>
                <div class="p-3">
                    <div class="mb-2">
                        <textarea class="app-table-input" id="headerRawInput"
                            placeholder="Paste full email headers here..."
                            style="width:100%;min-height:180px;border:1px solid var(--border-color);border-radius:6px;padding:12px;resize:vertical;font-family:monospace;font-size:var(--font-xs);"></textarea>
                    </div>
                    <div>
                        <button class="btn btn-primary" id="headerParseGo" style="height:36px;border-radius:6px;">
                            <i class="fas fa-cog me-1"></i>Parse Headers
                        </button>
                        <button class="btn btn-outline-secondary" id="headerClearBtn" style="height:36px;border-radius:6px;">
                            <i class="fas fa-times me-1"></i>Clear
                        </button>
                    </div>
                </div>
            </div>

            <div id="headerResults" style="display:none;">
                <!-- Envelope -->
                <div class="app-table-card ws-card mb-3" style="border-top:3px solid var(--primary-color);">
                    <div class="log-title-wrapper app-table-title">
                        <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-envelope me-2"></i>Envelope</h3>
                    </div>
                    <div class="p-3" id="headerEnvelopeResults">-</div>
                </div>
                <!-- Auth Results -->
                <div class="app-table-card ws-card mb-3" style="border-top:3px solid #0d6efd;">
                    <div class="log-title-wrapper app-table-title">
                        <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-check-double me-2"></i>Authentication Results</h3>
                    </div>
                    <div class="p-3" id="headerAuthResults">-</div>
                </div>
                <!-- Received Chain -->
                <div class="app-table-card ws-card mb-3" style="border-top:3px solid #6f42c1;">
                    <div class="log-title-wrapper app-table-title">
                        <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-route me-2"></i>Received Chain</h3>
                    </div>
                    <div class="p-3" id="headerReceivedResults">-</div>
                </div>
            </div>
        </div>

        <!-- TAB: Blacklist -->
        <div class="tab-pane" id="tab-blacklist" style="display:none;">
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid var(--primary-color);">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-ban me-2"></i>Blacklist Check</h3>
                    <button class="app-table-btn" id="blacklistCheckBtn" style="height:36px;">
                        <i class="fas fa-search me-1"></i>Check
                    </button>
                </div>
                <div class="p-3">
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <input type="text" class="app-table-input" id="blacklistTargetInput"
                            placeholder="IP address or domain"
                            style="flex:1;min-width:200px;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                        <button class="btn btn-primary" id="blacklistCheckGo" style="height:36px;border-radius:6px;">
                            <i class="fas fa-search me-1"></i>Check
                        </button>
                    </div>
                </div>
            </div>

            <div id="blacklistResults" style="display:none;">
                <div class="app-table-card ws-card mb-3" style="border-top:3px solid var(--primary-color);">
                    <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                        <h3 class="m-0" style="font-size:var(--font-lg);">
                            <i class="fas fa-list me-2"></i>Blacklist Results
                            <span id="blSummary" class="badge ms-2" style="font-size:var(--font-xs);vertical-align:middle;">0/0</span>
                        </h3>
                    </div>
                    <div class="p-3" id="blacklistResultsTable">-</div>
                </div>
            </div>
        </div>

        <!-- TAB: Email Validate -->
        <div class="tab-pane" id="tab-validate" style="display:none;">
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid var(--primary-color);">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-check-circle me-2"></i>Email Address Validation</h3>
                    <button class="app-table-btn" id="emailValidateBtn" style="height:36px;">
                        <i class="fas fa-check me-1"></i>Validate
                    </button>
                </div>
                <div class="p-3">
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <input type="email" class="app-table-input" id="emailValidateInput"
                            placeholder="email@example.com"
                            style="flex:1;min-width:200px;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                        <button class="btn btn-primary" id="emailValidateGo" style="height:36px;border-radius:6px;">
                            <i class="fas fa-check me-1"></i>Validate
                        </button>
                    </div>
                </div>
            </div>

            <div id="emailValidateResults" style="display:none;">
                <div class="app-table-card ws-card mb-3" style="border-top:3px solid var(--primary-color);">
                    <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                        <h3 class="m-0" style="font-size:var(--font-lg);">
                            <i class="fas fa-tasks me-2"></i>Validation Results
                            <span id="emailValScore" class="badge ms-2" style="font-size:var(--font-xs);vertical-align:middle;">0/100</span>
                        </h3>
                    </div>
                    <div class="p-3" id="emailValidateChecks">-</div>
                </div>
            </div>
        </div>

        <!-- TAB: SMTP Test -->
        <div class="tab-pane" id="tab-smtp" style="display:none;">
            <!-- SMTP Test -->
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid var(--primary-color);">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-envelope-open-text me-2"></i>SMTP Server Test</h3>
                </div>
                <div class="p-3">
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <input type="text" class="app-table-input" id="smtpHostInput"
                            placeholder="mail.example.com"
                            style="flex:1;min-width:200px;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                        <input type="number" class="app-table-input" id="smtpPortInput"
                            placeholder="25" value="25"
                            style="width:80px;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                        <button class="btn btn-primary" id="smtpTestGo" style="height:36px;border-radius:6px;">
                            <i class="fas fa-plug me-1"></i>Test
                        </button>
                    </div>
                </div>
            </div>

            <div id="smtpTestResults" style="display:none;">
                <div class="app-table-card ws-card mb-3" style="border-top:3px solid var(--primary-color);">
                    <div class="log-title-wrapper app-table-title">
                        <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-info-circle me-2"></i>Server Info</h3>
                    </div>
                    <div class="p-3" id="smtpServerInfo">-</div>
                </div>
                <div class="app-table-card ws-card mb-3" style="border-top:3px solid #0d6efd;">
                    <div class="log-title-wrapper app-table-title">
                        <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-terminal me-2"></i>EHLO Response</h3>
                    </div>
                    <div class="p-3" id="smtpEhloInfo">-</div>
                </div>
            </div>

            <!-- Port Scan -->
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid #6f42c1;">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-plug me-2"></i>Mail Port Scanner</h3>
                    <button class="btn btn-primary" id="portScanGo" style="height:36px;border-radius:6px;">
                        <i class="fas fa-search me-1"></i>Scan
                    </button>
                </div>
                <div class="p-3">
                    <div class="d-flex gap-2 align-items-center flex-wrap mb-3">
                        <input type="text" class="app-table-input" id="portScanInput"
                            placeholder="mail.example.com"
                            style="flex:1;min-width:200px;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                    </div>
                    <div id="portScanResults">-</div>
                </div>
            </div>

            <!-- BIMI Check -->
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid #198754;">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-image me-2"></i>BIMI Record Check</h3>
                    <button class="btn btn-primary" id="bimiCheckGo" style="height:36px;border-radius:6px;">
                        <i class="fas fa-search me-1"></i>Check
                    </button>
                </div>
                <div class="p-3">
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <input type="text" class="app-table-input" id="bimiDomainInput"
                            placeholder="example.com"
                            style="flex:1;min-width:200px;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                    </div>
                </div>
            </div>
            <div id="bimiResults" style="display:none;">
                <div class="app-table-card ws-card mb-3" style="border-top:3px solid #198754;">
                    <div class="log-title-wrapper app-table-title">
                        <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-image me-2"></i>BIMI Result</h3>
                    </div>
                    <div class="p-3" id="bimiResultContent">-</div>
                </div>
            </div>

            <!-- MTA-STS Check -->
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid #dc3545;">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-shield-alt me-2"></i>MTA-STS Check</h3>
                    <button class="btn btn-primary" id="mtaStsCheckGo" style="height:36px;border-radius:6px;">
                        <i class="fas fa-search me-1"></i>Check
                    </button>
                </div>
                <div class="p-3">
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <input type="text" class="app-table-input" id="mtaStsDomainInput"
                            placeholder="example.com"
                            style="flex:1;min-width:200px;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                    </div>
                </div>
            </div>
            <div id="mtaStsResults" style="display:none;">
                <div class="app-table-card ws-card mb-3" style="border-top:3px solid #dc3545;">
                    <div class="log-title-wrapper app-table-title">
                        <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-shield-alt me-2"></i>MTA-STS Result</h3>
                    </div>
                    <div class="p-3" id="mtaStsResultContent">-</div>
                </div>
            </div>
        </div>
    </div>
</div>
