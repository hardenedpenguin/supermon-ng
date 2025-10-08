<?php

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Exception;

/**
 * Service for generating and managing AllStar database
 * 
 * Replicates the functionality of astdb.php in a modern service-oriented approach
 * with automatic scheduling capabilities
 */
class DatabaseGenerationService
{
    private LoggerInterface $logger;
    private CacheInterface $cache;
    private string $astdbFile;
    private string $privateNodesFile;
    private string $allstarDbUrl;
    
    private const MIN_DB_SIZE_BYTES = 300000;
    private const MAX_RETRIES = 5;
    private const RETRY_SLEEP_SECONDS = 5;
    private const HTTP_TIMEOUT_SECONDS = 20;
    private const CRON_MAX_DELAY_SECONDS = 1800; // 30 minutes
    private const AUTO_UPDATE_INTERVAL = 10800; // 3 hours in seconds
    
    public function __construct(
        LoggerInterface $logger,
        CacheInterface $cache,
        ?string $astdbFile = null,
        ?string $privateNodesFile = null,
        ?string $allstarDbUrl = null
    ) {
        $this->logger = $logger;
        $this->cache = $cache;
        
        // Use configuration cache service for optimized path loading
        $this->initializePaths($astdbFile, $privateNodesFile, $allstarDbUrl);
    }
    
    /**
     * Initialize configuration paths using cached configuration
     */
    private function initializePaths(?string $astdbFile, ?string $privateNodesFile, ?string $allstarDbUrl): void
    {
        // Create configuration cache service instance
        $configService = new \SupermonNg\Services\ConfigurationCacheService($this->logger);
        
        // Get configuration values from cache
        $userFilesPath = $configService->getConfig('USERFILES', 'user_files');
        $astdbTxt = $configService->getConfig('ASTDB_TXT', 'astdb.txt');
        $privateNodes = $configService->getConfig('PRIVATENODES', 'privatenodes.txt');
        
        // Set file paths with fallbacks
        $this->astdbFile = $astdbFile ?? $_ENV['ASTDB_FILE'] ?? $astdbTxt;
        $this->privateNodesFile = $privateNodesFile ?? $_ENV['PRIVATE_NODES_FILE'] ?? $userFilesPath . '/' . $privateNodes;
        $this->allstarDbUrl = $allstarDbUrl ?? $_ENV['ALLSTAR_DB_URL'] ?? 'http://allmondb.allstarlink.org/';
        
        $this->logger->debug('Database service paths initialized', [
            'astdb_file' => $this->astdbFile,
            'private_nodes_file' => $this->privateNodesFile,
            'allstar_db_url' => $this->allstarDbUrl
        ]);
    }
    
