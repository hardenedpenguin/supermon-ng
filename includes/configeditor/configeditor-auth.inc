<?php
/**
 * Supermon-ng Configuration Editor Authentication
 * 
 * Handles authentication, authorization, and validation for configuration file editing.
 * Provides user access control and file list generation for the configuration editor.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Validate user access to configuration editor function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateConfigeditorAccess(): bool {
    if (($_SESSION['sm61loggedin'] === true) && (get_user_auth("CFGEDUSER"))) {
        return true;
    }
    return false;
}

/**
 * Get the list of configuration files with their paths and labels
 * 
 * @return array Array of file information with paths, labels, and check types
 */
function getConfigeditorFiles(): array {
    global $SUPERMON_DIR, $USERFILES, $CALL;
    
    $files_to_list = [
        ["$SUPERMON_DIR/$USERFILES/authini.inc", "Supermon-ng - $USERFILES/authini.inc"],
        ["$SUPERMON_DIR/$USERFILES/authusers.inc", "Supermon-ng - $USERFILES/authusers.inc"],
        ["$SUPERMON_DIR/$USERFILES/cntrlini.inc", "Supermon-ng - $USERFILES/cntrlini.inc"],
        ["$SUPERMON_DIR/$USERFILES/cntrlnolog.ini", "Supermon-ng - $USERFILES/cntrlnolog.ini"],
        ["$SUPERMON_DIR/$USERFILES/favini.inc", "Supermon-ng - $USERFILES/favini.inc"],
        ["$SUPERMON_DIR/$USERFILES/favnolog.ini", "Supermon-ng - $USERFILES/favnolog.ini"],
        ["$SUPERMON_DIR/$USERFILES/global.inc", "Supermon-ng - $USERFILES/global.inc"],
        ["$SUPERMON_DIR/$USERFILES/nolog.ini", "Supermon-ng $USERFILES/nolog.ini"],
        ["$SUPERMON_DIR/$USERFILES/allmon.ini", "Supermon-ng - $USERFILES/allmon.ini"],
        ["$SUPERMON_DIR/$USERFILES/favorites.ini", "Supermon-ng - $USERFILES/favorites.ini"],
        ["$SUPERMON_DIR/$USERFILES/controlpanel.ini", "Supermon-ng - $USERFILES/controlpanel.ini"],
        ["$SUPERMON_DIR/$USERFILES/privatenodes.txt", "Supermon-ng - $USERFILES/privatenodes.txt"],
        ["$SUPERMON_DIR/supermon-ng.css", "Supermon-ng - supermon-ng.css"],

        ["/opt/Analog_Bridge/Analog_Bridge.ini", "DvSwitch - Analog_Bridge.ini"],
        ["/opt/MMDVM_Bridge/MMDVM_Bridge.ini", "DvSwitch - MMDVM_Bridge.ini"],
        ["/opt/MMDVM_Bridge/DVSwitch.ini", "DvSwitch - DVSwitch.ini"],

        ["/etc/asterisk/http.conf", "AllStar - http.conf"],
        ["/etc/asterisk/rpt.conf", "AllStar - rpt.conf"],
        ["/etc/asterisk/iax.conf", "AllStar - iax.conf"],
        ["/etc/asterisk/extensions.conf", "AllStar - extensions.conf"],
        ["/etc/asterisk/dnsmgr.conf", "AllStar - dnsmgr.conf"],
        ["/etc/asterisk/voter.conf", "AllStar - voter.conf"],
        ["/etc/asterisk/manager.conf", "AllStar - manager.conf"],
        ["/etc/asterisk/asterisk.conf", "AllStar - asterisk.conf"],
        ["/etc/asterisk/modules.conf", "AllStar - modules.conf"],
        ["/etc/asterisk/logger.conf", "AllStar - logger.conf"],
        ["/etc/asterisk/usbradio.conf", "AllStar - usbradio.conf"],
        ["/etc/asterisk/simpleusb.conf", "AllStar - simpleusb.conf"],
        ["/etc/asterisk/irlp.conf", "AllStar - irlp.conf"],
        ["/etc/asterisk/echolink.conf", "EchoLink - echolink.conf"],
        ["/etc/asterisk/sip.conf", "AllStar - sip.conf"],
        ["/etc/asterisk/users.conf", "AllStar - users.conf"],

        ["/home/irlp/custom/environment", "IRLP - environment"],
        ["/home/irlp/custom/custom_decode", "IRLP - custom_decode"],
        ["/home/irlp/custom/custom.crons", "IRLP - custom.crons"],
        ["/home/irlp/custom/lockout_list", "IRLP - lockout_list"],
        ["/home/irlp/custom/timing", "IRLP - timing"],
        ["/home/irlp/custom/timeoutvalue", "IRLP - timeoutvalue"],

        ["/usr/local/bin/AUTOSKY/AutoSky.ini", "AutoSky - AutoSky.ini"],

        ["/usr/local/etc/allstar.env", "Misc - allstar.env"],
    ];

    $separator_item = ["###SEPARATOR###", "─────────────────────────────────"];

    array_splice($files_to_list, 13, 0, [$separator_item]);
    array_splice($files_to_list, 17, 0, [$separator_item]);
    array_splice($files_to_list, 34, 0, [$separator_item]);

    // Add conditional files
    $irlp_cron_path_noupdate = "/home/irlp/noupdate/scripts/irlp.crons";
    $irlp_cron_path_scripts = "/home/irlp/scripts/irlp.crons";
    if (file_exists($irlp_cron_path_noupdate)) {
        $files_to_list[] = [$irlp_cron_path_noupdate, "IRLP - irlp.crons"];
    } elseif (file_exists($irlp_cron_path_scripts)) {
        $files_to_list[] = [$irlp_cron_path_scripts, "IRLP - irlp.crons"];
    }
    
    $autosky_log_path = "/usr/local/bin/AUTOSKY/AutoSky-log.txt";
    if (file_exists($autosky_log_path) && filesize($autosky_log_path) > 0) {
        $files_to_list[] = [$autosky_log_path, "AutoSky - AutoSky-log.txt"];
    }

    return $files_to_list;
}

/**
 * Check if a file should be displayed based on its type
 * 
 * @param array $file_info File information array
 * @return bool True if file should be displayed, false otherwise
 */
function shouldDisplayFile(array $file_info): bool {
    $path = $file_info[0];
    $check_type = isset($file_info[2]) ? $file_info[2] : 'file_exists';
    
    if ($path === "###SEPARATOR###") {
        return true; // Always display separators
    }

    if ($check_type === 'file_exists' && file_exists($path)) {
        return true;
    } elseif ($check_type === 'is_writable' && is_writable($path)) {
        return true;
    }

    return false;
}
