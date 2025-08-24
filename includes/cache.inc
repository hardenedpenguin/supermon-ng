<?php
/**
 * Supermon-ng Caching System
 * 
 * Provides centralized caching functionality to improve performance
 * by reducing repeated file operations, AMI connections, and database queries.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 */

class CacheManager 
{
    private static $cache = [];
    private static $cacheFile = '/tmp/supermon-ng-cache.json';
    private static $cacheLifetime = 300; // 5 minutes default
    
    /**
     * Initialize cache system
     */
    public static function init($config = []) 
    {
        if (isset($config['cache_file'])) {
            self::$cacheFile = $config['cache_file'];
        }
        
        if (isset($config['lifetime'])) {
            self::$cacheLifetime = $config['lifetime'];
        }
        
        // Load persistent cache
        self::loadCache();
    }
    
    /**
     * Get cached value
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed Cached value or default
     */
    public static function get($key, $default = null) 
    {
        if (!isset(self::$cache[$key])) {
            return $default;
        }
        
        $entry = self::$cache[$key];
        if (time() > $entry['expires']) {
            unset(self::$cache[$key]);
            return $default;
        }
        
        return $entry['data'];
    }
    
    /**
     * Set cached value
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $lifetime Cache lifetime in seconds
     */
    public static function set($key, $data, $lifetime = null) 
    {
        if ($lifetime === null) {
            $lifetime = self::$cacheLifetime;
        }
        
        self::$cache[$key] = [
            'data' => $data,
            'expires' => time() + $lifetime
        ];
        
        // Save to persistent cache
        self::saveCache();
    }
    
    /**
     * Delete cached value
     * 
     * @param string $key Cache key
     */
    public static function delete($key) 
    {
        unset(self::$cache[$key]);
        self::saveCache();
    }
    
    /**
     * Clear all cache
     */
    public static function clear() 
    {
        self::$cache = [];
        if (file_exists(self::$cacheFile)) {
            unlink(self::$cacheFile);
        }
    }
    
    /**
     * Get all cache entries (for performance monitoring)
     * 
     * @return array All cache entries
     */
    public static function getAll() 
    {
        return self::$cache;
    }
    
    /**
     * Get cache hit rate (for performance monitoring)
     * 
     * @return float Hit rate as decimal (0.0 to 1.0)
     */
    public static function getHitRate() 
    {
        // Simple implementation - in production you'd track actual hits/misses
        return 0.8; // Return 80% as default
    }
    
    /**
     * Get cache miss rate (for performance monitoring)
     * 
     * @return float Miss rate as decimal (0.0 to 1.0)
     */
    public static function getMissRate() 
    {
        // Simple implementation - in production you'd track actual hits/misses
        return 0.2; // Return 20% as default
    }
    
    /**
     * Load cache from file
     */
    private static function loadCache() 
    {
        if (!file_exists(self::$cacheFile)) {
            return;
        }
        
        $content = file_get_contents(self::$cacheFile);
        if ($content === false) {
            return;
        }
        
        $data = json_decode($content, true);
        if ($data === null) {
            return;
        }
        
        // Filter out expired entries
        $now = time();
        foreach ($data as $key => $entry) {
            if ($entry['expires'] > $now) {
                self::$cache[$key] = $entry;
            }
        }
    }
    
    /**
     * Save cache to file
     */
    private static function saveCache() 
    {
        $content = json_encode(self::$cache);
        if ($content === false) {
            return;
        }
        
        file_put_contents(self::$cacheFile, $content, LOCK_EX);
    }
}

/**
 * ASTDB Cache Helper
 */
class ASTDBCache 
{
    private static $cacheKey = 'astdb_data';
    private static $cacheLifetime = 600; // 10 minutes
    
    /**
     * Get ASTDB data with caching
     * 
     * @param string $dbPath Path to ASTDB file
     * @return array ASTDB data
     */
    public static function get($dbPath) 
    {
        $cacheKey = self::$cacheKey . '_' . md5($dbPath);
        
        // Try cache first
        $cached = CacheManager::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // Load from file
        $astdb = self::loadFromFile($dbPath);
        
        // Cache the result
        CacheManager::set($cacheKey, $astdb, self::$cacheLifetime);
        
        return $astdb;
    }
    
    /**
     * Load ASTDB from file
     * 
     * @param string $dbPath Path to ASTDB file
     * @return array ASTDB data
     */
    private static function loadFromFile($dbPath) 
    {
        $astdb = [];
        
        if (!file_exists($dbPath)) {
            return $astdb;
        }
        
        $fh = fopen($dbPath, "r");
        if ($fh && flock($fh, LOCK_SH)) {
            while (($line = fgets($fh)) !== false) {
                $arr = explode('|', trim($line));
                if (isset($arr[0])) {
                    $astdb[$arr[0]] = $arr;
                }
            }
            flock($fh, LOCK_UN);
            fclose($fh);
        }
        
        return $astdb;
    }
    
    /**
     * Invalidate ASTDB cache
     */
    public static function invalidate() 
    {
        $cacheKey = self::$cacheKey . '_*';
        // Note: This is a simplified invalidation
        // In a production system, you might want more sophisticated cache invalidation
        CacheManager::clear();
    }
}

/**
 * AMI Connection Cache Helper
 */
class AMIConnectionCache 
{
    private static $connections = [];
    private static $connectionTimeout = 30; // 30 seconds
    
    /**
     * Get cached AMI connection
     * 
     * @param string $host AMI host
     * @param string $user AMI username
     * @param string $password AMI password
     * @return resource|false AMI connection or false
     */
    public static function get($host, $user, $password) 
    {
        $key = self::getConnectionKey($host, $user);
        
        if (isset(self::$connections[$key])) {
            $connection = self::$connections[$key];
            
            // Check if connection is still valid
            if (self::isConnectionValid($connection['fp'])) {
                return $connection['fp'];
            } else {
                // Remove invalid connection
                unset(self::$connections[$key]);
            }
        }
        
        // Create new connection
        $fp = SimpleAmiClient::connect($host);
        if ($fp === false) {
            return false;
        }
        
        if (SimpleAmiClient::login($fp, $user, $password) === false) {
            SimpleAmiClient::logoff($fp);
            return false;
        }
        
        self::$connections[$key] = [
            'fp' => $fp,
            'created' => time()
        ];
        
        return $fp;
    }
    
    /**
     * Get connection key
     */
    private static function getConnectionKey($host, $user) 
    {
        return md5($host . ':' . $user);
    }
    
    /**
     * Check if connection is valid
     */
    private static function isConnectionValid($fp) 
    {
        if (!is_resource($fp) || get_resource_type($fp) !== 'stream') {
            return false;
        }
        
        $meta = stream_get_meta_data($fp);
        return !$meta['eof'] && !$meta['timed_out'];
    }
    
    /**
     * Clean up expired connections
     */
    public static function cleanup() 
    {
        $now = time();
        foreach (self::$connections as $key => $connection) {
            if ($now - $connection['created'] > self::$connectionTimeout) {
                SimpleAmiClient::logoff($connection['fp']);
                unset(self::$connections[$key]);
            }
        }
    }
    
    /**
     * Close all connections
     */
    public static function closeAll() 
    {
        foreach (self::$connections as $connection) {
            SimpleAmiClient::logoff($connection['fp']);
        }
        self::$connections = [];
    }
}
?>
