<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Doctrine\DBAL\Connection;

/**
 * Database optimization service for query caching and optimization
 */
class DatabaseOptimizationService
{
    private LoggerInterface $logger;
    private CacheInterface $cache;
    private Connection $connection;
    private int $defaultTtl;

    public function __construct(
        LoggerInterface $logger,
        CacheInterface $cache,
        Connection $connection,
        int $defaultTtl = 300
    ) {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->connection = $connection;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * Execute a cached query with automatic cache invalidation
     */
    public function executeCachedQuery(
        string $query,
        array $params = [],
        array $types = [],
        ?int $ttl = null,
        ?string $cacheKey = null
    ): array {
        $cacheKey = $cacheKey ?: 'query_' . md5($query . serialize($params));
        
        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($query, $params, $types, $ttl) {
                $item->expiresAfter($ttl ?? $this->defaultTtl);
                
                $this->logger->debug('Executing cached query', [
                    'query' => $query,
                    'params' => $params,
                    'cache_key' => $cacheKey
                ]);
                
                $result = $this->connection->executeQuery($query, $params, $types);
                return $result->fetchAllAssociative();
            });
        } catch (\Exception $e) {
            $this->logger->error('Failed to execute cached query', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to direct query execution
            $result = $this->connection->executeQuery($query, $params, $types);
            return $result->fetchAllAssociative();
        }
    }

