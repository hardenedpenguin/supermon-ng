<?php
/**
 * Supermon-ng Control Panel Controller
 * 
 * Main controller for the control panel that orchestrates authentication,
 * validation, configuration loading, and UI rendering.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/controlpanel/controlpanel-auth.inc";
include_once "includes/controlpanel/controlpanel-ui.inc";

/**
 * Initialize and run the control panel system
 * 
 * This is the main entry point for the control panel functionality.
 * Handles authentication, validation, and UI rendering.
 * 
 * @return void
 */
function runControlPanel(): void {
    // Validate user access
    if (!validateControlPanelAccess()) {
        die("<br><h3>ERROR: You Must login to use the 'Control Panel' function!</h3>");
    }

    // Get and validate node parameter
    $node = getValidatedNode();
    if ($node === false) {
        die("Please provide a properly formated URI. (ie controlpanel.php?node=1234)");
    }

    $title = "AllStar $node Control Panel";

    // Load user configuration
    $config = loadUserConfig($_SESSION['user']);
    if ($config === false) {
        die("Couldn't load configuration files.\n");
    }

    // Process control panel commands
    $cpCommands = processControlPanelCommands($config['controlpanel'], $node);

    // Output the complete control panel interface
    outputControlPanelHead($title);
    outputControlPanelScript(true);
    ?>
</head>
<body>
    <?php
    outputControlPanelHeader($title);
    outputControlPanelMain($node, $cpCommands);
    ?>
</body>
</html>
    <?php
}
