<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\ApiResponseHelper;
use SupermonNg\Services\SessionService;
use SupermonNg\Services\UserPermissionService;

class NodeStatusController
{
    private LoggerInterface $logger;
    private SessionService $sessionService;
    private UserPermissionService $userPermissionService;

    public function __construct(
        LoggerInterface $logger,
        SessionService $sessionService,
        UserPermissionService $userPermissionService
    ) {
        $this->logger = $logger;
        $this->sessionService = $sessionService;
        $this->userPermissionService = $userPermissionService;
    }

    private function requireSysInfUser(Response $response): ?Response
    {
        $user = $this->sessionService->getCurrentUser();
        if ($user === null) {
            return ApiResponseHelper::error($response, 'Authentication required', 401);
        }
        if (!$this->userPermissionService->hasPermission($user, 'SYSINFUSER')) {
            return ApiResponseHelper::error($response, 'You are not authorized to manage node status settings.', 403);
        }
        return null;
    }

    /**
     * Get node status configuration
     */
    public function getConfig(Request $request, Response $response): Response
    {
        if ($denied = $this->requireSysInfUser($response)) {
            return $denied;
        }

        try {
            $configFile = __DIR__ . '/../../../user_files/sbin/node_info.ini';

            if (!file_exists($configFile)) {
                return ApiResponseHelper::json($response, [
                    'success' => false,
                    'message' => 'Node status configuration not found',
                    'config' => null,
                ]);
            }

            $config = parse_ini_file($configFile, true);

            return ApiResponseHelper::json($response, [
                'success' => true,
                'config' => $config,
                'enabled' => !empty($config['general']['NODE']),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error getting node status config: ' . $e->getMessage());

            return ApiResponseHelper::error(
                $response,
                'Error loading configuration: ' . ApiResponseHelper::safeExceptionMessage($e),
                500
            );
        }
    }

    /**
     * Update node status configuration
     */
    public function updateConfig(Request $request, Response $response): Response
    {
        if ($denied = $this->requireSysInfUser($response)) {
            return $denied;
        }

        try {
            $data = $request->getParsedBody() ?? [];
            $configFile = __DIR__ . '/../../../user_files/sbin/node_info.ini';

            if (empty($data['nodes']) || !is_array($data['nodes'])) {
                return ApiResponseHelper::error($response, 'Nodes array is required', 400);
            }

            $iniContent = "[general]\n";
            $iniContent .= "NODE = " . implode(' ', $data['nodes']) . "\n";
            $iniContent .= "WX_USE_GPS = " . (!empty($data['wx_use_gps']) ? 'yes' : 'no') . "\n";
            $iniContent .= "WX_CODE = " . ($data['wx_code'] ?? '') . "\n";
            $iniContent .= "WX_LOCATION = " . ($data['wx_location'] ?? '') . "\n";
            $iniContent .= "TEMP_UNIT = " . ($data['temp_unit'] ?? 'F') . "\n\n";

            $provider = strtolower((string) ($data['alert_provider'] ?? 'skywarnplus'));
            if (!in_array($provider, ['skywarnplus', 'canwarn_ng'], true)) {
                $provider = 'skywarnplus';
            }
            $iniContent .= "ALERT_PROVIDER = " . $provider . "\n";
            if (!empty($data['alert_product'])) {
                $iniContent .= "ALERT_PRODUCT = " . (string) $data['alert_product'] . "\n";
            }
            $iniContent .= "\n";

            $iniContent .= "[skywarnplus]\n";
            $iniContent .= "MASTER_ENABLE = " . (!empty($data['skywarnplus_enabled']) ? 'yes' : 'no') . "\n";
            $iniContent .= "API_URL = " . ($data['skywarnplus_api_url'] ?? ($data['api_url'] ?? '')) . "\n\n";

            $iniContent .= "[canwarn_ng]\n";
            $iniContent .= "MASTER_ENABLE = " . (!empty($data['canwarn_enabled']) ? 'yes' : 'no') . "\n";
            $iniContent .= "API_URL = " . ($data['canwarn_api_url'] ?? '') . "\n";

            if (file_put_contents($configFile, $iniContent) === false) {
                throw new \RuntimeException('Failed to write configuration file');
            }

            $this->logger->info('Node status configuration updated');

            return ApiResponseHelper::json($response, [
                'success' => true,
                'message' => 'Configuration updated successfully',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error updating node status config: ' . $e->getMessage());

            return ApiResponseHelper::error(
                $response,
                'Error updating configuration: ' . ApiResponseHelper::safeExceptionMessage($e),
                500
            );
        }
    }

    /**
     * Trigger manual node status update
     */
    public function triggerUpdate(Request $request, Response $response): Response
    {
        if ($denied = $this->requireSysInfUser($response)) {
            return $denied;
        }

        try {
            $scriptPath = __DIR__ . '/../../../user_files/sbin/ast_node_status_update.py';
            $configFile = __DIR__ . '/../../../user_files/sbin/node_info.ini';

            if (!file_exists($scriptPath)) {
                return ApiResponseHelper::error($response, 'Node status update script not found', 500);
            }

            if (!file_exists($configFile)) {
                return ApiResponseHelper::error($response, 'Node status configuration not found', 500);
            }

            $systemdOutput = (string) shell_exec('/usr/bin/sudo -n /bin/systemctl --no-pager status supermon-ng-node-status.service 2>&1');
            if ($systemdOutput !== '' && str_contains($systemdOutput, 'supermon-ng-node-status.service')) {
                $command = '/usr/bin/sudo -n /bin/systemctl start supermon-ng-node-status.service 2>&1';
                $output = (string) shell_exec($command);

                sleep(2);

                $logFile = __DIR__ . '/../../../logs/node-status-update.log';
                if (file_exists($logFile)) {
                    $logContent = (string) shell_exec('tail -20 ' . escapeshellarg($logFile) . ' 2>/dev/null');
                    if ($logContent) {
                        $output = "Node Status Update Results:\n" . trim($logContent);
                    }
                }

                $serviceStatus = (string) shell_exec('/usr/bin/sudo -n /bin/systemctl is-active supermon-ng-node-status.service 2>/dev/null');
                if ($serviceStatus !== '') {
                    $output .= "\n\nService Status: " . trim($serviceStatus);
                }
            } else {
                $command = '/usr/bin/sudo -n ' . escapeshellarg($scriptPath) . ' 2>&1';
                $output = (string) shell_exec($command);
            }

            $this->logger->info('Node status update triggered', ['output' => $output]);

            return ApiResponseHelper::json($response, [
                'success' => true,
                'message' => 'Node status update triggered successfully',
                'output' => $output,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error triggering node status update: ' . $e->getMessage());

            return ApiResponseHelper::error(
                $response,
                'Error triggering update: ' . ApiResponseHelper::safeExceptionMessage($e),
                500
            );
        }
    }

    /**
     * Get node status update service status
     */
    public function getServiceStatus(Request $request, Response $response): Response
    {
        if ($denied = $this->requireSysInfUser($response)) {
            return $denied;
        }

        try {
            $serviceStatus = (string) shell_exec('/usr/bin/sudo -n /bin/systemctl is-active supermon-ng-node-status.service 2>/dev/null');
            $serviceEnabled = (string) shell_exec('/usr/bin/sudo -n /bin/systemctl is-enabled supermon-ng-node-status.service 2>/dev/null');

            return ApiResponseHelper::json($response, [
                'success' => true,
                'service_active' => trim($serviceStatus) === 'active',
                'service_enabled' => trim($serviceEnabled) === 'enabled',
                'last_update' => $this->getLastUpdateTime(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error getting service status: ' . $e->getMessage());

            return ApiResponseHelper::error(
                $response,
                'Error getting service status: ' . ApiResponseHelper::safeExceptionMessage($e),
                500
            );
        }
    }

    private function getLastUpdateTime(): ?string
    {
        $logFile = __DIR__ . '/../../../logs/node-status-update.log';

        if (!file_exists($logFile)) {
            return null;
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || $lines === []) {
            return null;
        }

        $lastLine = end($lines);
        if (preg_match('/^\[(.*?)\]/', (string) $lastLine, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
