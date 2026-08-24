<?php

$secureBasePath = getenv('ACCESSPILOT_SECURE_BASE_PATH') ?: 'VAR_SECURE_BASE_PATH';
$secureBasePath = rtrim($secureBasePath, '/\\');
$statePath = $secureBasePath . '/license_state.json';

return [
    'license' => [
        'state_path' => $statePath,
        'vendor_issued_dir' => $secureBasePath . '/vendor_issued_licenses',
        'deployment_active_dir' => $secureBasePath . '/deployment_active_license',
        'deployment_active_filename' => 'active_license.pem',
        'vendor_signing_keys_dir' => $secureBasePath . '/vendor_signing_keys',
        'warning_days' => 90,
        'allow_secure_expiry_without_certificate' => false,
        'public_key_path' => __DIR__ . '/license_public.pem',
        'page_slug' => 'license',
        'contact' => [
            'company' => 'CHANGE_ME_LICENSE_CONTACT_ORG',
            'email' => 'CHANGE_ME_LICENSE_SUPPORT_EMAIL',
            'phone' => 'CHANGE_ME_LICENSE_SUPPORT_PHONE',
            'website' => 'CHANGE_ME_LICENSE_SUPPORT_URL',
            'sales_name' => 'AccessPilot Licensing Desk',
        ],
        'policy' => [
            'title' => 'AccessPilot Software License Policy',
            'summary' => 'This deployment is licensed for controlled internal use. License validity controls access to all operational features.',
            'clauses' => [
                'The license is deployment-specific and should be issued for the approved organization or environment only.',
                'License status is evaluated by signed external certificate data and secure expiry metadata.',
                'Three months before expiry, the application starts showing renewal alerts in the notification system and license page.',
                'Once the license expires, operational features become restricted and the application shows the license policy and renewal information instead of normal work surfaces.',
                'Tampering with secure license metadata, certificate contents, or signature verification logic is outside supported operation.',
                'Renewal, certificate replacement, or commercial support should be handled through the configured contact channel.',
            ],
        ],
    ],
];