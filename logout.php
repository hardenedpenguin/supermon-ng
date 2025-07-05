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

?>
<!DOCTYPE html>
<html>
<head>
	<title>Logged Out - Supermon-ng</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<style>
		body { 
			font-family: Arial, sans-serif; 
			margin: 0; 
			padding: 20px; 
			background: #f5f5f5; 
			text-align: center;
		}
		.logout-container { 
			max-width: 400px; 
			margin: 50px auto; 
			background: white; 
			padding: 30px; 
			border-radius: 8px; 
			box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
		}
		.logout-title { 
			color: #333; 
			margin-bottom: 20px; 
		}
		.logout-message { 
			color: #666; 
			margin-bottom: 30px; 
		}
		.btn { 
			display: inline-block; 
			padding: 12px 24px; 
			background: #007cba; 
			color: white; 
			text-decoration: none; 
			border-radius: 4px; 
			font-size: 16px; 
		}
		.btn:hover { 
			background: #005a87; 
		}
	</style>
</head>
<body>
	<div class="logout-container">
		<h2 class="logout-title">Successfully Logged Out</h2>
		<p class="logout-message">
			You have been successfully logged out of Supermon-ng.<br>
			All session data has been cleared.
		</p>
		<a href="index.php" class="btn">Return to Login</a>
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
</body>
</html>