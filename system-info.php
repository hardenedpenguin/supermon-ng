<?php

include("session.inc");
include("security.inc");
include("common.inc");
include("user_files/global.inc");
include("authusers.php");
include("authini.php");
include("favini.php");
include("cntrlini.php");

if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("SYSINFUSER"))) {
    die("<br><h3 class='error-message'>ERROR: You Must login to use the 'System Info' function!</h3>");
}

// Sanitize input parameters
$name = isset($_COOKIE['display-data']['name']) ? htmlspecialchars($_COOKIE['display-data']['name']) : '';
$value = isset($_COOKIE['display-data']['value']) ? htmlspecialchars($_COOKIE['display-data']['value']) : '';

if (!empty($name) && !empty($value)) {
    $name = htmlspecialchars($name);
    $value = htmlspecialchars($value);
    
    if ($name === "show-detailed") {
        $Show_Detail = htmlspecialchars($value);
    }
}

// Define safe command paths with validation
function get_safe_command_path($command_name, $default_path) {
    $path = $default_path;
    
    // Validate the path exists and is executable
    if (!file_exists($path) || !is_executable($path)) {
        // Try common alternatives
        $alternatives = [
            "/usr/bin/{$command_name}",
            "/bin/{$command_name}",
            "/usr/local/bin/{$command_name}"
        ];
        
        foreach ($alternatives as $alt_path) {
            if (file_exists($alt_path) && is_executable($alt_path)) {
                $path = $alt_path;
                break;
            }
        }
    }
    
    return escapeshellarg($path);
}

