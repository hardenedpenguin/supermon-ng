<?php
/**
 * Pi GPIO Status Display
 * 
 * Handles GPIO status reading and table display functionality.
 */

/**
 * Display GPIO status table
 */
function displayGPIOStatus() {
    // Safe GPIO status check
    $command = "gpio readall";
    $data = safe_exec($command);
    
    if ($data !== false) {
        // Prepare data for the table
        $lines = explode("\n", $data);
        $headers = ['Pin', 'Mode', 'Value'];
        $rows = [];
        foreach ($lines as $line) {
            if (preg_match('/^(\d+)\s+(\w+)\s+(\w+)$/', $line, $matches)) {
                $rows[] = [
                    'pin' => $matches[1],
                    'mode' => $matches[2],
                    'value' => $matches[3]
                ];
            }
        }
        
        // Set table class
        $table_class = 'gpio-table';
        
        // Include the table partial
        include 'includes/table.inc';
        
    } else {
        print "<p class='error-message'>Error: Could not read GPIO status. Make sure gpio command is available.</p>";
    }
}
