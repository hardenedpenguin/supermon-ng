<?php
/**
 * Pi GPIO Command Execution and Validation
 * 
 * Provides safe command execution and GPIO validation utilities
 * for hardware control operations.
 */

/**
 * Safe command execution function
 * 
 * @param string $command The command to execute
 * @param string $args Optional arguments to pass to the command
 * @return string|false Command output or false on failure
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
        return false;
    }
    
    return implode("\n", $output);
}

/**
 * Validate GPIO pin number
 * 
 * @param mixed $pin The pin number to validate
 * @return bool True if valid GPIO pin (0-40), false otherwise
 */
function validate_gpio_pin($pin) {
    return is_numeric($pin) && $pin >= 0 && $pin <= 40;
}

/**
 * Validate GPIO state
 * 
 * @param string $state The state to validate
 * @return bool True if valid GPIO state, false otherwise
 */
function validate_gpio_state($state) {
    return in_array($state, ['0', '1', 'input', 'output', 'up', 'down']);
}
