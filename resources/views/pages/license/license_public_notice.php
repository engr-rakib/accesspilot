<?php
$licenseStatus = $licenseStatus ?? [];
$contact = $licenseStatus['contact'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Restricted</title>
    <link href="<?= $baseURL ?>/vendor/bootstrap/bootstrap.min.css?v=<?= $app_config['app_info']['version'] ?>" rel="stylesheet">
    <link href="<?= $baseURL ?>/vendor/roboto/roboto.css?v=<?= $app_config['app_info']['version'] ?>" rel="stylesheet">
    <link href="<?= $baseURL ?>/vendor/fontawesome/all.min.css?v=<?= $app_config['app_info']['version'] ?>" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .restricted-card {
            max-width: 720px;
            width: 100%;
            background: rgba(255,255,255,0.97);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            overflow: hidden;
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .restricted-header {
            background: linear-gradient(135deg, #7f1d1d, #dc2626);
            padding: 1.5rem 2rem;
            text-align: center;
        }
        .restricted-header .icon-wrap {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        .restricted-header h1 {
            color: #fff;
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .restricted-header p {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        .restricted-body {
            padding: 2rem;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .info-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem 1.25rem;
        }
        .info-item .label {
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            color: #94a3b8;
            margin-bottom: 0.25rem;
        }
        .info-item .value {
            font-size: 0.88rem;
            font-weight: 600;
            color: #1e293b;
        }
        .contact-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
        }
        .contact-section h5 {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #3b82f6;
            margin-bottom: 1rem;
        }
        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .contact-item:last-child { margin-bottom: 0; }
        .contact-item .icon-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(59,130,246,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .contact-item .icon-circle i {
            font-size: 0.75rem;
            color: #3b82f6;
        }
        .contact-item .contact-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #94a3b8;
        }
        .contact-item .contact-value {
            font-size: 0.85rem;
            font-weight: 600;
            color: #1e293b;
        }
        @media (max-width: 576px) {
            .info-grid { grid-template-columns: 1fr; }
            .restricted-body { padding: 1.25rem; }
        }
    </style>
</head>
<body>
    <div class="restricted-card">
        <div class="restricted-header">
            <div class="icon-wrap">
                <i class="fas fa-exclamation-triangle" style="color:#fff;font-size:1.5rem;"></i>
            </div>
            <h1>Access Restricted</h1>
            <p><?= htmlspecialchars((string) ($licenseStatus['message'] ?? 'License has expired or is invalid.')) ?></p>
        </div>
        <div class="restricted-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Product</div>
                    <div class="value"><?= htmlspecialchars((string) ($licenseStatus['product_name'] ?? 'AccessPilot')) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Issued To</div>
                    <div class="value"><?= htmlspecialchars((string) ($licenseStatus['issued_to'] ?? 'Unknown')) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">License ID</div>
                    <div class="value" style="font-family:monospace;"><?= htmlspecialchars((string) ($licenseStatus['license_id'] ?? 'Not assigned')) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Expiry Date</div>
                    <div class="value"><?= htmlspecialchars((string) ($licenseStatus['expires_on'] ?? 'Not set')) ?></div>
                </div>
            </div>

            <div class="contact-section">
                <h5><i class="fas fa-headset me-2"></i>Renewal Contact</h5>
                <div class="contact-item">
                    <div class="icon-circle"><i class="fas fa-building"></i></div>
                    <div>
                        <div class="contact-label">Desk</div>
                        <div class="contact-value"><?= htmlspecialchars((string) ($contact['sales_name'] ?? 'Licensing Desk')) ?></div>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="icon-circle"><i class="fas fa-envelope"></i></div>
                    <div>
                        <div class="contact-label">Email</div>
                        <div class="contact-value"><?= htmlspecialchars((string) ($contact['email'] ?? 'N/A')) ?></div>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="icon-circle"><i class="fas fa-phone"></i></div>
                    <div>
                        <div class="contact-label">Phone</div>
                        <div class="contact-value"><?= htmlspecialchars((string) ($contact['phone'] ?? 'N/A')) ?></div>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="icon-circle"><i class="fas fa-globe"></i></div>
                    <div>
                        <div class="contact-label">Website</div>
                        <div class="contact-value"><?= htmlspecialchars((string) ($contact['website'] ?? 'N/A')) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
