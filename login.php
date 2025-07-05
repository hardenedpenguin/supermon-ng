<?php
include_once "session.inc";
include_once "rate_limit.inc";

if (!isset($_SESSION['sm61loggedin'])) {
    $_SESSION['sm61loggedin'] = false;
}

define('HTPASSWD_FILE', '.htpasswd');

// Check rate limiting for login attempts
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (is_rate_limited('login', 5, 900)) { // 5 attempts per 15 minutes
        http_response_code(429);
        die("Too many login attempts. Please wait 15 minutes before trying again.");
    }
}

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

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'] ?? '';
    $passwd = $_POST['passwd'] ?? '';
    
    // Basic input validation
    if (empty($user) || empty($passwd)) {
        $error_message = "Username and password are required.";
    } else {
        if (http_authenticate($user, $passwd)) {
            // Clear rate limit on successful login
            clear_rate_limit('login');
            
            if (function_exists('session_regenerate_id')) {
                session_regenerate_id(true);
            }
            
            $_SESSION['sm61loggedin'] = true;
            $_SESSION['user'] = $user;
            $_SESSION['login_time'] = time();

            logUser($user, true);

            $current_script_url = basename($_SERVER['REQUEST_URI'] ?? '');
            $redirect_url = urldecode($current_script_url); 

            echo "Login succeeded. Redirecting...";
            echo "<meta http-equiv='REFRESH' content='0;url=" . htmlspecialchars($redirect_url, ENT_QUOTES, 'UTF-8') . "'>";
            exit;
        } else {
            logUser($user, false);
            $remaining_attempts = get_remaining_attempts('login', 5, 900);
            $error_message = "Login failed. Remaining attempts: {$remaining_attempts}";
        }
    }
}

// Display login form
?>
<!DOCTYPE html>
<html>
<head>
    <title>Supermon-ng Login</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .login-container { max-width: 400px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .login-title { text-align: center; margin-bottom: 30px; color: #333; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn { width: 100%; padding: 12px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #005a87; }
        .error { color: #d32f2f; background: #ffebee; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .attempts { color: #666; font-size: 14px; text-align: center; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="login-title">Supermon-ng Login</h2>
        
        <?php if (isset($error_message)): ?>
            <div class="error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="user">Username:</label>
                <input type="text" id="user" name="user" required>
            </div>
            
            <div class="form-group">
                <label for="passwd">Password:</label>
                <input type="password" id="passwd" name="passwd" required>
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
        
        <div class="attempts">
            <?php 
            $remaining = get_remaining_attempts('login', 5, 900);
            echo "Remaining login attempts: {$remaining}";
            ?>
        </div>
    </div>
</body>
</html>