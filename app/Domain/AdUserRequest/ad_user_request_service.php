<?php

require_once __DIR__ . '/../../Infrastructure/Persistence/repositories.php';
require_once __DIR__ . '/../../Ldap/Config/ldap_config_repository.php';

function ad_user_request_allowed_domains(): array
{
    $domains = function_exists('ldap_get_domains') ? ldap_get_domains() : [];
    $map = [];
    foreach ($domains as $d) {
        $key = $d['key'] ?? '';
        if ($key === '') continue;
        $aliases = [$key, $key . '.com'];
        $map[$key] = $aliases;
    }
    return $map ?: ['example' => ['example', 'example.com'], 'example' => ['example', 'example.com']];
}

function ad_user_request_normalize_domain(?string $domain): ?string
{
    $value = strtolower(trim((string)$domain));
    if ($value === '') {
        return null;
    }

    foreach (ad_user_request_allowed_domains() as $canonical => $aliases) {
        foreach ($aliases as $alias) {
            if ($value === strtolower($alias)) {
                return $canonical;
            }
        }
    }

    return null;
}

function ad_user_request_normalize_target(string $target, ?string $selectedDomain = null): array
{
    $rawTarget = trim($target);
    if ($rawTarget === '') {
        return ['success' => false, 'message' => 'Target user ID is required for this request.'];
    }

    $selectedDomainNormalized = ad_user_request_normalize_domain($selectedDomain);
    $cleanTarget = preg_replace('/\s+/', '', str_replace('/', '\\', $rawTarget));
    $parts = explode('\\', $cleanTarget);

    $typedDomain = null;
    $accountId = $cleanTarget;
    $warning = '';

    if (count($parts) > 2) {
        return ['success' => false, 'message' => 'Target user format is invalid. Use `user_id` or `domain\\user_id`.'];
    }

    if (count($parts) === 2) {
        $typedDomain = ad_user_request_normalize_domain($parts[0]);
        if ($typedDomain === null) {
            return ['success' => false, 'message' => 'Unsupported domain. Use `example` or `example`.'];
        }
        $accountId = trim($parts[1]);
        if ($selectedDomainNormalized && $typedDomain !== $selectedDomainNormalized) {
            $warning = 'Typed domain differs from the selected domain. The typed domain was used.';
        }
    }

    if ($accountId === '') {
        return ['success' => false, 'message' => 'Target user ID is required for this request.'];
    }

    $finalDomain = $typedDomain ?: ($selectedDomainNormalized ?: 'example');

    return [
        'success' => true,
        'account_id' => $accountId,
        'domain' => $finalDomain,
        'display_username' => $finalDomain . '\\' . $accountId,
        'warning' => $warning,
    ];
}

function ad_user_request_get_file_path(): string
{
    return repo_ad_user_requests_path();
}

function ad_user_request_ensure_file(): string
{
    $file = ad_user_request_get_file_path();
    repo_ensure_json_file($file, []);
    return $file;
}

function ad_user_request_read_all(): array
{
    ad_user_request_ensure_file();
    return repo_read_ad_user_requests();
}

function ad_user_request_write_all(array $requests): bool
{
    ad_user_request_ensure_file();
    return repo_write_ad_user_requests($requests);
}

