<?php
/**
 * LDAP defaults — runtime settings live in {secure_base_path}/ldap/config.json
 */

return [
    'ldap' => [
        'enabled' => false,
        'backend' => 'powershell',
        'host' => '',
        'port' => 389,
        'use_tls' => false,
        'base_dn' => '',
        'bind_dn' => '',
        'user_search_base' => '',
        'connect_timeout' => 5,
        'page_size' => 500,
    ],
];
