<?php
global $app_config;
$app_config = include __DIR__ . '/../../../../config/app_config.php';
$GLOBALS['app_config'] = $app_config;
include_once __DIR__ . '/../../../../bootstrap/request_context.php';
require_once __DIR__ . '/../../../../app/Domain/AdUserRequest/ad_user_request_service.php';

$typeMapRaw = ad_user_request_type_map();
$typeMap = [];
$typeMapExchange = [];
foreach ($typeMapRaw as $key => $cfg) {
    if (!empty($cfg['needs_exchange'])) {
        $typeMapExchange[$key] = $cfg['label'];
    } else {
        $typeMap[$key] = $cfg['label'];
    }
}

$securityMessages = [
    'Submit only business-justified requests. Clear context helps admins process faster.',
    'Never share your existing password in the justification box or any form field.',
    'Always verify the target user ID (e.g. 66684) before submitting a request.',
    'Request the minimum access level needed for your specific job role.',
    'Ensure your official email is correct — it is used for tracking and status updates.',
    'Clear and accurate justification details reduce administrative processing delays.',
    'For New User requests, provide the correct HRMS ID to avoid creation failures.',
    'Custom user accounts must follow the username convention: letters, numbers, dot, underscore, hyphen only.',
    'Service account names should reflect the server or operation they belong to.',
    'Duplicate pending requests are blocked — check your history before re-submitting.',
    'Track your request status anytime using the same email or contact you submitted with.',
    'Approved requests are executed automatically. Check the history panel for results.',
];
shuffle($securityMessages);
$securityMessages = array_slice($securityMessages, 0, 8);

