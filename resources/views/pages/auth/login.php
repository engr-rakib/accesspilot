<?php
session_start();
$app_config = include __DIR__ . '/../../../../config/app_config.php';
$GLOBALS['app_config'] = $app_config;
require_once __DIR__ . '/../../../../app/Application/Support/helpers.php';
require_once __DIR__ . '/../../../../app/Domain/Licensing/license_service.php';
include_once __DIR__ . '/../../../../bootstrap/request_context.php';
$licenseStatus = license_get_status();

$securityMessages = [
    'Welcome! Please ensure you are on the official login page before entering your credentials.',
    'Tip: Use a strong and unique password that you do not use on other websites.',
    'Did you know? We never ask for your password via email or phone. Stay alert.',
    'For a smooth login experience, clear your browser cache if you face any issues.',
    'Your session is protected with end-to-end encryption. Always verify the padlock icon in your browser.',
    'If you notice any suspicious activity, report it immediately to the IT security team.',
    'Guideline: Use multi-factor authentication (MFA) for an extra layer of security.',
    'Reminder: Always log out from shared or public computers after your session ends.',
    'Stay aware: Phishing attempts often mimic login pages. Always verify the URL before typing.',
    'Welcome gesture: We are glad to have you here. Your security is our top priority.'
];
shuffle($securityMessages);
$securityMessages = array_slice($securityMessages, 0, 6);

// First visit of the day check
$today = date('Y-m-d');
$isFirstVisit = !isset($_SESSION['login_visit_date']) || $_SESSION['login_visit_date'] !== $today;
if ($isFirstVisit) {
    $_SESSION['login_visit_date'] = $today;
    $welcomeMsg = 'Welcome';
    $welcomeSub = 'We are glad to have you here.';
} else {
    $welcomeMsg = 'Welcome Back';
    $welcomeSub = 'Good to see you again.';
}

// Session lifecycle notices from session_guard redirects (?message=...)
$loginNotice = '';
$loginNoticeType = 'danger';
$messageParam = trim((string) ($_GET['message'] ?? ''));
switch ($messageParam) {
    case 'session_expired':
        $loginNotice = 'Your session ended due to inactivity. Please sign in again.';
        break;
    case 'session_terminated':
        $loginNotice = 'Your session was terminated by an administrator. Please sign in again.';
        break;
}

$uiConfig = include __DIR__ . '/../../../../config/ui.php';
$loginColors = $uiConfig['login_colors'] ?? [
    'header_column_bg' => '241, 251, 240',
    'card_panel_bg' => '246, 246, 246',
];

