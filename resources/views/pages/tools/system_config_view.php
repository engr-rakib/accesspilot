<?php
/**
 * resources/views/pages/tools/system_config_view.php
 * Deployment identity, LDAP + PowerShell backends, storage — unified admin portal.
 */

// ── Inline doc viewer ──────────────────────────────────────────────
$docFile = trim((string) ($_GET['doc'] ?? ''));
if ($docFile) {
    $docMap = [
        'API_DOCUMENTATION' => __DIR__ . '/../../../../docs/client/guides/API_DOCUMENTATION.md',
    ];
    if (isset($docMap[$docFile])) {
        $mdPath = $docMap[$docFile];
        if (file_exists($mdPath) && is_readable($mdPath)) {
            $markdown = file_get_contents($mdPath);
            $title = 'HRMS API Integration Guide';
            $icon  = 'fas fa-plug';

            function render_markdown_ss(string $text): string {
                $text = str_replace(["\r\n", "\r"], "\n", $text);
                $lines = explode("\n", $text);
                $html = '';
                $inCodeBlock = false;
                $codeContent = '';
                $codeLang = '';
                $inList = false;
                $listType = '';
                $inTable = false;
                $tableHeaders = [];
                $tableRows = [];

                $flushList = function () use (&$html, &$inList, &$listType) {
                    if ($inList) { $html .= ($listType === 'ol' ? "</ol>\n" : "</ul>\n"); $inList = false; $listType = ''; }
                };
                $flushTable = function () use (&$html, &$inTable, &$tableHeaders, &$tableRows) {
                    if ($inTable && !empty($tableHeaders)) {
                        $html .= "<table class=\"md-table\">\n<thead>\n<tr>";
                        foreach ($tableHeaders as $h) $html .= '<th>' . htmlspecialchars(trim($h)) . '</th>';
                        $html .= "</tr>\n</thead>\n<tbody>\n";
                        foreach ($tableRows as $row) {
                            $html .= "<tr>";
                            foreach ($row as $cell) $html .= '<td>' . htmlspecialchars(trim($cell)) . '</td>';
                            $html .= "</tr>\n";
                        }
                        $html .= "</tbody>\n</table>\n";
                    }
                    $inTable = false; $tableHeaders = []; $tableRows = [];
                };
                $inlineMd = function ($str): string {
                    $str = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $str);
                    $str = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $str);
                    $str = preg_replace('/`([^`]+)`/', '<code class="md-inline-code">$1</code>', $str);
                    $str = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $str);
                    return $str;
                };

                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if (str_starts_with($trimmed, '```')) {
                        if ($inCodeBlock) { $html .= '<pre class="md-code-block"><code>' . htmlspecialchars($codeContent) . "</code></pre>\n"; $inCodeBlock = false; $codeContent = ''; $codeLang = ''; }
                        else { $flushList(); $flushTable(); $inCodeBlock = true; $codeLang = substr($trimmed, 3); $codeContent = ''; }
                        continue;
                    }
                    if ($inCodeBlock) { $codeContent .= $line . "\n"; continue; }
                    if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $trimmed)) { $flushList(); $flushTable(); $html .= "<hr class=\"md-hr\">\n"; continue; }
                    if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $m)) { $flushList(); $flushTable(); $html .= "<h" . strlen($m[1]) . " class=\"md-h" . strlen($m[1]) . "\">{$inlineMd($m[2])}</h" . strlen($m[1]) . ">\n"; continue; }
                    if (str_starts_with($trimmed, '|') && substr_count($trimmed, '|') >= 2) {
                        $cells = explode('|', trim($trimmed, '|'));
                        if (preg_match('/^[\s\-:]+$/', trim($cells[0]))) continue;
                        if (!$inTable) { $inTable = true; $tableHeaders = $cells; }
                        else { $tableRows[] = $cells; }
                        continue;
                    } else { $flushTable(); }
                    if (preg_match('/^(\s*)[\*\-\+]\s+(.+)$/', $trimmed, $m)) {
                        if (!$inList || $listType !== 'ul') { $flushList(); $inList = true; $listType = 'ul'; $html .= "<ul>\n"; }
                        $html .= '<li>' . $inlineMd($m[2]) . "</li>\n"; continue;
                    }
                    if (preg_match('/^(\s*)\d+\.\s+(.+)$/', $trimmed, $m)) {
                        if (!$inList || $listType !== 'ol') { $flushList(); $inList = true; $listType = 'ol'; $html .= "<ol>\n"; }
                        $html .= '<li>' . $inlineMd($m[2]) . "</li>\n"; continue;
                    }
                    if ($trimmed === '') { $flushList(); $flushTable(); continue; }
                    $flushList(); $flushTable();
                    $html .= '<p class="md-paragraph">' . $inlineMd($trimmed) . "</p>\n";
                }
                $flushList(); $flushTable();
                if ($inCodeBlock) $html .= '<pre class="md-code-block"><code>' . htmlspecialchars($codeContent) . "</code></pre>\n";
                return $html;
            }

            echo '<div class="doc-container slide-in-top" style="padding:16px;">';
            echo '<div class="status-banner success mb-3">
                    <div class="status-banner-icon"><i class="' . $icon . '"></i></div>
                    <div>
                        <div class="status-banner-title">' . htmlspecialchars($title) . '</div>
                        <div class="status-banner-msg">
                            <a href="index.php?page=system_config" class="text-white text-decoration-none"><i class="fas fa-arrow-left me-1"></i>Back to System Configuration</a>
                        </div>
                    </div>
                  </div>';
            echo '<div class="card app-table-card"><div class="card-body doc-card-body"><div class="doc-content">';
            echo render_markdown_ss($markdown);
            echo '</div></div></div></div>';
            return;
        }
    }
    // doc param present but not found — fall through to normal page
}
// ── End inline doc viewer ──────────────────────────────────────────
?>
<div class="sys-config-content sys-config-portal slide-in-top">

    <form id="systemConfigForm">
        <input type="hidden" name="app_name" id="config_app_name" value="AccessPilot">
        <input type="hidden" name="domain" id="config_domain" value="">
        <input type="hidden" name="base_dn" id="config_base_dn" value="">

        <!-- Status dashboard — individual compact cards in a single row -->
        <div id="sys_status_dashboard" class="sys-status-dash mb-2 sys-hidden sys-fade">
            <div class="sys-dash-card">
                <div class="sys-dash-icon"><i class="fas fa-certificate"></i></div>
                <div class="sys-dash-body">
                    <span class="sys-dash-label">License</span>
                    <span id="dash_license" class="badge rounded-pill bg-secondary sys-dash-val">...</span>
                </div>
            </div>
            <div class="sys-dash-card">
                <div class="sys-dash-icon"><i class="fas fa-microchip"></i></div>
                <div class="sys-dash-body">
                    <span class="sys-dash-label">PHP LDAP</span>
                    <span id="ldap_card_extension" class="badge rounded-pill bg-secondary sys-dash-val">...</span>
                </div>
            </div>
            <div class="sys-dash-card">
                <div class="sys-dash-icon"><i class="fas fa-globe"></i></div>
                <div class="sys-dash-body">
                    <span class="sys-dash-label">Active Domain</span>
                    <span id="diag_domain_val" class="sys-status-chip sys-dash-val">...</span>
                </div>
            </div>

            <div class="sys-dash-card">
                <div class="sys-dash-icon"><i class="fas fa-cogs"></i></div>
                <div class="sys-dash-body">
                    <span class="sys-dash-label">Backend</span>
                    <span id="diag_pass_val" class="sys-status-chip sys-dash-val">...</span>
                </div>
            </div>
            <div class="sys-dash-card">
                <div class="sys-dash-icon"><i class="fas fa-database"></i></div>
                <div class="sys-dash-body">
                    <span class="sys-dash-label">Storage Vault</span>
                    <span id="status_secure_vault" class="badge rounded-pill bg-secondary sys-dash-val">...</span>
                </div>
            </div>
            <div class="sys-dash-card">
                <div class="sys-dash-icon"><i class="fas fa-bolt"></i></div>
                <div class="sys-dash-body">
                    <span class="sys-dash-label">API</span>
                    <span id="diag_ttl_display" class="sys-status-chip sys-chip-ttl sys-dash-val">—</span>
                </div>
            </div>
            <input type="hidden" id="diag_avg_ttl" value="">
        </div>

        <div id="deploy_status_banner_row" class="row sys-hidden sys-fade mb-2">
            <div class="col-12">
                <div id="deploy_status_banner" class="status-banner danger">
                    <div class="status-banner-icon"><i class="fas fa-bug"></i></div>
                    <div>
                        <div id="deploy_status_title" class="status-banner-title">INVALID SIGNATURE STATUS</div>
                        <div id="deploy_status_msg" class="status-banner-msg">Your deployment is registered but unlicensed.</div>
                    </div>
                    <span id="deploy_status_badge" class="status-banner-restricted"><i class="fas fa-ban"></i>RESTRICTED</span>
                </div>
            </div>
        </div>

        <!-- RIBBON TABS -->
        <div class="noc-tabs-bar">
            <button class="noc-tab-item active" data-tab="application"><i class="fas fa-cube"></i>Application</button>
            <button class="noc-tab-item" data-tab="domain"><i class="fas fa-server"></i>Domain</button>
            <button class="noc-tab-item" data-tab="adobjects"><i class="fas fa-users-cog"></i>AD Objects</button>
        </div>

        <!-- TAB: Application -->
        <div id="tab-application" class="col-12 noc-tab-content">
            <div class="row">
                <div class="col-xl-7">
                    <!-- Organization Setup -->
                    <div class="card app-table-card" id="org_setup_card">
                        <div class="card-body no-padding">
                            <div class="app-table-title d-flex align-items-center flex-wrap gap-2">
                                <span><i class="fas fa-building text-primary me-1"></i>Organization Setup</span>
                                <span id="sys_cert_badge" class="badge rounded-pill ms-auto sys-hidden" style="background:rgba(22,163,74,0.12);color:#059669;font-size:0.7rem;font-weight:700;border:1px solid rgba(22,163,74,0.25);letter-spacing:0.02em;">
                                    <i class="fas fa-check-circle me-1" style="font-size:0.6rem;"></i><span id="sys_cert_id_text">—</span>
                                </span>
                            </div>
                            <div class="p-3">
                                <div class="row g-3 mb-2">
                                    <div class="col-12">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="sys-label mb-0">Application Identity</span>
                                            <span class="badge rounded-pill bg-secondary sys-badge-sm" style="font-size:var(--font-xs);">DEPLOYMENT ID</span>
                                        </div>
                                        <div class="input-group">
                                            <input type="text" class="form-control font-mono fw-bold" id="fld_deploy_id" value="—" readonly style="font-size:var(--font-md);letter-spacing:0.02em;">
                                            <button class="btn btn-outline-secondary btn-sm" type="button" title="Copy deployment ID" onclick="navigator.clipboard.writeText(this.previousElementSibling.value);this.querySelector('i').className='fas fa-check';setTimeout(()=>this.querySelector('i').className='fas fa-copy',1500)"><i class="fas fa-copy"></i></button>
                                        </div>
                                        <small class="text-muted" style="font-size:var(--font-xs);">Unique identifier for license binding. Appears after organization registration.</small>
                                    </div>
                                </div>

                                <hr class="my-2">

                                <div class="row g-3 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label" for="fld_org_name">Organization name</label>
                                        <input type="text" class="form-control font-mono" name="org_name" id="fld_org_name" placeholder="e.g. Acme Corporation" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="fld_domain">Primary domain</label>
                                        <input type="text" class="form-control font-mono" name="domain" id="fld_domain" placeholder="e.g. corp.local" required>
                                    </div>
                                </div>

                                <input type="hidden" id="fld_base_dn" name="base_dn">

                                <div id="org_feedback" class="sys-inline-feedback sys-hidden mb-2"></div>
                                <div id="org_update_warning" class="sys-callout sys-callout-warn sys-hidden mb-2"><i class="fas fa-exclamation-triangle me-1"></i>Updating organization or domain may invalidate the current license binding.</div>

                                <div id="lic_activate_prompt" class="sys-hidden mb-3">
                                    <div class="fw-bold mb-2" style="font-size:0.85rem;color:var(--text-color);">
                                        <i class="fas fa-info-circle me-1" style="color:var(--primary-color,#007bff);"></i>License Activation Guide
                                    </div>
                                    <p class="mb-2" style="font-size:0.75rem;color:#64748b;">Follow the steps below to obtain and apply a signed license certificate for this deployment.</p>
                                    <div class="d-flex flex-column gap-2">
                                        <div class="d-flex align-items-start gap-2 p-2" style="background:rgba(255,255,255,0.5);border-radius:8px;border:1px solid rgba(0,0,0,0.06);">
                                            <span class="badge rounded-pill bg-primary" style="min-width:22px;min-height:22px;line-height:22px;font-size:0.65rem;flex-shrink:0;text-align:center;">1</span>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold" style="font-size:0.78rem;color:var(--text-color);">Copy Deployment ID</div>
                                                <div style="font-size:0.72rem;color:#64748b;line-height:1.45;">Your deployment identity: <span id="guide_deploy_id_txt" class="fw-bold font-mono" style="color:var(--text-color);">—</span></div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" style="height:26px;padding:0 8px;font-size:0.7rem;flex-shrink:0;" onclick="var _d=document.getElementById('guide_deploy_id_txt');if(_d)navigator.clipboard.writeText(_d.textContent);this.querySelector('i').className='fas fa-check';setTimeout(function(){var _b=this;if(_b)_b.querySelector('i').className='fas fa-copy'}.bind(this),1200)"><i class="fas fa-copy"></i></button>
                                        </div>
                                        <div class="d-flex align-items-start gap-2 p-2" style="background:rgba(255,255,255,0.5);border-radius:8px;border:1px solid rgba(0,0,0,0.06);">
                                            <span class="badge rounded-pill bg-primary" style="min-width:22px;min-height:22px;line-height:22px;font-size:0.65rem;flex-shrink:0;text-align:center;">2</span>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold" style="font-size:0.78rem;color:var(--text-color);">Send to Vendor</div>
                                                <div style="font-size:0.72rem;color:#64748b;line-height:1.45;">Email the Deployment ID to your vendor along with your organization name and the domain(s) you wish to license.</div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-start gap-2 p-2" style="background:rgba(255,255,255,0.5);border-radius:8px;border:1px solid rgba(0,0,0,0.06);">
                                            <span class="badge rounded-pill bg-primary" style="min-width:22px;min-height:22px;line-height:22px;font-size:0.65rem;flex-shrink:0;text-align:center;">3</span>
                                            <div>
                                                <div class="fw-bold" style="font-size:0.78rem;color:var(--text-color);">Receive Signed Certificate</div>
                                                <div style="font-size:0.72rem;color:#64748b;line-height:1.45;">The vendor will return a signed certificate (JSON handshake). Keep it ready for the next step.</div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-start gap-2 p-2" style="background:rgba(255,255,255,0.5);border-radius:8px;border:1px solid rgba(0,0,0,0.06);">
                                            <span class="badge rounded-pill bg-primary" style="min-width:22px;min-height:22px;line-height:22px;font-size:0.65rem;flex-shrink:0;text-align:center;">4</span>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold" style="font-size:0.78rem;color:var(--text-color);">Apply Certificate on License Page</div>
                                                <div style="font-size:0.72rem;color:#64748b;line-height:1.45;">Go to the <strong>License</strong> page, paste the signed certificate into the text area, and click <strong>Synchronize Renewal</strong>.</div>
                                            </div>
                                            <a href="<?= htmlspecialchars(base_url('index.php?page=license')) ?>" class="btn btn-sm btn-outline-primary" style="height:26px;padding:0 8px;font-size:0.7rem;flex-shrink:0;"><i class="fas fa-external-link-alt me-1"></i>License Page</a>
                                        </div>
                                    </div>
                                </div>

                                <div id="lic_activated_msg" class="sys-hidden mb-3">
                                    <div class="d-flex align-items-start gap-2 p-2" style="background:rgba(22,163,74,0.06);border-radius:8px;border:1px solid rgba(22,163,74,0.18);">
                                        <i class="fas fa-check-circle mt-1" style="color:#16a34a;font-size:1.1rem;"></i>
                                        <div>
                                            <div class="fw-bold" style="font-size:0.82rem;color:var(--text-color);">Deployment Licensed &amp; Verified</div>
                                            <div id="sys_activated_org_info" style="font-size:0.72rem;color:#64748b;line-height:1.45;">Organization <strong id="lic_activated_org">—</strong> — bound to <strong id="lic_activated_domain">—</strong>.</div>
                                            <div style="font-size:0.72rem;color:#64748b;line-height:1.45;margin-top:4px;">
                                                <span class="fw-bold" style="color:var(--text-color);">Deployment ID:</span>
                                                <span id="sys_activated_did" class="font-mono">—</span>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" style="height:20px;padding:0 6px;font-size:0.62rem;margin-left:4px;vertical-align:baseline;" onclick="var _d=document.getElementById('sys_activated_did');if(_d)navigator.clipboard.writeText(_d.textContent);this.querySelector('i').className='fas fa-check';setTimeout(function(){var _b=this;if(_b)_b.querySelector('i').className='fas fa-copy'}.bind(this),1200)"><i class="fas fa-copy"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="app-form-actions">
                                    <button type="button" id="btnSubmitOrg" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Register</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- API Integration -->
                    <div class="card app-table-card mt-3" id="api_integration_card">
                        <?php $_hrmsUrl = config_get('api_paths.hrms_api_url', ''); $_hrmsImgUrl = config_get('api_paths.hrms_img_url', ''); $_hrmsStsUrl = config_get('api_paths.hrms_emp_sts_url', ''); ?>
                        <div class="card-body no-padding">
                            <div class="app-table-title">
                                <span><i class="fas fa-plug text-info me-1"></i>API Integration</span>
                                <span class="badge rounded-pill bg-warning" style="font-size:var(--font-xs);"><i class="fas fa-exclamation-triangle me-1"></i>Source of Truth</span>
                            </div>
                            <div class="p-3">
                                <div id="api_warning" class="sys-callout sys-callout-warn mb-2" style="font-size:var(--font-sm);">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    This API is the <strong>source of truth</strong> for all employee data.
                                    The application reads employee information from this API to create and manage AD users.
                                    If the API response structure changes, the application will not work correctly.
                                    <strong>Do not change field names</strong> without verifying compatibility.
                                    <div class="mt-1">
                                        <a href="index.php?page=system_config&doc=API_DOCUMENTATION" class="text-decoration-none" style="font-size:var(--font-xs);">
                                            <i class="fas fa-external-link-alt me-1"></i>View API Documentation
                                        </a>
                                    </div>
                                </div>

                                <!-- HRMS API URL + Endpoint 1 inline -->
                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <label class="form-label" style="font-size:var(--font-xs);font-weight:600;">
                                            <i class="fas fa-link text-secondary me-1"></i>HRMS API Endpoint
                                        </label>
                                        <div class="input-group">
                                            <input type="url" class="form-control font-mono" id="fld_hrms_api_url"
                                                value="<?= htmlspecialchars($_hrmsUrl) ?>"
                                                placeholder="https://hrms.example.com/api/employee"
                                                style="font-size:var(--font-sm);">
                                            <button type="button" id="btnToggleApiTest" class="btn btn-outline-info btn-sm" style="font-size:var(--font-sm);padding-top:0.375rem;padding-bottom:0.375rem;">
                                                <i class="fas fa-flask me-1"></i> Test API
                                            </button>
                                        </div>
                                        <div class="field-hint">API base URL. The <code>emp_id</code> parameter is appended automatically (e.g., <code>?emp_id=66684</code>).</div>

                                        <!-- Endpoint 1 inline (always visible) -->
                                        <div style="margin-top:4px;padding:6px 8px;border:1px solid var(--border-light,#eee);border-radius:6px;background:rgba(0,0,0,0.02);">
                                            <div style="font-size:var(--font-xs);font-weight:600;margin-bottom:2px;">
                                                <span class="badge rounded-pill bg-primary" style="font-size:0.55rem;">1</span>
                                                Get Individual Employee Details
                                                <span class="badge rounded-pill bg-success" style="font-size:0.55rem;">In Use</span>
                                            </div>
                                            <div style="font-size:var(--font-xs);color:var(--text-soft);line-height:1.4;">
                                                When <code>emp_id</code> is provided, the API returns full profile data for that specific employee.
                                            </div>
                                            <div style="margin-top:2px;font-size:var(--font-xs);color:var(--text-soft);">
                                                <span class="text-muted">Example URL:</span>
                                                <code class="font-mono"><?= htmlspecialchars(preg_replace('/\?.*$/', '', $_hrmsUrl)) ?>?emp_id=66684</code>
                                            </div>
                                        </div>

                                        <!-- Test (hidden, toggled by Test API button) -->
                                        <div id="api_test_section" class="sys-hidden" style="margin-top:6px;">
                                            <div style="padding:8px;border:1px solid var(--border-light,#eee);border-radius:6px;background:rgba(0,0,0,0.02);">
                                                <label style="font-size:var(--font-xs);color:var(--text-soft);">
                                                    <span class="badge rounded-pill bg-primary" style="font-size:0.55rem;">1</span>
                                                    Employee ID:
                                                </label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control font-mono" id="fld_test_emp_id" value="00000" style="font-size:var(--font-sm);" placeholder="Enter employee code">
                                                    <button type="button" class="btn btn-outline-info btn-sm btn-test-ep" style="font-size:var(--font-sm);padding-top:0.375rem;padding-bottom:0.375rem;" data-ep="emp_id">
                                                        <i class="fas fa-play me-1"></i>Test
                                                    </button>
                                                </div>
                                                <!-- Console -->
                                                <div id="api_console_ep1" class="api-console mt-2 sys-hidden">
                                                    <div class="api-console-header">
                                                        <span>API Test Console</span>
                                                        <span class="api-console-clear" onclick="apiClearConsole('api_console_ep1');">&times;</span>
                                                    </div>
                                                    <pre id="api_console_ep1_output" class="api-console-output"></pre>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" style="font-size:var(--font-xs);font-weight:600;">
                                            <i class="fas fa-image text-secondary me-1"></i>HRMS Image Base URL
                                        </label>
                                        <input type="url" class="form-control font-mono" id="fld_hrms_img_url"
                                            value="<?= htmlspecialchars($_hrmsImgUrl) ?>"
                                            placeholder="https://hrms.example.com/images"
                                            style="font-size:var(--font-sm);">
                                        <div class="field-hint">Base URL for employee profile images (e.g., <code>https://hrms.example.com/images/repository</code>).</div>
                                    </div>
                                </div>

                                <div class="app-table-subtitle mt-2 mb-2" style="font-size:var(--font-xs);font-weight:600;color:var(--text-soft);border-bottom:1px solid var(--border-color);padding-bottom:4px;">
                                    <i class="fas fa-list me-1"></i>API Endpoints
                                </div>

                                <!-- Endpoint 2 -->
                                <div style="padding:8px 10px;margin-bottom:8px;border:1px solid var(--border-light,#eee);border-radius:6px;background:rgba(0,0,0,0.02);">
                                    <div style="font-size:var(--font-sm);font-weight:600;margin-bottom:4px;">
                                        <span class="badge rounded-pill bg-secondary me-1" style="font-size:0.6rem;">2</span>
                                        Get Employees by Status
                                    </div>
                                    <div style="font-size:var(--font-xs);color:var(--text-soft);line-height:1.5;">
                                        When <code>emp_sts</code> is provided, the API returns a list of employee codes matching that status.
                                        This will be used for bulk operations — e.g., fetch all <strong>INACTIVE</strong> employees and disable their AD accounts, or verify <strong>ACTIVE</strong> employees.
                                    </div>
                                    <label style="font-size:var(--font-xs);color:var(--text-soft);margin-top:4px;">
                                        <i class="fas fa-link text-secondary me-1"></i>Status Endpoint URL
                                    </label>
                                    <input type="url" class="form-control font-mono mb-2" id="fld_hrms_sts_url"
                                        value="<?= htmlspecialchars($_hrmsStsUrl) ?>"
                                        placeholder="https://hrms.example.com/api/employees_by_status"
                                        style="font-size:var(--font-sm);">
                                    <div style="font-size:var(--font-xs);color:var(--text-soft);margin-bottom:4px;">
                                        If left empty, the main HRMS API Endpoint (Endpoint 1) will be used with <code>?emp_sts=STATUS</code>.
                                    </div>
                                    <label style="font-size:var(--font-xs);color:var(--text-soft);">
                                        <span class="badge rounded-pill bg-secondary" style="font-size:0.55rem;">2</span>
                                        Status:
                                    </label>
                                    <div class="input-group">
                                        <select class="form-select font-mono" id="fld_test_emp_sts" style="font-size:var(--font-sm);">
                                            <?php
                                            $statuses = ['ACTIVE', 'INACTIVE', 'PENDING', 'RESIGNED', 'TERMINATION', 'Terminated', 'RETIRED', 'SUSPENDED', 'DISCHARGED', 'DEATH', 'ACCIDENTAL_DEATH', 'CONTRACT_END', 'REMOVAL', 'RESERVED'];
                                            foreach ($statuses as $s) {
                                                echo '<option value="' . htmlspecialchars($s) . '">' . htmlspecialchars($s) . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <button type="button" class="btn btn-outline-info btn-sm btn-test-ep" style="font-size:var(--font-sm);padding-top:0.375rem;padding-bottom:0.375rem;" data-ep="emp_sts">
                                            <i class="fas fa-play me-1"></i>Test
                                        </button>
                                    </div>
                                    <!-- Console -->
                                    <div id="api_console_ep2" class="api-console mt-1 sys-hidden">
                                        <div class="api-console-header">
                                            <span>Response</span>
                                            <span class="api-console-clear" onclick="apiClearConsole('api_console_ep2');">&times;</span>
                                        </div>
                                        <pre id="api_console_ep2_output" class="api-console-output"></pre>
                                    </div>
                                    <div style="margin-top:6px;font-size:var(--font-xs);">
                                        <span class="text-muted">Possible status values:</span>
                                    </div>
                                    <div style="margin-top:2px;display:flex;flex-wrap:wrap;gap:3px;">
                                        <?php
                                        $statuses = ['ACTIVE', 'INACTIVE', 'PENDING', 'RESIGNED', 'TERMINATION', 'Terminated', 'RETIRED', 'SUSPENDED', 'DISCHARGED', 'DEATH', 'ACCIDENTAL_DEATH', 'CONTRACT_END', 'REMOVAL', 'RESERVED'];
                                        foreach ($statuses as $s) {
                                            echo '<span class="badge rounded-pill ' . ($s === 'ACTIVE' ? 'bg-success' : 'bg-secondary') . '" style="font-size:0.55rem;font-weight:400;">' . htmlspecialchars($s) . '</span>';
                                        }
                                        ?>
                                    </div>
                                </div>

                                <div style="padding:8px 10px;margin-bottom:8px;border:1px solid var(--border-light,#eee);border-radius:6px;background:rgba(0,0,0,0.02);">
                                    <div style="font-size:var(--font-sm);font-weight:600;margin-bottom:4px;">
                                        <i class="fas fa-sitemap text-secondary me-1"></i>OU Hierarchy Structure
                                    </div>
                                    <div style="font-size:var(--font-xs);color:var(--text-soft);line-height:1.6;">
                                        The PowerShell script builds the AD Organizational Unit (OU) path automatically from API fields:
                                        <div style="margin-top:4px;padding:6px 8px;background:rgba(0,0,0,0.03);border-radius:4px;font-family:monospace;font-size:var(--font-xs);">
                                            <strong>OPERATING_UNIT_TITLE</strong> → <strong>DEPARTMENT_TITLE</strong> → <strong>SECTION_TITLE</strong> → <strong>PRODUCT_TITLE</strong> → <strong>SUB_SECTION_TITLE</strong>
                                        </div>
                                        <div style="margin-top:4px;">
                                            For each level, a <strong>security group</strong> is auto-created (e.g., "Server Administration Group") and the user is added to it.
                                            If an OU already exists, it is reused. The user's Description field stores: <code>Rank: 27 | OU: Dept > Section > Product > Sub-Section</code>.
                                        </div>
                                    </div>
                                </div>

                                <div class="app-table-subtitle mt-2 mb-2" style="font-size:var(--font-xs);font-weight:600;color:var(--text-soft);border-bottom:1px solid var(--border-color);padding-bottom:4px;cursor:pointer;" onclick="toggleRespFields();">
                                    <i class="fas fa-table me-1"></i>Response Fields — What Data the API Provides
                                    <i class="fas fa-chevron-right ms-1" id="respFieldsIcon" style="font-size:0.55rem;transition:transform 0.2s;"></i>
                                </div>

                                <div id="respFieldsBlock" class="sys-hidden">

                                <p style="font-size:var(--font-xs);color:var(--text-soft);margin-bottom:6px;">
                                    Fields marked <span class="badge bg-danger" style="font-size:0.55rem;">User Creation</span> are directly used by the AD creation script (<code>create-user-core.ps1</code>) — if any of these are missing, renamed, or changed, <strong>user creation and OU/group management will fail</strong>.
                                    Fields marked <span class="badge bg-info" style="font-size:0.55rem;">Reference</span> are used for display and reference.
                                    <strong class="text-danger">Do not rename or remove any field.</strong>
                                </p>

                                <div style="overflow-x:auto;margin-bottom:8px;">
                                    <table class="sys-doc-table">
                                        <thead>
                                            <tr>
                                                <th style="width:30px;">#</th>
                                                <th>Field Name</th>
                                                <th style="width:140px;">Usage</th>
                                                <th>Used In</th>
                                                <th>Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $responseFields = [
                                                [1, 'EMP_CODE', 'danger', 'sAMAccountName, UserPrincipalName', 'Primary employee ID. Used as the Windows logon name (username). <strong>Without this, user cannot be created.</strong>'],
                                                [2, 'EMP_NAME', 'danger', 'GivenName, Surname, DisplayName, Name', 'Employee full name. Parsed to extract first/last name for AD. <strong>Without this, user cannot be created.</strong>'],
                                                [3, 'EMAIL', 'danger', 'EmailAddress (mail)', 'Official company email. <strong>Without this, user is created without email.</strong>'],
                                                [4, 'MOBILE', 'danger', 'MobilePhone (telephoneNumber)', 'Mobile / cell phone number.'],
                                                [5, 'DESIGNATION', 'danger', 'Title', 'Job title / designation.'],
                                                [6, 'DEPARTMENT_TITLE', 'danger', 'Department + OU hierarchy', 'Department name. Used both as AD Department attribute AND to build OU structure.'],
                                                [7, 'OPERATING_UNIT_TITLE', 'danger', 'Company + Top-level OU', 'Operating unit name. Used as AD Company AND the top-level OU for the user.'],
                                                [8, 'LOCATION_TITLE', 'danger', 'Office', 'Office location / branch. Used as AD physical delivery office.'],
                                                [9, 'RANK', 'danger', 'Description field', 'Employee rank. Written into the AD Description field: <code>Rank: 27 | OU: ...</code>.'],
                                                [10, 'SECTION_TITLE', 'danger', 'OU hierarchy', 'Used to build the OU path: Operating Unit → Department → <strong>Section</strong> → Product → Sub-Section.'],
                                                [11, 'PRODUCT_TITLE', 'danger', 'OU hierarchy', 'Used to build the OU path: Operating Unit → Department → Section → <strong>Product</strong> → Sub-Section.'],
                                                [12, 'SUB_SECTION_TITLE', 'danger', 'OU hierarchy (lowest level)', 'Used as the lowest OU level. The user is placed in this OU and added to its security group.'],
                                                [13, 'EMP_STS', 'danger', 'Status gate', 'Must be <code>"ACTIVE"</code> for user creation to proceed. If inactive, the script triggers auto-disable.'],
                                                [14, 'EMP_ID', 'info', 'Query parameter', 'Internal HRMS ID. The API is called with this value (<code>?emp_id=...</code>).'],
                                                [15, 'PIC_URL_', 'info', 'Profile image', 'Employee photo path. Combined with HRMS Image Base URL for display.'],
                                                [16, 'EMP_CAT_TITLE', 'info', 'Reference', 'Employee category (Permanent, Contractual, etc.).'],
                                                [17, 'PRODUCT_GROUP_TITLE', 'info', 'Reference', 'Product group (e.g., WCOM).'],
                                                [18, 'JOINING_DT / JOINING_DATE', 'info', 'Reference', 'Date of joining.'],
                                                [19, 'DOB', 'info', 'Reference', 'Date of birth.'],
                                                [20, 'AGE', 'info', 'Reference', 'Calculated age.'],
                                                [21, 'GENDER', 'info', 'Reference', 'Gender.'],
                                                [22, 'RESPONSIBILITY', 'info', 'Reference', 'Job responsibility description.'],
                                                [23, 'OTHERS', 'info', 'Reference', 'ROLE_TITLE, TEAM_TITLE, SUB_TEAM_TITLE, DESIGNATION_ORDER, JOB_LOCATION_ID, ALL_ORG_MST_*, etc.'],
                                            ];
                                            foreach ($responseFields as $f) {
                                                $badge = $f[2] === 'danger'
                                                    ? '<span class="badge bg-danger" style="font-size:0.55rem;">User Creation</span>'
                                                    : '<span class="badge bg-info" style="font-size:0.55rem;">Reference</span>';
                                                echo '<tr>';
                                                echo '<td style="text-align:center;">' . $f[0] . '</td>';
                                                echo '<td><code>' . $f[1] . '</code></td>';
                                                echo '<td>' . $badge . '</td>';
                                                echo '<td>' . $f[3] . '</td>';
                                                echo '<td>' . $f[4] . '</td>';
                                                echo '</tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div style="padding:6px 10px;border-left:3px solid var(--primary-color,#dc3545);margin-bottom:8px;font-size:var(--font-xs);color:var(--text-soft);line-height:1.5;">
                                    <i class="fas fa-exclamation-circle text-danger me-1"></i>
                                    <strong>Important:</strong> The API response field names must match <strong>exactly</strong> as listed above — the PowerShell creation script (<code>create-user-core.ps1</code>) reads them directly by name.
                                    The application uses <code>EMP_CODE</code> (not <code>EMP_ID</code>) as the samAccountName.
                                    The <code>emp_id</code> query parameter accepts the same value as <code>EMP_CODE</code>.
                                    The <code>PIC_URL_</code> field is combined with the <strong>HRMS Image Base URL</strong> to load profile photos.
                                    The <code>EMP_STS</code> field must contain <code>"ACTIVE"</code> for user creation to proceed; inactive employees trigger auto-disable.
                                </div>
                                </div><!-- /respFieldsBlock -->

                                <div class="app-table-subtitle mt-2 mb-2" style="font-size:var(--font-xs);font-weight:600;color:var(--text-soft);border-bottom:1px solid var(--border-color);padding-bottom:4px;cursor:pointer;" onclick="toggleExampleResponse();">
                                    <i class="fas fa-code me-1"></i>Example API Response
                                    <i class="fas fa-chevron-right ms-1" id="exampleRespIcon" style="font-size:0.55rem;transition:transform 0.2s;"></i>
                                </div>

                                <div id="exampleRespBlock" class="sys-hidden">
                                <pre class="sys-code-block">{
    "EMP_ID": "12345678",
    "EMP_CODE": "50755",
    "EMP_NAME": "Demo User Khan",
    "DESIGNATION": "Jr. Officer",
    "ROLE_TITLE": null,
    "EMAIL": "demo.user@company.com",
    "MOBILE": "+8801700000000",
    "OPERATING_UNIT_TITLE": "Company Name Ltd.",
    "LOCATION_TITLE": "Head Office",
    "JOB_LOCATION_ID": "87654321",
    "DEPARTMENT_TITLE": "ICT",
    "SECTION_TITLE": "Software Development",
    "PRODUCT_TITLE": "AccessPilot",
    "SUB_SECTION_TITLE": "Web Development",
    "EMP_STS": "ACTIVE",
    "PIC_URL_": "images\/repository\/HrCrEmp\/PIC_\/50755~abc12345-def6-7890-abcd-ef1234567890.png",
    "TEAM_TITLE": null,
    "SUB_TEAM_TITLE": null,
    "ALL_ORG_MST_ID": "11111111",
    "ALL_ORG_MST_TEAM_ID": null,
    "ALL_ORG_MST_DEPARTMENT_ID": "22222222",
    "ALL_ORG_MST_SECTION_ID": "33333333",
    "ALL_ORG_MST_PRODUCT_ID": "44444444",
    "DESIGNATION_ORDER": "15",
    "RANK": "15",
    "ALL_ORG_MST_OPERATING_UNIT_ID": "55",
    "JOINING_DT": "2024-06-01",
    "JOINING_DATE": "2024-06-01",
    "RESPONSIBILITY": "Web application development and maintenance",
    "DOB": "1995-03-15",
    "AGE": "31Y   3M   0D",
    "GENDER": "Male",
    "LAST_EDU_TITLE": null,
    "SECTION_ID": null,
    "DEPARTMENT_ID": null,
    "PRODUCT_ID": null,
    "EMP_CAT_TITLE": "Permanent",
    "PRODUCT_GROUP_TITLE": "WCOM",
    "ALL_ORG_MST_SUB_SECTION_ID": "55555555",
    "ALKP_PRODUCT_GROUP_ID": "6666666",
    "ADDRESS_PERMANENT": null,
    "DATA_SOURCE": "HRMS"
}</pre>
                                </div>

                                <div id="api_feedback" class="sys-inline-feedback sys-hidden mt-2"></div>

                                <div class="app-form-actions mt-2">
                                    <button type="button" id="btnSaveApi" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Save Changes</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div id="storage_row" class="sys-fade sys-hidden">
                        <div class="card app-table-card" id="storage_card">
                            <div class="card-body no-padding">
                                <div class="app-table-title"><span><i class="fas fa-database text-secondary me-1"></i>Storage Mapping</span></div>
                                <div class="p-3">
                                    <p class="sys-card-hint">Vault and log paths for secure config and audit logs.</p>
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <span id="status_log_storage" class="badge rounded-pill bg-secondary">Log path</span>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-12">
                                            <label class="form-label">Secure vault path</label>
                                            <input type="text" class="form-control font-mono" name="secure_base_path" id="config_secure_base_path" required disabled>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Base log path</label>
                                            <input type="text" class="form-control font-mono" name="base_log_path" id="config_base_log_path" required disabled>
                                        </div>
                                    </div>
                                    <div class="app-form-actions">
                                        <button type="button" id="btnCancelStorage" class="btn btn-secondary sys-hidden" disabled>Cancel</button>
                                        <button type="button" id="btnSaveStorage" class="btn btn-primary" disabled><i class="fas fa-save me-1"></i>Save Storage</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="password_row" class="sys-fade sys-hidden">
                        <div class="card app-table-card" id="password_card">
                            <div class="card-body no-padding">
                                <div class="app-table-title"><span><i class="fas fa-key text-warning me-1"></i>Default Passwords</span></div>
                                <div class="p-3">
                                    <p class="sys-card-hint">Passwords used for AD user provisioning and app defaults.</p>
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label">AD user password</label>
                                            <input type="text" class="form-control font-mono" name="default_password" id="config_default_password" required disabled>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">App user password</label>
                                            <input type="text" class="form-control font-mono" name="application_user_password" id="config_application_user_password" disabled>
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-12">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="pwd_reset_use_random" name="pwd_reset_use_random" disabled>
                                                <label class="form-check-label" for="pwd_reset_use_random">Use random password on unlock/reset operations</label>
                                                <span class="sys-card-hint d-block small ms-1 mt-1">When enabled, a random password is generated instead of the default password for unlock &amp; reset-password actions.</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="app-form-actions">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" id="ack_passwords" disabled>
                                            <label class="form-check-label sys-ack-label" for="ack_passwords">I acknowledge password policy impact.</label>
                                        </div>
                                        <button type="button" id="btnSavePasswords" class="btn btn-primary" disabled><i class="fas fa-save me-1"></i>Update</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="health_issues_row">
                        <div class="card app-table-card sys-health-hub" id="sys_health_hub">
                            <div class="card-body no-padding">
                                <div class="app-table-title d-flex align-items-center flex-wrap gap-2">
                                    <span><i class="fas fa-clipboard-list text-primary me-1"></i>Status &amp; Recommended Actions</span>
                                    <span id="diag_overall_val" class="sys-status-chip sys-chip-overall">CHECKING</span>
                                    <button type="button" id="btnRefreshDiagnostics" class="btn btn-sm sys-btn-refresh" title="Run live connectivity test">
                                        <i class="fas fa-sync-alt me-1"></i>Refresh
                                    </button>
                                </div>
                                <div class="p-3">
                                    <p class="sys-card-hint">System configuration health overview</p>
                                    <div id="diag_issues_panel">
                                        <ul id="diag_issues_list" class="sys-health-issues-list">
                                            <li class="sys-issue sys-issue-info"><span class="sys-issue-dot"></span><div><strong>Loading…</strong><p>Running configuration checks.</p></div></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: Domain -->
        <div id="tab-domain" class="col-12 noc-tab-content app-hidden">
            <div class="row">
                <div class="col-12">
                    <div id="domain_dash_container"></div>

                    <?php if (function_exists('ldap_get_domains')):
                        $_domains = ldap_get_domains();
                        $_activeKey = ldap_active_domain_key();
                        $_limitMsg = function_exists('ldap_domain_limit_message') ? ldap_domain_limit_message() : '';
                        $_max = 1; $_used = count($_domains); $_rem = 0;
                        if (function_exists('license_get_status')) {
                            $_lic = license_get_status();
                            $_max = (int) ($_lic['max_domains'] ?? 1);
                            $_rem = (int) ($_lic['domains_remaining'] ?? 0);
                        }
                    ?>
                    <div class="card app-table-card" id="domain_card">
                        <div class="card-body no-padding">
                            <div class="app-table-title d-flex align-items-center flex-wrap gap-2">
                                <span><i class="fas fa-server text-primary me-1"></i>Domains</span>
                                <span id="domainLimitBadge" class="badge rounded-pill bg-secondary sys-badge-sm"><?= htmlspecialchars($_limitMsg) ?></span>
                                <button type="button" id="btnRefreshDomains" class="btn btn-sm sys-btn-refresh ms-auto" title="Refresh AD domain status">
                                    <i class="fas fa-sync-alt me-1"></i>Refresh
                                </button>
                            </div>
                            <div class="p-3">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-3 pb-2 border-bottom">
                                    <label class="form-label mb-0 me-1">Backend</label>
                                    <span id="currentBackendBadge" class="badge rounded-pill sys-brand-pill-ldap me-2">LDAP</span>
                                    <input type="hidden" name="ldap_backend_mode" id="ldapBackendModeInput" value="ldap">
                                    <button type="button" id="btnToggleBackend" class="btn btn-outline-success btn-sm px-2 py-0" title="Switch between LDAP and PowerShell"><i class="fas fa-exchange-alt me-1"></i><small>Switch</small></button>
                                    <button type="button" id="btnTestBackend" class="btn btn-outline-primary btn-sm px-2 py-0" title="Test backend connectivity"><i class="fas fa-plug me-1"></i><small>Test</small></button>
                                    <button type="button" id="btnSaveBackendConfig" class="btn btn-primary btn-sm px-2 py-0" title="Save backend selection"><i class="fas fa-save me-1"></i><small>Save</small></button>
                                    <div id="backend_mode_hint" class="sys-callout sys-callout-info sys-hidden mb-0" style="font-size:var(--font-xs);padding:4px 10px;">
                                        LDAP mode uses PHP ext-ldap for all directory operations. No PowerShell dependency.
                                    </div>
                                </div>

                                <div id="ps_cred_fields_container" class="sys-hidden mb-2">
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label" for="config_admin_username">Admin username</label>
                                            <input type="text" class="form-control font-mono" name="admin_username" id="config_admin_username" placeholder="admin@domain.com">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label d-flex align-items-center justify-content-between">
                                                <span>Admin password</span>
                                                <span id="ps_admin_password_status" class="badge rounded-pill bg-secondary sys-cred-badge sys-hidden">NOT SET</span>
                                            </label>
                                            <div class="input-group">
                                                <input type="password" class="form-control font-mono sys-cred-field" name="admin_password" id="config_admin_password" placeholder="Not stored — enter to set" autocomplete="new-password">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle-pw="config_admin_password" tabindex="-1"><i class="fas fa-eye"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="app-form-actions">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" id="ack_credentials">
                                            <label class="form-check-label small" for="ack_credentials">I acknowledge credential updates affect AD automation.</label>
                                        </div>
                                        <button type="button" id="btnSaveCredentials" class="btn btn-primary" disabled><i class="fas fa-save me-1"></i>Save</button>
                                    </div>
                                </div>
                                <div id="domainLimitWarning" class="sys-callout sys-callout-warn mb-2 <?= ($_max > 0 && $_rem <= 0) ? '' : 'sys-hidden' ?>">
                                    <i class="fas fa-exclamation-triangle me-1"></i> Domain limit reached. Contact vendor to upgrade your license for additional domains.
                                </div>

                                <div class="log-table-wrapper app-table-wrapper">
                                    <table class="table app-data-table log-table mb-0" id="domainTable">
                                        <thead>
                                            <tr>
                                                <th>Domain</th>
                                                <th>Label</th>
                                                <th>Host:Port</th>
                                                <th>Connection</th>
                                                <th>Latency</th>
                                                <th>Last Test</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="domainTableBody">
                                            <?php if (empty($_domains)): ?>
                                            <tr><td colspan="7" class="text-muted text-center py-3">No domains configured yet. Click <strong>Add Domain</strong> to begin.</td></tr>
                                            <?php else: ?>
                                            <?php foreach ($_domains as $_d):
                                                $_dk = $_d['key'] ?? '';
                                                $_dl = $_d['label'] ?? $_dk;
                                                $_dh = $_d['host'] ?? '';
                                                $_dp = $_d['port'] ?? 389;
                                                $_isActive = $_dk === $_activeKey;
                                                $_db = $_d['base_dn'] ?? '';
                                                $_adName = '';
                                                if ($_db !== '') {
                                                    $_parts = [];
                                                    preg_match_all('/DC\s*=\s*([^,]+)/i', $_db, $_parts);
                                                    if (!empty($_parts[1])) {
                                                        $_adName = strtolower(implode('.', $_parts[1]));
                                                    }
                                                }
                                            ?>
                                            <tr class="domain-row <?= $_isActive ? 'domain-row-active' : '' ?>" data-key="<?= htmlspecialchars($_dk) ?>" data-host="<?= htmlspecialchars($_dh) ?>" data-port="<?= (int)$_dp ?>">
                                                <td>
                                                    <span class="domain-status-dot" style="display:inline-block;width:10px;height:10px;border-radius:50%;<?= $_isActive ? 'background:#22c55e;' : 'background:#cbd5e1;' ?>vertical-align:middle;margin-right:6px;flex-shrink:0;"></span>
                                                    <span class="badge rounded-pill bg-light text-dark border font-monospace fw-bold" style="font-size:0.78rem;letter-spacing:0.3px;padding:4px 10px;"><?= htmlspecialchars($_adName ?: $_dk) ?></span>
                                                    <?php if (isset($_lic) && strtolower($_adName) === strtolower($_lic['domain_name'] ?? '')): ?>
                                                    <span class="badge rounded-pill bg-warning text-dark ms-1" style="font-size:0.55rem;font-weight:700;letter-spacing:0.3px;vertical-align:middle;">LICENSED</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($_dl) ?></td>
                                                <td><code><?= htmlspecialchars($_dh) ?>:<?= (int)$_dp ?></code></td>
                                                <td><span class="domain-conn-badge badge rounded-pill bg-secondary" style="font-size:var(--font-xs);" data-status="unknown">—</span></td>
                                                <td><span class="domain-latency" style="font-size:var(--font-xs);color:var(--text-soft);">—</span></td>
                                                <td><span class="domain-lasttest" style="font-size:var(--font-xs);color:var(--text-soft);">—</span></td>
                                                <td style="width:1%;white-space:nowrap;">
                                                    <span class="d-flex gap-1" style="flex-wrap:nowrap;">
                                                        <?php if (!$_isActive): ?>
                                                        <button type="button" class="btn btn-outline-success btn-sm px-1 py-0 domain-switch-btn" title="Switch to this domain" data-key="<?= htmlspecialchars($_dk) ?>"><i class="fas fa-exchange-alt me-1"></i><small>Switch</small></button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-outline-primary btn-sm px-1 py-0 domain-edit-btn" title="Edit domain" data-key="<?= htmlspecialchars($_dk) ?>"><i class="fas fa-pen"></i></button>
                                                        <button type="button" class="btn btn-outline-warning btn-sm px-1 py-0 domain-test-btn" title="Test connection" data-key="<?= htmlspecialchars($_dk) ?>"><i class="fas fa-plug"></i></button>
                                                        <button type="button" class="btn btn-outline-danger btn-sm px-1 py-0 domain-delete-btn" title="Delete domain" data-key="<?= htmlspecialchars($_dk) ?>" <?= $_isActive ? 'disabled' : '' ?>><i class="fas fa-trash"></i></button>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div id="domainInlineForm" class="sys-domain-form-panel sys-hidden" aria-live="polite">
                                    <div class="sys-domain-form-head">
                                        <span class="sys-domain-form-title"><i class="fas fa-pen me-1"></i><span id="domainFormTitleText">Domain Configuration</span></span>
                                        <span id="domainTestStatus" class="badge rounded-pill bg-secondary sys-badge-sm">NOT TESTED</span>
                                    </div>
                                    <input type="hidden" id="domainFormKey">
                                    <div id="domainFormFeedback" class="sys-inline-feedback sys-hidden mb-2"></div>
                                    <div id="domainTestFeedback" class="sys-inline-feedback sys-hidden mb-2"></div>

                                    <div class="row g-2 mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label sys-field-label" for="domainFormKeyInput"><span>Domain Key <span class="text-danger">*</span></span><span class="sys-field-state">REQ</span></label>
                                            <input type="text" class="form-control font-mono" id="domainFormKeyInput" placeholder="e.g., example" autocomplete="off" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label sys-field-label" for="domainFormLabel"><span>Domain Label</span><span class="sys-field-state optional">OPTIONAL</span></label>
                                            <input type="text" class="form-control" id="domainFormLabel" placeholder="e.g., ExampleOrg">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label sys-field-label" for="domainFormHost"><span>LDAP Host <span class="text-danger">*</span></span><span id="domainHostStatus" class="sys-field-state">REQ</span></label>
                                            <div class="sys-inp-wrap">
                                                <input type="text" class="form-control font-mono" id="domainFormHost" placeholder="dc01.corp.local" required>
                                                <button class="sys-inp-icon" type="button" id="domainFormResolveBtn" title="Resolve host"><i class="fas fa-globe"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label sys-field-label" for="domainFormPort"><span>Port</span><span class="sys-field-state">REQ</span></label>
                                            <input type="number" class="form-control font-mono" id="domainFormPort" value="389" min="1" max="65535" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label sys-field-label" for="domainFormIp"><span>Resolved IP</span><span class="sys-field-state optional">AUTO</span></label>
                                            <input type="text" class="form-control font-mono" id="domainFormIp" placeholder="Optional">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label sys-field-label" for="domainFormUseTls"><span>TLS/LDAPS</span><span class="sys-field-state optional">MODE</span></label>
                                            <div class="form-check form-switch mb-0" style="padding-top:4px">
                                                <input class="form-check-input" type="checkbox" id="domainFormUseTls">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label sys-field-label" for="domainFormBaseDn"><span>Base DN <span class="text-danger">*</span></span><span class="sys-field-state">REQ</span></label>
                                            <input type="text" class="form-control font-mono" id="domainFormBaseDn" placeholder="DC=corp,DC=local" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label sys-field-label" for="domainFormUserSearchBase"><span>User Search Base</span><span class="sys-field-state optional">OPTIONAL</span></label>
                                            <input type="text" class="form-control font-mono" id="domainFormUserSearchBase" placeholder="OU=Users,DC=...">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label sys-field-label" for="domainFormBindDn"><span>Bind DN <span class="text-danger">*</span></span><span id="domainBindStatus" class="sys-field-state">REQ</span></label>
                                            <input type="text" class="form-control font-mono" id="domainFormBindDn" placeholder="CN=svc_ap,OU=...,DC=..." required>
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label sys-field-label" for="domainFormBindPassword"><span>Bind Password</span><span id="domainFormPwStatus" class="sys-field-state optional">NOT SET</span></label>
                                            <div class="sys-inp-wrap">
                                                <input type="password" class="form-control font-mono" id="domainFormBindPassword" placeholder="Set or change" autocomplete="new-password">
                                                <button type="button" class="sys-inp-icon" data-toggle-pw="domainFormBindPassword" title="Toggle visibility"><i class="fas fa-eye"></i></button>
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label sys-field-label" for="domainTestUsername"><span>Test User Lookup</span><span id="domainUserLookupStatus" class="sys-field-state optional">OPTIONAL</span></label>
                                            <div class="sys-inp-wrap">
                                                <input type="text" class="form-control font-mono" id="domainTestUsername" placeholder="Enter AD username after connection test" autocomplete="off">
                                                <button type="button" id="btnDomainTestUser" class="sys-inp-icon" title="Lookup user"><i class="fas fa-search" style="color:#0d6efd"></i></button>
                                            </div>
                                            <div id="domainTestUserResult" class="sys-domain-user-result sys-hidden"></div>
                                        </div>
                                    </div>

                                    <!-- Exchange Configuration -->
                                    <div class="border-top pt-2 mt-2">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                            <span class="sys-field-label"><i class="fas fa-exchange-alt me-1"></i>Exchange Configuration</span>
                                            <span id="exchangeConfigBadge" class="badge rounded-pill bg-secondary sys-badge-sm">NOT CONFIGURED</span>
                                            <button type="button" id="btnExchangeInfo" class="btn btn-sm p-0 border-0" style="color:var(--text-muted);font-size:14px;" title="Click for details"><i class="fas fa-info-circle"></i></button>
                                            <div class="form-check form-switch mb-0 ms-auto">
                                                <input class="form-check-input" type="checkbox" id="domainFormExEnabled">
                                                <label class="form-check-label" for="domainFormExEnabled" style="font-size:var(--font-xs);cursor:pointer;">Enable Exchange</label>
                                            </div>
                                        </div>

                                        <!-- Info panel -->
                                        <div id="exchangeInfoPanel" class="p-2 mb-2 rounded" style="display:none;background:var(--bg-muted);border:1px solid var(--border-color);font-size:11px;line-height:1.5;">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <strong style="font-size:12px;">Credential Mode Details</strong>
                                                <button type="button" id="btnExchangeInfoClose" class="btn btn-sm p-0 border-0" style="color:var(--text-muted);"><i class="fas fa-times"></i></button>
                                            </div>
                                            <table style="width:100%;border-collapse:collapse;">
                                                <tr style="border-bottom:1px solid var(--border-color);">
                                                    <th style="padding:4px 6px;text-align:left;width:22%;"></th>
                                                    <th style="padding:4px 6px;text-align:left;width:39%;">LDAP Bind User (Default)</th>
                                                    <th style="padding:4px 6px;text-align:left;width:39%;">Custom Exchange Account</th>
                                                </tr>
                                                <tr style="border-bottom:1px solid var(--border-color);">
                                                    <td style="padding:4px 6px;font-weight:600;">How it works</td>
                                                    <td style="padding:4px 6px;">Exchange cmdlets run using the same AD user configured in LDAP Bind DN field. No extra credentials needed.</td>
                                                    <td style="padding:4px 6px;">A dedicated service account connects to Exchange using Basic auth with explicit username/password.</td>
                                                </tr>
                                                <tr style="border-bottom:1px solid var(--border-color);">
                                                    <td style="padding:4px 6px;font-weight:600;">Requirement</td>
                                                    <td style="padding:4px 6px;">The LDAP bind user <strong>must</strong> have Exchange RBAC roles (e.g., Organization Management, Recipient Management).</td>
                                                    <td style="padding:4px 6px;">The custom user <strong>must</strong> have Exchange RBAC roles and be a domain user with mailbox access.</td>
                                                </tr>
                                                <tr style="border-bottom:1px solid var(--border-color);">
                                                    <td style="padding:4px 6px;font-weight:600;">Benefits</td>
                                                    <td style="padding:4px 6px;">No separate credential to manage. Reuses existing LDAP bind account. Single authentication source.</td>
                                                    <td style="padding:4px 6px;">Isolate Exchange permissions from LDAP operations. Use a low-privilege service account with only Exchange access.</td>
                                                </tr>
                                                <tr style="border-bottom:1px solid var(--border-color);">
                                                    <td style="padding:4px 6px;font-weight:600;">Drawbacks</td>
                                                    <td style="padding:4px 6px;">Bind user needs elevated Exchange permissions (may violate least-privilege). Password rotation affects both LDAP + Exchange.</td>
                                                    <td style="padding:4px 6px;">Extra credential to maintain. Password must be updated separately in this form when it changes.</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding:4px 6px;font-weight:600;">Auth method</td>
                                                    <td style="padding:4px 6px;">Kerberos (Linux) — kinit ticket from bind password. No password sent over wire.</td>
                                                    <td style="padding:4px 6px;">Basic auth — username/password sent to Exchange PS endpoint (use HTTPS in production).</td>
                                                </tr>
                                            </table>
                                        </div>

                                        <!-- Disabled placeholder -->
                                        <div id="exchangeDisabledMsg" class="p-3 text-center text-muted rounded" style="font-size:12px;background:var(--bg-muted);border:1px dashed var(--border-color);display:block;">
                                            <i class="fas fa-exchange-alt me-1"></i>Toggle <strong>Enable Exchange</strong> on to configure connection settings.
                                        </div>

                                        <!-- Config fields -->
                                        <div id="exchangeConfigFields" style="display:none;">
                                            <div class="mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <label class="form-label sys-field-label mb-0" for="domainFormExCredMode">Credential Mode</label>
                                                    <span id="exCredModeInfo" style="cursor:help;" title="How Exchange authenticates to run PowerShell cmdlets."><i class="fas fa-question-circle text-muted"></i></span>
                                                </div>
                                                <select class="form-control font-mono" id="domainFormExCredMode" style="height:30px;font-size:11px;max-width:300px;">
                                                    <option value="bind">Use LDAP bind user (auto-fallback)</option>
                                                    <option value="override">Use custom Exchange account</option>
                                                </select>
                                            </div>

                                            <!-- BIND MODE: auto-discovered info only -->
                                            <div id="exBindModeFields">
<div id="exBindInfo" class="p-2 rounded mb-2" style="background:var(--bg-muted);border:1px solid var(--border-color);font-size:11px;line-height:1.6;">
    <div><strong>Auth:</strong> LDAP bind user (Kerberos) — <span id="exBindUser">—</span></div>
    <div><strong>Server:</strong> <span id="exBindServer">Auto-discovered via LDAP</span></div>
    <div><strong>PS URI:</strong> <span id="exBindUri">Auto-built from server + port</span></div>
    <div><strong>Port:</strong> <span id="exBindPort">—</span></div>
    <div class="mt-1 text-muted"><i class="fas fa-info-circle"></i> No extra config needed. Uses same AD user as LDAP bind DN.</div>
</div>
                                            </div>

                                            <!-- OVERRIDE MODE: all input fields -->
                                            <div id="exOverrideModeFields" style="display:none;">
                                                <div class="row g-2 mb-2">
                                                    <div class="col-md-4">
                                                        <label class="form-label sys-field-label" for="domainFormExServer">Exchange Server <small class="text-muted">(override)</small></label>
                                                        <input type="text" class="form-control font-mono" id="domainFormExServer" placeholder="Auto-discovered via LDAP">
                                                        <small id="exServerResolved" class="text-muted" style="font-size:10px;"></small>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label sys-field-label" for="domainFormExUri">PS URI Override</label>
                                                        <input type="text" class="form-control font-mono" id="domainFormExUri" placeholder="http://SERVER/PowerShell/">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label sys-field-label" for="domainFormExUseHttps">Use HTTPS <small class="text-muted">(5986)</small></label>
                                                        <div class="form-check form-switch mb-0" style="padding-top:4px">
                                                            <input class="form-check-input" type="checkbox" id="domainFormExUseHttps">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row g-2 mb-2">
                                                    <div class="col-md-4">
                                                        <label class="form-label sys-field-label" for="domainFormExUsername">Username <small class="text-muted">(Exchange account)</small></label>
                                                        <input type="text" class="form-control font-mono" id="domainFormExUsername" placeholder="DOMAIN\username">
                                                        <small id="exUsernameResolved" class="text-muted" style="font-size:10px;"></small>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label sys-field-label" for="domainFormExPassword">Password</label>
                                                        <div class="sys-inp-wrap">
                                                            <input type="password" class="form-control font-mono" id="domainFormExPassword" placeholder="Set or change" autocomplete="new-password">
                                                            <button type="button" class="sys-inp-icon" data-toggle-pw="domainFormExPassword" title="Toggle visibility"><i class="fas fa-eye"></i></button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Test button (always visible) -->
                                            <div class="mb-2">
                                                <button type="button" id="btnDomainTestExchange" class="btn btn-outline-info btn-sm"><i class="fas fa-plug me-1"></i>Test Exchange</button>
                                            </div>

                                            <!-- Resolved config info (shown after test) -->
                                            <div id="exResolvedInfo" class="mt-2 p-2 rounded" style="background:var(--bg-muted);border:1px solid var(--border-color);display:none;">
                                                <div class="row g-1" style="font-size:11px;">
                                                    <div class="col-md-3"><strong>URI:</strong> <span id="exResolvedUri">—</span></div>
                                                    <div class="col-md-2"><strong>Port:</strong> <span id="exResolvedPort">—</span></div>
                                                    <div class="col-md-3"><strong>Auth:</strong> <span id="exResolvedAuth">—</span></div>
                                                    <div class="col-md-4"><strong>Server:</strong> <span id="exResolvedServer">—</span></div>
                                                </div>
                                            </div>

                                            <!-- Diagnostic result -->
                                            <div id="exDiagResult" class="mt-2 rounded" style="display:none;font-size:11px;"></div>
                                        </div>
                                    </div>

                                    <!-- Health Check Admin Credentials -->
                                    <div class="border-top pt-2 mt-2">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                            <span class="sys-field-label"><i class="fas fa-heartbeat me-1"></i>Health Check Admin Account</span>
                                            <span id="healthAdminBadge" class="badge rounded-pill bg-secondary sys-badge-sm">NOT SET</span>
                                        </div>
                                        <div id="healthAdminMsg" class="p-3 text-center text-muted rounded" style="font-size:12px;background:var(--bg-muted);border:1px dashed var(--border-color);display:none;">
                                            <i class="fas fa-check-circle me-1" style="color:var(--green);"></i>Stored credentials will be used automatically for deep health checks.
                                        </div>
                                        <div id="healthAdminFields">
                                            <div class="row g-2 mb-2">
                                                <div class="col-md-5">
                                                    <label class="form-label sys-field-label" for="domainFormHealthUsername">Username <small class="text-muted">(DOMAIN\Username)</small></label>
                                                    <input type="text" class="form-control font-mono" id="domainFormHealthUsername" placeholder="DOMAIN\AdminUser">
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label sys-field-label" for="domainFormHealthPassword">Password</label>
                                                    <div class="sys-inp-wrap">
                                                        <input type="password" class="form-control font-mono" id="domainFormHealthPassword" placeholder="Password" autocomplete="new-password">
                                                        <button type="button" class="sys-inp-icon" data-toggle-pw="domainFormHealthPassword" title="Toggle visibility"><i class="fas fa-eye"></i></button>
                                                    </div>
                                                </div>
                                                <div class="col-md-2 d-flex align-items-end">
                                                    <button type="button" id="btnSaveHealthAdmin" class="btn btn-outline-success btn-sm w-100"><i class="fas fa-save me-1"></i>Save</button>
                                                </div>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-md-12">
                                                    <button type="button" id="btnDeleteHealthAdmin" class="btn btn-outline-danger btn-sm" style="display:none;"><i class="fas fa-trash-alt me-1"></i>Clear Saved Credentials</button>
                                                    <small class="text-muted ms-2" style="font-size:10px;">Stored encrypted in vault. Used automatically by the Health button on the Reports page.</small>
                                                </div>
                                            </div>
                                            <div id="healthAdminDiag" class="mt-2 rounded" style="display:none;font-size:11px;"></div>
                                        </div>
                                    </div>

                                    <div class="app-form-actions">
                                        <button type="button" id="btnDomainTestConnect" class="btn btn-outline-success"><i class="fas fa-plug me-1"></i>Test Connection</button>
                                        <button type="button" id="btnCancelDomainForm" class="btn btn-secondary"><i class="fas fa-times me-1"></i>Cancel</button>
                                        <button type="button" id="btnSaveDomainInline" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Domain</button>
                                    </div>
                                </div>
                                <div class="app-form-actions">
                                    <span class="sys-card-hint"><?= count($_domains) ?> domain(s) configured</span>
                                    <button type="button" id="btnAddDomain" class="btn btn-secondary"><i class="fas fa-plus-circle me-1"></i>Add Domain</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB: AD Objects -->
        <div id="tab-adobjects" class="col-12 noc-tab-content app-hidden">
            <div class="row">
                <div class="col-12">

                    <div class="card app-table-card">
                        <div class="card-body no-padding">
                            <div class="app-table-title d-flex align-items-center flex-wrap gap-2">
                                <span><i class="fas fa-info-circle text-secondary me-1"></i>Per-Domain</span>
                                <a href="index.php?page=ad-guide" target="_blank" class="ms-auto" style="font-size:var(--font-xs);color:var(--primary-color);text-decoration:none;">
                                    <i class="fas fa-book me-1"></i>Guide
                                </a>
                            </div>
                            <div class="p-3" style="font-size:var(--font-xs);color:var(--text-soft);line-height:1.6;">
                                Each domain has its own naming config. Select a domain below, enable Customize, set fields, and Save. Fields you leave empty use default behavior.
                            </div>
                        </div>
                    </div>

                    <div class="card app-table-card">
                        <div class="card-body no-padding">
                            <div class="app-table-title d-flex align-items-center flex-wrap gap-2">
                                <span><i class="fas fa-user-tag text-primary me-1"></i>User Properties Configuration</span>
                                <select id="adoDomainSelector" class="form-select form-select-sm ms-auto" style="width:auto;min-width:180px;font-size:var(--font-xs);">
                                    <option value="">— Select domain —</option>
                                </select>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="adoCustomToggle">
                                    <label class="form-check-label fw-bold" for="adoCustomToggle" style="font-size:var(--font-xs);">Customize</label>
                                </div>
                            </div>
                            <div class="p-3">
                                <p class="sys-card-hint">Controls how HRMS name maps to AD fields for the selected domain. <strong>displayName</strong> &amp; <strong>cn</strong> always preserve the original HRMS name. <a href="index.php?page=ad-guide" target="_blank" style="color:var(--primary-color);text-decoration:none;"><i class="fas fa-book me-1"></i>Guide</a></p>

                                <!-- Default behavior hint (shown when toggle OFF) -->
                                <div id="adoDefaultHint" class="sys-hidden" style="font-size:var(--font-xs);color:var(--text-soft);padding:8px 12px;border-left:3px solid var(--border-color);margin-bottom:12px;">
                                    <i class="fas fa-info-circle me-1" style="color:var(--primary-color);"></i>Using system defaults (emp_code mode, simple whitespace split). Enable <strong>Customize</strong> above to configure per-domain.
                                </div>

                                <!-- Customization fields (shown when toggle ON) -->
                                <div id="adoCustomFields" class="sys-hidden">
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label" for="adoNamingMode" style="font-size:var(--font-xs);">sAMAccountName Mode
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="How sAMAccountName is built from employee name + ID.&#10;Example: 'John A. Doe' (code: 12345)&#10;━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━&#10;emp_code: 12345 (Employee ID only)&#10;last_name_id: doe12345 (Last name + ID) ✓&#10;first_non_prefix_id: john12345 (First name + ID)&#10;full_name_slug_id: johnadoe12345 (All name parts + ID)&#10;index:N_id: selects the Nth name part + ID&#10;  index:0_id = john12345 | index:1_id = a12345 | index:2_id = doe12345"></i>
                                            </label>
                                            <select class="form-select form-select-sm" id="adoNamingMode">
                                                <option value="emp_code">Employee ID only → 12345</option>
                                                <option value="last_name_id" selected>Last name + ID → doe12345</option>
                                                <option value="first_non_prefix_id">First name + ID → john12345</option>
                                                <option value="full_name_slug_id">All names + ID → johnadoe12345</option>
                                                <option value="index:0_id">Part 1 name + ID → john12345</option>
                                                <option value="index:1_id">Part 2 name + ID → a12345</option>
                                                <option value="index:2_id">Part 3 name + ID → doe12345</option>
                                            </select>
                                            <div class="field-hint">How sAMAccountName is built from employee name + code</div>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label" for="adoNamingCase" style="font-size:var(--font-xs);">Case
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="Case for sAMAccountName.&#10;lowercase: doe12345&#10;UPPERCASE: DOE12345&#10;As Is: Doe12345"></i>
                                            </label>
                                            <select class="form-select form-select-sm" id="adoNamingCase">
                                                <option value="lowercase">lowercase</option>
                                                <option value="uppercase">UPPERCASE</option>
                                                <option value="as_is">As Is</option>
                                            </select>
                                            <div class="field-hint">Letter casing for the username</div>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label" for="adoNamingSeparator" style="font-size:var(--font-xs);">Separator
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="Separator for full_name_slug mode.&#10;'john doe'+'.'→'john.doe'"></i>
                                            </label>
                                            <input type="text" class="form-control form-control-sm" id="adoNamingSeparator" placeholder="." maxlength="3">
                                            <div class="field-hint">Joins name parts — only used in <code>All names + ID</code> mode (e.g., <code>john.doe12345</code>)</div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="adoExcludePrefixes" style="font-size:var(--font-xs);">Exclude Prefixes
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="Honorifics to skip (comma-separated).&#10;'Mr. John Doe' → 'John Doe'"></i>
                                            </label>
                                            <input type="text" class="form-control form-control-sm" id="adoExcludePrefixes" placeholder="md., md, mr., mrs.">
                                            <div class="field-hint">Honorifics to strip from name (comma-separated, case-insensitive)</div>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label" for="adoGivenNameMode" style="font-size:var(--font-xs);">Given Name (givenName) Mode
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="How givenName (AD first name) is set.&#10;Example: 'John A. Doe' (code: 12345)&#10;━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━&#10;first_non_prefix: 'John' (First name, skip title) ✓&#10;first_part: 'John' (First part)&#10;emp_code: '12345' (Employee ID)&#10;emp_code_idx0_idx1: '12345 John A' (ID + name[0] + name[1])&#10;index:N: selects Nth part (index:1 = A)"></i>
                                            </label>
                                            <select class="form-select form-select-sm" id="adoGivenNameMode">
                                                <option value="first_non_prefix">First name (no title) → John ✓</option>
                                                <option value="first_part">First part → John</option>
                                                <option value="emp_code">Employee ID → 12345</option>
                                                <option value="emp_code_idx0_idx1">ID + name[0] + name[1] → 12345 John A</option>
                                                <option value="index:0">Part 1 → John</option>
                                                <option value="index:1">Part 2 → A</option>
                                                <option value="index:2">Part 3 → Doe</option>
                                            </select>
                                            <div class="field-hint">Which name part becomes givenName (first name) in AD. <code>emp_code_idx0_idx1</code> combines ID + first two name parts with spaces.</div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="adoSurnameMode" style="font-size:var(--font-xs);">Surname (sn) Mode
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="How sn (surname/last name) is extracted.&#10;Example: 'James R. Smith Jr.'&#10;━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━&#10;last_part: 'Jr.' (Last part) ✓&#10;after_given_name: 'R. Smith Jr.' (Rest after first name)&#10;emp_code: '99999' (Employee ID)&#10;index:N: selects Nth part (index:2 = Smith)"></i>
                                            </label>
                                            <select class="form-select form-select-sm" id="adoSurnameMode">
                                                <option value="last_part">Last part → Jr. ✓</option>
                                                <option value="after_given_name">After first name → R. Smith Jr.</option>
                                                <option value="emp_code">Employee ID → 99999</option>
                                                <option value="index:0">Part 1 → James</option>
                                                <option value="index:1">Part 2 → R.</option>
                                                <option value="index:2">Part 3 → Smith</option>
                                                <option value="index:3">Part 4 → Jr.</option>
                                            </select>
                                            <div class="field-hint">Which name part becomes sn (last name) in AD</div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="adoDisplayNameFormat" style="font-size:var(--font-xs);">Display Name Format
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="How displayName is formatted:&#10;original: HRMS full name unchanged ✓&#10;first_last: 'John Doe' (First name + Last name)&#10;last_first: 'Doe, John' (Last name, First name)"></i>
                                            </label>
                                            <select class="form-select form-select-sm" id="adoDisplayNameFormat">
                                                <option value="original">Original (HRMS) ✓</option>
                                                <option value="first_last">First name + Last name</option>
                                                <option value="last_first">Last name, First name</option>
                                            </select>
                                            <div class="field-hint">Format of displayName and cn in AD</div>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label" for="adoUpnSuffix" style="font-size:var(--font-xs);">UPN Suffix
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="Domain for userPrincipalName.&#10;Auto-detected from AD 'uPNSuffixes' attribute.&#10;Select a suffix or keep 'Auto' to use the default AD domain."></i>
                                            </label>
                                            <select class="form-select form-select-sm" id="adoUpnSuffix">
                                                <option value="">Auto (from AD)</option>
                                            </select>
                                            <div class="field-hint">Auto-detected from AD. Select a custom UPN suffix if needed.</div>
                                        </div>
                                        <div class="col-md-4">
                                        </div>
                                        <div class="col-md-4">
                                        </div>
                                    </div>
                                </div>

                                <!-- Preview -->
                                <div style="margin-top:12px;">
                                    <div style="font-size:var(--font-xs);font-weight:600;color:var(--text-soft);margin-bottom:6px;">Preview — Computed Values</div>
                                    <div class="sys-dcard">
                                        <div class="sys-dcard-row"><span class="sys-dcard-key">sAMAccountName</span><span id="adoPreviewSam" class="sys-dcard-val font-monospace fw-bold">—</span></div>
                                        <div class="sys-dcard-row"><span class="sys-dcard-key">userPrincipalName</span><span id="adoPreviewUpn" class="sys-dcard-val font-monospace">—</span></div>
                                        <div class="sys-dcard-row"><span class="sys-dcard-key">givenName</span><span id="adoPreviewGivenName" class="sys-dcard-val">—</span></div>
                                        <div class="sys-dcard-row"><span class="sys-dcard-key">sn</span><span id="adoPreviewSn" class="sys-dcard-val">—</span></div>
                                        <div class="sys-dcard-row"><span class="sys-dcard-key">displayName</span><span id="adoPreviewDisplayName" class="sys-dcard-val">—</span></div>
                                        <div class="sys-dcard-row"><span class="sys-dcard-key">cn</span><span id="adoPreviewCn" class="sys-dcard-val">—</span></div>
                                    </div>
                                </div>

                                <div style="margin-top:12px;">
                                    <div style="font-size:var(--font-xs);font-weight:600;color:var(--text-soft);margin-bottom:6px;">HRMS → AD Field Mapping</div>
                                    <div id="adoFieldMapping" style="font-size:var(--font-xs);line-height:1.8;padding:6px 8px;background:rgba(0,0,0,0.02);border-radius:4px;">
                                        <div class="d-flex align-items-center gap-2 mb-1" style="border-bottom:1px solid var(--border-color,#ddd);padding-bottom:3px;font-weight:600;color:var(--text-soft);">
                                            <span style="width:180px;">API Field</span>
                                            <span style="width:24px;text-align:center;"></span>
                                            <span style="width:140px;">AD Attribute</span>
                                            <span style="width:80px;text-align:center;">Config</span>
                                            <span>Value (example)</span>
                                        </div>
                                        <div id="adoMappingRows"></div>
                                    </div>
                                </div>

                                <div id="adoFeedback" class="sys-inline-feedback sys-hidden mt-2"></div>

                                <div class="app-form-actions mt-2">
                                    <button type="button" id="btnResetAdoForm" class="btn btn-secondary btn-sm" disabled><i class="fas fa-undo me-1"></i>Reset</button>
                                    <button type="button" id="btnSaveAdoConfig" class="btn btn-primary btn-sm" disabled><i class="fas fa-save me-1"></i>Save to Domain</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card app-table-card">
                        <div class="card-body no-padding">
                            <div class="app-table-title d-flex align-items-center flex-wrap gap-2">
                                <span><i class="fas fa-sitemap text-info me-1"></i>OU Management</span>
                                <a href="index.php?page=ad-guide" target="_blank" style="font-size:var(--font-xs);color:var(--primary-color);text-decoration:none;"><i class="fas fa-book me-1"></i>Guide</a>
                                <div class="form-check form-switch mb-0 ms-auto">
                                    <input class="form-check-input" type="checkbox" id="ouCustomToggle">
                                    <label class="form-check-label fw-bold" for="ouCustomToggle" style="font-size:var(--font-xs);">Customize</label>
                                </div>
                            </div>
                            <div class="p-3">
                                <p class="sys-card-hint">Controls how Organizational Units are auto-created from HRMS API fields during user creation.</p>

                                <div id="ouDomainBadge" style="font-size:var(--font-xs);color:var(--text-soft);margin-bottom:8px;">
                                    <i class="fas fa-server me-1"></i>Domain: <strong id="ouActiveDomainLabel">—</strong>
                                </div>

                                <div id="ouDefaultHint" style="font-size:var(--font-xs);color:var(--text-soft);padding:8px 12px;border-left:3px solid var(--border-color);margin-bottom:12px;">
                                    <i class="fas fa-info-circle me-1" style="color:var(--primary-color);"></i>OU path is auto-built from API fields. Existing OUs are reused. User is placed in the lowest-level OU.
                                    <div style="margin-top:8px;font-family:monospace;font-size:var(--font-xs);line-height:1.8;padding:6px 8px;background:rgba(0,0,0,0.02);border-radius:4px;">
                                        <div style="color:#eab308;"><i class="fas fa-sitemap me-1"></i>OPERATING_UNIT_TITLE <span style="color:#94a3b8;font-weight:400;">(Top OU)</span></div>
                                        <div style="padding-left:20px;border-left:1px dashed #e2e8f0;margin-left:6px;color:#3b82f6;">└─ DEPARTMENT_TITLE</div>
                                        <div style="padding-left:40px;border-left:1px dashed #e2e8f0;margin-left:6px;color:#10b981;">└─ SECTION_TITLE</div>
                                        <div style="padding-left:60px;border-left:1px dashed #e2e8f0;margin-left:6px;color:#8b5cf6;">└─ PRODUCT_TITLE</div>
                                        <div style="padding-left:80px;margin-left:6px;color:#ef4444;">└─ <strong>SUB_SECTION_TITLE</strong> <span style="color:#94a3b8;font-weight:400;">(User OU)</span></div>
                                    </div>
                                </div>

                                <div id="ouCustomFields" class="sys-hidden">
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label" style="font-size:var(--font-xs);">Level 1 (Top OU)
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="Top-level OU name. Default: OPERATING_UNIT_TITLE"></i>
                                            </label>
                                            <select class="form-select form-select-sm ou-level-select" id="ouFieldL1">
                                                <option value="OPERATING_UNIT_TITLE">OPERATING_UNIT_TITLE</option>
                                                <option value="DEPARTMENT_TITLE">DEPARTMENT_TITLE</option>
                                                <option value="SECTION_TITLE">SECTION_TITLE</option>
                                                <option value="PRODUCT_TITLE">PRODUCT_TITLE</option>
                                                <option value="SUB_SECTION_TITLE">SUB_SECTION_TITLE</option>
                                                <option value="">— Skip —</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" style="font-size:var(--font-xs);">Level 2
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="Second-level OU. Default: DEPARTMENT_TITLE"></i>
                                            </label>
                                            <select class="form-select form-select-sm ou-level-select" id="ouFieldL2">
                                                <option value="DEPARTMENT_TITLE">DEPARTMENT_TITLE</option>
                                                <option value="OPERATING_UNIT_TITLE">OPERATING_UNIT_TITLE</option>
                                                <option value="SECTION_TITLE">SECTION_TITLE</option>
                                                <option value="PRODUCT_TITLE">PRODUCT_TITLE</option>
                                                <option value="SUB_SECTION_TITLE">SUB_SECTION_TITLE</option>
                                                <option value="">— Skip —</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label" style="font-size:var(--font-xs);">Level 3
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="Third-level OU. Default: SECTION_TITLE"></i>
                                            </label>
                                            <select class="form-select form-select-sm ou-level-select" id="ouFieldL3">
                                                <option value="SECTION_TITLE">SECTION_TITLE</option>
                                                <option value="DEPARTMENT_TITLE">DEPARTMENT_TITLE</option>
                                                <option value="OPERATING_UNIT_TITLE">OPERATING_UNIT_TITLE</option>
                                                <option value="PRODUCT_TITLE">PRODUCT_TITLE</option>
                                                <option value="SUB_SECTION_TITLE">SUB_SECTION_TITLE</option>
                                                <option value="">— Skip —</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" style="font-size:var(--font-xs);">Level 4
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="Fourth-level OU. Default: PRODUCT_TITLE"></i>
                                            </label>
                                            <select class="form-select form-select-sm ou-level-select" id="ouFieldL4">
                                                <option value="PRODUCT_TITLE">PRODUCT_TITLE</option>
                                                <option value="OPERATING_UNIT_TITLE">OPERATING_UNIT_TITLE</option>
                                                <option value="DEPARTMENT_TITLE">DEPARTMENT_TITLE</option>
                                                <option value="SECTION_TITLE">SECTION_TITLE</option>
                                                <option value="SUB_SECTION_TITLE">SUB_SECTION_TITLE</option>
                                                <option value="">— Skip —</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label" style="font-size:var(--font-xs);">Level 5 (User OU)
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="Lowest-level OU where the user is placed. Default: SUB_SECTION_TITLE"></i>
                                            </label>
                                            <select class="form-select form-select-sm ou-level-select" id="ouFieldL5">
                                                <option value="SUB_SECTION_TITLE">SUB_SECTION_TITLE</option>
                                                <option value="OPERATING_UNIT_TITLE">OPERATING_UNIT_TITLE</option>
                                                <option value="DEPARTMENT_TITLE">DEPARTMENT_TITLE</option>
                                                <option value="SECTION_TITLE">SECTION_TITLE</option>
                                                <option value="PRODUCT_TITLE">PRODUCT_TITLE</option>
                                                <option value="">— Skip —</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" style="font-size:var(--font-xs);">Prefix
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="Optional prefix for all OU names"></i>
                                            </label>
                                            <input type="text" class="form-control form-control-sm" id="ouPrefix" placeholder="e.g. BD_">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" style="font-size:var(--font-xs);">Suffix
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="Optional suffix for all OU names"></i>
                                            </label>
                                            <input type="text" class="form-control form-control-sm" id="ouSuffix" placeholder="e.g. _OU">
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label" style="font-size:var(--font-xs);">Root OU (optional)
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="Base container where auto-created OUs are placed. Example: OU=CompanyUsers. Leave empty to create at domain root."></i>
                                            </label>
                                            <input type="text" class="form-control form-control-sm" id="ouRootOu" placeholder="e.g. OU=CompanyUsers">
                                        </div>
                                    </div>
                                    <!-- Preview -->
                                    <div style="margin-top:8px;">
                                        <div style="font-size:var(--font-xs);font-weight:600;color:var(--text-soft);margin-bottom:4px;">Preview Generated OU Path</div>
                                        <div id="ouPreview" style="padding:6px 8px;background:rgba(0,0,0,0.02);border-radius:4px;font-family:monospace;font-size:var(--font-xs);color:var(--text-soft);">
                                            OU=OperatingUnit → OU=Department → OU=Section → OU=Product → OU=SubSection
                                        </div>
                                    </div>
                                </div>

                                <div id="ouFeedback" class="sys-inline-feedback sys-hidden mt-2"></div>

                                <div class="app-form-actions mt-2">
                                    <button type="button" id="btnResetOU" class="btn btn-secondary btn-sm" disabled><i class="fas fa-undo me-1"></i>Reset</button>
                                    <button type="button" id="btnSaveOU" class="btn btn-primary btn-sm" disabled><i class="fas fa-save me-1"></i>Save to Domain</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card app-table-card">
                        <div class="card-body no-padding">
                            <div class="app-table-title d-flex align-items-center flex-wrap gap-2">
                                <span><i class="fas fa-users text-success me-1"></i>Group Management</span>
                                <a href="index.php?page=ad-guide" target="_blank" style="font-size:var(--font-xs);color:var(--primary-color);text-decoration:none;"><i class="fas fa-book me-1"></i>Guide</a>
                                <div class="form-check form-switch mb-0 ms-auto">
                                    <input class="form-check-input" type="checkbox" id="grpCustomToggle">
                                    <label class="form-check-label fw-bold" for="grpCustomToggle" style="font-size:var(--font-xs);">Customize</label>
                                </div>
                            </div>
                            <div class="p-3">
                                <p class="sys-card-hint">Controls how security groups are auto-created and how users are assigned to groups during creation.</p>

                                <div id="grpDomainBadge" style="font-size:var(--font-xs);color:var(--text-soft);margin-bottom:8px;">
                                    <i class="fas fa-server me-1"></i>Domain: <strong id="grpActiveDomainLabel">—</strong>
                                </div>

                                <div id="grpDefaultHint" style="font-size:var(--font-xs);color:var(--text-soft);padding:8px 12px;border-left:3px solid var(--border-color);margin-bottom:12px;">
                                    <i class="fas fa-info-circle me-1" style="color:var(--primary-color);"></i>For each OU level in the hierarchy, a <strong>security group</strong> is auto-created. The user is added to each group along the OU path. Existing groups are reused.
                                    <div style="margin-top:8px;font-family:monospace;font-size:var(--font-xs);line-height:1.8;padding:6px 8px;background:rgba(0,0,0,0.02);border-radius:4px;">
                                        <div style="color:#eab308;"><i class="fas fa-sitemap me-1"></i>OPERATING_UNIT_TITLE <span style="color:#94a3b8;">→ Group: "OU_Group"</span></div>
                                        <div style="padding-left:20px;border-left:1px dashed #e2e8f0;margin-left:6px;color:#3b82f6;">└─ DEPARTMENT_TITLE <span style="color:#94a3b8;">→ Group: "Dept_Group"</span></div>
                                        <div style="padding-left:40px;border-left:1px dashed #e2e8f0;margin-left:6px;color:#10b981;">└─ SECTION_TITLE <span style="color:#94a3b8;">→ Group: "Section_Group"</span></div>
                                        <div style="padding-left:60px;border-left:1px dashed #e2e8f0;margin-left:6px;color:#8b5cf6;">└─ PRODUCT_TITLE <span style="color:#94a3b8;">→ Group: "Product_Group"</span></div>
                                        <div style="padding-left:80px;margin-left:6px;color:#ef4444;">└─ <strong>SUB_SECTION_TITLE</strong> <span style="color:#94a3b8;">→ Group: "SubSection_Group" <span class="badge bg-primary" style="font-size:0.5rem;">User</span></span></div>
                                    </div>
                                </div>

                                <div id="grpCustomFields" class="sys-hidden">
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label" style="font-size:var(--font-xs);">Auto-Create Groups
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="Enable/disable automatic group creation per OU level"></i>
                                            </label>
                                            <div class="d-flex gap-3">
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="grpAutoCreate" id="grpAutoYes" value="1" checked>
                                                    <label class="form-check-label" for="grpAutoYes" style="font-size:var(--font-xs);">Enabled</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="grpAutoCreate" id="grpAutoNo" value="0">
                                                    <label class="form-check-label" for="grpAutoNo" style="font-size:var(--font-xs);">Disabled</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" style="font-size:var(--font-xs);">Group Name Prefix
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="Prefix added to auto-created group names"></i>
                                            </label>
                                            <input type="text" class="form-control form-control-sm" id="grpPrefix" placeholder="e.g. GRP_">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" style="font-size:var(--font-xs);">Group Name Suffix
                                                <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="Suffix added to auto-created group names"></i>
                                            </label>
                                            <input type="text" class="form-control form-control-sm" id="grpSuffix" placeholder="e.g. _Group">
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <div style="font-size:var(--font-xs);font-weight:600;color:var(--text-soft);margin-bottom:4px;">
                                            <i class="fas fa-tasks me-1"></i>Conditional Group Assignment Rules
                                            <i class="fas fa-question-circle text-muted" style="cursor:help;font-size:var(--font-xs);" title="Automatically add users to specific groups based on API field values"></i>
                                        </div>
                                        <p style="font-size:var(--font-xs);color:var(--text-soft);margin-bottom:6px;">
                                            Users whose HRMS field matches a condition will be added to the specified group during creation.
                                        </p>
                                        <div id="grpRulesContainer"></div>
                                        <button type="button" id="btnAddGrpRule" class="btn btn-sm btn-outline-primary mt-1" style="font-size:var(--font-xs);"><i class="fas fa-plus me-1"></i>Add Rule</button>
                                    </div>

                                    <!-- Preview -->
                                    <div style="margin-top:8px;">
                                        <div style="font-size:var(--font-xs);font-weight:600;color:var(--text-soft);margin-bottom:4px;">Preview Generated Groups</div>
                                        <div id="grpPreview" style="padding:6px 8px;background:rgba(0,0,0,0.02);border-radius:4px;font-family:monospace;font-size:var(--font-xs);color:var(--text-soft);">
                                            Auto: — | Conditional: —
                                        </div>
                                    </div>
                                </div>

                                <div id="grpFeedback" class="sys-inline-feedback sys-hidden mt-2"></div>

                                <div class="app-form-actions mt-2">
                                    <button type="button" id="btnResetGrp" class="btn btn-secondary btn-sm" disabled><i class="fas fa-undo me-1"></i>Reset</button>
                                    <button type="button" id="btnSaveGrp" class="btn btn-primary btn-sm" disabled><i class="fas fa-save me-1"></i>Save to Domain</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Credential confirm modal (z-index fixed above nav rail) -->
<div class="modal fade" id="credentialConfirmModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content sys-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-shield-alt" style="color:var(--sys-hub-accent);margin-right:10px;"></i><span id="modalTitleText">Confirm change</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="d-flex align-items-start gap-2 mb-3 sys-info-box">
                    <i class="fas fa-info-circle mt-1" style="color:var(--sys-hub-accent);flex-shrink:0;"></i>
                    <p id="modalDescText" class="sys-card-hint" style="margin:0;">Re-enter your portal credentials to authorize this change.</p>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-user me-1"></i>User ID</label>
                    <input type="text" class="form-control" id="confirm_user_id" autocomplete="username" placeholder="Enter your portal user ID">
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-lock me-1"></i>Password</label>
                    <input type="password" class="form-control" id="confirm_password" autocomplete="current-password" placeholder="Enter your portal password">
                </div>
                <div id="modalFeedback" class="sys-inline-feedback sys-hidden"></div>
            </div>
            <div class="modal-footer border-0 pt-0 app-form-actions">
<button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Cancel</button>
                                <button type="button" id="btnConfirmUpdate" class="btn btn-primary flex-fill"><i class="fas fa-check me-1"></i>Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- credentialConfirmModal is used by system_config_actions.js — kept for credential confirmation -->

<!-- Domain Switch Confirm Modal -->
<div class="modal fade" id="domainSwitchModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content sys-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-exchange-alt me-2" style="color:#f59e0b"></i>Switch Active Domain</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="d-flex align-items-start gap-2 mb-2 sys-info-box">
                    <i class="fas fa-exclamation-triangle mt-1" style="color:#f59e0b;flex-shrink:0;"></i>
                    <div>
                        <p id="domainSwitchDesc" class="mb-0" style="font-size:0.85rem;">Switch active domain to <strong id="domainSwitchTargetKey"></strong>?</p>
                        <p class="mb-0 mt-1" style="font-size:0.78rem;color:#64748b;">All LDAP operations will use this domain. The page will reload to apply the change.</p>
                    </div>
                </div>
                <div id="domainSwitchFeedback" class="sys-inline-feedback sys-hidden"></div>
            </div>
            <div class="modal-footer border-0 pt-0 app-form-actions">
                <button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Cancel</button>
                <button type="button" id="btnConfirmDomainSwitch" class="btn btn-warning flex-fill"><i class="fas fa-exchange-alt me-1"></i>Switch Now</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    window.AccessPilotInlineDomainManager = true;
    var apiBase = window.APP_CONFIG ? window.APP_CONFIG.apiBaseUrl : (typeof baseURL !== 'undefined' ? baseURL + 'api/index.php' : 'api/index.php');
    var phptoken = <?php if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); echo json_encode($_SESSION['csrf_token']); ?>;
    var csrfToken = window._csrfToken || (window.APP_CONFIG && window.APP_CONFIG.csrfToken) || phptoken;

    function apiHeaders(extra) {
        var h = { 'Content-Type': 'application/json' };
        if (csrfToken) h['X-CSRF-Token'] = csrfToken;
        if (extra) { for (var k in extra) { h[k] = extra[k]; } }
        return h;
    }

    var form = {
        key: document.getElementById('domainFormKey'),
        keyInput: document.getElementById('domainFormKeyInput'),
        label: document.getElementById('domainFormLabel'),
        host: document.getElementById('domainFormHost'),
        ip: document.getElementById('domainFormIp'),
        port: document.getElementById('domainFormPort'),
        useTls: document.getElementById('domainFormUseTls'),
        baseDn: document.getElementById('domainFormBaseDn'),
        userSearchBase: document.getElementById('domainFormUserSearchBase'),
        bindDn: document.getElementById('domainFormBindDn'),
        bindPassword: document.getElementById('domainFormBindPassword'),
        pwStatus: document.getElementById('domainFormPwStatus'),
        exEnabled: document.getElementById('domainFormExEnabled'),
        exCredMode: document.getElementById('domainFormExCredMode'),
        exServer: document.getElementById('domainFormExServer'),
        exUri: document.getElementById('domainFormExUri'),
        exUseHttps: document.getElementById('domainFormExUseHttps'),
        exUsername: document.getElementById('domainFormExUsername'),
        exPassword: document.getElementById('domainFormExPassword'),
        exBadge: document.getElementById('exchangeConfigBadge'),
        feedback: document.getElementById('domainFormFeedback'),
        testFeedback: document.getElementById('domainTestFeedback'),
        testStatus: document.getElementById('domainTestStatus'),
        inlineForm: document.getElementById('domainInlineForm'),
    };
    var btnSave = document.getElementById('btnSaveDomainInline');
    var btnCancel = document.getElementById('btnCancelDomainForm');
    var btnTest = document.getElementById('btnDomainTestConnect');
    var btnTestUser = document.getElementById('btnDomainTestUser');
    var btnResolve = document.getElementById('domainFormResolveBtn');
    var btnTestExchange = document.getElementById('btnDomainTestExchange');

    var resolveTimer = null;

    function feedback(msg, type) {
        form.feedback.textContent = msg;
        form.feedback.className = 'sys-inline-feedback mb-2 ' + (type === 'error' ? 'is-error' : type === 'success' ? 'is-success' : 'is-warn');
        form.feedback.classList.remove('sys-hidden');
    }

    function hideFeedback() { form.feedback.classList.add('sys-hidden'); }

    function testFeedback(msg, type) {
        form.testFeedback.textContent = msg;
        form.testFeedback.className = 'sys-inline-feedback mb-2 ' + (type === 'error' ? 'is-error' : type === 'success' ? 'is-success' : 'is-warn');
        form.testFeedback.classList.remove('sys-hidden');
    }

    function hideTestFeedback() { form.testFeedback.classList.add('sys-hidden'); }

    function setTestStatus(text, cls) {
        form.testStatus.textContent = text;
        form.testStatus.className = 'badge rounded-pill ' + (cls || 'bg-secondary');
    }

    function setFieldStatus(el, text, stateClass) {
        if (!el) return;
        el.textContent = text;
        el.className = 'sys-field-state ' + (stateClass || '');
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function clearForm() {
        form.key.value = '';
        form.keyInput.value = '';
        form.keyInput.disabled = false;
        form.label.value = '';
        form.host.value = '';
        form.ip.value = '';
        form.port.value = '389';
        form.useTls.checked = false;
        form.baseDn.value = '';
        form.userSearchBase.value = '';
        form.bindDn.value = '';
        form.bindPassword.value = '';
        form.exEnabled.checked = false;
        form.exCredMode.value = 'bind';
        form.exServer.value = '';
        form.exUri.value = '';
        form.exUseHttps.checked = true;
        form.exUsername.value = '';
        form.exPassword.value = '';
        var exSrvRes = document.getElementById('exServerResolved');
        if (exSrvRes) exSrvRes.textContent = '';
        var exUsrRes = document.getElementById('exUsernameResolved');
        if (exUsrRes) exUsrRes.textContent = '';
        var exResInfo = document.getElementById('exResolvedInfo');
        if (exResInfo) exResInfo.style.display = 'none';
        document.getElementById('exBindModeFields').style.display = 'block';
        document.getElementById('exOverrideModeFields').style.display = 'none';
        document.getElementById('exchangeConfigFields').style.display = 'none';
        document.getElementById('exchangeDisabledMsg').style.display = 'block';
        if (form.exBadge) {
            form.exBadge.textContent = 'NOT CONFIGURED';
            form.exBadge.className = 'badge rounded-pill bg-secondary sys-badge-sm';
        }
        if (healthUsernameInput) healthUsernameInput.value = '';
        if (healthPasswordInput) { healthPasswordInput.value = ''; healthPasswordInput.placeholder = 'Password'; }
        if (healthAdminBadge) { healthAdminBadge.textContent = 'NOT SET'; healthAdminBadge.className = 'badge rounded-pill bg-secondary sys-badge-sm'; }
        if (healthAdminMsg) healthAdminMsg.style.display = 'none';
        if (btnDeleteHealth) btnDeleteHealth.style.display = 'none';
        if (healthDiag) healthDiag.style.display = 'none';
        setFieldStatus(form.pwStatus, 'NOT SET', 'optional');
        setFieldStatus(document.getElementById('domainHostStatus'), 'REQ', '');
        setFieldStatus(document.getElementById('domainBindStatus'), 'REQ', '');
        setFieldStatus(document.getElementById('domainUserLookupStatus'), 'OPTIONAL', 'optional');
        var userResult = document.getElementById('domainTestUserResult');
        if (userResult) {
            userResult.classList.add('sys-hidden');
            userResult.innerHTML = '';
        }
        var userInput = document.getElementById('domainTestUsername');
        if (userInput) userInput.value = '';
        hideTestFeedback();
        setTestStatus('NOT TESTED', 'bg-secondary');
    }

    function openForm(mode, data) {
        hideFeedback();
        clearForm();
        if (mode === 'add') {
            document.getElementById('domainFormTitleText').textContent = 'Add Domain';
            btnSave.innerHTML = '<i class="fas fa-save me-1"></i>Save Domain';
            form.keyInput.disabled = false;
        } else if (mode === 'edit' && data) {
            document.getElementById('domainFormTitleText').textContent = 'Edit Domain';
            btnSave.innerHTML = '<i class="fas fa-save me-1"></i>Update Domain';
            form.key.value = data.key;
            form.keyInput.value = data.key;
            form.keyInput.disabled = true;
            form.label.value = data.label || '';
            form.host.value = data.host || '';
            form.ip.value = data.ip || '';
            form.port.value = data.port || '389';
            form.useTls.checked = !!data.use_tls;
            form.baseDn.value = data.base_dn || '';
            form.userSearchBase.value = data.user_search_base || '';
            form.bindDn.value = data.bind_dn || '';
            if (data.bind_password_stored) {
                form.bindPassword.value = '••••••••';
                setFieldStatus(form.pwStatus, 'STORED', 'ok');
            }
            var ex = data.exchange || {};
            var exEnabled = ex.enabled !== false;
            form.exEnabled.checked = exEnabled;
            form.exServer.value = ex.server_override || '';
            form.exUri.value = ex.ps_uri_override || '';
            form.exUseHttps.checked = ex.ps_use_https !== false;
            form.exUsername.value = ex.ps_username || '';
            if (ex.ps_password_stored) {
                form.exPassword.value = '••••••••';
            }

            // Credential mode: if ps_username set → override, else → bind
            var credMode = (ex.ps_username && ex.ps_username.trim() !== '') ? 'override' : 'bind';
            form.exCredMode.value = credMode;

            // Show/hide sections based on enabled and credential mode
            document.getElementById('exchangeDisabledMsg').style.display = exEnabled ? 'none' : 'block';
            document.getElementById('exchangeConfigFields').style.display = exEnabled ? 'block' : 'none';
            document.getElementById('exBindModeFields').style.display = (exEnabled && credMode === 'bind') ? 'block' : 'none';
            document.getElementById('exOverrideModeFields').style.display = (exEnabled && credMode === 'override') ? 'block' : 'none';

            // Populate bind mode info
            var bindUser = document.getElementById('exBindUser');
            if (bindUser) bindUser.textContent = data.bind_dn || 'LDAP bind DN';
            var bindSrv = document.getElementById('exBindServer');
            if (bindSrv) bindSrv.textContent = ex.server_override || 'Auto-discovered via LDAP';
            var bindUri = document.getElementById('exBindUri');
            if (bindUri) bindUri.textContent = ex.ps_uri_override || 'Auto-built from server + port';
            var bindPort = document.getElementById('exBindPort');
            if (bindPort) bindPort.textContent = ex.ps_use_https !== false ? '5986 (HTTPS)' : '5985 (HTTP)';

            // Show resolved fallback values
            var srvRes = document.getElementById('exServerResolved');
            if (srvRes) {
                if (ex.server_override) {
                    srvRes.textContent = 'Override: ' + ex.server_override;
                } else {
                    srvRes.textContent = 'Auto-discover from LDAP';
                }
            }
            var usrRes = document.getElementById('exUsernameResolved');
            if (usrRes) {
                if (ex.ps_username) {
                    usrRes.textContent = 'Override: ' + ex.ps_username;
                } else if (data.bind_dn) {
                    usrRes.textContent = 'Falls back to LDAP bind: ' + data.bind_dn;
                }
            }

            // Show resolved config info
            var exResInfo = document.getElementById('exResolvedInfo');
            if (exResInfo) {
                var isOverridden = !!ex.ps_uri_override;
                if (isOverridden) {
                    document.getElementById('exResolvedUri').textContent = ex.ps_uri_override;
                } else {
                    document.getElementById('exResolvedUri').textContent = 'Auto-built from server + port';
                }
                if (ex.ps_uri_override) {
                    var uriPort = ex.ps_uri_override.match(/:(\d+)\//);
                    document.getElementById('exResolvedPort').textContent = uriPort ? uriPort[1] : '80 (from URI)';
                } else {
                    document.getElementById('exResolvedPort').textContent = form.exUseHttps.checked ? '5986 (HTTPS)' : '5985 (HTTP)';
                }
                var hasExplicitCreds = credMode === 'override' && ex.ps_password_stored;
                document.getElementById('exResolvedAuth').textContent = hasExplicitCreds ? 'Exchange credentials' : 'LDAP bind user';
                document.getElementById('exResolvedServer').textContent = ex.server_override || 'LDAP auto-discovery';
                exResInfo.style.display = 'block';
            }

            if (form.exBadge) {
                var exConfigured = !!(ex.enabled !== false && (ex.ps_uri_override || ex.ps_password_stored || ex.server_override));
                form.exBadge.textContent = exConfigured ? 'CONFIGURED' : 'NOT CONFIGURED';
                form.exBadge.className = 'badge rounded-pill ' + (exConfigured ? 'bg-success' : 'bg-secondary') + ' sys-badge-sm';
            }
        }
        form.inlineForm.classList.remove('sys-hidden');
        form.inlineForm.classList.add('sys-fade');
        form.inlineForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        form.keyInput.focus();
    }

    function closeForm() {
        form.inlineForm.classList.add('sys-hidden');
        hideFeedback();
        hideTestFeedback();
    }

    function testConnection() {
        var h = form.host.value.trim();
        if (!h) { testFeedback('Host is required.', 'error'); return; }

        btnTest.disabled = true;
        btnTest.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Testing...';
        setTestStatus('TESTING', 'bg-warning');
        hideTestFeedback();

        var isEdit = form.keyInput.disabled;
        var currentKey = form.key.value || form.keyInput.value.trim();
        var password = form.bindPassword.value;
        var url;
        if (isEdit && currentKey && (!password || password === '••••••••')) {
            url = apiBase + '?endpoint=domain_api&action=test_domain&key=' + encodeURIComponent(currentKey);
        } else {
            var params = new URLSearchParams({
                endpoint: 'system_config_api',
                action: 'ldap_test_connect',
                ldap_host: h,
                ldap_port: String(parseInt(form.port.value) || 389),
                ldap_use_tls: form.useTls.checked ? '1' : '0',
                ldap_base_dn: form.baseDn.value.trim(),
                ldap_bind_dn: form.bindDn.value.trim()
            });
            if (password && password !== '••••••••') params.set('ldap_bind_password', password);
            url = apiBase + '?' + params.toString();
        }

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btnTest.disabled = false;
            btnTest.innerHTML = '<i class="fas fa-plug me-1"></i>Test Connection';
            if (res.ldap) res = res.ldap;
            if (res.success) {
                setTestStatus('OK ' + (res.latency_ms || '0') + 'ms', 'bg-success');
                setFieldStatus(document.getElementById('domainHostStatus'), 'OK', 'ok');
                setFieldStatus(document.getElementById('domainBindStatus'), 'OK', 'ok');
                var d = res.resolved_ip ? 'Resolved: ' + res.resolved_ip + '. ' : '';
                testFeedback(d + 'Connection OK. You can now test an AD username below.', 'success');
                var userInput = document.getElementById('domainTestUsername');
                if (userInput) userInput.focus();
            } else {
                setTestStatus('FAILED', 'bg-danger');
                setFieldStatus(document.getElementById('domainHostStatus'), 'FAIL', '');
                setFieldStatus(document.getElementById('domainBindStatus'), 'FAIL', '');
                testFeedback(res.message || 'Connection failed.', 'error');
            }
        })
        .catch(function() {
            btnTest.disabled = false;
            btnTest.innerHTML = '<i class="fas fa-plug me-1"></i>Test Connection';
            setTestStatus('ERROR', 'bg-danger');
            testFeedback('Network error.', 'error');
        });
    }

    function testUserLookup() {
        var usernameInput = document.getElementById('domainTestUsername');
        var resultBox = document.getElementById('domainTestUserResult');
        var status = document.getElementById('domainUserLookupStatus');
        var username = usernameInput ? usernameInput.value.trim() : '';
        if (!username) {
            setFieldStatus(status, 'REQ', '');
            if (resultBox) {
                resultBox.classList.remove('sys-hidden');
                resultBox.innerHTML = '<span class="text-warning">Enter an AD username first.</span>';
            }
            return;
        }

        btnTestUser.disabled = true;
        btnTestUser.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Lookup';
        setFieldStatus(status, 'CHECK', 'optional');
        if (resultBox) {
            resultBox.classList.remove('sys-hidden');
            resultBox.innerHTML = '<span class="text-muted">Looking up user...</span>';
        }

        var isEdit = form.keyInput.disabled;
        var currentKey = form.key.value || form.keyInput.value.trim();
        var lookupUrl = (isEdit && currentKey)
            ? apiBase + '?endpoint=domain_api&action=test_user&key=' + encodeURIComponent(currentKey) + '&username=' + encodeURIComponent(username)
            : apiBase + '?endpoint=system_config_api&action=ldap_test_user&username=' + encodeURIComponent(username);

        fetch(lookupUrl, {
            method: 'GET',
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btnTestUser.disabled = false;
            btnTestUser.innerHTML = '<i class="fas fa-search me-1"></i>Lookup';
            if (res.success && res.user) {
                var display = res.user.DisplayName || res.user.displayName || username;
                var sam = res.user.SamAccountName || res.user.sAMAccountName || username;
                var mail = res.user.EmailAddress || res.user.mail || '';
                setFieldStatus(status, 'FOUND', 'ok');
                resultBox.innerHTML = '<strong class="text-success">Found:</strong> <code>' + escapeHtml(sam) + '</code> - ' + escapeHtml(display) + (mail ? ' <span class="text-muted">(' + escapeHtml(mail) + ')</span>' : '');
            } else {
                setFieldStatus(status, 'MISS', '');
                resultBox.innerHTML = '<span class="text-danger">' + escapeHtml(res.message || 'User not found.') + '</span>';
            }
        })
        .catch(function() {
            btnTestUser.disabled = false;
            btnTestUser.innerHTML = '<i class="fas fa-search me-1"></i>Lookup';
            setFieldStatus(status, 'ERROR', '');
            if (resultBox) resultBox.innerHTML = '<span class="text-danger">Network error.</span>';
        });
    }

    function testExchangeConnection() {
        btnTestExchange.disabled = true;
        btnTestExchange.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Testing...';
        testFeedback('Running Exchange diagnostic...', 'warn');

        var diagDiv = document.getElementById('exDiagResult');

        // Pass current form values so test works even before saving
        var override = {
            enabled: true,
            server_override: form.exServer.value.trim(),
            ps_uri_override: form.exUri.value.trim(),
            ps_use_https: form.exUseHttps.checked,
            ps_username: form.exUsername.value.trim(),
            ps_password: form.exPassword.value.trim(),
            cred_mode: form.exCredMode.value
        };

        fetch(apiBase + '?endpoint=exchange', {
            method: 'POST',
            headers: apiHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'exchange_diagnostic_test', config_override: override })
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btnTestExchange.disabled = false;
            btnTestExchange.innerHTML = '<i class="fas fa-plug me-1"></i>Test Exchange';

            var diag = res.diagnostic || {};
            var html = '';
            var statusClass = res.success ? 'bg-success' : 'bg-danger';
            var statusText = res.success ? '<i class=\"fas fa-check\"></i> Exchange OK' : '<i class=\"fas fa-times\"></i> Exchange Failed';

            html += '<div class=\"p-2 rounded\" style=\"background:var(--bg-muted);border:1px solid ' + (res.success ? 'var(--success-color,#198754)' : 'var(--danger-color,#dc3545)') + ';\">';
            html += '<div class=\"d-flex align-items-center gap-2 mb-2\"><span class=\"badge rounded-pill ' + statusClass + '\">' + statusText + '</span></div>';

            // Connection details
            html += '<table style=\"width:100%;border-collapse:collapse;\">';
            html += '<tr><td style=\"padding:2px 6px;font-weight:600;width:30%;\">Server</td><td style=\"padding:2px 6px;\">' + (diag.server || '—') + '</td></tr>';
            html += '<tr><td style=\"padding:2px 6px;font-weight:600;\">PS URI</td><td style=\"padding:2px 6px;\">' + (diag.uri || '—') + ' <small class=\"text-muted\">(' + (diag.uri_source || '?') + ')</small></td></tr>';
            html += '<tr><td style=\"padding:2px 6px;font-weight:600;\">Port</td><td style=\"padding:2px 6px;\">' + (diag.port || '—') + '</td></tr>';
            html += '<tr><td style=\"padding:2px 6px;font-weight:600;\">Auth</td><td style=\"padding:2px 6px;\">' + (diag.credential_mode === 'custom_account' ? 'Custom Exchange account' : 'LDAP bind user') + '</td></tr>';
            html += '<tr><td style=\"padding:2px 6px;font-weight:600;\">Effective User</td><td style=\"padding:2px 6px;\">' + (diag.effective_user || '—') + '</td></tr>';
            html += '</table>';

            // Cmdlet test result
            var cmd = diag.cmdlet_test || {};
            if (cmd.message) {
                html += '<div class=\"mt-1 p-1 rounded\" style=\"background:' + (cmd.success ? 'rgba(25,135,84,0.1)' : 'rgba(220,53,69,0.1)') + ';padding:4px 8px;\">';
                html += '<strong>Cmdlet test:</strong> ' + (cmd.success ? '<span class=\"text-success\">' : '<span class=\"text-danger\">') + (cmd.message || '') + '</span>';
                if (cmd.output) html += ' <small class=\"text-muted\">(' + cmd.output + ')</small>';
                html += '</div>';
            }

            // Issues & suggestions
            var issues = diag.issues || [];
            var suggestions = diag.suggestions || [];
            if (issues.length > 0) {
                html += '<div class=\"mt-1\"><strong style=\"color:#dc3545;\">Issues:</strong></div>';
                html += '<ul class=\"mb-0\" style=\"margin:0;padding-left:16px;\">';
                issues.forEach(function(issue) {
                    html += '<li style=\"color:#dc3545;\">' + issue + '</li>';
                });
                html += '</ul>';
            }
            if (suggestions.length > 0) {
                html += '<div class=\"mt-1\"><strong style=\"color:#0d6efd;\">Suggestions:</strong></div>';
                html += '<ul class=\"mb-0\" style=\"margin:0;padding-left:16px;\">';
                suggestions.forEach(function(s) {
                    html += '<li style=\"color:#0d6efd;\">' + s + '</li>';
                });
                html += '</ul>';
            }
            html += '</div>';

            if (diagDiv) {
                diagDiv.innerHTML = html;
                diagDiv.style.display = 'block';
            }

            // Update badge
            if (form.exBadge) {
                form.exBadge.textContent = res.success ? 'ONLINE' : 'FAILED';
                form.exBadge.className = 'badge rounded-pill ' + (res.success ? 'bg-success' : 'bg-danger') + ' sys-badge-sm';
            }
            testFeedback(res.message || (res.success ? 'Exchange OK' : 'Exchange test failed.'), res.success ? 'success' : 'error');
        })
        .catch(function(err) {
            btnTestExchange.disabled = false;
            btnTestExchange.innerHTML = '<i class="fas fa-plug me-1"></i>Test Exchange';
            testFeedback('Exchange test network error: ' + err.message, 'error');
            if (diagDiv) { diagDiv.style.display = 'none'; }
        });
    }

    function resolveHost() {
        var h = form.host.value.trim();
        if (!h) { testFeedback('Enter a hostname first.', 'warn'); return; }
        btnResolve.disabled = true;
        btnResolve.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        fetch(apiBase + '?endpoint=domain_api&action=resolve_host', {
            method: 'POST',
            headers: apiHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ host: h })
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btnResolve.disabled = false;
            btnResolve.innerHTML = '<i class="fas fa-globe"></i>';
            if (res.success && res.ip) {
                form.ip.value = res.ip;
                testFeedback('Resolved: ' + h + ' → ' + res.ip, 'success');
            } else {
                testFeedback(res.message || 'Resolution failed.', 'error');
            }
        })
        .catch(function() {
            btnResolve.disabled = false;
            btnResolve.innerHTML = '<i class="fas fa-globe"></i>';
            testFeedback('Network error.', 'error');
        });
    }

    function reverseResolve() {
        var ip = form.ip.value.trim();
        if (!ip) return;
        fetch(apiBase + '?endpoint=domain_api&action=reverse_resolve', {
            method: 'POST',
            headers: apiHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ ip: ip })
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success && res.hostname) {
                form.host.value = res.hostname;
            }
        })
        .catch(function() {});
    }

    function debounceAutoResolve(fn) {
        if (resolveTimer) clearTimeout(resolveTimer);
        resolveTimer = setTimeout(fn, 400);
    }

    document.getElementById('btnAddDomain').addEventListener('click', function() { openForm('add'); });
    if (btnTestExchange) btnTestExchange.addEventListener('click', testExchangeConnection);

    document.querySelectorAll('.domain-edit-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var key = this.closest('tr').getAttribute('data-key');
            fetch(apiBase + '?endpoint=domain_api&action=list_domains')
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        var domain = null;
                        res.domains.forEach(function(d) { if (d.key === key) domain = d; });
                        if (domain) { openForm('edit', domain); }
                        else { alert('Domain not found.'); }
                    }
                });
        });
    });

    document.querySelectorAll('.domain-test-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var key = this.getAttribute('data-key');
            var isAuto = this.dataset.autoTest === '1';
            this.dataset.autoTest = '';
            var row = this.closest('tr');
            var badge = row ? row.querySelector('.domain-conn-badge') : null;
            var latency = row ? row.querySelector('.domain-latency') : null;
            var lastTest = row ? row.querySelector('.domain-lasttest') : null;
            var original = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            if (badge) {
                badge.textContent = 'TESTING';
                badge.className = 'domain-conn-badge badge rounded-pill bg-warning sys-badge-sm';
            }
            fetch(apiBase + '?endpoint=domain_api&action=test_domain&key=' + encodeURIComponent(key), {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (badge) {
                    var statusText = 'ONLINE';
                    var statusClass = 'bg-success';
                    if (!res.success) {
                        if (res.message && res.message.indexOf('Bind password is not stored') !== -1) {
                            statusText = 'NO PWD';
                            statusClass = 'bg-warning text-dark';
                        } else {
                            statusText = 'FAILED';
                            statusClass = 'bg-danger';
                        }
                    }
                    badge.textContent = statusText;
                    badge.className = 'domain-conn-badge badge rounded-pill ' + statusClass + ' sys-badge-sm';
                }
                if (latency) latency.textContent = typeof res.latency_ms === 'number' ? res.latency_ms + 'ms' : '-';
                if (lastTest) lastTest.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                if (!isAuto && !res.success && res.message) alert(res.message);
            })
            .catch(function() {
                if (badge) {
                    badge.textContent = 'ERROR';
                    badge.className = 'domain-conn-badge badge rounded-pill bg-danger sys-badge-sm';
                }
                if (!isAuto) alert('Network error.');
            })
            .finally(function() {
                btn.disabled = false;
                btn.innerHTML = original;
            });
        });
    });

    setTimeout(function() {
        document.querySelectorAll('.domain-test-btn').forEach(function(btn, index) {
            setTimeout(function() {
                btn.dataset.autoTest = '1';
                btn.click();
            }, index * 450);
        });
    }, 600);

    var domainSwitchModal = null;
    var domainSwitchKey = '';
    var domainActionType = 'switch';

    (function() {
        var el = document.getElementById('domainSwitchModal');
        if (el && el.parentElement !== document.body) {
            document.body.appendChild(el);
        }
        if (el && typeof bootstrap !== 'undefined') {
            domainSwitchModal = bootstrap.Modal.getOrCreateInstance(el);
        }
    })();

    function resetDomainModalUI() {
        document.getElementById('domainSwitchDesc').innerHTML = 'Switch active domain to <strong id="domainSwitchTargetKey"></strong>?';
        var note = document.querySelector('#domainSwitchModal .modal-body p:nth-child(2)');
        if (note) note.textContent = 'All LDAP operations will use this domain. The page will reload to apply the change.';
        var icon = document.querySelector('#domainSwitchModal .modal-title i');
        if (icon) { icon.className = 'fas fa-exchange-alt me-2'; icon.style.color = '#f59e0b'; }
        var titleEl = document.querySelector('#domainSwitchModal .modal-title');
        if (titleEl) titleEl.childNodes[1].textContent = ' Switch Active Domain';
        document.getElementById('btnConfirmDomainSwitch').className = 'btn btn-warning flex-fill';
        document.getElementById('btnConfirmDomainSwitch').innerHTML = '<i class="fas fa-exchange-alt me-1"></i>Switch Now';
        document.getElementById('domainSwitchFeedback').classList.add('sys-hidden');
        domainActionType = 'switch';
    }

    document.querySelectorAll('.domain-switch-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            domainSwitchKey = this.getAttribute('data-key');
            resetDomainModalUI();
            document.getElementById('domainSwitchTargetKey').textContent = domainSwitchKey;
            var el = document.getElementById('domainSwitchModal');
            if (!domainSwitchModal && el) {
                if (el.parentElement !== document.body) document.body.appendChild(el);
                domainSwitchModal = bootstrap.Modal.getOrCreateInstance(el);
            } else if (el && el.parentElement !== document.body) {
                document.body.appendChild(el);
            }
            if (domainSwitchModal) domainSwitchModal.show();
        });
    });

    document.querySelectorAll('.domain-delete-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            domainSwitchKey = this.getAttribute('data-key');
            resetDomainModalUI();
            document.getElementById('domainSwitchTargetKey').textContent = domainSwitchKey;
            document.getElementById('domainSwitchDesc').innerHTML = 'Permanently delete domain <strong>' + domainSwitchKey + '</strong>?';
            var note = document.querySelector('#domainSwitchModal .modal-body p:nth-child(2)');
            if (note) note.textContent = 'All saved credentials for this domain will be removed. This action cannot be undone.';
            var icon = document.querySelector('#domainSwitchModal .modal-title i');
            if (icon) { icon.className = 'fas fa-trash-alt me-2'; icon.style.color = '#ef4444'; }
            var titleEl = document.querySelector('#domainSwitchModal .modal-title');
            if (titleEl) titleEl.childNodes[1].textContent = ' Delete Domain';
            document.getElementById('btnConfirmDomainSwitch').className = 'btn btn-danger flex-fill';
            document.getElementById('btnConfirmDomainSwitch').innerHTML = '<i class="fas fa-trash-alt me-1"></i>Delete Now';
            domainActionType = 'delete';
            var el = document.getElementById('domainSwitchModal');
            if (!domainSwitchModal && el) {
                if (el.parentElement !== document.body) document.body.appendChild(el);
                domainSwitchModal = bootstrap.Modal.getOrCreateInstance(el);
            } else if (el && el.parentElement !== document.body) {
                document.body.appendChild(el);
            }
            if (domainSwitchModal) domainSwitchModal.show();
        });
    });

    document.getElementById('btnConfirmDomainSwitch').addEventListener('click', function() {
        var key = domainSwitchKey;
        if (!key) return;
        var action = domainActionType === 'delete' ? 'delete_domain' : 'switch_domain';
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + (action === 'delete_domain' ? 'Deleting...' : 'Switching...');
        fetch(apiBase + '?endpoint=domain_api&action=' + action, {
            method: 'POST',
            headers: apiHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ key: key })
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) { window.location.reload(); }
            else {
                btn.disabled = false;
                btn.innerHTML = action === 'delete_domain'
                    ? '<i class="fas fa-trash-alt me-1"></i>Delete Now'
                    : '<i class="fas fa-exchange-alt me-1"></i>Switch Now';
                var fb = document.getElementById('domainSwitchFeedback');
                fb.textContent = res.message || 'Action failed.';
                fb.className = 'sys-inline-feedback is-error';
                fb.classList.remove('sys-hidden');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = action === 'delete_domain'
                ? '<i class="fas fa-trash-alt me-1"></i>Delete Now'
                : '<i class="fas fa-exchange-alt me-1"></i>Switch Now';
            var fb = document.getElementById('domainSwitchFeedback');
            fb.textContent = 'Network error.';
            fb.className = 'sys-inline-feedback is-error';
            fb.classList.remove('sys-hidden');
        });
    });

    document.getElementById('domainSwitchModal')?.addEventListener('hidden.bs.modal', function() {
        domainSwitchKey = '';
        document.getElementById('btnConfirmDomainSwitch').disabled = false;
        resetDomainModalUI();
    });
    btnCancel.addEventListener('click', closeForm);
    btnTest.addEventListener('click', testConnection);
    if (btnTestUser) btnTestUser.addEventListener('click', testUserLookup);
    document.getElementById('domainTestUsername')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            testUserLookup();
        }
    });
    btnResolve.addEventListener('click', resolveHost);

    // Exchange enable toggle
    form.exEnabled.addEventListener('change', function() {
        var enabled = this.checked;
        var mode = form.exCredMode.value;
        document.getElementById('exchangeDisabledMsg').style.display = enabled ? 'none' : 'block';
        document.getElementById('exchangeConfigFields').style.display = enabled ? 'block' : 'none';
        if (enabled) {
            document.getElementById('exBindModeFields').style.display = mode === 'bind' ? 'block' : 'none';
            document.getElementById('exOverrideModeFields').style.display = mode === 'override' ? 'block' : 'none';
        } else {
            document.getElementById('exBindModeFields').style.display = 'none';
            document.getElementById('exOverrideModeFields').style.display = 'none';
        }
    });

    // Exchange credential mode toggle
    form.exCredMode.addEventListener('change', function() {
        var enabled = form.exEnabled.checked;
        if (enabled) {
            document.getElementById('exBindModeFields').style.display = this.value === 'bind' ? 'block' : 'none';
            document.getElementById('exOverrideModeFields').style.display = this.value === 'override' ? 'block' : 'none';
        }
    });

    // Info panel toggle
    document.getElementById('btnExchangeInfo').addEventListener('click', function() {
        var panel = document.getElementById('exchangeInfoPanel');
        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    });
    document.getElementById('btnExchangeInfoClose').addEventListener('click', function() {
        document.getElementById('exchangeInfoPanel').style.display = 'none';
    });

    form.host.addEventListener('blur', function() {
        var host = this.value.trim();
        var ip = form.ip.value.trim();
        if (host && !ip) {
            fetch(apiBase + '?endpoint=domain_api&action=resolve_host', {
                method: 'POST',
                headers: apiHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ host: host })
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success && res.ip) {
                    form.ip.value = res.ip;
                }
            })
            .catch(function() {});
        }
    });

    form.ip.addEventListener('blur', function() {
        var ip = this.value.trim();
        var host = form.host.value.trim();
        if (ip && !host) {
            debounceAutoResolve(reverseResolve);
        }
    });

    btnSave.addEventListener('click', function() {
        var isEdit = form.keyInput.disabled;
        var key = isEdit ? form.key.value : form.keyInput.value.trim();
        if (!key) { feedback('Domain key is required.', 'error'); return; }

        var payload = {
            key: key,
            label: form.label.value.trim(),
            host: form.host.value.trim(),
            ip: form.ip.value.trim(),
            port: parseInt(form.port.value) || 389,
            use_tls: form.useTls.checked,
            base_dn: form.baseDn.value.trim(),
            user_search_base: form.userSearchBase.value.trim(),
            bind_dn: form.bindDn.value.trim(),
            backend: (document.getElementById('ldapBackendModeInput') || {}).value || 'ldap',
            exchange: {
                enabled: form.exEnabled.checked,
                server_override: form.exServer.value.trim(),
                ps_uri_override: form.exUri.value.trim(),
                ps_use_https: form.exUseHttps.checked,
                ps_username: form.exUsername.value.trim()
            },
        };

        // If credential mode is "bind", don't save username/password — use LDAP bind fallback
        if (form.exCredMode.value === 'bind') {
            payload.exchange.ps_username = '';
            payload.exchange.ps_password = '';
        }

        if (form.bindPassword.value.trim() && form.bindPassword.value.trim() !== '••••••••') {
            payload.bind_password = form.bindPassword.value.trim();
        }
        if (form.exPassword.value.trim() && form.exPassword.value.trim() !== '••••••••') {
            payload.exchange.ps_password = form.exPassword.value.trim();
        }

        var action = isEdit ? 'update_domain' : 'add_domain';
        btnSave.disabled = true;
        btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

        fetch(apiBase + '?endpoint=domain_api&action=' + action, {
            method: 'POST',
            headers: apiHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="fas fa-save me-1"></i>' + (isEdit ? 'Update Domain' : 'Save Domain');
            if (res.success) {
                feedback(res.message + ' Reloading...', 'success');
                setTimeout(function() { window.location.reload(); }, 800);
            } else {
                feedback(res.message, 'error');
            }
        })
        .catch(function() {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="fas fa-save me-1"></i>' + (isEdit ? 'Update Domain' : 'Save Domain');
            feedback('Network error.', 'error');
        });
    });

    // ─── AD Objects Tab: User Properties Configuration ─────────────────────
    var ADO = {
        domainSelector: document.getElementById('adoDomainSelector'),
        toggle: document.getElementById('adoCustomToggle'),
        defaultHint: document.getElementById('adoDefaultHint'),
        customFields: document.getElementById('adoCustomFields'),
        fields: {
            mode: document.getElementById('adoNamingMode'),
            exclude: document.getElementById('adoExcludePrefixes'),
            caseField: document.getElementById('adoNamingCase'),
            sep: document.getElementById('adoNamingSeparator'),
            surnameMode: document.getElementById('adoSurnameMode'),
            givenNameMode: document.getElementById('adoGivenNameMode'),
            displayNameFormat: document.getElementById('adoDisplayNameFormat'),
            upnSuffix: document.getElementById('adoUpnSuffix'),
        },
        preview: {
            sam: document.getElementById('adoPreviewSam'),
            upn: document.getElementById('adoPreviewUpn'),
            givenName: document.getElementById('adoPreviewGivenName'),
            sn: document.getElementById('adoPreviewSn'),
            displayName: document.getElementById('adoPreviewDisplayName'),
            cn: document.getElementById('adoPreviewCn'),
        },
        feedback: document.getElementById('adoFeedback'),
        btnSave: document.getElementById('btnSaveAdoConfig'),
        btnReset: document.getElementById('btnResetAdoForm'),
        previewName: 'John A. Doe',
        previewCode: '12345',
        selectedDomain: null,
        selectedDomainKey: null,
    };

    function adoDefaultConfig() {
        return {
            mode: 'emp_code',
            exclude_prefixes: [],
            case: 'lowercase',
            separator: '',
            surname_mode: 'last_part',
            given_name_mode: 'first_non_prefix',
            display_name_format: 'original',
            upn_suffix: '',
        };
    }

    function adoGetParts(name, exclude) {
        var ex = (exclude || []).map(function(s){ return s.trim().toLowerCase(); });
        var parts = name.split(/[\s.]+/).map(function(s){ return s.trim(); }).filter(Boolean);
        var filtered = [];
        for (var i = 0; i < parts.length; i++) {
            if (ex.indexOf(parts[i].toLowerCase()) !== -1) continue;
            filtered.push(parts[i]);
        }
        if (filtered.length === 0) filtered = parts;
        return { all: parts, filtered: filtered };
    }

    function adoExtractNamePart(filtered, all, mode, code) {
        if (mode === 'emp_code') return code || '';
        if (mode === 'emp_code_idx0_idx1') return ((code || '') + ' ' + (filtered[0] || '') + ' ' + (filtered[1] || '')).trim();
        var arr = mode.indexOf('index:') === 0 ? all : filtered;
        if (mode === 'first_non_prefix') return filtered[0] || all[0] || '';
        if (mode === 'first_part') return all[0] || '';
        if (mode === 'last_non_prefix') return filtered[filtered.length - 1] || all[all.length - 1] || '';
        if (mode === 'last_part') return all[all.length - 1] || '';
        var idxMatch = mode.match(/^index:(\d+)$/);
        if (idxMatch) { var idx = parseInt(idxMatch[1], 10); return arr[idx] || arr[0] || ''; }
        return filtered[0] || all[0] || '';
    }

    function adoGetNamingConfig() {
        if (!ADO.toggle.checked) return adoDefaultConfig();
        var raw = (ADO.fields.exclude.value || '').trim();
        var prefixes = raw ? raw.split(',').map(function(s){ return s.trim(); }).filter(Boolean) : [];
        return {
            mode: ADO.fields.mode.value || 'emp_code',
            exclude_prefixes: prefixes,
            case: ADO.fields.caseField.value || 'lowercase',
            separator: (ADO.fields.sep.value || '').trim(),
            surname_mode: ADO.fields.surnameMode.value || 'last_part',
            given_name_mode: ADO.fields.givenNameMode ? ADO.fields.givenNameMode.value : 'first_non_prefix',
            display_name_format: ADO.fields.displayNameFormat ? ADO.fields.displayNameFormat.value : 'original',
            upn_suffix: (ADO.fields.upnSuffix ? ADO.fields.upnSuffix.value : '').trim(),
        };
    }

    function adoParsePreview(name, code, cfg) {
        var parts = adoGetParts(name, cfg.exclude_prefixes || []);
        var all = parts.all, filtered = parts.filtered;

        var givenName = adoExtractNamePart(filtered, all, cfg.given_name_mode || 'first_non_prefix', code);
        var surname = adoExtractNamePart(filtered, all, cfg.surname_mode || 'last_part', code);

        var displayName;
        if ((cfg.display_name_format || 'original') === 'first_last') {
            displayName = (givenName + ' ' + surname).trim();
        } else if (cfg.display_name_format === 'last_first') {
            displayName = (surname + ', ' + givenName).trim();
        } else {
            displayName = name;
        }

        var samMode = cfg.mode || 'emp_code';
        var sam;
        if (samMode === 'emp_code') {
            sam = code;
        } else {
            var base;
            switch (samMode) {
                case 'first_non_prefix_id': base = filtered[0]; break;
                case 'last_name_id': base = filtered[filtered.length - 1]; break;
                case 'full_name_slug_id': base = filtered.join(cfg.separator || ''); break;
                default:
                    var idxMatch = samMode.match(/^index:(\d+)_id$/);
                    if (idxMatch) { var idx = parseInt(idxMatch[1], 10); base = filtered[idx] || filtered[0]; }
                    else base = code;
            }
            var cas = cfg.case || 'lowercase';
            if (cas === 'uppercase') base = base.toUpperCase();
            else if (cas === 'lowercase') base = base.toLowerCase();
            sam = base + code;
        }
        return { givenName: givenName, surname: surname, sam: sam, displayName: displayName };
    }

    function adoUpdatePreview() {
        var cfg = adoGetNamingConfig();
        var result = adoParsePreview(ADO.previewName, ADO.previewCode, cfg);
        var domainHint = (ADO.selectedDomain && adoDisplayName(ADO.selectedDomain)) || '';
        var upnSuffix = cfg.upn_suffix || domainHint || 'domain.local';
        if (ADO.preview.sam) ADO.preview.sam.textContent = result.sam;
        if (ADO.preview.upn) ADO.preview.upn.textContent = result.sam + '@' + upnSuffix;
        if (ADO.preview.givenName) ADO.preview.givenName.textContent = result.givenName;
        if (ADO.preview.sn) ADO.preview.sn.textContent = result.surname;
        if (ADO.preview.displayName) {
            ADO.preview.displayName.textContent = result.displayName;
            if (result.displayName === ADO.previewName) {
                ADO.preview.displayName.innerHTML = result.displayName + ' <span class="text-muted">(HRMS)</span>';
            }
        }
        if (ADO.preview.cn) {
            ADO.preview.cn.textContent = result.displayName;
            if (result.displayName === ADO.previewName) {
                ADO.preview.cn.innerHTML = result.displayName + ' <span class="text-muted">(HRMS)</span>';
            }
        }

        // Populate HRMS → AD field mapping
        var mappingContainer = document.getElementById('adoMappingRows');
        if (!mappingContainer) return;
        var samModeLabel = cfg.mode || 'emp_code';
        var displayFormatLabel = cfg.display_name_format || 'original';
        var mappings = [
            { api: 'EMP_CODE', ad: 'sAMAccountName (Logon Name)', config: samModeLabel, val: result.sam },
            { api: 'EMP_CODE', ad: 'userPrincipalName (UPN)', config: cfg.upn_suffix || 'auto', val: result.sam + '@' + upnSuffix },
            { api: 'EMP_NAME', ad: 'givenName (First Name)', config: cfg.given_name_mode || 'first_non_prefix', val: result.givenName },
            { api: 'EMP_NAME', ad: 'sn (Last Name)', config: cfg.surname_mode || 'last_part', val: result.surname },
            { api: 'EMP_NAME', ad: 'displayName (Display Name)', config: displayFormatLabel, val: result.displayName },
            { api: 'EMP_NAME', ad: 'cn (Full Name)', config: displayFormatLabel, val: result.displayName },
            { api: 'EMAIL', ad: 'mail', config: 'fixed', val: 'user@domain.com' },
            { api: 'MOBILE', ad: 'mobilePhone', config: 'fixed', val: '+880XXXXXXXXX' },
            { api: 'DESIGNATION', ad: 'title', config: 'fixed', val: 'Job Title' },
            { api: 'DEPARTMENT_TITLE', ad: 'department', config: 'fixed', val: 'Department' },
            { api: 'OPERATING_UNIT_TITLE', ad: 'company', config: 'fixed', val: 'Company' },
            { api: 'LOCATION_TITLE', ad: 'office', config: 'fixed', val: 'Office Location' },
            { api: 'RANK', ad: 'description', config: 'fixed', val: 'Rank: N | OU: ...' },
        ];
        mappingContainer.innerHTML = '';
        mappings.forEach(function(m){
            var row = document.createElement('div');
            row.className = 'd-flex align-items-center gap-2 mb-1';
            var apiSpan = document.createElement('span');
            apiSpan.style.cssText = 'width:180px;color:var(--text-soft);';
            apiSpan.textContent = m.api;
            var arrowSpan = document.createElement('span');
            arrowSpan.style.cssText = 'width:24px;text-align:center;color:var(--text-soft);';
            arrowSpan.textContent = '\u2192';
            var adSpan = document.createElement('span');
            adSpan.style.cssText = 'width:140px;font-weight:500;';
            adSpan.textContent = m.ad;
            var cfgSpan = document.createElement('span');
            cfgSpan.style.cssText = 'width:80px;text-align:center;';
            if (m.config === 'fixed') {
                cfgSpan.innerHTML = '<span class="badge bg-secondary" style="font-size:0.55rem;">fixed</span>';
            } else {
                cfgSpan.innerHTML = '<span class="badge bg-info" style="font-size:0.55rem;">' + escapeHtml(m.config) + '</span>';
            }
            var valSpan = document.createElement('span');
            valSpan.style.cssText = 'font-family:monospace;color:var(--text-soft);font-size:var(--font-xs);';
            valSpan.textContent = m.val;
            row.appendChild(apiSpan);
            row.appendChild(arrowSpan);
            row.appendChild(adSpan);
            row.appendChild(cfgSpan);
            row.appendChild(valSpan);
            mappingContainer.appendChild(row);
        });
    }

    function adoShowFeedback(msg, type) {
        ADO.feedback.textContent = msg;
        ADO.feedback.className = 'sys-inline-feedback mb-2 ' + (type === 'success' ? 'is-success' : type === 'error' ? 'is-error' : 'is-warn');
        ADO.feedback.classList.remove('sys-hidden');
    }

    function adoHideFeedback() { ADO.feedback.classList.add('sys-hidden'); }

    function adoSetMode(customizing) {
        ADO.defaultHint.classList.toggle('sys-hidden', customizing);
        ADO.customFields.classList.toggle('sys-hidden', !customizing);
        ADO.btnSave.disabled = !customizing || !ADO.selectedDomainKey;
        ADO.btnReset.disabled = !customizing || !ADO.selectedDomainKey;
    }

    function adoDisplayName(d) { return d.label || d.key || ''; }

    function adoLoadDomain(domainData) {
        ADO.selectedDomain = domainData;
        ADO.selectedDomainKey = domainData ? domainData.key : null;

        var hasNaming = domainData && domainData.naming && Object.keys(domainData.naming).length > 0;
        ADO.toggle.checked = hasNaming;

        var naming = hasNaming ? domainData.naming : adoDefaultConfig();
        if (ADO.fields.mode) ADO.fields.mode.value = naming.mode || 'emp_code';
        if (ADO.fields.exclude) ADO.fields.exclude.value = (naming.exclude_prefixes || []).join(', ');
        if (ADO.fields.caseField) ADO.fields.caseField.value = naming.case || 'lowercase';
        if (ADO.fields.sep) ADO.fields.sep.value = naming.separator || '';
        if (ADO.fields.surnameMode) ADO.fields.surnameMode.value = naming.surname_mode || 'last_part';
        if (ADO.fields.givenNameMode) ADO.fields.givenNameMode.value = naming.given_name_mode || 'first_non_prefix';
        if (ADO.fields.displayNameFormat) ADO.fields.displayNameFormat.value = naming.display_name_format || 'original';

        // Load UPN suffixes from AD + restore saved value
        adoLoadUpnSuffixes(function() {
            if (ADO.fields.upnSuffix) {
                ADO.fields.upnSuffix.value = naming.upn_suffix || '';
            }
        });

        adoSetMode(hasNaming);
        adoUpdatePreview();
        adoHideFeedback();
    }

    function adoLoadUpnSuffixes(callback) {
        var sel = ADO.fields.upnSuffix;
        if (!sel) { if (callback) callback(); return; }
        // Set loading state
        sel.innerHTML = '<option value="">Loading...</option>';
        fetch(apiBase + '?endpoint=domain_api&action=get_upn_suffixes', { method: 'GET', credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){
            sel.innerHTML = '<option value="">Auto (from AD)</option>';
            if (res.success && res.suffixes && res.suffixes.length) {
                var seen = {};
                res.suffixes.forEach(function(s){
                    if (seen[s]) return;
                    seen[s] = true;
                    var opt = document.createElement('option');
                    opt.value = s;
                    opt.textContent = s + (s === res.default ? ' (default)' : '');
                    sel.appendChild(opt);
                });
            }
            if (callback) callback();
        })
        .catch(function(){
            // Fallback: allow manual entry
            sel.outerHTML = '<input type="text" class="form-control form-control-sm" id="adoUpnSuffix" placeholder="auto (from AD)" style="font-family:var(--technical-font);">';
            ADO.fields.upnSuffix = document.getElementById('adoUpnSuffix');
            if (callback) callback();
        });
    }

    function adoPopulateDomainDropdown() {
        fetch(apiBase + '?endpoint=domain_api&action=list_domains', { method: 'GET', credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (!res.success) return;
            var sel = ADO.domainSelector;
            sel.innerHTML = '<option value="">— Select domain —</option>';
            var activeKey = res.active_key;
            var preSelected = null;
            res.domains.forEach(function(d){
                var opt = document.createElement('option');
                opt.value = d.key;
                opt.textContent = adoDisplayName(d) + (d.key === activeKey ? ' (active)' : '');
                if (d.key === activeKey) opt.selected = true;
                sel.appendChild(opt);
                if (d.key === activeKey) preSelected = d;
            });
            if (preSelected) {
                ADO.selectedDomain = preSelected;
                ADO.selectedDomainKey = preSelected.key;
                adoLoadDomain(preSelected);
                ouLoadDomain(preSelected);
                grpLoadDomain(preSelected);
            }
        })
        .catch(function(){});
    }

    function adoSaveConfig() {
        var key = ADO.selectedDomainKey;
        if (!key) { adoShowFeedback('Select a domain first.', 'error'); return; }
        if (!ADO.toggle.checked) { adoShowFeedback('Enable Customize to save.', 'warn'); return; }

        var naming = adoGetNamingConfig();

        ADO.btnSave.disabled = true;
        ADO.btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
        adoHideFeedback();

        fetch(apiBase + '?endpoint=domain_api&action=update_domain', {
            method: 'POST',
            headers: apiHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ key: key, naming: naming })
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            ADO.btnSave.disabled = false;
            ADO.btnSave.innerHTML = '<i class="fas fa-save me-1"></i>Save to Domain';
            if (res.success) {
                adoShowFeedback('Saved to <strong>' + escapeHtml(key) + '</strong>', 'success');
                if (window.__sysConfigData) window.__sysConfigData.naming = naming;
                // Update dropdown label to show configured
                for (var i = 0; i < ADO.domainSelector.options.length; i++) {
                    if (ADO.domainSelector.options[i].value === key) {
                        var txt = ADO.domainSelector.options[i].textContent.replace(' (configured)', '').replace(' (active)', '');
                        ADO.domainSelector.options[i].textContent = txt + ' (configured)';
                    }
                }
            } else {
                adoShowFeedback(res.message || 'Save failed.', 'error');
            }
        })
        .catch(function(){
            ADO.btnSave.disabled = false;
            ADO.btnSave.innerHTML = '<i class="fas fa-save me-1"></i>Save to Domain';
            adoShowFeedback('Network error.', 'error');
        });
    }

    function adoResetForm() {
        if (ADO.selectedDomainKey) {
            fetch(apiBase + '?endpoint=domain_api&action=list_domains', { method: 'GET', credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res.success) return;
                res.domains.forEach(function(d){ if (d.key === ADO.selectedDomainKey) adoLoadDomain(d); });
            })
            .catch(function(){});
        }
    }

    ADO.toggle.addEventListener('change', function(){
        var customizing = this.checked;
        adoSetMode(customizing);
        if (customizing && ADO.selectedDomain) {
            var naming = ADO.selectedDomain.naming || {};
            for (var k in ADO.fields) {
                var el = ADO.fields[k];
                if (!el) continue;
            }
        }
        adoUpdatePreview();
        adoHideFeedback();
    });

    ADO.domainSelector.addEventListener('change', function(){
        var key = this.value;
        if (!key) {
            ADO.selectedDomain = null;
            ADO.selectedDomainKey = null;
            adoSetMode(false);
            ADO.toggle.checked = false;
            adoUpdatePreview();
            OU.selectedDomainKey = null; GRP.selectedDomainKey = null;
            ouSetMode(false); grpSetMode(false);
            OU.toggle.checked = false; GRP.toggle.checked = false;
            return;
        }
        fetch(apiBase + '?endpoint=domain_api&action=list_domains', { method: 'GET', credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (!res.success) return;
            res.domains.forEach(function(d){ if (d.key === key) { adoLoadDomain(d); ouLoadDomain(d); grpLoadDomain(d); } });
        })
        .catch(function(){});
    });

    Object.keys(ADO.fields).forEach(function(k){
        var el = ADO.fields[k];
        if (el) {
            el.addEventListener('change', adoUpdatePreview);
            el.addEventListener('input', adoUpdatePreview);
        }
    });

    if (ADO.btnSave) ADO.btnSave.addEventListener('click', adoSaveConfig);
    if (ADO.btnReset) ADO.btnReset.addEventListener('click', adoResetForm);

    document.querySelectorAll('.noc-tab-item').forEach(function(t){
        t.addEventListener('click', function(){
            if (this.dataset.tab === 'adobjects') {
                if (!ADO.domainSelector.options.length || ADO.domainSelector.options.length <= 1) {
                    adoPopulateDomainDropdown();
                } else {
                    var key = ADO.domainSelector.value;
                    if (key) {
                        fetch(apiBase + '?endpoint=domain_api&action=list_domains', { method: 'GET', credentials: 'same-origin' })
                        .then(function(r){ return r.json(); })
                        .then(function(res){
                            if (!res.success) return;
                            res.domains.forEach(function(d){ if (d.key === key) { adoLoadDomain(d); ouLoadDomain(d); grpLoadDomain(d); } });
                        })
                        .catch(function(){});
                    }
                }
            }
        });
    });

    // ─── AD Objects: OU Management ──────────────────────────────────────
    var OU = {
        toggle: document.getElementById('ouCustomToggle'),
        defaultHint: document.getElementById('ouDefaultHint'),
        customFields: document.getElementById('ouCustomFields'),
        levels: {
            l1: document.getElementById('ouFieldL1'),
            l2: document.getElementById('ouFieldL2'),
            l3: document.getElementById('ouFieldL3'),
            l4: document.getElementById('ouFieldL4'),
            l5: document.getElementById('ouFieldL5'),
        },
        prefix: document.getElementById('ouPrefix'),
        suffix: document.getElementById('ouSuffix'),
        rootOu: document.getElementById('ouRootOu'),
        preview: document.getElementById('ouPreview'),
        feedback: document.getElementById('ouFeedback'),
        btnSave: document.getElementById('btnSaveOU'),
        btnReset: document.getElementById('btnResetOU'),
        selectedDomainKey: null,
    };

    var OU_FIELD_LABELS = {
        OPERATING_UNIT_TITLE: 'OperatingUnit',
        DEPARTMENT_TITLE: 'Department',
        SECTION_TITLE: 'Section',
        PRODUCT_TITLE: 'Product',
        SUB_SECTION_TITLE: 'SubSection',
    };

    function ouDefaultConfig() {
        return {
            levels: {
                '1': { field: 'OPERATING_UNIT_TITLE' },
                '2': { field: 'DEPARTMENT_TITLE' },
                '3': { field: 'SECTION_TITLE' },
                '4': { field: 'PRODUCT_TITLE' },
                '5': { field: 'SUB_SECTION_TITLE' },
            },
            prefix: '',
            suffix: '',
            root_ou: '',
        };
    }

    function ouSetMode(customizing) {
        OU.defaultHint.classList.toggle('sys-hidden', customizing);
        OU.customFields.classList.toggle('sys-hidden', !customizing);
        OU.btnSave.disabled = !customizing || !OU.selectedDomainKey;
        OU.btnReset.disabled = !customizing || !OU.selectedDomainKey;
    }

    function ouUpdatePreview() {
        var cfg = ouGetConfig();
        var lines = [];
        var indent = '';
        if (cfg.root_ou) {
            lines.push('<div style="color:#f59e0b;font-size:var(--font-xs);margin-bottom:4px;"><i class="fas fa-folder-open me-1"></i>Root: <strong>' + escapeHtml(cfg.root_ou) + '</strong></div>');
        }
        for (var i = 1; i <= 5; i++) {
            var f = cfg.levels[String(i)]?.field;
            if (!f) continue;
            var label = OU_FIELD_LABELS[f] || f;
            var name = cfg.prefix + label + cfg.suffix;
            var isLast = true;
            for (var j = i + 1; j <= 5; j++) { if (cfg.levels[String(j)]?.field) { isLast = false; break; } }
            var prefix = indent + (isLast ? '\u2514\u2500 ' : '\u251C\u2500 ');
            var suffix = i === 5 ? ' <span style="color:#94a3b8;font-weight:400;">(User OU)</span>' : '';
            lines.push('<div style="padding-left:' + (indent.length * 8) + 'px;' + (!isLast ? 'border-left:1px dashed #e2e8f0;' : '') + '">' + prefix + 'OU=<strong>' + name + '</strong>' + suffix + '</div>');
            indent += isLast ? '  ' : '\u2502 ';
        }
        OU.preview.innerHTML = lines.length ? lines.join('') : '<span style="color:#94a3b8;">(no levels selected)</span>';
    }

    var OU_ALL_FIELDS = ['OPERATING_UNIT_TITLE', 'DEPARTMENT_TITLE', 'SECTION_TITLE', 'PRODUCT_TITLE', 'SUB_SECTION_TITLE'];
    var OU_FIELD_DISPLAY = {
        OPERATING_UNIT_TITLE: 'OPERATING_UNIT_TITLE',
        DEPARTMENT_TITLE: 'DEPARTMENT_TITLE',
        SECTION_TITLE: 'SECTION_TITLE',
        PRODUCT_TITLE: 'PRODUCT_TITLE',
        SUB_SECTION_TITLE: 'SUB_SECTION_TITLE',
    };

    function ouRefreshLevelOptions(changedKey) {
        var selected = {};
        for (var k in OU.levels) {
            var el = OU.levels[k];
            if (el && el.value) selected[k] = el.value;
        }
        // If conflict after change, reset the other level to Skip
        var used = {};
        for (var k in selected) {
            var v = selected[k];
            if (v && used[v] !== undefined) {
                var resetKey = (k === changedKey) ? used[v] : k;
                var resetEl = OU.levels[resetKey];
                if (resetEl) { resetEl.value = ''; delete selected[resetKey]; }
            }
            if (v) used[v] = k;
        }
        // Rebuild selected map after conflict resolution
        var finalSelected = {};
        for (var k in OU.levels) {
            var el = OU.levels[k];
            if (el && el.value) finalSelected[k] = el.value;
        }
        // Disable options used at other levels
        for (var k in OU.levels) {
            var el = OU.levels[k];
            if (!el) continue;
            for (var i = 0; i < el.options.length; i++) {
                var opt = el.options[i];
                if (!opt.value) { opt.disabled = false; continue; }
                var isUsed = false;
                for (var ok in finalSelected) {
                    if (ok !== k && finalSelected[ok] === opt.value) { isUsed = true; break; }
                }
                opt.disabled = isUsed;
            }
        }
    }

    function ouGetConfig() {
        return {
            levels: {
                '1': { field: OU.levels.l1 ? OU.levels.l1.value : '' },
                '2': { field: OU.levels.l2 ? OU.levels.l2.value : '' },
                '3': { field: OU.levels.l3 ? OU.levels.l3.value : '' },
                '4': { field: OU.levels.l4 ? OU.levels.l4.value : '' },
                '5': { field: OU.levels.l5 ? OU.levels.l5.value : '' },
            },
            prefix: OU.prefix ? OU.prefix.value : '',
            suffix: OU.suffix ? OU.suffix.value : '',
            root_ou: OU.rootOu ? OU.rootOu.value : '',
        };
    }

    function ouLoadDomain(domainData) {
        OU.selectedDomainKey = domainData ? domainData.key : null;
        var domainLabel = document.getElementById('ouActiveDomainLabel');
        if (domainLabel) domainLabel.textContent = domainData ? (domainData.label || domainData.key || '\u2014') : '\u2014';
        var cfg = domainData && domainData.ou_config && domainData.ou_config.customized ? domainData.ou_config : ouDefaultConfig();
        var hasCustom = domainData && domainData.ou_config && domainData.ou_config.customized;
        OU.toggle.checked = hasCustom;
        if (OU.levels.l1) OU.levels.l1.value = cfg.levels?.['1']?.field || 'OPERATING_UNIT_TITLE';
        if (OU.levels.l2) OU.levels.l2.value = cfg.levels?.['2']?.field || 'DEPARTMENT_TITLE';
        if (OU.levels.l3) OU.levels.l3.value = cfg.levels?.['3']?.field || 'SECTION_TITLE';
        if (OU.levels.l4) OU.levels.l4.value = cfg.levels?.['4']?.field || 'PRODUCT_TITLE';
        if (OU.levels.l5) OU.levels.l5.value = cfg.levels?.['5']?.field || 'SUB_SECTION_TITLE';
        if (OU.prefix) OU.prefix.value = cfg.prefix || '';
        if (OU.suffix) OU.suffix.value = cfg.suffix || '';
        if (OU.rootOu) OU.rootOu.value = cfg.root_ou || '';
        ouRefreshLevelOptions();
        ouSetMode(hasCustom);
        ouUpdatePreview();
        ouHideFeedback();
    }

    function ouShowFeedback(msg, type) {
        OU.feedback.textContent = msg;
        OU.feedback.className = 'sys-inline-feedback mb-2 ' + (type === 'success' ? 'is-success' : type === 'error' ? 'is-error' : 'is-warn');
        OU.feedback.classList.remove('sys-hidden');
    }
    function ouHideFeedback() { OU.feedback.classList.add('sys-hidden'); }

    function ouSaveConfig() {
        var key = OU.selectedDomainKey;
        if (!key) { ouShowFeedback('Select a domain first.', 'error'); return; }
        if (!OU.toggle.checked) { ouShowFeedback('Enable Customize to save.', 'warn'); return; }
        var config = ouGetConfig();
        config.customized = true;
        OU.btnSave.disabled = true;
        OU.btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
        ouHideFeedback();
        fetch(apiBase + '?endpoint=domain_api&action=update_domain', {
            method: 'POST',
            headers: apiHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ key: key, ou_config: config })
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            OU.btnSave.disabled = false;
            OU.btnSave.innerHTML = '<i class="fas fa-save me-1"></i>Save to Domain';
            if (res.success) {
                ouShowFeedback('OU config saved to <strong>' + escapeHtml(key) + '</strong>', 'success');
            } else {
                ouShowFeedback(res.message || 'Save failed.', 'error');
            }
        })
        .catch(function(){
            OU.btnSave.disabled = false;
            OU.btnSave.innerHTML = '<i class="fas fa-save me-1"></i>Save to Domain';
            ouShowFeedback('Network error.', 'error');
        });
    }

    function ouResetForm() {
        if (OU.selectedDomainKey) {
            fetch(apiBase + '?endpoint=domain_api&action=list_domains', { method: 'GET', credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res.success) return;
                res.domains.forEach(function(d){ if (d.key === OU.selectedDomainKey) ouLoadDomain(d); });
            })
            .catch(function(){});
        }
    }

    OU.toggle.addEventListener('change', function(){
        ouSetMode(this.checked);
        if (!this.checked) { ouUpdatePreview(); }
        ouHideFeedback();
    });

    ['l1','l2','l3','l4','l5'].forEach(function(key){
        var el = OU.levels[key];
        if (el) {
            el.addEventListener('change', function(){ ouRefreshLevelOptions(key); ouUpdatePreview(); });
        }
    });
    if (OU.prefix) OU.prefix.addEventListener('input', ouUpdatePreview);
    if (OU.suffix) OU.suffix.addEventListener('input', ouUpdatePreview);
    if (OU.rootOu) OU.rootOu.addEventListener('input', ouUpdatePreview);
    if (OU.btnSave) OU.btnSave.addEventListener('click', ouSaveConfig);
    if (OU.btnReset) OU.btnReset.addEventListener('click', ouResetForm);

    // ─── AD Objects: Group Management ───────────────────────────────────
    var GRP = {
        toggle: document.getElementById('grpCustomToggle'),
        defaultHint: document.getElementById('grpDefaultHint'),
        customFields: document.getElementById('grpCustomFields'),
        autoYes: document.getElementById('grpAutoYes'),
        autoNo: document.getElementById('grpAutoNo'),
        prefix: document.getElementById('grpPrefix'),
        suffix: document.getElementById('grpSuffix'),
        rulesContainer: document.getElementById('grpRulesContainer'),
        preview: document.getElementById('grpPreview'),
        feedback: document.getElementById('grpFeedback'),
        btnSave: document.getElementById('btnSaveGrp'),
        btnReset: document.getElementById('btnResetGrp'),
        btnAddRule: document.getElementById('btnAddGrpRule'),
        selectedDomainKey: null,
        ruleIndex: 0,
    };

    var GRP_FIELD_OPTIONS = ['DEPARTMENT_TITLE', 'SECTION_TITLE', 'OPERATING_UNIT_TITLE', 'PRODUCT_TITLE', 'SUB_SECTION_TITLE', 'DESIGNATION', 'LOCATION_TITLE'];

    function grpDefaultConfig() {
        return { auto_create: true, prefix: '', suffix: '', rules: [] };
    }

    function grpSetMode(customizing) {
        GRP.defaultHint.classList.toggle('sys-hidden', customizing);
        GRP.customFields.classList.toggle('sys-hidden', !customizing);
        GRP.btnSave.disabled = !customizing || !GRP.selectedDomainKey;
        GRP.btnReset.disabled = !customizing || !GRP.selectedDomainKey;
    }

    function grpBuildRuleRow(rule) {
        var idx = GRP.ruleIndex++;
        var row = document.createElement('div');
        row.className = 'row g-1 mb-1 align-items-center grp-rule-row';
        row.style.cssText = 'border:1px solid var(--border-light,#eee);border-radius:4px;background:rgba(0,0,0,0.01);padding:4px 6px;';
        var colSel = document.createElement('div');
        colSel.className = 'col-md-3 col-4';
        var sel = document.createElement('select');
        sel.className = 'form-select form-select-sm grp-rule-field';
        sel.style.cssText = 'font-size:var(--font-xs);';
        GRP_FIELD_OPTIONS.forEach(function(o){
            var opt = document.createElement('option');
            opt.value = o;
            opt.textContent = o;
            if (rule && rule.field === o) opt.selected = true;
            sel.appendChild(opt);
        });
        colSel.appendChild(sel);
        var colEq = document.createElement('div');
        colEq.className = 'col-auto text-center';
        colEq.style.cssText = 'font-size:var(--font-xs);color:var(--text-soft);padding:0 2px;';
        colEq.textContent = '=';
        var colVal = document.createElement('div');
        colVal.className = 'col-md-2 col-3';
        var valInput = document.createElement('input');
        valInput.type = 'text';
        valInput.className = 'form-control form-control-sm grp-rule-value';
        valInput.style.cssText = 'font-size:var(--font-xs);';
        valInput.placeholder = 'value';
        if (rule) valInput.value = rule.value || '';
        colVal.appendChild(valInput);
        var colArr = document.createElement('div');
        colArr.className = 'col-auto text-nowrap';
        colArr.style.cssText = 'font-size:var(--font-xs);color:var(--text-soft);padding:0 4px;';
        colArr.textContent = '\u2192 Add to Group';
        var colGrp = document.createElement('div');
        colGrp.className = 'col-md-2 col-3';
        var grpInput = document.createElement('input');
        grpInput.type = 'text';
        grpInput.className = 'form-control form-control-sm grp-rule-group';
        grpInput.style.cssText = 'font-size:var(--font-xs);';
        grpInput.placeholder = 'group name';
        if (rule) grpInput.value = rule.group || '';
        colGrp.appendChild(grpInput);
        var colBtn = document.createElement('div');
        colBtn.className = 'col-auto';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-danger grp-remove-rule px-2';
        btn.style.cssText = 'font-size:var(--font-xs);line-height:1;';
        btn.innerHTML = '<i class="fas fa-times"></i>';
        btn.addEventListener('click', function(){ row.remove(); grpUpdatePreview(); });
        colBtn.appendChild(btn);
        row.appendChild(colSel);
        row.appendChild(colEq);
        row.appendChild(colVal);
        row.appendChild(colArr);
        row.appendChild(colGrp);
        row.appendChild(colBtn);
        // live preview on change
        [sel, valInput, grpInput].forEach(function(el){
            el.addEventListener('change', grpUpdatePreview);
            el.addEventListener('input', grpUpdatePreview);
        });
        return row;
    }

    function grpUpdatePreview() {
        var lines = [];
        var autoEnabled = GRP.autoYes ? GRP.autoYes.checked : true;
        if (autoEnabled) {
            var p = GRP.prefix ? GRP.prefix.value : '';
            var s = GRP.suffix ? GRP.suffix.value : '';
            var labels = ['Section', 'Dept', 'OperatingUnit', 'Product', 'SubSection'];
            lines.push('<div style="color:#10b981;"><i class="fas fa-users me-1"></i>Auto-created groups (per OU level):</div>');
            labels.forEach(function(l, idx){
                var name = p + l + s;
                var prefix = idx < labels.length - 1 ? '\u251C\u2500 ' : '\u2514\u2500 ';
                lines.push('<div style="padding-left:16px;' + (idx < labels.length - 1 ? 'border-left:1px dashed #e2e8f0;' : '') + '">' + prefix + name + '</div>');
            });
        } else {
            lines.push('<div style="color:#94a3b8;"><i class="fas fa-ban me-1"></i>Auto-creation: disabled</div>');
        }
        var ruleTexts = [];
        GRP.rulesContainer.querySelectorAll('.grp-rule-row').forEach(function(row){
            var field = row.querySelector('.grp-rule-field')?.value || '';
            var val = row.querySelector('.grp-rule-value')?.value || '';
            var grp = row.querySelector('.grp-rule-group')?.value || '';
            if (field && val && grp) ruleTexts.push('<div style="padding-left:16px;"><span style="color:#8b5cf6;">' + field + '=' + val + '</span> \u2192 <strong>' + grp + '</strong></div>');
        });
        if (ruleTexts.length) {
            lines.push('<div style="margin-top:4px;color:#f59e0b;"><i class="fas fa-tasks me-1"></i>Conditional rules:</div>');
            lines = lines.concat(ruleTexts);
        }
        GRP.preview.innerHTML = lines.length ? lines.join('') : '<span style="color:#94a3b8;">\u2014</span>';
    }

    function grpGetConfig() {
        var rules = [];
        GRP.rulesContainer.querySelectorAll('.grp-rule-row').forEach(function(row){
            var field = row.querySelector('.grp-rule-field')?.value || '';
            var val = row.querySelector('.grp-rule-value')?.value || '';
            var grp = row.querySelector('.grp-rule-group')?.value || '';
            if (field && val && grp) rules.push({ field: field, value: val, group: grp });
        });
        return {
            customized: GRP.toggle.checked,
            auto_create: GRP.autoYes ? GRP.autoYes.checked : true,
            prefix: GRP.prefix ? GRP.prefix.value : '',
            suffix: GRP.suffix ? GRP.suffix.value : '',
            rules: rules,
        };
    }

    function grpLoadDomain(domainData) {
        GRP.selectedDomainKey = domainData ? domainData.key : null;
        var domainLabel = document.getElementById('grpActiveDomainLabel');
        if (domainLabel) domainLabel.textContent = domainData ? (domainData.label || domainData.key || '\u2014') : '\u2014';
        var cfg = domainData && domainData.group_config && domainData.group_config.customized ? domainData.group_config : grpDefaultConfig();
        var hasCustom = domainData && domainData.group_config && domainData.group_config.customized;
        GRP.toggle.checked = hasCustom;
        if (GRP.autoYes) GRP.autoYes.checked = cfg.auto_create !== false;
        if (GRP.autoNo) GRP.autoNo.checked = cfg.auto_create === false;
        if (GRP.prefix) GRP.prefix.value = cfg.prefix || '';
        if (GRP.suffix) GRP.suffix.value = cfg.suffix || '';
        GRP.rulesContainer.innerHTML = '';
        (cfg.rules || []).forEach(function(r){ GRP.rulesContainer.appendChild(grpBuildRuleRow(r)); });
        grpSetMode(hasCustom);
        grpUpdatePreview();
        grpHideFeedback();
    }

    function grpShowFeedback(msg, type) {
        GRP.feedback.textContent = msg;
        GRP.feedback.className = 'sys-inline-feedback mb-2 ' + (type === 'success' ? 'is-success' : type === 'error' ? 'is-error' : 'is-warn');
        GRP.feedback.classList.remove('sys-hidden');
    }
    function grpHideFeedback() { GRP.feedback.classList.add('sys-hidden'); }

    function grpSaveConfig() {
        var key = GRP.selectedDomainKey;
        if (!key) { grpShowFeedback('Select a domain first.', 'error'); return; }
        if (!GRP.toggle.checked) { grpShowFeedback('Enable Customize to save.', 'warn'); return; }
        var config = grpGetConfig();
        GRP.btnSave.disabled = true;
        GRP.btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
        grpHideFeedback();
        fetch(apiBase + '?endpoint=domain_api&action=update_domain', {
            method: 'POST',
            headers: apiHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ key: key, group_config: config })
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            GRP.btnSave.disabled = false;
            GRP.btnSave.innerHTML = '<i class="fas fa-save me-1"></i>Save to Domain';
            if (res.success) {
                grpShowFeedback('Group config saved to <strong>' + escapeHtml(key) + '</strong>', 'success');
            } else {
                grpShowFeedback(res.message || 'Save failed.', 'error');
            }
        })
        .catch(function(){
            GRP.btnSave.disabled = false;
            GRP.btnSave.innerHTML = '<i class="fas fa-save me-1"></i>Save to Domain';
            grpShowFeedback('Network error.', 'error');
        });
    }

    function grpResetForm() {
        if (GRP.selectedDomainKey) {
            fetch(apiBase + '?endpoint=domain_api&action=list_domains', { method: 'GET', credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res.success) return;
                res.domains.forEach(function(d){ if (d.key === GRP.selectedDomainKey) grpLoadDomain(d); });
            })
            .catch(function(){});
        }
    }

    GRP.toggle.addEventListener('change', function(){
        grpSetMode(this.checked);
        if (!this.checked) { grpUpdatePreview(); }
        grpHideFeedback();
    });

    if (GRP.autoYes) GRP.autoYes.addEventListener('change', grpUpdatePreview);
    if (GRP.autoNo) GRP.autoNo.addEventListener('change', grpUpdatePreview);
    if (GRP.prefix) GRP.prefix.addEventListener('input', grpUpdatePreview);
    if (GRP.suffix) GRP.suffix.addEventListener('input', grpUpdatePreview);
    if (GRP.btnAddRule) GRP.btnAddRule.addEventListener('click', function(){
        GRP.rulesContainer.appendChild(grpBuildRuleRow(null));
        grpUpdatePreview();
    });
    if (GRP.btnSave) GRP.btnSave.addEventListener('click', grpSaveConfig);
    if (GRP.btnReset) GRP.btnReset.addEventListener('click', grpResetForm);

    // ─── API Integration ────────────────────────────────────────────────
    function apiFeedback(msg, type) {
        var el = document.getElementById('api_feedback');
        el.textContent = msg;
        el.className = 'sys-inline-feedback mb-2 ' + (type === 'success' ? 'is-success' : type === 'error' ? 'is-error' : 'is-warn');
        el.classList.remove('sys-hidden');
    }
    function apiHideFeedback() { document.getElementById('api_feedback').classList.add('sys-hidden'); }

    function apiClearConsole(consoleId) {
        if (!consoleId) { consoleId = 'api_console_ep1'; }
        var el = document.getElementById(consoleId + '_output');
        if (el) el.textContent = '';
        var c = document.getElementById(consoleId);
        if (c) c.classList.add('sys-hidden');
    }
    window.apiClearConsole = apiClearConsole;

    function toggleExampleResponse() {
        var block = document.getElementById('exampleRespBlock');
        var icon = document.getElementById('exampleRespIcon');
        var isHidden = block.classList.contains('sys-hidden');
        block.classList.toggle('sys-hidden');
        icon.style.transform = isHidden ? 'rotate(90deg)' : '';
    }
    window.toggleExampleResponse = toggleExampleResponse;

    function toggleRespFields() {
        var block = document.getElementById('respFieldsBlock');
        var icon = document.getElementById('respFieldsIcon');
        var isHidden = block.classList.contains('sys-hidden');
        block.classList.toggle('sys-hidden');
        icon.style.transform = isHidden ? 'rotate(90deg)' : '';
    }
    window.toggleRespFields = toggleRespFields;

    // Toggle Ep1 test section
    document.getElementById('btnToggleApiTest').addEventListener('click', function(){
        var sec = document.getElementById('api_test_section');
        var isHidden = sec.classList.contains('sys-hidden');
        sec.classList.toggle('sys-hidden');
        this.innerHTML = isHidden
            ? '<i class="fas fa-times me-1"></i> Close'
            : '<i class="fas fa-flask me-1"></i> Test API';
        if (!isHidden) apiClearConsole('api_console_ep1');
    });

    // Console helpers with consoleId
    function apiShowConsole(consoleId) { document.getElementById(consoleId).classList.remove('sys-hidden'); }
    function apiAppendConsole(label, content, type, consoleId) {
        var el = document.getElementById(consoleId + '_output');
        el.innerHTML += '<div><span class="console-label">' + label + '</span> <span class="console-' + type + '">' + content + '</span></div>';
        el.scrollTop = el.scrollHeight;
        apiShowConsole(consoleId);
    }
    function apiAppendRaw(label, content, consoleId) {
        var el = document.getElementById(consoleId + '_output');
        el.innerHTML += '<div class="mt-1"><span class="console-label">' + label + '</span></div><div>' + content + '</div>';
        el.scrollTop = el.scrollHeight;
        apiShowConsole(consoleId);
    }

    // Shared test helper
    function apiRunTest(action, body, btn, consoleId){
        apiHideFeedback();
        apiClearConsole(consoleId);
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>'; }
        fetch(apiBase + '?endpoint=system_config_api&action=' + action, {
            method: 'POST',
            headers: apiHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify(body)
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.success) {
                apiAppendConsole('✓', res.response_time + 'ms (' + res.message + ')', 'success', consoleId);
                var raw = document.getElementById(consoleId + '_output');
                if (action === 'test_integration') {
                    var picUrl = (res.response_data && res.response_data['PIC_URL_']) || '';
                    if (picUrl) {
                        var imgBase = document.getElementById('fld_hrms_img_url').value.trim();
                        if (imgBase) {
                            var cleaned = picUrl.replace(/^images[\/\\]repository[\/\\]/, '');
                            var finalImg = imgBase.replace(/\/+$/, '') + '/' + cleaned;
                            raw.innerHTML += '<div class="mt-1"><span class="console-label">Photo:</span></div>'
                                + '<img src="' + finalImg.replace(/&/g, '&amp;') + '" style="max-width:80px;max-height:80px;border-radius:4px;margin:2px 0;border:1px solid #334155;" onerror="this.style.display=\'none\'">';
                        }
                    }
                }
                apiAppendRaw('← Response' + (res.keys ? ' (' + res.keys.length + ' fields)' : res.employees ? ' (' + res.employees.length + ' records)' : '') + ':', '', consoleId);
                var pre = document.createElement('pre');
                pre.style.cssText = 'margin:2px 0 0;background:#0f172a;color:#a5f3fc;font-size:0.68rem;padding:6px;border-radius:4px;overflow-x:auto;white-space:pre-wrap;word-break:break-all;';
                pre.textContent = JSON.stringify(res.response_data || {}, null, 2);
                raw.appendChild(pre);
                raw.scrollTop = raw.scrollHeight;
            } else {
                apiAppendConsole('✗', (res.response_time ? res.response_time + 'ms - ' : '') + (res.message || 'Test failed.'), 'error', consoleId);
            }
        })
        .catch(function(err){ apiAppendConsole('✗', 'Network error: ' + err.message, 'error', consoleId); })
        .then(function(){
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-play me-1"></i>Test'; }
        });
    }

    // Endpoint 1: emp_id
    document.querySelectorAll('.btn-test-ep[data-ep="emp_id"]').forEach(function(b){
        b.addEventListener('click', function(){
            var empId = document.getElementById('fld_test_emp_id').value.trim();
            if (!empId) { apiFeedback('Enter an employee ID.', 'error'); return; }
            var baseUrl = document.getElementById('fld_hrms_api_url').value.trim();
            if (!baseUrl) { apiFeedback('HRMS API URL is not configured.', 'error'); return; }
            var cleanUrl = baseUrl.replace(/\?.*$/, '');
            var testUrl = cleanUrl + '?emp_id=' + encodeURIComponent(empId);
            apiAppendConsole('▶', 'GET ' + testUrl, 'time', 'api_console_ep1');
            apiRunTest('test_integration', { test_url: cleanUrl + '?emp_id=' + empId }, this, 'api_console_ep1');
        });
    });

    // Endpoint 2: emp_sts
    document.querySelectorAll('.btn-test-ep[data-ep="emp_sts"]').forEach(function(b){
        b.addEventListener('click', function(){
            var status = document.getElementById('fld_test_emp_sts').value;
            var stsUrl = document.getElementById('fld_hrms_sts_url').value.trim();
            var testUrl = stsUrl || document.getElementById('fld_hrms_api_url').value.trim();
            if (!testUrl) { apiFeedback('No API URL configured for status endpoint.', 'error'); return; }
            var cleanUrl = testUrl.replace(/\?.*$/, '');
            apiAppendConsole('▶', 'GET ' + cleanUrl + '?emp_sts=' + status, 'time', 'api_console_ep2');
            apiRunTest('test_emp_sts', { status: status, test_url: testUrl }, this, 'api_console_ep2');
        });
    });

    document.getElementById('btnSaveApi').addEventListener('click', function(){
        var hrmsUrl = document.getElementById('fld_hrms_api_url').value.trim();
        var imgUrl = document.getElementById('fld_hrms_img_url').value.trim();
        var stsUrl = document.getElementById('fld_hrms_sts_url').value.trim();
        if (!hrmsUrl) { apiFeedback('HRMS API URL is required.', 'error'); return; }
        apiHideFeedback();
        window.__pendingApiHrmsUrl = hrmsUrl;
        window.__pendingApiImgUrl = imgUrl;
        window.__pendingApiStsUrl = stsUrl;
        if (typeof window.openConfirmModal === 'function') {
            window.openConfirmModal('integrations');
        } else {
            apiFeedback('Credential confirmation not available. Refresh and try again.', 'error');
        }
    });

    // ─── Health Admin Credentials ──────────────────────────────────────
    var healthAdminBadge = document.getElementById('healthAdminBadge');
    var healthAdminMsg = document.getElementById('healthAdminMsg');
    var healthAdminFields = document.getElementById('healthAdminFields');
    var btnSaveHealth = document.getElementById('btnSaveHealthAdmin');
    var btnDeleteHealth = document.getElementById('btnDeleteHealthAdmin');
    var healthUsernameInput = document.getElementById('domainFormHealthUsername');
    var healthPasswordInput = document.getElementById('domainFormHealthPassword');
    var healthDiag = document.getElementById('healthAdminDiag');

    function healthDiagMsg(msg, type) {
        healthDiag.style.display = 'block';
        healthDiag.className = 'mt-2 rounded p-2 ' + (type === 'error' ? 'alert alert-danger' : type === 'success' ? 'alert alert-success' : 'alert alert-info');
        healthDiag.innerHTML = msg;
        setTimeout(function() { healthDiag.style.display = 'none'; }, 6000);
    }

    function loadHealthAdminStatus(domainKey) {
        if (!domainKey) return;
        fetch(apiBase + '?endpoint=domain_api&action=get_health_admin_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'key=' + encodeURIComponent(domainKey)
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success && res.has_stored_creds) {
                healthAdminBadge.textContent = 'SAVED (' + escapeHtml(res.username) + ')';
                healthAdminBadge.className = 'badge rounded-pill bg-success sys-badge-sm';
                healthAdminMsg.style.display = 'block';
                healthUsernameInput.value = res.username;
                healthPasswordInput.value = '';
                healthPasswordInput.placeholder = '•••••••• (unchanged)';
                btnDeleteHealth.style.display = 'inline-block';
            } else {
                healthAdminBadge.textContent = 'NOT SET';
                healthAdminBadge.className = 'badge rounded-pill bg-secondary sys-badge-sm';
                healthAdminMsg.style.display = 'none';
                healthPasswordInput.placeholder = 'Password';
                btnDeleteHealth.style.display = 'none';
            }
        })
        .catch(function() {});
    }

    function saveHealthAdmin() {
        var domainKey = form.key.value.trim();
        var u = healthUsernameInput.value.trim();
        var p = healthPasswordInput.value;
        if (!domainKey) { healthDiagMsg('Save the domain first.', 'error'); return; }
        if (!u) { healthDiagMsg('Username is required.', 'error'); return; }
        if (!p) { healthDiagMsg('Password is required.', 'error'); return; }
        btnSaveHealth.disabled = true;
        btnSaveHealth.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        fetch(apiBase + '?endpoint=domain_api&action=save_health_admin_creds', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'key=' + encodeURIComponent(domainKey) + '&username=' + encodeURIComponent(u) + '&password=' + encodeURIComponent(p)
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                healthDiagMsg(res.message, 'success');
                loadHealthAdminStatus(domainKey);
            } else {
                healthDiagMsg(res.message, 'error');
            }
        })
        .catch(function() { healthDiagMsg('Network error.', 'error'); })
        .finally(function() {
            btnSaveHealth.disabled = false;
            btnSaveHealth.innerHTML = '<i class="fas fa-save me-1"></i>Save';
        });
    }

    function deleteHealthAdmin() {
        var domainKey = form.key.value.trim();
        if (!domainKey) return;
        if (!confirm('Clear stored health check admin credentials for this domain?')) return;
        btnDeleteHealth.disabled = true;
        fetch(apiBase + '?endpoint=domain_api&action=delete_health_admin_creds', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'key=' + encodeURIComponent(domainKey)
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                healthDiagMsg(res.message, 'success');
                loadHealthAdminStatus(domainKey);
            } else {
                healthDiagMsg(res.message, 'error');
            }
        })
        .catch(function() { healthDiagMsg('Network error.', 'error'); })
        .finally(function() {
            btnDeleteHealth.disabled = false;
        });
    }

    if (btnSaveHealth) btnSaveHealth.addEventListener('click', saveHealthAdmin);
    if (btnDeleteHealth) btnDeleteHealth.addEventListener('click', deleteHealthAdmin);

    // Patch openForm to load health admin status after domain loads
    var origOpenForm = openForm;
    openForm = function(mode, data) {
        origOpenForm(mode, data);
        setTimeout(function() {
            var dk = form.key.value;
            if (dk) loadHealthAdminStatus(dk);
        }, 100);
    };

    document.addEventListener('sysConfigLoaded', function(){
        adoPopulateDomainDropdown();
    });

})();
</script>
