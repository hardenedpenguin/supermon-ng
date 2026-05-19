<?php

declare(strict_types=1);

namespace SupermonNg\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SupermonNg\Services\SessionService;

/**
 * Requires an authenticated session (or HTTP auth). Anonymous nolog/allmon browsing
 * uses routes that do not attach this middleware.
 */
final class RequireAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionService $sessionService
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if (!$this->sessionService->isAuthenticated()) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Authentication required',
            ]));

            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
