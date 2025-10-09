<?php

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\AstdbCacheService;

/**
 * Dedicated ASTDB API Controller (Phase 7 optimization)
 * 
 * Provides optimized endpoints for ASTDB data access,
 * reducing redundant loading and improving performance.
 */
class AstdbController
{
    private LoggerInterface $logger;
    private AstdbCacheService $astdbService;
    
    public function __construct(
        LoggerInterface $logger,
        AstdbCacheService $astdbService
    ) {
        $this->logger = $logger;
        $this->astdbService = $astdbService;
    }
    
    /**
     * Get ASTDB cache statistics and metadata
     * 
     * Provides information about cache status, compression, and performance metrics
     */
    public function getStats(Request $request, Response $response): Response
    {
        try {
            $cacheStats = $this->astdbService->getCacheStats();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $cacheStats,
                'timestamp' => date('c')
            ]));
            
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
                
        } catch (\Exception $e) {
            $this->logger->error('Failed to get ASTDB stats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to get ASTDB statistics',
                'error' => $e->getMessage()
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Get single node information efficiently
     * 
     * Uses lazy loading for optimal performance when only one node is needed
     */
    public function getNode(Request $request, Response $response, array $args): Response
    {
        try {
            $nodeId = $args['id'] ?? '';
            
            if (empty($nodeId)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Node ID is required'
                ]));
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json');
            }
            
            $startTime = microtime(true);
            $nodeInfo = $this->astdbService->getSingleNodeInfo($nodeId);
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            if ($nodeInfo) {
                // Format the response to match expected frontend format
                $formattedInfo = [
                    'node_id' => $nodeId,
                    'callsign' => $nodeInfo['callsign'],
                    'description' => $nodeInfo['description'],
                    'location' => $nodeInfo['location'],
                    'full_info' => trim($nodeInfo['callsign'] . ' ' . $nodeInfo['description'] . ' ' . $nodeInfo['location'])
                ];
                
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'data' => $formattedInfo,
                    'metadata' => [
                        'duration_ms' => $duration,
                        'method' => 'lazy_loading'
                    ],
                    'timestamp' => date('c')
                ]));
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Node $nodeId not found in ASTDB",
                    'data' => null,
                    'metadata' => [
                        'duration_ms' => $duration,
                        'method' => 'lazy_loading'
                    ]
                ]));
            }
            
            return $response
                ->withStatus($nodeInfo ? 200 : 404)
                ->withHeader('Content-Type', 'application/json');
                
        } catch (\Exception $e) {
            $this->logger->error('Failed to get node info', [
                'node_id' => $args['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to get node information',
                'error' => $e->getMessage()
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Get multiple nodes information efficiently
     * 
     * Uses batch lazy loading for optimal performance when multiple nodes are needed
     */
    public function getNodes(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $nodeIds = $queryParams['nodes'] ?? '';
            
            if (empty($nodeIds)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Node IDs are required (comma-separated)'
                ]));
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json');
            }
            
            // Parse comma-separated node IDs
            $nodeIdArray = array_map('trim', explode(',', $nodeIds));
            $nodeIdArray = array_filter($nodeIdArray, function($id) {
                return !empty($id) && is_numeric($id);
            });
            
            if (empty($nodeIdArray)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'No valid node IDs provided'
                ]));
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json');
            }
            
            $startTime = microtime(true);
            $nodeInfoMap = $this->astdbService->getMultipleNodeInfo($nodeIdArray);
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            // Format the response
            $formattedNodes = [];
            foreach ($nodeIdArray as $nodeId) {
                $nodeInfo = $nodeInfoMap[$nodeId] ?? null;
                if ($nodeInfo) {
                    $formattedNodes[$nodeId] = [
                        'node_id' => $nodeId,
                        'callsign' => $nodeInfo['callsign'],
                        'description' => $nodeInfo['description'],
                        'location' => $nodeInfo['location'],
                        'full_info' => trim($nodeInfo['callsign'] . ' ' . $nodeInfo['description'] . ' ' . $nodeInfo['location'])
                    ];
                } else {
                    $formattedNodes[$nodeId] = null;
                }
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $formattedNodes,
                'metadata' => [
                    'requested_count' => count($nodeIdArray),
                    'found_count' => count(array_filter($formattedNodes)),
                    'duration_ms' => $duration,
                    'method' => 'batch_lazy_loading'
                ],
                'timestamp' => date('c')
            ]));
            
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
                
        } catch (\Exception $e) {
            $this->logger->error('Failed to get multiple nodes info', [
                'nodes' => $queryParams['nodes'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to get nodes information',
                'error' => $e->getMessage()
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Search ASTDB efficiently using indexed search
     * 
     * Provides fast search capabilities with optimized indexing
     */
    public function search(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $query = $queryParams['q'] ?? '';
            $limit = (int)($queryParams['limit'] ?? 50);
            
            if (empty($query) || strlen($query) < 2) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Search query must be at least 2 characters long'
                ]));
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json');
            }
            
            if ($limit > 200) {
                $limit = 200; // Cap at 200 results for performance
            }
            
            $startTime = microtime(true);
            $searchResults = $this->astdbService->searchNodes($query, $limit);
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'query' => $query,
                    'results' => $searchResults,
                    'count' => count($searchResults)
                ],
                'metadata' => [
                    'duration_ms' => $duration,
                    'limit' => $limit,
                    'method' => 'indexed_search'
                ],
                'timestamp' => date('c')
            ]));
            
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
                
        } catch (\Exception $e) {
            $this->logger->error('Failed to search ASTDB', [
                'query' => $queryParams['q'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to search ASTDB',
                'error' => $e->getMessage()
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Get ASTDB cache management endpoints
     */
    public function clearCache(Request $request, Response $response): Response
    {
        try {
            $this->astdbService->clearApplicationCache();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'ASTDB cache cleared successfully',
                'timestamp' => date('c')
            ]));
            
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
                
        } catch (\Exception $e) {
            $this->logger->error('Failed to clear ASTDB cache', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to clear ASTDB cache',
                'error' => $e->getMessage()
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Get ASTDB health check
     */
    public function health(Request $request, Response $response): Response
    {
        try {
            $cacheStats = $this->astdbService->getCacheStats();
            
            // Consider healthy if cache file exists, regardless of whether it's loaded in memory
            $cacheFileExists = $cacheStats['cache_file_exists'] ?? false;
            $hasEntries = ($cacheStats['entries_count'] ?? 0) > 0;
            $isHealthy = $cacheFileExists || $hasEntries;
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'healthy' => $isHealthy,
                'data' => [
                    'cache_status' => $isHealthy ? 'ready' : 'not_loaded',
                    'entries_count' => $cacheStats['entries_count'] ?? 0,
                    'cache_file_exists' => $cacheFileExists,
                    'is_compressed' => $cacheStats['is_compressed'] ?? false
                ],
                'timestamp' => date('c')
            ]));
            
            return $response
                ->withStatus($isHealthy ? 200 : 503)
                ->withHeader('Content-Type', 'application/json');
                
        } catch (\Exception $e) {
            $this->logger->error('ASTDB health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'healthy' => false,
                'message' => 'ASTDB health check failed',
                'error' => $e->getMessage()
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }
}
