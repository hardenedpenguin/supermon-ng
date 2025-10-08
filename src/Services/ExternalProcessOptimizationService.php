<?php

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Exception;

/**
 * External Process Optimization Service
 * 
 * Replaces external command execution (shell_exec, exec) with native PHP
 * implementations for better performance and security.
 */
class ExternalProcessOptimizationService
{
    private LoggerInterface $logger;
    private CacheInterface $cache;
    
    // Performance tracking
    private static array $performanceStats = [
        'irlp_lookups' => 0,
        'irlp_cache_hits' => 0,
        'irlp_cache_misses' => 0,
        'irlp_file_reads' => 0,
        'echolink_lookups' => 0,
        'echolink_cache_hits' => 0,
        'echolink_cache_misses' => 0,
        'total_lookup_time' => 0,
        'total_parse_time' => 0,
        'shell_commands_avoided' => 0
    ];
    
    // Cache for parsed IRLP data
    private static ?array $irlpData = null;
    private static ?int $irlpDataLoadTime = null;
    
    // Cache TTLs
    private const IRLP_CACHE_FOUND = 600;      // 10 minutes
    private const IRLP_CACHE_NOT_FOUND = 30;   // 30 seconds
    private const ELNK_CACHE_FOUND = 300;      // 5 minutes
    private const ELNK_CACHE_NOT_FOUND = 30;   // 30 seconds
    private const IRLP_FILE_CACHE = 3600;      // 1 hour for parsed file

