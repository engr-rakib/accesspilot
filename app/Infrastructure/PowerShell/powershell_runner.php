<?php
/**
 * app/Infrastructure/PowerShell/powershell_runner.php
 * 
 * Logic for executing PowerShell scripts with secure parameter passing.
 */

require_once __DIR__ . '/../../Application/Support/helpers.php';

function powershell_binary() {
    $configured = config_get('powershell.binary', '');
    if ($configured !== '') {
        return (string) $configured;
    }
    if (PHP_OS_FAMILY === 'Windows') {
        return 'powershell.exe';
    }
    $candidates = ['pwsh', 'pwsh.exe', 'powershell'];
    foreach ($candidates as $bin) {
        $which = trim(shell_exec("which {$bin} 2>/dev/null"));
        if ($which !== '') {
            return $which;
        }
    }
    return 'pwsh';
}

function powershell_is_linux(): bool
{
    return PHP_OS_FAMILY !== 'Windows';
}

function powershell_default_flags() {
    $flags = config_get('powershell.default_flags', ['-NoProfile', '-ExecutionPolicy Bypass']);
    return is_array($flags) ? array_values($flags) : ['-NoProfile', '-ExecutionPolicy Bypass'];
}

function powershell_script_path($scriptKey, $fallback = null) {
    return config_get("script_paths.{$scriptKey}", $fallback);
}

function powershell_secure_config_path() {
    return resolved_secure_config_path();
}

function powershell_escape($value) {
    return escapeshellarg((string) $value);
}

function powershell_build_command($scriptKey, array $parameters = [], array $options = []) {
    $scriptPath = $options['script_path'] ?? powershell_script_path($scriptKey);

    if (empty($scriptPath)) {
        throw new InvalidArgumentException("Unknown PowerShell script key: {$scriptKey}");
    }

    $segments = [powershell_binary()];

    foreach (powershell_default_flags() as $flag) {
        $segments[] = $flag;
    }

    if (!empty($options['non_interactive'])) {
        $segments[] = '-NonInteractive';
    }

    $segments[] = '-File';
    $segments[] = powershell_escape($scriptPath);

    // Auto-inject the Secure XML configuration if required
    if (!empty($options['include_secure_config']) && !array_key_exists('SecureConfigPath', $parameters)) {
        $parameters = ['SecureConfigPath' => powershell_secure_config_path()] + $parameters;
    }

    // Inject SharedConfigPath only when explicitly requested via options
    // Read from vault — codebase shared_config.json is never authoritative.
    if (!empty($options['include_shared_config']) && !array_key_exists('SharedConfigPath', $parameters)) {
        $sharedPath = function_exists('vault_shared_config_path') ? vault_shared_config_path() : app_root('config/shared_config.json');
        $parameters['SharedConfigPath'] = $sharedPath;
    }

    foreach ($parameters as $name => $value) {
        if ($value === null || $value === false) {
            continue;
        }

        $segments[] = '-' . $name;

        if ($value === true) {
            continue;
        }

        if (is_array($value)) {
            $value = implode(',', $value);
        }

        $segments[] = powershell_escape($value);
    }

    return implode(' ', $segments);
}

/**
 * Executes a PowerShell script by key and returns processed results.
 * This is the primary high-level entry point for AD actions.
 */
function powershell_run_script($scriptKey, array $parameters = [], array $options = []) {
    try {
        $command = powershell_build_command($scriptKey, $parameters, $options);

        // Log the command (hide password if present)
        $logCommand = preg_replace(
            '/-(Password|AdminPassword|AdminPass|DefaultPassword|admin_password)\s+\'[^\']+\'/i',
            '-$1 \'********\'',
            $command
        );
        $logCommand = preg_replace(
            '/-(Password|AdminPassword|AdminPass|DefaultPassword|admin_password)\s+\S+/i',
            '-$1 ********',
            $logCommand
        );
        error_log("PowerShell Executing: " . $logCommand);

        $result = powershell_exec_command($command, $options);

        error_log("PowerShell Result: Success=" . ($result['success'] ? 'YES' : 'NO') . ", ExitCode=" . $result['exit_code']);

        // Ensure output is a single string for consumers
        $rawOutput = is_array($result['output']) ? implode("\n", $result['output']) : (string)$result['output'];

        // --- ENCODING PROTECTION ---
        // PowerShell often outputs in local code pages (like Windows-1252).
        // We MUST ensure valid UTF-8 for json_encode to work in the controllers.
        $utf8Output = mb_convert_encoding($rawOutput, 'UTF-8', 'auto');

        $result['output'] = $utf8Output;
        return $result;
    } catch (Throwable $e) {
        return [
            'success' => false,
            'output' => 'PowerShell Runner Fatal Error: ' . $e->getMessage(),
            'exit_code' => 1
        ];
    }
}

/**
 * Executes a PowerShell script and parses its output as JSON.
 */
function powershell_run_json_script($scriptKey, array $parameters = [], array $options = []) {
    $result = powershell_run_script($scriptKey, $parameters, $options);
    
    $cleanOutput = trim($result['output']);
    
    // Attempt to decode JSON
    $decoded = json_decode($cleanOutput, true);
    $isValid = (json_last_error() === JSON_ERROR_NONE);
    
    return [
        'success' => $result['success'] && $isValid,
        'output' => $cleanOutput,
        'decoded' => $decoded,
        'json_valid' => $isValid,
        'exit_code' => $result['exit_code']
    ];
}

/**
 * Executes a PowerShell script inline via temp .ps1 file (no script_path config needed).
 * Used by ExchangePsRunner which generates scripts dynamically.
 */
function powershell_run_inline(string $script, array $options = []): array
{
    $binary = powershell_binary();
    $flags = powershell_default_flags();

    // Write script to temp file to avoid quoting issues with -Command
    $tmpFile = tempnam(sys_get_temp_dir(), 'ps_inline_') . '.ps1';
    file_put_contents($tmpFile, $script);

    $segments = [$binary];
    foreach ($flags as $flag) {
        $segments[] = $flag;
    }
    if (!empty($options['non_interactive'])) {
        $segments[] = '-NonInteractive';
    }
    $segments[] = '-File';
    $segments[] = escapeshellarg($tmpFile);

    $command = implode(' ', $segments);

    error_log("PowerShell Inline Executing: " . preg_replace('/-[Pp]assword\s+\'[^\']+\'/i', '-Password \'***\'', $command));

    $result = powershell_exec_command($command, $options);

    // Clean up temp file
    @unlink($tmpFile);

    // Ensure UTF-8 output
    $rawOutput = is_array($result['output']) ? implode("\n", $result['output']) : (string)$result['output'];
    $result['output'] = mb_convert_encoding($rawOutput, 'UTF-8', 'auto');

    return $result;
}

function powershell_exec_command($command, array $options = []) {
    $timeout = (int)($options['timeout'] ?? 60);
    if ($timeout <= 0) {
        // Never let a pwsh task run uncapped — unbounded processes hang FPM workers.
        $timeout = 60;
    }
    $command = 'timeout ' . $timeout . ' ' . $command;

    $output = [];
    $return_var = 0;
    exec($command, $output, $return_var);

    if ($timeout > 0 && $return_var === 124) {
        return [
            'success' => false,
            'output' => implode("\n", $output),
            'exit_code' => 124,
            'timeout' => true,
            'message' => "Command exceeded {$timeout}s timeout",
        ];
    }

    return [
        'success' => $return_var === 0,
        'output' => $output,
        'exit_code' => $return_var,
    ];
}