function ad_user_request_type_map(): array
{
    return [
        'unlock' => [
            'label' => 'Unlock User',
            'action' => 'unlockUser',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => false,
        ],
        'reset_unlock' => [
            'label' => 'Reset & Unlock',
            'action' => 'resetUnlock',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => false,
        ],
        'enable' => [
            'label' => 'Enable User',
            'action' => 'enableUser',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => false,
        ],
        'disable' => [
            'label' => 'Disable User',
            'action' => 'disableUser',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => false,
        ],
        'new_user' => [
            'label' => 'New User Create',
            'action' => 'createUser',
            'needs_target' => true,
            'needs_hrms_id' => true,
            'needs_exchange' => false,
        ],
        'modify_user' => [
            'label' => 'Modify User',
            'action' => 'modifyuser',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => false,
        ],
        'create_custom_user' => [
            'label' => 'Create Custom User',
            'action' => 'manualCreateCustomUser',
            'needs_target' => false,
            'needs_hrms_id' => false,
            'needs_exchange' => false,
        ],
        'create_service_account' => [
            'label' => 'Create Service Account',
            'action' => 'manualCreateCustomUser',
            'needs_target' => false,
            'needs_hrms_id' => false,
            'needs_exchange' => false,
        ],
        'exchange_enable_mailbox' => [
            'label' => 'Enable Mailbox',
            'action' => 'mailbox_enable',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => true,
        ],
        'exchange_disable_mailbox' => [
            'label' => 'Disable Mailbox',
            'action' => 'mailbox_disable',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => true,
        ],
        'exchange_add_email' => [
            'label' => 'Add Email Address',
            'action' => 'mailbox_add_address',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => true,
        ],
        'exchange_remove_email' => [
            'label' => 'Remove Email Address',
            'action' => 'mailbox_remove_address',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => true,
        ],
        'exchange_set_primary_smtp' => [
            'label' => 'Change Primary Email',
            'action' => 'mailbox_set_primary_smtp',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => true,
        ],
        'exchange_set_forward' => [
            'label' => 'Set Email Forwarding',
            'action' => 'mailbox_set_forward',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => true,
        ],
        'exchange_set_quota' => [
            'label' => 'Increase Mailbox Size',
            'action' => 'mailbox_set_quota',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => true,
        ],
        'exchange_hide_gal' => [
            'label' => 'Hide from Address List',
            'action' => 'mailbox_set_hidden_gal',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => true,
        ],
        'exchange_enable_archive' => [
            'label' => 'Enable Archive Mailbox',
            'action' => 'mailbox_enable_archive',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => true,
        ],
        'exchange_disable_archive' => [
            'label' => 'Disable Archive Mailbox',
            'action' => 'mailbox_disable_archive',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => true,
        ],
        'exchange_set_litigation_hold' => [
            'label' => 'Set Litigation Hold',
            'action' => 'mailbox_set_litigation_hold',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => true,
        ],
        'exchange_set_mail_tip' => [
            'label' => 'Set Mail Tip',
            'action' => 'mailbox_set_mail_tip',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => true,
        ],
        'exchange_group_create' => [
            'label' => 'Create Distribution Group',
            'action' => 'group_create',
            'needs_target' => false,
            'needs_hrms_id' => false,
            'needs_exchange' => true,
            'extra_fields' => ['group_name', 'group_alias', 'group_description'],
        ],
        'exchange_group_add_member' => [
            'label' => 'Add Group Member',
            'action' => 'group_add_member',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => true,
        ],
        'exchange_group_remove_member' => [
            'label' => 'Remove Group Member',
            'action' => 'group_remove_member',
            'needs_target' => true,
            'needs_hrms_id' => false,
            'needs_exchange' => true,
        ],
    ];
}

