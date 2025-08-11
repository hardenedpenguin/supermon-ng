<?php
/**
 * Supermon-ng Delete Favorite
 * 
 * Provides functionality to delete nodes from user favorites.
 * Shows current favorites and allows selective deletion.
 * 
 * Features:
 * - User authentication and authorization (FAVUSER permission required)
 * - Display of current favorites
 * - Selective deletion of favorite entries
 * - Form-based deletion confirmation
 * - Error handling and validation
 * 
 * Security: Requires FAVUSER permission
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("authusers.php");
include("includes/common.inc");
include("user_files/global.inc");
include("favini.php");
include("includes/deletefavorite/deletefavorite-controller.inc");

// Run the delete favorite system
runDeleteFavorite();
?>
