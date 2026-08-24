<?php
session_start();

require_once __DIR__ . '/../../../../app/Domain/Audit/audit_service.php';
include_once __DIR__ . '/../../../../bootstrap/request_context.php';
require_once __DIR__ . '/../../../../app/Domain/Auth/auth_session_service.php';

if (!isset($app_config)) {
    $app_config = require_once __DIR__ . '/../../../../config/app_config.php';
}

$config = $app_config;
$username = $_SESSION['username'] ?? 'unknown';
$session_duration = isset($_SESSION['login_time']) ? time() - $_SESSION['login_time'] : 0;
$duration_details = 'Session duration: ' . $session_duration . ' seconds.';

log_activity($username, 'User Logout', 'success', $duration_details);
auth_remove_authenticated_user($username);
auth_destroy_current_session();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logging Out...</title>
    <link href="<?= $baseURL ?>/vendor/bootstrap/bootstrap.min.css?v=<?= $app_config['app_info']['version'] ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseURL ?>/vendor/roboto/roboto.css?v=<?= $app_config['app_info']['version'] ?>">

    <script>
        localStorage.removeItem('isLoggedOut');
        window.location.href = '<?= route_url('login.php') ?>';
    </script>
</head>
<body>
    <div class="d-flex justify-content-center align-items-center" style="min-height: 100vh; background-color: #f8f9fa;">
        <div class="card text-center p-4 shadow-sm" style="max-width: 400px;">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h3 class="mb-3">Logging Out...</h3>
            <p class="text-muted">Please wait while we securely log you out.</p>
            <div id="loginRedirectButton" style="display: none;">
                <p class="mt-3">If you are not redirected automatically:</p>
                <a href="<?= route_url('login.php') ?>" class="btn btn-primary">Go to Login Page</a>
            </div>
        </div>
    </div>
    <script>
        setTimeout(function() {
            document.getElementById('loginRedirectButton').style.display = 'block';
        }, 3000);
    </script>
</body>
</html>
<?php
exit();
?>
