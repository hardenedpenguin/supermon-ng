<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables (optional)
try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} catch (Exception $e) {
    // .env file not found, use default values
    $_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'production';
    $_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? 'false';
    $_ENV['JWT_SECRET'] = $_ENV['JWT_SECRET'] ?? 'your-secret-key';
    $_ENV['USER_FILES_PATH'] = $_ENV['USER_FILES_PATH'] ?? '/var/www/html/supermon-ng/user_files/';
}

// Set error reporting based on environment
if ($_ENV['APP_ENV'] === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Set timezone
date_default_timezone_set('UTC');

// Configure session for cross-origin requests
ini_set('session.cookie_samesite', 'Lax'); // Use Lax for development
ini_set('session.cookie_secure', '0'); // Set to 1 in production with HTTPS
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
