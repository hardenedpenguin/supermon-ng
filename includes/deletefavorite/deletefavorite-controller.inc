<?php
/**
 * Supermon-ng Delete Favorite Controller
 * 
 * Main controller for the delete favorite functionality that orchestrates authentication,
 * validation, favorites display, and deletion operations.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

include_once "includes/deletefavorite/deletefavorite-auth.inc";
include_once "includes/deletefavorite/deletefavorite-ui.inc";

/**
 * Initialize and run the delete favorite system
 * 
 * This is the main entry point for the delete favorite functionality.
 * Handles authentication, validation, favorites display, and deletion processing.
 * 
 * @return void
 */
function runDeleteFavorite(): void {
    // Validate user access
    if (!validateDeleteFavoriteAccess()) {
        die("<br><h3>ERROR: You Must login to use the 'Delete Favorite' function!</h3>");
    }

    // Get and validate node parameter
    $node = getValidatedNode();
    if ($node === false) {
        die ("Please provide a properly formatted URI. (ie deletefavorite.php?node=1234)");
    }

    $title = "Delete Favorite - Node $node";
    $user = isset($_SESSION['user']) ? $_SESSION['user'] : 'admin';

    // Load user's favorites
    $favorites = getAllUserFavorites($user);
    
    if (empty($favorites)) {
        $title = "No Favorites Found";
    }

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = processDeleteFavoriteForm($user);
        if ($result['success']) {
            $title = "Favorite Deleted Successfully";
            // Reload favorites after deletion
            $favorites = getAllUserFavorites($user);
        } else {
            $title = "Error Deleting Favorite";
        }
    }

    // Output the complete delete favorite interface
    outputDeleteFavoriteHead($title);
    outputDeleteFavoriteScript();
    ?>
</head>
<body>
    <?php
    outputDeleteFavoriteHeader($title);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($result)) {
        outputDeleteFavoriteResult($result);
    }
    
    if (empty($favorites)) {
        outputDeleteFavoriteEmpty();
    } else {
        outputDeleteFavoriteForm($favorites, $node);
    }
    
    outputDeleteFavoriteFooter();
    ?>
</body>
</html>
    <?php
}

/**
 * Process the delete favorite form submission
 * 
 * @param string $user User identifier
 * @return array Result of the operation
 */
function processDeleteFavoriteForm($user) {
    $section = isset($_POST['section']) ? $_POST['section'] : '';
    $index = isset($_POST['index']) ? $_POST['index'] : '';
    
    if (empty($section) || !is_numeric($index)) {
        return array(
            'success' => false,
            'message' => 'Invalid selection. Please try again.'
        );
    }
    
    $index = (int)$index;
    
    // Get the favorite details before deletion for confirmation message
    $favorites = getAllUserFavorites($user);
    $deletedFavorite = null;
    
    foreach ($favorites as $favorite) {
        if ($favorite['section'] === $section && $favorite['index'] === $index) {
            $deletedFavorite = $favorite;
            break;
        }
    }
    
    if ($deletedFavorite === null) {
        return array(
            'success' => false,
            'message' => 'Favorite not found. It may have been already deleted.'
        );
    }
    
    // Delete the favorite
    $result = deleteFavoriteEntry($user, $section, $index);
    
    if ($result) {
        return array(
            'success' => true,
            'message' => "Successfully deleted favorite: \"" . htmlspecialchars($deletedFavorite['label']) . "\"",
            'deleted_label' => $deletedFavorite['label']
        );
    } else {
        return array(
            'success' => false,
            'message' => 'Error: Could not delete favorite. Please check file permissions.'
        );
    }
}
?>
