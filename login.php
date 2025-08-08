<?php
include_once "includes/session.inc";
include_once "includes/rate_limit.inc";
include_once "includes/security.inc";

if (!isset($_SESSION['sm61loggedin'])) {
    $_SESSION['sm61loggedin'] = false;
}

define('HTPASSWD_FILE', '.htpasswd');

// Check rate limiting for login attempts
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (is_rate_limited('login', 5, 900)) { // 5 attempts per 15 minutes
        http_response_code(429);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Too many login attempts. Please wait 15 minutes before trying again.']);
        } else {
            die("Too many login attempts. Please wait 15 minutes before trying again.");
        }
        exit;
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
    include_once "includes/common.inc";
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
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    
    // Basic input validation
    if (empty($user) || empty($passwd)) {
        $error_message = "Username and password are required.";
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit;
        }
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

            if ($is_ajax) {
                // Return JSON response for AJAX requests
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Login successful', 'user' => $user]);
            } else {
                // For form submissions, redirect to main page
                header('Location: index.php');
                exit;
            }
            exit;
        } else {
            logUser($user, false);
            $remaining_attempts = get_remaining_attempts('login', 5, 900);
            $error_message = "Login failed. Remaining attempts: {$remaining_attempts}";
            
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $error_message]);
                exit;
            }
        }
    }
}

// Include header for consistent styling
include "header.inc";
?>

<style>
.login-page {
    max-width: 400px;
    margin: 50px auto;
    background: var(--container-bg);
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
    border: 1px solid var(--border-color);
}

.login-title {
    text-align: center;
    margin-bottom: 30px;
    color: var(--text-color);
    font-size: 1.5em;
    font-weight: bold;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: var(--text-color);
    font-weight: bold;
}

.form-group input[type="text"],
.form-group input[type="password"] {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    box-sizing: border-box;
    background: var(--input-bg);
    color: var(--input-text);
    font-size: 16px;
}

.form-group input[type="text"]:focus,
.form-group input[type="password"]:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

.login-btn {
    width: 100%;
    padding: 12px;
    background: var(--primary-color);
    color: var(--text-color);
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    transition: background-color 0.3s ease;
}

.login-btn:hover {
    background: var(--link-hover);
}

.login-error {
    color: var(--error-color);
    background: rgba(211, 47, 47, 0.1);
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 20px;
    border: 1px solid var(--error-color);
}

.login-attempts {
    color: var(--text-color);
    font-size: 14px;
    text-align: center;
    margin-top: 10px;
    opacity: 0.8;
}

@media (max-width: 768px) {
    .login-page {
        margin: 20px auto;
        padding: 20px;
    }
    
    .login-title {
        font-size: 1.3em;
    }
}
</style>

<div class="login-page">
    <h2 class="login-title">Supermon-ng Login</h2>
    
    <?php if (isset($error_message)): ?>
        <div class="login-error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    
    <?php
    // Setup form data for reusable form include
    $fields = [
        [
            'type' => 'text',
            'name' => 'user',
            'value' => '',
            'label' => 'Username:',
            'attrs' => 'id="user" required',
            'wrapper_class' => 'form-group',
            'label_for' => 'user'
        ],
        [
            'type' => 'password',
            'name' => 'passwd',
            'value' => '',
            'label' => 'Password:',
            'attrs' => 'id="passwd" required',
            'wrapper_class' => 'form-group',
            'label_for' => 'passwd'
        ]
    ];
    $action = '';
    $method = 'post';
    $submit_label = 'Login';
    $form_class = 'login-form';
    $submit_class = 'login-btn';
    ?>
    <?php include 'includes/form.inc'; ?>
    
    <div class="login-attempts">
        <?php 
        $remaining = get_remaining_attempts('login', 5, 900);
        echo "Remaining login attempts: {$remaining}";
        ?>
    </div>
</div>

<?php include "footer.inc"; ?>