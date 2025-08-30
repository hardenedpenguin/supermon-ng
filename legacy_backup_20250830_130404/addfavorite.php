<?php
/**
 * Supermon-ng Add Favorite
 * 
 * Provides functionality to add nodes to user favorites by looking up
 * node information from astdb.txt and adding it to the user's favorites file.
 * 
 * Features:
 * - User authentication and authorization (FAVUSER permission required)
 * - Node lookup from astdb.txt
 * - Dynamic favorites file management
 * - Form-based node addition
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
include("includes/addfavorite/addfavorite-controller.inc");

// Run the add favorite system
runAddFavorite();
?>
