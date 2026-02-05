<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

// Initialize AMI connection pooling after autoloader is available
if (file_exists(__DIR__ . '/../includes/amifunctions.inc')) {
    require_once __DIR__ . '/../includes/amifunctions.inc';
    if (class_exists('\SimpleAmiClient')) {
        \SimpleAmiClient::initPool([
            'max_size' => 5,         // Reduced pool size for better performance
            'timeout' => 10          // Shorter timeout for faster failover
        ]);
        
        // Register cleanup on shutdown
        register_shutdown_function([\SimpleAmiClient::class, 'cleanupPool']);
    }
}

// Load environment variables (optional)
try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} catch (Exception $e) {
    // .env file not found, use default values
}

// Ensure default values are set (use null coalescing to avoid PHP 8+ warnings)
$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'production';
$_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? 'false';
$_ENV['USER_FILES_PATH'] = $_ENV['USER_FILES_PATH'] ?? __DIR__ . '/../user_files/';

// Set error reporting based on environment (use null coalescing to avoid warnings)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', '/var/log/php/error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Set timezone
date_default_timezone_set('UTC');

// Configure session for cross-origin requests
$isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
$sessionSecure = $isProduction ? '1' : '0'; // Require HTTPS in production
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', $sessionSecure);
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_domain', '');
ini_set('session.cookie_path', '/supermon-ng');
ini_set('session.cookie_lifetime', '86400'); // 24 hours - match auth controller timeout

// Build container
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/Config/Dependencies.php');
$container = $containerBuilder->build();

// Create app
$app = AppFactory::createFromContainer($container);

// Add middleware
require __DIR__ . '/Config/Middleware.php';

// Add routes
require __DIR__ . '/Config/Routes.php';

return $app;
