<?php

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Exception;

/**
 * File I/O Caching Service
 * 
 * Provides intelligent file content and metadata caching to reduce
 * disk I/O operations and improve overall system performance.
 */
class FileIOCachingService
{
    private LoggerInterface $logger;
    private CacheInterface $cache;
    
    // In-memory cache for frequently accessed files
    private static array $memoryCache = [];
    private static array $statCache = [];
    
    // Performance tracking
    private static array $performanceStats = [
        'file_reads' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'disk_reads' => 0,
        'stat_calls' => 0,
        'stat_cache_hits' => 0,
        'bytes_read' => 0,
        'total_read_time' => 0,
        'total_cache_time' => 0,
        'disk_io_avoided' => 0
    ];
    
    // Cache configuration
    private int $maxMemoryCacheSize;
    private int $maxFileSize;
    private int $defaultTtl;
    private bool $enableMemoryCache;

    public function __construct(
        LoggerInterface $logger, 
        CacheInterface $cache,
        int $maxMemoryCacheSize = 10485760, // 10MB
        int $maxFileSize = 1048576,         // 1MB
        int $defaultTtl = 300                // 5 minutes
    ) {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->maxMemoryCacheSize = $maxMemoryCacheSize;
        $this->maxFileSize = $maxFileSize;
        $this->defaultTtl = $defaultTtl;
        $this->enableMemoryCache = true;
    }

