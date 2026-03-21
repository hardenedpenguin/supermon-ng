<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\UserPermissionService;
use Throwable;

class SystemController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UserPermissionService $userPermissionService
    ) {
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
        $denied = $this->requireSystemPermission($response, 'ASTRELUSER');
        if ($denied !== null) {
            return $denied;
        }

        $this->logger->info('System reload request');
        
        try {
            [$output, $returnCode] = $this->executeShellCommand("asterisk -rx 'core reload'");
            
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
        } catch (Throwable $e) {
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
        $denied = $this->requireSystemPermission($response, 'ASTSTRUSER');
        if ($denied !== null) {
            return $denied;
        }

        $this->logger->info('System start request');
        
        try {
            [$output, $returnCode] = $this->executeShellCommand('systemctl start asterisk');
            
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
        } catch (Throwable $e) {
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
        $denied = $this->requireSystemPermission($response, 'ASTSTPUSER');
        if ($denied !== null) {
            return $denied;
        }

        $this->logger->info('System stop request');
        
        try {
            [$output, $returnCode] = $this->executeShellCommand('systemctl stop asterisk');
            
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
        } catch (Throwable $e) {
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
        $denied = $this->requireSystemPermission($response, 'FSTRESUSER');
        if ($denied !== null) {
            return $denied;
        }

        $this->logger->info('System fast restart request');
        
        try {
            [$output, $returnCode] = $this->executeShellCommand("asterisk -rx 'core restart now'");
            
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
        } catch (Throwable $e) {
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
        $denied = $this->requireSystemPermission($response, 'RBTUSER');
        if ($denied !== null) {
            return $denied;
        }

        $this->logger->info('System reboot request');
        
        try {
            [$output, $returnCode] = $this->executeShellCommand('sudo reboot');
            
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
        } catch (Throwable $e) {
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
     * @return Response|null 403 JSON response if denied, null if allowed
     */
    private function requireSystemPermission(Response $response, string $permission): ?Response
    {
        $user = $this->getCurrentUserStrict();
        if ($user === null || !$this->userPermissionService->hasPermission($user, $permission)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Forbidden',
                'message' => 'You do not have permission to perform this system action.',
            ]));
            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json');
        }
        return null;
    }

    /**
     * Require authenticated session (same rules as login) for system control.
     */
    private function getCurrentUserStrict(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['user']) && $_SESSION['user'] !== '' && isset($_SESSION['authenticated']) && $_SESSION['authenticated']) {
            if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) < 86400) {
                return $_SESSION['user'];
            }
            session_destroy();
            return null;
        }

        if (isset($_SERVER['PHP_AUTH_USER']) && $_SERVER['PHP_AUTH_USER'] !== '') {
            return $_SERVER['PHP_AUTH_USER'];
        }

        if (isset($_SERVER['REMOTE_USER']) && $_SERVER['REMOTE_USER'] !== '') {
            return $_SERVER['REMOTE_USER'];
        }

        return null;
    }

    /**
     * @return array{0: string, 1: int} [combined output, exit code]
     */
    private function executeShellCommand(string $command): array
    {
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        return [implode("\n", $output), $returnCode];
    }
}
