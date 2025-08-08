<?php
/**
 * Configuration Management System
 * 
 * Provides centralized configuration loading and management for Supermon-ng.
 * Simplifies configuration access and provides default value handling.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 */

/**
 * Configuration Manager Class
 * 
 * Handles loading, caching, and accessing configuration values from various
 * configuration files in a standardized way.
 */
class Config 
{
    private static $config = [];
    private static $loadedFiles = [];
    private static $cache = [];
    
    /**
     * Load configuration from a file
     * 
     * @param string $file Configuration file path
     * @param string $section Optional section name for namespacing
     * @return bool True on success, false on failure
     */
    public static function load($file, $section = null) 
    {
        if (!file_exists($file)) {
            ErrorHandler::logError("Configuration file not found", ['file' => $file]);
            return false;
        }
        
        if (in_array($file, self::$loadedFiles)) {
            return true; // Already loaded
        }
        
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        
        switch ($extension) {
            case 'ini':
                $data = parse_ini_file($file, true);
                break;
                
            case 'inc':
            case 'php':
                // Capture PHP configuration variables
                $beforeVars = get_defined_vars();
                include $file;
                $afterVars = get_defined_vars();
                $data = array_diff_key($afterVars, $beforeVars);
                // Remove common PHP variables
                unset($data['beforeVars'], $data['afterVars']);
                break;
                
            case 'json':
                $content = file_get_contents($file);
                $data = json_decode($content, true);
                break;
                
            default:
                ErrorHandler::logError("Unsupported configuration file type", ['file' => $file]);
                return false;
        }
        
        if ($data === false || $data === null) {
            ErrorHandler::logError("Failed to parse configuration file", ['file' => $file]);
            return false;
        }
        
        if ($section) {
            self::$config[$section] = array_merge(self::$config[$section] ?? [], $data);
        } else {
            self::$config = array_merge(self::$config, $data);
        }
        
        self::$loadedFiles[] = $file;
        
        ErrorHandler::logError("Configuration loaded", ['file' => $file, 'section' => $section], 'INFO');
        return true;
    }
    
    /**
     * Get configuration value
     * 
     * @param string $key Configuration key (supports dot notation)
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public static function get($key, $default = null) 
    {
        return self::getNestedValue(self::$config, $key, $default);
    }
    
    /**
     * Set configuration value
     * 
     * @param string $key Configuration key (supports dot notation)
     * @param mixed $value Value to set
     */
    public static function set($key, $value) 
    {
        self::setNestedValue(self::$config, $key, $value);
    }
    
    /**
     * Check if configuration key exists
     * 
     * @param string $key Configuration key
     * @return bool True if exists, false otherwise
     */
    public static function has($key) 
    {
        return self::getNestedValue(self::$config, $key, '__NOT_FOUND__') !== '__NOT_FOUND__';
    }
    
    /**
     * Get all configuration values
     * 
     * @return array All configuration data
     */
    public static function all() 
    {
        return self::$config;
    }
    
    /**
     * Get configuration section
     * 
     * @param string $section Section name
     * @return array Section data or empty array if not found
     */
    public static function section($section) 
    {
        return self::$config[$section] ?? [];
    }
    
    /**
     * Load user-specific configuration
     * 
     * @param string $username Username
     * @return bool True on success, false on failure
     */
    public static function loadUserConfig($username) 
    {
        $userFiles = [
            "user_files/{$username}-allmon.ini",
            "user_files/{$username}-favorites.ini",
            "user_files/{$username}-controlpanel.ini"
        ];
        
        $loaded = false;
        foreach ($userFiles as $file) {
            if (file_exists($file)) {
                if (self::load($file, "user_{$username}")) {
                    $loaded = true;
                }
            }
        }
        
        return $loaded;
    }
    
    /**
     * Get user-specific configuration
     * 
     * @param string $username Username
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed Configuration value
     */
    public static function getUserConfig($username, $key, $default = null) 
    {
        return self::get("user_{$username}.{$key}", $default);
    }
    
    /**
     * Load node configuration
     * 
     * @param string $username Username
     * @return array Node configuration
     */
    public static function getNodeConfig($username) 
    {
        $cacheKey = "nodes_{$username}";
        
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        $iniFile = get_ini_name($username);
        if (!$iniFile || !file_exists($iniFile)) {
            return [];
        }
        
        $config = parse_ini_file($iniFile, true);
        if (!$config) {
            return [];
        }
        
        self::$cache[$cacheKey] = $config;
        return $config;
    }
    
    /**
     * Get specific node configuration
     * 
     * @param string $username Username
     * @param string $nodeId Node ID
     * @return array|false Node configuration or false if not found
     */
    public static function getNodeById($username, $nodeId) 
    {
        $nodes = self::getNodeConfig($username);
        return $nodes[$nodeId] ?? false;
    }
    
    /**
     * Reload configuration from files
     */
    public static function reload() 
    {
        $files = self::$loadedFiles;
        self::$config = [];
        self::$loadedFiles = [];
        self::$cache = [];
        
        foreach ($files as $file) {
            self::load($file);
        }
    }
    
    /**
     * Get environment-based configuration
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed Configuration value
     */
    public static function env($key, $default = null) 
    {
        $envValue = getenv($key);
        if ($envValue !== false) {
            return $envValue;
        }
        
        return $_ENV[$key] ?? $default;
    }
    
    /**
     * Get nested value from array using dot notation
     * 
     * @param array $array Array to search
     * @param string $key Dot notation key
     * @param mixed $default Default value
     * @return mixed Found value or default
     */
    private static function getNestedValue($array, $key, $default = null) 
    {
        if (strpos($key, '.') === false) {
            return $array[$key] ?? $default;
        }
        
        $keys = explode('.', $key);
        $current = $array;
        
        foreach ($keys as $k) {
            if (!is_array($current) || !array_key_exists($k, $current)) {
                return $default;
            }
            $current = $current[$k];
        }
        
        return $current;
    }
    
    /**
     * Set nested value in array using dot notation
     * 
     * @param array &$array Array to modify
     * @param string $key Dot notation key
     * @param mixed $value Value to set
     */
    private static function setNestedValue(&$array, $key, $value) 
    {
        if (strpos($key, '.') === false) {
            $array[$key] = $value;
            return;
        }
        
        $keys = explode('.', $key);
        $current = &$array;
        
        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }
        
        $current = $value;
    }
}

/**
 * Configuration helper functions for backward compatibility and convenience
 */

/**
 * Get configuration value (shorthand)
 * 
 * @param string $key Configuration key
 * @param mixed $default Default value
 * @return mixed Configuration value
 */
function config($key, $default = null) 
{
    return Config::get($key, $default);
}

/**
 * Load common configuration files
 */
function loadCommonConfig() 
{
    Config::load('user_files/global.inc');
    Config::load('includes/common.inc');
    
    // Load user-specific config if logged in
    if (isset($_SESSION['user'])) {
        Config::loadUserConfig($_SESSION['user']);
    }
}

// Auto-load common configuration if not in CLI mode
if (php_sapi_name() !== 'cli') {
    loadCommonConfig();
}
