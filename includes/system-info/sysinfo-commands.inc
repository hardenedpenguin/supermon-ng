<?php
/**
 * System Info Safe Command Execution
 * 
 * Provides secure command path validation and execution utilities
 * for system information collection operations.
 */

/**
 * Define safe command paths with validation
 * 
 * @param string $command_name The command name to find
 * @param string $default_path The default path to try first
 * @return string Escaped shell path to the command
 */
function get_safe_command_path($command_name, $default_path) {
    $path = $default_path;
    
    // Validate the path exists and is executable
    if (!file_exists($path) || !is_executable($path)) {
        // Try common alternatives
        $alternatives = [
            "/usr/bin/{$command_name}",
            "/bin/{$command_name}",
            "/usr/local/bin/{$command_name}"
        ];
        
        foreach ($alternatives as $alt_path) {
            if (file_exists($alt_path) && is_executable($alt_path)) {
                $path = $alt_path;
                break;
            }
        }
    }
    
    return escapeshellarg($path);
}

/**
 * Safe command execution function
 * 
 * @param string $command The command to execute
 * @param string $args Optional arguments to pass to the command
 * @return string Command output or 'N/A' on failure
 */
function safe_exec($command, $args = '') {
    $escaped_command = escapeshellcmd($command);
    if (!empty($args)) {
        $escaped_args = escapeshellarg($args);
        $full_command = "{$escaped_command} {$escaped_args}";
    } else {
        $full_command = $escaped_command;
    }
    
    $output = [];
    $return_var = 0;
    exec($full_command . " 2>/dev/null", $output, $return_var);
    
    if ($return_var !== 0) {
        return 'N/A';
    }
    
    return implode("\n", $output);
}

/**
 * Initialize safe command paths for system info collection
 * 
 * @return array Array of safe command paths
 */
function initializeSystemCommands() {
    return [
        'HOSTNAME_CMD' => get_safe_command_path('hostname', '/usr/bin/hostname'),
        'AWK_CMD' => get_safe_command_path('awk', '/usr/bin/awk'),
        'DATE_CMD' => get_safe_command_path('date', '/usr/bin/date'),
        'CAT_CMD' => get_safe_command_path('cat', '/usr/bin/cat'),
        'EGREP_CMD' => get_safe_command_path('egrep', '/usr/bin/egrep'),
        'SED_CMD' => get_safe_command_path('sed', '/usr/bin/sed'),
        'GREP_CMD' => get_safe_command_path('grep', '/usr/bin/grep'),
        'HEAD_CMD' => get_safe_command_path('head', '/usr/bin/head'),
        'TAIL_CMD' => get_safe_command_path('tail', '/usr/bin/tail'),
        'CURL_CMD' => get_safe_command_path('curl', '/usr/bin/curl'),
        'CUT_CMD' => get_safe_command_path('cut', '/usr/bin/cut'),
        'IFCONFIG_CMD' => get_safe_command_path('ip', '/usr/bin/ip'),
        'UPTIME_CMD' => get_safe_command_path('uptime', '/usr/bin/uptime')
    ];
}
