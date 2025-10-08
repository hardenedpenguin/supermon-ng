<?php

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Exception;

/**
 * Session Optimization Service
 * 
 * Provides intelligent session management with caching,
 * security enhancements, and performance monitoring.
 */
class SessionOptimizationService
{
    private LoggerInterface $logger;
    private CacheInterface $cache;
    
    // Session cache for frequently accessed data
    private static array $sessionCache = [];
    
    // Performance tracking
    private static array $performanceStats = [
        'sessions_created' => 0,
        'sessions_accessed' => 0,
        'sessions_destroyed' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'authentication_attempts' => 0,
        'authentication_successes' => 0,
        'authentication_failures' => 0,
        'session_timeouts' => 0,
        'total_session_time' => 0,
        'total_auth_time' => 0
    ];

    private int $sessionTimeout;
    private int $maxInactiveTime;
    private bool $enableSessionCaching;

    public function __construct(LoggerInterface $logger, CacheInterface $cache, int $sessionTimeout = 3600, int $maxInactiveTime = 1800)
    {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->sessionTimeout = $sessionTimeout;
        $this->maxInactiveTime = $maxInactiveTime;
        $this->enableSessionCaching = true;
        
        $this->initializeSession();
    }

    /**
     * Initialize optimized session
     */
    private function initializeSession(): void
    {
        // Set secure session parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', $this->sessionTimeout);
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            self::$performanceStats['sessions_created']++;
        }
        
