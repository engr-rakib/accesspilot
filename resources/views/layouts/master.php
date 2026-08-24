<?php
// Prevent direct access to this file
if (!defined('_CORE_ADMIN_')) {
    die('Access Denied');
}

// Read theme from cookie to prevent FOUC (Flash of Unstyled Content)
$themeClass = $_COOKIE['selectedTheme'] ?? 'theme-corporate-blue'; // Default theme
$isLicenseRestricted = !empty($licenseStatus['is_restricted']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo (strpos($pageTitle, $app_config['app_info']['name']) !== false) ? $pageTitle : $app_config['app_info']['name'] . ' - ' . $pageTitle; ?></title>
    <link rel="icon" href="<?= $baseURL . $app_config['app_info']['favicon_path'] ?>" type="image/x-icon">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $baseURL . $_SERVER['REQUEST_URI'] ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? $app_config['app_info']['og_title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription ?? $app_config['app_info']['og_description']) ?>">
    <meta property="og:image" content="<?= $baseURL . $app_config['app_info']['og_image'] ?>">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= $baseURL . $_SERVER['REQUEST_URI'] ?>">
    <meta property="twitter:title" content="<?= htmlspecialchars($pageTitle ?? $app_config['app_info']['og_title']) ?>">
    <meta property="twitter:description" content="<?= htmlspecialchars($pageDescription ?? $app_config['app_info']['og_description']) ?>">
    <meta property="twitter:image" content="<?= $baseURL . $app_config['app_info']['og_image'] ?>">
    <!-- Bootstrap CSS -->
    <link href="<?= $baseURL ?>/vendor/bootstrap/bootstrap.min.css?v=<?= $app_config['app_info']['version'] ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseURL ?>/vendor/roboto/roboto.css?v=<?= $app_config['app_info']['version'] ?>">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="<?= $baseURL ?>/vendor/fontawesome/all.min.css?v=<?= $app_config['app_info']['version'] ?>">
    <!-- Fonts: Roboto served locally, falls back to system -->
    <!-- Centralized Typography Styles -->
    <style>
        @font-face {
            font-family: 'Kalpurush';
            src: url('<?= $baseURL . $app_config['typography']['font_path'] ?>') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        :root {
            --primary-font: <?= $app_config['typography']['primary_font'] ?>;
            --secondary-font: <?= $app_config['typography']['secondary_font'] ?>;
            --technical-font: <?= $app_config['typography']['technical_font'] ?? "'JetBrains Mono', monospace" ?>;
<?php
$fs = $app_config['typography']['font_sizes'] ?? [];
foreach ($fs as $key => $val) {
    echo "            --font-{$key}: {$val};\n";
}
?>
        }

        body {
            font-family: var(--primary-font) !important;
        }
    </style>
    <!-- Custom CSS -->
    <?php
    if (!empty($page_styles)) {
        foreach ($page_styles as $style) {
            echo "<link rel=\"stylesheet\" href=\"{$style}\" >";
        }
    }
    ?>
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/theme.css?v=<?= $app_config['app_info']['version'] ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/base.css?v=<?= $app_config['app_info']['version'] ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/layout.css?v=<?= $app_config['app_info']['version'] ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/components.css?v=<?= $app_config['app_info']['version'] ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/animations.css?v=<?= $app_config['app_info']['version'] ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/dashboard.css?v=<?= $app_config['app_info']['version'] ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/pages.css?v=<?= $app_config['app_info']['version'] ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/sidecard.css?v=<?= $app_config['app_info']['version'] ?>">
    <style>
    <?php
    // Function to adjust the brightness of a hex color
    if (!function_exists('adjust_brightness')) {
        function adjust_brightness($hex, $percent) {
            $orig = $hex;
            $hex = ltrim($hex, '#');
            if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) return $orig;
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            
            $r = floor($r * (1 + $percent / 100));
            $g = floor($g * (1 + $percent / 100));
            $b = floor($b * (1 + $percent / 100));

            $r = max(0, min(255, $r));
            $g = max(0, min(255, $g));
            $b = max(0, min(255, $b));

            return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
        }
    }

    $themes_adj = [
        'theme-corporate-blue' => 0,
        'theme-red' => -10,
        'theme-natural-green' => 5,
        'theme-matte-black' => 15
    ];
    $actions_adj = [
        'btn-info-action',
        'btn-create-action',
        'btn-manual-create-action',
        'btn-enable-action',
        'btn-disable-action',
        'btn-unlock-action',
        'btn-reset-action',
        'btn-modify-action',
        'btn-dashboard-action',
        'btn-update-action',
        'btn-report-action'
    ];
    $button_colors = $app_config['button_colors'] ?? [];
    $btn_css_map = [
        'info'      => 'info',
        'create'    => 'create',
        'manual'    => 'manual',
        'directory' => 'directory',
        'enable'    => 'enable',
        'disable'   => 'disable',
        'unlock'    => 'unlock',
        'reset'     => 'reset',
        'modify'    => 'modify',
        'dashboard' => 'dashboard',
        'update'    => 'update',
        'report'    => 'report',
        'sync'      => 'sync',
        'mapping'   => 'mapping',
        'users'     => 'users',
        'health'    => 'health',
        'groups'    => 'groups',
        'reports'   => 'reports',
    ];
    $theme_configs = $app_config['themes'] ?? [];

    foreach ($theme_configs as $theme => $cfg) {
        $bgVal = $cfg['bg'] ?? '#f0f2f5';
        $isGradient = strpos($bgVal, 'gradient') !== false;
        echo ".{$theme} {\n";
        echo "    --background-color: " . $bgVal . ";\n";
        echo "    --whatsapp-bg: " . ($isGradient ? 'transparent' : $bgVal) . ";\n";
        echo "    --ws-card-bg: " . ($cfg['card'] ?? '#ffffff') . ";\n";
        echo "    --rail-bg: " . ($cfg['rail'] ?? '#e9edef') . ";\n";
        echo "    --border-color: " . ($cfg['border'] ?? '#d1d7db') . ";\n";
        echo "    --text-color: " . ($cfg['text'] ?? '#333d51') . ";\n";
        echo "    --header-bg: " . ($cfg['header'] ?? '#1976D2') . ";\n";
        echo "    --header-text: " . ($cfg['header_text'] ?? '#ffffff') . ";\n";
        echo "    --context-pane-bg: " . ($cfg['rail'] ?? '#e9edef') . ";\n";
        echo "    --primary-color: " . ($cfg['primary'] ?? '#183593') . ";\n";
        echo "    --primary-rgb: " . ($cfg['primary_rgb'] ?? '24, 53, 147') . ";\n";
        foreach ($btn_css_map as $key => $var) {
            $val = $button_colors[$key] ?? '';
            if ($val) {
                echo "    --sidecard-action-bg-{$var}: {$val};\n";
            }
        }
        if ($isGradient) {
            echo "    background: " . $bgVal . " !important;\n";
            echo "    background-attachment: fixed !important;\n";
        }
        echo "}\n";
        if ($isGradient) {
            echo "body." . $theme . " .shell-workspace,\n";
            echo "body." . $theme . " .workspace-content-scroll,\n";
            echo "body." . $theme . " .context-body,\n";
            echo "body." . $theme . " .context-header {\n";
            echo "    background: transparent !important;\n";
            echo "}\n";
            echo "body." . $theme . " .workspace-header .header-right-group .shell-tool-btn {\n";
            echo "    color: rgba(255,255,255,0.8) !important;\n";
            echo "}\n";
            echo "body." . $theme . " .workspace-header .shell-header-tools .shell-tool-btn:hover {\n";
            echo "    background: rgba(255,255,255,0.12) !important;\n";
            echo "    color: #fff !important;\n";
            echo "}\n";
            echo "html body.shell-whatsapp." . $theme . " {\n";
            echo "    background: " . $bgVal . " !important;\n";
            echo "    background-attachment: fixed !important;\n";
            echo "}\n";
            echo "html body.shell-whatsapp." . $theme . " .app-shell-container {\n";
            echo "    background: transparent !important;\n";
            echo "}\n";
            echo "html body.shell-whatsapp." . $theme . " .shell-workspace,\n";
            echo "html body.shell-whatsapp." . $theme . " .workspace-content-scroll {\n";
            echo "    background: transparent !important;\n";
            echo "}\n";
            echo "body." . $theme . " .rail-item i {\n";
            echo "    color: inherit !important;\n";
            echo "}\n";
            echo "body." . $theme . " .rail-item.active i,\n";
            echo "body." . $theme . " .rail-item:hover i {\n";
            echo "    color: inherit !important;\n";
            echo "}\n";
        }
        if ($isGradient) {
            echo "." . $theme . " .card,\n";
            echo "." . $theme . " .profile-card,\n";
            echo "." . $theme . " .app-table-card {\n";
            echo "    background: " . ($cfg['card'] ?? 'rgba(255,255,255,0.08)') . " !important;\n";
            echo "    backdrop-filter: blur(16px) !important;\n";
            echo "    -webkit-backdrop-filter: blur(16px) !important;\n";
            echo "    border: 1px solid " . ($cfg['border'] ?? 'rgba(255,255,255,0.15)') . " !important;\n";
            echo "    box-shadow: 0 8px 32px rgba(0,0,0,0.2), inset 0 1px 0 " . ($cfg['border'] ?? 'rgba(255,255,255,0.15)') . " !important;\n";
            echo "}\n";
            echo "." . $theme . " .card-header,\n";
            echo "." . $theme . " .card-body,\n";
            echo "." . $theme . " .log-title-wrapper {\n";
            echo "    background: transparent !important;\n";
            echo "}\n";
            echo "." . $theme . " table.table,\n";
            echo "." . $theme . " table.table tbody,\n";
            echo "." . $theme . " table.table tbody tr,\n";
            echo "." . $theme . " table.table tbody td {\n";
            echo "    background: transparent !important;\n";
            echo "}\n";
            echo "." . $theme . " table.table thead th {\n";
            echo "    color: #ffffff !important;\n";
            echo "    border-bottom: none !important;\n";
            echo "}\n";
            echo "." . $theme . " table.table tbody tr:hover td {\n";
            echo "    background: rgba(255,255,255,0.04) !important;\n";
            echo "}\n";
            echo "." . $theme . " .log-table-wrapper {\n";
            echo "    background: transparent !important;\n";
            echo "}\n";
            echo "." . $theme . " .btn {\n";
            echo "    border-color: " . ($cfg['border'] ?? 'rgba(255,255,255,0.2)') . " !important;\n";
            echo "    background: rgba(255,255,255,0.1) !important;\n";
            echo "    color: #fff !important;\n";
            echo "}\n";
            echo "." . $theme . " .btn-primary,\n";
            echo "." . $theme . " .btn-profile,\n";
            echo "." . $theme . " .btn-noc-premium {\n";
            echo "    background: linear-gradient(135deg, " . ($cfg['primary'] ?? '#a78bfa') . ", " . ($cfg['header'] ?? '#6366f1') . ") !important;\n";
            echo "    border: none !important;\n";
            echo "    color: #fff !important;\n";
            echo "}\n";
            echo "." . $theme . " .btn:hover {\n";
            echo "    background: rgba(255,255,255,0.18) !important;\n";
            echo "}\n";
            echo "." . $theme . " .form-control,\n";
            echo "." . $theme . " .form-select,\n";
            echo "." . $theme . " input[type=text],\n";
            echo "." . $theme . " input[type=email],\n";
            echo "." . $theme . " input[type=password],\n";
            echo "." . $theme . " select,\n";
            echo "." . $theme . " textarea {\n";
            echo "    background: rgba(255,255,255,0.08) !important;\n";
            echo "    border: 1px solid " . ($cfg['border'] ?? 'rgba(255,255,255,0.15)') . " !important;\n";
            echo "    color: #fff !important;\n";
            echo "}\n";
            echo "." . $theme . " .form-control:focus,\n";
            echo "." . $theme . " .form-select:focus {\n";
            echo "    border-color: " . ($cfg['primary'] ?? '#c4b5fd') . " !important;\n";
            echo "    box-shadow: 0 0 0 2px rgba(196,181,253,0.25) !important;\n";
            echo "}\n";
            echo "." . $theme . " .form-label,\n";
            echo "." . $theme . " label {\n";
            echo "    color: rgba(255,255,255,0.75) !important;\n";
            echo "}\n";
            echo "." . $theme . " .card-title,\n";
            echo "." . $theme . " .log-title-wrapper span,\n";
            echo "." . $theme . " .app-table-title span {\n";
            echo "    color: rgba(255,255,255,0.9) !important;\n";
            echo "}\n";
            echo "." . $theme . " .text-muted,\n";
            echo "." . $theme . " .text-secondary {\n";
            echo "    color: rgba(255,255,255,0.5) !important;\n";
            echo "}\n";
            echo "." . $theme . " .list-group-item {\n";
            echo "    background: transparent !important;\n";
            echo "    color: rgba(255,255,255,0.8) !important;\n";
            echo "    border-color: " . ($cfg['border'] ?? 'rgba(255,255,255,0.08)') . " !important;\n";
            echo "}\n";
            echo "." . $theme . " .nav-link {\n";
            echo "    color: rgba(255,255,255,0.7) !important;\n";
            echo "}\n";
            echo "." . $theme . " .workspace-header {\n";
            $h = ($cfg['header'] ?? 'rgba(255,255,255,0.12)');
            echo "    background: linear-gradient(135deg, {$h}, color-mix(in srgb, {$h}, #000 18%)) !important;\n";
            echo "    backdrop-filter: blur(12px) !important;\n";
            echo "    -webkit-backdrop-filter: blur(12px) !important;\n";
            echo "    border-bottom: 1px solid " . ($cfg['border'] ?? 'rgba(255,255,255,0.15)') . " !important;\n";
            echo "}\n";
            echo "." . $theme . " .shell-vertical-rail {\n";
            echo "    background: " . ($cfg['rail'] ?? 'rgba(255,255,255,0.05)') . " !important;\n";
            echo "    backdrop-filter: blur(8px) !important;\n";
            echo "}\n";
            echo "." . $theme . " .child-rail-flyout {\n";
            echo "    background: " . ($cfg['rail'] ?? 'rgba(255,255,255,0.05)') . " !important;\n";
            echo "    backdrop-filter: blur(8px) !important;\n";
            echo "}\n";
        }
    }

    foreach ($themes_adj as $theme => $adjustment) {
        foreach ($actions_adj as $action_class) {
            $action_name = str_replace(['-action', 'btn-'], '', $action_class);
            if (isset($button_colors[$action_name])) {
                $base_color = $button_colors[$action_name];
                $theme_color = adjust_brightness($base_color, $adjustment);
                $border_color = adjust_brightness($theme_color, -10);
                echo ".{$theme} .action-button.{$action_class} { background: {$theme_color} !important; border-color: {$border_color} !important; }\n";
            }
        }
    }
    ?>
    #portal-page-title { font-family: var(--secondary-font), serif !important; font-weight: 700; letter-spacing: 1px; }
    .notification-center-card { font-size: 0.72rem; border-radius: 8px !important; }
    .notification-center-header {
        padding: 6px 8px !important;
        align-items: center !important;
    }
    .notification-center-header h3 {
        font-size: 0.82rem !important;
        font-weight: 700 !important;
    }
    .notification-center-header small {
        font-size: 0.6rem !important;
    }
    .notification-close-btn {
        width: 22px; height: 22px; padding: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border: none; background: rgba(0,0,0,0.08); border-radius: 5px;
        color: var(--text-muted, rgba(0,0,0,0.5)); cursor: pointer;
        font-size: 0.6rem; transition: background 0.15s;
    }
    .notification-close-btn:hover { background: rgba(0,0,0,0.15); }
    .notification-toast-close-btn {
        width: 22px; height: 22px; padding: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border: none; background: rgba(0,0,0,0.08); border-radius: 5px;
        color: var(--text-muted, rgba(0,0,0,0.5)); cursor: pointer;
        font-size: 0.6rem; transition: background 0.15s; flex-shrink: 0;
    }
    .notification-toast-close-btn:hover { background: rgba(0,0,0,0.15); }
    .notification-center-card .form-label,
    .notification-center-card .form-check-label,
    .notification-center-card .form-control,
    .notification-center-card .form-select,
    .notification-center-card .btn,
    .notification-center-card select option,
    .notification-center-card .notification-compose-title,
    .notification-center-card .notification-compose-copy,
    .notification-center-card .notification-check-item span { font-size: 0.6rem !important; }
    #notificationComposerPanel .form-label,
    #notificationComposerPanel .form-control,
    #notificationComposerPanel .form-select,
    #notificationComposerPanel select option,
    #notificationComposerPanel .btn { font-size: 0.6rem !important; }
    .notification-compose-grid { display: flex; flex-wrap: wrap; }
    .notification-compose-grid > .col-6 { flex: 0 0 auto; width: 50%; box-sizing: border-box; }
    .notification-row-3 { display: flex; gap: 6px; width: 100%; }
    .notification-row-3 > .notification-col { flex: 1; min-width: 0; }
    .notification-row-3 .form-control,
    .notification-row-3 .form-select { width: 100%; }
    .notification-row-3 #notificationPersistentField { display: flex; flex-direction: column; justify-content: flex-end; }
    .notification-header-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        border-radius: 999px;
        background: #ff3b30;
        color: #fff;
        font-size: 0.6rem;
        font-weight: 800;
        line-height: 1;
    }
    </style>
    <style>
        .domain-bar { display: flex; align-items: center; gap: 8px; padding: 6px 0 10px; cursor: pointer; user-select: none; }
        .domain-bar:hover { opacity: 0.85; }
        .domain-bar-dot { width: 10px; height: 10px; border-radius: 50%; background: #22c55e; flex-shrink: 0; }
        .domain-bar-key { font-size: 0.78rem; font-weight: 800; letter-spacing: 0.5px; color: var(--text-primary, #0f172a); flex: 1; line-height: 1.2; }
        .domain-bar-active { font-size: 0.6rem; font-weight: 700; color: #22c55e; letter-spacing: 0.5px; }

        .domain-switcher-dropdown { position: absolute; left: 0; top: calc(100% + 4px); min-width: 220px; background: #fff; border: 1px solid #d1d9e6; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); z-index: 1060; padding: 6px 0; }
        .domain-switcher-header { display: flex; justify-content: space-between; align-items: center; padding: 8px 14px 6px; font-size: 0.65rem; color: #64748b; border-bottom: 1px solid #e2e8f0; }
        .domain-switcher-title { font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .domain-switcher-count { font-size: 0.6rem; }
        .domain-switcher-list { max-height: 200px; overflow-y: auto; padding: 4px 0; }
        .domain-switcher-item { display: flex; align-items: center; gap: 10px; padding: 9px 14px; cursor: pointer; transition: background .1s ease; font-size: 0.78rem; }
        .domain-switcher-item:hover { background: #f1f5f9; }
        .domain-switcher-item.active { background: #eff6ff; }
        .domain-switcher-item-dot { width: 8px; height: 8px; border-radius: 50%; background: #cbd5e1; flex-shrink: 0; }
        .domain-switcher-item.active .domain-switcher-item-dot { background: #22c55e; }
        .domain-switcher-item-key { font-weight: 700; color: #0f172a; letter-spacing: 0.5px; flex: 1; }
        .domain-switcher-footer { padding: 6px 14px; font-size: 0.55rem; color: #94a3b8; border-top: 1px solid #e2e8f0; text-align: center; }
        .domain-switcher-backdrop { position: fixed; inset: 0; z-index: 1059; }
    </style>
</head>
<body class="<?php echo $themeClass; ?> shell-whatsapp <?php echo isset($is_index_pro_page) && $is_index_pro_page ? 'index-pro-page' : ''; ?>">
    <!-- EFFECT: page loader | Purpose: loading spinner shown during initial page load -->
    <div class="loader-container">
        <div class="loader"></div>
    </div>

    <div class="app-shell-container">
        <!-- Pane 1: Navigation Rail -->
        <?php include include_path('resources/views/partials/vertical_rail.php'); ?>

        <!-- Pane 2: Assistant Context Panel -->
        <aside class="shell-context-pane">
            <div class="context-header">
                <div class="sidecard-inner-rail">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <img src="<?= $baseURL . $app_config['app_info']['logo_path'] ?>" alt="Logo" style="width: 24px; height: 24px;">
                                <h4 class="mb-0 fw-bold">Assistant</h4>
                            </div>
                            <i class="fas fa-tasks text-muted"></i>
                        </div>
                        <?php if (function_exists('ldap_get_domains')): ?>
                        <?php
                            $_domains = ldap_get_domains();
                            $_activeKey = ldap_active_domain_key();
                            $_activeDomain = ldap_get_domain($_activeKey);
                            $_raw = $_activeDomain['label'] ?? $_activeDomain['base_dn'] ?? $_activeKey;
                            if (stripos($_raw, 'DC=') !== false) {
                                $_parts = preg_split('/,\s*/', $_raw);
                                $_dcs = [];
                                foreach ($_parts as $_p) {
                                    if (stripos($_p, 'DC=') === 0) $_dcs[] = trim(substr($_p, 3));
                                }
                                $_display = implode('.', $_dcs);
                            } else {
                                $_display = $_raw;
                            }
                            if ($_display === '' || $_display === 'Default AD') $_display = $_activeKey;
                        ?>
                        <div class="domain-bar-wrapper" style="position:relative;">
                            <div class="domain-bar" id="domainSwitcherTrigger" data-active-key="<?= htmlspecialchars($_activeKey) ?>" role="button" tabindex="0">
                                <span class="domain-bar-dot"></span>
                                <span class="domain-bar-key"><?= htmlspecialchars(strtoupper($_display)) ?></span>
                                <span class="domain-bar-active">ACTIVE</span>
                            </div>
                            <div class="domain-switcher-dropdown" id="domainSwitcherDropdown" style="display:none;">
                                <div class="domain-switcher-header">
                                    <span class="domain-switcher-title">Switch Domain</span>
                                    <span class="domain-switcher-count"><?= count($_domains) ?> configured</span>
                                </div>
                                <div class="domain-switcher-list" id="domainSwitcherList">
                                    <?php foreach ($_domains as $_d):
                                        $_dk = $_d['key'] ?? '';
                                        $_dl_raw = $_d['label'] ?? $_d['base_dn'] ?? $_dk;
                                        if (stripos($_dl_raw, 'DC=') !== false) {
                                            $_dl_parts = preg_split('/,\s*/', $_dl_raw);
                                            $_dl_dcs = [];
                                            foreach ($_dl_parts as $_dl_p) {
                                                if (stripos($_dl_p, 'DC=') === 0) $_dl_dcs[] = trim(substr($_dl_p, 3));
                                            }
                                            $_dl_display = implode('.', $_dl_dcs);
                                        } else {
                                            $_dl_display = $_dl_raw;
                                        }
                                        if ($_dl_display === '' || $_dl_display === 'Default AD') $_dl_display = $_dk;
                                    ?>
                                    <div class="domain-switcher-item <?= $_dk === $_activeKey ? 'active' : '' ?>" data-key="<?= htmlspecialchars($_dk) ?>">
                                        <span class="domain-switcher-item-dot"></span>
                                        <span class="domain-switcher-item-key"><?= htmlspecialchars(strtoupper($_dl_display)) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="domain-switcher-footer">
                                    <span id="domainLimitStatus"><?= htmlspecialchars(function_exists('ldap_domain_limit_message') ? ldap_domain_limit_message() : '') ?></span>
                                </div>
                            </div>
                            <div class="domain-switcher-backdrop" id="domainSwitcherBackdrop" style="display:none;"></div>
                        </div>
                        <?php endif; ?>
                    <div class="assistant-search-wrapper mb-3">
                        <div class="assistant-search-field">
                            <i class="fas fa-search assistant-search-icon"></i>
                            <input type="text" id="username" name="username" class="assistant-search-input" placeholder="Provide user id or group name" list="assistant-history" autocomplete="off">
                            <i class="fas fa-times assistant-clear-icon" id="clear-username" style="display: none;"></i>
                        </div>
                        <datalist id="assistant-history"></datalist>
                    </div>
                    <div class="d-flex gap-2 overflow-auto pb-1 shell-filter-pills">
                        <span class="badge rounded-pill bg-success px-3 filter-pill active" data-filter="all" style="cursor: pointer;">All</span>
                        <span class="badge rounded-pill bg-light text-dark border px-3 filter-pill" data-filter="identity" style="cursor: pointer;">Identity</span>
                        <span class="badge rounded-pill bg-light text-dark border px-3 filter-pill" data-filter="reports" style="cursor: pointer;">Reports</span>
                    </div>
                </div>
                <script>
                (function() {
                    const input = document.getElementById('username');
                    const clearBtn = document.getElementById('clear-username');
                    const datalist = document.getElementById('assistant-history');
                    const STORAGE_KEY = 'assistant_input_history';
                    const LAST_VAL_KEY = 'assistant_last_input';

                    // 1. Restore last value
                    const lastValue = localStorage.getItem(LAST_VAL_KEY);
                    if (lastValue) {
                        input.value = lastValue;
                        clearBtn.style.display = 'block';
                    }

                    // 2. Load history for datalist
                    function updateDatalist() {
                        const history = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
                        datalist.innerHTML = history.map(item => `<option value="${item}">`).join('');
                    }
                    updateDatalist();

                    input.addEventListener('input', function() {
                        clearBtn.style.display = this.value ? 'block' : 'none';
                        localStorage.setItem(LAST_VAL_KEY, this.value);
                    });

                    function saveToHistory() {
                        const val = input.value.trim();
                        if (!val) return;

                        let history = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
                        history = history.filter(item => item !== val);
                        history.unshift(val);
                        history = history.slice(0, 10);

                        localStorage.setItem(STORAGE_KEY, JSON.stringify(history));
                        updateDatalist();
                    }

                    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') saveToHistory(); });
                    input.addEventListener('blur', saveToHistory);

                    clearBtn.addEventListener('click', function() {
                        input.value = '';
                        localStorage.removeItem(LAST_VAL_KEY);
                        input.dispatchEvent(new Event('input'));
                        input.focus();
                    });
                })();
                </script>
            </div>
            <div class="context-body flex-grow-1 overflow-y-auto">
                <div class="sidecard-inner-rail">
                    <?php 
                    // Directly include the cleaned-up actions
                    include include_path('resources/views/components/sidebar_actions.php');
                    ?>
                </div>
            </div>
        </aside>

        <!-- Pane 3: Main Workspace -->
        <main class="shell-workspace">
            <!-- Workspace Header -->
            <div class="workspace-header">
                <h5 class="workspace-header-title" id="portal-page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h5>

                <div class="header-right-group">
                    <div class="shell-header-tools">
                        <button class="shell-tool-btn" type="button" id="notificationBellButton" data-noc-tip="Notifications" style="position: relative;">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge" id="notificationBadge" style="display:none;">0</span>
                        </button>
                        <button class="shell-tool-btn" type="button" id="fullscreenBtn" data-noc-tip="Toggle Fullscreen">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>

                    <div class="header-divider"></div>
                    
                    <div class="shell-user-block">
                        <div class="user-top-row">
                            <i class="fas fa-user user-icon"></i>
                            <span class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                        </div>
                        <div class="user-clock"><i class="fas fa-clock"></i><span class="js-clock"><?= date('h:i A') ?></span></div>
                    </div>
                </div>
            </div>
            
            <!-- Workspace Content -->
            <!-- Layout Contract: Vertical gaps are managed globally via CSS variables (--card-gutter-desktop/mobile) 
                 applied to .row elements within .workspace-content-scroll. Individual components should not define margins. -->
            <div class="workspace-content-scroll" id="main-content">
                <div id="spa-main-content">
                    <?php 
                    include include_path('resources/views/components/global/action_taken_card.php');
                    include include_path('resources/views/components/global/manual_create_form.php');
                    include include_path('resources/views/components/global/directory_builder_form.php');
                    include include_path('resources/views/components/global/export_user_oureport_card.php');
                    include include_path('resources/views/components/global/user_report_card.php');
                    include include_path('resources/views/components/global/security_events_card.php');
                    include include_path('resources/views/components/global/info_cards.php');
                    ?>
                    <div id="spa-page-content">
                    <?php 
                    if (isset($content_for_layout) && file_exists($content_for_layout)) {
                        include $content_for_layout;
                    }
                    ?>
                    </div>
                </div>
                <?php include(__DIR__ . '/../partials/footer.php'); ?>
            </div>
        </main>
    </div>

    <?php include __DIR__ . '/../partials/offcanvas_menu.php'; ?>
    <?php include include_path('resources/views/components/notification/center.php'); ?>

    <!-- Password Manager Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="passwordModalLabel">Create/Edit Password Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="passwordForm">
                    <input type="hidden" id="passwordEntryId">
                    <div class="mb-3">
                        <label for="passwordOwner" class="form-label">Owner</label>
                        <input type="text" class="form-control" id="passwordOwner" placeholder="e.g., IT Department, jdoe">
                    </div>
                    <div class="mb-3">
                        <label for="passwordSystemName" class="form-label">System Name / Hostname</label>
                        <input type="text" class="form-control" id="passwordSystemName" required>
                    </div>
                    <div class="mb-3">
                        <label for="passwordUrl" class="form-label">Access Point / URL</label>
                        <input type="text" class="form-control" id="passwordUrl" placeholder="e.g., https://vcenter.local, 192.168.1.10">
                    </div>
                    <div class="mb-3">
                        <label for="passwordUserId" class="form-label">User ID / Username</label>
                        <input type="text" class="form-control" id="passwordUserId" required>
                    </div>
                    <div class="mb-3">
                        <label for="passwordValue" class="form-label">Password</label>
                        <div style="display:flex;align-items:center;gap:4px;">
                            <input type="password" class="form-control" id="passwordValue" required style="flex:1;">
                            <button class="btn btn-icon btn-sm toggle-vis-btn" type="button" title="Toggle Visibility" style="flex:0 0 auto;"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-icon btn-sm copy-btn" type="button" title="Copy Password" style="flex:0 0 auto;"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="passwordIp" class="form-label">IP Address</label>
                        <input type="text" class="form-control" id="passwordIp" placeholder="e.g., 192.168.1.50">
                    </div>
                    <div class="mb-3">
                        <label for="passwordParent" class="form-label">Parent Entry</label>
                        <select class="form-select" id="passwordParent">
                            <option value="">No Parent</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="passwordEntryType" class="form-label">Entry Type</label>
                        <select class="form-select" id="passwordEntryType">
                            <option value="credential">Credential</option>
                            <option value="host">Host</option>
                            <option value="vm">VM</option>
                            <option value="os_user">OS User</option>
                            <option value="application">Application</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="passwordRemarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="passwordRemarks" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-noc-tip="Discard and close.">Close</button>
                <button type="button" class="btn btn-primary" id="savePasswordEntry" data-noc-tip="Save this credential.">Save Entry</button>
            </div>
        </div>
    </div>
</div>

    <!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="resetPasswordForm" method="POST"> <!-- Added form tag -->
                    <input type="hidden" name="username" id="reset-username">
                    <input type="text" name="username" id="reset-username-visible" class="visually-hidden" autocomplete="username">
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="default_password_check" name="use_default_password">
                        <label class="form-check-label" for="default_password_check">Use Default Password (<?= htmlspecialchars($app_config['default_password']) ?>)</label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="force_password_change" name="force_password_change">
                        <label class="form-check-label" for="force_password_change">Force password change on next login</label>
                    </div>
                    <div class="mb-3" id="new_password_group">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required autocomplete="new-password">
                    </div>
                </form> <!-- Closed form tag -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-noc-tip="Discard and close.">Close</button>
                <button type="button" class="btn btn-primary" id="confirm-reset-password-btn">Reset Password</button>
            </div>
        </div>
    </div>
</div>

<!-- Approve User Modal -->
<div class="modal fade" id="approveUserModal" tabindex="-1" aria-labelledby="approveUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveUserModalLabel">Approve User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="approveUserForm" method="POST">
                    <input type="hidden" name="request_index" id="approve-request-index">
                    <input type="text" name="username" id="approve-username-visible" class="visually-hidden" autocomplete="username">
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="approve-default-password-check" name="use_default_password" checked>
                        <label class="form-check-label" for="approve-default-password-check">Use Default Password (<?= htmlspecialchars($app_config['default_password']) ?>)</label>
                    </div>
                    <div class="mb-3" id="approve-new_password_group" style="display: none;">
                        <label for="approve-new-password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="approve-new-password" name="new_password" autocomplete="new-password">
                    </div>
                    <!-- Warning section for missing HRMS ID -->
                    <div id="hrms-warning-section" style="display: none;" class="mt-3">
                        <div class="alert alert-warning py-2 small">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            User ID not found on HRMS. Check the box below to create as a Custom User.
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="acknowledge_custom_user">
                            <label class="form-check-label text-danger fw-bold" for="acknowledge_custom_user">I acknowledge and want to create this Custom User.</label>
                        </div>
                    </div>
                    </form>
                    </div>
                    <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-noc-tip="Discard and close.">Close</button>
                    <button type="button" class="btn btn-primary" id="confirm-approve-user-btn">Approve User</button>
                    </div>        </div>
    </div>
</div>

    <!-- Bootstrap JS and dependencies (served locally, no external CDN round-trip) -->
    <script src="<?= $baseURL ?>/vendor/bootstrap/bootstrap.bundle.min.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script src="<?= $baseURL ?>/vendor/chartjs/chart.umd.min.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <!-- Custom JavaScript -->
    <script>
        const baseURL = "<?= $baseURL ?>";
        window.APP_CONFIG = {
            baseUrl: "<?= $baseURL ?>",
            basePath: "<?= $base_path ?? base_path() ?>",
            apiBaseUrl: "<?= api_url() ?>",
            assetBaseUrl: "<?= asset_url() ?>",
            hrmsImgBaseUrl: "<?= ($app_config['api_paths'] ?? [])['hrms_img_url'] ?? '' ?>",
            appLogoUrl: "<?= $baseURL . (($app_config['ui_paths'] ?? [])['user_photo_fallback_path'] ?? $app_config['app_info']['logo_path']) ?>",
            adminPageUrl: "<?= admin_page_url() ?>",
            dashboardPageUrl: "<?= admin_page_url('dashboard') ?>",
            userManagementPageUrl: "<?= admin_page_url('user_management') ?>",
            userActivityPageUrl: "<?= admin_page_url('user_activity') ?>",
            serverTimestamp: <?= time() ?>,
            csrfToken: "<?php if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>"
        };
        window._csrfToken = window.APP_CONFIG.csrfToken;
        (function() {
            if (typeof window._csrfToken !== 'string' || !window._csrfToken) return;
            const origFetch = window.fetch;
            window.fetch = function(input, init) {
                if (!init) init = {};
                const url = (typeof input === 'string' ? input : (input && input.url) || '');
                if (url.indexOf('/api/') !== -1) {
                    init.headers = init.headers || {};
                    if (init.headers instanceof Headers) {
                        if (!init.headers.has('X-CSRF-Token')) {
                            init.headers.set('X-CSRF-Token', window._csrfToken);
                        }
                    } else {
                        init.headers['X-CSRF-Token'] = window._csrfToken;
                    }
                }
                const responsePromise = origFetch.call(this, input, init);
                if (url.indexOf('/api/') !== -1) {
                    return responsePromise.then(function(response) {
                        if (response.status === 419) {
                            window.location.href = (window.APP_CONFIG && window.APP_CONFIG.baseUrl || '') + '/login.php?message=session_expired';
                            return response;
                        }
                        return response;
                    });
                }
                return responsePromise;
            };
        })();
        (function() {
            var idleTimer = null;
            var rememberMe = <?php echo !empty($_SESSION['remember_me']) ? 'true' : 'false'; ?>;
            // Server thresholds: plain = 900s (15 min), remember-me = 7200s (2 hrs).
            // Fire client redirect slightly early to avoid racing the server kill.
            var IDLE_TIMEOUT = rememberMe ? 7140000 : 840000; // 119 min or 14 min

            function redirectToLogin() {
                window.location.href = (window.APP_CONFIG && window.APP_CONFIG.baseUrl || '') + '/login.php?message=session_expired';
            }

            function resetIdleTimer() {
                if (idleTimer) clearTimeout(idleTimer);
                idleTimer = setTimeout(redirectToLogin, IDLE_TIMEOUT);
            }

            resetIdleTimer();
            document.addEventListener('mousemove', resetIdleTimer, { passive: true });
            document.addEventListener('keydown', resetIdleTimer, { passive: true });
            document.addEventListener('click', resetIdleTimer, { passive: true });
            document.addEventListener('touchstart', resetIdleTimer, { passive: true });
            document.addEventListener('scroll', resetIdleTimer, { passive: true });
        })();
        const hrmsImgBaseUrl = "<?= ($app_config['api_paths'] ?? [])['hrms_img_url'] ?? '' ?>";
        const appLogoUrl = "<?= $baseURL . (($app_config['ui_paths'] ?? [])['user_photo_fallback_path'] ?? $app_config['app_info']['logo_path']) ?>";
        const isIndexProPage = <?php echo isset($is_index_pro_page) && $is_index_pro_page ? 'true' : 'false'; ?>;
    </script>
    <script>
    (function() {
        var trigger = document.getElementById('domainSwitcherTrigger');
        var dropdown = document.getElementById('domainSwitcherDropdown');
        var backdrop = document.getElementById('domainSwitcherBackdrop');
        if (!trigger || !dropdown || !backdrop) return;

        function openDropdown() {
            dropdown.style.display = 'block';
            backdrop.style.display = 'block';
        }
        function closeDropdown() {
            dropdown.style.display = 'none';
            backdrop.style.display = 'none';
        }

        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            if (dropdown.style.display === 'block') { closeDropdown(); }
            else { openDropdown(); }
        });
        backdrop.addEventListener('click', closeDropdown);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeDropdown();
        });

        var items = dropdown.querySelectorAll('.domain-switcher-item');
        var apiBase = window.APP_CONFIG ? window.APP_CONFIG.apiBaseUrl : (baseURL + 'api/index.php');

        items.forEach(function(item) {
            item.addEventListener('click', function() {
                var self = this;
                var key = self.getAttribute('data-key');
                var activeKey = trigger.getAttribute('data-active-key');
                if (key === activeKey || self.classList.contains('active')) {
                    closeDropdown();
                    return;
                }

                var originalHtml = self.innerHTML;
                self.innerHTML = '<span style="font-size:0.7rem;color:#64748b;"><i class="fas fa-spinner fa-spin"></i> Switching...</span>';

                fetch(apiBase + '?endpoint=domain_api&action=switch_domain', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ key: key })
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        window.location.reload();
                    } else {
                        self.innerHTML = originalHtml;
                        alert(res.message || 'Switch failed.');
                        closeDropdown();
                    }
                })
                .catch(function() {
                    self.innerHTML = originalHtml;
                    alert('Network error.');
                    closeDropdown();
                });
            });
        });
    })();
    </script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/utils.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/spa_loader.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script>
    window.NocTooltipConfig = {
        bg: '<?= $app_config['tooltips']['bg'] ?>',
        color: '<?= $app_config['tooltips']['color'] ?>',
        fontSize: '<?= $app_config['tooltips']['font_size'] ?>',
        padding: '<?= $app_config['tooltips']['padding'] ?>',
        borderRadius: '<?= $app_config['tooltips']['border_radius'] ?>',
        border: '<?= $app_config['tooltips']['border'] ?>',
        shadow: '<?= $app_config['tooltips']['shadow'] ?>',
        maxWidth: '<?= $app_config['tooltips']['max_width'] ?>',
        gap: <?= $app_config['tooltips']['gap'] ?>,
        arrowSize: <?= $app_config['tooltips']['arrow_size'] ?>,
        arrowColor: '<?= $app_config['tooltips']['arrow_color'] ?>',
        zIndex: <?= $app_config['tooltips']['z_index'] ?>
    };
    </script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/noc_tooltip.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/clipboard_utility.js" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/theme_handler.js" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/menu_handler.js" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/clock_updater.js" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/ui_aligner.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/dashboard/chart_renderer.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/quick_action_chart_logic.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/action_processor.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/manual_create_user_actions.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/report_actions.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/user_report_actions.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/security_events_actions.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/export_user_report_actions.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/notifications.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/assistant_filter.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/modules/header.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/mobile_reorder.js" defer></script>
    <script src="<?= $baseURL ?>/resources/frontend/js/admin/system_config_domains.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    
    <?php
    if (!empty($page_scripts)) {
        foreach ($page_scripts as $script) {
            echo "<script src=\"{$script}\" defer></script>\n";
        }
    }
    ?>
    <!-- ANIMATION: SPA entrance animations | Purpose: loader hide + content fade-in + sidebar slide-in on page load -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {


        const loaderContainer = document.querySelector('.loader-container');
        const mainContentArea = document.querySelector('.main-content-area');

        // Function to hide loader and show content
        const showContent = () => {
            if (loaderContainer) {
                loaderContainer.style.display = 'none';
            }

            if (mainContentArea) {
                mainContentArea.classList.add('animate-in'); // Apply animate-in to main content area
                // Specific card animations will be applied in their respective view files

                // Apply slide-in-left to the sidebar if it exists
                const leftSidebar = document.getElementById('left-sidebar');
                if (leftSidebar) {
                    leftSidebar.classList.add('slide-in-left');
                }


            }
        };

        // Animate initial content
        const spaMain = document.getElementById('spa-main-content');
        <!-- ANIMATION: SPA page enter | Purpose: initial page entrance animation -->
        if (spaMain) { spaMain.classList.add('spa-page-enter'); }

        // Hide loader and show content
        showContent();
    });
    </script>


    </script>

    <!-- GLOBAL: fetching preview animation | Purpose: 3-dot bounce + "Your request is underway" | Usage: clone template anywhere -->
    <template id="fetchingPreview">
        <div class="alert-loading-content">
            <div class="loading-dots">
                <span style="background-color: #1976D2;"></span>
                <span style="background-color: #AA3A46;"></span>
                <span style="background-color: #1B5E20;"></span>
            </div>
            <div class="loading-text">Your request is underway...</div>
        </div>
    </template>

</body>
</html>
