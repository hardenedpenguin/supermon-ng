<?php

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Exception;

/**
 * Authentication Optimization Service
 * 
 * Provides intelligent authentication management with caching,
 * rate limiting, and performance monitoring.
 */
class AuthenticationOptimizationService
{
    private LoggerInterface $logger;
    private CacheInterface $cache;
    
    // Authentication cache for user data
    private static array $authCache = [];
    
    // Performance tracking
    private static array $performanceStats = [
        'login_attempts' => 0,
        'successful_logins' => 0,
        'failed_logins' => 0,
        'rate_limited_attempts' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'password_verifications' => 0,
        'total_auth_time' => 0,
        'total_password_time' => 0,
        'brute_force_attempts' => 0
    ];

    private int $maxLoginAttempts;
    private int $lockoutDuration;
    private int $passwordHashRounds;
    private bool $enableAuthCaching;

    public function __construct(LoggerInterface $logger, CacheInterface $cache, int $maxLoginAttempts = 5, int $lockoutDuration = 900)
    {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->maxLoginAttempts = $maxLoginAttempts;
        $this->lockoutDuration = $lockoutDuration;
        $this->passwordHashRounds = 12;
        $this->enableAuthCaching = true;
    }

    /**
     * Authenticate user with optimization and security features
     */
    public function authenticate(string $username, string $password, ?string $clientIp = null): array
    {
        $startTime = microtime(true);
        self::$performanceStats['login_attempts']++;
        
        try {
            // Check rate limiting
            if ($this->isRateLimited($username, $clientIp)) {
                self::$performanceStats['rate_limited_attempts']++;
                
                $this->logger->warning('Authentication rate limited', [
                    'username' => $username,
                    'client_ip' => $clientIp,
                    'attempts' => $this->getFailedAttempts($username, $clientIp)
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Too many failed attempts. Please try again later.',
                    'rate_limited' => true,
                    'lockout_duration' => $this->lockoutDuration
                ];
            }
            
            // Get user data with caching
            $userData = $this->getUserData($username);
            
            if (!$userData) {
                $this->recordFailedAttempt($username, $clientIp);
                
                return [
                    'success' => false,
                    'message' => 'Invalid username or password.',
                    'rate_limited' => false
                ];
            }
            
            // Verify password with performance tracking
            $passwordStartTime = microtime(true);
            $passwordValid = password_verify($password, $userData['password_hash']);
            $passwordTime = microtime(true) - $passwordStartTime;
            
            self::$performanceStats['password_verifications']++;
            self::$performanceStats['total_password_time'] += $passwordTime;
            
            if (!$passwordValid) {
                $this->recordFailedAttempt($username, $clientIp);
                
                $this->logger->warning('Authentication failed - invalid password', [
                    'username' => $username,
                    'client_ip' => $clientIp,
                    'password_verify_time_ms' => round($passwordTime * 1000, 2)
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Invalid username or password.',
                    'rate_limited' => false
                ];
            }
            
            // Successful authentication
            $this->clearFailedAttempts($username, $clientIp);
            self::$performanceStats['successful_logins']++;
            
            $totalTime = microtime(true) - $startTime;
            self::$performanceStats['total_auth_time'] += $totalTime;
            
            $this->logger->info('Authentication successful', [
                'username' => $username,
                'client_ip' => $clientIp,
                'total_time_ms' => round($totalTime * 1000, 2),
                'password_verify_time_ms' => round($passwordTime * 1000, 2)
            ]);
            
            return [
                'success' => true,
                'message' => 'Authentication successful.',
                'user_data' => [
                    'username' => $username,
                    'roles' => $userData['roles'] ?? [],
                    'permissions' => $userData['permissions'] ?? [],
                    'last_login' => time()
                ],
                'rate_limited' => false
            ];
            
        } catch (Exception $e) {
            self::$performanceStats['failed_logins']++;
            self::$performanceStats['total_auth_time'] += microtime(true) - $startTime;
            
            $this->logger->error('Authentication error', [
                'username' => $username,
                'client_ip' => $clientIp,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Authentication service error.',
                'rate_limited' => false
            ];
        }
    }

    /**
     * Get user data with caching
     */
    private function getUserData(string $username): ?array
    {
        $cacheKey = "user_data:$username";
        
        // Check cache first
        if ($this->enableAuthCaching && isset(self::$authCache[$cacheKey])) {
            self::$performanceStats['cache_hits']++;
            
            $this->logger->debug('User data cache hit', [
                'username' => $username
            ]);
            
            return self::$authCache[$cacheKey];
        }
        
        self::$performanceStats['cache_misses']++;
        
        try {
            // Load user data from files
            $configService = new \SupermonNg\Services\ConfigurationCacheService($this->logger);
            $userFilesPath = $configService->getConfig('USERFILES', 'user_files');
            
            $authUsersFile = $userFilesPath . '/authusers.inc';
            $authIniFile = $userFilesPath . '/authini.inc';
            
            if (!file_exists($authUsersFile)) {
                return null;
            }
            
            // Load users array
            $users = [];
            include $authUsersFile;
            
            if (!isset($users[$username])) {
                return null;
            }
            
            $userData = [
                'username' => $username,
                'password_hash' => $users[$username],
                'roles' => [],
                'permissions' => []
            ];
            
            // Load additional user data from authini.inc if available
            if (file_exists($authIniFile)) {
                $userConfig = [];
                include $authIniFile;
                
                if (isset($userConfig[$username])) {
                    $userData = array_merge($userData, $userConfig[$username]);
                }
            }
            
            // Cache the user data
            if ($this->enableAuthCaching) {
                self::$authCache[$cacheKey] = $userData;
            }
            
            return $userData;
            
        } catch (Exception $e) {
            $this->logger->error('Error loading user data', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Check if user is rate limited
     */
    private function isRateLimited(string $username, ?string $clientIp = null): bool
    {
        $failedAttempts = $this->getFailedAttempts($username, $clientIp);
        return $failedAttempts >= $this->maxLoginAttempts;
    }

    /**
     * Get failed login attempts count
     */
    private function getFailedAttempts(string $username, ?string $clientIp = null): int
    {
        $key = $this->getFailedAttemptsKey($username, $clientIp);
        
        try {
            return $this->cache->get($key, function () {
                return 0;
            });
        } catch (Exception $e) {
            $this->logger->error('Error getting failed attempts count', [
                'username' => $username,
                'client_ip' => $clientIp,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Record failed login attempt
     */
    private function recordFailedAttempt(string $username, ?string $clientIp = null): void
    {
        $key = $this->getFailedAttemptsKey($username, $clientIp);
        $attempts = $this->getFailedAttempts($username, $clientIp) + 1;
        
        try {
            $this->cache->delete($key);
            $this->cache->get($key, function () use ($attempts) {
                return $attempts;
            }, $this->lockoutDuration);
            
            self::$performanceStats['failed_logins']++;
            
            // Check for brute force patterns
            if ($attempts >= $this->maxLoginAttempts) {
                self::$performanceStats['brute_force_attempts']++;
                
                $this->logger->warning('Potential brute force attack detected', [
                    'username' => $username,
                    'client_ip' => $clientIp,
                    'attempts' => $attempts,
                    'lockout_duration' => $this->lockoutDuration
                ]);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Error recording failed attempt', [
                'username' => $username,
                'client_ip' => $clientIp,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear failed login attempts
     */
    private function clearFailedAttempts(string $username, ?string $clientIp = null): void
    {
        $key = $this->getFailedAttemptsKey($username, $clientIp);
        
        try {
            $this->cache->delete($key);
        } catch (Exception $e) {
            $this->logger->error('Error clearing failed attempts', [
                'username' => $username,
                'client_ip' => $clientIp,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate failed attempts cache key
     */
    private function getFailedAttemptsKey(string $username, ?string $clientIp = null): string
    {
        $identifier = $username;
        if ($clientIp) {
            $identifier .= ':' . $clientIp;
        }
        
        return 'failed_login_attempts:' . md5($identifier);
    }

    /**
     * Hash password with optimization
     */
    public function hashPassword(string $password): string
    {
        $startTime = microtime(true);
        
        $hash = password_hash($password, PASSWORD_DEFAULT, [
            'cost' => $this->passwordHashRounds
        ]);
        
        $duration = microtime(true) - $startTime;
        
        $this->logger->debug('Password hashed', [
            'hash_rounds' => $this->passwordHashRounds,
            'hash_time_ms' => round($duration * 1000, 2)
        ]);
        
        return $hash;
    }

    /**
     * Verify password strength
     */
    public function validatePasswordStrength(string $password): array
    {
        $strength = 0;
        $feedback = [];
        
        if (strlen($password) >= 8) {
            $strength++;
        } else {
            $feedback[] = 'Password should be at least 8 characters long';
        }
        
        if (preg_match('/[A-Z]/', $password)) {
            $strength++;
        } else {
            $feedback[] = 'Password should contain at least one uppercase letter';
        }
        
        if (preg_match('/[a-z]/', $password)) {
            $strength++;
        } else {
            $feedback[] = 'Password should contain at least one lowercase letter';
        }
        
        if (preg_match('/[0-9]/', $password)) {
            $strength++;
        } else {
            $feedback[] = 'Password should contain at least one number';
        }
        
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $strength++;
        } else {
            $feedback[] = 'Password should contain at least one special character';
        }
        
        return [
            'strength' => $strength,
            'max_strength' => 5,
            'is_strong' => $strength >= 4,
            'feedback' => $feedback
        ];
    }

    /**
     * Get authentication performance statistics
     */
    public function getPerformanceStats(): array
    {
        $stats = self::$performanceStats;
        
        // Calculate derived metrics
        $stats['success_rate'] = $stats['login_attempts'] > 0 
            ? round(($stats['successful_logins'] / $stats['login_attempts']) * 100, 2)
            : 0;
        
        $stats['failure_rate'] = $stats['login_attempts'] > 0 
            ? round(($stats['failed_logins'] / $stats['login_attempts']) * 100, 2)
            : 0;
        
        $stats['rate_limit_rate'] = $stats['login_attempts'] > 0 
            ? round(($stats['rate_limited_attempts'] / $stats['login_attempts']) * 100, 2)
            : 0;
        
        $stats['cache_hit_ratio'] = ($stats['cache_hits'] + $stats['cache_misses']) > 0 
            ? round(($stats['cache_hits'] / ($stats['cache_hits'] + $stats['cache_misses'])) * 100, 2)
            : 0;
        
        $stats['average_auth_time'] = $stats['login_attempts'] > 0 
            ? round(($stats['total_auth_time'] / $stats['login_attempts']) * 1000, 2)
            : 0;
        
        $stats['average_password_time'] = $stats['password_verifications'] > 0 
            ? round(($stats['total_password_time'] / $stats['password_verifications']) * 1000, 2)
            : 0;
        
        $stats['cached_users_count'] = count(self::$authCache);
        
        // Add security configuration
        $stats['security_config'] = [
            'max_login_attempts' => $this->maxLoginAttempts,
            'lockout_duration' => $this->lockoutDuration,
            'password_hash_rounds' => $this->passwordHashRounds,
            'auth_caching_enabled' => $this->enableAuthCaching
        ];
        
        return $stats;
    }

    /**
     * Clear authentication cache
     */
    public function clearAuthCache(): int
    {
        $clearedCount = count(self::$authCache);
        self::$authCache = [];
        
        $this->logger->info('Authentication cache cleared', [
            'cleared_entries' => $clearedCount
        ]);
        
        return $clearedCount;
    }

    /**
     * Reset authentication performance statistics
     */
    public function resetStats(): void
    {
        self::$performanceStats = [
            'login_attempts' => 0,
            'successful_logins' => 0,
            'failed_logins' => 0,
            'rate_limited_attempts' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'password_verifications' => 0,
            'total_auth_time' => 0,
            'total_password_time' => 0,
            'brute_force_attempts' => 0
        ];
        
        $this->logger->info('Authentication optimization statistics reset');
    }
}
