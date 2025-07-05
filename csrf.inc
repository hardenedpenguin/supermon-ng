<?php
/**
 * CSRF Protection Utilities
 * Provides functions to generate and validate CSRF tokens
 */

/**
 * Generate a CSRF token
 * @return string The generated token
 */
function generate_csrf_token(): string {
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
function validate_csrf_token(string $token): bool {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate a CSRF token input field for forms
 * @return string HTML input field with CSRF token
 */
function csrf_token_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate CSRF token from POST request
 * @return bool True if valid, false otherwise
 */
function validate_post_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true; // Only validate POST requests
    }
    
    $token = $_POST['csrf_token'] ?? '';
    return validate_csrf_token($token);
}

/**
 * Require CSRF validation and die if invalid
 */
function require_csrf(): void {
    if (!validate_post_csrf()) {
        http_response_code(403);
        die('CSRF token validation failed. Please refresh the page and try again.');
    }
}
?> 