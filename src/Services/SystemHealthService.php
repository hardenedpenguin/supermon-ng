<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;

final class SystemHealthService
{
    private const CACHE_TTL_SECONDS = 45;

    private ?array $cachedHealth = null;
    private int $cachedAt = 0;

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getHealth(): array
    {
        $now = time();
        if ($this->cachedHealth !== null && ($now - $this->cachedAt) < self::CACHE_TTL_SECONDS) {
            return $this->cachedHealth;
        }

        $checks = [
            'backend_service' => $this->checkSystemdUnit('supermon-ng-backend.service'),
            'websocket_service' => $this->checkSystemdUnit('supermon-ng-websocket.service'),
            'node_status_timer' => $this->checkSystemdTimer('supermon-ng-node-status.timer'),
            'database_timer' => $this->checkSystemdTimer('supermon-ng-database-update.timer'),
            'backend_http' => $this->checkBackendHttp(),
            'websocket_port' => $this->checkTcpPort(
                '127.0.0.1',
                (int) ($_ENV['WEBSOCKET_PORT'] ?? 8105)
            ),
        ];

        $healthy = true;
        foreach (['backend_service', 'websocket_service', 'backend_http', 'websocket_port'] as $key) {
            if (empty($checks[$key]['ok'])) {
                $healthy = false;
                break;
            }
        }

        $this->cachedHealth = [
            'healthy' => $healthy,
            'checks' => $checks,
            'checked_at' => date('c'),
        ];
        $this->cachedAt = $now;

        return $this->cachedHealth;
    }

    /**
     * @return array{ok: bool, state: string, enabled: string|null, hint?: string}
     */
    private function checkSystemdUnit(string $unit): array
    {
        $active = $this->systemctlValue('is-active', $unit);
        $enabled = $this->systemctlValue('is-enabled', $unit);

        return [
            'ok' => $active === 'active',
            'state' => $active ?: 'unknown',
            'enabled' => $enabled ?: 'unknown',
            'hint' => $active === 'active' ? null : "sudo systemctl status {$unit}",
        ];
    }

    /**
     * @return array{ok: bool, state: string, enabled: string|null, hint?: string}
     */
    private function checkSystemdTimer(string $timer): array
    {
        $active = $this->systemctlValue('is-active', $timer);
        $enabled = $this->systemctlValue('is-enabled', $timer);

        return [
            'ok' => in_array($active, ['active', 'waiting'], true),
            'state' => $active ?: 'unknown',
            'enabled' => $enabled ?: 'unknown',
            'hint' => in_array($active, ['active', 'waiting'], true) ? null : "sudo systemctl status {$timer}",
        ];
    }

    /**
     * @return array{ok: bool, state: string, hint?: string}
     */
    private function checkBackendHttp(): array
    {
        // php -S is single-threaded: an HTTP loopback from inside a request deadlocks.
        if (PHP_SAPI === 'cli-server') {
            return [
                'ok' => true,
                'state' => 'healthy',
            ];
        }

        $port = (int) ($_ENV['BACKEND_PORT'] ?? 8000);
        $body = $this->fetchHealthViaSocket('::1', $port)
            ?? $this->fetchHealthViaSocket('127.0.0.1', $port);

        if ($body !== null) {
            $decoded = json_decode($body, true);
            if (is_array($decoded) && ($decoded['status'] ?? '') === 'healthy') {
                return [
                    'ok' => true,
                    'state' => 'healthy',
                ];
            }
        }

        if (!empty($_ENV['BACKEND_HEALTH_URL'])) {
            try {
                $context = stream_context_create([
                    'http' => ['timeout' => 3, 'ignore_errors' => true],
                ]);
                $remote = @file_get_contents((string) $_ENV['BACKEND_HEALTH_URL'], false, $context);
                if ($remote !== false) {
                    $decoded = json_decode($remote, true);
                    if (is_array($decoded) && ($decoded['status'] ?? '') === 'healthy') {
                        return ['ok' => true, 'state' => 'healthy'];
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Backend health URL probe failed', ['error' => $e->getMessage()]);
            }
        }

        return [
            'ok' => false,
            'state' => 'unreachable',
            'hint' => 'Check supermon-ng-backend.service (php -S localhost:8000)',
        ];
    }

    private function fetchHealthViaSocket(string $host, int $port): ?string
    {
        $target = $host === '::1'
            ? "tcp://[::1]:{$port}"
            : "tcp://{$host}:{$port}";

        $socket = @stream_socket_client($target, $errno, $errstr, 2.0);
        if ($socket === false) {
            return null;
        }

        fwrite($socket, "GET /health HTTP/1.0\r\nHost: localhost\r\nConnection: close\r\n\r\n");
        $response = stream_get_contents($socket);
        fclose($socket);

        if (!is_string($response) || $response === '') {
            return null;
        }

        $parts = explode("\r\n\r\n", $response, 2);

        return $parts[1] ?? null;
    }

    /**
     * @return array{ok: bool, state: string, hint?: string}
     */
    private function checkTcpPort(string $host, int $port): array
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, 2.0);
        if ($socket === false) {
            return [
                'ok' => false,
                'state' => 'closed',
                'hint' => "Port {$port} not accepting connections on {$host}",
            ];
        }

        fclose($socket);

        return [
            'ok' => true,
            'state' => 'open',
        ];
    }

    private function systemctlValue(string $command, string $unit): string
    {
        $output = shell_exec(
            '/bin/systemctl ' . escapeshellarg($command) . ' ' . escapeshellarg($unit) . ' 2>/dev/null'
        );

        return trim((string) $output);
    }
}
