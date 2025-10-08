<?php

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\ConfigurationCacheService;
use SupermonNg\Services\LazyFileLoaderService;
use SupermonNg\Services\AstdbCacheService;
use SupermonNg\Services\IncludeManagerService;

/**
 * Performance monitoring controller
 * 
 * Provides endpoints for monitoring optimization performance
 * and system efficiency metrics.
 */
class PerformanceController
{
    private LoggerInterface $logger;
    private ConfigurationCacheService $configService;
    private LazyFileLoaderService $fileService;
    private AstdbCacheService $astdbService;
    private IncludeManagerService $includeService;

    public function __construct(
        LoggerInterface $logger,
        ConfigurationCacheService $configService,
        LazyFileLoaderService $fileService,
        AstdbCacheService $astdbService,
        IncludeManagerService $includeService
    ) {
        $this->logger = $logger;
        $this->configService = $configService;
        $this->fileService = $fileService;
        $this->astdbService = $astdbService;
        $this->includeService = $includeService;
    }

    /**
     * Get comprehensive performance metrics
     */
    public function getMetrics(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching performance metrics');
        
        try {
            $metrics = [
                'timestamp' => date('c'),
                'system' => $this->getSystemMetrics(),
                'configuration' => $this->configService->getPerformanceStats(),
                'file_loading' => $this->fileService->getPerformanceStats(),
                'include_management' => $this->includeService->getPerformanceStats(),
                'astdb' => $this->astdbService->getCacheStats(),
                'memory' => $this->getMemoryMetrics(),
                'optimization' => $this->getOptimizationMetrics()
            ];
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $metrics
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Error fetching performance metrics', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to fetch performance metrics: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Get configuration performance stats
     */
    public function getConfigStats(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching configuration performance stats');
        
        try {
            $stats = $this->configService->getPerformanceStats();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $stats
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Error fetching configuration stats', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to fetch configuration stats: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Get file loading performance stats
     */
    public function getFileStats(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching file loading performance stats');
        
        try {
            $stats = $this->fileService->getPerformanceStats();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $stats
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Error fetching file stats', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to fetch file stats: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Clear all optimization caches
     */
    public function clearCaches(Request $request, Response $response): Response
    {
        $this->logger->info('Clearing all optimization caches');
        
        try {
            $this->configService->clearCache();
            $this->fileService->clearCache();
            $this->includeService->clearCache();
            $this->astdbService->clearApplicationCache();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'All optimization caches cleared successfully'
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Error clearing caches', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to clear caches: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Clean up old cache entries
     */
    public function cleanupCache(Request $request, Response $response): Response
    {
        $this->logger->info('Performing cache cleanup');
        
        try {
            $cleanedFiles = $this->fileService->cleanupCache(3600); // 1 hour
            $cleanedIncludes = $this->includeService->cleanupCache(3600); // 1 hour
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => "Cache cleanup completed",
                'data' => [
                    'cleaned_file_entries' => $cleanedFiles,
                    'cleaned_include_entries' => $cleanedIncludes,
                    'timestamp' => date('c')
                ]
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Error during cache cleanup', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to cleanup cache: ' . $e->getMessage()
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
     * Get memory metrics
     */
    private function getMemoryMetrics(): array
    {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        return [
            'current_usage' => $usage,
            'current_usage_mb' => round($usage / 1024 / 1024, 2),
            'peak_usage' => $peak,
            'peak_usage_mb' => round($peak / 1024 / 1024, 2),
            'memory_limit' => $limit,
            'memory_limit_mb' => round($limit / 1024 / 1024, 2),
            'usage_percentage' => $limit > 0 ? round(($usage / $limit) * 100, 2) : 0
        ];
    }

    /**
     * Get optimization metrics
     */
    private function getOptimizationMetrics(): array
    {
        return [
            'optimizations_active' => [
                'configuration_caching' => true,
                'lazy_file_loading' => true,
                'include_management' => true,
                'astdb_caching' => true,
                'frontend_polling' => true,
                'request_batching' => true,
                'csrf_token_caching' => true
            ],
            'performance_improvements' => [
                'configuration_loading' => 'Eliminated repeated include_once calls',
                'file_operations' => 'Intelligent caching with modification time checks',
                'include_management' => 'Smart caching of PHP includes and requires',
                'astdb_access' => 'Multi-level caching with 84.8% compression',
                'frontend_polling' => 'Adaptive frequency based on user activity',
                'api_requests' => 'Smart batching and deduplication',
                'csrf_tokens' => '1-hour caching with proactive refresh'
            ]
        ];
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
}
