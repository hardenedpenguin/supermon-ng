<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class SystemController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function info(Request $request, Response $response): Response
    {
        $this->logger->info('System info request');
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'System info endpoint - to be implemented',
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function stats(Request $request, Response $response): Response
    {
        $this->logger->info('System stats request');
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'System stats endpoint - to be implemented',
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function logs(Request $request, Response $response): Response
    {
        $this->logger->info('System logs request');
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'System logs endpoint - to be implemented',
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getLogs(Request $request, Response $response, array $args): Response
    {
        $logType = $args['type'] ?? 'general';
        $this->logger->info("System logs request for type: $logType");
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => "System logs endpoint for type '$logType' - to be implemented",
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getClientIP(Request $request, Response $response): Response
    {
        $this->logger->info('Client IP request');
        
        // Get client IP from various sources
        $clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
                   $_SERVER['HTTP_X_REAL_IP'] ?? 
                   $_SERVER['REMOTE_ADDR'] ?? 
                   '127.0.0.1';
        
        // Handle multiple IPs in X-Forwarded-For header
        if (strpos($clientIP, ',') !== false) {
            $clientIP = trim(explode(',', $clientIP)[0]);
        }
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'ip' => $clientIP,
                'timestamp' => date('c')
            ],
            'message' => 'Client IP retrieved successfully'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function reload(Request $request, Response $response): Response
    {
        $this->logger->info('System reload request');
        
        try {
            // Execute Asterisk reload command
            $command = "asterisk -rx 'core reload'";
            $output = shell_exec($command . " 2>&1");
            $returnCode = $this->getLastReturnCode();
            
            if ($returnCode === 0) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'System reload completed successfully',
                    'output' => $output,
                    'timestamp' => date('c')
                ]));
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'System reload failed',
                    'output' => $output,
                    'timestamp' => date('c')
                ]));
                
                return $response
                    ->withStatus(500)
                    ->withHeader('Content-Type', 'application/json');
            }
        } catch (Exception $e) {
            $this->logger->error('System reload error', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'System reload error: ' . $e->getMessage(),
                'timestamp' => date('c')
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function start(Request $request, Response $response): Response
    {
        $this->logger->info('System start request');
        
        try {
            // Execute Asterisk start command
            $command = "systemctl start asterisk";
            $output = shell_exec($command . " 2>&1");
            $returnCode = $this->getLastReturnCode();
            
            if ($returnCode === 0) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Asterisk started successfully',
                    'output' => $output,
                    'timestamp' => date('c')
                ]));
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Failed to start Asterisk',
                    'output' => $output,
                    'timestamp' => date('c')
                ]));
                
                return $response
                    ->withStatus(500)
                    ->withHeader('Content-Type', 'application/json');
            }
        } catch (Exception $e) {
            $this->logger->error('System start error', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'System start error: ' . $e->getMessage(),
                'timestamp' => date('c')
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function stop(Request $request, Response $response): Response
    {
        $this->logger->info('System stop request');
        
        try {
            // Execute Asterisk stop command
            $command = "systemctl stop asterisk";
            $output = shell_exec($command . " 2>&1");
            $returnCode = $this->getLastReturnCode();
            
            if ($returnCode === 0) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Asterisk stopped successfully',
                    'output' => $output,
                    'timestamp' => date('c')
                ]));
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Failed to stop Asterisk',
                    'output' => $output,
                    'timestamp' => date('c')
                ]));
                
                return $response
                    ->withStatus(500)
                    ->withHeader('Content-Type', 'application/json');
            }
        } catch (Exception $e) {
            $this->logger->error('System stop error', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'System stop error: ' . $e->getMessage(),
                'timestamp' => date('c')
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function fastRestart(Request $request, Response $response): Response
    {
        $this->logger->info('System fast restart request');
        
        try {
            // Execute Asterisk fast restart command
            $command = "asterisk -rx 'core restart now'";
            $output = shell_exec($command . " 2>&1");
            $returnCode = $this->getLastReturnCode();
            
            if ($returnCode === 0) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Asterisk fast restart completed successfully',
                    'output' => $output,
                    'timestamp' => date('c')
                ]));
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Failed to fast restart Asterisk',
                    'output' => $output,
                    'timestamp' => date('c')
                ]));
                
                return $response
                    ->withStatus(500)
                    ->withHeader('Content-Type', 'application/json');
            }
        } catch (Exception $e) {
            $this->logger->error('System fast restart error', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'System fast restart error: ' . $e->getMessage(),
                'timestamp' => date('c')
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function reboot(Request $request, Response $response): Response
    {
        $this->logger->info('System reboot request');
        
        try {
            // Execute system reboot command
            $command = "sudo reboot";
            $output = shell_exec($command . " 2>&1");
            $returnCode = $this->getLastReturnCode();
            
            if ($returnCode === 0) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'System reboot initiated successfully',
                    'output' => $output,
                    'timestamp' => date('c')
                ]));
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Failed to initiate system reboot',
                    'output' => $output,
                    'timestamp' => date('c')
                ]));
                
                return $response
                    ->withStatus(500)
                    ->withHeader('Content-Type', 'application/json');
            }
        } catch (Exception $e) {
            $this->logger->error('System reboot error', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'System reboot error: ' . $e->getMessage(),
                'timestamp' => date('c')
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get the return code from the last shell_exec command
     */
    private function getLastReturnCode(): int
    {
        return $GLOBALS['?'] ?? 0;
    }
}
