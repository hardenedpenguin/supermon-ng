<?php

declare(strict_types=1);

namespace SupermonNg\Application\Services;

/**
 * Validation Service
 * 
 * Modernized service for input validation and sanitization
 */
class ValidationService
{
    /**
     * Validate a node ID
     */
    public static function validateNodeId(mixed $input): string|false
    {
        if (empty($input)) {
            return false;
        }
        
        $nodeId = trim((string)$input);
        
        // Node IDs should be numeric and reasonable length
        if (!preg_match('/^\d{1,8}$/', $nodeId)) {
            return false;
        }
        
        return $nodeId;
    }
    
    /**
     * Validate an IP address
     */
    public static function validateIpAddress(mixed $input): string|false
    {
        if (empty($input)) {
            return false;
        }
        
        $ip = trim((string)$input);
        
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        
        return false;
    }
    
    /**
     * Validate a hostname
     */
    public static function validateHostname(mixed $input): string|false
    {
        if (empty($input)) {
            return false;
        }
        
        $hostname = trim((string)$input);
        
        // Basic hostname validation
        if (preg_match('/^[a-zA-Z0-9.-]+$/', $hostname) && strlen($hostname) <= 255) {
            return $hostname;
        }
        
        return false;
    }
    
    /**
     * Validate a port number
     */
    public static function validatePort(mixed $input): int|false
    {
        if (empty($input) && $input !== '0') {
            return false;
        }
        
        $port = (int)$input;
        
        if ($port >= 1 && $port <= 65535) {
            return $port;
        }
        
        return false;
    }
    
    /**
     * Sanitize user input
     */
    public static function sanitizeInput(mixed $input): string
    {
        if ($input === null) {
            return '';
        }
        
        $sanitized = trim((string)$input);
        $sanitized = strip_tags($sanitized);
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
        
        return $sanitized;
    }
    
    /**
     * Validate email address
     */
    public static function validateEmail(mixed $input): string|false
    {
        if (empty($input)) {
            return false;
        }
        
        $email = trim((string)$input);
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        
        return false;
    }
    
    /**
     * Validate username (alphanumeric + underscore)
     */
    public static function validateUsername(mixed $input): string|false
    {
        if (empty($input)) {
            return false;
        }
        
        $username = trim((string)$input);
        
        if (preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
            return $username;
        }
        
        return false;
    }
    
    /**
     * Validate and sanitize a filename
     */
    public static function validateFilename(mixed $input): string|false
    {
        if (empty($input)) {
            return false;
        }
        
        $filename = trim((string)$input);
        
        // Remove path traversal attempts and dangerous characters
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        if (strlen($filename) > 0 && strlen($filename) <= 255) {
            return $filename;
        }
        
        return false;
    }
}