function ad_user_request_validate(array $payload): array
{
    $type = trim((string)($payload['request_type'] ?? ''));
    $requester_name = trim((string)($payload['requester_name'] ?? ''));
    $requester_email = trim((string)($payload['requester_email'] ?? ''));
    $requester_contact = trim((string)($payload['requester_contact'] ?? ''));
    $target_username = trim((string)($payload['target_username'] ?? ''));
    $selected_domain = trim((string)($payload['selected_domain'] ?? ''));
    $hrms_id = trim((string)($payload['hrms_id'] ?? ''));
    $requested_name = trim((string)($payload['requested_name'] ?? ''));
    $justification = trim((string)($payload['justification'] ?? ''));
    $custom_username = trim((string)($payload['custom_username'] ?? ''));
    $custom_display_name = trim((string)($payload['custom_display_name'] ?? ''));
    $exchange_email = trim((string)($payload['exchange_email'] ?? ''));
    $exchange_extra = trim((string)($payload['exchange_extra'] ?? ''));
    $group_name = trim((string)($payload['group_name'] ?? ''));
    $group_alias = trim((string)($payload['group_alias'] ?? ''));
    $group_description = trim((string)($payload['group_description'] ?? ''));

    $map = ad_user_request_type_map();
    if (!isset($map[$type])) {
        return ['success' => false, 'message' => 'Please select a valid request type.'];
    }

    if ($requester_name === '' || $requester_email === '') {
        return ['success' => false, 'message' => 'Requester name and email are required.'];
    }

    if (!filter_var($requester_email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid requester email address.'];
    }

    $normalizedTarget = null;
    if ($map[$type]['needs_target']) {
        $normalizedTarget = ad_user_request_normalize_target($target_username, $selected_domain);
        if (!$normalizedTarget['success']) {
            return $normalizedTarget;
        }
    }

    if ($map[$type]['needs_hrms_id'] && $hrms_id === '') {
        return ['success' => false, 'message' => 'HRMS ID is required for new user creation requests.'];
    }

    if ($type === 'modify_user' && $justification === '') {
        return ['success' => false, 'message' => 'Please describe what needs to be modified.'];
    }

    if ($type === 'create_custom_user') {
        if ($custom_display_name === '' || $custom_username === '') {
            return ['success' => false, 'message' => 'Display Name and Username are required for custom user creation requests.'];
        }
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $custom_username)) {
            return ['success' => false, 'message' => 'Custom username may contain only letters, numbers, dot, underscore, and hyphen.'];
        }
    }

    if ($type === 'create_service_account') {
        if ($custom_display_name === '' || $custom_username === '' || $justification === '') {
            return ['success' => false, 'message' => 'Display Name, Username, and Server/Operation are required for service account creation requests.'];
        }
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $custom_username)) {
            return ['success' => false, 'message' => 'Service account username may contain only letters, numbers, dot, underscore, and hyphen.'];
        }
    }

    // Exchange type field validations
    if (($map[$type]['needs_exchange'] ?? false)) {
        if (in_array($type, ['exchange_add_email', 'exchange_remove_email', 'exchange_set_primary_smtp', 'exchange_set_forward'], true) && $exchange_email === '') {
            return ['success' => false, 'message' => 'Email address is required for this exchange request.'];
        }
        if ($type === 'exchange_set_litigation_hold' && $justification === '') {
            return ['success' => false, 'message' => 'Please provide a reason for litigation hold.'];
        }
        if ($type === 'exchange_set_mail_tip' && $exchange_extra === '') {
            return ['success' => false, 'message' => 'Mail tip text is required.'];
        }
        if ($type === 'exchange_group_create') {
            if ($group_name === '') {
                return ['success' => false, 'message' => 'Group name is required.'];
            }
        }
        if (in_array($type, ['exchange_group_add_member', 'exchange_group_remove_member'], true) && $exchange_extra === '') {
            return ['success' => false, 'message' => 'Member identity is required.'];
        }
    }

    $target_username_value = $normalizedTarget['account_id'] ?? '';
    $target_domain_value = $normalizedTarget['domain'] ?? '';
    $target_display_value = $normalizedTarget['display_username'] ?? '';
    $target_warning_value = $normalizedTarget['warning'] ?? '';

    if ($type === 'create_custom_user' || $type === 'create_service_account') {
        $target_username_value = $custom_username;
        $target_display_value = $custom_username;
        $target_domain_value = '';
        $target_warning_value = '';
    }

    return [
        'success' => true,
        'data' => [
            'request_type' => $type,
            'request_type_label' => $map[$type]['label'],
            'requester_name' => $requester_name,
            'requester_email' => $requester_email,
            'requester_contact' => $requester_contact,
            'target_username' => $target_username_value,
            'target_domain' => $target_domain_value,
            'target_display_username' => $target_display_value,
            'target_warning' => $target_warning_value,
            'hrms_id' => $hrms_id,
            'requested_name' => $requested_name,
            'custom_username' => $custom_username,
            'custom_display_name' => $custom_display_name,
            'justification' => $justification,
            'exchange_email' => $exchange_email,
            'exchange_extra' => $exchange_extra,
            'group_name' => $group_name,
            'group_alias' => $group_alias,
            'group_description' => $group_description,
        ],
    ];
}

