<?php
/**
 * Supermon-ng Web Error Log UI
 * 
 * Provides the user interface components for the web error log viewer including
 * HTML structure, CSS styling, and content rendering.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Output web error log page HTML head section
 */
function outputWeberrlogHead(): void {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Server error_log Viewer</title>
    <!-- Modular CSS Files -->
<link type="text/css" rel="stylesheet" href="css/base.css">
<link type="text/css" rel="stylesheet" href="css/layout.css">
<link type="text/css" rel="stylesheet" href="css/menu.css">
<link type="text/css" rel="stylesheet" href="css/tables.css">
<link type="text/css" rel="stylesheet" href="css/forms.css">
<link type="text/css" rel="stylesheet" href="css/widgets.css">
<link type="text/css" rel="stylesheet" href="css/responsive.css">
<!-- Custom CSS (load last to override defaults) -->
<?php if (file_exists('css/custom.css')): ?>
<link type="text/css" rel="stylesheet" href="css/custom.css">
<?php endif; ?>
</head>
<body>

    <h2 class="log-viewer-title">Web Server Error Log</h2>
    <?php
}

/**
 * Output log file info message
 * 
 * @param string $logFilePath Path to the log file
 */
function outputWeberrlogFileInfo(string $logFilePath): void {
    echo "<div class='log-viewer-info'>Viewing Log File: " . htmlspecialchars($logFilePath) . "</div>";
}

/**
 * Output parsed log table
 * 
 * @param array $parsedData Array containing 'headers' and 'rows'
 */
function outputWeberrlogTable(array $parsedData): void {
    $headers = $parsedData['headers'];
    $rows = $parsedData['rows'];
    $table_class = 'weberrlog-table';
    include 'includes/table.inc';
}

/**
 * Output empty log file message
 */
function outputWeberrlogEmpty(): void {
    echo "<p>Log file exists but is currently empty.</p>";
}

/**
 * Output log read error message
 */
function outputWeberrlogReadError(): void {
    echo "<p class='log-viewer-error'>ERROR: Could not read the log file content.</p>";
}

/**
 * Output file validation error message
 * 
 * @param string $error Error message
 */
function outputWeberrlogFileError(string $error): void {
    echo "<p class='log-viewer-error'>ERROR: " . $error . "</p>";
}

/**
 * Output path not defined error message
 */
function outputWeberrlogPathError(): void {
    echo "<p class='log-viewer-error'>ERROR: Log file path (WEB_ERROR_LOG) not defined.</p>";
}

/**
 * Output access denied message
 */
function outputWeberrlogAccessDenied(): void {
    echo "<p class='log-viewer-error'>ERROR: Not authorized.</p>";
}

/**
 * Output web error log page closing tags
 */
function outputWeberrlogFooter(): void {
    ?>
</body>
</html>
    <?php
}
