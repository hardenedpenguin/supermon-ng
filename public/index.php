<?php
/**
 * Supermon-ng API Entry Point
 * 
 * This file serves as the main entry point for the API backend.
 * It initializes the Slim application and handles all API requests.
 */

// Set error reporting for development (should be disabled in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the bootstrap file
require_once __DIR__ . '/../src/bootstrap.php';

// Run the application
$app->run();
