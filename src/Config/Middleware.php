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

// Add combined session and CSRF middleware
$app->add(function (Request $request, RequestHandlerInterface $handler): Response {
    // Only configure and start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_name('supermon61');

        // Detect HTTPS for secure cookies
        $isSecure = false;
        $serverParams = $request->getServerParams();
        if (($serverParams['HTTPS'] ?? '') === 'on' ||
            ($serverParams['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ||
            ($serverParams['HTTP_X_FORWARDED_SSL'] ?? '') === 'on' ||
            ($serverParams['SERVER_PORT'] ?? '') == '443') {
            $isSecure = true;
        }

        session_set_cookie_params([
            'lifetime' => 86400, // 24 hours (86400 seconds) - match auth controller timeout
            'path' => '/supermon-ng',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }
    
    // Handle session timeout (24 hours) - only if session is active
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
        } elseif (time() - $_SESSION['last_activity'] > 86400) { // 24 hours
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['last_activity'] = time();
        }
        $_SESSION['last_activity'] = time();
        
        // Initialize session variables
        $_SESSION['sm61loggedin'] = $_SESSION['sm61loggedin'] ?? false;
        
        // Generate CSRF token if not exists
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
    
    // CSRF validation for POST, PUT, DELETE, and PATCH requests (all state-changing methods)
    $method = $request->getMethod();
    if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
        $uri = $request->getUri()->getPath();
        
        // Skip CSRF validation for auth endpoints (login, etc.) and bubble chart
        $skipPaths = ['/api/auth/login', '/api/auth/logout', '/api/auth/me', '/api/config/bubblechart'];
        if (!in_array($uri, $skipPaths)) {
            $parsedBody = $request->getParsedBody();
            // For DELETE requests, body might be empty, so check header first
            $token = $request->getHeaderLine('X-CSRF-Token') ?? ($parsedBody['csrf_token'] ?? '');
            
            if (empty($token) || !isset($_SESSION['csrf_token']) || 
                !hash_equals($_SESSION['csrf_token'], $token)) {
                
                // Debug logging
                error_log("CSRF validation failed: method=$method, token='$token', session_token='" . ($_SESSION['csrf_token'] ?? 'null') . "', uri='$uri', session_id='" . session_id() . "'");
                
                $response = new \Slim\Psr7\Response();
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'CSRF token validation failed. Please refresh the page and try again.'
                ]));
                return $response
                    ->withStatus(403)
                    ->withHeader('Content-Type', 'application/json');
            }
        }
    }
    
    return $handler->handle($request);
});

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
    true, // Enable error details for debugging
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

// Add request logging middleware (optimized for production)
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
    
    // Only log slow requests or errors in production
    if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
        if ($statusCode >= 400 || $duration > 1000) { // Log errors or requests > 1 second
            $logger->warning("Slow/Error Request", [
                'method' => $method,
                'uri' => $uri,
                'status_code' => $statusCode,
                'duration_ms' => $duration,
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
    } else {
        // Full logging in development
        $logger->info("HTTP Request", [
            'method' => $method,
            'uri' => $uri,
            'status_code' => $statusCode,
            'duration_ms' => $duration,
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
    
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
        // Get current request count, defaulting to 0 if not set
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
        
        // Increment request count and set with TTL
        // Delete old entry and create new one with incremented value and expiration
        $cache->delete($cacheKey);
        $cache->get($cacheKey, function () use ($requests) {
            return $requests + 1;
        }, $rateLimitWindow);
        
        return $handler->handle($request);
        
    } catch (Exception $e) {
        // If cache fails, allow the request to proceed
        return $handler->handle($request);
    }
});

// Add HTTP caching middleware
$app->add(function (Request $request, RequestHandlerInterface $handler): Response {
    $response = $handler->handle($request);
    
    $uri = $request->getUri()->getPath();
    $method = $request->getMethod();
    
    // Only cache GET requests
    if ($method !== 'GET') {
        return $response;
    }
    
    // Cache static assets for 1 year
    if (strpos($uri, '/assets/') !== false || 
        strpos($uri, '.css') !== false || 
        strpos($uri, '.js') !== false || 
        strpos($uri, '.png') !== false || 
        strpos($uri, '.jpg') !== false || 
        strpos($uri, '.ico') !== false) {
        
        $response = $response->withHeader('Cache-Control', 'public, max-age=31536000, immutable');
        $response = $response->withHeader('Expires', gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
    }
    // Cache API responses for different durations based on endpoint
    elseif (strpos($uri, '/api/') !== false) {
        // Skip ETag generation for API endpoints to avoid consuming response body
        // API responses are dynamic and shouldn't be cached anyway
        $response = $response->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response = $response->withHeader('Pragma', 'no-cache');
        $response = $response->withHeader('Expires', '0');
    }
    
    return $response;
});

// Add JSON parsing middleware
$app->add(function (Request $request, RequestHandlerInterface $handler): Response {
    $contentType = $request->getHeaderLine('Content-Type');
    
    if (strpos($contentType, 'application/json') !== false) {
        $contents = $request->getBody()->getContents();
        $parsed = json_decode($contents, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            // Restore the request body stream (getContents() consumes it)
            // This ensures the body is still available if needed elsewhere
            $request = $request->withBody(new \Slim\Psr7\Stream(fopen('php://temp', 'r+')));
            $request->getBody()->write($contents);
            $request = $request->withParsedBody($parsed);
        } else {
            // If JSON parsing failed, restore the body anyway
            $request = $request->withBody(new \Slim\Psr7\Stream(fopen('php://temp', 'r+')));
            $request->getBody()->write($contents);
        }
    }
    
    return $handler->handle($request);
});
