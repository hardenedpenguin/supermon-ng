<?php

declare(strict_types=1);

use Slim\App;
use Slim\Middleware\MethodOverrideMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use SupermonNg\Support\AppBasePath;

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
            'path' => AppBasePath::cookiePath(),
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
        
        // Skip CSRF validation for auth endpoints (login, etc.) and bubble chart.
        // DVSwitch is intentionally NOT exempt: it changes state (mode switch,
        // bridge restart) and the frontend already sends the token, so a
        // session-cookie CSRF must not be able to reach it.
        $normalizedUri = AppBasePath::stripPrefix($uri);
        $skipPaths = [
            '/api/v1/auth/login',
            '/api/v1/auth/logout',
            '/api/v1/auth/me',
            '/api/v1/config/bubblechart',
        ];
        if (!in_array($uri, $skipPaths, true) && !in_array($normalizedUri, $skipPaths, true)) {
            $parsedBody = $request->getParsedBody();
            $headerToken = $request->getHeaderLine('X-CSRF-Token');
            $token = $headerToken !== '' ? $headerToken : (is_array($parsedBody) ? ($parsedBody['csrf_token'] ?? '') : '');
            
            if (empty($token) || !isset($_SESSION['csrf_token']) ||
                !hash_equals($_SESSION['csrf_token'], $token)) {
                if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
                    error_log("CSRF validation failed: method=$method, uri=$uri");
                }
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
    
    $corsOrigins = array_map('trim', explode(',', $_ENV['CORS_ORIGINS'] ?? 'http://localhost:5173,http://localhost:5174,http://localhost:5175,http://localhost:5176,http://localhost:5177'));
    $origin = $request->getHeaderLine('Origin');
    // Explicit whitelist match (* is not a valid browser Origin string, so it never matches $origin)
    $explicitMatch = $origin !== '' && in_array($origin, $corsOrigins, true);
    // Localhost any port for dev (safe reflected origin only for localhost)
    $localhostDev = $origin !== '' && str_starts_with($origin, 'http://localhost:');

    // Do not treat CORS_ORIGINS=* as "reflect any Origin" while Allow-Credentials is true (CSRF/session risk).
    $allowThisOrigin = $explicitMatch || $localhostDev;

    if ($allowThisOrigin && $origin !== '') {
        $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
    }
    
    $response = $response->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
    $response = $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
    
    return $response;
});

// Add error handling middleware (disable detailed errors in production)
$isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
$errorMiddleware = $app->addErrorMiddleware(
    !$isProduction,
    !$isProduction,
    true
);
$errorMiddleware->setErrorHandler(
    \Throwable::class,
    function (
        Request $request,
        \Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ) use ($app): Response {
        if ($logErrors) {
            try {
                $logger = $app->getContainer()->get(LoggerInterface::class);
                $logger->error('Unhandled exception: ' . $exception->getMessage(), [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $logErrorDetails ? $exception->getTraceAsString() : '',
                ]);
            } catch (\Throwable) {
                // Never fail the error response because logging failed
            }
        }

        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'error' => 'Internal Server Error',
            'message' => $displayErrorDetails ? $exception->getMessage() : 'An error occurred',
        ]));

        return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json');
    },
    true
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

// Add rate limiting middleware (exempts lightweight health/bootstrap paths).
// Uses APCu when available so counts are shared across PHP-FPM workers and
// survive between requests; otherwise falls back to an in-process array,
// which is only effective under long-running (non-FPM) runtimes.
$app->add(function (Request $request, RequestHandlerInterface $handler): Response {
    static $rateBuckets = [];

    $uri = $request->getUri()->getPath();
    $normalizedUri = AppBasePath::stripPrefix($uri);
    $exemptPaths = [
        '/health',
        '/api/v1/bootstrap',
        '/api/v1/version/check',
        '/api/v1/config/system-info',
        '/api/v1/astdb/health',
        '/api/v1/system/health',
    ];

    if (in_array($uri, $exemptPaths, true)
        || in_array($normalizedUri, $exemptPaths, true)
        || str_ends_with($normalizedUri, '/health')) {
        return $handler->handle($request);
    }

    $rateLimit = (int) ($_ENV['API_RATE_LIMIT'] ?? 200);
    $rateLimitWindow = (int) ($_ENV['API_RATE_LIMIT_WINDOW'] ?? 60);
    $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
    $currentTime = time();

    $useApcu = function_exists('apcu_enabled') && apcu_enabled();
    $apcuKey = 'sng_rl_' . $clientIp;

    if ($useApcu) {
        $bucket = apcu_fetch($apcuKey);
        if (!is_array($bucket)) {
            $bucket = ['count' => 0, 'window_start' => $currentTime];
        }
    } else {
        $bucket = $rateBuckets[$clientIp] ?? ['count' => 0, 'window_start' => $currentTime];
    }

    if (($currentTime - $bucket['window_start']) >= $rateLimitWindow) {
        $bucket = ['count' => 0, 'window_start' => $currentTime];
    }

    if ($bucket['count'] >= $rateLimit) {
        $retryAfter = max(1, $rateLimitWindow - ($currentTime - $bucket['window_start']));
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $retryAfter,
        ]));

        return $response
            ->withStatus(429)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Retry-After', (string) $retryAfter)
            ->withHeader('X-RateLimit-Limit', (string) $rateLimit)
            ->withHeader('X-RateLimit-Remaining', '0');
    }

    $bucket['count']++;
    if ($useApcu) {
        apcu_store($apcuKey, $bucket, $rateLimitWindow * 2);
    } else {
        $rateBuckets[$clientIp] = $bucket;
    }

    $response = $handler->handle($request);
    $remaining = max(0, $rateLimit - $bucket['count']);

    return $response
        ->withHeader('X-RateLimit-Limit', (string) $rateLimit)
        ->withHeader('X-RateLimit-Remaining', (string) $remaining)
        ->withHeader('X-RateLimit-Reset', (string) ($bucket['window_start'] + $rateLimitWindow));
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
        $normalizedUri = AppBasePath::stripPrefix($uri);
        $cacheableApiPaths = [
            '/api/v1/bootstrap',
            '/api/v1/version/check',
            '/api/v1/config/system-info',
        ];
        $preserveCache = in_array($uri, $cacheableApiPaths, true)
            || in_array($normalizedUri, $cacheableApiPaths, true);
        if (!$preserveCache) {
            $response = $response->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response = $response->withHeader('Pragma', 'no-cache');
            $response = $response->withHeader('Expires', '0');
        }
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
