<?php
/**
 * System Info UI Components
 * 
 * Handles HTML template rendering and page structure
 * for the system information display.
 */

/**
 * Render the HTML head section
 */
function renderSystemInfoHead() {
    ?>
<html>
<head>
    <meta charset="UTF-8"> 
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
    <script>
        function refreshParent() {
            if (window.opener && !window.opener.closed) {
                 try {
                     window.opener.location.reload();
                                 } catch (e) {
                    // Error reloading opener window silently ignored
                }
            }
        }
    </script>
    <title>System Info</title> 
</head>
    <?php
}

/**
 * Render the page body start and title
 * 
 * @param int $Show_Detail Detail level for styling
 */
function renderSystemInfoBodyStart($Show_Detail) {
    $info_container_class = ($Show_Detail == 1) ? 'info-container-detailed' : 'info-container-summary';
    ?>
<body> 
    <p class="page-title">System Info</p>
    <br> 
    <div class="<?php echo htmlspecialchars($info_container_class); ?>">
    <?php
}

/**
 * Render the page footer with close button
 */
function renderSystemInfoFooter() {
    ?>
    </div> 
    <center> 
        <input type="button" class="submit2" Value="Close Window" onclick="self.close();"> 
    </center>
    <br> 
</body>
</html>
    <?php
}
