<?php

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\HttpOptimizationService;
use SupermonNg\Services\MiddlewareOptimizationService;

/**
 * HTTP Performance monitoring controller
 * 
 * Provides endpoints for monitoring HTTP performance,
 * middleware optimization, and response statistics.
 */
class HttpPerformanceController
{
    private LoggerInterface $logger;
    private HttpOptimizationService $httpService;
    private MiddlewareOptimizationService $middlewareService;

    public function __construct(
        LoggerInterface $logger,
        HttpOptimizationService $httpService,
        MiddlewareOptimizationService $middlewareService
    ) {
        $this->logger = $logger;
        $this->httpService = $httpService;
        $this->middlewareService = $middlewareService;
    }

    /**
     * Get comprehensive HTTP performance metrics
     */
    public function getMetrics(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching HTTP performance metrics');
        
        try {
            $metrics = [
                'timestamp' => date('c'),
                'http_optimization' => $this->httpService->getPerformanceStats(),
                'middleware' => $this->middlewareService->getMiddlewareStats(),
                'middleware_health' => $this->middlewareService->getMiddlewareHealth(),
                'slow_middleware' => $this->middlewareService->getSlowMiddlewareAnalysis(),
                'middleware_optimization' => $this->middlewareService->getOptimalMiddlewareOrder(),
                'system' => $this->getSystemMetrics(),
                'optimization' => $this->getOptimizationMetrics()
            ];
            
            return $this->httpService->optimizeJsonResponse($response, [
                'success' => true,
                'data' => $metrics
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error fetching HTTP performance metrics', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to fetch HTTP performance metrics: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Get HTTP optimization stats
     */
    public function getHttpStats(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching HTTP optimization stats');
        
        try {
            $stats = $this->httpService->getPerformanceStats();
            
            return $this->httpService->optimizeJsonResponse($response, [
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error fetching HTTP stats', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to fetch HTTP stats: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Get middleware performance stats
     */
    public function getMiddlewareStats(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching middleware performance stats');
        
        try {
            $stats = $this->middlewareService->getMiddlewareStats();
            
            return $this->httpService->optimizeJsonResponse($response, [
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error fetching middleware stats', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to fetch middleware stats: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Get slow middleware analysis
     */
    public function getSlowMiddleware(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching slow middleware analysis');
        
        try {
            $analysis = $this->middlewareService->getSlowMiddlewareAnalysis();
            
            return $this->httpService->optimizeJsonResponse($response, [
                'success' => true,
                'data' => $analysis
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error fetching slow middleware analysis', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to fetch slow middleware analysis: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Get middleware optimization recommendations
     */
    public function getMiddlewareOptimization(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching middleware optimization recommendations');
        
        try {
            $optimization = $this->middlewareService->getOptimalMiddlewareOrder();
            
            return $this->httpService->optimizeJsonResponse($response, [
                'success' => true,
                'data' => $optimization
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error fetching middleware optimization', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to fetch middleware optimization: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Reset HTTP optimization statistics
     */
    public function resetHttpStats(Request $request, Response $response): Response
    {
        $this->logger->info('Resetting HTTP optimization statistics');
        
        try {
            $this->httpService->resetStats();
            
            return $this->httpService->optimizeJsonResponse($response, [
                'success' => true,
                'message' => 'HTTP optimization statistics reset successfully'
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error resetting HTTP stats', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to reset HTTP stats: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Reset middleware statistics
     */
    public function resetMiddlewareStats(Request $request, Response $response): Response
    {
        $this->logger->info('Resetting middleware statistics');
        
        try {
            $this->middlewareService->resetStats();
            
            return $this->httpService->optimizeJsonResponse($response, [
                'success' => true,
                'message' => 'Middleware statistics reset successfully'
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error resetting middleware stats', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to reset middleware stats: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Test HTTP optimization with sample data
     */
    public function testOptimization(Request $request, Response $response): Response
    {
        $this->logger->info('Testing HTTP optimization');
        
        try {
            $testData = [
                'timestamp' => date('c'),
                'test_data' => str_repeat('This is test data for optimization. ', 100),
                'performance' => [
                    'compression_enabled' => $this->httpService->clientAcceptsCompression($request),
                    'is_api_request' => strpos($request->getUri()->getPath(), '/api/') === 0,
                    'request_method' => $request->getMethod(),
                    'user_agent' => $request->getHeaderLine('User-Agent')
                ]
            ];
            
            // Test optimization
            $optimizedResponse = $this->httpService->optimizeJsonResponse($response, $testData, 60);
            
            return $optimizedResponse;
            
        } catch (\Exception $e) {
            $this->logger->error('Error testing HTTP optimization', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to test HTTP optimization: ' . $e->getMessage()
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
            'timezone' => date_default_timezone_get(),
            'opcache_enabled' => extension_loaded('Zend OPcache') && ini_get('opcache.enable'),
            'gzip_enabled' => extension_loaded('zlib')
        ];
    }

    /**
     * Get optimization metrics
     */
    private function getOptimizationMetrics(): array
    {
        return [
            'optimizations_active' => [
                'http_response_compression' => true,
                'http_caching_headers' => true,
                'middleware_performance_monitoring' => true,
                'request_preprocessing' => true,
                'response_optimization' => true,
                'security_headers' => true,
                'cors_headers' => true,
                'etag_generation' => true
            ],
            'performance_improvements' => [
                'response_compression' => 'Automatic gzip compression for compatible clients',
                'caching_headers' => 'ETag and Cache-Control headers for better caching',
                'middleware_monitoring' => 'Real-time middleware performance tracking',
                'request_optimization' => 'Intelligent request preprocessing and analysis',
                'response_optimization' => 'Automatic response optimization and compression',
                'security_enhancement' => 'Security headers for better protection',
                'cors_optimization' => 'Optimized CORS headers for API endpoints',
                'performance_monitoring' => 'Comprehensive performance metrics and analysis'
            ]
        ];
    }
}