    /**
     * Execute a single-row cached query
     */
    public function executeCachedQueryOne(
        string $query,
        array $params = [],
        array $types = [],
        ?int $ttl = null,
        ?string $cacheKey = null
    ): ?array {
        $cacheKey = $cacheKey ?: 'query_one_' . md5($query . serialize($params));
        
        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($query, $params, $types, $ttl) {
                $item->expiresAfter($ttl ?? $this->defaultTtl);
                
                $this->logger->debug('Executing cached single-row query', [
                    'query' => $query,
                    'params' => $params,
                    'cache_key' => $cacheKey
                ]);
                
                $result = $this->connection->executeQuery($query, $params, $types);
                $row = $result->fetchAssociative();
                return $row ?: null;
            });
        } catch (\Exception $e) {
            $this->logger->error('Failed to execute cached single-row query', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to direct query execution
            $result = $this->connection->executeQuery($query, $params, $types);
            $row = $result->fetchAssociative();
            return $row ?: null;
        }
    }

    /**
     * Get cached node information
     */
    public function getCachedNodeInfo(string $nodeId, ?int $ttl = 300): ?array
    {
        $cacheKey = "node_info_{$nodeId}";
        
        return $this->executeCachedQueryOne(
            "SELECT * FROM nodes WHERE node_id = ?",
            [$nodeId],
            [\PDO::PARAM_STR],
            $ttl,
            $cacheKey
        );
    }

    /**
     * Get cached node list with optimized query
     */
    public function getCachedNodeList(?string $username = null, ?int $ttl = 60): array
    {
        $cacheKey = "node_list_" . ($username ?? 'anonymous');
        
        $query = "
            SELECT 
                n.node_id,
                n.callsign,
                n.description,
                n.location,
                n.status,
                n.last_heard,
                n.is_online,
                n.is_keyed,
                n.updated_at
            FROM nodes n
        ";
        
        $params = [];
        $types = [];
        
        // Add user-specific filtering if username provided
        if ($username) {
            $query .= " 
                LEFT JOIN user_node_permissions unp ON n.node_id = unp.node_id 
                WHERE (unp.username = ? OR unp.username IS NULL)
            ";
            $params[] = $username;
            $types[] = \PDO::PARAM_STR;
        }
        
        $query .= " ORDER BY n.callsign ASC";
        
        return $this->executeCachedQuery($query, $params, $types, $ttl, $cacheKey);
    }

    /**
     * Get cached system statistics
     */
    public function getCachedSystemStats(?int $ttl = 30): array
    {
        $cacheKey = 'system_stats';
        
        $queries = [
            'total_nodes' => "SELECT COUNT(*) as count FROM nodes",
            'online_nodes' => "SELECT COUNT(*) as count FROM nodes WHERE is_online = 1",
            'keyed_nodes' => "SELECT COUNT(*) as count FROM nodes WHERE is_keyed = 1",
            'recent_activity' => "
                SELECT COUNT(*) as count 
                FROM nodes 
                WHERE updated_at > datetime('now', '-1 hour')
            "
        ];
        
        $stats = [];
        foreach ($queries as $key => $query) {
            $result = $this->executeCachedQueryOne($query, [], [], $ttl, "stats_{$key}");
            $stats[$key] = $result['count'] ?? 0;
        }
        
        return $stats;
    }

    /**
     * Get cached user permissions
     */
    public function getCachedUserPermissions(string $username, ?int $ttl = 600): array
    {
        $cacheKey = "user_permissions_{$username}";
        
        return $this->executeCachedQuery(
            "
                SELECT 
                    unp.node_id,
                    unp.permission_level,
                    n.callsign,
                    n.description
                FROM user_node_permissions unp
                JOIN nodes n ON unp.node_id = n.node_id
                WHERE unp.username = ?
            ",
            [$username],
            [\PDO::PARAM_STR],
            $ttl,
            $cacheKey
        );
    }

    /**
     * Cache query results with custom key and TTL
     */
    public function cacheQueryResult(string $key, array $data, ?int $ttl = null): void
    {
        try {
            $this->cache->get($key, function (ItemInterface $item) use ($data, $ttl) {
                $item->expiresAfter($ttl ?? $this->defaultTtl);
                return $data;
            });
            
            $this->logger->debug('Cached query result', [
                'key' => $key,
                'data_count' => count($data),
                'ttl' => $ttl ?? $this->defaultTtl
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to cache query result', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get cached query result
     */
    public function getCachedQueryResult(string $key): ?array
    {
        try {
            return $this->cache->get($key, function () {
                return null; // Return null if not in cache
            });
        } catch (\Exception $e) {
            $this->logger->error('Failed to get cached query result', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Invalidate cache for specific keys or patterns
     */
    public function invalidateCache(string $pattern): bool
    {
        try {
            return $this->cache->delete($pattern);
        } catch (\Exception $e) {
            $this->logger->error('Failed to invalidate cache', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Invalidate node-related cache
     */
    public function invalidateNodeCache(string $nodeId): void
    {
        $patterns = [
            "node_info_{$nodeId}",
            "node_list_*",
            "stats_*"
        ];
        
        foreach ($patterns as $pattern) {
            $this->invalidateCache($pattern);
        }
        
        $this->logger->debug('Invalidated node cache', ['node_id' => $nodeId]);
    }

    /**
     * Invalidate user-related cache
     */
    public function invalidateUserCache(string $username): void
    {
        $patterns = [
            "user_permissions_{$username}",
            "node_list_{$username}"
        ];
        
        foreach ($patterns as $pattern) {
            $this->invalidateCache($pattern);
        }
        
        $this->logger->debug('Invalidated user cache', ['username' => $username]);
    }

    /**
     * Get database performance statistics
     */
    public function getDatabaseStats(): array
    {
        try {
            $stats = [];
            
            // Get table sizes and row counts
            $tables = ['nodes', 'user_node_permissions', 'user_sessions'];
            foreach ($tables as $table) {
                $result = $this->executeCachedQueryOne(
                    "SELECT COUNT(*) as count FROM {$table}",
                    [],
                    [],
                    3600 // Cache for 1 hour
                );
                $stats["{$table}_count"] = $result['count'] ?? 0;
            }
            
            // Get cache hit/miss statistics (if available)
            $stats['cache_enabled'] = true;
            $stats['default_ttl'] = $this->defaultTtl;
            
            return $stats;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get database stats', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Optimize database queries with prepared statements and indexing hints
     */
    public function executeOptimizedQuery(
        string $query,
        array $params = [],
        array $types = [],
        ?string $indexHint = null
    ): array {
        // Add index hints if provided
        if ($indexHint && strpos($query, 'FROM') !== false) {
            $query = str_replace('FROM ', "FROM {$indexHint} ", $query);
        }
        
        try {
            $this->logger->debug('Executing optimized query', [
                'query' => $query,
                'params_count' => count($params),
                'index_hint' => $indexHint
            ]);
            
            $result = $this->connection->executeQuery($query, $params, $types);
            return $result->fetchAllAssociative();
        } catch (\Exception $e) {
            $this->logger->error('Failed to execute optimized query', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
