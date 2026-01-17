<?php

namespace SupermonNg\Services;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Exception;

/**
 * HTTP Optimization Service
 * 
 * Provides intelligent HTTP response optimization including compression,
 * caching headers, ETag generation, and performance monitoring.
 */
class HttpOptimizationService
{
    private LoggerInterface $logger;
    
    // Performance tracking
    private static array $performanceStats = [
        'responses_optimized' => 0,
        'compression_operations' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'etag_generated' => 0,
        'total_compression_time' => 0,
        'total_optimization_time' => 0,
        'bytes_saved' => 0
    ];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Optimize HTTP response with compression and caching headers
     */
    public function optimizeResponse(Response $response, string $content = '', bool $enableCompression = true, bool $enableCaching = true, int $cacheTtl = 3600): Response
    {
        $startTime = microtime(true);
        $originalSize = strlen($content);
        
        try {
            // Generate ETag for caching
            if ($enableCaching && !empty($content)) {
                $etag = $this->generateETag($content);
                $response = $response->withHeader('ETag', $etag);
                $response = $response->withHeader('Cache-Control', "public, max-age={$cacheTtl}");
                $response = $response->withHeader('Expires', gmdate('D, d M Y H:i:s', time() + $cacheTtl) . ' GMT');
                self::$performanceStats['etag_generated']++;
            }
            
            // Apply compression if enabled and supported
            if ($enableCompression && !empty($content)) {
                $compressedContent = $this->compressContent($content);
                if ($compressedContent !== false) {
                    $response->getBody()->write($compressedContent);
                    $response = $response->withHeader('Content-Encoding', 'gzip');
                    $response = $response->withHeader('Vary', 'Accept-Encoding');
                    
                    $bytesSaved = $originalSize - strlen($compressedContent);
                    self::$performanceStats['bytes_saved'] += $bytesSaved;
                    self::$performanceStats['compression_operations']++;
                    
                    $this->logger->debug('Response compressed', [
                        'original_size' => $originalSize,
                        'compressed_size' => strlen($compressedContent),
                        'compression_ratio' => round((1 - strlen($compressedContent) / $originalSize) * 100, 1) . '%',
                        'bytes_saved' => $bytesSaved
                    ]);
                } else {
                    $response->getBody()->write($content);
                }
            } else {
                $response->getBody()->write($content);
            }
            
            // Add performance headers
            $response = $this->addPerformanceHeaders($response, $startTime);
            
            self::$performanceStats['responses_optimized']++;
            self::$performanceStats['total_optimization_time'] += microtime(true) - $startTime;
            
            return $response;
            
        } catch (Exception $e) {
            $this->logger->error('Response optimization failed', [
                'error' => $e->getMessage(),
                'content_length' => $originalSize
            ]);
            
            // Fallback to unoptimized response
            $response->getBody()->write($content);
            return $response;
        }
    }

    /**
     * Check if client accepts compression
     */
    public function clientAcceptsCompression(Request $request): bool
    {
        $acceptEncoding = $request->getHeaderLine('Accept-Encoding');
        return strpos($acceptEncoding, 'gzip') !== false || strpos($acceptEncoding, 'deflate') !== false;
    }

    /**
     * Check if request has valid ETag (cache hit)
     */
    public function checkETagCache(Request $request, string $content): ?Response
    {
        $clientETag = $request->getHeaderLine('If-None-Match');
        if (empty($clientETag) || empty($content)) {
            return null;
        }
        
        $serverETag = $this->generateETag($content);
        
        if ($clientETag === $serverETag) {
            self::$performanceStats['cache_hits']++;
            $this->logger->debug('ETag cache hit', [
                'etag' => $serverETag,
                'content_length' => strlen($content)
            ]);
            
            $response = new \Slim\Psr7\Response();
            return $response
                ->withStatus(304)
                ->withHeader('ETag', $serverETag)
                ->withHeader('Cache-Control', 'public');
        }
        
        self::$performanceStats['cache_misses']++;
        return null;
    }

