<?php
$licenseStatus = $licenseStatus ?? (function_exists('license_get_status') ? license_get_status() : []);
$contact = $licenseStatus['contact'] ?? [];
$policy = $licenseStatus['policy'] ?? [];
$isRestricted = !empty($licenseStatus['is_restricted']);
$isExpired = !empty($licenseStatus['is_expired']);
$isWarning = !empty($licenseStatus['is_warning']);
$isGrace = !empty($licenseStatus['is_grace_period']);

$statusLabel = strtoupper((string) ($licenseStatus['status'] ?? 'active'));
$statusKey = strtolower((string) ($licenseStatus['status'] ?? 'active'));
$canManageLicense = function_exists('license_can_manage') && license_can_manage();
$alertClass = 'success';
$alertIcon = 'fa-check-circle';
if ($isRestricted || $statusKey === 'expired' || $statusKey === 'missing_certificate' || $statusKey === 'invalid_signature') {
    $alertClass = 'danger';
    $alertIcon = $statusKey === 'missing_certificate' ? 'fa-file-excel' : ($statusKey === 'invalid_signature' ? 'fa-bug' : 'fa-lock');
} elseif ($isWarning || $isGrace || $statusKey === 'warning' || $statusKey === 'grace_period') {
    $alertClass = 'warning';
    $alertIcon = $statusKey === 'grace_period' ? 'fa-clock' : 'fa-exclamation-triangle';
}
?>
<div class="license-status-content slide-in-top">
    <div class="row">
        <div class="col-12">
            <div class="status-banner <?= $alertClass ?>">
                <div class="status-banner-icon"><i class="fas <?= $alertIcon ?>"></i></div>
                <div>
                    <div class="status-banner-title"><?= $statusLabel ?> STATUS</div>
                    <div class="status-banner-msg"><?= htmlspecialchars($licenseStatus['message'] ?? ($statusKey === 'active' ? 'Your license is fully active and verified.' : 'License state requires attention.')) ?></div>
                </div>
                <?php if ($isRestricted): ?>
                <span class="status-banner-restricted"><i class="fas fa-ban"></i>RESTRICTED</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Certificate Card -->
        <div class="col-xl-7">
            <div class="row">
                <div class="col-12">
                    <div class="cert-card">
                <div id="licenseAccentTop" class="cert-card-accent <?= $isRestricted ? 'accent-danger' : (($isWarning || $isGrace) ? 'accent-warning' : 'accent-active') ?>"></div>
                <div class="cert-card-inner">
                    <div class="cert-card-frame"></div>
                    <div class="cert-card-content">
                        <div class="text-center mb-3">
                            <div class="cert-card-subtitle">Certificate of Authenticity</div>
                            <h1 id="licenseProductName" class="cert-card-product"><?= htmlspecialchars($licenseStatus['issued_to'] ?? ($licenseStatus['product_name'] ?? 'Authorized Deployment')) ?></h1>
                            <div class="cert-card-divider">
                                <div class="cert-card-divider-line"></div>
                                <i class="fas fa-certificate cert-card-divider-icon"></i>
                                <div class="cert-card-divider-line"></div>
                            </div>
                            <div class="cert-card-quote px-3">
                                This digital instrument serves as authoritative proof of entitlement. Active cryptographic binding is established with the designated host domain.
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <div class="cert-card-meta">
                                    <div class="cert-card-label">Licensed Organisation</div>
                                    <div id="licenseIssuedTo" class="cert-card-value"><?= htmlspecialchars($licenseStatus['issued_to'] ?? 'Authorized Deployment') ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="cert-card-meta">
                                    <div class="cert-card-label">Bound Domain</div>
                                    <div id="licenseDomain" class="cert-card-value"><?= htmlspecialchars($licenseStatus['domain_name'] ?? 'Localhost') ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="cert-card-meta">
                                    <div class="cert-card-label">Certification ID</div>
                                    <div id="licenseId" class="cert-card-value id"><?= htmlspecialchars($licenseStatus['license_id'] ?? 'CERT-SYNC-PENDING') ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="cert-card-meta">
                                    <div class="cert-card-label">Activation Timestamp</div>
                                    <div id="licenseIssuedAt" class="cert-card-value date"><?= htmlspecialchars($licenseStatus['issued_at'] ?? 'N/A') ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="cert-card-meta">
                                    <div class="cert-card-label">Expires On</div>
                                    <div id="licenseExpiresOn" class="cert-card-value date"><?= htmlspecialchars($licenseStatus['expires_on'] ?? 'N/A') ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="cert-card-meta">
                                    <div class="cert-card-label">Deployment ID</div>
                                    <div id="licenseDeployId" class="cert-card-value id"><?= htmlspecialchars($licenseStatus['deployment_id'] ?? 'N/A') ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="cert-card-meta">
                                    <div class="cert-card-label">Domain Entitlement</div>
                                    <div id="licenseMaxDomains" class="cert-card-value"><?php
                                        $maxDomains = (int) ($licenseStatus['max_domains'] ?? 1);
                                        $used = (int) ($licenseStatus['domains_used'] ?? 1);
                                        $remaining = (int) ($licenseStatus['domains_remaining'] ?? 0);
                                        if ($maxDomains === 0): ?><span style="color:#16a34a;">Unlimited <span style="font-size:0.6rem;color:#64748b;">(<?= $used ?> configured)</span></span><?php
                                        else:
                                            echo $used . ' / ' . $maxDomains . ' used';
                                            if ($remaining > 0): ?> <span style="font-size:0.6rem;color:#64748b;">(<?= $remaining ?> remaining)</span><?php
                                            else: ?> <span style="font-size:0.6rem;color:#dc2626;">(limit reached)</span><?php
                                            endif;
                                        endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="cert-card-timer-box mb-3">
                            <div class="cert-card-timer-label">Remaining Operational Window</div>
                            <div id="cert-timer-live" class="cert-card-timer-digits">00 : 00 : 00 : 00</div>
                        </div>

                        <div class="cert-card-declaration mb-3">
                            <div class="cert-card-declaration-label">Declaration Statement</div>
                            <p id="licenseDeclarationText" class="cert-card-declaration-text">
                                This certificate confirms that <strong><?= htmlspecialchars($licenseStatus['issued_to'] ?? 'Authorized Deployment') ?></strong> holds an authorized deployment entitlement for <strong><?= htmlspecialchars($licenseStatus['product_name'] ?? 'AccessPilot') ?></strong>. Valid through <strong><?= htmlspecialchars($licenseStatus['expires_on'] ?? 'N/A') ?></strong>.
                            </p>
                        </div>

                        <div class="cert-card-footer">
                            <i class="fas fa-shield-halved cert-card-footer-shield"></i>
                            <div class="text-end">
                                <div class="cert-card-footer-brand">AccessPilot</div>
                                <div class="cert-card-footer-auth">Licensing Authority</div>
                            </div>
                        </div>
                    </div>

                    <div class="cert-card-seal">
                        <div class="cert-card-seal-ring"></div>
                        <div class="cert-card-seal-inner">
                            <i class="fas fa-shield-check"></i>
                            <span>SECURE</span>
                        </div>
                    </div>
                </div>
            </div>
                </div>
            </div>

            <?php if ($canManageLicense): ?>
            <div class="row">
                <div class="col-12">
                    <div class="sync-panel">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="fas fa-sync-alt sync-panel-title-icon me-2"></i>
                            <h6 class="sync-panel-title mb-0">Sync Material</h6>
                        </div>
                        <textarea id="licenseInputBox" class="form-control sync-panel-textarea mb-3" rows="3" placeholder="Paste signed PEM license or upload .pem file..."></textarea>
                        <div class="sync-file-area mb-3">
                            <div class="sync-file-divider"><span>or</span></div>
                            <label class="sync-file-btn" for="licenseFileInput">
                                <i class="fas fa-upload"></i> Upload Certificate File
                            </label>
                            <input type="file" id="licenseFileInput" accept=".pem" style="display:none">
                            <div id="licenseFileName" class="sync-file-name"></div>
                        </div>
                        <button type="button" class="sync-panel-btn" id="applyLicenseButton">Synchronize Renewal</button>
                        <div id="licenseApplyStatus" class="sync-panel-status"></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Vendor Support, Lifecycle, Verification, Terms -->
        <div class="col-xl-5">
            <div class="row">
                <div class="col-12">
                    <div class="card app-table-card">
                        <div class="card-body no-padding">
                            <div class="log-title-wrapper app-table-title">
                                <span><i class="fas fa-headset text-primary me-1"></i>Vendor Support</span>
                            </div>
                            <div class="p-3">
                                <div class="info-row">
                                    <div class="info-row-icon"><i class="fas fa-building"></i></div>
                                    <div>
                                        <div class="info-row-label">Organisation</div>
                                        <div class="info-row-value"><?= htmlspecialchars($contact['company'] ?? 'AccessPilot Global') ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-row-icon"><i class="fas fa-envelope"></i></div>
                                    <div>
                                        <div class="info-row-label">Support Email</div>
                                        <div class="info-row-value email"><?= htmlspecialchars($contact['email'] ?? 'licensing@accesspilot.io') ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-row-icon"><i class="fas fa-phone"></i></div>
                                    <div>
                                        <div class="info-row-label">Direct Help</div>
                                        <div class="info-row-value"><?= htmlspecialchars($contact['phone'] ?? 'N/A') ?></div>
                                    </div>
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
                                <span><i class="fas fa-info-circle text-primary me-1"></i>Deployment Lifecycle & Behavior</span>
                            </div>
                            <div class="p-3">
                                <div class="lifecycle-summary">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span id="lifecycleSummaryBadge" class="lifecycle-summary-badge" style="background:<?= $isRestricted ? 'rgba(220,38,38,0.2)' : (($isWarning || $isGrace) ? 'rgba(245,158,11,0.2)' : 'rgba(22,163,74,0.2)') ?>;color:<?= $isRestricted ? '#dc2626' : (($isWarning || $isGrace) ? '#f59e0b' : '#16a34a') ?>;"><?php
                                        $badgeText = 'ACTIVE CERTIFICATE';
                                        $phase = 1;
                                        if ($isRestricted) { $badgeText = 'SERVICE LOCK'; $phase = 4; }
                                        elseif ($isExpired) { $badgeText = 'SERVICE LOCK'; $phase = 4; }
                                        elseif ($isGrace) { $badgeText = 'GRACE WINDOW'; $phase = 3; }
                                        elseif ($isWarning) { $badgeText = 'EXPIRY WATCH'; $phase = 2; }
                                        echo $badgeText;
                                        ?></span>
                                        <h6 id="lifecycleSummaryTitle" class="lifecycle-summary-title"><?php
                                        $phaseTitle = 'Phase 1: Healthy Operation';
                                        if ($phase === 4) $phaseTitle = 'Phase 4: Restricted Operation';
                                        elseif ($phase === 3) $phaseTitle = 'Phase 3: Controlled Continuity';
                                        elseif ($phase === 2) $phaseTitle = 'Phase 2: Renewal Advisory';
                                        echo $phaseTitle;
                                        ?></h6>
                                    </div>
                                    <p id="lifecycleSummaryDetail" class="lifecycle-summary-detail"><?php
                                    $phaseDetail = 'All platform capabilities remain fully available. The certificate is valid, the host binding is verified, and administrative workflows continue without restriction.';
                                    if ($phase === 4) $phaseDetail = 'The operational window has closed. Read-only access may remain, but data-modifying actions, automation routines, and protected APIs are blocked until a valid certificate is synchronized.';
                                    elseif ($phase === 3) $phaseDetail = 'The signed entitlement has reached expiry, yet the platform continues under the approved grace window to support controlled continuity and completion of pending operations.';
                                    elseif ($phase === 2) $phaseDetail = 'The license remains active, but the renewal window is approaching. Administrative operators should prepare a fresh signed certificate before the entitlement enters grace.';
                                    echo $phaseDetail;
                                    ?></p>
                                </div>
                                <div class="row g-2">
                                    <?php $phases = [
                                        ['num' => 1, 'label' => 'Phase 1: Healthy', 'desc' => 'Fully operational. All automation and security features enabled.', 'color' => '#16a34a'],
                                        ['num' => 2, 'label' => 'Phase 2: Warning', 'desc' => '90 days to expiry. Renewal banners displayed to operators.', 'color' => '#f59e0b'],
                                        ['num' => 3, 'label' => 'Phase 3: Grace', 'desc' => '7 days of extended functionality post-expiry.', 'color' => '#ea580c'],
                                        ['num' => 4, 'label' => 'Phase 4: Lock', 'desc' => 'Read-only mode. Mutating operations and APIs are blocked.', 'color' => '#dc2626'],
                                    ]; foreach ($phases as $p): ?>
                                    <div class="col-md-6">
                                        <div class="phase-item<?= $phase === $p['num'] ? ' current phase-' . $p['num'] : '' ?>"<?= $phase === $p['num'] ? ' style="border-left-color:' . $p['color'] . '"' : '' ?>>
                                            <div class="phase-item-label"><?= $p['label'] ?></div>
                                            <p class="phase-item-desc"><?= $p['desc'] ?></p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
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
                                <span><i class="fas fa-shield-alt text-warning me-1"></i>Verification Info</span>
                            </div>
                            <div class="p-3">
                                <div class="verify-row"><i class="fas fa-fingerprint"></i><span>Every certificate is bound to a specific domain and product identity.</span></div>
                                <div class="verify-row"><i class="fas fa-lock"></i><span>Signed license state is maintained in protected storage.</span></div>
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
                                <span><i class="fas fa-file-contract text-info me-1"></i>Usage Terms</span>
                            </div>
                            <div class="p-3">
                                <ul class="terms-list">
                                    <?php foreach (($policy['clauses'] ?? ['Bound to Domain', 'RSA-2048 Verification', 'Forensic Logging Active']) as $clause): ?>
                                    <li><i class="fas fa-check-circle"></i><span><?= htmlspecialchars((string) $clause) ?></span></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php if ($isRestricted || $isWarning || $isGrace): ?>
    <div class="row mb-0">
        <div class="col-12">
            <div class="callout-warning <?= $isRestricted ? 'danger' : 'amber' ?>">
                <div class="d-flex align-items-start gap-3">
                    <div class="callout-warning-icon <?= $isRestricted ? 'danger' : 'amber' ?>"><i class="fas <?= $isRestricted ? 'fa-exclamation-circle' : 'fa-clock' ?>"></i></div>
                    <div>
                        <div class="callout-warning-title <?= $isRestricted ? 'danger' : 'amber' ?>"><?= $isRestricted ? 'Action Required' : 'Renewal Advisory' ?></div>
                        <p class="callout-warning-text <?= $isRestricted ? 'danger' : 'amber' ?>">
                            <?php if ($isRestricted): ?>
                            This deployment is currently operating in a restricted state. Some platform features and data-modifying operations are limited. Please submit a valid signed certificate to restore full functionality.
                            <?php else: ?>
                            Your license is approaching its expiration date. To avoid service disruption, please prepare a renewed certificate before the current entitlement enters the grace window.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    const initialStatus = <?= json_encode($licenseStatus, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let expiryWithGrace = calcExpiry(<?= json_encode($licenseStatus['expires_on'] ?? null) ?>, <?= (int)($licenseStatus['grace_days'] ?? 7) ?>);
    let countdownInterval = null;
    const timerEl = document.getElementById('cert-timer-live');
    const declarationEl = document.getElementById('licenseDeclarationText');
    const productEl = document.getElementById('licenseProductName');
    const issuedToEl = document.getElementById('licenseIssuedTo');
    const domainEl = document.getElementById('licenseDomain');
    const licenseIdEl = document.getElementById('licenseId');
    const issuedAtEl = document.getElementById('licenseIssuedAt');
    const deployIdEl = document.getElementById('licenseDeployId');
    const expiresOnEl = document.getElementById('licenseExpiresOn');
    const bannerEl = document.querySelector('.status-banner');
    const accentEl = document.getElementById('licenseAccentTop');
    const summaryBadgeEl = document.getElementById('lifecycleSummaryBadge');
    const summaryTitleEl = document.getElementById('lifecycleSummaryTitle');
    const summaryDetailEl = document.getElementById('lifecycleSummaryDetail');
    const phaseItems = Array.from(document.querySelectorAll('.phase-item'));
    const statusConfig = {
        active: { alertClass: 'success', icon: 'fa-check-circle', toneClass: 'accent-active', phase: 1, badge: 'ACTIVE CERTIFICATE', title: 'Phase 1: Healthy Operation', detail: 'All platform capabilities remain fully available. The certificate is valid, the host binding is verified, and administrative workflows continue without restriction.' },
        warning: { alertClass: 'warning', icon: 'fa-exclamation-triangle', toneClass: 'accent-warning', phase: 2, badge: 'EXPIRY WATCH', title: 'Phase 2: Renewal Advisory', detail: 'The license remains active, but the renewal window is approaching. Administrative operators should prepare a fresh signed certificate before the entitlement enters grace.' },
        grace_period: { alertClass: 'warning', icon: 'fa-clock', toneClass: 'accent-warning', phase: 3, badge: 'GRACE WINDOW', title: 'Phase 3: Controlled Continuity', detail: 'The signed entitlement has reached expiry, yet the platform continues under the approved grace window to support controlled continuity and completion of pending operations.' },
        expired: { alertClass: 'danger', icon: 'fa-lock', toneClass: 'accent-danger', phase: 4, badge: 'SERVICE LOCK', title: 'Phase 4: Restricted Operation', detail: 'The operational window has closed. Read-only access may remain, but data-modifying actions, automation routines, and protected APIs are blocked until a valid certificate is synchronized.' },
        missing_certificate: { alertClass: 'danger', icon: 'fa-file-excel', toneClass: 'accent-danger', phase: 4, badge: 'CERTIFICATE MISSING', title: 'Phase 4: Restricted Operation', detail: 'No signed certificate is present. The deployment remains in a locked posture until a valid license package is submitted and verified.' },
        invalid_signature: { alertClass: 'danger', icon: 'fa-bug', toneClass: 'accent-danger', phase: 4, badge: 'SIGNATURE FAILED', title: 'Phase 4: Restricted Operation', detail: 'The submitted certificate could not be validated. The deployment is intentionally restricted until a trusted vendor signature is applied.' },
    };

    function statusIsRenderable(status) {
        return !!(status && (status.expires_on || status.issued_to || status.product_name));
    }

    function buildDeclaration(status) {
        const issuedTo = status.issued_to || 'Authorized Deployment';
        const product = status.product_name || 'AccessPilot';
        const domain = status.domain_name || 'Localhost';
        const deployId = status.deployment_id || 'N/A';
        const expiresOn = status.expires_on || 'N/A';
        return 'This certificate confirms that ' + issuedTo + ' holds an authorized deployment entitlement for ' + product + ' bound to domain ' + domain + ' (DID: ' + deployId + '). Unless renewed or superseded by a newly signed vendor certificate, this entitlement remains valid through ' + expiresOn + ' and is subject to the platform\'s published operational policy and grace-window controls.';
    }

    function applyLicenseState(status) {
        if (!status) return;
        const key = (status.status || 'active').toLowerCase();
        const cfg = statusConfig[key] || statusConfig.active;
        const colors = cfg.alertClass === 'danger' ? { bg: 'linear-gradient(135deg,#fef2f2,#fee2e2)', border: '#dc2626', icon: '#dc2626', iconBg: 'rgba(220,38,38,0.12)', title: '#991b1b', msg: '#7f1d1d' } : (cfg.alertClass === 'warning' ? { bg: 'linear-gradient(135deg,#fffbeb,#fef3c7)', border: '#f59e0b', icon: '#d97706', iconBg: 'rgba(245,158,11,0.12)', title: '#92400e', msg: '#78350f' } : { bg: 'linear-gradient(135deg,#f0fdf4,#dcfce7)', border: '#16a34a', icon: '#16a34a', iconBg: 'rgba(22,163,74,0.12)', title: '#166534', msg: '#14532d' });

        if (productEl) productEl.textContent = status.issued_to || status.product_name || 'Authorized Deployment';
        if (issuedToEl) issuedToEl.textContent = status.issued_to || 'Authorized Deployment';
        if (domainEl) domainEl.textContent = status.domain_name || 'Localhost';
        if (licenseIdEl) licenseIdEl.textContent = status.license_id || 'CERT-SYNC-PENDING';
        if (issuedAtEl) issuedAtEl.textContent = status.issued_at || 'N/A';
        if (deployIdEl) deployIdEl.textContent = status.deployment_id || 'N/A';
        if (expiresOnEl) expiresOnEl.textContent = status.expires_on || 'N/A';

        var maxDomainsEl = document.getElementById('licenseMaxDomains');
        if (maxDomainsEl && status.max_domains !== undefined) {
            var max = parseInt(status.max_domains) || 1;
            var used = parseInt(status.domains_used) || 1;
            var rem = parseInt(status.domains_remaining) || 0;
            if (max === 0) {
                maxDomainsEl.innerHTML = '<span style="color:#16a34a;">Unlimited <span style="font-size:0.6rem;color:#64748b;">(' + used + ' configured)</span></span>';
            } else {
                var html = used + ' / ' + max + ' used';
                if (rem > 0) html += ' <span style="font-size:0.6rem;color:#64748b;">(' + rem + ' remaining)</span>';
                else html += ' <span style="font-size:0.6rem;color:#dc2626;">(limit reached)</span>';
                maxDomainsEl.innerHTML = html;
            }
        }

        if (declarationEl) declarationEl.textContent = buildDeclaration(status);

        if (bannerEl) {
            bannerEl.className = 'status-banner ' + cfg.alertClass;
            const iconWrap = bannerEl.querySelector('.status-banner-icon');
            if (iconWrap) {
                const i = iconWrap.querySelector('i');
                if (i) i.className = 'fas ' + cfg.icon;
            }
            const titleEl = bannerEl.querySelector('.status-banner-title');
            if (titleEl) titleEl.textContent = key.toUpperCase() + ' STATUS';
            const msgEl = bannerEl.querySelector('.status-banner-msg');
            if (msgEl) msgEl.textContent = (status.message || 'License state synchronized.') + ' (Binding: deployment identity verified)';
        }

        if (accentEl) {
            accentEl.className = 'cert-card-accent ' + cfg.toneClass;
        }

        if (summaryBadgeEl) {
            var bc = '#16a34a', bb = 'rgba(22,163,74,0.2)';
            if (cfg.phase === 2) { bc = '#f59e0b'; bb = 'rgba(245,158,11,0.2)'; }
            else if (cfg.phase === 3) { bc = '#ea580c'; bb = 'rgba(234,88,12,0.2)'; }
            else if (cfg.phase === 4) { bc = '#dc2626'; bb = 'rgba(220,38,38,0.2)'; }
            summaryBadgeEl.style.background = bb;
            summaryBadgeEl.style.color = bc;
            summaryBadgeEl.textContent = cfg.badge;
        }
        if (summaryTitleEl) summaryTitleEl.textContent = cfg.title;
        if (summaryDetailEl) summaryDetailEl.textContent = cfg.detail;

        phaseItems.forEach(function(item, index) {
            var phase = index + 1;
            var isCurrent = cfg.phase === phase;
            var clr = phase === 1 ? '#16a34a' : (phase === 2 ? '#f59e0b' : (phase === 3 ? '#ea580c' : '#dc2626'));
            item.className = 'phase-item' + (isCurrent ? ' current phase-' + phase : '');
            item.style.borderLeftColor = isCurrent ? clr : '#e2e8f0';
            var label = item.querySelector('.phase-item-label');
            if (label) label.style.color = isCurrent ? clr : '#94a3b8';
        });

        if (status.expires_on) {
            expiryWithGrace = calcExpiry(status.expires_on, status.grace_days);
        }
        if (countdownInterval) clearInterval(countdownInterval);
        updateTimer();
        if (expiryWithGrace) countdownInterval = setInterval(updateTimer, 1000);
    }

    async function refreshLicenseState() {
        try {
            var apiUrl = '<?= function_exists('api_url') ? api_url() : 'api/index.php' ?>';
            var response = await fetch(apiUrl + '?endpoint=license_api', { credentials: 'same-origin' });
            var res = await response.json();
            if (res && res.success && statusIsRenderable(res.status)) applyLicenseState(res.status);
        } catch (e) { updateTimer(); }
    }

    function calcExpiry(expiresOn, gDays) {
        if (!expiresOn) return null;
        var d = new Date(String(expiresOn) + 'T23:59:59');
        if (isNaN(d.getTime())) return null;
        d.setDate(d.getDate() + (parseInt(gDays) || 7));
        return d;
    }

    function formatDuration(expDate) {
        if (!expDate) return 'PENDING LICENSE';
        var now = new Date();
        if (now >= expDate) return 'EXPIRED';
        var y = expDate.getFullYear() - now.getFullYear();
        var mo = expDate.getMonth() - now.getMonth();
        var d = expDate.getDate() - now.getDate();
        var h = expDate.getHours() - now.getHours();
        var mi = expDate.getMinutes() - now.getMinutes();
        var s = expDate.getSeconds() - now.getSeconds();
        if (s < 0) { mi--; s += 60; }
        if (mi < 0) { h--; mi += 60; }
        if (h < 0) { d--; h += 24; }
        if (d < 0) { mo--;
            var pm = new Date(expDate.getFullYear(), expDate.getMonth() - 1, 1);
            d += new Date(pm.getFullYear(), pm.getMonth() + 1, 0).getDate();
        }
        if (mo < 0) { y--; mo += 12; }
        var parts = [];
        if (y > 0) parts.push(y + ' year' + (y > 1 ? 's' : ''));
        if (mo > 0) parts.push(mo + ' month' + (mo > 1 ? 's' : ''));
        if (d > 0) parts.push(d + ' day' + (d > 1 ? 's' : ''));
        if (h > 0) parts.push(h + ' hour' + (h > 1 ? 's' : ''));
        if (mi > 0) parts.push(mi + ' minute' + (mi > 1 ? 's' : ''));
        if (s >= 0 || parts.length === 0) parts.push(s + ' second' + (s !== 1 ? 's' : ''));
        return parts.join(' ') + ' remaining';
    }

    function updateTimer() {
        if (!timerEl) return;
        timerEl.textContent = formatDuration(expiryWithGrace);
        timerEl.style.color = expiryWithGrace && new Date() < expiryWithGrace ? '' : '#b91c1c';
    }

    if (statusIsRenderable(initialStatus)) applyLicenseState(initialStatus);
    else { updateTimer(); refreshLicenseState(); }

    var btn = document.getElementById('applyLicenseButton');
    var box = document.getElementById('licenseInputBox');
    var st = document.getElementById('licenseApplyStatus');
    if (btn) {
        btn.addEventListener('click', async function() {
            var val = box.value.trim();
            if (!val) return;
            btn.disabled = true;
            st.textContent = 'Verifying Handshake...';
            st.style.color = '#06b6d4';
            try {
                var apiUrl = '<?= function_exists('api_url') ? api_url() : 'api/index.php' ?>';
                var response = await fetch(apiUrl + '?endpoint=license_api', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ license_input: val })
                });
                var res = await response.json();
                st.textContent = res.message;
                st.style.color = res.success ? '#16a34a' : '#dc2626';
                if (res.success) applyLicenseState(res.status || null);
            } catch (e) { st.textContent = 'System Communication Error.'; st.style.color = '#dc2626'; }
            finally { btn.disabled = false; }
        });
    }

    var fileInput = document.getElementById('licenseFileInput');
    var fileNameEl = document.getElementById('licenseFileName');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (!file) return;
            fileNameEl.textContent = file.name;
            var reader = new FileReader();
            reader.onload = function(ev) {
                box.value = ev.target.result;
                fileNameEl.textContent = file.name + ' (loaded)';
                if (box.value.trim()) btn.click();
            };
            reader.onerror = function() {
                fileNameEl.textContent = 'Error reading file';
                fileNameEl.style.color = '#dc2626';
            };
            reader.readAsText(file);
        });
    }
})();
</script>