function ad_user_request_submit(array $payload): array
{
    $validated = ad_user_request_validate($payload);
    if (!$validated['success']) {
        return $validated;
    }

    $data = $validated['data'];
    $requests = ad_user_request_read_all();

    foreach ($requests as $request) {
        if (
            ($request['status'] ?? '') === 'pending' &&
            ($request['request_type'] ?? '') === $data['request_type'] &&
            mb_strtolower((string)($request['target_username'] ?? '')) === mb_strtolower($data['target_username']) &&
            mb_strtolower((string)($request['requester_email'] ?? '')) === mb_strtolower($data['requester_email'])
        ) {
            if (!in_array($data['request_type'] ?? '', ['create_custom_user', 'create_service_account']) &&
                mb_strtolower((string)($request['target_domain'] ?? '')) !== mb_strtolower((string)($data['target_domain'] ?? ''))) {
                continue;
            }
            return ['success' => false, 'message' => 'A similar request is already pending review.'];
        }
    }

    $record = [
        'id' => 'aur_' . date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8),
        'request_type' => $data['request_type'],
        'request_type_label' => $data['request_type_label'],
        'requester_name' => $data['requester_name'],
        'requester_email' => $data['requester_email'],
        'requester_contact' => $data['requester_contact'],
        'target_username' => $data['target_username'],
        'target_domain' => $data['target_domain'],
        'target_display_username' => $data['target_display_username'],
        'target_warning' => $data['target_warning'],
        'hrms_id' => $data['hrms_id'],
        'requested_name' => $data['requested_name'],
        'custom_username' => $data['custom_username'],
        'custom_display_name' => $data['custom_display_name'],
        'justification' => $data['justification'],
        'exchange_email' => $data['exchange_email'] ?? '',
        'exchange_extra' => $data['exchange_extra'] ?? '',
        'group_name' => $data['group_name'] ?? '',
        'group_alias' => $data['group_alias'] ?? '',
        'group_description' => $data['group_description'] ?? '',
        'status' => 'pending',
        'timestamp' => date('Y-m-d h:i:s A'),
        'processed_at' => '',
        'processed_by' => '',
        'process_note' => '',
        'execution_message' => '',
    ];

    $requests[] = $record;
    if (!ad_user_request_write_all($requests)) {
        return ['success' => false, 'message' => 'Failed to save the request.'];
    }

    $message = 'Request submitted successfully. Please wait for administrator review.';
    if (!empty($data['target_warning'])) {
        $message .= ' Warning: ' . $data['target_warning'];
    }

    return ['success' => true, 'message' => $message, 'request' => $record];
}

function ad_user_request_get_pending(): array
{
    return array_values(array_filter(ad_user_request_read_all(), fn($item) => ($item['status'] ?? '') === 'pending'));
}

function ad_user_request_status_label(string $status): string
{
    $normalized = strtolower(trim($status));
    return match ($normalized) {
        'pending' => 'Pending Review',
        'completed' => 'Approved',
        'failed' => 'Approved But Failed',
        'denied' => 'Denied',
        default => ucfirst($normalized !== '' ? $normalized : 'Unknown'),
    };
}

function ad_user_request_get_requester_history(string $lookupType, string $lookupValue): array
{
    $lookupType = strtolower(trim($lookupType));
    $lookupValue = trim($lookupValue);

    if ($lookupValue === '') {
        return ['success' => false, 'message' => 'Tracking value is required.'];
    }

    if (!in_array($lookupType, ['email', 'contact'], true)) {
        return ['success' => false, 'message' => 'Invalid tracking source.'];
    }

    $requests = ad_user_request_read_all();
    $matched = array_values(array_filter($requests, function ($request) use ($lookupType, $lookupValue) {
        $field = $lookupType === 'email' ? 'requester_email' : 'requester_contact';
        return mb_strtolower(trim((string)($request[$field] ?? ''))) === mb_strtolower($lookupValue);
    }));

    usort($matched, function ($a, $b) {
        return strcmp((string)($b['timestamp'] ?? ''), (string)($a['timestamp'] ?? ''));
    });

    $history = array_map(function ($request) {
        return [
            'id' => (string)($request['id'] ?? ''),
            'request_type_label' => (string)($request['request_type_label'] ?? ''),
            'target' => (string)($request['target_display_username'] ?? ($request['target_username'] ?? '')),
            'custom_display_name' => (string)($request['custom_display_name'] ?? ''),
            'requested_name' => (string)($request['requested_name'] ?? ''),
            'justification' => (string)($request['justification'] ?? ''),
            'timestamp' => (string)($request['timestamp'] ?? ''),
            'status' => (string)($request['status'] ?? ''),
            'status_label' => ad_user_request_status_label((string)($request['status'] ?? '')),
            'processed_at' => (string)($request['processed_at'] ?? ''),
            'processed_by' => (string)($request['processed_by'] ?? ''),
            'process_note' => (string)($request['process_note'] ?? ''),
            'execution_message' => (string)($request['execution_message'] ?? ''),
        ];
    }, $matched);

    return [
        'success' => true,
        'requests' => $history,
        'message' => empty($history) ? 'No request history found for this lookup value.' : 'Request history loaded successfully.',
    ];
}

