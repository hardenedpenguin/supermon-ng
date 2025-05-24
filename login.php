<?php
include_once "session.inc";

if (!isset($_SESSION['sm61loggedin'])) {
    $_SESSION['sm61loggedin'] = false;
}

define('HTPASSWD_FILE', '.htpasswd');

function http_authenticate(string $user, string $pass, string $pass_file = HTPASSWD_FILE): bool {
    if (!file_exists($pass_file) || !is_readable($pass_file)) {
        error_log("htpasswd file '{$pass_file}' not found or not readable.");
        return false;
    }

    $fp = @fopen($pass_file, 'r');
    if ($fp === false) {
        error_log("Failed to open htpasswd file '{$pass_file}'.");
        return false;
    }

    $authenticated = false;
    while (($line = fgets($fp)) !== false) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') {
            continue;
        }

        $parts = explode(':', $line, 2);
        if (count($parts) !== 2) {
            error_log("Malformed line in htpasswd file '{$pass_file}': {$line}");
            continue;
        }
        list($fuser, $fpass) = $parts;

        if ($fuser === $user) {
            if (password_verify($pass, $fpass)) {
                $authenticated = true;
            }
            break;
        }
    }
    fclose($fp);
    return $authenticated;
}

function logUser(string $user, bool $success): void {
    include_once "user_files/global.inc";
    include_once "common.inc";
    include_once "authini.php";

    if (isset($SMLOG) && $SMLOG === "yes" && isset($SMLOGNAME)) {
        $type = $success ? "Success" : "Fail";
        
        $logUserIdentifier = $_SESSION['user'] ?? $user; 
        $supIni = function_exists('get_ini_name') ? get_ini_name($logUserIdentifier) : 'N/A';

        $hostname = gethostname();
        if ($hostname === false) {
            $hostname = 'unknown_host';
        } else {
            $hostnameParts = explode('.', $hostname);
            $hostname = $hostnameParts[0];
        }
        
        try {
            $dateTime = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
            $myday = $dateTime->format('l, F j, Y T - H:i:s');
        } catch (Exception $e) {
            $myday = 'N/A_DATE';
            error_log("DateTime error in logUser: " . $e->getMessage());
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
        
        $wrtStr = sprintf(
            "Supermon-ng <b>login %s</b> Host-%s <b>user-%s</b> at %s from IP-%s using ini file-%s\n",
            $type,
            htmlspecialchars($hostname, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($user, ENT_QUOTES, 'UTF-8'),
            $myday,
            htmlspecialchars($ip, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($supIni, ENT_QUOTES, 'UTF-8')
        );

        if (file_put_contents($SMLOGNAME, $wrtStr, FILE_APPEND | LOCK_EX) === false) {
            error_log("Failed to write to SMLOGNAME: {$SMLOGNAME}");
        }
    }
}

$user = $_POST['user'] ?? '';
$passwd = $_POST['passwd'] ?? '';

if (http_authenticate($user, $passwd)) {
    if (function_exists('session_regenerate_id')) {
        session_regenerate_id(true);
    }
    
    $_SESSION['sm61loggedin'] = true;
    $_SESSION['user'] = $user;

    logUser($user, true);

    $current_script_url = basename($_SERVER['REQUEST_URI'] ?? '');
    $redirect_url = urldecode($current_script_url); 

    echo "Login succeeded. Redirecting...";
    echo "<meta http-equiv='REFRESH' content='0;url=" . htmlspecialchars($redirect_url, ENT_QUOTES, 'UTF-8') . "'>";
    
} else {
    logUser($user, false);
    echo "Sorry, login failed."; 
}
?>