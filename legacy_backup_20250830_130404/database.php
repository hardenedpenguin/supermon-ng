<?php
/**
 * Supermon-ng Database Viewer
 * 
 * Provides a web-based interface for viewing AllStar node database contents.
 * Connects to Asterisk Manager Interface (AMI) to retrieve and display
 * database key-value pairs from specified nodes.
 * 
 * Features:
 * - User authentication and authorization (DBTUSER permission required)
 * - AMI connection and authentication
 * - Database content retrieval and parsing
 * - Structured key-value pair display
 * - Error handling and status reporting
 * 
 * Security: Requires DBTUSER permission
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("includes/amifunctions.inc");
include("user_files/global.inc");
include("includes/common.inc");
include("authusers.php");
include("authini.php");
include("includes/database/database-controller.inc");

// Run the database system
runDatabase();