<?php
/**
 * @var string Location of all user configurable files.
 */
$USERFILES="user_files";

/**
 * @var string Path/name of Supermon2 login/out log.
 */
$SMLOGNAME = "/tmp/SMLOG.txt";

/**
 * @var string Title in header when logged in.
 */
$TITLE_LOGGED = "Supermon-ng V1.0.6 AllStar Manager";

/**
 * @var string Version date of the Supermon-ng software.
 */
$VERSION_DATE = "June 15, 2025";

/**
 * @var string Title in header when not logged in.
 */
$TITLE_NOT_LOGGED = "Supermon-ng V1.0.6 AllStar Monitor";

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
 * @var string Path to the AllStar Echolink database dump (gzipped).
 */
$ECHO_DB = "/tmp/echolink.db.gz";

/**
 * @var string Path to the Private AllStar nodelist.
 */
$PRIVATENODES = "/var/www/html/supermon-ng/privatenodes.txt";

/**
 * @var string Path to the IRLP calls data file (gzipped).
 */
$IRLP_CALLS = "/tmp/irlpdata.txt.gz";

/**
 * @var string Path to the IRLP log file.
 */
$IRLP_LOG = "/home/irlp/log/messages";

/**
 * @var string Stores the path to the most recently updated Asterisk version file.
 * Determined by checking modification times of potential version files.
 */
$file = `find /tmp/asterisk_version /run/asterisk.pid /etc/allstar_version -printf "%T@  %p\n" 2>/dev/null | sort -nr | awk '{if (NR == 1) printf ("%s", $2)}'`;

/**
 * @var string Stores the Asterisk version string.
 * Reads from /tmp/asterisk_version if it exists, otherwise queries Asterisk directly.
 */
if ($file == "/tmp/asterisk_version")
        $version= `cat /tmp/asterisk_version`;
else
        $version = `export TERM=vt100; sudo asterisk -rx "core show version" |awk '{print $2}' | tee /tmp/asterisk_version`;

/**
 * @var string Prepends "Asterisk " to the obtained version string.
 */
$version="Asterisk $version";

/**
 * @var string Regex pattern to detect HamVOIP release candidate versions (e.g., "X.XrcX").
 */
$pattern="/ [0-9].[0-9]rc[0-9]/";

/**
 * @var string Stores the identified system type. Defaults to "allstarlink".
 * If the version matches the HamVOIP RC pattern, it's set to "allstarlink".
 */
if (preg_match($pattern, $version, $match)) {
        $system_type = "allstarlink";
} else {
        $system_type = "allstarlink";
}

/**
 * @var string Path to the Asterisk executable.
 */
$ASTERISK =	"/usr/sbin/asterisk";

/**
 * @var string Path to the awk utility.
 */
$AWK =		"/usr/bin/awk";

/**
 * @var string Path to the cat utility.
 */
$CAT =		"/usr/bin/cat";

/**
 * @var string Path to the cut utility.
 */
$CUT =		"/usr/bin/cut";

/**
 * @var string Path to the date utility.
 */
$DATE =		"/usr/bin/date";

/**
 * @var string Path to the egrep utility.
 */
$EGREP =	"/usr/bin/egrep";

/**
 * @var string Path to the grep utility.
 */
$GREP =		"/usr/bin/grep";

/**
 * @var string Path to the head utility.
 */
$HEAD =		"/usr/bin/head";

/**
 * @var string Path to the hostname utility.
 */
$HOSTNAME =	"/usr/bin/hostname";

/**
 * @var string Path to the ifconfig utility.
 */
$IFCONFIG =	"/usr/bin/ip a";

/**
 * @var string Path to the journalctl utility.
 */
$JOURNALCTL =	"/usr/bin/journalctl";

/**
 * @var string Path to the mbuffer utility.
 */
$MBUFFER =	"/usr/bin/mbuffer";

/**
 * @var string Path to the sed utility.
 */
$SED =		"/usr/bin/sed";

/**
 * @var string Command to execute sudo with a specific TERM environment variable.
 */
$SUDO =		"export TERM=vt100 && /usr/bin/sudo";

/**
 * @var string Path to the tail utility.
 */
$TAIL =		"/usr/bin/tail";

/**
 * @var string Path to the uptime utility.
 */
$UPTIME =	"/usr/bin/uptime";

/**
 * @var string Path to the wget utility.
 */
$WGET =		"/usr/bin/wget";

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
