<?php
/**
 * Supermon-ng Safe Initialization
 * 
 * This file provides safe initialization of the new performance and security features.
 * Include this file only if you want to enable the new features.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 */

/**
 * Initialize all new features safely
 * 
 * @param array $options Initialization options
 */
function init_supermon_features($options = []) 
{
    // Set default options
    $defaults = [
        'enable_caching' => true,
        'enable_error_handling' => true,
        'enable_security_monitoring' => true,
        'enable_ami_pooling' => true,
        'enable_config_management' => true,
        'debug_mode' => false,
        'cache_file' => '/tmp/supermon-ng-cache.json',
        'error_log' => '/tmp/supermon-ng-errors.log',
        'performance_log' => '/tmp/supermon-ng-performance.log',
        'rate_limit_file' => '/tmp/supermon-ng-rate-limit.json'
    ];
    
    $config = array_merge($defaults, $options);
    
    // Check if we can write to /tmp
    if (!is_writable('/tmp')) {
        error_log('Warning: /tmp is not writable. Some features may not work properly.');
        return false;
    }
    
    // Initialize caching if enabled
    if ($config['enable_caching'] && class_exists('CacheManager')) {
        try {
            CacheManager::init([
                'cache_file' => $config['cache_file'],
                'lifetime' => 300
            ]);
        } catch (Exception $e) {
            error_log('Warning: Failed to initialize caching: ' . $e->getMessage());
        }
    }
    
    // Initialize error handling if enabled
    if ($config['enable_error_handling'] && class_exists('ErrorHandler')) {
        try {
            ErrorHandler::init([
                'debug_mode' => $config['debug_mode'],
                'log_file' => $config['error_log'],
                'performance_log' => $config['performance_log']
            ]);
            register_shutdown_function([ErrorHandler::class, 'logRequestCompletion']);
        } catch (Exception $e) {
            error_log('Warning: Failed to initialize error handling: ' . $e->getMessage());
        }
    }
    
    // Initialize security monitoring if enabled
    if ($config['enable_security_monitoring'] && class_exists('RateLimiter')) {
        try {
            // Initialize security monitoring
            $alerts = SecurityMonitor::monitorRequest($_REQUEST);
            foreach ($alerts as $alert) {
                SecurityMonitor::logAlert($alert);
            }
            
            // Check rate limiting
            if (is_security_rate_limited()) {
                http_response_code(429);
                die('Too many requests. Please try again later.');
            }
            
            register_shutdown_function([RateLimiter::class, 'cleanup']);
        } catch (Exception $e) {
            error_log('Warning: Failed to initialize security monitoring: ' . $e->getMessage());
        }
    }
    
    // Initialize AMI pooling if enabled
    if ($config['enable_ami_pooling'] && class_exists('SimpleAmiClient')) {
        try {
            SimpleAmiClient::initPool();
            register_shutdown_function([SimpleAmiClient::class, 'cleanupPool']);
        } catch (Exception $e) {
            error_log('Warning: Failed to initialize AMI pooling: ' . $e->getMessage());
        }
    }
    
    // Initialize configuration management if enabled
    if ($config['enable_config_management'] && class_exists('Config')) {
        try {
            Config::init([
                'environment' => getenv('SUPERMON_ENV') ?: 'production',
                'config_path' => dirname(__DIR__) . '/user_files/'
            ]);
        } catch (Exception $e) {
            error_log('Warning: Failed to initialize configuration management: ' . $e->getMessage());
        }
    }
    
    return true;
}

/**
 * Check if new features are available
 * 
 * @return array Array of available features
 */
function check_feature_availability() 
{
    $features = [
        'caching' => class_exists('CacheManager'),
        'error_handling' => class_exists('ErrorHandler'),
        'security_monitoring' => class_exists('RateLimiter') && class_exists('SecurityMonitor'),
        'ami_pooling' => class_exists('SimpleAmiClient'),
        'config_management' => class_exists('Config')
    ];
    
    return $features;
}

/**
 * Get feature status
 * 
 * @return array Feature status information
 */
function get_feature_status() 
{
    $status = check_feature_availability();
    
    // Check file permissions
    $status['tmp_writable'] = is_writable('/tmp');
    $status['cache_file_writable'] = is_writable('/tmp/supermon-ng-cache.json') || is_writable('/tmp');
    $status['log_files_writable'] = is_writable('/tmp/supermon-ng-errors.log') || is_writable('/tmp');
    
    return $status;
}
?>

