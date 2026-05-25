<?php
/**
 * Supermon-ng API Entry Point
 *
 * This file serves as the main entry point for the API backend.
 * It initializes the Slim application and handles all API requests.
 */

ini_set('log_errors', '1');

$app = require_once __DIR__ . '/../src/bootstrap.php';

use SupermonNg\Support\AppBasePath;

$request = \Slim\Psr7\Factory\ServerRequestFactory::createFromGlobals();
$path = $request->getUri()->getPath();

$slimBase = AppBasePath::slimBaseForRequest($path);
if ($slimBase !== null) {
    $app->setBasePath($slimBase);
}

$app->run();
