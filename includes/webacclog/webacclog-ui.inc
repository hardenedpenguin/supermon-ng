<?php
/**
 * Supermon-ng Web Access Log UI
 * 
 * Provides the user interface components for the web access log viewer including
 * HTML structure, CSS styling, and content rendering.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Output web access log page HTML head section
 */
function outputWebacclogHead(): void {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Web Server access_log Viewer</title>
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

<h1 class="log-viewer-title">Web Server Access Log</h1>
    <?php
}

/**
 * Output log file info message
 * 
 * @param string $file Log file path
 */
function outputWebacclogFileInfo(string $file): void {
    echo "<div class=\"log-viewer-info\">Viewing Log File: " . htmlspecialchars($file) . "</div>";
}

/**
 * Output sudo command attempt message
 * 
 * @param string $sudoCommand Sudo command being attempted
 */
function outputWebacclogSudoAttempt(string $sudoCommand): void {
    echo "<p><i>Attempting to read via: <code>" . htmlspecialchars($sudoCommand) . "</code></i></p>";
}

/**
 * Output parsed log table
 * 
 * @param array $logLines Array of log lines
 */
function outputWebacclogTable(array $logLines): void {
    $headers = ['Line', 'Log Entry'];
    $rows = [];
    foreach ($logLines as $index => $line) {
        $lineNumber = $index + 1;
        $rows[] = [
            $lineNumber,
            htmlspecialchars($line)
        ];
    }
    $table_class = 'webacclog-table';
    include 'includes/table.inc';
}

/**
 * Output no log entries message
 */
function outputWebacclogNoEntries(): void {
    echo '<div class="log-viewer-error">No log entries found or unable to read log file.</div>';
}

/**
 * Output path not defined error message
 */
function outputWebacclogPathError(): void {
    echo '<div class="log-viewer-error">ERROR: The `WEB_ACCESS_LOG` path is not defined in `global.inc`.</div>';
}

/**
 * Output access denied message
 */
function outputWebacclogAccessDenied(): void {
    echo '<div class="log-viewer-error">ERROR: You must login with sufficient privileges to use this function!</div>';
}

/**
 * Output invalid path error message
 */
function outputWebacclogInvalidPath(): void {
    echo "<h3 class='error-message'>ERROR: Invalid or inaccessible log file path.</h3>";
}

/**
 * Output web access log page closing tags
 */
function outputWebacclogFooter(): void {
    ?>
</body>
</html>
    <?php
}
