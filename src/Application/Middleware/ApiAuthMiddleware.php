<?php

declare(strict_types=1);

namespace SupermonNg\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SupermonNg\Services\IncludeManagerService;
use Psr\Log\LoggerInterface;

class ApiAuthMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;
    private IncludeManagerService $includeService;

    public function __construct(LoggerInterface $logger, IncludeManagerService $includeService)
    {
        $this->logger = $logger;
        $this->includeService = $includeService;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $this->logger->info('API Auth middleware - checking authentication');
        
        // Get user from session
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            $this->logger->warning('API Auth middleware - no user session found');
            return $this->createUnauthorizedResponse();
        }

        // Check if user has valid permissions
        if (!$this->hasValidPermissions($user)) {
            $this->logger->warning('API Auth middleware - user lacks valid permissions', ['user' => $user]);
            return $this->createForbiddenResponse();
        }

        $this->logger->info('API Auth middleware - user authorized', ['user' => $user]);
        return $handler->handle($request);
    }

    private function hasValidPermissions(string $user): bool
    {
        // Include necessary files using optimized service
        $this->includeService->includeCommonInc();
        
        // Check if user exists in the system
        $validUsers = [];
        if (isset($GLOBALS['valid_users'])) {
            $validUsers = $GLOBALS['valid_users'];
        }
        
        return in_array($user, $validUsers, true);
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
            'error' => 'Access forbidden'
        ]));
        
        return $response
            ->withStatus(403)
            ->withHeader('Content-Type', 'application/json');
    }
}


