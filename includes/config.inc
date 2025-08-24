<?php
/**
 * Configuration Management System
 * 
 * Provides centralized configuration loading and management for Supermon-ng.
 * Simplifies configuration access and provides default value handling.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
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
    private static $environment = 'production';
    private static $configPath = '';
    
    /**
     * Initialize configuration system
     * 
     * @param array $options Configuration options
     */
    public static function init($options = []) 
    {
        if (isset($options['environment'])) {
            self::$environment = $options['environment'];
        }
        
        if (isset($options['config_path'])) {
            self::$configPath = $options['config_path'];
        } else {
            self::$configPath = dirname(__DIR__) . '/user_files/';
        }
        
        // Load environment-specific configuration
        self::loadEnvironmentConfig();
    }
    
    /**
     * Load environment-specific configuration
     */
    private static function loadEnvironmentConfig() 
    {
        $envFile = self::$configPath . 'config.' . self::$environment . '.ini';
        if (file_exists($envFile)) {
            self::load($envFile, 'environment');
        }
    }
    
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
            if (class_exists('ErrorHandler') && method_exists('ErrorHandler', 'logError')) {
                ErrorHandler::logError("Configuration file not found", ['file' => $file]);
            }
            return false;
        }
        
        if (in_array($file, self::$loadedFiles)) {
            return true; // Already loaded
        }
        
        $startTime = microtime(true);
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
                if (class_exists('ErrorHandler') && method_exists('ErrorHandler', 'logError')) {
                    ErrorHandler::logError("Unsupported configuration file type", ['file' => $file]);
                }
                return false;
        }
        
        if ($data === false || $data === null) {
            if (class_exists('ErrorHandler') && method_exists('ErrorHandler', 'logError')) {
                ErrorHandler::logError("Failed to parse configuration file", ['file' => $file]);
            }
            return false;
        }
        
        if ($section) {
            self::$config[$section] = array_merge(self::$config[$section] ?? [], $data);
        } else {
            self::$config = array_merge(self::$config, $data);
        }
        
        self::$loadedFiles[] = $file;
        
        $duration = microtime(true) - $startTime;
        if (class_exists('ErrorHandler') && method_exists('ErrorHandler', 'logFileOperation')) {
            ErrorHandler::logFileOperation('config_load', $file, $duration, true);
        }
        
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
     * @return bool True if key exists
     */
    public static function has($key) 
    {
        return self::getNestedValue(self::$config, $key, null) !== null;
    }
    
    /**
     * Get all configuration data
     * 
     * @return array All configuration data
     */
    public static function all() 
    {
        return self::$config;
    }
    
    /**
     * Get nested value from array using dot notation
     * 
     * @param array $array Array to search
     * @param string $key Dot notation key
     * @param mixed $default Default value
     * @return mixed Value or default
     */
    private static function getNestedValue($array, $key, $default = null) 
    {
        if (strpos($key, '.') === false) {
            return $array[$key] ?? $default;
        }
        
        $keys = explode('.', $key);
        $current = $array;
        
        foreach ($keys as $k) {
            if (!is_array($current) || !isset($current[$k])) {
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
    
    /**
     * Validate configuration
     * 
     * @param array $rules Validation rules
     * @return array Validation errors
     */
    public static function validate($rules) 
    {
        $errors = [];
        
        foreach ($rules as $key => $rule) {
            $value = self::get($key);
            
            if (isset($rule['required']) && $rule['required'] && $value === null) {
                $errors[$key] = "Configuration key '$key' is required";
                continue;
            }
            
            if ($value === null) {
                continue; // Skip validation for null values unless required
            }
            
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'string':
                        if (!is_string($value)) {
                            $errors[$key] = "Configuration key '$key' must be a string";
                        }
                        break;
                    case 'int':
                        if (!is_numeric($value) || (int)$value != $value) {
                            $errors[$key] = "Configuration key '$key' must be an integer";
                        }
                        break;
                    case 'bool':
                        if (!is_bool($value) && !in_array($value, ['true', 'false', '1', '0'], true)) {
                            $errors[$key] = "Configuration key '$key' must be a boolean";
                        }
                        break;
                    case 'array':
                        if (!is_array($value)) {
                            $errors[$key] = "Configuration key '$key' must be an array";
                        }
                        break;
                }
            }
            
            if (isset($rule['min']) && is_numeric($value) && $value < $rule['min']) {
                $errors[$key] = "Configuration key '$key' must be at least {$rule['min']}";
            }
            
            if (isset($rule['max']) && is_numeric($value) && $value > $rule['max']) {
                $errors[$key] = "Configuration key '$key' must be at most {$rule['max']}";
            }
            
            if (isset($rule['min_length']) && is_string($value) && strlen($value) < $rule['min_length']) {
                $errors[$key] = "Configuration key '$key' must be at least {$rule['min_length']} characters";
            }
            
            if (isset($rule['max_length']) && is_string($value) && strlen($value) > $rule['max_length']) {
                $errors[$key] = "Configuration key '$key' must be at most {$rule['max_length']} characters";
            }
            
            if (isset($rule['pattern']) && is_string($value) && !preg_match($rule['pattern'], $value)) {
                $errors[$key] = "Configuration key '$key' does not match required pattern";
            }
            
            if (isset($rule['allowed']) && !in_array($value, $rule['allowed'])) {
                $errors[$key] = "Configuration key '$key' must be one of: " . implode(', ', $rule['allowed']);
            }
        }
        
        return $errors;
    }
    
    /**
     * Get environment-specific configuration
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed Configuration value
     */
    public static function getEnv($key, $default = null) 
    {
        $envKey = strtoupper($key);
        return $_ENV[$envKey] ?? $_SERVER[$envKey] ?? getenv($envKey) ?: $default;
    }
    
    /**
     * Load user configuration with caching
     * 
     * @param string $user User identifier
     * @return array|false User configuration or false on failure
     */
    public static function loadUserConfig($user) 
    {
        $cacheKey = "user_config_{$user}";
        
        // Try cache first
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        $configFile = self::$configPath . "allmon_{$user}.ini";
        
        if (!file_exists($configFile)) {
            return false;
        }
        
        $config = parse_ini_file($configFile, true);
        if ($config === false) {
            return false;
        }
        
        // Cache the result
        self::$cache[$cacheKey] = $config;
        
        return $config;
    }
    
    /**
     * Clear configuration cache
     * 
     * @param string $key Optional specific cache key to clear
     */
    public static function clearCache($key = null) 
    {
        if ($key === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$key]);
        }
    }
    
    /**
     * Get configuration statistics
     * 
     * @return array Configuration statistics
     */
    public static function getStats() 
    {
        return [
            'loaded_files' => count(self::$loadedFiles),
            'cache_entries' => count(self::$cache),
            'config_keys' => count(self::$config),
            'environment' => self::$environment,
            'config_path' => self::$configPath
        ];
    }
}

/**
 * Configuration Helper Functions
 */

/**
 * Get configuration value with fallback
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
 * Set configuration value
 * 
 * @param string $key Configuration key
 * @param mixed $value Value to set
 */
function config_set($key, $value) 
{
    Config::set($key, $value);
}

/**
 * Check if configuration key exists
 * 
 * @param string $key Configuration key
 * @return bool True if key exists
 */
function config_has($key) 
{
    return Config::has($key);
}

/**
 * Get environment variable with fallback
 * 
 * @param string $key Environment key
 * @param mixed $default Default value
 * @return mixed Environment value
 */
function env($key, $default = null) 
{
    return Config::getEnv($key, $default);
}

// Note: Configuration system initialization is now optional
// To enable, call: Config::init();
?>
