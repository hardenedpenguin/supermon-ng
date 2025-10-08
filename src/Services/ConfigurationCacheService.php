<?php

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;
use Exception;

/**
 * Configuration Cache Service
 * 
 * Provides intelligent caching and lazy loading for configuration files,
 * eliminating repeated include_once calls and file system operations.
 */
class ConfigurationCacheService
{
    private LoggerInterface $logger;
    private static ?array $configCache = null;
    private static ?array $fileCache = null;
    private static ?array $includeCache = null;
    private static bool $initialized = false;
    
    // Configuration file paths (cached after first load)
    private static ?string $commonIncPath = null;
    private static ?string $userFilesPath = null;
    
    // Performance tracking
    private static array $performanceStats = [
        'include_calls' => 0,
        'file_checks' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'total_time' => 0
    ];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        
        if (!self::$initialized) {
            $this->initializeConfigurationPaths();
            self::$initialized = true;
        }
    }

    /**
     * Get configuration value with intelligent caching
     */
    public function getConfig(string $key, $default = null)
    {
        $startTime = microtime(true);
        
        // Load configuration if not cached
        if (self::$configCache === null) {
            $this->loadConfiguration();
        }
        
        self::$performanceStats['cache_hits']++;
        
        $result = self::$configCache[$key] ?? $default;
        
        self::$performanceStats['total_time'] += microtime(true) - $startTime;
        
        return $result;
    }

    /**
     * Get all configuration as array
     */
    public function getAllConfig(): array
    {
        if (self::$configCache === null) {
            $this->loadConfiguration();
        }
        
        return self::$configCache ?? [];
    }

    /**
     * Load file content with caching
     */
    public function getFileContent(string $filePath, bool $forceReload = false): ?string
    {
        $startTime = microtime(true);
        
        // Check cache first
        if (!$forceReload && isset(self::$fileCache[$filePath])) {
            $cachedFile = self::$fileCache[$filePath];
            
            // Check if file has been modified
            if (file_exists($filePath) && filemtime($filePath) === $cachedFile['mtime']) {
                self::$performanceStats['cache_hits']++;
                self::$performanceStats['total_time'] += microtime(true) - $startTime;
                return $cachedFile['content'];
            }
        }
        
        self::$performanceStats['cache_misses']++;
        self::$performanceStats['file_checks']++;
        
        if (!file_exists($filePath)) {
            self::$performanceStats['total_time'] += microtime(true) - $startTime;
            return null;
        }
        
        $content = file_get_contents($filePath);
        if ($content === false) {
            self::$performanceStats['total_time'] += microtime(true) - $startTime;
            return null;
        }
        
        // Cache the file content
        self::$fileCache[$filePath] = [
            'content' => $content,
            'mtime' => filemtime($filePath),
            'size' => strlen($content)
        ];
        
        self::$performanceStats['total_time'] += microtime(true) - $startTime;
        
        return $content;
    }

    /**
     * Include file with caching to prevent multiple inclusions
     */
    public function includeFile(string $filePath): bool
    {
        $startTime = microtime(true);
        
        // Check if already included
        if (isset(self::$includeCache[$filePath])) {
            self::$performanceStats['cache_hits']++;
            self::$performanceStats['total_time'] += microtime(true) - $startTime;
            return true;
        }
        
        self::$performanceStats['cache_misses']++;
        self::$performanceStats['include_calls']++;
        
        if (!file_exists($filePath)) {
            $this->logger->warning('Configuration file not found', ['file' => $filePath]);
            self::$performanceStats['total_time'] += microtime(true) - $startTime;
            return false;
        }
        
        try {
            include_once $filePath;
            self::$includeCache[$filePath] = true;
            
            self::$performanceStats['total_time'] += microtime(true) - $startTime;
            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to include configuration file', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            self::$performanceStats['total_time'] += microtime(true) - $startTime;
            return false;
        }
    }

    /**
     * Get configuration file path
     */
    public function getConfigPath(string $type): ?string
    {
        switch ($type) {
            case 'common':
                return self::$commonIncPath;
            case 'user_files':
                return self::$userFilesPath;
            default:
                return null;
        }
    }

    /**
     * Clear all caches
     */
    public function clearCache(): void
    {
        self::$configCache = null;
        self::$fileCache = null;
        self::$includeCache = null;
        
        $this->logger->info('Configuration cache cleared');
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
        
        $stats['average_time'] = $stats['total_time'] > 0 
            ? round(($stats['total_time'] / max(1, $stats['include_calls'] + $stats['file_checks'])) * 1000, 2)
            : 0;
        
        return $stats;
    }

    /**
     * Initialize configuration paths
     */
    private function initializeConfigurationPaths(): void
    {
        $startTime = microtime(true);
        
        // Determine the project root directory
        $projectRoot = $this->findProjectRoot();
        
        // Set configuration file paths
        self::$commonIncPath = $projectRoot . '/includes/common.inc';
        self::$userFilesPath = $projectRoot . '/user_files';
        
        $this->logger->debug('Configuration paths initialized', [
            'common_inc' => self::$commonIncPath,
            'user_files' => self::$userFilesPath,
            'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ]);
    }

    /**
     * Load configuration from common.inc
     */
    private function loadConfiguration(): void
    {
        $startTime = microtime(true);
        
        if (self::$configCache !== null) {
            return; // Already loaded
        }
        
        // Include common.inc with caching
        if (!$this->includeFile(self::$commonIncPath)) {
            $this->logger->error('Failed to load common.inc');
            self::$configCache = [];
            return;
        }
        
        // Extract global variables from common.inc
        self::$configCache = $this->extractGlobalVariables();
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $this->logger->debug('Configuration loaded', [
            'entries_count' => count(self::$configCache),
            'duration_ms' => $duration
        ]);
    }

    /**
     * Extract global variables from common.inc
     */
    private function extractGlobalVariables(): array
    {
        $config = [];
        
        // Define the variables we want to extract
        $globalVars = [
            'USERFILES',
            'TITLE_LOGGED',
            'TITLE_NOT_LOGGED',
            'VERSION_DATE',
            'WEB_ACCESS_LOG',
            'WEB_ERROR_LOG',
            'ASTERISK_LOG',
            'ASTDB_TXT',
            'PRIVATENODES',
            'IRLP_NODES',
            'ECHOLINK_NODES'
        ];
        
        foreach ($globalVars as $var) {
            if (isset($GLOBALS[$var])) {
                $config[$var] = $GLOBALS[$var];
            }
        }
        
        return $config;
    }

    /**
     * Find project root directory
     */
    private function findProjectRoot(): string
    {
        // Start from current file and work backwards
        $currentDir = __DIR__;
        
        // Go up two levels from Services directory
        $projectRoot = dirname(dirname($currentDir));
        
        // Verify this is the project root by checking for common.inc
        if (file_exists($projectRoot . '/includes/common.inc')) {
            return $projectRoot;
        }
        
        // Fallback: use current working directory
        return getcwd() ?: '/var/www/html/supermon-ng';
    }

    /**
     * Check if file exists with caching
     */
    public function fileExists(string $filePath): bool
    {
        // Check cache first
        if (isset(self::$fileCache[$filePath])) {
            $cachedFile = self::$fileCache[$filePath];
            
            // If we have a cached file, check if it still exists
            if (file_exists($filePath) && filemtime($filePath) === $cachedFile['mtime']) {
                return true;
            }
        }
        
        self::$performanceStats['file_checks']++;
        return file_exists($filePath);
    }

    /**
     * Get file modification time with caching
     */
    public function getFileMtime(string $filePath): ?int
    {
        if (!$this->fileExists($filePath)) {
            return null;
        }
        
        // Check cache first
        if (isset(self::$fileCache[$filePath])) {
            return self::$fileCache[$filePath]['mtime'];
        }
        
        return filemtime($filePath);
    }
}
