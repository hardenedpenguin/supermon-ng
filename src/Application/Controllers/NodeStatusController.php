<?php

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class NodeStatusController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get node status configuration
     */
    public function getConfig(Request $request, Response $response): Response
    {
        try {
            $configFile = __DIR__ . '/../../../user_files/sbin/node_info.ini';
            
            if (!file_exists($configFile)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Node status configuration not found',
                    'config' => null
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $config = parse_ini_file($configFile, true);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'config' => $config,
                'enabled' => !empty($config['general']['NODE'])
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('Error getting node status config: ' . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error loading configuration: ' . $e->getMessage()
            ]));
            
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Update node status configuration
     */
    public function updateConfig(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $configFile = __DIR__ . '/../../../user_files/sbin/node_info.ini';
            
            // Validate required fields
            if (empty($data['nodes']) || !is_array($data['nodes'])) {
                throw new \InvalidArgumentException('Nodes array is required');
            }

            // Build INI content
            $iniContent = "[general]\n";
            $iniContent .= "NODE = " . implode(' ', $data['nodes']) . "\n";
            $iniContent .= "WX_CODE = " . ($data['wx_code'] ?? '') . "\n";
            $iniContent .= "WX_LOCATION = " . ($data['wx_location'] ?? '') . "\n";
            $iniContent .= "TEMP_UNIT = " . ($data['temp_unit'] ?? 'F') . "\n\n";
            
            $iniContent .= "[autosky]\n";
            $iniContent .= "MASTER_ENABLE = " . ($data['autosky_enabled'] ? 'yes' : 'no') . "\n";
            $iniContent .= "ALERT_INI = " . ($data['alert_ini'] ?? '/usr/local/bin/AUTOSKY/AutoSky.ini') . "\n";
            $iniContent .= "WARNINGS_FILE = " . ($data['warnings_file'] ?? '/var/www/html/AUTOSKY/warnings.txt') . "\n";
            $iniContent .= "CUSTOM_LINK = " . ($data['custom_link'] ?? '') . "\n";

            // Write configuration file
            if (file_put_contents($configFile, $iniContent) === false) {
                throw new \RuntimeException('Failed to write configuration file');
            }

            $this->logger->info('Node status configuration updated');

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Configuration updated successfully'
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('Error updating node status config: ' . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error updating configuration: ' . $e->getMessage()
            ]));
            
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Trigger manual node status update
     */
    public function triggerUpdate(Request $request, Response $response): Response
    {
        try {
            $scriptPath = __DIR__ . '/../../../user_files/sbin/ast_node_status_update.py';
            $configFile = __DIR__ . '/../../../user_files/sbin/node_info.ini';
            
            if (!file_exists($scriptPath)) {
                throw new \RuntimeException('Node status update script not found');
            }
            
            if (!file_exists($configFile)) {
                throw new \RuntimeException('Node status configuration not found');
            }

            // Try to execute via systemd service first (preferred method)
            $systemdOutput = shell_exec("systemctl --no-pager status supermon-ng-node-status.service 2>/dev/null");
            if (strpos($systemdOutput, 'supermon-ng-node-status.service') !== false) {
                // Service exists, trigger it via systemctl
                $command = "/usr/bin/sudo -n /bin/systemctl start supermon-ng-node-status.service 2>&1";
                $output = shell_exec($command);
                
                // Wait a moment for service to complete
                sleep(2);
                
                // Get the actual script output from log file
                $logFile = __DIR__ . '/../../../logs/node-status-update.log';
                if (file_exists($logFile)) {
                    $logContent = shell_exec("tail -20 " . escapeshellarg($logFile) . " 2>/dev/null");
                    if ($logContent) {
                        $output = "Node Status Update Results:\n" . trim($logContent);
                    }
                }
                
                // Also get systemd service status
                $serviceStatus = shell_exec("/usr/bin/sudo -n /bin/systemctl is-active supermon-ng-node-status.service 2>/dev/null");
                if ($serviceStatus) {
                    $output .= "\n\nService Status: " . trim($serviceStatus);
                }
            } else {
                // Fallback to direct script execution
                $command = "/usr/bin/sudo -n " . escapeshellarg($scriptPath) . " 2>&1";
                $output = shell_exec($command);
            }
            
            $this->logger->info('Node status update triggered', ['output' => $output]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Node status update triggered successfully',
                'output' => $output
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('Error triggering node status update: ' . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error triggering update: ' . $e->getMessage()
            ]));
            
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get node status update service status
     */
    public function getServiceStatus(Request $request, Response $response): Response
    {
        try {
            // Check if systemd service exists and is running
            $serviceStatus = shell_exec('systemctl is-active supermon-ng-node-status 2>/dev/null');
            $serviceEnabled = shell_exec('systemctl is-enabled supermon-ng-node-status 2>/dev/null');
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'service_active' => trim($serviceStatus ?? '') === 'active',
                'service_enabled' => trim($serviceEnabled ?? '') === 'enabled',
                'last_update' => $this->getLastUpdateTime()
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('Error getting service status: ' . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error getting service status: ' . $e->getMessage()
            ]));
            
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    private function getLastUpdateTime(): ?string
    {
        $logFile = __DIR__ . '/../../../logs/node-status-update.log';
        
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!empty($lines)) {
                $lastLine = end($lines);
                // Extract timestamp from log line if it exists
                if (preg_match('/^\[(.*?)\]/', $lastLine, $matches)) {
                    return $matches[1];
                }
            }
        }
        
        return null;
    }
}
