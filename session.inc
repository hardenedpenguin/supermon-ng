<?php

if (session_status() == PHP_SESSION_NONE) {

    session_name('supermon61');

    // Force HTTPS detection - check multiple indicators
    $is_secure = false;
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        $is_secure = true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $is_secure = true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        $is_secure = true;
    } elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') {
        $is_secure = true;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();
    
    // Set session timeout (8 hours)
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    } elseif (time() - $_SESSION['last_activity'] > 28800) { // 8 hours
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['last_activity'] = time();
    }
    $_SESSION['last_activity'] = time();
}

$_SESSION['sm61loggedin'] = $_SESSION['sm61loggedin'] ?? false;

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>