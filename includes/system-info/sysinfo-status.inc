<?php
/**
 * System Info Status Display
 * 
 * Handles configuration status display, uptime information,
 * core dump checking, and CPU temperature monitoring.
 */

/**
 * Display user files and configuration status
 */
function displayConfigurationStatus() {
    global $USERFILES;
    
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
}

/**
 * Display system uptime and load average
 * 
 * @param array $commands Array of safe command paths
 * @param string $myday Formatted date string
 */
function displayUptimeAndLoad($commands, $myday) {
    extract($commands); // Extract command variables
    
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
}

/**
 * Display core dump information
 */
function displayCoreDumpInfo() {
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
}

/**
 * Display CPU temperature information
 */
function displayCPUTemperature() {
    define('CPU_TEMP_WARNING_THRESHOLD', 50); 
    define('CPU_TEMP_HIGH_THRESHOLD', 65);    

    $temp_script_path = 'user_files/sbin/get_temp';
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
}
