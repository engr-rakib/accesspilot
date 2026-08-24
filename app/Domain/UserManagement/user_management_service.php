<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../Infrastructure/Persistence/repositories.php';

function readUsers() {
    $externalUsers = repo_read_users();
    
    // If vault is empty, fall back to internal_admin.json (fail-safe).
    // This allows login on a fresh deploy where setup_complete.lock
    // exists (from file transfer) but the vault has not been initialized yet.
    // Once bootstrap runs and creates the vault, this path is never reached.
    if (empty($externalUsers)) {
        if ((bool) config_get('fail_safe.enabled', false)) {
            $internalPath = (string) config_get('fail_safe.path', '');
            if (file_exists($internalPath)) {
                $internalAdmin = json_decode(file_get_contents($internalPath), true);
                if (is_array($internalAdmin)) {
                    return $internalAdmin;
                }
            }
        }
    }

    return $externalUsers;
}

function writeUsers($users) {
    return repo_write_users($users);
}

function getPendingRegistrationRequestCount() {
    $requests = repo_read_registration_requests();

    $pending_count = 0;
    foreach ($requests as $request) {
        if (isset($request['status']) && $request['status'] === 'pending') {
            $pending_count++;
        }
    }

    return $pending_count;
}
