#!/usr/bin/env php
<?php
/**
 * Database Auto-Update CLI Script
 * 
 * This script checks if the database needs updating (based on 3-hour interval)
 * and performs the update if needed. Designed to be run via cron or systemd timer.
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Include the bootstrap file
require_once BASE_PATH . '/src/bootstrap.php';

use Psr\Container\ContainerInterface;
use SupermonNg\Services\DatabaseGenerationService;
use Psr\Log\LoggerInterface;

try {
    // Get container
    /** @var ContainerInterface $container */
    global $container;
    
    if (!$container) {
        throw new Exception("Container not initialized");
    }
    
    // Get logger
    $logger = $container->get(LoggerInterface::class);
    $logger->info('Starting database auto-update check from CLI');
    
    // Get database service
    $databaseService = $container->get(DatabaseGenerationService::class);
    
    // Check and perform automatic update
    $updated = $databaseService->checkAndPerformAutomaticUpdate();
    
    if ($updated) {
        $logger->info('Database auto-update completed successfully');
        echo "SUCCESS: Database updated\n";
        exit(0);
    } else {
        $logger->info('Database auto-update not needed at this time');
        echo "INFO: No update needed\n";
        exit(0);
    }
    
} catch (Exception $e) {
    if (isset($logger)) {
        $logger->error('Database auto-update failed', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
    
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

