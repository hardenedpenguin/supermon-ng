<?php

declare(strict_types=1);

namespace SupermonNg\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SupermonNg\Services\IncludeManagerService;
use SupermonNg\Services\SessionService;
use Psr\Log\LoggerInterface;

class AdminAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private IncludeManagerService $includeService,
        private SessionService $sessionService
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $this->logger->info('Admin Auth middleware - checking admin permissions');

        $user = $this->sessionService->getCurrentUser();
        if ($user === null || $user === '') {
            $this->logger->warning('Admin Auth middleware - no authenticated user');
            return $this->createUnauthorizedResponse();
        }

        if (!$this->includeService->userHasAdminPermission($user)) {
            $this->logger->warning('Admin Auth middleware - user lacks admin permissions', ['user' => $user]);
            return $this->createForbiddenResponse();
        }

        $this->logger->info('Admin Auth middleware - user authorized', ['user' => $user]);
        return $handler->handle($request);
    }

    private function createUnauthorizedResponse(): Response
    {
        $response = new \Slim\Psr7\Response();
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
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Admin permissions required'
        ]));
        return $response
            ->withStatus(403)
            ->withHeader('Content-Type', 'application/json');
    }
}
