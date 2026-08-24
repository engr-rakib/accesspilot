<?php
$hashed_password = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Password Hash</title>
    <link href="<?= $baseURL ?>/vendor/bootstrap/bootstrap.min.css?v=<?= $app_config['app_info']['version'] ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseURL ?>/vendor/roboto/roboto.css?v=<?= $app_config['app_info']['version'] ?>">

</head>
<body>
    <div class="container">
        <h2 class="mb-4">Generate Password Hash</h2>
        <p class="text-muted">Use this tool to generate hashed passwords for your <code>App_Data/users.json</code> file. This page should be deleted after initial setup.</p>
        <form method="POST" action="">
            <div class="mb-3">
                <label for="password" class="form-label">Enter Password:</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Generate Hash</button>
        </form>
        <?php if ($hashed_password): ?>
            <div class="mt-4">
                <label for="hashed_output" class="form-label">Generated Hash:</label>
                <textarea class="form-control" id="hashed_output" rows="3" readonly><?= htmlspecialchars($hashed_password) ?></textarea>
                <small class="form-text text-muted">Copy this hash and paste it into your <code>App_Data/users.json</code> file.</small>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