$initialLockSeconds = 0;
$lockIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$initialLockUntil = $_SESSION['login_locked_' . $lockIp] ?? 0;
if ((int) $initialLockUntil > time()) {
    $initialLockSeconds = (int) ceil((int) $initialLockUntil - time());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication - AccessPilot</title>
    <link rel="icon" href="<?= $baseURL . $app_config['app_info']['favicon_path'] ?>" type="image/x-icon">
    <link href="<?= $baseURL ?>/vendor/bootstrap/bootstrap.min.css?v=<?= $app_config['app_info']['version'] ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseURL ?>/vendor/roboto/roboto.css?v=<?= $app_config['app_info']['version'] ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/vendor/fontawesome/all.min.css?v=<?= $app_config['app_info']['version'] ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/login.css?v=<?= $app_config['app_info']['version'] ?>">
    <style>
        :root {
            --login-header-column-bg: <?= $loginColors['header_column_bg'] ?>;
            --login-card-panel-bg: <?= $loginColors['card_panel_bg'] ?>;
        }
    </style>
</head>
<body>

    <!-- ========== NAVBAR (commented — keep for later) ========== -->
    <!--
    <nav class="navbar" role="navigation" aria-label="Main navigation">
        <a href="#" class="navbar-brand">
            <div class="navbar-brand-icon">
                <img src="assets/images/logo_icon.png" alt="AccessPilot" width="28" height="28">
            </div>
            <span class="navbar-brand-text">AccessPilot</span>
        </a>
        <div class="navbar-links">
            <a href="#" class="navbar-link">Features</a>
            <a href="#" class="navbar-link">Documentation</a>
            <a href="#" class="navbar-link">Support</a>
            <a href="#" class="navbar-link highlight">Get Started</a>
        </div>
    </nav>
    -->

    <!-- ========== PAGE LAYOUT (no-navbar mode) ========== -->
    <div class="page-layout" style="margin-top: 0; height: 100vh;">

        <!-- ===== Hero Panel (Left) ===== -->
        <div class="hero-panel">

            <div class="hero-logo">
                <span class="hero-logo-left">
                    <img src="assets/images/logo_icon.png" alt="AccessPilot" class="hero-logo-img">
                    <span class="hero-logo-text">AccessPilot</span>
                </span>
                <div class="portal-group">
                    <a href="<?= $baseURL ?>/request_portal.php" target="_blank" class="portal-quick-link" title="Open Request Portal — submit & track AD requests">
                        <span class="portal-q-icon"><i class="fas fa-external-link-alt"></i></span>
                        <span class="portal-q-label">Request Portal</span>
                        <span class="portal-q-expand">
                            <strong>Self-Service Portal</strong><br>
                            Submit &amp; track AD requests — unlock, enable, reset, create users &amp; more. Fast, live, no login required.
                        </span>
                    </a>
                    <span class="portal-live-badge">LIVE</span>
                </div>
            </div>

            <div class="hero-badge">
                <i class="fas fa-shield-alt"></i> Enterprise Identity Platform
            </div>

            <h1 class="hero-title">
                <span class="welcome-msg"><?= $welcomeMsg ?>,</span>
                Centralized <span>Access Control</span><br>
                for Your Enterprise
            </h1>

            <p class="hero-sub">
                <?= $welcomeSub ?> Manage Active Directory, Exchange, and HRMS integrations from one unified console. Secure, auditable, and built for scale.
            </p>

            <div class="hero-features">
                <div class="hero-feature">
                    <div class="hero-feature-icon"><i class="fas fa-id-card"></i></div>
                    <div class="hero-feature-body">
                        <div class="hero-feature-title">Multi-AD lifecycle</div>
                        <div class="hero-feature-desc">Create, disable, unlock across domains</div>
                    </div>
                </div>
                <div class="hero-feature">
                    <div class="hero-feature-icon"><i class="fas fa-envelope"></i></div>
                    <div class="hero-feature-body">
                        <div class="hero-feature-title">Exchange provisioning</div>
                        <div class="hero-feature-desc">Auto-provision mailboxes with RBAC</div>
                    </div>
                </div>
                <div class="hero-feature">
                    <div class="hero-feature-icon"><i class="fas fa-server"></i></div>
                    <div class="hero-feature-body">
                        <div class="hero-feature-title">Infra monitoring</div>
                        <div class="hero-feature-desc">Real-time CPU, memory &amp; network</div>
                    </div>
                </div>
                <div class="hero-feature">
                    <div class="hero-feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="hero-feature-body">
                        <div class="hero-feature-title">RBAC &amp; audit</div>
                        <div class="hero-feature-desc">Role-based controls, compliance logs</div>
                    </div>
                </div>
                <div class="hero-feature">
                    <div class="hero-feature-icon"><i class="fas fa-cloud"></i></div>
                    <div class="hero-feature-body">
                        <div class="hero-feature-title">HRMS sync</div>
                        <div class="hero-feature-desc">Auto-sync from HR systems</div>
                    </div>
                </div>
            </div>
            <div class="security-bar" role="note">
                <i class="fas fa-shield-alt" aria-hidden="true"></i>
                <span id="security-message"><?= htmlspecialchars($securityMessages[0]) ?></span>
            </div>
        </div>

        <!-- ===== Form Panel (Right) ===== -->
        <div class="form-panel">

            <div class="form-card-wrap">

                <?php if (!empty($licenseStatus['is_restricted'])): ?>
                <div class="license-alert danger">
                    <strong>License Restricted:</strong> <?= htmlspecialchars((string) ($licenseStatus['message'] ?? 'License restricted.')) ?><br>
                    Contact <?= htmlspecialchars((string) ($licenseStatus['contact']['sales_name'] ?? 'Licensing Desk')) ?> at
                    <?= htmlspecialchars((string) ($licenseStatus['contact']['email'] ?? 'N/A')) ?>.
                </div>
                <?php elseif (!empty($licenseStatus['is_warning'])): ?>
                <div class="license-alert warning">
                    <strong>License Reminder:</strong> <?= htmlspecialchars((string) ($licenseStatus['days_remaining'] ?? 0)) ?> day(s) remaining before expiry.
                </div>
                <?php endif; ?>

                <div class="login-card">
                    <div class="card-header">
                        <img src="assets/images/logo_icon.png" alt="AccessPilot" class="card-logo">
                        <h2>Sign in</h2>
                        <p>Enter your credentials to access the dashboard</p>
                    </div>

                    <?php if ($loginNotice !== ''): ?>
                    <div class="alert alert-<?= htmlspecialchars($loginNoticeType, ENT_QUOTES) ?> login-page-notice" role="alert">
                        <?= htmlspecialchars($loginNotice) ?>
                    </div>
                    <?php endif; ?>

                    <div class="form-tabs" role="tablist">
                        <button class="form-tab active" role="tab" aria-selected="true" onclick="switchLoginTab('login-form')">Sign In</button>
                        <button class="form-tab" role="tab" aria-selected="false" onclick="switchLoginTab('register-form')">Create Account</button>
                        <button class="form-tab" role="tab" aria-selected="false" onclick="switchLoginTab('forgot-form')">Forgot Password</button>
                    </div>

                    <div id="login-form" class="login-form active" role="tabpanel">
                        <form onsubmit="handleAuth(event, 'login')" novalidate>
                            <div class="form-field">
                                <i class="fas fa-user input-icon" aria-hidden="true"></i>
                                <input type="text" name="username" placeholder="Username or email" required aria-label="Username">
                            </div>
                            <div class="form-field">
                                <i class="fas fa-lock input-icon" aria-hidden="true"></i>
                                <input type="password" id="login_password" name="password" placeholder="Password" required aria-label="Password">
                                <button type="button" class="pwd-toggle" data-toggle-target="login_password" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="form-row">
                                <label class="remember-label">
                                    <input type="checkbox" name="remember"> Remember me
                                </label>
                                <a href="#" class="forgot-link" onclick="switchLoginTab('forgot-form'); return false;">Forgot password?</a>
                            </div>
                            <button type="submit" class="btn-primary" id="login-submit">
                                <span class="spinner"></span>
                                <span class="btn-text">Sign In</span>
                            </button>
                        </form>
                    </div>

                    <div id="register-form" class="login-form" role="tabpanel">
                        <form onsubmit="handleAuth(event, 'register')" novalidate>
                            <div class="form-field">
                                <i class="fas fa-id-badge input-icon" aria-hidden="true"></i>
                                <input type="text" name="hrms_id" placeholder="HRMS ID" required aria-label="HRMS ID">
                            </div>
                            <div class="form-field">
                                <i class="fas fa-user input-icon" aria-hidden="true"></i>
                                <input type="text" name="username" placeholder="Full Name" required aria-label="Full Name">
                            </div>
                            <div class="form-field">
                                <i class="fas fa-envelope input-icon" aria-hidden="true"></i>
                                <input type="email" name="email" placeholder="Email Address" required aria-label="Email">
                            </div>
                            <button type="submit" class="btn-primary" id="register-submit">
                                <span class="spinner"></span>
                                <span class="btn-text">Submit Request</span>
                            </button>
                        </form>
                    </div>

                    <div id="forgot-form" class="login-form" role="tabpanel">
                        <form onsubmit="handleAuth(event, 'forgot')" novalidate>
                            <div class="form-field">
                                <i class="fas fa-envelope input-icon" aria-hidden="true"></i>
                                <input type="email" name="identifier" placeholder="Registered Email" required aria-label="Registered Email">
                            </div>
                            <button type="submit" class="btn-primary" id="forgot-submit">
                                <span class="spinner"></span>
                                <span class="btn-text">Send Reset Link</span>
                            </button>
                        </form>
                    </div>

                    <div id="alert-container" class="alert-container"></div>

                    <p class="signup-text">
                        Don't have an account? <a href="#" onclick="switchLoginTab('register-form'); return false;">Sign Up</a>
                    </p>
                </div>
            </div>
            <?php include __DIR__ . '/../../partials/footer.php'; ?>
        </div>

    <script>
    var securityMessages = <?= json_encode($securityMessages) ?>;
    var initialLockSeconds = <?= (int) $initialLockSeconds ?>;
    var msgIndex = 0, charIndex = 0, isDeleting = false, typingPaused = false;
    var el = document.getElementById('security-message');

    function typeEffect() {
        var current = securityMessages[msgIndex];
        if (!isDeleting && !typingPaused) {
            if (charIndex < current.length) {
                charIndex++;
                el.textContent = current.substring(0, charIndex);
                setTimeout(typeEffect, 35 + Math.random() * 30);
            } else {
                typingPaused = true;
                setTimeout(function() {
                    typingPaused = false;
                    isDeleting = true;
                    typeEffect();
                }, 3000);
            }
        } else if (isDeleting) {
            if (charIndex > 0) {
                charIndex--;
                el.textContent = current.substring(0, charIndex);
                setTimeout(typeEffect, 20 + Math.random() * 15);
            } else {
                isDeleting = false;
                msgIndex = (msgIndex + 1) % securityMessages.length;
                setTimeout(typeEffect, 300);
            }
        }
    }
    setTimeout(typeEffect, 600);

    if (initialLockSeconds > 0) {
        document.addEventListener('DOMContentLoaded', function() {
            var btn = document.getElementById('login-submit');
            var alertContainer = document.getElementById('alert-container');
            startLockoutCountdown(alertContainer, btn, 'Too many login attempts. Account locked.', initialLockSeconds);
        });
    }
    </script>

    <script src="<?= $baseURL ?>/vendor/bootstrap/bootstrap.bundle.min.js?v=<?= $app_config['app_info']['version'] ?>" defer></script>
    <script>
        function switchLoginTab(tabId) {
            document.querySelectorAll('.login-form').forEach(function(f) { f.classList.remove('active'); });
            document.querySelectorAll('.form-tab').forEach(function(b) { b.classList.remove('active'); b.setAttribute('aria-selected', 'false'); });
            document.getElementById(tabId).classList.add('active');
            var activeTab = event && event.target && event.target.closest ? event.target.closest('.form-tab') : null;
            if (activeTab) {
                activeTab.classList.add('active');
                activeTab.setAttribute('aria-selected', 'true');
            }
        }

        document.querySelectorAll('.pwd-toggle').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var target = document.getElementById(this.dataset.toggleTarget);
                var icon = this.querySelector('i');
                if (target.type === 'password') {
                    target.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                    this.setAttribute('aria-label', 'Hide password');
                } else {
                    target.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                    this.setAttribute('aria-label', 'Show password');
                }
            });
        });

        document.querySelectorAll('.login-form form').forEach(function(form) {
            form.addEventListener('submit', function() {
                var btn = this.querySelector('.btn-primary');
                if (btn) btn.classList.add('loading');
            });
        });

        function handleAuth(e, action) {
            e.preventDefault();
            var form = e.target;
            var formData = new FormData(form);
            var data = {};
            formData.forEach(function(v, k) { data[k] = v; });
            data.action = action;

            var btn = form.querySelector('.btn-primary');
            if (btn) btn.classList.add('loading');

            fetch('<?= $baseURL ?>/api/index.php?endpoint=auth_api&action=' + action, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (btn) btn.classList.remove('loading');
                var alertContainer = document.getElementById('alert-container');
                if (res.success) {
                    alertContainer.innerHTML = '<div class="alert alert-success">' + (res.message || 'Success') + '</div>';
                    if (res.redirect) setTimeout(function() { window.location.href = res.redirect; }, 1000);
                    else setTimeout(function() { window.location.reload(); }, 1000);
                } else if (res.retry_after > 0) {
                    startLockoutCountdown(alertContainer, btn, res.message, res.retry_after);
                } else {
                    alertContainer.innerHTML = '<div class="alert alert-danger">' + (res.message || 'An error occurred') + '</div>';
                }
            })
            .catch(function(err) {
                if (btn) btn.classList.remove('loading');
                document.getElementById('alert-container').innerHTML = '<div class="alert alert-danger">Connection error. Please try again.</div>';
            });
        }

        function startLockoutCountdown(alertContainer, btn, message, retryAfter) {
            if (btn) btn.disabled = true;
            var endTime = Date.now() + (retryAfter * 1000);
            var alertEl = document.createElement('div');
            alertEl.className = 'alert alert-danger';
            var msgSpan = document.createElement('span');
            msgSpan.textContent = message;
            var timerSpan = document.createElement('span');
            timerSpan.className = 'lock-timer';
            alertEl.appendChild(msgSpan);
            alertEl.appendChild(document.createElement('br'));
            alertEl.appendChild(timerSpan);
            alertContainer.innerHTML = '';
            alertContainer.appendChild(alertEl);

            var timer = setInterval(function() {
                var remaining = Math.max(0, Math.ceil((endTime - Date.now()) / 1000));
                if (remaining <= 0) {
                    clearInterval(timer);
                    timerSpan.innerHTML = 'Lock expired. You can try again now.';
                    if (btn) btn.disabled = false;
                    return;
                }
                var m = Math.floor(remaining / 60);
                var s = remaining % 60;
                var timeStr = (m > 0 ? m + ' min ' : '') + s + ' sec';
                timerSpan.textContent = 'Retry in ' + timeStr;
            }, 1000);
            timerSpan.textContent = 'Retry in ' + (retryAfter >= 60 ? Math.floor(retryAfter / 60) + ' min ' : '') + (retryAfter % 60) + ' sec';
        }
    </script>
</body>
</html>
