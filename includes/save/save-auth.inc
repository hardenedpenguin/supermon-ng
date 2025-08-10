<?php
/**
 * Supermon-ng Save Authentication
 * 
 * Handles authentication, authorization, and validation for file saving operations.
 * Provides user access control, file path validation, and security checks.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Validate user access to save function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateSaveAccess(): bool {
    if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("CFGEDUSER"))) {
        return false;
    }
    return true;
}

/**
 * Get and validate save parameters
 * 
 * @return array|false Validated parameters or false if invalid
 */
function getValidatedSaveParams(): array|false {
    $target_filepath = $_POST['filename'] ?? null;
    $new_content = $_POST['edit'] ?? '';

    if (!$target_filepath || strpos($target_filepath, '..') !== false) {
        return false;
    }

    return [
        'filepath' => $target_filepath,
        'content' => $new_content
    ];
}

/**
 * Execute file save operation using helper script
 * 
 * @param string $target_filepath File path to save
 * @param string $new_content New file content
 * @return array Save operation results
 */
function executeFileSave(string $target_filepath, string $new_content): array {
    $helper_script = "/usr/local/sbin/supermon_unified_file_editor.sh";
    $cmd = "sudo " . escapeshellcmd($helper_script) . " " . escapeshellarg($target_filepath);

    $descriptorspec = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w"),
        2 => array("pipe", "w")
    );

    $process = proc_open($cmd, $descriptorspec, $pipes);
    $stdout_data = '';
    $stderr_data = '';
    $return_var = -1;

    if (is_resource($process)) {
        fwrite($pipes[0], $new_content);
        fclose($pipes[0]);

        $stdout_data = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr_data = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $return_var = proc_close($process);
    } else {
        $return_var = -1;
        $stderr_data = "Error: Could not execute the helper command. Check web server logs (e.g., Apache error log) for sudo or permission issues.";
    }

    return [
        'return_var' => $return_var,
        'stdout_data' => $stdout_data,
        'stderr_data' => $stderr_data
    ];
}
