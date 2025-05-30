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

        if ($path === "###SEPARATOR###") {
            print "<option value=\"\" disabled>" . htmlspecialchars($label) . "</option>\n";
            continue;
        }

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