<?php

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;
use Exception;

/**
 * Include Manager Service
 * 
 * Provides intelligent management of PHP include/require operations,
 * eliminating redundant file inclusions and optimizing include paths.
 */
class IncludeManagerService
{
    private LoggerInterface $logger;
    private ConfigurationCacheService $configService;
    
    // Track included files to prevent redundant inclusions
    private static array $includedFiles = [];
    
    // Performance tracking
    private static array $performanceStats = [
        'include_calls' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'total_time' => 0
    ];

    public function __construct(LoggerInterface $logger, ConfigurationCacheService $configService)
    {
        $this->logger = $logger;
        $this->configService = $configService;
    }

    /**
     * Include a file with intelligent caching
     */
    public function includeFile(string $filePath, bool $once = true): bool
    {
        $startTime = microtime(true);
        
        // Normalize file path
        $normalizedPath = $this->normalizePath($filePath);
        
        // Check if already included
        if ($once && isset(self::$includedFiles[$normalizedPath])) {
            self::$performanceStats['cache_hits']++;
            self::$performanceStats['total_time'] += microtime(true) - $startTime;
            
            $this->logger->debug('Include cache hit', [
                'file' => basename($filePath),
                'normalized_path' => $normalizedPath
            ]);
            
            return true;
        }
        
        self::$performanceStats['cache_misses']++;
        self::$performanceStats['include_calls']++;
        
        // Check if file exists
        if (!file_exists($normalizedPath)) {
            $this->logger->warning('Include file not found', [
                'file' => $filePath,
                'normalized_path' => $normalizedPath
            ]);
            self::$performanceStats['total_time'] += microtime(true) - $startTime;
            return false;
        }
        
        try {
            if ($once) {
                include_once $normalizedPath;
            } else {
                include $normalizedPath;
            }
            
            // Track successful inclusion
            self::$includedFiles[$normalizedPath] = [
                'included_at' => time(),
                'file_size' => filesize($normalizedPath),
                'mtime' => filemtime($normalizedPath)
            ];
            
            self::$performanceStats['total_time'] += microtime(true) - $startTime;
            
            $this->logger->debug('File included successfully', [
                'file' => basename($filePath),
                'normalized_path' => $normalizedPath,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to include file', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            self::$performanceStats['total_time'] += microtime(true) - $startTime;
            return false;
        }
    }

    /**
     * Include common.inc with caching
     */
    public function includeCommonInc(): bool
    {
        $commonIncPath = $this->configService->getConfigPath('common');
        if ($commonIncPath === null) {
            $commonIncPath = __DIR__ . '/../../includes/common.inc';
        }
        
        return $this->includeFile($commonIncPath);
    }

    /**
     * Include AMI functions with caching
     */
    public function includeAmiFunctions(): bool
    {
        $amiFunctionsPath = __DIR__ . '/../../includes/amifunctions.inc';
        return $this->includeFile($amiFunctionsPath);
    }

    /**
     * Include node info functions with caching
     */
    public function includeNodeInfo(): bool
    {
        $nodeInfoPath = __DIR__ . '/../../includes/nodeinfo.inc';
        return $this->includeFile($nodeInfoPath);
    }

    /** Permission array names from authusers.inc (used to build valid_users and admin_users) */
    private const PERMISSION_ARRAY_NAMES = [
        'PERMUSER', 'CONNECTUSER', 'DISCUSER', 'MONUSER', 'LMONUSER', 'DTMFUSER',
        'ASTLKUSER', 'RSTATUSER', 'BUBLUSER', 'DVSWITCHUSER', 'FAVUSER', 'CTRLUSER',
        'CFGEDUSER', 'ASTRELUSER', 'ASTSTRUSER', 'ASTSTPUSER', 'FSTRESUSER',
        'RBTUSER', 'UPDUSER', 'HWTOUSER', 'WIKIUSER', 'CSTATUSER', 'ASTATUSER',
        'EXNUSER', 'ACTNUSER', 'ALLNUSER', 'DBTUSER', 'GPIOUSER', 'LLOGUSER',
        'ASTLUSER', 'CLOGUSER', 'IRLPLOGUSER', 'WLOGUSER', 'WERRUSER', 'BANUSER',
        'SYSINFUSER', 'SUSBUSER',
    ];

    /** Admin permission arrays - users in these are considered admin */
    private const ADMIN_PERMISSION_NAMES = ['CTRLUSER', 'CFGEDUSER'];

    /**
     * Include auth files with caching
     */
    public function includeAuthFiles(): bool
    {
        $success = true;
        
        // Include authusers.inc
        $authUsersPath = __DIR__ . '/../../user_files/authusers.inc';
        if (file_exists($authUsersPath)) {
            $success &= $this->includeFile($authUsersPath);
        }
        
        // Include authini.inc
        $authIniPath = __DIR__ . '/../../user_files/authini.inc';
        if (file_exists($authIniPath)) {
            $success &= $this->includeFile($authIniPath);
        }
        
        return $success;
    }

    /**
     * Ensure auth files are loaded and GLOBALS valid_users / admin_users are set.
     * Call this before checking $GLOBALS['valid_users'] or $GLOBALS['admin_users'].
     */
    public function ensureAuthGlobalsLoaded(): void
    {
        $this->includeAuthFiles();

        if (isset($GLOBALS['valid_users']) && is_array($GLOBALS['valid_users'])) {
            return; // Already set (e.g. by previous call)
        }

        $validUsers = [];
        $adminUsers = [];

        foreach (self::PERMISSION_ARRAY_NAMES as $name) {
            if (isset($GLOBALS[$name]) && is_array($GLOBALS[$name])) {
                foreach ($GLOBALS[$name] as $user) {
                    if (is_string($user) && $user !== '') {
                        $validUsers[$user] = true;
                    }
                }
                if (in_array($name, self::ADMIN_PERMISSION_NAMES, true)) {
                    foreach ($GLOBALS[$name] as $user) {
                        if (is_string($user) && $user !== '') {
                            $adminUsers[$user] = true;
                        }
                    }
                }
            }
        }

        $GLOBALS['valid_users'] = array_keys($validUsers);
        $GLOBALS['admin_users'] = array_keys($adminUsers);
    }

    /**
     * Get configuration value with fallback to include if needed
     */
    public function getConfigWithInclude(string $key, $default = null)
    {
        // First try to get from configuration cache
        $value = $this->configService->getConfig($key, null);
        if ($value !== null) {
            return $value;
        }
        
        // If not in cache, include common.inc and try again
        $this->includeCommonInc();
        return $this->configService->getConfig($key, $default);
    }

    /**
     * Normalize file path for consistent caching
     */
    private function normalizePath(string $filePath): string
    {
        // Convert relative paths to absolute
        if (!str_starts_with($filePath, '/')) {
            $filePath = __DIR__ . '/../../' . $filePath;
        }
        
        // Resolve any .. or . in the path
        return realpath($filePath) ?: $filePath;
    }

    /**
     * Clear include cache
     */
    public function clearCache(): void
    {
        self::$includedFiles = [];
        $this->logger->info('Include cache cleared');
    }

    /**
     * Get performance statistics
     */
    public function getPerformanceStats(): array
    {
        $stats = self::$performanceStats;
        $stats['cache_hit_ratio'] = $stats['cache_hits'] > 0 
            ? round(($stats['cache_hits'] / ($stats['cache_hits'] + $stats['cache_misses'])) * 100, 2)
            : 0;
        
        $stats['average_time'] = $stats['include_calls'] > 0 
            ? round(($stats['total_time'] / $stats['include_calls']) * 1000, 2)
            : 0;
        
        $stats['included_files_count'] = count(self::$includedFiles);
        $stats['included_files'] = array_keys(self::$includedFiles);
        
        return $stats;
    }

    /**
     * Check if a file has been included
     */
    public function isIncluded(string $filePath): bool
    {
        $normalizedPath = $this->normalizePath($filePath);
        return isset(self::$includedFiles[$normalizedPath]);
    }

    /**
     * Get included files list
     */
    public function getIncludedFiles(): array
    {
        return self::$includedFiles;
    }

    /**
     * Clean up old cache entries
     */
    public function cleanupCache(int $maxAge = 3600): int
    {
        $cleanedCount = 0;
        $currentTime = time();
        
        foreach (self::$includedFiles as $filePath => $fileData) {
            if (($currentTime - $fileData['included_at']) > $maxAge) {
                unset(self::$includedFiles[$filePath]);
                $cleanedCount++;
            }
        }
        
        if ($cleanedCount > 0) {
            $this->logger->info('Include cache cleaned up', ['cleaned_entries' => $cleanedCount]);
        }
        
        return $cleanedCount;
    }
}
