<?php
include("session.inc");
include("authusers.php");
include("user_files/global.inc");
include("common.inc");

$SUPERMON_DIR = "/var/www/html/supermon-ng";

print "<html>\n<body style=\"background-color:black;\">\n";
print "<p style=font-size:16px;>";

if (($_SESSION['sm61loggedin'] === true) && (get_user_auth("CFGEDUSER"))) {

    print "<form name=REFRESH method=POST action='configeditor.php'>";
    print "<link type='text/css' rel='stylesheet' href='supermon-ng.css'>";
    print "<h2> <i>$CALL</i> - AllStar Link / IRLP / EchoLink - Configuration File Editor </h2>";
    print "<p><b>Please use caution when editing files, misconfiguration can cause problems!</b></p><br>";
    print "<input name=refresh tabindex=50 class=submit-large TYPE=SUBMIT Value=Refresh> ";
    print "   <input type=\"button\" class=\"submit-large\" Value=\"Close Window\" onclick=\"self.close()\"></form>";

    print "<form action=edit.php method=post name=select>\n";
    print "<select name=file class=submit-large>\n";

    $files_to_list = [
        ["$SUPERMON_DIR/$USERFILES/authini.inc", "Supermon-ng - $USERFILES/authini.inc"],
        ["$SUPERMON_DIR/$USERFILES/authusers.inc", "Supermon-ng - $USERFILES/authusers.inc"],
        ["$SUPERMON_DIR/$USERFILES/cntrlini.inc", "Supermon-ng - $USERFILES/cntrlini.inc"],
        ["$SUPERMON_DIR/$USERFILES/cntrlnolog.ini", "Supermon-ng - $USERFILES/cntrlnolog.ini"],
        ["$SUPERMON_DIR/$USERFILES/favini.inc", "Supermon-ng - $USERFILES/favini.inc"],
        ["$SUPERMON_DIR/$USERFILES/favnolog.ini", "Supermon-ng - $USERFILES/favnolog.ini"],
        ["$SUPERMON_DIR/$USERFILES/global.inc", "Supermon-ng - global.inc"],
        ["$SUPERMON_DIR/$USERFILES/nolog.ini", "Supermon-ng nolog.ini"],
        ["$SUPERMON_DIR/$USERFILES/allmon.ini", "Supermon-ng - allmon.ini"],
        ["$SUPERMON_DIR/$USERFILES/favorites.ini", "Supermon-ng - favorites.ini"],
        ["$SUPERMON_DIR/$USERFILES/controlpanel.ini", "Supermon-ng - controlpanel.ini"],
        ["$SUPERMON_DIR/supermon.css", "Supermon-ng - supermon-ng.css"],
        ["$SUPERMON_DIR/privatenodes.txt", "Supermon-ng - privatenodes.txt"],
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
        ["/etc/wpa_supplicant/wpa_supplicant_custom-wlan0.conf", "AllStar - wpa_supplicant_custom-wlan0.conf"],
        ["/etc/asterisk/irlp.conf", "AllStar - irlp.conf"],
        ["/home/irlp/custom/environment", "IRLP - environment"],
        ["/home/irlp/custom/custom_decode", "IRLP - custom_decode"],
        ["/home/irlp/custom/custom.crons", "IRLP - custom.crons"],
        ["/home/irlp/custom/lockout_list", "IRLP - lockout_list"],
        ["/home/irlp/custom/timing", "IRLP - timing"],
        ["/home/irlp/custom/timeoutvalue", "IRLP - timeoutvalue"],
        ["/etc/asterisk/echolink.conf", "EchoLink - echolink.conf"],
        ["/usr/local/bin/AUTOSKY/AutoSky.ini", "AutoSky - AutoSky.ini"],
        ["$SUPERMON_DIR/$USERFILES/IMPORTANT-README", "Allmon - README"],
    ];

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

    foreach ($files_to_list as $file_info) {
        $path = $file_info[0];
        $label = $file_info[1];
        $check_type = isset($file_info[2]) ? $file_info[2] : 'file_exists';

        $display_option = false;
        if ($check_type === 'file_exists' && file_exists($path)) {
            $display_option = true;
        } elseif ($check_type === 'is_writable' && is_writable($path)) {
            $display_option = true;
        }

        if ($display_option) {
            print "<option value=\"" . htmlspecialchars($path) . "\">" . htmlspecialchars($label) . "</option>\n";
        }
    }

    print "</select>   <input name=Submit type=submit class=submit-large value=\" Edit File \"></form>\n";

} else {
    print "<br><h3>ERROR: You Must login to use the 'Configuration Editor' tool!</h3>";
}
print "</p>\n</body>\n</html>";
?>