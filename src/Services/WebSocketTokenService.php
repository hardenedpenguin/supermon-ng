<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Short-lived tokens for WebSocket subscribe (shared between HTTP API and WS daemon).
 */
final class WebSocketTokenService
{
    private const TTL_SECONDS = 120;

    private FilesystemAdapter $cache;

    public function __construct(?string $cacheDir = null)
    {
        $dir = $cacheDir ?? dirname(__DIR__) . '/cache/ws_tokens';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->cache = new FilesystemAdapter('ws_tokens', self::TTL_SECONDS, $dir);
    }

    public function issue(string $nodeId, string $username): string
    {
        $token = bin2hex(random_bytes(24));
        $key = $this->cacheKey($token);

        $this->cache->delete($key);
        $this->cache->get($key, function (ItemInterface $item) use ($nodeId, $username) {
            $item->expiresAfter(self::TTL_SECONDS);
            return json_encode([
                'node_id' => $nodeId,
                'user' => $username,
                'issued_at' => time(),
            ], JSON_THROW_ON_ERROR);
        });

        return $token;
    }

    /**
     * @return array{node_id: string, user: string}|null
     */
    public function validate(string $token, ?string $expectedNodeId = null): ?array
    {
        if ($token === '' || !preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }

        $key = $this->cacheKey($token);
        $raw = $this->cache->get($key, static fn () => null);
        if ($raw === null || !is_string($raw)) {
            return null;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (empty($data['node_id']) || empty($data['user'])) {
            return null;
        }

        if ($expectedNodeId !== null && (string) $data['node_id'] !== (string) $expectedNodeId) {
            return null;
        }

        return [
            'node_id' => (string) $data['node_id'],
            'user' => (string) $data['user'],
        ];
    }

    public function revoke(string $token): void
    {
        if ($token !== '') {
            $this->cache->delete($this->cacheKey($token));
        }
    }

    private function cacheKey(string $token): string
    {
        return 'ws.' . $token;
    }
}
