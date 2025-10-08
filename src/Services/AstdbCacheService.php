<?php

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;

/**
 * Service for caching ASTDB data with request-level optimization
 * 
 * Eliminates repeated file reads within the same request while maintaining
 * identical data structures and API compatibility.
 */
class AstdbCacheService
{
    private LoggerInterface $logger;
    private string $astdbFile;
    private string $cacheFile;
    
    // Request-level static cache
    private static ?array $requestCache = null;
    private static ?int $fileMtime = null;
    private static ?string $lastFilePath = null;
    
    // Application-level persistent cache
    private static ?array $applicationCache = null;
    private static ?int $applicationCacheMtime = null;
    
    // Search indexes for faster lookups (Added for Phase 4)
    private static ?array $callsignIndex = null;
    private static ?array $locationIndex = null;
    private static ?array $descriptionIndex = null;
    
    public function __construct(LoggerInterface $logger, ?string $astdbFile = null)
    {
        $this->logger = $logger;
        
        // Load ASTDB path from common.inc
        require_once __DIR__ . '/../../includes/common.inc';
        global $ASTDB_TXT;
        
        $this->astdbFile = $astdbFile ?? $_ENV['ASTDB_FILE'] ?? $ASTDB_TXT ?? 'astdb.txt';
        
        // Set up cache file path
        $cacheDir = __DIR__ . '/../../cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        $this->cacheFile = $cacheDir . '/astdb_cache.php';
        
        $this->logger->debug('AstdbCacheService initialized', ['astdb_file' => $this->astdbFile]);
    }
    
    /**
     * Get ASTDB data with multi-level caching
     * 
     * Returns identical data structure as original loadAstDb() method
     * but with request-level and application-level caching for optimal performance.
     */
    public function getAstdb(): array
    {
        // Check if we need to reload (different file or file modified)
        if (self::$requestCache === null || 
            self::$lastFilePath !== $this->astdbFile || 
            $this->isFileModified()) {
            
            // First check application-level cache
            if (self::$applicationCache === null || 
                self::$lastFilePath !== $this->astdbFile || 
                $this->isApplicationCacheStale()) {
                
                // Try to load from application cache first
                if (!$this->loadApplicationCache()) {
                    $this->logger->debug('Loading ASTDB from file', ['file' => $this->astdbFile]);
                    self::$applicationCache = $this->loadAndParseFile();
                    self::$applicationCacheMtime = file_exists($this->astdbFile) ? filemtime($this->astdbFile) : null;
                    $this->saveApplicationCache();
                    
                    $this->logger->debug('ASTDB loaded and cached', [
                        'entries_count' => count(self::$applicationCache),
                        'file_mtime' => self::$applicationCacheMtime
                    ]);
                }
            } else {
                $this->logger->debug('Using cached ASTDB (application-level)', ['file' => $this->astdbFile]);
            }
            
            // Update request-level cache
            self::$requestCache = self::$applicationCache;
            self::$fileMtime = self::$applicationCacheMtime;
            self::$lastFilePath = $this->astdbFile;
        }
        
        return self::$requestCache ?? [];
    }
    
    /**
     * Get specific node information from ASTDB
     * 
     * @param string $nodeId Node ID to lookup
     * @return array|null Node data or null if not found
     */
    public function getNodeInfo(string $nodeId): ?array
    {
        $astdb = $this->getAstdb();
        return $astdb[$nodeId] ?? null;
    }
    
    /**
     * Lazy loading: Get single node info without loading full ASTDB (Phase 5 optimization)
     * 
     * This method reads only the specific line needed from the ASTDB file,
     * avoiding the overhead of loading and parsing the entire dataset.
     * Falls back to full ASTDB if the node is not found in the first scan.
     */
    public function getSingleNodeInfo(string $nodeId): ?array
    {
        if (empty($nodeId)) {
            return null;
        }
        
        // Check if we already have the full ASTDB loaded
        if (self::$requestCache !== null || self::$applicationCache !== null) {
            return $this->getNodeInfo($nodeId);
        }
        
        // Perform lazy single-node lookup
        return $this->performLazyNodeLookup($nodeId);
    }
    
