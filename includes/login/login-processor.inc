<?php
/**
 * Supermon-ng Login Processor
 * 
 * Handles login form submission, authentication, and session management.
 * Processes both AJAX and form-based login requests with rate limiting.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/login/auth-functions.inc";

/**
 * Process login form submission
 * 
 * Handles authentication, rate limiting, session management, and response generation
 * for both AJAX and form-based login requests.
 * 
 * @return array Response data for AJAX requests or redirects for form requests
 */
function processLogin(): array {
    // Initialize session if not already done
    if (!isset($_SESSION['sm61loggedin'])) {
        $_SESSION['sm61loggedin'] = false;
    }

    // Check rate limiting for login attempts
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (is_rate_limited('login', 5, 900)) { // 5 attempts per 15 minutes
            http_response_code(429);
            $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
            
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Too many login attempts. Please wait 15 minutes before trying again.']);
            } else {
                die("Too many login attempts. Please wait 15 minutes before trying again.");
            }
            exit;
        }
    }

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
        return ['error' => $error_message];
    }

    // Attempt authentication
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
            exit;
        } else {
            // For form submissions, redirect to main page
            header('Location: index.php');
            exit;
        }
    } else {
        logUser($user, false);
        $remaining_attempts = get_remaining_attempts('login', 5, 900);
        $error_message = "Login failed. Remaining attempts: {$remaining_attempts}";
        
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit;
        }
        return ['error' => $error_message];
    }
}
