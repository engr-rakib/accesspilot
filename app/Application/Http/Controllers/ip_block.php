<?php

/**
 * IP Block Management API — add / remove / toggle / list blocked IPs.
 * Requires an admin role. CSRF is enforced by the API gateway.
 */

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!has_permission('page_application_events')) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied.']);
    exit;
}

require_once __DIR__ . '/../../../Domain/Security/ip_block_service.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$data = ip_block_read();
$myIp = ip_block_client();

switch ($action) {
    case 'list':
        echo json_encode([
            'success' => true,
            'enabled' => (bool)$data['enabled'],
            'blocklist' => $data['blocklist'],
            'allowlist' => $data['allowlist'],
            'my_ip' => $myIp,
        ]);
        exit;

    case 'add':
        $entry = (string)($_POST['ip'] ?? '');
        $normalized = ip_block_normalize($entry);
        if ($normalized === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid IP address or CIDR.']);
            exit;
        }
        if (in_array($normalized, $data['blocklist'], true)) {
            echo json_encode(['success' => false, 'message' => 'IP already blocked.']);
            exit;
        }
        $data['blocklist'][] = $normalized;
        if (!ip_block_write($data)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save blocklist.']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'message' => "Blocked {$normalized}.",
            'enabled' => (bool)$data['enabled'],
            'blocklist' => $data['blocklist'],
            'my_ip' => $myIp,
        ]);
        exit;

    case 'remove':
        $entry = (string)($_POST['ip'] ?? '');
        $entry = trim($entry);
        $idx = array_search($entry, $data['blocklist'], true);
        if ($idx === false) {
            echo json_encode(['success' => false, 'message' => 'IP not found in blocklist.']);
            exit;
        }
        array_splice($data['blocklist'], $idx, 1);
        if (!ip_block_write($data)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save blocklist.']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'message' => "Unblocked {$entry}.",
            'enabled' => (bool)$data['enabled'],
            'blocklist' => $data['blocklist'],
            'my_ip' => $myIp,
        ]);
        exit;

    case 'toggle':
        $data['enabled'] = !(bool)$data['enabled'];
        if (!ip_block_write($data)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save blocklist.']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'message' => $data['enabled'] ? 'IP blocking enabled.' : 'IP blocking disabled.',
            'enabled' => (bool)$data['enabled'],
            'blocklist' => $data['blocklist'],
            'my_ip' => $myIp,
        ]);
        exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
