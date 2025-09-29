<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Symfony\Contracts\Cache\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for caching API responses and frequently accessed data
 */
class CacheService
{
    private CacheInterface $cache;
    private LoggerInterface $logger;
    
    public function __construct(CacheInterface $cache, LoggerInterface $logger)
    {
        $this->cache = $cache;
        $this->logger = $logger;
    }
    
    /**
     * Cache API response data with TTL
     */
    public function cacheApiResponse(string $key, mixed $data, int $ttlSeconds = 300): mixed
    {
        try {
            $this->cache->set($key, $data, $ttlSeconds);
            return $data;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to cache API response', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $data; // Return data even if caching fails
        }
    }
    
    /**
     * Get cached API response
     */
    public function getCachedApiResponse(string $key): mixed
    {
        try {
            return $this->cache->get($key, function () {
                return null; // Return null if not in cache
            });
        } catch (\Exception $e) {
            $this->logger->warning('Failed to get cached API response', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Cache menu items (long-term cache)
     */
    public function cacheMenuItems(string $username, array $menuItems): array
    {
        $key = "menu_items:$username";
        return $this->cacheApiResponse($key, $menuItems, 1800); // 30 minutes
    }
    
    /**
     * Get cached menu items
     */
    public function getCachedMenuItems(string $username): ?array
    {
        $key = "menu_items:$username";
        return $this->getCachedApiResponse($key);
    }
    
    /**
     * Cache node configuration (medium-term cache)
     */
    public function cacheNodeConfig(string $nodeId, array $config): array
    {
        $key = "node_config:$nodeId";
        return $this->cacheApiResponse($key, $config, 600); // 10 minutes
    }
    
    /**
     * Get cached node configuration
     */
    public function getCachedNodeConfig(string $nodeId): ?array
    {
        $key = "node_config:$nodeId";
        return $this->getCachedApiResponse($key);
    }
    
    /**
     * Cache system info (short-term cache)
     */
    public function cacheSystemInfo(array $systemInfo): array
    {
        $key = "system_info";
        return $this->cacheApiResponse($key, $systemInfo, 60); // 1 minute
    }
    
    /**
     * Get cached system info
     */
    public function getCachedSystemInfo(): ?array
    {
        $key = "system_info";
        return $this->getCachedApiResponse($key);
    }
    
    /**
     * Cache parsed INI file data
     */
    public function cacheIniFile(string $filePath, array $data): array
    {
        $key = "ini_file:" . md5($filePath);
        return $this->cacheApiResponse($key, $data, 900); // 15 minutes
    }
    
    /**
     * Get cached INI file data
     */
    public function getCachedIniFile(string $filePath): ?array
    {
        $key = "ini_file:" . md5($filePath);
        return $this->getCachedApiResponse($key);
    }
    
    /**
     * Invalidate cache for a specific key
     */
    public function invalidateCache(string $key): void
    {
        try {
            $this->cache->delete($key);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to invalidate cache', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Invalidate all caches for a user
     */
    public function invalidateUserCache(string $username): void
    {
        $this->invalidateCache("menu_items:$username");
        // Add other user-specific cache keys here
    }
    
    /**
     * Clear all cache
     */
    public function clearAllCache(): void
    {
        try {
            $this->cache->clear();
        } catch (\Exception $e) {
            $this->logger->error('Failed to clear cache', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
