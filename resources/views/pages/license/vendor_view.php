<!-- ── Credential Verification Modal ───────────────────── -->
<div class="modal fade" id="vendorCredentialModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-shield-alt me-2"></i>Verify Access</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted text-center" style="font-size:0.85rem;margin-bottom:1.25rem;">This area is sensitive. Re-enter your portal credentials to continue.</p>
                <div class="mb-3">
                    <label class="form-label" for="vendorCredUserId">User ID</label>
                    <input type="text" class="form-control" id="vendorCredUserId" autocomplete="username" placeholder="Enter your portal user ID">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="vendorCredPassword">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="vendorCredPassword" autocomplete="current-password" placeholder="Enter your portal password">
                        <button type="button" class="btn btn-outline-secondary" id="vendorCredTogglePw" tabindex="-1"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div id="vendorCredFeedback" class="vendor-cred-feedback text-danger"></div>
            </div>
            <div class="modal-footer app-form-actions">
                <button type="button" class="btn btn-primary flex-fill" id="vendorCredConfirm"><i class="fas fa-check me-1"></i>Verify</button>
                <button type="button" class="btn btn-secondary flex-fill" id="vendorCredCancel"><i class="fas fa-times me-1"></i>Cancel</button>
            </div>
        </div>
    </div>
</div>

