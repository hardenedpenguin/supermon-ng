<?php
/**
 * RPT Stats UI Components
 * 
 * Handles HTML template rendering for different page states
 * in the AllStar rpt stats functionality.
 */

/**
 * Render authentication error page
 * 
 * @param int $localnode_param Local node parameter
 * @param int $node_param Node parameter
 */
function renderAuthErrorPage($localnode_param, $node_param) {
    $error_node_identifier = "Unknown";
    if ($localnode_param > 0) {
        $error_node_identifier = $localnode_param;
    } elseif ($node_param > 0) {
        $error_node_identifier = $node_param;
    }
    $title = "AllStar 'rpt stats' for node: " . htmlspecialchars($error_node_identifier) . " - Authentication Required";
    ?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $title; ?></title>
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
    <br><h3>ERROR: You Must login to use this function!</h3>
</body>
</html>
    <?php
    exit();
}

/**
 * Render stats page header
 * 
 * @param int $localnode_param Local node parameter
 */
function renderStatsPageHeader($localnode_param) {
    $title = "AllStar 'rpt stats' for node: " . htmlspecialchars($localnode_param);
    ?>
<html>
<head>
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
<title><?php echo $title; ?></title>
</head>
<body>
<pre class="rptstatus">
    <?php
}

/**
 * Render stats page footer
 */
function renderStatsPageFooter() {
    ?>
</pre>
</body>
</html>
    <?php
}

/**
 * Render error page for missing parameters
 */
function renderParameterErrorPage() {
    $title = "AllStar 'rpt stats' - Error";
    ?>
<html>
<head>
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
<title><?php echo htmlspecialchars($title); ?></title>
</head>
<body>
<pre>
Error: No valid node specified. Please provide a 'node' or ensure 'localnode' is correctly configured.
</pre>
</body>
</html>
    <?php
}
