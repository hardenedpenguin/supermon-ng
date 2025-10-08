<?php

namespace SupermonNg\Services;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Exception;

/**
 * Middleware Optimization Service
 * 
 * Provides intelligent middleware performance monitoring,
 * request/response optimization, and middleware chain management.
 */
class MiddlewareOptimizationService
{
    private LoggerInterface $logger;
    
    // Performance tracking for middleware chain
    private static array $middlewareStats = [
        'total_requests' => 0,
        'total_response_time' => 0,
        'middleware_times' => [],
        'slow_requests' => 0,
        'error_requests' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'rate_limited_requests' => 0,
        'compressed_responses' => 0,
        'optimized_responses' => 0
    ];

    // Middleware execution tracking
    private static array $middlewareExecutionTimes = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Track middleware execution time
     */
    public function trackMiddleware(string $middlewareName, callable $callback, Request $request): Response
    {
        $startTime = microtime(true);
        
        try {
            $response = $callback($request);
            
            $executionTime = microtime(true) - $startTime;
            
            // Track middleware performance
            if (!isset(self::$middlewareExecutionTimes[$middlewareName])) {
                self::$middlewareExecutionTimes[$middlewareName] = [
                    'count' => 0,
                    'total_time' => 0,
                    'min_time' => PHP_FLOAT_MAX,
                    'max_time' => 0,
                    'avg_time' => 0
                ];
            }
            
            $stats = &self::$middlewareExecutionTimes[$middlewareName];
            $stats['count']++;
            $stats['total_time'] += $executionTime;
            $stats['min_time'] = min($stats['min_time'], $executionTime);
            $stats['max_time'] = max($stats['max_time'], $executionTime);
            $stats['avg_time'] = $stats['total_time'] / $stats['count'];
            
            // Log slow middleware
            if ($executionTime > 0.1) { // 100ms threshold
                $this->logger->warning('Slow middleware detected', [
                    'middleware' => $middlewareName,
                    'execution_time_ms' => round($executionTime * 1000, 2),
                    'uri' => $request->getUri()->getPath()
                ]);
            }
            
            return $response;
            
        } catch (Exception $e) {
            $executionTime = microtime(true) - $startTime;
            
            $this->logger->error('Middleware execution failed', [
                'middleware' => $middlewareName,
                'execution_time_ms' => round($executionTime * 1000, 2),
                'error' => $e->getMessage(),
                'uri' => $request->getUri()->getPath()
            ]);
            
            throw $e;
        }
    }

    /**
     * Track request performance
     */
    public function trackRequest(Request $request, Response $response, float $totalTime): void
    {
        $statusCode = $response->getStatusCode();
        $method = $request->getMethod();
        $uri = $request->getUri()->getPath();
        
        // Update global stats
        self::$middlewareStats['total_requests']++;
        self::$middlewareStats['total_response_time'] += $totalTime;
        
        if ($totalTime > 1.0) { // 1 second threshold
            self::$middlewareStats['slow_requests']++;
        }
        
        if ($statusCode >= 400) {
            self::$middlewareStats['error_requests']++;
        }
        
        // Check for optimization headers
        if ($response->hasHeader('Content-Encoding')) {
            self::$middlewareStats['compressed_responses']++;
        }
        
        if ($response->hasHeader('X-Optimized-By')) {
            self::$middlewareStats['optimized_responses']++;
        }
        
        // Log performance metrics
        $this->logRequestPerformance($request, $response, $totalTime);
    }

    /**
     * Track cache operations
     */
    public function trackCacheOperation(string $operation, bool $hit): void
    {
        if ($hit) {
            self::$middlewareStats['cache_hits']++;
        } else {
            self::$middlewareStats['cache_misses']++;
        }
        
        $this->logger->debug('Cache operation tracked', [
            'operation' => $operation,
            'hit' => $hit
        ]);
    }

    /**
     * Track rate limiting
     */
    public function trackRateLimit(): void
    {
        self::$middlewareStats['rate_limited_requests']++;
        
        $this->logger->info('Rate limit applied', [
            'rate_limited_requests' => self::$middlewareStats['rate_limited_requests']
        ]);
    }

    /**
     * Get middleware performance statistics
     */
    public function getMiddlewareStats(): array
    {
        $stats = self::$middlewareStats;
        
        // Calculate derived metrics
        $stats['average_response_time'] = $stats['total_requests'] > 0 
            ? round(($stats['total_response_time'] / $stats['total_requests']) * 1000, 2)
            : 0;
        
        $stats['slow_request_percentage'] = $stats['total_requests'] > 0 
            ? round(($stats['slow_requests'] / $stats['total_requests']) * 100, 2)
            : 0;
        
        $stats['error_percentage'] = $stats['total_requests'] > 0 
            ? round(($stats['error_requests'] / $stats['total_requests']) * 100, 2)
            : 0;
        
        $stats['cache_hit_ratio'] = ($stats['cache_hits'] + $stats['cache_misses']) > 0 
            ? round(($stats['cache_hits'] / ($stats['cache_hits'] + $stats['cache_misses'])) * 100, 2)
            : 0;
        
        $stats['optimization_ratio'] = $stats['total_requests'] > 0 
            ? round(($stats['optimized_responses'] / $stats['total_requests']) * 100, 2)
            : 0;
        
        $stats['compression_ratio'] = $stats['total_requests'] > 0 
            ? round(($stats['compressed_responses'] / $stats['total_requests']) * 100, 2)
            : 0;
        
        // Add middleware execution times
        $stats['middleware_performance'] = self::$middlewareExecutionTimes;
        
        return $stats;
    }

