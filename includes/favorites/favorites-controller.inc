<?php
/**
 * Supermon-ng Favorites Controller
 * 
 * Main controller for the favorites functionality that orchestrates authentication,
 * validation, configuration loading, and UI rendering.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

include_once "includes/favorites/favorites-auth.inc";
include_once "includes/favorites/favorites-ui.inc";

/**
 * Initialize and run the favorites system
 * 
 * This is the main entry point for the favorites functionality.
 * Handles authentication, validation, configuration loading, and UI rendering.
 * 
 * @return void
 */
function runFavorites(): void {
    // Validate user access
    if (!validateFavoritesAccess()) {
        die("<br><h3>ERROR: You Must login to use the 'Favorites Panel' function!</h3>");
    }

    // Get and validate node parameter
    $node = getValidatedNode();
    if ($node === false) {
        die ("Please provide a properly formated URI. (ie favorites.php?node=1234)");
    }

    $title = "AllStar $node Favorites Panel";

    // Load and validate favorites configuration
    $config = loadFavoritesConfig($_SESSION['user'], $node);
    if ($config === false) {
        die("Couldn't load favorites configuration file.\n");
    }

    // Process favorites commands
    $cpCommands = processFavoritesCommands($config['cpConfig'], $node);

    // Output the complete favorites interface
    outputFavoritesHead($title);
    outputFavoritesScript();
    ?>
</head>
<body>
    <?php
    outputFavoritesHeader($title);
    outputFavoritesMain($node, $cpCommands);
    outputFavoritesFooter($config['FAVINI']);
    ?>
</body>
</html>
    <?php
}