// Safe command execution function
function safe_exec($command, $args = '') {
    $escaped_command = escapeshellcmd($command);
    if (!empty($args)) {
        $escaped_args = escapeshellarg($args);
        $full_command = "{$escaped_command} {$escaped_args}";
    } else {
        $full_command = $escaped_command;
    }
    
    $output = [];
    $return_var = 0;
    exec($full_command . " 2>/dev/null", $output, $return_var);
    
    if ($return_var !== 0) {
        return 'N/A';
    }
    
    return implode("\n", $output);
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

    // Get safe command paths
    $HOSTNAME_CMD = get_safe_command_path('hostname', '/usr/bin/hostname');
    $AWK_CMD = get_safe_command_path('awk', '/usr/bin/awk');
    $DATE_CMD = get_safe_command_path('date', '/usr/bin/date');
    $CAT_CMD = get_safe_command_path('cat', '/usr/bin/cat');
    $EGREP_CMD = get_safe_command_path('egrep', '/usr/bin/egrep');
    $SED_CMD = get_safe_command_path('sed', '/usr/bin/sed');
    $GREP_CMD = get_safe_command_path('grep', '/usr/bin/grep');
    $HEAD_CMD = get_safe_command_path('head', '/usr/bin/head');
    $TAIL_CMD = get_safe_command_path('tail', '/usr/bin/tail');
    $CURL_CMD = get_safe_command_path('curl', '/usr/bin/curl');
    $CUT_CMD = get_safe_command_path('cut', '/usr/bin/cut');
    $IFCONFIG_CMD = get_safe_command_path('ip', '/usr/bin/ip');
    $UPTIME_CMD = get_safe_command_path('uptime', '/usr/bin/uptime');

    // Safe command execution
    $hostname = safe_exec($HOSTNAME_CMD, "| {$AWK_CMD} -F '.' '{print $1}'");
    $myday = safe_exec($DATE_CMD, "'+%A, %B %e, %Y %Z'");
    $astport = safe_exec($CAT_CMD, "/etc/asterisk/iax.conf | {$EGREP_CMD} '^bindport' | {$SED_CMD} 's/bindport= //g'");
    $mgrport = safe_exec($CAT_CMD, "/etc/asterisk/manager.conf | {$EGREP_CMD} '^port =' | {$SED_CMD} 's/port = //g'");
    $http_port = safe_exec($GREP_CMD, "^Listen /etc/apache2/ports.conf | {$SED_CMD} 's/Listen //g'");

    $myip = 'N/A'; $mylanip = 'N/A'; $WL = '';
    if (empty($WANONLY)) {
        $ip_source_url = 'https://api.ipify.org';

        if (!empty($CURL_CMD) && is_executable($CURL_CMD)) {
            $myip_cmd = $CURL_CMD . " -s --connect-timeout 3 --max-time 5 " . escapeshellarg($ip_source_url);
            $ip_output_lines = [];
            $ip_return_status = -1;

            $potential_ip = safe_exec($myip_cmd, $ip_output_lines, $ip_return_status);

            if ($ip_return_status === 0 && !empty($potential_ip) && filter_var($potential_ip, FILTER_VALIDATE_IP)) {
                $myip = trim($potential_ip);
            } else {
                $myip = 'Lookup Failed';
            }
        } else {
            $myip = 'Lookup Failed (curl not found/executable)';
        }

        $mylanip_cmd1 = "$IFCONFIG_CMD addr show | $GREP_CMD inet | $HEAD_CMD -1 | $AWK_CMD '{print $2}' | $CUT_CMD -d'/' -f1";
        $mylanip = safe_exec($mylanip_cmd1);
        if ($mylanip == "127.0.0.1" || empty($mylanip)) {
            $mylanip_cmd2 = "$IFCONFIG_CMD addr show | $GREP_CMD inet | $TAIL_CMD -1 | $AWK_CMD '{print $2}' | $CUT_CMD -d'/' -f1";
            $mylanip = safe_exec($mylanip_cmd2);
            if ($mylanip != "127.0.0.1" && !empty($mylanip)) {
                $WL = "W";
            } elseif (empty($mylanip)) {
                 $mylanip = 'Not Found';
            }
        }
    } else { 
        $mylanip_cmd = "$IFCONFIG_CMD addr show | $GREP_CMD inet | $HEAD_CMD -1 | $AWK_CMD '{print $2}' | $CUT_CMD -d'/' -f1";
        $mylanip = safe_exec($mylanip_cmd);
         if (empty($mylanip)) { $mylanip = 'Not Found'; }
        $myip = $mylanip;
    }

    $myssh = safe_exec($CAT_CMD, "/etc/ssh/sshd_config | $EGREP_CMD '^Port' | $TAIL_CMD -1 | $CUT_CMD -d' ' -f2");
    if (empty($myssh)) { $myssh = 'Default (22)'; }

    $R1 = safe_exec("head -1", "/etc/allstar_version");
    $R2 = safe_exec("/sbin/asterisk", "-V"); 
    $R3 = safe_exec($CAT_CMD, "/proc/version | $AWK_CMD -F '[(][g]' '{print $1}'"); 
    $R4 = safe_exec($CAT_CMD, "/proc/version | $AWK_CMD -F '[(][g]' '{print \"g\"$2}'"); 

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

    $upsince = safe_exec($UPTIME_CMD, "-s");
    $loadavg_raw = safe_exec($UPTIME_CMD);
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

    $core_dir = '/var/crash';
    $Cores = 0;
    if (is_dir($core_dir) && is_readable($core_dir)) {
        $core_files = glob($core_dir . '/*');
        $Cores = is_array($core_files) ? count($core_files) : 0;
    } else {
        $core_command_output = safe_exec("ls", escapeshellarg($core_dir) . " 2>/dev/null | wc -w");
         $Cores = ($core_command_output !== 'N/A' && is_numeric($core_command_output)) ? intval($core_command_output) : 0;
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

    $temp_script_path = '/usr/local/bin/get_temp';
    $CPUTemp_raw = 'N/A';
    if (file_exists($temp_script_path) && is_executable($temp_script_path)) {
        $CPUTemp_raw = safe_exec($temp_script_path);
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
