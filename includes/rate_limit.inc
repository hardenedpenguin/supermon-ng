<?php
/**
 * Supermon-ng Rate Limiting Utilities
 * 
 * Provides comprehensive rate limiting functionality for Supermon-ng.
 * Handles IP-based rate limiting, attempt tracking, and security
 * enforcement to prevent brute force attacks and abuse.
 * 
 * Features:
 * - IP-based rate limiting with configurable thresholds
 * - Time-window based attempt tracking
 * - Automatic cleanup of expired attempts
 * - File-based storage for persistence across requests
 * - Configurable maximum attempts and time windows
 * - Remaining attempts calculation and reporting
 * - Rate limit clearing functionality
 * 
 * Security:
 * - IP address tracking for rate limiting
 * - Time-window based attempt expiration
 * - File locking for concurrent access safety
 * - JSON-based attempt storage with validation
 * - Automatic cleanup of stale data
 * - Configurable security thresholds
 * 
 * Usage:
 * - Check rate limit: is_rate_limited('login', 5, 300);
 * - Get remaining attempts: get_remaining_attempts('login');
 * - Clear rate limit: clear_rate_limit('login');
 * - Custom actions: is_rate_limited('command', 10, 600);
 * 
 * Configuration:
 * - Default max attempts: 5 per time window
 * - Default time window: 300 seconds (5 minutes)
 * - Storage location: System temporary directory
 * - File format: JSON with timestamps
 * - Lock mechanism: File locking for concurrency
 * 
 * File Storage:
 * - Location: sys_get_temp_dir() / rate_limit_{action}_{ip}.txt
 * - Format: JSON array of timestamps
 * - Locking: LOCK_EX for concurrent access safety
 * - Cleanup: Automatic removal of expired timestamps
 * 
 * Dependencies: None (core rate limiting functionality)
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Check if an IP is rate limited
 * @param string $action The action being rate limited (e.g., 'login', 'command')
 * @param int $max_attempts Maximum attempts allowed
 * @param int $time_window Time window in seconds
 * @return bool True if rate limited, false otherwise
 */
function is_rate_limited(string $action, int $max_attempts = 5, int $time_window = 300): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_limit_file = sys_get_temp_dir() . "/rate_limit_{$action}_{$ip}.txt";
    
    $attempts = [];
    if (file_exists($rate_limit_file)) {
        $data = file_get_contents($rate_limit_file);
        if ($data !== false) {
            $attempts = json_decode($data, true) ?: [];
        }
    }
    
    // Remove old attempts outside time window
    $current_time = time();
    $attempts = array_filter($attempts, function($timestamp) use ($current_time, $time_window) {
        return ($current_time - $timestamp) < $time_window;
    });
    
    // Check if rate limited
    if (count($attempts) >= $max_attempts) {
        return true;
    }
    
    // Add current attempt
    $attempts[] = $current_time;
    file_put_contents($rate_limit_file, json_encode($attempts), LOCK_EX);
    
    return false;
}

/**
 * Get remaining attempts for an IP
 * @param string $action The action being checked
 * @param int $max_attempts Maximum attempts allowed
 * @param int $time_window Time window in seconds
 * @return int Remaining attempts
 */
function get_remaining_attempts(string $action, int $max_attempts = 5, int $time_window = 300): int {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_limit_file = sys_get_temp_dir() . "/rate_limit_{$action}_{$ip}.txt";
    
    if (!file_exists($rate_limit_file)) {
        return $max_attempts;
    }
    
    $data = file_get_contents($rate_limit_file);
    if ($data === false) {
        return $max_attempts;
    }
    
    $attempts = json_decode($data, true) ?: [];
    
    // Remove old attempts outside time window
    $current_time = time();
    $attempts = array_filter($attempts, function($timestamp) use ($current_time, $time_window) {
        return ($current_time - $timestamp) < $time_window;
    });
    
    return max(0, $max_attempts - count($attempts));
}

/**
 * Clear rate limit for an IP
 * @param string $action The action to clear
 */
function clear_rate_limit(string $action): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_limit_file = sys_get_temp_dir() . "/rate_limit_{$action}_{$ip}.txt";
    
    if (file_exists($rate_limit_file)) {
        unlink($rate_limit_file);
    }
}
?> 