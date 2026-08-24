<?php

// =========================================================================
// --- AccessPilot Mailer & Alert Configuration ---
// =========================================================================
// This file centralizes mailer settings and global alert toggles.

return [
    'mailer' => [
        // Basic mail() function settings
        'from_email' => 'no-reply@accesspilot.local',
        'reply_to_email' => 'no-reply@accesspilot.local',

        // Global Alert Toggles
        'alerts_enabled' => true,            // Master switch for all email alerts
        'monitoring_alerts_enabled' => true, // specifically for NOC/Infrastructure alerts
        'security_alerts_enabled' => true,   // for login failures, etc.

        // PHPMailer / SMTP settings (Requires vendor/phpmailer)
        'use_phpmailer' => false, 
        'smtp_host' => 'smtp.example.com',
        'smtp_auth' => true,
        'smtp_username' => 'user@example.com',
        'smtp_password' => 'your_smtp_password',
        'smtp_secure' => 'tls', 
        'smtp_port' => 587,
    ]
];
