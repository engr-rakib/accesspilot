<?php
// Lightweight compatibility mail helper. Keep behavior aligned with legacy callers.

require_once __DIR__ . '/../../Application/Support/helpers.php';

$mailer_config = config_get('mailer', []);

function sendEmail($to, $subject, $message, $headers = '') {
    global $mailer_config;

    if (empty($headers)) {
        $headers = 'From: ' . $mailer_config['from_email'] . "\r\n" .
                   'Reply-To: ' . $mailer_config['reply_to_email'] . "\r\n" .
                   'X-Mailer: PHP/' . phpversion();
    }

    return mail($to, $subject, $message, $headers);
}
