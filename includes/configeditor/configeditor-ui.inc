<?php
/**
 * Supermon-ng Configuration Editor UI
 * 
 * Provides the user interface components for the configuration editor including
 * HTML structure, CSS styling, and content rendering.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Output configuration editor page HTML head section
 */
function outputConfigeditorHead(): void {
    ?>
<html>
<head>
<!-- Modular CSS Files -->
<link type='text/css' rel='stylesheet' href='css/base.css'>
<link type='text/css' rel='stylesheet' href='css/layout.css'>
<link type='text/css' rel='stylesheet' href='css/menu.css'>
<link type='text/css' rel='stylesheet' href='css/tables.css'>
<link type='text/css' rel='stylesheet' href='css/forms.css'>
<link type='text/css' rel='stylesheet' href='css/widgets.css'>
<link type='text/css' rel='stylesheet' href='css/responsive.css'>
<!-- Custom CSS (load last to override defaults) -->
<?php if (file_exists('css/custom.css')): ?>
<link type='text/css' rel='stylesheet' href='css/custom.css'>
<?php endif; ?>
</head>
<body class="configeditor-page">
    <?php
}

/**
 * Output configuration editor header and refresh form
 * 
 * @param string $call Call sign for the header
 */
function outputConfigeditorHeader(string $call): void {
    print "<p class=\"configeditor-text\">";
    print "<form name=REFRESH method=POST action='configeditor.php'>";
    print "<h2> <i>$call</i> - AllStar Link / IRLP / EchoLink - Configuration File Editor </h2>";
    print "<p><b>Please use caution when editing files, misconfiguration can cause problems!</b></p><br>";
    print "<input name=refresh tabindex=50 class=submit-large TYPE=SUBMIT Value=Refresh> ";
    print "   <input type=\"button\" class=\"submit-large\" Value=\"Close Window\" onclick=\"self.close()\"></form>";
}

/**
 * Output file selection form
 * 
 * @param array $files_to_list Array of file information
 */
function outputConfigeditorFileForm(array $files_to_list): void {
    print "<form action=edit.php method=post name=select>\n";
    print "<select name=file class=submit-large>\n";

    foreach ($files_to_list as $file_info) {
        $path = $file_info[0];
        $label = $file_info[1];

        if ($path === "###SEPARATOR###") {
            print "<option value=\"\" disabled>" . htmlspecialchars($label) . "</option>\n";
            continue;
        }

        if (shouldDisplayFile($file_info)) {
            print "<option value=\"" . htmlspecialchars($path) . "\">" . htmlspecialchars($label) . "</option>\n";
        }
    }

    print "</select>   <input name=Submit type=submit class=submit-large value=\" Edit File \"></form>\n";
}

/**
 * Output access denied message
 */
function outputConfigeditorAccessDenied(): void {
    print "<br><h3>ERROR: You Must login to use the 'Configuration Editor' tool!</h3>";
}

/**
 * Output configuration editor page closing tags
 */
function outputConfigeditorFooter(): void {
    print "</p>\n</body>\n</html>";
}
