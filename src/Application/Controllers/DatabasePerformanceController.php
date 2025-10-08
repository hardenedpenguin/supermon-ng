<?php

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\DatabaseOptimizationService;
use SupermonNg\Services\CacheOptimizationService;

/**
 * Database Performance monitoring controller
 * 
 * Provides endpoints for monitoring database and cache performance,
 * optimization statistics, and system health.
 */
class DatabasePerformanceController
{
    private LoggerInterface $logger;
    private DatabaseOptimizationService $dbService;
    private CacheOptimizationService $cacheService;

    public function __construct(
        LoggerInterface $logger,
        DatabaseOptimizationService $dbService,
        CacheOptimizationService $cacheService
    ) {
        $this->logger = $logger;
        $this->dbService = $dbService;
        $this->cacheService = $cacheService;
    }

    /**
     * Get comprehensive database performance metrics
     */
    public function getMetrics(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching database performance metrics');
        
        try {
            $metrics = [
                'timestamp' => date('c'),
                'database' => $this->dbService->getPerformanceStats(),
                'cache' => $this->cacheService->getPerformanceStats(),
                'memory_cache' => $this->cacheService->getMemoryCacheStats(),
                'system' => $this->getSystemMetrics(),
                'optimization' => $this->getOptimizationMetrics()
            ];
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $metrics
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Error fetching database performance metrics', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to fetch database performance metrics: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Get database performance stats
     */
    public function getDatabaseStats(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching database performance stats');
        
        try {
            $stats = $this->dbService->getPerformanceStats();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $stats
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Error fetching database stats', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to fetch database stats: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Get cache performance stats
     */
    public function getCacheStats(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching cache performance stats');
        
        try {
            $stats = $this->cacheService->getPerformanceStats();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $stats
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Error fetching cache stats', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to fetch cache stats: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Clear database query cache
     */
    public function clearQueryCache(Request $request, Response $response): Response
    {
        $this->logger->info('Clearing database query cache');
        
        try {
            $queryParams = $request->getQueryParams();
            $pattern = $queryParams['pattern'] ?? null;
            
            $clearedCount = $this->dbService->clearQueryCache($pattern);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Database query cache cleared successfully',
                'data' => [
                    'cleared_entries' => $clearedCount,
                    'pattern' => $pattern
                ]
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Error clearing query cache', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to clear query cache: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Clear all caches
     */
    public function clearAllCaches(Request $request, Response $response): Response
    {
        $this->logger->info('Clearing all caches');
        
        try {
            $queryCacheCleared = $this->dbService->clearQueryCache();
            $allCachesCleared = $this->cacheService->clearAll();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'All caches cleared successfully',
                'data' => [
                    'query_cache_cleared' => $queryCacheCleared,
                    'all_caches_cleared' => $allCachesCleared
                ]
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Error clearing all caches', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to clear all caches: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Optimize database tables
     */
    public function optimizeTables(Request $request, Response $response): Response
    {
        $this->logger->info('Optimizing database tables');
        
        try {
            $results = $this->dbService->optimizeTables();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Database tables optimized successfully',
                'data' => $results
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Error optimizing database tables', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to optimize database tables: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Clean up memory cache
     */
    public function cleanupMemoryCache(Request $request, Response $response): Response
    {
        $this->logger->info('Cleaning up memory cache');
        
        try {
            $cleanedCount = $this->cacheService->cleanupMemoryCache();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Memory cache cleaned up successfully',
                'data' => [
                    'cleaned_entries' => $cleanedCount
                ]
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Error cleaning up memory cache', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to cleanup memory cache: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Get system metrics
     */
    private function getSystemMetrics(): array
    {
        return [
            'load_average' => sys_getloadavg(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'php_version' => PHP_VERSION,
            'server_time' => time(),
            'timezone' => date_default_timezone_get()
        ];
    }

    /**
     * Get optimization metrics
     */
    private function getOptimizationMetrics(): array
    {
        return [
            'optimizations_active' => [
                'database_query_caching' => true,
                'multi_level_cache' => true,
                'data_compression' => true,
                'memory_cache' => true,
                'connection_pooling' => true,
                'automatic_cleanup' => true
            ],
            'performance_improvements' => [
                'database_queries' => 'Intelligent query caching with automatic invalidation',
                'cache_layers' => 'Multi-level caching (memory + persistent)',
                'data_compression' => 'Automatic compression for large data sets',
                'memory_management' => 'Smart memory cache with LRU eviction',
                'connection_optimization' => 'Connection pooling and reuse',
                'automatic_maintenance' => 'Automatic cache cleanup and optimization'
            ]
        ];
    }
}
