<?php
/**
 * Pi GPIO Form Processor
 * 
 * Handles POST form processing for GPIO pin control operations.
 */

/**
 * Process GPIO control form submission
 */
function processGPIOForm() {
    // Handle GPIO operations
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $Bit = $_POST['Bit'] ?? '';
        $State = $_POST['State'] ?? '';
        
        // Validate inputs
        if (!validate_gpio_pin($Bit)) {
            die("Invalid GPIO pin number.");
        }
        
        if (!validate_gpio_state($State)) {
            die("Invalid GPIO state.");
        }
        
        $escaped_bit = escapeshellarg($Bit);
        $escaped_state = escapeshellarg($State);
        
        switch ($State) {
            case 'input':
                safe_exec("gpio", "mode {$escaped_bit} input");
                break;
            case 'up':
                safe_exec("gpio", "mode {$escaped_bit} up");
                break;
            case 'down':
                safe_exec("gpio", "mode {$escaped_bit} down");
                break;
            case 'output':
                safe_exec("gpio", "mode {$escaped_bit} output");
                break;
            case '0':
            case '1':
                safe_exec("gpio", "write {$escaped_bit} {$escaped_state}");
                break;
        }
    }
}
