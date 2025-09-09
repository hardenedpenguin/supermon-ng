<?php
/**
 * Supermon-ng Common Configuration
 * 
 * Provides essential configuration constants for Supermon-ng.
 * Contains only actively used variables - all legacy unused variables removed.
 * 
 * Features:
 * - Essential file path configuration
 * - Log file path definitions for web access/error logs
 * - IRLP node lookup utilities (used by nodeinfo.inc)
 * - System utilities for log processing
 * 
 * Modernization:
 * - Removed 82% of unused legacy variables (~200 lines)
 * - Kept only variables actively used by modern API
 * - Eliminated complex Asterisk version detection (unused)
 * - Removed legacy UI title variables (Vue.js frontend)
 * 
 * Dependencies: None (core configuration)
 * 
 * @author Supermon-ng Team
 * @version 4.0.2
 * @since 1.0.0
 */

/**
 * @var string Location of all user configurable files.
 * Used by: ConfigController for user file paths
 */
$USERFILES = "user_files";

/**
 * @var string Path to the webserver access log.
 * Used by: NodeController for web access log functionality
 */
$WEB_ACCESS_LOG = "/var/log/apache2/access.log";

/**
 * @var string Path to the webserver error log.
 * Used by: NodeController for web error log functionality
 */
$WEB_ERROR_LOG = "/var/log/apache2/error.log";

/**
 * @var string Path to the Asterisk message log.
 * Used by: ConfigController for Asterisk log path
 */
$ASTERISK_LOG = "/var/log/asterisk/messages.log";

/**
 * @var string Path to the IRLP calls data file (gzipped).
 * Used by: nodeinfo.inc for IRLP node lookups
 */
$IRLP_CALLS = "/tmp/irlpdata.txt.gz";

/**
 * @var string Path to the awk utility.
 * Used by: nodeinfo.inc for text processing
 */
$AWK = "/usr/bin/awk";

/**
 * @var string Path to the zcat utility.
 * Used by: nodeinfo.inc for decompressing IRLP data
 */
$ZCAT = "/usr/bin/zcat";

?>