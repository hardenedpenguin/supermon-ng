<?php
/**
 * Rate Limiting Utilities
 * Provides functions to implement rate limiting for security
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