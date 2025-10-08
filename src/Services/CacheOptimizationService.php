<?php

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Exception;

/**
 * Cache Optimization Service
 * 
 * Provides intelligent multi-level caching with compression,
 * automatic cleanup, and performance monitoring.
 */
class CacheOptimizationService
{
    private LoggerInterface $logger;
    private CacheInterface $cache;
    
    // Memory cache for frequently accessed data
    private static array $memoryCache = [];
    
    // Performance tracking
    private static array $performanceStats = [
        'cache_hits' => 0,
        'cache_misses' => 0,
        'memory_hits' => 0,
        'memory_misses' => 0,
        'compression_operations' => 0,
        'total_compression_time' => 0,
        'total_cache_time' => 0,
        'total_memory_time' => 0
    ];

    public function __construct(LoggerInterface $logger, CacheInterface $cache)
    {
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * Get data with intelligent multi-level caching
     */
    public function get(string $key, callable $callback = null, int $ttl = 3600, bool $compress = true): mixed
    {
        $startTime = microtime(true);
        
        // Level 1: Memory cache (fastest)
        if (isset(self::$memoryCache[$key])) {
            $memoryEntry = self::$memoryCache[$key];
            if (time() < $memoryEntry['expires_at']) {
                self::$performanceStats['memory_hits']++;
                self::$performanceStats['total_memory_time'] += microtime(true) - $startTime;
                
                $this->logger->debug('Memory cache hit', [
                    'key' => $key,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ]);
                
                return $memoryEntry['data'];
            } else {
                unset(self::$memoryCache[$key]);
            }
        }
        
        self::$performanceStats['memory_misses']++;
        
        // Level 2: Persistent cache
        try {
            $cachedData = $this->cache->get($key, function () use ($key, $callback, $ttl, $compress, $startTime) {
                self::$performanceStats['cache_misses']++;
                
                if ($callback === null) {
                    return null;
                }
                
                // Generate data using callback
                $data = $callback();
                
                // Compress data if requested
                if ($compress && is_string($data)) {
                    $compressStartTime = microtime(true);
                    $compressedData = gzcompress($data, 9);
                    $compressDuration = microtime(true) - $compressStartTime;
                    
                    self::$performanceStats['compression_operations']++;
                    self::$performanceStats['total_compression_time'] += $compressDuration;
                    
                    $this->logger->debug('Data compressed', [
                        'key' => $key,
                        'original_size' => strlen($data),
                        'compressed_size' => strlen($compressedData),
                        'compression_ratio' => round((1 - strlen($compressedData) / strlen($data)) * 100, 1) . '%',
                        'compress_time_ms' => round($compressDuration * 1000, 2)
                    ]);
                    
                    $data = $compressedData;
                }
                
                return $data;
            }, $ttl);
            
            // Decompress if needed
            if ($compress && is_string($cachedData)) {
                $decompressStartTime = microtime(true);
                $decompressedData = @gzuncompress($cachedData);
                if ($decompressedData !== false) {
                    $cachedData = $decompressedData;
                    $this->logger->debug('Data decompressed', [
                        'key' => $key,
                        'decompress_time_ms' => round((microtime(true) - $decompressStartTime) * 1000, 2)
                    ]);
                }
            }
            
            self::$performanceStats['cache_hits']++;
            self::$performanceStats['total_cache_time'] += microtime(true) - $startTime;
            
            // Store in memory cache for faster access
            self::$memoryCache[$key] = [
                'data' => $cachedData,
                'expires_at' => time() + min($ttl, 300), // Memory cache expires in 5 minutes max
                'created_at' => time()
            ];
            
            // Clean up memory cache if it gets too large
            $this->cleanupMemoryCache();
            
            $totalDuration = microtime(true) - $startTime;
            
            $this->logger->debug('Cache data retrieved', [
                'key' => $key,
                'duration_ms' => round($totalDuration * 1000, 2),
                'source' => 'persistent_cache'
            ]);
            
            return $cachedData;
            
        } catch (Exception $e) {
            self::$performanceStats['cache_misses']++;
            self::$performanceStats['total_cache_time'] += microtime(true) - $startTime;
            
            $this->logger->error('Cache retrieval failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to callback if available
            if ($callback !== null) {
                return $callback();
            }
            
            return null;
        }
    }

    /**
     * Set data in cache with compression
     */
    public function set(string $key, mixed $data, int $ttl = 3600, bool $compress = true): bool
    {
        $startTime = microtime(true);
        
        try {
            $originalData = $data;
            
            // Compress data if requested
            if ($compress && is_string($data)) {
                $compressStartTime = microtime(true);
                $data = gzcompress($data, 9);
                $compressDuration = microtime(true) - $compressStartTime;
                
                self::$performanceStats['compression_operations']++;
                self::$performanceStats['total_compression_time'] += $compressDuration;
                
                $this->logger->debug('Data compressed for storage', [
                    'key' => $key,
                    'original_size' => strlen($originalData),
                    'compressed_size' => strlen($data),
                    'compression_ratio' => round((1 - strlen($data) / strlen($originalData)) * 100, 1) . '%',
                    'compress_time_ms' => round($compressDuration * 1000, 2)
                ]);
            }
            
            // Store in persistent cache
            $this->cache->delete($key);
            $this->cache->get($key, function () use ($data) {
                return $data;
            }, $ttl);
            
            // Store in memory cache
            self::$memoryCache[$key] = [
                'data' => $originalData, // Store uncompressed in memory
                'expires_at' => time() + min($ttl, 300),
                'created_at' => time()
            ];
            
            $duration = microtime(true) - $startTime;
            
            $this->logger->debug('Data cached successfully', [
                'key' => $key,
                'ttl' => $ttl,
                'compressed' => $compress,
                'duration_ms' => round($duration * 1000, 2)
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Cache storage failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Delete data from all cache levels
     */
    public function delete(string $key): bool
    {
        try {
            // Delete from memory cache
            unset(self::$memoryCache[$key]);
            
            // Delete from persistent cache
            $this->cache->delete($key);
            
            $this->logger->debug('Data deleted from cache', ['key' => $key]);
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Cache deletion failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Clear all caches
     */
    public function clearAll(): bool
    {
        try {
            // Clear memory cache
            $memoryCount = count(self::$memoryCache);
            self::$memoryCache = [];
            
            // Clear persistent cache (implementation depends on cache adapter)
            $this->cache->clear();
            
            $this->logger->info('All caches cleared', [
                'memory_entries_cleared' => $memoryCount
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Cache clearing failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get cache performance statistics
     */
    public function getPerformanceStats(): array
    {
        $stats = self::$performanceStats;
        
        // Calculate derived metrics
        $totalHits = $stats['cache_hits'] + $stats['memory_hits'];
        $totalMisses = $stats['cache_misses'] + $stats['memory_misses'];
        $totalRequests = $totalHits + $totalMisses;
        
        $stats['overall_hit_ratio'] = $totalRequests > 0 
            ? round(($totalHits / $totalRequests) * 100, 2)
            : 0;
        
        $stats['cache_hit_ratio'] = ($stats['cache_hits'] + $stats['cache_misses']) > 0 
            ? round(($stats['cache_hits'] / ($stats['cache_hits'] + $stats['cache_misses'])) * 100, 2)
            : 0;
        
        $stats['memory_hit_ratio'] = ($stats['memory_hits'] + $stats['memory_misses']) > 0 
            ? round(($stats['memory_hits'] / ($stats['memory_hits'] + $stats['memory_misses'])) * 100, 2)
            : 0;
        
        $stats['average_cache_time'] = $stats['cache_hits'] > 0 
            ? round(($stats['total_cache_time'] / $stats['cache_hits']) * 1000, 2)
            : 0;
        
        $stats['average_memory_time'] = $stats['memory_hits'] > 0 
            ? round(($stats['total_memory_time'] / $stats['memory_hits']) * 1000, 2)
            : 0;
        
        $stats['average_compression_time'] = $stats['compression_operations'] > 0 
            ? round(($stats['total_compression_time'] / $stats['compression_operations']) * 1000, 2)
            : 0;
        
        $stats['memory_cache_size'] = count(self::$memoryCache);
        
        return $stats;
    }

    /**
     * Clean up memory cache
     */
    public function cleanupMemoryCache(): int
    {
        $maxMemoryCacheSize = 1000;
        $currentTime = time();
        $cleanedCount = 0;
        
        // Remove expired entries
        foreach (self::$memoryCache as $key => $data) {
            if ($currentTime >= $data['expires_at']) {
                unset(self::$memoryCache[$key]);
                $cleanedCount++;
            }
        }
        
        // Remove oldest entries if cache is still too large
        if (count(self::$memoryCache) > $maxMemoryCacheSize) {
            $entries = [];
            foreach (self::$memoryCache as $key => $data) {
                $entries[$key] = $data['created_at'];
            }
            
            asort($entries);
            $entriesToRemove = count(self::$memoryCache) - $maxMemoryCacheSize;
            $removedCount = 0;
            
            foreach (array_keys($entries) as $key) {
                if ($removedCount >= $entriesToRemove) break;
                unset(self::$memoryCache[$key]);
                $cleanedCount++;
                $removedCount++;
            }
        }
        
        if ($cleanedCount > 0) {
            $this->logger->debug('Memory cache cleaned up', [
                'cleaned_entries' => $cleanedCount,
                'remaining_entries' => count(self::$memoryCache)
            ]);
        }
        
        return $cleanedCount;
    }

    /**
     * Get memory cache statistics
     */
    public function getMemoryCacheStats(): array
    {
        $totalSize = 0;
        $expiredCount = 0;
        $currentTime = time();
        
        foreach (self::$memoryCache as $key => $data) {
            $totalSize += strlen(serialize($data));
            if ($currentTime >= $data['expires_at']) {
                $expiredCount++;
            }
        }
        
        return [
            'entries_count' => count(self::$memoryCache),
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'expired_entries' => $expiredCount,
            'valid_entries' => count(self::$memoryCache) - $expiredCount
        ];
    }
}
