<?php

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;
use Exception;

/**
 * Lazy File Loader Service
 * 
 * Provides lazy loading and intelligent caching for file system operations,
 * reducing I/O overhead and improving application performance.
 */
class LazyFileLoaderService
{
    private LoggerInterface $logger;
    private ConfigurationCacheService $configService;
    
    // File cache with metadata
    private static array $fileCache = [];
    
    // Performance tracking
    private static array $performanceStats = [
        'file_reads' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'total_bytes_read' => 0,
        'total_time' => 0
    ];

    public function __construct(LoggerInterface $logger, ConfigurationCacheService $configService)
    {
        $this->logger = $logger;
        $this->configService = $configService;
    }

    /**
     * Load file content with intelligent caching
     */
    public function loadFile(string $filePath, bool $forceReload = false): ?string
    {
        $startTime = microtime(true);
        
        // Check cache first
        if (!$forceReload && isset(self::$fileCache[$filePath])) {
            $cachedFile = self::$fileCache[$filePath];
            
            // Verify file hasn't changed
            if ($this->isFileUnchanged($filePath, $cachedFile['mtime'])) {
                self::$performanceStats['cache_hits']++;
                self::$performanceStats['total_time'] += microtime(true) - $startTime;
                
                $this->logger->debug('File cache hit', [
                    'file' => basename($filePath),
                    'size' => $cachedFile['size']
                ]);
                
                return $cachedFile['content'];
            }
        }
        
        self::$performanceStats['cache_misses']++;
        self::$performanceStats['file_reads']++;
        
        // Load file from disk
        $content = $this->readFileFromDisk($filePath);
        if ($content === null) {
            self::$performanceStats['total_time'] += microtime(true) - $startTime;
            return null;
        }
        
        // Cache the file
        self::$fileCache[$filePath] = [
            'content' => $content,
            'mtime' => filemtime($filePath),
            'size' => strlen($content),
            'loaded_at' => time()
        ];
        
        self::$performanceStats['total_bytes_read'] += strlen($content);
        self::$performanceStats['total_time'] += microtime(true) - $startTime;
        
        $this->logger->debug('File loaded and cached', [
            'file' => basename($filePath),
            'size' => strlen($content),
            'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ]);
        
        return $content;
    }

    /**
     * Load INI file with parsing and caching
     */
    public function loadIniFile(string $filePath, bool $forceReload = false): ?array
    {
        $content = $this->loadFile($filePath, $forceReload);
        if ($content === null) {
            return null;
        }
        
        // Parse INI content
        $parsed = parse_ini_string($content, true, INI_SCANNER_RAW);
        if ($parsed === false) {
            $this->logger->warning('Failed to parse INI file', ['file' => $filePath]);
            return null;
        }
        
        return $parsed;
    }

    /**
     * Load JSON file with parsing and caching
     */
    public function loadJsonFile(string $filePath, bool $forceReload = false): ?array
    {
        $content = $this->loadFile($filePath, $forceReload);
        if ($content === null) {
            return null;
        }
        
        // Parse JSON content
        $parsed = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('Failed to parse JSON file', [
                'file' => $filePath,
                'error' => json_last_error_msg()
            ]);
            return null;
        }
        
        return $parsed;
    }

    /**
     * Load configuration file based on type
     */
    public function loadConfigFile(string $type, ?string $customPath = null): ?array
    {
        $filePath = $this->getConfigFilePath($type, $customPath);
        if ($filePath === null) {
            return null;
        }
        
        // Determine file type and load accordingly
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'ini':
                return $this->loadIniFile($filePath);
            case 'json':
                return $this->loadJsonFile($filePath);
            default:
                // Try to parse as INI first, fallback to raw content
                $iniResult = $this->loadIniFile($filePath);
                if ($iniResult !== null) {
                    return $iniResult;
                }
                
                $content = $this->loadFile($filePath);
                return $content ? ['content' => $content] : null;
        }
    }

    /**
     * Check if file exists without loading content
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
        
        return file_exists($filePath);
    }

    /**
     * Get file modification time
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

    /**
     * Get file size
     */
    public function getFileSize(string $filePath): ?int
    {
        if (!$this->fileExists($filePath)) {
            return null;
        }
        
        // Check cache first
        if (isset(self::$fileCache[$filePath])) {
            return self::$fileCache[$filePath]['size'];
        }
        
        return filesize($filePath);
    }

    /**
     * Clear file cache
     */
    public function clearCache(?string $filePath = null): void
    {
        if ($filePath !== null) {
            unset(self::$fileCache[$filePath]);
            $this->logger->debug('File cache cleared for specific file', ['file' => $filePath]);
        } else {
            self::$fileCache = [];
            $this->logger->info('All file cache cleared');
        }
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
        
        $stats['average_bytes_per_read'] = $stats['file_reads'] > 0 
            ? round($stats['total_bytes_read'] / $stats['file_reads'])
            : 0;
        
        $stats['average_time_per_read'] = $stats['file_reads'] > 0 
            ? round(($stats['total_time'] / $stats['file_reads']) * 1000, 2)
            : 0;
        
        $stats['cached_files_count'] = count(self::$fileCache);
        
        return $stats;
    }

    /**
     * Clean up old cache entries
     */
    public function cleanupCache(int $maxAge = 3600): int
    {
        $cleanedCount = 0;
        $currentTime = time();
        
        foreach (self::$fileCache as $filePath => $fileData) {
            if (($currentTime - $fileData['loaded_at']) > $maxAge) {
                unset(self::$fileCache[$filePath]);
                $cleanedCount++;
            }
        }
        
        if ($cleanedCount > 0) {
            $this->logger->info('File cache cleaned up', ['cleaned_entries' => $cleanedCount]);
        }
        
        return $cleanedCount;
    }

    /**
     * Read file from disk
     */
    private function readFileFromDisk(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            $this->logger->warning('File not found', ['file' => $filePath]);
            return null;
        }
        
        try {
            $content = file_get_contents($filePath);
            if ($content === false) {
                $this->logger->error('Failed to read file', ['file' => $filePath]);
                return null;
            }
            
            return $content;
        } catch (Exception $e) {
            $this->logger->error('Exception reading file', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if file is unchanged since cached
     */
    private function isFileUnchanged(string $filePath, int $cachedMtime): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }
        
        return filemtime($filePath) === $cachedMtime;
    }

    /**
     * Get configuration file path
     */
    private function getConfigFilePath(string $type, ?string $customPath = null): ?string
    {
        if ($customPath !== null) {
            return $customPath;
        }
        
        $userFilesPath = $this->configService->getConfigPath('user_files');
        if ($userFilesPath === null) {
            return null;
        }
        
        switch ($type) {
            case 'allmon':
                return $userFilesPath . '/allmon.ini';
            case 'auth':
                return $userFilesPath . '/authini.inc';
            case 'control':
                return $userFilesPath . '/controlpanel.ini';
            case 'favorites':
                return $userFilesPath . '/favorites.ini';
            case 'private_nodes':
                return $userFilesPath . '/privatenodes.txt';
            default:
                return null;
        }
    }
}
