<?php
/**
 * Supermon-ng Logout Authentication
 * 
 * Handles logout logging, session cleanup, and security measures for user logout.
 * Provides comprehensive session termination and audit trail functionality.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Log the logout event
 * 
 * @return void
 */
function logLogoutEvent(): void {
    global $SMLOG, $SMLOGNAME;
    
    if (!isset($_SESSION['user']) || !isset($SMLOG) || $SMLOG !== "yes" || !isset($SMLOGNAME)) {
        return;
    }
    
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
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
    $user = $_SESSION['user'] ?? 'unknown';
    
    $wrtStr = sprintf(
        "Supermon-ng <b>logout</b> Host-%s <b>user-%s</b> at %s from IP-%s\n",
        htmlspecialchars($hostname, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($user, ENT_QUOTES, 'UTF-8'),
        $myday,
        htmlspecialchars($ip, ENT_QUOTES, 'UTF-8')
    );

    if (file_put_contents($SMLOGNAME, $wrtStr, FILE_APPEND | LOCK_EX) === false) {
        error_log("Failed to write to SMLOGNAME: {$SMLOGNAME}");
    }
}

/**
 * Clear all session data
 * 
 * @return void
 */
function clearSessionData(): void {
    $_SESSION = array();
}

/**
 * Destroy session cookie
 * 
 * @return void
 */
function destroySessionCookie(): void {
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
}

/**
 * Destroy the session
 * 
 * @return void
 */
function destroySession(): void {
    session_destroy();
}

/**
 * Clear all cookies
 * 
 * @return void
 */
function clearAllCookies(): void {
    $cookies = $_COOKIE;
    foreach ($cookies as $name => $value) {
        setcookie($name, '', time() - 3600, '/');
    }
}

/**
 * Set security headers
 * 
 * @return void
 */
function setLogoutSecurityHeaders(): void {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/**
 * Perform complete logout process
 * 
 * @return void
 */
function performLogout(): void {
    // Log the logout event
    logLogoutEvent();
    
    // Clear all session data
    clearSessionData();
    
    // Destroy session cookie
    destroySessionCookie();
    
    // Destroy the session
    destroySession();
    
    // Clear any other cookies
    clearAllCookies();
    
    // Set security headers
    setLogoutSecurityHeaders();
}