    /**
     * Generate the AllStar database by combining private nodes and public database
     */
    public function generateDatabase(bool $isStrictlyPrivate = false, bool $isCronMode = false): bool
    {
        $this->logger->info('Starting AllStar database generation', [
            'strictly_private' => $isStrictlyPrivate,
            'cron_mode' => $isCronMode,
            'astdb_file' => $this->astdbFile,
            'private_nodes_file' => $this->privateNodesFile
        ]);
        
        try {
            // Add random delay for cron mode (like original script)
            if ($isCronMode && !$isStrictlyPrivate) {
                $delaySeconds = mt_rand(0, self::CRON_MAX_DELAY_SECONDS);
                if ($delaySeconds > 0) {
                    $this->logger->info('Cron mode: Adding random delay', ['delay_seconds' => $delaySeconds]);
                    sleep($delaySeconds);
                }
            }
            
            // Load private nodes
            $privateNodesContent = $this->loadPrivateNodes();
            
            // Load public AllStar database (unless strictly private)
            $allstarNodesContent = '';
            if (!$isStrictlyPrivate) {
                $allstarNodesContent = $this->fetchAllStarDatabase();
            }
            
            // Combine content
            $finalContent = $privateNodesContent . $allstarNodesContent;
            
            // Clean content
            $finalContent = $this->cleanContent($finalContent);
            
            // Write to file
            if (empty($finalContent) && $isStrictlyPrivate && empty($privateNodesContent)) {
                $this->logger->warning('No data to write - strictly private mode and no private nodes found');
                return false;
            }
            
            if (empty($finalContent) && !$isStrictlyPrivate) {
                $this->logger->warning('No data to write - public fetch failed and no private nodes');
                return false;
            }
            
            $this->writeDatabaseFile($finalContent);
            
            // Update last generation timestamp
            $this->updateLastGenerationTime();
            
            $this->logger->info('AllStar database generation completed successfully', [
                'bytes_written' => strlen($finalContent)
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to generate AllStar database', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return false;
        }
    }
    
    /**
     * Check if automatic update is needed and perform it
     */
    public function checkAndPerformAutomaticUpdate(): bool
    {
        $lastUpdate = $this->getLastGenerationTime();
        $currentTime = time();
        
        // Check if enough time has passed since last update
        if ($lastUpdate && ($currentTime - $lastUpdate) < self::AUTO_UPDATE_INTERVAL) {
            $this->logger->debug('Automatic update not needed yet', [
                'last_update' => $lastUpdate,
                'current_time' => $currentTime,
                'time_since_last' => $currentTime - $lastUpdate,
                'interval' => self::AUTO_UPDATE_INTERVAL
            ]);
            return false;
        }
        
        $this->logger->info('Performing automatic database update');
        return $this->generateDatabase(false, true);
    }
    
    /**
     * Get the last generation time from cache
     */
    private function getLastGenerationTime(): ?int
    {
        try {
            return $this->cache->get('astdb_last_generation', function() {
                return null;
            });
        } catch (Exception $e) {
            $this->logger->warning('Could not retrieve last generation time', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Update the last generation time in cache
     */
    private function updateLastGenerationTime(): void
    {
        try {
            $this->cache->delete('astdb_last_generation');
            $this->cache->get('astdb_last_generation', function() {
                return time();
            });
        } catch (Exception $e) {
            $this->logger->warning('Could not update last generation time', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Load private nodes from file
     */
    private function loadPrivateNodes(): string
    {
        if (!file_exists($this->privateNodesFile)) {
            $this->logger->info('Private nodes file not found', ['file' => $this->privateNodesFile]);
            return '';
        }
        
        $lines = @file($this->privateNodesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $this->logger->warning('Could not read private nodes file', ['file' => $this->privateNodesFile]);
            return '';
        }
        
        $validLines = [];
        foreach ($lines as $idx => $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 4 && trim($parts[0]) !== '') {
                $validLines[] = $line;
            } else {
                $this->logger->warning('Skipping malformed private node entry', [
                    'line' => $idx + 1,
                    'content' => $line
                ]);
            }
        }
        
        $content = implode("\n", $validLines) . (count($validLines) ? "\n" : "");
        $this->logger->info('Loaded private nodes', [
            'bytes' => strlen($content),
            'valid_entries' => count($validLines)
        ]);
        
        return $content;
    }
    
    /**
     * Fetch AllStar database from remote source
     */
    private function fetchAllStarDatabase(): string
    {
        $this->logger->info('Fetching AllStar database from remote source', ['url' => $this->allstarDbUrl]);
        
        $streamContext = stream_context_create([
            'http' => [
                'timeout' => self::HTTP_TIMEOUT_SECONDS,
                'ignore_errors' => true
            ]
        ]);
        
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $this->logger->info('Attempt to fetch AllStar database', [
                'attempt' => $attempt,
                'max_attempts' => self::MAX_RETRIES
            ]);
            
            $currentContent = file_get_contents($this->allstarDbUrl, false, $streamContext);
            $http_response_header = $http_response_header ?? [];
            
            if ($currentContent !== false) {
                $contentLength = strlen($currentContent);
                $statusLine = $http_response_header[0] ?? '';
                preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
                $statusCode = isset($match[1]) ? (int)$match[1] : 0;
                
                if ($statusCode >= 200 && $statusCode < 300 && $contentLength >= self::MIN_DB_SIZE_BYTES) {
                    $this->logger->info('Successfully fetched AllStar database', [
                        'bytes' => $contentLength,
                        'status_code' => $statusCode
                    ]);
                    return $currentContent;
                } else {
                    $reason = "";
                    if (!($statusCode >= 200 && $statusCode < 300)) {
                        $reason .= "HTTP status $statusCode. ";
                    }
                    if ($contentLength < self::MIN_DB_SIZE_BYTES) {
                        $reason .= "File too small ($contentLength bytes, minimum " . self::MIN_DB_SIZE_BYTES . "). ";
                    }
                    $this->logger->warning('Fetch failed', ['reason' => $reason]);
                }
            } else {
                $lastError = error_get_last();
                $errorMsg = $lastError ? $lastError['message'] : "Unknown error";
                $this->logger->warning('Fetch failed - connection error', ['error' => $errorMsg]);
            }
            
            if ($attempt < self::MAX_RETRIES) {
                $this->logger->info('Retrying fetch', ['delay_seconds' => self::RETRY_SLEEP_SECONDS]);
                sleep(self::RETRY_SLEEP_SECONDS);
            }
        }
        
        $this->logger->error('Max retries exceeded for fetching AllStar database');
        return '';
    }
    
    /**
     * Clean content by removing invalid characters
     */
    private function cleanContent(string $content): string
    {
        return preg_replace('/[\x00-\x09\x0B-\x0C\x0E-\x1F\x7F-\xFF]/', '', $content);
    }
    
    /**
     * Write database content to file
     */
    private function writeDatabaseFile(string $content): void
    {
        $fh = fopen($this->astdbFile, 'w');
        if ($fh === false) {
            throw new Exception("Cannot open output file for writing: {$this->astdbFile}");
        }
        
        if (!flock($fh, LOCK_EX)) {
            fclose($fh);
            throw new Exception("Unable to obtain exclusive lock on {$this->astdbFile}");
        }
        
        if (fwrite($fh, $content) === false) {
            flock($fh, LOCK_UN);
            fclose($fh);
            throw new Exception("Cannot write to {$this->astdbFile}");
        }
        
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        
        $this->logger->info('Database file written successfully', [
            'file' => $this->astdbFile,
            'bytes' => strlen($content)
        ]);
    }
    
    /**
     * Get database status information
     */
    public function getDatabaseStatus(): array
    {
        $lastUpdate = $this->getLastGenerationTime();
        $currentTime = time();
        
        $status = [
            'file_exists' => file_exists($this->astdbFile),
            'file_size' => file_exists($this->astdbFile) ? filesize($this->astdbFile) : 0,
            'last_modified' => file_exists($this->astdbFile) ? filemtime($this->astdbFile) : null,
            'last_generation' => $lastUpdate,
            'next_auto_update' => $lastUpdate ? $lastUpdate + self::AUTO_UPDATE_INTERVAL : null,
            'auto_update_enabled' => true,
            'auto_update_interval_hours' => self::AUTO_UPDATE_INTERVAL / 3600,
            'private_nodes_file_exists' => file_exists($this->privateNodesFile),
            'private_nodes_count' => 0,
            'allstar_db_url' => $this->allstarDbUrl
        ];
        
        // Count private nodes
        if (file_exists($this->privateNodesFile)) {
            $lines = @file($this->privateNodesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                $status['private_nodes_count'] = count(array_filter($lines, function($line) {
                    $parts = explode('|', $line);
                    return count($parts) >= 4 && trim($parts[0]) !== '';
                }));
            }
        }
        
        return $status;
    }
    
    /**
     * Force immediate update regardless of timing
     */
    public function forceUpdate(): bool
    {
        $this->logger->info('Forcing immediate database update');
        return $this->generateDatabase(false, false);
    }
}