function ad_user_request_get_by_id(string $request_id): ?array
{
    $requests = ad_user_request_read_all();
    $index = ad_user_request_find_index($requests, $request_id);
    if ($index < 0) {
        return null;
    }

    return $requests[$index];
}

function ad_user_request_find_index(array $requests, string $request_id): int
{
    foreach ($requests as $index => $request) {
        if (($request['id'] ?? '') === $request_id) {
            return $index;
        }
    }
    return -1;
}

function ad_user_request_approve(string $request_id, string $operator): array
{
    $requests = ad_user_request_read_all();
    $index = ad_user_request_find_index($requests, $request_id);
    if ($index < 0) {
        return ['success' => false, 'message' => 'Request not found.'];
    }

    $request = $requests[$index];
    if (($request['status'] ?? '') !== 'pending') {
        return ['success' => false, 'message' => 'This request has already been processed.'];
    }

    $map = ad_user_request_type_map();
    $type = (string)($request['request_type'] ?? '');
    if (!isset($map[$type])) {
        return ['success' => false, 'message' => 'Unsupported request type.'];
    }

    $targetDomain = (string)($request['target_domain'] ?? '');
    if ($targetDomain !== '') {
        $normalizedDomain = ad_user_request_normalize_domain($targetDomain);
        if ($normalizedDomain !== null && function_exists('ldap_set_active_domain')) {
            ldap_set_active_domain($normalizedDomain);
        }
    }

    if (!empty($map[$type]['needs_exchange'])) {
        require_once __DIR__ . '/../../Infrastructure/PowerShell/ExchangePsRunner.php';
        $exchangeAction = $map[$type]['action'];
        $target = (string)($request['target_username'] ?? '');
        $exchangeEmail = (string)($request['exchange_email'] ?? '');
        $exchangeExtra = (string)($request['exchange_extra'] ?? '');
        $groupName = (string)($request['group_name'] ?? '');
        $groupAlias = (string)($request['group_alias'] ?? '');
        $groupDescription = (string)($request['group_description'] ?? '');

        try {
            $result = match ($exchangeAction) {
                'mailbox_enable' => exchange_enable_mailbox($target),
                'mailbox_disable' => exchange_disable_mailbox($target),
                'mailbox_add_address' => exchange_add_email_address($target, $exchangeEmail),
                'mailbox_remove_address' => exchange_remove_email_address($target, $exchangeEmail),
                'mailbox_set_primary_smtp' => exchange_set_primary_smtp($target, $exchangeEmail),
                'mailbox_set_forward' => exchange_set_forwarding($target, $exchangeEmail),
                'mailbox_set_quota' => exchange_set_mailbox_quota($target, $exchangeExtra),
                'mailbox_set_hidden_gal' => exchange_set_hidden_from_gal($target, true),
                'mailbox_enable_archive' => exchange_enable_archive($target),
                'mailbox_disable_archive' => exchange_disable_archive($target),
                'mailbox_set_litigation_hold' => exchange_set_litigation_hold($target, true),
                'mailbox_set_mail_tip' => exchange_set_mail_tip($target, $exchangeExtra),
                'group_create' => exchange_new_distribution_group($groupName, $groupAlias, $groupDescription),
                'group_add_member' => exchange_add_group_member($target, $exchangeExtra),
                'group_remove_member' => exchange_remove_group_member($target, $exchangeExtra),
                default => throw new \RuntimeException("Unsupported exchange action: $exchangeAction"),
            };
            $success = !empty($result['success']);
            $message = $result['message'] ?? 'Exchange action completed.';
        } catch (\Throwable $e) {
            $success = false;
            $message = 'Exchange action error: ' . $e->getMessage();
        }

        $requests[$index]['status'] = $success ? 'completed' : 'failed';
        $requests[$index]['processed_at'] = date('Y-m-d h:i:s A');
        $requests[$index]['processed_by'] = $operator;
        $requests[$index]['execution_message'] = strip_tags($message);
        $requests[$index]['process_note'] = $success ? 'Approved and executed via Exchange.' : 'Exchange execution failed.';

        if (!ad_user_request_write_all($requests)) {
            return ['success' => false, 'message' => 'Action ran, but the request status could not be saved.'];
        }

        return [
            'success' => $success,
            'message' => $message,
            'request' => $requests[$index],
        ];
    }

    require_once __DIR__ . '/../ActiveDirectory/action_executor.php';
    $target = $type === 'new_user'
        ? ((string)($request['hrms_id'] ?? '') ?: (string)($request['target_username'] ?? ''))
        : (string)($request['target_username'] ?? '');

    if ($type === 'create_custom_user') {
        return [
            'success' => false,
            'message' => 'Custom user requests must be processed through the manual user creation form.',
        ];
    }

    $result = executeADAction($target, $map[$type]['action'], $operator);

    $requests[$index]['status'] = $result['success'] ? 'completed' : 'failed';
    $requests[$index]['processed_at'] = date('Y-m-d h:i:s A');
    $requests[$index]['processed_by'] = $operator;
    $requests[$index]['execution_message'] = strip_tags((string)($result['message'] ?? ''));
    $requests[$index]['process_note'] = $result['success'] ? 'Approved and executed.' : 'Execution failed.';

    if (!ad_user_request_write_all($requests)) {
        return ['success' => false, 'message' => 'Action ran, but the request status could not be saved.'];
    }

    return [
        'success' => (bool)$result['success'],
        'message' => $result['message'] ?? 'Request processed.',
        'request' => $requests[$index],
    ];
}

