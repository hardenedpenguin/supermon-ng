<?php
	include("session.inc");
	include("user_files/global.inc");
	include("common.inc");
	include("authini.php");

	// Log the logout event
	if (isset($_SESSION['user']) && isset($SMLOG) && $SMLOG === "yes" && isset($SMLOGNAME)) {
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

	// Clear all session data
	$_SESSION = array();

	// Destroy the session cookie
	if (ini_get("session.use_cookies")) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000,
			$params["path"], $params["domain"],
			$params["secure"], $params["httponly"]
		);
	}

	// Destroy the session
	session_destroy();

	// Clear any other cookies that might be set
	$cookies = $_COOKIE;
	foreach ($cookies as $name => $value) {
		setcookie($name, '', time() - 3600, '/');
	}

	// Set security headers
	header('Cache-Control: no-cache, no-store, must-revalidate');
	header('Pragma: no-cache');
	header('Expires: 0');

	// Include header for consistent styling
	include "header.inc";
?>

<style>
.logout-page {
    max-width: 400px;
    margin: 50px auto;
    background: var(--container-bg);
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
    border: 1px solid var(--border-color);
    text-align: center;
}

.logout-title {
    color: var(--text-color);
    margin-bottom: 20px;
    font-size: 1.5em;
    font-weight: bold;
}

.logout-message {
    color: var(--text-color);
    margin-bottom: 30px;
    opacity: 0.9;
    line-height: 1.5;
}

.logout-btn {
    display: inline-block;
    padding: 12px 24px;
    background: var(--primary-color);
    color: var(--text-color);
    text-decoration: none;
    border-radius: 4px;
    font-size: 16px;
    font-weight: bold;
    transition: background-color 0.3s ease;
}

.logout-btn:hover {
    background: var(--link-hover);
    text-decoration: none;
    color: var(--text-color);
}

@media (max-width: 768px) {
    .logout-page {
        margin: 20px auto;
        padding: 20px;
    }
    
    .logout-title {
        font-size: 1.3em;
    }
}
</style>

<div class="logout-page">
	<h2 class="logout-title">Successfully Logged Out</h2>
	<p class="logout-message">
		You have been successfully logged out of Supermon-ng.<br>
		All session data has been cleared.
	</p>
	<a href="index.php" class="logout-btn">Return to Login</a>
</div>

<script>
	// Clear any client-side storage
	if (typeof localStorage !== 'undefined') {
		localStorage.clear();
	}
	if (typeof sessionStorage !== 'undefined') {
		sessionStorage.clear();
	}
	
	// Redirect to login page after 3 seconds
	setTimeout(function() {
		window.location.href = 'index.php';
	}, 3000);
</script>

<?php include "footer.inc"; ?>