<?php
/**
 * Supermon-ng Linux Log UI
 * 
 * Provides the user interface components for the Linux log viewer including
 * HTML structure, CSS styling, and content rendering.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Output Linux log page HTML head section
 * 
 * @param string $log_description Description for the log page
 */
function outputLinuxlogHead(string $log_description): void {
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <!-- Set the title shown in the browser tab -->
    <title><?php echo htmlspecialchars($log_description); ?></title>
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

<div class="log-viewer-container">
    <?php
}

/**
 * Output Linux log title
 * 
 * @param string $log_description Description for the log page
 */
function outputLinuxlogTitle(string $log_description): void {
    echo "<div class=\"log-viewer-title\">" . htmlspecialchars($log_description) . "</div>\n";
}

/**
 * Output configuration error message
 */
function outputLinuxlogConfigError(): void {
    echo "<div class=\"log-viewer-error\">Configuration Error: Required command variables (SUDO, JOURNALCTL, SED) are not defined in common.inc.</div>";
}

/**
 * Output access denied message
 */
function outputLinuxlogAccessDenied(): void {
    echo "<div class=\"log-viewer-error\"><h3>ERROR: You must be logged in with appropriate permissions to view this log!</h3></div>";
}

/**
 * Output Linux log page closing tags
 */
function outputLinuxlogFooter(): void {
    ?>
</div>

</body>
</html>
    <?php
}