<div class="vendor-console-container slide-in-top">
    <div class="vendor-console-content">
    <div class="row mb-0">
        <div class="col-12">
            <div class="status-banner success">
                <div class="status-banner-icon"><i class="fas fa-handshake"></i></div>
                <div>
                    <div class="status-banner-title">VENDOR CONSOLE</div>
                    <div class="status-banner-msg">Generate, track, and manage RSA-256 signed license certificates for client deployments.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-7">
            <div class="card app-table-card">
                <div class="card-body no-padding">
                    <div class="log-title-wrapper app-table-title">
                        <span><i class="fas fa-info-circle text-primary me-2"></i><a href="<?= admin_page_url('license_doc', ['doc' => 'LICENSE_A-Z']) ?>" target="_blank" class="text-decoration-none text-reset">License Generation Guide</a></span>
                    </div>
                    <div class="p-3">
                        <div class="vendor-guide">
                            <div class="vendor-guide-step">
                                <span class="vendor-guide-num">1</span>
                                <div><strong>Fill the form</strong> — Enter client details (name, domain, deployment ID, expiry).</div>
                            </div>
                            <div class="vendor-guide-step">
                                <span class="vendor-guide-num">2</span>
                                <div><strong>Save &amp; Track</strong> — Click "Save License" to store it securely. All saved licenses appear in the tracking table below.</div>
                            </div>
                            <div class="vendor-guide-step">
                                <span class="vendor-guide-num">3</span>
                                <div><strong>Download &amp; Sign</strong> — Download as PEM anytime using your configured signing key.</div>
                            </div>
                            <div class="vendor-guide-step">
                                <span class="vendor-guide-num">4</span>
                                <div><strong>Verify &amp; Deliver</strong> — Use Verify to check integrity, then deliver the signed file to the client.</div>
                            </div>
                        </div>
                        <div class="vendor-guide-refs mt-3">
                            <a href="<?= admin_page_url('license_doc', ['doc' => 'LICENSE_A-Z']) ?>" target="_blank" class="text-decoration-none">License A-Z Guide</a>
                            <span class="mx-2 text-muted">|</span>
                            <a href="<?= admin_page_url('license_doc', ['doc' => 'VENDOR_SECURITY_AND_DEPLOYMENT']) ?>" target="_blank" class="text-decoration-none">Security &amp; Deployment</a>
                            <span class="mx-2 text-muted">|</span>
                            <a href="<?= admin_page_url('license_doc', ['doc' => 'LICENSE_ARCHITECTURE']) ?>" target="_blank" class="text-decoration-none">Architecture</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card app-table-card">
                <div class="card-body no-padding">
                    <div class="log-title-wrapper app-table-title">
                        <span><i class="fas fa-certificate text-success me-2"></i>Generate &amp; Save License Payload</span>
                    </div>
                    <div class="p-3">
                        <form id="vendorGenForm" autocomplete="off">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="vFieldClient">Client Name <span class="text-danger">*</span></label>
                                    <input type="text" id="vFieldClient" class="form-control" placeholder="e.g. Acme Corp" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="vFieldDomain">Domain Name <span class="text-danger">*</span></label>
                                    <input type="text" id="vFieldDomain" class="form-control" placeholder="e.g. acme.com" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="vFieldDeployId">Deployment ID <span class="text-danger">*</span></label>
                                    <input type="text" id="vFieldDeployId" class="form-control" placeholder="Paste encrypted Deployment ID from Organization Setup" required>
                                    <div class="vendor-field-hint">AES-256-CBC encrypted — paste to auto-decode client info.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="vFieldExpiry">Expiry Date <span class="text-danger">*</span></label>
                                    <input type="date" id="vFieldExpiry" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="vFieldMaxDomains">Max Domains</label>
                                    <input type="number" id="vFieldMaxDomains" class="form-control" value="1" min="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="vFieldType">License Type</label>
                                    <select id="vFieldType" class="form-control">
                                        <option value="issue">Issue (New)</option>
                                        <option value="renew">Renew (Extension)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="app-form-actions mt-3">
                                <button type="button" class="btn btn-primary" id="vBtnSave"><i class="fas fa-save"></i> Save License</button>
                                <button type="button" class="btn btn-secondary" id="vBtnReset"><i class="fas fa-undo"></i> Reset</button>
                            </div>
                        </form>
                        <div id="vendorGenStatus" class="vendor-console-status mt-2"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5 ps-xl-1">
            <div class="card app-table-card">
                <div class="card-body no-padding">
                    <div class="log-title-wrapper app-table-title">
                        <span><i class="fas fa-microchip text-secondary me-2"></i>Console Feedback</span>
                    </div>
                    <div class="p-3">
                        <div id="vendorConsoleLog" class="vendor-console-log">
                            <div class="vendor-console-line text-muted">// Vendor console ready</div>
                        </div>
                        <div class="app-form-actions mt-2">
                            <button type="button" class="btn btn-secondary" id="vBtnRefreshList" title="Refresh license list"><i class="fas fa-sync-alt"></i> Refresh</button>
                            <button type="button" class="btn btn-secondary" id="vBtnClearLog" title="Clear log"><i class="fas fa-trash-alt"></i> Clear</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card app-table-card">
                <div class="card-body no-padding">
                    <div class="log-title-wrapper app-table-title">
                        <span><i class="fas fa-key text-danger me-2"></i>Signing Key</span>
                        <span id="vKeyStatusBadge" class="vendor-key-badge">Checking...</span>
                    </div>
                    <div class="p-3" id="vKeySection">
                        <p class="vendor-console-note mb-2" id="vKeyInfo">
                            <i class="fas fa-info-circle me-1"></i>
                            Upload your RSA private key (PEM) to enable automatic RSA-SHA256 signing on download.
                        </p>
                        <div id="vKeyUploadArea">
                            <textarea id="vKeyInput" class="form-control mb-2 vendor-key-textarea" rows="4" placeholder="-----BEGIN RSA PRIVATE KEY-----&#10;...paste key...&#10;-----END RSA PRIVATE KEY-----"></textarea>
                            <div class="app-form-actions">
                                <button type="button" class="btn btn-primary" id="vBtnSaveKey"><i class="fas fa-upload"></i> Upload Key</button>
                                <button type="button" class="btn btn-secondary" id="vBtnDeleteKey"><i class="fas fa-trash"></i> Remove Key</button>
                            </div>
                            <div id="vendorKeyStatus" class="vendor-console-status mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card app-table-card">
                <div class="card-body no-padding">
                    <div class="log-title-wrapper app-table-title">
                        <span><i class="fas fa-box text-warning me-2"></i>Client Release Pack</span>
                    </div>
                    <div class="p-3">
                        <div class="vendor-guide">
                            <div class="vendor-guide-step">
                                <span class="vendor-guide-num"><i class="fas fa-box" style="font-size:0.6rem;"></i></span>
                                <div>Export a clean delivery-ready application package — strips vendor-only files (<code>license_admin_templates</code>, <code>codebase_upgrade_plan</code>) and downloads as <strong>zip</strong>. Nothing stored on server.</div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="vFieldOrgName">Organization Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="vFieldOrgName" placeholder="e.g. Acme Corp" value="<?= htmlspecialchars(config_get('org_name', '')) ?>">
                        </div>
                        <div class="app-form-actions">
                            <button type="button" class="btn btn-primary" id="vBtnBuildRelease"><i class="fas fa-cube me-1"></i>Build &amp; Download</button>
                        </div>
                        <div id="vendorReleaseStatus" class="vendor-console-status mt-2"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card app-table-card">
                <div class="card-body no-padding">
                    <div class="log-title-wrapper app-table-title">
                        <span><i class="fas fa-table-list text-info me-2"></i>License Tracking</span>
                        <span id="vLicenseCount" class="vendor-count-badge">0</span>
                    </div>
                    <div class="log-table-wrapper app-table-wrapper" style="max-height:420px;">
                        <table class="table app-data-table log-table mb-0 table-hover" id="vLicenseTable">
                            <thead>
                                <tr>
                                    <th>License ID</th>
                                    <th>Client</th>
                                    <th>Domain</th>
                                    <th>Type</th>
                                    <th>Created</th>
                                    <th>Expires On</th>
                                    <th>Remaining</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="vLicenseBody">
                                <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-1"></i> Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card app-table-card">
                <div class="card-body no-padding">
                    <div class="log-title-wrapper app-table-title">
                        <span><i class="fas fa-folder-open text-info me-2"></i>Documents</span>
                        <span style="font-size:0.7rem;font-weight:400;color:#6c757d;margin-left:auto;">auto-detected from /docs</span>
                    </div>
                    <div class="p-3" style="max-height:500px;overflow-y:auto;">
