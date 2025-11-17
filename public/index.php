<?php
/**
 * Supermon-ng API Entry Point
 * 
 * This file serves as the main entry point for the API backend.
 * It initializes the Slim application and handles all API requests.
 */

// Set error reporting for development (should be disabled in production)
// In production, disable display_errors to prevent HTML output in JSON responses
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../user_files/logs/php_errors.log');

// Include the bootstrap file
require_once __DIR__ . '/../src/bootstrap.php';

// Set base path if running under /supermon-ng subdirectory
// .htaccess routes /supermon-ng/api/* to this file, so we need to strip the prefix
$request = \Slim\Psr7\Factory\ServerRequestFactory::createFromGlobals();
$uri = $request->getUri();
$path = $uri->getPath();

// If the path starts with /supermon-ng, set that as the base path
// This tells Slim to strip /supermon-ng from route matching
if (strpos($path, '/supermon-ng') === 0) {
    $app->setBasePath('/supermon-ng');
}

// Run the application
$app->run();
