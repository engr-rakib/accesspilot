<?php
session_start();
require_once __DIR__ . '/../../../../app/Domain/UserManagement/user_management_service.php';
include_once __DIR__ . '/../../../../bootstrap/request_context.php';

$message = '';
$is_success = false;

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    $users = readUsers();
    $user_found = false;

    foreach ($users as $username => &$user_data) {
        if (isset($user_data['verification_token']) && $user_data['verification_token'] === $token) {
            if ($user_data['active'] === true) {
                $message = 'Your account is already verified and active.';
                $is_success = true;
            } else {
                $user_data['active'] = true;
                unset($user_data['verification_token']);
                if (writeUsers($users)) {
                    $message = 'Email verified successfully! Your account is now active. You can now log in.';
                    $is_success = true;
                } else {
                    $message = 'Failed to activate your account. Please try again later.';
                }
            }
            $user_found = true;
            break;
        }
    }

    if (!$user_found) {
        $message = 'Invalid or expired verification token.';
    }
} else {
    $message = 'No verification token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - AccessPilot</title>
    <link href="<?= $baseURL ?>/vendor/bootstrap/bootstrap.min.css?v=<?= $app_config['app_info']['version'] ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseURL ?>/vendor/roboto/roboto.css?v=<?= $app_config['app_info']['version'] ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/theme.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/components.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/pages.css?v=<?= time() ?>">

</head>
<body>
    <div class="verification-card text-center">
        <img src="<?= $baseURL . config_get('app_info.logo_path', '/assets/images/logo.png') ?>" alt="Logo" height="60" class="mb-4">
        <h2>Email Verification</h2>
        <?php if ($message): ?>
            <div class="alert <?= $is_success ? 'alert-success' : 'alert-danger' ?>" role="alert">
                <?= $message ?>
            </div>
        <?php endif; ?>
        <p class="mt-3 mb-0">
            <a href="<?= route_url('login.php') ?>" class="btn btn-primary">Go to Login</a>
            <a href="<?= route_url('register.php') ?>" class="btn btn-secondary">Sign Up Again</a>
        </p>
    </div>
</body>
</html>
