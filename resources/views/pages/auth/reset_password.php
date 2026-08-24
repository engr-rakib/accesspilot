<?php
session_start();
require_once __DIR__ . '/../../../../app/Domain/UserManagement/user_management_service.php';
include_once __DIR__ . '/../../../../bootstrap/request_context.php';

$message = '';
$is_success = false;
$token_valid = false;
$reset_token = $_GET['token'] ?? '';

if (!empty($reset_token)) {
    $users = readUsers();
    foreach ($users as $username => $user_data) {
        if (isset($user_data['reset_token']) && $user_data['reset_token'] === $reset_token) {
            if (isset($user_data['reset_token_expires']) && strtotime($user_data['reset_token_expires']) > time()) {
                $token_valid = true;
                break;
            } else {
                $message = 'Password reset token has expired.';
            }
        }
    }
    if (!$token_valid && empty($message)) {
        $message = 'Invalid password reset token.';
    }
} else {
    $message = 'No password reset token provided.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    if (empty($new_password) || empty($confirm_new_password)) {
        $message = 'Please enter and confirm your new password.';
    } elseif ($new_password !== $confirm_new_password) {
        $message = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $message = 'New password must be at least 6 characters long.';
    } else {
        $users = readUsers();
        foreach ($users as $username => &$user_data) {
            if (isset($user_data['reset_token']) && $user_data['reset_token'] === $reset_token) {
                $user_data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
                unset($user_data['reset_token']);
                unset($user_data['reset_token_expires']);

                if (writeUsers($users)) {
                    $message = 'Your password has been reset successfully. You can now log in with your new password.';
                    $is_success = true;
                    $token_valid = false;
                } else {
                    $message = 'Failed to reset password. Please try again later.';
                }
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - AccessPilot</title>
    <link href="<?= $baseURL ?>/vendor/bootstrap/bootstrap.min.css?v=<?= $app_config['app_info']['version'] ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseURL ?>/vendor/roboto/roboto.css?v=<?= $app_config['app_info']['version'] ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/theme.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/components.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= $baseURL ?>/resources/frontend/css/pages.css?v=<?= time() ?>">

</head>
<body>
    <div class="reset-card text-center">
        <img src="<?= $baseURL . config_get('app_info.logo_path', '/assets/images/logo.png') ?>" alt="Logo" height="60" class="mb-4">
        <h2>Reset Password</h2>
        <?php if ($message): ?>
            <div class="alert <?= $is_success ? 'alert-success' : 'alert-danger' ?>" role="alert">
                <?= $message ?>
            </div>
        <?php endif; ?>
        <?php if ($token_valid): ?>
            <form method="POST" action="">
                <input type="hidden" name="token" value="<?= htmlspecialchars($reset_token) ?>">
                <div class="mb-3">
                    <input type="password" class="form-control" id="new_password" name="new_password" placeholder="New Password" required>
                </div>
                <div class="mb-4">
                    <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" placeholder="Confirm New Password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Reset Password</button>
            </form>
        <?php endif; ?>
        <p class="mt-3 mb-0"><a href="<?= route_url('login.php') ?>">Back to Login</a></p>
    </div>
</body>
</html>
