<?php

$secureBasePath = getenv('ACCESSPILOT_SECURE_BASE_PATH') ?: 'C:/inetpub/Desk_secure_files';
$secureBasePath = rtrim($secureBasePath, '/\\');
$statePath = $secureBasePath . '/license_state.json';

return [
    'license' => [
        'state_path' => $statePath,
        // Vendor generates these PEM files for client deployments (Vendor Console tracking).
        'vendor_issued_dir' => $secureBasePath . '/vendor_issued_licenses',
        // This deployment's own active license (applied via License page).
        'deployment_active_dir' => $secureBasePath . '/deployment_active_license',
        'deployment_active_filename' => 'active_license.pem',
        // Vendor RSA signing keys uploaded from Vendor Console (fallback to scripts/vault).
        'vendor_signing_keys_dir' => $secureBasePath . '/vendor_signing_keys',
        'warning_days' => 90,
        'allow_secure_expiry_without_certificate' => false,
        'public_key_path' => __DIR__ . '/license_public.pem',
        'page_slug' => 'license',
        'contact' => [
            'company' => 'RKBZIX',
            'email' => 'rakibcse47@gmail.com',
            'phone' => '+880-1955-653548',
            'website' => 'https://accesspilot.local',
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