    /**
     * Read file with intelligent caching
     */
    public function readFile(string $filePath, bool $useMemoryCache = true): string|false
    {
        $startTime = microtime(true);
        self::$performanceStats['file_reads']++;
        
        try {
            // Check memory cache first (fastest)
            if ($useMemoryCache && $this->enableMemoryCache && isset(self::$memoryCache[$filePath])) {
                $cached = self::$memoryCache[$filePath];
                
                // Check if file has been modified
                $currentMtime = $this->getFileMtime($filePath);
                
                if ($currentMtime !== false && $cached['mtime'] === $currentMtime) {
                    self::$performanceStats['cache_hits']++;
                    self::$performanceStats['disk_io_avoided']++;
                    self::$performanceStats['total_cache_time'] += microtime(true) - $startTime;
                    
                    $this->logger->debug('Memory cache hit', [
                        'file' => $filePath,
                        'size' => strlen($cached['content']),
                        'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
                    ]);
                    
                    return $cached['content'];
                }
            }
            
            // Check persistent cache
            $cacheKey = $this->getCacheKey($filePath);
            $cached = $this->getCachedContent($cacheKey);
            
            if ($cached !== null) {
                // Verify cache validity by checking mtime
                $currentMtime = $this->getFileMtime($filePath);
                
                if ($currentMtime !== false && $cached['mtime'] === $currentMtime) {
                    self::$performanceStats['cache_hits']++;
                    self::$performanceStats['total_cache_time'] += microtime(true) - $startTime;
                    
                    // Store in memory cache for next access
                    if ($useMemoryCache && $this->enableMemoryCache) {
                        $this->storeInMemoryCache($filePath, $cached['content'], $currentMtime);
                    }
                    
                    $this->logger->debug('Persistent cache hit', [
                        'file' => $filePath,
                        'size' => strlen($cached['content']),
                        'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
                    ]);
                    
                    return $cached['content'];
                }
            }
            
            self::$performanceStats['cache_misses']++;
            
            // Read from disk
            $content = $this->readFromDisk($filePath);
            
            if ($content === false) {
                return false;
            }
            
            $mtime = $this->getFileMtime($filePath);
            $fileSize = strlen($content);
            
            self::$performanceStats['disk_reads']++;
            self::$performanceStats['bytes_read'] += $fileSize;
            self::$performanceStats['total_read_time'] += microtime(true) - $startTime;
            
            // Cache the content
            $this->cacheContent($cacheKey, $content, $mtime);
            
            // Store in memory cache if applicable
            if ($useMemoryCache && $this->enableMemoryCache && $fileSize <= $this->maxFileSize) {
                $this->storeInMemoryCache($filePath, $content, $mtime);
            }
            
            $this->logger->debug('File read from disk and cached', [
                'file' => $filePath,
                'size' => $fileSize,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);
            
            return $content;
            
        } catch (Exception $e) {
            $this->logger->error('File read error', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Check if file exists with caching
     */
    public function fileExists(string $filePath): bool
    {
        self::$performanceStats['stat_calls']++;
        
        // Check stat cache
        if (isset(self::$statCache[$filePath])) {
            $cached = self::$statCache[$filePath];
            
            // Cache is valid for 5 seconds
            if (time() - $cached['time'] < 5) {
                self::$performanceStats['stat_cache_hits']++;
                return $cached['exists'];
            }
        }
        
        // Check filesystem
        $exists = file_exists($filePath);
        
        // Cache result
        self::$statCache[$filePath] = [
            'exists' => $exists,
            'time' => time()
        ];
        
        return $exists;
    }

    /**
     * Get file modification time with caching
     */
    public function getFileMtime(string $filePath): int|false
    {
        self::$performanceStats['stat_calls']++;
        
        // Check stat cache
        if (isset(self::$statCache[$filePath])) {
            $cached = self::$statCache[$filePath];
            
            // Cache is valid for 5 seconds
            if (time() - $cached['time'] < 5 && isset($cached['mtime'])) {
                self::$performanceStats['stat_cache_hits']++;
                return $cached['mtime'];
            }
        }
        
        // Get from filesystem
        clearstatcache(true, $filePath);
        $mtime = @filemtime($filePath);
        
        // Cache result
        if (!isset(self::$statCache[$filePath])) {
            self::$statCache[$filePath] = [];
        }
        
        self::$statCache[$filePath]['mtime'] = $mtime;
        self::$statCache[$filePath]['time'] = time();
        
        return $mtime;
    }

    /**
     * Read lines from file with caching
     */
    public function readFileLines(string $filePath, int $maxLines = 0): array|false
    {
        $content = $this->readFile($filePath);
        
        if ($content === false) {
            return false;
        }
        
        $lines = explode("\n", $content);
        
        if ($maxLines > 0 && count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
        }
        
        return $lines;
    }

    /**
     * Read and parse INI file with caching
     */
    public function readIniFile(string $filePath, bool $processSections = true): array|false
    {
        $content = $this->readFile($filePath);
        
        if ($content === false) {
            return false;
        }
        
        // Parse INI content
        $ini = parse_ini_string($content, $processSections);
        
        if ($ini === false) {
            $this->logger->error('INI parse error', [
                'file' => $filePath
            ]);
            return false;
        }
        
        return $ini;
    }

    /**
     * Read and parse JSON file with caching
     */
    public function readJsonFile(string $filePath): mixed
    {
        $content = $this->readFile($filePath);
        
        if ($content === false) {
            return false;
        }
        
        $json = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('JSON parse error', [
                'file' => $filePath,
                'error' => json_last_error_msg()
            ]);
            return false;
        }
        
        return $json;
    }

    /**
     * Read from disk with error handling
     */
    private function readFromDisk(string $filePath): string|false
    {
        if (!file_exists($filePath)) {
            return false;
        }
        
        $fileSize = filesize($filePath);
        
        if ($fileSize === false) {
            return false;
        }
        
        // For very large files, use streaming
        if ($fileSize > $this->maxFileSize * 10) {
            $this->logger->warning('Large file read', [
                'file' => $filePath,
                'size_mb' => round($fileSize / 1024 / 1024, 2)
            ]);
        }
        
        return file_get_contents($filePath);
    }

    /**
     * Get cache key for file
     */
    private function getCacheKey(string $filePath): string
    {
        return 'fileio:' . md5($filePath);
    }

    /**
     * Get cached content
     */
    private function getCachedContent(string $cacheKey): ?array
    {
        try {
            $item = $this->cache->getItem($cacheKey);
            
            if ($item->isHit()) {
                return $item->get();
            }
        } catch (Exception $e) {
            $this->logger->debug('Cache get error', [
                'key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }

    /**
     * Cache file content
     */
    private function cacheContent(string $cacheKey, string $content, int|false $mtime): void
    {
        try {
            $data = [
                'content' => $content,
                'mtime' => $mtime,
                'cached_at' => time()
            ];
            
            $item = $this->cache->getItem($cacheKey);
            $item->set($data);
            $item->expiresAfter($this->defaultTtl);
            $this->cache->save($item);
        } catch (Exception $e) {
            $this->logger->debug('Cache store error', [
                'key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Store in memory cache
     */
    private function storeInMemoryCache(string $filePath, string $content, int|false $mtime): void
    {
        // Check memory cache size
        $currentSize = $this->getMemoryCacheSize();
        $contentSize = strlen($content);
        
        if ($currentSize + $contentSize > $this->maxMemoryCacheSize) {
            // Evict oldest entries
            $this->evictOldestMemoryCacheEntries($contentSize);
        }
        
        self::$memoryCache[$filePath] = [
            'content' => $content,
            'mtime' => $mtime,
            'accessed' => time()
        ];
    }

    /**
     * Get memory cache size
     */
    private function getMemoryCacheSize(): int
    {
        $size = 0;
        
        foreach (self::$memoryCache as $cached) {
            $size += strlen($cached['content']);
        }
        
        return $size;
    }

    /**
     * Evict oldest memory cache entries
     */
    private function evictOldestMemoryCacheEntries(int $neededSpace): void
    {
        // Sort by access time
        uasort(self::$memoryCache, function($a, $b) {
            return $a['accessed'] <=> $b['accessed'];
        });
        
        $freedSpace = 0;
        $evicted = 0;
        
        foreach (self::$memoryCache as $path => $cached) {
            if ($freedSpace >= $neededSpace) {
                break;
            }
            
            $freedSpace += strlen($cached['content']);
            unset(self::$memoryCache[$path]);
            $evicted++;
        }
        
        $this->logger->debug('Memory cache eviction', [
            'evicted_entries' => $evicted,
            'freed_bytes' => $freedSpace
        ]);
    }

    /**
     * Clear all caches
     */
    public function clearAllCaches(): array
    {
        $memoryCacheSize = count(self::$memoryCache);
        $statCacheSize = count(self::$statCache);
        
        self::$memoryCache = [];
        self::$statCache = [];
        
        $this->logger->info('All file I/O caches cleared', [
            'memory_cache_entries' => $memoryCacheSize,
            'stat_cache_entries' => $statCacheSize
        ]);
        
        return [
            'memory_cache_cleared' => $memoryCacheSize,
            'stat_cache_cleared' => $statCacheSize
        ];
    }

    /**
     * Get performance statistics
     */
    public function getPerformanceStats(): array
    {
        $stats = self::$performanceStats;
        
        // Calculate derived metrics
        $stats['cache_hit_ratio'] = ($stats['file_reads'] > 0) 
            ? round(($stats['cache_hits'] / $stats['file_reads']) * 100, 2)
            : 0;
        
        $stats['stat_cache_hit_ratio'] = ($stats['stat_calls'] > 0) 
            ? round(($stats['stat_cache_hits'] / $stats['stat_calls']) * 100, 2)
            : 0;
        
        $stats['average_read_time'] = ($stats['disk_reads'] > 0) 
            ? round(($stats['total_read_time'] / $stats['disk_reads']) * 1000, 2)
            : 0;
        
        $stats['average_cache_time'] = ($stats['cache_hits'] > 0) 
            ? round(($stats['total_cache_time'] / $stats['cache_hits']) * 1000, 2)
            : 0;
        
        $stats['memory_cache_size'] = count(self::$memoryCache);
        $stats['memory_cache_bytes'] = $this->getMemoryCacheSize();
        $stats['memory_cache_mb'] = round($this->getMemoryCacheSize() / 1024 / 1024, 2);
        $stats['stat_cache_size'] = count(self::$statCache);
        
        $stats['disk_io_savings'] = ($stats['file_reads'] > 0) 
            ? round(($stats['disk_io_avoided'] / $stats['file_reads']) * 100, 2)
            : 0;
        
        return $stats;
    }

    /**
     * Reset performance statistics
     */
    public function resetStats(): void
    {
        self::$performanceStats = [
            'file_reads' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'disk_reads' => 0,
            'stat_calls' => 0,
            'stat_cache_hits' => 0,
            'bytes_read' => 0,
            'total_read_time' => 0,
            'total_cache_time' => 0,
            'disk_io_avoided' => 0
        ];
        
        $this->logger->info('File I/O caching statistics reset');
    }
}