        $this->logger->debug('Session optimized and initialized', [
            'session_id' => session_id(),
            'session_timeout' => $this->sessionTimeout,
            'max_inactive_time' => $this->maxInactiveTime
        ]);
    }

    /**
     * Get session data with caching
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $startTime = microtime(true);
        $sessionId = session_id();
        
        if (empty($sessionId)) {
            return $default;
        }
        
        // Check cache first
        if ($this->enableSessionCaching && isset(self::$sessionCache[$sessionId][$key])) {
            self::$performanceStats['cache_hits']++;
            self::$performanceStats['total_session_time'] += microtime(true) - $startTime;
            
            $this->logger->debug('Session cache hit', [
                'key' => $key,
                'session_id' => substr($sessionId, 0, 8) . '...',
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);
            
            return self::$sessionCache[$sessionId][$key];
        }
        
        self::$performanceStats['cache_misses']++;
        
        // Get from session
        $value = $_SESSION[$key] ?? $default;
        
        // Cache the value
        if ($this->enableSessionCaching) {
            if (!isset(self::$sessionCache[$sessionId])) {
                self::$sessionCache[$sessionId] = [];
            }
            self::$sessionCache[$sessionId][$key] = $value;
        }
        
        self::$performanceStats['sessions_accessed']++;
        self::$performanceStats['total_session_time'] += microtime(true) - $startTime;
        
        return $value;
    }

    /**
     * Set session data with caching
     */
    public function set(string $key, mixed $value): void
    {
        $startTime = microtime(true);
        $sessionId = session_id();
        
        if (empty($sessionId)) {
            return;
        }
        
        // Set in session
        $_SESSION[$key] = $value;
        
        // Update cache
        if ($this->enableSessionCaching) {
            if (!isset(self::$sessionCache[$sessionId])) {
                self::$sessionCache[$sessionId] = [];
            }
            self::$sessionCache[$sessionId][$key] = $value;
        }
        
        self::$performanceStats['sessions_accessed']++;
        self::$performanceStats['total_session_time'] += microtime(true) - $startTime;
        
        $this->logger->debug('Session data set', [
            'key' => $key,
            'session_id' => substr($sessionId, 0, 8) . '...',
            'cached' => $this->enableSessionCaching
        ]);
    }

    /**
     * Check if session data exists
     */
    public function has(string $key): bool
    {
        $sessionId = session_id();
        
        if (empty($sessionId)) {
            return false;
        }
        
        // Check cache first
        if ($this->enableSessionCaching && isset(self::$sessionCache[$sessionId][$key])) {
            return true;
        }
        
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session data
     */
    public function remove(string $key): void
    {
        $sessionId = session_id();
        
        if (empty($sessionId)) {
            return;
        }
        
        // Remove from session
        unset($_SESSION[$key]);
        
        // Remove from cache
        if ($this->enableSessionCaching && isset(self::$sessionCache[$sessionId][$key])) {
            unset(self::$sessionCache[$sessionId][$key]);
        }
        
        $this->logger->debug('Session data removed', [
            'key' => $key,
            'session_id' => substr($sessionId, 0, 8) . '...'
        ]);
    }

    /**
     * Authenticate user with performance tracking
     */
    public function authenticate(string $username, string $password): bool
    {
        $startTime = microtime(true);
        self::$performanceStats['authentication_attempts']++;
        
        try {
            // Include authentication files using optimized service
            $configService = new \SupermonNg\Services\ConfigurationCacheService($this->logger);
            $userFilesPath = $configService->getConfig('USERFILES', 'user_files');
            
            // Load authentication files
            $authUsersFile = $userFilesPath . '/authusers.inc';
            $authIniFile = $userFilesPath . '/authini.inc';
            
            if (!file_exists($authUsersFile) || !file_exists($authIniFile)) {
                $this->logger->warning('Authentication files not found', [
                    'auth_users_file' => $authUsersFile,
                    'auth_ini_file' => $authIniFile
                ]);
                return false;
            }
            
            // Load user data
            $users = [];
            if (file_exists($authUsersFile)) {
                include $authUsersFile;
            }
            
            // Check credentials
            $authenticated = false;
            if (isset($users[$username])) {
                $storedHash = $users[$username];
                $authenticated = password_verify($password, $storedHash);
            }
            
            $duration = microtime(true) - $startTime;
            self::$performanceStats['total_auth_time'] += $duration;
            
            if ($authenticated) {
                self::$performanceStats['authentication_successes']++;
                
                // Set session data
                $this->set('user', $username);
                $this->set('authenticated', true);
                $this->set('login_time', time());
                $this->set('last_activity', time());
                
                $this->logger->info('User authenticated successfully', [
                    'username' => $username,
                    'auth_time_ms' => round($duration * 1000, 2)
                ]);
            } else {
                self::$performanceStats['authentication_failures']++;
                
                $this->logger->warning('Authentication failed', [
                    'username' => $username,
                    'auth_time_ms' => round($duration * 1000, 2)
                ]);
            }
            
            return $authenticated;
            
        } catch (Exception $e) {
            self::$performanceStats['authentication_failures']++;
            self::$performanceStats['total_auth_time'] += microtime(true) - $startTime;
            
            $this->logger->error('Authentication error', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        $authenticated = $this->get('authenticated', false);
        $lastActivity = $this->get('last_activity', 0);
        
        // Check for timeout
        if ($lastActivity > 0 && (time() - $lastActivity) > $this->maxInactiveTime) {
            $this->destroySession();
            self::$performanceStats['session_timeouts']++;
            
            $this->logger->info('Session timed out due to inactivity', [
                'last_activity' => $lastActivity,
                'timeout_after' => $this->maxInactiveTime
            ]);
            
            return false;
        }
        
        // Update last activity
        if ($authenticated) {
            $this->set('last_activity', time());
        }
        
        return $authenticated;
    }

    /**
     * Get current user
     */
    public function getCurrentUser(): ?string
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return $this->get('user');
    }

    /**
     * Destroy session and clear cache
     */
    public function destroySession(): void
    {
        $sessionId = session_id();
        
        if (!empty($sessionId)) {
            // Clear cache
            if (isset(self::$sessionCache[$sessionId])) {
                unset(self::$sessionCache[$sessionId]);
            }
            
            // Destroy session
            session_destroy();
            self::$performanceStats['sessions_destroyed']++;
            
            $this->logger->info('Session destroyed', [
                'session_id' => substr($sessionId, 0, 8) . '...'
            ]);
        }
    }

    /**
     * Regenerate session ID for security
     */
    public function regenerateSessionId(): void
    {
        $oldSessionId = session_id();
        
        session_regenerate_id(true);
        
        $newSessionId = session_id();
        
        // Update cache with new session ID
        if ($this->enableSessionCaching && !empty($oldSessionId) && isset(self::$sessionCache[$oldSessionId])) {
            self::$sessionCache[$newSessionId] = self::$sessionCache[$oldSessionId];
            unset(self::$sessionCache[$oldSessionId]);
        }
        
        $this->logger->debug('Session ID regenerated', [
            'old_session_id' => substr($oldSessionId, 0, 8) . '...',
            'new_session_id' => substr($newSessionId, 0, 8) . '...'
        ]);
    }

    /**
     * Get session performance statistics
     */
    public function getPerformanceStats(): array
    {
        $stats = self::$performanceStats;
        
        // Calculate derived metrics
        $stats['cache_hit_ratio'] = ($stats['cache_hits'] + $stats['cache_misses']) > 0 
            ? round(($stats['cache_hits'] / ($stats['cache_hits'] + $stats['cache_misses'])) * 100, 2)
            : 0;
        
        $stats['authentication_success_rate'] = $stats['authentication_attempts'] > 0 
            ? round(($stats['authentication_successes'] / $stats['authentication_attempts']) * 100, 2)
            : 0;
        
        $stats['average_session_time'] = $stats['sessions_accessed'] > 0 
            ? round(($stats['total_session_time'] / $stats['sessions_accessed']) * 1000, 2)
            : 0;
        
        $stats['average_auth_time'] = $stats['authentication_attempts'] > 0 
            ? round(($stats['total_auth_time'] / $stats['authentication_attempts']) * 1000, 2)
            : 0;
        
        $stats['active_sessions_count'] = count(self::$sessionCache);
        
        // Add session configuration
        $stats['session_config'] = [
            'timeout' => $this->sessionTimeout,
            'max_inactive_time' => $this->maxInactiveTime,
            'caching_enabled' => $this->enableSessionCaching,
            'secure_cookies' => ini_get('session.cookie_secure'),
            'httponly_cookies' => ini_get('session.cookie_httponly'),
            'strict_mode' => ini_get('session.use_strict_mode')
        ];
        
        return $stats;
    }

    /**
     * Clean up expired session cache entries
     */
    public function cleanupExpiredSessions(): int
    {
        $cleanedCount = 0;
        $currentTime = time();
        
        foreach (self::$sessionCache as $sessionId => $data) {
            $lastActivity = $data['last_activity'] ?? 0;
            
            if ($lastActivity > 0 && ($currentTime - $lastActivity) > $this->maxInactiveTime) {
                unset(self::$sessionCache[$sessionId]);
                $cleanedCount++;
            }
        }
        
        if ($cleanedCount > 0) {
            $this->logger->info('Expired session cache entries cleaned up', [
                'cleaned_count' => $cleanedCount,
                'remaining_sessions' => count(self::$sessionCache)
            ]);
        }
        
        return $cleanedCount;
    }

    /**
     * Reset session performance statistics
     */
    public function resetStats(): void
    {
        self::$performanceStats = [
            'sessions_created' => 0,
            'sessions_accessed' => 0,
            'sessions_destroyed' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'authentication_attempts' => 0,
            'authentication_successes' => 0,
            'authentication_failures' => 0,
            'session_timeouts' => 0,
            'total_session_time' => 0,
            'total_auth_time' => 0
        ];
        
        $this->logger->info('Session optimization statistics reset');
    }
}