    /**
     * Perform lazy lookup by reading only the needed line from ASTDB file
     */
    private function performLazyNodeLookup(string $nodeId): ?array
    {
        if (!file_exists($this->astdbFile)) {
            $this->logger->debug('ASTDB file not found for lazy lookup', ['file' => $this->astdbFile]);
            return null;
        }
        
        $startTime = microtime(true);
        $linesRead = 0;
        
        try {
            $handle = fopen($this->astdbFile, 'r');
            if ($handle === false) {
                $this->logger->error('Failed to open ASTDB file for lazy lookup', ['file' => $this->astdbFile]);
                return null;
            }
            
            // Read file line by line until we find the node
            while (($line = fgets($handle)) !== false) {
                $linesRead++;
                $line = trim($line);
                
                if (empty($line)) {
                    continue;
                }
                
                $parts = explode('|', $line, 4);
                if (count($parts) >= 4) {
                    $currentNodeId = trim($parts[0]);
                    
                    if ($currentNodeId === $nodeId) {
                        // Found the node!
                        fclose($handle);
                        
                        $endTime = microtime(true);
                        $duration = round(($endTime - $startTime) * 1000, 2);
                        
                        $this->logger->debug('Lazy node lookup successful', [
                            'node_id' => $nodeId,
                            'lines_read' => $linesRead,
                            'duration_ms' => $duration
                        ]);
                        
                        return [
                            'node_id' => $nodeId,
                            'callsign' => trim($parts[1]),
                            'description' => trim($parts[2]),
                            'location' => trim($parts[3])
                        ];
                    }
                }
            }
            
            fclose($handle);
            
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            $this->logger->debug('Lazy node lookup completed (not found)', [
                'node_id' => $nodeId,
                'lines_read' => $linesRead,
                'duration_ms' => $duration
            ]);
            
            return null;
            
        } catch (\Exception $e) {
            $this->logger->error('Error during lazy node lookup', [
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
                'lines_read' => $linesRead
            ]);
            return null;
        }
    }
    
    /**
     * Lazy loading: Get multiple specific node info without loading full ASTDB (Phase 5 optimization)
     * 
     * This method reads only the lines needed for the specified node IDs,
     * avoiding the overhead of loading and parsing the entire dataset.
     */
    public function getMultipleNodeInfo(array $nodeIds): array
    {
        if (empty($nodeIds)) {
            return [];
        }
        
        // Check if we already have the full ASTDB loaded
        if (self::$requestCache !== null || self::$applicationCache !== null) {
            $astdb = $this->getAstdb();
            $results = [];
            foreach ($nodeIds as $nodeId) {
                if (isset($astdb[$nodeId])) {
                    $results[$nodeId] = $astdb[$nodeId];
                }
            }
            return $results;
        }
        
        // Perform lazy multi-node lookup
        return $this->performLazyMultiNodeLookup($nodeIds);
    }
    
    /**
     * Perform lazy lookup for multiple nodes by reading only needed lines from ASTDB file
     */
    private function performLazyMultiNodeLookup(array $nodeIds): array
    {
        if (!file_exists($this->astdbFile)) {
            $this->logger->debug('ASTDB file not found for lazy multi-node lookup', ['file' => $this->astdbFile]);
            return [];
        }
        
        $startTime = microtime(true);
        $linesRead = 0;
        $results = [];
        $remainingNodes = array_flip($nodeIds); // Convert to associative array for O(1) lookup
        
        try {
            $handle = fopen($this->astdbFile, 'r');
            if ($handle === false) {
                $this->logger->error('Failed to open ASTDB file for lazy multi-node lookup', ['file' => $this->astdbFile]);
                return [];
            }
            
            // Read file line by line until we find all nodes or reach end of file
            while (($line = fgets($handle)) !== false && !empty($remainingNodes)) {
                $linesRead++;
                $line = trim($line);
                
                if (empty($line)) {
                    continue;
                }
                
                $parts = explode('|', $line, 4);
                if (count($parts) >= 4) {
                    $currentNodeId = trim($parts[0]);
                    
                    if (isset($remainingNodes[$currentNodeId])) {
                        // Found one of the nodes we're looking for!
                        $results[$currentNodeId] = [
                            'node_id' => $currentNodeId,
                            'callsign' => trim($parts[1]),
                            'description' => trim($parts[2]),
                            'location' => trim($parts[3])
                        ];
                        
                        // Remove from remaining nodes
                        unset($remainingNodes[$currentNodeId]);
                    }
                }
            }
            
            fclose($handle);
            
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            $this->logger->debug('Lazy multi-node lookup completed', [
                'requested_nodes' => count($nodeIds),
                'found_nodes' => count($results),
                'lines_read' => $linesRead,
                'duration_ms' => $duration
            ]);
            
            return $results;
            
        } catch (\Exception $e) {
            $this->logger->error('Error during lazy multi-node lookup', [
                'node_ids' => $nodeIds,
                'error' => $e->getMessage(),
                'lines_read' => $linesRead
            ]);
            return [];
        }
    }
    
