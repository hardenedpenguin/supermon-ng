#!/usr/bin/env php
<?php
/**
 * WebSocket Server Entry Point
 * 
 * Single entry point that manages all WebSocket servers (one per node).
 * Matches Allmon3's architecture: one service, one process, multiple WebSocket servers.
 * 
 * Usage: php bin/websocket-server.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use SupermonNg\Services\WebSocketServerManager;
use SupermonNg\Services\AllStarConfigService;
use SupermonNg\Services\AstdbCacheService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;

// Set up error handling
// Suppress PHP 8.2+ deprecation warnings from Ratchet library (dynamic properties)
// These are library issues, not application issues
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Create logger
$logger = new Logger('supermon-ng-websocket');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));
$logger->pushHandler(new SyslogHandler('supermon-ng-websocket', LOG_USER, Logger::INFO));

try {
    // Get base port from environment or use default
    $basePort = (int)($_ENV['WEBSOCKET_BASE_PORT'] ?? getenv('WEBSOCKET_BASE_PORT') ?: 8105);
    
    // Create configuration service
    $userFilesPath = __DIR__ . '/../user_files/';
    $configService = new AllStarConfigService($logger, $userFilesPath);
    
    // Create ASTDB cache service
    // Check both user_files and root directory for astdb.txt
    $astdbFile = $userFilesPath . 'astdb.txt';
    if (!file_exists($astdbFile)) {
        // Fallback to root directory (legacy location)
        $astdbFile = __DIR__ . '/../astdb.txt';
    }
    $astdbService = new AstdbCacheService($logger, $astdbFile);
    
    // Create WebSocket server manager
    $manager = new WebSocketServerManager($logger, $configService, $astdbService, $basePort);
    
    // Set up signal handlers for graceful shutdown
    $manager->setupSignalHandlers();
    
    // Start all WebSocket servers
    $manager->start();
    
    $logger->info("WebSocket server started", [
        'base_port' => $basePort,
        'node_ports' => $manager->getAllNodePorts()
    ]);
    
    $logger->info("About to start event loop - this will block");
    
    // Run the event loop (blocks until stopped)
    try {
        $manager->run();
    } catch (Throwable $e) {
        $logger->error("Event loop error", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
    
    $logger->info("Event loop exited");
    
} catch (Throwable $e) {
    if (isset($logger)) {
        $logger->error("Fatal error in WebSocket server", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    } else {
        error_log("Fatal error in WebSocket server: " . $e->getMessage());
    }
    exit(1);
}

