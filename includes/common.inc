<?php
/**
 * Supermon-ng Common Configuration
 * 
 * Provides centralized configuration and constants for Supermon-ng.
 * Contains file paths, system settings, version information, and
 * global variables used throughout the application.
 * 
 * Features:
 * - Centralized file path configuration
 * - System version and date information
 * - Log file path definitions
 * - Database and configuration file locations
 * - Essential system utilities
 * - User interface text and titles
 * 
 * Configuration:
 * - User files directory: user_files/
 * - Log files: Various system and application logs
 * - Database files: Asterisk, IRLP, Private nodes data
 * - Version information: Application version and date
 * - UI text: Titles and display strings
 * 
 * File Paths:
 * - Log files: Apache, Asterisk, IRLP logs
 * - Database files: ASTDB, Private nodes, External nodes
 * - Configuration: User files and system configs
 * - System utilities: Essential command paths
 * 
 * Dependencies: None (core configuration)
 * 
 * @author Supermon-ng Team
 * @version 4.0.3
 * @since 1.0.0
 */

/**
 * @var string Location of all user configurable files.
 */
$USERFILES="user_files";

/**
 * @var string Title in header when logged in.
 */
$TITLE_LOGGED = "Supermon-ng V4.0.3 AllStar Manager";

/**
 * @var string Version date of the Supermon-ng software.
 */
$VERSION_DATE = "September 10, 2025";

/**
 * @var string Title in header when not logged in.
 */
$TITLE_NOT_LOGGED = "Supermon-ng V4.0.3 AllStar Monitor";

/**
 * @var string Path to the webserver access log.
 */
$WEB_ACCESS_LOG = "/var/log/apache2/access.log";

/**
 * @var string Path to the webserver error log.
 */
$WEB_ERROR_LOG = "/var/log/apache2/error.log";

/**
 * @var string Path to the Asterisk message log.
 */
$ASTERISK_LOG = "/var/log/asterisk/messages.log";

/**
 * @var string Path to the AllStar allmon database text file.
 */
$ASTDB_TXT = "/var/www/html/supermon-ng/astdb.txt";

/**
 * @var string Path to the AllStar rpt_extnodes file (not present in DNS mode).
 */
$EXTNODES = "/tmp/rpt_extnodes";

/**
 * @var string Path to the Private AllStar nodelist.
 */
$PRIVATENODES = "/var/www/html/supermon-ng/user_files/privatenodes.txt";

/**
 * @var string Path to the IRLP calls data file (gzipped).
 */
$IRLP_CALLS = "/tmp/irlpdata.txt.gz";

/**
 * @var string Path to the IRLP log file.
 */
$IRLP_LOG = "/home/irlp/log/messages";

/**
 * @var string Path to the awk utility.
 */
$AWK =		"/usr/bin/awk";

/**
 * @var string Path to the journalctl utility.
 */
$JOURNALCTL =	"/usr/bin/journalctl";

/**
 * @var string Path to the sed utility.
 */
$SED =		"/usr/bin/sed";

/**
 * @var string Command to execute sudo with a specific TERM environment variable.
 */
$SUDO =		"export TERM=vt100 && /usr/bin/sudo";

/**
 * @var string Path to the zcat utility.
 */
$ZCAT =		"/usr/bin/zcat";

/**
 * @var bool True if the IRLP log file exists, false otherwise.
 */
if (file_exists($IRLP_LOG))
	$IRLPLOG = true;
else
	$IRLPLOG = false;

/**
 * @var bool True if the IRLP calls data file exists, false otherwise.
 */
if (file_exists($IRLP_CALLS))
	$IRLP = true;
else
	$IRLP = false;

/**
 * @var bool True if the AllStar rpt_extnodes file exists, false otherwise.
 */
if (file_exists($EXTNODES))
	$EXTN = true;
else
	$EXTN = false;

?>
