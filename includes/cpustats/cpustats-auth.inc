<?php
/**
 * Supermon-ng CPU Statistics Authentication
 * 
 * Handles authentication, authorization, and validation for CPU statistics viewing.
 * Provides user access control and command execution for system statistics.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Validate user access to CPU statistics function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateCpustatsAccess(): bool {
    if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && 
        get_user_auth("CSTATUSER")) {
        return true;
    }
    return false;
}

/**
 * Get the list of commands to run for CPU statistics
 * 
 * @return array Array of commands to execute
 */
function getCpustatsCommands(): array {
    global $USERFILES;
    
    return [
        "/usr/bin/date",
        "export TERM=vt100 && sudo $USERFILES/sbin/ssinfo - ",
        "/usr/bin/ip a",
        "$USERFILES/sbin/din",
        "/usr/bin/df -hT",
        "export TERM=vt100 && sudo /usr/bin/top -b -n1"
    ];
}

/**
 * Execute a command and return its output
 * 
 * @param string $command Command to execute
 * @return string Command output
 */
function executeCpustatsCommand(string $command): string {
    ob_start();
    passthru($command);
    $output = ob_get_clean();
    return $output;
}

/**
 * Format command output for display
 * 
 * @param string $command Command that was executed
 * @param string $output Command output
 * @return string Formatted output
 */
function formatCpustatsOutput(string $command, string $output): string {
    $formatted = "Command: " . htmlspecialchars($command) . "\n";
    $formatted .= "-----------------------------------------------------------------\n";
    $formatted .= htmlspecialchars($output);
    $formatted .= "\n\n";
    return $formatted;
}
