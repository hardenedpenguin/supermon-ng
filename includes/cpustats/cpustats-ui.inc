<?php
/**
 * Supermon-ng CPU Statistics UI
 * 
 * Provides the user interface components for the CPU statistics viewer including
 * HTML structure, CSS styling, and content rendering.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Output CPU statistics page HTML head section
 */
function outputCpustatsHead(): void {
    ?>
<html>
<head>
<title>CPU and System Status</title>
<!-- Modular CSS Files -->
<link rel="stylesheet" type="text/css" href="css/base.css">
<link rel="stylesheet" type="text/css" href="css/layout.css">
<link rel="stylesheet" type="text/css" href="css/menu.css">
<link rel="stylesheet" type="text/css" href="css/tables.css">
<link rel="stylesheet" type="text/css" href="css/forms.css">
<link rel="stylesheet" type="text/css" href="css/widgets.css">
<link rel="stylesheet" type="text/css" href="css/responsive.css">
<!-- Custom CSS (load last to override defaults) -->
<?php if (file_exists('css/custom.css')): ?>
<link rel="stylesheet" type="text/css" href="css/custom.css">
<?php endif; ?>
</head>
<body class="cpustats">
    <?php
}

/**
 * Output CPU statistics content
 * 
 * @param string $content Formatted statistics content
 */
function outputCpustatsContent(string $content): void {
    echo "<pre>";
    echo $content;
    echo "</pre>";
}

/**
 * Output access denied message
 */
function outputCpustatsAccessDenied(): void {
    echo "<br><h3>ERROR: You Must login to use this function!</h3>";
}

/**
 * Output CPU statistics page closing tags
 */
function outputCpustatsFooter(): void {
    ?>
</body>
</html>
    <?php
}