function ad_user_request_prepare_execution(string $request_id): array
{
    $request = ad_user_request_get_by_id($request_id);
    if (!$request) {
        return ['success' => false, 'message' => 'Request not found.'];
    }

    if (($request['status'] ?? '') !== 'pending') {
        return ['success' => false, 'message' => 'This request has already been processed.'];
    }

    $map = ad_user_request_type_map();
    $type = (string)($request['request_type'] ?? '');
    if (!isset($map[$type])) {
        return ['success' => false, 'message' => 'Unsupported request type.'];
    }

    $target = $type === 'new_user'
        ? ((string)($request['hrms_id'] ?? '') ?: (string)($request['target_username'] ?? ''))
        : (string)($request['target_username'] ?? '');

    if ($type === 'create_custom_user' || $type === 'create_service_account') {
        $description = $type === 'create_service_account'
            ? trim(
                'Service Account for ' . (string)($request['justification'] ?? '') .
                ' | Requested by ' . (string)($request['requester_name'] ?? '') .
                (!empty($request['requester_email']) ? ' | ' . (string)$request['requester_email'] : '') .
                (!empty($request['requester_contact']) ? ' | ' . (string)$request['requester_contact'] : '')
            )
            : trim(
                'Requested by ' . (string)($request['requester_name'] ?? '') .
                (!empty($request['requester_email']) ? ' | ' . (string)$request['requester_email'] : '') .
                (!empty($request['requester_contact']) ? ' | ' . (string)$request['requester_contact'] : '') .
                (!empty($request['justification']) ? ' | ' . (string)$request['justification'] : '')
            );
        return [
            'success' => true,
            'request' => $request,
            'execution' => [
                'action' => 'manualCreateCustomUser',
                'target' => (string)($request['custom_username'] ?? ''),
                'label' => $map[$type]['label'],
                'manual_create' => [
                    'username' => (string)($request['custom_username'] ?? ''),
                    'display_name' => (string)($request['custom_display_name'] ?? ''),
                    'description' => $description,
                    'is_service_account' => $type === 'create_service_account',
                    'server_operation' => $type === 'create_service_account' ? (string)($request['justification'] ?? '') : '',
                ],
            ],
        ];
    }

    return [
        'success' => true,
        'request' => $request,
        'execution' => [
            'action' => $map[$type]['action'],
            'target' => $target,
            'label' => $map[$type]['label'],
        ],
    ];
}