    public function __construct(LoggerInterface $logger, CacheInterface $cache)
    {
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * Optimized IRLP lookup - replaces shell_exec with native PHP parsing
     */
    public function irlpLookup(string $irlpNode, ?string $irlpCallsFile = null): string
    {
        $startTime = microtime(true);
        self::$performanceStats['irlp_lookups']++;
        
        try {
            // Extract node number
            $lookupKey = (int)substr((string)$irlpNode, 1);
            $displayNode = $lookupKey;
            $subchannelDigit = substr((string)$displayNode, 3, 1);
            
            // Check persistent cache first
            $cacheKey = "irlp_lookup:$lookupKey";
            $cached = $this->getCachedLookup($cacheKey);
            
            if ($cached !== null) {
                self::$performanceStats['irlp_cache_hits']++;
                self::$performanceStats['total_lookup_time'] += microtime(true) - $startTime;
                
                $this->logger->debug('IRLP cache hit', [
                    'node' => $lookupKey,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ]);
                
                return $cached['callsign'] . " [IRLP $displayNode] " . $cached['qth'];
            }
            
            self::$performanceStats['irlp_cache_misses']++;
            
            // Adjust search key for subchannels
            $searchKey = $lookupKey;
            if ($lookupKey >= 9000 && $subchannelDigit > '0') {
                $searchKey = (int)substr_replace((string)$lookupKey, '0', 3, 1);
            }
            
            // Load and parse IRLP data
            $irlpData = $this->getIrlpData($irlpCallsFile);
            
            if (empty($irlpData)) {
                $result = [
                    'callsign' => 'No info (config error)',
                    'qth' => 'No info',
                    'found' => false
                ];
                
                $this->cacheLookup($cacheKey, $result, self::IRLP_CACHE_NOT_FOUND);
                
                return "No info (config error) [IRLP $displayNode] No info";
            }
            
            // Native PHP lookup - much faster than shell_exec
            if (isset($irlpData[$searchKey])) {
                $data = $irlpData[$searchKey];
                $callsign = $data['callsign'];
                $qth = $data['qth'];
                
                // Handle reflector subchannels
                if ($searchKey >= 9000 && $subchannelDigit > '0') {
                    $callsign = "REF" . $displayNode;
                }
                
                $result = [
                    'callsign' => $callsign,
                    'qth' => $qth,
                    'found' => true
                ];
                
                $this->cacheLookup($cacheKey, $result, self::IRLP_CACHE_FOUND);
                
                self::$performanceStats['shell_commands_avoided']++;
                self::$performanceStats['total_lookup_time'] += microtime(true) - $startTime;
                
                $this->logger->debug('IRLP lookup successful', [
                    'node' => $searchKey,
                    'callsign' => $callsign,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ]);
                
                return "$callsign [IRLP $displayNode] $qth";
            }
            
            // Not found
            $result = [
                'callsign' => 'No info',
                'qth' => 'No info',
                'found' => false
            ];
            
            $this->cacheLookup($cacheKey, $result, self::IRLP_CACHE_NOT_FOUND);
            
            self::$performanceStats['total_lookup_time'] += microtime(true) - $startTime;
            
            return "No info [IRLP $displayNode] No info";
            
        } catch (Exception $e) {
            $this->logger->error('IRLP lookup error', [
                'node' => $irlpNode,
                'error' => $e->getMessage()
            ]);
            
            return "Error [IRLP] " . $e->getMessage();
        }
    }

    /**
     * Load and parse IRLP calls file using native PHP
     */
    private function getIrlpData(?string $irlpCallsFile = null): array
    {
        $parseStartTime = microtime(true);
        
        // Check if we have recent cached data
        if (self::$irlpData !== null && 
            self::$irlpDataLoadTime !== null && 
            (time() - self::$irlpDataLoadTime) < self::IRLP_FILE_CACHE) {
            return self::$irlpData;
        }
        
        // Get file path from configuration
        if ($irlpCallsFile === null) {
            $configService = new ConfigurationCacheService($this->logger);
            $irlpCallsFile = $configService->getConfig('IRLP_CALLS', '/var/lib/irlp/nodes.txt');
        }
        
        // Check if file exists
        if (!file_exists($irlpCallsFile)) {
            $this->logger->warning('IRLP calls file not found', [
                'path' => $irlpCallsFile
            ]);
            return [];
        }
        
        self::$performanceStats['irlp_file_reads']++;
        
        // Parse file using native PHP (much faster than shell_exec with zcat/awk)
        $data = [];
        
        // Detect if file is gzipped
        $isGzipped = str_ends_with($irlpCallsFile, '.gz') || 
                     str_ends_with($irlpCallsFile, '.z');
        
        if ($isGzipped) {
            $handle = gzopen($irlpCallsFile, 'rb');
        } else {
            $handle = fopen($irlpCallsFile, 'rb');
        }
        
        if ($handle === false) {
            $this->logger->error('Failed to open IRLP calls file', [
                'path' => $irlpCallsFile
            ]);
            return [];
        }
        
        // Parse line by line for memory efficiency
        while (!feof($handle)) {
            $line = $isGzipped ? gzgets($handle, 4096) : fgets($handle, 4096);
            
            if ($line === false) {
                break;
            }
            
            $line = trim($line);
            if (empty($line) || $line[0] === '#') {
                continue;
            }
            
            // Parse pipe-delimited format: node|callsign|city|state|country
            $columns = explode('|', $line);
            
            if (count($columns) >= 5) {
                $node = (int)trim($columns[0]);
                $callsign = trim($columns[1]);
                $city = trim($columns[2]);
                $state = trim($columns[3]);
                $country = trim($columns[4]);
                
                $data[$node] = [
                    'callsign' => $callsign,
                    'qth' => "$city, $state $country"
                ];
            }
        }
        
        if ($isGzipped) {
            gzclose($handle);
        } else {
            fclose($handle);
        }
        
        // Cache in memory
        self::$irlpData = $data;
        self::$irlpDataLoadTime = time();
        
        $parseTime = microtime(true) - $parseStartTime;
        self::$performanceStats['total_parse_time'] += $parseTime;
        
        $this->logger->info('IRLP data parsed successfully', [
            'entries' => count($data),
            'parse_time_ms' => round($parseTime * 1000, 2),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
        ]);
        
        return $data;
    }

    /**
     * Optimized EchoLink lookup with persistent caching
     */
    public function echolinkLookup($fp, string $echoNode): string
    {
        $startTime = microtime(true);
        self::$performanceStats['echolink_lookups']++;
        
        try {
            $lookupNode = (int)substr((string)$echoNode, 1);
            
            // Check persistent cache first
            $cacheKey = "echolink_lookup:$lookupNode";
            $cached = $this->getCachedLookup($cacheKey);
            
            if ($cached !== null) {
                self::$performanceStats['echolink_cache_hits']++;
                self::$performanceStats['total_lookup_time'] += microtime(true) - $startTime;
                
                $this->logger->debug('EchoLink cache hit', [
                    'node' => $lookupNode,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ]);
                
                return $cached['callsign'] . " [EchoLink $lookupNode] (" . $cached['location'] . ")";
            }
            
            self::$performanceStats['echolink_cache_misses']++;
            
            // Perform AMI lookup
            $commandString = "echolink dbget nodename $lookupNode";
            $amiResponse = \SimpleAmiClient::command($fp, $commandString);
            
            if ($amiResponse === false || $amiResponse === '') {
                $result = [
                    'callsign' => 'No info (cmd fail)',
                    'location' => 'No info',
                    'found' => false
                ];
                
                $this->cacheLookup($cacheKey, $result, self::ELNK_CACHE_NOT_FOUND);
                
                return "No info (cmd fail) [EchoLink $lookupNode] (No info)";
            }
            
            // Parse response
            $rows = explode("\n", $amiResponse);
            
            if (!empty($rows) && !empty($rows[0])) {
                $columns = explode("|", $rows[0]);
                
                if (count($columns) >= 3 && trim($columns[0]) == $lookupNode) {
                    $callsign = trim($columns[1]);
                    $location = trim($columns[2]);
                    
                    $result = [
                        'callsign' => $callsign,
                        'location' => $location,
                        'found' => true
                    ];
                    
                    $this->cacheLookup($cacheKey, $result, self::ELNK_CACHE_FOUND);
                    
                    self::$performanceStats['total_lookup_time'] += microtime(true) - $startTime;
                    
                    $this->logger->debug('EchoLink lookup successful', [
                        'node' => $lookupNode,
                        'callsign' => $callsign,
                        'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
                    ]);
                    
                    return "$callsign [EchoLink $lookupNode] ($location)";
                }
            }
            
            // Not found
            $result = [
                'callsign' => 'No info',
                'location' => 'No info',
                'found' => false
            ];
            
            $this->cacheLookup($cacheKey, $result, self::ELNK_CACHE_NOT_FOUND);
            
            self::$performanceStats['total_lookup_time'] += microtime(true) - $startTime;
            
            return "No info [EchoLink $lookupNode] (No info)";
            
        } catch (Exception $e) {
            $this->logger->error('EchoLink lookup error', [
                'node' => $echoNode,
                'error' => $e->getMessage()
            ]);
            
            return "Error [EchoLink] " . $e->getMessage();
        }
    }

    /**
     * Get cached lookup result
     */
    private function getCachedLookup(string $cacheKey): ?array
    {
        try {
            $item = $this->cache->getItem($cacheKey);
            
            if ($item->isHit()) {
                return $item->get();
            }
        } catch (Exception $e) {
            $this->logger->debug('Cache lookup error', [
                'key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }

    /**
     * Cache lookup result
     */
    private function cacheLookup(string $cacheKey, array $data, int $ttl): void
    {
        try {
            $item = $this->cache->getItem($cacheKey);
            $item->set($data);
            $item->expiresAfter($ttl);
            $this->cache->save($item);
        } catch (Exception $e) {
            $this->logger->debug('Cache store error', [
                'key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear IRLP data cache (force reload)
     */
    public function clearIrlpCache(): void
    {
        self::$irlpData = null;
        self::$irlpDataLoadTime = null;
        
        $this->logger->info('IRLP data cache cleared');
    }

    /**
     * Get performance statistics
     */
    public function getPerformanceStats(): array
    {
        $stats = self::$performanceStats;
        
        // Calculate derived metrics
        $stats['irlp_cache_hit_ratio'] = ($stats['irlp_lookups'] > 0) 
            ? round(($stats['irlp_cache_hits'] / $stats['irlp_lookups']) * 100, 2)
            : 0;
        
        $stats['echolink_cache_hit_ratio'] = ($stats['echolink_lookups'] > 0) 
            ? round(($stats['echolink_cache_hits'] / $stats['echolink_lookups']) * 100, 2)
            : 0;
        
        $stats['average_lookup_time'] = ($stats['irlp_lookups'] + $stats['echolink_lookups']) > 0 
            ? round(($stats['total_lookup_time'] / ($stats['irlp_lookups'] + $stats['echolink_lookups'])) * 1000, 2)
            : 0;
        
        $stats['irlp_data_loaded'] = self::$irlpData !== null;
        $stats['irlp_entries_count'] = self::$irlpData !== null ? count(self::$irlpData) : 0;
        $stats['irlp_cache_age_seconds'] = self::$irlpDataLoadTime !== null 
            ? time() - self::$irlpDataLoadTime 
            : null;
        
        return $stats;
    }

    /**
     * Reset performance statistics
     */
    public function resetStats(): void
    {
        self::$performanceStats = [
            'irlp_lookups' => 0,
            'irlp_cache_hits' => 0,
            'irlp_cache_misses' => 0,
            'irlp_file_reads' => 0,
            'echolink_lookups' => 0,
            'echolink_cache_hits' => 0,
            'echolink_cache_misses' => 0,
            'total_lookup_time' => 0,
            'total_parse_time' => 0,
            'shell_commands_avoided' => 0
        ];
        
        $this->logger->info('External process optimization statistics reset');
    }
}
