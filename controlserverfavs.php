<?php
/**
 * Supermon-ng Control Server Favorites
 * 
 * Provides server control functionality through AMI (Asterisk Manager Interface) for favorites.
 * Allows authenticated users to execute commands on remote nodes through
 * secure AMI connections with enhanced error handling and proper resource cleanup.
 * 
 * Features:
 * - User authentication and session validation
 * - Enhanced parameter validation with sanitization
 * - INI file configuration loading and validation
 * - AMI connection management and authentication
 * - Command execution with node substitution
 * - Comprehensive error handling with try-catch blocks
 * - Proper resource cleanup in finally blocks
 * - Enhanced output formatting with HTML escaping
 * 
 * Security: Requires valid session and proper node configuration
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

declare(strict_types=1);

include('includes/session.inc');
include('includes/amifunctions.inc');
include('includes/common.inc');
include('authini.php');
include("includes/controlserverfavs/controlserverfavs-controller.inc");

// Run the control server favorites system
runControlserverfavs();