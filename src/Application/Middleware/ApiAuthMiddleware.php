<?php

declare(strict_types=1);

namespace SupermonNg\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class ApiAuthMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $this->logger->info('API Auth middleware - placeholder implementation');
        
        // TODO: Implement actual authentication logic
        // For now, just pass through to allow testing
        
        return $handler->handle($request);
    }
}


