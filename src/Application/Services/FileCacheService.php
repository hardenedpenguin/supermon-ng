<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Symfony\Contracts\Cache\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for caching file-based data like INI files and ASTDB
 */
class FileCacheService
{
    private CacheInterface $cache;
    private LoggerInterface $logger;
    private array $fileTimestamps = [];
    
    public function __construct(CacheInterface $cache, LoggerInterface $logger)
    {
        $this->cache = $cache;
        $this->logger = $logger;
    }
    
    /**
     * Get cached file contents with file modification checking
     */
    public function getCachedFile(string $filePath, int $ttlSeconds = 900): ?array
    {
        try {
            // Check if file exists
            if (!file_exists($filePath)) {
                return null;
            }
            
            // Get current file modification time
            $currentMtime = filemtime($filePath);
            $cacheKey = "file:" . md5($filePath);
            
            // Check if we have cached data for this file
            $cachedData = $this->cache->get($cacheKey, function () {
                return null;
            });
            
            // If no cached data or file has been modified, return null to force refresh
            if ($cachedData === null || 
                !isset($this->fileTimestamps[$filePath]) || 
                $this->fileTimestamps[$filePath] !== $currentMtime) {
                
                return null;
            }
            
            return $cachedData;
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to get cached file', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Cache file contents with modification time tracking
     */
    public function cacheFile(string $filePath, array $data, int $ttlSeconds = 900): array
    {
        try {
            $cacheKey = "file:" . md5($filePath);
            $mtime = file_exists($filePath) ? filemtime($filePath) : 0;
            
            // Store modification time for this file
            $this->fileTimestamps[$filePath] = $mtime;
            
            // Cache the data
            $this->cache->set($cacheKey, $data, $ttlSeconds);
            
            return $data;
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to cache file', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return $data; // Return data even if caching fails
        }
    }
    
    /**
     * Get cached INI file with parsing
     */
    public function getCachedIniFile(string $filePath): ?array
    {
        $cachedData = $this->getCachedFile($filePath);
        
        if ($cachedData !== null) {
            $this->logger->debug('Using cached INI file', ['file' => $filePath]);
            return $cachedData;
        }
        
        // Parse INI file
        if (!file_exists($filePath)) {
            return null;
        }
        
        $data = parse_ini_file($filePath, true);
        if ($data === false) {
            $this->logger->warning('Failed to parse INI file', ['file' => $filePath]);
            return null;
        }
        
        // Cache the parsed data
        $this->cacheFile($filePath, $data);
        
        return $data;
    }
    
    /**
     * Get cached ASTDB data
     */
    public function getCachedAstdbData(string $filePath = 'astdb.txt'): ?array
    {
        $cachedData = $this->getCachedFile($filePath, 60); // Cache for 1 minute
        
        if ($cachedData !== null) {
            $this->logger->debug('Using cached ASTDB data', ['file' => $filePath]);
            return $cachedData;
        }
        
        // Parse ASTDB file
        if (!file_exists($filePath)) {
            return [];
        }
        
        $data = [];
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines !== false) {
            foreach ($lines as $line) {
                $parts = explode('|', trim($line));
                if (count($parts) >= 2) {
                    $data[trim($parts[0])] = trim($parts[1]);
                }
            }
        }
        
        // Cache the parsed data
        $this->cacheFile($filePath, $data, 60);
        
        return $data;
    }
    
    /**
     * Get cached JSON file
     */
    public function getCachedJsonFile(string $filePath): ?array
    {
        $cachedData = $this->getCachedFile($filePath);
        
        if ($cachedData !== null) {
            $this->logger->debug('Using cached JSON file', ['file' => $filePath]);
            return $cachedData;
        }
        
        // Parse JSON file
        if (!file_exists($filePath)) {
            return null;
        }
        
        $content = file_get_contents($filePath);
        if ($content === false) {
            $this->logger->warning('Failed to read JSON file', ['file' => $filePath]);
            return null;
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('Failed to parse JSON file', [
                'file' => $filePath,
                'error' => json_last_error_msg()
            ]);
            return null;
        }
        
        // Cache the parsed data
        $this->cacheFile($filePath, $data);
        
        return $data;
    }
    
    /**
     * Invalidate cache for a specific file
     */
    public function invalidateFileCache(string $filePath): void
    {
        try {
            $cacheKey = "file:" . md5($filePath);
            $this->cache->delete($cacheKey);
            
            // Remove from timestamp tracking
            unset($this->fileTimestamps[$filePath]);
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to invalidate file cache', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Clear all file caches
     */
    public function clearAllFileCaches(): void
    {
        try {
            $this->cache->clear();
            $this->fileTimestamps = [];
        } catch (\Exception $e) {
            $this->logger->error('Failed to clear file caches', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