    /**
     * Check if ASTDB file has been modified since last load
     */
    private function isFileModified(): bool
    {
        if (!file_exists($this->astdbFile) || self::$fileMtime === null) {
            return true;
        }
        
        $currentMtime = filemtime($this->astdbFile);
        return $currentMtime !== self::$fileMtime;
    }
    
    /**
     * Load and parse ASTDB file with optimized streaming approach
     * 
     * Uses memory-efficient streaming parser instead of loading entire file into memory.
     * Returns identical data structure as original loadAstDb() method:
     * array[nodeId] = array[node_id, callsign, description, location]
     */
    private function loadAndParseFile(): array
    {
        $astdb = [];
        
        if (!file_exists($this->astdbFile)) {
            $this->logger->warning('ASTDB file not found', ['file' => $this->astdbFile]);
            return $astdb;
        }
        
        $startTime = microtime(true);
        $lineCount = 0;
        $parsedCount = 0;
        
        try {
            $handle = fopen($this->astdbFile, 'r');
            if ($handle === false) {
                $this->logger->error('Failed to open ASTDB file for reading', ['file' => $this->astdbFile]);
                return $astdb;
            }
            
            // Read file line by line (streaming approach)
            while (($line = fgets($handle)) !== false) {
                $lineCount++;
                $line = trim($line);
                
                // Skip empty lines
                if (empty($line)) {
                    continue;
                }
                
                // Parse line with optimized approach
                $parts = explode('|', $line, 4); // Limit to 4 parts for performance
                
                if (count($parts) >= 4) {
                    $nodeId = trim($parts[0]);
                    
                    // Only process if node ID is not empty
                    if (!empty($nodeId)) {
                        $astdb[$nodeId] = [
                            'node_id' => $nodeId,
                            'callsign' => trim($parts[1]),
                            'description' => trim($parts[2]),
                            'location' => trim($parts[3])
                        ];
                        $parsedCount++;
                    }
                } else {
                    // Log malformed lines only in debug mode to avoid spam
                    if ($this->logger->isHandling(\Psr\Log\LogLevel::DEBUG)) {
                        $this->logger->debug('Skipping malformed ASTDB line', [
                            'line_num' => $lineCount,
                            'line' => substr($line, 0, 100) . (strlen($line) > 100 ? '...' : '')
                        ]);
                    }
                }
            }
            
            fclose($handle);
            
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
            
            // Build search indexes for faster lookups (Phase 4 optimization)
            $this->buildSearchIndexes($astdb);
            
            $this->logger->info('ASTDB file parsed successfully', [
                'file' => $this->astdbFile,
                'total_lines' => $lineCount,
                'parsed_entries' => $parsedCount,
                'duration_ms' => $duration,
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error parsing ASTDB file', [
                'file' => $this->astdbFile,
                'error' => $e->getMessage(),
                'line_count' => $lineCount,
                'parsed_count' => $parsedCount
            ]);
        }
        
        return $astdb;
    }
    
    /**
     * Clear request-level cache (for testing or manual refresh)
     */
    public function clearCache(): void
    {
        self::$requestCache = null;
        self::$fileMtime = null;
        self::$lastFilePath = null;
        $this->logger->debug('ASTDB request cache cleared');
    }
    
    /**
     * Get cache statistics for monitoring including compression info (Phase 6 optimization)
     */
    public function getCacheStats(): array
    {
        $stats = [
            'is_cached' => self::$requestCache !== null,
            'file_path' => self::$lastFilePath,
            'file_mtime' => self::$fileMtime,
            'entries_count' => self::$requestCache ? count(self::$requestCache) : 0,
            'application_cache_exists' => self::$applicationCache !== null,
            'application_cache_mtime' => self::$applicationCacheMtime
        ];
        
        // Add compression statistics if cache file exists
        if (file_exists($this->cacheFile)) {
            $cacheFileSize = filesize($this->cacheFile);
            $stats['cache_file_size'] = $cacheFileSize;
            $stats['cache_file_exists'] = true;
            
            // Try to determine if it's compressed by attempting decompression
            $fileData = file_get_contents($this->cacheFile);
            if ($fileData !== false) {
                $decompressedData = @gzuncompress($fileData);
                if ($decompressedData !== false) {
                    $stats['is_compressed'] = true;
                    $stats['uncompressed_size'] = strlen($decompressedData);
                    $stats['compression_ratio'] = round((1 - $cacheFileSize / strlen($decompressedData)) * 100, 1) . '%';
                } else {
                    $stats['is_compressed'] = false;
                    $stats['uncompressed_size'] = $cacheFileSize;
                    $stats['compression_ratio'] = '0%';
                }
            }
        } else {
            $stats['cache_file_exists'] = false;
            $stats['is_compressed'] = false;
        }
        
        return $stats;
    }
    
