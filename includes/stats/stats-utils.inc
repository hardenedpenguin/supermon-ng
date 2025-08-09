<?php
/**
 * AllStar Statistics Utility Functions
 * 
 * Helper functions for data processing and formatting
 */

/**
 * Clean AMI output by removing 'Output: ' prefix from lines
 */
function clean_ami_output_for_display($raw_output) {
    if ($raw_output === null || trim($raw_output) === '') {
        return $raw_output;
    }
    $lines = explode("\n", $raw_output);
    $cleaned_lines = [];
    $prefix = "Output: ";
    foreach ($lines as $line) {
        if (strpos($line, $prefix) === 0) {
            $cleaned_lines[] = substr($line, strlen($prefix));
        } else {
            $cleaned_lines[] = $line;
        }
    }
    return implode("\n", $cleaned_lines);
}

/**
 * Display the page header with hostname and date
 */
function page_header()
{
    global $HOSTNAME, $AWK, $DATE;

    $HOSTNAME_CMD = isset($HOSTNAME) ? $HOSTNAME : 'hostname';
    $AWK_CMD = isset($AWK) ? $AWK : 'awk';
    $DATE_CMD = isset($DATE) ? $DATE : 'date';

    echo "<span class='ast-status-header-line'>#################################################################</span>\n";
    $host = trim(`$HOSTNAME_CMD | $AWK_CMD -F. '{printf ("%s", $1);}' 2>/dev/null`);
    $date = trim(`$DATE_CMD 2>/dev/null`);
    echo " <span class='ast-status-highlight'>" . htmlspecialchars($host) . "</span> AllStar Status: <span class='ast-status-highlight'>" . htmlspecialchars($date) . "</span>\n";
    echo "<span class='ast-status-header-line'>#################################################################</span>\n";
    echo "\n";
}