function ad_user_request_finalize_execution(string $request_id, string $operator, bool $success, string $message = ''): array
{
    $requests = ad_user_request_read_all();
    $index = ad_user_request_find_index($requests, $request_id);
    if ($index < 0) {
        return ['success' => false, 'message' => 'Request not found.'];
    }

    if (($requests[$index]['status'] ?? '') !== 'pending') {
        return ['success' => false, 'message' => 'This request has already been processed.'];
    }

    $requests[$index]['status'] = $success ? 'completed' : 'failed';
    $requests[$index]['processed_at'] = date('Y-m-d h:i:s A');
    $requests[$index]['processed_by'] = $operator;
    $requests[$index]['execution_message'] = strip_tags($message);
    $requests[$index]['process_note'] = $success ? 'Approved and executed via quick action.' : 'Quick action execution failed.';

    if (!ad_user_request_write_all($requests)) {
        return ['success' => false, 'message' => 'Execution ran, but the request status could not be saved.'];
    }

    return [
        'success' => $success,
        'message' => $message !== '' ? $message : ($success ? 'Request processed.' : 'Request failed.'),
        'request' => $requests[$index],
    ];
}

function ad_user_request_deny(string $request_id, string $operator, string $note = ''): array
{
    $requests = ad_user_request_read_all();
    $index = ad_user_request_find_index($requests, $request_id);
    if ($index < 0) {
        return ['success' => false, 'message' => 'Request not found.'];
    }

    if (($requests[$index]['status'] ?? '') !== 'pending') {
        return ['success' => false, 'message' => 'This request has already been processed.'];
    }

    $requests[$index]['status'] = 'denied';
    $requests[$index]['processed_at'] = date('Y-m-d h:i:s A');
    $requests[$index]['processed_by'] = $operator;
    $requests[$index]['process_note'] = trim($note) !== '' ? trim($note) : 'Denied by administrator.';

    if (!ad_user_request_write_all($requests)) {
        return ['success' => false, 'message' => 'Failed to save the denied request state.'];
    }

    return ['success' => true, 'message' => 'Request denied successfully.', 'request' => $requests[$index]];
}

function ad_user_request_get_recently_resolved(int $limit = 5): array
{
    $requests = ad_user_request_read_all();
    $resolved = array_values(array_filter($requests, function($req) {
        $status = strtolower($req['status'] ?? '');
        return in_array($status, ['completed', 'failed', 'denied'], true);
    }));

    // Sort by processed_at descending
    usort($resolved, function($a, $b) {
        $timeA = $a['processed_at'] ?: $a['timestamp'];
        $timeB = $b['processed_at'] ?: $b['timestamp'];
        return strcmp($timeB, $timeA);
    });

    $recent = array_slice($resolved, 0, $limit);
    
    return array_map(function($req) {
        $type = $req['request_type_label'];
        $target = $req['target_display_username'] ?: $req['target_username'];
        $status = strtolower($req['status'] ?? '');
        $requester = $req['requester_name'] ?? 'User';
        
        $verb = 'processed';
        if ($status === 'completed') $verb = 'successfully approved';
        if ($status === 'denied') $verb = 'officially rejected';
        if ($status === 'failed') $verb = 'processed with an error';

        $msg = "<strong>$requester</strong>, your <strong>$type</strong> has been $verb for user id <code>$target</code>.";

        return [
            'message' => $msg,
            'status_raw' => $status,
            'time' => $req['processed_at'] ?: $req['timestamp']
        ];
    }, $recent);
}
