<?php

declare(strict_types=1);

use Slim\App;
use Slim\Middleware\MethodOverrideMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/** @var App $app */
global $app;

// Add method override middleware
$app->add(MethodOverrideMiddleware::class);

// Add CORS middleware
$app->add(function (Request $request, RequestHandlerInterface $handler): Response {
    $response = $handler->handle($request);
    
    $corsOrigins = explode(',', $_ENV['CORS_ORIGINS'] ?? 'http://localhost:5173,http://localhost:5174,http://localhost:5175,http://localhost:5176,http://localhost:5177');
    $origin = $request->getHeaderLine('Origin');
    
    // Allow any localhost port for development
    if (in_array($origin, $corsOrigins) || in_array('*', $corsOrigins) || 
        (strpos($origin, 'http://localhost:') === 0 && strpos($origin, 'localhost:') !== false)) {
        $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
    }
    
    $response = $response->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    $response = $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
    
    return $response;
});

// Add error handling middleware
$errorMiddleware = $app->addErrorMiddleware(
    $_ENV['APP_DEBUG'] === 'true',
    true,
    true
);
$errorMiddleware->setErrorHandler(
    \Throwable::class,
    function (Request $request, \Throwable $exception, bool $displayErrorDetails) use ($app) {
        $logger = $app->getContainer()->get(LoggerInterface::class);
        $logger->error('Unhandled exception: ' . $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
        
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'error' => 'Internal Server Error',
            'message' => $displayErrorDetails ? $exception->getMessage() : 'An error occurred'
        ]));
        
        return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json');
    }
);

// Add request logging middleware
$app->add(function (Request $request, RequestHandlerInterface $handler) use ($app): Response {
    $container = $app->getContainer();
    $logger = $container->get(LoggerInterface::class);
    
    $startTime = microtime(true);
    $response = $handler->handle($request);
    $endTime = microtime(true);
    
    $duration = round(($endTime - $startTime) * 1000, 2);
    $method = $request->getMethod();
    $uri = $request->getUri()->getPath();
    $statusCode = $response->getStatusCode();
    
    $logger->info("HTTP Request", [
        'method' => $method,
        'uri' => $uri,
        'status_code' => $statusCode,
        'duration_ms' => $duration,
        'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    return $response;
});

// Add rate limiting middleware
$app->add(function (Request $request, RequestHandlerInterface $handler) use ($app): Response {
    $container = $app->getContainer();
    $cache = $container->get(\Symfony\Contracts\Cache\CacheInterface::class);
    
    $rateLimit = (int)($_ENV['API_RATE_LIMIT'] ?? 100);
    $rateLimitWindow = (int)($_ENV['API_RATE_LIMIT_WINDOW'] ?? 60);
    
    $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
    $cacheKey = "rate_limit:$clientIp";
    
    try {
        $requests = $cache->get($cacheKey, function () {
            return 0;
        });
        
        if ($requests >= $rateLimit) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.'
            ]));
            
            return $response
                ->withStatus(429)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After', $rateLimitWindow);
        }
        
        $cache->get($cacheKey, function () use ($requests) {
            return $requests + 1;
        });
        
        return $handler->handle($request);
        
    } catch (Exception $e) {
        // If cache fails, allow the request to proceed
        return $handler->handle($request);
    }
});

// Add JSON parsing middleware
$app->add(function (Request $request, RequestHandlerInterface $handler): Response {
    $contentType = $request->getHeaderLine('Content-Type');
    
    if (strpos($contentType, 'application/json') !== false) {
        $contents = $request->getBody()->getContents();
        $parsed = json_decode($contents, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            $request = $request->withParsedBody($parsed);
        }
    }
    
    return $handler->handle($request);
});
