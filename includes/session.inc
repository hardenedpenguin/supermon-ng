<?php
/**
 * Supermon-ng Session Management
 * 
 * Provides comprehensive session management functionality for Supermon-ng.
 * Handles session initialization, security configuration, timeout management,
 * and CSRF token generation for secure web application operation.
 * 
 * Features:
 * - Secure session initialization with HTTPS detection
 * - Configurable session timeout (8 hours default)
 * - CSRF token generation and management
 * - Secure cookie configuration with SameSite protection
 * - Automatic session cleanup and regeneration
 * - Multiple HTTPS detection methods for proxy environments
 * 
 * Security:
 * - HTTPS-only cookies when available
 * - HttpOnly flag for XSS protection
 * - SameSite=Strict for CSRF protection
 * - Session timeout with automatic cleanup
 * - Secure session naming and configuration
 * 
 * Configuration:
 * - Session name: 'supermon61'
 * - Default timeout: 8 hours (28800 seconds)
 * - Cookie security: HTTPS detection, HttpOnly, SameSite=Strict
 * - CSRF token: 32-byte random hex string
 * 
 * Dependencies: None (core session functionality)
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

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