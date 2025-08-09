<?php
/**
 * System Info Data Collectors
 * 
 * Functions to collect various system information including
 * network, system versions, and hardware data.
 */

/**
 * Collect basic system information
 * 
 * @param array $commands Array of safe command paths
 * @return array Array of collected system data
 */
function collectBasicSystemInfo($commands) {
    extract($commands); // Extract command variables
    
    return [
        'hostname' => safe_exec($HOSTNAME_CMD, "| {$AWK_CMD} -F '.' '{print $1}'"),
        'myday' => safe_exec($DATE_CMD, "'+%A, %B %e, %Y %Z'"),
        'astport' => safe_exec($CAT_CMD, "/etc/asterisk/iax.conf | {$EGREP_CMD} '^bindport' | {$SED_CMD} 's/bindport= //g'"),
        'mgrport' => safe_exec($CAT_CMD, "/etc/asterisk/manager.conf | {$EGREP_CMD} '^port =' | {$SED_CMD} 's/port = //g'"),
        'http_port' => safe_exec($GREP_CMD, "^Listen /etc/apache2/ports.conf | {$SED_CMD} 's/Listen //g'")
    ];
}

/**
 * Collect network IP information
 * 
 * @param array $commands Array of safe command paths
 * @return array Array of network information
 */
function collectNetworkInfo($commands) {
    global $WANONLY;
    extract($commands); // Extract command variables
    
    $myip = 'N/A'; 
    $mylanip = 'N/A'; 
    $WL = '';
    
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

    return ['myip' => $myip, 'mylanip' => $mylanip, 'WL' => $WL];
}

/**
 * Collect SSH and system version information
 * 
 * @param array $commands Array of safe command paths
 * @return array Array of SSH and version information
 */
function collectSSHAndVersionInfo($commands) {
    extract($commands); // Extract command variables
    
    $myssh = safe_exec($CAT_CMD, "/etc/ssh/sshd_config | $EGREP_CMD '^Port' | $TAIL_CMD -1 | $CUT_CMD -d' ' -f2");
    if (empty($myssh)) { $myssh = 'Default (22)'; }

    $R1 = safe_exec("head -1", "/etc/allstar_version");
    $R2 = safe_exec("/sbin/asterisk", "-V"); 
    $R3 = safe_exec($CAT_CMD, "/proc/version | $AWK_CMD -F '[(][g]' '{print $1}'"); 
    $R4 = safe_exec($CAT_CMD, "/proc/version | $AWK_CMD -F '[(][g]' '{print \"g\"$2}'"); 

    return [
        'myssh' => $myssh,
        'R1' => $R1,
        'R2' => $R2, 
        'R3' => $R3,
        'R4' => $R4
    ];
}
