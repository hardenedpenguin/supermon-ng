<?php

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Doctrine\DBAL\Connection;
use Exception;

/**
 * Database Optimization Service
 * 
 * Provides intelligent database query caching, connection pooling,
 * and performance monitoring for optimal database operations.
 */
class DatabaseOptimizationService
{
    private LoggerInterface $logger;
    private CacheInterface $cache;
    private ?Connection $connection;
    
    // Query cache with metadata
    private static array $queryCache = [];
    
    // Performance tracking
    private static array $performanceStats = [
        'queries_executed' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'total_query_time' => 0,
        'total_cache_time' => 0,
        'connection_pool_hits' => 0,
        'connection_pool_misses' => 0
    ];

    public function __construct(LoggerInterface $logger, CacheInterface $cache, ?Connection $connection = null)
    {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->connection = $connection;
    }

    /**
     * Execute query with intelligent caching
     */
    public function executeCachedQuery(string $sql, array $parameters = [], int $cacheTtl = 300): array
    {
        if ($this->connection === null) {
            $this->logger->warning('Database connection not available for query execution');
            return [];
        }
        
        $startTime = microtime(true);
        
        // Generate cache key
        $cacheKey = $this->generateCacheKey($sql, $parameters);
        
        // Check cache first
        if (isset(self::$queryCache[$cacheKey])) {
            $cachedQuery = self::$queryCache[$cacheKey];
            
            // Verify cache is not expired
            if (time() < $cachedQuery['expires_at']) {
                self::$performanceStats['cache_hits']++;
                self::$performanceStats['total_cache_time'] += microtime(true) - $startTime;
                
                $this->logger->debug('Query cache hit', [
                    'sql' => $this->truncateSql($sql),
                    'cache_key' => substr($cacheKey, 0, 16) . '...',
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ]);
                
                return $cachedQuery['result'];
            } else {
                // Remove expired cache entry
                unset(self::$queryCache[$cacheKey]);
            }
        }
        
        self::$performanceStats['cache_misses']++;
        
        // Execute query
        $queryStartTime = microtime(true);
        try {
            $stmt = $this->connection->prepare($sql);
            $result = $stmt->executeQuery($parameters)->fetchAllAssociative();
            
            $queryDuration = microtime(true) - $queryStartTime;
            self::$performanceStats['queries_executed']++;
            self::$performanceStats['total_query_time'] += $queryDuration;
            
            // Cache the result
            self::$queryCache[$cacheKey] = [
                'result' => $result,
                'expires_at' => time() + $cacheTtl,
                'created_at' => time(),
                'query_time' => $queryDuration,
                'row_count' => count($result)
            ];
            
            // Clean up old cache entries if cache is getting large
            $this->cleanupQueryCache();
            
            $totalDuration = microtime(true) - $startTime;
            
            $this->logger->debug('Query executed and cached', [
                'sql' => $this->truncateSql($sql),
                'parameters' => $parameters,
                'row_count' => count($result),
                'query_time_ms' => round($queryDuration * 1000, 2),
                'total_time_ms' => round($totalDuration * 1000, 2)
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            self::$performanceStats['total_query_time'] += microtime(true) - $queryStartTime;
            
            $this->logger->error('Query execution failed', [
                'sql' => $this->truncateSql($sql),
                'parameters' => $parameters,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Execute query without caching (for write operations)
     */
    public function executeQuery(string $sql, array $parameters = []): array
    {
        if ($this->connection === null) {
            $this->logger->warning('Database connection not available for query execution');
            return [];
        }
        
        $startTime = microtime(true);
        
        try {
            $stmt = $this->connection->prepare($sql);
            $result = $stmt->executeQuery($parameters)->fetchAllAssociative();
            
            $duration = microtime(true) - $startTime;
            self::$performanceStats['queries_executed']++;
            self::$performanceStats['total_query_time'] += $duration;
            
            $this->logger->debug('Query executed (no cache)', [
                'sql' => $this->truncateSql($sql),
                'parameters' => $parameters,
                'row_count' => count($result),
                'duration_ms' => round($duration * 1000, 2)
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            self::$performanceStats['total_query_time'] += microtime(true) - $startTime;
            
            $this->logger->error('Query execution failed', [
                'sql' => $this->truncateSql($sql),
                'parameters' => $parameters,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Execute insert/update/delete query
     */
    public function executeWriteQuery(string $sql, array $parameters = []): int
    {
        if ($this->connection === null) {
            $this->logger->warning('Database connection not available for write query execution');
            return 0;
        }
        
        $startTime = microtime(true);
        
        try {
            $stmt = $this->connection->prepare($sql);
            $result = $stmt->executeStatement($parameters);
            
            $duration = microtime(true) - $startTime;
            self::$performanceStats['queries_executed']++;
            self::$performanceStats['total_query_time'] += $duration;
            
            $this->logger->debug('Write query executed', [
                'sql' => $this->truncateSql($sql),
                'parameters' => $parameters,
                'affected_rows' => $result,
                'duration_ms' => round($duration * 1000, 2)
            ]);
            
            // Clear related cache entries for write operations
            $this->clearRelatedCache($sql);
            
            return $result;
            
        } catch (Exception $e) {
            self::$performanceStats['total_query_time'] += microtime(true) - $startTime;
            
            $this->logger->error('Write query execution failed', [
                'sql' => $this->truncateSql($sql),
                'parameters' => $parameters,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get database performance statistics
     */
    public function getPerformanceStats(): array
    {
        $stats = self::$performanceStats;
        
        // Calculate derived metrics
        $stats['cache_hit_ratio'] = $stats['cache_hits'] > 0 
            ? round(($stats['cache_hits'] / ($stats['cache_hits'] + $stats['cache_misses'])) * 100, 2)
            : 0;
        
        $stats['average_query_time'] = $stats['queries_executed'] > 0 
            ? round(($stats['total_query_time'] / $stats['queries_executed']) * 1000, 2)
            : 0;
        
        $stats['average_cache_time'] = $stats['cache_hits'] > 0 
            ? round(($stats['total_cache_time'] / $stats['cache_hits']) * 1000, 2)
            : 0;
        
        $stats['cached_queries_count'] = count(self::$queryCache);
        
        // Add database-specific metrics
        $stats['database_info'] = $this->connection ? [
            'driver' => $this->connection->getDatabasePlatform()->getName(),
            'host' => $this->getDatabaseHost(),
            'database' => $this->getDatabaseName()
        ] : [
            'driver' => 'none',
            'host' => 'none',
            'database' => 'none'
        ];
        
        return $stats;
    }

    /**
     * Clear query cache
     */
    public function clearQueryCache(?string $pattern = null): int
    {
        if ($pattern === null) {
            $clearedCount = count(self::$queryCache);
            self::$queryCache = [];
            $this->logger->info('All query cache cleared', ['cleared_entries' => $clearedCount]);
            return $clearedCount;
        }
        
        $clearedCount = 0;
        foreach (self::$queryCache as $key => $value) {
            if (strpos($key, $pattern) !== false) {
                unset(self::$queryCache[$key]);
                $clearedCount++;
            }
        }
        
        if ($clearedCount > 0) {
            $this->logger->info('Query cache cleared by pattern', [
                'pattern' => $pattern,
                'cleared_entries' => $clearedCount
            ]);
        }
        
        return $clearedCount;
    }

    /**
     * Optimize database tables
     */
    public function optimizeTables(): array
    {
        if ($this->connection === null) {
            $this->logger->warning('Database connection not available for table optimization');
            return ['error' => 'Database connection not available'];
        }
        
        $results = [];
        $startTime = microtime(true);
        
        try {
            // Get all table names
            $tables = $this->connection->createSchemaManager()->listTableNames();
            
            foreach ($tables as $table) {
                try {
                    // Execute OPTIMIZE TABLE (works for most databases)
                    $sql = "OPTIMIZE TABLE `{$table}`";
                    $this->connection->executeStatement($sql);
                    $results[$table] = 'optimized';
                } catch (Exception $e) {
                    $results[$table] = 'failed: ' . $e->getMessage();
                }
            }
            
            $duration = microtime(true) - $startTime;
            
            $this->logger->info('Database tables optimized', [
                'tables_count' => count($tables),
                'duration_ms' => round($duration * 1000, 2),
                'results' => $results
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Database optimization failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
        
        return $results;
    }

    /**
     * Generate cache key for query
     */
    private function generateCacheKey(string $sql, array $parameters): string
    {
        $normalizedSql = $this->normalizeSql($sql);
        $paramsHash = md5(serialize($parameters));
        return 'query_' . md5($normalizedSql . $paramsHash);
    }

    /**
     * Normalize SQL for consistent caching
     */
    private function normalizeSql(string $sql): string
    {
        // Remove extra whitespace and normalize case
        $sql = preg_replace('/\s+/', ' ', trim($sql));
        return strtolower($sql);
    }

    /**
     * Truncate SQL for logging
     */
    private function truncateSql(string $sql, int $length = 100): string
    {
        if (strlen($sql) <= $length) {
            return $sql;
        }
        return substr($sql, 0, $length) . '...';
    }

    /**
     * Clean up old cache entries
     */
    private function cleanupQueryCache(): void
    {
        $maxCacheSize = 1000; // Maximum number of cached queries
        $currentTime = time();
        
        if (count(self::$queryCache) > $maxCacheSize) {
            // Remove oldest entries
            $entries = [];
            foreach (self::$queryCache as $key => $data) {
                $entries[$key] = $data['created_at'];
            }
            
            asort($entries);
            $entriesToRemove = count(self::$queryCache) - $maxCacheSize;
            $removedCount = 0;
            
            foreach (array_keys($entries) as $key) {
                if ($removedCount >= $entriesToRemove) break;
                unset(self::$queryCache[$key]);
                $removedCount++;
            }
            
            $this->logger->debug('Query cache cleaned up', [
                'removed_entries' => $removedCount,
                'remaining_entries' => count(self::$queryCache)
            ]);
        }
        
        // Remove expired entries
        $expiredCount = 0;
        foreach (self::$queryCache as $key => $data) {
            if ($currentTime >= $data['expires_at']) {
                unset(self::$queryCache[$key]);
                $expiredCount++;
            }
        }
        
        if ($expiredCount > 0) {
            $this->logger->debug('Expired query cache entries removed', [
                'expired_entries' => $expiredCount
            ]);
        }
    }

    /**
     * Clear cache entries related to a write operation
     */
    private function clearRelatedCache(string $sql): void
    {
        $sql = $this->normalizeSql($sql);
        
        // Determine which cache entries to clear based on the operation
        $tablesAffected = $this->extractTablesFromSql($sql);
        
        $clearedCount = 0;
        foreach (self::$queryCache as $key => $data) {
            foreach ($tablesAffected as $table) {
                if (strpos($key, $table) !== false) {
                    unset(self::$queryCache[$key]);
                    $clearedCount++;
                    break;
                }
            }
        }
        
        if ($clearedCount > 0) {
            $this->logger->debug('Related cache entries cleared', [
                'sql' => $this->truncateSql($sql),
                'tables_affected' => $tablesAffected,
                'cleared_entries' => $clearedCount
            ]);
        }
    }

    /**
     * Extract table names from SQL statement
     */
    private function extractTablesFromSql(string $sql): array
    {
        $tables = [];
        
        // Simple regex to extract table names from common SQL patterns
        if (preg_match_all('/(?:FROM|JOIN|UPDATE|INTO)\s+[`]?(\w+)[`]?/i', $sql, $matches)) {
            $tables = array_unique($matches[1]);
        }
        
        return $tables;
    }

    /**
     * Get database host information
     */
    private function getDatabaseHost(): string
    {
        if ($this->connection === null) {
            return 'none';
        }
        
        try {
            $params = $this->connection->getParams();
            return $params['host'] ?? $params['path'] ?? 'unknown';
        } catch (Exception $e) {
            return 'unknown';
        }
    }

    /**
     * Get database name
     */
    private function getDatabaseName(): string
    {
        if ($this->connection === null) {
            return 'none';
        }
        
        try {
            $params = $this->connection->getParams();
            return $params['dbname'] ?? basename($params['path'] ?? 'unknown');
        } catch (Exception $e) {
            return 'unknown';
        }
    }
}