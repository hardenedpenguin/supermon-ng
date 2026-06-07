<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;

final class SystemHealthService
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getHealth(): array
    {
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

        return [
            'healthy' => $healthy,
            'checks' => $checks,
            'checked_at' => date('c'),
        ];
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
        $url = $_ENV['BACKEND_HEALTH_URL'] ?? 'http://127.0.0.1:8000/health';

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'ignore_errors' => true,
                ],
            ]);
            $body = @file_get_contents($url, false, $context);
            if ($body === false) {
                return [
                    'ok' => false,
                    'state' => 'unreachable',
                    'hint' => 'Check supermon-ng-backend.service and port 8000',
                ];
            }

            $decoded = json_decode($body, true);
            $ok = is_array($decoded) && ($decoded['status'] ?? '') === 'healthy';

            return [
                'ok' => $ok,
                'state' => $ok ? 'healthy' : 'unhealthy',
                'hint' => $ok ? null : 'Backend /health did not return healthy',
            ];
        } catch (\Throwable $e) {
            $this->logger->warning('Backend health check failed', ['error' => $e->getMessage()]);

            return [
                'ok' => false,
                'state' => 'error',
                'hint' => 'Backend health check failed',
            ];
        }
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