<?php
$docsBase = realpath(__DIR__ . '/../../../../docs');
$docUrlBase = admin_page_url('license_doc', []);

function scan_docs($dir, $baseRel = '') {
    $items = [];
    $entries = scandir($dir);
    foreach ($entries as $e) {
        if ($e[0] === '.') continue;
        $path = $dir . '/' . $e;
        $rel = $baseRel ? $baseRel . '/' . $e : $e;
        if (is_dir($path)) {
            $children = scan_docs($path, $rel);
            if ($children) {
                $items[] = [
                    'name' => $e,
                    'rel' => $rel,
                    'type' => 'dir',
                    'children' => $children,
                ];
            }
        } elseif (pathinfo($e, PATHINFO_EXTENSION) === 'md') {
            $items[] = [
                'name' => pathinfo($e, PATHINFO_FILENAME),
                'rel' => preg_replace('/\.md$/', '', $rel),
                'type' => 'file',
            ];
        }
    }
    return $items;
}

function render_doc_tree($items, $urlBase) {
    $html = '<ul class="doc-tree-list">';
    foreach ($items as $item) {
        if ($item['type'] === 'dir') {
            $html .= '<li class="doc-tree-folder">';
            $name = htmlspecialchars($item['name']);
            $html .= '<div class="doc-tree-folder-label"><i class="fas fa-folder-open text-warning me-1"></i>' . $name . '</div>';
            $html .= render_doc_tree($item['children'], $urlBase);
            $html .= '</li>';
        } else {
            $url = $urlBase . '&doc=' . urlencode($item['rel']);
            $name = htmlspecialchars(str_replace('_', ' ', $item['name']));
            $html .= '<li class="doc-tree-file">';
            $html .= '<a href="' . $url . '" target="_blank" class="doc-tree-link"><i class="fas fa-file-alt text-muted me-1"></i>' . $name . '</a>';
            $html .= '</li>';
        }
    }
    $html .= '</ul>';
    return $html;
}

$tree = scan_docs($docsBase);
echo render_doc_tree($tree, $docUrlBase);
?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<style>
.doc-tree-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.doc-tree-list ul {
    list-style: none;
    padding: 0 0 0 20px;
    margin: 2px 0 4px;
}
.doc-tree-folder > .doc-tree-list {
    display: none;
}
.doc-tree-folder.open > .doc-tree-list {
    display: block;
}
.doc-tree-folder-label {
    cursor: pointer;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 3px 6px;
    border-radius: 4px;
    color: #334155;
    user-select: none;
}
.doc-tree-folder-label:hover {
    background: #f0f2f5;
}
.doc-tree-folder-label::before {
    content: '\f0da';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    display: inline-block;
    margin-right: 6px;
    font-size: 0.7rem;
    transition: transform 0.15s ease;
    color: #8b2eb8;
}
.doc-tree-folder.open > .doc-tree-folder-label::before {
    transform: rotate(90deg);
}
.doc-tree-file {
    font-size: 0.82rem;
    padding: 2px 6px 2px 24px;
}
.doc-tree-link {
    text-decoration: none;
    color: #2563eb;
    display: inline-block;
    padding: 2px 4px;
    border-radius: 3px;
    transition: background 0.15s ease;
}
.doc-tree-link:hover {
    background: #e8f0fe;
    text-decoration: underline;
}
</style>
<script>
(function() {
    var container = document.querySelector('.doc-tree-folder-label');
    if (container) {
        document.addEventListener('click', function(e) {
            var label = e.target.closest('.doc-tree-folder-label');
            if (label) {
                var folder = label.closest('.doc-tree-folder');
                if (folder) folder.classList.toggle('open');
            }
        });
        document.querySelectorAll('.doc-tree-folder').forEach(function(f) {
            f.classList.add('open');
        });
    }
})();
</script>

    </div><!-- /vendor-console-content -->
</div>