    /**
     * Get slow middleware analysis
     */
    public function getSlowMiddlewareAnalysis(): array
    {
        $slowMiddleware = [];
        $threshold = 0.05; // 50ms threshold
        
        foreach (self::$middlewareExecutionTimes as $name => $stats) {
            if ($stats['avg_time'] > $threshold) {
                $slowMiddleware[] = [
                    'middleware' => $name,
                    'avg_time_ms' => round($stats['avg_time'] * 1000, 2),
                    'min_time_ms' => round($stats['min_time'] * 1000, 2),
                    'max_time_ms' => round($stats['max_time'] * 1000, 2),
                    'execution_count' => $stats['count'],
                    'total_time_ms' => round($stats['total_time'] * 1000, 2)
                ];
            }
        }
        
        // Sort by average time descending
        usort($slowMiddleware, function($a, $b) {
            return $b['avg_time_ms'] <=> $a['avg_time_ms'];
        });
        
        return $slowMiddleware;
    }

    /**
     * Optimize middleware chain order
     */
    public function getOptimalMiddlewareOrder(): array
    {
        $currentOrder = array_keys(self::$middlewareExecutionTimes);
        
        // Sort by average execution time (fastest first)
        usort($currentOrder, function($a, $b) {
            $timeA = self::$middlewareExecutionTimes[$a]['avg_time'] ?? 0;
            $timeB = self::$middlewareExecutionTimes[$b]['avg_time'] ?? 0;
            return $timeA <=> $timeB;
        });
        
        return [
            'current_order' => array_keys(self::$middlewareExecutionTimes),
            'recommended_order' => $currentOrder,
            'potential_savings_ms' => $this->calculatePotentialSavings($currentOrder)
        ];
    }

    /**
     * Log request performance
     */
    private function logRequestPerformance(Request $request, Response $response, float $totalTime): void
    {
        $method = $request->getMethod();
        $uri = $request->getUri()->getPath();
        $statusCode = $response->getStatusCode();
        $duration = round($totalTime * 1000, 2);
        
        // Determine log level based on performance
        if ($totalTime > 2.0) {
            $level = 'error';
        } elseif ($totalTime > 1.0) {
            $level = 'warning';
        } elseif ($totalTime > 0.5) {
            $level = 'info';
        } else {
            $level = 'debug';
        }
        
        $this->logger->log($level, 'Request Performance', [
            'method' => $method,
            'uri' => $uri,
            'status_code' => $statusCode,
            'duration_ms' => $duration,
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'optimized' => $response->hasHeader('X-Optimized-By'),
            'compressed' => $response->hasHeader('Content-Encoding')
        ]);
    }

    /**
     * Calculate potential time savings from reordering middleware
     */
    private function calculatePotentialSavings(array $recommendedOrder): float
    {
        $currentTime = 0;
        $optimizedTime = 0;
        
        foreach (array_keys(self::$middlewareExecutionTimes) as $index => $middleware) {
            $avgTime = self::$middlewareExecutionTimes[$middleware]['avg_time'] ?? 0;
            $currentTime += $avgTime * ($index + 1); // Weight by position
        }
        
        foreach ($recommendedOrder as $index => $middleware) {
            $avgTime = self::$middlewareExecutionTimes[$middleware]['avg_time'] ?? 0;
            $optimizedTime += $avgTime * ($index + 1); // Weight by position
        }
        
        return round(($currentTime - $optimizedTime) * 1000, 2); // Return in milliseconds
    }

    /**
     * Reset middleware statistics
     */
    public function resetStats(): void
    {
        self::$middlewareStats = [
            'total_requests' => 0,
            'total_response_time' => 0,
            'middleware_times' => [],
            'slow_requests' => 0,
            'error_requests' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'rate_limited_requests' => 0,
            'compressed_responses' => 0,
            'optimized_responses' => 0
        ];
        
        self::$middlewareExecutionTimes = [];
        
        $this->logger->info('Middleware optimization statistics reset');
    }

    /**
     * Get middleware health status
     */
    public function getMiddlewareHealth(): array
    {
        $stats = $this->getMiddlewareStats();
        
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'recommendations' => []
        ];
        
        // Check for performance issues
        if ($stats['average_response_time'] > 500) {
            $health['status'] = 'warning';
            $health['issues'][] = 'High average response time: ' . $stats['average_response_time'] . 'ms';
            $health['recommendations'][] = 'Consider optimizing slow middleware or adding caching';
        }
        
        if ($stats['slow_request_percentage'] > 10) {
            $health['status'] = 'warning';
            $health['issues'][] = 'High percentage of slow requests: ' . $stats['slow_request_percentage'] . '%';
            $health['recommendations'][] = 'Investigate and optimize slow request patterns';
        }
        
        if ($stats['error_percentage'] > 5) {
            $health['status'] = 'critical';
            $health['issues'][] = 'High error rate: ' . $stats['error_percentage'] . '%';
            $health['recommendations'][] = 'Investigate and fix error sources';
        }
        
        if ($stats['cache_hit_ratio'] < 50 && $stats['cache_hits'] + $stats['cache_misses'] > 100) {
            $health['issues'][] = 'Low cache hit ratio: ' . $stats['cache_hit_ratio'] . '%';
            $health['recommendations'][] = 'Review cache configuration and TTL settings';
        }
        
        return $health;
    }
}