$portalDomains = function_exists('ldap_get_domains') ? ldap_get_domains() : [];
if (empty($portalDomains)) {
    $portalDomains = [['key' => 'wgbd', 'label' => 'wgbd.com'], ['key' => 'whildc', 'label' => 'whildc.com']];
}
$portalDomainExchange = [];
foreach ($portalDomains as $d) {
    $dk = $d['key'] ?? '';
    if ($dk !== '') {
        $portalDomainExchange[$dk] = !empty($d['exchange']['enabled']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Portal - <?= htmlspecialchars($app_config['app_info']['name']) ?></title>
    <link rel="icon" href="<?= $baseURL . $app_config['app_info']['favicon_path'] ?>" type="image/x-icon">
    <link href="<?= $baseURL ?>/vendor/roboto/roboto.css?v=<?= $app_config['app_info']['version'] ?>" rel="stylesheet">
    <link href="<?= $baseURL ?>/vendor/bootstrap/bootstrap.min.css?v=<?= $app_config['app_info']['version'] ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseURL ?>/vendor/fontawesome/all.min.css?v=<?= $app_config['app_info']['version'] ?>">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338CA;
            --primary-light: rgba(79, 70, 229, 0.12);
            --secondary: #7C3AED;
            --accent: #06B6D4;
            --bg: #EEF2FF;
            --card-bg: #ffffff;
            --card-border: rgba(198, 196, 197, 0.4);
            --text: #0F172A;
            --text-muted: #94A3B8;
            --text-secondary: #475569;
            --input-bg: #F8FAFC;
            --input-border: #E2E8F0;
            --radius: 12px;
            --radius-sm: 7px;
            --radius-xs: 4px;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.04), 0 10px 15px -3px rgba(79,70,229,0.06), 0 20px 40px -4px rgba(0,0,0,0.08);
            --shadow-hover: 0 8px 20px rgba(79,70,229,0.12), 0 20px 40px -4px rgba(0,0,0,0.08);
            --font: 'Roboto', 'Kalpurush', system-ui, -apple-system, sans-serif;
        }
        html { overflow-y: scroll; scroll-behavior: smooth; }
        body {
            margin: 0; padding: 0;
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        .portal-wrap { max-width: 1320px; margin: 0 auto; padding: 2rem 1.5rem; }

        .card-s1 {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            position: relative;
        }
        .card-s1::before {
            content: '';
            position: absolute;
            top: -1px; left: 25%; right: 25%;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(79,70,229,0.3), rgba(6,182,212,0.3), transparent);
            border-radius: 2px;
            pointer-events: none;
        }
        .card-s1:hover { box-shadow: var(--shadow-hover); transform: translateY(-1px); }
        .card-s1-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid var(--input-border);
            min-height: 48px;
        }
        .card-s1-header-title {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.85rem; font-weight: 700; color: var(--primary);
            letter-spacing: 0.01em;
        }
        .card-s1-body { padding: 1.25rem; }
        .card-s1-body .form-label {
            font-size: 0.78rem; font-weight: 600; color: var(--text-secondary);
            margin-bottom: 0.2rem; display: block;
        }
        .card-s1-body .form-control,
        .card-s1-body .form-select {
            font-size: 0.82rem; padding: 0.5rem 0.65rem;
            border-radius: var(--radius-xs);
            border: 1.5px solid var(--input-border);
            background: var(--input-bg);
            font-family: var(--font);
            color: var(--text);
            transition: all 0.2s cubic-bezier(0.4,0,0.2,1);
        }
        .card-s1-body .form-control:focus,
        .card-s1-body .form-select:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(79,70,229,0.1);
            transform: scale(1.005);
        }
        .card-s1-body .input-group-text {
            font-size: 0.78rem; padding: 0.5rem 0.65rem;
            background: var(--input-bg); border: 1.5px solid var(--input-border);
            color: var(--text-secondary); font-weight: 500;
        }
        .card-s1-body textarea.form-control { font-size: 0.82rem; }

        .brand-logo { height: 36px; width: auto; border-radius: 8px; }
        .brand-wrap { display: flex; align-items: center; gap: 0.85rem; }
        .brand-title { font-size: 1.05rem; font-weight: 800; color: var(--text); letter-spacing: -0.01em; margin: 0; }
        .brand-sub { font-size: 0.65rem; color: var(--text-muted); font-weight: 400; }

        .broadcast-trigger {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.35rem 0.85rem; background: var(--card-bg);
            border: 1px solid var(--input-border); border-radius: 20px;
            cursor: pointer; font-size: 0.68rem; font-weight: 600;
            color: #dc2626; transition: all 0.2s; user-select: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .broadcast-trigger:hover { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,0.08); }
        .broadcast-dot {
            width: 6px; height: 6px; background: #dc2626;
            border-radius: 50%; animation: pulse-dot 1.5s ease-in-out infinite;
        }
        @keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.4;transform:scale(0.75)} }
        .broadcast-panel {
            display: none; position: fixed;
            width: 340px; background: var(--card-bg);
            border-radius: var(--radius-sm); box-shadow: var(--shadow);
            border: 1px solid var(--card-border); z-index: 9999; overflow: hidden;
        }
        .broadcast-panel.show { display: block; }
        .broadcast-panel .panel-head {
            background: var(--text); color: #fff;
            padding: 0.55rem 0.85rem;
            font-size: 0.68rem; font-weight: 700;
            display: flex; justify-content: space-between; align-items: center;
        }
        .broadcast-panel .panel-body { max-height: 300px; overflow-y: auto; padding: 0; }
        .broadcast-item { padding: 0.55rem 0.85rem; border-bottom: 1px solid var(--input-border); font-size: 0.78rem; color: var(--text); }
        .broadcast-item:last-child { border-bottom: none; }
        .broadcast-item .time { font-size: 0.6rem; color: var(--text-muted); margin-top: 2px; display: block; }

        .ticker-bar {
            display: flex; align-items: center; gap: 0.65rem;
            padding: 0.55rem 1.25rem;
            border-top: 1px solid var(--input-border);
            background: rgba(79,70,229,0.04);
        }
        .ticker-bar .badge {
            background: var(--primary); font-size: 0.5rem; font-weight: 800;
            letter-spacing: 0.5px; padding: 0.15rem 0.5rem;
            border-radius: 4px; white-space: nowrap;
        }
        .ticker-bar #ticker-text { font-size: 0.78rem; color: var(--text-secondary); }

        .btn-s1 {
            height: 44px; padding: 0 1.4rem;
            border-radius: var(--radius-sm); font-weight: 600;
            font-size: 0.88rem; display: inline-flex;
            align-items: center; justify-content: center;
            gap: 0.45rem; border: none; cursor: pointer;
            font-family: var(--font);
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            position: relative; overflow: hidden;
        }
        .btn-s1-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            box-shadow: 0 4px 14px rgba(79,70,229,0.35);
        }
        .btn-s1-primary::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.6s ease;
        }
        .btn-s1-primary:hover::before { left: 100%; }
        .btn-s1-primary:hover {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 6px 24px rgba(79,70,229,0.45);
            transform: translateY(-2px) scale(1.02);
            color: #fff;
        }
        .btn-s1-primary:active {
            transform: translateY(0) scale(0.98);
            box-shadow: 0 2px 8px rgba(79,70,229,0.3);
        }
        .btn-s1-secondary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            box-shadow: 0 4px 14px rgba(79,70,229,0.35);
        }
        .btn-s1-secondary::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.6s ease;
        }
        .btn-s1-secondary:hover::before { left: 100%; }
        .btn-s1-secondary:hover {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 6px 24px rgba(79,70,229,0.45);
            transform: translateY(-2px) scale(1.02);
            color: #fff;
        }
        .btn-s1-secondary:active {
            transform: translateY(0) scale(0.98);
            box-shadow: 0 2px 8px rgba(79,70,229,0.3);
        }

        .history-item {
            padding: 0.75rem 1rem; border-bottom: 1px solid var(--input-border);
            font-size: 0.78rem; transition: background 0.15s;
        }
        .history-item:hover { background: rgba(79,70,229,0.03); }
        .history-item:last-child { border-bottom: none; }
        .history-item .hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem; }
        .history-item .hdr .ttl { font-weight: 700; color: var(--text); }
        .status-badge {
            font-size: 0.55rem; font-weight: 800;
            padding: 0.15rem 0.5rem; border-radius: 8px;
            text-transform: uppercase; letter-spacing: 0.02em;
        }
        .status-pending { background: #FEF3C7; color: #92400E; }
        .status-completed { background: #D1FAE5; color: #065F46; }
        .status-failed { background: #FEE2E2; color: #991B1B; }
        .status-denied { background: #F1F5F9; color: #475569; }
        .history-item .meta { font-size: 0.6rem; color: var(--text-muted); }
        .admin-note {
            margin-top: 0.4rem; padding: 0.35rem 0.6rem;
            background: #FFFBEB; border-left: 3px solid #F59E0B;
            border-radius: 4px; font-size: 0.68rem; color: #78350F;
        }
        .typing-caret {
            display: inline-block; width: 2px; height: 1em;
            background: var(--primary); margin-left: 2px;
            vertical-align: text-bottom; animation: blink 0.6s step-end infinite;
        }
        @keyframes blink { 50% { opacity: 0; } }
        .search-field-group { position: relative; }
        .clear-search-trigger {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: var(--text-muted);
            cursor: pointer; display: none; padding: 0; font-size: 0.9rem;
            z-index: 5;
        }
        .toast-container { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 2000; }
        .broadcast-toast {
            border-radius: 8px; border: 1px solid var(--card-border);
            border-left: 4px solid #dc2626;
            box-shadow: var(--shadow); background: var(--card-bg);
        }
        /* Footer styles */
        .app-signature-footer {
            padding: 0.85rem 0;
            font-size: 10px;
            line-height: 1;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            color: #64748b;
            border-top: 1px solid rgba(148, 163, 184, 0.25);
            margin-top: 8px;
            width: 100%;
            background: transparent;
        }
        .footer-sig-wrapper { display: block; text-align: center; }
        .footer-main-row { display: block; }
        .footer-main-row > span { display: inline; margin: 0 2px; }
        .footer-links-row { display: block; margin-top: 4px; }
        .footer-sig-wrapper a { color: #4F46E5; text-decoration: none; transition: color 0.15s; }
        .footer-sig-wrapper a:hover { color: #4338CA; text-decoration: underline; }
        .footer-sep { color: #64748b; opacity: 0.7; }
        .sig-main-text { font-weight: 700; color: #334155; }
        .sig-dev strong { font-weight: 700; }
        .dot-sep { color: #64748b; opacity: 0.7; margin: 0 5px; font-weight: 700; vertical-align: middle; }

        .portal-header-link {
            font-size: 0.68rem; color: var(--text-muted);
            text-decoration: none; display: inline-flex;
            align-items: center; gap: 0.35rem;
            transition: color 0.2s;
        }
        .portal-header-link:hover { color: var(--primary); }

        @media (max-width: 991.98px) {
            .portal-wrap { padding: 1rem; }
            .broadcast-panel { width: calc(100% - 2rem); }
        }
        @media (max-width: 767.98px) {
            .portal-wrap { padding: 0.75rem; }
            .brand-logo { height: 28px; }
            .brand-title { font-size: 0.95rem; }
            .card-s1 { border-radius: 14px; }
            .card-s1-header { padding: 0.65rem 1rem; }
            .card-s1-body { padding: 1rem; }
            .btn-s1 { height: 40px; font-size: 0.82rem; }
        }
    </style>
</head>
<body>
    <div class="portal-wrap">
        <div class="card-s1 mb-3">
            <div class="card-s1-header">
                <div class="brand-wrap">
                    <img src="<?= $baseURL . $app_config['app_info']['logo_path'] ?>" alt="" class="brand-logo">
                    <div>
                        <div class="brand-title">AD Request Portal</div>
                        <div class="brand-sub"><?= htmlspecialchars($app_config['app_info']['name']) ?></div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= $baseURL ?>" class="portal-header-link"><i class="fas fa-arrow-left"></i> Back to Login</a>
                    <div class="broadcast-trigger" id="broadcastTrigger">
                        <span class="broadcast-dot"></span>
                        <span>Live</span>
                        <i class="fas fa-chevron-down" style="font-size:0.55rem;opacity:0.5"></i>
                    </div>
                </div>
            </div>
            <div class="ticker-bar">
                <span class="badge">Security</span>
                <div id="ticker-text">Initialising...</div>
            </div>
        </div>

        <div class="broadcast-panel" id="broadcastPanel">
            <div class="panel-head">
                <span><i class="fas fa-rss me-1"></i>Activity</span>
                <span class="badge bg-danger rounded-pill px-2" id="broadcastCount" style="font-size:0.55rem">0</span>
            </div>
            <div class="panel-body" id="broadcastItems">
                <div class="text-center py-4 text-muted small"><i class="fas fa-circle-notch fa-spin fs-5 mb-2 opacity-20"></i><br>Connecting...</div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card-s1 h-100">
                    <div class="card-s1-header">
                        <div class="card-s1-header-title"><i class="fas fa-file-signature"></i> Submit Access Request</div>
                    </div>
                    <div class="card-s1-body">
                        <form id="adUserRequestForm">
                            <div class="row g-2 mb-2">
                                <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" class="form-control" name="requester_name" required></div>
                                <div class="col-md-6"><label class="form-label">Official Email</label><input type="email" class="form-control" name="requester_email" required></div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <label class="form-label">Request Type</label>
                                    <select class="form-select" id="request_type" name="request_type" required>
                                        <option value="">Choose...</option>
                                        <optgroup label="AD Operations">
                                        <?php foreach ($typeMap as $val => $lbl): ?><option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($lbl) ?></option><?php endforeach; ?>
                                        </optgroup>
                                        <optgroup label="Exchange Operations" id="exchangeOptgroup">
                                        <?php foreach ($typeMapExchange as $val => $lbl): ?><option value="<?= htmlspecialchars($val) ?>" data-needs-exchange="1"><?= htmlspecialchars($lbl) ?></option><?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                </div>
                                <div class="col-md-3" id="domain_wrap"><label class="form-label">Domain</label><select class="form-select" id="domain_select" name="domain_select"><?php foreach ($portalDomains as $d): $dk = $d['key'] ?? ''; if ($dk === '') continue; $dl = $d['label'] ?? ($dk . '.com'); $de = !empty($d['exchange']['enabled']); ?><option value="<?= htmlspecialchars($dk) ?>" data-exchange="<?= $de ? '1' : '0' ?>"><?= htmlspecialchars($dl) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-5" id="target_username_wrap"><label class="form-label">Account ID</label><div class="input-group"><span class="input-group-text" id="domainPrefix"><?= htmlspecialchars(($portalDomains[0]['key'] ?? 'wgbd') . '\\') ?></span><input type="text" class="form-control" id="target_username" name="target_username" placeholder="e.g. 66684"></div></div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-sm-6" id="hrms_id_wrap" style="display:none;"><label class="form-label">HRMS ID</label><input type="text" class="form-control" id="hrms_id" name="hrms_id"></div>
                                <div class="col-sm-6" id="requested_name_wrap" style="display:none;"><label class="form-label">Requested Name</label><input type="text" class="form-control" id="requested_name" name="requested_name"></div>
                                <div class="col-sm-6" id="custom_display_name_wrap" style="display:none;"><label class="form-label">Display Name</label><input type="text" class="form-control" id="custom_display_name" name="custom_display_name"></div>
                                <div class="col-sm-6" id="custom_username_wrap" style="display:none;"><label class="form-label">Username</label><input type="text" class="form-control" id="custom_username" name="custom_username"></div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-12" id="exchange_email_wrap" style="display:none;"><label class="form-label">Email Address</label><input type="email" class="form-control" id="exchange_email" name="exchange_email" placeholder="user@domain.com"></div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-12" id="exchange_extra_wrap" style="display:none;"><label class="form-label">Additional Info</label><input type="text" class="form-control" id="exchange_extra" name="exchange_extra" placeholder="e.g. Member identity, quota value, mail tip text"></div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-12" id="group_name_wrap" style="display:none;"><label class="form-label">Group Name</label><input type="text" class="form-control" id="group_name" name="group_name" placeholder="e.g. HR Distribution Group"></div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-sm-6" id="group_alias_wrap" style="display:none;"><label class="form-label">Group Alias</label><input type="text" class="form-control" id="group_alias" name="group_alias" placeholder="e.g. hr-dg"></div>
                                <div class="col-sm-6" id="group_description_wrap" style="display:none;"><label class="form-label">Group Description</label><input type="text" class="form-control" id="group_description" name="group_description" placeholder="Optional description"></div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-12"><label class="form-label" id="justificationLabel">Business Justification</label><textarea class="form-control" name="justification" rows="3" placeholder="Briefly explain..." id="justificationInput"></textarea></div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <button type="submit" class="btn-s1 btn-s1-primary" id="requestSubmitBtn"><i class="fas fa-paper-plane"></i>Submit</button>
                                <div id="requestStatus" class="fw-bold" style="font-size:0.8rem"></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card-s1 h-100">
                    <div class="card-s1-header">
                        <div class="card-s1-header-title"><i class="fas fa-history"></i> Track Application</div>
                    </div>
                    <div class="card-s1-body">
                        <form id="trackRequestForm">
                            <div class="mb-2">
                                <label class="form-label">Search by</label>
                                <select class="form-select mb-2" id="lookup_type">
                                    <option value="email">Email</option>
                                    <option value="contact">Contact</option>
                                </select>
                                <div class="search-field-group">
                                    <input type="text" class="form-control" id="lookup_value" placeholder="Enter value...">
                                    <button type="button" class="clear-search-trigger" id="btnClearSearch"><i class="fas fa-times-circle"></i></button>
                                </div>
                            </div>
                            <button type="submit" class="btn-s1 btn-s1-secondary w-100 justify-content-center" id="trackRequestBtn"><i class="fas fa-search"></i>Find Records</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-12">
                <div class="card-s1">
                    <div class="card-s1-header">
                        <div class="card-s1-header-title"><i class="fas fa-clock"></i> History</div>
                        <span id="historyCount" class="badge rounded-pill" style="font-size:0.55rem;background:#e2e8f0;color:#475569">0</span>
                    </div>
                    <div id="requestHistoryContainer" style="max-height:360px;overflow-y:auto">
                        <div class="text-center py-4 text-muted small">
                            <i class="fas fa-fingerprint d-block fs-4 mb-2 opacity-20"></i>
                            Search to load history
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include(__DIR__ . '/../../partials/footer.php'); ?>
    </div>

    <div class="toast-container">
        <div id="broadcastToast" class="toast broadcast-toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header border-0 pb-0">
                <i class="fas fa-broadcast-tower text-danger me-2"></i>
                <strong class="me-auto small fw-bold">SYSTEM</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body" id="toastMessage"></div>
        </div>
    </div>

    <script src="<?= $baseURL ?>/vendor/bootstrap/bootstrap.bundle.min.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script>
    (function() {
        const rForm = document.getElementById('adUserRequestForm'), tForm = document.getElementById('trackRequestForm');
        const rType = document.getElementById('request_type'), dSel = document.getElementById('domain_select');
        const bTrigger = document.getElementById('broadcastTrigger'), bPanel = document.getElementById('broadcastPanel');
        const lookupInput = document.getElementById('lookup_value'), btnClear = document.getElementById('btnClearSearch');
        const secMsgs = <?= json_encode($securityMessages) ?>;
        const apiUrl = '<?= route_url('ad_user_request_public.php') ?>';
        const domainExchangeMap = <?= json_encode($portalDomainExchange) ?>;
        let lastBroadcastTime = null;

        function filterExchangeOptions() {
            const t = rType.value;
            const isExchange = t.startsWith('exchange_');

            const hasExchangeDomains = Object.values(domainExchangeMap).some(v => v === true);
            const opt = document.getElementById('exchangeOptgroup');
            if (opt) opt.style.display = hasExchangeDomains ? '' : 'none';

            const dSel = document.getElementById('domain_select');
            const allOpts = Array.from(dSel.options).filter(o => o.value !== '');
            const currentVal = dSel.value;

            allOpts.forEach(o => {
                const exch = o.getAttribute('data-exchange') === '1';
                o.style.display = isExchange ? (exch ? '' : 'none') : '';
            });

            if (currentVal && allOpts.some(o => o.value === currentVal && o.style.display !== 'none')) {
                return;
            }
            const firstVisible = allOpts.find(o => o.style.display !== 'none');
            if (firstVisible) {
                dSel.value = firstVisible.value;
                const p = document.getElementById('domainPrefix');
                if (p) p.textContent = firstVisible.value + '\\';
            } else if (isExchange) {
                rType.value = '';
            }
            if (typeof sync === 'function') sync();
        }

        function sync() {
            const t = rType.value;
            const c = t === 'create_custom_user' || t === 'create_service_account';
            const isExchange = t.startsWith('exchange_');
            const tg = (i,s) => { const e=document.getElementById(i); if(e) e.style.display=s?'':'none'; };
            tg('hrms_id_wrap',t==='new_user'); tg('requested_name_wrap',t==='new_user');
            tg('custom_display_name_wrap',c); tg('custom_username_wrap',c);
            tg('domain_wrap',!c); tg('target_username_wrap',!c);

            tg('exchange_email_wrap', isExchange && ['exchange_add_email','exchange_remove_email','exchange_set_primary_smtp','exchange_set_forward'].includes(t));
            tg('exchange_extra_wrap', isExchange && ['exchange_set_quota','exchange_set_mail_tip','exchange_group_add_member','exchange_group_remove_member','exchange_set_litigation_hold'].includes(t));
            tg('group_name_wrap', t === 'exchange_group_create');
            tg('group_alias_wrap', t === 'exchange_group_create');
            tg('group_description_wrap', t === 'exchange_group_create');

            const p = document.getElementById('domainPrefix');
            if(p) p.textContent = dSel.value + '\\';
            const jl = document.getElementById('justificationLabel'), ji = document.getElementById('justificationInput');
            if(t==='create_service_account') {
                if(jl) jl.textContent='Server / Operation';
                if(ji) ji.placeholder='e.g. Print Server, SQL Backup';
            } else if (t === 'exchange_set_litigation_hold') {
                if(jl) jl.textContent='Reason for Litigation Hold';
                if(ji) ji.placeholder='Explain why litigation hold is needed...';
            } else {
                if(jl) jl.textContent='Business Justification';
                if(ji) ji.placeholder='Briefly explain...';
            }
        }
        rType.addEventListener('change', function() { filterExchangeOptions(); sync(); });
        dSel.addEventListener('change', sync);
        filterExchangeOptions();

        bTrigger.addEventListener('click', e => {
            e.stopPropagation();
            if (bPanel.classList.contains('show')) { bPanel.classList.remove('show'); return; }
            const rect = bTrigger.getBoundingClientRect();
            const isMobile = window.innerWidth < 992;
            bPanel.style.top = (rect.bottom + 6) + 'px';
            if (isMobile) {
                bPanel.style.left = '1rem';
                bPanel.style.width = 'calc(100% - 2rem)';
            } else {
                bPanel.style.width = '340px';
                let left = rect.right - 340;
                if (left < 8) left = 8;
                if (left + 340 > window.innerWidth - 8) left = window.innerWidth - 348;
                bPanel.style.left = left + 'px';
            }
            bPanel.classList.add('show');
            fetchBroadcasts();
        });
        document.addEventListener('click', e => { if(!bPanel.contains(e.target) && e.target!==bTrigger) bPanel.classList.remove('show'); });
        window.addEventListener('scroll', () => bPanel.classList.remove('show'), { passive: true });
        window.addEventListener('resize', () => bPanel.classList.remove('show'), { passive: true });

        lookupInput.addEventListener('input', () => btnClear.style.display = lookupInput.value ? 'block' : 'none');
        btnClear.addEventListener('click', () => {
            lookupInput.value=''; btnClear.style.display='none';
            document.getElementById('requestHistoryContainer').innerHTML = '<div class="text-center py-4 text-muted small"><i class="fas fa-fingerprint d-block fs-4 mb-2 opacity-20"></i>Search to load history</div>';
            document.getElementById('historyCount').textContent='0';
        });

        async function fetchBroadcasts() {
            try {
                const r = await (await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_broadcast'})})).json(),
                    body=document.getElementById('broadcastItems'),cnt=document.getElementById('broadcastCount');
                if(r.success && r.broadcasts.length) {
                    cnt.textContent=r.broadcasts.length;
                    body.innerHTML=r.broadcasts.map(i => `<div class="broadcast-item">${i.message}<span class="time">${i.time}</span></div>`).join('');
                    const l = r.broadcasts[0];
                    if(lastBroadcastTime!==null && l.time!==lastBroadcastTime) { showPopup(l); if(lookupInput.value.trim()!=='') refreshTracking(); }
                    lastBroadcastTime=l.time;
                } else { body.innerHTML='<div class="text-center py-4 text-muted small">No activity.</div>'; cnt.textContent='0'; }
            } catch(e) {}
        }

        async function refreshTracking() {
            const hb = document.getElementById('requestHistoryContainer'), hc = document.getElementById('historyCount');
            try {
                const r = await (await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'track_requests',lookup_type:document.getElementById('lookup_type').value,lookup_value:lookupInput.value})})).json();
                if(r.success && r.requests.length) {
                    hc.textContent=r.requests.length;
                    hb.innerHTML=r.requests.map(i => `<div class="history-item"><div class="hdr"><span class="ttl">${i.request_type_label}</span><span class="status-badge status-${i.status.toLowerCase()}">${i.status_label}</span></div><div><strong>Account:</strong> <code style="font-size:0.75rem;background:#f1f5f9;padding:0.1rem 0.35rem;border-radius:3px">${i.target}</code></div><div class="meta">${i.timestamp}${i.processed_at ? ' &bull; '+i.processed_at : ''}</div>${i.process_note ? '<div class="admin-note"><i class="fas fa-reply-all me-1"></i>'+i.process_note+'</div>' : ''}</div>`).join('');
                } else { hb.innerHTML='<div class="text-center py-4 text-muted small">No records found.</div>'; hc.textContent='0'; }
            } catch(e) {}
        }

        function showPopup(i) { document.getElementById('toastMessage').innerHTML=i.message; new bootstrap.Toast(document.getElementById('broadcastToast'),{delay:10000}).show(); }

        rForm.addEventListener('submit', async e => {
            e.preventDefault(); const b=document.getElementById('requestSubmitBtn'); b.disabled=true; b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Sending...';
            try {
                const r=await (await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign(Object.fromEntries(new FormData(rForm).entries()),{target_username:document.getElementById('target_username').value,selected_domain:dSel.value}))})).json(),
                    s=document.getElementById('requestStatus');
                s.className='fw-bold '+(r.success?'text-success':'text-danger'); s.textContent=r.message; if(r.success) rForm.reset();
            } catch(e) { alert('Failed.'); } finally { b.disabled=false; b.innerHTML='<i class="fas fa-paper-plane me-1"></i>Submit'; }
        });

        tForm.addEventListener('submit', e => { e.preventDefault(); refreshTracking(); });

        let mi=0;
        function ticker() {
            const el=document.getElementById('ticker-text');
            if(!el||!secMsgs.length) return;
            const msg=secMsgs[mi++%secMsgs.length]; el.textContent=''; let i=0;
            const iv=setInterval(() => {
                if(i<msg.length) { el.textContent+=msg.charAt(i++); const c=document.createElement('span'); c.className='typing-caret'; el.appendChild(c); const l=el.querySelector('.typing-caret:not(:last-child)'); if(l) l.remove(); }
                else { clearInterval(iv); setTimeout(ticker,4000); }
            },35);
        }
        sync(); ticker(); fetchBroadcasts(); setInterval(fetchBroadcasts,7000);
    })();
    </script>
</body>
</html>
