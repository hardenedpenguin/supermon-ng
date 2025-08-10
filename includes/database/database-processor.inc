<?php
/**
 * Supermon-ng Database Processor
 * 
 * Handles the processing and parsing of database content retrieved from AMI.
 * Converts raw AMI output into structured key-value pairs for display.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Process raw database output from AMI
 * 
 * @param string|false $databaseOutput Raw database output from AMI
 * @return array Processed database entries
 */
function processDatabaseOutput($databaseOutput): array {
    $processedOutput = ($databaseOutput === false) ? "" : trim($databaseOutput);
    $dbEntries = [];

    if (!empty($processedOutput)) {
        $processedOutput = preg_replace('/^Output: /m', '', $processedOutput);
        $lines = explode("\n", trim($processedOutput));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $dbEntries[] = [
                    'key' => trim($parts[0]),
                    'value' => trim($parts[1])
                ];
            }
        }
    }

    return $dbEntries;
}

/**
 * Get database status message
 * 
 * @param string|false $databaseOutput Raw database output from AMI
 * @param array $dbEntries Processed database entries
 * @param string $processedOutput Cleaned output string
 * @return string Status message for display
 */
function getDatabaseStatusMessage($databaseOutput, array $dbEntries, string $processedOutput): string {
    if ($databaseOutput === false) {
        return "ERROR: Could not retrieve database content from AMI.";
    } elseif (empty($dbEntries)) {
        if (empty(trim($processedOutput))) {
            return "--- NO DATABASE CONTENT RETURNED (or output was empty after cleaning) ---";
        } else {
            return "--- NO KEY-VALUE PAIRS FOUND IN DATABASE OUTPUT ---";
        }
    }
    
    return ""; // No error message
}

/**
 * Prepare database table data for display
 * 
 * @param array $dbEntries Processed database entries
 * @return array Table data with headers and rows
 */
function prepareDatabaseTableData(array $dbEntries): array {
    $headers = ['Key', 'Value'];
    $rows = array_map(function($entry) {
        return [ $entry['key'], $entry['value'] ];
    }, $dbEntries);
    
    return [
        'headers' => $headers,
        'rows' => $rows,
        'table_class' => 'db-table'
    ];
}