    /**
     * Check if application-level cache is stale
     */
    private function isApplicationCacheStale(): bool
    {
        if (!file_exists($this->astdbFile)) {
            return true; // File disappeared, force reload
        }
        
        $currentMtime = filemtime($this->astdbFile);
        return self::$applicationCacheMtime === null || $currentMtime > self::$applicationCacheMtime;
    }
    
    /**
     * Save application-level cache to disk with compression (Phase 6 optimization)
     */
    private function saveApplicationCache(): void
    {
        if (self::$applicationCache === null) {
            return;
        }
        
        try {
            $cacheData = [
                'data' => self::$applicationCache,
                'mtime' => self::$applicationCacheMtime,
                'timestamp' => time(),
                'file_path' => $this->astdbFile,
                'compressed' => true, // Mark as compressed
                'version' => '1.1' // Cache format version
            ];
            
            // Serialize and compress the data
            $serializedData = serialize($cacheData);
            $compressedData = gzcompress($serializedData, 9); // Maximum compression level
            
            if ($compressedData === false) {
                $this->logger->warning('Failed to compress application cache', ['cache_file' => $this->cacheFile]);
                return;
            }
            
            $result = file_put_contents($this->cacheFile, $compressedData, LOCK_EX);
            
            if ($result === false) {
                $this->logger->warning('Failed to save application cache', ['cache_file' => $this->cacheFile]);
            } else {
                $originalSize = strlen($serializedData);
                $compressedSize = $result;
                $compressionRatio = round((1 - $compressedSize / $originalSize) * 100, 1);
                
                $this->logger->debug('Application cache saved with compression', [
                    'cache_file' => $this->cacheFile,
                    'original_size' => $originalSize,
                    'compressed_size' => $compressedSize,
                    'compression_ratio' => $compressionRatio . '%'
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error saving application cache', [
                'cache_file' => $this->cacheFile,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Load application-level cache from disk with compression support (Phase 6 optimization)
     */
    private function loadApplicationCache(): bool
    {
        if (!file_exists($this->cacheFile)) {
            return false;
        }
        
        try {
            $fileData = file_get_contents($this->cacheFile);
            if ($fileData === false) {
                return false;
            }
            
            // Try to decompress first (new format)
            $decompressedData = @gzuncompress($fileData);
            if ($decompressedData !== false) {
                // Successfully decompressed - new compressed format
                $serializedData = $decompressedData;
                $wasCompressed = true;
            } else {
                // Fallback to direct unserialize (old format)
                $serializedData = $fileData;
                $wasCompressed = false;
            }
            
            $cacheData = unserialize($serializedData);
            if (!is_array($cacheData) || 
                !isset($cacheData['data'], $cacheData['mtime'], $cacheData['file_path'])) {
                return false;
            }
            
            // Verify the cache is for the correct file
            if ($cacheData['file_path'] !== $this->astdbFile) {
                return false;
            }
            
            // Verify the cache is not stale
            if (file_exists($this->astdbFile) && filemtime($this->astdbFile) > $cacheData['mtime']) {
                return false;
            }
            
            self::$applicationCache = $cacheData['data'];
            self::$applicationCacheMtime = $cacheData['mtime'];
            
            $this->logger->debug('Application cache loaded', [
                'cache_file' => $this->cacheFile,
                'entries_count' => count(self::$applicationCache),
                'cached_mtime' => self::$applicationCacheMtime,
                'was_compressed' => $wasCompressed,
                'format_version' => $cacheData['version'] ?? '1.0'
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error loading application cache', [
                'cache_file' => $this->cacheFile,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Clear application-level cache
     */
    public function clearApplicationCache(): void
    {
        self::$applicationCache = null;
        self::$applicationCacheMtime = null;
        
        // Clear search indexes when clearing application cache
        self::$callsignIndex = null;
        self::$locationIndex = null;
        self::$descriptionIndex = null;
        
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
            $this->logger->debug('Application cache file deleted', ['cache_file' => $this->cacheFile]);
        }
    }
    
    /**
     * Build search indexes for faster lookups (Phase 4 optimization)
     * 
     * Creates indexes for callsign, location, and description fields to enable
     * fast text searches without scanning the entire dataset.
     */
    private function buildSearchIndexes(array $astdb): void
    {
        $startTime = microtime(true);
        
        self::$callsignIndex = [];
        self::$locationIndex = [];
        self::$descriptionIndex = [];
        
        foreach ($astdb as $nodeId => $nodeData) {
            $callsign = strtolower($nodeData['callsign'] ?? '');
            $location = strtolower($nodeData['location'] ?? '');
            $description = strtolower($nodeData['description'] ?? '');
            
            // Index callsign
            if (!empty($callsign)) {
                self::$callsignIndex[$callsign][] = $nodeId;
            }
            
            // Index location
            if (!empty($location)) {
                self::$locationIndex[$location][] = $nodeId;
            }
            
            // Index description
            if (!empty($description)) {
                self::$descriptionIndex[$description][] = $nodeId;
            }
        }
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        $this->logger->debug('Search indexes built successfully', [
            'callsign_entries' => count(self::$callsignIndex),
            'location_entries' => count(self::$locationIndex),
            'description_entries' => count(self::$descriptionIndex),
            'duration_ms' => $duration
        ]);
    }
    
    /**
     * Fast search using indexes (Phase 4 optimization)
     * 
     * Performs indexed search instead of full dataset scan.
     * Falls back to traditional search if indexes are not available.
     */
    public function searchNodes(string $query, int $limit = 50): array
    {
        $startTime = microtime(true);
        $results = [];
        
        if (empty($query) || strlen($query) < 2) {
            return $results;
        }
        
        $query = strtolower(trim($query));
        $astdb = $this->getAstdb();
        
        // Use indexed search if indexes are available
        if (self::$callsignIndex !== null && self::$locationIndex !== null && self::$descriptionIndex !== null) {
            $results = $this->performIndexedSearch($query, $astdb, $limit);
        } else {
            // Fallback to traditional search
            $results = $this->performTraditionalSearch($query, $astdb, $limit);
        }
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        $this->logger->debug('Node search completed', [
            'query' => $query,
            'results_count' => count($results),
            'duration_ms' => $duration,
            'method' => self::$callsignIndex !== null ? 'indexed' : 'traditional'
        ]);
        
        return $results;
    }
    
    /**
     * Perform indexed search using pre-built indexes
     */
    private function performIndexedSearch(string $query, array $astdb, int $limit): array
    {
        $foundNodeIds = [];
        $results = [];
        
        // Search in callsign index
        foreach (self::$callsignIndex as $callsign => $nodeIds) {
            if (strpos($callsign, $query) !== false) {
                $foundNodeIds = array_merge($foundNodeIds, $nodeIds);
            }
        }
        
        // Search in location index
        foreach (self::$locationIndex as $location => $nodeIds) {
            if (strpos($location, $query) !== false) {
                $foundNodeIds = array_merge($foundNodeIds, $nodeIds);
            }
        }
        
        // Search in description index
        foreach (self::$descriptionIndex as $description => $nodeIds) {
            if (strpos($description, $query) !== false) {
                $foundNodeIds = array_merge($foundNodeIds, $nodeIds);
            }
        }
        
        // Also search node IDs directly
        foreach ($astdb as $nodeId => $nodeData) {
            if (strpos($nodeId, $query) !== false) {
                $foundNodeIds[] = $nodeId;
            }
        }
        
        // Remove duplicates and limit results
        $foundNodeIds = array_unique($foundNodeIds);
        $foundNodeIds = array_slice($foundNodeIds, 0, $limit);
        
        // Build results array
        foreach ($foundNodeIds as $nodeId) {
            if (isset($astdb[$nodeId])) {
                $nodeData = $astdb[$nodeId];
                $results[] = [
                    'node_id' => $nodeId,
                    'callsign' => $nodeData['callsign'],
                    'description' => $nodeData['description'],
                    'location' => $nodeData['location']
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Traditional search (fallback method)
     */
    private function performTraditionalSearch(string $query, array $astdb, int $limit): array
    {
        $results = [];
        $count = 0;
        
        foreach ($astdb as $nodeId => $nodeData) {
            if ($count >= $limit) break;
            
            $callsign = strtolower($nodeData['callsign'] ?? '');
            $description = strtolower($nodeData['description'] ?? '');
            $location = strtolower($nodeData['location'] ?? '');
            
            if (strpos($nodeId, $query) !== false ||
                strpos($callsign, $query) !== false ||
                strpos($description, $query) !== false ||
                strpos($location, $query) !== false) {
                
                $results[] = [
                    'node_id' => $nodeId,
                    'callsign' => $nodeData['callsign'],
                    'description' => $nodeData['description'],
                    'location' => $nodeData['location']
                ];
                $count++;
            }
        }
        
        return $results;
    }
}
