<?php

declare(strict_types=1);

namespace SupermonNg\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class AdminAuthMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $this->logger->info('Admin Auth middleware - checking admin permissions');
        
        // Get user from session
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            $this->logger->warning('Admin Auth middleware - no user session found');
            return $this->createUnauthorizedResponse();
        }

        // Check if user has admin permissions
        if (!$this->hasAdminPermissions($user)) {
            $this->logger->warning('Admin Auth middleware - user lacks admin permissions', ['user' => $user]);
            return $this->createForbiddenResponse();
        }

        $this->logger->info('Admin Auth middleware - user authorized', ['user' => $user]);
        return $handler->handle($request);
    }

    private function hasAdminPermissions(string $user): bool
    {
        // Include necessary files
        require_once __DIR__ . '/../../../includes/common.inc';
        
        // Check admin permissions from configuration
        $adminUsers = [];
        if (isset($GLOBALS['admin_users'])) {
            $adminUsers = $GLOBALS['admin_users'];
        }
        
        return in_array($user, $adminUsers, true);
    }

    private function createUnauthorizedResponse(): Response
    {
        $response = new \GuzzleHttp\Psr7\Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Authentication required'
        ]));
        
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }

    private function createForbiddenResponse(): Response
    {
        $response = new \GuzzleHttp\Psr7\Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Admin permissions required'
        ]));
        
        return $response
            ->withStatus(403)
            ->withHeader('Content-Type', 'application/json');
    }
}


