<?php

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\DatabaseGenerationService;
use SupermonNg\Services\AstdbCacheService;

/**
 * Controller for database-related API endpoints
 */
class DatabaseController
{
    private LoggerInterface $logger;
    private DatabaseGenerationService $databaseService;
    private AstdbCacheService $astdbService;
    
    public function __construct(
        LoggerInterface $logger,
        DatabaseGenerationService $databaseService,
        AstdbCacheService $astdbService
    ) {
        $this->logger = $logger;
        $this->databaseService = $databaseService;
        $this->astdbService = $astdbService;
    }
    
    /**
     * Get database status and information
     */
    public function status(Request $request, Response $response): Response
    {
        try {
            $this->logger->info('Fetching database status');
            
            // Get database status - handle errors gracefully
            try {
                $status = $this->databaseService->getDatabaseStatus();
                if (!is_array($status)) {
                    $status = [];
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to get database status', ['error' => $e->getMessage()]);
                $status = [];
            }
            
            // Add ASTDB data for frontend compatibility using optimized caching
            // Ensure astdb is always an array, even if empty
            try {
                $astdb = $this->astdbService->getAstdb();
                if (!is_array($astdb)) {
                    $astdb = [];
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to load ASTDB data', ['error' => $e->getMessage()]);
                $astdb = [];
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => array_merge($status, ['astdb' => $astdb])
            ]));
            
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
                
        } catch (\Exception $e) {
            $this->logger->error('Database status endpoint error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => ['astdb' => []]
            ]));
            
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Generate/update the AllStar database
     */
    public function generate(Request $request, Response $response): Response
    {
        $this->logger->info('Starting database generation');
        
        $body = $request->getParsedBody() ?? [];
        $isStrictlyPrivate = $body['strictly_private'] ?? false;
        
        $success = $this->databaseService->generateDatabase($isStrictlyPrivate);
        
        if ($success) {
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Database generated successfully'
            ]));
            
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
        } else {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to generate database'
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Check and perform automatic update if needed
     */
    public function autoUpdate(Request $request, Response $response): Response
    {
        $this->logger->info('Checking for automatic database update');
        
        $success = $this->databaseService->checkAndPerformAutomaticUpdate();
        
        if ($success) {
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Automatic update performed successfully'
            ]));
            
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
        } else {
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'No automatic update needed at this time'
            ]));
            
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Force immediate update regardless of timing
     */
    public function forceUpdate(Request $request, Response $response): Response
    {
        $this->logger->info('Forcing immediate database update');
        
        $success = $this->databaseService->forceUpdate();
        
        if ($success) {
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Database updated successfully'
            ]));
            
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
        } else {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to update database'
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Search nodes in the database
     */
    public function search(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams()['q'] ?? '';
        $limit = (int)($request->getQueryParams()['limit'] ?? 50);
        
        $this->logger->info('Searching database', [
            'query' => $query,
            'limit' => $limit
        ]);
        
        if (empty($query)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Search query is required'
            ]));
            
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }
        
        $results = $this->searchNodes($query, $limit);
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'query' => $query,
                'results' => $results,
                'count' => count($results)
            ]
        ]));
        
        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Get a specific node by ID
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        $nodeId = $args['id'] ?? '';
        
        $this->logger->info('Fetching node details', ['node_id' => $nodeId]);
        
        if (empty($nodeId)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Node ID is required'
            ]));
            
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }
        
        $node = $this->findNodeById($nodeId);
        
        if ($node) {
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $node
            ]));
            
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
        } else {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Node not found'
            ]));
            
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Search nodes using optimized AstdbCacheService with indexed lookups
     */
    private function searchNodes(string $query, int $limit): array
    {
        // Use the optimized search method from AstdbCacheService
        return $this->astdbService->searchNodes($query, $limit);
    }
    
    /**
     * Find a specific node by ID
     */
    private function findNodeById(string $nodeId): ?array
    {
        $astdbFile = $_ENV['ASTDB_FILE'] ?? 'astdb.txt';
        
        if (!file_exists($astdbFile)) {
            return null;
        }
        
        $lines = @file($astdbFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return null;
        }
        
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 4) {
                $currentNodeId = trim($parts[0]);
                if ($currentNodeId === $nodeId) {
                    return [
                        'node_id' => $currentNodeId,
                        'callsign' => trim($parts[1]),
                        'description' => trim($parts[2]),
                        'location' => trim($parts[3])
                    ];
                }
            }
        }
        
        return null;
    }
}
