<?php
/**
 * Supermon-ng CSRF Protection Utilities
 * 
 * Provides comprehensive CSRF (Cross-Site Request Forgery) protection
 * functionality for Supermon-ng. Handles token generation, validation,
 * and form integration for secure web application operation.
 * 
 * Features:
 * - Secure CSRF token generation using cryptographically secure random bytes
 * - Token validation with timing-safe comparison
 * - Automatic form field generation for CSRF protection
 * - POST request validation and enforcement
 * - Session-based token storage and management
 * - Comprehensive error handling and user feedback
 * 
 * Security:
 * - 32-byte random token generation (256-bit security)
 * - Timing-safe token comparison to prevent timing attacks
 * - Session-based token storage for persistence
 * - Automatic token regeneration for new sessions
 * - HTML escaping for safe form field generation
 * - HTTP 403 response for validation failures
 * 
 * Usage:
 * - Include in forms: <?php echo csrf_token_field(); ?>
 * - Validate requests: require_csrf();
 * - Manual validation: validate_csrf_token($token);
 * - Generate tokens: generate_csrf_token();
 * 
 * Integration:
 * - Automatic session integration
 * - Form field generation for easy inclusion
 * - POST request validation enforcement
 * - Error handling with user-friendly messages
 * - HTTP status code management
 * 
 * Dependencies: session.inc (for session management)
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Generate a CSRF token
 * @return string The generated token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token
 * @param string $token The token to validate
 * @return bool True if valid, false otherwise
 */
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate a CSRF token input field for forms
 * @return string HTML input field with CSRF token
 */
function csrf_token_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate CSRF token from POST request
 * @return bool True if valid, false otherwise
 */
function validate_post_csrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true; // Only validate POST requests
    }
    
    $token = $_POST['csrf_token'] ?? '';
    return validate_csrf_token($token);
}

/**
 * Require CSRF validation and die if invalid
 */
function require_csrf() {
    if (!validate_post_csrf()) {
        http_response_code(403);
        die('CSRF token validation failed. Please refresh the page and try again.');
    }
}
?> 