<?php

include("session.inc");
include("common.inc");
include("user_files/global.inc");
include("authusers.php");
include("authini.php");
include("favini.php");
include("cntrlini.php");

if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("SYSINFUSER"))) {
    die("<br><h3>ERROR: You Must login to use the 'System Info' function!</h3>");
}

$Show_Detail = 0;
if (isset($_COOKIE['display-data']) && is_array($_COOKIE['display-data'])) {
    foreach ($_COOKIE['display-data'] as $name => $value) {
        $name = htmlspecialchars($name);
        switch ($name) {
            case "show-detailed":
                $Show_Detail = htmlspecialchars($value);
                break;
        }
    }
}

?>

<html>
<head>
    <meta charset="UTF-8"> 
    <link type="text/css" rel="stylesheet" href="supermon-ng.css"> 
    <script>
        function refreshParent() {
            if (window.opener && !window.opener.closed) {
                 try {
                     window.opener.location.reload();
                 } catch (e) {
                     console.error("Error reloading opener window:", e);
                 }
            }
        }
    </script>
    <title>System Info</title> 
</head>

<body> 
    <p class="page-title">System Info</p>
    <br> 
    <?php
    $info_container_class = ($Show_Detail == 1) ? 'info-container-detailed' : 'info-container-summary';

    print "<div class=\"" . htmlspecialchars($info_container_class) . "\">";

    $HOSTNAME_CMD = isset($HOSTNAME) ? $HOSTNAME : '/usr/bin/hostname';
    $AWK_CMD = isset($AWK) ? $AWK : '/usr/bin/awk';
    $DATE_CMD = isset($DATE) ? $DATE : '/usr/bin/date';
    $CAT_CMD = isset($CAT) ? $CAT : '/usr/bin/cat';
    $EGREP_CMD = isset($EGREP) ? $EGREP : '/usr/bin/egrep';
    $SED_CMD = isset($SED) ? $SED : '/usr/bin/sed';
    $GREP_CMD = isset($GREP) ? $GREP : '/usr/bin/grep';
    $HEAD_CMD = isset($HEAD) ? $HEAD : '/usr/bin/head';
    $TAIL_CMD = isset($TAIL) ? $TAIL : '/usr/bin/tail';
    $CURL_CMD = isset($CURL) ? $CURL : '/usr/bin/curl';
    $CUT_CMD = isset($CUT) ? $CUT : '/usr/bin/cut';
    $IFCONFIG_CMD = isset($IFCONFIG) ? $IFCONFIG : '/usr/bin/ip a';
    $UPTIME_CMD = isset($UPTIME) ? $UPTIME : '/usr/bin/uptime';

    $hostname = exec("$HOSTNAME_CMD | $AWK_CMD -F '.' '{print $1}'");
    $myday = exec("$DATE_CMD '+%A, %B %e, %Y %Z'");
    $astport = exec("$CAT_CMD /etc/asterisk/iax.conf | $EGREP_CMD '^bindport' | $SED_CMD 's/bindport= //g'");
    $mgrport = exec("$CAT_CMD /etc/asterisk/manager.conf | $EGREP_CMD '^port =' | $SED_CMD 's/port = //g'");
    $http_port = exec("$GREP_CMD ^Listen /etc/apache2/ports.conf | $SED_CMD 's/Listen //g'");

    $myip = 'N/A'; $mylanip = 'N/A'; $WL = '';
    if (empty($WANONLY)) {
        $ip_source_url = 'https://api.ipify.org';

        if (!empty($CURL_CMD) && is_executable($CURL_CMD)) {
            $myip_cmd = $CURL_CMD . " -s --connect-timeout 3 --max-time 5 " . escapeshellarg($ip_source_url);
            $ip_output_lines = [];
            $ip_return_status = -1;

            $potential_ip = exec($myip_cmd, $ip_output_lines, $ip_return_status);

            if ($ip_return_status === 0 && !empty($potential_ip) && filter_var($potential_ip, FILTER_VALIDATE_IP)) {
                $myip = trim($potential_ip);
            } else {
                $myip = 'Lookup Failed';
            }
        } else {
            $myip = 'Lookup Failed (curl not found/executable)';
        }

        $mylanip_cmd1 = "$IFCONFIG_CMD | $GREP_CMD inet | $HEAD_CMD -1 | $AWK_CMD '{print $2}'";
        $mylanip = exec($mylanip_cmd1);
        if ($mylanip == "127.0.0.1" || empty($mylanip)) {
            $mylanip_cmd2 = "$IFCONFIG_CMD | $GREP_CMD inet | $TAIL_CMD -1 | $AWK_CMD '{print $2}'";
            $mylanip = exec($mylanip_cmd2);
            if ($mylanip != "127.0.0.1" && !empty($mylanip)) {
                $WL = "W";
            } elseif (empty($mylanip)) {
                 $mylanip = 'Not Found';
            }
        }
    } else { 
        $mylanip_cmd = "$IFCONFIG_CMD | $GREP_CMD inet | $HEAD_CMD -1 | $AWK_CMD '{print $2}'";
        $mylanip = exec($mylanip_cmd);
         if (empty($mylanip)) { $mylanip = 'Not Found'; }
        $myip = $mylanip;
    }

    $myssh = exec("$CAT_CMD /etc/ssh/sshd_config | $EGREP_CMD '^Port' | $TAIL_CMD -1 | $CUT_CMD -d' ' -f2");
    if (empty($myssh)) { $myssh = 'Default (22)'; }

    print "Version - " . (isset($TITLE_LOGGED) ? htmlspecialchars($TITLE_LOGGED) : 'N/A') . "<br>";
    print "Date - " . (isset($VERSION_DATE) ? htmlspecialchars($VERSION_DATE) : 'N/A') . "<br>";

    print "Hostname - " . htmlspecialchars($hostname) . "<br>";
    print "Public IP - <a href=\"custom/iplog.txt\" target=\"_blank\">" . htmlspecialchars($myip) . "</a>";
    if ($myip != $mylanip && $mylanip !== 'Not Found' && !empty($mylanip)) {
        print " . $WL<br>"; 
        print "LAN IP - " . htmlspecialchars($mylanip) . "<br>";
    } else {
        print "<br>";
    }
    print "IAX Port - " . htmlspecialchars($astport) . "<br>";
    print "Asterisk Manager Port - " . htmlspecialchars($mgrport) . "<br>";
    print "SSH Port - " . htmlspecialchars($myssh) . "<br>";
    print "HTTP Port - " . htmlspecialchars($http_port) . "<br><br>"; 

    $R1 = exec("head -1 /etc/allstar_version");
    $R2 = exec("/sbin/asterisk -V"); 
    $R3 = exec("cat /proc/version | awk -F '[(][g]' '{print $1}'"); 
    $R4 = exec("cat /proc/version | awk -F '[(][g]' '{print 'g'$2}'"); 

    print "<p class=\"section-subheader\">AllStar Version Numbers</p>";
    print "<b>Asterisk Version:</b><br>" . htmlspecialchars($R2) . "<br>";
    print "<b>Linux Kernel Version:</b><br>" . htmlspecialchars($R3) . htmlspecialchars($R4) . "<br>";
    print "<br>"; 

    $user_files_dir = isset($USERFILES) ? $USERFILES : 'user_files';
    print "ALL user configurable files are in the <b>\"" . htmlspecialchars(getcwd()) . "/" . htmlspecialchars($user_files_dir) . "\"</b> directory.<br><br>";

    $current_user = isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : 'N/A';
    $current_ini = function_exists('get_ini_name') ? get_ini_name($current_user) : 'allmon.ini';
    print "Logged in as: '<b>" . $current_user . "</b>' using INI file: '<b>" . htmlspecialchars($current_ini) . "</b>'<br>";

    $logged_out_ini = "$user_files_dir/allmon.ini"; 
    if (file_exists("$user_files_dir/authini.inc") && file_exists("$user_files_dir/nolog.ini")) {
        $logged_out_ini = "$user_files_dir/nologin.ini"; 
    }
    print "Supermon Logged OUT INI: \"<b>" . htmlspecialchars($logged_out_ini) . "</b>\"<br>";
    print "<br>"; 

    $ini_valid = function_exists('iniValid') && iniValid(); 
    $favini_valid = function_exists('faviniValid') && faviniValid(); 
    $cntrlini_valid = function_exists('cntrliniValid') && cntrliniValid(); 

    if (file_exists("$user_files_dir/authini.inc") && $ini_valid) {
        print "Selective INI based on username: <b>ACTIVE</b><br>";
    } else {
        print "Selective INI based on username: <b>INACTIVE</b> (Using <b>" . htmlspecialchars("$user_files_dir/allmon.ini") . "</b>)<br>";
    }

    if (file_exists("$user_files_dir/authusers.inc")) {
        print "Button selective based on username: <b>ACTIVE</b> (using rules related to '<b>" . htmlspecialchars($current_ini) . "</b>')<br>";
    } else {
        print "Button selective based on username: <b>INACTIVE</b><br>";
    }

    if (file_exists("$user_files_dir/favini.inc") && $favini_valid && function_exists('get_fav_ini_name')) {
        $current_fav_ini = get_fav_ini_name($current_user);
        print "Selective Favorites INI based on username: <b>ACTIVE</b> (using <b>\"" . htmlspecialchars($current_fav_ini) . "</b>\")<br>";
    } else {
        print "Selective Favorites INI: <b>INACTIVE</b> (using <b>" . htmlspecialchars("$user_files_dir/favorites.ini") . "</b>)<br>";
    }

    if (file_exists("$user_files_dir/cntrlini.inc") && $cntrlini_valid && function_exists('get_cntrl_ini_name')) {
        $current_cntrl_ini = get_cntrl_ini_name($current_user);
        print "Selective Control Panel INI based on username: <b>ACTIVE</b> (using <b>\"" . htmlspecialchars($current_cntrl_ini) . "</b>\")<br>";
    } else {
        print "Selective Control Panel INI: <b>INACTIVE</b> (using <b>" . htmlspecialchars("$user_files_dir/controlpanel.ini") . "</b>)<br>";
    }

    $upsince = exec("$UPTIME_CMD -s");
    $loadavg_raw = exec("$UPTIME_CMD");
    $loadavg = 'N/A';
    if (strpos($loadavg_raw, 'load average:') !== false) {
        $loadavg_parts = explode('load average:', $loadavg_raw);
        $loadavg = trim($loadavg_parts[1]); 
    } elseif (file_exists('/proc/loadavg')) { 
         $loadavg_parts = explode(' ', file_get_contents('/proc/loadavg'));
         $loadavg = $loadavg_parts[0] . ', ' . $loadavg_parts[1] . ', ' . $loadavg_parts[2];
    }
    print "<br>" . htmlspecialchars($myday) . " - Up since: " . htmlspecialchars($upsince) . " - Load Average: " . htmlspecialchars($loadavg) . "<br>";
    print "<br>"; 

    $core_dir = '/var/lib/systemd/coredump';
    $Cores = 0;
    if (is_dir($core_dir) && is_readable($core_dir)) {
        $core_files = glob($core_dir . '/*');
        $Cores = is_array($core_files) ? count($core_files) : 0;
    } else {
        $core_command_output = exec("ls " . escapeshellarg($core_dir) . " 2>/dev/null | wc -w", $core_output_lines, $core_return_var);
         $Cores = ($core_return_var === 0 && isset($core_output_lines[0])) ? intval($core_output_lines[0]) : 0;
    }

    print "[ Core dumps: ";
    if ($Cores >= 1 && $Cores <= 2) { 
        print "<span class=\"coredump-warning\">" . $Cores . "</span>";
    } elseif ($Cores > 2) { 
        print "<span class=\"coredump-error\">" . $Cores . "</span>";
    } else { 
        print "0";
    }
    print " ]<br><br>"; 

    define('CPU_TEMP_WARNING_THRESHOLD', 50); 
    define('CPU_TEMP_HIGH_THRESHOLD', 65);    

    $temp_script_path = "/usr/local/sbin/supermon/get_temp";
    $CPUTemp_raw = '';
    if (is_executable($temp_script_path)) {
        $CPUTemp_raw = exec($temp_script_path);
    } else {
        $CPUTemp_raw = "Error: Script not executable ($temp_script_path)";
    }

    $cleaned_step1 = strip_tags($CPUTemp_raw);
    $cleaned_step2 = html_entity_decode($cleaned_step1, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $cleaned_step3 = preg_replace('/\s+/', ' ', $cleaned_step2);
    $CPUTemp_cleaned = trim($cleaned_step3);

    $temp_class = 'cpu-temp-unknown';
    $output_html = "<span class=\"" . $temp_class . "\">" . htmlspecialchars($CPUTemp_cleaned) . "</span>";

    if (preg_match('/^(CPU:)\s*(.*?)\s*(@\s*\d{2}:\d{2})$/', $CPUTemp_cleaned, $matches)) {
        $cpu_prefix_text = trim($matches[1]);
        $temp_text_content = trim($matches[2]); 
        $cpu_suffix_text = trim($matches[3]);

        $celsius_val = null;
        if (preg_match('/(-?\d+)\s?°?C/', $temp_text_content, $celsius_matches)) {
            $celsius_val = intval($celsius_matches[1]);

            if ($celsius_val >= CPU_TEMP_HIGH_THRESHOLD) {
                $temp_class = 'cpu-temp-high'; 
            } elseif ($celsius_val >= CPU_TEMP_WARNING_THRESHOLD) {
                $temp_class = 'cpu-temp-warning'; 
            } else {
                $temp_class = 'cpu-temp-normal'; 
            }
        }

        $output_html = htmlspecialchars($cpu_prefix_text) .
                       " <span class=\"" . $temp_class . "\">" . 
                       htmlspecialchars($temp_text_content) .     
                       "</span>" .                             
                       " " . htmlspecialchars($cpu_suffix_text); 

    }

    print $output_html;
    print "<br><br>"; 

    ?>
    </div> 
    <center> 
        <input type="button" class="submit2" Value="Close Window" onclick="self.close();"> 
    </center>
    <br> 
</body>
</html>