    /**
     * Add security headers to response
     */
    public function addSecurityHeaders(Response $response): Response
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
        ];
        
        foreach ($headers as $name => $value) {
            if (!$response->hasHeader($name)) {
                $response = $response->withHeader($name, $value);
            }
        }
        
        return $response;
    }

    /**
     * Add CORS headers for API endpoints
     */
    public function addCorsHeaders(Response $response, Request $request): Response
    {
        $origin = $request->getHeaderLine('Origin');
        $allowedOrigins = ['http://localhost:3000', 'http://127.0.0.1:3000'];
        $originHost = $origin ? parse_url($origin, PHP_URL_HOST) : null;
        $requestHost = $request->getUri()->getHost();
        
        if ($originHost && $requestHost && $originHost === $requestHost) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        } elseif (in_array($origin, $allowedOrigins, true)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        }
        
        $response = $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response = $response->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        $response = $response->withHeader('Access-Control-Max-Age', '86400');
        
        return $response;
    }

    /**
     * Optimize JSON response
     */
    public function optimizeJsonResponse(Response $response, array $data, int $cacheTtl = 300): Response
    {
        $jsonContent = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('JSON encoding failed', [
                'error' => json_last_error_msg(),
                'data_size' => count($data)
            ]);
            
            $jsonContent = json_encode(['error' => 'JSON encoding failed']);
        }
        
        $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response = $this->optimizeResponse($response, $jsonContent, true, true, $cacheTtl);
        
        return $response;
    }

    /**
     * Get HTTP optimization performance statistics
     */
    public function getPerformanceStats(): array
    {
        $stats = self::$performanceStats;
        
        // Calculate derived metrics
        $stats['average_optimization_time'] = $stats['responses_optimized'] > 0 
            ? round(($stats['total_optimization_time'] / $stats['responses_optimized']) * 1000, 2)
            : 0;
        
        $stats['average_compression_time'] = $stats['compression_operations'] > 0 
            ? round(($stats['total_compression_time'] / $stats['compression_operations']) * 1000, 2)
            : 0;
        
        $stats['cache_hit_ratio'] = ($stats['cache_hits'] + $stats['cache_misses']) > 0 
            ? round(($stats['cache_hits'] / ($stats['cache_hits'] + $stats['cache_misses'])) * 100, 2)
            : 0;
        
        $stats['compression_ratio'] = $stats['responses_optimized'] > 0 
            ? round(($stats['bytes_saved'] / ($stats['responses_optimized'] * 1024)) * 100, 2)
            : 0;
        
        $stats['total_bytes_saved_mb'] = round($stats['bytes_saved'] / 1024 / 1024, 2);
        
        return $stats;
    }

    /**
     * Compress content using gzip
     */
    private function compressContent(string $content): string|false
    {
        $startTime = microtime(true);
        
        $compressed = gzencode($content, 9, FORCE_GZIP);
        
        self::$performanceStats['total_compression_time'] += microtime(true) - $startTime;
        
        return $compressed;
    }

    /**
     * Generate ETag for content
     */
    private function generateETag(string $content): string
    {
        return '"' . md5($content) . '"';
    }

    /**
     * Add performance monitoring headers
     */
    private function addPerformanceHeaders(Response $response, float $startTime): Response
    {
        $processingTime = round((microtime(true) - $startTime) * 1000, 2);
        
        $response = $response->withHeader('X-Response-Time', $processingTime . 'ms');
        $response = $response->withHeader('X-Optimized-By', 'SupermonNG-Optimizer');
        
        return $response;
    }

    /**
     * Preprocess request for optimization
     */
    public function preprocessRequest(Request $request): array
    {
        $optimization = [
            'accepts_compression' => $this->clientAcceptsCompression($request),
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'accept_language' => $request->getHeaderLine('Accept-Language'),
            'connection' => $request->getHeaderLine('Connection'),
            'is_api_request' => strpos($request->getUri()->getPath(), '/api/') === 0,
            'is_static_resource' => $this->isStaticResource($request)
        ];
        
        $this->logger->debug('Request preprocessed for optimization', [
            'path' => $request->getUri()->getPath(),
            'optimization' => $optimization
        ]);
        
        return $optimization;
    }

    /**
     * Check if request is for static resource
     */
    private function isStaticResource(Request $request): bool
    {
        $path = $request->getUri()->getPath();
        $staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf'];
        
        foreach ($staticExtensions as $extension) {
            if (str_ends_with($path, $extension)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Reset performance statistics
     */
    public function resetStats(): void
    {
        self::$performanceStats = [
            'responses_optimized' => 0,
            'compression_operations' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'etag_generated' => 0,
            'total_compression_time' => 0,
            'total_optimization_time' => 0,
            'bytes_saved' => 0
        ];
        
        $this->logger->info('HTTP optimization statistics reset');
    }
}
